"""Exercise release failure boundaries using real disposable Git histories."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

REPO = Path(__file__).resolve().parents[2]


class DeploymentTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="adzbyte-deploy-test-")
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.app = self.root / "app"
        self.ops = self.root / "operations"
        self.bin = self.root / "bin"
        for directory in [self.app, self.ops, self.bin, self.app / "storage/framework", self.app / "scripts/deployment"]:
            directory.mkdir(parents=True, exist_ok=True)
        self.log = self.root / "calls"
        self.environment = dict(os.environ, CALLS=str(self.log), QA_APP=str(self.app), PATH=str(self.bin) + os.pathsep + os.environ["PATH"])
        if not shutil.which("flock"):
            # macOS lacks util-linux; use its actual flock syscall on inherited fds.
            self.write_executable(self.bin / "flock", "#!/usr/bin/env python3\n" + '''
import fcntl, sys, time
arguments=sys.argv[1:]
fd=int(arguments[-1])
if "--unlock" in arguments:
    fcntl.flock(fd, fcntl.LOCK_UN)
else:
    duration=float(arguments[arguments.index("--timeout")+1])
    deadline=time.monotonic()+duration
    while True:
        try:
            fcntl.flock(fd, fcntl.LOCK_EX|fcntl.LOCK_NB)
            break
        except BlockingIOError:
            if time.monotonic()>=deadline:
                sys.exit(1)
            time.sleep(0.01)
''')
        self.write_executable(self.bin / "php", '''#!/usr/bin/env bash
set -eu
printf 'php %s\\n' "$*" >> "$CALLS"
if [[ "$1" == *backup.php ]]; then
    [[ "${FAIL_PHASE:-}" != backup ]] || exit 1
    cp "$2/.env" "$3/environment.before"
    printf 'encrypted fixture' > "$3/database.sql.enc"
elif [[ "$1" == *verify.php ]]; then
    [[ "${FAIL_PHASE:-}" != verify ]] || exit 1
elif [[ "$1" == artisan ]]; then
    case "$2" in
        down) touch "$QA_APP/storage/framework/down" ;;
        up) rm -f "$QA_APP/storage/framework/down" ;;
        migrate) [[ "${FAIL_PHASE:-}" != migration ]] || exit 1 ;;
        optimize) printf 'synthetic compiled secrets' > "$QA_APP/bootstrap/cache/config.php" ;;
    esac
fi
''')
        self.write_executable(self.bin / "composer2", '#!/usr/bin/env bash\nprintf "composer %s\\n" "$*" >> "$CALLS"\n')
        self.write_executable(self.bin / "curl", '''#!/usr/bin/env bash
set -eu
url="${!#}"
if [[ "${FAIL_PHASE:-}" == http && "$url" == */up ]]; then printf 500
elif [[ "$url" == https://app.adzbyte.com/ ]]; then printf '302 https://app.adzbyte.com/login'
elif [[ "$url" == */.env || "$url" == */storage/logs/laravel.log ]]; then printf 403
else printf 200; fi
''')
        for file in ["hostinger-deploy.sh", "backup.php", "verify.php"]:
            source = (REPO / "scripts/deployment" / file).read_text()
            source = source.replace("/usr/bin/php", str(self.bin / "php")).replace("/usr/local/bin/composer2", str(self.bin / "composer2"))
            (self.root / file).write_text(source)
        shutil.copy(REPO / "scripts/hostinger-after-pull.sh", self.app / "scripts/hostinger-after-pull.sh")
        shutil.copy(REPO / "scripts/deployment/http-check.sh", self.app / "scripts/deployment/http-check.sh")
        (self.app / "public/build/assets").mkdir(parents=True)
        (self.app / "bootstrap/cache").mkdir(parents=True)
        (self.app / "bootstrap/cache/config.php").write_text("old synthetic compiled secrets")
        (self.app / "bootstrap/cache/config.php").chmod(0o644)
        (self.app / "public/build/assets/app-fixture.css").write_text("body {}")
        (self.app / ".gitignore").write_text(".env\nstorage/\nbootstrap/cache/\n")
        (self.app / ".env").write_text("APP_KEY=synthetic-key-that-must-survive\nDB_PASSWORD=synthetic-only\n")
        (self.app / ".env").chmod(0o644)
        (self.app / "storage/customer-records").write_text("retained customer fixture")
        self.git("init", "-q")
        self.git("config", "user.email", "qa@example.test")
        self.git("config", "user.name", "Synthetic QA")
        self.git("add", ".")
        self.git("commit", "-qm", "Previous release")
        self.before = self.git("rev-parse", "HEAD").strip()
        (self.app / "release-marker").write_text("new code")
        self.git("add", ".")
        self.git("commit", "-qm", "Reviewed source")
        self.source = self.git("rev-parse", "HEAD").strip()
        self.git("commit", "--allow-empty", "-qm", "Deploy " + self.source[:12])
        self.target = self.git("rev-parse", "HEAD").strip()
        self.git("update-ref", "refs/adzbyte-ci/deploy", self.target)
        self.git("checkout", "-q", "--detach", self.before)

    @staticmethod
    def write_executable(path, content):
        path.write_text(content)
        path.chmod(0o700)

    def git(self, *arguments):
        return subprocess.check_output(["git", "-C", str(self.app), *arguments], text=True, stderr=subprocess.STDOUT)

    def deploy(self, failure=None):
        env = dict(self.environment)
        if failure:
            env["FAIL_PHASE"] = failure
        return subprocess.run(["bash", str(self.root / "hostinger-deploy.sh"), str(self.app), str(self.ops), self.source, self.target], env=env, text=True, capture_output=True, timeout=10)

    def status(self):
        return json.loads((self.ops / "last-release.json").read_text())

    def test_success_preserves_customer_data_environment_and_orders_release_steps(self):
        original = (self.app / ".env").read_bytes()
        result = self.deploy()
        self.assertEqual(0, result.returncode, result.stdout + result.stderr)
        self.assertEqual(self.target, self.git("rev-parse", "HEAD").strip())
        self.assertEqual(original, (self.app / ".env").read_bytes())
        self.assertEqual(0o600, (self.app / ".env").stat().st_mode & 0o777)
        self.assertEqual(0o600, (self.app / "bootstrap/cache/config.php").stat().st_mode & 0o777)
        self.assertEqual("retained customer fixture", (self.app / "storage/customer-records").read_text())
        self.assertFalse((self.app / "storage/framework/down").exists())
        calls = self.log.read_text()
        self.assertLess(calls.index("backup.php"), calls.index("artisan down"))
        self.assertLess(calls.index("artisan migrate"), calls.index("artisan optimize"))
        self.assertLess(calls.index("artisan queue:restart"), calls.index("artisan up"))
        self.assertEqual("success", self.status()["state"])
        self.assertTrue(Path(self.status()["backup"], "database.sql.enc").exists())

    def test_backup_failure_keeps_previous_code_and_site_available(self):
        result = self.deploy("backup")
        self.assertNotEqual(0, result.returncode)
        self.assertEqual(self.before, self.git("rev-parse", "HEAD").strip())
        self.assertFalse((self.app / "storage/framework/down").exists())
        self.assertNotIn("artisan down", self.log.read_text())
        self.assertEqual("backup", self.status()["phase"])

    def test_migration_failure_retains_backup_and_maintenance_without_data_rollback(self):
        result = self.deploy("migration")
        self.assertNotEqual(0, result.returncode)
        self.assertTrue((self.app / "storage/framework/down").exists())
        self.assertEqual(self.target, self.git("rev-parse", "HEAD").strip())
        self.assertNotIn("artisan up", self.log.read_text())
        self.assertEqual("retained customer fixture", (self.app / "storage/customer-records").read_text())
        self.assertEqual("failed", self.status()["state"])
        self.assertTrue(Path(self.status()["backup"], "environment.before").exists())

    def test_invariant_failure_does_not_reopen_partially_changed_site(self):
        result = self.deploy("verify")
        self.assertNotEqual(0, result.returncode)
        self.assertTrue((self.app / "storage/framework/down").exists())
        self.assertEqual("verify", self.status()["phase"])

    def test_failed_http_check_is_reported_without_reverting_database_or_code(self):
        result = self.deploy("http")
        self.assertNotEqual(0, result.returncode)
        self.assertEqual("failed", self.status()["state"])
        self.assertEqual("http", self.status()["phase"])
        self.assertEqual(self.target, self.git("rev-parse", "HEAD").strip())

    def test_dirty_tracked_source_is_rejected_before_backup(self):
        (self.app / ".gitignore").write_text("changed tracked file")
        result = self.deploy()
        self.assertNotEqual(0, result.returncode)
        self.assertFalse(self.log.exists())
        self.assertEqual(self.before, self.git("rev-parse", "HEAD").strip())

    def test_wrong_deploy_target_is_rejected_before_backup(self):
        self.git("update-ref", "refs/adzbyte-ci/deploy", self.before)
        result = self.deploy()
        self.assertNotEqual(0, result.returncode)
        self.assertFalse(self.log.exists())

    def test_symlink_environment_is_rejected(self):
        (self.app / ".env").rename(self.root / "protected-env")
        (self.app / ".env").symlink_to(self.root / "protected-env")
        result = self.deploy()
        self.assertNotEqual(0, result.returncode)
        self.assertFalse(self.log.exists())

    def test_active_runtime_work_blocks_checkout_and_backup(self):
        # Use the same cross-process flock contract as both production crons.
        ready = self.root / "lock-ready"
        holder = subprocess.Popen([shutil.which("python3"), "-c", "import fcntl,pathlib,sys,time; f=open(sys.argv[1],'w'); fcntl.flock(f,fcntl.LOCK_SH); pathlib.Path(sys.argv[2]).touch(); time.sleep(10)", str(self.app / "storage/framework/deployment.lock"), str(ready)])
        self.addCleanup(lambda: holder.poll() is None and holder.terminate())
        import time
        deadline = time.monotonic() + 3
        while not ready.exists() and time.monotonic() < deadline:
            time.sleep(0.01)
        self.assertTrue(ready.exists())
        path = self.root / "hostinger-deploy.sh"
        path.write_text(path.read_text().replace("--timeout 150", "--timeout 0"))
        result = self.deploy()
        self.assertNotEqual(0, result.returncode)
        self.assertEqual("drain", self.status()["phase"])
        self.assertEqual(self.before, self.git("rev-parse", "HEAD").strip())
        self.assertFalse(self.log.exists())
        holder.terminate()
        holder.wait(timeout=3)

    def test_ssh_entrypoint_rejects_shell_commands_and_malformed_shas(self):
        entry = REPO / "scripts/deployment/ssh-entrypoint.sh"
        for command in ["", "sh", "deploy main deploy", "deploy " + "a" * 40 + " " + "b" * 40 + "; id", "preflight; id", "deploy $(id) HEAD"]:
            with self.subTest(command=command):
                result = subprocess.run(["bash", str(entry)], env=dict(self.environment, SSH_ORIGINAL_COMMAND=command), capture_output=True, text=True)
                self.assertEqual(64, result.returncode)


class BackupTests(unittest.TestCase):
    def test_real_encrypted_backup_round_trip_and_cleanup(self):
        self.check_backup(False)

    def test_dump_failure_removes_plaintext_and_temporary_credentials(self):
        self.check_backup(True)

    def check_backup(self, fail):
        with tempfile.TemporaryDirectory(prefix="adzbyte-backup-test-") as temporary:
            root = Path(temporary)
            app = root / "app"
            backup = root / "backup"
            for directory in [app / "vendor", app / "bootstrap", backup]:
                directory.mkdir(parents=True)
            backup.chmod(0o700)
            (app / ".env").write_text("APP_KEY=synthetic-only")
            (app / "vendor/autoload.php").write_text('''<?php
function app(){return new class {function environment($name){return $name==='production';}};}
function config($name){return match($name){'app.debug'=>false,'database.default'=>'mysql','app.key'=>'synthetic-only','database.connections.mysql'=>['username'=>'synthetic','password'=>'synthetic-secret','host'=>'localhost','port'=>3306,'database'=>'fixture'],default=>null};}
''')
            (app / "bootstrap/app.php").write_text("<?php return new class {function make($name){return new class {function bootstrap(){}};}};")
            dump = root / "mysqldump"
            dump.write_text('#!/bin/sh\n' + ('printf "partial dump"; exit 1\n' if fail else 'printf "CREATE TABLE fixture(id INTEGER); INSERT INTO fixture VALUES(1);"\n'))
            dump.chmod(0o700)
            script = root / "backup.php"
            script.write_text((REPO / "scripts/deployment/backup.php").read_text().replace("/usr/bin/mysqldump", str(dump)).replace("/usr/bin/openssl", shutil.which("openssl")))
            result = subprocess.run([shutil.which("php"), str(script), str(app), str(backup), "a" * 40, "b" * 40], capture_output=True, text=True, umask=0o077)
            self.assertFalse((backup / "client.cnf").exists())
            self.assertFalse((backup / "database.sql.tmp").exists())
            self.assertNotIn("synthetic-secret", result.stdout + result.stderr)
            if fail:
                self.assertNotEqual(0, result.returncode)
            else:
                self.assertEqual(0, result.returncode, result.stdout + result.stderr)
                metadata = json.loads((backup / "metadata.json").read_text())
                self.assertTrue(metadata["encryption_round_trip_verified"])
                for file in backup.iterdir():
                    self.assertEqual(0o600, file.stat().st_mode & 0o777, str(file))
                self.assertNotIn(b"CREATE TABLE", (backup / "database.sql.enc").read_bytes())


class CronWrapperTests(unittest.TestCase):
    def test_only_fixed_scheduler_and_worker_commands_are_allowed_under_shared_lock(self):
        with tempfile.TemporaryDirectory(prefix="adzbyte-cron-test-") as directory:
            root = Path(directory)
            capture = root / "capture"
            capture.write_text('#!/usr/bin/env python3\nimport json,sys\nprint(json.dumps(sys.argv[1:]))\n')
            capture.chmod(0o700)
            wrapper = root / "cron-run.sh"
            wrapper.write_text((REPO / "scripts/deployment/cron-run.sh").read_text().replace("/usr/bin/flock", str(capture)))
            for mode, command in [("scheduler", "schedule:run"), ("worker", "queue:work")]:
                result = subprocess.run(["bash", str(wrapper), mode], capture_output=True, text=True)
                self.assertEqual(0, result.returncode, result.stderr)
                arguments = json.loads(result.stdout)
                self.assertEqual(["--shared", "--nonblock"], arguments[:2])
                self.assertTrue(arguments[2].endswith("/storage/framework/deployment.lock"))
                self.assertEqual("/usr/bin/php", arguments[3])
                self.assertTrue(arguments[4].endswith("/public_html/artisan"))
                self.assertEqual(command, arguments[5])
                if mode == "worker":
                    self.assertIn("--timeout=60", arguments)
                    self.assertIn("--max-time=50", arguments)
            for arguments in [[], ["migrate"], ["worker", "extra"]]:
                result = subprocess.run(["bash", str(wrapper), *arguments], capture_output=True, text=True)
                self.assertEqual(64, result.returncode)


class WebRequestDrainTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="adzbyte-web-lock-test-")
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        (self.root / "bootstrap").mkdir()
        (self.root / "storage/framework").mkdir(parents=True)
        self.bootstrap = self.root / "bootstrap/deployment-lock.php"
        shutil.copy(REPO / "bootstrap/deployment-lock.php", self.bootstrap)
        self.lock = self.root / "storage/framework/deployment.lock"

    def test_request_cannot_boot_during_exclusive_deployment(self):
        import fcntl
        with self.lock.open("w") as lock:
            fcntl.flock(lock, fcntl.LOCK_EX)
            result = subprocess.run([shutil.which("php"), "-r", "require $argv[1]; echo 'APPLICATION_BOOTED';", str(self.bootstrap)], capture_output=True, text=True)
            self.assertNotIn("APPLICATION_BOOTED", result.stdout)
            self.assertIn("scheduled update", result.stdout)
        result = subprocess.run([shutil.which("php"), "-r", "require $argv[1]; echo 'APPLICATION_BOOTED';", str(self.bootstrap)], capture_output=True, text=True)
        self.assertIn("APPLICATION_BOOTED", result.stdout)

    def test_later_shutdown_work_still_holds_the_request_lock(self):
        import fcntl
        import time
        ready = self.root / "shutdown-ready"
        code = "require $argv[1]; register_shutdown_function(function() use($argv) { file_put_contents($argv[2], 'ready'); usleep(2000000); });"
        process = subprocess.Popen([shutil.which("php"), "-r", code, str(self.bootstrap), str(ready)])
        try:
            deadline = time.monotonic() + 3
            while not ready.exists() and time.monotonic() < deadline:
                time.sleep(0.01)
            self.assertTrue(ready.exists())
            with self.lock.open("r+") as lock:
                with self.assertRaises(BlockingIOError):
                    fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
                process.wait(timeout=4)
                fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        finally:
            if process.poll() is None:
                process.terminate()
                process.wait()

    def test_existing_request_holds_shared_lock_until_process_shutdown(self):
        import fcntl
        import time
        ready = self.root / "request-ready"
        process = subprocess.Popen([shutil.which("php"), "-r", "require $argv[1]; file_put_contents($argv[2], 'ready'); usleep(2000000);", str(self.bootstrap), str(ready)])
        try:
            deadline = time.monotonic() + 3
            while not ready.exists() and time.monotonic() < deadline:
                time.sleep(0.01)
            self.assertTrue(ready.exists())
            with self.lock.open("r+") as lock:
                with self.assertRaises(BlockingIOError):
                    fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
                process.wait(timeout=4)
                fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        finally:
            if process.poll() is None:
                process.terminate()
                process.wait()


if __name__ == "__main__":
    unittest.main()

<?php

// Protected, server-only pre-release backup. Never print credentials or contents.
use Illuminate\Contracts\Console\Kernel;

[$script, $root, $directory, $previous, $target] = $argv;
if (! is_dir($directory) || is_link($directory) || (fileperms($directory) & 0777) !== 0700) {
    throw new RuntimeException('Expected a protected backup directory.');
}
chdir($root);
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('production') || config('app.debug') || config('database.default') !== 'mysql') {
    throw new RuntimeException('Expected the production MySQL application.');
}
$connection = config('database.connections.mysql');
$quote = static fn ($value) => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $value).'"';
$client = $directory.'/client.cnf';
$dump = $directory.'/database.sql.tmp';
$key = $directory.'/backup.key';
$encrypted = $directory.'/database.sql.enc';
$run = static function (array $command, array $descriptors): void {
    $process = proc_open($command, $descriptors, $pipes);
    if (! is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('Backup command failed; see protected backup diagnostics.');
    }
};
try {
    $ini = "[client]\nuser=".$quote($connection['username'])."\npassword=".$quote($connection['password'])
        ."\nhost=".$quote($connection['host'])."\nport=".$quote($connection['port'])."\n";
    if (! empty($connection['unix_socket'])) {
        $ini .= 'socket='.$quote($connection['unix_socket'])."\n";
    }
    file_put_contents($client, $ini);
    chmod($client, 0600);
    file_put_contents($key, bin2hex(random_bytes(32)));
    chmod($key, 0600);
    copy('.env', $directory.'/environment.before');
    chmod($directory.'/environment.before', 0600);
    $run(['/usr/bin/mysqldump', '--defaults-extra-file='.$client, '--single-transaction', '--quick', '--skip-lock-tables', '--no-tablespaces', $connection['database']],
        [0 => ['file', '/dev/null', 'r'], 1 => ['file', $dump, 'w'], 2 => ['file', $directory.'/diagnostics.log', 'a']]);
    chmod($dump, 0600);
    if (filesize($dump) === 0) {
        throw new RuntimeException('Empty database backup refused.');
    }
    $hash = hash_file('sha256', $dump);
    $run(['/usr/bin/openssl', 'enc', '-aes-256-cbc', '-pbkdf2', '-salt', '-in', $dump, '-out', $encrypted, '-pass', 'file:'.$key],
        [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', $directory.'/diagnostics.log', 'a']]);
    chmod($encrypted, 0600);
    $process = proc_open(['/usr/bin/openssl', 'enc', '-d', '-aes-256-cbc', '-pbkdf2', '-in', $encrypted, '-pass', 'file:'.$key],
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $directory.'/diagnostics.log', 'a']], $pipes);
    if (! is_resource($process)) {
        throw new RuntimeException('Backup decryption verification could not start.');
    }
    $context = hash_init('sha256');
    hash_update_stream($context, $pipes[1]);
    fclose($pipes[1]);
    if (proc_close($process) !== 0 || ! hash_equals($hash, hash_final($context))) {
        throw new RuntimeException('Encrypted backup round-trip verification failed.');
    }
    file_put_contents($directory.'/metadata.json', json_encode(['created_at' => gmdate('c'), 'previous' => $previous, 'target' => $target,
        'app_key_hash' => hash('sha256', config('app.key')), 'dump_sha256' => $hash,
        'encryption_round_trip_verified' => true], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    chmod($directory.'/metadata.json', 0600);
    echo "Protected encrypted backup and environment snapshot verified.\n";
} finally {
    foreach ([$client, $dump] as $temporary) {
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
}

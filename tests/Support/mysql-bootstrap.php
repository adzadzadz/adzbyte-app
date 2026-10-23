<?php

// Only the explicit MySQL test configuration uses this bootstrap. Never take
// connection details from the application's development/production .env.
$database = getenv('TEST_MYSQL_DATABASE');
$host = getenv('TEST_MYSQL_HOST') ?: '127.0.0.1';
if (! is_string($database) || ! preg_match('/^adzbyte_test_[a-zA-Z0-9_]+$/D', $database)
    || ! in_array($host, ['127.0.0.1', 'localhost'], true)) {
    throw new RuntimeException('MySQL verification requires an explicit adzbyte_test_ database on a loopback test server.');
}

$configCache = sys_get_temp_dir().'/adzbyte-mysql-test-config-disabled-'.getmypid().'.php';
if (is_file($configCache)) {
    throw new RuntimeException('MySQL verification cannot use an existing configuration cache.');
}
$settings = [
    'APP_ENV' => 'testing',
    'APP_CONFIG_CACHE' => $configCache,
    'DB_CONNECTION' => 'mysql',
    'DB_URL' => '',
    'DB_HOST' => $host,
    'DB_PORT' => getenv('TEST_MYSQL_PORT') ?: '3306',
    'DB_DATABASE' => $database,
    'DB_USERNAME' => getenv('TEST_MYSQL_USERNAME') ?: 'root',
    'DB_PASSWORD' => getenv('TEST_MYSQL_PASSWORD') ?: '',
    'DB_SOCKET' => getenv('TEST_MYSQL_SOCKET') ?: '',
];
foreach ($settings as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
require_once dirname(__DIR__, 2).'/vendor/autoload.php';

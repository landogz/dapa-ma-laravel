<?php

declare(strict_types=1);

require __DIR__.'/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    installer_json(['status' => false, 'message' => 'POST required.'], 405);
}

$raw = file_get_contents('php://input') ?: '';
$input = json_decode($raw, true);
if (! is_array($input)) {
    $input = $_POST;
}

$action = (string) ($input['action'] ?? '');

try {
    switch ($action) {
        case 'status':
            installer_json([
                'status' => true,
                'installed' => installer_is_locked(),
                'checks' => installer_php_checks(),
            ]);

        case 'requirements':
            installer_require_unlocked();
            $checks = installer_php_checks();
            installer_json([
                'status' => $checks['ready'],
                'message' => $checks['ready']
                    ? 'Server meets DAPE-MA requirements.'
                    : 'Fix the failed checks before continuing.',
                'data' => $checks,
            ], $checks['ready'] ? 200 : 422);

        case 'test_database':
            installer_require_unlocked();
            $host = (string) ($input['db_host'] ?? '127.0.0.1');
            $port = (int) ($input['db_port'] ?? 3306);
            $database = (string) ($input['db_database'] ?? '');
            $username = (string) ($input['db_username'] ?? '');
            $password = (string) ($input['db_password'] ?? '');
            $createDb = ! empty($input['create_database']);
            $rootUser = (string) ($input['db_root_username'] ?? 'root');
            $rootPass = (string) ($input['db_root_password'] ?? '');

            if ($database === '' || $username === '') {
                installer_json(['status' => false, 'message' => 'Database name and username are required.'], 422);
            }

            if ($createDb) {
                $rootDsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);
                $root = new PDO($rootDsn, $rootUser, $rootPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                $root->exec('CREATE DATABASE IF NOT EXISTS `'.str_replace('`', '``', $database).'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $root->exec(
                    "CREATE USER IF NOT EXISTS '".str_replace("'", "''", $username)."'@'localhost' IDENTIFIED BY '".str_replace("'", "''", $password)."'"
                );
                $root->exec(
                    "ALTER USER '".str_replace("'", "''", $username)."'@'localhost' IDENTIFIED BY '".str_replace("'", "''", $password)."'"
                );
                $root->exec(
                    'GRANT ALL PRIVILEGES ON `'.str_replace('`', '``', $database)."`.* TO '".str_replace("'", "''", $username)."'@'localhost'"
                );
                $root->exec('FLUSH PRIVILEGES');
            }

            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->query('SELECT 1');

            installer_json([
                'status' => true,
                'message' => $createDb
                    ? 'Database created and connection successful.'
                    : 'Database connection successful.',
            ]);

        case 'install':
            installer_require_unlocked();
            $checks = installer_php_checks();
            if (! $checks['ready']) {
                installer_json([
                    'status' => false,
                    'message' => 'Requirements not met.',
                    'data' => $checks,
                ], 422);
            }

            $appName = trim((string) ($input['app_name'] ?? 'DAPE-MA'));
            $appUrl = rtrim(trim((string) ($input['app_url'] ?? '')), '/');
            $host = (string) ($input['db_host'] ?? '127.0.0.1');
            $port = (string) ($input['db_port'] ?? '3306');
            $database = (string) ($input['db_database'] ?? '');
            $username = (string) ($input['db_username'] ?? '');
            $password = (string) ($input['db_password'] ?? '');
            $seedDemo = ! empty($input['seed_demo']);
            $createDb = ! empty($input['create_database']);
            $rootUser = (string) ($input['db_root_username'] ?? 'root');
            $rootPass = (string) ($input['db_root_password'] ?? '');

            if ($appUrl === '' || $database === '' || $username === '') {
                installer_json(['status' => false, 'message' => 'App URL, database name, and username are required.'], 422);
            }

            // Create DB if requested (same as test step)
            if ($createDb) {
                $rootDsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, (int) $port);
                $root = new PDO($rootDsn, $rootUser, $rootPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                $root->exec('CREATE DATABASE IF NOT EXISTS `'.str_replace('`', '``', $database).'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $root->exec(
                    "CREATE USER IF NOT EXISTS '".str_replace("'", "''", $username)."'@'localhost' IDENTIFIED BY '".str_replace("'", "''", $password)."'"
                );
                $root->exec(
                    "ALTER USER '".str_replace("'", "''", $username)."'@'localhost' IDENTIFIED BY '".str_replace("'", "''", $password)."'"
                );
                $root->exec(
                    'GRANT ALL PRIVILEGES ON `'.str_replace('`', '``', $database)."`.* TO '".str_replace("'", "''", $username)."'@'localhost'"
                );
                $root->exec('FLUSH PRIVILEGES');
            }

            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, (int) $port, $database);
            new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            installer_write_env([
                'APP_NAME' => installer_quote_env($appName),
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'APP_URL' => $appUrl,
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $host,
                'DB_PORT' => $port,
                'DB_DATABASE' => installer_quote_env($database),
                'DB_USERNAME' => installer_quote_env($username),
                'DB_PASSWORD' => installer_quote_env($password),
                // File drivers until migrate creates cache/sessions tables.
                'SESSION_DRIVER' => 'file',
                'CACHE_STORE' => 'file',
                'QUEUE_CONNECTION' => 'database',
                'LOG_LEVEL' => 'warning',
            ]);

            $out = '';
            $steps = [];

            $code = installer_run('php artisan key:generate --force', $out);
            $steps[] = ['step' => 'key:generate', 'code' => $code, 'output' => $out];
            if ($code !== 0) {
                installer_json(['status' => false, 'message' => 'APP_KEY generation failed.', 'steps' => $steps], 500);
            }

            $code = installer_run('php artisan migrate --force', $out);
            $steps[] = ['step' => 'migrate', 'code' => $code, 'output' => $out];
            if ($code !== 0) {
                installer_json(['status' => false, 'message' => 'Migration failed. Check DB credentials and logs.', 'steps' => $steps], 500);
            }

            // Switch to database drivers after tables exist.
            installer_write_env([
                'SESSION_DRIVER' => 'database',
                'CACHE_STORE' => 'database',
            ]);

            if ($seedDemo) {
                $code = installer_run('php artisan db:seed --force', $out);
                $steps[] = ['step' => 'db:seed', 'code' => $code, 'output' => $out];
                if ($code !== 0) {
                    installer_json(['status' => false, 'message' => 'Seeding failed.', 'steps' => $steps], 500);
                }
            }

            $code = installer_run('php artisan storage:link', $out);
            $steps[] = ['step' => 'storage:link', 'code' => $code, 'output' => $out];

            installer_run('CACHE_STORE=file SESSION_DRIVER=file php artisan optimize:clear', $out);
            installer_run('php artisan config:cache', $out);
            installer_run('php artisan route:cache', $out);
            installer_run('php artisan view:cache', $out);

            $lockDir = dirname(installer_lock_path());
            if (! is_dir($lockDir)) {
                mkdir($lockDir, 0775, true);
            }
            file_put_contents(
                installer_lock_path(),
                json_encode([
                    'installed_at' => gmdate('c'),
                    'app_url' => $appUrl,
                ], JSON_PRETTY_PRINT)
            );

            installer_json([
                'status' => true,
                'message' => 'Installation complete.',
                'data' => [
                    'admin_url' => $appUrl.'/admin/login',
                    'api_url' => $appUrl.'/api/v1',
                    'seeded' => $seedDemo,
                    'demo_note' => $seedDemo
                        ? 'Demo accounts use password “password” (e.g. superadmin@dape-ma.local). Change them immediately.'
                        : 'Create your first admin at /admin/register if no super admin exists yet.',
                    'steps' => $steps,
                ],
            ]);

        default:
            installer_json(['status' => false, 'message' => 'Unknown action.'], 400);
    }
} catch (Throwable $e) {
    installer_json([
        'status' => false,
        'message' => $e->getMessage(),
    ], 500);
}

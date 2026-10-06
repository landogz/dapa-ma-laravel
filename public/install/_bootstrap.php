<?php

declare(strict_types=1);

/**
 * Shared helpers for the DAPE-MA web installer.
 * Base path = Laravel project root (parent of public/).
 */

function installer_base_path(): string
{
    return dirname(__DIR__, 2);
}

function installer_lock_path(): string
{
    return installer_base_path().'/storage/app/installed';
}

function installer_is_locked(): bool
{
    return is_file(installer_lock_path());
}

function installer_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function installer_require_unlocked(): void
{
    if (installer_is_locked()) {
        installer_json([
            'status' => false,
            'message' => 'DAPE-MA is already installed. Remove storage/app/installed only if you intentionally need to re-run the installer.',
        ], 403);
    }
}

function installer_php_checks(): array
{
    $requiredExt = [
        'bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'json', 'mbstring',
        'openssl', 'pdo', 'pdo_mysql', 'tokenizer', 'xml', 'zip', 'gd',
    ];

    $ext = [];
    foreach ($requiredExt as $name) {
        $ext[$name] = extension_loaded($name);
    }

    $base = installer_base_path();
    $paths = [
        'storage' => is_writable($base.'/storage'),
        'bootstrap/cache' => is_writable($base.'/bootstrap/cache'),
        'public' => is_writable($base.'/public'),
        '.env writable or creatable' => is_writable($base) || (is_file($base.'/.env') && is_writable($base.'/.env')),
    ];

    $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
    $vendorOk = is_file($base.'/vendor/autoload.php');
    $artisanOk = is_file($base.'/artisan');

    $allExt = ! in_array(false, $ext, true);
    $allPaths = ! in_array(false, $paths, true);

    return [
        'php_version' => PHP_VERSION,
        'php_ok' => $phpOk,
        'vendor_ok' => $vendorOk,
        'artisan_ok' => $artisanOk,
        'extensions' => $ext,
        'paths' => $paths,
        'ready' => $phpOk && $vendorOk && $artisanOk && $allExt && $allPaths,
    ];
}

function installer_run(string $command, ?string &$output = null): int
{
    $base = installer_base_path();
    $full = 'cd '.escapeshellarg($base).' && '.$command.' 2>&1';
    $lines = [];
    $code = 0;
    exec($full, $lines, $code);
    $output = implode("\n", $lines);

    return $code;
}

function installer_write_env(array $data): void
{
    $base = installer_base_path();
    $envPath = $base.'/.env';
    $example = $base.'/.env.example';
    $deployExample = $base.'/deploy/env.production.example';

    if (! is_file($envPath)) {
        if (is_file($example)) {
            copy($example, $envPath);
        } elseif (is_file($deployExample)) {
            copy($deployExample, $envPath);
        } else {
            file_put_contents($envPath, "");
        }
    }

    $content = file_get_contents($envPath);
    if ($content === false) {
        throw new RuntimeException('Unable to read .env');
    }

    $set = static function (string $content, string $key, string $value): string {
        $line = $key.'='.$value;
        if (preg_match('/^'.preg_quote($key, '/').'=.*/m', $content)) {
            return preg_replace('/^'.preg_quote($key, '/').'=.*/m', $line, $content, 1) ?? $content;
        }

        return rtrim($content)."\n".$line."\n";
    };

    foreach ($data as $key => $value) {
        $content = $set($content, $key, (string) $value);
    }

    if (file_put_contents($envPath, $content) === false) {
        throw new RuntimeException('Unable to write .env — check permissions.');
    }
}

function installer_quote_env(string $value): string
{
    if ($value === '' || preg_match('/[\s#"\']/', $value)) {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    return $value;
}

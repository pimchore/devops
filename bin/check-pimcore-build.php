#!/usr/bin/env php
<?php
/**
 * This file checks if pimcore is correctly built for production mode.
 * Execute it with `php -d zend.assertions=1 check-build.php`.
 * Or from the web: `wget -qO- https://gist.githubusercontent.com/yariksheptykin/4eb979490fa51df77309e02f32173d69/raw/pimcore-check-build.php | php -d zend.assertions=1 --`.
 */
declare(strict_types=1);

// Enable assertions.
ini_set('zend.assertions', 1);
ini_set('assert.exception', 1);

// Check if the build is in production mode.
assert('prod' === getenv('APP_ENV'), 'APP_ENV must be set to prod');

// Check if the environment variables are set correctly.
assert(file_exists('.env.prod'), 'Missing .env.prod file');
$vars = parse_envfile(file_get_contents('.env.prod'));
foreach ([
    'PIMCORE_CLASS_DEFINITION_WRITABLE' => '0',
    'APP_DEBUG' => '0'
] as $name => $value) {
    assert(isset($vars[$name]) && $vars[$name] === $value, "Missing or invalid {$name} variable");
}

// Check if pimcore system directories are present, and writable.
foreach ([
    'public/bundles',
    'public/var',
    'var/config',
    'var/classes',
    'var/log',
    'var/recyclebin',
    'var/versions',
] as $dir) {
    assert(file_exists($dir) && is_dir($dir), "Missing {$dir} directory");
    assert(is_writable($dir), "Directory {$dir} is not writable");
}

// Check if var/classes/DataObject contains generated *.php files.
$files = array_filter(scandir('var/classes/DataObject'), static fn (string $file): bool => str_ends_with($file, '.php'));
assert(count($files) > 0, 'Missing generated DataObject classes');

// Check if the public/bundles/ exists and contains at least one files.
$files = array_filter(scandir('public/bundles'), static fn (string $file): bool => !in_array($file, ['.', '..'], true));
assert(count($files) > 0, 'Missing public/bundles files');

// Assert that composer autoloader is dumped and optimized.
assert(file_exists('vendor/autoload.php'), 'Consider executing `composer dump-autoload --optimize --classmap-authoritative`');

// Assert that vendor/ directory does not contain dev dependencies.
$composerJson = json_decode(file_get_contents('composer.json'), true, 512, \JSON_THROW_ON_ERROR);
$devDependencies = array_keys($composerJson['require-dev'] ?? []);
foreach ($devDependencies as $devDependency) {
    assert(!file_exists("vendor/{$devDependency}"), "Dev dependency {$devDependency} should not be installed in prod");
}

// Assert that symfony cache for prod is warmed up.
assert(file_exists('var/cache/prod'), 'Missing var/cache/prod directory');

function parse_envfile(bool|string $env): array
{
    $lines = explode(\PHP_EOL, $env);
    $vars = array_filter($lines, static fn (string $line): bool => !(empty($line) || str_starts_with($line, '#')));
    $vars = array_map(static fn (string $line): array => explode('=', $line, 2), $vars);

    return array_combine(array_column($vars, 0), array_column($vars, 1));
}

exit(0);

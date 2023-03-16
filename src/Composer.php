<?php

namespace Pimchore\DevOps;

use Composer\Script\Event;

class Composer
{
    public static function build(Event $event)
    {
        $prod = 'prod' === getenv('APP_ENV');

        // copy .env.dist to .env unless it already exists
        if (!file_exists('.env')) {
            copy('.env.dist', '.env');
        }

        // copy parameters example to parameters unless it already exists.
        if (!file_exists('config/parameters.yaml')) {
            copy('config/parameters.example.yaml', 'config/parameters.yaml');
        }

        // Create pimcore system directories unless they already exist.
        foreach ([
                     'public/bundles',
                     'public/var',
                     'var/config',
                     'var/classes',
                     'var/log',
                     'var/recyclebin',
                     'var/versions',
                 ] as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0775, true);
            }
        }

        // Delete directory var/classes/DataObject recursively.
        if (file_exists('var/classes/DataObject')) {
            self::rrmdir('var/classes/DataObject');
        }

        shell_exec('php bin/console pimcore:build:classes');
        shell_exec('php bin/console assets:install' . ($prod ? '' : ' --symlink --relative'));
        shell_exec('php bin/console cache:clear');
    }

    public static function setup(Event $event)
    {
        shell_exec(
            'php bin/console pimcore:deployment:classes-rebuild --delete-classes --create-classes --no-interaction'
        );
        self::installBundles();
        shell_exec('php bin/console doctrine:migrations:sync-metadata-storage');
        shell_exec('php bin/console doctrine:migrations:migrate --no-interaction');
        shell_exec('php bin/console pimcore:cache:clear --no-interaction');
        shell_exec('php bin/console pimcore:cache:warming --no-interaction');
    }

    private static function installBundles(): void
    {
        foreach (explode(',', getenv('AUTOINSTALL_BUNDLES') ?? '') as $bundle) {
            shell_exec(
                "php bin/console pimcore:bundle:install --fail-without-error --no-interaction {$bundle} || true"
            );
        }
    }

    public static function installPimcore()
    {
        shell_exec('PIMCORE_CONFIGURATION_DIRECTORY=config/pimcore php pimcore-install --ignore-existing-config --skip-database-config --no-interaction');
    }

    private static function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ('.' !== $object && '..' !== $object) {
                    if (is_dir($dir . \DIRECTORY_SEPARATOR . $object) && !is_link($dir . '/' . $object)) {
                        self::rrmdir($dir . \DIRECTORY_SEPARATOR . $object);
                    } else {
                        unlink($dir . \DIRECTORY_SEPARATOR . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}

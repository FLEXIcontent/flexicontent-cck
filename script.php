<?php
/**
 * com_flexicontent installer script (Joomla 6 compatible)
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer as JoomlaInstaller;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseDriver;

class com_flexicontentInstallerScript implements InstallerScriptInterface
{
    /**
     * Vérification avant installation/mise à jour
     */
    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        $version = new Version();
        if (version_compare($version->getShortVersion(), '5.0', '<')) {
            Factory::getApplication()->enqueueMessage(
                'FLEXIcontent requires Joomla 5.0 or later.',
                'error'
            );
            return false;
        }

        @set_time_limit(600);
        @ini_set('memory_limit', '256M');

        return true;
    }

    /**
     * Installation
     */
    public function install(InstallerAdapter $adapter): bool
    {
        $app = Factory::getApplication();

        $this->executePackageSQL();
        $this->createFallbackTables();
        $this->installPlugins($adapter);
        $this->installModules($adapter);

        $app->enqueueMessage('FLEXIcontent installation completed.');
        return true;
    }

    /**
     * Mise à jour
     */
    public function update(InstallerAdapter $adapter): bool
    {
        $app = Factory::getApplication();

        $this->executePackageSQL();
        $this->migrateOldTables();

        $app->enqueueMessage('FLEXIcontent update completed.');
        return true;
    }

    /**
     * Désinstallation
     */
    public function uninstall(InstallerAdapter $adapter): bool
    {
        Factory::getApplication()->enqueueMessage('FLEXIcontent uninstalled.');
        return true;
    }

    /**
     * Postflight
     */
    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        Factory::getApplication()->enqueueMessage("FLEXIcontent postflight action: {$type}");
        return true;
    }

    /**
     * Execute SQL d’installation
     */
    private function executePackageSQL(): void
    {
        $basePath = __DIR__;
        $possibleSqlFiles = [
            $basePath . '/sql/install.sql',
            $basePath . '/sql/install.mysql.utf8.sql',
            $basePath . '/sql/installer.sql'
        ];

        foreach ($possibleSqlFiles as $sqlFile) {
            if (file_exists($sqlFile) && is_readable($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                if (trim($sql) === '') {
                    continue;
                }
                $this->runSqlString($sql);
                Factory::getApplication()->enqueueMessage("Executed SQL file: " . basename($sqlFile));
            }
        }
    }

    /**
     * Exécution de requêtes SQL multiples
     */
    private function runSqlString(string $sqlString): void
    {
        /** @var DatabaseDriver $db */
        $db = Factory::getContainer()->get('DatabaseDriver');

        $sqlString = str_replace(["\r\n", "\r"], "\n", $sqlString);
        $sqlString = preg_replace('!/\*.*?\*/!s', '', $sqlString);

        $queries = $this->splitSqlStatements($sqlString);

        foreach ($queries as $q) {
            $q = trim($q);
            if ($q === '') {
                continue;
            }
            try {
                $db->setQuery($q)->execute();
            } catch (\Exception $e) {
                Factory::getApplication()->enqueueMessage('SQL Error: ' . $e->getMessage(), 'warning');
            }
        }
    }

    private function splitSqlStatements(string $sql): array
    {
        $stmts = [];
        $buffer = '';
        $inString = false;
        $stringChar = '';
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $buffer .= $ch;
            if ($inString) {
                if ($ch === $stringChar) {
                    $prev = ($i > 0) ? $sql[$i - 1] : '';
                    if ($prev !== '\\') {
                        $inString = false;
                        $stringChar = '';
                    }
                }
                continue;
            } else {
                if ($ch === '"' || $ch === "'") {
                    $inString = true;
                    $stringChar = $ch;
                    continue;
                }
                if ($ch === ';') {
                    $stmts[] = $buffer;
                    $buffer = '';
                }
            }
        }
        if (trim($buffer) !== '') {
            $stmts[] = $buffer;
        }
        return $stmts;
    }

    /**
     * Création de tables par défaut si pas de SQL fourni
     */
    private function createFallbackTables(): void
    {
        /** @var DatabaseDriver $db */
        $db = Factory::getContainer()->get('DatabaseDriver');

        $queries = [
            "
            CREATE TABLE IF NOT EXISTS `#__flexicontent_file_usage` (
                `file_id` int(11) unsigned NOT NULL DEFAULT '0',
                `item_id` int(11) unsigned NOT NULL DEFAULT '0',
                `field_id` int(11) unsigned NOT NULL DEFAULT '0',
                `jfield_id` int(11) unsigned NOT NULL DEFAULT '0',
                `value` text NOT NULL,
                `value_usage` tinyint(1) NOT NULL DEFAULT '1',
                PRIMARY KEY (`file_id`,`item_id`,`field_id`,`jfield_id`),
                KEY `idx_file_id` (`file_id`),
                KEY `idx_item_id` (`item_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ",
            "
            CREATE TABLE IF NOT EXISTS `#__flexicontent_items` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(255) NOT NULL,
                `alias` VARCHAR(255) DEFAULT NULL,
                `catid` INT(11) DEFAULT 0,
                `state` TINYINT(1) DEFAULT 1,
                `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `created_by` INT(11) DEFAULT NULL,
                `modified` DATETIME NULL,
                `params` TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            "
        ];

        foreach ($queries as $q) {
            try {
                $db->setQuery($q)->execute();
            } catch (\Exception $e) {
                Factory::getApplication()->enqueueMessage('Table creation error: ' . $e->getMessage(), 'warning');
            }
        }
    }

    /**
     * Migration simple
     */
    private function migrateOldTables(): void
    {
        /** @var DatabaseDriver $db */
        $db = Factory::getContainer()->get('DatabaseDriver');

        try {
            $cols = $db->getTableColumns('#__flexicontent_items');
            if (!isset($cols['description'])) {
                $db->setQuery("ALTER TABLE `#__flexicontent_items` ADD `description` TEXT NULL")->execute();
            }
        } catch (\Exception $e) {
            // Ignore
        }
    }

    /**
     * Installation des plugins du package
     */
    private function installPlugins(InstallerAdapter $adapter): void
    {
        $base = __DIR__ . '/plugins';

        if (!is_dir($base)) {
            return;
        }

        $folders = scandir($base);
        foreach ($folders as $folder) {
            if ($folder === '.' || $folder === '..') continue;
            $pathFolder = $base . '/' . $folder;
            if (!is_dir($pathFolder)) continue;

            $items = scandir($pathFolder);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $pluginPath = $pathFolder . '/' . $item;
                if (is_dir($pluginPath)) {
                    try {
                        $installer = new JoomlaInstaller();
                        $installer->setDatabase(Factory::getContainer()->get('DatabaseDriver'));
                        $installer->setApplication(Factory::getApplication());

                        $installer->install($pluginPath);

                        Factory::getApplication()->enqueueMessage("Installed plugin: {$item} ({$folder})");
                    } catch (\Exception $e) {
                        Factory::getApplication()->enqueueMessage("Plugin install failed: {$item} - " . $e->getMessage(), 'warning');
                    }
                }
            }
        }
    }

    /**
     * Installation des modules du package
     */
    private function installModules(InstallerAdapter $adapter): void
    {
        $base = __DIR__ . '/modules';

        if (!is_dir($base)) {
            return;
        }

        $mods = scandir($base);
        foreach ($mods as $mod) {
            if ($mod === '.' || $mod === '..') continue;
            $modPath = $base . '/' . $mod;
            if (is_dir($modPath)) {
                try {
                    $installer = new JoomlaInstaller();
                    $installer->setDatabase(Factory::getContainer()->get('DatabaseDriver'));
                    $installer->setApplication(Factory::getApplication());

                    $installer->install($modPath);

                    Factory::getApplication()->enqueueMessage("Installed module: {$mod}");
                } catch (\Exception $e) {
                    Factory::getApplication()->enqueueMessage("Module install failed: {$mod} - " . $e->getMessage(), 'warning');
                }
            }
        }
    }
}

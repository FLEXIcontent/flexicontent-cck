<?php
/** Exercise template deletion with an in-memory database and filesystem adapter. */
namespace Joomla\CMS\MVC\Model {
    class BaseDatabaseModel { protected $_db; public function __construct($config = []) { $this->_db = $config['db']; } }
}
namespace Joomla\Filesystem {
    class Folder { public static $succeeds = true; public static $calls = []; public static function delete($path) { self::$calls[] = $path; return self::$succeeds; } }
}
namespace {
    define('_JEXEC', 1); define('JPATH_SITE', '/fixture'); define('DS', '/');
    require __DIR__ . '/../admin/models/templates.php';
    class TemplateDatabase {
        public $rows = ['#__flexicontent_templates' => ['custom', 'other'], '#__flexicontent_layouts_conf' => ['custom', 'custom', 'other']];
        private $query;
        public function Quote($value) { return "'" . str_replace("'", "''", $value) . "'"; }
        public function setQuery($query) { $this->query = $query; }
        public function execute() {
            if (!preg_match("/^DELETE FROM (#__flexicontent_\w+) WHERE template = 'custom'$/", $this->query, $match)) throw new \RuntimeException('Unexpected deletion scope');
            $this->rows[$match[1]] = array_values(array_diff($this->rows[$match[1]], ['custom']));
        }
    }
    $db = new TemplateDatabase(); $model = new \FlexicontentModelTemplates(['db' => $db]); $before = $db->rows;
    foreach (['', 'grid', 'table', 'faq', 'items-tabbed'] as $name) if ($model->delete($name)) throw new \RuntimeException('Built-in template deleted');
    if (\Joomla\Filesystem\Folder::$calls || $db->rows !== $before) throw new \RuntimeException('Protected templates mutated');
    \Joomla\Filesystem\Folder::$succeeds = false;
    if ($model->delete('custom') || $db->rows !== $before) throw new \RuntimeException('Failed filesystem deletion lost settings');
    \Joomla\Filesystem\Folder::$succeeds = true;
    if (!$model->delete('custom')) throw new \RuntimeException('Custom template deletion failed');
    foreach ($db->rows as $rows) if ($rows !== ['other']) throw new \RuntimeException('Saved template settings survived deletion or unrelated settings were removed');
    echo "Template deletion: protected folders, filesystem failure, both settings tables and unrelated rows passed\n";
}

<?php
/** Loading shortcuts must be restricted to read-only HTML site views. */
namespace Joomla\CMS {
    final class Factory {
        public static $app;
        public static function getApplication() {
            if (!self::$app) throw new \RuntimeException('Application has not been created');
            return self::$app;
        }
    }
}
namespace {
    error_reporting(E_ALL);
    set_error_handler(static function ($severity, $message, $file, $line) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });
    final class PageInput {
        public $values = array();
        public function get($name, $default = null, $filter = null) { return $this->values[$name] ?? $default; }
        public function getCmd($name, $default = '') { return $this->get($name, $default); }
    }
    final class PageApp {
        public $client = 'site';
        public $input;
        public function __construct() { $this->input = new PageInput; }
        public function isClient($client) { return $this->client === $client; }
        public function getInput() { return $this->input; }
    }
    $source = file_get_contents(__DIR__ . '/../site/classes/flexicontent.fields.php');
    $start = strpos($source, 'static function _isPageView(');
    $method = ''; $depth = 0; $opened = false;
    foreach (token_get_all('<?php ' . substr($source, $start)) as $token) {
        if (is_array($token) && $token[0] === T_OPEN_TAG) continue;
        $method .= is_array($token) ? $token[1] : $token;
        if ($token === '{') { ++$depth; $opened = true; }
        if ($token === '}' && --$depth === 0 && $opened) break;
    }
    eval('class FlexicontentFields { ' . $method . '}');
    $cases = array(
        array('HTML item', 'site', 'GET', array('view'=>'item'), true),
        array('HTML category', 'site', 'GET', array('view'=>'category'), true),
        array('HTML module', 'site', 'GET', array('option'=>'com_content'), true),
        array('raw', 'site', 'GET', array('format'=>'raw'), false),
        array('json', 'site', 'GET', array('format'=>'json'), false),
        array('feed', 'site', 'GET', array('format'=>'feed'), false),
        array('PDF', 'site', 'GET', array('format'=>'pdf'), false),
        array('com_ajax', 'site', 'GET', array('option'=>'com_ajax'), false),
        array('vote', 'site', 'GET', array('task'=>'ajaxvote'), false),
        array('edit layout', 'site', 'GET', array('view'=>'item','layout'=>'edit'), false),
        array('form layout', 'site', 'GET', array('view'=>'item','layout'=>'form'), false),
        array('administrator item', 'administrator', 'GET', array('view'=>'item'), false),
        array('POST', 'site', 'POST', array(), false),
        array('CLI', 'cli', '', array(), false),
        array('indexer', 'administrator', 'GET', array('option'=>'com_finder'), false),
    );
    $failed = array();
    foreach ($cases as [$name,$client,$method,$values,$expected]) {
        $app = new PageApp; $app->client=$client; $app->input->values=$values;
        \Joomla\CMS\Factory::$app=$app; $_SERVER['REQUEST_METHOD']=$method;
        if (FlexicontentFields::_isPageView() !== $expected) $failed[]=$name;
    }
    $app = new PageApp; \Joomla\CMS\Factory::$app=$app; $_SERVER['REQUEST_METHOD']='GET';
    FlexicontentFields::_isPageView(); $app->input->values['task']='save';
    if (FlexicontentFields::_isPageView()) $failed[]='task added by routing';
    \Joomla\CMS\Factory::$app=null; unset($_SERVER['REQUEST_METHOD']);
    if (FlexicontentFields::_isPageView()) $failed[]='CLI before application boot';
    if ($failed) { fwrite(STDERR, 'FAILED: '.implode(', ', $failed)."\n"); exit(1); }
    echo "17 plain-page request checks passed\n";
}

<?php
/** Standalone request-boundary regression; no Joomla bootstrap, DB or uploads. */
namespace Joomla\CMS {
    class Factory { public static $application; public static function getApplication() { return self::$application; } }
}
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    define('_JEXEC', 1);
    define('DS', DIRECTORY_SEPARATOR);
    $scratch = sys_get_temp_dir() . '/fc-filemanager-guard-' . bin2hex(random_bytes(6));
    define('JPATH_ADMINISTRATOR', $scratch);
    $folders = array($scratch, $scratch.'/components', $scratch.'/components/com_flexicontent', $scratch.'/components/com_flexicontent/controllers');
    foreach ($folders as $folder) mkdir($folder,0700);
    $backend = end($folders).'/filemanager.php';
    file_put_contents($backend, '<?php $GLOBALS["filemanagerBackendLoaded"] = true;');
    $target = dirname(__DIR__).'/site/controllers/filemanager.php';
    $input = new class {
        public $task='', $controller='', $view='';
        public function get($key,$default='',$filter='cmd') { return isset($this->$key) ? $this->$key : $default; }
    };
    $app = new class($input) {
        public $input, $identity, $client='site';
        public function __construct($input) { $this->input=$input; }
        public function isClient($client) { return $client===$this->client; }
        public function getIdentity() { return $this->identity; }
    };
    \Joomla\CMS\Factory::$application=$app;
    $user = static function ($guest,$rights=array(),$categoryRights=array()) {
        return new class($guest,$rights,$categoryRights) {
            public $guest, $rights, $categoryRights;
            public function __construct($guest,$rights,$categoryRights) { $this->guest=$guest;$this->rights=$rights;$this->categoryRights=$categoryRights; }
            public function authorise($action,$asset) { return $action==='flexicontent.uploadfiles' || in_array($action,$this->rights,true); }
            public function getAuthorisedCategories($component,$action) { return $component==='com_content' && in_array($action,$this->categoryRights,true) ? array(23) : array(); }
        };
    };
    $checks=0;
    $run = static function ($name,$identity,$controller,$view,$task,$expected,$client='site') use ($app,$input,$target,&$checks) {
        $app->identity=$identity;$app->client=$client;$input->controller=$controller;$input->view=$view;$input->task=$task;
        $allowed=false;
        try { include $target;$allowed=true; }
        catch (\RuntimeException $error) { if ($error->getCode()!==403) throw $error; }
        if ($allowed!==$expected) throw new \RuntimeException($name.': unexpected admission result');
        ++$checks;
    };
    try {
        foreach (array(true,false) as $guest) {
            foreach (array('upload','uploads','apply','remove','publish','display','') as $task) {
                $run('direct default file permissions',$user($guest),'filemanager','',$task,false);
            }
        }
        $run('controller before task normalization',$user(true),'','','filemanager.upload',false);
        $run('view-derived controller',$user(true),'','filemanager','upload',false);
        $run('case-normalized controller',$user(false),'FILEMANAGER','','UPLOADS',false);
        $run('no identity',null,'filemanager','','upload',false);
        $run('guest with author ACL',$user(true,array('core.create')),'filemanager','','upload',false);
        $run('explicit file manager',$user(false,array('flexicontent.managefiles')),'filemanager','','upload',true);
        foreach (array('core.create','core.edit','core.edit.own') as $action) {
            $run('component author',$user(false,array($action)),'filemanager','','upload',true);
            $run('category author',$user(false,array(),array($action)),'filemanager','','upload',true);
        }
        foreach (array(true,false) as $guest) {
            $run('internal item field call',$user($guest),'items','item','save',true);
        }
        $run('administrator dispatch',$user(true),'filemanager','','upload',true,'administrator');
        foreach (array('addurl','addlocal') as $task) {
            $run('existing unsafe HTTP-task restriction',$user(false,array('flexicontent.managefiles')),'filemanager','',$task,false);
        }
        if (empty($GLOBALS['filemanagerBackendLoaded'])) throw new \RuntimeException('Allowed requests did not load backend');
        echo $checks." frontend file-manager boundary checks passed.\n";
    } finally {
        unlink($backend);
        foreach (array_reverse($folders) as $folder) rmdir($folder);
    }
}

<?php
/** Shared field upload policy and bounded thumbnail ownership, without a database. */
namespace Joomla\Registry {
    class Registry {
        public array $values;
        public function __construct($values = []) { $this->values = is_string($values) ? json_decode($values, true) : $values; }
        public function get($key, $default = null) { return $this->values[$key] ?? $default; }
        public function set($key, $value) { $this->values[$key] = $value; }
        public function merge($other) { $this->values = array_replace($this->values, $other->values); }
    }
}
namespace Joomla\CMS\Component {
    class ComponentHelper {
        public static $params;
        public static function getParams($name) { return self::$params; }
    }
}
namespace {
    error_reporting(E_ALL);
    set_error_handler(static function ($level, $message, $file, $line) { throw new \ErrorException($message, 0, $level, $file, $line); });
    define('_JEXEC', 1);
    $base = sys_get_temp_dir() . '/fc-upload-test-' . bin2hex(random_bytes(8));
    mkdir($base); mkdir($base . '/site'); mkdir($base . '/site/thumbs'); mkdir($base . '/outside');
    define('JPATH_SITE', $base . '/site');
    require __DIR__ . '/../site/classes/helpers/upload.php';
    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
    $global = new \Joomla\Registry\Registry(['upload_extensions'=>'jpg,png,pdf,mp3', 'ignore_extensions'=>'pdf', 'restrict_uploads'=>0, 'upload_maxsize'=>100]);
    \Joomla\CMS\Component\ComponentHelper::$params = $global;
    $field = (object)['field_type'=>'image','parameters'=>new \Joomla\Registry\Registry(['upload_extensions'=>'png,pdf', 'upload_maxsize'=>50])];
    $params = flexicontent_upload::getUploadParams($field);
    check($params->get('upload_extensions') === 'png', 'Image upload policy must reject non-images independent of layout');
    check($params->get('ignore_extensions') === '' && $params->get('restrict_uploads') === 1, 'Image policy must validate file content');
    check($params->get('upload_maxsize') === 50 && $global->get('upload_maxsize') === 100, 'Field limits must not mutate component parameters');
    $field->field_type = 'file';
    check(flexicontent_upload::getUploadParams($field)->get('upload_extensions') === 'png,pdf', 'File fields must retain non-image support');
    $field->field_type = 'mediafile';
    $field->parameters = new \Joomla\Registry\Registry();
    $media = flexicontent_upload::getUploadParams($field);
    check(in_array('wav', explode(',', $media->get('upload_extensions')), true), 'Media fields accept audio');
    check(in_array('webm', explode(',', $media->get('upload_extensions')), true), 'Media fields accept video');
    check($media->get('upload_maxsize') === 100, 'Media fields inherit the component size limit');
    $field->parameters = new \Joomla\Registry\Registry(['upload_extensions'=>'wav,pdf','upload_maxsize'=>40]);
    $media = flexicontent_upload::getUploadParams($field);
    check($media->get('upload_extensions') === 'wav' && $media->get('ignore_extensions') === '', 'Field restrictions also apply to ignored types');
    check($media->get('upload_maxsize') === 40, 'Media fields can override the maximum size');
    check(flexicontent_upload::getAllowedExtensions($media) === ['wav'], 'Local media picker and uploader agree on file types');
    check(flexicontent_upload::getAllowedExtensions($global) === ['jpg', 'png', 'pdf', 'mp3'], 'File picker includes each configured extension once');
    $created = [];
    try {
        foreach (['s_sample.png','l_sample.png','s_fld7_sample.png','s_sample.webp','s_fld7_sample.webp','s_other.png','original.png'] as $name) {
            $created[] = JPATH_SITE . '/thumbs/' . $name; file_put_contents(end($created), 'fixture');
        }
        $created[] = $base . '/outside/s_sample.png'; file_put_contents(end($created), 'unrelated');
        $fields = [(object)['id'=>7,'attribs'=>json_encode(['image_source'=>0,'target_dir'=>0,'dir'=>'thumbs'])]];
        $file = (object)['filename'=>'sample.png','secure'=>0];
        $paths = flexicontent_upload::getImageThumbnails($file, $fields);
        check(count($paths) === 5, 'Find exact shared and field-prefixed variants');
        check(count(flexicontent_upload::getImageThumbnails($file, $fields, false)) === 3, 'Retain WebP variants when a surviving original shares the stem');
        $file->secure = 1;
        check(!flexicontent_upload::getImageThumbnails($file, $fields), 'Do not touch thumbnails from another storage area');
        $file->secure = 0; $file->filename = '../sample.png';
        check(!flexicontent_upload::getImageThumbnails($file, $fields), 'Reject filenames containing paths');
        $file->filename = 'sample.png'; $fields[0]->attribs = json_encode(['image_source'=>0,'target_dir'=>0,'dir'=>'../outside']);
        check(!flexicontent_upload::getImageThumbnails($file, $fields), 'Never traverse outside the site root');
        echo "PASS: image/file policy, independent field limits and exact thumbnail ownership\n";
    } finally {
        foreach ($created as $path) unlink($path);
        rmdir($base . '/outside'); rmdir(JPATH_SITE . '/thumbs'); rmdir(JPATH_SITE); rmdir($base);
    }
}

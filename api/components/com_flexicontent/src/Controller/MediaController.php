<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\Controller;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\MVC\Controller\Exception\Save as SaveException;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Flexicontent\Api\Helper\AccessHelper;
use Joomla\Component\Flexicontent\Api\Helper\WriteHelper;
use Joomla\Component\Flexicontent\Api\Legacy\LegacyBootstrap;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Upload files into FLEXIcontent's own storage.
 *
 * DB-mode files go to FLEXIcontent's uploads/medias directory and are
 * registered in #__flexicontent_files. Folder-mode image fields instead store
 * the original under the field's own item folder, exactly where image.php
 * looks for it. The returned field_value is ready for item writes either way.
 *
 * @since  5.3
 */
class MediaController extends ApiController
{
    /**
     * The content type of the item.
     *
     * @var    string
     */
    protected $contentType = 'media';

    /**
     * The default view for the display method.
     *
     * @var    string
     */
    protected $default_view = 'media';

    /**
     * The URL option for the component.
     *
     * @var    string
     */
    protected $option = 'com_flexicontent';

    /**
     * Store an uploaded file (POST /media).
     *
     * Accepts either a JSON body with filename/content_base64 or a multipart
     * upload with a "file" part. Both forms are convenient: MCP clients use
     * JSON, browser clients can keep their usual multipart uploader.
     *
     * @return  void
     */
    public function add()
    {
        LegacyBootstrap::boot();

        if (!$this->allowAdd()) {
            throw new NotAllowed('JLIB_APPLICATION_ERROR_CREATE_RECORD_NOT_PERMITTED', 403);
        }

        $payload  = $this->readPayload();
        $original = $this->originalName($payload);
        $bytes    = $this->readBytes($payload);
        $fieldId  = (int) ($payload['field_id'] ?? 0);
        $field    = $this->loadField($fieldId);
        $ext      = strtolower((string) \flexicontent_upload::getExt($original));

        $this->checkExtension($ext, $field);
        $this->checkSize(strlen($bytes), $field);
        $this->checkImage($bytes, $ext);

        $fieldType   = (string) ($field->field_type ?? '');
        $fieldParams = $field !== null ? new \Joomla\Registry\Registry($field->attribs ?? '') : null;

        // Image fields have two incompatible storage modes. In DB mode the
        // file lives in FLEXIcontent's uploads/medias folder and is registered
        // in #__flexicontent_files. In folder mode the original lives under
        // <dir>/item_<item_id>_field_<field_id>/original/ and the field value
        // is only a filename. Use the same paths as image.php::getThumbPaths().
        $imageSource = null;

        if ($fieldType === 'image' && $fieldParams !== null) {
            $imageSource = (int) $fieldParams->get('image_source', 0);

            if ($imageSource < 0) {
                throw new SaveException(
                    'This image field uses the Joomla media/intro-full source mode, which the API cannot upload to.',
                    422
                );
            }
        }

        $folderMode = $imageSource !== null && $imageSource >= 1;

        if ($folderMode) {
            $itemId = (int) ($payload['item_id'] ?? 0);

            if ($itemId <= 0 || $fieldId <= 0) {
                throw new SaveException('item_id and field_id are required for folder-mode image fields.', 422);
            }

            $dir = trim(str_replace('\\', '/', (string) $fieldParams->get('dir', 'images/stories/flexicontent')), '/');

            if ($dir === '' || preg_match('#(^|/)\.\.?(/|$)#', $dir)) {
                throw new SaveException('The target field has an invalid image folder.', 422);
            }

            $folderName = 'item_' . $itemId . '_field_' . $fieldId;
            $folder     = $dir . '/' . $folderName . '/original';
            $root       = Path::clean(JPATH_SITE . DS . str_replace('/', DS, $folder));
            $secure     = 0;
        } else {
            // Follow the target field's target_dir (secure uploads by default).
            // An explicit payload value always wins.
            $targetDir = $fieldParams !== null ? (int) $fieldParams->get('target_dir', 1) : 1;
            $secure    = \array_key_exists('secure', $payload)
                ? (int) !empty($payload['secure'])
                : ($field !== null ? $targetDir : 1);
            $root      = $secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;

            $params = ComponentHelper::getParams('com_flexicontent');
            $folder = $secure
                ? $params->get('file_path', 'components/com_flexicontent/uploads')
                : $params->get('media_path', 'components/com_flexicontent/medias');
        }

        if (!is_dir($root) && !mkdir($root, 0755, true) && !is_dir($root)) {
            throw new SaveException('The upload directory could not be created.', 500);
        }

        $filename = \flexicontent_upload::sanitize(Path::clean($root . DS), $original);
        $target   = Path::clean($root . DS . $filename);

        if (!File::write($target, $bytes)) {
            throw new SaveException('The uploaded file could not be stored.', 500);
        }

        $fileId = 0;

        if (!$folderMode) {
            try {
                $fileId = $this->createFileRecord($filename, $original, $ext, $bytes, $secure, $payload);
            } catch (\Throwable $e) {
                // Do not leave an orphaned file behind when the database write fails.
                @unlink($target);
                throw new SaveException('The uploaded file could not be registered: ' . $e->getMessage(), 500);
            }
        }

        $url        = rtrim(Uri::root() ?: '', '/') . '/' . trim(str_replace('\\', '/', (string) $folder), '/') . '/' . rawurlencode($filename);
        $fieldValue = $fieldType === 'image'
            ? [['originalname' => $filename, 'alt' => (string) ($payload['alt'] ?? '')]]
            : $fileId;

        $entry = [
            'type'       => 'media',
            'id'         => (string) $fileId,
            'attributes' => [
                'id'            => $fileId,
                'filename'      => $filename,
                'original_name' => $original,
                'extension'     => $ext,
                'size'          => strlen($bytes),
                'mime_type'     => $this->mimeType($bytes),
                'secure'        => $secure,
                'storage'       => $folderMode ? 'field_folder' : 'flexicontent_files',
                'url'           => $url,
                'path'          => str_replace('\\', '/', $target),
                'field_id'      => $fieldId,
                'field_type'    => $fieldType,
                'field_value'   => $fieldValue,
            ],
        ];

        // JsonResponse already provides the outer "data" member.
        $this->app->setHeader('status', 201, true);
        echo new JsonResponse($entry);
        $this->app->close();
    }

    /**
     * Can the current user upload files?
     *
     * @return  boolean
     */
    protected function allowAdd($data = []): bool
    {
        $user = AccessHelper::getIdentity();

        return $user->authorise('flexicontent.uploadfiles', 'com_flexicontent')
            || $user->authorise('flexicontent.managefiles', 'com_flexicontent')
            || $user->authorise('core.manage', 'com_flexicontent');
    }

    /**
     * Decode the JSON or multipart upload request.
     *
     * @return  array
     */
    private function readPayload(): array
    {
        $raw = trim((string) $this->input->json->getRaw());

        if ($raw !== '') {
            $json = json_decode($raw, true);

            if (!\is_array($json)) {
                throw new SaveException('Invalid JSON payload.', 400);
            }

            return WriteHelper::unwrap($json);
        }

        $file = $this->input->files->get('file', null, 'raw');

        if (!\is_array($file)) {
            throw new SaveException('No file was uploaded.', 422);
        }

        return [
            'filename'    => (string) ($file['name'] ?? ''),
            'tmp_name'    => (string) ($file['tmp_name'] ?? ''),
            'error'       => (int) ($file['error'] ?? 0),
            'secure'      => $this->input->getInt('secure', 1),
            'field_id'    => $this->input->getInt('field_id', 0),
            'item_id'     => $this->input->getInt('item_id', 0),
            'description' => $this->input->getString('description', ''),
            'alt'         => $this->input->getString('alt', ''),
        ];
    }

    /**
     * Validate and return the client-supplied file name.
     *
     * @param   array  $payload  The upload payload.
     *
     * @return  string
     */
    private function originalName(array $payload): string
    {
        $name = basename(str_replace('\\', '/', (string) ($payload['filename'] ?? '')));

        if ($name === '' || $name === '.' || $name === '..' || strpos($name, '.') === false) {
            throw new SaveException('A filename with an extension is required.', 422);
        }

        return $name;
    }

    /**
     * Read the uploaded bytes from a temp file, base64 or raw content.
     *
     * @param   array  $payload  The upload payload.
     *
     * @return  string
     */
    private function readBytes(array $payload): string
    {
        if (!empty($payload['tmp_name'])) {
            if ((int) ($payload['error'] ?? 0) !== UPLOAD_ERR_OK) {
                throw new SaveException('The file upload failed.', 422);
            }

            if (!is_readable((string) $payload['tmp_name'])) {
                throw new SaveException('The uploaded file could not be read.', 422);
            }

            $bytes = file_get_contents((string) $payload['tmp_name']);

            return $bytes === false ? '' : $bytes;
        }

        if (isset($payload['content_base64'])) {
            $bytes = base64_decode((string) $payload['content_base64'], true);

            if ($bytes === false) {
                throw new SaveException('content_base64 is not valid base64.', 422);
            }

            return $bytes;
        }

        if (isset($payload['content'])) {
            return (string) $payload['content'];
        }

        throw new SaveException('No file content was supplied.', 422);
    }

    /**
     * Load an optional target field for upload limits and type-aware values.
     *
     * @param   integer  $id  The FLEXIcontent field id, or 0.
     *
     * @return  object|null
     */
    private function loadField(int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $row = $db->setQuery(
            $db->createQuery()
                ->select('*')
                ->from($db->quoteName('#__flexicontent_fields'))
                ->where($db->quoteName('id') . ' = ' . $id)
        )->loadObject();

        if (!$row) {
            throw new SaveException('The supplied field_id does not exist.', 422);
        }

        return $row;
    }

    /**
     * Check the extension against the component/field configuration.
     *
     * @param   string       $ext    The lower-case extension.
     * @param   ?object      $field  The optional target field.
     *
     * @return  void
     */
    private function checkExtension(string $ext, ?object $field): void
    {
        $params = ComponentHelper::getParams('com_flexicontent');
        $list   = (string) $params->get(
            'upload_extensions',
            'bmp,wbmp,csv,doc,docx,webp,gif,ico,jpg,jpeg,odg,odp,ods,odt,pdf,png,ppt,pptx,txt,xcf,xls,xlsx,zip,ics'
        );

        if ($field !== null) {
            $fieldParams = new \Joomla\Registry\Registry($field->attribs ?? '');
            $fieldExts   = trim((string) $fieldParams->get('upload_extensions', ''));

            if ($fieldExts !== '') {
                $list = $fieldExts;
            } elseif (($field->field_type ?? '') === 'image') {
                // Match image.xml's default instead of accepting every file type
                // allowed by the component when the field has no override.
                $list = 'bmp,wbmp,gif,jpg,jpeg,png,webp,ico';
            }
        }

        $allowed = array_map('strtolower', array_map('trim', preg_split('/[\s]*,[\s]*/', $list) ?: []));

        if ($ext === '' || !\in_array($ext, $allowed, true)) {
            throw new SaveException('This file extension is not allowed.', 422);
        }
    }

    /**
     * Check the file size against the component/field configuration.
     *
     * @param   integer  $size   The file size in bytes.
     * @param   ?object  $field  The optional target field.
     *
     * @return  void
     */
    private function checkSize(int $size, ?object $field): void
    {
        $params = ComponentHelper::getParams('com_flexicontent');
        $max    = (int) $params->get('upload_maxsize', 10000000);

        if ($field !== null) {
            $fieldParams = new \Joomla\Registry\Registry($field->attribs ?? '');
            $fieldMax    = (int) $fieldParams->get('upload_maxsize', 0);

            if ($fieldMax > 0) {
                $max = $fieldMax;
            }
        }

        if ($max > 0 && $size > $max) {
            throw new SaveException('The file is larger than the configured upload limit.', 422);
        }
    }

    /**
     * Reject files whose contents do not match a supported image extension.
     *
     * @param   string  $bytes  The file contents.
     * @param   string  $ext    The lower-case extension.
     *
     * @return  void
     */
    private function checkImage(string $bytes, string $ext): void
    {
        if (!\in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)) {
            return;
        }

        if (!@getimagesizefromstring($bytes)) {
            throw new SaveException('The file is not a valid image.', 422);
        }

        // getimagesizefromstring() only reads the header. FLEXIcontent's
        // thumbnailing uses GD/ImageMagick, so reject truncated or corrupt
        // images here instead of leaving an unusable value on the item.
        if (\function_exists('imagecreatefromstring')) {
            $image = @imagecreatefromstring($bytes);

            if ($image === false) {
                throw new SaveException('The file is not a decodable image.', 422);
            }

            if (\is_resource($image) || $image instanceof \GdImage) {
                imagedestroy($image);
            }
        }
    }

    /**
     * Detect a display mime type for the response.
     *
     * @param   string  $bytes  The file contents.
     *
     * @return  string
     */
    private function mimeType(string $bytes): string
    {
        if (\function_exists('finfo_open')) {
            $info = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $info ? finfo_buffer($info, $bytes) : false;

            if ($info) {
                finfo_close($info);
            }

            if (\is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        return 'application/octet-stream';
    }

    /**
     * Register the stored file in FLEXIcontent's file table.
     *
     * @param   string  $filename   The stored name.
     * @param   string  $original   The client-supplied name.
     * @param   string  $ext        The lower-case extension.
     * @param   string  $bytes      The file contents.
     * @param   integer $secure     Storage area flag.
     * @param   array   $payload    The upload payload.
     *
     * @return  integer
     */
    private function createFileRecord(
        string $filename,
        string $original,
        string $ext,
        string $bytes,
        int $secure,
        array $payload
    ): int {
        $db   = Factory::getContainer()->get(DatabaseInterface::class);
        $user = AccessHelper::getIdentity();
        $date = Factory::getDate()->toSql();

        $row = new \stdClass;
        $row->filename          = $filename;
        $row->filename_original = $original;
        $row->altname           = (string) ($payload['alt'] ?? $filename);
        $row->description       = (string) ($payload['description'] ?? '');
        $row->url               = 0;
        $row->secure            = $secure;
        $row->stamp             = 1;
        $row->ext               = $ext;
        $row->language          = '*';
        $row->access            = 1;
        $row->hits              = 0;
        $row->size              = strlen($bytes);
        $row->uploaded          = $date;
        $row->uploaded_by       = (int) $user->id;

        \flexicontent_db::fitRecordToTable($row, '#__flexicontent_files', true);
        $db->insertObject('#__flexicontent_files', $row);

        return (int) $db->insertid();
    }
}

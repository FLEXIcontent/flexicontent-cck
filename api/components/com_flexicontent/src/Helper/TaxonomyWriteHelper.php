<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\Helper;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\Exception\Save as SaveException;
use Joomla\Database\DatabaseInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Payload mapping and write ACL for the FLEXIcontent taxonomies.
 *
 * The API accepts either a flat JSON object or a JSON:API "data.attributes"
 * envelope. Only whitelisted columns are forwarded to the legacy models; the
 * id, asset id, checkout fields and computed counters are never client-driven.
 *
 * @since  5.3
 */
final class TaxonomyWriteHelper
{
    /**
     * Resource specifications.
     *
     * @var    array
     */
    private const RESOURCES = [
        'types' => [
            'table'    => '#__flexicontent_types',
            'fields'   => ['name', 'alias', 'description', 'published', 'itemscreatable', 'access', 'attribs'],
            'defaults' => ['published' => 1, 'itemscreatable' => 0, 'access' => 1, 'description' => ''],
            'required' => ['name'],
            'attribs'  => true,
        ],
        'fields' => [
            'table'    => '#__flexicontent_fields',
            'fields'   => [
                'field_type', 'name', 'label', 'description', 'published', 'access', 'ordering',
                'issearch', 'isadvsearch', 'isfilter', 'isadvfilter', 'untranslatable',
                'formhidden', 'valueseditable', 'edithelp', 'positions', 'attribs',
            ],
            'defaults' => [
                'field_type' => 'text', 'published' => 1, 'access' => 1, 'ordering' => 999,
                'issearch' => 1, 'isadvsearch' => 0, 'isfilter' => 0, 'isadvfilter' => 0,
                'untranslatable' => 0, 'formhidden' => 0, 'valueseditable' => 0, 'edithelp' => 2,
                'positions' => '', 'description' => '',
            ],
            'required' => ['label'],
            'attribs'  => true,
        ],
        'categories' => [
            'table'    => '#__categories',
            'fields'   => [
                'title', 'alias', 'parent_id', 'published', 'access', 'language',
                'note', 'description', 'metadesc', 'metakey', 'metadata', 'params',
            ],
            'defaults' => ['parent_id' => 1, 'published' => 1, 'access' => 1, 'language' => '*'],
            'required' => ['title'],
        ],
        'tags' => [
            'table'    => '#__flexicontent_tags',
            'fields'   => ['name', 'alias', 'published', 'jtag_id'],
            'defaults' => ['published' => 1],
            'required' => ['name'],
        ],
    ];

    /**
     * Unwrap a JSON:API envelope if present.
     *
     * @param   array  $data  The decoded request payload.
     *
     * @return  array
     */
    public static function unwrap(array $data): array
    {
        return WriteHelper::unwrap($data);
    }

    /**
     * Build the legacy-shaped data array for a taxonomy write.
     *
     * @param   string       $resource  The API resource name.
     * @param   array        $payload   The normalised API payload.
     * @param   ?object      $stored    The stored row on PATCH, null on POST.
     * @param   boolean      $isNew     True on POST.
     *
     * @return  array
     */
    public static function normalize(string $resource, array $payload, ?object $stored, bool $isNew): array
    {
        $spec    = self::spec($resource);
        $payload = self::unwrap($payload);
        $data    = [];

        foreach ($spec['fields'] as $key) {
            if (\array_key_exists($key, $payload)) {
                $data[$key] = $payload[$key];
            }
        }

        // A partial PATCH keeps every value the client did not send.
        if ($stored !== null) {
            foreach ($spec['fields'] as $key) {
                if (!\array_key_exists($key, $data) && isset($stored->{$key})) {
                    $data[$key] = $stored->{$key};
                }
            }
        }

        foreach ($spec['defaults'] as $key => $value) {
            if (!\array_key_exists($key, $data)) {
                $data[$key] = $value;
            }
        }

        // Never trust a client-supplied id or internal column.
        $data['id'] = $isNew ? 0 : (int) ($stored->id ?? 0);

        foreach (['asset_id', 'checked_out', 'checked_out_time', 'lft', 'rgt', 'level', 'path'] as $internal) {
            unset($data[$internal]);
        }

        if (!empty($spec['attribs'])) {
            $attribs = $stored !== null ? self::decodeRegistry($stored->attribs ?? '') : [];

            if (isset($payload['attribs'])) {
                $attribs = array_merge($attribs, self::toArray($payload['attribs']));
            }

            // The legacy admin controllers copy these raw settings into attribs.
            foreach (['layouts', 'iflayout'] as $rawKey) {
                if (isset($payload[$rawKey])) {
                    $attribs[$rawKey] = $payload[$rawKey];
                }
            }

            $data['attribs'] = $attribs;
        }

        if ($resource === 'categories') {
            $params = $stored !== null ? self::decodeRegistry($stored->params ?? '') : [];

            if (isset($payload['params'])) {
                $params = array_merge($params, self::toArray($payload['params']));
            }

            if (isset($payload['layouts'])) {
                $params['layouts'] = $payload['layouts'];
            }

            $data['params'] = $params;
            $data['extension'] = self::categoryExtension();
        }

        if ($resource === 'fields') {
            if (\array_key_exists('positions', $data) && \is_array($data['positions'])) {
                $data['positions'] = implode("\n", $data['positions']);
            }

            // The legacy model rebuilds type relations from 'tid' on every save.
            $types = $payload['tid'] ?? $payload['type_ids'] ?? $payload['types'] ?? null;

            if ($types === null && $stored !== null) {
                $types = self::loadFieldTypes((int) $stored->id);
            }

            $data['tid'] = self::idList($types);
        }

        if ($resource === 'tags') {
            if (!empty($data['jtag_id'])) {
                $jtagId = (int) $data['jtag_id'];

                if (!self::joomlaTagExists($jtagId)) {
                    throw new SaveException('The supplied jtag_id does not exist.', 422);
                }

                $data['jtag_id'] = $jtagId;
            } else {
                $data['jtag_id'] = 0;
            }
        }

        return $data;
    }

    /**
     * Check the mandatory fields of a creation payload.
     *
     * @param   string  $resource  The API resource name.
     * @param   array   $payload   The normalised payload.
     *
     * @return  void
     */
    public static function validateCreate(string $resource, array $payload): void
    {
        $spec = self::spec($resource);
        $payload = self::unwrap($payload);

        foreach ($spec['required'] as $key) {
            if (!isset($payload[$key]) || trim((string) $payload[$key]) === '') {
                throw new SaveException(ucfirst($key) . ' is required to create a ' . rtrim($resource, 's') . '.', 422);
            }
        }

        if ($resource === 'fields' && !empty($payload['iscore'])) {
            throw new SaveException('Creating core fields via the API is not allowed.', 422);
        }

        if ($resource === 'tags' && !empty($payload['jtag_id']) && !self::joomlaTagExists((int) $payload['jtag_id'])) {
            throw new SaveException('The supplied jtag_id does not exist.', 422);
        }
    }

    /**
     * Load the raw stored row of a taxonomy record.
     *
     * @param   string   $resource  The API resource name.
     * @param   integer  $id        The record id.
     *
     * @return  object|null
     */
    public static function loadStored(string $resource, int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }

        $spec = self::spec($resource);
        $db   = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery();

        $query->select('a.*')
            ->from($db->quoteName($spec['table'], 'a'))
            ->where($db->quoteName('a.id') . ' = ' . $id);

        $row = $db->setQuery($query)->loadObject();

        return $row ?: null;
    }

    /**
     * Can the current user create this resource?
     *
     * @param   string  $resource  The API resource name.
     * @param   array   $data      The normalised payload.
     *
     * @return  boolean
     */
    public static function canCreate(string $resource, array $data = []): bool
    {
        switch ($resource) {
            case 'types':
                return AccessHelper::canManageTypes();

            case 'fields':
                return AccessHelper::canCreateField();

            case 'categories':
                return AccessHelper::canCreateCategory((int) ($data['parent_id'] ?? 1));

            case 'tags':
                if (!AccessHelper::canCreateTag()) {
                    return false;
                }

                // A linked Joomla tag is created unless the client supplies an
                // existing jtag_id, which is validated in normalize().
                return !empty($data['jtag_id']) || AccessHelper::canCreateJoomlaTag();
        }

        return false;
    }

    /**
     * Can the current user edit this resource?
     *
     * @param   string   $resource  The API resource name.
     * @param   integer  $id        The record id.
     *
     * @return  boolean
     */
    public static function canEdit(string $resource, int $id): bool
    {
        switch ($resource) {
            case 'types':
                return AccessHelper::canManageTypes();

            case 'fields':
                return AccessHelper::canEditField($id);

            case 'categories':
                return AccessHelper::canEditCategory($id);

            case 'tags':
                return AccessHelper::canManageTags();
        }

        return false;
    }

    /**
     * Can the current user delete records of this resource in general?
     *
     * @param   string  $resource  The API resource name.
     *
     * @return  boolean
     */
    public static function canDelete(string $resource): bool
    {
        switch ($resource) {
            case 'types':
                return AccessHelper::canManageTypes();

            case 'fields':
                return AccessHelper::canDeleteField();

            case 'categories':
                return AccessHelper::canDeleteCategory();

            case 'tags':
                return AccessHelper::canManageTags();
        }

        return false;
    }

    /**
     * Can the current user delete this specific record?
     *
     * @param   string  $resource  The API resource name.
     * @param   object  $stored    The stored record.
     *
     * @return  boolean
     */
    public static function canDeleteRecord(string $resource, object $stored): bool
    {
        if ($resource === 'fields') {
            return AccessHelper::canDeleteField((int) $stored->id, (int) ($stored->iscore ?? 0));
        }

        if ($resource === 'categories') {
            return AccessHelper::canDeleteCategory((int) $stored->id);
        }

        return true;
    }

    /**
     * Get the resource specification.
     *
     * @param   string  $resource  The API resource name.
     *
     * @return  array
     */
    private static function spec(string $resource): array
    {
        if (!isset(self::RESOURCES[$resource])) {
            throw new SaveException('Unsupported FLEXIcontent resource.', 400);
        }

        return self::RESOURCES[$resource];
    }

    /**
     * Decode a Registry JSON string into an array.
     *
     * @param   string  $value  The stored value.
     *
     * @return  array
     */
    private static function decodeRegistry(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * Normalise a value that should be an array.
     *
     * @param   mixed  $value  The value.
     *
     * @return  array
     */
    private static function toArray($value): array
    {
        if (\is_string($value)) {
            $decoded = json_decode($value, true);

            return \is_array($decoded) ? $decoded : [];
        }

        return \is_array($value) ? $value : (array) $value;
    }

    /**
     * Normalise a list of ids.
     *
     * @param   mixed  $value  An array or a comma separated string.
     *
     * @return  integer[]
     */
    private static function idList($value): array
    {
        if (\is_string($value)) {
            $value = $value === '' ? [] : explode(',', $value);
        }

        $ids = [];

        foreach ((array) $value as $id) {
            if (\is_object($id)) {
                $id = $id->id ?? $id->value ?? null;
            }

            if (is_numeric($id) && (int) $id > 0) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Read the current type ids assigned to a field.
     *
     * @param   integer  $fieldId  The field id.
     *
     * @return  integer[]
     */
    private static function loadFieldTypes(int $fieldId): array
    {
        if ($fieldId <= 0) {
            return [];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        return array_map(
            'intval',
            (array) $db->setQuery(
                $db->createQuery()
                    ->select($db->quoteName('type_id'))
                    ->from($db->quoteName('#__flexicontent_fields_type_relations'))
                    ->where($db->quoteName('field_id') . ' = ' . $fieldId)
            )->loadColumn()
        );
    }

    /**
     * Does a Joomla tag with this id exist?
     *
     * @param   integer  $id  The Joomla tag id.
     *
     * @return  boolean
     */
    private static function joomlaTagExists(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        return (bool) $db->setQuery(
            $db->createQuery()
                ->select('COUNT(*)')
                ->from($db->quoteName('#__tags'))
                ->where($db->quoteName('id') . ' = ' . $id)
        )->loadResult();
    }

    /**
     * Extension used by FLEXIcontent categories.
     *
     * @return  string
     */
    private static function categoryExtension(): string
    {
        $extension = trim((string) ComponentHelper::getParams('com_flexicontent')->get('flexi_cat_extension', 'com_content'));

        return $extension !== '' ? $extension : 'com_content';
    }
}

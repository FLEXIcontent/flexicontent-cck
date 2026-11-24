<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\Helper;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Payload mapping for item writes.
 *
 * Joomla's JSON:API controllers read a flat JSON object (the same shape used by
 * com_content), but clients may also send a JSON:API envelope. This helper
 * normalises both forms to the array expected by the legacy FLEXIcontent item
 * model and preserves stored values that a PATCH does not mention.
 *
 * @since  5.3
 */
final class WriteHelper
{
    /**
     * Core columns accepted from an API payload.
     *
     * Dates are deliberately handled explicitly: a PATCH must not feed an
     * already stored UTC date back through the legacy form's USER_UTC filter.
     *
     * @var    string[]
     */
    private const CORE_FIELDS = [
        'title',
        'alias',
        'catid',
        'type_id',
        'state',
        'access',
        'language',
        'created',
        'created_by',
        'created_by_alias',
        'modified',
        'publish_up',
        'publish_down',
        'ordering',
        'featured',
        'metakey',
        'metadesc',
        'note',
        'vstate',
    ];

    /**
     * Fields copied from the stored row when a PATCH does not provide them.
     *
     * @var    string[]
     */
    private const STORED_FIELDS = [
        'title',
        'alias',
        'catid',
        'type_id',
        'state',
        'access',
        'language',
        'created_by',
        'created_by_alias',
        'ordering',
        'featured',
        'metakey',
        'metadesc',
        'note',
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
        if (!isset($data['data']) || !\is_array($data['data'])) {
            return $data;
        }

        $envelope = $data['data'];

        if (isset($envelope['attributes']) && \is_array($envelope['attributes'])) {
            $attributes = $envelope['attributes'];

            if (!isset($attributes['id']) && !empty($envelope['id'])) {
                $attributes['id'] = (int) $envelope['id'];
            }

            return $attributes;
        }

        return $envelope;
    }

    /**
     * Load the raw stored item row without any read ACL filtering.
     *
     * The write ACL is checked separately by AccessHelper; using the API read
     * model here would hide unpublished items from their own author.
     *
     * @param   integer  $id  The item id.
     *
     * @return  object|null
     */
    public static function loadStoredItem(int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery();

        $query->select('a.*')
            ->select($db->quoteName('ext.type_id'))
            ->from($db->quoteName('#__content', 'a'))
            ->join(
                'INNER',
                $db->quoteName('#__flexicontent_items_ext', 'ext'),
                $db->quoteName('ext.item_id') . ' = ' . $db->quoteName('a.id')
            )
            ->where($db->quoteName('a.id') . ' = ' . $id);

        $item = $db->setQuery($query)->loadObject();

        return $item ?: null;
    }

    /**
     * Read current custom field values, keyed by field name.
     *
     * @param   integer  $itemId  The item id.
     *
     * @return  array
     */
    public static function loadFieldValues(int $itemId): array
    {
        if ($itemId <= 0) {
            return [];
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery();

        $query->select(['f.name', 'v.value'])
            ->from($db->quoteName('#__flexicontent_fields_item_relations', 'v'))
            ->join(
                'INNER',
                $db->quoteName('#__flexicontent_fields', 'f'),
                $db->quoteName('f.id') . ' = ' . $db->quoteName('v.field_id')
            )
            ->where($db->quoteName('v.item_id') . ' = ' . $itemId)
            ->where($db->quoteName('f.iscore') . ' = 0')
            ->order($db->quoteName('v.field_id') . ', ' . $db->quoteName('v.valueorder') . ', ' . $db->quoteName('v.suborder'));

        $values = [];

        foreach ((array) $db->setQuery($query)->loadObjectList() as $row) {
            $values[$row->name][] = $row->value;
        }

        foreach ($values as $name => $items) {
            if (\count($items) === 1) {
                $values[$name] = $items[0];
            }
        }

        return $values;
    }

    /**
     * Build the legacy item data array from an API payload.
     *
     * @param   array         $payload  The normalised API payload.
     * @param   object|null   $stored   The stored row on PATCH, null on POST.
     * @param   boolean       $isNew    True when creating an item.
     *
     * @return  array
     */
    public static function prepare(array $payload, ?object $stored, bool $isNew): array
    {
        $payload = self::unwrap($payload);
        $data    = [];

        foreach (self::CORE_FIELDS as $key) {
            if (\array_key_exists($key, $payload)) {
                $data[$key] = $payload[$key];
            }
        }

        // A partial PATCH keeps every core value that was not sent.
        if ($stored !== null) {
            foreach (self::STORED_FIELDS as $key) {
                if (!\array_key_exists($key, $data) && isset($stored->{$key})) {
                    $data[$key] = $stored->{$key};
                }
            }
        }

        $data['id']     = $isNew ? 0 : (int) ($payload['id'] ?? ($stored->id ?? 0));
        $data['vstate'] = (int) ($payload['vstate'] ?? 2);

        // Tags and secondary categories use FLEXIcontent ids, like the 'tag_ids'
        // and 'category_ids' attributes exposed by the read API.
        if (\array_key_exists('tags', $payload)) {
            $data['tags'] = self::idList($payload['tags']);
        } elseif (\array_key_exists('tag_ids', $payload)) {
            $data['tags'] = self::idList($payload['tag_ids']);
        }

        if (\array_key_exists('categories', $payload)) {
            $data['categories'] = self::idList($payload['categories']);
        } elseif (\array_key_exists('category_ids', $payload)) {
            $data['categories'] = self::idList($payload['category_ids']);
        }

        // Legacy saveFields() treats a field absent from $data['custom'] as an
        // empty value and deletes its stored relations. On PATCH we must always
        // supply the current values, even when the payload has no 'fields' key.
        $custom = $stored !== null ? self::loadFieldValues((int) $stored->id) : [];

        if (isset($payload['fields']) && \is_array($payload['fields'])) {
            $provided = $payload['fields'];
        } elseif (isset($payload['custom']) && \is_array($payload['custom'])) {
            $provided = $payload['custom'];
        } elseif (isset($payload['flexi_fields']) && \is_array($payload['flexi_fields'])) {
            $provided = [];

            foreach ($payload['flexi_fields'] as $name => $entry) {
                $provided[$name] = \is_array($entry) ? ($entry['value'] ?? ($entry['raw'] ?? null)) : $entry;
            }
        } else {
            $provided = [];
        }

        foreach ($provided as $name => $value) {
            if (\is_array($value) && \array_key_exists('value', $value)) {
                $value = $value['value'];
            }

            $custom[$name] = $value;
        }

        $data['custom'] = $custom;

        // ParentClassItem::store() always rebuilds introtext/fulltext from
        // 'text'; a stored or partial value must therefore be supplied here.
        $data['text'] = self::buildText($payload, $stored);

        return $data;
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
     * Build the 'text' value consumed by ParentClassItem::splitText().
     *
     * 'text' is canonical. 'introtext' and 'fulltext' are also accepted and are
     * combined with the stored counterpart when only one of them is patched.
     *
     * @param   array         $payload  The normalised API payload.
     * @param   object|null   $stored   The stored row on PATCH, null on POST.
     *
     * @return  string
     */
    private static function buildText(array $payload, ?object $stored): string
    {
        if (isset($payload['text']) && \is_string($payload['text'])) {
            return $payload['text'];
        }

        $intro = $stored !== null ? (string) ($stored->introtext ?? '') : '';
        $full  = $stored !== null ? (string) ($stored->fulltext ?? '') : '';

        if (\array_key_exists('introtext', $payload)) {
            $intro = (string) $payload['introtext'];
        }

        if (\array_key_exists('fulltext', $payload)) {
            $full = (string) $payload['fulltext'];
        }

        if ($full === '') {
            return $intro;
        }

        return rtrim($intro) . "\n" . '<hr id="system-readmore" />' . "\n" . $full;
    }
}

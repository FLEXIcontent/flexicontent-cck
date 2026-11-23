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
use Joomla\Component\Flexicontent\Api\Legacy\LegacyBootstrap;
use Joomla\Database\DatabaseInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Attach FLEXIcontent custom field values to a single API item.
 *
 * The legacy field classes are used first (access checks, field parameters and
 * rendered HTML). If they cannot run in the API application, the values are
 * read directly from the FLEXIcontent tables without changing the item query.
 *
 * @since  5.3
 */
final class FlexiFieldsHelper
{
    /**
     * Append a 'flexi_fields' array to the item. Never throws.
     *
     * @param   object  $item  The item loaded by the API model.
     *
     * @return  void
     */
    public static function attach(object $item): void
    {
        $item->flexi_fields = [];

        try {
            if (self::attachFromLegacy($item)) {
                return;
            }
        } catch (\Throwable $e) {
            error_log('[FLEXIcontent API] legacy custom fields unavailable: ' . $e->getMessage());
        }

        try {
            self::attachFromDatabase($item);
        } catch (\Throwable $e) {
            error_log('[FLEXIcontent API] custom field values unavailable: ' . $e->getMessage());
        }
    }

    /**
     * Load and render custom fields through the legacy FLEXIcontent classes.
     *
     * @param   object  $item  The item.
     *
     * @return  boolean  True when the legacy path produced a result.
     */
    private static function attachFromLegacy(object $item): bool
    {
        LegacyBootstrap::boot();

        if (!\class_exists('\\FlexicontentFields')) {
            return false;
        }

        // $use_tmpl = false: only load definitions and values, no layout/CSS.
        \FlexicontentFields::getFields($item, 'item', null, null, false);

        if (empty($item->fields)) {
            return false;
        }

        $values = (array) ($item->fieldvalues ?? []);

        foreach ($item->fields as $name => $field) {
            if (!empty($field->iscore) || strpos((string) $name, 'form_') === 0) {
                continue;
            }

            $raw = $values[$field->id] ?? ($field->value ?? []);

            if (empty($raw)) {
                continue;
            }

            $display = null;

            try {
                \FlexicontentFields::renderField($item, $field, $raw, 'display', 'item', false);
                $display = $field->display ?? null;
            } catch (\Throwable $e) {
                // Some field plugins need a site router; keep the raw value.
                $display = null;
            }

            $entry = self::buildEntry($field, $raw, $display);

            if ($entry !== null) {
                $item->flexi_fields[$name] = $entry;
            }
        }

        return true;
    }

    /**
     * Fallback: read field definitions and values straight from the DB.
     *
     * @param   object  $item  The item.
     *
     * @return  void
     */
    private static function attachFromDatabase(object $item): void
    {
        $typeId = (int) ($item->type_id ?? 0);
        $itemId = (int) ($item->id ?? 0);

        if ($typeId <= 0 || $itemId <= 0) {
            return;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery();

        $query->select('f.id, f.name, f.label, f.field_type, v.value, v.valueorder, v.suborder')
            ->from('#__flexicontent_fields_type_relations', 'tr')
            ->join('INNER', '#__flexicontent_fields AS f ON f.id = tr.field_id')
            ->join(
                'LEFT',
                '#__flexicontent_fields_item_relations AS v ON v.field_id = f.id AND v.item_id = ' . $itemId
            )
            ->where('tr.type_id = ' . $typeId)
            ->where('f.published = 1')
            ->where('f.iscore = 0')
            ->where("f.name NOT LIKE 'form\\_%'")
            ->where('f.access IN (' . implode(',', AccessHelper::getViewLevels()) . ')')
            ->order('tr.ordering, f.ordering, v.valueorder, v.suborder');

        $rows = $db->setQuery($query)->loadObjectList();

        $grouped = [];

        foreach ((array) $rows as $row) {
            if ($row->value !== null && $row->value !== '') {
                $grouped[$row->id]['field']  = $row;
                $grouped[$row->id]['raw'][]  = $row->value;
            }
        }

        foreach ($grouped as $fieldId => $data) {
            $entry = self::buildEntry($data['field'], $data['raw'], null);

            if ($entry !== null) {
                $item->flexi_fields[$data['field']->name] = $entry;
            }
        }
    }

    /**
     * Build one API field entry, decoding FLEXI's serialized values.
     *
     * @param   object  $field    The field definition.
     * @param   mixed   $raw      The stored value(s).
     * @param   ?string $display  The legacy-rendered HTML, if available.
     *
     * @return  ?array
     */
    private static function buildEntry(object $field, $raw, ?string $display): ?array
    {
        $rawValues  = \is_array($raw) ? array_values($raw) : [$raw];
        $decoded    = [];

        foreach ($rawValues as $value) {
            $decodedValue = self::decode($value);

            if ($decodedValue !== null && $decodedValue !== '' && $decodedValue !== []) {
                $decoded[] = $decodedValue;
            }
        }

        if (!$decoded) {
            return null;
        }

        return [
            'id'         => (int) $field->id,
            'name'       => (string) $field->name,
            'label'      => (string) ($field->label ?? ''),
            'field_type' => (string) ($field->field_type ?? ''),
            'value'      => \count($decoded) === 1 ? $decoded[0] : $decoded,
            'raw'        => \count($rawValues) === 1 ? $rawValues[0] : $rawValues,
            'display'    => $display,
        ];
    }

    /**
     * Decode a FLEXI field value without allowing object instantiation.
     *
     * @param   mixed  $value  The stored value.
     *
     * @return  mixed
     */
    private static function decode($value)
    {
        if (!\is_string($value)) {
            return $value;
        }

        if (\preg_match('/^(a|s|i|b|d|O):[0-9]+:/', $value) || $value === 'b:0;') {
            $decoded = @unserialize($value, ['allowed_classes' => false]);

            if ($decoded !== false || $value === 'b:0;') {
                return $decoded;
            }
        }

        if (is_numeric($value)) {
            return $value + 0;
        }

        return $value;
    }
}

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
 * Build a SQL condition filtering items by a FLEXIcontent custom field value.
 *
 * A field can be referenced by its name or by its numeric id. Only published
 * fields visible to the current API user may be used. An unknown or forbidden
 * field produces a condition matching no item instead of leaking information.
 *
 * @since  5.3
 */
final class FieldFilterHelper
{
    /**
     * Build the SQL condition, without the leading AND.
     *
     * @param   mixed   $field       Field name or id.
     * @param   mixed   $value       Value to look for.
     * @param   mixed   $match       'exact' (default) or 'like'.
     * @param   string  $itemAlias   Alias of the content table in the query.
     *
     * @return  ?string  The condition, or null when no filter is requested.
     */
    public static function getCondition($field, $value, $match = 'exact', string $itemAlias = 'a'): ?string
    {
        $field = trim((string) $field);

        if ($field === '' || $value === null || $value === '') {
            return null;
        }

        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $fieldId = self::resolveFieldId($db, $field);

        if ($fieldId === 0) {
            // Unknown field, unpublished field or field the user may not use.
            return '1 = 0';
        }

        $query = $db->createQuery();
        $query->select('1')
            ->from($db->quoteName('#__flexicontent_fields_item_relations', 'fv'))
            ->where($db->quoteName('fv.item_id') . ' = ' . $db->quoteName($itemAlias . '.id'))
            ->where($db->quoteName('fv.field_id') . ' = ' . $fieldId);

        $needle = (string) $value;
        $mode   = strtolower(trim((string) $match));

        if ($mode === 'like' || $mode === 'contains') {
            $query->where(
                $db->quoteName('fv.value') . ' LIKE ' . $db->quote('%' . $db->escape($needle) . '%')
            );
        } else {
            $query->where($db->quoteName('fv.value') . ' = ' . $db->quote($needle));
        }

        return 'EXISTS (' . $query . ')';
    }

    /**
     * Resolve a field name/id to a published, accessible field id.
     *
     * @param   DatabaseInterface  $db     The database driver.
     * @param   string             $field  Field name or numeric id.
     *
     * @return  integer  0 when the field cannot be used.
     */
    private static function resolveFieldId(DatabaseInterface $db, string $field): int
    {
        $query = $db->createQuery();
        $query->select($db->quoteName('id'))
            ->from($db->quoteName('#__flexicontent_fields'))
            ->where($db->quoteName('published') . ' = 1');

        if (ctype_digit($field)) {
            $query->where($db->quoteName('id') . ' = ' . (int) $field);
        } else {
            $query->where($db->quoteName('name') . ' = ' . $db->quote($field));
        }

        if (!AccessHelper::canManage()) {
            $levels = implode(',', AccessHelper::getViewLevels());
            $query->where($db->quoteName('access') . ' IN (' . $levels . ')');
        }

        return (int) $db->setQuery($query, 0, 1)->loadResult();
    }
}

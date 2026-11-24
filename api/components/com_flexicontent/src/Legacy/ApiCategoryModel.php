<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\Legacy;

use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Legacy category model adapted to the API application.
 *
 * As for types, the legacy mergeAttributes() XML lookup relies on JPATH_BASE
 * being administrator/. The API helper has already merged the params registry;
 * only a default for the legacy copycid input remains to be supplied.
 *
 * @since  5.3
 */
class ApiCategoryModel extends \FlexicontentModelCategory
{
    /**
     * Bind the merged params without consulting the legacy form XML.
     *
     * @param   object  $record  The table record.
     * @param   array   $data    The validated data (by reference).
     *
     * @return  void
     */
    protected function _prepareBind($record, &$data)
    {
        if (isset($data['params'])) {
            $record->params = (new Registry((array) $data['params']))->toString();
            unset($data['params']);
        }

        $data['copycid'] = (int) ($data['copycid'] ?? 0);

        // FCModelAdmin::_prepareBind() checks class_uses($this) to detect the
        // nestable trait. On this adapter the trait belongs to the parent class,
        // so the legacy check misses it and leaves every API-created category
        // at the root. Apply the same relocation explicitly.
        if (property_exists($record, 'parent_id') && method_exists($record, 'setLocation')) {
            $parentId = (int) ($data['parent_id'] ?? 0);

            if ($parentId > 0 && (int) $record->parent_id !== $parentId) {
                $record->setLocation($parentId, 'last-child');
            }
        }

        \FCModelAdmin::_prepareBind($record, $data);
    }
}

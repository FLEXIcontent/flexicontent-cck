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
 * Legacy item model adapted to the API application.
 *
 * The legacy mergeAttributes() resolves XML files relative to JPATH_BASE,
 * which is the api/ application. Instead of duplicating form definitions into
 * the API tree, the API only needs to merge the JSON parameter groups, so this
 * subclass replaces that one method with a direct Registry merge.
 *
 * @since  5.3
 */
class ApiLegacyItemModel extends \FlexicontentModelItem
{
    /**
     * Merge JSON parameter groups into the item record.
     *
     * @param   object  $item        The item record being saved.
     * @param   array   $data        The validated item data.
     * @param   array   $properties  The parameter columns to merge.
     * @param   array   $options     The legacy merge options (unused here).
     *
     * @return  void
     */
    public function mergeAttributes(&$item, &$data, $properties, $options)
    {
        foreach ($properties as $property) {
            if (!isset($data[$property]) || !\is_array($data[$property])) {
                continue;
            }

            $registry = new Registry($item->{$property} ?? '');
            $registry->loadArray($data[$property]);
            $item->{$property} = $registry->toString();
        }
    }
}

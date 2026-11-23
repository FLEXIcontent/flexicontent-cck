<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\Model;

use Joomla\CMS\MVC\Model\ItemModel as BaseItemModel;
use Joomla\Component\Flexicontent\Api\Helper\AccessHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Single item model for the API.
 *
 * MVP spike: read-only. Loads the Joomla content row joined with the
 * FLEXIcontent items extension table (which carries the FLEXI type).
 * The ApiController sets the requested id in the 'item.id' model state.
 *
 * @since  5.3
 */
class ItemModel extends BaseItemModel
{
    /**
     * Method to get a single item.
     *
     * @param   integer  $pk  The id of the item to load (defaults to the 'item.id' state).
     *
     * @return  object|null  The item data object, or null when not found / not a FLEXIcontent item.
     */
    public function getItem($pk = null)
    {
        $pk = (int) ($pk ?: $this->getState('item.id'));

        if (!$pk) {
            return null;
        }

        if (\array_key_exists($pk, (array) $this->_item)) {
            return $this->_item[$pk];
        }

        $db    = $this->getDatabase();
        $query = $db->createQuery();

        $query->select('a.*')
            ->select($db->quoteName('ext.type_id'))
            ->from($db->quoteName('#__content', 'a'))
            ->join(
                'INNER',
                $db->quoteName('#__flexicontent_items_ext', 'ext'),
                $db->quoteName('ext.item_id') . ' = ' . $db->quoteName('a.id')
            )
            ->where($db->quoteName('a.id') . ' = ' . $pk);

        $item = $db->setQuery($query)->loadObject();

        // Do not leak the existence of an item the user is not allowed to see
        $this->_item[$pk] = ($item && AccessHelper::isItemVisible($item)) ? $item : null;

        return $this->_item[$pk];
    }
}
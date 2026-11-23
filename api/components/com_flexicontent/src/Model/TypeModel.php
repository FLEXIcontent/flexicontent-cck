<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\Model;

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ItemModel as BaseItemModel;
use Joomla\Component\Flexicontent\Api\Helper\AccessHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Single FLEXIcontent content type model for the API.
 *
 * @since  5.3
 */
class TypeModel extends BaseItemModel
{
    /**
     * Get one FLEXIcontent content type.
     *
     * @param   integer  $pk  The type id (defaults to the 'type.id' state).
     *
     * @return  object|null
     */
    public function getItem($pk = null)
    {
        $pk = (int) ($pk ?: $this->getState('type.id'));

        if (!$pk) {
            return null;
        }

        if (\array_key_exists($pk, (array) $this->_item)) {
            return $this->_item[$pk];
        }

        $db        = $this->getDatabase();
        $query     = $db->createQuery();
        $itemCount = $db->createQuery();

        $itemCount->select('COUNT(*)')
            ->from($db->quoteName('#__flexicontent_items_ext', 'item'));

        AccessHelper::applyItemCountRestrictions($itemCount, $db, 'item', 'item_id');

        $itemCount->where($db->quoteName('item.type_id') . ' = ' . $db->quoteName('a.id'));

        $query->select([
                'a.id',
                'a.name',
                'a.alias',
                'a.description',
                'a.published',
                'a.itemscreatable',
                'a.checked_out',
                'a.checked_out_time',
                'a.access',
                '(' . $itemCount . ') AS item_count',
            ])
            ->from($db->quoteName('#__flexicontent_types', 'a'))
            ->where($db->quoteName('a.id') . ' = ' . $pk);

        $row = $db->setQuery($query)->loadObject();

        $this->_item[$pk] = ($row && AccessHelper::isPublishedVisible($row)) ? $row : null;

        return $this->_item[$pk];
    }
}

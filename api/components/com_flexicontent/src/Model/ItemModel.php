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
use Joomla\Component\Flexicontent\Api\Helper\FlexiFieldsHelper;
use Joomla\Component\Flexicontent\Api\Legacy\LegacyBootstrap;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Single item model for the API.
 *
 * Read-only adapter: delegates to the legacy site FlexicontentModelItem and
 * enforces the API read ACL on the returned row. The original SQL lookup is
 * kept as a safe fallback if the legacy model cannot be bootstrapped.
 * The ApiController sets the requested id in the 'item.id' model state.
 *
 * @since  5.3
 */
class ItemModel extends BaseItemModel
{
    /**
     * Legacy item model instance. Null = not created yet, false = disabled after an error.
     *
     * @var    \FlexicontentModelItem|false|null
     */
    private $legacyModel = null;

    /**
     * Get the legacy FLEXIcontent site item model.
     *
     * @return  \FlexicontentModelItem|null
     */
    private function getLegacyModel()
    {
        if ($this->legacyModel === false) {
            return null;
        }

        if ($this->legacyModel instanceof \FlexicontentModelItem) {
            return $this->legacyModel;
        }

        try {
            LegacyBootstrap::boot();
            $this->legacyModel = new \FlexicontentModelItem(['ignore_request' => true]);
        } catch (\Throwable $e) {
            $this->legacyModel = false;
            error_log('[FLEXIcontent API] legacy item model unavailable: ' . $e->getMessage());

            return null;
        }

        return $this->legacyModel;
    }

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

        $legacy = $this->getLegacyModel();

        if ($legacy !== null) {
            try {
                $legacy->setId($pk);
                $item = $legacy->getItem($pk);

                if (!\is_object($item) || !AccessHelper::isItemVisible($item)) {
                    $item = null;
                }

                if ($item !== null) {
                    FlexiFieldsHelper::attach($item);
                }

                $this->_item[$pk] = $item;

                return $this->_item[$pk];
            } catch (\Throwable $e) {
                $this->legacyModel = false;
                error_log('[FLEXIcontent API] legacy item lookup failed: ' . $e->getMessage());
            }
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
        if ($item && AccessHelper::isItemVisible($item)) {
            FlexiFieldsHelper::attach($item);
            $this->_item[$pk] = $item;
        } else {
            $this->_item[$pk] = null;
        }

        return $this->_item[$pk];
    }
}
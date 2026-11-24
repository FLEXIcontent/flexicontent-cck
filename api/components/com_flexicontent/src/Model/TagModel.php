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
 * Single FLEXIcontent tag model for the API.
 *
 * @since  5.3
 */
class TagModel extends BaseItemModel
{
    use TaxonomyWriteTrait;

    /**
     * Legacy model class used for persistence.
     *
     * @return  string
     */
    protected function legacyModelClass(): string
    {
        return \FlexicontentModelTag::class;
    }

    /**
     * Legacy list model class used for deletion.
     *
     * @return  string
     */
    protected function legacyListModelClass(): string
    {
        return \FlexicontentModelTags::class;
    }

    /**
     * Get one FLEXIcontent tag.
     *
     * @param   integer  $pk  The tag id (defaults to the 'tag.id' state).
     *
     * @return  object|null
     */
    public function getItem($pk = null)
    {
        $pk = (int) ($pk ?: $this->getState('tag.id'));

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
            ->from($db->quoteName('#__flexicontent_tags_item_relations', 'rel'));

        AccessHelper::applyItemCountRestrictions($itemCount, $db, 'rel', 'itemid');

        $itemCount->where($db->quoteName('rel.tid') . ' = ' . $db->quoteName('a.id'));

        $query->select([
                'a.id',
                'a.name',
                'a.alias',
                'a.published',
                'a.checked_out',
                'a.checked_out_time',
                'a.jtag_id',
                'jt.title AS jtag_title',
                'jt.alias AS jtag_alias',
                'jt.published AS jtag_published',
                'jt.access AS jtag_access',
                '(' . $itemCount . ') AS item_count',
            ])
            ->from($db->quoteName('#__flexicontent_tags', 'a'))
            ->leftJoin($db->quoteName('#__tags', 'jt') . ' ON ' . $db->quoteName('jt.id') . ' = ' . $db->quoteName('a.jtag_id'))
            ->where($db->quoteName('a.id') . ' = ' . $pk);

        $row    = $db->setQuery($query)->loadObject();
        $visible = false;

        if ($row) {
            if (AccessHelper::canManage()) {
                $visible = true;
            } elseif ((int) $row->published === 1) {
                $visible = empty($row->jtag_id)
                    || ((int) $row->jtag_published === 1
                        && \in_array((int) $row->jtag_access, AccessHelper::getViewLevels(), true));
            }
        }

        $this->_item[$pk] = $visible ? $row : null;

        return $this->_item[$pk];
    }
}

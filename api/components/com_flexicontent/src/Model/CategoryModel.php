<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ItemModel as BaseItemModel;
use Joomla\Component\Flexicontent\Api\Helper\AccessHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Single FLEXIcontent category model for the API.
 *
 * @since  5.3
 */
class CategoryModel extends BaseItemModel
{
    use TaxonomyWriteTrait;

    /**
     * Legacy model class used for persistence.
     *
     * @return  string
     */
    protected function legacyModelClass(): string
    {
        return \Joomla\Component\Flexicontent\Api\Legacy\ApiCategoryModel::class;
    }

    /**
     * Legacy list model class used for deletion.
     *
     * @return  string
     */
    protected function legacyListModelClass(): string
    {
        return \FlexicontentModelCategories::class;
    }

    /**
     * Constructor.
     *
     * @param   array                 $config   An optional associative array of configuration settings.
     * @param   ?MVCFactoryInterface  $factory  The factory.
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        parent::__construct($config, $factory);
    }

    /**
     * Get one FLEXIcontent category.
     *
     * @param   integer  $pk  The category id (defaults to the 'category.id' state).
     *
     * @return  object|null
     */
    public function getItem($pk = null)
    {
        $pk = (int) ($pk ?: $this->getState('category.id'));

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
            ->from($db->quoteName('#__flexicontent_cats_item_relations', 'rel'));

        AccessHelper::applyItemCountRestrictions($itemCount, $db, 'rel', 'itemid');

        $itemCount->where($db->quoteName('rel.catid') . ' = ' . $db->quoteName('a.id'));

        $query->select([
                'a.id',
                'a.title',
                'a.alias',
                'a.note',
                'a.description',
                'a.extension',
                'a.published',
                'a.access',
                'a.parent_id',
                'a.level',
                'a.path',
                'a.language',
                'a.metadesc',
                'a.metakey',
                'a.metadata',
                'a.created_user_id',
                'a.created_time',
                'a.modified_user_id',
                'a.modified_time',
                'a.hits',
                '(' . $itemCount . ') AS item_count',
            ])
            ->from($db->quoteName('#__categories', 'a'))
            ->where($db->quoteName('a.id') . ' = ' . $pk);

        $extension = trim((string) ComponentHelper::getParams('com_flexicontent')->get('flexi_cat_extension', 'com_content'));

        $query->where($db->quoteName('a.extension') . ' = ' . $db->quote($extension ?: 'com_content'));

        $row = $db->setQuery($query)->loadObject();

        $this->_item[$pk] = ($row && AccessHelper::isPublishedVisible($row)) ? $row : null;

        return $this->_item[$pk];
    }
}

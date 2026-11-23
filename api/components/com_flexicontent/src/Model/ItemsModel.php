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
use Joomla\CMS\MVC\Model\ListModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Items list model for the API.
 *
 * MVP spike: reads the Joomla content table joined with the FLEXIcontent
 * items extension table (which carries the FLEXI type). Only rows having a
 * FLEXIcontent extension record are returned (INNER JOIN), i.e. items managed
 * by FLEXIcontent. Reusing the legacy FlexicontentModelItems business logic
 * through an adapter is planned for the next phase.
 *
 * @since  5.3
 */
class ItemsModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array                 $config   An optional associative array of configuration settings.
     * @param   ?MVCFactoryInterface  $factory  The factory.
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'title', 'a.title',
                'alias', 'a.alias',
                'catid', 'a.catid',
                'state', 'a.state',
                'created', 'a.created',
                'modified', 'a.modified',
                'ordering', 'a.ordering',
                'language', 'a.language',
                'type_id', 'ext.type_id',
            ];
        }

        parent::__construct($config, $factory);
    }

    /**
     * Method to get an object implementing QueryInterface for retrieving the data set from a database.
     *
     * @return  \Joomla\Database\QueryInterface
     */
    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->createQuery();

        $query->select('a.*')
            ->select($db->quoteName('ext.type_id'))
            ->from($db->quoteName('#__content', 'a'))
            ->join(
                'INNER',
                $db->quoteName('#__flexicontent_items_ext', 'ext'),
                $db->quoteName('ext.item_id') . ' = ' . $db->quoteName('a.id')
            );

        // Filter: search (title)
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            $query->where($db->quoteName('a.title') . ' LIKE ' . $db->quote('%' . $db->escape($search, true) . '%'));
        }

        // Filter: publication state
        $published = $this->getState('filter.published');

        if (is_numeric($published)) {
            $query->where($db->quoteName('a.state') . ' = ' . (int) $published);
        }

        // Filter: category
        $categoryId = $this->getState('filter.category_id');

        if (is_numeric($categoryId)) {
            $query->where($db->quoteName('a.catid') . ' = ' . (int) $categoryId);
        }

        // Filter: FLEXIcontent type
        $typeId = $this->getState('filter.type_id');

        if (is_numeric($typeId)) {
            $query->where($db->quoteName('ext.type_id') . ' = ' . (int) $typeId);
        }

        // Filter: language
        $language = $this->getState('filter.language');

        if ($language) {
            $query->where($db->quoteName('a.language') . ' = ' . $db->quote($language));
        }

        // Ordering (validated against filter_fields by the controller)
        $query->order(
            $db->escape($this->getState('list.ordering', 'a.id')) . ' ' . $db->escape($this->getState('list.direction', 'DESC'))
        );

        return $query;
    }
}
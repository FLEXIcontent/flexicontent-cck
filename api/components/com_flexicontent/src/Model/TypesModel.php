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
use Joomla\Component\Flexicontent\Api\Helper\AccessHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * FLEXIcontent content types list model for the API.
 *
 * @since  5.3
 */
class TypesModel extends ListModel
{
    /**
     * Whitelist of accepted ordering columns.
     *
     * @var    array
     */
    private $sortColumns = [
        'id'            => 'a.id',
        'a.id'          => 'a.id',
        'name'          => 'a.name',
        'a.name'        => 'a.name',
        'alias'         => 'a.alias',
        'a.alias'       => 'a.alias',
        'published'     => 'a.published',
        'a.published'   => 'a.published',
        'access'        => 'a.access',
        'a.access'      => 'a.access',
        'itemscreatable'=> 'a.itemscreatable',
        'a.itemscreatable' => 'a.itemscreatable',
    ];

    /**
     * Constructor.
     *
     * @param   array                 $config   An optional associative array of configuration settings.
     * @param   ?MVCFactoryInterface  $factory  The factory.
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array_keys($this->sortColumns);
        }

        parent::__construct($config, $factory);
    }

    /**
     * Build the types list query.
     *
     * @return  \Joomla\Database\QueryInterface
     */
    protected function getListQuery()
    {
        $db        = $this->getDatabase();
        $query     = $db->createQuery();
        $itemCount = $db->createQuery();

        $itemCount->select('COUNT(*)')
            ->from($db->quoteName('#__flexicontent_items_ext', 'item'))
            ->where($db->quoteName('item.type_id') . ' = ' . $db->quoteName('a.id'));

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
            ->from($db->quoteName('#__flexicontent_types', 'a'));

        $search = trim((string) $this->getState('filter.search', ''));

        if ($search !== '') {
            $like = '%' . $db->escape($search, true) . '%';
            $query->where(
                '(' . $db->quoteName('a.name') . ' LIKE ' . $db->quote($like)
                . ' OR ' . $db->quoteName('a.alias') . ' LIKE ' . $db->quote($like) . ')'
            );
        }

        $published = $this->getState('filter.published');

        if (is_numeric($published)) {
            $query->where($db->quoteName('a.published') . ' = ' . (int) $published);
        }

        $access = $this->getState('filter.access');

        if (is_numeric($access)) {
            $query->where($db->quoteName('a.access') . ' = ' . (int) $access);
        }

        AccessHelper::applyPublishedRestrictions($query, $db, 'a');

        $ordering  = (string) $this->getState('list.ordering', 'a.name');
        $direction = strtolower((string) $this->getState('list.direction', 'asc'));
        $direction = \in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

        $query->order(($this->sortColumns[$ordering] ?? 'a.name') . ' ' . $direction);

        return $query;
    }
}

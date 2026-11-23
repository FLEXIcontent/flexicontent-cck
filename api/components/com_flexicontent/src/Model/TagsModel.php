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
 * FLEXIcontent tags list model for the API.
 *
 * Tags come from #__flexicontent_tags. The optional Joomla tag bridge is
 * exposed as jtag_id / jtag_title so clients can link to Joomla's tag resource.
 *
 * @since  5.3
 */
class TagsModel extends ListModel
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
        'jtag_id'       => 'a.jtag_id',
        'a.jtag_id'     => 'a.jtag_id',
        'jtag_title'    => 'jt.title',
        'jt.title'      => 'jt.title',
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
     * Build the tags list query.
     *
     * @return  \Joomla\Database\QueryInterface
     */
    protected function getListQuery()
    {
        $db        = $this->getDatabase();
        $query     = $db->createQuery();
        $itemCount = $db->createQuery();

        $itemCount->select('COUNT(*)')
            ->from($db->quoteName('#__flexicontent_tags_item_relations', 'rel'))
            ->where($db->quoteName('rel.tid') . ' = ' . $db->quoteName('a.id'));

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
            ->leftJoin($db->quoteName('#__tags', 'jt') . ' ON ' . $db->quoteName('jt.id') . ' = ' . $db->quoteName('a.jtag_id'));

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

        $jtag = $this->getState('filter.jtag');

        if (is_numeric($jtag) && \in_array((int) $jtag, [0, 1], true)) {
            if ((int) $jtag === 1) {
                $query->where($db->quoteName('a.jtag_id') . ' IS NOT NULL');
            } else {
                $query->where($db->quoteName('a.jtag_id') . ' IS NULL');
            }
        }

        if (!AccessHelper::canManage()) {
            $levels  = AccessHelper::getViewLevels();
            $jtId    = $db->quoteName('jt.id');
            $jtPub   = $db->quoteName('jt.published');
            $jtAcc   = $db->quoteName('jt.access');

            $query->where($db->quoteName('a.published') . ' = 1')
                ->where(
                    '(' . $jtId . ' IS NULL OR (' . $jtPub . ' = 1 AND '
                    . $jtAcc . ' IN (' . implode(',', $levels) . ')))'
                );
        }

        $ordering  = (string) $this->getState('list.ordering', 'a.name');
        $direction = strtolower((string) $this->getState('list.direction', 'asc'));
        $direction = \in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

        $query->order(($this->sortColumns[$ordering] ?? 'a.name') . ' ' . $direction);

        return $query;
    }
}

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
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Component\Flexicontent\Api\Helper\AccessHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Categories list model for the FLEXIcontent API.
 *
 * FLEXIcontent stores categories in #__categories under the extension set in
 * the component parameters. This model exposes those rows without booting the
 * legacy MVC layer, so the administrator and site applications are untouched.
 *
 * @since  5.3
 */
class CategoriesModel extends ListModel
{
    /**
     * Whitelist of accepted ordering columns.
     *
     * @var    array
     */
    private $sortColumns = [
        'id'         => 'a.id',
        'a.id'       => 'a.id',
        'title'      => 'a.title',
        'a.title'    => 'a.title',
        'alias'      => 'a.alias',
        'a.alias'    => 'a.alias',
        'published'  => 'a.published',
        'a.published'=> 'a.published',
        'access'     => 'a.access',
        'a.access'   => 'a.access',
        'parent_id'  => 'a.parent_id',
        'a.parent_id'=> 'a.parent_id',
        'level'      => 'a.level',
        'a.level'    => 'a.level',
        'path'       => 'a.path',
        'a.path'     => 'a.path',
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
            $config['filter_fields'] = array_values(array_unique(array_merge(
                array_keys($this->sortColumns),
                ['language', 'a.language', 'extension', 'a.extension']
            )));
        }

        parent::__construct($config, $factory);
    }

    /**
     * Build the categories list query.
     *
     * @return  \Joomla\Database\QueryInterface
     */
    protected function getListQuery()
    {
        $db       = $this->getDatabase();
        $query    = $db->createQuery();
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
                'a.created_user_id',
                'a.created_time',
                'a.modified_user_id',
                'a.modified_time',
                'a.hits',
                '(' . $itemCount . ') AS item_count',
            ])
            ->from($db->quoteName('#__categories', 'a'));

        $extension = trim((string) ComponentHelper::getParams('com_flexicontent')->get('flexi_cat_extension', 'com_content'));

        $query->where($db->quoteName('a.extension') . ' = ' . $db->quote($extension ?: 'com_content'));

        $search = trim((string) $this->getState('filter.search', ''));

        if ($search !== '') {
            // escape(..., true) protects SQL and LIKE wildcards; quote(..., false)
            // avoids escaping the result a second time.
            $like = $db->quote('%' . $db->escape($search, true) . '%', false);
            $query->where(
                '(' . $db->quoteName('a.title') . ' LIKE ' . $like
                . ' OR ' . $db->quoteName('a.alias') . ' LIKE ' . $like . ')'
            );
        }

        $published = $this->getState('filter.published');

        if (is_numeric($published)) {
            $query->where($db->quoteName('a.published') . ' = ' . (int) $published);
        }

        $parentId = $this->getState('filter.parent_id');

        if (is_numeric($parentId)) {
            $query->where($db->quoteName('a.parent_id') . ' = ' . (int) $parentId);
        }

        $level = $this->getState('filter.level');

        if (is_numeric($level)) {
            $query->where($db->quoteName('a.level') . ' = ' . (int) $level);
        }

        $language = trim((string) $this->getState('filter.language', ''));

        if ($language !== '') {
            $query->where($db->quoteName('a.language') . ' = ' . $db->quote($language));
        }

        $access = $this->getState('filter.access');

        if (is_numeric($access)) {
            $query->where($db->quoteName('a.access') . ' = ' . (int) $access);
        }

        AccessHelper::applyPublishedRestrictions($query, $db, 'a');

        $ordering  = (string) $this->getState('list.ordering', 'a.title');
        $direction = strtolower((string) $this->getState('list.direction', 'asc'));
        $direction = \in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

        $query->order(($this->sortColumns[$ordering] ?? 'a.title') . ' ' . $direction);

        return $query;
    }
}

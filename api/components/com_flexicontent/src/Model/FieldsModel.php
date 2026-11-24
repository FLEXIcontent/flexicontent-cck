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
 * FLEXIcontent fields list model for the API.
 *
 * Fields come from #__flexicontent_fields. The iscore flag is exposed so API
 * clients can distinguish built-in fields from custom ones.
 *
 * @since  5.3
 */
class FieldsModel extends ListModel
{
    /**
     * Whitelist of accepted ordering columns.
     *
     * @var    array
     */
    private $sortColumns = [
        'id'          => 'a.id',
        'a.id'        => 'a.id',
        'name'        => 'a.name',
        'a.name'      => 'a.name',
        'label'       => 'a.label',
        'a.label'     => 'a.label',
        'field_type'  => 'a.field_type',
        'a.field_type' => 'a.field_type',
        'published'   => 'a.published',
        'a.published' => 'a.published',
        'access'      => 'a.access',
        'a.access'    => 'a.access',
        'ordering'    => 'a.ordering',
        'a.ordering'  => 'a.ordering',
        'iscore'      => 'a.iscore',
        'a.iscore'    => 'a.iscore',
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
     * Build the fields list query.
     *
     * @return  \Joomla\Database\QueryInterface
     */
    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->createQuery();

        $query->select([
                'a.id',
                'a.name',
                'a.label',
                'a.field_type',
                'a.description',
                'a.iscore',
                'a.published',
                'a.access',
                'a.ordering',
            ])
            ->from($db->quoteName('#__flexicontent_fields', 'a'));

        $search = trim((string) $this->getState('filter.search', ''));

        if ($search !== '') {
            // escape(..., true) protects SQL and LIKE wildcards; quote(..., false)
            // avoids escaping the result a second time.
            $like = $db->quote('%' . $db->escape($search, true) . '%', false);
            $query->where(
                '(' . $db->quoteName('a.name') . ' LIKE ' . $like
                . ' OR ' . $db->quoteName('a.label') . ' LIKE ' . $like . ')'
            );
        }

        $published = $this->getState('filter.published');

        if (is_numeric($published)) {
            $query->where($db->quoteName('a.published') . ' = ' . (int) $published);
        }

        $core = $this->getState('filter.core');

        if (is_numeric($core) && \in_array((int) $core, [0, 1], true)) {
            $query->where($db->quoteName('a.iscore') . ' = ' . (int) $core);
        }

        // form_* entries are FLEXIcontent configuration pseudo-fields, not
        // writable item fields. They are hidden unless explicitly requested.
        $form   = (int) $this->getState('filter.form', 0);
        $prefix = 'LEFT(' . $db->quoteName('a.name') . ', 5) ';

        if (\in_array($form, [0, 1], true)) {
            $query->where($prefix . ($form === 1 ? '= ' : '<> ') . $db->quote('form_'));
        }

        $access = $this->getState('filter.access');

        if (AccessHelper::canManage() && is_numeric($access)) {
            $query->where($db->quoteName('a.access') . ' = ' . (int) $access);
        }

        $typeId = (int) $this->getState('filter.type_id', 0);

        if ($typeId > 0) {
            $relation = $db->createQuery();
            $relation->select('1')
                ->from($db->quoteName('#__flexicontent_fields_type_relations', 'rel'))
                ->where($db->quoteName('rel.field_id') . ' = ' . $db->quoteName('a.id'))
                ->where($db->quoteName('rel.type_id') . ' = ' . $typeId);

            $query->where('EXISTS (' . $relation . ')');
        }

        AccessHelper::applyPublishedRestrictions($query, $db, 'a');

        $ordering  = (string) $this->getState('list.ordering', 'a.name');
        $direction = strtolower((string) $this->getState('list.direction', 'asc'));
        $direction = \in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

        $query->order(($this->sortColumns[$ordering] ?? 'a.name') . ' ' . $direction);

        return $query;
    }
}

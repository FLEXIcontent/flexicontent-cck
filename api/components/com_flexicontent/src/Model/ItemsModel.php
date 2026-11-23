<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Component\Flexicontent\Api\Helper\AccessHelper;
use Joomla\Component\Flexicontent\Api\Helper\FieldFilterHelper;
use Joomla\Component\Flexicontent\Api\Legacy\LegacyBootstrap;
use Joomla\Component\Flexicontent\Api\Legacy\LegacyItemsModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Items list model for the API.
 *
 * Delegates to the legacy FlexicontentModelItems (through LegacyItemsModel) so
 * FLEXIcontent's own query, joins and item enrichment stay the single source of
 * truth. The original SQL query is kept as a safe fallback if the legacy model
 * cannot be bootstrapped.
 *
 * @since  5.3
 */
class ItemsModel extends ListModel
{
    /**
     * Legacy model instance. Null = not created yet, false = disabled after an error.
     *
     * @var    LegacyItemsModel|false|null
     */
    private $legacyModel = null;

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
                'access', 'a.access',
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
     * Get the legacy FLEXIcontent items model, configured with the API state.
     *
     * @return  LegacyItemsModel|null
     */
    private function getLegacyModel(): ?LegacyItemsModel
    {
        if ($this->legacyModel === false) {
            return null;
        }

        if ($this->legacyModel instanceof LegacyItemsModel) {
            return $this->legacyModel;
        }

        try {
            LegacyBootstrap::boot();
            $this->legacyModel = new LegacyItemsModel([
                'ignore_request' => true,
                'api_state'      => $this->getState(),
            ]);
        } catch (\Throwable $e) {
            $this->disableLegacy($e);
            return null;
        }

        return $this->legacyModel;
    }

    /**
     * Disable the legacy adapter and log the reason; the SQL fallback stays active.
     *
     * @param   \Throwable  $error  The caught error.
     *
     * @return  void
     */
    private function disableLegacy(\Throwable $error): void
    {
        $this->legacyModel = false;
        error_log('[FLEXIcontent API] legacy items model unavailable: ' . $error->getMessage());
    }

    /**
     * Items list from the legacy model, with a safe fallback to the API query.
     *
     * @return  array
     */
    public function getItems()
    {
        $legacy = $this->getLegacyModel();

        if ($legacy !== null) {
            try {
                return $legacy->getItems();
            } catch (\Throwable $e) {
                $this->disableLegacy($e);
            }
        }

        return parent::getItems();
    }

    /**
     * Total from the legacy model, with a safe fallback to the API query.
     *
     * @return  integer
     */
    public function getTotal()
    {
        $legacy = $this->getLegacyModel();

        if ($legacy !== null) {
            try {
                return (int) $legacy->getTotal();
            } catch (\Throwable $e) {
                $this->disableLegacy($e);
            }
        }

        return (int) parent::getTotal();
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
            // escape(..., true) protects SQL and LIKE wildcards; quote(..., false)
            // avoids escaping the result a second time.
            $like = '%' . $db->escape($search, true) . '%';
            $query->where($db->quoteName('a.title') . ' LIKE ' . $db->quote($like, false));
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

        // Filter: author
        $authorId = $this->getState('filter.created_by');

        if (is_numeric($authorId)) {
            $query->where($db->quoteName('a.created_by') . ' = ' . (int) $authorId);
        }

        // Filter: Joomla access level (ACL restrictions are applied below)
        $access = $this->getState('filter.access');

        if (is_numeric($access)) {
            $query->where($db->quoteName('a.access') . ' = ' . (int) $access);
        }

        // Filter: featured
        $featured = $this->getState('filter.featured');

        if (is_numeric($featured)) {
            $query->where($db->quoteName('a.featured') . ' = ' . (int) $featured);
        }

        // Filter: tag
        $tagId = (int) $this->getState('filter.tag');

        if ($tagId > 0) {
            $tagQuery = $db->createQuery();
            $tagQuery->select('1')
                ->from($db->quoteName('#__flexicontent_tags_item_relations', 'tr'))
                ->where($db->quoteName('tr.itemid') . ' = ' . $db->quoteName('a.id'))
                ->where($db->quoteName('tr.tid') . ' = ' . $tagId);

            $query->where('EXISTS (' . $tagQuery . ')');
        }

        // Filter: FLEXIcontent custom field value
        $fieldCondition = FieldFilterHelper::getCondition(
            $this->getState('filter.field', ''),
            $this->getState('filter.field_value', ''),
            $this->getState('filter.field_match', 'exact')
        );

        if ($fieldCondition !== null) {
            $query->where($fieldCondition);
        }

        // Published items and Joomla viewing access levels for non-managers
        AccessHelper::applyListRestrictions($query, $db);

        // Ordering (validated against filter_fields by the controller)
        $query->order(
            $db->escape($this->getState('list.ordering', 'a.id')) . ' ' . $db->escape($this->getState('list.direction', 'DESC'))
        );

        return $query;
    }
}
<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\Legacy;

use Joomla\CMS\Factory;
use Joomla\Component\Flexicontent\Api\Helper\AccessHelper;
use Joomla\Component\Flexicontent\Api\Helper\FieldFilterHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

// The legacy parent class must exist before this class is declared.
LegacyBootstrap::boot();
// phpcs:enable PSR1.Files.SideEffects

/**
 * Legacy items model configured from the API model state.
 *
 * It keeps FLEXIcontent's business logic (query, joins, item enrichment) but
 * translates the JSON:API state into the request/state names used by the
 * legacy admin model. Read ACL is enforced in SQL for non-managers.
 *
 * @since  5.3
 */
class LegacyItemsModel extends \FlexicontentModelItems
{
    /**
     * Ordering columns accepted from the API (must match the API model whitelist).
     *
     * @var    string[]
     */
    private const ORDER_FIELDS = [
        'a.id',
        'a.title',
        'a.alias',
        'a.catid',
        'a.state',
        'a.access',
        'a.created',
        'a.modified',
        'a.ordering',
        'a.language',
        'a.featured',
        'a.hits',
        'ie.type_id',
        't.name',
        'rel.ordering',
    ];

    /**
     * Mapped values, kept to re-apply them after the legacy constructor.
     *
     * @var    array
     */
    private $apiMapping = [];

    /**
     * Constructor.
     *
     * @param   array  $config  Model configuration; 'api_state' carries the API model state.
     */
    public function __construct($config = [])
    {
        $apiState = $config['api_state'] ?? null;

        if ($apiState && \is_object($apiState) && method_exists($apiState, 'get')) {
            $this->apiMapping = $this->mapApiState($apiState);
        }

        parent::__construct($config);

        // The legacy constructor reads the request again: re-apply our values.
        foreach ($this->apiMapping as $name => $value) {
            $this->setState($name, $value);
        }

        // The API has no legacy filter form: never render its HTML/scripts.
        $this->cparams = clone $this->cparams;
        $this->cparams->set('items_manager_custom_filters', '');
    }

    /**
     * Translate the JSON:API state into legacy request values.
     *
     * @param   object  $apiState  The API model state.
     *
     * @return  array  Legacy model states.
     */
    private function mapApiState($apiState): array
    {
        $input    = Factory::getApplication()->input;
        $canManage = AccessHelper::canManage();
        $mapping  = [];

        // Use explicit request values instead of the admin session filters.
        $input->set('fcform', 1);

        $search = (string) $apiState->get('filter.search', '');
        $input->set('search', $search);
        $mapping['search'] = $search;

        // Legacy text search needs an explicit scope; without it the search is
        // silently ignored. Keep the SQL stub's behaviour: search the title.
        $scope = $search !== '' ? 'a.title' : '';
        $input->set('scope', $scope);
        $mapping['scope'] = $scope;

        // FLEXI uses symbolic state codes ('P', 'IP', 'ALL', ...).
        if ($canManage) {
            $published = $apiState->get('filter.published', null);
            $stateCode = $published === null ? 'ALL' : self::stateCode($published);
        } else {
            $stateCode = 'P';
        }

        $filterState = [$stateCode ?: 'ALL'];
        $input->set('filter_state', $filterState);
        $mapping['filter_state'] = $filterState;

        // Keep the API's exact-category semantics (the legacy default includes subcategories).
        $categoryId = (int) $apiState->get('filter.category_id', 0);
        $input->set('filter_cats', $categoryId ?: '');
        $input->set('filter_subcats', 0);
        $mapping['filter_cats']    = $categoryId ?: '';
        $mapping['filter_subcats'] = 0;

        $typeId = (int) $apiState->get('filter.type_id', 0);
        $types  = $typeId ? [$typeId] : [];
        $input->set('filter_type', $types);
        $mapping['filter_type'] = $types;

        $language = (string) $apiState->get('filter.language', '');
        $languages = $language !== '' ? [$language] : [];
        $input->set('filter_lang', $languages);
        $mapping['filter_lang'] = $languages;

        $authorId = (int) $apiState->get('filter.created_by', 0);
        $authors  = $authorId ? [$authorId] : [];
        $input->set('filter_author', $authors);
        $mapping['filter_author'] = $authors;

        // A non-manager cannot widen its access by requesting another level.
        $apiAccess = (int) $apiState->get('filter.access', 0);
        $filterAccess = $canManage
            ? ($apiAccess ? [$apiAccess] : [])
            : array_map('intval', AccessHelper::getViewLevels());
        $input->set('filter_access', $filterAccess);
        $mapping['filter_access'] = $filterAccess;

        $featured = $apiState->get('filter.featured', null);
        $featuredValue = is_numeric($featured) ? (int) $featured : '';
        $input->set('filter_featured', $featuredValue);
        $mapping['filter_featured'] = $featuredValue;

        $tagId = (int) $apiState->get('filter.tag', 0);
        $tags  = $tagId ? [$tagId] : [];
        $input->set('filter_tag', $tags);
        $mapping['filter_tag'] = $tags;

        // Custom field value filtering is applied to the legacy query directly.
        $mapping['filter.field']       = (string) $apiState->get('filter.field', '');
        $mapping['filter.field_value'] = (string) $apiState->get('filter.field_value', '');
        $mapping['filter.field_match'] = (string) $apiState->get('filter.field_match', 'exact');

        $ordering = self::normaliseOrdering((string) $apiState->get('list.ordering', ''));

        if ($ordering !== '') {
            $input->set('filter_order', $ordering);
            $mapping['filter_order'] = $ordering;
        }

        $direction = strtolower((string) $apiState->get('list.direction', ''));
        $direction = \in_array($direction, ['asc', 'desc'], true) ? strtoupper($direction) : 'DESC';
        $input->set('filter_order_Dir', $direction);
        $mapping['filter_order_Dir'] = $direction;

        $limit = (int) $apiState->get('list.limit', 20);
        $limit = $limit > 0 ? min($limit, 1000) : 20;
        $start = (int) $apiState->get('list.start', $apiState->get('items.limitstart', 0));
        $start = max(0, $start);
        $input->set('limit', $limit);
        $input->set('limitstart', $start);
        $mapping['limit']      = $limit;
        $mapping['limitstart'] = $start;

        return $mapping;
    }

    /**
     * Convert an API publication state into a FLEXIcontent state code.
     *
     * @param   mixed  $value  The API state value.
     *
     * @return  ?string  The FLEXI code, or null when not recognised.
     */
    private static function stateCode($value): ?string
    {
        $map = [
            1  => 'P',
            0  => 'U',
            -3 => 'PE',
            -4 => 'OQ',
            -5 => 'IP',
            2  => 'A',
            -2 => 'T',
        ];

        return $map[(int) $value] ?? null;
    }

    /**
     * Normalise an API ordering value to a legacy column name.
     *
     * @param   string  $ordering  The API ordering.
     *
     * @return  string  A whitelisted legacy column, or an empty string.
     */
    private static function normaliseOrdering(string $ordering): string
    {
        $ordering = trim($ordering);

        // The SQL stub used 'ext.type_id'; the legacy query aliases it as 'ie'.
        if ($ordering === 'ext.type_id') {
            $ordering = 'ie.type_id';
        }

        return \in_array($ordering, self::ORDER_FIELDS, true) ? $ordering : '';
    }

    /**
     * The API does not use the legacy filter form.
     *
     * @return  array
     */
    public function getCustomFilts()
    {
        return [];
    }

    /**
     * Belt-and-braces read ACL and custom field filtering in the legacy list query.
     *
     * @param   mixed    $query_ids  Optional ids for the second-stage detail query.
     * @param   boolean  $for_count  Whether this is the count query.
     *
     * @return  string  The SQL query.
     */
    protected function _buildQuery($query_ids = false, $for_count = false)
    {
        $query = parent::_buildQuery($query_ids, $for_count);

        if ($query_ids !== false) {
            return $query;
        }

        $conditions = [];

        if (!AccessHelper::canManage()) {
            $conditions[] = 'a.state = 1';
            $conditions[] = 'a.access IN (' . implode(',', AccessHelper::getViewLevels()) . ')';
        }

        $fieldCondition = FieldFilterHelper::getCondition(
            $this->getState('filter.field', ''),
            $this->getState('filter.field_value', ''),
            $this->getState('filter.field_match', 'exact')
        );

        if ($fieldCondition !== null) {
            $conditions[] = $fieldCondition;
        }

        if (!$conditions) {
            return $query;
        }

        return self::appendWhereConditions($query, $conditions);
    }

    /**
     * Append AND conditions to a legacy SQL string before GROUP BY / ORDER BY.
     *
     * @param   string    $query       The legacy query.
     * @param   string[]  $conditions  Conditions without the leading AND.
     *
     * @return  string
     */
    private static function appendWhereConditions(string $query, array $conditions): string
    {
        $extra  = ' AND ' . implode(' AND ', $conditions) . ' ';
        $marker = false;
        $offset = 0;

        foreach ([' GROUP BY a.id', ' ORDER BY ', ' LIMIT '] as $candidate) {
            $position = strpos($query, $candidate);

            if ($position !== false && ($marker === false || $position < $marker)) {
                $marker = $position;
                $offset = $position;
            }
        }

        if ($marker === false) {
            return rtrim($query) . ' ' . $extra;
        }

        return substr($query, 0, $offset) . $extra . substr($query, $offset);
    }
}

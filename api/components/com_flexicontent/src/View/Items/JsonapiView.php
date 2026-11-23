<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\View\Items;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\JsonApiView as BaseApiView;
use Joomla\Component\Flexicontent\Api\Serializer\ItemsSerializer;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The items API view.
 *
 * @since  5.3
 */
class JsonapiView extends BaseApiView
{
    /**
     * The content type.
     *
     * @var    string
     */
    protected $type = 'items';

    /**
     * The fields to render in list documents.
     *
     * @var    array
     */
    protected $fieldsToRenderList = [
        'id',
        'type_id',
        'type_name',
        'title',
        'alias',
        'catid',
        'category_title',
        'category_ids',
        'tag_ids',
        'author_name',
        'category',
        'categories',
        'modified_by',
        'tags',
        'state',
        'access',
        'created',
        'created_by',
        'modified',
        'publish_up',
        'publish_down',
        'language',
        'ordering',
        'featured',
        'hits',
    ];

    /**
     * The fields to render in item documents.
     *
     * @var    array
     */
    protected $fieldsToRenderItem = [
        'id',
        'type_id',
        'type_name',
        'type_alias',
        'title',
        'alias',
        'slug',
        'catid',
        'category_title',
        'category_alias',
        'category_ids',
        'tag_ids',
        'author_name',
        'category',
        'categories',
        'created_by',
        'modified_by',
        'tags',
        'state',
        'access',
        'created',
        'created_by_alias',
        'modified',
        'modified_by',
        'publish_up',
        'publish_down',
        'language',
        'ordering',
        'featured',
        'hits',
        'version',
        'note',
        'introtext',
        'fulltext',
        'metakey',
        'metadesc',
        'images',
        'urls',
        'attribs',
        'metadata',
    ];

    /**
     * The relationships rendered for each item.
     *
     * @var    array
     */
    protected $relationship = [
        'category',
        'categories',
        'created_by',
        'modified_by',
        'tags',
    ];

    /**
     * Cached lookup tables, loaded once per request.
     *
     * @var    array
     */
    private $lookups = [];

    /**
     * Constructor.
     *
     * @param   array  $config  A named configuration array for object construction.
     */
    public function __construct($config = [])
    {
        // Must be set before the parent constructor, which falls back to JoomlaSerializer.
        $this->serializer = new ItemsSerializer($config['contentType'] ?? $this->type);

        parent::__construct($config);
    }

    /**
     * Prepare item before render: normalize fields and expose FLEXI relations.
     *
     * @param   object  $item  The model item.
     *
     * @return  object
     */
    protected function prepareItem($item)
    {
        if (!\is_object($item)) {
            return $item;
        }

        foreach (['id', 'type_id', 'catid', 'state', 'access', 'created_by', 'modified_by', 'ordering', 'featured', 'hits', 'version'] as $field) {
            if (isset($item->$field)) {
                $item->$field = (int) $item->$field;
            }
        }

        // JSON-encoded columns are exposed as structured values.
        foreach (['images', 'urls'] as $field) {
            if (!empty($item->$field) && \is_string($item->$field)) {
                $decoded = json_decode($item->$field, true);

                if (\is_array($decoded)) {
                    $item->$field = $decoded;
                }
            }
        }

        foreach (['attribs', 'metadata'] as $field) {
            if ($item->$field instanceof Registry) {
                $item->$field = $item->$field->toArray();
            }
        }

        $typeId = (int) ($item->type_id ?? 0);
        $types  = $this->getLookup('types');

        $item->type_name  = $item->type_name ?? $item->typename ?? ($types[$typeId]['name'] ?? '');
        $item->type_alias = $item->type_alias ?? $item->typealias ?? ($types[$typeId]['alias'] ?? '');

        $categoryId = (int) ($item->catid ?? 0);
        $categories = $this->getLookup('categories');

        $item->category_title = $item->category_title ?? $item->maincat_title ?? ($categories[$categoryId]['title'] ?? '');
        $item->category_alias = $item->category_alias ?? $item->maincat_alias ?? ($categories[$categoryId]['alias'] ?? '');

        // Legacy list exposes 'catids'/'tagids', legacy detail 'cats'/'tags'.
        $item->category_ids = $this->idList($item->catids ?? $item->cats ?? null, $categoryId);
        $flexiTagIds        = $this->idList($item->tagids ?? $item->tags ?? null);
        $item->tag_ids      = $flexiTagIds;
        $item->author_name  = $item->author ?? $item->creator ?? '';

        // The Joomla tags API uses jtag_id, not the FLEXI tag id. Build the map
        // consumed by ItemsSerializer::tags(): jtag id => tag name. It is kept
        // out of the attributes (only 'tag_ids' is exposed there).
        $tagMap          = $this->getLookup('tags');
        $item->flexi_tags = [];

        foreach ($flexiTagIds as $flexiTagId) {
            if (!empty($tagMap[$flexiTagId]['jtag_id'])) {
                $jtagId = (int) $tagMap[$flexiTagId]['jtag_id'];
                $item->flexi_tags[$jtagId] = $tagMap[$flexiTagId]['name'];
            }
        }

        // Avoid duplicate attributes: these are exposed as 'category_ids' and
        // 'tag_ids' plus the 'categories'/'tags' relationships.
        unset($item->tags, $item->categories);

        return $item;
    }

    /**
     * Normalize a list of ids coming from the legacy models.
     *
     * @param   mixed  $values    An array, a comma separated string or null.
     * @param   ?int   $fallback  Value to use when no id was found.
     *
     * @return  array
     */
    private function idList($values, ?int $fallback = null): array
    {
        if (\is_string($values)) {
            $values = $values === '' ? [] : explode(',', $values);
        }

        $ids = [];

        foreach ((array) $values as $value) {
            if (\is_object($value)) {
                $value = $value->id ?? $value->value ?? null;
            }

            if (is_numeric($value) && (int) $value > 0) {
                $ids[] = (int) $value;
            }
        }

        $ids = array_values(array_unique($ids));

        if (!$ids && $fallback !== null && $fallback > 0) {
            $ids[] = $fallback;
        }

        return $ids;
    }

    /**
     * Load a small lookup table once per request.
     *
     * @param   string  $name  One of 'types', 'categories', 'tags'.
     *
     * @return  array
     */
    private function getLookup(string $name): array
    {
        if (isset($this->lookups[$name])) {
            return $this->lookups[$name];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery();

        switch ($name) {
            case 'types':
                $query->select(['id', 'name', 'alias'])
                    ->from('#__flexicontent_types');
                $key = 'id';
                break;

            case 'categories':
                $query->select(['id', 'title', 'alias'])
                    ->from('#__categories');
                $key = 'id';
                break;

            case 'tags':
                $query->select(['id', 'name', 'jtag_id'])
                    ->from('#__flexicontent_tags');
                $key = 'id';
                break;

            default:
                return $this->lookups[$name] = [];
        }

        $rows = $db->setQuery($query)->loadAssocList($key);

        return $this->lookups[$name] = \is_array($rows) ? $rows : [];
    }
}

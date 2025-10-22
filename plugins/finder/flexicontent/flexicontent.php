<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Finder.Flexicontent
 *
 * @copyright   (C) 2011 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Finder\Flexicontent\Extension;

defined('_JEXEC') or die;

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Finder as FinderEvent;
use Joomla\Component\Finder\Administrator\Indexer\Adapter as _FinderIndexerAdapter;
use Joomla\Component\Finder\Administrator\Indexer\Helper as _FinderIndexerHelper;
use Joomla\Component\Finder\Administrator\Indexer\Indexer as _FinderIndexer;
use Joomla\Component\Finder\Administrator\Indexer\Result as _FinderIndexerResult;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseQuery;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Joomla\Database\DatabaseInterface;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

if (version_compare(JVERSION, '4.0', 'ge'))
{
    class_alias(_FinderIndexerAdapter::class, Adapter::class);
    class_alias(_FinderIndexerHelper::class, Helper::class);
    class_alias(_FinderIndexer::class, Indexer::class);
    class_alias(_FinderIndexerResult::class, Result::class);
    abstract class _Flexicontent extends Adapter implements SubscriberInterface {
        use DatabaseAwareTrait;
        protected static bool $isJ4GE = false;
    }
}
else
{
    \JLoader::register('FinderIndexerAdapter', JPATH_ADMINISTRATOR . '/components/com_finder/helpers/indexer/adapter.php');
    \JLoader::register('FinderIndexerHelper', JPATH_ADMINISTRATOR . '/components/com_finder/helpers/indexer/helper.php');
    \JLoader::register('FinderIndexer', JPATH_ADMINISTRATOR . '/components/com_finder/helpers/indexer/indexer.php');
    \JLoader::register('FinderIndexerResult', JPATH_ADMINISTRATOR . '/components/com_finder/helpers/indexer/result.php');

    class_alias(\FinderIndexerAdapter::class, Adapter::class);
    class_alias(\FinderIndexerResult::class, Result::class);
    class_alias(\FinderIndexerHelper::class, Helper::class);
    class_alias(\FinderIndexer::class, Indexer::class);
    abstract class _Flexicontent extends Adapter {
        protected static bool $isJ4GE = false;
    }
}


/**
 * Smart Search adapter for com_flexicontent.
 *
 * @since  2.5
 */
class Flexicontent extends _Flexicontent
{
    /**
     * The plugin identifier.
     *
     * @var    string
     * @since  2.5
     */
    protected $context = 'FLEXIcontent';

    /**
     * The extension name.
     *
     * @var    string
     * @since  2.5
     */
    protected $extension = 'com_flexicontent';

    /**
     * The sublayout to use when rendering the results.
     *
     * @var    string
     * @since  2.5
     */
    protected $layout = 'item';

    /**
     * The type of content that the adapter indexes.
     *
     * @var    string
     * @since  2.5
     */
    protected $type_title = 'Content Item';

    /**
     * The table name.
     *
     * @var    string
     * @since  2.5
     */
    protected $table = '#__content';

    /**
     * Load the language file on instantiation.
     *
     * @var    boolean
     * @since  3.1
     */
    protected $autoloadLanguage = true;

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   5.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return array_merge([
            'onFinderCategoryChangeState' => 'onFinderCategoryChangeState',
            'onFinderChangeState'         => 'onFinderChangeState',
            'onFinderAfterDelete'         => 'onFinderAfterDelete',
            'onFinderBeforeSave'          => 'onFinderBeforeSave',
            'onFinderAfterSave'           => 'onFinderAfterSave',
        ], parent::getSubscribedEvents());
    }

    /**
     * Method to setup the indexer to be run.
     *
     * @return  boolean  True on success.
     *
     * @since   2.5
     */
    protected function setup()
    {
        // Load dependent classes.
        include_once JPATH_SITE . '/components/com_flexicontent/helpers/route.php';

        return true;
    }

    /**
     * Method to update the item link information when the item category is
     * changed. This is fired when the item category is published or unpublished
     * from the list view.
     *
     * @param   string   $extension  The extension whose category has been updated.
     * @param   array    $pks        A list of primary key ids of the content that has changed state.
     * @param   integer  $value      The value of the state that the content has been changed to.
     *
     * @return  void
     *
     * @since   2.5
     */
    public function onFinderCategoryChangeState($extension, $pks, $value)
    {
        // Make sure we're handling com_content categories.
        if ($extension === 'com_content')
        {
            $this->categoryStateChange($pks, $value);
        }
    }

    /**
     * Method to remove the link information for items that have been deleted.
     *
     * @param   string  $context  The context of the action being performed.
     * @param   \Joomla\CMS\Table\Table  $table    A \Joomla\CMS\Table\Table object containing the record to be deleted
     *
     * @return  void
     *
     * @since   2.5
     * @throws  Exception on database error.
     */
    public function onFinderAfterDelete($context, $table)
    {
        if ($context === 'com_content.article') {
            $id = $table->id;
        } elseif ($context === 'com_finder.index') {
            $id = $table->link_id;
        } else {
            return;
        }

        // Remove item from the index.
        $this->remove($id);
    }

    /**
     * Smart Search after save content method.
     * Reindexes the link information for an article that has been saved.
     * It also makes adjustments if the access level of an item or the
     * category to which it belongs has changed.
     *
     * @param   string   $context  The context of the content passed to the plugin.
     * @param   \Joomla\CMS\Table\Table   $row      A \Joomla\CMS\Table\Table object.
     * @param   boolean  $isNew    True if the content has just been created.
     *
     * @return  boolean  True on success.
     *
     * @since   2.5
     * @throws  Exception on database error.
     */
    public function onFinderAfterSave($context, $row = null, $isNew = null)
    {
        $event = $context;
        if ($event instanceof \Joomla\CMS\Event\Model\SaveEvent)
        {
            $context = $event->getContext();
            $row     = $event->getItem();
            $isNew   = $event->getIsNew();
        }

        // We only want to handle articles here.
        if ($context === 'com_content.article' || $context === 'com_flexicontent.form')
        {
            // Check if the access levels are different.
            if (!$isNew && $this->old_access != $row->access)
            {
                // Process the change.
                $this->itemAccessChange($row);
            }

            // Reindex the item.
            $this->reindex($row->id);
        }

        // Check for access changes in the category.
        if ($context === 'com_categories.category')
        {
            // Check if the access levels are different.
            if (!$isNew && $this->old_cataccess != $row->access)
            {
                $this->categoryAccessChange($row);
            }
        }

        return true;
    }

    /**
     * Smart Search before content save method.
     * This event is fired before the data is actually saved.
     *
     * @param   string   $context  The context of the content passed to the plugin.
     * @param   \Joomla\CMS\Table\Table   $row      A \Joomla\CMS\Table\Table object.
     * @param   boolean  $isNew    If the content is just about to be created.
     *
     * @return  boolean  True on success.
     *
     * @since   2.5
     * @throws  Exception on database error.
     */
    public function onFinderBeforeSave($context, $row = null, $isNew = null)
    {
        $event = $context;
        if ($event instanceof \Joomla\CMS\Event\Model\SaveEvent)
        {
            $context = $event->getContext();
            $row     = $event->getItem();
            $isNew   = $event->getIsNew();
        }

        // We only want to handle articles here.
        if ($context === 'com_content.article' || $context === 'com_flexicontent.form') {
            // Query the database for the old access level if the item isn't new.
            if (!$isNew) {
                $this->checkItemAccess($row);
            }
        }

        // Check for access levels from the category.
        if ($context === 'com_categories.category') {
            // Query the database for the old access level if the item isn't new.
            if (!$isNew) {
                $this->checkCategoryAccess($row);
            }
        }

        return true;
    }

    /**
     * Method to update the link information for items that have been changed
     * from outside the edit screen. This is fired when the item is published,
     * unpublished, archived, or unarchived from the list view.
     *
     * @param   string   $context  The context for the content passed to the plugin.
     * @param   array    $pks      An array of primary key ids of the content that has changed state.
     * @param   integer  $value    The value of the state that the content has been changed to.
     *
     * @return  void
     *
     * @since   2.5
     */
    public function onFinderChangeState($context, $pks, $value)
    {
        // We only want to handle articles here.
        if ($context === 'com_content.article' || $context === 'com_flexicontent.form') {
            $this->itemStateChange($pks, $value);
        }

        // Handle when the plugin is disabled.
        if ($context === 'com_plugins.plugin' && $value === 0) {
            $this->pluginDisable($pks);
        }
    }

    /**
     * Method to index an item. The item must be a Result object.
     *
     * @param   Result  $item  The item to index as a Result object.
     *
     * @return  void
     *
     * @since   2.5
     * @throws  \Exception on database error.
     */
    protected function index(Result $item)
    {
        global $globalcats;
        $category = $globalcats[$item->catid] ?? null;

        // Get the item's category information.
        if (!isset($item->category) || !isset($item->cat_state) || !isset($item->cat_access))
        {
            /** @var DatabaseDriver $db */
            $db = version_compare(JVERSION, '4', 'ge') ? Factory::getContainer()->get('DatabaseDriver') : Factory::getContainer()->get(DatabaseInterface::class);
            $category = self::$isJ4GE
                ? $this->getApplication()->bootComponent('com_content')->getCategory(['published' => false, 'access' => false])->get($item->catid)
                : $db->setQuery($db->getQuery(true)
                    ->select('*')
                    ->from('#__categories')
                    ->where('id = ' . (int) $item->catid)
                )->loadObject();
            $item->category   = $item->category ?? $category->title;
            $item->cat_state  = $item->cat_state ?? $category->published;
            $item->cat_access = $item->cat_access ?? $category->access;
        }

        // Type ID is required for the item. Assume 'Article' if not set.
        $item->type_id = $item->type_id ?? 1;

        // Get the item's type information.
        if (!isset($item->type_state))
        {
            /** @var DatabaseDriver $db */
            $db = version_compare(JVERSION, '4', 'ge') ? Factory::getContainer()->get('DatabaseDriver') : Factory::getContainer()->get(DatabaseInterface::class);
            $type = $db->setQuery($db->getQuery(true)
                    ->select('t.*')
                    ->from('#__flexicontent_types AS t')
                    ->innerJoin('#__flexicontent_items_ext AS ie ON ie.type_id = t.id')
                    ->where('ie.item_id = ' . (int) $item->id)
                )->loadObject();
            $item->type_id = $type ? $type->id : 1;
            $item->type_state = $type ? $type->published : 0;
        }

        $item->setLanguage();

        // Check if the extension is enabled.
        if (ComponentHelper::isEnabled($this->extension) === false) {
            return;
        }

        $item->context = 'com_content.article';

        // Initialise the item parameters.
        $registry     = new Registry($item->params);
        $item->params = ComponentHelper::getParams('com_flexicontent', true);
        $item->params->merge($registry);

        $item->metadata = new Registry($item->metadata);

        // Trigger the onContentPrepare event.
        $item->summary = Helper::prepareContent($item->summary, $item->params, $item);
        $item->body    = Helper::prepareContent($item->body, $item->params, $item);

        // Create a URL as identifier to recognise items again.
        $item->url = $this->getUrl($item->id, $this->extension, $this->layout, $item->catid);

        // Build the necessary route and path information.
        $item->route = \FlexicontentHelperRoute::getItemRoute($item->slug, $item->catslug, 0, $item);
        if (!static::$isJ4GE && method_exists(Helper::class, 'getContentPath'))
        {
            $item->path = Helper::getContentPath($item->route);
        }

        // Get the menu title if it exists.
        $title = $this->getItemMenuTitle($item->url);

        // Adjust the title if necessary.
        if (!empty($title) && $this->params->get('use_menu_title', true)) {
            $item->title = $title;
        }

        // Add the meta author.
        $item->metaauthor = $item->metadata->get('author');

        // Add the metadata processing instructions.
        $item->addInstruction(Indexer::META_CONTEXT, 'metakey');
        $item->addInstruction(Indexer::META_CONTEXT, 'metadesc');
        $item->addInstruction(Indexer::META_CONTEXT, 'metaauthor');
        $item->addInstruction(Indexer::META_CONTEXT, 'author');
        $item->addInstruction(Indexer::META_CONTEXT, 'created_by_alias');

        // Translate the state. Articles should only be published if the category is published.
        $item->state = $this->translateState($item->state, $item->cat_state, $item->type_state);

        // Get taxonomies to display
        $taxonomies = $this->params->get('taxonomies', ['type', 'author', 'category', 'language']);

        // Add the type taxonomy data.
        $item->addTaxonomy('Type', 'Item');  // TODO MORE ! this must be the item's content TYPE !!

        // Add the author taxonomy data.
        if (\in_array('author', $taxonomies) && (!empty($item->author) || !empty($item->created_by_alias))) {
            $item->addTaxonomy('Author', !empty($item->created_by_alias) ? $item->created_by_alias : $item->author, $item->state);
        }

        // Add the category taxonomy data.
        if ($category && \in_array('category', $taxonomies)) {
            if (static::$isJ4GE) {
                $item->addNestedTaxonomy('Category', $category, $this->translateState($category->published), $category->access, $category->language);
            } else {
                // Add the category taxonomy data.
                $item->addTaxonomy('Category', $item->category, $this->translateState($item->cat_state), $item->cat_access);
            }
        }

        // Add the language taxonomy data.
        if (\in_array('language', $taxonomies)) {
            $item->addTaxonomy('Language', $item->language);
        }

        // Get content extras.
        Helper::getContentExtras($item);
        if (static::$isJ4GE) Helper::addCustomFields($item, 'com_content.article');

        // Index the item.
        $this->indexer->index($item);
    }

    /**
     * Method to get the SQL query used to retrieve the list of content items.
     *
     * @param   mixed  $query  A DatabaseQuery object or null.
     *
     * @return  DatabaseQuery  A database object.
     *
     * @since   2.5
     */
    protected function getListQuery($query = null)
    {
        /** @var DatabaseDriver $db */
        $db = version_compare(JVERSION, '4', 'ge') ? Factory::getContainer()->get('DatabaseDriver') : Factory::getContainer()->get(DatabaseInterface::class);

        // Check if we can use the supplied SQL query.
        /** @var DatabaseQuery $query */
        $query = $query instanceof DatabaseQuery ? $query : $db->getQuery(true)
            ->select('a.id, a.title, a.alias, a.introtext AS summary, a.fulltext AS body')
            ->select('a.images')
            ->select('a.state, a.catid, a.created AS start_date, a.created_by')
            ->select('a.created_by_alias, a.modified, a.modified_by, a.attribs AS params')
            ->select('a.metakey, a.metadesc, a.metadata, a.language, a.access, a.version, a.ordering')
            ->select('a.publish_up AS publish_start_date, a.publish_down AS publish_end_date')
            ->select('c.title AS category, c.published AS cat_state, c.access AS cat_access')

            ->select('u.name AS author')
            ->from('#__content AS a')
            ->join('LEFT', '#__categories AS c ON c.id = a.catid')
            ->join('LEFT', '#__users AS u ON u.id = a.created_by');

        // Handle the alias CASE WHEN portion of the query
        $case_when_item_alias = ' CASE WHEN ';
        $case_when_item_alias .= $query->charLength('a.alias', '!=', '0');
        $case_when_item_alias .= ' THEN ';
        $a_id = $query->castAsChar('a.id');
        $case_when_item_alias .= $query->concatenate([$a_id, 'a.alias'], ':');
        $case_when_item_alias .= ' ELSE ';
        $case_when_item_alias .= $a_id . ' END as slug';
        $query->select($case_when_item_alias);

        $case_when_category_alias = ' CASE WHEN ';
        $case_when_category_alias .= $query->charLength('c.alias', '!=', '0');
        $case_when_category_alias .= ' THEN ';
        $c_id = $query->castAsChar('c.id');
        $case_when_category_alias .= $query->concatenate([$c_id, 'c.alias'], ':');
        $case_when_category_alias .= ' ELSE ';
        $case_when_category_alias .= $c_id . ' END as catslug';

        $query->select($case_when_category_alias);

        return $query;
    }


    // override ...
    protected function getUrl($id, $extension, $view, $cid=0)
    {
        return 'index.php?option=' . $extension . '&view=' . $view . '&id=' . $id . ($cid ? '&cid=' . $cid : '');
    }


    // override ...
    protected function translateState($item, $category = null, $type = null)
    {
        // If category is present, factor in its states as well
        if ($category !== null && $category == 0) $item = 0;

        // If type is present, factor in its states as well
        if ($type !== null && $type == 0) $item = 0;

        // Translate the state
        switch ($item)
        {
            // Published and archived items only should return a published state
            case 1:  // Published
            case -5:  // In Progress - Published
                return 1;
            case 2:  // Archived
                // Archived items should only show up when option is enabled
                if ($this->params->get('search_archived', 1) == 0) {
                    return 0;
                }
                return 1;

            // All other states should return a unpublished state
            default:
            case 0:
                return 0;
        }
    }


    // override ...
    protected function getStateQuery()
    {
        $sql = $this->db->getQuery(true);
        // Item ID
        $sql->select('a.id');
        // Item, category, type published state
        $sql->select('a.' . $this->state_field . ' AS state, c.published AS cat_state, t.published AS type_state');
        // Item, category, type access levels
        $sql->select('a.access, c.access AS cat_access, t.access AS type_access');
        $sql->from($this->table . ' AS a');
        $sql->join('LEFT', '#__categories AS c ON c.id = a.catid');
        $sql->join('LEFT', '#__flexicontent_items_ext AS ie ON ie.item_id = a.id');
        $sql->join('LEFT', '#__flexicontent_types AS t ON t.id = ie.type_id');

        return $sql;
    }


    // override ...
    protected function itemAccessChange($row)
    {
        $sql = clone($this->getStateQuery());
        $sql->where('a.id = ' . (int) $row->id);

        // Get the access level.
        $this->db->setQuery($sql);
        $item = $this->db->loadObject();

        // Set the access level.
        $temp = max($row->access, $item->cat_access, $item->type_access);

        // Update the item.
        $this->change((int) $row->id, 'access', $temp);
    }

}

// J3.x compatibility
if (version_compare(JVERSION, '4.0', 'lt'))
{
    class_alias(Flexicontent::class, 'PlgFinderFlexicontent');
}

// J4.x/J5.x compatibility
//else {}

<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Finder.FLEXIcontent
 *
 * @copyright   Copyright (C) 2005 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\Registry\Registry;

JLoader::register('FinderIndexerAdapter', JPATH_ADMINISTRATOR . '/components/com_finder/helpers/indexer/adapter.php');

/**
 * Smart Search adapter for com_flexicontent.
 *
 * @since  2.5
 */
class plgFinderFLEXIcontent extends FinderIndexerAdapter
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
	 * @param   JTable  $table    A JTable object containing the record to be deleted
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   2.5
	 * @throws  Exception on database error.
	 */
	public function onFinderAfterDelete($context, $table)
	{
		if ($context === 'com_content.article')
		{
			$id = $table->id;
		}
		elseif ($context === 'com_finder.index')
		{
			$id = $table->link_id;
		}
		else
		{
			return true;
		}

		// Remove item from the index.
		return $this->remove($id);
	}

	/**
	 * Smart Search after save content method.
	 * Reindexes the link information for an article that has been saved.
	 * It also makes adjustments if the access level of an item or the
	 * category to which it belongs has changed.
	 *
	 * @param   string   $context  The context of the content passed to the plugin.
	 * @param   JTable   $row      A JTable object.
	 * @param   boolean  $isNew    True if the content has just been created.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   2.5
	 * @throws  Exception on database error.
	 */
	public function onFinderAfterSave($context, $row, $isNew)
	{
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
	 * @param   JTable   $row      A JTable object.
	 * @param   boolean  $isNew    If the content is just about to be created.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   2.5
	 * @throws  Exception on database error.
	 */
	public function onFinderBeforeSave($context, $row, $isNew)
	{
		// We only want to handle articles here.
		if ($context === 'com_content.article' || $context === 'com_flexicontent.form')
		{
			// Query the database for the old access level if the item isn't new.
			if (!$isNew)
			{
				$this->checkItemAccess($row);
			}
		}

		// Check for access levels from the category.
		if ($context === 'com_categories.category')
		{
			// Query the database for the old access level if the item isn't new.
			if (!$isNew)
			{
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
		if ($context === 'com_content.article' || $context === 'com_flexicontent.form')
		{
			$this->itemStateChange($pks, $value);
		}

		// Handle when the plugin is disabled.
		if ($context === 'com_plugins.plugin' && $value === 0)
		{
			$this->pluginDisable($pks);
		}
	}

	/**
	 * Method to index an item. The item must be a FinderIndexerResult object.
	 *
	 * @param   FinderIndexerResult  $item    The item to index as a FinderIndexerResult object.
	 * @param   string               $format  The item format.  Not used.
	 *
	 * @return  void
	 *
	 * @since   2.5
	 * @throws  Exception on database error.
	 */
	protected function index(FinderIndexerResult $item, $format = 'html')
	{
		$item->setLanguage();

		// Check if the extension is enabled.
		if (JComponentHelper::isEnabled($this->extension) === false)
		{
			return;
		}

		$item->context = 'com_content.article';

		// Initialise the item parameters.
		$registry = new Registry($item->params);
		$item->params = JComponentHelper::getParams('com_flexicontent', true);
		$item->params->merge($registry);

		$item->metadata = new Registry($item->metadata);

		// Trigger the onContentPrepare event.
		$item->summary = FinderIndexerHelper::prepareContent($item->summary, $item->params, $item);
		$item->body    = FinderIndexerHelper::prepareContent($item->body, $item->params, $item);

		// Create a URL as identifier to recognise items again.
		$item->url = $this->getUrl($item->id, $this->extension, $this->layout, $item->catid);

		// Build the necessary route and path information.
		$item->route = FlexicontentHelperRoute::getItemRoute($item->slug, $item->catslug, 0, $item);
		if (!FLEXI_J40GE)
		{
			$item->path = FinderIndexerHelper::getContentPath($item->route);
		}

		// Get the menu title if it exists.
		$title = $this->getItemMenuTitle($item->url);

		// Adjust the title if necessary.
		if (!empty($title) && $this->params->get('use_menu_title', true))
		{
			$item->title = $title;
		}

		// Add the meta author.
		$item->metaauthor = $item->metadata->get('author');

		// Add the metadata processing instructions.
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'metakey');
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'metadesc');
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'metaauthor');
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'author');
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'created_by_alias');

		// Translate the state. Articles should only be published if the category is published.
		$item->state = $this->translateState($item->state, $item->cat_state, $item->type_state);

		// Add the type taxonomy data.
		$item->addTaxonomy('Type', 'Item');  // TODO MORE ! this must be the item's content TYPE !!

		// Add the author taxonomy data.
		if (!empty($item->author) || !empty($item->created_by_alias))
		{
			$item->addTaxonomy('Author', !empty($item->created_by_alias) ? $item->created_by_alias : $item->author);
		}

		// Add the category taxonomy data.
		if (FLEXI_J40GE)
		{
			global $globalcats;
			if ($globalcats)
			{
				if (!isset($globalcats[$item->catid]))
				{
					return;
				}
			}
		}
		$item->addTaxonomy('Category', $item->category, $item->cat_state, $item->cat_access);

		// Add the language taxonomy data.
		$item->addTaxonomy('Language', $item->language);

		// Get content extras.
		FinderIndexerHelper::getContentExtras($item);

		// Index the item.
		$this->indexer->index($item);
	}

	/**
	 * Method to get the SQL query used to retrieve the list of content items.
	 *
	 * @param   mixed  $query  A JDatabaseQuery object or null.
	 *
	 * @return  JDatabaseQuery  A database object.
	 *
	 * @since   2.5
	 */
	protected function getListQuery($query = null)
	{
		$db = JFactory::getDbo();

		// Check if we can use the supplied SQL query.
		$query = $query instanceof JDatabaseQuery ? $query : $db->getQuery(true)
			->select('a.id, a.title, a.alias, a.introtext AS summary, a.fulltext AS body')
			->select('a.state, a.catid, a.created AS start_date, a.created_by')
			->select('a.created_by_alias, a.modified, a.modified_by, a.attribs AS params')
			->select('a.metakey, a.metadesc, a.metadata, a.language, a.access, a.version, a.ordering')
			->select('a.publish_up AS publish_start_date, a.publish_down AS publish_end_date')
			->select('c.title AS category, c.published AS cat_state, c.access AS cat_access');

		// Handle the alias CASE WHEN portion of the query
		$case_when_item_alias = ' CASE WHEN ';
		$case_when_item_alias .= $query->charLength('a.alias', '!=', '0');
		$case_when_item_alias .= ' THEN ';
		$a_id = $query->castAsChar('a.id');
		$case_when_item_alias .= $query->concatenate(array($a_id, 'a.alias'), ':');
		$case_when_item_alias .= ' ELSE ';
		$case_when_item_alias .= $a_id . ' END as slug';
		$query->select($case_when_item_alias);

		$case_when_category_alias = ' CASE WHEN ';
		$case_when_category_alias .= $query->charLength('c.alias', '!=', '0');
		$case_when_category_alias .= ' THEN ';
		$c_id = $query->castAsChar('c.id');
		$case_when_category_alias .= $query->concatenate(array($c_id, 'c.alias'), ':');
		$case_when_category_alias .= ' ELSE ';
		$case_when_category_alias .= $c_id . ' END as catslug';
		$query->select($case_when_category_alias)

			->select('u.name AS author')
			->from('#__content AS a')
			->join('LEFT', '#__categories AS c ON c.id = a.catid')
			->join('LEFT', '#__users AS u ON u.id = a.created_by');

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
			case 2:  // Archived
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

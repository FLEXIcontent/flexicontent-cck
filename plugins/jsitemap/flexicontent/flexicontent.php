<?php
// namespace administrator\components\com_jmap\plugins;
/**
 * @package JMAP::EXTERNALPLUGINS::administrator::components::com_jmap
 * @subpackage plugins
 * @author Lyquix
 * @copyright (C) 2016 - Lyquix
 * @license GNU/GPLv2 http://www.gnu.org/licenses/gpl-2.0.html
 */
defined ( '_JEXEC' ) or die ( 'Restricted access' );

/**
 * External plugin data source
 * It's the concrete implementation of the interface JMapFilePlugin
 * that retrieves data in an arbitrary way or resource and returns them
 * following a specific format to render the sitemap in every format supported HTML, XML, etc
 *
 * @package JMAP::FRAMEWORK::components::com_jmap
 * @subpackage plugins
 * @since 3.3
 */
class JMapFilePluginFLEXIcontent implements JMapFilePlugin {
	/**
	 * Retrieves records for the plugin data source using whatever way and resource is required
	 * Formats and returns an associative array of data based on the following scheme  
	 *
	 * @param JRegistry The object holding configuration parameters for the plugin and data source
	 * @param JDatabase $db The database connector object
	 * @param JMapModel $sitemapModel The sitemap model object reference, it's needed to manage limitStart, limitRows properties and affected_rows state
	 *        	
	 * @return array
	 * This function must return an associative array as following:
	 * $returndata['items'] -> It's the mandatory objects array of elements, it must contain at least title and routed link fields
	 * $returndata['items_tree'] -> Needed to render elements grouped by cats with a nested tree, not mandatory
	 * $returndata['categories_tree'] -> Needed to render elements grouped by cats with a nested tree, not mandatory
	 * 
	 * $returndata['items'] must contain records objects with following properties (* = required)
	 * 						->title * A string for the title
	 * 						->link * A string for the link
	 * 						->lastmod (used for XML sitemap) A date string in MySql format yyyy-mm-dd hh:ii:ss
	 * 						->metakey (used for Google news sitemap) A string for metakeys of each record
	 * 						->publish_up (used for Google news sitemap) A date string in MySql format yyyy-mm-dd hh:ii:ss
	 * 						->access (used for Google news sitemap, >1 = registration access) An integer for Joomla! access level of each record
	 * 
	 * $returndata['items_tree'] must be a numerical array that groups items by the containing category id, the index of the array is the category id 
	 * 
	 * $returndata['categories_tree'] must be a numerical array that groups categories by parent category, the index of the array is the category parent id,
	 * 								  the elements of the array must be records objects representing categories with following properties (* = required)
	 * 						->category_id * An integer for the category ID
	 * 						->category_title * A string for the category title
	 * 						->category_link * A string for the category link
	 * 						->lastmod (used for XML sitemap) A date string in MySql format yyyy-mm-dd hh:ii:ss
	 */
	public function getSourceData(JRegistry $pluginParams, JDatabase $db, JMapModel $sitemapModel) {

		// Check if the extension is installed
		if(!file_exists(JPATH_SITE . '/components/com_flexicontent')) {
			throw new JMapException(JText::sprintf('COM_JMAP_ERROR_EXTENSION_NOTINSTALLED', 'FLEXIcontent'), 'warning');
		}
		
		// The associative array holding the returned data
		$returndata = array();
		
		// Get user
		$user = JFactory::getUser();
		if(!is_object($user)) {
			throw new JMapException(JText::_('COM_JMAP_PLGFLEXICONTENT_NOUSER_OBJECT'), 'warning');
		}

		// Get access level and language
		$accessLevel = $user->getAuthorisedViewLevels();
		$langTag = JFactory::getLanguage()->getTag();
		
		// Category scope
		$catScope = $pluginParams->get('cats_scope', 1);
		$catScopeQuery = null;
		if($catScope) {
			// Exclude categories
			$cats = $pluginParams->get('cats');
			if(is_array($cats)) $catScopeQuery = "\n AND #__categories.id NOT IN ( " . implode(',', $cats) . " )";
		}
		else {
			// Include categories
			$cats = $pluginParams->get('cats');
			if(is_array($cats)) $catScopeQuery = "\n AND #__categories.id IN ( " . implode(',', $cats) . " )";
		}

		// ACL
		$aclQueryItems = "\n AND #__content.access IN ( " . implode(',', $accessLevel) . " )";
		$aclQueryCategories = "\n AND #__categories.access IN ( " . implode(',', $accessLevel) . " )";
		
		// Retrieve records
		$itemsQuery = "SELECT" .
					  "\n #__content.id," .
					  "\n #__content.alias," .
					  "\n #__content.title," .
					  "\n #__content.catid," .
					  "\n #__content.modified AS " . $db->quoteName('lastmod') . "," .
					  "\n #__content.publish_up," .
					  "\n #__content.metakey" .
					  "\n FROM " . $db->quoteName('#__content') .
					  // Main category only (#__content.catid), do not use the item-category relations table: it also holds the secondary categories,
					  // which would output the same item several times (once per assigned category)
					  "\n JOIN " . $db->quoteName('#__categories') . " ON #__content.catid = #__categories.id" .
					  "\n WHERE" .
					  "\n #__categories.published = 1" .
					  "\n AND #__content.state = 1" .
					  $aclQueryItems .
					  $aclQueryCategories .
					  $catScopeQuery . 
					  "\n AND (#__content.language = '*' OR #__content.language = '' OR #__content.language = " . $db->quote($langTag) . ")" .
					  //"\n AND #__content.trash = 0" .
					  "\n AND (#__content.publish_down > NOW() OR #__content.publish_down = '0000-00-00 00:00:00' OR #__content.publish_down = '1000-01-01 00:00:00' OR ISNULL(" .  $db->quoteName('#__content.publish_down') . "))" .
					  "\n ORDER BY" .
					  "\n #__categories.title ASC," .
					  "\n #__content.title ASC";
		
		// Check if a limit for query rows has been set, this means we are in precaching process by JS App client
		if(!$sitemapModel->limitRows) {
			$items = $db->setQuery($itemsQuery)->loadObjectList();
		} else {
			$items = $db->setQuery($itemsQuery, $sitemapModel->limitStart, $sitemapModel->limitRows)->loadObjectList();
		}
		
		if(method_exists($db, 'getErrorNum')) {
			if ($db->getErrorNum ()) {
				throw new JMapException(JText::sprintf('COM_JMAP_ERROR_RETRIEVING_DATA_FROM_PLUGIN_DATASOURCE', $db->getErrorMsg()), 'warning');
			}
		}
		
		// Detected a precaching call, we have to store in the model state the number of affected rows for the JS application
		if($sitemapModel->limitRows) {
			$sitemapModel->setState('affected_rows', count($items));
		}
		
		// Include the extension route helper
		if(file_exists(JPATH_SITE . '/components/com_flexicontent/helpers/route.php')) {
			include_once (JPATH_SITE . '/components/com_flexicontent/helpers/route.php');
		}

		
		// Route links for each record
		if(count($items)) {
			$itemsByCats = array();
			foreach ($items as $item) {
				$item->link = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->id . ':' . $item->alias, $item->catid));
				// First pass organize items by containing cats
				$itemsByCats[$item->catid][] = $item;
			}
			// Sort by URL
			usort($items, function($a, $b) {
			    return strcmp($a->link, $b->link);
			});
			$returndata['items'] = $items; // Assign items
			$returndata['items_tree'] = $itemsByCats; // Assign items grouped by category
		}
		
		// Build the categories tree
		$catsQuery = $db->getQuery(true)
						->select('id AS ' . $db->qn('category_id'))
						->select('alias AS ' . $db->qn('category_alias'))
						->select('title AS ' . $db->qn('category_title'))
						->select('modified_time AS ' . $db->qn('lastmod'))
						->from($db->qn('#__categories'))
						->where('extension = ' . $db->q('com_content'))
						->where('published = 1')
						->where('language in (' . $db->q($langTag) . ',' . $db->q('*') . ')');
		
		$totalItemsCats = $db->setQuery($catsQuery)->loadObjectList();
		if (method_exists ( $db, 'getErrorNum' )) {
			if ($db->getErrorNum ()) {
				throw new JMapException ( JText::sprintf ( 'COM_JMAP_ERROR_RETRIEVING_DATA_FROM_PLUGIN_DATASOURCE', $db->getErrorMsg () ), 'warning' );
			}
		}
		
		$catsTreeQuery = $db->getQuery(true)
							->select('parent_id AS ' . $db->qn('parent'))
							->select('id AS ' . $db->qn('child'))
							->where('extension = ' . $db->q('com_content'))
							->from($db->qn('#__categories'));
		
		$totalItemsCatsTree = $db->setQuery($catsTreeQuery)->loadAssocList('child');
		
		if(method_exists($db, 'getErrorNum')) {
			if ($db->getErrorNum ())
			{
				throw new JMapException(JText::sprintf('COM_JMAP_ERROR_RETRIEVING_DATA_FROM_PLUGIN_DATASOURCE', $db->getErrorMsg()), 'warning');
			}
		}
		
		// Second pass organize the categories tree by parent -> children
		$catsTree = array();
		if (count ( $totalItemsCats )) {
			// Check if categories must be linkable
			$linkableCats = $pluginParams->get('linkable_content_cats', 0);
			foreach ( $totalItemsCats as &$childCat ) {
				if($linkableCats) {
					$childCat->category_link = JRoute::_ (FlexicontentHelperRoute::getCategoryRoute($childCat->category_id . ':' . $childCat->category_alias));
				}
				$parent = $totalItemsCatsTree [$childCat->category_id] ['parent'];
				$parentCat = ($parent == 1) ? 0 : $parent;
				$catsTree[$parentCat][] = $childCat;
			}
		}
		
		$returndata['categories_tree'] = $catsTree; // Assign the categories tree
		
		return $returndata;
	}
}
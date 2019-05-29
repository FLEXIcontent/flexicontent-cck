<?php

/**
 * @package   	JCE
 * @copyright 	Copyright (C) 2014 FLEXIcontent project. All rights reserved.
 * @license   	GNU/GPL 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * @author     	Emmanuel Dannan, Ryan Demmer, ggppdk
 * 
 * Flexicontentlinks is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 *
 * Based on "joomlalinks" found in JCE's core distribution, and flexicontentlinks by Emmanuel Dannan and Ryan Demmer
 */
defined('_WF_EXT') or die('RESTRICTED');

class FlexicontentlinksItems extends JObject {

	var $_option = 'com_flexicontent_items';

	/**
	* Constructor activating the default information of the class
	*
	* @access	protected
	*/
	public function __construct($options = array())
	{
	}


	/**
	 * Returns a reference to a editor object
	 *
	 * This method must be invoked as:
	 * 		<pre>  $browser =JContentEditor::getInstance();</pre>
	 *
	 * @access	public
	 * @return	JCE  The editor object.
	 * @since	1.5
	 */
	public static function getInstance()
	{
		static $instance;

		if (!is_object($instance)) {
			$instance = new FlexicontentlinksItems();
		}
		return $instance;
	}


	public function getOption()
	{
		return $this->_option;
	}


	public function getList()
	{
		$wf = WFEditorPlugin::getInstance();

		if ($wf->checkAccess('links.joomlalinks.content', 1)) {
			return '
			<li id="index.php?option=com_flexicontent_items&view=category" class="folder fccats nolink">
				<div class="uk-tree-row">
					<a href="javascript:;">
						<span class="uk-tree-icon"></span>
						<span class="uk-tree-text">' . JText::_('FLEXI_EDITOR_LINKS_ITEMS_BY_CAT_N_SINGLE_ITEMS') . '</span>
					</a>
				</div>
			</li>';
		}
	}


	public function getLinks($args)
	{
		$wf = WFEditorPlugin::getInstance();

		require_once(JPATH_SITE . DIRECTORY_SEPARATOR. 'components' .DIRECTORY_SEPARATOR. 'com_flexicontent' .DIRECTORY_SEPARATOR. 'helpers' .DIRECTORY_SEPARATOR. 'route.php');

		$items = array();
		$view = isset($args->view) ? $args->view : '';

		// Get category ID. Note: 1 is the top level category
		$categoryId = isset($args->cid ) ? (int) $args->cid : 1;
		$categoryId = $categoryId ?: 1;

		if ($view && $view!='category') return array();

		$db = JFactory::getDbo();

		// Add categories
		$query	= $db->getQuery(true);
		$query->clear();
		$query
			->select('id, title, lft')
			->select('CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', id, alias) ELSE id END as slug')
			->from('#__categories')
			->where('published = 1 AND extension = \'com_content\' AND parent_id = '.$categoryId)
			->order('lft ASC');
		$db->setQuery($query);
		$categories = $db->loadObjectList();

		foreach ($categories as $category)
		{
			$url = FlexicontentHelperRoute::getCategoryRoute($category->slug);
			$items[] = array(
				'id'    => 'index.php?option=com_flexicontent_items&view=category&cid='.$category->id,
				'url'   => self::route($url),
				'name'	=> $category->title,
				'class'	=> 'folder flexicat'
			);
		}

		// Add items
		$query->clear();
		$query
			->select(' i.id as id, i.title AS ititle, c.id as catid, i.access, i.language,'
				. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug')
			->from('#__content AS i')
			->join('LEFT', '#__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id')
			->join('LEFT', '#__categories AS c ON c.id = rel.catid')
			->where('c.id = ' . $categoryId)
			->order('i.title ASC');

		$db->setQuery($query);
		$contents = $db->loadObjectList();

		foreach ($contents as $content)
		{
			$url = FlexicontentHelperRoute::getItemRoute($content->slug, $content->catslug, 0, $content);
			$items[] = array(
				'id'	=>  'index.php?option=com_flexicontent&view=item&cid='.$content->catid.'&id='.$content->id,
				'url'	=>	self::route($url),
				'name'	=>	$content->ititle.'(id='.$content->id.')',
				'class'	=>	'file flexiitem'
			);
		}

		return $items;
	}


	private static function route($url)
	{
		$wf = WFEditorPlugin::getInstance();

		if ($wf->getParam('links.joomlalinks.sef_url', 0)) {
			$url = WFLinkExtension::route($url);
		}

		return $url;
	}
}
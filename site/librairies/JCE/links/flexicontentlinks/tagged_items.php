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

class FlexicontentlinksTagged_items extends JObject {

	var $_option = 'com_flexicontent_tagged_items';

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

		if (!is_object($instance))
		{
			$instance = new FlexicontentlinksTagged_items();
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
			<li id="index.php?option=com_flexicontent_tagged_items&view=category&layout=tags" class="folder fctagged nolink">
				<div class="uk-tree-row">
					<a href="javascript:;">
						<span class="uk-tree-icon"></span>
						<span class="uk-tree-text">' . JText::_('FLEXI_EDITOR_LINKS_ITEMS_BY_TAG') . '</span>
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
		$layout = isset($args->layout) ? $args->layout : '';

		if ($layout && $layout!='tags') return array();

		$db = JFactory::getDbo();

		// Add tagged items
		$query	= $db->getQuery(true);
		$query->clear();
		$query
			->select('t.name, t.id')
			->select('CASE WHEN CHAR_LENGTH(t.alias) THEN CONCAT_WS(\':\', t.id, t.alias) ELSE t.id END as slug')
			->from('#__flexicontent_tags AS t')
			->where('t.published = 1')
			->order('t.name ASC');
		$db->setQuery($query);
		$tags = $db->loadObjectList();

		foreach ($tags as $tag)
		{
			$url = FlexicontentHelperRoute::getCategoryRoute('', 0, array('layout'=>'tags', 'tagid'=>$tag->id));
			$items[] = array(
				'id'    => 'index.php?option=com_flexicontent_tagged_items&view=category&layout=tags&tagid='.$tag->id,
				'url'   => self::route($url),
				'name'	=> $tag->name,
				'class'	=> 'file flexitagged'
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

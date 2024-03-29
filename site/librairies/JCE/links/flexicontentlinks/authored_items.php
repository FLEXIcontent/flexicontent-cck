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

class FlexicontentlinksAuthored_items extends \Joomla\CMS\Object\CMSObject {

	var $_option = 'com_flexicontent_authored_items';

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
			$instance = new FlexicontentlinksAuthored_items();
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

		if ($wf->checkAccess('links.joomlalinks.content', 1))
		{
			return '
			<li id="index.php?option=com_flexicontent_authored_items&view=category&layout=author" class="folder fcauthors nolink">
				<div class="uk-tree-row">
					<a href="javascript:;">
						<span class="uk-tree-icon"></span>
						<span class="uk-tree-text">' . \Joomla\CMS\Language\Text::_('FLEXI_EDITOR_LINKS_ITEMS_BY_AUTHOR') . '</span>
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

		if ($layout && $layout!='author') return array();

		$db = \Joomla\CMS\Factory::getDbo();

		// Add authored items
		$query	= $db->getQuery(true);
		$query->clear();
		$query
			->select('id, name, username')
			->from('#__users')
			->where('block = 0')
			->order('name ASC');
		$db->setQuery($query);
		$users = $db->loadObjectList();

		foreach ($users as $user)
		{
			$url = FlexicontentHelperRoute::getCategoryRoute('', 0, array('layout'=>'author', 'authorid'=>$user->id));
			$items[] = array(
				'id'    => 'index.php?option=com_flexicontent_authored_items&view=category&layout=author&authorid='.$user->id,
				'url'   => self::route($url),
				'name'	=> $user->name . ' [' . $user->username . ']',
				'class'	=> 'file flexiauthor'
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
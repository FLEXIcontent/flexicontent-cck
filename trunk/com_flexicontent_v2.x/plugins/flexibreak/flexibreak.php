<?php
/**
* @version		$Id: flexibreak.php 1 2010-05-03 14:10:00Z flowman $
* @package		FLEXIBreak Content Plugin
* @copyright	Copyright (C) 2005 - 2010 Otherland. All rights reserved.
* @license		GNU/GPL, see LICENSE.php
* Joomla! is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
*/

// No direct access.
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.plugin.plugin');
jimport('joomla.html.pagination');

/**
 * Page break plugin
 *
 * <b>Usage:</b>
 * <code><hr class="system-pagebreak" /></code>
 * <code><hr class="system-pagebreak" title="The page title" /></code>
 * or
 * <code><hr class="system-pagebreak" alt="The first page" /></code>
 * or
 * <code><hr class="system-pagebreak" title="The page title" alt="The first page" /></code>
 * or
 * <code><hr class="system-pagebreak" alt="The first page" title="The page title" /></code>
 *
 * @package		Joomla.Plugin
 * @subpackage	Content.pagebreak
 * @since		1.6
 */
class plgContentFlexiBreak extends JPlugin
{
	var $pattern = '#(<hr[^>]*?class=[\"|\']system-pagebreak[\"|\'][^(>|/>)]*?/?>)#iU';
	var $pluginPath;

	function plgContentFlexiBreak(&$subject, $params)
	{
		parent::__construct($subject, $params);
		$this->pluginPath = dirname(__FILE__).DS.$this->_name;
		JPlugin::loadLanguage('plg_'.$this->_type.'_'.$this->_name, JPATH_ADMINISTRATOR);
	}

	/**
	 * @param	string	The context of the content being passed to the plugin.
	 * @param	object	The article object.  Note $article->text is also available
	 * @param	object	The article params
	 * @param	int		The 'page' number
	 *
	 * @return	void
	 * @since	1.6
	 */
	public function onContentPrepare($context, &$row, &$params, $page = 0)
	{
		//simple performance check to determine whether bot should process further
		if (strpos($row->text, 'class="system-pagebreak') === false && strpos($row->text, 'class=\'system-pagebreak') === false)
			return true;
		
		if ( empty($row->id) && empty($row->slug) ) return;  // modify nothing

		// Remove page markers when article in popup (printing)
		$print = JRequest::getBool('pop');
		if ($print) {
			$row->text = preg_replace( $this->pattern, '', $row->text );
			return;
		}
		
		// Find all instances of plugin
		$pagescount = preg_match_all( $this->pattern, $row->text, $pages, PREG_SET_ORDER );
		$texts		= preg_split( $this->pattern, $row->text );
		$textscount	= count($texts);
		
		$display_method = $this->params->get('display_method', 1);
		
		$row->slug = @ $row->slug ? $row->slug : $row->id;
		$limitstart = JRequest::getInt('limitstart', 0);
		$type_id = isset($row->type_id) ? $row->type_id : 0;
		$prev_link = $limitstart > 0 ?
			JRoute::_(FlexicontentHelperRoute::getItemRoute($row->slug, $row->catid).'&showall=&limitstart='. ($limitstart-1)) :
			'';
		$next_link = $limitstart < $textscount - 1 ?
			JRoute::_(FlexicontentHelperRoute::getItemRoute($row->slug, $row->catid).'&showall=&limitstart='. ($limitstart+1)) :
			'';
		
		$this->assignRef('pages', $pages);
		$this->assignRef('pagescount', $pagescount);
		$this->assignRef('texts', $texts);
		$this->assignRef('textscount', $textscount);
		$this->assignRef('limitstart', $limitstart);
		$this->assignRef('row', $row);
		$this->assignRef('prev_link', $prev_link);
		$this->assignRef('next_link', $next_link);

		// If there are no breaks then don't alter the article
		if ($this->textscount <= 1)
			return;
			
		$document	= JFactory::getDocument();
		$base = JURI::root(true).'/plugins/'.$this->_type.'/'.$this->_name.'/'.$this->_name.(FLEXI_J16GE ? '/'.$this->_name : '');
		$pagenav = $this->params->get('pagination', 3);
		
		// Add Javascript instant navigation
		if ($display_method == 1)
		{
			JHTML::_('behavior.mootools');
			if ($this->params->get('plugin_css', 1))
				$document->addScript($base.'.js');
		}
		
		// Add CSS rules file
		$document->addStyleSheet($base.'.css');
		
		// Clear article's text
		$row->text = '';
		
		// Page navigation at article's start
		if (2 == $pagenav && $display_method != 0) $row->text .= $this->loadTemplate('pagination_js');
		
		// Table of Contents
		$row->text .= $this->loadTemplate('default_js');
		
		// Concatenated text of all pages encapsulated in containers (or e.g. having anchors)
		$row->text .= $this->_text;
		
		// Page navigation at article's end
		if (3 == $pagenav && $display_method != 0) $row->text .= $this->loadTemplate('pagination_js');
	}

	function _generateToc( &$row, $index )
	{
		$display_method = $this->params->get('display_method', 1);
		$limitstart = JRequest::getInt('limitstart', 0);
		
		$result = new stdClass();

		if (0 == $index && $this->texts[$index] != "")
		{
			//$result->title	= $this->params->get('intro_text') != "" ? $this->params->get('intro_text') : $this->article->title ;
			$result->title = ' - '. JText::_($this->params->get('custom_introtext', 'FLEXIBREAK_INTRO_TEXT')) .' - ';
			$result->name = $result->id = 'start';
			$result->link = JRoute::_(FlexicontentHelperRoute::getItemRoute($row->slug, $row->catid).'&showall=&limitstart=');
			$this->pagescount++;
		}
		else
		{
			if ($this->texts[0] == "")
				$attrs = JUtility::parseAttributes($this->pages[$index][0]);
			else
				$attrs = JUtility::parseAttributes($this->pages[$index-1][0]);
			$result->title	= isset($attrs['title']) ? $attrs['title'] : 'unknown';
			$result->name	= isset($attrs['name']) ? $attrs['name'] : preg_replace('/[ \t]+/u', '', $result->title);
			$result->link = JRoute::_(FlexicontentHelperRoute::getItemRoute($row->slug, $row->catid).'&showall=&limitstart='. ($index));
			
			$result->id		= $result->name ? $result->name : 'start';
		}
		
		$curr_index = $this->texts[0] == "" ? $index+1 : $index;
		switch ($display_method) {
		case 0:
			$this->_text .= '<a id="'.$result->id.'_toc_page"></a>'.$this->texts[$curr_index];  // add an anchor link for scrolling
			break;
		case 1:
			$this->_text .= '<div class="articlePage" id="'.$result->id.'"> '.$this->texts[$curr_index].'</div>';
			break;
		case 2:
			if ($limitstart == $curr_index) $this->_text .= $this->texts[$curr_index];
			break;
		}
		return $result;
	}

	function assignRef($key, &$val)
	{
		if (is_string($key) && substr($key, 0, 1) != '_')
		{
			unset($this->$key);
			$this->$key =& $val;
			return true;
		}
		return false;
	}

	function loadTemplate($name = 'default')
	{
		$app = JFactory::getApplication();
		$override = JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'html'.DS.'plg_'.$this->_type.'_'.$this->_name.DS.(FLEXI_J16GE ? $this->_name.DS : '').$name.'.php';
		ob_start();
		if (is_readable($override))
		{
			include($override);
		}
		else if (is_readable($this->pluginPath.DS.'tmpl'.DS.$name.'.php'))
		{
			include($this->pluginPath.DS.'tmpl'.DS.$name.'.php');
		}
		else
		{
			ob_end_clean();
			JError::raiseError(500, JText::_('Failed to load template '.$name.'.php'));
			return '';
		}
		return trim(ob_get_clean());
	}
}

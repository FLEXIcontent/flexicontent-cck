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
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');

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
	static $rowLinks = array();
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
		// Simple performance check to determine whether bot should process further
		if (
			strpos($row->text, 'class="system-pagebreak') === false &&
			strpos($row->text, 'class=\'system-pagebreak') === false
		)
			return true;
		
		// Sanity check property slug or id exists
		if ( empty($row->id) && empty($row->slug) ) return;  // nothing to do

		// Remove page markers when article in popup (printing)
		if ( JRequest::getBool('pop') )
		{
			$row->text = preg_replace( $this->pattern, '', $row->text );
			return;
		}
		
		// Find all instances of plugin inside the description text
		$pagescount = preg_match_all( $this->pattern, $row->text, $pages, PREG_SET_ORDER );
		$texts      = preg_split( $this->pattern, $row->text );
		$textscount = count($texts);   // Number of breaks

		// If there are no breaks then don't alter the article
		if ($textscount <= 1) return;
		
		// Get slug and limitstart (current page) and use them to create next / previous links
		$row->slug = @ $row->slug ? $row->slug : $row->id;
		$limitstart = JRequest::getInt('limitstart', 0);
		
		// Create item's SEF link without limitstart
		if ( !isset(self::$rowLinks[$row->slug]) )
			self::$rowLinks[$row->slug] = FlexicontentHelperRoute::getItemRoute($row->slug, $row->catid, 0, $row);
		
		$prev_link = $limitstart > 0 ?
			JRoute::_(self::$rowLinks[$row->slug].'&showall=&limitstart='. ($limitstart-1)) :
			'';
		$next_link = $limitstart < $textscount - 1 ?
			JRoute::_(self::$rowLinks[$row->slug].'&showall=&limitstart='. ($limitstart+1)) :
			'';
		
		$this->assignRef('pages', $pages);
		$this->assignRef('pagescount', $pagescount);
		$this->assignRef('texts', $texts);
		$this->assignRef('textscount', $textscount);
		$this->assignRef('limitstart', $limitstart);
		$this->assignRef('row', $row);
		$this->assignRef('prev_link', $prev_link);
		$this->assignRef('next_link', $next_link);
		
		// Plugin base folder
		$document = JFactory::getDocument();
		$plgbase  = JURI::root(true).'/plugins/'.$this->_type.'/'.$this->_name.'/'.$this->_name.'/'.$this->_name;
		
		// Display configuration
		$display_method = $this->params->get('display_method', 1);
		$pagenav        = $this->params->get('pagination', 3);
		$toc_placement  = $this->params->get('toc_placement', 1);
		
		// Add JS code for JS based navigation
		if ($display_method == 1 || $display_method == 0)
		{
			if (class_exists('flexicontent_html'))
			{
				require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');
				flexicontent_html::loadFramework('jQuery');
			}
			$document->addScript($plgbase.'.js');
		}
		
		// Add CSS
		$document->addStyleSheet($plgbase.'.css');
		
		// Clear article's text, so that we re-construct with appropriate containers
		$row->text = '';
		
		// Add page navigation (next / previous) at article's start
		if (2 == $pagenav && $display_method != 0) $row->text .= $this->loadTemplate('pagination_js');
		
		// Create Table Of Contents (TOC). This will also create the text of all pages, encapsulated in containers
		// (having anchor IDs), and assign it to: $this->_text, so that it can be re-assigned to article's text below
		if ($toc_placement) {
			$row->text .= $this->loadTemplate('default_js');
		} else {
			$row->toc = $this->loadTemplate('default_js');
		}
		
		// Append the text of all pages encapsulated in containers (having anchor IDs too)
		$row->text .= $this->_text;
		
		// Add page navigation (next / previous) at article's end
		if (3 == $pagenav && $display_method != 0) $row->text .= $this->loadTemplate('pagination_js');
	}


	function _generateToc( &$row, $index )
	{
		$display_method = $this->params->get('display_method', 1);
		$limitstart = JRequest::getInt('limitstart', 0);
		
		$page = new stdClass();
		
		// If intro text exists, then increment pages-counter by 1, and make page ZERO to be the intro-text page
		if (0 == $index && $this->texts[$index] != "")
		{
			$this->pagescount++;
			
			$page->title = ' - '. JText::_($this->params->get('custom_introtext', 'FLEXIBREAK_INTRO_TEXT')) .' - ';
			$page->name = 'start';
			$page->link = JRoute::_(self::$rowLinks[$row->slug].'&showall=&limitstart=');
			$page->id = str_replace('"', '', str_replace("'", "", $page->name));
		}
		else
		{
			$attrs = $this->texts[0] == "" ?
				JUtility::parseAttributes($this->pages[$index][0]) :
				JUtility::parseAttributes($this->pages[$index-1][0]) ;
			$page->title = isset($attrs['title']) ? $attrs['title'] : 'unknown';
			$page->name	= isset($attrs['name']) ? $attrs['name'] : preg_replace('/[ \t]+/u', '', $page->title);
			$page->link = JRoute::_(self::$rowLinks[$row->slug].'&showall=&limitstart='. ($index));
			$page->id		= $page->name ? str_replace('"', '', str_replace("'", "", $page->name)) : 'start';
		}
		
		$curr_index = $this->texts[0] == "" ? $index+1 : $index;
		if ( !isset($this->_text) ) $this->_text = '';
		
		switch ($display_method) {
			
		// Add an anchor link for scrolling,
		case 0:
			$this->_text .= '<a id="'.$page->id.'_toc_page"></a>'.$this->texts[$curr_index];
			break;
		
		// Add a DIV container for JS based navigation
		case 1:
			$this->_text .= '<div class="articlePage" id="'.$page->id.'"> '.$this->texts[$curr_index].'</div>';
			break;
		
		// Nor scrolled, neither JS based navigation:  Only ADD page's text, if it is the current page
		case 2:
			if ($limitstart == $curr_index) $this->_text .= $this->texts[$curr_index];
			break;
		
		}
		return $page;
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
		$override = JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'html'.DS.'plg_'.$this->_type.'_'.$this->_name.DS.$this->_name.DS.$name.'.php';
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

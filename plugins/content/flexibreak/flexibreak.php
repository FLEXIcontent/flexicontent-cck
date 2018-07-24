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

jimport('cms.plugin.plugin');
use Joomla\String\StringHelper;

if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');

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

	function __construct(&$subject, $params)
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
		if ( $context != 'com_content.article' && $context != 'com_flexicontent.item' ) return true;
		
		// Sanity check property slug or id exists
		if ( empty($row->id) && empty($row->slug) ) return true;
		
		// Simple performance check to determine whether bot should process further.
		if ( StringHelper::strpos($row->text, 'class="system-pagebreak') === false ) return true;
		
		$input = JFactory::getApplication()->input;
		
		$print   = $input->getBool('pop') || $input->getBool('print');
		$showall = $input->getBool('showall');
		$view    = $input->getString('view');
		
		// If current page is print page or is not article / item view,
		// then display all pages, hiding TOC and pagination (prev/next/count)
		if ( $print || ($view!='article' && $view!='item') )
		{
			$this->params->set('multipage_toc', 0);
			$this->params->set('pagination', 0);
			$showall = 'plain';
		}
		
		// Find all instances of plugin inside the description text
		$pagescount = preg_match_all( $this->pattern, $row->text, $pages, PREG_SET_ORDER );
		$texts      = preg_split( $this->pattern, $row->text );
		$textscount = count($texts);   // Number of breaks

		// If there are no breaks then don't alter the article
		if ($textscount <= 1) return;
		
		// Get slug and limitstart (current page) and use them to create next / previous links
		$row->slug = @ $row->slug ? $row->slug : $row->id;
		$limitstart = $input->getInt('limitstart', 0);  // should be same as: $page;
		
		// Create item's SEF link without any extra variables (limitstart , showall)
		if ( !isset(self::$rowLinks[$row->slug]) )
			self::$rowLinks[$row->slug] = FlexicontentHelperRoute::getItemRoute($row->slug, $row->catid, 0, $row);
		
		$prev_link = !$showall && $limitstart > 0 ?
			JRoute::_(self::$rowLinks[$row->slug].'&limitstart='. ($limitstart-1)) :
			'';
		$next_link = !$showall && $limitstart < $textscount - 1 ?
			JRoute::_(self::$rowLinks[$row->slug].'&limitstart='. ($limitstart+1)) :
			'';
		
		$this->pages = $pages;
		$this->pagescount = $pagescount;
		$this->texts = $texts;
		$this->textscount = $textscount;
		$this->limitstart = $limitstart;
		$this->showall = $showall;
		$this->row = $row;
		$this->prev_link = $prev_link;
		$this->next_link = $next_link;
		$this->nonsef_link = self::$rowLinks[$row->slug];
		
		// Plugin base folder
		$document = JFactory::getDocument();
		$plgbase  = JUri::root(true).'/plugins/'.$this->_type.'/'.$this->_name.'/'.$this->_name.'/'.$this->_name;
		
		// Display configuration
		$display_method = $this->params->get('display_method', 1);
		$pagenav        = $this->params->get('pagination', 3);
		
		$multipage_toc  = $this->params->get('multipage_toc', 1);
		$toc_placement  = $this->params->get('toc_placement', 1);
		
		// Add JS code for JS based navigation
		if ($display_method == 1 || $display_method == 0)
		{
			if ( !class_exists('flexicontent_html') )
			{
				require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');
				flexicontent_html::loadFramework('jQuery');
			}
			$document->addScript($plgbase.'.js');
		}
		
		// Add CSS
		$document->addStyleSheet($plgbase.'.css');
		
		// Clear article's text (and toc), so that we re-construct with appropriate containers
		$row->text = '';
		$row->toc = null;
		
		// Create Table Of Contents (TOC) if this was enabled, this may also create the 'text of visible pages' ($this->_text)
		switch ($display_method)
		{
			case 0: case 1: case 2:
				$row->toc = $this->loadTemplate('default_js');
				break;
			
			case 3: case 4:
				$row->toc = $this->loadTemplate('default_tabs_sliders');
				break;
			
			default:
				$row->toc = 'Pagination plugin: Unreachable code due to "display_method": '.$display_method.' (not implemented)';
				break;
		}
		
		// Add page navigation (next / previous) at article's start
		if (2 == $pagenav && !in_array($display_method, array(0, 3, 4))) $row->text .= $this->loadTemplate('pagination_js');
		
		// Respect TOC placement method, but note: if $this->_text ('text of visible pages') is set
		// then it indicates non-free placeable TOC, aka add to description text
		if ($toc_placement || isset($this->_text))
		{
			$row->text .= $row->toc;
			$row->toc = null;
		}
		
		// Get text of visible pages, if the template file has not created it, this is either current page or all pages 
		if ( !isset($this->_text) )
		{
			$this->_text = '';
			for ($i = 0; $i < $pagescount; $i++)  $this->_text .= $this->_getPageText($row, $i, $showall);
		}
		
		// Add visible pages back to the articles description text
		$row->text .= $this->_text;
		
		// Add page navigation (next / previous) at article's end
		if (3 == $pagenav && !in_array($display_method, array(0, 3, 4))) $row->text .= $this->loadTemplate('pagination_js');
	}
	
	
	
	function _generateToc( &$row, $index )
	{
		$display_method = $this->params->get('display_method', 1);
		
		$page = new stdClass();
		
		// If intro text exists, then increment pages-counter by 1, and make page ZERO to be the intro-text page
		if (0 == $index && $this->texts[$index] != "")
		{
			$this->pagescount++;
			
			$page->title = $this->params->get('introtext_title', 1) ?  $row->title  :  JText::_($this->params->get('custom_introtext', 'FLEXIBREAK_INTRODUCTION'));
			$page->link = JRoute::_(self::$rowLinks[$row->slug]);
			
			$page->name = 'Page-1';
			$page->id   = $page->name;
		}
		else
		{
			$attrs = $this->texts[0] == "" ?
				JUtility::parseAttributes($this->pages[$index][0]) :
				JUtility::parseAttributes($this->pages[$index-1][0]) ;
			
			$_alt   = @ $attrs['alt'];
			$_title = $_alt ? $_alt : @ $attrs['title'];
			$_page  = isset($attrs['name']) ? $attrs['name'] : $_title;
			
			$page->title = $_title  ?  $_title  : JText::_('FLEXIBREAK_PAGE') .' '. ($index+1);  // Default is "Page N"
			$page->link  = JRoute::_(self::$rowLinks[$row->slug].'&limitstart='. ($index));
			
			$page->name	 = $this->getHashtag($_page, $index+1);    // Default is "Page-N"
			$page->id		 = $page->name;
		}
		
		$this->page_links[$index] = $page;
		return $page;
	}
	
	
	
	function _getPageText( &$row, $index, $showall )
	{
		$input = JFactory::getApplication()->input;
		$limitstart = $input->getInt('limitstart', 0);
		
		$display_method = $this->params->get('display_method', 1);
		$auto_toc_return = $this->params->get('return_anchors', 1) == 2;
		
		$curr_index = $this->texts[0] == "" ? $index+1 : $index;
		
		$_page = isset($this->page_links[$curr_index] ) ? $this->page_links[$curr_index] : false;
		$page_id = $_page ? $_page->id : 'page_id'.$curr_index;
		
		
		if (($showall && $display_method >= 3) || $showall==='plain')  // Methods 0 - 2 can handle showall on load properly
		{
			return
				$this->texts[$curr_index]
				. ($curr_index < count($this->texts)-1 ? '<hr class="articlePageEnd" />' : '');
		}
		
		if ( !isset($this->toc_return_link) )
		{
			// RETURN-TO-TOC LINK, current uri is needed to avoid page reloading when anchors are clicked and BASE Tags is missing query variables
			$this->toc_return_link = !$this->params->get('multipage_toc', 1) || !$this->params->get('return_anchors', 1) ?  '' :  '
				<br/>
				<a class="btn returnToc'.($display_method==1 && $auto_toc_return ? ' tocReturnAll' : '').'" href="'.htmlentities( JUri::getInstance(), ENT_QUOTES, 'UTF-8' ).'#articleToc" >
					'.JText::_('FLEXIBREAK_RETURN_TO_CONTENTS').'
				</a>';
		}
		switch ($display_method)
		{
			// Add an anchor link for scrolling, and page separator HTML
			case 0:
				return '
					<div class="articlePageScrolled">
						<a class="articleAnchor" id="'.$page_id.'"></a>
					' . $this->texts[$curr_index] . '
					' . $this->toc_return_link . '
					' . ($curr_index < count($this->texts)-1 ? '<hr class="articlePageEnd" />' : '') . '
					</div>';
				break;
			
			// Add a DIV container for JS based navigation
			case 1:
				return '
					<div class="articlePage'.($limitstart == $curr_index || $showall ? ' active' : '').'" id="'.$page_id.'">
						' . $this->texts[$curr_index] . '
						' . $this->toc_return_link . '
						' . ($curr_index < count($this->texts)-1 ? '<hr class="articlePageEnd" />' : '') . '
					</div>';
				break;
			
			// Nor scrolled, neither JS based navigation:  Only ADD page's text, if it is the current page or showall IS true
			case 2:
				if ($limitstart == $curr_index || $showall)
				{
					return
						$this->texts[$curr_index] . '
						' . (!$showall && $auto_toc_return ? '' : $this->toc_return_link)  . '
						' . ( $showall && $curr_index < count($this->texts)-1 ? '<hr class="articlePageEnd" />' : '');
				}
				else return ''; // No text for current page
				break;
			
			default:  // Other case unhandled case, just add all pages, this is normally handled in the template file
				return $this->texts[$curr_index] . ($curr_index < count($this->texts)-1 ? '<hr class="articlePageEnd" />' : '');
				break;
		}
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
	
	
	// Create safe hashtag (UTF8 language characters and dashes)
	function getHashtag($string, $i)
	{
		static $hashtags = array();
		
		$string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');
		
		// Strip invalid characters
		if (function_exists('iconv'))
			$hashtag = iconv("utf-8", "utf-8//IGNORE", $string);
		
		else if (function_exists('mb_convert_encoding'))
			$hashtag = mb_convert_encoding($string, 'utf-8', 'utf-8');
		
		else if (version_compare(PHP_VERSION, '5.4.0', '>='))
			$hashtag = htmlspecialchars_decode(htmlspecialchars($string, ENT_SUBSTITUTE, 'UTF-8'));
		
		// Replace special characters and spaces with dash
		$hashtag = preg_replace('/[^\p{L}0-9-]/u','-', $hashtag);
		
		// Default if empty is Page-N, also no language filtering for it to avoid it changing if language is switched
		if ( empty($hashtag) || isset($hashtags[$hashtag]) )
			$hashtag = 'Page-'.$i;  
		
		$hashtags[$hashtag] = 1;
		return $hashtag;
	}
	
}

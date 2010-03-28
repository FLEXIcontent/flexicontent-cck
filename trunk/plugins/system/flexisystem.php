<?php
/**
 * @version 1.5 stable $Id$
 * @plugin 1.1
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.plugin.plugin' );

/**
 * Example system plugin
 */
class plgSystemFlexisystem extends JPlugin
{
	/**
	 * Constructor
	 */
	function plgSystemFlexisystem( &$subject, $config )
	{
		parent::__construct( $subject, $config );

		$fparams =& JComponentHelper::getParams('com_flexicontent');
		if (!defined('FLEXI_SECTION'))		define('FLEXI_SECTION', $fparams->get('flexi_section'));
		if (!defined('FLEXI_ACCESS')) 		define('FLEXI_ACCESS', (JPluginHelper::isEnabled('system', 'flexiaccess') && version_compare(PHP_VERSION, '5.0.0', '>')) ? 1 : 0);
		if (!defined('FLEXI_CACHE')) 		define('FLEXI_CACHE'		, $fparams->get('advcache', 1));
		if (!defined('FLEXI_CACHE_TIME'))	define('FLEXI_CACHE_TIME'	, $fparams->get('advcache_time', 3600));
		if (!defined('FLEXI_CACHE_GUEST'))	define('FLEXI_CACHE_GUEST'	, $fparams->get('advcache_guest', 1));

        JPlugin::loadLanguage('com_flexicontent', JPATH_SITE);
	}
	
	/**
	 * Do load rules and start checking function
	 */
	function onAfterRoute()
	{
		// ensure the PHP version is correct
		if (version_compare(PHP_VERSION, '5.0.0', '<')) return;

		$this->trackSaveConf();
		if (FLEXI_SECTION) {
			global $globalcats;
			if (FLEXI_CACHE) {
			// add the category tree to categories cache
			$catscache 	=& JFactory::getCache('com_flexicontent_cats');
			$catscache->setCaching(1); 		//force cache
			$catscache->setLifeTime(84600); //set expiry to one day
		    $globalcats = $catscache->call(array('plgSystemFlexisystem', 'getCategoriesTree'));
			} else {
				$globalcats = $this->getCategoriesTree();
			}
		}

		$this->redirectAdminComContent();
		$this->redirectSiteComContent();
	}
	
	function redirectAdminComContent()
	{
		$app 				=& JFactory::getApplication();
		$option 			= JRequest::getCMD('option');
		$applicationName 	= $app->getName();
		$user 				=& JFactory::getUser();
		$minsecs			= $this->params->get('redirect_sections', 24);
		$mincats			= $this->params->get('redirect_cats', 24);
		$minarts			= $this->params->get('redirect_articles', 24);
		
		if (!empty($option)) {
			// if try to access com_content you get redirected to Flexicontent items
			if ($option == 'com_content' && $applicationName == 'administrator' && $user->gid <= $minarts) {
				//get task execution
				$task = JRequest::getCMD('task');
				// url to redirect
				$urlItems = 'index.php?option=com_flexicontent';

				if ($task == 'edit') {
					$cid = JRequest::getVar('id');
					$urlItems .= '&controller=items&task=edit&cid='.intval(is_array($cid) ? $cid[0] : $cid);
				} else {
					$urlItems .= '&view=items';
				}
				
				$app->redirect($urlItems,'');
				return false;

			} elseif ($option == 'com_sections' && $applicationName == 'administrator' && $user->gid <= $minsecs) {
				// url to redirect
				$urlItems = 'index.php?option=com_flexicontent&view=categories';
				
				$scope = JRequest::getVar('scope');
				if ($scope == 'content') {
					$app->redirect($urlItems,'');
				}
				return false;
			
			} elseif ($option == 'com_categories' && $applicationName == 'administrator' && $user->gid <= $mincats) {
				// url to redirect
				$urlItems = 'index.php?option=com_flexicontent&view=categories';
				
				$section = JRequest::getVar('section');
				if ($section == 'com_content') {
					$app->redirect($urlItems,'');
				}
				return false;
			}
		}
	}
	
	function redirectSiteComContent()
	{
		//include the route helper
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');
		//get the section associated with flexicontent
		$flexiparams 	=& JComponentHelper::getParams('com_flexicontent');
		$flexisection 	= $flexiparams->get('flexi_section');
		
		$app 				=& JFactory::getApplication();
		$option 			= JRequest::getCMD('option');
		$applicationName 	= $app->getName();
		$db 				=& JFactory::getDBO();
		
		if( !empty($option) ){

			if($option == 'com_content' && $applicationName == 'site') {

				$view = JRequest::getCMD('view');

				if( $view == 'article' ){
					$id 		= JRequest::getInt('id');
					$itemslug 	= JRequest::getVar('id');
					$catslug	= JRequest::getVar('catid');
					$urlItem 	= $catslug ? FlexicontentHelperRoute::getItemRoute($itemslug, $catslug) : FlexicontentHelperRoute::getItemRoute($itemslug);
					
					$query 	= 'SELECT sectionid FROM #__content'
							. ' WHERE id = ' . $id
							;
					$db->setQuery($query);
					$section = $db->loadResult();

					if ($section == $flexisection) {
						$app->redirect($urlItem);
						return false;
					}
				}
			}
		}
	}
	
	function getCategoriesTree()
	{
		global $globalcats;

		$db		=& JFactory::getDBO();

		// get the category tree and append the ancestors to each node		
		$query	= 'SELECT id, parent_id, published, access, title,'
				. ' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', id, alias) ELSE id END as slug'
				. ' FROM #__categories'
				. ' WHERE section = ' . FLEXI_SECTION
				. ' ORDER BY parent_id, ordering'
				;
		$db->setQuery($query);
		$cats = $db->loadObjectList();

		//establish the hierarchy of the categories
		$children = array();
		$parents = array();
		
    	//set depth limit
   		$levellimit = 10;
		
    	foreach ($cats as $child) {
        	$parent = $child->parent_id;
        	if ($parent) $parents[] = $parent;
       		$list 	= @$children[$parent] ? $children[$parent] : array();
        	array_push($list, $child);
        	$children[$parent] = $list;
    	}
    	
    	$parents = array_unique($parents);

    	//get list of the items
    	$globalcats = plgSystemFlexisystem::_getCatAncestors(0, '', array(), $children, true, max(0, $levellimit-1));

    	foreach ($globalcats as $cat) {
    		$cat->ancestorsonlyarray	= $cat->ancestors;
    		$cat->ancestorsonly			= implode(',', $cat->ancestors);
    		$cat->ancestors[] 			= $cat->id;
    		$cat->ancestorsarray		= $cat->ancestors;
    		$cat->ancestors				= implode(',', $cat->ancestors);
    		$cat->descendantsarray		= plgSystemFlexisystem::_getDescendants(array($cat));
    		$cat->descendants			= implode(',', $cat->descendantsarray);
    	}

    	return $globalcats;
	}

	/**
    * Get the ancestors of each category node
    *
    * @access private
    * @return array
    */
	function _getCatAncestors( $id, $indent, $list, &$children, $title, $maxlevel=9999, $level=0, $type=1, $ancestors=null )
	{
		if (!$ancestors) $ancestors = array();
		
		if (@$children[$id] && $level <= $maxlevel) {
			foreach ($children[$id] as $v) {
				$id = $v->id;
				
				if ((!in_array($v->parent_id, $ancestors)) && $v->parent_id != 0) {
					$ancestors[] 	= $v->parent_id;
				} 
				
				if ( $type ) {
					$pre    = '<sup>|_</sup>&nbsp;';
					$spacer = '.&nbsp;&nbsp;&nbsp;';
				} else {
					$pre    = '- ';
					$spacer = '&nbsp;&nbsp;';
				}

				if ($title) {
					if ( $v->parent_id == 0 ) {
						$txt    = ''.$v->title;
					} else {
						$txt    = $pre.$v->title;
					}
				} else {
					if ( $v->parent_id == 0 ) {
						$txt    = '';
					} else {
						$txt    = $pre;
					}
				}

				$pt = $v->parent_id;
				$list[$id] = $v;
				$list[$id]->treename 		= "$indent$txt";
				$list[$id]->title 			= $v->title;
				$list[$id]->slug 			= $v->slug;
				$list[$id]->ancestors 		= $ancestors;
				$list[$id]->childrenarray 	= @$children[$id];

				$list[$id]->children 		= count( @$children[$id] );

				$list = plgSystemFlexisystem::_getCatAncestors( $id, $indent.$spacer, $list, $children, $title, $maxlevel, $level+1, $type, $ancestors );
			}
		}
		return $list;
	}
	
	/**
    * Get the descendants of each category node
    *
    * @access private
    * @return array
    */
	function _getDescendants($arr, &$descendants = array())
	{
		foreach($arr as $k => $v)
		{
			$descendants[] = $v->id;
		
			if ($v->childrenarray) {
				plgSystemFlexisystem::_getDescendants($v->childrenarray, $descendants);
			}
		}
		return $descendants;
	}
	
	/**
    * Detect if the config was altered to clean the category cache
    *
    * @access public
    * @return void
    */
	function trackSaveConf() {
		$option 	= JRequest::getVar('option');
		$component 	= JRequest::getVar('component');
		$task 		= JRequest::getVar('task');
		
		if ($option == 'com_config' && $component == 'com_flexicontent' && $task == 'save') {
			$catscache 	=& JFactory::getCache('com_flexicontent_cats');
			$catscache->clean();
		}
	}
}
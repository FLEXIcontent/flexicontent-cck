<?php
/**
 * @version 1.2 $Id: mod_flexifilter.php 1536 2012-11-03 09:08:46Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent Filter Module
 * @copyright (C) 2012 ggppdk - www.flexicontent.org
 * @license GNU/GPL v2
 * 
 * Content Listing Filter Module for flexicontent.
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

// Decide whether to show module contents
$app    = JFactory::getApplication();
$view   = JRequest::getVar('view');
$option = JRequest::getVar('option');

if ($option=='com_flexicontent')
	$_view = ($view==FLEXI_ITEMVIEW) ? 'item' : $view;
else
	$_view = 'others';

$show_in_views = $params->get('show_in_views', array());
$show_in_views = !is_array($show_in_views) ? array($show_in_views) : $show_in_views;
$views_show_mod =!count($show_in_views) || in_array($_view,$show_in_views);

if ($params->get('enable_php_rule', 0)) {
	$php_show_mod = eval($params->get('php_rule'));
	$show_mod = $params->get('combine_show_rules', 'AND')=='AND'
		? ($views_show_mod && $php_show_mod)  :  ($views_show_mod || $php_show_mod);
} else {
	$show_mod = $views_show_mod;
}

if ( $show_mod )
{
	global $modfc_jprof;
	jimport('joomla.profiler.profiler');
	$modfc_jprof = new JProfiler();
	$modfc_jprof->mark('START: FLEXIcontent Filter-Search Module');
	
	static $mod_initialized = null;
	$modulename = 'mod_flexifilter';
	if ($mod_initialized === null)
	{
		// Load english language file for current module then override (forcing a reload) with current language file
		JFactory::getLanguage()->load($modulename, JPATH_SITE, 'en-GB', $force_reload = false, $load_default = true);
		JFactory::getLanguage()->load($modulename, JPATH_SITE, null, $force_reload = true, $load_default = true);
		
		// Load english language file for 'com_flexicontent' and then override with current language file. Do not force a reload for either (not needed)
		JFactory::getLanguage()->load('com_flexicontent', JPATH_SITE, 'en-GB', $force_reload = false, $load_default = true);
		JFactory::getLanguage()->load('com_flexicontent', JPATH_SITE, null, $force_reload = false, $load_default = true);
		$mod_initialized = true;
	}
	
	// initialize various variables
	$document = JFactory::getDocument();
	$caching 	= $app->getCfg('caching', 0);
	$flexiparams = JComponentHelper::getParams('com_flexicontent');
	
	// include the helper only once
	require_once (dirname(__FILE__).DS.'helper.php');
	// include flexicontent route helper file
	require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');
	
	
	// Styling parameters
	$moduleclass_sfx= $params->get('moduleclass_sfx', '');
	$layout 				= $params->get('layout', 'default');
	$add_ccs 				= $params->get('add_ccs', !$flexiparams->get('disablecss', 0));
	$add_tooltips 	= $params->get('add_tooltips', 1);
	
	// Form behaviour parameters
	$autosubmit  	  = $params->get('filter_autosubmit', 0);
	
	// CATEGORY SELECTION parameters
	$display_cat_list = $params->get('display_cat_list', 0);
	$catids           = $params->get('catids', array());
	$catlistsize      = $params->get('catlistsize', 6);
	
	// default-OR-specific category ID
	$config_catid = (int)$params->get('catid', 0);
	
	// Current category ID (via any of: menu item / url / single category selector 'cid')
	$is_flexiview = $option=="com_flexicontent" && ($view=="category" || $view=="item");
	$catid_fieldname = 'cid';    // we could use a different name per module but that is an overkill and possible will lead to bugs, e.g. 'cid_mod_'.$module->id;
	$current_cid = $is_flexiview ? JRequest::getInt($catid_fieldname, 0) : 0;
	
	// Decide to use specific category ID or use current category ID
	$force_specific_cid = !$display_cat_list && $config_catid;
	$empty_current_cid  = !$current_cid;
	$catid = $force_specific_cid || $empty_current_cid ? $config_catid : $current_cid;
	
	// TARGET VIEW / Get target menu item for multi-category view case
	$mcats_selection = $display_cat_list ? $params->get('mcats_selection', 0) : 0;
	$mcats_itemid    = $mcats_selection  ? $params->get('mcats_itemid', 0)    : 0;
	
	if ($mcats_itemid)
	{
		$menus = JFactory::getApplication()->getMenu('site', array());
		$mcats_menu = $menus->getItem( $mcats_itemid );
		if (!$mcats_menu) $mcats_itemid = 0;
	}
	
	if ( !empty($mcats_menu) )
	{
		$menu_params = new JRegistry();   // Empty parameters object
		$menu_params->merge( JComponentHelper::getComponent('com_flexicontent')->params );   // Merge component parameters
		$menu_params->merge($mcats_menu->params);   // Merge menu parameters
	}
	
	// Set category id / ids for TEXT autocomplete
	if ($display_cat_list && $mcats_selection)
	{
		$cids_val = JRequest::getVar('cids', array());
		
		// CLEAR single category id, we will you cids from category selector
		$params->set('txt_ac_cid', 'NA');
		!empty($cids_val) ?
			$params->set('txt_ac_cids', is_array($cids_val) ? $cids_val : array((string) $cids_val) ) :  // Use current 'cids' (selected in category selector)
			$params->set('txt_ac_cids', $display_cat_list==1 ? $catids : array() );   // Category selector is empty, use the 'include' categories configured for the selector (include: display_cat_list==1)
	}
	else {
		// Specific or current category (single selector uses name 'cid' which is same name as categor id via menu item or viaitem/category URLs)
		$params->set('txt_ac_cid',  $catid);
		$params->set('txt_ac_cids', array());
	}
	
	
	// FIELD FILTERS
	$display_filter_list  = $params->get('display_filter_list', 0);
	$filter_ids           = $params->get('filters', array());
	$limit_filters_to_cat = $display_filter_list==1 || $display_filter_list==3;
	
	// Check if array or comma separated list
	if ( !is_array($filter_ids) ) {
		$filter_ids = preg_split("/\s*,\s*/u", $filter_ids);
		if ( empty($filter_ids[0]) ) unset($filter_ids[0]);
	}
	// Sanitize the given filter_ids ... just in case
	$filter_ids = array_filter($filter_ids, 'is_numeric');
	// array_flip to get unique filter ids as KEYS (due to flipping) ... and then array_keys to get filter_ids in 0,1,2, ... array
	$filter_ids = array_keys(array_flip($filter_ids));
	
	$form_name = 'moduleFCform_'.$module->id;
	
	/*if (!$catid && !$display_cat_list) :
		echo "WARNING: You must select a target category or display a category list. You have not enabled any of these 2<br>";
	elseif ($catid && $display_cat_list) :
		echo "WARNING: You have selected both: (a) a target category and also set this module not to display category list<br>";
	else :*/
	
	//print_r($filter_ids);
	
	
	// CREATE CATEGORY SELECTOR or create a hidden single category for input
	if ($display_cat_list)
	{
		$_fld_classes = 'fc_field_filter use_select2_lib';
		
		$loader_html = '<span class=\"ajax-loader\"></span>';
		$url_to_load = JURI::root().'index.php?option=com_flexicontent&amp;task=getsefurl&amp;view=category&amp;tmpl=component&amp;cid=';
		$autosubmit_msg = '<span>'.JText::_('FLEXI_RELOADING_PLEASE_WAIT').'</span>';
		
		$_fld_onchange = $_fld_multiple = '';
		
		// CASE 1: Multi-category selector, targeting multi-category view
		if ($mcats_selection) {
			$_fld_size = " size='$catlistsize' ";
			$_fld_multiple = ' multiple="multiple" ';
			$_fld_name = 'cids[]';
			
			$mcats_list = JRequest::getVar('cids', '');
			if ( !is_array($mcats_list) ) {
				$mcats_list = preg_replace( '/[^0-9,]/i', '', (string) $mcats_list );
				$mcats_list = explode(',', $mcats_list);
			}
			// Make sure given data are integers ... !!
			$cids = array();
			foreach ($mcats_list as $i => $_id)  if ((int)$_id) $cids[] = (int)$_id;
		}
		
		// CASE 2: Single category selector, targeting single category view
		else {
			$_fld_classes .= ' fc_autosubmit_exclude';  // exclude from autosubmit because we need to get single category SEF url before submitting, and then submit ...
			$_fld_size = "";
			$_fld_onchange = ' onchange="update_'.$form_name.'();" ';
			$_fld_name = $catid_fieldname;
		}
		$_fld_attributes = ' class="'.$_fld_classes.'" '.$_fld_size.$_fld_onchange.$_fld_multiple;
		
		$allowedtree = modFlexifilterHelper::decideCats($params);
		$selected_cats = $mcats_selection ? $cids : ($catid ? $catid : '');
		$top = false;
		$cats_select_field = flexicontent_cats::buildcatselect($allowedtree, $_fld_name, $selected_cats, $top, $_fld_attributes, $check_published = true, $check_perms = false, array(), $require_all=false);
	}
	
	
	// CASE 3: Hidden single category selector, targeting specific category or current category
	else if ($catid) {
		$cat_hidden_field = '<input type="hidden" name="cid" value="'.$catid.'"/>';
	}
	
	$limit_selector = flexicontent_html::limit_selector( $params, $form_name, $autosubmit );
	$orderby_selector = flexicontent_html::orderby_selector( $params, $form_name, $autosubmit );
	
	// 2. Get category, this is needed so that we get only the allowed filters of the category
	// allowed filters are set in the category options (configuration)
	
	$saved_cid = JRequest::getVar('cid', '');   // save cid ...
	$saved_layout = JRequest::getVar('layout'); // save layout ...
	$saved_option = JRequest::getVar('option'); // save option ...
	$saved_view = JRequest::getVar('view'); // save layout ...
	
	$target_layout = $mcats_selection || !$catid ? 'mcats' : '';
	JRequest::setVar('layout', $target_layout);
	JRequest::setVar($target_layout=='mcats' ? 'cids' : 'cid', $limit_filters_to_cat ? $catid : 0);
	JRequest::setVar('option', 'com_flexicontent');
	JRequest::setVar('view', 'category');
	
	// Get/Create current category model ... according to configuration set above into the JRequest variables ...
	$cat_model = new FlexicontentModelCategory();
	$category = $cat_model->getCategory($pk=null, $raiseErrors=false, $checkAccess=false);
	
	$cat_params = $cat_model->getParams();  // Get current's view category parameters this will if needed to get category specific filters ...
	$cat_model->_buildItemWhere($wherepart='where', $counting=true);
	$cat_model->_buildItemFromJoin($counting=true);
	
	// Category parameters from category or from multi-category menu item
	$view_params = !empty($mcats_menu) ? $menu_params : $cat_params;
	
	// ALL filters
	if ($display_filter_list==0) {
		// WARNING: this CASE is supposed to get ALL filters regardless category,
		// but __ALL_FILTERS__ ignores the 'use_filters' parameter, so we must check it separetely
		// ... $params->set('filters_order', 1);  // respect filters ordering if so configured in category 
		$filters = ! $params->get('use_filters', 0) ? array() : FlexicontentFields::getFilters('filters', '__ALL_FILTERS__', $view_params);
	}
	
	// Filter selected in category configuration
	else if ($display_filter_list==1) {
		// ... $params->set('filters_order', 1);  // respect filters ordering if so configured in category 
		$filters = FlexicontentFields::getFilters('filters', 'use_filters', $view_params);
	}
	
	// Filters selected in module
	else if ($display_filter_list==2) {
		$params->set('filters_order', 1); // respect filters ordering
		$filters = FlexicontentFields::getFilters('filters', 'use_filters', $params);
	}
	
	// Filters selected in module and intersect with current category
	else if ($display_filter_list) {  // ==3
		$params->set('filters_order', 1); // respect filters ordering
		$cat_filters = FlexicontentFields::getFilters('filters', 'use_filters', $params);
		
		// Intersection of selected filters and of category assigned filters
		$filters = array();
		$filter_ids_indexed = array_flip($filter_ids);
		foreach ($cat_filters as $filter_name => $filter) {
			if ( isset($filter_ids_indexed[$filter->id]) ) {
				$filters[] = $filter;
			}
		}
	}
	
	// Remove categories filter
	if ($display_cat_list)  unset($filters[13]);
	
	// Set filter values (initial or locked) via configuration parameters
	FlexicontentFields::setFilterValues( $params, 'persistent_filters', $is_persistent=1);
	FlexicontentFields::setFilterValues( $params, 'initial_filters'   , $is_persistent=0);
	
	// Set if auto-complete will use items from subcategories
	$display_subcats = (int) $view_params->get('display_subcategories_items', 2);   // include subcategory items
	$params->set('txt_ac_usesubs', $display_subcats );
	
	// Override text search auto-complete category ids with those of filter 13
	$f13_val = JRequest::getVar('filter_13');
	if ( isset($filters['categories']) && !empty($f13_val) )
	{
		$params->set('txt_ac_cid', 'NA');
		$params->set('txt_ac_cids', is_array($f13_val) ? $f13_val : array((string) $f13_val) );
	}
	
	// 4. Add html to filter objects
	if ( !empty($filters) ) {
		FlexicontentFields::renderFilters( $params, $filters, $form_name );
	}
	
	// Restore variables
	JRequest::setVar('cid', $saved_cid); // restore cid
	JRequest::setVar('layout', $saved_layout); // restore layout
	JRequest::setVar('option', $saved_option); // restore option
	JRequest::setVar('view', $saved_view); // restore view
	
	// Load needed JS libs & CSS styles
	//JHtml::_('behavior.framework', true);
	flexicontent_html::loadFramework('jQuery');
	flexicontent_html::loadFramework('flexi_tmpl_common');
	
	// Add tooltips
	if ($add_tooltips) JHtml::_('bootstrap.tooltip');
	
	// Add css
	if ($add_ccs && $layout) {
		// Work around for extension that capture module's HTML 
		if ($add_ccs==2) {
			if (file_exists(dirname(__FILE__).DS.'tmpl'.DS.$layout.DS.$layout.'.css')) {
				// active layout css
				echo '<link rel="stylesheet" href="'.JURI::base(true).'/modules/'.$modulename.'/tmpl/'.$layout.'/'.$layout.'.css?'.FLEXI_VHASH.'">';
			}
			echo '<link rel="stylesheet" href="'.JURI::base(true).'/modules/'.$modulename.'/tmpl_common/module.css?'.FLEXI_VHASH.'">';
			echo '<link rel="stylesheet" href="'.JURI::base(true).'/components/com_flexicontent/assets/css/flexicontent.css?'.FLEXI_VHASH.'">';
			//allow css override
			if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css')) {
				echo '<link rel="stylesheet" href="'.JURI::base(true).'/templates/'.$app->getTemplate().'/css/flexicontent.css">';
			}
		}
		
		// Standards compliant implementation by placing CSS link into the HTML HEAD
		else {
			if (file_exists(dirname(__FILE__).DS.'tmpl'.DS.$layout.DS.$layout.'.css')) {
				// active layout css
				$document->addStyleSheetVersion(JURI::base(true).'/modules/'.$modulename.'/tmpl/'.$layout.'/'.$layout.'.css', FLEXI_VHASH);
			}
			$document->addStyleSheetVersion(JURI::base(true).'/modules/'.$modulename.'/tmpl_common/module.css', FLEXI_VHASH);
			$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', FLEXI_VHASH);
			//allow css override
			if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css')) {
				$document->addStyleSheet(JURI::base(true).'/templates/'.$app->getTemplate().'/css/flexicontent.css');
			}
		}
	}
	
	$form_target = '';
	$default_target = $mcats_itemid ? 
		JRoute::_('index.php?Itemid='.$mcats_itemid) :
		JURI::base(true).'/index.php?option=com_flexicontent&view=category&layout=mcats'
		;
	
	// !! target MCATS layout of category view when selecting multiple categories OR selecting single category but no default category set (or no current category)
	if ( ($display_cat_list && $mcats_selection) || !$catid) {
		$form_target = $default_target;
	}
	
	// !! target (single) category view when selecting single category a category is currently selected
	else if ($catid) {
		$db = JFactory::getDBO();
		$query 	= 'SELECT CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
			.' FROM #__categories AS c WHERE c.id = '.$catid;
		$db->setQuery( $query );
		$categoryslug = $db->loadResult();
		$form_target = JRoute::_(FlexicontentHelperRoute::getCategoryRoute($categoryslug), false);
	}
	
	// Render Layout
	require(JModuleHelper::getLayoutPath('mod_flexifilter', $layout));
	
	// Add needed js
	$js = "";
	/*if (!$display_cat_list || !empty($selected_cats)) {
		$js .= '
			jQuery(document).ready(function() {
				jQuery("#'.$form_name.'_filter_box").css("display", "block");
			});
		';
	}
	$document = JFactory::getDocument();
	$document->addScriptDeclaration($js);*/
	
	if ($display_cat_list && !$mcats_selection) {
		$js .= '
			function update_'.$form_name.'() {
				form=document.getElementById("'.$form_name.'");
				cid_val=form.'.$catid_fieldname.'.value;
				/*if ( cid_val.length == 0 ) { jQuery("#'.$form_name.'_filter_box").css("display", "none"); return; } */
				if ( cid_val.length == 0 ) {
					var fcform = jQuery(form);
					var _action = fcform.attr("data-fcform_default_action"); 
					fcform.attr("action", _action);
					fcform.attr("data-fcform_action", _action ); 
					adminFormPrepare(form, 1);
					return;
				}
				getSEFurl("cid_loading_'.$module->id.'",	"'.$loader_html.'", form,"'.$url_to_load.'"+cid_val, "'.$autosubmit_msg.'", '.$autosubmit.', "'.$default_target.'");
				/*jQuery("#'.$form_name.'_filter_box").css("display", "block");*/
			}
		';
	}
	if ($js) JFactory::getDocument()->addScriptDeclaration($js);
	
	// append performance stats to global variable
	if ( $flexiparams->get('print_logging_info') )
	{
		$modfc_jprof->mark('END: FLEXIcontent Filter-Search Module');
		$msg  = '<br/><br/>'.implode('<br/>', $modfc_jprof->getbuffer());
		global $fc_performance_msg;
		$fc_performance_msg .= $msg;
	}
}
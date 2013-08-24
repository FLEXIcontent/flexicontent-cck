<?php
/**
 * @version 1.2 $Id: mod_flexifilter.php 1536 2012-11-03 09:08:46Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent Filter Module
 * @copyright (C) 2012 ggppdk - www.flexicontent.org
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
defined('_JEXEC') or die('Restricted access');

// Decide whether to show module contents
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
	jimport( 'joomla.error.profiler' );
	$modfc_jprof = new JProfiler();
	$modfc_jprof->mark('START: FLEXIcontent Filter-Search Module');
	
	// load english language file for 'mod_flexifilter' module then override with current language file
	JFactory::getLanguage()->load('mod_flexifilter', JPATH_SITE, 'en-GB', true);
	JFactory::getLanguage()->load('mod_flexifilter', JPATH_SITE, null, true);
	
	// initialize various variables
	$document = JFactory::getDocument();
	$config   = JFactory::getConfig();
	$caching  = $config->getValue('config.caching', 0);
	
	// include the helper only once
	require_once (dirname(__FILE__).DS.'helper.php');
	
	// Other parameters
	$moduleclass_sfx= $params->get('moduleclass_sfx', '');
	$layout 				= $params->get('layout', 'default');
	$add_ccs 				= $params->get('add_ccs', 1);
	$add_tooltips 	= $params->get('add_tooltips', 1);
	$autosubmit  	  = $params->get('autosubmit', 0);
	
	// current & default category IDs
	$catid_fieldname = 'cid'; //'filter_catid_'.$module->id;
	$isflexicat = JRequest::getVar('option')=="com_flexicontent" && JRequest::getVar('view')=="category";
	
	$current_cid = $isflexicat ? JRequest::getInt($catid_fieldname, 0) : 0;
	$default_cid = (int)$params->get('catid', 0);
	$catid = !$isflexicat || !$current_cid ? $default_cid : $current_cid;  // id of category view or default value
	
	// CATEGORY SELECTION
	$mcats_selection  = $params->get('mcats_selection', 0);
	$display_cat_list = $params->get('display_cat_list', 0);
	$catids           = $params->get('catids', array());
	$catlistsize      = $params->get('catlistsize', 6);
	
	// FIELD FILTERS
	$display_filter_list  = $params->get('display_filter_list', 0);
	$filterids            = $params->get('filters', array());
	$limit_filters_to_cat = $display_filter_list==1 || $display_filter_list==3;
	
	$form_name = 'moduleFCform_'.$module->id;
	
	/*if (!$catid && !$display_cat_list) :
		echo "WARNING: You must select a target category or display a category list. You have not enabled any of these 2<br>";
	elseif ($catid && $display_cat_list) :
		echo "WARNING: You have selected both: (a) a target category and also set this module not to display category list<br>";
	else :*/
	
	//print_r($filterids);
	
	if ($display_cat_list)
	{
		$_fld_class = ' class="fc_field_filter use_select2_lib select2_list_selected"';
		
		$loader_html = '\'<p class=\\\'qf_centerimg=\\\'><img src=\\\''.JURI::base().'components/com_flexicontent/assets/images/ajax-loader.gif\\\' align=\\\'center\\\'></p>\'';
		$url_to_load = JURI::root().'index.php?option=com_flexicontent&amp;task=getsefurl&amp;view=category&amp;tmpl=component&amp;cid=';
		$autosubmit_msg = JText::_('FLEXI_RELOADING_PLEASE_WAIT');
		
		$_fld_onchange = $_fld_multiple = '';
		if ($mcats_selection) {
			$_fld_size = " size='$catlistsize' ";
			$_fld_multiple = ' multiple="multiple" ';
			$_fld_name = 'cids[]';
			$mcats_list = JRequest::getVar('cids', '');
			if ( !is_array($mcats_list) ) {
				$mcats_list = preg_replace( '/[^0-9,]/i', '', (string) $mcats_list );
				$mcats_list = explode(',', $mcats_list);
			}
			// make sure given data are integers ... !!
			$cids = array();
			foreach ($mcats_list as $i => $_id)  if ((int)$_id) $cids[] = (int)$_id;
		} else {
			$_fld_size = "";
			$_fld_onchange = ' onchange="update_'.$form_name.'();" ';
			$_fld_name = $catid_fieldname;
		}
		$_fld_attributes = $_fld_class.$_fld_size.$_fld_onchange.$_fld_multiple;
		
		$allowedtree = modFlexifilterHelper::decideCats($params);
		$selected_cats = $mcats_selection ? $cids : ($catid ? $catid : "") ;
		$top = $mcats_selection ? false : 2;
		$cats_select_field = flexicontent_cats::buildcatselect($allowedtree, $_fld_name, $selected_cats, $top, $_fld_attributes, $check_published = true, $check_perms = false, array(), $require_all=false);
	} else if ($catid) {
		$cat_hidden_field = '<input type="hidden" name="cid" value="'.$catid.'"/>';
	}
	
	$limit_selector = flexicontent_html::limit_selector( $params, $form_name, $autosubmit );
	$orderby_selector = flexicontent_html::ordery_selector( $params, $form_name, $autosubmit );
	
	// 2. Get category, this is needed so that we get only the allowed filters of the category
	// allowed filters are set in the category options (configuration)
	
	$saved_cid = JRequest::getVar('cid', '');   // save cid ...
	$saved_layout = JRequest::getVar('layout'); // save layout ...
	JRequest::setVar('layout', $mcats_selection || !$catid ? 'mcats' : '');
	JRequest::setVar('cid', $limit_filters_to_cat ? $catid : 0);
	
	$catmodel = new FlexicontentModelCategory();
	//$_params = $catmodel->getParams();
	// ALL filters
	if ($display_filter_list==0) {
		$filters = $catmodel->getFilters('filters', '__ALL_FILTERS__');
	}
	// Filter selected in category configuration
	else if ($display_filter_list==1) {
		$filters = $catmodel->getFilters('filters', 'use_filters');
	}
	// Filters selected in module
	else if ($display_filter_list==2) {
		$filters = $catmodel->getFilters('filters', 'use_filters', $params);
	}
	// Filters selected in module
	else if ($display_filter_list) {  // ==3
		$cat_filters = $catmodel->getFilters('filters', 'use_filters', $params);
		
		// Intersection of selected filters and of category assigned filters
		$filters = array();
		$filterids_indexed = array_flip($filterids);
		foreach ($cat_filters as $filter_name => $filter) {
			if ( isset($filterids_indexed[$filter->id]) ) {
				$filters[] = $filter;
			}
		}
	}
	
	JRequest::setVar('cid', $saved_cid); // restore cid
	JRequest::setVar('layout', $saved_layout); // restore layout
	
	// Set filter values (initial or locked) via configuration parameters
	FlexicontentFields::setFilterValues( $params, 'persistent_filters', $is_persistent=1);
	FlexicontentFields::setFilterValues( $params, 'initial_filters'   , $is_persistent=0);
	
	// 4. Add html to filter objects
	if ($filters) {
		FlexicontentFields::renderFilters( $params, $filters, $form_name );
	}
	
	// Load needed JS libs & CSS styles
	FLEXI_J30GE ? JHtml::_('behavior.framework') : JHTML::_('behavior.mootools');
	flexicontent_html::loadFramework('jQuery');
	flexicontent_html::loadFramework('flexi_tmpl_common');
	
	// Add tooltips
	if ($add_tooltips) JHTML::_('behavior.tooltip');
	
	// Add css
	if ($add_ccs && $layout) {
		if ($caching && !FLEXI_J16GE) {
			// Work around for caching bug in J1.5
			if (file_exists(dirname(__FILE__).DS.'tmpl'.DS.$layout.DS.$layout.'.css')) {
				// active layout css
				echo '<link rel="stylesheet" href="'.JURI::base(true).'/modules/mod_flexifilter/tmpl/'.$layout.'/'.$layout.'.css">';
			}
			echo '<link rel="stylesheet" href="'.JURI::base(true).'/modules/mod_flexifilter/tmpl_common/module.css">';
			echo '<link rel="stylesheet" href="'.JURI::base(true).'/components/com_flexicontent/assets/css/flexicontent.css">';
		} else {
			// Standards compliant implementation for >= J1.6 or earlier versions without caching disabled
			if (file_exists(dirname(__FILE__).DS.'tmpl'.DS.$layout.DS.$layout.'.css')) {
				// active layout css
				$document->addStyleSheet(JURI::base(true).'/modules/mod_flexifilter/tmpl/'.$layout.'/'.$layout.'.css');
			}
			$document->addStyleSheet(JURI::base(true).'/modules/mod_flexifilter/tmpl_common/module.css');
			$document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontent.css');
		}
	}
	
	$form_target = '';
	$default_target = JRoute::_('index.php?option=com_flexicontent&view=category', false) . '&layout=mcats';
	// !! target MCATS layout of category view when selecting multiple categories OR selecting single category but no default category set (or no current category)
	if (($display_cat_list && $mcats_selection) || !$catid) {
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
	
	
	$flexiparams = JComponentHelper::getParams('com_flexicontent');
	if ( $flexiparams->get('print_logging_info') )
	{
		$app = JFactory::getApplication();
		$modfc_jprof->mark('END: FLEXIcontent Filter-Search Module');
		$msg = implode('<br/>', $modfc_jprof->getbuffer());
		$app->enqueueMessage( $msg, 'notice' );
	}
	
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
					form.action = _action;
					fcform.attr("data-fcform_action", _action ); 
					return;
				}
				getSEFurl("cid_loading_'.$module->id.'",	'.$loader_html.', form,"'.$url_to_load.'"+cid_val, "'.$autosubmit_msg.'", '.$autosubmit.');
				/*jQuery("#'.$form_name.'_filter_box").css("display", "block");*/
			}
		';
	}
	if ($js) JFactory::getDocument()->addScriptDeclaration($js);
}
?>
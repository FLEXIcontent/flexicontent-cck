<?php
/**
 * Main File
 *
 * @package     FLEXIcontent Category Filter-Search Form
 * @version     1.0
 *
 * @author      ggppdk
 * @link
 * @copyright   Copyright © 2011 ggppdk All Rights Reserved
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
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
	$default_cid = $params->get('catid', 0);
	$catid = !$isflexicat ? $default_cid : $current_cid;  // id of category view or default value
	
	// CATEGORY SELECTION
	$display_cat_list = $params->get('display_cat_list', 0);
	$catids           = $params->get('catids', array());
	$catlistsize      = $params->get('catlistsize', 4);
	
	// FIELD FILTERS
	$display_filter_list  = $params->get('display_filter_list', 0);
	$filterids            = $params->get('filterids', array());
	$limit_filters_to_cat = $display_filter_list==1 || $display_filter_list==3;
	
	$form_name = 'moduleFCform_'.$module->id;
	
	/*if (!$catid && !$display_cat_list) :
		echo "WARNING: You must select a target category or display a category list. You have not enabled any of these 2<br>";
	elseif ($catid && $display_cat_list) :
		echo "WARNING: You have selected both: (a) a target category and also set this module not to display category list<br>";
	else :*/
	
	//print_r($filterids);
	
	//flexicontent_html::loadFramework('jQuery');
	//flexicontent_html::loadFramework('select2');
	if ($display_cat_list)
	{
		$_fld_class = ' class="fc_field_filter use_select2_lib"';
		$_fld_size = " size='$catlistsize' ";
		
		$loader_html = '\'<p class=\\\'qf_centerimg=\\\'><img src=\\\''.JURI::base().'components/com_flexicontent/assets/images/ajax-loader.gif\\\' align=\\\'center\\\'></p>\'';
		$url_to_load = JURI::root().'index.php?option=com_flexicontent&amp;task=getsefurl&amp;view=category&amp;tmpl=component&amp;cid=';
		$autosubmit_msg = JText::_('FLEXI_RELOADING_PLEASE_WAIT');
		
		$_fld_onchange = ' onchange="'
			.' form=document.getElementById(\''.$form_name.'\'); '
			.' cid_val=form.'.$catid_fieldname.'.value; '
			.' getSEFurl(\'cid_loading_'.$module->id.'\',	'.$loader_html.', form,\''.$url_to_load.'\'+cid_val, \''.$autosubmit_msg.'\', '.$autosubmit.'); '
			.' $(\'fc_filter_mod_elements_'.$module->id.'\').style.display=\'block\'; " '
			;
		$_fld_attributes = $_fld_class.$_fld_size.$_fld_onchange;
		
		$allowedtree = modFlexifilterHelper::decideCats($params);
		$selected_cats = $catid ? array($catid) : false;
		$cats_select_field = flexicontent_cats::buildcatselect($allowedtree, $catid_fieldname, $selected_cats, 2, $_fld_attributes, $check_published = true, $check_perms = false, array(), $require_all=false);
	} else if ($catid) {
		$cat_hidden_field = '<input type="hidden" name="cid" value="'.$catid.'"/>';
	}
	
	$limit_selector = flexicontent_html::limit_selector( $params, $form_name, $autosubmit );
	$orderby_selector = flexicontent_html::ordery_selector( $params, $form_name, $autosubmit );
	
	// 2. Get category, this is needed so that we get only the allowed filters of the category
	// allowed filters are set in the category options (configuration)
	$saved_cid = JRequest::getVar('cid', '');     // save cid ...
	JRequest::setVar('cid', $limit_filters_to_cat ? $catid : 0);
	$catmodel = new FlexicontentModelCategory();
	$cat_filters = $catmodel->getFilters();
	JRequest::setVar('cid', $saved_cid);          // restore cid
	
	// 3. Decide filters to use
	if ($display_filter_list == 0 || $display_filter_list == 1) {
		// ALL filters or category assigned filters
		$filters = & $cat_filters;
	} else {
		// Intersection of selected filters and of category assigned filters
		$filters = array();
		foreach ($cat_filters as $filter_name => $filter) {
			if ( in_array($filter->id, $filterids) ) {
				$filters[] = $filter;
			}
		}
	}
	
	// 4. Create/shape HTML of filters
	$display_label_filter_override = (int) $params->get('show_filter_labels', 0);
	$filter_html = array();
	foreach ($filters as $filter_name => $filter)
	{
		// 4.a Get the filter 's HTML
		$filter_value = JRequest::getVar('filter_'.$filter->id, '');  // CURRENT value
		
		// make sure filter HTML is cleared, and create it
		$display_label_filter_saved = $filter->parameters->get('display_label_filter');
		if ( $display_label_filter_override ) $filter->parameters->set('display_label_filter', $display_label_filter_override); // suppress labels inside filter's HTML (hide or show all labels externally)
		
		// else ... filter default label behavior
		$filter->html = '';  // make sure filter HTML display is cleared
		$field_type = $filter->iscore ? 'core' : $filter->field_type;
		FLEXIUtilities::call_FC_Field_Func($field_type, 'onDisplayFilter', array( &$filter, $filter_value, $form_name ) );
		$filter->parameters->set('display_label_filter', $display_label_filter_saved);
		
		// 4.b Manipulate filter's HTML to match our filtering form
		if ( !empty($filter->html) ) {
			// First replace any 'adminForm' string present in the filter's HTML with the name of our form
			$filter_html[$filter->id] = preg_replace('/([\'"])adminForm([\'"])/', '${1}'.$form_name.'${2}', $filter->html);
			
			// Form field that have form auto submit, need to be have their onChange Event prepended with the FORM PREPARATION function call
			if ( preg_match('/onchange[ ]*=[ ]*([\'"])/i', $filter_html[$filter->id], $matches) ) {
				if ( preg_match('/\.submit\(\)/', $filter_html[$filter->id], $matches) && !preg_match('/adminFormPrepare/', $filter_html[$filter->id], $matches2) ) {
					// Autosubmit detected inside onChange event, prepend the event with form preparation function call
					$filter_html[$filter->id] = preg_replace('/onchange[ ]*=[ ]*([\'"])/i', 'onchange=${1}adminFormPrepare(document.getElementById(\''.$form_name.'\')); ', $filter_html[$filter->id]);
				} else {
					// The onChange Event, has no autosubmit, force GO button (in case GO button was not already inside search box)
					$force_go = true;
				}
			} else {
				// Filter has no onChange event and thus no autosubmit, force GO button  (in case GO button was not already inside search box)
				$force_go = true;
			}
			
		}
	}
	
	if ( !empty($cats_select_field) || !empty($cat_hidden_field ) )
	{
		// Add tooltips
		if ($add_tooltips) JHTML::_('behavior.tooltip');
		
		// Add js
		$document->addScript( JURI::base().'components/com_flexicontent/assets/js/tmpl-common.js');
		
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
		
		// Render Layout
		require(JModuleHelper::getLayoutPath('mod_flexifilter', $layout));
	}
	
	$flexiparams = JComponentHelper::getParams('com_flexicontent');
	if ( $flexiparams->get('print_logging_info') )
	{
		$app = JFactory::getApplication();
		$modfc_jprof->mark('END: FLEXIcontent Filter-Search Module');
		$msg  = implode('<br/>', $modfc_jprof->getbuffer());
		$app->enqueueMessage( $msg, 'notice' );
	}
	
}
?>
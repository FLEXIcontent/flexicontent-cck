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

// load english language file for 'mod_flexifilter' module then override with current language file
JFactory::getLanguage()->load('mod_flexifilter', JPATH_SITE, 'en-GB', true);
JFactory::getLanguage()->load('mod_flexifilter', JPATH_SITE, null, true);

// Include the syndicate functions only once
require_once (dirname(__FILE__).DS.'helper.php');

$document 	=& JFactory::getDocument();
$config 	=& JFactory::getConfig();
$caching 	= $config->getValue('config.caching', 0);

// Other parameters
$moduleclass_sfx= $params->get('moduleclass_sfx', '');
$layout 				= $params->get('layout', 'default');
$add_ccs 				= $params->get('add_ccs', 1);
$add_tooltips 	= $params->get('add_tooltips', 1);
$autosubmit  	  = $params->get('autosubmit', 0);

// current & default category IDs
$isflexicat = JRequest::getVar('option')=="com_flexicontent" && JRequest::getVar('view')=="category";
$cid = $isflexicat ? JRequest::getInt('cid', 0) : 0;
$default_cid = $params->get('catid', $cid);     // Fallback to current category id

// CATEGORY SELECTION
$display_cat_list = $params->get('display_cat_list', 0);
$catids           = $params->get('catids', array());
$catlistsize      = $params->get('catlistsize', 4);

// FIELD FILTERS
$display_filter_list  = $params->get('display_filter_list', 0);
$filterids            = $params->get('filterids', array());
$limit_filters_to_cat = $display_filter_list==1 || $display_filter_list==3;

// Decide category to preselect
//$catid_fieldname = 'filter_catid_'.$module->id;
$catid_fieldname = 'cid';
$catid = JRequest::getInt( $catid_fieldname, $default_cid );  // CURRENTLY selected category or 

$form_name = 'moduleFCform_'.$module->id; // you can change this

$js = '
var fcf_'.$module->id.'_cid = '.$catid.';
var fcf_'.$module->id.'_form;
function fcf_'.$module->id.'_submit() {
	if ( fcf_'.$module->id.'_cid=="" || fcf_'.$module->id.'_cid==0 ) return;
	fcf_'.$module->id.'_form.action += "&cid=" + fcf_'.$module->id.'_cid;
	fcf_'.$module->id.'_form.submit();
}

window.addEvent("domready", function(){
	fcf_'.$module->id.'_form = document.getElementById("'.$form_name.'");
});
';
$document->addScriptDeclaration($js);
$document->addScript( JURI::base().'components/com_flexicontent/assets/js/tmpl-common.js');


/*if (!$catid && !$display_cat_list) :
	echo "WARNING: You must select a target category or display a category list. You have not enabled any of these 2<br>";
elseif ($catid && $display_cat_list) :
	echo "WARNING: You have selected both: (a) a target category and also set this module not to display category list<br>";
else :*/

//print_r($filterids);

if ($display_cat_list)
{
	$class = ' class="inputbox fc_field_filter" ';
	$size = " size='$catlistsize' ";
	$onchange =  !$autosubmit ? '' : ' onchange="adminFormPrepare(document.getElementById(\''.$formname.'\')); document.getElementById(\''.$formname.'\').submit();" ';
	$attribs = $class.$size.$onchange;
	
	$allowedtree = modFlexifilterHelper::decideCats($params);
	$cats_select_field = flexicontent_cats::buildcatselect($allowedtree, $catid_fieldname, array($catid), 3, $attribs, $check_published = true, $check_perms = false, array(), $require_all=false);
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

// Create HTML of filters
$filter_html = array();
foreach ($filters as $filter_name => $filter)
{
	// 4.a Get the filter display we need
	$filter_value = JRequest::getVar('filter_'.$filter->id, '');  // CURRENT value
	$fieldname = $filter->iscore ? 'core' : $filter->field_type;
	if (!isset($filter->html)) {
		FLEXIUtilities::call_FC_Field_Func($fieldname, 'onDisplayFilter', array( &$filter, $filter_value ) );
	}
	if ( isset($filter->html) )
		$filter_html[$filter->id] = str_replace('adminForm', $form_name, $filter->html);   // !!!REPLACE THE form name ('adminForm') used inside the field to use our form name
}

if ($add_ccs && $layout) {
  if ($caching && !FLEXI_J16GE) {
		// Work around for caching bug in J1.5
    if (file_exists(dirname(__FILE__).DS.'tmpl'.DS.$layout.DS.$layout.'.css')) {
      // active layout css
      echo '<link rel="stylesheet" href="'.JURI::base(true).'/modules/mod_flexifilter/tmpl/'.$layout.'/'.$layout.'.css">';
    }
    echo '<link rel="stylesheet" href="'.JURI::base(true).'/modules/mod_flexifilter/tmpl_common/module.css">';
  } else {
    // Standards compliant implementation for >= J1.6 or earlier versions without caching disabled
    if (file_exists(dirname(__FILE__).DS.'tmpl'.DS.$layout.DS.$layout.'.css')) {
      // active layout css
      $document->addStyleSheet(JURI::base(true).'/modules/mod_flexifilter/tmpl/'.$layout.'/'.$layout.'.css');
    }
    $document->addStyleSheet(JURI::base(true).'/modules/mod_flexifilter/tmpl_common/module.css');
  }
}

// Tooltips
if ($add_tooltips) JHTML::_('behavior.tooltip');

// Render Layout
require(JModuleHelper::getLayoutPath('mod_flexifilter', $layout));


?>
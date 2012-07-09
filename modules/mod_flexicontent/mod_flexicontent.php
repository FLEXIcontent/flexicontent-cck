<?php
/**
 * @version 1.2 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent Module
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

//no direct access
defined('_JEXEC') or die('Restricted access');

// Logging Info variables
$start_microtime = microtime(true);
global $fc_content_plg_microtime;
$fc_content_plg_microtime = 0;

global $globalcats;

// initialize
$document 	=& JFactory::getDocument();
$config 	=& JFactory::getConfig();
$caching 	= $config->getValue('config.caching', 0);

// get module ordering & count parameters
$ordering 				= $params->get('ordering');
$ordering_addtitle 	= $params->get('ordering_addtitle',1);
if (!is_array($ordering)) { $ordering = explode(',', $ordering); }
$count 					= (int)$params->get('count', 5);
$featured				= (int)$params->get('count_feat', 1);

// get module display parameters
$moduleclass_sfx= $params->get('moduleclass_sfx', '');
$layout 				= $params->get('layout', 'default');
$add_ccs 				= $params->get('add_ccs', 1);
$add_tooltips 	= $params->get('add_tooltips', 1);
$width 					= $params->get('width');
$height 				= $params->get('height');

// get module basic fields parameters
// standard
$display_title 	= $params->get('display_title',1);
$link_title 		= $params->get('link_title');
$display_text 	= $params->get('display_text',1);
$display_date 	= $params->get('display_date', 1);
$mod_readmore	 	= $params->get('mod_readmore',1);
$mod_use_image 	= $params->get('mod_use_image',1);
$mod_link_image = $params->get('mod_link_image');

// featured
$display_title_feat 	= $params->get('display_title_feat',1);
$link_title_feat 			= $params->get('link_title_feat');
$display_date_feat		= $params->get('display_date_feat',1);
$display_text_feat 		= $params->get('display_text_feat',1);
$mod_readmore_feat		= $params->get('mod_readmore_feat',1);
$mod_use_image_feat 	= $params->get('mod_use_image_feat',1);
$mod_link_image_feat 	= $params->get('mod_link_image_feat');

// get module custom fields parameters
// standard
$use_fields 			= $params->get('use_fields',1);
$display_label 		= $params->get('display_label');
$text_after_label = $params->get('text_after_label');
$fields 					= $params->get('fields');
// featured
$use_fields_feat 				= $params->get('use_fields_feat',1);
$display_label_feat 		= $params->get('display_label_feat');
$text_after_label_feat 	= $params->get('text_after_label_feat');
$fields_feat 						= $params->get('fields_feat');

// module display params
$show_more		= (int)$params->get('show_more', 1);
$more_link		= $params->get('more_link');
$more_title		= $params->get('more_title', 'FLEXI_MODULE_READ_MORE');
$more_css			= $params->get('more_css');

// custom parameters
$custom1 				= $params->get('custom1');
$custom2 				= $params->get('custom2');
$custom3 				= $params->get('custom3');
$custom4 				= $params->get('custom4');
$custom5 				= $params->get('custom5');

// Calculate menu itemid for item links
$menus				= & JApplication::getMenu('site', array());
$itemid_force	= (int)$params->get('itemid_force');
if ($itemid_force==1) {
	$Itemid					= JRequest::getInt('Itemid');
	$menu						= & $menus->getItem($Itemid);
	$component			= @$menu->query['option'] ? $menu->query['option'] : '';
	$forced_itemid	= $component=="com_flexicontent" ? $Itemid : 0;
} else if ($itemid_force==2) {
	$itemid_force_value	= (int)$params->get('itemid_force_value', 0);
	$menu								= & $menus->getItem($itemid_force_value);
	$component					= @$menu->query['option'] ? $menu->query['option'] : '';
	$forced_itemid			= $component=="com_flexicontent" ? $itemid_force_value : 0;
} else {
	$forced_itemid = 0;
}
$params->set('forced_itemid', $forced_itemid);

// include the helper only once
require_once (dirname(__FILE__).DS.'helper.php');

$list_arr = modFlexicontentHelper::getList($params);
$catdata_arr = modFlexicontentHelper::getCategoryData($params);
if (!$catdata_arr) $catdata_arr = array (false);


if ($add_ccs && $layout) {
  if ($caching && !FLEXI_J16GE) {
		// Work around for caching bug in J1.5
    if (file_exists(dirname(__FILE__).DS.'tmpl'.DS.$layout.DS.$layout.'.css')) {
      // active layout css
      echo '<link rel="stylesheet" href="'.JURI::base(true).'/modules/mod_flexicontent/tmpl/'.$layout.'/'.$layout.'.css">';
    }
    echo '<link rel="stylesheet" href="'.JURI::base(true).'/modules/mod_flexicontent/tmpl_common/module.css">';
  } else {
    // Standards compliant implementation for >= J1.6 or earlier versions without caching disabled
    if (file_exists(dirname(__FILE__).DS.'tmpl'.DS.$layout.DS.$layout.'.css')) {
      // active layout css
      $document->addStyleSheet(JURI::base(true).'/modules/mod_flexicontent/tmpl/'.$layout.'/'.$layout.'.css');
    }
    $document->addStyleSheet(JURI::base(true).'/modules/mod_flexicontent/tmpl_common/module.css');
  }
}


// Tooltips
if ($add_tooltips) JHTML::_('behavior.tooltip');

// Render Layout
foreach ($catdata_arr as $i => $catdata) {
	$list = & $list_arr[$i];
	require(JModuleHelper::getLayoutPath('mod_flexicontent', $layout));
}
?>

<?php if ($show_more == 1) : ?>
	<span class="module_readon<?php echo $params->get('moduleclass_sfx'); ?>"<?php if ($more_css) : ?> style="<?php echo $more_css; ?>"<?php endif;?>>
		<a class="readon" href="<?php echo JRoute::_($more_link); ?>" <?php if ($params->get('more_blank') == 1) {echo 'target="_blank"';} ?>><span><?php echo JText::_($more_title); ?></span></a>
	</span>
<?php endif;?>

<?php
$flexiparams =& JComponentHelper::getParams('com_flexicontent');
if ( $flexiparams->get('print_logging_info') ) {
	$elapsed_microseconds = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	$app = & JFactory::getApplication();
	$msg = sprintf( 'FLEXIcontent universal module creation is %.2f secs, (including content plugins: %.2f secs)', $elapsed_microseconds/1000000, $fc_content_plg_microtime/1000000);
	$app->enqueueMessage( $msg, 'notice' );
}
?>
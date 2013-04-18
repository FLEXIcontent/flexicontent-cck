<?php
/**
 * @version 1.2 $Id: mod_flexicontent.php 1150 2012-02-24 03:26:18Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent Module
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent module is universal Content Listing Module for flexicontent.
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

//no direct access
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

if ($params->get('enable_php_rule', 0))
	$php_show_mod = eval($params->get('php_rule'));
else
	$php_show_mod = true;

if ($params->get('combine_show_rules', 'AND')=='AND') {
	$show_mod = $views_show_mod && $php_show_mod;
} else {
	$show_mod = $views_show_mod || $php_show_mod;
}

if ( $show_mod )
{
	global $modfc_jprof;
	jimport( 'joomla.error.profiler' );
	$modfc_jprof = new JProfiler();
	$modfc_jprof->mark('START: FLEXIcontent Module');
	global $mod_fc_run_times;
	$mod_fc_run_times = array();
	
	// Logging Info variables
	global $fc_content_plg_microtime;
	$fc_content_plg_microtime = 0;
	
	// load english language file for 'mod_flexicontent' module then override with current language file
	JFactory::getLanguage()->load('mod_flexicontent', JPATH_SITE, 'en-GB', true);
	JFactory::getLanguage()->load('mod_flexicontent', JPATH_SITE, null, true);
	
	// initialize various variables
	global $globalcats;
	$document = JFactory::getDocument();
	$config   = JFactory::getConfig();
	$caching  = $config->getValue('config.caching', 0);
	
	// include the helper only once
	require_once (dirname(__FILE__).DS.'helper.php');
	
	// Verify parameters (like forced menu item id and comments showing)
	modFlexicontentHelper::verifyParams( $params );
	
	// get module ordering & count parameters
	$ordering 				= $params->get('ordering');
	$ordering_addtitle 	= $params->get('ordering_addtitle',1);
	if (!is_array($ordering)) { $ordering = explode(',', $ordering); }
	$count 					= (int)$params->get('count', 5);
	$featured				= (int)$params->get('count_feat', 1);
	
	// get module's basic display parameters
	$moduleclass_sfx= $params->get('moduleclass_sfx', '');
	$layout 				= $params->get('layout', 'default');
	$add_ccs 				= $params->get('add_ccs', 1);
	$add_tooltips 	= $params->get('add_tooltips', 1);
	$width 					= $params->get('width');
	$height 				= $params->get('height');
	
	// get module basic fields parameters
	// standard
	$display_title 		= $params->get('display_title');
	$link_title 			= $params->get('link_title');
	$display_date 		= $params->get('display_date');
	$display_text 		= $params->get('display_text');
	$display_hits			= $params->get('display_hits');
	$display_voting		= $params->get('display_voting');
	$display_comments	= $params->get('display_comments');
	$mod_readmore	 		= $params->get('mod_readmore');
	$mod_use_image	 	= $params->get('mod_use_image');
	$mod_link_image		= $params->get('mod_link_image');
	
	// featured
	$display_title_feat 	= $params->get('display_title_feat');
	$link_title_feat 			= $params->get('link_title_feat');
	$display_date_feat		= $params->get('display_date_feat');
	$display_text_feat 		= $params->get('display_text_feat');
	$display_hits_feat 		= $params->get('display_hits_feat');
	$display_voting_feat	= $params->get('display_voting_feat');
	$display_comments_feat= $params->get('display_comments_feat');
	$mod_readmore_feat		= $params->get('mod_readmore_feat');
	$mod_use_image_feat 	= $params->get('mod_use_image_feat');
	$mod_link_image_feat 	= $params->get('mod_link_image_feat');
	
	// get module custom fields parameters
	// standard
	$use_fields 				= $params->get('use_fields',1);
	$display_label 			= $params->get('display_label');
	$hide_label_onempty	= $params->get('hide_label_onempty');
	$text_after_label		= $params->get('text_after_label');
	$fields 						= $params->get('fields');
	// featured
	$use_fields_feat 				= $params->get('use_fields_feat',1);
	$display_label_feat 		= $params->get('display_label_feat');
	$hide_label_onempty_feat= $params->get('hide_label_onempty_feat');
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
	
	// Create Item List Data
	$list_arr = modFlexicontentHelper::getList($params);
	
	$mod_fc_run_times['category_data_retrieval'] = $modfc_jprof->getmicrotime();
	
	// Get Category List Data
	$catdata_arr = modFlexicontentHelper::getCategoryData($params);
	$catdata_arr = $catdata_arr ? $catdata_arr : array (false);
	
	$mod_fc_run_times['category_data_retrieval'] = $modfc_jprof->getmicrotime() - $mod_fc_run_times['category_data_retrieval'];
	
	$mod_fc_run_times['rendering_template'] = $modfc_jprof->getmicrotime();
	
	// Add tooltips
	if ($add_tooltips) JHTML::_('behavior.tooltip');
	
	// Add css
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
	
	// Render Layout, (once per category if apply per category is enabled ...)
	foreach ($catdata_arr as $i => $catdata) {
		$list = & $list_arr[$i];
		require(JModuleHelper::getLayoutPath('mod_flexicontent', $layout));
	}
	
	// Add module Read More
	if ($show_more == 1) : ?>
		<span class="module_readon<?php echo $params->get('moduleclass_sfx'); ?>"<?php if ($more_css) : ?> style="<?php echo $more_css; ?>"<?php endif;?>>
			<a class="readon" href="<?php echo JRoute::_($more_link); ?>" <?php if ($params->get('more_blank') == 1) {echo 'target="_blank"';} ?>><span><?php echo JText::_($more_title); ?></span></a>
		</span>
	<?php endif;
	
	$mod_fc_run_times['rendering_template'] = $modfc_jprof->getmicrotime() - $mod_fc_run_times['rendering_template'];
	
	$task_lbls = array(
		'query_items'=>'DB Querying of Items: %.2f secs',
		'empty_fields_filter'=>'Empty fields filter (skip items)): %.2f secs',
		'item_list_creation'=>'Item list creation (with custom field rendering): %.2f secs',
		'category_data_retrieval'=>'Category data retrieval: %.2f secs',
		'rendering_template'=>'Adding css/js & Rendering Template with item/category/etc data: %.2f secs'
	);
	$flexiparams = JComponentHelper::getParams('com_flexicontent');
	if ( $flexiparams->get('print_logging_info') )
	{
		$app = JFactory::getApplication();
		$modfc_jprof->mark('END: FLEXIcontent Module');
		$msg  = implode('<br/>', $modfc_jprof->getbuffer());
		$msg .= sprintf( '<code> <b><u>including</u></b>: <br/> -- Content Plugins: %.2f secs</code><br/>', $fc_content_plg_microtime/1000000);
		foreach ($mod_fc_run_times as $modtask => $modtime) {
			$msg .= '<code>'.sprintf( ' -- '.$task_lbls[$modtask].'<br/>', $modtime) .'</code>';
		}
		$app->enqueueMessage( $msg, 'notice' );
	}
	
}
?>
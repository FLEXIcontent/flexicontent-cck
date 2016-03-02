<?php
/**
 * @version 1.5 stable $Id: default.php 1890 2014-04-26 04:19:53Z ggppdk $
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

defined('_JEXEC') or die('Restricted access');

$tooltip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';

require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');
$action = JRoute::_(FlexicontentHelperRoute::getSearchRoute(0, $itemid), true);
$form_id = "default_form_".$module->id;
$form_name = "default_form_".$module->id;

$txtmode = $params->get('txtmode', 0);
$show_search_label = $params->get('show_search_label', 1);
$search_autocomplete = $params->get( 'search_autocomplete', 1 );
$flexi_button_class_go =  ($params->get('flexi_button_class_go' ,'') != '-1')  ?
    $params->get('flexi_button_class_go', (FLEXI_J30GE ? 'btn btn-info' : 'fc_button'))   :
    $params->get('flexi_button_class_go_custom', (FLEXI_J30GE ? 'btn btn-info' : 'fc_button'))  ;
$flexi_button_class_direct =  ($params->get('flexi_button_class_direct' ,'') != '-1')  ?
    $params->get('flexi_button_class_direct', (FLEXI_J30GE ? 'btn btn-info' : 'fc_button'))   :
    $params->get('flexi_button_class_direct_custom', (FLEXI_J30GE ? 'btn btn-info' : 'fc_button'))  ;
$flexi_button_class_advanced =  ($params->get('flexi_button_class_advanced' ,'') != '-1')  ?
    $params->get('flexi_button_class_advanced' ,'')   :
    $params->get('flexi_button_class_advanced_custom', 'fc_button fcsimple flexiadvsearchlink')  
?>

<div class="mod_flexiadvsearch_wrapper mod_flexiadvsearch_wrap<?php echo $moduleclass_sfx; ?>" id="mod_flexiadvsearch_default<?php echo $module->id ?>">

<form class="mod_flexiadvsearch<?php echo $params->get('moduleclass_sfx'); ?>" name="<?php echo $form_name; ?>" id="<?php echo $form_id; ?>" action="<?php echo $action; ?>" method="post">
	<div class="search<?php echo $params->get('moduleclass_sfx') ?>">
		<input name="option" type="hidden" value="com_flexicontent" />
		<input name="view" type="hidden" value="search" />
		<span class="fc_filter_html fc_text_search">
		<?php
		$append_buttons = ( !$button || (!$button_as && in_array($button_pos, array('left', 'right'))) ) && ( !$direct || (!$direct_as && in_array($direct_pos, array('left', 'right'))) );
		
		$_ac_index = $txtmode ? 'fc_adv_complete' : 'fc_basic_complete';
		$text_search_class  = !$append_buttons ? 'fc_text_filter' : '';
		$_label_internal = '';//'fc_label_internal';  // data-fc_label_text="..."
		$text_search_class .= $search_autocomplete ? ($search_autocomplete==2 ? ' fc_index_complete_tlike '.$_ac_index : ' fc_index_complete_simple '.$_ac_index.' '.$_label_internal) : ' '.$_label_internal;
		//$text_search_label = JText::_($show_search_label==2 ? 'FLEXI_TEXT_SEARCH' : 'FLEXI_TYPE_TO_LIST');
		$search_inner_width = JText::_($params->get('search_inner_width', 20));
		$search_inner_prompt = JText::_($params->get('search_inner_prompt', 'FLEXI_ADV_MOD_SEARCH_PROMPT'));
		$width = $params->get('width', 10);
		$maxchars = $params->get('maxchars', 200);
		
		//$output = '<input name="q" id="mod_search_searchword-'.$module->id.'" maxlength="'.$maxlength.'" alt="'.$button_text.'" class="fc_field_filter inputbox" type="text" size="'.$width.'" value="'.$text.'"  onblur="if(this.value==\'\') this.value=\''.$text.'\';" onfocus="if(this.value==\''.$text.'\') this.value=\'\';" />';
		$output = '
			<input type="'.($search_autocomplete==2 ? 'hidden' : 'text').'"
				id="mod_search_searchword-'.$module->id.'" class="'.$text_search_class.'"
				placeholder="'.$search_inner_prompt.'" name="q" '.($search_autocomplete==2 ? '' : ' size="'.$search_inner_width.'" maxlength="'.$maxchars.'"').' value="" />';
		
		// Search GO button
		if ($button) :
			if ($button_as) :
				$button = '<input type="image" title="'.$button_text.'" class="'.(!$append_buttons ? 'fc_filter_button' : '').$tooltip_class.' '.$flexi_button_class_go.'" src="'.JURI::base().$button_image.'" onclick="this.form.q.focus();"/>';
			else :
				$button = '<input type="submit" value="'.$button_text.'" class="'.(!$append_buttons ? 'fc_filter_button' : '').' '.$flexi_button_class_go.'" onclick="this.form.q.focus();"/>';
			endif;
		else :
			/* Hidden submit button so that pressing Enter will work */
			$button = '<input type="submit" value="'.$button_text.'" style="position:absolute; left:-9999px;" onclick="this.form.q.focus();" />';
		endif;
		
		switch ($button_pos) :
			case 'top'   : $output = $button.'<br />'.$output;  break;
			case 'bottom': $output = $output.'<br />'.$button;  break;
			case 'right' : $output = $output.' '.$button;  break;
			case 'left'  :
			default      : $output = $button.' '.$output; break;
		endswitch;
		
		// Search DIRECT (lucky) button
		if ($direct) :
			if ($direct_as) :
				// hidden field, is workaround for image button not being able to submit a value
				$direct = '
					<input type="hidden" name="direct" value="" />
					<input type="image" title="'.$direct_text.'" class="'.(!$append_buttons ? 'fc_filter_button' : '').$tooltip_class.' '.$flexi_button_class_direct.'" src="'.JURI::base().$direct_image.'" onclick="this.form.direct.value=1; this.form.q.focus();"/>
					';
			else :
			 $direct = '<input type="submit" name="direct" value="'.$direct_text.'" class="'.(!$append_buttons ? 'fc_filter_button' : '').' '.$flexi_button_class_direct.'" onclick="this.form.q.focus();"/>';
			endif;
			
			switch ($direct_pos) :
				case 'top'   : $output = $direct.'<br />'.$output;  break;
				case 'bottom': $output = $output.'<br />'.$direct;  break;
				case 'right' : $output = $output.' '.$direct;  break;
				case 'left'  :
				default      : $output = $direct.' '.$output; break;
			endswitch;
		endif;
		
		// If using button in same row try to create bootstrap btn input append
		$output = $append_buttons ? '<span class="btn-wrapper input-append">'.$output.'</span>' : $output;
		
		// Display the optional buttons and advanced search box
		?>
			<?php	echo $output; ?>
			<?php if ($linkadvsearch) : /* Display advanced search link*/ ?>
				<a href="<?php echo $action; ?>" class="<?php echo $flexi_button_class_advanced;?>"><?php echo $linkadvsearch_txt;?></a>
			<?php endif; ?>
		</span>
	</div>
	
</form>
</div>

<?php
$js = '
	jQuery(document).ready(function() {
		jQuery("#'.$form_id.' input:not(.fc_autosubmit_exclude), #'.$form_id.' select:not(.fc_autosubmit_exclude)").on("change", function() {
			var form=document.getElementById("'.$form_id.'");
			adminFormPrepare(form, 1);
		});
	});
';
$document = JFactory::getDocument();
$document->addScriptDeclaration($js);
?>
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
    $params->get('flexi_button_class_go', (FLEXI_J30GE ? 'btn btn-success' : 'fc_button'))   :
    $params->get('flexi_button_class_go_custom', (FLEXI_J30GE ? 'btn btn-success' : 'fc_button'))  ;
$flexi_button_class_direct =  ($params->get('flexi_button_class_direct' ,'') != '-1')  ?
    $params->get('flexi_button_class_direct', (FLEXI_J30GE ? 'btn' : 'fc_button'))   :
    $params->get('flexi_button_class_direct_custom', (FLEXI_J30GE ? 'btn' : 'fc_button'))  ;
$flexi_button_class_advanced =  ($params->get('flexi_button_class_advanced' ,'') != '-1')  ?
    $params->get('flexi_button_class_advanced', (FLEXI_J30GE ? 'btn' : 'fc_button'))   :
    $params->get('flexi_button_class_advanced_custom', (FLEXI_J30GE ? 'btn' : 'fc_button'))  
?>

<div class="mod_flexiadvsearch_wrapper mod_flexiadvsearch_wrap<?php echo $moduleclass_sfx; ?>" id="mod_flexiadvsearch_default<?php echo $module->id ?>">

<form class="mod_flexiadvsearch<?php echo $params->get('moduleclass_sfx'); ?>" name="<?php echo $form_name; ?>" id="<?php echo $form_id; ?>" action="<?php echo $action; ?>" method="post">
	<div class="search<?php echo $params->get('moduleclass_sfx') ?>">
		<input name="option" type="hidden" value="com_flexicontent" />
		<input name="view" type="hidden" value="search" />
		<span class="fc_filter_html fc_text_search">
		<?php
		$prependToText =
			( $button && $button_pos == 'left' ) ||
			( $direct && $direct_pos == 'left' ) ||
			( $linkadvsearch && $linkadvsearch_pos == 'left' );
		$appendToText =
			( $button && $button_pos == 'right' ) ||
			( $direct && $direct_pos == 'right' ) ||
			( $linkadvsearch && $linkadvsearch_pos == 'right' );
		$isInputGrp = $prependToText || $appendToText;

		$_ac_index = $txtmode ? 'fc_adv_complete' : 'fc_basic_complete';
		$text_search_class  = !$isInputGrp ? 'fc_text_filter' : '';
		$_label_internal = '';//'fc_label_internal';  // data-fc_label_text="..."
		$text_search_class .= $search_autocomplete ? ($search_autocomplete==2 ? ' fc_index_complete_tlike '.$_ac_index : ' fc_index_complete_simple '.$_ac_index.' '.$_label_internal) : ' '.$_label_internal;
		
		//$text_search_label = JText::_($show_search_label==2 ? 'FLEXI_TEXT_SEARCH' : 'FLEXI_TYPE_TO_LIST');
		$search_inner_width = JText::_($params->get('search_inner_width', 20));
		$search_inner_prompt = JText::_($params->get('search_inner_prompt', 'FLEXI_ADV_MOD_SEARCH_PROMPT'));
		$width = $params->get('width', 10);
		$maxchars = $params->get('maxchars', 200);
		
		$button_html = $direct_html = $hidden_html = false;
		$top_html = $bottom_html = $output = array();
		
		//$output[] = '<input name="q" id="mod_search_searchword-'.$module->id.'" maxlength="'.$maxlength.'" alt="'.$button_text.'" class="fc_field_filter inputbox" type="text" size="'.$width.'" value="'.$text.'"  onblur="if(this.value==\'\') this.value=\''.$text.'\';" onfocus="if(this.value==\''.$text.'\') this.value=\'\';" />';
		$output[] = '
			<input type="'.($search_autocomplete==2 ? 'hidden' : 'text').'"
				id="mod_search_searchword-'.$module->id.'" class="'.$text_search_class.'"
				placeholder="'.$search_inner_prompt.'" name="q" '.($search_autocomplete==2 ? '' : ' size="'.$search_inner_width.'" maxlength="'.$maxchars.'"').' value="" />';
		
		// Search's GO button
		if ($button) :
			if ($button_as) :
				$button_html = '<input type="image" title="'.$button_text.'" class="'.(!$isInputGrp ? 'fc_filter_button' : '').$tooltip_class.' '.$flexi_button_class_go.'" src="'.JUri::base().$button_image.'" onclick="this.form.q.focus();"/>';
			else :
				$button_html = '<input type="submit" value="'.$button_text.'" class="'.(!$isInputGrp ? 'fc_filter_button' : '').' '.$flexi_button_class_go.'" onclick="this.form.q.focus();"/>';
			endif;
		else :
			/* Hidden submit button so that pressing Enter will work */
			$hidden_html = '<input type="submit" value="'.$button_text.'" style="position:absolute; left:-9999px;" onclick="this.form.q.focus();" />';
		endif;
		
		if ($button_html) switch ($button_pos) :
			case 'top'   : $top_html[]    = $button_html;  break;
			case 'bottom': $bottom_html[] = $button_html;  break;
			case 'right' : array_push($output, $button_html);  break;
			case 'left'  :
			default      : array_unshift($output, $button_html); break;
		endswitch;
		
		// Search's DIRECT (lucky) button
		if ($direct) :
			if ($direct_as) :
				// hidden field, is workaround for image button not being able to submit a value
				$direct_html = '
					<input type="hidden" name="direct" value="" />
					<input type="image" title="'.$direct_text.'" class="'.(!$isInputGrp ? 'fc_filter_button' : '').$tooltip_class.' '.$flexi_button_class_direct.'" src="'.JUri::base().$direct_image.'" onclick="this.form.direct.value=1; this.form.q.focus();"/>
					';
			else :
			 $direct_html = '<input type="submit" name="direct" value="'.$direct_text.'" class="'.(!$isInputGrp ? 'fc_filter_button' : '').' '.$flexi_button_class_direct.'" onclick="this.form.q.focus();"/>';
			endif;
			
			if ($direct_html) switch ($direct_pos) :
				case 'top'   : $top_html[]    = $direct_html;  break;
				case 'bottom': $bottom_html[] = $direct_html;  break;
				case 'right' : array_push($output, $direct_html);  break;
				case 'left'  :
				default      : array_unshift($output, $direct_html); break;
			endswitch;
		endif;
		
		// Search's 'ADVANCED' link button
		if ($linkadvsearch) :
			$linkadvsearch_html = '<input type="button" onclick="window.location.href=\''.$action.'\';" class="'.(!$isInputGrp ? 'fc_filter_button' : '').' '.$flexi_button_class_advanced.'" value="'.$linkadvsearch_txt.'" />';
			
			if ($linkadvsearch_html) switch ($linkadvsearch_pos) :
				case 'top'   : $top_html[]    = $linkadvsearch_html;  break;
				case 'bottom': $bottom_html[] = $linkadvsearch_html;  break;
				case 'right' : array_push($output, $linkadvsearch_html);  break;
				case 'left'  :
				default      : array_unshift($output, $linkadvsearch_html); break;
			endswitch;
		endif;
		
		// If using button in same row try to create bootstrap btn input append
		$txt_grp_class = $params->get('bootstrap_ver', 2)==2  ?  (($prependToText ? ' input-prepend' : '') . ($appendToText ? ' input-append' : '')) : 'input-group';
		$input_grp_class = $params->get('bootstrap_ver', 2)==2  ?  'input-prepend  input-append' : 'input-group';
		
		$output =
			(count($top_html) > 1 ? '<span class="btn-wrapper '.$input_grp_class.'">'.implode("\n", $top_html).'</span>' : implode("\n", $top_html)).
			(count($output) > 1 ? '<span class="btn-wrapper '.$txt_grp_class.'">'.implode("\n", $output).'</span>' : implode("\n", $output)).
			(count($bottom_html) > 1 ? '<span class="btn-wrapper '.$input_grp_class.'">'.implode("\n", $bottom_html).'</span>' : implode("\n", $bottom_html));
		
		// Display the optional buttons and advanced search box
		echo $output . $hidden_html;		
		?>
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
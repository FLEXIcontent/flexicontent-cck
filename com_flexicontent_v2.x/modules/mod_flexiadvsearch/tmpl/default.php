<?php
/**
 * @version 1.5 stable $Id: default.php 1760 2013-09-10 10:42:37Z ggppdk $
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
JHTML::_('behavior.tooltip');

$action = JRoute::_(FlexicontentHelperRoute::getSearchRoute(0, $itemid), false);
$form_id = "default_form_".$module->id;
$form_name = "default_form_".$module->id;

$txtmode = $params->get('txtmode', 0);
$show_search_label = $params->get('show_search_label', 1);
$search_autocomplete = $params->get( 'search_autocomplete', 1 );
?>

<div class="mod_flexiadvsearch_wrapper mod_flexiadvsearch_wrap<?php echo $moduleclass_sfx; ?>" id="mod_flexiadvsearch_default<?php echo $module->id ?>">

<form class="mod_flexiadvsearch<?php echo $params->get('moduleclass_sfx'); ?>" name="<?php echo $form_name; ?>" id="<?php echo $form_id; ?>" action="<?php echo $action; ?>" method="post">
	<div class="search<?php echo $params->get('moduleclass_sfx') ?>">
		<input name="option" type="hidden" value="com_flexicontent" />
		<input name="view" type="hidden" value="search" />
		<span class="fc_filter fc_text_search">
		<?php
		//$output = '<input name="searchword" id="mod_search_searchword-'.$module->id.'" maxlength="'.$maxlength.'" alt="'.$button_text.'" class="fc_field_filter inputbox" type="text" size="'.$width.'" value="'.$text.'"  onblur="if(this.value==\'\') this.value=\''.$text.'\';" onfocus="if(this.value==\''.$text.'\') this.value=\'\';" />';
		
		$_ac_index = $txtmode ? 'fc_basic_complete' : 'fc_adv_complete';
		$text_search_class  = 'fc_text_filter';
		$text_search_class .= $search_autocomplete ? ($search_autocomplete==2 ? ' fc_index_complete_tlike '.$_ac_index : ' fc_index_complete_simple '.$_ac_index.' fc_label_internal') : ' fc_label_internal';
		$text_search_label = JText::_($show_search_label==2 ? 'FLEXI_TEXT_SEARCH' : 'FLEXI_TYPE_TO_LIST');
		$maxchars = $params->get('maxchars', 200);
		$output = '<input type="'.($search_autocomplete==2 ? 'hidden' : 'text').'" class="'.$text_search_class.'"
				fc_label_text="'.$text_search_label.'" name="searchword" size="" maxlength="'.$maxchars.'" 
				id="search_searchword" value="" />';
		
		if ($button) :
		    if ($button_as) :
		        $button = '<input type="image" value="'.$button_text.'" class="button" src="'.JURI::base().$button_image.'" onclick="this.form.searchword.focus();"/>';
		    else :
		        $button = '<input type="submit" value="'.$button_text.'" class="fc_button" onclick="this.form.searchword.focus();"/>';
		    endif;
		endif;
		switch ($button_pos) :
		    case 'top' :
			    $button = $button.'<br />';
			    $output = $button.$output;
			    break;
	
		    case 'bottom' :
			    $button = '<br />'.$button;
			    $output = $output.$button;
			    break;
	
		    case 'right' :
			    $output = $output.$button;
			    break;
	
		    case 'left' :
		    default :
			    $output = $button.$output;
			    break;
		endswitch;
		echo $output;
		?>
		</span>
	</div>
	
	<?php if ($linkadvsearch) : ?>
	<a href="<?php echo $action; ?>" class="fc_button fcsimple flexiadvsearchlink"><?php echo $linkadvsearch_txt;?></a>
	<?php endif; ?>
	
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
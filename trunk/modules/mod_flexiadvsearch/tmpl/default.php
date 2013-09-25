<?php
/**
 * @version 1.5 stable $Id$
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
?>

<div class="mod_flexiadvsearch_wrapper mod_flexiadvsearch_wrap<?php echo $moduleclass_sfx; ?>" id="mod_flexiadvsearch_default<?php echo $module->id ?>">

<form class="mod_flexiadvsearch<?php echo $params->get('moduleclass_sfx'); ?>" name="<?php echo $form_name; ?>" id="<?php echo $form_id; ?>" action="<?php echo $action; ?>" method="post">
	<div class="search<?php echo $params->get('moduleclass_sfx') ?>">
	<input name="option" type="hidden" value="com_flexicontent" />
	<input name="view" type="hidden" value="search" />
	<?php
	$output = '<input name="searchword" id="mod_search_searchword-'.$module->id.'" maxlength="'.$maxlength.'" alt="'.$button_text.'" class="fc_field_filter inputbox" type="text" size="'.$width.'" value="'.$text.'"  onblur="if(this.value==\'\') this.value=\''.$text.'\';" onfocus="if(this.value==\''.$text.'\') this.value=\'\';" />';
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
<?php
/**
 * @version 1.5 stable $Id: default.php 1193 2012-03-14 09:20:15Z emmanuel.danan@gmail.com $
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
?>

<form id="mod-flexiadvsearch-<?php echo $module->id;?>" action="index.php" method="get">
	<div class="search<?php echo $params->get('moduleclass_sfx') ?>">
	<?php
	$output = '<input name="searchword" id="mod_search_searchword-'.$module->id.'" maxlength="'.$maxlength.'" alt="'.$button_text.'" class="inputbox'.$moduleclass_sfx.'" type="text" size="'.$width.'" value="'.$text.'"  onblur="if(this.value==\'\') this.value=\''.$text.'\';" onfocus="if(this.value==\''.$text.'\') this.value=\'\';" />';
	if ($button) :
	    if ($imagebutton) :
	        $button = '<input type="image" value="'.$button_text.'" class="button'.$moduleclass_sfx.'" src="'.$img.'" onclick="this.form.searchword.focus();"/>';
	    else :
	        $button = '<input type="submit" value="'.$button_text.'" class="button'.$moduleclass_sfx.'" onclick="this.form.searchword.focus();"/>';
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
	<?php
	if($linkadvsearch) {
	?>
	<a href="index.php?option=com_flexicontent&amp;task=search<?php echo $useitemid?'&amp;Itemid='.$mitemid:'';?>" class="flexiadvsearchlink"><?php echo $linkadvsearch_txt;?></a>
	<?php
	}
	?>
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="task" value="search" />
	<?php if($useitemid) {?>
	<input type="hidden" name="Itemid" value="<?php echo $mitemid; ?>" />
	<?php }?>
</form>

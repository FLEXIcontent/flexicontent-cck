<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

$i = 0;
$layout = JRequest::getCmd('layout', '');

// Sub-category prefix/suffix/separator parameters
$pretext = $this->params->get( 'peercat_pretext', '' ); $posttext = $this->params->get( 'peercat_posttext', '' );
$opentag = $this->params->get( 'peercat_opentag', '' ); $closetag = $this->params->get( 'peercat_closetag', '' );

$separatorf = $this->params->get( 'peercat_separatorf' ); 
$separators_arr = array( 0 => '&nbsp;', 1 => '<br />', 2 => '&nbsp;|&nbsp;', 3 => ',&nbsp;', 4 => $closetag.$opentag, 5 => '' );
$separatorf = isset($separators_arr[$separatorf]) ? $separators_arr[$separatorf] : '&nbsp;';

$cats_label = JText::_( $this->category->id ? 'FLEXI_PEERCATEGORIES' : 'FLEXI_CATEGORIES' );

// Sub-category information parameters
$show_empty_cats = $this->params->get('show_empty_peercats', 1);
$show_label_peercats = $this->params->get('show_label_peercats', 1);
$show_itemcount   = $this->params->get('show_itemcount_peercat', 0);
$show_subcatcount = $this->params->get('show_subcatcount_peercat', 0);
$itemcount_label   = ($show_itemcount==2   ? ' '.JText::_('FLEXI_ITEM_S').' ' : '');
$peercatcount_label = ($show_subcatcount==2 ? ' '.JText::_('FLEXI_CATEGORIES').' ' : '');
$show_description_image_peercat = (int) $this->params->get('show_description_image_peercat', 0);
$show_description_peercat     = (int) $this->params->get('show_description_peercat', 0);
$description_cut_text_peercat = (int) $this->params->get('description_cut_text_peercat', 120);

// Classes for sub-category containers
$peercats_lbl_class = ($show_description_peercat || $show_description_image_peercat) ? "fc_inline_clear"  : "fc_inline";
$peercats_lbl_dots  = ($show_description_peercat || $show_description_image_peercat) ? ""  : ": ";
$peercat_cont_class = $show_description_peercat ? "fc_block"  : "fc_inline_block";
$peercat_info_class = $show_description_peercat ? "fc_inline_clear" : "fc_inline";

$peercats_html = array();
foreach ($this->peercats as $sub) {
	if (!$show_empty_cats && $show_itemcount && $sub->assigneditems==0) continue;
	$subsubcount = count($sub->subcats);
	
	// a. Optional sub-category image
	$peercats_html[$i] = "<span class='floattext peercat ".$peercat_cont_class."'>\n";
	if ($show_description_image_peercat && $sub->image) {
		$peercats_html[$i] .= "  <span class='catimg'>".$sub->image."</span>\n";
	}
	
	$peercats_html[$i] .= "  <span class='catinfo ".$peercat_info_class."'>\n";
	
	// b. Category title with link and optional item counts
	$cat_link = ($layout=='myitems' || $layout=='author') ? $this->action .(strstr($this->action, '?') ? '&amp;'  : '?'). 'cid='.$sub->slug :
		JRoute::_( FlexicontentHelperRoute::getCategoryRoute($sub->slug) );
	$infocount_str = '';
	if ($show_itemcount)   $infocount_str .= (int) $sub->assigneditems . $itemcount_label;
	if ($show_subcatcount) $infocount_str .= ($show_itemcount ? ' / ' : '').count($sub->subcats) . $peercatcount_label;
	if (strlen($infocount_str)) $infocount_str = ' (' . $infocount_str . ')';
	$peercats_html[$i] .= "    <a class='catlink' href='".$cat_link."'>".$this->escape($sub->title)."</a>".$infocount_str."</span>\n";
	
	// c. Optional sub-category description stripped of HTML and cut to given length
	if ($show_description_peercat && $sub->description) {
		$peercats_html[$i] .= "  <span class='catdescription'>". flexicontent_html::striptagsandcut( $sub->description, $description_cut_text_peercat )."</span>";
	}
	
	$peercats_html[$i] .= "</span>\n";
	
	// d. Add prefix, suffix to the HTML of current sub-category
	$peercats_html[$i] = $pretext.$peercats_html[$i].$posttext;
	$i++;
}

// Create the HTML of sub-category list , add configured separator
$peercats_html = implode($separatorf, $peercats_html);
// Add open/close tag to the HTML
$peercats_html = $opentag .$peercats_html. $closetag;
// Add optional sub-categories list label
if ($show_label_peercats) {
	$peercats_label = "<span class='peercategorieslabel ".$peercats_lbl_class."'>".$cats_label."</span>".$peercats_lbl_dots;
	$peercats_html = $peercats_label.$peercats_html;
}
?>

<div class="peercategorieslist group">
	<?php echo $peercats_html; ?>
</div>
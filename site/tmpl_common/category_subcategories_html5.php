<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

$i = 0;
$layout = JRequest::getCmd('layout', '');

// Sub-category prefix/suffix/separator parameters
$pretext = $this->params->get( 'subcat_pretext', '' ); $posttext = $this->params->get( 'subcat_posttext', '' );
$opentag = $this->params->get( 'subcat_opentag', '' ); $closetag = $this->params->get( 'subcat_closetag', '' );

$separatorf = $this->params->get( 'subcat_separatorf' ); 
$separators_arr = array( 0 => '&nbsp;', 1 => '<br />', 2 => '&nbsp;|&nbsp;', 3 => ',&nbsp;', 4 => $closetag.$opentag, 5 => '' );
$separatorf = isset($separators_arr[$separatorf]) ? $separators_arr[$separatorf] : '&nbsp;';

$cats_label = JText::_( $this->category->id ? 'FLEXI_SUBCATEGORIES' : 'FLEXI_CATEGORIES' );

// Sub-category information parameters
$show_empty_cats = $this->params->get('show_empty_cats', 1);
$show_label_subcats = $this->params->get('show_label_subcats', 1);
$show_itemcount   = $this->params->get('show_itemcount', 1);
$show_subcatcount = $this->params->get('show_subcatcount', 0);
$itemcount_label   = ($show_itemcount==2   ? ' '.JText::_('FLEXI_ITEM_S').' ' : '');
$subcatcount_label = ($show_subcatcount==2 ? ' '.JText::_('FLEXI_CATEGORIES').' ' : '');
$show_description_image_subcat = (int) $this->params->get('show_description_image_subcat', 0);
$show_description_subcat     = (int) $this->params->get('show_description_subcat', 0);
$description_cut_text_subcat = (int) $this->params->get('description_cut_text_subcat', 120);

// Classes for sub-category containers
$subcats_lbl_class = ($show_description_subcat || $show_description_image_subcat) ? "fc_inline_clear"  : "fc_inline";
$subcats_lbl_dots  = ($show_description_subcat || $show_description_image_subcat) ? ""  : ": ";
$subcat_cont_class = $show_description_subcat ? "fc_block"  : "fc_inline_block";
$subcat_info_class = $show_description_subcat ? "fc_inline_clear" : "fc_inline";

$subcats_html = array();
foreach ($this->categories as $sub) {
	if (!$show_empty_cats && $show_itemcount && $sub->assigneditems==0) continue;
	$subsubcount = count($sub->subcats);
	
	// a. Optional sub-category image
	$subcats_html[$i] = "<span class='floattext subcat ".$subcat_cont_class."'>\n";
	if ($show_description_image_subcat && $sub->image) {
		$subcats_html[$i] .= "  <span class='catimg'>".$sub->image."</span>\n";
	}
	
	$subcats_html[$i] .= "  <span class='catinfo ".$subcat_info_class."'>\n";
	
	// b. Category title with link and optional item counts
	$cat_link = ($layout=='myitems' || $layout=='author') ? $this->action .(strstr($this->action, '?') ? '&amp;'  : '?'). 'cid='.$sub->slug :
		JRoute::_( FlexicontentHelperRoute::getCategoryRoute($sub->slug) );
	$infocount_str = '';
	if ($show_itemcount)   $infocount_str .= (int) $sub->assigneditems . $itemcount_label;
	if ($show_subcatcount) $infocount_str .= ($show_itemcount ? ' / ' : '').count($sub->subcats) . $subcatcount_label;
	if (strlen($infocount_str)) $infocount_str = ' (' . $infocount_str . ')';
	$subcats_html[$i] .= "    <a class='catlink' href='".$cat_link."'>".$this->escape($sub->title)."</a>".$infocount_str."</span>\n";
	
	// c. Optional sub-category description stripped of HTML and cut to given length
	if ($show_description_subcat && $sub->description) {
		$subcats_html[$i] .= "  <span class='catdescription'>". flexicontent_html::striptagsandcut( $sub->description, $description_cut_text_subcat )."</span>";
	}
	
	$subcats_html[$i] .= "</span>\n";
	
	// d. Add prefix, suffix to the HTML of current sub-category
	$subcats_html[$i] = $pretext.$subcats_html[$i].$posttext;
	$i++;
}

// Create the HTML of sub-category list , add configured separator
$subcats_html = implode($separatorf, $subcats_html);
// Add open/close tag to the HTML
$subcats_html = $opentag .$subcats_html. $closetag;
// Add optional sub-categories list label
if ($show_label_subcats) {
	$subcats_label = "<span class='subcategorieslabel ".$subcats_lbl_class."'>".$cats_label."</span>".$subcats_lbl_dots;
	$subcats_html = $subcats_label.$subcats_html;
}
?>

<div class="subcategorieslist group">
	<?php echo $subcats_html; ?>
</div>
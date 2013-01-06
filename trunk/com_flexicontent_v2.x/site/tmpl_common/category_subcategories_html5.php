<?php
/**
 * HTML5 Template
 * @version 1.5 stable $Id: category_subcategories.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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

defined( '_JEXEC' ) or die( 'Restricted access' );
?>

<?php
$n = count($this->categories);
$i = 0;

// Sub-category prefix/suffix/separator parameters
$pretext = $this->params->get( 'pretext', '' ); $posttext = $this->params->get( 'posttext', '' );
$opentag = $this->params->get( 'opentag', '' ); $closetag = $this->params->get( 'closetag', '' );

$separatorf = $this->params->get( 'separatorf' ); 
$separators_arr = array( 0 => '&nbsp;', 1 => '<br />', 2 => '&nbsp;|&nbsp;', 3 => ',&nbsp;', 4 => $closetag.$opentag);
$separatorf = isset($separators_arr[$separatorf]) ? $separators_arr[$separatorf] : '&nbsp;';

// Sub-category information parameters
$show_label_subcats = $this->params->get('show_label_subcats', 1);
$show_itemcount     = $this->params->get('show_itemcount', 1);
$show_description_image_subcat = $this->params->get('show_description_image_subcat', 0);
$show_description_subcat     = $this->params->get('show_description_subcat', 0);
$description_cut_text_subcat = $this->params->get('description_cut_text_subcat', 120);

// Classes for sub-category containers
$subcats_lbl_class = ($show_description_subcat || $show_description_image_subcat) ? "fc_inline_clear"  : "fc_inline";
$subcats_lbl_dots  = ($show_description_subcat || $show_description_image_subcat) ? ""  : ": ";
$subcat_cont_class = $show_description_subcat ? "fc_block"  : "fc_inline_block";
$subcat_info_class = $show_description_subcat ? "fc_inline_clear" : "fc_inline";

$subcats_html = array();
foreach ($this->categories as $sub) {
	$subsubcount = count($sub->subcats);
	
	// a. Optional sub-category image
	$subcats_html[$i] = "<span class='floattext subcat ".$subcat_cont_class."'>\n";
	if ($show_description_image_subcat && $sub->image) {
		$subcats_html[$i] .= "  <span class='catimg'>".$sub->image."</span>\n";
	}
	
	$subcats_html[$i] .= "  <span class='catinfo ".$subcat_info_class."'>\n";
	
	// b. Category title with link and optional item counts
	$cat_link = JRoute::_( FlexicontentHelperRoute::getCategoryRoute($sub->slug) );
	$itemcount = !$show_itemcount ? '' : '	(' . ($sub->assigneditems != null ? $sub->assigneditems.'/'.$subsubcount : '0/'.$subsubcount) . ')';
	$subcats_html[$i] .= "    <a class='catlink' href='".$cat_link."'>".$this->escape($sub->title)."</a>".$itemcount."</span>\n";
	
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
	$subcats_label = "<span class='subcategorieslabel ".$subcats_lbl_class."'>".JText::_( 'FLEXI_SUBCATEGORIES' )."</span>".$subcats_lbl_dots;
	$subcats_html = $subcats_label.$subcats_html;
}
?>

<div class="subcategorieslist group">
	<?php echo $subcats_html; ?>
</div>
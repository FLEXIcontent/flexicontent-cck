<?php
/**
 * @package FLEXIcontent
 * @copyright (C) 2009-2021 Emmanuel Danan, Georgios Papadakis, Yannick Berges
 * @author Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @license GNU/GPL v2
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
// first define the template name
$tmpl = $this->tmpl;
$user = JFactory::getUser();

$readon_type  = (int) $this->params->get('readon_type', 0);
$readon_image = $this->params->get('readon_image', '');
$readon_class = $this->params->get('readon_class', 'btn btn-default');
$use_lazy_loading = (int) $this->params->get('use_lazy_loading', 1);
$lazy_loading = $use_lazy_loading ? ' loading="lazy" decoding="async" ' : '';


if ($readon_type && $readon_image && file_exists(JPath::clean(JPATH_SITE . DS . $readon_image)))
{
	$readon_image = JUri::base(true) . '/' . $readon_image;
}
$readon_type = !$readon_image ? 0 : $readon_type;

// standard
$display_date 		= $this->params->get('display_date');
$display_text 		= $this->params->get('display_text');
$display_hits			= $this->params->get('display_hits');
$display_voting		= $this->params->get('display_voting');
$display_comments	= $this->params->get('display_comments');

// featured
$display_date_feat		= $this->params->get('display_date_feat');
$display_text_feat 		= $this->params->get('display_text_feat');
$display_hits_feat 		= $this->params->get('display_hits_feat');
$display_voting_feat	= $this->params->get('display_voting_feat');
$display_comments_feat= $this->params->get('display_comments_feat');

$hl_items_onnav_feat = (int)$this->params->get('hl_items_onnav_feat', 0);
$do_hlight_feat = '';
$do_hlight_feat .= $hl_items_onnav_feat == 1 || $hl_items_onnav_feat == 3 ? ' mod_hl_active' : '';
$do_hlight_feat .= $hl_items_onnav_feat == 2 || $hl_items_onnav_feat == 3 ? ' mod_hl_hover' : '';

$hl_items_onnav = (int)$this->params->get('hl_items_onnav', 0);
$do_hlight = '';
$do_hlight .= $hl_items_onnav == 1 || $hl_items_onnav == 3 ? ' mod_hl_active' : '';
$do_hlight .= $hl_items_onnav == 2 || $hl_items_onnav == 3 ? ' mod_hl_hover' : '';

// Item Dimensions featured
$ibox_inner_inline_css_feat = (int)$this->params->get('ibox_inner_inline_css_feat', 0);
$ibox_padding_top_bottom_feat = (int)$this->params->get('ibox_padding_top_bottom_feat', 8);
$ibox_padding_left_right_feat = (int)$this->params->get('ibox_padding_left_right_feat', 12);
$ibox_margin_top_bottom_feat = (int)$this->params->get('ibox_margin_left_right_feat', 4);
$ibox_margin_left_right_feat = (int)$this->params->get('ibox_margin_left_right_feat', 4);
$ibox_border_width_feat = (int)$this->params->get('ibox_border_width_feat', 1);
$ibox_background_color_feat = $this->params->get('ibox_background_color_feat', '');


// Item Dimensions standard
$ibox_inner_inline_css = (int)$this->params->get('ibox_inner_inline_css', 0);
$ibox_padding_top_bottom = (int)$this->params->get('ibox_padding_top_bottom', 8);
$ibox_padding_left_right = (int)$this->params->get('ibox_padding_left_right', 12);
$ibox_margin_top_bottom = (int)$this->params->get('ibox_margin_left_right', 4);
$ibox_margin_left_right = (int)$this->params->get('ibox_margin_left_right', 4);
$ibox_border_width = (int)$this->params->get('ibox_border_width', 1);
$ibox_background_color = $this->params->get('ibox_background_color', '');


// *****************************************************
// Content placement and default image of featured items
// *****************************************************
$content_display_feat = $this->params->get('content_display_feat', 0);  // 0: always visible, 1: On mouse over / item active, 2: On mouse over
$content_layout_feat = $this->params->get('content_layout_feat', 3);  // 0/1: floated (right/left), 2/3: cleared (above/below), 4/5/6: overlayed (top/bottom/full)
$item_img_fit_feat = $this->params->get('img_fit_feat', 1);   // 0: Auto-fit, 1: Auto-fit and stretch to larger

switch ($content_layout_feat) {
	case 0: case 1:
		$img_container_class_feat = ($content_layout_feat==0 ? 'fc_float_left' : 'fc_float_right');
		$content_container_class_feat = 'fc_floated';
		break;
	case 2: case 3:
		$img_container_class_feat = 'fc_stretch fc_clear';
		$content_container_class_feat = '';
		break;
	case 4: case 5: case 6:
		$img_container_class_feat = 'fc_stretch';
		$content_container_class_feat = 'fc_overlayed '
			.($content_layout_feat==4 ? 'fc_top' : '')
			.($content_layout_feat==5 ? 'fc_bottom' : '')
			.($content_layout_feat==6 ? 'fc_full' : '')
			;
		if ($content_display_feat >= 1) $content_container_class_feat .= ' fc_auto_show';
		if ($content_display_feat == 1) $content_container_class_feat .= ' fc_show_active';
		break;
	default: $img_container_class_feat = '';  break;
}



// ***
// *** Content placement and default image of standard items
// ***
$content_display = $this->params->get('content_display', 0);  // 0: always visible, 1: On mouse over / item active, 2: On mouse over
$content_layout = $this->params->get('content_layout', 3);  // 0/1: floated (right/left), 2/3: cleared (above/below), 4/5/6: overlayed (top/bottom/full)
$item_img_fit = $this->params->get('img_fit', 1);   // 0: Auto-fit, 1: Auto-fit and stretch to larger

switch ($content_layout) {
	case 0: case 1:
		$img_container_class = ($content_layout==0 ? 'fc_float_left' : 'fc_float_right');
		$content_container_class = 'fc_floated';
		break;
	case 2: case 3:
		$img_container_class = 'fc_stretch fc_clear';
		$content_container_class = '';
		break;
	case 4: case 5: case 6:
		$img_container_class = 'fc_stretch';
		$content_container_class = 'fc_overlayed '
			.($content_layout==4 ? 'fc_top' : '')
			.($content_layout==5 ? 'fc_bottom' : '')
			.($content_layout==6 ? 'fc_full' : '')
			;
		if ($content_display >= 1) $content_container_class .= ' fc_auto_show';
		if ($content_display == 1) $content_container_class .= ' fc_show_active';
		break;
	default: $img_container_class = '';  break;
}



// ***
// *** Default image and image fitting
// ***

$mod_default_img_path = $this->params->get('mod_default_img_path', 'components/com_flexicontent/assets/images/image.png');
$img_path = JUri::base(true) .'/'; 

// image of FEATURED items, auto-fit and (optionally) limit to image max-dimensions to avoid stretching
$img_auto_dims_css_feat=" width: 100%; height: auto; display: block !important; border: 0 !important;";

// image of STANDARD items, auto-fit and (optionally) limit to image max-dimensions to avoid stretching
$img_auto_dims_css=" width: 100%; height: auto; display: block !important; border: 0 !important;";


// Featured
$box_background_color_feat = $this->params->get('box_background_color_feat', '');
$box_padding_top_bottom_feat = $this->params->get('box_padding_top_bottom_feat', '');
$box_padding_left_right_feat = $this->params->get('box_padding_left_right_feat', '');
$box_margin_top_bottom_feat = $this->params->get('box_margin_top_bottom_feat', '');
$box_margin_left_right_feat = $this->params->get('box_margin_left_right_feat', '');

$item_columns_feat = (int) $this->params->get('item_columns_feat', 1);
$item_placement_feat = (int) $this->params->get('item_placement_feat', 0);  // 0: cleared, 1: as masonry tiles
$cols_class_feat = ($item_columns_feat <= 1)  ?  ''  :  'cols_'.$item_columns_feat;

// Standard
$box_background_color_std = $this->params->get('box_background_color', '');
$box_padding_top_bottom_std = $this->params->get('box_padding_top_bottom', '');
$box_padding_left_right_std = $this->params->get('box_padding_left_right', '');
$box_margin_top_bottom_std = $this->params->get('box_margin_top_bottom', '');
$box_margin_left_right_std = $this->params->get('box_margin_left_right', '');

$item_placement_std = $this->params->get('item_placement', 0);  // -1: other, 0: cleared, 1: as masonry tiles
$item_columns_std = $this->params->get('item_columns', 2);
$cols_class_std  = ($item_columns_std  <= 1)  ?  ''  :  'cols_'.$item_columns_std;

$document = JFactory::getDocument();

// Add masonry JS
$load_masonry_feat = $item_placement_feat == 1 && $item_columns_feat > 1;
$load_masonry_std  = $item_placement_std == 1 && $item_columns_std > 1;

$lead_use_image        = $this->params->get('lead_use_image', 1);
$lead_link_image       = $this->params->get('lead_link_image', 1);
$lead_link_image_to    = $this->params->get('lead_link_image_to', 0);
$lead_use_description  = $this->params->get('lead_use_description', 1);

$intro_use_image       = $this->params->get('intro_use_image', 1);
$intro_link_image      = $this->params->get('intro_link_image', 1);
$intro_link_image_to   = $this->params->get('intro_link_image_to', 0);
$intro_use_description = $this->params->get('intro_use_description', 1);

$lead_link_to_popup  = $this->params->get('lead_link_to_popup', 0);
$intro_link_to_popup = $this->params->get('intro_link_to_popup', 0);

if ($lead_link_to_popup || $intro_link_to_popup) {
	flexicontent_html::loadFramework('flexi-lib');
}


// MICRODATA 'itemtype' for ALL items in the listing (this is the fallback if the 'itemtype' in content type / item configuration are not set)
$microdata_itemtype_cat = $this->params->get( 'microdata_itemtype_cat', 'Article' );

// ITEMS as MASONRY tiles
if (!empty($this->items) && ($load_masonry_feat || $load_masonry_std))
{
	flexicontent_html::loadFramework('masonry');
	flexicontent_html::loadFramework('imagesLoaded');

	$js = "
		jQuery(document).ready(function(){
	";
	if ($load_masonry_feat) {
		$js .= "
			var container_lead = document.querySelector('div.featured-block.fc-items-block');
			var msnry_lead;
			// initialize Masonry after all images have loaded
			if (container_lead) {
				imagesLoaded( container_lead, function() {
					msnry_lead = new Masonry( container_lead );
				});
			}
		";
	}
	if ($load_masonry_std) {
		$js .= "
			var container_intro = document.querySelector('div.standard-block.fc-items-block');
			var msnry_intro;
			// initialize Masonry after all images have loaded
			if (container_intro) {
				imagesLoaded( container_intro, function() {
					msnry_intro = new Masonry( container_intro );
				});
			}
		";
	}
	$js .= "	
		});
	";
	JFactory::getDocument()->addScriptDeclaration($js);
}
?>

<?php
	ob_start();

	// Form for (a) Text search, Field Filters, Alpha-Index, Items Total Statistics, Selectors(e.g. per page, orderby)
	// If customizing via CSS rules or JS scripts is not enough, then please copy the following file here to customize the HTML too
    file_exists(dirname(__FILE__).DS.'listings_filter_form_html5.php')
        ? include(dirname(__FILE__).DS.'listings_filter_form_html5.php')
        : include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'listings_filter_form_html5.php');

	$filter_form_html = trim(ob_get_contents());
	ob_end_clean();
	if ( $filter_form_html ) {
		echo '<aside class="group">'."\n".$filter_form_html."\n".'</aside>';
	}
?>

<div class="fcclear"></div>

<?php
if (!$this->items) {
	// No items exist
	if ($this->getModel()->getState('limit')) {
		// Not creating a category view without items
		echo '<div class="noitems group">' . JText::_( 'FLEXI_NO_ITEMS_FOUND' ) . '</div>';
	}
	return;
}

$items	= & $this->items;
$count 	= count($items);
// Calculate common data outside the item loops
if ($count) {
	$_read_more_about = JText::_( 'FLEXI_READ_MORE_ABOUT' );
	$tooltip_class = FLEXI_J30GE ? 'hasTooltip' : 'hasTip';
	$_comments_container_params = 'class="fc_comments_count '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_NUM_OF_COMMENTS', 'FLEXI_NUM_OF_COMMENTS_TIP', 1, 1).'"';
}
?>
<div class="content group">

<?php
$leadnum  = $this->params->get('lead_num', 1);
$leadnum  = ($leadnum >= $count) ? $count : $leadnum;

// Handle category block (start of category items)
$doing_cat_order = $this->category->_order_arr[1]=='order';
$lead_catblock  = $this->params->get('lead_catblock', 0);
$intro_catblock = $this->params->get('intro_catblock', 0);
$lead_catblock_title  = $this->params->get('lead_catblock_title', 1);
$intro_catblock_title = $this->params->get('intro_catblock_title', 1);
if ($lead_catblock || $intro_catblock) {
	global $globalcats;
}

// ONLY FIRST PAGE has leading content items
if ($this->limitstart != 0) $leadnum = 0;

$lead_cut_text  = $this->params->get('lead_cut_text', 400);
$intro_cut_text = $this->params->get('intro_cut_text', 200);
$uncut_length = 0;
FlexicontentFields::getFieldDisplay($items, 'text', $values=null, $method='display'); // Render 'text' (description) field for all items

$rowtoggler = 0;
if ($leadnum) :
	//added to intercept more columns (see also css changes)
	$lead_cols = $this->params->get('lead_cols', 1);
	$lead_cols_classes = array(1=>'one',2=>'two',3=>'three',4=>'four');
	$classnum = $lead_cols_classes[$lead_cols];
?>


	<!-- BOF DIV featured-block (featured items) -->

	<div class="featured-block news fc-items-block <?php echo $classnum; ?> group row">

		<?php
		if ($lead_use_image && $this->params->get('lead_image'))
		{
			$img_size_map   = array('l'=>'large', 'm'=>'medium', 's'=>'small', 'o'=>'original');
			$img_field_size = $img_size_map[ $this->params->get('lead_image_size' , 'l') ];
			$img_field_name = $this->params->get('lead_image');
		}
		
		$lead_fallback_field = $params->get('lead_fallback_field', 0);
		$lead_image_fallback_img = $params->get('lead_image_fallback_img');
		$lead_image_custom_display	= $params->get('lead_image_custom_url');
		$lead_image_custom_url	= $params->get('lead_image_custom_url');

		$lead_dimgs = $this->params->get('lead_default_images');
		if ($lead_use_image && $lead_dimgs)
		{
			$lead_dimgs = preg_split("/[\s]*,[\s]*/", $lead_dimgs);
			$lead_type_default_imgs = array();
			foreach ($lead_dimgs as $_image) {
				$_d = preg_split("/[\s]*##[\s]*/", $_image);
				$_type_alias  = empty($_d[1]) ? '_OTHER_' : $_d[0];
				$_type_dimage = empty($_d[1]) ? $_d[0] : $_d[1];
				$lead_type_default_imgs[$_type_alias] = $_type_dimage;
			}
		}

		$rowcount = 0;
		for ($i=0; $i < $leadnum; $i++) :
			$item = $items[$i];
			$src = '';
			$thumb_rendered = '';
			$fc_item_classes = 'fc_newslist_item';
			if ($doing_cat_order)
     		$fc_item_classes .= ($i==0 || ($items[$i-1]->rel_catid != $items[$i]->rel_catid) ? ' fc_cat_item_1st' : '');
			$fc_item_classes .= ' fccol'.($i%$lead_cols + 1);

			$markup_tags = '<span class="fc_mublock">';
			foreach($item->css_markups as $grp => $css_markups)
			{
				if ( empty($css_markups) )  continue;
				$fc_item_classes .= ' fc'.implode(' fc', $css_markups);

				$ecss_markups  = $item->ecss_markups[$grp];
				$title_markups = $item->title_markups[$grp];
				foreach($css_markups as $mui => $css_markup)
				{
					$markup_tags .= '<span class="fc_markup mu' . $css_markups[$mui] . $ecss_markups[$mui] .'">' .$title_markups[$mui]. '</span>';
				}
			}
			$markup_tags .= '</span>';

			$custom_link = null;
			if ($lead_use_image) :
				if ($lead_image_custom_display)
				{
					@list($fieldname, $varname) = preg_split('/##/',$lead_image_custom_display);
					$fieldname = trim($fieldname); $varname = trim($varname);
					$varname = $varname ? $varname : 'display';
					$thumb_rendered = FlexicontentFields::getFieldDisplay($item, $fieldname, null, $varname, 'category');
					$src = '';  // Clear src no rendering needed
					$item->image_w = $item->image_h = 0;
				}
				if (!$src && !$thumb_rendered && $lead_image_custom_url)
				{
					@list($fieldname, $varname) = preg_split('/##/',$lead_image_custom_url);
					$fieldname = trim($fieldname); $varname = trim($varname);
					$varname = $varname ? $varname : 'display';
					$src =  FlexicontentFields::getFieldDisplay($item, $fieldname, null, $varname, 'category');
				}
				if (!$src && !$thumb_rendered)
				{
					// Render method 'display_NNNN_src' to avoid CSS/JS being added to the page
					$img_field = false;
					if (!empty($img_field_name))
					{
						FlexicontentFields::getFieldDisplay($item, $img_field_name, $values=null, $method='display_'.$img_field_size.'_src', 'category');
						$img_field = isset($item->fields[$img_field_name]) ? $item->fields[$img_field_name] : false;
					}
					$item->image_w = $item->image_h = 0;

					if ($img_field)
					{
						$src = str_replace(JUri::root(), '', ($img_field->thumbs_src[$img_field_size][0] ?? '') );
						if ( $lead_link_image_to && isset($img_field->value[0]) )
						{
							$custom_link = ($v = unserialize($img_field->value[0])) !== false ? @ $v['link'] : ($img_field->value[0]['link'] ?? '');
						}

						$item->image_w = $src ? $img_field->parameters->get('w_'.$img_field_size[0], 120) : 0;
						$item->image_h = $src ? $img_field->parameters->get('h_'.$img_field_size[0], 90) : 0;
					}
					else
					{
						$src = flexicontent_html::extractimagesrc($item);
					}

					if (!$src && $lead_image_fallback_img && $lead_fallback_field)
					{
						$image_url2 = FlexicontentFields::getFieldDisplay($item, $lead_fallback_field, $values=null, $method='display_'.$img_field_size.'_src', 'category');

						if ($image_url2)
						{
							$img_field2 = $item->fields[$lead_fallback_field];

							if ($lead_use_image==1)
							{
								$src = str_replace(JUri::root(), '', ($img_field2->thumbs_src[$img_field_size][0] ?? '') );
							}
							else
							{
								$src = $img_field2->thumbs_src[ $lead_use_image ][0] ?? '';
								$item->image_w = $src ? $img_field2->parameters->get('w_'.$lead_use_image[0], 120) : 0;
								$item->image_h = $src ? $img_field2->parameters->get('h_'.$lead_use_image[0], 90) : 0;
							}
						}
					}
				}
				if(!$src && ($lead_image_fallback_img!=2)) {
					// Use default image form layout parameters
					if (!$src && isset($lead_type_default_imgs[$item->typealias]))  $src = $lead_type_default_imgs[$item->typealias];
					if (!$src && isset($lead_type_default_imgs['_OTHER_']))         $src = $lead_type_default_imgs['_OTHER_'];
				}
				$RESIZE_FLAG = !$this->params->get('lead_image') || !$this->params->get('lead_image_size');
				if ( $src && $RESIZE_FLAG ) {
					// Resize image when src path is set and RESIZE_FLAG: (a) using image extracted from item main text OR (b) not using image field's already created thumbnails
					$w		= '&amp;w=' . $this->params->get('lead_width', 200);
					$h		= '&amp;h=' . $this->params->get('lead_height', 200);
					$aoe	= '&amp;aoe=1';
					$q		= '&amp;q=95';
					$ar 	= '&amp;ar=x';
					$zc		= $this->params->get('lead_method') ? '&amp;zc=' . $this->params->get('lead_method') : '';
					$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
					$f = in_array( $ext, array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico') ) ? '&amp;f='.$ext : '';
					$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;

					$base_url = (!preg_match("#^http|^https|^ftp|^/#i", $src)) ?  JUri::base(true).'/' : '';
					$item->image = JUri::base(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;

					$item->image_w = $this->params->get('lead_width', 200);
					$item->image_h = $this->params->get('lead_height', 200);
				} else {
					// Do not resize image when (a) image src path not set or (b) using image field's already created thumbnails
					$item->image = $src ?: $thumb_rendered;
				}

				// Instead of empty image
				$item->image = $item->image ?: $mod_default_img_path;
			endif;
			$link_url = $custom_link ? $custom_link : JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, 0, $item));
			$title_encoded = htmlspecialchars($item->title, ENT_COMPAT, 'UTF-8');

			// MICRODATA document type (itemtype) for each item
			// -- NOTE: category's microdata itemtype is fallback if the microdata itemtype of the CONTENT TYPE / ITEM are not set
			$microdata_itemtype = $item->params->get( 'microdata_itemtype') ? $item->params->get( 'microdata_itemtype') : $microdata_itemtype_cat;
			$microdata_itemtype_code = 'itemscope itemtype="http://schema.org/'.$microdata_itemtype.'"';
		?>

		<?php echo $lead_catblock ?
			'<div class="lead_catblock">'
				.($lead_catblock_title && @$globalcats[$item->rel_catid] ? $globalcats[$item->rel_catid]->title : '').
			'</div>' : ''; ?>		


			<?php $oe_class = $rowtoggler ? 'odd' : 'even'; ?>

			<?php
				$img_force_dims_css_feat = $img_auto_dims_css_feat;
				if (!empty($item->image) && ($item_img_fit_feat==0/* || $content_layout_feat <= 3*/))
				{
					$img_force_dims_css_feat .= ($item->image_w ? ' max-width:'. $item->image_w.'px; ' : '') . ($item->image_h ? ' max-height:'. $item->image_h.'px; ' : '');
				}

				if ($rowcount%$item_columns_feat==0)
				{
					$oe_class = $oe_class=='odd' ? 'even' : 'odd';
					$rowtoggler = !$rowtoggler;
				}
				$rowcount++;
			?>

			<!-- BOF item -->	
			<div class="fc-item-block-featured-wrapper<?php echo $do_hlight_feat; ?><?php echo ' '.$oe_class . ($cols_class_feat ? ' '.$cols_class_feat : ''); ?>"
				<?php echo $microdata_itemtype_code; ?>
				id="fc_newslist_item_<?php echo $i; ?>"
			>
			<div class="fc-item-block-featured-wrapper-innerbox <?php echo $fc_item_classes; ?>" >

			<article class="group">

				<!-- BOF beforeDisplayContent -->
				<?php if ($item->event->beforeDisplayContent) : ?>
					<aside class="fc_beforeDisplayContent group">
						<?php echo $item->event->beforeDisplayContent; ?>
					</aside>
				<?php endif; ?>
				<!-- EOF beforeDisplayContent -->

				<?php
					$header_shown =
						$this->params->get('show_comments_count', 1) ||
						$this->params->get('show_title', 1) || $item->event->afterDisplayTitle ||
						0; // ...
				?>

				<?php if ( $header_shown ) : ?>
				<header class="group tool">
				<?php endif; ?>

				<?php if ($this->params->get('show_editbutton', 1)) : ?>

					<?php $editbutton = flexicontent_html::editbutton( $item, $this->params ); ?>
					<?php if ($editbutton) : ?>
						<div class="fc_edit_link btn"><?php echo $editbutton;?></div>
					<?php endif; ?>

					<?php $statebutton = flexicontent_html::statebutton( $item, $this->params ); ?>
					<?php if ($statebutton) : ?>
						<div class="fc_state_toggle_link btn"><?php echo $statebutton;?></div>
					<?php endif; ?>

				<?php endif; ?>

				<?php $deletebutton = flexicontent_html::deletebutton( $item, $this->params ); ?>
				<?php if ($deletebutton) : ?>
					<div class="fc_delete_link btn"><?php echo $deletebutton;?></div>
				<?php endif; ?>

				<?php $approvalbutton = flexicontent_html::approvalbutton( $item, $this->params ); ?>
				<?php if ($approvalbutton) : ?>
					<div class="fc_approval_request_link btn"><?php echo $approvalbutton;?></div>
				<?php endif; ?>

				<?php if ($this->params->get('show_comments_count')) : ?>
					<?php if ( isset($this->comments[ $item->id ]->total) ) : ?>
						<div <?php echo $_comments_container_params; ?> >
							<?php echo $this->comments[ $item->id ]->total; ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>

				<?php echo $markup_tags; ?>

				<?php if ( $header_shown ) : ?>
				</header>
				<?php endif; ?>

				<!-- BOF item title -->
				<?php ob_start(); ?>

					<?php if ($this->params->get('show_title', 1)) : ?>
						<span class="fcitem_title_box">
							<h2 class="fcitem_title" itemprop="name">
							<?php if ($this->params->get('link_titles', 0)) : ?>
								<a href="<?php echo $link_url; ?>"><?php echo $item->title; ?></a>
							<?php else : ?>
								<?php echo $item->title; ?>
							<?php endif; ?>
							</h2>
						</span>
					<?php endif; ?>


					<?php if ($item->event->afterDisplayTitle) : ?>
					<!-- BOF afterDisplayTitle -->
						<div class="fc_afterDisplayTitle group">
							<?php echo $item->event->afterDisplayTitle; ?>
						</div>
					<!-- EOF afterDisplayTitle -->
					<?php endif; ?>

				<?php $captured_title = ob_get_clean(); ?>
				<!-- EOF item title -->


				<!-- BOF item's image -->	
				<?php ob_start(); ?>

					<?php if (!empty($item->image_rendered)) : ?>

						<figure class="image_featured <?php echo $img_container_class_feat;?>">
							<?php if ($lead_link_image) : ?>
								<a href="<?php echo $link_url; ?>">
									<?php echo $item->image_rendered; ?>
								</a>
							<?php else : ?>
								<?php echo $item->image_rendered; ?>
							<?php endif; ?>
						</figure>

					<?php elseif (!empty($item->image)) : ?>

						<figure class="image_featured <?php echo $img_container_class_feat;?>">
							<?php if ($lead_link_image) : ?>
								<a href="<?php echo $link_url; ?>">
									<img style="<?php echo $img_force_dims_css_feat; ?>" src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($title_encoded, 60); ?> <?php echo $lazy_loading; ?>" />
								</a>
							<?php else : ?>
								<img style="<?php echo $img_force_dims_css_feat; ?>" src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($title_encoded, 60); ?> <?php echo $lazy_loading; ?>" />
							<?php endif; ?>
						</figure>

					<?php endif; ?>

				<?php $captured_image = ob_get_clean(); $hasImage = (boolean) trim($captured_image); ?>
				<!-- EOF item's image -->

				<?php echo $content_layout_feat!=2 ? $captured_image : '';?>


				<!-- BOF item's content -->
				<div class="content_featured <?php echo $content_container_class_feat;?>">

					<?php echo $captured_title; ?>


					<!-- BOF above-description-line1 block -->
					<?php if (isset($item->positions['above-description-line1'])) : ?>
					<div class="lineinfo line1">
						<?php foreach ($item->positions['above-description-line1'] as $field) : ?>
						<div class="element field_<?php echo $field->name; ?>">
							<?php if ($field->label) : ?>
							<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
							<?php endif; ?>
							<div class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
					<!-- EOF above-description-line1 block -->

					<!-- BOF above-description-nolabel-line1 block -->
					<?php if (isset($item->positions['above-description-line1-nolabel'])) : ?>
					<div class="lineinfo line1">
						<?php foreach ($item->positions['above-description-line1-nolabel'] as $field) : ?>
						<div class="element field_<?php echo $field->name; ?>">
							<div class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
					<!-- EOF above-description-nolabel-line1 block -->

					<!-- BOF above-description-line2 block -->
					<?php if (isset($item->positions['above-description-line2'])) : ?>
					<div class="lineinfo line2">
						<?php foreach ($item->positions['above-description-line2'] as $field) : ?>
						<div class="element field_<?php echo $field->name; ?>">
							<?php if ($field->label) : ?>
							<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
							<?php endif; ?>
							<div class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
					<!-- EOF above-description-line2 block -->

					<!-- BOF above-description-nolabel-line2 block -->
					<?php if (isset($item->positions['above-description-line2-nolabel'])) : ?>
					<div class="lineinfo line2">
						<?php foreach ($item->positions['above-description-line2-nolabel'] as $field) : ?>
						<div class="element field_<?php echo $field->name; ?>">
							<div class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
					<!-- EOF above-description-nolabel-line2 block -->


					<?php if ($lead_use_description) : ?>
					<div class="fc_block fcitem_text">
						<?php
						$desc_text = $this->params->get('lead_strip_html', 1)
							? flexicontent_html::striptagsandcut( $item->fields['text']->display, $lead_cut_text, $uncut_length )
							: $item->fields['text']->display;
						echo strlen($desc_text) ? '<p>' . $desc_text . '</p>' : '';
						?>
					</div>
					<?php endif; ?>


					<!-- BOF under-description-line1 block -->
					<?php if (isset($item->positions['under-description-line1'])) : ?>
					<div class="lineinfo line3">
						<?php foreach ($item->positions['under-description-line1'] as $field) : ?>
						<div class="element field_<?php echo $field->name; ?>">
							<?php if ($field->label) : ?>
							<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
							<?php endif; ?>
							<div class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
					<!-- EOF under-description-line1 block -->

					<!-- BOF under-description-line1-nolabel block -->
					<?php if (isset($item->positions['under-description-line1-nolabel'])) : ?>
					<div class="lineinfo line3">
						<?php foreach ($item->positions['under-description-line1-nolabel'] as $field) : ?>
						<div class="element field_<?php echo $field->name; ?>">
							<div class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
					<!-- EOF under-description-line1-nolabel block -->

					<!-- BOF under-description-line2 block -->
					<?php if (isset($item->positions['under-description-line2'])) : ?>
					<div class="lineinfo line4">
						<?php foreach ($item->positions['under-description-line2'] as $field) : ?>
						<div class="element field_<?php echo $field->name; ?>">
							<?php if ($field->label) : ?>
							<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
							<?php endif; ?>
							<div class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
					<!-- EOF under-description-line2 block -->

					<!-- BOF under-description-line2-nolabel block -->
					<?php if (isset($item->positions['under-description-line2-nolabel'])) : ?>
					<div class="lineinfo line4">
						<?php foreach ($item->positions['under-description-line2-nolabel'] as $field) : ?>
						<div class="element field_<?php echo $field->name; ?>">
							<div class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
					<!-- EOF under-description-line2-nolabel block -->


					<?php
					$readmore_forced = $this->params->get('show_readmore', 1) == -1 || $this->params->get('lead_strip_html', 1) == 1 ;
					$readmore_shown  = $this->params->get('show_readmore', 1) && ($uncut_length > $lead_cut_text || strlen(trim($item->fulltext)) >= 1);
					$readmore_shown  = $readmore_shown || $readmore_forced;
					$footer_shown = $readmore_shown || $item->event->afterDisplayContent;

					if ($lead_link_to_popup) $_tmpl_ = (strstr($link_url, '?') ? '&' : '?'). 'tmpl=component';
					?>

					<?php if ( $footer_shown ) : ?>
					<footer class="fc_block">
					<?php endif; ?>

					<?php if ($readmore_shown) : ?>
						<div class="fcitem_readon readmore">
							<a href="<?php echo $link_url; ?>" class="<?php echo $readon_class; ?>" itemprop="url" <?php echo ($lead_link_to_popup ? 'onclick="var url = jQuery(this).attr(\'href\')+\''.$_tmpl_.'\'; fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, 0, {title: \'\'}); return false;"' : '');?> >
								<?php
								$read_more_text = $item->params->get('readmore')  ?  $item->params->get('readmore') : JText::sprintf('FLEXI_READ_MORE', $item->title);
								echo $readon_type === 1
									? '<img src="' . $readon_image . '" alt="' . JText::sprintf('FLEXI_READ_MORE', $item->title) . '" />'
									: '<span class="icon-chevron-right"></span> ' . $read_more_text;
								?>
							</a>
						</div>
					<?php endif; ?>

					<!-- BOF afterDisplayContent -->
					<?php if ($item->event->afterDisplayContent) : ?>
						<aside class="fc_afterDisplayContent group">
							<?php echo $item->event->afterDisplayContent; ?>
						</aside>
					<?php endif; ?>
					<!-- EOF afterDisplayContent -->

					<?php if ( $footer_shown ) : ?>
					</footer>
					<?php endif; ?>

					<div class="clearfix"></div> 

				</div> <!-- EOF item's content -->

				<?php echo $content_layout_feat==2 ? $captured_image : '';?>

				</article>
			</div>  <!-- EOF wrapper-innerbox -->
			</div>  <!-- EOF wrapper -->
			<!-- EOF item -->

			<?php if ($item_placement_feat==0) /* 0: clear, 1: as masonry tiles */ echo !($rowcount%$item_columns_feat) ? '<div class="clearfix"></div>' : ''; ?>

		<?php endfor; ?>

	</div>

	<!-- EOF DIV featured-block (featured items) -->


<?php endif; ?>



<?php
if ($this->limitstart != 0) $leadnum = 0;
if ($count > $leadnum) :
	//added to intercept more columns (see also css changes)
	$intro_cols = $this->params->get('intro_cols', 2);
	$intro_cols_classes = array(1=>'one',2=>'two',3=>'three',4=>'four');
	$classnum = $intro_cols_classes[$intro_cols];

	// bootstrap span
	$intro_cols_spanclasses = array(1=>'span12',2=>'span6',3=>'span4',4=>'span3');
	$classspan = $intro_cols_spanclasses[$intro_cols];
?>


	<!-- BOF DIV standard-block (standard items) -->

	<div class="standard-block news fc-items-block <?php echo $classnum; ?> group row">

		<?php
		if ($intro_use_image && $this->params->get('intro_image'))
		{
			$img_size_map   = array('l'=>'large', 'm'=>'medium', 's'=>'small', 'o'=>'original');
			$img_field_size = $img_size_map[ $this->params->get('intro_image_size' , 'l') ];
			$img_field_name = $this->params->get('intro_image');
		}

		$intro_fallback_field = $params->get('intro_fallback_field', 0);
		$intro_image_fallback_img = $params->get('intro_image_fallback_img');
		$intro_image_custom_display	= $params->get('intro_image_custom_url');
		$intro_image_custom_url	= $params->get('intro_image_custom_url');

		$intro_dimgs = $this->params->get('intro_default_images');
		if ($intro_use_image && $intro_dimgs) {
			$intro_dimgs = preg_split("/[\s]*,[\s]*/", $intro_dimgs);
			$intro_type_default_imgs = array();
			foreach ($intro_dimgs as $_image) {
				$_d = preg_split("/[\s]*##[\s]*/", $_image);
				$_type_alias  = empty($_d[1]) ? '_OTHER_' : $_d[0];
				$_type_dimage = empty($_d[1]) ? $_d[0] : $_d[1];
				$intro_type_default_imgs[$_type_alias] = $_type_dimage;
			}
		}


		$rowcount = 0;
		for ($i = $leadnum; $i < $count; $i++) :
			$item = $items[$i];
			$src = '';
			$thumb_rendered = '';
			$fc_item_classes = 'fc_newslist_item';
			if ($doing_cat_order)
     		$fc_item_classes .= ($i==0 || ($items[$i-1]->rel_catid != $items[$i]->rel_catid) ? ' fc_cat_item_1st' : '');
			$fc_item_classes .= ' '.$classspan;
			$fc_item_classes .= ' fccol'.($i%$intro_cols + 1);

			$markup_tags = '<span class="fc_mublock">';
			foreach($item->css_markups as $grp => $css_markups) {
				if ( empty($css_markups) )  continue;
				$fc_item_classes .= ' fc'.implode(' fc', $css_markups);

				$ecss_markups  = $item->ecss_markups[$grp];
				$title_markups = $item->title_markups[$grp];
				foreach($css_markups as $mui => $css_markup) {
					$markup_tags .= '<span class="fc_markup mu' . $css_markups[$mui] . $ecss_markups[$mui] .'">' .$title_markups[$mui]. '</span>';
				}
			}
			$markup_tags .= '</span>';

			$custom_link = null;
			if ($intro_use_image) :
				if ($intro_image_custom_display)
				{
					@list($fieldname, $varname) = preg_split('/##/',$intro_image_custom_display);
					$fieldname = trim($fieldname); $varname = trim($varname);
					$varname = $varname ? $varname : 'display';
					$thumb_rendered = FlexicontentFields::getFieldDisplay($item, $fieldname, null, $varname, 'category');
					$src = '';  // Clear src no rendering needed
					$item->image_w = $item->image_h = 0;
				}
				if (!$src && !$thumb_rendered && $intro_image_custom_url)
				{
					@list($fieldname, $varname) = preg_split('/##/',$intro_image_custom_url);
					$fieldname = trim($fieldname); $varname = trim($varname);
					$varname = $varname ? $varname : 'display';
					$src =  FlexicontentFields::getFieldDisplay($item, $fieldname, null, $varname, 'category');
				}
				if (!$src && !$thumb_rendered)
				{
					// Render method 'display_NNNN_src' to avoid CSS/JS being added to the page
					$img_field = false;
					if (!empty($img_field_name))
					{
						FlexicontentFields::getFieldDisplay($item, $img_field_name, $values=null, $method='display_'.$img_field_size.'_src', 'category');
						$img_field = isset($item->fields[$img_field_name]) ? $item->fields[$img_field_name] : false;
					}
					$item->image_w = $item->image_h = 0;

					if ($img_field)
					{
						$src = str_replace(JUri::root(), '', ($img_field->thumbs_src[$img_field_size][0] ?? '') );
						if ( $intro_link_image_to && isset($img_field->value[0]) )
						{
							$custom_link = ($v = unserialize($img_field->value[0])) !== false ? @ $v['link'] : ($img_field->value[0]['link'] ?? '');
						}

						$item->image_w = $src ? $img_field->parameters->get('w_'.$img_field_size[0], 120) : 0;
						$item->image_h = $src ? $img_field->parameters->get('h_'.$img_field_size[0], 90) : 0;
					}
					else
					{
						$src = flexicontent_html::extractimagesrc($item);
					}

					if (!$src && $intro_image_fallback_img && $intro_fallback_field)
					{
						$image_url2 = FlexicontentFields::getFieldDisplay($item, $intro_fallback_field, $values=null, $method='display_'.$img_field_size.'_src', 'category');

						if ($image_url2)
						{
							$img_field2 = $item->fields[$intro_fallback_field];

							if ($intro_use_image==1)
							{
								$src = str_replace(JUri::root(), '', ($img_field2->thumbs_src[$img_field_size][0] ?? '') );
							}
							else
							{
								$src = $img_field2->thumbs_src[ $intro_use_image ][0] ?? '';
								$item->image_w = $src ? $img_field2->parameters->get('w_'.$intro_use_image[0], 120) : 0;
								$item->image_h = $src ? $img_field2->parameters->get('h_'.$intro_use_image[0], 90) : 0;
							}
						}
					}
				}
				if(!$src && ($intro_image_fallback_img!=2)) {
					// Use default image form layout parameters
					if (!$src && isset($intro_type_default_imgs[$item->typealias]))  $src = $intro_type_default_imgs[$item->typealias];
					if (!$src && isset($intro_type_default_imgs['_OTHER_']))         $src = $intro_type_default_imgs['_OTHER_'];
				}
				$RESIZE_FLAG = !$this->params->get('intro_image') || !$this->params->get('intro_image_size');
				if ( $src && $RESIZE_FLAG ) {
					// Resize image when src path is set and RESIZE_FLAG: (a) using image extracted from item main text OR (b) not using image field's already created thumbnails
					$w		= '&amp;w=' . $this->params->get('intro_width', 200);
					$h		= '&amp;h=' . $this->params->get('intro_height', 200);
					$aoe	= '&amp;aoe=1';
					$q		= '&amp;q=95';
					$zc		= $this->params->get('intro_method') ? '&amp;zc=' . $this->params->get('intro_method') : '';
					$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
					$f = in_array( $ext, array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico') ) ? '&amp;f='.$ext : '';
					$conf	= $w . $h . $aoe . $q . $zc . $f;

					$base_url = (!preg_match("#^http|^https|^ftp|^/#i", $src)) ?  JUri::base(true).'/' : '';
					$item->image = JUri::base(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;

					$item->image_w = $this->params->get('intro_width', 200);
					$item->image_h = $this->params->get('intro_height', 200);
				} else {
					// Do not resize image when (a) image src path not set or (b) using image field's already created thumbnails
					$item->image = $src ?: $thumb_rendered;
				}

				// Instead of empty image
				$item->image = $item->image ?: $mod_default_img_path;
			endif;
			$link_url = $custom_link ? $custom_link : JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, 0, $item));
			$title_encoded = htmlspecialchars($item->title, ENT_COMPAT, 'UTF-8');

			// MICRODATA document type (itemtype) for each item
			// -- NOTE: category's microdata itemtype is fallback if the microdata itemtype of the CONTENT TYPE / ITEM are not set
			$microdata_itemtype = $item->params->get( 'microdata_itemtype') ? $item->params->get( 'microdata_itemtype') : $microdata_itemtype_cat;
			$microdata_itemtype_code = 'itemscope itemtype="http://schema.org/'.$microdata_itemtype.'"';
		?>

		<?php echo $intro_catblock ?
			'<div class="intro_catblock">'
				.($intro_catblock_title && @$globalcats[$item->rel_catid] ? $globalcats[$item->rel_catid]->title : '').
			'</div>' : ''; ?>


			<?php $oe_class = $rowtoggler ? 'odd' : 'even'; $n=-1; ?>

			<?php
				$img_force_dims_css = $img_auto_dims_css;
				if (!empty($item->image) && ($item_img_fit==0/* || $content_layout <= 3*/))
				{
					$img_force_dims_css .= ($item->image_w ? ' max-width:'. $item->image_w.'px; ' : '') . ($item->image_h ? ' max-height:'. $item->image_h.'px; ' : '');
				}

				if ($rowcount%$item_columns_std==0)
				{
					$oe_class = $oe_class=='odd' ? 'even' : 'odd';
					$rowtoggler = !$rowtoggler;
				}
				$rowcount++;
				$n++;
			?>

			<!-- BOF item -->	
			<div class="fc-item-block-standard-wrapper<?php echo $do_hlight; ?><?php echo ' '.$oe_class . ($cols_class_std ? ' '.$cols_class_std : ''); ?>"
				<?php echo $microdata_itemtype_code; ?>
				id="fc_newslist_item_<?php echo $i; ?>"
			>
			<div class="fc-item-block-standard-wrapper-innerbox <?php echo $fc_item_classes; ?>" >

			<article class="group">

				<!-- BOF beforeDisplayContent -->
				<?php if ($item->event->beforeDisplayContent) : ?>
					<aside class="fc_beforeDisplayContent group">
						<?php echo $item->event->beforeDisplayContent; ?>
					</aside>
				<?php endif; ?>
				<!-- EOF beforeDisplayContent -->

				<?php
					$header_shown =
						$this->params->get('show_comments_count', 1) ||
						$this->params->get('show_title', 1) || $item->event->afterDisplayTitle ||
						0; // ...
				?>

				<?php if ( $header_shown ) : ?>
				<header class="group tool">
				<?php endif; ?>

				<?php if ($this->params->get('show_editbutton', 1)) : ?>

					<?php $editbutton = flexicontent_html::editbutton( $item, $this->params ); ?>
					<?php if ($editbutton) : ?>
						<div class="fc_edit_link btn"><?php echo $editbutton;?></div>
					<?php endif; ?>

					<?php $statebutton = flexicontent_html::statebutton( $item, $this->params ); ?>
					<?php if ($statebutton) : ?>
						<div class="fc_state_toggle_link btn"><?php echo $statebutton;?></div>
					<?php endif; ?>

				<?php endif; ?>

				<?php $deletebutton = flexicontent_html::deletebutton( $item, $this->params ); ?>
				<?php if ($deletebutton) : ?>
					<div class="fc_delete_link btn"><?php echo $deletebutton;?></div>
				<?php endif; ?>

				<?php $approvalbutton = flexicontent_html::approvalbutton( $item, $this->params ); ?>
				<?php if ($approvalbutton) : ?>
					<div class="fc_approval_request_link btn"><?php echo $approvalbutton;?></div>
				<?php endif; ?>

				<?php if ($this->params->get('show_comments_count')) : ?>
					<?php if ( isset($this->comments[ $item->id ]->total) ) : ?>
						<div <?php echo $_comments_container_params; ?> >
							<?php echo $this->comments[ $item->id ]->total; ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>

				<?php echo $markup_tags; ?>

				<?php if ( $header_shown ) : ?>
				</header>
				<?php endif; ?>

				<!-- BOF item title -->
				<?php ob_start(); ?>

					<?php if ($this->params->get('show_title', 1)) : ?>
						<span class="fcitem_title_box">
							<h2 class="fcitem_title" itemprop="name">
							<?php if ($this->params->get('link_titles', 0)) : ?>
								<a href="<?php echo $link_url; ?>"><?php echo $item->title; ?></a>
							<?php else : ?>
								<?php echo $item->title; ?>
							<?php endif; ?>
							</h2>
						</span>
					<?php endif; ?>


					<?php if ($item->event->afterDisplayTitle) : ?>
					<!-- BOF afterDisplayTitle -->
						<div class="fc_afterDisplayTitle group">
							<?php echo $item->event->afterDisplayTitle; ?>
						</div>
					<!-- EOF afterDisplayTitle -->
					<?php endif; ?>

				<?php $captured_title = ob_get_clean(); ?>
				<!-- EOF item title -->


				<!-- BOF item's image -->
				<?php ob_start(); ?>

					<?php if (!empty($item->image_rendered)) : ?>

						<figure class="image_standard <?php echo $img_container_class;?>">
							<?php if ($intro_link_image) : ?>
								<a href="<?php echo $link_url; ?>">
									<?php echo $item->image_rendered; ?>
								</a>
							<?php else : ?>
								<?php echo $item->image_rendered; ?>
							<?php endif; ?>
						</figure>


					<?php elseif (!empty($item->image)) : ?>

						<figure class="image_standard <?php echo $img_container_class;?>">
							<?php if ($intro_link_image) : ?>
								<a href="<?php echo $link_url; ?>">
									<img style="<?php echo $img_force_dims_css; ?>" src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($title_encoded, 60); ?>" <?php echo $lazy_loading; ?> />
								</a>
							<?php else : ?>
								<img style="<?php echo $img_force_dims_css; ?>" src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($title_encoded, 60); ?>" <?php echo $lazy_loading; ?> />
							<?php endif; ?>
						</figure>

					<?php endif; ?>

				<?php $captured_image = ob_get_clean(); $hasImage = (boolean) trim($captured_image); ?>
				<!-- EOF item's image -->

				<?php echo $content_layout!=2 ? $captured_image : '';?>

				<!-- BOF item's content -->
				<div class="content_standard <?php echo $content_container_class;?>">

					<?php echo $captured_title; ?>


					<!-- BOF above-description-line1 block -->
					<?php if (isset($item->positions['above-description-line1'])) : ?>
					<div class="lineinfo line1">
						<?php foreach ($item->positions['above-description-line1'] as $field) : ?>
						<div class="element field_<?php echo $field->name; ?>">
							<?php if ($field->label) : ?>
							<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
							<?php endif; ?>
							<div class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
					<!-- EOF above-description-line1 block -->

					<!-- BOF above-description-nolabel-line1 block -->
					<?php if (isset($item->positions['above-description-line1-nolabel'])) : ?>
					<div class="lineinfo line1">
						<?php foreach ($item->positions['above-description-line1-nolabel'] as $field) : ?>
						<div class="element field_<?php echo $field->name; ?>">
							<div class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
					<!-- EOF above-description-nolabel-line1 block -->

					<!-- BOF above-description-line2 block -->
					<?php if (isset($item->positions['above-description-line2'])) : ?>
					<div class="lineinfo line2">
						<?php foreach ($item->positions['above-description-line2'] as $field) : ?>
						<div class="element field_<?php echo $field->name; ?>">
							<?php if ($field->label) : ?>
							<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
							<?php endif; ?>
							<div class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
					<!-- EOF above-description-line2 block -->

					<!-- BOF above-description-nolabel-line2 block -->
					<?php if (isset($item->positions['above-description-line2-nolabel'])) : ?>
					<div class="lineinfo line2">
						<?php foreach ($item->positions['above-description-line2-nolabel'] as $field) : ?>
						<div class="element field_<?php echo $field->name; ?>">
							<div class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
					<!-- EOF above-description-nolabel-line2 block -->


					<?php if ($intro_use_description) : ?>
					<div class="fc_block fcitem_text">
						<?php
						$desc_text = $this->params->get('intro_strip_html', 1)
							? flexicontent_html::striptagsandcut( $item->fields['text']->display, $intro_cut_text, $uncut_length )
							: $item->fields['text']->display;
						echo strlen($desc_text) ? '<p>' . $desc_text . '</p>' : '';
						?>
					</div>
					<?php endif; ?>

					<!-- BOF under-description-line1 block -->
					<?php if (isset($item->positions['under-description-line1'])) : ?>
					<div class="lineinfo line3">
						<?php foreach ($item->positions['under-description-line1'] as $field) : ?>
						<div class="element field_<?php echo $field->name; ?>">
							<?php if ($field->label) : ?>
							<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
							<?php endif; ?>
							<div class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
					<!-- EOF under-description-line1 block -->

					<!-- BOF under-description-line1-nolabel block -->
					<?php if (isset($item->positions['under-description-line1-nolabel'])) : ?>
					<div class="lineinfo line3">
						<?php foreach ($item->positions['under-description-line1-nolabel'] as $field) : ?>
						<div class="element field_<?php echo $field->name; ?>">
							<div class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
					<!-- EOF under-description-line1-nolabel block -->

					<!-- BOF under-description-line2 block -->
					<?php if (isset($item->positions['under-description-line2'])) : ?>
					<div class="lineinfo line4">
						<?php foreach ($item->positions['under-description-line2'] as $field) : ?>
						<div class="element field_<?php echo $field->name; ?>">
							<?php if ($field->label) : ?>
							<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
							<?php endif; ?>
							<div class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
					<!-- EOF under-description-line2 block -->

					<!-- BOF under-description-line2-nolabel block -->
					<?php if (isset($item->positions['under-description-line2-nolabel'])) : ?>
					<div class="lineinfo line4">
						<?php foreach ($item->positions['under-description-line2-nolabel'] as $field) : ?>
						<div class="element field_<?php echo $field->name; ?>">
							<div class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
					<!-- EOF under-description-line2-nolabel block -->


					<?php
					$readmore_forced = $this->params->get('show_readmore', 1) == -1 || $this->params->get('intro_strip_html', 1) == 1 ;
					$readmore_shown  = $this->params->get('show_readmore', 1) && ($uncut_length > $intro_cut_text || strlen(trim($item->fulltext)) >= 1);
					$readmore_shown  = $readmore_shown || $readmore_forced;
					$footer_shown = $readmore_shown || $item->event->afterDisplayContent;

					if ($intro_link_to_popup) $_tmpl_ = (strstr($link_url, '?') ? '&' : '?'). 'tmpl=component';
					?>

					<?php if ( $footer_shown ) : ?>
					<footer class="fc_block">
					<?php endif; ?>

					<?php if ($readmore_shown) : ?>
						<div class="fcitem_readon readmore">
							<a href="<?php echo $link_url; ?>" class="<?php echo $readon_class; ?>" itemprop="url" <?php echo ($intro_link_to_popup ? 'onclick="var url = jQuery(this).attr(\'href\')+\''.$_tmpl_.'\'; fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, 0, {title: \'\'}); return false;"' : '');?> >
								<?php
								$read_more_text = $item->params->get('readmore')  ?  $item->params->get('readmore') : JText::sprintf('FLEXI_READ_MORE', $item->title);
								echo $readon_type === 1
									? '<img src="' . $readon_image . '" alt="' . JText::sprintf('FLEXI_READ_MORE', $item->title) . '" />'
									: '<span class="icon-chevron-right"></span> ' . $read_more_text;
								?>
							</a>
						</div>
					<?php endif; ?>

					<!-- BOF afterDisplayContent -->
					<?php if ($item->event->afterDisplayContent) : ?>
						<aside class="fc_afterDisplayContent group">
							<?php echo $item->event->afterDisplayContent; ?>
						</aside>
					<?php endif; ?>
					<!-- EOF afterDisplayContent -->

					<?php if ( $footer_shown ) : ?>
					</footer>
					<?php endif; ?>

					<div class="clearfix"></div> 

				</div> <!-- EOF item's content -->

				<?php echo $content_layout==2 ? $captured_image : '';?>

				</article>
			</div>  <!-- EOF wrapper-innerbox -->
			</div>  <!-- EOF wrapper -->
			<!-- EOF item -->

			<?php if ($item_placement_std==0) /* 0: clear, 1: as masonry tiles */ echo !($rowcount%$item_columns_std) ? '<div class="clearfix"></div>' : ''; ?>

		<?php endfor; ?>

	</div>

	<!-- EOF DIV standard-block (standard items) -->


	<?php endif; ?>

</div>
<div class="fcclear"></div>


	<?php
	// We need this inside the loop since ... we may have multiple orderings thus we may
	// have multiple container (1 item list container per order) being effected by JS
	$js = ''
		;
	if ($js) $document->addScriptDeclaration($js);

	// ***********************************************************
	// Module specific styling (we use names containing module ID)
	// ***********************************************************

	$css = '';

	if ($css) $document->addStyleDeclaration($css);

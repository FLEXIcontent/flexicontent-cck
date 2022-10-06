<?php
defined('_JEXEC') or die('Restricted access');

if (!count($this->results))
{
	if ($this->searchword || count($this->filter_values)) :	
	?>
		<div class="fcclear"></div>
		<div class="alert alert-warning noitems_search"> <?php echo JText::_( 'FLEXI_SEARCH_NO_ITEMS_FOUND' ); ?> </div>
		<div class="fcclear"></div>
	<?php
	endif;
	
	return;
}


// **************************************************
// Indentify result ITEMs that are FLEXIcontent items
// **************************************************
$fcitems = array();
foreach ($this->results as $i => $result)
{
	if ( ! @$result->fc_item_id ) continue;
	$fcitems[$i] = JTable::getInstance('flexicontent_items', '');
	$fcitems[$i]->load($result->fc_item_id);
	$fcitems[$i]->category_access = $result->category_access;
	$fcitems[$i]->type_access = $result->type_access ;
	$fcitems[$i]->has_access  = $result->has_access;
	$fcitems[$i]->categories = $result->categories;
}


// ************************************************************************
// Calculate CSS classes needed to add special styling markups to the items
// ************************************************************************
flexicontent_html::calculateItemMarkups($fcitems, $this->params);


// *********************************************************
// Get Original content ids for creating some untranslatable
// fields that have share data (like shared folders)
// *********************************************************
flexicontent_db::getOriginalContentItemids($fcitems);


// *****************************************************
// Get image configuration for FLEXIcontent result items
// *****************************************************
$fcr_use_image = $this->params->get('fcr_use_image', 1);
$fcr_image = $this->params->get('fcr_image');

if ($fcr_use_image && $fcr_image) {
	$img_size_map   = array('l'=>'large', 'm'=>'medium', 's'=>'small');
	$img_field_size = $img_size_map[ $this->params->get('fcr_image_size' , 'l') ];
	$img_field_name = $this->params->get('fcr_image');
}


// *******************************************************************
// Get custom displayed fields to add to each FLEXIcontent result item
// *******************************************************************
$use_infoflds = (int)$this->params->get('use_infoflds', 1);
$infoflds = $this->params->get('infoflds');
$infoflds = preg_replace("/[\"'\\\]/u", "", $infoflds);
$infoflds = preg_split("/\s*,\s*/u", $infoflds);
if ( !strlen($infoflds[0]) ) unset($infoflds[0]);
$infoflds = array_keys(array_flip($infoflds));  // array_flip to get unique filter ids as KEYS (due to flipping) ... and then array_keys to get filter_ids in 0,1,2, ... array

if ( $use_infoflds && count($infoflds) ) {
	foreach ($infoflds as $fieldname) {
		FlexicontentFields::getFieldDisplay($fcitems, $fieldname, $values=null, $method='display');
	}
}

$form_placement = (int) $this->params->get('form_placement', 0);

if ($form_placement)
{
	$results_placement_class = 'span8 col-md-8';
	$results_placement_style = $form_placement === 1 ? 'float: right; margin: 0;' : 'float: left; margin: 0;';
}
else
{
	$results_placement_class = '';
	$results_placement_style = '';
}

?>

<div
	class="fc_search_results_list <?php echo $results_placement_class; ?> page<?php echo $this->escape($this->params->get('pageclass_sfx')); ?>"
	style="<?php echo $results_placement_style; ?>"
>

<?php $count = -1; ?>
<?php foreach($this->results as $i => $result) : ?>
	<div class="fcclear"></div>
	<?php
		$count++;
		$fc_item_classes = 'fc_search_result '.($count%2 ? 'fcodd' : 'fceven');
		if ( @ $result->fc_item_id ) { // FLEXIcontent specific result
			foreach ($result->categories as $item_cat) {
				$fc_item_classes .= ' fc_itemcat_'.$item_cat->id;
			}
			$fc_item_classes .= $result->has_access ? ' fc_item_has_access' : ' fc_item_no_access';
			
			$markup_tags = '<span class="fc_mublock">';
			$item = $fcitems[$i];
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
		}
		
		$item_link = JRoute::_($result->href);
	?>
	<fieldset id="searchlist_item_<?php echo $i; ?>" class="<?php echo $fc_item_classes; ?>">
	 <div class="search-results<?php echo $this->pageclass_sfx; ?>">
	 	
		<h2 class="fc_search_result_title">
			<?php echo $this->pageNav->limitstart + $result->count.'. ';?>
			<?php if ($result->href) :?>
				<a href="<?php echo $item_link; ?>"<?php if ($result->browsernav == 1) :?> target="_blank"<?php endif;?>>
					<?php echo $this->escape($result->title);?>
				</a>
			<?php else:?>
				<?php echo $this->escape($result->title);?>
			<?php endif; ?>
		</h2>
		
		
		<?php if ( @ $result->fc_item_id ) echo $markup_tags; ?>
		<div class="fcclear"></div>
		
		
		<?php if ($this->params->get('show_date', 1)) : ?>
		<span class="fc_search_result_date">
			<span class="fc-mssg-inline fc-success fc-nobgimage">
				<?php echo $result->created; ?>
			</span>
		</span>
		<?php endif; ?>
		
		
		
		<?php if ( $this->params->get( 'show_section', 1 ) && $result->section ) : ?>
		<span class="fc_search_result_category">
			<span class="fc-mssg-inline fc-info fc-nobgimage">
				<?php echo $this->escape($result->section); ?>
			</span>
		</span>
		<?php endif; ?>
		
		
		
		<?php if ( $fcr_use_image && @ $result->fc_item_id ) : // FLEXIcontent specific result ?>
			
			<?php
			$src = $thumb = '';
			if (!empty($img_field_name)) :
				FlexicontentFields::getFieldDisplay($fcitems[$i], $img_field_name, $values=null, $method='display');
				$img_field = & $fcitems[$i]->fields[$img_field_name];
				$src = str_replace(JUri::root(), '', ($img_field->thumbs_src[$img_field_size][0] ?? '') );
			else :
				$src = flexicontent_html::extractimagesrc($fcitems[$i]);
			endif;
			
			$RESIZE_FLAG = !$this->params->get('fcr_image') || !$this->params->get('fcr_image_size');
			if ( $src && $RESIZE_FLAG ) {
				// Resize image when src path is set and RESIZE_FLAG: (a) using image extracted from item main text OR (b) not using image field's already created thumbnails
				$w		= '&amp;w=' . $this->params->get('fcr_width', 200);
				$h		= '&amp;h=' . $this->params->get('fcr_height', 200);
				$aoe	= '&amp;aoe=1';
				$q		= '&amp;q=95';
				$ar 	= '&amp;ar=x';
				$zc		= $this->params->get('fcr_method') ? '&amp;zc=' . $this->params->get('fcr_method') : '';
				$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
				$f = in_array( $ext, array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico') ) ? '&amp;f='.$ext : '';
				$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;
				
				$base_url = (!preg_match("#^http|^https|^ftp|^/#i", $src)) ?  JUri::base(true).'/' : '';
				$thumb = JUri::base(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
			} else {
				// Do not resize image when (a) image src path not set or (b) using image field's already created thumbnails
				$thumb = $src;
			}
			?>
		
			<?php if ($src) : ?>
			<div class="fc_search_result_image <?php echo $this->params->get('fcr_position') ? ' fcright' : ' fcleft'; ?>">
				
				<?php $title_encoded = htmlspecialchars($result->title, ENT_COMPAT, 'UTF-8'); ?>
				<?php if ($this->params->get('fcr_link_image', 1)) : ?>
					<a href="<?php echo $item_link; ?>" >
						<img src="<?php echo $thumb; ?>" alt="<?php echo $title_encoded; ?>" class="<?php echo $tooltip_class;?>" title="<?php echo flexicontent_html::getToolTip($_read_more_about, $title_encoded, 0, 0); ?>"/>
					</a>
				<?php else : ?>
					<img src="<?php echo $thumb; ?>" alt="<?php echo $title_encoded; ?>" title="<?php echo $title_encoded; ?>" />
				<?php endif; ?>
				
			</div>
			<?php endif; ?>
			
		<?php endif; // End of fcr_use_image ?>
		
		
		
		<?php if ( $this->params->get( 'show_text', 1 )) : ?>
		<div class="fc_search_result_text">
			<?php echo $result->text; ?>
		</div>
		<?php endif;?>
		
		
		
		<?php if ( count($infoflds) ) : ?>
		<div class="fc_search_result_fields">
	
		<?php foreach ($infoflds as $fieldname) : ?>
			<?php if ( @$fcitems[$i]->fields[$fieldname]->display ) : ?>
			<span class="fc_search_field_container">
				<span class="fc_search_field_label label">
					<?php echo $fcitems[$i]->fields[$fieldname]->label; ?>
				</span>
				<span class="fc_search_field_value">
					<?php echo $fcitems[$i]->fields[$fieldname]->display; ?>
				</span>
			</span>
			<?php endif; ?>
		<?php endforeach; ?>
			
		</div>
		<?php endif; // $infoflds ?>
		
		
		
	 </div>
	</fieldset>
	
	<?php endforeach; ?>

</div>
<div class="fcclear"></div>


<!-- BOF pagination (After Results) -->
<?php
	// If customizing via CSS rules or JS scripts is not enough, then please copy the following file here to customize the HTML too
	include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'pagination.php');
?>
<!-- EOF pagination -->


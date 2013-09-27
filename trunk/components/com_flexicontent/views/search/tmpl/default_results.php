<?php defined('_JEXEC') or die('Restricted access'); ?>

<?php
$fcr_use_image = $this->params->get('fcr_use_image', 1);
$fcr_image = $this->params->get('fcr_image');

if ($fcr_use_image && $fcr_image) {
	$img_size_map   = array('l'=>'large', 'm'=>'medium', 's'=>'small');
	$img_field_size = $img_size_map[ $this->params->get('fcr_image_size' , 'l') ];
	$img_field_name = $this->params->get('fcr_image');
}


$use_infoflds = (int)$this->params->get('use_infoflds', 1);

$infoflds = $this->params->get('infoflds');
$infoflds = preg_replace("/[\"'\\\]/u", "", $infoflds);
$infoflds = array_unique(preg_split("/\s*,\s*/u", $infoflds));
if ( !strlen($infoflds[0]) ) unset($infoflds[0]);

$fcitems = array();
if ( ($use_infoflds && count($infoflds)) || $fcr_use_image )
{
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
	
	// Calculate CSS classes needed to add special styling markups to the items
	flexicontent_html::calculateItemMarkups($fcitems, $this->params);
}

if ( $use_infoflds && count($infoflds) ) {
	foreach ($infoflds as $fieldname)
	{
		FlexicontentFields::getFieldDisplay($fcitems, $fieldname, $values=null, $method='display');
	}
}
?>

<table class="contentpaneopen<?php echo $this->escape($this->params->get('pageclass_sfx')); ?>"><tr><td>

<?php $count = -1; ?>
<?php foreach($this->results as $i => $result) : ?>
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
	?>
	<fieldset id="searchlist_item_<?php echo $i; ?>" class="<?php echo $fc_item_classes; ?>">
	 <div class="search-results<?php echo $this->pageclass_sfx; ?>">
	 	
		<div class="fc_search_result_title">
			<?php echo $this->pageNav->limitstart + $result->count.'. ';?>
			<?php if ($result->href) :?>
				<a href="<?php echo JRoute::_($result->href); ?>"<?php if ($result->browsernav == 1) :?> target="_blank"<?php endif;?>>
					<?php echo $this->escape($result->title);?>
				</a>
			<?php else:?>
				<?php echo $this->escape($result->title);?>
			<?php endif; ?>
		</div>
		
		
		<?php if ( @ $result->fc_item_id ) echo $markup_tags; ?>
		<div class="fcclear"></div>
		
		
		<?php if ($this->params->get('show_date', 1)) : ?>
		<div class="fc_search_result_date">
			<span class="small<?php echo $this->escape($this->params->get('pageclass_sfx')); ?>">
			<?php echo JText::sprintf('FLEXI_CREATED_ON', $result->created); ?>
			</span>
		</div>
		<?php endif; ?>
		
		
		
		<?php if ( $this->params->get( 'show_section', 1 ) && $result->section ) : ?>
		<div class="fc_search_result_category">
			<span class="small<?php echo $this->pageclass_sfx; ?>">
				(<?php echo $this->escape($result->section); ?>)
			</span>
		</div>
		<?php endif; ?>
		
		
		
		<?php if ( $fcr_use_image && @ $result->fc_item_id ) : // FLEXIcontent specific result ?>
			
			<?php
			$src = $thumb = '';
			if (!empty($img_field_name)) :
				FlexicontentFields::getFieldDisplay($fcitems[$i], $img_field_name, $values=null, $method='display');
				$img_field = & $fcitems[$i]->fields[$img_field_name];
				$src = str_replace(JURI::root(), '', @ $img_field->thumbs_src[$img_field_size][0] );
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
				$zc		= $this->params->get('fcr_method') ? '&amp;zc=' . $this->params->get('fcr_method') : '';
				$ext = pathinfo($src, PATHINFO_EXTENSION);
				$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
				$conf	= $w . $h . $aoe . $q . $zc . $f;
				
				$base_url = (!preg_match("#^http|^https|^ftp#i", $src)) ?  JURI::base(true).'/' : '';
				$thumb = JURI::base(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
			} else {
				// Do not resize image when (a) image src path not set or (b) using image field's already created thumbnails
				$thumb = $src;
			}
			?>
		
			<?php if ($src) : ?>
			<div class="fc_search_result_image <?php echo $this->params->get('fcr_position') ? ' fcright' : ' fcleft'; ?>">
				<?php if ($this->params->get('fcr_link_image', 1)) : ?>
				<a href="<?php JRoute::_($result->href); ?>" class="hasTip" title="<?php echo JText::_( 'FLEXI_READ_MORE_ABOUT' ) . '::' . htmlspecialchars($result->title, ENT_COMPAT, 'UTF-8'); ?>">
					<img src="<?php echo $thumb; ?>" alt="<?php echo htmlspecialchars($result->title, ENT_COMPAT, 'UTF-8'); ?>" />
				</a>
				<?php else : ?>
				<img src="<?php echo $thumb; ?>" alt="<?php echo htmlspecialchars($result->title, ENT_COMPAT, 'UTF-8'); ?>" />
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
			<span class="fc_field_container">
			<?php if ( @$fcitems[$i]->fields[$fieldname]->display ) : ?>
				<span class="fc_field_label"><?php echo $fcitems[$i]->fields[$fieldname]->label; ?></span>
				<span class="fc_field_value"><?php echo $fcitems[$i]->fields[$fieldname]->display; ?></span>
			<?php endif; ?>
		<?php endforeach; ?>
			
		</div>
		<?php endif; // $infoflds ?>
		
		
		
	 </div>
	</fieldset>
	<?php endforeach; ?>

</td></tr></table>


<!-- BOF pagination (After Results) -->
<?php
	// If customizing via CSS rules or JS scripts is not enough, then please copy the following file here to customize the HTML too
	include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'pagination.php');
?>
<!-- EOF pagination -->


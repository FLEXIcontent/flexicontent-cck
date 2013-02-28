<?php defined('_JEXEC') or die('Restricted access'); ?>

<?php
$fcr_use_image = $this->params->get('fcr_use_image', 1);

if ($this->params->get('fcr_use_image', 1) && $this->params->get('fcr_image')) {
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
	}
}

if ( $use_infoflds && count($infoflds) ) {
	foreach ($infoflds as $fieldname)
	{
		FlexicontentFields::getFieldDisplay($fcitems, $fieldname, $values=null, $method='display');
	}
}

$dd = FLEXI_J16GE ? 'dd' : 'div'; $ddc = FLEXI_J16GE ? '/dd' : '/div';
$dt = FLEXI_J16GE ? 'dd' : 'div'; $dtc = FLEXI_J16GE ? '/dt' : '/div';
?>

<?php if (FLEXI_J16GE) :?>
	<dl class="search-results<?php echo $this->pageclass_sfx; ?>">
<?php else :?>
<table class="contentpaneopen<?php echo $this->escape($this->params->get('pageclass_sfx')); ?>">
	<tr>
		<td>
<?php endif; ?>

<?php $count = -1; ?>
<?php foreach($this->results as $i => $result) : ?>
<?php $count++; ?>

<?php if (!FLEXI_J16GE) :?>
<fieldset class="fc_search_result <?php echo $count%2 ? 'odd' : 'even'; ?>">
<div class="search-results<?php echo $this->pageclass_sfx; ?>">

<?php endif; ?>

	<<?php echo $dt; ?> class="result-title fc_search_result_title">
		<?php echo $this->pageNav->limitstart + $result->count.'. ';?>
		<?php if ($result->href) :?>
			<a href="<?php echo JRoute::_($result->href); ?>"<?php if ($result->browsernav == 1) :?> target="_blank"<?php endif;?>>
				<?php echo $this->escape($result->title);?>
			</a>
		<?php else:?>
			<?php echo $this->escape($result->title);?>
		<?php endif; ?>
	<<?php echo $dtc; ?>>
	
	<?php if ( $this->params->get( 'show_section', 1 ) && $result->section ) : ?>
		<<?php echo $dd; ?> class="result-category">
			<span class="small<?php echo $this->pageclass_sfx; ?>">
				(<?php echo $this->escape($result->section); ?>)
			</span>
		<<?php echo $ddc; ?>>
	<?php endif; ?>

	
	<?php if ($this->params->get('show_date', 1)) : ?>
		<<?php echo $dd; ?> class="result-created<?php echo $this->pageclass_sfx; ?> fc_search_result_date">
			<span class="small<?php echo $this->escape($this->params->get('pageclass_sfx')); ?>">
			<?php echo JText::sprintf('FLEXI_CREATED_ON', $result->created); ?>
			</span>
		<<?php echo $ddc; ?>>
	<?php endif; ?>
	
<div class="fcclear"></div>

<?php if ( $fcr_use_image ) : ?>
	<?php
	$src = $thumb = '';
	if ($this->params->get('fcr_use_image', 1)) :
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
			$thumb = JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
		} else {
			// Do not resize image when (a) image src path not set or (b) using image field's already created thumbnails
			$thumb = $src;
		}
	endif;
	?>
	<?php if ($this->params->get('fcr_use_image', 1) && $src) : ?>
		<<?php echo $dd; ?> class="fc_search_result_image <?php echo $this->params->get('fcr_position') ? ' right' : ' left'; ?>">
			<?php if ($this->params->get('fcr_link_image', 1)) : ?>
			<a href="<?php JRoute::_($result->href); ?>" class="hasTip" title="<?php echo JText::_( 'FLEXI_READ_MORE_ABOUT' ) . '::' . htmlspecialchars($result->title, ENT_COMPAT, 'UTF-8'); ?>">
				<img src="<?php echo $thumb; ?>" alt="<?php echo htmlspecialchars($result->title, ENT_COMPAT, 'UTF-8'); ?>" />
			</a>
			<?php else : ?>
			<img src="<?php echo $thumb; ?>" alt="<?php echo htmlspecialchars($result->title, ENT_COMPAT, 'UTF-8'); ?>" />
			<?php endif; ?>
		<<?php echo $ddc; ?>>
	<?php endif; ?>
	
<?php endif; ?>


	<?php if ( $this->params->get( 'show_text', 1 )) : ?>
	<<?php echo $dd; ?> class="result-text fc_search_result_text">
		<?php echo $result->text; ?>
	<<?php echo $ddc; ?>>
	<?php endif;?>
	
<div class="fcclear"></div>

	<<?php echo $dd; ?> class="fc_search_result_fields">

	<?php foreach ($infoflds as $fieldname) : ?>
		<span class="fc_field_container">
		<?php if ( @$fcitems[$i]->fields[$fieldname]->display ) : ?>
			<span class="fc_field_label"><?php echo $fcitems[$i]->fields[$fieldname]->label; ?></span>
			<span class="fc_field_value"><?php echo $fcitems[$i]->fields[$fieldname]->display; ?></span>
		<?php endif; ?>
	<?php endforeach; ?>
		
	<<?php echo $ddc; ?>>

<?php if (!FLEXI_J16GE) :?>
</dl>
</fieldset>
<?php endif; ?>

<?php endforeach; ?>

<?php if (FLEXI_J16GE) :?>
</div>
<?php else :?>
		</td>
	</tr>
</table>
<?php endif; ?>


<!-- BOF pagination -->
<?php
	// If customizing via CSS rules or JS scripts is not enough, then please copy the following file here to customize the HTML too
	include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'pagination.php');
?>
<!-- EOF pagination -->


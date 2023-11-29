<?php

// ***
// *** Showing total found items ?
// ***

$show_found_items = (int) $params->get('show_found_items', 0);
$total_info = $show_found_items && $list_totals
	? $list_totals[$catdata ? $catdata->id : false]
	: '';
$show_total = strlen($total_info);


// ***
// *** Showing category information ?
// ***

$show_cat_data = false;
if ( $catdata )
{
	$show_cat_image = $catdata->conf->show_image && ($catdata->image || $catdata->conf->show_default_image );
	$show_cat_data = $show_found_items || $catdata->conf->showtitle || $show_cat_image || !empty($catdata->description);
}


// ***
// *** If neither return
// ***
if ( !$show_cat_data && !$show_total ) return;


// ***
// *** Showing only total information
// ***

if ( $show_total && !$show_cat_data ) : ?>

	<div class="umod_list_totals" >
		<span class="icon-stack"></span>
		<?php echo '<i>' . JText::_('FLEXI_UMOD_TOTAL_ITEMS') . '</i> ' . ' (' . $total_info . ')'; ?>
	</div>


<?php
// ***
// *** Showing both category information and total found items
// ***
else :

	$app     = JFactory::getApplication();
	$jinput  = $app->input;
	$option  = $jinput->get('option', '', 'cmd');
	$view    = $jinput->get('view', '', 'cmd');
	$cid     = $jinput->get('cid', 0, 'int');

	$is_active_cat = $cid == $catdata->id && $option == 'com_flexicontent';
	$cat_classes  = 'catdata';
	$cat_classes .= ($is_active_cat && $view == FLEXI_ITEMVIEW) ? ' fcitemcat_active' : '';
	$cat_classes .= ($is_active_cat && $view == 'category')     ? ' fccat_active' : '';
	
	if ($show_found_items === 2 && !$catdata->conf->showtitle)
	{
		$show_found_items = 1;
	}
	?>

	<div class="<?php echo $cat_classes; ?>">
		
		<?php if ($catdata->conf->showtitle) : ?>

			<div class="fc_block">
				<div class="fc_block cattitle">
					<?php	if ($catdata->conf->link_title) : ?>
						<a class="cattitle_link" href="<?php echo $catdata->titlelink; ?>">
							<?php echo $catdata->title; ?>
							<?php echo $show_found_items === 2 && $show_total ? '<span class="umod_title_list_totals fc-nowrap-box"><span class="icon-stack"></span> (' . $total_info . ')</span>' : ''; ?>
						</a>
					<?php else : ?>
						<?php echo $catdata->title; ?>
						<?php echo $show_found_items === 2 && $show_total ? '<span class="umod_title_list_totals fc-nowrap-box"><span class="icon-stack"></span> (' . $total_info . ')</span>' : ''; ?>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
		
		<?php if ($show_found_items === 1 && $show_total ) : ?>
			<div class="umod_list_totals" >
				<span class="icon-stack"></span>
				<?php echo '<i>' . JText::_('FLEXI_UMOD_TOTAL_ITEMS') . '</i> ' . ' (' . $total_info . ')'; ?>
			</div>
		<?php endif; ?>

		<?php if ( $show_cat_image ) : ?>
			<div class="catimage">
				<?php
				if ($catdata->image) {
					$catimage_thumb = '<img class="catimage_thumb" src="'.$catdata->image.'" alt="'.addslashes($catdata->title).'" title="'.addslashes($catdata->title).'"/>';
				} else { // DEFAULT IMAGE or empty image place holder
					//$catimage_thumb = '<div class="fccat_image" style="height:'.$catdata->conf->image_height.'px;width:'.$catdata->conf->image_width.'px;" ></div>';
				}
				?>
				<?php	if ($catdata->conf->link_title) : ?>
					<a class="catimage_link" href="<?php echo $catdata->imagelink; ?>"><?php echo $catimage_thumb; ?></a>
				<?php else : ?>
					<?php echo $catimage_thumb; ?>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		
		<?php if ( !empty($catdata->description) ) : ?>
			<div class="catdescr">
				<?php echo $catdata->description; ?>
			</div>
		<?php endif; ?>
		
	</div>

	<span class="modclear"></span>
<?php endif; ?>
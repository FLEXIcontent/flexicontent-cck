<?php if ( $catdata ) : ?>
	<?php
	$show_cat_image = $catdata->conf->show_image && ($catdata->image || $catdata->conf->show_default_image );
	$show_cat_data = $catdata->conf->showtitle || $show_cat_image || !empty($catdata->description);
	if ( !$show_cat_data  ) return;
	?>
	
	<div class='catdata'>
		
		<?php if ($catdata->conf->showtitle) : ?>
		<span class='fc_block'>
			<span class='fc_block cattitle'>
				<?php	if ($catdata->conf->link_title) : ?>
					<a class="cattitle_link" href='<?php echo $catdata->titlelink; ?>'><?php echo $catdata->title; ?></a>
				<?php else : ?>
					<?php echo $catdata->title; ?>
				<?php endif; ?>
			</span>
		</span>
		<?php endif; ?>
		
		<?php if ( $show_cat_image ) : ?>
			<span class='catimage'>
				<?php
				if ($catdata->image) {
					$catimage_thumb = '<img class="catimage_thumb" src="'.$catdata->image.'" alt="'.addslashes($catdata->title).'" title="'.addslashes($catdata->title).'"/>';
				} else { // DEFAULT IMAGE or empty image place holder
					//$catimage_thumb = '<div class="fccat_image" style="height:'.$catconf->image_height.'px;width:'.$catconf->image_width.'px;" ></div>';
				}
				?>
				<?php	if ($catdata->conf->link_title) : ?>
					<a class="catimage_link" href='<?php echo $catdata->imagelink; ?>'><?php echo $catimage_thumb; ?></a>
				<?php else : ?>
					<?php echo $catimage_thumb; ?>
				<?php endif; ?>
			</span>
		<?php endif; ?>
		
		<?php if ( !empty($catdata->description) ) : ?>
			<span class='catdescr'><?php echo $catdata->description; ?></span>
		<?php endif; ?>
		
		<span class='modclear'></span>
		
	</div>

<?php endif; ?>
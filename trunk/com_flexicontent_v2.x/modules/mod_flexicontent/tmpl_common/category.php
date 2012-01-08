
<?php if ($catdata) : ?>

	<div class='catdata'>
		
		<?php if (isset($catdata->title)) : ?>
		<span class='block'>
			<span class='inline_block cattitle'>
				<?php	if (isset($catdata->titlelink)) : ?>
					<a href='<?php echo $catdata->titlelink; ?>'><?php echo $catdata->title; ?></a>
				<?php else : ?>
					<?php echo $catdata->title; ?>
				<?php endif; ?>
			</span>
		</span>
		<?php endif; ?>
		
		<?php if (isset($catdata->image)) : ?>
			<span class='image_cat'>
				<?php if (isset($catdata->imagelink)) : ?>
					<a href='<?php echo $catdata->imagelink; ?>'>
						<img src='<?php echo $catdata->image; ?>' alt='<?php echo flexicontent_html::striptagsandcut($catdata->title, 60); ?>' />
					</a>
				<?php else : ?>
					<img src='<?php echo $catdata->image; ?>' alt='<?php echo flexicontent_html::striptagsandcut($catdata->title, 60); ?>' />
				<?php endif; ?>
			</span>
		<?php endif; ?>
		
		<?php if (isset($catdata->description)) : ?>
			<span class='catdescr'><?php echo $catdata->description; ?></span>
		<?php endif; ?>
		
		<span class='modclear'></span>
		
	</div>
	
	<span class='modclear'></span>
<?php endif; ?>
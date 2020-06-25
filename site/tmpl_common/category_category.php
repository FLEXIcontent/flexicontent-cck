<?php
defined( '_JEXEC' ) or die( 'Restricted access' );
?>

<div class="fclear"></div>
<div class="floattext">
	<?php if ($this->params->get('show_cat_title', 1)) : ?>
	<h2 class="cattitle">
		<?php echo $this->escape($this->category->title); ?>
	</h2>
	<?php endif; ?>

	<?php if ($this->params->get('show_description_image', 1) && $this->category->image) : ?>
	<div class="catimg">
		<?php echo $this->category->image; ?>
	</div>
	<?php endif; ?>
	
	<?php if ($this->params->get('show_description', 1) && $this->category->description) : ?>
	<div class="catdescription">
		<?php echo $this->category->description; ?>
	</div>
	<?php endif; ?>
</div>

<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

$catTitleHeaderLevel = ( $this->params->get( 'show_page_heading', 1 ) && $this->params->get('show_cat_title', 1) ) ? '2' : '1'; 
// Note:in Some editors like Dreamweaver will automatically set a closing tag > after </h when opening the document. So look for h>  and replaced it with h
?>

<div class="floattext group">
	<?php if ($this->params->get('show_cat_title', 1)) : ?>
    <header>
		<?php echo "<h".$catTitleHeaderLevel; ?> class="cattitle">
		<?php echo $this->escape($this->category->title); ?>
		<?php echo "</h". $catTitleHeaderLevel; ?>>
    </header>
	<?php endif; ?>

	<?php if ($this->params->get('show_description_image', 1) && $this->category->image) : ?>
	<figure class="catimg">
		<?php echo $this->category->image; ?>
	</figure>
	<?php endif; ?>
	
	<?php if ($this->params->get('show_description', 1) && $this->category->description) : ?>
	<div class="catdescription">
		<?php echo $this->category->description; ?>
	</div>
	<?php endif; ?>
</div>

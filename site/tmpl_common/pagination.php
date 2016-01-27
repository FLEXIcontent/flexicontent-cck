<?php if ($this->params->get('show_pagination', 2) != 0) : ?>

<div class="pagination">
	
	<div class="pageslinks">
		<?php echo $this->pageNav->getPagesLinks(); ?>
	</div>

	<?php if ($this->params->get('show_pagination_results', 1)) : ?>
	<p class="pagescounter counter">
		<?php echo $this->pageNav->getPagesCounter(); ?>
	</p>
	<?php endif; ?>
	
</div>

<?php endif; ?>

<?php if ($this->params->get('show_pagination', 2) != 0) : ?>

<div class="pagination">
	
	<?php if ($this->params->get('show_pagination_results', 1)) : ?>
	<p class="counter pull-right">
		<?php echo $this->pageNav->getPagesCounter(); ?>
	</p>
	<?php endif; ?>
	
	<?php echo $this->pageNav->getPagesLinks(); ?>
	
</div>

<?php endif; ?>

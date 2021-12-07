<?php
$page_classes  = 'flexi_edit flexicontent' . (!$isSite ? ' full_body_box' : '');
$page_classes .= $isSite && $this->pageclass_sfx ? ' page'.$this->pageclass_sfx : '';
?>

<div id="flexicontent" class="<?php echo $page_classes; ?>" style="<?php echo $form_container_css; ?>">

	<?php if ($isSite && $this->params->def( 'show_page_heading', 1 )) : ?>
	<h1 class="componentheading">
		<?php echo $this->params->get('page_heading'); ?>
	</h1>
	<?php endif; ?>

	<form action="<?php echo $this->action ?>" method="post" name="adminForm" id="adminForm" class="form-validate form-horizontal" enctype="multipart/form-data">

		<?php if ($buttons_placement === 0) : /* PLACE buttons at BOTTOM of form */ ?>
			<div id="fctoolbar_btn" class="btn btn-primary" onclick="fc_toggle_box_via_btn('fctoolbar', this, 'btn-primary');" >
				<?php echo JText::_('JTOOLBAR'); ?> <span class="icon-wrench"></span></a>
			</div>
			<?php echo $this->toolbar->render(); ?>
		<?php endif; ?>

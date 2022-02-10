<?php
defined('_JEXEC') or die('Restricted access');

$page_classes  = 'flexi_edit flexicontent fc_item_form_box_' . $typeid. ' ' . $form_container_class . (!$isSite ? ' full_body_box' : '');
$page_classes .= $isSite && $this->pageclass_sfx ? ' page'.$this->pageclass_sfx : '';
?>

<div id="flexicontent" class="<?php echo $page_classes; ?>" style="<?php echo $form_container_css; ?>">

	<?php if ($isSite && $this->params->def( 'show_page_heading', 1 )) : ?>
	<h1 class="componentheading"><?php echo $this->params->get('page_heading'); ?></h1>
	<?php endif; ?>


	<div class="container-fluid row" style="padding: 0px !important; margin: 0px !important">


	<?php if ($buttons_placement === 2) : /* PLACE buttons at LEFT of form */ ?>
		<div class="span2 col-md-2 fctoolbar_side_placement fcpos_left">
			<div id="fctoolbar_btn" class="btn btn-primary" onclick="fc_toggle_box_via_btn(<?php echo FLEXI_J40GE ? "jQuery('#fctoolbar').parent()" : "'fctoolbar'"; ?>, this, 'btn-primary');" >
				<?php echo JText::_('JTOOLBAR'); ?> <span class="icon-wrench"></span></a>
			</div>
			<?php // An EXAMPLE of adding more buttons: $this->toolbar->appendButton('Standard', 'cancel', 'JCANCEL', 'items.cancel', false);
			echo $this->toolbar->render(); ?>
		</div>
	<?php endif;



	/* Open form's SPAN**, reserve width if buttons are at LEFT or RIGHT of form */
	if ($buttons_placement === 2 || $buttons_placement === 3) : ?>
		<div class="span10 col-md-10">
	<?php else: ?>
		<div class="span12 col-md-12">
	<?php endif; ?>



		<form action="<?php echo $this->action ?>" method="post" name="adminForm" id="adminForm" class="form-validate form-horizontal" enctype="multipart/form-data">

			<?php if ($buttons_placement === 0) : /* PLACE buttons at TOP of form */ ?>
			<div class="fctoolbar_top_placement">
				<div id="fctoolbar_btn" class="btn btn-primary" onclick="fc_toggle_box_via_btn(<?php echo FLEXI_J40GE ? "jQuery('#fctoolbar').parent()" : "'fctoolbar'"; ?>, this, 'btn-primary');" >
					<?php echo JText::_('JTOOLBAR'); ?> <span class="icon-wrench"></span></a>
				</div>
				<?php // An EXAMPLE of adding more buttons: $this->toolbar->appendButton('Standard', 'cancel', 'JCANCEL', 'items.cancel', false);
				echo $this->toolbar->render(); ?>
			</div>
			<?php endif; ?>

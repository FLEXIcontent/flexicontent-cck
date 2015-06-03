<?php
$form_style = JRequest::getCmd('print') ? 'display:none' : '';
ob_start();

// Body of form for (a) Text search, Field Filters, Alpha-Index, Items Total Statistics, Selectors(e.g. per page, orderby)
// If customizing via CSS rules or JS scripts is not enough, then please copy the following file here to customize the HTML too
include('listings_filter_form_body_html5.php');

$filter_form_body = trim(ob_get_contents());
ob_end_clean();
if ( empty($filter_form_body) ) return;
?>

<div class="fcfilter_form_outer fcfilter_form_component">

<?php
// FORM in slider
$ff_placement = $this->params->get('ff_placement', 0);

if ($ff_placement){
	$model = $this->getModel();
	$ff_slider_id = 
		($model->_id     ? '_'.$model->_id : '').
		($model->_layout ? '_'.$model->_layout : '')
		;
	$ff_slider_title = JText::_($this->params->get('ff_slider_title', 'FLEXI_SEARCH_FORM_TOGGLE'));
	echo JHtml::_('sliders.start', 'fcfilter_form_slider'.$ff_slider_id, array('useCookie'=>1, 'show'=>-1, 'display'=>-1, 'startOffset'=>-1, 'startTransition'=>1));
	echo JHtml::_('sliders.panel', $ff_slider_title, 'fcfilter_form_togglebtn'.$ff_slider_id);
}
?>

<form action="<?php echo $this->action; ?>" method="post" id="adminForm" onsubmit="" class="group" style="<?php echo $form_style;?>">

<?php echo $filter_form_body; ?>

	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['filter_order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['filter_order_Dir']; ?>" />
	<input type="hidden" name="view" value="category" />
	<input type="hidden" name="letter" value="<?php echo JRequest::getVar('letter');?>" id="alpha_index" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="cid" value="<?php echo $this->category->id; ?>" />
	<input type="hidden" name="layout" value="<?php echo $this->layout_vars['layout']; ?>" />
</form>

<?php
// FORM in slider
if ($ff_placement) echo JHtml::_('sliders.end');
?>

</div>
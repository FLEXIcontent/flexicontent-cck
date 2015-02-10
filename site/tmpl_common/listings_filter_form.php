<?php
$form_style = JRequest::getCmd('print') ? 'display:none' : '';
ob_start();

// Body of form for (a) Text search, Field Filters, Alpha-Index, Items Total Statistics, Selectors(e.g. per page, orderby)
// If customizing via CSS rules or JS scripts is not enough, then please copy the following file here to customize the HTML too
include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'listings_filter_form_body.php');

//field in slider
$slider_mod = $this->params->get('slider_mod', 0);
$slider_cat_title =$this->params->get('slider_cat_title', 'FLEXI_SLIDER_SEARCH');

$filter_form_body = trim(ob_get_contents());
ob_end_clean();
if ( empty($filter_form_body) ) return;
?>


<?php 
	if ($slider_mod){
		$slider_mod2 = JHtml::_('sliders.start',$slider_cat_title, array('useCookie'=>1 ,'startOffset'=>-1, 'startTransition'=>1));
		$slider_mod2 .=  JHtml::_('sliders.panel', $slider_cat_title, 'slidercat');
		$endslider_mod2 = JHtml::_('sliders.end');
		echo $slider_mod2;
	}
?>

<form action="<?php echo $this->action; ?>" method="post" id="adminForm" onsubmit="" style="<?php echo $form_style;?>">

<?php echo $filter_form_body; ?>

<?php if ( JRequest::getVar('clayout') == $this->params->get('clayout', 'blog') ) :?>
	<input type="hidden" name="clayout" value="<?php echo JRequest::getVar('clayout'); ?>" />
<?php endif; ?>

	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['filter_order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['filter_order_Dir']; ?>" />
	<input type="hidden" name="view" value="category" />
	<input type="hidden" name="letter" value="<?php echo JRequest::getVar('letter');?>" id="alpha_index" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="cid" value="<?php echo $this->category->id; ?>" />
	<input type="hidden" name="layout" value="<?php echo JRequest::getCmd('layout',''); ?>" />
</form>

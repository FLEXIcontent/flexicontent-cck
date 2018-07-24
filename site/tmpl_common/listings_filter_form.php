<?php
defined( '_JEXEC' ) or die( 'Restricted access' );
if ( JFactory::getApplication()->input->getInt('print', 0) ) return;

ob_start();

/**
 * Body of form for (a) Text search, Field Filters, Alpha-Index, Items Total Statistics, Selectors(e.g. per page, orderby)
 * If customizing via CSS rules or JS scripts is not enough, then please copy the following file here to customize the HTML too
 *
 * First try current folder, otherwise load from common folder
 */

file_exists('listings_filter_form_body.php')
	? include('listings_filter_form_body.php')
	: include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'listings_filter_form_body.php');

$filter_form_body = trim(ob_get_contents());
ob_end_clean();
if ( empty($filter_form_body) ) return;
?>

<div class="fcfilter_form_outer fcfilter_form_component">

<?php
$jcookie = JFactory::getApplication()->input->cookie;
$cookie_name = 'fc_active_TabSlidePage';

// FORM in slider
$ff_placement = $this->params->get('ff_placement', 0);

if ($ff_placement)
{
	$model = $this->getModel();
	$ff_slider_id =
		($model->_id     ? '_cat_' . $model->_id : '').
		($model->_layout ? '_cat_' . $model->_layout : '')
		;
	$ff_toggle_search_title = JText::_($this->params->get('ff_toggle_search_title', 'FLEXI_TOGGLE_SEARCH_FORM'));
	$ff_slider_tagid = 'fcfilter_form_slider'.$ff_slider_id;

	$active_slides = $jcookie->get($cookie_name, '{}', 'string');

	try
	{
		$active_slides = json_decode($active_slides);
	}
	catch (Exception $e)
	{
		$jcookie->set($cookie_name, '{}', time()+60*60*24*(365*5), JUri::base(true), '');
	}

	$last_active_slide = isset($active_slides->$ff_slider_tagid) ? $active_slides->$ff_slider_tagid : null;

	echo JHtml::_('bootstrap.startAccordion', $ff_slider_tagid, array('active' => $last_active_slide));
	echo JHtml::_('bootstrap.addSlide', $ff_slider_tagid, $ff_toggle_search_title, $ff_slider_tagid . '_filters_slide');
}
?>

<form action="<?php echo $this->action; ?>" method="post" id="adminForm" >

<?php echo $filter_form_body; ?>

	<input type="hidden" id="filter_order" name="filter_order" value="<?php echo $this->lists['filter_order']; ?>" />
	<input type="hidden" id="filter_order_Dir" name="filter_order_Dir" value="<?php echo $this->lists['filter_order_Dir']; ?>" />

	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="view" value="category" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="cid" value="<?php echo $this->category->id; ?>" />
	<input type="hidden" name="layout" value="<?php echo $this->layout_vars['layout']; ?>" />

	<input type="hidden" name="letter" value="<?php echo JFactory::getApplication()->input->get('letter', '', 'string'); ?>" id="alpha_index" />

	<?php if (flexicontent_html::initial_list_limited($this->params)) : ?>
	<input type="hidden" name="listall" value="<?php echo JFactory::getApplication()->input->get('listall', 0, 'int'); ?>" />
	<?php endif; ?>

</form>

<?php
// FORM in slider
if ($ff_placement)
{
	echo JHtml::_('bootstrap.endSlide');
	echo JHtml::_('bootstrap.endAccordion');
	
	JFactory::getDocument()->addScriptDeclaration("
	(function($) {
		$(document).ready(function ()
		{
			$('#" . $ff_slider_tagid ."').on('shown', function ()
			{
				var active_slides = fclib_getCookie('" . $cookie_name ."');
				try { active_slides = JSON.parse(active_slides); } catch(e) { active_slides = {}; }

				active_slides['" . $ff_slider_tagid ."'] = $('#" . $ff_slider_tagid ." .in').attr('id');
				fclib_setCookie('" . $cookie_name ."', JSON.stringify(active_slides), 7);
				window.console.log(JSON.stringify(active_slides));
			});

			$('#" . $ff_slider_tagid ."').on('hidden', function ()
			{
				var active_slides = fclib_getCookie('" . $cookie_name ."');
				try { active_slides = JSON.parse(active_slides); } catch(e) { active_slides = {}; }

				active_slides['" . $ff_slider_tagid ."'] = null;
				fclib_setCookie('" . $cookie_name ."', JSON.stringify(active_slides), 7);
				window.console.log(JSON.stringify(active_slides));
			});

			var active_slides = fclib_getCookie('" . $cookie_name ."');
			try { active_slides = JSON.parse(active_slides); } catch(e) { active_slides = {}; }

			if (!!active_slides['" . $ff_slider_tagid ."'])
			{
				// Hide default active slide
				$('#" . $ff_slider_tagid ." .collapse').removeClass('in');

				// Show the last active slide
				$('#' + active_slides['" . $ff_slider_tagid ."']).addClass('in');
			}
		});
	})(jQuery);
	");
}

$listall_selector = flexicontent_html::listall_selector($this->params, $formname='adminForm', $autosubmit=1);

if ($listall_selector) : ?>
	<div class="fc_listall_box">
		<div class="fc_listall_selector">
			<?php echo $listall_selector;?>
		</div>
	</div>
<?php endif; ?>

</div>
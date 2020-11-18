<?php // no direct access
defined('_JEXEC') or die('Restricted access');

// use css class fc_nnnnn_clear to override wrapping

if ($scroll_to_anchor_tag) echo '
	<a name="mod_flexifilter_anchor' . $module->id . '"></a>
';
?>

<div class="mod_flexifilter_wrapper mod_flexifilter_wrap<?php echo $moduleclass_sfx; ?>" id="mod_flexifilter_default<?php echo $module->id ?>">

<?php
// Prepare remaining form parameters
$form_id = $form_name;
$form_method = 'post';   // DO NOT CHANGE THIS

$show_filter_labels = $params->get('show_filter_labels', 1);
$filter_placement = $params->get( 'filter_placement', 1 );
$filter_container_class  = $filter_placement ? 'fc_filter_line' : 'fc_filter';
$filter_container_class .= $filter_placement==2 ? ' fc_clear_label' : '';
$text_search_val = JFactory::getApplication()->input->get('filter', '', 'string');

// 4. Create (print) the form
?>

<div class="fcfilter_form_outer fcfilter_form_module">

<?php
$jcookie = JFactory::getApplication()->input->cookie;
$cookie_name = 'fc_active_TabSlidePage';

// FORM in slider
$ff_placement = $params->get('ff_placement', 0);

if ($ff_placement)
{
	$ff_slider_id =
		($module->id     ? '_module_' . $module->id : '')
		;
	$ff_toggle_search_title = JText::_($params->get('ff_toggle_search_title', 'FLEXI_TOGGLE_SEARCH_FORM'));
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

<form id='<?php echo $form_id; ?>' action='<?php echo $form_target; ?>' data-fcform_default_action='<?php echo $form_target; ?>' method='<?php echo $form_method; ?>' role='search' >

<?php if ( !empty($cats_select_field) ) : ?>
<fieldset class="fc_filter_set" style="padding-bottom:0px;">
	<span class="<?php echo $filter_container_class. ' fc_odd'; ?>" style="margin-bottom:0px;">
		<span class="fc_filter_label fc_cid_label"><?php echo JText::_($mcats_selection ? 'FLEXI_FILTER_CATEGORIES' : 'FLEXI_FILTER_CATEGORY'); ?></span>
		<span class="fc_filter_html fc_cid_selector"><span class="cid_loading" id="cid_loading_<?php echo $module->id; ?>"></span><?php echo $cats_select_field; ?></span>
	</span>
</fieldset>
<div class="fcclear"></div>
<?php elseif ( !empty($cat_hidden_field) ): ?>
	<?php echo $cat_hidden_field; ?>
<?php endif; ?>

<?php include(JPATH_SITE.'/components/com_flexicontent/tmpl_common/filters.php'); ?>

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
				//window.console.log(JSON.stringify(active_slides));
			});

			$('#" . $ff_slider_tagid ."').on('hidden', function ()
			{
				var active_slides = fclib_getCookie('" . $cookie_name ."');
				try { active_slides = JSON.parse(active_slides); } catch(e) { active_slides = {}; }

				active_slides['" . $ff_slider_tagid ."'] = null;
				fclib_setCookie('" . $cookie_name ."', JSON.stringify(active_slides), 7);
				//window.console.log(JSON.stringify(active_slides));
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

if ($scroll_to_anchor_tag)
{
	JFactory::getDocument()->addScriptDeclaration("
	(function($) {
		$(document).ready(function ()
		{
			function scrollToAnchor(aid){
					var aTag = $(\"a[name='\"+ aid +\"']\");
					$('html,body').animate({scrollTop: aTag.offset().top},'slow');
			}
			scrollToAnchor('mod_flexifilter_anchor" . $module->id . "');
		});
	})(jQuery);
	");
}
?>

</div>

</div> <!-- mod_flexifilter_wrap -->

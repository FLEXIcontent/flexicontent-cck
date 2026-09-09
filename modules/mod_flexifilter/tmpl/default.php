<?php // no direct access
defined('_JEXEC') or die('Restricted access');
use Joomla\CMS\Factory;

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();

// Choices.js mode (select_lib_type=1) keeps the front entirely jQuery-free (site only).
// select2 legacy keeps jQuery + mousewheel; admin keeps jQuery.
$cparams = \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent');
$useChoicesJs = ((int) $cparams->get('select_lib_type', 0) === 1);
$isSite = Factory::getApplication()->isClient('site');
if (!$isSite || !$useChoicesJs) {
	$wa->useScript('jquery');
}
$wa->useScript('bootstrap.collapse'); // pour accordion Joomla

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
$text_search_val = \Joomla\CMS\Factory::getApplication()->input->get('filter', '', 'string');
$column_width = $params->get('column_width', '20%');
$column_gap = $params->get('column_gap', '2rem');

// 4. Create (print) the form
?>

<div class="fcfilter_form_outer fcfilter_form_module">

<?php
$jcookie = \Joomla\CMS\Factory::getApplication()->input->cookie;
$cookie_name = 'fc_active_TabSlidePage';

// FORM in slider
$ff_placement = $params->get('ff_placement', 0);

if ($ff_placement)
{
	$ff_slider_id =
		($module->id     ? '_module_' . $module->id : '')
		;
	$ff_toggle_search_title = \Joomla\CMS\Language\Text::_($params->get('ff_toggle_search_title', 'FLEXI_TOGGLE_SEARCH_FORM'));
	$ff_slider_tagid = 'fcfilter_form_slider'.$ff_slider_id;

	$active_slides = $jcookie->get($cookie_name, '{}', 'string');

	try
	{
		$active_slides = json_decode($active_slides);
	}
	catch (Exception $e)
	{
		$jcookie->set($cookie_name, '{}', time()+60*60*24*(365*5), \Joomla\CMS\Uri\Uri::base(true), '');
	}

	$last_active_slide = isset($active_slides->$ff_slider_tagid) ? $active_slides->$ff_slider_tagid : null;

	echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.startAccordion', $ff_slider_tagid, array('active' => $last_active_slide));
	echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.addSlide', $ff_slider_tagid, $ff_toggle_search_title, $ff_slider_tagid . '_filters_slide');
}
?>

<form id='<?php echo $form_id; ?>' action='<?php echo $form_target; ?>' data-fcform_default_action='<?php echo $form_target; ?>' method='<?php echo $form_method; ?>' role='search' >

<?php if ( !empty($cats_select_field) ) : ?>
<fieldset class="fc_filter_set fc_category" style="padding-bottom:0px;">
	<span class="<?php echo $filter_container_class. ' fc_odd'; ?>" style="margin-bottom:0px;">
		<span class="fc_filter_label fc_cid_label"><?php echo \Joomla\CMS\Language\Text::_($mcats_selection ? 'FLEXI_FILTER_CATEGORIES' : 'FLEXI_FILTER_CATEGORY'); ?></span>
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
	echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endSlide');
	echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endAccordion');
	
	$wa->addInlineScript("
	if (typeof jQuery !== 'undefined') {
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
	}
	");
}

if ($scroll_to_anchor_tag)
{
	$wa->addInlineScript("
	if (typeof jQuery !== 'undefined') {
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
	}
	");
}

if ($params->get('filter_ajax', 0)) :
$wa->addInlineScript(<<<'JS'
document.addEventListener('DOMContentLoaded', function() {

	if (window.fc_ajax_filter_bound) return;
	window.fc_ajax_filter_bound = true;

	function fcGetAttr(el, name, val) {
		if (val === undefined) return el ? el.getAttribute(name) : null;
		if (el) el.setAttribute(name, val);
	}
	function fcFindOrCreateTask(form) {
		var t = form.querySelector('input[name="task"]');
		if (t) return t;
		t = document.createElement('input');
		t.type = 'hidden';
		t.name = 'task';
		form.appendChild(t);
		return t;
	}
	function fcSerialize(form) {
		var parts = [];
		var els = form.elements;
		for (var i = 0; i < els.length; i++) {
			var el = els[i];
			if (!el.name) continue;
			var type = (el.type || '').toLowerCase();
			var tag = el.tagName.toLowerCase();
			if (tag === 'button' || type === 'submit' || type === 'reset' || type === 'file' || type === 'image') continue;
			if (el.disabled) continue;
			if ((type === 'checkbox' || type === 'radio') && !el.checked) continue;
			if (type === 'select-multiple') {
				for (var j = 0; j < el.options.length; j++) {
					if (el.options[j].selected) parts.push(encodeURIComponent(el.name) + '=' + encodeURIComponent(el.options[j].value));
				}
				continue;
			}
			parts.push(encodeURIComponent(el.name) + '=' + encodeURIComponent(el.value));
		}
		return parts.join('&');
	}
	function fcSetOpacity(els, v) {
		for (var i = 0; i < els.length; i++) els[i].style.opacity = v;
	}

	/* Wrap adminFormPrepare WITHOUT truncating the base action: the original builds
	 * form.action = base (+option/view/cid/Itemid...) + filters; we keep base intact. */
	if (typeof window.adminFormPrepare === 'function' && !window._original_adminFormPrepare) {
		window._original_adminFormPrepare = window.adminFormPrepare;
		window.adminFormPrepare = function(form, postprep, task) {
			window._original_adminFormPrepare(form, 0, task);
			if (postprep == 2) {
				if (task) fcFindOrCreateTask(form).value = task;
				form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
				var blocker = document.getElementById('fc_filter_form_blocker');
				if (blocker) blocker.style.display = 'block';
			} else if (postprep == 1) {
				var warn = document.getElementById(form.id + '_submitWarn');
				if (warn) warn.style.display = 'inline-block';
			}
		};
	}

	/* Override native form.submit so programmatic submits go through the ajax handler */
	window.fcOverrideNativeSubmit = function(formId) {
		var nativeForm = document.getElementById(formId);
		if (nativeForm && !nativeForm._originalSubmit) {
			nativeForm._originalSubmit = nativeForm.submit;
			nativeForm.submit = function(task) {
				if (task && typeof task === 'string') fcFindOrCreateTask(this).value = task;
				this.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
			};
		}
	};

	window.fcOverrideNativeSubmit('adminForm');
	var moduleForms = document.querySelectorAll('form[id^="moduleFCform_"]');
	for (var mf = 0; mf < moduleForms.length; mf++) window.fcOverrideNativeSubmit(moduleForms[mf].id);

	/* Re-init select libraries (choices / select2 / chosen) after DOM replacement */
	function fcReattachLibs(targetContainer, moduleContainers) {
		var containers = [];
		if (targetContainer) containers.push(targetContainer);
		for (var mm = 0; mm < moduleContainers.length; mm++) {
			var f = moduleContainers[mm].querySelector('form');
			containers.push(f || moduleContainers[mm]);
		}
		for (var c = 0; c < containers.length; c++) {
			var cont = containers[c];
			if (!cont || !cont.querySelectorAll) continue;
			var libEls = cont.querySelectorAll('select.use_select2_lib');
			if (libEls.length && window.fc_attachSelect2 && !window.skip_select2_js) {
				window.fc_attachSelect2(cont, Array.prototype.slice.call(libEls));
			}
			if (window.jQuery && window.jQuery.fn && window.jQuery.fn.chosen) {
				var chosenEls = cont.querySelectorAll('select.use_chosen_lib');
				for (var ch = 0; ch < chosenEls.length; ch++) {
					var s = chosenEls[ch];
					var next = s.nextElementSibling;
					var done = next && next.classList && next.classList.contains('chosen-container');
					if (!done) window.jQuery(s).chosen();
				}
			}
		}
	}

	/* Main ajax submit handler */
	document.addEventListener('submit', function(e) {
		var form = e.target;
		if (!form || !form.tagName || form.tagName.toLowerCase() !== 'form') return;

		var isFlexiForm =
			form.id === 'adminForm' ||
			(form.id && form.id.indexOf('default_form_') === 0) ||
			!!form.closest('.mod_flexifilter_wrapper') ||
			!!form.closest('.fcfilter_form_component');
		if (!isFlexiForm) return;

		e.preventDefault();

		var targetContainer = document.getElementById('flexicontent');
		var moduleContainers = document.querySelectorAll('.mod_flexifilter_wrapper, .mod_fleximap');

		if (!targetContainer && moduleContainers.length === 0) {
			if (form._originalSubmit) form._originalSubmit.call(form);
			else form.submit();
			return;
		}

		var fullActionUrl = fcGetAttr(form, 'action') || '';
		var urlParts = fullActionUrl.split('?');
		var baseUrl = urlParts[0];
		var queryString = urlParts.length > 1 ? urlParts[1] : fcSerialize(form);

		if (targetContainer) targetContainer.style.opacity = '0.5';
		fcSetOpacity(moduleContainers, '0.5');

		var oldAutosubmits = {};
		if (targetContainer) {
			var oldForm = document.getElementById('adminForm');
			if (oldForm) oldAutosubmits['adminForm'] = fcGetAttr(oldForm, 'data-fc-autosubmit') || '2';
		}
		for (var mm = 0; mm < moduleContainers.length; mm++) {
			var modForm = moduleContainers[mm].querySelector('form');
			if (modForm && modForm.id) oldAutosubmits[modForm.id] = fcGetAttr(modForm, 'data-fc-autosubmit') || '2';
		}

		var blocker = document.getElementById('fc_filter_form_blocker');
		if (blocker) blocker.style.display = 'block';

		fetch(baseUrl + '?' + queryString, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: queryString
		}).then(function(resp) { return resp.text(); }).then(function(response) {
			var doc = new DOMParser().parseFromString(response, 'text/html');

			if (targetContainer) {
				var newContent = doc.getElementById('flexicontent');
				if (newContent) targetContainer.innerHTML = newContent.innerHTML;
				targetContainer.style.opacity = '1';
			}

			if (moduleContainers.length) {
				for (var mc = 0; mc < moduleContainers.length; mc++) {
					var modWrapper = moduleContainers[mc];
					if (!document.body.contains(modWrapper)) continue;
					var modId = modWrapper.id;
					if (modId) {
						var newMod = doc.getElementById(modId);
						if (newMod) modWrapper.innerHTML = newMod.innerHTML;
					}
					modWrapper.style.opacity = '1';
				}
			}

			if (blocker) blocker.style.display = 'none';

			fcReattachLibs(targetContainer, moduleContainers);

			/* Re-bind the refreshed forms */
			var formsToRebind = [];
			var newAdminForm = document.getElementById('adminForm');
			if (newAdminForm) formsToRebind.push(newAdminForm);
			var modFormWrappers = document.querySelectorAll('.mod_flexifilter_wrapper form');
			for (var mw = 0; mw < modFormWrappers.length; mw++) formsToRebind.push(modFormWrappers[mw]);

			for (var nb = 0; nb < formsToRebind.length; nb++) {
				(function(newForm) {
					if (!newForm) return;
					var fId = newForm.id;
					if (oldAutosubmits[fId]) {
						newForm.setAttribute('data-fc-autosubmit', oldAutosubmits[fId]);
						var autos = oldAutosubmits[fId];
						var els = newForm.elements;
						for (var ei = 0; ei < els.length; ei++) {
							var fe = els[ei];
							var type = (fe.type || '').toLowerCase();
							if (type === 'hidden' || type === 'button' || type === 'submit' || type === 'reset' || type === 'file' || type === 'image') continue;
							if (fe.classList.contains('fc_autosubmit_exclude')) continue;
							if (fe.classList.contains('select2-input')) continue;
							fe.addEventListener('change', function() {
								if (window.adminFormPrepare) window.adminFormPrepare(newForm, autos);
							});
						}
					}
					var resetBtns = newForm.querySelectorAll('.fc_button.button_reset');
					for (var rb = 0; rb < resetBtns.length; rb++) {
						(function(btn) {
							btn.addEventListener('click', function() {
								if (window.fc_use_choicesjs) {
									var sels = newForm.querySelectorAll('.use_select2_lib');
									for (var rr = 0; rr < sels.length; rr++) {
										var instanceKey = sels[rr].id || sels[rr].name;
										if (window.fc_choices_instances && window.fc_choices_instances[instanceKey]) {
											window.fc_choices_instances[instanceKey].removeActiveItems();
										}
									}
								} else {
									if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
										var sels2 = newForm.querySelectorAll('.use_select2_lib');
										for (var rr2 = 0; rr2 < sels2.length; rr2++) window.jQuery(sels2[rr2]).val('').trigger('change');
									}
									if (window.jQuery && window.jQuery.fn && window.jQuery.fn.chosen) {
										var chSels = newForm.querySelectorAll('.use_chosen_lib');
										for (var rrch = 0; rrch < chSels.length; rrch++) window.jQuery(chSels[rrch]).val('').trigger('chosen:updated');
									}
								}
							});
						})(resetBtns[rb]);
					}
					if (window.fcOverrideNativeSubmit) window.fcOverrideNativeSubmit(fId);
					var currentAction = fcGetAttr(newForm, 'data-fcform_action') || fcGetAttr(newForm, 'action') || baseUrl;
					newForm.setAttribute('action', currentAction);
					newForm.setAttribute('data-fcform_action', currentAction);
				})(formsToRebind[nb]);
			}

			if (window.history && window.history.pushState) window.history.pushState(null, '', fullActionUrl);
		}).catch(function() {
			if (targetContainer) targetContainer.style.opacity = '1';
			fcSetOpacity(moduleContainers, '1');
			if (blocker) blocker.style.display = 'none';
			if (form._originalSubmit) form._originalSubmit.call(form);
			else form.submit();
		});
	});
});
JS
);
endif;
?>

</div>

</div> <!-- mod_flexifilter_wrap -->


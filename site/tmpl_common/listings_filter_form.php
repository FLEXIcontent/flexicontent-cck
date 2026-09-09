<?php
defined( '_JEXEC' ) or die( 'Restricted access' );
if ( \Joomla\CMS\Factory::getApplication()->input->getInt('print', 0) ) return;

ob_start();

file_exists(dirname(__FILE__).DS.'listings_filter_form_body.php')
	? include(dirname(__FILE__).DS.'listings_filter_form_body.php')
	: include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'listings_filter_form_body.php');

$filter_form_body = trim(ob_get_contents());
ob_end_clean();
if ( empty($filter_form_body) ) return;
?>

<div class="fcfilter_form_outer fcfilter_form_component">

<?php
$jcookie = \Joomla\CMS\Factory::getApplication()->input->cookie;
$cookie_name = 'fc_active_TabSlidePage';

$ff_placement = $this->params->get('ff_placement', 0);

if ($ff_placement)
{
	$model = $this->getModel();
	$ff_slider_id =
		($model->_id     ? '_cat_' . $model->_id : '').
		($model->_layout ? '_cat_' . $model->_layout : '')
		;
	$ff_toggle_search_title = \Joomla\CMS\Language\Text::_($this->params->get('ff_toggle_search_title', 'FLEXI_TOGGLE_SEARCH_FORM'))
		. (!empty($active_filters)
			? ' - <span class="ff_filter_active_count badge badge-important">' . $active_filters . '</span>'
			: ''
		);
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

<form action="<?php echo $this->action; ?>" method="post" id="adminForm" >

<?php echo $filter_form_body; ?>

	<input type="hidden" id="filter_order" name="filter_order" value="<?php echo $this->lists['filter_order']; ?>" />
	<input type="hidden" id="filter_order_Dir" name="filter_order_Dir" value="<?php echo $this->lists['filter_order_Dir']; ?>" />

	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="view" value="category" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="cid" value="<?php echo $this->category->id; ?>" />
	<input type="hidden" name="layout" value="<?php echo $this->layout_vars['layout']; ?>" />

	<input type="hidden" name="letter" value="<?php echo htmlspecialchars(\Joomla\CMS\Factory::getApplication()->input->get('letter', '', 'string'), ENT_QUOTES, 'UTF-8'); ?>" id="alpha_index" />

	<?php if (flexicontent_html::initial_list_limited($this->params)) : ?>
	<input type="hidden" name="listall" value="<?php echo \Joomla\CMS\Factory::getApplication()->input->get('listall', 0, 'int'); ?>" />
	<?php endif; ?>

</form>

<?php
if ($ff_placement)
{
	echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endSlide');
	echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endAccordion');
	
	\Joomla\CMS\Factory::getDocument()->addScriptDeclaration("
	(function() {
		function readActiveSlides() {
			var s = fclib_getCookie('" . $cookie_name ."');
			try { s = JSON.parse(s); } catch(e) { s = {}; }
			return s;
		}

		var container = document.getElementById('" . $ff_slider_tagid ."');
		if (!container) return;

		/* Remember which collapse slide is open (Bootstrap 3 events bubble from .collapse to container) */
		container.addEventListener('shown.bs.collapse', function(e) {
			var el = e && e.target ? e.target : null;
			if (!el || !el.id) return;
			var active_slides = readActiveSlides();
			active_slides['" . $ff_slider_tagid ."'] = el.id;
			fclib_setCookie('" . $cookie_name ."', JSON.stringify(active_slides), 7);
		});
		container.addEventListener('hidden.bs.collapse', function(e) {
			var active_slides = readActiveSlides();
			active_slides['" . $ff_slider_tagid ."'] = null;
			fclib_setCookie('" . $cookie_name ."', JSON.stringify(active_slides), 7);
		});

		/* Restore the previously open slide */
		var active_slides = readActiveSlides();
		var collapses = container.querySelectorAll('.collapse');
		for (var i = 0; i < collapses.length; i++) collapses[i].classList.remove('in');
		var activeId = active_slides['" . $ff_slider_tagid ."'];
		if (activeId) {
			var activeEl = document.getElementById(activeId);
			if (activeEl) activeEl.classList.add('in');
		}
	})();
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

<?php
if ($this->params->get('filter_ajax', 0)) :
\Joomla\CMS\Factory::getDocument()->addScriptDeclaration(<<<'JS'
document.addEventListener('DOMContentLoaded', function() {

	if (window.fc_ajax_filter_bound) return;
	window.fc_ajax_filter_bound = true;

	/* --- tiny vanilla helpers (no jQuery) --- */
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

	/* Save the original form-preparation hook, then wrap it: keep the base action intact
	 * (adminFormPrepare appends the filters to it) and only handle the submit/warn part. */
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

			/* choices.js or select2js (fc_attachSelect2 routes internally, skips on mobile) */
			var libEls = cont.querySelectorAll('select.use_select2_lib');
			if (libEls.length && window.fc_attachSelect2 && !window.skip_select2_js) {
				window.fc_attachSelect2(cont, Array.prototype.slice.call(libEls));
			}

			/* chosen library (mobile / .use_chosen_lib) â€” guard against double attach (jQuery plugin, only when available) */
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

		/* Destroy noUiSlider instances BEFORE the DOM replacement */
		if (typeof noUiSlider !== 'undefined') {
			var toDestroy = document.querySelectorAll('[id*="_nouislider"]');
			for (var di = 0; di < toDestroy.length; di++) {
				if (toDestroy[di].noUiSlider) { try { toDestroy[di].noUiSlider.destroy(); } catch (eD) {} }
			}
		}

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

			/* Re-execute inline noUiSlider init scripts after ajax */
			var scripts = doc.querySelectorAll('script:not([src])');
			for (var sp = 0; sp < scripts.length; sp++) {
				var scriptContent = scripts[sp].textContent || '';
				if (scriptContent.indexOf('noUiSlider') === -1 && scriptContent.indexOf('_nouislider') === -1) continue;
				var allSliderEls = document.querySelectorAll('[id*="_nouislider"]');
				for (var si = 0; si < allSliderEls.length; si++) {
					var se = allSliderEls[si];
					if (scriptContent.indexOf(se.id) !== -1 && se.noUiSlider) { try { se.noUiSlider.destroy(); } catch (eD) {} }
				}
				var innerContent = scriptContent;
				var readyMatch = scriptContent.indexOf('document).ready(function()');
				if (readyMatch !== -1) {
					var firstBrace = scriptContent.indexOf('{', readyMatch);
					if (firstBrace !== -1) {
						var depth = 1, pos = firstBrace + 1;
						while (pos < scriptContent.length && depth > 0) {
							var ch = scriptContent[pos];
							if (ch === "'" || ch === '"') {
								var quote = ch; pos++;
								while (pos < scriptContent.length && scriptContent[pos] !== quote) {
									if (scriptContent[pos] === '\\') pos++;
									pos++;
								}
							} else if (ch === '`') {
								pos++;
								while (pos < scriptContent.length && scriptContent[pos] !== '`') {
									if (scriptContent[pos] === '\\') pos++;
									pos++;
								}
							} else if (ch === '/' && pos + 1 < scriptContent.length) {
								if (scriptContent[pos+1] === '/') {
									while (pos < scriptContent.length && scriptContent[pos] !== '\n') pos++;
								} else if (scriptContent[pos+1] === '*') {
									pos += 2;
									while (pos < scriptContent.length - 1 && !(scriptContent[pos] === '*' && scriptContent[pos+1] === '/')) pos++;
									pos++;
								}
							} else {
								if (ch === '{') depth++;
								else if (ch === '}') depth--;
							}
							pos++;
						}
						innerContent = scriptContent.substring(firstBrace + 1, pos - 1);
					}
				}
				try { eval(innerContent); } catch (eE) { console.log('[FC Slider] erreur reinit:', eE.message); }
			}

			/* Re-init JoomlaCalendar after ajax */
			if (typeof JoomlaCalendar === 'function') {
				var calContainers = document.querySelectorAll('.field-calendar');
				for (var ci = 0; ci < calContainers.length; ci++) {
					try { JoomlaCalendar.init(calContainers[ci]); } catch (eC) { console.log('[FC Calendar] erreur init:', eC.message); }
				}
			}

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

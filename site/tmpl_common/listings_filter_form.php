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
	(function($) {
		$(document).ready(function ()
		{
			$('#" . $ff_slider_tagid ."').on('shown', function ()
			{
				var active_slides = fclib_getCookie('" . $cookie_name ."');
				try { active_slides = JSON.parse(active_slides); } catch(e) { active_slides = {}; }
				active_slides['" . $ff_slider_tagid ."'] = $('#" . $ff_slider_tagid ." .in').attr('id');
				fclib_setCookie('" . $cookie_name ."', JSON.stringify(active_slides), 7);
			});

			$('#" . $ff_slider_tagid ."').on('hidden', function ()
			{
				var active_slides = fclib_getCookie('" . $cookie_name ."');
				try { active_slides = JSON.parse(active_slides); } catch(e) { active_slides = {}; }
				active_slides['" . $ff_slider_tagid ."'] = null;
				fclib_setCookie('" . $cookie_name ."', JSON.stringify(active_slides), 7);
			});

			var active_slides = fclib_getCookie('" . $cookie_name ."');
			try { active_slides = JSON.parse(active_slides); } catch(e) { active_slides = {}; }
			$('#" . $ff_slider_tagid ." .collapse').removeClass('in');
			if (!!active_slides['" . $ff_slider_tagid ."'])
			{
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

<?php
if ($this->params->get('filter_ajax', 0)) :
\Joomla\CMS\Factory::getDocument()->addScriptDeclaration("
	jQuery(document).ready(function($) {
		
		if (window.fc_ajax_filter_bound) return;
		window.fc_ajax_filter_bound = true;

		if (typeof window.adminFormPrepare === 'function' && !window._original_adminFormPrepare) {
			window._original_adminFormPrepare = window.adminFormPrepare;
			window.adminFormPrepare = function(form, postprep, task) {
				var \$form = $(form);
				var currentAction = \$form.attr('data-fcform_action') || \$form.attr('action') || '';
				if (currentAction.indexOf('?') !== -1) {
					var cleanBase = currentAction.split('?')[0];
					\$form.attr('action', cleanBase);
					\$form.attr('data-fcform_action', cleanBase);
				}
				window._original_adminFormPrepare(form, 0, task);
				if (postprep == 2) {
					if (task) {
						var taskInput = \$form.find('input[name=\"task\"]');
						if (taskInput.length) taskInput.val(task);
						else \$form.append('<input type=\"hidden\" name=\"task\" value=\"'+task+'\" />');
					}
					\$form.trigger('submit');
					var fc_filter_form_blocker = $('#fc_filter_form_blocker');
					if (fc_filter_form_blocker.length) {
						fc_filter_form_blocker.css('display', 'block');
					}
				} else if (postprep == 1) {
					$('#'+form.id+'_submitWarn').css('display', 'inline-block');
				}
			};
		}

		window.fcOverrideNativeSubmit = function(formId) {
			var nativeForm = document.getElementById(formId);
			if (nativeForm && !nativeForm._originalSubmit) {
				nativeForm._originalSubmit = nativeForm.submit;
				nativeForm.submit = function(task) {
					var \$f = $(this);
					if (task && typeof task === 'string') {
						var taskInput = \$f.find('input[name=\"task\"]');
						if (taskInput.length) taskInput.val(task);
						else \$f.append('<input type=\"hidden\" name=\"task\" value=\"'+task+'\" />');
					}
					\$f.trigger('submit');
				};
			}
		};

		window.fcOverrideNativeSubmit('adminForm');
		$('form[id^=\"moduleFCform_\"]').each(function() {
			window.fcOverrideNativeSubmit(this.id);
		});

		$(document).on('submit', 'form', function(e) {
			var \$form = $(this);
			var isFlexiForm = \$form.attr('id') === 'adminForm' || \$form.attr('id').indexOf('default_form_') === 0 || \$form.closest('.mod_flexifilter_wrapper').length > 0 || \$form.closest('.fcfilter_form_component').length > 0;
			if (!isFlexiForm) return;

			e.preventDefault();

			var targetContainer = $('#flexicontent');
			var moduleContainers = $('.mod_flexifilter_wrapper, .mod_fleximap');

			if (!targetContainer.length && !moduleContainers.length) {
				if (this._originalSubmit) this._originalSubmit.call(this);
				else \$form[0].submit();
				return;
			}

			var fullActionUrl = \$form.attr('action') || '';
			var urlParts = fullActionUrl.split('?');
			var baseUrl = urlParts[0];
			var queryString = urlParts.length > 1 ? urlParts[1] : \$form.serialize();

			if (targetContainer.length) targetContainer.css('opacity', '0.5');
			if (moduleContainers.length) moduleContainers.css('opacity', '0.5');

			var oldAutosubmits = {};
			if (targetContainer.length) {
				var oldForm = document.getElementById('adminForm');
				if (oldForm) oldAutosubmits['adminForm'] = $(oldForm).attr('data-fc-autosubmit') || '2';
			}
			moduleContainers.each(function() {
				var modForm = $(this).find('form')[0];
				if (modForm && modForm.id) oldAutosubmits[modForm.id] = $(modForm).attr('data-fc-autosubmit') || '2';
			});

			var fc_filter_form_blocker = $('#fc_filter_form_blocker');
			if (fc_filter_form_blocker.length) {
				fc_filter_form_blocker.css('display', 'block');
			}

			// Détruire les instances noUiSlider AVANT le remplacement DOM
			if (typeof noUiSlider !== 'undefined') {
				var toDestroy = document.querySelectorAll('[id*=\"_nouislider\"]');
				for (var di = 0; di < toDestroy.length; di++) {
					if (toDestroy[di].noUiSlider) {
						try { toDestroy[di].noUiSlider.destroy(); } catch(eD) {}
					}
				}
			}

			$.ajax({
				url: baseUrl, 
				type: 'POST',
				data: queryString, 
				success: function(response) {
					var parser = new DOMParser();
					var doc = parser.parseFromString(response, 'text/html');
					var parsedDoc = $(doc);
					
					if (targetContainer.length) {
						var \$newContent = parsedDoc.find('#flexicontent');
						if (\$newContent.length === 0 && parsedDoc.filter('#flexicontent').length > 0) {
							\$newContent = parsedDoc.filter('#flexicontent');
						}
						if (\$newContent.length) {
							targetContainer.html(\$newContent.html());
						}
						targetContainer.css('opacity', '1');
					}

					if (moduleContainers.length) {
						moduleContainers.each(function() {
							var \$modWrapper = $(this);
							if (!document.body.contains(\$modWrapper[0])) return;
							var modId = \$modWrapper.attr('id');
							if (modId) {
								var \$newMod = parsedDoc.find('#' + modId);
								if (\$newMod.length === 0 && parsedDoc.filter('#' + modId).length > 0) {
									\$newMod = parsedDoc.filter('#' + modId);
								}
								if (\$newMod.length) {
									\$modWrapper.html(\$newMod.html());
								}
							}
							\$modWrapper.css('opacity', '1');
						});
					}
					
					if (fc_filter_form_blocker.length) {
						fc_filter_form_blocker.css('display', 'none');
					}

					if (typeof $.fn.select2 !== 'undefined') {
						if (targetContainer.length) targetContainer.find('select.use_select2_lib').select2();
						if (moduleContainers.length) moduleContainers.find('select.use_select2_lib').select2();
					}

					//allow to update nouislider with search is to ajaxed, as nouislider does not support dynamic content, we need to reinit it
					parsedDoc.find('script:not([src])').each(function() {
						var scriptContent = $(this).html();
						if (scriptContent.indexOf('noUiSlider') === -1 && scriptContent.indexOf('_nouislider') === -1) return;
						var allSliderEls = document.querySelectorAll('[id*=\"_nouislider\"]');
						for (var si = 0; si < allSliderEls.length; si++) {
							var se = allSliderEls[si];
							if (scriptContent.indexOf(se.id) !== -1 && se.noUiSlider) {
								try { se.noUiSlider.destroy(); } catch(eD) {}
							}
						}
						var innerContent = scriptContent;
						var readyMatch = scriptContent.indexOf('document).ready(function()');
						if (readyMatch !== -1) {
							var firstBrace = scriptContent.indexOf('{', readyMatch);
							if (firstBrace !== -1) {
								// Compter les accolades pour trouver la fermeture correcte du ready
								var depth = 1;
								var pos = firstBrace + 1;
								while (pos < scriptContent.length && depth > 0) {
									var ch = scriptContent[pos];
									if (ch === '{') depth++;
									else if (ch === '}') depth--;
									pos++;
								}
								// pos pointe maintenant après le } fermant du ready
								// on extrait entre firstBrace+1 et pos-1
								innerContent = scriptContent.substring(firstBrace + 1, pos - 1);
							}
						}
						try {
							eval(innerContent);
							console.log('[FC Slider] réinitialisé');
						} catch(eE) {
							console.log('[FC Slider] erreur reinit:', eE.message);
						}
					});

					// update JoomlaCalendar with ajax search, as it does not support dynamic content, we need to reinit it
					if (typeof JoomlaCalendar === 'function') {
						var calContainers = document.querySelectorAll('.field-calendar');
						for (var ci = 0; ci < calContainers.length; ci++) {
							try { JoomlaCalendar.init(calContainers[ci]); } catch(eC) {
								console.log('[FC Calendar] erreur init:', eC.message);
							}
						}
					}

					var formsToRebind = [];
					var newAdminForm = document.getElementById('adminForm');
					if (newAdminForm) formsToRebind.push(newAdminForm);
					$('.mod_flexifilter_wrapper form').each(function() {
						formsToRebind.push(this);
					});

					formsToRebind.forEach(function(newForm) {
						var fId = newForm.id;
						if (oldAutosubmits[fId]) {
							$(newForm).attr('data-fc-autosubmit', oldAutosubmits[fId]);
							$(newForm.elements).filter('input:not(.fc_autosubmit_exclude):not(.select2-input), select:not(.fc_autosubmit_exclude)').on('change', function() {
								if (window.adminFormPrepare) window.adminFormPrepare(newForm, oldAutosubmits[fId]);
							});
						}
						$(newForm).find('.fc_button.button_reset').on('click', function() {
							$(newForm).find('.use_select2_lib').select2('val', '');
						});
						if (window.fcOverrideNativeSubmit) {
							window.fcOverrideNativeSubmit(fId);
						}
						var currentAction = $(newForm).attr('data-fcform_action') || $(newForm).attr('action') || baseUrl;
						var cleanBase = currentAction.split('?')[0];
						$(newForm).attr('action', cleanBase);
						$(newForm).attr('data-fcform_action', cleanBase);
					});

					if (window.history && window.history.pushState) {
						window.history.pushState(null, '', fullActionUrl);
					}
				},
				error: function() {
					if (targetContainer.length) targetContainer.css('opacity', '1');
					if (moduleContainers.length) moduleContainers.css('opacity', '1');
					if (fc_filter_form_blocker.length) {
						fc_filter_form_blocker.css('display', 'none');
					}
					if (\$form[0]._originalSubmit) \$form[0]._originalSubmit.call(\$form[0]);
					else \$form[0].submit();
				}
			});
		});
	});
");
endif;
?>

</div>
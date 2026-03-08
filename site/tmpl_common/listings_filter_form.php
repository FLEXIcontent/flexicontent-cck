<?php
defined( '_JEXEC' ) or die( 'Restricted access' );
if ( \Joomla\CMS\Factory::getApplication()->input->getInt('print', 0) ) return;

ob_start();

/**
 * Body of form for (a) Text search, Field Filters, Alpha-Index, Items Total Statistics, Selectors(e.g. per page, orderby)
 * If customizing via CSS rules or JS scripts is not enough, then please copy the following file here to customize the HTML too
 *
 * First try current folder, otherwise load from common folder
 */

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

// FORM in slider
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
// FORM in slider
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

			// Hide default active slide
			$('#" . $ff_slider_tagid ." .collapse').removeClass('in');

			if (!!active_slides['" . $ff_slider_tagid ."'])
			{
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

<?php
// Custom AJAX Form Submission for Flexicontent
if ($this->params->get('filter_ajax', 0)) :
\Joomla\CMS\Factory::getDocument()->addScriptDeclaration("
	jQuery(document).ready(function($) {
		
		// 1. Monkey-patch adminFormPrepare
		if (typeof window.adminFormPrepare === 'function' && !window._original_adminFormPrepare) {
			window._original_adminFormPrepare = window.adminFormPrepare;
			window.adminFormPrepare = function(form, postprep, task) {
				var \$form = $(form);

				// Clean the base internally so it doesn't accumulate 
				var currentAction = \$form.attr('data-fcform_action') || \$form.attr('action') || '';
				if (currentAction.indexOf('?') !== -1) {
					var cleanBase = currentAction.split('?')[0];
					\$form.attr('action', cleanBase);
					\$form.attr('data-fcform_action', cleanBase);
				}

				// We execute the original function which correctly builds the query string and puts it in form.action
				window._original_adminFormPrepare(form, 0, task);
				
				if (postprep == 2) {
					// Add task manually to the form if it was passed 
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

		// 2. Override native submit
		var nativeForm = document.getElementById('adminForm');
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

		// 3. The main AJAX submission logic
		$(document).on('submit', '#adminForm', function(e) {
			var \$form = $(this);
			
			// Only apply AJAX to the flexicontent filter form
			if (\$form.find('input[name=\"option\"][value=\"com_flexicontent\"]').length === 0) return;

			// Prevent standard page reload
			e.preventDefault();

			// Main container to replace
			var targetContainer = $('#flexicontent');
			if (!targetContainer.length) {
				if (this._originalSubmit) this._originalSubmit.call(this);
				else \$form[0].submit();
				return;
			}

			// We need to use the final action string generated by adminFormPrepare 
			// because that string contains ALL active filters, even if the inputs were disabled.
			var fullActionUrl = \$form.attr('action') || '';
			
			var urlParts = fullActionUrl.split('?');
			var baseUrl = urlParts[0];
			var queryString = urlParts.length > 1 ? urlParts[1] : \$form.serialize();

			// Add a simple loading state
			targetContainer.css('opacity', '0.5');

			// Snag the autosubmitType from the old form before we destroy it
			var oldAutosubmitType = \$form.attr('data-fc-autosubmit') || '2';

			// Send AJAX POST request to fetch the updated page
			$.ajax({
				url: baseUrl, 
				type: 'POST',
				data: queryString, 
				success: function(response) {
					var parsedDoc = $($.parseHTML(response, document, true));
					
					var \$newContent = parsedDoc.find('#flexicontent');
					if (\$newContent.length === 0 && parsedDoc.filter('#flexicontent').length > 0) {
						\$newContent = parsedDoc.filter('#flexicontent');
					}
					
					if (\$newContent.length) {
						targetContainer.html(\$newContent.html());
						targetContainer.css('opacity', '1');
						$('#fc_filter_form_blocker').css('display', 'none');

						// Restore plugins (e.g. Select2 if flexicontent uses it)
						if (typeof $.fn.select2 !== 'undefined') {
							targetContainer.find('select.use_select2_lib').select2();
						}

						// Re-bind FLEXIcontent auto-submit events that were lost during DOM replacement
						var newForm = document.getElementById('adminForm');
						if (newForm) {
							$(newForm).attr('data-fc-autosubmit', oldAutosubmitType);
							
							$(newForm.elements).filter('input:not(.fc_autosubmit_exclude):not(.select2-input), select:not(.fc_autosubmit_exclude)').on('change', function() {
								if (window.adminFormPrepare) window.adminFormPrepare(newForm, oldAutosubmitType);
							});
							
							// Re-bind reset button to clear select2
							$(newForm).find('.fc_button.button_reset').on('click', function() {
								$(newForm).find('.use_select2_lib').select2('val', '');
							});
						}

						// Update browser URL
						// We use fullActionUrl because adminFormPrepare correctly builds the clean SEF URL with active query parameters
						if (window.history && window.history.pushState) {
							window.history.pushState(null, '', fullActionUrl);
						}
						
						// IMPORTANT FIX: 
						// adminFormPrepare uses the form's action or data-fcform_action to build upcoming URLs.
						// If we replaced the HTML, the form might have picked up the 'dirty' URL from the new browser state.
						// We force the form's Base URL to be totally clean to prevent accumulating GET parameters on next click.
						var currentAction = \$form.attr(\"data-fcform_action\") || \$form.attr(\"action\") || baseUrl;
						var cleanBase = currentAction.split('?')[0];
						if (newForm) {
							\$(newForm).attr('action', cleanBase);
							\$(newForm).attr('data-fcform_action', cleanBase);
						}

				} else {
					if (\$form[0]._originalSubmit) \$form[0]._originalSubmit.call(\$form[0]);
					else \$form[0].submit();
				}
			},
			error: function() {
				targetContainer.css('opacity', '1');
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
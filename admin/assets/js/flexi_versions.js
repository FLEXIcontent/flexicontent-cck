/**
 * FLEXIcontent version history panel
 *
 * The history table is always inline in the item's Versions tab. The
 * `versionsplacement` setting only controls how the comparison link opens:
 *   - inline: a new browser tab (handled by target="_blank" in the renderer)
 *   - popup:  a Joomla dialog (handled by data-joomla-dialog in the renderer)
 *
 * This script only handles pagination, tooltips, restore and the permanent
 * backup toggle.
 */
(function ($) {
	'use strict';

	function initPanel(panel)
	{
		if (panel.data('fcVersionsInit')) return;
		panel.data('fcVersionsInit', true);

		var pageCount = parseInt(panel.attr('data-pagecount'), 10) || 1;
		var currentPage = parseInt(panel.attr('data-current-page'), 10) || 1;
		var ajaxUrl = panel.attr('data-ajax-url') || '';
		var confirmRestore = panel.attr('data-confirm-restore') || '';
		var table = panel.find('[data-fc-versions-table]');
		var pager = panel.find('#fc_pager');

		function refreshTools()
		{
			// Bootstrap 5 does not necessarily expose the jQuery plugins; fail gracefully
			if (typeof $.fn.tooltip === 'function')
			{
				panel.find('.hasTooltip').tooltip({ html: true, container: panel });
			}
			if (typeof $.fn.popover === 'function')
			{
				panel.find('.hasPopover').popover({ html: true, container: panel, trigger: 'hover focus' });
			}
		}

		function loadPage(page)
		{
			currentPage = page;
			if (!ajaxUrl) return;

			$.ajax({ url: ajaxUrl + page, dataType: 'html' })
				.done(function (html)
				{
					table.html(html);
					pager.pager({ pagenumber: page, pagecount: pageCount, buttonClickCallback: loadPage });
					refreshTools();
				});
		}

		// Register the click handlers first, so a failure in tooltip/pager setup
		// can never break restore or the permanent backup toggle.
		panel.on('click', '.fc-version-restore', function ()
		{
			var url = $(this).attr('data-restore-url');
			if (url && (!confirmRestore || window.confirm(confirmRestore)))
			{
				window.location.href = url;
			}
			return false;
		});

		// Permanent backup: Ajax toggle, then refresh the current page of the table
		panel.on('click', 'a.fc-version-keep', function ()
		{
			var url = $(this).attr('data-keep-url');
			if (!url) return false;

			$.getJSON(url, function (data)
			{
				if (data && data.success)
				{
					loadPage(currentPage);
				}
			});
			return false;
		});

		// Tooltips and pagination are cosmetic/optional; never let them abort this panel
		try
		{
			refreshTools();

			if (pager.length && ajaxUrl && typeof $.fn.pager === 'function')
			{
				pager.pager({ pagenumber: currentPage, pagecount: pageCount, buttonClickCallback: loadPage });
			}
		}
		catch (e)
		{
			if (window.console && window.console.warn) window.console.warn('FLEXIcontent versions panel:', e);
		}
	}

	$(function ()
	{
		$('[data-fc-versions-panel]').each(function () { initPanel($(this)); });
	});
})(jQuery);

/**
 * fcvote.js — Vanilla (jQuery-free) voting field script
 *
 * Handles: star hover preview, vote submission (fetch),
 * counter flash, message bubbles, review form (iframe modal),
 * accordion toggle, modal widget toggle.
 *
 * Compatible with both jQuery-free (Choices.js) and jQuery modes.
 */
(function() {
	'use strict';

	// Joomla Root and Base URL (set via addScriptDeclaration in html.php)
	window.root_url = window.jroot_url_fc || '';
	window.base_url = window.jbase_url_fc || '';

	var under_vote = false;


	/* ================================================================
	 *  MESSAGE BUBBLE
	 * ================================================================ */

	function fcvote_show_message(msgEl, html) {
		if (!html || !msgEl) return;

		// Parse server message HTML, strip wrapper and close button
		var div = document.createElement('div');
		div.innerHTML = html;
		var closeBtns = div.querySelectorAll('button.close');
		for (var i = 0; i < closeBtns.length; i++) {
			closeBtns[i].parentNode.removeChild(closeBtns[i]);
		}

		var is_warning = div.querySelector('.fc-warning') !== null;
		var mssg_inner = div.querySelector('.fc-mssg');
		var inner_html = mssg_inner ? mssg_inner.innerHTML : div.innerHTML;

		msgEl.style.marginLeft = '';
		msgEl.innerHTML = inner_html;
		msgEl.classList.remove('fcvote_message--success', 'fcvote_message--warning');
		msgEl.classList.add(is_warning ? 'fcvote_message--warning' : 'fcvote_message--success');
		msgEl.classList.add('fcvote_message-visible');

		// Keep the bubble inside the viewport (important on mobile)
		var pad = 8;
		var rect = msgEl.getBoundingClientRect();
		var win_width = window.innerWidth || document.documentElement.clientWidth;
		var shift = 0;
		if (rect.left < pad) shift = pad - rect.left;
		else if (rect.right > win_width - pad) shift = (win_width - pad) - rect.right;
		if (shift) msgEl.style.marginLeft = Math.round(shift) + 'px';

		clearTimeout(msgEl._fcvote_hide_timer);
		msgEl._fcvote_hide_timer = setTimeout(function() {
			msgEl.classList.remove('fcvote_message-visible');
		}, 2000);
	}


	/* ================================================================
	 *  STAR HOVER PREVIEW (event delegation)
	 * ================================================================ */

	document.addEventListener('mouseover', function(e) {
		var link = e.target.closest('a.fc_dovote');
		if (!link) return;
		var vote_list = link.closest('.fcvote_list');
		if (!vote_list) return;
		var total = vote_list.querySelectorAll('li.voting-links').length;
		var rating = parseInt(link.textContent, 10);
		if (!total || !rating) return;
		vote_list.classList.add('fcvote-hovering');
		var hover = vote_list.querySelector('li.hover-rating');
		if (hover) hover.style.width = (100 * rating / total) + '%';
	});

	document.addEventListener('mouseout', function(e) {
		var ul = e.target.closest('ul.fcvote_list');
		if (!ul) return;
		var to = e.relatedTarget;
		if (to && ul.contains(to)) return;
		ul.classList.remove('fcvote-hovering');
		var hover = ul.querySelector('li.hover-rating');
		if (hover) hover.style.width = '0';
	});


	/* ================================================================
	 *  VOTE SUBMISSION (event delegation on .fc_dovote clicks)
	 * ================================================================ */

	document.addEventListener('click', function(e) {
		var link = e.target.closest('a.fc_dovote');
		if (!link) return;
		if (under_vote) return;

		e.preventDefault();
		under_vote = true;

		var fcvote = link.closest('.fcvote');
		var voting_group = fcvote ? fcvote.closest('.voting-group, .fieldname-group') : null;
		if (voting_group) voting_group.style.opacity = '0.5';

		var vote_list = link.closest('.fcvote_list');
		var data_arr = link.getAttribute('data-rel').split('_');

		// ID for item being voted
		var itemID = data_arr[0];

		// Voting characteristic (defaults to 'main' if not set)
		var xid = (typeof data_arr[1] !== 'undefined' && data_arr[1]) ? data_arr[1] : 'main';

		var xid_msg = fcvote ? fcvote.querySelector('.fcvote_message') : null;
		var main_msg = voting_group ? (voting_group.querySelector('.fieldname-row_main .fcvote_message') || xid_msg) : xid_msg;

		var xid_cnt = fcvote ? fcvote.querySelector('.fcvote-count') : null;
		var main_cnt = voting_group ? (voting_group.querySelector('.fieldname-row_main .fcvote-count') || null) : null;

		var xid_rating = vote_list ? vote_list.querySelector('.current-rating') : null;
		var main_rating = null;
		if (voting_group) {
			var main_row = voting_group.querySelector('.fieldname-row_main');
			if (main_row) {
				var main_vote_list = main_row.querySelector('.fcvote_list');
				if (main_vote_list) main_rating = main_vote_list.querySelector('.current-rating');
			}
		}

		var _htmlrating = xid_cnt ? xid_cnt.innerHTML : '';
		var _htmlrating_main = main_cnt ? main_cnt.innerHTML : '';

		var rating = link.textContent;

		var fc_csrf_token = (typeof Joomla !== 'undefined' && Joomla.getOptions) ? (Joomla.getOptions('csrf.token') || '') : '';
		var voteurl = base_url
			+ 'index.php?option=com_flexicontent&task=reviews.ajaxvote&user_rating=' + rating + '&cid=' + itemID + '&xid=' + xid
			+ (fc_csrf_token ? '&' + fc_csrf_token + '=1' : '');

		fetch(voteurl, {
			method: 'POST',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			body: new URLSearchParams({ lang: (typeof window.fc_sef_lang !== 'undefined' ? window.fc_sef_lang : '') })
		})
		.then(function(r) { return r.json(); })
		.then(function(data) {
			// Update rating bar widths
			if (data.percentage && xid_rating) xid_rating.style.width = data.percentage + '%';
			if (data.percentage_main && main_rating) main_rating.style.width = data.percentage_main + '%';

			// Update counter HTML
			if (data.htmlrating) _htmlrating = data.htmlrating;
			if (data.htmlrating_main) _htmlrating_main = data.htmlrating_main;

			// Counter flash: show "you voted!" text briefly, then restore rating count
			if (xid_cnt && data.html && data.htmlrating) {
				xid_cnt.innerHTML = data.html;
				xid_cnt.style.display = '';
				setTimeout(function() { xid_cnt.classList.add('fcvote-flash'); }, 2000);
				setTimeout(function() {
					xid_cnt.classList.remove('fcvote-flash');
					if (_htmlrating && _htmlrating.trim()) {
						xid_cnt.innerHTML = _htmlrating;
					}
				}, 3000);
			} else if (xid_cnt && _htmlrating && _htmlrating.trim()) {
				xid_cnt.innerHTML = _htmlrating;
				xid_cnt.style.display = '';
			}

			// Main counter flash
			if (main_cnt) {
				if (data.html_main && data.htmlrating_main) {
					main_cnt.innerHTML = data.html_main;
					main_cnt.style.display = '';
					setTimeout(function() { main_cnt.classList.add('fcvote-flash'); }, 2000);
					setTimeout(function() {
						main_cnt.classList.remove('fcvote-flash');
						if (_htmlrating_main && _htmlrating_main.trim()) {
							main_cnt.innerHTML = _htmlrating_main;
						} else {
							main_cnt.innerHTML = '';
						}
					}, 3000);
				} else if (_htmlrating_main && _htmlrating_main.trim()) {
					main_cnt.innerHTML = _htmlrating_main;
				}
			}

			// Show vote messages as auto-hiding bubbles above the stars
			if (data.message) fcvote_show_message(xid_msg, data.message);
			if (data.message_main) fcvote_show_message(main_msg, data.message_main);

			// Clear hover preview (touch devices do not fire mouseleave)
			if (voting_group) {
				var hovers = voting_group.querySelectorAll('ul.fcvote_list');
				for (var i = 0; i < hovers.length; i++) {
					hovers[i].classList.remove('fcvote-hovering');
					var h = hovers[i].querySelector('li.hover-rating');
					if (h) h.style.width = '0';
				}
			}

			under_vote = false;
			if (voting_group) voting_group.style.opacity = '';
		})
		.catch(function(err) {
			alert('Error: ' + (err.message || err));
			under_vote = false;
			if (voting_group) voting_group.style.opacity = '';
		});
	});


	/* ================================================================
	 *  REVIEW FORM — Open (DOM modal, loads getreviewform HTML)
	 * ================================================================ */

	function fcvote_open_review_form(tagid, content_id, review_type) {
		var url = base_url
			+ 'index.php?option=com_flexicontent&task=getreviewform&format=raw&tagid=' + tagid
			+ '&content_id=' + content_id + '&review_type=' + review_type
			+ '&lang=' + (typeof window.fc_sef_lang !== 'undefined' ? window.fc_sef_lang : '');

		// Reuse the (hidden) inline box if present, otherwise create one
		var box = document.getElementById(tagid);
		if (!box) {
			box = document.createElement('div');
			box.id = tagid;
			box.className = 'fcvote_review_form_box';
		}

		// Open the modal (box is moved inside the modal, restored on close)
		var overlay = fcvote_showAsDialog(box, 700, 660, null, { title: window.FC_VOTE_REVIEW_TITLE || '' });

		// Loading placeholder while the form is fetched
		box.innerHTML = '<div class="fc-mssg fc-info fc-nobgimage"><span class="fc-spinner"></span>&nbsp;Loading...</div>';

		fetch(url, {
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		})
		.then(function(r) { return r.json(); })
		.then(function(data) {
			if (!box.parentNode) return; // modal was closed meanwhile
			box.innerHTML = (data && data.html) ? data.html
				: '<div class="fc-mssg fc-warning fc-nobgimage">' + ((data && data.error) ? data.error : 'Error loading review form') + '</div>';
		})
		.catch(function(err) {
			if (!box.parentNode) return;
			box.innerHTML = '<div class="fc-mssg fc-warning fc-nobgimage">Error: ' + (err.message || err) + '</div>';
		});

		return overlay;
	}

	// Expose globally for inline onclick handlers
	window.fcvote_open_review_form = fcvote_open_review_form;


	/* ================================================================
	 *  REVIEW FORM — Submit (fetch POST to storereviewform)
	 * ================================================================ */

	function fcvote_submit_review_form(tagid, form) {
		var box = document.getElementById(tagid);
		var box_loading = document.getElementById(tagid + '_loading');
		var in_modal = !!document.querySelector('.fcvote_modal_overlay.fcvote_modal_visible');

		if (typeof form.checkValidity === 'function') {
			if (!form.checkValidity()) {
				fcvote_submit_review_form_show_validation(form, box_loading || box);
				return;
			}
		}

		var url = root_url
			+ 'index.php?option=com_flexicontent&format=raw&task=storereviewform';

		if (box_loading && !in_modal) {
			box_loading.innerHTML = '';
			box_loading.classList.add('ajax-loader');
			box_loading.style.display = 'inline-block';
		}

		fetch(url, {
			method: 'POST',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			body: new FormData(form)
		})
		.then(function(r) { return r.json(); })
		.then(function(data) {
			if (box_loading && !in_modal) {
				box_loading.innerHTML = '';
				box_loading.classList.remove('ajax-loader');
				box_loading.style.display = 'none';
			}
			if (data.html) {
				if (data.error) {
					if (in_modal) {
						if (box) {
							var eBox = document.createElement('div');
							eBox.innerHTML = data.html;
							var firstForm = box.querySelector('form');
							if (firstForm) firstForm.insertAdjacentElement('beforebegin', eBox.firstChild || eBox);
							else box.insertAdjacentElement('afterbegin', eBox.firstChild || eBox);
						}
					} else if (box_loading) {
						box_loading.innerHTML = data.html;
						box_loading.style.display = 'block';
					}
				} else if (box) {
					box.innerHTML = data.html;
					box.style.display = '';
				}
			}
		})
		.catch(function(err) {
			if (box_loading && !in_modal) {
				box_loading.innerHTML = '';
				box_loading.classList.remove('ajax-loader');
				box_loading.style.display = 'none';
			}
			alert('Error: ' + (err.message || err));
		});
	}

	window.fcvote_submit_review_form = fcvote_submit_review_form;


	/* ================================================================
	 *  REVIEW FORM — Validation display
	 * ================================================================ */

	function fcvote_submit_review_form_show_validation(form, errorBox) {
		var errorList = document.createElement('div');
		if (errorBox) {
			errorBox.innerHTML = '';
			errorBox.appendChild(errorList);
		}

		var invalids = form.querySelectorAll(':invalid');
		for (var i = 0; i < invalids.length; i++) {
			var node = invalids[i];
			var label = document.querySelector('label[for="' + node.id + '"]');
			var labelText = label ? label.innerHTML : '';
			var message = node.validationMessage || 'Invalid value.';

			var errDiv = document.createElement('div');
			errDiv.className = 'fc-mssg fc-warning fc-nobgimage';
			errDiv.innerHTML = '<button type="button" class="close" data-dismiss="alert">&times;</button><b>' + labelText + '</b>: ' + message;
			errorList.appendChild(errDiv);

			if (!node.classList.contains('needs-validation')) {
				node.classList.add('needs-validation');
				node.addEventListener('blur', function() { this.classList.remove('invalid'); });
			}
			node.classList.add('invalid');
		}
	}

	window.fcvote_submit_review_form_show_validation = fcvote_submit_review_form_show_validation;


	/* ================================================================
	 *  MODAL: Vanilla iframe dialog (fcvote_show_dialog)
	 *
	 *  Creates: overlay → modal → header + iframe
	 *  Registers in window.fc_Dialogs for fc_closeDialog compat.
	 * ================================================================ */

	function fcvote_show_dialog(url, w, h, title) {
		// Remove any existing fcvote modal
		fcvote_remove_modal();

		// Overlay
		var overlay = document.createElement('div');
		overlay.id = 'fcvote_modal_overlay';
		overlay.className = 'fcvote_modal_overlay';

		// Modal container
		var modal = document.createElement('div');
		modal.className = 'fcvote_modal';

		// Header
		var header = document.createElement('div');
		header.className = 'fcvote_modal_header';

		var titleEl = document.createElement('span');
		titleEl.className = 'fcvote_modal_title';
		titleEl.textContent = title || '';

		var closeBtn = document.createElement('button');
		closeBtn.className = 'fcvote_modal_close';
		closeBtn.innerHTML = '&times;';
		closeBtn.setAttribute('aria-label', 'Close');

		header.appendChild(titleEl);
		header.appendChild(closeBtn);

		// Iframe
		var iframe = document.createElement('iframe');
		iframe.src = url;
		iframe.style.visibility = 'hidden';
		iframe.addEventListener('load', function() {
			iframe.style.visibility = 'visible';
		});

		modal.appendChild(header);
		modal.appendChild(iframe);
		overlay.appendChild(modal);
		document.body.appendChild(overlay);

		// Animate in
		requestAnimationFrame(function() {
			overlay.classList.add('fcvote_modal_visible');
		});

		// Close handlers
		closeBtn.addEventListener('click', function() { fcvote_remove_modal(); });
		overlay.addEventListener('click', function(e) {
			if (e.target === overlay) fcvote_remove_modal();
		});
		document.addEventListener('keydown', fcvote_modal_keydown);

		// Prevent body scroll
		document.body.classList.add('fc-no-scroll');

		// Register in fc_Dialogs for admin iframe auto-close compatibility
		if (typeof window.fc_Dialogs === 'undefined') window.fc_Dialogs = {};
		window.fc_Dialogs['fc_modal_popup_container'] = {
			dialog: function(method) {
				if (method === 'close') fcvote_remove_modal();
			}
		};

		return overlay;
	}

	window.fcvote_show_dialog = fcvote_show_dialog;


	/* ================================================================
	 *  MODAL: Vanilla DOM element dialog (fcvote_showAsDialog)
	 *
	 *  Moves a DOM element into a modal overlay for display.
	 *  Restores it to its original parent on close.
	 *  Compatible with the fc_showAsDialog signature:
	 *    fcvote_showAsDialog(box, winwidth, winheight, closeFunc, params)
	 * ================================================================ */

	function fcvote_showAsDialog(box, winwidth, winheight, closeFunc, params) {
		params = params || {};

		if (!box) return null;

		// Remove any existing fcvote modal
		fcvote_remove_modal();

		var title = params.title || '';

		// Save original parent and next sibling for restoration
		var parent = box.parentNode;
		var nextSibling = box.nextSibling;
		var origDisplay = box.style.display;

		// Overlay
		var overlay = document.createElement('div');
		overlay.id = 'fcvote_modal_overlay';
		overlay.className = 'fcvote_modal_overlay';

		// Modal container
		var modal = document.createElement('div');
		modal.className = 'fcvote_modal';
		if (winwidth) {
			modal.style.width = Math.min(winwidth, window.innerWidth * 0.9) + 'px';
			modal.style.maxWidth = '90vw';
		}
		if (winheight) {
			modal.style.height = Math.min(winheight, window.innerHeight * 0.85) + 'px';
			modal.style.maxHeight = '85vh';
		}

		// Header
		var header = document.createElement('div');
		header.className = 'fcvote_modal_header';

		var titleEl = document.createElement('span');
		titleEl.className = 'fcvote_modal_title';
		titleEl.textContent = title;

		var closeBtn = document.createElement('button');
		closeBtn.className = 'fcvote_modal_close';
		closeBtn.innerHTML = '&times;';
		closeBtn.setAttribute('aria-label', 'Close');

		header.appendChild(titleEl);
		header.appendChild(closeBtn);

		modal.appendChild(header);
		modal.appendChild(box);
		overlay.appendChild(modal);
		document.body.appendChild(overlay);

		// Make the moved element visible inside modal (override data-placement hiding)
		box.style.display = '';

		// Animate in
		requestAnimationFrame(function() {
			overlay.classList.add('fcvote_modal_visible');
		});

		// Close handler
		function closeModal() {
			// Restore element to original position
			if (nextSibling && nextSibling.parentNode === parent) {
				parent.insertBefore(box, nextSibling);
			} else if (parent) {
				parent.appendChild(box);
			}
			box.style.display = origDisplay;

			fcvote_remove_modal();

			// Execute close function
			if (typeof closeFunc === 'function') closeFunc(box);
			else if (closeFunc === 1) window.location.reload(false);
		}

		closeBtn.addEventListener('click', closeModal);
		overlay.addEventListener('click', function(e) {
			if (e.target === overlay) closeModal();
		});
		document.addEventListener('keydown', fcvote_modal_keydown);

		// Store close function for fc_closeDialog
		overlay._fcvote_closeFn = closeModal;

		// Prevent body scroll
		document.body.classList.add('fc-no-scroll');

		// Register in fc_Dialogs
		if (typeof window.fc_Dialogs === 'undefined') window.fc_Dialogs = {};
		window.fc_Dialogs['fc_modal_popup_container'] = {
			dialog: function(method) {
				if (method === 'close') closeModal();
			}
		};

		return overlay;
	}

	window.fcvote_showAsDialog = fcvote_showAsDialog;


	/* ================================================================
	 *  MODAL HELPERS
	 * ================================================================ */

	function fcvote_remove_modal() {
		var overlay = document.getElementById('fcvote_modal_overlay');
		if (overlay) {
			overlay.classList.remove('fcvote_modal_visible');
			setTimeout(function() {
				if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
			}, 250);
		}
		document.removeEventListener('keydown', fcvote_modal_keydown);

		// Restore body scroll
		document.body.classList.remove('fc-no-scroll');
	}

	function fcvote_modal_keydown(e) {
		if (e.key === 'Escape' || e.keyCode === 27) {
			fcvote_remove_modal();
		}
	}


	/* ================================================================
	 *  fc_closeDialog COMPATIBILITY
	 *
	 *  Admin reviews controller saves → window.parent.fc_closeDialog('fc_modal_popup_container')
	 *  If jQuery UI version exists (flexi-lib.js loaded), delegate to it.
	 *  Otherwise close our vanilla modal.
	 * ================================================================ */

	if (typeof window.fc_closeDialog !== 'function') {
		window.fc_closeDialog = function(name) {
			// Prefer jQuery UI dialog when available
			if (window.fc_Dialogs && window.fc_Dialogs[name] && typeof window.fc_Dialogs[name].dialog === 'function') {
				return window.fc_Dialogs[name].dialog('close');
			}
			// Vanilla fallback
			fcvote_remove_modal();
			return false;
		};
	}

})();

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

	// Joomla Root and Base URL (set via addScriptDeclaration in html.php).
	// Resolved at call time: this file is loaded before that inline declaration runs,
	// so reading them here would give '' and produce a relative (SEF-path) AJAX URL.
	function fcvote_base_url() {
		if (window.jbase_url_fc) return window.jbase_url_fc;
		var p = (typeof Joomla !== 'undefined' && Joomla.getOptions) ? Joomla.getOptions('system.paths') : null;
		return (p && p.baseFull) ? p.baseFull : document.baseURI;
	}
	function fcvote_root_url() {
		if (window.jroot_url_fc) return window.jroot_url_fc;
		var p = (typeof Joomla !== 'undefined' && Joomla.getOptions) ? Joomla.getOptions('system.paths') : null;
		return (p && p.rootFull) ? p.rootFull : document.baseURI;
	}

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

		// The bubble is displayed by a single element of the body (position: fixed), the element of the voting box can be hidden / clipped by its parents (e.g. the cards of a list)
		var box = msgEl.closest ? msgEl.closest('.fcvote') : null;
		var list = box ? box.querySelector('ul.fcvote_list') : null;
		var ref = (list || msgEl).getBoundingClientRect();

		if (!fcvote_bubble_el) {
			fcvote_bubble_el = document.createElement('div');
			fcvote_bubble_el.className = 'fcvote-bubble';
			fcvote_bubble_el.setAttribute('role', 'status');
			document.body.appendChild(fcvote_bubble_el);
		}

		fcvote_bubble_el.innerHTML = inner_html;
		fcvote_bubble_el.classList.toggle('fcvote-bubble--warning', is_warning);
		fcvote_place_above(fcvote_bubble_el, ref.left + ref.width / 2, ref.top - 10);

		clearTimeout(fcvote_bubble_timer);
		fcvote_bubble_timer = setTimeout(fcvote_hide_bubble, 2000);
	}

	var fcvote_bubble_el = null;
	var fcvote_bubble_timer = null;

	function fcvote_hide_bubble() {
		clearTimeout(fcvote_bubble_timer);
		if (fcvote_bubble_el) fcvote_bubble_el.style.display = 'none';
	}

	// Shows a fixed element (tooltip, bubble) centered above the given point, kept inside the viewport (the arrow still points to the point)
	function fcvote_place_above(el, centerX, topY) {
		el.style.left = '0px';
		el.style.top = '0px';
		el.style.visibility = 'hidden';
		el.style.display = 'block';

		var pad = 8;
		var half = el.offsetWidth / 2;
		var win_width = document.documentElement.clientWidth || window.innerWidth;
		var left = Math.max(pad + half, Math.min(centerX, win_width - pad - half));

		el.style.left = Math.round(left) + 'px';
		el.style.top = Math.round(topY) + 'px';
		el.style.setProperty('--fcvote-tip-arrow', Math.round(centerX - left) + 'px');
		el.style.visibility = '';
	}


	/* ================================================================
	 *  TOAST after the page reload: a message in the center of the screen, displayed for 3 seconds
	 *  (e.g. 'Thank you, your review was saved...'), it is stored in the session storage before the reload
	 * ================================================================ */

	var FCVOTE_TOAST_KEY = 'fcvote_toast';

	// Plain text of a message of the server (HTML with the close button)
	function fcvote_message_text(html) {
		var div = document.createElement('div');
		div.innerHTML = html || '';
		var btns = div.querySelectorAll('button');
		for (var i = 0; i < btns.length; i++) btns[i].parentNode.removeChild(btns[i]);

		return (div.textContent || '').replace(/\s+/g, ' ').trim();
	}

	// Remember a message (server HTML or text) to be displayed after the page is reloaded
	function fcvote_toast_after_reload(html) {
		var text = fcvote_message_text(html);
		if (!text) return;

		try { window.sessionStorage.setItem(FCVOTE_TOAST_KEY, text); } catch (err) { /* storage not available */ }
	}

	// Display a message now (server HTML or text)
	function fcvote_toast(html) {
		var text = fcvote_message_text(html);
		if (!text) return;

		window.fcvote_last_toast = text;
		fcvote_toast_display(text);
	}

	function fcvote_toast_show_stored() {
		var text = '';

		try {
			text = window.sessionStorage.getItem(FCVOTE_TOAST_KEY) || '';
			window.sessionStorage.removeItem(FCVOTE_TOAST_KEY);
		} catch (err) { return; }

		if (text) fcvote_toast_display(text);
	}

	function fcvote_toast_display(text) {
		var old = document.querySelectorAll('.fcvote-toast');
		for (var i = 0; i < old.length; i++) old[i].parentNode.removeChild(old[i]);

		var toast = document.createElement('div');
		toast.className = 'fcvote-toast';
		toast.setAttribute('role', 'status');
		toast.textContent = text;
		document.body.appendChild(toast);

		// Fade in, stay for 3 seconds, fade out
		requestAnimationFrame(function() { toast.classList.add('fcvote-toast-visible'); });
		setTimeout(function() { toast.classList.remove('fcvote-toast-visible'); }, 3000);
		setTimeout(function() { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 3400);
		toast.addEventListener('click', function() { if (toast.parentNode) toast.parentNode.removeChild(toast); });
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fcvote_toast_show_stored);
	else fcvote_toast_show_stored();


	/* ================================================================
	 *  STAR TOOLTIP (e.g. 'Good 4/5'): a single bubble, shown above the hovered star
	 *  It does not depend on the tooltip library of the site, and it is hidden when the mouse leaves the star, when the vote is made, or on scrolling
	 * ================================================================ */

	var fcvote_tip_el = null;

	function fcvote_hide_tooltip(el) {
		if (fcvote_tip_el) fcvote_tip_el.style.display = 'none';
		if (el && el.blur) el.blur();
	}

	function fcvote_show_tooltip(link) {
		var text = link.getAttribute('data-fcvote-tip');
		var list = link.closest('ul.fcvote_list');
		if (!text || !list) return;

		if (!fcvote_tip_el) {
			fcvote_tip_el = document.createElement('div');
			fcvote_tip_el.className = 'fcvote-tip';
			fcvote_tip_el.setAttribute('role', 'tooltip');
			document.body.appendChild(fcvote_tip_el);
		}

		var tip = fcvote_tip_el;
		tip.textContent = text;

		// Centered above the hovered star: the links overlap (the link of star N covers N stars), so use the position of the star in the list
		var total = list.querySelectorAll('li.voting-links').length || 1;
		var star = Math.min(total, Math.max(1, parseInt(link.textContent, 10) || 1));
		var rect = list.getBoundingClientRect();

		fcvote_place_above(tip, rect.left + rect.width * (star - 0.5) / total, rect.top - 6);
	}

	// Show on hover, hide when the mouse is over anything else
	document.addEventListener('mouseover', function(e) {
		var link = e.target.closest ? e.target.closest('a[data-fcvote-tip]') : null;
		if (link) fcvote_show_tooltip(link);
		else if (fcvote_tip_el && fcvote_tip_el.style.display !== 'none') fcvote_hide_tooltip();
	});

	document.documentElement.addEventListener('mouseleave', function() { fcvote_hide_tooltip(); });
	window.addEventListener('scroll', function() { fcvote_hide_tooltip(); fcvote_hide_bubble(); }, { passive: true });
	window.addEventListener('blur', function() { fcvote_hide_tooltip(); });
	document.addEventListener('touchstart', function(e) {
		if (!e.target.closest || !e.target.closest('a[data-fcvote-tip]')) fcvote_hide_tooltip();
	}, { passive: true });


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

		// The star that was clicked keeps the focus (and its tooltip), release it when the mouse leaves the stars
		if (document.activeElement && ul.contains(document.activeElement)) fcvote_hide_tooltip(document.activeElement);
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
		fcvote_hide_tooltip(link);

		var fcvote = link.closest('.fcvote');
		var voting_group = fcvote ? fcvote.closest('.voting-group') : null;
		if (voting_group) voting_group.style.opacity = '0.5';

		var vote_list = link.closest('.fcvote_list');
		var data_arr = link.getAttribute('data-rel').split('_');

		// ID for item being voted
		var itemID = data_arr[0];

		// Voting characteristic (defaults to 'main' if not set)
		var xid = (typeof data_arr[1] !== 'undefined' && data_arr[1]) ? data_arr[1] : 'main';

		var xid_msg = fcvote ? fcvote.querySelector('.fcvote_message') : null;
		var main_msg = voting_group ? (voting_group.querySelector('.voting-row_main .fcvote_message') || xid_msg) : xid_msg;

		var xid_cnt = fcvote ? fcvote.querySelector('.fcvote-count') : null;
		var main_cnt = voting_group ? (voting_group.querySelector('.voting-row_main .fcvote-count') || null) : null;

		var xid_rating = vote_list ? vote_list.querySelector('.current-rating') : null;
		var main_rating = null;
		if (voting_group) {
			var main_row = voting_group.querySelector('.voting-row_main');
			if (main_row) {
				var main_vote_list = main_row.querySelector('.fcvote_list');
				if (main_vote_list) main_rating = main_vote_list.querySelector('.current-rating');
			}
		}
		// Voting the 'main' characteristic without a surrounding group: the list itself is the main one
		if (!main_rating && xid === 'main') main_rating = xid_rating;

		var _htmlrating = xid_cnt ? xid_cnt.innerHTML : '';
		var _htmlrating_main = main_cnt ? main_cnt.innerHTML : '';

		var rating = link.textContent;

		var fc_csrf_token = (typeof Joomla !== 'undefined' && Joomla.getOptions) ? (Joomla.getOptions('csrf.token') || '') : '';
		var voteurl = fcvote_base_url()
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

			fcvote_hide_tooltip(link);
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
		var url = fcvote_base_url()
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
		// After a review was saved / deleted, reload the page when the form is closed (rating, counter and list are updated)
		var overlay = fcvote_showAsDialog(box, 700, 660, fcvote_reload_if_changed, { title: window.FC_VOTE_REVIEW_TITLE || '' });

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

	// Characters counter of the review text (shows used/maximum)
	document.addEventListener('input', function(e) {
		var ta = e.target;
		if (!ta || !ta.matches || !ta.matches('textarea[data-fcvote-max]')) return;
		var counter = ta.parentNode.querySelector('.fcvote-counter');
		if (counter) counter.textContent = ta.value.length + '/' + ta.getAttribute('data-fcvote-max');
	});

	function fcvote_submit_review_form(tagid, form) {
		var box = document.getElementById(tagid);
		var box_loading = document.getElementById(tagid + '_loading');
		var in_modal = !!document.querySelector('.fcvote_modal_overlay.fcvote_modal_visible');

		// Rating (stars) is required
		var rating_input = form.elements['rating'];
		var rating_error = form.querySelector('.fcvote-review-rating-error');
		if (rating_input) {
			if (!rating_input.value) {
				if (rating_error) rating_error.style.display = '';
				return;
			}
			if (rating_error) rating_error.style.display = 'none';
		}

		if (typeof form.checkValidity === 'function') {
			if (!form.checkValidity()) {
				// Inside the modal show the validation messages in the form, otherwise in the (page) loading box
				fcvote_submit_review_form_show_validation(form, in_modal ? fcvote_review_validation_box(form) : (box_loading || box));
				return;
			}
		}

		var url = fcvote_root_url()
			+ 'index.php?option=com_flexicontent&format=raw&task=storereviewform'
			+ '&lang=' + (typeof window.fc_sef_lang !== 'undefined' ? window.fc_sef_lang : '');

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
							var eNode = eBox.firstElementChild || eBox;
							if (firstForm) firstForm.insertAdjacentElement('beforebegin', eNode);
							else box.insertAdjacentElement('afterbegin', eNode);
						}
					} else if (box_loading) {
						box_loading.innerHTML = data.html;
						box_loading.style.display = 'block';
					}
				} else if (box) {
					window.fcvote_reviews_changed = true;

					// Form in a modal window: no intermediate message window, the form is closed and the page is reloaded (the own review is displayed in the list, with its 'awaiting approval' status)
					var form_overlay = in_modal ? box.closest('.fcvote_modal_overlay') : null;

					if (form_overlay) {
						// The form was opened from the list of reviews (modal window): the list is updated in place, no reload and no message
						// (the review of the user is displayed first, with its status), otherwise the message of the server is displayed after the reload, as a toast
						var under = fcvote_open_overlays().filter(function(o) { return o !== form_overlay; });
						var list_box = under.length ? under[under.length - 1].querySelector('.fcvote-reviews') : null;

						if (!list_box) fcvote_toast(data.html);

						if (typeof form_overlay._fcvote_closeFn === 'function') {
							form_overlay._fcvote_closeFn();
						} else {
							fcvote_remove_modal(form_overlay);
							fcvote_reload_if_changed();
						}

						if (list_box) {
							// (the filter by rating is removed: the review of the user must be visible)
							fcvote_reviews_reload(list_box, 0, { flashOwn: true, scroll: true });
						}
						return;
					}

					box.innerHTML = data.html;
					box.style.display = '';
					fcvote_review_update_button(tagid, 'edit');
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
	 *  REVIEW FORM — Rating (stars) picker
	 * ================================================================ */

	function fcvote_review_set_rating(btn) {
		var wrap = btn.parentNode;
		var value = parseInt(btn.getAttribute('data-value'), 10) || 0;
		var input = wrap.querySelector('input[name="rating"]');
		if (input) input.value = value;

		var stars = wrap.querySelectorAll('.fcvote-review-star');
		for (var i = 0; i < stars.length; i++) {
			var v = parseInt(stars[i].getAttribute('data-value'), 10) || 0;
			stars[i].style.color = v <= value ? '#f5a623' : '#ccc';
		}

		var form = btn.form || (input && input.form);
		var err = form ? form.querySelector('.fcvote-review-rating-error') : null;
		if (err) err.style.display = 'none';
	}

	window.fcvote_review_set_rating = fcvote_review_set_rating;


	/* ================================================================
	 *  REVIEW FORM — Update label of the 'review' button (new / edit)
	 * ================================================================ */

	function fcvote_review_update_button(tagid, mode) {
		var btns = document.querySelectorAll('.fcvote_toggle_review_form');
		for (var i = 0; i < btns.length; i++) {
			if (btns[i].getAttribute('data-tagid') !== tagid) continue;
			var label = btns[i].getAttribute(mode === 'edit' ? 'data-label-edit' : 'data-label-new');
			if (label) btns[i].value = label;
		}
	}


	/* ================================================================
	 *  REVIEW FORM — Delete the review of the current user
	 * ================================================================ */

	function fcvote_delete_review(tagid, form) {
		var btn = form.querySelector('.fcvote_review_form_delete_btn');
		if (!window.confirm(btn ? btn.getAttribute('data-confirm') : '')) return;

		var box = document.getElementById(tagid);
		var url = fcvote_root_url()
			+ 'index.php?option=com_flexicontent&format=raw&task=deletereviewform'
			+ '&lang=' + (typeof window.fc_sef_lang !== 'undefined' ? window.fc_sef_lang : '');

		fetch(url, {
			method: 'POST',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			body: new FormData(form)
		})
		.then(function(r) { return r.json(); })
		.then(function(data) {
			if (!data || !data.html || !box) return;
			if (data.error) {
				var eBox = document.createElement('div');
				eBox.innerHTML = data.html;
				form.insertAdjacentElement('beforebegin', eBox.firstElementChild || eBox);
			} else {
				window.fcvote_reviews_changed = true;
				box.innerHTML = data.html;
				fcvote_review_update_button(tagid, 'new');
			}
		})
		.catch(function(err) {
			alert('Error: ' + (err.message || err));
		});
	}

	window.fcvote_delete_review = fcvote_delete_review;


	/* ================================================================
	 *  REVIEW LIST — Delete the review of the current user (button of the list)
	 * ================================================================ */

	function fcvote_review_delete_own(btn, contentId) {
		if (!window.confirm(btn.getAttribute('data-confirm') || '')) return false;

		var body = new URLSearchParams();
		body.set('content_id', contentId);
		body.set('review_type', 'item');
		body.set(btn.getAttribute('data-token'), '1');

		var url = fcvote_root_url()
			+ 'index.php?option=com_flexicontent&format=raw&task=deletereviewform'
			+ '&lang=' + (typeof window.fc_sef_lang !== 'undefined' ? window.fc_sef_lang : '');

		btn.disabled = true;

		fetch(url, {
			method: 'POST',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			body: body
		})
		.then(function(r) { return r.json(); })
		.then(function(data) {
			if (data && !data.error) {
				fcvote_toast(data.html);

				// In a modal window the list is loaded again (the page is refreshed when the last modal is closed), in the page the blocks of the page are refreshed
				var del_box = btn.closest('.fcvote-reviews');

				if (btn.closest('.fcvote_modal_overlay') && del_box) {
					window.fcvote_reviews_changed = true;
					fcvote_reviews_reload(del_box, 0);
				} else {
					fcvote_refresh_page();
				}
				return;
			}
			btn.disabled = false;
			var tmp = document.createElement('div');
			tmp.innerHTML = (data && data.html) || '';
			alert(tmp.textContent.replace(/^\s*[×x]\s*/, '').trim() || 'Error');
		})
		.catch(function(err) {
			btn.disabled = false;
			alert('Error: ' + (err.message || err));
		});

		return false;
	}

	window.fcvote_review_delete_own = fcvote_review_delete_own;


	/* ================================================================
	 *  REVIEW LIST — Quick edit of a review by a moderator (fix typos, approve), simple modal like the review form
	 * ================================================================ */

	function fcvote_review_edit_open(reviewId, title, btn) {
		var box = document.createElement('div');
		box.className = 'fcvote_review_form_box';
		box.innerHTML = '<div class="fc-mssg fc-info fc-nobgimage"><span class="fc-spinner"></span>&nbsp;Loading...</div>';

		// The modal is displayed on top of the reviews list (if it is open), the row of the review in the list is updated after saving
		var row = btn ? btn.closest('.fcvote-review-item') : null;
		box._fcvote_review_row = row;
		fcvote_showAsDialog(box, 700, 600, fcvote_reload_if_changed, { title: title || '' });

		var url = fcvote_base_url()
			+ 'index.php?option=com_flexicontent&task=getreviewedit&format=raw&review_id=' + reviewId
			+ '&lang=' + (typeof window.fc_sef_lang !== 'undefined' ? window.fc_sef_lang : '');

		fetch(url, {
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			cache: 'no-store'
		})
		.then(function(r) { return r.json(); })
		.then(function(data) {
			if (!box.parentNode) return; // modal was closed meanwhile
			box.innerHTML = (data && data.html) ? data.html
				: '<div class="fc-mssg fc-warning fc-nobgimage">Error loading the review</div>';
		})
		.catch(function(err) {
			if (!box.parentNode) return;
			box.innerHTML = '<div class="fc-mssg fc-warning fc-nobgimage">Error: ' + (err.message || err) + '</div>';
		});

		return false;
	}

	function fcvote_review_edit_save(form) {
		var msgBox = fcvote_review_validation_box(form);

		if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
			fcvote_submit_review_form_show_validation(form, msgBox);
			return;
		}

		var btn = form.querySelector('.fcvote_review_form_submit_btn');
		if (btn) btn.disabled = true;

		var url = fcvote_root_url()
			+ 'index.php?option=com_flexicontent&format=raw&task=savereviewedit'
			+ '&lang=' + (typeof window.fc_sef_lang !== 'undefined' ? window.fc_sef_lang : '');

		fetch(url, {
			method: 'POST',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			body: new FormData(form)
		})
		.then(function(r) { return r.json(); })
		.then(function(data) {
			if (btn) btn.disabled = false;
			msgBox.innerHTML = (data && data.html) || '';

			if (data && !data.error) {
				window.fcvote_reviews_changed = true;

				var overlay = form.closest('.fcvote_modal_overlay');
				var box = form.closest('.fcvote_review_form_box');
				var row = box ? box._fcvote_review_row : null;

				// Update the review in the list underneath (without reloading the page), then close the edit modal
				if (data.item_html && row && row.parentNode) {
					var tmp = document.createElement('div');
					tmp.innerHTML = data.item_html;
					var fresh = tmp.firstElementChild;
					if (fresh) {
						fresh.classList.add('fcvote-review-flash');
						row.parentNode.replaceChild(fresh, row);
					}
				}

				if (overlay && typeof overlay._fcvote_closeFn === 'function') overlay._fcvote_closeFn();
			}
		})
		.catch(function(err) {
			if (btn) btn.disabled = false;
			alert('Error: ' + (err.message || err));
		});
	}

	window.fcvote_review_edit_open = fcvote_review_edit_open;
	window.fcvote_review_edit_save = fcvote_review_edit_save;


	/* ================================================================
	 *  REVIEW LIST — 'Was this helpful?' Yes / No (useful votes of a review)
	 *  Same answer again removes the vote, the other answer changes it
	 * ================================================================ */

	function fcvote_review_useful(btn, reviewId, answer) {
		var wrap = btn.closest('.fcvote-review-useful');
		if (!wrap) return false;

		var btns = wrap.querySelectorAll('.fcvote-useful-btn');
		var body = new URLSearchParams();
		body.set('review_id', reviewId);
		body.set('vote', answer);
		body.set(btn.getAttribute('data-token'), '1');

		var url = fcvote_root_url()
			+ 'index.php?option=com_flexicontent&format=raw&task=votereviewuseful'
			+ '&lang=' + (typeof window.fc_sef_lang !== 'undefined' ? window.fc_sef_lang : '');

		for (var i = 0; i < btns.length; i++) btns[i].disabled = true;

		fetch(url, {
			method: 'POST',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			body: body
		})
		.then(function(r) { return r.json(); })
		.then(function(data) {
			for (var i = 0; i < btns.length; i++) btns[i].disabled = false;

			if (!data || data.error) {
				alert((data && data.msg) || 'Error');
				return;
			}

			// Selected answer
			for (var j = 0; j < btns.length; j++) {
				btns[j].classList.toggle('is-active', btns[j].getAttribute('data-vote') === data.my_vote);
			}

			// Number of people that found the review useful
			var count = wrap.querySelector('.fcvote-review-useful-count');
			if (count) {
				count.textContent = data.count_text || '';
				count.style.display = data.count_text ? '' : 'none';
			}
		})
		.catch(function(err) {
			for (var i = 0; i < btns.length; i++) btns[i].disabled = false;
			alert('Error: ' + (err.message || err));
		});

		return false;
	}

	window.fcvote_review_useful = fcvote_review_useful;


	/* ================================================================
	 *  REVIEW LIST — 'N Reviews' button of the Inline placement
	 *
	 *  Scrolls to the reviews list of the item (displayed by the item template),
	 *  if the list is not in the page then it is opened in a modal window
	 * ================================================================ */

	function fcvote_reviews_goto(itemId, title) {
		var target = document.getElementById('fcvote-reviews-' + itemId);

		if (target) {
			target.scrollIntoView({ behavior: 'smooth', block: 'start' });
			return false;
		}

		// The list is not in the page (the item template does not display it), load it in a modal window
		return fcvote_reviews_modal(itemId, title);
	}

	window.fcvote_reviews_goto = fcvote_reviews_goto;


	/* ================================================================
	 *  REVIEW LIST — Load the list on demand (getreviewslist) and display it in a modal window
	 * ================================================================ */

	// star: only the reviews with this rating (number of stars), inline: the list is displayed in the page (not in the modal window)
	function fcvote_reviews_list_url(itemId, offset, star, inline) {
		return fcvote_root_url()
			+ 'index.php?option=com_flexicontent&task=getreviewslist&format=raw&content_id=' + itemId
			+ (offset ? '&offset=' + offset : '')
			+ (star ? '&star=' + star : '')
			+ (inline ? '&inline=1' : '')
			+ '&lang=' + (typeof window.fc_sef_lang !== 'undefined' ? window.fc_sef_lang : '');
	}

	function fcvote_reviews_modal(itemId, title, star) {
		var box = document.createElement('div');
		box.className = 'fcvote-reviews-modal-box';
		box.innerHTML = '<div class="fc-mssg fc-info fc-nobgimage"><span class="fc-spinner"></span>&nbsp;Loading...</div>';

		fcvote_showAsDialog(box, 800, 600, fcvote_reload_if_changed, { title: title || '' });

		fetch(fcvote_reviews_list_url(itemId, 0, star), {
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			cache: 'no-store'
		})
		.then(function(r) { return r.json(); })
		.then(function(data) {
			if (!box.parentNode) return; // modal was closed meanwhile
			box.innerHTML = (data && data.html) ? data.html
				: '<div class="fc-mssg fc-warning fc-nobgimage">Error loading reviews</div>';

			// Load the next reviews, when the end of the list is reached (scrolling)
			fcvote_reviews_lazy(box, itemId);
		})
		.catch(function(err) {
			if (!box.parentNode) return;
			box.innerHTML = '<div class="fc-mssg fc-warning fc-nobgimage">Error: ' + (err.message || err) + '</div>';
		});

		return false;
	}


	/* ================================================================
	 *  REVIEW LIST — Lazy loading: the next reviews are loaded when the 'loading' marker (end of the list) becomes visible
	 * ================================================================ */

	function fcvote_reviews_lazy(root, itemId) {
		var marker = root.querySelector('.fcvote-reviews-more');
		if (!marker) return;

		// Browsers without IntersectionObserver: a button loads the next reviews
		var supported = 'IntersectionObserver' in window;
		var loading = false;
		var observer = null;
		var loaderHtml = marker.innerHTML;  // the loader (spinner + text), displayed while the next reviews are loading

		function loadNext() {
			if (loading) return;
			loading = true;
			if (observer) observer.disconnect();
			marker.innerHTML = loaderHtml;

			var listBox = marker.closest('.fcvote-reviews');
			var star = listBox ? parseInt(listBox.getAttribute('data-star'), 10) || 0 : 0;

			fetch(fcvote_reviews_list_url(itemId, marker.getAttribute('data-next'), star), {
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
				cache: 'no-store'
			})
			.then(function(r) { return r.json(); })
			.then(function(data) {
				if (!marker.parentNode) return; // modal was closed meanwhile
				if (!data || data.error) throw new Error('Error loading reviews');

				var container = marker.parentNode;
				var tmp = document.createElement('div');
				tmp.innerHTML = data.html || '';
				container.removeChild(marker);
				while (tmp.firstChild) container.appendChild(tmp.firstChild);

				// The new end of the list (if there are more reviews)
				fcvote_reviews_lazy(container, itemId);
			})
			.catch(function(err) {
				loading = false;
				if (marker.parentNode) {
					marker.innerHTML = '<button type="button" class="btn btn-sm btn-outline-secondary">Retry</button>';
					marker.firstChild.addEventListener('click', loadNext);
				}
			});
		}

		if (supported) {
			observer = new IntersectionObserver(function(entries) {
				if (entries[0].isIntersecting) loadNext();
			}, { rootMargin: '200px' });
			observer.observe(marker);
		} else {
			marker.innerHTML = '<button type="button" class="btn btn-sm btn-outline-secondary">...</button>';
			marker.firstChild.addEventListener('click', loadNext);
		}
	}

	window.fcvote_reviews_lazy = fcvote_reviews_lazy;


	/* ================================================================
	 *  REVIEW LIST — Filter by rating: clicking a bar of the summary shows only the reviews with that rating
	 *  (clicking the active bar, or 'Show all', shows all the reviews), the box (summary + list) is replaced in place
	 * ================================================================ */

	function fcvote_reviews_filter(el, star) {
		var box = el.closest('.fcvote-reviews');
		if (!box) return false;

		// Clicking the active bar removes the filter
		if (star === (parseInt(box.getAttribute('data-star'), 10) || 0)) star = 0;

		fcvote_reviews_reload(box, star);
		return false;
	}

	// Loads the first reviews again (summary + list) and replaces the box in place, with the given filter by rating
	// options.flashOwn: highlight the review of the user (after it was saved), options.scroll: scroll to the top of the box
	function fcvote_reviews_reload(box, star, options) {
		if (!box || box._fcvote_loading) return;
		options = options || {};

		var itemId = box.getAttribute('data-item');
		var inline = box.getAttribute('data-inline') === '1';

		box._fcvote_loading = true;
		box.style.opacity = '0.5';
		box.style.pointerEvents = 'none';

		fetch(fcvote_reviews_list_url(itemId, 0, star, inline), {
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			cache: 'no-store'
		})
		.then(function(r) { return r.json(); })
		.then(function(data) {
			if (!data || data.error || !data.html) throw new Error('Error loading reviews');
			if (!box.parentNode) return;

			var tmp = document.createElement('div');
			tmp.innerHTML = data.html;
			var newBox = tmp.querySelector('.fcvote-reviews');
			if (!newBox) throw new Error('Error loading reviews');

			box.parentNode.replaceChild(newBox, box);

			// Load the next reviews by scrolling (modal window)
			fcvote_reviews_lazy(newBox, itemId);

			if (options.flashOwn) {
				var own = newBox.querySelector('.fcvote-review-own');
				if (own) own.classList.add('fcvote-review-flash');
			}

			if (options.scroll && newBox.scrollIntoView) newBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
		})
		.catch(function() {
			box._fcvote_loading = false;
			box.style.opacity = '';
			box.style.pointerEvents = '';
		});
	}

	window.fcvote_reviews_filter = fcvote_reviews_filter;

	window.fcvote_reviews_modal = fcvote_reviews_modal;


	/* ================================================================
	 *  REVIEW LIST — Moderation: approve / trash a pending review
	 * ================================================================ */

	function fcvote_review_moderate(btn, action, reviewId) {
		if (action === 'trash' && !window.confirm(btn.getAttribute('data-confirm') || '')) return false;

		var row = btn.closest('.fcvote-review-item') || document.getElementById('review_' + reviewId);
		var body = new URLSearchParams();
		body.set('review_id', reviewId);
		body.set(btn.getAttribute('data-token'), '1');

		var url = fcvote_root_url()
			+ 'index.php?option=com_flexicontent&format=raw&task=' + (action === 'approve' ? 'approvereview' : 'trashreview')
			+ '&lang=' + (typeof window.fc_sef_lang !== 'undefined' ? window.fc_sef_lang : '');

		btn.disabled = true;

		fetch(url, {
			method: 'POST',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			body: body
		})
		.then(function(r) { return r.json(); })
		.then(function(data) {
			if (!data || data.error) {
				btn.disabled = false;
				alert((data && data.msg) || 'Error');
				return;
			}
			// The page is reloaded when the last modal is closed, the other copy of the review (page / modal) is updated then
			window.fcvote_reviews_changed = true;

			if (!row) return;

			if (action === 'trash') {
				row.parentNode.removeChild(row);
			} else {
				// Approved: display as a normal review (the Edit button of the moderator stays)
				row.classList.remove('fcvote-review-pending-item');
				var mod = row.querySelector('.fcvote-review-moderate');
				if (mod) {
					var modBtns = mod.querySelectorAll('.fcvote-review-btn-approve, .fcvote-review-btn-trash');
					for (var b = 0; b < modBtns.length; b++) modBtns[b].parentNode.removeChild(modBtns[b]);
					if (!mod.querySelector('button')) mod.parentNode.removeChild(mod);
				}
				var badge = row.querySelector('.fcvote-review-pending');
				if (badge) badge.parentNode.removeChild(badge);
			}
		})
		.catch(function(err) {
			btn.disabled = false;
			alert('Error: ' + (err.message || err));
		});

		return false;
	}

	window.fcvote_review_moderate = fcvote_review_moderate;


	/* ================================================================
	 *  REVIEW FORM — Validation display
	 * ================================================================ */

	function fcvote_review_validation_box(form) {
		var box = form.querySelector('.fcvote-review-validation');
		if (!box) {
			box = document.createElement('div');
			box.className = 'fcvote-review-validation';
			form.insertAdjacentElement('afterbegin', box);
		}
		return box;
	}

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
		overlay.setAttribute('data-fcvote-open', '1');

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
		// Close on a click outside of the dialog, but not when a press started inside the dialog (e.g. selecting text of a field and releasing the mouse over the backdrop)
		var down_on_overlay = false;
		overlay.addEventListener('mousedown', function(e) { down_on_overlay = (e.target === overlay); });
		overlay.addEventListener('touchstart', function(e) { down_on_overlay = (e.target === overlay); }, { passive: true });
		overlay.addEventListener('click', function(e) {
			var close = (e.target === overlay) && down_on_overlay;
			down_on_overlay = false;
			if (close) fcvote_remove_modal();
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

		// Modals are stacked: a modal opened from another modal (e.g. edit of a review, opened from the reviews list) is displayed on top of it
		var level = fcvote_open_overlays().length;

		var title = params.title || '';

		// Save original parent and next sibling for restoration
		var parent = box.parentNode;
		var nextSibling = box.nextSibling;
		var origDisplay = box.style.display;

		// Overlay
		var overlay = document.createElement('div');
		if (!level) overlay.id = 'fcvote_modal_overlay';
		overlay.className = 'fcvote_modal_overlay';
		overlay.setAttribute('data-fcvote-open', '1');
		if (level) overlay.style.zIndex = String(9999 + 10 * level);

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
		function restoreBox() {
			// Restore element to original position
			if (nextSibling && nextSibling.parentNode === parent) {
				parent.insertBefore(box, nextSibling);
			} else if (parent) {
				parent.appendChild(box);
			}
			box.style.display = origDisplay;
		}
		overlay._fcvote_restore = restoreBox;

		function closeModal() {
			restoreBox();

			fcvote_remove_modal(overlay);

			// Execute close function
			if (typeof closeFunc === 'function') closeFunc(box);
			else if (closeFunc === 1) window.location.reload(false);
		}

		closeBtn.addEventListener('click', closeModal);
		// Close on a click outside of the dialog, but not when a press started inside the dialog (e.g. selecting text of a field and releasing the mouse over the backdrop)
		var down_on_overlay = false;
		overlay.addEventListener('mousedown', function(e) { down_on_overlay = (e.target === overlay); });
		overlay.addEventListener('touchstart', function(e) { down_on_overlay = (e.target === overlay); }, { passive: true });
		overlay.addEventListener('click', function(e) {
			var close = (e.target === overlay) && down_on_overlay;
			down_on_overlay = false;
			if (close) closeModal();
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

	// The open (not closed yet) modals, the last one is the top-most
	function fcvote_open_overlays() {
		return Array.prototype.slice.call(document.querySelectorAll('.fcvote_modal_overlay[data-fcvote-open]'));
	}

	// Remove the given modal, or all the modals if none is given
	function fcvote_remove_modal(target) {
		var list = target ? [target] : fcvote_open_overlays();

		list.forEach(function(overlay) {
			// Give the box back to the page (e.g. the reviews list, opened again later)
			if (typeof overlay._fcvote_restore === 'function') {
				var restore = overlay._fcvote_restore;
				overlay._fcvote_restore = null;
				restore();
			}

			overlay.removeAttribute('data-fcvote-open');
			overlay.classList.remove('fcvote_modal_visible');
			setTimeout(function() {
				if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
			}, 250);
		});

		// Restore body scroll and the keyboard handler, when no modal is left
		if (!fcvote_open_overlays().length) {
			document.removeEventListener('keydown', fcvote_modal_keydown);
			document.body.classList.remove('fc-no-scroll');
		}
	}

	// Escape closes the top-most modal
	function fcvote_modal_keydown(e) {
		if (e.key === 'Escape' || e.keyCode === 27) {
			var list = fcvote_open_overlays();
			var top = list[list.length - 1];
			if (!top) return;
			if (typeof top._fcvote_closeFn === 'function') top._fcvote_closeFn();
			else fcvote_remove_modal(top);
		}
	}

	// After a review was changed (saved, approved, ...), the page is refreshed when the last modal is closed (rating, counter and list of the page are updated)
	function fcvote_reload_if_changed() {
		if (window.fcvote_reviews_changed && !fcvote_open_overlays().length) {
			window.fcvote_reviews_changed = false;
			fcvote_refresh_page();
		}
	}

	// Replace the stars + reviews button and the reviews card of the item, in the page, by their current HTML (loaded from the server),
	// the page is not reloaded, it is reloaded only if the blocks cannot be found / replaced (e.g. a template that does not output the field as usual)
	function fcvote_refresh_page() {
		var wraps = document.querySelectorAll('.fcvote-field[data-item]');
		var ids = [];

		for (var i = 0; i < wraps.length; i++) {
			var id = wraps[i].getAttribute('data-item');
			if (ids.indexOf(id) < 0) ids.push(id);
		}

		function fallback() {
			fcvote_toast_after_reload(window.fcvote_last_toast || '');
			window.location.reload();
		}

		if (!ids.length) return fallback();

		var pending = ids.length;
		var failed = false;

		function done() {
			if (--pending === 0 && failed) fallback();
		}

		ids.forEach(function(itemId) {
			var blocks = document.querySelectorAll('.fcvote-field[data-item="' + itemId + '"], #fcvote-reviews-' + itemId);
			for (var b = 0; b < blocks.length; b++) blocks[b].style.opacity = '0.6';

			fetch(fcvote_root_url() + 'index.php?option=com_flexicontent&task=getreviewsrefresh&format=raw&content_id=' + itemId
				+ '&lang=' + (typeof window.fc_sef_lang !== 'undefined' ? window.fc_sef_lang : ''), {
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
				cache: 'no-store'
			})
			.then(function(r) { return r.json(); })
			.then(function(data) {
				if (!data || data.error || typeof data.field !== 'string') throw new Error('refresh');

				var tmp = document.createElement('div');
				tmp.innerHTML = data.field;
				var fresh = tmp.querySelector('.fcvote-field');
				if (!fresh) throw new Error('refresh');

				var fields = document.querySelectorAll('.fcvote-field[data-item="' + itemId + '"]');
				for (var f = 0; f < fields.length; f++) {
					fields[f].innerHTML = fresh.innerHTML;
					fields[f].style.opacity = '';
				}

				// Reviews card of the Inline placement: replaced (or removed if there is nothing to display anymore)
				var card = document.getElementById('fcvote-reviews-' + itemId);
				if (card) {
					var tmp2 = document.createElement('div');
					tmp2.innerHTML = data.card || '';
					var fresh_card = tmp2.firstElementChild;

					if (fresh_card) card.parentNode.replaceChild(fresh_card, card);
					else card.parentNode.removeChild(card);
				}

				done();
			})
			.catch(function() {
				failed = true;
				done();
			});
		});
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

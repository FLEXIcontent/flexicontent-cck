/**
 * State selector of records (flexicontent_html::statebutton): the frontend buttons and the backend managers.
 *
 * Plain JavaScript: it works the same with or without jQuery on the page.
 */

var fc_state_imgs = {
	'1': 'accept.png',
	'-5': 'publish_g.png',
	'0': 'publish_x.png',
	'-3': 'publish_r.png',
	'-4': 'publish_y.png',
	'2': 'archive.png',
	'-2': 'trash.png',
	'u': 'unknown.png'
};
var fc_state_icons = {
	'1': 'publish',
	'-5': 'checkmark-2',
	'0': 'unpublish',
	'-3': 'question',
	'-4': 'pencil-2',
	'2': 'archive',
	'-2': 'trash',
	'u': 'question-2'
};

var fc_statehandler = function(options)
{
	// Joomla Base URL
	var base_url = typeof jbase_url_fc !== 'undefined' && !!jbase_url_fc ? jbase_url_fc : '';

	this.options = {
		id: '',
		script_url: base_url + 'index.php?option=com_flexicontent&format=raw',
		task: '',
		token: '',
		state: '',
		font_icons: true,
		refresh_on_success: false
	};

	if( typeof options != 'undefined') for (var key in options)
	{
		this.options[key] = options[key];  //window.console.log(key, options[key]);
	};


	var text = function(key)
	{
		var J = window.Joomla, T = J && (J.Text || J.JText);
		return T && T._ ? T._(key) : key;
	};

	// Value of a data-* attribute that holds JSON (as jQuery's .data() did)
	var jsonData = function(el, name)
	{
		var value = el.getAttribute('data-' + name);
		try {
			return value ? JSON.parse(value) : null;
		} catch(e) {
			return null;
		}
	};

	// As jQuery's .slideToggle(): animate height, padding and margin from / to zero
	var slideToggle = function(el, show, ms)
	{
		if (el.fcSlide)
		{
			el.fcSlide.onfinish = null;
			el.fcSlide.cancel();
		}

		if (show)
		{
			el.style.display = 'block';
		}

		if (!el.animate)
		{
			el.style.display = show ? 'block' : 'none';
			return;
		}

		var cs   = window.getComputedStyle(el);
		var full = {height: el.offsetHeight + 'px', paddingTop: cs.paddingTop, paddingBottom: cs.paddingBottom, marginTop: cs.marginTop, marginBottom: cs.marginBottom};
		var none = {height: '0px', paddingTop: '0px', paddingBottom: '0px', marginTop: '0px', marginBottom: '0px'};

		el.style.overflow = 'hidden';
		el.fcSlide = el.animate(show ? [none, full] : [full, none], {duration: ms, easing: 'ease-in-out'});
		el.fcSlide.onfinish = function()
		{
			el.fcSlide = null;
			el.style.overflow = '';
			if (!show)
			{
				el.style.display = 'none';
			}
		};
	};


	// *** AJAX request to set new item state
	this.setState = function(state, id)
	{
		var row = document.getElementById('row' + id);
		var toggler = row ? row.closest('.statetoggler') : null;
		var options = this.options;

		if (row)
		{
			row.innerHTML = '';
			row.classList.add('ajax-loader');
		}

		var url = this.options.script_url + '&task=' + this.options.task + '&id=' + id + '&state=' + state + (this.options.token ? '&' + this.options.token + '=1' : '')
			+ '&lang=' + encodeURIComponent(typeof fc_sef_lang != 'undefined' ? fc_sef_lang : '');

		fetch(url, {
			credentials: 'same-origin',
			headers: {'Accept': 'application/json, text/javascript, */*; q=0.01', 'X-Requested-With': 'XMLHttpRequest'}
		})
			.then(function(r)
			{
				return r.text().then(function(body)
				{
					if (!r.ok)
					{
						throw {status: r.status, text: r.statusText};
					}
					try {
						return JSON.parse(body);
					} catch(e) {
						throw {status: r.status, text: String(e)};
					}
				});
			})
			.then(function(data)
			{
				if (row)
				{
					row.classList.remove('ajax-loader');
					if (typeof data.html != 'undefined') row.innerHTML = data.html;
				}
				if (toggler && typeof data.title != 'undefined')
				{
					toggler.setAttribute('data-original-title', data.title);
					if (row.matches('button.stateopener')) row.setAttribute('aria-description', data.title);
				}

				if (typeof data.error != 'undefined')
				{
					var msgs = document.getElementById('system-message-container');
					if (msgs) msgs.innerHTML = data.error;
				}
				else
				{
					if (options.refresh_on_success)
					{
						window.document.body.innerHTML = '<span class="fc_loading_msg">Reloading ... please wait</span>';
						window.location.reload(false);  //window.location.replace(window.location.href);
					}
				}
			})
			.catch(function(err)
			{
				FlexiForm.alert('Error status: ' + ((err && err.status) || 0) + ' , Error text: ' + ((err && (err.text || err.message)) || ''));
			});
	};


	// *** Toggle drop down selector display, and populate available options if this is not already done
	this.toggleSelector = function(el)
	{
		if (!fc_statehandler_singleton) return;

		var toggler = el.closest('.statetoggler');
		var ops = toggler?.querySelector('.options');
		if (!ops) return;
		var button = toggler.querySelector('button.stateopener');
		var show = this.active_selector_box !== toggler;
		if (button) button.setAttribute('aria-expanded', String(show));

		var fc_state_titles = typeof _fc_state_titles != 'undefined' ? _fc_state_titles : {
			 '1': 'FLEXI_PUBLISH_THIS_ITEM',
			'-5': 'FLEXI_SET_STATE_AS_IN_PROGRESS',
			 '0': 'FLEXI_UNPUBLISH_THIS_ITEM',
			'-3': 'FLEXI_SET_STATE_AS_PENDING',
			 '-4': 'FLEXI_SET_STATE_AS_TO_WRITE',
				'2': 'FLEXI_ARCHIVE_THIS_ITEM',
			'-2': 'FLEXI_TRASH_THIS_ITEM',
			 'u': 'FLEXI_UNKNOWN'
		};

		if (show)
		{
			if (this.active_selector_box && this.active_selector_box !== toggler)
			{
				this.toggleSelector(this.active_selector_box);
			}

			toggler.classList.add('active');
			toggler.style.zIndex = 100; // should 3 or more

			if (ops.children.length == 0)
			{
				var iid    = ops.getAttribute('data-id');
				var states = jsonData(ops, 'st') || [];
				var titles = jsonData(ops, 'tt');

				states.forEach(item =>
				{
					var action = document.createElement('button');
					action.type = 'button';
					var icon;
					if (this.options.font_icons)
					{
						icon = document.createElement('span');
						icon.className = 'icon-' + fc_state_icons[item.i];
						icon.setAttribute('aria-hidden', 'true');
					}
					else
					{
						icon = document.createElement('img');
						icon.src = this.options.img_path + fc_state_imgs[item.i];
						icon.alt = '';
					}
					var label = titles && titles[item.i]
						? text('FLEXI_SET_STATE_TO') + ' ' + titles[item.i]
						: text(fc_state_titles[item.i]);
					action.append(icon, label);
					action.addEventListener('click', event =>
					{
						event.stopPropagation();
						button?.focus();
						this.toggleSelector(toggler);
						this.setState(item.i, iid);
					});
					ops.append(action);
				});
			}
			this.active_selector_box = toggler;
		}
		else
		{
			this.active_selector_box = null;

			setTimeout(() => {
				if (this.active_selector_box !== toggler)
				{
					toggler.classList.remove('active');
					toggler.style.zIndex = '';
				}
			}, 200);
		}
		slideToggle(ops, show, 200);
	};


	// *** Detect click outside container and close any open selector
	this.closeSelector = function(event)
	{
		var box = this.active_selector_box;

		if (!box) return;  // no open container
		if (box === event.target || box.contains(event.target)) return;  // if target of the click is the container or a descendant of it

		this.toggleSelector(box);
	};
};


var fc_statehandler_singleton = false;

document.addEventListener('mouseup', function(event)
{
	if (fc_statehandler_singleton) fc_statehandler_singleton.closeSelector(event);
});

document.addEventListener('keydown', function(event)
{
	var box = fc_statehandler_singleton && fc_statehandler_singleton.active_selector_box;
	if (event.defaultPrevented || event.key !== 'Escape' || !box || !box.contains(event.target)) return;
	event.preventDefault();
	box.querySelector('button.stateopener')?.focus();
	fc_statehandler_singleton.toggleSelector(box);
});

document.addEventListener('focusout', function(event)
{
	var box = fc_statehandler_singleton && fc_statehandler_singleton.active_selector_box;
	if (!box || !box.contains(event.target) || !event.relatedTarget || box.contains(event.relatedTarget)) return;
	fc_statehandler_singleton.toggleSelector(box);
});

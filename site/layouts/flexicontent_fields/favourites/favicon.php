<?php
defined('JPATH_BASE') or die;

extract($displayData);

$tooltip_class = 'hasTooltip';

static $tooltip_title, $icon_not_fav, $icon_is_fav, $icon_del_fav, $icon_login_fav;  // Reusable Texts / HTML for creating FAVs Icon
static $allow_guests_favs, $users_counter;  // Favourites field configuration

static $js_and_css_added = false;
if (!$js_and_css_added)
{
	$document = \Joomla\CMS\Factory::getApplication()->getDocument();
	flexicontent_html::loadFramework('flexi-lib');
	$cparams  = \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent');
	$use_font = $cparams->get('use_font_icons', 1);

	// Get Favourites field configuration (if FIELD is empty then retrieve it)
	$favs_field = $field ?: reset(FlexicontentFields::getFieldsByIds(array(12)));
	$favs_field->parameters = new \Joomla\Registry\Registry($favs_field->attribs);

	$toggle_style = (int) $favs_field->parameters->get('toggle_style', $use_font ? 2 : 1);
	$status_info  = (int) $favs_field->parameters->get('status_info', 0);
	$toggle_info  = (int) $favs_field->parameters->get('toggle_info', 1);

	// Style 0 was the "BootStrap Toggle" jQuery plugin, it is now drawn as the heart of style 1
	$toggle_style = $toggle_style === 2 ? 2 : 1;

	$allow_guests_favs = (int) $favs_field->parameters->get('allow_guests_favs', 1);
	$users_counter     = (int) $favs_field->parameters->get('display_favoured_usercount', 0);

	$text 		= \Joomla\CMS\Factory::getApplication()->getIdentity()->id || $allow_guests_favs ? 'FLEXI_ADDREMOVE_FAVOURITE' : 'FLEXI_FAVOURE';
	$overlib 	= \Joomla\CMS\Factory::getApplication()->getIdentity()->id || $allow_guests_favs ? 'FLEXI_ADDREMOVE_FAVOURITE_TIP' : 'FLEXI_FAVOURE_LOGIN_TIP';
	$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 1, 1);

	switch ($toggle_style)
	{
		case 1:
			// SVG heart, the size of the old PNG hearts: an outline when not a favourite (class fcfavs-heart-border),
			// filled when a favourite (class fcfavs-heart-fill). Same shape as the CSS heart of style 2.
			$heart_svg = function ($class)
			{
				return '<svg class="fcfavs-img_icon fcfavs-svg_icon ' . $class . '" viewBox="1.5 1.5 21 21" width="28" height="28" aria-hidden="true" focusable="false">'
					. '<path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>'
					. '</svg>';
			};

			$icon_del_fav = \Joomla\CMS\HTML\HTMLHelper::image('components/com_flexicontent/assets/images/'.'cancel.png', \Joomla\CMS\Language\Text::_('FLEXI_REMOVE_FAVOURITE'), 'class="fcfavs-img_icon"');
			$icon_is_fav  = $heart_svg('fcfavs-heart-fill');
			$icon_not_fav = $heart_svg('fcfavs-heart-border');

			$_attribs = 'class="fcfavs-icon_login '.$tooltip_class.'" title="'.$tooltip_title.'" onclick="FlexiForm.alert(\''.\Joomla\CMS\Language\Text::_( 'FLEXI_FAVOURE_LOGIN_TIP', true ).'\')"';
			$icon_login_fav = '<span class="fcfavs-btn"><span ' . $_attribs . '>' . $heart_svg('fcfavs-svg_login') . '</span></span>';
			break;

		case 2:
			$icon_del_fav = '<span class="fcfavs-btn"><span class="fcfavs-btn-inner fcfavs-heart-fill fcfavs-heart-delete"></span></span>';
			$icon_is_fav  = '<span class="fcfavs-btn"><span class="fcfavs-btn-inner fcfavs-heart-fill"></span></span>';
			$icon_not_fav = '<span class="fcfavs-btn"><span class="fcfavs-btn-inner fcfavs-heart-border"></span></span>';

			$_attribs = 'class="fcfavs-btn-inner fcfavs-heart-fill fcfavs-heart-login '.$tooltip_class.'" title="'.$tooltip_title.'" onclick="FlexiForm.alert(\''.\Joomla\CMS\Language\Text::_( 'FLEXI_FAVOURE_LOGIN_TIP', true ).'\')" ';
			$icon_login_fav = '<span class="fcfavs-btn"><span ' . $_attribs . '></span></span>';
			break;
	}

	// Load JS / CSS
	if ($cparams->get('add_tooltips', 1))
	{
		\Joomla\CMS\HTML\HTMLHelper::_('bootstrap.tooltip');
	}

	// fcfav.js is plain JavaScript: it needs neither jQuery nor the other FLEXIcontent scripts,
	// so its URL globals are set here, if no other FLEXIcontent script has set them (same values as 'flexi_js_common')
	$site_languages    = FLEXIUtilities::getLanguages();
	$current_lang_code = flexicontent_html::getUserCurrentLang(false);
	$default_lang_code = flexicontent_html::getSiteDefaultLang();
	$lang_code         = isset($site_languages->{$current_lang_code}) ? $current_lang_code : $default_lang_code;
	$sef_lang_code     = isset($site_languages->{$lang_code}) ? $site_languages->{$lang_code}->sef : '';

	$document->addScriptDeclaration("
		if (typeof window.jbase_url_fc === 'undefined') window.jbase_url_fc = " . json_encode(\Joomla\CMS\Uri\Uri::base()) . ";
		if (typeof window.jroot_url_fc === 'undefined') window.jroot_url_fc = " . json_encode(\Joomla\CMS\Uri\Uri::root()) . ";
		if (!window.fc_sef_lang) window.fc_sef_lang = " . json_encode((string) $sef_lang_code) . ";
		var fcfav_toggle_style = " . $toggle_style . ";
		var fcfav_status_info = " . $status_info . ";
		var fcfav_toggle_info = " . $toggle_info . ";
	");

	$document->addScript(\Joomla\CMS\Uri\Uri::root(true).'/components/com_flexicontent/assets/js/fcfav.js', array('version' => FLEXI_VHASH));
	if (FLEXI_J40GE) $document->getWebAssetManager()->useScript('messages');

	// Make the CSRF token available to the JS, via: Joomla.getOptions('csrf.token')
	\Joomla\CMS\HTML\HTMLHelper::_('form.csrf');

	\Joomla\CMS\Language\Text::script('FLEXI_YOUR_BROWSER_DOES_NOT_SUPPORT_AJAX',true);
	\Joomla\CMS\Language\Text::script('FLEXI_LOADING',true);
	\Joomla\CMS\Language\Text::script('FLEXI_ADDED_TO_YOUR_FAVOURITES',true);
	\Joomla\CMS\Language\Text::script('FLEXI_YOU_NEED_TO_LOGIN',true);
	\Joomla\CMS\Language\Text::script('FLEXI_REMOVED_FROM_YOUR_FAVOURITES',true);
	\Joomla\CMS\Language\Text::script('FLEXI_USERS',true);  //5
	\Joomla\CMS\Language\Text::script('FLEXI_FAVOURE',true);
	\Joomla\CMS\Language\Text::script('FLEXI_REMOVE_FAVOURITE',true); //7
	\Joomla\CMS\Language\Text::script('FLEXI_FAVS_YOU_HAVE_SUBSCRIBED',true);
	\Joomla\CMS\Language\Text::script('FLEXI_FAVS_CLICK_TO_SUBSCRIBE',true);
	\Joomla\CMS\Language\Text::script('FLEXI_TOTAL',true);

	$js_and_css_added = true;
}

$icon_class = 'fcfavs-icon_box';

// Favs for guests disabled
if (!\Joomla\CMS\Factory::getApplication()->getIdentity()->id && !$allow_guests_favs)
{
	echo '
		<span class="' . $icon_class . '">
			' . $icon_login_fav . '
		</span>';
}

// Favs for logged user via DB, or guest user via COOKIE
else
{
	$btn_class  = $favoured === -1 ? 'fcfavs-delete-btn' : 'fcfavs-toggle-btn ' . $tooltip_class;
	$btn_text   = ($favoured === -1
		? $icon_del_fav
		: ($favoured ? $icon_is_fav : $icon_not_fav)
	);
	$btn_title = $favoured === -1 ? \Joomla\CMS\Language\Text::_('FLEXI_REMOVE_FAVOURITE') : $tooltip_title;

	$item_url  = $favoured === -1
		? $item->url
		: \Joomla\CMS\Router\Route::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, 0, $item));

	// Remove button (list of favourites): a click calls FCFav()
	if ($favoured === -1)
	{
		$onclick = ' onclick="' . "javascript:FCFav(".$item->id.", '".$type."', ".$users_counter.")" . '"';
		$toggle  = '';
	}

	// Add / remove button: a checkbox inside the label, so it takes the click, the Tab key and the Space key. fcfav.js listens to its change.
	else
	{
		$onclick = '';
		$toggle  = '<input type="checkbox" class="fcfavs-toggle-input"'
			. ' data-fcfav-id="' . htmlspecialchars($item->id, ENT_COMPAT, 'UTF-8') . '"'
			. ' data-fcfav-type="' . htmlspecialchars($type, ENT_COMPAT, 'UTF-8') . '"'
			. ' data-fcfav-counter="' . $users_counter . '"'
			. ' aria-label="' . htmlspecialchars(\Joomla\CMS\Language\Text::_('FLEXI_ADDREMOVE_FAVOURITE'), ENT_COMPAT, 'UTF-8') . '"'
			. ($favoured ? ' checked' : '') . ' />';
	}

	echo '
		<span class="' . $icon_class . '">
			<label' . $onclick . ' class="favlink_' . $type . '_' . $item->id . ' ' . $btn_class .'" title="' . $btn_title . '">
				' . $toggle . $btn_text . '
			</label>
			<span class="fav_item_id" style="display:none;">'.$item->id.'</span>
			<span class="fav_item_title" style="display:none;">'.$item->title.'</span>
			<span class="fav_item_url" style="display:none;">'.$item_url.'</span>
		</span>';
}

<?php
defined('JPATH_BASE') or die;

extract($displayData);

$tooltip_class = 'hasTooltip';

static $tooltip_title, $icon_not_fav, $icon_is_fav, $icon_login_fav;  // Reusable Texts / HTML for creating FAVs Icon
static $allow_guests_favs, $users_counter;  // Favourites field configuration

static $js_and_css_added = false;
if (!$js_and_css_added)
{
	$document = JFactory::getDocument();
	$cparams  = JComponentHelper::getParams('com_flexicontent');
	$use_font = $cparams->get('use_font_icons', 1);

	// Get Favourites field configuration (if FIELD is empty then retrieve it)
	$favs_field = $field ?: reset(FlexicontentFields::getFieldsByIds(array(12)));
	$favs_field->parameters = new JRegistry($favs_field->attribs);

	$toggle_style = (int) $favs_field->parameters->get('toggle_style', $use_font ? 2 : 1);
	$status_info  = (int) $favs_field->parameters->get('status_info', 0);
	$toggle_info  = (int) $favs_field->parameters->get('toggle_info', 1);

	switch ($toggle_style)
	{
		case 0: 
			$icon_del_fav = '<span class="icon-remove fcfavs-icon_delete"></span>';
			$icon_is_fav  = '<input data-on="&lt;i class=\'icon-heart fcfavs-icon_on\'&gt;&lt;/i&gt;" data-off="&lt;i class=\'icon-heart fcfavs-icon_off\'&gt;&lt;/i&gt;" data-toggle="toggle" type="checkbox" value="1" checked="checked" />';
			$icon_not_fav = '<input data-on="&lt;i class=\'icon-heart fcfavs-icon_on\'&gt;&lt;/i&gt;" data-off="&lt;i class=\'icon-heart fcfavs-icon_off\'&gt;&lt;/i&gt;" data-toggle="toggle" type="checkbox" value="1" />';

			$_attribs = 'class="icon-heart fcfavs-icon_login '.$tooltip_class.'" title="'.$tooltip_title.'" onclick="alert(\''.JText::_( 'FLEXI_FAVOURE_LOGIN_TIP', true ).'\')" ';
			$icon_login_fav = '<span class="fcfavs-btn"><span ' . $_attribs . '></span>';
			break;

		case 1: 
			$icon_del_fav = JHtml::image('components/com_flexicontent/assets/images/'.'cancel.png', JText::_('FLEXI_REMOVE_FAVOURITE'), 'class="fcfavs-img_icon"');
			$icon_is_fav  = JHtml::image('components/com_flexicontent/assets/images/'.'heart_full.png', JText::_('FLEXI_REMOVE_FAVOURITE'), 'class="fcfavs-img_icon"');
			$icon_not_fav = JHtml::image('components/com_flexicontent/assets/images/'.'heart_empty.png', JText::_('FLEXI_FAVOURE'), 'class="fcfavs-img_icon"');

			$_attribs = 'class="fcfavs-icon_login '.$tooltip_class.'" title="'.$tooltip_title.'" onclick="alert(\''.JText::_( 'FLEXI_FAVOURE_LOGIN_TIP', true ).'\')"';
			$icon_login_fav = '<span class="fcfavs-btn"><span ' . $_attribs . '>' . JHtml::image('components/com_flexicontent/assets/images/'.'heart_disabled.png', JText::_( 'FLEXI_FAVOURE' ), '') . '</span></span>';
			break;

		case 2: 
			$icon_del_fav = '<span class="fcfavs-btn"><span class="fcfavs-btn-inner fcfavs-heart-fill fcfavs-heart-delete"></span>';
			$icon_is_fav  = '<span class="fcfavs-btn"><span class="fcfavs-btn-inner fcfavs-heart-fill"></span>';
			$icon_not_fav = '<span class="fcfavs-btn"><span class="fcfavs-btn-inner fcfavs-heart-border"></span>';

			$_attribs = 'class="fcfavs-btn-inner fcfavs-heart-fill fcfavs-heart-login '.$tooltip_class.'" title="'.$tooltip_title.'" onclick="alert(\''.JText::_( 'FLEXI_FAVOURE_LOGIN_TIP', true ).'\')" ';
			$icon_login_fav = '<span class="fcfavs-btn"><span ' . $_attribs . '></span></span>';
			break;
	}

	$allow_guests_favs = (int) $favs_field->parameters->get('allow_guests_favs', 1);
	$users_counter     = (int) $favs_field->parameters->get('display_favoured_usercount', 0);

	$text 		= JFactory::getUser()->id || $allow_guests_favs ? 'FLEXI_ADDREMOVE_FAVOURITE' : 'FLEXI_FAVOURE';
	$overlib 	= JFactory::getUser()->id || $allow_guests_favs ? 'FLEXI_ADDREMOVE_FAVOURITE_TIP' : 'FLEXI_FAVOURE_LOGIN_TIP';
	$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 1, 1);

	// Load JS / CSS
	if ($cparams->get('add_tooltips', 1))
	{
		JHtml::_('bootstrap.tooltip');
	}

	flexicontent_html::loadFramework('jQuery');
	flexicontent_html::loadFramework('flexi_tmpl_common');
	flexicontent_html::loadFramework('flexi-lib');
	flexicontent_html::loadFramework('bootstrap-toggle');

	$document->addScriptDeclaration("
		var fcfav_toggle_style = " . $toggle_style . ";
		var fcfav_status_info = " . $status_info . ";
		var fcfav_toggle_info = " . $toggle_info . ";
	");

	$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/fcfav.js', array('version' => FLEXI_VHASH));

	JText::script('FLEXI_YOUR_BROWSER_DOES_NOT_SUPPORT_AJAX',true);
	JText::script('FLEXI_LOADING',true);
	JText::script('FLEXI_ADDED_TO_YOUR_FAVOURITES',true);
	JText::script('FLEXI_YOU_NEED_TO_LOGIN',true);
	JText::script('FLEXI_REMOVED_FROM_YOUR_FAVOURITES',true);
	JText::script('FLEXI_USERS',true);  //5
	JText::script('FLEXI_FAVOURE',true);
	JText::script('FLEXI_REMOVE_FAVOURITE',true); //7
	JText::script('FLEXI_FAVS_YOU_HAVE_SUBSCRIBED',true);
	JText::script('FLEXI_FAVS_CLICK_TO_SUBSCRIBE',true);
	JText::script('FLEXI_TOTAL',true);

	$js_and_css_added = true;
}

$icon_class = 'fcfavs-icon_box';

// Favs for guests disabled
if (!JFactory::getUser()->id && !$allow_guests_favs)
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
	$btn_title = $favoured === -1 ? JText::_('FLEXI_REMOVE_FAVOURITE') : $tooltip_title;
	
	$item_url  = $favoured === -1
		? $item->url
		: JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, 0, $item));

	$onclick 	= "javascript:FCFav(".$item->id.", '".$type."', ".$users_counter.")";

	echo '
		<span class="' . $icon_class . '">
			<label onclick="' . $onclick . '" class="favlink_' . $type . '_' . $item->id . ' ' . $btn_class .'" title="' . $btn_title . '">
				' . $btn_text . '
			</label>
			<span class="fav_item_id" style="display:none;">'.$item->id.'</span>
			<span class="fav_item_title" style="display:none;">'.$item->title.'</span>
			<span class="fav_item_url" style="display:none;">'.$item_url.'</span>
		</span>';
}

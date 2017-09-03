<?php
defined('JPATH_BASE') or die;

extract($displayData);

$tooltip_class = 'hasTooltip';

static $tooltip_title, $icon_not_fav, $icon_is_fav, $icon_disabled_fav;  // Reusable Texts / HTML for creating FAVs Icon
static $allow_guests_favs, $users_counter;  // Favourites field configuration

static $js_and_css_added = false;
if (!$js_and_css_added)
{
	$document = JFactory::getDocument();
	$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
	$use_font = $cparams->get('use_font_icons', 1);

	$icon_del_fav = !$use_font
		? JHtml::image('components/com_flexicontent/assets/images/'.'cancel.png', JText::_('FLEXI_REMOVE_FAVOURITE'), NULL)
		: '<span class="icon-remove fcfav_icon_delete"></span>';
	$icon_is_fav = !$use_font
		? JHtml::image('components/com_flexicontent/assets/images/'.'heart_delete.png', JText::_('FLEXI_REMOVE_FAVOURITE'), NULL)
		: '<span class="icon-heart fcfav_icon_on"></span>';
	$icon_not_fav = !$use_font
		? JHtml::image('components/com_flexicontent/assets/images/'.'heart_add.png', JText::_('FLEXI_FAVOURE'), NULL)
		: '<span class="icon-heart fcfav_icon_off"></span>';

	$_attribs = 'class="btn '.$tooltip_class.'" title="'.$tooltip_title.'" onclick="alert(\''.JText::_( 'FLEXI_FAVOURE_LOGIN_TIP', true ).'\')" ';
	$icon_disabled_fav = !$use_font
		? JHtml::image('components/com_flexicontent/assets/images/'.'heart_login.png', JText::_( 'FLEXI_FAVOURE' ), $_attribs)
		: '<span class="icon-heart fcfav_icon_disabled"></span>';

	// Get Favourites field configuration (if FIELD is empty then retrieve it)
	$favs_field = $field ?: reset(FlexicontentFields::getFieldsByIds(array(12)));
	$favs_field->parameters = new JRegistry($favs_field->attribs);

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

	$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/fcfav.js', FLEXI_VHASH);

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
	$document->addScriptDeclaration('
		var fcfav_rfolder = "'.JUri::root(true).'";
	');

	$js_and_css_added = true;
}

// Favs for guests disabled
if (!JFactory::getUser()->id && !$allow_guests_favs)
{
	echo $icon_disabled_fav;
}

// Favs for logged user via DB, or guest user via COOKIE
else
{
	$icon_class = 'fcfav_icon';
	$btn_class  = $favoured === -1 ? 'fcfav-delete-btn' : 'fcfav-toggle-btn ' . $tooltip_class;
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
			<span onclick="' . $onclick . '" class="favlink_' . $type . '_' . $item->id . ' btn ' . $btn_class .'" title="' . $btn_title . '">
				' . $btn_text . '
			</span>
			<span class="fav_item_id" style="display:none;">'.$item->id.'</span>
			<span class="fav_item_title" style="display:none;">'.$item->title.'</span>
			<span class="fav_item_url" style="display:none;">'.$item_url.'</span>
		</span>';
}

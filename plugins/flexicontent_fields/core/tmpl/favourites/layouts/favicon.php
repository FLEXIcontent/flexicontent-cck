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

	$icon_is_fav = !$use_font
		? JHTML::image('components/com_flexicontent/assets/images/'.'heart_delete.png', JText::_('FLEXI_REMOVE_FAVOURITE'), NULL)
		: '<span class="icon-heart fcfav_icon_on"></span>';
	$icon_not_fav = !$use_font
		? JHTML::image('components/com_flexicontent/assets/images/'.'heart_add.png', JText::_('FLEXI_FAVOURE'), NULL)
		: '<span class="icon-heart fcfav_icon_off"></span>';

	$_attribs = 'class="btn '.$tooltip_class.'" title="'.$tooltip_title.'" onclick="alert(\''.JText::_( 'FLEXI_FAVOURE_LOGIN_TIP', true ).'\')" ';
	$icon_disabled_fav = !$use_font
		? JHTML::image('components/com_flexicontent/assets/images/'.'heart_login.png', JText::_( 'FLEXI_FAVOURE' ), $_attribs)
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

	$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/fcfav.js', FLEXI_VHASH);

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
		var fcfav_rfolder = "'.JURI::root(true).'";
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
	$link_class = $favoured ? 'fcfav_delete' : 'fcfav_add';
	$link_text  = $favoured ? $icon_is_fav : $icon_not_fav;

	$onclick 	= "javascript:FCFav(".$item->id.", '".$type."', ".$users_counter.")";
	$link 		= "javascript:void(null)";

	echo '
		<span class="' . $link_class . '">
			<a id="favlink_' . $type . '_' . $item->id . '" href="' . $link . '" onclick="' . $onclick . '" class="btn fcfav-reponse '.$tooltip_class.'" title="'.$tooltip_title.'">
				' . $link_text . '
			</a>
			<span class="fav_item_id" style="display:none;">'.$item->id.'</span>
			<span class="fav_item_title" style="display:none;">'.$item->title.'</span>
		</span>';
}

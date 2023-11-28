<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

$layouts_path = null; //realpath(JPATH_ROOT.DS.'plugins'.DS.'flexicontent_fields'.DS.'core');

$displayData = array(
	'field' => $field,
	'item' => $item,
	'favoured' => $favoured,
	'favourites' => $favourites,
	'type' => 'item'
);

//ob_start();  include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'layouts'.DS.'flexicontent_fields'.DS.'favourites'.DS.'favicon.php');   $favicon  = ob_get_contents();  ob_end_clean();
//ob_start();  include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'layouts'.DS.'flexicontent_fields'.DS.'favourites'.DS.'userlist.php');  $userlist = ob_get_contents();  ob_end_clean();

$field->{$prop} =
	$pretext . '
		<div class="fav-block">
			' .
			JLayoutHelper::render('flexicontent_fields.favourites.favicon', $displayData, $layouts_path) .
			JLayoutHelper::render('flexicontent_fields.favourites.userlist', $displayData, $layouts_path)
			. '
			<div class="fcfavs-responce_item_' . $item->id . ' fcfavs-responce-tip">
				' . ($field->parameters->get('status_info', 0) ? '
				<div class="fc-mssg fc-info fc-iblock fc-nobgimage '.($favoured ? 'fcfavs-is-subscriber' : 'fcfavs-isnot-subscriber').'">
					' . JText::_($favoured ? 'FLEXI_FAVS_YOU_HAVE_SUBSCRIBED' : 'FLEXI_FAVS_CLICK_TO_SUBSCRIBE') . '
				</div>
				' : '') . '
			</div>
		</div>
	' . $posttext;

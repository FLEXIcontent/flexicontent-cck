<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

$field_layouts_path = realpath(JPATH_ROOT.DS.'plugins'.DS.'flexicontent_fields'.DS.'core');

$layoutData = array(
	'field' => $field,
	'item' => $item,
	'favoured' => $favoured,
	'favourites' => $favourites,
	'type' => 'item'
);

//ob_start();  include('layouts'.DS.'favicon.php');   $favicon  = ob_get_contents();  ob_end_clean();
//ob_start();  include('layouts'.DS.'userlist.php');  $userlist = ob_get_contents();  ob_end_clean();

$field->{$prop} =
	$pretext . '
		<div class="fav-block">
			' . JLayoutHelper::render('tmpl.favourites.layouts.favicon', $layoutData, $field_layouts_path) .'
			<div id="fcfav-reponse_item_'.$item->id.'" class="fcfav-reponse-tip">
				<div class="fc-mssg fc-info fc-iblock fc-nobgimage '.($favoured ? 'fcfavs-is-subscriber' : 'fcfavs-isnot-subscriber').'">
					' . JText::_($favoured ? 'FLEXI_FAVS_YOU_HAVE_SUBSCRIBED' : 'FLEXI_FAVS_CLICK_TO_SUBSCRIBE') . '
				</div>
				'. JLayoutHelper::render('tmpl.favourites.layouts.userlist', $layoutData, $field_layouts_path) .'
			</div>
		</div>
	' . $posttext;

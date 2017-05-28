<?php

// DO NOT OVERRIDE THIS FILE YET !!
// API Methods will change !! and their HTML / JS will be moved here

$field->{$prop} =
	$pretext . '
		<div class="fav-block">
			'.flexicontent_html::favicon( $field, $favoured, $item ).'
			<div id="fcfav-reponse_item_'.$item->id.'" class="fcfav-reponse-tip">
				<div class="fc-mssg fc-info fc-iblock fc-nobgimage '.($favoured ? 'fcfavs-is-subscriber' : 'fcfavs-isnot-subscriber').'">
					'.JText::_($favoured ? 'FLEXI_FAVS_YOU_HAVE_SUBSCRIBED' : 'FLEXI_FAVS_CLICK_TO_SUBSCRIBE').'
				</div>
				'. flexicontent_html::favoured_userlist( $field, $item, $favourites) .'
			</div>
		</div>
	' . $posttext;

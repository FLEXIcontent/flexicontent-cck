<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

ob_start();
include ('layouts'.DS.'$favicon.php');
$favicon = ob_get_contents();
ob_end_clean();

ob_start();
include ('layouts'.DS.'userlist.php');
$userlist = ob_get_contents();
ob_end_clean();

$field->{$prop} =
	$pretext . '
		<div class="fav-block">
			' . $favicon .'
			<div id="fcfav-reponse_item_'.$item->id.'" class="fcfav-reponse-tip">
				<div class="fc-mssg fc-info fc-iblock fc-nobgimage '.($favoured ? 'fcfavs-is-subscriber' : 'fcfavs-isnot-subscriber').'">
					'.JText::_($favoured ? 'FLEXI_FAVS_YOU_HAVE_SUBSCRIBED' : 'FLEXI_FAVS_CLICK_TO_SUBSCRIBE').'
				</div>
				'. $userlist .'
			</div>
		</div>
	' . $posttext;

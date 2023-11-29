<?php

// DO NOT OVERRIDE THIS FILE YET !!
// API Methods will change !! and their HTML / JS will be moved here

$field->{$prop} =
	$pretext
		. flexicontent_html::ItemVote( $field, 'all', $vote ) .
	$posttext;


if ($allow_reviews)
{
	$box_js = ' var box = document.getElementById(\'fc_content_item_id_reviews_box_' . $item->id . '\'); ';

	$reviews_text = '
		<div id="fc_content_item_id_reviews_box_' . $item->id . '">
	';

	foreach($reviews as $review)
	{
		$reviews_text .=
			'<h3>' . $review->title . '</h3>' .
			'<p>' . $review->text . '</p>' .
			'<hr>';
	}
	$reviews_text .= '
		</div>
	';

	if ($reviews_placement === 1)
	{
		$field->{$prop} .= '
			<div style="display: none;">
				'. $reviews_text . '
			</div>
			<div>
				<a class="btn btn-info" style="cursor: pointer; font-size: 120%; text-decoration: none;" href="javascript:;" onclick="' . $box_js . ' var reviews_box = fc_showAsDialog(box, 800, 600, null, { title: \'' . count($reviews) . ' ' . JText::_('FLEXI_REVIEWS') . '\'}); return false;">
					<span class="icon-smiley-happy-2"></span>
					' . count($reviews) . ' ' . JText::_('FLEXI_REVIEWS') . '
				</a>
			</div>';
	}
	else // $reviews_placement === 0
	{
		$field->{$prop} .= $reviews_text;
	}
}
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

	// Number of stars to display (same as the voting field)
	$rv_resolution = max(5, min(100, (int) $field->parameters->get('rating_resolution', 5)));
	$rv_stars = (int) $field->parameters->get('rating_stars', 5);
	$rv_stars = max(1, min(10, $rv_stars > 0 ? min($rv_stars, $rv_resolution) : $rv_resolution));

	// Average rating of the item (the votes, that include the ratings of the reviews)
	$rv_votes = !empty($vote->rating_count) ? (int) $vote->rating_count : 0;
	$rv_avg_percent = $rv_votes
		? ((float) $vote->rating_sum / $rv_votes) * (100 / $rv_resolution)
		: 0;

	// Fallback: average rating of the displayed reviews
	if (!$rv_votes)
	{
		$rv_sum = 0;

		foreach ($reviews as $review)
		{
			if ((int) $review->average_rating > 0)
			{
				$rv_votes++;
				$rv_sum += (int) $review->average_rating;
			}
		}

		$rv_avg_percent = $rv_votes ? $rv_sum / $rv_votes : 0;
	}

	$reviews_text = '
		<div id="fc_content_item_id_reviews_box_' . $item->id . '" class="fcvote-reviews">';

	if ($rv_votes)
	{
		$reviews_text .= '
			<div class="fcvote-reviews-summary">
				<span class="fcvote-reviews-avg">' . number_format($rv_avg_percent * $rv_stars / 100, 1) . '</span>
				' . flexicontent_html::reviewStars($rv_avg_percent, $rv_stars, '1.3em') . '
				<span class="fcvote-reviews-count">' . $rv_votes . ' ' . \Joomla\CMS\Language\Text::_($rv_votes > 1 ? 'FLEXI_VOTES' : 'FLEXI_VOTE') . ' &middot; ' . count($reviews) . ' ' . \Joomla\CMS\Language\Text::_('FLEXI_REVIEWS') . '</span>
			</div>';
	}

	foreach ($reviews as $review)
	{
		$rv_author = $review->author_name
			? $review->author_name
			: \Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_GUEST');

		$rv_date = $review->submit_date && strpos($review->submit_date, '0000') !== 0 && strpos($review->submit_date, '1000') !== 0
			? \Joomla\CMS\HTML\HTMLHelper::_('date', $review->submit_date, \Joomla\CMS\Language\Text::_('DATE_FORMAT_LC4'))
			: '';

		$rv_edited = $review->update_date && strpos($review->update_date, '0000') !== 0 && strpos($review->update_date, '1000') !== 0;

		$reviews_text .= '
			<div class="fcvote-review-item">
				<div class="fcvote-review-head">
					<span class="fcvote-review-author">' . htmlspecialchars((string) $rv_author, ENT_QUOTES, 'UTF-8') . '</span>'
					. (!(int) $review->approved ? ' <span class="fcvote-review-pending badge bg-warning text-dark">' . \Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_PENDING') . '</span>' : '') . '
				</div>
				<div class="fcvote-review-meta">'
					. ((int) $review->average_rating > 0 ? flexicontent_html::reviewStars($review->average_rating, $rv_stars, '1em') : '')
					. ($rv_date ? ' <span class="fcvote-review-date">' . htmlspecialchars($rv_date, ENT_QUOTES, 'UTF-8') . '</span>' : '')
					. ($rv_edited ? ' <span class="fcvote-review-edited">(' . \Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_EDITED') . ')</span>' : '') . '
				</div>'
				. ((string) $review->title !== '' ? '<h4 class="fcvote-review-title">' . htmlspecialchars((string) $review->title, ENT_QUOTES, 'UTF-8') . '</h4>' : '') . '
				<p class="fcvote-review-text">' . nl2br(htmlspecialchars((string) $review->text, ENT_QUOTES, 'UTF-8')) . '</p>
			</div>';
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
				<a class="btn btn-info" style="cursor: pointer; font-size: 120%; text-decoration: none;" href="javascript:;" onclick="' . $box_js . ' var reviews_box = fcvote_showAsDialog(box, 800, 600, null, { title: \'' . count($reviews) . ' ' . \Joomla\CMS\Language\Text::_('FLEXI_REVIEWS') . '\'}); return false;">
					<span class="icon-smiley-happy-2"></span>
					' . count($reviews) . ' ' . \Joomla\CMS\Language\Text::_('FLEXI_REVIEWS') . '
				</a>
			</div>';
	}
	else // $reviews_placement === 0
	{
		$field->{$prop} .= $reviews_text;
	}
}
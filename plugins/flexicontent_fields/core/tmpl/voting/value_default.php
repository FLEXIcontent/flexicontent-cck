<?php

// DO NOT OVERRIDE THIS FILE YET !!
// API Methods will change !! and their HTML / JS will be moved here

// List-only mode ($prop = 'display_reviews'): used by the 'getreviewslist' task of the component, to load the list of reviews on demand
$rv_list_only = ($prop === 'display_reviews');

$field->{$prop} = $rv_list_only
	? ''
	: $pretext
		. flexicontent_html::ItemVote( $field, 'all', $vote ) .
	$posttext;


if ($allow_reviews)
{
	$rv_user    = \Joomla\CMS\Factory::getApplication()->getIdentity();
	$rv_item_id = (int) $item->id;
	$rv_token   = \Joomla\CMS\Session\Session::getFormToken();

	// The full reviews UI is displayed only in the item view, in the other views (lists) only the stars and the (optional) reviews counter are displayed
	$rv_item_view = $rv_list_only || (isset($view) && $view === 'item');

	// Number of reviews loaded at a time
	$rv_page_size = max(1, min(50, (int) $field->parameters->get('reviews_page_size', 5)));

	// Number of (visible) reviews, a single query for all the items of the page
	$rv_ids = array($rv_item_id);

	foreach ((isset($items) && is_array($items)) ? $items : array() as $rv_other_item)
	{
		$rv_ids[] = (int) $rv_other_item->id;
	}

	$rv_counts     = flexicontent_html::getItemReviewCounts($rv_ids);
	$reviews_total = (int) ($rv_counts[$rv_item_id] ?? 0);

	// Number of reviews, with plural: '1 Review', '2 Reviews'
	$rv_count_label = \Joomla\CMS\Language\Text::plural('FLEXI_VOTE_REVIEWS_N', $reviews_total);

	// Small icon of the reviews
	$rv_icon_bubble = '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false" style="flex:none;display:block;"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';

	$field->display_reviews = '';


	/**
	 * Lists (category, module, ...): only the stars and the reviews counter (it can be disabled in the field configuration)
	 */

	if (!$rv_item_view)
	{
		if ($reviews_total && (int) $field->parameters->get('reviews_counter_in_lists', 1))
		{
			$field->{$prop} .= '
				<div class="fcvote-reviews-trigger-row">
					<span class="fcvote-reviews-counter" style="display:inline-flex;align-items:center;gap:6px;color:#374151;font-size:0.85em;font-weight:500;line-height:1.5;">
						<span style="color:#2b6fd6;display:inline-flex;">' . $rv_icon_bubble . '</span>
						<span>' . $rv_count_label . '</span>
					</span>
				</div>';
		}

		return;
	}


	// Average rating of the item (the votes, that include the ratings of the reviews)
	$rv_resolution = max(5, min(100, (int) $field->parameters->get('rating_resolution', 5)));
	$rv_stars = (int) $field->parameters->get('rating_stars', 5);
	$rv_stars = max(1, min(10, $rv_stars > 0 ? min($rv_stars, $rv_resolution) : $rv_resolution));

	$rv_votes = !empty($vote->rating_count) ? (int) $vote->rating_count : 0;
	$rv_avg_percent = $rv_votes
		? ((float) $vote->rating_sum / $rv_votes) * (100 / $rv_resolution)
		: 0;

	// Can the current user submit a review (same rules as the review form of the site controller)
	switch ((int) $field->parameters->get('allow_submit_review_by', 0))
	{
		case 1:
			$rv_can_submit = true;
			break;
		case 2:
			$rv_can_submit = (bool) $rv_user->id;
			break;
		case 3:
			$rv_can_submit = in_array((int) $field->parameters->get('review_submit_access', 1), $rv_user->getAuthorisedViewLevels());
			break;
		default:
			$rv_can_submit = false;
			break;
	}

	// Buttons: self-contained styles (site templates may style buttons differently) and inline SVG icons
	$rv_btn_style = 'display:inline-flex;align-items:center;gap:6px;flex:0 0 auto;width:auto;margin:0;padding:4px 12px;'
		. 'background:#fff;border:1px solid;border-radius:6px;cursor:pointer;font-size:0.9em;font-weight:500;line-height:1.4;'
		. 'text-transform:none;letter-spacing:normal;text-decoration:none;';
	$rv_btn_primary = 'display:inline-flex;align-items:center;gap:8px;flex:0 0 auto;width:auto;margin:0;padding:8px 18px;'
		. 'background:#2b6fd6;color:#fff;border:1px solid #2b6fd6;border-radius:8px;cursor:pointer;font-size:0.95em;font-weight:500;line-height:1.4;'
		. 'text-transform:none;letter-spacing:normal;text-decoration:none;';
	// Small outline button, used under the stars by both 'No reviews, be first' and 'N Reviews' (same look and size)
	$rv_btn_small = 'display:inline-flex;align-items:center;gap:6px;flex:0 0 auto;width:auto;height:auto;min-height:0;margin:0;padding:2px 12px;'
		. 'background:#fff;color:#2b6fd6;border:1px solid #2b6fd6;border-radius:8px;cursor:pointer;font-size:0.85em;font-weight:500;line-height:1.5;'
		. 'text-transform:none;letter-spacing:normal;text-decoration:none;box-shadow:none;';
	$rv_btn_outline = 'display:inline-flex;align-items:center;gap:8px;flex:0 0 auto;width:auto;margin:0;padding:6px 16px;'
		. 'background:#fff;color:#2b6fd6;border:1px solid #2b6fd6;border-radius:8px;cursor:pointer;font-size:0.9em;font-weight:500;line-height:1.4;'
		. 'text-transform:none;letter-spacing:normal;text-decoration:none;';

	$rv_svg_open   = '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false" style="flex:none;display:block;"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" ';
	$rv_icon_check = $rv_svg_open . 'stroke-width="3" d="M4 12.5l5 5L20 6.5"/></svg>';
	$rv_icon_trash = $rv_svg_open . 'stroke-width="2" d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg>';
	$rv_icon_edit  = $rv_svg_open . 'stroke-width="2" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>';

	// Opens the review form (new review or edit of the review of the user) in a modal
	$rv_open_form_js = 'fcvote_open_review_form(\'fcvote_review_form_box_' . $rv_item_id . '\', ' . $rv_item_id . ', \'item\'); return false;';

	// Button that opens the review form, the label depends on the case: 'Review this item' or 'No reviews, be first'
	$rv_review_btn = function ($label_key, $style) use ($rv_open_form_js, $rv_icon_edit)
	{
		return '<button type="button" class="fcvote-review-btn fcvote-review-btn-new" style="' . $style . '" onclick="' . $rv_open_form_js . '">'
			. $rv_icon_edit . '<span>' . \Joomla\CMS\Language\Text::_($label_key) . '</span></button>';
	};

	// 'Useful' votes ('Was this helpful?'), 0: disabled, 1: logged users can vote, 2: anyone can vote (guests are identified by IP address)
	$rv_useful_mode = (int) $field->parameters->get('reviews_useful', 1);
	$rv_can_vote    = $rv_useful_mode === 2 || ($rv_useful_mode === 1 && $rv_user->id);
	$rv_my_votes    = array();  // review id => 1 (useful) or 0 (not useful), set before the reviews are rendered

	$rv_useful_btn  = 'display:inline-flex;align-items:center;width:auto;height:auto;min-height:0;margin:0;padding:0 15px;background:#fff;color:#1f2937;border:1px solid #c4c7cc;'
		. 'border-radius:999px;cursor:pointer;font-size:0.9em;font-weight:500;line-height:1.5;text-transform:none;letter-spacing:normal;text-decoration:none;box-shadow:none;';

	// 'All reviews' button (Inline placement), centered, filled
	$rv_btn_all = 'display:inline-flex;align-items:center;gap:8px;flex:0 0 auto;width:auto;margin:0 auto;padding:6px 16px;'
		. 'background:#508dae;color:#ffffff;border:0;border-radius:8px;cursor:pointer;font-size:0.9em;font-weight:500;line-height:1.4;'
		. 'text-transform:none;letter-spacing:normal;text-decoration:none;box-shadow:none;';

	// Composite voting: the characteristics are displayed as a second row of small stars in every review
	$rv_chars = flexicontent_html::getVotingCharacteristics($field);

	// Renders a review of the list
	$rv_render = function ($review, $rv_own) use ($rv_chars, $rv_user, $rv_stars, $rv_token, $rv_item_id, $rv_btn_style, $rv_btn_outline, $rv_icon_check, $rv_icon_trash, $rv_icon_edit, $rv_useful_mode, $rv_can_vote, $rv_useful_btn, &$rv_my_votes)
	{
		// Pending (unapproved) reviews are displayed dimmed, moderators can approve them or move them to the trash
		$rv_pending = !(int) $review->approved;
		$rv_is_moderator = $rv_user->authorise('core.edit.state', 'com_content.article.' . (int) $review->content_id);
		$rv_can_moderate = $rv_pending && $rv_is_moderator;

		// Moderators see a row of buttons: Edit (quick edit of typos, approval) for the reviews of other users, Approve / Trash for the pending reviews
		$rv_show_mod_row = $rv_can_moderate || ($rv_is_moderator && !$rv_own);

		$rv_author = $review->author_name
			? $review->author_name
			: \Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_GUEST');

		$rv_date = $review->submit_date && strpos($review->submit_date, '0000') !== 0 && strpos($review->submit_date, '1000') !== 0
			? \Joomla\CMS\HTML\HTMLHelper::_('date', $review->submit_date, \Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_DATE_FORMAT'))
			: '';

		$rv_edited = $review->update_date && strpos($review->update_date, '0000') !== 0 && strpos($review->update_date, '1000') !== 0;

		// 'Useful' block: number of people that found the review useful, and the 'Was this helpful?' question with Yes / No buttons
		// (only for approved reviews of other users, the author of the review sees only the number)
		$rv_useful_html = '';
		$rv_useful_yes  = (int) $review->useful_yes;
		$rv_show_vote   = $rv_can_vote && !$rv_own && !$rv_pending;

		if ($rv_useful_mode && !$rv_pending && ($rv_useful_yes || $rv_show_vote))
		{
			$rv_my_vote = $rv_my_votes[(int) $review->id] ?? null;

			$rv_useful_html = '
				<div class="fcvote-review-useful" data-id="' . (int) $review->id . '">
					<div class="fcvote-review-useful-count"' . ($rv_useful_yes ? '' : ' style="display:none;"') . '>'
						. ($rv_useful_yes ? \Joomla\CMS\Language\Text::plural('FLEXI_VOTE_REVIEW_USEFUL_N', $rv_useful_yes) : '') . '</div>'
					. ($rv_show_vote ? '
					<div class="fcvote-review-useful-ask">
						<span class="fcvote-review-useful-question">' . \Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_USEFUL_ASK') . '</span>
						<button type="button" class="fcvote-useful-btn' . ($rv_my_vote === 1 ? ' is-active' : '') . '" data-vote="yes" data-token="' . $rv_token . '"
							style="' . $rv_useful_btn . '" onclick="return fcvote_review_useful(this, ' . (int) $review->id . ', \'yes\');">' . \Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_USEFUL_YES') . '</button>
						<button type="button" class="fcvote-useful-btn' . ($rv_my_vote === 0 ? ' is-active' : '') . '" data-vote="no" data-token="' . $rv_token . '"
							style="' . $rv_useful_btn . '" onclick="return fcvote_review_useful(this, ' . (int) $review->id . ', \'no\');">' . \Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_USEFUL_NO') . '</button>
					</div>' : '') . '
				</div>';
		}

		// Composite voting: ratings of the characteristics of this review
		$rv_extra_html = '';

		if ($rv_chars && trim((string) $review->custom_ratings) !== '')
		{
			$rv_extra = (array) json_decode((string) $review->custom_ratings, true);

			foreach ($rv_chars as $rv_xid => $rv_char)
			{
				if (!isset($rv_extra[$rv_xid]))
				{
					continue;
				}

				$rv_extra_html .= '<span class="fcvote-review-extra-item">'
					. '<span class="fcvote-review-extra-title">' . htmlspecialchars($rv_char->title !== '' ? $rv_char->title : (string) $rv_xid, ENT_QUOTES, 'UTF-8') . '</span> '
					. flexicontent_html::reviewStars((int) $rv_extra[$rv_xid], $rv_stars, '0.8em')
					. '</span>';
			}

			$rv_extra_html = $rv_extra_html !== '' ? '<div class="fcvote-review-extra">' . $rv_extra_html . '</div>' : '';
		}

		return '
			<div class="fcvote-review-item' . ($rv_pending ? ' fcvote-review-pending-item' : '') . '" id="review_' . (int) $review->id . '">
				<div class="fcvote-review-head">
					<span class="fcvote-review-author">' . htmlspecialchars((string) $rv_author, ENT_QUOTES, 'UTF-8') . '</span>'
					. ($rv_pending ? ' <span class="fcvote-review-pending badge bg-warning text-dark">' . \Joomla\CMS\Language\Text::_($rv_own ? 'FLEXI_VOTE_REVIEW_PENDING' : 'FLEXI_VOTE_REVIEW_PENDING_ADMIN') . '</span>' : '') . '
				</div>
				<div class="fcvote-review-meta">'
					. ((int) $review->average_rating > 0 ? flexicontent_html::reviewStars($review->average_rating, $rv_stars, '1em') : '')
					. ($rv_date ? ' <span class="fcvote-review-date">' . htmlspecialchars($rv_date, ENT_QUOTES, 'UTF-8') . '</span>' : '')
					. ($rv_edited ? ' <span class="fcvote-review-edited">(' . \Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_EDITED') . ')</span>' : '') . '
				</div>'
				. $rv_extra_html
				. ((string) $review->title !== '' ? '<h4 class="fcvote-review-title">' . htmlspecialchars((string) $review->title, ENT_QUOTES, 'UTF-8') . '</h4>' : '') . '
				<p class="fcvote-review-text">' . nl2br(htmlspecialchars((string) $review->text, ENT_QUOTES, 'UTF-8')) . '</p>'
				. $rv_useful_html
				. ($rv_own ? '
				<div class="fcvote-review-actions" style="display:flex;justify-content:flex-start;align-items:center;gap:8px;margin-top:10px;">
					<button type="button" class="fcvote-review-btn fcvote-review-btn-edit"
						style="' . $rv_btn_outline . '"
						onclick="fcvote_open_review_form(\'fcvote_review_form_box_' . $rv_item_id . '\', ' . $rv_item_id . ', \'item\'); return false;">' . $rv_icon_edit . '<span>' . \Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_EDIT') . '</span></button>
					<button type="button" class="fcvote-review-btn fcvote-review-btn-delete" data-token="' . $rv_token . '"
						style="' . $rv_btn_style . 'color:#dc3545;border-color:#dc3545;padding:6px 16px;border-radius:8px;"
						data-confirm="' . htmlspecialchars(\Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_DELETE_CONFIRM'), ENT_COMPAT, 'UTF-8') . '"
						onclick="return fcvote_review_delete_own(this, ' . $rv_item_id . ');">' . $rv_icon_trash . '<span>' . \Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_DELETE_BTN') . '</span></button>
				</div>' : '')
				. ($rv_show_mod_row ? '
				<div class="fcvote-review-moderate" style="display:flex;justify-content:flex-start;align-items:center;gap:8px;margin-top:8px;">'
					. (!$rv_own ? '
					<button type="button" class="fcvote-review-btn fcvote-review-btn-modedit"
						style="' . $rv_btn_style . 'color:#2b6fd6;border-color:#2b6fd6;"
						onclick="return fcvote_review_edit_open(' . (int) $review->id . ', ' . htmlspecialchars(json_encode(\Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_EDIT_REVIEW')), ENT_COMPAT, 'UTF-8') . ', this);">' . $rv_icon_edit . '<span>' . \Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_EDIT') . '</span></button>' : '')
					. ($rv_can_moderate ? '
					<button type="button" class="fcvote-review-btn fcvote-review-btn-approve" data-token="' . $rv_token . '"
						style="' . $rv_btn_style . 'color:#198754;border-color:#198754;"
						onclick="return fcvote_review_moderate(this, \'approve\', ' . (int) $review->id . ');">' . $rv_icon_check . '<span>' . \Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_APPROVE') . '</span></button>
					<button type="button" class="fcvote-review-btn fcvote-review-btn-trash" data-token="' . $rv_token . '"
						style="' . $rv_btn_style . 'color:#dc3545;border-color:#dc3545;"
						data-confirm="' . htmlspecialchars(\Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_TRASH_CONFIRM'), ENT_COMPAT, 'UTF-8') . '"
						onclick="return fcvote_review_moderate(this, \'trash\', ' . (int) $review->id . ');">' . $rv_icon_trash . '<span>' . \Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_TRASH') . '</span></button>' : '') . '
				</div>' : '') . '
			</div>';
	};

	// The 'loading' marker at the end of the list: when it becomes visible (scrolling), the next reviews are loaded (see fcvote.js)
	// (the loader is optional, without it the marker is empty, it is still needed to detect the end of the scrolling)
	$rv_show_loader = (int) $field->parameters->get('reviews_loader', 1);

	$rv_more_marker = function ($next) use ($rv_show_loader)
	{
		return '<div class="fcvote-reviews-more" data-next="' . (int) $next . '" role="status">'
			. ($rv_show_loader
				? '<span class="fcvote-loader" aria-hidden="true"></span>'
					. '<span class="fcvote-loader-text">' . \Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_LOADING') . '</span>'
				: '')
			. '</div>';
	};

	// The end of the list, after the last reviews were loaded by scrolling (displayed with the loader)
	$rv_end_marker = $rv_show_loader
		? '<div class="fcvote-reviews-end"><span>' . \Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_END') . '</span></div>'
		: '';


	// Single review mode (list-only): only the given review is rendered, e.g. after the quick edit by a moderator
	if ($rv_list_only && !empty($rv_single_review_id))
	{
		$rv_db = \Joomla\CMS\Factory::getDbo();

		$rv_single = $rv_db->setQuery(
			$rv_db->getQuery(true)
				->select('r.*')
				->select('CASE WHEN u.id IS NOT NULL THEN u.name ELSE r.name END AS author_name')
				->from('#__flexicontent_reviews AS r')
				->join('LEFT', '#__users AS u ON u.id = r.user_id')
				->where('r.id = ' . (int) $rv_single_review_id)
				->where('r.content_id = ' . $rv_item_id)
		)->loadObject();

		if ($rv_single)
		{
			if ($rv_useful_mode && $rv_can_vote)
			{
				$rv_my_votes = flexicontent_html::getReviewUsefulVotes(array((int) $rv_single->id));
			}

			$field->display_reviews = $rv_render($rv_single, $rv_user->id && (int) $rv_single->user_id === (int) $rv_user->id);
		}

		return;
	}


	/**
	 * Modal window placement: the list is not included in the page, it is loaded when the counter button is clicked
	 * (Inline placement: the list is included only when it is requested by the item template via display_reviews)
	 */

	$rv_need_list = $rv_list_only || $reviews_placement !== 1;

	if ($rv_need_list)
	{
		// Offset of the first review to load (the review of the user is not counted, it is displayed separately), > 0 only for the next pages
		$rv_offset = $rv_list_only ? max(0, (int) ($rv_chunk_offset ?? 0)) : 0;

		// Filter by rating (number of stars, list-only mode), 0: all reviews
		$rv_filter = $rv_list_only ? (int) ($rv_star_filter ?? 0) : 0;
		$rv_filter = ($rv_filter >= 1 && $rv_filter <= $rv_stars) ? $rv_filter : 0;

		// Distribution of the ratings of the reviews (summary bars), and number of reviews of the list (the filtered ones, if there is a filter)
		$rv_dist           = flexicontent_html::getItemReviewDistribution($rv_item_id, $rv_stars);
		$rv_dist_total     = array_sum($rv_dist);
		$rv_filtered_total = $rv_filter ? (int) $rv_dist[$rv_filter] : $reviews_total;

		// The review of the current user is displayed first, separated from the other reviews (only in the first page)
		// With a filter it is displayed only if it has the same rating
		$rv_own_row       = flexicontent_html::getItemOwnReview($rv_item_id);
		$rv_own_match     = $rv_own_row && (!$rv_filter || flexicontent_html::reviewStarBucket($rv_own_row->average_rating, $rv_stars) === $rv_filter);
		$rv_own_review    = ($rv_offset || !$rv_own_match) ? null : $rv_own_row;
		$rv_others_total  = $rv_filtered_total - ($rv_own_match ? 1 : 0);
		$rv_other_reviews = flexicontent_html::getItemReviewsPage($rv_item_id, $rv_offset, $rv_page_size, $rv_filter, $rv_stars);
		$rv_has_more      = ($rv_offset + count($rv_other_reviews)) < $rv_others_total;

		// The box is displayed inline (page): 'All reviews' button, otherwise (modal window) the next reviews are loaded by scrolling
		$rv_box_inline = !$rv_list_only || !empty($rv_inline_box);

		// The 'useful' votes of the current user for the reviews of the page
		if ($rv_useful_mode && $rv_can_vote)
		{
			$rv_my_votes = flexicontent_html::getReviewUsefulVotes(array_map(function ($_review) { return (int) $_review->id; }, $rv_other_reviews));
		}

		// Next page (only the reviews + the loading marker, if there are more)
		if ($rv_offset)
		{
			$chunk = '';

			foreach ($rv_other_reviews as $review)
			{
				$chunk .= $rv_render($review, false);
			}

			$field->display_reviews = $chunk . ($rv_has_more
				? $rv_more_marker($rv_offset + count($rv_other_reviews))
				: ($rv_box_inline ? '' : $rv_end_marker));
			return;
		}


		/**
		 * The reviews box (content of the modal window, or the inline list)
		 */

		// Fallback: average rating of the displayed reviews
		if (!$rv_votes)
		{
			$rv_sum = 0;

			foreach (array_merge($rv_own_review ? array($rv_own_review) : array(), $rv_other_reviews) as $review)
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
			<div id="fc_content_item_id_reviews_box_' . $rv_item_id . '" class="fcvote-reviews" data-item="' . $rv_item_id . '" data-star="' . $rv_filter . '" data-inline="' . ($rv_box_inline ? 1 : 0) . '">';

		// Summary: average rating + bars with the number of reviews for each rating, clicking a bar shows only the reviews with that rating
		if ($rv_votes || $rv_dist_total)
		{
			$rv_bars = '';

			if ($rv_dist_total)
			{
				for ($rv_s = $rv_stars; $rv_s >= 1; $rv_s--)
				{
					$rv_n      = (int) $rv_dist[$rv_s];
					$rv_active = ($rv_filter === $rv_s);
					$rv_width  = $rv_n ? max(3, round($rv_n * 100 / $rv_dist_total)) : 0;

					$rv_bars .= '
						<a href="#" class="fcvote-bar-row' . ($rv_active ? ' is-active' : '') . ($rv_n ? '' : ' is-empty') . '" data-star="' . $rv_s . '"
							title="' . htmlspecialchars(\Joomla\CMS\Language\Text::sprintf('FLEXI_VOTE_REVIEW_FILTER_TIP', $rv_s, $rv_n), ENT_QUOTES, 'UTF-8') . '"
							onclick="return ' . ($rv_n || $rv_active ? 'fcvote_reviews_filter(this, ' . $rv_s . ')' : 'false') . ';">
							<span class="fcvote-bar-label">' . $rv_s . '</span>
							<span class="fcvote-bar-track"><span class="fcvote-bar-fill" style="width:' . $rv_width . '%;"></span></span>
							<span class="fcvote-bar-count">' . $rv_n . '</span>
						</a>';
				}
			}

			$reviews_text .= '
				<div class="fcvote-reviews-summary">
					<div class="fcvote-reviews-score">
						<span class="fcvote-reviews-avg">' . number_format($rv_avg_percent * $rv_stars / 100, 1) . '</span>
						' . flexicontent_html::reviewStars($rv_avg_percent, $rv_stars, '1.3em') . '
						<span class="fcvote-reviews-count">' . ($rv_votes ? $rv_votes . ' ' . \Joomla\CMS\Language\Text::_($rv_votes > 1 ? 'FLEXI_VOTES' : 'FLEXI_VOTE') . ' &middot; ' : '') . $rv_count_label . '</span>
					</div>'
					. ($rv_bars ? '
					<div class="fcvote-reviews-bars">' . $rv_bars . '
					</div>' : '') . '
				</div>';
		}

		// Filter by rating is active: what is displayed, and how to show all the reviews
		if ($rv_filter)
		{
			$reviews_text .= '
				<div class="fcvote-reviews-filter">
					<span>' . \Joomla\CMS\Language\Text::sprintf('FLEXI_VOTE_REVIEW_FILTER_ACTIVE', $rv_filter) . '</span>
					<a href="#" class="fcvote-reviews-filter-reset" onclick="return fcvote_reviews_filter(this, 0);">' . \Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_FILTER_RESET') . '</a>
				</div>';
		}

		// Button to add a review (only if the user has no review yet), displayed under the rating
		if ($rv_can_submit && !$rv_own_row)
		{
			$reviews_text .= '
				<div class="fcvote-reviews-actions">'
					. $rv_review_btn($reviews_total ? 'FLEXI_VOTE_REVIEW_THIS_ITEM' : 'FLEXI_VOTE_REVIEW_BE_FIRST', $rv_btn_primary) . '
				</div>';
		}
		elseif (!$reviews_total)
		{
			$reviews_text .= '<div class="fcvote-reviews-none">' . \Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_NONE') . '</div>';
		}

		// The review of the user: first, separated from the others, with Edit / Delete buttons
		if ($rv_own_review)
		{
			$reviews_text .= '
				<div class="fcvote-review-own">
					<div class="fcvote-review-own-label">' . \Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_YOURS') . '</div>'
					. $rv_render($rv_own_review, true) . '
				</div>';
		}

		if ($rv_filter && !$rv_own_review && !$rv_other_reviews)
		{
			$reviews_text .= '<div class="fcvote-reviews-none">' . \Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_FILTER_NONE') . '</div>';
		}

		if ($rv_own_review && $rv_other_reviews)
		{
			$reviews_text .= '<div class="fcvote-reviews-others-title">' . \Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_OTHERS') . '</div>';
		}

		foreach ($rv_other_reviews as $review)
		{
			$reviews_text .= $rv_render($review, false);
		}

		// More reviews: modal window = loaded by scrolling, inline = 'All reviews' button that opens the modal window (that loads the next reviews by scrolling)
		if ($rv_has_more)
		{
			$reviews_text .= !$rv_box_inline
				? $rv_more_marker(count($rv_other_reviews))
				: '<div class="fcvote-reviews-all" style="text-align:center;">
					<button type="button" class="fcvote-review-btn fcvote-review-btn-all" style="' . $rv_btn_all . '"
						onclick="return fcvote_reviews_modal(' . $rv_item_id . ', ' . htmlspecialchars(json_encode($rv_count_label), ENT_COMPAT, 'UTF-8') . ', ' . $rv_filter . ');">'
						. \Joomla\CMS\Language\Text::sprintf('FLEXI_VOTE_REVIEW_ALL_N', $rv_filtered_total) . '</button>
				</div>';
		}

		$reviews_text .= '
			</div>
		';

		// List-only mode: only the list (box) is needed
		if ($rv_list_only)
		{
			$field->display_reviews = $reviews_text;
			return;
		}
	}


	/**
	 * The counter / button under the stars
	 */

	if ($reviews_total)
	{
		// Reviews exist: small counter, Inline: scrolls to the reviews list (if the list is not in the page it is loaded in a modal window), Modal window: loads the list in a modal window
		$rv_trigger_js = ($reviews_placement === 1 ? 'fcvote_reviews_modal' : 'fcvote_reviews_goto')
			. '(' . $rv_item_id . ', ' . htmlspecialchars(json_encode($rv_count_label), ENT_COMPAT, 'UTF-8') . '); return false;';

		$field->{$prop} .= '
			<div class="fcvote-reviews-trigger-row">
				<button type="button" class="fcvote-reviews-trigger" style="' . $rv_btn_small . '"
					onclick="' . $rv_trigger_js . '">
					' . $rv_icon_bubble . '
					<span>' . $rv_count_label . '</span>
				</button>
			</div>';
	}
	else
	{
		// No reviews: the button starts the review form (or a plain text if the user cannot submit reviews)
		$field->{$prop} .= '
			<div class="fcvote-reviews-trigger-row">'
				. ($rv_can_submit
					? $rv_review_btn('FLEXI_VOTE_REVIEW_BE_FIRST', $rv_btn_small)
					: '<span class="fcvote-reviews-none">' . \Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_NONE') . '</span>') . '
			</div>';
	}


	// Container of the stars and the reviews button: it is replaced by fcvote.js when the reviews change (without reloading the page)
	if (!$rv_list_only)
	{
		$field->{$prop} = '<div class="fcvote-field" data-item="' . $rv_item_id . '">' . $field->{$prop} . '</div>';
	}


	/**
	 * The reviews list, for the item template: $item->fields['voting']->display_reviews
	 * It is used by the 'Inline' placement, the stars and the reviews button stay in the normal display of the field
	 * (nothing to display if there are no reviews and the user cannot add one)
	 */

	if ($rv_need_list && ($reviews_total || $rv_can_submit))
	{
		$field->display_reviews = '
			<div id="fcvote-reviews-' . $rv_item_id . '" class="fcvote-reviews-card">
				<h3 class="fcvote-reviews-heading">' . \Joomla\CMS\Language\Text::_('FLEXI_REVIEWS') . '</h3>
				' . $reviews_text . '
			</div>';
	}
}

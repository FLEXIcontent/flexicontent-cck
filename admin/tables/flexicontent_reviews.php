<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
require_once('flexicontent_basetable.php');

class flexicontent_reviews extends flexicontent_basetable
{
	// Non-table (private) properties
	var $_record_name = 'review';
	var $_title = 'title';
	var $_alias = null;
	var $_force_ascii_alias = false;
	var $_allow_underscore = false;

	public function __construct(& $db)
	{
		$this->_records_dbtbl  = 'flexicontent_' . $this->_record_name . 's';
		$this->_NAME = strtoupper($this->_record_name);

		parent::__construct('#__' . $this->_records_dbtbl, 'id', $db);

		// Set the alias since the column is called state
		$this->setColumnAlias('published', 'state');
	}


	// overloaded check function
	public function check()
	{
		// Set submit date if it is empty
		if ( !$this->submit_date )
		{
			$datenow = \Joomla\CMS\Factory::getDate();
			$this->submit_date = $datenow->toSql();
		}

		// If edited by review submitter then also set the update_date
		if ( $this->id && $this->user_id == \Joomla\CMS\Factory::getApplication()->getIdentity()->id )
		{
			$datenow = \Joomla\CMS\Factory::getDate();
			$this->update_date = $datenow->toSql();
		}

		return true;
	}


	/**
	 * Check if the rating of a review was added to the votes of the reviewed item
	 *
	 * The rating of a review is also a vote of the item, except if the same visitor had already voted when the review
	 * was saved: the review keeps its rating, but the rating is not a vote, and it must not be removed from the votes
	 * of the item later. This is recorded in the 'attribs' column as {"vote_counted":0}. A review without this value
	 * (every review saved before this was recorded) has its rating counted
	 *
	 * @param   mixed  $attribs  The 'attribs' of the review: JSON string, null, or an object (e.g. Registry)
	 *
	 * @return  boolean
	 */
	public static function isVoteCounted($attribs)
	{
		if (is_object($attribs))
		{
			$value = method_exists($attribs, 'get') ? $attribs->get('vote_counted', 1) : (isset($attribs->vote_counted) ? $attribs->vote_counted : 1);
		}
		else
		{
			$data  = is_string($attribs) && $attribs !== '' ? json_decode($attribs, true) : null;
			$value = is_array($data) && isset($data['vote_counted']) ? $data['vote_counted'] : 1;
		}

		return (int) $value !== 0;
	}


	/**
	 * Record if the rating of a review was added to the votes of the reviewed item, see isVoteCounted()
	 *
	 * @param   object   $review   The review record (table object), it is not stored here
	 * @param   boolean  $counted  False if the rating is not a vote of the item
	 *
	 * @return  void
	 */
	public static function setVoteCounted($review, $counted)
	{
		if (!property_exists($review, 'attribs') || self::isVoteCounted($review->attribs) === (bool) $counted)
		{
			return;
		}

		$data = is_string($review->attribs) ? json_decode($review->attribs, true) : null;
		$data = is_array($data) ? $data : array();

		if ($counted)
		{
			unset($data['vote_counted']);
		}
		else
		{
			$data['vote_counted'] = 0;
		}

		// An empty string (not null) so that the column is also updated when the last value is removed
		$review->attribs = $data ? json_encode($data) : '';
	}


	/**
	 * Check if a review with composite voting is known to have a vote in every voting characteristic of the reviewed item
	 *
	 * This is recorded in the 'attribs' column as {"chars_counted":1} when the review is saved. Older versions stored only
	 * the vote of the last characteristic (and the average) if the item already had votes, so a counted review without this
	 * value may have no vote in some characteristics, see FlexicontentControllerReviews::_applyCompositeChange()
	 *
	 * @param   mixed  $attribs  The 'attribs' of the review: JSON string, null, or an object (e.g. Registry)
	 *
	 * @return  boolean
	 */
	public static function hasCharVotes($attribs)
	{
		if (is_object($attribs))
		{
			$value = method_exists($attribs, 'get') ? $attribs->get('chars_counted', 0) : (isset($attribs->chars_counted) ? $attribs->chars_counted : 0);
		}
		else
		{
			$data  = is_string($attribs) && $attribs !== '' ? json_decode($attribs, true) : null;
			$value = is_array($data) && isset($data['chars_counted']) ? $data['chars_counted'] : 0;
		}

		return (int) $value === 1;
	}


	/**
	 * Record if a review with composite voting has a vote in every voting characteristic, see hasCharVotes()
	 *
	 * @param   object   $review  The review record (table object), it is not stored here
	 * @param   boolean  $stored  True if the votes of all characteristics were stored
	 *
	 * @return  void
	 */
	public static function setCharVotes($review, $stored)
	{
		if (!property_exists($review, 'attribs') || self::hasCharVotes($review->attribs) === (bool) $stored)
		{
			return;
		}

		$data = is_string($review->attribs) ? json_decode($review->attribs, true) : null;
		$data = is_array($data) ? $data : array();

		if ($stored)
		{
			$data['chars_counted'] = 1;
		}
		else
		{
			unset($data['chars_counted']);
		}

		$review->attribs = $data ? json_encode($data) : '';
	}
}

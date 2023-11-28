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

jimport('legacy.model.legacy');

/**
 * FLEXIcontent Component stats Model
 *
 */
class FlexicontentModelStats extends JModelLegacy
{
	/**
	 * Rating resolution
	 *
	 * @var object
	 */
	var $_rating_resolution = null;

	/**
	 * Constructor
	 *
	 * @since 3.3.0
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		$this->getRatingResolution();
	}

	/**
	* Method to get general stats
	*
	* @access public
	* @return array
	*/
	function getGeneralstats()
	{
		$_items = array();

		// Get total nr of items
		$query = 'SELECT count(i.id)'
			. ' FROM #__content as i'
			. ' JOIN #__categories as c ON i.catid = c.id'
			. ' WHERE c.extension="'.FLEXI_CAT_EXTENSION.'" AND c.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND c.rgt <= ' . $this->_db->Quote(FLEXI_RGT_CATEGORY)
			;
		$this->_db->SetQuery($query);
		$_items[] = $this->_db->loadResult();


		// Get nr of all categories
		$query = 'SELECT count(id)'
			. ' FROM #__categories as c'
			. ' WHERE c.extension="'.FLEXI_CAT_EXTENSION.'" AND lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND rgt <= ' . $this->_db->Quote(FLEXI_RGT_CATEGORY)
			;
		$this->_db->SetQuery($query);
		$_items[] = $this->_db->loadResult();


		// Get nr of all tags
		$query = 'SELECT count(id) FROM #__flexicontent_tags';
		$this->_db->SetQuery($query);
		$_items[] = $this->_db->loadResult();


		// Get nr of all files
		$query = 'SELECT count(id) FROM #__flexicontent_files';
		$this->_db->SetQuery($query);
		$_items[] = $this->_db->loadResult();

		// Get nr of type
		$query = 'SELECT count(id) FROM #__flexicontent_types';
		$this->_db->SetQuery($query);
		$_items[] = $this->_db->loadResult();

		// Get nr of authors
		$query = 'SELECT COUNT(id) FROM #__users';
		$this->_db->SetQuery($query);
		$_items[] = $this->_db->loadResult();


		// Get nr of templates
		$query = 'SELECT count(DISTINCT template) FROM #__flexicontent_templates';
		$this->_db->SetQuery($query);
		$_items[] = $this->_db->loadResult();

		// Get nr of fields
		$query = 'SELECT count(id) FROM #__flexicontent_fields';
		$this->_db->SetQuery($query);
		$_items[] = $this->_db->loadResult();

		return $_items;
	}


	/**
	* Method to get items counts per month
	*
	* @access public
	* @return array
	*/
	function getItemsgraph(){
		$_items = array();

		// Get total nr of items
		$query = 'SELECT
				  COUNT(*) AS item_count,
				  DATE_FORMAT(i.created, "%Y-%m") as year_month_num,
				  DATE_FORMAT(i.created, "%Y-%b") as year_month_text,
				  DATE_FORMAT(i.created, "%m") as month_num,
				  DATE_FORMAT(i.created, "%b") as month_txt

				FROM #__content AS i
				WHERE i.created > DATE_SUB(NOW(), INTERVAL 120 MONTH)
				GROUP BY DATE_FORMAT(i.created, "%Y-%m")

				';
		$this->_db->SetQuery($query);
		$_items[] = $this->_db->loadObjectList();

		return $_items;
	}


	/**
	 * Method to get popular data
	 *
	 * @access public
	 * @return array
	 */
	function getPopular()
	{
		$_df = 100 / $this->_rating_resolution;
		$query = 'SELECT (cr.rating_sum / cr.rating_count ) * '.$_df.' AS votes, i.title, i.id, i.hits'
			. ' FROM #__content AS i'
			. ' JOIN #__categories as c ON i.catid=c.id'
			. ' LEFT JOIN #__content_rating AS cr ON cr.content_id = i.id'
			. ' WHERE c.extension="'.FLEXI_CAT_EXTENSION.'" AND c.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND c.rgt<=' . $this->_db->Quote(FLEXI_RGT_CATEGORY)
			. ' ORDER BY i.hits DESC'
			. ' LIMIT 5'
			;

		$this->_db->SetQuery($query);
		$hits = $this->_db->loadObjectList();

		return $hits;
	}

	/**
	 * Method to get popular data
	 *
	 * @access public
	 * @return array
	 */
	function getUnpopular()
	{
		$_df = 100 / $this->_rating_resolution;
		$query = 'SELECT (cr.rating_sum / cr.rating_count ) * '.$_df.' AS votes, i.title, i.id, i.hits'
			. ' FROM #__content AS i'
			. ' JOIN #__categories as c ON i.catid=c.id'
			. ' LEFT JOIN #__content_rating AS cr ON cr.content_id = i.id'
			. ' WHERE c.extension="'.FLEXI_CAT_EXTENSION.'" AND c.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND c.rgt<=' . $this->_db->Quote(FLEXI_RGT_CATEGORY)
			. ' ORDER BY i.hits ASC'
			. ' LIMIT 5'
			;

		$this->_db->SetQuery($query);
		$hits = $this->_db->loadObjectList();

		return $hits;
	}

	/**
	 * Method to get rating data
	 *
	 * @access public
	 * @return array
	 */
	function getRating()
	{
		$_df = 100 / $this->_rating_resolution;
		$query = 'SELECT (cr.rating_sum / cr.rating_count ) * '.$_df.' AS votes, i.title, i.id'
			. ' FROM #__content AS i'
			. ' JOIN #__categories as c ON i.catid=c.id'
			. ' INNER JOIN #__content_rating AS cr ON cr.content_id = i.id'
			. ' WHERE c.extension="'.FLEXI_CAT_EXTENSION.'" AND c.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND c.rgt<=' . $this->_db->Quote(FLEXI_RGT_CATEGORY)
			. ' ORDER BY votes DESC'
			. ' LIMIT 5'
			;

		$this->_db->SetQuery($query);
		$votes = $this->_db->loadObjectList();

		return $votes;
	}

	/**
	 * Method to get rating data
	 *
	 * @access public
	 * @return array
	 */
	function getWorstRating()
	{
		$_df = 100 / $this->_rating_resolution;
		$query = 'SELECT (cr.rating_sum / cr.rating_count ) * '.$_df.' AS votes, i.title, i.id'
			. ' FROM #__content AS i'
			. ' JOIN #__categories as c ON i.catid=c.id'
			. ' INNER JOIN #__content_rating AS cr ON cr.content_id = i.id'
			. ' WHERE c.extension="'.FLEXI_CAT_EXTENSION.'" AND c.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND c.rgt<=' . $this->_db->Quote(FLEXI_RGT_CATEGORY)
			. ' ORDER BY votes ASC'
			. ' LIMIT 5'
			;

		$this->_db->SetQuery($query);
		$worstvotes = $this->_db->loadObjectList();

		return $worstvotes;
	}

	/**
	 * Method to get creators data
	 *
	 * @access public
	 * @return array
	 */
	function getCreators()
	{
		$query = 'SELECT COUNT(*) AS counter, i.created_by AS id, ua.name, ua.username'
			. ' FROM #__content AS i'
			. ' JOIN #__categories as c ON i.catid=c.id'
			. ' LEFT JOIN #__users AS ua ON ua.id = i.created_by'
			. ' WHERE c.extension="'.FLEXI_CAT_EXTENSION.'" AND c.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND c.rgt<=' . $this->_db->Quote(FLEXI_RGT_CATEGORY)
			. ' GROUP BY ua.name'
			. ' ORDER BY counter DESC'
			. ' LIMIT 5'
			;

		$this->_db->SetQuery($query);
		$usercreate = $this->_db->loadObjectList();

		return $usercreate;
	}

	/**
	 * Method to get editors data
	 *
	 * @access public
	 * @return array
	 */
	function getEditors()
	{
		$query = 'SELECT COUNT(*) AS counter, i.modified_by AS id, ua.name, ua.username'
			. ' FROM #__content AS i'
			. ' JOIN #__categories as c ON i.catid=c.id'
			. ' LEFT JOIN #__users AS ua ON ua.id = i.modified_by'
			. ' WHERE c.extension="'.FLEXI_CAT_EXTENSION.'" AND i.modified_by > 0'
			. ' AND c.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND c.rgt<=' . $this->_db->Quote(FLEXI_RGT_CATEGORY)
			. ' GROUP BY ua.name'
			. ' ORDER BY counter DESC'
			. ' LIMIT 5'
			;

		$this->_db->SetQuery($query);
		$usereditor = $this->_db->loadObjectList();

		return $usereditor;
	}

	/**
	 * Method to get favourites data
	 *
	 * @access public
	 * @return array
	 */
	function getFavoured()
	{
		$query = 'SELECT i.title, i.id, COUNT(f.itemid) AS favnr'
			. ' FROM #__content AS i'
			. ' JOIN #__categories as c ON i.catid=c.id'
			. ' INNER JOIN #__flexicontent_favourites AS f ON f.itemid = i.id'
			. ' WHERE c.extension="'.FLEXI_CAT_EXTENSION.'" AND c.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND c.rgt<=' . $this->_db->Quote(FLEXI_RGT_CATEGORY)
			. ' GROUP BY f.itemid'
			. ' ORDER BY favnr DESC'
			. ' LIMIT 5'
			;

		$this->_db->SetQuery($query);
		$favnr = $this->_db->loadObjectList();

		return $favnr;
	}

	/**
	 * Method to get favourites data
	 * TODO: Clean up this mess
	 *
	 * @access public
	 * @return array
	 */
	function getStatestats()
	{
		//get states
		$query = 'SELECT state'
			. ' FROM #__content AS i'
			. ' JOIN #__categories as c ON i.catid=c.id'
			. ' WHERE c.extension="'.FLEXI_CAT_EXTENSION.'" AND c.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND c.rgt<=' . $this->_db->Quote(FLEXI_RGT_CATEGORY)
			;

		$this->_db->SetQuery($query);
		$states = $this->_db->loadObjectList();

		$total = count($states);

		//initialize vars
		$collect = array();
		$collect['published'] = 0;
		$collect['unpublished'] = 0;
		$collect['archived'] = 0;
		$collect['pending'] = 0;
		$collect['open'] = 0;
		$collect['progress'] = 0;

		//count each states
		foreach ($states AS $state)
		{
			switch ($state->state)
			{
				case 1:
					$collect['published']++;
					break;
				case 0:
					$collect['unpublished']++;
					break;
				case -1:
					$collect['archived']++;
					break;
				case -3:
					$collect['pending']++;
					break;
				case -4:
					$collect['open']++;
					break;
				case -5:
					$collect['progress']++;
					break;
			}
		}

		//get percentage and label
		$val = array();
		$lab = array();
		$i = 0;
		foreach ($collect as $key => $proz)
		{
			if ($proz == 0)
			{
				unset($collect[$key]);
				continue;
			}

			$val[] = round($proz / $total * 100);

			switch ($key)
			{
				case 'published':
					$lab[] = JText::_( 'FLEXI_PUBLISHED' ).' '.$val[$i].' %';
					break;
				case 'unpublished':
					$lab[] = JText::_( 'FLEXI_UNPUBLISHED' ).' '.$val[$i].' %';
					break;
				case 'archived':
					$lab[] = JText::_( 'FLEXI_ARCHIVED' ).' '.$val[$i].' %';
					break;
				case 'pending':
					$lab[] = JText::_( 'FLEXI_PENDING' ).' '.$val[$i].' %';
					break;
				case 'open':
					$lab[] = JText::_( 'FLEXI_TO_WRITE' ).' '.$val[$i].' %';
					break;
				case 'progress':
					$lab[] = JText::_( 'FLEXI_IN_PROGRESS' ).' '.$val[$i].' %';
					break;
			}
			$i++;
		}

		$collect['values'] = implode(',', $val);
		$collect['labels'] = implode('|', $lab);

		return $collect;
	}

	/**
	 * Method to get votes data
	 *
	 * @access public
	 * @return array
	 */
	function getVotesstats()
	{
		// Get all votes
		$query = 'SELECT cr.rating_sum, cr.rating_count, i.id'
			. ' FROM #__content AS i'
			. ' JOIN #__categories as c ON i.catid=c.id'
			. ' LEFT JOIN #__content_rating AS cr ON cr.content_id = i.id'
			. ' WHERE c.extension="'.FLEXI_CAT_EXTENSION.'" AND c.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND c.rgt<=' . $this->_db->Quote(FLEXI_RGT_CATEGORY)
			;

		$this->_db->SetQuery($query);
		$votes = $this->_db->loadObjectList();

		$total = count($votes);

		//initialize vars
		$collect = array();
		$collect['020'] = 0;
		$collect['040'] = 0;
		$collect['060'] = 0;
		$collect['080'] = 0;
		$collect['100'] = 0;
		$collect['novotes'] = 0;
		$collect['negative'] = 0;

		//count
		foreach ($votes as $vote) {

			if(!$vote->rating_sum) {
				$collect['novotes']++;
				continue;
			}

			//$percentage = round(($vote->rating_sum / $vote->rating_count) * 20);
			$percentage	= round((($vote->rating_sum / $vote->rating_count) * (100 / $this->_rating_resolution)), 2);

			if ($percentage > 0 && $percentage < 20) {
				$collect['020']++;
			} elseif($percentage >= 20 && $percentage < 40) {
				$collect['040']++;
			} elseif($percentage >= 40 && $percentage < 60) {
				$collect['060']++;
			} elseif($percentage >= 60 && $percentage < 80) {
				$collect['080']++;
			} elseif($percentage >= 80 && $percentage <= 100) {
				$collect['100']++;
			}
		}

		//get votes and label
		$val = array();
		$lab = array();
		$i = 0;
		foreach ($collect as $key => $value) {

			if ($value == 0) {
				unset($collect[$key]);
				continue;
			}
			$val[]	= $value;
			$proz	= round($value / $total * 100);

			switch ($key)
			{
				case '020':
					$lab[] = JText::_( 'FLEXI_VOTES_BEETWEEN_020' ).' '.$proz.' % ('.$val[$i].')';
					break;
				case '040':
					$lab[] = JText::_( 'FLEXI_VOTES_BEETWEEN_040' ).' '.$proz.' % ('.$val[$i].')';
					break;
				case '060':
					$lab[] = JText::_( 'FLEXI_VOTES_BEETWEEN_060' ).' '.$proz.' % ('.$val[$i].')';
					break;
				case '080':
					$lab[] = JText::_( 'FLEXI_VOTES_BEETWEEN_080' ).' '.$proz.' % ('.$val[$i].')';
					break;
				case '100':
					$lab[] = JText::_( 'FLEXI_VOTES_BEETWEEN_100' ).' '.$proz.' % ('.$val[$i].')';
					break;
				case 'novotes':
					$lab[] = JText::_( 'FLEXI_NOVOTES' ).' '.$proz.' % ('.$val[$i].')';
					break;
			}

			$i++;
		}

		$collect['values'] = implode( ',', $val );
		$collect['labels'] = implode( '|', $lab );

		return $collect;
	}

	function getRatingResolution()
	{
		if ($this->_rating_resolution)
		{
			return $this->_rating_resolution;
		}

		$this->_db->setQuery('SELECT * FROM #__flexicontent_fields WHERE field_type="voting"');
		$field = $this->_db->loadObject();
		$item = JTable::getInstance( $type = 'flexicontent_items', $prefix = '', $config = array() );
		//$item->load( $id );
		FlexicontentFields::loadFieldConfig($field, $item);

		$rating_resolution = (int)$field->parameters->get('rating_resolution', 5);
		$rating_resolution = $rating_resolution >= 5   ?  $rating_resolution  :  5;
		$rating_resolution = $rating_resolution <= 100  ?  $rating_resolution  :  100;
		$this->_rating_resolution = $rating_resolution;
	}

	/**
	 * Method to get item count of published items
	 *
	 * @access public
	 * @return array
	 */
	function getItemspublish()
	{
		$query = 'SELECT COUNT(*) AS itemspub '
			. ' FROM #__content AS i'
			. ' WHERE i.state = 1'
			;

		$this->_db->SetQuery($query);
		$itemspub = $this->_db->loadObjectList();

		return $itemspub;
	}

	/**
	 * Method to get item count of unpublished items
	 *
	 * @access public
	 * @return array
	 */
	function getItemsunpublish()
	{
		$query = 'SELECT COUNT(*) AS itemsunpub '
			. ' FROM #__content AS i'
			. ' JOIN #__categories as c ON i.catid = c.id'
			. ' WHERE i.state = 0'
			;

		$this->_db->SetQuery($query);
		$itemsunpub = $this->_db->loadObjectList();

		return $itemsunpub;
	}
	/**
	 * Method to get item count of items waiting approval
	 *
	 * @access public
	 * @return array
	 */
	function getItemswaiting()
	{
		$query = 'SELECT COUNT(*) AS itemswaiting '
			. ' FROM #__content AS i'
			. ' JOIN #__categories as c ON i.catid = c.id'
			. ' WHERE i.state = -3'
			;

		$this->_db->SetQuery($query);
		$itemswaiting = $this->_db->loadObjectList();

		return $itemswaiting;
	}

	/**
	 * Method to get item count of items in progress
	 *
	 * @access public
	 * @return array
	 */
	function getItemsprogress()
	{
		$query = 'SELECT COUNT(*) AS itemsprogress '
			. ' FROM #__content AS i'
			. ' JOIN #__categories as c ON i.catid = c.id'
			. ' WHERE i.state = -5'
			;

		$this->_db->SetQuery($query);
		$itemsprogress = $this->_db->loadObjectList();

		return $itemsprogress;
	}

	/**
	 * Method to get item count of items without Meta-Description
	 *
	 * @access public
	 * @return array
	 */
	function getItemsmetadescription()
	{
		$query = 'SELECT COUNT(*) AS itemsmetadesc '
			. ' FROM #__content AS i'
			. ' JOIN #__categories as c ON i.catid = c.id'
			. ' WHERE i.metadesc = ""'
			;

		$this->_db->SetQuery($query);
		$itemsmetadesc = $this->_db->loadObjectList();

		return $itemsmetadesc;
	}

	/**
	 * Method to get without Meta Keywords
	 *
	 * @access public
	 * @return array
	 */
	function getItemsmetakeywords()
	{
		$query = 'SELECT COUNT(i.state) AS itemsmetakey '
			. ' FROM #__content AS i'
			. ' JOIN #__categories as c ON i.catid = c.id'
			. ' WHERE i.metakey = ""'
			;

		$this->_db->SetQuery($query);
		$itemsmetakey = $this->_db->loadObjectList();

		return $itemsmetakey;
	}

}
?>

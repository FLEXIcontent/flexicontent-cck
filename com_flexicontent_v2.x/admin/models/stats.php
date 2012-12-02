<?php
/**
 * @version 1.5 stable $Id: stats.php 171 2010-03-20 00:44:02Z emmanuel.danan $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.model');

/**
 * FLEXIcontent Component stats Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelStats extends JModelLegacy
{

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();
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

		/*
		* Get total nr of items
		*/
		$query = 'SELECT count(i.id)'
			. ' FROM #__content as i'
			. ' JOIN #__categories as c ON i.catid = c.id'
			. ' WHERE c.extension="'.FLEXI_CAT_EXTENSION.'" AND c.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND c.rgt <= ' . $this->_db->Quote(FLEXI_RGT_CATEGORY)
			;

		$this->_db->SetQuery($query);
  		$_items[] = $this->_db->loadResult();
  		
  		/*
		* Get nr of all categories
		*/
		$query = 'SELECT count(id)'
			. ' FROM #__categories as c'
			. ' WHERE c.extension="'.FLEXI_CAT_EXTENSION.'" AND lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND rgt <= ' . $this->_db->Quote(FLEXI_RGT_CATEGORY)
			;

		$this->_db->SetQuery($query);
  		$_items[] = $this->_db->loadResult();
  		
  		/*
		* Get nr of all tags
		*/
		$query = 'SELECT count(id)'
			. ' FROM #__flexicontent_tags'
			;

		$this->_db->SetQuery($query);
  		$_items[] = $this->_db->loadResult();

  		/*
		* Get nr of all files
		*/
		$query = 'SELECT count(id)'
			. ' FROM #__flexicontent_files'
			;

		$this->_db->SetQuery($query);
  		$_items[] = $this->_db->loadResult();
  		
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
		$query = 'SELECT (cr.rating_sum / cr.rating_count ) * 20 AS votes, i.title, i.id, i.hits'
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
	 * Method to get rating data
	 *
	 * @access public
	 * @return array
	 */
	function getRating()
	{
		$query = 'SELECT (cr.rating_sum / cr.rating_count ) * 20 AS votes, i.title, i.id'
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
		$query = 'SELECT (cr.rating_sum / cr.rating_count ) * 20 AS votes, i.title, i.id'
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
		$query = 'SELECT COUNT(*) AS counter, i.created_by AS id, u.name, u.username'
			. ' FROM #__content AS i'
			. ' JOIN #__categories as c ON i.catid=c.id'
			. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
			. ' WHERE c.extension="'.FLEXI_CAT_EXTENSION.'" AND c.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND c.rgt<=' . $this->_db->Quote(FLEXI_RGT_CATEGORY)
			. ' GROUP BY u.name'
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
		$query = 'SELECT COUNT(*) AS counter, i.modified_by AS id, u.name, u.username'
			. ' FROM #__content AS i'
			. ' JOIN #__categories as c ON i.catid=c.id'
			. ' LEFT JOIN #__users AS u ON u.id = i.modified_by'
			. ' WHERE c.extension="'.FLEXI_CAT_EXTENSION.'" AND i.modified_by > 0'
			. ' AND c.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND c.rgt<=' . $this->_db->Quote(FLEXI_RGT_CATEGORY)
			. ' GROUP BY u.name'
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
  		foreach ($states AS $state) {
  			if ($state->state == 1) {
  				$collect['published']++;
  			} elseif($state->state == 0) {
  				$collect['unpublished']++;
  			} elseif($state->state == -1) {
  				$collect['archived']++;
  			} elseif($state->state == -3) {
  				$collect['pending']++;
  			} elseif($state->state == -4) {
  				$collect['open']++;
  			} elseif($state->state == -5) {
  				$collect['progress']++;
  			}
  		}
  		
  		//get percentage and label
  		$val = array();
  		$lab = array();
  		$i = 0;
  		foreach ($collect as $key => $proz) {
  			
  			if ($proz == 0) {
  				unset($collect[$key]);
  				continue;
  			}
  			$val[] = round($proz / $total * 100);
  			
  			if ( $key == 'published' ) {
				$lab[] = JText::_( 'FLEXI_PUBLISHED' ).' '.$val[$i].' %';
			} else if ( $key == 'unpublished' ) {
				$lab[] = JText::_( 'FLEXI_UNPUBLISHED' ).' '.$val[$i].' %';
			} else if ( $key == 'archived' ) {
				$lab[] = JText::_( 'FLEXI_ARCHIVED' ).' '.$val[$i].' %';
			} else if ( $key == 'pending' ) {
				$lab[] = JText::_( 'FLEXI_PENDING' ).' '.$val[$i].' %';
			} else if ( $key == 'open' ) {
				$lab[] = JText::_( 'FLEXI_TO_WRITE' ).' '.$val[$i].' %';
			} else if ( $key == 'progress' ) {
				$lab[] = JText::_( 'FLEXI_IN_PROGRESS' ).' '.$val[$i].' %';
			}
			$i++;
  		}
  		
  		$collect['values'] = implode( ',', $val );
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
  		/*
		* Get all votes
		*
		*/
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
  		foreach ($votes AS $vote) {
  			
  			if(!$vote->rating_sum) {
  				$collect['novotes']++;
  				continue;
  			}
  			
  			$percentage = round(($vote->rating_sum / $vote->rating_count) * 20);
  			
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
  			
  			if ( $key == '020' ) {
				$lab[] = JText::_( 'FLEXI_VOTES_BEETWEEN_020' ).' '.$proz.' % ('.$val[$i].')';
			} else if ( $key == '040' ) {
				$lab[] = JText::_( 'FLEXI_VOTES_BEETWEEN_040' ).' '.$proz.' % ('.$val[$i].')';
			} else if ( $key == '060' ) {
				$lab[] = JText::_( 'FLEXI_VOTES_BEETWEEN_060' ).' '.$proz.' % ('.$val[$i].')';
			} else if ( $key == '080' ) {
				$lab[] = JText::_( 'FLEXI_VOTES_BEETWEEN_080' ).' '.$proz.' % ('.$val[$i].')';
			} else if ( $key == '100' ) {
				$lab[] = JText::_( 'FLEXI_VOTES_BEETWEEN_100' ).' '.$proz.' % ('.$val[$i].')';
			} else if ( $key == 'novotes' ) {
				$lab[] = JText::_( 'FLEXI_NOVOTES' ).' '.$proz.' % ('.$val[$i].')';
			}
			
			$i++;
  		}
  		
  		$collect['values'] = implode( ',', $val );
  		$collect['labels'] = implode( '|', $lab );
  		  		
  		return $collect;
	}
}
?>

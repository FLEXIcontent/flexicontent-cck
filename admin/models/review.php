<?php
/**
 * @version 1.5 stable $Id: review.php 1577 2012-12-02 15:10:44Z ggppdk $
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

jimport('legacy.model.legacy');

/**
 * FLEXIcontent Component Review Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelReview extends JModelLegacy
{
	/**
	 * Review data
	 *
	 * @var object
	 */
	var $_review = null;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();

		$array = JRequest::getVar('cid',  0, '', 'array');
		$this->setId((int)$array[0]);
	}

	/**
	 * Method to set the identifier
	 *
	 * @access	public
	 * @param	int review identifier
	 */
	function setId($id)
	{
		// Set review id and wipe data
		$this->_id  = $id;
		$this->_review = null;
	}
	
	/**
	 * Overridden get method to get properties from the review
	 *
	 * @access	public
	 * @param	string	$property	The name of the property
	 * @param	mixed	$value		The value of the property to set
	 * @return 	mixed 				The value of the property
	 * @since	1.0
	 */
	function get($property, $default=null)
	{
		if ($this->_loadReview()) {
			if(isset($this->_review->$property)) {
				return $this->_review->$property;
			}
		}
		return $default;
	}

	/**
	 * Method to get review data
	 *
	 * @access	public
	 * @return	array
	 * @since	1.0
	 */
	function & getReview($name = null)
	{
		if ($this->_loadReview($name))
		{

		}
		else  $this->_initReview();

		return $this->_review;
	}


	/**
	 * Method to load review data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _loadReview($name = null)
	{
		// Lets load the review if it doesn't already exist
		if (empty($this->_review))
		{
			$name_quoted = $name && strlen($name) ? $this->_db->Quote($name) : null;
			if (!$name_quoted && !$this->_id) {
				$this->_review = false;
			} else {
				
				$query = 'SELECT *'
						. ' FROM #__flexicontent_reviews_dev'
						. ' WHERE '
						.(!$name_quoted ? ' id='.$this->_id : '')
						.($name_quoted  ? ' name='.$name_quoted : '')
						;
				$this->_db->setQuery($query);
				$this->_review = $this->_db->loadObject();
			}
			
			if ($this->_review) $this->_id = $this->_review->id;
			return (boolean) $this->_review;
		}
		return true;
	}
	
	
	/**
	 * Method to initialise the review data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _initReview()
	{
		// Lets load the review if it doesn't already exist
		if (empty($this->_review))
		{
			$review = $this->getTable('flexicontent_reviews', $_prefix='');

			$review->id         = 0;
			$review->title      = '';
			$review->published  = 1;
			$review->text       = '';

			$this->_review      = $review;
			return (boolean) $this->_review;
		}
		return true;
	}

	/**
	 * Method to checkin/unlock the review
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function checkin($pk = NULL)
	{
		if (!$pk) $pk = $this->_id;

		if ($pk)
		{
			$tbl = $this->getTable('flexicontent_reviews', $_prefix='');
			return $tbl->checkin($pk);
		}
		return false;
	}
	
	
	/**
	 * Method to checkout/lock the review
	 *
	 * @access	public
	 * @param	int	$uid	User ID of the user checking the item out
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function checkout($pk = null)   // UPDATED to match function signature of J1.6+ models
	{
		// Make sure we have a record id to checkout the record with
		if ( !$pk ) $pk = $this->_id;
		if ( !$pk ) return true;
		
		// Get current user
		$user	= JFactory::getUser();
		$uid	= $user->get('id');
		
		// Lets get table record and checkout the it
		$tbl = $this->getTable('flexicontent_reviews', $_prefix='');
		if ( $tbl->checkout($uid, $this->_id) ) return true;
		
		// Reaching this points means checkout failed
		$this->setError( FLEXI_J16GE ? $tbl->getError() : JText::_("FLEXI_ALERT_CHECKOUT_FAILED") );
		return false;
	}
	
	
	/**
	 * Tests if the review is checked out
	 *
	 * @access	public
	 * @param	int	A user id
	 * @return	boolean	True if checked out
	 * @since	1.0
	 */
	function isCheckedOut( $uid=0 )
	{
		if ($this->_loadReview())
		{
			if ($uid) {
				return ($this->_review->checked_out && $this->_review->checked_out != $uid);
			} else {
				return $this->_review->checked_out;
			}
		} elseif ($this->_id < 1) {
			return false;
		} else {
			JError::raiseWarning( 0, 'UNABLE LOAD DATA');
			return false;
		}
	}

	/**
	 * Method to store the review
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function store($data)
	{
		$review = $this->getTable('flexicontent_reviews', $_prefix='');

		// bind it to the table
		if (!$review->bind($data)) {
			$this->setError( $this->_db->getErrorMsg() );
			return false;
		}

		// Make sure the data is valid
		if (!$review->check()) {
			$this->setError($review->getError() );
			return false;
		}

		// Store it in the db
		if (!$review->store()) {
			$this->setError( $this->_db->getErrorMsg() );
			return false;
		}
		
		$this->_review	=& $review;

		return true;
	}
	
	function addreview($title)
	{	
		$obj = new stdClass();
		$obj->title = $title;
		$obj->published	= 1;
		
		if ($this->store($obj))
		{
			return true;
		}
		
		return false;
	}

}
?>
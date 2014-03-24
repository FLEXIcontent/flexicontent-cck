<?php
/**
 * @version 1.5 stable $Id: flexicontent_items_ext.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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

defined('_JEXEC') or die('Restricted access');

/**
 * FLEXIcontent table class
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class flexicontent_items_ext extends JTable{
	/** @var int Primary key */
	var $item_id					= null;
	/** @var int */
	var $type_id				= null;
	/** @var string */
	var $language				= null;
	/** @var int */
	
	// for item counting in categories
	var $cnt_state        = null;
	var $cnt_access       = null;
	var $cnt_publish_up   = null;
	var $cnt_publish_down = null;
	var $cnt_created_by   = null;
		
	var $lang_parent_id	= null;
	/** @var string */
	var $sub_items		= null;
	/** @var string */
	var $sub_categories			= null;
	/** @var string */
	var $related_items			= null;
	/** @var string */
	var $search_index				= null;

	/**
	* @param database A database connector object
	*/
	function flexicontent_items_ext(& $db) {
		parent::__construct('#__flexicontent_items_ext', 'item_id', $db);
	}
}

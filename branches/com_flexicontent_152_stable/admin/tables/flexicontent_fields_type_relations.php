<?php
/**
 * @version 1.5 stable $Id: flexicontent_fields_type_relations.php 183 2009-11-18 10:30:48Z vistamedia $
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
 * @since 1.5
 */
class flexicontent_fields_type_relations extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $field_id 				= null;
	/**
	 * Primary Key
	 * @var int
	 */
	var $type_id				= null;
	/**
	 * Ordering
	 * @var int
	 */
	var $ordering				= null;

	function flexicontent_fields_type_relations(& $db) {
		parent::__construct('#__flexicontent_fields_type_relations', 'field_id', $db);
	}
	
	// overloaded check function
	function check()
	{
		return;
	}
}
?>
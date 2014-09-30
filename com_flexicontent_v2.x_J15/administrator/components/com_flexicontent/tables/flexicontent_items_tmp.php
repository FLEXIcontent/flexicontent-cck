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
class flexicontent_items_tmp extends JTable{
	/* content properties
	(a) CORE content relations that maybe used for ordering too
	(b) other simple attributes used for ordering
	*/
	/* PRIMARY key */
	var $id = null;
	
	/**
	* @param database A database connector object
	*/
	function flexicontent_items_tmp(& $db) {
		$tbl_name = '#__flexicontent_items_tmp';
		
		// Get columns
		$tbls = array($tbl_name);
		if (!FLEXI_J16GE) $tbl_fields = $db->getTableFields($tbls);
		else foreach ($tbls as $tbl) $tbl_fields[$tbl] = $db->getTableColumns($tbl);
		
		$tbl_fields = array_keys($tbl_fields[$tbl_name]);
		foreach ($tbl_fields as $tbl_field) $this->$tbl_field = null;
		
		parent::__construct($tbl_name, 'id', $db);
	}
}

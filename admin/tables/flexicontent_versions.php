<?php
/**
 * @version 1.0 $Id: flexicontent_versions.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
class flexicontent_versions extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $id 				= null;
	/** @var int */
	var $item_id			= null;
	/** @var int */
	var $version_id			= null;
	/** @var string */
	var $comment	 		= null;
	/** @var date */
	var $created			= null;
	/** @var int */
	var $created_by 		= null;
	/** @var int */
	var $state				= null;

	function flexicontent_versions(& $db) {
		parent::__construct('#__flexicontent_versions', 'id', $db);
	}
	
	// overloaded check function
	function check()
	{
		return true;
	}
}
?>
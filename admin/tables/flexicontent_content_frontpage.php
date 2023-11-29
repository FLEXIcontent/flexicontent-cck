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

// no direct access
defined('_JEXEC') or die;

/**
 * @package		Joomla.Administrator
 * @subpackage	com_content
 */
class flexicontent_content_frontpage extends JTable
{
	/**
	 * @param	JDatabase	A database connector object
	 */
	function __construct(&$db)
	{
		parent::__construct('#__content_frontpage', 'content_id', $db);
	}
}

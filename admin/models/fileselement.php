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
defined('_JEXEC') or die('Restricted access');

// Register autoloader for parent model
JLoader::register('FlexicontentModelFilemanager', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'filemanager.php');

/**
 * FLEXIcontent Component Fileselement Model
 *
 */
class FlexicontentModelFileselement extends FlexicontentModelFilemanager
{
	public function __construct($config = array())
	{
		parent::__construct($config);
		$this->sess_assignments = false;
	}
}

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
use Joomla\Utilities\ArrayHelper;

JLoader::register('ParentClassItem', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'parentclassitem.php');

/**
 * FLEXIcontent Component Itemcompare Model
 *
 */
class FlexicontentModelItemcompare extends ParentClassItem
{
	/**
	 * Item data
	 *
	 * @var object
	 */
	var $_item = null;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);
	}
}


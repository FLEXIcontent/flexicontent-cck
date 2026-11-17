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

// Lazy class loading (was JLoader::register): loaded only if/when actually used.
spl_autoload_register(static function ($class) {
	$map = array(
		'FlexicontentController' => JPATH_BASE . DS . 'components' . DS . 'com_flexicontent' . DS . 'controller.php',
	);
	if (isset($map[$class]) && is_file($map[$class])) require_once $map[$class];
}, true, true);

/**
 * FLEXIcontent Admin list Trait (Controller Legacy methods)
 *
 * @since 3.3.0
 */
trait FCControllerTraitBase
{
	/**
	 * Logic to publish records
	 *
	 * @return  void
	 *
	 * @since   3.3.0
	 */
	public function publish()
	{
		$this->changestate(1);
	}

	/**
	 * Logic to unpublish records
	 *
	 * @return  void
	 *
	 * @since   3.3.0
	 */
	public function unpublish()
	{
		$this->changestate(0);
	}


	/**
	 * Logic to archive records
	 *
	 * @return  void
	 *
	 * @since   3.3.0
	 */
	public function archive()
	{
		$this->changestate(2);
	}


	/**
	 * Logic to trash records
	 *
	 * @return  void
	 *
	 * @since   3.3.0
	 */
	public function trash()
	{
		$this->changestate(-2);
	}
}

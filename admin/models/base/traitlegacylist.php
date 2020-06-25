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
use Joomla\CMS\Table\Table;

/**
 * FLEXIcontent List of Records Trait (legacy methods)
 *
 */
trait FCModelTraitLegacyList
{
	/**
	 * (Legacy) Method to get records data
	 *
	 * @return array
	 *
	 * @since	3.3.0
	 */
	public function getData()
	{
		return $this->getItems();
	}


	/**
	 * Method to check if given records can not be changed to the given state due to assignments or due to permissions
	 *
	 * @param   array       $cid          array of record ids to check
	 * @param   array       $cid_noauth   (variable by reference), pass authorizing -ignored- IDs and return an array of non-authorized record ids
	 * @param   array       $cid_wassocs  (variable by reference), pass assignments -ignored- IDs and return an array of 'locked' record ids
	 * @param   int         $tostate      New state value
	 *
	 * @return  boolean   True when at least 1 changeable record found
	 *
	 * @since   3.3.0
	 */
	public function canchangestate($cid, & $cid_noauth = null, & $cid_wassocs = null, $tostate = 0)
	{
		return $this->canDoAction($cid, $cid_noauth, $cid_wassocs, $action = $tostate);
	}


	/**
	 * Method to check if given records can not be deleted due to assignments or due to permissions
	 *
	 * @param   array       $cid          array of record ids to check
	 * @param   array       $cid_noauth   (variable by reference), pass authorizing -ignored- IDs and return an array of non-authorized record ids
	 * @param   array       $cid_wassocs  (variable by reference), pass assignments -ignored- IDs and return an array of 'locked' record ids
	 *
	 * @return	boolean	  True when at least 1 deleteable record found
	 *
	 * @since   3.3.0
	 */
	public function candelete($cid, & $cid_noauth = null, & $cid_wassocs = null)
	{
		return $this->canDoAction($cid, $cid_noauth, $cid_wassocs, $action = 'core.delete');
	}


	/**
	 * Method to check if given records can not be unpublished due to assignments or due to permissions
	 *
	 * @param		array			$cid          array of record ids to check
	 * @param		array			$cid_noauth   (variable by reference), pass authorizing -ignored- IDs and return an array of non-authorized record ids
	 * @param		array			$cid_wassocs  (variable by reference), pass assignments -ignored- IDs and return an array of 'locked' record ids
	 *
	 * @return	boolean	  True when at least 1 publishable record found
	 *
	 * @since   3.3.0
	 */
	public function canunpublish($cid, & $cid_noauth = null, & $cid_wassocs = null)
	{
		return $this->canDoAction($cid, $cid_noauth, $cid_wassocs, $action = 0);
	}
}

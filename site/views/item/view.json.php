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
defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

jimport('legacy.view.legacy');

/**
 * HTML View class for the Item View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewItem extends JViewLegacy
{
	var $_type = '';
	var $_name = FLEXI_ITEMVIEW;

	/**
	 * Creates the item page
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		$app      = JFactory::getApplication();
		$jinput   = $app->input;

		//initialize variables
		$user  = JFactory::getUser();
		$aid   = JAccess::getAuthorisedViewLevels($user->id);


		// ***
		// *** Get item, model and create form (that loads item data)
		// ***

		// Get model
		$model  = $this->getModel();

		// Indicate to model that current view IS item form
		$model->isForm = false;

		// Get current category id
		$cid = $model->_cid ? $model->_cid : $model->get('catid');

		/**
		 * Decide version to load,
		 * Note: A non zero version forces a login, version meaning is
		 *   0 : is currently active version,
		 *  -1: preview latest version (this is also the default for edit form),
		 *  -2: preview currently active (version 0)
		 * > 0: is a specific version
		 * Preview flag forces a specific item version if version is not set
		 */
		$version = $jinput->getInt('version', 0);
		/**
		 * Preview versioned data FLAG ... if preview is set and version is not then
		 *  1: load version -1 (version latest)
		 *  2: load version -2 (version currently active (0))
		 */
		$preview = $jinput->getInt('preview', 0);
		$version = $preview && !$version ? - $preview : $version;

		// Allow ilayout from HTTP request, this will be checked during loading item parameters
		$model->setItemLayout('__request__');

		// Indicate to model to merge menu parameters if menu matches
		$model->mergeMenuParams = true;


		/**
		 * Try to load existing item, an 404 error will be raised if item is not found. Also value 2 for check_view_access
		 * indicates to raise 404 error for ZERO primary key too, instead of creating and returning a new item object
		 * Get the item, loading item data and doing parameters merging
		 */

		$item = $model->getItem(null, $_check_view_access=2, $_no_cache=$version, $_force_version=$version);  // ZERO version means unversioned data

		// Get item parameters as VIEW's parameters (item parameters are merged parameters in order: layout(template-manager)/component/category/type/item/menu/access)
		$params = $item->parameters;

		// Get field values
		$items = array($item);
		$_vars = null;
		FlexicontentFields::getItemFields($items, $_vars, $_view=FLEXI_ITEMVIEW, $aid);

		// Zero unneeded search index text
		foreach ($items as $item) $item->search_index = '';
		$item->search_index = '';

		// Use &test=1 to test / preview item data
		/*if ($jinput->getInt('test', 0) === 1)
		{
			die('<pre>' . print_r($item, true) . '');
		}*/

		// Output item in JSON FORMAT
		echo @json_encode($item);
	}
}
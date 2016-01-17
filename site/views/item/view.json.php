<?php
/**
 * @version 1.5 stable $Id$
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

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('legacy.view.legacy');

/**
 * HTML View class for the Item View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewItem  extends JViewLegacy
{
	var $_type = '';
	var $_name = FLEXI_ITEMVIEW;

	/**
	 * Creates the page's display
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		$user     = JFactory::getUser();
		$aid      = JAccess::getAuthorisedViewLevels($user->id);
		
		// Get model
		$model  = $this->getModel();
		// Indicate to model that current view IS item form
		$model->isForm = false;
		
		$cid    = $model->_cid ? $model->_cid : $model->get('catid');  // Get current category id
		
		// Decide version to load
		$version = JRequest::getVar( 'version', 0, 'request', 'int' );   // Load specific item version (non-zero), 0 version: is unversioned data, -1 version: is latest version (=default for edit form)
		$preview = JRequest::getVar( 'preview', 0, 'request', 'int' );   // Preview versioned data FLAG ... if previewing and version is not set then ... we load version -1 (=latest version)
		$version = $preview && !$version ? -1 : $version;
		
		// Allow iLayout from HTTP request, this will be checked during loading item parameters
		$model->setItemLayout('__request__');
		// Indicate to model to merge menu parameters if menu matches
		$model->mergeMenuParams = true;
		
		
		// Try to load existing item, an 404 error will be raised if item is not found. Also value 2 for check_view_access
		// indicates to raise 404 error for ZERO primary key too, instead of creating and returning a new item object
		// Get the item, loading item data and doing parameters merging
		$item = $model->getItem(null, $check_view_access=2, $no_cache=($version||$preview), $force_version=($version||$preview ? $version : 0));  // ZERO means unversioned data
		
		// Get item parameters as VIEW's parameters (item parameters are merged parameters in order: component/category/layout/type/item/menu/access)
		$params = & $item->parameters;
		
		// Get field values
		$items = array($item);
		$_vars = null;
		FlexicontentFields::getItemFields($items, $_vars, $_view=FLEXI_ITEMVIEW, $aid);
		
		// Zero unneeded search index text
		foreach ($items as $item) $item->search_index = '';
		$item->search_index = '';
		
		// Use &test=1 to test / preview item data
		if (JRequest::getCmd('test', 0))
		{
			echo "<pre>"; print_r($item); exit;
		}
		
		// Output item in JSON FORMAT
		echo @json_encode( $item );
	}
}
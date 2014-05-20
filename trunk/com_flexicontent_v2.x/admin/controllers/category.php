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

defined('_JEXEC') or die;

jimport('joomla.application.component.controllerform');

/**
 * The Menu Item Controller
 *
 * @package		Joomla.Administrator
 * @subpackage	com_categories
 * @since		1.6
 */
class FlexicontentControllerCategory extends JControllerForm
{
	/**
	 * @var		string	The extension for which the categories apply.
	 * @since	1.6
	 */
	protected $extension;

	/**
	 * Constructor.
	 *
	 * @param	array An optional associative array of configuration settings.
	 * @see		JController
	 * @since	1.6
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		// Guess the JText message prefix. Defaults to the option.
		if (empty($this->extension)) {
			$this->extension = JRequest::getCmd('extension', 'com_flexicontent');
		}
		
		$this->registerTask( 'apply'  ,		'save' );
		$this->registerTask( 'save2new',	'save' );
		$this->registerTask( 'save2copy', 'save' );
	}

	function add() {
		parent::add();
	}
	
	function edit($key = NULL, $urlVar = NULL) {
		$cid = JRequest::getVar('cid', array(), 'get', 'array');
		if (count($cid)) JRequest::setVar('cid', $cid, 'post', 'array');
		parent::edit();
	}

	function save($key = NULL, $urlVar = NULL) {
		parent::save();
	}
	
	function cancel($key = NULL) {
		parent::cancel();
	}
	
	/**
	 * Method to check if you can add a new record.
	 *
	 * Extended classes can override this if necessary.
	 *
	 * @param	array	An array of input data.
	 *
	 * @return	boolean
	 * @since	1.6
	 */
	protected function allowAdd($data = array())
	{
		$user = JFactory::getUser();
		if ( !FLEXI_J16GE || $user->authorise('core.create', $this->extension) ) {
			$cancreate_cat = true;
		} else {
			$usercats = FlexicontentHelperPerm::getAllowedCats($user, $actions_allowed = array('core.create')
				, $require_all = true, $check_published = true, $specific_catids = false, $find_first = true
			);
			$cancreate_cat = count($usercats) > 0;
		}
		
		return $cancreate_cat;
	}

	/**
	 * Method to check if you can edit a record.
	 *
	 * Extended classes can override this if necessary.
	 *
	 * @param	array	An array of input data.
	 * @param	string	The name of the key for the primary key.
	 *
	 * @return	boolean
	 * @since	1.6
	 */
	protected function allowEdit($data = array(), $key = 'parent_id')
	{
		// Initialise variables.
		$recordId	= (int) isset($data[$key]) ? $data[$key] : 0;
		$user		= JFactory::getUser();
		$userId		= $user->get('id');

		// Check general edit permission first.
		if ($user->authorise('core.edit', $this->extension)) {
			return true;
		}

		// Check specific edit permission.
		if ($user->authorise('core.edit', 'com_content.category.'.$recordId)) {
			return true;
		}

		// Fallback on edit.own.
		// First test if the permission is available.
		if ($user->authorise('core.edit.own', 'com_content.category.'.$recordId) || $user->authorise('core.edit.own', $this->extension)) {
			// Now test the owner is the user.
			$ownerId	= (int) isset($data['created_user_id']) ? $data['created_user_id'] : 0;
			if (empty($ownerId) && $recordId) {
				// Need to do a lookup from the model.
				$record		= $this->getModel()->getItem($recordId);

				if (empty($record)) {
					return false;
				}

				$ownerId = $record->created_user_id;
			}

			// If the owner matches 'me' then do the test.
			if ($ownerId == $userId) {
				return true;
			}
		}
		return false;
	 }

	/**
	 * Method to run batch opterations.
	 *
	 * @return	void
	 */
	public function batch($model)
	{
		JRequest::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		// Set the model
		$model	= $this->getModel('Category');

		$extension = JRequest::getCmd('extension', '');
		if ($extension) {
			$extension = '&extension='.$extension;
		}

		// Preset the redirect
		$this->setRedirect('index.php?option=com_flexicontent&view=categories'.$extension);

		return parent::batch($model);
	}

	/**
	 * Gets the URL arguments to append to an item redirect.
	 *
	 * @param	int		$recordId	The primary key id for the item.
	 *
	 * @return	string	The arguments to append to the redirect URL.
	 * @since	1.6
	 */
	protected function getRedirectToItemAppend($recordId = null, $urlVar = 'id')
	{
		$append = parent::getRedirectToItemAppend($recordId);
		$append .= '&extension='.$this->extension;

		return $append;
	}

	/**
	 * Gets the URL arguments to append to a list redirect.
	 *
	 * @return	string	The arguments to append to the redirect URL.
	 * @since	1.6
	 */
	protected function getRedirectToListAppend()
	{
		$append = parent::getRedirectToListAppend();
		$append .= '&extension='.$this->extension;

		return $append;
	}
}

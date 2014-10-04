<?php
/**
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

// No direct access.
defined('_JEXEC') or die;

jimport('joomla.application.component.modeladmin');
require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_users'.DS.'models'.DS.'user.php');

/**
 * User model.
 *
 * @package     Joomla.Administrator
 * @subpackage  com_flexicontent
 * @since       1.6
 */
class FlexicontentModelUser extends UsersModelUser
{
	/**
	 * Type primary key
	 *
	 * @var int
	 */
	var $_id = null;
	
	/**
	 * Type data
	 *
	 * @var object
	 */
	var $_user = null;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();

		$array = JRequest::getVar('cid',  array(0), '', 'array');
		$array = is_array($array) ? $array : array($array);
		$id = $array[0];
		if(!$id) {
			$post = JRequest::get( 'post' );
			$data = FLEXI_J16GE ? @$post['jform'] : $post;
			$id = @$data['id'];
		}
		$this->setId((int)$id);
	}

	/**
	 * Method to set the identifier
	 *
	 * @access	public
	 * @param	int type identifier
	 */
	function setId($id)
	{
		// Set type id and wipe data
		$this->_id     = $id;
		$this->_user   = null;
	}
	

	/**
	 * Method to get the type identifier
	 *
	 * @access	public
	 */
	function getId() {
		return $this->_id;
	}

	/**
	 * Method to get the record form.
	 *
	 * @param   array    $data      An optional array of data for the form to interogate.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  mixed  A JForm object on success, false on failure
	 *
	 * @since   1.6
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// Initialise variables.
		$app = JFactory::getApplication();

		// Get the form.
		$form = $this->loadForm('com_flexicontent.user', 'user', array('control' => 'jform', 'load_data' => $loadData));

		if (empty($form))
		{
			return false;
		}

		return $form;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  mixed  The data for the form.
	 *
	 * @since   1.6
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = JFactory::getApplication()->getUserState('com_flexicontent.edit.user.data', array());

		if (empty($data))
		{
			$data = $this->getItem($this->_id);
		}

		// TODO: Maybe this can go into the parent model somehow?
		// Get the dispatcher and load the users plugins.
		$dispatcher	= JDispatcher::getInstance();
		JPluginHelper::importPlugin('user');

		// Trigger the data preparation event.
		$results = $dispatcher->trigger('onContentPrepareData', array('com_users.profile', $data));

		// Check for errors encountered while preparing the data.
		if (count($results) && in_array(false, $results, true))
		{
			$this->setError($dispatcher->getError());
		}

		return $data;
	}
}

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

// Import parent controller
jimport('legacy.controller.form');

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
		
		$this->registerTask( 'apply',        'save' );
		$this->registerTask( 'apply_ajax',   'save' );
		$this->registerTask( 'save2new',     'save' );
		$this->registerTask( 'save2copy',    'save' );
	}

	function add()
	{
		parent::add();
	}
	
	function edit($key = NULL, $urlVar = NULL)
	{
		$cid = $this->input->get->get('cid', array(), 'array');
		if (count($cid))
		{
			$this->input->post->set('cid', $cid);
		}
		parent::edit();
	}

	function save($key = NULL, $urlVar = NULL)
	{
		parent::save();
		if ( $this->input->get('fc_doajax_submit') )
		{
			JFactory::getApplication()->enqueueMessage(JText::_( 'FLEXI_ITEM_SAVED' ), 'message');
			echo flexicontent_html::get_system_messages_html();
			exit();  // Ajax submit, do not rerender the view
		}
	}
	
	function cancel($key = NULL)
	{
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
		if ( $user->authorise('core.create', $this->extension) )
		{
			return true;
		}

		$usercats = FlexicontentHelperPerm::getAllowedCats($user, $actions_allowed = array('core.create')
			, $require_all = true, $check_published = true, $specific_catids = false, $find_first = true
		);

		return count($usercats) > 0;
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
		$_extension = 'com_content';

		// Zero record (id:0), return component edit permission by calling parent controller method
		if (!$recordId)
		{
			return parent::allowEdit($data, $key);
		}

		// Check "edit" permission on record asset (explicit or inherited)
		if ($user->authorise('core.edit',  $_extension . '.category.'.$recordId))
		{
			return true;
		}

		// Check "edit own" permission on record asset (explicit or inherited)
		if ($user->authorise('core.edit.own', $_extension . '.category.'.$recordId))
		{
			// Load record
			$record = $this->getModel()->getItem($recordId);

			// Record not found
			if (empty($record))
			{
				return false;
			}

			return $record->created_user_id == $user->get('id');
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
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

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

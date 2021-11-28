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

defined('_JEXEC') or die;

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

JLoader::register('FlexicontentControllerBaseAdmin', JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'controllers' . DS . 'base' . DS . 'baseadmin.php');

// Manually import models in case used by frontend, then models will not be autoloaded correctly via getModel('name')
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'field.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'fields.php';

/**
 * FLEXIcontent Fields Controller (RAW)
 *
 * NOTE: -Only- if this controller is needed by frontend URLs, then create a derived controller in frontend 'controllers' folder
 *
 * @since 3.3
 */
class FlexicontentControllerFields extends FlexicontentControllerBaseAdmin
{
	var $records_dbtbl  = 'flexicontent_fields';
	var $records_jtable = 'flexicontent_fields';

	var $record_name = 'field';
	var $record_name_pl = 'fields';

	var $_NAME = 'FIELD';
	var $record_alias = 'name';

	var $runMode = 'standalone';

	var $exitHttpHead = null;
	var $exitMessages = array();
	var $exitLogTexts = array();
	var $exitSuccess  = true;

	/**
	 * Constructor
	 *
	 * @param   array   $config    associative array of configuration settings.
	 *
	 * @since 3.3
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		// Can manage ACL
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanFields;
	}


	/**
	 * Logic to get (e.g. via AJAX call) the field specific parameters
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function getfieldspecificproperties()
	{
		/**
		 * 1. Set view to be record name aka to 'field', if not already done in http request
		 * 2 . Force raw format, if not already done in http request
		 */
		$this->input->set('view', $this->record_name);
		$this->input->set('format', 'raw');    

		// Import field to execute its constructor, e.g. needed for loading language file etc
		JPluginHelper::importPlugin('flexicontent_fields', $this->input->get('field_type', '', 'cmd'));

		// Display the field parameters
		parent::display();
	}


	/**
	 * Logic to get (e.g. via AJAX call) the field elements of an indexed field
	 *
	 * @access public
	 * @return void
	 * @since 3.0
	 */
	function getIndexedFieldJSON()
	{
		$user   = JFactory::getUser();
		$app    = JFactory::getApplication();
		$jinput = $app->input;

		$field_id  = $jinput->get('field_id', 0, 'int');

		$is_authorised = !$field_id
			? $user->authorise('flexicontent.createfield', 'com_flexicontent')
			: $user->authorise('flexicontent.editfield', 'com_flexicontent.field.' . $field_id);

		if (!$is_authorised)
		{
			jexit(JText::_('FLEXI_ALERTNOTAUTH_TASK'));
		}

		// Get field configuration
		$_fields = FlexicontentFields::getFieldsByIds(array($field_id));
		$field = $_fields[$field_id];

		// Get field configuration
		$item = null;
		FlexicontentFields::loadFieldConfig($field, $item);

		// Get indexed element values
		$item_pros = false;
		$elements = FlexicontentFields::indexedField_getElements($field, $item, array(), $item_pros);

		// Check for error during getting indexed field elements
		if (!$elements)
		{
			$sql_mode = $field->parameters->get('sql_mode', 0);  // Must retrieve variable here, and not before retrieving elements !

			if ($sql_mode && $item_pros > 0)
			{
				$error_mssg = sprintf(JText::_('FLEXI_FIELD_ITEM_SPECIFIC_AS_FILTERABLE'), $field->label);
			}
			elseif ($sql_mode)
			{
				$error_mssg = JText::_('FLEXI_FIELD_INVALID_QUERY');
			}
			else
			{
				$error_mssg = JText::_('FLEXI_FIELD_INVALID_ELEMENTS');
			}

			jexit($error_mssg);
		}

		jexit(json_encode(array_values($elements)));
	}
}

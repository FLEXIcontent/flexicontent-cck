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

defined( '_JEXEC' ) or die( 'Restricted access' );

// Register autoloader for parent controller, in case controller is executed by another component
JLoader::register('FlexicontentController', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'controller.php');

/**
 * FLEXIcontent Component Fields Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerFields extends FlexicontentController
{
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();
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
		$this->input->set('view', 'field');    // set view to be field, if not already done in http request
		$this->input->set('format', 'raw');    // force raw format, if not already done in http request

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
		$user  = JFactory::getUser();
		$app   = JFactory::getApplication();
		$jinput = $app->input;

		$field_id  = $jinput->get('field_id', 0, 'int');

		$is_authorised = !$field_id ?
			$user->authorise('flexicontent.createfield', 'com_flexicontent') :
			$user->authorise('flexicontent.editfield', 'com_flexicontent.field.' . $field_id) ;

		if (!$is_authorised) die(JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ));

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
		if ( !$elements )
		{
			$sql_mode = $field->parameters->get( 'sql_mode', 0 );  // must retrieve variable here, and not before retrieving elements !
			if ($sql_mode && $item_pros > 0)
				$error_mssg = sprintf( JText::_('FLEXI_FIELD_ITEM_SPECIFIC_AS_FILTERABLE'), $field->label );
			else if ($sql_mode)
				$error_mssg = JText::_('FLEXI_FIELD_INVALID_QUERY');
			else
				$error_mssg = JText::_('FLEXI_FIELD_INVALID_ELEMENTS');

			echo '';
			exit;
		}
		
		echo json_encode(array_values($elements));
		exit;
	}
}

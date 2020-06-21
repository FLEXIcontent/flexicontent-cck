<?php
/**
 * @package         FLEXIcontent
 * @version         3.4
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2020, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\String\StringHelper;
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');

class plgFlexicontent_fieldsRelation_reverse extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = null;  // Field's methods allowed to be called via AJAX

	// ***
	// *** CONSTRUCTOR
	// ***

	public function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_relation', JPATH_ADMINISTRATOR);
	}



	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	public function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = $field->parameters->get('label_form') ? JText::_($field->parameters->get('label_form')) : JText::_($field->label);

		// Initialize framework objects and other variables
		$user = JFactory::getUser();

		// ***
		// *** Check that relation field to be reversed was configured
		// ***
		$reverse_field_id = (int) $field->parameters->get('reverse_field', 0);
		if ( !$reverse_field_id )
		{
			$field->html = '<div class="alert alert-warning">' . $field->label . ': ' . JText::_('FLEXI_RIFLD_NO_FIELD_SELECTED_TO_BE_REVERSED').'</div>';
			return;
		}


		// ***
		// *** Check relation field being reversed exists
		// ***
		$_fields = FlexicontentFields::getFieldsByIds(array($reverse_field_id));
		if (empty($_fields))
		{
			$field->html = '<div class="alert alert-warning">' . $field->label . ': ' . JText::sprintf('FLEXI_RIFLD_FIELD_BEING_REVERSED_NOT_FOUND', $autorelation_itemid).'</div>';
			return;
		}


		// ***
		// *** Get relation field being reversed and load its configuration
		// ***

		$reversed_field = reset($_fields);
		FlexicontentFields::loadFieldConfig($reversed_field, $item);


		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;


		/**
		 * Case of autorelated item
		 */

		$autorelation_itemid = JFactory::getApplication()->input->get('autorelation_'.$reverse_field_id, 0, 'int');

		if ( $autorelation_itemid )
		{
			$auto_relate_curritem = $reversed_field->parameters->get( 'auto_relate_curritem', 0);
			$auto_relate_menu_itemid = $reversed_field->parameters->get( 'auto_relate_menu_itemid', 0);
			$auto_relate_submit_mssg = $reversed_field->parameters->get( 'auto_relate_submit_mssg', 'FLEXI_RIFLD_SUBMITTING_CONTENT_ASSIGNED_TO');

			// Check if also configuration is proper
			if ($auto_relate_curritem && $auto_relate_menu_itemid)
			{
				$db = JFactory::getDbo();
				$db->setQuery(
					'SELECT title, id, catid, state, alias '
					. ' FROM #__content '
					. ' WHERE id ='. $autorelation_itemid
				);
				$rel_item = $db->loadObject();

				if (!$rel_item)
				{
					$field->html = '<div class="alert alert-warning">' . $field->label . ': ' . JText::sprintf('FLEXI_RIFLD_CANNOT_AUTORELATE_ITEM', $autorelation_itemid).'</div>';
					return;
				}

				$field->html = '<input id="'.$elementid.'" name="'.$fieldname.'[]" type="hidden" value="'.(int) $rel_item->id.'" />';
				$field->html .= '<div class="alert alert-success">'.JText::_($auto_relate_submit_mssg).' '.$rel_item->title.'</div>';
				return;
			}
		}


		// ***
		// *** Pass null items since the items will be retrieved from the DB
		// ***

		$_items = null;
		$options = new stdClass();
		$options->isform = 1;
		$field->html = FlexicontentFields::getItemsList($field->parameters, $_items, $field, $item, $options);
	}


	// Method to create field's HTML display for frontend views
	public function onDisplayFieldValue(&$field, $item, $values = null, $prop = 'display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = JText::_($field->label);

		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);


		/**
		 * One time initialization
		 */

		static $initialized = null;
		static $app, $document, $option, $format, $realview;

		if ($initialized === null)
		{
			$initialized = 1;

			$app       = JFactory::getApplication();
			$document  = JFactory::getDocument();
			$option    = $app->input->getCmd('option', '');
			$format    = $app->input->getCmd('format', 'html');
			$realview  = $app->input->getCmd('view', '');
		}

		// Current view variable
		$view = $app->input->getCmd('flexi_callview', ($realview ?: 'item'));
		$sfx = $view === 'item' ? '' : '_cat';

		// Check if field should be rendered according to configuration
		if (!$this->checkRenderConds($prop, $view))
		{
			return;
		}

		// Call respective method of 'relation' field, field_type is not changed since 'relation' field can handle current field type
		FLEXIUtilities::call_FC_Field_Func('relation', 'onDisplayFieldValue', array(&$field, $item, $values, $prop));
	}



	// ***
	// *** METHODS HANDLING before & after saving / deleting field events
	// ***

	// Method to handle field's values before they are saved into the DB
	public function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if(!is_array($post) && !strlen($post)) return;

		$reverse_field_id = (int) $field->parameters->get('reverse_field', 0);

		if ($reverse_field_id)
		{
			$_fields = FlexicontentFields::getFieldsByIds(array($reverse_field_id));
			if (!empty($_fields))
			{
				$reversed_field = reset($_fields);
				FlexicontentFields::loadFieldConfig($reversed_field, $item);

				$auto_relate_curritem = $reversed_field->parameters->get( 'auto_relate_curritem', 0);
				$auto_relate_menu_itemid = $reversed_field->parameters->get( 'auto_relate_menu_itemid', 0);

				// Check if also configuration is proper and value was posted
				if ($auto_relate_curritem && $auto_relate_menu_itemid)
				{
					$master_item_id = (int) reset($post);
					if ($master_item_id)
					{
						$db = JFactory::getDbo();
						$db->setQuery(
							'SELECT MAX(valueorder) '
							. ' FROM #__flexicontent_fields_item_relations '
							. ' WHERE field_id = '.$reverse_field_id.' AND item_id ='. $master_item_id
						);
						$max_valueorder = (int)$db->loadResult();

						$field->use_field_id = $reverse_field_id;
						$field->use_item_id  = $master_item_id;
						$field->use_valueorder  = $max_valueorder + 1;

						$post = array($item->id.':'.$item->catid);
					}
					else $post = array();
				}
			}
		}
	}


	// Method to take any actions/cleanups needed after field's values are saved into the DB
	public function onAfterSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}


	// Method called just before the item is deleted to remove custom item data related to the field
	public function onBeforeDeleteField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}



	// ***
	// *** CATEGORY/SEARCH FILTERING METHODS
	// ***

	// Method to display a search filter for the advanced search view
	public function onAdvSearchDisplayFilter(&$filter, $value = '', $formName = 'searchForm')
	{
		// Supports filtering, but currently does not display any filter
	}


	// Method to display a category filter for the category view
	public function onDisplayFilter(&$filter, $value = '', $formName = 'adminForm', $isSearchView = 0)
	{
		// Supports filtering, but currently does not display any filter
	}


	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for content lists e.g. category view, and not for search view
	public function getFiltered(&$filter, $value, $return_sql = true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		// Call respective method of 'relation' field, field_type is not changed since 'relation' field can handle current field type
		return FLEXIUtilities::call_FC_Field_Func('relation', 'getFiltered', array(&$filter, $value, $return_sql));
	}
}

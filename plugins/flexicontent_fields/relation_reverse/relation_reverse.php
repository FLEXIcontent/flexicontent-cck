<?php
/**
 * @version 1.0 : relation_reverse.php
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.relation_reverse
 * @copyright (C) 2011 ggppdk
 * @license GNU/GPL v2
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('cms.plugin.plugin');

class plgFlexicontent_fieldsRelation_reverse extends JPlugin
{
	// ***********
	// CONSTRUCTOR
	// ***********
	static $field_types = array('relation_reverse');
	
	function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_relation', JPATH_ADMINISTRATOR);
		JPlugin::loadLanguage('plg_flexicontent_fields_relation_reverse', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;

		// Initialize framework objects and other variables
		$user = JFactory::getUser();


		// *******************************************************
		// Check that relation field to be reversed was configured
		// *******************************************************
		
		$reversed_field_id = $field->parameters->get('reverse_field', 0) ;
		if ( !$reversed_field_id )
		{
			$field->html = '<div class="alert alert-warning">'.JText::_('FLEXI_RIFLD_NO_FIELD_SELECTED_TO_BE_REVERSED').'</div>';
			return;
		}


		// ******************************************
		// Check relation field being reversed exists
		// ******************************************
		
		$_fields = FlexicontentFields::getFieldsByIds(array($reversed_field_id));
		if (empty($_fields))
		{
			$field->html = '<div class="alert alert-warning">'.JText::sprintf('FLEXI_RIFLD_FIELD_BEING_REVERSED_NOT_FOUND', $autorelation_itemid).'</div>';
			return;
		}


		// ************************************************************
		// Get relation field being reversed and load its configuration
		// ************************************************************
		
		$reversed_field = reset($_fields);
		FlexicontentFields::loadFieldConfig($reversed_field, $item);


		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;


		// ************************
		// Case of autorelated item
		// ************************

		$autorelation_itemid = JFactory::getApplication()->input->get('autorelation_'.$reversed_field_id, 0, 'int');

		if ( $autorelation_itemid )
		{
			$auto_relate_curritem = $reversed_field->parameters->get( 'auto_relate_curritem', 0);
			$auto_relate_menu_itemid = $reversed_field->parameters->get( 'auto_relate_menu_itemid', 0);
			$auto_relate_submit_mssg = $reversed_field->parameters->get( 'auto_relate_submit_mssg', 'FLEXI_RIFLD_SUBMITTING_CONTENT_ASSIGNED_TO');

			// Check if also configuration is proper
			if ($auto_relate_curritem && $auto_relate_menu_itemid)
			{
				$db = JFactory::getDBO();
				$db->setQuery(
					'SELECT title, id, catid, state, alias '
					. ' FROM #__content '
					. ' WHERE id ='. $autorelation_itemid
				);
				$rel_item = $db->loadObject();

				if (!$rel_item)
				{
					$field->html = '<div class="alert alert-warning">'.JText::sprintf('FLEXI_RIFLD_CANNOT_AUTORELATE_ITEM', $autorelation_itemid).'</div>';
					return;
				}

				$field->html = '<input id="'.$elementid.'" name="'.$fieldname.'[]" type="hidden" value="'.$rel_item->id.'" />';
				$field->html .= '<div class="alert alert-success">'.JText::_($auto_relate_submit_mssg).' '.$rel_item->title.'</div>';
				return;
			}
		}


		// *************************************************************
		// Pass null items since the items will be retrieved from the DB
		// *************************************************************

		$_items = null;
		$field->html = FlexicontentFields::getItemsList($field->parameters, $_items, $isform=1, $reversed_field_id, $field, $item);
	}


	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		$field->{$prop} = '';
		$values = $values ? $values : $field->value;

		if ($field->field_type == 'relation_reverse')
		{
			$reversed_field_id = $field->parameters->get('reverse_field', 0) ;

			// Check that relation field to be reversed was configured
			if ( !$reversed_field_id )
			{
				$field->{$prop} = '<div class="alert alert-warning">'.JText::_('FLEXI_RIFLD_NO_FIELD_SELECTED_TO_BE_REVERSED').'</div>';
				return;
			}

			// Always ignore passed items, the DB query will determine the items
			$_itemids_catids = null;
		}
		else  // $field->field_type == 'relation')
		{
			// Compatibility with old values, we no longer serialize all values to one, this way the field can be reversed !!!
			$values = ( $field_data = @unserialize($values) ) ? $field_data : $field->value;
			// No related items, just return empty display
			if ( !$values || !count($values) ) return;
			
			$_itemids_catids = array();
			foreach($values as $i => $val)
			{
				list ($itemid,$catid) = explode(":", $val);
				$_itemids_catids[$itemid] = new stdClass();
				$_itemids_catids[$itemid]->itemid = $itemid;
				$_itemids_catids[$itemid]->catid = $catid;
				$_itemids_catids[$itemid]->value  = $val;
			}
		}

		$field->{$prop} = FlexicontentFields::getItemsList($field->parameters, $_itemids_catids, $isform=0, @ $reversed_field_id, $field, $item);
	}
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if(!is_array($post) && !strlen($post)) return;

		$reversed_field_id = $field->parameters->get('reverse_field', 0) ;
		
		if ($reversed_field_id)
		{
			$_fields = FlexicontentFields::getFieldsByIds(array($reversed_field_id));
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
						$db = JFactory::getDBO();
						$db->setQuery(
							'SELECT MAX(valueorder) '
							. ' FROM #__flexicontent_fields_item_relations '
							. ' WHERE field_id = '.$reversed_field_id.' AND item_id ='. $master_item_id
						);
						$max_valueorder = (int)$db->loadResult();

						$field->use_field_id = $reversed_field_id;
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
	function onAfterSaveField( &$field, &$post, &$file, &$item ) {
	}
	
	
	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
	}
	
}
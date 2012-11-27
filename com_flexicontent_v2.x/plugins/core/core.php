<?php
/**
 * @version 1.0 $Id: core.php 1146 2012-02-22 06:52:39Z enjoyman@gmail.com $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.textarea
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

//jimport('joomla.plugin.plugin');
jimport('joomla.event.plugin');

class plgFlexicontent_fieldsCore extends JPlugin
{
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsCore( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_core', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayCoreFieldValue( &$field, $item, &$params, $tags=null, $categories=null, $favourites=null, $favoured=null, $vote=null, $values=null, $prop='display' )
	{
		// this function is a mess and need complete refactoring
		// execute the code only if the field type match the plugin type
		$view = JRequest::setVar('view', JRequest::getVar('view', FLEXI_ITEMVIEW));
		
		$values = $values ? $values : $field->value;

		if($field->iscore != 1) return;
		
		$pretext			= $field->parameters->get( 'pretext', '' ) ;
		$posttext			= $field->parameters->get( 'posttext', '' ) ;
		$separatorf			= $field->parameters->get( 'separatorf', 1 ) ;
		$dateformat			= $field->parameters->get( 'date_format', '' ) ;
		$customdate			= $field->parameters->get( 'custom_date', '' ) ;		
						
		if($pretext) $pretext = $pretext . ' ';

		if($posttext) $posttext = ' ' . $posttext . ' ';

		switch($separatorf)
		{
			case 0:
			$separatorf = ' ';
			break;

			case 1:
			$separatorf = '<br />';
			break;

			case 2:
			$separatorf = ' | ';
			break;

			case 3:
			$separatorf = ', ';
			break;

			default:
			$separatorf = ' ';
			break;
		}
			
		$field->value = array();
		switch ($field->field_type)
		{
			case 'created': // created
				$field->value[] = $item->created;
				$dateformat = $dateformat ? $dateformat : $customdate;
				$field->display = $pretext.JHTML::_( 'date', $item->created, JText::_($dateformat) ).$posttext;
				break;
			
			case 'createdby': // created by
				$field->value[] = $item->created_by;
				$field->display = $pretext.(($field->parameters->get('name_username', 1) == 2) ? $item->cuname : $item->creator).$posttext;
				break;

			case 'modified': // modified
				$field->value[] = $item->modified;
				$dateformat = $dateformat ? $dateformat : $customdate;
				$field->display = $pretext.JHTML::_( 'date', $item->modified, JText::_($dateformat) ).$posttext;
				break;
			
			case 'modifiedby': // modified by
				$field->value[] = $item->modified_by;
				$field->display = $pretext.(($field->parameters->get('name_username', 1) == 2) ? $item->muname : $item->modifier).$posttext;
				break;

			case 'title': // title
				$field->value[] = $item->title;
				$field->display = $pretext.$item->title.$posttext;
				break;

			case 'hits': // hits
				$field->value[] = $item->hits;
				$field->display = $pretext.$item->hits.$posttext;
				break;

			case 'type': // document type
				$field->value[] = $item->type_id;
				$field->display = $pretext.JText::_($item->typename).$posttext;
				break;

			case 'version': // version
				$field->value[] = $item->version;
				$field->display = $pretext.$item->version.$posttext;
				break;

			case 'state': // state
				$field->value[] = $item->state;
				$field->display = flexicontent_html::stateicon( $item->state, $field->parameters );
				break;

			case 'voting': // voting button
				$field->value[] = 'button'; // dummy value to force display
				$field->display = flexicontent_html::ItemVote( $field, 'all', $vote );
				break;

			case 'favourites': // favourites button
				$field->value[] = 'button'; // dummy value to force display
				$favs = flexicontent_html::favoured_userlist( $field, $item, $favourites);
				$field->display = '
				<span class="fav-block">
					'.flexicontent_html::favicon( $field, $favoured, $item ).'
					<span id="fcfav-reponse_'.$field->item_id.'" class="fcfav-reponse">
						<small>'.$favs.'</small>
					</span>
				</span>
					';
				break;

			case 'categories': // assigned categories
				$field->display = '';
				if ($categories) :
					// Get categories that should be excluded from linking
					global $globalnoroute;
					if ( !is_array($globalnoroute) ) $globalnoroute = array();
					
					// Create list of category links, excluding the "noroute" categories
					$field->display = array();
					foreach ($categories as $category) {
						if (!in_array($category->id, @$globalnoroute)) :
							$field->display[]  = '<a class="fc_categories link_' . $field->name . '" href="' . JRoute::_(FlexicontentHelperRoute::getCategoryRoute($category->slug)) . '">' . $category->title . '</a>';
							$field->value[] = $category->title;
						endif;
					}
					$field->display = implode($separatorf, $field->display);
				endif;
				break;

			case 'tags': // assigned tags
				$field->display = '';
				if ($tags) :
					// Create list of tag links
					$field->display = array();
					foreach ($tags as $tag) :
						$field->value[] = $tag->name; 
						$field->display[]  = '<a class="fc_tags link_' . $field->name . '" href="' . JRoute::_(FlexicontentHelperRoute::getTagRoute($tag->slug)) . '">' . $tag->name . '</a>';
					endforeach;
					$field->display = implode($separatorf, $field->display);
				endif;
				break;
			
			case 'maintext': // main text
			
				// Special display variables
				if ($prop != 'display')
				{
					switch ($prop) {
						case 'display_if': $field->{$prop} = $item->introtext . chr(13).chr(13) . $item->fulltext;  break;
						case 'display_i' : $field->{$prop} = $item->introtext;  break;
						case 'display_f' : $field->{$prop} = $item->fulltext;   break;
					}
				}
				
				// Check for no fulltext present and force using introtext
				else if ( !$item->fulltext )
				{
					$field->display = $item->introtext;
				}
				
				// Multi-item views: category/tags/favourites/module etc, only show introtext, but we have added 'force_full' item parameter
				// to allow showing the fulltext too. This parameter can be inherited by category/menu parameters or be set inside template files
				else if ($view != FLEXI_ITEMVIEW)
				{	
					if ( $item->parameters->get('force_full', 0) )
					{
						$field->display = $item->introtext . chr(13).chr(13) . $item->fulltext;
					} else {
						$field->{$prop} = $item->introtext;
					}
				}
					
				// ITEM view only shows fulltext, introtext is shown only if 'show_intro' item parameter is set
				else
				{
					if ( $item->parameters->get('show_intro', 1) )
					{
						$field->display = $item->introtext . chr(13).chr(13) . $item->fulltext;
					} else {
						$field->display = $item->fulltext;
					}
				}
				
				// Get ogp configuration
				$useogp     = $field->parameters->get('useogp', 0);
				$ogpinview  = $field->parameters->get('ogpinview', array());
				$ogpinview  = FLEXIUtilities::paramToArray($ogpinview);
				$ogpmaxlen  = $field->parameters->get('ogpmaxlen', 300);
				
				if ($useogp && $field->{$prop}) {
					if ( in_array($view, $ogpinview) ) {
						$content_val = flexicontent_html::striptagsandcut($field->display, $ogpmaxlen);
						JFactory::getDocument()->addCustomTag('<meta property="og:description" content="'.$content_val.'" />');
					}
				}
				
				break;
		}
	}
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if($field->iscore != 1) return;
		if(!is_array($post) && !strlen($post)) return;
	}
	
	
	// Method to take any actions/cleanups needed after field's values are saved into the DB
	function onAfterSaveField( &$field, &$post, &$file, &$item ) {
	}
	
	
	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
	}
	
	
	
	// *********************************
	// CATEGORY/SEARCH FILTERING METHODS
	// *********************************
	
	// Method to display a search filter for the advanced search view
	function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if($filter->iscore != 1) return;
		
		if ( !in_array( $filter->field_type, array('createdby', 'modifiedby', 'type', 'state', 'categories', 'tags') ) ) {
			$filter->html	= 'Field type: '.$filter->field_type.' can not be used as search filter';
			return;
		}
		
		plgFlexicontent_fieldsCore::onDisplayFilter($filter, $value, $formName);
	}
	
	
	// Method to display a category filter for the category view
	function onDisplayFilter(&$filter, $value='', $formName='adminForm')
	{
		if($filter->iscore != 1) return; // performance check
		
		if ( !in_array( $filter->field_type, array('createdby', 'modifiedby', 'type', 'state', 'categories', 'tags') ) ) {
			$filter->html	= 'Field type: '.$filter->field_type.' can not be used as category filter';
			return;
		}
		
		$db =& JFactory::getDBO();
		$formfieldname = 'filter_'.$filter->id;
		
		switch ($filter->field_type)
		{
			case 'createdby': // Created by
				$label_filter 		= $filter->parameters->get( 'display_label_filter', 2 ) ;
				if ($label_filter == 2) 
					$text_select = $filter->label; 
				else 
					$text_select = JText::_('FLEXI_ALL');
				
				$query 	= ' SELECT DISTINCT i.created_by AS value, u.name AS text'
						. ' FROM #__content AS i'
						. ' LEFT JOIN #__users AS u'
						. ' ON i.created_by = u.id'
						. ' WHERE i.created_by <> 0'
						. ' ORDER BY u.name ASC'
						;
				$db->setQuery($query);
				$lists = $db->loadObjectList();
				
				$options = array(); 
				$options[] = JHTML::_('select.option', '', '-'.$text_select.'-');
				foreach ($lists as $list) {
					$options[] = JHTML::_('select.option', $list->value, $list->text); 
					}			
				
				if ($label_filter == 1) $filter->html  .= $filter->label.': ';	
				
				$filter->html	.= JHTML::_('select.genericlist', $options, $formfieldname, ' class="fc_field_filter" onchange="document.getElementById(\''.$formName.'\').submit();"', 'value', 'text', $value);
			break;

			case 'modifiedby': // Modified by
				$label_filter 		= $filter->parameters->get( 'display_label_filter', 2 ) ;
				if ($label_filter == 2) 
					$text_select = $filter->label; 
				else 
					$text_select = JText::_('FLEXI_ALL');
				
				$query 	= ' SELECT DISTINCT i.modified_by AS value, u.name AS text'
					. ' FROM #__content AS i'
					. ' LEFT JOIN #__users AS u'
					. ' ON i.modified_by = u.id'
					. ' WHERE i.modified_by <> 0'
					. ' ORDER BY u.name ASC'
					;
				$db->setQuery($query);
				$lists = $db->loadObjectList();
				
				$options = array(); 
				$options[] = JHTML::_('select.option', '', '-'.$text_select.'-');
				foreach ($lists as $list) {
					$options[] = JHTML::_('select.option', $list->value, $list->text); 
					}			
				
				if ($label_filter == 1) $filter->html  .= $filter->label.': ';	
				
				$filter->html	.= JHTML::_('select.genericlist', $options, $formfieldname, ' class="fc_field_filter" onchange="document.getElementById(\''.$formName.'\').submit();"', 'value', 'text', $value);
			break;

			case 'type': // Type
				$label_filter 		= $filter->parameters->get( 'display_label_filter', 2 ) ;
				if ($label_filter == 2) 
					$text_select = $filter->label; 
				else 
					$text_select = JText::_('FLEXI_ALL');
				
				$query 	= ' SELECT id AS value, name AS text'
						. ' FROM #__flexicontent_types'
						. ' ORDER BY name ASC'
						;
				$db->setQuery($query);
				$lists = $db->loadObjectList();
				
				$options = array(); 
				$options[] = JHTML::_('select.option', '', '-'.$text_select.'-');
				foreach ($lists as $list) {
					$options[] = JHTML::_('select.option', $list->value, $list->text); 
					}			
				
				if ($label_filter == 1) $filter->html  .= $filter->label.': ';	
				
				$filter->html	.= JHTML::_('select.genericlist', $options, $formfieldname, ' class="fc_field_filter" onchange="document.getElementById(\''.$formName.'\').submit();"', 'value', 'text', $value);
			break;

			case 'state': // State
				$label_filter 		= $filter->parameters->get( 'display_label_filter', 2 ) ;
				if ($label_filter == 2) 
					$text_select = $filter->label; 
				else 
					$text_select = JText::_('FLEXI_ALL');
				
				$options = array(); 
				$options[] = JHTML::_('select.option', '', '-'.$text_select.'-');
				$options[] = JHTML::_('select.option',  'P', JText::_( 'FLEXI_PUBLISHED' ) );
				$options[] = JHTML::_('select.option',  'U', JText::_( 'FLEXI_UNPUBLISHED' ) );
				$options[] = JHTML::_('select.option',  'PE', JText::_( 'FLEXI_PENDING' ) );
				$options[] = JHTML::_('select.option',  'OQ', JText::_( 'FLEXI_TO_WRITE' ) );
				$options[] = JHTML::_('select.option',  'IP', JText::_( 'FLEXI_IN_PROGRESS' ) );
				
				if ($label_filter == 1) $filter->html  .= $filter->label.': ';	
				
				$filter->html	.= JHTML::_('select.genericlist', $options, $formfieldname, ' class="fc_field_filter" onchange="document.getElementById(\''.$formName.'\').submit();"', 'value', 'text', $value);
			break;

			case 'categories': // Categories
				$label_filter 		= $filter->parameters->get( 'display_label_filter', 2 ) ;
				$rootcatid = $filter->parameters->get( 'rootcatid', '' ) ;
				if ($label_filter == 2) 
					$text_select = $filter->label; 
				else 
					$text_select = JText::_('FLEXI_ALL');

				global $globalcats;

				$options = array(); 
				$options[] = JHTML::_('select.option', '', '-'.$text_select.'-');
				if($rootcatid) {
					$options[] = JHTML::_('select.option', $globalcats[$rootcatid]->id, $globalcats[$rootcatid]->treename);
					foreach ($globalcats[$rootcatid]->childrenarray as $k=>$list) {
						$options[] = JHTML::_('select.option', $list->id, $list->treename); 
					}
				}else{
					foreach ($globalcats as $k=>$list) {
						$options[] = JHTML::_('select.option', $list->id, $list->treename); 
					}
				}

				if ($label_filter == 1) $filter->html  .= $filter->label.': ';	
				
				$filter->html	.= JHTML::_('select.genericlist', $options, $formfieldname, ' class="fc_field_filter" onchange="document.getElementById(\''.$formName.'\').submit();"', 'value', 'text', $value);
				$filter->html	= str_replace('&lt;sup&gt;|_&lt;/sup&gt;', '\'-', $filter->html);
			break;

			case 'tags': // Tags
				$label_filter 		= $filter->parameters->get( 'display_label_filter', 2 ) ;
				if ($label_filter == 2) 
					$text_select = $filter->label; 
				else 
					$text_select = JText::_('FLEXI_ALL');
				
				$query 	= ' SELECT id AS value, name AS text'
						. ' FROM #__flexicontent_tags'
						. ' ORDER BY name ASC'
						;
				$db->setQuery($query);
				$lists = $db->loadObjectList();
				
				$options = array(); 
				$options[] = JHTML::_('select.option', '', '-'.$text_select.'-');
				foreach ($lists as $list) {
					$options[] = JHTML::_('select.option', $list->value, $list->text); 
				}			
				
				if ($label_filter == 1) $filter->html  .= $filter->label.': ';	
				
				$filter->html	.= JHTML::_('select.genericlist', $options, $formfieldname, ' class="fc_field_filter" onchange="document.getElementById(\''.$formName.'\').submit();"', 'value', 'text', $value);
			break;
		}
	}
	
	
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !$field->iscore ) return;
		if ( !$field->isadvsearch ) return;
		
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func='strip_tags');
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !$field->iscore ) return;
		if ( !$field->issearch ) return;
		
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func='strip_tags');
		return true;
	}
	
	
	// Method to get ALL items that have matching search values for the current field id
	function onFLEXIAdvSearch(&$field)
	{
		if($field->iscore != 1) return;
		
		FlexicontentFields::onFLEXIAdvSearch($field);
	}
}

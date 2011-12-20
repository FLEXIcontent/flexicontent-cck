<?php
/**
 * @version 1.0 $Id: core.php 1050 2011-12-12 02:02:58Z ggppdk $
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
	function plgFlexicontent_fieldsCore( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_core', JPATH_ADMINISTRATOR);
	}

	function onAdvSearchDisplayField(&$field, &$item) {
		if($field->iscore != 1) return;
		$mainframe = &JFactory::getApplication();
		$cparams = & $mainframe->getParams('com_flexicontent');
		
		$filter_types = $cparams->get('filter_types', 'createdby,modifiedby,type,state,tags,checkbox,checkboximage,radio,radioimage,select,selectmultiple');
		$filter_types = explode(',', $filter_types);
		if ( in_array ($field->name, $filter_types) ) {
			$value = JRequest::get($field->name, '');
			plgFlexicontent_fieldsCore::onDisplayFilter($field, $value, $field->name);
		} else {
			$value = JRequest::get($field->name, '');
			$field->html	= '<input name="'.$field->name.'[]" id="'.$field->name.'" class="inputbox" type="text" size="30" value="'.$value.'" />';
		}
	}
	
	function onDisplayCoreFieldValue( &$field, $item, &$params, $tags=null, $categories=null, $favourites=null, $favoured=null, $vote=null, $values=null, $prop='display' )
	{
		// this function is a mess and need complete refactoring
		// execute the code only if the field type match the plugin type
		$view = JRequest::setVar('view', JRequest::getVar('view', 'item'));
		
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
			$field->display = flexicontent_html::ItemVote( $field, 'main', $vote );
			break;

			case 'favourites': // favourites button
			$field->value[] = 'button'; // dummy value to force display
			$favs = flexicontent_html::favoured_userlist( $field, $item, $favourites);
			$field->display = '
			<span class="fav-block">
				'.flexicontent_html::favicon( $field, $favoured ).'
				<span id="fcfav-reponse_'.$field->item_id.'" class="fcfav-reponse">
					<small>'.$favs.'</small>
				</span>
			</span>
				';
			break;

			case 'categories': // assigned categories
			global $globalnoroute;
			if ( !is_array($globalnoroute) ) $globalnoroute = array();
			$display = '';
			if ($categories) :
				$field->display = array();
				foreach ($categories as $category) {
					if (!in_array($category->id, @$globalnoroute)) :
						$field->display[]  = '<a class="fc_categories link_' . $field->name . '" href="' . JRoute::_(FlexicontentHelperRoute::getCategoryRoute($category->slug)) . '">' . $category->title . '</a>';
						$field->value[] = $category->title;
					endif;
				}
				if (isset($field->display)) :
					$field->display = implode($separatorf, $field->display);
				else :
					$field->value[] = '';
					$field->display = '';
				endif;
			else :
				$field->value[] = '';
				$field->display = '';
			endif;
			break;

			case 'tags': // assigned tags
			$display = '';
			if ($tags) {
				$field->display = array();
				foreach ($tags as $tag) {
					$field->value[] = $tag->name; 
					$field->display[]  = '<a class="fc_tags link_' . $field->name . '" href="' . JRoute::_(FlexicontentHelperRoute::getTagRoute($tag->slug)) . '">' . $tag->name . '</a>';
					}
				$field->display = implode($separatorf, $field->display);
			} else {
				//$field->value[] = '';
				$field->display = '';
			}
			break;
			
			case 'maintext': // main text
			if ($view == 'category') {
				$field->{$prop} = $item->introtext;
				break;
			}

			// manage the don't show introtext parameter
			if (!$item->parameters->get('show_intro', 1) && $item->fulltext && ($prop == 'display')) {
				$field->{$prop} = $item->fulltext;
				break;
			}

			$text = $item->text ? $item->text : '';

			// Search for the {readmore} tag and split the text up accordingly.
			$text = str_replace('<br>', '<br />', $text);

			$pattern = '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
			$tagPos	= preg_match($pattern, $text);

			if ($tagPos != 0)	{
				$text = preg_replace($pattern, '',$text);
			}
			
			$field->{$prop} = $text;
			break;
		}
	}


	function onBeforeSaveField( $field, &$post, &$file )
	{
		if($field->iscore != 1) return;
		if(!$post) return;
		
		switch ($field->field_type)
		{
			case 'title': // title
				if ($field->issearch) {
					$field->search = $post . ' | ';
				} else {
					$field->search = '';
				}
			break;

			case 'maintext': // maintext
				if ($field->issearch) {
					$field->search = flexicontent_html::striptagsandcut($post) . ' | ';
				} else {
					$field->search = '';
				}
			break;
		}

		if($field->isadvsearch && JRequest::getVar('vstate', 0)==2) {
			plgFlexicontent_fieldsCore::onIndexAdvSearch($field, $post);
		}
	}
	
	function onIndexAdvSearch(&$field, $post) {
		// execute the code only if the field type match the plugin type
		if($field->iscore != 1) return;
		
		$db = &JFactory::getDBO();
		$post = is_array($post)?$post:array($post);
		$query = "DELETE FROM #__flexicontent_advsearch_index WHERE field_id='{$field->id}' AND item_id='{$field->item_id}' AND extratable='{$field->field_type}';";
		$db->setQuery($query);
		$db->query();
		$i = 0;
		foreach($post as $v) {
			$v = is_array($v) ? serialize($v) : $v;
			$query = "INSERT INTO #__flexicontent_advsearch_index VALUES('{$field->id}','{$field->item_id}','{$field->field_type}','{$i}', ".$db->Quote(strip_tags($v)).");";
			$db->setQuery($query);
			$db->query();
			$i++;
		}
		return true;
	}
	
	function onFLEXIAdvSearch(&$field, $fieldsearch) {
		if($field->iscore != 1) return; // performance check
		$db = &JFactory::getDBO();
		
		switch ($field->field_type)
		{
			case 'tags': // Tags
				$query 	= ' SELECT * '
						. ' FROM #__flexicontent_tags'
						. ' ORDER BY name ASC'
						;
				$db->setQuery($query);
				$tag_data = $db->loadObjectList('id');
				break;
			default:
				break;
		}
					
		$resultfields = array();
		foreach($fieldsearch as $fsearch) {
			$query = "SELECT ai.search_index, ai.item_id FROM #__flexicontent_advsearch_index as ai"
				." WHERE ai.field_id='{$field->id}' AND ai.extratable='{$field->field_type}' AND ai.search_index like '%{$fsearch}%';";
			$db->setQuery($query);
			//echo $query;
			$objs = $db->loadObjectList();
			if ($objs===false) continue;
			//echo "<pre>"; print_r($objs);echo "</pre>"; 
			$objs = is_array($objs)?$objs:array($objs);
			foreach($objs as $o) {
				$obj = new stdClass;
				$obj->item_id = $o->item_id;
				$obj->label = $field->label;
				/*if ($field->field_type=='tags' && isset($tag_data[$fsearch]->name) ) {
					//echo $fsearch. " ";
					$obj->value = $tag_data[$fsearch]->name;
				} else*/
					$obj->value = $fsearch;
				$resultfields[] = $obj;
			}
		}
		$field->results = $resultfields;
		//return $resultfields;
	}
		
	
	function onDisplayFilter(&$filter, $value='', $formfieldname='')
	{
		if($filter->iscore != 1) return; // performance check
		
		$db =& JFactory::getDBO();
		
		$formfieldname = ($formfieldname!='') ? $formfieldname : ('filter_'.$filter->id) ;
		
		switch ($filter->field_type)
		{
			case 'createdby': // Created by
				$label_filter 		= $filter->parameters->get( 'display_label_filter', 2 ) ;
				if ($label_filter == 2) 
					$text_select = $filter->label; 
				else 
					$text_select = JText::_('All');
				
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
				
				$filter->html	.= JHTML::_('select.genericlist', $options, $formfieldname, 'onchange="document.getElementById(\'adminForm\').submit();"', 'value', 'text', $value);
			break;

			case 'modifiedby': // Modified by
				$label_filter 		= $filter->parameters->get( 'display_label_filter', 2 ) ;
				if ($label_filter == 2) 
					$text_select = $filter->label; 
				else 
					$text_select = JText::_('All');
				
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
				
				$filter->html	.= JHTML::_('select.genericlist', $options, $formfieldname, 'onchange="document.getElementById(\'adminForm\').submit();"', 'value', 'text', $value);
			break;

			case 'type': // Type
				$label_filter 		= $filter->parameters->get( 'display_label_filter', 2 ) ;
				if ($label_filter == 2) 
					$text_select = $filter->label; 
				else 
					$text_select = JText::_('All');
				
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
				
				$filter->html	.= JHTML::_('select.genericlist', $options, $formfieldname, 'onchange="document.getElementById(\'adminForm\').submit();"', 'value', 'text', $value);
			break;

			case 'state': // State
				$label_filter 		= $filter->parameters->get( 'display_label_filter', 2 ) ;
				if ($label_filter == 2) 
					$text_select = $filter->label; 
				else 
					$text_select = JText::_('All');
				
				$options = array(); 
				$options[] = JHTML::_('select.option', '', '-'.$text_select.'-');
				$options[] = JHTML::_('select.option',  'P', JText::_( 'FLEXI_PUBLISHED' ) );
				$options[] = JHTML::_('select.option',  'U', JText::_( 'FLEXI_UNPUBLISHED' ) );
				$options[] = JHTML::_('select.option',  'PE', JText::_( 'FLEXI_PENDING' ) );
				$options[] = JHTML::_('select.option',  'OQ', JText::_( 'FLEXI_TO_WRITE' ) );
				$options[] = JHTML::_('select.option',  'IP', JText::_( 'FLEXI_IN_PROGRESS' ) );
				
				if ($label_filter == 1) $filter->html  .= $filter->label.': ';	
				
				$filter->html	.= JHTML::_('select.genericlist', $options, $formfieldname, 'onchange="document.getElementById(\'adminForm\').submit();"', 'value', 'text', $value);
			break;

			case 'tags': // Tags
				$label_filter 		= $filter->parameters->get( 'display_label_filter', 2 ) ;
				if ($label_filter == 2) 
					$text_select = $filter->label; 
				else 
					$text_select = JText::_('All');
				
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
				
				$filter->html	.= JHTML::_('select.genericlist', $options, $formfieldname, 'onchange="document.getElementById(\'adminForm\').submit();"', 'value', 'text', $value);
			break;
		}
	}
}

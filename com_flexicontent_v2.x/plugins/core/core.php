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
		
		// Prefix - Suffix - Separator parameters, replacing other field values if found
		$remove_space = $field->parameters->get( 'remove_space', 0 ) ;
		$pretext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'pretext', '' ), 'pretext' );
		$posttext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'posttext', '' ), 'posttext' );
		$separatorf	= $field->parameters->get( 'separatorf', 3 ) ;       // used by some fields
		$opentag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'opentag', '' ), 'opentag' );     // used by some fields
		$closetag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'closetag', '' ), 'closetag' );   // used by some fields
		
		if($pretext)  { $pretext  = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) { $posttext = $remove_space ? $posttext : ' ' . $posttext; }
		
		// some parameter shortcuts
		$dateformat			= $field->parameters->get( 'date_format', '' ) ;
		$customdate			= $field->parameters->get( 'custom_date', '' ) ;		
		
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

			case 4:
			$separatorf = $closetag . $opentag;
			break;

			case 5:
			$separatorf = '';
			break;

			default:
			$separatorf = '&nbsp;';
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
				$field->display = $pretext.flexicontent_html::stateicon( $item->state, $field->parameters ).$posttext;
				break;

			case 'voting': // voting button
				$field->value[] = 'button'; // dummy value to force display
				$field->display = $pretext.flexicontent_html::ItemVote( $field, 'all', $vote ).$posttext;
				break;

			case 'favourites': // favourites button
				$field->value[] = 'button'; // dummy value to force display
				$favs = flexicontent_html::favoured_userlist( $field, $item, $favourites);
				$field->display = $pretext.'
				<span class="fav-block">
					'.flexicontent_html::favicon( $field, $favoured, $item ).'
					<span id="fcfav-reponse_'.$field->item_id.'" class="fcfav-reponse">
						<small>'.$favs.'</small>
					</span>
				</span>
					'.$posttext;
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
							$cat_link = JRoute::_(FlexicontentHelperRoute::getCategoryRoute($category->slug));
							$display = '<a class="fc_categories link_' . $field->name . '" href="' . $cat_link . '">' . $category->title . '</a>';
							$field->display[] = $pretext. $display .$posttext;
							$field->value[] = $category->title;
						endif;
					}
					$field->display = implode($separatorf, $field->display);
					$field->display = $opentag . $field->display . $closetag;
				endif;
				break;

			case 'tags': // assigned tags
				$field->display = '';
				if ($tags) :
					// Create list of tag links
					$field->display = array();
					foreach ($tags as $tag) :
						$tag_link = JRoute::_(FlexicontentHelperRoute::getTagRoute($tag->slug));
						$display = '<a class="fc_tags link_' . $field->name . '" href="' . $tag_link . '">' . $tag->name . '</a>';
						$field->display[] = $pretext. $display .$posttext;
						$field->value[] = $tag->name; 
					endforeach;
					$field->display = implode($separatorf, $field->display);
					$field->display = $opentag . $field->display . $closetag;
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
						$field->display = $item->introtext;
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
		
		if ($filter->field_type == 'maintext' || $filter->field_type == 'title') {
			$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		}
		
		$indexed_elements = in_array($filter->field_type, array('tags', 'createdby', 'modifiedby', 'created', 'modified', 'type'));
		
		if ($filter->field_type == 'categories') {
			plgFlexicontent_fieldsCore::onDisplayFilter($filter, $value, $formName);
		} else {
			FlexicontentFields::createFilter($filter, $value, $formName, $indexed_elements);
		}
	}
	
	
	// Method to display a category filter for the category view
	function onDisplayFilter(&$filter, $value='', $formName='adminForm')
	{
		if($filter->iscore != 1) return; // performance check
		
		$db = JFactory::getDBO();
		$formfieldname = 'filter_'.$filter->id;
		
		$display_filter_as = $filter->parameters->get( 'display_filter_as', 0 );  // Filter Type of Display
		$filter_as_range = in_array($display_filter_as, array(2,3,)) ;
		
		// Create first prompt option of drop-down select
		$label_filter = $filter->parameters->get( 'display_label_filter', 2 ) ;
		$first_option_txt = $label_filter==2 ? $filter->label : JText::_('FLEXI_ALL');
		
		// Prepend Field's Label to filter HTML
		$filter->html = $label_filter==1 ? $filter->label.': ' : '';
		
		switch ($filter->field_type)
		{
			case 'title':
				$filter->html	.='<input name="filter_'.$filter->id.'" class="fc_field_filter" type="text" size="20" value="'.$value.'" />';
			break;
			
			case 'createdby':     // Authors
				// WARNING: we can not use column alias in from, join, where, group by, can use in having (mysql) and in order by
				// partial SQL clauses
				$filter->filter_valuesselect = ' i.created_by AS value, u.name AS text';
				$filter->filter_valuesjoin   = ' ';  // ... a space, (indicates not needed and prevents using default)
				$filter->filter_valueswhere  = ' AND i.created_by <> 0';
				// full SQL clauses
				$filter->filter_groupby = ' GROUP BY i.created_by ';
				$filter->filter_having  = null;   // use default
				$filter->filter_orderby = ' ORDER BY text ASC ';
				
				FlexicontentFields::createFilter($filter, $value, $formName);
			break;
			
			case 'modifiedby':   // Modifiers
				// WARNING: we can not use column alias in from, join, where, group by, can use in having (mysql) and in order by
				// partial SQL clauses
				$filter->filter_valuesselect = ' i.modified_by AS value, u.name AS text';
				$filter->filter_valuesjoin   = ' ';  // ... a space, (indicates not needed and prevents using default)
				$filter->filter_usersjoinon  = ' u.id = i.modified_by';
				$filter->filter_valueswhere  = ' AND i.modified_by <> 0';
				// full SQL clauses
				$filter->filter_groupby = ' GROUP BY i.modified_by ';
				$filter->filter_having  = null;   // use default
				$filter->filter_orderby = ' ORDER BY text ASC ';
				
				FlexicontentFields::createFilter($filter, $value, $formName);
			break;
			
			case 'type':  // Document Type
				// WARNING: we can not use column alias in from, join, where, group by, can use in having (mysql) and in order by
				// partial SQL clauses
				$filter->filter_valuesselect = ' ty.id AS value, ty.name AS text';
				$filter->filter_valuesjoin   = ' ';  // ... a space, (indicates not needed and prevents using default)
				$filter->filter_valueswhere  = ' ';  // ... a space, (indicates not needed and prevents using default)
				// full SQL clauses
				$filter->filter_groupby = ' GROUP BY ty.id';
				$filter->filter_having  = null;   // use default
				$filter->filter_orderby = ' ORDER BY text ASC ';
				
				FlexicontentFields::createFilter($filter, $value, $formName);
			break;
			
			case 'state':
				$options = array(); 
				$options[] = JHTML::_('select.option', '', '-'.$first_option_txt.'-');
				$options[] = JHTML::_('select.option',  'P', JText::_( 'FLEXI_PUBLISHED' ) );
				$options[] = JHTML::_('select.option',  'U', JText::_( 'FLEXI_UNPUBLISHED' ) );
				$options[] = JHTML::_('select.option',  'PE', JText::_( 'FLEXI_PENDING' ) );
				$options[] = JHTML::_('select.option',  'OQ', JText::_( 'FLEXI_TO_WRITE' ) );
				$options[] = JHTML::_('select.option',  'IP', JText::_( 'FLEXI_IN_PROGRESS' ) );
				$options[] = JHTML::_('select.option',  'A', JText::_( 'FLEXI_ARCHIVED' ) );
				//$options[] = JHTML::_('select.option',  'T', JText::_( 'FLEXI_TRASHED' ) );
			break;
			
			case 'categories':
				global $globalcats;
				$rootcatid = $filter->parameters->get( 'rootcatid', '' ) ;
				$options = array(); 
				$options[] = JHTML::_('select.option', '', '-'.$first_option_txt.'-');
				$option = JRequest::getVar('option', '');
				$view   = JRequest::getVar('view', '');
				$cid    = JRequest::getInt('cid', '');
				if ($option=='com_flexicontent' && $view=='category' && $cid) {   // Current view is category view limit to descendants
					$options[] = JHTML::_('select.option', $globalcats[$cid]->id, $globalcats[$cid]->treename);
					$cats = $globalcats[$cid]->childrenarray;
				} else if ( $rootcatid ) {     // If configured ... limit to subcategory tree of a specified category
					$options[] = JHTML::_('select.option', $globalcats[$rootcatid]->id, $globalcats[$rootcatid]->treename);
					$cats = $globalcats[$rootcatid]->childrenarray;
				} else {
					$cats = $globalcats;  // All categories by default
				}
				if (!empty($cats) ) foreach ($cats as $k => $list) $options[] = JHTML::_('select.option', $list->id, $list->treename);
			break;
			
			case 'tags':
				// WARNING: we can not use column alias in from, join, where, group by, can use in having (mysql) and in order by
				// partial SQL clauses
				$filter->filter_valuesselect = ' tags.id AS value, tags.name AS text';
				$filter->filter_valuesjoin   =
					 ' JOIN #__flexicontent_tags_item_relations AS tagsrel ON tagsrel.itemid = i.id '
					.' JOIN #__flexicontent_tags AS tags ON tags.id =  tagsrel.tid ';
				$filter->filter_valueswhere  = ' ';  // ... a space, (indicates not needed and prevents using default)
				// full SQL clauses
				$filter->filter_groupby = ' GROUP BY tags.id ';
				$filter->filter_having  = null;   // use default
				$filter->filter_orderby = ' ORDER BY text ';
				
				FlexicontentFields::createFilter($filter, $value, $formName);
			break;
			
			case 'created':  // creation dates
			case 'modified': // modification dates
				$date_filter_group = $filter->parameters->get('date_filter_group', 'month');
				if ($date_filter_group=='year') { $date_valformat='%Y'; $date_txtformat='%Y'; }
				else if ($date_filter_group=='month') { $date_valformat='%Y-%m'; $date_txtformat='%Y-%b'; }
				else { $date_valformat='%Y-%m-%d'; $date_txtformat='%Y-%b-%d'; }
				
				$valuecol = sprintf(' DATE_FORMAT(i.%s, "%s") ', $filter->field_type, $date_valformat);
				$textcol  = sprintf(' DATE_FORMAT(i.%s, "%s") ', $filter->field_type, $date_txtformat);
				
				// WARNING: we can not use column alias in from, join, where, group by, can use in having (some DB e.g. mysql) and in order by
				// partial SQL clauses
				$filter->filter_valuesselect = ' '.$valuecol.' AS value, '.$textcol.' AS text';
				$filter->filter_valuesjoin   = ' ';  // ... a space, (indicates not needed and prevents using default)
				$filter->filter_valueswhere  = ' AND i.'.$filter->field_type.' IS NOT NULL';
				// full SQL clauses
				$filter->filter_groupby = ' GROUP BY '.$valuecol;
				$filter->filter_having  = null;   // use default
				$filter->filter_orderby = ' ORDER BY '.$valuecol;
				FlexicontentFields::createFilter($filter, $value, $formName);
			break;

			default:
				$filter->html	.= 'Field type: '.$filter->field_type.' can not be used as search filter';
			break;
		}
		
		// a. If field filter has defined a custom SQL query to create filter (drop-down select) options, execute it and then create the options
		if ( !empty($query) ) {
			$db->setQuery($query);
			$lists = $db->loadObjectList();
			$options = array();
			$options[] = JHTML::_('select.option', '', '-'.$first_option_txt.'-');
			foreach ($lists as $list) $options[] = JHTML::_('select.option', $list->value, $list->text . ($count_column ? ' ('.$list->found.')' : '') );
		}
		
		// b. If field filter has defined drop-down select options the create the drop-down select form field
		if ( !empty($options) ) {
			if ($label_filter == 1) $filter->html  .= $filter->label.': ';	
			$filter->html	.= JHTML::_('select.genericlist', $options, $formfieldname,
				' class="fc_field_filter" onchange="document.getElementById(\''.$formName.'\').submit();"', 'value', 'text', $value);
		}
		
		// Special CASE 'categories' filter, replace some tags in filter HTML ...
		if ( $filter->field_type == 'categories') $filter->html = str_replace('&lt;sup&gt;|_&lt;/sup&gt;', '\'-', $filter->html);
	}
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for content lists e.g. category view, and not for search view
	function getFiltered(&$filter, $value)
	{
		if ( !$filter->iscore ) return;
		
		$isdate = in_array($filter->field_type, array('date','created','modified')) || $filter->parameters->get('isdate',0);
		if ($isdate) {
			$date_filter_group = $filter->parameters->get('date_filter_group', 'month');
			if ($date_filter_group=='year') { $date_valformat='%Y'; }
			else if ($date_filter_group=='month') { $date_valformat='%Y-%m';}
			else { $date_valformat='%Y-%m-%d'; }
			
			$filter->filter_colname    = sprintf(' DATE_FORMAT(c.%s, "%s") ', $filter->field_type, $date_valformat);
			$filter->filter_valuesjoin = ' ';   // ... a space, (indicates not needed)
			$filter->filter_valueformat = sprintf(' DATE_FORMAT("__filtervalue__", "%s") ', $date_valformat);
			return FlexicontentFields::getFiltered($filter, $value, $return_sql=true);
		} else {
			return array(0);
		}
	}	
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$field, $value)
	{
		if($field->iscore != 1) return;
		
		if ($field->field_type == 'maintext' || $field->field_type == 'title') {
			$field->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		}
		
		return FlexicontentFields::getFilteredSearch($field, $value, $return_sql=true);
	}	
	
	
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !$field->iscore ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		
		$values = $this->_prepareForSearchIndexing($field, $post, $for_advsearch=1);
		$filter_func = $field->field_type == 'maintext' ? 'strip_tags' : null;
		
		FlexicontentFields::onIndexAdvSearch($field, $values, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func);
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !$field->iscore ) return;
		if ( !$field->issearch ) return;
		
		$values = $this->_prepareForSearchIndexing($field, $post, $for_advsearch=0);
		$filter_func = $field->field_type == 'maintext' ? 'strip_tags' : null;
		
		FlexicontentFields::onIndexSearch($field, $values, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func);
		return true;
	}
	
	
	// Method to prepare for indexing, either preparing SQL query (if post is null) or formating/preparing given $post data for usage bu index
	function _prepareForSearchIndexing(&$field, &$post, $for_advsearch=0)
	{
		static $nullDate = null;
		
		if ($post!==null && isset($post[0])) {
			$db = JFactory::getDBO();
			$values = array();
			if ($field->field_type=='type') {
				$textcol = 't.name';
				$query 	= ' SELECT t.id AS value_id, '.$textcol.' AS value FROM #__flexicontent_types AS t WHERE t.id<>0 AND t.id = '.(int)$post[0];
				
			} else if ($field->field_type=='categories') {
				$query 	= ' SELECT c.id AS value_id, c.title AS value FROM #__categories AS c WHERE c.id<>0 AND c.id IN ('.implode(",",$post).')';
				
			} else if ($field->field_type=='tags') {
				$query 	= ' SELECT t.id AS value_id, t.name AS value FROM #__flexicontent_tags AS t WHERE t.id<>0 AND t.id IN ('.implode(",",$post).')';
				
			} else if ($field->field_type=='createdby' || $field->field_type=='modifiedby') {
				$textcol = 'u.name';
				$query 	= ' SELECT u.id AS value_id, '.$textcol.' AS value FROM #__users AS u WHERE u.id<>0 AND u.id = '.(int)$post[0];
				
			} else if ($field->field_type=='created' || $field->field_type=='modified') {
				if ($nullDate===null) $nullDate	= $db->getNullDate();
				
				$date_filter_group = $field->parameters->get( $for_advsearch ? 'date_filter_group_s' : 'date_filter_group', 'month');
				if ($date_filter_group=='year') { $date_valformat='%Y'; }
				else if ($date_filter_group=='month') { $date_valformat='%Y-%m'; }
				else { $date_valformat='%Y-%m-%d'; }
				$valuecol = sprintf(' DATE_FORMAT(i.%s, "%s") ', $field->field_type, $date_valformat);
				
				$query 	= 'SELECT '.$valuecol.' AS value_id'
					.' FROM #__content AS i'
					.' WHERE i.'.$field->name.'<>'.$db->Quote($nullDate).' AND i.id='.$field->item_id;
				$db->setQuery($query);
				$value = $db->loadResult();
				$values = !$value ? false : array( $value => $value) ;
				unset($query);
				
			} else {
				$values = $post;  // Other fields will be entered as is into the index !!
			}
			
			if (!empty($query)) {
				$db->setQuery($query);
				$values = $db->loadAssocList('value_id', 'value');
			}
		} else {
			$values = null;
		}
		return $values;
	}
}

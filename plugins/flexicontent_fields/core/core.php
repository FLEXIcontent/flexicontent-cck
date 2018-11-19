<?php
/**
 * @package         FLEXIcontent
 * @version         3.2
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2017, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');

class plgFlexicontent_fieldsCore extends FCField
{
	static $cparams = null;

	static $field_types = null;
	var $task_callable = null;

	// ***
	// *** CONSTRUCTOR
	// ***

	function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_core', JPATH_ADMINISTRATOR);
		JPlugin::loadLanguage('plg_flexicontent_fields_textarea', JPATH_ADMINISTRATOR);

		if (self::$cparams===null)
		{
			self::$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		}
	}



	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	function onDisplayCoreFieldValue( &$_field, & $_item, &$params, $_tags=null, $_categories=null, $_favourites=null, $_favoured=null, $_vote=null, $raw_values=null, $prop='display' )
	{
		static $cat_links = array();
		static $tag_links = array();

		$items = !is_array($_item)
			? array( $_item )
			: $_item;

		$item = reset($items);

		// Check field exists, normally this should not happen
		if (!is_object($_field) && !isset($item->fields[$_field]))
		{
			return;
		}


		// ***
		// *** One time initialization
		// ***

		static $initialized = null;
		static $app, $document, $option, $format, $realview;
		static $itemViewId, $isItemsManager, $isHtmlViewFE;
		static $isMobile, $isTablet, $useMobile;
		static $cut_options;

		if ($initialized === null)
		{
			$initialized = 1;

			$app       = JFactory::getApplication();
			$document  = JFactory::getDocument();
			$option    = $app->input->get('option', '', 'cmd');
			$format    = $app->input->get('format', 'html', 'cmd');
			$realview  = $app->input->get('view', '', 'cmd');

			$itemViewId     = $realview === 'item' && $option === 'com_flexicontent' ? $app->input->get('id', 0, 'int') : 0;
			$isItemsManager = $app->isAdmin() && $realview === 'items' && $option === 'com_flexicontent';
			$isHtmlViewFE   = $format === 'html' && $app->isSite();

			$cparams   = JComponentHelper::getParams( 'com_flexicontent' );

			// Get isMobile / isTablet Flags
			$force_desktop_layout = $cparams->get('force_desktop_layout', 0 );
			$mobileDetector = flexicontent_html::getMobileDetector();
			$isMobile = $mobileDetector->isMobile();
			$isTablet = $mobileDetector->isTablet();
			$useMobile = $force_desktop_layout  ?  $isMobile && !$isTablet  :  $isMobile;

			$cut_options = array(
				'cut_at_word' => true,
				'more_toggler' => 2,
				'more_icon' => 'icon-paragraph-center',
				'more_txt' => '...',
				'modal_title'=>'...',
				'keep_jplugins_code' => false
			);
		}

		// Current view variable
		$view = $app->input->get('flexi_callview', ($realview ?: 'item'), 'cmd');

		// The current view is a full item view of the item
		$isMatchedItemView = $itemViewId === (int) $item->id;


		$field = is_object($_field)
			? $_field
			: $item->fields[$_field];

		// Prefix - Suffix - Separator parameters
		// These parameters should be common so we will retrieve them from the first item instead of inside the loop
		$remove_space = $field->parameters->get( 'remove_space', 0 ) ;
		$_pretext = $field->parameters->get( 'pretext', '' );
		$_posttext = $field->parameters->get( 'posttext', '' );
		$separatorf	= $field->parameters->get( 'separatorf', 3 ) ;
		$_opentag = $field->parameters->get( 'opentag', '' );
		$_closetag = $field->parameters->get( 'closetag', '' );
		$pretext_cacheable = $posttext_cacheable = $opentag_cacheable = $closetag_cacheable = false;

		switch($separatorf)
		{
			case 0:
			$separatorf = ' ';
			break;

			case 1:
			$separatorf = '<br class="fcclear" />';
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

		if ($prop === 'csv_export')
		{
			$separatorf = ', ';
			$itemprop = false;
		}

		// Get date format configuration for date-based fields
		if ($field->field_type === 'created' || $field->field_type === 'modified')
		{
			// Get date format
			$customdate = $field->parameters->get( 'custom_date', 'Y-m-d' ) ;
			$dateformat = $field->parameters->get( 'date_format', '' ) ;
			$dateformat = $dateformat ? JText::_($dateformat) : ($field->parameters->get( 'lang_filter_format', 0) ? JText::_($customdate) : $customdate);
		}

		// Get viewing layout
		$viewlayout = $field->parameters->get('viewlayout', '');
		$viewlayout = $viewlayout ? 'value_'.$viewlayout : 'value_default';

		foreach($items as $item)
		{
			//if (!is_object($_field)) echo $item->id." - ".$_field ."<br/>";
			if (is_object($_field))
				$field = $_field;

			else if (!empty($item->fields))
				$field = $item->fields[$_field];

			else continue;   // Item with no type ?

			if($field->iscore != 1) continue;
			$field->item_id = $item->id;
			$field->item_title = $item->title;

			// Replace item properties or values of other fields
			if (!$pretext_cacheable)
			{
				$pretext = FlexicontentFields::replaceFieldValue( $field, $item, $_pretext, 'pretext', $pretext_cacheable );
				if ($pretext && !$remove_space)  $pretext  =  $pretext . ' ';
			}

			if (!$posttext_cacheable)
			{
				$posttext = FlexicontentFields::replaceFieldValue( $field, $item, $_posttext, 'posttext', $posttext_cacheable );
				if ($posttext && !$remove_space) $posttext = ' ' . $posttext;
			}

			if (!$opentag_cacheable)  $opentag		= FlexicontentFields::replaceFieldValue( $field, $item, $_opentag, 'opentag', $opentag_cacheable );     // used by some fields
			if (!$closetag_cacheable) $closetag		= FlexicontentFields::replaceFieldValue( $field, $item, $_closetag, 'closetag', $closetag_cacheable );   // used by some fields

			$field->value = array();
			switch ($field->field_type)
			{
				case 'created': // created
					$field->value = array($item->created);

					// Create field's HTML
					$field->{$prop} = array();
					include(self::getViewPath('core', $viewlayout, 'created'));
					break;

				case 'createdby': // created by
					$field->value[] = $item->created_by;
					$field->{$prop} = $pretext.(($field->parameters->get('name_username', 1) == 2) ? $item->cuname : $item->creator).$posttext;
					break;

				case 'modified': // modified
					$field->value = array($item->modified);

					// Get date format
					$customdate = $field->parameters->get( 'custom_date', 'Y-m-d' ) ;
					$dateformat = $field->parameters->get( 'date_format', '' ) ;
					$dateformat = $dateformat ? JText::_($dateformat) : ($field->parameters->get( 'lang_filter_format', 0) ? JText::_($customdate) : $customdate);

					// Create field's HTML
					$field->{$prop} = array();
					include(self::getViewPath('core', $viewlayout, 'modified'));
					break;

				case 'modifiedby': // modified by
					$field->value[] = $item->modified_by;
					$field->{$prop} = $pretext.(($field->parameters->get('name_username', 1) == 2) ? $item->muname : $item->modifier).$posttext;
					break;

				case 'title': // title
					$field->value[] = $item->title;

					// Create field's HTML
					$field->{$prop} = array();
					include(self::getViewPath('core', $viewlayout, 'title'));
					break;

				case 'hits': // hits
					$field->value[] = $item->hits;
					$field->{$prop} = $pretext.$item->hits.$posttext;
					break;

				case 'type': // document type
					$field->value[] = $item->type_id;
					$field->{$prop} = $pretext.JText::_($item->typename).$posttext;
					break;

				case 'version': // version
					$field->value[] = $item->version;
					$field->{$prop} = $pretext.$item->version.$posttext;
					break;

				case 'state': // state
					$field->value[] = $item->state;
					$field->{$prop} = $pretext.flexicontent_html::stateicon( $item->state, $field->parameters ).$posttext;
					break;

				case 'voting': // voting button
					// TODO: if ($raw_values!==null) $vote = convert ... $raw_values;
					$vote = $_vote === false
						? $item->vote
						: $_vote;


					// Enable/disable according to current view
					$vote_submit_inview = FLEXIUtilities::paramToArray($field->parameters->get('vote_submit_inview', array('item')));
					$composite_inview   = FLEXIUtilities::paramToArray($field->parameters->get('composite_inview', array('item')));
					$enable_extra_votes = $field->parameters->get('enable_extra_votes', 0);

					$allow_vote = in_array($view, $vote_submit_inview) || ($isItemsManager && in_array('backend', $vote_submit_inview));
					$show_composite = in_array($view, $composite_inview) || ($isItemsManager && in_array('backend', $composite_inview));

					// Disable vote submit if voting disabled or if extra voting characteristics are not visible
					if (!$allow_vote || ($enable_extra_votes && !$show_composite))
					{
						$vote = $vote ?: new stdClass;
						$vote->allow_vote = false;
					}

					// Do not show extra votes
					if (!$show_composite)
					{
						$field->parameters->set('enable_extra_votes', 0);
					}

					$field->value[] = 'button';  // A dummy value to force display

					// Create field's HTML
					$field->{$prop} = array();
					include(self::getViewPath('core', $viewlayout, 'voting'));
					break;

				case 'favourites': // favourites button
					$favourites = $_favourites === false && isset($item->favs)
						? $item->favs
						: $_favourites;

					$favoured = $_favoured === false && isset($item->fav)
						? $item->fav
						: $_favoured;

					$field->value[] = 'button';  // A dummy value to force display

					// Create field's HTML
					$field->{$prop} = array();
					include(self::getViewPath('core', $viewlayout, 'favourites'));
					break;

				case 'categories': // assigned categories
					$field->{$prop} = '';

					/*if ($raw_values!==null) $categories = convert ... $raw_values;
					else */if ($_categories===false) $categories = & $item->cats;
					else $categories = & $_categories;


					if ($categories)
					{
						// Get categories that should be excluded from linking
						global $globalnoroute;
						if ( !is_array($globalnoroute) ) $globalnoroute = array();
						$link_to_view = $field->parameters->get( 'link_to_view', 1 ) ;

						$viewlayout = $field->parameters->get('viewlayout', '');
						$viewlayout = $viewlayout ? 'value_'.$viewlayout : 'value_default';

						// Create field's HTML
						$field->{$prop} = array();
						include(self::getViewPath('core', $viewlayout, 'categories'));
						$field->{$prop} = implode($separatorf, $field->{$prop});
						$field->{$prop} = $opentag . $field->{$prop} . $closetag;
					}
					break;

				case 'tags': // assigned tags
					$use_catlinks = self::$cparams->get('tags_using_catview', 0);
					$field->{$prop} = '';

					/*if ($raw_values!==null) $tags = convert ... $raw_values;
					else */if ($_tags===false) $tags = & $item->tags;
					else $tags = & $_tags;

					if ($tags)
					{
						$link_to_view = $field->parameters->get( 'link_to_view', 1 ) ;

						$viewlayout = $field->parameters->get('viewlayout', '');
						$viewlayout = $viewlayout ? 'value_'.$viewlayout : 'value_default';

						// Create field's HTML
						$field->{$prop} = array();
						include(self::getViewPath('core', $viewlayout, 'tags'));
						$field->{$prop} = implode($separatorf, $field->{$prop});
						$field->{$prop} = $opentag . $field->{$prop} . $closetag;
					}
					break;

				case 'maintext': // main text

					if ($prop === 'csv_export')
					{
						$field->{$prop} = $item->introtext . chr(13).chr(13) . $item->fulltext;
						continue;
					}

					// Special display using raw value
					if ($raw_values !== null)
					{
						$field->{$prop} = $raw_values[0];
					}

					// Specific display variables
					elseif ($prop !== 'display')
					{
						switch ($prop)
						{
							case 'display_if': $field->{$prop} = $item->introtext . chr(13).chr(13) . $item->fulltext;  break;
							case 'display_i' : $field->{$prop} = $item->introtext;  break;
							case 'display_f' : $field->{$prop} = $item->fulltext;   break;
						}
					}

					// Check for fulltext being empty and force using introtext
					elseif (!$item->fulltext)
					{
						$field->{$prop} = $item->introtext;
					}

					/**
					 * Multi-item views and listings: category, tags, favourites, module etc, only show introtext,
					 * but we have added 'force_full' item parameter to allow showing the fulltext too.
					 * This parameter can be inherited by category/menu parameters or be set inside template files
					 */
					elseif ($view !== 'item')
					{
						$field->{$prop} = $item->parameters->get('force_full', 0)
							? $item->introtext . chr(13) . chr(13) . $item->fulltext
							: $item->introtext;
					}

					// ITEM view only shows fulltext, introtext is shown only if 'show_intro' item parameter is set
					else
					{
						$field->{$prop} = $item->parameters->get('show_intro', 1)
							? $item->introtext . chr(13) . chr(13) . $item->fulltext
							: $item->fulltext;
					}

					if ($isItemsManager)
					{
						$uncut_length = 0;
						$field->{$prop} = flexicontent_html::striptagsandcut($field->{$prop}, 200, $uncut_length, $cut_options);
					}

					/*
					 * Add OGP Tags (description)
					 */
					if ($field->parameters->get('useogp', 1) && $field->{$prop})
					{
						// The current view is frontend view with HTML format and is a full item view of current item
						if ($isHtmlViewFE && $isMatchedItemView)
						{
							$ogpmaxlen = $field->parameters->get('ogpmaxlen', 300);

							$item->metadesc
								? $document->addCustomTag('<meta property="og:description" content="' . $item->metadesc . '" />')
								: $document->addCustomTag('<meta property="og:description" content="' . flexicontent_html::striptagsandcut($field->{$prop}, $ogpmaxlen) . '" />');
						}
					}
					break;
			}
		}
	}


	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		$field->label = JText::_($field->label);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if (!isset($field->formhidden_grp)) $field->formhidden_grp = $field->formhidden;
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;
		$is_ingroup  = !empty($field->ingroup);

		// Get viewing layout
		$field->item_id    = $item->id;
		$field->item_title = $item->title;

		switch ($field->field_type)
		{
			case 'voting': // voting button
				$vote_iform_display = (int) $field->parameters->get('vote_iform_display', 0);

				if (!$vote_iform_display)
				{
					$field->formhidden = 3;
					return;
				}

				// TODO: if ($raw_values !== null) $vote = convert ... $raw_values;
				$vote = !empty($item->vote) ? $item->vote : null;
				if ($vote)
				{
					$vote->allow_vote = $vote_iform_display && $vote_iform_display !== 2;
				}

				// Create field's HTML
				$field->html = flexicontent_html::ItemVote( $field, 'all', $vote );
				break;
		}
	}


	// ***
	// *** METHODS HANDLING before & after saving / deleting field events
	// ***

	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if($field->iscore != 1) return;
		if(!is_array($post) && !strlen($post)) return;

		if ($field->field_type == 'maintext')
		{
			// Field_type is not changed textarea so that field can handle this field type
			FLEXIUtilities::call_FC_Field_Func('textarea', 'onBeforeSaveField', array(&$field, &$post, &$file, &$item));
		}
	}


	// Method to do extra handling of field's values after all fields have validated their posted data, and are ready to be saved
	// $item->fields['fieldname']->postdata contains values of other fields
	// $item->fields['fieldname']->filedata contains files of other fields (normally this is empty due to using AJAX for file uploading)
	function onAllFieldsPostDataValidated( &$field, &$item )
	{
		if($field->iscore != 1) return;

		if ($field->field_type == 'title')
		{
			$auto_title = (int) $item->parameters->get('auto_title', 0);

			// Check if using 'auto_title_code', clear 'auto_title', if function not set
			$auto_title_code = $item->parameters->get('auto_title_code', '');
			$auto_title = $auto_title === 2 && !$auto_title_code ? 0 : $auto_title;

			if ($auto_title)
			{
				$field->postdata = array();
			}

			switch($auto_title)
			{
				case 1:     // AUTOMATIC TITLE, set to item ID
					$field->postdata[0] = $item->title = $item->id;
					break;
				case 2:     // AUTOMATIC TITLE, via function
					try {
						$field->postdata[0] = $item->title = @ eval($auto_title_code);
					}
					catch (ParseError $e) {
						JFactory::getApplication()->enqueueMessage( "Automatic title custom code, failed with: <pre>" . $e->getMessage() . '</pre>', 'warning');
					}
					if (!strlen($field->postdata[0]))
					{
						$field->postdata[0] = $item->title = $item->id;
					}
					break;
			}
		}
	}


	// Method to take any actions/cleanups needed after field's values are saved into the DB
	function onAfterSaveField( &$field, &$post, &$file, &$item )
	{
	}


	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item)
	{
	}



	// ***
	// *** CATEGORY/SEARCH FILTERING METHODS
	// ***

	// Method to display a search filter for the advanced search view
	function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if ($filter->iscore != 1)
		{
			return;
		}

		// For fields: title, description, only supports a basic filter of single text search input
		if ($filter->field_type === 'title' || $filter->field_type === 'maintext')
		{
			$filter->parameters->set( 'display_filter_as_s', 1 );
		}

		// This will make filter values to be retrieved from the value_id DB column
		$indexed_elements = in_array($filter->field_type, array('type','state','tags','categories','created','createdby','modified','modifiedby'));

		if ($filter->field_type == 'title' || $filter->field_type == 'created' || $filter->field_type == 'modified')
		{
			$this->onDisplayFilter($filter, $value, $formName, $isSearchView=1);
		}
		else
		{
			$filter->filter_orderby_adv = null;   // default will order by value and not by label
			FlexicontentFields::createFilter($filter, $value, $formName, $indexed_elements);
		}
	}


	// Method to display a category filter for the category view
	function onDisplayFilter(&$filter, $value='', $formName='adminForm', $isSearchView=0)
	{
		if ($filter->iscore != 1)
		{
			return;
		}

		// This will make filter values to be retrieved from the value_id DB column
		$indexed_elements = $isSearchView
			? in_array($filter->field_type, array('type','state','tags','categories','created','createdby','modified','modifiedby'))
			: false;

		$db = JFactory::getDbo();
		$formfieldname = 'filter_'.$filter->id;

		$_s = $isSearchView ? '_s' : '';
		$display_filter_as = $filter->parameters->get( 'display_filter_as'.$_s, 0 );  // Filter Type of Display
		$faceted_filter = $filter->parameters->get( 'faceted_filter'.$_s, 2 );  // Filter Type of Display
		$disable_keyboardinput = $filter->parameters->get('disable_keyboardinput', 0);
		$filter_as_range = in_array($display_filter_as, array(2,3,8)) ;

		// Create first prompt option of drop-down select
		$label_filter = $filter->parameters->get( 'display_label_filter'.$_s, 2 ) ;
		$first_option_txt = $label_filter==2 ? $filter->label : JText::_('FLEXI_ALL');

		// Prepend Field's Label to filter HTML
		//$filter->html = $label_filter==1 ? $filter->label.': ' : '';
		$filter->html = '';

		switch ($filter->field_type)
		{
			case 'title':
				$_inner_lb = $label_filter==2 ? $filter->label : JText::_('FLEXI_TYPE_TO_LIST');
				$_inner_lb = htmlspecialchars($_inner_lb, ENT_COMPAT, 'UTF-8');
				$_label_internal = '';
				$attribs_str = ' class="fc_field_filter '.$_label_internal.'" placeholder="'.$_inner_lb.'"';

				$filter_ffname = 'filter_'.$filter->id;
				$filter_ffid   = $formName.'_'.$filter->id.'_val';
				$title_value   = (isset($value[0]) ? $value[0] : '');

				$filter->html	.= '<input id="'.$filter_ffid.'" name="'.$filter_ffname.'" '.$attribs_str.' type="text" size="20" value="'.htmlspecialchars($title_value, ENT_COMPAT, 'UTF-8' ).'" />';
			break;

			case 'createdby':     // Authors
				// WARNING: we can not use column alias in from, join, where, group by, can use in having (some DB e.g. mysql) and in order-by
				// partial SQL clauses
				$text_col = $filter->parameters->get('name_username', 1) == 2 ? 'username' : 'name';
				$filter->filter_valuesselect = ' i.created_by AS value, CASE WHEN usr.' . $text_col . ' IS NULL THEN CONCAT(\''.JText::_('FLEXI_NOT_ASSIGNED').' ID:\', i.created_by) ELSE usr.' . $text_col . ' END AS text';
				$filter->filter_valuesjoin   = ' JOIN #__users AS usr ON usr.id = i.created_by';
				$filter->filter_valueswhere  = ' AND i.created_by <> 0';
				// full SQL clauses
				$filter->filter_groupby = ' GROUP BY i.created_by ';
				$filter->filter_having  = null;   // this indicates to use default, space is use empty
				$filter->filter_orderby = ' ORDER by text';   // default will order by value and not by label

				FlexicontentFields::createFilter($filter, $value, $formName);
			break;

			case 'modifiedby':   // Modifiers
				// WARNING: we can not use column alias in from, join, where, group by, can use in having (some DB e.g. mysql) and in order-by
				// partial SQL clauses
				$text_col = $filter->parameters->get('name_username', 1) == 2 ? 'username' : 'name';
				$filter->filter_valuesselect = ' i.modified_by AS value, CASE WHEN usr.' . $text_col . ' IS NULL THEN CONCAT(\''.JText::_('FLEXI_NOT_ASSIGNED').' ID:\', i.modified_by) ELSE usr.' . $text_col . ' END AS text';
				$filter->filter_valuesjoin   = ' JOIN #__users AS usr ON usr.id = i.modified_by';
				$filter->filter_valueswhere  = ' AND i.modified_by <> 0';
				// full SQL clauses
				$filter->filter_groupby = ' GROUP BY i.modified_by ';
				$filter->filter_having  = null;   // this indicates to use default, space is use empty
				$filter->filter_orderby = ' ORDER by text';   // default will order by value and not by label

				FlexicontentFields::createFilter($filter, $value, $formName);
			break;

			case 'type':  // Document Type
				// WARNING: we can not use column alias in from, join, where, group by, can use in having (some DB e.g. mysql) and in order-by
				// partial SQL clauses
				$filter->filter_valuesselect = ' ict.id AS value, ict.name AS text';
				$filter->filter_valuesfrom   = ' FROM #__flexicontent_items_ext AS i ';
				$filter->filter_valuesjoin   = ' JOIN #__flexicontent_types AS ict ON i.type_id = ict.id';
				$filter->filter_valueswhere  = ' ';  // ... a space, (indicates not needed and prevents using default)
				$filter->filter_item_id_col = ' i.item_id ';  // ID column

				// full SQL clauses
				$filter->filter_groupby = ' GROUP BY ict.id';
				$filter->filter_having  = null;   // this indicates to use default, space is use empty
				$filter->filter_orderby = ' ORDER by text';   // default will order by value and not by label

				FlexicontentFields::createFilter($filter, $value, $formName);
			break;

			case 'state':
				$options = array();
				//$options[] = (object) array('value' => '', '- '.$first_option_txt.' -');
				$options[] = (object) array('value' => 'P', 'text' => JText::_('FLEXI_PUBLISHED'));
				$options[] = (object) array('value' => 'U', 'text' => JText::_('FLEXI_UNPUBLISHED'));
				$options[] = (object) array('value' => 'PE', 'text' => JText::_('FLEXI_PENDING'));
				$options[] = (object) array('value' => 'OQ', 'text' => JText::_('FLEXI_TO_WRITE'));
				$options[] = (object) array('value' => 'IP', 'text' => JText::_('FLEXI_IN_PROGRESS'));
				$options[] = (object) array('value' => 'A', 'text' => JText::_('FLEXI_ARCHIVED'));
				//$options[] = (object) array('value' => 'T', 'text' => JText::_('FLEXI_TRASHED'));
				$filter->filter_options = $options;
				unset($options);

				FlexicontentFields::createFilter($filter, $value, $formName);
			break;

			case 'categories':
				// Get categories
				global $globalcats;
				$rootcatid = $filter->parameters->get( 'rootcatid'.$_s, '' ) ;

				$app = JFactory::getApplication();
				$option = $app->input->get('option', '', 'cmd');
				$view   = $app->input->get('view', '', 'cmd');

				$cid    = $app->isSite() ? $app->input->get('cid', 0, 'int') : 0;
				$cids   = $app->input->get('cids', array(), 'array');
				$cids   = ArrayHelper::toInteger($cids);

				$cats = array();

				if ($option=='com_flexicontent' && $view=='category' && (count($cids) || $cid))
				{
					// Current view is category view limit to descendants
					$cids = count($cids)
						? $cids
						: array($cid);
					//$cats = $globalcats[$cid]->childrenarray;
				}
				else if ( $rootcatid )
				{
					// If configured ... limit to subcategory tree of a specified category
					$cids = array($rootcatid);
					//$cats = $globalcats[$rootcatid]->childrenarray;
				}

				// Specific categories were given, e.g. category of current category view
				if ( count($cids) )
				{
					// If having more than one root then force displaying as drop-down select
					if (count($cids) > 1)
					{
						$filter->parameters->set('display_filter_as' . $_s, 0);
						$display_filter_as = 0;
					}

					// Loop through given categories, adding them and their immediate sub-categories
					foreach($cids as $_cid)
					{
						if (!isset($globalcats[$_cid]))
						{
							continue;
						}

						// Do not add root category of single category sub-tree to the filter
						if ( count($cids) > 1 )
						{
							$cat_obj = new stdClass();
							$cat_obj->id = $globalcats[$_cid]->id;
							$cat_obj->treename = $globalcats[$_cid]->title;  // Make first-level categories look as if at first level, thus do not use: $globalcats[$_cid]->treename;
							$cat_obj->totalitems = $globalcats[$_cid]->totalitems;
							$cats[] = $cat_obj;
						}

						if ( empty($globalcats[$_cid]->childrenarray))
						{
							continue;
						}

						foreach($globalcats[$_cid]->childrenarray as $child)
						{
							$_child = clone($child);
							$_child->treename = ($display_filter_as === 0 ? ' . ' : '') . $_child->title;
							$cats[] = $_child;
						}
					}
				}

				// Not display specific categories add all categories, forcing filter display as a drop-down select
				else
				{
					$filter->parameters->set('display_filter_as'.$_s, 0);
					$display_filter_as = 0;

					// All categories by default
					foreach($globalcats as $child)
					{
						$_child = clone($child);
						$_child->treename = str_replace('<sup>|_</sup> ', '', str_replace('&nbsp;.&nbsp;', '', $_child->treename));
						$cats[] = $_child;
					}
				}

				$filter->filter_options = array();

				if (!empty($cats))
				{
					foreach ($cats as $k => $list)
					{
						$filter->filter_options[] = (object) array('value' => $list->id, 'text' => $list->treename, 'found' => $list->totalitems);
					}
				}

				FlexicontentFields::createFilter($filter, $value, $formName);
			break;

			case 'tags':
				// WARNING: we can not use column alias in from, join, where, group by, can use in having (some DB e.g. mysql) and in order-by
				// partial SQL clauses
				$filter->filter_valuesselect = ' tags.id AS value, tags.name AS text';
				if (!$faceted_filter) {
					$filter->filter_valuesfrom = ' FROM #__flexicontent_tags AS tags ';
				} else {
					$filter->filter_valuesjoin   =
						 ' JOIN #__flexicontent_tags_item_relations AS tagsrel ON tagsrel.itemid = i.id '
						.' JOIN #__flexicontent_tags AS tags ON tags.id =  tagsrel.tid ';
				}
				$filter->filter_valueswhere  = ' ';  // ... a space, (indicates not needed and prevents using default)
				// full SQL clauses
				$filter->filter_groupby = ' GROUP BY tags.id ';
				$filter->filter_having  = null;   // this indicates to use default, space is use empty
				$filter->filter_orderby = ' ORDER by text';   // default will order by value and not by label

				FlexicontentFields::createFilter($filter, $value, $formName);
			break;

			case 'created':  // creation dates
			case 'modified': // modification dates
				$date_filter_group = $filter->parameters->get('date_filter_group'.$_s, 'month');
				if ($date_filter_group=='year') { $date_valformat='%Y'; }
				else if ($date_filter_group=='month') { $date_valformat='%Y-%m'; }
				else { $date_valformat='%Y-%m-%d'; }

				// Display date 'label' can be different than the (aggregated) date value
				$date_filter_label_format = $filter->parameters->get('date_filter_label_format'.$_s, '');
				$date_txtformat = $date_filter_label_format ? $date_filter_label_format : $date_valformat;  // If empty then same as value

				$filter->date_valformat = $date_valformat;
				$filter->date_txtformat = $date_txtformat;

				if($disable_keyboardinput)
				{
					$filter_ffid   = $formName.'_'.$filter->id.'_val';
					$document =  JFactory::getDocument();
					switch ($display_filter_as)
					{
						case 1:
							$document->addScriptDeclaration("
										jQuery(document).ready(function(){
											jQuery('#".$filter_ffid."').on('keydown keypress keyup', false);
										});
									");
						break;
						case 3:
							$document->addScriptDeclaration("
										jQuery(document).ready(function(){
											jQuery('#".$filter_ffid."1').on('keydown keypress keyup', false);
											jQuery('#".$filter_ffid."2').on('keydown keypress keyup', false);
										});
									");
						break;
					}
				}

				if (!$isSearchView)
				{
					$filter_as_range = in_array($display_filter_as, array(2,3,8));  // We don't want null date if using a range
					$nullDate_quoted = $db->Quote($db->getNullDate());
					$valuecol = sprintf(' CASE WHEN i.%s='.$nullDate_quoted.' THEN '.$nullDate_quoted.' ELSE DATE_FORMAT(i.%s, "%s") END ', $filter->field_type, $filter->field_type, $date_valformat);
					$textcol  = sprintf(' CASE WHEN i.%s='.$nullDate_quoted.' THEN "'.JText::_('FLEXI_NEVER').'" ELSE DATE_FORMAT(i.%s, "%s") END ', $filter->field_type, $filter->field_type, $date_txtformat);

					// WARNING: we can not use column alias in from, join, where, group by, can use in having (some DB e.g. mysql) and in order-by
					// partial SQL clauses
					$filter->filter_valuesselect = ' '.$valuecol.' AS value, '.$textcol.' AS text';
					$filter->filter_valuesjoin   = ' ';  // ... a space, (indicates not needed and prevents using default)
					$filter->filter_valueswhere  = $filter_as_range ? ' AND i.'.$filter->field_type.'<>'.$nullDate_quoted : ' ';  // ... a space, (indicates not needed and prevents using default)
					// full SQL clauses
					$filter->filter_groupby = ' GROUP BY '.$valuecol;
					$filter->filter_having  = null;   // this indicates to use default, space is use empty
					$filter->filter_orderby = ' ORDER BY '.$valuecol;
				}
				else
				{
					$filter->filter_orderby_adv = ' ORDER BY value_id';  // we can use a date type cast here, but it is not needed due to the format of value_id
				}

				FlexicontentFields::createFilter($filter, $value, $formName, $indexed_elements);
			break;

			default:
				$filter->html	.= 'Field type: '.$filter->field_type.' can not be used as search filter';
			break;
		}

		// a. If field filter has defined a custom SQL query to create filter (drop-down select) options, execute it and then create the options
		if ( !empty($query) )
		{
			$db->setQuery($query);
			$lists = $db->loadObjectList();

			// Add the options
			$options = array();
			$_inner_lb = $label_filter==2 ? $filter->label : JText::_('FLEXI_CLICK_TO_LIST');
			$_inner_lb = htmlspecialchars($_inner_lb, ENT_COMPAT, 'UTF-8');
			if ($display_filter_as == 6)
			{
				if ($label_filter==2)
				{
					$options[] = JHtml::_('select.option', '', $_inner_lb, 'value', 'text', $_disabled = true);
				}
			}
			else
				$options[] = JHtml::_('select.option', '', '- '.$first_option_txt.' -');
			foreach ($lists as $list) $options[] = JHtml::_('select.option', $list->value, $list->text . ($count_column ? ' ('.$list->found.')' : '') );
		}

		// b. If field filter has defined drop-down select options the create the drop-down select form field
		if ( !empty($options) )
		{
			// Make use of select2 lib
			flexicontent_html::loadFramework('select2');
			$classes  = " use_select2_lib";
			$extra_param = '';

			// MULTI-select: special label and prompts
			if ($display_filter_as == 6)
			{
				$classes .= ' fc_prompt_internal fc_is_selmultiple';

				// Add field's LABEL internally or click to select PROMPT (via js)
				$extra_param  = ' data-placeholder="'.$_inner_lb.'"';

				// Add type to filter PROMPT (via js)
				$extra_param .= ' data-fc_prompt_text="'.htmlspecialchars(JText::_('FLEXI_TYPE_TO_FILTER'), ENT_QUOTES, 'UTF-8').'"';
			}

			// Create HTML tag attributes
			$attribs_str  = ' class="fc_field_filter'.$classes.'" '.$extra_param;
			$attribs_str .= $display_filter_as==6 ? ' multiple="multiple" size="5" ' : '';
			//$attribs_str .= ($display_filter_as==0 || $display_filter_as==6) ? ' onchange="document.getElementById(\''.$formName.'\').submit();"' : '';

			// Filter name and id
			$filter_ffname = 'filter_'.$filter->id;
			$filter_ffid   = $formName.'_'.$filter->id.'_val';

			if (!is_array($value))
			{
				$value = array($value);
			}
			if ( count($value) === 1 && !strlen( reset($value) ) )
			{
				$value = array();
			}

			// Calculate if field has value
			$has_value = (!is_array($value) && strlen($value)) || (is_array($value) && count($value));
			$filter->html	.= $label_filter==2 && $has_value
				? ' <span class="badge fc_mobile_label" style="display:none;">'.JText::_($filter->label).'</span> '
				: '';

			// Create filter
			// Need selected values: array('') instead of array(), to force selecting the "field's prompt option" (e.g. field label) thus avoid "0 selected" display in mobiles
			$filter->html	.= $display_filter_as != 6
				? JHtml::_('select.genericlist', $options, $filter_ffname.'[]', $attribs_str, 'value', 'text', $value, $filter_ffid)
				: JHtml::_('select.genericlist', $options, $filter_ffname.'[]', $attribs_str, 'value', 'text', ($label_filter==2 && !count($value) ? array('') : $value), $filter_ffid);
		}

		// Special CASE for some filters, do some replacements
		if ( $filter->field_type == 'categories') $filter->html = str_replace('&lt;sup&gt;|_&lt;/sup&gt;', '\'-', $filter->html);
	}


 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for content lists e.g. category view, and not for search view
	function getFiltered(&$filter, $value, $return_sql=true)
	{
		if ( !$filter->iscore ) return;
		//echo __FUNCTION__ ." of CORE field type: ".$filter->field_type;

		$isdate = in_array($filter->field_type, array('date','created','modified')) || $filter->parameters->get('isdate',0);
		if ($isdate)
		{
			$date_filter_group = $filter->parameters->get('date_filter_group', 'month');
			if ($date_filter_group=='year') { $date_valformat='%Y'; }
			else if ($date_filter_group=='month') { $date_valformat='%Y-%m';}
			else { $date_valformat='%Y-%m-%d'; }

			$filter->filter_colname    = sprintf(' DATE_FORMAT(c.%s, "%s") ', $filter->field_type, $date_valformat);
			$filter->filter_valuesjoin = ' ';   // ... a space, (indicates not needed)
			$filter->filter_valueformat = sprintf(' DATE_FORMAT(__filtervalue__, "%s") ', $date_valformat);   // format of given values must be same as format of the value-column

			// 'isindexed' is not applicable for basic index and CORE fields
			$filter->isindexed = 0; //in_array($filter->field_type, array('type','state','tags','categories','created','createdby','modified','modifiedby'));
			return FlexicontentFields::getFiltered($filter, $value, $return_sql);
		}
		else
		{
			return $return_sql ? ' AND i.id IN (0) ' : array(0);
		}
	}


 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$filter, $value, $return_sql=true)
	{
		if ($filter->iscore != 1)
		{
			return;
		}

		if ($filter->field_type == 'maintext' || $filter->field_type == 'title') {
			$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		}

		$filter->isindexed = in_array($filter->field_type, array('type','state','tags','categories','created','createdby','modified','modifiedby'));
		return FlexicontentFields::getFilteredSearch($filter, $value, $return_sql);
	}



	// ***
	// *** SEARCH / INDEXING METHODS
	// ***

	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !$field->iscore ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;

		$values = $this->_prepareForSearchIndexing($field, $post, $for_advsearch=1);
		$filter_func = $field->field_type == 'maintext' ? 'strip_tags' : null;

		$field->isindexed = in_array($field->field_type, array('type','state','tags','categories','created','createdby','modified','modifiedby'));
		FlexicontentFields::onIndexAdvSearch($field, $values, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func);
		return true;
	}


	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !$field->iscore ) return;
		if ( !$field->issearch ) return;

		$values = $this->_prepareForSearchIndexing($field, $post, $for_advsearch=0);  // if post is null, indexer is running
		$filter_func = $field->field_type == 'maintext' ? 'strip_tags' : null;

		// 'isindexed' is not applicable for basic index and CORE fields
		$field->isindexed = 0; //in_array($field->field_type, array('type','state','tags','categories','created','createdby','modified','modifiedby'));

		// if values is null means retrieve data from the DB
		FlexicontentFields::onIndexSearch($field, $values, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func);
		return true;
	}



	// ***
	// *** VARIOUS HELPER METHODS
	// ***

	// Method to prepare for indexing, either preparing SQL query (if post is null) or formating/preparing given $post data for usage bu index
	function _prepareForSearchIndexing(&$field, &$post, $for_advsearch=0)
	{
		static $nullDate = null;
		static $state_names = null;

		if (!$state_names)
		{
			$state_names = array(
				1 => JText::_('FLEXI_PUBLISHED'),
				-5 => JText::_('FLEXI_IN_PROGRESS'),
				0 => JText::_('FLEXI_UNPUBLISHED'),
				-3 => JText::_('FLEXI_PENDING'),
				-4 => JText::_('FLEXI_TO_WRITE'),
				2 => JText::_('FLEXI_ARCHIVED'),
				-2 => JText::_('FLEXI_TRASHED')
			);
		}

		// null indicates that indexer is running, values is set to NULL which means retrieve data from the DB
		// for CORE fields, we do not set the query clauses, these are inside the fields helper file
		if ($post===null)
		{
			return null;
		}

		// IF no data posted, nothing to index
		if (empty($post))
		{
			return array();
		}

		$db = JFactory::getDbo();
		$_s = $for_advsearch ? '_s' : '';

		$values = array();
		if ($field->field_type=='type')
		{
			$textcol = 't.name';
			$query = ' SELECT t.id AS value_id, '.$textcol.' AS value FROM #__flexicontent_types AS t WHERE t.id<>0 AND t.id = '.(int)$post[0];
		}

		else if ($field->field_type=='state')
		{
			$values[$post[0]] = $state_names[$post[0]];
		}

		else if ($field->field_type=='categories')
		{
			$query = ' SELECT c.id AS value_id, c.title AS value FROM #__categories AS c WHERE c.id<>0 AND c.id IN ('.implode(",",$post).')';
		}

		else if ($field->field_type=='tags')
		{
			$query = ' SELECT t.id AS value_id, t.name AS value FROM #__flexicontent_tags AS t WHERE t.id<>0 AND t.id IN ('.implode(",",$post).')';
		}

		else if ($field->field_type=='createdby' || $field->field_type=='modifiedby')
		{
			$textcol = 'u.name';
			$query = ' SELECT u.id AS value_id, '.$textcol.' AS value FROM #__users AS u WHERE u.id<>0 AND u.id = '.(int)$post[0];
		}

		else if ($field->field_type=='created' || $field->field_type=='modified')
		{
			if ($nullDate===null) $nullDate	= $db->getNullDate();

			$date_filter_group = $field->parameters->get('date_filter_group'.$_s, 'month');
			if ($date_filter_group=='year') { $date_valformat='%Y'; }
			else if ($date_filter_group=='month') { $date_valformat='%Y-%m'; }
			else { $date_valformat='%Y-%m-%d'; }

			// Display date 'label' can be different than the (aggregated) date value
			$date_filter_label_format = $field->parameters->get('date_filter_label_format'.$_s, '');
			$date_txtformat = $date_filter_label_format ? $date_filter_label_format : $date_valformat;  // If empty then same as value

			$valuecol = sprintf(' DATE_FORMAT(i.%s, "%s") ', $field->field_type, $date_valformat);
			$textcol  = sprintf(' DATE_FORMAT(i.%s, "%s") ', $field->field_type, $date_txtformat);

			$query 	= 'SELECT '.$valuecol.' AS value_id, '.$textcol.' AS value'
				.' FROM #__content AS i'
				.' WHERE i.'.$field->name.'<>'.$db->Quote($nullDate).' AND i.id='.$field->item_id;
			$db->setQuery($query);
			$obj = $db->loadObject();
			$values = !$obj ? false : array( $obj->value_id => $obj->value) ;
			unset($query);
		}

		else
		{
			$values = $post;  // Other fields will be entered as is into the index !!
		}

		if (!empty($query))
		{
			$db->setQuery($query);
			$_values = $db->loadAssocList();
			$values = array();
			foreach ($_values as $v)
			{
				$values[$v['value_id']] = $v['value'];
			}
		}

		return $values;
	}

}

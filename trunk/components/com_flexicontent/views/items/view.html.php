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

jimport( 'joomla.application.component.view');

/**
 * HTML View class for the Items View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewItems extends JView
{
	var $_type = '';
	var $_name = FLEXI_ITEMVIEW;
	
	/**
	 * Creates the item page
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		jimport( 'joomla.html.parameter' );
		
		// check for form layout
		if($this->getLayout() == 'form') {
			$this->_displayForm($tpl);
			return;
		} else {
			$this->setLayout('item');
		}
		
		// Get globaltypes and make sure it is an array
		global $globalnopath;
		$globalnopath = !is_array($globalnopath) ? array() : $globalnopath;
		
		// Initialize variables
		$mainframe  = &JFactory::getApplication();
		$dispatcher = & JDispatcher::getInstance();
		$document   = & JFactory::getDocument();
		$user       = & JFactory::getUser();
		$db         = & JFactory::getDBO();
		$nullDate   = $db->getNullDate();
		
		$menu			= JSite::getMenu()->getActive();
		$aid			= !FLEXI_J16GE ? (int) $user->get('aid') : $user->getAuthorisedViewLevels();
		$model		= & $this->getModel();
		$limitstart	= JRequest::getVar('limitstart', 0, '', 'int');
		$cid			= JRequest::getInt('cid', 0);
		$Itemid		= JRequest::getInt('Itemid', 0);
		
		// Get the PAGE/COMPONENT parameters (WARNING: merges current menu item parameters in J1.5 but not in J1.6+)
		$params = clone($mainframe->getParams('com_flexicontent'));
		
		// In J1.6+ the above function does not merge current menu item parameters, it behaves like JComponentHelper::getParams('com_flexicontent') was called
		if (FLEXI_J16GE && $menu) {
			$menuParams = new JRegistry;
			$menuParams->loadJSON($menu->params);
			$params->merge($menuParams);
		}
		
		//add css file
		if (!$params->get('disablecss', '')) {
			$document->addStyleSheet($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css');
			$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext {zoom:1;}</style><![endif]-->');
		}
		//allow css override
		if (file_exists(JPATH_SITE.DS.'templates'.DS.$mainframe->getTemplate().DS.'css'.DS.'flexicontent.css')) {
			$document->addStyleSheet($this->baseurl.'/templates/'.$mainframe->getTemplate().'/css/flexicontent.css');
		}
		//special to hide the joomfish language selector on item views
		$css = '#jflanguageselection { visibility:hidden; }'; 
		if ($params->get('disable_lang_select', 1)) {
			$document->addStyleDeclaration($css);
		}

		//get item data
		
		// we are in display() task, so we will load the current item version by default
		// 'preview' request variable will force last, and finally 'version' request variable will force specific
		// NOTE: preview and version variables cannot be used by users that cannot edit the item
		JRequest::setVar('loadcurrent', true);
		$item = & $model->getItem();
		
		// Check that item was loaded successfully, NOTE, this maybe unreachable if getItem() already has raised an ERROR ...
		if ($item->id == 0)
		{
			$id	= JRequest::getInt('id', 0);
			return JError::raiseError( 404, JText::sprintf('FLEXI_CONTENT_UNAVAILABLE_ITEM_NOT_FOUND', $id) );
		}
		
		// Set item parameters as VIEW's parameters (item parameters are merged with component/page/type/current category/access parameters already)
		$params = & $item->parameters;
		
		// Load Template-Specific language file to override or add new language strings
		if (FLEXI_FISH || FLEXI_J16GE)
			FLEXIUtilities::loadTemplateLanguageFile( $params->get('ilayout') );
		
		// Bind Fields
		$item 	= FlexicontentFields::getFields($item, FLEXI_ITEMVIEW, $params, $aid);
		$item	= $item[0];
		
		// Note : This parameter doesn't exist yet but it will be used by the future gallery template
		if ($params->get('use_panes', 1)) {
			jimport('joomla.html.pane');
			$pane = & JPane::getInstance('Tabs');
			$this->assignRef('pane', $pane);
		}
		
		$fields		=& $item->fields;

		// Pathway need to be improved
		$cats		= new flexicontent_cats($cid);
		$parents	= $cats->getParentlist();
		$depth		= $params->get('item_depth', 0);
		
		// !!! The triggering of the event onPrepareContent (J1.5) /onContentPrepare (J2.5) of content plugins
		// !!! for description field (maintext) along with all other flexicontent
		// !!! fields is handled by flexicontent.fields.php
		// CODE REMOVED
		
		// Because the application sets a default page title, we need to get title right from the menu item itself
		if ($params->get('override_title', 0)) {
			if ($params->get('custom_ititle', '')) {
				$params->set('page_title',	$params->get('custom_ititle'));				
			} else {
				$params->set('page_title',	$item->title);
			}
		} else {
			// Get the menu item object		
			if (is_object($menu)) {
				$menu_params = new JParameter( $menu->params );
				if (!$menu_params->get( 'page_title')) {
					$params->set('page_title',	$item->title);
				}
			} else {
				$params->set('page_title',	$item->title);
			}
		}
		
		// *************************
		// Create the document title
		// *************************
		
		// First check and prepend category title
		if($cid && $params->get('addcat_title', 1) && (count($parents)>0)) {
			$parentcat = end($parents);
			$doc_title = (isset($parentcat->title) ? $parentcat->title.' - ':"") .$params->get( 'page_title' );
		} else {
			$doc_title = $params->get( 'page_title' );
		}
		
		// Second check and prepend site name
		if (FLEXI_J16GE) {  // Not available in J1.5
			// Add Site Name to page title
			if ($mainframe->getCfg('sitename_pagetitles', 0) == 1) {
				$params->set('page_title', $mainframe->getCfg('sitename') ." - ". $params->get( 'page_title' ));
			}
			elseif ($mainframe->getCfg('sitename_pagetitles', 0) == 2) {
				$params->set('page_title', $params->get( 'page_title' ) ." - ". $mainframe->getCfg('sitename'));
			}
		}
		
		// Finally, set document title
		$document->setTitle($doc_title);
		
		
		// ****************************
		// Set the document's META tags
		// ****************************
		
		// Set item's META data: desc, keyword, title, author
		if ($item->metadesc)		$document->setDescription( $item->metadesc );
		if ($item->metakey)			$document->setMetadata('keywords', $item->metakey);
		if ($mainframe->getCfg('MetaTitle') == '1')		$document->setMetaData('title', $item->title);
		if ($mainframe->getCfg('MetaAuthor') == '1')	$document->setMetaData('author', $item->author);
		
		// Set remaining META keys
		$mdata = new JParameter($item->metadata);
		$mdata = $mdata->toArray();
		foreach ($mdata as $k => $v)
		{
			if ($v)  $document->setMetadata($k, $v);
		}
		
		// @TODO check that as it seems to be dirty :(
		$uri  			=& JFactory::getURI();
		$base 			= $uri->getScheme() . '://' . $uri->getHost();
		$ucanonical 	= $base . JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug));
		if ($params->get('add_canonical')) {
			$document->addHeadLink( $ucanonical, 'canonical', 'rel', '' );
		}
		
		$limitstart	= JRequest::getVar('limitstart', 0, '', 'int');
		
		// increment the hit counter
		if ( $limitstart == 0 && FLEXIUtilities::count_new_hit($item) ) {
			$model->hit();
		}

		$themes		= flexicontent_tmpl::getTemplates();
		$tmplvar	= $themes->items->{$params->get('ilayout', 'default')}->tmplvar;

		if ($params->get('ilayout')) {
			// Add the templates css files if availables
			if (isset($themes->items->{$params->get('ilayout')}->css)) {
				foreach ($themes->items->{$params->get('ilayout')}->css as $css) {
					$document->addStyleSheet($this->baseurl.'/'.$css);
				}
			}
			// Add the templates js files if availables
			if (isset($themes->items->{$params->get('ilayout')}->js)) {
				foreach ($themes->items->{$params->get('ilayout')}->js as $js) {
					$document->addScript($this->baseurl.'/'.$js);
				}
			}
			// Set the template var
			$tmpl = $themes->items->{$params->get('ilayout')}->tmplvar;
		} else {
			$tmpl = '.items.default';
		}

		// Just put item's text (description field) inside property 'text' in case the events modify the given text,
		$item->text = isset($item->fields['text']->display) ? $item->fields['text']->display : '';
		
		// Maybe here not to import all plugins but just those for description field ???
		// Anyway these events are usually not very time consuming, so lets trigger all of them ???
		JPluginHelper::importPlugin('content');
		
		// Set the view and option to 'article' and 'com_content'
		JRequest::setVar('view', 'article');
		JRequest::setVar('option', 'com_content');
		JRequest::setVar("isflexicontent", "yes");
		
		// These events return text that could be displayed at appropriate positions by our templates
		$item->event = new stdClass();
		
		$results = $dispatcher->trigger('onAfterDisplayTitle', array (&$item, &$params, $limitstart));
		$item->event->afterDisplayTitle = trim(implode("\n", $results));

		$results = $dispatcher->trigger('onBeforeDisplayContent', array (& $item, & $params, $limitstart));
		$item->event->beforeDisplayContent = trim(implode("\n", $results));

		$results = $dispatcher->trigger('onAfterDisplayContent', array (& $item, & $params, $limitstart));
		$item->event->afterDisplayContent = trim(implode("\n", $results));
		
		// Set the view and option back to 'items' and 'com_flexicontent'
	  JRequest::setVar('view', FLEXI_ITEMVIEW);
	  JRequest::setVar('option', 'com_flexicontent');
		
		// Put text back into the description field, THESE events SHOULD NOT modify the item text, but some plugins may do it anyway... , so we assign text back for compatibility
		$item->fields['text']->display = & $item->text;
		if(isset($item->fields['text']->toc)) {
			$item->toc = &$item->fields['text']->toc;
		}
		
		// ********************************************************************************************
		// Create pathway, if automatic pathways is enabled, then path will be cleared before populated
		// ********************************************************************************************
		$pathway 	=& $mainframe->getPathWay();
		
		// Clear pathway, if automatic pathways are enabled
		if ( $params->get('automatic_pathways', 0) ) {
			$pathway_arr = $pathway->getPathway();
			$pathway->setPathway( array() );
			$pathway->set('_count', 0);
			$item_depth = 0;  // menu item depth is now irrelevant ???, ignore it
		} else {
			$item_depth = $params->get('item_depth', 0);
			if ($params->get('add_item_pathway', 1)) $item_depth++;
		}

		if (count($globalnopath) > 0) {   // ggppdk: I DO NOT understand the purpose of this IF
			if (!in_array($item->id, $globalnopath)) {
				$pathway->addItem( $this->escape($item->title), JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug)) );
			}
		} else {
			for ($p=$item_depth; $p<count($parents); $p++) {
				$pathway->addItem( $this->escape($parents[$p]->title), JRoute::_( FlexicontentHelperRoute::getCategoryRoute($parents[$p]->categoryslug) ) );
			}
			if ($params->get('add_item_pathway', 1)) {
				$pathway->addItem( $this->escape($item->title), JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug)) );
			}
		}
		
		$print_link = JRoute::_('index.php?view='.FLEXI_ITEMVIEW.'&cid='.$item->categoryslug.'&id='.$item->slug.'&pop=1&tmpl=component&print=1');

		$this->assignRef('item' , 				$item);
		$this->assignRef('user' , 				$user);
		$this->assignRef('params' , 			$params);
		$this->assignRef('menu_params' , 	$menu_params);
		$this->assignRef('print_link' , 	$print_link);
		$this->assignRef('parentcat',			$parentcat);
		$this->assignRef('fields',				$item->fields);
		$this->assignRef('tmpl' ,					$tmpl);

		/*
		 * Set template paths : this procedure is issued from K2 component
		 *
		 * "K2" Component by JoomlaWorks for Joomla! 1.5.x - Version 2.1
		 * Copyright (c) 2006 - 2009 JoomlaWorks Ltd. All rights reserved.
		 * Released under the GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
		 * More info at http://www.joomlaworks.gr and http://k2.joomlaworks.gr
		 * Designed and developed by the JoomlaWorks team
		 */
		$this->addTemplatePath(JPATH_COMPONENT.DS.'templates');
		$this->addTemplatePath(JPATH_SITE.DS.'templates'.DS.$mainframe->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates');
		$this->addTemplatePath(JPATH_COMPONENT.DS.'templates'.DS.'default');
		$this->addTemplatePath(JPATH_SITE.DS.'templates'.DS.$mainframe->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates'.DS.'default');
		if ($params->get('ilayout')) {
			$this->addTemplatePath(JPATH_COMPONENT.DS.'templates'.DS.$params->get('ilayout'));
			$this->addTemplatePath(JPATH_SITE.DS.'templates'.DS.$mainframe->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates'.DS.$params->get('ilayout'));
		}

		parent::display($tpl);

	}
	
	/**
	 * Creates the item submit form
	 *
	 * @since 1.0
	 */
	function _displayForm($tpl)
	{
		jimport( 'joomla.html.parameter' );
		
		// ... we use some strings from administrator part
		// load english language file for 'com_content' component then override with current language file
		JFactory::getLanguage()->load('com_content', JPATH_ADMINISTRATOR, 'en-GB', true);
		JFactory::getLanguage()->load('com_content', JPATH_ADMINISTRATOR, null, true);
		// load english language file for 'com_flexicontent' component then override with current language file
		JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
		JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);

		// Initialize variables
		$mainframe	= & JFactory::getApplication();
		$dispatcher	= & JDispatcher::getInstance();
		$document		= & JFactory::getDocument();
		$user				= & JFactory::getUser();
		$uri				= & JFactory::getURI();
		$db					= &JFactory::getDBO();
		$nullDate		= $db->getNullDate();
		$menu				= JSite::getMenu()->getActive();

		// Get the PAGE/COMPONENT parameters (WARNING: merges current menu item parameters in J1.5 but not in J1.6+)
		$params = clone($mainframe->getParams('com_flexicontent'));
		
		// In J1.6+ the above function does not merge current menu item parameters, it behaves like JComponentHelper::getParams('com_flexicontent') was called
		if (FLEXI_J16GE && $menu) {
			$menuParams = new JRegistry;
			$menuParams->loadJSON($menu->params);
			$params->merge($menuParams);
		}
		
		// Initialiaze JRequest variables
		JRequest::setVar('loadcurrent', false);  // we are in edit() task, so we load the last item version by default
		JRequest::setVar('typeid', @$menu->query['typeid'][0]);  // This also forces zero if value not set
		
		// Get item and model
		$model = & $this->getModel();
		$item = & $this->get('Item');
		if (FLEXI_J16GE) {
			//$model->setId(0); // Clear $model->_item, to force recalculation of the item data
			//$row = & $model->getItem($model->getId(), false);
			$form = & $this->get('Form');
		}
		
		// Load Template-Specific language file to override or add new language strings
		FLEXIUtilities::loadTemplateLanguageFile( $item->parameters->get('ilayout') );
		
		// *******************************
		// CHECK EDIT / CREATE PERMISSIONS
		// *******************************
		
		// new item and ownership variables
		$isnew = !$item->id;
		$isOwner = ( $item->created_by == $user->get('id') );
		
		if (!$isnew) {
			// EDIT action
			if (FLEXI_J16GE) {
				$asset = 'com_content.article.' . $item->id;
				$has_edit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $isOwner);
				// ALTERNATIVE 1
				//$has_edit = $model->getItemAccess()->get('access-edit'); // includes privileges edit and edit-own
				// ALTERNATIVE 2
				//$rights = FlexicontentHelperPerm::checkAllItemAccess($user->get('id'), 'item', $item->id);
				//$has_edit = in_array('edit', $rights) || (in_array('edit.own', $rights) && $item->created_by == $user->get('id')) ;
			} else if ($user->gid >= 25) {
				$has_edit = true;
			} else if (FLEXI_ACCESS) {
				$rights		= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
				$has_edit = in_array('edit', $rights) || (in_array('editown', $rights) && $isOwner);
			} else {
				$has_edit = $user->authorize('com_content', 'edit', 'content', 'all') || ($user->authorize('com_content', 'edit', 'content', 'own') && $isOwner);
			}

			if (!$has_edit) {
				// user isn't authorize to edit THIS item
				JError::raiseError( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
			}
		} else {
			// CREATE action
			if (FLEXI_J16GE) {
				$can_create = $model->getItemAccess()->get('access-create');
				// ALTERNATIVE 1
				//$allowed_cats = FlexicontentHelperPerm::checkUserElementsAccess($user->get('id'), 'core.create', 'category');
				//$can_create = count($allowed_cats) > 1;
			} else if (FLEXI_ACCESS) {
				$canAdd = FAccess::checkUserElementsAccess($user->gmid, 'submit');
				$can_create = @$canAdd['content'] || @$canAdd['category'];
			} else {
				$can_create	= $user->authorize('com_content', 'add', 'content', 'all');
			}
			
			$allowunauthorize = $params->get('allowunauthorize', 0); // MENU ITEM PARAMETER TO OVERRIDE CREATE PERMISSION !!! 
			
			if(!$can_create && !$allowunauthorize) {
				// user isn't authorize to create NEW item
				
				$notauth_itemid = $params->get('notauthurl');
				if (!$notauth_itemid) {
					// no custom unauthorized page is set in menu item parameters
					JError::raiseError( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
				} else {
					// custom unauthorized page is set in menu item parameters, retrieve url and redirect
					$item = JSite::getMenu()->getItem($notauth_itemid);
					$internal_link_vars = $item->component ? '&Itemid='.$notauth_itemid.'&option='.$item->component : '';
					$notauthurl = JRoute::_($item->link.$internal_link_vars, false);
					$mainframe->redirect($notauthurl, JText::_( 'FLEXI_ALERTNOTAUTH' ));
				}
			}
		}
		
		// *********************************************
		// Get more variables to push into the FORM view
		// *********************************************
		
		// Create the type parameters
		$tparams = & $this->get( 'Typeparams' );
		$tparams = new JParameter($tparams);
		
		// Check if saving an item that translates an original content in site's default language
		$enable_translation_groups = $params->get('enable_translation_groups');
		$is_content_default_lang = substr(flexicontent_html::getSiteDefaultLang(), 0,2) == substr($item->language, 0,2);
		$modify_untraslatable_values = $enable_translation_groups && !$is_content_default_lang && $item->lang_parent_id && $item->lang_parent_id!=$item->id;
		
		// *****************************************************************************
		// Get (CORE & CUSTOM) fields and their VERSIONED values and then
		// (a) Apply Content Type Customization to CORE fields (label, description, etc) 
		// (b) Create the edit html of the CUSTOM fields by triggering 'onDisplayField' 
		// *****************************************************************************
		$fields = & $this->get( 'Extrafields' );
		foreach ($fields as $field)
		{
			// -- SET a type specific label & description for the core field and also retrieve any other ITEM TYPE customizations (must call this manually when editing)
			if ($field->iscore) {
				FlexicontentFields::loadFieldConfig($field, $item);
			} else {
				// Create field 's editing HTML
				if ($modify_untraslatable_values && $field->untranslatable) {
					$field->html = JText::_( 'FLEXI_FIELD_VALUE_IS_UNTRANSLATABLE' );
				} else {
					FLEXIUtilities::call_FC_Field_Func($field->field_type, 'onDisplayField', array( &$field, &$item ));
				}
			}
			if ($field->field_type == 'maintext')
			{
				// Create main text field, via calling the display function of the textarea field (will also check for tabs)
				// We use the text created by the model and not the text retrieved by the CORE plugin code, which maybe overwritten with JoomFish data
				
				if ( isset($item->item_translations) ) {
					$itemlang = substr($item->language ,0,2);
					foreach ($item->item_translations as $lang_id => $t)	{
						if ($itemlang == $t->shortcode) continue;
						$field->name = array('jfdata',$t->shortcode,'text');
						$field->value[0] = html_entity_decode($t->fields->text->value, ENT_QUOTES, 'UTF-8');
						FLEXIUtilities::call_FC_Field_Func('textarea', 'onDisplayField', array(&$field, &$item) );
						$t->fields->text->tab_labels = $field->tab_labels;
						$t->fields->text->html = $field->html;
						unset( $field->tab_labels );
						unset( $field->html );
					}
				}
				$field->name = 'text';
				$field->value[0] = html_entity_decode(FLEXI_J16GE ? $item->text: $item->text, ENT_QUOTES, 'UTF-8');
				FLEXIUtilities::call_FC_Field_Func('textarea', 'onDisplayField', array(&$field, &$item) );
			}
		}
		
		// Tags used by the item
		$usedtagsids  = & $this->get( 'UsedtagsIds' );  // NOTE: This will normally return the already set versioned value of tags ($item->tags)
		//$usedtagsIds 	= $isnew ? array() : $fields['tags']->value;
		$usedtagsdata = $model->getUsedtagsData($usedtagsids);
		//echo "<br>usedtagsIds: "; print_r($usedtagsids);
		//echo "<br>usedtags (data): "; print_r($usedtagsdata);
		
		// Compatibility for old overriden templates ...
		if (!FLEXI_J16GE) {
			$tags			= & $this->get('Alltags');
			$usedtags	= & $this->get('UsedtagsIds');
		}
		
		// Load permissions (used by form template)
		$perms 	= array();
		if (FLEXI_J16GE) {
			$perms['isSuperAdmin']= $user->authorise('core.admin', 'root.1');
			$permission = FlexicontentHelperPerm::getPerm();
			$perms['multicat'] = $permission->MultiCat;
			$perms['cantags'] = $permission->CanUseTags;
			$perms['canparams'] = $permission->CanParams;
			$perms['cantemplates']= $permission->CanTemplates;
			
			//item specific permissions
			if ( $item->id ) {
				$asset = 'com_content.article.' . $item->id;
				$perms['canedit']			= $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $isOwner);
				$perms['canpublish']	= $user->authorise('core.edit.state', $asset) || ($user->authorise('core.edit.state.own', $asset) && $isOwner);
				$perms['candelete']		= $user->authorise('core.delete', $asset) || ($user->authorise('core.delete.own', $asset) && $isOwner);
			} else {
				$perms['canedit']			= $user->authorise('core.edit', 'com_flexicontent');
				$perms['canpublish']	= $user->authorise('core.edit.state', 'com_flexicontent');
				$perms['candelete']		= $user->authorise('core.delete', 'com_flexicontent');
			}
			
			$perms['canright']		= $permission->CanConfig;
		} else if (FLEXI_ACCESS) {
			$perms['isSuperAdmin']= $user->gid >= 25;
			$perms['multicat'] 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'multicat', 'users', $user->gmid) : 1;
			$perms['cantags'] 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'usetags', 'users', $user->gmid) : 1;
			$perms['canparams'] 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'paramsitems', 'users', $user->gmid) : 1;
			$perms['cantemplates']= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'templates', 'users', $user->gmid) : 1;

			if ($item->id) {
				$rights = FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
				$perms['canedit']		= ($user->gid < 25) ? ( (in_array('editown', $rights) && $item->created_by == $user->get('id')) || (in_array('edit', $rights)) ) : 1;
				$perms['canpublish']	= ($user->gid < 25) ? ( (in_array('publishown', $rights) && $item->created_by == $user->get('id')) || (in_array('publish', $rights)) ) : 1;
				$perms['candelete']		= ($user->gid < 25) ? ( (in_array('deleteown', $rights) && $item->created_by == $user->get('id')) || (in_array('delete', $rights)) ) : 1;
				$perms['canright']		= ($user->gid < 25) ? ( (in_array('right', $rights)) ) : 1;
			} else {
				$perms['canedit']		= ($user->gid < 25) ? 0 : 1;
				$perms['canpublish']	= ($user->gid < 25) ? 0 : 1;
				$perms['candelete']		= ($user->gid < 25) ? 0 : 1;
				$perms['canright']		= ($user->gid < 25) ? 0 : 1;
			}
		} else {
			$perms['isSuperAdmin']= $user->gid >= 25;
			$perms['multicat']		= 1;
			$perms['cantags'] 		= 1;
			$perms['canparams'] 	= 1;
			$perms['cantemplates']= $user->gid >= 25;
			$perms['canedit']		= ($user->gid >= 20);
			$perms['canpublish']	= ($user->gid >= 21);
			$perms['candelete']		= ($user->gid >= 21);
			$perms['canright']		= ($user->gid >= 21);
		}

		// Get the edit lists
		$lists = $this->_buildEditLists($perms);

		// Build languages list
		$site_default_lang = flexicontent_html::getSiteDefaultLang();
		if (FLEXI_J16GE) {
			$item_lang = $isnew ? $site_default_lang : $item->language;
			$lists['languages'] = flexicontent_html::buildlanguageslist('jform[language]', '', $item_lang, 3);
		} else if (FLEXI_FISH) {
			$item_lang = $isnew ? $site_default_lang : $item->language;
			$lists['languages'] = flexicontent_html::buildlanguageslist('language', '', $item_lang, 3);
		} else {
			$item->language = $site_default_lang;
		}
		
		//Load the JEditor object
		$editor =& JFactory::getEditor();
		
		// Add the js files to the document <head> section
		//JHTML::_('behavior.formvalidation'); // Commented out, custom overloaded validator class loaded inside form template file
		JHTML::_('behavior.tooltip');
		JHTML::_('script', 'joomla.javascript.js', 'includes/js/');

		// Add css files to the document <head> section
		$document->addStyleSheet($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css');
		if (!FLEXI_J16GE) {
			$document->addStyleSheet($this->baseurl.'/administrator/templates/khepri/css/general.css');
		}
		$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #flexicontent dd { height: 1%; }</style><![endif]-->');
		
		// Set page title
		$title = !$isnew ? JText::_( 'FLEXI_EDIT' ) : JText::_( 'FLEXI_NEW' );
		$document->setTitle($title);
		
		// Add title to pathway
		$pathway =& $mainframe->getPathWay();
		$pathway->addItem($title, '');
		
		// Merge item parameters
		if (!$isnew) {
			$params->merge($item->parameters);
		}
		
		// Ensure the row data is safe html
		// @TODO: check if this is really required as it conflicts with the escape function in the tmpl
		//JFilterOutput::objectHTMLSafe( $item );
		
		$this->assign('action',				$uri->toString());
		$this->assignRef('item',			$item);
		if (FLEXI_J16GE) {  // most core field are created via calling methods of the form (J2.5)
			$this->assignRef('form',		$form);
		}
		$this->assignRef('params',		$params);
		$this->assignRef('lists',			$lists);
		$this->assignRef('editor',		$editor);
		$this->assignRef('user',			$user);
		if (!FLEXI_J16GE) {  // compatibility old templates
			$this->assignRef('tags',		$tags);
			$this->assignRef('usedtags',	$usedtags);
		}
		$this->assignRef('usedtagsdata', $usedtagsdata);
		$this->assignRef('fields',		$fields);
		$this->assignRef('tparams',		$tparams);
		$this->assignRef('perms', 		$perms);
		$this->assignRef('document',	$document);
		$this->assignRef('nullDate',	$nullDate);
		
		// **************************************************************************************
		// Load a different template file for parameters depending on whether we use FLEXI_ACCESS
		// **************************************************************************************
		
		if (!FLEXI_J16GE) {
			if (FLEXI_ACCESS) {
				$formparams = new JParameter('', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'item2.xml');
			} else {
				$formparams = new JParameter('', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'item.xml');
			}
		}
		
		
		// ****************************************************************
		// SET INTO THE FORM, parameter values for various parameter groups
		// ****************************************************************
			
		if (!FLEXI_J16GE) {
			// Permissions (Access) Group
			if (!FLEXI_ACCESS) {
				$formparams->set('access', $item->access);
			}
			
			// Set: (Publication) Details Group
			$created_by = (intval($item->created_by) ? intval($item->created_by) : $user->get('id'));
			$formparams->set('created_by', $created_by);
			$formparams->set('created_by_alias', $item->created_by_alias);
			$formparams->set('created', JHTML::_('date', $item->created, '%Y-%m-%d %H:%M:%S'));
			$formparams->set('publish_up', JHTML::_('date', $item->publish_up, '%Y-%m-%d %H:%M:%S'));
			if (JHTML::_('date', $item->publish_down, '%Y') <= 1969 || $item->publish_down == $nullDate) {
				$formparams->set('publish_down', JText::_( 'FLEXI_NEVER' ));
			} else {
				$formparams->set('publish_down', JHTML::_('date', $item->publish_down, '%Y-%m-%d %H:%M:%S'));
			}
			
			// Set:  Attributes (parameters) Group, (these are retrieved from the item table column 'attribs')
			// (also contains templates parameters, but we will use these individual for every template ... see below)
			$formparams->loadINI($item->attribs);
	
			// Set: Metadata (parameters) Group
			// NOTE: (2 params from 2 item table columns, and then multiple params from item table column 'metadata')
			$formparams->set('description', $item->metadesc);
			$formparams->set('keywords', $item->metakey);
			$formparams->loadINI($item->metadata);
			
			// Now create the sliders object,
			// And also push the Form Parameters object into the template (Template Parameters object is seperate)
			jimport('joomla.html.pane');
			$pane = & JPane::getInstance('sliders');
			$this->assignRef('pane'				, $pane);
			$this->assignRef('formparams'	, $formparams);
		} else {
			if ( JHTML::_('date', $item->publish_down , 'Y') <= 1969 || $item->publish_down == $nullDate ) {
				$item->publish_down= JText::_( 'FLEXI_NEVER' );
			}
		}
		
		// Set: Templates (parameters) Group, by loading item's attribs for every template (these are ALSO saved in the table column 'attribs')
		$themes			= flexicontent_tmpl::getTemplates();
		$tmpls_all	= $themes->items;
		
		$allowed_tmpls = $tparams->get('allowed_ilayouts');
		$type_default_layout = $tparams->get('ilayout', 'default');
		if ( empty($allowed_tmpls) )							$allowed_tmpls = array();
		else if ( ! is_array($allowed_tmpls) )		$allowed_tmpls = !FLEXI_J16GE ? array($allowed_tmpls) : explode("|", $allowed_tmpls);
		if ( !in_array( $type_default_layout, $allowed_tmpls ) ) $allowed_tmpls[] = $type_default_layout;
		
		if ( count($allowed_tmpls) ) {
			foreach ($tmpls_all as $tmpl) {
				if (in_array($tmpl->name, $allowed_tmpls) )
				$tmpls[]= $tmpl;
			}
		} else {
			$tmpls= $tmpls_all;
		}
		
		if (!$isnew) {
			foreach ($tmpls as $tmpl) {
				if (FLEXI_J16GE) {
					foreach ($tmpl->params->getGroup('attribs') as $field) {
						$fieldname =  $field->__get('fieldname');
						$value = $item->itemparams->get($fieldname);
						if ( strlen($value) ) $tmpl->params->setValue($fieldname, 'attribs', $value);
					}
				} else {
					$tmpl->params->loadINI($item->attribs);
				}
			}
		}
		$this->assignRef('tmpls',		$tmpls);
		
		parent::display($tpl);
	}
	
	/**
	 * Creates the item submit form
	 *
	 * @since 1.0
	 */
	function _buildEditLists($perms)
	{
		global $globalcats;
		$lists = array();
		
		$user = & JFactory::getUser();	// get current user
		$item = & $this->get('Item');		// get the item from the model
		$categories = $globalcats;			// get the categories tree
		$selectedcats = & $this->get( 'Catsselected' );		// get category ids, NOTE: This will normally return the already set versioned value of categories ($item->categories)
		$actions_allowed = array('core.create');					// user actions allowed for categories
		$types = & $this->get( 'Typeslist' );
		$typesselected = '';
		
		if ( $perms['canpublish'] && !$item->id ) $item->state = 1;
		
		// Multi-category form field, for user allowed to use multiple categories
		$lists['cid'] = '';
		if ($perms['multicat']) {
			$class = 'class="inputbox validate" multiple="multiple" size="8"';
			if (FLEXI_J16GE) {
				$lists['cid'] = flexicontent_cats::buildcatselect($categories, 'jform[cid][]', $selectedcats, false, $class, true, true,	$actions_allowed);
			} else {
				$lists['cid'] = flexicontent_cats::buildcatselect($categories, 'cid[]', $selectedcats, false, $class, true, true,	$actions_allowed);
			}
		}
		
		//buid types selectlist
		if (FLEXI_J16GE) {
			$lists['type'] = flexicontent_html::buildtypesselect($types, 'jform[type_id]', $typesselected, 1, 'class="required"', true );
		} else {
			$lists['type'] = flexicontent_html::buildtypesselect($types, 'type_id', $typesselected, 1, 'class="required"', true );
		}
		
		// Main category form field
		$class = 'class="inputbox required validate"';
		if (FLEXI_J16GE) {
			$lists['catid'] = flexicontent_cats::buildcatselect($categories,'jform[catid]', $item->catid, 2, $class, true, true, $actions_allowed);
		} else {
			$lists['catid'] = flexicontent_cats::buildcatselect($categories,'catid', $item->catid, 2, $class, true, true, $actions_allowed);
		}
		
		if (!FLEXI_J16GE) {
			$state = array();
			$state[] = JHTML::_('select.option',  1, JText::_( 'FLEXI_PUBLISHED' ) );
			$state[] = JHTML::_('select.option',  0, JText::_( 'FLEXI_UNPUBLISHED' ) );
			$state[] = JHTML::_('select.option',  -3, JText::_( 'FLEXI_PENDING' ) );
			$state[] = JHTML::_('select.option',  -4, JText::_( 'FLEXI_TO_WRITE' ) );
			$state[] = JHTML::_('select.option',  -5, JText::_( 'FLEXI_IN_PROGRESS' ) );
			$lists['state'] = JHTML::_('select.genericlist', $state, 'state', '', 'value', 'text', $item->state );
		}
		
		$vstate = array();
		$vstate[] = JHTML::_('select.option',  1, JText::_( 'FLEXI_NO' ) );
		$vstate[] = JHTML::_('select.option',  2, JText::_( 'FLEXI_YES' ) );
		if (FLEXI_J16GE) {
			$lists['vstate'] = JHTML::_('select.genericlist', $vstate, 'jform[vstate]', '', 'value', 'text', 2 );
		} else {
			$lists['vstate'] = JHTML::_('select.radiolist', $vstate, 'vstate', '', 'value', 'text', 2 );
		}

		// build granular access list
		if (FLEXI_ACCESS) {
			if (isset($user->level)) {
				$lists['access'] = FAccess::TabGmaccess( $item, 'item', 1, 0, 0, 1, 0, 1, 0, 1, 1 );
			} else {
				$lists['access'] = JText::_('Your profile has been changed, please logout to access to the permissions');
			}
		} else {
			$lists['access'] = JHTML::_('list.accesslevel', $item);
		}

		return $lists;
	}
}
?>

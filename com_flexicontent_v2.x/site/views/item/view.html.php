<?php 
/**
 * @version 1.5 stable $Id: view.html.php 351 2010-06-29 11:00:04Z emmanuel.danan $
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
class FlexicontentViewItem extends JView {
	/**
	 * Creates the item page
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		global $globaltypes;
		$mainframe = &JFactory::getApplication();

		//initialize variables
		$document 	= & JFactory::getDocument();
		$user		= & JFactory::getUser();
		$menus		= & JSite::getMenu();
		$menu    	= $menus->getActive();
		$dispatcher = & JDispatcher::getInstance();
		$params 	= & $mainframe->getParams('com_flexicontent');
		$aid		= (int) $user->get('aid');
		$model		= & $this->getModel();
		$limitstart	= JRequest::getVar('limitstart', 0, '', 'int');
		$cid		= JRequest::getInt('cid', 0);

		if($this->getLayout() == 'form') {
			$this->_displayForm($tpl);
			return;
		}
		
		//Set layout
		$this->setLayout('item');

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
		JRequest::setVar('loadcurrent', true);
		$item = $model->getItem();

		$iparams	=& $item->parameters;
		$params->merge($iparams);

		// Bind Fields
		$item 	= FlexicontentFields::getFields($item, 'item', $params, $aid);
		$item	= $item[0];

		// Note : This parameter doesn't exist yet but it will be used by the future gallery template
		if ($params->get('use_panes', 1)) {
			jimport('joomla.html.pane');
			$pane = & JPane::getInstance('Tabs');
			$this->assignRef('pane', $pane);
		}

		if ($item->id == 0)
		{	
			$id	= JRequest::getInt('id', 0);
			return JError::raiseError( 404, JText::sprintf( 'ITEM #%d NOT FOUND (item/view.html)', $id ) );
		}
		
		$fields		=& $item->fields;

		// Pathway need to be improved
		$cats		= new flexicontent_cats($cid);
		$parents	= $cats->getParentlist();
		$depth		= $params->get('item_depth', 0);

		$pathway 	=& $mainframe->getPathWay();
		for($p = $depth; $p<count($parents); $p++) {
			$pathway->addItem( $this->escape($parents[$p]->title), JRoute::_( FlexicontentHelperRoute::getCategoryRoute($parents[$p]->categoryslug) ) );
		}
		if ($params->get('add_item_pathway', 1)) {
			$pathway->addItem( $this->escape($item->title), JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug)) );
		}
		
		// !!! The triggering of the event onPrepareContent of content plugins
		// !!! for description field (maintext) along with all other flexicontent
		// !!! fields is handled by flexicontent.fields.php
		// CODE REMOVED
		
//		JPluginHelper::importPlugin('content');
//		$item->event = new stdClass();
//		$results = $dispatcher->trigger('onPrepareContent', array (&$item, &$params, $limitstart));
//		$item->event->afterDisplayTitle = trim(implode("\n", $results));
//
		/*
		 * Handle the metadata
		 *
		 * Because the application sets a default page title,
		 * we need to get it right from the menu item itself
		 */
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

		/*
		 * Create the document title
		 * 
		 * First is to check if we have a category id, if yes add it.
		 * If we haven't one than we accessed this screen direct via the menu and don't add the parent category
		 */
		if($cid && $params->get('addcat_title', 1)) {
			$parentcat = array_pop($parents);
			$doc_title = $parentcat->title.' - '.$params->get( 'page_title' );
		} else {
			$doc_title = $params->get( 'page_title' );
		}
		
		$document->setTitle($doc_title);
		
		if ($item->metadesc) {
			$document->setDescription( $item->metadesc );
		}
		
		if ($item->metakey) {
			$document->setMetadata('keywords', $item->metakey);
		}
		
		if ($mainframe->getCfg('MetaTitle') == '1') {
			$document->setMetaData('title', $item->title);
		}
		
		if ($mainframe->getCfg('MetaAuthor') == '1') {
			$document->setMetaData('author', $item->author);
		}

		$mdata = new JParameter($item->metadata);
		$mdata = $mdata->toArray();
		foreach ($mdata as $k => $v)
		{
			if ($v) {
				$document->setMetadata($k, $v);
			}
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
		if ($limitstart == 0) {
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
		$item->text = @$item->fields['text']->display;
		
		// Maybe here not to import all plugins but just those for description field ?
		JPluginHelper::importPlugin('content');
		
		// These events return text that could be displayed at appropriate positions by our templates
		
		$results = $dispatcher->trigger('onAfterDisplayTitle', array (&$item, &$params, $limitstart));
		$item->event->afterDisplayTitle = trim(implode("\n", $results));

		$results = $dispatcher->trigger('onBeforeDisplayContent', array (& $item, & $params, $limitstart));
		$item->event->beforeDisplayContent = trim(implode("\n", $results));

		$results = $dispatcher->trigger('onAfterDisplayContent', array (& $item, & $params, $limitstart));
		$item->event->afterDisplayContent = trim(implode("\n", $results));
		
		// Put text back into the description field
		$item->fields['text']->display = $item->text;
				
		$pathway 	=& $mainframe->getPathWay();
		if (count($globaltypes) > 0) {
			if (!in_array($item->id, $globaltypes)) {
				$pathway->addItem( $this->escape($item->title), JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug)) );
			}
		} else {
			for($p = $depth; $p<count($parents); $p++) {
				$pathway->addItem( $this->escape($parents[$p]->title), JRoute::_( FlexicontentHelperRoute::getCategoryRoute($parents[$p]->categoryslug) ) );
			}
			if ($params->get('add_item_pathway', 1)) {
				$pathway->addItem( $this->escape($item->title), JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug)) );
			}
		}

		$print_link = JRoute::_('index.php?view=item&cid='.$item->categoryslug.'&id='.$item->slug.'&pop=1&tmpl=component&print=1');

		$this->assignRef('item' , 				$item);
		$this->assignRef('user' , 				$user);
		$this->assignRef('params' , 			$params);
		$this->assignRef('iparams' , 			$iparams);
		$this->assignRef('menu_params' , 		$menu_params);
		$this->assignRef('print_link' , 		$print_link);
		$this->assignRef('parentcat',			$parentcat);
		$this->assignRef('fields',				$item->fields);
		$this->assignRef('tmpl' ,				$tmpl);

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
	function _displayForm($tpl) {

		$mainframe = &JFactory::getApplication();

		//Initialize variables
		$dispatcher = & JDispatcher::getInstance();
		$document	=& JFactory::getDocument();
		$user		=& JFactory::getUser();
		$menus		= & JSite::getMenu();
		$menu    	= $menus->getActive();
		$uri     	=& JFactory::getURI();
		JRequest::setVar('loadcurrent', false);
		//$item		=& $this->get('Item');
		JRequest::setVar('typeid', @$menu->query['typeid'][0]);
		$item		= $this->get('Form');
		$tags 		=& $this->get('Alltags');
		$used 		=& $this->get('Usedtags');
		//$params		=& $mainframe->getParams('com_flexicontent');
		//$params		=& JComponentHelper::getParams('com_flexicontent');
		$params = &$menu->params;
		//echo "<xmp>";var_dump($menu->params);echo "</xmp>";
		//$params	=& $item->parameters;var_dump($item);
		$Itemid		=&JRequest::getVar('Itemid', 0);
		$db = &JFactory::getDBO();
		JPlugin::loadLanguage('com_flexicontent', JPATH_ADMINISTRATOR);
		JPlugin::loadLanguage('com_content', JPATH_ADMINISTRATOR);
		if($Itemid) {
			$query = "SELECT params FROM #__menu WHERE id='{$Itemid}';";
			$db->setQuery($query);
			$paramsstring = $db->loadResult();
			$mparams = new JParameter($paramsstring);
			$params->merge($mparams);
		}
		$nullDate 		= $db->getNullDate();
		
		$tparams	=& $this->get( 'Typeparams' );
		
		$fields			= & $this->get( 'Extrafields' );
		// Add html to field object trought plugins
		foreach ($fields as $field) {
			JPluginHelper::importPlugin('flexicontent_fields', $field->field_type);
			$results = $dispatcher->trigger('onDisplayField', array( &$field, &$item ));
		}
		JHTML::_('script', 'joomla.javascript.js', 'includes/js/');
		$allowunauthorize = $params->get('allowunauthorize', 0);

		// first check if the user is logged
		if (!$allowunauthorize && !$user->get('id')) {
			$menu =& JSite::getMenu();
			$itemid = $params->get('notauthurl');
			$item = $menu->getItem($itemid);
			if($item->component) {
				$url = JRoute::_($item->link.'&Itemid='.$itemid.'&option='.$item->component, false);
			}else{
				$url = JRoute::_($item->link, false);
			}
			$mainframe->redirect($url, JText::_( 'FLEXI_ALERTNOTAUTH' ));
		}

		// load permission
		$permission = FlexicontentHelperPerm::getPerm();
		// check if it's an edit action
		if ($item->getValue('id')) {
			// EDIT action
			$canEditOwn	= $user->authorise('flexicontent.editown', 'com_flexicontent');
			if ( !$permission->CanEdit && !($canEditOwn && ($item->created_by == $user->get('id'))) ) {
				// user isn't authorize to edit
				JError::raiseError( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
			}
		} else {
			// SUBMIT action
			if (!$permission->CanAdd) {
				// user isn't authorize to submit
				JError::raiseError( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
			}
		}

		$perms 	= array();
		$perms['multicat'] = $permission->MultiCat;
		$perms['cantags'] = $permission->CanTags;
		$perms['canparams'] = $permission->CanParams;

		$itemrights = FlexicontentHelperPerm::checkAllItemAccess($user->get('id'), 'item', $item->getValue('id'));
		//$catrights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'category', $item->catid);
		//$rights = array_merge($itemrights, $catrights);//I not sure if it will merged or not?//by enjoyman
		$rights = $itemrights;
		$perms['canedit'] = ( (in_array('editown', $rights) && $item->created_by == $user->get('id')) || (in_array('edit', $rights)) );
		$perms['canpublish'] = ( (in_array('editown.state', $rights) && $item->created_by == $user->get('id')) || (in_array('edit.state', $rights)) );
		$perms['candelete'] = ( (in_array('deleteown', $rights) && $item->created_by == $user->get('id')) || (in_array('delete', $rights)) );
		$perms['canright'] = $permission->CanRights;

		//Add the js includes to the document <head> section
		JHTML::_('behavior.formvalidation');
		JHTML::_('behavior.tooltip');

		// Create the type parameters
		jimport( 'joomla.html.parameter' );
		$tparams = new JParameter($tparams);

		//ensure $used is an array
		if(!is_array($used)){
			$used = array();
		}
		
		//add css file
		$document->addStyleSheet($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css');
		//$document->addStyleSheet($this->baseurl.'/administrator/templates/khepri/css/general.css');
		$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #flexicontent dd { height: 1%; }</style><![endif]-->');
		
		//Get the lists
		$lists = $this->_buildEditLists($perms['multicat']);

		if (FLEXI_FISH) {
		//build languages list
			$lists['languages'] = flexicontent_html::buildlanguageslist('language', '', $item->language, 3);
		} else {
			$item->language = flexicontent_html::getSiteDefaultLang();
		}


		//Load the JEditor object
		//$editor =& JFactory::getEditor();

		//Build the page title string
		$title = $item->getValue('id') ? JText::_( 'FLEXI_EDIT' ) : JText::_( 'FLEXI_NEW' );

		//Set page title
		$document->setTitle($title);

		// Get the menu item object		
		if (is_object($menu)) {
			$menu_params = new JParameter( $menu->params );
			if (!$menu_params->get( 'page_title')) {
				$params->set('page_title',	$title);
			}
		} else {
			$params->set('page_title',	$title);
		}

		//get pathway
		$pathway =& $mainframe->getPathWay();
		$pathway->addItem($title, '');

		//Ensure the row data is safe html
		JFilterOutput::objectHTMLSafe( $item );

		$this->assign('action', 	$uri->toString());

		$this->assignRef('item',		$item);
		$this->assignRef('params',		$params);
		$this->assignRef('lists',		$lists);
		//$this->assignRef('editor',		$editor);
		$this->assignRef('user',		$user);
		$this->assignRef('tags',		$tags);
		$this->assignRef('used',		$used);
		$this->assignRef('fields',		$fields);
		$this->assignRef('tparams', 	$tparams);
		$this->assignRef('perms', 		$perms);
		$this->assignRef('document',	$document);
		$this->assignRef('nullDate', $nullDate);
		
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
		if ($tparams->get('ilayout')) {
			$this->addTemplatePath(JPATH_COMPONENT.DS.'templates'.DS.$tparams->get('ilayout'));
			$this->addTemplatePath(JPATH_SITE.DS.'templates'.DS.$mainframe->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates'.DS.$tparams->get('ilayout'));
		}

		parent::display($tpl);
	}
	
	/**
	 * Creates the item submit form
	 *
	 * @since 1.0
	 */
	function _buildEditLists($multicat = 1)
	{
		global $globalcats;
		//Get the item from the model
		$item 		= & $this->get('Item');
		//get the categories tree
		$categories = $globalcats;
		//get ids of selected categories (edit action)
		$selectedcats = & $this->get( 'Catsselected' );
		$user		=& JFactory::getUser();

		$multiple	= $multicat ? ' multiple="multiple" size="8"' : ''; 
		
		//build selectlist
		$lists = array();
		$lists['cid'] = flexicontent_cats::buildcatselect($categories, 'jform[cid][]', $selectedcats, false, 'class="inputbox required validate-cid"'.$multiple, true);
		
		/*$state = array();
		$state[] = JHTML::_('select.option',  1, JText::_( 'FLEXI_PUBLISHED' ) );
		$state[] = JHTML::_('select.option',  0, JText::_( 'FLEXI_UNPUBLISHED' ) );
		$state[] = JHTML::_('select.option',  -3, JText::_( 'FLEXI_PENDING' ) );
		$state[] = JHTML::_('select.option',  -4, JText::_( 'FLEXI_TO_WRITE' ) );
		$state[] = JHTML::_('select.option',  -5, JText::_( 'FLEXI_IN_PROGRESS' ) );

		$lists['state'] = JHTML::_('select.genericlist', $state, 'state', '', 'value', 'text', $item->state );*/

		$vstate = array();
		$vstate[] = JHTML::_('select.option',  1, JText::_( 'FLEXI_NO' ) );
		$vstate[] = JHTML::_('select.option',  2, JText::_( 'FLEXI_YES' ) );
		$lists['vstate'] = JHTML::_('select.radiolist', $vstate, 'vstate', '', 'value', 'text', 2 );

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

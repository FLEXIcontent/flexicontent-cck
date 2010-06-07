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
	/**
	 * Creates the item page
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		global $mainframe;

		//initialize variables
		$document 	= & JFactory::getDocument();
		$user		= & JFactory::getUser();
		$menus		= & JSite::getMenu();
		$menu    	= $menus->getActive();
		$dispatcher = & JDispatcher::getInstance();
		$params 	= & $mainframe->getParams('com_flexicontent');
		$aid		= (int) $user->get('aid');
		
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
		$item 	= & $this->get('Item');
				
		$iparams	=& $item->parameters;
		$params->merge($iparams);

		// Bind Fields
		$item 	= FlexicontentFields::getFields($item, 'items', $params, $aid);
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
			return JError::raiseError( 404, JText::sprintf( 'MANU ITEM #%d NOT FOUND', $id ) );
		}
		
		$fields		=& $item->fields;
		// Add html to field object trought plugins
/*
		foreach ($fields as $field)
		{
			$results = $dispatcher->trigger('onDisplayField', array( &$field, $row ));
		}
*/

		// Pathway need to be improved
		$cats		= new flexicontent_cats($cid);
		$parents	= $cats->getParentlist();
		$pathway 	=& $mainframe->getPathWay();
		$depth		= $params->get('item_depth', 0);

		for($p = $depth; $p<count($parents); $p++) {
			$pathway->addItem( $this->escape($parents[$p]->title), JRoute::_( FlexicontentHelperRoute::getCategoryRoute($parents[$p]->categoryslug) ) );
		}
		if ($params->get('add_item_pathway', 1)) {
			$pathway->addItem( $this->escape($item->title), JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug)) );
		}
		
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
			$mainframe->addMetaTag('title', $item->title);
		}
		
		if ($mainframe->getCfg('MetaAuthor') == '1') {
			$mainframe->addMetaTag('author', $item->author);
		}

		$mdata = new JParameter($item->metadata);
		$mdata = $mdata->toArray();
		foreach ($mdata as $k => $v)
		{
			if ($v) {
				$document->setMetadata($k, $v);
			}
		}
		
		$limitstart	= JRequest::getVar('limitstart', 0, '', 'int');
		
		// increment the hit counter
		if ($limitstart == 0)
		{
			$model =& $this->getModel();
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

		/*
		 * Handle display events
		 * No need for it currently
		*/
		JPluginHelper::importPlugin('content');
		$item->event = new stdClass();
		$results = $dispatcher->trigger('onPrepareContent', array ($item, &$params, $limitstart));
		$item->event->afterDisplayTitle = trim(implode("\n", $results));

		$results = $dispatcher->trigger('onAfterDisplayTitle', array ($item, &$params, $limitstart));
		$item->event->afterDisplayTitle = trim(implode("\n", $results));

		$results = $dispatcher->trigger('onBeforeDisplayContent', array (& $item, & $params, $limitstart));
		$item->event->beforeDisplayContent = trim(implode("\n", $results));

		$results = $dispatcher->trigger('onAfterDisplayContent', array (& $item, & $params, $limitstart));
		$item->event->afterDisplayContent = trim(implode("\n", $results));

		$print_link = JRoute::_('index.php?view=items&cid='.$item->categoryslug.'&id='.$item->slug.'&pop=1&tmpl=component');

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
	function _displayForm($tpl)
	{
		global $mainframe;

		//Initialize variables
		$dispatcher = & JDispatcher::getInstance();
		$document	=& JFactory::getDocument();
		$user		=& JFactory::getUser();
		$menus		= & JSite::getMenu();
		$menu    	= $menus->getActive();
		$uri     	=& JFactory::getURI();
		$item		=& $this->get('Item');
		$tags 		=& $this->get('Alltags');
		$used 		=& $this->get('Usedtags');
		$params		=& $mainframe->getParams('com_flexicontent');
		$tparams	=& $this->get( 'Typeparams' );
		
		$fields			= & $this->get( 'Extrafields' );
		// Add html to field object trought plugins
		foreach ($fields as $field)
		{
			$results = $dispatcher->trigger('onDisplayField', array( &$field, $item ));
		}
		// if it's an edit action, redirect it
		if ($item->id) {
			$mainframe->redirect('index.php', JText::_( 'ALERTNOTAUTH' ));
		}
		
		if (!$user->get('id')) {
			$mainframe->redirect('index.php', JText::_( 'ALERTNOTAUTH' ));
		}
		
		$perms = array();
		if (FLEXI_ACCESS) {
			$perms['multicat'] 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'multicat', 'users', $user->gmid) : 1;
			$perms['cantags'] 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'usetags', 'users', $user->gmid) : 1;
			$perms['canparams'] = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'paramsitems', 'users', $user->gmid) : 1;
			$perms['cansubmit']	= ($user->gid < 20) ? ((FAccess::checkComponentAccess('com_content', 'submit', 'users', $user->gmid)) || (FAccess::checkAllContentAccess('com_content','add','users',$user->gmid,'content','all'))) : 1;
			$perms['canpublish']	= ($user->gid < 22) ? (
										(FAccess::checkComponentAccess('com_content', 'publish', 'users', $user->gmid)) || 
										(FAccess::checkComponentAccess('com_content', 'publishown', 'users', $user->gmid)) || 
										(FAccess::checkAllContentAccess('com_content','publish','users',$user->gmid,'content','all')) || 
										(FAccess::checkAllContentAccess('com_content','publishown','users',$user->gmid,'content','all'))
														   ) : 1;
		} else {
			$perms['multicat']		= 1;
			$perms['cantags'] 		= 1;
			$perms['canparams'] 	= 1;
			$perms['cansubmit']		= ($user->gid >= 18);
			$perms['canpublish']	= ($user->gid >= 21);
		}

		if (!$perms['cansubmit']) {
			$mainframe->redirect('index.php', JText::_( 'ALERTNOTAUTH' ));
		}

		//Add the js includes to the document <head> section
		JHTML::_('behavior.formvalidation');
		JHTML::_('behavior.tooltip');

		// Create the type parameters
		$tparams = new JParameter($tparams);

		//ensure $used is an array
		if(!is_array($used)){
			$used = array();
		}
		
		//add css file
		$document->addStyleSheet($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css');
		$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #flexicontent dd { height: 1%; }</style><![endif]-->');
		
		//Get the lists
		$lists = $this->_buildEditLists($perms['multicat']);

		//Load the JEditor object
		$editor =& JFactory::getEditor();

		//Build the page title string
		$title = $item->id ? JText::_( 'FLEXI_EDIT' ) : JText::_( 'FLEXI_NEW' );

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

		// Unify the introtext and fulltext fields and separated the fields by the readmore tag
		if (JString::strlen($item->fulltext) > 1) {
			$item->text = $item->introtext."<hr id=\"system-readmore\" />".$item->fulltext;
		} else {
			$item->text = $item->introtext;
		}

		//Ensure the row data is safe html
		JFilterOutput::objectHTMLSafe( $item );

		$this->assign('action', 	$uri->toString());

		$this->assignRef('item',	$item);
		$this->assignRef('params',$params);
		$this->assignRef('lists',	$lists);
		$this->assignRef('editor',	$editor);
		$this->assignRef('user',	$user);
		$this->assignRef('tags',	$tags);
		$this->assignRef('used',	$used);
		$this->assignRef('fields',	$fields);
		$this->assignRef('tparams', $tparams);
		$this->assignRef('perms', 	$perms);
		$this->assignRef('document', $document);

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
		$lists['cid'] = flexicontent_cats::buildcatselect($categories, 'cid[]', $selectedcats, false, 'class="inputbox required validate-cid"'.$multiple, true);
		
		$state = array();
		$state[] = JHTML::_('select.option',  1, JText::_( 'FLEXI_PUBLISHED' ) );
		$state[] = JHTML::_('select.option',  0, JText::_( 'FLEXI_UNPUBLISHED' ) );
		$state[] = JHTML::_('select.option',  -3, JText::_( 'FLEXI_PENDING' ) );
		$state[] = JHTML::_('select.option',  -4, JText::_( 'FLEXI_TO_WRITE' ) );
		$state[] = JHTML::_('select.option',  -5, JText::_( 'FLEXI_IN_PROGRESS' ) );

		$lists['state'] = JHTML::_('select.genericlist', $state, 'state', '', 'value', 'text', $item->state );

		return $lists;
	}
}
?>
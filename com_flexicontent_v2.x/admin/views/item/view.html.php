<?php
/**
 * @version 1.5 stable $Id: view.html.php 185 2010-04-04 07:53:52Z emmanuel.danan $
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
 * View class for the FLEXIcontent item screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewItem extends JView {
	function display($tpl = null) {
		global $globalcats;
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');
		$permission = FlexicontentHelperPerm::getPerm();

		//Load pane behavior
		jimport('joomla.html.pane');

		//initialise variables
		$document	= & JFactory::getDocument();
		$user 		= & JFactory::getUser();
		$db  		= & JFactory::getDBO();
		$dispatcher	= & JDispatcher::getInstance();
		$cparams 	= & JComponentHelper::getParams('com_flexicontent');
		
		if(!JPluginHelper::isEnabled('system', 'jquerysupport')) {
			JHTML::_('behavior.mootools');
			$document->addScript('components/com_flexicontent/assets/js/jquery-1.6.2.min.js');
		}
		// The 'noConflict()' statement is inside the above jquery file, to make sure it executed immediately
		//$document->addCustomTag('<script>jQuery.noConflict();</script>');

		JHTML::_('behavior.tooltip');

		$nullDate 		= $db->getNullDate();

		//get vars
		$cid 		= JRequest::getVar( 'cid' );
		$cid		= is_array($cid)?$cid[0]:$cid;

		//add css to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');
		$document->addScript( JURI::base().'components/com_flexicontent/assets/js/itemscreen.js' );
		//add js function to overload the joomla submitform
		$document->addScript('components/com_flexicontent/assets/js/admin.js');
		$document->addScript('components/com_flexicontent/assets/js/validate.js');

		//create the toolbar
		if ( $cid ) {
			JToolBarHelper::title( JText::_( 'FLEXI_EDIT_ITEM' ), 'itemedit' );

		} else {
			JToolBarHelper::title( JText::_( 'FLEXI_NEW_ITEM' ), 'itemadd' );
		}
		JToolBarHelper::apply('items.apply');
		JToolBarHelper::save('items.save');
		JToolBarHelper::custom( 'items.saveandnew', 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );
		JToolBarHelper::cancel('items.cancel');

		//Get data from the model
		$model			= & $this->getModel();
		$form		= $this->get('Form');
		$version 	= JRequest::getVar( 'version', 0, 'request', 'int' );
		$lastversion = FLEXIUtilities::getLastVersions($form->getValue("id"), true);
		if($version==0)
			$currentversion = $lastversion;
		else
			$currentversion = $version;
		//if(!$cparams->get('use_versioning', 1))
		//	JRequest::setVar( 'version', 0);
		$subscribers 	= & $this->get( 'SubscribersCount' );
		$selectedcats	= & $this->get( 'Catsselected' );
		$fields			= & $this->get( 'Extrafields' );
		//$types			= & $this->get( 'Typeslist' );
		$typesselected	= & $this->get( 'Typesselected' );
		$versioncount	= & $this->get( 'VersionCount' );
		$versionsperpage = $cparams->get('versionsperpage', 10);
		$pagecount	= (int)ceil($versioncount/$versionsperpage);
		$allversions		= & $model->getVersionList();//get all versions.
		$current_page = 1;
		$k=1;
		foreach($allversions as $v) {
			if( $k && (($k%$versionsperpage)==0) ) 
				$current_page++;
			if($v->nr==$currentversion) break;
			$k++;
		}

		$versions		= & $model->getVersionList(($current_page-1)*$versionsperpage, $versionsperpage);
		$tparams		= & $this->get( 'Typeparams' );
		//$languages		= & $this->get( 'Languages' );
		//$lastversion 	= FLEXIUtilities::getLastVersions($row->id, true);
		$categories 	= $globalcats;

		$usedtags = array();
		if ($cid) {
			$usedtagsA 	= & $fields['tags']->value;
			$usedtags 	= $model->getUsedtags($usedtagsA);
		}

		// Add html to field object trought plugins
		foreach ($fields as $field) {
			$results = $dispatcher->trigger('onDisplayField', array( &$field, &$form ));
		}
		
		$permission = FlexicontentHelperPerm::getPerm();
		if (!$permission->CanParams) 	$document->addStyleDeclaration('#det-pane {display:none;}');

		// set default values
		$canPublish 	= 1;
		$canPublishOwn	= 1;
		$canRight 		= 1;

		if ($form->getValue("id")) {
			// fail if checked out not by 'me'	
			//if ($model->isCheckedOut( $user->get('id') )) {
			//	JError::raiseWarning( 'SOME_ERROR_CODE', $row->title.' '.JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
			//	$mainframe->redirect( 'index.php?option=com_flexicontent&view=items' );
			//}
			if(!JAccess::check($user->id, 'core.admin', 'root.1')) {
				//$rights = FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $row->id, $form->getValue("catid"));
				$rights 		= FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $item->id);
				$canEdit 		= in_array('edit', $rights);
				$canEditOwn		= (in_array('editown', $rights) && ($row->created_by == $user->id));
				$canPublish 	= in_array('publish', $rights);
				$canPublishOwn	= (in_array('publishown', $rights) && ($row->created_by == $user->id));
				$canRight 		= in_array('right', $rights);

				// check if the user can really edit the item
				if ($canEdit || $canEditOwn || ($lastversion < 3)) {
				} else {
					$mainframe->redirect('index.php?option=com_flexicontent&view=items', JText::_( 'FLEXI_NO_ACCESS' ));
				}
			}
		}

		//clean data
		JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES );

		//build selectlists
		$lists = array();
		
		// Create the type parameters
		$tparams = new JParameter($tparams);

		// Handle item templates parameters
		$themes		= flexicontent_tmpl::getTemplates();
		$tmpls		= $themes->items;

		//build state list
		/*$state[] = JHTML::_('select.option',  -4, JText::_( 'FLEXI_TO_WRITE' ) );
		$state[] = JHTML::_('select.option',  -3, JText::_( 'FLEXI_PENDING' ) ); 
		$state[] = JHTML::_('select.option',  -5, JText::_( 'FLEXI_IN_PROGRESS' ) );
		$state[] = JHTML::_('select.option',   1, JText::_( 'FLEXI_PUBLISHED' ) );
		$state[] = JHTML::_('select.option',   0, JText::_( 'FLEXI_UNPUBLISHED' ) );
		$state[] = JHTML::_('select.option',  -1, JText::_( 'FLEXI_ARCHIVED' ) );
		//if(!$canPublish)
		//	$row->state = $row->state ? $row->state : -4;
		$lists['state'] = JHTML::_('select.genericlist',   $state, 'state', '', 'value', 'text', $form->getValue("state") );
		*/
		
		//build version state list
		$vstate[] = JHTML::_('select.option',  1, JText::_( 'FLEXI_NO' ) );
		$vstate[] = JHTML::_('select.option',  2, JText::_( 'FLEXI_YES' ) ); 
		$lists['vstate'] = JHTML::_('select.radiolist', $vstate, 'vstate', '', 'value', 'text', 1 );
		/*if (FLEXI_FISH) {
		//build languages list
			$lists['languages'] = flexicontent_html::buildlanguageslist('language', '', $row->language, 3);
		} else {
			$row->language = flexicontent_html::getSiteDefaultLang();
		}*/
		switch ($form->getValue("state")) {
			case 0:
			$published = JText::_( 'FLEXI_UNPUBLISHED' );
			break;
			case 1:
			$published = JText::_( 'FLEXI_PUBLISHED' );
			break;
			case -1:
			$published = JText::_( 'FLEXI_ARCHIVED' );
			break;
			case -3:
			$published = JText::_( 'FLEXI_PENDING' );
			break;
			case -5:
			$published = JText::_( 'FLEXI_IN_PROGRESS' );
			break;
			case -4:
			default:
			$published = JText::_( 'FLEXI_TO_WRITE' );
			break;
		}
		//assign data to template
		$this->assignRef('document'     	, $document);
		$this->assignRef('lists'      		, $lists);
		$this->assignRef('canPublish'   	, $canPublish);
		$this->assignRef('canPublishOwn'	, $canPublishOwn);
		$this->assignRef('canRight'			, $canRight);
		$this->assignRef('published'		, $published);
		$this->assignRef('nullDate'			, $nullDate);
		$this->assignRef('form'				, $form);
		$this->assignRef('subscribers'		, $subscribers);
		$this->assignRef('fields'			, $fields);
		$this->assignRef('versions'			, $versions);
		$this->assignRef('pagecount'		, $pagecount);
		$this->assignRef('version'			, $version);
		$this->assignRef('lastversion'		, $lastversion);
		$this->assignRef('cparams'			, $cparams);
		$this->assignRef('tparams'			, $tparams);
		$this->assignRef('tmpls'			, $tmpls);
		$this->assignRef('usedtags'			, $usedtags);
		$this->assignRef('permission'		, $permission);
		$this->assignRef('current_page'		, $current_page);
		$this->assignRef('permission'		, $permission);
		$this->assignRef('fieldtype'		, $typesselected);

		parent::display($tpl);
	}
}
?>

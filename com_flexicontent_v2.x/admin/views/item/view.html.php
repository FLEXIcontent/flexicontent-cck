<?php
/**
 * @version 1.5 stable $Id: view.html.php 1088 2012-01-08 16:40:44Z ggppdk $
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
require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.fields.php');

/**
 * View class for the FLEXIcontent item screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewItem extends JView {

	function display($tpl = null)
	{
		global $globalcats;

		//Load pane behavior
		jimport('joomla.html.pane');
		//Get the route helper for the preview function
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.fields.php');
		
		//initialise variables
		$mainframe	= & JFactory::getApplication();
		$option = JRequest::getVar('option');
		$document	= & JFactory::getDocument();
		$user 		= & JFactory::getUser();
		$db  		= & JFactory::getDBO();
		$dispatcher = & JDispatcher::getInstance();
		$cparams 	= & JComponentHelper::getParams('com_flexicontent');
		$bar 		= & JToolBar::getInstance('toolbar');

		if(!JPluginHelper::isEnabled('system', 'jquerysupport')) {
			JHTML::_('behavior.mootools');
			$document->addScript('components/com_flexicontent/assets/js/jquery-1.6.2.min.js');
		}
		// The 'noConflict()' statement is inside the above jquery file, to make sure it executed immediately
		//$document->addCustomTag('<script>jQuery.noConflict();</script>');
		
		//JHTML::_('behavior.formvalidation'); // we use custom validation class
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

		//Get data from the model
		$model	= & $this->getModel();
		$form	= $this->get('Form');
		$row = & $form;
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
		
		// ******************
		// Create the toolbar
		// ******************
		
		// SET toolbar title
		if ( $cid )  
		{
			JToolBarHelper::title( JText::_( 'FLEXI_EDIT_ITEM' ), 'itemedit' );   // Editing existing item
		} else {
			JToolBarHelper::title( JText::_( 'FLEXI_NEW_ITEM' ), 'itemadd' );     // Creating new item
		}
		
		// Add a preview button for existing item
		if ( $cid )  
		{
			$autologin		= $cparams->get('autoflogin', 1) ? '&fcu='.$user->username . '&fcp='.$user->password : '';
			$base 			= str_replace('administrator/', '', JURI::base());
			if (FLEXI_J16GE) {
				$previewlink = $base . JRoute::_(FlexicontentHelperRoute::getItemRoute($form->getValue('id').':'.$form->getValue('alias'), $categories[$form->getValue('catid')]->slug)) . $autologin;
			} else {
				$previewlink = $base . JRoute::_(FlexicontentHelperRoute::getItemRoute($row->id.':'.$row->alias, $categories[$row->catid]->slug)) . $autologin;
			}
			$bar->appendButton( 'Custom', '<a class="preview" href="'.$previewlink.'" target="_blank"><span title="'.JText::_('Preview').'" class="icon-32-preview"></span>'.JText::_('Preview').'</a>', 'preview' );
		} 
		
		// Common Buttons
		if (FLEXI_J16GE) {
			JToolBarHelper::apply('items.apply');
			JToolBarHelper::save('items.save');
			JToolBarHelper::custom( 'items.saveandnew', 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );
			JToolBarHelper::cancel('items.cancel');
		} else {
			JToolBarHelper::apply();
			JToolBarHelper::save();
			JToolBarHelper::custom( 'saveandnew', 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );
			JToolBarHelper::cancel();
		}
		
		// Get fields and create their edit html, also customize core fields
		$fields = & $this->get( 'Extrafields' );
		foreach ($fields as $field)
		{
			$fieldname = $field->iscore ? 'core' : $field->field_type;
			// -- SET a type specific label & description for the core field and also retrieve any other ITEM TYPE customizations (must call this manually when editing)
			if ($field->iscore) FlexicontentFields::loadFieldConfig($field, $row);
			// Create field 's editing HTML
			FLEXIUtilities::call_FC_Field_Func($fieldname, 'onDisplayField', array( &$field, &$row ));
		}
		
		// Tags used by the item
		$usedtags = array();
		if ($cid) {
			//$usedtags 	= $model->getusedtags($cid);
			if (FLEXI_J16GE) {
				$usedtagsA 	= & $fields['tags']->value;
				$usedtags 	= $model->getUsedtagsData($usedtagsA);
			} else {
				$usedtagsA 	= & $this->get( 'UsedtagsArray' );
				$usedtags 	= $model->getUsedtags($usedtagsA);
			}
		}
		
		if (FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$CanParams  = $permission->CanParams;
			$CanVersion = $permission->CanVersion;
			$CanUseTags = $permission->CanUseTags;
		} else if (FLEXI_ACCESS) {
			$CanParams	 = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'paramsitems', 'users', $user->gmid) : 1;
			$CanVersion	 = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'versioning', 'users', $user->gmid) : 1;
			$CanUseTags	 = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'usetags', 'users', $user->gmid) : 1;
		} else {
			$CanParams	= 1;
			$CanVersion	= 1;
			$CanUseTags = 1;
		}
		if (!$CanParams) 	$document->addStyleDeclaration('#det-pane {display:none;}');

		// set default values
		$canPublish 		= 1;
		$canPublishOwn	= 1;
		$canRight 			= 1;
		
		$is_edit = FLEXI_J16GE ? $form->getValue("id") : $row->id;
		
		if ($is_edit) {
			// First, check that user can edit the item
			if (FLEXI_J16GE) {
				$rights				= FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $form->getValue('id'));
				$canEdit			= in_array('edit', $rights);
				$canEditOwn		= (in_array('editown', $rights) && ($form->getValue("created_by") == $user->id));
				$canPublish		= in_array('edit.state', $rights);
				$canPublishOwn= (in_array('edit.state.own', $rights) && ($form->getValue("created_by") == $user->id));
				$canRight			= $permission->CanConfig;
			} else if (FLEXI_ACCESS) {
				$rights				= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $row->id, $row->catid);
				$canEdit			= in_array('edit', $rights);
				$canEditOwn		= (in_array('editown', $rights) && ($row->created_by == $user->id));
				$canPublish		= in_array('publish', $rights);
				$canPublishOwn= (in_array('publishown', $rights) && ($row->created_by == $user->id));
				$canRight			= in_array('right', $rights);
			} else {
				$canEdit			= $user->authorize('com_content', 'edit', 'content', 'all');
				$canEditOwn		= 0;
				$canPublish		= $user->authorize('com_content', 'publish', 'content', 'all');
				$canPublishOwn= 0;
			}
			$has_edit = $canEdit || $canEditOwn || ($lastversion < 3);
			if (!$has_edit) {
				$mainframe->redirect('index.php?option=com_flexicontent&view=items', JText::sprintf( 'FLEXI_NO_ACCESS_EDIT', JText::_('FLEXI_ITEM') ));
			}
			
			// Second, check if item is already edited by a user and check it out (this fails if edit by any user other than the current user)
			if ($model->isCheckedOut( $user->get('id') )) {
				JError::raiseWarning( 'SOME_ERROR_CODE', $row->title.' '.JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
				$mainframe->redirect( 'index.php?option=com_flexicontent&view=items' );
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
		//$vstate[] = JHTML::_('select.option',  1, JText::_( 'FLEXI_NO' ) );
		//$vstate[] = JHTML::_('select.option',  2, JText::_( 'FLEXI_YES' ) ); 
		//$lists['vstate'] = JHTML::_('select.radiolist', $vstate, 'vstate', '', 'value', 'text', 2 );
		if (FLEXI_FISH || FLEXI_J16GE) {
			//build languages list
			$lists['languages'] = flexicontent_html::buildlanguageslist('jform[language]', '', $form->getValue("language"), 3);
		} else {
			$row->language = flexicontent_html::getSiteDefaultLang();
		}
		
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
		$this->assignRef('document'     , $document);
		$this->assignRef('lists'      	, $lists);
		$this->assignRef('form'					, $form);
		$this->assignRef('canPublish'   , $canPublish);
		$this->assignRef('canPublishOwn', $canPublishOwn);
		$this->assignRef('canRight'			, $canRight);
		$this->assignRef('published'		, $published);
		$this->assignRef('nullDate'			, $nullDate);
		$this->assignRef('subscribers'	, $subscribers);
		$this->assignRef('fields'				, $fields);
		$this->assignRef('versions'			, $versions);
		$this->assignRef('pagecount'		, $pagecount);
		$this->assignRef('version'			, $version);
		$this->assignRef('lastversion'	, $lastversion);
		$this->assignRef('cparams'			, $cparams);
		$this->assignRef('tparams'			, $tparams);
		$this->assignRef('tmpls'				, $tmpls);
		$this->assignRef('usedtags'			, $usedtags);
		$this->assignRef('CanVersion'		, $CanVersion);
		$this->assignRef('CanUseTags'		, $CanUseTags);
		$this->assignRef('current_page'	, $current_page);
		$this->assignRef('permission'		, $permission);
		$this->assignRef('fieldtype'		, $typesselected);

		parent::display($tpl);
	}
}
?>

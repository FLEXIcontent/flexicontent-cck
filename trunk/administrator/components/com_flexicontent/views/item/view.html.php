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
		
		//initialise variables
		$mainframe	= & JFactory::getApplication();
		$editor 	= & JFactory::getEditor();
		$document	= & JFactory::getDocument();
		$user 		= & JFactory::getUser();
		$db  		= & JFactory::getDBO();
		$pane 		= & JPane::getInstance('sliders');
		$dispatcher = & JDispatcher::getInstance();
		$cparams 	= & JComponentHelper::getParams('com_flexicontent');
		$bar 		= & JToolBar::getInstance('toolbar');

		if(!JPluginHelper::isEnabled('system', 'jquerysupport')) {
			JHTML::_('behavior.mootools');
			$document->addScript('components/com_flexicontent/assets/js/jquery-1.6.2.min.js');
		}
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
		$model			= & $this->getModel();
		$row     		= & $this->get( 'Item' );
		$version 	= JRequest::getVar( 'version', 0, 'request', 'int' );
		$lastversion = FLEXIUtilities::getLastVersions($row->id, true);
		if($version==0)
			JRequest::setVar( 'version', $version = $lastversion);
		if(!$cparams->get('use_versioning', 1))
			JRequest::setVar( 'version', 0);
		$subscribers 	= & $this->get( 'SubscribersCount' );
		$selectedcats	= & $this->get( 'Catsselected' );
		$fields			= & $this->get( 'Extrafields' );
		$types			= & $this->get( 'Typeslist' );
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
			if($v->nr==$version) break;
			$k++;
		}

		$versions		= & $model->getVersionList(($current_page-1)*$versionsperpage, $versionsperpage);
		$tparams		= & $this->get( 'Typeparams' );
		$languages		= & $this->get( 'Languages' );
		$categories 	= $globalcats;

		//create the toolbar
		if ( $cid ) 
		{
			JToolBarHelper::title( JText::_( 'FLEXI_EDIT_ITEM' ), 'itemedit' );
			$autologin		= $cparams->get('autoflogin', 1) ? '&fcu='.$user->username . '&fcp='.$user->password : '';
			$base 			= str_replace('administrator/', '', JURI::base());
			$previewlink 	= $base . JRoute::_(FlexicontentHelperRoute::getItemRoute($row->id.':'.$row->alias, $categories[$row->catid]->slug)) . $autologin;
			// Add a preview button
			$bar->appendButton( 'Custom', '<a class="preview" href="'.$previewlink.'" target="_blank"><span title="'.JText::_('Preview').'" class="icon-32-preview"></span>'.JText::_('Preview').'</a>', 'preview' );
		} 
		else 
		{
			JToolBarHelper::title( JText::_( 'FLEXI_NEW_ITEM' ), 'itemadd' );
		}
		JToolBarHelper::apply();
		JToolBarHelper::save();
		JToolBarHelper::custom( 'saveandnew', 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );
		JToolBarHelper::cancel();

		$usedtags = array();
		if ($cid) {
			//$usedtags 	= $model->getusedtags($cid);
			$usedtagsA 	= & $this->get( 'UsedtagsArray' );
			$usedtags 	= $model->getUsedtags($usedtagsA);
		}
		// Add html to field object trought plugins
		foreach ($fields as $field)
		{
			//$results = $dispatcher->trigger('onDisplayField', array( &$field, &$row ));
			$fieldname = $field->iscore ? 'core' : $field->field_type;
			FLEXIUtilities::call_FC_Field_Func($fieldname, 'onDisplayField', array( &$field, &$row ));
		}
		
		if (FLEXI_ACCESS) {
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
		$canPublish 	= 1;
		$canPublishOwn	= 1;
		$canRight 		= 1;

		if ($row->id) {
			// fail if checked out not by 'me'	
			if ($model->isCheckedOut( $user->get('id') )) {
				JError::raiseWarning( 'SOME_ERROR_CODE', $row->title.' '.JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
				$mainframe->redirect( 'index.php?option=com_flexicontent&view=items' );
			}
			if (FLEXI_ACCESS && ($user->gid < 25)) {
				$rights = FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $row->id, $row->catid);
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
		if (FLEXI_ACCESS && ($user->gid < 25)) {
			if ((FAccess::checkAllContentAccess('com_content','add','users',$user->gmid,'content','all')) || (FAccess::checkAllContentAccess('com_content','edit','users',$user->gmid,'content','all'))) {
				$lists['cid'] = flexicontent_cats::buildcatselect($categories, 'cid[]', $selectedcats, false, 'multiple="multiple" size="20" class="required mcat"', true, false);
				$lists['catid'] = flexicontent_cats::buildcatselect($categories, 'catid', $row->catid, 2, 'class="scat"', true, false);
			} else {
				$lists['cid'] = flexicontent_cats::buildcatselect($categories, 'cid[]', $selectedcats, false, 'multiple="multiple" size="20" class="required mcat"', true);
				$lists['catid'] = flexicontent_cats::buildcatselect($categories, 'catid', $row->catid, 2, 'class="scat"', true);
			}
		} else {
			$lists['cid'] = flexicontent_cats::buildcatselect($categories, 'cid[]', $selectedcats, false, 'multiple="multiple" size="20" class="required mcat"', true);
			$lists['catid'] = flexicontent_cats::buildcatselect($categories, 'catid', $row->catid, 2, 'class="scat"', true);
		}

		//buid types selectlist
		$lists['type'] = flexicontent_html::buildtypesselect($types, 'type_id', $typesselected, 1, 'class="required"', true );
	
		// build granular access list
		if (FLEXI_ACCESS) {
			if (isset($user->level)) {
			$lists['access'] = FAccess::TabGmaccess( $row, 'item', 1, 0, 0, 1, 0, 1, 0, 1, 1 );
			} else {
				$lists['access'] = JText::_('Your profile has been changed, please logout to access to the permissions');
			}
		}
		
		// Create the type parameters
		$tparams = new JParameter($tparams);
		// Create the form
		if (FLEXI_ACCESS) {
			$form = new JParameter('', JPATH_COMPONENT.DS.'models'.DS.'item2.xml');
		} else {
			$form = new JParameter('', JPATH_COMPONENT.DS.'models'.DS.'item.xml');
		}

		// Details Group
		$active = (intval($row->created_by) ? intval($row->created_by) : $user->get('id'));
		if (!FLEXI_ACCESS) {
			$form->set('access', $row->access);
		}
		$form->set('created_by', $active);
		$form->set('created_by_alias', $row->created_by_alias);
		$form->set('created', JHTML::_('date', $row->created, '%Y-%m-%d %H:%M:%S'));
		$form->set('publish_up', JHTML::_('date', $row->publish_up, '%Y-%m-%d %H:%M:%S'));
		$form->set('publish_up', JHTML::_('date', $row->publish_up, '%Y-%m-%d %H:%M:%S'));
		if (JHTML::_('date', $row->publish_down, '%Y') <= 1969 || $row->publish_down == $db->getNullDate()) {
			$form->set('publish_down', JText::_( 'FLEXI_NEVER' ));
		} else {
			$form->set('publish_down', JHTML::_('date', $row->publish_down, '%Y-%m-%d %H:%M:%S'));
		}

		// Advanced Group
		$form->loadINI($row->attribs);

		// Metadata Group
		$form->set('description', $row->metadesc);
		$form->set('keywords', $row->metakey);
		$form->loadINI($row->metadata);

		// Handle item templates parameters
		$themes		= flexicontent_tmpl::getTemplates();
		$tmpls		= $themes->items;
		foreach ($tmpls as $tmpl) {
			$tmpl->params->loadINI($row->attribs);
		}

		//build state list
		$state[] = JHTML::_('select.option',  -4, JText::_( 'FLEXI_TO_WRITE' ) );
		$state[] = JHTML::_('select.option',  -3, JText::_( 'FLEXI_PENDING' ) ); 
		$state[] = JHTML::_('select.option',  -5, JText::_( 'FLEXI_IN_PROGRESS' ) );
		$state[] = JHTML::_('select.option',   1, JText::_( 'FLEXI_PUBLISHED' ) );
		$state[] = JHTML::_('select.option',   0, JText::_( 'FLEXI_UNPUBLISHED' ) );
		$state[] = JHTML::_('select.option',  -1, JText::_( 'FLEXI_ARCHIVED' ) );
		if(!$canPublish)
			$row->state = $row->state ? $row->state : -4;
		$lists['state'] = JHTML::_('select.genericlist',   $state, 'state', '', 'value', 'text', $row->state );
		
		//build version state list
		$vstate[] = JHTML::_('select.option',  1, JText::_( 'FLEXI_NO' ) );
		$vstate[] = JHTML::_('select.option',  2, JText::_( 'FLEXI_YES' ) ); 
		$lists['vstate'] = JHTML::_('select.radiolist', $vstate, 'vstate', '', 'value', 'text', 1 );
		if (FLEXI_FISH) {
		//build languages list
			$lists['languages'] = flexicontent_html::buildlanguageslist('language', '', $row->language, 3);
		} else {
			$row->language = flexicontent_html::getSiteDefaultLang();
		}
		
		switch ($row->state) {
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
		$this->assignRef('row'      		, $row);
		$this->assignRef('canPublish'   	, $canPublish);
		$this->assignRef('canPublishOwn'	, $canPublishOwn);
		$this->assignRef('canRight'			, $canRight);
		$this->assignRef('published'		, $published);
		$this->assignRef('editor'			, $editor);
		$this->assignRef('pane'				, $pane);
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
		$this->assignRef('CanVersion'		, $CanVersion);
		$this->assignRef('CanUseTags'		, $CanUseTags);
		$this->assignRef('current_page'		, $current_page);

		parent::display($tpl);
	}
}
?>

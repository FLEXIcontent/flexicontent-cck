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
require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.fields.php');
require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');

/**
 * HTML View class for the Items View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewItem extends JView
{

	
	/**
	 * Creates the item page
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		global $globalcats;
		
		//Load pane behavior
		jimport('joomla.html.pane');
		
		// Initialize variables
		$mainframe  = &JFactory::getApplication();
		$dispatcher = & JDispatcher::getInstance();
		$document   = & JFactory::getDocument();
		$cparams    = & JComponentHelper::getParams('com_flexicontent');
		$option     = JRequest::getVar('option');
		$user       = & JFactory::getUser();
		$db         = & JFactory::getDBO();
		$nullDate   = $db->getNullDate();
		$bar    = & JToolBar::getInstance('toolbar');
		if (!FLEXI_J16GE) {
			$editor 	= & JFactory::getEditor();
			$pane 		= & JPane::getInstance('sliders');
		}
		
		if(!JPluginHelper::isEnabled('system', 'jquerysupport')) {
			JHTML::_('behavior.mootools');
			$document->addScript('components/com_flexicontent/assets/js/jquery-1.6.2.min.js');
		}
		// The 'noConflict()' statement is inside the above jquery file, to make sure it executed immediately
		//$document->addCustomTag('<script>jQuery.noConflict();</script>');
		
		//JHTML::_('behavior.formvalidation'); // we use custom validation class
		JHTML::_('behavior.tooltip');
		
		//add css to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');
		$document->addScript( JURI::base().'components/com_flexicontent/assets/js/itemscreen.js' );
		//add js function to overload the joomla submitform
		$document->addScript('components/com_flexicontent/assets/js/admin.js');
		$document->addScript('components/com_flexicontent/assets/js/validate.js');

		//Get data from the model
		$model = & $this->getModel();
		$row   = & $this->get('Item');
		if (FLEXI_J16GE) {
			$form  = & $this->get('Form');
		}
		
		// Get item id and new flag
		$cid = $model->getId();
		$isnew = ! $cid;
		
		$version = $row->loaded_version;
		$lastversion = FLEXIUtilities::getLastVersions($row->id, true);
		
		$subscribers 	= & $this->get( 'SubscribersCount' );
		if (!FLEXI_J16GE) {
			$types			= & $this->get( 'Typeslist' );
			$languages = & $this->get( 'Languages' );
		}
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

		$versions  = & $model->getVersionList(($current_page-1)*$versionsperpage, $versionsperpage);
		$tparams   = & $this->get( 'Typeparams' );
		$tparams   = new JParameter($tparams);
		$categories = $globalcats;
		
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
			$previewlink = $base . JRoute::_(FlexicontentHelperRoute::getItemRoute($row->id.':'.$row->alias, $categories[$row->catid]->slug)) . $autologin;
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
				FlexicontentFields::loadFieldConfig($field, $row);
			} else {
				// Create field 's editing HTML
				FLEXIUtilities::call_FC_Field_Func($field->field_type, 'onDisplayField', array( &$field, &$row ));
			}
			if ($field->field_type == 'maintext')
			{
				// Create main text field, via calling the display function of the textarea field (will also check for tabs)
				// We use the text created by the model and not the text retrieved by the CORE plugin code, which maybe overwritten with JoomFish data
				
				if ( isset($row->item_translations) ) {
					$itemlang = substr($row->language ,0,2);
					foreach ($row->item_translations as $lang_id => $t)	{
						if ($itemlang == $t->shortcode) continue;
						$field->name = array('jfdata',$t->shortcode,'text');
						$field->value[0] = html_entity_decode($t->fields->text->value, ENT_QUOTES, 'UTF-8');
						FLEXIUtilities::call_FC_Field_Func('textarea', 'onDisplayField', array(&$field, &$row) );
						$t->fields->text->tab_labels = $field->tab_labels;
						$t->fields->text->html = $field->html;
						unset( $field->tab_labels );
						unset( $field->html );
					}
				}
				$field->name = 'text';
				$field->value[0] = html_entity_decode(FLEXI_J16GE ? $row->text: $row->text, ENT_QUOTES, 'UTF-8');
				FLEXIUtilities::call_FC_Field_Func('textarea', 'onDisplayField', array(&$field, &$row) );
			}
		}
		
		// Tags used by the item
		$usedtagsIds  = & $this->get( 'UsedtagsIds' );  // NOTE: This will normally return the already set versioned value of tags ($row->tags)
		//$usedtagsIds 	= $isnew ? array() : $fields['tags']->value;
		$usedtags = $model->getUsedtagsData($usedtagsIds);
		
		// Categories used by the item
		$selectedcats	= & $this->get( 'Catsselected' );   // NOTE: This will normally return the already set versioned value of categories ($row->categories)
		//$selectedcats 	= $isnew ? array() : $fields['categories']->value;
		
		//echo "<br>row->tags: "; print_r($row->tags);
		//echo "<br>usedtagsIds: "; print_r($usedtagsIds);
		//echo "<br>usedtags (data): "; print_r($usedtags);
		
		//echo "<br>row->categories: "; print_r($row->categories);
		//echo "<br>selectedcats: "; print_r($selectedcats);
		
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
		
		$is_edit = (boolean) $row->id;
		
		if ($is_edit) {
			// First, check that user can edit the item
			if (FLEXI_J16GE) {
				$rights				= FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $row->id);
				$canEdit			= in_array('edit', $rights);
				$canEditOwn		= in_array('edit.own', $rights) && $row->created_by == $user->id;
				$canPublish		= in_array('edit.state', $rights);
				$canPublishOwn= in_array('edit.state.own', $rights) && $row->created_by == $user->id;
				$canRight			= $permission->CanConfig;
			} else if ($user->gid >= 25) {
				$canEdit = $canEditOwn = $canPublish = $canPublishOwn	= $canRight = true;
			} else if (FLEXI_ACCESS) {
				$rights				= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $row->id, $row->catid);
				$canEdit			= in_array('edit', $rights);
				$canEditOwn		= in_array('editown', $rights) && $row->created_by == $user->id;
				$canPublish		= in_array('publish', $rights);
				$canPublishOwn= in_array('publishown', $rights) && $row->created_by == $user->id;
				$canRight			= in_array('right', $rights);
			} else {
				$canEdit = $canEditOwn = $canPublish = $canPublishOwn = ($user->id!=0);
				// Redudant check, all backend users have these permissions (managers, admininstrators, super administrators)
				//$canEdit			= $user->authorize('com_content', 'edit', 'content', 'all');
				//$canEditOwn		= $user->authorize('com_content', 'edit', 'content', 'own') && $row->created_by == $user->id;
				//$canPublish		= $user->authorize('com_content', 'publish', 'content', 'all');
				//$canPublishOwn= 1;
			}
			// redundant ?? ... since already checked by the controller
			//$has_edit = $canEdit || $canEditOwn || ($lastversion < 3);
			//if (!$has_edit) {
			//	$mainframe->redirect('index.php?option=com_flexicontent&view=items', JText::sprintf( 'FLEXI_NO_ACCESS_EDIT', JText::_('FLEXI_ITEM') ));
			//}
			
			// Second, check if item is already edited by a user and check it out (this fails if edit by any user other than the current user)
			if ($model->isCheckedOut( $user->get('id') )) {
				JError::raiseWarning( 'SOME_ERROR_CODE', $row->title.' '.JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
				$mainframe->redirect( 'index.php?option=com_flexicontent&view=items' );
			}
		}

		//clean data
		JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES );
		
		// *********************************************************************************************
		// Build select lists for the form field. Only few of them are used in J1.6+, since we will use:
		// (a) form XML file to declare them and then (b) getInput() method form field to create them
		// *********************************************************************************************
		$lists = array();
		
		// *** BOF: J1.5 SPECIFIC SELECT LISTS
		if (!FLEXI_J16GE) {

			if (FLEXI_ACCESS && ($user->gid < 25)) {
				if ((FAccess::checkAllContentAccess('com_content','add','users',$user->gmid,'content','all')) || (FAccess::checkAllContentAccess('com_content','edit','users',$user->gmid,'content','all'))) {
					$lists['cid'] = flexicontent_cats::buildcatselect($categories, 'cid[]', $selectedcats, false, 'multiple="multiple" size="20" class="mcat"', true, false);
					$lists['catid'] = flexicontent_cats::buildcatselect($categories, 'catid', $row->catid, 2, 'class="required scat"', true, false);
				} else {
					$lists['cid'] = flexicontent_cats::buildcatselect($categories, 'cid[]', $selectedcats, false, 'multiple="multiple" size="20" class="mcat"', true);
					$lists['catid'] = flexicontent_cats::buildcatselect($categories, 'catid', $row->catid, 2, 'class="required scat"', true);
				}
			} else {
				$lists['cid'] = flexicontent_cats::buildcatselect($categories, 'cid[]', $selectedcats, false, 'multiple="multiple" size="20" class="mcat"', true);
				$lists['catid'] = flexicontent_cats::buildcatselect($categories, 'catid', $row->catid, 2, 'class="required scat"', true);
			}

			//buid types selectlist
			$lists['type'] = flexicontent_html::buildtypesselect($types, 'type_id', $typesselected->id, 1, 'class="required"', true );
	
			// build granular access list
			if (FLEXI_ACCESS) {
				if (isset($user->level)) {
				$lists['access'] = FAccess::TabGmaccess( $row, 'item', 1, 0, 0, 1, 0, 1, 0, 1, 1 );
				} else {
					$lists['access'] = JText::_('Your profile has been changed, please logout to access to the permissions');
				}
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
			$lists['vstate'] = JHTML::_('select.radiolist', $vstate, 'vstate', '', 'value', 'text', 2 );
		}
		// *** EOF: J1.5 SPECIFIC SELECT LISTS
		
		
		//build languages list
		// We will not use the default getInput() function of J1.6+ since we want to create a radio selection field with flags
		// we could also create a new class and override getInput() method but maybe this is an overkill, we may do it in the future
		$language_fieldname = FLEXI_J16GE ? 'jform[language]' : 'language';
		if (FLEXI_FISH || FLEXI_J16GE) {
			$lists['languages'] = flexicontent_html::buildlanguageslist($language_fieldname, '', $row->language, 3);
		} else {
			$row->language = flexicontent_html::getSiteDefaultLang();
		}
		
		// Label for current item state: published, unpublished, archived etc
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
		
		
		// **************************************************************
		// Handle Item Parameters Creation and Load their values for J1.5
		// In J1.6+ we declare them in the item form XML file
		// **************************************************************
		if (!FLEXI_J16GE) {
			// Create the form parameters object
			if (FLEXI_ACCESS) {
				$formparams = new JParameter('', JPATH_COMPONENT.DS.'models'.DS.'item2.xml');
			} else {
				$formparams = new JParameter('', JPATH_COMPONENT.DS.'models'.DS.'item.xml');
			}

			// Details Group
			$active = (intval($row->created_by) ? intval($row->created_by) : $user->get('id'));
			if (!FLEXI_ACCESS) {
				$formparams->set('access', $row->access);
			}
			$formparams->set('created_by', $active);
			$formparams->set('created_by_alias', $row->created_by_alias);
			$formparams->set('created', JHTML::_('date', $row->created, '%Y-%m-%d %H:%M:%S'));
			$formparams->set('publish_up', JHTML::_('date', $row->publish_up, '%Y-%m-%d %H:%M:%S'));
			if ( JHTML::_('date', $row->publish_down, '%Y') <= 1969 || $row->publish_down == $db->getNullDate() || empty($row->publish_down) ) {
				$formparams->set('publish_down', JText::_( 'FLEXI_NEVER' ));
			} else {
				$formparams->set('publish_down', JHTML::_('date', $row->publish_down, '%Y-%m-%d %H:%M:%S'));
			}

			// Advanced Group
			$formparams->loadINI($row->attribs);

			// Metadata Group
			$formparams->set('description', $row->metadesc);
			$formparams->set('keywords', $row->metakey);
			$formparams->loadINI($row->metadata);
		} else {
			if ( JHTML::_('date', $row->publish_down , 'Y') <= 1969 || $row->publish_down == $db->getNullDate() || empty($row->publish_down) ) {
				$form->setValue('publish_down', null, JText::_( 'FLEXI_NEVER' ) );
			}
		}
		
		// ****************************
		// Handle Template related work
		// ****************************

		// (a) Get the templates structures used to create form fields for template parameters
		$themes			= flexicontent_tmpl::getTemplates();
		$tmpls_all	= $themes->items;
		
		// (b) Get Content Type allowed templates
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
		
		// (c) Apply Template Parameters values into the form fields structures 
		foreach ($tmpls as $tmpl) {
			if (FLEXI_J16GE) {
				foreach ($tmpl->params->getGroup('attribs') as $field) {
					$fieldname =  $field->__get('fieldname');
					$value = $row->itemparams->get($fieldname);
					if (strlen($value)) $tmpl->params->setValue($fieldname, 'attribs', $value);
				}
			} else {
				$tmpl->params->loadINI($row->attribs);
			}
		}
		
		// ******************************
		// Assign data to VIEW's template
		// ******************************
		$this->assignRef('document'     , $document);
		$this->assignRef('lists'      	, $lists);
		$this->assignRef('row'      		, $row);
		if (FLEXI_J16GE) {
			$this->assignRef('form'				, $form);
			$this->assignRef('permission'		, $permission);
		} else {
			$this->assignRef('editor'			, $editor);
			$this->assignRef('pane'				, $pane);
			$this->assignRef('formparams'	, $formparams);
		}
		$this->assignRef('typesselected', $typesselected);
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

		parent::display($tpl);
	}
}
?>

<?php
/**
 * @version 1.5 stable $Id: view.html.php 1319 2012-05-26 19:27:51Z ggppdk $
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
		
		JHTML::_('behavior.mootools');
		if(!JPluginHelper::isEnabled('system', 'jquerysupport')) {
			$document->addScript('components/com_flexicontent/assets/js/jquery-'.FLEXI_JQUERY_VER.'.js');
			// The 'noConflict()' statement is inside the above jquery file, to make sure it executed immediately
			//$document->addCustomTag('<script>jQuery.noConflict();</script>');
		}
		
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
		$types				= & $this->get( 'Typeslist' );
		if (!FLEXI_J16GE) {
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
			$previewlink = JRoute::_(JURI::root() . FlexicontentHelperRoute::getItemRoute($row->id.':'.$row->alias, $categories[$row->catid]->slug)) . $autologin;
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
		
		// Check if saving an item that translates an original content in site's default language
		$enable_translation_groups = $cparams->get('enable_translation_groups');
		$is_content_default_lang = substr(flexicontent_html::getSiteDefaultLang(), 0,2) == substr($row->language, 0,2);
		$modify_untraslatable_values = $enable_translation_groups && !$is_content_default_lang && $row->lang_parent_id && $row->lang_parent_id!=$row->id;
		
		// *****************************************************************************
		// Get (CORE & CUSTOM) fields and their VERSIONED values and then
		// (a) Apply Content Type Customization to CORE fields (label, description, etc) 
		// (b) Create the edit html of the CUSTOM fields by triggering 'onDisplayField' 
		// *****************************************************************************
		$fields = & $this->get( 'Extrafields' );
		foreach ($fields as $field)
		{
			// a. Apply CONTENT TYPE customizations to CORE FIELDS, e.g a type specific label & description
			// NOTE: the field parameters are already created so there is not need to call this for CUSTOM fields, which do not have CONTENT TYPE customizations
			if ($field->iscore) {
				FlexicontentFields::loadFieldConfig($field, $row);
			}
			
			// b. Create field 's editing HTML
			// NOTE: this is DONE only for CUSTOM fields, since form field html is created by the form for all CORE fields, EXCEPTION is the 'text' field (see bellow)
			if (!$field->iscore)
			{
				if ($modify_untraslatable_values && $field->untranslatable) {
					$field->html = JText::_( 'FLEXI_FIELD_VALUE_IS_UNTRANSLATABLE' );
				} else {
					FLEXIUtilities::call_FC_Field_Func($field->field_type, 'onDisplayField', array( &$field, &$row ));
				}
			}
			
			// c. Create main text field, via calling the display function of the textarea field (will also check for tabs)
			// NOTE: We use the text created by the model and not the text retrieved by the CORE plugin code, which maybe overwritten with JoomFish data
			if ($field->field_type == 'maintext')
			{
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
		
		//buid types selectlist
		if (FLEXI_J16GE) {
			$lists['type'] = flexicontent_html::buildtypesselect($types, 'jform[type_id]', $typesselected->id, 1, 'class="required"', true );
		} else {
			$lists['type'] = flexicontent_html::buildtypesselect($types, 'type_id', $typesselected->id, 1, 'class="required"', true );
		}
		
		// *** BOF: J1.5 SPECIFIC SELECT LISTS
		if (!FLEXI_J16GE) {
			
			// build granular access list
			if (FLEXI_ACCESS) {
				if (isset($user->level)) {
				$lists['access'] = FAccess::TabGmaccess( $row, 'item', 1, 0, 0, 1, 0, 1, 0, 1, 1 );
				} else {
					$lists['access'] = JText::_('Your profile has been changed, please logout to access to the permissions');
				}
			}
			
			// build state list
			$state[] = JHTML::_('select.option',  1, JText::_( 'FLEXI_PUBLISHED' ) );
			$state[] = JHTML::_('select.option',  0, JText::_( 'FLEXI_UNPUBLISHED' ) );
			$state[] = JHTML::_('select.option',  -3, JText::_( 'FLEXI_PENDING' ) );
			$state[] = JHTML::_('select.option',  -4, JText::_( 'FLEXI_TO_WRITE' ) );
			$state[] = JHTML::_('select.option',  -5, JText::_( 'FLEXI_IN_PROGRESS' ) );
			$state[] = JHTML::_('select.option',  FLEXI_J16GE ? 2:-1, JText::_( 'FLEXI_ARCHIVED' ) );
			
			if(!$canPublish && !$canPublishOwn)
				$row->state = $row->state ? $row->state : -4;
			$state_fieldname = FLEXI_J16GE ? 'jform[state]' : 'state';
			$lists['state'] = JHTML::_('select.genericlist',   $state, $state_fieldname, '', 'value', 'text', $row->state );
			
			// build version approval list
			$vstate = array();
			$vstate[] = JHTML::_('select.option',  1, JText::_( 'FLEXI_NO' ) );
			$vstate[] = JHTML::_('select.option',  2, JText::_( 'FLEXI_YES' ) );
			
			$fieldname = FLEXI_J16GE ? 'jform[vstate]' : 'vstate';
			$elementid = FLEXI_J16GE ? 'jform_vstate' : 'vstate';
			$attribs = FLEXI_J16GE ? ' style ="float:left!important;" '  :  '';   // this is not right for J1.5' style ="float:left!important;" ';
			$lists['vstate'] = JHTML::_('select.radiolist', $vstate, $fieldname, $attribs, 'value', 'text', 2, $elementid);
		}
		// *** EOF: J1.5 SPECIFIC SELECT LISTS
		
		
		// Retrieve author configuration
		$db->setQuery('SELECT author_basicparams FROM #__flexicontent_authors_ext WHERE user_id = ' . $user->id);
		if ( $authorparams = $db->loadResult() )
			$authorparams = new JParameter($authorparams);
		
		// Get author's maximum allowed categories per item and set js limitation
		$max_cat_assign = !$authorparams ? 0 : intval($authorparams->get('max_cat_assign',0));
		$document->addScriptDeclaration('
			max_cat_assign_fc = '.$max_cat_assign.';
			existing_cats_fc  = ["'.implode('","',$selectedcats).'"];
			max_cat_overlimit_msg_fc = "'.JText::_('FLEXI_TOO_MANY_ITEM_CATEGORIES').'";
		');
		
		$actions_allowed = array('core.create');
		// Multi-category form field, for user allowed to use multiple categories
		$class = $max_cat_assign ? " validate-fccats mcat" : "mcat";
		$attribs = 'multiple="multiple" size="20" class="'.$class.'"';
		
		if (FLEXI_J16GE) {
			$lists['cid'] = flexicontent_cats::buildcatselect($categories, 'jform[cid][]', $selectedcats, false, $attribs, true, true,	$actions_allowed);
		} else {
			$lists['cid'] = flexicontent_cats::buildcatselect($categories, 'cid[]', $selectedcats, false, $attribs, true, true,	$actions_allowed);
		}

		// Main category form field
		$attribs = 'class="scat validate-catid"';
		if (FLEXI_J16GE) {
			$lists['catid'] = flexicontent_cats::buildcatselect($categories, 'jform[catid]', $row->catid, 2, $attribs, true, true, $actions_allowed);
		} else {
			$lists['catid'] = flexicontent_cats::buildcatselect($categories, 'catid', $row->catid, 2, $attribs, true, true, $actions_allowed);
		}
		
		//build languages list
		$allowed_langs = !$authorparams ? null : $authorparams->get('langs_allowed',null);
		$allowed_langs = !$allowed_langs ? null : FLEXIUtilities::paramToArray($allowed_langs);
		if (!$isnew && $allowed_langs) $allowed_langs[] = $row->language;
		
		// We will not use the default getInput() function of J1.6+ since we want to create a radio selection field with flags
		// we could also create a new class and override getInput() method but maybe this is an overkill, we may do it in the future
		$language_fieldname = FLEXI_J16GE ? 'jform[language]' : 'language';
		if (FLEXI_FISH || FLEXI_J16GE) {
			$lists['languages'] = flexicontent_html::buildlanguageslist($language_fieldname, '', $row->language, 3, $allowed_langs);
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
		
		// (c) Add default layout, unless all templates allowed (=array is empty)
		if ( count ($allowed_tmpls) && !in_array( $type_default_layout, $allowed_tmpls ) ) $allowed_tmpls[] = $type_default_layout;
		
		// (d) Create array of template data according to the allowed templates for current content type
		if ( count($allowed_tmpls) ) {
			foreach ($tmpls_all as $tmpl) {
				if (in_array($tmpl->name, $allowed_tmpls) ) {
					$tmpls[]= $tmpl;
				}
			}
		} else {
			$tmpls= $tmpls_all;
		}
		
		// (e) Apply Template Parameters values into the form fields structures 
		foreach ($tmpls as $tmpl) {
			if (FLEXI_J16GE) {
				$jform = new JForm('com_flexicontent.template.item', array('control' => 'jform', 'load_data' => true));
				$jform->load($tmpl->params);
				$tmpl->params = $jform;
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

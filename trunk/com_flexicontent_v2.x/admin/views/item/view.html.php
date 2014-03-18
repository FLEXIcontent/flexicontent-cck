<?php
/**
 * @version 1.5 stable $Id: view.html.php 1694 2013-07-12 09:42:03Z ggppdk $
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

jimport('joomla.application.component.view');

/**
 * HTML View class for the Items View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewItem extends JViewLegacy
{


	/**
	 * Creates the item page
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		// ********************************
		// Initialize variables, flags, etc
		// ********************************
		global $globalcats;
		$categories = $globalcats;		
		
		$mainframe  = JFactory::getApplication();
		$dispatcher = JDispatcher::getInstance();
		$document   = JFactory::getDocument();
		$session    = JFactory::getSession();
		$user       = JFactory::getUser();
		$db         = JFactory::getDBO();
		
		$params     = clone( JComponentHelper::getParams('com_flexicontent') );
		$option     = JRequest::getVar('option');
		$nullDate   = $db->getNullDate();
		
		if (!FLEXI_J16GE) {
			jimport('joomla.html.pane');
			$pane   = JPane::getInstance('sliders');
			$editor = JFactory::getEditor();
		}

		// Some flags
		$enable_translation_groups = $params->get("enable_translation_groups") && ( FLEXI_J16GE || FLEXI_FISH ) ;
		$print_logging_info = $params->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		
		
		// *****************
		// Load JS/CSS files
		// *****************
		
		FLEXI_J30GE ? JHtml::_('behavior.framework', true) : JHTML::_('behavior.mootools');
		flexicontent_html::loadJQuery();

		// Load custom behaviours: form validation, popup tooltips
		//JHTML::_('behavior.formvalidation');
		JHTML::_('behavior.tooltip');
		
		// Add css to document
		$document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/flexicontentbackend.css');
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j25.css');
		else                  $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j15.css');
		
		// Add js function to overload the joomla submitform
		$document->addScript(JURI::root().'components/com_flexicontent/assets/js/admin.js');
		$document->addScript(JURI::root().'components/com_flexicontent/assets/js/validate.js');
		
		// Add js function for custom code used by FLEXIcontent item form
		$document->addScript(JURI::root().'components/com_flexicontent/assets/js/itemscreen.js');
		
		
		// ***********************
		// Get data from the model
		// ***********************
		
		if ( $print_logging_info )  $start_microtime = microtime(true);
		
		$model = $this->getModel();
		$row   = $this->get('Item');
		if (FLEXI_J16GE) {
			$form  = $this->get('Form');
		}
		
		if ( $print_logging_info ) $fc_run_times['get_item_data'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

		// Get Associated Translations
		if ($enable_translation_groups)  $langAssocs = $this->get( 'LangAssocs' );
		if (FLEXI_FISH || FLEXI_J16GE)   $langs = FLEXIUtilities::getLanguages('code');

		// Get item id and new flag
		$cid = $model->getId();
		$isnew = ! $cid;
		
		// Get user allowed permissions on the item ... to be used by the form rendering
		// Also hide parameters panel if user can not edit parameters
		$perms = $this->_getItemPerms($row);
		if (!$perms['canparams'])  $document->addStyleDeclaration( (FLEXI_J16GE ? '#details-options' : '#det-pane') .'{display:none;}');
		
		// Create and set a unique item id for plugins that needed it
		JRequest::setVar( 'unique_tmp_itemid', $cid ? $cid : date('_Y_m_d_h_i_s_', time()) . uniqid(true) );
		
		// Get number of subscribers
		$subscribers = $model->getSubscribersCount();
		
		
		// ******************
		// Version Panel data
		// ******************
		
		// Get / calculate some version related variables
		$versioncount    = $model->getVersionCount();
		$versionsperpage = $params->get('versionsperpage', 10);
		$pagecount = (int) ceil( $versioncount / $versionsperpage );
		
		// Data need by version panel: (a) current version page, (b) currently active version
		$current_page = 1;  $k=1;
		$allversions  = $model->getVersionList();
		foreach($allversions as $v)
		{
			if ( $k && ( $k % $versionsperpage == 0 ) )
				$current_page++;
			if ( $v->nr == $row->version ) break;
			$k++;
		}
		
		// Finally fetch the version data for versions in current page
		$versions = $model->getVersionList( ($current_page-1)*$versionsperpage, $versionsperpage );
		
		
		// *****************
		// Type related data
		// *****************
		
		// Get available types and the currently selected/requested type
		$types         = $model->getTypeslist();
		$typesselected = $model->getTypesselected();
		
		// Get and merge type parameters
		$tparams    = $this->get( 'Typeparams' );
		$tparams    = FLEXI_J16GE ? new JRegistry($tparams) : new JParameter($tparams);
		$params->merge($tparams);       // Apply type configuration if it type is set
		
		
		// ******************
		// Create the toolbar
		// ******************
		$toolbar = JToolBar::getInstance('toolbar');
		
		// SET toolbar title
		if ( $cid )
		{
			JToolBarHelper::title( JText::_( 'FLEXI_EDIT_ITEM' ), 'itemedit' );   // Editing existing item
		} else {
			JToolBarHelper::title( JText::_( 'FLEXI_NEW_ITEM' ), 'itemadd' );     // Creating new item
		}

		// Add a preview button for LATEST version of the item
		if ( $cid )
		{
			// Domain URL and autologin vars
			$server = JURI::getInstance()->toString(array('scheme', 'host', 'port'));
			$autologin   = $params->get('autoflogin', 1) ? '&fcu='.$user->username . '&fcp='.$user->password : '';
			
			// Check if we are in the backend, in the back end we need to set the application to the site app instead
			$isAdmin = JFactory::getApplication()->isAdmin();
			if ( $isAdmin && FLEXI_J16GE ) JFactory::$application = JApplication::getInstance('site');
			
			// Create the URL
			$item_url = JRoute::_(FlexicontentHelperRoute::getItemRoute($row->id.':'.$row->alias, $categories[$row->catid]->slug) . $autologin );
			
			// Check if we are in the backend again
			// In backend we need to remove administrator from URL as it is added even though we've set the application to the site app
			if( $isAdmin ) {
				if ( FLEXI_J16GE ) {
					$admin_folder = str_replace(JURI::root(true),'',JURI::base(true));
					$item_url = str_replace($admin_folder, '', $item_url);
					// Restore application
					JFactory::$application = JApplication::getInstance('administrator');
				} else {
					$item_url = JURI::root(true).'/'.$item_url;
				}
			}
			
			$previewlink     = /*$server .*/ $item_url;
			$previewlink     = str_replace('&amp;', '&', $previewlink);
			//$previewlink = JRoute::_(JURI::root() . FlexicontentHelperRoute::getItemRoute($row->id.':'.$row->alias, $categories[$row->catid]->slug)) .$autologin;

			if ( !$params->get('use_versioning', 1) || ($row->version == $row->current_version && $row->version == $row->last_version) )
			{
				$toolbar->appendButton( 'Custom', '<a class="preview btn btn-small" href="'.$previewlink.'" target="_blank"><span title="'.JText::_('Preview').'" class="icon-32-preview"></span>'.JText::_('Preview').'</a>', 'preview' );
			} else {
				// Add a preview button for (currently) LOADED version of the item
				$previewlink_loaded_ver = $previewlink .'&version='.$row->version;
				$toolbar->appendButton( 'Custom', '<a class="preview btn btn-small" href="'.$previewlink_loaded_ver.'" target="_blank"><span title="'.JText::_('Preview').'" class="icon-32-preview"></span>'.JText::_('FLEXI_PREVIEW_FORM_LOADED_VERSION').' ['.$row->version.']</a>', 'preview' );

				// Add a preview button for currently ACTIVE version of the item
				$previewlink_active_ver = $previewlink .'&version='.$row->current_version;
				$toolbar->appendButton( 'Custom', '<a class="preview btn btn-small" href="'.$previewlink_active_ver.'" target="_blank"><span title="'.JText::_('Preview').'" class="icon-32-preview"></span>'.JText::_('FLEXI_PREVIEW_FRONTEND_ACTIVE_VERSION').' ['.$row->current_version.']</a>', 'preview' );

				// Add a preview button for currently LATEST version of the item
				$previewlink_last_ver = $previewlink .'&preview='.$row->last_version;
				$toolbar->appendButton( 'Custom', '<a class="preview btn btn-small" href="'.$previewlink_last_ver.'" target="_blank"><span title="'.JText::_('Preview').'" class="icon-32-preview"></span>'.JText::_('FLEXI_PREVIEW_LATEST_SAVED_VERSION').' ['.$row->last_version.']</a>', 'preview' );
			}
			JToolBarHelper::spacer();
			JToolBarHelper::divider();
			JToolBarHelper::spacer();
		}

		// Common Buttons
		if (FLEXI_J16GE) {
			JToolBarHelper::apply('items.apply');
			if (!$isnew || $row->version) JToolBarHelper::save('items.save');
			if (!$isnew || $row->version) JToolBarHelper::custom( 'items.saveandnew', 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );
			JToolBarHelper::cancel('items.cancel');
		} else {
			JToolBarHelper::apply();
			if (!$isnew || $row->version) JToolBarHelper::save();
			if (!$isnew || $row->version) JToolBarHelper::custom( 'saveandnew', 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );
			JToolBarHelper::cancel();
		}
		
		
		// *****************************************************************************
		// Get (CORE & CUSTOM) fields and their VERSIONED values and then
		// (a) Apply Content Type Customization to CORE fields (label, description, etc)
		// (b) Create the edit html of the CUSTOM fields by triggering 'onDisplayField'
		// *****************************************************************************
		
		if ( $print_logging_info )  $start_microtime = microtime(true);
		$fields = $this->get( 'Extrafields' );
		if ( $print_logging_info ) $fc_run_times['get_field_vals'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

		// Check if saving an item that translates an original content in site's default language
		$is_content_default_lang = substr(flexicontent_html::getSiteDefaultLang(), 0,2) == substr($row->language, 0,2);
		$modify_untraslatable_values = $enable_translation_groups && !$is_content_default_lang && $row->lang_parent_id && $row->lang_parent_id!=$row->id;
		
		if ( $print_logging_info )  $start_microtime = microtime(true);
		foreach ($fields as $field)
		{
			// a. Apply CONTENT TYPE customizations to CORE FIELDS, e.g a type specific label & description
			// NOTE: the field parameters are already created so there is not need to call this for CUSTOM fields, which do not have CONTENT TYPE customizations
			if ($field->iscore) {
				FlexicontentFields::loadFieldConfig($field, $row);
			}

			// b. Create field 's editing HTML (the form field)
			// NOTE: this is DONE only for CUSTOM fields, since form field html is created by the form for all CORE fields, EXCEPTION is the 'text' field (see bellow)
			if (!$field->iscore)
			{
				if (FLEXI_J16GE)
					$is_editable = !$field->valueseditable || $user->authorise('flexicontent.editfieldvalues', 'com_flexicontent.field.' . $field->id);
				else if (FLEXI_ACCESS && $user->gid < 25)
					$is_editable = !$field->valueseditable || FAccess::checkAllContentAccess('com_content','submit','users', $user->gmid, 'field', $field->id);
				else
					$is_editable = 1;

				if ( !$is_editable ) {
					$field->html = '<div class="fc_mini_note_box">'. JText::_('FLEXI_NO_ACCESS_LEVEL_TO_EDIT_FIELD') . '</div>';
				} else if ($modify_untraslatable_values && $field->untranslatable) {
					$field->html = '<div class="fc_mini_note_box">'. JText::_('FLEXI_FIELD_VALUE_IS_UNTRANSLATABLE') . '</div>';
				} else {
					FLEXIUtilities::call_FC_Field_Func($field->field_type, 'onDisplayField', array( &$field, &$row ));
				}
			}

			// c. Create main text field, via calling the display function of the textarea field (will also check for tabs)
			if ($field->field_type == 'maintext')
			{
				if ( isset($row->item_translations) ) {
					$shortcode = substr($row->language ,0,2);
					foreach ($row->item_translations as $lang_id => $t)	{
						if ($shortcode == $t->shortcode) continue;
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
				// NOTE: We use the text created by the model and not the text retrieved by the CORE plugin code, which maybe overwritten with JoomFish/Falang data
				$field->value[0] = $row->text; // do not decode special characters this was handled during saving !
				// Render the field's (form) HTML
				FLEXIUtilities::call_FC_Field_Func('textarea', 'onDisplayField', array(&$field, &$row) );
			}
		}
		if ( $print_logging_info ) $fc_run_times['render_field_html'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		
		// *************************
		// Get tags used by the item
		// *************************
		
		$usedtagsIds = $this->get( 'UsedtagsIds' );  // NOTE: This will normally return the already set versioned value of tags ($row->tags)
		$usedtags = $model->getUsedtagsData($usedtagsIds);
		
		
		// *******************************
		// Get categories used by the item
		// *******************************
		
		if ($cid) {
			// NOTE: This will normally return the already set versioned value of categories ($row->categories)
			$selectedcats	= $this->get( 'Catsselected' );
		} else {
			$maincat = JRequest::getInt('maincat', 0);
			if (!$maincat) {
				$maincat = $mainframe->getUserStateFromRequest( $option.'.items.filter_cats', 'filter_cats', '', 'int' );
			}
			if ($maincat) {
				$selectedcats = array($maincat);
				$row->catid = $maincat;
			} else {
				$selectedcats = array();
			}
		}

		//$selectedcats 	= $isnew ? array() : $fields['categories']->value;

		//echo "<br>row->tags: "; print_r($row->tags);
		//echo "<br>usedtagsIds: "; print_r($usedtagsIds);
		//echo "<br>usedtags (data): "; print_r($usedtags);

		//echo "<br>row->categories: "; print_r($row->categories);
		//echo "<br>selectedcats: "; print_r($selectedcats);
		
		
		
		// *********************************************************************************************
		// Build select lists for the form field. Only few of them are used in J1.6+, since we will use:
		// (a) form XML file to declare them and then (b) getInput() method form field to create them
		// *********************************************************************************************
		
		// First clean form data
		JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES );
		
		flexicontent_html::loadFramework('select2');
		$prettycheckable_added = flexicontent_html::loadFramework('prettyCheckable');
		$lists = array();

		//buid types selectlist
		$class   = 'required use_select2_lib';
		$attribs = 'class="'.$class.'"';
		$fieldname = FLEXI_J16GE ? 'jform[type_id]' : 'type_id';
		$elementid = FLEXI_J16GE ? 'jform_type_id'  : 'type_id';
		$lists['type'] = flexicontent_html::buildtypesselect($types, $fieldname, $typesselected->id, 1, $attribs, $elementid, $check_perms=true );
		
		// build granular access list
		if (FLEXI_ACCESS) {
			if (isset($user->level)) {
			$lists['access'] = FAccess::TabGmaccess( $row, 'item', 1, 0, 0, 1, 0, 1, 0, 1, 1 );
			} else {
				$lists['access'] = JText::_('Your profile has been changed, please logout to access to the permissions');
			}
		}

		// build state list
		$_arc_ = FLEXI_J16GE ? 2:-1;
		$non_publishers_stategrp    = $perms['isSuperAdmin'] || $row->state==-3 || $row->state==-4 ;
		$special_privelege_stategrp = ($row->state==$_arc_ || $perms['canarchive']) || ($row->state==-2 || $perms['candelete']) ;
		
		$state = array();
		if ($non_publishers_stategrp || $special_privelege_stategrp)
			$state[] = JHTML::_('select.optgroup', JText::_( 'FLEXI_PUBLISHERS_WORKFLOW_STATES' ) );
		$state[] = JHTML::_('select.option',  1,  JText::_( 'FLEXI_PUBLISHED' ) );
		$state[] = JHTML::_('select.option',  0,  JText::_( 'FLEXI_UNPUBLISHED' ) );
		$state[] = JHTML::_('select.option',  -5, JText::_( 'FLEXI_IN_PROGRESS' ) );
		
		// States reserved for workflow
		if ( $non_publishers_stategrp ) {
			$state[] = JHTML::_('select.optgroup', '' );
			$state[] = JHTML::_('select.optgroup', JText::_( 'FLEXI_NON_PUBLISHERS_WORKFLOW_STATES' ) );
		}
		if ($row->state==-3 || $perms['isSuperAdmin'])  $state[] = JHTML::_('select.option',  -3, JText::_( 'FLEXI_PENDING' ) );
		if ($row->state==-4 || $perms['isSuperAdmin'])  $state[] = JHTML::_('select.option',  -4, JText::_( 'FLEXI_TO_WRITE' ) );
		
		// Special access states
		if ( $special_privelege_stategrp ) {
			$state[] = JHTML::_('select.optgroup', '' );
			$state[] = JHTML::_('select.optgroup', JText::_( 'FLEXI_SPECIAL_ACTION_STATES' ) );
		}
		if ($row->state==$_arc_ || $perms['canarchive']) $state[] = JHTML::_('select.option',  $_arc_, JText::_( 'FLEXI_ARCHIVED' ) );
		if ($row->state==-2     || $perms['candelete'])  $state[] = JHTML::_('select.option',  -2,     JText::_( 'FLEXI_TRASHED' ) );
		
		if( !$perms['canpublish'] )  $row->state = $row->state ? $row->state : -4;
		$fieldname = FLEXI_J16GE ? 'jform[state]' : 'state';
		$elementid = FLEXI_J16GE ? 'jform_state'  : 'state';
		$class = 'inputbox use_select2_lib';
		$attribs = 'class="'.$class.'"';
		$lists['state'] = JHTML::_('select.genericlist', $state, $fieldname, $attribs, 'value', 'text', $row->state, $elementid );
		
		// *** BOF: J2.5 SPECIFIC SELECT LISTS
		if (FLEXI_J16GE)
		{
			// build featured flag
			$fieldname = 'jform[featured]';
			$elementid = 'jform_featured';
			/*
			$options = array();
			$options[] = JHTML::_('select.option',  0, JText::_( 'FLEXI_NO' ) );
			$options[] = JHTML::_('select.option',  1, JText::_( 'FLEXI_YES' ) );
			$attribs = FLEXI_J16GE ? ' style ="float:none!important;" '  :  '';   // this is not right for J1.5' style ="float:left!important;" ';
			$lists['featured'] = JHTML::_('select.radiolist', $options, $fieldname, $attribs, 'value', 'text', $row->featured, $elementid);
			*/
			$classes = !$prettycheckable_added ? '' : ' use_prettycheckable ';
			$attribs = ' class="'.$classes.'" ';
			$i = 1;
			$options = array(0=>JText::_( 'FLEXI_NO' ), 1=>JText::_( 'FLEXI_YES' ) );
			$lists['featured'] = '';
			foreach ($options as $option_id => $option_label) {
				$checked = $option_id==$row->featured ? ' checked="checked"' : '';
				$elementid_no = $elementid.'_'.$i;
				if (!$prettycheckable_added) $lists['featured'] .= '<label class="fccheckradio_lbl" for="'.$elementid_no.'">';
				$extra_params = !$prettycheckable_added ? '' : ' data-label="'.JText::_($option_label).'" data-labelPosition="right" data-customClass="fcradiocheck"';
				$lists['featured'] .= ' <input type="radio" id="'.$elementid_no.'" element_group_id="'.$elementid
					.'" name="'.$fieldname.'" '.$attribs.' value="'.$option_id.'" '.$checked.$extra_params.' />';
				if (!$prettycheckable_added) $lists['featured'] .= '&nbsp;'.JText::_($option_label).'</label>';
				$i++;
			}
		}
		// *** EOF: J1.5 SPECIFIC SELECT LISTS
		
		// build version approval list
		$fieldname = FLEXI_J16GE ? 'jform[vstate]' : 'vstate';
		$elementid = FLEXI_J16GE ? 'jform_vstate' : 'vstate';
		/*
		$options = array();
		$options[] = JHTML::_('select.option',  1, JText::_( 'FLEXI_NO' ) );
		$options[] = JHTML::_('select.option',  2, JText::_( 'FLEXI_YES' ) );
		$attribs = FLEXI_J16GE ? ' style ="float:left!important;" '  :  '';   // this is not right for J1.5' style ="float:left!important;" ';
		$lists['vstate'] = JHTML::_('select.radiolist', $options, $fieldname, $attribs, 'value', 'text', 2, $elementid);
		*/
		$classes = !$prettycheckable_added ? '' : ' use_prettycheckable ';
		$attribs = ' class="'.$classes.'" ';
		$i = 1;
		$options = array(1=>JText::_( 'FLEXI_NO' ), 2=>JText::_( 'FLEXI_YES' ) );
		$lists['vstate'] = '';
		foreach ($options as $option_id => $option_label) {
			$checked = $option_id==2 ? ' checked="checked"' : '';
			$elementid_no = $elementid.'_'.$i;
			if (!$prettycheckable_added) $lists['vstate'] .= '<label class="fccheckradio_lbl" for="'.$elementid_no.'">';
			$extra_params = !$prettycheckable_added ? '' : ' data-label="'.JText::_($option_label).'" data-labelPosition="right" data-customClass="fcradiocheck"';
			$lists['vstate'] .= ' <input type="radio" id="'.$elementid_no.'" element_group_id="'.$elementid
				.'" name="'.$fieldname.'" '.$attribs.' value="'.$option_id.'" '.$checked.$extra_params.' />';
			if (!$prettycheckable_added) $lists['vstate'] .= '&nbsp;'.JText::_($option_label).'</label>';
			$i++;
		}
		
		
		// build field for notifying subscribers
		if ( !$subscribers )
		{
			$lists['notify'] = !$isnew ? JText::_('FLEXI_NO_SUBSCRIBERS_EXIST') : '';
		} else {
			// b. Check if notification emails to subscribers , were already sent during current session
			$subscribers_notified = $session->get('subscribers_notified', array(),'flexicontent');
			if ( !empty($subscribers_notified[$row->id]) ) {
				$lists['notify'] = JText::_('FLEXI_SUBSCRIBERS_ALREADY_NOTIFIED');
			} else {
				// build favs notify field
				$fieldname = FLEXI_J16GE ? 'jform[notify]' : 'notify';
				$elementid = FLEXI_J16GE ? 'jform_notify' : 'notify';
				/*
				$attribs = FLEXI_J16GE ? ' style ="float:none!important;" '  :  '';   // this is not right for J1.5' style ="float:left!important;" ';
				$lists['notify'] = '<input type="checkbox" name="jform[notify]" id="jform_notify" '.$attribs.' /> '. $lbltxt;
				*/
				$classes = !$prettycheckable_added ? '' : ' use_prettycheckable ';
				$attribs = ' class="'.$classes.'" ';
				$lbltxt = $subscribers .' '. JText::_( $subscribers>1 ? 'FLEXI_SUBSCRIBERS' : 'FLEXI_SUBSCRIBER' );
				if (!$prettycheckable_added) $lists['notify'] .= '<label class="fccheckradio_lbl" for="'.$elementid.'">';
				$extra_params = !$prettycheckable_added ? '' : ' data-label="'.$lbltxt.'" data-labelPosition="right" data-customClass="fcradiocheck"';
				$lists['notify'] = ' <input type="checkbox" id="'.$elementid.'" element_group_id="'.$elementid
					.'" name="'.$fieldname.'" '.$attribs.' value="1" '.$extra_params.' checked="checked" />';
				if (!$prettycheckable_added) $lists['notify'] .= '&nbsp;'.$lbltxt.'</label>';
			}
		}
		
		// Retrieve author configuration
		$db->setQuery('SELECT author_basicparams FROM #__flexicontent_authors_ext WHERE user_id = ' . $user->id);
		if ( $authorparams = $db->loadResult() )
			$authorparams = FLEXI_J16GE ? new JRegistry($authorparams) : new JParameter($authorparams);

		// Get author's maximum allowed categories per item and set js limitation
		$max_cat_assign = !$authorparams ? 0 : intval($authorparams->get('max_cat_assign',0));
		$document->addScriptDeclaration('
			max_cat_assign_fc = '.$max_cat_assign.';
			existing_cats_fc  = ["'.implode('","',$selectedcats).'"];
			max_cat_overlimit_msg_fc = "'.JText::_('FLEXI_TOO_MANY_ITEM_CATEGORIES',true).'";
		');
		
		// Creating categorories tree for item assignment, we use the 'create' privelege
		$actions_allowed = array('core.create');

		// Featured categories form field
		$featured_cats_parent = $params->get('featured_cats_parent', 0);
		$featured_cats = array();
		if ( $featured_cats_parent )
		{
			$featured_tree = flexicontent_cats::getCategoriesTree($published_only=1, $parent_id=$featured_cats_parent, $depth_limit=0);
			$featured_sel = array();
			foreach($selectedcats as $featured_cat) if (isset($featured_tree[$featured_cat])) $featured_sel[] = $featured_cat;
			
			$class  = "use_select2_lib select2_list_selected";
			$attribs = 'class="'.$class.'" multiple="multiple" size="8"';
			$fieldname = FLEXI_J16GE ? 'jform[featured_cid][]' : 'featured_cid[]';
			$lists['featured_cid'] = flexicontent_cats::buildcatselect($featured_tree, $fieldname, $featured_sel, 3, $attribs, true, true,	$actions_allowed);
		}
		
		// Multi-category form field, for user allowed to use multiple categories
		$class  = "mcat use_select2_lib select2_list_selected";
		$class .= $max_cat_assign ? " validate-fccats" : "";
		$attribs = 'multiple="multiple" size="20" class="'.$class.'"';
		$fieldname = FLEXI_J16GE ? 'jform[cid][]' : 'cid[]';
		$skip_subtrees = $featured_cats_parent ? array($featured_cats_parent) : array();
		$lists['cid'] = flexicontent_cats::buildcatselect($categories, $fieldname, $selectedcats, false, $attribs, true, true,
			$actions_allowed, $require_all=true, $skip_subtrees, $disable_subtrees=array());

		// Main category form field
		$attribs = 'class="scat validate-catid use_select2_lib"';
		$fieldname = FLEXI_J16GE ? 'jform[catid]' : 'catid';
		$lists['catid'] = flexicontent_cats::buildcatselect($categories, $fieldname, $row->catid, 2, $attribs, true, true, $actions_allowed);
		
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

			//echo "<pre>"; print_r($formparams->_xml['themes']->_children[0]);  echo "<pre>"; print_r($formparams->_xml['themes']->param[0]); exit;
			foreach($formparams->_xml['themes']->_children as $i => $child) {
				if ( isset($child->_attributes['enableparam']) && !$params->get($child->_attributes['enableparam']) ) {
					unset($formparams->_xml['themes']->_children[$i]);
					unset($formparams->_xml['themes']->param[$i]);
				}
			}

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
		} else {
			$this->assignRef('editor'			, $editor);
			$this->assignRef('pane'				, $pane);
			$this->assignRef('formparams'	, $formparams);
		}
		if ($enable_translation_groups)  $this->assignRef('lang_assocs', $langAssocs);
		if (FLEXI_FISH || FLEXI_J16GE)   $this->assignRef('langs', $langs);
		$this->assignRef('typesselected', $typesselected);
		$this->assignRef('published'		, $published);
		$this->assignRef('nullDate'			, $nullDate);
		$this->assignRef('subscribers'	, $subscribers);
		$this->assignRef('fields'				, $fields);
		$this->assignRef('versions'			, $versions);
		$this->assignRef('pagecount'		, $pagecount);
		$this->assignRef('params'				, $params);
		$this->assignRef('tparams'			, $tparams);
		$this->assignRef('tmpls'				, $tmpls);
		$this->assignRef('usedtags'			, $usedtags);
		$this->assignRef('perms'				, $perms);
		$this->assignRef('current_page'	, $current_page);
		
		if ( $print_logging_info ) $start_microtime = microtime(true);
		parent::display($tpl);
		if ( $print_logging_info ) $fc_run_times['form_rendering'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	}
	
	
	/**
	 * Calculates the user permission on the given item
	 *
	 * @since 1.0
	 */
	function _getItemPerms( &$item )
	{
		$user = JFactory::getUser();	// get current user\
		$isOwner = ( $item->created_by == $user->get('id') );

		$perms 	= array();

		$permission = FlexicontentHelperPerm::getPerm();
		$perms['isSuperAdmin'] = $permission->SuperAdmin;
		$perms['multicat']     = $permission->MultiCat;
		$perms['cantags']      = $permission->CanUseTags;
		$perms['canparams']    = $permission->CanParams;
		$perms['cantemplates'] = $permission->CanTemplates;
		$perms['canarchive']   = $permission->CanArchives;
		$perms['canright']     = $permission->CanRights;
		$perms['canversion']   = $permission->CanVersion;
		
		// J2.5+ specific
		if (FLEXI_J16GE) $perms['editcreationdate'] = $permission->EditCreationDate;
		//else if (FLEXI_ACCESS) $perms['editcreationdate'] = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'editcreationdate', 'users', $user->gmid) : 1;
		//else $perms['editcreationdate'] = ($user->gid >= 25);
		
		// Get general edit/publish/delete permissions (we will override these for existing items)
		$perms['canadd']     = $permission->CanAdd;
		$perms['canedit']    = $permission->CanEdit    || $permission->CanEditOwn;
		$perms['canpublish'] = $permission->CanPublish || $permission->CanPublishOwn;
		$perms['candelete']  = $permission->CanDelete  || $permission->CanDeleteOwn;
		
		// OVERRIDE global with existing item's atomic settings
		if ( $item->id )
		{
			if (FLEXI_J16GE) {
				$asset = 'com_content.article.' . $item->id;
				$perms['canedit']			= $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $isOwner);
				$perms['canpublish']	= $user->authorise('core.edit.state', $asset) || ($user->authorise('core.edit.state.own', $asset) && $isOwner);
				$perms['candelete']		= $user->authorise('core.delete', $asset) || ($user->authorise('core.delete.own', $asset) && $isOwner);
			}
			else if (FLEXI_ACCESS) {
				$rights = FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
				$perms['canedit']			= ($user->gid < 25) ? ( (in_array('editown', $rights) && $isOwner) || (in_array('edit', $rights)) ) : 1;
				$perms['canpublish']	= ($user->gid < 25) ? ( (in_array('publishown', $rights) && $isOwner) || (in_array('publish', $rights)) ) : 1;
				$perms['candelete']		= ($user->gid < 25) ? ( (in_array('deleteown', $rights) && $isOwner) || (in_array('delete', $rights)) ) : 1;
				// Only FLEXI_ACCESS has per item rights permission
				$perms['canright']		= ($user->gid < 25) ? ( (in_array('right', $rights)) ) : 1;
			}
			else {
				// J1.5 permissions with no FLEXIaccess are only general, no item specific permissions
			}
		}
		
		return $perms;
	}
	
}
?>

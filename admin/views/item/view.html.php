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

jimport('legacy.view.legacy');

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
		$categories = & $globalcats;		
		
		$app        = JFactory::getApplication();
		$jinput     = $app->input;
		$dispatcher = JDispatcher::getInstance();
		$document   = JFactory::getDocument();
		$config     = JFactory::getConfig();
		$session    = JFactory::getSession();
		$user       = JFactory::getUser();
		$db         = JFactory::getDBO();
		$option     = $jinput->get('option', '', 'cmd');
		$nullDate   = $db->getNullDate();
		
		// We do not have item parameters yet, but we need to do some work before creating the item
		
		// Get the COMPONENT only parameter
		$params  = new JRegistry();
		$cparams = JComponentHelper::getParams('com_flexicontent');
		$params->merge($cparams);
		
		// Some flags
		$useAssocs = flexicontent_db::useAssociations();
		$print_logging_info = $params->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		
		
		
		// ***********************
		// Get data from the model
		// ***********************
		
		if ( $print_logging_info )  $start_microtime = microtime(true);
		
		$model = $this->getModel();
		$item = $model->getItem();
		$form = $this->get('Form');
		
		if ( $print_logging_info ) $fc_run_times['get_item_data'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		
		
		// **************************************************************
		// Get (CORE & CUSTOM) fields and their VERSIONED values and then
		// **************************************************************
		
		if ( $print_logging_info )  $start_microtime = microtime(true);
		
		$fields = $this->get( 'Extrafields' );
		$item->fields = & $fields;
		
		if ( $print_logging_info ) $fc_run_times['get_field_vals'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		
		
		// ***************************
		// Get Associated Translations
		// ***************************
		
		if ($useAssocs)  $langAssocs = $this->get( 'LangAssocs' );
		$langs = FLEXIUtilities::getLanguages('code');

		// Get item id and new flag
		$cid = $model->getId();
		$isnew = ! $cid;
		
		// Create and set a unique item id for plugins that needed it
		if ($cid) {
			$unique_tmp_itemid = $cid;
		} else {
			$unique_tmp_itemid = $app->getUserState($form->option.'.edit.item.unique_tmp_itemid');
			$unique_tmp_itemid = $unique_tmp_itemid ? $unique_tmp_itemid : date('_Y_m_d_h_i_s_', time()) . uniqid(true);
		}
		//print_r($unique_tmp_itemid);
		$jinput->set('unique_tmp_itemid', $unique_tmp_itemid);

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
			if ( $k > 1 && (($k-1) % $versionsperpage) == 0 )
				$current_page++;
			if ( $v->nr == $item->version ) break;
			$k++;
		}
		
		// Finally fetch the version data for versions in current page
		$versions = $model->getVersionList( ($current_page-1)*$versionsperpage, $versionsperpage );
		
		// Create display of average rating 
		$ratings = $model->getRatingDisplay();
		
		
		
		// *****************
		// Type related data
		// *****************
		
		// Get available types and the currently selected/requested type
		$types         = $model->getTypeslist();
		$typesselected = $model->getTypesselected();
		
		// Get and merge type parameters
		$tparams = $this->get( 'Typeparams' );
		$tparams = new JRegistry($tparams);
		$params->merge($tparams);       // Apply type configuration if it type is set
		
		// Get user allowed permissions on the item ... to be used by the form rendering
		// Also hide parameters panel if user can not edit parameters
		$perms = $this->_getItemPerms($item);
		if (!$perms['canparams'])  $document->addStyleDeclaration( '#details-options {display:none;}' );
		
		
		
		// *****************
		// Load JS/CSS files
		// *****************
		
		// Add css to document
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', FLEXI_VHASH);
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/j3x_rtl.css', FLEXI_VHASH);
		
		// Fields common CSS
		$document->addStyleSheetVersion(JURI::root(true).'/components/com_flexicontent/assets/css/flexi_form_fields.css', FLEXI_VHASH);
		
		// Add JS frameworks
		$has_J2S = false;
		foreach ($fields as $field)
		{
			$has_J2S = $has_J2S || $field->field_type == 'j2store';
			if ($has_J2S) break;
		}
		$_params = new JRegistry();
		$_params->set('load-ui-dialog', 1);
		$_params->set('load-ui-menu', $has_J2S ? 0 : 1);
		$_params->set('load-ui-autocomplete', $has_J2S ? 0 : 1);
		
		flexicontent_html::loadJQuery( $add_jquery = 1, $add_jquery_ui = 1, $add_jquery_ui_css = 1, $add_remote = 1, $_params);   //flexicontent_html::loadFramework('jQuery');
		flexicontent_html::loadFramework('select2');
		flexicontent_html::loadFramework('touch-punch');
		flexicontent_html::loadFramework('prettyCheckable');
		flexicontent_html::loadFramework('flexi-lib');
		flexicontent_html::loadFramework('flexi-lib-form');
		
		// Add js function to overload the joomla submitform validation
		JHTML::_('behavior.formvalidation');  // load default validation JS to make sure it is overriden
		$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
		$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);
		
		// Add js function for custom code used by FLEXIcontent item form
		$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/itemscreen.js', FLEXI_VHASH);
		
		
		
		// ******************
		// Create the toolbar
		// ******************
		$toolbar = JToolBar::getInstance('toolbar');
		$tip_class = ' hasTooltip';
		
		// SET toolbar title
		if ( $cid )
		{
			JToolBarHelper::title( JText::_( 'FLEXI_EDIT_ITEM' ), 'itemedit' );   // Editing existing item
		} else {
			JToolBarHelper::title( JText::_( 'FLEXI_NEW_ITEM' ), 'itemadd' );     // Creating new item
		}
		
		
		
		// **************
		// Common Buttons
		// **************
		
		// ***
		// *** Apply buttons
		// ***

		// Apply button
		$btn_arr = array();
		if (!$isnew || $item->version)
		{
			$btn_name = 'apply_ajax';
			$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
				'FLEXI_APPLY', $btn_name, $full_js="Joomla.submitbutton('items.apply_ajax')", $msg_alert='', $msg_confirm='',
				$btn_task='items.apply_ajax', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="".$tip_class, $btn_icon="icon-loop",
				'data-placement="bottom" title="'.JText::_('FLEXI_FAST_SAVE_INFO', true).'"', $auto_add = 0);
		}

		// Apply & Reload button   ***   (Apply Type, is a special case of new that has not loaded custom fieds yet, due to type not defined on initial form load)
		$btn_name = $item->type_id ? 'apply' : 'apply_type';
		$btn_task = $item->type_id ? 'items.apply' : 'items.apply_type';
		$btn_title = !$isnew ? 'FLEXI_APPLY_N_RELOAD' : ($typesselected->id ? 'FLEXI_ADD' : 'FLEXI_APPLY_TYPE');

		//JToolBarHelper::apply($btn_task, $btn_title, false);

		$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
			$btn_title, $btn_name, $full_js="Joomla.submitbutton('".$btn_task."')", $msg_alert='', $msg_confirm='',
			$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="".$tip_class, $btn_icon="icon-save",
			'data-placement="right" title=""', $auto_add = 0);

		flexicontent_html::addToolBarDropMenu($btn_arr, 'apply_btns_group');


		// ***
		// *** Save buttons
		// ***
		$btn_arr = array();
		if (!$isnew || $item->version)
		{
			//JToolBarHelper::save('items.save');
			//JToolBarHelper::save2new('group.save2new'); //JToolBarHelper::custom( 'items.save2new', 'save2new.png', 'save2new.png', 'FLEXI_SAVE_AND_NEW', false );
			//JToolBarHelper::save2copy('group.save2copy'); //JToolBarHelper::custom( 'items.save2copy', 'save2copy.png', 'save2copy.png', 'FLEXI_SAVE_AS_COPY', false );

			$btn_name = 'save';
			$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
				'JSAVE', $btn_name, $full_js="Joomla.submitbutton('items.save')", $msg_alert='', $msg_confirm='',
				$btn_task='items.save', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="".$tip_class, $btn_icon="icon-save",
				'data-placement="bottom" title=""', $auto_add = 0);

			$btn_name = 'save2new';
			$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
				'FLEXI_SAVE_AND_NEW', $btn_name, $full_js="Joomla.submitbutton('group.save2new')", $msg_alert='', $msg_confirm='',
				$btn_task='group.save2new', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="".$tip_class, $btn_icon="icon-save-new",
				'data-placement="right" title="'.JText::_('FLEXI_SAVE_AND_NEW_INFO', true).'"', $auto_add = 0);

			$btn_name = 'save2copy';
			$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
				'FLEXI_SAVE_AS_COPY', $btn_name, $full_js="Joomla.submitbutton('group.save2copy')", $msg_alert='', $msg_confirm='',
				$btn_task='group.save2copy', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="".$tip_class, $btn_icon="icon-save-copy",
				'data-placement="right" title="'.JText::_('FLEXI_SAVE_AS_COPY_INFO', true).'"', $auto_add = 0);
		}
		flexicontent_html::addToolBarDropMenu($btn_arr, 'save_btns_group');

		JToolBarHelper::cancel('items.cancel');



		// ***********************
		// Add a preview button(s)
		// ***********************
		
		//$_sh404sef = JPluginHelper::isEnabled('system', 'sh404sef') && $config->get('sef');
		$_sh404sef = defined('SH404SEF_IS_RUNNING') && $config->get('sef');
		if ( $cid )
		{
			// Domain URL and autologin vars
			$server = JURI::getInstance()->toString(array('scheme', 'host', 'port'));
			$autologin = ''; //$params->get('autoflogin', 1) ? '&fcu='.$user->username . '&fcp='.$user->password : '';
			
			// We use 'isAdmin' check so that we can copy later without change, e.g. to a plugin
			$isAdmin = JFactory::getApplication()->isAdmin();
			
			// Create the non-SEF URL
			$item_url =
				FlexicontentHelperRoute::getItemRoute($item->id.':'.$item->alias, $categories[$item->catid]->slug)
				. ($item->language!='*' ? '&lang='.substr($item->language, 0,2) : '');
			
			// Check if we are in the backend, in the backend we need to set the application to the site app
			//if ( $isAdmin && !$_sh404sef )  JFactory::$application = JApplication::getInstance('site');
			
			// Create the SEF URL
			$item_url = //!$isAdmin && $_sh404sef  ?  Sh404sefHelperGeneral::getSefFromNonSef($item_url, $fullyQualified = true, $xhtml = false, $ssl = null) :
				JRoute::_($item_url);
			
			// Restore application to the admin app if we are in the backend
			//if  ( $isAdmin && !$_sh404sef )  JFactory::$application = JApplication::getInstance('administrator');
			
			// Check if we are in the backend again
			if ( $isAdmin )
			{
				// Remove administrator from URL as it is added even though we've set the application to the site app
				$admin_folder = str_replace(JURI::root(true),'',JURI::base(true));
				$item_url = str_replace($admin_folder.'/', '/', $item_url);
			}
			
			$previewlink     = /*$server .*/ $item_url. (strstr($item_url, '?') ? '&amp;' : '?') .'preview=1' . $autologin;
			//$previewlink     = str_replace('&amp;', '&', $previewlink);
			//$previewlink = JRoute::_(JURI::root() . FlexicontentHelperRoute::getItemRoute($item->id.':'.$item->alias, $categories[$item->catid]->slug)) .$autologin;
			
			// PREVIEW for latest version
			if ( !$params->get('use_versioning', 1) || ($item->version == $item->current_version && $item->version == $item->last_version) )
			{
				$toolbar->appendButton( 'Custom', '<button class="preview btn btn-small btn-fcaction btn-info spaced-btn" onClick="window.open(\''.$previewlink.'\');"><span title="'.JText::_('FLEXI_PREVIEW').'" class="icon-screen"></span>'.JText::_('FLEXI_PREVIEW').'</button>', 'preview' );
			}
			
			// PREVIEW for non-approved versions of the item, if they exist
			else
			{
				$btn_arr = array();

				// Add a preview button for (currently) LOADED version of the item
				$previewlink_loaded_ver = $previewlink .'&version='.$item->version;
				//$toolbar->appendButton( 'Custom',
				$btn_arr['preview_current'] = '<a class="toolbar btn btn-small" href="#" onclick="window.open(\''.$previewlink_loaded_ver.'\');" target="_blank"><span title="'.JText::_('FLEXI_PREVIEW').'" class="icon-screen"></span>'.JText::_('FLEXI_PREVIEW_FORM_LOADED_VERSION').' ['.$item->version.']</a>';
				//, 'preview' );

				// Add a preview button for currently ACTIVE version of the item
				$previewlink_active_ver = $previewlink .'&version='.$item->current_version;
				//$toolbar->appendButton( 'Custom',
				$btn_arr['preview_active'] = '<a class="toolbar btn btn-small" href="#" onclick="window.open(\''.$previewlink_active_ver.'\');" target="_blank"><span title="'.JText::_('FLEXI_PREVIEW').'" class="icon-screen"></span>'.JText::_('FLEXI_PREVIEW_FRONTEND_ACTIVE_VERSION').' ['.$item->current_version.']</a>';
				//, 'preview' );

				// Add a preview button for currently LATEST version of the item
				$previewlink_last_ver = $previewlink; //'&version='.$item->last_version;
				//$toolbar->appendButton( 'Custom',
				$btn_arr['preview_latest'] = '<a class="toolbar btn btn-small" href="#" onclick="window.open(\''.$previewlink_last_ver.'\');" target="_blank"><span title="'.JText::_('FLEXI_PREVIEW').'" class="icon-screen"></span>'.JText::_('FLEXI_PREVIEW_LATEST_SAVED_VERSION').' ['.$item->last_version.']</a>';
				//, 'preview' );

				flexicontent_html::addToolBarDropMenu($btn_arr, 'preview_btns_group', null);
			}
			JToolBarHelper::spacer();
			JToolBarHelper::divider();
			JToolBarHelper::spacer();
		}


		$btn_arr = array();
		$btn_arr['fc_actions'] = '';

		// ************************
		// Add modal layout editing
		// ************************
		if ($perms['cantemplates'])
		{
			$edit_layout = htmlspecialchars(JText::_('FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS'), ENT_QUOTES, 'UTF-8');
			JToolBarHelper::divider();
			if (!$isnew || $item->version)
			{
				$btn_name='edit_layout';
				$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
					'FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS', $btn_name, $full_js="var url = jQuery(this).attr('data-href'); fc_showDialog(url, 'fc_modal_popup_container', 0, 0, 0, 0, {title:'".$edit_layout."'}); return false;",
					$msg_alert='', $msg_confirm='',
					$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
					$btn_class="btn-fcaction".$tip_class, $btn_icon="icon-pencil",
					'data-placement="right" data-href="index.php?option=com_flexicontent&amp;view=template&amp;type=items&amp;tmpl=component&amp;ismodal=1&amp;folder='.$item->itemparams->get('ilayout', $tparams->get('ilayout', 'default')).
					'" title="Edit the display layout of this item. <br/><br/>Note: this layout maybe assigned to content types or other items, thus changing it will effect them too"', $auto_add = 0
				);
			}
		}


		// ************************
		// Add collaboration button
		// ************************

		$do_collaboration = 0; //JPluginHelper::isEnabled('system', 'flexisyspro');
		$com_mailto_found = file_exists(JPATH_SITE.DS.'components'.DS.'com_mailto'.DS.'helpers'.DS.'mailto.php');

		if ($com_mailto_found)
		{
			require_once(JPATH_SITE.DS.'components'.DS.'com_mailto'.DS.'helpers'.DS.'mailto.php');
			$status = 'width=700,height=360,menubar=yes,resizable=yes';
			$btn_title = JText::_('FLEXI_COLLABORATE_EMAIL_ABOUT_THIS_ITEM');
			$btn_info  = JText::_('FLEXI_COLLABORATE_EMAIL_ABOUT_THIS_ITEM_INFO');
			$send_form_url = 'index.php?option=com_flexicontent&tmpl=component'
				.'&task=call_extfunc&exttype=plugins&extfolder=system&extname=flexisyspro&extfunc=collaborate_form'
				.'&content_id='.$item->id;
			$full_js = $do_collaboration
				? "var url = jQuery(this).attr('data-href'); fc_showDialog(url, 'fc_modal_popup_container', 0, 800, 800, 0, {title:'" . JText::_($btn_title) . "'}); return false;"
				: "var box = jQuery('#fc_available_in_pro'); fc_file_props_handle = fc_showAsDialog(box, 480, 320, null, {title:'" . JText::_($btn_title) . "'}); return false;";

			$btn_name='collaborate';
			$btn_arr[$btn_name] = '<div id="fc_available_in_pro" style="display: none;">Available in FLEXIcontent PRO version</div>' . flexicontent_html::addToolBarButton(
					$btn_title, $btn_name, $full_js ,
					$msg_alert='', $msg_confirm='',
					$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
					$btn_class="btn-fcaction".$tip_class, $btn_icon="icon-mail",
					'data-placement="right" data-href="'.$send_form_url.'" title="Send email to other reviewers"', $auto_add = 0
				);
		}

		// Add Extra actions drop-down menu
		if (count($btn_arr) <= 2)
		{
			array_shift($btn_arr);
		}
		$drop_btn = '
			<button type="button" class="btn btn-small dropdown-toggle" data-toggle="dropdown">
				<span title="'.JText::_('FLEXI_ACTIONS').'" class="icon-menu"></span>
				'.JText::_('FLEXI_MORE').'
				<span class="caret"></span>
			</button>';
		flexicontent_html::addToolBarDropMenu($btn_arr, 'action_btns_group', $drop_btn);



		// **************************************************************************
		// Load any previous form, NOTE: Because of fieldgroup rendering other fields
		// this step must be done in seperate loop, placed before FIELD HTML creation
		// **************************************************************************
		
		$jcustom = $app->getUserState($form->option.'.edit.item.custom');
		foreach ($fields as $field)
		{
			if (!$field->iscore)
			{
				if ( isset($jcustom[$field->name]) ) {
					$field->value = array();
					foreach ($jcustom[$field->name] as $i => $_val)  $field->value[$i] = $_val;
				}
			}
		}
		
		
		// *****************************************************************************
		// (a) Apply Content Type Customization to CORE fields (label, description, etc)
		// (b) Create the edit html of the CUSTOM fields by triggering 'onDisplayField'
		// *****************************************************************************
		
		if ( $print_logging_info )  $start_microtime = microtime(true);
		foreach ($fields as $field)
		{
			FlexicontentFields::getFieldFormDisplay($field, $item, $user);
		}
		if ( $print_logging_info ) $fc_run_times['render_field_html'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;



		// **************************************************
		// Get tags used by the item and quick selection tags
		// **************************************************
		
		$usedtagsIds = $this->get( 'UsedtagsIds' );  // NOTE: This will normally return the already set versioned value of tags ($item->tags)
		$usedtagsdata = $model->getTagsByIds($usedtagsIds, $_indexed = false);
		
		$quicktagsIds = $tparams->get('quick_tags', array());
		$quicktagsdata = !empty($quicktagsIds) ? $model->getTagsByIds($quicktagsIds, $_indexed = true) : array();
		
		
		
		// *******************************
		// Get categories used by the item
		// *******************************
		
		if ($isnew) {
			// Case for preselected main category for new items
			$maincat = $item->catid ? $item->catid : $jinput->get('maincat', 0, 'int');
			if (!$maincat) {
				$maincat = $app->getUserStateFromRequest( $option.'.items.filter_cats', 'filter_cats', '', 'int' );
			}
			if ($maincat) {
				$selectedcats = array($maincat);
				$item->catid = $maincat;
			} else {
				$selectedcats = array();
			}
			
			if ( $tparams->get('cid_default') ) {
				$selectedcats = $tparams->get('cid_default');
			}
			if ( $tparams->get('catid_default') ) {
				$item->catid = $tparams->get('catid_default');
			}
			
		} else {
			// NOTE: This will normally return the already set versioned value of categories ($item->categories)
			$selectedcats = $this->get( 'Catsselected' );
		}
		
		
		
		// *********************************************************************************************
		// Build select lists for the form field. Only few of them are used in J1.6+, since we will use:
		// (a) form XML file to declare them and then (b) getInput() method form field to create them
		// *********************************************************************************************
		
		// Encode (UTF-8 charset) HTML entities form data so that they can be set as form field values
		// we do this after creating the description field which is used un-encoded inside 'textarea' tags
		JFilterOutput::objectHTMLSafe( $item, ENT_QUOTES, $exclude_keys = '' );  // Maybe exclude description text ?
		
		$lists = array();
		$prettycheckable_added = flexicontent_html::loadFramework('prettyCheckable');  // Get if prettyCheckable was loaded
		
		// build state list
		$non_publishers_stategrp    = $perms['isSuperAdmin'] || $item->state==-3 || $item->state==-4 ;
		$special_privelege_stategrp = ($item->state==2 || $perms['canarchive']) || ($item->state==-2 || $perms['candelete']) ;
		
		$state = array();
		// Using <select> groups
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
		if ($item->state==-3 || $perms['isSuperAdmin'])  $state[] = JHTML::_('select.option',  -3, JText::_( 'FLEXI_PENDING' ) );
		if ($item->state==-4 || $perms['isSuperAdmin'])  $state[] = JHTML::_('select.option',  -4, JText::_( 'FLEXI_TO_WRITE' ) );
		
		// Special access states
		if ( $special_privelege_stategrp ) {
			$state[] = JHTML::_('select.optgroup', '' );
			$state[] = JHTML::_('select.optgroup', JText::_( 'FLEXI_SPECIAL_ACTION_STATES' ) );
		}
		if ($item->state==2  || $perms['canarchive']) $state[] = JHTML::_('select.option',  2, JText::_( 'FLEXI_ARCHIVED' ) );
		if ($item->state==-2 || $perms['candelete'])  $state[] = JHTML::_('select.option', -2, JText::_( 'FLEXI_TRASHED' ) );
		
		// Close last <select> group
		if ($non_publishers_stategrp || $special_privelege_stategrp)
			$state[] = JHTML::_('select.optgroup', '');
		
		$fieldname = 'jform[state]';
		$elementid = 'jform_state';
		$class = 'use_select2_lib';
		$attribs = 'class="'.$class.'"';
		$lists['state'] = JHTML::_('select.genericlist', $state, $fieldname, $attribs, 'value', 'text', $item->state, $elementid );
		if (!FLEXI_J16GE) $lists['state'] = str_replace('<optgroup label="">', '</optgroup>', $lists['state']);
		
		// build featured flag
		$fieldname = 'jform[featured]';
		$elementid = 'jform_featured';
		/*
		$options = array();
		$options[] = JHTML::_('select.option',  0, JText::_( 'FLEXI_NO' ) );
		$options[] = JHTML::_('select.option',  1, JText::_( 'FLEXI_YES' ) );
		$attribs = FLEXI_J16GE ? ' style ="float:none!important;" '  :  '';   // this is not right for J1.5' style ="float:left!important;" ';
		$lists['featured'] = JHTML::_('select.radiolist', $options, $fieldname, $attribs, 'value', 'text', $item->featured, $elementid);
		*/
		$classes = !$prettycheckable_added ? '' : ' use_prettycheckable ';
		$attribs = ' class="'.$classes.'" ';
		$i = 1;
		$options = array(0=>JText::_( 'FLEXI_NO' ), 1=>JText::_( 'FLEXI_YES' ) );
		$lists['featured'] = '';
		foreach ($options as $option_id => $option_label)
		{
			$checked = $option_id==$item->featured ? ' checked="checked"' : '';
			$elementid_no = $elementid.'_'.$i;
			if (!$prettycheckable_added) $lists['featured'] .= '<label class="fccheckradio_lbl" for="'.$elementid_no.'">';
			$extra_params = !$prettycheckable_added ? '' : ' data-labeltext="'.JText::_($option_label).'" data-labelPosition="right" data-customClass="fcradiocheck"';
			$lists['featured'] .= ' <input type="radio" id="'.$elementid_no.'" data-element-grpid="'.$elementid
				.'" name="'.$fieldname.'" '.$attribs.' value="'.$option_id.'" '.$checked.$extra_params.' />';
			if (!$prettycheckable_added) $lists['featured'] .= '&nbsp;'.JText::_($option_label).'</label>';
			$i++;
		}
		
		// build version approval list
		$fieldname = 'jform[vstate]';
		$elementid = 'jform_vstate';
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
			$extra_params = !$prettycheckable_added ? '' : ' data-labeltext="'.JText::_($option_label).'" data-labelPosition="right" data-customClass="fcradiocheck"';
			$lists['vstate'] .= ' <input type="radio" id="'.$elementid_no.'" data-element-grpid="'.$elementid
				.'" name="'.$fieldname.'" '.$attribs.' value="'.$option_id.'" '.$checked.$extra_params.' />';
			if (!$prettycheckable_added) $lists['vstate'] .= '&nbsp;'.JText::_($option_label).'</label>';
			$i++;
		}
		
		
		// check access level exists
		$level_name = flexicontent_html::userlevel(null, $item->access, null, null, null, $_createlist = false);
		if (empty($level_name))
		{
			JFactory::getApplication()->enqueueMessage(JText::sprintf('FLEXI_ABOUT_INVALID_ACCESS_LEVEL_PLEASE_SAVE_NEW', $item->access, 'Public'), 'warning');
			$document->addScriptDeclaration("jQuery(document).ready(function() { jQuery('#jform_access').val(1).trigger('change'); });");
		}
		
		
		// build field for notifying subscribers
		if ( !$subscribers )
		{
			$lists['notify'] = !$isnew ? '<div class="alert alert-info fc-small fc-iblock">'.JText::_('FLEXI_NO_SUBSCRIBERS_EXIST').'</div>' : '';
		} else {
			// b. Check if notification emails to subscribers , were already sent during current session
			$subscribers_notified = $session->get('subscribers_notified', array(),'flexicontent');
			if ( !empty($subscribers_notified[$item->id]) ) {
				$lists['notify'] = '<div class="alert alert-info fc-small fc-iblock">'.JText::_('FLEXI_SUBSCRIBERS_ALREADY_NOTIFIED').'</div>';
			} else {
				// build favs notify field
				$fieldname = 'jform[notify]';
				$elementid = 'jform_notify';
				/*
				$attribs = FLEXI_J16GE ? ' style ="float:none!important;" '  :  '';   // this is not right for J1.5' style ="float:left!important;" ';
				$lists['notify'] = '<input type="checkbox" name="jform[notify]" id="jform_notify" '.$attribs.' /> '. $lbltxt;
				*/
				$classes = !$prettycheckable_added ? '' : ' use_prettycheckable ';
				$attribs = ' class="'.$classes.'" ';
				$lbltxt = $subscribers .' '. JText::_( $subscribers>1 ? 'FLEXI_SUBSCRIBERS' : 'FLEXI_SUBSCRIBER' );
				if (!$prettycheckable_added) $lists['notify'] .= '<label class="fccheckradio_lbl" for="'.$elementid.'">';
				$extra_params = !$prettycheckable_added ? '' : ' data-labeltext="'.$lbltxt.'" data-labelPosition="right" data-customClass="fcradiocheck"';
				$lists['notify'] = ' <input type="checkbox" id="'.$elementid.'" data-element-grpid="'.$elementid
					.'" name="'.$fieldname.'" '.$attribs.' value="1" '.$extra_params.' checked="checked" />';
				if (!$prettycheckable_added) $lists['notify'] .= '&nbsp;'.$lbltxt.'</label>';
			}
		}
		
		// Retrieve author configuration
		$authorparams = flexicontent_db::getUserConfig($user->id);
		
		// Get author's maximum allowed categories per item and set js limitation
		$max_cat_assign = !$authorparams ? 0 : intval($authorparams->get('max_cat_assign',0));
		$document->addScriptDeclaration('
			max_cat_assign_fc = '.$max_cat_assign.';
			existing_cats_fc  = ["'.implode('","',$selectedcats).'"];
		');
		JText::script('FLEXI_TOO_MANY_ITEM_CATEGORIES',true);
		
		
		// Creating categorories tree for item assignment, we use the 'create' privelege
		$actions_allowed = array('core.create');

		// Featured categories form field
		$featured_cats_parent = $params->get('featured_cats_parent', 0);
		$featured_cats = array();
		$enable_featured_cid_selector = $perms['multicat'] && $perms['canchange_featcat'];
		if ( $featured_cats_parent )
		{
			$featured_tree = flexicontent_cats::getCategoriesTree($published_only=1, $parent_id=$featured_cats_parent, $depth_limit=0);
			$disabled_cats = $params->get('featured_cats_parent_disable', 1) ? array($featured_cats_parent) : array();
			
			$featured_sel = array();
			foreach($selectedcats as $item_cat) if (isset($featured_tree[$item_cat])) $featured_sel[] = $item_cat;
			
			$class  = "use_select2_lib";
			$attribs  = 'class="'.$class.'" multiple="multiple" size="8"';
			$attribs .= $enable_featured_cid_selector ? '' : ' disabled="disabled"';
			$fieldname = 'jform[featured_cid][]';
			
			// Skip main category from the selected cats to allow easy change of it
			$featured_sel_nomain = array();
			foreach($featured_sel as $cat_id) if ($cat_id!=$item->catid) $featured_sel_nomain[] = $cat_id;
			
			$lists['featured_cid'] = ($enable_featured_cid_selector ? '' : '<label class="label" style="float:none; margin:0 6px 0 0 !important;">locked</label>').
				flexicontent_cats::buildcatselect($featured_tree, $fieldname, $featured_sel_nomain, 3, $attribs, true, true,	$actions_allowed,
					$require_all=true, $skip_subtrees=array(), $disable_subtrees=array(), $custom_options=array(), $disabled_cats
				);
		}
		else{
			// Do not display, if not configured or not allowed to the user
			$lists['featured_cid'] = false;
		}
		
		
		// Multi-category form field, for user allowed to use multiple categories
		$lists['cid'] = '';
		$enable_cid_selector = $perms['multicat'] && $perms['canchange_seccat'];
		if ( 1 )
		{
			if ($tparams->get('cid_allowed_parent'))
			{
				$cid_tree = flexicontent_cats::getCategoriesTree($published_only=1, $parent_id=$tparams->get('cid_allowed_parent'), $depth_limit=0);
				$disabled_cats = $tparams->get('cid_allowed_parent_disable', 1) ? array($tparams->get('cid_allowed_parent')) : array();
			}
			else
			{
				$cid_tree = & $categories;
				$disabled_cats = array();
			}
			
			// Get author's maximum allowed categories per item and set js limitation
			$max_cat_assign = !$authorparams ? 0 : intval($authorparams->get('max_cat_assign',0));
			$document->addScriptDeclaration('
				max_cat_assign_fc = '.$max_cat_assign.';
				existing_cats_fc  = ["'.implode('","',$selectedcats).'"];
			');
			
			$class  = "mcat use_select2_lib";
			$class .= $max_cat_assign ? " validate-fccats" : " validate";
			
			$attribs  = 'class="'.$class.'" multiple="multiple" size="20"';
			$attribs .= $enable_cid_selector ? '' : ' disabled="disabled"';
			
			$fieldname = 'jform[cid][]';
			$skip_subtrees = $featured_cats_parent ? array($featured_cats_parent) : array();
			
			// Skip main category from the selected secondary cats to allow easy change of it
			$selectedcats_nomain = array();
			foreach($selectedcats as $cat_id) if ($cat_id!=$item->catid) $selectedcats_nomain[] = $cat_id;
			
			$lists['cid'] = ($enable_cid_selector ? '' : '<label class="label" style="float:none; margin:0 6px 0 0 !important;">locked</label>').
				flexicontent_cats::buildcatselect($cid_tree, $fieldname, $selectedcats_nomain, false, $attribs, true, true, $actions_allowed,
					$require_all=true, $skip_subtrees, $disable_subtrees=array(), $custom_options=array(), $disabled_cats
				);
		}
		else {
			if ( count($selectedcats)>1 ) {
				foreach ($selectedcats as $catid) {
					$cat_titles[$catid] = $globalcats[$catid]->title;
				}
				$lists['cid'] .= implode(', ', $cat_titles);
			} else {
				$lists['cid'] = false;
			}
		}
		
		
		// Main category form field
		$class = 'scat use_select2_lib';
		if ($perms['multicat']) {
			$class .= ' validate-catid';
		} else {
			$class .= ' required';
		}
		$attribs = 'class="'.$class.'"';
		$fieldname = 'jform[catid]';
		
		$enable_catid_selector = ($isnew && !$tparams->get('catid_default')) || (!$isnew && empty($item->catid)) || $perms['canchange_cat'];
		
		if ($tparams->get('catid_allowed_parent'))
		{
			$catid_tree = flexicontent_cats::getCategoriesTree($published_only=1, $parent_id=$tparams->get('catid_allowed_parent'), $depth_limit=0);
			$disabled_cats = $tparams->get('catid_allowed_parent_disable', 1) ? array($tparams->get('catid_allowed_parent')) : array();
		} else {
			$catid_tree = & $categories;
			$disabled_cats = array();
		}
		
		$lists['catid'] = false;
		if ( !empty($catid_tree) ) {
			$disabled = $enable_catid_selector ? '' : ' disabled="disabled"';
			$attribs .= $disabled;
			$lists['catid'] = ($enable_catid_selector ? '' : '<label class="label" style="float:none; margin:0 6px 0 0 !important;">locked</label>').
				flexicontent_cats::buildcatselect($catid_tree, $fieldname, $item->catid, 2, $attribs, true, true, $actions_allowed,
					$require_all=true, $skip_subtrees=array(), $disable_subtrees=array(), $custom_options=array(), $disabled_cats
				);
		} else if ( !$isnew && $item->catid ) {
			$lists['catid'] = $globalcats[$item->catid]->title;
		}
		
		
		//buid types selectlist
		$class   = 'required use_select2_lib';
		$attribs = 'class="'.$class.'"';
		$fieldname = 'jform[type_id]';
		$elementid = 'jform_type_id';
		$lists['type'] = flexicontent_html::buildtypesselect($types, $fieldname, $typesselected->id, 1, $attribs, $elementid, $check_perms=true );
		
		
		//build languages list
		$allowed_langs = !$authorparams ? null : $authorparams->get('langs_allowed',null);
		$allowed_langs = !$allowed_langs ? null : FLEXIUtilities::paramToArray($allowed_langs);
		if (!$isnew && $allowed_langs) $allowed_langs[] = $item->language;

		// We will not use the default getInput() function of J1.6+ since we want to create a radio selection field with flags
		// we could also create a new class and override getInput() method but maybe this is an overkill, we may do it in the future
		$lists['languages'] = flexicontent_html::buildlanguageslist('jform[language]', 'class="use_select2_lib"', $item->language, 2, $allowed_langs);
		
		// Label for current item state: published, unpublished, archived etc
		switch ($item->state) {
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
		
		if ( JHTML::_('date', $item->publish_down , 'Y') <= 1969 || $item->publish_down == $db->getNullDate() || empty($item->publish_down) ) {
			$form->setValue('publish_down', null, ''/*JText::_( 'FLEXI_NEVER' )*/);  // Setting to text will break form date element 
		}
		
		
		// ****************************
		// Handle Template related work
		// ****************************

		// (a) Get Content Type allowed templates
		$allowed_tmpls = $tparams->get('allowed_ilayouts');
		$type_default_layout = $tparams->get('ilayout', 'default');

		// (b) Load language file of currently selected template
		$_ilayout = $item->itemparams->get('ilayout', $type_default_layout);
		if ($_ilayout) FLEXIUtilities::loadTemplateLanguageFile( $_ilayout );

		// (c) Get the item layouts, checking template of current layout for modifications
		$themes			= flexicontent_tmpl::getTemplates($_ilayout);
		$tmpls_all	= $themes->items;

		// (d) Get allowed layouts adding default layout (unless all templates are already allowed ... array is empty)
		if ( empty($allowed_tmpls) ) {
			$allowed_tmpls = array();
		}
		if ( ! is_array($allowed_tmpls) ) {
			$allowed_tmpls = explode("|", $allowed_tmpls);
		}
		if ( count ($allowed_tmpls) && !in_array( $type_default_layout, $allowed_tmpls ) ) $allowed_tmpls[] = $type_default_layout;

		// (e) Create array of template data according to the allowed templates for current content type
		if ( count($allowed_tmpls) ) {
			foreach ($tmpls_all as $tmpl) {
				if (in_array($tmpl->name, $allowed_tmpls) ) {
					$tmpls[]= $tmpl;
				}
			}
		} else {
			$tmpls= $tmpls_all;
		}

		// (f) Create JForm for the layout and apply Layout parameters values into the fields
		foreach ($tmpls as $tmpl)
		{
			if ($tmpl->name != $_ilayout) continue;
			
			$jform = new JForm('com_flexicontent.template.item', array('control' => 'jform', 'load_data' => false));
			$jform->load($tmpl->params);
			$tmpl->params = $jform;
			foreach ($tmpl->params->getGroup('attribs') as $field)
			{
				$fieldname = $field->fieldname;
				$value = $item->itemparams->get($fieldname);
				if (strlen($value)) $tmpl->params->setValue($fieldname, 'attribs', $value);
			}
		}

		// ******************************
		// Assign data to VIEW's template
		// ******************************
		$this->document = $document;
		$this->lists  = $lists;
		$this->row    = $item;
		$this->form   = $form;
		if ($useAssocs)  $this->assignRef('lang_assocs', $langAssocs);
		$this->assignRef('langs'        , $langs);
		$this->assignRef('typesselected', $typesselected);
		$this->assignRef('published'		, $published);
		$this->assignRef('nullDate'			, $nullDate);
		$this->assignRef('subscribers'	, $subscribers);
		$this->assignRef('fields'				, $fields);
		$this->assignRef('versions'			, $versions);
		$this->assignRef('ratings'			, $ratings);
		$this->assignRef('pagecount'		, $pagecount);
		$this->assignRef('params'				, $params);
		$this->assignRef('tparams'			, $tparams);
		$this->assignRef('tmpls'				, $tmpls);
		$this->assignRef('usedtagsdata'  , $usedtagsdata);
		$this->assignRef('quicktagsdata' , $quicktagsdata);
		$this->assignRef('perms'				, $perms);
		$this->assignRef('current_page'	, $current_page);
		
		// Clear custom form data from session
		$app->setUserState($form->option.'.edit.item.custom', false);
		$app->setUserState($form->option.'.edit.item.jfdata', false);
		$app->setUserState($form->option.'.edit.item.unique_tmp_itemid', false);
		
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
		$user = JFactory::getUser();	// get current user
		$permission = FlexicontentHelperPerm::getPerm();  // get global perms
		$model = $this->getModel();
		
		$perms 	= array();
		$perms['isSuperAdmin'] = $permission->SuperAdmin;
		$perms['multicat']     = $permission->MultiCat;
		$perms['cantags']      = $permission->CanUseTags;
		$perms['cancreatetags']= $permission->CanCreateTags;
		$perms['canparams']    = $permission->CanParams;
		$perms['cantemplates'] = $permission->CanTemplates;
		$perms['canarchive']   = $permission->CanArchives;
		$perms['canright']     = $permission->CanRights;
		$perms['canacclvl']    = $permission->CanAccLvl;
		$perms['canversion']   = $permission->CanVersion;
		$perms['editcreationdate'] = $permission->EditCreationDate;
		
		// Get general edit/publish/delete permissions (we will override these for existing items)
		$perms['canedit']    = $permission->CanEdit    || $permission->CanEditOwn;
		$perms['canpublish'] = $permission->CanPublish || $permission->CanPublishOwn;
		$perms['candelete']  = $permission->CanDelete  || $permission->CanDeleteOwn;
		
		// Get permissions for changing item's category assignments
		$perms['canchange_cat'] = $permission->CanChangeCat;
		$perms['canchange_seccat'] = $permission->CanChangeSecCat;
		$perms['canchange_featcat'] = $permission->CanChangeFeatCat;
		
		// OVERRIDE global with existing item's atomic settings
		if ( $model->get('id') )
		{
			// the following include the "owned" checks too
			$itemAccess = $model->getItemAccess();
			$perms['canedit']    = $itemAccess->get('access-edit');  // includes temporary editable via session's 'rendered_uneditable'
			$perms['canpublish'] = $itemAccess->get('access-edit-state');  // includes (frontend) check (and allows) if user is editing via a coupon and has 'edit.state.own'
			$perms['candelete']  = $itemAccess->get('access-delete');
		}
		
		// Get can change categories ACL access
		$type = $this->get( 'Typesselected' );
		if ( $type->id )
		{
			$perms['canchange_cat']     = $user->authorise('flexicontent.change.cat', 'com_flexicontent.type.' . $type->id);
			$perms['canchange_seccat']  = $user->authorise('flexicontent.change.cat.sec', 'com_flexicontent.type.' . $type->id);
			$perms['canchange_featcat'] = $user->authorise('flexicontent.change.cat.feat', 'com_flexicontent.type.' . $type->id);
		}
		
		return $perms;
	}
	
}
?>

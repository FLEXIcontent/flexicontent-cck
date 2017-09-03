<?php
/**
 * @version 1.5 stable $Id: view.html.php 1958 2014-09-16 21:37:52Z ggppdk $
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
 * View class for the FLEXIcontent categories screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewImport extends JViewLegacy
{

	function display( $tpl = null )
	{
		// ********************
		// Initialise variables
		// ********************
		
		$app     = JFactory::getApplication();
		$jinput  = $app->input;
		$option  = $jinput->get('option', '', 'cmd');
		$view    = $jinput->get('view', '', 'cmd');
		$task    = $jinput->get('task', '', 'cmd');
		
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$user     = JFactory::getUser();
		$db       = JFactory::getDbo();
		$document = JFactory::getDocument();
		$session  = JFactory::getSession();
		
		// Get model
		$model = $this->getModel();
		
		// Some flags
		$has_zlib = function_exists ( "zlib_encode" ); //version_compare(PHP_VERSION, '5.4.0', '>=');
		
		// Get session information
		$conf  = $session->get('csvimport_config', "", 'flexicontent');
		$conf  = unserialize( $conf ? ($has_zlib ? zlib_decode(base64_decode($conf)) : base64_decode($conf)) : "" );
		
		$lineno = $session->get('csvimport_lineno', 999999, 'flexicontent');
		$session->set('csvimport_parse_log', null, 'flexicontent');  // This is the flag if CSV file has been parsed (import form already submitted), thus to display the imported data
		
		
		// **************************
		// Add css and js to document
		// **************************
		
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', FLEXI_VHASH);
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x_rtl.css', FLEXI_VHASH);
		
		// Add JS frameworks
		flexicontent_html::loadFramework('select2');
		$prettycheckable_added = flexicontent_html::loadFramework('prettyCheckable');
		flexicontent_html::loadFramework('flexi-lib');
		flexicontent_html::loadFramework('flexi-lib-form');

		// Add js function to overload the joomla submitform validation
		JHtml::_('behavior.formvalidation');  // load default validation JS to make sure it is overriden
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);
		
		
		
		// *****************************
		// Get user's global permissions
		// *****************************
		
		$perms = FlexicontentHelperPerm::getPerm();
		
		
		
		// ************************
		// Create Submenu & Toolbar
		// ************************
		
		// Create Submenu (and also check access to current view)
		FLEXIUtilities::ManagerSideMenu('CanImport');
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_IMPORT' );
		$site_title = $document->getTitle();
		JToolbarHelper::title( $doc_title, 'import' );
		$document->setTitle($doc_title .' - '. $site_title);
		
		// Create the toolbar
		$toolbar = JToolbar::getInstance('toolbar');
		
		if ( !empty($conf) ) {
			if ($task!='processcsv') {
				$ctrl_task = 'import.processcsv';
				$import_btn_title = empty($lineno) ? 'FLEXI_IMPORT_START_TASK' : 'FLEXI_IMPORT_CONTINUE_TASK';
				JToolbarHelper::custom( $ctrl_task, 'save.png', 'save.png', $import_btn_title, $list_check = false );
			}
			$ctrl_task = 'import.clearcsv';
			JToolbarHelper::custom( $ctrl_task, 'cancel.png', 'cancel.png', 'FLEXI_IMPORT_CLEAR_TASK', $list_check = false );
		} else {
			$ctrl_task = 'import.initcsv';
			JToolbarHelper::custom( $ctrl_task, 'import.png', 'import.png', 'FLEXI_IMPORT_PREPARE_TASK', $list_check = false );
			$ctrl_task = 'import.testcsv';
			JToolbarHelper::custom( $ctrl_task, 'test.png', 'test.png', 'FLEXI_IMPORT_TEST_FILE_FORMAT', $list_check = false );
		}
		//JToolbarHelper::Back();
		if ($perms->CanConfig) {
			JToolbarHelper::divider(); JToolbarHelper::spacer();
			$session = JFactory::getSession();
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolbarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}
		
		
			
		// Get types
		$types = flexicontent_html::getTypesList( $_type_ids=false, $_check_perms = false, $_published=true);
		
		// Get Languages
		$languages = FLEXIUtilities::getLanguages('code');
		
		// Get categories
		global $globalcats;
		$categories = $globalcats;
		
		
		// ************************************
		// Decide layout to load: 'import*.php'
		// ************************************
		
		$this->setLayout('import');
		$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;
		
		
		// Execute the import task, load the log-like AJAX-based layout (import_process.php), to display results including any warnings
		if ( !empty($conf) && $task=='processcsv' )
		{
			$this->conf = $conf;
			parent::display('process');
			return;
		}
		
		// Configuration has been parsed, display a 'preview' layout:  (import_list.php)
		else if ( !empty($conf) )
		{
			$this->conf = $conf;
			$this->cparams = $cparams;
			$this->types = $types;
			$this->languages = $languages;
			$this->categories = $globalcats;
			parent::display('list');
			return;
		}
		
		// Session config is empty, means import form has not been submited, display the form
		// We will display import form which is not 'default.php', it is 'import.php'
		// else ...
		
		
		// Check is session table DATA column is not mediumtext (16MBs, it can be 64 KBs ('text') in some sites that were not properly upgraded)
		$tblname  = 'session';
		$dbprefix = $app->getCfg('dbprefix');
		$dbname   = $app->getCfg('db');
		$db->setQuery("SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '".$dbname."' AND TABLE_NAME = '".$dbprefix.$tblname."'");
		$jession_coltypes = $db->loadAssocList('COLUMN_NAME');
		$_dataColType = strtolower($jession_coltypes['data']['DATA_TYPE']);
		$_dataCol_wrongSize = ($_dataColType != 'mediumtext') && ($_dataColType != 'longtext');
		
		// If data type is "text" it is safe to assume that it can be converted to "mediumtext",
		// since "text" means that session table is not memory storage,
		// plus it is already stored externally aka operation will be quick ?
		/*if ($_dataCol_wrongSize && $_dataColType == 'text')
		{
			$db->setQuery("ALTER TABLE `#__session` MODIFY `data` MEDIUMTEXT");
			$db->execute();
			$_dataCol_wrongSize = false;
		}*/
		if ($_dataCol_wrongSize) {
			$app->enqueueMessage("Joomla DB table: <b>'session'</b> has a <b>'data'</b> column with type: <b>'".$_dataColType."'</b>, instead of expected type <b>'mediumtext'</b>. Trying to import large data files may fail", "notice");
		}
		
		
		$formvals = array();
		
		// Retrieve Basic configuration
		$formvals['id_col']             = $model->getState('id_col');
		$formvals['type_id']  = $model->getState('type_id');
		$formvals['language'] = $model->getState('language');
		$formvals['state']    = $model->getState('state');
		$formvals['access']   = $model->getState('access');
		
		// Main and secondary categories, tags
		$formvals['maincat']     = $model->getState('maincat');
		$formvals['maincat_col'] = $model->getState('maincat_col');
		$formvals['seccats']     = $model->getState('seccats');
		$formvals['seccats_col'] = $model->getState('seccats_col');
		$formvals['tags_col']    = $model->getState('tags_col');
		
		// Publication: Author/modifier
		$formvals['created_by_col']  = $model->getState('created_by_col');
		$formvals['modified_by_col'] = $model->getState('modified_by_col');
		
		// Publication: META data
		$formvals['metadesc_col'] = $model->getState('metadesc_col');
		$formvals['metakey_col']  = $model->getState('metakey_col');
		
		
		// Publication: dates
		$formvals['modified_col'] = $model->getState('modified_col');
		$formvals['created_col']  = $model->getState('modified_col');
		$formvals['publish_up_col']   = $model->getState('publish_up_col');
		$formvals['publish_down_col'] = $model->getState('publish_down_col');
		
		
		// Advanced configuration
		$formvals['ignore_unused_cols'] = $model->getState('ignore_unused_cols');
		$formvals['items_per_step']     = $model->getState('items_per_step');
		
		
		// CSV file format
		$formvals['mval_separator']   = $model->getState('mval_separator');
		$formvals['mprop_separator']  = $model->getState('mprop_separator');
		$formvals['field_separator']  = $model->getState('field_separator');
		$formvals['enclosure_char']   = $model->getState('enclosure_char');
		$formvals['record_separator'] = $model->getState('record_separator');
		$formvals['debug_records']    = $model->getState('debug_records');
		
		
		
		// ******************
		// Create form fields
		// ******************
		$lists['type_id'] = flexicontent_html::buildtypesselect($types, 'type_id', $formvals['type_id'], true, 'class="required use_select2_lib"', 'type_id');
		
		$actions_allowed = array('core.create');  // Creating categorories tree for item assignment, we use the 'create' privelege
		
		// build the main category select list
		$attribs = 'class="use_select2_lib required"';
		$fieldname = 'maincat';
		$lists['maincat'] = flexicontent_cats::buildcatselect($categories, $fieldname, $formvals['maincat'], 2, $attribs, false, true, $actions_allowed);
		
		// build the secondary categories select list
		$class  = "use_select2_lib";
		$attribs = 'multiple="multiple" size="10" class="'.$class.'"';
		$fieldname = 'seccats[]';
		$lists['seccats'] = flexicontent_cats::buildcatselect($categories, $fieldname, $formvals['seccats'], false, $attribs, false, true,
			$actions_allowed, $require_all=true);
		
		
		// build languages list
		// Retrieve author configuration
		$authorparams = flexicontent_db::getUserConfig($user->id);
		
		$allowed_langs = $authorparams->get('langs_allowed',null);
		$allowed_langs = !$allowed_langs ? null : FLEXIUtilities::paramToArray($allowed_langs);
		
		// We will not use the default getInput() function of J1.6+ since we want to create a radio selection field with flags
		// we could also create a new class and override getInput() method but maybe this is an overkill, we may do it in the future
		$lists['languages'] = flexicontent_html::buildlanguageslist('language'
			, ' style="vertical-align:top;" onchange="var m=jQuery(\'#fc_import_about_langcol\'); this.value ? m.hide(600) : m.show(600);"'
			, $formvals['language'], 6/*'- '.JText::_('FLEXI_USE_LANGUAGE_COLUMN').' -'*/, $allowed_langs, $published_only=true
			, $disable_langs=null, $add_all=true, $conf=array('required'=>true)
		).'
			<span class="fc-mssg-inline fc-note fc-nobgimage" id="fc_import_about_langcol" style="display:none;">
				'.JText::_('FLEXI_USE_LANGUAGE_COLUMN_TIP').'
			</span>';

		$lists['states'] = flexicontent_html::buildstateslist('state', ' style="vertical-align:top;" onchange="var m=jQuery(\'#fc_import_about_statecol\'); this.value ? m.hide(600) : m.show(600);"'
			, $formvals['state'], 2/*'- '.JText::_('FLEXI_USE_STATE_COLUMN').' -'*/).
			'<span class="fc-mssg-inline fc-note fc-nobgimage" id="fc_import_about_statecol" style="display:none;">
				'.JText::_('FLEXI_USE_STATE_COLUMN_TIP').'
			</span>';
		
		// build access level filter
		$access_levels = JHtml::_('access.assetgroups');
		array_unshift($access_levels, JHtml::_('select.option', '0', "Use 'access' column") );
		array_unshift($access_levels, JHtml::_('select.option', '', 'FLEXI_SELECT_ACCESS_LEVEL') );
		$fieldname = 'access';  // make multivalue
		$elementid = 'access';
		$attribs = 'class="required use_select2_lib"';
		$lists['access'] = JHtml::_('select.genericlist', $access_levels, $fieldname, $attribs, 'value', 'text', $formvals['access'], $elementid, $translate=true );
		
		// Ignore warnings because component may not be installed
		$warnHandlers = JERROR::getErrorHandling( E_WARNING );
		JERROR::setErrorHandling( E_WARNING, 'ignore' );
		
		// Reset the warning handler(s)
		foreach( $warnHandlers as $mode )  JERROR::setErrorHandling( E_WARNING, $mode );
		
		
		// ********************************************************************************
		// Get field names (from the header line (row 0), and remove it form the data array
		// ********************************************************************************
		$file_field_types_list = '"image","file"';
		$q = 'SELECT id, name, label, field_type FROM #__flexicontent_fields AS fi'
			//.' JOIN #__flexicontent_fields_type_relations AS ftrel ON ftrel.field_id = fi.id AND ftrel.type_id='.$type_id;
			.' WHERE fi.field_type IN ('. $file_field_types_list .')';
		$db->setQuery($q);
		$file_fields = $db->loadObjectList('name');
		
		//assign data to template
		$this->model = $model;
		$this->lists = $lists;
		$this->user = $user;
		$this->cparams = $cparams;
		$this->file_fields = $file_fields;
		$this->formvals = $formvals;

		parent::display($tpl);
	}
}
?>

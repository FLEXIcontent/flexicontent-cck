<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

JLoader::register('FlexicontentViewBaseRecords', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/base/view_records.php');

/**
 * HTML View class for the FLEXIcontent import screen
 */
class FlexicontentViewImport extends FlexicontentViewBaseRecords
{
	public function display($tpl = null)
	{
		/**
		 * Initialise variables
		 */

		global $globalcats;
		$app      = JFactory::getApplication();
		$jinput   = $app->input;
		$document = JFactory::getDocument();
		$user     = JFactory::getUser();
		$cparams  = JComponentHelper::getParams('com_flexicontent');
		$session  = JFactory::getSession();
		$db       = JFactory::getDbo();

		$option   = $jinput->getCmd('option', '');
		$view     = $jinput->getCmd('view', '');
		$task     = $jinput->getCmd('task', '');
		$layout   = $jinput->getString('layout', 'default');

		$isAdmin  = $app->isClient('administrator');
		$isCtmpl  = $jinput->getCmd('tmpl') === 'component';

		// Some flags & constants
		$useAssocs = flexicontent_db::useAssociations();


		// Get model
		$model   = $this->getModel();

		// zlib may be available (loaded) if PHP >= 5.4.0
		$has_zlib = function_exists ('zlib_encode');

		// Get session information
		$this->conf   = $session->get('csvimport_config', "", 'flexicontent');
		$this->conf   = unserialize($this->conf ? ($has_zlib ? zlib_decode(base64_decode($this->conf)) : base64_decode($this->conf)) : '');
		$this->lineno = $session->get('csvimport_lineno', 999999, 'flexicontent');

		// This is the flag if CSV file has been parsed (import form already submitted), thus to display the imported data
		$session->set('csvimport_parse_log', null, 'flexicontent');



		/**
		 * Add css and js to document
		 */

		if ($layout !== 'indexer')
		{
			// Add css to document
			if ($isAdmin)
			{
				!JFactory::getLanguage()->isRtl()
					? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', array('version' => FLEXI_VHASH))
					: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', array('version' => FLEXI_VHASH));
				!JFactory::getLanguage()->isRtl()
					? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x.css' : 'j3x.css'), array('version' => FLEXI_VHASH))
					: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x_rtl.css' : 'j3x_rtl.css'), array('version' => FLEXI_VHASH));
			}
			else
			{
				!JFactory::getLanguage()->isRtl()
					? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', array('version' => FLEXI_VHASH))
					: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent_rtl.css', array('version' => FLEXI_VHASH));
			}

			// Add JS frameworks
			flexicontent_html::loadFramework('select2');
			$prettycheckable_added = flexicontent_html::loadFramework('prettyCheckable');
			flexicontent_html::loadFramework('flexi-lib');
			flexicontent_html::loadFramework('flexi-lib-form');

			// Load custom behaviours: form validation, popup tooltips
			JHtml::_('behavior.formvalidator');
			JHtml::_('bootstrap.tooltip');

			// Add js function to overload the joomla submitform validation
			$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', array('version' => FLEXI_VHASH));
			$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', array('version' => FLEXI_VHASH));
		}


		/**
		 * Create Submenu & Toolbar
		 */

		// Create Submenu (and also check access to current view)
		FLEXIUtilities::ManagerSideMenu('CanImport');

		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_IMPORT' );
		$site_title = $document->getTitle();
		JToolbarHelper::title( $doc_title, 'import' );
		$document->setTitle($doc_title .' - '. $site_title);

		// Create the toolbar
		$this->setToolbar();


		// Get types
		$types = flexicontent_html::getTypesList( $_type_ids=false, $_check_perms = false, $_published=true);

		// Get Languages
		$languages = FLEXIUtilities::getLanguages('code');

		// Get categories
		$categories = $globalcats;


		if (!$jinput->getCmd('nosidebar'))
		{
			$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;
		}

		/**
		 * Decide which layout to load: 'import.php' or 'import_*.php'
		 */



		/**
		 * Execute the import task, display a log-like AJAX-based layout,
		 * to display results including any warnings
		 * LAYOUT: -- import_process.php --
		 */
		if (!empty($this->conf) && $task === 'processcsv')
		{
			$this->setLayout('import');
			parent::display('process');
			return;
		}

		/**
		 * Configuration has been parsed, display a 'preview' layout
		 * LAYOUT: -- import_list.php --
		 */
		elseif (!empty($this->conf))
		{
			$this->cparams = $cparams;
			$this->types = $types;
			$this->languages = $languages;
			$this->categories = $globalcats;

			// Load import_list.php
			$this->setLayout('import');
			parent::display('list');
			return;
		}

		/**
		 * Session config is empty, means import form has not been submited
		 * We will display import form which is 'import.php' if $tpl is empty
		 * LAYOUT: -- import.php -- (or -- import_${tpl}.php -- )
		 */
		else
		{
			$this->setLayout('import');
		}


		/**
		 * Check is session table DATA column is not mediumtext (16MBs)
		 * As it can be 64 KBs ('text') in some sites that were not properly upgraded
		 * A text or small size will make importing large CSV files to fail
		 */

		$tblname  = 'session';
		$dbprefix = $app->getCfg('dbprefix');
		$dbname   = $app->getCfg('db');
		$db->setQuery(
			'SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS'
			. ' WHERE TABLE_SCHEMA = ' . $db->quote($dbname)
			. '  AND TABLE_NAME = ' . $db->quote($dbprefix . $tblname)
		);
		$jession_coltypes = $db->loadAssocList('COLUMN_NAME');
		$_dataColType = strtolower($jession_coltypes['data']['DATA_TYPE']);
		$_dataCol_wrongSize = ($_dataColType !== 'mediumtext') && ($_dataColType !== 'longtext');

		/**
		 * If data type is "text" it is safe to assume that it can be converted to "mediumtext",
		 * since "text" means that session table is not memory storage,
		 * plus it is already stored externally aka operation will be quick ?
		 */
		/*if ($_dataCol_wrongSize && $_dataColType === 'text')
		{
			$db->setQuery("ALTER TABLE `#__session` MODIFY `data` MEDIUMTEXT");
			$db->execute();
			$_dataCol_wrongSize = false;
		}*/

		if ($_dataCol_wrongSize)
		{
			$app->enqueueMessage('Joomla DB table: <b>\'session\'</b> has a <b>\'data\'</b> column with type: ' .
					'<span class="badge">' . $_dataColType .	'</span>' .
					'<br>Expected column type: <span class="badge bg-info badge-info">mediumtext</span>.' .
					'<br>Trying to import large data files may fail',
				'notice'
			);
		}


		$formvals = array();

		// Retrieve Basic configuration
		$formvals['id_col']   = $model->getState('id_col');
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
		$formvals['custom_ititle_col'] = $model->getState('custom_ititle_col');


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



		/**
		 * Create form fields
		 */

		// Build content type field
		$lists['type_id'] = flexicontent_html::buildtypesselect($types, 'type_id', $formvals['type_id'], true, 'class="required use_select2_lib"', 'type_id');

		// Creating categorories tree for item assignment, we use the 'create' privelege
		$actions_allowed = array('core.create');


		// Build main category field
		$attribs = 'class="use_select2_lib required"';
		$fieldname = 'maincat';
		$lists['maincat'] = flexicontent_cats::buildcatselect($categories, $fieldname, $formvals['maincat'], 2, $attribs, false, true, $actions_allowed);


		// Build secondary categories field
		$class  = "use_select2_lib";
		$attribs = 'multiple="multiple" size="10" class="'.$class.'"';
		$fieldname = 'seccats[]';
		$lists['seccats'] = flexicontent_cats::buildcatselect($categories, $fieldname, $formvals['seccats'], false, $attribs, false, true,
			$actions_allowed, $require_all=true);


		// Retrieve author configuration
		$authorparams = flexicontent_db::getUserConfig($user->id);
		
		// Get current user's allowed languages
		$allowed_langs = $authorparams->get('langs_allowed',null);
		$allowed_langs = !$allowed_langs ? null : FLEXIUtilities::paramToArray($allowed_langs);


		// Build language field
		$lists['languages'] = flexicontent_html::buildlanguageslist('language'
			, ' style="vertical-align:top;" onchange="var m=jQuery(\'#fc_import_about_langcol\'); this.value ? m.hide(600) : m.show(600);"'
			, $formvals['language'], 6/*'- '.JText::_('FLEXI_USE_LANGUAGE_COLUMN').' -'*/, $allowed_langs, $published_only=true
			, $disable_langs=null, $add_all=true, $_conf=array('required'=>true)
		).'
			<span class="fc-mssg-inline fc-note fc-nobgimage" id="fc_import_about_langcol" style="display:none;">
				'.JText::_('FLEXI_USE_LANGUAGE_COLUMN_TIP').'
			</span>';

		$lists['states'] = flexicontent_html::buildstateslist('state', ' style="vertical-align:top;" onchange="var m=jQuery(\'#fc_import_about_statecol\'); this.value ? m.hide(600) : m.show(600);"'
			, $formvals['state'], 2/*'- '.JText::_('FLEXI_USE_STATE_COLUMN').' -'*/).
			'<span class="fc-mssg-inline fc-note fc-nobgimage" id="fc_import_about_statecol" style="display:none;">
				'.JText::_('FLEXI_USE_STATE_COLUMN_TIP').'
			</span>';


		// Build access level field
		$access_levels = JHtml::_('access.assetgroups');
		array_unshift($access_levels, JHtml::_('select.option', '0', "Use 'access' column") );
		array_unshift($access_levels, JHtml::_('select.option', '', 'FLEXI_SELECT_ACCESS_LEVEL') );
		$fieldname = 'access';  // make multivalue
		$elementid = 'access';
		$attribs = 'class="required use_select2_lib"';
		$lists['access'] = JHtml::_('select.genericlist', $access_levels, $fieldname, $attribs, 'value', 'text', $formvals['access'], $elementid, $translate=true );


		/**
		 * Get field names (from the header line (row 0), and remove it form the data array
		 */

		$file_field_types_list = '"image","file","mediafile"';
		$q = 'SELECT id, name, label, field_type FROM #__flexicontent_fields AS fi'
			//.' JOIN #__flexicontent_fields_type_relations AS ftrel ON ftrel.field_id = fi.id AND ftrel.type_id='.$type_id;
			.' WHERE fi.field_type IN ('. $file_field_types_list .')'
		;
		$file_fields = $db->setQuery($q)->loadObjectList('name');


		/**
		 * Assign data to template
		 */

		$this->model = $model;
		$this->lists = $lists;
		$this->user = $user;
		$this->cparams = $cparams;
		$this->file_fields = $file_fields;
		$this->formvals = $formvals;

		parent::display($tpl);
	}



	/**
	 * Method to configure the toolbar for this view.
	 *
	 * @access	public
	 * @return	void
	 */
	function setToolbar()
	{
		$user     = JFactory::getUser();
		$document = JFactory::getDocument();
		$toolbar  = JToolbar::getInstance('toolbar');
		$perms    = FlexicontentHelperPerm::getPerm();
		$session  = JFactory::getSession();
		$useAssocs= flexicontent_db::useAssociations();
		$task     = JFactory::getApplication()->input->getCmd('task');

		$js = '';

		$contrl = $this->ctrl . '.';
		$contrl_s = null;

		$loading_msg = flexicontent_html::encodeHTML(JText::_('FLEXI_LOADING') .' ... '. JText::_('FLEXI_PLEASE_WAIT'), 2);

		if (!empty($this->conf))
		{
			if ($task !== 'processcsv')
			{
				$btn_task  = $contrl . 'processcsv';
				$btn_title = empty($this->lineno)
					? 'FLEXI_IMPORT_START_TASK'
					: 'FLEXI_IMPORT_CONTINUE_TASK';

				flexicontent_html::addToolBarButton(
					$btn_title, $btn_name = 'processcsv', $full_js = '',
					$msg_alert = '', $msg_confirm = '',
					$btn_task, $extra_js = '', $btn_list=false, $btn_menu=true, $btn_confirm=false,
					$this->btn_sm_class . ' btn-fcaction btn-success ' . (FLEXI_J40GE ? $this->btn_iv_class : ''), $btn_icon='icon-box-add',
					$tag_params = '', $auto_add = 1, $tag_type='button'
				);
			}

			$ctrl_task = 'import.clearcsv';
			JToolbarHelper::custom( $ctrl_task, 'cancel.png', 'cancel.png', 'FLEXI_IMPORT_CLEAR_TASK', $list_check = false );
		}

		else
		{
			$ctrl_task = 'import.initcsv';
			JToolbarHelper::custom( $ctrl_task, 'import.png', 'import.png', 'FLEXI_IMPORT_PREPARE_TASK', $list_check = false );
			$ctrl_task = 'import.testcsv';
			JToolbarHelper::custom( $ctrl_task, 'test.png', 'test.png', 'FLEXI_IMPORT_TEST_FILE_FORMAT', $list_check = false );
		}

		//JToolbarHelper::Back();

		if ($perms->CanConfig)
		{
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolbarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}


		if ($js)
		{
			$document->addScriptDeclaration('
				jQuery(document).ready(function(){
					' . $js . '
				});
			');
		}
	}
}

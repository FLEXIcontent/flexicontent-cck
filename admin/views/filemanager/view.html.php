<?php
/**
 * @version 1.5 stable $Id: view.html.php 1869 2014-03-12 12:18:40Z ggppdk $
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
use Joomla\String\StringHelper;

/**
 * HTML View class for the Filemanager View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewFilemanager extends JViewLegacy
{
	/**
	 * Creates the Filemanager view
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		// ********************
		// Initialise variables
		// ********************
		
		$app     = JFactory::getApplication();
		$jinput  = $app->input;
		
		$layout  = $jinput->get('layout', 'default', 'cmd');
		$option  = $jinput->get('option', '', 'cmd');
		$view    = $jinput->get('view', '', 'cmd');
		
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$user     = JFactory::getUser();
		$db       = JFactory::getDbo();
		$document = JFactory::getDocument();
		$session  = JFactory::getSession();
		
		// Get model
		$model = $this->getModel();
		
		//$authorparams = flexicontent_db::getUserConfig($user->id);
		$langs = FLEXIUtilities::getLanguages('code');
		
		//$jq_params = new JRegistry();
		//$jq_params->set('jquery_ver', '1');
		//$jq_params->set('jquery_ui_ver', '1.10.2');
		//$jq_params->set('jquery_ui_theme', 'ui-lightness');
		//flexicontent_html::loadJQuery( $add_jquery = 1, $add_jquery_ui = 1, $add_jquery_ui_css = 1, $add_remote = 2, $jq_params );
		
		flexicontent_html::loadJQuery();
		flexicontent_html::loadFramework('select2');
		//JHtml::_('behavior.tooltip');
		// Load the form validation behavior
		JHtml::_('behavior.formvalidation');
		
		
		// Get user's global permissions
		$perms = FlexicontentHelperPerm::getPerm();
		
		// Get field id and folder mode
		$fieldid = $view=='fileselement' ? $jinput->get('field', 0, 'int') : null;   // Force no field id for filemanager
		$folder_mode = 0;
		$assign_mode = 0;
		if ($fieldid)
		{
			$_fields = FlexicontentFields::getFieldsByIds(array($fieldid), false);
			if ( !empty($_fields[$fieldid]) )
			{
				$field = $_fields[$fieldid];
				$field->parameters = new JRegistry($field->attribs);

				if ( in_array($field->field_type, array('image')) )
				{
				 $folder_mode = $field->parameters->get('image_source')==0 ? 0 : 1;
				 $assign_mode = 1;
				}
				$layout = in_array($field->field_type, array('image', 'minigallery')) ? 'image' : $layout;
			}
			else
			{
				$fieldid = null;
			}
		}

		if (!$fieldid && $view=='fileselement') die('no valid field ID');
		$_view = $view.$fieldid;
		//$folder_mode = !$fieldid ? 0 : $app->getUserStateFromRequest( $option.'.'.$_view.'.folder_mode', 'folder_mode', 0, 'int' );



		// ***********
		// Get filters
		// ***********
		
		$count_filters = 0;
		
		// Order and order direction
		$filter_order      = $model->getState('filter_order');
		$filter_order_Dir  = $model->getState('filter_order_Dir');
		
		$filter_lang			= $app->getUserStateFromRequest( $option.'.'.$_view.'.filter_lang',      'filter_lang',      '',          'string' );
		$filter_url       = $app->getUserStateFromRequest( $option.'.'.$_view.'.filter_url',       'filter_url',       '',          'word' );
		$filter_secure    = $app->getUserStateFromRequest( $option.'.'.$_view.'.filter_secure',    'filter_secure',    '',          'word' );
		$filter_stamp     = $app->getUserStateFromRequest( $option.'.'.$_view.'.filter_stamp',     'filter_stamp',     '',          'word' );
		
		$target_dir = $layout=='image' ? 0 : 2;  // 0: Force media, 1: force secure, 2: allow selection
		$optional_cols = array('state', 'access', 'lang', 'hits', 'target', 'stamp', 'usage', 'uploader', 'upload_time', 'file_id');
		$cols = array();


		// Column disabling only applicable for FILESELEMENT view, with field in DB mode (folder_mode==0)
		if (!$folder_mode && $fieldid)
		{
			// Clear secure/media filter if field is not configured to use specific
			$target_dir = $field->parameters->get('target_dir', '');
			$filter_secure = !strlen($target_dir) || $target_dir!=2  ?  ''  :  $filter_secure;
			
			$filelist_cols = FLEXIUtilities::paramToArray( $field->parameters->get('filelist_cols', array('upload_time', 'hits')) );
			
		}

		// Column selection of optional columns given
		if ( !empty($filelist_cols) )
		{
			foreach($filelist_cols as $col) $cols[$col] = 1;
			unset($cols['_SAVED_']);
		}

		// Column selection of optional columns not given
		else
		{
			// Filemanager view, add all columns
			if ($view=='filemanager')
			{
				foreach($optional_cols as $col) $cols[$col] = 1;
			}
			
			// Fileselement view, show none of optional columns
			else ;
		}

		$filter_ext       = $app->getUserStateFromRequest( $option.'.'.$_view.'.filter_ext',       'filter_ext',       '',          'alnum' );
		$filter_uploader  = $app->getUserStateFromRequest( $option.'.'.$_view.'.filter_uploader',  'filter_uploader',  '',           'int' );
		$filter_item      = $app->getUserStateFromRequest( $option.'.'.$_view.'.item_id',          'item_id',          '',           'int' );
		
		if ($layout!='image')
		{
			if ($filter_lang) $count_filters++;
			if ($filter_url) $count_filters++;
			if ($filter_stamp) $count_filters++;
			if ($filter_secure) $count_filters++;
		}
		
		// ?? Force unsetting language and target_dir columns if LAYOUT is image file list
		else
		{
			unset($cols['lang']);
			unset($cols['target']);
		}
		
		// Case of uploader column not applicable or not allowed
		if (!$folder_mode && !$perms->CanViewAllFiles) unset($cols['uploader']);
		
		if ($filter_ext) $count_filters++;
		if ($filter_uploader && !empty($cols['uploader'])) $count_filters++;
		if ($filter_item) $count_filters++;
		
		$u_item_id = $view=='fileselement' ? $app->getUserStateFromRequest( $option.'.'.$_view.'.u_item_id', 'u_item_id', 0, 'string' ) : null;


		// Text search
		$scope  = $model->getState( 'scope' );
		$search = $model->getState( 'search' );
		$search = $db->escape( StringHelper::trim(StringHelper::strtolower( $search ) ) );
		
		$filter_uploader  = $filter_uploader ? $filter_uploader : '';
		$filter_item      = $filter_item ? $filter_item : '';


		// *** TODO: (enhancement) get recently deleted file(s), and remove their assignments from current form
		$delfilename = $app->getUserState('delfilename', null);
		$app->setUserState('delfilename', null);

		$upload_context = 'fc_upload_history.item_' . $u_item_id . '_field_' . $fieldid;
		$session_files = $session->get($upload_context, array());
		
		$pending_file_ids = !empty($session_files['ids_pending']) ? $session_files['ids_pending'] : array();
		$pending_file_names = !empty($session_files['names_pending']) ? $session_files['names_pending'] : array();

		$pending_file_names = array_flip($pending_file_names);


		// ***
		// *** Add css and js to document
		// ***
		
		if ($app->isSite())
		{
			$document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', FLEXI_VHASH);
		}
		else
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', FLEXI_VHASH);

		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x_rtl.css', FLEXI_VHASH);

		// Fields common CSS
		$document->addStyleSheetVersion(JUri::root(true).'/components/com_flexicontent/assets/css/flexi_form_fields.css', FLEXI_VHASH);

		
		// Add JS frameworks
		flexicontent_html::loadFramework('select2');
		$prettycheckable_added = flexicontent_html::loadFramework('prettyCheckable');
		flexicontent_html::loadFramework('flexi-lib');
		
		// Add js function to overload the joomla submitform validation
		JHtml::_('behavior.formvalidation');  // load default validation JS to make sure it is overriden
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);
		
		
		// ************************
		// Create Submenu & Toolbar
		// ************************
		
		// Create Submenu (and also check access to current view)
		if ($view!='fileselement')
		{
			FLEXIUtilities::ManagerSideMenu('CanFiles');
		}
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_FILEMANAGER' );
		$site_title = $document->getTitle();
		if ($view!='fileselement')
		{
			JToolbarHelper::title( $doc_title, 'files' );
		}
		$document->setTitle($doc_title .' - '. $site_title);
		
		// Create the toolbar
		$this->setToolbar();
		

		
		// ***********************
		// Get data from the model
		// ***********************
		
		// DB mode
		if ( !$folder_mode )
		{
			$rows_pending = $view=='fileselement'
				? $model->getDataPending()
				: false;
			if (empty($rows_pending))
			{
				$rows = $model->getData();
			}
			$img_folder = '';
		}

		// FOLDER mode
		else
		{
			$rows_pending = $view=='fileselement'
				? $model->getFilesFromPath($u_item_id, $fieldid, null, true)
				: false;
			if (empty($rows_pending))
			{
				$rows = $model->getFilesFromPath($u_item_id, $fieldid, null, false);
			}
			$img_folder = $model->getFieldFolderPath($u_item_id, $fieldid);
		}

		
		// Clear pending
		unset($session_files['ids_pending']);
		unset($session_files['names_pending']);
		$session->set($upload_context, $session_files);

		$pagination = $this->get('Pagination');
		//$users = $this->get('Users');
		
		// Get item using at least one file (-of- the currently listed files)
		/*$items_single	= $model->getItemsSingleprop( array('file','minigallery') );
		$items_multi	= $model->getItemsMultiprop ( $field_props=array('image'=>'originalname'), $value_props=array('image'=>'filename') );
		$items = array();
		foreach ($items_single as $item_id => $_item) $items[$item_id] = $_item;
		foreach ($items_multi  as $item_id => $_item) $items[$item_id] = $_item;
		ksort($items);*/
		
		$assigned_fields_labels = array('image'=>'image/gallery', 'file'=>'file', 'minigallery'=>'minigallery');
		$assigned_fields_icons = array('image'=>'picture_link', 'file'=>'page_link', 'minigallery'=>'film_link');


		// *** BOF FOLDER MODE specific ***

		
		
		/*****************
		 ** BUILD LISTS **
		 *****************/
		
		$lists = array();
		
		// ** FILE UPLOAD FORM **
		
		// Build languages list
		//$allowed_langs = !$authorparams ? null : $authorparams->get('langs_allowed',null);
		//$allowed_langs = !$allowed_langs ? null : FLEXIUtilities::paramToArray($allowed_langs);
		$display_file_lang_as = $cparams->get('display_file_lang_as', 3);
		$allowed_langs = null;
		$lists['file-lang'] = flexicontent_html::buildlanguageslist('file-lang', 'class="use_select2_lib"', '*', $display_file_lang_as, $allowed_langs, $published_only=false);
		
		// Build access list
		//$lists['file-access'] = JHtml::_('access.assetgrouplist', 'access', null, $attribs=' class="use_select2_lib" ', $config=array(/*'title' => JText::_('FLEXI_SELECT'), */'id' => 'access'));

		$options = JHtml::_('access.assetgroups');
		$elementid = $fieldname = 'file-access';
		$attribs = 'class="use_select2_lib"';
		$lists['file-access'] = JHtml::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', null, $elementid, $translate=true );


		/*************
		 ** FILTERS **
		 *************/
		
		// language filter
		$lists['language'] = ($filter_lang || 1 ? '<div class="add-on">'.JText::_('FLEXI_LANGUAGE').'</div>' : '').
			flexicontent_html::buildlanguageslist('filter_lang', 'class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()" size="1" ', $filter_lang, '-'/*2*/);
		
		// search
		$lists['search'] 	= $search;
		
		//search filter
		$filters = array();
		$filters[] = JHtml::_('select.option', '0', '- '.JText::_( 'FLEXI_ALL' ).' -' );
		$filters[] = JHtml::_('select.option', '1', JText::_( 'FLEXI_FILENAME' ) );
		$filters[] = JHtml::_('select.option', '2', JText::_( 'FLEXI_FILE_DISPLAY_TITLE' ) );
		$filters[] = JHtml::_('select.option', '3', JText::_( 'FLEXI_DESCRIPTION' ) );
		$lists['scope'] = '
			<span class="hasTooltip" style="display:inline-block; padding:0; margin:0;" title="'.JText::_('FLEXI_SEARCH_TEXT_INSIDE').'"><i class="icon-info"></i></span>
			'.JHtml::_('select.genericlist', $filters, 'scope', 'size="1" class="use_select2_lib fc_skip_highlight" onchange="jQuery(\'#search\').attr(\'placeholder\', jQuery(this).find(\'option:selected\').text());" ', 'value', 'text', $scope );
		
		if (1)
		{
			//build url/file filterlist
			$url 	= array();
			$url[] 	= JHtml::_('select.option',  '', '-'/*JText::_( 'FLEXI_ALL_FILES' )*/ );
			$url[] 	= JHtml::_('select.option',  'F', JText::_( 'FLEXI_FILE' ) );
			$url[] 	= JHtml::_('select.option',  'U', JText::_( 'FLEXI_URL' ) );

			$lists['url'] = ($filter_url || 1 ? '<div class="add-on">'.JText::_('FLEXI_ALL_FILES').'</div>' : '').
				JHtml::_('select.genericlist', $url, 'filter_url', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_url );

			//build stamp filterlist
			$stamp 	= array();
			$stamp[] 	= JHtml::_('select.option',  '', '-'/*JText::_( 'FLEXI_ALL_FILES' )*/ );
			$stamp[] 	= JHtml::_('select.option',  '0', JText::_( 'FLEXI_NO' ) );
			$stamp[] 	= JHtml::_('select.option',  '1', JText::_( 'FLEXI_YES' ) );

			$lists['stamp'] = ($filter_stamp || 1 ? '<div class="add-on">'.JText::_('FLEXI_DOWNLOAD_STAMPING').'</div>' : '').
				JHtml::_('select.genericlist', $stamp, 'filter_stamp', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_stamp );
		}

		//item lists
		/*$items_list = array();
		$items_list[] = JHtml::_('select.option', '', '- '. JText::_( 'FLEXI_FILTER_BY_ITEM' ) .' -' );
		foreach($items as $item) {
			$items_list[] = JHtml::_('select.option', $item->id, JText::_( $item->title ) . ' (#' . $item->id . ')' );
		}
		$lists['item_id'] = JHtml::_('select.genericlist', $items_list, 'item_id', 'size="1" class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_item );*/
		$lists['item_id'] = '<input type="text" name="item_id" size="1" class="inputbox" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()" value="'.$filter_item.'" />';
		
		//build secure/media filterlist
		$_secure_info = '<i data-placement="bottom" class="icon-info hasTooltip" title="'.flexicontent_html::getToolTip('FLEXI_URL_SECURE', 'FLEXI_URL_SECURE_DESC', 1, 1).'"></i>';
		$lists['secure'] = ($filter_secure || 1 ? '<div class="add-on">' . $_secure_info . ' ' . JText::_('FLEXI_URL_SECURE') . '</div>' : '');
		if ($target_dir==2)
		{
			$secure 	= array();
			$secure[] 	= JHtml::_('select.option',  '', '-'/*JText::_( 'FLEXI_ALL_DIRECTORIES' )*/ );
			$secure[] 	= JHtml::_('select.option',  'S', JText::_( 'FLEXI_SECURE_DIR' ) );
			$secure[] 	= JHtml::_('select.option',  'M', JText::_( 'FLEXI_MEDIA_DIR' ) );
			
			$lists['secure'] .=
				JHtml::_('select.genericlist', $secure, 'filter_secure', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_secure );
		}
		else
			$lists['secure'] .= '<span class="badge badge-info">'.JText::_($target_dir==0 ? 'FLEXI_MEDIA_DIR' : 'FLEXI_SECURE_DIR').'</span>';

		//build ext filterlist
		$lists['ext'] = ($filter_ext || 1 ? '<div class="add-on">'.JText::_('FLEXI_ALL_EXT').'</div>' : '').
			flexicontent_html::buildfilesextlist('filter_ext', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', $filter_ext, '-'/*1*/);

		//build uploader filterlist
		if ($perms->CanViewAllFiles && !empty($cols['uploader']))
		{
			$lists['uploader'] = ($filter_uploader || 1 ? '<div class="add-on">'.JText::_('FLEXI_ALL_UPLOADERS').'</div>' : '').
				flexicontent_html::builduploaderlist('filter_uploader', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', $filter_uploader, '-'/*1*/);
		}

		// table ordering
		$lists['order_Dir']	= $filter_order_Dir;
		$lists['order']			= $filter_order;

		// uploadstuff
		jimport('joomla.client.helper');
		$require_ftp = !JClientHelper::hasCredentials('ftp');
		
		//assign data to template
		$this->target_dir = $target_dir;
		$this->optional_cols = $optional_cols;
		$this->cols = $cols;
		$this->count_filters = $count_filters;

		$this->params = $cparams;
		$this->lists  = $lists;
		$this->rows   = $rows_pending ?: $rows ;
		$this->is_pending = $rows_pending ? true : false;
		$this->langs  = $langs;

		$this->folder_mode = $folder_mode;
		$this->assign_mode = $assign_mode;
		$this->pagination = $pagination;

		$this->CanFiles   = $perms->CanFiles;
		$this->CanUpload  = $perms->CanUpload;
		$this->CanViewAllFiles = $perms->CanViewAllFiles;

		$this->assigned_fields_labels = $assigned_fields_labels;
		$this->assigned_fields_icons  = $assigned_fields_icons;

		$this->require_ftp = $require_ftp;
		$this->layout  = $layout;
		$this->field   = !empty($field) ? $field : null;
		$this->fieldid = $fieldid;
		$this->u_item_id  = $u_item_id;
		$this->pending_file_names = $pending_file_names;

		$this->option = $option;
		$this->view   = $view;

		if ($view=='fileselement')
		{
			$this->img_folder = $img_folder;
			$this->thumb_w    = $thumb_w;
			$this->thumb_h    = $thumb_h;
			$this->targetid   = $targetid;
		}
		else
		{
			$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;
		}
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
		$document = JFactory::getDocument();
		$toolbar = JToolbar::getInstance('toolbar');
		$loading_msg = flexicontent_html::encodeHTML(JText::_('FLEXI_LOADING') .' ... '. JText::_('FLEXI_PLEASE_WAIT'), 2);

		$user  = JFactory::getUser();
		$perms = FlexicontentHelperPerm::getPerm();
		$session = JFactory::getSession();

		$contrl = "filemanager.";
		JToolbarHelper::editList($contrl.'edit');
		JToolbarHelper::checkin($contrl.'checkin');
		JToolbarHelper::deleteList(JText::_('FLEXI_ARE_YOU_SURE'), 'filemanager.remove');

		$js = "jQuery(document).ready(function(){";
		if ($perms->CanConfig)
		{
			$btn_task = '';
			$popup_load_url = JUri::base().'index.php?option=com_flexicontent&view=filemanager&layout=indexer&tmpl=component&indexer=fileman_default';
			//$toolbar->appendButton('Popup', 'basicindex', 'Index file statistics', str_replace('&', '&amp;', $popup_load_url), 500, 240);
			$js .= "
				jQuery('#toolbar-basicindex a.toolbar, #toolbar-basicindex button').attr('href', '".$popup_load_url."')
					.attr('onclick', 'var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 550, 350, function(){document.body.innerHTML=\'<span class=\"fc_loading_msg\">"
						.$loading_msg."</span>\'; window.location.reload(false)}, {\'title\': \'".flexicontent_html::encodeHTML(JText::_('Index file statistics'), 2)."\'}); return false;');
			";
			JToolbarHelper::custom( $btn_task, 'basicindex.png', 'basicindex_f2.png', JText::_('FLEXI_INDEX_FILE_STATISTICS') . ' (' . JText::_('FLEXI_SIZE') . ', ' . JText::_('FLEXI_USAGE') . ' )', false );

			$btn_task = '';
			$popup_load_url = JUri::base().'index.php?option=com_flexicontent&view=filemanager&layout=indexer&tmpl=component&indexer=fileman_default&index_urls=1';
			//$toolbar->appendButton('Popup', 'advindex', 'Index file statistics', str_replace('&', '&amp;', $popup_load_url), 500, 240);
			$js .= "
				jQuery('#toolbar-advindex a.toolbar, #toolbar-advindex button').attr('href', '".$popup_load_url."')
					.attr('onclick', 'var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 550, 350, function(){document.body.innerHTML=\'<span class=\"fc_loading_msg\">"
						.$loading_msg."</span>\'; window.location.reload(false)}, {\'title\': \'".flexicontent_html::encodeHTML(JText::_('Index file statistics'), 2)."\'}); return false;');
			";
			JToolbarHelper::custom( $btn_task, 'advindex.png', 'advindex_f2.png', JText::_('FLEXI_INDEX_FILE_STATISTICS') . ' (' . JText::_('FLEXI_SIZE') . ', ' . JText::_('FLEXI_USAGE') . ', ' . JText::_('FLEXI_URL') . ' )', false );
		}

		/*$stats_indexer_errors = $session->get('filemanager.stats_indexer_errors', null, 'flexicontent');
		if ($stats_indexer_errors !== null)
		{
			foreach($stats_indexer_errors as $error_message)
			{
				JFactory::getApplication()->enqueueMessage($error_message, 'warning');
			}
			$session->set('filemanager.stats_indexer_errors', null, 'flexicontent');
		}*/

		$stats_indexer_error_count = $session->get('filemanager.stats_indexer_error_count', 0, 'flexicontent');
		if ($stats_indexer_error_count)
		{
			JFactory::getApplication()->enqueueMessage('Could not calculate file stats for: ' . $stats_indexer_error_count . ' cases (e.g. bad URLs)', 'warning');
			$session->set('filemanager.stats_indexer_error_count', null, 'flexicontent');

			if ($log_filename = $session->get('filemanager.log_filename', null, 'flexicontent'))
			{
				JFactory::getApplication()->enqueueMessage('You may see log file : <b>' . JPATH::clean($log_filename) . '</b> for messages and errors', 'warning');
			}
		}
		$session->set('filemanager.log_filename', null, 'flexicontent');

		if ($perms->CanConfig)
		{
			JToolbarHelper::divider(); JToolbarHelper::spacer();
			$session = JFactory::getSession();
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolbarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}
		
		$js .= "});";
		$document->addScriptDeclaration($js);
	}


	function indexer($tpl)
	{		
		parent::display($tpl);
	}
}
<?php
/**
 * @version 1.5 stable $Id: view.html.php 1925 2014-06-25 01:50:14Z ggppdk $
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
 * HTML View class for the Fileselement View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewFileselement extends JViewLegacy
{
	/**
	 * Creates the Fileselement view
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		// Check for request forgeries
		JRequest::checkToken('request') or jexit( 'Invalid Token' );
		
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
		$db       = JFactory::getDBO();
		$document = JFactory::getDocument();
		
		// Get model
		$model = $this->getModel();
		
		//$authorparams = flexicontent_db::getUserConfig($user->id);
		$langs = FLEXIUtilities::getLanguages('code');
		
		$fieldid	= JRequest::getVar( 'field', null, 'request', 'int' );
		$client		= $app->isAdmin() ? '../' : '';
		
		flexicontent_html::loadJQuery();
		flexicontent_html::loadFramework('select2');
		JHTML::_('behavior.tooltip');
		// Load the form validation behavior
		JHTML::_('behavior.formvalidation');
		
		
		// Get user's global permissions
		$perms = FlexicontentHelperPerm::getPerm();
		
		// Get folder mode
		$_view = $view.$fieldid;
		$folder_mode = $app->getUserStateFromRequest( $option.'.'.$_view.'.folder_mode',      'folder_mode',      0,           'int' );
		
		
		
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
		
		$target_dir = 2;
		if (!$folder_mode)
		{
			$field_params = $model->getFieldParams();
			$target_dir = $field_params->get('target_dir', '');
			// Clear secure/media filter if field is not configured to use specific
			if ( !strlen($target_dir) || $target_dir!=2 ) $filter_secure = '';
		}
		
		$filter_ext       = $app->getUserStateFromRequest( $option.'.'.$_view.'.filter_ext',       'filter_ext',       '',          'alnum' );
		$filter_uploader  = $app->getUserStateFromRequest( $option.'.'.$_view.'.filter_uploader',  'filter_uploader',  '',           'int' );
		$filter_item      = $app->getUserStateFromRequest( $option.'.'.$_view.'.item_id',          'item_id',          '',           'int' );
		
		if ($layout!='image') {
			if ($filter_lang) $count_filters++;
			if ($filter_url) $count_filters++;
			if ($filter_secure) $count_filters++;
		}
		if ($filter_ext) $count_filters++;
		//if ($perms->CanViewAllFiles && $filter_uploader) $count_filters++;
		if ($filter_item) $count_filters++;
		
		$u_item_id 	      = $app->getUserStateFromRequest( $option.'.'.$_view.'.u_item_id',        'u_item_id',        0,           'string' );
		//if ($u_item_id && (int)$u_item_id = $u_item_id) $filter_item = $u_item_id;   // DO NOT SET it prevents listing and selecting files !!
		if (!$u_item_id && $filter_item)   $u_item_id   = $filter_item;
		$autoselect       = $app->getUserStateFromRequest( $option.'.'.$_view.'.autoselect',       'autoselect',       0, 				  'int' );
		$autoassign       = $app->getUserStateFromRequest( $option.'.'.$_view.'.autoassign',       'autoassign',       0, 				  'int' );
		
		$folder_param			= $app->getUserStateFromRequest( $option.'.'.$_view.'.folder_param',     'folder_param',		 'dir',				'string' );
		$append_item			= $app->getUserStateFromRequest( $option.'.'.$_view.'.append_item',      'append_item',      1, 				  'int' );
		$append_field			= $app->getUserStateFromRequest( $option.'.'.$_view.'.append_field',     'append_field',     1, 				  'int' );
		$targetid					= $app->getUserStateFromRequest( $option.'.'.$_view.'.targetid',    		 'targetid',     		 '', 				  'string' );
		$thumb_w					= $app->getUserStateFromRequest( $option.'.'.$_view.'.thumb_w',    			 'thumb_w',     		 120, 				'int' );
		$thumb_h					= $app->getUserStateFromRequest( $option.'.'.$_view.'.thumb_h',    			 'thumb_h',     		 90, 				  'int' );
		
		// Text search
		$scope  = $model->getState( 'scope' );
		$search = $model->getState( 'search' );
		$search = $db->escape( trim(JString::strtolower( $search ) ) );
		
		$filter_uploader  = $filter_uploader ? $filter_uploader : '';
		$filter_item      = $filter_item ? $filter_item : '';
		
		$newfileid		= JRequest::getInt('newfileid');
		$newfilename	= base64_decode(JRequest::getVar('newfilename', ''));
		$delfilename	= base64_decode(JRequest::getVar('delfilename', ''));
		
		// **************************
		// Add css and js to document
		// **************************
		
		if ($app->isSite()) {
			$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', FLEXI_VHASH);
		} else {
			$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH);
		}
		
		$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH);
		
		// This is not included automatically in frontend
		$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/flexi-lib.js', FLEXI_VHASH);
		
		// Include backend CSS template CSS file , access to backend folder may not be allowed but ...
		//$template = $app->isSite() ? (!FLEXI_J16GE ? 'khepri' : (FLEXI_J30GE ? 'hathor' : 'bluestork')) : $app->getTemplate();
		//$document->addStyleSheet(JURI::base(true).'/templates/'.$template.(FLEXI_J16GE ? '/css/template.css': '/css/general.css'));
		
		//a trick to avoid loosing general style in modal window
		$css = 'body, td, th { font-size: 11px; }
		a.striketext {
			text-decoration: line-through;
			color:red;
			font-style:italic;
		}
		';
		$document->addStyleDeclaration($css);
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_FILE' );
		$site_title = $document->getTitle();
		$document->setTitle($doc_title .' - '. $site_title);
		
		
		// ***********************
		// Get data from the model
		// ***********************
		
		if ( !$folder_mode ) {
			$rows  = $this->get('Data');
			$img_folder = '';
		} else {
			$exts = $cparams->get('upload_extensions', 'bmp,csv,doc,docx,gif,ico,jpg,jpeg,odg,odp,ods,odt,pdf,png,ppt,pptx,swf,txt,xcf,xls,xlsx,zip,ics');
			$rows = $model->getFilesFromPath($u_item_id, $fieldid, $append_item, $append_field, $folder_param, $exts);
			
			$img_folder = $model->getFieldFolderPath($u_item_id, $fieldid, $append_item, $append_field, $folder_param);
			$img_path = str_replace('\\', '/', $img_folder . DS . $newfilename);
			
			$ext = strtolower(pathinfo($newfilename, PATHINFO_EXTENSION));
			$_f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
			$thumb = JURI::root() . 'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' .$img_path.$_f. '&amp;w='.$thumb_w.'&amp;h='.$thumb_h.'&amp;zc=1';
		}
		$upload_path_var = 'fc_upload_path_'.$fieldid.'_'.$u_item_id;
		$app->setUserState( $upload_path_var, $img_folder );
		//echo $upload_path_var . "<br>";
		//echo $app->getUserState( $upload_path_var, 'noset' );
		
		$pagination = $this->get('Pagination');
		//$users = $this->get('Users');
		
		// Get item using at least one file (-of- the currently listed files)
		/*$items_single	= $model->getItemsSingleprop( array('file','minigallery') );
		$items_multi	= $model->getItemsMultiprop ( $field_props=array('image'=>'originalname'), $value_props=array('image'=>'filename') );
		$items = array();
		foreach ($items_single as $item_id => $_item) $items[$item_id] = $_item;
		foreach ($items_multi  as $item_id => $_item) $items[$item_id] = $_item;
		ksort($items);*/
		
		$fname = $model->getFieldName($fieldid);
		$files_selected = $model->getItemFiles($u_item_id);
		$formfieldname = FLEXI_J16GE ? 'custom['.$fname.'][]' : $fname.'[]';
		
		// Add JS to document to initialize the file list
		// eg Find and mark file usage by fileid / filename search (respectively: DB mode / Folder mode)
		if ($folder_mode) {
			$js = "
			jQuery(document).ready(function()
			{
				var delfilename = '".$delfilename."';
				var remove_existing_files_from_list = 0;
				var remove_new_files_from_list = 0;
				
				// Find and mark file usage by filename search
				var original_objs = jQuery(window.parent.document.body).find('.fcfieldval_container_".$fieldid." .originalname');  // newly selected field values, not yet saved in DB
				var existing_objs = jQuery(window.parent.document.body).find('.fcfieldval_container_".$fieldid." .existingname');  // existing field values, already saved in DB
				
				var imgobjs = Array();
				for(i=0,n=original_objs.length; i<n; i++)  {
					if (original_objs[i].value) imgobjs.push(original_objs[i].value);
					if (delfilename!='' && original_objs[i].value == delfilename)
					{
						window.parent.qmAssignFile".$fieldid."('".$targetid."', '', '', '1');
						remove_existing_files_from_list = 1;
					}
				}
				for(i=0,n=existing_objs.length; i<n; i++) {
					if (existing_objs[i].value) imgobjs.push(existing_objs[i].value);
					if (delfilename!='' && existing_objs[i].value == delfilename)
					{
						window.parent.qmAssignFile".$fieldid."('".$targetid."', '', '', '1');
						remove_new_files_from_list = 1;
					}
				}
				
				if ( remove_existing_files_from_list || remove_new_files_from_list ) {
					mssg = '".JText::_('FLEXI_DELETE_FILE_IN_LIST_WINDOW_MUST_CLOSE')."';
					mssg = mssg + '\\n' + (remove_existing_files_from_list ? '".JText::_('FLEXI_EXISTING_FILE_REMOVED_SAVE_RECOMMENEDED',true)."' : '');
					alert( mssg );
					window.parent.qmAssignFile".$fieldid."('".$targetid."', '', '', '2');
				}
				
				for(i=0,n=imgobjs.length; i<n; i++) {
					var rows = jQuery.find('a[data-filename=\"'+ imgobjs[i] +'\"]');
					jQuery(rows).addClass('striketext');
				}
				"
				.($autoassign && $newfilename ? "window.parent.qmAssignFile".$fieldid."('".$targetid."', '".$newfilename."', '".$thumb."');" : "")
				."
			});
			";
		}
		
		else {
			if ($newfileid)
			{
				$_newfile = JTable::getInstance('flexicontent_files', '');
				$_newfile->load($newfileid);
				
				if ($folder_mode) {
					$file_path = $img_folder . DS . $_newfile->filename;
				} else if (!$_newfile->url && substr($_newfile->filename, 0, 7)!='http://') {
					$path = !empty($_newfile->secure) ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;  // JPATH_ROOT . DS . <media_path | file_path>
					$file_path = $path . DS . $_newfile->filename;
				} else {
					$file_path = $_newfile->filename;
				}
				$file_path = str_replace('\\', '/', $file_path);
				
				$imageexts = array('jpg','gif','png','bmp','jpeg');
				$ext = strtolower($_newfile->ext);
				$_f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
				$file_preview = !in_array($ext, $imageexts)  ?  ''  :  JURI::root() . 'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' .$file_path.$_f. '&w='.$thumb_w.'&h='.$thumb_h.'&zc=1';
			}
			
			
			$js = ($newfileid ?"
			var newfile_data = ".json_encode($_newfile).";
			" : "")."
			
			function qffileselementadd(obj, id, file, targetid, file_data) {
				var result = window.parent.qfSelectFile".$fieldid."(obj, id, file, targetid, file_data);
				if ((typeof result) != 'undefined' && result == 'cancel') return;
				obj.className = 'striketext';
				document.adminForm.file.value=id;
			}
			jQuery(document).ready(function()
			{
				// Find and mark file usage by filename search
				var existing_objs = jQuery(window.parent.document.body).find('.fcfieldval_container_".$fieldid." .existingname');
				for(i=0,n=existing_objs.length; i<n; i++) {
					var rows = jQuery.find('a[data-filename=\"'+ existing_objs[i].value +'\"]');
					jQuery(rows).addClass('striketext');
				}
				
				// Find and mark file usage by fileid search
				var id_objs = jQuery(window.parent.document.body).find('.fcfieldval_container_".$fieldid." input.contains_fileid');
				var imgids = Array();
				for(i=0,n=id_objs.length; i<n; i++) {
					if ( id_objs[i].value) imgids.push(id_objs[i].value);
				}
				for(i=0,n=imgids.length; i<n; i++) {
					var rows = jQuery.find('a[data-fileid=\"'+ imgids[i] +'\"]');
					jQuery(rows).addClass('striketext');
				}
				
				"
				.(($autoselect && $newfileid) ? "newfile_data.displayname = '".$newfilename."'; newfile_data.preview = '".$file_preview."';  qffileselementadd( document.getElementById('file".$newfileid."'), '".$newfileid."', '".$newfilename."', '".$targetid."', newfile_data);" : "")
				."
			});
			";
		}
		
		$document->addScriptDeclaration($js);
		if ($autoselect && $newfileid) {
			$app->enqueueMessage(JText::_( 'FLEXI_UPLOADED_FILE_WAS_SELECTED' ), 'message');
		}
		
		
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
		$lists['file-lang'] = flexicontent_html::buildlanguageslist('file-lang', '', '*', $display_file_lang_as, $allowed_langs, $published_only=false);
		
		
		/*************
		 ** FILTERS **
		 *************/
		
		// language filter
		$lists['language'] = ($filter_lang || 1 ? '<label class="label">'.JText::_('FLEXI_LANGUAGE').'</label>' : '').
			flexicontent_html::buildlanguageslist('filter_lang', 'class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()" size="1" ', $filter_lang, '-'/*2*/);
		
		// search
		$lists['search'] 	= $search;
		
		//search filter
		$filters = array();
		$filters[] = JHTML::_('select.option', '0', '- '.JText::_( 'FLEXI_ALL' ).' -' );
		$filters[] = JHTML::_('select.option', '1', JText::_( 'FLEXI_FILENAME' ) );
		$filters[] = JHTML::_('select.option', '2', JText::_( 'FLEXI_FILE_DISPLAY_TITLE' ) );
		$filters[] = JHTML::_('select.option', '3', JText::_( 'FLEXI_DESCRIPTION' ) );
		$lists['scope'] = '
			<span class="hasTooltip" style="display:inline-block; padding:0; margin:0;" title="'.JText::_('FLEXI_SEARCH_TEXT_INSIDE').'"><i class="icon-info"></i></span>
			'.JHTML::_('select.genericlist', $filters, 'scope', 'size="1" class="use_select2_lib fc_skip_highlight" onchange="jQuery(\'#search\').attr(\'placeholder\', jQuery(this).find(\'option:selected\').text());" ', 'value', 'text', $scope );
		
		//build url/file filterlist
		$url 	= array();
		$url[] 	= JHTML::_('select.option',  '', '-'/*JText::_( 'FLEXI_ALL_FILES' )*/ );
		$url[] 	= JHTML::_('select.option',  'F', JText::_( 'FLEXI_FILE' ) );
		$url[] 	= JHTML::_('select.option',  'U', JText::_( 'FLEXI_URL' ) );

		$lists['url'] = ($filter_url || 1 ? '<label class="label">'.JText::_('FLEXI_ALL_FILES').'</label>' : '').
			JHTML::_('select.genericlist', $url, 'filter_url', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_url );

		//item lists
		/*$items_list = array();
		$items_list[] = JHTML::_('select.option', '', '- '. JText::_( 'FLEXI_FILTER_BY_ITEM' ) .' -' );
		foreach($items as $item) {
			$items_list[] = JHTML::_('select.option', $item->id, JText::_( $item->title ) . ' (#' . $item->id . ')' );
		}
		$lists['item_id'] = JHTML::_('select.genericlist', $items_list, 'item_id', 'size="1" class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_item );*/
		$lists['item_id'] = '<input type="text" name="item_id" size="1" class="inputbox" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()" value="'.$filter_item.'" />';
		
		//build secure/media filterlist
		$secure 	= array();
		$secure[] 	= JHTML::_('select.option',  '', '-'/*JText::_( 'FLEXI_ALL_DIRECTORIES' )*/ );
		$secure[] 	= JHTML::_('select.option',  'S', JText::_( 'FLEXI_SECURE_DIR' ) );
		$secure[] 	= JHTML::_('select.option',  'M', JText::_( 'FLEXI_MEDIA_DIR' ) );

		$lists['secure'] = ($filter_secure || 1 ? '<label class="label">'.JText::_('FLEXI_ALL_DIRECTORIES').'</label>' : '').
			JHTML::_('select.genericlist', $secure, 'filter_secure', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_secure );

		//build ext filterlist
		$lists['ext'] = ($filter_ext || 1 ? '<label class="label">'.JText::_('FLEXI_ALL_EXT').'</label>' : '').
			flexicontent_html::buildfilesextlist('filter_ext', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', $filter_ext, '-'/*1*/);

		//build uploader filterlist
		$lists['uploader'] = ($filter_uploader || 1 ? '<label class="label">'.JText::_('FLEXI_ALL_UPLOADERS').'</label>' : '').
			flexicontent_html::builduploaderlist('filter_uploader', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', $filter_uploader, '-'/*1*/);

		// table ordering
		$lists['order_Dir']	= $filter_order_Dir;
		$lists['order']			= $filter_order;
		
		// removed files
		$filelist = JRequest::getString('files');
		$file = JRequest::getInt('file');

		$filelist = explode(',', $filelist);
		$files = array();
		foreach ($filelist as $fileid) {
			
			if ($fileid && $fileid != $file) {
			$files[] = (int)$fileid;
			}
			
		}

		$files = implode(',', $files);
		if (strlen($files) > 0) {
			$files .= ',';
		}
		$files .= $file;
		
		//assign data to template
		$this->assignRef('target_dir', $target_dir);
		$this->assignRef('count_filters', $count_filters);
		$this->assignRef('params'     , $cparams);
		$this->assignRef('client'     , $client);
		$this->assignRef('lists'      , $lists);
		$this->assignRef('rows'       , $rows);
		$this->assignRef('folder_mode', $folder_mode);
		$this->assignRef('img_folder' , $img_folder);
		$this->assignRef('thumb_w'    , $thumb_w);
		$this->assignRef('thumb_h'    , $thumb_h);
		$this->assignRef('pagination' , $pagination);
		$this->assignRef('files' 			, $files);
		$this->assignRef('fieldid' 		, $fieldid);
		$this->assignRef('u_item_id' 	, $u_item_id);
		$this->assignRef('targetid' 	, $targetid);
		$this->assignRef('CanFiles'        , $perms->CanFiles);
		$this->assignRef('CanUpload'       , $perms->CanUpload);
		$this->assignRef('CanViewAllFiles' , $perms->CanViewAllFiles);
		$this->assignRef('files_selected'  , $files_selected);
		$this->assignRef('langs', $langs);
		
		$this->assignRef('option', $option);
		$this->assignRef('view', $view);

		parent::display($tpl);
	}
}
?>

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

jimport('legacy.view.legacy');
use Joomla\String\StringHelper;

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
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

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

		// *** BOF FILESELEMENT view specific ***
		if (!$u_item_id && $filter_item)   $u_item_id   = $filter_item;

		$autoassign = $app->getUserStateFromRequest( $option.'.'.$_view.'.autoassign',       'autoassign',       1, 				  'int' );

		$existing_class   = $app->getUserStateFromRequest( $option.'.'.$_view.'.existing_class',   'existing_class', 'existingname','string' );
		
		$targetid					= $app->getUserStateFromRequest( $option.'.'.$_view.'.targetid',    		 'targetid',     		 '', 				  'string' );
		$thumb_w					= $app->getUserStateFromRequest( $option.'.'.$_view.'.thumb_w',    			 'thumb_w',     		 120, 				'int' );
		$thumb_h					= $app->getUserStateFromRequest( $option.'.'.$_view.'.thumb_h',    			 'thumb_h',     		 90, 				  'int' );
		// *** EOF FILESELEMENT view specific ***

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
		
		$assigned_fields_labels = array('image'=>'image/gallery', 'file'=>'file', 'minigallery'=>'minigallery');
		$assigned_fields_icons = array('image'=>'picture_link', 'file'=>'page_link', 'minigallery'=>'film_link');


		// *** BOF FOLDER MODE specific ***

		// Add JS to document to initialize the file list
		// eg Find and mark file usage by fileid / filename search (respectively: DB mode / Folder mode)
		if ($folder_mode)
		{
			$js = "
			jQuery(document).ready(function()
			{
				var delfilename = '".$delfilename."';
				var remove_existing_files_from_list = 0;
				var remove_new_files_from_list = 0;
				
				// Find and mark file usage by filename search
				var original_objs = jQuery(window.parent.document.body).find('.fcfieldval_container_".$fieldid." .originalname');  // newly selected field values, not yet saved in DB
				var existing_objs = jQuery(window.parent.document.body).find('.fcfieldval_container_".$fieldid." .".$existing_class."');  // existing (or optionally newly selected too) field values, already saved in DB
				
				var imgobjs = Array();
				for (i=0,n=original_objs.length; i<n; i++)
				{
					if (original_objs[i].value) imgobjs.push(original_objs[i].value);
					if (delfilename!='' && original_objs[i].value == delfilename)
					{
						//window.parent.fcfield_assignImage".$fieldid."('".$targetid."', '', '', 0);
						remove_existing_files_from_list = 1;
					}
				}
				for (i=0,n=existing_objs.length; i<n; i++)
				{
					if (existing_objs[i].value) imgobjs.push(existing_objs[i].value);
					if (delfilename!='' && existing_objs[i].value == delfilename)
					{
						//window.parent.fcfield_assignImage".$fieldid."('".$targetid."', '', '', 0);
						remove_new_files_from_list = 1;
					}
				}
				
				if ( remove_existing_files_from_list || remove_new_files_from_list )
				{
					mssg = '".JText::_('FLEXI_DELETE_FILE_IN_LIST_WINDOW_MUST_CLOSE')."';
					mssg = mssg + '\\n' + (remove_existing_files_from_list ? '".JText::_('FLEXI_EXISTING_FILE_REMOVED_SAVE_RECOMMENEDED',true)."' : '');
					alert( mssg );
					//window.parent.fcfield_assignImage".$fieldid."('".$targetid."', '', '', 0);
				}
				
				for (i=0,n=imgobjs.length; i<n; i++)
				{
					var rows = jQuery.find('a[data-filename=\"'+ imgobjs[i] +'\"]');
					jQuery(rows).addClass('striketext');
				}

				".($autoassign && count($pending_file_names) ? "
				jQuery('td.is-pending-file input').next().trigger('click');
				" : "")."

				".($autoassign==1 && count($pending_file_names) ? "
				jQuery('#insert_selected_btn').trigger('click');
				" : "")."
			});
			";
		}
		
		else
		{
			$js = "
			jQuery(document).ready(function()
			{
				// Find and mark file usage by filename search
				var existing_objs = jQuery(window.parent.document.body).find('.fcfieldval_container_".$fieldid." .".$existing_class."');  // existing (or optionally newly selected too) field values, already saved in DB
				for (i=0,n=existing_objs.length; i<n; i++)
				{
					var rows = jQuery.find('a[data-filename=\"'+ existing_objs[i].innerHTML +'\"]');
					jQuery(rows).addClass('striketext');
				}
				
				// Find and mark file usage by fileid search
				var id_objs = jQuery(window.parent.document.body).find('.fcfieldval_container_".$fieldid." input.contains_fileid');
				var imgids = Array();
				for (i=0,n=id_objs.length; i<n; i++)
				{
					if ( id_objs[i].value) imgids.push(id_objs[i].value);
				}
				for (i=0,n=imgids.length; i<n; i++)
				{
					var rows = jQuery.find('a[data-fileid=\"'+ imgids[i] +'\"]');
					jQuery(rows).addClass('striketext');
				}

				".($autoassign && count($pending_file_names) ? "
				jQuery('td.is-pending-file input').next().trigger('click');
				" : "")."

				".($autoassign==1 && count($pending_file_names) ? "
				jQuery('#insert_selected_btn').trigger('click');
				" : "")."
			});
			";
		}


		$js .= "			
			var fcfiles_keep_modal = 0;
			var fcfiles_targetid = '".$targetid."';

			function fc_fileselement_assign_files(el)
			{
				var rows = jQuery('input[name=\"cid[]\"]:checked');
				if (!rows.length)
				{
					alert('Please select some files');
					return;
				}
				
				jQuery('#fileman_tabset').hide();
				jQuery('#fileman_tabset').prev().show();
				
				setTimeout(function()
				{
					fcfiles_keep_modal = 1;
					var row_count = 0;
					rows.each(function(index, value)
					{
						var row = jQuery(this).closest('tr');
						var assign_file_btn = row.find('.fc_set_file_assignment');
						if (row_count > 0 || fcfiles_targetid == '')
						{
							// Add after given element or at end if element was not provided
							fcfiles_targetid = fcfiles_targetid ? 
								window.parent.addField".$fieldid."(null, null, jQuery('#'+fcfiles_targetid, parent.document).closest('li'), {insert_before: 0, scroll_visible: 0, animate_visible: 0}) :
								window.parent.addField".$fieldid."(null);
						}
						if (fcfiles_targetid == 'cancel')  return false;  // Stop .each() loop
						row_count++;
						assign_file_btn.trigger('click');
					});
					window.parent.fc_field_dialog_handle_".$field->id.".dialog('close');
				}, 50);
			}

			function fc_fileselement_assign_file(target_valuebox_tagid, file_data, f_preview)
			{
				file_data.preview = f_preview;
				var result = window.parent.fcfield_assignFile".$fieldid."(target_valuebox_tagid, file_data, fcfiles_keep_modal);
				if (result != 'cancel')
				{
					jQuery('file'+file_data.id).className = 'striketext';
				}
			}

			function fc_fileselement_delete_files()
			{
				if (document.adminForm.boxchecked.value==0)
				{
					alert('". flexicontent_html::encodeHTML(JText::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_DELETE')), 'd')."');
				}
				else
				{
					if (confirm('".flexicontent_html::encodeHTML(JText::_('FLEXI_ARE_YOU_SURE'), 's')."')) Joomla.submitbutton('filemanager.remove');
				}
			}
		";


		$document->addScriptDeclaration($js);
		if ($autoassign==2 && count($pending_file_names))
		{
			$app->enqueueMessage(JText::_( 'FLEXI_UPLOADED_FILES_SELECTED_CLICK_TO_ASSIGN' ), 'message');
		}

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
		
		if ($layout!='image')
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
	}
}

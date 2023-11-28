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
 * HTML View class for the Fileselement View
 */
class FlexicontentViewFileselement extends FlexicontentViewBaseRecords
{
	var $proxy_option   = null;
	var $title_propname = 'filename';
	var $state_propname = 'published';
	var $db_tbl         = 'flexicontent_files';
	var $name_singular  = 'file';

	public function display($tpl = null)
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		/**
		 * Initialise variables
		 */
		// Use filemanager controller
		$this->ctrl = 'filemanager';

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

		// Load Joomla language files of other extension
		if (!empty($this->proxy_option))
		{
			JFactory::getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, null, true);
		}

		// Get model
		$model   = $this->getModel();
		$model_s = $this->getModel($this->name_singular);

		// Performance statistics
		if ($print_logging_info = $cparams->get('print_logging_info'))
		{
			global $fc_run_times;
		}

		$langs = FLEXIUtilities::getLanguages('code');
		/*
		$authorparams = flexicontent_db::getUserConfig($user->id);
		$allowed_langs = !$authorparams ? null : $authorparams->get('langs_allowed',null);
		$allowed_langs = !$allowed_langs ? null : FLEXIUtilities::paramToArray($allowed_langs);
		*/
		$allowed_langs = null;
		$display_file_lang_as = $cparams->get('display_file_lang_as', 3);


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

		if (!$fieldid && $view === 'fileselement' && !$jinput->getCmd('isxtdbtn', ''))
		{
			jexit('<div class="alert alert-info">no valid field ID</div>');
		}

		$_view = $view . $fieldid;


		/**
		 * Get filters and ordering
		 */

		$count_filters = 0;

		// Order and order direction
		$filter_order      = $model->getState('filter_order');
		$filter_order_Dir  = $model->getState('filter_order_Dir');

		// Various filters
		$filter_state     = $model->getState('filter_state');
		$filter_access    = $model->getState('filter_access');
		$filter_lang      = $model->getState('filter_lang');
		$filter_url       = $model->getState('filter_url');
		$filter_secure    = $model->getState('filter_secure');
		$filter_stamp     = $model->getState('filter_stamp');

		$filter_ext       = $model->getState('filter_ext');
		$filter_uploader  = $model->getState('filter_uploader');
		$filter_item      = $model->getState('filter_item');

		$target_dir = $layout === 'image' ? 0 : 2;  // 0: Force media, 1: force secure, 2: allow selection
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


		/**
		 * Column selection of optional columns given
		 */

		if (!empty($filelist_cols))
		{
			foreach($filelist_cols as $col)
			{
				$cols[$col] = 1;
			}

			unset($cols['_SAVED_']);
		}
		
		/*
		 * Column selection of optional columns not given)
		 */

		// Filemanager view, add all columns
		elseif ($view === 'filemanager')
		{
			foreach($optional_cols as $col)
			{
				$cols[$col] = 1;
			}
		}

		// Fileselement view, add none of optional columns
		else ;

		if ($layout !== 'image')
		{
			if (strlen($filter_state)) $count_filters++;
			if (strlen($filter_access)) $count_filters++;
			if (strlen($filter_lang)) $count_filters++;
			if (strlen($filter_url)) $count_filters++;
			if (strlen($filter_stamp)) $count_filters++;
			if (strlen($filter_secure)) $count_filters++;
		}

		// ?? Force unsetting language and target_dir columns if LAYOUT is image file list
		else
		{
			unset($cols['lang']);
			unset($cols['target']);
		}

		// Case of uploader column not applicable or not allowed
		if (!$folder_mode && !$perms->CanViewAllFiles) unset($cols['uploader']);

		if (strlen($filter_ext)) $count_filters++;
		if (strlen($filter_uploader)) $count_filters++;
		if (strlen($filter_item)) $count_filters++;

		$u_item_id = $view === 'fileselement' ? $app->getUserStateFromRequest( $option.'.'.$_view.'.u_item_id', 'u_item_id', 0, 'string' ) : null;

		// *** BOF FILESELEMENT view specific ***
		if (!$u_item_id && $filter_item)   $u_item_id   = $filter_item;

		$autoassign = $app->getUserStateFromRequest( $option.'.'.$_view.'.autoassign',       'autoassign',       1, 				  'int' );

		$existing_class   = $app->getUserStateFromRequest( $option.'.'.$_view.'.existing_class',   'existing_class', 'existingname','string' );

		$targetid					= $app->getUserStateFromRequest( $option.'.'.$_view.'.targetid',    		 'targetid',     		 '', 				  'string' );
		$thumb_w					= $app->getUserStateFromRequest( $option.'.'.$_view.'.thumb_w',    			 'thumb_w',     		 120, 				'int' );
		$thumb_h					= $app->getUserStateFromRequest( $option.'.'.$_view.'.thumb_h',    			 'thumb_h',     		 90, 				  'int' );
		// *** EOF FILESELEMENT view specific ***

		// Text search
		$scope  = $model->getState('scope');
		$search = $model->getState('search');
		$search = StringHelper::trim(StringHelper::strtolower($search));


		// *** TODO: (enhancement) get recently deleted file(s), and remove their assignments from current form
		$delfilename = $app->getUserState('delfilename', null);
		$app->setUserState('delfilename', null);

		$upload_context = 'fc_upload_history.item_' . $u_item_id . '_field_' . $fieldid;
		$session_files = $session->get($upload_context, array());

		$pending_file_ids = !empty($session_files['ids_pending']) ? $session_files['ids_pending'] : array();
		$pending_file_names = !empty($session_files['names_pending']) ? $session_files['names_pending'] : array();

		$pending_file_names = array_flip($pending_file_names);


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

			// Fields common CSS
			$document->addStyleSheet(JUri::root(true).'/components/com_flexicontent/assets/css/flexi_form_fields.css', array('version' => FLEXI_VHASH));

			// Add JS frameworks
			flexicontent_html::loadFramework('select2');
			flexicontent_html::loadFramework('prettyCheckable');
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
		if ($view !== 'fileselement')
		{
			FLEXIUtilities::ManagerSideMenu('CanFiles');
		}

		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_FILEMANAGER' );
		$site_title = $document->getTitle();

		if ($view !== 'fileselement')
		{
			JToolbarHelper::title( $doc_title, 'files' );
		}

		$document->setTitle($doc_title .' - '. $site_title);

		// Create the toolbar
		$this->setToolbar();


		/**
		 * Get data from the model, note data retrieval must be before 
		 * getTotal() and getPagination() because it also calculates total rows
		 */

		// DB mode
		if ( !$folder_mode )
		{
			$rows_pending = $view === 'fileselement'
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
			$rows_pending = $view === 'fileselement'
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

		// Create pagination object
		$pagination = $this->get('Pagination');

		$assigned_fields_labels = array('image'=>'image/gallery', 'file'=>'file', /*'minigallery'=>'minigallery'*/);
		$assigned_fields_icons = array('image'=>'picture_link', 'file'=>'page_link', /*'minigallery'=>'film_link'*/);


		// *** BOF FOLDER MODE specific ***

		// Add JS to document to initialize the file list
		// eg Find and mark file usage by fileid / filename search (respectively: DB mode / Folder mode)
		if ($folder_mode)
		{
			$js = "
			document.addEventListener('DOMContentLoaded', function()
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
			document.addEventListener('DOMContentLoaded', function()
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
								window.parent.addField".$fieldid."(null, null, jQuery('#'+fcfiles_targetid, parent.document).closest('li.fcfieldval_container'), {insert_before: 0, scroll_visible: 0, animate_visible: 0}) :
								window.parent.addField".$fieldid."(null);
						}
						if (fcfiles_targetid == 'cancel')  return false;  // Stop .each() loop
						row_count++;
						assign_file_btn.trigger('click');
					});
					window.parent.fc_field_dialog_handle_".$fieldid.".dialog('close');
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

		/**
		 * FILE UPLOAD FORM
		 */

		$ffields = array();

		// Language form field
		$elementid = 'file-lang';
		$fieldname = 'file-lang';

		$ffields['file-lang'] = flexicontent_html::buildlanguageslist(
			$fieldname,
			array(
				'class' => $this->select_class,
			),
			'*',
			$display_file_lang_as,
			$allowed_langs,
			$published_only = false
		);


		// Access level form field
		$elementid = 'file-access';
		$fieldname = 'file-access';

		$options = JHtml::_('access.assetgroups');

		$ffields['file-access'] = JHtml::_('select.genericlist',
			$options,
			$fieldname,
			array(
				'class' => $this->select_class,
			),
			'value',
			'text',
			null,
			$elementid,
			$translate = true
		);


		/**
		 * Create List Filters
		 */

		$lists = array();


		// Build publication state filter
		//$options = JHtml::_('jgrid.publishedOptions');
		$options = array();

		$options[] = JHtml::_('select.option',  '', '-'/*JText::_( 'FLEXI_SELECT_STATE' )*/ );
		$options[] = JHtml::_('select.option',  'P', JText::_( 'FLEXI_PUBLISHED' ) );
		$options[] = JHtml::_('select.option',  'U', JText::_( 'FLEXI_UNPUBLISHED' ) );
		//$options[] = JHtml::_('select.option',  'A', JText::_( 'FLEXI_ARCHIVED' ) );
		//$options[] = JHtml::_('select.option',  'T', JText::_( 'FLEXI_TRASHED' ) );

		$fieldname = 'filter_state';
		$elementid = 'filter_state';
		$value     = $filter_state;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_STATE'),
			'html' => JHtml::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'class' => $this->select_class,
					'size' => '1',
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'value',
				'text',
				$value,
				$elementid,
				$translate = true
			),
		));


		// Build access level filter
		$options = JHtml::_('access.assetgroups');
		array_unshift($options, JHtml::_('select.option', '', '-'/*'JOPTION_SELECT_ACCESS'*/));

		$fieldname = 'filter_access';
		$elementid = 'filter_access';
		$value     = $filter_access;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_ACCESS'),
			'html' => JHtml::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'class' => $this->select_class,
					'size' => '1',
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'value',
				'text',
				$value,
				$elementid,
				$translate = true
			),
		));


		// Build language filter
		$lists['filter_lang'] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_LANGUAGE'),
			'html' => flexicontent_html::buildlanguageslist(
				'filter_lang',
				array(
					'class' => $this->select_class,
					'size' => '1',
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				$filter_lang,
				'-'
			)
		));


		if ($layout !== 'image' || $view !== 'fileselement')
		{
			// Build url/file filter
			$url 	= array();
			$url[] 	= JHtml::_('select.option',  '', '-'/*JText::_( 'FLEXI_ALL_FILES' )*/ );
			$url[] 	= JHtml::_('select.option',  'F', JText::_( 'FLEXI_FILE' ) );
			$url[] 	= JHtml::_('select.option',  'U', JText::_( 'FLEXI_URL' ) );

			$lists['filter_url'] = $this->getFilterDisplay(array(
				'label' => JText::_('FLEXI_ALL_FILES'),
				'html' => JHtml::_('select.genericlist',
					$url,
					'filter_url',
					array(
						'class' => $this->select_class,
						'size' => '1',
						'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
					),
					'value',
					'text',
					$filter_url
				)
			));


			// Build stamp filter
			$stamp 	= array();
			$stamp[] 	= JHtml::_('select.option',  '', '-'/*JText::_( 'FLEXI_ALL_FILES' )*/ );
			$stamp[] 	= JHtml::_('select.option',  'N', JText::_( 'FLEXI_NO' ) );
			$stamp[] 	= JHtml::_('select.option',  'Y', JText::_( 'FLEXI_YES' ) );

			$lists['filter_stamp'] = $this->getFilterDisplay(array(
				'label' => JText::_('FLEXI_DOWNLOAD_STAMPING'),
				'html' => JHtml::_('select.genericlist',
					$stamp,
					'filter_stamp',
					array(
						'class' => $this->select_class,
						'size' => '1',
						'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
					),
					'value',
					'text',
					$filter_stamp
				)
			));
		}


		// Build content item id filter
		$lists['item_id'] = $this->getFilterDisplay(array(
			'label' => JText::_('Item id'),
			'html' => '<input type="text" name="item_id" size="1" class="inputbox" onchange="if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform()" value="'.$filter_item.'" />',
		));


		// Build target folder (secure / media) filter
		$_secure_info = '<i data-placement="bottom" class="icon-info hasTooltip" title="'.flexicontent_html::getToolTip('FLEXI_URL_SECURE', 'FLEXI_URL_SECURE_DESC', 1, 1).'"></i>';

		if ($target_dir==2)
		{
			$secure 	= array();
			$secure[] 	= JHtml::_('select.option',  '', '-'/*JText::_( 'FLEXI_ALL_DIRECTORIES' )*/ );
			$secure[] 	= JHtml::_('select.option',  'S', JText::_( 'FLEXI_SECURE_DIR' ) );
			$secure[] 	= JHtml::_('select.option',  'M', JText::_( 'FLEXI_MEDIA_DIR' ) );

			$lists['filter_secure'] = $this->getFilterDisplay(array(
				'label' => $_secure_info . ' ' . JText::_('FLEXI_URL_SECURE'),
				'html' => JHtml::_('select.genericlist',
					$secure,
					'filter_secure',
					array(
						'class' => $this->select_class,
						'size' => '1',
						'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
					),
					'value',
					'text',
					$filter_secure
				)
			));
		}
		else
		{
			$lists['filter_secure'] = $this->getFilterDisplay(array(
				'label' => $_secure_info . ' ' . JText::_('FLEXI_URL_SECURE'),
				'html' => '<span class="badge bg-info badge-info">' . JText::_($target_dir == 0 ? 'FLEXI_MEDIA_DIR' : 'FLEXI_SECURE_DIR') . '</span>',
			));
		}


		// Build extension filter
		$lists['filter_ext'] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_ALL_EXT'),
			'html' => flexicontent_html::buildfilesextlist(
				'filter_ext',
				array(
					'class' => $this->select_class,
					'size' => '1',
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				$filter_ext,
				'-'
			)
		));


		// Build uploader filter
		if ($perms->CanViewAllFiles)
		{
			$lists['filter_uploader'] = $this->getFilterDisplay(array(
				'label' => JText::_('FLEXI_ALL_UPLOADERS'),
				'html' => flexicontent_html::builduploaderlist(
					'filter_uploader',
					array(
						'class' => $this->select_class,
						'size' => '1',
						'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
					),
					$filter_uploader,
					'-'
				)
			));
		}


		// Build text search scope
		$scopes = !$folder_mode ? null : array(
			'a.filename' => JText::_('FLEXI_FILENAME'),
		);

		$lists['scope_tip'] = '';
		$lists['scope'] = $this->getScopeSelectorDisplay($scopes, $scope);
		$this->scope_title = isset($scopes[$scope]) ? $scopes[$scope] : reset($scopes);


		// Text search filter value
		$lists['search'] = $search;


		// Table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order']     = $filter_order;

		// Uploadstuff
		jimport('joomla.client.helper');
		$require_ftp = !JClientHelper::hasCredentials('ftp');


		/**
		 * Assign data to template
		 */

		$this->target_dir = $target_dir;
		$this->optional_cols = $optional_cols;
		$this->cols = $cols;
		$this->count_filters = $count_filters;

		$this->params      = $cparams;
		$this->ffields     = $ffields;
		$this->lists       = $lists;
		$this->rows        = $rows_pending ?: $rows;
		$this->is_pending  = $rows_pending ? true : false;
		$this->langs       = $langs;

		$this->folder_mode = $folder_mode;
		$this->assign_mode = $assign_mode;
		$this->pagination  = $pagination;

		$this->perms  = FlexicontentHelperPerm::getPerm();

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
		$this->state  = $this->get('State');

		if ($view === 'fileselement')
		{
			$this->img_folder = $img_folder;
			$this->thumb_w    = $thumb_w;
			$this->thumb_h    = $thumb_h;
			$this->targetid   = $targetid;
		}
		elseif (!$jinput->getCmd('nosidebar'))
		{
			$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;
		}

		/**
		 * Render view's template
		 */

		if ( $print_logging_info ) { global $fc_run_times; $start_microtime = microtime(true); }

		parent::display($tpl);

		if ( $print_logging_info ) @$fc_run_times['template_render'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
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

<?php
/**
 * @package         FLEXIcontent
 * @version         3.4
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2020, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

defined( '_JEXEC' ) or die( 'Restricted access' );

// Public handlers may run before the database helper is autoloaded.
require_once JPATH_SITE . '/components/com_flexicontent/classes/helpers/security.php';
// Lazy class loading (was JLoader::register): loaded only if/when actually used.
spl_autoload_register(static function ($class) {
	$map = array(
		'FCField'                           => JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php',
		'FlexicontentControllerFilemanager' => JPATH_BASE . '/components/com_flexicontent/controllers/filemanager.php',
		'FlexicontentModelFilemanager'      => JPATH_BASE . '/components/com_flexicontent/models/filemanager.php',
	);
	if (isset($map[$class]) && is_file($map[$class])) require_once $map[$class];
}, true, true);

class plgFlexicontent_fieldsFile extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = array('share_file_form', 'share_file_email');  // Field's methods allowed to be called via AJAX

	// ***
	// *** CONSTRUCTOR
	// ***

	public function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}



	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	public function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = $field->parameters->get('label_form') ? \Joomla\CMS\Language\Text::_($field->parameters->get('label_form')) : \Joomla\CMS\Language\Text::_($field->label);

		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);

		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if (!isset($field->formhidden_grp)) $field->formhidden_grp = $field->formhidden;
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;

		// Initialize framework objects and other variables
		$document = Factory::getApplication()->getDocument();
		$cparams  = ComponentHelper::getParams( 'com_flexicontent' );
		$app  = Factory::getApplication();
		$user = Factory::getApplication()->getIdentity();

		$tooltip_class = 'hasTooltip';
		$add_on_class    = $cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
		$input_grp_class = $cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';
		$btn_item_class  = $cparams->get('bootstrap_ver', 2)==2  ?  'btn' : 'btn btn-outline-secondary';
		$btn_group_class = $cparams->get('bootstrap_ver', 2)==2  ?  'btn-group' : 'btn-group';
		$form_font_icons = $cparams->get('form_font_icons', 1);
		$font_icon_class = $form_font_icons ? ' fcfont-icon' : '';
		$font_icon_class .= FLEXI_J40GE ? ' icon icon- ' : '';
		$tip_class     = $tooltip_class;  // Compatibility with older custom templates

		// Get a unique id to use as item id if current item is new
		$u_item_id = $item->id ? $item->id : substr(Factory::getApplication()->input->get('unique_tmp_itemid', '', 'string'), 0, 1000);


		/**
		 * Number of values
		 */

		$multiple     = $use_ingroup || (int) $field->parameters->get('allow_multiple', 1);
		$max_values   = $use_ingroup ? 0 : (int) $field->parameters->get('max_values', 0);
		$required     = (int) $field->parameters->get('required', 0);
		$add_position = (int) $field->parameters->get('add_position', 3);

		$use_myfiles      = (int) $field->parameters->get('use_myfiles', '1');

		$media_params     = ComponentHelper::getParams('com_media');
		$file_path        = $media_params->get('file_path', 'images');
		//$image_path       = $media_params->get('image_path', 'images');

		$jmedia_topdir    = $field->parameters->get('jmedia_topdir', $file_path);
		$jmedia_subpath   = $field->parameters->get('jmedia_subpath', '');
		$jmedia_filetypes = $field->parameters->get('jmedia_filetypes', array('folders', 'images', 'docs', 'videos', 'audios'));

		$media_params = ComponentHelper::getParams('com_media');
		$imagesExt    = in_array('images', $jmedia_filetypes)
			? array_map('trim', explode(',', $media_params->get('image_extensions', 'bmp,gif,jpg,jpeg,png,webp')))
			: [];
		$videosExt    = in_array('videos', $jmedia_filetypes)
			? array_map('trim', explode(',', $media_params->get('video_extensions', 'mp4,mp4v,mpeg,mov,webm')))
			: [];
		$audiosExt    = in_array('audios', $jmedia_filetypes)
			? array_map('trim', explode(',', $media_params->get('audio_extensions', 'mp3,m4a,mp4a,ogg')))
			: [];
		$documentsExt = in_array('docs', $jmedia_filetypes)
			? array_map('trim', explode(',', $media_params->get('doc_extensions', 'doc,odg,odp,ods,odt,pdf,ppt,txt,xcf,xls,csv')))
			: [];

		$mediaTypes = [];
		$mediaTypeNames = [];
		if (version_compare(\Joomla\CMS\Version::MAJOR_VERSION, '4', 'ge'))
		{
			// 0: images, 1: audios, 2: videos, 3: documents * 'folders' is always included in J4
			$mediaTypes = [];
			if (in_array('images', $jmedia_filetypes)) { $mediaTypes[] = '0'; $mediaTypeNames[] = 'Images'; }
			if (in_array('audios', $jmedia_filetypes)) { $mediaTypes[] = '1'; $mediaTypeNames[] = 'Audios'; }
			if (in_array('videos', $jmedia_filetypes)) { $mediaTypes[] = '2'; $mediaTypeNames[] = 'Videos'; }
			if (in_array('docs',   $jmedia_filetypes)) { $mediaTypes[] = '3'; $mediaTypeNames[] = 'Documents'; }
		}
		else {
			$fileTypes = implode(',', $jmedia_filetypes); // Supported values: 'folders,images,docs,videos' * audios will be ignored in J3
		}
		// Only implode the mediaTypes array , but not the mediaTypeNames array, since it is not used in the layout file as an array
		$mediaTypes = implode(',', $mediaTypes);  // * 'folders' is always included in J4

		// Classes for marking field required
		$required_class = $required ? ' required' : '';

		// If we are multi-value and not inside fieldgroup then add the control buttons (move, delete, add before/after)
		$add_ctrl_btns = !$use_ingroup && $multiple;

		$form_info_header = (int) $field->parameters->get('form_info_header', 3);

		// Inline file property editing
		$inputmode = (int)$field->parameters->get( 'inputmode', 1 ) ;  // 1: file selection only,  0: inline file properties editing
		$top_notice = '';//$use_ingroup ? '<div class="alert alert-warning">Field group mode is not implenent in current version, please disable</div>' : '';

		$iform_allowdel = 0;//$field->parameters->get('iform_allowdel', 1);
		$fields_box_placing = (int) $field->parameters->get('fields_box_placing', 1);
		$show_values_expand_btn = (int) $field->parameters->get('show_values_expand_btn', 1);
		$form_file_preview  = (int) $field->parameters->get('form_file_preview', 2);

		$iform_title = $inputmode==1 ? 0 : $field->parameters->get('iform_title', 1);
		$iform_desc  = $inputmode==1 ? 0 : $field->parameters->get('iform_desc',  1);
		$iform_lang  = $inputmode==1 ? 0 : $field->parameters->get('iform_lang',  0);
		$iform_access= $inputmode==1 ? 0 : $field->parameters->get('iform_access',0);
		$iform_dir   = $inputmode==1 ? 0 : $field->parameters->get('iform_dir',   0);
		$iform_stamp = $inputmode==1 ? 0 : $field->parameters->get('iform_stamp', 0);
		if (!Factory::getApplication()->getIdentity()->authorise('flexicontent.editfileproperties', 'com_flexicontent'))
		{
			$iform_lang = $iform_access = $iform_stamp = 0;
		}

		$custom_props = self::getCustomProps($field, $inputmode);

		$custom_prop_defaults = array();
		foreach ($custom_props as $n => $cp)
		{
			if ($cp['form']) $custom_prop_defaults['custom' . $n] = $cp['default'];
		}

		$secure_default = !$iform_dir   ? 1 : (int) $field->parameters->get('iform_dir_default', 1);
		$stamp_default  = !$iform_stamp ? 1 : (int) $field->parameters->get('iform_stamp_default', 1);

		$mediapath   = $cparams->get('media_path', 'components/com_flexicontent/medias');
		$docspath    = $cparams->get('file_path', 'components/com_flexicontent/uploads');
		$imagesExt   = array('jpg', 'png', 'gif', 'xcf', 'odg', 'wbmp', 'bmp', 'ico', 'jpeg', 'webp');


		$thumb_size_resizer = (int) $field->parameters->get('thumb_size_resizer', 0);
		$thumb_size_default = (int) $field->parameters->get('thumb_size_default', 120);
		$preview_thumb_w = $preview_thumb_h = 600;

		// Inline uploaders flags
		$use_inline_uploaders = $use_myfiles != 4;
		$usemediaurl = 0; //(int) $field->parameters->get('use_mediaurl', 0);
		$file_btns_position = 0; //(int) $field->parameters->get('file_btns_position', 0);


		/**
		 * Slider for files grid view
		 */

		if ($use_inline_uploaders && $thumb_size_resizer)
		{
			flexicontent_html::loadFramework('nouislider');

			$resize_cfg = new stdClass();
			$resize_cfg->slider_name = 'fcfield-'.$field->id.'-uploader-thumb-size';
			$resize_cfg->element_selector = 'div.fcfield-'.$field->id.'-uploader-thumb-box';

			$resize_cfg->elem_container_selector = '.fcfieldval_container_'.$field->id.' div.fc-box';
			$resize_cfg->element_selector = '.fcfieldval_container_'.$field->id.' div.fc_file_uploader ul.plupload_filelist > li';

			$resize_cfg->element_class_prefix = 'thumb_';
			$resize_cfg->values = array(90, 120, 150, 200, 250);
			$resize_cfg->labels = array();

			$thumb_size_default = $app->input->cookie->get($resize_cfg->slider_name . '-val', $thumb_size_default, 'int');
			$resize_cfg->initial_pos = (int) array_search($thumb_size_default, $resize_cfg->values);
			if ($resize_cfg->initial_pos === false)
			{
				$resize_cfg->initial_pos = 1;
			}
			$thumb_size_default = $resize_cfg->values[$resize_cfg->initial_pos];
			$app->input->cookie->set($resize_cfg->slider_name . '-val', $thumb_size_default);

			foreach($resize_cfg->values as $value) $resize_cfg->labels[] = $value .'x'. $value;

			$element_classes = array();
			foreach($resize_cfg->values as $value) $element_classes[] = $resize_cfg->element_class_prefix . $value;

			$element_class_list = implode(' ', $element_classes);
			$step_values = '[' . implode(', ', $resize_cfg->values) . ']';
			$step_labels = '["' . implode('", "', $resize_cfg->labels) . '"]';

			$thumb_size_slider_cfg = "{
					name: '".$resize_cfg->slider_name."',
					step_values: ".$step_values.",
					step_labels: ".$step_labels.",
					initial_pos: ".$resize_cfg->initial_pos.",
					start_hidden: false,
					element_selector: '".$resize_cfg->element_selector."',
					element_class_list: '',
					element_class_prefix: '',
					elem_container_selector: '".$resize_cfg->elem_container_selector."',
					elem_container_class: '".$element_class_list."',
					elem_container_prefix: '".$resize_cfg->element_class_prefix."'
				}";

			$document->getWebAssetManager()->addInlineScript("
			FlexiForm.ready(function()
			{
				fc_attachSingleSlider(".$thumb_size_slider_cfg.", ".$thumb_size_resizer.");
			});
			");
		}

		// Empty field value
		if (!$field->value || (count($field->value) === 1 && reset($field->value) === null))
		{
			$files_data = array();
			$form_data = array();
			$field->value = array();
		}

		// Non empty field value
		else
		{
			$file_ids  = array();
			$form_data = array();

			// Check if reloading user data after form reload (e.g. due to form validation error)
			$v = reset($field->value);
			if (is_array($v) && isset($v['file-id']))
			{
				foreach($field->value as $v)
				{
					if (!isset($v['secure'])) $v['secure'] = !$iform_dir   ? 1 : (int) $field->parameters->get('iform_dir_default', 1);
					if (!isset($v['stamp']))  $v['stamp']  = !$iform_stamp ? 1 : (int) $field->parameters->get('iform_stamp_default', 1);
					$file_ids[] = $v['file-id'];
					$form_data[$v['file-id']] = $v;
				}
			}
			else
			{
				$file_ids = $field->value;
			}

			// Get file data for given file ids
			$files_data = $this->getFileData( $file_ids, $published=false );

			// Do not skip values if in fieldgroup
			if ($use_ingroup)
			{
				foreach($field->value as $i => $v)
				{
					$file_id = is_array($v) && isset($v['file-id']) ?  $v['file-id']  :  $v;
					$field->value[$i] = isset($files_data[$file_id]) ? (int)$file_id : 0;
				}
			}
			else
			{
				$field->value = array_keys($files_data);
			}
		}

		// Flag that indicates if field has values
		$has_values = !empty($field->value);

		// Inline mode needs an default value, TODO add for popup too ?
		if (empty($field->value) || $use_ingroup)
		{
			// Create an empty file properties value, used by code that creates empty inline file editing form fields
			if (empty($field->value))
			{
				$field->value = array(0 => 0);
			}
			$files_data[0] = (object)array(
				'id' => '', 'filename' => '', 'filename_original' => '', 'altname' => '', 'description' => '',
				'custom1' => isset($custom_props[1]) ? $custom_props[1]['default'] : '', 'custom2' => isset($custom_props[2]) ? $custom_props[2]['default'] : '',
				'url' => '',
				'secure' => (!$iform_dir  ? 1 : (int) $field->parameters->get('iform_dir_default', 1)),
				'stamp' => (!$iform_stamp ? 1 : (int) $field->parameters->get('iform_stamp_default', 1)),
				'ext' => '', 'published' => 1,
				'language' => $field->parameters->get('iform_lang_default', '*'),
				'access' => (int) $field->parameters->get('iform_access_default', 1),
				'hits' => 0,
				'uploaded' => '', 'uploaded_by' => 0, 'checked_out' => false, 'checked_out_time' => ''
			);
		}

		// Button for popup file selection
		$autoassign = (int) $field->parameters->get( 'autoassign', 1 ) ;
		$filesElementURL = \Joomla\CMS\Uri\Uri::base(true)
			.'/index.php?option=com_flexicontent&amp;view=fileselement&amp;tmpl=component'
			.'&amp;index=%s'
			.'&amp;field='.$field->id.'&amp;u_item_id='.$u_item_id.'&amp;autoassign='.$autoassign
			.'&amp;filter_uploader='.$user->id
			.'&amp;targetid=%s'
			.'&amp;existing_class=fc_filedata_storage_name'
			.'&amp;' . \Joomla\CMS\Session\Session::getFormToken() . '=1';

		// URL for modal fileselement view
		$addExistingURL = sprintf($filesElementURL, '__rowno__', '__thisid__');

		$_prompt_txt = \Joomla\CMS\Language\Text::_( 'FLEXI_FIELD_FILE_SELECT_FILE' );  //\Joomla\CMS\Language\Text::_( 'FLEXI_ADD_FILE' );

		// CSS classes of value container
		$value_classes_base     = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		$value_classes_single   = $value_classes_base;// . ' fc-expanded' ;
		$value_classes_multiple = $value_classes_base . ($fields_box_placing ? ' floated' : '');

		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;

		// JS safe Field name
		$field_name_js = str_replace('-', '_', $field->name);

		$js = "
			var fc_field_dialog_handle_".$field->id.";


			function file_fcfield_del_existing_value".$field->id."(el)
			{
				FlexiMediaField.setFileRemoval(el);
			}


			function fc_editFile_".$field->id."(element)
			{
				return fcfield_file.openEditDialog(element,
					".json_encode(\Joomla\CMS\Uri\Uri::base(true).'/index.php?option=com_flexicontent&task=filemanager.edit&view=file&id=__file_id__&tmpl=component&fcmodal=1&fieldid='.(int)$field->id.'&u_item_id='.(int)$item->id.'&'.\Joomla\CMS\Session\Session::getFormToken().'=1').",
					".json_encode(\Joomla\CMS\Uri\Uri::base(true).'/index.php?option=com_flexicontent&task=filemanager.checkin&cid[]=__file_id__&'.\Joomla\CMS\Session\Session::getFormToken().'=1').",
					".json_encode(\Joomla\CMS\Language\Text::_('FLEXI_FIELD_FILE_EDIT_FILE')).",
					".json_encode(\Joomla\CMS\Language\Text::_('FLEXI_FIELD_FILE_EDIT_NO_FILE'))."
				);
			}


			function fc_openFileSelection_".$field->id."(element)
			{
				var url = element.dataset.href.replace('__rowno__', element.dataset.rowno || '').replace('__thisid__', element.id);

				fcfield_file.dialog_handle['".$field_name_js."'] = fc_field_dialog_handle_".$field->id." = fc_showDialog(url, 'fc_modal_popup_container', 0, 0, 0, 0, {title: '".\Joomla\CMS\Language\Text::_('FLEXI_FIELD_FILE_SELECT_FILE', true)."', paddingW: 10, paddingH: 16});
				return false;
			}
		";
		$css = '';

		// Handle multiple records
		if ($multiple)
		{
			$config = array(
				'kind' => 'file', 'fieldKey' => $field_name_js,
				'previewAlways' => $form_file_preview === 1,
				'defaults' =>  $custom_prop_defaults + array('file-access' => '1', 'secure' => (string) $secure_default, 'stamp' => (string) $stamp_default),
			);
			$document->addScript(\Joomla\CMS\Uri\Uri::root(true) . '/components/com_flexicontent/assets/js/media-repeater.js', array('version' => FLEXI_VHASH));
			$js .= $this->buildValueRepeater($field, $elementid, $fieldname, $max_values, $add_ctrl_btns, $config, 'fcfield_media_rows');

			$remove_button = '<span class="' . $btn_item_class . ' fcfield-delvalue ' . $font_icon_class . '" title="'.\Joomla\CMS\Language\Text::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);"></span>';
			$move2         = '<span class="' . $btn_item_class . ' fcfield-drag-handle ' . $font_icon_class . '" title="'.\Joomla\CMS\Language\Text::_( 'FLEXI_CLICK_TO_DRAG' ).'"></span>';
			$add_here = '';
			$add_here .= $add_position==2 || $add_position==3 ? '<span class="' . $btn_item_class . ' fcfield-insertvalue fc_before ' . $font_icon_class . '" onclick="addField'.$field->id.'(null, this.closest(\'ul\'), this.closest(\'li\'), {insert_before: 1});" title="'.\Joomla\CMS\Language\Text::_( 'FLEXI_ADD_BEFORE' ).'"></span> ' : '';
			$add_here .= $add_position==1 || $add_position==3 ? '<span class="' . $btn_item_class . ' fcfield-insertvalue fc_after ' . $font_icon_class . '"  onclick="addField'.$field->id.'(null, this.closest(\'ul\'), this.closest(\'li\'), {insert_before: 0});" title="'.\Joomla\CMS\Language\Text::_( 'FLEXI_ADD_AFTER' ).'"></span> ' : '';
		}

		// Field not multi-value
		else
		{
			$remove_button = '';
			$move2         = '';
			$add_here = '';
			$js .= '';
			$css .= '';
		}


		// Add field's custom CSS / JS
		$js .= "
			/**
			 * Method Wrappers to class methods (used for compatibility)
			 */

			function fcfield_assignFile".$field->id."(value_container_id, file, keep_modal)
			{
				fcfield_file.assignFile(value_container_id, file, keep_modal, '".$field_name_js."');
			}

			/* Method used as callbacks of AJAX uploader */

			function fcfield_FileFiltered_".$field->id."(uploader, file)
			{
				fcfield_file.fileFiltered(uploader, file, '".$field_name_js."');
			}
			function fcfield_FileUploaded_".$field->id."(uploader, file, result)
			{
				fcfield_file.fileUploaded(uploader, file, result, '".$field_name_js."');
			}
		";
		$css .='';


		/**
		 * Load form JS
		 */

		// Add needed JS/CSS
		static $js_added = null;
		if ( $js_added === null )
		{
			$js_added = true;

			\Joomla\CMS\Language\Text::script('PLG_FLEXICONTENT_FIELDS_FILE_RESPONSE_PARSING_FAILED', false);
			\Joomla\CMS\Language\Text::script('PLG_FLEXICONTENT_FIELDS_FILE_FILE_NOT_FOUND', false);
			\Joomla\CMS\Language\Text::script('FLEXI_PLEASE_UPLOAD_A_FILE', true);

			flexicontent_html::loadFramework('flexi-lib');
			require_once JPATH_SITE . '/components/com_flexicontent/helpers/html/fcuploader.php';
			if (!\Joomla\CMS\HTML\HTMLHelper::getServiceRegistry()->hasService('fcuploader')) {
				\Joomla\CMS\HTML\HTMLHelper::getServiceRegistry()->register('fcuploader', 'JHtmlFcuploader');
			}
			flexicontent_html::loadFramework('media-field-controls');
			$document->getWebAssetManager()->registerAndUseScript('plg_flexicontent_fields_file.form', 'plugins/flexicontent_fields/file/js/form.js', array('version' => FLEXI_VHASH));
		}

		// Add field's CSS / JS
		if ($js)  $document->getWebAssetManager()->addInlineScript($js);
		if ($css) $document->getWebAssetManager()->addInlineStyle($css);


		/**
		 * Create field's HTML display for item form
		 */

		$field->html = array();
		$uploader_html_arr = array();

		$formlayout = $field->parameters->get('formlayout', '');
		$formlayout = $formlayout ? 'field_'.$formlayout : 'field_InlineBoxes';

		//$this->setField($field);
		//$this->setItem($item);
		//$this->displayField( $formlayout );

		$drop_btn_class =
			(FLEXI_J40GE
				? 'toolbar dropdown-toggle dropdown-toggle-split'
				: 'toolbar dropdown-toggle'
			);
		$drop_btn_style = 'padding: 0.4rem; box-sizing: border-box; max-height: 100%; margin: 0; height: 100%;';

		include(self::getFormPath($this->fieldtypes[0], $formlayout));

		// Use inline media field
		if ($use_myfiles == 4) {
			$document->getWebAssetManager()->addInlineScript('
				function jInsertFieldValue(value, id)
				{
					var box = FlexiMediaField.row(document.getElementById(id));
					var editor = window.fcfield_mediafile && box?.querySelector(\'[data-fc-media-key][data-fc-media-type="mediafile"]\') ? fcfield_mediafile : window.fcfield_file || fcfield_file;
					editor.selectMediaFile(id, value, '.json_encode(rtrim(Uri::root(), '/') . '/').');
				}
			');
		}

		foreach($field->html as $n => &$_html)
		{
			$uploader_html = $use_inline_uploaders ? $uploader_html_arr[$n] : false;
			$_html = '
				'.(!$add_ctrl_btns ? '' : '
				<div class="'.$btn_group_class.' fc-xpended-btns">
					'.$move2.'
					'.$remove_button.'
					'.(!$add_position ? '' : $add_here)
					.($use_inline_uploaders && !$file_btns_position ? '
					<div class="buttons '.$btn_item_class.' fc-iblock ' . (FLEXI_J40GE ? ' dropdown ' : '') . '" style="padding:0 !important;">
						<div role="button" class="' . $drop_btn_class . ' fcfield-addvalue ' . $font_icon_class . '" data-toggle="dropdown" data-bs-toggle="dropdown" style="'.$drop_btn_style.'">
							<span class="caret"></span>
						</div>
						<ul class="dropdown-menu dropdown-menu-right" role="menu">
							<li>'.$uploader_html->toggleBtn.'</li>
							<li>'.$uploader_html->multiUploadBtn.'</li>
							' . ($use_myfiles > 0 ? '<li>'.$uploader_html->myFilesBtn.'</li>' : '') . '
							<li>'.$uploader_html->mediaUrlBtn.'</li>
						</ul>
					</div>
					'.$uploader_html->clearBtn.'
					' : '') . '
				</div>
				'.($fields_box_placing ? '<div class="fcclear"></div>' : '').'
				').'

				<div class="fc-field-props-box fc-upload-props-box">

					'.($use_inline_uploaders && ($file_btns_position || !$add_ctrl_btns) ? '
					<div class="fcclear"></div>
					<div class="btn-group" style="margin: 4px 0 16px 0; display: inline-block;">
						<div class="'.$btn_group_class.' fc-xpended-btns">
							'.$uploader_html->toggleBtn.'
							'.$uploader_html->multiUploadBtn.'
							' . ($use_myfiles > 0 ? $uploader_html->myFilesBtn : '') . '
							'.$uploader_html->mediaUrlBtn.'
							'.$uploader_html->clearBtn.'
						</div>
					</div>
					' : '') . '

				'.$_html.'

				</div>
				';
		}
		unset($_html);

		// Do not convert the array to string if field is in a group
		if ($use_ingroup);

		// Handle multiple records
		elseif ($multiple)
		{
			$field->html = !count($field->html) ? '' :
				'<li class="' . $value_classes_multiple . '">'.
					implode('</li><li class="' . $value_classes_multiple . '">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			if (!$add_position) $field->html .= '
				<div class="'.$btn_group_class.' fc-xpended-btns">
					<span class="fcfield-addvalue ' . $font_icon_class . ' ' . $btn_item_class . '" onclick="addField'.$field->id.'(this.closest(\'.fc-xpended-btns\'));" title="'.\Joomla\CMS\Language\Text::_( 'FLEXI_ADD_TO_BOTTOM' ).'">
						'.\Joomla\CMS\Language\Text::_( 'FLEXI_ADD_VALUE' ).'
					</span>
				</div>';
		}

		// Handle single values
		else
		{
			$field->html = '<div class="'.$value_classes_single.'">' . $field->html[0] .'</div>';
		}


		// This is field HTML that is created regardless of values
		$non_value_html = '';//'<input id="custom_'.$field_name_js.'" class="fc_hidden_value '.($use_ingroup ? '' : $required_class).'" type="text" name="__fcfld_valcnt__['.$field->name.']" value="'.($count_vals ? $count_vals : '').'" />';
		if ($use_ingroup)
		{
			$uploader_html = reset($uploader_html_arr);

			$field->html[-1] = $non_value_html;
			if ($use_inline_uploaders && $uploader_html->thumbResizer)
			{
				$field->html[-1] = '<div class="label" style="float: left">' . $field->label . '</div>' . $uploader_html->thumbResizer . ' ' . $field->html[-1] . '<div class="clear"></div>';
			}
		}
		else
		{
			$field->html .= $non_value_html;
			if ($use_inline_uploaders && $uploader_html->thumbResizer)
			{
				$field->html = $uploader_html->thumbResizer . ' ' . $field->html;
			}
		}

		// Add toggle button for: Compact values view (= multiple values per row)
		$show_values_expand_btn = $formlayout === 'field_InlineBoxes' ? $show_values_expand_btn : 0;
		if (!$use_ingroup && $show_values_expand_btn)
		{
			$field->html = '
			<button type="button" class="fcfield-expand-view-btn btn btn-small" data-expandedFieldState="0" aria-label="' . \Joomla\CMS\Language\Text::_('FLEXI_EXPAND') . '"
				onclick="fc_toggleCompactValuesView(this, this.closest(\'.container_fcfield\'));"
			>
				<span class="fcfield-expand-view ' . $font_icon_class . '" aria-hidden="true"></span>&nbsp; ' . \Joomla\CMS\Language\Text::_('FLEXI_EXPAND', true) . '
			</button>
			' . $field->html;
		}

		// Button for popup file selection
		/*if (!$use_ingroup) $field->html .= '
			<input id="'.$elementid.'" class="'.$required_class.' fc_hidden_value" type="text" name="__fcfld_valcnt__['.$field->name.']" value="'.($n ? $n : '').'" />';*/
		if ($top_notice) $field->html = $top_notice.$field->html;
	}


	// Method to create field's HTML display for frontend views
	public function onDisplayFieldValue(&$field, $item, $values = null, $prop = 'display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = \Joomla\CMS\Language\Text::_($field->label);

		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);


		/**
		 * One time initialization
		 */

		static $initialized = null;
		static $app, $document, $option, $format, $realview;

		if ($initialized === null)
		{
			$initialized = 1;

			$app       = Factory::getApplication();
			$document  = Factory::getApplication()->getDocument();
			$option    = $app->input->getCmd('option', '');
			$format    = $app->input->getCmd('format', 'html');
			$realview  = $app->input->getCmd('view', '');
		}

		// Current view variable
		$view = $app->input->getCmd('flexi_callview', ($realview ?: 'item'));
		$sfx = $view === 'item' ? '' : '_cat';

		// Check if field should be rendered according to configuration
		if (!$this->checkRenderConds($prop, $view))
		{
			return;
		}

		// The current view is a full item view of the item
		$isMatchedItemView = static::$itemViewId === (int) $item->id;

		// Some variables
		$is_ingroup  = !empty($field->ingroup);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$multiple    = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 1 ) ;


		/**
		 * Get field values
		 */

		$values = $values ? $values : $field->value;

		// Check for no values and no default value, and return empty display
		if (empty($values))
		{
			$field->{$prop} = $is_ingroup ? array() : '';
			return;
		}

		// CSV export in a format that the batch import tool can read back (see onBeforeSaveField)
		if ($prop === 'csv_export' && $field->parameters->get('csv_export_format', 'html') === 'importable')
		{
			$field->{$prop} = array();

			foreach ($this->getFileData($values, $published = true) as $file_data)
			{
				// Add local file to the ZIP file of the CSV export (if enabled), this may rename the file
				$filename = $file_data->url ? $file_data->filename : flexicontent_csvmedia::add(
					'fcimport_docs',
					($file_data->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH) . DS . $file_data->filename,
					$file_data->filename
				);

				// Custom (HTML) properties are exported only when they have a value, so that older import code / sites are not affected
				$custom_tags = '';
				foreach (array(1, 2) as $cn)
				{
					$cval = isset($file_data->{'custom' . $cn}) ? (string) $file_data->{'custom' . $cn} : '';
					if (strlen(trim($cval))) $custom_tags .= '{Custom' . $cn . '}' . $cval . '{/Custom' . $cn . '}';
				}

				$field->{$prop}[] = ($file_data->url ? '{URL}' . $filename . '{/URL}' : $filename)
					. '{Altname}' . $file_data->altname . '{/Altname}'
					. '{Desc}' . $file_data->description . '{/Desc}'
					. $custom_tags
					. '{Ext}' . $file_data->ext . '{/Ext}';
			}

			if (!$is_ingroup)
			{
				$field->{$prop} = implode(ComponentHelper::getParams('com_flexicontent')->get('csv_export_field_multivalue_sep', '%%'), $field->{$prop});
			}

			return;
		}

		static $langs = null;
		if ($langs === null) $langs = FLEXIUtilities::getLanguages('code');

		// Load the tooltip library according to configuration, FLAG is an array to have a different check per field ID
		// This is needed ONLY for fields that also have a configuration parameter, for this field is redundant
		static $tooltips_added = array();
		if ( empty($tooltips_added[$field->id]) )
		{
			$add_tooltips = ComponentHelper::getParams( 'com_flexicontent' )->get('add_tooltips', 1);
			if ($add_tooltips) \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.tooltip');
			$tooltips_added[$field->id] = true;
		}


		/**
		 * Get common parameters like: itemprop, value's prefix (pretext), suffix (posttext), separator, value list open/close text (opentag, closetag)
		 * This will replace other field values and item properties, if such are found inside the parameter texts
		 */
		$common_params_array = $this->getCommonParams();
		extract($common_params_array);


		// some parameter shortcuts
		$tooltip_class = 'hasTooltip';


		$display_total_count = $field->parameters->get( 'display_total_count', 0 ) ;
		$total_count_label = \Joomla\CMS\Language\Text::_($field->parameters->get( 'total_count_label', 'FLEXI_FIELD_FILE_TOTAL_FILES' ));

		$display_total_hits  = $field->parameters->get( 'display_total_hits', 0 ) ;
		$total_hits_label = \Joomla\CMS\Language\Text::_($field->parameters->get( 'total_hits_label', 'FLEXI_FIELD_FILE_TOTAL_DOWNLOADS' ));

		$useicon = $field->parameters->get( 'useicon', 0 ) ;
		$lowercase_filename = $field->parameters->get( 'lowercase_filename', 1 ) ;

		$display_filename	= (int) $field->parameters->get( 'display_filename', 1 ) ;
		$display_lang     = (int) $field->parameters->get( 'display_lang', 1 ) ;
		$display_size			= (int) $field->parameters->get( 'display_size', 0 ) ;
		$display_hits     = (int) $field->parameters->get( 'display_hits', 0 ) ;
		$display_descr		= (int) $field->parameters->get( 'display_descr', 1 ) ;

		// Force icons in items manager
		$display_lang  = $display_lang && static::$isItemsManager ?  0 : $display_lang;
		$display_size  = $display_size && static::$isItemsManager ?  0 : $display_size;
		$display_hits  = $display_hits && static::$isItemsManager ?  0 : $display_hits;
		$display_descr = $display_descr && static::$isItemsManager ?  0 : $display_descr;

		// Custom (HTML) file properties, not displayed in backend items manager
		$custom_props = static::$isItemsManager ? array() : self::getCustomProps($field);

		$add_lang_img = $display_lang == 1 || $display_lang == 3;
		$add_lang_txt = $display_lang == 2 || $display_lang == 3 || static::$isMobile;
		$add_hits_img = $display_hits == 1 || $display_hits == 3;
		$add_hits_txt = $display_hits == 2 || $display_hits == 3 || static::$isMobile;

		// NOTE: link_filename parameter is ignore when using buttons !!!
		$link_filename = $field->parameters->get( 'link_filename', 1 ) ;
		$usebutton     = $field->parameters->get( 'usebutton', 1 ) ;

		$buttonsposition = $field->parameters->get('buttonsposition', 1);
		$use_infoseptxt   = $field->parameters->get( 'use_info_separator', 0 ) ;
		$use_actionseptxt = $field->parameters->get( 'use_action_separator', 0 ) ;
		$infoseptxt   = $use_infoseptxt   ?  ' '.$field->parameters->get( 'info_separator', '' ).' '    :  '<br>';
		$actionseptxt = $use_actionseptxt ?  ' '.$field->parameters->get( 'action_separator', '' ).' '  :  '<br>';

		$allowdownloads = $field->parameters->get( 'allowdownloads', 1 ) ;
		$downloadstext  = $allowdownloads==2 ? $field->parameters->get( 'downloadstext', 'FLEXI_DOWNLOAD' ) : 'FLEXI_DOWNLOAD';
		$downloadstext  = \Joomla\CMS\Language\Text::_($downloadstext);
		$downloadsinfo  = \Joomla\CMS\Language\Text::_('FLEXI_FIELD_FILE_DOWNLOAD_INFO', true);

		$allowview = $field->parameters->get( 'allowview', 0 ) ;
		$viewtext  = $allowview==2 ? $field->parameters->get( 'viewtext', 'FLEXI_FIELD_FILE_VIEW' ) : 'FLEXI_FIELD_FILE_VIEW';
		$viewtext  = \Joomla\CMS\Language\Text::_($viewtext);
		$viewinfo  = \Joomla\CMS\Language\Text::_('FLEXI_FIELD_FILE_VIEW_INFO', true);
		$viewinside= $field->parameters->get( 'viewinside', 1 ) ;

		$mediapath = static::$cparams->get('media_path', 'components/com_flexicontent/medias');
		$docspath  = static::$cparams->get('file_path', 'components/com_flexicontent/uploads');

		$target_dir = $field->parameters->get('target_dir', 0);
		$base_url   = \Joomla\CMS\Uri\Uri::root(true) . '/' . (!$target_dir ? $mediapath : $docspath);
		$base_url   = str_replace(DS, '/', $base_url);

		// JS safe Field name
		$field_name_js = str_replace('-', '_', $field->name);

		static $fc_lib_added = false;
		if ($viewinside==1 && !$fc_lib_added)
		{
			$fc_lib_added = true;

			flexicontent_html::loadFramework('flexi-lib');
			require_once JPATH_SITE . '/components/com_flexicontent/helpers/html/fcuploader.php';
			if (!\Joomla\CMS\HTML\HTMLHelper::getServiceRegistry()->hasService('fcuploader')) {
				\Joomla\CMS\HTML\HTMLHelper::getServiceRegistry()->register('fcuploader', 'JHtmlFcuploader');
			}
		}

		$allowshare = $field->parameters->get( 'allowshare', 0 ) ;
		$sharetext  = $allowshare==2 ? $field->parameters->get( 'sharetext', 'FLEXI_FIELD_FILE_EMAIL_TO_FRIEND' ) : 'FLEXI_FIELD_FILE_EMAIL_TO_FRIEND';
		$sharetext  = \Joomla\CMS\Language\Text::_($sharetext);
		$shareinfo  = \Joomla\CMS\Language\Text::_('FLEXI_FIELD_FILE_EMAIL_TO_FRIEND_INFO', true);

		$allowaddtocart = $field->parameters->get( 'use_downloads_manager', 0);
		$addtocarttext  = $allowaddtocart==2 ? $field->parameters->get( 'addtocarttext', 'FLEXI_FIELD_FILE_ADD_TO_DOWNLOADS_CART' ) : 'FLEXI_FIELD_FILE_ADD_TO_DOWNLOADS_CART';
		$addtocarttext  = \Joomla\CMS\Language\Text::_($addtocarttext);
		$addtocartinfo  = \Joomla\CMS\Language\Text::_('FLEXI_FIELD_FILE_ADD_TO_DOWNLOADS_CART_INFO', true);

		/** BACKEND **/
		$link_filename = static::$isItemsManager ? 1 : $link_filename;
		$usebutton     = static::$isItemsManager ?  0 : $usebutton;
		$allowdownloads = static::$isItemsManager ?  1 : $allowdownloads;
		$allowview = static::$isItemsManager ?  0 : $allowview;
		$allowshare = static::$isItemsManager ?  0 : $allowshare;
		$allowaddtocart = static::$isItemsManager ?  0 : $allowaddtocart;

		$noaccess_display	     = $field->parameters->get( 'noaccess_display', 1 ) ;
		$noaccess_url_unlogged = $field->parameters->get( 'noaccess_url_unlogged', false ) ;
		$noaccess_url_logged   = $field->parameters->get( 'noaccess_url_logged', false ) ;
		$noaccess_msg_unlogged = \Joomla\CMS\Language\Text::_($field->parameters->get( 'noaccess_msg_unlogged', '' ));
		$noaccess_msg_logged   = \Joomla\CMS\Language\Text::_($field->parameters->get( 'noaccess_msg_logged', '' ));
		$noaccess_addvars      = $field->parameters->get( 'noaccess_addvars', 0);

		// Select appropriate messages depending if user is logged on
		if (Factory::getApplication()->getIdentity()->guest)
		{
			if ($noaccess_url_unlogged)
			{
				$noaccess_url = $noaccess_url_unlogged;
			}
			else
			{
				$uri  = \Joomla\CMS\Uri\Uri::getInstance();

				$return   = strtr(base64_encode($uri->toString()), '+/=', '-_,');          // Current URL as return URL (but we will for id / cid)
				$fcreturn = serialize( array('id' => $app->input->getInt('id'), 'cid' => $app->input->getInt('cid')) );  // a special url parameter, used by some SEF code
				$noaccess_url = ComponentHelper::getParams( 'com_flexicontent' )->get('login_page', 'index.php?option=com_users&view=login')
					. '&return='.$return
					. '&fcreturn='.base64_encode($fcreturn);
			}

			$noaccess_msg = $noaccess_msg_unlogged;
		}
		else
		{
			$noaccess_url = $noaccess_url_logged;
			$noaccess_msg = $noaccess_msg_logged;
		}


		// VERIFY downloads manager module is installed and enabled
		static $mod_is_enabled = null;

		if ($mod_is_enabled === null && $allowaddtocart && !Factory::getApplication()->isClient('administrator'))
		{
			$db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
			$mod_is_enabled = $db->setQuery($db->getQuery(true)
				->select('published')
				->from('#__modules')
				->where('module = '.$db->quote('mod_flexidownloads'))
				->where('published = 1')
			)->loadResult();

			if (!$mod_is_enabled)
			{
				Factory::getApplication()->enqueueMessage("FILE FIELD: please disable parameter \"Use Downloads Manager Module\", the module is not install or not published", 'message' );
			}
		}
		$allowaddtocart = $allowaddtocart ? $mod_is_enabled : 0;


		// Downloads manager feature
		if ($allowshare)
		{
			$com_mailto_found = file_exists(JPATH_SITE.DS.'components'.DS.'com_mailto'.DS.'helpers'.DS.'mailto.php');
			if ($com_mailto_found)
			{
				require_once(JPATH_SITE.DS.'components'.DS.'com_mailto'.DS.'helpers'.DS.'mailto.php');
				$status = 'width=700,height=360,menubar=yes,resizable=yes';
			}
		}

		// Get user access level (these are multiple for J2.5)
		$user = Factory::getApplication()->getIdentity();
		$aid_arr = \Joomla\CMS\Access\Access::getAuthorisedViewLevels($user->id);

		$n = 0;

		/**
		 * Get All file information at once (Data maybe cached already)
		 * TODO (maybe) e.g. contentlists should could call this function ONCE for all file fields,
		 * This may be done by adding a new method to fields to prepare multiple fields with a single call
		 */
		$files_data = $this->getFileData( $values, $published=true );   //if ($field->id==NNN) { echo "<pre>"; print_r($files_data); exit; }

		// Legacy layout support, create 'hits_icon' variable
		static $hits_icon = null;

		if ($hits_icon === null && $add_hits_img)
		{
			if ($display_hits==1)
			{
				$_tooltip_title   = '';
				$_tooltip_content = '%s '.\Joomla\CMS\Language\Text::_( 'FLEXI_HITS', true );
				$_attribs = 'class="'.$tooltip_class.' fcicon-hits" title="'.\Joomla\CMS\HTML\HTMLHelper::tooltipText($_tooltip_title, $_tooltip_content, 0, 0).'"';
			}
			else
			{
				$_attribs = ' class="fcicon-hits"';
			}

			//$hits_icon = \Joomla\CMS\HTML\HTMLHelper::image('components/com_flexicontent/assets/images/'.'user.png', \Joomla\CMS\Language\Text::_( 'FLEXI_HITS' ), $_attribs) . ' ';
			$hits_icon = '<span class="icon-eye" ' . $_attribs . '></span>';
		}

		$show_filename = $display_filename > 0 || $prop=='namelist';
		$public_acclevel = 1;
		$empty_file_data = array('filename'=>false, 'filename_original'=>false, 'altname'=>false, 'description'=>false, 'ext'=>false, 'id'=>0);

		// Get layout name
		$viewlayout = $field->parameters->get('viewlayout', '');
		$viewlayout = $viewlayout ? 'value_'.$viewlayout : 'value_InlineBoxes';

		// Create field's viewing HTML, using layout file
		$field->{$prop} = array();
		include(self::getViewPath($field->field_type, $viewlayout));

		if (!empty($fancybox_needed)) flexicontent_html::loadFramework('fancybox');

		// Do not convert the array to string if field is in a group, and do not add: FIELD's opentag, closetag, value separator
		if (!$is_ingroup)
		{
			// values_list_placement: 1:In slider, 0:Inline
			$values_list_placement = (int) $field->parameters->get('values_list_placement', 0);
			$values_list_placement = static::$isItemsManager ? 1 : $values_list_placement;
			$values_slider_title   = $field->parameters->get('values_slider_title', 'FLEXI_FILES');
			$head = $values_list_placement && isset($field->{$prop}[-1]) ? $field->{$prop}[-1] : '';

			if ($values_list_placement)
			{
				unset($field->{$prop}[-1]);
			}

			// Apply values separator
			$field->{$prop} = implode($separatorf, $field->{$prop});

			if ($field->{$prop} !== '')
			{
				// Apply field 's opening / closing texts
				$field->{$prop} = $opentag . $field->{$prop} . $closetag;

				// Add microdata once for all values, if field -- is NOT -- in a field group
				if ($itemprop)
				{
					$field->{$prop} = '<div style="display:inline" itemprop="'.$itemprop.'" >' .$field->{$prop}. '</div>';
				}
			}

			$is_csv_export = $prop == 'csv_export';
			if ($values_list_placement && !$is_csv_export)
			{
				$ff_slider_tagid = 'fcfile_slider_' . $item->id . '_' . $field->id;
				$ff_slider_title = \Joomla\CMS\Language\Text::_($values_slider_title);

				$field->{$prop} = $head .
					\Joomla\CMS\HTML\HTMLHelper::_('bootstrap.startAccordion', $ff_slider_tagid, array('active' => -1)) .
					\Joomla\CMS\HTML\HTMLHelper::_('bootstrap.addSlide', $ff_slider_tagid, $ff_slider_title, $ff_slider_tagid . '_filelist_slide') .
					$field->{$prop} .
					\Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endSlide') .
					\Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endAccordion');
			}
		}
	}



	// ***
	// *** METHODS HANDLING events on field values
	// ***

	// Method to execute a task when an action on a value is performed
	function onFieldValueAction_FC(&$field, $item, $value_order, $config)
	{
		/**
		 * IMPORTANT:
		 * If you add EVENT 'onFieldValueAction_FC' to a SYSTEM plugin use $handled_types = array('file')
		 */
		$handled_types = static::$field_types;

		if (!in_array($field->field_type, $handled_types))
		{
			return;
		}

		/**
		 * Use $field->id, $item, $value_order, $config to decide on making an action
		 * Typical config array data is:

			$config = array(
				'fileid' => $file_id,  // (int)
				'task' => $task,  // (string) 'download', 'download_tree'
				'method' => $method,  // (string) 'view', 'download'
				'coupon_id' => $coupon_id,  // int or null
				'coupon_token' => $coupon_token // string or null
			);
		*/

		//echo '<pre>' . get_class($this) . '::' . __FUNCTION__ . "()\n\n"; print_r($config); echo '</pre>'; die('TEST code reached exiting');

		/**
		 * false is failure, indicates abort further actions
		 * true is success
		 * null is no work done
		 */
		return null;
	}



	// ***
	// *** METHODS HANDLING before & after saving / deleting field events
	// ***

	// Method to handle field's values before they are saved into the DB
	public function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ( !is_array($post) && !strlen($post) && !$use_ingroup ) return;

		// Get configuration
		$app  = Factory::getApplication();
		$is_importcsv = $app->input->get('task', '', 'cmd') == 'importcsv';
		$import_docs_folder = $app->input->get('import_docs_folder', '', 'string');

		$inputmode = (int)$field->parameters->get( 'inputmode', 1 ) ;
		$iform_allowdel = $field->parameters->get('iform_allowdel', 1);

		$iform_title = $inputmode==1 ? 0 : $field->parameters->get('iform_title', 1);
		$iform_desc  = $inputmode==1 ? 0 : $field->parameters->get('iform_desc',  1);
		$iform_lang  = $inputmode==1 ? 0 : $field->parameters->get('iform_lang',  0);
		$iform_access= $inputmode==1 ? 0 : $field->parameters->get('iform_access',0);
		$iform_dir   = $inputmode==1 ? 0 : $field->parameters->get('iform_dir',   0);
		$iform_stamp = $inputmode==1 ? 0 : $field->parameters->get('iform_stamp', 0);
		if (!Factory::getApplication()->getIdentity()->authorise('flexicontent.editfileproperties', 'com_flexicontent'))
		{
			$iform_lang = $iform_access = $iform_stamp = 0;
		}

		$custom_props = array_filter(self::getCustomProps($field, $inputmode), function ($cp) { return $cp['form']; });

		// Execute once
		static $initialized = null;
		static $srcpath_original = '';
		if ( !$initialized )
		{
			$initialized = 1;
			$srcpath_original  = \Joomla\Filesystem\Path::clean( JPATH_SITE .DS. $import_docs_folder .DS );
		}


		// ***
		// *** Reformat the posted data
		// ***

		// Make sure posted data is an array
		$post = !is_array($post) ? array($post) : $post;

		$newpost = array();
		$new = 0;
		foreach ($post as $n => $v)
		{
			if (empty($v)) {
				// skip empty value, but allow empty (null) placeholder value if in fieldgroup
				if ($use_ingroup) $newpost[$new++] = null;
				continue;
			}

			// support for basic CSV import / export
			if ( $is_importcsv )
			{
				if ( !is_numeric($v) )
				{
					// Parse structured export format (see onDisplayFieldValue 'csv_export'):
					// External file: {URL}...{/URL}{Altname}...{/Altname}{Desc}...{/Desc}{Custom1}...{/Custom1}{Custom2}...{/Custom2}{Ext}...{/Ext}
					// Local file:    path/filename{Altname}...{/Altname}{Desc}...{/Desc}{Custom1}...{/Custom1}{Custom2}...{/Custom2}{Ext}...{/Ext}
					// The tags are optional, a bare URL or a bare path/filename is also accepted
					$_altname = preg_match('#\{Altname\}(.*?)\{/Altname\}#s', $v, $_m) ? trim($_m[1]) : '';
					$_desc    = preg_match('#\{Desc\}(.*?)\{/Desc\}#s',       $v, $_m) ? trim($_m[1]) : '';
					$_ext     = preg_match('#\{Ext\}(.*?)\{/Ext\}#s',         $v, $_m) ? trim($_m[1]) : '';
					$_custom  = array();
					foreach (array(1, 2) as $_cn)
					{
						// Custom (HTML) properties, HTML is filtered according to the Text Filters of the current user's groups
						$_cv = preg_match('#\{Custom' . $_cn . '\}(.*?)\{/Custom' . $_cn . '\}#s', $v, $_m) ? trim($_m[1]) : '';
						if ($_cv !== '') $_custom['custom' . $_cn] = \Joomla\CMS\Component\ComponentHelper::filterText($_cv);
					}
					$_path    = preg_match('#^\{URL\}(.*?)\{/URL\}#s', $v, $_m)
						? trim($_m[1])
						: trim(preg_split('#\{(?:Altname|Desc|Ext|Custom[12])\}#', $v, 2)[0]);

					// Detect URL value: {URL}...{/URL} structured format or bare http(s):// / ftp:// URL
					if (preg_match('#^\{URL\}#', $v) || preg_match('#^(?:https?|ftp)://#i', $_path))
					{
						$_url = $_path;

						// Create URL-type file record directly (mirrors addurl() without CSRF requirement)
						$_db   = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
						$_user = Factory::getApplication()->getIdentity();
						$_target_dir = $field->parameters->get('target_dir', 1);
						$_secure = (strlen((string) $_target_dir) && (int) $_target_dir != 2) ? (int) $_target_dir : 1;

						$_fo = new stdClass;
						$_fo->filename          = $_url;
						$_fo->filename_original = $_url;
						$_fo->altname           = $_altname ?: basename(parse_url($_url, PHP_URL_PATH));
						$_fo->url               = 1;
						$_fo->secure            = $_secure;
						$_fo->stamp             = 0;
						$_fo->ext               = $_ext ?: strtolower(pathinfo($_url, PATHINFO_EXTENSION));
						$_fo->description       = $_desc;
						foreach ($_custom as $_cname => $_cvalue) $_fo->$_cname = $_cvalue;
						$_fo->language          = '*';
						$_fo->access            = 1;
						$_fo->hits              = 0;
						$_fo->size              = 0;
						$_fo->uploaded          = Factory::getDate('now')->toSql();
						$_fo->uploaded_by       = $_user->get('id');

						// A new row: its required columns (attribs) need a value, or strict mode refuses the INSERT
						flexicontent_db::fitRecordToTable($_fo, '#__flexicontent_files', true);
						$_db->insertObject('#__flexicontent_files', $_fo);
						$v = (int) $_db->insertid();
						$v = $v ?: ($use_ingroup ? null : false);
					}
					else
					{
						$filename = basename($_path);
						$sub_folder = dirname($_path);
						$sub_folder = $sub_folder && $sub_folder!='.' ? DS.$sub_folder : '';

						// Add by calling the filemanager upload() task in interactive mode
						$fman = new FlexicontentControllerFilemanager();
						$fman->runMode = 'interactive';

						$Fobj = new stdClass();
						$Fobj->return_url     = null;
						$Fobj->file_dir_path  = DS. $import_docs_folder . $sub_folder;
						$Fobj->file_filter_re = '^' . preg_quote($filename, '/') . '$';  // Whole name, e.g. report.pdf must not match 2025-report.pdf
						$Fobj->secure = (int) $field->parameters->get('iform_dir_default', 1);
						$Fobj->stamp  = (int) $field->parameters->get('iform_stamp_default', 1);
						$Fobj->keep   = 1;

						$upload_errs = null;
						$file_ids = $fman->addlocal($Fobj, $upload_errs);

						if (empty($file_ids))
						{
							\Joomla\CMS\Log\Log::add(
								'file import: file "' . $filename . '" not found in folder "' . $import_docs_folder . $sub_folder . '"',
								\Joomla\CMS\Log\Log::WARNING,
								'com_flexicontent.importcsv'
							);
						}

						// Get fist element
						$v = !empty($file_ids) ? reset($file_ids) : ($use_ingroup ? null : false);
						$v = $v ?: ($use_ingroup ? null : false);
						//$_filetitle = key($file_ids);  // This is the cleaned up filename, currently not needed

						// Set file properties given via the structured format, addlocal() sets title to filename and empty description
						if ($v && ($_altname !== '' || $_desc !== '' || $_ext !== '' || $_custom))
						{
							$_fo = new stdClass;
							$_fo->id = (int) $v;
							if ($_altname !== '') $_fo->altname     = $_altname;
							if ($_desc !== '')    $_fo->description = $_desc;
							if ($_ext !== '')     $_fo->ext         = $_ext;
							foreach ($_custom as $_cname => $_cvalue) $_fo->$_cname = $_cvalue;

							Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class)->updateObject('#__flexicontent_files', $_fo, 'id');
						}
					}
				}
			}

			// we were given a file ID
			elseif (!is_array($v))
			{
				$file_id = (int) $v;
				$v = $v ?: ($use_ingroup ? null : false);
			}

			// Using inline property editing
			else
			{
				$file_id = (int) $v['file-id'];

				$err_code = isset($_FILES['custom']['error'][$field->name][$n]['file-data'])
					? $_FILES['custom']['error'][$field->name][$n]['file-data']
					: UPLOAD_ERR_NO_FILE;
				$new_file = $err_code === 0;

				if ( $err_code && $err_code!=UPLOAD_ERR_NO_FILE )
				{
					$err_msg = array(
						UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
						UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
						UPLOAD_ERR_PARTIAL  => 'The uploaded file was only partially uploaded',
						UPLOAD_ERR_NO_FILE  => 'No file was uploaded',
						UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
						UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
						UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
					);
					Factory::getApplication()->enqueueMessage("FILE FIELD: ".$err_msg[$err_code], 'warning' );
					if ($use_ingroup) $newpost[$new++] = null;
					continue;
				}

				// validate data or empty/set default values
				$v['file-del']   = !$iform_allowdel ? 0 : (int) (!empty($v['file-del']) ? 1 : 0);
				$v['file-title'] = !$iform_title  ? '' : flexicontent_html::dataFilter($v['file-title'],  1000,  'STRING', 0);
				$v['file-desc']  = !$iform_desc   ? '' : flexicontent_html::dataFilter($v['file-desc'],   10000, 'STRING', 0);
				$v['file-lang']  = !$iform_lang   ? '' : flexicontent_html::dataFilter($v['file-lang'],   9,     'STRING', 0);
				$v['file-access']= !$iform_access ? '' : flexicontent_html::dataFilter($v['file-access'], 9,     'ACCESSLEVEL', 0);
				$v['stamp']      = !$iform_stamp  ? 1 : ((int) $v['stamp'] ? 1 : 0);
				foreach ($custom_props as $cn => $cp)
				{
					// HTML allowed, filtered according to the Text Filters of Joomla for the current user's groups
					$v['custom' . $cn] = \Joomla\CMS\Component\ComponentHelper::filterText(isset($v['custom' . $cn]) ? (string) $v['custom' . $cn] : '');
				}

				if( $new_file )
				{
					$v['secure']   = !$iform_dir    ? 1 : ((int) $v['secure'] ? 1 : 0);
				}

				$db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

				if (!empty($v['mediafile']))
				{
					$_parts         = explode('#', $v['mediafile']);
					$v['mediafile'] = $_parts[0];
					$v['mediafile'] = str_replace('%20', '__SPACE__', $v['mediafile']);
					$v['mediafile'] = str_replace(' ', '__SPACE__', $v['mediafile']);
					$v['mediafile'] = flexicontent_html::dataFilter($v['mediafile'], 4000, 'PATH', 0);  // Validate JMedia file PATH
					$v['mediafile'] = str_replace('__SPACE__', ' ', $v['mediafile']);
				}

				// Clear matched file_id if filename does not match
				if ($file_id && !empty($v['mediafile'])) {
					$matched_file = $db->setQuery($db->getQuery(true)
						->select('a.*')
						->from('#__flexicontent_files AS a')
						// filename same as en_file
						->where('a.id = ' . (int) $file_id)
						->where('a.filename = ' . $db->quote($v['mediafile']))
					)->loadObject();
					if (!$matched_file) {
						$file_id = 0;
					}
				}

				// Support inline media file
				if (!$file_id && !empty($v['mediafile']))
				{
					// Check if file exists already in file manager
					$existing_file = $db->setQuery($db->getQuery(true)
						->select('a.*')
						->from('#__flexicontent_files AS a')
						// filename same as en_file
						->where('a.filename = ' . $db->quote($v['mediafile']))
					)->loadObject();

					if ($existing_file)
					{
						$file_id = $existing_file->id;
					}
					else {
						// Add file by calling filemanager controller upload() task, which will do the data filtering too
						$fman = new FlexicontentControllerFilemanager();
						$fman->runMode = 'interactive';

						$inp = Factory::getApplication()->input;
						$inp->set('file-link-type', 2);
						$inp->set('file-jmedia-data', $v['mediafile']);
						$app->input->set('return', null);
						$app->input->set('secure', 0);
						$app->input->set('stamp', 0);
						$app->input->set('file-url-title', $v['file-title']);
						$app->input->set('file-url-desc', $v['file-desc']);
						$app->input->set('file-url-lang', $v['file-lang']);
						$app->input->set('file-url-access', $v['file-access']);

						$upload_errs = null;
						$file_id = $fman->addurl(null, $upload_errs);
						$file_id = !empty($file_id) ? $file_id : ($use_ingroup ? null : false);

						if (empty($file_id)) foreach ($upload_errs as $err_type => $upload_err)
						{
							Factory::getApplication()->enqueueMessage(print_r($upload_err, true), $err_type);
						}
					}
				}

				// UPDATE existing file
				if ( !$new_file && $file_id )
				{
					$dbdata = array();

					$dbdata['id'] = $file_id;
					if ($iform_title)  $dbdata['altname'] = $v['file-title'];
					if ($iform_desc)   $dbdata['description'] = $v['file-desc'];
					if ($iform_lang)   $dbdata['language'] = $v['file-lang'];
					if ($iform_access) $dbdata['access'] = $v['file-access'];
					if ($iform_stamp)  $dbdata['stamp']  = $v['stamp'];
					foreach ($custom_props as $cn => $cp) $dbdata['custom' . $cn] = $v['custom' . $cn];
					//if ($iform_dir)  $dbdata['secure'] = $v['secure'];  // !! Do not change folder for existing files

					// Load file data from DB
					require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/tables/flexicontent_files.php';
					$row = new \flexicontent_files($db);
					$row->load( $file_id );
					$_filename = $row->filename_original ? $row->filename_original : $row->filename;
					$dbdata['secure'] = $row->secure ? 1 : 0;  // !! Do not change media/secure -folder- for existing files

					// Security concern, check file is assigned to current item
					$isAssigned = $this->checkFileAssignment($field, $file_id, $item);
					if ( $v['file-del'] )
					{
						/*!$isAssigned
							? Factory::getApplication()->enqueueMessage("FILE FIELD: refusing to delete file: '".$_filename."', that is not assigned to current item", 'warning' );
							: Factory::getApplication()->enqueueMessage("FILE FIELD: refusing to update file properties of a file: '".$_filename."', that is not assigned to current item", 'warning' );*/
					}

					// Delete existing file if so requested
					if ( $v['file-del'] )
					{
						$canDelete = $this->canDeleteFile($field, $file_id, $item);
						if ($isAssigned && $canDelete)
						{
							$fm = new FlexicontentModelFilemanager();
							$fm->delete( array($file_id) );
						}
						if ($use_ingroup) $newpost[$new++] = null;
						continue;  // Skip file since unloading / removal was requested
					}

					// Set the changed data into the object
					foreach ($dbdata as $index => $data) $row->{$index} = $data;

					// Only users that can edit all files, or the uploader of the file (edit own files), may change its properties
					$_user = Factory::getApplication()->getIdentity();
					$canEditFile = $_user->authorise('flexicontent.editfile', 'com_flexicontent')
						|| ($_user->authorise('flexicontent.editownfile', 'com_flexicontent') && (int) $row->uploaded_by === (int) $_user->id);

					// Update DB data of the file
					if ( $canEditFile && (!$row->check() || !$row->store()) )
					{
						Factory::getApplication()->enqueueMessage("FILE FIELD: " . $row->getError(), 'warning');
						if ($use_ingroup) $newpost[$new++] = null;
						continue;
					}

					// Set file id as value of the field
					$v = $file_id;
				}

				//INSERT new file
				elseif( $new_file )
				{
					// new file was uploaded, but also handle previous selected file ...
					if ($file_id)
					{
						// Security concern, check file is assigned to current item
						$isAssigned = $this->checkFileAssignment($field, $file_id, $item);
						if ( !$isAssigned )
						{
							/*$row = \Joomla\CMS\Table\Table::getInstance('flexicontent_files', '');
							$row->load( $file_id );
							$_filename = $row->filename_original ? $row->filename_original : $row->filename;
							Factory::getApplication()->enqueueMessage("FILE FIELD: refusing to delete file: '".$_filename."', that is not assigned to current item", 'warning' );*/
						}

						// Delete previous file if no longer used
						else if ( $this->canDeleteFile($field, $file_id, $item) )
						{
							$fm = new FlexicontentModelFilemanager();
							$fm->delete( array($file_id) );
						}
					}

					// Skip file if unloading / removal was requested
					if ( $v['file-del'] )
					{
						if ($use_ingroup) $newpost[$new++] = null;
						continue;
					}

					// Add file by calling filemanager controller upload() task, which will do the data filtering too
					$fman = new FlexicontentControllerFilemanager();
					$fman->runMode = 'interactive';

					$app->input->set('return', null);
					$app->input->set('secure', $v['secure']);
					$app->input->set('stamp', $v['stamp']);
					$app->input->set('file-title', $v['file-title']);
					$app->input->set('file-desc', $v['file-desc']);
					$app->input->set('file-lang', $v['file-lang']);
					$app->input->set('file-access', $v['file-access']);

					// The dform field name of the <input type="file" ...
					$app->input->set('file-ffname', 'custom');
					$app->input->set('fname_level1', $field->name);
					$app->input->set('fname_level2', $n);
					$app->input->set('fname_level3', 'file-data');

					$v_custom = $v;  // Keep posted values, since $v is replaced by the file id below
					$upload_errs = null;
					$file_id = $fman->upload(null, $upload_errs);
					$v = !empty($file_id) ? $file_id : ($use_ingroup ? null : false);

					// The upload task does not know about this field's custom properties, so store them now
					if (!empty($file_id) && is_numeric($file_id) && $custom_props)
					{
						require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/tables/flexicontent_files.php';
						$crow = new \flexicontent_files($db);
						if ($crow->load((int) $file_id))
						{
							foreach ($custom_props as $cn => $cp) $crow->{'custom' . $cn} = $v_custom['custom' . $cn];
							$crow->store();
						}
					}

					if (empty($file_id)) foreach ($upload_errs as $err_type => $upload_err)
					{
						Factory::getApplication()->enqueueMessage($upload_err, $err_type);
					}
				}

				else {
					// no existing file and no new file uploaded
					$v = $use_ingroup ? null : false;
				}
	    }

	    if (!$use_ingroup)
			{
	    	// NOT inside field group, add it only if not empty reverse the file array, indexing it by file IDs, to add each file only once
				if ( !empty($v) && is_numeric($v) ) $newpost[(int)$v] = $new++;
			}
			else
			{
				// Inside fieldgroup, allow same file multiple times
				$newpost[$new++] = $v===null ? null : (int)$v;  // null means skip value but increment value position
			}
    }

    // IF NOT inside field group, the file array was reversed (indexed by file IDs), so that the same file can be added once
   	$post = !$use_ingroup ? array_flip($newpost) : $newpost;
	}


	// Method to take any actions/cleanups needed after field's values are saved into the DB
	public function onAfterSaveField( &$field, &$post, &$file, &$item ) {
	}


	// Method called just before the item is deleted to remove custom item data related to the field
	public function onBeforeDeleteField(&$field, &$item) {
	}



	// ***
	// *** CATEGORY/SEARCH FILTERING METHODS
	// ***

	// Method to display a search filter for the advanced search view
	public function onAdvSearchDisplayFilter(&$filter, $value = '', $formName = 'searchForm')
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		FlexicontentFields::createFilter($filter, $value, $formName);
	}


	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	public function getFilteredSearch(&$filter, $value, $return_sql = true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		return FlexicontentFields::getFilteredSearch($filter, $value, $return_sql);
	}



	// ***
	// *** SEARCH INDEX METHODS
	// ***

	// Method to create (insert) advanced search index DB records for the field values
	public function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;

		$field->field_isfile = true;
		$field->unserialize = 0;

		if ($post===null) {
			// null indicates that indexer is running, values is set to NULL which means retrieve data from the DB
			$values = null;
			$field->field_rawvalues = 1;
			$field->field_valuesselect = ' file.id AS value_id, file.altname, file.description, file.filename, file.secure, file.custom1, file.custom2';
			$field->field_valuesjoin   = ' JOIN #__flexicontent_files AS file ON file.id = fi.value';
			$field->field_groupby      = null;
		} else {
			$_files_data = $this->getFileData( $post, $published=true, $extra_select =', f.id AS value_id' );
			$values = array();
			if ($_files_data) foreach($_files_data as $_file_id => $_file_data) $values[$_file_id] = (array)$_file_data;
		}

		FlexicontentFields::onIndexAdvSearch($field, $values, $item, $required_properties=array('filename'), $search_properties=array_merge(array('altname', 'description'), array_map(function ($n) { return 'custom' . $n; }, array_keys(self::getCustomProps($field)))), $properties_spacer=' ', $filter_func='strip_tags');
		return true;
	}


	// Method to create basic search index (added as the property field->search)
	public function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->issearch ) return;

		$field->field_isfile = true;
		$field->unserialize = 0;

		if ($post===null) {
			// null indicates that indexer is running, values is set to NULL which means retrieve data from the DB
			$values = null;
			$field->field_rawvalues = 1;
			$field->field_valuesselect = ' file.id AS value_id, file.altname, file.description, file.filename, file.secure, file.custom1, file.custom2';
			$field->field_valuesjoin   = ' JOIN #__flexicontent_files AS file ON file.id = fi.value';
			$field->field_groupby      = null;
		} else {
			$_files_data = $this->getFileData( $post, $published=true, $extra_select =', f.id AS value_id' );
			$values = array();
			if ($_files_data) foreach($_files_data as $_file_id => $_file_data) $values[$_file_id] = (array)$_file_data;
		}

		FlexicontentFields::onIndexSearch($field, $values, $item, $required_properties=array('filename'), $search_properties=array_merge(array('altname', 'description'), array_map(function ($n) { return 'custom' . $n; }, array_keys(self::getCustomProps($field)))), $properties_spacer=' ', $filter_func='strip_tags');
		return true;
	}



	// ***
	// *** VARIOUS HELPER METHODS
	// ***




	/**
	 * Get DB data for the given file IDs
	 */

	/** Settings of the 2 custom (HTML) file properties of this field, see flexicontent_fileeditor::customProps() */
	public static function getCustomProps($field, $inputmode = null)
	{
		require_once JPATH_SITE . '/components/com_flexicontent/classes/helpers/fileeditor.php';
		return flexicontent_fileeditor::customProps($field->parameters, $inputmode);
	}

	public static function getCustomFormValue(array $cp, $n, $file_data, $form_row = null)
	{
		require_once JPATH_SITE . '/components/com_flexicontent/classes/helpers/fileeditor.php';
		return flexicontent_fileeditor::customFormValue($cp, $n, $file_data, $form_row);
	}

	public static function getCustomDisplayValue(array $cp, $n, $file_data)
	{
		require_once JPATH_SITE . '/components/com_flexicontent/classes/helpers/fileeditor.php';
		return flexicontent_fileeditor::customDisplayValue($cp, $n, $file_data);
	}


	function getFileData($fid, $published = 1, $extra_select = '')
	{
		// Find which file data are already cached, and if no new file ids to query, then return cached only data
		static $cached_data = array();

		$return_data = array();
		$new_ids = array();
		$file_ids = (array) $fid;

		foreach ($file_ids as $file_id)
		{
			$f = (int) $file_id;
			if (!isset($cached_data[$f]) && $f)
			{
				$new_ids[] = $f;
			}
		}

		$md_select = '';

		// Get file data not retrieved already
		if (count($new_ids))
		{
			// Only query files that are not already cached
			$db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
			$query = 'SELECT ' . $md_select . ' f.* '. $extra_select //filename, filename_original, altname, description, ext, id'
				. ' FROM #__flexicontent_files AS f'
				. ' WHERE f.id IN ('. implode(',', $new_ids) . ')'
				. ($published ? '  AND published = 1' : '')
			;
			$new_data = $db->setQuery($query)->loadObjectList('id');

			if ($new_data) foreach($new_data as $file_id => $file_data)
			{
				$cached_data[$file_id] = $file_data;
			}
		}

		// Finally get file data in correct order
		foreach($file_ids as $file_id)
		{
			$f = (int) $file_id;
			if (isset($cached_data[$f]) && $f)
			{
				$return_data[$file_id] = $cached_data[$f];
			}
		}

		return !is_array($fid) ? @ $return_data[(int) $fid] : $return_data;
	}


	// ************************************************
	// Returns an array of images that can be deleted
	// e.g. of a specific field, or a specific uploader
	// ************************************************
	function canDeleteFile( &$field, $file_id, &$item )
	{
		// Check file exists in DB
		$db   = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$query = 'SELECT id'
			. ' FROM #__flexicontent_files'
			. ' WHERE id='. $db->Quote($file_id)
			;
		$db->setQuery($query);
		$file_id = $db->loadResult();
		if (!$file_id)  return true;

		$ignored = array($item->id);

		$fm = new FlexicontentModelFilemanager();
		return $fm->candelete( array($file_id), $ignored );
	}


	// *****************************************
	// Check if file is assigned to current item
	// *****************************************
	function checkFileAssignment( &$field, $file_id, &$item )
	{
		// Check file exists in DB
		$db   = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$query = 'SELECT item_id '
			. ' FROM #__flexicontent_fields_item_relations '
			. ' WHERE '
			. '  field_id='. $db->Quote($field->id)
			. '  AND item_id='. $db->Quote($item->id)
			. '  AND value='. $db->Quote($file_id)
			. ' LIMIT 1'
			;
		$db->setQuery($query);
		$db_id = $db->loadResult();
		return (bool)$db_id;
	}


	// *************************************
	// Return an icon according to file type
	// *************************************
	function addIcon( &$file )
	{
		static $icon_exists = array();

		switch ($file->ext)
		{
			// Image
			case 'jpg':
			case 'png':
			case 'gif':
			case 'xcf':
			case 'odg':
			case 'wbmp':
			case 'bmp':
			case 'ico':
			case 'jpeg':
			case 'webp':
				$file->icon = 'components/com_flexicontent/assets/images/mime-icon-16/image.png';
			break;

			// Non-image document
			default:
				if ( !isset($icon_exists[$file->ext]) ) {
					$icon = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'assets'.DS.'images'.DS.'mime-icon-16'.DS.$file->ext.'.png';
					$icon_exists[$file->ext] = file_exists($icon);
				}
				if ( $icon_exists[$file->ext] ) {
					$file->icon = 'components/com_flexicontent/assets/images/mime-icon-16/'.$file->ext.'.png';
				} else {
					$file->icon = 'components/com_flexicontent/assets/images/mime-icon-16/unknown.png';
				}
			break;
		}
		return $file;
	}



	// *******************************
	// HELPER METHODS FOR FILE SHARING
	// *******************************

	/**
	 * Create form for sharing the download link of given file
	 *
	 * @access public
	 * @since 1.0
	 */
	function share_file_form($tpl = null)
	{
		$user = Factory::getApplication()->getIdentity();
		$db   = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$app  = Factory::getApplication();

		$session  = Factory::getApplication()->getSession();
		$document = Factory::getApplication()->getDocument();

		$file_id    = $app->input->get('file_id', 0, 'int');
		$content_id = $app->input->get('content_id', 0, 'int');
		$field_id   = $app->input->get('field_id', 0, 'int');
		$tpl = $app->input->get('tpl', 'default', 'cmd');

		// Check for missing file id
		if (!$file_id)
		{
			jexit( \Joomla\CMS\Language\Text::_('file id is missing') );
		}

		// Check file exists
		$query = ' SELECT * FROM #__flexicontent_files WHERE id='. $file_id;
		$db->setQuery( $query );
		$file = $db->loadObject();

		if (!$file)
		{
			jexit( \Joomla\CMS\Language\Text::_('file id no '.$file_id.', was not found') );
		}

		$data = new stdClass();
		$data->file_id    = $file_id;
		$data->content_id = $content_id;
		$data->field_id   = $field_id;

		// Load with previous data, if it exists
		$mailto		= $app->input->get('mailto', '', 'string');
		$sender		= $app->input->get('sender', '', 'string');
		$from			= $app->input->get('from', '', 'string');
		$subject	= $app->input->get('subject', '', 'string');
		$desc     = $app->input->get('desc', '', 'string');

		if ($user->get('id') > 0)
		{
			$data->sender	= $user->get('name');
			$data->from		= $user->get('email');
		}
		else
		{
			$data->sender	= $sender;
			$data->from		= $from;
		}

		$data->subject = $subject;
		$data->desc    = $desc;
		$data->mailto  = $mailto;

		$document->getWebAssetManager()->registerAndUseStyle('plg_flexicontent_fields_file.flexicontent', 'components/com_flexicontent/assets/css/flexicontent.css', array('version' => FLEXI_VHASH));
		include('file'.DS.'share_form.php');
		$session->set('com_flexicontent.formtime', time());
	}


	/**
	 * Send email with download (file) link, to the given email address
	 *
	 * @access public
	 * @since 1.0
	 */
	function share_file_email()
	{
		// Check for request forgeries
		flexicontent_security::requirePostToken();

		$user = Factory::getApplication()->getIdentity();
		$db   = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$app  = Factory::getApplication();

		$session  = Factory::getApplication()->getSession();
		$document = Factory::getApplication()->getDocument();

		$timeout = $session->get('com_flexicontent.formtime', 0);
		if ($timeout == 0 || time() - $timeout < 2) {
			\Joomla\CMS\Factory::getApplication()->enqueueMessage(\Joomla\CMS\Language\Text:: _ ('FLEXI_FIELD_FILE_EMAIL_NOT_SENT'), 'notice');
			return $this->share_file_form();
		}

		$SiteName	= $app->get('sitename');
		$MailFrom	= $app->get('mailfrom');
		$FromName	= $app->get('fromname');

		$file_id    = $app->input->get('file_id', 0, 'int');
		$content_id = $app->input->get('content_id', 0, 'int');
		$field_id   = $app->input->get('field_id', 0, 'int');
		$tpl = $app->input->get('tpl', 'default', 'cmd');

		// Check for missing file id
		if (!$file_id)
		{
			jexit( \Joomla\CMS\Language\Text::_('file id is missing') );
		}

		// Check file exists
		$query = ' SELECT * FROM #__flexicontent_files WHERE id='. $file_id;
		$db->setQuery( $query );
		$file = $db->loadObject();

		if (!$file)
		{
			jexit( \Joomla\CMS\Language\Text::_('file id no '.$file_id.', was not found') );
		}


		// Create SELECT OR JOIN / AND clauses for checking Access
		$access_clauses['select'] = '';
		$access_clauses['join']   = '';
		$access_clauses['and']    = '';
		$access_clauses = $this->_createFieldItemAccessClause( $get_select_access = false, $include_file = true );


		// Also enforce publication and item/type membership for direct handler calls.
		flexicontent_security::loadItemField($content_id, $field_id, 'file');

		// Get field's configuration
		$q = 'SELECT attribs, name FROM #__flexicontent_fields WHERE id = '.(int) $field_id;
		$db->setQuery($q);
		$fld = $db->loadObject();
		if (!$fld) throw new \RuntimeException('Field not found', 404);
		$field_params = new \Joomla\Registry\Registry($fld->attribs);
		if (!(int) $field_params->get('allowshare', 0))
		{
			throw new \RuntimeException('File sharing is disabled', 403);
		}

		// Get all needed data related to the given file
		$query  = 'SELECT f.id, f.filename, f.altname, f.secure, f.stamp, f.url,'
				.' i.title as item_title, i.introtext as item_introtext, i.fulltext as item_fulltext, u.email as item_owner_email, '

				// Item and Current Category slugs (for URL)
				. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as itemslug,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'

				.' FROM #__flexicontent_fields_item_relations AS rel'
				.' LEFT JOIN #__flexicontent_files AS f ON f.id = rel.value'
				.' LEFT JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id'
				.' LEFT JOIN #__content AS i ON i.id = rel.item_id'
				.' LEFT JOIN #__categories AS c ON c.id = i.catid'
				.' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
				.' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
				.' LEFT JOIN #__users AS u ON u.id = i.created_by'
				. $access_clauses['join']
				.' WHERE rel.item_id = ' . $content_id
				.' AND rel.field_id = ' . $field_id
				.' AND f.id = ' . $file_id
				.' AND f.published= 1'
				. $access_clauses['and']
				;
		$file = $db->setQuery($query)->loadObject();

		if ( empty($file) )
		{
			// this is normally not reachable because the share link should not have been displayed for the user, but it is reachable if e.g. user session has expired
			jexit( \Joomla\CMS\Language\Text::_( 'FLEXI_ALERTNOTAUTH' ). "File data not found OR no access for file #: ". $file_id ." of content #: ". $content_id ." in field #: ".$field_id );
		}

		flexicontent_security::consumeMailQuota('field-mail');
		$session->set('com_flexicontent.formtime', 0);
		$coupon_vars = '';
		if ( $field_params->get('enable_coupons', 0) )
		{
			// Insert new download coupon into the DB, in the case the file is sent to a user with no ACCESS
			$coupon_token = bin2hex(random_bytes(32));  // cryptographically random bearer token
			$query = ' INSERT #__flexicontent_download_coupons '
				. 'SET user_id = ' . (int)$user->id
				. ', file_id = ' . $file_id
				. ', token = ' . $db->Quote($coupon_token)
				. ', hits = 0'
				. ', hits_limit = '. (int)$field_params->get('coupon_hits_limit', 3)
				. ', expire_on = NOW() + INTERVAL '. (int)$field_params->get('coupon_expiration_days', 15).' DAY'
				;
			$db->setQuery( $query );
			$db->execute();
			$coupon_id = $db->insertid();  // get id of newly created coupon
			$coupon_vars = '&conid='.$coupon_id.'&contok='.$coupon_token;
		}

		$uri  = \Joomla\CMS\Uri\Uri::getInstance();
		$base = $uri->toString( array('scheme', 'host', 'port'));
		$vars = '&id='.$file_id.'&cid='.$content_id.'&fid='.$field_id . $coupon_vars;
		$link = $base . \Joomla\CMS\Router\Route::_( 'index.php?option=com_flexicontent&task=download'.$vars, false );

		// Verify that this is a local link
		if (!$link || !\Joomla\CMS\Uri\Uri::isInternal($link))
		{
			//Non-local url...
			\Joomla\CMS\Factory::getApplication()->enqueueMessage(\Joomla\CMS\Language\Text:: _ ('FLEXI_FIELD_FILE_EMAIL_NOT_SENT'), 'notice');
			return $this->share_file_form();
		}

		// An array of email headers we do not want to allow as input
		$headers = array ('Content-Type:',
							'MIME-Version:',
							'Content-Transfer-Encoding:',
							'bcc:',
							'cc:');

		// An array of the input fields to scan for injected headers
		$fields = array(
			'mailto',
			'sender',
			'from',
			'subject',
		);

		/*
		 * Here is the meat and potatoes of the header injection test.  We
		 * iterate over the array of form input and check for header strings.
		 * If we find one, send an unauthorized header and die.
		 */
		foreach ($fields as $field)
		{
			foreach ($headers as $header)
			{
				if (strpos($_POST[$field], $header) !== false)
				{
					throw new \Exception('', 403);
				}
			}
		}

		/*
		 * Free up memory
		 */
		unset ($headers, $fields);

		$email		= $app->input->get('mailto', '', 'string');
		$sender		= $app->input->get('sender', '', 'string');
		$from			= $app->input->get('from', '', 'string');
		$_subject = \Joomla\CMS\Language\Text::sprintf('FLEXI_FIELD_FILE_SENT_BY', $sender);
		$subject  = $app->input->get('subject', $_subject, 'string');
		$desc     = $app->input->get('desc', '', 'string');

		// Check for a valid to address
		$error	= false;
		if (! $email  || ! \Joomla\CMS\Mail\MailHelper::isEmailAddress($email))
		{
			$error	= \Joomla\CMS\Language\Text::sprintf('FLEXI_FIELD_FILE_EMAIL_INVALID', $email);
			\Joomla\CMS\Factory::getApplication()->enqueueMessage($error, 'warning');
		}

		// Check for a valid from address
		if (! $from || ! \Joomla\CMS\Mail\MailHelper::isEmailAddress($from))
		{
			$error	= \Joomla\CMS\Language\Text::sprintf('FLEXI_FIELD_FILE_EMAIL_INVALID', $from);
			\Joomla\CMS\Factory::getApplication()->enqueueMessage($error, 'warning');
		}

		if ($error)
		{
			return $this->share_file_form();
		}

		// Build the message to send
		$body  = \Joomla\CMS\Language\Text::sprintf('FLEXI_FIELD_FILE_EMAIL_MSG', $SiteName, $sender, $from, $link);
		$body	.= "\n\n".\Joomla\CMS\Language\Text::_('FLEXI_FIELD_FILE_EMAIL_SENDER_NOTES').":\n\n".$desc;

		// Clean the email data
		$subject = \Joomla\CMS\Mail\MailHelper::cleanSubject($subject);
		$body    = \Joomla\CMS\Mail\MailHelper::cleanBody($body);
		$sender  = \Joomla\CMS\Mail\MailHelper::cleanAddress($sender);

		$html_mode=false; $cc=null; $bcc=null;
		$attachment=null; $replyto=null; $replytoname=null;

		// Send the email
		$send_result = \Joomla\CMS\Factory::getContainer()->get(\Joomla\CMS\Mail\MailerFactoryInterface::class)->createMailer()->sendMail( $MailFrom, $FromName, $email, $subject, $body, $html_mode, $cc, $bcc, $attachment, $from, $sender );
		if ( $send_result !== true )
		{
			\Joomla\CMS\Factory::getApplication()->enqueueMessage(\Joomla\CMS\Language\Text:: _ ('FLEXI_FIELD_FILE_EMAIL_NOT_SENT'), 'notice');
			return $this->share_file_form();
		}

		$document->getWebAssetManager()->registerAndUseStyle('plg_flexicontent_fields_file.flexicontent', 'components/com_flexicontent/assets/css/flexicontent.css', array('version' => FLEXI_VHASH));
		include('file'.DS.'share_result.php');
	}


	// Private common method to create join + and-where SQL CLAUSEs, for checking access of field - item pair(s), IN FUTURE maybe moved
	function _createFieldItemAccessClause($get_select_access = false, $include_file = false )
	{
		$user  = Factory::getApplication()->getIdentity();
		$select_access = $joinacc = $andacc = '';

		$aid_arr = \Joomla\CMS\Access\Access::getAuthorisedViewLevels($user->id);
		$aid_list = implode(",", $aid_arr);

		// Access Flags for: content item and field
		if ( $get_select_access )
		{
			$select_access = '';
			if ($include_file) $select_access .= ', CASE WHEN'.
				'   f.access IN (0,'.$aid_list.')  THEN 1 ELSE 0 END AS has_file_access';
			$select_access .= ', CASE WHEN'.
				'  fi.access IN (0,'.$aid_list.')  THEN 1 ELSE 0 END AS has_field_access';
			$select_access .= ', CASE WHEN'.
				'  ty.access IN (0,'.$aid_list.') AND '.
				'   c.access IN (0,'.$aid_list.') AND '.
				'   i.access IN (0,'.$aid_list.')'.
				' THEN 1 ELSE 0 END AS has_content_access';
		}

		else
		{
			if ($include_file)
				$andacc .= ' AND  f.access IN (0,'.$aid_list.')';  // AND file access
			$andacc   .= ' AND fi.access IN (0,'.$aid_list.')';  // AND field access
			$andacc .= ''  // AND content access
				.' AND ty.access IN (0,'.$aid_list.')'
				.' AND  c.access IN (0,'.$aid_list.')'
				.' AND  i.access IN (0,'.$aid_list.')';
		}

		$clauses['select'] = $select_access;
		$clauses['join']   = $joinacc;
		$clauses['and']    = $andacc;
		return $clauses;
	}
}

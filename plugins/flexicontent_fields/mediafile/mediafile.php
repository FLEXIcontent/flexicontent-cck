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

class plgFlexicontent_fieldsMediafile extends FCField
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
		$flat_btn_class  = ' fc-flat-btn';
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
		//$file_path        = $media_params->get('file_path', 'images');
		$image_path       = $media_params->get('image_path', 'images');

		$jmedia_topdir    = $field->parameters->get('jmedia_topdir', $image_path);
		$jmedia_subpath   = $field->parameters->get('jmedia_subpath', '');
		$jmedia_filetypes = array('videos', 'audios');

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

		// Width of the media file card in the item edit form
		$form_card_width = (int) $field->parameters->get('form_card_width', 420);
		$form_card_width = $form_card_width > 0 ? $form_card_width : 420;

		// Inline file property editing
		$inputmode = (int)$field->parameters->get( 'inputmode', 1 ) ;  // 1: file selection only,  0: inline file properties editing
		$top_notice = '';//$use_ingroup ? '<div class="alert alert-warning">Field group mode is not implenent in current version, please disable</div>' : '';

		$iform_allowdel = 0;//$field->parameters->get('iform_allowdel', 1);
		$fields_box_placing = (int) $field->parameters->get('fields_box_placing', 1);
		$form_file_preview  = (int) $field->parameters->get('form_file_preview', 2);

		$iform_title = $inputmode==1 ? 0 : $field->parameters->get('iform_title', 1);
		$iform_desc  = $inputmode==1 ? 0 : $field->parameters->get('iform_desc',  1);
		$iform_lang  = $inputmode==1 ? 0 : $field->parameters->get('iform_lang',  0);
		$iform_access= $inputmode==1 ? 0 : $field->parameters->get('iform_access',0);
		$iform_dir   = $inputmode==1 ? 0 : $field->parameters->get('iform_dir',   0);
		$iform_stamp = 0; //$inputmode==1 ? 0 : $field->parameters->get('iform_stamp', 0);
		if (!Factory::getApplication()->getIdentity()->authorise('flexicontent.editfileproperties', 'com_flexicontent'))
		{
			$iform_lang = $iform_access = $iform_stamp = 0;
		}

		$secure_default = 0; //!$iform_dir   ? 0 : (int) $field->parameters->get('iform_dir_default', 1);
		$stamp_default  = 0; //!$iform_stamp ? 0 : (int) $field->parameters->get('iform_stamp_default', 1);

		$mediapath   = $cparams->get('media_path', 'components/com_flexicontent/medias');
		$docspath    = $cparams->get('file_path', 'components/com_flexicontent/uploads');
		$imagesExt   = array('jpg', 'png', 'gif', 'xcf', 'odg', 'wbmp', 'bmp', 'ico', 'jpeg', 'webp');

		$target_dir = $field->parameters->get('target_dir', 0);
		$base_url   = \Joomla\CMS\Uri\Uri::root(true) . '/' . (!$target_dir ? $mediapath : $docspath);
		$base_url   = str_replace(DS, '/', $base_url);

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
					if (!isset($v['secure'])) $v['secure'] = 0; //!$iform_dir   ? 0 : (int) $field->parameters->get('iform_dir_default', 0);
					if (!isset($v['stamp']))  $v['stamp']  = 0; //!$iform_stamp ? 0 : (int) $field->parameters->get('iform_stamp_default', 0);
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
				'url' => '',
				'secure' => 0, //(!$iform_dir  ? 0 : (int) $field->parameters->get('iform_dir_default', 0)),
				'stamp' => 0, //(!$iform_stamp ? 0 : (int) $field->parameters->get('iform_stamp_default', 0)),
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


			function fc_openFileSelection_".$field->id."(element)
			{
				var url = element.dataset.href.replace('__rowno__', element.dataset.rowno || '').replace('__thisid__', element.id);

				fcfield_mediafile.dialog_handle['".$field_name_js."'] = fc_field_dialog_handle_".$field->id." = fc_showDialog(url, 'fc_modal_popup_container', 0, 0, 0, 0, {title: '".\Joomla\CMS\Language\Text::_('FLEXI_SELECT', true)."', paddingW: 10, paddingH: 16});
				return false;
			}
		";
		$css = '';

		// Handle multiple records
		if ($multiple)
		{
			$config = array(
				'kind' => 'mediafile', 'fieldKey' => $field_name_js,
				'previewAlways' => $form_file_preview === 1,
				'defaults' => array('file-access' => '1', 'secure' => (string) $secure_default, 'stamp' => (string) $stamp_default),
			);
			$document->addScript(\Joomla\CMS\Uri\Uri::root(true) . '/components/com_flexicontent/assets/js/media-repeater.js', array('version' => FLEXI_VHASH));
			$js .= $this->buildValueRepeater($field, $elementid, $fieldname, $max_values, $add_ctrl_btns, $config, 'fcfield_media_rows');

			$remove_button = '
				<button type="button" class="' . $btn_item_class . $flat_btn_class . ' fcfield-delvalue ' . $font_icon_class . '"
					title="' . \Joomla\CMS\Language\Text::_( 'FLEXI_REMOVE_VALUE' ) . '"
					onclick="deleteField'.$field->id.'(this); return false;"
				></button>';
			$move2 = '
				<button type="button" class="' . $btn_item_class . $flat_btn_class . ' fcfield-drag-handle ' . $font_icon_class . '" onclick="event.preventDefault(); return false;"
					title="' . \Joomla\CMS\Language\Text::_( 'FLEXI_CLICK_TO_DRAG' ) . '"
				></button>';
			$add_here = '';
			$add_here .= $add_position==2 || $add_position==3 ? '
				<button type="button" class="' . $btn_item_class . $flat_btn_class . ' fcfield-insertvalue fc_before ' . $font_icon_class . '"
					title="' . \Joomla\CMS\Language\Text::_( 'FLEXI_ADD_BEFORE' ) . '"
					onclick="addField'.$field->id.'(null, this.closest(\'ul\'), this.closest(\'li\'), {insert_before: 1}); return false;"
				></button> ' : '';
			$add_here .= $add_position==1 || $add_position==3 ? '
				<button type="button" class="' . $btn_item_class . $flat_btn_class . ' fcfield-insertvalue fc_after ' . $font_icon_class . '"
					title="' . \Joomla\CMS\Language\Text::_( 'FLEXI_ADD_AFTER' ) . '"
					onclick="addField'.$field->id.'(null, this.closest(\'ul\'), this.closest(\'li\'), {insert_before: 0}); return false;"
				></button> ' : '';

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
				fcfield_mediafile.assignFile(value_container_id, file, keep_modal, '".$field_name_js."');
			}

			/* Method used as callbacks of AJAX uploader */

			function fcfield_FileFiltered_".$field->id."(uploader, file)
			{
				fcfield_mediafile.fileFiltered(uploader, file, '".$field_name_js."');
			}
			function fcfield_FileUploaded_".$field->id."(uploader, file, result)
			{
				fcfield_mediafile.fileUploaded(uploader, file, result, '".$field_name_js."');
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

			\Joomla\CMS\Language\Text::script('PLG_FLEXICONTENT_FIELDS_MEDIAFILE_RESPONSE_PARSING_FAILED', true);
			\Joomla\CMS\Language\Text::script('PLG_FLEXICONTENT_FIELDS_MEDIAFILE_FILE_NOT_FOUND', true);
			\Joomla\CMS\Language\Text::script('FLEXI_FIELD_MEDIAFILE_LOADING_PERCENT');
			\Joomla\CMS\Language\Text::script('FLEXI_PLEASE_UPLOAD_A_FILE', true);

			//flexicontent_html::loadFramework('wavesurfer');
			flexicontent_html::loadFramework('flexi-lib');
			require_once JPATH_SITE . '/components/com_flexicontent/helpers/html/fcuploader.php';
			if (!\Joomla\CMS\HTML\HTMLHelper::getServiceRegistry()->hasService('fcuploader')) {
				\Joomla\CMS\HTML\HTMLHelper::getServiceRegistry()->register('fcuploader', 'JHtmlFcuploader');
			}
			$document->getWebAssetManager()->registerAndUseScript('plg_flexicontent_fields_mediafile.wavesurfer', 'components/com_flexicontent/librairies/wavesurfer/wavesurfer.min.js', array('version' => '7.12.12'));
			$document->getWebAssetManager()->useStyle('fontawesome');
			flexicontent_html::loadFramework('media-field-controls');
			$document->getWebAssetManager()->registerAndUseScript('plg_flexicontent_fields_mediafile.form', 'plugins/flexicontent_fields/mediafile/js/form.js', array('version' => filemtime(JPATH_ROOT . '/plugins/flexicontent_fields/mediafile/js/form.js')));

			$js .= "
			FlexiForm.ready(function()
			{
				audio_spectrum_conf['" . $field_name_js . "'] = [];
				audio_spectrum_conf['" . $field_name_js . "']['waveColor'] = '" . $field->parameters->get( 'ws_wave_color', '#619fc7' ) . "';
				audio_spectrum_conf['" . $field_name_js . "']['progressColor'] = '" .$field->parameters->get( 'ws_progress_color', '#d0d0d0' ) . "';
				audio_spectrum_conf['" . $field_name_js . "']['cursorColor'] = '" .  $field->parameters->get( 'ws_cursor_color', '#619fc7' ) . "';
				audio_spectrum_conf['" . $field_name_js . "']['cursorWidth'] = '" . (int) $field->parameters->get( 'ws_cursor_width', 2 ) . "';

				new fc_Waveform_LazyLoad(
					document.getElementsByTagName('body')[0], {
						rootMargin: '0px 0px',
						threshold: 0.25
					}
				);
			});
			";
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
					var editor = window.fcfield_mediafile && box?.querySelector(\'[data-fc-media-key][data-fc-media-type="mediafile"]\') ? fcfield_mediafile : window.fcfield_file || fcfield_mediafile;
					editor.selectMediaFile(id, value, '.json_encode(rtrim(Uri::root(), '/') . '/').');
				}
			');
		}

		foreach($field->html as $n => &$_html)
		{
			$uploader_html = $use_inline_uploaders ? $uploader_html_arr[$n] : false;
			$elementid_n   = $elementid.'_'.$n;

			// Icon-only edit button, in the toolbar below the preview
			$edit_btn = !($iform_title || $iform_desc || $iform_lang || $iform_access || $iform_dir || $iform_stamp) ? '' : '<button type="button" aria-expanded="false" class="' . $btn_item_class . $flat_btn_class . ' fc-media-edit-btn" data-for="' . $elementid_n . '" title="' . \Joomla\CMS\Language\Text::_( 'FLEXI_EDIT_OPTIONS' ) . '" onclick="fcfield_mediafile.toggleFields(\'' . $elementid_n . '\');">' . flexicontent_html::mediaActionLabel('edit') . '</button>';

			$bottom_toolbar = '
				<div class="' . $btn_group_class . ' fc-xpended-btns fc-image-bottom-toolbar">
					<div class="btn-group fc-image-btn-group" role="group">
						' . ($uploader_html ?
							$uploader_html->toggleBtn .
							($use_myfiles > 0 ? $uploader_html->myFilesBtn : '') .
							$uploader_html->mediaUrlBtn
						: '') . '
						' . $edit_btn . ($uploader_html ? ($uploader_html->clearBtn ?? '') : '') . '
					</div>
				</div>';

			$_html = '
				' . ($multiple && !$add_ctrl_btns ? '<div class="fcclear"></div>' : '') . '
				<div class="card fc-video-card fc-media-card" style="width: '.$form_card_width.'px; max-width: 100%;">
				<div class="fc-video-card-body">
				' . (!$add_ctrl_btns ? '' : '
				<div class="' . $btn_group_class . ' fc-xpended-btns fc-image-top-toolbar">
					' . $move2 . '
					' . (!$add_position ? '' : $add_here) . '
					' . $remove_button . '
				</div>
				') . '
				<div class="fc-field-props-box fc-upload-props-box">
					<div class="fc-field-value-properties-box">
						' . $_html . '
						' . $bottom_toolbar . '
					</div>
				</div>
				</div><!-- EOF .fc-video-card-body -->
				</div><!-- EOF .card -->
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
					<button type="button" class="fcfield-addvalue ' . $font_icon_class . ' ' . $btn_item_class . '" onclick="addField'.$field->id.'(this.closest(\'.fc-xpended-btns\'));" title="'.\Joomla\CMS\Language\Text::_( 'FLEXI_ADD_TO_BOTTOM' ).'">
						'.\Joomla\CMS\Language\Text::_( 'FLEXI_ADD_VALUE' ).'
					</button>
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
		$actionseptxt = $use_actionseptxt ?  ' '.$field->parameters->get( 'action_separator', '' ).' '  :  '';

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

		// GLightbox is a vanilla JS lightbox: the "View inside" modal no longer needs jQuery
		static $fc_glightbox_added = false;
		if ($viewinside==1 && !$fc_glightbox_added)
		{
			$fc_glightbox_added = true;

			flexicontent_html::loadFramework('glightbox');

			$wam = \Joomla\CMS\Factory::getApplication()->getDocument()->getWebAssetManager();
			if (!$wam->assetExists('script', 'fcfile_glightbox'))
			{
				$wam->registerScript('fcfile_glightbox', 'components/com_flexicontent/assets/js/fcfile_glightbox.js', array('version' => FLEXI_VHASH));
			}
			$wam->useScript('fcfile_glightbox');
		}

		// Add needed JS/CSS
		static $js_added = null;
		if ( $js_added === null )
		{
			$js_added = true;
			$document = Factory::getApplication()->getDocument();

			\Joomla\CMS\Language\Text::script('PLG_FLEXICONTENT_FIELDS_MEDIAFILE_RESPONSE_PARSING_FAILED', false);
			\Joomla\CMS\Language\Text::script('PLG_FLEXICONTENT_FIELDS_MEDIAFILE_FILE_NOT_FOUND', false);
			\Joomla\CMS\Language\Text::script('FLEXI_FIELD_MEDIAFILE_LOADING_PERCENT');
			\Joomla\CMS\Language\Text::script('FLEXI_FIELD_MEDIAFILE_PREVIEW_MISSING');
			\Joomla\CMS\Language\Text::script('FLEXI_FIELD_MEDIAFILE_PLAYBACK_LOAD_FAILED');

			//flexicontent_html::loadFramework('wavesurfer');
			flexicontent_html::loadFramework('flexi-lib');
			require_once JPATH_SITE . '/components/com_flexicontent/helpers/html/fcuploader.php';
			if (!\Joomla\CMS\HTML\HTMLHelper::getServiceRegistry()->hasService('fcuploader')) {
				\Joomla\CMS\HTML\HTMLHelper::getServiceRegistry()->register('fcuploader', 'JHtmlFcuploader');
			}
			$document->getWebAssetManager()->registerAndUseScript('plg_flexicontent_fields_mediafile.wavesurfer', 'components/com_flexicontent/librairies/wavesurfer/wavesurfer.min.js', array('version' => '7.12.12'));
			$document->getWebAssetManager()->registerAndUseScript('plg_flexicontent_fields_mediafile.view', 'plugins/flexicontent_fields/mediafile/js/view.js', array('version' => filemtime(JPATH_ROOT . '/plugins/flexicontent_fields/mediafile/js/view.js')));
			//$document->addScript(\Joomla\CMS\Uri\Uri::root(true) . '/components/com_flexicontent/assets/js/pako.min.js', array('version' => FLEXI_VHASH));
			//$document->addScript(\Joomla\CMS\Uri\Uri::root(true) . '/components/com_flexicontent/assets/js/pako_deflate.min.js', array('version' => FLEXI_VHASH));
			//$document->addScript(\Joomla\CMS\Uri\Uri::root(true) . '/components/com_flexicontent/assets/js/pako_inflate.min.js', array('version' => FLEXI_VHASH));

			$document->getWebAssetManager()->addInlineScript("
			FlexiForm.ready(function()
			{
				audio_spectrum_conf['" . $field_name_js . "'] = [];
				audio_spectrum_conf['" . $field_name_js . "']['waveColor'] = '" . $field->parameters->get( 'ws_wave_color', '#619fc7' ) . "';
				audio_spectrum_conf['" . $field_name_js . "']['progressColor'] = '" .$field->parameters->get( 'ws_progress_color', '#d0d0d0' ) . "';
				audio_spectrum_conf['" . $field_name_js . "']['cursorColor'] = '" .  $field->parameters->get( 'ws_cursor_color', '#619fc7' ) . "';
				audio_spectrum_conf['" . $field_name_js . "']['cursorWidth'] = '" . (int) $field->parameters->get( 'ws_cursor_width', 2 ) . "';

				new fc_Waveform_LazyLoad(
					document.getElementsByTagName('body')[0], {
						rootMargin: '0px 0px',
						threshold: 0.25
					});
			});
			");
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
		if (!empty($glightbox_needed))
		{
			flexicontent_html::loadFramework('glightbox');
			$wam = \Joomla\CMS\Factory::getApplication()->getDocument()->getWebAssetManager();
			if (!$wam->assetExists('script', 'fcfile_glightbox'))
			{
				$wam->registerScript('fcfile_glightbox', 'components/com_flexicontent/assets/js/fcfile_glightbox.js', array('version' => FLEXI_VHASH));
			}
			$wam->useScript('fcfile_glightbox');
		}

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
					$filename = basename($v);
					$sub_folder = dirname($v);
					$sub_folder = $sub_folder && $sub_folder!='.' ? DS.$sub_folder : '';

					// Add by calling the filemanager upload() task in interactive mode
					$fman = new FlexicontentControllerFilemanager();
					$fman->runMode = 'interactive';

					$Fobj = new stdClass();
					$Fobj->return_url     = null;
					$Fobj->file_dir_path  = DS. $import_docs_folder . $sub_folder;
					$Fobj->file_filter_re = '^' . preg_quote($filename, '/') . '$';  // Whole name, e.g. report.pdf must not match 2025-report.pdf
					$Fobj->secure = 0; //(int) $field->parameters->get('iform_dir_default', 0);
					$Fobj->stamp  = 0; //(int) $field->parameters->get('iform_stamp_default', 0);
					$Fobj->keep   = 1;

					$upload_errs = null;
					$file_ids = $fman->addlocal($Fobj, $upload_errs);

					if (empty($file_ids))
					{
						\Joomla\CMS\Log\Log::add(
							'mediafile import: file "' . $filename . '" not found in folder "' . $import_docs_folder . $sub_folder . '"',
							\Joomla\CMS\Log\Log::WARNING,
							'com_flexicontent.importcsv'
						);
					}

					// Get fist element
					$v = !empty($file_ids) ? reset($file_ids) : ($use_ingroup ? null : false);
					$v = $v ?: ($use_ingroup ? null : false);
					//$_filetitle = key($file_ids);  // This is the cleaned up filename, currently not needed

					// Run media processing for newly added file (skipped by addlocal)
					if (!empty($v) && is_numeric($v))
					{
						try
						{
							$_file_id = (int) $v;
							$_db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
							$_fileObj = $_db->setQuery('SELECT * FROM #__flexicontent_files WHERE id = ' . $_file_id)->loadObject();

							if ($_fileObj)
							{
								$_destpath = $_fileObj->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;
								$_fileObj->full_path = $_destpath . DS . $_fileObj->filename;

								$_fmodel = new FlexicontentModelFilemanager();

								$_res = $_fmodel->createMediaData($field, $_fileObj);

								if ($_res && !empty($_fileObj->mediaData))
								{
									$_fmodel->createAudioPreview($field, $_fileObj);
								}

								// Schedule FTP transfer if field uses remote storage
								$_estorage_mode = $field->parameters->get('estorage_mode', '0');
								if ($_estorage_mode === 'FTP' && !empty($field->id))
								{
									$_db->setQuery('UPDATE #__flexicontent_files SET estorage_fieldid = ' . (int) $field->id . ' WHERE id = ' . $_file_id)->execute();
								}
							}
						}
						catch (\Throwable $_e)
						{
							Factory::getApplication()->enqueueMessage('mediafile import post-processing error (file id ' . (int) $v . '): ' . $_e->getMessage() . ' in ' . $_e->getFile() . ' on line ' . $_e->getLine(), 'warning');
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
				$v['stamp']      = 0;  //!$iform_stamp  ? 0 : ((int) $v['stamp'] ? 1 : 0);

				if( $new_file )
				{
					$v['secure']   = 0; //!$iform_dir    ? 0 : ((int) $v['secure'] ? 1 : 0);
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

					$upload_errs = null;
					$file_id = $fman->upload(null, $upload_errs);
					$v = !empty($file_id) ? $file_id : ($use_ingroup ? null : false);

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


	// Method to display a category filter for the category view
	public function onDisplayFilter(&$filter, $value = '', $formName = 'adminForm', $isSearchView = 0)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		// Available properties
		/*
		$mediadata = array('state', 'media_type', 'media_format', 'codec_type', 'codec_name', 'codec_long_name',
			'resolution', 'fps', 'bit_rate', 'bits_per_sample', 'sample_rate', 'duration',
			'channels', 'channel_layout', 'checked_out', 'checked_out_time');
			*/
		$mediadata = array('media_format', 'sample_rate', 'duration');
		$media_property_filters = $filter->parameters->get('media_property_filters', $mediadata);
    $media_property_filters = FLEXIUtilities::paramToArray($media_property_filters, "/[\s]*,[\s]*/", false, true);

		$html = array();
		$label = $filter->label;

		foreach($media_property_filters as $prop_name)
		{
			// WARNING: we can not use column alias in from, join, where, group by, can use in having (some DB e.g. mysql) and in order-by
			// partial SQL clauses
			$filter->filter_valuesselect = ' md.' . $prop_name . ' AS value, md.' . $prop_name . ' AS text';
			$filter->filter_valuesjoin   = ' JOIN #__flexicontent_files AS f ON f.id = fi.value '
				. ' JOIN #__flexicontent_mediadatas AS md ON f.id = md.file_id';
			//$filter->filter_valueswhere  = ' AND f.id NOT NULL)';
			// full SQL clauses
			$filter->filter_groupby = ' GROUP BY md.' . $prop_name;
			$filter->filter_having  = null;   // this indicates to use default, space is use empty
			//$filter->filter_orderby = ' ORDER by text';   // default will order by value and not by label

			$prop_value = isset($value[$prop_name]) ? $value[$prop_name] : null;  // TODO GET FROM URI
			//echo 'filter_id: ' . $filter->id . '<br>';
			//echo '<pre>'; print_r($prop_value); echo '</pre>';


			unset($filter->html);
			unset($filter->filt_prop_name);
			$filter->label = $prop_name;
			$filter->filt_prop_name = $prop_name;

			// For duration use text range
			$display_filter_as = $filter->parameters->set('display_filter_as', 0);
			if ($prop_name === 'duration')
			{
				$filter->parameters->set('display_filter_as', 3);
			}

			FlexicontentFields::createFilter($filter, $prop_value, $formName);
			$html[$prop_name] = $filter->html;

			// Restore previous value of filter
			$filter->parameters->set('display_filter_as', $display_filter_as);
		}


		unset($filter->filt_prop_name);

		$filter->label = $label;
		$filter->html = array();
		foreach($media_property_filters as $prop_name)
		{
			$filtername = $prop_name;

			switch($prop_name)
			{
				case 'media_format':  $filtername = \Joomla\CMS\Language\Text::_('FLEXI_FIELD_MEDIADATA_MEDIA_TYPE'); break;
				case 'sample_rate':  $filtername = \Joomla\CMS\Language\Text::_('FLEXI_FIELD_MEDIADATA_SAMPLE_RATE'); break;
				case 'duration':  $filtername = \Joomla\CMS\Language\Text::_('FLEXI_FIELD_MEDIADATA_DURATION_SECONDS'); break;
			}

			$filter->html[] = '
			<div class="fc_filter_media_property_'.$prop_name.'">
				<div class="fc_filter_label fc_label_field_'.$filter->id.'_'.$prop_name.'">'. $filtername . '</div>
				<div class="fc_filter_html">' . $html[$prop_name] . '</div>
			</div>
			';
		}

		$filter->html =  '<div class="fc_filter_media_properties_box" style="float:left; width: 100%;">' . implode('', $filter->html) . '</div>';
	}


	// Method to display a search filter for the advanced search view
	public function onAdvSearchDisplayFilter(&$filter, $value = '', $formName = 'searchForm')
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$mediadata = array('media_format', 'sample_rate', 'duration');
		$media_property_filters = $filter->parameters->get('media_property_filters', $mediadata);
		$media_property_filters = FLEXIUtilities::paramToArray($media_property_filters, "/[\s]*,[\s]*/", false, true);

		// Only allow known columns, since the name is used inside SQL
		$media_property_filters = array_intersect($media_property_filters, $mediadata);

		$db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$html = array();
		$label = $filter->label;

		// Remember parameters / properties that we will alter
		$param_names = array('display_filter_as_s', 'faceted_filter_s', 'display_label_filter_s');
		$old_params = array();
		foreach ($param_names as $param_name)
		{
			$old_params[$param_name] = $filter->parameters->get($param_name);
		}
		$had_options = property_exists($filter, 'filter_options');
		$old_options = $had_options ? $filter->filter_options : null;

		foreach($media_property_filters as $prop_name)
		{
			$prop_value = is_array($value) && isset($value[$prop_name]) ? $value[$prop_name] : null;

			$is_range = $prop_name === 'duration';
			$filter->parameters->set('display_filter_as_s', $is_range ? 3 : 0);
			$filter->parameters->set('faceted_filter_s', 0);
			$filter->parameters->set('display_label_filter_s', 0);

			// Values are taken directly from the media data table, the advanced search index has file names / descriptions
			unset($filter->filter_options);
			if (!$is_range)
			{
				$query = $db->getQuery(true)
					->select('md.' . $prop_name . ' AS value, md.' . $prop_name . ' AS text')
					->from($db->quoteName('#__flexicontent_mediadatas', 'md'))
					->join('INNER', $db->quoteName('#__flexicontent_files', 'f') . ' ON f.id = md.file_id')
					->join('INNER', $db->quoteName('#__flexicontent_fields_item_relations', 'rel') . ' ON rel.value = f.id AND rel.field_id = ' . (int) $filter->id)
					->where('md.' . $prop_name . ' IS NOT NULL')
					->where('md.' . $prop_name . ' <> ' . $db->quote(''))
					->group('md.' . $prop_name)
					->order('md.' . $prop_name . ' ASC');

				try
				{
					$db->setQuery($query);
					$filter->filter_options = $db->loadObjectList() ?: array();
				}
				catch (\Exception $e)
				{
					$filter->filter_options = array();
				}
			}

			unset($filter->html);
			unset($filter->filt_prop_name);
			$filter->label = $prop_name;
			$filter->filt_prop_name = $prop_name;

			FlexicontentFields::createFilter($filter, $prop_value, $formName);
			$html[$prop_name] = $filter->html;
		}

		// Restore altered parameters / properties
		foreach ($old_params as $param_name => $param_value)
		{
			$filter->parameters->set($param_name, $param_value);
		}
		unset($filter->filter_options);
		if ($had_options) $filter->filter_options = $old_options;

		unset($filter->filt_prop_name);
		$filter->label = $label;

		$out = '';
		foreach($media_property_filters as $prop_name)
		{
			$filtername = $prop_name;

			switch($prop_name)
			{
				case 'media_format': $filtername = \Joomla\CMS\Language\Text::_('FLEXI_FIELD_MEDIADATA_MEDIA_TYPE'); break;
				case 'sample_rate':  $filtername = \Joomla\CMS\Language\Text::_('FLEXI_FIELD_MEDIADATA_SAMPLE_RATE'); break;
				case 'duration':     $filtername = \Joomla\CMS\Language\Text::_('FLEXI_FIELD_MEDIADATA_DURATION_SECONDS'); break;
			}

			$out .= '
			<div class="fc_filter_media_property_'.$prop_name.'">
				<div class="fc_filter_label fc_label_field_'.$filter->id.'_'.$prop_name.'">'. $filtername . '</div>
				<div class="fc_filter_html">' . $html[$prop_name] . '</div>
			</div>
			';
		}

		$filter->html = '<div class="fc_filter_media_properties_box">' . $out . '</div>';
	}


	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for category view
	public function getFiltered(&$filter, $value, $return_sql = true)
	{
		// Available properties
		/*
		$mediadata = array('state', 'media_type', 'media_format', 'codec_type', 'codec_name', 'codec_long_name',
			'resolution', 'fps', 'bit_rate', 'bits_per_sample', 'sample_rate', 'duration',
			'channels', 'channel_layout', 'checked_out', 'checked_out_time');
		*/
		$mediadata = array('media_format', 'sample_rate', 'duration');
		$media_property_filters = $filter->parameters->get('media_property_filters', $mediadata);
    $media_property_filters = FLEXIUtilities::paramToArray($media_property_filters, "/[\s]*,[\s]*/", false, true);

		$results = array();
		$label = $filter->label;

		foreach($media_property_filters as $prop_name)
		{
			$filter->filter_valuesjoin   = ' JOIN #__flexicontent_fields_item_relations as rel ON rel.item_id = c.id AND rel.field_id = ' . (int) $filter->id
				. ' JOIN #__flexicontent_files AS f ON f.id = rel.value '
				. ' JOIN #__flexicontent_mediadatas AS md ON f.id = md.file_id';

			$prop_value = isset($value[$prop_name]) ? $value[$prop_name] : null;  // TODO GET FROM URI
			$is_empty  = ($prop_value && is_array($prop_value)) ? !strlen(trim(implode('', $prop_value))) : !strlen(trim($prop_value));

			//  No posted value, nothing to do
			if ($is_empty) continue;
			//echo 'filter_id: ' . $filter->id . '<br>';
			//echo '<pre>'; print_r($prop_value); echo '</pre>';

			unset($filter->filt_prop_name);
			$filter->label = $prop_name;
			$filter->filt_prop_name = $prop_name;
			$filter->filter_colname = 'md.' . $prop_name;

			// For duration use text range
			$display_filter_as = $filter->parameters->set('display_filter_as', 0);
			if ($prop_name === 'duration')
			{
				$filter->parameters->set('display_filter_as', 3);
			}

			$res = FlexicontentFields::getFiltered($filter, $prop_value, $return_sql);
			if ($res)
			{
				$results[] = $res;
			}

			// Restore previous value of filter
			$filter->parameters->set('display_filter_as', $display_filter_as);
		}

		unset($filter->filt_prop_name);
		$filter->label = $label;

		// This method should not have been called for empty values ... but in case this is called for empty values ...
		// return a non-empty string (prepend a space) to indicate no filtering should be done for this field
		return ' ' . implode(' ', $results);
	}

	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	public function getFilteredSearch(&$filter, $value, $return_sql = true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		// Value is an array per media property, skip properties that have only empty values
		$clean = array();
		if (is_array($value))
		{
			foreach ($value as $prop_name => $prop_value)
			{
				$flat = array();
				if (is_array($prop_value))
				{
					array_walk_recursive($prop_value, function ($v) use (&$flat) { $flat[] = trim((string) $v); });
				}
				else
				{
					$flat[] = trim((string) $prop_value);
				}
				if (!strlen(implode('', $flat))) continue;

				$clean[$prop_name] = $prop_value;
			}
		}

		// Nothing to filter, return a non-empty string to indicate no filtering
		if (!count($clean)) return ' ';

		return $this->getFiltered($filter, $clean, $return_sql);
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
			$field->field_valuesselect = ' file.id AS value_id, file.altname, file.description, file.filename, file.secure';
			$field->field_valuesjoin   = ' JOIN #__flexicontent_files AS file ON file.id = fi.value';
			$field->field_groupby      = null;
		} else {
			$_files_data = $this->getFileData( $post, $published=true, $extra_select =', f.id AS value_id' );
			$values = array();
			if ($_files_data) foreach($_files_data as $_file_id => $_file_data) $values[$_file_id] = (array)$_file_data;
		}

		FlexicontentFields::onIndexAdvSearch($field, $values, $item, $required_properties=array('filename'), $search_properties=array('altname', 'description'), $properties_spacer=' ', $filter_func='strip_tags');
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
			$field->field_valuesselect = ' file.id AS value_id, file.altname, file.description, file.filename, file.secure';
			$field->field_valuesjoin   = ' JOIN #__flexicontent_files AS file ON file.id = fi.value';
			$field->field_groupby      = null;
		} else {
			$_files_data = $this->getFileData( $post, $published=true, $extra_select =', f.id AS value_id' );
			$values = array();
			if ($_files_data) foreach($_files_data as $_file_id => $_file_data) $values[$_file_id] = (array)$_file_data;
		}

		FlexicontentFields::onIndexSearch($field, $values, $item, $required_properties=array('filename'), $search_properties=array('altname', 'description'), $properties_spacer=' ', $filter_func='strip_tags');
		return true;
	}



	// ***
	// *** VARIOUS HELPER METHODS
	// ***




	/**
	 * Get DB data for the given file IDs
	 */

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

		$md_select = 'md.state, md.media_type, md.media_format, md.codec_type, md.codec_name, md.codec_long_name, ' .
			'md.resolution, md.fps, md.bit_rate, md.bits_per_sample, md.sample_rate, md.duration, ' .
			'md.channels, md.channel_layout, md.checked_out, md.checked_out_time, ';

		// Get file data not retrieved already
		if (count($new_ids))
		{
			// Only query files that are not already cached
			$db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
			$query = 'SELECT ' . $md_select . ' f.* '. $extra_select //filename, filename_original, altname, description, ext, id'
				. ' FROM #__flexicontent_files AS f'
				. ' LEFT JOIN #__flexicontent_mediadatas AS md ON f.id = md.file_id'
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

		$document->getWebAssetManager()->registerAndUseStyle('plg_flexicontent_fields_mediafile.flexicontent', 'components/com_flexicontent/assets/css/flexicontent.css', array('version' => FLEXI_VHASH));
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
		flexicontent_security::loadItemField($content_id, $field_id, 'mediafile');

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

		$document->getWebAssetManager()->registerAndUseStyle('plg_flexicontent_fields_mediafile.flexicontent', 'components/com_flexicontent/assets/css/flexicontent.css', array('version' => FLEXI_VHASH));
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

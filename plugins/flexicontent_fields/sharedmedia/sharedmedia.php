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

defined( '_JEXEC' ) or die( 'Restricted access' );
// Lazy class loading (was JLoader::register): loaded only if/when actually used.
spl_autoload_register(static function ($class) {
	$map = array(
		'FCField' => JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php',
	);
	if (isset($map[$class]) && is_file($map[$class])) require_once $map[$class];
}, true, true);

class plgFlexicontent_fieldsSharedmedia extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = null;  // Field's methods allowed to be called via AJAX

	private const PROVIDER_EMBED_URLS = array(
		'youtube' => '//www.youtube.com/embed/',
		'vimeo' => '//player.vimeo.com/video/',
		'dailymotion' => '//www.dailymotion.com/embed/video/',
	);

	private static function normalizeEmbedUrl($value, $allowProtocolRelative = true)
	{
		$url = flexicontent_security::safeUrl($value, $allowProtocolRelative);
		$prefix = $allowProtocolRelative ? '~^(?:https?:)?//~i' : '~^https?://~i';

		return preg_match($prefix, $url) ? $url : '';
	}

	private static function normalizeMediaId($apiType, $value)
	{
		$value = is_string($value) || is_int($value) ? (string) $value : '';
		if ($value === '' || strlen($value) > 1000) return '';

		return isset(self::PROVIDER_EMBED_URLS[$apiType])
			? (preg_match('/^[a-z0-9_-]+$/iD', $value) ? $value : '')
			: self::normalizeEmbedUrl($value, false);
	}

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
		$document = \Joomla\CMS\Factory::getApplication()->getDocument();
		$cparams  = \Joomla\CMS\Component\ComponentHelper::getParams( 'com_flexicontent' );

		$tooltip_class = 'hasTooltip';
		$add_on_class    = $cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
		$input_grp_class = $cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';
		$btn_item_class  = $cparams->get('bootstrap_ver', 2)==2  ?  'btn' : 'btn btn-outline-secondary';
		$btn_group_class = $cparams->get('bootstrap_ver', 2)==2  ?  'btn-group' : 'btn-group';
		$form_font_icons = $cparams->get('form_font_icons', 1);
		$font_icon_class = $form_font_icons ? ' fcfont-icon' : '';
		$font_icon_class .= FLEXI_J40GE ? ' icon icon- ' : '';
		$flat_btn_class  = ' fc-flat-btn';


		/**
		 * Number of values
		 */

		$multiple     = $use_ingroup || (int) $field->parameters->get('allow_multiple', 0);
		$max_values   = $use_ingroup ? 0 : (int) $field->parameters->get('max_values', 0);
		$required     = (int) $field->parameters->get('required', 0);
		$add_position = (int) $field->parameters->get('add_position', 3);

		// Classes for marking field required
		$required_class = $required ? ' required' : '';

		// If we are multi-value and not inside fieldgroup then add the control buttons (move, delete, add before/after)
		$add_ctrl_btns = !$use_ingroup && $multiple;
		$fields_box_placing = (int) $field->parameters->get('fields_box_placing', 1);


		/**
		 * Form field display parameters
		 */

		// Usage information
		$show_usage  = (int) $field->parameters->get( 'show_usage', 0 ) ;
		$field_notes = '';

		// API key and other form configuration
		$debug_to_console = (int) $field->parameters->get('debug_to_console', 0);
		$use_native_apis  = (int) $field->parameters->get('use_native_apis', 0);

		$embedly_key = $field->parameters->get('embedly_key','') ;
		$youtube_key = $field->parameters->get('youtube_key', '');

		// Browser typically disabled autoplay ...
		$autostart   = 0; //(int) $field->parameters->get('autostart', 0);
		$autostart   = $autostart ? 'true' : 'false';

		$force_ssl   = (int) $field->parameters->get('force_ssl', 1);
		$force_ssl   = $force_ssl ? 'true' : 'false';

		$display_api_type_form  = (int) $field->parameters->get('display_api_type_form', 0);
		$display_media_id_form  = (int) $field->parameters->get('display_media_id_form', 0);
		$display_embed_url_form = (int) $field->parameters->get('display_embed_url_form', 0);

		$display_title_form       = (int) $field->parameters->get('display_title_form', 1);
		$display_author_form      = (int) $field->parameters->get('display_author_form', 1);
		$display_duration_form    = (int) $field->parameters->get('display_duration_form', 0);

		$display_description_form = (int) $field->parameters->get('display_description_form', 1);
		$display_edit_size_form   = (int) $field->parameters->get('display_edit_size_form', 1);

		$width  = (int)$field->parameters->get('width', 960);
		$height = (int)$field->parameters->get('height', 540);

		// Width of the video card in the item edit form
		$form_card_width = (int) $field->parameters->get('form_card_width', 250);
		$form_card_width = $form_card_width > 0 ? $form_card_width : 250;


		// Return error message if api keys are missing
		//if( empty($embedly_key) && !$use_native_apis ) {
		//  $api_key_name = \Joomla\CMS\Language\Text::_('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_EMBEDLY_API_KEY');
		//	$api_key_desc = \Joomla\CMS\Language\Text::_('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_EMBEDLY_API_KEY_DESC');
		//	$error_text = \Joomla\CMS\Language\Text::sprintf('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_API_KEY_REQUIRED', $api_key_name) ." <br/> ". $api_key_desc;
		//}
		if (empty($youtube_key) && $use_native_apis)
		{
			$api_key_name = \Joomla\CMS\Language\Text::_('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_YOUTUBE_API_KEY');
			$api_key_desc = \Joomla\CMS\Language\Text::_('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_YOUTUBE_API_KEY_DESC');
			$error_text = \Joomla\CMS\Language\Text::sprintf('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_API_KEY_REQUIRED', $api_key_name) ." <br/> ". $api_key_desc;
		}

		// Initialise property with default value
		if (!$field->value || (count($field->value) === 1 && reset($field->value) === null))
		{
			$field->value = array();
			$field->value[0]['url'] = '';         // Actual media URL (enter by user)
			$field->value[0]['embed_url'] = '';   // Direct embed URL (e.g. via embed.ly)
			$field->value[0]['api_type'] = '';    // Native APIs: embed method (e.g. youtube)
			$field->value[0]['media_id'] = '';     // Native APIs: media ID
			$field->value[0]['title'] = '';
			$field->value[0]['author'] = '';
			$field->value[0]['duration'] = '';
			$field->value[0]['description'] = '';
			$field->value[0]['height'] = '';
			$field->value[0]['width'] = '';
			$field->value[0]['thumb'] = '';
			$field->value[0] = serialize($field->value[0]);
		}

		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		$value_classes .= ' fcfield_sharedmedia_valuebox';
		$value_classes .= $fields_box_placing ? ' floated' : '';

		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;

		// JS safe Field name
		$field_name_js = str_replace('-', '_', $field->name);

		// JS & CSS of current field
		$js = '';
		$css = '';

		// Handle multiple records
		if ($multiple)
		{
			flexicontent_html::loadFramework('native-field-controls');
			$js .= $this->buildValueRepeater($field, $elementid, $fieldname, $max_values, $add_ctrl_btns, array(), 'fcfield_sharedmedia_rows');

			$remove_button = '
				<button type="button" class="' . $btn_item_class . $flat_btn_class . ' fcfield-delvalue ' . $font_icon_class . '"
					title="' . \Joomla\CMS\Language\Text::_('FLEXI_REMOVE_VALUE') . '"
					onclick="deleteField'.$field->id.'(this); return false;"
				></button>';
			$move2         = '
				<button type="button" class="' . $btn_item_class . $flat_btn_class . ' fcfield-drag-handle ' . $font_icon_class . '" onclick="event.preventDefault(); return false;"
					title="' . \Joomla\CMS\Language\Text::_('FLEXI_CLICK_TO_DRAG') . '"
				></button>';
			$add_here = '';
			$add_here .= $add_position==2 || $add_position==3 ? '
				<button type="button" class="' . $btn_item_class . $flat_btn_class . ' fcfield-insertvalue fc_before ' . $font_icon_class . '"
				 aria-label="' . \Joomla\CMS\Language\Text::_('FLEXI_ADD_BEFORE') . '"
				 onclick="addField'.$field->id.'(null, this.closest(\'ul\'), this.closest(\'li\'), {insert_before: 1}); return false;"
				></button> ' : '';
			$add_here .= $add_position==1 || $add_position==3 ? '
				<button type="button" class="' . $btn_item_class . $flat_btn_class . ' fcfield-insertvalue fc_after ' . $font_icon_class . '"
				 aria-label="' . \Joomla\CMS\Language\Text::_('FLEXI_ADD_AFTER') . '"
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

		// Add needed JS/CSS
		static $js_added = null;
		if ( $js_added === null )
		{
			$js_added = true;
			\Joomla\CMS\Language\Text::script('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_RESPONSE_PARSING_FAILED', false);
			\Joomla\CMS\Language\Text::script('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_SERVER_RESPONDED_WITH_ERROR', false);
			\Joomla\CMS\Language\Text::script('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_REASON', false);
			\Joomla\CMS\Language\Text::script('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_PREVIEW', false);
			\Joomla\CMS\Language\Text::script('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_URL_NOT_MEDIA', false);
			flexicontent_html::loadFramework('media-metadata');
			$document->getWebAssetManager()->registerAndUseScript('plg_flexicontent_fields_sharedmedia.form', 'plugins/flexicontent_fields/sharedmedia/js/form.js', array('version' => FLEXI_VHASH));
		}

		// JS CODE to handle fetching media DATA
		$js .= '
		fcfield_sharemedia.debugToConsole["'.$field_name_js.'"] = ' . $debug_to_console . ';
		fcfield_sharemedia.use_native_apis["'.$field_name_js.'"] = ' . $use_native_apis . ';
		fcfield_sharemedia.youtube_key["'.$field_name_js.'"] = "' . $youtube_key . '";
		';

		// TODO more
		//if (!headers_sent()) header("Content-Security-Policy: script-src 'unsafe-inline' 'self' ...;");



		/**
		 * Create field's HTML display for item form
		 */

		$field->html = array();
		$n = 0;

		// These are unused in this field
		$skipped_vals = array();
		$per_val_js   = '';

		foreach ($field->value as $n => $value)
		{
			// Compatibility for non-serialized values (e.g. reload user input after form validation error) or for NULL values in a field group
			if ( !is_array($value) )
			{
				$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
				$value = $array ?: array();
			}

			// Compatibility with deprecated fields
			if (empty($value['api_type'])) $value['api_type'] = isset($value['videotype']) ? $value['videotype'] : (isset($value['audiotype']) ? $value['audiotype'] : '');
			if (empty($value['media_id']))  $value['media_id']  = isset($value['videoid'])   ? $value['videoid']   : (isset($value['audioid'])   ? $value['audioid']   : '');

			$is_empty = (empty($value['api_type']) || empty($value['media_id'])) && empty($value['embed_url']);
			if ( $is_empty && !$use_ingroup && $n) continue;  // If at least one added, skip empty if not in field group

			$fieldname_n = $fieldname.'['.$n.']';
			$elementid_n = $elementid.'_'.$n;

			$value['api_type'] = is_string($value['api_type']) ? $value['api_type'] : '';
			$preview_url = $value['embed_url'] ?? '';
			$media_id = self::normalizeMediaId($value['api_type'], $value['media_id']);
			if ($value['api_type'] !== '' && $value['media_id'] !== '')
			{
				$preview_url = $media_id === '' ? '' : (self::PROVIDER_EMBED_URLS[$value['api_type']] ?? '') . $media_id;
			}
			$value['media_id'] = $media_id;
			$value['embed_url'] = $preview_url = self::normalizeEmbedUrl($preview_url);
			$embed_html = '<iframe class="sharedmedia seamless" src="'.htmlspecialchars($preview_url ?: 'about:blank', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" style="width:100%; height:100%; border:none; margin:0; padding:0; overflow:hidden;" allowFullScreen></iframe>';

			if (!isset($value['url']))         $value['url'] = '';
			if (!isset($value['title']))       $value['title'] = '';
			if (!isset($value['author']))      $value['author'] = '';
			if (empty($value['duration']))     $value['duration'] = '';
			if (!isset($value['height']))      $value['height'] = '';
			if (!isset($value['width']))       $value['width'] = '';
			if (!isset($value['description'])) $value['description'] = '';
			if (!isset($value['thumb']))       $value['thumb'] = '';

			// Force display of configuration's default dimensions
			if ($display_edit_size_form!=1)
			{
				$value['height'] = $height;
				$value['width']  = $width;
			}

			// Icon-only toolbar below the preview: URL / fetch / clear / edit fields
			$url_btn = '<button type="button" class="' . $btn_item_class . $flat_btn_class . ' fc-sm-url-btn" data-for="' . $elementid_n . '" title="' . \Joomla\CMS\Language\Text::_('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_MEDIA_URL') . '" aria-label="' . \Joomla\CMS\Language\Text::_('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_MEDIA_URL') . '" aria-expanded="false" onclick="fcfield_sharemedia.toggleURL(\'' . $elementid_n . '\');">' . flexicontent_html::mediaActionLabel('url') . '</button>';
			$fetch_btn = '<button type="button" class="' . $btn_item_class . $flat_btn_class . ' fc-sm-fetch-btn" data-for="' . $elementid_n . '" title="' . \Joomla\CMS\Language\Text::_('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_FETCH') . '" aria-label="' . \Joomla\CMS\Language\Text::_('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_FETCH') . '" onclick="fcfield_sharemedia.fetchData(\'' . $elementid_n . '\', \'' . $field_name_js . '\');">' . flexicontent_html::mediaActionLabel('fetch') . '</button>';
			$clear_btn = '<button type="button" class="' . $btn_item_class . $flat_btn_class . ' fc-sm-clear-btn" data-for="' . $elementid_n . '" title="' . \Joomla\CMS\Language\Text::_('FLEXI_CLEAR') . '" aria-label="' . \Joomla\CMS\Language\Text::_('FLEXI_CLEAR') . '" onclick="fcfield_sharemedia.clearData(\'' . $elementid_n . '\', \'' . $field_name_js . '\');">' . flexicontent_html::mediaActionLabel('clear') . '</button>';
			$edit_btn = '<button type="button" class="' . $btn_item_class . $flat_btn_class . ' fc-sm-edit-btn" data-for="' . $elementid_n . '" title="' . \Joomla\CMS\Language\Text::_('FLEXI_EDIT_OPTIONS') . '" aria-label="' . \Joomla\CMS\Language\Text::_('FLEXI_EDIT_OPTIONS') . '" aria-expanded="false" onclick="fcfield_sharemedia.toggleFields(\'' . $elementid_n . '\');">' . flexicontent_html::mediaActionLabel('edit') . '</button>';

			$bottom_toolbar = '
				<div class="' . $btn_group_class . ' fc-xpended-btns fc-image-bottom-toolbar">
					<div class="btn-group fc-image-btn-group" role="group">
						' . $url_btn . '
						' . $fetch_btn . '
						' . $edit_btn . '
					</div>
				</div>';



			// Compact property rows, toggled by the edit-fields button
			$sm_props = '';

			$sm_props .= ($display_embed_url_form && !$use_native_apis) ? '
				<div>
					<label class="fc-prop-lbl" for="'.$elementid_n.'_embed_url">' . \Joomla\CMS\Language\Text::_('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_EMBED_URL') . '</label>
					<input type="text" class="fcfield_textval sm_embed_url" readonly="readonly" id="'.$elementid_n.'_embed_url" name="'.$fieldname_n.'[embed_url]" value="'.htmlspecialchars($value['embed_url'], ENT_COMPAT, 'UTF-8').'" />
				</div>' : '
				<input type="hidden" class="sm_embed_url" id="'.$elementid_n.'_embed_url" name="'.$fieldname_n.'[embed_url]" value="'.htmlspecialchars($value['embed_url'], ENT_COMPAT, 'UTF-8').'" />';

			$sm_props .= $display_api_type_form ? '
				<div>
					<label class="fc-prop-lbl" for="'.$elementid_n.'_api_type">' . \Joomla\CMS\Language\Text::_('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_EMBED_METHOD') . '</label>
					<input type="text" class="fcfield_textval sm_api_type" readonly="readonly" id="'.$elementid_n.'_api_type" name="'.$fieldname_n.'[api_type]" value="'.htmlspecialchars($value['api_type'], ENT_COMPAT, 'UTF-8').'" />
				</div>' : '
				<input type="hidden" class="sm_api_type" id="'.$elementid_n.'_api_type" name="'.$fieldname_n.'[api_type]" value="'.htmlspecialchars($value['api_type'], ENT_COMPAT, 'UTF-8').'" />';

			$sm_props .= $display_media_id_form ? '
				<div>
					<label class="fc-prop-lbl" for="'.$elementid_n.'_media_id">' . \Joomla\CMS\Language\Text::_('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_MEDIA_ID') . '</label>
					<input type="text" class="fcfield_textval sm_media_id" readonly="readonly" id="'.$elementid_n.'_media_id" name="'.$fieldname_n.'[media_id]" value="'.htmlspecialchars($value['media_id'], ENT_COMPAT, 'UTF-8').'" />
				</div>' : '
				<input type="hidden" class="sm_media_id" id="'.$elementid_n.'_media_id" name="'.$fieldname_n.'[media_id]" value="'.htmlspecialchars($value['media_id'], ENT_COMPAT, 'UTF-8').'" />';

			$sm_props .= $display_title_form ? '
				<div>
					<label class="fc-prop-lbl" for="'.$elementid_n.'_title">' . \Joomla\CMS\Language\Text::_('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_TITLE') . '</label>
					<input type="text" class="fcfield_textval sm_title" id="'.$elementid_n.'_title" name="'.$fieldname_n.'[title]" value="'.htmlspecialchars($value['title'], ENT_COMPAT, 'UTF-8').'" '.($display_title_form==2 ? 'readonly="readonly"' : '').' />
				</div>' : '
				<input type="hidden" class="sm_title" id="'.$elementid_n.'_title" name="'.$fieldname_n.'[title]" value="'.htmlspecialchars($value['title'], ENT_COMPAT, 'UTF-8').'" />';

			$sm_props .= $display_author_form ? '
				<div>
					<label class="fc-prop-lbl" for="'.$elementid_n.'_author">' . \Joomla\CMS\Language\Text::_('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_AUTHOR') . '</label>
					<input type="text" class="fcfield_textval sm_author" id="'.$elementid_n.'_author" name="'.$fieldname_n.'[author]" value="'.htmlspecialchars($value['author'], ENT_COMPAT, 'UTF-8').'" '.($display_author_form==2 ? 'readonly="readonly"' : '').' />
				</div>' : '
				<input type="hidden" class="sm_author" id="'.$elementid_n.'_author" name="'.$fieldname_n.'[author]" value="'.htmlspecialchars($value['author'], ENT_COMPAT, 'UTF-8').'" />';

			$sm_props .= $display_duration_form ? '
				<div>
					<label class="fc-prop-lbl" for="'.$elementid_n.'_duration">' . \Joomla\CMS\Language\Text::_('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_DURATION') . '</label>
					<input type="text" class="fcfield_textval inlineval sm_duration" id="'.$elementid_n.'_duration" name="'.$fieldname_n.'[duration]" value="'.htmlspecialchars($value['duration'], ENT_COMPAT, 'UTF-8').'" size="10" '.($display_duration_form==2 ? 'readonly="readonly"' : '').' /> ' . \Joomla\CMS\Language\Text::_('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_SECONDS') . '
				</div>' : '
				<input type="hidden" class="sm_duration" id="'.$elementid_n.'_duration" name="'.$fieldname_n.'[duration]" value="'.htmlspecialchars($value['duration'], ENT_COMPAT, 'UTF-8').'" />';

			if ($display_edit_size_form)
			{
				$sm_props .= '
				<div>
					<label class="fc-prop-lbl">' . \Joomla\CMS\Language\Text::_('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_PLAYER_DIMENSIONS') . '</label>
					<div class="fc-sm-dimensions">
						<label class="fc-sm-dim-lbl sm_width-lbl" for="'.$elementid_n.'_width">' . \Joomla\CMS\Language\Text::_('FLEXI_WIDTH') . '</label>
						<input type="text" class="fcfield_textval sm_width inlineval" size="4" id="'.$elementid_n.'_width" name="'.$fieldname_n.'[width]" value="'.htmlspecialchars($value['width'], ENT_COMPAT, 'UTF-8').'" '.($display_edit_size_form==2 ? 'readonly="readonly"' : '').' />
						<span class="fc-sm-dim-sep">x</span>
						<label class="fc-sm-dim-lbl sm_height-lbl" for="'.$elementid_n.'_height">' . \Joomla\CMS\Language\Text::_('FLEXI_HEIGHT') . '</label>
						<input type="text" class="fcfield_textval sm_height inlineval" size="4" id="'.$elementid_n.'_height" name="'.$fieldname_n.'[height]" value="'.htmlspecialchars($value['height'], ENT_COMPAT, 'UTF-8').'" '.($display_edit_size_form==2 ? 'readonly="readonly"' : '').' />
						<span class="fc-sm-dim-sep">' . \Joomla\CMS\Language\Text::_('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_PIXELS') . '</span>
					</div>
				</div>';
			}

			$sm_props .= $display_description_form ? '
				<div>
					<label class="fc-prop-lbl" for="'.$elementid_n.'_description">' . \Joomla\CMS\Language\Text::_('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_DESCRIPTION') . '</label>
					<textarea class="fcfield_textareaval sm_description" id="'.$elementid_n.'_description" name="'.$fieldname_n.'[description]" rows="5" '.($display_description_form==2 ? 'readonly="readonly"' : '').'>' . $value['description'] . '</textarea>
				</div>' : '
				<input type="hidden" class="sm_description" id="'.$elementid_n.'_description" name="'.$fieldname_n.'[description]" value="'.htmlspecialchars($value['description'], ENT_COMPAT, 'UTF-8').'" />';

			$html_field = '
				'.($multiple && !$add_ctrl_btns ? '<div class="fcclear"></div>' : '').'
				<div class="card fc-video-card" style="width: '.$form_card_width.'px; max-width: 100%;">
				<div class="fc-video-card-body">
				'.(!$add_ctrl_btns ? '' : '
				<div class="'.$btn_group_class.' fc-xpended-btns fc-image-top-toolbar">
					'.$move2.'
					'.(!$add_position ? '' : $add_here).'
					'.$remove_button.'
				</div>
				').'
				<div class="fc-field-props-box fc-upload-props-box">
				<div class="fc-field-value-properties-box">

					<div class="fc-video-preview-box">
						<div class="sm_preview" id="'.$elementid_n.'_preview">
							'.$embed_html.'
						</div>
						<input type="hidden" class="sm_thumb" id="'.$elementid_n.'_thumb" name="'.$fieldname_n.'[thumb]" value="'.htmlspecialchars($value['thumb'], ENT_COMPAT, 'UTF-8').'" />
					</div>

					'.$bottom_toolbar.'
					<div><span class="fcfield_message_box" id="fcfield_message_box_'.$elementid_n.'"></span></div>

					<div class="sm_url_props" data-name="'.$elementid_n.'" style="display: none;">
						<div class="fc-form-tbl fcinner fccompact">
							<div>
								<label class="visually-hidden" for="'.$elementid_n.'_url">' . \Joomla\CMS\Language\Text::_('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_MEDIA_URL') . '</label>
								<input type="text" class="fcfield_textval' . $required_class . ' sm_url" inputmode="url" enterkeyhint="go" id="'.$elementid_n.'_url" name="'.$fieldname_n.'[url]" value="'.htmlspecialchars($value['url'], ENT_COMPAT, 'UTF-8').'" placeholder="' . \Joomla\CMS\Language\Text::_('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_MEDIA_URL') . '" />
							</div>

						</div>
					</div>

					<div class="sm_value_props" data-name="'.$elementid_n.'" style="display: none;">
						<div class="fc-form-tbl fcinner fccompact">
							'.$sm_props.'
						</div>
					</div>

				</div><!-- EOF class="fc-field-value-properties-box" -->
				</div>
				</div>
				</div>
				';

			$field->html[$n] = $html_field;
		}

		// Add per value JS
		if ($per_val_js)
		{
			$js .= "
			FlexiForm.ready(function()
			{
				" . $per_val_js . "
			});
			";
		}

		// Add field's CSS / JS
		if ($js)  $document->getWebAssetManager()->addInlineScript($js);
		if ($css) $document->getWebAssetManager()->addInlineStyle($css);


		// Do not convert the array to string if field is in a group
		if ($use_ingroup);

		// Handle multiple records
		elseif ($multiple)
		{
			$field->html = !count($field->html) ? '' :
				'<li class="'.$value_classes.'">'.
					implode('</li><li class="'.$value_classes.'">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			if (!$add_position) $field->html .= '
				<div class="'.$btn_group_class.' fc-xpended-btns">
					<button type="button" class="fcfield-addvalue ' . $font_icon_class . ' fccleared"
						aria-label="' . \Joomla\CMS\Language\Text::_( 'FLEXI_ADD_VALUE' ) . '" title="' . \Joomla\CMS\Language\Text::_( 'FLEXI_ADD_VALUE' ) . '"
						onclick="addField'.$field->id.'(this.closest(\'.fc-xpended-btns\')); return false;"
					>
						' . \Joomla\CMS\Language\Text::_( 'FLEXI_ADD_VALUE' ) . '
					</button>
				</div>';
		}

		// Handle single values
		else
		{
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">
				' . (isset($field->html[-1]) ? $field->html[-1] : '') . $field->html[0] . '
			</div>';
		}

		// Add Error message
		if (!empty($error_text))
		{
			$error_text = '<div class="alert alert-warning fc-small fc-iblock">'.$error_text.'</div>';

			if (!$use_ingroup)
			{
				$field->html = $error_text . $field->html;
			}
			else
			{
				foreach($field->html as & $html) $html = $error_text . $html;
				unset($html);
			}
		}


		if (!$use_ingroup)
		{
			$field->html = ($show_usage && $field_notes
				? ' <div class="alert alert-info fc-small fc-iblock">'.$field_notes.'</div><div class="fcclear"></div>'
				: ''
			) . $field->html;
		}

		if (count($skipped_vals))
		{
			$app->enqueueMessage( \Joomla\CMS\Language\Text::sprintf('FLEXI_FIELD_DATE_EDIT_VALUES_SKIPPED', $field->label, implode(',',$skipped_vals)), 'notice' );
		}
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

			$app       = \Joomla\CMS\Factory::getApplication();
			$document  = \Joomla\CMS\Factory::getApplication()->getDocument();
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
		$multiple    = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;


		// Meta DATA that will be displayed
		$display_title       = (int) $field->parameters->get('display_title', 1);
		$display_author      = (int) $field->parameters->get('display_author', 0);
		$display_duration    = (int) $field->parameters->get('display_duration',0) ;
		$display_description = (int) $field->parameters->get('display_description', 0);
		$privacy_embeed      = (int) $field->parameters->get('privacy_embeed', 0);

		$headinglevel = (int)  $field->parameters->get('headinglevel', 3);
		$width        = (int) $field->parameters->get('width', 960);
		$height       = (int) $field->parameters->get('height', 540);
		$autostart    = (int) $field->parameters->get('autostart', 0);

		$player_position        = (int) $field->parameters->get('player_position', 0);
		$display_edit_size_form = (int) $field->parameters->get('display_edit_size_form', 1);

		$sticky_video           = (int) $field->parameters->get('sticky_video', 0);
		$sticky_video_width     = (int) $field->parameters->get('sticky_video_width', '300');
		$sticky_video_position  = $field->parameters->get('sticky_video_position', 'bottomright');

		/**
		 * Get field values
		 */

		$values = $values ? $values : $field->value;

		$js  = '';
		$css = '';

		// JS safe Field name
		$field_name_js = str_replace('-', '_', $field->name);

		/*$css .= "
		.plyr--paused .plyr__poster {
			z-index: 2;
			filter: blur(1rem);
			position: absolute;
			opacity: 1;
			top: 65px;
			left: 0;
			bottom: 0;
			right: 0;
			display:none;
		}
		";*/

		static $js_added = null;
		if ($js_added === null)
		{
			$js_added = true;
			//$document->addScript('https://www.youtube.com/iframe_api');
			//$document->addScript('https://cdn.plyr.io/3.6.2/plyr.js');
			//$document->addStyleSheet('https://cdn.plyr.io/3.6.2/plyr.css');
			$document->getWebAssetManager()->registerAndUseScript('plg_flexicontent_fields_sharedmedia.view', 'plugins/flexicontent_fields/sharedmedia/js/view.js', array('version' => FLEXI_VHASH));
		}

		if ($js)  $document->getWebAssetManager()->addInlineScript($js);
		if ($css) $document->getWebAssetManager()->addInlineStyle($css);

		$unserialize_vals = true;
		if ($unserialize_vals)
		{
			// (* BECAUSE OF THIS, the value display loop expects unserialized values)
			foreach ($values as &$value)
			{
				// Compatibility for non-serialized values or for NULL values in a field group
				if ( !is_array($value) )
				{
					$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
					$value = $array ?: array();
				}
			}
			unset($value); // Unset this or you are looking for trouble !!!, because it is a reference and reusing it will overwrite the pointed variable !!!
		}


		/**
		 * Get common parameters like: itemprop, value's prefix (pretext), suffix (posttext), separator, value list open/close text (opentag, closetag)
		 * This will replace other field values and item properties, if such are found inside the parameter texts
		 */
		$common_params_array = $this->getCommonParams();
		extract($common_params_array);


		// CSV export: Create a simpler output
		if ($prop === 'csv_export')
		{
			$separatorf = ', ';
			$itemprop = false;
		}


		// Get layout name
		$viewlayout = $field->parameters->get('viewlayout', '');
		$viewlayout = $viewlayout ? 'value_'.$viewlayout : 'value_default';

		// Create field's viewing HTML, using layout file
		$field->{$prop} = array();
		include(self::getViewPath($field->field_type, $viewlayout));

		// Do not convert the array to string if field is in a group, and do not add: FIELD's opentag, closetag, value separator
		if (!$is_ingroup)
		{
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
			elseif ($no_value_msg !== '')
			{
				$field->{$prop} = $no_value_msg;
			}
		}
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
		$app  = \Joomla\CMS\Factory::getApplication();
		$is_importcsv = $app->input->get('task', '', 'cmd') == 'importcsv';
		$display_edit_size_form = $field->parameters->get('display_edit_size_form', 1);


		// ***
		// *** Reformat the posted data
		// ***

		// Make sure posted data is an array
		$post = !is_array($post) ? array($post) : $post;

		$newpost = array();
		$new = 0;
		foreach ($post as $n => $v)
		{
			// Support for serialized user data, e.g. basic CSV import / export. (Safety concern: objects code will abort unserialization!)
			if ( /*$is_importcsv &&*/ !is_array($v) && $v )
			{
				$array = $this->unserialize_array($v, $force_array=false, $force_value=false);
				$v = $array ?: array(
					'url' => $v
				);
			}

			// ***
			// *** Validate data, skipping values that are empty after validation
			// ***

			$url = flexicontent_html::dataFilter($v['url'] ?? '', 4000, 'URL', 0);  // Clean bad text/html

			// Skip empty value, but if in group increment the value position
			if (empty($url))
			{
				if ($use_ingroup) $newpost[$new++] = null;
				continue;
			}

			$newpost[$new] = array();
			$newpost[$new]['url'] = $url;

			// Validate other value properties
			$api_type = $v['api_type'] ?? '';
			$api_type = is_string($api_type) ? flexicontent_html::dataFilter($api_type, 100, 'STRING', 0) : '';
			$newpost[$new]['api_type'] = $api_type;
			$newpost[$new]['media_id'] = self::normalizeMediaId($api_type, $v['media_id'] ?? '');
			$embed_url = $v['embed_url'] ?? '';
			$newpost[$new]['embed_url'] = is_string($embed_url)
				? self::normalizeEmbedUrl(flexicontent_html::dataFilter($embed_url, 1000, 'STRING', 0))
				: '';
			$newpost[$new]['thumb']       = flexicontent_html::dataFilter(@$v['thumb'], 0, 'STRING', 1000);  // 'URL' strips needed characters ?
			$newpost[$new]['title']       = flexicontent_html::dataFilter(@$v['title'], 0, 'STRING', 1000);
			$newpost[$new]['author']      = flexicontent_html::dataFilter(@$v['author'], 0, 'STRING', 1000);
			$newpost[$new]['duration']    = flexicontent_html::dataFilter(@$v['duration'], 0, 'INT', 20);
			$newpost[$new]['description'] = flexicontent_html::dataFilter(@$v['description'], 0, 'STRING', 10000);
			$newpost[$new]['height']      = $display_edit_size_form==1 ? flexicontent_html::dataFilter(@$v['height'], 0, 'INT', 0) : '';
			$newpost[$new]['width']       = $display_edit_size_form==1 ? flexicontent_html::dataFilter(@$v['width'], 0, 'INT', 0)  : '';

			$new++;
		}
		$post = $newpost;

		// Serialize multi-property data before storing them into the DB,
		// null indicates to increment valueorder without adding a value
		foreach($post as $i => $v)
		{
			if ($v!==null) $post[$i] = serialize($v);
		}
	}



	// ***
	// *** SEARCH INDEX METHODS
	// ***

	// Method to create (insert) advanced search index DB records for the field values
	public function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;

		// a. Each of the values of $values array will be added to the advanced search index as searchable text (column value)
		// b. Each of the indexes of $values will be added to the column 'value_id',
		//    and it is meant for fields that we want to be filterable via a drop-down select
		// c. If $values is null then only the column 'value' will be added to the search index after retrieving
		//    the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   contains property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array('url'), $search_properties=array('title','author','description'), $properties_spacer=' ', $filter_func=null);
		return true;
	}


	// Method to create basic search index (added as the property field->search)
	public function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->issearch ) return;

		// a. Each of the values of $values array will be added to the basic search index (one record per item)
		// b. If $values is null then the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   contains property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array('url'), $search_properties=array('title','author','description'), $properties_spacer=' ', $filter_func=null);
		return true;
	}

}

<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_content
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;
\Joomla\CMS\HTML\HTMLHelper::_('bootstrap.tooltip');


/**
 * Fcuploader HTML helper
 *
 * @since  3.2
 */
abstract class JHtmlFcuploader
{
	/**
	 * Get Uploader HTML
	 *
	 * @param   int      $field      The field (can be empty)
	 * @param   string   $u_item_id  The unique item id (can be empty)
	 * @param   int      $up_tag_id  The HTML Tag ID of the uploader container (optional)
	 * @param   int      $n          The row number (optional)
	 * @param   string   $options    Options (optional)
	 *
	 * @return  string       HTML code
	 */
	public static function getUploader($field, $u_item_id, $up_tag_id=null, $n=0, $options=array())
	{
		$up_tag_id = $up_tag_id ?: 'custom_' . str_replace('-', '_', $field->name) . '_uploader_';
		$up_css_class = isset($options['container_class']) ? $options['container_class'] : '';

		// Toggle Uploader button
		isset($options['toggle_btn']) || $options['toggle_btn'] = array();
		$tBtn = $options['toggle_btn'];

		isset($tBtn['class'])   || $tBtn['class']  = 'btn';
		isset($tBtn['text'])    || $tBtn['text']   = '<span class="icon-upload"></span> ' . \Joomla\CMS\Language\Text::_('FLEXI_UPLOAD');
		isset($tBtn['onclick']) || $tBtn['onclick']= '';
		isset($tBtn['action'])  || $tBtn['action'] = 'false';  // 'show', 'hide'

		self::init($field, $u_item_id, $up_tag_id, $options);

		return (object) array(
			'toggleBtn' => '
				<button type="button" class="'.$tBtn['class'].' fc_files_uploader_toggle_btn" data-rowno="'.$n.'"
					aria-label="'.htmlspecialchars(\Joomla\CMS\Language\Text::_('FLEXI_UPLOAD'), ENT_QUOTES, 'UTF-8').'" ' . (!empty($options['autostart_on_select']) ? 'aria-haspopup="dialog"' : 'aria-expanded="false"') . '
					onclick="'.$tBtn['onclick'].'if (window.'.$up_tag_id.') '.$up_tag_id.'.toggleUploader(Number(this.dataset.rowno), '.$tBtn['action'].', this);">
					' . $tBtn['text'] . '
				</button>
			',
			'thumbResizer' => (($resize_cfg = @ $options['resize_cfg']) ? '
				<select id="'.$resize_cfg->slider_name.'-sel" class="fc_uploader_size_select fc_no_js_attach" name="'.$resize_cfg->slider_name.'-sel" style="float: left; display: none;"></select>
				<div id="'.$resize_cfg->slider_name.'_nouislider" class="fc_uploader_size_slider"></div>
				<div class="fc_slider_input_box">
					<input id="'.$resize_cfg->slider_name.'-val" name="'.$resize_cfg->slider_name.'-val" type="text" size="12" value="140" />
				</div>
			' : ''),
			'container' => '
				<div style="display: inline-block; width: 100%; box-sizing: border-box; position: relative;">
					<div class="fc_uploader_status_box">
						<div class="fc_uploader_mssg_box" style="display: none;"></div>
						<input type="text" value="" class="validate-fcuploader fc_hidden_value" data-error-mssg="" data-error-css="" />
					</div>
					<div id="'. $up_tag_id . $n .'" data-tagid-prefix="'. $up_tag_id .'" class="fc_file_uploader '.$up_css_class.' '.$up_tag_id.'" style="display: none;">
						<span class="alert alert-warning">' . htmlspecialchars(\Joomla\CMS\Language\Text::_('FLEXI_UPLOAD_START_FAILED'), ENT_QUOTES, 'UTF-8') . '</span>
					</div>
				</div>
			'
		);
	}


	/**
	 * Initialize an Uploader
	 *
	 * @param   int      $field      The field (can be empty)
	 * @param   string   $u_item_id  The unique item id (can be empty)
	 * @param   int      $up_tagid   The HTML Tag ID of the uploader container
	 * @param   string   $option     Options
	 *
	 * @return  string       HTML code
	 */
	public static function init($field, $u_item_id, $up_tag_id, $options)
	{
		static $initialized = array();

		if (isset($initialized[$up_tag_id]))
		{
			return;
		}

		$initialized[$up_tag_id] = true;

		$defaults = array(
			'action' => \Joomla\CMS\Uri\Uri::base(true) . '/index.php?option=com_flexicontent&task=filemanager.uploads'
				. '&language=' . rawurlencode(\Joomla\CMS\Factory::getApplication()->getLanguage()->getTag())
				. '&view='.($field ? 'fileselement' : 'filemanager')
				. '&fieldid='.($field ? $field->id : ''). '&u_item_id='.$u_item_id
				. '&'.\Joomla\CMS\Session\Session::getFormToken().'=1',
			'upload_maxcount' => 0,
			'autostart_on_select' => false,
			'layout' => 'default',
			'edit_properties' => false,
			'add_size_slider' => false,
			'refresh_on_complete' => true,
			'thumb_size_default' => 150,
			'thumb_size_slider_cfg' => 0,
			'height_spare' => 0,
			'handle_FileFiltered' => 'null',
			'handle_FileUploaded' => 'null'
		);
		foreach($defaults as $i => $v)
		{
			isset($options[$i]) || $options[$i] = $v;
		}

		$uops = self::getUploadConf($field);


		flexicontent_html::loadFramework('flexi-lib');
		flexicontent_html::loadFramework('glightbox');
		$document = \Joomla\CMS\Factory::getApplication()->getDocument();
		$assetPath = \Joomla\CMS\Uri\Uri::root(true) . '/components/com_flexicontent/assets/';
		$version = array('version' => FLEXI_VHASH);
		require_once JPATH_SITE . '/components/com_flexicontent/librairies/uppy/language.php';
		FlexicontentUppyLanguage::register($document);
		$dashboardPath = \Joomla\CMS\Uri\Uri::root(true) . '/components/com_flexicontent/librairies/uppy/dist/dashboard.min';
		$document->addStyleSheet($dashboardPath . '.css', $version);
		$document->addScript($dashboardPath . '.js', $version);
		$document->addStyleSheet($assetPath . 'css/fc-uploader.css', $version);
		$document->addScript($assetPath . 'js/fc-upload-transport.js', $version);
		$document->addScript($assetPath . 'js/fc-uploader.js', $version);

		foreach (array('FLEXI_UPLOAD', 'FLEXI_START_UPLOAD', 'FLEXI_REMOVE_FILE', 'FLEXI_THUMBNAILS',
			'FLEXI_OFFICE_PREVIEW_UNAVAILABLE', 'FLEXI_OFFICE_PREVIEW_LIMIT', 'FLEXI_OFFICE_PREVIEW_SHEETS', 'FLEXI_OFFICE_PREVIEW_SLIDE',
			'FLEXI_UPLOAD_DROP_OR_CHOOSE', 'FLEXI_UPLOAD_ACCEPTED_TYPES', 'FLEXI_PREVIEW', 'JCLOSE',
			'FLEXI_UPLOAD_FILES', 'FLEXI_UPLOAD_MAX_EACH', 'FLEXI_ACCESS', 'FLEXI_LANGUAGE',
			'FLEXI_UPLOADING', 'FLEXI_UPLOAD_FINISHED', 'FLEXI_PLEASE_WAIT', 'FLEXI_FILE_PROPERTIES',
			'FLEXI_UPLOAD_CONNECTION_FAILED', 'FLEXI_UPLOAD_INVALID_RESPONSE', 'FLEXI_UPLOAD_REQUEST_FAILED',
			'FLEXI_UPLOAD_FORBIDDEN', 'FLEXI_UPLOAD_UNFINISHED',
			'FLEXI_UPLOAD_INVALID_URL', 'FLEXI_UPLOAD_UNCONFIRMED',
			'FLEXI_UPLOAD_TYPE_REJECTED', 'FLEXI_UPLOAD_LIMIT_REACHED', 'FLEXI_UPLOAD_ASSIGN_FAILED',
			'JLIB_HTML_MOVE_UP', 'JLIB_HTML_MOVE_DOWN') as $key)
		{
			\Joomla\CMS\Language\Text::script($key, true);
		}

		$configuration = $uops + array(
			'tag_id' => $up_tag_id,
			'action' => $options['action'],
			'upload_maxcount' => (int) $options['upload_maxcount'],
			'autostart_on_select' => (bool) $options['autostart_on_select'],
			'edit_properties' => (bool) $options['edit_properties'],
			'add_size_slider' => (bool) $options['add_size_slider'],
			'refresh_on_complete' => (bool) $options['refresh_on_complete'],
			'thumb_size_default' => (int) $options['thumb_size_default'],
			'height_spare' => (int) $options['height_spare'],
		);
		// Register before field-ready callbacks auto-open the queue; DOM creation stays lazy.
		$js = '
		var ' . $up_tag_id . ' = new FlexiUploader.Uploader(Object.assign(' .
			json_encode($configuration, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ', {
			thumb_size_slider_cfg: ' . $options['thumb_size_slider_cfg'] . ',
			handle_FileFiltered: ' . $options['handle_FileFiltered'] . ',
			handle_FileUploaded: ' . $options['handle_FileUploaded'] . '
		}));
		window.addEventListener("resize", fc_debounce_exec(' . $up_tag_id . '.autoResize, 200, false, ' . $up_tag_id . '));
		';
		\Joomla\CMS\Factory::getApplication()->getDocument()->addScriptDeclaration($js);
	}


	// ***
	// *** Get uploader configuration from component overriding it by field configuration
	// ***
	public static function getUploadConf($field)
	{
		static $uops = array();

		$conf_index = $field
			? $field->id
			: 'component';

		if (isset($uops[$conf_index]))
		{
			return $uops[$conf_index];
		}
		
		$uconf = flexicontent_upload::getUploadParams($field);

		// Try field with upload_maxsize and resize_on_upload parameters
		$u = array();
		$u['extensions'] = flexicontent_upload::getAllowedExtensions($uconf);
		$u['upload_maxsize']   = (int) $uconf->get('upload_maxsize', 10000000);
		$u['resize_on_upload'] = (int) $uconf->get('resize_on_upload', 1);

		if ($u['resize_on_upload'])
		{
			$u['upload_max_w']   = (int) $uconf->get('upload_max_w', 4000);
			$u['upload_max_h']   = (int) $uconf->get('upload_max_h', 3000);
			$u['upload_quality'] = (int) $uconf->get('upload_quality', 95);
			$u['upload_method']  = (int) $uconf->get('upload_method', 0);
		}

		return $uops[$conf_index] = $u;
	}
}

<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_content
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;
JHtml::_('bootstrap.tooltip');


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
		isset($tBtn['text'])    || $tBtn['text']   = '<span class="icon-upload"></span> ' . JText::_('FLEXI_UPLOAD');
		isset($tBtn['onclick']) || $tBtn['onclick']= '';
		isset($tBtn['action'])  || $tBtn['action'] = 'false';  // 'show', 'hide'

		self::init($field, $u_item_id, $up_tag_id, $options);

		return (object) array(
			'toggleBtn' => '
				<span class="'.$tBtn['class'].' fc_files_uploader_toggle_btn" data-rowno="'.$n.'" onclick="'.$tBtn['onclick'].$up_tag_id.'.toggleUploader(jQuery(this).data(\'rowno\'), '.$tBtn['action'].');">
					' . $tBtn['text'] . '
				</span>
			',
			'thumbResizer' => (($resize_cfg = @ $options['resize_cfg']) ? '
				<select id="'.$resize_cfg->slider_name.'-sel" class="fc_uploader_size_select fc_no_js_attach" name="'.$resize_cfg->slider_name.'-sel" style="float: left; display: none;"></select>
				<div id="'.$resize_cfg->slider_name.'_nouislider" class="fc_uploader_size_slider"></div>
				<div class="fc_slider_input_box">
					<input id="'.$resize_cfg->slider_name.'-val" name="'.$resize_cfg->slider_name.'-val" type="text" size="12" value="140" />
				</div>
			' : ''),
			'container' => '
				<div id="'. $up_tag_id . $n .'" data-tagid-prefix="'. $up_tag_id .'" class="fc_file_uploader '.$up_css_class.' '.$up_tag_id.'" style="display:none;">
					<span class="alert alert-warning">File uploader script failed to start</span>
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
			'action' => JUri::base(true) . '/index.php?option=com_flexicontent&task=filemanager.uploads'
				. '&'.JSession::getFormToken().'=1' . '&fieldid='.($field ? $field->id : ''). '&u_item_id='.$u_item_id,
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


		// ***
		// *** Load plupload JS framework
		// ***

		$pluploadlib = JUri::root(true).'/components/com_flexicontent/librairies/plupload/';
		$plupload_mode = 'runtime';  // 'runtime,ui'
		flexicontent_html::loadFramework('plupload', $plupload_mode);
		flexicontent_html::loadFramework('flexi-lib');

		JText::script("FLEXI_FILE_PROPERTIES", true);
		JText::script("FLEXI_APPLYING_DOT", true);
		JFactory::getDocument()->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/plupload-extend.js', FLEXI_VHASH);

		// Add plupload Queue handling functions and initialize a plupload Queue
		$js = '
		var '.$up_tag_id.';
		var '.$up_tag_id.'_options = {
			mode: "'.$plupload_mode.'",
			tag_id: "'.$up_tag_id.'",
			action: "'.$options['action'].'",

			upload_maxsize: "'.$uops['upload_maxsize'].'",
			upload_maxcount: '.$options['upload_maxcount'].',

			view_layout: "'.$options['layout'].'",
			autostart_on_select: '.($options['autostart_on_select'] ? 'true' : 'false').',
			edit_properties: '.($options['edit_properties'] ? 'true' : 'false').',
			add_size_slider: '.($options['add_size_slider'] ? 'true' : 'false').',
			refresh_on_complete: '.($options['refresh_on_complete'] ? 'true' : 'false').',
			thumb_size_default: '.$options['thumb_size_default'].',
			thumb_size_slider_cfg: '.$options['thumb_size_slider_cfg'].',
			height_spare: '.$options['height_spare'].',

			handle_FileFiltered: '.$options['handle_FileFiltered'].',
			handle_FileUploaded: '.$options['handle_FileUploaded'].',

			resize_on_upload: '.($uops['resize_on_upload'] ? 'true' : 'false').',
			'.($uops['resize_on_upload'] ? '
			upload_max_w: '.$uops['upload_max_w'].',
			upload_max_h: '.$uops['upload_max_h'].',
			upload_quality: '.$uops['upload_quality'].',
			upload_crop: '.($uops['upload_method'] ? 'true' : 'false').',
			' : '').'

			flash_swf_url : "'.$pluploadlib.'/js/Moxie.swf",
			silverlight_xap_url : "'.$pluploadlib.'/js/Moxie.xap"
		};

		jQuery(document).ready(function()
		{
			// Create a configuration object, to be used by all uploaders in this group
			var uploader = '.$up_tag_id.' = new fc_plupload('.$up_tag_id.'_options);

			// Register debounced autoresizing of all uploaders in this group
			var uploader_resize = fc_debounce_exec(uploader.autoResize, 200, false, uploader);
			jQuery(window).resize(function()
			{
				uploader_resize();
			});
		});
		';

		JFactory::getDocument()->addScriptDeclaration($js);
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
		
		$uconf = new JRegistry();
		$uconf->merge(JComponentHelper::getParams('com_flexicontent'));

		if (!empty($field))
		{
			$uconf->merge($field->parameters);
		}

		// Try field with upload_maxsize and resize_on_upload parameters
		$u = array();
		$u['upload_maxsize']   = (int) $uconf->get('upload_maxsize', 10000000);
		$u['resize_on_upload'] = (int) $uconf->get('resize_on_upload', 1);

		if ($u['resize_on_upload'])
		{
			$u['upload_max_w']   = (int) $uconf->get('upload_max_w', 4000);
			$u['upload_max_h']   = (int) $uconf->get('upload_max_h', 3000);
			$u['upload_quality'] = (int) $uconf->get('upload_quality', 95);
			$u['upload_method']  = (int) $uconf->get('upload_method', 1);
		}

		return $uops[$conf_index] = $u;
	}
}

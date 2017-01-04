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
 * Fcitems HTML helper
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
	 * @param   string   $option    Options (optional)
	 *
	 * @return  string       HTML code
	 */
	public static function getUploader($field, $u_item_id, $up_tag_id=null, $n=0, $options=array())
	{
		$up_tag_id = $up_tag_id ?: 'custom_' . $field->name . '_uploader_';
		$up_css_class = isset($options['container_class']) ? $options['container_class'] : '';

		self::init($field, $u_item_id, $up_tag_id, $options);

		return (object) array(
			'toggleBtn' => '
				<span class="btn fc_files_uploader_toggle_btn" data-rowno="'.$n.'" onclick="'.$up_tag_id.'.toggleUploader(jQuery(this).data(\'rowno\'));">
					<span class="icon-upload"></span> ' . JText::_('FLEXI_UPLOAD').'
				</span>
			',
			'container' => '
				<div class="clear"></div>
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
			'action' => JURI::base() . 'index.php?option=com_flexicontent&task=filemanager.uploads'
				. '&'.JSession::getFormToken().'=1' . '&fieldid='.($field ? $field->id : ''). '&u_item_id='.$u_item_id,
			'maxcount' => 0,
			'layout' => 'default',
			'edit_properties' => false,
			'refresh_on_upload' => true,
			'height_spare' => 0
		);
		foreach($defaults as $i => $v)  isset($options[$i]) || $options[$i] = $v;

		$uops = self::getUploadConf($field);


		// ***
		// *** Load plupload JS framework
		// ***

		$pluploadlib = JURI::root(true).'/components/com_flexicontent/librairies/plupload/';
		$plupload_mode = 'runtime';  // 'runtime,ui'
		flexicontent_html::loadFramework('plupload', $plupload_mode);
		flexicontent_html::loadFramework('flexi-lib');

		JText::script("FLEXI_FILE_PROPERTIES", true);
		JText::script("FLEXI_APPLYING_DOT", true);
		JFactory::getDocument()->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/plupload-extend.js', FLEXI_VHASH);

		// Add plupload Queue handling functions and initialize a plupload Queue
		$js = '
		var '.$up_tag_id.';
		var '.$up_tag_id.'_options = {
			mode: "'.$plupload_mode.'",
			tag_id: "'.$up_tag_id.'",
			action: "'.$options['action'].'",

			upload_maxsize: "'.$uops['upload_maxsize'].'",
			upload_maxcount: '.$options['maxcount'].',

			view_layout: "'.$options['layout'].'",
			edit_properties: '.($options['edit_properties'] ? 'true' : 'false').',
			refresh_on_upload: '.($options['refresh_on_upload'] ? 'true' : 'false').',
			height_spare: '.$options['height_spare'].',

			handle_select: null,  // TODO implement
			handle_complete: null,  // TODO implement

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
		$conf_index = $field ? $field->id : 'component';

		if (isset($uops[$conf_index])) return $uops[$conf_index];
		
		$uconf = new JRegistry();
		$uconf->merge( JComponentHelper::getParams('com_flexicontent') );
		if (!empty($field))
		{
			$uconf->merge($field->parameters);
		}

		// Try field with upload_maxsize and resize_on_upload parameters
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

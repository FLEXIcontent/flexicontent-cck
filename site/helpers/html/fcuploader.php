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
		$field_id = $field ? $field->id : '';
		$up_tag_id = $up_tag_id ?: 'custom_' . $field->name . '_uploader_';

		self::init($field, $u_item_id, $up_tag_id, $n, $options);

		return (object) array(
			'toggleBtn' => '<span class="btn fc_files_uploader_toggle_btn" data-rowno="'.$n.'" onclick="fc_files_uploader_'.$field_id.'.toggleUploader(jQuery(this).data(\'rowno\'));"><span class="icon-upload"></span>'.JText::_('FLEXI_UPLOAD').'</span>',
			'container' => '<div class="clear"></div><div id="'. $up_tag_id . $n .'" class="fc_file_uploader fc_inline_uploader"></div>'
		);
	}
	
	
	/**
	 * Initialize an Uploader
	 *
	 * @param   int      $field      The field (can be empty)
	 * @param   string   $u_item_id  The unique item id (can be empty)
	 * @param   int      $up_tagid   The HTML Tag ID of the uploader container
	 * @param   int      $n          The row number
	 * @param   string   $option     Options
	 *
	 * @return  string       HTML code
	 */
	public static function init($field, $u_item_id, $up_tag_id, $n, $options)
	{
		$field_id = $field ? $field->id : '';

		static $initialized = array();
		if (isset($initialized[$up_tag_id]))
		{
			return;
		}
		$initialized[$up_tag_id] = true;

		$defaults = array(
			'maxcount' => 1,  'layout' => 'default',  'edit_properties' => 'false',  'height_spare' => 0,
			'action' => JURI::base() . 'index.php?option=com_flexicontent&task=filemanager.uploads'
				. '&'.JSession::getFormToken().'=1' . '&fieldid='.$field_id . '&u_item_id='.$u_item_id
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
		var fc_files_uploader_'.$field_id.';
		var fc_files_uploader_'.$field_id.'_options = {
			mode: "'.$plupload_mode.'",
			tag_id: "'.$up_tag_id.'",
			action: "'.$options['action'].'",
			upload_maxsize: "'.$uops['upload_maxsize'].'",
			upload_maxcount: '.$options['maxcount'].',
			view_layout: "'.$options['layout'].'",
			edit_properties: '.$options['edit_properties'].',
			height_spare: '.$options['height_spare'].',

			handle_select: "fc_files_uploader_handle_select_'.$field_id.'",
			handle_complete: "fc_files_uploader_handle_complete_'.$field_id.'",

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
		var fc_files_uploader_handle_select_'.$field_id.' = function()
		{
			alert("select");
		}
		var fc_files_uploader_handle_complete_'.$field_id.' = function()
		{
			alert("complete");
		}

		jQuery(document).ready(function()
		{
			// Instantiate uploader
			var uploader = fc_files_uploader_'.$field_id.' = new fc_plupload(fc_files_uploader_'.$field_id.'_options);

			// Register debounced autoresizing of the uploader
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
		$field_id = $field ? $field->id : '';

		if (isset($uops[$field_id])) return $uops[$field_id];
		
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

		return $uops[$field_id] = $u;
	}
}

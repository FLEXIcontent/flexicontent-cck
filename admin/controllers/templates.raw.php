<?php
/**
 * @version 1.5 stable $Id: templates.php 1260 2012-04-25 17:43:21Z ggppdk $
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

// Register autoloader for parent controller, in case controller is executed by another component
JLoader::register('FlexicontentController', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'controller.php');

/**
 * FLEXIcontent Component Templates Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerTemplates extends FlexicontentController
{
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();

		// Register Extra task
	}
		
	/**
	 * Logic to duplicate a template
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function duplicate()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );

		$source 		= JRequest::getCmd('source');
		$dest 			= JRequest::getCmd('dest');
		
		$model = $this->getModel('templates');
		
		if (!$model->duplicate($source, $dest)) {
			echo JText::sprintf( 'FLEXI_TEMPLATE_FAILED_CLONE', $source );
			return;
		} else {
			$tmplcache = JFactory::getCache('com_flexicontent_tmpl');
			$tmplcache->clean();
			echo '<span class="copyok" style="margin-top:15px; display:block">'.JText::sprintf( 'FLEXI_TEMPLATE_CLONED', $source, $dest ).'</span>';
		}
	}
	
	/**
	 * Logic to remove a template
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function remove()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );
		$dir = JRequest::getCmd('dir');

		$model = $this->getModel('templates');
		
		if (!$model->delete($dir)) {
			echo JText::sprintf( 'FLEXI_TEMPLATE_FAILED_DELETE', $dir );
			return;
		} else {
			$tmplcache = JFactory::getCache('com_flexicontent_tmpl');
			$tmplcache->clean();
			echo '<span class="copyok">'.JText::sprintf( 'FLEXI_TEMPLATE_DELETED', $dir ).'</span>';
		}
	}


	/**
	 * Logic to render an XML file as form parameters
	 * NOTE: this does not work with Request Data validation in J2.5+. The validation
	 *       must be skipped or the parameters must be re-added after the validation
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function getlayoutparams()
	{
		jimport('joomla.filesystem.file');
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		
		//get vars
		$ext_option = JRequest::getVar( 'ext_option', '');
		$ext_view = JRequest::getVar( 'ext_view', '');
		$ext_id   = JRequest::getInt ( 'ext_id', 0 );
		$layout_name = JRequest::getVar( 'layout_name', 0 );
		$directory   = JRequest::getVar( 'directory', 0 );
		$path = (!is_dir($directory) ? JPATH_ROOT : '') . $directory;
		
		$db = JFactory::getDBO();
		if ($ext_view=='module') {
			$query = 'SELECT params FROM #__modules WHERE id = '.$ext_id;
			// load english language file for 'mod_flexicontent' module then override with current language file
			$module_name = basename(dirname($directory));
			JFactory::getLanguage()->load($module_name, JPATH_SITE, 'en-GB', true);
			JFactory::getLanguage()->load($module_name, JPATH_SITE, null, true);
		} else if ($ext_view=='field') {
			$query = 'SELECT attribs FROM #__flexicontent_fields WHERE id = '.$ext_id;
		} else {
			echo "not supported extension/view: ".$ext_view;
			return;
		}
		if ($ext_option!='com_flexicontent' && $ext_option!='com_modules' && $ext_option!='com_advancedmodules' && $ext_option!='com_menus') {
			echo '<div class="alert fcpadded fcinlineblock" style="">You are editing module via extension: <span class="label label-warning">'.$ext_option.'</span><br/> - If extension does not call Joomla event <span class="label label-warning">onExtensionBeforeSave</span> then custom layout parameters may not be saved</div>';
		}
		
		$db->setQuery( $query );
		$ext_params_str = $db->loadResult();
		
		$layout_names = explode(':', $layout_name);
		if(count($layout_names)>1) {
			$layout_name = $layout_names[1];
			$layoutpath = JPATH_ROOT.DS.'templates'.DS.$layout_names[0].DS.'html'.DS.'mod_flexicontent/'.$layout_name.'.xml';
		}else{
			$layoutpath = $path.DS.$layout_name.'.xml';
		}
		if (!file_exists($layoutpath)) {
			if (file_exists($path.DS.'_fallback'.DS.'_fallback.xml')) {
				$layoutpath = $path.DS.'_fallback'.DS.'_fallback.xml';
				echo '<div class="alert fcpadded fcinlineblock">Currently selected layout: <b>"'.$layout_name.'"</b> does not have a parameters XML file, using general defaults. if this is an old template then these parameters will allow to continue using it, but we recommend that you create parameter file: '.$layout_name.'.xml</div><div class="clear"></div>';
			}
			else {
				echo !FLEXI_J16GE ? '<div style="font-size: 11px; color: gray; background-color: lightyellow; border: 1px solid lightgray; width: auto; padding: 4px 2%; margin: 1px 8px; height: auto;">' : '<p class="tip">';
				echo ' Currently selected layout: <b>"'.$layout_name.'"</b> does not have layout specific parameters';
				echo !FLEXI_J16GE ? '</div>' : '</p>';
				exit;
			}
		}
		
		//Get data from the model
		if (FLEXI_J16GE)
		{
			// Load XML file
			if (FLEXI_J30GE) {
				$xml = simplexml_load_file($layoutpath);
				$xmldoc = & $xml;
			} else {
				$xml = JFactory::getXMLParser('Simple');
				$xml->loadFile($layoutpath);
				$xmldoc = & $xml->document;
			}
			
			// Create form object, (form name seems not to cause any problem)
			$jform = new JForm('com_flexicontent.template.item', array('control' => 'jform', 'load_data' => true));
			$tmpl_params = FLEXI_J30GE ? $xmldoc->asXML() : $xmldoc->toString();
			$jform->load($tmpl_params);
			
			// Load existing layout values into the object (that we got from DB)
			$ext_params = new JRegistry($ext_params_str); // and for J1.5:  new JParameter($ext_params_str);
			$grpname = 'params'; // this is the name of <fields> container
			foreach ($jform->getGroup($grpname) as $field) {
				$fieldname =  $field->__get('fieldname');
				$value = $ext_params->get($fieldname);
				if (strlen($value)) $jform->setValue($fieldname, $grpname, $value);
			}
		}
		else {
			// Create a parameters object
			$form = new JParameter('', $layoutpath);
			
			// Load existing layout values into the object (that we got from DB)
			$form->loadINI($ext_params_str);
		}
		
		if ($layout_name)
		{
			if (!FLEXI_J16GE) {
				echo $form->render('params', 'layout' );
			} else {
				?>
				<fieldset class="panelform"><?php /*<ul class="adminformlist">*/ ?>
					<?php
					$grpname = 'params'; // this is the name of <fields> container
					foreach ($jform->getGroup($grpname) as $field) {
						//echo '<li>'. $field->label . $field->input .'</li>';
						$_depends = FLEXI_J30GE ? $field->getAttribute('depend_class') :
							$form->getFieldAttribute($field->__get('fieldname'), 'depend_class', '', 'attribs');

						echo '
						<fieldset class="panelform '.($_depends ? ' '.$_depends : '').'" id="'.$field->id.'-container">
							'.($field->label ? '
								<span class="label-fcouter">'.str_replace('class="', 'class="label label-fcinner ', $field->label).'</span>
								<div class="container_fcfield">'.$field->input.'</div>
							' : $field->input).'
						</fieldset>';
					}
					?>
				<?php /*</ul>*/?></fieldset>
				<?php
			}
		} else {
			echo "<br /><span style=\"padding-left:25px;\"'>" . JText::_( 'FLEXI_APPLY_TO_SEE_THE_PARAMETERS' ) . "</span><br /><br />";
		}
		//parent::display($tpl);
	}


	function loadlayoutfile()
	{
		jimport('joomla.filesystem.file');
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		
		$var['sysmssg'] = '';
		$var['content'] = '';
		$var['default_exists'] = '0';
		
		// Check for request forgeries
		if (!JRequest::checkToken()) {
			$app->enqueueMessage( 'Invalid Token', 'error');
			$var['sysmssg'] = flexicontent_html::get_system_messages_html();
			echo json_encode($var);
			exit;
		}
		
		$common = array(
			'item.php' => 'item_layouts/modular.php',
			'item_html5.php' => 'item_layouts/modular_html5.php',
		);
		
		//get vars
		$load_mode  = JRequest::getVar( 'load_mode', '0' );
		$layout_name  = JRequest::getVar( 'layout_name', 'default' );
		$file_subpath = JRequest::getVar( 'file_subpath', '' );
		$layout_name  = preg_replace("/\.\.\//", "", $layout_name);
		$file_subpath = preg_replace("/\.\.\//", "", $file_subpath);
		//$file_subpath = preg_replace("#\\#", DS, $file_subpath);
		if (!$layout_name) $app->enqueueMessage( 'Layout name is empty / invalid', 'warning');
		if (!$file_subpath) $app->enqueueMessage( 'File path is empty / invalid', 'warning');
		
		if (!$layout_name || !$file_subpath) {
			$var['sysmssg'] = flexicontent_html::get_system_messages_html();
			echo json_encode($var);
			exit();
		}
		
		$path = JPath::clean(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.$layout_name);
		if (!is_dir($path)) {
			$app->enqueueMessage( 'Path: '.$path.' was not found', 'warning');
			$var['sysmssg'] = flexicontent_html::get_system_messages_html();
			echo json_encode($var);
			exit();
		}
		
		$file_path = JPath::clean($path.DS.$file_subpath);
		if (!file_exists($file_path)) {
			$app->enqueueMessage( 'File: '.$file_path.' was not found', 'warning');
			$var['sysmssg'] = flexicontent_html::get_system_messages_html();
			echo json_encode($var);
			exit();
		}
		
		// CASE of downloading instead of loading the file
		if ($load_mode == 2) {
			header("Pragma: public"); // required
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Cache-Control: private", false); // required for certain browsers
			header("Content-Type: text/plain");
			header("Content-Disposition: attachment; filename=\"".basename($file_subpath)."\";" );
			header("Content-Transfer-Encoding: binary");
			header("Content-Length: ".filesize($file_path));
			readfile($file_path);
		}
		
		// Check if default file path exists
		$default_path = JPath::clean(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common');
		$default_file = isset($common[$file_subpath]) ? $common[$file_subpath] : $file_subpath;    // Some files do not have the same name as default file
		$default_file_path = JPath::clean($default_path.DS.$default_file);
		$default_file_exists = file_exists($default_file_path) ? 1 : 0;
		
		// CASE LOADING system's default, set a different path to be read
		if ($load_mode) {
			if (!$default_file_exists) {
				$app->enqueueMessage( 'No default file for: '.$file_subpath.' exists, current file was --reloaded--', 'notice');
			} else {
				$file_path = $default_file_path;
			}
		}
		
		$var['sysmssg'] = flexicontent_html::get_system_messages_html();
		$var['default_exists'] = (string) $default_file_exists;
		$var['content'] = file_get_contents($file_path);
		echo json_encode($var);
	}
	
	
	function savelayoutfile()
	{
		jimport('joomla.filesystem.file');
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		
		$var['sysmssg'] = '';
		$var['content'] = '';
		
		// Check for request forgeries
		if (!JRequest::checkToken()) {
			$app->enqueueMessage( 'Invalid Token', 'error');
			$var['sysmssg'] = flexicontent_html::get_system_messages_html();
			echo json_encode($var);
			exit;
		}
		
		//get vars
		$file_contents = $_POST['file_contents'];
		$layout_name  = JRequest::getVar( 'layout_name', 'default' );
		$file_subpath = JRequest::getVar( 'file_subpath', '' );
		$layout_name  = preg_replace("/\.\.\//", "", $layout_name);
		$file_subpath = preg_replace("/\.\.\//", "", $file_subpath);
		if (!$layout_name) $app->enqueueMessage( 'Layout name is empty / invalid', 'warning');
		if (!$file_subpath) $app->enqueueMessage( 'File path is empty / invalid', 'warning');
		
		if (!$layout_name || !$file_subpath) {
			$var['sysmssg'] = flexicontent_html::get_system_messages_html();
			echo json_encode($var);
			exit();
		}
		
		$path = JPath::clean(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.$layout_name);
		if (!is_dir($path)) {
			$app->enqueueMessage( 'Layout: '.$layout_name.' was not found', 'warning');
			$var['sysmssg'] = flexicontent_html::get_system_messages_html();
			echo json_encode($var);
			exit();
		}
		
		$file_path = JPath::clean($path.DS.$file_subpath);
		if (!file_exists($file_path)) {
			$app->enqueueMessage( 'Layout: '.$layout_name.' was not found', 'warning');
			$var['sysmssg'] = flexicontent_html::get_system_messages_html();
			echo json_encode($var);
			exit();
		}
		
		if (file_put_contents($file_path, $file_contents)) {
			$app->enqueueMessage( 'File: '.$file_path.' was saved ', 'message');
			if (preg_match('#\.xml#', $file_path)) {
				$tmplcache = JFactory::getCache('com_flexicontent_tmpl');
				$tmplcache->clean();
			}
		} else {
			$app->enqueueMessage( 'Failed to save file: '.$layout_name, 'warning');
		}
		
		$var['sysmssg'] = flexicontent_html::get_system_messages_html();
		$var['content'] = file_get_contents($file_path);
		echo json_encode($var);
	}
}

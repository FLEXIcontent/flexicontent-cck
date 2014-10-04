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

jimport('joomla.application.component.controller');

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
		$mainframe = JFactory::getApplication();
		$user = JFactory::getUser();
		
		//get vars
		$ext_view = JRequest::getVar( 'ext_view', '');
		$ext_id   = JRequest::getInt ( 'ext_id', 0 );
		$layout_name = JRequest::getVar( 'layout_name', 0 );
		$directory   = JRequest::getVar( 'directory', 0 );
		$path = (!is_dir($directory) ? JPATH_ROOT : '') . $directory;
		
		$db = JFactory::getDBO();
		if ($ext_view=='module') {
			$query = 'SELECT params FROM #__modules WHERE id = '.$ext_id;
		} else if ($ext_view=='field') {
			$query = 'SELECT attribs FROM #__flexicontent_fields WHERE id = '.$ext_id;
		} else {
			echo "not supported extension/view: ".$ext_view;
			return;
		}
		$db->setQuery( $query );
		$ext_params_str = $db->loadResult();
		
		$layoutpath = $path.DS.$layout_name.'.xml';
		if (!file_exists($layoutpath)) {
			echo !FLEXI_J16GE ? '<div style="font-size: 11px; color: gray; background-color: lightyellow; border: 1px solid lightgray; width: auto; padding: 4px 2%; margin: 1px 8px; height: auto;">' : '<p class="tip">';
			echo ' Currently selected layout: <b>"'.$layout_name.'"</b> does not have layout specific parameters';
			echo !FLEXI_J16GE ? '</div>' : '</p>';
			exit;
		}
		
		//Get data from the model
		if (FLEXI_J16GE) {
			$grpname = 'params'; // this name of <fields> container
			
			if (FLEXI_J30GE) {
				$xml = simplexml_load_file($layoutpath);
				$xmldoc = & $xml;
			} else {
				$xml = JFactory::getXMLParser('Simple');
				$xml->loadFile($layoutpath);
				$xmldoc = & $xml->document;
			}
			
			$tmpl_params = FLEXI_J30GE ? $xmldoc->asXML() : $xmldoc->toString();
			
			// Create form object, (form name seems not to cause any problem)
			$jform = new JForm('com_flexicontent.template.item', array('control' => 'jform', 'load_data' => true));
			$jform->load($tmpl_params);
			
			// Load existing layout values into the object (that we got from DB)
			$ext_params = new JRegistry($ext_params_str); // and for J1.5:  new JParameter($ext_params_str);
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
				<fieldset class="panelform"><ul class="adminformlist">
					<?php
					foreach ($jform->getGroup($grpname) as $field) {
						echo '<li>'. $field->label . $field->input .'</li>';
					}
					?>
				</ul></fieldset>
				<?php
			}
		} else {
			echo "<br /><span style=\"padding-left:25px;\"'>" . JText::_( 'FLEXI_APPLY_TO_SEE_THE_PARAMETERS' ) . "</span><br /><br />";
		}
		//parent::display($tpl);
	}
}
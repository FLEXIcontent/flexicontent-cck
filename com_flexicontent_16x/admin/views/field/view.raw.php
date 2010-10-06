<?php
/**
 * @version 1.5 stable $Id: view.raw.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
jimport( 'joomla.application.component.view');

/**
 * View class for the FLEXIcontent field screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewField extends JView {

	function display($tpl = null) {
		$mainframe = &JFactory::getApplication();
		$user 		= & JFactory::getUser();

		JHTML::_('behavior.tooltip');

		//get vars
		$cid 		= JRequest::getVar( 'cid' );
		$field_type = JRequest::getVar( 'field_type', 0 );
		
		//Get data from the model
		$model			= & $this->getModel();
		$row     			= & $this->get( 'Field' );
		//var_dump($model);
		//Import File system
		jimport('joomla.filesystem.file');
		
		// Create the form
		$pluginpath = JPATH_PLUGINS.DS.'flexicontent_fields'.DS.$field_type.'.xml';
		if (JFile::exists( $pluginpath )) {
			$form = new JParameter('', $pluginpath);
		} else {
			$form = new JParameter('', JPATH_PLUGINS.DS.'flexicontent_fields'.DS.'core.xml');
		}
		$form->loadINI($row->attribs);
		// fail if checked out not by 'me'
		if ($row->id) {
			if ($model->isCheckedOut( $user->get('id') )) {
				JError::raiseWarning( 'SOME_ERROR_CODE', $row->name.' '.JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
				$mainframe->redirect( 'index.php?option=com_flexicontent&view=fields' );
			}
		}
		if ($field_type)
		{
			echo $form->render('params', 'group-' . $field_type );
		} else {
			echo "<br /><span style=\"padding-left:25px;\"'>" . JText::_( 'FLEXI_APPLY_TO_SEE_THE_PARAMETERS' ) . "</span><br /><br />";
		}
		//parent::display($tpl);
	}
}
?>
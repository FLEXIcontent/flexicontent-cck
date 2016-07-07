<?php
/**
 * @version 1.5 stable $Id: view.html.php 1577 2012-12-02 15:10:44Z ggppdk $
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

jimport('legacy.view.legacy');

/**
 * View class for the FLEXIcontent category screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewTag extends JViewLegacy
{
	function display($tpl = null)
	{
		$mainframe = JFactory::getApplication();

		//initialise variables
		$document	= JFactory::getDocument();
		$user 		= JFactory::getUser();
		$bar			= JToolBar::getInstance('toolbar');
		$cparams	= JComponentHelper::getParams('com_flexicontent');

		//get vars
		$cid 		= JRequest::getVar( 'cid' );

		//add css to document
		$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH);
		$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH);
		
		// Add JS frameworks
		//flexicontent_html::loadFramework('select2');
		
		// Add js function to overload the joomla submitform validation
		JHTML::_('behavior.formvalidation');  // load default validation JS to make sure it is overriden
		$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
		$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);

		//Get data from the model
		$model = $this->getModel();
		$row   = $this->get( 'Tag' );
		
		//create the toolbar
		if ( $cid ) {
			JToolBarHelper::title( JText::_( 'FLEXI_EDIT_TAG' ), 'tagedit' );
			$base 			= str_replace('administrator/', '', JURI::base());
			$autologin  = ''; // $cparams->get('autoflogin', 1) ? '&fcu='.$user->username . '&fcp='.$user->password : '';
			// Add a preview button
			$previewlink 	= $base . JRoute::_(FlexicontentHelperRoute::getTagRoute($row->id)) . $autologin;
			$bar->appendButton( 'Custom', '<a class="preview" href="'.$previewlink.'" target="_blank"><span title="'.JText::_('FLEXI_PREVIEW').'" class="icon-32-preview"></span>'.JText::_('FLEXI_PREVIEW').'</a>', 'preview' );
		} else {
			JToolBarHelper::title( JText::_( 'FLEXI_NEW_TAG' ), 'tagadd' );
		}
		
		if (FLEXI_J16GE) {
			JToolBarHelper::apply	('tags.apply');
			JToolBarHelper::save	('tags.save');
			JToolBarHelper::custom('tags.saveandnew', 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );
			JToolBarHelper::cancel('tags.cancel');
		} else {
			JToolBarHelper::apply();
			JToolBarHelper::save();
			JToolBarHelper::custom( 'saveandnew', 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );
			JToolBarHelper::cancel();
		}
		
		// fail if checked out not by 'me'
		if ($row->id) {
			if ($model->isCheckedOut( $user->get('id') )) {
				JError::raiseWarning( 'SOME_ERROR_CODE', $row->name.' '.JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
				$mainframe->redirect( 'index.php?option=com_flexicontent&view=tags' );
			}
		}

		// Encode (UTF-8 charset) HTML entities form data so that they can be set as form field values
		JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES, $exclude_keys = '' );

		//assign data to template
		$this->assignRef('row'      	, $row);

		parent::display($tpl);
	}
}
?>
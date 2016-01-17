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
 * View class for the FLEXIcontent file screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewFile extends JViewLegacy {

	function display($tpl = null)
	{
		//initialise variables
		$app      = JFactory::getApplication();
		$document = JFactory::getDocument();
		$user     = JFactory::getUser();
		//$authorparams = flexicontent_db::getUserConfig($user->id);

		//add css/js to document
		flexicontent_html::loadFramework('select2');
		$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH);
		$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH);
		
		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_EDIT_FILE' ), 'fileedit' );
		
		JToolBarHelper::apply('filemanager.apply');
		JToolBarHelper::save('filemanager.save');
		JToolBarHelper::cancel('filemanager.cancel');
		
		//Get data from the model
		$model = $this->getModel();
		$form  = $this->get('Form');
		$row   = $this->get('File');
		
		// fail if checked out not by 'me'
		if ($row->id) {
			if ($model->isCheckedOut( $user->get('id') )) {
				JError::raiseWarning( 'SOME_ERROR_CODE', $row->name.' '.JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
				$app->redirect( 'index.php?option=com_flexicontent&view=filemanager' );
			}
		}
		
		//build access level list
		$lists['access'] 	= JHTML::_('access.assetgrouplist', 'access', $row->access, $config=array('class'=>'use_select2_lib'));
		
		// Build languages list
		//$allowed_langs = !$authorparams ? null : $authorparams->get('langs_allowed',null);
		//$allowed_langs = !$allowed_langs ? null : FLEXIUtilities::paramToArray($allowed_langs);
		$allowed_langs = null;
		$lists['language'] = flexicontent_html::buildlanguageslist('language', ' class="use_select2_lib" ', $row->language, 2, $allowed_langs, $published_only=false);
		
		// Encode (UTF-8 charset) HTML entities form data so that they can be set as form field values
		JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES, $exclude_keys = '' );

		//assign data to template
		$this->assignRef('form'				, $form);
		$this->assignRef('row'				, $row);
		$this->assignRef('lists'			, $lists);
		$this->assignRef('document'		, $document);

		parent::display($tpl);
	}
}
?>
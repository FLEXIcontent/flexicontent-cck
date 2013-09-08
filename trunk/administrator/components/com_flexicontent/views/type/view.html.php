<?php
/**
 * @version 1.5 stable $Id$
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

jimport('joomla.application.component.view');

/**
 * View class for the FLEXIcontent type screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewType extends JViewLegacy
{
	function display($tpl = null)
	{
		//initialise variables
		$app      = JFactory::getApplication();
		$document	= JFactory::getDocument();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$user     = JFactory::getUser();
		
		//add css to document
		$document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/flexicontentbackend.css');
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j25.css');
		else                  $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j15.css');
		
		//add js function to overload the joomla submitform
		FLEXI_J30GE ? JHtml::_('behavior.framework') : JHTML::_('behavior.mootools');
		JHTML::_('behavior.tooltip');
		$document->addScript(JURI::root().'components/com_flexicontent/assets/js/admin.js');
		$document->addScript(JURI::root().'components/com_flexicontent/assets/js/validate.js');
		
		//Load pane behavior
		jimport('joomla.html.pane');

		//Get data from the model
		$model  = $this->getModel();
		$row		= $this->get( FLEXI_J16GE ? 'Item' : 'Type' );
		if (FLEXI_J16GE) {
			$form = $this->get('Form');
		}
		$themes		= flexicontent_tmpl::getTemplates();
		$tmpls		= $themes->items;
		
		//create the toolbar
		if ( $row->id ) {
			JToolBarHelper::title( JText::_( 'FLEXI_EDIT_TYPE' ), 'typeedit' );
		} else {
			JToolBarHelper::title( JText::_( 'FLEXI_ADD_TYPE' ), 'typeadd' );
		}
		
		$ctrl = FLEXI_J16GE ? 'types.' : '';
		JToolBarHelper::apply( $ctrl.'apply' );
		JToolBarHelper::save( $ctrl.'save' );
		JToolBarHelper::custom( $ctrl.'saveandnew', 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );
		JToolBarHelper::cancel( $ctrl.'cancel' );
		
		// fail if checked out not by 'me'
		if ($row->id) {
			if ($model->isCheckedOut( $user->get('id') )) {
				JError::raiseWarning( 'SOME_ERROR_CODE', $row->name.' '.JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
				$app->redirect( 'index.php?option=com_flexicontent&view=types' );
			}
		}
		
		if (FLEXI_ACCESS) {
			$itemscreatable[] = JHTML::_('select.option',  0, JText::_( 'FLEXI_ANY_AUTHOR' ) );
			$itemscreatable[] = JHTML::_('select.option',  1, JText::_( 'FLEXI_USE_ACL_TO_HIDE' ) );
			$itemscreatable[] = JHTML::_('select.option',  2, JText::_( 'FLEXI_USE_ACL_TO_DISABLE' ) );
			$itemscreatable_fieldname = FLEXI_J16GE ? 'jform[itemscreatable]' : 'itemscreatable';
			$lists['itemscreatable'] = JHTML::_('select.genericlist',   $itemscreatable, $itemscreatable_fieldname, '', 'value', 'text', $row->itemscreatable );
		}
		
		//build access level list
		if (!FLEXI_J16GE) {
			if (FLEXI_ACCESS) {
				$lang = & JFactory::getLanguage();
				$lang->_strings['FLEXIACCESS_PADD'] = 'Create Items';
				$lists['access']	= FAccess::TabGmaccess( $row, 'type', 1, 1, 0, 0, 0, 0, 0, 0, 0 );
			} else {
				$lists['access'] 	= JHTML::_('list.accesslevel', $row );
			}
		}
		
		if (!FLEXI_J16GE) {
			//clean data
			JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES );
			
			//create the parameter form
			$form = new JParameter($row->attribs, JPATH_COMPONENT.DS.'models'.DS.'type.xml');
			//$form->loadINI($row->attribs);
			
			//echo "<pre>"; print_r($form->_xml['themes']->_children[0]);  echo "<pre>"; print_r($form->_xml['themes']->param[0]); exit;
			foreach($form->_xml['themes']->_children as $i => $child) {
				if ( isset($child->_attributes['enableparam']) && !$cparams->get($child->_attributes['enableparam']) ) {
					unset($form->_xml['themes']->_children[$i]);
					unset($form->_xml['themes']->param[$i]);
				}
			}
		}
		
		// Apply Template Parameters values into the form fields structures 
		foreach ($tmpls as $tmpl) {
			if (FLEXI_J16GE) {
				$jform = new JForm('com_flexicontent.template.item', array('control' => 'jform', 'load_data' => true));
				$jform->load($tmpl->params);
				$tmpl->params = $jform;
				// ... values applied at the template form file
			} else {
				$tmpl->params->loadINI($row->attribs);
			}
		}		
		
		//assign data to template
		// assign permissions for J2.5
		if (FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$this->assignRef('permission'  , $permission);
		}
		$this->assignRef('document'   , $document);
		$this->assignRef('row'        , $row);
		$this->assignRef('form'       , $form);
		$this->assignRef('tmpls'      , $tmpls);
		if (!FLEXI_J16GE) {
			$pane = JPane::getInstance('sliders');
			$this->assignRef('pane'     , $pane);
			$this->assignRef('lists'    , $lists);
		}
		
		parent::display($tpl);
	}
}
?>
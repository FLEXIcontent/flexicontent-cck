<?php
/**
 * @version 1.5 stable $Id: view.html.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
 * View class for the FLEXIcontent category screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewCategory extends JView {
	function display($tpl = null) {
		global $globalcats;
		$mainframe = &JFactory::getApplication();
		$permission = FlexicontentHelperPerm::getPerm();

		if (!$permission->CanCats && !$permission->CanAddCats) {
			$mainframe->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
		}
		
		$iform		= $this->get('Form');

		//Load pane behavior
		jimport('joomla.html.pane');

		//initialise variables
		$editor 	= & JFactory::getEditor();
		$document	= & JFactory::getDocument();
		$user 		= & JFactory::getUser();
		$pane 		= & JPane::getInstance('sliders');

		JHTML::_('behavior.tooltip');

		//get vars
		$cid 		= JRequest::getVar( 'cid' );

		//add css to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');
		//add js function to overload the joomla submitform
		$document->addScript('components/com_flexicontent/assets/js/admin.js');
		$document->addScript('components/com_flexicontent/assets/js/validate.js');

		//create the toolbar
		if ( $cid ) {
			JToolBarHelper::title( JText::_( 'FLEXI_EDIT_CATEGORY' ), 'fc_categoryedit' );

		} else {
			JToolBarHelper::title( JText::_( 'FLEXI_NEW_CATEGORY' ), 'fc_categoryadd' );
		}

		JToolBarHelper::apply();
		JToolBarHelper::save();
		JToolBarHelper::custom( 'saveandnew', 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );
		JToolBarHelper::cancel();

		//Get data from the model
		$model		= & $this->getModel();
		//$row     		= & $this->get( 'Category' );
		$themes		= flexicontent_tmpl::getTemplates();
		$tmpls		= $themes->category;
		$categories = $globalcats;
		//fail if checked out not by 'me'
		if($iform->getValue("id")) {
			if ($model->isCheckedOut( $user->get('id') )) {
				JError::raiseWarning( 'SOME_ERROR_CODE', $iform->getValue("title").' '.JText::_( 'FLEXI_EDITED BY ANOTHER ADMIN' ));
				$mainframe->redirect( 'index.php?option=com_flexicontent&view=categories' );
			}
		} else {
			if (!$permission->CanAddCats) {
				$mainframe->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
			}
		}

		//clean data
		//JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES, 'description' );
	    	
		// Create the form
		/*$form = new JParameter($row->params, JPATH_COMPONENT.DS.'models'.DS.'category.xml');
//		$form->loadINI($row->params);
		foreach ($tmpls as $tmpl) {
			$tmpl->params->loadINI($row->params);
		}*/
		
		//build selectlists
		$Lists = array();
		$javascript = "onchange=\"javascript:if (document.forms[0].image.options[selectedIndex].value!='') {document.imagelib.src='../images/stories/' + document.forms[0].image.options[selectedIndex].value} else {document.imagelib.src='../images/blank.png'}\"";
		//$Lists['imagelist'] 		= JHTML::_('list.images', 'image', $row->image, $javascript, '/images/' );
		//$Lists['access'] 			= JHTML::_('list.accesslevel', $row );


		/*if (FLEXI_ACCESS && ($user->gid < 25)) {
			if ((FAccess::checkAllContentAccess('com_content','add','users',$user->gmid,'content','all')) || (FAccess::checkAllContentAccess('com_content','edit','users',$user->gmid,'content','all')) || (FAccess::checkAllContentAccess('com_content','editown','users',$user->gmid,'content','all')) || $permission->CanCats) {
				$Lists['parent_id'] = flexicontent_cats::buildcatselect($categories, 'parent_id', $row->parent_id, true, 'class="inputbox"', false, false);
				$Lists['copyid'] = flexicontent_cats::buildcatselect($categories, 'copycid', '', 2, 'class="inputbox"', false, false);
			} else {
				$Lists['parent_id'] = flexicontent_cats::buildcatselect($categories, 'parent_id', $row->parent_id, true, 'class="inputbox"');
				$Lists['copyid'] = flexicontent_cats::buildcatselect($categories, 'copycid', '', 2, 'class="inputbox"');
			}
		} else {
			$Lists['parent_id'] = flexicontent_cats::buildcatselect($categories, 'parent_id', $row->parent_id, true, 'class="inputbox"');
			$Lists['copyid'] = flexicontent_cats::buildcatselect($categories, 'copycid', '', 2, 'class="inputbox"');
		}*/

		// build granular access list
		/*if (FLEXI_ACCESS) {
			$Lists['access'] = FAccess::TabGmaccess( $row, 'category', 1, 1, 1, 1, 1, 1, 1, 1, 1 );
		}*/

		//assign vars to view
		$this->assignRef('document'     , $document);
		$this->assignRef('Lists'      	, $Lists);
		//$this->assignRef('row'      	, $row);
		$this->assignRef('permission'	, $permission);
		$this->assignRef('editor'	, $editor);
		//$this->assignRef('form'	, $form);
		$this->assignRef('pane'		, $pane);
		$this->assignRef('tmpls'	, $tmpls);
		$this->assignRef('iform'	, $iform);

		parent::display($tpl);
	}
}
?>

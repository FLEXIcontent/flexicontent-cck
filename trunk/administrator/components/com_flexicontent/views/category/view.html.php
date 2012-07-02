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

jimport( 'joomla.application.component.view');

/**
 * View class for the FLEXIcontent category screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewCategory extends JView
{
	function display($tpl = null)
	{
		global $globalcats;
		$mainframe = &JFactory::getApplication();
		$user 		= & JFactory::getUser();
		
		if (FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$CanCats = $permission->CanCats;
			$CanAddCats = $permission->CanAdd;
		} else if (FLEXI_ACCESS) {
			$CanCats		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'categories', 'users', $user->gmid) : 1;
			$CanAddCats	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'addcats', 'users', $user->gmid) : 1;
		} else {
			$CanCats		= 1;
			$CanAddCats	= 1;
		}
		
		// This also done at the controller
		if ( !$CanCats && !$CanAddCats ) {
			$mainframe->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
		}

		//Load pane behavior
		jimport('joomla.html.pane');
		//Get the route helper for the preview function
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');

		//initialise variables
		$editor 	= & JFactory::getEditor();
		$document	= & JFactory::getDocument();
		$cparams 	= & JComponentHelper::getParams('com_flexicontent');
		$bar			= & JToolBar::getInstance('toolbar');
		$pane			= & JPane::getInstance('sliders');

		JHTML::_('behavior.tooltip');

		//add css to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');
		//add js function to overload the joomla submitform
		$document->addScript('components/com_flexicontent/assets/js/admin.js');
		$document->addScript('components/com_flexicontent/assets/js/validate.js');

		//Get data from the model
		$model		= & $this->getModel();
		$row     	= & $this->get( 'Category' );
		$cid			=	$row->id;
		$themes		= flexicontent_tmpl::getTemplates();
		$tmpls		= $themes->category;
		
		$categories = $globalcats;
		
		//create the toolbar
		if ( $cid ) {
			JToolBarHelper::title( JText::_( 'FLEXI_EDIT_CATEGORY' ), 'fc_categoryedit' );
			$autologin		= $cparams->get('autoflogin', 1) ? '&fcu='.$user->username . '&fcp='.$user->password : '';
			$previewlink 	= JRoute::_(JURI::root(). FlexicontentHelperRoute::getCategoryRoute($categories[$cid]->slug)) . $autologin;
			// Add a preview button
			$bar->appendButton( 'Custom', '<a class="preview" href="'.$previewlink.'" target="_blank"><span title="'.JText::_('Preview').'" class="icon-32-preview"></span>'.JText::_('Preview').'</a>', 'preview' );
		} else {
			JToolBarHelper::title( JText::_( 'FLEXI_NEW_CATEGORY' ), 'fc_categoryadd' );
		}
		
		JToolBarHelper::apply();
		JToolBarHelper::save();
		JToolBarHelper::custom( 'saveandnew', 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );
		JToolBarHelper::cancel();
		
		//fail if checked out not by 'me'
		if ($cid) {
			if ($model->isCheckedOut( $user->get('id') )) {
				JError::raiseWarning( 'SOME_ERROR_CODE', $row->title.' '.JText::_( 'FLEXI_EDITED BY ANOTHER ADMIN' ));
				$mainframe->redirect( 'index.php?option=com_flexicontent&view=categories' );
			}
		} else {
			if (!$CanAddCats) {
				$mainframe->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
			}
		}
		
		//clean data
		JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES, 'description' );
		
		// Create the form
		$form = new JParameter($row->params, JPATH_COMPONENT.DS.'models'.DS.'category.xml');
		//$form->loadINI($row->attribs);
		
		// Apply Template Parameters values into the form fields structures 
		foreach ($tmpls as $tmpl) {
			if (FLEXI_J16GE) {
				$jform = new JForm('com_flexicontent.template.category', array('control' => 'jform', 'load_data' => true));
				$jform->load($tmpl->params);
				$tmpl->params = $jform;
				// ... values applied at the template form file
			} else {
				$tmpl->params->loadINI($row->params);
			}
		}
		
		//build selectlists
		$Lists = array();
		$javascript = "onchange=\"javascript:if (document.forms[0].image.options[selectedIndex].value!='') {document.imagelib.src='../images/stories/' + document.forms[0].image.options[selectedIndex].value} else {document.imagelib.src='../images/blank.png'}\"";
		$Lists['imagelist']	= JHTML::_('list.images', 'image', $row->image, $javascript, '/images/stories/' );
		$Lists['access']		= JHTML::_('list.accesslevel', $row );


		if (FLEXI_ACCESS && ($user->gid < 25)) {
			if ((FAccess::checkAllContentAccess('com_content','add','users',$user->gmid,'content','all')) || (FAccess::checkAllContentAccess('com_content','edit','users',$user->gmid,'content','all')) || (FAccess::checkAllContentAccess('com_content','editown','users',$user->gmid,'content','all')) || $CanCats) {
				$Lists['parent_id'] = flexicontent_cats::buildcatselect($categories, 'parent_id', $row->parent_id, true, 'class="inputbox"', false, false);
				$Lists['copyid'] = flexicontent_cats::buildcatselect($categories, 'copycid', '', 2, 'class="inputbox"', false, false);
			} else {
				$Lists['parent_id'] = flexicontent_cats::buildcatselect($categories, 'parent_id', $row->parent_id, true, 'class="inputbox"');
				$Lists['copyid'] = flexicontent_cats::buildcatselect($categories, 'copycid', '', 2, 'class="inputbox"');
			}
		} else {
			$Lists['parent_id'] = flexicontent_cats::buildcatselect($categories, 'parent_id', $row->parent_id, true, 'class="inputbox"');
			$Lists['copyid'] = flexicontent_cats::buildcatselect($categories, 'copycid', '', 2, 'class="inputbox"');
		}

		// build granular access list
		if (FLEXI_ACCESS) {
			$Lists['access'] = FAccess::TabGmaccess( $row, 'category', 1, 1, 1, 1, 1, 1, 1, 1, 1 );
		}
					
		//assign vars to view
		$this->assignRef('document'	, $document);
		$this->assignRef('Lists'		, $Lists);
		$this->assignRef('row'			, $row);
		$this->assignRef('form'			, $form);
		$this->assignRef('CanCats'	, $CanCats);
		$this->assignRef('editor'		, $editor);
		$this->assignRef('tmpls'		, $tmpls);
		$this->assignRef('pane'			, $pane);

		parent::display($tpl);
	}
}
?>
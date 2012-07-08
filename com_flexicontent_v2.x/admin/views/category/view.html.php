<?php
/**
 * @version 1.5 stable $Id: view.html.php 1364 2012-07-02 02:09:20Z ggppdk $
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
		
		if ( !$CanCats ) {
			$mainframe->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
		}

		// Get data from the model
		$model		= & $this->getModel();
		$row     	= & $this->get( 'Item' );
		$form			= & $this->get('Form');
		
		$cid			=	$row->id;
		$isNew		= !$cid;
		$checkedOut	= $model->isCheckedOut( $user->get('id') );
		$isOwner = $row->get('created_by') == $user->id;
		
		$rights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'category', $cid);
		$canedit_cat   = in_array('edit', $rights) || (in_array('edit.own', $rights) && $isOwner);
		$cancreate_cat = in_array('create', $rights);
		$cancreate_any = count( FlexicontentHelperPerm::getCats(array('core.create')) );
		
		// Editing existing category: Check if category is already checked out by different user
		if ($cid) {
			if ($model->isCheckedOut( $user->get('id') )) {
				JError::raiseWarning( 'SOME_ERROR_CODE', $row->title.' '.JText::_( 'FLEXI_EDITED BY ANOTHER ADMIN' ));
				$mainframe->redirect( 'index.php?option=com_flexicontent&view=categories' );
			}
		}
		
		// Creating new category: Check if user can create at least one category
		else {
			if ( !$CanAddCats || !$cancreate_any ) {
				$mainframe->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
			}
		}
		
		// Load pane behavior
		jimport('joomla.html.pane');
		//Get the route helper for the preview function
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');

		// Initialise variables
		$editor 	= & JFactory::getEditor();
		$document	= & JFactory::getDocument();
		$cparams 	= & JComponentHelper::getParams('com_flexicontent');
		$bar			= & JToolBar::getInstance('toolbar');
		$themes		= flexicontent_tmpl::getTemplates();
		$tmpls		= $themes->category;
		$categories = $globalcats;
		
		// Load tooltips
		JHTML::_('behavior.tooltip');
		
		// Add css to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');
		
		// Add js function to overload the joomla submitform
		$document->addScript('components/com_flexicontent/assets/js/admin.js');
		$document->addScript('components/com_flexicontent/assets/js/validate.js');
		
		
		// *****************
		// Create the toolbar
		// *****************
		
		if ( $cid ) {
			JToolBarHelper::title( JText::_( 'FLEXI_EDIT_CATEGORY' ), 'fc_categoryedit' );
			$autologin		= $cparams->get('autoflogin', 1) ? '&fcu='.$user->username . '&fcp='.$user->password : '';
			$previewlink 	= JRoute::_(JURI::root(). FlexicontentHelperRoute::getCategoryRoute($categories[$cid]->slug)) . $autologin;
			// Add a preview button
			$bar->appendButton( 'Custom', '<a class="preview" href="'.$previewlink.'" target="_blank"><span title="'.JText::_('Preview').'" class="icon-32-preview"></span>'.JText::_('Preview').'</a>', 'preview' );
		} else {
			JToolBarHelper::title( JText::_( 'FLEXI_NEW_CATEGORY' ), 'fc_categoryadd' );
		}
		
		// For new records, check the create permission.
		if ( $isNew && $cancreate_any ) {
			JToolBarHelper::apply('category.apply');
			JToolBarHelper::save('category.save');
			JToolBarHelper::save2new('category.save2new');
		}

		// If not checked out, can save the item.
		elseif ( !$checkedOut && $canedit_cat ) {
			JToolBarHelper::apply('category.apply');
			JToolBarHelper::save('category.save');
			if ($cancreate_cat) {
				JToolBarHelper::save2new('category.save2new');
			}
		}

		// If an existing item, can save to a copy.
		if (!$isNew && $cancreate_cat) {
			JToolBarHelper::save2copy('category.save2copy');
		}

		if (empty($row->id))  {
			JToolBarHelper::cancel('category.cancel');
		}
		else {
			JToolBarHelper::cancel('category.cancel', 'JTOOLBAR_CLOSE');
		}
		
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

		//assign vars to view
		$this->assignRef('document'	, $document);
		$this->assignRef('Lists'		, $Lists);
		$this->assignRef('row'			, $row);
		$this->assignRef('form'			, $form);
		$this->assignRef('permission', $permission);
		$this->assignRef('editor'		, $editor);
		$this->assignRef('tmpls'		, $tmpls);

		parent::display($tpl);
	}
}
?>

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
jimport('joomla.form.form');

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

		//initialise variables
		$document	= & JFactory::getDocument();
		$user 		= & JFactory::getUser();

		//get vars
		$cid 		= JRequest::getVar( 'cid' );

		//add css to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');
		//add js function to overload the joomla submitform
		$document->addScript('components/com_flexicontent/assets/js/admin.js');
		$document->addScript('components/com_flexicontent/assets/js/validate.js');

		//create the toolbar
		if ( $cid ) {
			JToolBarHelper::title( JText::_( 'FLEXI_EDIT_FIELD' ), 'fieldedit' );

		} else {
			JToolBarHelper::title( JText::_( 'FLEXI_ADD_FIELD' ), 'fieldadd' );
		}
		JToolBarHelper::apply('fields.apply');
		JToolBarHelper::save('fields.save');
		JToolBarHelper::custom( 'fields.saveandnew', 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );
		JToolBarHelper::cancel('fields.cancel');

		//Load pane behavior
		jimport('joomla.html.pane');
		//Import File system
		jimport('joomla.filesystem.file');

		//Get data from the model
		$model				= & $this->getModel();
		$form				= $this->get('Form');
		
		//support checking.
		$this->supportsearch = true;
		$this->supportadvsearch = false;
		$this->supportfilter = false;
		$core_advsearch = array('title', 'maintext', 'tags', 'checkbox', 'checkboximage', 'radio', 'radioimage', 'select', 'selectmultiple', 'text', 'date');
		$core_filters = array('createdby', 'modifiedby', 'type', 'state', 'tags', 'checkbox', 'checkboximage', 'radio', 'radioimage', 'select', 'selectmultiple', 'text', 'date', 'categories');
		if($form->getValue('field_type')) {
			// load language file
			//JPlugin::loadLanguage('plg_flexicontent_fields_'. ($form->getValue("iscore") ? 'core' : $form->getValue('field_type')), JPATH_ADMINISTRATOR);
			$jlang =& JFactory::getLanguage();
			$jlang->load('plg_flexicontent_fields_'. ($form->getValue("iscore") ? 'core' : $form->getValue('field_type')), JPATH_ADMINISTRATOR);
			
			$classname	= 'plgFlexicontent_fields'.($form->getValue("iscore") ? 'core' : $form->getValue('field_type'));
			$classmethods	= get_class_methods($classname);
			if($form->getValue("iscore")) {
				$this->supportadvsearch = in_array($form->getValue('field_type'), $core_advsearch);//I'm not sure for this line, we may be change it if we have other ways are better.[Enjoyman]
				$this->supportfilter = in_array($form->getValue('field_type'), $core_filters);
			}else{
				$this->supportadvsearch = (in_array('onAdvSearchDisplayField', $classmethods) || in_array('onFLEXIAdvSearch', $classmethods));
				$this->supportfilter = in_array('onDisplayFilter', $classmethods);
			}
		}
		
		JHTML::_('behavior.tooltip');

		//build field_type list
		if ($form->getValue("iscore") == 1) { $class = 'disabled="disabled"'; } else {
			$class = '';
			$document->addScriptDeclaration("
					window.addEvent('domready', function() {
						$$('#jform_field_type').addEvent('change', function(ev) {
							$('fieldspecificproperties').set('html', '<p class=\"centerimg\"><img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" align=\"center\"></p>');
							new Request.HTML({
								url: 'index.php?option=com_flexicontent&task=fields.getfieldspecificproperties&cid=".$form->getValue("id")."&field_type='+this.value+'&format=raw',
								method: 'get',
								update: $('fieldspecificproperties'),
								evalScripts: false,
								onComplete:function(response) {
									var JTooltips = new Tips($$('.hasTip'), { maxTitleChars: 50, fixed: false});									
									$('field_typename').innerHTML = $('jform_field_type').value;
								}
							}).send();
						});
					});
				");
			
		}

		// fail if checked out not by 'me'
		if ($form->getValue("id")) {
			if ($model->isCheckedOut( $user->get('id') )) {
				JError::raiseWarning( 'SOME_ERROR_CODE', $row->name.' '.JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
				$mainframe->redirect( 'index.php?option=com_flexicontent&view=fields' );
			}
		}
		$permission = &FlexicontentHelperPerm::getPerm();
		//clean data
		JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES );

		//assign data to template
		$this->assignRef('document'     , $document);
		$this->assignRef('row'      	, $row);
		$this->assignRef('permission'   , $permission);
		$this->assignRef('lists'      	, $lists);
//		$this->assignRef('tmpls'      	, $tmpls);
		//$this->assignRef('aform'	, $aform);
		$this->assignRef('form'		, $form);

		parent::display($tpl);
	}
}
?>

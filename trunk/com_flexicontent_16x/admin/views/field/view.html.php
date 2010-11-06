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
		JToolBarHelper::apply();
		JToolBarHelper::save();
		JToolBarHelper::custom( 'saveandnew', 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );
		JToolBarHelper::cancel();

		//Load pane behavior
		jimport('joomla.html.pane');
		//Import File system
		jimport('joomla.filesystem.file');

		//Get data from the model
		$model				= & $this->getModel();
		$form				= $this->get('Form');
		$types				= & $this->get( 'Typeslist' );
		$typesselected		= & $this->get( 'Typesselected' );
		JHTML::_('behavior.tooltip');

		//build selectlists
		$lists = array();
		//build type select list
		$lists['tid'] 			= flexicontent_html::buildtypesselect($types, 'tid[]', $typesselected, false, 'multiple="multiple" size="6"');
		//build field_type list
		if ($form->getValue("iscore") == 1) { $class = 'disabled="disabled"'; } else {
			$class = '';
			$document->addScriptDeclaration("
					window.addEvent('domready', function() {
						$$('#jform_field_type').addEvent('change', function(ev) {
							$('fieldspecificproperties').set('html', '<p class=\"centerimg\"><img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" align=\"center\"></p>');
							var ajaxoptions ={
								method:'get',
								onComplete:function(response) {
									var JTooltips = new Tips($$('.hasTip'), { maxTitleChars: 50, fixed: false});									
								},
								update:$('fieldspecificproperties')
							};
							new Request.HTML({
								url: 'index.php?option=com_flexicontent&controller=fields&task=getfieldspecificproperties&cid=".$form->getValue("id")."&field_type='+this.value+'&format=raw',
								method: 'get',
								update: $('fieldspecificproperties'),
								evalScripts: false,
								onComplete:function(response) {
									var JTooltips = new Tips($$('.hasTip'), { maxTitleChars: 50, fixed: false});									
								}
							}).send();
						});
					});
				");
			
		}
		//$lists['field_type'] 	= flexicontent_html::buildfieldtypeslist('field_type', $class, $row->field_type);
		//build access level list
		/*if (FLEXI_ACCESS) {
			$lists['access']	= FAccess::TabGmaccess( $row, 'field', 1, 0, 0, 0, 0, 0, 0, 0, 0 );
		} else {
			$lists['access'] 	= JHTML::_('list.accesslevel', $row );
		}*/

		// Create the form
		/*$pluginpath = JPATH_PLUGINS.DS.'flexicontent_fields'.DS.$form->getValue("field_type").DS.$form->getValue("field_type").'.xml';
		
		if (JFile::exists( $pluginpath )) {
			$aform = JForm::getInstance('com_flexicontent.field.'.$form->getValue('field_type'), $pluginpath, array('control' => 'jform', 'load_data' => true), false);
		}else{
			$pluginpath = JPATH_PLUGINS.DS.'flexicontent_fields'.DS.'core'.DS.'core.xml';
			$aform = JForm::getInstance('com_flexicontent.field.'.$global_field_types[0]->value, $pluginpath, array('control' => 'jform', 'load_data' => true), false);
		}
		echo $pluginpath."<br />";
		var_dump($aform);*/
		
		/*if($attribs = $form->getValue("attribs")) {
			$arrays = explode("\n", $attribs);
			$params = new JObject();
			foreach($arrays as $a) {
				$a = explode("=", $a);
				$params->$a[0] = @$a[1];
			}
			$form->bind($params);
			unset($params);
		}*/

		// fail if checked out not by 'me'
		if ($form->getValue("id")) {
			if ($model->isCheckedOut( $user->get('id') )) {
				JError::raiseWarning( 'SOME_ERROR_CODE', $row->name.' '.JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
				$mainframe->redirect( 'index.php?option=com_flexicontent&view=fields' );
			}
		}

		//clean data
		JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES );

		//assign data to template
		$this->assignRef('document'      , $document);
		$this->assignRef('row'      	, $row);
		$this->assignRef('lists'      	, $lists);
//		$this->assignRef('tmpls'      	, $tmpls);
		//$this->assignRef('aform'	, $aform);
		$this->assignRef('form'		, $form);

		parent::display($tpl);
	}
}
?>

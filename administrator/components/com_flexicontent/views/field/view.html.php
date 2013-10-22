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
 * View class for the FLEXIcontent field screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewField extends JViewLegacy
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
		FLEXI_J30GE ? JHtml::_('behavior.framework', true) : JHTML::_('behavior.mootools');
		JHTML::_('behavior.tooltip');
		$document->addScript(JURI::root().'components/com_flexicontent/assets/js/admin.js');
		$document->addScript(JURI::root().'components/com_flexicontent/assets/js/validate.js');
		
		//Load pane behavior
		jimport('joomla.html.pane');
		//Import File system
		jimport('joomla.filesystem.file');

		//Get data from the model
		$model  = $this->getModel();
		$row    = $this->get( 'Field' );
		if (FLEXI_J16GE) {
			$form = $this->get('Form');
		} else {
			$types				= $this->get( 'Typeslist' );
			$typesselected= $this->get( 'Typesselected' );
		}
		
		//create the toolbar
		if ( $row->id ) {
			JToolBarHelper::title( JText::_( 'FLEXI_EDIT_FIELD' ), 'fieldedit' );
		} else {
			JToolBarHelper::title( JText::_( 'FLEXI_ADD_FIELD' ), 'fieldadd' );
		}
		
		$ctrl = FLEXI_J16GE ? 'fields.' : '';
		JToolBarHelper::apply( $ctrl.'apply' );
		JToolBarHelper::save( $ctrl.'save' );
		JToolBarHelper::custom( $ctrl.'saveandnew', 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );
		JToolBarHelper::cancel( $ctrl.'cancel' );
		
		// Import Joomla plugin that implements the type of current flexi field
		JPluginHelper::importPlugin('flexicontent_fields', ($row->iscore ? 'core' : $row->field_type) );
		
		// load plugin's english language file then override with current language file
		$extension_name = 'plg_flexicontent_fields_'. ($row->iscore ? 'core' : $row->field_type);
		JFactory::getLanguage()->load($extension_name, JPATH_ADMINISTRATOR, 'en-GB', true);
		JFactory::getLanguage()->load($extension_name, JPATH_ADMINISTRATOR, null, true);
		
		//check which properties are supported by current field
		$ft_support = FlexicontentFields::getPropertySupport($row->field_type, $row->iscore);
		
		$supportsearch          = $ft_support->supportsearch;
		$supportadvsearch       = $ft_support->supportadvsearch;
		$supportfilter          = $ft_support->supportfilter;
		$supportadvfilter       = $ft_support->supportadvfilter;
		$supportuntranslatable  = $ft_support->supportuntranslatable;
		$supportvalueseditable  = $ft_support->supportvalueseditable;
		$supportformhidden      = $ft_support->supportformhidden;
		$supportedithelp        = $ft_support->supportedithelp;
		
		
		//build selectlists, (for J1.6+ most of these are defined via XML file and custom form field classes)
		$lists = array();
		
		$formhidden[] = JHTML::_('select.option',  0, JText::_( 'FLEXI_NO' ) );
		$formhidden[] = JHTML::_('select.option',  1, JText::_( 'FLEXI_FRONTEND' ) );
		$formhidden[] = JHTML::_('select.option',  2, JText::_( 'FLEXI_BACKEND' ) );
		$formhidden[] = JHTML::_('select.option',  3, JText::_( 'FLEXI_BOTH' ) );
		$formhidden_fieldname = FLEXI_J16GE ? 'jform[formhidden]' : 'formhidden';
		$lists['formhidden'] = JHTML::_('select.radiolist',   $formhidden, $formhidden_fieldname, '', 'value', 'text', $row->formhidden );
		
		if (FLEXI_ACCESS) {
			$valueseditable[] = JHTML::_('select.option',  0, JText::_( 'FLEXI_ANY_EDITOR' ) );
			$valueseditable[] = JHTML::_('select.option',  1, JText::_( 'FLEXI_USE_ACL_PERMISSION' ) );
			$valueseditable_fieldname = FLEXI_J16GE ? 'jform[valueseditable]' : 'valueseditable';
			$lists['valueseditable'] = JHTML::_('select.radiolist',   $valueseditable, $valueseditable_fieldname, '', 'value', 'text', $row->valueseditable );
		}
		
		$edithelp[] = JHTML::_('select.option',  0, JText::_( 'FLEXI_EDIT_HELP_NONE' ) );
		$edithelp[] = JHTML::_('select.option',  1, JText::_( 'FLEXI_EDIT_HELP_LABEL_TOOLTIP' ) );
		$edithelp[] = JHTML::_('select.option',  2, JText::_( 'FLEXI_EDIT_HELP_LABEL_TOOLTIP_WICON' ) );
		$edithelp[] = JHTML::_('select.option',  3, JText::_( 'FLEXI_EDIT_HELP_INLINE' ) );
		$edithelp_fieldname = FLEXI_J16GE ? 'jform[edithelp]' : 'edithelp';
		$lists['edithelp'] = JHTML::_('select.radiolist', $edithelp, $edithelp_fieldname, '', 'value', 'text', $row->edithelp );
		
		//build type select list
		$lists['tid'] 			= flexicontent_html::buildtypesselect($types, 'tid[]', $typesselected, false, 'multiple="multiple" size="6"');

		// build the html select list for ordering
		$query = 'SELECT ordering AS value, label AS text'
		. ' FROM #__flexicontent_fields'
		. ' WHERE published >= 0'
		. ' ORDER BY ordering'
		;
		$row->ordering = @$row->ordering;
		if($row->id)
			$lists['ordering'] 			= JHTML::_('list.specificordering',  $row, $row->id, $query );
		else
			$lists['ordering'] 			= JHTML::_('list.specificordering',  $row, '', $query );
		
		
		//build field_type list
		if ($row->iscore == 1) { $class = 'disabled="disabled"'; } else {
			$class = '';
			$document->addScriptDeclaration("
					window.addEvent('domready', function() {
						$$('#field_type').addEvent('change', function(ev) {
							$('fieldspecificproperties').setHTML('<p class=\"centerimg\"><img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" align=\"center\"></p>');
							var ajaxoptions ={
								method: 'get',
								update: $('fieldspecificproperties'),
								onComplete:function(response) {
									var JTooltips = new Tips($$('.hasTip'), { maxTitleChars: 50, fixed: false});									
									$('field_typename').innerHTML = $('field_type').value;
								}
							};
							var ajaxobj = new Ajax(
								'index.php?option=com_flexicontent&controller=fields&task=getfieldspecificproperties&cid=".$row->id."&field_type='+this.value+'&format=raw',
								ajaxoptions);
							ajaxobj.request.delay(300, ajaxobj);
						});
					});
				");
		}
		$lists['field_type'] 	= flexicontent_html::buildfieldtypeslist('field_type', $class, $row->field_type);
		//build access level list
		if (FLEXI_ACCESS) {
			$lang = & JFactory::getLanguage();
			$lang->_strings['FLEXIACCESS_PADD'] = 'Edit-Value';
			$lists['access']	= FAccess::TabGmaccess( $row, 'field', 1, 1, 0, 1, 0, 1, 0, 1, 1 );
		} else {
			$lists['access'] 	= JHTML::_('list.accesslevel', $row );
		}

		// Create the form
		$pluginpath = JPATH_PLUGINS.DS.'flexicontent_fields'.DS.$row->field_type.'.xml';
		if (JFile::exists( $pluginpath )) {
			$form = new JParameter('', $pluginpath);
		} else {
			$form = new JParameter('', JPATH_PLUGINS.DS.'flexicontent_fields'.DS.'core.xml');
		}
		// Special and Core Groups
		$form->loadINI($row->attribs);

		// fail if checked out not by 'me'
		if ($row->id) {
			if ($model->isCheckedOut( $user->get('id') )) {
				JError::raiseWarning( 'SOME_ERROR_CODE', $row->name.' '.JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
				$app->redirect( 'index.php?option=com_flexicontent&view=fields' );
			}
		}
		
		//clean data
		JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES );
		
		// assign permissions for J2.5
		if (FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$this->assignRef('permission' , $permission);
		}
		//assign data to template
		$this->assignRef('document'   , $document);
		$this->assignRef('row'        , $row);
		$this->assignRef('lists'      , $lists);
		$this->assignRef('form'       , $form);
		$this->assignRef('supportsearch'           , $supportsearch);
		$this->assignRef('supportadvsearch'        , $supportadvsearch);
		$this->assignRef('supportfilter'           , $supportfilter);
		$this->assignRef('supportadvfilter'        , $supportadvfilter);
		$this->assignRef('supportuntranslatable'   , $supportuntranslatable);
		$this->assignRef('supportvalueseditable'   , $supportvalueseditable);
		$this->assignRef('supportformhidden'       , $supportformhidden);
		$this->assignRef('supportedithelp'         , $supportedithelp);
		
		parent::display($tpl);
	}
}
?>

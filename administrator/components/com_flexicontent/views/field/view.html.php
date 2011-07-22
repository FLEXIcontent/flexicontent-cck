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
 * View class for the FLEXIcontent field screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewField extends JView {

	function display($tpl = null)
	{
		global $mainframe;

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
		$row     			= & $this->get( 'Field' );
		$types				= & $this->get( 'Typeslist' );
		$typesselected		= & $this->get( 'Typesselected' );
		JHTML::_('behavior.tooltip');
		
		//build selectlists
		$lists = array();
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
								method:'get',
								onComplete:function(response) {
									var JTooltips = new Tips($$('.hasTip'), { maxTitleChars: 50, fixed: false});									
								},
								update:$('fieldspecificproperties')
							};
							var ajaxobj = new Ajax(
								'index.php?option=com_flexicontent&controller=fields&task=getfieldspecificproperties&cid=".$row->id."&field_type='+this.value+'&format=raw',
								ajaxoptions);
							ajaxobj.request.delay(300, ajaxobj);
						});
					});
				");
			
		}
		// Import field to execute its constructor, e.g. needed for loading language file etc
		JPluginHelper::importPlugin('flexicontent_fields', $row->field_type);
		$lists['field_type'] 	= flexicontent_html::buildfieldtypeslist('field_type', $class, $row->field_type);
		//build access level list
		if (FLEXI_ACCESS) {
			$lists['access']	= FAccess::TabGmaccess( $row, 'field', 1, 0, 0, 0, 0, 0, 0, 0, 0 );
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
		$this->assignRef('form'			, $form);

		parent::display($tpl);
	}
}
?>
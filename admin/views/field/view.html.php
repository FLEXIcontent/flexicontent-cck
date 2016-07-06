<?php
/**
 * @version 1.5 stable $Id: view.html.php 1870 2014-03-13 00:22:57Z ggppdk $
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
		$option   = JRequest::getVar('option');
		$document	= JFactory::getDocument();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$user     = JFactory::getUser();
		
		//add css to document
		$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH);
		$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH);
		
		// Add JS frameworks
		flexicontent_html::loadFramework('select2');
		flexicontent_html::loadFramework('flexi-lib-form');
		
		// Add js function to overload the joomla submitform validation
		JHTML::_('behavior.formvalidation');  // load default validation JS to make sure it is overriden
		$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
		$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);
		
		//Import File system
		jimport('joomla.filesystem.file');

		//Get data from the model
		$model  = $this->getModel();
		$row    = $this->get('Field');
		$form   = $this->get('Form');
		$types				= $this->get( 'Typeslist' );
		$typesselected= $this->get( 'Typesselected' );
		
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
		$extfolder = 'flexicontent_fields';
		$extname = $row->iscore ? 'core' : $row->field_type;
		JPluginHelper::importPlugin('flexicontent_fields', ($row->iscore ? 'core' : $row->field_type) );
		
		// Create class name of the plugin and then create a plugin instance
		$classname = 'plg'. ucfirst($extfolder).$extname;
		
		// Check max allowed version
		if ( property_exists ($classname, 'prior_to_version') ) {
			// Set a system message with warning of failed PHP limits
			$prior_to_version = $app->getUserStateFromRequest( $option.'.flexicontent.prior_to_version_'.$row->field_type,	'prior_to_version_'.$row->field_type,	0, 'int' );
			$app->setUserState( $option.'.flexicontent.prior_to_version_'.$row->field_type, $prior_to_version+1 );
			if ($prior_to_version < 2)
			{
				$close_btn = FLEXI_J30GE ? '<a class="close" data-dismiss="alert">&#215;</a>' : '<a class="fc-close" onclick="this.parentNode.parentNode.removeChild(this.parentNode);">&#215;</a>';
				
				$manifest_path = JPATH_ADMINISTRATOR .DS. 'components' .DS. 'com_flexicontent' .DS. 'manifest.xml';
				$com_xml = JApplicationHelper::parseXMLInstallFile( $manifest_path );
				$ver_exceeded = version_compare( str_replace(' ', '.', $com_xml['version']), str_replace(' ', '.', $classname::$prior_to_version), '>=');
				if ($ver_exceeded) echo '
					<span class="fc-note fc-mssg-inline">
						'.$close_btn.'
						Warning: installed version of Field: \'<b>'.$extname.'</b>\' was given to be free for FLEXIcontent versions prior to: v'.$classname::$prior_to_version.' <br/> It may or may not work properly in later versions
					</span>';
				else echo '
					<span class="fc-info fc-mssg-inline">
						'.$close_btn.'
						Note: installed version of Field: \'<b>'.$extname.'</b>\' is given free for FLEXIcontent versions prior to: v'.$classname::$prior_to_version.', &nbsp; &nbsp; nevertheless it will continue to function after FLEXIcontent is upgraded.
					</span>';
			}
		}
		
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
		
		//build field_type list
		if (!$row->field_type) $row->field_type = 'text';
		
		$_attribs = ' class="use_select2_lib fc_skip_highlight" ';
		if ($row->iscore == 1) {
			$_attribs .= ' disabled="disabled" ';
		}
		else {
			$_field_id = 'jform_field_type';
			$_row_id = $form->getValue("id");
			$_ctrl_task = 'task=fields.getfieldspecificproperties';
			$document->addScriptDeclaration("
				jQuery(document).ready(function() {
					jQuery('#".$_field_id."').on('change', function() {
						jQuery('#fieldspecificproperties').html('<p class=\"centerimg\"><img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" align=\"center\"></p>');
						jQuery.ajax({
							type: \"GET\",
							url: 'index.php?option=com_flexicontent&".$_ctrl_task."&cid=".$_row_id."&field_type='+this.value+'&format=raw',
							success: function(str) {
								jQuery('#fieldspecificproperties').html(str);
								jQuery('.hasTooltip').tooltip({'html': true,'container': jQuery('#fieldspecificproperties')});

								tabberAutomatic(tabberOptions, 'fieldspecificproperties');
								fc_bindFormDependencies('#fieldspecificproperties', 0, '');
								fc_bootstrapAttach('#fieldspecificproperties');
								if (typeof(fcrecord_attach_sortable) == 'function')
								{
									fcrecord_attach_sortable('#fieldspecificproperties');
								}
								jQuery('#field_typename').html(jQuery('#".$_field_id."').val());
							}
						});
					});
				});
			");
		}
		
		//build field select list
		$fieldtypes = flexicontent_db::getFieldTypes($_grouped=true, $_usage=false, $_published=true);
		$fftypes = array();
		foreach ($fieldtypes as $field_group => $ft_types) {
			$fftypes[] = $field_group;
			foreach ($ft_types as $field_type => $ftdata) {
				$fftypes[] = array('value'=>$ftdata->field_type, 'text'=>$ftdata->friendly);
			}
			$fftypes[] = '';
		}
		$lists['field_type'] = flexicontent_html::buildfieldtypeslist($fftypes, 'jform[field_type]', $row->field_type, ($_grouped ? 1 : 0), $_attribs);
		
		//build type select list
		$attribs  = 'class="use_select2_lib" multiple="multiple" size="6"';
		$attribs .= $row->iscore ? ' disabled="disabled"' : '';
		$types_fieldname = FLEXI_J16GE ? 'jform[tid][]' : 'tid[]';
		$lists['tid'] = flexicontent_html::buildtypesselect($types, $types_fieldname, $typesselected, false, $attribs);
		
		
		// **************************************************************************
		// Create fields for J1.5 (J2.5+ uses JForm XML file for most of form fields)
		// **************************************************************************
		if (!FLEXI_J16GE)
		{
			// Create the parameter 's form object parsing the file XML
			$pluginpath = JPATH_PLUGINS.DS.'flexicontent_fields'.DS.$row->field_type.'.xml';
			if (JFile::exists( $pluginpath )) {
				$form = new JParameter('', $pluginpath);
			} else {
				$form = new JParameter('', JPATH_PLUGINS.DS.'flexicontent_fields'.DS.'core.xml');
			}
			// Special and Core Groups
			$form->loadINI($row->attribs);
		}
		

		// fail if checked out not by 'me'
		if ($row->id) {
			if ($model->isCheckedOut( $user->get('id') )) {
				JError::raiseWarning( 'SOME_ERROR_CODE', $row->name.' '.JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
				$app->redirect( 'index.php?option=com_flexicontent&view=fields' );
			}
		}
		
		// Encode (UTF-8 charset) HTML entities form data so that they can be set as form field values
		//JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES, $exclude_keys = '' );    // we use JForm to output fields so this is redundant
		
		// assign permissions for J2.5
		$permission = FlexicontentHelperPerm::getPerm();
		$this->assignRef('permission' , $permission);
		
		//assign data to template
		$this->assignRef('document'   , $document);
		$this->assignRef('row'        , $row);
		$this->assignRef('lists'      , $lists);
		$this->assignRef('form'       , $form);
		$this->assignRef('typesselected'	, $typesselected);
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

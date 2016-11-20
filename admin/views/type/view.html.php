<?php
/**
 * @version 1.5 stable $Id: view.html.php 1608 2012-12-25 04:31:58Z ggppdk $
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
		$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH);
		if (JFactory::getLanguage()->isRtl())
		{
			$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', FLEXI_VHASH);
		}
		$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH);
		
		// Add JS frameworks
		flexicontent_html::loadFramework('select2');
		flexicontent_html::loadFramework('flexi-lib-form');
		
		// Add js function to overload the joomla submitform validation
		JHTML::_('behavior.formvalidation');  // load default validation JS to make sure it is overriden
		$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
		$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);
		
		//Get data from the model
		$model  = $this->getModel();
		$row    = $this->get( FLEXI_J16GE ? 'Item' : 'Type' );
		$form   = $this->get('Form');
		
		// Get type parameters
		$tparams = new JRegistry($row->attribs);
		
		// Get item layouts
		$themes = flexicontent_tmpl::getTemplates();
		$tmpls  = $themes->items;
		
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
		
		// Load language file of currently selected template
		$_ilayout = @ $row->attribs['ilayout'];
		if ($_ilayout) FLEXIUtilities::loadTemplateLanguageFile( $_ilayout );
		
		// Create JForm for the layout and apply Layout parameters values into the fields
		foreach ($tmpls as $tmpl)
		{
			if ($tmpl->name != $_ilayout) continue;
			
			$jform = new JForm('com_flexicontent.template.item', array('control' => 'jform', 'load_data' => false));
			$jform->load($tmpl->params);
			$tmpl->params = $jform;
			foreach ($tmpl->params->getGroup('attribs') as $field)
			{
				$fieldname = $field->fieldname;
				$value = $tparams->get($fieldname);
				if (strlen($value)) $tmpl->params->setValue($fieldname, 'attribs', $value);
			}
		}
		
		// check access level exists
		$level_name = flexicontent_html::userlevel(null, $row->access, null, null, null, $_createlist = false);
		if (empty($level_name))
		{
			JFactory::getApplication()->enqueueMessage(JText::sprintf('FLEXI_ABOUT_INVALID_ACCESS_LEVEL_PLEASE_SAVE_NEW', $row->access, 'Public'), 'warning');
			$document->addScriptDeclaration("jQuery(document).ready(function() { jQuery('#jform_access').val(1).trigger('change'); });");
		}
		
		// assign permissions
		$permission = FlexicontentHelperPerm::getPerm();
		$this->assignRef('permission'  , $permission);
		
		//assign data to template
		$this->assignRef('document'   , $document);
		$this->assignRef('row'        , $row);
		$this->assignRef('form'       , $form);
		$this->assignRef('tmpls'      , $tmpls);
		$this->assignRef('cparams'    , $cparams);
		
		parent::display($tpl);
	}
	
	
	
	/**
	 * Method to diplay field showing inherited value
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	function getInheritedFieldDisplay($field, $params)
	{
		$_v = $params->get($field->fieldname);
		
		if ($_v==='' || $_v===null)
			return $field->input;
		else if ($field->getAttribute('type')=='radio' || $field->getAttribute('type')=='fcradio' || ($field->getAttribute('type')=='multilist' && $field->getAttribute('subtype')=='radio'))
		{
			$_v = htmlspecialchars( $_v, ENT_COMPAT, 'UTF-8' );
			return str_replace(
				'value="'.$_v.'"',
				'value="'.$_v.'" class="fc-inherited-value" ',
				$field->input);
		}
		else if ($field->getAttribute('type')=='fccheckbox' && is_array($_v))
		{
			$_input = $field->input;
			foreach ($_v as $v)
			{
				$v = htmlspecialchars( $v, ENT_COMPAT, 'UTF-8' );
				$_input = str_replace(
					'value="'.$v.'"',
					'value="'.$v.'" class="fc-inherited-value" ',
					$_input);
			}
			return $_input;
		}
		else if ($field->getAttribute('type')=='text')
		{
			$_v = htmlspecialchars( preg_replace('/[\n\r]/', ' ', $_v), ENT_COMPAT, 'UTF-8' );
			return str_replace(
				'<input ',
				'<input placeholder="'.$_v.'" ',
				$field->input);
		}
		else if ($field->getAttribute('type')=='textarea')
		{
			$_v = htmlspecialchars(preg_replace('/[\n\r]/', ' ', $_v), ENT_COMPAT, 'UTF-8' );
			return str_replace('<textarea ', '<textarea placeholder="'.$_v.'" ', $field->input);
		}
		else if ( method_exists($field, 'setInherited') )
		{
			$field->setInherited($_v);
			return $field->input;
		}
		else
			return $field->input;
	}
}
?>
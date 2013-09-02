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
jimport('joomla.application.component.helper' );

/**
 * View class for the FLEXIcontent categories screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewImport extends JViewLegacy
{

	function display( $tpl = null )
	{
		global $globalcats;
		$mainframe = JFactory::getApplication();

		//initialise variables
		$user     = JFactory::getUser();
		$db       = JFactory::getDBO();
		$document = JFactory::getDocument();
		$option   = JRequest::getCmd( 'option' );
		$context  = 'com_flexicontent';
		$task     = JRequest::getVar('task', '');
		$cid      = JRequest::getVar('cid', array());
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		
		$this->setLayout('import');

		//initialise variables
		$user 		= JFactory::getUser();
		$document	= JFactory::getDocument();
		$context	= 'com_flexicontent';

		JHTML::_('behavior.tooltip');

		//add css to document
		$document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/flexicontentbackend.css');
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j25.css');
		else                  $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j15.css');

		//get vars
		$filter_order		= $mainframe->getUserStateFromRequest( $context.'.items.filter_order', 		'filter_order', 	'', 	'cmd' );
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( $context.'.items.filter_order_Dir',	'filter_order_Dir',	'', 		'word' );

		// Get User's Global Permissions
		$perms = FlexicontentHelperPerm::getPerm();

		// Create Submenu (and also check access to current view)
		FLEXISubmenu('CanImport');

		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_COPYMOVE_ITEM' ), 'import' );
		$ctrl_task = FLEXI_J16GE ? 'items.importcsv' : 'importcsv';
		JToolBarHelper::custom( $ctrl_task, 'import.png', 'import.png', 'FLEXI_IMPORT', $list_check = false );  // list_check will check that at least one row is checked in listing-like views
		//JToolBarHelper::Back();
		if ($perms->CanConfig) {
			JToolBarHelper::divider(); JToolBarHelper::spacer();
			JToolBarHelper::preferences('com_flexicontent', '550', '850', 'Configuration');
		}

		$query = 'SELECT id, name'
			. ' FROM #__flexicontent_types'
			. ' WHERE published = 1'
			. ' ORDER BY name ASC'
			;
		$db->setQuery($query);
		$types = $db->loadObjectList();

		$lists['type_id'] = flexicontent_html::buildtypesselect($types, 'type_id', '', true, 'class="fcfield_selectval" size="1"', 'type_id');
		
		$categories = $globalcats;
		$actions_allowed = array('core.create');  // Creating categorories tree for item assignment, we use the 'create' privelege
		
		// build the secondary categories select list
		$class  = "fcfield_selectmulval";
		$attribs = 'multiple="multiple" size="10" class="'.$class.'"';
		$fieldname = FLEXI_J16GE ? 'seccats[]' : 'seccats[]';
		$lists['seccats'] = flexicontent_cats::buildcatselect($categories, $fieldname, '', false, $attribs, false, true,
			$actions_allowed, $require_all=true);
		
		// build the main category select list
		$attribs = 'class="fcfield_selectval"';
		$fieldname = FLEXI_J16GE ? 'maincat' : 'maincat';
		$lists['maincat'] = flexicontent_cats::buildcatselect($categories, $fieldname, '', 2, $attribs, false, true, $actions_allowed);
		
		/*
			// build the main category select list
			$lists['maincat'] = flexicontent_cats::buildcatselect($categories, 'maincat', '', 0, 'class="inputbox" size="10"', false, false);
			// build the secondary categories select list
			$lists['seccats'] = flexicontent_cats::buildcatselect($categories, 'seccats[]', '', 0, 'class="inputbox" multiple="multiple" size="10"', false, false);
		*/
		
		//build languages list
		// Retrieve author configuration
		$db->setQuery('SELECT author_basicparams FROM #__flexicontent_authors_ext WHERE user_id = ' . $user->id);
		if ( $authorparams = $db->loadResult() )
			$authorparams = FLEXI_J16GE ? new JRegistry($authorparams) : new JParameter($authorparams);

		$allowed_langs = !$authorparams ? null : $authorparams->get('langs_allowed',null);
		$allowed_langs = !$allowed_langs ? null : FLEXIUtilities::paramToArray($allowed_langs);

		// We will not use the default getInput() function of J1.6+ since we want to create a radio selection field with flags
		// we could also create a new class and override getInput() method but maybe this is an overkill, we may do it in the future
		if (FLEXI_FISH || FLEXI_J16GE) {
			$default_lang = $cparams->get('import_lang', '*');
			$lists['languages'] = flexicontent_html::buildlanguageslist('language', '', $default_lang, 6, $allowed_langs, $default_lang);
		} else {
			$default_lang = flexicontent_html::getSiteDefaultLang();
			$languages[] = JHTML::_('select.option', $default_lang, JText::_( 'Default' ).' ('.flexicontent_html::getSiteDefaultLang().')' );
			$lists['languages'] = JHTML::_('select.radiolist', $languages, 'language', $class='', 'value', 'text', $default_lang );
		}

		$default_state= $cparams->get('import_state', 1);
		$lists['states'] = flexicontent_html::buildstateslist('state', '', $default_state, 2);
		
		
		// Ignore warnings because component may not be installed
		$warnHandlers = JERROR::getErrorHandling( E_WARNING );
		JERROR::setErrorHandling( E_WARNING, 'ignore' );
		
		if (FLEXI_J30GE) {
			// J3.0+ adds an warning about component not installed, commented out ... till time ...
			$fleximport_comp_enabled = false; //JComponentHelper::isEnabled('com_fleximport');
		} else {
			$fleximport_comp = JComponentHelper::getComponent('com_fleximport', true);
			$fleximport_comp_enabled = $fleximport_comp && $fleximport_comp->enabled;
		}
		
		// Reset the warning handler(s)
		foreach( $warnHandlers as $mode )  JERROR::setErrorHandling( E_WARNING, $mode );
		
		if ($fleximport_comp_enabled) {
			$fleximport = JText::sprintf('FLEXI_FLEXIMPORT_INSTALLED',JText::_('FLEXI_FLEXIMPORT_INFOS'));
		} else {
			$fleximport = JText::sprintf('FLEXI_FLEXIMPORT_NOT_INSTALLED',JText::_('FLEXI_FLEXIMPORT_INFOS'));
		}
		
		
		// ********************************************************************************
		// Get field names (from the header line (row 0), and remove it form the data array
		// ********************************************************************************
		$file_field_types_list = '"image","file"';
		$q = 'SELECT id, name, label, field_type FROM #__flexicontent_fields AS fi'
			//.' JOIN #__flexicontent_fields_type_relations AS ftrel ON ftrel.field_id = fi.id AND ftrel.type_id='.$type_id;
			.' WHERE fi.field_type IN ('. $file_field_types_list .')';
		$db->setQuery($q);
		$file_fields = $db->loadObjectList('name');
		
		//assign data to template
		$this->assignRef('lists'   	, $lists);
		$this->assignRef('cid'     	, $cid);
		$this->assignRef('user'			, $user);
		$this->assignRef('fleximport', $fleximport);
		$this->assignRef('cparams', $cparams);
		$this->assignRef('file_fields', $file_fields);

		parent::display($tpl);
	}
}
?>

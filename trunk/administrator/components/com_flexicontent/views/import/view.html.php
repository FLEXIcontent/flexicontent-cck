<?php
/**
 * @version 1.5 stable $Id: view.html.php 1063 2011-12-22 04:15:04Z ggppdk $
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
 * View class for the FLEXIcontent categories screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewImport extends JView {

	function display($tpl = null)
	{
		global $mainframe, $globalcats;

		//initialise variables
		$user 		= & JFactory::getUser();
		$db  		= & JFactory::getDBO();
		$document	= & JFactory::getDocument();
		$option		= JRequest::getCmd( 'option' );
		$context	= 'com_flexicontent';
		$task		= JRequest::getVar('task', '');
		$cid		= JRequest::getVar('cid', array());
		$extlimit	= JRequest::getInt('extlimit', 100);
		
		$this->setLayout('import');

		//initialise variables
		$user 		= & JFactory::getUser();
		$document	= & JFactory::getDocument();
		$context	= 'com_flexicontent';
		
		JHTML::_('behavior.tooltip');

		//add css to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');

		//get vars
		$filter_order		= $mainframe->getUserStateFromRequest( $context.'.items.filter_order', 		'filter_order', 	'', 	'cmd' );
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( $context.'.items.filter_order_Dir',	'filter_order_Dir',	'', 		'word' );

		FLEXISubmenu('notvariable');

		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_COPYMOVE_ITEM' ), 'import' );
		JToolBarHelper::save('importcsv');
		JToolBarHelper::cancel();
		
		$db = &JFactory::getDBO();
		$query = 'SELECT id, name'
			. ' FROM #__flexicontent_types'
			. ' WHERE published = 1'
			. ' ORDER BY name ASC'
			;
		$db->setQuery($query);
		$types = $db->loadObjectList();
		
		$lists['type_id'] = flexicontent_html::buildtypesselect($types, 'type_id', '', true, 'class="inputbox" size="1"');

		$categories = $globalcats;
		
		// build the main category select list
		$lists['maincat'] = flexicontent_cats::buildcatselect($categories, 'maincat', '', 0, 'class="inputbox" size="10"', false, false);
		// build the secondary categories select list
		$lists['seccats'] = flexicontent_cats::buildcatselect($categories, 'seccats[]', '', 0, 'class="inputbox" multiple="multiple" size="10"', false, false);

		//assign data to template
		$this->assignRef('lists'      	, $lists);
		$this->assignRef('cid'      	, $cid);
		$this->assignRef('user'			, $user);

		parent::display($tpl);
	}
}
?>

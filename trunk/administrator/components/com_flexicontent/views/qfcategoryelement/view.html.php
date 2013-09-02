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
 * View class for the tagelement screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewQfcategoryelement extends JViewLegacy
{
	function display($tpl = null)
	{
		//initialise variables
		global $globalcats;
		$app    = JFactory::getApplication();
		$option = JRequest::getVar('option');
		$view   = JRequest::getVar('view');
		$user = JFactory::getUser();
		$db   = JFactory::getDBO();
		$document	= JFactory::getDocument();
		$template = $app->getTemplate();
		
		JHTML::_('behavior.tooltip');
		JHTML::_('behavior.modal');

		//get vars
		$order_property = !FLEXI_J16GE ? 'c.ordering' : 'c.lft';
		$filter_order     = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order',     'filter_order',     $order_property, 'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order_Dir', 'filter_order_Dir', '', 'word' );
		$filter_state     = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_state',     'filter_state',     '', 'string' );
		$filter_cats      = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_cats',      'filter_cats',			 '', 'int' );
		$filter_level     = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_level',     'filter_level',     '', 'string' );
		$filter_access    = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_access',    'filter_access',    '', 'string' );
		if (FLEXI_J16GE) {
			$filter_language	= $app->getUserStateFromRequest( $option.'.'.$view.'.filter_language',  'filter_language',  '', 'string' );
		}
		$search  = $app->getUserStateFromRequest( $option.'.'.$view.'.search', 			'search', 			'', 'string' );
		$search  = FLEXI_J16GE ? $db->escape( trim(JString::strtolower( $search ) ) ) : $db->getEscaped( trim(JString::strtolower( $search ) ) );

		// Prepare the document: add css files, etc
		$document->setTitle(JText::_( 'FLEXI_SELECTITEM' ));
		$document->addStyleSheet(JURI::root().'administrator/components/com_flexicontent/assets/css/flexicontentbackend.css');
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::root().'administrator/components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::root().'administrator/components/com_flexicontent/assets/css/j25.css');
		else                  $document->addStyleSheet(JURI::root().'administrator/components/com_flexicontent/assets/css/j15.css');
		$document->addStyleSheet(JURI::root().'administrator/templates/'.$template.(FLEXI_J16GE ? '/css/template.css': '/css/general.css'));

		//Get data from the model
		if (FLEXI_J16GE) {
			$rows = $this->get( 'Items');
		} else {
			$rows = $this->get( 'Data');
		}
		
		// Parse configuration for every category
   	foreach ($rows as $cat)  $cat->config = FLEXI_J16GE ? new JRegistry($cat->config) : new JParameter($cat->config);
		
		$pagination 	= $this->get( 'Pagination' );
		
		// *******************
		// Create Form Filters
		// *******************
		
		// filter by a category (it's subtree will be displayed)
		$categories = $globalcats;
		$lists['cats'] = flexicontent_cats::buildcatselect($categories, 'filter_cats', $filter_cats, 2, 'class="inputbox" size="1" onchange="this.form.submit();"', $check_published=true, $check_perms=false);
		
		// filter depth level
		$options	= array();
		$options[]	= JHtml::_('select.option', '', JText::_( 'FLEXI_SELECT_MAX_DEPTH' ));
		for($i=1; $i<=10; $i++) $options[]	= JHtml::_('select.option', $i, $i);
		$fieldname =  $elementid = 'filter_level';
		$attribs = ' class="inputbox" onchange="this.form.submit();" ';
		$lists['level']	= JHTML::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $filter_level, $elementid, $translate=true );
		
		// filter publication state
		if (FLEXI_J16GE)
		{
			$options = JHtml::_('jgrid.publishedOptions');
			array_unshift($options, JHtml::_('select.option', '', JText::_('JOPTION_SELECT_PUBLISHED')) );
			$fieldname =  $elementid = 'filter_state';
			$attribs = ' class="inputbox" onchange="Joomla.submitform()" ';
			$lists['state']	= JHTML::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $filter_state, $elementid, $translate=true );
		} else {
			$lists['state']	= JHTML::_('grid.state', $filter_state );
		}
		
		if (FLEXI_J16GE)
		{
			// filter access level
			$options = JHtml::_('access.assetgroups');
			array_unshift($options, JHtml::_('select.option', '', JText::_('JOPTION_SELECT_ACCESS')) );
			$fieldname =  $elementid = 'filter_access';
			$attribs = ' class="inputbox" onchange="Joomla.submitform()" ';
			$lists['access']	= JHTML::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $filter_access, $elementid, $translate=true );
			
			// filter language
			$lists['language'] = flexicontent_html::buildlanguageslist('filter_language', 'class="inputbox" onchange="submitform();"', $filter_language, 2);
		} else {
			// filter access level
			$options = array();
			$options[] = JHtml::_('select.option', '', JText::_('FLEXI_SELECT_ACCESS_LEVEL'));
			$options[] = JHtml::_('select.option', '0', JText::_('Public'));
			$options[] = JHtml::_('select.option', '1', JText::_('Registered'));
			$options[] = JHtml::_('select.option', '2', JText::_('SPECIAL'));
			$fieldname =  $elementid = 'filter_access';
			$attribs = ' class="inputbox" onchange="this.form.submit()" ';
			$lists['access']	= JHTML::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $filter_access, $elementid, $translate=true );
		}
		
		// filter search word
		$lists['search']= $search;
		
		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		$ordering = ($lists['order'] == $order_property) ? $order_property : '';

		//assign data to template
		$this->assignRef('lists'			, $lists);
		$this->assignRef('rows'				, $rows);
		$this->assignRef('ordering'		, $ordering);
		$this->assignRef('pagination'	, $pagination);

		parent::display($tpl);
	}
}
?>
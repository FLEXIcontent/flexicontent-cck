<?php
/**
 * @version 1.5 stable $Id: view.html.php 1577 2012-12-02 15:10:44Z ggppdk $
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
 * View class for the itemelement screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewItemelement extends JViewLegacy
{
	function display($tpl = null)
	{
		global $globalcats;
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		$db   = JFactory::getDBO();
		$option   = JRequest::getVar('option');
		$view     = JRequest::getVar('view');
		$document	= JFactory::getDocument();

		// Get model
		$model = $this->getModel();
		
		JHTML::_('behavior.tooltip');
		JHTML::_('behavior.modal');

		$assocs_id   = JRequest::getInt( 'assocs_id', 0 );
		
		$language    = !$assocs_id ? JRequest::getCmd('language') : $app->getUserStateFromRequest( $option.'.'.$view.'.language', 'language', '', 'string' );
		$type_id     = !$assocs_id ? JRequest::getCmd('type_id') : $app->getUserStateFromRequest( $option.'.'.$view.'.type_id', 'type_id', 0, 'int' );
		$created_by  = !$assocs_id ? JRequest::getCmd('created_by') : $app->getUserStateFromRequest( $option.'.'.$view.'.created_by', 'created_by', 0, 'int' );
		
		$type_data = $model->getTypeData( $assocs_id, $type_id );
		
		if ($assocs_id)
		{
			$assocanytrans = $user->authorise('flexicontent.assocanytrans', 'com_flexicontent');
			if (!$assocanytrans && !$created_by)  $created_by = $user->id;
		}
		
		// get filter values
		$filter_order     = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order', 	  'filter_order', 	 'i.ordering', 'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order_Dir',	'filter_order_Dir',	''				 , 'cmd' );
		
		$filter_state  = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_state',  'filter_state',   '',    'cmd' );
		$filter_cats   = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_cats',   'filter_cats',    0,     'int' );
		$filter_type   = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_type',   'filter_type',    0,     'int' );
		$filter_access = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_access', 'filter_access',  '',    'string' );
		$filter_lang   = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_lang',   'filter_lang',    '',    'cmd' );
		$filter_author = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_author', 'filter_author',  '',    'cmd' );
		
		$search = $app->getUserStateFromRequest( $option.'.'.$view.'.search', 			'search', 			'', 'string' );
		$search = $db->escape( trim(JString::strtolower( $search ) ) );

		// Prepare the document: set title, add css files, etc
		$document->setTitle(JText::_( 'FLEXI_SELECTITEM' ));
		
		if ($app->isSite()) {
			$document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontent.css');
		} else {
			$document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css');
		}
		flexicontent_html::loadFramework('select2');
		
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/j25.css');
		
		// Include backend CSS template CSS file , access to backend folder may not be allowed but ...
		//$template = $app->isSite() ? (!FLEXI_J16GE ? 'khepri' : (FLEXI_J30GE ? 'hathor' : 'bluestork')) : $app->getTemplate();
		//$document->addStyleSheet(JURI::base(true).'/templates/'.$template.(FLEXI_J16GE ? '/css/template.css': '/css/general.css'));
		
		//Get data from the model
		$rows     = $this->get( 'Data');
		$types		= $this->get( 'Typeslist' );
		$authors  = $this->get( 'Authorslist' );
		$langs    = FLEXIUtilities::getLanguages('code');
		$pagination = $this->get( 'Pagination' );
		
		// Ordering active FLAG
		$ordering = ($filter_order == 'i.ordering');
		
		
		// *******************
		// Create Form Filters
		// *******************
		
		// filter search word
		$lists['search']= $search;
		
		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;
		
		// build the categories select list
		$categories = $globalcats;
		$lists['filter_cats'] =  '<label class="label">'.JText::_('FLEXI_CATEGORY').'</label>'.
			flexicontent_cats::buildcatselect($categories, 'filter_cats', $filter_cats, '-'/*2*/, 'class="use_select2_lib fcfilter_be" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', $check_published=true, $check_perms=false);
		
		
		// build type select list
		$lists['filter_type'] = '<label class="label">'.JText::_('FLEXI_TYPE').'</label>'.
			($assocs_id && !empty($type_data) ?
				'<span class="badge badge-info">'.$type_data->name.'</span>' :
				flexicontent_html::buildtypesselect($types, 'filter_type', $filter_type, '-'/*true*/, 'class="use_select2_lib fcfilter_be" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'filter_type')
			);
		
		// build author select list
		$lists['filter_author'] = '<label class="label">'.JText::_('FLEXI_AUTHOR').'</label>'.
			($assocs_id && $created_by ?
				'<span class="badge badge-info">'.JFactory::getUser($created_by)->name.'</span>' :
				flexicontent_html::buildauthorsselect($authors, 'filter_author', $filter_author, '-'/*true*/, 'class="use_select2_lib fcfilter_be" size="3" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"')
			);

		// build publication state filter
		$states[] = JHtml::_('select.option',  '', '-'/*'FLEXI_SELECT_STATE'*/ );
		$states[] = JHtml::_('select.option',  'P', 'FLEXI_PUBLISHED' );
		$states[] = JHtml::_('select.option',  'U', 'FLEXI_UNPUBLISHED' );
		$states[] = JHtml::_('select.option',  'PE','FLEXI_PENDING' );
		$states[] = JHtml::_('select.option',  'OQ','FLEXI_TO_WRITE' );
		$states[] = JHtml::_('select.option',  'IP','FLEXI_IN_PROGRESS' );
		$states[] = JHtml::_('select.option',  'A', 'FLEXI_ARCHIVED' );
		
		$fieldname =  $elementid = 'filter_state';
		$attribs = ' class="use_select2_lib fcfilter_be" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()" ';
		$lists['filter_state'] = '<label class="label">'.JText::_('FLEXI_STATE').'</label>'.
			JHTML::_('select.genericlist', $states, $fieldname, $attribs, 'value', 'text', $filter_state, $elementid
		, $translate=true );
		
		// build access level filter
		$levels = JHtml::_('access.assetgroups');
		array_unshift($levels, JHtml::_('select.option', '', '-'/*'FLEXI_SELECT_ACCESS'*/));
		$fieldname =  $elementid = 'filter_access';
		$attribs = ' class="use_select2_lib fcfilter_be" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()" ';
		$lists['filter_access']	= '<label class="label">'.JText::_('FLEXI_ACCESS').'</label>'.
			JHTML::_('select.genericlist', $levels, $fieldname, $attribs, 'value', 'text', $filter_access, $elementid
		, $translate=true );
		
		// build language filter
		$lists['filter_lang'] = '<label class="label">'.JText::_('FLEXI_LANGUAGE').'</label>'.
			($assocs_id && $language ?
				'<span class="badge badge-info">'.$language.'</span>' :
				flexicontent_html::buildlanguageslist('filter_lang', 'class="use_select2_lib fcfilter_be" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', $filter_lang, '-'/*2*/)
			);
		
		// assign data to template
		$this->assignRef('assocs_id'	, $assocs_id);
		$this->assignRef('langs'    	, $langs);
		$this->assignRef('filter_cats', $filter_cats);
		$this->assignRef('lists'     	, $lists);
		$this->assignRef('rows'      	, $rows);
		$this->assignRef('ordering'		, $ordering);
		$this->assignRef('pagination'	, $pagination);

		parent::display($tpl);
	}
}
?>
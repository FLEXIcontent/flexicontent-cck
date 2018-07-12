<?php
/**
 * @version 1.5 stable $Id: view.html.php 1657 2013-03-25 11:31:45Z ggppdk $
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
use Joomla\String\StringHelper;

/**
 * View class for the qfcategoryelement screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewQfcategoryelement extends JViewLegacy
{
	function display($tpl = null)
	{
		global $globalcats;
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		$db   = JFactory::getDbo();
		$option   = JRequest::getVar('option');
		$view     = JRequest::getVar('view');
		$document	= JFactory::getDocument();

		// Get model
		$model = $this->getModel();
		
		//JHtml::_('behavior.tooltip');
		JHtml::_('behavior.modal');

		$assocs_id   = JRequest::getInt( 'assocs_id', 0 );
		
		$language    = !$assocs_id ? JRequest::getCmd('language') : $app->getUserStateFromRequest( $option.'.'.$view.'.language', 'language', '', 'string' );
		$created_by  = !$assocs_id ? JRequest::getCmd('created_by') : $app->getUserStateFromRequest( $option.'.'.$view.'.created_by', 'created_by', 0, 'int' );
		
		if ($assocs_id)
		{
			$assocanytrans = $user->authorise('flexicontent.assocanytrans', 'com_flexicontent');
			if (!$assocanytrans && !$created_by)  $created_by = $user->id;
		}
		
		// get filter values
		$filter_order     = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order',     'filter_order',     'c.lft'	, 'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order_Dir',	'filter_order_Dir',	''			, 'cmd' );
		
		$filter_state  = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_state',  'filter_state',   '',    'cmd' );
		$filter_cats   = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_cats',   'filter_cats',    0,     'int' );
		$filter_level  = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_level',  'filter_level',   0,     'int' );
		$filter_access = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_access', 'filter_access',  '',    'string' );
		$filter_lang   = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_lang',   'filter_lang',    '',    'cmd' );
		$filter_author = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_author', 'filter_author',  '',    'cmd' );
		
		$search = $app->getUserStateFromRequest( $option.'.'.$view.'.search', 			'search', 			'', 'string' );
		$search = $db->escape( StringHelper::trim(StringHelper::strtolower( $search ) ) );

		// Prepare the document: set title, add css files, etc
		$document->setTitle(JText::_( 'FLEXI_SELECTITEM' ));
		
		$app->isSite() ?
			$document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', FLEXI_VHASH) :
			!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', FLEXI_VHASH);
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x_rtl.css', FLEXI_VHASH);
		
		// Add JS frameworks
		flexicontent_html::loadFramework('select2');
		
		// Include backend CSS template CSS file , access to backend folder may not be allowed but ...
		//$template = $app->isSite() ? (!FLEXI_J16GE ? 'khepri' : (FLEXI_J30GE ? 'hathor' : 'bluestork')) : $app->getTemplate();
		//$document->addStyleSheet(JUri::base(true).'/templates/'.$template.(FLEXI_J16GE ? '/css/template.css': '/css/general.css'));
		
		//Get data from the model
		$rows     = $this->get( 'Items');
		$authors  = $this->get( 'Authorslist' );
		$pagination = $this->get( 'Pagination' );
		
		// Ordering active FLAG
		$ordering = ($filter_order == 'c.lft');
		
		// Parse configuration for every category
   	foreach ($rows as $cat)  $cat->config = new JRegistry($cat->config);
		
		
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
			flexicontent_cats::buildcatselect($categories, 'filter_cats', $filter_cats, '-'/*2*/, 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', $check_published=true, $check_perms=false);
		
		// filter depth level
		$depths	= array();
		$depths[]	= JHtml::_('select.option', '', '-'/*'FLEXI_SELECT_MAX_DEPTH'*/);
		for($i=1; $i<=10; $i++) $depths[]	= JHtml::_('select.option', $i, $i);
		
		$fieldname =  $elementid = 'filter_level';
		$attribs = ' class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()" ';
		$lists['filter_level'] = '<label class="label">'.JText::_('FLEXI_MAX_DEPTH').'</label>'.
			JHtml::_('select.genericlist', $depths, $fieldname, $attribs, 'value', 'text', $filter_level, $elementid
		, $translate=true );
		
		// build author select list
		$lists['filter_author'] = '<label class="label">'.JText::_('FLEXI_AUTHOR').'</label>'.
			($assocs_id && $created_by ?
				'<span class="badge badge-info">'.JFactory::getUser($created_by)->name.'</span>' :
				flexicontent_html::buildauthorsselect($authors, 'filter_author', $filter_author, '-'/*true*/, 'class="use_select2_lib" size="3" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"')
			);

		// build publication state filter
		$states = JHtml::_('jgrid.publishedOptions');
		array_unshift($states, JHtml::_('select.option', '', '-'/*'FLEXI_SELECT_STATE'*/));
		
		$fieldname =  $elementid = 'filter_state';
		$attribs = ' class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()" ';
		$lists['filter_state'] = '<label class="label">'.JText::_('FLEXI_STATE').'</label>'.
			JHtml::_('select.genericlist', $states, $fieldname, $attribs, 'value', 'text', $filter_state, $elementid
		, $translate=true );
		
		// build access level filter
		$levels = JHtml::_('access.assetgroups');
		array_unshift($levels, JHtml::_('select.option', '', '-'/*'FLEXI_SELECT_ACCESS'*/));
		$fieldname =  $elementid = 'filter_access';
		$attribs = ' class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()" ';
		$lists['filter_access']	= '<label class="label">'.JText::_('FLEXI_ACCESS').'</label>'.
			JHtml::_('select.genericlist', $levels, $fieldname, $attribs, 'value', 'text', $filter_access, $elementid
		, $translate=true );
		
		// build language filter
		$lists['filter_lang'] = '<label class="label">'.JText::_('FLEXI_LANGUAGE').'</label>'.
			($assocs_id && $language ?
				'<span class="badge badge-info">'.$language.'</span>' :
				flexicontent_html::buildlanguageslist('filter_lang', 'class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', $filter_lang, '-'/*2*/)
			);
		
		// assign data to template
		$this->assocs_id = $assocs_id;
		$this->lists = $lists;
		$this->rows = $rows;
		$this->ordering = $ordering;
		$this->pagination = $pagination;

		parent::display($tpl);
	}
}
?>
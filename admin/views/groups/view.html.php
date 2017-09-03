<?php
/**
 * @version 1.5 stable $Id: view.html.php 1889 2014-04-26 03:25:28Z ggppdk $
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
 * View class for the FLEXIcontent (user) groups screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewGroups extends JViewLegacy
{
	protected $items;
	protected $pagination;
	protected $state;
	
	function display( $tpl = null )
	{
		// ***
		// *** Initialise variables
		// ***

		$app     = JFactory::getApplication();
		$jinput  = $app->input;
		$option  = $jinput->get('option', '', 'cmd');
		$view    = $jinput->get('view', '', 'cmd');

		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$user     = JFactory::getUser();
		$db       = JFactory::getDbo();
		$document = JFactory::getDocument();
		
		// Get model
		$model = $this->getModel();

		$this->items		= $model->getItems();
		$this->pagination	= $this->get('Pagination');
		$this->state		= $this->get('State');

		// Check for errors.
		if (count($errors = $this->get('Errors')))
		{
			$app->setHeader('status', '500', true);
			$app->enqueueMessage(implode("\n", $errors), 'error');
			return false;
		}



		// ***
		// *** Get filters
		// ***

		$count_filters = 0;
		
		$search = $app->getUserStateFromRequest( $option.'.'.$view.'.search', 			'search', 			'', 'string' );
		$search = $db->escape( StringHelper::trim(StringHelper::strtolower( $search ) ) );



		// ***
		// *** Add css and js to document
		// ***
		
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', FLEXI_VHASH);
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x_rtl.css', FLEXI_VHASH);

		// Add JS frameworks
		flexicontent_html::loadFramework('select2');

		// Add js function to overload the joomla submitform validation
		JHtml::_('behavior.formvalidation');  // load default validation JS to make sure it is overriden
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);



		// ***
		// *** Create Submenu & Toolbar
		// ***
		
		// Get user's global permissions
		$perms = FlexicontentHelperPerm::getPerm();

		// Create Submenu (and also check access to current view)
		FLEXIUtilities::ManagerSideMenu('CanGroups');
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_GROUPS' );
		$site_title = $document->getTitle();
		JToolbarHelper::title( $doc_title, 'groups' );
		$document->setTitle($doc_title .' - '. $site_title);

		// Create the toolbar
		$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;
		$this->addToolbar();
		
		//assign data to template
		$this->lists['search'] = $search;
		$this->count_filters = $count_filters;
		$this->option = $option;
		$this->view   = $view;
		
		parent::display($tpl);
	}

	/**
	 * Add the page title and toolbar.
	 *
	 * @since	1.6
	 */
	protected function addToolbar()
	{
		$document = JFactory::getDocument();
		$perms = FlexicontentHelperPerm::getPerm();
		$contrl = "groups.";

		$canDo = UsersHelper::getActions();

		if ($canDo->get('core.create'))
		{
			//JToolbarHelper::addNew($contrl.'add');
			JText::script("FLEXI_UPDATING_CONTENTS", true);
			$document->addScriptDeclaration('
				function fc_edit_jgroup_modal_load( container )
				{
					if ( container.find("iframe").get(0).contentWindow.location.href.indexOf("view=groups") != -1 )
					{
						container.dialog("close");
					}
				}
				function fc_edit_jgroup_modal_close()
				{
					window.location.reload(false);
					document.body.innerHTML = Joomla.JText._("FLEXI_UPDATING_CONTENTS") + \' <img id="page_loading_img" src="components/com_flexicontent/assets/images/ajax-loader.gif">\';
				}
			');
			
			$modal_title = JText::_('Add new Joomla group', true);
			$tip_class = ' hasTooltip';
			JToolbarHelper::divider();
			flexicontent_html::addToolBarButton(
				'FLEXI_NEW', $btn_name='add_jgroup',
				$full_js="var url = jQuery(this).attr('data-href'); var the_dialog = fc_showDialog(url, 'fc_modal_popup_container', 0, 0, 0, fc_edit_jgroup_modal_close, {title:'".$modal_title."', loadFunc: fc_edit_jgroup_modal_load}); return false;",
				$msg_alert='', $msg_confirm='',
				$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="btn btn-small btn-success".$tip_class, $btn_icon="icon-new icon-white",
				'data-placement="bottom" data-href="index.php?option=com_users&task=group.edit&id=0" title="Add new Joomla group"'
			);
		}

		if ($canDo->get('core.edit'))
		{
			JToolbarHelper::editList('group.edit');
		}

		if ($canDo->get('core.delete'))
		{
			JToolbarHelper::deleteList(JText::_('FLEXI_ARE_YOU_SURE'), $contrl.'delete');
		}

		$appsman_path = JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'views'.DS.'appsman';
		if (file_exists($appsman_path))
		{
			$btn_icon = 'icon-download';
			$btn_name = 'download';
			$btn_task    = 'appsman.exportxml';
			$extra_js    = " var f=document.getElementById('adminForm'); f.elements['view'].value='appsman'; jQuery('<input>').attr({type: 'hidden', name: 'table', value: 'usergroups'}).appendTo(jQuery(f));";
			flexicontent_html::addToolBarButton(
				'Export now',
				$btn_name, $full_js='', $msg_alert='', $msg_confirm=JText::_('FLEXI_EXPORT_NOW_AS_XML'),
				$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true, $btn_class="btn-info", $btn_icon);
			
			$btn_icon = 'icon-box-add';
			$btn_name = 'box-add';
			$btn_task    = 'appsman.addtoexport';
			$extra_js    = " var f=document.getElementById('adminForm'); f.elements['view'].value='appsman'; jQuery('<input>').attr({type: 'hidden', name: 'table', value: 'usergroups'}).appendTo(jQuery(f));";
			flexicontent_html::addToolBarButton(
				'Add to export',
				$btn_name, $full_js='', $msg_alert='', $msg_confirm=JText::_('FLEXI_ADD_TO_EXPORT_LIST'),
				$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true, $btn_class="btn-info", $btn_icon);
		}
		
		if ($canDo->get('core.admin')) {
			JToolbarHelper::preferences('com_users');
			JToolbarHelper::divider();
		}
		JToolbarHelper::help('JHELP_USERS_GROUPS');
	}
}

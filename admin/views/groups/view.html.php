<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            http://www.flexicontent.com
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

JLoader::register('FlexicontentViewBaseRecords', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/base/view_records.php');

/**
 * View class for the FLEXIcontent (user) groups screen
 */
class FlexicontentViewGroups extends FlexicontentViewBaseRecords
{
	var $proxy_option   = 'com_users';
	var $title_propname = 'title';
	var $state_propname = null;
	var $db_tbl         = 'usergroups';

	public function display($tpl = null)
	{
		/**
		 * Initialise variables
		 */

		global $globalcats;
		$app      = JFactory::getApplication();
		$jinput   = $app->input;
		$document = JFactory::getDocument();
		$user     = JFactory::getUser();
		$cparams  = JComponentHelper::getParams('com_flexicontent');
		$session  = JFactory::getSession();
		$db       = JFactory::getDbo();

		$option   = $jinput->getCmd('option', '');
		$view     = $jinput->getCmd('view', '');
		$task     = $jinput->getCmd('task', '');
		$layout   = $jinput->getString('layout', 'default');

		$isAdmin  = $app->isAdmin();
		$isCtmpl  = $jinput->getCmd('tmpl') === 'component';

		// Some flags & constants
		;

		// Load Joomla language files of other extension
		if (!empty($this->proxy_option))
		{
			JFactory::getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, null, true);
		}

		// Get model
		$model = $this->getModel();

		// Performance statistics
		if ($print_logging_info = $cparams->get('print_logging_info'))
		{
			global $fc_run_times;
		}


		/**
		 * Get filters and ordering
		 */

		$count_filters = 0;

		// Text search
		$search = $app->getUserStateFromRequest( $option.'.'.$view.'.search', 			'search', 			'', 'string' );
		$search = StringHelper::trim(StringHelper::strtolower($search));


		/**
		 * Add css and js to document
		 */

		if ($layout !== 'indexer')
		{
			// Add css to document
			if ($isAdmin)
			{
				!JFactory::getLanguage()->isRtl()
					? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH)
					: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', FLEXI_VHASH);
				!JFactory::getLanguage()->isRtl()
					? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH)
					: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x_rtl.css', FLEXI_VHASH);
			}
			else
			{
				!JFactory::getLanguage()->isRtl()
					? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', FLEXI_VHASH)
					: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent_rtl.css', FLEXI_VHASH);
			}

			// Add JS frameworks
			flexicontent_html::loadFramework('select2');

			// Load custom behaviours: form validation, popup tooltips
			JHtml::_('behavior.formvalidation');
			JHtml::_('bootstrap.tooltip');

			// Add js function to overload the joomla submitform validation
			$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
			$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);
		}



		/**
		 * Create Submenu & Toolbar
		 */

		// Create Submenu (and also check access to current view)
		FLEXIUtilities::ManagerSideMenu('CanGroups');

		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_GROUPS' );
		$site_title = $document->getTitle();
		JToolbarHelper::title( $doc_title, 'groups' );
		$document->setTitle($doc_title .' - '. $site_title);

		// Create the toolbar
		$this->setToolbar();


		/**
		 * Get data from the model
		 */

		if ( $print_logging_info )  $start_microtime = microtime(true);

		$rows = $this->get('Items');

		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

		// Create pagination object
		$pagination = $this->get('Pagination');



		/**
		 * Create List Filters
		 */

		$lists = array();

		$lists['search'] = $search;


		/**
		 * Assign data to template
		 */

		$this->count_filters = $count_filters;

		$this->lists       = $lists;
		$this->rows        = $rows;
		$this->pagination  = $pagination;
		$this->perms  = FlexicontentHelperPerm::getPerm();
		$this->option = $option;
		$this->view   = $view;
		$this->state  = $this->get('State');

		$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;


		/**
		 * Render view's template
		 */

		if ( $print_logging_info ) { global $fc_run_times; $start_microtime = microtime(true); }

		parent::display($tpl);

		if ( $print_logging_info ) @$fc_run_times['template_render'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	}



	/**
	 * Method to configure the toolbar for this view.
	 *
	 * @access	public
	 * @return	void
	 */
	function setToolbar()
	{
		$user     = JFactory::getUser();
		$document = JFactory::getDocument();
		$toolbar  = JToolbar::getInstance('toolbar');
		$perms    = FlexicontentHelperPerm::getPerm();
		$canDo    = UsersHelper::getActions();

		$js = '';

		$contrl = "groups.";

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
			JToolbarHelper::divider();
			flexicontent_html::addToolBarButton(
				'FLEXI_NEW', $btn_name='add_jgroup',
				$full_js="var url = jQuery(this).attr('data-href'); var the_dialog = fc_showDialog(url, 'fc_modal_popup_container', 0, 0, 0, fc_edit_jgroup_modal_close, {title:'".$modal_title."', loadFunc: fc_edit_jgroup_modal_load}); return false;",
				$msg_alert='', $msg_confirm='',
				$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class=$this->btn_sm_class . " btn-success " . $this->tooltip_class, $btn_icon="icon-new icon-white",
				'data-placement="bottom" data-href="index.php?option=com_users&amp;task=group.edit&amp;id=0" title="Add new Joomla group"'
			);
		}

		if (0 && $canDo->get('core.edit'))
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

		if ($canDo->get('core.admin'))
		{
			JToolbarHelper::preferences('com_users');
			JToolbarHelper::divider();
		}

		JToolbarHelper::help('JHELP_USERS_GROUPS');

		if ($js)
		{
			$document->addScriptDeclaration('
				jQuery(document).ready(function(){
					' . $js . '
				});
			');
		}
	}
}

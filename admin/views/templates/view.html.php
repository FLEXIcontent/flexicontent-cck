<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;
use Joomla\Database\DatabaseInterface;

JLoader::register('FlexicontentViewBaseRecords', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/base/view_records.php');

/**
 * View class for the FLEXIcontent templates screen
 */
class FlexicontentViewTemplates extends FlexicontentViewBaseRecords
{
	var $proxy_option   = null;
	var $title_propname = null;
	var $state_propname = null;
	var $db_tbl         = 'flexicontent_templates';

	public function display($tpl = null)
	{
		/**
		 * Initialise variables
		 */

		global $globalcats;
		$app      = \Joomla\CMS\Factory::getApplication();
		$jinput   = $app->input;
		$document = \Joomla\CMS\Factory::getDocument();
		$user     = \Joomla\CMS\Factory::getUser();
		$cparams  = \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent');
		$session  = \Joomla\CMS\Factory::getSession();
		$db       = \Joomla\CMS\Factory::getContainer()->get(DatabaseInterface::class);

		$option   = $jinput->getCmd('option', '');
		$view     = $jinput->getCmd('view', '');
		$task     = $jinput->getCmd('task', '');
		$layout   = $jinput->getString('layout', 'default');

		$isAdmin  = $app->isClient('administrator');
		$isCtmpl  = $jinput->getCmd('tmpl') === 'component';

		// Some flags & constants
		;

		// Load Joomla language files of other extension
		if (!empty($this->proxy_option))
		{
			\Joomla\CMS\Factory::getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, 'en-GB', true);
			\Joomla\CMS\Factory::getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, null, true);
		}

		// Get model
		$model = $this->getModel();

		// Performance statistics
		if ($print_logging_info = $cparams->get('print_logging_info'))
		{
			global $fc_run_times;
		}



		/**
		 * Add css and js to document
		 */

		if ($layout !== 'indexer')
		{
			// Add css to document
			if ($isAdmin)
			{
				!\Joomla\CMS\Factory::getLanguage()->isRtl()
					? $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', array('version' => FLEXI_VHASH))
					: $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', array('version' => FLEXI_VHASH));
				!\Joomla\CMS\Factory::getLanguage()->isRtl()
					? $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x.css' : 'j3x.css'), array('version' => FLEXI_VHASH))
					: $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x_rtl.css' : 'j3x_rtl.css'), array('version' => FLEXI_VHASH));
			}
			else
			{
				!\Joomla\CMS\Factory::getLanguage()->isRtl()
					? $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', array('version' => FLEXI_VHASH))
					: $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontent_rtl.css', array('version' => FLEXI_VHASH));
			}

			// Add JS frameworks
			flexicontent_html::loadFramework('select2');

			// Load custom behaviours: form validation, popup tooltips
			\Joomla\CMS\HTML\HTMLHelper::_('behavior.formvalidator');
			\Joomla\CMS\HTML\HTMLHelper::_('bootstrap.tooltip');

			// Add js function to overload the joomla submitform validation
			$document->addScript(\Joomla\CMS\Uri\Uri::root(true).'/components/com_flexicontent/assets/js/admin.js', array('version' => FLEXI_VHASH));
			$document->addScript(\Joomla\CMS\Uri\Uri::root(true).'/components/com_flexicontent/assets/js/validate.js', array('version' => FLEXI_VHASH));
		}


		/**
		 * Create Submenu & Toolbar
		 */

		// Create Submenu (and also check access to current view)
		FLEXIUtilities::ManagerSideMenu('CanTemplates');

		// Create document/toolbar titles
		$doc_title = \Joomla\CMS\Language\Text::_('FLEXI_TEMPLATES');
		$site_title = $document->getTitle();
		\Joomla\CMS\Toolbar\ToolbarHelper::title( $doc_title, 'eye' );
		$document->setTitle($doc_title .' - '. $site_title);

		// Create the toolbar
		$this->setToolbar();


		/**
		 * Get data from the model, note data retrieval must be before 
		 * getTotal() and getPagination() because it also calculates total rows
		 */

		if ( $print_logging_info )  $start_microtime = microtime(true);

		$rows = $this->get('Data');

		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;


		/**
		 * Create List Filters
		 */

		$lists = array();


		/**
		 * Assign data to template
		 */

		$tmpldirectory = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS;
		$source = $jinput->get('source', '', 'STRING');
		$dest   = $source ? flexicontent_upload::sanitizedir($tmpldirectory, $source) : '';

		$this->lists       = $lists;
		$this->rows        = $rows;
		$this->tmpldirectory = $tmpldirectory;
		$this->source = $source;
		$this->dest = $dest;

		$this->sidebar = null;

		if(FLEXI_J30GE && !FLEXI_J40GE) $this->sidebar = JHtmlSidebar::render();
		if(FLEXI_J40GE) $this->sidebar = \Joomla\CMS\HTML\Helpers\Sidebar::render();


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
		$user     = \Joomla\CMS\Factory::getUser();
		$document = \Joomla\CMS\Factory::getDocument();
		$toolbar  = \Joomla\CMS\Toolbar\Toolbar::getInstance('toolbar');
		$perms    = FlexicontentHelperPerm::getPerm();
		$session  = \Joomla\CMS\Factory::getSession();

		$js = '';

		$contrl = "templates.";

		$appsman_path = JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'views'.DS.'appsman';
		if (file_exists($appsman_path))
		{
			$btn_icon = 'icon-download';
			$btn_name = 'download';
			$btn_task = 'appsman.exportxml';
			$extra_js = " var f=document.getElementById('adminForm'); f.elements['view'].value='appsman'; jQuery('<input>').attr({type: 'hidden', name: 'table', value: '" . $this->db_tbl . "'}).appendTo(jQuery(f));";
			flexicontent_html::addToolBarButton(
				'Export now',
				$btn_name, $full_js='', $msg_alert='', $msg_confirm=\Joomla\CMS\Language\Text::_('FLEXI_EXPORT_NOW_AS_XML'),
				$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true, $btn_class="btn-info", $btn_icon);

			$btn_icon = 'icon-box-add';
			$btn_name = 'box-add';
			$btn_task = 'appsman.addtoexport';
			$extra_js = " var f=document.getElementById('adminForm'); f.elements['view'].value='appsman'; jQuery('<input>').attr({type: 'hidden', name: 'table', value: '" . $this->db_tbl . "'}).appendTo(jQuery(f));";
			flexicontent_html::addToolBarButton(
				'Add to export',
				$btn_name, $full_js='', $msg_alert='', $msg_confirm=\Joomla\CMS\Language\Text::_('FLEXI_ADD_TO_EXPORT_LIST'),
				$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true, $btn_class="btn-info", $btn_icon);
		}

		if ($perms->CanConfig)
		{
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			\Joomla\CMS\Toolbar\ToolbarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}

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
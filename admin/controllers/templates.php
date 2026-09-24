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

defined('_JEXEC') or die;

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

JLoader::register('FlexicontentControllerBaseAdmin', JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'controllers' . DS . 'base' . DS . 'baseadmin.php');

// Manually import models in case used by frontend, then models will not be autoloaded correctly via getModel('name')
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'template.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'templates.php';

/**
 * FLEXIcontent Templates Controller
 *
 * NOTE: -Only- if this controller is needed by frontend URLs, then create a derived controller in frontend 'controllers' folder
 *
 * @since 3.3
 */
class FlexicontentControllerTemplates extends FlexicontentControllerBaseAdmin
{
	var $records_dbtbl = 'flexicontent_templates';
	var $records_jtable = 'flexicontent_templates';

	var $record_name = 'template';
	var $record_name_pl = 'templates';

	var $_NAME = 'TEMPLATE';
	var $record_alias = null;

	var $runMode = 'standalone';

	var $exitHttpHead = null;
	var $exitMessages = array();
	var $exitLogTexts = array();
	var $exitSuccess  = true;

	/**
	 * Constructor
	 *
	 * @param   array   $config    associative array of configuration settings.
	 *
	 * @since 3.3
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		// Register task aliases
		$this->registerTask('add',          'edit');
		$this->registerTask('apply',        'save');
		$this->registerTask('apply_modal',  'save');

		// Can manage ACL
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanTemplates;
	}


	/**
	 * Logic to save a record
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function save()
	{
		// Check for request forgeries
		\Joomla\CMS\Session\Session::checkToken('request') or die(\Joomla\CMS\Language\Text::_('JINVALID_TOKEN'));

		// Initialize variables
		$app     = \Joomla\CMS\Factory::getApplication();
		$user    = \Joomla\CMS\Factory::getUser();

		$ctrl_task = 'task=' . $this->record_name_pl . '.';
		$original_task = $this->task;

		$type    = $this->input->getWord('type', 'items');
		$folder  = $this->input->getString('folder', 'table');
		$cfgname = $this->input->getString('cfgname');

		$isnew = 0;

		// Extra steps before creating the model
		if ($isnew)
		{
			// Nothing needed
		}

		// Get the model
		$model = $this->getModel($this->record_name);

		// Make sure Primary Key is correctly set into the model ... (needed for loading correct item)
		$model->setId($type, $folder, $cfgname);

		// Calculate access
		$is_authorised = $this->canManage;

		// Check access
		if (!$is_authorised)
		{
			$app->setHeader('status', '403 Forbidden', true);
			$app->enqueueMessage(\Joomla\CMS\Language\Text::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');

			// Skip redirection back to return url if inside a component-area-only view, showing error using current page, since usually we are inside a iframe modal
			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($this->returnURL);
			}

			if ($this->input->get('fc_doajax_submit'))
			{
				jexit(flexicontent_html::get_system_messages_html());
			}
			else
			{
				return false;
			}
		}


		/**
		 * Basic Form data validation
		 */

		$positions = $this->input->getString('positions',  '');
		$positions = explode(',', $positions);

		// Default filtering will remove HTML
		$post = $this->input->get->post->getArray();
		$attribs = $post['jform']['layouts'][$folder];

		// Make sure the Layout Builder JHtml helper is loaded before the layout XML filters run,
		// otherwise callable filters like createUrlHash / prepareLess are silently ignored
		// (this is why the builder hash stayed empty and the compiled CSS was never refreshed)
		\Joomla\CMS\HTML\HTMLHelper::addIncludePath(JPATH_SITE . '/components/com_flexicontent/helpers/html');
		\Joomla\CMS\HTML\HTMLHelper::_('fclayoutbuilder.createUrlHash', '');

		// Run the layout XML field filters (JHtmlFclayoutbuilder::createUrlHash / prepareLess, etc)
		// so that the builder hash is regenerated and the LESS file is removed (CSS will be re-created)
		$existing_attribs = flexicontent_tmpl::getLayoutparams($type, $folder, $cfgname);
		$layout_type = $type == 'category' ? 'category' : 'item';
		$attribs = flexicontent_tmpl::validateLayoutData($attribs, new \Joomla\Registry\Registry($existing_attribs),
			(object) array('type' => $layout_type, 'name' => $folder, 'fset' => 'attribs', 'cssprep_save' => true)
		);

		// Joomla's Input layer applies the Global Text Filtering rules to every posted value,
		// stripping HTML tags (e.g. '<h1>') even when they are embedded inside JSON such as the
		// Layout Builder project data (data-fc-prefix="<h1>"). The PHP superglobals / raw request
		// body are NOT processed by Joomla's filters, so use them as the source of truth for the
		// Layout Builder fields below (the layout XML form filters still run afterwards).
		$raw_layout = isset($_POST['jform']['layouts'][$folder]) ? $_POST['jform']['layouts'][$folder] : array();
		if (empty($raw_layout))
		{
			$raw_body = file_get_contents('php://input');
			if (is_string($raw_body) && strlen($raw_body) && strpos($raw_body, 'jform[') !== false)
			{
				$raw_post = array();
				parse_str($raw_body, $raw_post);
				$raw_layout = isset($raw_post['jform']['layouts'][$folder]) ? $raw_post['jform']['layouts'][$folder] : array();
			}
		}
		if (empty($raw_layout))
		{
			$raw_layout = isset($post['jform']['layouts'][$folder]) ? $post['jform']['layouts'][$folder] : array();
		}
		foreach (array(
			'builder_layout1_data', 'builder_layout1_html', 'builder_layout1_css', 'builder_layout1_js',
			'builder_page1_data',  'builder_page1_html',  'builder_page1_css',  'builder_page1_js',
		) as $builder_field)
		{
			if (array_key_exists($builder_field, $raw_layout))
			{
				$attribs[$builder_field] = $raw_layout[$builder_field];
			}
		}

		// Deterministically regenerate the front-end HTML/CSS/JS from the builder project JSON
		// (the single source of truth). The browser canvas serialization captured transient /
		// partial states on some saves and produced degraded "_html" values (only text).
		// Keep previously stored values when the project data is missing / invalid.
		if (!class_exists('JHtmlFclayoutbuilder'))
		{
			$builder_helper = JPATH_SITE . '/components/com_flexicontent/helpers/html/fclayoutbuilder.php';
			if (is_file($builder_helper)) require_once $builder_helper;
		}
		$layout_data    = isset($attribs['builder_layout1_data']) ? $attribs['builder_layout1_data'] : '';
		$layout_render  = class_exists('JHtmlFclayoutbuilder') ? \JHtmlFclayoutbuilder::renderLayoutFromProject($layout_data) : null;
		if (!empty($layout_render) && $layout_render['html'] !== null)
		{
			$attribs['builder_layout1_html'] = $layout_render['html'];
			$attribs['builder_layout1_css']  = $layout_render['css'];
			$attribs['builder_layout1_js']   = $layout_render['js'];

			// Regenerate the layout URL hash (cache-buster for the compiled CSS file).
			// The Joomla form filter skips fields with an empty submitted value, so the
			// JHtmlFclayoutbuilder::createUrlHash filter could never populate this field:
			// it only runs when the hash field already holds a value. Content-addressed so
			// that unchanged layouts keep the same hash and the browser cache is reused.
			$attribs['builder_layout1_hash'] = md5((string) $attribs['builder_layout1_css']);
		}

		// Same regeneration for the category page layout (builder_page1): the posted project
		// JSON is the source of truth, the stored HTML/CSS/JS are rebuilt from it server-side
		// (otherwise the raw canvas capture, e.g. empty block divs without {flexi_cat:..} tokens,
		// would be saved as-is and the composed page would render only the item grid).
		$layout_data    = isset($attribs['builder_page1_data']) ? $attribs['builder_page1_data'] : '';
		$layout_render  = class_exists('JHtmlFclayoutbuilder') ? \JHtmlFclayoutbuilder::renderLayoutFromProject($layout_data) : null;
		if (!empty($layout_render) && $layout_render['html'] !== null)
		{
			$attribs['builder_page1_html'] = $layout_render['html'];
			$attribs['builder_page1_css']  = $layout_render['css'];
			$attribs['builder_page1_js']   = $layout_render['js'];
			$attribs['builder_page1_hash'] = md5((string) $attribs['builder_page1_css']);
		}

		// Set templates configuration
		$model->setConfig($positions, $attribs);


		/**
		 * Try to store the form data into the item
		 */

		// If saving fails, do any needed cleanup, and then redirect back to record form
		if (!$model->store($post))
		{
			// Set error message and the redirect URL (back to the record form)
			$app->setHeader('status', '500 Internal Server Error', true);
			$this->setError($model->getError() ?: \Joomla\CMS\Language\Text::_('FLEXI_ERROR_SAVING_' . $this->_NAME));
			$this->setMessage($this->getError(), 'error');

			// Skip redirection back to return url if inside a component-area-only view, showing error using current page, since usually we are inside a iframe modal
			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($this->returnURL);
			}

			// Try to check-in the record, but ignore any new errors
			try
			{
				!$isnew ? $model->checkin() : true;
			}
			catch (Exception $e)
			{
			}

			if ($this->input->get('fc_doajax_submit'))
			{
				jexit(flexicontent_html::get_system_messages_html());
			}
			else
			{
				return false;
			}
		}


		/**
		 * Saving is done, decide where to redirect
		 */

		$msg = \Joomla\CMS\Language\Text::_('FLEXI_SAVE_FIELD_POSITIONS');

		switch ($this->task)
		{
			case 'apply_modal' :
				$link = 'index.php?option=com_flexicontent&view=template&type=' . $type . '&folder=' . $folder . '&tmpl=component&ismodal=1&' . \Joomla\CMS\Session\Session::getFormToken() . '=1';
				break;

			case 'apply':
				$link = 'index.php?option=com_flexicontent&view=' . $this->record_name
					. '&type=' . $type . '&folder=' . $folder;
				break;

			// REDIRECT CASES FOR SAVING
			default:
				$link = $this->returnURL;
				break;
		}

		$app->enqueueMessage($msg, 'message');
		$this->setRedirect($link);

		// return;  // comment above and decomment this one to profile the saving operation

		if ($this->input->get('fc_doajax_submit'))
		{
			jexit(flexicontent_html::get_system_messages_html());
		}
	}


	/**
	 * Cancel the edit, check in the record and return to the records manager
	 *
	 * @return bool
	 *
	 * @since 3.3
	 */
	public function cancel()
	{
		// Check for request forgeries
		\Joomla\CMS\Session\Session::checkToken('request') or die(\Joomla\CMS\Language\Text::_('JINVALID_TOKEN'));

		// Calculate access
		$is_authorised = $this->canManage;

		// Check access
		if (!$is_authorised)
		{
			JError::raiseWarning(403, \Joomla\CMS\Language\Text::_('FLEXI_ALERTNOTAUTH_TASK'));
			$this->setRedirect('index.php?option=com_flexicontent', '');

			return;
		}

		$this->setRedirect('index.php?option=com_flexicontent&view=templates');
	}
}

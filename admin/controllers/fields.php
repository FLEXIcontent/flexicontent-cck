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
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'field.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'fields.php';

/**
 * FLEXIcontent Fields Controller
 *
 * NOTE: -Only- if this controller is needed by frontend URLs, then create a derived controller in frontend 'controllers' folder
 *
 * @since 3.3
 */
class FlexicontentControllerFields extends FlexicontentControllerBaseAdmin
{
	var $records_dbtbl  = 'flexicontent_fields';
	var $records_jtable = 'flexicontent_fields';

	var $record_name = 'field';
	var $record_name_pl = 'fields';

	var $_NAME = 'FIELD';
	var $record_alias = 'name';

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
		$this->registerTask('apply_ajax',   'save');
		$this->registerTask('save2new',     'save');
		$this->registerTask('save2copy',    'save');
		$this->registerTask('copy_wvalues', 'copy');

		$this->registerTask('exportxml', 'export');
		$this->registerTask('exportsql', 'export');
		$this->registerTask('exportcsv', 'export');

		// Can manage ACL
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanFields;

		// Error messages
		$this->err_locked_recs_changestate = 'FLEXI_YOU_CANNOT_UNPUBLISH_THESE_CORE_FIELDS_DUE_TO_VERSIONING';
		$this->err_locked_recs_delete      = 'FLEXI_YOU_CANNOT_REMOVE_CORE_FIELDS';

		// Warning messages
		$this->warn_locked_recs_skipped    = 'FLEXI_SKIPPED_N_ROWS_BEING_OF_CORE_TYPE';
		$this->warn_noauth_recs_skipped    = 'FLEXI_SKIPPED_N_ROWS_UNAUTHORISED';		

		// Messages about related data
		$this->msg_relations_deleted = 'FLEXI_VALUES_DELETED';
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
		parent::save();
	}


	/**
	 * Logic to order up/down a record
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function reorder($dir = null)
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		$app   = JFactory::getApplication();
		$model = $this->getModel($this->record_name_pl);
		$user  = JFactory::getUser();

		// Calculate ACL access
		$is_authorised = $user->authorise('flexicontent.orderfields', 'com_flexicontent');

		// Check access
		if (!$is_authorised)
		{
			$app->setHeader('status', '403 Forbidden', true);
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->redirect($this->returnURL);
		}

		// Get record id and ordering group
		$cid         = $this->input->get('cid', array(0), 'array');
		$filter_type = $this->input->get('filter_type', array(0), 'array');

		$cid = ArrayHelper::toInteger($cid);
		$filter_type = ArrayHelper::toInteger($filter_type);

		// Make sure direction is set
		$dir = $dir ?: ($this->task === 'orderup' ? -1 : 1);

		if (!$model->move($dir, reset($filter_type)))
		{
			$app->setHeader('status', '500 Internal Server Error', true);
			$app->enqueueMessage(JText::_('FLEXI_ERROR_SAVING_ORDER') . ': ' . $model->getError(), 'error');
			$app->redirect($this->returnURL);
		}

		// Note we no longer set the somewhat redundant message: JText::_('FLEXI_NEW_ORDERING_SAVED')
		$this->setRedirect($this->returnURL);
	}


	/**
	 * Logic to orderup a record, wrapper for reorder method
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function orderup()
	{
		$this->reorder($dir = -1);
	}


	/**
	 * Logic to orderdown a record, wrapper for reorder method
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function orderdown()
	{
		$this->reorder($dir = 1);
	}


	/**
	 * Logic to mass order records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function saveorder()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		$app   = JFactory::getApplication();
		$model = $this->getModel($this->record_name_pl);
		$user  = JFactory::getUser();

		// Calculate ACL access
		$is_authorised = $user->authorise('flexicontent.orderfields', 'com_flexicontent');

		// Check access
		if (!$is_authorised)
		{
			$app->setHeader('status', 403);
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->redirect($this->returnURL);
		}

		// Get record ids, new orderings and the ordering group
		$cid         = $this->input->get('cid', array(0), 'array');
		$order       = $this->input->get('order', array(0), 'array');
		$filter_type = $this->input->get('filter_type', array(0), 'array');

		$cid = ArrayHelper::toInteger($cid);
		$order = ArrayHelper::toInteger($order);
		$filter_type = ArrayHelper::toInteger($filter_type);

		if (!$model->saveorder($cid, $order, reset($filter_type)))
		{
			$app->setHeader('status', 500);
			$app->enqueueMessage(JText::_('FLEXI_ERROR_SAVING_ORDER') . ': ' . $model->getError(), 'error');
			$app->redirect($this->returnURL);
		}

		// Note we no longer set the somewhat redundant message: JText::_('FLEXI_NEW_ORDERING_SAVED')
		$this->setRedirect($this->returnURL);
	}


	/**
	 * Check in a record
	 *
	 * @since	3.3
	 */
	public function checkin()
	{
		parent::checkin();
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
		return parent::cancel();
	}


	/**
	 * Logic to publish records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function publish()
	{
		parent::publish();
	}


	/**
	 * Logic to unpublish records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function unpublish()
	{
		parent::unpublish();
	}


	/**
	 * Logic to delete records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function remove()
	{
		parent::remove();
	}


	/**
	 * Logic to create the view for record editing
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function edit()
	{
		parent::edit();
	}


	/**
	 * Logic to set the access level of the records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function access()
	{
		parent::access();
	}


	/**
	 * Method for clearing cache of data depending on records type
	 *
	 * @return void
	 *
	 * @since 3.2.0
	 */
	protected function _cleanCache()
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		parent::_cleanCache();

		$cache_site = FLEXIUtilities::getCache($group = '', $client = 0);
		$cache_site->clean('com_flexicontent_items');
		$cache_site->clean('com_flexicontent_filters');

		$cache_admin = FLEXIUtilities::getCache($group = '', $client = 1);
		$cache_admin->clean('com_flexicontent_items');
		$cache_admin->clean('com_flexicontent_filters');

		// Also clean this as it contains Joomla frontend view cache of the component)
		$cache_site->clean('com_flexicontent');
	}


	/**
	 * Method for extra form validation after JForm validation is executed
	 *
	 * @param   array     $validated_data  The already jform-validated data of the record
	 * @param   object    $model            The Model object of current controller instance
	 * @param   array     $data            The original posted data of the record
	 *
	 * @return  boolean   true on success, false on failure
	 *
	 * @since 3.3
	 */
	protected function _afterModelValidation(& $validated_data, & $data, $model)
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		if (!parent::_afterModelValidation($validated_data, $data, $model))
		{
			return false;
		}

		/**
		 * Add parameters of layouts, these are unfiltered since field configuration is privileged
		 * and it already allow RAW value parameters like value prefix and value suffix parameters
		 */

		if (isset($data['layouts']))
		{
			foreach ($data['layouts'] as $i => $v)
			{
				$validated_data['attribs'][$i] = $v;
			}
		}

		return true;
	}


	/**
	 * Method for doing some record type specific work before calling model store
	 *
	 * @return  boolean   true on success, false on failure
	 *
	 * @since 3.3
	 */
	protected function _beforeModelStore(& $validated_data, & $data, $model)
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		/**
		 * Do not allow custom fields to be marked as CORE
		 */

		if (!$validated_data['id'] && !empty($validated_data['iscore']))
		{
			$this->setError('Field\'s "iscore" property is ON, but creating new fields as CORE is not allowed');

			return false;
		}

		return true;
	}



	/**
	 * Logic to copy the records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function copy()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		// Initialize variables
		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();
		$task   = $this->input->get('task', 'copy', 'cmd');
		$option = $this->input->get('option', '', 'cmd');

		// Get model
		$model = $this->getModel($this->record_name_pl);

		// Get and santize records ids
		$cid = $this->input->get('cid', array(), 'array');
		$cid = ArrayHelper::toInteger($cid);

		// Check at least one item was selected
		if (!count($cid))
		{
			$app->enqueueMessage(JText::_('FLEXI_SELECT_ITEMS'), 'error');
			$app->setHeader('status', 500, true);
			$this->setRedirect($this->returnURL);

			return;
		}

		// Calculate access
		$is_authorised = $user->authorise('flexicontent.copyfields', 'com_flexicontent');

		// Check access
		if (!$is_authorised)
		{
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->setHeader('status', 403, true);
			$this->setRedirect($this->returnURL);

			return;
		}

		// Remove core fields
		$cid_locked = array();
		$non_core_cid = array();

		// Copying of core fields is not allowed
		foreach ($cid as $id)
		{
			if ($id < 15)
			{
				$cid_locked[] = $id;
			}
			else
			{
				$non_core_cid[] = $id;
			}
		}

		// Remove uneditable fields
		$auth_cid = array();
		$non_auth_cid = array();

		// Cannot copy fields you cannot edit
		foreach ($non_core_cid as $id)
		{
			$asset = 'com_flexicontent.field.' . $id;
			$is_authorised = $user->authorise('flexicontent.editfield', $asset);

			if ($is_authorised)
			{
				$auth_cid[] = $id;
			}
			else
			{
				$non_auth_cid[] = $id;
			}
		}

		// Try to copy fields
		$ids_map = $model->copy($auth_cid, $task === 'copy_wvalues');

		if (!$ids_map)
		{
			$msg = JText::_('FLEXI_FIELDS_COPY_FAILED');
			JError::raiseWarning(500, $model->getError());
		}
		else
		{
			$msg = '';

			if (count($ids_map))
			{
				$msg .= JText::sprintf('FLEXI_FIELDS_COPY_SUCCESS', count($ids_map)) . ' ';
			}

			if (count($auth_cid) - count($ids_map))
			{
				// $msg .= JText::sprintf('FLEXI_FIELDS_SKIPPED_DURING_COPY', count($auth_cid)-count($ids_map)) . ' ';
			}

			if (count($cid_locked))
			{
				$msg .= JText::sprintf('FLEXI_FIELDS_CORE_FIELDS_NOT_COPIED', count($cid_locked)) . ' ';
			}

			if (count($non_auth_cid))
			{
				$msg .= JText::sprintf('FLEXI_FIELDS_UNEDITABLE_FIELDS_NOT_COPIED', count($non_auth_cid)) . ' ';
			}

			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
		}

		$filter_type = $app->getUserStateFromRequest($option . '.fields.filter_type', 'filter_type', '', 'int');

		if ($filter_type)
		{
			$app->setUserState($option . '.fields.filter_type', '');
			$msg .= ' ' . JText::_('FLEXI_TYPE_FILTER_CLEARED_TO_VIEW_NEW_FIELDS');
		}

		$this->setRedirect($this->returnURL, $msg);
	}


	/**
	 * START OF CONTROLLER SPECIFIC METHODS
	 */


	/**
	 * Logic to turn ON a boolean property of fields
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	function toggleprop_on()
	{
		$this->toggleprop($toggle_value = 1);
	}


	/**
	 * Logic to turn OFF a boolean property of fields
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	function toggleprop_off()
	{
		$this->toggleprop($toggle_value = 0);
	}


	/**
	 * Logic to toggle boolean property of fields
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	function toggleprop($toggle_value = null)
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();

		// Get model
		$model = $this->getModel($this->record_name_pl);

		$propname = $this->input->get('propname', null, 'cmd');

		// Get and santize records ids
		$cid = $this->input->get('cid', array(), 'array');
		$cid = ArrayHelper::toInteger($cid);

		// Check at least one item was selected
		if (!count($cid))
		{
			$app->enqueueMessage(JText::_('FLEXI_SELECT_ITEMS'), 'error');
			$app->setHeader('status', 500, true);
			$this->setRedirect($this->returnURL);

			return;
		}

		// Calculate access
		$cid_noauth = array();

		foreach ($cid as $i => $_id)
		{
			if (!$user->authorise('flexicontent.publishfield', 'com_flexicontent.field.' . $_id))
			{
				$cid_noauth[] = $_id;
				unset($cid[$i]);
			}
		}

		$is_authorised = count($cid);

		// Check access
		if (!$is_authorised)
		{
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->setHeader('status', 403, true);
			$this->setRedirect($this->returnURL);

			return;
		}
		elseif (count($cid_noauth))
		{
			$app->enqueueMessage("You cannot change state of fields : ", implode(', ', $cid_noauth));
		}

		$unsupported = 0;
		$locked = 0;
		$affected = $model->toggleprop($cid, $propname, $unsupported, $locked, $toggle_value);

		if ($affected === false)
		{
			$msg = JText::_('FLEXI_OPERATION_FAILED') . ' : ' . $model->getError();
			throw new Exception($msg, 500);
		}

		// A message about total count of affected rows , and about skipped fields (unsupported or locked)
		$prop_map = array(
			'issearch' => 'FLEXI_TOGGLE_TEXT_SEARCHABLE',
			'isfilter' => 'FLEXI_TOGGLE_FILTERABLE',
			'isadvsearch' => 'FLEXI_TOGGLE_ADV_TEXT_SEARCHABLE',
			'isadvfilter' => 'FLEXI_TOGGLE_ADV_FILTERABLE'
		);
		$property_fullname = isset($prop_map[$propname]) ? "'" . JText::_($prop_map[$propname]) . "'" : '';

		$msg = JText::sprintf('FLEXI_FIELDS_TOGGLED_PROPERTY', $property_fullname, $affected);

		if ($unsupported || $locked)
		{
			$msg .= '<br/>' . JText::sprintf('FLEXI_FIELDS_TOGGLED_PROPERTY_FIELDS_SKIPPED', $unsupported + $locked, $unsupported, $locked);
		}

		// Clear dependent cache data
		$this->_cleanCache();

		$this->setRedirect($this->returnURL, $msg);
	}


	/**
	 * Task for AJAX request, for creating HTML for toggling search properties for many fields
	 *
	 * return: string
	 *
	 * @since 3.3
	 */
	function selectsearchflag()
	{
		$document = JFactory::getDocument();
		flexicontent_html::loadFramework('flexi-lib');
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', array('version' => FLEXI_VHASH))
			: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', array('version' => FLEXI_VHASH));
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x.css' : 'j3x.css'), array('version' => FLEXI_VHASH))
			: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x_rtl.css' : 'j3x_rtl.css'), array('version' => FLEXI_VHASH));

		$btn_class = 'btn';

		$state['issearch'] = array( 'name' => 'FLEXI_TOGGLE_TEXT_SEARCHABLE', 'desc' => 'FLEXI_FIELD_CONTENT_LIST_TEXT_SEARCHABLE_DESC', 'icon' => 'search', 'btn_class' => '', 'clear' => true );
		$state['isfilter'] = array( 'name' => 'FLEXI_TOGGLE_FILTERABLE', 'desc' => 'FLEXI_FIELD_CONTENT_LIST_FILTERABLE_DESC', 'icon' => 'filter', 'btn_class' => '', 'clear' => true );
		$state['isadvsearch'] = array( 'name' => 'FLEXI_TOGGLE_ADV_TEXT_SEARCHABLE', 'desc' => 'FLEXI_FIELD_ADVANCED_TEXT_SEARCHABLE_DESC', 'icon' => 'search', 'btn_class' => '', 'clear' => true );
		$state['isadvfilter'] = array( 'name' => 'FLEXI_TOGGLE_ADV_FILTERABLE', 'desc' => 'FLEXI_FIELD_ADVANCED_FILTERABLE_DESC', 'icon' => 'filter', 'btn_class' => '', 'clear' => true );

		echo '
		<div id="flexicontent" class="flexicontent" style="padding-top:5%;">
		
		<script>
			var i = 0;
			function submit_progressbar(bar_elem) {
				var elem = bar_elem.firstChild;
				if (i == 0) {
					i = 1;
					var width = 1;
					var id = setInterval(frame, 25);
					function frame() {
						if (width >= 100) {
							clearInterval(id);
							i = 0;
						} else {
							width++;
							elem.style.width = width + "%";
						}
					}
				}
			}

			function field_toggleprop(shortname, onoff)
			{
				if (window.parent.document.adminForm.boxchecked.value==0) {
					alert("' . JText::_('FLEXI_NO_ITEMS_SELECTED', true) . '");
					return false;
				}
				var fc_blocker_mssg = document.getElementsByClassName("fc_blocker_mssg")[0];
				var fc_filter_form_blocker = document.getElementById("fc_filter_form_blocker");
				var fc_blocker_bar = document.getElementsByClassName("fc_blocker_bar")[0];

				fc_blocker_mssg.innerHTML = "' . JText::_('FLEXI_LOADING') . '";
				fc_filter_form_blocker.style.display = "block";
				submit_progressbar(fc_blocker_bar);

				window.parent.document.adminForm.propname.value=shortname;
				window.parent.document.adminForm.task.value="fields.toggleprop_" + onoff;
				window.parent.document.adminForm.submit();
				return false;
			}
		</script>

		';
		foreach ($state as $shortname => $statedata)
		{
			$css = "width: auto; margin:0px 24px 12px 0px; text-align: left;";
			$link = JUri::base(true) . "/index.php?option=com_flexicontent&task=fields.toggleprop&propname=" . $shortname . "&" . JSession::getFormToken() . "=1";
			$icon = $statedata['icon'];

			if ($shortname == 'issearch')
			{
				echo '<h2>' . JText::_('FLEXI_CONTENT_LISTS') . '</h2>';
			}
			elseif ($shortname == 'isadvsearch')
			{
				echo '<h2>' . JText::_('FLEXI_SEARCH_VIEW_CONF') . '</h2>';
			}
			?>
			
			<div style="display: inline-block; min-width: 216px;" class="hasTooltip" data-placement="right" title="<?php echo JText::_($statedata['desc']); ?>" style="font-size: 1rem;">
				<span class="icon-info"></span>
				<span class="icon-<?php echo $icon; ?>"></span>
				<?php echo JText::_($statedata['name']); ?>
			</div>

			<button style="<?php echo $css; ?>" class="<?php echo $btn_class . ' ' . $statedata['btn_class'] . ' btn-success'; ?>" onclick="field_toggleprop('<?php echo $shortname; ?>','on');">
				<?php echo JText::_('JON'); ?> 
			</button>

			<button style="<?php echo $css; ?>" class="<?php echo $btn_class . ' ' . $statedata['btn_class'] . ' btn-danger'; ?>" onclick="field_toggleprop('<?php echo $shortname; ?>', 'off');">
				<?php echo JText::_('JOFF'); ?> 
			</button>

			<?php
			if (isset($statedata['clear']))
			{
				echo '<div class="fcclear"></div>';
			}
		}
		
		echo '
		</div>
		';

		return;
	}
}

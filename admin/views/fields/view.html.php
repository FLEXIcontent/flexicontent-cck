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

JLoader::register('FlexicontentViewBaseRecords', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/base/view_records.php');

/**
 * HTML View class for the FLEXIcontent fields screen
 */
class FlexicontentViewFields extends FlexicontentViewBaseRecords
{
	var $proxy_option   = null;
	var $title_propname = 'label';
	var $state_propname = 'published';
	var $db_tbl         = 'flexicontent_fields';
	var $name_singular  = 'field';

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
		$cid      = $jinput->get('cid', array(), 'array');

		$isAdmin  = $app->isClient('administrator');
		$isCtmpl  = $jinput->getCmd('tmpl') === 'component';

		// Some flags & constants
		$useAssocs = flexicontent_db::useAssociations();

		// Load Joomla language files of other extension
		if (!empty($this->proxy_option))
		{
			JFactory::getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, null, true);
		}

		// Get model
		$model   = $this->getModel();
		$model_s = $this->getModel($this->name_singular);

		// Performance statistics
		if ($print_logging_info = $cparams->get('print_logging_info'))
		{
			global $fc_run_times;
		}


		/**
		 * Get filters and ordering
		 */

		$count_filters = 0;

		// Order and order direction
		$filter_order      = $model->getState('filter_order');
		$filter_order_Dir  = $model->getState('filter_order_Dir');

		// Various filters
		$filter_fieldtype = $model->getState('filter_fieldtype');
		$filter_assigned  = $model->getState('filter_assigned');
		$filter_type      = $model->getState('filter_type');
		$filter_state     = $model->getState('filter_state');
		$filter_access    = $model->getState('filter_access');

		$reOrderingActive = !$filter_type
			? ($filter_order === 'a.ordering')
			: ($filter_order === 'typeordering');

		//if ($filter_fieldtype) $count_filters++;
		if ($filter_assigned) $count_filters++;
		if ($filter_type && !$reOrderingActive) $count_filters++;
		if (strlen($filter_state)) $count_filters++;
		if (strlen($filter_access)) $count_filters++;

		// Text search
		$scope  = $model->getState('scope');
		$search = $model->getState('search');
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
					? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', array('version' => FLEXI_VHASH))
					: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', array('version' => FLEXI_VHASH));
				!JFactory::getLanguage()->isRtl()
					? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x.css' : 'j3x.css'), array('version' => FLEXI_VHASH))
					: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x_rtl.css' : 'j3x_rtl.css'), array('version' => FLEXI_VHASH));
			}
			else
			{
				!JFactory::getLanguage()->isRtl()
					? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', array('version' => FLEXI_VHASH))
					: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent_rtl.css', array('version' => FLEXI_VHASH));
			}

			// Add JS frameworks
			flexicontent_html::loadFramework('select2');

			// Load custom behaviours: form validation, popup tooltips
			JHtml::_('behavior.formvalidator');
			JHtml::_('bootstrap.tooltip');

			// Add js function to overload the joomla submitform validation
			$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', array('version' => FLEXI_VHASH));
			$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', array('version' => FLEXI_VHASH));
		}


		/**
		 * Create Submenu & Toolbar
		 */

		// Create Submenu (and also check access to current view)
		FLEXIUtilities::ManagerSideMenu('CanFields');

		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_FIELDS' );
		$site_title = $document->getTitle();
		JToolbarHelper::title( $doc_title, 'puzzle' );
		$document->setTitle($doc_title .' - '. $site_title);

		// Create the toolbar
		$this->setToolbar();


		/**
		 * Get data from the model, note data retrieval must be before 
		 * getTotal() and getPagination() because it also calculates total rows
		 */

		if ( $print_logging_info )  $start_microtime = microtime(true);

		$rows        = $model->getItems();

		// Get -all- content types
		$types       = $model->getTypeslist();

		/**
		 * Support language strings in content type names
		 */
		foreach($types as $type)
		{
			$type->jname = JText::_($type->name);
		}

		$rowsByIds    = array();
		$master_fids  = array();
		$grouped_fids = array();

		$CA_fieldids  = array();
		$FG_fieldids  = array();

		foreach ($rows as $row)
		{
			$id = $row->id;

			// Assign to index via id
			$rowsByIds[$id] = $row;

			// Load field parameters
			$row->parameters = new JRegistry($row->attribs);

			// Find ids of master fields (if any)
			$cascade_after = (int) $row->parameters->get('cascade_after');

			if ($cascade_after)
			{
				$CA_fieldids[$cascade_after] = $id;
				$master_fids[] = $cascade_after;
			}

			// Find ids of master fields (if any)
			if ($row->field_type === 'fieldgroup')
			{
				$FG_fieldids[$id] = array_filter(preg_split('/[\s]*,[\s]*/', $row->parameters->get('fields')), function($v) { return (trim($v) !== ''); });
				$FG_fieldids[$id] = ArrayHelper::toInteger($FG_fieldids[$id]);

				$grouped_fids = array_merge($grouped_fids, $FG_fieldids[$id]);
			}
		}

		// Get grouped fields inside a fieldgroup field of -current- page
		$rowsInGroup = !count($grouped_fids) ? array() : $model->getItemsByConditions(array(
			'where' => array('t.id IN (' . implode(',', $grouped_fids) . ')'),
		));


		/**
		 * Get -all- fieldgroup fields ('fieldgroup' field-type) and iterate through all them to create the
		 * information needed for displaying fieldgroup information of every grouped field in current list
		 */

		$rowsFG = $model->getItemsByConditions(array(
			'where' => array('t.field_type = "fieldgroup"'),
		));

		foreach ($rowsFG as $row)
		{
			$id = $row->id;

			// Handle displaying information: FIELDGROUP feature
			$row->parameters = new JRegistry($row->attribs);

			$ingroup_fids = array_filter(preg_split('/[\s]*,[\s]*/', $row->parameters->get('fields')), function($v) { return (trim($v) !== ''); });
			$ingroup_fids = ArrayHelper::toInteger($ingroup_fids);

			// For fields of group that are included in current list, add reference to their group field
			foreach($ingroup_fids as $grouped_field_id)
			{
				if (isset($rowsByIds[$grouped_field_id]))
				{
					$rowsByIds[$grouped_field_id]->grouping_field = $row;
				}
			}

			// Check if fieldgroup field is present in current list and create its list of grouped fields
			if (isset($rowsByIds[$id]))
			{
				$rowsByIds[$id]->grouped_fields = array();

				foreach ($ingroup_fids as $grouped_field_id)
				{
					if (isset($rowsInGroup[$grouped_field_id]))
					{
						$rowsByIds[$id]->grouped_fields[] = $rowsInGroup[$grouped_field_id];
					}
				}
			}
		}


		/**
		 * Get master fields of fields in -current- page and use them to create the information
		 * needed for displaying master field information of every field cascading after them
		 */

		$rowsMasters = !count($master_fids) ? array() : $model->getItemsByConditions(array(
			'where' => array('t.id IN (' . implode(',', $master_fids) . ')'),
		));
		
		foreach ($rows as $row)
		{
			if ($row->parameters->get('cascade_after'))
			{
				$row->master_field = $rowsMasters[$row->parameters->get('cascade_after')];
			}
		}


		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

		// Create pagination object
		$pagination = $this->get('Pagination');


		/**
		 * Add usage information notices if these are enabled
		 */

		$conf_link = '<a href="index.php?option=com_config&amp;view=component&amp;component=com_flexicontent&amp;path=" class="' . $this->btn_sm_class . ' btn-info">'.JText::_("FLEXI_CONFIG").'</a>';

		if ($cparams->get('show_usability_messages', 1))
		{
			/*$conf_link = '<a href="index.php?option=com_config&view=component&component=com_flexicontent&path=" class="btn btn-info btn-small">'.JText::_("FLEXI_CONFIG").'</a>';
			$notice_content_type_order = $app->getUserStateFromRequest( $option.'.'.$view.'.notice_content_type_order',	'notice_content_type_order',	0, 'int' );
			if (!$notice_content_type_order)
			{
				$app->setUserState( $option.'.'.$view.'.notice_content_type_order', 1 );
				JFactory::getDocument()->addStyleDeclaration("#system-message-container .alert.alert-info > .alert-heading { display:none; }");

				$disable_use_notices = '<span class="fc-nowrap-box fc-disable-notices-box">'. JText::_('FLEXI_USABILITY_MESSAGES_TURN_OFF_IN').' '.$conf_link.'</span><div class="fcclear"></div>';
				$app->enqueueMessage(JText::_('FLEXI_FILTER_BY_TYPE_BEFORE_ACTIONS') .' '. $disable_use_notices, 'notice');
			}*/
		}

		$this->minihelp = '
			<div id="fc-mini-help" class="fc-mssg fc-info" style="display:none; min-width: 600px;">
				'.JText::_('FLEXI_FILTER_BY_TYPE_BEFORE_ACTIONS') .' <br/><br/>
				'.JText::_('FLEXI_FIELDS_ORDER_NO_TYPE_FILTER_ACTIVE').'
			</div>
		';


		/**
		 * Create List Filters
		 */

		$lists = array();

		// Build item type filter
		$options = array();
		foreach($types as $t)
		{
			$o = clone($t);
			if ($reOrderingActive)
			{
				$o->name = JText::_('FLEXI_ITEM_FORM') . ' : ' . $o->name;
			}
			$options[] = $o;
		}
		array_unshift($options, (object) array('id' => 0, 'name' => ($reOrderingActive ? 'FLEXI_FILTERS' : '-')/*JText::_('FLEXI_SELECT_TYPE')*/));

		if (!$filter_type)
		{
			$_img_title = JText::_('FLEXI_GLOBAL_ORDER', true);
			$_img_title_desc = JText::_('FLEXI_GLOBAL_ORDER_DESC', true);
		}
		else
		{
			$_img_title = JText::_('FLEXI_TYPE_ORDER', true);
			$_img_title_desc = JText::_('FLEXI_TYPE_ORDER_DESC', true);
		}

		$lists['filter_type'] = $this->getFilterDisplay(array(
			'label' => ($reOrderingActive ? JText::_('FLEXI_ORDER') : JText::_('FLEXI_TYPE')),
			'label_extra_class' => ($reOrderingActive ? 'fc-lbl-inverted fc-lbl-short icon-info-2 ' . $this->popover_class : ''),
			'label_extra_attrs' => array(
				'data-placement' => 'bottom',
				'data-content' => flexicontent_html::getToolTip($_img_title, $_img_title_desc, 0, 1),
			),
			'html' => flexicontent_html::buildtypesselect(
				$options,
				'filter_type',
				$filter_type,
				0,
				array(
					'class' => $this->select_class,
					'size' => '1',
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'filter_type'
			),
		));


		// Build orphaned/assigned filter
		$options 	= array();
		$options[] = JHtml::_('select.option',  '', '-'/*JText::_( 'FLEXI_ALL_FIELDS' )*/ );
		$options[] = JHtml::_('select.option',  'O', JText::_( 'FLEXI_ORPHANED' ) );
		$options[] = JHtml::_('select.option',  'A', JText::_( 'FLEXI_ASSIGNED' ) );

		$lists['filter_assigned'] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_ASSIGNED'),
			'html' => JHtml::_('select.genericlist',
				$options,
				'filter_assigned', 
				array(
					'class' => $this->select_class,
					'size' => '1',
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'value',
				'text',
				$filter_assigned
			),
		));


		// Build field-type filter
		$fieldTypes = flexicontent_db::getFieldTypes($_grouped = true, $_usage=true, $_published=false);  // Field types with content type ASSIGNMENT COUNTING
		$ALL = StringHelper::strtoupper(JText::_( 'FLEXI_ALL' )) . ' : ';
		$options = array();
		$options[] = array('value' => '', 'text' => '-'/*JText::_( 'FLEXI_ALL_FIELDS_TYPE' )*/ );
		$options[] = array('value' => 'BV', 'text' => $ALL . JText::_( 'FLEXI_BACKEND_FIELDS' ) );
		$options[] = array('value' => 'C',  'text' => $ALL . JText::_( 'FLEXI_CORE_COREPROPS_FIELDS' ) );
		$options[] = array('value' => 'NC', 'text' => $ALL . JText::_( 'FLEXI_CUSTOM_NON_CORE_FIELDS' ));
		$n = 0;

		foreach ($fieldTypes as $field_group => $ft_types)
		{
			$options[$field_group] = array();
			$options[$field_group]['id'] = 'field_group_' . ($n++);
			$options[$field_group]['text'] = $field_group;
			$options[$field_group]['items'] = array();

			foreach ($ft_types as $field_type => $ftdata)
			{
				$options[$field_group]['items'][] = array('value' => $ftdata->field_type, 'text' => '-'.$ftdata->assigned.'- '. $ftdata->friendly);
			}
		}


		// Build item type filter
		$lists['filter_fieldtype'] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_FIELD_TYPE'),
			'label_extra_class' => ($reOrderingActive ? 'fc-lbl-inverted fc-lbl-short ' : ''),
			'html' => flexicontent_html::buildfieldtypeslist(
				$options,
				'filter_fieldtype',
				$filter_fieldtype,
				($_grouped ? 1 : 0),
				array(
					'class' => $this->select_class,
					'size' => '1',
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'filter_fieldtype'
			),
		));


		// Build publication state filter
		//$options = JHtml::_('jgrid.publishedOptions');
		$options = array();

		foreach ($model_s->supported_conditions as $condition_value => $condition_name)
		{
			$options[] = JHtml::_('select.option', $condition_value, JText::_($condition_name));
		}
		array_unshift($options, JHtml::_('select.option', '', '-'/*'FLEXI_STATE'*/));

		$fieldname = 'filter_state';
		$elementid = 'filter_state';

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_STATE'),
			'html' => JHtml::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'class' => $this->select_class,
					'size' => '1',
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'value',
				'text',
				$filter_state,
				$elementid,
				$translate = true
			),
		));


		// Build access level filter
		$options = JHtml::_('access.assetgroups');
		array_unshift($options, JHtml::_('select.option', '', '-'/*'JOPTION_SELECT_ACCESS'*/));

		$fieldname = 'filter_access';
		$elementid = 'filter_access';

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_ACCESS'),
			'html' => JHtml::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'class' => $this->select_class,
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'value',
				'text',
				$filter_access,
				$elementid,
				$translate = true
			)
		));


		// Build text search scope
		$scopes = null;

		$lists['scope_tip'] = '';
		$lists['scope'] = $this->getScopeSelectorDisplay($scopes, $scope);
		$this->scope_title = isset($scopes[$scope]) ? $scopes[$scope] : reset($scopes);


		// Text search filter value
		$lists['search'] = $search;


		// Table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order']     = $filter_order;


		/**
		 * Assign data to template
		 */

		$this->count_filters = $count_filters;
		$this->filter_type   = $filter_type;

		$this->lists       = $lists;
		$this->rows        = $rows;

		$this->types       = $types;
		$this->pagination  = $pagination;

		$this->reOrderingActive = $reOrderingActive;

		$this->perms  = FlexicontentHelperPerm::getPerm();
		$this->option = $option;
		$this->view   = $view;
		$this->state  = $this->get('State');

		if (!$jinput->getCmd('nosidebar'))
		{
			$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;
		}

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
		$session  = JFactory::getSession();
		$useAssocs= flexicontent_db::useAssociations();

		$js = '';

		$contrl = $this->ctrl . '.';
		$contrl_s = $this->name_singular . '.';

		$loading_msg = flexicontent_html::encodeHTML(JText::_('FLEXI_LOADING') .' ... '. JText::_('FLEXI_PLEASE_WAIT'), 2);

		// Get if state filter is active
		$model   = $this->getModel();
		$model_s = $this->getModel($this->name_singular);
		$filter_state = $model->getState('filter_state');

		$hasCreate    = $perms->CanAddField;
		$hasEdit      = $perms->CanEditField;
		$hasEditState = $perms->CanFields;
		$hasDelete    = $perms->CanDeleteField;
		$hasCopy      = $perms->CanCopyFields;


		if ($hasCreate)
		{
			JToolbarHelper::addNew($contrl.'add');
		}

		if (0 && $hasEdit)
		{
			JToolbarHelper::editList($contrl.'edit');
		}

		$btn_arr = array();
		$states_applicable = array();

		if ($hasEditState)
		{
			$states_applicable = $model_s->supported_conditions;
			unset($states_applicable[-2]);

			/*
			JToolbarHelper::publishList($contrl.'publish', 'JTOOLBAR_PUBLISH', true);
			JToolbarHelper::unpublishList($contrl.'unpublish', 'JTOOLBAR_UNPUBLISH', true);
			JToolbarHelper::archiveList($contrl.'archive', 'JTOOLBAR_ARCHIVE', true);
			*/
		}


		/**
		 * Delete data buttons (Record , Assignments, Assignments + Record)
		 */
		if ($filter_state == -2 && $hasDelete)
		{
			$btn_arr = array();

			//JToolbarHelper::deleteList(JText::_('FLEXI_ARE_YOU_SURE'), $contrl.'remove');
			$msg_alert   = JText::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_DELETE'));
			$msg_confirm = JText::_('FLEXI_ARE_YOU_SURE');
			$btn_task    = $contrl.'remove';
			$extra_js    = "";
			$btn_arr[] = flexicontent_html::addToolBarButton(
				'FLEXI_RECORDS', 'delete', '', $msg_alert, $msg_confirm,
				$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true,
				$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . 'btn-warning' : '') . ' ' . $this->tooltip_class, 'icon-remove',
				'data-placement="right" title="' . flexicontent_html::encodeHTML(JText::_('FLEXI_ABOUT_DELETING_RECORDS_WITHOUT_ASSIGNMENTS'), 2) . '"', $auto_add = 0, $tag_type='button'
			);

			if ($model::canDelRelated)
			{
				$msg_alert   = JText::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_ASSIGNMENTS'));
				$msg_confirm = JText::_('FLEXI_ARE_YOU_SURE');
				$btn_task    = $contrl.'remove_relations';
				$extra_js    = "";
				$btn_arr[] = flexicontent_html::addToolBarButton(
					'FLEXI_ASSIGNMENTS', 'delete', '', $msg_alert, $msg_confirm,
					$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true,
					$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . 'btn-warning' : '') . ' ' . $this->tooltip_class, 'icon-remove',
					'data-placement="right" title="' . flexicontent_html::encodeHTML(JText::_('FLEXI_ABOUT_DELETING_ASSIGNMENTS'), 2) . '"', $auto_add = 0, $tag_type='button'
				);

				$msg_alert   = JText::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_ASSIGNMENTS_N_RECORDS'));
				$msg_confirm = JText::_('FLEXI_ARE_YOU_SURE');
				$btn_task    = $contrl.'remove_cascade';
				$extra_js    = "";
				$btn_arr[] = flexicontent_html::addToolBarButton(
					'FLEXI_ASSIGNMENTS_N_RECORDS', 'delete', '', $msg_alert, $msg_confirm,
					$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true,
					$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . 'btn-warning' : '') . ' ' . $this->tooltip_class, 'icon-remove',
					'data-placement="right" title="' . flexicontent_html::encodeHTML(JText::_('FLEXI_ABOUT_DELETING_ASSIGNMENTS_N_RECORDS'), 2) . '"', $auto_add = 0, $tag_type='button'
				);
			}

			$drop_btn = '
				<button id="toolbar-delete" class="' . $this->btn_sm_class . ' dropdown-toggle btn-fcaction" data-toggle="dropdown" data-bs-toggle="dropdown">
					<span title="'.JText::_('FLEXI_DELETE').'" class="icon-delete"></span>
					'.JText::_('FLEXI_DELETE').'
					<span class="caret"></span>
				</button>';
			array_unshift($btn_arr, $drop_btn);
			flexicontent_html::addToolBarDropMenu($btn_arr, 'action_btns_group', ' ');
		}
		elseif ($hasEdit)
		{
			if (isset($model_s->supported_conditions[-2]))
			{
				$states_applicable['T'] = 0;
				//JToolbarHelper::trash($contrl.'trash');
			}
		}

		$btn_arr = $this->getStateButtons($states_applicable);
		$this->addStateButtons($btn_arr);


		/**
		 * Search flags
		 */
		if ($hasEdit)
		{
			$ctrl_task = '&task=fields.selectsearchflag';
			$popup_load_url = JUri::base(true) . '/index.php?option=com_flexicontent'.$ctrl_task.'&tmpl=component';

			$btn_name = 'basicindex';
				$full_js="var url = jQuery(this).data('taskurl'); fc_showDialog(url, 'fc_modal_popup_container', 0, 550, 0, function(){document.body.innerHTML='<span class=\"fc_loading_msg\">"
							.$loading_msg."<\/span>'; window.location.reload(false)}, {'title': '".flexicontent_html::encodeHTML(JText::_('FLEXI_TOGGLE_SEARCH_FLAG'), 2)."'}); return false;";

				flexicontent_html::addToolBarButton(
					'FLEXI_TOGGLE_SEARCH_FLAG', $btn_name, $full_js,
					$msg_alert = JText::_('FLEXI_SELECT_FIELDS_TO_TOGGLE_PROPERTY'), $msg_confirm = '',
					$btn_task='', $extra_js='', $btn_list=true, $btn_menu=true, $btn_confirm=false,
					$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? $this->btn_iv_class : '') . ' ' . $this->popover_class, 'icon-upload',
					'data-placement="right" data-taskurl="' . $popup_load_url .'" data-content="' . flexicontent_html::encodeHTML(JText::_('FLEXI_MASS_TAGS_IMPORT_DESC'), 2) . '"', $auto_add = 1, $tag_type='button'
				);

		}


		/**
		 * Copy record
		 */
		if ($hasCopy)
		{
			JToolbarHelper::custom($contrl.'copy', 'copy.png', 'copy_f2.png', 'FLEXI_COPY');
			JToolbarHelper::custom( $contrl.'copy_wvalues', 'copy_wvalues.png', 'copy_f2.png', 'FLEXI_COPY_WITH_VALUES' );
		}


		/**
		 * Maintenance button (Check-in, Verify Tag mappings, Assignments + Record)
		 */

		$btn_arr = array();

		//JToolbarHelper::checkin($contrl . 'checkin');
		$btn_task  = $contrl . 'checkin';
		$btn_arr[] = flexicontent_html::addToolBarButton(
			'JTOOLBAR_CHECKIN', $btn_name = 'checkin', $full_js = '',
			$msg_alert = '', $msg_confirm = '',
			$btn_task, $extra_js = '', $btn_list=true, $btn_menu=true, $btn_confirm=false,
			$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . $this->btn_iv_class : '') . ' ' . $this->popover_class, $btn_icon='icon-checkin',
			'data-placement="right" data-content="' . flexicontent_html::encodeHTML(JText::_('FLEXI_MAINTENANCE_CHECKIN_DESC'), 2) . '"', $auto_add = 0, $tag_type='button'
		);

		$appsman_path = JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'views'.DS.'appsman';
		if (file_exists($appsman_path))
		{
			$btn_icon = 'icon-download';
			$btn_name = 'download';
			$btn_task = 'appsman.exportxml';
			$extra_js = " var f=document.getElementById('adminForm'); f.elements['view'].value='appsman'; jQuery('<input>').attr({type: 'hidden', name: 'table', value: '" . $this->db_tbl . "'}).appendTo(jQuery(f));";
			$btn_arr[] = flexicontent_html::addToolBarButton(
				'Export now',
				$btn_name, $full_js='', $msg_alert='', $msg_confirm=JText::_('FLEXI_EXPORT_NOW_AS_XML'),
				$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true,
				$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . $this->btn_iv_class : '') . ' ' . $this->popover_class, $btn_icon,
				'data-placement="right" data-content="' . flexicontent_html::encodeHTML(JText::_(''), 2) . '"', $auto_add = 0, $tag_type='button'
			);

			$btn_icon = 'icon-box-add';
			$btn_name = 'box-add';
			$btn_task = 'appsman.addtoexport';
			$extra_js = " var f=document.getElementById('adminForm'); f.elements['view'].value='appsman'; jQuery('<input>').attr({type: 'hidden', name: 'table', value: '" . $this->db_tbl . "'}).appendTo(jQuery(f));";
			$btn_arr[] = flexicontent_html::addToolBarButton(
				'Add to export',
				$btn_name, $full_js='', $msg_alert='', $msg_confirm=JText::_('FLEXI_ADD_TO_EXPORT_LIST'),
				$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true,
				$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . $this->btn_iv_class : '') . ' ' . $this->popover_class, $btn_icon,
				'data-placement="right" data-content="' . flexicontent_html::encodeHTML(JText::_(''), 2) . '"', $auto_add = 0, $tag_type='button'
			);

			$btn_icon = 'icon-download';
			$btn_name = 'download';
			$btn_task = 'appsman.exportsql';
			$extra_js = "";
			$btn_arr[] = flexicontent_html::addToolBarButton(
				'Export SQL', $btn_name, $full_js='', $msg_alert='', $msg_confirm='Field\'s configuration will be exported as SQL',
				$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true,
				$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . $this->btn_iv_class : '') . ' ' . $this->popover_class, $btn_icon,
				'data-placement="right" data-content="' . flexicontent_html::encodeHTML(JText::_(''), 2) . '"', $auto_add = 0, $tag_type='button'
			);

			$btn_icon = 'icon-download';
			$btn_name = 'download';
			$btn_task = 'appsman.exportcsv';
			$extra_js = "";
			flexicontent_html::addToolBarButton(
				'Export CSV', $btn_name, $full_js='', $msg_alert='', $msg_confirm='Field\'s configuration will be exported as CSV',
				$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true,
				$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . $this->btn_iv_class : '') . ' ' . $this->popover_class, $btn_icon,
				'data-placement="right" data-content="' . flexicontent_html::encodeHTML(JText::_(''), 2) . '"', $auto_add = 0, $tag_type='button'
			);
		}

		if (count($btn_arr))
		{
			$drop_btn = '
				<button id="toolbar-maintenance" class="' . $this->btn_sm_class . ' dropdown-toggle btn-fcaction" data-toggle="dropdown" data-bs-toggle="dropdown">
					<span title="'.JText::_('FLEXI_MAINTENANCE').'" class="icon-tools"></span>
					'.JText::_('FLEXI_MAINTENANCE').'
					<span class="caret"></span>
				</button>';

			array_unshift($btn_arr, $drop_btn);
			flexicontent_html::addToolBarDropMenu($btn_arr, 'maintenance-btns-group', ' ');
		}


		if ($perms->CanConfig)
		{
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolbarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
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
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
 * HTML View class for the categories backend manager
 */
class FlexicontentViewCategories extends FlexicontentViewBaseRecords
{
	var $proxy_option   = 'com_categories';
	var $title_propname = 'title';
	var $state_propname = 'published';
	var $db_tbl         = 'categories';
	var $name_singular  = 'category';

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

		$isAdmin  = $app->isClient('administrator');
		$isCtmpl  = $jinput->getCmd('tmpl') === 'component';

		// Some flags & constants
		$useAssocs = flexicontent_db::useAssociations();
		$order_property = 'a.lft';

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
		$filter_assockey  = $model->getState('filter_assockey');
		$filter_state     = $model->getState('filter_state');
		$filter_cats      = $model->getState('filter_cats');
		$filter_level     = $model->getState('filter_level');
		$filter_access    = $model->getState('filter_access');
		$filter_lang      = $model->getState('filter_lang');
		$filter_author    = $model->getState('filter_author');

		if ($filter_assockey) $count_filters++;
		if (strlen($filter_state)) $count_filters++;
		if ($filter_cats) $count_filters++;
		if ($filter_level) $count_filters++;
		if (strlen($filter_access)) $count_filters++;
		if ($filter_lang) $count_filters++;
		if (strlen($filter_author)) $count_filters++;

		// Record ID filter
		$filter_id = $model->getState('filter_id');
		if (strlen($filter_id)) $count_filters++;


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
		FLEXIUtilities::ManagerSideMenu('CanCats');

		// Create document/toolbar titles
		$doc_title = JText::_('FLEXI_CATEGORIES');
		$site_title = $document->getTitle();
		JToolbarHelper::title($doc_title, 'icon-folder');
		$document->setTitle($doc_title .' - '. $site_title);

		// Create the toolbar
		$this->setToolbar();


		/**
		 * Get data from the model, note data retrieval must be before 
		 * getTotal() and getPagination() because it also calculates total rows
		 */

		if ( $print_logging_info )  $start_microtime = microtime(true);

		$rows        = $model->getItems();
		$authors     = $model->getAuthorslist();

		$author_names = array();
		foreach($authors as $author)
		{
			$author_names[$author->id] = $author->name;
		}
		foreach($rows as $row)
		{
			$row->author = $author_names[$row->created_user_id];
		}

		$lang_assocs = $useAssocs ? $model->getLangAssocs() : array();
		$langs       = FLEXIUtilities::getLanguages('code');
		$categories  = $globalcats ?: array();

		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

		// Create pagination object
		$pagination = $this->get('Pagination');


		/**
		 * Get assigned items (via separate query), do this is if not already retrieved
		 */

		if (1)
		{
			$rowids = array();

			foreach ($rows as $row)
			{
				$rowids[] = $row->id;
			}

			if ( $print_logging_info )  $start_microtime = microtime(true);
			//$rowtotals = $model->getAssignedItems($rowids);
			$byStateTotals = $model->countItemsByState($rowids);
			if ( $print_logging_info ) @$fc_run_times['execute_sec_queries'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

			foreach ($rows as $row)
			{
				//$row->nrassigned = isset($rowtotals[$row->id]) ? $rowtotals[$row->id]->nrassigned : 0;
				$row->byStateTotals = isset($byStateTotals[$row->id]) ? $byStateTotals[$row->id] : array();
			}
		}

		// Parse configuration for every category
   	foreach ($rows as $cat)
		{
			$cat->config = new JRegistry($cat->params);
		}

		// Preprocess the list of items to find ordering divisions.
		foreach ($rows as $item)
		{
			$this->ordering[$item->parent_id][] = $item->id;
		}


		/**
		 * Add usage information notices if these are enabled
		 */

		$conf_link = '<a href="index.php?option=com_config&amp;view=component&amp;component=com_flexicontent&amp;path=" class="' . $this->btn_sm_class . ' btn-info">'.JText::_("FLEXI_CONFIG").'</a>';

		if ($cparams->get('show_usability_messages', 1))
		{
		}


		/**
		 * Create List Filters
		 */

		$lists = array();

		$lists['copyid'] = flexicontent_cats::buildcatselect($categories, 'copycid', '', 2, 'class="use_select2_lib"', false, true, $actions_allowed=array('core.edit'));
		$lists['destid'] = flexicontent_cats::buildcatselect($categories, 'destcid[]', '', false, 'class="use_select2_lib" size="10" multiple="true"', false, true, $actions_allowed=array('core.edit'));



		// Build category filter
		$fieldname = 'filter_cats';
		$elementid = 'filter_cats';
		$value     = $filter_cats;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_CATEGORY'),
			'label_extra_class' => ($value ? ' fc-lbl-inverted' : ''),
			'html' => flexicontent_cats::buildcatselect(
				$categories,
				$fieldname,
				$value,
				$displaytype = '-',
				array(
					'class' => $this->select_class,
					'size' => '1',
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				$check_published = true,
				$check_perms = false
			),
		));


		// Build depth level filter
		$options = array(
			JHtml::_('select.option', '', '-'/*'FLEXI_SELECT_MAX_DEPTH'*/),
		);

		for ($i = 1; $i <= 10; $i++)
		{
			$options[]	= JHtml::_('select.option', $i, $i);
		}

		$fieldname = 'filter_level';
		$elementid = 'filter_level';
		$value     = $filter_level;

		if (1)
		{
			$lists[$elementid] = $this->getFilterDisplay(array(
				'label' => JText::_('FLEXI_MAX_DEPTH'),
				'label_extra_class' => ($value ? ' fc-lbl-inverted' : ''),
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
					$value,
					$elementid,
					$translate = true
				),
			));
		}

		// Build author filter
		$fieldname = 'filter_author';
		$elementid = 'filter_author';
		$value     = $filter_author;

		if (1)
		{
			$lists[$elementid] = $this->getFilterDisplay(array(
				'label' => JText::_('FLEXI_AUTHOR'),
				'label_extra_class' => (strlen($value) ? ' fc-lbl-inverted' : ''),
				'html' => flexicontent_html::buildauthorsselect(
					$authors,
					$fieldname,
					$value,
					$displaytype = '-',
					array(
						'class' => $this->select_class,
						'size' => '1',
						'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
					)
				),
			));
		}


		// Build publication state filter
		//$options = JHtml::_('jgrid.publishedOptions');
		$options = array();
		$options[] = JHtml::_('select.option', 'ALL', JText::_('FLEXI_GRP_ALL') . ' ' . JText::_('FLEXI_STATE_S'));

		foreach ($model_s->supported_conditions as $condition_value => $condition_name)
		{
			$options[] = JHtml::_('select.option', $condition_value, $condition_name);
		}
		array_unshift($options, JHtml::_('select.option', '', '-'/*'FLEXI_SELECT_STATE'*/));

		$fieldname = 'filter_state';
		$elementid = 'filter_state';
		$value     = $filter_state;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_STATE'),
			'label_extra_class' => (strlen($value) ? ' fc-lbl-inverted' : ''),
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
				$value,
				$elementid,
				$translate = true
			),
		));


		// Build access level filter
		$options = JHtml::_('access.assetgroups');
		array_unshift($options, JHtml::_('select.option', '', '-'/*'JOPTION_SELECT_ACCESS'*/));

		$fieldname = 'filter_access';
		$elementid = 'filter_access';
		$value     = $filter_access;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_ACCESS'),
			'label_extra_class' => (strlen($value) ? ' fc-lbl-inverted' : ''),
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
				$value,
				$elementid,
				$translate = true
			),
		));


		// Build language filter
		$fieldname = 'filter_lang';
		$elementid = 'filter_lang';
		$value     = $filter_lang;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_LANGUAGE'),
			'label_extra_class' => (strlen($value) ? ' fc-lbl-inverted' : ''),
			'html' => flexicontent_html::buildlanguageslist(
				$fieldname,
				array(
					'class' => $this->select_class,
					'size' => '1',
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				$value,
				'-'
			)
		));


		// Build record id filter
		$fieldname = 'filter_id';
		$elementid = 'filter_id';
		$value     = $filter_id;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_ID'),
			'label_extra_class' => (strlen($value) ? ' fc-lbl-inverted' : ''),
			'html' => '<input type="text" name="'.$fieldname.'" id="'.$elementid.'" size="6" value="' . $value . '" class="fcfield_textval" style="width:auto;" />',
		));


		/**
		 * Filter by item usage a specific file
		 */

		$fieldname = 'filter_assockey';
		$elementid = 'filter_assockey';
		$value     = $filter_assockey;

		if ($filter_assockey)
		{
			$lists[$elementid] = $this->getFilterDisplay(array(
				'label' => JText::_('FLEXI_ASSOCIATIONS'),
				'label_extra_class' => ' fc-lbl-short' . ($value ? ' fc-lbl-inverted' : ''),
				'html' => '
					<div class="group-fcset" style="display: inline-block;">
							<input type="checkbox" id="'.$elementid.'" name="'.$fieldname.'" onchange="if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform()" value="'.$value.'" '.($value ? ' checked="checked" ' : '').' />
							<label id="'.$elementid.'-lbl" for="'.$elementid.'" style="margin: 0 12px; vertical-align: middle; border: 0;"></label>
					</div>',
			));
		}
		else
		{
			$lists[$elementid] = '';
		}


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

		$orderingx = $lists['order'] == $order_property && strtolower($lists['order_Dir']) == 'asc'
			? $order_property
			: '';


		/**
		 * Assign data to template
		 */

		$this->count_filters = $count_filters;

		$this->lists       = $lists;
		$this->rows        = $rows;
		$this->lang_assocs = $lang_assocs;
		$this->langs       = $langs;
		$this->pagination  = $pagination;
		$this->orderingx   = $orderingx;

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

		if ($user->authorise('core.create', 'com_flexicontent'))
		{
			$cancreate_cat = true;
		}
		else
		{
			$usercats = FlexicontentHelperPerm::getAllowedCats(
				$user,
				$actions_allowed = array('core.create'),
				$require_all = true,
				$check_published = true,
				$specific_catids = false,
				$find_first = true
			);
			$cancreate_cat  = count($usercats) > 0;
		}

		$hasCreate    = $cancreate_cat;
		$hasEdit      = $perms->CanEdit    || $perms->CanEditOwn;
		$hasEditState = $perms->CanPublish || $perms->CanPublishOwn;
		$hasDelete    = $perms->CanDelete  || $perms->CanDeleteOwn;

		if ($hasCreate)
		{
			JToolbarHelper::addNew($contrl_s.'add');
		}

		if (0 && $hasEdit)
		{
			JToolbarHelper::editList($contrl_s.'edit');
		}

		$btn_arr = array();
		$states_applicable = array();

		if ($hasEditState)
		{
			$states_applicable = array_merge($states_applicable, array('P' => 0, 'U' => 0, 'A' => 0));

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
			flexicontent_html::addToolBarButton(
				'FLEXI_DELETE', 'delete', '', $msg_alert, $msg_confirm,
				$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true);
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
		 * Copy Parameters
		 */
		$btn_task = '';
		$popup_load_url = JUri::base(true) . '/index.php?option=com_flexicontent&view=categories&layout=params&tmpl=component';
		//$toolbar->appendButton('Popup', 'params', JText::_('FLEXI_COPY_PARAMS'), str_replace('&', '&amp;', $popup_load_url), 600, 440);
		$js .= "
			jQuery('#toolbar-params a.toolbar, #toolbar-params button')
				.attr('href', '".$popup_load_url."')
				.attr('onclick', 'var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 600, 440, function(){document.body.innerHTML=\'<span class=\"fc_loading_msg\">"
					.$loading_msg."<\/span>\'; window.location.reload(false)}, {\'title\': \'".flexicontent_html::encodeHTML(JText::_('FLEXI_COPY_PARAMS'), 2)."\'}); return false;');
		";
		JToolbarHelper::custom( $btn_task, 'params.png', 'params_f2.png', 'FLEXI_COPY_CONFIG', false );

		//$toolbar->appendButton('Popup', 'move', JText::_('FLEXI_BATCH'), JUri::base(true) . '/index.php?option=com_flexicontent&amp;view=categories&amp;layout=batch&amp;tmpl=component', 800, 440);


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

		if ($perms->CanConfig)
		{
			//JToolbarHelper::custom($contrl.'rebuild', 'refresh.png', 'refresh_f2.png', 'JTOOLBAR_REBUILD', false);

			$btn_task  = $contrl . 'rebuild';
			$btn_arr[] = flexicontent_html::addToolBarButton(
				'JTOOLBAR_REBUILD', $btn_name = 'rebuild', $full_js = '',
				$msg_alert = '', $msg_confirm = '',
				$btn_task, $extra_js = '', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . $this->btn_iv_class : '') . ' ' . $this->popover_class, $btn_icon='icon-refresh',
				'data-placement="right" data-content="' . flexicontent_html::encodeHTML(JText::_(''), 2) . '"', $auto_add = 0, $tag_type='button'
			);

			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolbarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}

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
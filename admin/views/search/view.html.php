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
use Joomla\CMS\Toolbar\ToolbarFactoryInterface;

JLoader::register('FlexicontentViewBaseRecords', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/base/view_records.php');

/**
 * View class for the FLEXIcontent search indexes screen
 */
class FLEXIcontentViewSearch extends FlexicontentViewBaseRecords
{
	var $proxy_option   = null;
	var $title_propname = null;
	var $state_propname = null;
	var $db_tbl         = null;

	public function display($tpl = null)
	{
		/**
		 * Initialise variables
		 */

		global $globalcats;
		$app      = \Joomla\CMS\Factory::getApplication();
		$jinput   = $app->input;
		$document = \Joomla\CMS\Factory::getApplication()->getDocument();
		$user     = \Joomla\CMS\Factory::getApplication()->getIdentity();
		$cparams  = \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent');
		$session  = \Joomla\CMS\Factory::getApplication()->getSession();
		$db       = \Joomla\CMS\Factory::getContainer()->get(DatabaseInterface::class);

		$option   = $jinput->getCmd('option', '');
		$view     = $jinput->getCmd('view', '');
		$task     = $jinput->getCmd('task', '');
		$layout   = $jinput->getString('layout', 'default');

		if ($layout === 'indexer')
		{
			return parent::display($tpl);
		}

		$isAdmin  = $app->isClient('administrator');
		$isCtmpl  = $jinput->getCmd('tmpl') === 'component';

		// Some flags & constants
		$useAssocs = flexicontent_db::useAssociations();

		// Load Joomla language files of other extension
		if (!empty($this->proxy_option))
		{
			\Joomla\CMS\Factory::getApplication()->getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, 'en-GB', true);
			\Joomla\CMS\Factory::getApplication()->getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, null, true);
		}

		// Get model
		$model   = $this->getModel();
		$model_s = null;

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
		$filter_indextype = $model->getState('filter_indextype');
		$isADV = $filter_indextype === 'advanced';

		$filter_itemlang  = $model->getState('filter_itemlang');
		$filter_fieldtype = $model->getState('filter_fieldtype');
		$filter_type      = $model->getState('filter_type');
		$filter_state     = $model->getState('filter_state');

		if ($filter_itemlang) $count_filters++;
		if ($filter_fieldtype) $count_filters++;
		if ($filter_type) $count_filters++;
		if (strlen($filter_state)) $count_filters++;

		// Text search
		$scope  = $model->getState('scope');
		$search = $model->getState('search');
		$search = StringHelper::trim(StringHelper::strtolower($search));


		$search_itemtitle	= $model->getState( 'search_itemtitle' );
		$search_itemid		= $model->getState( 'search_itemid' );
		$search_itemid		= !empty($search_itemid) ? (int)$search_itemid : '';
		if ($search_itemtitle) $count_filters++; if ($search_itemid) $count_filters++;

		$filter_indextype	= $model->getState( 'filter_indextype' );

		$f_active['filter_itemlang']	= (boolean)$filter_itemlang;
		$f_active['filter_fieldtype']	= (boolean)$filter_fieldtype;
		$f_active['filter_type']	= (boolean)$filter_type;
		$f_active['filter_state']	= (boolean)$filter_state;

		$f_active['search']			= strlen($search);
		$f_active['search_itemtitle']	= strlen($search_itemtitle);
		$f_active['search_itemid']		= (boolean)$search_itemid;


		/**
		 * Add css and js to document
		 */

		if ($layout !== 'indexer')
		{
			// Add css to document
			if ($isAdmin)
			{
				!\Joomla\CMS\Factory::getApplication()->getLanguage()->isRtl()
					? $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', array('version' => FLEXI_VHASH))
					: $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', array('version' => FLEXI_VHASH));
				!\Joomla\CMS\Factory::getApplication()->getLanguage()->isRtl()
					? $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x.css' : 'j3x.css'), array('version' => FLEXI_VHASH))
					: $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x_rtl.css' : 'j3x_rtl.css'), array('version' => FLEXI_VHASH));
			}
			else
			{
				!\Joomla\CMS\Factory::getApplication()->getLanguage()->isRtl()
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
		FLEXIUtilities::ManagerSideMenu('CanIndex');

		// Create document/toolbar titles
		$doc_title = \Joomla\CMS\Language\Text::_( 'FLEXI_SEARCH_INDEX' );
		$site_title = $document->getTitle();
		\Joomla\CMS\Toolbar\ToolbarHelper::title( $doc_title, 'search' );
		$document->setTitle($doc_title .' - '. $site_title);

		// Create the toolbar
		$this->setToolbar();


		/**
		 * Get data from the model, note data retrieval must be before 
		 * getTotal() and getPagination() because it also calculates total rows
		 */

		if ( $print_logging_info )  $start_microtime = microtime(true);

		$rows        = $model->getData();
		$pagination  = $model->getPagination();

		// Get item types
		$types = $this->get('Typeslist');

		// Get field types
		$fieldtypes = flexicontent_db::getFieldTypes($_grouped=false, $_usage=true, $_published=false);

		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;



		/**
		 * Create List Filters
		 */

		$lists = array();

		$js = '';

		// Build field type filter
		if ($isADV)
		{
			$fftypes = array();
			$fftypes[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option',  '', '-' /*\Joomla\CMS\Language\Text::_( 'FLEXI_ALL_FIELDS_TYPE' )*/ );
			$fftypes[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option',  'C', \Joomla\CMS\Language\Text::_( 'FLEXI_CORE_FIELDS' ) );
			$fftypes[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option',  'NC', \Joomla\CMS\Language\Text::_( 'FLEXI_CUSTOM_NON_CORE_FIELDS' ) );
			foreach ($fieldtypes as $field_type => $ftdata)
			{
				$fftypes[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option', $field_type, '-'.$ftdata->assigned.'- '. $field_type);
			}

			$lists['filter_fieldtype'] = $this->getFilterDisplay(array(
				'label' => \Joomla\CMS\Language\Text::_('FLEXI_FIELD_TYPE'),
				'html' => \Joomla\CMS\HTML\HTMLHelper::_('select.genericlist',
					$fftypes,
					'filter_fieldtype',
					array(
						'class' => $this->select_class,
						'size' => '1',
						'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
					),
					'value',
					'text',
					$filter_fieldtype
				),
			));
		}


		// Build item language filter
		$lists['filter_itemlang'] = $this->getFilterDisplay(array(
			'label' => \Joomla\CMS\Language\Text::_('FLEXI_LANGUAGE'),
			'html' => flexicontent_html::buildlanguageslist(
				'filter_itemlang',
				array(
					'class' => $this->select_class,
					'size' => '1',
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				$filter_itemlang,
				'-'
			)
		));


		// Build item type filter
		$lists['filter_type'] = $this->getFilterDisplay(array(
			'label' => \Joomla\CMS\Language\Text::_('FLEXI_TYPE'),
			'html' => flexicontent_html::buildtypesselect(
				$types,
				'filter_type',
				$filter_type,
				'-'/*true*/,
				array(
					'class' => $this->select_class,
					'size' => '1',
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'filter_type'
			),
		));

		// Build state filter (grouping published and unpublished states)
		$ffstates = array();
		$ffstates[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option',  '', '-' /*\Joomla\CMS\Language\Text::_( 'FLEXI_SELECT_STATE' )*/ );
		$ffstates[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option',  'ALL_P', \Joomla\CMS\Language\Text::_('FLEXI_GRP_PUBLISHED') . ' ' . \Joomla\CMS\Language\Text::_('FLEXI_STATE_S'));
		$ffstates[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option',  'ALL_U', \Joomla\CMS\Language\Text::_('FLEXI_GRP_UNPUBLISHED') . ' ' . \Joomla\CMS\Language\Text::_('FLEXI_STATE_S'));

		$lists['filter_state'] = $this->getFilterDisplay(array(
			'label' => \Joomla\CMS\Language\Text::_('FLEXI_STATE'),
			'html' => \Joomla\CMS\HTML\HTMLHelper::_('select.genericlist',
				$ffstates,
				'filter_state',
				array(
					'class' => $this->select_class,
					'size' => '1',
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'value',
				'text',
				$filter_state
			),
		));

		// Build index type filter
		$itn['basic'] = \Joomla\CMS\Language\Text::_( 'FLEXI_INDEX_BASIC' );
		$itn['advanced'] = \Joomla\CMS\Language\Text::_( 'FLEXI_INDEX_ADVANCED' );
		$indextypes = array();
		//foreach ($itn as $i => $v) $indextypes[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option', $i, $v);
		//$lists['filter_indextype'] = \Joomla\CMS\HTML\HTMLHelper::_('select.radiolist', $indextypes, 'filter_indextype', 'size="1" class="inputbox" onchange="if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_indextype );

		$lists['filter_indextype'] = '<div class="fc-iblock group-fcset radio" style="vertical-align: middle">';

		foreach ($itn as $i => $v)
		{
			$checked = $filter_indextype == $i ? ' checked="checked" ' : '';
			$lists['filter_indextype'] .= '<input type="radio" onchange="if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform()" class="inputbox" size="1" '.$checked.' value="'.$i.'" id="filter_indextype'.$i.'" name="filter_indextype" />';
			$lists['filter_indextype'] .= '<label class="" id="filter_indextype'.$i.'-lbl" for="filter_indextype'.$i.'">'.$v.'</label>';
		}

		$lists['filter_indextype'] .= '</div>';


		// Build text search scope
		$scopes = array(
			'-1' => \Joomla\CMS\Language\Text::_('FLEXI_INDEXED_CONTENT'),
		);

		$lists['scope_tip'] = '';
		$lists['scope'] = $this->getScopeSelectorDisplay($scopes, $scope);
		$this->scope_title = isset($scopes[$scope]) ? $scopes[$scope] : reset($scopes);


		// Text search filter value
		$lists['search'] = $search;


		// Table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order']     = $filter_order;


		// Build item title filter
		$lists['search_itemtitle'] = $this->getFilterDisplay(array(
			'label' => \Joomla\CMS\Language\Text::_('FLEXI_TITLE'),
			'html' => '<input type="text" name="search_itemtitle" id="search_itemtitle" value="' . htmlspecialchars($search_itemtitle, ENT_QUOTES, 'UTF-8') . '" class="text_area" onchange="document.adminForm.submit();" size="30"/>',
		));


		// Build item id filter
		$lists['search_itemid'] = $this->getFilterDisplay(array(
			'label' => \Joomla\CMS\Language\Text::_('FLEXI_ID'),
			'html' => '<input type="text" name="search_itemid" id="search_itemid" value="' . htmlspecialchars($search_itemid, ENT_QUOTES, 'UTF-8') . '" class="text_area" onchange="document.adminForm.submit();" size="6" />',
		));

		if ($filter_fieldtype)
		{
			$js .= "jQuery('.col_fieldtype').addClass('filtered_column');";
		}

		if ($search)
		{
			$js .= "jQuery('.col_search').addClass('filtered_column');";
		}

		if ($js)
		{
			$document->addScriptDeclaration('
				jQuery(document).ready(function(){
					' . $js . '
				});
			');
		}

		// Add modal edit code
		if (1)
		{
			\Joomla\CMS\Language\Text::script("FLEXI_UPDATING_CONTENTS", true);
			$document->addScriptDeclaration('
				function fc_edit_fcitem_modal_load( container )
				{
					if ( container.find("iframe").get(0).contentWindow.location.href.indexOf("view=items") != -1 )
					{
						container.dialog("close");
					}
				}
				function fc_edit_fcitem_modal_close()
				{
					//window.location.reload(false);
					window.location.href = \'index.php?option=com_flexicontent&view=search\';
					document.body.innerHTML = "<div>" + Joomla.JText._("FLEXI_UPDATING_CONTENTS") + \' <img id="page_loading_img" src="components/com_flexicontent/assets/images/ajax-loader.gif"></div>\';
				}
			');
		}

		$query = "SHOW VARIABLES LIKE '%ft_min_word_len%'";
		$db->setQuery($query);
		$_dbvariable = $db->loadObject();
		$ft_min_word_len = (int) @ $_dbvariable->Value;
		$notice_ft_min_word_len	= $app->getUserStateFromRequest( $option.'.fields.notice_ft_min_word_len',	'notice_ft_min_word_len',	0, 'int' );
		//if ( $cparams->get('show_usability_messages', 1) )     // Important usability messages
		//{

		$old_add_search_prefix = $app->getUserState('add_search_prefix', null);

		$add_search_prefix = $cparams->get('add_search_prefix', 0);
		$app->setUserState('add_search_prefix', $add_search_prefix);


		if ($old_add_search_prefix !== null && $old_add_search_prefix != $add_search_prefix) {
			$app->enqueueMessage('Parameter: "Searching small/common words" has changed, please recreate (just once) the search indexes, otherwise text search will not work', 'warning');
		}

		// Important usability messages
		if (!$cparams->get('add_search_prefix', 0))
		{
			if ($ft_min_word_len > 1 && $notice_ft_min_word_len < 10)
			{
				$app->setUserState( $option.'.fields.notice_ft_min_word_len', $notice_ft_min_word_len+1 );
				$app->enqueueMessage("NOTE : Database limits minimum search word length (ft_min_word_len) to ".$ft_min_word_len, 'message');
				$app->enqueueMessage('Please enable: "Searching small/common words":
					<a class="btn" href="index.php?option=com_config&view=component&component=com_flexicontent&path=&"><span class="icon-options"></span>Configuration</a>
					and then click to re-INDEX both search indexes', 'notice');
			}
		}



		$this->count_filters = $count_filters;

		$this->lists       = $lists;
		$this->rows        = $rows;
		$this->pagination  = $pagination;
		$this->f_active    = $f_active;

		$this->perms  = FlexicontentHelperPerm::getPerm();
		$this->option = $option;
		$this->view   = $view;
		$this->state  = $this->get('State');
		$this->isADV = $isADV;

		if (!$jinput->getCmd('nosidebar'))
		{
			$this->sidebar = null;

			if(FLEXI_J30GE && !FLEXI_J40GE) $this->sidebar = JHtmlSidebar::render();
			if(FLEXI_J40GE) $this->sidebar = \Joomla\CMS\HTML\Helpers\Sidebar::render();
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
		$user     = \Joomla\CMS\Factory::getApplication()->getIdentity();
		$document = \Joomla\CMS\Factory::getApplication()->getDocument();
		$toolbar  = \Joomla\CMS\Factory::getApplication()->getDocument()->getToolbar('toolbar');
		$perms    = FlexicontentHelperPerm::getPerm();
		$session  = \Joomla\CMS\Factory::getApplication()->getSession();

		$js = '';

		$contrl = "search.";
		$contrl_s = null;

		$loading_msg = flexicontent_html::encodeHTML(\Joomla\CMS\Language\Text::_('FLEXI_LOADING') .' ... '. \Joomla\CMS\Language\Text::_('FLEXI_PLEASE_WAIT'), 2);

		$btn_arr = array();

		if ($perms->CanIndex)
		{
			$popup_load_url = \Joomla\CMS\Uri\Uri::base(true) . '/index.php?option=com_flexicontent&amp;view=search&amp;layout=indexer&amp;tmpl=component&amp;indexer=basic';
			$btn_text = str_replace('<br/>', '', \Joomla\CMS\Language\Text::_('FLEXI_REINDEX_BASIC_CONTENT_LISTS'));
			$btn_name = 'search_adv_index_dirty_only';
			$full_js="if (!confirm('" . str_replace('<br>', '\n', flexicontent_html::encodeHTML(\Joomla\CMS\Language\Text::_('FLEXI_MAY_TAKE_TIME_IN_LARGE_WEBSITES'), 'd')) . "')) return false; var url = jQuery(this).data('taskurl'); fc_showDialog(url, 'fc_modal_popup_container', 0, 550, 420, function(){document.body.innerHTML='<span class=\"fc_loading_msg\">"
						.$loading_msg."<\/span>'; window.location.reload(false)}, {'title': '".flexicontent_html::encodeHTML(strip_tags(\Joomla\CMS\Language\Text::_('FLEXI_REINDEX_BASIC_CONTENT_LISTS')), 'd')."'}); return false;";
			$btn_arr[] = flexicontent_html::addToolBarButton(
				$btn_text, $btn_name, $full_js,
				$msg_alert = \Joomla\CMS\Language\Text::_('FLEXI_NO_ITEMS_SELECTED'), $msg_confirm = '',
				$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				'btn btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . $this->btn_iv_class : '') . ' ' . $this->tooltip_class, 'icon-loop',
				'data-placement="right" data-taskurl="' . $popup_load_url .'" title=""', $auto_add = 0, $tag_type='button')
				;

			$popup_load_url = \Joomla\CMS\Uri\Uri::base(true) . '/index.php?option=com_flexicontent&amp;view=search&amp;layout=indexer&amp;tmpl=component&amp;indexer=advanced';
			$btn_text = str_replace('<br/>', '', \Joomla\CMS\Language\Text::_('FLEXI_REINDEX_ADVANCED_SEARCH_VIEW'));
			$btn_name = 'search_adv_index_dirty_only';
			$full_js="if (!confirm('" . str_replace('<br>', '\n', flexicontent_html::encodeHTML(\Joomla\CMS\Language\Text::_('FLEXI_MAY_TAKE_TIME_IN_LARGE_WEBSITES'), 'd')) . "')) return false; var url = jQuery(this).data('taskurl'); fc_showDialog(url, 'fc_modal_popup_container', 0, 550, 420, function(){document.body.innerHTML='<span class=\"fc_loading_msg\">"
						.$loading_msg."<\/span>'; window.location.reload(false)}, {'title': '".flexicontent_html::encodeHTML(strip_tags(\Joomla\CMS\Language\Text::_('FLEXI_REINDEX_ADVANCED_SEARCH_VIEW')), 'd')."'}); return false;";
			$btn_arr[] = flexicontent_html::addToolBarButton(
				$btn_text, $btn_name, $full_js,
				$msg_alert = \Joomla\CMS\Language\Text::_('FLEXI_NO_ITEMS_SELECTED'), $msg_confirm = '',
				$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				'btn btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . $this->btn_iv_class : '') . ' ' . $this->tooltip_class, 'icon-loop',
				'data-placement="right" data-taskurl="' . $popup_load_url .'" title=""', $auto_add = 0, $tag_type='button')
				;

			$popup_load_url = \Joomla\CMS\Uri\Uri::base(true) . '/index.php?option=com_flexicontent&amp;view=search&amp;layout=indexer&amp;tmpl=component&amp;indexer=advanced&amp;rebuildmode=quick';
			$btn_text = str_replace('<br/>', '', \Joomla\CMS\Language\Text::_('FLEXI_REINDEX_ADVANCED_SEARCH_VIEW_DIRTY_ONLY'));
			$btn_name = 'search_adv_index_dirty_only';
			$full_js="if (!confirm('" . str_replace('<br>', '\n', flexicontent_html::encodeHTML(\Joomla\CMS\Language\Text::_('FLEXI_MAY_TAKE_TIME_IN_LARGE_WEBSITES'), 'd')) . "')) return false; var url = jQuery(this).data('taskurl'); fc_showDialog(url, 'fc_modal_popup_container', 0, 550, 420, function(){document.body.innerHTML='<span class=\"fc_loading_msg\">"
						.$loading_msg."<\/span>'; window.location.reload(false)}, {'title': '".flexicontent_html::encodeHTML(strip_tags(\Joomla\CMS\Language\Text::_('FLEXI_REINDEX_ADVANCED_SEARCH_VIEW_DIRTY_ONLY')), 'd')."'}); return false;";
			$btn_arr[] = flexicontent_html::addToolBarButton(
				$btn_text, $btn_name, $full_js,
				$msg_alert = \Joomla\CMS\Language\Text::_('FLEXI_NO_ITEMS_SELECTED'), $msg_confirm = '',
				$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				'btn btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . $this->btn_iv_class : '') . ' ' . $this->tooltip_class, 'icon-loop',
				'data-placement="right" data-taskurl="' . $popup_load_url .'" title=""', $auto_add = 0, $tag_type='button')
				;
		}

		if (count($btn_arr))
		{
			$drop_btn = '
				<button type="button" class="' . $this->btn_sm_class . ' btn-primary dropdown-toggle" data-toggle="dropdown" data-bs-toggle="dropdown">
					<span title="'.\Joomla\CMS\Language\Text::_('FLEXI_SEARCH_INDEXES').'" class="icon-menu"></span>
					'.\Joomla\CMS\Language\Text::_('FLEXI_SEARCH_INDEXES').'
					<span class="caret"></span>
				</button>';
			array_unshift($btn_arr, $drop_btn);
			flexicontent_html::addToolBarDropMenu($btn_arr, 'search-index-btns-group', ' ');
		}

		//$toolbar->appendButton('Confirm', 'FLEXI_DELETE_INDEX_CONFIRM', 'trash', 'FLEXI_INDEX_ADVANCED_PURGE', $contrl . 'purge', false);
		$btn_icon = 'icon-trash';
		$btn_name = 'purge';
		$btn_task = $contrl . 'purge';
		$extra_js = "";
		flexicontent_html::addToolBarButton(
			'FLEXI_INDEX_ADVANCED_PURGE',
			$btn_name, $full_js='', $msg_alert='', $msg_confirm=\Joomla\CMS\Language\Text::_('FLEXI_PURGE_INDEX_CONFIRM'),
			$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true, $btn_class="", $btn_icon);

		//$toolbar->appendButton('Confirm', 'Update ?', 'shuffle', 'FLEXI_UPDATE_CUSTOM_ORDER_INDEXES', $contrl . 'custom_order', false);
		$btn_icon = 'icon-shuffle';
		$btn_name = 'custom_order';
		$btn_task = $contrl . 'custom_order';
		$extra_js = "";
		flexicontent_html::addToolBarButton(
			'FLEXI_UPDATE_CUSTOM_ORDER_INDEXES',
			$btn_name, $full_js='', $msg_alert='', $msg_confirm=\Joomla\CMS\Language\Text::_('FLEXI_UPDATE_CUSTOM_ORDER_INDEXES'),
			$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true, $btn_class="", $btn_icon);

		// Configuration button
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

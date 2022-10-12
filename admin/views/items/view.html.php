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
 * HTML View class for the items backend manager
 */
class FlexicontentViewItems extends FlexicontentViewBaseRecords
{
	var $proxy_option   = 'com_content';
	var $title_propname = 'title';
	var $state_propname = 'state';
	var $db_tbl         = 'content';

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
		$perms    = FlexicontentHelperPerm::getPerm();

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

		// Performance statistics
		if ($print_logging_info = $cparams->get('print_logging_info'))
		{
			global $fc_run_times;
		}


		/**
		 * Batch task variable
		 */

		if ($task === 'batch' || $task === 'copy' || $task === 'translate' || $task === 'quicktranslate')
		{
			//$model_s = $this->getModel('item');
			//$this->model_s = $model_s;
			$this->task    = $task;

			$behaviour = $task === 'translate' || $task === 'quicktranslate'
				? 'translate'
				: ($task === 'copy' ? 'copyonly' : 'copymove');
			$this->setLayout('batch');
			$this->_displayCopyMove($tpl, $cid, $behaviour);

			return;
		}


		/**
		 * Get filters and ordering
		 */

		$count_filters = 0;

		// File id filtering
		$fileid_to_itemids = $session->get('fileid_to_itemids', array(),'flexicontent');
		$filter_fileid     = $model->getState('filter_fileid');

		// Category filtering
		$filter_cats        = $model->getState('filter_cats');
		$filter_subcats     = $model->getState('filter_subcats');
		$filter_catsinstate = $model->getState('filter_catsinstate');
		$filter_featured    = $model->getState('filter_featured');

		// Order and order direction
		$filter_order      = $model->getState('filter_order');
		$filter_order_Dir  = $model->getState('filter_order_Dir');

		// Order type
		$filter_order_type = $model->getState('filter_order_type');

		// Ordering filter
		$reOrderingActive = !$filter_order_type
			? $filter_order == 'a.ordering'
			: $filter_order == 'catsordering';

		if ($filter_fileid) $count_filters++;
		if ($filter_cats && !$reOrderingActive) $count_filters++;
		if ($filter_subcats != 1 && !$reOrderingActive) $count_filters++;
		if ($filter_catsinstate != 1) $count_filters++;
		if (strlen($filter_featured)) $count_filters++;


		// Various filters
		$filter_assockey  = $model->getState('filter_assockey');
		$filter_tag       = $model->getState('filter_tag');
		$filter_lang      = $model->getState('filter_lang');
		$filter_type      = $model->getState('filter_type');
		$filter_author    = $model->getState('filter_author');
		$filter_state     = $model->getState('filter_state');
		$filter_access    = $model->getState('filter_access');
		$filter_meta      = $model->getState('filter_meta');

		$csv_header       = $model->getState('csv_header');
		$csv_raw_export   = $model->getState('csv_raw_export');
		$csv_all_fields   = $model->getState('csv_all_fields');

		// Support for using 'ALL', 'ORPHAN' fake states, by clearing other values
		if (is_array($filter_state) && in_array('ALL', $filter_state))     $filter_state = array('ALL');
		if (is_array($filter_state) && in_array('ORPHAN', $filter_state))  $filter_state = array('ORPHAN');

		// Count active filters
		if ($filter_assockey) $count_filters++;
		if ($filter_tag) $count_filters++;
		if ($filter_lang) $count_filters++;
		if ($filter_type) $count_filters++;
		if ($filter_author) $count_filters++;
		if ($filter_state) $count_filters++;
		if ($filter_access) $count_filters++;
		if ($filter_meta) $count_filters++;

		// Date filters
		$date      = $model->getState('date');
		$startdate = $model->getState('startdate');
		$enddate   = $model->getState('enddate');

		$startdate = $db->escape( StringHelper::trim(StringHelper::strtolower( $startdate ) ) );
		$enddate   = $db->escape( StringHelper::trim(StringHelper::strtolower( $enddate ) ) );
		if ($startdate) $count_filters++;
		if ($enddate)   $count_filters++;

		// Record ID filter
		$filter_id = $model->getState('filter_id');
		if (strlen($filter_id)) $count_filters++;


		// Text search
		$scope  = $model->getState('scope');
		$search = $model->getState('search');
		$search = StringHelper::trim(StringHelper::strtolower($search));

		/**
		 * Get single type configuration
		 */
		$model_s = $this->getModel('item');

		if (count($filter_type) === 1)
		{
			$this->single_type = reset($filter_type);
			$this->tparams  = new JRegistry();
			$this->tparams->merge($cparams);

			$tmp_params = $model_s->getTypeparams($this->single_type);
			$tmp_params = new JRegistry($tmp_params);
			$this->tparams->merge($tmp_params);
		}
		else
		{
			$this->single_type = 0;
			$this->tparams = $cparams;
		}


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

		$js = '';

		if ($filter_state || strlen($filter_featured))
		{
			$js .= "jQuery('.col_status').addClass('filtered_column');";
		}
		if (strlen($search)) $js .= "jQuery('.col_title').addClass('filtered_column');";
		if ($filter_cats)    $js .= "jQuery('.col_cats').addClass('filtered_column');";
		if ($filter_type)    $js .= "jQuery('.col_type').addClass('filtered_column');";
		if ($filter_author)  $js .= "jQuery('.col_authors').addClass('filtered_column');";
		if ($filter_lang)    $js .= "jQuery('.col_lang').addClass('filtered_column');";
		if ($filter_access)  $js .= "jQuery('.col_access').addClass('filtered_column');";
		if ($filter_meta)    $js .= "jQuery('.col_meta').addClass('filtered_column');";
		if ($filter_tag)     $js .= "jQuery('.col_tag').addClass('filtered_column');";
		if ($filter_id)      $js .= "jQuery('.col_id').addClass('filtered_column');";
		if ($startdate || $enddate)
		{
			if ($date == 1) {
				$js .= "jQuery('.col_created').addClass('filtered_column');";
			} else if ($date == 2) {
				$js .= "jQuery('.col_revised').addClass('filtered_column');";
			}
		}

		if ($js)
		{
			$document->addScriptDeclaration('
				jQuery(document).ready(function(){
					' . $js . '
				});
			');
		}


		/**
		 * Create Submenu & Toolbar
		 */

		// Create Submenu (and also check access to current view)
		FLEXIUtilities::ManagerSideMenu(null);

		// Create document/toolbar titles
		$doc_title = JText::_('FLEXI_ITEMS');
		$site_title = $document->getTitle();
		JToolbarHelper::title($doc_title, 'items');
		$document->setTitle($doc_title .' - '. $site_title);

		// Create the toolbar
		$this->setToolbar();


		/**
		 * Get data from the model, note data retrieval must be before
		 * getTotal() and getPagination() because it also calculates total rows
		 */

		$badcatitems  = (int) $model->getUnboundedItems($limit=10000000, $count_only=true, $checkNoExtData=false, $checkInvalidCat=true);
		$unassociated = (int) $model->getUnboundedItems($limit=10000000, $count_only=true, $checkNoExtData=true, $checkInvalidCat=false);

		$bind_limit = $jinput->get('bind_limit', ($unassociated >= 1000 ? 1000 : 250), 'int');

		$rows        = $model->getItems();
		$pagination  = $model->getPagination();
		$types       = $model->getTypeslist();
		$authors     = $model->getAuthorslist();

		// These depend on data rows and must be called after getting data
		$extraCols   = $model->getExtraCols();
		$customFilts = $model->getCustomFilts();

		foreach($customFilts as $filter)
		{
			if (count($filter->value))
			{
				$count_filters++;
			}
		}

		$itemCats    = $model->getItemCats();
		$itemTags    = $model->getItemTags();

		// Get Field values to be used for rendering custom columns
		if ($extraCols)
		{
			FlexicontentFields::getFields($rows, 'category');
		}

		$lang_assocs = $useAssocs ? $model->getLangAssocs() : array();
		$langs       = FLEXIUtilities::getLanguages('code');
		$categories  = $globalcats ?: array();


		$drag_reorder_max = 200;
		if ($pagination->limit > $drag_reorder_max)
		{
			$cparams->set('draggable_reordering', 0);
		}


		/**
		 * Add usage information notices if these are enabled
		 */

		$conf_link = '<a href="index.php?option=com_config&amp;view=component&amp;component=com_flexicontent&amp;path=" class="' . $this->btn_sm_class . ' btn-info">'.JText::_("FLEXI_CONFIG").'</a>';

		if ($cparams->get('show_usability_messages', 1) && !$unassociated && !$badcatitems)
		{
			$notice_drag_reorder_disabled = $app->getUserStateFromRequest( $option.'.items.notice_drag_reorder_disabled',	'notice_drag_reorder_disabled',	0, 'int' );

			if (!$notice_drag_reorder_disabled && $pagination->limit > $drag_reorder_max)
			{
				$app->setUserState( $option.'.items.notice_drag_reorder_disabled', 1 );
				$app->enqueueMessage(JText::sprintf('FLEXI_DRAG_REORDER_DISABLED', $drag_reorder_max), 'notice');
				$show_turn_off_notice = 1;
			}

			if (!empty($show_turn_off_notice))
			{
				$disable_use_notices = '<span class="fc-nowrap-box fc-disable-notices-box">'. JText::_('FLEXI_USABILITY_MESSAGES_TURN_OFF_IN').' '.$conf_link.'</span><div class="fcclear"></div>';
				$app->enqueueMessage($disable_use_notices, 'notice');
			}
		}

		$this->minihelp = '
			<div id="fc-mini-help" class="fc-mssg fc-info" style="display:none; min-width: 600px;">
				'.JText::sprintf('FLEXI_ABOUT_CUSTOM_FIELD_COLUMNS_COMPONENT_AND_PER_TYPE', $conf_link).'<br/><br/>
				<sup>[1]</sup> ' . JText::_('FLEXI_TMPL_NOT_SET_USING_TYPE_DEFAULT') . '<br />
				'.( $useAssocs ? '
				<sup>[3]</sup> ' . JText::_('FLEXI_SORT_TO_GROUP_TRANSLATION') . '<br />
				' : '').'
				<sup>[4]</sup> ' . JText::_('FLEXI_MULTIPLE_ITEM_ORDERINGS') . '<br />
			</div>
		';


		/**
		 * Create List Filters
		 */

		$lists = array();


		// Build include sub-categories filter
		$subcats_na = $filter_order_type && $filter_cats && ($filter_order === 'a.ordering' || $filter_order === 'catsordering');

		$fieldname = 'filter_subcats';
		$elementid = 'filter_subcats';
		$value     = $filter_subcats;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_SUBCATEGORIES'),
			'label_extra_class' => ($reOrderingActive ? ' fc-lbl-short' : '') . (!$value ? ' fc-lbl-inverted' : ''),
			'html' =>
				($subcats_na ? '<div style="display:none">' : '') . '
					<div class="group-fcset" style="display: inline-block;">
						<input type="checkbox" id="'.$elementid.'" name="'.$fieldname.'" onchange="if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform()" value="1" '.($value ? ' checked="checked" ' : '').' />
						<label id="'.$elementid.'-lbl" for="'.$elementid.'" style="margin: 0 12px; vertical-align: middle; border: 0;"></label>
					</div>'
				. ($subcats_na ? '</div>' : '')
				. ($subcats_na ? '<span class="icon-question ' . $this->popover_class . '" style="margin: 0 8px; font-size: 12px;" data-content="'
					. flexicontent_html::getToolTip('FLEXI_SUBCATEGORIES_NOT_INCLUDED_DURING_CATORDER', 'FLEXI_SUBCATEGORIES_NOT_INCLUDED_DURING_CATORDER_DESC', 1 , 1)
					. '" ></span>' : ''),
		));


		// Build featured filter
		$options = array(
			JHtml::_('select.option', '', '-'),
			JHtml::_('select.option', '0', JText::_('FLEXI_NO')),
			JHtml::_('select.option', '1', JText::_('FLEXI_YES')),
		);

		$fieldname = 'filter_featured';
		$elementid = 'filter_featured';
		$value     = $filter_featured;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_FEATURED'),
			'label_extra_class' => (strlen($value) ? ' fc-lbl-inverted' : ''),
			'html' => JHtml::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'size' => '1',
					'class' => $this->select_class,
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'value',
				'text',
				$value,
				$elementid,
				$translate = true
			),
		));


		// Build category-state filter
		$options = array(
			JHtml::_('select.option', 1, JText::_('FLEXI_PUBLISHED')),
			JHtml::_('select.option', 0, JText::_('FLEXI_UNPUBLISHED')),
			JHtml::_('select.option', 99, JText::_('FLEXI_ANY')),
			JHtml::_('select.option', 2, JText::_('FLEXI_ARCHIVED')),
			JHtml::_('select.option', -2, JText::_('FLEXI_TRASHED')),
		);

		$fieldname = 'filter_catsinstate';
		$elementid = 'filter_catsinstate';
		$value     = $filter_catsinstate;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_IN_CAT_STATE'),
			'label_extra_class' => 'icon-info-2 ' . $this->tooltip_class . ($value != 1 ? ' fc-lbl-inverted' : ''),
			'label_extra_attrs' => array(
				'data-placement' => 'top',
				'title' => flexicontent_html::getToolTip(JText::_('FLEXI_LIST_ITEMS_IN_CATS', true), JText::_('FLEXI_LIST_ITEMS_IN_CATS_DESC', true), 0, 1),
			),
			'html' => JHtml::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'size' => '1',
					'class' => $this->select_class,
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'value',
				'text',
				$value,
				$elementid
			),
		));

		/*$lists['filter_catsinstate'] = JHtml::_('select.radiolist', $options, 'filter_catsinstate', 'size="1" class="inputbox" onchange="if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_catsinstate );
		$lists['filter_catsinstate']  = '';
		foreach ($catsinstate as $i => $v)
		{
			$checked = $filter_catsinstate == $i ? ' checked="checked" ' : '';
			$lists['filter_catsinstate'] .= '<input type="radio" onchange="if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform()" class="inputbox" '.$checked.' value="'.$i.'" id="filter_catsinstate'.$i.'" name="filter_catsinstate" />';
			$lists['filter_catsinstate'] .= '<label class="" id="filter_catsinstate'.$i.'-lbl" for="filter_catsinstate'.$i.'">'.$v.'</label>';
		}*/


		// Build id filter
		$fieldname = 'filter_id';
		$elementid = 'filter_id';
		$value     = $filter_id;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_ID'),
			'label_extra_class' =>  ($value ? ' fc-lbl-inverted' : ''),
			'html' => '<input type="text" name="'.$fieldname.'" id="'.$elementid.'" size="6" value="' . $value . '" class="fcfield_textval" style="width:auto;" />',
		));


		// Build order type selector
		$order_types = array(
			JHtml::_('select.option', '0', JText::_('FLEXI_ORDER_JOOMLA_GLOBAL') . ' (' . JText::_('FLEXI_ORDER_JOOMLA_GLOBAL_ABOUT') . ')'),
			JHtml::_('select.option', '1', JText::_('FLEXI_ORDER_FC_PER_CATEGORY') . ' (' . JText::_('FLEXI_ORDER_FC_PER_CATEGORY_ABOUT') . ')'),
		);

		if (!$filter_order_type)
		{
			$_img_title = JText::_('FLEXI_ORDER_JOOMLA_GLOBAL');
			$_img_title_desc = JText::sprintf('FLEXI_CURRENT_ORDER_IS',JText::_('FLEXI_ORDER_JOOMLA_GLOBAL')).' '.JText::_('FLEXI_ITEM_ORDER_EXPLANATION_TIP');
		}
		else
		{
			$_img_title = JText::_('FLEXI_ORDER_FC_PER_CATEGORY', true);
			$_img_title_desc = JText::sprintf('FLEXI_CURRENT_ORDER_IS',JText::_('FLEXI_ORDER_FC_PER_CATEGORY')).' '.JText::_('FLEXI_ITEM_ORDER_EXPLANATION_TIP');
		}

		$fieldname = 'filter_order_type';
		$elementid = 'filter_order_type';
		$value     = $filter_order_type;

		//$lists['filter_order_type'] = JHtml::_('select.radiolist', $order_types, $fieldname, 'size="1" class="inputbox" onchange="if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_order_type );
		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_ORDER_TYPE'),
			'label_extra_class' => 'fc-lbl-inverted fc-lbl-short icon-info-2 ' . $this->popover_class . ($value ? ' fc-lbl-inverted' : ''),
			'label_extra_attrs' => array(
				'data-placement' => 'bottom',
				'data-content' => flexicontent_html::getToolTip($_img_title, $_img_title_desc, 0, 1),
			),
			'html' => JHtml::_('select.genericlist',
				$order_types,
				$fieldname,
				array(
					'size' => '1',
					'class' => $this->select_class,
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'value',
				'text',
				$value,
				$elementid,
				$translate = true
			),
		));


		// Build category filter
		$fieldname = 'filter_cats';
		$elementid = 'filter_cats';
		$value     = $filter_cats;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_CATEGORY'),
			'label_extra_class' => ($reOrderingActive ? ' fc-lbl-short' : '') . ($value ? ' fc-lbl-inverted' : ''),
			'html' => flexicontent_cats::buildcatselect(
				$categories,
				$fieldname,
				$value,
				($filter_order !== 'a.ordering' && $filter_order !== 'catsordering' ? '-' : 0),
				array(
					'class' => $this->select_class,
					'size' => '1',
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				$check_published = false,
				$check_perms = false
			),
		));


		// Build item type filter
		$this->isMobile  = flexicontent_html::getMobileDetector()->isMobile();
		$this->isTablet  = flexicontent_html::getMobileDetector()->isTablet();

		if ($cparams->get('iman_quick_itype_links', 1))
		{
			$this->max_tab_types = !$this->isMobile ? 20 : ($this->isTablet ? 8 : 1);
		}
		else
		{
			$this->max_tab_types = 0;
		}

		$filt_label          =  JText::_('FLEXI_TYPE'); //(!$this->max_tab_types || count($types) < $this->max_tab_types) ? JText::_('FLEXI_TYPE') : '';
		$filt_placeholder    = ''; //$filt_label ? '' : JText::_('FLEXI_TYPE');
		$filt_label_css      = (!$this->max_tab_types || count($types) < $this->max_tab_types) ? '' : ' fc-lbl-short' /*. ' fc-lbl-inverted'*/;

		$fieldname = 'filter_type[]';
		$elementid = 'filter_type';
		$value     = $filter_type;

		if (1)
		{
			$lists[$elementid] = $this->getFilterDisplay(array(
				'label' => $filt_label,
				'label_extra_class' => $filt_label_css . ($value ? ' fc-lbl-inverted' : ''),
				'html' => flexicontent_html::buildtypesselect(
					$types,
					$fieldname,
					$value,
					$displaytype = 0,
					array(
						'class' => $this->select_class,
						'multiple' => 'multiple',
						'size' => '3',
						'onmouseenter' => 'if (typeof this.oVal == \'undefined\') this.oVal = jQuery(this).val(); this.valChanged = false;',
						'onchange' => 'this.valChanged = JSON.stringify(this.oVal) !== JSON.stringify(jQuery(this).val()); if (this.valChanged && this != document.activeElement) {if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();}',
						'onblur' => 'this.oVal = jQuery(this).val(); if (this.valChanged) {if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();}',
						'placeholder' => $filt_placeholder,
					),
					$elementid
				),
			));
		}


		// Build author filter
		$fieldname = 'filter_author[]';
		$elementid = 'filter_author';
		$value     = $filter_author;

		if (1)
		{
			$lists[$elementid] = $this->getFilterDisplay(array(
				'label' => JText::_('FLEXI_AUTHOR'),
				'label_extra_class' => ($value ? ' fc-lbl-inverted' : ''),
				'html' => flexicontent_html::buildauthorsselect(
					$authors,
					$fieldname,
					$value,
					$displaytype = 0,
					array(
						'class' => $this->select_class,
						'multiple' => 'multiple',
						'size' => '3',
						'onmouseenter' => 'if (typeof this.oVal == \'undefined\') this.oVal = jQuery(this).val(); this.valChanged = false;',
						'onchange' => 'this.valChanged = JSON.stringify(this.oVal) !== JSON.stringify(jQuery(this).val()); if (this.valChanged && this != document.activeElement) {if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();}',
						'onblur' => 'this.oVal = jQuery(this).val(); if (this.valChanged) {if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();}',
					)
				),
			));
		}

		if ($badcatitems)
		{
			$lists['default_cat'] = flexicontent_cats::buildcatselect($categories, 'default_cat', '', 2, 'class="use_select2_lib"', false, false);
		}


		/**
		 * Create note about dates displayed using current user's timezone
		 */

		$site_zone = $app->getCfg('offset');
		$user_zone = JFactory::getUser()->getParam('timezone', $site_zone);

		$tz = new DateTimeZone( $user_zone );
		$tz_offset = $tz->getOffset(new JDate()) / 3600;
		$tz_info = $tz_offset
			? ' UTC +' . $tz_offset . ' (' . $user_zone . ')'
			: ' UTC ' . $tz_offset . ' (' . $user_zone . ')';

		$date_note_msg = JText::sprintf(FLEXI_J16GE ? 'FLEXI_DATES_IN_USER_TIMEZONE_NOTE' : 'FLEXI_DATES_IN_SITE_TIMEZONE_NOTE', ' ', $tz_info);


		// Build date filter scope
		$fieldname = 'date';
		$elementid = 'date';
		$value     = $date;

		$options = array(
			JHtml::_('select.option', 1, 'FLEXI_CREATED'),
			JHtml::_('select.option', 2, 'FLEXI_REVISED'),
			JHtml::_('select.option', 3, 'FLEXI_PUBLISH_UP'),
			JHtml::_('select.option', 4, 'FLEXI_PUBLISH_DOWN'),
		);

		$lists[$_filter_name = 'filter_' . $elementid] = $this->getFilterDisplay(array(
			'label' => null, // JText::_('FLEXI_DATE'),
			'label_extra_class' => ($startdate || $enddate ? ' fc-lbl-inverted' : ''),
			'html' => trim(JHtml::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'size' => '1',
					'style' => 'margin: 0 ;',
					'class' => $this->select_class . ' ' . $this->tooltip_class,
					'data-placement' => 'bottom',
					'title' => flexicontent_html::getToolTip(null, $date_note_msg, 0, 1),
				),
				'value',
				'text',
				$value,
				$elementid,
				$translate = true
			))
			. trim(JHtml::_('calendar', $startdate, 'startdate', 'startdate', '%Y-%m-%d', array('class'=>($startdate ? 'has-inverted-date-lbl' : ''), 'size'=>'8',  'maxlength'=>'19', 'style'=>'width:auto', 'placeholder'=>JText::_('FLEXI_FROM'))))
			. trim(JHtml::_('calendar', $enddate, 'enddate', 'enddate', '%Y-%m-%d', array('class'=>($enddate ? 'has-inverted-date-lbl' : ''), 'size'=>'8',  'maxlength'=>'19', 'style'=>'width:auto', 'placeholder'=>JText::_('FLEXI_TO'))))
		));


		// Build language filter
		$fieldname = 'filter_lang[]';
		$elementid = 'filter_lang';
		$value     = $filter_lang;

		if (1)
		{
			$lists[$elementid] = $this->getFilterDisplay(array(
				'label' => JText::_('FLEXI_LANGUAGE'),
				'label_extra_class' => ($value ? ' fc-lbl-inverted' : ''),
				'html' => flexicontent_html::buildlanguageslist(
					$fieldname,
					array(
						'class' => $this->select_class,
						'multiple' => 'multiple',
						'size' => '3',
						'onmouseenter' => 'if (typeof this.oVal == \'undefined\') this.oVal = jQuery(this).val(); this.valChanged = false;',
						'onchange' => 'this.valChanged = JSON.stringify(this.oVal) !== JSON.stringify(jQuery(this).val()); if (this.valChanged && this != document.activeElement) {if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();}',
						'onblur' => 'this.oVal = jQuery(this).val(); if (this.valChanged) {if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();}',
					),
					$value,
					$displaytype = 1
				),
			));
		}


		// Build bind-to-type limit
		$bind_limits = array(
			JHtml::_('select.option', 250, '250 ' . JText::_('FLEXI_ITEMS')),
			JHtml::_('select.option', 500, '500 ' . JText::_('FLEXI_ITEMS')),
			JHtml::_('select.option', 750, '750 ' . JText::_('FLEXI_ITEMS')),
			JHtml::_('select.option', 1000,'1000 ' . JText::_('FLEXI_ITEMS')),
			JHtml::_('select.option', 1500,'1500 ' . JText::_('FLEXI_ITEMS')),
			JHtml::_('select.option', 2000,'2000 ' . JText::_('FLEXI_ITEMS')),
		);
		$lists['bind_limits'] = JHtml::_('select.genericlist',
			$bind_limits,
			'bind_limit',
			array(
				'class' => $this->select_class . ' fc_add_highlight',
			),
			'value',
			'text',
			$bind_limit,
			'bind_limit'
		);


		// Build text search scope
		$scopes = array(
			'a.title'         => JText::_('FLEXI_TITLE'),
			'_desc_'          => JText::_('FLEXI_DESCRIPTION'),
			'ie.search_index' => JText::_('FLEXI_FIELDS_IN_BASIC_SEARCH_INDEX'),
			'a.metadesc'      => 'Meta (' . JText::_('FLEXI_DESCRIPTION') . ')',
			'a.metakey'       => 'Meta (' . JText::_('FLEXI_KEYWORDS') . ')',
			'_meta_'          => 'Meta (' . JText::_('FLEXI_DESCRIPTION') . ' + ' . JText::_('FLEXI_KEYWORDS') . ')',
		);

		$lists['scope_tip'] = ''; //'<span class="hidden-phone ' . $this->tooltip_class . '" title="'.JText::_('FLEXI_SEARCH_TEXT_INSIDE').'" style="display: inline-block;"><i class="icon-info-2"></i></span>';
		$lists['scope'] = $this->getScopeSelectorDisplay($scopes, $scope);
		$this->scope_title = isset($scopes[$scope]) ? $scopes[$scope] : reset($scopes);


		// Text search filter value
		$lists['search'] = $search;


		// Table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order']     = $filter_order;


		// Build tags filter
		$fieldname = 'filter_tag[]';
		$elementid = 'filter_tag';
		$value     = $filter_tag;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_TAG'),
			'label_extra_class' => ($value ? ' fc-lbl-inverted' : ''),
			'html' => flexicontent_html::buildtagsselect(
				$fieldname,
				array(
					'class' => $this->select_class,
					'multiple' => 'multiple',
					'size' => '3',
					'onmouseenter' => 'if (typeof this.oVal == \'undefined\') this.oVal = jQuery(this).val(); this.valChanged = false;',
					'onchange' => 'this.valChanged = JSON.stringify(this.oVal) !== JSON.stringify(jQuery(this).val()); if (this.valChanged && this != document.activeElement) {if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();}',
					'onblur' => 'this.oVal = jQuery(this).val(); if (this.valChanged) {if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();}',
				),
				$value,
				$displaytype = 0
			),
		));


		// Build publication state filter
		$options = array();
		//$options[]['items'][] = array('value' => '', 'text' => '-' /*JText::_('FLEXI_SELECT_STATE')*/);

		$grp = 'single_status_states';
		$options[$grp] = array();
		$options[$grp]['id'] = 'single_status_states';
		$options[$grp]['text'] = JText::_('FLEXI_SINGLE_STATUS');
		$options[$grp]['items'] = array(
			array('value' => 'P', 'text' => JText::_('FLEXI_PUBLISHED')),
			array('value' => 'U', 'text' => JText::_('FLEXI_UNPUBLISHED')),
			array('value' => 'PE', 'text' => JText::_('FLEXI_PENDING')),
			array('value' => 'OQ', 'text' => JText::_('FLEXI_TO_WRITE')),
			array('value' => 'IP', 'text' => JText::_('FLEXI_IN_PROGRESS')),
			array('value' => 'A', 'text' => JText::_('FLEXI_ARCHIVED')),
			array('value' => 'T', 'text' => JText::_('FLEXI_TRASHED'))
		);

		$grp = 'status_groups_states';
		$options[$grp] = array();
		$options[$grp]['id'] = 'status_groups_states';
		$options[$grp]['text'] = JText::_('FLEXI_STATUS_GROUPS');
		$options[$grp]['items'] = array(
			array('value' => 'RV', 'text' => JText::_('FLEXI_REVISED_VER')),
			array('value' => 'ALL', 'text' => JText::_('FLEXI_GRP_ALL') . ' ' . JText::_('FLEXI_STATE_S')),
			array('value' => 'ALL_P', 'text' => JText::_('FLEXI_GRP_PUBLISHED') . ' ' . JText::_('FLEXI_STATE_S')),
			array('value' => 'ALL_U', 'text' => JText::_('FLEXI_GRP_UNPUBLISHED') . ' ' . JText::_('FLEXI_STATE_S')),
			array('value' => 'ORPHAN', 'text' => JText::_('FLEXI_GRP_ORPHAN'))
		);

		$fieldname = 'filter_state[]';  // make multivalue
		$elementid = 'filter_state';
		$value     = $filter_state;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_STATE'),
			'label_extra_class' => ($value ? ' fc-lbl-inverted' : ''),
			'html' => JHtml::_('select.groupedlist',
				$options,
				$fieldname,
				array(
					'id' => $elementid,
					'group.id' => 'id',
					'list.attr' => array(
						'class' => $this->select_class,
						'multiple' => 'multiple',
						'size' => '3',
						'onmouseenter' => 'if (typeof this.oVal == \'undefined\') this.oVal = jQuery(this).val(); this.valChanged = false;',
						'onchange' => 'this.valChanged = JSON.stringify(this.oVal) !== JSON.stringify(jQuery(this).val()); if (this.valChanged && this != document.activeElement) {if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();}',
						'onblur' => 'this.oVal = jQuery(this).val(); if (this.valChanged) {if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();}',
					),
					'list.select' => $value,
				)
			),
		));
		//JHtml::_('grid.state', $filter_state),


		// Build access level filter
		$access_levels = JHtml::_('access.assetgroups');

		// Note 'all items' is already granted to super admins, so no need to check the is-super-admin ('core.admin') separately
		$allitems       = $perms->DisplayAllItems;
		$viewable_items = $cparams->get('iman_viewable_items', 1);
		$editable_items = $cparams->get('iman_editable_items', 0);

		// If can list only viewable items, then skip the non available levels to avoid user confusion
		if (!$allitems && $viewable_items)
		{
			$_aid_arr = array_flip(JAccess::getAuthorisedViewLevels($user->id));
			$_levels = array();

			foreach($access_levels as $i => $level)
			{
				if (isset($_aid_arr[$level->value]))
				{
					$_levels[] = $level;
				}
				else
				{
					// The alternative is to render the selector having some access levels disabled ...
					$access_levels[$i]->disable = 1;
				}
			}

			$options = $_levels;
		}
		else
		{
			$options = $access_levels;
		}

		$fieldname = 'filter_access[]';  // make multivalue
		$elementid = 'filter_access';
		$value     = $filter_access;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_ACCESS'),
			'label_extra_class' => ($value ? ' fc-lbl-inverted' : ''),
			'html' => JHtml::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'class' => $this->select_class,
					'multiple' => 'multiple',
					'size' => '3',
					'onmouseenter' => 'if (typeof this.oVal == \'undefined\') this.oVal = jQuery(this).val(); this.valChanged = false;',
					'onchange' => 'this.valChanged = JSON.stringify(this.oVal) !== JSON.stringify(jQuery(this).val()); if (this.valChanged && this != document.activeElement) {if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();}',
					'onblur' => 'this.oVal = jQuery(this).val(); if (this.valChanged) {if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();}',
				),
				'value',
				'text',
				$value,
				$elementid,
				$translate = true
			),
		));


		// Build meta status filter
		$fieldname = 'filter_meta';
		$elementid = 'filter_meta';
		$value     = $filter_meta;

		$options = array(
			JHtml::_('select.option', '', '-'),
			JHtml::_('select.option', 1, JText::_('FLEXI_EMPTY') . ' (' . JText::_('FLEXI_KEYWORDS') . ')'),
			JHtml::_('select.option', 2, JText::_('FLEXI_EMPTY') . ' (' . JText::_('FLEXI_DESCRIPTION') . ')'),
			JHtml::_('select.option', 3, JText::_('FLEXI_EMPTY') . ' (' . JText::_('FLEXI_KEYWORDS') . ' ' . JText::_('FLEXI_OR') . ' ' . JText::_('FLEXI_DESCRIPTION') . ')'),
		);

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_META'),
			'label_extra_class' => ($value ? ' fc-lbl-inverted' : ''),
			'html' => JHtml::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'size' => '1',
					'class' => $this->select_class . ' ' . $this->tooltip_class,
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
					'data-placement' => 'bottom',
					'title' => flexicontent_html::getToolTip(JText::_('FLEXI_META', true), JText::_('FLEXI_EMPTY', true), 0, 1),
				),
				'value',
				'text',
				$value,
				$elementid,
				$translate = true
			)
		));


		/**
		 * Filter by item usage a specific file
		 */
		$fieldname = 'filter_fileid';
		$elementid = 'filter_fileid';
		$value     = $filter_fileid;

		if ($fileid_to_itemids && count($fileid_to_itemids))
		{
			$files_data = $model->getFileData(array_keys($fileid_to_itemids));
			$file_options = array();
			$file_options[] = JHtml::_('select.option',  '', '-'/*.JText::_( 'FLEXI_SELECT' ).' '.JText::_( 'FLEXI_FILE' )*/ );

			foreach($files_data as $_file)
			{
				$file_options[] = JHtml::_('select.option', $_file->id, $_file->altname );
			}

			$lists[$elementid] = $this->getFilterDisplay(array(
				'label' => JText::_('FLEXI_ITEMS_USING') . ' ' . JText::_('FLEXI_FILE'),
				'label_extra_class' => ($value ? ' fc-lbl-inverted' : ''),
				'html' => JHtml::_('select.genericlist',
					$file_options,
					$fieldname,
					array(
						'size' => '1',
						'class' => $this->select_class,
						'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
					),
					'value',
					'text',
					$value
				),
			));
		}
		else
		{
			$lists[$elementid] = '';
		}


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


		// Build CSV header selection
		$csv_header_ops = array(
			JHtml::_('select.option', '', JText::_('-')),
			JHtml::_('select.option', '-1', JText::_('FLEXI_DEFAULT') . ' (' . JText::_('Component') . ' ' . JText::_('FLEXI_CONFIG'). ')'),
			JHtml::_('select.option', '1', JText::_('FLEXI_FIELD') . ' ' . JText::_('FLEXI_LABEL')),
			JHtml::_('select.option', '2', JText::_('FLEXI_FIELD') . ' ' . JText::_('FLEXI_NAME')),
		);

		$fieldname = 'csv_header';
		$elementid = 'csv_header';
		$value     = $csv_header;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('Header Row'),
			'label_extra_class' => 'fc-lbl-short ' . $this->popover_class . ($value ? ' fc-lbl-inverted' : ''),
			'label_extra_attrs' => array(
				'data-placement' => 'bottom',
				'data-content' => flexicontent_html::getToolTip('', 'Select if header row will contain field names or labels. <br>Use field names if you plan to reimport the file', 0, 1),
			),
			'html' => JHtml::_('select.genericlist',
				$csv_header_ops,
				$fieldname,
				array(
					'size' => '1',
					'class' => $this->select_class,
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'value',
				'text',
				$value,
				$elementid,
				$translate = true
			),
		));



		// Build CSV raw value (Default or Raw) selection
		$csv_raw_export_ops = array(
			JHtml::_('select.option', '', JText::_('-')),
			JHtml::_('select.option', '1', JText::_('FLEXI_DEFAULT') . ' (' . JText::_('FLEXI_FIELD') . ' ' . JText::_('FLEXI_CONFIG'). ')'),
			JHtml::_('select.option', '2', JText::_('FLEXI_FIELD_RAW_VALUES')),
		);

		$fieldname = 'csv_raw_export';
		$elementid = 'csv_raw_export';
		$value     = $csv_raw_export;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('Field values'),
			'label_extra_class' => 'fc-lbl-short ' . $this->popover_class . ($value ? ' fc-lbl-inverted' : ''),
			'label_extra_attrs' => array(
				'data-placement' => 'bottom',
				'data-content' => flexicontent_html::getToolTip('', 'Select if field values will be raw or according to field configuration. <br>Use raw values if you plan to reimport the file', 0, 1),
			),
			'html' => JHtml::_('select.genericlist',
				$csv_raw_export_ops,
				$fieldname,
				array(
					'size' => '1',
					'class' => $this->select_class,
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'value',
				'text',
				$value,
				$elementid,
				$translate = true
			),
		));


		// Build CSV export all field (Default / All) selection
		$csv_all_fields_ops = array(
			JHtml::_('select.option', '1', JText::_('FLEXI_DEFAULT') . ' (' . JText::_('FLEXI_FIELD') . ' ' . JText::_('FLEXI_CONFIG'). ')'),
			JHtml::_('select.option', '2', JText::_('FLEXI_ALL') . ' &nbsp; ( BETA Feature !!! )'),
		);

		$fieldname = 'csv_all_fields';
		$elementid = 'csv_all_fields';
		$value     = $csv_all_fields;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('Include fields'),
			'label_extra_class' => 'fc-lbl-short ' . $this->popover_class . ($value ? ' fc-lbl-inverted' : ''),
			'label_extra_attrs' => array(
				'data-placement' => 'bottom',
				'data-content' => flexicontent_html::getToolTip('', 'If you plan to reimport the file, then before re-import it is best to remove field columns that you have not modified. Also some field types may not support importing !!', 0, 1),
			),
			'html' => JHtml::_('select.genericlist',
				$csv_all_fields_ops,
				$fieldname,
				array(
					'size' => '1',
					'class' => $this->select_class,
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'value',
				'text',
				$value,
				$elementid,
				$translate = true
			),
		));


		/**
		 * Assign data to template
		 */

		$this->count_filters = $count_filters;
		$this->filter_catsinstate = $filter_catsinstate;

		$this->lists       = $lists;
		$this->rows        = $rows;
		$this->itemCats    = $itemCats;
		$this->itemTags    = $itemTags;
		$this->itemTypes   = $types;
		$this->extra_fields= $extraCols;
		$this->custom_filts= $customFilts;
		$this->lang_assocs = $lang_assocs;
		$this->langs       = $langs;
		$this->cid         = $cid;
		$this->pagination  = $pagination;

		$this->reOrderingActive = $reOrderingActive;
		$this->unassociated     = $unassociated;
		$this->badcatitems      = $badcatitems;

		// Filters related to ordering
		$this->filter_order_type = $filter_order_type;
		$this->filter_order = $filter_order;

		$this->scope = $scope;
		$this->date = $date;
		$this->startdate = $startdate;
		$this->enddate = $enddate;

		$this->perms  = $perms;
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


	function _displayCopyMove($tpl = null, $cid = array(), $behaviour = 'copymove')
	{
		global $globalcats;

		// Initialise variables
		$app      = JFactory::getApplication();
		$jinput   = $app->input;
		$user 		= JFactory::getUser();
		$document	= JFactory::getDocument();
		$contrl   = $this->ctrl . '.';

		// Add css to document
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', array('version' => FLEXI_VHASH))
			: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', array('version' => FLEXI_VHASH));
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x.css' : 'j3x.css'), array('version' => FLEXI_VHASH))
			: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x_rtl.css' : 'j3x_rtl.css'), array('version' => FLEXI_VHASH));

		// Add js to document
		//JHtml::_('behavior.tooltip');
		flexicontent_html::loadFramework('select2');
		$document->addScript(JUri::base(true).'/components/com_flexicontent/assets/js/batchprocess.js', array('version' => FLEXI_VHASH));

		flexicontent_html::loadFramework('flexi-lib');
		flexicontent_html::loadFramework('flexi-lib-form');

		// Add js function to overload the joomla submitform validation
		JHtml::_('behavior.formvalidator');  // load default validation JS to make sure it is overriden
		$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', array('version' => FLEXI_VHASH));
		$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', array('version' => FLEXI_VHASH));

		// Create document/toolbar titles
		$doc_title = $behaviour === 'translate'
			? JText::_('FLEXI_TRANSLATE_ITEM')
			: JText::_('FLEXI_BATCH');
			//: JText::_('FLEXI_COPYMOVE_ITEM');

		$site_title = $document->getTitle();
		JToolbarHelper::title($doc_title, 'itemadd');
		$document->setTitle($doc_title . ' - '. $site_title);

		// Create the toolbar
		JToolbarHelper::save($contrl . 'batchprocess');
		JToolbarHelper::cancel($contrl . 'cancel');

		// Get data from the model
		$model    = $this->getModel();

		$cid = $jinput->get('cid', array(), 'array');
		$model->setIds(ArrayHelper::toInteger($cid));

		$rows     = $model->getItems();
		$itemCats = $model->getItemCats();
		$categories = $globalcats;

		// build the main category select list
		$lists['maincat'] = flexicontent_cats::buildcatselect($categories, 'maincat',
			($behaviour === 'translate' ? '-99' : ''), $behaviour === 'translate' ? 5 : 4,
			'class="use_select2_lib" size="10"', false, false);

		// build the secondary categories select list
		$lists['seccats'] = flexicontent_cats::buildcatselect($categories, 'seccats[]', '', 0, 'class="use_select2_lib" multiple="multiple" size="10"', false, false);

		// build language selection
		$lists['language'] = flexicontent_html::buildlanguageslist(
			'language',
			array(
				'class' => 'use_select2_lib' . ($behaviour !== 'translate' ? '' : ' required'),
				'onchange' => ($behaviour !== 'translate' ? '' : "document.adminForm.prefix.value = ''; document.adminForm.suffix.value = this.value.substring(0, 2); "),
				),
			$selected = '',
			$type = ($behaviour !== 'translate' ? JText::_('FLEXI_NOCHANGE_LANGUAGE') : 2),
			$allowed_langs = null,
			$published_only = true,
			$disable_langs = null,
			$add_all = ($behaviour !== 'translate'),
			$radio_conf = ($behaviour !== 'translate' ? array() : array('required' => 1))
		 );

		// build state selection
		$selected_state = 0; // use unpublished as default state of new items, (instead of '' which means do not change)
		$lists['state'] = flexicontent_html::buildstateslist('state', 'class="use_select2_lib"', $selected_state);

		// build types selection
		$types = flexicontent_html::getTypesList();
		$lists['type_id'] = flexicontent_html::buildtypesselect($types, 'type_id', '', JText::_('FLEXI_DO_NOT_CHANGE'), 'class="use_select2_lib" size="1" style="vertical-align:top;"', 'type_id');

		// build access level filter
		$levels = JHtml::_('access.assetgroups');
		array_unshift($levels, JHtml::_('select.option', '', 'FLEXI_DO_NOT_CHANGE') );
		$fieldname =  $elementid = 'access';
		$attribs = 'class="use_select2_lib"';
		$lists['access'] = JHtml::_('select.genericlist', $levels, $fieldname, $attribs, 'value', 'text', $value='', $elementid, $translate = true);


		//assign data to template
		$this->lists = $lists;
		$this->rows = $rows;
		$this->itemCats = $itemCats;
		$this->cid = $cid;
		$this->user = $user;
		$this->behaviour = $behaviour;

		parent::display($tpl);
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
		$cparams  = JComponentHelper::getParams('com_flexicontent');

		$js = '';

		$contrl = $this->ctrl . '.';
		$contrl_s = 'item.';

		$loading_msg = flexicontent_html::encodeHTML(JText::_('FLEXI_LOADING') .' ... '. JText::_('FLEXI_PLEASE_WAIT'), 2);

		$hasEdit      = $perms->CanEdit    || $perms->CanEditOwn;
		$hasEditState = $perms->CanPublish || $perms->CanPublishOwn;
		$hasDelete    = $perms->CanDelete  || $perms->CanDeleteOwn;
		$hasArchive   = $perms->CanArchives;

		// Check if user can create in at least one published category
		require_once("components/com_flexicontent/models/item.php");
		$itemmodel = new FlexicontentModelItem();
		$CanAddAny = $itemmodel->getItemAccess()->get('access-create');

		// Get if state filter is active
		$model = $this->getModel();
		$filter_state = $model->getState('filter_state');
		$filter_type  = $model->getState('filter_type');
		$filter_cats  = $model->getState('filter_cats');

		// Implementation of multiple-item state selector

		if ($CanAddAny)
		{
			$btn_arr = array();
			$newBtn_in_dropdown = count($filter_type) === 1;

			// Add button of New Item of current (filtered) type
			if ($newBtn_in_dropdown)
			{
				$typeid = $filter_type[0];
				$types = flexicontent_html::getTypesList( $_type_ids=$filter_type, $_check_perms = false, $_published=true);
				$types = is_array($types) ? $types : array();

				$btn_title = JText::_('FLEXI_NEW', true) . ' ' . $types[$typeid]->name;
				$btn_info  = flexicontent_html::encodeHTML(JText::_('FLEXI_ADD_ITEM_OF_CURRENT_TYPE'), 2);
				$task_url  = JUri::base(true) . '/index.php?option=com_flexicontent&controller=items&task=items.add'
					. '&typeid=' . $filter_type[0] . '&catid=' . $filter_cats . '&' . JSession::getFormToken() . '=1';

				$full_js   = "window.location.replace('" . $task_url . "')";
				$btn_arr[] = flexicontent_html::addToolBarButton(
					$btn_title, 'csvexport', $full_js, $msg_alert='', $msg_confirm='',
					$btn_task='', $extra_js="", $btn_list=false, $btn_menu=true, $btn_confirm=false,
					$this->btn_sm_class . ' btn-success btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . $this->btn_iv_class : '') . ' ' . $this->tooltip_class, $btn_icon='icon-new',
					'data-placement="right" data-title="' . $btn_info . '"', $auto_add = 0, $tag_type='button'
				);
			}

			/**
			 * Add NEW ITEM button (with select type POPUP window)
			 */
			$popup_load_url = JUri::base(true) . '/index.php?option=com_flexicontent&view=types&tmpl=component&layout=typeslist&action=new' . '&catid=' . $filter_cats;
			$btn_name = 'add_item';
			$full_js = "var url = jQuery(this).data('taskurl'); fc_showDialog(url, 'fc_modal_popup_container', 0, 1200, 0, false, {'title': '".flexicontent_html::encodeHTML(JText::_('FLEXI_TYPE'), 2)."'}); return false;";
			$btn_arr[] = flexicontent_html::addToolBarButton(
				'FLEXI_NEW', $btn_name, $full_js,
				$msg_alert = '', $msg_confirm = '',
				$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				$this->btn_sm_class . ' btn-success btn-fcaction ' . ($newBtn_in_dropdown && FLEXI_J40GE ? '_DDI_class_ ' : '') .(FLEXI_J40GE ? $this->btn_iv_class : '') . ' ' . $this->tooltip_class, 'icon-new',
				'data-placement="right" data-taskurl="' . $popup_load_url .'" data-title="' . flexicontent_html::encodeHTML(JText::_('FLEXI_SELECT_TYPE'), 2) . '"', $auto_add = !$newBtn_in_dropdown, $tag_type='button'
			);

			/*
			// ALTERNATIVE WAYS to add the NEW ITEM button (with select type POPUP window)
			$btn_task = '';
			$popup_load_url = JUri::base(true) . '/index.php?option=com_flexicontent&view=types&tmpl=component&layout=typeslist&action=new';
			//$toolbar->appendButton('Popup', 'new',  JText::_('FLEXI_NEW'), str_replace('&', '&amp;', $popup_load_url), 780, 240);   //JToolbarHelper::addNew( $btn_task );
			$js .= "
				jQuery('#toolbar-new a.toolbar, #toolbar-new button')
					.attr('href', '".$popup_load_url."')
					.attr('onclick', 'var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 1200, 0, false, {\'title\': \'".flexicontent_html::encodeHTML(JText::_('FLEXI_TYPE'), 2)."\'}); return false;');
			";
			JToolbarHelper::custom( $btn_task, 'new.png', 'new_f2.png', 'FLEXI_NEW', false );
			*/

			if (!$newBtn_in_dropdown) $btn_arr = array();

			if (count($btn_arr))
			{
				$drop_btn = '
					<button id="toolbar-new" class="' . $this->btn_sm_class . ' dropdown-toggle btn-fcaction" data-toggle="dropdown" data-bs-toggle="dropdown">
						<span title="'.JText::_('FLEXI_NEW').'" class="icon-new"></span>
						'.JText::_('FLEXI_NEW').'
						<span class="caret"></span>
					</button>';
				array_unshift($btn_arr, $drop_btn);
				flexicontent_html::addToolBarDropMenu($btn_arr, 'add-btns-group', ' ');
			}
		}

		if ($hasDelete)
		{
			if ($filter_state && in_array('T', $filter_state))
			{
				$msg_alert   = JText::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_DELETE'));
				$msg_confirm = JText::_('FLEXI_ARE_YOU_SURE');
				$btn_task    = $contrl . 'remove';
				$extra_js    = "";
				flexicontent_html::addToolBarButton(
					'FLEXI_DELETE', 'delete', '', $msg_alert, $msg_confirm,
					$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true, $btn_class='btn-danger'
				);
			}
		}

		if (0 && (
			($hasArchive  && $filter_state && in_array('A', $filter_state)) ||
			($hasDelete   && $filter_state && in_array('T', $filter_state))
		)) {
			$msg_alert   = JText::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_RESTORE'));
			$msg_confirm = JText::_('FLEXI_RESTORE_CONFIRM');
			$btn_task    = $contrl . 'changestate';
			$extra_js    = "document.adminForm.newstate.value='P';";
			flexicontent_html::addToolBarButton(
				'FLEXI_RESTORE', 'restore', $full_js='', $msg_alert, $msg_confirm,
				$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true, $btn_class='btn-primary'
			);
		}


		// Button disabled to reduce buttons shown, instead we will use inline icon
		if (0 && $hasEdit)
		{
			$btn_task = $contrl . 'edit';
			JToolbarHelper::editList($btn_task);
		}


		if ($hasEditState || $hasDelete)
		{
			$states_applicable = array('P' => 0, 'U' => 0, 'A' => 0);

			// Trashed
			if (!$filter_state || !in_array('T', $filter_state) || count($filter_state) > 1)
			{
				$states_applicable['T'] = 0;
			}

			// In-Progress (Published)
			$states_applicable['IP'] = 0;

			// Automatic workflow states: Pending-Approval, Draft
			if ($perms->SuperAdmin)
			{
				$states_applicable['PE'] = 0;
				$states_applicable['OQ'] = 0;
			}

			$btn_arr = $this->getStateButtons($states_applicable);
			$this->addStateButtons($btn_arr);
		}

		$btn_arr = array();

		if ($CanAddAny && $perms->CanCopy)
		{
			$btn_task = $contrl . 'batch';
			//JToolbarHelper::custom($btn_task, 'copy.png', 'copy_f2.png', 'FLEXI_BATCH');
			$btn_arr[] = flexicontent_html::addToolBarButton(
				'FLEXI_UPDATE_COPY_MOVE', $btn_name = 'batch', $full_js = '',
				$msg_alert = '', $msg_confirm = '',
				$btn_task, $extra_js = '', $btn_list=true, $btn_menu=true, $btn_confirm=false,
				$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . $this->btn_iv_class : '') . ' ' . $this->popover_class, $btn_icon='icon-checkbox-partial',
				'data-placement="right" data-content="' . flexicontent_html::encodeHTML(JText::_(''), 2) . '"', $auto_add = 0, $tag_type='button'
			);

			if ($useAssocs)
			{
				/**
				 * Add multiple translations
				 */
				$popup_load_url = JUri::base(true) . '/index.php?option=com_flexicontent&view=items&tmpl=component&task=quicktranslate';
				$btn_name = 'quicktranslate';
				$full_js = "" .
					"var url = jQuery(this).data('taskurl'); " .
					"var cid = []; jQuery.each(jQuery(\"input[name='cid[]']:checked\"), function(){ cid.push(jQuery(this).val()); }); " .
					"url += '&' + cid.map(function(el, idx) { return 'cid[' + ']=' + el; }).join('&'); " .
					"fc_showDialog(url, 'fc_modal_popup_container', 0, 1200, 0, fc_edit_batch_modal_close, {'title': '".flexicontent_html::encodeHTML(JText::_('Add translations'), 2)."'}); return false;";
				$btn_arr[] = flexicontent_html::addToolBarButton(
					'FLEXI_VERIFY_N_TRANSLATE', $btn_name, $full_js,
					$msg_alert = JText::_("FLEXI_NO_ITEMS_SELECTED", true), $msg_confirm = '',
					$btn_task='quicktranslate', $extra_js='', $btn_list=true, $btn_menu=true, $btn_confirm=false,
					$this->btn_sm_class . ' btn-fcaction ' . ($newBtn_in_dropdown && FLEXI_J40GE ? '_DDI_class_ ' : '') .(FLEXI_J40GE ? $this->btn_iv_class : '') . ' ' . $this->tooltip_class, $btn_icon='icon-flag',
					'data-placement="right" data-taskurl="' . $popup_load_url .'" data-title="' . flexicontent_html::encodeHTML(JText::_('FLEXI_ADD_TRANSLATIONS'), 2) . '"', $auto_add = 0, $tag_type='button'
				);

				/**
				 * Add single translation
				 */
				$btn_task = $contrl . 'translate';
				//JToolbarHelper::custom($btn_task, 'flag', 'translate', 'FLEXI_TRANSLATE');
				$btn_arr[] = flexicontent_html::addToolBarButton(
					'FLEXI_TRANSLATE_MULTIPLE_ITEMS', $btn_name = 'translate', $full_js = '',
					$msg_alert = '', $msg_confirm = '',
					$btn_task, $extra_js = '', $btn_list=true, $btn_menu=true, $btn_confirm=false,
					$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? $this->btn_iv_class : '') . ' ' . $this->tooltip_class, $btn_icon='icon-flag',
					'data-placement="right" data-title="' . flexicontent_html::encodeHTML(JText::_('FLEXI_ADD_TRANSLATIONS'), 2) . '"', $auto_add = 0, $tag_type='button'
				);

				/**
				 * Mark translations as up to date
				 */
				$btn_task = $contrl . 'set_uptodate';
				//JToolbarHelper::custom($btn_task, 'flag', 'translate', 'Translations are up-to-date');
				$btn_arr[] = flexicontent_html::addToolBarButton(
					'FLEXI_TRANSLATIONS_SET_AS_UPTODATE', $btn_name = 'set_uptodate', $full_js = '',
					$msg_alert = '', $msg_confirm = '',
					$btn_task, $extra_js = '', $btn_list=true, $btn_menu=true, $btn_confirm=false,
					$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? $this->btn_iv_class : '') . ' ' . $this->tooltip_class, $btn_icon='icon-flag',
					'data-placement="right" data-title="' . flexicontent_html::encodeHTML(JText::_('FLEXI_TRANSLATIONS_SET_AS_UPTODATE_DESC'), 2) . '"', $auto_add = 0, $tag_type='button'
				);
			}
		}

		if (count($btn_arr))
		{
			$drop_btn = '
				<button id="toolbar-advanced" class="' . $this->btn_sm_class . ' dropdown-toggle" data-toggle="dropdown" data-bs-toggle="dropdown">
					<span title="'.JText::_('FLEXI_ADVANCED').'" class="icon-menu"></span>
					'.JText::_('FLEXI_BATCH').'
					<span class="caret"></span>
				</button>';
			array_unshift($btn_arr, $drop_btn);
			flexicontent_html::addToolBarDropMenu($btn_arr, 'advanced-btns-group', ' ');
		}


		/**
		 * Maintenance button (Check-in, CSV export, Verify Tag mappings, Assignments + Record)
		 */

		$btn_arr = array();

		//$btn_task = $contrl . 'checkin';
		//JToolbarHelper::checkin($btn_task);
		$btn_task  = $contrl . 'checkin';
		$btn_arr[] = flexicontent_html::addToolBarButton(
			'JTOOLBAR_CHECKIN', $btn_name = 'checkin', $full_js = '',
			$msg_alert = '', $msg_confirm = '',
			$btn_task, $extra_js = '', $btn_list=true, $btn_menu=true, $btn_confirm=false,
			$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . $this->btn_iv_class : '') . ' ' . $this->tooltip_class, $btn_icon='icon-checkin',
			'data-placement="right" data-title="' . flexicontent_html::encodeHTML(JText::_('FLEXI_MAINTENANCE_CHECKIN_DESC'), 2) . '"', $auto_add = 0, $tag_type='button'
		);

		if (JComponentHelper::getParams('com_flexicontent')->get('show_csvbutton_be', 0))
		{
			$btn_title = JText::_('FLEXI_CSV_EXPORT_CURRENT_PAGE', true);
			$btn_info  = flexicontent_html::encodeHTML(JText::_('FLEXI_CSV_EXPORT_CURRENT_PAGE_INFO'), 2);
			$task_url  = JUri::base(true) . '/index.php?option=com_flexicontent&view=items&format=csv';

			$full_js   = "window.location.replace('" . $task_url . "')";
			$btn_arr[] = flexicontent_html::addToolBarButton(
				$btn_title, 'csvexport', $full_js, $msg_alert='', $msg_confirm='',
				$btn_task='', $extra_js="", $btn_list=false, $btn_menu=true, $btn_confirm=false,
				$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . $this->btn_iv_class : '') . ' ' . $this->tooltip_class, $btn_icon='icon-download',
				'data-placement="right" data-title="' . $btn_info . '"', $auto_add = 0, $tag_type='button'
			);


			/**
			 * Add all-items CSV Export button
			 */

			$has_pro = JPluginHelper::isEnabled($extfolder = 'system', $extname = 'flexisyspro');

			if (1)
			{
				$btn_title = JText::_('FLEXI_CSV_EXPORT_ALL_ITEMS', true);
				$btn_info  = flexicontent_html::encodeHTML(JText::_('FLEXI_CSV_EXPORT_ALL_ITEMS_INFO'), 2);
				$task_url  = JUri::base(true) . '/index.php?option=com_flexicontent&view=items&format=csv&items_set=all';

				$full_js = $has_pro
					? "window.location.replace('" . $task_url . "')"
					: "var box = jQuery('#fc_available_in_pro'); fc_file_props_handle = fc_showAsDialog(box, 480, 320, null, {title:'" . JText::_($btn_title) . "'}); return false;";

				$btn_name='collaborate';
				$btn_arr[$btn_name] = '<div id="fc_available_in_pro" style="display: none;">' . JText::_('FLEXI_AVAILABLE_IN_PRO_VERSION') . '</div>' . flexicontent_html::addToolBarButton(
						$btn_title, $btn_name, $full_js ,
						$msg_alert='', $msg_confirm='',
						$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
						$btn_class='btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . $this->btn_iv_class : '') . ' ' . $this->tooltip_class, $btn_icon="icon-download",
						'data-placement="right" data-href="' . $task_url . '" data-title="' . $btn_info . '"', $auto_add = 0
					);
			}
		}

		if ($perms->CanCreateTags)
		{
			$popup_load_url = JUri::base(true) . '/index.php?option=com_flexicontent&amp;view=items&amp;layout=indexer&amp;tmpl=component&amp;indexer=tag_assignments';
			$btn_name = 'sync_tags';
			$full_js="if (!confirm('" . str_replace('<br>', '\n', flexicontent_html::encodeHTML(JText::_('FLEXI_2WAY_TAG_ASSIGNMENTS_SYNC_DESC'), 2)) . "')) return false; var url = jQuery(this).data('taskurl'); fc_showDialog(url, 'fc_modal_popup_container', 0, 550, 350, function(){document.body.innerHTML='<span class=\"fc_loading_msg\">"
						.$loading_msg."<\/span>'; window.location.reload(false)}, {'title': '".flexicontent_html::encodeHTML(JText::_('FLEXI_2WAY_TAG_ASSIGNMENTS_SYNC'), 2)."'}); return false;";
			$btn_arr[] = flexicontent_html::addToolBarButton(
				'FLEXI_2WAY_TAG_ASSIGNMENTS_SYNC', $btn_name, $full_js,
				$msg_alert = JText::_('FLEXI_NO_ITEMS_SELECTED'), $msg_confirm = '',
				$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . $this->btn_iv_class : '') . ' ' . $this->tooltip_class, 'icon-loop',
				'data-placement="right" data-taskurl="' . $popup_load_url .'" data-title="' . flexicontent_html::encodeHTML(JText::_('FLEXI_2WAY_TAG_ASSIGNMENTS_SYNC_DESC'), 2) . '"', $auto_add = 0, $tag_type='button'
			);
		}

		if ($perms->CanConfig)
		{
			$popup_load_url = JUri::base(true) . '/index.php?option=com_flexicontent&amp;view=items&amp;layout=indexer&amp;tmpl=component&amp;indexer=resave';
			$btn_name = 'recalculate_alias';
			$full_js="if (!confirm('" . str_replace('<br>', '\n', flexicontent_html::encodeHTML(JText::_('Recalculate item aliases. <br>Only for new websites <br>as this can destroy your existing SEO ranks'), 2)) . "')) return false; var url = jQuery(this).data('taskurl'); fc_showDialog(url, 'fc_modal_popup_container', 0, 550, 350, function(){document.body.innerHTML='<span class=\"fc_loading_msg\">"
						.$loading_msg."<\/span>'; window.location.reload(false)}, {'title': '".flexicontent_html::encodeHTML(JText::_('Recalculate item aliases'), 2)."'}); return false;";
			$btn_arr[] = flexicontent_html::addToolBarButton(
				'Recalculate item aliases', $btn_name, $full_js,
				$msg_alert = JText::_('FLEXI_NO_ITEMS_SELECTED'), $msg_confirm = '',
				$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . $this->btn_iv_class : '') . ' ' . $this->tooltip_class, 'icon-loop',
				'data-placement="right" data-taskurl="' . $popup_load_url .'" data-title="' . flexicontent_html::encodeHTML(JText::_('Clear the aliases of items and recalculates them according to current Joomla settings'), 2) . '"', $auto_add = 0, $tag_type='button'
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

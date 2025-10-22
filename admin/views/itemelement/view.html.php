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
 * HTML View class for the FLEXIcontent itemelement screen
 */
class FlexicontentViewItemelement extends FlexicontentViewBaseRecords
{
	var $proxy_option   = 'com_content';
	var $title_propname = 'title';
	var $state_propname = 'state';
	var $db_tbl         = 'content';
	var $name_singular  = 'item';

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
		$perms    = FlexicontentHelperPerm::getPerm();

		$option   = $jinput->getCmd('option', '');
		$view     = $jinput->getCmd('view', '');
		$task     = $jinput->getCmd('task', '');
		$layout   = $jinput->getString('layout', 'default');
		$assocs_id= $jinput->getInt('assocs_id', 0);

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

		if (\Joomla\CMS\Factory::getApplication()->isClient('site'))
		{
			// Note : we use some strings from administrator part, so we will also load administrator language file
			// TODO: remove this need by moving common language string to different file ?

			// Load english language file for 'com_flexicontent' component then override with current language file
			\Joomla\CMS\Factory::getApplication()->getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
			\Joomla\CMS\Factory::getApplication()->getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);
		}

		// Get model
		$model   = $this->getModel();
		$model_s = $this->getModel($this->name_singular);

		// Performance statistics
		if ($print_logging_info = $cparams->get('print_logging_info'))
		{
			global $fc_run_times;
		}

		if ($assocs_id)
		{
			$type_id     = $app->getUserStateFromRequest( $option.'.'.$view.'.type_id', 'type_id', 0, 'int' );
			$item_lang   = $app->getUserStateFromRequest( $option.'.'.$view.'.item_lang', 'item_lang', '', 'string' );
			$created_by  = $app->getUserStateFromRequest( $option.'.'.$view.'.created_by', 'created_by', 0, 'int' );

			$assocanytrans = $user->authorise('flexicontent.assocanytrans', 'com_flexicontent');

			if (!$assocanytrans && !$created_by)
			{
				$created_by = $user->id;
			}

			$_type_id = null;
			$type_data = $model->getTypeData($assocs_id, $_type_id);

			if (!$assocanytrans && !$type_id)
			{
				$type_id = $_type_id;
			}
		}


		/**
		 * Get filters and ordering
		 */

		$count_filters = 0;

		// Category filtering
		$filter_cats        = $model->getState('filter_cats');

		// Order and order direction
		$filter_order      = $model->getState('filter_order');
		$filter_order_Dir  = $model->getState('filter_order_Dir');

		// Various filters
		$filter_assockey  = $model->getState('filter_assockey');
		$filter_lang      = $model->getState('filter_lang');
		$filter_type      = $model->getState('filter_type');
		$filter_author    = $model->getState('filter_author');
		$filter_state     = $model->getState('filter_state');
		$filter_access    = $model->getState('filter_access');

		// Count active filters
		if ($filter_assockey) $count_filters++;
		if ($filter_cats) $count_filters++;
		if ($filter_lang) $count_filters++;
		if ($filter_type) $count_filters++;
		if (strlen($filter_author)) $count_filters++;
		if (strlen($filter_state)) $count_filters++;
		if (strlen($filter_access)) $count_filters++;

		// Text search
		$scope  = $model->getState('scope');
		$search = $model->getState('search');
		$search = StringHelper::trim(StringHelper::strtolower($search));

		/**
		 * Get single type configuration
		 */
		$model_s = $this->getModel('item');

		if ($filter_type)
		{
			$this->single_type = $filter_type;
			$this->tparams  = new \Joomla\Registry\Registry();
			$this->tparams->merge($cparams);

			$tmp_params = $model_s->getTypeparams($this->single_type);
			$tmp_params = new \Joomla\Registry\Registry($tmp_params);
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

		$js = '';

		if ($filter_state)
		{
			$js .= "jQuery('.col_status').addClass('filtered_column');";
		}
		if (strlen($search)) $js .= "jQuery('.col_title').addClass('filtered_column');";
		if ($filter_cats)    $js .= "jQuery('.col_cats').addClass('filtered_column');";
		if ($filter_type)    $js .= "jQuery('.col_type').addClass('filtered_column');";
		if ($filter_author)  $js .= "jQuery('.col_authors').addClass('filtered_column');";
		if ($filter_lang)    $js .= "jQuery('.col_lang').addClass('filtered_column');";
		if ($filter_access)  $js .= "jQuery('.col_access').addClass('filtered_column');";
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
		// NA

		// Create document/toolbar titles
		$doc_title = \Joomla\CMS\Language\Text::_( 'FLEXI_SELECTITEM' );
		$document->setTitle($doc_title);

		// Create the toolbar
		// NA


		/**
		 * Get data from the model, note data retrieval must be before
		 * getTotal() and getPagination() because it also calculates total rows
		 */

		if ( $print_logging_info )  $start_microtime = microtime(true);

		$rows        = $model->getData();
		$pagination  = $model->getPagination();
		$types       = $model->getTypeslist();
		$authors     = $model->getAuthorslist();

		$lang_assocs = $useAssocs ? $model->getLangAssocs() : array();
		$langs       = FLEXIUtilities::getLanguages('code');
		$categories  = $globalcats ?: array();

		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;


		// Ordering active FLAG
		$ordering = $filter_order === 'a.ordering';



		/**
		 * Create List Filters
		 */

		$lists = array();


		// Build category filter
		$fieldname = 'filter_cats';
		$elementid = 'filter_cats';
		$value     = $filter_cats;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => \Joomla\CMS\Language\Text::_('FLEXI_CATEGORY'),
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
				$check_published = ! ($perms->ViewAllCats || $perms->CanCats),
				$check_perms = false
			),
		));


		// Build item type filter

		$filt_label          =  \Joomla\CMS\Language\Text::_('FLEXI_TYPE');
		$filt_placeholder    = ''; //$filt_label ? '' : \Joomla\CMS\Language\Text::_('FLEXI_TYPE');
		$filt_label_css      = '';

		$fieldname = 'filter_type';
		$elementid = 'filter_type';
		$value     = $filter_type;

		if (!$assocs_id || $assocanytrans || !$type_id)
		{
			$lists[$elementid] = $this->getFilterDisplay(array(
				'label' => $filt_label,
				'label_extra_class' => ($value ? ' fc-lbl-inverted' : ''),
				'html' => flexicontent_html::buildtypesselect(
					$types,
					$fieldname,
					$value,
					$displaytype = '-',
					array(
						'class' => $this->select_class,
						'onmouseenter' => 'if (typeof this.oVal == \'undefined\') this.oVal = jQuery(this).val(); this.valChanged = false;',
						'onchange' => 'this.valChanged = JSON.stringify(this.oVal) !== JSON.stringify(jQuery(this).val()); if (this.valChanged && this != document.activeElement) {if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();}',
						'onblur' => 'this.oVal = jQuery(this).val(); if (this.valChanged) {if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();}',
						'placeholder' => $filt_placeholder,
					),
					$elementid
				),
			));
		}
		else
		{
			$lists[$elementid] = $this->getFilterDisplay(array(
				'label' => \Joomla\CMS\Language\Text::_('FLEXI_TYPE'),
				'html' => '<span class="add-on"><i>' . $type_data->name . '</i></span>',
			));
		}


		// Build author filter
		$fieldname = 'filter_author';
		$elementid = 'filter_author';
		$value     = $filter_author;

		if (!$assocs_id || $assocanytrans || !$created_by)
		{
			$lists[$elementid] = $this->getFilterDisplay(array(
				'label' => \Joomla\CMS\Language\Text::_('FLEXI_AUTHOR'),
				'label_extra_class' => ($value ? ' fc-lbl-inverted' : ''),
				'html' => flexicontent_html::buildauthorsselect(
					$authors,
					$fieldname,
					$value,
					$displaytype = '-',
					array(
						'class' => $this->select_class,
						'onmouseenter' => 'if (typeof this.oVal == \'undefined\') this.oVal = jQuery(this).val(); this.valChanged = false;',
						'onchange' => 'this.valChanged = JSON.stringify(this.oVal) !== JSON.stringify(jQuery(this).val()); if (this.valChanged && this != document.activeElement) {if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();}',
						'onblur' => 'this.oVal = jQuery(this).val(); if (this.valChanged) {if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();}',
					)
				),
			));
		}
		else
		{
			$lists[$elementid] = $this->getFilterDisplay(array(
				'label' => \Joomla\CMS\Language\Text::_('FLEXI_AUTHOR'),
				'html' => '<span class="add-on"><i>' . \Joomla\CMS\Factory::getUser($created_by)->name . '</i></span>',
			));
		}


		// Build language filter
		$fieldname = 'filter_lang';
		$elementid = 'filter_lang';
		$value     = $filter_lang;

		if (!$assocs_id || !$item_lang)
		{
			$lists[$elementid] = $this->getFilterDisplay(array(
				'label' => \Joomla\CMS\Language\Text::_('FLEXI_LANGUAGE'),
				'label_extra_class' => ($value ? ' fc-lbl-inverted' : ''),
				'html' => flexicontent_html::buildlanguageslist(
					$fieldname,
					array(
						'class' => $this->select_class,
						'onmouseenter' => 'if (typeof this.oVal == \'undefined\') this.oVal = jQuery(this).val(); this.valChanged = false;',
						'onchange' => 'this.valChanged = JSON.stringify(this.oVal) !== JSON.stringify(jQuery(this).val()); if (this.valChanged && this != document.activeElement) {if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();}',
						'onblur' => 'this.oVal = jQuery(this).val(); if (this.valChanged) {if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();}',
					),
					$value,
					$displaytype = '-'
				),
			));
		}
		else
		{
			$lists[$elementid] = $this->getFilterDisplay(array(
				'label' => \Joomla\CMS\Language\Text::_('FLEXI_LANGUAGE'),
				'label_extra_class' => ($item_lang ? ' fc-lbl-inverted' : ''),
				'html' => '<span class="add-on"><i>' . $item_lang . '</i></span>',
			));
		}




		// Build text search scope
		$scopes = array(
			'a.title'         => \Joomla\CMS\Language\Text::_('FLEXI_TITLE'),
			'_desc_'          => \Joomla\CMS\Language\Text::_('FLEXI_DESCRIPTION'),
			'ie.search_index' => \Joomla\CMS\Language\Text::_('FLEXI_FIELDS_IN_BASIC_SEARCH_INDEX'),
			//'a.metadesc'      => 'Meta (' . \Joomla\CMS\Language\Text::_('FLEXI_DESCRIPTION') . ')',
			//'a.metakey'       => 'Meta (' . \Joomla\CMS\Language\Text::_('FLEXI_KEYWORDS') . ')',
			//'_meta_'          => 'Meta (' . \Joomla\CMS\Language\Text::_('FLEXI_DESCRIPTION') . ' + ' . \Joomla\CMS\Language\Text::_('FLEXI_KEYWORDS') . ')',
		);

		$lists['scope_tip'] = ''; //'<span class="hidden-phone ' . $this->tooltip_class . '" title="'.\Joomla\CMS\Language\Text::_('FLEXI_SEARCH_TEXT_INSIDE').'" style="display: inline-block;"><i class="icon-info-2"></i></span>';
		$lists['scope'] = $this->getScopeSelectorDisplay($scopes, $scope);
		$this->scope_title = isset($scopes[$scope]) ? $scopes[$scope] : reset($scopes);


		// Text search filter value
		$lists['search'] = $search;


		// Table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order']     = $filter_order;


		// Build publication state filter
		$options = array(
			\Joomla\CMS\HTML\HTMLHelper::_('select.option',  '', '-'/*'FLEXI_SELECT_STATE'*/),
			\Joomla\CMS\HTML\HTMLHelper::_('select.option',  'P', 'FLEXI_PUBLISHED'),
			\Joomla\CMS\HTML\HTMLHelper::_('select.option',  'U', 'FLEXI_UNPUBLISHED'),
			\Joomla\CMS\HTML\HTMLHelper::_('select.option',  'PE','FLEXI_PENDING'),
			\Joomla\CMS\HTML\HTMLHelper::_('select.option',  'OQ','FLEXI_TO_WRITE'),
			\Joomla\CMS\HTML\HTMLHelper::_('select.option',  'IP','FLEXI_IN_PROGRESS'),
			\Joomla\CMS\HTML\HTMLHelper::_('select.option',  'A', 'FLEXI_ARCHIVED'),
		);

		$fieldname = 'filter_state';
		$elementid = 'filter_state';
		$value     = $filter_state;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => \Joomla\CMS\Language\Text::_('FLEXI_STATE'),
			'label_extra_class' => ($value ? ' fc-lbl-inverted' : ''),
			'html' => \Joomla\CMS\HTML\HTMLHelper::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'class' => $this->select_class,
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
		//\Joomla\CMS\HTML\HTMLHelper::_('grid.state', $filter_state),


		// Build access level filter
		$access_levels = \Joomla\CMS\HTML\HTMLHelper::_('access.assetgroups');
		array_unshift($access_levels, \Joomla\CMS\HTML\HTMLHelper::_('select.option', '', '-'/*'JOPTION_SELECT_ACCESS'*/));

		// Note 'all items' is already granted to super admins, so no need to check the is-super-admin ('core.admin') separately
		$allitems       = $perms->DisplayAllItems;
		$viewable_items = $cparams->get('iman_viewable_items', 1);
		$editable_items = $cparams->get('iman_editable_items', 0);

		// If can list only viewable items, then skip the non available levels to avoid user confusion
		if (!$allitems && $viewable_items)
		{
			$_aid_arr = array_flip(\Joomla\CMS\Access\Access::getAuthorisedViewLevels($user->id));
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

		$fieldname = 'filter_access';
		$elementid = 'filter_access';
		$value     = $filter_access;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => \Joomla\CMS\Language\Text::_('FLEXI_ACCESS'),
			'label_extra_class' => ($value ? ' fc-lbl-inverted' : ''),
			'html' => \Joomla\CMS\HTML\HTMLHelper::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'class' => $this->select_class,
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


		/**
		 * Filter by item usage a specific file
		 */

		$fieldname = 'filter_assockey';
		$elementid = 'filter_assockey';
		$value     = $filter_assockey;

		if ($filter_assockey)
		{
			$lists[$elementid] = $this->getFilterDisplay(array(
				'label' => \Joomla\CMS\Language\Text::_('FLEXI_ASSOCIATIONS'),
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


		/**
		 * Assign data to template
		 */

		$this->assocs_id = $assocs_id;
		$this->filter_cats = $filter_cats;
		$this->count_filters = $count_filters;

		$this->lists       = $lists;
		$this->rows        = $rows;
		$this->lang_assocs = $lang_assocs;
		$this->langs       = $langs;
		$this->pagination  = $pagination;
		$this->ordering    = $ordering;

		$this->perms  = $perms;
		$this->option = $option;
		$this->view   = $view;
		$this->state  = $this->get('State');

		$this->sidebar = null;


		/**
		 * Render view's template
		 */

		if ( $print_logging_info ) { global $fc_run_times; $start_microtime = microtime(true); }

		parent::display($tpl);

		if ( $print_logging_info ) @$fc_run_times['template_render'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	}
}

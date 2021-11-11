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

jimport('legacy.view.legacy');

/**
 * HTML View class for backend managers (Base)
 */
class FlexicontentViewBaseRecords extends JViewLegacy
{
	var $tooltip_class = FLEXI_J40GE ? 'hasTooltip' : 'hasTooltip';
	var $popover_class = FLEXI_J40GE ? 'hasPopover' : 'hasPopover';
	var $btn_sm_class  = FLEXI_J40GE ? 'btn btn-sm' : 'btn btn-small';
	var $btn_iv_class  = FLEXI_J40GE ? 'btn-dark' : 'btn-inverse';
	var $ina_grp_class = FLEXI_J40GE ? 'input-group' : 'input-append';
	var $inp_grp_class = FLEXI_J40GE ? 'input-group' : 'input-prepend';
	var $select_class  = FLEXI_J40GE ? 'use_select2_lib' : 'use_select2_lib';
	//var $txt_grp_class = FLEXI_J40GE ? 'input-group-text' : 'add-on';
	var $ctrl;


	public function __construct($config = array())
	{
		parent::__construct($config);

		// Default controller is same name as the view
		$this->ctrl = $this->getName();
	}


	/**
	 * Method to get the model object
	 *
	 * @param   string  $name  The name of the model (optional)
	 *
	 * @return  mixed  \JModelLegacy object
	 *
	 * @since   3.0
	 */
	public function getModel($name = null)
	{
		if ($name === null)
		{
			return parent::getModel($name);
		}
		else
		{
			return \JModelLegacy::getInstance($name, $prefix = 'FlexicontentModel', $config = array('ignore_request' => true));
		}
	}

	/**
	 * Method to create the HTML container of a filter
	 *
	 * @param   array    $filter     Contains the configuration of the filter, most notable properties the 'label' text and the 'html' (form elements) of the filter
	 * @return  string   The created HTML container
	 *
	 * @since   3.3.0
	 */
	public function getFilterDisplay($filter)
	{
		$label_extra_class = isset($filter['label_extra_class']) ? $filter['label_extra_class'] : '';
		$label_extra_attrs = isset($filter['label_extra_attrs']) ? ArrayHelper::toString($filter['label_extra_attrs']) : '';

		if (!FLEXI_J40GE)
		{
			$label = $filter['label']
				? '<div class="add-on ' . $label_extra_class .'" ' . $label_extra_attrs .'>' . $filter['label'] . '</div>'
				: '';
			return '
				<div class="fc-filter nowrap_box">
					<div class="input-prepend input-append fc-xpended-row">
						' . $label . '
						' . $filter['html'] . '
					</div>
				</div>
			';
		}
		else
		{
			$label = $filter['label']
				? '<div class="input-group-text ' . $label_extra_class .'" ' . $label_extra_attrs .'>' . $filter['label'] . '</div>'
				: '';
			return '
				<div class="fc-filter nowrap_box">
					<div class="input-group fc-xpended-row">
						<div class="input-group-prepend">
						' . $label . '
							' . $filter['html'] . '
						</div>
					</div>
				</div>
			';
		}
	}


	/**
	 * Method to get the CSS for backend management listings
	 *
	 * @return  void
	 *
	 * @since   3.3.0
	 */
	public function addCssJs()
	{
	}


	/**
	 * Method to add the state changing buttons for setting a new state for multiple records as a dropdown
	 *
	 * @param   array    $btn_arr    An array of state buttons
	 *
	 * @return  void
	 *
	 * @since   3.3.0
	 */
	public function addStateButtons($btn_arr)
	{
		if (count($btn_arr))
		{
			$drop_btn = '
				<button id="toolbar-changestate" class="' . $this->btn_sm_class . ' dropdown-toggle btn-fcaction" data-toggle="dropdown" data-bs-toggle="dropdown">
					<span title="'.JText::_('FLEXI_CHANGE_STATE').'" class="icon-checkmark"></span>
					'.JText::_('FLEXI_CHANGE_STATE').'
					<span class="caret"></span>
				</button>';
			array_unshift($btn_arr, $drop_btn);
			flexicontent_html::addToolBarDropMenu($btn_arr, 'action_btns_group', ' ');
		}
	}


	/**
	 * Method to create state changing buttons for setting a new state for multiple records
	 *
	 * @param   array    $applicable   An array of flags indicating which buttons are applicable
	 *
	 * @since   3.3.0
	 */
	public function getStateButtons($states_applicable = null)
	{
		// Permissions should have been checked by the caller
		$states_applicable = $states_applicable ?: array(
			 1 => true,
			 0 => true,
			 2 => true,
			-2 => true,
		);

		$state_aliases = array(
			 1 => 'P',
			-5 => 'IP',
			 0 => 'U',
			-3 => 'PE',
			-4 => 'OQ',
			 2 => 'A',
			-2 => 'T',
		);

		$states = array(
			 1 => array('btn_text' =>'FLEXI_PUBLISHED', 'btn_desc' =>'', 'btn_icon' => 'icon-publish', 'btn_class' => '', 'btn_name'=>'publish'),
			-5 => array('btn_text' =>'FLEXI_IN_PROGRESS', 'btn_desc' =>'FLEXI_IN_PROGRESS_SLIDER', 'btn_icon' => 'icon-checkmark-2', 'btn_class' => '', 'btn_name'=>'inprogress'),
			 0 => array('btn_text' =>'FLEXI_UNPUBLISHED', 'btn_desc' =>'', 'btn_icon' => 'icon-unpublish', 'btn_class' => '', 'btn_name'=>'unpublish'),
			-3 => array('btn_text' =>'FLEXI_PENDING', 'btn_desc' =>'FLEXI_PENDING_SLIDER', 'btn_icon' => 'icon-question', 'btn_class' => '', 'btn_name'=>'pending'),
			-4 => array('btn_text' =>'FLEXI_TO_WRITE', 'btn_desc' =>'FLEXI_DRAFT_SLIDER', 'btn_icon' => 'icon-pencil', 'btn_class' => '', 'btn_name'=>'draft'),
			 2 => array('btn_text' =>'FLEXI_ARCHIVE', 'btn_desc' =>'', 'btn_icon' => 'icon-archive', 'btn_class' => '_btn-info', 'btn_name'=>'archived'),
			-2 => array('btn_text' =>'FLEXI_TRASH', 'btn_desc' =>'', 'btn_icon' => 'icon-trash', 'btn_class' => '_btn-inverse', 'btn_name'=>'trashed'),
		);

		$contrl  = $this->ctrl . '.';
		$btn_arr = array();

		foreach($states as $sid => $s)
		{
			$alias = $state_aliases[$sid];

			$applicable = isset($states_applicable[$sid])
				? $states_applicable[$sid]
				: null;

			$applicable = isset($states_applicable[$alias])
				? $states_applicable[$alias]
				: $applicable;

			if ($applicable !== null)
			{
				$state_name = is_string($applicable) ? $applicable : $s['btn_name'];

				$full_js = "window.parent.fc_parent_form_submit(" .
					"'fc_modal_popup_container', 'adminForm', " .
					"{'newstate':'" . $sid . "', 'task': '" . $contrl . "changestate'}, " .
					"{'task':'" . $contrl . "changestate', 'is_list':true}" .
				");";

				$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
					$btn_text = $s['btn_text'],
					$btn_name = $state_name,
					$full_js,
					$msg_alert = JText::_('FLEXI_NO_ITEMS_SELECTED'),
					$msg_confirm = '',
					$btn_task = '',
					$extra_js = '',
					$btn_list = true,
					$btn_menu = true,
					$btn_confirm = false,
					$s['btn_class'] . ' ' . $this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ btn-info' : '') . ' ' . $this->tooltip_class,
					$s['btn_icon'],
					$attribs = 'data-placement="right" title="' . flexicontent_html::encodeHTML(JText::_($s['btn_desc']), 2) . '"',
					$auto_add = 0,
					$tag_type='button'
				);
			}
		}

		return $btn_arr;
	}


	/**
	 * Method to get configuration state of FC managers for current user (from cookie)
	 *
	 * @param   string  $name  The name of the model (optional)
	 *
	 * @return  object  The configuration object
	 *
	 * @since   3.3.0
	 */
	public function getUserStatePrefs($fc_man_name = null)
	{
		$cookie_name = 'fc_managers_conf';
		$fc_man_name = $fc_man_name ?: 'fc_' . $this->view_id;

		JFactory::getDocument()->addScriptDeclaration('
		var FCMAN_conf = {};
		FCMAN_conf.fc_man_config_cookie = "' . $cookie_name . '";
		FCMAN_conf.fc_man_manager_name  = "' . $fc_man_name . '";
		');

		$jinput = JFactory::getApplication()->input;

		$FcMansConf = $jinput->cookie->get($cookie_name, '{}', 'string');

		// Parse FC managers configuration cookie
		try
		{
			$FcMansConf = json_decode($FcMansConf);

			// Reset cookie if it is not a class, or if the version hash does not matches (reset column chooser on every version upgrade)
			if (!$FcMansConf || !isset($FcMansConf->vhash) || $FcMansConf->vhash !== FLEXI_VHASH)
			{
				$FcMansConf = new stdClass();
				$FcMansConf->vhash = FLEXI_VHASH;
				$jinput->cookie->set($cookie_name, json_encode($FcMansConf), time()+60*60*24*30, JUri::base(true), '');
			}
		}
		catch (Exception $e)
		{
			$FcMansConf = new stdClass();
			$FcMansConf->vhash = FLEXI_VHASH;
			$jinput->cookie->set($cookie_name, json_encode($FcMansConf), time()+60*60*24*30, JUri::base(true), '');
		}

		return $FcMansConf;
	}


	/**
	 * Method to get the display of text search scope selector
	 *
	 * @param   array   $scopes  The available scopes
	 *
	 * @return  string  The HTML display of the scope selector
	 *
	 * @since   3.3.0
	 */	
	public function getScopeSelectorDisplay(& $scopes, $value, $fieldname = 'scope', $elementid = 'scope')
	{
		$model = $this->getModel();
		
		if (!$scopes)
		{
			$scopes = array('-1' => '- ' . JText::_('FLEXI_ALL') . ' -');

			foreach ($model->search_cols as $label => $column_name)
			{
				// Numeric label means do not add this search case to the scope selector
				if (!is_numeric($label))
				{
					$scopes['a.' . $column_name] = JText::_($label);
				}
			}

			// Remove scope for searching text in all search columns, if only 1 column was added
			if (count($scopes) === 2)
			{
				unset($scopes['-1']);
			}
		}

		$this->scope_title = isset($scopes[$value]) ? $scopes[$value] : reset($scopes);
		$options = array();

		foreach ($scopes as $i => $v)
		{
			$options[] = JHtml::_('select.option', $i, $v);
		}

		array_unshift($options, JHtml::_('select.option', 0, JText::_('FLEXI_SEARCH_TEXT_INSIDE'), 'value', 'text', 'disabled'));

		return JHtml::_('select.genericlist',
			$options,
			$fieldname,
			array(
				'size' => '1',
				'class' => $this->select_class . ' fc_is_selarrow ' . $this->tooltip_class,
				'onchange' => 'jQuery(\'#search\').attr(\'placeholder\', jQuery(this).find(\'option:selected\').text()); jQuery(this).blur();',
				'title' => JText::_('FLEXI_SEARCH_TEXT_INSIDE'),
			),
			'value',
			'text',
			$value,
			$elementid,
			$translate = false
		);
	}


	/**
	 * Method to get CSS for hidding a table cell because its column is currently hidden
	 *
	 * @param   array   $colposition  The position of the column
	 *
	 * @return  string  The inline CSS to use for hidding the table cell
	 *
	 * @since   3.3.2
	 */	
	public function hideCol($colposition)
	{
		static $colsvisible = false;

		if ($colsvisible === false)
		{
			$colsvisible = flexicontent_html::getVisibleColumns($this->data_tbl_id);
			$colsvisible = is_array($colsvisible) ? array_flip($colsvisible) : array();
		}

		return !empty($colsvisible) && !isset($colsvisible[$colposition]) ? 'display: none;' : '';
	}
}

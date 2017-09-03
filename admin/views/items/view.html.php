<?php
/**
 * @version 1.5 stable $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('legacy.view.legacy');
use Joomla\String\StringHelper;

/**
 * View class for the FLEXIcontent categories screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewItems extends JViewLegacy
{
	function display( $tpl = null )
	{
		// ***********
		// Batch tasks
		// ***********
		
		$app     = JFactory::getApplication();
		$jinput  = $app->input;
		
		$task    = $jinput->get('task', '', 'cmd');
		$cid     = $jinput->get('cid', array(), 'array');
		
		if($task == 'copy')
		{
			$behaviour = $jinput->get('copy_behaviour', 'copy/move', 'string');
			$this->setLayout('copy');
			$this->_displayCopyMove($tpl, $cid, $behaviour);
			return;
		}
		
		
		// ********************
		// Initialise variables
		// ********************
		
		global $globalcats;
		
		$option  = $jinput->get('option', '', 'cmd');
		$view    = $jinput->get('view', '', 'cmd');

		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$user     = JFactory::getUser();
		$db       = JFactory::getDbo();
		$document = JFactory::getDocument();
		$session  = JFactory::getSession();
		
		// Some flags
		$useAssocs = flexicontent_db::useAssociations();
		$print_logging_info = $cparams->get('print_logging_info');
		$tip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
		
		// Get model
		$model = $this->getModel();
		
		
		// ***********
		// Get filters
		// ***********
		
		$count_filters = 0;
		
		// File id filtering
		$fileid_to_itemids = $session->get('fileid_to_itemids', array(),'flexicontent');
		$filter_fileid     = $model->getState('filter_fileid');
		if ($filter_fileid) $count_filters++;
		
		// Order type, order, order direction
		$filter_order_type = $model->getState('filter_order_type');
		$filter_order      = $model->getState('filter_order');
		$filter_order_Dir  = $model->getState('filter_order_Dir');
		
		// Category filtering
		$filter_cats        = $model->getState('filter_cats');
		$filter_subcats     = $model->getState('filter_subcats');
		$filter_catsinstate = $model->getState('filter_catsinstate');
		$filter_featured    = $model->getState('filter_featured');

		// filter ordering
		$reOrderingActive = !$filter_order_type
			? $filter_order == 'i.ordering'
			: $filter_order == 'catsordering';

		if ($filter_cats && !$reOrderingActive) $count_filters++;
		if ($filter_subcats!=1 && !$reOrderingActive) $count_filters++;
		if ($filter_catsinstate!=1) $count_filters++;
		if (strlen($filter_featured)) $count_filters++;
		
		
		// Other filters
		$filter_tag    = $model->getState('filter_tag');
		$filter_lang	 = $model->getState('filter_lang');
		$filter_type   = $model->getState('filter_type');
		$filter_author = $model->getState('filter_author');
		$filter_state  = $model->getState('filter_state');
		$filter_access = $model->getState('filter_access');
		
		// Support for using 'ALL', 'ORPHAN' fake states, by clearing other values
		if (is_array($filter_state) && in_array('ALL', $filter_state))     $filter_state = array('ALL');
		if (is_array($filter_state) && in_array('ORPHAN', $filter_state))  $filter_state = array('ORPHAN');
		
		// Count active filters
		if ($filter_tag)   $count_filters++;  if ($filter_lang)   $count_filters++;
		if ($filter_type)  $count_filters++;  if ($filter_author) $count_filters++;
		if ($filter_state) $count_filters++;  if ($filter_access) $count_filters++;
		
		// Date filters
		$date	 				= $model->getState('date');
		$startdate	 	= $model->getState('startdate');
		$enddate	 		= $model->getState('enddate');
		
		$startdate = $db->escape( StringHelper::trim(StringHelper::strtolower( $startdate ) ) );
		$enddate   = $db->escape( StringHelper::trim(StringHelper::strtolower( $enddate ) ) );
		if ($startdate) $count_filters++;
		if ($enddate)   $count_filters++;
		
		// Item ID filter
		$filter_id  = $model->getState('filter_id');
		if ($filter_id) $count_filters++;
		
		// Text search
		$scope  = $model->getState( 'scope' );
		$search = $model->getState( 'search' );
		$search = $db->escape( StringHelper::trim(StringHelper::strtolower( $search ) ) );
		
		
		
		// ***
		// *** Add css and js to document
		// ***
		
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', FLEXI_VHASH);
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x_rtl.css', FLEXI_VHASH);

		// Add JS frameworks
		flexicontent_html::loadFramework('select2');
		JHtml::_('behavior.calendar');

		// Add js function to overload the joomla submitform validation
		JHtml::_('behavior.formvalidation');  // load default validation JS to make sure it is overriden
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);

		$js = '';

		if ($filter_cats)   $js .= "jQuery('.col_cats').addClass('filtered_column');";
		if ($filter_type)   $js .= "jQuery('.col_type').addClass('filtered_column');";
		if ($filter_author) $js .= "jQuery('.col_authors').addClass('filtered_column');";
		if ($filter_state)  $js .= "jQuery('.col_state').addClass('filtered_column');";
		if ($filter_lang)   $js .= "jQuery('.col_lang').addClass('filtered_column');";
		if ($filter_access) $js .= "jQuery('.col_access').addClass('filtered_column');";
		if ($filter_tag)    $js .= "jQuery('.col_tag').addClass('filtered_column');";
		if ($filter_id)     $js .= "jQuery('.col_id').addClass('filtered_column');";
		if ($startdate || $enddate)
		{
			if ($date == 1) {
				$js .= "jQuery('.col_created').addClass('filtered_column');";
			} else if ($date == 2) {
				$js .= "jQuery('.col_revised').addClass('filtered_column');";
			}
		}
		if (strlen($search)) $js .= "jQuery('.col_title').addClass('filtered_column');";
		
		
		// *****************************
		// Get user's global permissions
		// *****************************
		
		$perms = FlexicontentHelperPerm::getPerm();
		
		$CanEdit			= $perms->CanEdit;
		$CanPublish		= $perms->CanPublish;
		$CanDelete		= $perms->CanDelete;
		
		$CanEditOwn			= $perms->CanEditOwn;
		$CanPublishOwn	= $perms->CanPublishOwn;
		$CanDeleteOwn		= $perms->CanDeleteOwn;
		
		$hasEdit    = $CanEdit    || $CanEditOwn     || $CanEdit==null    || $CanEditOwn==null;
		$hasPublish = $CanPublish || $CanPublishOwn  || $CanPublish==null || $CanPublishOwn==null;
		$hasDelete  = $CanDelete  || $CanDeleteOwn   || $CanDelete==null  || $CanDeleteOwn==null;
		
		$CanCats		= $perms->CanCats;
		$CanAccLvl	= $perms->CanAccLvl;
		$CanOrder		= $perms->CanOrder;
		$CanCopy		= $perms->CanCopy;
		$CanArchives= $perms->CanArchives;
		
		// Check if user can create in at least one published category
		require_once("components/com_flexicontent/models/item.php");
		$itemmodel = new FlexicontentModelItem();
		$CanAddAny = $itemmodel->getItemAccess()->get('access-create');
		
		
		// *****************************
		// Get user's global permissions
		// *****************************
		
		$perms = FlexicontentHelperPerm::getPerm();


		// ************************
		// Create Submenu & Toolbar
		// ************************
		
		// Create Submenu (and also check access to current view)
		FLEXIUtilities::ManagerSideMenu(null);
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_ITEMS' );
		$site_title = $document->getTitle();
		JToolbarHelper::title( $doc_title, 'items' );
		$document->setTitle($doc_title .' - '. $site_title);
		
		$toolbar = JToolbar::getInstance('toolbar');
		$loading_msg = flexicontent_html::encodeHTML(JText::_('FLEXI_LOADING') .' ... '. JText::_('FLEXI_PLEASE_WAIT'), 2);
		
		// Implementation of multiple-item state selector
		$add_divider = false;
		if ( $hasPublish )
		{
			$add_divider = true;
			$popup_load_url = JUri::base().'index.php?option=com_flexicontent&task=items.selectstate&format=raw';

			/*$btn_task = '';
			//$toolbar->appendButton('Popup', 'publish', JText::_('FLEXI_CHANGE_STATE'), str_replace('&', '&amp;', $popup_load_url), 800, 300);  //JToolbarHelper::publishList( $btn_task );
			$js .= "
				jQuery('#toolbar-publish a.toolbar, #toolbar-publish button').attr('href', '".$popup_load_url."')
					.attr('onclick', 'var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 780, 300, false, {\'title\': \'".flexicontent_html::encodeHTML(JText::_('FLEXI_CHANGE_STATE'), 2)."\', \'modal\': true}); return false;');
			";
			JToolbarHelper::custom( $btn_task, 'publish.png', 'publish_f2.png', 'FLEXI_CHANGE_STATE', true );*/

			/*$msg_alert   = JText::_('FLEXI_NO_ITEMS_SELECTED');
			$msg_confirm = JText::_('FLEXI_ARE_YOU_SURE');
			$btn_task    = '';
			$extra_js    = "";
			$full_js     = "
				jQuery('#toolbar-publish a.toolbar, #toolbar-publish button').attr('href', '".$popup_load_url."')
					.attr('onclick', 'var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 780, 300, false, {\'title\': \'".flexicontent_html::encodeHTML(JText::_('FLEXI_CHANGE_STATE'), 2)."\', \'modal\': true}); return false;');
			";
			flexicontent_html::addToolBarButton(
				'FLEXI_CHANGE_STATE', 'publish', $full_js, $msg_alert, $msg_confirm,
				$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true, $btn_class="",
				$btn_icon="icon-publish", $attrs='', $auto_add = true, $tag_type='button');*/

			$btn_arr = $this->getStateButtons();
			$drop_btn = '
				<button type="button" class="btn btn-small dropdown-toggle" data-toggle="dropdown">
					<span title="'.JText::_('FLEXI_CHANGE_STATE').'" class="icon-menu"></span>
					'.JText::_('FLEXI_CHANGE_STATE').'
					<span class="caret"></span>
				</button>';
			array_unshift($btn_arr, $drop_btn);
			flexicontent_html::addToolBarDropMenu($btn_arr, 'action_btns_group', ' ');
		}

		if ($hasDelete)
		{
			if ( $filter_state && in_array('T',$filter_state) ) {
				//$btn_msg = JText::_('FLEXI_ARE_YOU_SURE');
				//$btn_task = 'items.remove';
				//JToolbarHelper::deleteList($btn_msg, $btn_task);
				$msg_alert   = JText::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_DELETE'));
				$msg_confirm = JText::_('FLEXI_ARE_YOU_SURE');
				$btn_task    = 'items.remove';
				$extra_js    = "";
				flexicontent_html::addToolBarButton(
					'FLEXI_DELETE', 'delete', '', $msg_alert, $msg_confirm,
					$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true, $btn_class="btn-warning");
			}
			/*else
			{
				$msg_alert   = JText::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_TRASH'));
				$msg_confirm = JText::_('FLEXI_TRASH_CONFIRM').' '.JText::_('FLEXI_NOTES').': '.JText::_('FLEXI_DELETE_PERMANENTLY');
				$btn_task    = 'items.changestate';
				$extra_js    = "document.adminForm.newstate.value='T';";
				flexicontent_html::addToolBarButton(
					'FLEXI_TRASH', 'trash', '', $msg_alert, $msg_confirm,
					$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true, $btn_class="");
			}*/
			$add_divider = true;
		}
		
		/*if ($CanArchives && (!$filter_state || !in_array('A',$filter_state)))
		{
			$msg_alert   = JText::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_ARCHIVE'));
			$msg_confirm = JText::_('FLEXI_ARCHIVE_CONFIRM');
			$btn_task    = 'items.changestate';
			$extra_js    = "document.adminForm.newstate.value='A';";
			flexicontent_html::addToolBarButton(
				'FLEXI_ARCHIVE', 'archive', $full_js='', $msg_alert, $msg_confirm,
				$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true);
			$add_divider = true;
		}*/
		
		if (
			($CanArchives && $filter_state && in_array('A',$filter_state)) ||
			($hasDelete   && $filter_state && in_array('T',$filter_state))
		) {
			$msg_alert   = JText::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_RESTORE'));
			$msg_confirm = JText::_('FLEXI_RESTORE_CONFIRM');
			$btn_task    = 'items.changestate';
			$extra_js    = "document.adminForm.newstate.value='P';";
			flexicontent_html::addToolBarButton(
				'FLEXI_RESTORE', 'restore', $full_js='', $msg_alert, $msg_confirm,
				$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true);
		}
		if ($add_divider) { JToolbarHelper::divider(); }
		
		$add_divider = false;
		if ($CanAddAny)
		{
			$btn_task = '';
			$popup_load_url = JUri::base().'index.php?option=com_flexicontent&view=types&format=raw';
			//$toolbar->appendButton('Popup', 'new',  JText::_('FLEXI_NEW'), str_replace('&', '&amp;', $popup_load_url), 780, 240);   //JToolbarHelper::addNew( $btn_task );
			$js .= "
				jQuery('#toolbar-new a.toolbar, #toolbar-new button').attr('href', '".$popup_load_url."')
					.attr('onclick', 'var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 780, 240, false, {\'title\': \'".flexicontent_html::encodeHTML(JText::_('FLEXI_TYPE'), 2)."\'}); return false;');
			";
			JToolbarHelper::custom( $btn_task, 'new.png', 'new_f2.png', 'FLEXI_NEW', false );
			$add_divider = true;
		}
		if ($hasEdit)
		{
			$btn_task = 'items.edit';
			JToolbarHelper::editList($btn_task);
			$add_divider = true;
		}
		if ($add_divider) { JToolbarHelper::divider(); }
		
		$add_divider = false;
		if ($CanAddAny && $CanCopy)
		{
			$btn_task = 'items.copy';
			JToolbarHelper::custom( $btn_task, 'copy.png', 'copy_f2.png', 'FLEXI_BATCH' /*'FLEXI_COPY_MOVE'*/ );
			if ($useAssocs) {
				JToolbarHelper::custom( 'translate', 'translate', 'translate', 'FLEXI_TRANSLATE' );
			}
			$add_divider = true;
		}
		$btn_task = 'items.checkin';
		JToolbarHelper::checkin($btn_task);
		
		if ( $cparams->get('show_csvbutton_be', 0) )
		{
			$full_js     = "window.location.replace('" .JUri::base().'index.php?option=com_flexicontent&view=items&format=csv'. "')";
			flexicontent_html::addToolBarButton(
				'CSV', 'csvexport', $full_js, $msg_alert='', $msg_confirm='',
				$btn_task='', $extra_js="", $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="btn-info", $btn_icon="icon-download");
		}

		if ($add_divider) { JToolbarHelper::divider(); JToolbarHelper::spacer(); }
		if ($perms->CanConfig) {
			$session = JFactory::getSession();
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

		// ***
		// *** Get data from the model
		// ***
		
		$badcatitems  = (int) $model->getUnboundedItems($limit=10000000, $count_only=true, $checkNoExtData=false, $checkInvalidCat=true);
		$unassociated = (int) $model->getUnboundedItems($limit=10000000, $count_only=true, $checkNoExtData=true, $checkInvalidCat=false);
		
		$bind_limit = $jinput->get('bind_limit', ($unassociated >= 1000 ? 1000 : 250), 'int');
		
		$rows     	= $this->get( 'Data');
		$pagination	= $this->get( 'Pagination' );
		$types			= $this->get( 'Typeslist' );
		$authors		= $this->get( 'Authorslist' );
		// these depend on data rows and must be called after getting data
		$extraCols  = $this->get( 'ExtraCols' );
		$customFilts= $this->get( 'CustomFilts' );
		foreach($customFilts as $filter) if (count($filter->value)) $count_filters++;
		$itemCats   = $this->get( 'ItemCats' );
		$itemTags   = $this->get( 'ItemTags' );

		// Get Field values to be used for rendering custom columns
		if ($extraCols)
		{
			FlexicontentFields::getFields($rows, 'category');
		}

		if ($useAssocs)  $langAssocs = $this->get( 'LangAssocs' );
		$langs = FLEXIUtilities::getLanguages('code');
		$categories = $globalcats ? $globalcats : array();
		
		
		$inline_ss_max = 50000;
		$drag_reorder_max = 200;
		if ( $pagination->limit > $drag_reorder_max ) $cparams->set('draggable_reordering', 0);
		
		
		// ******************************************
		// Add usability notices if these are enabled
		// ******************************************
		
		$conf_link = '<a href="index.php?option=com_config&view=component&component=com_flexicontent&path=" class="btn btn-info btn-small">'.JText::_("FLEXI_CONFIG").'</a>';
		
		if ( $cparams->get('show_usability_messages', 1)  && !$unassociated && !$badcatitems)     // Important usability messages
		{
			$notice_iss_disabled = $app->getUserStateFromRequest( $option.'.items.notice_iss_disabled',	'notice_iss_disabled',	0, 'int' );
			if (!$notice_iss_disabled && $pagination->limit > $inline_ss_max) {
				$app->setUserState( $option.'.items.notice_iss_disabled', 1 );
				$app->enqueueMessage(JText::sprintf('FLEXI_INLINE_ITEM_STATE_SELECTOR_DISABLED', $inline_ss_max), 'notice');
				$show_turn_off_notice = 1;
			}
			
			$notice_drag_reorder_disabled = $app->getUserStateFromRequest( $option.'.items.notice_drag_reorder_disabled',	'notice_drag_reorder_disabled',	0, 'int' );
			if (!$notice_drag_reorder_disabled && $pagination->limit > $drag_reorder_max) {
				$app->setUserState( $option.'.items.notice_drag_reorder_disabled', 1 );
				$app->enqueueMessage(JText::sprintf('FLEXI_DRAG_REORDER_DISABLED', $drag_reorder_max), 'notice');
				$show_turn_off_notice = 1;
			}
			
			if (!empty($show_turn_off_notice)) {
				$disable_use_notices = '<span class="fc-nowrap-box fc-disable-notices-box">'. JText::_('FLEXI_USABILITY_MESSAGES_TURN_OFF_IN').' '.$conf_link.'</span><div class="fcclear"></div>';
				$app->enqueueMessage($disable_use_notices, 'notice');
			}
		}
		
		$this->minihelp = '
			<div id="fc-mini-help" class="fc-mssg fc-info" style="display:none;">
				'.JText::sprintf('FLEXI_ABOUT_CUSTOM_FIELD_COLUMNS_COMPONENT_AND_PER_TYPE', $conf_link).'<br/><br/>
				<sup>[1]</sup> ' . JText::_('FLEXI_TMPL_NOT_SET_USING_TYPE_DEFAULT') . '<br />
				<sup>[2]</sup> ' . JText::sprintf('FLEXI_INLINE_ITEM_STATE_SELECTOR_DISABLED', $inline_ss_max) . '<br />
				'.( $useAssocs ? '
				<sup>[3]</sup> ' . JText::_('FLEXI_SORT_TO_GROUP_TRANSLATION') . '<br />
				' : '').'
				<sup>[4]</sup> ' . JText::_('FLEXI_MULTIPLE_ITEM_ORDERINGS') . '<br />
			</div>
		';
		
		
		// *******************
		// Create Filters HTML
		// *******************
		
		// filter publication state
		$states 	= array();
		//$states[]['items'][] = array('value' => '', 'text' => '-' /*JText::_('FLEXI_SELECT_STATE')*/);

		$grp = 'single_status_states';
		$states[$grp] = array();
		$states[$grp]['id'] = 'single_status_states';
		$states[$grp]['text'] = JText::_('FLEXI_SINGLE_STATUS');
		$states[$grp]['items'] = array(
			array('value' => 'P', 'text' => JText::_('FLEXI_PUBLISHED')),
			array('value' => 'U', 'text' => JText::_('FLEXI_UNPUBLISHED')),
			array('value' => 'PE', 'text' => JText::_('FLEXI_PENDING')),
			array('value' => 'OQ', 'text' => JText::_('FLEXI_TO_WRITE')),
			array('value' => 'IP', 'text' => JText::_('FLEXI_IN_PROGRESS')),
			array('value' => 'RV', 'text' => JText::_('FLEXI_REVISED_VER')),
			array('value' => 'A', 'text' => JText::_('FLEXI_ARCHIVED')),
			array('value' => 'T', 'text' => JText::_('FLEXI_TRASHED'))
		);

		$grp = 'status_groups_states';
		$states[$grp] = array();
		$states[$grp]['id'] = 'status_groups_states';
		$states[$grp]['text'] = JText::_('FLEXI_STATUS_GROUPS');
		$states[$grp]['items'] = array(
			array('value' => 'ALL', 'text' => JText::_('FLEXI_GRP_ALL') . ' ' . JText::_('FLEXI_STATE_S')),
			array('value' => 'ALL_P', 'text' => JText::_('FLEXI_GRP_PUBLISHED') . ' ' . JText::_('FLEXI_STATE_S')),
			array('value' => 'ALL_U', 'text' => JText::_('FLEXI_GRP_UNPUBLISHED') . ' ' . JText::_('FLEXI_STATE_S')),
			array('value' => 'ORPHAN', 'text' => JText::_('FLEXI_GRP_ORPHAN'))
		);

		$attribs = 'class="use_select2_lib" multiple="multiple" size="3" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"';
		$lists['filter_state'] = ($filter_state || 1 ? '<div class="add-on">'.JText::_('FLEXI_STATE').'</div>' : '').
			JHtml::_('select.groupedlist', $states, 'filter_state[]',
				array('id' => 'filter_state', 'group.id' => 'id', 'list.attr' => $attribs, 'list.select' => $filter_state)
			);
			//JHtml::_('grid.state', $filter_state );


		// include subcats boolean list
		$subcats_na = $filter_order_type && $filter_cats && ($filter_order=='i.ordering' || $filter_order=='catsordering');
		$lists['filter_subcats'] = $subcats_na
			? '<img src="components/com_flexicontent/assets/images/comments.png" style="margin: 4px 0 0 8px;" class="hasTooltip" title="'.JText::_( 'FLEXI_SUBCATEGORIES_NOT_INCLUDED_DURING_CATORDER', true ).' &lt;br/&gt; &lt;br/&gt; '.JText::_('FLEXI_SUBCATEGORIES_NOT_INCLUDED_DURING_CATORDER_DESC', true).'" />'
			: '';
		$lists['filter_subcats'] .= ($subcats_na ? '<div style="display:none">' : '') . '
			<input type="checkbox" id="filter_subcats" name="filter_subcats" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()" value="1" '.($filter_subcats ? ' checked="checked" ' : '').' />
			<label id="filter_subcats-lbl" for="filter_subcats" style="margin: 0 12px; vertical-align: middle;"></label>
		' . ($subcats_na ? '</div>' : '');

		$lists['filter_subcats'] = ($filter_subcats || 1 ? '<div class="add-on'. ($reOrderingActive ? ' fc-lbl-short' : '') .'">'.JText::_('FLEXI_SUBCATEGORIES').'</div>' : '')
			. $lists['filter_subcats'];

		// build the order type boolean list
		$featured_ops = array();
		$featured_ops[] = JHtml::_('select.option', '', '-');
		$featured_ops[] = JHtml::_('select.option', '0', JText::_('FLEXI_NO'));
		$featured_ops[] = JHtml::_('select.option', '1', JText::_('FLEXI_YES'));

		$lists['filter_featured'] = JHtml::_('select.genericlist', $featured_ops, 'filter_featured', 'size="1" class="use_select2_lib fc_skip_highlight" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_featured, 'filter_featured', $translate=true );
		$lists['filter_featured'] = ($filter_featured || 1 ? '<div class="add-on">'.JText::_('FLEXI_FEATURED').'</div>' : '').$lists['filter_featured'];

		// build the include non-published cats boolean list
		$catsinstate[1] = JText::_( 'FLEXI_PUBLISHED' );
		$catsinstate[0] = JText::_( 'FLEXI_UNPUBLISHED' );
		$catsinstate[99] = JText::_( 'FLEXI_ANY' );
		$catsinstate[2] = JText::_( 'FLEXI_ARCHIVED' );
		$catsinstate[-2] = JText::_( 'FLEXI_TRASHED' );
		$_catsinstate = array();
		foreach ($catsinstate as $i => $v)
		{
			$_catsinstate[] = JHtml::_('select.option', $i, $v);
		}

		$catsinstate_attrs = ' class="add-on icon-info '.$tip_class.'" title="'.flexicontent_html::getToolTip(JText::_('FLEXI_LIST_ITEMS_IN_CATS', true), JText::_('FLEXI_LIST_ITEMS_IN_CATS_DESC', true), 0, 1).'" ';
		$lists['filter_catsinstate'] = ($filter_catsinstate || 1 ? '<div '.$catsinstate_attrs.'>&nbsp;'.JText::_('FLEXI_IN_CAT_STATE').'</div>' : '').
			JHtml::_('select.genericlist', $_catsinstate, 'filter_catsinstate', 'size="1" class="use_select2_lib'.($filter_catsinstate!=1 ? '' : ' fc_skip_highlight').'" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_catsinstate, 'filter_catsinstate' );
		//$lists['filter_catsinstate'] = JHtml::_('select.radiolist', $_catsinstate, 'filter_catsinstate', 'size="1" class="inputbox" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_catsinstate );
		/*$lists['filter_catsinstate']  = '';
		foreach ($catsinstate as $i => $v) {
			$checked = $filter_catsinstate == $i ? ' checked="checked" ' : '';
			$lists['filter_catsinstate'] .= '<input type="radio" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()" class="inputbox" '.$checked.' value="'.$i.'" id="filter_catsinstate'.$i.'" name="filter_catsinstate" />';
			$lists['filter_catsinstate'] .= '<label class="" id="filter_catsinstate'.$i.'-lbl" for="filter_catsinstate'.$i.'">'.$v.'</label>';
		}*/
		
		// build the order type boolean list
		$order_types = array();
		$order_types[] = JHtml::_('select.option', '0', JText::_('FLEXI_ORDER_JOOMLA').' ('.JText::_('FLEXI_ORDER_JOOMLA_ABOUT').')' );
		$order_types[] = JHtml::_('select.option', '1', JText::_('FLEXI_ORDER_FLEXICONTENT').' ('.JText::_('FLEXI_ORDER_FLEXICONTENT_ABOUT').')' );
		//$lists['filter_order_type'] = JHtml::_('select.radiolist', $order_types, 'filter_order_type', 'size="1" class="inputbox" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_order_type );
		$lists['filter_order_type'] = JHtml::_('select.genericlist', $order_types, 'filter_order_type', 'size="1" class="use_select2_lib fc_skip_highlight" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_order_type, 'filter_order_type', $translate=true );
		
		// build the categories select list for filter
		$lists['filter_cats'] = ($filter_cats || 1 ? '<div class="add-on'. ($reOrderingActive ? ' fc-lbl-short' : '') .'">'.JText::_('FLEXI_CATEGORY').'</div>' : '').
			flexicontent_cats::buildcatselect($categories, 'filter_cats', $filter_cats, (1 ? '-' : 2), 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', $check_published=false, $check_perms=false);

		//build type select list
		$lists['filter_type'] = ($filter_type || 1 ? '<div class="add-on">'.JText::_('FLEXI_TYPE').'</div>' : '').
			flexicontent_html::buildtypesselect($types, 'filter_type[]', $filter_type, 0/*'-'*//*true*/, 'class="use_select2_lib" multiple="multiple" size="3" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'filter_type');

		//build authors select list
		$lists['filter_author'] = ($filter_author || 1 ? '<div class="add-on">'.JText::_('FLEXI_AUTHOR').'</div>' : '').
			flexicontent_html::buildauthorsselect($authors, 'filter_author[]', $filter_author, 0/*'-'*//*true*/, 'class="use_select2_lib" multiple="multiple" size="3" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"');

		if ($badcatitems) $lists['default_cat'] = flexicontent_cats::buildcatselect($categories, 'default_cat', '', 2, 'class="use_select2_lib"', false, false);
		
		//search filter
		$scopes = array();
		$scopes[1] = JText::_( 'FLEXI_TITLE' );
		$scopes[2] = JText::_( 'FLEXI_DESCRIPTION' );
		$scopes[4] = JText::_( 'FLEXI_FIELDS_IN_BASIC_SEARCH_INDEX' );
		$_scopes = array();
		foreach ($scopes as $i => $v) {
			$_scopes[] = JHtml::_('select.option', $i, $v);
		}
		//$lists['scope'] = JHtml::_('select.radiolist', $_scopes, 'scope', 'size="1" class="inputbox"', 'value', 'text', $scope );
		$lists['scope'] = '
			<span class="hasTooltip" style="display:inline-block; padding:0; margin:0;" title="'.JText::_('FLEXI_SEARCH_TEXT_INSIDE').'"><i class="icon-info"></i></span>
			'.JHtml::_('select.genericlist', $_scopes, 'scope', 'size="1" class="use_select2_lib fc_skip_highlight" onchange="jQuery(\'#search\').attr(\'placeholder\', jQuery(this).find(\'option:selected\').text());" ', 'value', 'text', $scope, 'scope' );
		
		/*$lists['scope']  = '';
		foreach ($scopes as $i => $v) {
			$checked = $scope == $i ? ' checked="checked" ' : '';
			$lists['scope'] .= '<input type="radio" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()" class="inputbox" '.$checked.' value="'.$i.'" id="scope'.$i.'" name="scope" />';
			$lists['scope'] .= '<label class="" id="scope'.$i.'-lbl" for="scope'.$i.'">'.$v.'</label>';
		}*/
		
		// build item dates option list
		$dates[1] = JText::_( 'FLEXI_CREATED' );
		$dates[2] = JText::_( 'FLEXI_REVISED' );
		$dates[3] = JText::_( 'FLEXI_PUBLISH_UP' );
		$dates[4] = JText::_( 'FLEXI_PUBLISH_DOWN' );
		$_dates = array();
		foreach ($dates as $i => $v) {
			$_dates[] = JHtml::_('select.option', $i, $v);
		}
		//$lists['date'] = JHtml::_('select.radiolist', $_dates, 'date', 'size="1" class="inputbox"', 'value', 'text', $date );
		$lists['date'] = //'<div class="add-on">'.JText::_('FLEXI_DATE').'</div>'.
			JHtml::_('select.genericlist', $_dates, 'date', 'size="1" class="use_select2_lib fc_skip_highlight"', 'value', 'text', $date, 'date' );
		/*$lists['date']  = '';
		foreach ($dates as $i => $v) {
			$checked = $date == $i ? ' checked="checked" ' : '';
			$lists['date'] .= '<input type="radio" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()" class="inputbox" '.$checked.' value="'.$i.'" id="date'.$i.'" name="date" />';
			$lists['date'] .= '<label class="" id="date'.$i.'-lbl" for="date'.$i.'">'.$v.'</label>';
		}*/
		
		$lists['startdate'] = JHtml::_('calendar', $startdate, 'startdate', 'startdate', '%Y-%m-%d', array('class'=>'', 'size'=>'8',  'maxlength'=>'19', 'style'=>'width:auto', 'placeholder'=>JText::_('FLEXI_FROM')));
		$lists['enddate'] 	= JHtml::_('calendar', $enddate, 'enddate', 'enddate', '%Y-%m-%d', array('class'=>'', 'size'=>'8',  'maxlength'=>'19', 'style'=>'width:auto', 'placeholder'=>JText::_('FLEXI_TO')));

		// search filter
		$bind_limits = array();
		$bind_limits[] = JHtml::_('select.option', 250, '250 ' . JText::_( 'FLEXI_ITEMS' ) );
		$bind_limits[] = JHtml::_('select.option', 500, '500 ' . JText::_( 'FLEXI_ITEMS' ) );
		$bind_limits[] = JHtml::_('select.option', 750, '750 ' . JText::_( 'FLEXI_ITEMS' ) );
		$bind_limits[] = JHtml::_('select.option', 1000,'1000 ' . JText::_( 'FLEXI_ITEMS' ) );
		$bind_limits[] = JHtml::_('select.option', 1500,'1500 ' . JText::_( 'FLEXI_ITEMS' ) );
		$bind_limits[] = JHtml::_('select.option', 2000,'2000 ' . JText::_( 'FLEXI_ITEMS' ) );
		$lists['bind_limits'] = JHtml::_('select.genericlist', $bind_limits, 'bind_limit', ' class="use_select2_lib" ', 'value', 'text', $bind_limit, 'bind_limit' );

		// search filter
		$lists['search'] = $search;
		// search id
		$lists['filter_id'] = $filter_id;

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		
		//build tags filter
		$lists['filter_tag'] = ($filter_tag || 1 ? '<div class="add-on">'.JText::_('FLEXI_TAG').'</div>' : '').
			flexicontent_html::buildtagsselect('filter_tag[]', 'class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()" multiple="multiple" size="3" ', $filter_tag, 0);

		//build languages filter
		$lists['filter_lang'] = ($filter_lang || 1 ? '<div class="add-on">'.JText::_('FLEXI_LANGUAGE').'</div>' : '').
			flexicontent_html::buildlanguageslist('filter_lang[]', 'class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()" multiple="multiple" size="3" ', $filter_lang, 1/*'-'*//*2*/);
		
		// build access level filter
		$access_levels = JHtml::_('access.assetgroups');
		/*if ( $cparams->get('iman_viewable_items', 1) )  // only viewable items is enabled, skip the non available levels to avoid user confusion
		{
			$_aid_arr = array_flip(JAccess::getAuthorisedViewLevels($user->id));
			$_levels = array();
			foreach($access_levels as $i => $level)
			{
				if ( isset($_aid_arr[$level->value]) )
					$_levels[] = $level;
				//else $access_levels[$i]->disable = 1;
			}
			$access_levels = $_levels;
		}*/  // Above code is maybe problematic (e.g. in multi-sites), need to test more
		//array_unshift($access_levels, JHtml::_('select.option', '', '-'/*'JOPTION_SELECT_ACCESS'*/) );
		$fieldname = 'filter_access[]';  // make multivalue
		$elementid = 'filter_access';
		$attribs = 'class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()" multiple="multiple"';
		$lists['filter_access'] = ($filter_access || 1 ? '<div class="add-on">'.JText::_('FLEXI_ACCESS').'</div>' : '').
			JHtml::_('select.genericlist', $access_levels, $fieldname, $attribs, 'value', 'text', $filter_access, $elementid, $translate=true );
		
		// filter by item usage a specific file
		if ($fileid_to_itemids && count($fileid_to_itemids)) {
			$files_data = $model->getFileData(array_keys($fileid_to_itemids));
			$file_options = array();
			$file_options[] = JHtml::_('select.option',  '', '-'/*.JText::_( 'FLEXI_SELECT' ).' '.JText::_( 'FLEXI_FILE' )*/ );
			foreach($files_data as $_file) {
				$file_options[] = JHtml::_('select.option', $_file->id, $_file->altname );
			}
			flexicontent_html::loadFramework('select2');
			$lists['filter_fileid'] = ($filter_fileid || 1 ? '<div class="add-on">'.JText::_('FLEXI_ITEMS_USING').' '.JText::_('FLEXI_FILE').'</div>' : '').
				JHtml::_('select.genericlist', $file_options, 'filter_fileid', 'size="1" class="use_select2_lib'.($filter_fileid ? '' : ' fc_skip_highlight').'" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_fileid );
		}
		
		//assign data to template
		$this->CanTemplates = $perms->CanTemplates;
		$this->count_filters = $count_filters;
		//$this->filter_stategrp = $filter_stategrp;
		$this->filter_catsinstate = $filter_catsinstate;
		$this->db = $db;
		$this->lists = $lists;
		$this->rows = $rows;
		$this->itemCats = $itemCats;
		$this->itemTags = $itemTags;
		$this->extra_fields = $extraCols;
		$this->custom_filts = $customFilts;
		if ($useAssocs)  $this->lang_assocs = $langAssocs;
		$this->langs = $langs;
		$this->cid = $cid;
		$this->pagination = $pagination;
		$this->reOrderingActive = $reOrderingActive;
		$this->CanOrder = $CanOrder;
		$this->CanCats = $CanCats;
		$this->CanAccLvl = $CanAccLvl;
		$this->unassociated = $unassociated;
		$this->badcatitems = $badcatitems;
		
		// filters
		$this->filter_id = $filter_id;
		$this->filter_state = $filter_state;
		$this->filter_author = $filter_author;
		$this->filter_type = $filter_type;
		
		$this->filter_cats = $filter_cats;
		$this->filter_subcats = $filter_subcats;
		$this->filter_catsinstate = $filter_catsinstate;
		
		$this->filter_order_type = $filter_order_type;
		$this->filter_order = $filter_order;
		
		$this->filter_lang = $filter_lang;
		$this->filter_access = $filter_access;
		$this->filter_tag = $filter_tag;
		$this->filter_fileid = $filter_fileid;
		
		$this->inline_ss_max = $inline_ss_max;
		$this->scope = $scope;
		$this->search = $search;
		$this->date = $date;
		$this->startdate = $startdate;
		$this->enddate = $enddate;
		
		$this->option = $option;
		$this->view = $view;

		$print_logging_info = $cparams->get('print_logging_info');
		if ( $print_logging_info ) { global $fc_run_times; $start_microtime = microtime(true); }
		
		$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;
		parent::display($tpl);
		
		if ( $print_logging_info ) @$fc_run_times['template_render'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	}
	
	
	function _displayCopyMove($tpl = null, $cid = array(), $behaviour='copy/move')
	{
		global $globalcats;
		$app = JFactory::getApplication();

		// Initialise variables
		$user 		= JFactory::getUser();
		$document	= JFactory::getDocument();
		
		// Add css to document
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', FLEXI_VHASH);
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x_rtl.css', FLEXI_VHASH);

		// Add js to document
		//JHtml::_('behavior.tooltip');
		flexicontent_html::loadFramework('select2');
		$document->addScriptVersion(JUri::base(true).'/components/com_flexicontent/assets/js/copymove.js', FLEXI_VHASH);
		
		// Add js function to overload the joomla submitform validation
		JHtml::_('behavior.formvalidation');  // load default validation JS to make sure it is overriden
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);
		
		// Create document/toolbar titles
		if ($behaviour == 'translate') {
			$doc_title =  JText::_( 'FLEXI_TRANSLATE_ITEM' );
		} else {
			$doc_title = JText::_( 'FLEXI_BATCH' /*'FLEXI_COPYMOVE_ITEM'*/ );
		}
		$site_title = $document->getTitle();
		JToolbarHelper::title( $doc_title, 'itemadd' );
		$document->setTitle($doc_title .' - '. $site_title);
		
		// Create the toolbar
		JToolbarHelper::save('items.copymove');
		JToolbarHelper::cancel('items.cancel');

		//Get data from the model
		$rows     = $this->get( 'Data');
		$itemCats = $this->get( 'ItemCats' );		
		$categories = $globalcats;
		
		// build the main category select list
		$lists['maincat'] = flexicontent_cats::buildcatselect($categories, 'maincat', '', JText::_('FLEXI_DO_NOT_CHANGE'), 'class="use_select2_lib" size="10"', false, false);
		
		// build the secondary categories select list
		$lists['seccats'] = flexicontent_cats::buildcatselect($categories, 'seccats[]', '', 0, 'class="use_select2_lib" multiple="multiple" size="10"', false, false);
		
		// build language selection
		$lists['language'] = flexicontent_html::buildlanguageslist('language', ''/*'class="use_select2_lib"'*/, '', $type = ($behaviour != 'translate' ? JText::_( 'FLEXI_NOCHANGE_LANGUAGE') : 7),
			$allowed_langs=null, $published_only=true, $disable_langs=null, $add_all=true, array('required'=>1)
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
		$lists['access'] = JHtml::_('select.genericlist', $levels, $fieldname, $attribs, 'value', 'text', $value='', $elementid, $translate=true );
		
		
		
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
	 * Method to create state buttons for setting a new state for many items
	 * 
	 * @since 1.5
	 */
	function getStateButtons()
	{
		// Use general permissions since we do not have examine any specific item
		$perms = FlexicontentHelperPerm::getPerm();
		$auth_publish = $perms->CanPublish || $perms->CanPublishOwn || $perms->CanPublish==null || $perms->CanPublishOwn==null;
		$auth_delete  = $perms->CanDelete  || $perms->CanDeleteOwn  || $perms->CanDelete==null  || $perms->CanDeleteOwn==null;
		$auth_archive = $perms->CanArchives;
		
		if ($auth_publish)
		{
			$state['P'] = array( 'name' =>'FLEXI_PUBLISHED', 'desc' =>'', 'btn_icon' => 'icon-publish', 'btn_class' => '_btn-success', 'btn_name'=>'publish' );
			$state['IP'] = array( 'name' =>'FLEXI_IN_PROGRESS', 'desc' =>'FLEXI_IN_PROGRESS_SLIDER', 'btn_icon' => 'icon-checkmark-2', 'btn_class' => '_btn-success', 'btn_name'=>'inprogress' );
			$state['U'] = array( 'name' =>'FLEXI_UNPUBLISHED', 'desc' =>'', 'btn_icon' => 'icon-unpublish', 'btn_class' => '', 'btn_name'=>'unpublish' );
			$state['PE'] = array( 'name' =>'FLEXI_PENDING', 'desc' =>'FLEXI_PENDING_SLIDER', 'btn_icon' => 'icon-clock', 'btn_class' => '', 'btn_name'=>'pending' );
			$state['OQ'] = array( 'name' =>'FLEXI_TO_WRITE', 'desc' =>'FLEXI_DRAFT_SLIDER', 'btn_icon' => 'icon-pencil', 'btn_class' => '', 'btn_name'=>'draft' );
		}
		if ($auth_archive)
		{
			$state['A'] = array( 'name' =>'FLEXI_ARCHIVE', 'desc' =>'', 'btn_icon' => 'icon-archive', 'btn_class' => '_btn-info', 'btn_name'=>'archived' );
		}
		if ($auth_delete)
		{
			$state['T'] = array( 'name' =>'FLEXI_TRASH', 'desc' =>'', 'btn_icon' => 'icon-trash', 'btn_class' => '_btn-inverse', 'btn_name'=>'trashed' );
		}

		$tip_class = 'hasTooltip';
		$btn_arr = array();
		foreach($state as $shortname => $statedata)
		{
			$btn_name = $statedata['btn_name'];
			$full_js="window.parent.fc_parent_form_submit('fc_modal_popup_container', 'adminForm', {'newstate':'" . $shortname . "', 'task':'items.changestate'}, {'task':'items.changestate', 'is_list':true});";
			$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
				$statedata['name'], $btn_name, $full_js,
				$msg_alert = JText::_('FLEXI_NO_ITEMS_SELECTED'), $msg_confirm = JText::_('FLEXI_ARE_YOU_SURE'),
				$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				$statedata['btn_class'] . ' btn-fcaction ' . $tip_class, $statedata['btn_icon'],
				'data-placement="right" title="' . flexicontent_html::encodeHTML(JText::_($statedata['desc']), 2) . '"', $auto_add = 0, $tag_type='button');
		}
		return $btn_arr;
	}
}
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

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;

$tip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
$btn_class = FLEXI_J30GE ? 'btn' : 'fc_button fcsimple';

$start_text = '<span class="label">'.JText::_('FLEXI_COLUMNS', true).'</span>';
$end_text = '<div class="icon-arrow-up-2" title="'.JText::_('FLEXI_HIDE').'" style="cursor: pointer;" onclick="fc_toggle_box_via_btn(\\\'mainChooseColBox\\\', document.getElementById(\\\'fc_mainChooseColBox_btn\\\'), \\\'btn-primary\\\');"></div>';
flexicontent_html::jscode_to_showhide_table('mainChooseColBox', 'adminListTableFCfields', $start_text, $end_text);

$edit_entry = JText::_('FLEXI_EDIT_FIELD', true);

$fcfilter_attrs_row  = ' class="input-prepend fc-xpended-row" ';

$user    = JFactory::getUser();
$cparams = JComponentHelper::getParams( 'com_flexicontent' );
$ctrl = FLEXI_J16GE ? 'fields.' : '';
$fields_task = FLEXI_J16GE ? 'task=fields.' : 'controller=fields&amp;task=';
//$field_model = JModel::getInstance('field', 'FlexicontentModel'); 

$flexi_yes = JText::_( 'FLEXI_YES' );
$flexi_no  = JText::_( 'FLEXI_NO' );
$flexi_nosupport = JText::_( 'FLEXI_PROPERTY_NOT_SUPPORTED', true );
$flexi_rebuild   = JText::_( 'FLEXI_REBUILD_SEARCH_INDEX', true );
$flexi_toggle    = JText::_( 'FLEXI_CLICK_TO_TOGGLE', true );

$ordering_draggable = $cparams->get('draggable_reordering', 1);
if ($this->ordering) {
	$image_ordering_tip = '<img src="components/com_flexicontent/assets/images/comments.png" class="fc-man-icon-s '.$tip_class.'" alt="Reordering" title="'.flexicontent_html::getToolTip('FLEXI_REORDERING', 'FLEXI_REORDERING_ENABLED_DESC', 1, 1).'" /> ';
	$drag_handle_box = '<div class="fc_drag_handle%s" title="'.JText::_('FLEXI_ORDER_SAVE_WHEN_DONE', true).'"></div>';
} else {
	$image_ordering_tip = '<img src="components/com_flexicontent/assets/images/comments.png" class="fc-man-icon-s '.$tip_class.'" alt="Reordering" title="'.flexicontent_html::getToolTip('FLEXI_REORDERING', 'FLEXI_REORDERING_DISABLED_DESC', 1, 1).'" /> ';
	$drag_handle_box = '<div class="fc_drag_handle%s" title="'.JText::_('FLEXI_ORDER_COLUMN_FIRST', true).'" ></div>';
	$image_saveorder    = '';
}

if ($this->filter_type == '' || $this->filter_type == 0) {
	$ord_col = 'ordering';
} else {
	$ord_col = 'typeordering';
}
$ord_grp = 1;
$list_total_cols = 13;


// Parse parameter and find fieldgroup
$f2g_map = array();
$grouped_fields = array();
$rows_byid = array();
foreach ($this->rows as $row)
{
	// Parse parameters, limited to some types, but maybe parse for all
	if ( in_array($row->field_type, array('groupmarker', 'coreprops', 'fieldgroup', 'select', 'selectmultiple', 'radio', 'radioimage', 'checkbox', 'checkboximage')) )
	{
		$row->parameters = new JRegistry($row->attribs);
	}
	$rows_byid[$row->id] = $row;
}
foreach($this->types as $type)
{
	$type->jname = JText::_($type->name);
}

// Iterate thtrough all fields and create information needed by field
$allrows_byid = array();
foreach ($this->allrows as $row)
{
	// Handle displaying information: FIELDGROUP feature
	if ($row->field_type=='fieldgroup') {
		$row->parameters = new JRegistry($row->attribs);
		$fid_arr = preg_split('/[\s]*,[\s]*/', $row->parameters->get('fields'));
		$grouped_fields[$row->id] = array();  // Add this in case it is empty (= has no fields in it)
		foreach($fid_arr as $_fid) $f2g_map[$_fid] = $row;
	}
	$allrows_byid[$row->id] = $row;  // used to display information for: depends-on-master field feature (and in future for more cases)
}
foreach ($this->allrows as $row)
{
	if (isset($f2g_map[$row->id])) {
		$grouping_field = $f2g_map[$row->id];
		$grouped_fields[ $grouping_field->id ][ $row->id ] = $row;    // used to display information for: FIELDGROUP feature (and in future for more cases)
		
		if ( isset($rows_byid[$row->id]) )
		{
			// field of group is included in current list add info to it
			$rows_byid[$row->id]->grouping_field = $grouping_field;
			if (empty($rows_byid[$row->id]->parameters))
			{
				$rows_byid[$row->id]->parameters = new JRegistry($rows_byid[$row->id]->attribs);
			}
		}
	}
}
$tools_cookies['fc-filters-box-disp'] = JFactory::getApplication()->input->cookie->get('fc-filters-box-disp', 0, 'int');
?>
<script type="text/javascript">

// delete active filter
function delFilter(name)
{
	//if(window.console) window.console.log('Clearing filter:'+name);
	var myForm = jQuery('#adminForm');
	var filter = jQuery('#'+name);
	if (filter.attr('type')=='checkbox')
		filter.checked = '';
	else
		filter.val('');
}

function delAllFilters() {
	delFilter('search'); delFilter('filter_type');  delFilter('filter_assigned');
	delFilter('filter_fieldtype'); delFilter('filter_state');  delFilter('filter_access');
	delFilter('filter_order'); delFilter('filter_order_Dir');
}

</script>



<div id="flexicontent" class="flexicontent">

<form action="index.php?option=<?php echo $this->option; ?>&amp;view=<?php echo $this->view; ?>" method="post" name="adminForm" id="adminForm">

<?php if (!empty( $this->sidebar)) : ?>
<div class="<?php echo FLEXI_J40GE ? 'row' : 'row-fluid'; ?>">
	<div id="j-sidebar-container" class="span2 col-md-2">
		<?php echo str_replace('type="button"', '', $this->sidebar); ?>
	</div>
	<div class="span10 col-md-10">
		<div id="j-main-container">
<?php else : ?>
<div class="<?php echo FLEXI_J40GE ? 'row' : 'row-fluid'; ?>">
	<div class="span12 col-md-12">
		<div id="j-main-container">
<?php endif;?>

	<div id="fc-filters-header">
		<span class="btn-group input-append fc-filter filter-search">
			<input type="text" name="search" id="search" placeholder="<?php echo JText::_( 'FLEXI_SEARCH' ); ?>" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="inputbox" />
			<button title="" data-original-title="<?php echo JText::_('FLEXI_SEARCH'); ?>" class="<?php echo $btn_class.' '.$tip_class; ?>" onclick="document.adminForm.limitstart.value=0; Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-search"></i>' : JText::_('FLEXI_GO'); ?></button>
			<button title="" data-original-title="<?php echo JText::_('FLEXI_RESET_FILTERS'); ?>" class="<?php echo $btn_class.' '.$tip_class; ?>" onclick="document.adminForm.limitstart.value=0; delAllFilters(); Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-remove"></i>' : JText::_('FLEXI_CLEAR'); ?></button>
		</span>
		
		<?php $_class = FLEXI_J30GE ? ' btn' : ' fc_button fcsimple fcsmall'; ?>
		<span class="btn-group fc-filter">
			<span id="fc_filters_box_btn" class="<?php echo $_class.($this->count_filters ? ' btn-primary' : ''); ?>" onclick="fc_toggle_box_via_btn('fc-filters-box', this, 'btn-primary', false, undefined, 1);"><?php echo JText::_( 'FLEXI_FILTERS' ) . ($this->count_filters  ? ' <sup>'.$this->count_filters.'</sup>' : ''); ?></span>
			<span id="fc_mainChooseColBox_btn" class="<?php echo $_class; ?>" onclick="fc_toggle_box_via_btn('mainChooseColBox', this, 'btn-primary');"><?php echo JText::_( 'FLEXI_COLUMNS' ); ?><sup id="columnchoose_totals"></sup></span>
			<span id="fc-toggle-types_btn" class="<?php echo $_class; ?> hasTooltip" title="<?php echo JText::_('FLEXI_ASSIGNED_TYPES'); ?>" onclick="jQuery(this).data('box_showing', !jQuery(this).data('box_showing')); jQuery(this).data('box_showing') ? jQuery('.fc_assignments_box.fc_types').show() : jQuery('.fc_assignments_box.fc_types').hide();" ><span class="icon-tree"></span></span>
			<span id="fc-mini-help_btn" class="<?php echo $_class; ?>" onclick="fc_toggle_box_via_btn('fc-mini-help', this, 'btn-primary');"><span class="icon-help"></span></span>
		</span>
		<input type="hidden" id="fc-filters-box-disp" name="fc-filters-box-disp" value="<?php echo $tools_cookies['fc-filters-box-disp']; ?>" />
		
		<span class="fc-filter nowrap_box">
			<span class="limit nowrap_box">
				<?php
				$pagination_footer = $this->pagination->getListFooter();
				if (strpos($pagination_footer, '"limit"') === false) echo $this->pagination->getLimitBox();
				?>
			</span>
			
			<span class="fc_item_total_data nowrap_box fc-mssg-inline fc-info fc-nobgimage">
				<?php echo @$this->resultsCounter ? $this->resultsCounter : $this->pagination->getResultsCounter(); // custom Results Counter ?>
			</span>
			
			<?php if (($getPagesCounter = $this->pagination->getPagesCounter())): ?>
			<span class="fc_pages_counter nowrap_box fc-mssg-inline fc-info fc-nobgimage">
				<?php echo $getPagesCounter; ?>
			</span>
			<?php endif; ?>
		</span>
	</div>
	
	
	<div id="fc-filters-box" <?php if (!$this->count_filters || !$tools_cookies['fc-filters-box-disp']) echo 'style="display:none;"'; ?> class="">
		<!--<span class="label"><?php echo JText::_( 'FLEXI_FILTERS' ); ?></span>-->

		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<?php echo $this->lists['filter_type']; ?>
			</div>
		</div>

		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<?php echo $this->lists['assigned']; ?>
			</div>
		</div>

		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<?php echo $this->lists['fftype']; ?>
			</div>
		</div>

		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<?php echo $this->lists['state']; ?>
			</div>
		</div>

		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<?php echo $this->lists['access']; ?>
			</div>
		</div>

		<div id="fc-filters-slide-btn" class="icon-arrow-up-2 btn" title="<?php echo JText::_('FLEXI_HIDE'); ?>" style="cursor: pointer;" onclick="fc_toggle_box_via_btn('fc-filters-box', document.getElementById('fc_filters_box_btn'), 'btn-primary');"></div>
	</div>
	
	<div id="mainChooseColBox" class="well well-small" style="display:none;"></div>
	<?php echo @$this->minihelp; ?>

	<?php if ($this->ordering): ?>
	<div id="fcorder_save_warn_box" class="fc-mssg-inline fc-nobgimage fc-info" style="padding: 4px 8px 4px 8px; margin: 4px 0; line-height: 28px;">
		<span class="icon-pin"></span> <?php echo JText::_('FLEXI_FCORDER_CLICK_TO_SAVE') .' '. flexicontent_html::gridOrderBtn($this->rows, 'filesave.png', $ctrl.'saveorder'); ?>
	</div>
	<?php endif; ?>

	<table id="adminListTableFCfields" class="adminlist fcmanlist">
	<thead>
		<tr>
			<?php /*
			<th><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			*/ ?>

			<th class="left nowrap">
				<?php
				echo $image_ordering_tip;
				echo !$this->filter_type
					? JHtml::_('grid.sort', 'FLEXI_GLOBAL_ORDER', 't.ordering', $this->lists['order_Dir'], $this->lists['order'])
					: JHtml::_('grid.sort', 'FLEXI_TYPE_ORDER', 'typeordering', $this->lists['order_Dir'], $this->lists['order']);

				if ($this->permission->CanOrderFields && $this->ordering):
					//echo str_replace('rel="tooltip"', '', JHtml::_('grid.order', $this->rows, 'filesave.png', $ctrl.'saveorder'));
				endif;
				?>
			</th>

			<th class="left">
				<input type="checkbox" name="checkall-toggle" id="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
				<label for="checkall-toggle" class="green single"></label>
			</th>

			<?php /*<th style="padding:0px;"><?php echo JHtml::_('grid.sort', 'FLEXI_FIELD_DESCRIPTION', 't.description', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>*/ ?>

			<th class="hideOnDemandClass title" colspan="2" style="text-align:left; padding-left:24px;"><?php echo JHtml::_('grid.sort', 'FLEXI_FIELD_LABEL', 't.label', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass title" style="text-align:left;"><?php echo JHtml::_('grid.sort', 'FLEXI_FIELD_NAME', 't.name', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass title" style="text-align:left;"><?php echo JHtml::_('grid.sort', 'FLEXI_FIELD_TYPE', 't.field_type', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass nowrap">
				<?php echo '<small class="badge">'.JText::_( 'Content Lists' ).'</small>'; ?><br/>
				<small>
					<?php echo JHtml::_('grid.sort', 'FLEXI_FIELD_CONTENT_LIST_TEXT_SEARCHABLE', 't.issearch', $this->lists['order_Dir'], $this->lists['order'] ); ?> /
					<?php echo JHtml::_('grid.sort', 'FLEXI_FIELD_CONTENT_LIST_FILTERABLE', 't.isfilter', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				</small>
				<span class="column_toggle_lbl" style="display:none;"><?php echo '<small class="badge">'.JText::_( 'Content Lists' ).'</small>'; ?></span>
			</th>
			<th class="hideOnDemandClass nowrap">
				<?php echo '<small class="badge">'.JText::_( 'Search view' ).'</small>'; ?><br/>
				<small>
					<?php echo JHtml::_('grid.sort', 'FLEXI_FIELD_ADVANCED_TEXT_SEARCHABLE', 't.isadvsearch', $this->lists['order_Dir'], $this->lists['order'] ); ?> /
					<?php echo JHtml::_('grid.sort', 'FLEXI_FIELD_ADVANCED_FILTERABLE', 't.isadvfilter', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				</small>
				<span class="column_toggle_lbl" style="display:none;"><?php echo '<small class="badge">'.JText::_( 'Search view' ).'</small>'; ?></span>
			</th>
			<th class="hideOnDemandClass left" colspan="2"><?php echo JHtml::_('grid.sort', 'FLEXI_ASSIGNED_TYPES', 'nrassigned', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass left"><?php echo JHtml::_('grid.sort', 'FLEXI_ACCESS', 't.access', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass center"><?php echo JHtml::_('grid.sort', 'FLEXI_PUBLISHED', 't.published', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass"><?php echo JHtml::_('grid.sort', 'FLEXI_ID', 't.id', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
		</tr>
	</thead>

	<tbody <?php echo $ordering_draggable && $this->permission->CanOrderFields && $this->ordering ? 'id="sortable_fcitems"' : ''; ?> >
		<?php
		$canCheckinRecords = $user->authorise('core.admin', 'com_checkin');
		$_desc_label = JText::_('FLEXI_FIELD_DESCRIPTION', true);
		
		$k = 0;
		$padcount = 0;
		
		$total_rows = count($this->rows);
		if (!count($this->rows)) echo '<tr class="collapsed_row"><td colspan="'.$list_total_cols.'"></td></tr>';  // Collapsed row to allow border styling to apply		$k = 0;
		foreach($this->rows as $i => $row)
		{
			$padspacer = '';
			$row_css = '';
			
			if ( $this->filter_type ) // Create coloring and padding for groupmarker fields if filtering by specific type is enabled
			{
				if ($row->field_type=='groupmarker') {
					if ( in_array ($row->parameters->get('marker_type'), array( 'tabset_start', 'tabset_end' ) ) ) {
						$row_css = 'color:black;';
					} else if ( in_array ($row->parameters->get('marker_type'), array( 'tab_open', 'fieldset_open' ) ) ) {
						$row_css = 'color:darkgreen;';
						for ($icnt=0; $icnt < $padcount; $icnt++) $padspacer .= "&nbsp;|_&nbsp;";
						$padcount++;
					} else if ( in_array ($row->parameters->get('marker_type'), array( 'tab_close', 'fieldset_close' ) ) ) {
						$row_css = 'color:darkred;';
						$padcount--;
						for ($icnt=0; $icnt < $padcount; $icnt++) $padspacer .= "&nbsp;|_&nbsp;";
					}
				} else {
					$row_css = '';
					for ($icnt=0; $icnt < $padcount; $icnt++) $padspacer .= "&nbsp;|_&nbsp;";
				}
			}
			
			$rights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'field', $row->id);
			$canEdit			= in_array('editfield', $rights);
			$canPublish		= in_array('publishfield', $rights);
			$canDelete		= in_array('deletefield', $rights);
			
			$link 		= 'index.php?option=com_flexicontent&amp;'.$fields_task.'edit&amp;view=field&amp;id='. $row->id;
			if ($row->id < 7) {  // First 6 core field are not unpublishable
				$published 	= JHtml::image( 'administrator/components/com_flexicontent/assets/images/tick_f2.png', JText::_ ( 'FLEXI_NOT_AVAILABLE' ), ' class="fc-man-icon-s" ' );
			} else if (!$canPublish && $row->published) {   // No privilige published
				$published 	= JHtml::image( 'administrator/components/com_flexicontent/assets/images/tick_f2.png', JText::_ ( 'FLEXI_NOT_AVAILABLE' ), ' class="fc-man-icon-s" ' );
			} else if (!$canPublish && !$row->published) {   // No privilige unpublished
				$published 	= JHtml::image( 'administrator/components/com_flexicontent/assets/images/publish_x_f2.png', JText::_ ( 'FLEXI_NOT_AVAILABLE' ), ' class="fc-man-icon-s" ' );
			} else {
				$published 	= JHtml::_('jgrid.published', $row->published, $i, $ctrl );
			}
			
			//check which properties are supported by current field
			$ft_support = FlexicontentFields::getPropertySupport($row->field_type, $row->iscore);
			
			$supportsearch    = $ft_support->supportsearch;
			$supportfilter    = $ft_support->supportfilter;
			$supportadvsearch = $ft_support->supportadvsearch;
			$supportadvfilter = $ft_support->supportadvfilter;
			
			if ($row->issearch==0 || $row->issearch==1 || !$supportsearch) {
				$search_dirty = 0;
				$issearch = ($row->issearch && $supportsearch) ? "magnifier2.png" : "publish_x".(!$supportsearch ? '_f2' : '').".png";
				$issearch_tip = ($row->issearch && $supportsearch) ? $flexi_yes.", ".$flexi_toggle : ($supportsearch ? $flexi_no.", ".$flexi_toggle : $flexi_nosupport);
			} else {
				$search_dirty = 1;
				$issearch = $row->issearch==-1 ? "disconnect.png" : "connect.png";
				$issearch_tip = ($row->issearch==2 ? $flexi_yes : $flexi_no) .", ".$flexi_toggle.", ". $flexi_rebuild;
			}
			
			$isfilter = ($row->isfilter && $supportfilter) ? "filter.png" : "publish_x".(!$supportfilter ? '_f2' : '').".png";	
			$isfilter_tip = ($row->isfilter && $supportfilter) ? $flexi_yes.", ".$flexi_toggle : ($supportsearch ? $flexi_no.", ".$flexi_toggle : $flexi_nosupport);
			
			if ($row->isadvsearch==0 || $row->isadvsearch==1 || !$supportadvsearch) {
				$advsearch_dirty = 0;
				$isadvsearch = ($row->isadvsearch && $supportadvsearch) ? "magnifier2.png" : "publish_x".(!$supportadvsearch ? '_f2' : '').".png";
				$isadvsearch_tip = ($row->isadvsearch && $supportadvsearch) ? $flexi_yes.", ".$flexi_toggle : ($supportadvsearch ? $flexi_no.", ".$flexi_toggle : $flexi_nosupport);
			} else {
				$advsearch_dirty = 1;
				$isadvsearch = $row->isadvsearch==-1 ? "disconnect.png" : "connect.png";
				$isadvsearch_tip = ($row->isadvsearch==2 ? $flexi_yes : $flexi_no) .", ".$flexi_toggle.", ". $flexi_rebuild;
			}
			
			if ($row->isadvfilter==0 || $row->isadvfilter==1 || !$supportadvfilter) {
				$advfilter_dirty = 0;
				$isadvfilter = ($row->isadvfilter && $supportadvfilter) ? "filter.png" : "publish_x".(!$supportadvfilter ? '_f2' : '').".png";
				$isadvfilter_tip = ($row->isadvfilter && $supportadvfilter) ? $flexi_yes : ($supportadvfilter ? $flexi_no : $flexi_nosupport);
			} else {
				$advfilter_dirty = 1;
				$isadvfilter = $row->isadvfilter==-1 ? "disconnect.png" : "connect.png";
				$isadvfilter_tip = ($row->isadvfilter==2 ? $flexi_yes : $flexi_no) .", ". $flexi_rebuild;
			}
			
			if ($canPublish) {
				$access = flexicontent_html::userlevel('access['.$row->id.']', $row->access, 'onchange="return listItemTask(\'cb'.$i.'\',\''.$ctrl.'access\')"');
			} else {
				$access = $this->escape($row->access_level);
			}

			$orphan_warning	= '
				<span class="'.$tip_class.'" title="'. flexicontent_html::getToolTip('FLEXI_WARNING', 'FLEXI_NO_TYPES_ASSIGNED', 1, 1) .'">
					'.JHtml::image ( 'administrator/components/com_flexicontent/assets/images/warning.png', JText::_ ( 'FLEXI_NO_TYPES_ASSIGNED' ), ' class="fc-man-icon-s" ' ).'
				</span>';
   		?>
		<tr class="<?php echo "row$k"; ?>" style="<?php echo $row_css; ?>">

			<?php /*
			<td>
				<div class="adminlist-table-row"></div>
				<?php echo $this->pagination->getRowOffset( $i ); ?>
			</td>
			*/ ?>

			<?php if ($this->permission->CanOrderFields) : ?>
			<td class="order left">
				<?php
					$show_orderUp   = $i > 0;
					$show_orderDown = $i < $total_rows-1;
				?>
				<?php if ($ordering_draggable) : ?>
					<?php
						if (!$this->ordering) echo sprintf($drag_handle_box,' fc_drag_handle_disabled');
						else if ($show_orderUp && $show_orderDown) echo sprintf($drag_handle_box,' fc_drag_handle_both');
						else if ($show_orderUp) echo sprintf($drag_handle_box,' fc_drag_handle_uponly');
						else if ($show_orderDown) echo sprintf($drag_handle_box,' fc_drag_handle_downonly');
						else echo sprintf($drag_handle_box,'_none');
					?>
				<?php else: ?>
					<span><?php echo $this->pagination->orderUpIcon( $i, true, $ctrl.'orderup', 'Move Up', $this->ordering ); ?></span>
					<span><?php echo $this->pagination->orderDownIcon( $i, $total_rows, true, $ctrl.'orderdown', 'Move Down', $this->ordering );?></span>
				<?php endif; ?>
				
				<?php $disabled = $this->ordering ?  '' : 'disabled="disabled"'; ?>
				<input class="fcitem_order_no" type="text" name="order[]" size="5" value="<?php echo $row->$ord_col; ?>" <?php echo $disabled; ?> style="text-align: center" />
				
				<input type="hidden" name="item_cb[]" value="<?php echo $row->id; ?>" />
				<input type="hidden" name="prev_order[]" value="<?php echo $row->$ord_col; ?>" />
				<input type="hidden" name="ord_grp[]" value="<?php echo $show_orderDown ? $ord_grp : $ord_grp++; ?>" />
			</td>
			<?php else : ?>
			<td>
				<?php
				if ($this->filter_type == '' || $this->filter_type == 0) {
					echo $row->ordering;
				} else {
					echo $row->typeordering;
				}
				?>
			</td>
			<?php endif; ?>

			<td>
				<?php echo JHtml::_('grid.id', $i, $row->id); ?>
				<label for="cb<?php echo $i; ?>" class="green single"></label>
			</td>

			<td style="padding:0px;">
				<?php
				$translated_label = JText::_($row->label);
				$original_label_text = ($translated_label != $row->label) ? '<br/><small>'.$row->label.'</small>' : '';
				$escaped_label = htmlspecialchars(JText::_($row->label), ENT_QUOTES, 'UTF-8');
				
				$field_desc = '';
				$field_desc_len = StringHelper::strlen($row->description);
				if ($field_desc_len > 50) {
					$field_desc = StringHelper::substr( htmlspecialchars($row->description, ENT_QUOTES, 'UTF-8'), 0 , 50).'...';
				} else if ($field_desc_len) {
					$field_desc = htmlspecialchars($row->description, ENT_QUOTES, 'UTF-8');
				}
				if ($field_desc) echo ' <img src="components/com_flexicontent/assets/images/comments.png" class="fc-man-icon-s '.$tip_class.'" alt="Note" title="'.flexicontent_html::getToolTip($_desc_label, $field_desc, 0, 0).'" />';
				?>
			</td>

			<td>
				<?php
				if (isset($row->grouping_field) && $row->parameters->get('use_ingroup'))
				{
					$_r = $row->grouping_field;
					$_link = 'index.php?option=com_flexicontent&amp;'.$fields_task.'edit&amp;view=field&amp;id='. $_r->id;
					echo '
					<a style="padding:2px;" href="'.$_link.'" title="'.$edit_entry.'">
						<img style="max-height:24px; padding:0px; margin:0px;" alt="Note" src="components/com_flexicontent/assets/images/insert_merge_field.png" title="Grouped inside: '.htmlspecialchars($_r->label, ENT_QUOTES, 'UTF-8').'" class="fc-man-icon-s '.$tip_class.'" />
					</a>';
				}
				
				echo $padspacer;
				
				// Display an icon with checkin link, if current user has checked out current item
				if ($row->checked_out) {
					// Record check-in is allowed if either (a) current user has Global Checkin privilege OR (b) record checked out by current user
					$canCheckin = $canCheckinRecords || $row->checked_out == $user->id;
					if ($canCheckin) {
						//echo JHtml::_('jgrid.checkedout', $i, $row->editor, $row->checked_out_time, 'types.', $canCheckin);
						$task_str = 'fields.checkin';
						if ($row->checked_out == $user->id) {
							$_tip_title = JText::sprintf('FLEXI_CLICK_TO_RELEASE_YOUR_LOCK_DESC', $row->editor, $row->checked_out_time);
						} else {
							echo '<input id="cb'.$i.'" type="checkbox" value="'.$row->id.'" name="cid[]" style="display:none!important;">';
							$_tip_title = JText::sprintf('FLEXI_CLICK_TO_RELEASE_FOREIGN_LOCK_DESC', $row->editor, $row->checked_out_time);
						}
						?>
						<a class="btn btn-micro <?php echo $tip_class; ?>" title="<?php echo $_tip_title; ?>" href="javascript:;" onclick="var ccb=document.getElementById('cb<?php echo $i;?>'); ccb.checked=1; ccb.form.task.value='<?php echo $task_str; ?>'; ccb.form.submit();">
							<span class="icon-checkedout"></span>
						</a>
						<?php
					} else {
						echo '<span class="fc-noauth">'.JText::sprintf('FLEXI_RECORD_CHECKED_OUT_DIFF_USER').'</span><br/>';
					}
				}
				
				// Display title with no edit link ... if row checked out by different user -OR- is uneditable
				if ( ( $row->checked_out && $row->checked_out != $user->id ) || ( !$canEdit ) ) {
					echo $translated_label;
					echo $original_label_text;
				
				// Display title with edit link ... (row editable and not checked out)
				} else {
				?>
					<a href="<?php echo $link; ?>" title="<?php echo $edit_entry; ?>">
						<?php echo htmlspecialchars(JText::_($row->label), ENT_QUOTES, 'UTF-8'); ?>
					</a>
					<?php echo $original_label_text;?>
				<?php
					// Handle displaying information: depends-on-master field
					if (!empty($row->parameters) && $row->parameters->get('cascade_after'))
					{
						$_r = $allrows_byid[ $row->parameters->get('cascade_after') ];
						$_link = 'index.php?option=com_flexicontent&amp;'.$fields_task.'edit&amp;view=field&amp;id='. $_r->id;
						echo '
						<a style="padding:2px;" href="'.$_link.'" title="'.$edit_entry.'">
							<img style="max-height:24px; padding:0px; margin:0px;" alt="Note" src="components/com_flexicontent/assets/images/relationships.png" title="'.JText::_('FLEXI_VALGRP_DEPENDS_ON_MASTER_FIELD').': '.htmlspecialchars($_r->label, ENT_QUOTES, 'UTF-8').'" class="fc-man-icon-s '.$tip_class.'" />
						</a>';
					}
				}
				?>
			</td>
			<td>
				<?php echo $row->name; ?>
			</td>
			<td>
				<?php
				switch ($row->field_type) {
				case 'fieldgroup':
					echo '<span class="badge" style="display:display: inline-block; margin: 0px 0px 1px; border-radius: 3px; width: 94%; padding: 2px 3%;">
					'.$row->type."</span><br/>";
					echo '<span class="alert alert-info" style="display: inline-block; margin: 0px 0px 1px; border-radius: 3px; width: 98%; padding: 4px 1%;">';
					$_lbls = array();
					foreach($grouped_fields[$row->id] as $_r)
					{
						$_link = 'index.php?option=com_flexicontent&amp;'.$fields_task.'edit&amp;view=field&amp;id='. $_r->id;
						$_lbls[] = '<a class="label" style="border-radius:3px; padding: 2px;" href="'.$_link.'" title="'.$edit_entry.'">'.htmlspecialchars(JText::_($_r->label), ENT_QUOTES, 'UTF-8').'</a>';
					}
					echo implode(' ', $_lbls);
					echo '</span>';
					break;
				case 'groupmarker':
					echo "<strong>".$row->type."</strong><br/>";
					echo "<small>-&nbsp;". $row->parameters->get('marker_type') ."&nbsp;-</small>";
					break;
				case 'coreprops':
					echo "<strong>".$row->type."</strong><br/>";
					echo "<small>-&nbsp;". $row->parameters->get('props_type') ."&nbsp;-</small>";
					break;
				default:
					echo "<strong>".$row->type."</strong><br/>";
					echo "<small>-&nbsp;". ($row->iscore? "[Core]" : $row->friendly) ."&nbsp;-</small>";
				}
				
				?>
			</td>
			
			<td>
				<?php if($supportsearch) :?>
					<?php if ($canEdit) :?>
					<a title="Toggle property" onclick="document.adminForm.propname.value='issearch'; return listItemTask('cb<?php echo $i;?>','toggleprop')" href="javascript:void(0);">
					<?php endif; ?>
					<img src="components/com_flexicontent/assets/images/<?php echo $issearch;?>" width="16" height="16" style="border-width:0;" class="fc-man-icon-s" title="<?php echo $issearch_tip;?>" alt="<?php echo $issearch_tip;?>" />
					<?php if ($canEdit) :?>
					</a>
					<?php endif; ?>
				<?php else: ?>
					<span style="display:inline-block; width:16px; height:16px;"></span>
				<?php endif; ?> /
				
				<?php if($supportfilter) :?>
					<?php if ($canEdit) :?>
					<a title="Toggle property" onclick="document.adminForm.propname.value='isfilter'; return listItemTask('cb<?php echo $i;?>','toggleprop')" href="javascript:void(0);">
					<?php endif; ?>
					<img src="components/com_flexicontent/assets/images/<?php echo $isfilter;?>" width="16" height="16" style="border-width:0;" class="fc-man-icon-s" title="<?php echo $isfilter_tip;?>" alt="<?php echo $isfilter_tip;?>" />
					<?php if ($canEdit) :?>
					</a>
					<?php endif; ?>
				<?php else: ?>
					<span style="display:inline-block; width:16px; height:16px;"></span>
				<?php endif; ?>
			</td>
			
			<td>
				<?php if($supportadvsearch) :?>
					<?php if ($canEdit) :?>
					<a title="Toggle property" onclick="document.adminForm.propname.value='isadvsearch'; return listItemTask('cb<?php echo $i;?>','toggleprop')" href="javascript:void(0);">
					<?php endif; ?>
					<img src="components/com_flexicontent/assets/images/<?php echo $isadvsearch;?>" width="16" height="16" style="border-width:0;" class="fc-man-icon-s" title="<?php echo $isadvsearch_tip;?>" alt="<?php echo $isadvsearch_tip;?>" />
					<?php if ($canEdit) :?>
					</a>
					<?php endif; ?>
				<?php else: ?>
					<span style="display:inline-block; width:16px; height:16px;"></span>
				<?php endif; ?> /
				
				<?php if($supportadvfilter) :?>
					<?php if ($canEdit) :?>
					<a title="Toggle property" onclick="document.adminForm.propname.value='isadvfilter'; return listItemTask('cb<?php echo $i;?>','toggleprop')" href="javascript:void(0);">
					<?php endif; ?>
					<img src="components/com_flexicontent/assets/images/<?php echo $isadvfilter;?>" width="16" height="16" style="border-width:0;" class="fc-man-icon-s" title="<?php echo $isadvfilter_tip;?>" alt="<?php echo $isadvfilter_tip;?>" />
					<?php if ($canEdit) :?>
					</a>
					<?php endif; ?>
				<?php else: ?>
					<span style="display:inline-block; width:16px; height:16px;"></span>
				<?php endif; ?>
			</td>
			<td>
				<?php echo $row->nrassigned ? '<span class="badge badge-info hasTooltip">'.$row->nrassigned.'</span>' : $orphan_warning; ?>
			</td>
			<td>
				<?php
				if (!count($row->content_types))
				{
					echo '<span class="badge badge-warning">'.JText::_('FLEXI_NONE').'</span>';
				}
				else
				{
					$row_types  = array();
					$type_names = array();
					foreach($row->content_types as $type_id)
					{
						$row_types[] = '
						<span class="itemtype">
							'.$this->types[$type_id]->jname.'
						</span>';
						$type_names[] = $this->types[$type_id]->jname;
					}
					echo count($row_types) > 3 ? '
						<span class="btn btn-mini hasTooltip nowrap_box" onclick="jQuery(this).next().toggle();" title="'.flexicontent_html::getToolTip(JText::_('FLEXI_ASSIGNED_TYPES'), '<ul class="fc_plain"><li>'.implode('</li><li>', $type_names).'</li></ul>', 0, 1).'">
							'.count($row_types).' <i class="icon-tree"></i>
						</span>
						<div class="fc_assignments_box fc_types">' : '';
					echo count($row_types) > 8
						? implode(', ', $row_types)
						: (count($row_types) ? '<ul class="fc_plain"><li>' . implode('</li><li>', $row_types) . '</li></ul>' : '');
					echo count($row_types) > 3 ? '</div>' : '';
				}
				?>
			</td>
			<td>
				<?php echo $access; ?>
			</td>
			<td class="center">
				<?php echo $published; ?>
			</td>
			<td><?php echo $row->id; ?></td>
		</tr>
		<?php $k = 1 - $k; } ?>
	</tbody>

	<tfoot>
		<tr>
			<td colspan="<?php echo $list_total_cols; ?>" style="text-align: left;">
				<?php echo $pagination_footer; ?>
			</td>
		</tr>
	</tfoot>

	</table>
	
	<div class="fcclear"></div>
	
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="fields" />
	<input type="hidden" name="view" value="fields" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="propname" value="" />
	<input type="hidden" id="filter_order" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" id="filter_order_Dir" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<input type="hidden" name="fcform" value="1" />
	<?php echo JHtml::_( 'form.token' ); ?>
	
		<!-- fc_perf -->
		</div>  <!-- j-main-container -->
	</div>  <!-- spanNN -->
</div>  <!-- row -->
</form>
</div><!-- #flexicontent end -->
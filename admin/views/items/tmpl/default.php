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


// Create custom field columns
foreach($this->extra_fields as $_field):
	$values = null;
	$field = $_field;
	FlexicontentFields::renderField($this->rows, $field->name, $values, $field->methodname);
endforeach;

$tip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
$btn_class = FLEXI_J30GE ? 'btn' : 'fc_button fcsimple';
$ico_class = 'btn btn-micro'; //'fc-man-icon-s';

$featimg = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/star.png', JText::_( 'FLEXI_FEATURED' ), ' style="text-align:left" class="fc-man-icon-s" title="'.JText::_( 'FLEXI_FEATURED' ).'"' );

$start_text = '<span class="label">'.JText::_('FLEXI_COLUMNS', true).'</span>  &nbsp; ';
$end_text = '<div class="icon-arrow-up-2" title="'.JText::_('FLEXI_HIDE').'" style="cursor: pointer;" onclick="fc_toggle_box_via_btn(\\\'mainChooseColBox\\\', document.getElementById(\\\'fc_mainChooseColBox_btn\\\'), \\\'btn-primary\\\');"></div>';
flexicontent_html::jscode_to_showhide_table('mainChooseColBox', 'adminListTableFCitems', $start_text, $end_text);

global $globalcats;
$cparams = JComponentHelper::getParams( 'com_flexicontent' );
$limit = $this->pagination->limit;
$ctrl  = 'items.';
$items_task = 'task=items.';
$cats_task  = 'task=category.';

$db 			= JFactory::getDBO();
$config		= JFactory::getConfig();
$nullDate	= $db->getNullDate();
$user 		= JFactory::getUser();

//$_sh404sef = JPluginHelper::isEnabled('system', 'sh404sef') && $config->get('sef');
$_sh404sef = defined('SH404SEF_IS_RUNNING') && $config->get('sef');
$isAdmin = JFactory::getApplication()->isAdmin();
$useAssocs = flexicontent_db::useAssociations();
$autologin = '';//$cparams->get('autoflogin', 1) ? '&amp;fcu='.$user->username . '&amp;fcp='.$user->password : '';

$list_total_cols = 18;
if ( $useAssocs ) $list_total_cols++;

$list_total_cols += count($this->extra_fields);

$image_flag_path = "../media/mod_languages/images/";
$attribs_preview = ' class="fc-man-icon-s '.$ico_class.' '.$tip_class.'" title="'.flexicontent_html::getToolTip( 'FLEXI_PREVIEW', 'FLEXI_DISPLAY_ENTRY_IN_FRONTEND_DESC', 1, 1).'" ';
$image_preview = JHTML::image( 'components/com_flexicontent/assets/images/'.'monitor_go.png', JText::_('FLEXI_PREVIEW'), $attribs_preview);

$attribs_editlayout = ' class="'.$ico_class.' '.$tip_class.'" title="'.flexicontent_html::getToolTip( 'FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS', null, 1, 1).'" ';
$image_editlayout = JHTML::image( 'components/com_flexicontent/assets/images/'.'layout_edit.png', JText::_('FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS'),  $attribs_editlayout);

$ordering_draggable = $cparams->get('draggable_reordering', 1);
if ($this->ordering) {
	$image_ordering_tip = '<img src="components/com_flexicontent/assets/images/comments.png" class="fc-man-icon-s '.$tip_class.'" alt="Reordering" title="'.flexicontent_html::getToolTip('FLEXI_REORDERING', 'FLEXI_REORDERING_ENABLED_DESC', 1, 1).'" /> ';
	//$image_ordering_tip = '<span class="icon-info '.$tip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_REORDERING', 'FLEXI_REORDERING_ENABLED_DESC', 1, 1).'"></span>';
	$drag_handle_box = '<div class="fc_drag_handle%s" title="'.JText::_('FLEXI_ORDER_SAVE_WHEN_DONE', true).'"></div>';
} else {
	$image_ordering_tip = '<img src="components/com_flexicontent/assets/images/comments.png" class="fc-man-icon-s '.$tip_class.'" alt="Reordering" title="'.flexicontent_html::getToolTip('FLEXI_REORDERING', 'FLEXI_REORDERING_DISABLED_DESC', 1, 1).'" /> ';
	//$image_ordering_tip = '<span class="icon-info '.$tip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_REORDERING', 'FLEXI_REORDERING_DISABLED_DESC', 1, 1).'"></span>';
	$drag_handle_box = '<div class="fc_drag_handle%s" title="'.JText::_('FLEXI_ORDER_COLUMN_FIRST', true).'" ></div>';
	$image_saveorder    = '';
}
$_img_title = JText::_('MAIN category shown in bold', true);
$categories_tip  = '<img src="components/com_flexicontent/assets/images/information.png" class="fc-man-icon-s '.$tip_class.'" alt="'.$_img_title.'" title="'.flexicontent_html::getToolTip(null, $_img_title, 0, 1).'" />';

$_img_title = JText::_('FLEXI_LIST_ITEMS_IN_CATS', true);
$_img_title_desc = JText::_('FLEXI_LIST_ITEMS_IN_CATS_DESC', true);
$_tooltip = ' class="fc-man-icon-s '.$tip_class.'" title="'.flexicontent_html::getToolTip(null, $_img_title_desc, 0, 1).'" ';
$catsinstate_tip = '<img src="components/com_flexicontent/assets/images/comment.png" alt="'.$_img_title_desc.'" '.$_tooltip.' />';

if ( !$this->filter_order_type ) {
	$_img_title = JText::_('FLEXI_ORDER_JOOMLA');
	$_img_title_desc = JText::sprintf('FLEXI_CURRENT_ORDER_IS',JText::_('FLEXI_ORDER_JOOMLA')).' '.JText::_('FLEXI_ITEM_ORDER_EXPLANATION_TIP');
	$ord_catid = 'catid';
	$ord_col = 'ordering';
} else {
	$_img_title = JText::_('FLEXI_ORDER_FLEXICONTENT', true);
	$_img_title_desc = JText::sprintf('FLEXI_CURRENT_ORDER_IS',JText::_('FLEXI_ORDER_FLEXICONTENT')).' '.JText::_('FLEXI_ITEM_ORDER_EXPLANATION_TIP');
	$ord_catid = 'rel_catid';
	$ord_col = 'catsordering';
}
$ordering_type_tip  = '<img src="components/com_flexicontent/assets/images/comment.png" data-placement="bottom" class="fc-man-icon-s '.$tip_class.'" alt="'.$_img_title.'" title="'.flexicontent_html::getToolTip($_img_title, $_img_title_desc, 0, 1).'" />';
$ord_grp = 1;

$stategrps = array(1=>'published', 0=>'unpublished', -2=>'trashed', -3=>'unpublished', -4=>'unpublished', -5=>'published');


// Dates displayed in the item form, are in user timezone for J2.5, and in site's default timezone for J1.5
$site_zone = JFactory::getApplication()->getCfg('offset');
$user_zone = JFactory::getUser()->getParam('timezone', $site_zone);
$tz = new DateTimeZone( $user_zone );
$tz_offset = $tz->getOffset(new JDate()) / 3600;
$tz_info =  $tz_offset > 0 ? ' UTC +' . $tz_offset : ' UTC ' . $tz_offset;
$tz_info .= ' ('.$user_zone.')';
$date_note_msg   = JText::sprintf( FLEXI_J16GE ? 'FLEXI_DATES_IN_USER_TIMEZONE_NOTE' : 'FLEXI_DATES_IN_SITE_TIMEZONE_NOTE', ' ', $tz_info );
$date_note_attrs = ' class="fc-man-icon-s '.$tip_class.'" title="'.flexicontent_html::getToolTip(null, $date_note_msg, 0, 1).'" ';
$date_zone_tip   = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/comment.png', JText::_( 'FLEXI_NOTES' ), $date_note_attrs );

// COMMON repeated texts
$edit_item_title = JText::_('FLEXI_EDIT_ITEM', true);
$edit_cat_title = JText::_('FLEXI_EDIT_CATEGORY', true);
$edit_layout = JText::_('FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS', true);
$rem_filt_txt = JText::_('FLEXI_REMOVE_FILTER', true);
$rem_filt_tip = ' class="'.$tip_class.' filterdel" title="'.flexicontent_html::getToolTip('FLEXI_ACTIVE_FILTER', 'FLEXI_CLICK_TO_REMOVE_THIS_FILTER', 1, 1).'" ';
$scheduled_for_publication = JText::_( 'FLEXI_SCHEDULED_FOR_PUBLICATION', true );
$publication_expired = JText::_( 'FLEXI_PUBLICATION_EXPIRED', true );
?>
<script type="text/javascript">

function fetchcounter(el_id, task_name)
{
	var url = "index.php?option=com_flexicontent&amp;<?php echo $items_task; ?>"+task_name+"&amp;tmpl=component&amp;format=raw";
	if(MooTools.version>="1.2.4") {
		new Request.HTML({
			url: url,
			method: 'get',
			update: $(el_id),
			onSuccess:function(responseTree, responseElements, responseHTML, responseJavaScript) {
				if(responseHTML==0) {
					if(confirm("<?php echo JText::_( 'FLEXI_ITEMS_REFRESH_CONFIRM',true ); ?>"))
						location.href = 'index.php?option=com_flexicontent&view=items';
				}
				return responseHTML;
			}
		}).send();
	}else{
		var ajax = new Ajax(url, {
			method: 'get',
			update: $(el_id),
			onComplete:function(v) {
				if(v==0) {
					if(confirm("<?php echo JText::_( 'FLEXI_ITEMS_REFRESH_CONFIRM',true ); ?>"))
						location.href = 'index.php?option=com_flexicontent&view=items';
				}
				return v;
			}
		});
		ajax.request();
	}
}

// the function overloads joomla standard event
function submitform(pressbutton)
{
	form = document.adminForm;
	// If formvalidator activated
	/*if( pressbutton == 'remove' ) {
		var answer = confirm('<?php echo JText::_( 'FLEXI_ITEMS_DELETE_CONFIRM',true ); ?>')
		if (!answer){
			new Event(e).stop();
			return;
		}
	}*/

	// Store the button task into the form
	if (pressbutton) {
		form.task.value=pressbutton;
	}

	// Execute onsubmit
	if (typeof form.onsubmit == "function") {
		form.onsubmit();
	}
	// Submit the form
	form.submit();
}

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
	jQuery('.fc_field_filter').val('');
	delFilter('search'); delFilter('filter_type'); delFilter('filter_state');
	delFilter('filter_cats'); delFilter('filter_author'); delFilter('filter_id');
	delFilter('startdate'); delFilter('enddate'); delFilter('filter_lang');
	delFilter('filter_tag'); delFilter('filter_access');
	delFilter('filter_fileid');
	delFilter('filter_order'); delFilter('filter_order_Dir');
	jQuery('#filter_subcats').val('1');  // default: include subcats
	jQuery('#filter_catsinstate').val('1');	  // default: published categories
}

<?php if ($this->ordering) : ?>
var move_within_ordering_groups_limits = <?php echo '"'.JText::_('FLEXI_MOVE_WITHIN_ORDERING_GROUPS_LIMITS',true).'"'; ?>
<?php endif; ?>

</script>

<script type="text/javascript">

<?php if ($this->unassociated && !$this->badcatitems) : ?>
	var unassociated_items = <?php echo $this->unassociated; ?>;
	function bindItems() {
		jQuery('#log-bind').html('<img src="components/com_flexicontent/assets/images/ajax-loader.gif">');
		jQuery('#orphan_items_mssg').html("<?php echo JText::_( 'FLEXI_ITEMS_TO_BIND', true ); ?>");
		
		//$('bindForm').submb
		//this.form.action += '&typeid='+this.form.elements['typeid'].options[this.form.elements['typeid'].selectedIndex].value;
		//this.form.action += '&bind_limit='+this.form.elements['bind_limit'].options[this.form.elements['bind_limit'].selectedIndex].value;		
		
    var postData = jQuery('#bindForm').serializeArray();
    var formURL = jQuery('#bindForm').attr("action");
    jQuery.ajax(
		{
			url : formURL,
			type: "POST",
			data : postData,
			success:function(data, textStatus, jqXHR)
			{
				jQuery('#log-bind').html(data);
				bind_limit = jQuery('#bind_limit').val();
				unassociated_items = unassociated_items - bind_limit;
				if (unassociated_items > 0) {
					jQuery('#orphan_items_count').html(unassociated_items);
					bindItems();
				} else {
					jQuery('#orphan_items_count').html('0');
					if(confirm("<?php echo JText::_( 'FLEXI_ITEMS_REFRESH_CONFIRM',true ); ?>")) {
						location.href = 'index.php?option=com_flexicontent&view=items';
					}
				}
				//if (fetchcounter('orphan_items_count', 'getOrphansItems', jQuery('#bind_limit').val()) != 0) bindItems();
			},
			error: function(jqXHR, textStatus, errorThrown)
			{
			    //if fails     
			}
		});
	}
<?php endif; ?>

<?php if ($this->badcatitems) : ?>
jQuery(document).ready(function(){
	$('fixcatForm').addEvent('submit', function(e) {
		if ( !$('fixcatForm').elements['default_cat'].options[$('fixcatForm').elements['default_cat'].selectedIndex].value ) {
			alert('Please select a category');
			return false;
		}
		$('fixcatForm').action += '&default_cat='+$('fixcatForm').elements['default_cat'].options[$('fixcatForm').elements['default_cat'].selectedIndex].value;
		
		if(MooTools.version>="1.2.4") {
			$('log-fixcat').set('html', '<p class="centerimg"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" style="text-align:center;"></p>');
			e = e.stop();
		}else{
			$('log-fixcat').setHTML('<p class="centerimg"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" style="text-align:center;"></p>');
			e = new Event(e).stop();
		}
		if(MooTools.version>="1.2.4") {
			new Request.HTML({
				url: this.action,
				method: 'post',
				update: $('log-fixcat'),
				onComplete: function() {
					fetchcounter('badcat_items_count', 'getBadCatItems');
				}
			}).send();
		}else{
			this.send({
				update: $('log-fixcat'),
				onComplete: function() {
					fetchcounter('badcat_items_count', 'getBadCatItems');
				}
			});
		}
	});
});
<?php endif; ?>

</script>
<div id="flexicontent" class="flexicontent">



<?php if (!empty( $this->sidebar)) : ?>
	<div id="j-sidebar-container" class="span2">
		<?php echo str_replace('type="button"', '', $this->sidebar); ?>
	</div>
	<div id="j-main-container" class="span10">
<?php else : ?>
	<div id="j-main-container">
<?php endif;?>


	<?php if ($this->unassociated && !$this->badcatitems) : ?>
		<div class="fc-mssg fc-success" style="margin-bottom: 32px;">
			
			<?php echo JText::_( 'FLEXI_UNASSOCIATED_WARNING' ); ?>

			<br/><br/>
			<span id="log-bind"></span>
			<span class="badge" style="border-radius: 3px;" id="orphan_items_count"><?php echo $this->unassociated; ?></span>
			<span id="orphan_items_mssg"><?php echo JText::_( 'FLEXI_ITEMS' ); ?></span>

			<form action="index.php?option=com_flexicontent&amp;<?php echo $items_task; ?>bindextdata&amp;tmpl=component&amp;format=raw" method="post" name="bindForm" id="bindForm" style="display: inline-block;">

				<input id="button-bind" type="button"
					class="<?php echo $btn_class; ?> btn-primary" style='float:none !important; box-sizing: border-box; min-width: 200px;'
					value="<?php echo JText::_( 'FLEXI_BIND' ); ?>" onclick="jQuery(this.form).hide(); bindItems();"
				/>

				<?php
					echo '
						<span class="badge" style="border-radius: 3px;">'.JText::_( 'FLEXI_TO' ) . '</span> ' .
						flexicontent_html::buildtypesselect($_types = $this->get( 'Typeslist' ), 'typeid', $_typesselected='', false, ' class="use_select2_lib" ', 'typeid') . '
						
						<div style="display: '.($this->unassociated > 1000 ? 'inline-block;' : 'none;').'">
							<span class="label">'.JText::_( 'with step ' ) . '</span>' . $this->lists['bind_limits'] .'
						</div>';
				?>

			</form>
		</div>
	<?php endif; ?>


	<?php if ($this->badcatitems) : ?>
	<form action="index.php?option=com_flexicontent&amp;<?php echo $items_task; ?>fixmaincat&amp;tmpl=component&amp;format=raw" method="post" name="fixcatForm" id="fixcatForm">
		<div class="fc-mssg fc-warning">
		<table>
			<tr>
				<td>
				<span style="font-size:115%;">
				<?php echo JText::_( 'Item with invalid or missing main category' ); ?>
				</span>
				</td>
				<td width="35%">
					<span style="font-size:150%;"><span id="badcat_items_count"><?php echo $this->badcatitems; ?></span></span>&nbsp;
					<br/>
					<?php echo JText::_( 'FLEXI_DEFAULT_CAT_FOR_NO_CAT_ITEMS' ).': '.$this->lists['default_cat']; ?>
					<input id="button-fixcat" type="submit" class="<?php echo $btn_class; ?>" style='float:none !important;' value="<?php echo JText::_( 'FLEXI_FIX' ); ?>"
					onclick="" />
					<div id="log-fixcat"></div>
				</td>
			</tr>
		</table>
		</div>
	</form>
	<?php endif; ?>


	<form action="index.php?option=<?php echo $this->option; ?>&amp;view=<?php echo $this->view; ?>" method="post" name="adminForm" id="adminForm" style="<?php echo ($this->unassociated && !count($this->rows) ? 'display: none;' : ''); ?>">
	
	<div id="fc-filters-header">
		<span class="fc-filter nowrap_box" style="margin:0;">
			<?php echo $this->lists['scope']; ?>
		</span>
		<span class="btn-group input-append filter-search fc-filter">
			<input type="text" name="search" id="search" placeholder="<?php echo JText::_( 'FLEXI_SEARCH' ); ?>" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="inputbox" />
			<button title="" data-original-title="<?php echo JText::_('FLEXI_SEARCH'); ?>" class="<?php echo $btn_class.' '.$tip_class; ?>" onclick="document.adminForm.limitstart.value=0; Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-search"></i>' : JText::_('FLEXI_GO'); ?></button>
			<button title="" data-original-title="<?php echo JText::_('FLEXI_RESET_FILTERS'); ?>" class="<?php echo $btn_class.' '.$tip_class; ?>" onclick="document.adminForm.limitstart.value=0; delAllFilters(); Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-remove"></i>' : JText::_('FLEXI_CLEAR'); ?></button>
		</span>
		
		<?php $_class = FLEXI_J30GE ? ' btn' : ' fc_button fcsimple fcsmall'; ?>
		<span class="btn-group input-append fc-filter">
			<input type="button" id="fc_filters_box_btn" class="<?php echo $_class.($this->count_filters ? ' btn-primary' : ''); ?>" onclick="fc_toggle_box_via_btn('fc-filters-box', this, 'btn-primary');" value="<?php echo JText::_( 'FLEXI_FILTERS' ); ?>" />
			<input type="button" id="fc_mainChooseColBox_btn" class="<?php echo $_class; ?>" onclick="fc_toggle_box_via_btn('mainChooseColBox', this, 'btn-primary');" value="<?php echo JText::_( 'FLEXI_COLUMNS' ); ?>" />
			<span id="fc-mini-help_btn" class="<?php echo $_class; ?>" onclick="fc_toggle_box_via_btn('fc-mini-help', this, 'btn-primary');" ><span class="icon-help"></span></span>
		</span>
		
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
	
	
	<div id="fc-filters-box" <?php if (!$this->count_filters) echo 'style="display:none;"'; ?> class="">
		<!--<span class="label"><?php echo JText::_( 'FLEXI_FILTERS' ); ?></span>-->
		
		<?php if (@$this->lists['filter_fileid']): ?>
		<span class="fc-filter nowrap_box">
			<?php echo $this->lists['filter_fileid']; ?>
		</span>
		<?php endif; ?>
		
		<span class="fc-filter nowrap_box">
			<?php echo $this->lists['filter_author']; ?>
		</span>
		
		<span class="fc-filter nowrap_box">
			<?php echo $this->lists['filter_tag']; ?>
		</span>
		
		<span class="fc-filter nowrap_box">
			<?php echo $this->lists['filter_lang']; ?>
		</span>
		
		<span class="fc-filter nowrap_box">
			<?php echo $this->lists['filter_type']; ?>
		</span>
		
		<span class="fc-filter nowrap_box">
			<?php echo $this->lists['filter_state']; ?>
		</span>
		
		<span class="fc-filter nowrap_box">
			<?php echo $this->lists['filter_access']; ?>
		</span>
		
		<span class="fc-filter nowrap_box">
			<?php echo $catsinstate_tip; ?>
			<?php echo $this->lists['filter_catsinstate']; ?>
		</span>
		
		<span class="fc-filter nowrap_box">
			<?php echo $this->lists['filter_cats']; ?>
		</span>
		
		<span class="fc-filter nowrap_box">
			<?php echo $this->lists['filter_subcats']; ?>
		</span>
		
		<div class="fc-filter nowrap_box">
			<?php echo $date_zone_tip; ?>
			<?php echo $this->lists['date']; ?>
			<?php echo $this->lists['startdate']; ?>
			<?php echo $this->lists['enddate']; ?>
		</div>
		
		<span class="fc-filter nowrap_box">
			<label class="label"><?php echo JText::_('FLEXI_ID'); ?></label>
			<input type="text" name="filter_id" id="filter_id" size="6" value="<?php echo $this->lists['filter_id']; ?>" class="inputbox" style="width:auto;" />
		</span>
		
		<span class="fc-filter nowrap_box">
			<?php echo $ordering_type_tip; ?>
			<label class="label"><?php echo JText::_('FLEXI_ORDER_TYPE'); ?></label>
			<?php echo $this->lists['filter_order_type']; ?>
		</span>
		
		<?php foreach($this->custom_filts as $filt) : ?>
		<span class="fc-filter nowrap_box">
			<?php echo $filt->html; ?>
		</span>
		<?php endforeach; ?>
		
		<div class="icon-arrow-up-2" title="<?php echo JText::_('FLEXI_HIDE'); ?>" style="cursor: pointer;" onclick="fc_toggle_box_via_btn('fc-filters-box', document.getElementById('fc_filters_box_btn'), 'btn-primary');"></div>
	</div>
	
	<div id="mainChooseColBox" class="well well-small" style="display:none;"></div>
	<?php echo @$this->minihelp; ?>

	<div class="fcclear"></div>
	<div id="fcorder_save_warn_box" class="fc-mssg fc-note" style="padding: 4px 8px 4px 36px; line-height: 32px; display: none;">
		<?php echo JText::_('FLEXI_FCORDER_CLICK_TO_SAVE') .' '. ($this->ordering ? flexicontent_html::gridOrderBtn($this->rows, 'filesave.png', $ctrl.'saveorder') : '') ; ?>
	</div>
	
	<?php
	$order_msg = '';
	
	if (!$this->filter_order_type && ($this->filter_order=='i.ordering' || $this->filter_order=='catsordering')):
		$order_msg .= JText::_('Joomla order, GROUPING BY main category') .'. ';
	elseif ($this->filter_order_type && !$this->filter_cats && ($this->filter_order=='i.ordering' || $this->filter_order=='catsordering')):
		$order_msg .= JText::_('Grouping by first listed category') .'. ';
	endif;
	
	if ($this->filter_order_type && !$this->filter_cats && ($this->filter_order=='i.ordering' || $this->filter_order=='catsordering')):
		$order_msg .= JText::_('FLEXI_FCORDER_USE_CATEGORY_FILTER');
	endif;
	?>
	
	<?php if ($order_msg): ?>
	<div id="fcorder_notes_box" class="fc-mssg fc-success" style="padding: 4px 8px 4px 36px; line-height: 32px;">
		<?php echo $order_msg;?>
	</div>
	<?php endif; ?>
	
	<div class="fcclear"></div>
	
	<table id="adminListTableFCitems" class="adminlist fcmanlist" itemscope itemtype="http://schema.org/WebPage">
	<thead>
		<tr>
			
			<th class="left">
				<?php echo JText::_( 'FLEXI_NUM' ); ?>
			</th>
			
			<th class="left">
				<input type="checkbox" name="toggle" value="" onclick="<?php echo FLEXI_J30GE ? 'Joomla.checkAll(this);' : 'checkAll('.count( $this->rows).');'; ?>" />
			</th>

			<th class="left nowrap hideOnDemandClass" style="padding-right: 0px;">
				<?php
				if (!$this->filter_order_type) {
					echo $this->CanOrder ? $image_ordering_tip : '';
					echo str_replace('_FLEXI_ORDER_</a>', '<span class="icon-menu-2"></span></a>', JHTML::_('grid.sort', '_FLEXI_ORDER_', 'i.ordering', $this->lists['order_Dir'], $this->lists['order'] ));
				} else {
					echo $this->CanOrder ? $image_ordering_tip : '';
					echo str_replace('_FLEXI_ORDER_', JText::_('FLEXI_ORDER', true), str_replace('_FLEXI_ORDER_</a>', '<span class="icon-menu-2"></span></a>', JHTML::_('grid.sort', '_FLEXI_ORDER_', 'catsordering', $this->lists['order_Dir'], $this->lists['order'] )));
				}

				/*if ($this->CanOrder && $this->ordering) :
					echo flexicontent_html::gridOrderBtn($this->rows, 'filesave.png', $ctrl.'saveorder');
				endif;*/
				?>
				<span class="column_toggle_lbl" style="display:none;"><?php echo JText::_( 'FLEXI_ORDER' ); ?></span>
			</th>

			<th class="left"></th>
			
			<th class="left hideOnDemandClass">
				<?php echo JHTML::_('grid.sort', 'FLEXI_TITLE', 'i.title', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->search) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('search');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			
			<th class="left hideOnDemandClass">
				<?php echo JHTML::_('grid.sort', 'FLEXI_AUTHOR', 'i.created_by', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->filter_author) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('filter_author');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			
			<th class="left hideOnDemandClass">
				<?php echo JHTML::_('grid.sort', 'FLEXI_LANGUAGE', 'i.language', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->filter_lang) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('filter_lang');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>

		<?php if ( $useAssocs ) : ?>
			<th class="left hideOnDemandClass">
				<?php echo JText::_('FLEXI_ASSOCIATIONS'); /*JHTML::_('grid.sort', 'Translation Group', 'i.lang_parent_id', $this->lists['order_Dir'], $this->lists['order'] );*/ ?>
			</th>
		<?php endif; ?>
			
			<th class="left hideOnDemandClass">
				<?php echo JHTML::_('grid.sort', 'FLEXI_TYPE_NAME', 'type_name', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->filter_type) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('filter_type');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			
			<th class="left hideOnDemandClass">
				<?php echo JText::_( 'FLEXI_STATE', true ); ?>
				<?php if ($this->filter_state) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('filter_state');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			
			<th class="left hideOnDemandClass" colspan="2">
				<?php echo JText::_( 'FLEXI_TEMPLATE' ); ?>
			</th>
		
		<?php foreach($this->extra_fields as $field) :?>
			<th class="left hideOnDemandClass">
				<?php echo $field->label; ?>
			</th>
		<?php endforeach; ?>

			<th class="left hideOnDemandClass">
				<?php echo JHTML::_('grid.sort', 'FLEXI_ACCESS', 'i.access', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->filter_access) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('filter_access');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			
			<th class="left hideOnDemandClass">
				<?php echo $categories_tip; ?>
				<?php echo JText::_( 'FLEXI_CATEGORIES' ); ?>
				<?php if ($this->filter_cats) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('filter_cats');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			
			<th class="left hideOnDemandClass">
				<?php echo JText::_( 'FLEXI_TAGS' ); ?>
				<?php if ($this->filter_tag) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('filter_tag');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			
			<th class="left hideOnDemandClass">
				<?php echo JHTML::_('grid.sort',   'FLEXI_CREATED', 'i.created', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php
				if ($this->date == '1') :
					if (($this->startdate && ($this->startdate != JText::_('FLEXI_FROM'))) || ($this->enddate && ($this->startdate != JText::_('FLEXI_TO')))) :
				?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('startdate');delFilter('enddate');document.adminForm.submit();" />
				</span>
				<?php
					endif;
				endif;
				?>
			</th>
			
			<th class="left hideOnDemandClass">
				<?php echo JHTML::_('grid.sort',   'FLEXI_REVISED', 'i.modified', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php
				if ($this->date == '2') :
					if (($this->startdate && ($this->startdate != JText::_('FLEXI_FROM'))) || ($this->enddate && ($this->startdate != JText::_('FLEXI_TO')))) :
				?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('startdate');delFilter('enddate');document.adminForm.submit();" />
				</span>
				<?php
					endif;
				endif;
				?>
			</th>
			
			<th class="left hideOnDemandClass">
				<?php echo JHTML::_('grid.sort', 'FLEXI_HITS', 'i.hits', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>
			
			<th class="left hideOnDemandClass">
				<?php echo JHTML::_('grid.sort', 'FLEXI_ID', 'i.id', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->filter_id) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('filter_id');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			
		</tr>
	</thead>

	<tfoot>
		<tr>
			<td colspan="<?php echo $list_total_cols; ?>" style="text-align: left;">
				<?php echo $pagination_footer; ?>
			</td>
		</tr>
		
		<tr>
			<td colspan="<?php echo $list_total_cols; ?>" style="margin: 0 auto !important; background-color: white;">
				<table class="admintable" style="margin: 0 auto !important; background-color: white;">
					<tr>
						<td><img src="../components/com_flexicontent/assets/images/tick.png" width="16" height="16" style="border: 0;" alt="<?php echo JText::_( 'FLEXI_PUBLISHED', true ); ?>" /></td>
						<td><?php echo JText::_( 'FLEXI_PUBLISHED_DESC' ); ?> <u><?php echo JText::_( 'FLEXI_PUBLISHED' ); ?></u></td>
						<td><img src="../components/com_flexicontent/assets/images/publish_g.png" width="16" height="16" style="border: 0;" alt="<?php echo JText::_( 'FLEXI_IN_PROGRESS', true ); ?>" /></td>
						<td colspan="3"><?php echo JText::_( 'FLEXI_NOT_FINISHED_YET' ); ?> <u><?php echo JText::_( 'FLEXI_PUBLISHED' ); ?></u></td>
					</tr><tr>
						<td><img src="../components/com_flexicontent/assets/images/publish_x.png" width="16" height="16" style="border: 0;" alt="<?php echo JText::_( 'FLEXI_UNPUBLISHED', true ); ?>" /></td>
						<td><?php echo JText::_( 'FLEXI_UNPUBLISHED_DESC' ); ?></td>
						<td><img src="../components/com_flexicontent/assets/images/publish_r.png" width="16" height="16" style="border: 0;" alt="<?php echo JText::_( 'FLEXI_PENDING', true ); ?>" /></td>
						<td><?php echo JText::_( 'FLEXI_NEED_TO_BE_APPROVED' ); ?> <u><?php echo JText::_( 'FLEXI_UNPUBLISHED_DESC' ); ?></u></td>
						<td><img src="../components/com_flexicontent/assets/images/publish_y.png" width="16" height="16" style="border: 0;" alt="<?php echo JText::_( 'FLEXI_TO_WRITE', true ); ?>" /></td>
						<td><?php echo JText::_( 'FLEXI_TO_WRITE_DESC' ); ?> <u><?php echo JText::_( 'FLEXI_UNPUBLISHED_DESC' ); ?></u></td>
					</tr><tr>
						<td><img src="../components/com_flexicontent/assets/images/archive.png" width="16" height="16" style="border: 0;" alt="<?php echo JText::_( 'FLEXI_ARCHIVED', true ); ?>" /></td>
						<td><?php echo JText::_( 'FLEXI_ARCHIVED_STATE' ); ?> <u><?php echo JText::_( 'FLEXI_UNPUBLISHED_DESC' ); ?></u></td>
						<td><img src="../components/com_flexicontent/assets/images/trash.png" width="16" height="16" style="border: 0;" alt="<?php echo JText::_( 'FLEXI_TRASHED', true ); ?>" /></td>
						<td colspan="3"><?php echo JText::_( 'FLEXI_TRASHED_STATE' ); ?> <u><?php echo JText::_( 'FLEXI_UNPUBLISHED_DESC' ); ?></u></td>
					</tr>
				</table>
			</td>
		</tr>
		
	</tfoot>

	<tbody <?php echo $ordering_draggable && $this->CanOrder && $this->ordering ? 'id="sortable_fcitems"' : ''; ?> >
		<?php
		$canCheckinRecords = $user->authorise('core.admin', 'checkin');
		
		$k = 0;
		$date_format = (($date_format = JText::_( 'FLEXI_DATE_FORMAT_FLEXI_ITEMS_J16GE' )) == 'FLEXI_DATE_FORMAT_FLEXI_ITEMS_J16GE') ? "d/m/y H:i" : $date_format;
		
		$unpublishableFound = false;
		if (!count($this->rows)) echo '<tr class="collapsed_row"><td colspan="'.$list_total_cols.'"></td></tr>';  // Collapsed row to allow border styling to apply
		for ($i=0, $n=count($this->rows); $i < $n; $i++)
		{
			$row = & $this->rows[$i];
			
			$rights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $row->id);
			
			$canEdit 			 = in_array('edit', $rights);
			$canEditOwn		 = in_array('edit.own', $rights) && $row->created_by == $user->id;
			$canPublish 	 = in_array('edit.state', $rights);
			$canPublishOwn = in_array('edit.state.own', $rights) && $row->created_by == $user->id;
			$canPublishCurrent = $canPublish || $canPublishOwn;
			$unpublishableFound = $unpublishableFound || !$canPublishCurrent;
			
			$publish_up   = JFactory::getDate($row->publish_up);
			$publish_down = JFactory::getDate($row->publish_down);
			$publish_up->setTimezone($tz);
			$publish_down->setTimezone($tz);
			
			$link = 'index.php?option=com_flexicontent&amp;'.$items_task.'edit&amp;view=item&amp;id='. $row->id;
			
			if (($canEdit || $canEditOwn) && $this->CanAccLvl) {
				$access = flexicontent_html::userlevel('access['.$row->id.']', $row->access, 'onchange="return listItemTask(\'cb'.$i.'\',\'items.access\')"');
			} else {
				$access = $this->escape($row->access_level);
			}

			$cid_checkbox = @ JHTML::_('grid.checkedout', $row, $i );

			// Check publication START/FINISH dates (publication Scheduled / Expired)
			$is_published = in_array( $row->state, array(1, -5, (FLEXI_J16GE ? 2:-1) ) );
			$extra_img = $extra_alt = '';

			if ( $row->publication_scheduled && $is_published ) {
				$extra_img = 'pushished_scheduled.png';
				$extra_alt = & $scheduled_for_publication;
			}
			if ( $row->publication_expired && $is_published ) {
				$extra_img = 'pushished_expired.png';
				$extra_alt = & $publication_expired;
			}
			
			$row_ilayout =  $row->config->get('ilayout') ?  $row->config->get('ilayout') : $row->tconfig->get('ilayout');
			$layout_url = 'index.php?option=com_flexicontent&amp;view=template&amp;type=items&amp;tmpl=component&amp;ismodal=1&amp;folder='. $row_ilayout;
			
			// Set a row language, even if empty to avoid errors
			$lang_default = !FLEXI_J16GE ? '' : '*';
			$row->lang = @$row->lang ? $row->lang : $lang_default;
   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td class="sort_handle"><?php echo $this->pagination->getRowOffset( $i ); ?></td>
			<td><?php echo $cid_checkbox; ?></td>

		<?php if ($this->CanOrder) : ?>
			<td class="order">
				<?php
					$row_stategrp_prev = @ $stategrps[@$this->rows[$i-1]->state];
					$row_stategrp = @ $stategrps[$this->rows[$i]->state];
					$row_stategrp_next = @ $stategrps[@$this->rows[$i+1]->state];

					$show_orderUp   = @$this->rows[$i-1]->$ord_catid == $this->rows[$i]->$ord_catid && $row_stategrp_prev == $row_stategrp;
					$show_orderDown = $this->rows[$i]->$ord_catid == @$this->rows[$i+1]->$ord_catid && $row_stategrp == $row_stategrp_next;
					if (
						($this->filter_order_type && (FLEXI_FISH || FLEXI_J16GE)) ||   // FLEXIcontent order supports language in J1.5 too
						(!$this->filter_order_type && FLEXI_J16GE)   // Joomla order does not support language in J1.5
					) {
						$show_orderUp   = $show_orderUp   && @$this->rows[$i-1]->lang == $this->rows[$i]->lang;
						$show_orderDown = $show_orderDown && $this->rows[$i]->lang == @$this->rows[$i+1]->lang;
					}
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
					<span><?php echo $this->pagination->orderUpIcon( $i, $show_orderUp, $ctrl.'orderup', 'Move Up', $this->ordering ); ?></span>
					<span><?php echo $this->pagination->orderDownIcon( $i, $n, $show_orderDown, $ctrl.'orderdown', 'Move Down', $this->ordering );?></span>
				<?php endif; ?>

				<?php /*$disabled = $this->ordering ?  '' : 'disabled="disabled"';*/ ?>
				<?php if ($this->ordering): $disabled = ''; ?>
				<input class="fcitem_order_no" type="text" name="order[]" size="5" value="<?php echo $row->$ord_col; ?>" <?php echo $disabled; ?> style="text-align: center" />

				<input type="hidden" name="item_cb[]" style="display:none;" value="<?php echo $row->id; ?>" />
				<input type="hidden" name="ord_catid[]" style="display:none;" value="<?php echo $row->$ord_catid; ?>" />
				<input type="hidden" name="prev_order[]" style="display:none;" value="<?php echo $row->$ord_col; ?>" />
				<input type="hidden" name="ord_grp[]" style="display:none;" value="<?php echo $show_orderDown ? $ord_grp : $ord_grp++; ?>" />
				<?php endif; ?>
			</td>
		<?php else : ?>
			<td>
				<?php echo !$this->filter_order_type  ?  $row->ordering  :  $row->catsordering; ?>
			</td>
		<?php endif; ?>

			<td>
				<?php
				$item_url = str_replace('&', '&amp;',
					FlexicontentHelperRoute::getItemRoute($row->id.':'.$row->alias, $row->categoryslug, 0, $row).
					($row->language!='*' ? '&lang='.substr($row->language, 0,2) : '')
				);
				$item_url = JRoute::_(JURI::root().$item_url, $xhtml=false); // xhtml to false we do it manually above (at least the ampersand) also it has no effect because we prepended the root URL ?
				$previewlink = $item_url .'&amp;preview=1' .$autologin;
				echo '<a class="preview" href="'.$previewlink.'" target="_blank">'.$image_preview.'</a>';
				?>
			</td>

			<td class="col_title">
				<?php

				// Display an icon with checkin link, if current user has checked out current item
				if ($row->checked_out) {
					// Record check-in is allowed if either (a) current user has Global Checkin privilege OR (b) record checked out by current user
					$canCheckin = $canCheckinRecords || $row->checked_out == $user->id;
					if ($canCheckin) {
						//if (FLEXI_J16GE && $row->checked_out == $user->id) echo JHtml::_('jgrid.checkedout', $i, $row->editor, $row->checked_out_time, 'items.', $canCheckin);
						$task_str = 'items.checkin';
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
				if ( ( $row->checked_out && $row->checked_out != $user->id ) || ( !$canEdit && !$canEditOwn ) ) {
					echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8');

				// Display title with edit link ... (row editable and not checked out)
				} else {
					if ( $useAssocs ) {
						if ($this->lists['order']=='i.lang_parent_id' && $row->lang_parent_id && $row->id!=$row->lang_parent_id) echo "<sup>|</sup>--";
					}
					echo '<a href="'.$link.'" title="'.$edit_item_title.'">'.htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8').'</a>';
				}
				?>

			</td>
			<td class="col_authors">
				<?php echo $row->author; ?>
			</td>
			
			<td class="col_lang" title="<?php echo ($row->lang=='*' ? JText::_("All") : $this->langs->{$row->lang}->name); ?>">
				<?php if ( 0 && !empty($row->lang) && !empty($this->langs->{$row->lang}->imgsrc) ) : ?>
					<img src="<?php echo $this->langs->{$row->lang}->imgsrc; ?>" alt="<?php echo $row->lang; ?>" />
				<?php elseif( !empty($row->lang) ) : ?>
					<?php echo $row->lang=='*' ? JText::_("FLEXI_ALL") : $row->lang;?>
				<?php endif; ?>
			</td>


		<?php if ( $useAssocs ) : ?>
			<td>
				<?php
					/*if ($this->lists['order']=='i.lang_parent_id') {
						if ($row->id==$row->lang_parent_id) echo "Main";
						else echo "+";
					}*/// else echo "unsorted<sup>[3]</sup>";

				if ( !empty($this->lang_assocs[$row->id]) )
				{
					$row_modified = strtotime($row->modified);
					if (!$row_modified)  $row_modified = strtotime($row->created);
					
					foreach($this->lang_assocs[$row->id] as $assoc_item) {
						if ($assoc_item->id==$row->id) continue;

						$assoc_modified = strtotime($assoc_item->modified);
						if (!$assoc_modified)  $assoc_modified = strtotime($assoc_item->created);
						$_class = ( $assoc_modified < $row_modified ) ? ' fc_assoc_outdated' : '';
						
						$_link  = 'index.php?option=com_flexicontent&amp;'.$items_task.'edit&amp;cid='. $assoc_item->id;
						$_title = flexicontent_html::getToolTip(
							JText::_( $assoc_modified < $row_modified ? 'FLEXI_OUTDATED' : 'FLEXI_UPTODATE'),
							//JText::_( 'FLEXI_EDIT_ASSOC_TRANSLATION').
							($assoc_item->lang=='*' ? JText::_("All") : $this->langs->{$assoc_item->lang}->name).' <br/><br/> '.
							$assoc_item->title, 0, 1
						);
						
						echo '<a class="fc_assoc_translation '.$tip_class.$_class.'" target="_blank" href="'.$_link.'" title="'.$_title.'" >';
						//echo $assoc_item->id;
						if ( !empty($assoc_item->lang) && !empty($this->langs->{$assoc_item->lang}->imgsrc) ) {
							echo ' <img src="'.$this->langs->{$assoc_item->lang}->imgsrc.'" alt="'.$assoc_item->lang.'" />';
						} else if( !empty($assoc_item->lang) ) {
							echo $assoc_item->lang=='*' ? JText::_("FLEXI_ALL") : $assoc_item->lang;
						}
						echo "</a>";
					}
				}
				?>
			</td>
		<?php endif ; ?>


			<td class="col_type">
				<?php echo $row->type_name; ?>
			</td>
			<td class="col_state">
				<?php echo flexicontent_html::statebutton( $row, $row->params, $addToggler = ($limit <= $this->inline_ss_max) ); ?>
				<?php if ($extra_img) : ?>
					<img src="components/com_flexicontent/assets/images/<?php echo $extra_img;?>" width="16" height="16" style="border: 0;" class="<?php echo $tip_class; ?>" alt="<?php echo $extra_alt; ?>" title="<?php echo $extra_alt; ?>" />
				<?php endif; ?>
				<?php echo $row->featured ? $featimg : ''; ?>
			</td>
			
			<td class="col_edit_layout">
				<?php if ($this->CanTemplates && $row_ilayout) : ?>
				<a href="<?php echo $layout_url; ?>" title="<?php echo $edit_layout; ?>" onclick="var url = jQuery(this).attr('href'); fc_showDialog(url, 'fc_modal_popup_container', 0, 0, 0, 0, {title:'<?php echo $edit_layout; ?>'}); return false;" >
					<?php echo $image_editlayout;?>
				</a>
				<?php endif; ?>
			</td>
			<td class="col_template">
				<?php echo $row_ilayout.($row->config->get('ilayout') ? '' : '<sup>[1]</sup>') ?>
			</td>

    <?php foreach($this->extra_fields as $field) :?>

			<td>
		    <?php
				// Output the field's display HTML
				echo isset( $row->fields[$field->name]->{$field->methodname} ) ? $row->fields[$field->name]->{$field->methodname} : '';
		    ?>
			</td>
		<?php endforeach; ?>

			<td class="col_access">
				<?php echo $access; ?>
			</td>
			
			<td class="col_cats">
				<?php
				// Reorder categories place item's MAIN category first or ...
				// place first the category being filtered (if order is 'FLEXIcontent')
				$catids = array();
				$nn = 1;
				foreach ($row->catids as $key => $_icat)
				{
					if ( !isset($this->itemCats[$_icat]) ) continue;
					$category = & $this->itemCats[$_icat];
					
					// Place first category of category filter
					if ($this->filter_order=='catsordering' && (int)$this->filter_cats) {
						$isFilterCat = ((int)$category->id == (int)$this->filter_cats);
						if ($isFilterCat) $catids[0] = $_icat;
						else $catids[$nn++] = $_icat;
					}
					
					// Place first the main category of the item, in ALL cases except if doing per category FLEXIcontent ordering
					else if ($this->filter_order!='catsordering') {
						$isMainCat = ((int)$category->id == (int)$row->catid);
						if ($isMainCat) $catids[0] = $_icat;
						else $catids[$nn++] = $_icat;
					}
					else {  // $this->filter_order=='catsordering' AND filter_cats is empty, ordering by first found category, DONOT reoder the display
						$catids[$nn-1] = $_icat;
						$nn++;
					}
				}
				$row->catids = $catids;
				
				$nr = count($row->catids);
				$ix = 0;
				$nn = 0;
				for ($nn=0; $nn < $nr; $nn++) :
					$_item_cat = $row->catids[$nn];
					if ( !isset($this->itemCats[$_item_cat]) ) continue;
					$category = & $this->itemCats[$_item_cat];
					
					$isMainCat = ((int)$category->id == (int)$row->catid);
					//if (!$this->filter_order_type && !$isMainCat) continue;
					
					$typeofcats = $isMainCat ? 'maincat' : 'secondarycat';
					if ( $ix==0 && ($this->filter_order=='catsordering' || $this->filter_order=='i.ordering') )
						$typeofcats .= ' orderingcat';
					
					$catlink	= 'index.php?option=com_flexicontent&amp;'.$cats_task.'edit&amp;cid='. $category->id;
					$title = htmlspecialchars($category->title, ENT_QUOTES, 'UTF-8');
					if ($this->CanCats) :
				?>
					<span class="<?php echo $typeofcats; ?>" title="<?php echo $edit_cat_title; ?>">
					<a href="<?php echo $catlink; ?>">
						<?php
						if (StringHelper::strlen($title) > 40) {
							echo StringHelper::substr( $title , 0 , 40).'...';
						} else {
							echo $title;
						}
						?></a></span>
					<?php
					else :
						if (StringHelper::strlen($title) > 40) {
							echo ($category->id != $row->catid) ? '' : '<strong>';
							echo StringHelper::substr( $title , 0 , 40).'...';
							echo ($category->id != $row->catid) ? '' : '</strong>';
						} else {
							echo ($category->id != $row->catid) ? '' : '<strong>';
							echo $title;
							echo ($category->id != $row->catid) ? '' : '</strong>';
						}
					endif;
					$ix++;
					if ($ix != $nr) :
						echo ', ';
					endif;
				endfor;
				?>
			</td>
			
			<td class="col_tag">
				<?php
					foreach ($row->tagids as $key => $_itag)
					{
						if ( !isset($this->itemTags[$_itag]) ) continue;
						$tag = & $this->itemTags[$_itag];
						echo '<span class="badge">'.$tag->name.'</span> ';
					}
				?>
			</td>
			
			<td class="col_created">
				<?php echo JHTML::_('date',  $row->created, $date_format ); ?>
			</td>
			
			<td class="col_revised">
				<?php echo ($row->modified != $this->db->getNullDate()) ? JHTML::_('date', $row->modified, $date_format) : JText::_('FLEXI_NEVER'); ?>
			</td>
			
			<td>
				<?php echo $row->hits; ?>
			</td>
			
			<td class="col_id">
				<?php echo $row->id; ?>
			</td>
			
		</tr>
		<?php
			$k = 1 - $k;
		}
		if ( $unpublishableFound ) {
			$ctrl_task = 'items.approval';
			JToolBarHelper::spacer();
			JToolBarHelper::divider();
			JToolBarHelper::spacer();
			FLEXI_J16GE ?
				JToolBarHelper::custom( $ctrl_task, 'apply.png', 'apply.png', 'FLEXI_APPROVAL_REQUEST' ) :
				JToolBarHelper::custom( $ctrl_task, 'person2.png', 'person2_f2.png', 'FLEXI_APPROVAL_REQUEST' );
		}
		JToolBarHelper::spacer();
		JToolBarHelper::spacer();
		?>
	</tbody>

	</table>

	<div class="fcclear"></div>

	<sup>[1]</sup> <?php echo JText::_('FLEXI_TMPL_NOT_SET_USING_TYPE_DEFAULT'); ?><br />
	<sup>[2]</sup> <?php echo JText::sprintf('FLEXI_INLINE_ITEM_STATE_SELECTOR_DISABLED', $this->inline_ss_max); ?><br />
	<?php if ( $useAssocs )	:?>
	<sup>[3]</sup> <?php echo JText::_('FLEXI_SORT_TO_GROUP_TRANSLATION'); ?><br />
	<?php endif;?>
	<sup>[4]</sup> <?php echo JText::_('FLEXI_MULTIPLE_ITEM_ORDERINGS'); ?><br />
	
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="items" />
	<input type="hidden" name="view" value="items" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="newstate" id="newstate" value="" />
	<input type="hidden" id="filter_order" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" id="filter_order_Dir" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<input type="hidden" name="fcform" value="1" />
	<?php echo JHTML::_( 'form.token' ); ?>

	</form>
	<!-- fc_perf -->
	</div>  <!-- sidebar -->
</div><!-- #flexicontent end -->
<?php
/**
 * @version 1.5 stable $Id: default.php 1907 2014-05-27 12:35:36Z ggppdk $
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

$header_text = '<span class="label">'.JText::_('FLEXI_COLUMNS', true).'</span>  &nbsp; '
.'<a onclick="alert(this.title);" title="'.str_replace("<br/>","\\n", JText::_('FLEXI_CAN_ADD_CUSTOM_FIELD_COLUMNS_COMPONENT_AND_PER_TYPE', true)).'" style="vertical-align:middle; font-size:12px; cursor:pointer;" href="javascript:;" >'
.'<img src="components/com_flexicontent/assets/images/plus-button.png" /><sup>[5]</sup>'
.'</a> &nbsp; '
;
flexicontent_html::jscode_to_showhide_table('mainChooseColBox', 'adminListTableFCitems', $header_text);

global $globalcats;
$cparams = JComponentHelper::getParams( 'com_flexicontent' );
$limit = $this->pagination->limit;
$ctrl  = FLEXI_J16GE ? 'items.' : '';
$items_task = FLEXI_J16GE ? 'task=items.' : 'controller=items&amp;task=';
$cats_task  = FLEXI_J16GE ? 'task=category.' : 'controller=categories&amp;task=';

$db 			= JFactory::getDBO();
$config		= JFactory::getConfig();
$nullDate	= $db->getNullDate();
$user 		= JFactory::getUser();

$enable_translation_groups = $cparams->get("enable_translation_groups") && ( FLEXI_J16GE || FLEXI_FISH ) ;
$autologin = '';//$cparams->get('autoflogin', 1) ? '&amp;fcu='.$user->username . '&amp;fcp='.$user->password : '';

$items_list_cols = 15;
if ( FLEXI_J16GE || FLEXI_FISH ) {
	$items_list_cols++;
	if ( $enable_translation_groups ) $items_list_cols++;
}

$items_list_cols += count($this->extra_fields);

$image_flag_path = !FLEXI_J16GE ? "../components/com_joomfish/images/flags/" : "../media/mod_languages/images/";
$_img_title = JText::_('FLEXI_PREVIEW', true);
$image_zoom = '<img src="components/com_flexicontent/assets/images/monitor_go.png" width="16" height="16" border="0" class="hasTip" alt="'.$_img_title.'" title="'.$_img_title.':: Click to display the frontend view of this item in a new browser window" />';

$ordering_draggable = $cparams->get('draggable_reordering', 1);
if ($this->ordering) {
	$_img_title = JText::_('FLEXI_REORDERING_ENABLED_TIP', true);
	$image_ordering_tip = '<img src="components/com_flexicontent/assets/images/information.png" class="hasTip" alt="'.$_img_title.'" title="'.$_img_title.'" />' .' ';
	$drag_handle_box = '<div class="fc_drag_handle%s" title="'.JText::_('FLEXI_ORDER_SAVE_WHEN_DONE', true).'"></div>';
} else {
	$_img_title = JText::_('FLEXI_REORDERING_DISABLED_TIP', true);
	$image_ordering_tip = '<img src="components/com_flexicontent/assets/images/information.png" class="hasTip" alt="'.$_img_title.'" title="'.$_img_title.'" />' .' ';
	$drag_handle_box = '<div class="fc_drag_handle %s" title="'.JText::_('FLEXI_ORDER_COLUMN_FIRST', true).'" ></div>';
	$image_saveorder    = '';
}
$_img_title = JText::_('MAIN category shown in bold', true);
$categories_tip  = '<img src="components/com_flexicontent/assets/images/information.png" class="hasTip" alt="'.$_img_title.'" title="'.'::'.$_img_title.'" />';

if ( !$this->filter_order_type ) {
	$_img_title = JText::_('FLEXI_ORDER_JOOMLA', true);
	$_img_title_desc = JText::sprintf('FLEXI_CURRENT_ORDER_IS',JText::_('FLEXI_ORDER_JOOMLA', true)).' '.JText::_('FLEXI_ITEM_ORDER_EXPLANATION_TIP', true);
	$ordering_type_tip  = '<img align="left" src="components/com_flexicontent/assets/images/comment.png" class="hasTip" alt="'.$_img_title.'" title="'.$_img_title.'::'.$_img_title_desc.'" />';
	$ord_catid = 'catid';
	$ord_col = 'ordering';
} else {
	$_img_title = JText::_('FLEXI_ORDER_FLEXICONTENT', true);
	$_img_title_desc = JText::sprintf('FLEXI_CURRENT_ORDER_IS',JText::_('FLEXI_ORDER_FLEXICONTENT', true)).' '.JText::_('FLEXI_ITEM_ORDER_EXPLANATION_TIP', true);
	$ordering_type_tip  = '<img align="left" src="components/com_flexicontent/assets/images/comment.png" class="hasTip" alt="'.$_img_title.'" title="'.$_img_title.'::'.$_img_title_desc.'" />';
	$ord_catid = 'rel_catid';
	$ord_col = 'catsordering';
}
$ord_grp = 1;

$stategrps = array(1=>'published', 0=>'unpublished', -2=>'trashed', -3=>'unpublished', -4=>'unpublished', -5=>'published');


// Dates displayed in the item form, are in user timezone for J2.5, and in site's default timezone for J1.5
$site_zone = JFactory::getApplication()->getCfg('offset');
$user_zone = JFactory::getUser()->getParam('timezone', $site_zone);
if (FLEXI_J16GE) {
	$tz = new DateTimeZone( $user_zone );
	$tz_offset = $tz->getOffset(new JDate()) / 3600;
} else {
	$tz_offset = $site_zone;
}
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
		var answer = confirm('<?php echo addslashes(JText::_( 'FLEXI_ITEMS_DELETE_CONFIRM__',true )); ?>')
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
	var myForm = $('adminForm');
	if ($(name).type=='checkbox')
		$(name).checked = '';
	else
		$(name).setProperty('value', '');
}

function delAllFilters() {
	delFilter('search'); delFilter('filter_type'); delFilter('filter_state');
	delFilter('filter_cats'); delFilter('filter_authors'); delFilter('filter_id');
	delFilter('startdate'); delFilter('enddate');
	<?php echo (FLEXI_FISH || FLEXI_J16GE) ? "delFilter('filter_lang');" : ""; ?>
}

<?php if ($this->ordering) : ?>
var move_within_ordering_groups_limits = <?php echo '"'.JText::_('FLEXI_MOVE_WITHIN_ORDERING_GROUPS_LIMITS',true).'"'; ?>
<?php endif; ?>

window.addEvent('domready', function(){

	var startdate	= $('startdate');
	var enddate 	= $('enddate');
	if(MooTools.version>="1.2.4") {
		var sdate = startdate.value;
		var edate = enddate.value;
	}else{
		var sdate = startdate.getValue();
		var edate = enddate.getValue();
	}
	if (sdate == '') {
		startdate.setProperty('value', '<?php echo JText::_( 'FLEXI_FROM',true ); ?>');
	}
	if (edate == '') {
		enddate.setProperty('value', '<?php echo JText::_( 'FLEXI_TO',true ); ?>');
	}
	$('startdate').addEvent('focus', function() {
		if (sdate == '<?php echo JText::_( 'FLEXI_FROM',true ); ?>') {
			startdate.setProperty('value', '');
		}
	});
	$('enddate').addEvent('focus', function() {
		if (edate == '<?php echo JText::_( 'FLEXI_TO',true ); ?>') {
			enddate.setProperty('value', '');
		}
	});
	$('startdate').addEvent('blur', function() {
		if (sdate == '') {
			startdate.setProperty('value', '<?php echo JText::_( 'FLEXI_FROM',true ); ?>');
		}
	});
	$('enddate').addEvent('blur', function() {
		if (edate == '') {
			enddate.setProperty('value', '<?php echo JText::_( 'FLEXI_TO',true ); ?>');
		}
	});

<?php /*
	$('show_filters').setStyle('display', 'none');
	$('hide_filters').addEvent('click', function() {
		$('filterline').setStyle('display', 'none');
		$('show_filters').setStyle('display', '');
		$('hide_filters').setStyle('display', 'none');
	});
	$('show_filters').addEvent('click', function() {
		$('filterline').setStyle('display', '');
		$('show_filters').setStyle('display', 'none');
		$('hide_filters').setStyle('display', '');
	});
*/ ?>
});
</script>

<script type="text/javascript">

<?php if ($this->unassociated && !$this->badcatitems) : ?>
	var unassociated_items = <?php echo $this->unassociated; ?>;
	function bindItems() {
		jQuery('#log-bind').html('<p class="centerimg"><img src="components/com_flexicontent/assets/images/ajax-loader-orange.gif" align="center"></p>');
		
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
window.addEvent('domready', function() {
	$('fixcatForm').addEvent('submit', function(e) {
		if ( !$('fixcatForm').elements['default_cat'].options[$('fixcatForm').elements['default_cat'].selectedIndex].value ) {
			alert('Please select a category');
			return false;
		}
		$('fixcatForm').action += '&default_cat='+$('fixcatForm').elements['default_cat'].options[$('fixcatForm').elements['default_cat'].selectedIndex].value;
		
		if(MooTools.version>="1.2.4") {
			$('log-fixcat').set('html', '<p class="centerimg"><img src="components/com_flexicontent/assets/images/ajax-loader-orange.gif" align="center"></p>');
			e = e.stop();
		}else{
			$('log-fixcat').setHTML('<p class="centerimg"><img src="components/com_flexicontent/assets/images/ajax-loader-orange.gif" align="center"></p>');
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
<div class="flexicontent">


<?php if ($this->unassociated && !$this->badcatitems) : ?>
	<div class="fc-mssg fc-warning">
	<table>
		<tr>
			<td>
			<span style="font-size:115%;">
			<?php echo JText::_( 'FLEXI_UNASSOCIATED_WARNING' ); ?>
			</span>
			</td>
			<td align="center" width="35%">
				<span style="font-size:150%;"><span id="orphan_items_count"><?php echo $this->unassociated; ?></span></span>
				<span style="font-size:115%;"><?php echo JText::_( 'FLEXI_ITEMS_TO_BIND' ); ?></span>
				<div id="log-bind"></div>
				<form action="index.php?option=com_flexicontent&<?php echo $items_task; ?>bindextdata&tmpl=component&format=raw" method="post" name="bindForm" id="bindForm">
					<?php echo $this->lists['bind_limits']; ?>
					<?php
						$types = $this->get( 'Typeslist' );
						echo JText::_( 'Bind to' ). flexicontent_html::buildtypesselect($types, 'typeid', $typesselected='', false, 'size="1"', 'typeid');
					?>
					<input id="button-bind" type="button" class="fc_button" style='float:none !important;' value="<?php echo JText::_( 'FLEXI_BIND' ); ?>"
					onclick="jQuery(this.form).hide(); bindItems();" />
				</form>
			</td>
		</tr>
	</table>
	</div>
<?php endif; ?>


<?php if ($this->badcatitems) : ?>
<form action="index.php?option=com_flexicontent&<?php echo $items_task; ?>fixmaincat&tmpl=component&format=raw" method="post" name="fixcatForm" id="fixcatForm">
	<div class="fc-mssg fc-warning">
	<table>
		<tr>
			<td>
			<span style="font-size:115%;">
			<?php echo JText::_( 'Item with invalid or missing main category' ); ?>
			</span>
			</td>
			<td align="center" width="35%">
				<span style="font-size:150%;"><span id="badcat_items_count"><?php echo $this->badcatitems; ?></span></span>&nbsp;
				<br/>
				<?php echo JText::_( 'FLEXI_DEFAULT_CAT_FOR_NO_CAT_ITEMS' ).': '.$this->lists['default_cat']; ?>
				<input id="button-fixcat" type="submit" class="fc_button" style='float:none !important;' value="<?php echo JText::_( 'FLEXI_FIX' ); ?>"
				onclick="" />
				<div id="log-fixcat"></div>
			</td>
		</tr>
	</table>
	</div>
</form>
<?php endif; ?>



<form action="index.php" method="post" name="adminForm" id="adminForm">

	<table style="white-space:nowrap">
		<tr class="filterbuttons_head">
			<td>
				<div style="float:left; margin:2px 48px 0px 0px;">
					<label class="label"><?php echo JText::_( 'FLEXI_SEARCH' ); ?></label>
					<span class="radio"><?php echo $this->lists['scope']; ?></span>
					<input type="text" name="search" id="search" value="<?php echo $this->lists['search']; ?>" class="inputbox" />
				</div>
				
				<input type="button" class="fc_button" onclick="jQuery('#mainChooseColBox').slideToggle();" value="<?php echo JText::_( 'Columns' ); ?>" />
				<input type="button" class="fc_button" onclick="jQuery('#stateGroupsBox').slideToggle();" value="<?php echo JText::_( 'State Groups' ); ?>" />
				<input type="button" class="fc_button" onclick="jQuery('#filterline').slideToggle('slow');" value="<?php echo JText::_( 'Filters' ); ?>" />
				<!--
				<input type="button" class="button" id="hide_filters" value="<?php echo JText::_( 'FLEXI_HIDE_FILTERS' ); ?>" />
				<input type="button" class="button" id="show_filters" value="<?php echo JText::_( 'FLEXI_DISPLAY_FILTERS' ); ?>" />
				-->
				
				<div class="clear"></div>
				
				<div class="limit" style="display: inline-block;">
					<label class="label">
						<?php echo JText::_(FLEXI_J16GE ? 'JGLOBAL_DISPLAY_NUM' : 'DISPLAY NUM'); ?>
					</label>
					<?php
					$pagination_footer = $this->pagination->getListFooter();
					if (strpos($pagination_footer, '"limit"') === false) echo $this->pagination->getLimitBox();
					?>
				</div>
				
				<span class="fc_item_total_data fc_nice_box" style="margin-right:10px;" >
					<?php echo @$this->resultsCounter ? $this->resultsCounter : $this->pagination->getResultsCounter(); // custom Results Counter ?>
				</span>
				
				<span class="fc_pages_counter" style="display:inline-block; white-space:nowrap;">
					<?php echo $this->pagination->getPagesCounter(); ?>
				</span>
				
				<div class='fc_mini_note_box' style='display: inline-block;'>
					<?php
					$tz_info =  $tz_offset > 0 ? ' UTC +' . $tz_offset : ' UTC ' . $tz_offset;
					if (FLEXI_J16GE) $tz_info .= ' ('.$user_zone.')';
					echo JText::sprintf( FLEXI_J16GE ? 'FLEXI_DATES_IN_USER_TIMEZONE_NOTE' : 'FLEXI_DATES_IN_SITE_TIMEZONE_NOTE', ' ', $tz_info );
					?>
				</div>
				
				<?php if (@$this->lists['filter_fileid']): ?>
					<div class="fcclear"></div>
					<?php echo '<label class="label">'.JText::_('List items using file') . '</label> ' . $this->lists['filter_fileid']; ?>
				<?php endif; ?>
				
			</td>
		</tr>
	</table>
	
	
	<div id="filterline" <?php if (!$this->count_filters) echo 'style="display:none;"'; ?> >
	
		<div class="fc-mssg-inline fc-nobgimage fc-success">
			<?php echo $this->lists['filter_authors']; ?>
			
			<?php if (FLEXI_FISH || FLEXI_J16GE) : ?>
				<?php echo $this->lists['filter_lang']; ?>
			<?php endif; ?>
			
			<?php echo $this->lists['filter_type']; ?>
			<?php echo $this->lists['filter_state']; ?>
		</div>
		
		<div class="fc-mssg-inline fc-nobgimage fc-success">
			<div style="display:inline-block; white-space:nowrap; padding:1px 0px 5px 0px;">
				<span style="display:none; color:darkred;" class="fc_nice_box" id="fcorder_save_warn_box"><?php echo JText::_('FLEXI_FCORDER_CLICK_TO_SAVE'); ?></span>
				<?php echo $ordering_type_tip; ?>
				<label class="label"><?php echo JText::_('FLEXI_ORDER_TYPE'); ?></label>
				<?php echo $this->lists['filter_order_type']; ?>
			</div>
		</div>
		
		<div class="fc-mssg-inline fc-nobgimage fc-success">
			<div style="display:inline-block; white-space:nowrap;">
				<?php echo $this->lists['filter_cats']; ?>
				<label class="label"><?php echo '&nbsp;'.JText::_( 'FLEXI_INCLUDE_SUBS' ); ?></label>
				<span class="radio"><?php echo $this->lists['filter_subcats']; ?></span>
			</div>
		</div>
		
		<div class="fc-mssg-inline fc-nobgimage fc-success">
			<div style="display:inline-block; white-space:nowrap; padding:1px 0px 5px 0px;">
				<span class="radio"><?php echo $this->lists['date']; ?></span>
				<?php echo $this->lists['startdate']; ?>
				<?php echo $this->lists['enddate']; ?>
			</div>
		</div>
		
		<div class="fc-mssg-inline fc-nobgimage fc-success">
			<div style="display:inline-block; white-space:nowrap; padding:1px 0px 5px 0px;">
				<label class="label"><?php echo JText::_('FLEXI_ID'); ?></label>
				<input type="text" name="filter_id" id="filter_id" size="4" value="<?php echo $this->lists['filter_id']; ?>" class="inputbox" />
			</div>
		</div>
		
		<div style="display:inline-block; white-space:nowrap; margin:0px 0px 12px 12px;">
			<input type="submit" class="fc_button fcsimple" onclick="this.form.submit();" value="<?php echo JText::_( 'FLEXI_APPLY_FILTERS' ); ?>" />
			<input type="button" class="fc_button fcsimple" onclick="delAllFilters();this.form.submit();" value="<?php echo JText::_( 'FLEXI_RESET_FILTERS' ); ?>" />
		</div>
		
	</div>
	
	
	<div id="mainChooseColBox" class="fc_nice_box" style="margin-top:6px; display:none;"></div>

	<div id="stateGroupsBox" class="fc_nice_box" <?php if (!$this->filter_stategrp && $this->filter_catsinstate==1) echo 'style="display:none;"'; ?> >
		
		<div style="float:left; margin-right:12px;">
		<?php
		$_img_title = JText::_('FLEXI_LIST_ITEMS_WITH_STATE', true);
		$_img_title_desc = JText::_('FLEXI_LIST_ITEMS_WITH_STATE_DESC', true);
		?>
		<label class="label"><?php echo '&nbsp;'.$_img_title; ?></label>
		<img src="components/com_flexicontent/assets/images/information.png" class="hasTip" alt="<?php echo $_img_title_desc; ?>" title="::<?php echo $_img_title_desc; ?>" style="vertical-align:middle;" />
		<?php echo $this->lists['filter_stategrp']; ?>
		</div>
		
		<div style="float:left;">
		<?php
		$_img_title = JText::_('FLEXI_LIST_ITEMS_IN_CATS', true);
		$_img_title_desc = JText::_('FLEXI_LIST_ITEMS_IN_CATS_DESC', true);
		?>
		<label class="label"><?php echo '&nbsp;'.$_img_title; ?></label>
		<img src="components/com_flexicontent/assets/images/information.png" class="hasTip" alt="<?php echo $_img_title_desc; ?>" title="::<?php echo $_img_title_desc; ?>" style="vertical-align:middle;" />
		<span class="radio"><?php echo $this->lists['filter_catsinstate']; ?></span>
		</div>
	</div>
	
	<table id="adminListTableFCitems" class="adminlist" cellspacing="1" style="margin-top:12px;">
	<thead>
		<tr>
			<th class="center" style="width:24px;">
				<?php echo JText::_( 'FLEXI_NUM' ); ?>
			</th>
			<th class="center" style="width:24px;">
				<input type="checkbox" name="toggle" value="" onclick="<?php echo FLEXI_J30GE ? 'Joomla.checkAll(this);' : 'checkAll('.count( $this->rows).');'; ?>" />
			</th>
			<th class="center" style="width:24px;">&nbsp;</th>
			<th class="left hideOnDemandClass">
				<?php echo JHTML::_('grid.sort', 'FLEXI_TITLE', 'i.title', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->search) : ?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC', true) ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER', true) ?>" onclick="delFilter('search');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			<th class="center hideOnDemandClass">
				<?php echo JText::_( 'FLEXI_AUTHOR' ); ?>
				<?php if ($this->filter_authors) : ?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC', true) ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER', true) ?>" onclick="delFilter('filter_authors');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>			
			<?php if (FLEXI_FISH || FLEXI_J16GE) : ?>
			<th nowrap="nowrap" class="center hideOnDemandClass">
				<?php echo JHTML::_('grid.sort', 'FLEXI_LANGUAGE', 'i.language', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->filter_lang) : ?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC', true) ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER', true) ?>" onclick="delFilter('filter_lang');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			<?php endif; ?>
			<th nowrap="nowrap" class="center hideOnDemandClass">
				<?php echo JHTML::_('grid.sort', 'FLEXI_TYPE_NAME', 'type_name', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->filter_type) : ?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC', true) ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER', true) ?>" onclick="delFilter('filter_type');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			<th nowrap="nowrap" class="center hideOnDemandClass">
				<?php echo JText::_( 'FLEXI_STATE', true ); ?>
				<?php if ($this->filter_state) : ?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC', true) ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER', true) ?>" onclick="delFilter('filter_state');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			<th class="center hideOnDemandClass">
				<?php echo JText::_( 'FLEXI_TEMPLATE' ); ?>
			</th>

		<?php if ( $enable_translation_groups ) : ?>
			<th class="center hideOnDemandClass">
				<?php echo JHTML::_('grid.sort', 'Translation Group', 'i.lang_parent_id', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>
		<?php endif; ?>

	   <?php foreach($this->extra_fields as $field) :?>
			<th class="center hideOnDemandClass">
				<?php echo $field->label; ?>
			</th>
		<?php endforeach; ?>

			<th width="<?php echo $this->CanOrder ? '' : ''; ?>" class="center hideOnDemandClass">
				<?php
				echo $this->CanOrder ? $image_ordering_tip : '';

				if (!$this->filter_order_type) :
					echo JHTML::_('grid.sort', 'FLEXI_REORDER', 'i.ordering', $this->lists['order_Dir'], $this->lists['order'] );
				else :
					echo JHTML::_('grid.sort', 'FLEXI_REORDER', 'catsordering', $this->lists['order_Dir'], $this->lists['order'] );
				endif;

				if ($this->CanOrder && $this->ordering) :
					echo JHTML::_('grid.order', $this->rows, 'filesave.png', $ctrl.'saveorder' );
				endif;
				?>
			</th>
			<th class="center hideOnDemandClass">
				<?php echo JHTML::_('grid.sort', 'FLEXI_ACCESS', 'i.access', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>
			<th class="left hideOnDemandClass">
				<?php echo $categories_tip; ?>
				<?php echo JText::_( 'FLEXI_CATEGORIES' ); ?>
				<?php if ($this->filter_cats) : ?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC', true) ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER', true) ?>" onclick="delFilter('filter_cats');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			<th class="center hideOnDemandClass">
				<?php echo JHTML::_('grid.sort',   'FLEXI_CREATED', 'i.created', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php
				if ($this->date == '1') :
					if (($this->startdate && ($this->startdate != JText::_('FLEXI_FROM'))) || ($this->enddate && ($this->startdate != JText::_('FLEXI_TO')))) :
				?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC', true) ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER', true) ?>" onclick="delFilter('startdate');delFilter('enddate');document.adminForm.submit();" />
				</span>
				<?php
					endif;
				endif;
				?>
			</th>
			<th class="center hideOnDemandClass">
				<?php echo JHTML::_('grid.sort',   'FLEXI_REVISED', 'i.modified', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php
				if ($this->date == '2') :
					if (($this->startdate && ($this->startdate != JText::_('FLEXI_FROM'))) || ($this->enddate && ($this->startdate != JText::_('FLEXI_TO')))) :
				?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC', true) ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER', true) ?>" onclick="delFilter('startdate');delFilter('enddate');document.adminForm.submit();" />
				</span>
				<?php
					endif;
				endif;
				?>
			</th>
			<th nowrap="nowrap" class="center hideOnDemandClass">
				<?php echo JHTML::_('grid.sort', 'FLEXI_HITS', 'i.hits', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>
			<th nowrap="nowrap" class="center hideOnDemandClass">
				<?php echo JHTML::_('grid.sort', 'FLEXI_ID', 'i.id', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->filter_id) : ?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC', true) ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER', true) ?>" onclick="delFilter('filter_id');document.adminForm.submit();" />
				</span>
				<?php endif; ?>

			</th>
		</tr>
		
		<tr>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<?php if ( (FLEXI_FISH || FLEXI_J16GE) ): ?>
				<td></td>
			<?php endif; ?>
			<td></td>
			<td></td>
			<td></td>
			<?php if ( $enable_translation_groups ) : ?>
				<td></td>
			<?php endif; ?>

	  	<?php foreach($this->extra_fields as $field) :?>
				<td></td>
			<?php endforeach; ?>
			
			<td>
				<?php if ($this->filter_order_type && !$this->filter_cats && ($this->filter_order=='i.ordering' || $this->filter_order=='catsordering')): ?>
					<span style="color:darkgreen; white-space:nowrap;" class="fc_nice_box" id="fcorder_notes_box"><?php echo JText::_('FLEXI_FCORDER_USE_CATEGORY_FILTER'); ?></span>
				<?php endif; ?>
			</td>
			
			<td></td>
			<td>
				<?php if (!$this->filter_order_type && ($this->filter_order=='i.ordering' || $this->filter_order=='catsordering')): ?>
					<span style="color:darkgreen;" class="fc_nice_box"><?php echo JText::_('Joomla order, GROUPING BY main category'); ?></span>
				<?php elseif ($this->filter_order_type && !$this->filter_cats && ($this->filter_order=='i.ordering' || $this->filter_order=='catsordering')): ?>
					<span style="color:darkgreen; white-space:nowrap;" class="fc_nice_box"><?php echo JText::_('Grouping by first listed category'); ?></span>
				<?php endif; ?>
			</td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
		</tr>

	</thead>

	<tfoot>
		<tr>
			<td colspan="<?php echo $items_list_cols; ?>">
				<?php echo $pagination_footer; ?>
			</td>
		</tr>
		
		<tr>
			<td colspan="<?php echo $items_list_cols; ?>" style="margin: 0 auto !important; background-color: white;">
				<table class="admintable" style="margin: 0 auto !important; background-color: white;">
					<tr>
						<td><img src="../components/com_flexicontent/assets/images/tick.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_PUBLISHED', true ); ?>" /></td>
						<td><?php echo JText::_( 'FLEXI_PUBLISHED_DESC' ); ?> <u><?php echo JText::_( 'FLEXI_PUBLISHED' ); ?></u></td>
						<td><img src="../components/com_flexicontent/assets/images/publish_g.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_IN_PROGRESS', true ); ?>" /></td>
						<td><?php echo JText::_( 'FLEXI_NOT_FINISHED_YET' ); ?> <u><?php echo JText::_( 'FLEXI_PUBLISHED' ); ?></u></td>
					</tr><tr>
						<td><img src="../components/com_flexicontent/assets/images/publish_x.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_UNPUBLISHED', true ); ?>" /></td>
						<td><?php echo JText::_( 'FLEXI_UNPUBLISHED_DESC' ); ?></td>
						<td><img src="../components/com_flexicontent/assets/images/publish_r.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_PENDING', true ); ?>" /></td>
						<td><?php echo JText::_( 'FLEXI_NEED_TO_BE_APPROVED' ); ?> <u><?php echo JText::_( 'FLEXI_UNPUBLISHED_DESC' ); ?></u></td>
						<td><img src="../components/com_flexicontent/assets/images/publish_y.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_TO_WRITE', true ); ?>" /></td>
						<td><?php echo JText::_( 'FLEXI_TO_WRITE_DESC' ); ?> <u><?php echo JText::_( 'FLEXI_UNPUBLISHED_DESC' ); ?></u></td>
					</tr><tr>
						<td><img src="../components/com_flexicontent/assets/images/archive.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_ARCHIVED', true ); ?>" /></td>
						<td><?php echo JText::_( 'FLEXI_ARCHIVED_STATE' ); ?> <u><?php echo JText::_( 'FLEXI_UNPUBLISHED_DESC' ); ?></u></td>
						<td><img src="../components/com_flexicontent/assets/images/trash.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_TRASHED', true ); ?>" /></td>
						<td><?php echo JText::_( 'FLEXI_TRASHED_STATE' ); ?> <u><?php echo JText::_( 'FLEXI_UNPUBLISHED_DESC' ); ?></u></td>
					</tr>
				</table>
			</td>
		</tr>
		
	</tfoot>

	<tbody <?php echo $ordering_draggable && $this->CanOrder && $this->ordering ? 'id="sortable_fcitems"' : ''; ?> >
		<?php
		$k = 0;
		if (FLEXI_J16GE)
			$date_format = (($date_format = JText::_( 'FLEXI_DATE_FORMAT_FLEXI_ITEMS_J16GE' )) == 'FLEXI_DATE_FORMAT_FLEXI_ITEMS_J16GE') ? "d/m/y H:i" : $date_format;
		else
			$date_format = (($date_format = JText::_( 'FLEXI_DATE_FORMAT_FLEXI_ITEMS' )) == 'FLEXI_DATE_FORMAT_FLEXI_ITEMS') ? "%d/%m/%y %H:%M" : $date_format;

		$unpublishableFound = false;
		for ($i=0, $n=count($this->rows); $i < $n; $i++)
		{
			$row = & $this->rows[$i];

			if (FLEXI_J16GE) {
				$rights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $row->id);

				$canEdit 			 = in_array('edit', $rights);
				$canEditOwn		 = in_array('edit.own', $rights) && $row->created_by == $user->id;
				$canPublish 	 = in_array('edit.state', $rights);
				$canPublishOwn = in_array('edit.state.own', $rights) && $row->created_by == $user->id;
			} else if ($user->gid > 24) {
				$canEdit = $canEditOwn = $canPublish = $canPublishOwn = 1;
			} else if (FLEXI_ACCESS) {
				$rights 			= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $row->id, $row->catid);

				$canEdit 			= /*$canEditAll ||*/ in_array('edit', $rights);
				$canEditOwn			= (in_array('editown', $rights) /*|| $canEditOwnAll)*/) && $row->created_by == $user->id;
				$canPublish 		= /*$canPublishAll ||*/ in_array('publish', $rights);
				$canPublishOwn		= (in_array('publishown', $rights) /*|| $canPublishOwnAll*/) && $row->created_by == $user->id;
			} else {
				// J1.5 with no FLEXIaccess, since backend users are at least 'manager', these should be true anyway
				$canEdit		= $user->authorize('com_content', 'edit', 'content', 'all');
				$canEditOwn	= $user->authorize('com_content', 'edit', 'content', 'own') && $row->created_by == $user->id;
				$canPublish	=	$user->authorize('com_content', 'publish', 'content', 'all');
				$canPublishOwn= 1; // due to being backend user
			}
			$canPublishCurrent = $canPublish || $canPublishOwn;
			$unpublishableFound = $unpublishableFound || !$canPublishCurrent;


			$publish_up   = JFactory::getDate($row->publish_up);
			$publish_down = JFactory::getDate($row->publish_down);
			if (FLEXI_J16GE) {
				$publish_up->setTimezone($tz);
				$publish_down->setTimezone($tz);
			} else {
				$publish_up->setOffset($tz_offset);
				$publish_down->setOffset($tz_offset);
			}

			$link = 'index.php?option=com_flexicontent&amp;'.$items_task.'edit&amp;cid[]='. $row->id;

			if (FLEXI_J16GE) {
				if (($canEdit || $canEditOwn) && $this->CanAccLvl) {
					$access = flexicontent_html::userlevel('access['.$row->id.']', $row->access, 'onchange="return listItemTask(\'cb'.$i.'\',\'items.access\')"');
				} else {
					$access = $this->escape($row->access_level);
				}
			} else if (FLEXI_ACCESS) {
				if (($canEdit || $canEditOwn) && $this->CanAccLvl) {
					$access 	= FAccess::accessswitch('item', $row, $i);
				} else {
					$access 	= FAccess::accessswitch('item', $row, $i, 'content', 1);
				}
			} else {
				$access 	= JHTML::_('grid.access', $row, $i );
			}

			$cid_checkbox = @ JHTML::_('grid.checkedout', $row, $i );

			// Check publication START/FINISH dates (publication Scheduled / Expired)
			$is_published = in_array( $row->state, array(1, -5, (FLEXI_J16GE ? 2:-1) ) );
			$extra_img = $extra_alt = '';

			if ( $row->publication_scheduled && $is_published ) {
				$extra_img = 'pushished_scheduled.png';
				$extra_alt = JText::_( 'FLEXI_SCHEDULED_FOR_PUBLICATION', true );
			}
			if ( $row->publication_expired && $is_published ) {
				$extra_img = 'pushished_expired.png';
				$extra_alt = JText::_( 'FLEXI_PUBLICATION_EXPIRED', true );
			}

			// Set a row language, even if empty to avoid errors
			$lang_default = !FLEXI_J16GE ? '' : '*';
			$row->lang = @$row->lang ? $row->lang : $lang_default;
   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td align="center" class="sort_handle"><?php echo $this->pagination->getRowOffset( $i ); ?></td>
			<td align="center"><?php echo $cid_checkbox; ?></td>
			<td align="center">
				<?php
				$item_link = str_replace('&', '&amp;', FlexicontentHelperRoute::getItemRoute($row->id.':'.$row->alias, $globalcats[$row->catid]->slug, 0, $row));
				$item_link = JRoute::_(JURI::root().$item_link, $xhtml=false);  // xhtml to false we do it manually above (at least the ampersand) also it has no effect because we prepended the root URL ?
				$previewlink = $item_link .'&amp;preview=1' .$autologin;
				echo '<a class="preview" href="'.$previewlink.'" target="_blank">'.$image_zoom.'</a>';
				?>
			</td>
			<td align="left" class="col_title">
				<?php

				// Display an icon with checkin link, if current user has checked out current item
				if ($row->checked_out) {
					if (FLEXI_J16GE) {
						$canCheckin = $user->authorise('core.admin', 'checkin');
					} else if (FLEXI_ACCESS) {
						$canCheckin = ($user->gid < 25) ? FAccess::checkComponentAccess('com_checkin', 'manage', 'users', $user->gmid) : 1;
					} else {
						$canCheckin = $user->gid >= 24;
					}
					if ($canCheckin) {
						//if (FLEXI_J16GE && $row->checked_out == $user->id) echo JHtml::_('jgrid.checkedout', $i, $row->editor, $row->checked_out_time, 'items.', $canCheckin);
						$task_str = FLEXI_J16GE ? 'items.checkin' : 'checkin';
						if ($row->checked_out == $user->id) {
							echo JText::sprintf('FLEXI_CLICK_TO_RELEASE_YOUR_LOCK', $row->editor, $row->checked_out_time, '"cb'.$i.'"', '"'.$task_str.'"');
						} else {
							echo '<input id="cb'.$i.'" type="checkbox" value="'.$row->id.'" name="cid[]" style="display:none;">';
							echo JText::sprintf('FLEXI_CLICK_TO_RELEASE_FOREIGN_LOCK', $row->editor, $row->checked_out_time, '"cb'.$i.'"', '"'.$task_str.'"');
						}
					}
				}

				// Display title with no edit link ... if row checked out by different user -OR- is uneditable
				if ( ( $row->checked_out && $row->checked_out != $user->id ) || ( !$canEdit && !$canEditOwn ) ) {
					echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8');

				// Display title with edit link ... (row editable and not checked out)
				} else {
				?>
					<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM', true );?>::<?php echo $row->title; ?>">
					<?php
					if ( $enable_translation_groups ) :
						if ($this->lists['order']=='i.lang_parent_id'&& $row->id!=$row->lang_parent_id) echo "<sup>|</sup>--";
					endif;
					?>
					<a href="<?php echo $link; ?>">
					<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
					</a></span>
				<?php
				}
				?>

			</td>
			<td align="center" class="col_authors">
				<?php echo $row->author; ?>
			</td>

		<?php if ( (FLEXI_FISH || FLEXI_J16GE) ): ?>
			<td align="center" class="hasTip col_lang" title="<?php echo JText::_( 'FLEXI_LANGUAGE', true ).'::'.($row->lang=='*' ? JText::_("All") : $this->langs->{$row->lang}->name); ?>">

				<?php if ( !empty($row->lang) && !empty($this->langs->{$row->lang}->imgsrc) ) : ?>
					<img src="<?php echo $this->langs->{$row->lang}->imgsrc; ?>" alt="<?php echo $row->lang; ?>" />
				<?php elseif( !empty($row->lang) ) : ?>
					<?php echo $row->lang=='*' ? JText::_("FLEXI_ALL") : $row->lang;?>
				<?php endif; ?>

			</td>
		<?php endif; ?>

			<td align="center" class="col_type">
				<?php echo $row->type_name; ?>
			</td>
			<td align="center" class="col_state">
			<?php echo flexicontent_html::statebutton( $row, $row->params, $addToggler = ($limit <= $this->inline_ss_max) ); ?>
			<?php if ($extra_img) : ?><img style='float:right;' src="components/com_flexicontent/assets/images/<?php echo $extra_img;?>" width="16" height="16" border="0" class="hasTip" alt="<?php echo $extra_alt; ?>" title="<?php echo $extra_alt; ?>" /><?php endif; ?>
			</td>

			<td align="center">
				<?php echo ($row->config->get("ilayout","") ? $row->config->get("ilayout") : $row->tconfig->get("ilayout")."<sup>[1]</sup>") ?>
			</td>

		<?php if ( $enable_translation_groups ) : ?>
			<td align="center">
				<?php
					/*if ($this->lists['order']=='i.lang_parent_id') {
						if ($row->id==$row->lang_parent_id) echo "Main";
						else echo "+";
					}*/// else echo "unsorted<sup>[3]</sup>";

				if ( (FLEXI_FISH || FLEXI_J16GE) && !empty($this->lang_assocs[$row->lang_parent_id]) )
				{
					$row_modified = 0;
					foreach($this->lang_assocs[$row->lang_parent_id] as $assoc_item) {
						if ($assoc_item->id == $row->lang_parent_id) {
							$row_modified = strtotime($assoc_item->modified);
							if (!$row_modified)  $row_modified = strtotime($assoc_item->created);
						}
					}

					echo "<br/>";
					foreach($this->lang_assocs[$row->lang_parent_id] as $assoc_item) {
						if ($assoc_item->id==$row->id) continue;

						$_link  = 'index.php?option=com_flexicontent&amp;'.$items_task.'edit&amp;cid[]='. $assoc_item->id;
						$_title = JText::_( 'FLEXI_EDIT_ASSOC_TRANSLATION', true ).':: ['. $assoc_item->lang .'] '. $assoc_item->title;
						echo "<a class='fc_assoc_translation editlinktip hasTip' target='_blank' href='".$_link."' title='".$_title."' >";
						//echo $assoc_item->id;
						if ( !empty($assoc_item->lang) && !empty($this->langs->{$assoc_item->lang}->imgsrc) ) {
							echo ' <img src="'.$this->langs->{$assoc_item->lang}->imgsrc.'" alt="'.$assoc_item->lang.'" />';
						} else if( !empty($assoc_item->lang) ) {
							echo $assoc_item->lang=='*' ? JText::_("FLEXI_ALL") : $assoc_item->lang;
						}

						$assoc_modified = strtotime($assoc_item->modified);
						if (!$assoc_modified)  $assoc_modified = strtotime($assoc_item->created);
						if ( $assoc_modified < $row_modified ) echo "(!)";
						echo "</a>";
					}
				}

				?>
			</td>
		<?php endif ; ?>


    <?php foreach($this->extra_fields as $field) :?>

			<td align="center">
		    <?php
		    // Clear display HTML just in case
		    if (isset($field->{$field->methodname}))
		    	unset( $field->{$field->methodname} );

		    // Field value for current item
		    $field_value = & $row->extra_field_value[$field->name];

		    if ( !empty($field_value) )
		    {
					// Create field's display HTML, via calling FlexicontentFields::renderField() for the given method name
					FlexicontentFields::renderField($row, $field, $field_value, $method=$field->methodname);

					// Output the field's display HTML
					echo @$field->{$field->methodname};
				}
		    ?>
			</td>
		<?php endforeach; ?>

		<?php if ($this->CanOrder) : ?>
			<td class="order ">
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

				<?php $disabled = $this->ordering ?  '' : 'disabled="disabled"'; ?>
				<input class="fcitem_order_no" type="text" name="order[]" size="5" value="<?php echo $row->$ord_col; ?>" <?php echo $disabled; ?> style="text-align: center" />

				<input type="hidden" name="item_cb[]" style="display:none;" value="<?php echo $row->id; ?>" />
				<input type="hidden" name="ord_catid[]" style="display:none;" value="<?php echo $row->$ord_catid; ?>" />
				<input type="hidden" name="prev_order[]" style="display:none;" value="<?php echo $row->$ord_col; ?>" />
				<input type="hidden" name="ord_grp[]" style="display:none;" value="<?php echo $show_orderDown ? $ord_grp : $ord_grp++; ?>" />

			</td>
		<?php else : ?>
			<td align="center">
				<?php
				if (!$this->filter_order_type) {
					echo $row->ordering;
				} else {
					echo $row->catsordering;
				}
				?>
			</td>
		<?php endif; ?>

			<td align="center" class="col_access">
				<?php echo $access; ?>
			</td>
			<td class="col_cats">
				<?php
				// Reorder categories when changing Joomla ordering and place item's MAIN category first ...
				if (!$this->filter_order_type) {
					$__cats = array();
					$nn = 1;
					foreach ($row->categories as $key => $_icat) {
						if ( !isset($this->itemCats[$_icat]) ) continue;
						$category = & $this->itemCats[$_icat];
						$isMainCat = ((int)$category->id == (int)$row->catid);
						if ($isMainCat) $__cats[0] = $_icat;
						else $__cats[$nn++] = $_icat;
					}
					$row->categories = $__cats;
				}
				$nr = count($row->categories);
				$ix = 0;
				$nn = 0;
				for ($nn=0; $nn < $nr; $nn++) :
					$_item_cat = $row->categories[$nn];
					if ( !isset($this->itemCats[$_item_cat]) ) continue;
					$category = & $this->itemCats[$_item_cat];
					
					$isMainCat = ((int)$category->id == (int)$row->catid);
					//if (!$this->filter_order_type && !$isMainCat) continue;
					
					$typeofcats = '';
					if ( $ix==0 && ($this->filter_order=='i.ordering' || $this->filter_order=='catsordering') )
						$typeofcats .= ' orderingcat';
					$typeofcats .= $isMainCat ? ' maincat' : ' secondarycat';
					$catlink	= 'index.php?option=com_flexicontent&amp;'.$cats_task.'edit&amp;cid[]='. $category->id;
					$title = htmlspecialchars($category->title, ENT_QUOTES, 'UTF-8');
					if ($this->CanCats) :
				?>
					<span class="editlinktip hasTip<?php echo $typeofcats; ?>" title="<?php echo JText::_( 'FLEXI_EDIT_CATEGORY', true );?>::<?php echo $title; ?>">
					<a href="<?php echo $catlink; ?>">
						<?php
						if (JString::strlen($title) > 40) {
							echo JString::substr( $title , 0 , 40).'...';
						} else {
							echo $title;
						}
						?></a></span>
					<?php
					else :
						if (JString::strlen($title) > 40) {
							echo ($category->id != $row->catid) ? '' : '<strong>';
							echo JString::substr( $title , 0 , 40).'...';
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
			<td align="center" nowrap="nowrap" class="col_created">
				<?php echo JHTML::_('date',  $row->created, $date_format ); ?>
			</td>
			<td align="center" nowrap="nowrap" class="col_revised">
				<?php echo ($row->modified != $this->db->getNullDate()) ? JHTML::_('date', $row->modified, $date_format) : JText::_('FLEXI_NEVER'); ?>
			</td>
			<td align="center">
				<?php echo $row->hits; ?>
			</td>
			<td align="center" class="col_id">
				<?php echo $row->id; ?>
			</td>
		</tr>
		<?php
			$k = 1 - $k;
		}
		if ( (FLEXI_ACCESS || FLEXI_J16GE) && $unpublishableFound) {
			$ctrl_task = FLEXI_J16GE ? 'items.approval' : 'approval';
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

	<div class="clear"></div>

	<sup>[1]</sup> <?php echo JText::_('FLEXI_TMPL_NOT_SET_USING_TYPE_DEFAULT'); ?><br />
	<sup>[2]</sup> <?php echo JText::sprintf('FLEXI_INLINE_ITEM_STATE_SELECTOR_DISABLED', $this->inline_ss_max); ?><br />
	<?php if ( $enable_translation_groups )	:?>
	<sup>[3]</sup> <?php echo JText::_('FLEXI_SORT_TO_GROUP_TRANSLATION'); ?><br />
	<?php endif;?>
	<sup>[4]</sup> <?php echo JText::_('FLEXI_MULTIPLE_ITEM_ORDERINGS'); ?><br />
	<sup>[5]</sup> <?php echo JText::_('FLEXI_CAN_ADD_CUSTOM_FIELD_COLUMNS_COMPONENT_AND_PER_TYPE'); ?><br />

	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="items" />
	<input type="hidden" name="view" value="items" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="newstate" id="newstate" value="" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<?php echo JHTML::_( 'form.token' ); ?>
</form>
</div>

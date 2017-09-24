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
JHtml::addIncludePath(JPATH_COMPONENT . '/helpers/html');


// Create custom field columns
foreach($this->extra_fields as $_field):
	$values = null;
	$field = $_field;
	FlexicontentFields::renderField($this->rows, $field->name, $values, $field->methodname);
endforeach;

$app = JFactory::getApplication();
$jinput = $app->input;
$tip_class = ' hasTooltip';
$btn_class = 'btn';  //'fc_button fcsimple';

$ico_class   = 'fc-man-icon-s';
$btn_s_class = 'btn btn-small';

$featimg = JHtml::image ( 'administrator/components/com_flexicontent/assets/images/star.png', JText::_( 'FLEXI_FEATURED' ), ' style="text-align:left" class="'.$ico_class.'" title="'.JText::_( 'FLEXI_FEATURED' ).'"' );

$start_text = '<span class="label">'.JText::_('FLEXI_COLUMNS', true).'</span>  &nbsp; ';
$end_text = '<div class="icon-arrow-up-2 btn" title="'.JText::_('FLEXI_HIDE').'" style="cursor: pointer;" onclick="fc_toggle_box_via_btn(\\\'mainChooseColBox\\\', document.getElementById(\\\'fc_mainChooseColBox_btn\\\'), \\\'btn-primary\\\');"></div>';
flexicontent_html::jscode_to_showhide_table('mainChooseColBox', 'adminListTableFCitems', $start_text, $end_text);

global $globalcats;
$cparams = JComponentHelper::getParams( 'com_flexicontent' );
$ctrl  = 'items.';
$items_task = 'task=items.';
$cats_task  = 'task=category.';

$db 			= JFactory::getDbo();
$config		= JFactory::getConfig();
$nullDate	= $db->getNullDate();
$user 		= JFactory::getUser();

//$_sh404sef = JPluginHelper::isEnabled('system', 'sh404sef') && $config->get('sef');
$_sh404sef = defined('SH404SEF_IS_RUNNING') && $config->get('sef');
$isAdmin = $app->isAdmin();
$useAssocs = flexicontent_db::useAssociations();

$list_total_cols = 18;
if ( $useAssocs ) $list_total_cols++;

$list_total_cols += count($this->extra_fields);


// ***
// *** COMMON repeated texts
// ***

$edit_item_title = JText::_('FLEXI_EDIT_ITEM', true);
$edit_cat_title = JText::_('FLEXI_EDIT_CATEGORY', true);
$edit_layout = htmlspecialchars(JText::_('FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS', true), ENT_QUOTES, 'UTF-8');
$rem_filt_txt = JText::_('FLEXI_REMOVE_FILTER', true);
$rem_filt_tip = ' class="'.$tip_class.' filterdel" title="'.flexicontent_html::getToolTip('FLEXI_ACTIVE_FILTER', 'FLEXI_CLICK_TO_REMOVE_THIS_FILTER', 1, 1).'" ';
$_NEVER_ = JText::_('FLEXI_NEVER');
$_NULL_DATE_ = $this->db->getNullDate();


// ***
// *** ICONS
// ***

$image_flag_path = "../media/mod_languages/images/";

$attribs_preview = ' class="'.$ico_class.' '.$tip_class.'" title="'.flexicontent_html::getToolTip( 'FLEXI_PREVIEW', 'FLEXI_DISPLAY_ENTRY_IN_FRONTEND_DESC', 1, 1).'" ';


$attribs_preview    = ' class="fc-preview-btn ntxt '.$btn_s_class.' '.$tip_class.'" title="'.flexicontent_html::getToolTip( 'FLEXI_PREVIEW', 'FLEXI_DISPLAY_ENTRY_IN_FRONTEND_DESC', 1, 1).'" ';
$attribs_editlayout = ' class="fc-edit-layout-btn ntxt '.$btn_s_class.' '.$tip_class.'" title="'.flexicontent_html::getToolTip( 'FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS', null, 1, 1).'" ';

$image_preview = 0
	? JHtml::image( 'components/com_flexicontent/assets/images/'.'monitor_go.png', JText::_('FLEXI_PREVIEW'), ' class="'.$ico_class.'"')
	: '<span class="icon-screen"></span>';

$image_editlayout = 0
	? JHtml::image('components/com_flexicontent/assets/images/'.'layout_edit.png', htmlspecialchars(JText::_('FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS'), ENT_QUOTES, 'UTF-8'), ' class="'.$ico_class.'"')
	: '<span class="icon-edit"></span>';



// ***
// *** Ordering related data
// ***

$ordering_draggable = $cparams->get('draggable_reordering', 1);
if ($this->reOrderingActive)
{
	$image_ordering_tip = '<img src="components/com_flexicontent/assets/images/comments.png" class="'.$ico_class.' '.$tip_class.'" alt="Reordering" title="'.flexicontent_html::getToolTip('FLEXI_REORDERING', 'FLEXI_REORDERING_ENABLED_DESC', 1, 1).'" /> ';
	//$image_ordering_tip = '<span class="icon-info '.$tip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_REORDERING', 'FLEXI_REORDERING_ENABLED_DESC', 1, 1).'"></span>';
	$drag_handle_box = '<div class="fc_drag_handle%s" title="'.JText::_('FLEXI_ORDER_SAVE_WHEN_DONE', true).'"></div>';
}
else
{
	$image_ordering_tip = '<img src="components/com_flexicontent/assets/images/comments.png" class="'.$ico_class.' '.$tip_class.'" alt="Reordering" title="'.flexicontent_html::getToolTip('FLEXI_REORDERING', 'FLEXI_REORDERING_DISABLED_DESC', 1, 1).'" /> ';
	//$image_ordering_tip = '<span class="icon-info '.$tip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_REORDERING', 'FLEXI_REORDERING_DISABLED_DESC', 1, 1).'"></span>';
	$drag_handle_box = '<div class="fc_drag_handle%s" title="'.JText::_('FLEXI_ORDER_COLUMN_FIRST', true).'" ></div>';
	$image_saveorder    = '';
}

$drag_handle_html['disabled'] = sprintf($drag_handle_box, ' fc_drag_handle_disabled');
$drag_handle_html['both']     = sprintf($drag_handle_box, ' fc_drag_handle_both');
$drag_handle_html['uponly']   = sprintf($drag_handle_box, ' fc_drag_handle_uponly');
$drag_handle_html['downonly'] = sprintf($drag_handle_box, ' fc_drag_handle_downonly');
$drag_handle_html['none']     = sprintf($drag_handle_box, '_none');

$_img_title = JText::_('MAIN category shown in bold', true);
$categories_tip  = '<img src="components/com_flexicontent/assets/images/information.png" class="'.$ico_class.' '.$tip_class.'" alt="'.$_img_title.'" title="'.flexicontent_html::getToolTip(null, $_img_title, 0, 1).'" />';

if ( !$this->filter_order_type )
{
	$_img_title = JText::_('FLEXI_ORDER_JOOMLA');
	$_img_title_desc = JText::sprintf('FLEXI_CURRENT_ORDER_IS',JText::_('FLEXI_ORDER_JOOMLA')).' '.JText::_('FLEXI_ITEM_ORDER_EXPLANATION_TIP');
	$ord_catid = 'catid';
	$ord_col = 'ordering';
}
else
{
	$_img_title = JText::_('FLEXI_ORDER_FLEXICONTENT', true);
	$_img_title_desc = JText::sprintf('FLEXI_CURRENT_ORDER_IS',JText::_('FLEXI_ORDER_FLEXICONTENT')).' '.JText::_('FLEXI_ITEM_ORDER_EXPLANATION_TIP');
	$ord_catid = 'rel_catid';
	$ord_col = 'catsordering';
}
$ordering_type_attrs = ' data-placement="bottom" class="add-on fc-lbl-inverted fc-lbl-padded icon-info '.$tip_class.'" title="'.flexicontent_html::getToolTip($_img_title, $_img_title_desc, 0, 1).'" ';

$ord_grp = 1;



// ***
// *** Various variables
// ***

$fcfilter_attrs_row  = ' class="input-prepend fc-xpended-row" ';
$fcfilter_attrs = ' class="input-prepend fc-xpended" ';
$stategrps = array(1=>'published', 0=>'unpublished', -2=>'trashed', -3=>'unpublished', -4=>'unpublished', -5=>'published');
$tools_cookies['fc-filters-box-disp'] = JFactory::getApplication()->input->cookie->get('fc-filters-box-disp', 0, 'int');



// ***
// *** Create dates displayed using current user's timezone
// ***

$site_zone = $app->getCfg('offset');
$user_zone = JFactory::getUser()->getParam('timezone', $site_zone);
$tz = new DateTimeZone( $user_zone );
$tz_offset = $tz->getOffset(new JDate()) / 3600;
$tz_info =  $tz_offset > 0 ? ' UTC +' . $tz_offset : ' UTC ' . $tz_offset;
$tz_info .= ' ('.$user_zone.')';
$date_note_msg   = JText::sprintf( FLEXI_J16GE ? 'FLEXI_DATES_IN_USER_TIMEZONE_NOTE' : 'FLEXI_DATES_IN_SITE_TIMEZONE_NOTE', ' ', $tz_info );
$date_note_attrs = ' class="input-append input-prepend fc-xpended '.$tip_class.'" title="'.flexicontent_html::getToolTip(null, $date_note_msg, 0, 1).'" ';
//$date_zone_tip   = JHtml::image ( 'administrator/components/com_flexicontent/assets/images/comments.png', JText::_( 'FLEXI_NOTES' ), $date_note_attrs );



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
	{
		filter.checked = '';
	}
	else
	{
		filter.val('');
		// Case that input has Calendar JS attached
		if (filter.attr('data-alt-value'))
		{
			filter.attr('data-alt-value', '');
		}
	}
}

function delAllFilters() {
	jQuery('.fc_field_filter').val('');
	delFilter('search'); delFilter('filter_type'); delFilter('filter_state');
	delFilter('filter_cats'); delFilter('filter_featured');
	delFilter('filter_author'); delFilter('filter_id');
	delFilter('startdate'); delFilter('enddate'); delFilter('filter_lang');
	delFilter('filter_tag'); delFilter('filter_access');
	delFilter('filter_fileid');
	delFilter('filter_order'); delFilter('filter_order_Dir');
	jQuery('#filter_subcats').attr('checked', 'checked');  // default: include subcats
	jQuery('#filter_catsinstate').val('1');	  // default: published categories
}

<?php if ($this->reOrderingActive) : ?>
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
		<span class="fc-filter nowrap_box" style="margin: 1px;">
			<?php echo $this->lists['scope']; ?>
		</span>
		<span class="btn-group input-append fc-filter filter-search">
			<input type="text" name="search" id="search" placeholder="<?php echo JText::_( 'FLEXI_SEARCH' ); ?>" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="inputbox" />
			<button title="" data-original-title="<?php echo JText::_('FLEXI_SEARCH'); ?>" class="<?php echo $btn_class.' '.$tip_class; ?>" onclick="document.adminForm.limitstart.value=0; Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-search"></i>' : JText::_('FLEXI_GO'); ?></button>
			<button title="" data-original-title="<?php echo JText::_('FLEXI_RESET_FILTERS'); ?>" class="<?php echo $btn_class.' '.$tip_class; ?>" onclick="document.adminForm.limitstart.value=0; delAllFilters(); Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-remove"></i>' : JText::_('FLEXI_CLEAR'); ?></button>
		</span>

		<?php $_class = FLEXI_J30GE ? ' btn' : ' fc_button fcsimple fcsmall'; ?>
		<span class="fc-filter nowrap_box" style="margin: 1px;">
			<span class="hasTooltip" title="<?php echo JText::_('FLEXI_IMAN_ABOUT_ADDING_MORE_COLUMNS_AND_FILTERS'); ?>" style="display: inline-block; margin-top: 3px;" >
				<i class="icon-cog" style="font-size: 14px; margin-top: 2px;"></i>
			</span>
			<span class="btn-group fc-filter">
				<span id="fc_filters_box_btn" class="<?php echo $_class.($this->count_filters ? ' btn-primary' : ''); ?>" onclick="fc_toggle_box_via_btn('fc-filters-box', this, 'btn-primary', false, undefined, 1);">
					<?php echo JText::_( 'FLEXI_FILTERS' ) . ($this->count_filters  ? ' <sup>'.$this->count_filters.'</sup>' : ''); ?> &nbsp;
				</span>
				<span id="fc_mainChooseColBox_btn" class="<?php echo $_class; ?>" onclick="fc_toggle_box_via_btn('mainChooseColBox', this, 'btn-primary');">
					<?php echo JText::_( 'FLEXI_COLUMNS' ); ?><sup id="columnchoose_totals"></sup>
				</span>
				<span id="fc-toggle-cats_btn" class="<?php echo $_class; ?> hasTooltip" title="<?php echo JText::_('FLEXI_SECONDARY_CATEGORIES'); ?>" onclick="jQuery(this).data('box_showing', !jQuery(this).data('box_showing')); jQuery(this).data('box_showing') ? jQuery('.fc_assignments_box.fc_cats').show(400) : jQuery('.fc_assignments_box.fc_cats').hide(400);" ><span class="icon-tree-2"></span></span>
				<span id="fc-toggle-tags_btn" class="<?php echo $_class; ?> hasTooltip" title="<?php echo JText::_('FLEXI_TAGS'); ?>" onclick="jQuery(this).data('box_showing', !jQuery(this).data('box_showing')); jQuery(this).data('box_showing') ? jQuery('.fc_assignments_box.fc_tags').show(400) : jQuery('.fc_assignments_box.fc_tags').hide(400);" ><span class="icon-tags"></span></span>
				<span id="fc-mini-help_btn" class="<?php echo $_class; ?>" onclick="fc_toggle_box_via_btn('fc-mini-help', this, 'btn-primary');" ><span class="icon-help"></span></span>
			</span>
			<input type="hidden" id="fc-filters-box-disp" name="fc-filters-box-disp" value="<?php echo $tools_cookies['fc-filters-box-disp']; ?>" />
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
	
	
	<div id="fc-filters-box" <?php if (!$this->count_filters || !$tools_cookies['fc-filters-box-disp']) echo 'style="display:none;"'; ?> class="">
		<!--<span class="label"><?php echo JText::_( 'FLEXI_FILTERS' ); ?></span>-->
		
		<?php if (@$this->lists['filter_fileid']): ?>
		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<?php echo $this->lists['filter_fileid']; ?>
			</div>
		</div>
		<?php endif; ?>

		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<?php echo $this->lists['filter_author']; ?>
			</div>
		</div>

		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<?php echo $this->lists['filter_tag']; ?>
			</div>
		</div>

		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<?php echo $this->lists['filter_type']; ?>
			</div>
		</div>

		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<?php echo $this->lists['filter_lang']; ?>
			</div>
		</div>

		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<?php echo $this->lists['filter_state']; ?>
			</div>
		</div>

		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<?php echo $this->lists['filter_access']; ?>
			</div>
		</div>

	<?php if (!$this->reOrderingActive): ?>

		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<?php echo $this->lists['filter_cats']; ?>
			</div>
		</div>

		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<?php echo $this->lists['filter_subcats']; ?>
			</div>
		</div>

	<?php endif; ?>
		
		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<?php echo $this->lists['filter_featured']; ?>
			</div>
		</div>
		
		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<?php echo $this->lists['filter_catsinstate']; ?>
			</div>
		</div>

		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<div class="add-on"><?php echo JText::_('FLEXI_ID'); ?></div>
				<input type="text" name="filter_id" id="filter_id" size="6" value="<?php echo $this->lists['filter_id']; ?>" class="inputbox" style="width:auto;" />
			</div>
		</div>

		<div class="fc-filter nowrap_box">
			<div <?php echo $date_note_attrs; ?> >
				<?php echo $this->lists['date']; ?>
			</div>
			<?php echo $this->lists['startdate']; ?>
			<?php echo $this->lists['enddate']; ?>
		</div>

		<?php foreach($this->custom_filts as $filt) : ?>
		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<?php echo $filt->html; ?>
			</div>
		</div>
		<?php endforeach; ?>
		
		<div id="fc-filters-slide-btn" class="icon-arrow-up-2 btn" title="<?php echo JText::_('FLEXI_HIDE'); ?>" style="cursor: pointer;" onclick="fc_toggle_box_via_btn('fc-filters-box', document.getElementById('fc_filters_box_btn'), 'btn-primary');"></div>
	</div>
	
	<div id="mainChooseColBox" class="well well-small" style="display:none;"></div>

	<?php echo @$this->minihelp; ?>
	<div class="fcclear"></div>

	<?php if ($this->reOrderingActive): ?>

		<?php if ($this->CanOrder): ?>
		<div id="fcorder_save_warn_box" class="fc-mssg-inline fc-nobgimage fc-info" style="padding: 4px 8px 4px 8px; margin: 4px 0 0 0; line-height: 28px; max-width: unset;">
			<span class="icon-pin"></span> <?php echo JText::_('FLEXI_FCORDER_CLICK_TO_SAVE') . flexicontent_html::gridOrderBtn($this->rows, 'filesave.png', $ctrl.'saveorder'); ?>
		</div>

		<?php else: ?>
		<div class="fc-mssg-inline fc-nobgimage fc-info" style="padding: 4px 8px 4px 8px; margin: 4px 0 0 0; line-height: 28px; max-width: unset;">
			<span class="icon-pin"></span> <?php echo JText::_('FLEXI_FCORDER_ONLY_VIEW') ; ?>
		</div>
		<?php endif; ?>

		<?php
		$order_msg = '';

		if (!$this->filter_order_type)
		{
			$order_msg .= JText::_('FLEXI_FCORDER_JOOMLA_ORDER_GROUPING_BY_MAINCAT');
			$msg_class = 'fc-mssg-inline fc-nobgimage fc-success';
			$msg_style = 'padding: 4px 8px; margin: 12px 0 6px 0;';
			$msg_icon  = '<span class="icon-checkbox"></span>';
		}
		else
		{
			if (!$this->filter_cats)
			{
				$order_msg .= JText::_('FLEXI_FCORDER_FC_ORDER_PLEASE_SET_CATEGORY_FILTER');
				$msg_class = 'fc-mssg-inline fc-nobgimage fc-warning';
				$msg_style = 'padding: 4px 8px; margin: 12px 0 6px 0;';
				$msg_icon  = '<span class="icon-warning"></span>';
			}
			else
			{
				$order_msg .= JText::_('FLEXI_FCORDER_FC_ORDER_GROUPING_BY_SELECTED_CATEGORY');
				$msg_class = 'fc-mssg-inline fc-nobgimage fc-success';
				$msg_style = 'padding: 4px 8px; margin: 12px 0 6px 0;';
				$msg_icon  = '<span class="icon-checkbox"></span>';
			}
		}
		
		$order_msg .= '
			<div '.  $fcfilter_attrs . ' style="margin: 0 12px !important;">' . $this->lists['filter_cats'] . ' </div>
			<div '.  $fcfilter_attrs . ' style="margin: 0 12px !important;">' . $this->lists['filter_subcats'] . ' </div>';
		?>

		<div class="fc-filter nowrap_box" id="order_type_selector" style="margin: 8px 0 0 0;">
			<div <?php echo $fcfilter_attrs; ?> >
				<div <?php echo $ordering_type_attrs; ?> > <?php echo JText::_('FLEXI_ORDER_TYPE'); ?></div>
				<?php echo $this->lists['filter_order_type']; ?>
			</div>
		</div>

		<?php if (!empty($order_msg)): ?>
			<div class="clear"></div>
			<div id="fcorder_notes_box" class="<?php echo $msg_class; ?>" style="<?php echo $msg_style; ?> line-height: 28px; max-width: unset;">
				<?php echo $msg_icon; ?> <?php echo $order_msg;?>
			</div>

		<?php endif; ?>
		<div class="fcclear"></div>

	<?php endif; ?>


	<div class="fcclear"></div>

	<table id="adminListTableFCitems" class="adminlist fcmanlist" itemscope itemtype="http://schema.org/WebPage">
	<thead>
		<tr>

			<!--th class="left">
				<?php echo JText::_( 'FLEXI_NUM' ); ?>
			</th-->

			<th class="hideOnDemandClass left nowrap">
				<?php
				echo $this->CanOrder ? $image_ordering_tip : '';
				echo str_replace('_FLEXI_ORDER_', JText::_('FLEXI_ORDER', true), str_replace('_FLEXI_ORDER_</a>', '<span class="icon-menu-2"></span></a>', JHtml::_('grid.sort', '_FLEXI_ORDER_', (!$this->filter_order_type ? 'i.ordering' : 'catsordering'), $this->lists['order_Dir'], $this->lists['order'] )));

				/*if ($this->CanOrder && $this->reOrderingActive) :
					echo flexicontent_html::gridOrderBtn($this->rows, 'filesave.png', $ctrl.'saveorder');
				endif;*/
				?>
				<span class="column_toggle_lbl" style="display:none;"><?php echo JText::_( 'FLEXI_ORDER' ); ?></span>
			</th>

			<th class="left">
				<input type="checkbox" name="checkall-toggle" id="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
				<label for="checkall-toggle" class="green single"></label>
			</th>

			<th class="left hideOnDemandClass">
				<?php echo JHtml::_('grid.sort', 'FLEXI_STATUS', 'i.state', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->filter_state) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('filter_state');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>

			<th class="left hideOnDemandClass">
				<?php echo JHtml::_('grid.sort', 'FLEXI_TITLE', 'i.title', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->search) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('search');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			
			<th class="left hideOnDemandClass">
				<?php echo JHtml::_('grid.sort', 'FLEXI_AUTHOR', 'i.created_by', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->filter_author) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('filter_author');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			
			<th class="left hideOnDemandClass">
				<?php echo JHtml::_('grid.sort', 'FLEXI_LANGUAGE', 'i.language', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->filter_lang) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('filter_lang');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>

		<?php if ( $useAssocs ) : ?>
			<th class="left hideOnDemandClass">
				<?php echo JText::_( 'FLEXI_ASSOCIATIONS' ); ?>
			</th>
		<?php endif; ?>

			<th class="left hideOnDemandClass">
				<?php echo JHtml::_('grid.sort', 'FLEXI_TYPE_NAME', 'type_name', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->filter_type) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('filter_type');document.adminForm.submit();" />
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
				<?php echo JHtml::_('grid.sort', 'FLEXI_ACCESS', 'i.access', $this->lists['order_Dir'], $this->lists['order'] ); ?>
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
				<?php echo JHtml::_('grid.sort',   'FLEXI_CREATED', 'i.created', $this->lists['order_Dir'], $this->lists['order'] ); ?>
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
				<?php echo JHtml::_('grid.sort',   'FLEXI_REVISED', 'i.modified', $this->lists['order_Dir'], $this->lists['order'] ); ?>
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
				<?php echo JHtml::_('grid.sort', 'JGLOBAL_HITS', 'i.hits', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="left hideOnDemandClass">
				<?php echo JHtml::_('grid.sort', 'JGLOBAL_VOTES', 'rating_count', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="left hideOnDemandClass">
				<?php echo JHtml::_('grid.sort', 'JGLOBAL_RATINGS', 'rating', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="left hideOnDemandClass">
				<?php echo JHtml::_('grid.sort', 'JGRID_HEADING_ID', 'i.id', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->filter_id) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('filter_id');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			
		</tr>
	</thead>

	<tbody <?php echo $ordering_draggable && $this->CanOrder && $this->reOrderingActive ? 'id="sortable_fcitems"' : ''; ?> >
		<?php
		$unpublishableFound = false;
		$canCheckinRecords = $user->authorise('core.admin', 'com_checkin');
		
		$date_format = (($date_format = JText::_( 'FLEXI_DATE_FORMAT_FLEXI_ITEMS_J16GE' )) == 'FLEXI_DATE_FORMAT_FLEXI_ITEMS_J16GE') ? "d/m/y H:i" : $date_format;
		
		$total_rows = count($this->rows);
		if (!$total_rows) echo '<tr class="collapsed_row"><td colspan="'.$list_total_cols.'"></td></tr>';  // Collapsed row to allow border styling to apply

		foreach($this->rows as $i => $row)
		{
			$assetName  = 'com_content.article.' . $row->id;
			$isAuthor = $row->created_by == $user->id;

			$row->canCheckin   = $canCheckinRecords || $row->checked_out == 0 || $row->checked_out == $user->id;
			$row->canEdit      = $row->canCheckin && ($user->authorise('core.edit', $assetName)       || ($isAuthor && $user->authorise('core.edit.own', $assetName)));
			$row->canEditState = $row->canCheckin && ($user->authorise('core.edit.state', $assetName) || ($isAuthor && $user->authorise('core.edit.state.own', $assetName)));
			$row->canDelete    = $row->canCheckin && ($user->authorise('core.delete', $assetName)     || ($isAuthor && $user->authorise('core.delete.own', $assetName)));

			$unpublishableFound = $unpublishableFound || ($row->canCheckin && !$row->canEditState);
			$edit_link = 'index.php?option=com_flexicontent&amp;'.$items_task.'edit&amp;view=item&amp;id='. $row->id;
			

			$row_ilayout =  $row->config->get('ilayout') ?  $row->config->get('ilayout') : $row->tconfig->get('ilayout');
			$layout_url = 'index.php?option=com_flexicontent&amp;view=template&amp;type=items&amp;tmpl=component&amp;ismodal=1&amp;folder='. $row_ilayout;
			
			// Set a row language, even if empty to avoid errors
			$lang_default = '*';
			$row->lang = !empty($row->lang) ? $row->lang : $lang_default;
   		?>

		<tr class="<?php echo 'row'.($i % 2); ?>">

			<!--td class="col_rowcount">
				<div class="adminlist-table-row"></div>
				<?php /*echo $this->pagination->getRowOffset( $i );*/ ?>
			</td-->

		<?php if ($this->CanOrder) : ?>

			<td class="order center">
				<?php
					if ($this->reOrderingActive)
					{
						$row_stategrp_prev = @ $stategrps[@$this->rows[$i-1]->state];
						$row_stategrp = @ $stategrps[$this->rows[$i]->state];
						$row_stategrp_next = @ $stategrps[@$this->rows[$i+1]->state];

						$show_orderUp   = @$this->rows[$i-1]->$ord_catid == $this->rows[$i]->$ord_catid && $row_stategrp_prev == $row_stategrp;
						$show_orderDown = $this->rows[$i]->$ord_catid == @$this->rows[$i+1]->$ord_catid && $row_stategrp == $row_stategrp_next;
						$fcorder_lang_separately = true;
						$jorder_lang_separately = true;
						if (
							($this->filter_order_type && $fcorder_lang_separately) ||
							(!$this->filter_order_type && $jorder_lang_separately)
						) {
							$show_orderUp   = $show_orderUp   && @ $this->rows[$i-1]->lang == $this->rows[$i]->lang;
							$show_orderDown = $show_orderDown && $this->rows[$i]->lang == @ $this->rows[$i+1]->lang;
						}
					}
				?>
				<?php if (!$this->reOrderingActive): echo '<span class="icon-move" style="color: #d0d0d0"></span>'; //$drag_handle_html['disabled']; ?>
				<?php elseif ($ordering_draggable): ?>
					<?php
						if ($show_orderUp && $show_orderDown) echo $drag_handle_html['both'];
						else if ($show_orderUp) echo $drag_handle_html['uponly'];
						else if ($show_orderDown) echo $drag_handle_html['downonly'];
						else echo $drag_handle_html['none'];
					?>
				<?php else: ?>
					<span><?php echo $this->pagination->orderUpIcon( $i, $show_orderUp, $ctrl.'orderup', 'Move Up', $this->reOrderingActive ); ?></span>
					<span><?php echo $this->pagination->orderDownIcon( $i, $total_rows, $show_orderDown, $ctrl.'orderdown', 'Move Down', $this->reOrderingActive );?></span>
				<?php endif; ?>

				<?php if ($this->reOrderingActive): ?>
					<input class="fcitem_order_no" type="text" name="order[]" size="5" value="<?php echo $row->$ord_col; ?>" style="text-align: center" />
					<input type="hidden" name="item_cb[]" value="<?php echo $row->id; ?>" />
					<input type="hidden" name="ord_catid[]" value="<?php echo $row->$ord_catid; ?>" />
					<input type="hidden" name="prev_order[]" value="<?php echo $row->$ord_col; ?>" />
					<input type="hidden" name="ord_grp[]" value="<?php echo $show_orderDown ? $ord_grp : $ord_grp++; ?>" />
				<?php endif; ?>
			</td>

		<?php else : ?>

			<td class="center">
				<?php
				echo !$this->reOrderingActive
					? '<span class="icon-move" style="color: #d0d0d0"></span>'
					: '<input class="fcitem_order_no" type="text" name="order[]" size="5" value="' . $row->$ord_col . '" disabled="disabled" />';
				?>
			</td>

		<?php endif; ?>

			<td>
				<?php echo JHtml::_('grid.id', $i, $row->id); ?>
				<label for="cb<?php echo $i; ?>" class="green single"></label>
			</td>

			<td class="col_state" style="padding-right: 8px;">
				<div class="btn-group fc-group fc-items">
					<?php
					$record_url =
						// Route the record URL to an appropriate menu item
						FlexicontentHelperRoute::getItemRoute($row->id.':'.$row->alias, $row->categoryslug, 0, $row)

						// Force language to be switched to the language of the record, thus showing the record (and not its associated translation of current FE language)
						. ( $row->language != '*' ? '&lang=' . substr($row->language, 0, 2) : '' );

					// Build a frontend SEF url
					$record_url = flexicontent_html::getSefUrl($record_url);

					$previewlink = $record_url;

					echo flexicontent_html::statebutton( $row, null, $addToggler = ($this->pagination->limit <= $this->inline_ss_max), 'top', 'btn btn-small' );
					echo JHtml::_('fcitems.featured', $row->featured, $i, $row->canEditState);
					echo '<a '.$attribs_preview.' href="'.$previewlink.'" target="_blank">'.$image_preview.'</a>';
					?>
				</div>
			</td>

			<td class="col_title">
				<?php
				// Display an icon if item has an unapproved latest version, thus needs revising
				echo JHtml::_('fcitems.reviewing_needed', $row, $user, $i);

				// Display a check-in button if: either (a) current user has Global Checkin privilege OR (b) record checked out by current user, otherwise display a lock with no link
				echo JHtml::_('fcitems.checkedout', $row, $user, $i);

				// Display item scheduled / expired icons if item is in published state
				echo JHtml::_('fcitems.scheduled_expired', $row, $user, $i);

				// Display title with no edit link ... if row is not-editable for any reason (no ACL or checked-out by other user)
				if (!$row->canEdit)
				{
					echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8');
				}

				// Display title with edit link ... (row editable and not checked out)
				else
				{
					echo '<a href="'.$edit_link.'" title="'.$edit_item_title.'">'.htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8').'</a>';
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
				if ( !empty($this->lang_assocs[$row->id]) )
				{
					$row_modified = strtotime($row->modified);
					if (!$row_modified)
					{
						$row_modified = strtotime($row->created);
					}

					foreach($this->lang_assocs[$row->id] as $assoc_item)
					{
						// Joomla article manager show also current item, so we will not skip it
						$is_current = $assoc_item->id == $row->id;

						$assoc_modified = strtotime($assoc_item->modified);
						if (!$assoc_modified)
						{
							$assoc_modified = strtotime($assoc_item->created);
						}

						$_link  = 'index.php?option=com_flexicontent&amp;'.$items_task.'edit&amp;cid='. $assoc_item->id;
						$_title = flexicontent_html::getToolTip(
							($is_current ? '' : JText::_( $assoc_modified < $row_modified ? 'FLEXI_EARLIER_THAN_THIS' : 'FLEXI_LATER_THAN_THIS')),
							( !empty($this->langs->{$assoc_item->lang}->imgsrc) ? ' <img src="'.$this->langs->{$assoc_item->lang}->imgsrc.'" alt="'.$assoc_item->lang.'" /> ' : '').
							($assoc_item->lang=='*' ? JText::_("FLEXI_ALL") : $this->langs->{$assoc_item->lang}->name).' <br/> '.
							$assoc_item->title, 0, 1
						);
						
						echo '
						<a class="fc_assoc_translation label label-association ' . $tip_class . ($assoc_modified < $row_modified ? ' fc_assoc_later_mod' : '').'" target="_blank" href="'.$_link.'" title="'.$_title.'" >
							'.($assoc_item->lang=='*' ? JText::_("FLEXI_ALL") : strtoupper($assoc_item->shortcode)).'
						</a>';
					}
				}
				?>
			</td>
			<?php endif ; ?>


			<td class="col_type">
				<?php echo $row->type_name; ?>
			</td>

			<td class="col_edit_layout">
				<?php if ($this->CanTemplates && $row_ilayout) : ?>
				<a <?php echo $attribs_editlayout; ?> href="<?php echo $layout_url; ?>" onclick="var url = jQuery(this).attr('href'); fc_showDialog(url, 'fc_modal_popup_container', 0, 0, 0, 0, {title:'<?php echo $edit_layout; ?>'}); return false;" >
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
				<?php echo $row->canEdit && $this->CanAccLvl
					? flexicontent_html::userlevel('access['.$row->id.']', $row->access, 'onchange="return listItemTask(\'cb'.$i.'\',\'items.access\')"')
					: $row->access_level; ?>
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
				$row_cats  = array();
				$cat_names = array();
				$_maincat = '';
				for ($nn=0; $nn < $nr; $nn++)
				{
					$_item_cat = $row->catids[$nn];
					if ( !isset($this->itemCats[$_item_cat]) ) continue;

					$category = & $this->itemCats[$_item_cat];
					$isMainCat = ((int)$category->id == (int)$row->catid);

					$catClass = ($isMainCat ? 'maincat' : 'secondarycat').
						(($ix==0 && $this->reOrderingActive) ? ' orderingcat' : '');
					
					$catLink	= 'index.php?option=com_flexicontent&amp;'.$cats_task.'edit&amp;cid='. $category->id;
					$title = htmlspecialchars($category->title, ENT_QUOTES, 'UTF-8');
					
					$short_name = StringHelper::strlen($title) > 40  ? StringHelper::substr( $title , 0 , 40) . '...' : $title;
					if (!$isMainCat)
					{
						$row_cats[] = !$this->CanCats ? $short_name : '
							<span class="'.$catClass.'" title="'.$edit_cat_title.'">
								<a href="'.$catLink.'">'.$short_name.'</a>
							</span>';
						$cat_names[] = $short_name;
					}
					else
					{
						$_maincat = !$this->CanCats ? '<span class="badge">' . $short_name . '</span>' : '
						<span class="'.$catClass.'" title="'.$edit_cat_title.'">
							<a href="'.$catLink.'">'.$short_name.'</a>
						</span>';
					}
					$ix++;
				}
				echo $_maincat;
				echo count($row_cats) ? '
					<span class="btn btn-mini hasTooltip nowrap_box" onclick="jQuery(this).next().toggle(400);" title="'.flexicontent_html::getToolTip(JText::_('FLEXI_SECONDARY_CATEGORIES'), '<ul class="fc_plain"><li>'.implode('</li><li>', $cat_names).'</li></ul>', 0, 1).'">
						'.count($row_cats).' <i class="icon-tree-2"></i>
					</span>
					<div class="fc_assignments_box fc_cats">' : '';
				echo count($row_cats) > 8
					? implode(', ', $row_cats)
					: (count($row_cats) ? '<ul class="fc_plain"><li>' . implode('</li><li>', $row_cats) . '</li></ul>' : '');
				echo count($row_cats) ? '</div>' : '';
				?>
			</td>

			<td class="col_tag">
				<?php
					$row_tags  = array();
					$tag_names = array();
					foreach ($row->tagids as $key => $_itag)
					{
						if ( isset($this->itemTags[$_itag]) )
						{
							$row_tags[] = ' <span class="itemtag">'.$this->itemTags[$_itag]->name.'</span> ';
							$tag_names[] = $this->itemTags[$_itag]->name;
						}
					}
					echo count($row_tags) ? '
						<span class="btn btn-mini hasTooltip nowrap_box" onclick="jQuery(this).next().toggle(400);" title="'.flexicontent_html::getToolTip(JText::_('FLEXI_TAGS'), '<ul class="fc_plain"><li>'.implode('</li><li>', $tag_names).'</li></ul>', 0, 1).'">
							'.count($row_tags).' <i class="icon-tags"></i>
						</span>
						<div class="fc_assignments_box fc_tags">' : '';
					echo count($row_tags) > 8
						? implode(', ', $row_tags)
						: (count($row_tags) ? '<ul class="fc_plain"><li>' . implode('</li><li>', $row_tags) . '</li></ul>' : '');
					echo count($row_tags) ? '</div>' : '';
				?>
			</td>

			<td class="col_created">
				<?php echo JHtml::_('date',  $row->created, $date_format ); ?>
			</td>

			<td class="col_revised">
				<?php echo ($row->modified != $_NULL_DATE_ && $row->modified != $row->created) ? JHtml::_('date', $row->modified, $date_format) : $_NEVER_; ?>
			</td>

			<td>
				<?php echo $row->hits; ?>
			</td>

			<td>
				<?php echo $row->rating_count; ?>
			</td>

			<td>
				<?php echo sprintf('%.1f', (float) $row->rating); ?>%
			</td>

			<td class="col_id">
				<?php echo $row->id; ?>
			</td>

		</tr>
		<?php
		}
		if ($unpublishableFound)
		{
			$ctrl_task = 'items.approval';
			JToolbarHelper::spacer();
			JToolbarHelper::divider();
			JToolbarHelper::spacer();
			JToolbarHelper::custom($ctrl_task, 'apply.png', 'apply.png', 'FLEXI_APPROVAL_REQUEST');
		}
		JToolbarHelper::spacer();
		JToolbarHelper::spacer();
		?>
	</tbody>

	<tfoot>
		<tr>
			<td colspan="<?php echo $list_total_cols; ?>" style="text-align: left;">
				<?php echo $pagination_footer; ?>
			</td>
		</tr>
		
		<tr>
			<td colspan="<?php echo $list_total_cols; ?>" style="margin: 0 auto !important; background-color: white;">
				<table class="admintable" style="margin: 0 auto !important; background-color: unset; font-size: 12px">
					<tr>
						<td><span class="icon-publish" style="font-size: 16px;"></span></td>
						<td class="left"><?php echo JText::_( 'FLEXI_PUBLISHED_DESC' ); ?></td>
						<td><span class="icon-unpublish" style="font-size: 16px;"></span></td>
						<td class="left"><?php echo JText::_( 'FLEXI_UNPUBLISHED_DESC' ); ?></td>
						<td><span class="icon-archive" style="font-size: 16px;"></span></td>
						<td class="left"><?php echo JText::_( 'FLEXI_ARCHIVED' ); ?></td>
						<td><span class="icon-trash" style="font-size: 16px;"></span></td>
						<td class="left"><?php echo JText::_( 'FLEXI_TRASHED' ); ?></td>
					</tr><tr>
						<td><span class="icon-checkmark-2" style="font-size: 16px;"></span></td>
						<td class="left"><?php echo JText::_( 'FLEXI_NOT_FINISHED_YET' ); ?> (<?php echo JText::_( 'FLEXI_PUBLISHED' ); ?>)</td>
						<td><span class="icon-clock" style="font-size: 16px;"></span></td>
						<td class="left"><?php echo JText::_( 'FLEXI_NEED_TO_BE_APPROVED' ); ?> (<?php echo JText::_( 'FLEXI_UNPUBLISHED_DESC' ); ?>)</td>
						<td><span class="icon-pencil-2" style="font-size: 16px;"></span></td>
						<td class="left"><?php echo JText::_( 'FLEXI_TO_WRITE_DESC' ); ?> (<?php echo JText::_( 'FLEXI_UNPUBLISHED_DESC' ); ?>)</td>
						<td></td>
						<td class="left"></td>
					</tr>
				</table>
			</td>
		</tr>
	</tfoot>

	</table>

	<div class="fcclear"></div>

	
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="items" />
	<input type="hidden" name="view" value="items" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="newstate" id="newstate" value="" />
	<input type="hidden" id="filter_order" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" id="filter_order_Dir" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<input type="hidden" name="fcform" value="1" />
	<?php echo JHtml::_( 'form.token' ); ?>

	</form>

		<!-- fc_perf -->
		</div>  <!-- j-main-container -->
	</div>  <!-- spanNN -->
</div>  <!-- row -->
</div><!-- #flexicontent end -->
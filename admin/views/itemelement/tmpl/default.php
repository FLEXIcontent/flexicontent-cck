<?php
/**
 * @version 1.5 stable $Id: default.php 1267 2012-05-07 10:55:21Z ggppdk $
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
$app = JFactory::getApplication();
$option = $app->input->get('option');
global $globalcats;

$tip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
$btn_class = FLEXI_J30GE ? 'btn' : 'fc_button fcsimple';
?>

<script>
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
	delFilter('search'); delFilter('filter_type'); delFilter('filter_state');
	delFilter('filter_cats'); delFilter('filter_author'); delFilter('filter_id');
	delFilter('startdate'); delFilter('enddate'); delFilter('filter_lang');
	delFilter('filter_tag'); delFilter('filter_access');
	jQuery('#filter_subcats').val('1');
	jQuery('.fc_field_filter').val('');
}
</script>

<div id="flexicontent" class="flexicontent">

<form action="index.php?option=com_flexicontent&amp;view=itemelement&amp;tmpl=component&object=<?= JRequest::getVar('object',''); ?>" method="post" name="adminForm" id="adminForm">

	<div id="fc-filters-header">
		<span class="fc-filter nowrap_box">
			<span class="filter-search btn-group">
				<input type="text" name="search" id="search" placeholder="<?php echo JText::_( 'FLEXI_SEARCH' ); ?>" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="inputbox" />
			</span>
			<span class="btn-group hidden-phone">
				<button title="<?php echo JText::_('FLEXI_APPLY_FILTERS'); ?>" class="<?php echo $btn_class; ?>" onclick="document.adminForm.limitstart.value=0; Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-search"></i>' : JText::_('FLEXI_GO'); ?></button>
				<button title="<?php echo JText::_('FLEXI_RESET_FILTERS'); ?>" class="<?php echo $btn_class; ?>" onclick="document.adminForm.limitstart.value=0; delAllFilters(); Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-remove"></i>' : JText::_('FLEXI_CLEAR'); ?></button>
			</span>
		</span>
		
		<span class="fc-filter nowrap_box">
			<span class="limit nowrap_box" style="display: inline-block;">
				<label class="label">
					<?php echo JText::_('JGLOBAL_DISPLAY_NUM'); ?>
				</label>
				<?php
				$pagination_footer = $this->pagination->getListFooter();
				if (strpos($pagination_footer, '"limit"') === false) echo $this->pagination->getLimitBox();
				?>
			</span>
			
			<span class="fc_item_total_data nowrap_box badge badge-info">
				<?php echo @$this->resultsCounter ? $this->resultsCounter : $this->pagination->getResultsCounter(); // custom Results Counter ?>
			</span>
			
			<?php if (($getPagesCounter = $this->pagination->getPagesCounter())): ?>
			<span class="fc_pages_counter nowrap_box fc-mssg-inline fc-info fc-nobgimage">
				<?php echo $getPagesCounter; ?>
			</span>
			<?php endif; ?>
		</span>
	</div>
	
	<div id="fc-filters-box">
		<span class="fc-filter nowrap_box">
			<?php echo $this->lists['filter_type'];	?>
		</span>
		
		<span class="fc-filter nowrap_box">
		<?php echo $this->lists['filter_lang']; ?>
		</span>
		
		<span class="fc-filter nowrap_box">
		<?php echo $this->lists['filter_author']; ?>
		</span>
		
		<span class="fc-filter nowrap_box">
		<?php echo $this->lists['filter_cats']; ?>
		</span>
		
		<span class="fc-filter nowrap_box">
		<?php echo $this->lists['filter_state']; ?>
		</span>
		
		<span class="fc-filter nowrap_box">
		<?php echo $this->lists['filter_access']; ?>
		</span>
	</div>



	<table class="adminlist">
	<thead>
		<tr>
			<th><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			<th class="title"><?php echo JHtml::_('grid.sort', 'FLEXI_TITLE', 'i.title', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="" nowrap="nowrap"><?php echo JHtml::_('grid.sort', 'FLEXI_TYPE_NAME', 'type_name', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="" nowrap="nowrap"><?php echo JHtml::_('grid.sort', 'FLEXI_MAIN_CATEGORY', 'c.title', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="" nowrap="nowrap"><?php echo JHtml::_('grid.sort', 'FLEXI_AUTHOR', 'author', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="" nowrap="nowrap"><?php echo JText::_( 'FLEXI_STATE' ); ?></th>
			<th width=""><?php echo JHtml::_('grid.sort', 'FLEXI_ACCESS', 'i.access', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="" nowrap="nowrap"><?php echo JHtml::_('grid.sort', 'FLEXI_LANGUAGE', 'lang', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="" nowrap="nowrap"><?php echo JHtml::_('grid.sort', 'FLEXI_ID', 'i.id', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
		</tr>
	</thead>

	<tbody>
		<?php
		$k = 0;
		for ($i=0, $n=count($this->rows); $i < $n; $i++) {
			$row = $this->rows[$i];
			
				if ( $row->state == 1 ) {
					$img = 'tick.png';
					$alt = JText::_( 'FLEXI_PUBLISHED' );
					$state = 1;
				} else if ( $row->state == 0 ) {
					$img = 'publish_x.png';
					$alt = JText::_( 'FLEXI_UNPUBLISHED' );
					$state = 0;
				} else if ( $row->state == -1 ) {
					$img = 'disabled.png';
					$alt = JText::_( 'FLEXI_ARCHIVED' );
					$state = -1;
				} else if ( $row->state == -3 ) {
					$img = 'publish_r.png';
					$alt = JText::_( 'FLEXI_PENDING' );
					$state = -3;
				} else if ( $row->state == -4 ) {
					$img = 'publish_y.png';
					$alt = JText::_( 'FLEXI_TO_WRITE' );
					$state = -4;
				} else if ( $row->state == -5 ) {
					$img = 'publish_g.png';
					$alt = JText::_( 'FLEXI_IN_PROGRESS' );
					$state = -5;
				} else {
					$img = '';
				}
   		?>
		<tr class="<?php echo "row$k"; ?>">
			
			<td>
				<div class="adminlist-table-row"></div>
				<?php echo $this->pagination->getRowOffset( $i ); ?>
			</td>
			
			<td align="left">
				<?php
					$parentcats_ids = isset($globalcats[$row->catid]) ? $globalcats[$row->catid]->ancestorsarray : array();
					$pcpath = array();
					foreach($parentcats_ids as $pcid)
					{
						$pcpath[] = $globalcats[$pcid]->title;
					}
					$pcpath = implode($pcpath, ' / ');
				?>
				<span class="<?php echo $tip_class; ?>" title="<?php echo JHtml::tooltipText(JText::_( 'FLEXI_SELECT' ), $row->title . '<br/><br/>' .JText::_('FLEXI_MAIN_CATEGORY'). '<br/>' . $pcpath, 0, 1); ?>">
				<?php if(JRequest::getVar('object','')==''): ?>
					<a style="cursor:pointer" onclick="window.parent.fcSelectItem('<?php echo $row->id; ?>', '<?php echo $this->filter_cats ? $this->filter_cats : $row->catid; ?>', '<?php echo str_replace( array("'", "\""), array("\\'", ""), $row->title ); ?>');">
				<?php else: ?>
					<a style="cursor:pointer" onclick="window.parent.jSelectArticle('<?php echo $row->id; ?>', '<?php echo str_replace( array("'", "\""), array("\\'", ""), $row->title ); ?>', '<?php echo JRequest::getVar('object',''); ?>');">
				<?php endif; ?>
						<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
					</a>
				</span>
			</td>

			<td align="center" class="col_type"><?php echo $row->type_name; ?></td>
			
			<td align="left" class="col_cat">
				<?php echo $globalcats[$row->catid]->title; ?>
			</td>

			<td align="center"><?php echo $row->author; ?></td>
			
			<td align="center">
				<?php if ($img) : ?>
					<img src="<?php  echo $app->isAdmin() ? "../" : ""; ?>components/com_flexicontent/assets/images/<?php echo $img;?>" width="16" height="16" border="0" alt="<?php echo $alt; ?>" title="<?php echo $alt; ?>" />
				<?php endif; ?>
			</td>
			
			<td align="center"><?php echo $row->access_level; ?></td>
			
			<td align="center" class="col_lang">
				
				<?php if ( 0 && !empty($row->lang) && !empty($this->langs->{$row->lang}->imgsrc) ) : ?>
					<img class="<?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip(JText::_( 'FLEXI_LANGUAGE' ), ($row->lang=='*' ? JText::_("All") : (!empty($row->lang) ? $this->langs->{$row->lang}->name : '')), 0, 1); ?>"
						src="<?php echo $this->langs->{$row->lang}->imgsrc; ?>" alt="<?php echo $row->lang; ?>" />
				<?php elseif( !empty($row->lang) ) : ?>
					<?php echo ($row->lang=='*'  ?  JText::_("All")  :  (!empty($row->lang) ? $this->langs->{$row->lang}->name : '')); ?>
				<?php endif; ?>
				
			</td>
			
			<td align="center"><?php echo $row->id; ?></td>
			
		</tr>
		<?php
			$k = 1 - $k;
		}
		?>
	</tbody>

	<tfoot>
		<tr>
			<td colspan="6">
				<?php echo $this->pagination->getListFooter(); ?>
			</td>
		</tr>
	</tfoot>

	</table>
	<input type="hidden" name="task" value="" />
	<input type="hidden" id="filter_order" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" id="filter_order_Dir" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<?php echo $this->assocs_id ? '
		<input type="hidden" name="assocs_id" value="'.$this->assocs_id.'" />'
		: ''; ?>
</form>
</div>
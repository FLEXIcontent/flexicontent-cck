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

// This ordering data are needed for J2.5 + ordering
if (FLEXI_J16GE) {
	$listOrder  = $this->lists['order'];
	$listDirn   = $this->lists['order_Dir'];
	$saveOrder  = ($listOrder == 'c.lft' && $listDirn == 'asc');
}

$user      = JFactory::getUser();
$cparams   = JComponentHelper::getParams( 'com_flexicontent' );
$autologin = '';//$cparams->get('autoflogin', 1) ? '&amp;fcu='.$user->username . '&amp;fcp='.$user->password : '';

$attribs_preview = ' style="float:right;" class="hasTip" title="'.JText::_('FLEXI_PREVIEW').':: Click to display the frontend view of this category in a new browser window" ';
$attribs_rsslist = ' style="float:right;" class="hasTip" title="'.JText::_('FLEXI_FEED')   .':: Click to display the frontend RSS listing of this category in a new browser window" ';

$image_preview = FLEXI_J16GE ?
	JHTML::image( 'components/com_flexicontent/assets/images/'.'monitor_go.png', JText::_('FLEXI_PREVIEW'),  $attribs_preview) :
	JHTML::_('image.site', 'monitor_go.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_('FLEXI_PREVIEW'), $attribs_preview) ;
$image_rsslist = FLEXI_J16GE ?
	JHTML::image( FLEXI_ICONPATH.'livemarks.png', JText::_('FLEXI_FEED'), $attribs_rsslist ) :
	JHTML::_('image.site', 'livemarks.png', '../images/M_images/', NULL, NULL, JText::_('FLEXI_FEED'), $attribs_rsslist ) ;

$image_flag_path = !FLEXI_J16GE ? "../components/com_joomfish/images/flags/" : "../media/mod_languages/images/";
$infoimage  = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/lightbulb.png', JText::_( 'FLEXI_NOTES' ) );
?>

<div class="flexicontent">
<form action="index.php" method="post" name="adminForm" id="adminForm">

	<table class="adminform">
		<tr>
			<td align="left">
				<label class="label"><?php echo JText::_( 'FLEXI_SEARCH' ); ?></label>
				<input type="text" name="search" id="search" value="<?php echo $this->lists['search']; ?>" class="text_area" onchange="document.adminForm.submit();" />
				<div id="fc-filter-buttons">
					<button class="fc_button fcsimple" onclick="this.form.submit();"><?php echo JText::_( 'FLEXI_GO' ); ?></button>
					<button class="fc_button fcsimple" onclick="this.form.getElementById('search').value='';this.form.submit();"><?php echo JText::_( 'FLEXI_RESET' ); ?></button>
				</div>
			</td>
			<td nowrap="nowrap">
				<div class="limit" style="display: inline-block;">
					<?php echo JText::_(FLEXI_J16GE ? 'JGLOBAL_DISPLAY_NUM' : 'DISPLAY NUM') . str_replace('id="limit"', 'id="limit_top"', $this->pagination->getLimitBox()); ?>
				</div>
				
				<span class="fc_item_total_data fc_nice_box" style="margin-right:10px;" >
					<?php echo @$this->resultsCounter ? $this->resultsCounter : $this->pagination->getResultsCounter(); // custom Results Counter ?>
				</span>
				
				<span class="fc_pages_counter">
					<?php echo $this->pagination->getPagesCounter(); ?>
				</span>
			</td>
			<td style="text-align:right;">
				<div class="filter-select fltrt">
					<?php echo $this->lists['cats']; ?>
					<?php echo $this->lists['level']; ?>
					<?php echo $this->lists['state']; ?>
					<?php echo $this->lists['access']; ?>
				  <?php if (FLEXI_J16GE) echo $this->lists['language']; ?>
				</div>
			</td>
		</tr>
	</table>

	<table class="adminlist" cellspacing="1">
	<thead>
		<tr>
			<th width="5"><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			<th width="5"><input type="checkbox" name="toggle" value="" onclick="<?php echo FLEXI_J30GE ? 'Joomla.checkAll(this);' : 'checkAll('.count( $this->rows).');'; ?>" /></th>
			<th width="1%" nowrap="nowrap">&nbsp;</th>
			<th width="1%" nowrap="nowrap">&nbsp;</th>
			<th class="title"><?php echo JHTML::_('grid.sort', 'FLEXI_CATEGORY', 'c.title', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="20%"><?php echo JHTML::_('grid.sort', 'FLEXI_ALIAS', 'c.alias', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th><?php echo JText::_( 'FLEXI_TEMPLATE' ); ?></th>
			<th width="10%"><?php echo JHTML::_('grid.sort', 'FLEXI_ITEMS_ASSIGNED', 'nrassigned', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JText::_( 'FLEXI_PUBLISHED' ); ?></th>
			<th width="7%"><?php echo JHTML::_('grid.sort', 'FLEXI_ACCESS', 'c.access', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="90">
				<?php echo JHTML::_('grid.sort', 'FLEXI_REORDER', 'c.ordering', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php echo $this->ordering ? JHTML::_('grid.order', $this->rows, 'filesave.png', 'saveorder' ) : ''; ?>
			</th>
			<th width="1%" nowrap="nowrap">
				<?php echo JHTML::_('grid.sort', 'FLEXI_ID', 'c.id', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>
		</tr>
	</thead>

	<tfoot>
		<tr>
			<td colspan="13">
				<?php echo $this->pagination->getListFooter(); ?>
			</td>
		</tr>
	</tfoot>

	<tbody>
		<?php
		$k = 0;
		$i = 0;
		$n = count($this->rows);
		if (FLEXI_J16GE) {
			$originalOrders = array();
			$extension	= 'com_content';
		}
				
		if (FLEXI_J16GE) {
			$canCheckinRecords = $user->authorise('core.admin', 'checkin');
		} else if (FLEXI_ACCESS) {
			$canCheckinRecords = ($user->gid < 25) ? FAccess::checkComponentAccess('com_checkin', 'manage', 'users', $user->gmid) : 1;
		} else {
			$canCheckinRecords = $user->gid >= 24;
		}
		foreach ($this->rows as $row)
		{
			if (FLEXI_J16GE) {
				$canEdit		= $user->authorise('core.edit', $extension.'.category.'.$row->id);
				$canEditOwn	= $user->authorise('core.edit.own', $extension.'.category.'.$row->id) && $row->created_user_id == $user->get('id');
				$canEditState			= $user->authorise('core.edit.state', $extension.'.category.'.$row->id);
				$canEditStateOwn	= $user->authorise('core.edit.state.own', $extension.'.category.'.$row->id) && $row->created_user_id==$user->get('id');
				$recordAvailable	= ($canCheckinRecords && $row->checked_out == $user->id) || !$row->checked_out;
				$canChange		= ($canEditState || $canEditStateOwn ) && $recordAvailable;
			} else {
				$canEdit		= 1;  // No category edit ACL in J1.5
				$canEditOwn	= 1;  // No category edit.own ACL in J1.5
			}
			
			if (FLEXI_J16GE) {
				$published		= JHTML::_('jgrid.published', $row->published, $i, 'categories.', $canChange );
			} else {
				$published 	= JHTML::_('grid.published', $row, $i );
			}
			
			if (FLEXI_J16GE) {
				$orderkey = array_search($row->id, $this->ordering[$row->parent_id]);
				$link	= 'index.php?option=com_flexicontent&amp;task=category.edit&amp;cid[]='. $row->id;
			} else {
				$link	= 'index.php?option=com_flexicontent&amp;controller=categories&amp;task=edit&amp;cid[]='. $row->id;
			}
			
			if (FLEXI_J16GE) {
				if (($canEdit || $canEditOwn) && $this->perms->CanAccLvl) {
					$access = flexicontent_html::userlevel('access['.$row->id.']', $row->access, 'onchange="return listItemTask(\'cb'.$i.'\',\'categories.access\')"');
				} else {
					$access = $this->escape($row->access_level);
				}
			} else if (FLEXI_ACCESS) {
				if (($canEdit || $canEditOwn) && $this->perms->CanAccLvl) {
					$access = FAccess::accessswitch('category', $row, $i);
				} else {
					$access = FAccess::accessswitch('category', $row, $i, 'content', 1);
				}
			} else {
				$access 	= JHTML::_('grid.access', $row, $i );
			}
			
			$checked 	= @ JHTML::_('grid.checkedout', $row, $i );
			$items		= 'index.php?option=com_flexicontent&amp;view=items&amp;filter_cats='. $row->id;
   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td><?php echo $this->pagination->getRowOffset( $i ); ?></td>
			<td width="7"><?php echo $checked; ?></td>
			<td width="1%" >
				<?php
				$cat_link    = str_replace('&', '&amp;', FlexicontentHelperRoute::getCategoryRoute($row->id));
				$cat_link    = JRoute::_(JURI::root().$cat_link, $xhtml=false);  // xhtml to false we do it manually above (at least the ampersand) also it has no effect because we prepended the root URL ?
				$previewlink = $cat_link . $autologin;
				echo '<a class="preview" href="'.$previewlink.'" target="_blank">'.$image_preview.'</a>';
				?>
			</td>
			<td width="1%" >
				<?php
				$rsslink     = $cat_link . '&amp;format=feed&amp;type=rss';
				echo '<a class="preview" href="'.$rsslink.'" target="_blank">'.$image_rsslist.'</a>';
				?>
			</td>
			<td align="left" class="col_title">
				<?php
				if (FLEXI_J16GE) {
					if ($row->level>1) echo str_repeat('.&nbsp;&nbsp;&nbsp;', $row->level-1)."<sup>|_</sup>";
				} else {
					echo $row->treename.' ';
				}
				
				// Display an icon with checkin link, if current user has checked out current item
				if ($row->checked_out) {
					// Record check-in is allowed if either (a) current user has Global Checkin privilege OR (a) record checked out by current user
					$canCheckin = $canCheckinRecords || $row->checked_out == $user->id;
					if ($canCheckin) {
						//if (FLEXI_J16GE && $row->checked_out == $user->id) echo JHtml::_('jgrid.checkedout', $i, $row->editor, $row->checked_out_time, 'categories.', $canCheckin);
						$task_str = FLEXI_J16GE ? 'categories.checkin' : 'checkin';
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
					<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_CATEGORY', true );?>::<?php echo $row->alias; ?>">
						<a href="<?php echo $link; ?>">
						<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
						</a>
					</span>
				<?php
				}
				?>
				
				<?php	if (!empty($row->note)) : /* Display J1.6+ category note in a tooltip */ ?>
					<span class="hasTip" title="<?php echo JText::_ ( 'FLEXI_NOTES', true ); ?>::<?php echo $this->escape($row->note);?>">
						<?php echo $infoimage; ?>
					</span>
				<?php endif; ?>
				
			</td>
			
			<td>
				<?php
				if (JString::strlen($row->alias) > 25) {
					echo JString::substr( htmlspecialchars($row->alias, ENT_QUOTES, 'UTF-8'), 0 , 25).'...';
				} else {
					echo htmlspecialchars($row->alias, ENT_QUOTES, 'UTF-8');
				}
				?>
			</td>
			<td align="center">
				<?php echo ($row->config->get('clayout') ? $row->config->get('clayout') : "blog <sup>[1]</sup>") ?>
			</td>
			<td align="center">
				<?php echo $row->nrassigned?>
				<a href="<?php echo $items; ?>">
				[<?php echo JText::_( 'FLEXI_VIEW_ITEMS' );?>]
				</a>
			</td>
			<td align="center">
				<?php echo $published; ?>
			</td>
			<td align="center">
				<?php echo $access; ?>
			</td>
			<td class="order">
				<span><?php echo $this->pagination->orderUpIcon( $i, true, 'orderup', 'Move Up', $this->ordering ); ?></span>
				<span><?php echo $this->pagination->orderDownIcon( $i, $n, true, 'orderdown', 'Move Down', $this->ordering );?></span>

				
				<?php $disabled = $this->ordering ?  '' : '"disabled=disabled"'; ?>
				<input type="text" name="order[]" size="5" value="<?php echo $row->ordering; ?>" <?php echo $disabled; ?> class="text_area" style="text-align: center" />
			</td>
			<td align="center"><?php echo $row->id; ?></td>
		</tr>
		<?php 
			$k = 1 - $k;
			$i++;
		} 
		?>
	</tbody>

	</table>
	
	<sup>[1]</sup> Params not saved yet, default values will be used.
	
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="categories" />
	<input type="hidden" name="view" value="categories" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<?php echo JHTML::_( 'form.token' ); ?>
</form>
</div>
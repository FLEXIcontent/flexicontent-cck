<?php
/**
 * @version 1.5 stable $Id: default.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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

defined( '_JEXEC' ) or die( 'Restricted access' );

echo $this->pane->startPane( 'stat-pane' );
echo $this->pane->startPanel( JText::_( 'FLEXI_GENERAL_STATS' ), 'general' );
?>
<table border="0">
<tr>
<td>
<div class="cssbox">
	<div class="cssbox_head">
		<h2><?php echo JText::_( 'FLEXI_GENERAL_STATS' ); ?></h2>
	</div>
	<div class="cssbox_body">
		<table class="adminlist">
			<thead>
				<tr>
					<th><?php echo JText::_( 'FLEXI_TYPE' ); ?></th>
					<th><?php echo JText::_( 'FLEXI_COUNT' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<tr>
				<td>
					<?php echo JText::_( 'FLEXI_TOTAL_NR_ITEMS' ); ?>
				</td>
				<td align="center">
					<strong><?php echo $this->genstats[0]; ?></strong>
				</td>
			</tr>
			<tr>
				<td>
					<?php echo JText::_( 'FLEXI_TOTAL_NR_CATEGORIES' ); ?>
				</td>
				<td align="center">
					<strong><?php echo $this->genstats[1]; ?></strong>
				</td>
			</tr>
			<tr>
				<td>
					<?php echo JText::_( 'FLEXI_TOTAL_NR_TAGS' ); ?>
				</td>
				<td align="center">
					<strong><?php echo $this->genstats[2]; ?></strong>
				</td>
			</tr>
			<tr>
				<td>
					<?php echo JText::_( 'FLEXI_TOTAL_NR_FILES' ); ?>
				</td>
				<td align="center">
					<strong><?php echo $this->genstats[3]; ?></strong>
				</td>
			</tr>
		</table>
	</div>
</div>
</td>
<td>
<div class="cssbox">
	<div class="cssbox_head">
		<h2><?php echo JText::_( 'FLEXI_MOST_POPULAR' ); ?></h2>
	</div>
	<div class="cssbox_body">
		<table class="adminlist">
			<thead>
				<tr>
					<th><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
					<th><?php echo JText::_( 'FLEXI_HITS' ); ?></th>
					<th><?php echo JText::_( 'FLEXI_RATING' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$k = 0;
				for ($i=0, $n=count($this->popular); $i < $n; $i++) {
				$row = $this->popular[$i];
				$link 		= 'index.php?option=com_flexicontent&amp;task=items.edit&amp;cid[]='. $row->id;
				?>
				<tr>
					<td width="65%">
						<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' ); ?>::<?php echo $row->title; ?>">
							<a href="<?php echo $link; ?>">
								<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
							</a>
						</span>
					</td>
					<td width="1%" align="center">
						<strong><?php echo $row->hits; ?></strong>
					</td>
					<td width="34%">
						<strong><?php echo flexicontent_html::ratingbar( $row ); ?></strong>
					</td>
				</tr>
				<?php $k = 1 - $k; } ?>
			</tbody>
		</table>
	</div>
</div>
</td>
<tr>
<td>
<div class="cssbox">
	<div class="cssbox_head">
		<h2><?php echo JText::_( 'FLEXI_ITEM_STATES' ); ?></h2>
	</div>
	<div class="cssbox_body">
		<img src="http://chart.apis.google.com/chart?chs=450x150&amp;chd=t:<?php echo $this->statestats['values']; ?>&amp;cht=p3&amp;chl=<?php echo $this->statestats['labels']; ?>" alt="<?php echo JText::_( 'FLEXI_ITEM_STATES_CHART' ); ?>" />
	</div>
</div>
</td>
<td>
<div class="cssbox">
	<div class="cssbox_head">
		<h2><?php echo JText::_( 'FLEXI_MOST_FAVOURED' ); ?></h2>
	</div>
	<div class="cssbox_body">
		<table class="adminlist">
		<thead>
				<tr>
					<th><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
					<th><?php echo JText::_( 'FLEXI_COUNT' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
			$k = 0;
			for ($i=0, $n=count($this->favoured); $i < $n; $i++) {
			$row = $this->favoured[$i];
			$link 		= 'index.php?option=com_flexicontent&amp;task=items.edit&amp;cid[]='. $row->id;
			?>
			<tr>
				<td>
					<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' );?>::<?php echo $row->title; ?>">
						<a href="<?php echo $link; ?>">
							<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
						</a>
					</span>
				</td>
				<td align="center">
					<strong><?php echo $row->favnr; ?></strong>
				</td>
			</tr>
			<?php $k = 1 - $k; } ?>
		</table>
	</div>
</div>
</td>
</tr>
</table>
<?php
echo $this->pane->endPanel();
echo $this->pane->startPanel( JText::_( 'FLEXI_RATING_STATS' ), 'ratings' );
?>
<table border="0">
<tr>
<td>
<div class="cssbox">
	<div class="cssbox_head">
		<h2><?php echo JText::_( 'FLEXI_VOTE_STATS' ); ?></h2>
	</div>
	<div class="cssbox_body">
		<img src="http://chart.apis.google.com/chart?chs=450x150&amp;chd=t:<?php echo $this->votesstats['values']; ?>&amp;cht=p3&amp;chl=<?php echo $this->votesstats['labels']; ?>" alt="<?php echo JText::_( 'FLEXI_ITEM_VOTES_CHART' ); ?>" />
	</div>
</div>
</td>
<td>
<div class="cssbox">
	<div class="cssbox_head">
		<h2><?php echo JText::_( 'FLEXI_BEST_RATED' ); ?></h2>
	</div>
	<div class="cssbox_body">
		<table class="adminlist">
		<thead>
				<tr>
					<th><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
					<th><?php echo JText::_( 'FLEXI_RATING' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
			$k = 0;
			for ($i=0, $n=count($this->rating); $i < $n; $i++) {
			$row = $this->rating[$i];
			$link 		= 'index.php?option=com_flexicontent&amp;task=items.edit&amp;cid[]='. $row->id;
			?>
			<tr>
				<td>
					<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' );?>::<?php echo $row->title; ?>">
						<a href="<?php echo $link; ?>">
							<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
						</a>
					</span>
				</td>
				<td>
					<strong><?php echo flexicontent_html::ratingbar( $row ); ?></strong>
				</td>
			</tr>
			<?php $k = 1 - $k; } ?>
		</table>
	</div>
</div>
</td>
</tr>
<tr>
<td>
<div class="cssbox">
	<div class="cssbox_head">
		<h2><?php echo JText::_( 'FLEXI_WORST_RATED' ); ?></h2>
	</div>
	<div class="cssbox_body">
		<table class="adminlist">
		<thead>
				<tr>
					<th><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
					<th><?php echo JText::_( 'FLEXI_RATING' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
			$k = 0;
			for ($i=0, $n=count($this->worstrating); $i < $n; $i++) {
			$row = $this->worstrating[$i];
			$link 		= 'index.php?option=com_flexicontent&amp;task=items.edit&amp;cid[]='. $row->id;
			?>
			<tr>
				<td>
					<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' );?>::<?php echo $row->title; ?>">
						<a href="<?php echo $link; ?>">
							<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
						</a>
					</span>
				</td>
				<td>
					<strong><?php echo flexicontent_html::ratingbar( $row ); ?></strong>
				</td>
			</tr>
			<?php $k = 1 - $k; } ?>
		</table>
	</div>
</div>
</td>
</tr>
</table>
<?php
echo $this->pane->endPanel();
echo $this->pane->startPanel( JText::_( 'FLEXI_USER_STATS' ), 'users' );
?>
<table border="0">
<tr>
<td>
<div class="cssbox">
	<div class="cssbox_head">
		<h2><?php echo JText::_( 'FLEXI_TOP_CONTRIBUTORS' ); ?></h2>
	</div>
	<div class="cssbox_body">
		<table class="adminlist">
		<thead>
				<tr>
					<th><?php echo JText::_( 'FLEXI_USER' ); ?></th>
					<th><?php echo JText::_( 'FLEXI_#' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
			$k = 0;
			for ($i=0, $n=count($this->creators); $i < $n; $i++) {
			$row = $this->creators[$i];
			$link 		= 'index.php?option=com_users&amp;view=user&amp;task=edit&amp;cid[]='. $row->id;
			?>
			<tr>
				<td>
					<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_USER' );?>::<?php echo $row->username; ?>">
						<a href="<?php echo $link; ?>">
							<?php echo htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8').' ('.htmlspecialchars($row->username, ENT_QUOTES, 'UTF-8').')'; ?>
						</a>
					</span>
				</td>
				<td align="center">
					<strong><?php echo $row->counter; ?></strong>
				</td>
			</tr>
			<?php $k = 1 - $k; } ?>
		</table>
	</div>
</div>
</td>
<td>
<div class="cssbox">
	<div class="cssbox_head">
		<h2><?php echo JText::_( 'FLEXI_TOP_EDITORS' ); ?></h2>
	</div>
	<div class="cssbox_body">
		<table class="adminlist">
		<thead>
				<tr>
					<th><?php echo JText::_( 'FLEXI_USER' ); ?></th>
					<th><?php echo JText::_( 'FLEXI_#' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
			$k = 0;
			for ($i=0, $n=count($this->editors); $i < $n; $i++) {
			$row = $this->editors[$i];
			$link 		= 'index.php?option=com_users&amp;view=user&amp;task=edit&amp;cid[]='. $row->id;
			?>
			<tr>
				<td>
					<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_USER' );?>::<?php echo $row->username; ?>">
						<a href="<?php echo $link; ?>">
							<?php echo htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8').' ('.htmlspecialchars($row->username, ENT_QUOTES, 'UTF-8').')'; ?>
						</a>
					</span>
				</td>
				<td align="center">
					<strong><?php echo $row->counter; ?></strong>
				</td>
			</tr>
			<?php $k = 1 - $k; } ?>
		</table>
	</div>
</div>
</td>
</tr>
</table>
<?php
echo $this->pane->endPanel();
echo $this->pane->endPane();
?>
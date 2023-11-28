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
$app = JFactory::getApplication();
$template	= $app->getTemplate();
if($this->check['connect'] == 0) {
?>
	<table class="fc-table-list">
		<thead>
			<tr>
				<th colspan="2">
					<span class="label text-white bg-info label-info"><?php echo JText::_( 'FLEXI_VERSION' ); ?></span>
				</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td colspan="2">
				<?php
					echo '<strong><font color="red">'.JText::_( 'FLEXI_CONNECTION_FAILED' ).'</font></strong>';
				?>
				</td>
			</tr>
		</tbody>
	</table>
<?php
} elseif ($this->check['enabled'] == 1) {
?>

	<table class="fc-table-list fc-tbl-short" style="margin: 4px 16px 13px 4px;">
		
	<thead>
		<tr>
			<th colspan="2" style="height:0px; padding:0px; border:0px;"></th>
		</tr>
	</thead>
	
	<tbody>
		<tr>
			<td colspan="2" style="text-align: center;">
			<?php
				if ($this->check['current'] == 0 ) {		  				
					echo JHtml::image( 'components/com_flexicontent/assets/images/'.'accept.png', JText::_('FLEXI_LATEST_VERSION_INSTALLED'),  '');
				} elseif( $this->check['current'] == -1 ) {
					echo JHtml::image( 'components/com_flexicontent/assets/images/'.'note.gif', JText::_('FLEXI_OLD_VERSION_INSTALLED'),  '');
				} else {
					echo JHtml::image( 'components/com_flexicontent/assets/images/'.'note.gif', JText::_('You have installed a newer version than the latest officially stable version'),  '');
				}
			?> &nbsp;
			<?php
				if ($this->check['current'] == 0) {
					echo '<strong><span style="color:darkgreen">'.JText::_( 'FLEXI_LATEST_VERSION_INSTALLED' ).'</span></strong>';
				} elseif( $this->check['current'] == -1 ) {
					echo '
					<strong><span style="color:darkorange">'.JText::_( 'FLEXI_NEWS_VERSION_COMPONENT' ).'</span></strong>
					<a class="btn btn-small btn-primary" href="http://www.flexicontent.org/downloads/latest-version.html" target="_blank" style="margin:4px;">'.JText::_( 'FLEXI_DOWNLOAD' ) .'</a>
					';
				} else {
					echo '<strong><span style="color:#777">'.JText::_( 'FLEXI_NEWER_THAN_OFFICIAL_INSTALLED' ).'</span></strong>';
				}
			?>
			</td>
		</tr>
		
		<tr>
			<td>
				<span class="label"><?php echo JText::_( 'FLEXI_LATEST_VERSION' ); ?></span>
			</td>
			<td>
				<span class="badge bg-success badge-success"><?php echo $this->check['version']; ?></span>
				&nbsp; <strong><?php echo JText::_( 'FLEXI_RELEASED_DATE' ); ?></strong>:
				<?php echo $this->check['released']; ?>
			</td>
		</tr>
		<tr>
			<td>
				<span class="label"><?php echo JText::_( 'FLEXI_INSTALLED_VERSION' ); ?></span>
			</td>
			<td>
				<span class="badge <?php echo $this->check['current']==-1 ? 'badge-warning' : ($this->check['current']==0 ? 'badge-success' : 'badge-info'); ?>"><?php echo $this->check['current_version']; ?></span>
				&nbsp; <strong><?php echo JText::_( 'FLEXI_RELEASED_DATE' ); ?></strong>:
				
				<?php
					try {
						$timezone = 'UTC';
						$dateformat = 'Y-m-d';
						$date = JHtml::_('date', $this->check['current_creationDate'], $dateformat, $timezone );
					} catch ( Exception $e ) {
						$date = $this->check['current_creationDate'];
					}
					echo $date;
				?>
			</td>
		</tr>
		
	</tbody>
	
	</table>
<?php
}
?>

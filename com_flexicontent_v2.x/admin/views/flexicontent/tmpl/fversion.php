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

defined( '_JEXEC' ) or die( 'Restricted access' );
$mainframe = &JFactory::getApplication();
$template	= $mainframe->getTemplate();
if($this->check['connect'] == 0) {
?>
	<table class="adminlist">
		<thead>
				<tr>
					<th colspan="2">
					<?php echo JText::_( 'FLEXI_VERSION' ); ?>
					</th>
				</tr>
		</thead>
		<tbody>
			<tr>
			<td colspan="2">
				<?php
					echo '<b><font color="red">'.JText::_( 'FLEXI_CONNECTION_FAILED' ).'</font></b>';
				?>
			</td>
			</tr>
		</tbody>
	</table>
<?php
} elseif ($this->check['enabled'] == 1) {
?>

	<table class="adminlist">
	<thead>
	<tr>
		<th colspan="2">
		<?php echo JText::_( 'FLEXI_UPDATE_CHECK' ); ?>
		</th>
	</tr>
	</thead>
	<tbody>
	<tr>
	<td width="33%">
	<?php
		if ($this->check['current'] == 0 ) {		  				
			echo JHTML::_('image', 'administrator/templates/'. $template .'/images/header/icon-48-checkin.png', NULL, 'width=32');
		} elseif( $this->check['current'] == -1 ) {
			echo JHTML::_('image', 'administrator/templates/'. $template .'/images/header/icon-48-info.png', NULL, 'width=32');
		} else {
			echo JHTML::_('image', 'administrator/templates/'. $template .'/images/header/icon-48-info.png', NULL, 'width=32');
		}
	?>
	</td>
	<td>
	<?php
		if ($this->check['current'] == 0) {
			echo '<strong><font color="green">'.JText::_( 'FLEXI_LATEST_VERSION_INSTALLED' ).'</font></strong>';
		} elseif( $this->check['current'] == -1 ) {
			echo '<b><font color="red">'.JText::_( 'FLEXI_OLD_VERSION_INSTALLED' ).'</font></b>';
		} else {
			echo '<b><font color="orange">'.JText::_( 'FLEXI_NEWS_VERSION_COMPONENT' ).'</font></b>';
		}
	?>
	</td>
	</tr>
	<tr>
	<td width="33%">
		<?php echo JText::_( 'FLEXI_LATEST_VERSION' ).':'; ?>
	</td>
	<td>
		<?php echo $this->check['version']; ?>
	</td>
	</tr>
	<tr>
	<td width="33%">
		<?php echo JText::_( 'FLEXI_INSTALLED_VERSION' ).':'; ?>
	</td>
	<td>
		<?php echo $this->check['current_version']; ?>
	</td>
	</tr>
	<tr>
	<td width="33%">
		<?php echo JText::_( 'FLEXI_RELEASED_DATE' ).':'; ?>
	</td>
	<td>
		<?php echo $this->check['released']; ?>
	</td>
	</tr>


	</tbody>
	</table>
<?php
}
?>

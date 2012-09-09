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
?>
<form action="index.php" method="post" name="adminForm" id="adminForm">

	<table cellspacing="0" cellpadding="0" border="0" width="100%">
		<tr>
			<td valign="top">
				<table  class="admintable">
					<tr>
						<td class="key">
							<label for="name">
								<?php echo JText::_( 'FLEXI_TYPE_NAME' ).':'; ?>
							</label>
						</td>
						<td>
							<input id="name" name="name" class="required" value="<?php echo $this->row->name; ?>" size="50" maxlength="100" />
						</td>
					</tr>
					<tr>
						<td class="key">
							<label for="published">
								<?php echo JText::_( 'FLEXI_PUBLISHED' ).':'; ?>
							</label>
						</td>
						<td>
							<?php
							$html = JHTML::_('select.booleanlist', 'published', 'class="inputbox"', $this->row->published );
							echo $html;
							?>
						</td>
					</tr>
				</table>
			</td>
			<td valign="top" width="600" style="padding: 7px 0 0 5px" align="left" valign="top">
				<?php
				echo JText::_('FLEXI_ITEM_PARAM_OVERRIDE_ORDER_DETAILS');
				$title = JText::_( 'FLEXI_PARAMETERS' );
				echo $this->pane->startPane( 'det-pane' );
				echo $this->pane->startPanel( $title, "params-page" );
				echo $this->form->render('params');
				echo $this->pane->endPanel();
				
				echo '<h3 class="themes-title">' . JText::_( 'FLEXI_PARAMETERS_LAYOUT_THEMES' ) . '</h3>';
				
				echo $this->form->render('params', 'themes');
				
				foreach ($this->tmpls as $tmpl) {
					$title = JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ) . ' : ' . $tmpl->name;
					echo $this->pane->startPanel( $title, "params-".$tmpl->name );
					echo $tmpl->params->render();
					echo $this->pane->endPanel();
				}

				echo $this->pane->endPane();
				?>
			</td>
		</tr>
	</table>

<?php echo JHTML::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="id" value="<?php echo $this->row->id; ?>" />
<input type="hidden" name="controller" value="types" />
<input type="hidden" name="view" value="type" />
<input type="hidden" name="task" value="" />
</form>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>
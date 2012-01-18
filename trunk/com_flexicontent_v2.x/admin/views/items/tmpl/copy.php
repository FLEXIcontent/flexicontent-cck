<?php
/**
 * @version 1.5 stable $Id: copy.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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

<div class="flexicontent">
<form action="index.php" method="post"  name="adminForm" id="adminForm">

	<table cellspacing="10" cellpadding="0" border="0" width="100%">
		<tr>
			<td valign="top" width="33%">
			<fieldset>
			<legend><?php echo JText::_( 'FLEXI_CONTENTS_LIST' ); ?></legend>
				<table>
					<thead>
						<tr>
							<th><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
							<th><?php echo JText::_( 'FLEXI_PRIMARY_CATEGORY' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						//dump($this->cid,'cid');
						foreach ($this->rows as $row) :
							if (in_array($row->id, $this->cid)) :
								foreach ($row->categories as $cat) :
									if ($cat->id == $row->catid) :
										$maincat = $cat->title;
						?>
						<tr>
							<td><?php echo $row->title; ?></td>
							<td><?php echo $maincat; ?><input type="hidden" name="cid[]" value="<?php echo $row->id; ?>" /></td>
						</tr>
						<?php
									endif;
								endforeach;
							endif;
						endforeach;
						?>
						<tr>
							<td>
								<ul>
								</ul>
							</td>
							<td></td>
						</tr>
					</tbody>
				</table>
			</fieldset>
			</td>
			<td valign="top" width="33%">
			<fieldset>
			<legend><?php echo JText::_( 'FLEXI_COPY_MOVE_OPTIONS' ); ?></legend>
				<table>
					<tr>
						<td class="key"><?php echo JText::_( 'FLEXI_METHOD' ); ?></td>
						<td>
							<label for="menus-copy">
							<label for="menus-copy" style="white-space:nowrap; display:inline-block!important;float:none!important; margin:0px!important;">
							<label for="menus-copy" class="lang_box" >
								<input id="menus-copy" type="radio" name="method" value="1" onclick="copyonly();" checked="checked" />
								<?php echo JText::_( 'FLEXI_COPYONLY' ); ?>
							</label><br />
							<label for="method-move" class="lang_box"  >
								<input id="method-move" type="radio" name="method" value="2" onclick="moveonly();" />
								<?php echo JText::_( 'FLEXI_MOVEONLY' ); ?>
							</label><br />
							<label for="method-copymove" class="lang_box" >
								<input id="method-copymove" type="radio" name="method" value="3" onclick="copymove();" />
								<?php echo JText::_( 'FLEXI_COPYMOVE' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<td></td>
						<td></td>
					</tr>
					<tr>
						<td class="key"><?php echo JText::_( 'FLEXI_KEEP_SEC_CATS' ); ?></td>
						<td>
							<label for="keepseccats0">
								<input id="keepseccats0" type="radio" name="keepseccats" value="0" onclick="secmove();" />
								<?php echo JText::_( 'No' ); ?>
							</label>
							<label for="keepseccats1">
								<input id="keepseccats1" type="radio" name="keepseccats" value="1" onclick="secnomove();" checked="checked" />
								<?php echo JText::_( 'Yes' ); ?>
							</label>
						</td>						
					</tr>
					<tr>
						<td class="key"><?php echo JText::_( 'FLEXI_KEEP_TAGS' ); ?></td>
						<td>
							<?php echo JHTML::_('select.booleanlist', 'keeptags', 'class="inputbox"', 1 ); ?>
						</td>						
					</tr>
					<tr>
						<td class="key"><?php echo JText::_( 'FLEXI_ADD_PREFIX' ); ?></td>
						<td>
							<input type="text" id="prefix" name="prefix" value="<?php echo JText::_( 'FLEXI_DEFAULT_PREFIX' ); ?>" size="15" />
						</td>
					</tr>
					<tr>
						<td class="key"><?php echo JText::_( 'FLEXI_ADD_SUFFIX' ); ?></td>
						<td>
							<input type="text" id="suffix" name="suffix" value="" size="15" />
						</td>
					</tr>
					<tr>
						<td class="key"><?php echo JText::_( 'FLEXI_COPIES_NR' ); ?></td>
						<td>
							<input type="text" id="copynr" name="copynr" value="1" size="3" />
						</td>
					</tr>
					<?php if (FLEXI_FISH || FLEXI_J16GE) : ?>
					<tr>
						<td class="key"><?php echo JText::_( 'NEW' )." ".JText::_( 'FLEXI_LANGUAGE' ); ?></td>
						<td>
							<?php echo flexicontent_html::buildlanguageslist('language', '', $this->rows[0]->lang, $type = 5); ?>
						</td>
					</tr>
					<?php endif; ?>
					<tr>
						<td class="key"><?php echo JText::_( 'FLEXI_STATE' ); ?></td>
						<td>
							<?php echo flexicontent_html::buildstateslist('state', '', ''); ?>
						</td>
					</tr>
				</table>
			</fieldset>
			</td>
			<td valign="top" width="33%">
			<fieldset>
			<legend><?php echo JText::_( 'FLEXI_COPY_MOVE_DESTINATION' ); ?></legend>
				<table>
					<tr>
						<td class="key"><?php echo JText::_( 'FLEXI_PRIMARY_CATEGORY' ); ?></td>
						<td>
							<?php echo $this->lists['maincat']; ?>
						</td>
					</tr>
					<tr>
						<td class="key"><?php echo JText::_( 'FLEXI_SECONDARY_CATEGORIES' ); ?></td>
						<td><?php echo $this->lists['seccats']; ?></td>
					</tr>
				</table>
			</fieldset>
			</td>
		</tr>
	</table>
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="items" />
	<input type="hidden" name="view" value="items" />
	<input type="hidden" name="task" value="" />
	<?php echo JHTML::_( 'form.token' ); ?>
</form>
</div>
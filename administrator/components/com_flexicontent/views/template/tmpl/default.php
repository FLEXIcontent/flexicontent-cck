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

defined('_JEXEC') or die('Restricted access'); ?>

<script language="javascript" type="text/javascript">
	function storeordering() {
	<?php echo $this->jssort . ';' ; ?>
	}
</script>

<form action="index.php" method="post" name="adminForm" id="adminForm">

	<table cellpadding="0" cellspacing="0" width="100%">
		<tr>
			<td width="50%" valign="top">
				
				<fieldset class="tmplprop">
					<legend><?php echo JText::_('FLEXI_TEMPLATE_PROPERTIES') ?></legend>
					<div id="propvisible">
						<table width="100%">
							<tr>
								<td width="100%" valign="top">
									<table class="admintable" id="lay-desc-table">
										<tr>
											<td class="key">
												<label for="label">
												<?php echo JText::_( 'FLEXI_FOLDER' ); ?>
												</label>
											</td>
											<td>
												<?php echo $this->layout->name; ?>
											</td>
										</tr>
										<tr>
											<td class="key">
												<label for="label">
													<?php echo JText::_( 'View' ); ?>
												</label>
											</td>
											<td>
												<?php echo $this->layout->view; ?>
											</td>
										</tr>
										<tr>
											<td class="key">
												<label for="label">
													<?php echo JText::_( 'Author' ); ?>
												</label>
											</td>
											<td>
												<?php echo $this->layout->author; ?>
											</td>
										</tr>
										<tr>
											<td class="key">
												<label for="label">
													<?php echo JText::_( 'FLEXI_WEBSITE' ); ?>
												</label>
											</td>
											<td>
												<a href="http://<?php echo $this->layout->website; ?>" target="_blank"><?php echo $this->layout->website; ?></a>
											</td>
										</tr>
										<tr>
											<td class="key">
												<label for="label">
													<?php echo JText::_( 'Email' ); ?>
												</label>
											</td>
											<td>
												<a href="mailto:<?php echo $this->layout->email; ?>"><?php echo $this->layout->email; ?></a>
											</td>
										</tr>
										<tr>
											<td class="key">
												<label for="label">
													<?php echo JText::_( 'License' ); ?>
												</label>
											</td>
											<td>
												<?php echo $this->layout->license; ?>
											</td>
										</tr>
										<tr>
											<td class="key">
												<label for="label">
													<?php echo JText::_( 'Version' ); ?>
												</label>
											</td>
											<td>
												<?php echo $this->layout->version; ?>
											</td>
										</tr>
										<tr>
											<td class="key">
												<label for="label">
													<?php echo JText::_( 'FLEXI_RELEASE' ); ?>
												</label>
											</td>
											<td>
												<?php echo $this->layout->release; ?>
											</td>
										</tr>
										<tr>
											<td class="key">
												<label for="label">
													<?php echo JText::_( 'Description' ); ?>
												</label>
											</td>
											<td>
												<?php echo $this->layout->description; ?>
											</td>
										</tr>
									</table>
								</td>
								<td valign="top">
									<img src="../<?php echo $this->layout->thumb; ?>" alt="<?php echo JText::_( 'FLEXI_TEMPLATE_THUMBNAIL' ); ?>" />
								</td>
							</tr>
						</table>
					</div>
					<div id="propnovisible">
						<?php echo JText::_( 'FLEXI_CLICK_PROPERTIES' ); ?>
					</div>
				</fieldset>
				<fieldset>
					<legend><?php echo JText::_('FLEXI_AVAILABLE_FIELDS') ?></legend>
					<div class="postitle"><?php echo JText::_('FLEXI_CORE_FIELDS'); ?></div>
					<ul id="sortablecorefields" class="positions">
					<?php
					foreach ($this->fields as $field) :
						if ($field->iscore && (!in_array($field->name, $this->used))) :
					?>
					<li class="fields core" id="field_<?php echo $field->name; ?>"><?php echo $field->label; ?></li>
					<?php
						endif;
					endforeach;
					?>
					</ul>
					<div class="postitle"><?php echo JText::_('FLEXI_NON_CORE_FIELDS'); ?></div>
					<ul id="sortableuserfields" class="positions">
					<?php
					foreach ($this->fields as $field) :
						if (!$field->iscore && (!in_array($field->name, $this->used))) :
					?>
					<li class="fields user" id="field_<?php echo $field->name; ?>"><?php echo $field->label.' #'.$field->id; ?></li>
					<?php
						endif;
					endforeach;
					?>
					</ul>
				</fieldset>
			</td>

			<td width="50%" valign="top">
				<fieldset>
					<legend><?php echo JText::_('FLEXI_AVAILABLE_POS') ?></legend>
					<?php
					if (isset($this->layout->positions)) :
						$count=-1;
						foreach ($this->layout->positions as $pos) :
							$count++;
							
							$pos_css = "";
							$posrow_prev = @$posrow;
							$posrow = isset($this->layout->attributes[$count]['posrow'] )  ?  $this->layout->attributes[$count]['posrow'] : '';
							
							// Detect field group row change and close previous row if open
							echo ($posrow_prev && $posrow_prev != $posrow)  ?  "</td></tr></table>\n"  :  "";
							
							if ($posrow) {
								// we are inside field group row, start it or continue with next field group
								echo ($posrow_prev != $posrow)  ?  "<table width='100%' cellpadding='0' cellspacing='0'><tr class='fieldgrprow' ><td class='fieldgrprow_cell' >\n"  :  "</td><td class='fieldgrprow_cell'>\n";
							}
							
						?>
						
						<div class="postitle"><?php echo $pos; ?></div>
						
						<?php
						if ( isset($this->layout->attributes[$count]['readonly']) ) {
							switch ($this->layout->view) {
								case FLEXI_ITEMVIEW: $msg='in the <b>Item Type</b> configuration and/or in each individual <b>Item</b>'; break;
								case 'category': $msg='in each individual <b>Category</b>'; break;
								default: $msg='in each <b>'.$this->layout->view.'</b>'; break;
							}
							echo "<div class='positions_readonly' style='padding:1px 1px 1px 16px;'>NON-editable position.<br> To customize edit TEMPLATE parameters ".$msg."</div>";
							continue;
						}
						?>
						<ul id="sortable-<?php echo $pos; ?>" class="positions" >
						<?php
						if (isset($this->fbypos[$pos])) :
							foreach ($this->fbypos[$pos]->fields as $f) :
								if (isset($this->fields[$f])) : // this check is in case a field was deleted
						?>
							<li class="fields <?php echo $this->fields[$f]->iscore ? 'core' : 'user'; ?>" id="field_<?php echo $this->fields[$f]->name; ?>">
							<?php echo $this->fields[$f]->label . ($this->fields[$f]->iscore ? '' : ' #'.$this->fields[$f]->id); ?>
							</li>
						<?php
								endif;
							endforeach;
						endif;	
						?>
						</ul>
						<input type="hidden" name="<?php echo $pos; ?>" id="<?php echo $pos; ?>" value="" />
					<?php 
						endforeach;
						// Close any field group line that it is still open
						echo @$posrow ? "</td></tr></table>\n" : "";
					else :
						echo JText::_('FLEXI_NO_GROUPS_AVAILABLE');
					endif;
					?>
				</fieldset>
			</td>
		</tr>
	</table>
	
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="templates" />
	<input type="hidden" name="rows" id="rows" value="" />
	<input type="hidden" name="positions" id="positions" value="<?php echo $this->positions; ?>" />
	<input type="hidden" name="view" value="template" />
	<input type="hidden" name="type" value="<?php echo $this->type; ?>" />
	<input type="hidden" name="folder" value="<?php echo $this->folder; ?>" />
	<input type="hidden" name="task" value="" />
	<?php echo JHTML::_( 'form.token' ); ?>
</form>
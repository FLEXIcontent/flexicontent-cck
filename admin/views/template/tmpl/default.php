<?php
/**
 * @version 1.5 stable $Id: default.php 1832 2014-01-17 00:17:27Z ggppdk $
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

// Load JS tabber lib
$this->document->addScript(JURI::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js');
$this->document->addStyleSheet(JURI::root(true).'/components/com_flexicontent/assets/css/tabber.css');
$this->document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs
?>

<script language="javascript" type="text/javascript">
	function <?php echo $this->use_jquery_sortable ? 'initordering' : 'storeordering'; ?>() {
	<?php echo $this->jssort . ';' ; ?>
	}
	
	function save_layout_file(formid)
	{
		var form = jQuery('#'+formid);
		var layout_name  = jQuery('#editor__layout_name').val();
		var file_subpath = jQuery('#editor__file_subpath').val();
		if (file_subpath=='') {
			alert('Please load a file before trying to save');
			return;
		}
		
		txtarea = jQuery('#editor__file_contents');
		txtarea.after('<span id="fc_doajax_loading"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center" /> ... Saving</span>');
		
		jQuery.ajax({
			type: "POST",
			url: "index.php?option=com_flexicontent&task=templates.savelayoutfile&format=raw",
			data: form.serialize(),
			success: function (data) {
				jQuery('#fc_doajax_loading').remove();
				var theData = jQuery.parseJSON(data);
				jQuery('#ajax-system-message-container').html(theData.sysmssg);
				txtarea.val(theData.content);
				txtarea.show();
			}
		});
	}
	
	function load_layout_file(layout_name, file_subpath)
	{
		jQuery('#editor__layout_name').val(layout_name);
		jQuery('#editor__file_subpath').val(file_subpath);
		
		txtarea = jQuery('#editor__file_contents');
		txtarea.hide();
		txtarea.after('<span id="fc_doajax_loading"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center" /> ... Saving</span>');
		jQuery('#layout_edit_name_container').html(file_subpath);
		
		jQuery.ajax({
			type: "POST",
			url: "index.php?option=com_flexicontent&task=templates.loadlayoutfile&format=raw",
			data: { layout_name: layout_name, file_subpath: file_subpath },
			success: function (data) {
				jQuery('#fc_doajax_loading').remove();
				var theData = jQuery.parseJSON(data);
				jQuery('#ajax-system-message-container').html(theData.sysmssg);
				txtarea.val(theData.content);
				txtarea.show();
			}
		});
	}
</script>

<div id="flexicontent" class="flexicontent">

<form action="index.php" method="post" name="adminForm" id="adminForm">
	
	<!--div class="fc-info fc-nobgimage fc-mssg-inline" style="font-size: 12px; margin: 0px 0px 16px 0px !important; padding: 16px 32px !important">
		<?php echo !empty($fieldSet->label) ? $fieldSet->label : JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ) . ' : ' . $this->layout->name; ?>
	</div-->
	
	
	<table>
		<tr>
			<td valign="top">
				<img src="../<?php echo $this->layout->thumb; ?>" alt="<?php echo JText::_( 'FLEXI_TEMPLATE_THUMBNAIL' ); ?>" style="max-width:none;" />
			</td>
			<td valign="top">
				<table class="admintable" id="lay-desc-table">
					<tr>
						<td style="text-align:right;">
							<label class="label">
							<?php echo JText::_( 'FLEXI_FOLDER' ); ?>
							</label>
						</td>
						<td>
							<span class="badge badge-warning"><?php echo $this->layout->name; ?></span>
						</td>
					</tr>
					<tr>
						<td style="text-align:right;">
							<label class="label">
								<?php echo JText::_( 'View' ); ?>
							</label>
						</td>
						<td>
							<span class="badge badge-success"><?php echo $this->layout->view; ?></span>
						</td>
					</tr>
					<tr>
						<td style="text-align:right;">
							<label class="label">
								<?php echo JText::_( 'Author' ); ?>
							</label>
						</td>
						<td>
							<?php echo $this->layout->author; ?>
						</td>
					</tr>
					<tr>
						<td style="text-align:right;">
							<label class="label">
								<?php echo JText::_( 'FLEXI_WEBSITE' ); ?>
							</label>
						</td>
						<td>
							<a href="http://<?php echo $this->layout->website; ?>" target="_blank"><?php echo $this->layout->website; ?></a>
						</td>
					</tr>
					<tr>
						<td style="text-align:right;">
							<label class="label">
								<?php echo JText::_( 'Email' ); ?>
							</label>
						</td>
						<td>
							<a href="mailto:<?php echo $this->layout->email; ?>"><?php echo $this->layout->email; ?></a>
						</td>
					</tr>
					<tr>
						<td style="text-align:right;">
							<label class="label">
								<?php echo JText::_( 'License' ); ?>
							</label>
						</td>
						<td>
							<?php echo $this->layout->license; ?>
						</td>
					</tr>
					<tr>
						<td style="text-align:right;">
							<label class="label">
								<?php echo JText::_( 'Version' ); ?>
							</label>
						</td>
						<td>
							<?php echo $this->layout->version; ?>
						</td>
					</tr>
					<tr>
						<td style="text-align:right;">
							<label class="label">
								<?php echo JText::_( 'FLEXI_RELEASE' ); ?>
							</label>
						</td>
						<td>
							<?php echo $this->layout->release; ?>
						</td>
					</tr>
					<tr>
						<td style="text-align:right;">
							<label class="label">
								<?php echo JText::_( 'Description' ); ?>
							</label>
						</td>
						<td>
							<?php echo $this->layout->description; ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
	
	
	<div class="fctabber tabset_layout" id="tabset_layout" style="margin:32px 0 !important;">
		
		<div class="tabbertab" id="tabset_cat_props_desc_tab" data-icon-class="icon-signup" >
			<h3 class="tabberheading"> <?php echo JText::_( 'Field positions' ); ?></h3>
				
				<div class="fcclear"></div>
				<span class="fc-mssg-inline fc-success" style="font-size:100%; margin: 12px 0 0 0!important;">
					<span style="font-weight:bold;"><?php echo JText::_('FLEXI_NOTES');?>:</span>
					<?php echo JText::_('FLEXI_INSTRUCTIONS_ADD_FIELD_TO_LAYOUT_POSITION');?>
				</span>
				
				<table cellpadding="4" cellspacing="0" width="100%">
					<tr>
						<td width="50%" valign="top">
							
							<fieldset id="available_fields_container">
								<legend style="margin:0 0 12px 0; font-size:14px; padding:6px 12px; background:gray;" class="fcsep_level1"><?php echo JText::_('FLEXI_AVAILABLE_FIELDS') ?></legend>
								<div class="fcclear"></div>
								
								<div style="float:left; clear:both; width:100%; margin:0px 0px 12px 0px;">
									<div style="float:left; margin-right:32px;">
										<div style="float:left;" class="postitle label" ><?php echo JText::_('FLEXI_FILTER').' '.JText::_('FLEXI_TYPE'); ?></div>
										<div style="float:left; clear:both;">
											<?php echo sprintf(str_replace('__au__', '_available', $this->content_type_select), 'available_fields_container', 'hide', 'available'); ?>
										</div>
									</div>
									<div style="float:left;">
										<div style="float:left;" class="postitle label" ><?php echo JText::_('FLEXI_FILTER').' '.JText::_('FLEXI_FIELD_TYPE'); ?></div>
										<div style="float:left; clear:both;">
											<?php echo sprintf(str_replace('__au__', '_available', $this->field_type_select), 'available_fields_container', 'hide', 'available'); ?>
										</div>
									</div>
								</div>
								
								
								<div class="postitle badge badge-info" style="margin-top:10px;"><?php echo JText::_('FLEXI_CORE_FIELDS'); ?></div>
							
								<div class="positions_container">
									<ul id="sortablecorefields" class="positions">
									<?php
									foreach ($this->fields as $field) :
										if ($field->iscore && (!in_array($field->name, $this->used))) :
											$class_list  = "fields core";
											$class_list .= !empty($field->type_ids) ? " content_type_".implode(" content_type_", $field->type_ids) : "";
											$class_list .= " field_type_".$field->field_type;
									?>
									<li class="<?php echo $class_list; ?>" id="field_<?php echo $field->name; ?>"><?php echo $field->label; ?></li>
									<?php
										endif;
									endforeach;
									?>
									</ul>
								</div>
								
								
								<div class="postitle badge badge-info" style="margin-top:10px;"><?php echo JText::_('FLEXI_NON_CORE_FIELDS'); ?></div>
								
								<div class="positions_container">
									<ul id="sortableuserfields" class="positions">
									<?php
									foreach ($this->fields as $field) :
										if (!$field->iscore && (!in_array($field->name, $this->used))) :
											$class_list  = "fields user";
											$class_list .= !empty($field->type_ids) ? " content_type_".implode(" content_type_", $field->type_ids) : "";
											$class_list .= " field_type_".$field->field_type;
									?>
									<li class="<?php echo $class_list; ?>" id="field_<?php echo $field->name; ?>"><?php echo $field->label.' #'.$field->id; ?></li>
									<?php
										endif;
									endforeach;
									?>
									</ul>
								</div>
							
							</fieldset>
						</td>
						
						<td width="50%" valign="top">
							<fieldset id="layout_positions_container">
								<legend style="margin:0 0 12px 0; font-size:14px; padding:6px 12px; background:gray;" class="fcsep_level1"><?php echo JText::_('FLEXI_AVAILABLE_POS') ?></legend>
								<div class="fcclear"></div>
								
								<div style="float:left; clear:both; width:100%; margin:0px 0px 12px 0px;">
									<div style="float:left; margin-right:32px;">
										<div style="float:left;" class="postitle label" ><?php echo JText::_('FLEXI_FILTER').' '.JText::_('FLEXI_TYPE'); ?></div>
										<div style="float:left; clear:both;">
											<?php echo sprintf(str_replace('__au__', '_used',$this->content_type_select), 'layout_positions_container', 'highlight', 'used'); ?>
										</div>
									</div>
									<div style="float:left;">
										<div style="float:left;" class="postitle label" ><?php echo JText::_('FLEXI_FILTER').' '.JText::_('FLEXI_FIELD_TYPE'); ?></div>
										<div style="float:left; clear:both;">
											<?php echo sprintf(str_replace('__au__', '_used',$this->field_type_select), 'layout_positions_container', 'highlight', 'used'); ?>
										</div>
									</div>
								</div>
								
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
									
									<div class="postitle badge badge-success" style="margin:10px 0 2px"><?php echo $pos; ?></div>
									
									<?php
									if ( isset($this->layout->attributes[$count]['readonly']) ) {
										switch ($this->layout->view) {
											case FLEXI_ITEMVIEW: $msg='in the <b>Item Type</b> configuration and/or in each individual <b>Item</b>'; break;
											case 'category': $msg='in each individual <b>Category</b>'; break;
											default: $msg='in each <b>'.$this->layout->view.'</b>'; break;
										}
										echo "<div class='positions_readonly'>NON-editable position.<br/> To customize edit TEMPLATE parameters ".$msg."</div>";
										continue;
									}
									?>
								<div class="positions_container">
									<ul id="sortable-<?php echo $pos; ?>" class="positions" >
									<?php
									if (isset($this->fbypos[$pos])) :
										foreach ($this->fbypos[$pos]->fields as $f) :
											if (isset($this->fields[$f])) : // this check is in case a field was deleted
												$field = $this->fields[$f];
												$class_list  = "fields ". ($this->fields[$f]->iscore ? 'core' : 'user');
												$class_list .= !empty($field->type_ids) ? " content_type_".implode(" content_type_", $field->type_ids) : "";
												$class_list .= " field_type_".$field->field_type;
									?>
										<li class="<?php echo $class_list; ?>" id="field_<?php echo $this->fields[$f]->name; ?>">
										<?php echo $this->fields[$f]->label . ($this->fields[$f]->iscore ? '' : ' #'.$this->fields[$f]->id); ?>
										</li>
									<?php
											endif;
										endforeach;
									endif;	
									?>
									</ul>
								</div>
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
		
		</div>
		
		<div class="tabbertab" id="tabset_layout_fields_tab" data-icon-class="icon-options" >	
			<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_PARAMETERS' ); ?> </h3>
			
			<div class="fcclear"></div>
			<span class="fc-mssg-inline fc-success" style="font-size:100%; margin: 12px 0 0 0!important;">
				<span style="font-weight:bold;"><?php echo JText::_('FLEXI_NOTES');?>:</span>
				<?php echo JText::_( $this->layout->view == 'item' ?
					'Parameters specific to the layout, your <b>content types</b> will inherit defaults from here' :
					'Parameters specific to the layout, your <b>content lists</b> (categories, etc) will inherit defaults from here'
				);?>
			</span>
			<br/>
			<span class="fc-mssg-inline fc-success" style="font-size:100%; margin: 12px 0 0 0!important;">
				<span style="font-weight:bold;"><?php echo JText::_('FLEXI_NOTES');?>:</span>
				<?php echo JText::_( 'Setting any parameter below to <b>"Use global"</b>, will use default</b> value inside the <b>template\'s PHP code</b>');?>
			</span>
			
			<div style="max-width:1024px;">
			
				<?php
				$groupname = 'attribs';  // Field Group name this is for name of <fields name="..." >
				$fieldSets = $this->layout->params->getFieldsets($groupname);
				foreach ($fieldSets as $fsname => $fieldSet) :
					if (isset($fieldSet->description) && trim($fieldSet->description)) :
						echo '<p class="tip">'.$this->escape(JText::_($fieldSet->description)).'</p>';
					endif;
					?>
					<fieldset class="panelform">
						<?php foreach ($this->layout->params->getFieldset($fsname) as $field) :
							$fieldname =  $field->__get('fieldname');
							$value = $this->layout->params->getValue($fieldname, $groupname, @$this->conf->attribs[$fieldname]);
							echo $this->layout->params->getLabel($fieldname, $groupname);
							echo
								str_replace('jform_attribs_', 'jform_layouts_'.$this->layout->name.'_', 
									str_replace('[attribs]', '[layouts]['.$this->layout->name.']',
										$this->layout->params->getInput($fieldname, $groupname, $value)
									)
								);
						endforeach; ?>
					</fieldset>
				<?php endforeach; ?>
				
			</div>
		</div>
		
		<div class="tabbertab" id="tabset_cat_props_desc_tab" data-icon-class="icon-signup" >
			<h3 class="tabberheading"> <?php echo JText::_( 'Edit files' ); ?></h3>
			
			<div id="layout-filelist-container" class="span3">
				<?php
				$tmpldir = JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.$this->layout->name;
				$it = new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpldir)), '#'.$this->layout->view.'#');
				//$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpldir));
				$it->rewind();
				while($it->valid())
				{
					if (!$it->isDot()) {
						/*echo '<span class="label">SubPathName</span> '. $it->getSubPathName();
						echo ' -- <span class="label">SubPath</span> '. $it->getSubPath();
						echo ' -- <span class="label">Key</span> '. $it->key();*/
						$subpath = $it->getSubPath();
						$subpath_file = $it->getSubPathName();
						echo
						'<a href="javascript:;" onclick="load_layout_file(\''.addslashes($this->layout->name).'\', \''.addslashes($it->getSubPathName()).'\'); return false;">'
							.'<span class="badge">'.$subpath.'</span>'.preg_replace('#^'.$subpath.'#', '', $it->getSubPathName()).
						'</a>';
						echo "<br/>";
					}
					
					$it->next();
				}
				?>
			</div>

			<div id="layout-fileeditor-container" class="span9">
				<div id="ajax-system-message-container">
				</div>
				<div class="fcclear"></div>
				<span class="fcsep_level0" style="margin:0 0 12px 0; background-color:#333; ">
					<?php echo JText::_( 'Layout file editor' ); ?>
					<span id="layout_edit_name_container" class="badge badge-info">no file loaded</span>
				</span>
				
				
				<textarea name="file_contents" id="editor__file_contents" style="width: 100%;" rows="16" form="layout_file_editor_form"></textarea>
				<input type="hidden" name="layout_name" id="editor__layout_name" form="layout_file_editor_form"/>
				<input type="hidden" name="file_subpath" id="editor__file_subpath" form="layout_file_editor_form"/>
				<input type="button" name="save_file_btn" id="editor__save_file_btn" class="btn btn-success" onclick="save_layout_file('layout_file_editor_form'); return false;" value="Save File" form="layout_file_editor_form"/>
			</div>
			
		</div>
			
	</div>
	
	
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

<form id="layout_file_editor_form" name="layout_file_editor_form"></form>

</div>
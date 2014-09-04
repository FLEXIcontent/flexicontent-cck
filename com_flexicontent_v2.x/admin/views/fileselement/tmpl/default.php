<?php
/**
 * @version 1.5 stable $Id: default.php 1929 2014-07-08 17:04:16Z ggppdk $
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

$uri = JURI::getInstance();
$current_uri = $uri->toString();
$ctrl_task  = FLEXI_J16GE ? 'task=filemanager.'  :  'controller=filemanager&amp;task=';
$del_task   = FLEXI_J16GE ? 'filemanager.remove'  :  'remove';
$session = JFactory::getSession();

$close_btn = FLEXI_J30GE ? '<a class="close" data-dismiss="alert">&#215;</a>' : '<a class="fc-close" onclick="this.parentNode.parentNode.removeChild(this.parentNode);">&#215;</a>';
$alert_box = FLEXI_J30GE ? '<div %s class="alert alert-%s %s">'.$close_btn.'%s</div>' : '<div %s class="fc-mssg fc-%s %s">'.$close_btn.'%s</div>';
?>

<div class="flexicontent">

<?php if (!$this->CanUpload) :?>
	<?php echo sprintf( $alert_box, '', 'note', '', JText::_('FLEXI_YOUR_ACCOUNT_CANNOT_UPLOAD') ); ?>
<?php endif; ?>


	<?php
	echo FLEXI_J16GE ? JHtml::_('tabs.start') : $this->pane->startPane( 'stat-pane' );
	?>
	
	<!-- File(s) by uploading -->
	
	<?php if ($this->CanUpload):
		echo FLEXI_J16GE ?
			JHtml::_('tabs.panel', JText::_( 'FLEXI_UPLOAD_LOCAL_FILE' ), 'local' ) :
			$this->pane->startPanel( JText::_( 'FLEXI_UPLOAD_LOCAL_FILE' ), 'local' ) ;
	?>
	
	<!-- File Upload Form -->
	<fieldset class="filemanager-tab" >
		<legend><?php echo JText::_( 'FLEXI_CHOOSE_FILE' ); ?> [ <?php echo JText::_( 'FLEXI_MAX' ); ?>&nbsp;<?php echo ($this->params->get('upload_maxsize') / 1000000); ?>M ]</legend>
		
		<fieldset class="actions" id="filemanager-1">
			<form action="<?php echo JURI::base(); ?>index.php?option=com_flexicontent&amp;<?php echo $ctrl_task; ?>upload&amp;<?php echo $session->getName().'='.$session->getId(); ?>" name="uploadFileForm" id="uploadFileForm" method="post" enctype="multipart/form-data">
				
				<table class="admintable" cellspacing="0" cellpadding="0" border="0" width="100%">
					
					<tr>
						<td class="key">
							<label for="file-upload">
							<?php echo JText::_( 'FLEXI_CHOOSE_FILE' ); ?>
							</label>
						</td>
						<td width="260">
							<input type="file" id="file-upload" name="Filedata" />
						</td>
						
	<?php if (!$this->folder_mode) { ?>
						<td class="key">
							<label>
							<?php echo JText::_( 'FLEXI_LANGUAGE' ); ?>
							</label>
						</td>
						<td>
							<?php echo $this->lists['file-lang']; ?>
						</td>
	<?php } ?>
					</tr>
	<?php if (!$this->folder_mode) { ?>
					<tr>
						<td class="key hasTip"  title="<?php echo JText::_( 'FLEXI_CHOOSE_DIR' ); ?>">
							<label>
							<?php echo JText::_( 'FLEXI_FILE_DIRECTORY' ); ?>
							</label>
						</td>
						<td>
							<?php echo JHTML::_('select.booleanlist', 'secure', 'class="inputbox"', 1, JText::_( 'FLEXI_SECURE' ), JText::_( 'FLEXI_MEDIA' ) ); ?>
						</td>
						
						<td class="key" rowspan="2">
							<label for="file-desc">
							<?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?>
							</label>
						</td>
						<td valign="top" rowspan="2">
							<textarea name="file-desc" cols="24" rows="3" id="file-desc"></textarea>
						</td>
					</tr>
					
					<tr>
						<td class="key">
							<label for="file-title">
							<?php echo JText::_( 'FLEXI_FILE_TITLE' ); ?>
							</label>
						</td>
						<td>
							<input type="text" id="file-title" size="44" class="required" name="file-title" />
						</td>
					</tr>
	<?php } ?>
				</table>
				
				<input type="submit" id="file-upload-submit" class="fc_button fcsimple" value="<?php echo JText::_( 'FLEXI_START_UPLOAD' ); ?>"/>
				<span id="upload-clear"></span>
				
				<?php echo JHTML::_( 'form.token' ); ?>
				<input type="hidden" name="fieldid" value="<?php echo $this->fieldid; ?>" />
				<input type="hidden" name="u_item_id" value="<?php echo $this->u_item_id; ?>" />
				<input type="hidden" name="folder_mode" value="<?php echo $this->folder_mode; ?>" />
				<input type="hidden" name="return-url" value="<?php echo base64_encode('index.php?option=com_flexicontent&view=fileselement&tmpl=component&field='.$this->fieldid.'&folder_mode='.$this->folder_mode); ?>" />
			</form>
			
		</fieldset>
		
		
	</fieldset>
	
	<?php
	echo FLEXI_J16GE ? '' : $this->pane->endPanel();
	?>
	<?php endif; ?>
	
	
	<!-- File URL by Form -->
	<?php
		echo FLEXI_J16GE ?
			JHtml::_('tabs.panel', JText::_( 'FLEXI_ADD_FILE_BY_URL' ), 'fileurl' ) :
			$this->pane->startPanel( JText::_( 'FLEXI_ADD_FILE_BY_URL' ), 'fileurl' ) ;
	?>
	<form action="<?php echo JURI::base(); ?>index.php?option=com_flexicontent&amp;<?php echo $ctrl_task; ?>addurl&amp;<?php echo $session->getName().'='.$session->getId(); ?>&amp;<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1" class="form-validate" name="urlForm" id="urlForm" method="post">
		<fieldset class="filemanager-tab" >
			<legend><?php echo JText::_( 'FLEXI_ADD_FILE_BY_URL' ); ?></legend>
			<fieldset class="actions" id="filemanager-2">
				
				<table class="admintable" cellspacing="0" cellpadding="0" border="0" width="100%">
					
					<tr>
						<td class="key">
							<label for="file-url-data">
							<?php echo JText::_( 'FLEXI_FILE_URL' ); ?>
							</label>
						</td>
						<td width="260">
							<input type="text" id="file-url-data" size="44" class="required" name="file-url-data" />
						</td>
						
						<td class="key">
							<label>
							<?php echo JText::_( 'FLEXI_LANGUAGE' ); ?>
							</label>
						</td>
						<td>
							<?php echo str_replace('file-lang', 'file-url-lang', $this->lists['file-lang']); ?>
						</td>
					</tr>
					
					<tr>
						<td class="key">
							<label for="file-url-ext">
							<?php echo JText::_( 'FLEXI_FILEEXT' ); ?>
							</label>
						</td>
						<td>
							<input type="text" id="file-url-ext" size="5" class="required" name="file-url-ext" />
						</td>
						<td class="key" rowspan="3">
							<label for="file-url-desc">
							<?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?>
							</label>
						</td>
						<td rowspan="3">
							<textarea name="file-url-desc" cols="24" rows="3" id="file-url-desc"></textarea>
						</td>
					</tr>
					
					<tr>
						<td class="key">
							<label for="file-url-title">
							<?php echo JText::_( 'FLEXI_FILE_TITLE' ); ?>
							</label>
						</td>
						<td>
							<input type="text" id="file-url-title" size="44" class="required" name="file-url-title" />
						</td>
					</tr>
					
				</table>
				
				<input type="submit" id="file-url-submit" class="fc_button fcsimple validate" value="<?php echo JText::_( 'FLEXI_ADD_FILE' ); ?>"/>
			</fieldset>
		</fieldset>
		<input type="hidden" name="return-url" value="<?php echo base64_encode('index.php?option=com_flexicontent&view=fileselement&field='.$this->fieldid.'&tmpl=component'); ?>" />
	</form>
	<?php echo FLEXI_J16GE ? '' : $this->pane->endPanel(); ?>
	
<?php echo FLEXI_J16GE ? JHtml::_('tabs.end') : $this->pane->endPane(); ?>



			
<div class="fcclear"></div>

<form action="<?php echo JURI::base(); ?>index.php?option=com_flexicontent&amp;view=fileselement&amp;field=<?php echo $this->fieldid?>&amp;tmpl=component" method="post" name="adminForm" id="adminForm">

<?php if (!$this->folder_mode) : ?>
	<table class="adminform" border="0">
		<tr>
			<td align="left">
				<label class="label"><?php echo JText::_( 'FLEXI_SEARCH' ); ?></label>
				<?php echo $this->lists['filter']; ?>
				<input type="text" name="search" id="search" value="<?php echo $this->lists['search']; ?>" class="text_area" onChange="document.adminForm.submit();" />
				<div id="fc-filter-buttons">
					<button class="fc_button fcsimple" onclick="this.form.submit();"><?php echo JText::_( 'FLEXI_GO' ); ?></button>
					<button class="fc_button fcsimple" onclick="this.form.getElementById('search').value='';this.form.submit();"><?php echo JText::_( 'FLEXI_RESET' ); ?></button>
				</div>
			</td>
			<td nowrap="nowrap">
				<div class="limit" style="display: inline-block;">
					<?php echo JText::_(FLEXI_J16GE ? 'JGLOBAL_DISPLAY_NUM' : 'DISPLAY NUM') . $this->pagination->getLimitBox(); ?>
				</div>
				
				<span class="fc_item_total_data fc_nice_box" style="margin-right:10px;" >
					<?php echo @$this->resultsCounter ? $this->resultsCounter : $this->pagination->getResultsCounter(); // custom Results Counter ?>
				</span>
				
				<span class="fc_pages_counter">
					<?php echo $this->pagination->getPagesCounter(); ?>
				</span>
			</td>
			<td style="text-align:right;">
				<?php echo $this->lists['language']; ?>
				<?php echo $this->lists['url']; ?>
			 	<?php echo $this->lists['secure']; ?>
			 	<?php echo $this->lists['ext']; ?>
			 	<?php if ($this->CanViewAllFiles) echo $this->lists['uploader']; ?>
			 	&nbsp; &nbsp; &nbsp;
				<label class="label">Item ID</label> <?php echo $this->lists['item_id']; ?>
			</td>
		</tr>
	</table>
<?php endif; ?>

	<table class="adminlist" cellspacing="1">
	<thead>
		<tr>
			<th width="5"><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			<th width="5"><input type="checkbox" name="toggle" value="" onClick="<?php echo FLEXI_J30GE ? 'Joomla.checkAll(this);' : 'checkAll('.count( $this->rows).');'; ?>" /></th>
<?php if ($this->folder_mode) { ?>
			<th width="5">&nbsp;</th>
<?php } ?>
			<th width="5"><?php echo JText::_( 'FLEXI_THUMB' ); ?></th>
			<th class="title"><?php echo JHTML::_('grid.sort', 'FLEXI_FILENAME', 'f.filename', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class=""><?php echo JHTML::_('grid.sort', 'FLEXI_ORIGINAL_FILENAME', 'f.filename_original', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width=""><?php echo JHTML::_('grid.sort', 'FLEXI_FILE_TITLE', 'f.altname', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JText::_( 'FLEXI_PUBLISHED' ); ?></th>
			<th width=""><?php echo JText::_( 'FLEXI_ACCESS' ); ?></th>
			<th width=""><?php echo JText::_( 'FLEXI_LANGUAGE' ); ?></th>
			<th width=""><?php echo JText::_( 'FLEXI_SIZE' ); ?></th>
			<th width="15"><?php echo JHTML::_('grid.sort', 'FLEXI_HITS', 'f.hits', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width=""><?php echo JHTML::_('grid.sort', 'FLEXI_UPLOADER', 'uploader', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width=""><?php echo JHTML::_('grid.sort', 'FLEXI_UPLOAD_TIME', 'f.uploaded', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
<?php if (!$this->folder_mode) { ?>
			<th width="1%" nowrap="nowrap"><?php echo JHTML::_('grid.sort', 'FLEXI_ID', 'f.id', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
<?php } ?>
		</tr>
		
	</thead>

	<tfoot>
		<tr>
			<td colspan="<?php echo $this->folder_mode ? 12 : 13; ?>">
				<?php echo $this->pagination->getListFooter(); ?>
			</td>
		</tr>
		
	</tfoot>

	<tbody>
		<?php
		$imageexts = array('jpg','gif','png','bmp');
		$index = JRequest::getInt('index', 0);
		$k = 0;
		$i = 0;
		$n = count($this->rows);
		foreach ($this->rows as $row) {
			unset($thumb_or_icon);
			$filename    = str_replace( array("'", "\""), array("\\'", ""), $row->filename );
			$filename_original = str_replace( array("'", "\""), array("\\'", ""), $row->filename_original );
			$display_filename  = $filename_original ? $filename_original : $filename;
			
			if ( !in_array($row->ext, $imageexts)) $thumb_or_icon = JHTML::image($row->icon, $row->filename);
			
			$checked 	= @ JHTML::_('grid.checkedout', $row, $i );
			
			$path		= $row->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;  // JPATH_ROOT . DS . <media_path | file_path>
			$file_path = $row->filename;
			
			if ($this->folder_mode) {
				$file_path = $this->img_folder . DS . $row->filename;
			} else if (substr($row->filename, 0, 7)!='http://') {
				$file_path = $path . DS . $row->filename;
			} else {
				$thumb_or_icon = 'URL';
			}
			
			$file_path    = str_replace('\\', '/', $file_path);
			if ( empty($thumb_or_icon) ) {
				$thumb_or_icon = JURI::root() . 'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' . $file_path . '&w=60&h=60';
				$thumb_or_icon = "<img src=\"$thumb_or_icon\" alt=\"$display_filename\" />";
			}
			$file_preview = JURI::root() . 'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' . $file_path . '&w='.$this->thumb_w.'&h='.$this->thumb_h;
			if ($this->folder_mode) {
				$img_assign_link = "window.parent.qmAssignFile".$this->fieldid."('".$this->targetid."', '".$filename."', '".$file_preview."');";
			} else {
				$img_assign_link = "qffileselementadd(document.getElementById('file".$row->id."'), '".$row->id."', '".$display_filename."');";
			}
   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td><?php echo $this->pagination->getRowOffset( $i ); ?></td>
			<td width="7">
				<?php echo $checked; ?>
			</td>
<?php if ($this->folder_mode) { ?>
			<td>
				<a href="javascript:;" onclick="if (confirm('<?php echo JText::_('FLEXI_SURE_TO_DELETE_FILE'); ?>')) { document.adminForm.filename.value='<?php echo $row->filename;?>'; document.adminForm.controller.value='filemanager'; <?php echo FLEXI_J16GE ? "Joomla." : ""; ?>submitbutton('<?php echo $del_task; ?>'); }" href="#">
				<?php echo JHTML::image('components/com_flexicontent/assets/images/trash.png', JText::_('FLEXI_REMOVE') ); ?>
				</a>
			</td>
<?php } ?>
			<td align="center">
				<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_SELECT' ); ?>::<?php echo $row->filename; ?>">
				<a style="cursor:pointer" onclick="<?php echo $img_assign_link; ?>">
				<?php echo $thumb_or_icon; ?>
				</a>
				</span>
			</td>
			<td align="left">
				<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_SELECT' );?>::<?php echo $row->filename; ?>">
					<a style="cursor:pointer" id="file<?php echo $row->id;?>" rel="<?php echo $filename; ?>" onclick="<?php echo $img_assign_link; ?>">
					<?php 
							if (JString::strlen($row->filename) > 25) {
								echo JString::substr( htmlspecialchars($row->filename, ENT_QUOTES, 'UTF-8'), 0 , 25).'...';
							} else {
								echo htmlspecialchars($row->filename, ENT_QUOTES, 'UTF-8');
							}
						?>
					</a>
				</span>
			</td>
			<td align="left">
				<?php
					if (JString::strlen($row->filename_original) > 25) {
						$filename = JString::substr( htmlspecialchars($row->filename_original, ENT_QUOTES, 'UTF-8'), 0 , 25).'...';
					} else {
						$filename = htmlspecialchars($row->filename_original, ENT_QUOTES, 'UTF-8');
					}
				?>
				<span class="editlinktip hasTip" title="<?php echo JText::_('FLEXI_FILENAME'); ?>::<?php echo htmlspecialchars($row->filename_original, ENT_QUOTES, 'UTF-8'); ?>">
				<?php echo $row->filename_original; ?>
				</span>
			</td>
			<td>
				<?php
				if (JString::strlen($row->altname) > 25) {
					echo JString::substr( htmlspecialchars($row->altname, ENT_QUOTES, 'UTF-8'), 0 , 25).'...';
				} else {
					echo htmlspecialchars($row->altname, ENT_QUOTES, 'UTF-8');
				}
				?>
			</td>
<?php if (!$this->folder_mode) { ?>
			<td align="center">
				<?php echo JHTML::image('components/com_flexicontent/assets/images/'. ($row->published ? 'tick.png' : 'publish_x.png'), JText::_('FLEXI_REMOVE') ); ?>
			</td>
<?php } ?>
			
			<td align="center">
			<?php
			$is_authorised = $this->CanFiles && ($this->CanViewAllFiles || $user->id == $row->uploaded_by);
			if (FLEXI_J16GE) {
				if ($is_authorised) {
					$access = flexicontent_html::userlevel('access['.$row->id.']', $row->access, 'onchange="return listItemTask(\'cb'.$i.'\',\'filemanager.access\')"');
				} else {
					$access = strlen($row->access_level) ? $this->escape($row->access_level) : '-';
				}
			} else if (FLEXI_ACCESS) {
				if ($is_authorised) {
					$access 	= FAccess::accessswitch('file', $row, $i);
				} else {
					$access 	= FAccess::accessswitch('file', $row, $i, 'content', 1);
				}
			} else {
				$access = JHTML::_('grid.access', $row, $i );
			}
			echo $access;
			?>
			</td>
			
<?php if (!$this->folder_mode) { ?>
			<?php
			// Set a row language, even if empty to avoid errors
			$row->language = @$row->language ? $row->language : '*';
   		?>
			<td align="center" class="hasTip col_lang" title="<?php echo JText::_( 'FLEXI_LANGUAGE', true ).'::'.($row->language=='*' ? JText::_("All") : $this->langs->{$row->language}->name); ?>">
				<?php if ( !empty($row->language) && !empty($this->langs->{$row->language}->imgsrc) ) : ?>
					<img src="<?php echo $this->langs->{$row->language}->imgsrc; ?>" alt="<?php echo $row->language; ?>" />
				<?php elseif( !empty($row->language) ) : ?>
					<?php echo $row->language=='*' ? JText::_("FLEXI_ALL") : $row->language;?>
				<?php endif; ?>
			</td>
<?php } ?>
			
			<td align="center"><?php echo $row->size; ?></td>
			<td align="center"><?php echo $row->hits; ?></td>
			<td align="center"><?php echo $row->uploader; ?></td>
			<td align="center"><?php echo JHTML::Date( $row->uploaded, JText::_( 'DATE_FORMAT_LC2' ) ); ?></td>
<?php if (!$this->folder_mode) { ?>
			<td align="center"><?php echo $row->id; ?></td>
<?php } ?>
		</tr>
		<?php 
			$k = 1 - $k;
			$i++;
		} 
		?>
	</tbody>

	</table>
	<?php echo JHTML::_( 'form.token' ); ?>
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="controller" value="filemanager" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="file" value="" />
	<input type="hidden" name="files" value="<?php echo $this->files; ?>" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<input type="hidden" name="fieldid" value="<?php echo $this->fieldid; ?>" />
	<input type="hidden" name="u_item_id" value="<?php echo $this->u_item_id; ?>" />
	<input type="hidden" name="folder_mode" value="<?php echo $this->folder_mode; ?>" />
	<input type="hidden" name="filename" value="" />
</form>
</div>
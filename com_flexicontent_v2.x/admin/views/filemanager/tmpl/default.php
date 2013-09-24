<?php
/**
 * @version 1.5 stable $Id: default.php 1577 2012-12-02 15:10:44Z ggppdk $
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
$ctrl_task  = FLEXI_J16GE ? 'task=filemanager.'  :  'controller=filemanager&amp;task=';
$ctrl_task_authors = FLEXI_J16GE ? 'task=users.'  :  'controller=users&amp;task=';
$permissions = FlexicontentHelperPerm::getPerm();
?>
<style>
table#filemanager-zone label {
	clear:none;
}
</style>

<div class="flexicontent">
<table width="100%" border="0" style="padding: 5px; margin-bottom: 10px;" id="filemanager-zone">
	<tr>
		<td>
			<?php
			echo FLEXI_J16GE ? JHtml::_('tabs.start') : $this->pane->startPane( 'stat-pane' );
			if ($this->CanUpload) :
				echo FLEXI_J16GE ?
					JHtml::_('tabs.panel', JText::_( 'FLEXI_UPLOAD_LOCAL_FILE' ), 'local' ) :
					$this->pane->startPanel( JText::_( 'FLEXI_UPLOAD_LOCAL_FILE' ), 'local' ) ;
			?>
		    <?php if ($this->require_ftp): ?>
            <form action="index.php?option=com_flexicontent&amp;<?php echo $ctrl_task; ?>ftpValidate" name="ftpForm" id="ftpForm" method="post">
                <fieldset title="<?php echo JText::_( 'FLEXI_DESCFTPTITLE' ); ?>">
                    <legend><?php echo JText::_( 'FLEXI_DESCFTPTITLE' ); ?></legend>
                    <?php echo JText::_( 'FLEXI_DESCFTP' ); ?>
                    <table class="adminform nospace">
                        <tbody>
                            <tr>
                                <td width="120">
                                    <label for="username"><?php echo JText::_( 'FLEXI_USERNAME' ); ?>:</label>
                                </td>
                                <td>
                                    <input type="text" id="username" name="username" class="input_box" size="70" value="" />
                                </td>
                            </tr>
                            <tr>
                                <td width="120">
                                    <label for="password"><?php echo JText::_( 'FLEXI_PASSWORD' ); ?>:</label>
                                </td>
                                <td>
                                    <input type="password" id="password" name="password" class="input_box" size="70" value="" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </fieldset>
            </form>
            <?php endif; ?>
			
			<!-- File Upload Form -->
			<form action="<?php echo JURI::base(); ?>index.php?option=com_flexicontent&amp;<?php echo $ctrl_task; ?>upload&amp;<?php echo $this->session->getName().'='.$this->session->getId(); ?>" id="uploadForm" method="post" enctype="multipart/form-data">
				<fieldset>
					<legend><?php echo JText::_( 'FLEXI_CHOOSE_FILE' ); ?> [ <?php echo JText::_( 'FLEXI_MAX' ); ?>&nbsp;<?php echo ($this->params->get('upload_maxsize') / 1000000); ?>M ]</legend>
					<fieldset class="actions" id="filemanager-1">

						<table class="admintable" cellspacing="0" cellpadding="0" border="0" width="100%">
							<tr>
								<td class="key">
									<label for="file-upload">
									<?php echo JText::_( 'FLEXI_CHOOSE_FILE' ); ?>
									</label>
								</td>
								<td>
									<input type="file" id="file-upload" name="Filedata" />
								</td>
							</tr>
							<tr>
								<td class="key hasTip"  title="<?php echo JText::_( 'FLEXI_CHOOSE_DIR' ); ?>">
									<label for="secure">
									<?php echo JText::_( 'FLEXI_FILE_DIRECTORY' ); ?>
									</label>
								</td>
								<td>
									<?php
									echo JHTML::_('select.booleanlist', 'secure', 'class="inputbox"', 1, JText::_( 'FLEXI_SECURE' ), JText::_( 'FLEXI_MEDIA' ) );
									?>
								</td>
							</tr>
							<tr>
								<td class="key">
									<label for="file-title">
									<?php echo JText::_( 'FLEXI_FILE_TITLE' ); ?>
									</label>
								</td>
								<td>
									<input type="text" id="file-title" size="40" class="required" name="file-title" />
								<td>
							</tr>
							<tr>
								<td class="key">
									<label for="file-desc">
									<?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?>
									</label>
								</td>
								<td>
									<textarea name="file-desc" cols="23" rows="5" id="file-desc"></textarea>
								</td>
							</tr>
						</table>
						<input type="submit" id="file-upload-submit" style="margin: 5px 0 0 150px;" value="<?php echo JText::_( 'FLEXI_START_UPLOAD' ); ?>"/>
						<span id="upload-clear"></span>
						
					</fieldset>
					
					<ul class="upload-queue" id="upload-queue">
						<li style="display: none" />
					</ul>
				</fieldset>
				<?php echo JHTML::_( 'form.token' ); ?>
				<input type="hidden" name="return-url" value="<?php echo base64_encode('index.php?option=com_flexicontent&view=filemanager'); ?>" />
			</form>
			<?php
			echo FLEXI_J16GE ? '' : $this->pane->endPanel();
			endif;
			?>
			<!-- File URL Form -->
			<?php echo FLEXI_J16GE ? JHtml::_('tabs.panel', JText::_( 'FLEXI_ADD_FILE_BY_URL' ), 'fileurl' ) : $this->pane->startPanel( JText::_( 'FLEXI_ADD_FILE_BY_URL' ), 'fileurl' ) ; ?>
			<form action="<?php echo JURI::base(); ?>index.php?option=com_flexicontent&amp;<?php echo $ctrl_task; ?>addurl&amp;<?php echo $this->session->getName().'='.$this->session->getId(); ?>&amp;<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1" class="form-validate" name="urlForm" id="urlForm" method="post">
				<fieldset>
					<legend><?php echo JText::_( 'FLEXI_ADD_FILE_BY_URL' ); ?></legend>
					<fieldset class="actions">
						<table class="admintable" cellspacing="0" cellpadding="0" border="0" width="100%">
							<tr>
								<td class="key">
									<label for="file-url-data">
									<?php echo JText::_( 'FLEXI_FILE_URL' ); ?>
									</label>
								</td>
								<td>
									<input type="text" id="file-url-data" size="40" class="required" name="file-url-data" />
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
							</tr>
							<tr>
								<td class="key">
									<label for="file-url-title">
									<?php echo JText::_( 'FLEXI_FILE_TITLE' ); ?>
									</label>
								</td>
								<td>
									<input type="text" id="file-url-title" size="40" class="required" name="file-url-title" />
								</td>
							</tr>
							<tr>
								<td class="key">
									<label for="file-url-desc">
									<?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?>
									</label>
								</td>
								<td>
									<textarea name="file-url-desc" cols="23" rows="5" id="file-url-desc"></textarea>
								</td>
							</tr>
						</table>
						<input type="submit" id="file-url-submit" style="margin: 5px 0 0 150px;" class="validate" value="<?php echo JText::_( 'FLEXI_ADD_FILE' ); ?>"/>
					</fieldset>
				</fieldset>
				<input type="hidden" name="return-url" value="<?php echo base64_encode('index.php?option=com_flexicontent&view=filemanager'); ?>" />
			</form>
			<?php echo FLEXI_J16GE ? '' : $this->pane->endPanel(); ?>
			<?php
			if ($this->CanUpload) :
				echo FLEXI_J16GE ? JHtml::_('tabs.panel', JText::_( 'FLEXI_ADD_FILE_FROM_SERVER' ), 'server' ) : $this->pane->startPanel( JText::_( 'FLEXI_ADD_FILE_FROM_SERVER' ), 'server' ) ;
			?>
			<!-- File from server Form -->
			<form action="index.php?option=com_flexicontent&amp;<?php echo $ctrl_task; ?>addlocal&amp;<?php echo $this->session->getName().'='.$this->session->getId(); ?>&amp;<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1" class="form-validate" name="urlForm" id="urlForm" method="post">
				<fieldset>
					<legend>
						<?php echo JText::_( 'FLEXI_ADD_FILE_FROM_SERVER' ); ?>
					</legend>
					<fieldset class="actions">

						<table class="admintable" cellspacing="0" cellpadding="0" border="0" width="100%">
							<tr>
								<td class="key hasTip"  title="<?php echo JText::_( 'FLEXI_CHOOSE_DIR_PATH_DESC' ); ?>">
									<label for="file-dir-path">
									<?php echo JText::_( 'FLEXI_CHOOSE_DIR_PATH' ); ?>
									</label>
								</td>
								<td>
									<input type="text" id="file-dir-path" size="50" value="/tmp" class="required" name="file-dir-path" />
								</td>
							</tr>
							<tr>
								<td class="key hasTip"  title="<?php echo JText::_( 'FLEXI_FILE_FILTER_EXT' ); ?>::<?php echo JText::_( 'FLEXI_FILE_FILTER_EXT_DESC' ); ?>">
									<label for="file-filter-ext">
									<?php echo JText::_( 'FLEXI_FILE_FILTER_EXT' ); ?>
									</label>
								</td>
								<td>
									<input type="text" id="file-filter-ext" size="30" value="" name="file-filter-ext" />
								</td>
							</tr>
							<tr>
								<td class="key hasTip"  title="<?php echo JText::_( 'FLEXI_FILE_FILTER_REGEX' ); ?>::<?php echo JText::_( 'FLEXI_FILE_FILTER_REGEX_DESC' ); ?>">
									<label for="file-filter-re">
									<?php echo JText::_( 'FLEXI_FILE_FILTER_REGEX' ); ?>
									</label>
								</td>
								<td>
									<input type="text" id="file-filter-re" size="30" value="" name="file-filter-re" />
								</td>
							</tr>
							<tr>
								<td class="key hasTip"  title="<?php echo JText::_( 'FLEXI_KEEP_ORIGINAL_FILE_DESC' ); ?>">
									<label for="secure">
									<?php echo JText::_( 'FLEXI_KEEP_ORIGINAL_FILE' ); ?>
									</label>
								</td>
								<td>
									<?php
									echo JHTML::_('select.booleanlist', 'keep', 'class="inputbox"', 1, JText::_( 'FLEXI_YES' ), JText::_( 'FLEXI_NO' ) );
									?>
								</td>
							</tr>
							<tr>
								<td class="key hasTip"  title="<?php echo JText::_( 'FLEXI_CHOOSE_DIR' ); ?>">
									<label for="secure">
									<?php echo JText::_( 'FLEXI_FILE_DIRECTORY' ); ?>
									</label>
								</td>
								<td>
									<?php
									echo JHTML::_('select.booleanlist', 'secure', 'class="inputbox"', 1, JText::_( 'FLEXI_SECURE' ), JText::_( 'FLEXI_MEDIA' ) );
									?>
								</td>
							</tr>
							<tr>
								<td class="key">
									<label for="file-desc">
									<?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?>
									</label>
								</td>
								<td>
									<textarea name="file-desc" cols="23" rows="5" id="file-desc"></textarea>
								</td>
							</tr>
						</table>
						<input type="submit" id="file-dir-submit" style="margin: 5px 0 0 150px;" class="validate" value="<?php echo JText::_( 'FLEXI_ADD_DIR' ); ?>"/>
					</fieldset>
				</fieldset>
				<input type="hidden" name="return-url" value="<?php echo base64_encode('index.php?option=com_flexicontent&view=filemanager'); ?>" />
			</form>
			<?php echo FLEXI_J16GE ? '' : $this->pane->endPanel(); ?>
			<?php endif; ?>
			<?php echo FLEXI_J16GE ? JHtml::_('tabs.end') : $this->pane->endPane(); ?>
		</td>
	</tr>
</table>

<form action="<?php echo JURI::base(); ?>index.php" method="post" name="adminForm" id="adminForm">

	<table class="adminform">
		<tr>
			<td width="100%">
				<label class="label"><?php echo JText::_( 'FLEXI_SEARCH' ); ?></label>
				<?php echo $this->lists['filter']; ?>
				<input type="text" name="search" id="search" value="<?php echo $this->lists['search']; ?>" class="text_area" onChange="document.adminForm.submit();" />
				<button class="fc_button fcsimple" onclick="this.form.submit();"><?php echo JText::_( 'FLEXI_GO' ); ?></button>
				<button class="fc_button fcsimple" onclick="this.form.getElementById('search').value='';this.form.submit();"><?php echo JText::_( 'FLEXI_RESET' ); ?></button>
			</td>
			<td nowrap="nowrap">
				<?php echo $this->lists['url']; ?>
			 	<?php echo $this->lists['secure']; ?>
			 	<?php echo $this->lists['ext']; ?>
			 	<?php if ($this->CanViewAllFiles) echo $this->lists['uploader']; ?>
			 	&nbsp; &nbsp; &nbsp;
				<label class="label">Item ID</label> <?php echo $this->lists['item_id']; ?>
			</td>
		</tr>
	</table>

	<table class="adminlist filemanager" cellspacing="1">
	<thead>
		<tr>
			<th width="5"><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			<th width="5"><input type="checkbox" name="toggle" value="" onClick="<?php echo FLEXI_J30GE ? 'Joomla.checkAll(this);' : 'checkAll('.count( $this->rows).');'; ?>" /></th>
			<th width="5"><?php echo JText::_( 'FLEXI_THUMB' ); ?></th>
			<th class="title"><?php echo JHTML::_('grid.sort', 'FLEXI_FILENAME', 'f.filename', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width=""><?php echo JHTML::_('grid.sort', 'FLEXI_FILE_TITLE', 'f.altname', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JText::_( 'FLEXI_PUBLISHED' ); ?></th>
			<th width=""><?php echo JText::_( 'FLEXI_ACCESS' ); ?></th>
			<th width=""><?php echo JText::_( 'FLEXI_SIZE' ); ?></th>
			<th width="15"><?php echo JHTML::_('grid.sort', 'FLEXI_HITS', 'f.hits', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width=""><?php echo JText::_( 'FLEXI_FILE_ITEM_ASSIGNMENTS' ); ?> </th>
			<th width=""><?php echo JHTML::_('grid.sort', 'FLEXI_UPLOADER', 'uploader', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width=""><?php echo JHTML::_('grid.sort', 'FLEXI_UPLOAD_TIME', 'f.uploaded', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JHTML::_('grid.sort', 'FLEXI_ID', 'f.id', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
		</tr>

	</thead>

	<tfoot>
		<tr>
			<td colspan="13">
				<?php echo $this->pageNav->getListFooter(); ?>
			</td>
		</tr>
		
		<?php
		$field_legend = array();
		$this->assigned_fields_labels;
		foreach($this->assigned_fields_labels as $field_type => $field_label) {
			$icon_name = $this->assigned_fields_icons[$field_type];
			$tip = $field_label;
			$image = JHTML::image('administrator/components/com_flexicontent/assets/images/'.$icon_name.'.png', $tip);
			$field_legend[$field_type] = $image. " ".$field_label;
		}
		?>
		
		<tr>
			<td colspan="13" align="center" style="border-top:0px solid black;">
				<span class="fc_legend_box hasTip" title="<?php echo JText::_('FLEXI_FILE_ITEM_ASSIGNMENTS_LEGEND').'::'.JText::_('FLEXI_FILE_ITEM_ASSIGNMENTS_LEGEND_TIP'); ?> " ><?php echo JText::_('FLEXI_FILE_ITEM_ASSIGNMENTS_LEGEND'); ?></span> : &nbsp; 
				<?php echo implode(' &nbsp; &nbsp; | &nbsp; &nbsp; ', $field_legend); ?>
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
			if ( !in_array($row->ext, $imageexts)) $thumb_or_icon = JHTML::image($row->icon, $row->filename);
			
			$checked 	= JHTML::_('grid.checkedout', $row, $i );
			
			$path		= $row->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;  // JPATH_ROOT . DS . <media_path | file_path>
			$file_path = $row->filename;
			
			if (substr($row->filename, 0, 7)!='http://') {
				$file_path = $path . DS . $row->filename;
			} else {
				$thumb_or_icon = 'URL';
			}
			
			$file_path    = str_replace('\\', '/', $file_path);
			if ( empty($thumb_or_icon) ) {
				$thumb_or_icon = JURI::root() . 'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' . $file_path . '&w=60&h=60';
				$thumb_or_icon = "<img src=\"$thumb_or_icon\" alt=\"$filename\" />";
			}
			
			$row->count_assigned = 0;
			foreach($this->assigned_fields_labels as $field_type => $ignore) {
				$row->count_assigned += $row->{'assigned_'.$field_type};
			}
			if ($row->count_assigned)
			{
				$row->assigned = array();
				foreach($this->assigned_fields_labels as $field_type => $field_label) {
					if ( $row->{'assigned_'.$field_type} )
					{
						$icon_name = $this->assigned_fields_icons[$field_type];
						$tip = $row->{'assigned_'.$field_type} . ' ' . $field_label;
						$image = JHTML::image('administrator/components/com_flexicontent/assets/images/'.$icon_name.'.png', $tip, 'title="'.$field_type.' '.JText::_('FLEXI_FIELDS').'"' );
						$row->assigned[] = $row->{'assigned_'.$field_type} . ' ' . $image;
					}
				}
				$row->assigned = implode('&nbsp;&nbsp;| ', $row->assigned);
			} else {
				$row->assigned = JText::_( 'FLEXI_NOT_ASSIGNED' );
			}
			// link to items using the field
			$items_list = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_fileid='. $row->id;
   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td><?php echo $this->pageNav->getRowOffset( $i ); ?></td>
			<td width="7">
				<?php echo $checked; ?>
			</td>
			<td align="center">
				<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_SELECT' ); ?>::<?php echo $row->filename; ?>">
				<a style="cursor:pointer">
				<?php echo $thumb_or_icon; ?>
				</a>
				</span>
			</td>
			<td align="left">
				<?php echo ' <a href="index.php?option=com_flexicontent&amp;'.$ctrl_task.'edit&amp;cid[]='.$row->id.'">'.htmlspecialchars($row->filename, ENT_QUOTES, 'UTF-8').'</a>'; ?>
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
			<td align="center">
				<?php echo FLEXI_J16GE  ?  JHTML::_('jgrid.published', $row->published, $i, 'filemanager.' )  :  JHTML::_('grid.published', $row, $i ); ?>
			</td>
			
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
			
			<td align="center"><?php echo $row->size; ?></td>
			<td align="center"><?php echo $row->hits; ?></td>
			<td align="center">
				<?php echo $row->assigned; ?>
				<?php if ($row->count_assigned) : ?>
					<br/><br/>
					<?php echo count($row->itemids); ?>
					<a href="<?php echo $items_list; ?>">
					[<?php echo JText::_( 'FLEXI_VIEW_ITEMS' );?>]
				<?php endif; ?>
			</td>
			<td align="center">
<?php if ($permissions->CanAuthors) { ?>
				<a target="_blank" href="index.php?option=com_flexicontent&amp;<?php echo $ctrl_task_authors; ?>edit&amp;hidemainmenu=1&amp;cid[]=<?php echo $row->uploaded_by; ?>">
					<?php echo $row->uploader; ?>
				</a>
<?php } else { ?>
				<?php echo $row->uploader; ?>
<?php } ?>
			</td>
			<td align="center"><?php echo JHTML::Date( $row->uploaded, JText::_( 'DATE_FORMAT_LC2' ) );; ?></td>
			<td align="center"><?php echo $row->id; ?></td>
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
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="view" value="filemanager" />
	<input type="hidden" name="controller" value="filemanager" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
</form>
</div>
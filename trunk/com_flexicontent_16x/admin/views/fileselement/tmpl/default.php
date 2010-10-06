<?php
/**
 * @version 1.5 stable $Id: default.php 269 2010-06-11 09:04:21Z enjoyman $
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
?>
<table width="100%" border="0" style="padding: 5px; margin-bottom: 10px;">
	<tr>
		<td>
			<?php
			echo $this->pane->startPane( 'stat-pane' );
			if ($this->CanUpload) :
			echo $this->pane->startPanel( JText::_( 'FLEXI_UPLOAD_LOCAL_FILE' ), 'local' );
			?>
			<!-- File Upload Form -->
            <form action="<?php echo JURI::base(); ?>index.php?option=com_flexicontent&amp;controller=filemanager&amp;task=upload&amp;<?php echo $this->session->getName().'='.$this->session->getId(); ?>&amp;<?php echo JUtility::getToken();?>=1" id="uploadForm" method="post" enctype="multipart/form-data">
                <fieldset>
                    <legend><?php echo JText::_( 'FLEXI_CHOOSE_FILE' ); ?> [ <?php echo JText::_( 'FLEXI_MAX' ); ?>&nbsp;<?php echo ($this->params->get('upload_maxsize') / 1000000); ?>M ]</legend>
                    <fieldset class="actions">
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
									$secure	= JText::_( 'FLEXI_SECURE' );
									$media	= JText::_( 'FLEXI_MEDIA' );
									$html	= JHTML::_('select.booleanlist', 'secure', 'class="inputbox"', 1, $secure, $media );
									echo $html;
									?>
								</td>
							</tr>
						</table>
                        <input type="submit" id="file-upload-submit" style="margin: 5px 0 0 150px;" class="" value="<?php echo JText::_( 'FLEXI_START_UPLOAD' ); ?>"/>
                        <span id="upload-clear"></span>
                    </fieldset>
                    <ul class="upload-queue" id="upload-queue">
                        <li style="display: none" />
                    </ul>
                </fieldset>
                <input type="hidden" name="return-url" value="<?php echo base64_encode('index.php?option=com_flexicontent&view=fileselement&field='.$this->fieldid.'&tmpl=component'); ?>" />
            </form>
			<?php
			echo $this->pane->endPanel();
			endif;
			echo $this->pane->startPanel( JText::_( 'FLEXI_ADD_FILE_BY_URL' ), 'fileurl' );
			?>
			<!-- File URL Form -->
			<form action="<?php echo JURI::base(); ?>index.php?option=com_flexicontent&amp;controller=filemanager&amp;task=addurl&amp;<?php echo $this->session->getName().'='.$this->session->getId(); ?>&amp;<?php echo JUtility::getToken();?>=1" class="form-validate" name="urlForm" id="urlForm" method="post">
				<fieldset>
					<legend><?php echo JText::_( 'FLEXI_ADD_FILE_BY_URL' ); ?></legend>
					<fieldset class="actions">
						<table class="admintable" cellspacing="0" cellpadding="0" border="0" width="100%">
							<tr>
								<td class="key">
									<label for="file-url-display">
									<?php echo JText::_( 'FLEXI_DISPLAY_NAME' ); ?>
									</label>
								</td>
								<td>
									<input type="text" id="file-url-display" size="40" class="required" name="file-url-display" />
								</td>
							</tr>
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
						</table>
                        <input type="submit" id="file-url-submit" style="margin: 5px 0 0 150px;" class="validate" value="<?php echo JText::_( 'FLEXI_ADD_FILE' ); ?>"/>
					</fieldset>
				</fieldset>
                <input type="hidden" name="return-url" value="<?php echo base64_encode('index.php?option=com_flexicontent&view=fileselement&field='.$this->fieldid.'&tmpl=component'); ?>" />
			</form>
<?php
echo $this->pane->endPanel();
echo $this->pane->endPane();
?>
		</td>
	</tr>
</table>
<form action="<?php echo JURI::base(); ?>index.php?option=com_flexicontent&amp;view=fileselement&amp;field=<?php echo $this->fieldid?>&amp;tmpl=component" method="post" name="adminForm">
	<table class="adminform">
		<tr>
			<td width="100%">
			  	<?php echo JText::_( 'FLEXI_SEARCH' ); ?>
			  	<?php echo $this->lists['filter']; ?>
				<input type="text" name="search" id="search" value="<?php echo $this->lists['search']; ?>" class="text_area" onChange="document.adminForm.submit();" />
				<button onclick="this.form.submit();"><?php echo JText::_( 'FLEXI_GO' ); ?></button>
				<button onclick="this.form.getElementById('search').value='';this.form.submit();"><?php echo JText::_( 'FLEXI_RESET' ); ?></button>
			</td>
			<td nowrap="nowrap">
			 	<?php echo $this->lists['items']; ?>
				<?php echo $this->lists['url']; ?>
			 	<?php echo $this->lists['secure']; ?>
			 	<?php echo $this->lists['ext']; ?>
			 	<?php if ($this->CanViewAllFiles) echo $this->lists['uploader']; ?>
			</td>
		</tr>
	</table>

	<table class="adminlist" cellspacing="1">
	<thead>
		<tr>
			<th width="5"><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			<th class="title"><?php echo JHTML::_('grid.sort', 'FLEXI_FILENAME', 'f.filename', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="20%"><?php echo JHTML::_('grid.sort', 'FLEXI_DISPLAY_NAME', 'f.altname', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JText::_( 'FLEXI_PUBLISHED' ); ?></th>
			<th width="10%"><?php echo JText::_( 'FLEXI_SIZE' ); ?></th>
			<th width="15"><?php echo JHTML::_('grid.sort', 'FLEXI_HITS', 'f.hits', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
<!-- 			<th width="15"><?php // echo JHTML::_('grid.sort', 'ASSIGNED TO', 'nrassigned', $this->lists['order_Dir'], $this->lists['order'] ); ?></th> -->
			<th width="10%"><?php echo JHTML::_('grid.sort', 'FLEXI_UPLOADER', 'uploader', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="10%"><?php echo JHTML::_('grid.sort', 'FLEXI_UPLOAD_TIME', 'f.uploaded', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JHTML::_('grid.sort', 'FLEXI_ID', 'f.id', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
		</tr>
	</thead>

	<tfoot>
		<tr>
			<td colspan="10">
				<?php echo $this->pageNav->getListFooter(); ?>
			</td>
		</tr>
	</tfoot>

	<tbody>
		<?php
		$index = JRequest::getInt('index', 0);
		$k = 0;
		$i = 0;
		$n = count($this->rows);
		foreach ($this->rows as $row) {
   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td><?php echo $this->pageNav->getRowOffset( $i ); ?></td>
			<td align="left">
				<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_SELECT' );?>::<?php echo $row->filename; ?>">
				<a style="cursor:pointer" onclick="qffileselementadd('<?php echo $row->id; ?>', '<?php echo str_replace( array("'", "\""), array("\\'", ""), $row->filename ); ?>');">
				<?php echo JHTML::image($row->icon, '').' '.htmlspecialchars($row->filename, ENT_QUOTES, 'UTF-8'); ?>
				</a></span>
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
			<td align="center"><?php
				//$published = JHTML::_('grid.published', $row, $i );
				//echo $published;
				echo JHTML::image('administrator/images/tick.png', '');
				?></td>
			<td align="center"><?php echo $row->size; ?></td>
			<td align="center"><?php echo $row->hits; ?></td>
<!-- 			<td align="center"><?php // echo $row->nrassigned; ?></td> -->
			<td align="center"><?php echo $row->uploader; ?></td>
			<td align="center"><?php echo JHTML::Date( $row->uploaded, JText::_( 'DATE_FORMAT_LC4' ) ); ?></td>
			<td align="center"><?php echo $row->id; ?></td>
		</tr>
		<?php 
		$k = 1 - $k;
        $i++;
		} 
		?>
	</tbody>

	</table>
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="file" value="" />
	<input type="hidden" name="files" value="<?php echo $this->files; ?>" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
</form>
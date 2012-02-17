<?php
/**
 * @version 1.5 stable $Id: default.php 331 2010-06-23 06:43:09Z emmanuel.danan $
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
<style>
table#filemanager-zone label{
	clear:none;
}
</style>
<table width="100%" border="0" style="padding: 5px; margin-bottom: 10px;" id="filemanager-zone">
	<tr>
		<td>
			<?php
			echo $this->pane->startPane( 'stat-pane' );
			if ($this->permission->CanUpload) :
			echo $this->pane->startPanel( JText::_( 'FLEXI_UPLOAD_LOCAL_FILE' ), 'local' );
			?>
		    <?php if ($this->require_ftp): ?>
            <form action="index.php?option=com_flexicontent&amp;task=filemanager.ftpValidate" name="ftpForm" id="ftpForm" method="post">
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
            <form action="<?php echo JURI::base(); ?>index.php?option=com_flexicontent&amp;task=filemanager.upload&amp;<?php echo $this->session->getName().'='.$this->session->getId(); ?>&amp;<?php echo JUtility::getToken();?>=1" id="uploadForm" method="post" enctype="multipart/form-data">
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
                <input type="hidden" name="return-url" value="<?php echo base64_encode('index.php?option=com_flexicontent&view=filemanager'); ?>" />
            </form>
			<?php
			echo $this->pane->endPanel();
			endif;
			echo $this->pane->startPanel( JText::_( 'FLEXI_ADD_FILE_BY_URL' ), 'fileurl' );
			?>
			<!-- File URL Form -->
			<form action="<?php echo JURI::base(); ?>index.php?option=com_flexicontent&amp;task=filemanager.addurl&amp;<?php echo $this->session->getName().'='.$this->session->getId(); ?>&amp;<?php echo JUtility::getToken();?>=1" class="form-validate" name="urlForm" id="urlForm" method="post">
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
			<?php
			echo $this->pane->endPanel();
			if ($this->permission->CanUpload) :
			echo $this->pane->startPanel( JText::_( 'FLEXI_ADD_FILE_FROM_SERVER' ), 'server' );
			?>
			<!-- File from server Form -->
			<form action="index.php?option=com_flexicontent&amp;task=filemanager.addlocal&amp;<?php echo $this->session->getName().'='.$this->session->getId(); ?>&amp;<?php echo JUtility::getToken();?>=1" class="form-validate" name="urlForm" id="urlForm" method="post">
				<fieldset>
					<legend><?php echo JText::_( 'FLEXI_ADD_FILE_FROM_SERVER' ); ?></legend>
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
			<?php
			echo $this->pane->endPanel();
			endif;
			echo $this->pane->endPane();
			?>
		</td>
	</tr>
</table>

<form action="index.php" method="post" name="adminForm" id="adminForm">

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
				<?php echo $this->lists['item_id']; ?>
			 	<?php echo $this->lists['url']; ?>
			 	<?php echo $this->lists['secure']; ?>
			 	<?php echo $this->lists['ext']; ?>
			 	<?php if ($this->permission->CanViewAllFiles) echo $this->lists['uploader']; ?>
			</td>
		</tr>
	</table>

	<table class="adminlist filemanager" cellspacing="1">
	<thead>
		<tr>
			<th width="5"><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			<th width="5"><input type="checkbox" name="toggle" value="" onClick="checkAll(<?php echo count( $this->rows ); ?>);" /></th>
			<th class="title"><?php echo JHTML::_('grid.sort', 'FLEXI_FILENAME', 'f.filename', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="20%"><?php echo JHTML::_('grid.sort', 'FLEXI_DISPLAY_NAME', 'f.altname', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JText::_( 'FLEXI_PUBLISHED' ); ?></th>
			<th width="7%"><?php echo JText::_( 'FLEXI_SIZE' ); ?></th>
			<th width="15"><?php echo JHTML::_('grid.sort', 'FLEXI_HITS', 'f.hits', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="10%"><?php echo JText::_( 'FLEXI_ASSIGNED' ); ?></th>
			<th width="10%"><?php echo JHTML::_('grid.sort', 'FLEXI_UPLOADER', 'uploader', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="10%"><?php echo JHTML::_('grid.sort', 'FLEXI_UPLOAD_TIME', 'f.uploaded', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JHTML::_('grid.sort', 'FLEXI_ID', 'f.id', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
		</tr>
	</thead>

	<tfoot>
		<tr>
			<td colspan="11">
				<?php echo $this->pageNav->getListFooter(); ?>
			</td>
		</tr>
	</tfoot>

	<tbody>
		<?php
		$k = 0;
		$i = 0;
		$n = count($this->rows);
		foreach ($this->rows as $row) {
			$checked 	= JHTML::_('grid.checkedout', $row, $i );
		
			if ($row->nrassigned + $row->iassigned)
			{
				$row->assigned = array();
				if ($row->iassigned)
				{
					$tip = $row->iassigned . ' ' . JText::_( 'FLEXI_IMAGES' );
					$image = JHTML::image('administrator/components/com_flexicontent/assets/images/picture_link.png', $tip);
					$row->assigned[] = $row->iassigned . ' ' . $image;
				}
				if ($row->nrassigned)
				{
					$tip = $row->nrassigned . ' ' . JText::_( 'FLEXI_FILES' );
					$image = JHTML::image('administrator/components/com_flexicontent/assets/images/page_link.png', $tip);
					$row->assigned[] = $row->nrassigned . ' ' . $image;
				}
				$row->assigned = implode('&nbsp;&nbsp;| ', $row->assigned);
			} else {
				$row->assigned = JText::_( 'FLEXI_NOT_ASSIGNED' );
			}

   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td><?php echo $this->pageNav->getRowOffset( $i ); ?></td>
			<td width="7">
   				<?php echo $checked; ?>
   			</td>
			<td align="left">
				<?php echo JHTML::image($row->icon, '').' <a href="index.php?option=com_flexicontent&amp;task=filemanager.edit&amp;cid[]='.$row->id.'">'.htmlspecialchars($row->filename, ENT_QUOTES, 'UTF-8').'</a>'; ?>
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
				$published 	= JHTML::_('jgrid.published', $row->published, $i, 'filemanager.' );
				echo $published;
				?></td>
			<td align="center"><?php echo $row->size; ?></td>
			<td align="center"><?php echo $row->hits; ?></td>
			<td align="center"><?php echo $row->assigned; ?></td>
			<td align="center">
				<a href="<?php echo 'index.php?option=com_users&amp;task=edit&amp;hidemainmenu=1&amp;cid[]='.$row->uploaded_by; ?>">
					<?php echo $row->uploader; ?>
				</a>
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

	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="filemanager" />
	<input type="hidden" name="view" value="filemanager" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<?php echo JHTML::_( 'form.token' ); ?>
</form>

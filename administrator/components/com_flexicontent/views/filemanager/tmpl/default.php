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

$ctrl_task  = FLEXI_J16GE ? 'task=filemanager.'  :  'controller=filemanager&amp;task=';
$ctrl_task_authors = FLEXI_J16GE ? 'task=users.'  :  'controller=users&amp;task=';
$permissions = FlexicontentHelperPerm::getPerm();
$session = JFactory::getSession();
$document = JFactory::getDocument();

// Load JS tabber lib
$document->addScript( JURI::root().'components/com_flexicontent/assets/js/tabber-minimized.js' );
$document->addStyleSheet( JURI::root().'components/com_flexicontent/assets/css/tabber.css' );
$document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs
?>

<div id="flexicontent" class="flexicontent">
<table width="100%" border="0" style="padding: 5px; margin-bottom: 10px;" id="filemanager-zone">
	<tr>
		<td>
			<div class="fctabber" style=''>
			
			<!-- File(s) by uploading -->
			<?php
			//echo FLEXI_J16GE ? JHtml::_('tabs.start') : $this->pane->startPane( 'stat-pane' );
			
			if ($this->CanUpload) :
				/*echo FLEXI_J16GE ?
					JHtml::_('tabs.panel', JText::_( 'FLEXI_UPLOAD_LOCAL_FILE' ), 'local' ) :
					$this->pane->startPanel( JText::_( 'FLEXI_UPLOAD_LOCAL_FILE' ), 'local' ) ;*/
			?>
			<div class="tabbertab" style="padding: 0px;" id="local_tab" >
				<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_UPLOAD_LOCAL_FILE' ); ?> </h3>
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
			<form action="<?php echo JURI::base(); ?>index.php?option=com_flexicontent&amp;<?php echo $ctrl_task; ?>upload&amp;<?php echo $session->getName().'='.$session->getId(); ?>" name="uploadFileForm" id="uploadFileForm" method="post" enctype="multipart/form-data">
				<fieldset class="filemanager-tab" >
					<legend><?php echo JText::_( 'FLEXI_CHOOSE_FILE' ); ?> [ <?php echo JText::_( 'FLEXI_MAX' ); ?>&nbsp;<?php echo ($this->params->get('upload_maxsize') / 1000000); ?>M ]</legend>
					<fieldset class="actions" id="filemanager-1">
						
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
								
								<td class="key">
									<label>
									<?php echo JText::_( 'FLEXI_LANGUAGE' ); ?>
									</label>
								</td>
								<td>
									<?php echo $this->lists['file-lang']; ?>
								</td>
							</tr>
							
							<tr>
								<td class="key hasTip"  title="<?php echo JText::_( 'FLEXI_CHOOSE_DIR' ); ?>">
									<label>
									<?php echo JText::_( 'FLEXI_FILE_DIRECTORY' ); ?>
									</label>
								</td>
								<td>
									<?php echo JHTML::_('select.booleanlist', 'secure', 'class="inputbox"', 1, JText::_( 'FLEXI_SECURE' ), JText::_( 'FLEXI_MEDIA' ), 'secure_uploadFileForm' ); ?>
								</td>
								
								<td class="key" rowspan="3">
									<label for="file-desc_uploadFileForm">
									<?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?>
									</label>
								</td>
								<td valign="top" rowspan="3">
									<textarea name="file-desc" cols="24" rows="3" id="file-desc_uploadFileForm"></textarea>
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
							
						</table>
						
						<input type="submit" id="file-upload-submit" class="fc_button fcsimple" value="<?php echo JText::_( 'FLEXI_START_UPLOAD' ); ?>"/>
						<span id="upload-clear"></span>
						
					</fieldset>
					
					<ul class="upload-queue" id="upload-queue">
						<li style="display: none"></li>
					</ul>
				</fieldset>
				<?php echo JHTML::_( 'form.token' ); ?>
				<input type="hidden" name="return-url" value="<?php echo base64_encode('index.php?option=com_flexicontent&view=filemanager'); ?>" />
			</form>
			<?php /*echo FLEXI_J16GE ? '' : $this->pane->endPanel();*/ ?>
			</div>
			<?php endif; ?>
			
			
			<!-- File URL by Form -->
			<?php
				/*echo FLEXI_J16GE ?
					JHtml::_('tabs.panel', JText::_( 'FLEXI_ADD_FILE_BY_URL' ), 'fileurl' ) :
					$this->pane->startPanel( JText::_( 'FLEXI_ADD_FILE_BY_URL' ), 'fileurl' ) ;*/
			?>
			<div class="tabbertab" style="padding: 0px;" id="fileurl_tab" >
				<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_ADD_FILE_BY_URL' ); ?> </h3>
			
			<form action="<?php echo JURI::base(); ?>index.php?option=com_flexicontent&amp;<?php echo $ctrl_task; ?>addurl&amp;<?php echo $session->getName().'='.$session->getId(); ?>&amp;<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1" class="form-validate" name="addUrlForm" id="addUrlForm" method="post">
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
				<input type="hidden" name="return-url" value="<?php echo base64_encode('index.php?option=com_flexicontent&view=filemanager'); ?>" />
			</form>
			<?php /*echo FLEXI_J16GE ? '' : $this->pane->endPanel();*/ ?>
			</div>
			
			
			<!-- File(s) from server Form -->
			<?php
			if ($this->CanUpload) :
				/*echo FLEXI_J16GE ?
					JHtml::_('tabs.panel', JText::_( 'FLEXI_ADD_FILE_FROM_SERVER' ), 'server' ) :
					$this->pane->startPanel( JText::_( 'FLEXI_ADD_FILE_FROM_SERVER' ), 'server' ) ;*/
			?>
			<div class="tabbertab" style="padding: 0px;" id="server_tab" >
				<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_ADD_FILE_FROM_SERVER' ); ?> </h3>
			
			<form action="index.php?option=com_flexicontent&amp;<?php echo $ctrl_task; ?>addlocal&amp;<?php echo $session->getName().'='.$session->getId(); ?>&amp;<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1" class="form-validate" name="addFileForm" id="addFileForm" method="post">
				<fieldset class="filemanager-tab" >
					<legend>
						<?php echo JText::_( 'FLEXI_ADD_FILE_FROM_SERVER' ); ?>
					</legend>
					<fieldset class="actions" id="filemanager-3">

						<table class="admintable" cellspacing="0" cellpadding="0" border="0" width="100%">
							
							<tr>
								<td class="key hasTip"  title="<?php echo JText::_( 'FLEXI_CHOOSE_DIR_PATH_DESC' ); ?>">
									<label for="file-dir-path">
									<?php echo JText::_( 'FLEXI_CHOOSE_DIR_PATH' ); ?>
									</label>
								</td>
								<td width="260">
									<input type="text" id="file-dir-path" size="50" value="/tmp" class="required" name="file-dir-path" />
								</td>
								
								<td class="key">
									<label>
									<?php echo JText::_( 'FLEXI_LANGUAGE' ); ?>
									</label>
								</td>
								<td>
									<?php echo
										str_replace('id="file-lang', 'id="_file-lang',
										str_replace('id="file-lang', 'id="_file-lang', $this->lists['file-lang'])
										); ?>
								</td>
							</tr>
							
							<tr>
								<td class="key hasTip"  title="<?php echo JText::_( 'FLEXI_FILE_FILTER_EXT' ); ?>::<?php echo JText::_( 'FLEXI_FILE_FILTER_EXT_DESC' ); ?>">
									<label for="file-filter-ext">
									<?php echo JText::_( 'FLEXI_FILE_FILTER_EXT' ); ?>
									</label>
								</td>
								<td>
									<input type="text" id="file-filter-ext" size="50" value="" name="file-filter-ext" />
								</td>
								
								<td class="key" rowspan="4">
									<label for="file-desc_addFileForm">
									<?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?>
									</label>
								</td>
								<td rowspan="4">
									<textarea name="file-desc" cols="24" rows="6" id="file-desc_addFileForm"></textarea>
								</td>
							</tr>
							
							<tr>
								<td class="key hasTip"  title="<?php echo JText::_( 'FLEXI_FILE_FILTER_REGEX' ); ?>::<?php echo JText::_( 'FLEXI_FILE_FILTER_REGEX_DESC' ); ?>">
									<label for="file-filter-re">
									<?php echo JText::_( 'FLEXI_FILE_FILTER_REGEX' ); ?>
									</label>
								</td>
								<td>
									<input type="text" id="file-filter-re" size="50" value="" name="file-filter-re" />
								</td>
							</tr>
							
							<tr>
								<td class="key hasTip"  title="<?php echo JText::_( 'FLEXI_KEEP_ORIGINAL_FILE_DESC' ); ?>">
									<label>
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
									<label>
									<?php echo JText::_( 'FLEXI_FILE_DIRECTORY' ); ?>
									</label>
								</td>
								<td>
									<?php
									echo JHTML::_('select.booleanlist', 'secure', 'class="inputbox"', 1, JText::_( 'FLEXI_SECURE' ), JText::_( 'FLEXI_MEDIA' ), 'secure_addFileForm' );
									?>
								</td>
							</tr>
							
						</table>
						
						<input type="submit" id="file-dir-submit" class="fc_button fcsimple validate" value="<?php echo JText::_( 'FLEXI_ADD_DIR' ); ?>"/>
					</fieldset>
				</fieldset>
				<input type="hidden" name="return-url" value="<?php echo base64_encode('index.php?option=com_flexicontent&view=filemanager'); ?>" />
			</form>
			<?php /*echo FLEXI_J16GE ? '' : $this->pane->endPanel();*/ ?>
			</div>
			<?php endif; ?>
			
			<?php /*echo FLEXI_J16GE ? JHtml::_('tabs.end') : $this->pane->endPane();*/ ?>
			</div>
			
		</td>
	</tr>
</table>

<form action="<?php echo JURI::base(); ?>index.php" method="post" name="adminForm" id="adminForm">

	<table class="adminform" border="0">
		<tr>
			<td align="left">
				<label class="label"><?php echo JText::_( 'FLEXI_SEARCH' ); ?></label>
				<?php echo $this->lists['filter']; ?>
				<input type="text" name="search" id="search" value="<?php echo $this->lists['search']; ?>" class="text_area" onchange="document.adminForm.submit();" />
				<div id="fc-filter-buttons">
					<button class="fc_button fcsimple" onclick="this.form.submit();"><?php echo JText::_( 'FLEXI_GO' ); ?></button>
					<button class="fc_button fcsimple" onclick="this.form.getElementById('search').value='';this.form.submit();"><?php echo JText::_( 'FLEXI_RESET' ); ?></button>
				</div>
			</td>
			<td nowrap="nowrap">
				<div class="limit" style="display: inline-block;">
					<?php echo JText::_(FLEXI_J16GE ? 'JGLOBAL_DISPLAY_NUM' : 'DISPLAY NUM') . str_replace('id="limit"', 'id="limit_top"', $this->pagination->getLimitBox()); ?>
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

	<table class="adminlist filemanager" cellspacing="1">
	<thead>
		<tr>
			<th width="5"><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			<th width="5"><input type="checkbox" name="toggle" value="" onclick="<?php echo FLEXI_J30GE ? 'Joomla.checkAll(this);' : 'checkAll('.count( $this->rows).');'; ?>" /></th>
			<th width="5"><?php echo JText::_( 'FLEXI_THUMB' ); ?></th>
			<th class="title"><?php echo JHTML::_('grid.sort', 'FLEXI_FILENAME', 'f.filename', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width=""><?php echo JHTML::_('grid.sort', 'FLEXI_FILE_TITLE', 'f.altname', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JText::_( 'FLEXI_PUBLISHED' ); ?></th>
			<th width=""><?php echo JText::_( 'FLEXI_ACCESS' ); ?></th>
			<th width=""><?php echo JText::_( 'FLEXI_LANGUAGE' ); ?></th>
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
			<td colspan="14">
				<?php echo $this->pagination->getListFooter(); ?>
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
			<td colspan="14" align="center" style="border-top:0px solid black;">
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
			
			$checked 	= @ JHTML::_('grid.checkedout', $row, $i );
			
			$path		= $row->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;  // JPATH_ROOT . DS . <media_path | file_path>
			$file_path = $row->filename;
			
			if (substr($row->filename, 0, 7)!='http://') {
				$file_path = $path . DS . $row->filename;
			} else {
				$thumb_or_icon = 'URL';
			}
			
			$file_path    = str_replace('\\', '/', $file_path);
			if ( empty($thumb_or_icon) ) {
				$thumb_or_icon = JURI::root() . 'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' . $file_path . '&amp;w=60&amp;h=60';
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
			<td><?php echo $this->pagination->getRowOffset( $i ); ?></td>
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
			
			<td align="center"><?php echo $row->size; ?></td>
			<td align="center"><?php echo $row->hits; ?></td>
			<td align="center">
				<?php echo $row->assigned; ?>
				<?php if ($row->count_assigned) : ?>
					<br/><br/>
					<?php echo count($row->itemids); ?>
					<a href="<?php echo $items_list; ?>">
					[<?php echo JText::_( 'FLEXI_VIEW_ITEMS' );?>]
					</a>
				<?php endif; ?>
			</td>
			<td align="center">
			<?php if ($permissions->CanAuthors) :?>
				<a target="_blank" href="index.php?option=com_flexicontent&amp;<?php echo $ctrl_task_authors; ?>edit&amp;hidemainmenu=1&amp;cid[]=<?php echo $row->uploaded_by; ?>">
					<?php echo $row->uploader; ?>
				</a>
			<?php else :?>
				<?php echo $row->uploader; ?>
			<?php endif; ?>
			</td>
			<td align="center"><?php echo JHTML::Date( $row->uploaded, JText::_( 'DATE_FORMAT_LC2' ) ); ?></td>
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
<?php
use Joomla\String\StringHelper;

$tip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';

$_levels = JHtml::_('access.assetgroups');
$access_levels = array();
foreach($_levels as $_level) {
	$access_levels[$_level->value] = $_level->text;
}
?>

<div class="flexicontent" id="flexicontent">

<form action="index.php" method="post" name="adminForm" id="adminForm" class="form-validate form-horizontal" enctype="multipart/form-data" >

<?php if (!empty( $this->sidebar)) : ?>
<div class="<?php echo FLEXI_J40GE ? 'row' : 'row-fluid'; ?>">
	<div id="j-sidebar-container" class="span2 col-md-2">
		<?php echo str_replace('type="button"', '', $this->sidebar); ?>
	</div>
	<div class="span10 col-md-10">
		<div id="j-main-container">
<?php else : ?>
<div class="<?php echo FLEXI_J40GE ? 'row' : 'row-fluid'; ?>">
	<div class="span12 col-md-12">
		<div id="j-main-container">
<?php endif;?>


<div class="fc-mssg-inline fc-info fc-nobgimage" style="margin: 0 2px">
	<div class="fcimport_field_prop_list fcimport_field_prop_mainlist">
		
		<table class="fc-form-tbl">
			<tr>
				<td class="key">
					<span class="fc-prop-lbl">Content type <br/> (for new items)</span>
				</td>
				<td class="data">
					<span class="icon-lock"></span><b><?php echo $this->types[$this->conf['type_id']]->name; ?></b>
				</td>
				<td class="key">
					<span class="fc-prop-lbl">Item ID</span>
				</td>
				<td class="data">
					<?php if (!$this->conf['id_col']): ?>
						<span class="icon-lock"></span><b><?php echo JText::_("FLEXI_IMPORT_AUTO_NEW_ID");?></b>
					<?php elseif ($this->conf['id_col']==1): ?>
						<span class="icon-stack"></span><b><?php echo JText::_('FLEXI_IMPORT_USE_ID_COL') . ' <br/> ' . JText::_("FLEXI_IMPORT_CREATE_ITEMS"); ?> </b>
					<?php elseif ($this->conf['id_col']==2): ?>
						<span class="icon-stack"></span><b><?php echo JText::_('FLEXI_IMPORT_USE_ID_COL') . ' <br/> ' . JText::_("FLEXI_IMPORT_CREATE_UPDATE_ITEMS"); ?> </b>
					<?php elseif ($this->conf['id_col']==3): ?>
						<span class="icon-stack"></span><b><?php echo JText::_('FLEXI_IMPORT_USE_ID_COL') . ' <br/> ' . JText::_("FLEXI_IMPORT_UPDATE_ITEMS"); ?> </b>
					<?php else:
						$app = JFactory::getApplication();
						$app->setHeader('status', 500);
						$app->enqueueMessage('not implemented setting \'id_col\': ' . $this->conf['id_col'], 'error');
						$app->redirect('index.php?option=com_flexicontent&view=import');
					endif; ?>
				</td>
			</tr>
			
			<tr>
				<td class="key">
					<span class="fc-prop-lbl">Language</span>
				</td>
				<td class="data">
					<?php if (!$this->conf['language']): ?>
						<span class="icon-stack"><?php echo 'Using column'; ?></b>
					<?php else: ?>
						<span class="icon-lock"></span><b><?php echo $this->languages->{$this->conf['language']}->name; ?></b>
					<?php endif; ?>
				</td>
				<td class="key">
					<span class="fc-prop-lbl">Main category</span>
				</td>
				<td class="data">
					<?php if ($this->conf['maincat_col']): ?>
						<span class="icon-stack"></span><b><?php echo 'Using column'; ?></b>
					<?php else: ?>
						<span class="icon-lock"></span><b><?php echo $this->categories[$this->conf['maincat']]->title; ?></b>
					<?php endif; ?>
				</td>
			</tr>
			
			<tr>
				<td class="key">
					<span class="fc-prop-lbl">State</span>
				</td>
				<td class="data">
					<?php
						$tmpparams = new JRegistry();
						$tmpparams->set('show_icons', '0');
					?>
					<?php if (!$this->conf['state']): ?>
						<span class="icon-stack"></span><b><?php echo 'Using column'; ?></b>
					<?php else: ?>
						<span class="icon-lock"></span><b><?php echo flexicontent_html::stateicon( $this->conf['state'], $tmpparams); ?></b>
					<?php endif; ?>
				</td>
				<td class="key">
					<span class="fc-prop-lbl">Secondary categories</span>
				</td>
				<td class="data">
					<?php
						$seccats = array();
						foreach($this->conf['seccats'] as $seccatid) {
							$seccats[] = $this->categories[$seccatid]->title;
						}
					?>
					<?php if ($this->conf['seccats_col']): ?>
						<span class="icon-stack"></span><b><?php echo 'Using column'; ?></b>
					<?php else: ?>
						<span class="icon-lock"></span><b><?php echo !empty($seccats) ? implode(", ", $seccats) : '-'; ?></b>
					<?php endif; ?>
				</td>
			</tr>
			
			<tr>
				<td class="key">
					<span class="fc-prop-lbl">Access</span>
				</td>
				<td class="data">
					<?php if ($this->conf['access']===0): ?>
						<span class="icon-stack"></span><b><?php echo 'Using column'; ?></b>
					<?php else: ?>
						<span class="icon-lock"></span><b><?php echo isset($access_levels[$this->conf['access']]) ? $access_levels[$this->conf['access']] : $this->conf['access']; ?></b>
					<?php endif; ?>
				</td>
				<td class="key">
				</td>
				<td class="data">
				</td>
			</tr>
			
			<tr>
				<td colspan="4" style="height:16px;">
				</td>
			</tr>
			
			<tr>
				<td class="key">
					<span class="fc-prop-lbl">Created by (user)</span>
				</td>
				<td class="data">
					<?php if ($this->conf['created_by_col']): ?>
						<span class="icon-stack"></span><b><?php echo 'Using column'; ?></b>
					<?php else: ?>
						<span class="icon-lock"></span><b><?php echo 'Current user'; ?></b>
					<?php endif; ?>
				</td>
				<td class="key">
					<span class="fc-prop-lbl">Creation date</span>
				</td>
				<td class="data">
					<?php if ($this->conf['created_col']): ?>
						<span class="icon-stack"></span><b><?php echo 'Using column'; ?></b>
					<?php else: ?>
						<span class="icon-lock"></span><b><?php echo 'NOW'; ?></b>
					<?php endif; ?>
				</td>
			</tr>
			
			<tr>
				<td class="key">
					<span class="fc-prop-lbl">Modified by (user)</span>
				</td>
				<td class="data">
					<?php if ($this->conf['modified_by_col']): ?>
						<span class="icon-stack"></span><b><?php echo 'Using column'; ?></b>
					<?php else: ?>
						<span class="icon-lock"></span><b><?php echo 'NULL (none)'; ?></b>
					<?php endif; ?>
				</td>
				<td class="key">
					<span class="fc-prop-lbl">Modification date</span>
				</td>
				<td class="data">
					<?php if ($this->conf['modified_col']): ?>
						<span class="icon-stack"></span><b><?php echo 'Using column'; ?></b>
					<?php else: ?>
						<span class="icon-lock"></span><b><?php echo 'Never'; ?></b>
					<?php endif; ?>
				</td>
			</tr>
			
		</table>
						
	</div>
</div>

<table class="adminlist">
	<thead>
	<tr>
		<th>#</th>
		<?php foreach($this->conf['contents_parsed'][1] as $fieldname => $contents_parsed) :?>
			<?php	if ( !isset($this->conf['core_props'][$fieldname]) && !isset($this->conf['thefields'][$fieldname]) )  continue; ?>
			<th style="text-align: left;">
			<?php
			if ( isset($this->conf['thefields'][$fieldname]) ) {
				echo $this->conf['thefields'][$fieldname]->label."<br/>";
			} else if ( isset($this->conf['core_props'][$fieldname]) ) {
				echo $this->conf['core_props'][$fieldname]."<br/>";
			}
			echo '<small>-- '.$fieldname.' --</small>';
			
			$folderpath = '';
			if ( isset($this->conf['thefields'][$fieldname]) && isset($this->conf['ff_types_to_paths'][ $this->conf['thefields'][$fieldname]->field_type]) ) {
				$this->conf['thefields'][$fieldname]->folderpath = $this->conf['ff_types_to_paths'][ $this->conf['thefields'][$fieldname]->field_type];
			}
			?>
			</th>
		<?php endforeach; ?>
	</tr>
	</thead>

	<tbody>
	<?php foreach($this->conf['contents_parsed'] as $row_no => $contents_parsed) :?>
	<tr>
		<td class="center"><?php echo $row_no; ?></td>
		<?php foreach($contents_parsed as $fieldname => $field_values) :?>
			<?php
			if ( !isset($this->conf['core_props'][$fieldname]) && !isset($this->conf['thefields'][$fieldname]) )  continue;
			?>
			<td style="text-align: left;">
				<?php
				switch ($fieldname)
				{
					case 'id':
						$is_existing = isset($this->conf['existing_ids'][$field_values]);
						echo $field_values . ' &nbsp; ';
						echo ($is_existing ? ' <span class="icon-redo" style="color: darkorange;" title="Update"></span> ' : '<span class="icon-new" style="color: darkgreen;" title="Create"></span>');
						break;

					case 'access':
						echo $field_values . ' : ';
						echo isset($access_levels[$field_values]) ? $access_levels[$field_values] : 'Invalid, no Access level with ID: '.$field_values;
						break;

					case 'catid':
						echo $field_values .' : ';
						echo isset($this->categories[$field_values]) ? $this->categories[$field_values]->title : 'Invalid, no Category with ID: '.$field_values;
						break;

					case 'cid':
						$seccats = array();
						foreach($field_values as $seccatid)
						{
							$seccats[] = isset($this->categories[$seccatid]) ? $this->categories[$seccatid]->title : 'Invalid, no Category with ID: '.$seccatid;
						}
						echo !empty($seccats) ? implode(", ", $seccats) : '-';
						break;

					case 'language':
						echo $this->languages->{$field_values}->name;
						break;

					case 'state':
						echo flexicontent_html::stateicon( $field_values, $this->cparams);
						break;

					default:
						if (!is_array($field_values))
						{
							$is_missing = !empty($this->conf['filenames_missing'][$fieldname]) && is_string($field_values) && isset($this->conf['filenames_missing'][$fieldname][$field_values]);
							echo $is_missing ? '<span class="fcimport_missingfile '.$tip_class.'" title="<b>File is missing</b><br/> not found in path '.(@$this->conf['thefields'][$fieldname]->folderpath).'">' : '';
							echo StringHelper::strlen($field_values) > 40  ?  StringHelper::substr(strip_tags($field_values), 0, 40) . ' ... '  :  $field_values;
							echo $is_missing ? '</span>' : '';
						}

						else
						{
							echo '<ul class="fcimport_field_value_list">';

							foreach($field_values as $field_value)
							{
								echo '<li>';
								if (!is_array($field_value))
								{
									$is_missing = !empty($this->conf['filenames_missing'][$fieldname]) && is_string($field_value) && isset($this->conf['filenames_missing'][$fieldname][$field_value]);
									echo $is_missing ? '<span class="fcimport_missingfile '.$tip_class.'" title="<b>File is missing</b><br/> not found in path '.(@$this->conf['thefields'][$fieldname]->folderpath).'">' : '';
									echo StringHelper::strlen($field_value) > 40  ?  StringHelper::substr(strip_tags($field_value), 0, 40) . ' ... '  :  $field_value;
									echo $is_missing ? '</span>' : '';
								}
								else
								{
									echo '<dl class="fcimport_field_prop_list">';
									foreach($field_value as $prop_name => $prop_val)
									{
										echo '<dt>'.$prop_name.'</dt>';
										echo '<dd>'.print_r($prop_val,true).'</dd>';
									}
									echo "</dl>";
								}
								echo '</li>';
							}

							echo "</ul>";
						}
						break;
				}
				?>
			</td>
		<?php endforeach; ?>
	</tr>
	<?php endforeach; ?>
	</tbody>
	
</table>

	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="import" />
	<input type="hidden" name="view" value="import" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="fcform" value="1" />
	<?php echo JHtml::_( 'form.token' ); ?>

		</div>  <!-- j-main-container -->
	</div>  <!-- spanNN -->
</div>  <!-- row -->
</form>
</div><!-- #flexicontent end -->
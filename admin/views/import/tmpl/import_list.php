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

<form action="index.php" method="post" name="adminForm" id="adminForm" class="form-validate" enctype="multipart/form-data" >

<?php if (!empty( $this->sidebar)) : ?>
	<div id="j-sidebar-container" class="span2">
		<?php echo str_replace('type="button"', '', $this->sidebar); ?>
	</div>
	<div id="j-main-container" class="span10">
<?php else : ?>
	<div id="j-main-container">
<?php endif;?>


<div class="alert fc-small fc-iblock">
	<span class="fcimport_field_prop_list fcimport_field_prop_mainlist">
		
		<table>
			<tr>
				<td style="text-align:right">
					<span class="label">Content type</span>
				</td>
				<td>
					<span class="badge badge-success"><?php echo $this->types[$this->conf['type_id']]->name; ?></span>
				</td>
				<td style="text-align:right">
					<span class="label">Item ID</span>
				</td>
				<td>
					<?php if ($this->conf['id_col']): ?>
						<span class="badge badge-info"><?php echo 'Using column'; ?></span>
					<?php else: ?>
						<span class="badge badge-success"><?php echo 'AUTO (new ID)'; ?></span>
					<?php endif; ?>
				</td>
			</tr>
			
			<tr>
				<td style="text-align:right">
					<span class="label">Language</span>
				</td>
				<td>
					<span class="badge badge-success"><?php echo !$this->conf['language'] ? 'Using column' : $this->languages->{$this->conf['language']}->name; ?></span>
				</td>
				<td style="text-align:right">
					<span class="label">Main category</span>
				</td>
				<td>
					<?php if ($this->conf['maincat_col']): ?>
						<span class="badge badge-info"><?php echo 'Using column'; ?></span>
					<?php else: ?>
						<span class="badge badge-success"><?php echo $this->categories[$this->conf['maincat']]->title; ?></span>
					<?php endif; ?>
				</td>
			</tr>
			
			<tr>
				<td style="text-align:right">
					<span class="label">State</span>
				</td>
				<td>
					<?php
						$tmpparams = new JRegistry();
						$tmpparams->set('show_icons', '0');
					?>
					<?php if (!$this->conf['state']): ?>
						<span class="badge badge-info"><?php echo 'Using column'; ?></span>
					<?php else: ?>
						<span class="badge badge-success"><?php echo flexicontent_html::stateicon( $this->conf['state'], $tmpparams); ?></span>
					<?php endif; ?>
				</td>
				<td style="text-align:right">
					<span class="label">Secondary categories</span>
				</td>
				<td>
					<?php
						$seccats = array();
						foreach($this->conf['seccats'] as $seccatid) {
							$seccats[] = $this->categories[$seccatid]->title;
						}
					?>
					<?php if ($this->conf['seccats_col']): ?>
						<span class="badge badge-info"><?php echo 'Using column'; ?></span>
					<?php else: ?>
						<span class="badge badge-success"><?php echo !empty($seccats) ? implode(", ", $seccats) : '-'; ?></span>
					<?php endif; ?>
				</td>
			</tr>
			
			<tr>
				<td style="text-align:right">
					<span class="label">Access</span>
				</td>
				<td>
					<?php if ($this->conf['access']===0): ?>
						<span class="badge badge-info"><?php echo 'Using column'; ?></span>
					<?php else: ?>
						<span class="badge badge-success"><?php echo isset($access_levels[$this->conf['access']]) ? $access_levels[$this->conf['access']] : $this->conf['access']; ?></span>
					<?php endif; ?>
				</td>
				<td style="text-align:right">
				</td>
				<td>
				</td>
			</tr>
			
			<tr>
				<td colspan="4" style="height:16px;">
				</td>
			</tr>
			
			<tr>
				<td style="text-align:right">
					<span class="label">Created by (user)</span>
				</td>
				<td>
					<?php if ($this->conf['created_by_col']): ?>
						<span class="badge badge-info"><?php echo 'Using column'; ?></span>
					<?php else: ?>
						<span class="badge badge-success"><?php echo 'Current user'; ?></span>
					<?php endif; ?>
				</td>
				<td style="text-align:right">
					<span class="label">Creation date</span>
				</td>
				<td>
					<?php if ($this->conf['created_col']): ?>
						<span class="badge badge-info"><?php echo 'Using column'; ?></span>
					<?php else: ?>
						<span class="badge badge-success"><?php echo 'NOW'; ?></span>
					<?php endif; ?>
				</td>
			</tr>
			
			<tr>
				<td style="text-align:right">
					<span class="label">Modified by (user)</span>
				</td>
				<td>
					<?php if ($this->conf['modified_by_col']): ?>
						<span class="badge badge-info"><?php echo 'Using column'; ?></span>
					<?php else: ?>
						<span class="badge badge-success"><?php echo 'NULL (none)'; ?></span>
					<?php endif; ?>
				</td>
				<td style="text-align:right">
					<span class="label">Modification date</span>
				</td>
				<td>
					<?php if ($this->conf['modified_col']): ?>
						<span class="badge badge-info"><?php echo 'Using column'; ?></span>
					<?php else: ?>
						<span class="badge badge-success"><?php echo 'Never'; ?></span>
					<?php endif; ?>
				</td>
			</tr>
			
		</table>
						
	</span>
</div>

<table class="adminlist">
	<thead>
	<tr>
		<th>#</th>
		<?php foreach($this->conf['contents_parsed'][1] as $fieldname => $contents_parsed) :?>
			<?php	if ( !isset($this->conf['core_props'][$fieldname]) && !isset($this->conf['thefields'][$fieldname]) )  continue; ?>
			<th style="text-align:left;">
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
			<td>
				<?php
					if ($fieldname=='access') {
						echo $field_values .' : ';
						echo isset($access_levels[$field_values]) ? $access_levels[$field_values] : 'Invalid, no Access level with ID: '.$field_values;
					} else if ($fieldname=='catid') {
						echo $field_values .' : ';
						echo isset($this->categories[$field_values]) ? $this->categories[$field_values]->title : 'Invalid, no Category with ID: '.$field_values;
					} else if ($fieldname=='cid') {
						$seccats = array();
						foreach($field_values as $seccatid) {
							$seccats[] = isset($this->categories[$seccatid]) ? $this->categories[$seccatid]->title : 'Invalid, no Category with ID: '.$seccatid;
						}
						echo !empty($seccats) ? implode(", ", $seccats) : '-';
					} else if ($fieldname=='language') {
						echo $this->languages->{$field_values}->name;
					} else if ($fieldname=='state')
						echo flexicontent_html::stateicon( $field_values, $this->cparams);
					else if (!is_array($field_values)) {
						$is_missing = !empty($this->conf['filenames_missing'][$fieldname]) && is_string($field_values) && isset($this->conf['filenames_missing'][$fieldname][$field_values]);
						echo $is_missing ? '<span class="fcimport_missingfile '.$tip_class.'" title="<b>File is missing</b><br/> not found in path '.(@$this->conf['thefields'][$fieldname]->folderpath).'">' : '';
						echo StringHelper::strlen($field_values) > 40  ?  StringHelper::substr(strip_tags($field_values), 0, 40) . ' ... '  :  $field_values;
						echo $is_missing ? '</span>' : '';
					} else {
						echo '<ul class="fcimport_field_value_list">';
						foreach($field_values as $field_value) {
							echo '<li>';
							if (!is_array($field_value)) {
								$is_missing = !empty($this->conf['filenames_missing'][$fieldname]) && is_string($field_value) && isset($this->conf['filenames_missing'][$fieldname][$field_value]);
								echo $is_missing ? '<span class="fcimport_missingfile '.$tip_class.'" title="<b>File is missing</b><br/> not found in path '.(@$this->conf['thefields'][$fieldname]->folderpath).'">' : '';
								echo StringHelper::strlen($field_value) > 40  ?  StringHelper::substr(strip_tags($field_value), 0, 40) . ' ... '  :  $field_value;
								echo $is_missing ? '</span>' : '';
							} else {
								echo '<dl class="fcimport_field_prop_list">';
								foreach($field_value as $prop_name => $prop_val) {
									echo '<dt>'.$prop_name.'</dt>';
									echo '<dd>'.print_r($prop_val,true).'</dd>';
								}
								echo "</dl>";
							}
							echo '</li>';
						}
						echo "</ul>";
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
	<?php echo JHTML::_( 'form.token' ); ?>
	
</form>
</div>
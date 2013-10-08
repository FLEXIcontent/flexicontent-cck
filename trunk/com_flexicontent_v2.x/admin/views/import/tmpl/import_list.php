<form action="index.php" method="post" enctype="multipart/form-data" name="adminForm" id="adminForm">
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="import" />
	<input type="hidden" name="view" value="import" />
	<input type="hidden" name="task" value="" />
	<?php echo JHTML::_( 'form.token' ); ?>
</form>

<div class="fc_nice_box" style="padding:4px;">
	<dl class="fcimport_field_prop_list fcimport_field_prop_mainlist">
		
		<dt>Content type</dt>
		<dd><?php echo $this->types[$this->conf['type_id']]->name; ?></dd>
		
		<dt>Language</dt>
		<dd><?php echo !$this->conf['language'] ? 'Using column' : $this->languages->{$this->conf['language']}->name; ?></dd>
		
		<dt>Main category</dt>
		<dd><?php echo $this->conf['maincat_col'] ? 'Using column' : $this->categories[$this->conf['maincat']]->title; ?></dd>
		
		<?php
			$seccats = array();
			foreach($this->conf['seccats'] as $seccatid) {
				$seccats[] = $this->categories[$seccatid]->title;
			}
		?>
		<dt >Secondary categories</dt>
		<dd><?php echo !empty($seccats) ? implode(", ", $seccats) : '-'; ?></dd>
		
		<dt>Creation date</dt>
		<dd><?php echo $this->conf['created_col'] ? 'Using column' : 'NOW'; ?></dd>
		
		<dt>Created by (user)</dt>
		<dd><?php echo $this->conf['created_by_col'] ? 'Using column' : 'Current'; ?></dd>
		
		<dt>Item ID</dt>
		<dd><?php echo $this->conf['id_col'] ? 'Using column' : 'AUTO (new ID)'; ?></dd>
		
		<dt>State</dt>
		<dd><?php echo !$this->conf['state'] ? 'Using column' : flexicontent_html::stateicon( $this->conf['state'], $this->cparams); ?></dd>
		
	</dl>
</div>

<table class="adminlist">
	<thead>
	<tr>
		<th>#</th>
		<?php foreach($this->conf['contents_parsed'][1] as $fieldname => $contents_parsed) :?>
			<?php	if ( !isset($this->conf['core_props'][$fieldname]) && !isset($this->conf['thefields'][$fieldname]) )  continue; ?>
			<th>
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
					if ($fieldname=='catid') {
						echo $this->categories[$field_values]->title;
					} else if ($fieldname=='cid') {
						$seccats = array();
						foreach($field_values as $seccatid) {
							$seccats[] = $this->categories[$seccatid]->title;
						}
						echo !empty($seccats) ? implode(", ", $seccats) : '-';
					} else if ($fieldname=='language') {
						echo $this->languages->{$field_values}->name;
					} else if ($fieldname=='state')
						echo flexicontent_html::stateicon( $field_values, $this->cparams);
					else if (!is_array($field_values)) {
						$is_missing = !empty($this->conf['filenames_missing'][$fieldname]) && is_string($field_values) && isset($this->conf['filenames_missing'][$fieldname][$field_values]);
						echo $is_missing ? '<span class="fcimport_missingfile hasTip" title="File is missing::not found in path '.(@$this->conf['thefields'][$fieldname]->folderpath).'">' : '';
						echo mb_strlen($field_values, 'UTF-8') > 40  ?  mb_substr(strip_tags($field_values), 0, 40, 'UTF-8') . ' ... '  :  $field_values;
						echo $is_missing ? '</span>' : '';
					} else {
						echo '<ul class="fcimport_field_value_list">';
						foreach($field_values as $field_value) {
							echo '<li>';
							if (!is_array($field_value)) {
								$is_missing = !empty($this->conf['filenames_missing'][$fieldname]) && is_string($field_value) && isset($this->conf['filenames_missing'][$fieldname][$field_value]);
								echo $is_missing ? '<span class="fcimport_missingfile hasTip" title="File is missing::not found in path '.(@$this->conf['thefields'][$fieldname]->folderpath).'">' : '';
								echo mb_strlen($field_value, 'UTF-8') > 40  ?  mb_substr(strip_tags($field_value), 0, 40, 'UTF-8') . ' ... '  :  $field_value;
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
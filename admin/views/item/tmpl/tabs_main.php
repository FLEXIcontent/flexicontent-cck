<?php

// ************
// ABOVE TABSET
// ************
if ( count($tab_fields['above']) ) : ?>
<div class="fc_edit_container_full">

	<?php foreach($tab_fields['above'] as $fn => $i) : ?>
		<div class="fcclear"></div>

			<?php if (!is_array($captured[$fn])) :
				echo $captured[$fn]; unset($captured[$fn]);
			else:
				foreach($captured[$fn] as $n => $html) : ?>
					<div class="fcclear"></div>
					<?php echo $html;
				endforeach;
				unset($captured[$fn]);
			endif;
		endforeach;
		?>

</div>
<?php endif;



// ***
// *** MAIN TABSET START
// ***
array_push($tabSetStack, $tabSetCnt);
$tabSetCnt = ++$tabSetMax;
$tabCnt[$tabSetCnt] = 0;
?>

<!-- tabber start -->
<div class="fctabber fields_tabset" id="fcform_tabset_<?php echo $tabSetCnt; ?>">


<?php
// ***
// *** DESCRIPTION TAB
// ***
if ( count($tab_fields['tab01']) ) :
	$tab_lbl = isset($tab_titles['tab01']) ? $tab_titles['tab01'] : JText::_( 'FLEXI_DESCRIPTION' );
	$tab_ico = isset($tab_icocss['tab01']) ? $tab_icocss['tab01'] : 'icon-file-2';
	?>
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="<?php echo $tab_ico; ?>">
		<h3 class="tabberheading"> <?php echo $tab_lbl; ?> </h3>

		<?php foreach($tab_fields['tab01'] as $fn => $i) : ?>
			<div class="fcclear"></div>

			<?php if (!is_array($captured[$fn])) :
				echo $captured[$fn]; unset($captured[$fn]);
			else:
				foreach($captured[$fn] as $n => $html) : ?>
					<div class="fcclear"></div>
					<?php echo $html;
				endforeach;
				unset($captured[$fn]);
			endif;
		endforeach;
		?>

	</div>
<?php endif;



// ***
// *** CUSTOM FIELDS TAB (via TYPE)
// ***
if ( count($tab_fields['tab02']) ) :
	$tab_lbl = isset($tab_titles['tab02']) ? $tab_titles['tab02'] : $type_lbl; // __TYPE_NAME__
	$tab_ico = isset($tab_icocss['tab02']) ? $tab_icocss['tab02'] : 'icon-tree-2';
	?>
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="<?php echo $tab_ico; ?>">
		<h3 class="tabberheading"> <?php echo $tab_lbl; ?> </h3>

		<?php foreach($tab_fields['tab02'] as $fn => $i) : ?>
			<div class="fcclear"></div>

			<?php if (!is_array($captured[$fn])) :
				echo $captured[$fn]; unset($captured[$fn]);
			else:
				foreach($captured[$fn] as $n => $html) : ?>
					<div class="fcclear"></div>
					<?php echo $html;
				endforeach;
				unset($captured[$fn]);
			endif;
		endforeach;
		?>

	</div>
<?php endif;





// ***
// *** Joomla custom fields (1 TAB: 'fields-0') and custom field groups (1 TAB per group: fields-n)
// ***

$fieldSets = $this->form->getFieldsets();
foreach ($fieldSets as $name => $fieldSet) :
	if (substr($name, 0, 7) !== 'fields-') continue;

	$label = !empty($fieldSet->label) ? $fieldSet->label : 'COM_FLEXICONTENT_'.$name.'_FIELDSET_LABEL';
	if ( JText::_($label)=='COM_FLEXICONTENT_'.$name.'_FIELDSET_LABEL' ) $label = 'COM_CONTENT_'.$name.'_FIELDSET_LABEL';

	$icon_class = 'icon-pencil-2';
	//echo JHtml::_('sliders.panel', JText::_($label), $name.'-options');
	//echo "<h2>".$label. "</h2> " . "<h3>".$name. "</h3> ";
?>
<!-- CUSTOM parameters TABs -->
<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="<?php echo $icon_class; ?>">
	<h3 class="tabberheading"> <?php echo JText::_($label); ?> </h3>

	<div class="fc_tabset_inner">
		<?php foreach ($this->form->getFieldset($name) as $field) : ?>

			<?php if ($field->hidden): ?>
				<span style="display:none !important;">
					<?php echo $field->input; ?>
				</span>
			<?php else :
				echo ($field->getAttribute('type') === 'separator' || $field->hidden || !$field->label) ? $field->input : '
				<div class="control-group">
					<div class="control-label" id="jform_attribs_'.$field->fieldname.'-lbl-outer">
						' . str_replace('class="', 'class="' . $lbl_class . ' label-fcinner ', str_replace(' for="', ' data-for="', $field->label)) . '
					</div>
					<div class="controls container_fcfield">
						' . $field->input . '
					</div>
				</div>
				';
			endif; ?>

		<?php endforeach; ?>
	</div>

</div> <!-- end tab -->

<?php endforeach;




// ***
// *** ASSIGNMENTS TAB (Multi-category assignments  -- and --  Item language associations)
// ***
if ( count($tab_fields['tab03']) ) :
	$tab_lbl = isset($tab_titles['tab03']) ? $tab_titles['tab03'] : JText::_( 'FLEXI_ASSIGNMENTS' );
	$tab_ico = isset($tab_icocss['tab03']) ? $tab_icocss['tab03'] : 'icon-signup';
	?>
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="<?php echo $tab_ico; ?>">
		<h3 class="tabberheading"> <?php echo $tab_lbl; ?> </h3>

		<?php foreach($tab_fields['tab03'] as $fn => $i) : ?>
			<div class="fcclear"></div>

			<?php if (!is_array($captured[$fn])) :
				echo $captured[$fn]; unset($captured[$fn]);
			else:
				foreach($captured[$fn] as $n => $html) : ?>
					<div class="fcclear"></div>
					<?php echo $html;
				endforeach;
				unset($captured[$fn]);
			endif;
		endforeach;
		?>

	</div>
<?php endif;


//echo "<pre>"; print_r(array_keys($this->form->getFieldsets('attribs'))); echo "</pre>";
//echo "<pre>"; print_r(array_keys($this->form->getFieldsets())); echo "</pre>";

$fieldSets = $this->form->getFieldsets();
foreach ($fieldSets as $name => $fieldSet) :
	if (substr($name, 0, 7) == 'params-' || substr($name, 0, 7) == 'fields-' || $name=='themes' || $name=='item_associations') continue;

	$label = !empty($fieldSet->label) ? $fieldSet->label : 'COM_FLEXICONTENT_'.$name.'_FIELDSET_LABEL';
	if ( JText::_($label)=='COM_FLEXICONTENT_'.$name.'_FIELDSET_LABEL' ) $label = 'COM_CONTENT_'.$name.'_FIELDSET_LABEL';

	if ($name == 'metafb')
		$icon_class = 'icon-users';
	else
		$icon_class = '';
?>
<!-- CUSTOM parameters TABs -->
<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="<?php echo $icon_class; ?>">
	<h3 class="tabberheading"> <?php echo JText::_($label); ?> </h3>

	<div class="fc_tabset_inner">
		<?php foreach ($this->form->getFieldset($name) as $field) : ?>

			<?php if ($field->hidden): ?>
				<span style="display:none !important;">
					<?php echo $field->input; ?>
				</span>
			<?php else :
				echo ($field->getAttribute('type')=='separator' || $field->hidden || !$field->label) ? $field->input : '
				<div class="control-group">
					<div class="control-label" id="jform_attribs_'.$field->fieldname.'-lbl-outer">
						' . str_replace('class="', 'class="' . $lbl_class . ' label-fcinner ', str_replace(' for="', ' data-for="', $field->label)) . '
					</div>
					<div class="controls container_fcfield">
						' . $this->getFieldInheritedDisplay($field, $this->iparams) . '
					</div>
				</div>
				';
			endif; ?>

		<?php endforeach; ?>
	</div>

</div> <!-- end tab -->

<?php endforeach; ?>



<?php

// ***
// *** PUBLISHING TAB
// ***
// J2.5 requires Edit State privilege while J1.5 requires Edit privilege
if ( count($tab_fields['tab04']) ) : ?>
	<?php
	$tab_lbl = isset($tab_titles['tab04']) ? $tab_titles['tab04'] : JText::_( 'FLEXI_PUBLISHING' );
	$tab_ico = isset($tab_icocss['tab04']) ? $tab_icocss['tab04'] : 'icon-calendar';
	?>
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="<?php echo $tab_ico; ?>">
		<h3 class="tabberheading"> <?php echo $tab_lbl; ?> </h3>

		<?php foreach($tab_fields['tab04'] as $fn => $i) : ?>
			<div class="fcclear"></div>

			<?php if (!is_array($captured[$fn])) :
				echo $captured[$fn]; unset($captured[$fn]);
			else:
				foreach($captured[$fn] as $n => $html) : ?>
					<div class="fcclear"></div>
					<?php echo $html;
				endforeach;
				unset($captured[$fn]);
			endif;
		endforeach;
		?>

	</div> <!-- end tab -->

<?php endif;



// ***
// *** META / SEO TAB
// ***
if ( count($tab_fields['tab05']) ) : ?>
	<?php
	$tab_lbl = isset($tab_titles['tab05']) ? $tab_titles['tab05'] : JText::_( 'FLEXI_META_SEO' );
	$tab_ico = isset($tab_icocss['tab05']) ? $tab_icocss['tab05'] : 'icon-bookmark';
	?>
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="<?php echo $tab_ico; ?>" >
		<h3 class="tabberheading"> <?php echo $tab_lbl; ?> </h3>

		<?php foreach($tab_fields['tab05'] as $fn => $i) : ?>
			<div class="fcclear"></div>

			<?php if (!is_array($captured[$fn])) :
				echo $captured[$fn]; unset($captured[$fn]);
			else:
				foreach($captured[$fn] as $n => $html) : ?>
					<div class="fcclear"></div>
					<?php echo $html;
				endforeach;
				unset($captured[$fn]);
			endif;
		endforeach;
		?>

	</div> <!-- end tab -->
<?php endif;




// ***
// *** DISPLAYING PARAMETERS TAB
// ***
if ( count($tab_fields['tab06']) ) : ?>
	<?php
	$tab_lbl = isset($tab_titles['tab06']) ? $tab_titles['tab06'] : JText::_( 'FLEXI_DISPLAYING' );
	$tab_ico = isset($tab_icocss['tab06']) ? $tab_icocss['tab06'] : 'icon-eye-open';
	?>
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="<?php echo $tab_ico; ?>">
		<h3 class="tabberheading"> <?php echo $tab_lbl; ?> </h3>

		<?php foreach($tab_fields['tab06'] as $fn => $i) : ?>
			<div class="fcclear"></div>

			<?php if (!is_array($captured[$fn])) :
				echo $captured[$fn]; unset($captured[$fn]);
			else:
				foreach($captured[$fn] as $n => $html) : ?>
					<div class="fcclear"></div>
					<?php echo $html;
				endforeach;
				unset($captured[$fn]);
			endif;
		endforeach;
		?>

	</div>
<?php endif;



// ***
// *** JOOMLA IMAGE/URLS TAB
// ***
if ( count($FC_jfields_html) ) : ?>
	<?php
		if (isset($FC_jfields_html['images']) && isset($FC_jfields_html['urls'])) {
			$fsetname = 'FLEXI_COM_CONTENT_IMAGES_AND_URLS';
			$fseticon = 'icon-pencil-2';
		} else if (isset($FC_jfields_html['images'])) {
			$fsetname = 'FLEXI_IMAGES';
			$fseticon = 'icon-images';
		} else if (isset($FC_jfields_html['urls'])) {
			$fsetname = 'FLEXI_LINKS';
			$fseticon = 'icon-link';
		} else {
			$fsetname = 'FLEXI_COMPATIBILITY';
			$fseticon = 'icon-pencil-2';
		}
	?>
	<!-- Joomla images/urls tab -->
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="<?php echo $fseticon; ?>">
		<h3 class="tabberheading"> <?php echo JText::_($fsetname); ?> </h3>

		<?php foreach ($FC_jfields_html as $fields_grp_name => $_html) : ?>
		<fieldset class="flexi_params fc_tabset_inner">
			<div class="alert alert-info" style="width: 50%;"><?php echo JText::_('FLEXI_'.strtoupper($fields_grp_name).'_COMP'); ?></div>
			<?php echo $_html; ?>
		</fieldset>
		<?php endforeach; ?>

	</div>
<?php endif;



// ***
// *** TEMPLATE TAB
// ***
if ( count($tab_fields['tab07']) ) : ?>
	<?php
	$tab_lbl = isset($tab_titles['tab07']) ? $tab_titles['tab07'] : JText::_( 'FLEXI_TEMPLATE' );
	$tab_ico = isset($tab_icocss['tab07']) ? $tab_icocss['tab07'] : 'icon-palette';
	?>
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="<?php echo $tab_ico; ?>">
		<h3 class="tabberheading"> <?php echo $tab_lbl; ?> </h3>

		<fieldset class="flexi_params fc_edit_container_full">

		<?php foreach($tab_fields['tab07'] as $fn => $i) : ?>
			<div class="fcclear"></div>

			<?php if (!is_array($captured[$fn])) :
				echo $captured[$fn]; unset($captured[$fn]);
			else:
				foreach($captured[$fn] as $n => $html) : ?>
					<div class="fcclear"></div>
					<?php echo $html;
				endforeach;
				unset($captured[$fn]);
			endif;
		endforeach;
		?>

		</fieldset>

	</div> <!-- end tab -->

<?php endif;



// ***
// *** Versions TAB
// ***
if ( count($tab_fields['tab08']) ) : ?>
	<?php
	$tab_lbl = isset($tab_titles['tab08']) ? $tab_titles['tab08'] : JText::_( 'FLEXI_VERSIONS' );
	$tab_ico = isset($tab_icocss['tab08']) ? $tab_icocss['tab08'] : 'icon-stack';
	?>
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="<?php echo $tab_ico; ?>">
		<h3 class="tabberheading"> <?php echo $tab_lbl; ?> </h3>

		<fieldset class="flexi_params fc_edit_container_full">

		<?php foreach($tab_fields['tab08'] as $fn => $i) : ?>
			<div class="fcclear"></div>

			<?php if (!is_array($captured[$fn])) :
				echo $captured[$fn]; unset($captured[$fn]);
			else:
				foreach($captured[$fn] as $n => $html) : ?>
					<div class="fcclear"></div>
					<?php echo $html;
				endforeach;
				unset($captured[$fn]);
			endif;
		endforeach;
		?>

		</fieldset>

	</div> <!-- end tab -->

<?php endif; ?>





<?php
// ***
// *** MAIN TABSET END
// ***
?>
</div> <!-- end of tab set -->

<?php $tabSetCnt = array_pop($tabSetStack); ?>


<?php
// ************
// BELOW TABSET
// ************
if ( count($tab_fields['below']) || count($captured) ) : ?>
<div class="fc_edit_container_full">

	<?php foreach($tab_fields['below'] as $fn => $i) : ?>
		<div class="fcclear"></div>

		<?php if (!is_array($captured[$fn])) :
			echo $captured[$fn]; unset($captured[$fn]);
		else:
			foreach($captured[$fn] as $n => $html) : ?>
				<div class="fcclear"></div>
				<?php echo $html;
			endforeach;
			unset($captured[$fn]);
		endif;
	endforeach;
	?>

	<?php /* ALSO print any fields that were not placed above, this list may contain fields zero-length HTML which is OK */ ?>
	<?php foreach($captured as $fn => $i) : ?>
		<div class="fcclear"></div>

		<?php if (!is_array($captured[$fn])) :
			echo $captured[$fn]; unset($captured[$fn]);
		else:
			foreach($captured[$fn] as $n => $html) : ?>
				<div class="fcclear"></div>
				<?php echo $html;
			endforeach;
			unset($captured[$fn]);
		endif;
	endforeach;
	?>

</div>
<?php endif;

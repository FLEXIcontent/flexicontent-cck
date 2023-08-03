<?php
defined('_JEXEC') or die('Restricted access');
use Joomla\String\StringHelper;

/**
 * Arrays containing the captured form fields and other displayed content
 */
$captured = array();  // Support legacy forms, contains a single HTML string
$rendered = array();  // An object with: field (object), label_html, input_html, html (container with label_html + input_html)


if ($isSite): ob_start();  // captcha ?>

	<?php if ( $this->captcha_errmsg ) : ?>

		<?php echo sprintf( $alert_box, '', 'error', '', $this->captcha_errmsg );?>

	<?php elseif ( $this->captcha_field ) : ?>

			<fieldset class="flexi_params fc_edit_container_full">
			<?php echo $this->captcha_field; ?>
			</fieldset>

	<?php endif; ?>
<?php
$fn = 'captcha';
$captured[$fn] = ob_get_clean();
$rendered[$fn] = (object) array('label_html' => '', 'input_html' => $captured[$fn], 'html' => $captured[$fn], 'field' => false);
endif;




/**
 * Capture JOOMLA INTRO/FULL IMAGES and URLS
 */
$show_jui = JComponentHelper::getParams('com_content')->get('show_urls_images' . ($isSite ? '_frontend' : '_backend'), 0);
if ( $this->params->get('use_jimages' . $CFGsfx, $show_jui) || $this->params->get('use_jurls' . $CFGsfx, $show_jui) ) :

	// Do not change these are the 'images' and 'urls' names these are the names in the XML file
	$fields_grps_compatibility = array();
	if ( $this->params->get('use_jimages' . $CFGsfx, $show_jui) )  $fields_grps_compatibility[] = 'images';
	if ( $this->params->get('use_jurls' . $CFGsfx, $show_jui) )    $fields_grps_compatibility[] = 'urls';

	foreach ($fields_grps_compatibility as $fields_grp_name) :

		ob_start(); ?>
		<?php foreach ($this->form->getGroup($fields_grp_name) as $field) : ?>

			<?php if ($field->hidden): ?>
				<div style="display: none;"><?php echo $field->input; ?></div>

			<?php elseif (!$field->label): ?>
				<?php echo $field->input;?>

			<?php else: ?>
			<div class="control-group">
				<div class="control-label" id="jform_<?php echo $field->name; ?>-lbl-outer">
					<?php echo $field->label; ?>
				</div>
				<div class="controls">
					<?php echo $field->input;?>
				</div>
			</div>

			<?php endif;
		endforeach; ?>
		<?php
		$fn = 'j' . $fields_grp_name;
		$captured[$fn] = ob_get_clean();
		$rendered[$fn] = (object) array('label_html' => '', 'input_html' => $captured[$fn], 'html' => $captured[$fn], 'field' => false);

	endforeach;
endif;




if ( !$this->params->get('auto_title', 0) || $usetitle ) :  ob_start();  // title ?>
	<div class="control-group">
		<?php
		$field = isset($this->fields['title']) ? $this->fields['title'] : false;
		$field_description = $field && $field->description ? $field->description : JText::_($this->form->getField('title')->description);
		$label_attrs = 'class="' . $tip_class . $lbl_class . $lbl_extra_class . '" title="'.flexicontent_html::getToolTip(null, $field_description, 0, 1).'"';
		ob_start();
		?>
		<div class="control-label" id="jform_title-lbl-outer">
			<label id="jform_title-lbl" for="jform_title" data-for="jform_title" <?php echo $label_attrs; ?> >
				<?php echo $field ? $field->label : JText::_($this->form->getField('title')->getAttribute('label'));
				/* $field->label is set per type */ ?>
			</label>
		</div>
		<?php $label_html = ob_get_clean(); echo $label_html; ob_start(); ?>

		<div class="controls container_fcfield container_fcfield_id_6 container_fcfield_name_title input-fcmax" id="container_fcfield_6">

		<?php if ( $this->params->get('auto_title', 0) ): ?>
			<?php echo $this->row->title . ' <div class="fc-nobgimage fc-info fc-mssg-inline hasTooltip" title="' . JText::_('FLEXI_SET_TO_AUTOMATIC_VALUE_ON_SAVE', true) . '"><span class="icon-info"></span> ' . JText::_('FLEXI_AUTO', true) . '</div>' ; ?>
		<?php elseif ( isset($this->row->item_translations) ) : ?>

			<?php
			array_push($tabSetStack, $tabSetCnt);
			$tabSetCnt = ++$tabSetMax;
			$tabCnt[$tabSetCnt] = 0;
			?>
			<!-- tabber start -->
			<div class="fctabber tabber-inline s-gray tabber-lang" id="fcform_tabset_<?php echo $tabSetCnt; ?>">
				<div class="tabbertab fc-tabbed-field-box" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" >
					<h3 class="tabberheading"> <?php echo '-'.$this->itemlang->name.'-'; ?> </h3>
					<?php echo $this->form->getInput('title');?>
				</div>
				<?php foreach ($this->row->item_translations as $t): ?>
					<?php if ($this->itemlang->shortcode!=$t->shortcode && $t->shortcode!='*' && !in_array($t->code, $disable_langs)) : ?>
						<div class="tabbertab fc-tabbed-field-box" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" >
							<h3 class="tabberheading"> <?php echo $t->name; // $t->shortcode; ?> </h3>
							<?php
							$ff_id = 'jfdata_'.$t->shortcode.'_title';
							$ff_name = 'jfdata['.$t->shortcode.'][title]';
							?>
							<input class="fc_form_title fcfield_textareaval" type="text" id="<?php echo $ff_id; ?>" name="<?php echo $ff_name; ?>" value="<?php echo @$t->fields->title->value; ?>" size="36" maxlength="254" />
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
			<!-- tabber end -->
			<?php $tabSetCnt = array_pop($tabSetStack); ?>

		<?php else : ?>
			<?php echo $this->form->getInput('title');?>
		<?php endif; ?>

		</div>

		<?php $input_html = ob_get_clean(); echo $input_html; ?>
	</div>
<?php
$fn = 'title';
$captured[$fn] = ob_get_clean();
$rendered[$fn] = (object) array('label_html' => $label_html, 'input_html' => $input_html, 'html' => $captured[$fn], 'field' => $field);
unset($label_html); unset($input_html);
endif;




if ($usealias) : ob_start();  // alias ?>
	<div class="control-group">
		<?php
		$field = isset($this->fields['alias']) ? $this->fields['alias'] : false;
		$field_description = $field && $field->description ? $field->description : JText::_($this->form->getField('alias')->description);
		$label_attrs = 'class="' . $tip_class . $lbl_class . $lbl_extra_class . '" title="'.flexicontent_html::getToolTip(null, $field_description, 0, 1).'"';
		ob_start();
		?>
		<div class="control-label" id="jform_alias-lbl-outer">
			<label id="jform_alias-lbl" for="jform_alias" data-for="jform_alias" <?php echo $label_attrs; ?> >
				<?php echo $field ? $field->label : JText::_($this->form->getField('alias')->getAttribute('label'));
				/* $field->label is set per type */ ?>
			</label>
		</div>
		<?php $label_html = ob_get_clean(); echo $label_html; ob_start(); ?>

		<div class="controls container_fcfield container_fcfield_name_alias input-fcmax">
		<?php if ( isset($this->row->item_translations) ) :?>

			<?php
			array_push($tabSetStack, $tabSetCnt);
			$tabSetCnt = ++$tabSetMax;
			$tabCnt[$tabSetCnt] = 0;
			?>
			<!-- tabber start -->
			<div class="fctabber tabber-inline s-gray tabber-lang" id="fcform_tabset_<?php echo $tabSetCnt; ?>">
				<div class="tabbertab fc-tabbed-field-box" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" >
					<h3 class="tabberheading"> <?php echo '-'.$this->itemlang->name.'-'; ?> </h3>
					<?php echo $this->form->getInput('alias');?>
				</div>
				<?php foreach ($this->row->item_translations as $t): ?>
					<?php if ($this->itemlang->shortcode!=$t->shortcode && $t->shortcode!='*' && !in_array($t->code, $disable_langs)) : ?>
						<div class="tabbertab fc-tabbed-field-box" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" >
							<h3 class="tabberheading"> <?php echo $t->name; // $t->shortcode; ?> </h3>
							<?php
							$ff_id = 'jfdata_'.$t->shortcode.'_alias';
							$ff_name = 'jfdata['.$t->shortcode.'][alias]';
							?>
							<input class="fc_form_alias fcfield_textareaval" type="text" id="<?php echo $ff_id; ?>" name="<?php echo $ff_name; ?>" value="<?php echo @$t->fields->alias->value; ?>" size="36" maxlength="254" />
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
			<!-- tabber end -->
			<?php $tabSetCnt = array_pop($tabSetStack); ?>

		<?php else : ?>
			<?php echo $this->form->getInput('alias');?>
		<?php endif; ?>

		</div>

		<?php $input_html = ob_get_clean(); echo $input_html; ?>
	</div>
<?php
$fn = 'alias';
$captured[$fn] = ob_get_clean();
$rendered[$fn] = (object) array('label_html' => $label_html, 'input_html' => $input_html, 'html' => $captured[$fn], 'field' => $field);
unset($label_html); unset($input_html);
endif;




if ((!$this->menuCats || $this->menuCats->cancatid) && $usemaincat) : ob_start();  // category ?>
	<div class="control-group">
		<?php
		// Field via coreprops field type
		$field = isset($this->fields['core_category']) ? $this->fields['core_category'] : false;
		$field = isset($this->fields['core_category_' . $typeid]) ? $this->fields['core_category_' . $typeid] : $field;
		$field_description = $field && $field->description ? $field->description : JText::_($this->form->getField('catid')->description);
		$label_attrs = 'class="' . $tip_class . $lbl_class . $lbl_extra_class . '" title="'.flexicontent_html::getToolTip(null, $field_description, 0, 1).'"';
		ob_start();
		?>
		<div class="control-label" id="jform_catid-lbl-outer">
			<label id="jform_catid-lbl" for="jform_catid" data-for="jform_catid" <?php echo $label_attrs; ?> >
				<?php
				$label_maincat = JText::_(!$secondary_displayed ? $this->form->getLabel('catid') : 'FLEXI_MAIN_CATEGORY');
				echo $field ? $field->label : $label_maincat;
				/* $field->label is set per type */ ?>
				<i class="icon-tree-2"></i>
			</label>
		</div>
		<?php $label_html = ob_get_clean(); echo $label_html; ob_start(); ?>

		<div class="controls container_fcfield container_fcfield_name_catid">
			<?php /* MENU SPECIFIED main category (new item) or main category according to perms */ ?>
			<?php echo $this->menuCats ? $this->menuCats->catid : $this->lists['catid']; ?>

			<?php /* Display secondary categories if permitted, show advanced info in backend */ ?>
			<?php if ($cats_canselect_sec && !$isSite): ?>
			<span class="inlineFormTip <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip( 'FLEXI_NOTES', 'FLEXI_SEC_FEAT_CATEGORIES_NOTES', 1, 1); ?>" >
				<?php echo $info_image; ?>
			</span>
			<?php endif; ?>
		</div>

		<?php $input_html = ob_get_clean(); echo $input_html; ?>
	</div>
<?php
$fn = 'category';
$captured[$fn] = ob_get_clean();
$rendered[$fn] = (object) array('label_html' => $label_html, 'input_html' => $input_html, 'html' => $captured[$fn], 'field' => $field);
unset($label_html); unset($input_html);
endif;




if ($uselang) : ob_start();  // lang ?>
	<div class="control-group">
		<?php
		// Field via coreprops field type
		$field = isset($this->fields['core_lang']) ? $this->fields['core_lang'] : false;
		$field = isset($this->fields['core_lang_' . $typeid]) ? $this->fields['core_lang_' . $typeid] : $field;
		$field_description = $field && $field->description ? $field->description : JText::_($this->form->getField('tag')->description);  // Note: form element (XML file) is 'tag' not 'tags'
		$label_attrs = $field
			? 'class="' . $tip_class . $lbl_class . $lbl_extra_class . '" title="'.flexicontent_html::getToolTip(null, $field_description, 0, 1).'"'
			: 'class="' . $lbl_class . $lbl_extra_class . '"';
		ob_start();
		?>
		<div class="control-label" id="jform_language-lbl-outer">
			<label id="jform_language-lbl" for="jform_language" data-for="jform_language" class="<?php echo $lbl_class; ?> pull-left label-fcinner label-toplevel" >
				<?php echo $field ? $field->label : JText::_($this->form->getField('language')->getAttribute('label'));
				/* $field->label is set per type */ ?>
				<i class="icon-flag"></i>
			</label>
		</div>
		<?php $label_html = ob_get_clean(); echo $label_html; ob_start(); ?>

		<div class="controls ontainer_fcfield container_fcfield_name_language">
			<?php if (
				(in_array('mod_item_lang', $this->allowlangmods) || $isnew) &&
				in_array($uselang, array(1,3))
			) : ?>
				<?php echo $this->lists['languages']; ?>
			<?php else: ?>
				<?php echo $this->itemlang->image.' ['.$this->itemlang->name.']'; ?>
			<?php endif; ?>
		</div>

		<?php $input_html = ob_get_clean(); echo $input_html; ?>
	</div>
<?php
$fn = 'lang';
$captured[$fn] = ob_get_clean();
$rendered[$fn] = (object) array('label_html' => $label_html, 'input_html' => $input_html, 'html' => $captured[$fn], 'field' => $field);
unset($label_html); unset($input_html);
endif;




if ($tags_displayed) : ob_start();  // tags ?>
	<div class="control-group">
		<?php
		$field = isset($this->fields['tags']) ? $this->fields['tags'] : false;
		$field_description = $field && $field->description ? $field->description : JText::_($this->form->getField('tag')->description);  // Note: form element (XML file) is 'tag' not 'tags'
		$label_attrs = $field
			? 'class="' . $tip_class . $lbl_class . $lbl_extra_class . '" title="'.flexicontent_html::getToolTip(null, $field_description, 0, 1).'"'
			: 'class="' . $lbl_class . $lbl_extra_class . '"';
		ob_start();
		?>
		<div class="control-label" id="jform_tag-lbl-outer">
			<label id="jform_tag-lbl" data-for="input-tags" <?php echo $label_attrs; ?> >
				<?php echo $field ? $field->label : JText::_($this->form->getField('tag')->getAttribute('label'));
				/* $field->label is set per type */ ?>
				<i class="icon-tags-2"></i>
			</label>
		</div>
		<?php $label_html = ob_get_clean(); echo $label_html; ob_start(); ?>

		<div class="controls container_fcfield container_fcfield_name_tags">

			<?php if ($tags_editable) : ?>
				<div id="tags">
					<input type="text" id="input-tags" name="tagname" class="<?php echo $tip_class; ?>"
						placeholder="<?php echo JText::_($this->perms['cancreatetags'] ? 'FLEXI_TAG_SEARCH_EXISTING_CREATE_NEW' : 'FLEXI_TAG_SEARCH_EXISTING'); ?>"
						title="<?php echo flexicontent_html::getToolTip( 'FLEXI_NOTES', ($this->perms['cancreatetags'] ? 'FLEXI_TAG_CAN_ASSIGN_CREATE' : 'FLEXI_TAG_CAN_ASSIGN_ONLY'), 1, 1);?>"
					/>
					<span id='input_new_tag' ></span>
				</div>
			<?php endif; ?>

				<div class="fc_tagbox" id="fc_tagbox">

					<?php
					// Tags both shown and editable
					if ($tags_editable) echo '<input type="hidden" name="jform[tag][]" value="" />';
					?>

					<ul id="ultagbox">
					<?php
						$common_tags_selected = array();

						foreach($this->usedtagsdata as $tag)
						{
							if ($tags_editable)
							{
								if ( isset($this->quicktagsdata[$tag->id]) )
								{
									$common_tags_selected[$tag->id] = 1;
									continue;
								}
								echo '
								<li class="tagitem">
									<span>' . $tag->name . ($tag->translated_text ? ' (' . $tag->translated_text . ')' : '') . '</span>
									<input type="hidden" name="jform[tag][]" value="'.$tag->id.'" />
									<a href="javascript:;" class="deletetag" onclick="javascript:deleteTag(this);" title="'.JText::_('FLEXI_DELETE_TAG').'"></a>
								</li>';
							}
							else
							{
								echo '
								<li class="tagitem plain">
									<span>' . $tag->name . ($tag->translated_text ? ' (' . $tag->translated_text . ')' : '') . '</span>
									<input type="hidden" name="jform[tag][]" value="'.$tag->id.'" />
								</li>';
							}
						}
					?>
					</ul>

					<div class="fcclear"></div>

					<?php
					if ($tags_editable && count($this->quicktagsdata))
					{
						echo '<span class="tagicon '.$tip_class.'" title="'.JText::_('FLEXI_COMMON_TAGS').'"></span>';
						foreach ($this->quicktagsdata as $tag)
						{
							$_checked = isset($common_tags_selected[$tag->id]) ? ' checked="checked" ' : '';
							echo '
							<input type="checkbox" name="jform[tag][]" value="'.$tag->id.'" data-tagname="'.$tag->name.'" id="quick-tag-'.$tag->id.'" '.$_checked.' />
							<label for="quick-tag-'.$tag->id.'" class="tagitem">'.$tag->name.'</label>
							';
						}
					}
					?>
				</div>

		</div>

		<?php $input_html = ob_get_clean(); echo $input_html; ?>
	</div>
<?php
$fn = 'tags';
$captured[$fn] = ob_get_clean();
$rendered[$fn] = (object) array('label_html' => $label_html, 'input_html' => $input_html, 'html' => $captured[$fn], 'field' => $field);
unset($label_html); unset($input_html);
endif;




if (!$typeid || $usetype) : ob_start();  // type ?>
	<div class="control-group">
		<?php
		$field = isset($this->fields['document_type']) ? $this->fields['document_type'] : false;
		$field_description = $field && $field->description ? $field->description : JText::_($this->form->getField('type_id')->description);  // Note: form element (XML file) is 'type_id' not 'document_type'
		$warning_class = !$typeid && !$isSite ? ' label text-white bg-warning label-warning' : '';
		$label_attrs = $field
			? 'class="' . $tip_class . $lbl_class . $lbl_extra_class . $warning_class . '" title="'.flexicontent_html::getToolTip(null, $field_description, 0, 1).'"'
			: 'class="' . $lbl_class . $lbl_extra_class . '"';
		ob_start();
		?>
		<div class="control-label" id="jform_type_id-lbl-outer">
			<label id="jform_type_id-lbl" for="jform_type_id" data-for="jform_type_id" <?php echo $label_attrs; ?> >
				<?php echo $field ? $field->label : JText::_($this->form->getField('type_id')->getAttribute('label'));
				/* $field->label is set per type */ ?>
				<i class="icon-briefcase"></i>
			</label>
		</div>
		<?php $label_html = ob_get_clean(); echo $label_html; ob_start(); ?>

		<div class="controls container_fcfield container_fcfield_id_8 container_fcfield_name_type" id="container_fcfield_8">
			<?php echo $this->lists['type']; ?>
			<?php $type_warning = flexicontent_html::getToolTip('FLEXI_NOTES', 'FLEXI_TYPE_CHANGE_WARNING', 1, 1); ?>
			<span class="inlineFormTip <?php echo $tip_class; ?>" style="display:inline-block;" title="<?php echo $type_warning; ?>">
				<?php echo $info_image; ?>
			</span>
			<?php echo sprintf( $alert_box, 'id="fc-change-warning" style="display:none; float:left;"', 'warning', '', '<h4>'.JText::_( 'FLEXI_WARNING' ).'</h4> '.JText::_( 'FLEXI_TAKE_CARE_CHANGING_FIELD_TYPE' ) ); ?>
		</div>

		<?php $input_html = ob_get_clean(); echo $input_html; ?>
	</div>
<?php
$fn = 'document_type';
$captured[$fn] = ob_get_clean();
$rendered[$fn] = (object) array('label_html' => $label_html, 'input_html' => $input_html, 'html' => $captured[$fn], 'field' => $field);
unset($label_html); unset($input_html);
endif;




if (!$is_autopublished) :  // state and vstate (= approval of new document version) ?>

	<?php if ($usestate) : ob_start();  // state ?>
	<div class="control-group">
		<?php
		$field = isset($this->fields['state']) ? $this->fields['state'] : false;
		$field_description = $field && $field->description ? $field->description : JText::_($this->form->getField('state')->description);
		$label_attrs = $field
			? 'class="' . $tip_class . $lbl_class . $lbl_extra_class . '" title="'.flexicontent_html::getToolTip(null, $field_description, 0, 1).'"'
			: 'class="' . $lbl_class . $lbl_extra_class . '"';
		ob_start();
		?>
		<div class="control-label" id="jform_state-lbl-outer">
			<label id="jform_state-lbl" for="jform_state" data-for="jform_state" <?php echo $label_attrs; ?> >
				<?php echo $field ? $field->label : JText::_($this->form->getField('state')->getAttribute('label'));
				/* $field->label is set per type */ ?>
				<i class="icon-file-check"></i>
			</label>
		</div>
		<?php $label_html = ob_get_clean(); echo $label_html; ob_start(); ?>


		<?php if ( $this->perms['canpublish'] ) :  // state ?>

			<div class="controls container_fcfield container_fcfield_id_10 container_fcfield_name_state" id="container_fcfield_10">
				<?php echo $this->lists['state']; ?>
				<?php //echo $this->form->getInput('state'); ?>
				<span class="inlineFormTip <?php echo $tip_class; ?>" style="display:inline-block;" title="<?php echo flexicontent_html::getToolTip('FLEXI_NOTES', 'FLEXI_STATE_CHANGE_WARNING', 1, 1); ?>">
					<?php echo $info_image; ?>
				</span>
			</div>

		<?php else :

			echo $this->published;
			echo '<input type="hidden" name="jform[state]" id="jform_state" value="'.$this->row->state.'" />';

		endif;?>

		<?php $input_html = ob_get_clean(); echo $input_html; ?>
	</div>
	<?php
	$fn = 'state';
	$captured[$fn] = ob_get_clean();
	$rendered[$fn] = (object) array('label_html' => $label_html, 'input_html' => $input_html, 'html' => $captured[$fn], 'field' => $field);
	unset($label_html); unset($input_html);
	endif;
	?>


	<?php if ($this->perms['canpublish']) : // vstate (= approval of new document version) ?>

		<?php if ($use_versioning && !$auto_approve) : ob_start();
		// CASE 1. Display the 'publish changes' field.
		// User can publish and versioning is ON with auto approval  OFF
		?>

		<div class="control-group">
			<?php
			$label_attrs = 'class="' . $tip_class . $lbl_class . $lbl_extra_class . '" title="'.flexicontent_html::getToolTip('FLEXI_PUBLIC_DOCUMENT_CHANGES', 'FLEXI_PUBLIC_DOCUMENT_CHANGES_DESC', 1, 1).'"';
			ob_start();
			?>

			<div class="control-label" id="jform_vstate-lbl-outer">
				<label id="jform_vstate-lbl" data-for="jform_vstate" <?php echo $label_attrs; ?> >
					<?php echo JText::_( 'FLEXI_PUBLIC_DOCUMENT_CHANGES' ); ?>
				</label>
			</div>
			<?php $label_html = ob_get_clean(); echo $label_html; ob_start(); ?>

			<div class="controls container_fcfield container_fcfield_name_vstate">
				<?php echo $this->lists['vstate']; ?>
			</div>

			<?php $input_html = ob_get_clean(); echo $input_html; ?>
		</div>
		<?php
		$fn = 'vstate';
		$captured[$fn] = ob_get_clean();
		$rendered[$fn] = (object) array('label_html' => $label_html, 'input_html' => $input_html, 'html' => $captured[$fn], 'field' => $field);
		unset($label_html); unset($input_html);
		?>

		<?php else :
		// CASE 2. Hide 'publish changes' field.
		// User can publish AND either versioning is OFF or auto approval is ON (or both), publish changes immediately
		// vstate = 2 will be added at end of form (see file form_end.php)
		?>
		<?php endif; ?>

	<?php else :
		// User can not publish.  Display message that
		// Versioning ON: that item will need approval
		// Versioning OFF: that change are applied immediately and that existing item is overwritten immediately
	?>
		<?php ob_start(); ?>
		<div class="control-group">
			<?php ob_start(); ?>
				<div class="control-label" id="jform_vstate-lbl-outer">
				</div>
			<?php $label_html = ob_get_clean(); echo $label_html; ob_start(); ?>

			<div class="controls container_fcfield container_fcfield_name_vstate">
			<?php
			echo '<div class="alert alert-info">' . JText::_(($isnew || $use_versioning ? 'FLEXI_NEEDS_APPROVAL' : 'FLEXI_WITHOUT_APPROVAL') . ($isSite ? '' : '_BE')) . '</div>';
			// Enable approval if versioning disabled, this make sense,
			// since if use can edit item THEN item should be updated !!!
			$item_vstate = $use_versioning ? 1 : 2;
			echo '<input type="hidden" name="jform[vstate]" id="jform_vstate" value="'.$item_vstate.'" />';

			$input_html = ob_get_clean(); echo $input_html; ?>
		</div>
		<?php
		$fn = 'vstate';
		$captured[$fn] = ob_get_clean();
		$rendered[$fn] = (object) array('label_html' => $label_html, 'input_html' => $input_html, 'html' => $captured[$fn], 'field' => $field);
		unset($label_html); unset($input_html);
	endif;

endif;





if ($useaccess) : ob_start();  // access ?>
	<div class="control-group">
		<?php
		$field = isset($this->fields['core_access']) ? $this->fields['core_access'] : false;
		$field = isset($this->fields['core_access_' . $typeid]) ? $this->fields['core_access_' . $typeid] : $field;
		$field_description = $field && $field->description ? $field->description : JText::_($this->form->getField('access')->description);
		$label_attrs = $field
			? 'class="' . $tip_class . $lbl_class . $lbl_extra_class . '" title="'.flexicontent_html::getToolTip(null, $field_description, 0, 1).'"'
			: 'class="' . $lbl_class . $lbl_extra_class . '"';
		ob_start();
		?>
		<div class="control-label" id="jform_access-lbl-outer">
			<label id="jform_access-lbl" for="jform_access" data-for="jform_access" <?php echo $label_attrs; ?> >
				<?php echo $field ? $field->label : JText::_($this->form->getField('access')->getAttribute('label'));
				/* $field->label is set per type */ ?>
				<i class="icon-lock"></i>
			</label>
		</div>
		<?php $label_html = ob_get_clean(); echo $label_html; ob_start(); ?>

		<div class="controls container_fcfield container_fcfield_name_access">
			<?php echo $this->form->getInput('access');?>
		</div>

		<?php $input_html = ob_get_clean(); echo $input_html; ?>
	</div>
<?php
$fn = 'access';
$captured[$fn] = ob_get_clean();
$rendered[$fn] = (object) array('label_html' => $label_html, 'input_html' => $input_html, 'html' => $captured[$fn], 'field' => $field);
unset($label_html); unset($input_html);
endif;




/**
 * Note: This parameter is part of attribs parameter group, but we want to display it separately
 * when attribs are displayed in the form, it will be auto-skipped (from the section)
 */

if ($typeid && $allowdisablingcomments) : ob_start();  // disable_comments ?>
	<div class="control-group">
		<?php
		$label_attrs = 'class="' . $tip_class . $lbl_class . $lbl_extra_class . '" title="'.flexicontent_html::getToolTip('FLEXI_ALLOW_COMMENTS', 'FLEXI_ALLOW_COMMENTS_DESC', 1, 1).'"';
		ob_start();
		?>
		<div class="control-label" id="jform_attribs_comments-title-outer">
			<label id="jform_attribs_comments-title" <?php echo $label_attrs; ?> >
				<?php echo JText::_( 'FLEXI_ALLOW_COMMENTS' );?>
				<i class="icon-comment"></i>
			</label>
		</div>
		<?php $label_html = ob_get_clean(); echo $label_html; ob_start(); ?>

		<div class="controls container_fcfield container_fcfield_name_comments">
			<?php echo $this->lists['disable_comments']; ?>
		</div>

		<?php $input_html = ob_get_clean(); echo $input_html; ?>
	</div>
<?php
$fn = 'disable_comments';
$captured[$fn] = ob_get_clean();
$rendered[$fn] = (object) array('label_html' => $label_html, 'input_html' => $input_html, 'html' => $captured[$fn], 'field' => false);
unset($label_html); unset($input_html);
endif;




if ($typeid && $allow_subscribers_notify && $this->subscribers) :  ob_start();  // notify ?>
	<div class="control-group">
		<?php
		$label_attrs = 'class="' . $tip_class . $lbl_class  . $lbl_extra_class . '" title="'.flexicontent_html::getToolTip('FLEXI_NOTIFY_FAVOURING_USERS', 'FLEXI_NOTIFY_NOTES', 1, 1).'"';
		ob_start();
		?>
		<div class="control-label" id="jform_notify-lbl-outer">
			<label id="jform_notify-lbl" <?php echo $label_attrs; ?> >
				<?php echo JText::_( $isSite ? 'FLEXI_NOTIFY_FAVOURING_USERS' : 'FLEXI_NOTIFY_SUBSCRIBERS' ); ?>
				<i class="icon-mail"></i>
			</label>
		</div>
		<?php $label_html = ob_get_clean(); echo $label_html; ob_start(); ?>

		<div class="controls container_fcfield container_fcfield_name_notify">
			<span style="display:inline-block;">
				<?php echo $this->lists['notify']; ?>
			</span>
		</div>

		<?php $input_html = ob_get_clean(); echo $input_html; ?>
	</div>
<?php
$fn = 'notify_subscribers';
$captured[$fn] = ob_get_clean();
$rendered[$fn] = (object) array('label_html' => $label_html, 'input_html' => $input_html, 'html' => $captured[$fn], 'field' => false);
unset($label_html); unset($input_html);
endif;





if ($typeid && $allow_owner_notify && $this->row->created_by != $user->id) :  ob_start();  // notify_owner ?>
	<div class="control-group">
		<?php
		$label_attrs = 'class="' . $tip_class . $lbl_class . $lbl_extra_class . '" title="'.flexicontent_html::getToolTip('FLEXI_NOTIFY_OWNER', 'FLEXI_NOTIFY_OWNER_DESC', 1, 1).'"';
		ob_start();
		?>
		<div class="control-label" id="jform_notify_owner-lbl-outer">
			<label id="jform_notify_owner-lbl" <?php echo $label_attrs; ?> >
				<?php echo JText::_('FLEXI_NOTIFY_OWNER'); ?>
				<i class="icon-mail"></i>
			</label>
		</div>
		<?php $label_html = ob_get_clean(); echo $label_html; ob_start(); ?>

		<div class="controls container_fcfield container_fcfield_name_notify">
			<span style="display:inline-block;">
			<?php echo $this->lists['notify_owner']; ?>
			</span>

			<?php
				$tipOwnerCanEdit      = JText::_('FLEXI_OWNER_CAN_EDIT_THIS_ITEM') . ': &nbsp; - ' . mb_strtoupper(JText::_($this->ownerCanEdit ? 'JYES' : 'JNO')) . ' -';
				$tipOwnerCanEditState = JText::_('FLEXI_OWNER_CAN_PUBLISH_CHANGES_OF_THIS_ITEM') . ': &nbsp; - ' . mb_strtoupper(JText::_($this->ownerCanEdit ? 'JYES' : 'JNO')) . ' -';
			?>
			<span class="inlineFormTip <?php echo $tip_class; ?>" style="display:inline-block;"
			      title="<?php echo flexicontent_html::getToolTip('FLEXI_NOTES', $tipOwnerCanEdit . '<br>' . $tipOwnerCanEditState, 1, 1); ?>">
				<?php echo $this->ownerCanEditState ? $info_image : $warn_image; ?>
			</span>
		</div>

		<?php $input_html = ob_get_clean(); echo $input_html; ?>
	</div>
<?php
$fn = 'notify_owner';
$captured[$fn] = ob_get_clean();
$rendered[$fn] = (object) array('label_html' => $label_html, 'input_html' => $input_html, 'html' => $captured[$fn], 'field' => false);
unset($label_html); unset($input_html);
endif;




if ( $secondary_displayed || !empty($this->lists['featured_cid']) ) : ob_start();  // categories ?>

	<fieldset class="basicfields_set" id="fcform_categories_container">
		<legend>
			<?php $fset_lbl = JText::_('FLEXI_CATEGORIES') .' / '. JText::_('FLEXI_FEATURED');?>
			<span class="fc_legend_header_text"><?php echo JText::_( $fset_lbl ); ?></span>
		</legend>

		<!--__FC_CATEGORY_BOX__--><?php /* This is replaced by item 's main categry selector if this is placed inside here */ ?>

		<?php if ($secondary_displayed) : /* optionally via MENU SPECIFIED categories subset (instead of categories with CREATE perm) */ ?>

			<?php ob_start(); ?>
			<div class="control-group">
				<?php
				$field = isset($this->fields['categories']) ? $this->fields['categories'] : false;
				$field_description = $field && $field->description ? $field->description : ($isSite ? JText::_('FLEXI_CATEGORIES_NOTES') : '');
				$label_attrs = 'class="' . $tip_class . $lbl_class . $lbl_extra_class . '" title="'.flexicontent_html::getToolTip(null, $field_description, 0, 1).'"';
				ob_start();
				?>
				<div class="control-label" id="jform_cid-lbl-outer">
					<label id="jform_cid-lbl" for="jform_cid" data-for="jform_cid" <?php echo $label_attrs; ?>>
						<?php echo $field ? $field->label : JText::_($this->form->getField('title')->getAttribute('label'));
						/* $field->label is set per type */ ?>
					</label>
				</div>
				<?php $label_html = ob_get_clean(); echo $label_html; ob_start(); ?>

				<div class="controls container_fcfield container_fcfield_name_cid">
					<?php /* MENU SPECIFIED secondary categories (new item at frontend) or categories according to perms */ ?>
					<?php echo $this->menuCats && $this->menuCats->cid ? $this->menuCats->cid : $this->lists['cid']; ?>
				</div>
				<?php $input_html = ob_get_clean(); echo $input_html; ?>

			</div>

		<?php $fn = 'cid';
		echo $html = ob_get_clean();
		$rendered[$fn] = (object) array('label_html' => $label_html, 'input_html' => $input_html, 'html' => $html, 'field' => $field);
		unset($label_html); unset($input_html); ?>
		<?php endif; ?>


		<?php if ( !empty($this->lists['featured_cid']) ) : ?>

			<?php ob_start(); ?>
			<div class="control-group">

				<?php ob_start(); ?>
				<div class="control-label" id="jform_featured_cid-lbl-outer">
					<label id="jform_featured_cid-lbl" for="jform_featured_cid" data-for="jform_featured_cid" class="<?php echo $lbl_class; ?>  pull-left label-fcinner label-toplevel">
						<?php echo JText::_( 'FLEXI_FEATURED_CATEGORIES' ); ?>
					</label>
				</div>
				<?php $label_html = ob_get_clean(); echo $label_html; ob_start(); ?>

				<div class="controls container_fcfield container_fcfield_name_featured_cid">
					<?php echo $this->lists['featured_cid']; ?>
				</div>

				<?php $input_html = ob_get_clean(); echo $input_html; ?>

			</div>

		<?php $fn = 'featured_cid';
		echo $html = ob_get_clean();
		$rendered[$fn] = (object) array('label_html' => $label_html, 'input_html' => $input_html, 'html' => $html, 'field' => false);
		unset($label_html); unset($input_html); ?>
		<?php endif; ?>


		<?php if (!$isSite) : /* We do not (yet) allow modifying featured flag in frontend */ ?>

		<?php ob_start(); ?>
		<div class="control-group">

			<?php ob_start(); ?>
			<div class="control-label" id="jform_featured-lbl-outer">
				<label id="jform_featured-lbl" class="<?php echo $lbl_class; ?>  pull-left label-fcinner label-toplevel">
					<?php echo JText::_( 'FLEXI_FEATURED' ); ?>
					<br>
					<small><?php echo JText::_( 'FLEXI_JOOMLA_FEATURED_VIEW' ); ?></small>
				</label>
			</div>
			<?php $label_html = ob_get_clean(); echo $label_html; ob_start(); ?>

			<div class="controls container_fcfield container_fcfield_name_featured">
				<?php echo $this->lists['featured']; ?>
				<?php //echo $this->form->getInput('featured');?>
			</div>

			<?php $input_html = ob_get_clean(); echo $input_html; ?>

		</div>
		<?php $fn = 'featured';
		echo $html = ob_get_clean();
		$rendered[$fn] = (object) array('label_html' => $label_html, 'input_html' => $input_html, 'html' => $html, 'field' => false);
		unset($label_html); unset($input_html); ?>
		<?php endif; ?>

	</fieldset>
<?php
$fn = 'categories';
$captured[$fn] = ob_get_clean();
$rendered[$fn] = (object) array('label_html' => '', 'input_html' => '', 'html' => $captured[$fn], 'field' => false);
unset($label_html); unset($input_html);
endif;





$modifyLangAssocs = in_array('mod_original_content_assoc', $this->allowlangmods) && $uselang === 1;
if ( flexicontent_db::useAssociations() && $modifyLangAssocs ) : ob_start(); // lang_assocs ?>

	<fieldset class="basicfields_set" id="fcform_language_container">
		<legend>
			<span class="fc_legend_header_text">
				<?php echo JText::_( 'FLEXI_LANGUAGE' ) . ' '. JText::_( 'FLEXI_ASSOCIATIONS' ) ; ?>
			</span>
		</legend>

		<!--__FC_LANGUAGE_BOX__--><?php /* This is replaced by item 's language selector if this is placed inside here */ ?>

		<!-- BOF of language / language associations section -->
		<?php if (flexicontent_db::useAssociations() && $modifyLangAssocs): ?>
			<div class="fcclear"></div>

			<?php if ($this->row->language!='*'): ?>
				<?php echo JLayoutHelper::render('joomla.edit.associations', $this); ?>
			<?php else: ?>
				<?php echo JText::_( 'FLEXI_ASSOC_NOT_POSSIBLE' ); ?>
			<?php endif; ?>
		<?php endif; ?>
		<!-- EOF of language / language associations section -->

	</fieldset>
<?php
$fn = 'lang_assocs';
$captured[$fn] = ob_get_clean();
$rendered[$fn] = (object) array('label_html' => '', 'input_html' => '', 'html' => $captured[$fn], 'field' => false);
unset($label_html); unset($input_html);
endif;




if ($typeid && $this->perms['canright'] && $usepublicationdetails === 2) : ob_start(); ?>
		<?php
		// used to hide "Reset Hits" when hits = 0
		$visibility = !$this->row->hits ? 'style="display: none; visibility: hidden;"' : '';
		$visibility2 = !$this->row->rating_count ? 'style="display: none; visibility: hidden;"' : '';
		$default_label_class = ' fc-prop-lbl ';
		?>

		<table class="fc-form-tbl fcinner" style="margin: 10px; width: auto;">
		<tr>
			<td colspan="2">
				<h3><?php echo JText::_( 'FLEXI_VERSION_INFO' ); ?></h3>
			</td>
		</tr>
		<?php
		if ( $this->row->id ) {
		?>
		<tr>
			<td class="key">
				<label class="fc-prop-lbl"><?php echo JText::_( 'FLEXI_ITEM_ID' ); ?></label>
			</td>
			<td>
				<span class="badge badge-info"><?php echo $this->row->id; ?></span>
			</td>
		</tr>
		<?php
		}
		?>
		<tr>
			<td class="key">
				<?php
					$field = isset($this->fields['state']) ? $this->fields['state'] : false;
					$field_description = $field && $field->description ? $field->description : JText::_($this->form->getField('state')->description);
					$label_attrs = $field
						? 'class="' . $tip_class . ' fc-prop-lbl" title="'.flexicontent_html::getToolTip(null, $field_description, 0, 1).'"'
						: 'class="' . $default_label_class . '"';
				?>
				<label <?php echo $label_attrs; ?>><?php echo $field ? $field->label : JText::_( 'FLEXI_STATE' ); ?></label>
			</td>
			<td>
				<span class="badge badge-info"><?php echo $this->published; ?></span>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php
					$field = isset($this->fields['hits']) ? $this->fields['hits'] : false;
					$field_description = $field && $field->description ? $field->description : JText::_($this->form->getField('hits')->description);
					$label_attrs = $field
						? 'class="' . $tip_class . ' fc-prop-lbl" title="'.flexicontent_html::getToolTip(null, $field_description, 0, 1).'"'
						: 'class="' . $default_label_class . '"';
				?>
				<label <?php echo $label_attrs; ?>><?php echo $field ? $field->label : JText::_( 'FLEXI_HITS' ); ?></label>
			</td>
			<td>
				<div id="hits" style="float:left;" class="badge badge-info"><?php echo $this->row->hits; ?></div> &nbsp;
				<span <?php echo $visibility; ?>>
					<input name="reset_hits" type="button" class="button btn-small btn-warning" value="<?php echo JText::_( 'FLEXI_RESET' ); ?>" onclick="reseter('<?php echo $ctrl_items; ?>resethits', '<?php echo $this->row->id; ?>', 'hits')" />
				</span>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php
					$field = isset($this->fields['voting']) ? $this->fields['voting'] : false;
					$field_description = $field && $field->description ? $field->description : '';
					$label_attrs = $field
						? 'class="' . $tip_class . ' fc-prop-lbl" title="'.flexicontent_html::getToolTip(null, $field_description, 0, 1).'"'
						: 'class="' . $default_label_class . '"';
				?>
				<label <?php echo $label_attrs; ?>><?php echo $field ? $field->label : JText::_( 'FLEXI_SCORE' ); ?></label>
			</td>
			<td>
				<div id="votes" style="float:left;" class="badge badge-info"><?php echo $this->ratings; ?></div> &nbsp;
				<span <?php echo $visibility2; ?>>
					<input name="reset_votes" type="button" class="button btn-small btn-warning" value="<?php echo JText::_( 'FLEXI_RESET' ); ?>" onclick="reseter('<?php echo $ctrl_items; ?>resetvotes', '<?php echo $this->row->id; ?>', 'votes')" />
				</span>
			</td>
		</tr>

		<tr>
			<td class="key">
				<label class="<?php echo $default_label_class; ?>"><?php echo JText::_( 'FLEXI_REVISED' ); ?></label>
			</td>
			<td>
				<span class="badge badge-info">
					<?php echo $this->row->last_version;?> <?php echo JText::_( 'FLEXI_TIMES' ); ?>
				</span>
			</td>
		</tr>
		<tr>
			<td class="key">
				<label class="<?php echo $default_label_class; ?>"><?php echo JText::_( 'FLEXI_FRONTEND_ACTIVE_VERSION' ); ?></label>
			</td>
			<td>
				<span class="badge badge-info">#<?php echo $this->row->current_version;?></span>
			</td>
		</tr>
		<tr>
			<td class="key">
				<label class="<?php echo $default_label_class; ?>"><?php echo JText::_( 'FLEXI_FORM_LOADED_VERSION' ); ?></label>
			</td>
			<td>
				<span class="badge badge-info">#<?php echo $this->row->version;?></span>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php
					$field = isset($this->fields['created']) ? $this->fields['created'] : false;
					$field_description = $field && $field->description ? $field->description : JText::_($this->form->getField('created')->description);
					$label_attrs = $field
						? 'class="' . $tip_class . ' fc-prop-lbl" title="'.flexicontent_html::getToolTip(null, $field_description, 0, 1).'"'
						: 'class="' . $default_label_class . '"';
				?>
				<label <?php echo $label_attrs; ?>><?php echo $field ? $field->label : JText::_( 'FLEXI_CREATED' ); ?></label>
			</td>
			<td>
				<?php echo $this->row->created == $this->nullDate
					? JText::_( 'FLEXI_NEW_ITEM' )
					: JHtml::_('date',  $this->row->created,  JText::_( 'DATE_FORMAT_LC2' ) ); ?>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php
					$field = isset($this->fields['modified']) ? $this->fields['modified'] : false;
					$field_description = $field && $field->description ? $field->description : JText::_($this->form->getField('modified')->description);
					$label_attrs = $field
						? 'class="' . $tip_class . ' fc-prop-lbl" title="'.flexicontent_html::getToolTip(null, $field_description, 0, 1).'"'
						: 'class="' . $default_label_class . '"';
				?>
				<label <?php echo $label_attrs; ?>><?php echo $field ? $field->label : JText::_( 'FLEXI_MODIFIED' ); ?></label>
			</td>
			<td>
				<?php
					if ( $this->row->modified == $this->nullDate ) {
						echo JText::_( 'FLEXI_NOT_MODIFIED' );
					} else {
						echo JHtml::_('date',  $this->row->modified, JText::_( 'DATE_FORMAT_LC2' ));
					}
				?>
			</td>
		</tr>
	<?php if ($use_versioning) : ?>
			<tr>
				<td class="key">
					<label class="<?php echo $default_label_class; ?>"><?php echo JText::_( 'FLEXI_VERSION_COMMENT' ); ?></label>
				</td>
				<td></td>
			</tr><tr>
				<td colspan="2" style="text-align:center;">
					<textarea name="jform[versioncomment]" id="versioncomment" style="width: 96%; padding: 6px 2%; line-height:120%" rows="4"></textarea>
				</td>
			</tr>
		<?php endif; ?>
		</table>
		<div class="fcclear"></div>
<?php
$fn = 'item_screen';
$captured[$fn] = ob_get_clean();
$rendered[$fn] = (object) array('label_html' => '', 'input_html' => '', 'html' => $captured[$fn], 'field' => false);
unset($label_html); unset($input_html);
endif;





if ($usepublicationdetails) : // timezone_info, publication_details ?>

	<?php ob_start(); ?>
		<?php
		// Dates displayed in the item form, are in user timezone for J2.5, and in site's default timezone for J1.5
		$site_zone = $app->getCfg('offset');
		$user_zone = $user->getParam('timezone', $site_zone);

		$tz = new DateTimeZone( $user_zone );
		$tz_offset = $tz->getOffset(new JDate()) / 3600;
		$tz_info =  $tz_offset > 0 ? ' UTC +' . $tz_offset : ' UTC ' . $tz_offset;

		$tz_info .= ' ('.$user_zone.')';
		$msg = JText::sprintf( FLEXI_J16GE ? 'FLEXI_DATES_IN_USER_TIMEZONE_NOTE' : 'FLEXI_DATES_IN_SITE_TIMEZONE_NOTE', ' ', $tz_info );
		echo sprintf( $alert_box, ' style="display: inline-block;" ', 'info', 'fc-nobgimage', $msg );
		?>
	<?php
	$fn = 'timezone_info';
	$captured[$fn] = ob_get_clean();
	$rendered[$fn] = (object) array('label_html' => '', 'input_html' => '', 'html' => $captured[$fn], 'field' => false);
	unset($label_html); unset($input_html);
	?>

	<?php ob_start(); ?>
		<div class="control-group">
			<div class="control-label" id="publish_up-lbl-outer"><?php echo str_replace('class="', 'class="' . $lbl_class . ' label-fcinner ', $this->form->getLabel('publish_up')); ?></div>
			<div class="controls container_fcfield"><?php echo /*$this->perms['canpublish'] || $this->perms['editpublishupdown']*/ $this->form->getInput('publish_up'); ?></div>
		</div>
	<?php
	$fn = 'publish_up';
	$captured[$fn] = ob_get_clean();
	$rendered[$fn] = (object) array('label_html' => '', 'input_html' => '', 'html' => $captured[$fn], 'field' => false);
	unset($label_html); unset($input_html);
	?>

	<?php ob_start(); ?>
		<div class="control-group">
			<div class="control-label" id="publish_down-lbl-outer"><?php echo str_replace('class="', 'class="' . $lbl_class . ' label-fcinner ', $this->form->getLabel('publish_down')); ?></div>
			<div class="controls container_fcfield"><?php echo /*$this->perms['canpublish'] || $this->perms['editpublishupdown']*/ $this->form->getInput('publish_down'); ?></div>
		</div>
	<?php
	$fn = 'publish_down';
	$captured[$fn] = ob_get_clean();
	$rendered[$fn] = (object) array('label_html' => '', 'input_html' => '', 'html' => $captured[$fn], 'field' => false);
	unset($label_html); unset($input_html);
	?>

	<?php if ($usepublicationdetails === 2) : ob_start(); ?>
		<div class="control-group">
			<div class="control-label" id="created_by-lbl-outer"><?php echo str_replace('class="', 'class="' . $lbl_class . ' label-fcinner ', $this->form->getLabel('created_by')); ?></div>
			<div class="controls container_fcfield"><?php echo /*$this->perms['editcreator']*/ $this->form->getInput('created_by'); ?></div>
		</div>
	<?php
	$fn = 'created_by';
	$captured[$fn] = ob_get_clean();
	$rendered[$fn] = (object) array('label_html' => '', 'input_html' => '', 'html' => $captured[$fn], 'field' => false);
	unset($label_html); unset($input_html);
	endif;
	?>

	<?php if ($usepublicationdetails === 2) : ob_start(); ?>
		<div class="control-group">
			<div class="control-label" id="created-lbl-outer"><?php echo str_replace('class="', 'class="' . $lbl_class . ' label-fcinner ', $this->form->getLabel('created')); ?></div>
			<div class="controls container_fcfield"><?php echo /*$this->perms['editcreationdate']*/ $this->form->getInput('created'); ?></div>
		</div>
	<?php
	$fn = 'created';
	$captured[$fn] = ob_get_clean();
	$rendered[$fn] = (object) array('label_html' => '', 'input_html' => '', 'html' => $captured[$fn], 'field' => false);
	unset($label_html); unset($input_html);
	endif;
	?>

	<?php ob_start(); ?>
		<div class="control-group">
			<div class="control-label" id="created_by_alias-lbl-outer"><?php echo str_replace('class="', 'class="' . $lbl_class . ' label-fcinner ', $this->form->getLabel('created_by_alias')); ?></div>
			<div class="controls container_fcfield"><?php echo /*$this->perms['editcreator']*/ $this->form->getInput('created_by_alias'); ?></div>
		</div>
	<?php
	$fn = 'created_by_alias';
	$captured[$fn] = ob_get_clean();
	$rendered[$fn] = (object) array('label_html' => '', 'input_html' => '', 'html' => $captured[$fn], 'field' => false);
	unset($label_html); unset($input_html);
	?>

	<?php if ($usepublicationdetails === 2) : ob_start(); ?>
		<div class="control-group">
			<div class="control-label" id="modified_by-lbl-outer"><?php echo str_replace('class="', 'class="' . $lbl_class . ' label-fcinner ', $this->form->getLabel('modified_by')); ?></div>
			<div class="controls container_fcfield"><?php echo $this->form->getInput('modified_by'); ?></div>
		</div>
	<?php
	$fn = 'modified_by';
	$captured[$fn] = ob_get_clean();
	$rendered[$fn] = (object) array('label_html' => '', 'input_html' => '', 'html' => $captured[$fn], 'field' => false);
	unset($label_html); unset($input_html);
	endif;
	?>

	<?php if ($usepublicationdetails === 2) : ob_start(); ?>
		<div class="control-group">
			<div class="control-label" id="modified-lbl-outer"><?php echo str_replace('class="', 'class="' . $lbl_class . ' label-fcinner ', $this->form->getLabel('modified')); ?></div>
			<div class="controls container_fcfield"><?php echo $this->form->getInput('modified'); ?></div>
		</div>
	<?php
	$fn = 'modified';
	$captured[$fn] = ob_get_clean();
	$rendered[$fn] = (object) array('label_html' => '', 'input_html' => '', 'html' => $captured[$fn], 'field' => false);
	unset($label_html); unset($input_html);
	endif;
	?>

<?php endif;




if ( $typeid && $usemetadata ) : ob_start(); // metadata ?>
	<fieldset class="panelform">
		<legend>
			<?php echo JText::_( 'FLEXI_META' ); ?>
		</legend>

		<?php if ( $usemetadata >= 1) : ?>

		<div class="control-group">
			<div class="control-label">
				<?php echo $this->form->getLabel('metadesc'); ?>
			</div>
			<div class="controls container_fcfield">
				<?php if ( isset($this->row->item_translations) ) : ?>
					<?php
					array_push($tabSetStack, $tabSetCnt);
					$tabSetCnt = ++$tabSetMax;
					$tabCnt[$tabSetCnt] = 0;
					?>
					<!-- tabber start -->
					<div class="fctabber tabber-inline s-gray tabber-lang" id="fcform_tabset_<?php echo $tabSetCnt; ?>">
						<div class="tabbertab fc-tabbed-field-box" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" >
							<h3 class="tabberheading"> <?php echo '-'.$this->itemlang->name.'-'; ?> </h3>
							<?php echo $this->form->getInput('metadesc'); ?>
						</div>
						<?php foreach ($this->row->item_translations as $t): ?>
							<?php if ($this->itemlang->shortcode!=$t->shortcode && $t->shortcode!='*' && !in_array($t->code, $disable_langs)) : ?>
								<div class="tabbertab fc-tabbed-field-box" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" >
									<h3 class="tabberheading"> <?php echo $t->name; // $t->shortcode; ?> </h3>
									<?php
									$ff_id = 'jfdata_'.$t->shortcode.'_metadesc';
									$ff_name = 'jfdata['.$t->shortcode.'][metadesc]';
									?>
									<textarea id="<?php echo $ff_id; ?>" class="fcfield_textareaval" rows="3" cols="46" name="<?php echo $ff_name; ?>"><?php echo @$t->fields->metadesc->value; ?></textarea>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
					<!-- tabber end -->
					<?php $tabSetCnt = array_pop($tabSetStack); ?>

				<?php else : ?>
					<?php echo $this->form->getInput('metadesc'); ?>
				<?php endif; ?>

			</div>
		</div>

		<div class="control-group">
			<div class="control-label">
				<?php echo $this->form->getLabel('metakey'); ?>
			</div>

			<div class="controls container_fcfield">
				<?php if ( isset($this->row->item_translations) ) :?>
					<?php
					array_push($tabSetStack, $tabSetCnt);
					$tabSetCnt = ++$tabSetMax;
					$tabCnt[$tabSetCnt] = 0;
					?>
					<!-- tabber start -->
					<div class="fctabber tabber-inline s-gray tabber-lang" id="fcform_tabset_<?php echo $tabSetCnt; ?>">
						<div class="tabbertab fc-tabbed-field-box" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" >
							<h3 class="tabberheading"> <?php echo '-'.$this->itemlang->name.'-'; ?> </h3>
							<?php echo $this->form->getInput('metakey'); ?>
						</div>
						<?php foreach ($this->row->item_translations as $t): ?>
							<?php if ($this->itemlang->shortcode!=$t->shortcode && $t->shortcode!='*' && !in_array($t->code, $disable_langs)) : ?>
								<div class="tabbertab fc-tabbed-field-box" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" >
									<h3 class="tabberheading"> <?php echo $t->name; // $t->shortcode; ?> </h3>
									<?php
									$ff_id = 'jfdata_'.$t->shortcode.'_metakey';
									$ff_name = 'jfdata['.$t->shortcode.'][metakey]';
									?>
									<textarea id="<?php echo $ff_id; ?>" class="fcfield_textareaval" rows="3" cols="46" name="<?php echo $ff_name; ?>"><?php echo @$t->fields->metakey->value; ?></textarea>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
					<!-- tabber end -->
					<?php $tabSetCnt = array_pop($tabSetStack); ?>

				<?php else : ?>
					<?php echo $this->form->getInput('metakey'); ?>
				<?php endif; ?>

			</div>
		</div>
		<?php endif; ?>


		<?php if ($usemetadata === 2) :?>
		<?php foreach ($this->form->getGroup('metadata') as $field) :

			if ($field->getAttribute('type') === 'separator') : echo $field->input;

			elseif ($field->hidden) : ?>
				<span style="display:none !important;">
					<?php echo $field->input; ?>
				</span>

			<?php else: ?>
			<div class="control-group">
				<div class="control-label">
					<?php echo $field->label; ?>
				</div>
				<div class="controls container_fcfield">
					<?php echo $this->getFieldInheritedDisplay($field, $this->iparams); ?>
				</div>
			</div>

			<?php endif; ?>

		<?php endforeach; ?>
		<?php endif; ?>

	</fieldset>
<?php
$fn = 'metadata';
$captured[$fn] = ob_get_clean();
$rendered[$fn] = (object) array('label_html' => '', 'input_html' => '', 'html' => $captured[$fn], 'field' => false);
unset($label_html); unset($input_html);
endif;




if ($typeid && $useseoconf) : ob_start(); // seoconf ?>
	<fieldset class="panelform">
		<legend>
			<?php echo JText::_( 'FLEXI_SEO' ); ?>
		</legend>

		<?php foreach ($this->form->getFieldset('params-seoconf') as $field) :

			if ($field->getAttribute('type') === 'separator') : echo $field->input;

			elseif ($field->hidden) : ?>
				<span style="display:none !important;">
					<?php echo $field->input; ?>
				</span>

			<?php else: ?>
			<div class="control-group">
				<div class="control-label">
					<?php echo $field->label; ?>
				</div>
				<div class="controls container_fcfield">
					<?php echo $this->getFieldInheritedDisplay($field, $this->iparams); ?>
				</div>
			</div>

			<?php endif; ?>

		<?php endforeach; ?>
	</fieldset>
<?php
$fn = 'seoconf';
$captured[$fn] = ob_get_clean();
$rendered[$fn] = (object) array('label_html' => '', 'input_html' => '', 'html' => $captured[$fn], 'field' => false);
unset($label_html); unset($input_html);
endif;




if ($displayed_fieldSets) : ob_start();  // display_params ?>

<?php if(count($displayed_fieldSets) > 1) :
	array_push($tabSetStack, $tabSetCnt);
	$tabSetCnt = ++$tabSetMax;
	$tabCnt[$tabSetCnt] = 0;
?>
<!-- tabber start -->
<div class="fctabber s-gray tabber-displayparams" id="fcform_tabset_<?php echo $tabSetCnt; ?>">
<?php endif; ?>

	<?php foreach ($displayed_fieldSets as $name => $fieldSet) :
		$label = !empty($fieldSet->label)
			? $fieldSet->label
			: 'COM_FLEXICONTENT_'.$name.'_FIELDSET_LABEL';
		$label = JText::_($label) === 'COM_FLEXICONTENT_'.$name.'_FIELDSET_LABEL'
			? 'COM_CONTENT_'.$name.'_FIELDSET_LABEL'
			: $label;
		$icon_class = $name === 'metafb' ? 'icon-users' : '';

		if(count($displayed_fieldSets) > 1) : ?>
		<div class="tabbertab fc-tabbed-displayparams-box" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" >
			<h3 class="tabberheading"> <?php echo JText::_($label); ?> </h3>
		<?php else : ?>
		<fieldset class="flexi_params panelform">
			<legend><?php echo JText::_($label); ?></legend>
		<?php endif ?>


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

		<?php if(count($displayed_fieldSets) > 1) : ?>
		</div>
		<?php else : ?>
		</fieldset>
		<?php endif ?>


	<?php endforeach; ?>

<?php if(count($displayed_fieldSets) > 1) : ?>
</div>
<?php endif; ?>

<?php
$fn = 'display_params';
$captured[$fn] = ob_get_clean();
$rendered[$fn] = (object) array('label_html' => '', 'input_html' => '', 'html' => $captured[$fn], 'field' => false);
unset($label_html); unset($input_html);
endif;




if ($typeid && $selecttheme) : ?>

	<?php if ( $selecttheme >= 1 ) : ob_start();
		foreach ($this->form->getFieldset('themes') as $field) :

			if ($field->getAttribute('type') === 'separator' || !$field->label || $field->hidden)
			{
				echo $field->input;
				continue;
			}

			elseif ($field->input)
			{
				$_depends = $field->getAttribute('depend_class');
				echo '
				<div class="control-group'.($_depends ? ' '.$_depends : '').'" id="'.$field->id.'-container">
					<div class="control-label">
						'.str_replace('class="', 'class="' . $lbl_class . ' label-fcinner ', $field->label).'
					</div>
					<div class="controls container_fcfield">
						' . $this->getFieldInheritedDisplay($field, $this->iparams) . '
					</div>
				</div>
				';
			}
		endforeach; ?>

		<div class="fcclear"></div>
		<div class="fc-success fc-mssg-inline" style="font-size: 12px; margin: 8px 0 !important;" id="__content_type_default_layout__">
			<?php /*echo JText::sprintf( 'FLEXI_USING_CONTENT_TYPE_LAYOUT', $this->tparams->get('ilayout') ) . "<br/><br/>";*/ ?>
			<?php echo JText::_($isSite ? 'FLEXI_USING_LAYOUT_DEFAULTS' : 'FLEXI_RECOMMEND_CONTENT_TYPE_LAYOUT'); ?>
		</div>
	<?php
	$fn = 'layout_selection';
	$captured[$fn] = ob_get_clean();
	$rendered[$fn] = (object) array('label_html' => '', 'input_html' => '', 'html' => $captured[$fn], 'field' => false);
	unset($label_html); unset($input_html);
	endif;



	if ( $selecttheme >= 2 ) : ob_start(); ?>
		<?php $item_layout = $this->row->itemparams->get('ilayout'); ?>

		<div class="fc-sliders-plain-outer <?php echo $item_layout ? 'fc_preloaded' : ''; ?>">
			<?php
			$slider_set_id = 'theme-sliders-' . $this->form->getValue('id');
			//echo JHtml::_('sliders.start', $slider_set_id, array('useCookie'=>1));
			echo JHtml::_('bootstrap.startAccordion', $slider_set_id, array(/*'active' => ''*/));

			$groupname = 'attribs';  // Field Group name this is for name of <fields name="..." >

			foreach ($this->tmpls as $tmpl) :

				$form_layout = $tmpl->params;
				$slider_title = '
					<span class="btn"><i class="icon-edit"></i>
						' . JText::_('FLEXI_PARAMETERS_THEMES_SPECIFIC') . ' : ' . $tmpl->name . '
					</span>';
				$slider_id = $tmpl->name . '-' . $groupname . '-options';

				//echo JHtml::_('sliders.panel', $slider_title, $slider_id);
				echo JHtml::_('bootstrap.addSlide', $slider_set_id, $slider_title, $slider_id);

				if (!$item_layout || $tmpl->name !== $item_layout)
				{
					echo JHtml::_('bootstrap.endSlide');
					continue;
				}

				$fieldSets = $form_layout->getFieldsets($groupname);
				foreach ($fieldSets as $fsname => $fieldSet) : ?>
					<fieldset class="panelform">

					<?php
					if (isset($fieldSet->label) && trim($fieldSet->label)) :
						echo '<div style="margin:0 0 12px 0; font-size: 16px; background-color: #333; float:none;" class="fcsep_level0">'.JText::_($fieldSet->label).'</div>';
					endif;
					if (isset($fieldSet->description) && trim($fieldSet->description)) :
						echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
					endif;

					foreach ($form_layout->getFieldset($fsname) as $field) :

						if ($field->getAttribute('not_inherited')) continue;
						//if ($field->getAttribute('cssprep')) continue;

						$fieldname  = $field->fieldname;
						$cssprep    = $field->getAttribute('cssprep');
						$labelclass = $cssprep == 'less' ? 'fc_less_parameter' : '';

						// For J3.7.0+ , we have extra form methods Form::getFieldXml()
						if ($cssprep && FLEXI_J37GE)
						{
							$_value = $form_layout->getValue($fieldname, $groupname, $this->row->parameters->get($fieldname));

							// Not only set the disabled attribute but also clear the required attribute to avoid issues with some fields (like 'color' field)
							$form_layout->setFieldAttribute($fieldname, 'disabled', 'true', $field->group);
							$form_layout->setFieldAttribute($fieldname, 'required', 'false', $field->group);

							$field->setup($form_layout->getFieldXml($fieldname, $field->group), $_value, $field->group);
						}

						echo ($field->getAttribute('type') === 'separator' || $field->hidden || !$field->label)
						 ? $field->input
						 : '
							<div class="control-group" id="'.$field->id.'-container">
								<div class="control-label">'.
									str_replace('class="', 'class="'.$labelclass.' ',
										str_replace(' for="', ' data-for="',
											str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_',
												$form_layout->getLabel($fieldname, $groupname)
											)
										)
									) . '
								</div>
								<div class="controls">
									' . ($cssprep && !FLEXI_J37GE
										? (isset($this->iparams[$fieldname]) ? '<i>' . $this->iparams[$fieldname] . '</i>' : '<i>default</i>')
										:
										str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_',
											str_replace('[attribs]', '[layouts]['.$tmpl->name.']',
												$this->getFieldInheritedDisplay($field, $this->iparams)
												//$form_layout->getInput($fieldname, $groupname/*, $value*/)   // Value already set, no need to pass it
											)
										)
									) .
									($cssprep ? ' <span class="icon-info hasTooltip" title="' . JText::_('Used to auto-create a CSS styles file. To modify this, you can edit layout in template manager', true) . '"></span>' : '') . '
								</div>
							</div>
						';

					endforeach; ?>

					</fieldset>


				<?php endforeach; //fieldSets ?>
				<?php echo JHtml::_('bootstrap.endSlide'); ?>

			<?php endforeach; //tmpls ?>

			<?php echo JHtml::_('bootstrap.endAccordion'); //echo JHtml::_('sliders.end'); ?>

		</div><!-- END class="fc-sliders-plain-outer" -->
	<?php
	$fn = 'layout_params';
	$captured[$fn] = ob_get_clean();
	$rendered[$fn] = (object) array('label_html' => '', 'input_html' => '', 'html' => $captured[$fn], 'field' => false);
	unset($label_html); unset($input_html);
	endif;

endif; // end of template: layout_selection, layout_params




if ($typeid && $use_versioning && $this->perms['canversion'] && $versionsplacement !== 0) : ob_start()  // versions ?>
	<table class="" style="margin: 10px; width: auto;">
		<tr>
			<td>
				<h3><?php echo JText::_( 'FLEXI_VERSIONS_HISTORY' ); ?></h3>
			</td>
		</tr>
		<tr><td>
			<table id="version_tbl" class="fc-table-list fc-tbl-short">
			<?php if ($this->row->id == 0) : ?>
			<tr>
				<td class="versions-first" colspan="4"><?php echo JText::_( 'FLEXI_NEW_ARTICLE' ); ?></td>
			</tr>
			<?php
			else :
			$date_format = (($date_format = JText::_( 'FLEXI_DATE_FORMAT_FLEXI_VERSIONS_J16GE' )) == 'FLEXI_DATE_FORMAT_FLEXI_VERSIONS_J16GE') ? "d/M H:i" : $date_format;
			foreach ($this->versions as $version) :
				$isCurrent = (int) $version->nr === (int) $this->row->current_version;
				$class = $isCurrent ? ' id="active-version" class="success"' : '';
				if ((int)$version->nr > 0) :
			?>
			<tr<?php echo $class; ?>>
				<td class="versions"><span style="padding: 0 5px 0 0;"><?php echo '#' . $version->nr; ?></span></td>
				<td class="versions"><span style="padding: 0 5px 0 0;"><?php echo JHtml::_('date', (($version->nr == 1) ? $this->row->created : $version->date), $date_format ); ?></span></td>
				<td class="versions"><span style="padding: 0 5px 0 0;"><?php echo ($version->nr == 1) ? flexicontent_html::striptagsandcut($this->row->creator, 25) : flexicontent_html::striptagsandcut($version->modifier, 25); ?></span></td>
				<td class="versions">

					<a href="javascript:;" class="hasTooltip" title="<?php echo JHtml::tooltipText( JText::_( 'FLEXI_COMMENT' ), ($version->comment ? $version->comment : 'No comment written'), 0, 1); ?>"><?php echo $comment_image;?></a>

					<?php if (!$isSite) :?>

						<?php if (!$isCurrent && $allow_versioncomparing) : ?>
							<a class="modal-versions"
								href="index.php?option=com_flexicontent&amp;view=itemcompare&amp;cid=<?php echo $this->row->id; ?>&amp;version=<?php echo $version->nr; ?>&amp;tmpl=component"
								title="<?php echo JText::_( 'FLEXI_COMPARE_WITH_CURRENT_VERSION' ); ?>"
							>
								<?php echo $compare_image; ?>
							</a>
						<?php endif; ?>

						<?php if ($isCurrent) : ?>
							<a onclick="javascript:return clickRestore('<?php echo JUri::base(true); ?>/index.php?option=com_flexicontent&amp;view=item&amp;<?php echo $task_items;?>edit&amp;<?php echo ($isSite ? 'id=' : 'cid=') . $this->row->id;?>&amp;version=<?php echo $version->nr; ?>');" href="javascript:;"><?php echo JText::_( 'FLEXI_CURRENT' ); ?></a>
						<?php else : ?>
							<a onclick="javascript:return clickRestore('<?php echo JUri::base(true); ?>/index.php?option=com_flexicontent&amp;task=items.edit&amp;<?php echo ($isSite ? 'id=' : 'cid=') . $this->row->id;?>&amp;version=<?php echo $version->nr; ?>&amp;<?php echo JSession::getFormToken();?>=1');"
								href="javascript:;"
								title="<?php echo JText::sprintf( 'FLEXI_REVERT_TO_THIS_VERSION', $version->nr ); ?>"
							>
								<?php echo $revert_image; ?>
							</a>
						<?php endif; ?>

					<?php endif; ?>

				</td>
			</tr>
			<?php
				endif;
			endforeach;
			endif; ?>
			</table>
		</td></tr>
		<tr style="background:unset;"><td style="background:unset;">
			<div id="fc_pager"></div>
		</td></tr>
	</table>
	<div class="fcclear"></div>
<?php
$fn = 'versions';
$captured[$fn] = ob_get_clean();
$rendered[$fn] = (object) array('label_html' => '', 'input_html' => '', 'html' => $captured[$fn], 'field' => false);
unset($label_html); unset($input_html);
endif;




if ($permsplacement && $this->perms['canright'] ) : ob_start(); // perms ?>
	<fieldset id="flexiaccess" class="flexiaccess basicfields_set">
		<legend><?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT' ); ?></legend>
		<div id="tabacces">
			<div id="accessrules"><?php echo $this->form->getInput('rules'); ?></div>
		</div>
		<?php if ($permsplacement === 2) : ?>
		<div id="notabacces">
			<?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT_DESC' ); ?>
		</div>
		<?php endif; ?>
	</fieldset>
<?php
$fn = 'perms';
$captured[$fn] = ob_get_clean();
$rendered[$fn] = (object) array('label_html' => '', 'input_html' => '', 'html' => $captured[$fn], 'field' => false);
unset($label_html); unset($input_html);
endif;




// Fields manager tab
$captured['fman'] = array();

/**
 * Capture any fields manager contents that were missed (THIS SHOULD BE EMPTY !!)
 */
ob_start();

if ($this->fields && $typeid) :

	$this->document->addScriptDeclaration("
		jQuery(document).ready(function(){
			jQuery('#jform_type_id').change(function() {
				if (jQuery('#jform_type_id').val() != '".$typeid."')
					jQuery('#fc-change-warning').css('display', 'block');
				else
					jQuery('#fc-change-warning').css('display', 'none');
			});
		});
	");
	?>

	<div class="fc_edit_container_full">

		<?php
		$hide_ifempty_fields = array('fcloadmodule', 'fcpagenav', 'toolbar', 'comments');
		$row_k = 0;

		foreach ($this->fields as $field_name => $field) :

			$field_plain_name = $field->field_type === 'coreprops' ? str_replace('form_', '', $field_name) : $field_name;
			$customPlacement  = isset($this->placeViaLayout[$field_plain_name]);
			$hide_ifempty     = $field->iscore || $field->field_type === 'coreprops' ||	$field->formhidden == 4 || in_array($field->field_type, $hide_ifempty_fields);


			/**
			 * Skip coreprops fields not meant for item form
			 */
			if ($field->field_type === 'coreprops' && substr($field->name, 0, 5) !== 'form_')
			{
				continue;
			}


			/**
			 * Skip field via type parameter. This in only used for description field ('maintext' field type)
			 * since for other core fields we used 2 distinct parameters 1 parameter for frontend item form and 1 for backend item form
			 */
			$hide_field = (int) $this->tparams->get('hide_' . $field->field_type, 0);
			if ($hide_field === 1 || ($hide_field === 2 && $isSite) || ($hide_field === 3 && !$isSite))
			{
				continue;
			}


			/**
			 * Before we skip fields with empty HTML (below), we check if this a core field
			 * or coreprops field that will be placed via fields manager placement/ordering
			 */
			if ( !$customPlacement && ($field->iscore || $field->field_type === 'coreprops') )
			{
				if ( isset($captured[ $field_plain_name ]) )
				{
					$captured['fman'][$field_plain_name] = $captured[ $field_plain_name ];
					unset($captured[ $field_plain_name ]);
					continue;
				}
			}


			/**
			 * Skip fields with empty HTML, any needed fields with "form-captured" html
			 * that needed to be displayed via fields manager we already output them above
			 */
			if (

				// Skip hide-if-empty fields from this listing
				( empty($field->html) && $hide_ifempty ) ||

				// SKIP frontend / backend hidden fields from this listing
				( $isSite && ($field->formhidden==1 || $field->formhidden==3 || $field->parameters->get('frontend_hidden')) ) ||
				( !$isSite && ($field->formhidden==2 || $field->formhidden==3 || $field->parameters->get('backend_hidden')) )

			) continue;


			/**
			 * Output custom HTML field which neither displays a label nor any containers HTML
			 */
			if ($field->field_type === 'custom_form_html')
			{
				$rendered[$field->name] = (object) array('label_html' => '', 'input_html' => $field->html);

				if ( $customPlacement )
				{
					$captured[$field->name] = $field->html;
					$rendered[$field->name]->html = $captured[$field->name];
				}
				else
				{
					$captured['fman'][$field->name] = $field->html;
					$rendered[$field->name]->html = $captured['fman'][$field->name];
				}

				$rendered[$field->name]->field = $field;

				continue;
			}


			/**
			 * Start capturing field's html ( label + input )
			 */
			ob_start();


			if ($field->field_type === 'image')
			{
				if ($field->parameters->get('image_source')==-1)
				{
					$replace_txt = !empty($captured['jimages'])
						? $captured['jimages']
						: sprintf( $alert_box, '', 'warning', 'fc-nobgimage', JText::_('FLEXI_ENABLE_INTRO_FULL_IMAGES_IN_TYPE_CONFIGURATION') );
					unset($captured['jimages']);
					$field->html = str_replace('_INTRO_FULL_IMAGES_HTML_', $replace_txt, $field->html);
				}
			}


			if ($field->field_type === 'weblink')
			{
				if ($field->parameters->get('link_source')==-1)
				{
					$replace_txt = !empty($captured['jurls'])
						? $captured['jurls']
						: sprintf( $alert_box, '', 'warning', 'fc-nobgimage', JText::_('FLEXI_ENABLE_LINKS_IN_TYPE_CONFIGURATION') );
					unset($captured['jurls']);
					$field->html = str_replace('_JOOMLA_ARTICLE_LINKS_HTML_', $replace_txt, $field->html);
				}
			}


			// Field has tooltip
			$edithelp = $field->edithelp ? $field->edithelp : 1;
			if ( $field->description && ($edithelp==1 || $edithelp==2) )
			{
				$label_attrs = 'class="' . $tip_class . ($edithelp==2 ? ' fc_tooltip_icon' : '') . $lbl_class . $lbl_extra_class . '" title="'.flexicontent_html::getToolTip(null, $field->description, 0, 1).'"';
			}
			else
			{
				$label_attrs = 'class="' . $lbl_class . $lbl_extra_class . '"';
			}

			$row_k = 1 - $row_k;

			// Some fields may force a container width ?
			$display_label_form = (int) $field->parameters->get('display_label_form', 1);
			$full_width = $display_label_form === 0 || $display_label_form === 2 || $display_label_form === -1;

			$width = $field->parameters->get('container_width', ($full_width ? '100% !important;' : false));

			$container_width = empty($width)
				? ''
				: 'width:' . $width . ($width != (int) $width ? 'px !important;' : '');
			$container_class =
				//'fcfield_row' . $row_k .
				' container_fcfield container_fcfield_id_' . $field->id . ' container_fcfield_name_' . $field->name;
			?>

			<div class="control-group<?php echo $display_label_form === 2 ? ' fc_vertical' : ''; ?>">

				<?php ob_start(); /* label_html */ ?>
				<div
					class="control-label<?php echo $display_label_form === 2 ? ' fclabel_cleared' : ''; ?>"
					id="label_outer_fcfield_<?php echo $field->id; ?>"
					style="<?php echo $display_label_form < 1 ? 'display:none;' : '' ?>"
				>
					<label id="label_fcfield_<?php echo $field->id; ?>" data-for="<?php echo 'custom_'.$field->name;?>" <?php echo $label_attrs;?> >
						<?php echo $field->label; ?>
					</label>
				</div>
				<?php $rendered[$field->name] = (object) array('label_html' => ob_get_clean()); echo $rendered[$field->name]->label_html; ?>

				<?php if ($display_label_form === 2): ?>
					<div class="fcclear"></div>
				<?php endif; ?>

				<?php ob_start(); /*input_html */ ?>
				<div style="<?php echo $container_width . ($display_label_form !== 1 ? 'margin: 0' : ''); ?>" class="controls <?php echo $container_class; ?>" id="container_fcfield_<?php echo $field->id; ?>">
					<?php echo ($field->description && $edithelp==3)  ?  sprintf( $alert_box, '', 'info', 'fc-nobgimage', $field->description )  :  ''; 
					?>



				<?php // CASE 1: CORE 'description' FIELD with multi-tabbed editing falang
				if ($field->field_type === 'maintext' && isset($this->row->item_translations)) :

					array_push($tabSetStack, $tabSetCnt);
					$tabSetCnt = ++$tabSetMax;
					$tabCnt[$tabSetCnt] = 0;
					?>
					<!-- tabber start -->
					
					<div class="fctabber tabber-inline s-gray tabber-lang" id="fcform_tabset_<?php echo $tabSetCnt; ?>">
						<div class="tabbertab fc-tabbed-field-box" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" >
							<h3 class="tabberheading"> <?php echo '- '.$this->itemlang->name.' -'; ?> </h3>
							<?php
								$field_tab_labels = & $field->tab_labels;
								$field_html       = & $field->html;
								echo !is_array($field_html) ? $field_html : flexicontent_html::createFieldTabber( $field_html, $field_tab_labels, "");
							?>
						</div>
						<?php foreach ($this->row->item_translations as $t): ?>
							<?php if ($this->itemlang->shortcode!=$t->shortcode && $t->shortcode!='*' && !in_array($t->code, $disable_langs)) : ?>
								<div class="tabbertab fc-tabbed-field-box" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" >
									<h3 class="tabberheading"> <?php echo $t->name; // $t->shortcode; ?> </h3>
									<?php
									$field_tab_labels = & $t->fields->text->tab_labels;
									$field_html       = & $t->fields->text->html;
									echo !is_array($field_html) ? $field_html : flexicontent_html::createFieldTabber( $field_html, $field_tab_labels, "");
									?>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
					<!-- tabber end -->
					<?php $tabSetCnt = array_pop($tabSetStack); ?>

				<?php // CASE 2: NORMAL FIELD non-tabbed
				elseif ( !isset($field->html) || !is_array($field->html) ) : ?>

					<?php echo isset($field->html) ? $field->html : $noplugin; ?>

				<?php /* MULTI-TABBED FIELD e.g textarea, description */
				else :
					$not_in_tabs = '';

					array_push($tabSetStack, $tabSetCnt);
					$tabSetCnt = ++$tabSetMax;
					$tabCnt[$tabSetCnt] = 0;
					?>
					<!-- tabber start -->
					<div class="fctabber" id="fcform_tabset_<?php echo $tabSetCnt; ?>">
					<?php foreach ($field->html as $i => $fldhtml): ?>
						<?php
							// Hide field when it has no label, and skip creating tab
							$not_in_tabs .= !isset($field->tab_labels[$i]) ? "<div style='display:none!important'>".$field->html[$i]."</div>" : "";
							if (!isset($field->tab_labels[$i]))	continue;
						?>

						<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" style="padding: 0px;">
							<h3 class="tabberheading"> <?php echo $field->tab_labels[$i]; // Current TAB LABEL ?> </h3>
							<?php
								echo $not_in_tabs;      // Output hidden fields (no tab created), by placing them inside the next appearing tab
								$not_in_tabs = "";      // Clear the hidden fields variable
								echo $field->html[$i];  // Current TAB CONTENTS
							?>
						</div>

					<?php endforeach; ?>

					
					</div>
					<!-- tabber end -->
					<?php $tabSetCnt = array_pop($tabSetStack); ?>

					
					<?php echo $not_in_tabs;      // Output ENDING hidden fields, by placing them outside the tabbing area ?>

				<?php endif; /* END MULTI-TABBED FIELD */ ?>
				<?php 
				// ADD PLACEHOLDER
				if($field->description && $edithelp==4) {
							echo  '<small class="form-text">'.$field->description.'</small>';
					} ?>


				</div>
				<?php $rendered[$field->name]->input_html = ob_get_clean(); echo $rendered[$field->name]->input_html; ?>

			</div>

		<?php
			/**
			 * Check if a field will NOT be placed via fields manager placement/ordering,
			 * but instead it will be inside a custom TAB (e.g. 'text' (Description field) is placed inside the 'Description' TAB
			 *
			 * NOTE: if a field is not explicitely placed by layout, fields manager will try to place it by default
			 */
			$field_html = ob_get_clean();

			if ( $customPlacement )
			{
				$captured[$field->name] = $field_html;
				$rendered[$field->name]->html = $captured[$field->name];
			}
			else
			{
				$captured['fman'][$field->name] = $field_html;
				$rendered[$field->name]->html = $captured['fman'][$field->name];
			}

			$rendered[$field->name]->field = $field;
		?>

		<?php endforeach; ?>


		<?php
		/**
		 * Implode captured fields manager fields
		 */
		echo implode('<div class="fcclear"></div>', $captured['fman']);
		unset($captured['fman']);
		?>


	</div> <!-- fields manager container class="fc_edit_container_full" -->


<?php else : /* NO TYPE SELECTED */ ?>

		<?php if ( $typeid == 0) : // type_id is not set (user allowed to select item type) ?>
			<input name="jform[type_id_not_set]" value="1" type="hidden" />
			<?php echo sprintf( $alert_box, '', 'info', '', JText::_( 'FLEXI_CHOOSE_ITEM_TYPE' ) ); ?>
		<?php else : // existing item that has no custom fields, warn the user ?>
			<?php echo sprintf( $alert_box, '', 'info', '', JText::_( 'FLEXI_NO_FIELDS_TO_TYPE' ) ); ?>
		<?php endif; ?>

<?php endif;


/**
 * Capture the fields manager contents, including the external container
 */
$captured['fields_manager'] = ob_get_clean();


<?php
$n = 0;

foreach ($field->value as $i => $value)
{
  // Joomla tinyMCE custom config bug workaround, (form reload, data from SESSION)
  if ($mce_fieldname && isset($value[$mce_fieldname]))
  {
    $field->value[$i] = $value = $value[$mce_fieldname];
  }

  // Special case TABULAR representation of single value textarea
  if ($n==0 && $field->parameters->get('editorarea_per_tab', 0))
  {
    $this->parseTabs($field, $item);
    if ($field->tabs_detected)
    {
      $this->createTabs($field, $item, $fieldname, $elementid);
      return;
    }
  }
	$value = $value ?? '';
  if (!strlen($value) && !$use_ingroup && $n) continue;  // If at least one added, skip empty if not inside a field group

  $fieldname_n = $field->field_type == 'maintext' ? $fieldname : $fieldname.'['.$n.']';
  $elementid_n = $field->field_type == 'maintext' ? $elementid : $elementid.'_'.$n;

  // Normal textarea editting
  $field->tab_names[$n]  = $field->field_type == 'maintext' ? $fieldname : $fieldname_n;
  $field->tab_labels[$n] = $field->field_type == 'maintext' ? $field->label : $field->label." ".$n ;

  // NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
  //display($name, $html, $width, $height, $col, $row, $buttons = true, $id = null, $asset = null, $author = null, $params = array())
  $mce_fieldname_sfx = $mce_fieldname ? '[' . $mce_fieldname . ']' : '';
  $txtarea = !$use_html ? '
    <textarea ' . $extra_attribs . ' id="'.$elementid_n.'" name="'.$fieldname_n.'" ' . ($auto_value ? ' readonly="readonly" ' : '') . '>'
      . htmlspecialchars( $value, ENT_COMPAT, 'UTF-8' ) .
    '</textarea>
    ' : $editor->display(
      $fieldname_n . $mce_fieldname_sfx, htmlspecialchars( $value, ENT_COMPAT, 'UTF-8' ), $width, $height, $cols, $rows,
      $skip_buttons_arr, $elementid_n, $_asset_ = '', $_author_ = null, $editor_plg_params
    );
    // NOTE asset = ''; above is to workaround an issue in image XTD button that makes strict check $asset = $asset !== '' ? $asset : $extension;

  $txtarea = '
    <div class="fc_txtarea">
      <div class="fcfield_box' .($required ? ' required_box' : ''). '" data-label_text="'.$field->label.'">
        '.$txtarea.'
      </div>
    </div>';

  $field->html[] = '
    ' . (!$add_ctrl_btns || $auto_value ? '' : '
    <div class="'.$btn_group_class.' fc-xpended-btns">
      '.$move2.'
      '.$remove_button.'
      '.(!$add_position ? '' : $add_here).'
    </div>
    ').'
    '.($use_ingroup ? '' : '<div class="fcclear"></div>').'
    '.$txtarea.'
    ';

  $n++;
  if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}
<?php
//No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );
$fieldname = $field->name.'[]';

$values = & $this->values;

foreach ($values as $v)
{
	$field->html[] = '
		<input type="text" name="'.$fieldname.'" value="'.htmlspecialchars($v, ENT_COMPAT, 'UTF-8').'" />
	';
}
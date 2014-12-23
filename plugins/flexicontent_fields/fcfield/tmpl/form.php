<?php
//No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );
$fieldname = FLEXI_J16GE ? 'custom['.$field->name.'][]' : $field->name.'[]';
?>
<div>
	FLEXIContent Field
	<input type="hidden" name="<?php echo $fieldname;?>" value="" />
</div>
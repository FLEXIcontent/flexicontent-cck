<?php
//No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );
$fieldname = $field->name.'[]';
foreach ($this->values as $v) :?>
	<input type="text" name="<?php echo $fieldname;?>" value="<?php echo htmlspecialchars($v, ENT_COMPAT, 'UTF-8');?>" />
<?php
endforeach;
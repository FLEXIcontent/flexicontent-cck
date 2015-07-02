<?php
//No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

$values = & $this->values;

$this->field->{$prop} = null;
foreach ($values as $v)
{
	$this->field->{$prop}[] = $v;
}
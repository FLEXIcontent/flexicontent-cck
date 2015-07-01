<?php
//No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

foreach ($this->values as $v) {
	$this->field->{$prop} = $v;
}
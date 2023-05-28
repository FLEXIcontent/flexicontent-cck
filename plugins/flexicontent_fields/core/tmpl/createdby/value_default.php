<?php
// Add prefix / suffix
$field->{$prop} =
	$pretext
		. (($field->parameters->get('name_username', 1) == 2) ? $item->cuname : $item->creator) .
	$posttext;

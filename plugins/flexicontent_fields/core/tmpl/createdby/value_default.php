<?php
// The author name is text: encoded for HTML, as it is for a CSV export
$_author_name = (string) (($field->parameters->get('name_username', 1) == 2) ? $item->cuname : $item->creator);

// Add prefix / suffix
$field->{$prop} =
	$pretext
		. ($prop === 'csv_export' ? $_author_name : htmlspecialchars($_author_name, ENT_QUOTES, 'UTF-8')) .
	$posttext;

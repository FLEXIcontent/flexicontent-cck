<?php
	// Using in field group, return array
	if ( $is_ingroup )
	{
		return _FC_RETURN_;
	}

	// Check for value only displays and return
	if ( isset(self::$value_only_displays[$prop]) )
	{
		return _FC_RETURN_;
	}

	// Check for no values found
	if ( !count($field->{$prop}) )
	{
		$field->{$prop} = '';
		return _FC_RETURN_;
	}

	return 0;
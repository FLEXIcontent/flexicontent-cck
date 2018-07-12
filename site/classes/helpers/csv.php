<?php
defined( '_JEXEC' ) or die( 'Restricted access' );




class flexicontent_csv_data
{
	var $csv_string;
	
	function __construct()
	{
	}
	
	function getText()
	{
		return $this->csv_string;
	}
}



class flexicontent_csv
{
	function __construct()
	{
	}

	function parseFile($csvfile)
	{
		$csv_data = new flexicontent_csv_data();
		$csv_data->csv_string = file_get_contents($csvfile);

		return $csv_data;
	}	
}

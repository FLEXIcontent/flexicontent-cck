<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

class date_time
{
	/**
	 * Shifts from and to dates by $shift_amount of $shift_units.
	 * are the unit names.
	 * Modified in my version: I am more familiar with the standard
	 * units used in the date function provided by PHP so I changed
	 * the units to them. Also the default in mine is to add dates and
	 * time radther than subtracting. Cleaned up a bit of the code and
	 * added checking if a datetime variable is set and if not it will
	 * use the todays but you are able to input your own to modify.
	 *
	 *    shift_units:
	 *        months = m;
	 *        days = d;
	 *        years = Y;
	 *        hours = H;
	 *        minutes = i;
	 *        seconds = s;
	 *
	 * @author    Albert Lash
	 * @modifed   Richard Sumilang
	 * @param    string    $datetime    TimeStamp of the time you woud like to edit "YYYY-MM-DD HH:MM:SS".
	 * @param    integer    $shit_amount The amount you want to shit
	 * @param    string    $shift_unit    Unit you would like to shift
	 *
	 * @return timestamp
	 */
	static function shift_dates($datetime = '', $shift_amount, $shift_unit)
	{
		// Check that $datetime was given
		if (!$datetime)
		{
			$datetime = date("Y-m-d H:i:s");
		}

		/* Split into separate sections: date and time */
		list($date, $time)=preg_split("/ /", $datetime);

		/* Break down the date */
		list($year, $month, $day)=preg_split("/-/", $date, 3);

		/* Break down the time */
		list($hour, $min, $sec)=preg_split("/:/", $time, 3);

		/* This is the date shifting area */
		if ($shift_unit=="m")
		{
			$newdate = mktime ($hour, $min, $sec, (int)$month + (int)$shift_amount, $day, $year);
			$newdate = date("Y-m-d H:i:s",  $newdate);
		}
		elseif ( $shift_unit=="d")
		{
			$newdate = mktime ($hour, $min, $sec, $month, (int)$day + (int)$shift_amount, $year);
			$newdate = date("Y-m-d H:i:s",  $newdate);
		}
		elseif ($shift_unit=="Y")
		{
			$newdate = mktime ($hour, $min, $sec, $month, $day, (int)$year + (int)$shift_amount);
			$newdate = date("Y-m-d H:i:s",  $newdate);
		}
		elseif ($shift_unit=="H")
		{
			$newdate = mktime ($hour+$shift_amount, $min, $sec, $month, $day, $year);
			$newdate = date("Y-m-d H:i:s",  $newdate);
		}
		elseif ($shift_unit=="i")
		{
			$newdate = mktime ($hour, $min+$shift_amount, $sec, $month, $day, $year);
			$newdate = date("Y-m-d H:i:s",  $newdate);
		}
		elseif ($shift_unit=="s")
		{
			$newdate = mktime ($hour, $min, $sec+$shift_amount, $month, $day, $year);
			$newdate = date("Y-m-d H:i:s", $newdate);
		}
		else
		{
			echo '<span class="alert alert-warning fcpadded">Unknown shift unit character(s) in configuration: ' . $shift_unit . ' <br/><br/>Supported: Y (year), m (month),  d (day), H (hour), i (minutes), s (seconds) </span>';
			$newdate = date("Y-m-d H:i:s");
		}

		return $newdate;
	}



	/**
	 * This converts MM-DD-YYYY to YYYY-MM-DD
	 *
	 * @author Richard Sumilang
	 * @param  date  $date  MM-DD-YYYY
	 * @return string
	 */
	static function machine_date($date)
	{
		list($month, $date, $year)=preg_split("/-/", $date);

		return $year . "-" . $month . "-" . $date;
	}



	/**
	 * This converts YYYY-MM-DD to MM-DD-YYYY
	 *
	 * @author Richard Sumilang
	 * @param  date  $date  YYYY-MM-DD
	 *
	 * @return string
	 */
	static function human_date($date)
	{
		list($year, $month, $date)=preg_split("/-/", $date);

		return $month . "-" . $date . "-" . $year;
	}



	/**
	 * This converts stuff like 3:00am to 03:00 and 4:00pm to like 16:00
	 *
	 * @author Richard Sumilang
	 * @param  string  $time   Time in the form of 1:00 or whatever
	 * @param  string  $ampm   Values are am or pm
	 * @return string
	 */
	static function convert_to_24_hr($time, $ampm)
	{
		// Make sure it's lower case
		$ampm=strtolower($ampm);

		// Split the time
		list($hour, $min)=preg_split("/:/", $time);

		switch($ampm)
		{
		case "am":
			$hour=$this->fill_time($hour);
			break;

		case "pm":
			$hour=$hour + 12;
			break;
		}

		$min = $this->fill_time($min);

		return $hour . ":" . $min;
	}



	/**
	 * This converts stuff like 16:00 to 04:00pm
	 *
	 * @author Richard Sumilang
	 * @param  string  $time   Time in the form of HH:MI
	 * @return array
	 */
	static function convert_from_24_hr($time)
	{
		// Split up the time
		list($hour, $min)=preg_split("/:/", $time);
		
		// AM / PM
		$ampm = $hour > 12 ? 'pm' : 'am';
		
		// Adjust hour to 0-12
		$hour = $hour > 12 ? $hour - 12 : $hour;

		// Set hour to 12 instead of 00
		$hour = $hour == '00' ? '12' : $hour;

		$result=array(
			'time' => $hour . ':' . $min,
			'ampm' => $ampm
		);

		return $result;
	}



	/**
	 * Add a 0 in front of times with 1 character
	 *
	 * @author Richard Sumilang
	 * @param  string  $string
	 * @return string
	 */
	static function fill_time($string)
	{
		if (strlen($string)==1)
		{
			$string = '0' . $string;
		}

		return $string;
	}

}
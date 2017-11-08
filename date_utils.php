<?php
/* Useful date/time handling functions */

/**
 * Convert an interval string to days, rounded to nearest day
 *
 * @param string $date
 * @return float integer value of days
 */
function intervalStringToDays($interval) {
	$sec = intervalStringToSec ( $interval );
	return secToDays ( $sec );
}
/**
 * Convert an interval string to seconds
 *
 * @param string $interval
 */
function intervalStringToSec($interval) {
	return strtotime ( $interval, 0 );
	// return date_create ( "@0" )->add ( DateInterval::createFromDateString ( $interval ) )->getTimestamp ();
}
/**
 * Convert a number of seconds to days, rounded to nearest day
 *
 * @param int $time
 * @return float integer value of days
 */
function secToDays($sec) {
	return floor ( $sec / 60 / 60 / 24 );
}
/**
 * Returns number of whole days until given date, from 00:00 on $fromDate (Default now) until 00:00 on $toDate.
 * Note that the args are probably the opposite way around to what you would expect, so that the 2nd (from) date can be optional.
 * Input dates should be in any format recognised by the strtotime() PHP function.
 *
 * @param string $toDate
 * @param string $fromDate
 * @return number
 */
function daysUntilDate($toDate, $fromDate = NULL) {
	// Round dates to 00:00 on current day to perform day calculations
	$fromDate = $fromDate ? date ( "Y-m-d", strtotime ( $fromDate ) ) : date ( "Y-m-d" );
	$toDate = date ( "Y-m-d", strtotime ( $toDate ) );
	return secToDays ( strtotime ( $toDate ) ) - secToDays ( strtotime ( $fromDate ) );
}
/**
 * Postgres returns interval data types with time as hh:mm:ss, even when it was input like "5 hours 6 min".
 * This returned format gets misinterpreted by strtotime() as having 0 year,
 * so this function will find a hh:mm:ss pattern in a string and convert it to " 5 hours 6 minutes 0 seconds"
 * The leading date part, and any trailing characters are left unchanged.
 * If the argument value does not match the hh:mm:ss format then it is returned unchanged.
 *
 * @param string $interval
 * @return string
 */
function convertTimeInterval($interval) {
	// db returns hours intervals in h:m:s format which gets misinterpreted as 0 year by strtotime() so convert
	$matches = array ();
	if (preg_match ( "/^(.*)(\d+):(\d+):(\d+)(.*)$/", $interval, $matches )) {
		$interval = $matches [1] . $matches [2] . "hours " . $matches [3] . " minutes " . $matches [4] . " seconds " . $matches [5];
	}
	return $interval;
}
/**
 * Wrapper for convertTimeInterval($interval) but updates interval variable by reference.
 *
 * Postgres returns interval data types with time as hh:mm:ss, even when it was input like "5 hours 6 min".
 * This returned format gets misinterpreted by strtotime() as having 0 year,
 * so this function will find a hh:mm:ss pattern in a string and convert it to " 5 hours 6 minutes 0 seconds"
 * The leading date part, and any trailing characters are left unchanged.
 * If the argument value does not match the hh:mm:ss format then it is returned unchanged.
 * 
 * @param string $interval
 * @return string
 */
function convertTimeInterval_(&$interval) {
	$interval = convertTimeInterval ( $interval );
	return $interval;
}
/**
 * Return the number of non-business days (by default Sat and Sun) in the range.
 * If the first or last days are business days then they will be counted as whole days.
 * fromDate defaults to 12:00 today.
 *
 * @param string $toDate
 * @param string $fromDate
 * @param array $nonBusinessWeekDays
 */
function nonBusinessDays($toDate, $fromDate = NULL, $nonBusinessWeekDays = array(6,0), $nonBusinessDates = array()) {
	/*
	 * Rather than loop through every day in the range, just find out how many weeks are in the range
	 * multiply that by the number of $nonBusinessWeekDays, then check the remaining days.
	 * Hopefully that's more efficient.
	 * Note that the args are probably the opposite way around to what you would expect, so that the 2nd (from) date can be optional.
	 * Input dates should be in any format recognised by the strtotime() PHP function.
	 * 2nd arg $nonBusinessWeekDays is an array of numerical weekdays, where 0=Sunday, 6=Saturday
	 */
	$nonBusinessWeekDays = array_unique ( $nonBusinessWeekDays );
	$rangeDays = daysUntilDate ( $toDate, $fromDate );
	$weeksUntilDate = floor ( $rangeDays / 7 );
	$nonBusDays = count ( $nonBusinessWeekDays ) * $weeksUntilDate;
	$fromDate = $fromDate ?: date ( "Y-m-d 12:00:00" );
	$from = strtotime ( $fromDate );
	$to = strtotime ( $toDate . " +1day" );
	$daysMod = ($weeksUntilDate * 7) + 1;
	$remainderFrom = strtotime ( $fromDate . " +{$daysMod}days" );
	
	$failsafeCount = 0;
	while ( $remainderFrom < $to ) {
		$dayNum = date ( 'w', $remainderFrom );
		if (in_array ( $dayNum, $nonBusinessWeekDays ))
			$nonBusDays ++;
		/*
		 * Add a day - doesn't allow for daylight savings,
		 * but remainder range is < 7 days so 1 hour shouldn't matter, unless time is within 1 hr of midnight
		 */
		$remainderFrom += 86400;
		// Infinite loop failsafe in case loop runs more than 7 times, shouldn't happen unless there's a problem
		if (++ $failsafeCount > 7)
			break;
	}
	
	foreach ( $nonBusinessDates as $nbDate ) {
		$nbDateSec = strtotime ( $nbDate );
		// see if the date falls within the from-to range and isn't already accounted for in the $nonBusinessWeekDays
		if ($nbDateSec > $from && $nbDateSec < $to && ! in_array ( date ( 'w', $nbDateSec ), $nonBusinessWeekDays ))
			$nonBusDays ++;
	}
	
	return $nonBusDays;
}

<?php

	/*
		Author: Justin Musgrove
		Description:  Help to automate data gathering for weather processs
		Version: 1.0
	*/
?>


<h2>The weather data aggregator</h2>

<?php
	function getDataElements () {
	 $BASE_URL = "https://query.yahooapis.com/v1/public/yql";
	 $yql_query = "select * from html where url in ('http://www.uswx.com/uswx/text.php?q=kmke&h=168&stn=Kmke', " .
	 	"'http://www.uswx.com/uswx/text.php?q=kgrb&h=168&stn=Kgrb', 'http://www.uswx.com/uswx/text.php?q=kauw&h=168&stn=Kauw', " .
	 	"'http://www.uswx.com/uswx/text.php?q=keau&h=168&stn=Keau', 'http://www.uswx.com/uswx/text.php?q=krhi&h=168&stn=Krhi',  " .
	 	"'http://www.uswx.com/uswx/text.php?q=krpd&h=168&stn=Krpd', 'http://www.uswx.com/uswx/text.php?q=KMSN&h=168&stn=KMSN') " .
	 	" and xpath='//pre[@class=\'obs_coded\']'";

	 $yql_query_url = $BASE_URL . "?q=" . urlencode($yql_query) . "&format=xml";
	 $session = curl_init($yql_query_url);
	 curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
	 curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false); 
	 $response =  curl_exec($session);
	 $s = simplexml_load_string($response);
	 unset($_GET); // clean up

	 if(!is_null($s)) { 
	 	  
		 $allElements = array();

		 foreach($s->xpath('//pre') as $v) {
			
			// create array based on =
			$pieces = explode("=", $v);

			// scrub each line, add it to array of elements
			foreach($pieces as $row) {
				array_push($allElements, preg_replace("/[ \t\n]+/", " ", $row));
			}
		 }
		 return $allElements;
	 }
	}

	function getRightTime ($arrayPassed) {
		// since the time is not always in a specificed position, we need to search the array for string with a Z and length of 
		for ($i = 1; $i <= count(arrayPassed); $i++) {
			print_r(strlen($arrayPassed[i]));
		   if (strlen($arrayPassed[i]) == 6) {
		   	if (strpos($arrayPassed[i], 'Z')) {
		   		return $arrayPassed[i];
		   	}
		   }
		}
	}

	function determineWindResults ($allElements) {
  	
  		$timedWindResults = array();
		foreach($allElements as $row) {

			if (strlen(trim($row)) > 0) {
				// each row is string
				$rowArray = explode (" ", $row, 8);
				
				//print_r(array ($rowArray[1], $rowArray[2], $rowArray[3], $rowArray[4], $rowArray[5], $rowArray[6]));

				$recordDate = date_create_from_format("Y-m-d", $rowArray[0]); // date in which the event was recorded 2012-01-19
				$recordDateArray = getdate(time($recordDate));

				$theRightTime = $rowArray[5];

				$eventDate = date_create_from_format("dHiZY", $theRightTime . $recordDateArray['year'], timezone_open('UTC')); //19 17 52Z
				$eventDateArray = getdate(time($eventDate));

				$eventHour = date_format($eventDate, 'H'); // get the hour to compare

				// just compare using 0900Z (3am), 1500Z(9am), 2100Z(3pm), 0300Z(9pm).
				$hourArray = array('02', '03', '08', '09', '14', '15', '20', '21'); 

				// write file if it exists
				if (in_array($eventHour, $hourArray) == 1) {
					// parse the wind results
					$toFileString = implode (",", array ($rowArray[1], $rowArray[2], $rowArray[3], $rowArray[4], $rowArray[5], $rowArray[6]));
					array_push($timedWindResults, $toFileString);
				}
				
			}
		}
		return $timedWindResults;
	}


	/*
		A snow event is if a row contains a SNB or SNE

		Metod returns array of snow events
	*/
	function determineSnowResults ($allElements) {

		//$allElements = array('2012-01-17 17:33 METAR KMKE 172333Z 31012G20KT 5SM -SN BKN026 BKN033 OVC044 M05/M09 A3002 RMK AO2 SNB27 P0000 =');

		$snowEvent = array ('SNB', 'SNE');
		$snowResults = array();
		foreach($allElements as $row) {
			if (strlen(trim($row)) > 0) {
				foreach ($snowEvent as $element) {
					$posfound = strpos ($row , $element);
					if ($posfound > 0) {
						//print_r($row);
						$toArray = explode (" ", $row);
						$prepareForFile = implode (",", $toArray);
						array_push($snowResults, $prepareForFile);
					}
				}
			}
		}
		return $snowResults;
	}


	function writeToFile ($toWrite, $typeOfFile) { 

		$fh = fopen($typeOfFile . date('Y-m-d h.i.s') . '.txt', 'c+') or die("can't open file");
		$return = "\r\n";
		foreach ($toWrite as $element) {
			//replace any spaces 
			fwrite($fh, $element);
			fwrite($fh, $return);
		}
		fclose($fh);
	}

    $lineBreak = "<br />";
	echo ("starting pull process.... " . $lineBreak);
	$allElements = getDataElements ();
	echo ("pull process complete.  processing wind times.... " . count($allElements)) . $lineBreak;
	$timedWindResults = determineWindResults ($allElements) ;
	echo ("wind times process complete... writing to file..." . $lineBreak);
	writeToFile ($timedWindResults, "wind");
	echo ("process snow results..." . $lineBreak);
	$snowResults = determineSnowResults ($allElements);
	echo ("snow process complete ... writing to file..." . $lineBreak);
	writeToFile ($snowResults, "snow");
	echo ("process complete..." . $lineBreak);
?>


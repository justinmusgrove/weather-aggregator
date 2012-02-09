<?php

	/*
		Author: Justin Musgrove
		Description:  This class is used to pull forcast data, right now it writes to a file
		Version: 1.0
	*/
?>

<h2>Forcast data</h2>


<form name='forecast_form'>
<table>
	<tr>
		<td>Start Date:</td>
		<td><input name='startDate' id='startDate' type='text' size='20'/></td>
	</tr>
	<tr>
		<td>End Date:</td>
		<td><input name='endDate' id='endDate' type='text' size='20'/></td>
	</tr>
</table>
<p><button id='get_results'>Run</button></p>

<p>Dates <b>must</b> be in YYYY/mm/dd (ie. 2012/02/01)</p>
</form>

<script>
  // Attach event handler to button
  document.getElementById("get_results").addEventListener("click",find_forecast,false);
  // Get user input and submit form
  function find_forecast(){
    document.forecast_form.submit();
  } 
</script>


<?php


	// this function makes a request to login, it shoud return the cookies used for other requests
	function login ($username, $password) {

		$fields = array(
            'M_runmode'=>urlencode('login_do'),
            'M_persist'=>urlencode('destination,from'),
            'destination'=>urlencode('/private/mxwx/'),
            'from'=>urlencode(''),
            'username'=>urlencode($username),
            'password'=>urlencode($password)
        );

		$fields_string = http_build_query($fields);

		$ch = curl_init("http://wisdot.meridian-enviro.com/login/");

		curl_setopt ($ch, CURLOPT_POST, true); 
		curl_setopt ($ch, CURLOPT_POSTFIELDS, $fields_string); // post fields as body
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // since there is a redirect, don't follow it
		curl_setopt($ch, CURLOPT_AUTOREFERER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true); // turn on header information

 		$response = curl_exec($ch); 
 		
		$curlHeaderSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$responseData = mb_substr($response, $curlHeaderSize);
		$responseHeader = mb_substr($response, 0, $curlHeaderSize);

		preg_match_all('|Set-cookie: (.*);|U', $responseHeader, $content);   
		$responseCookie = implode(';', $content[1]);

		curl_close($ch);
		return $responseCookie;

	}

	/*
		Make request to get the data to process for multiple counties
	*/
	function getStringToProcess ($responseCookie, $startDate, $endDate) {
		
		$ch = curl_init("http://wisdot.meridian-enviro.com/login/");
		curl_setopt($ch, CURLOPT_COOKIE, $responseCookie);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	 	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

		curl_setopt($ch, CURLOPT_URL, "http://wisdot.meridian-enviro.com/private/mxwx/rw/pre/browsearchive/browsearchive.pl?" . 
		"startdate=" . urlencode($startDate) .
		"&" .
		"enddate=" . urlencode($endDate) .
		"&selectedlocations=+.+.+.+.+.+.+.+.+.+.+.+.+.+.+%2CBarron+Co.+%280003%29%2CBrown+Co.+%280005%29%2CDane+Co.+%280013%29%2CEau+Claire+Co.+%280018%29%2CLa+Crosse+Co.+%280032%29%2CMarathon+Co.+%280037%29%2CMilwaukee+Co.+%280041%29%2COneida+Co.+%280044%29&locationtypes=counties&timezoneoffset=360");

		$response = curl_exec($ch); 

		return $response;
	}

	function startsWith($haystack, $needle) {
		$length = strlen($needle);
		return (substr($haystack, 0, $length) === $needle);
	}

	function startsWithDate ($line) {
		//does line start with date
		preg_match ("/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/", substr($line, 0, 10), $matches); // check to see if it is a date
		if (count($matches) > 0) {
			return true;
		} else {
			return false;
		}
	}


	// common function that to convert dates
	// function getStringAsDate ($stringTodDate)
	// array of strings 
	// [
	// 	{"county", "publish date", "", "", }
	// ]
	// function should process string line by line
	// it will check each line to determine what type of lne it is, something we want to process or information such as 'County' or published
	// based on a set of rules 
	// 1) based on certain published times
	// 2) 
	function processResults ($resultsToProcess) {
		
		$lineToArray = preg_split ('/$\R?^/m', $resultsToProcess);

		$publishHour_00 = array('03', '09');
		$publishHour_12 = array('15', '21');

		$allResults = array();
		foreach ($lineToArray as $line_num => $line) {

			if (strlen($line) > 0) {
				// echo ($line . "<br>");

				// if it starts with County
				if (startsWith($line, 'County:')) {
					$county = trim(substr($line, strlen('County:')));
				} else {

					// set publish date to determine logic foe each line item
					if (startsWith($line, 'Published At:')) {
						$publishedDateAsString = trim(substr($line, strlen('Published At:')));
						$publishedDate = date_create_from_format("Y-m-d H:i:s e", $publishedDateAsString); //2012-02-04 12:01:21 CST
						$publishedHour = date_format($publishedDate, 'H'); 

					} else {

						// process lines that start with date 2012-02-04 00:00:00 CST
						if (startsWithDate($line)) {
							$startWithDate = date_create_from_format("Y-m-d H:i:s e", trim(substr($line, 0 , 23))); //2012-02-04 12:01:21 CST
							$startWithHour = date_format($startWithDate, 'H'); 

							//TODO: need to refactor @ some point
							switch ($publishedHour) {

								case 0:
									if (in_array($startWithHour, $publishHour_00) == 1) { 
										// replace all spaces with one space
										$output = preg_replace('!\s+!', ' ', $line);
										// explode to array
										$splitToArray = explode (" ", $output);
										// implode to string adding extra elements
										$toPush = array($county, $publishedDateAsString, implode (",", $splitToArray));
										array_push ($allResults, implode (",", $toPush));
									}
								break;

								case 12:
									if (in_array($startWithHour, $publishHour_12) == 1) { 
										// replace all spaces with one space
										$output = preg_replace('!\s+!', ' ', $line);
										// explode to array
										$splitToArray = explode (" ", $output);
										// implode to string adding extra elements
										$toPush = array($county, $publishedDateAsString, implode (",", $splitToArray));
										array_push ($allResults, implode (",", $toPush));
									}
								break;
							}
						}
					}
				}
			}
		}
		return $allResults;
	}

	function writeToFile ($toWrite, $nameOfFile) { 

		$fh = fopen($nameOfFile . date('Y-m-d h.i.s') . '.txt', 'c+') or die("can't open file");
		$return = "\r\n";
		foreach ($toWrite as $element) {
			//replace any spaces 
			fwrite($fh, $element);
			fwrite($fh, $return);
		}
		fclose($fh);
	}


	if(isset($_GET['startDate']) && isset($_GET['endDate'])) {
		$lineBreak = "<br />";
		echo ("starting pull process.... " . $lineBreak);
		$ini_array = parse_ini_file("weather.properties");
		
		echo ("reading properties file complete... login started..." . $lineBreak);
		$currentCookie = login($ini_array['username'], $ini_array['password']);
		
		echo ("login complete... process string started..." . $lineBreak);
		$resultsToProcess = getStringToProcess ($currentCookie, $_GET['startDate'], $_GET['endDate']);
		
		// $resultsToProcess = file_get_contents('f_response_example.txt');
		echo ("massaging results, writing to file..." . $lineBreak);
		$forcastToWrite = processResults($resultsToProcess);
		writeToFile($forcastToWrite, "future_forecast");

		echo ("process complete..." . $lineBreak);
	
	}

?>
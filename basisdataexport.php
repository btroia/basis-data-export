<?php
/**
 *
 * Basis Data Export
 *
 * Utility that exports and saves your Basis device's uploaded sensor device data.
 * You can learn more about Basis at http://www.mybasis.com/
 *
 * @author  Bob Troia btroia@gmail.com
 * http://www.quantifiedbob.com
 *
*/

////////////////////////////////////
// Settings
////////////////////////////////////

// Specify your Basis user id
$basis_userid = '[ ADD YOUR BASIS USER ID HERE ]';
$access_token = '[ ADD YOUR BASIS ACCESS_TOKEN HERE]';

// Debug flag
$debug = false;

// These settings should be left as-is
$import_offset = 0; // start time offset (in seconds)
$import_offset = 0; // end time offset (in seconds)
$import_interval = 60; // data granularity (60 = 1 reading per minute)

////////////////////////////////////

// Check to see that we have openssl and https wrappers enabled
if ($debug) {
	$w = stream_get_wrappers();
	echo '<pre>';
	echo 'openssl: ',  extension_loaded  ('openssl') ? 'yes':'no', "\n";
	echo 'http wrapper: ', in_array('http', $w) ? 'yes':'no', "\n";
	echo 'https wrapper: ', in_array('https', $w) ? 'yes':'no', "\n";
	echo '</pre>';
	echo 'wrappers: ', var_dump($w);
	exit();
}

// Check for YYYY-MM-DD date in $_GET request, else throw error
if (!isset($_GET['date'])) {
	// default to yesterday
	$import_date = date('Y-m-d', strtotime('-1 day', time()));
} else {
	$import_date = preg_replace('/[^-a-zA-Z0-9_]/', '', $_GET['date']);
}
if (!contains_date($import_date)) {
	echo 'Invalid date!: ' . $import_date;
	exit();
}

// Request data from Basis for selected date. Note we're requesting all available data.
$dataurl = 'https://app.mybasis.com/api/v1/chart/' . $basis_userid . '.json?'
         . 'summary=true'
         . '&interval=' . $import_interval
         . '&units=s'
         . '&start_date=' . $import_date
         . '&start_offset=' . $import_offset
         . '&end_offset=' . $import_offset
         . '&heartrate=true'
         . '&steps=true'
         . '&calories=true'
         . '&gsr=true'
         . '&skin_temp=true'
         . '&air_temp=true'
         . '&bodystates=true';
         
 $opts = array(
	'http'=>array(
		'method'=>"GET",
		'header'=>"Accept-Language: en\r\n" .
					"Cookie: access_token=" . $access_token . "\r\n"
		)
	);

$context = stream_context_create($opts);

if(!$basisdata = file_get_contents($dataurl,false,$context)) {
	if ($debug) {
	    echo 'Error retrieving data: ' . $dataurl;
    }
} else {
	if ($debug) {
		echo 'Data retreived for ' . $import_date;
	}
	// Save JSON respose to local file
	$file = dirname(__FILE__) . '/data/basis-data-' . $import_date . '.json';
	if (!file_put_contents($file, $basisdata)) {
		if ($debug) {
			echo 'Error saving data to file ' . $file;
		}
	}
}

// Parse data from JSON response
$result = json_decode($basisdata, true);

$report_date = $result['starttime']; // report date, as UNIX timestamp
$heartrates = $result['metrics']['heartrate']['values'];
$steps = $result['metrics']['steps']['values'];
$calories = $result['metrics']['calories']['values'];
$gsrs = $result['metrics']['gsr']['values'];
$skintemps = $result['metrics']['skin_temp']['values'];
$airtemps = $result['metrics']['air_temp']['values'];
$bodystates = $result['bodystates'];


// Begin browser output
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
header("Cache-Control: no-store, no-cache, must-revalidate");  // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache"); // HTTP/1.0
?>

<html>
<head><title>My Data</title>
<style>
body {
font-family: "Lucida Sans Unicode", "Lucida Grande", Sans-Serif;
font-size: 12px;
}
#my-data, #my-data-summary {
font-family: "Lucida Sans Unicode", "Lucida Grande", Sans-Serif;
font-size: 12px;
background: #fff;
width: 800px;
border-collapse: collapse;
text-align: left;
margin: 20px;
}
#my-data th, #my-data-summary th {
font-size: 14px;
font-weight: normal;
color: #039;
border-bottom: 2px solid #6678b1;
padding: 10px 8px;
text-align: center;
}
#my-data td, #my-data-summary td {
border-bottom: 1px solid #ccc;
color: #669;
padding: 6px 8px;
text-align: center;
}
</style>
</head>
<body>

<h3>Summary</h3>
<table id="my-data-summary">
	<thead>
		<tr>
			<th scope="col" id="info">&nbsp;</th>
			<th scope="col" id="min">Min</th>
			<th scope="col" id="max">Max</th>
			<th scope="col" id="sum">Sum</th>
			<th scope="col" id="avg">Avg</th>
			<th scope="col" id="stdev">St Dev</th>
			<th scope="col" id="maxpmin">Max Per Minute</th>
			<th scope="col" id="minpmin">Min Per Minute</th>
		</tr>
	</thead>
	<tbody>
<?php
// Heart rate data summary
echo '<tr><td><strong>Heart Rate</strong><br />';
echo '<td>' . $result['metrics']['heartrate']['min'] . '</td>';
echo '<td>' . $result['metrics']['heartrate']['max'] . '</td>';
echo '<td>' . $result['metrics']['heartrate']['sum'] . '</td>';
echo '<td>' . $result['metrics']['heartrate']['avg'] . '</td>';
echo '<td>' . $result['metrics']['heartrate']['stdev'] . '</td>';
echo '<td>' . $result['metrics']['heartrate']['summary']['max_heartrate_per_minute'] . '</td>';
echo '<td>' . $result['metrics']['heartrate']['summary']['min_heartrate_per_minute'] . '</td>';
echo '</tr>';

// Steps data summary
echo '<tr><td><strong>Steps</strong><br />';
echo '<td>' . $result['metrics']['steps']['min'] . '</td>';
echo '<td>' . $result['metrics']['steps']['max'] . '</td>';
echo '<td>' . $result['metrics']['steps']['sum'] . '</td>';
echo '<td>' . $result['metrics']['steps']['avg'] . '</td>';
echo '<td>' . $result['metrics']['steps']['stdev'] . '</td>';
echo '<td>' . $result['metrics']['steps']['summary']['max_steps_per_minute'] . '</td>';
echo '<td>' . $result['metrics']['steps']['summary']['min_steps_per_minute'] . '</td>';
echo '</tr>';

// Calories data summary
echo '<tr><td><strong>Calories</strong><br />';
echo '<td>' . $result['metrics']['calories']['min'] . '</td>';
echo '<td>' . $result['metrics']['calories']['max'] . '</td>';
echo '<td>' . $result['metrics']['calories']['sum'] . '</td>';
echo '<td>' . $result['metrics']['calories']['avg'] . '</td>';
echo '<td>' . $result['metrics']['calories']['stdev'] . '</td>';
echo '<td>' . $result['metrics']['calories']['summary']['max_calories_per_minute'] . '</td>';
echo '<td>' . $result['metrics']['calories']['summary']['min_calories_per_minute'] . '</td>';
echo '</tr>';

// GSR data summary
echo '<tr><td><strong>GSR</strong><br />';
echo '<td>' . $result['metrics']['gsr']['min'] . '</td>';
echo '<td>' . $result['metrics']['gsr']['max'] . '</td>';
echo '<td>' . $result['metrics']['gsr']['sum'] . '</td>';
echo '<td>' . $result['metrics']['gsr']['avg'] . '</td>';
echo '<td>' . $result['metrics']['gsr']['stdev'] . '</td>';
echo '<td>' . $result['metrics']['gsr']['summary']['max_gsr_per_minute'] . '</td>';
echo '<td>' . $result['metrics']['gsr']['summary']['min_gsr_per_minute'] . '</td>';
echo '</tr>';

// Skin temp data summary
echo '<tr><td><strong>Skin Temp</strong><br />';
echo '<td>' . $result['metrics']['skin_temp']['min'] . '</td>';
echo '<td>' . $result['metrics']['skin_temp']['max'] . '</td>';
echo '<td>' . $result['metrics']['skin_temp']['sum'] . '</td>';
echo '<td>' . $result['metrics']['skin_temp']['avg'] . '</td>';
echo '<td>' . $result['metrics']['skin_temp']['stdev'] . '</td>';
echo '<td>' . $result['metrics']['skin_temp']['summary']['max_skin_temp_per_minute'] . '</td>';
echo '<td>' . $result['metrics']['skin_temp']['summary']['min_skin_temp_per_minute'] . '</td>';
echo '</tr>';

// Air temp data summary
echo '<tr><td><strong>Air Temp</strong><br />';
echo '<td>' . $result['metrics']['air_temp']['min'] . '</td>';
echo '<td>' . $result['metrics']['air_temp']['max'] . '</td>';
echo '<td>' . $result['metrics']['air_temp']['sum'] . '</td>';
echo '<td>' . $result['metrics']['air_temp']['avg'] . '</td>';
echo '<td>' . $result['metrics']['air_temp']['stdev'] . '</td>';
echo '<td>' . $result['metrics']['air_temp']['summary']['max_air_temp_per_minute'] . '</td>';
echo '<td>' . $result['metrics']['air_temp']['summary']['min_air_temp_per_minute'] . '</td>';
echo '</tr>';

echo '</tbody></table><hr />';
?>

<h3>Body States</h3>
<table id="my-data-summary">
	<thead>
		<tr>
			<th scope="col" id="state-start">Start</th>
			<th scope="col" id="state-end">End</th>
			<th scope="col" id="state">State</th>
		</tr>
	</thead>
	<tbody>
<?php
	for ($i=0; $i< count($bodystates); $i++) {
		echo '<tr><td>' . strftime("%Y-%m-%d %T", $bodystates[$i][0]) . '</td>';
		echo '<td>' . strftime("%Y-%m-%d %T", $bodystates[$i][1]) . '</td>';
		echo '<td>' . $bodystates[$i][2] . '</td></tr>';
	}
?>
	<tbody>
</table><hr />

<h3>My Data</h3>
<table id="my-data">
	<thead>
		<tr>
			<th scope="col" id="reading">Reading</th>
			<th scope="col" id="heartrate">Heartrate</th>
			<th scope="col" id="steps">Steps</th>
			<th scope="col" id="calories">Calories</th>
			<th scope="col" id="gsr">GSR</th>
			<th scope="col" id="skintemp">Skin Temp</th>
			<th scope="col" id="airtemp">Air Temp</th>

		</tr>
	</thead>
	<tbody>
<?php
// Format and echo data to browser
for ($i=0; $i<count($heartrates); $i++) {
	// HH:MM:SS timestamp
	$timestamp = strftime("%Y-%m-%d %T", mktime(0, 0, $i*$import_interval, date("n", $report_date), date("j", $report_date), date("Y", $report_date)));

	echo '<tr>';
	echo '<td>' . $timestamp . '</td>';
	echo '<td>' . ($heartrates[$i] == '' ? 'null' : $heartrates[$i]) . '</td>';
	echo '<td>' . ($steps[$i] == '' ? 'null' : $steps[$i]) . '</td>';
	echo '<td>' . ($calories[$i] == '' ? 'null' : $calories[$i]) . '</td>';
	echo '<td>' . ($gsrs[$i] == '' ? 'null' : $gsrs[$i]) . '</td>';
	echo '<td>' . ($skintemps[$i] == '' ? 'null' : $skintemps[$i]) . '</td>';
	echo '<td>' . ($airtemps[$i] == '' ? 'null' : $airtemps[$i]) . '</td>';
	echo '</tr>';
}
?>
	</tbody>
</table>

<?php
function contains_date($str)
{
    if (preg_match('/\b(\d{4})-(\d{2})-(\d{2})\b/', $str, $matches))
    {
        if (checkdate($matches[2], $matches[3], $matches[1]))
        {
            return true;
        }
    }
    return false;
}

?>

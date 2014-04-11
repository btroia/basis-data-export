<?php
/**
 *
 * Basis Data Export
 *
 * Utility that exports and saves your Basis device's uploaded sensor device data.
 * You can learn more about Basis at http://www.mybasis.com/
 *
 * @author Bob Troia <bob@quantifiedbob.com>
 * @link   http://www.quantifiedbob.com
 *
*/

class BasisExport
{
    // Basis login details
    private $username;
    private $password;

    // Enable/disable debugging
    public $debug = false;

    // Access token
    private $access_token;

    // Data export date
    public $export_date;

    // Acceptable export formats
    private $export_formats = array('json', 'csv', 'html');

    // These settings should be left as-is
    private $export_start_offset = 0; // start time offset (in seconds)
    private $export_end_offset = 0; // end time offset (in seconds)
    private $export_interval = 60; // data granularity (60 = 1 reading per minute)

    // Used for cURL cookie storage (needed for api access)
    private $cookie_jar;

    public function __construct($username, $password)
    {
        $this->username = $username;
        $this->password = $password;

        // Location to store cURL's CURLOPT_COOKIEJAR (for access_token cookie)
        $this->cookie_jar = dirname(__FILE__) . '/cookie.txt';
    }

    /**
    * Attempts to login/authenticate to Basis server
    * @return bool
    * @throws Exception
    */
    function doLogin()
    {
        $login_data = array(
            'username' => $this->username,
            'password' => $this->password,
        );

        // Test configuration
        if ($this->debug) {
            $this->testConfig();
        }

        // Initialize the cURL resource and make login request
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => 'https://app.mybasis.com/login',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $login_data,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => 1,
            CURLOPT_COOKIESESSION => true,
            CURLOPT_COOKIEJAR => $this->cookie_jar
        ));
        $result = curl_exec($ch);
        curl_close($ch);

        if($result === false) {
            // A cURL error occurred
            throw new Exception('ERROR: cURL error - ' . curl_error($ch) . "\n");
            return false;
        }

        // Make sure login was successful and save access_token cookie for api requests.
        preg_match('/^Set-Cookie:\s*([^;]*)/mi', $result, $m);
        if (empty($m)) {
            throw new Exception('ERROR: Unable to login! Check your username and password.');
            return false;
        } else {
            parse_str($m[1], $cookies);
            if (empty($cookies['access_token'])) {
                throw new Exception('ERROR: Unable to get an access token!');
                return false;
            } else {
                $this->access_token = $cookies['access_token'];
                if ($this->debug) {
                    echo 'access_token cookie: ' . $this->access_token . "\n";
                }
            }
        }

    } // doLogin()

    /**
    * Retreive user's activities for given date and save to file
    * @param string $export_date Date in YYYY-MM-DD format
    * @param string $export_format Export type (json,csv,html)
    * @return bool
    * @throws Exception
    */
    function getActivities($export_date = '', $export_format = 'json')
    {
        // Check for YYYY-MM-DD date format, else throw error
        if (!isset($export_date)) {
            // default to yesterday
            $export_date = date('Y-m-d', strtotime('-1 day', time()));
        } else {
            $export_date = preg_replace('/[^-a-zA-Z0-9_]/', '', $export_date);
        }
        if (!$this->isValidDate($export_date)) {
            throw new Exception('ERROR: Invalid date -  ' . $export_date . "\n");
            return false;
        }

        // Make sure export format is valid
        if (!in_array($export_format, $this->export_formats)) {
            throw new Exception('ERROR: Invalid export format -  ' . $export_format . "\n");
            return false;
        }

        // Log into Basis account to authorize access.
        if (empty($this->access_token)) {
            try {
                $this->doLogin();
            } catch (Exception $e) {
                echo 'Caught exception: ',  $e->getMessage(), "\n";
                return false;
            }
        }

        // Request data from Basis for selected date. Note we're requesting all available data.
        $dataurl = 'https://app.mybasis.com/api/v1/chart/me?'
                . 'summary=true'
                . '&interval=' . $this->export_interval
                . '&units=ms'
                . '&start_date=' . $export_date
                . '&start_offset=' . $this->export_start_offset
                . '&end_offset=' . $this->export_end_offset
                . '&heartrate=true'
                . '&steps=true'
                . '&calories=true'
                . '&gsr=true'
                . '&skin_temp=true'
                . '&air_temp=true'
                . '&bodystates=true';

        // Initialize the cURL resource and make api request
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $dataurl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE => $this->cookie_jar
        ));
        $result = curl_exec($ch);
        $response_code = curl_getinfo ($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response_code == '401') {
            throw new Exception("ERROR: Unauthorized!\n");
            return false;
        }

        // Parse data from JSON response
        $json = json_decode($result, true);
        $report_date = $json['starttime']; // report date, as UNIX timestamp
        $heartrates = $json['metrics']['heartrate']['values'];
        $steps = $json['metrics']['steps']['values'];
        $calories = $json['metrics']['calories']['values'];
        $gsrs = $json['metrics']['gsr']['values'];
        $skintemps = $json['metrics']['skin_temp']['values'];
        $airtemps = $json['metrics']['air_temp']['values'];
        $bodystates = $json['bodystates'];

        if ($export_format == 'html') {
            // Save results as .html file
            $file = dirname(__FILE__) . '/data/basis-data-' . $export_date . '.html';
            $html = $this->activitiesToHTML($json);
            if (!file_put_contents($file, $html)) {
                throw new Exception("ERROR: Could not save data to file $file!");
                return false;
            }

        } else if ($export_format == 'csv') {
            // Save results as .csv file
            $file = dirname(__FILE__) . '/data/basis-data-' . $export_date . '.csv';

            $fp = fopen($file, 'w');
            if(!$fp) {
                throw new Exception("ERROR: Could not save data to file $file!");
                return false;
            }
            fputcsv($fp, array('timestamp', 'heartrate', 'steps', 'calories', 'gsr', 'skintemp', 'airtemp'));
            for ($i=0; $i<count($heartrates); $i++) {
                // HH:MM:SS timestamp
                $timestamp = strftime("%Y-%m-%d %T", mktime(0, 0, $i*$this->export_interval, date("n", $report_date), date("j", $report_date), date("Y", $report_date)));
                $row = array($timestamp, $heartrates[$i], $steps[$i], $calories[$i], $gsrs[$i], $skintemps[$i], $airtemps[$i]);

                // Add row to csv file
                fputcsv($fp, $row);
            }
            fclose($fp);

        }   else {
            // Save results as .json file
            $file = dirname(__FILE__) . '/data/basis-data-' . $export_date . '.json';
            if (!file_put_contents($file, $result)) {
                throw new Exception("ERROR: Could not save data to file $file!");
                return false;
            }
        }
    }

    /**
    * Utilitiy function to check/echo syste configuration
    */
    function testConfig()
    {
        $w = stream_get_wrappers();
        echo "------------------------------\n";
        echo "Checking system configuration:\n";
        echo "------------------------------\n";
        echo "OpenSSL: ",  extension_loaded  ('openssl') ? "yes":"NO", "\n";
        echo "HTTP wrapper: ", in_array('http', $w) ? "yes":'NO', "\n";
        echo "HTTPS wrapper: ", in_array('https', $w) ? "yes":"NO", "\n";
        echo "data/ writable: ", is_writable('./data') ? "yes":"NO", "\n";
        //echo "Wrappers: ", var_dump($w) . "\n";
        echo "------------------------------\n";
        return;
    }

    /**
    * Checks whether date string is in YYYY-MM-DD format
    * @param $str String contining date to check
    * @return bool
    */
    function isValidDate($str)
    {
        if (preg_match('/\b(\d{4})-(\d{2})-(\d{2})\b/', $str, $matches)) {
            if (checkdate($matches[2], $matches[3], $matches[1])) {
                return true;
            }
        }
        return false;
    }

    /**
    * Generates an HTML summary from json response.
    * Yes, this function is *very* ugly. It's only included for legacy purposes
    * and most likely you would never use this unless you want to open up
    * something nicely formatted in a web browser.
    * @param string $json JSON response from server
    * @return string Formatted HTML summary
    */
    function activitiesToHTML($json)
    {
        $result = $json;
        $report_date = $json['starttime']; // report date, as UNIX timestamp
        $heartrates = $json['metrics']['heartrate']['values'];
        $steps = $json['metrics']['steps']['values'];
        $calories = $json['metrics']['calories']['values'];
        $gsrs = $json['metrics']['gsr']['values'];
        $skintemps = $json['metrics']['skin_temp']['values'];
        $airtemps = $json['metrics']['air_temp']['values'];
        $bodystates = $json['bodystates'];

        $html = <<<HTML
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
    <!-- Heartrate data summary -->
    <tr>
        <td><strong>Heart Rate</strong><br />
        <td>{$result['metrics']['heartrate']['min']}</td>
        <td>{$result['metrics']['heartrate']['max']}</td>
        <td>{$result['metrics']['heartrate']['sum']}</td>
        <td>{$result['metrics']['heartrate']['avg']}</td>
        <td>{$result['metrics']['heartrate']['stdev']}</td>
        <td>{$result['metrics']['heartrate']['summary']['max_heartrate_per_minute']}</td>
        <td>{$result['metrics']['heartrate']['summary']['min_heartrate_per_minute']}</td>
    </tr>
    <!-- Steps data summary -->
    <tr>
        <td><strong>Steps</strong><br />
        <td>{$result['metrics']['steps']['min']}</td>
        <td>{$result['metrics']['steps']['max']}</td>
        <td>{$result['metrics']['steps']['sum']}</td>
        <td>{$result['metrics']['steps']['avg']}</td>
        <td>{$result['metrics']['steps']['stdev']}</td>
        <td>{$result['metrics']['steps']['summary']['max_steps_per_minute']}</td>
        <td>{$result['metrics']['steps']['summary']['min_steps_per_minute']}</td>
    </tr>
    <!-- Calories data summary -->
    <tr>
        <td><strong>Calories</strong><br />
        <td>{$result['metrics']['calories']['min']}</td>
        <td>{$result['metrics']['calories']['max']}</td>
        <td>{$result['metrics']['calories']['sum']}</td>
        <td>{$result['metrics']['calories']['avg']}</td>
        <td>{$result['metrics']['calories']['stdev']}</td>
        <td>{$result['metrics']['calories']['summary']['max_calories_per_minute']}</td>
        <td>{$result['metrics']['calories']['summary']['min_calories_per_minute']}</td>
    </tr>
    <!-- GSR data summary -->
    <tr>
        <td><strong>GSR</strong><br />
        <td>{$result['metrics']['gsr']['min']}</td>
        <td>{$result['metrics']['gsr']['max']}</td>
        <td>{$result['metrics']['gsr']['sum']}</td>
        <td>{$result['metrics']['gsr']['avg']}</td>
        <td>{$result['metrics']['gsr']['stdev']}</td>
        <td>{$result['metrics']['gsr']['summary']['max_gsr_per_minute']}</td>
        <td>{$result['metrics']['gsr']['summary']['min_gsr_per_minute']}</td>
    </tr>
    <!-- Skin temp data summary -->
    <tr>
        <td><strong>Skin Temp</strong><br />
        <td>{$result['metrics']['skin_temp']['min']}</td>
        <td>{$result['metrics']['skin_temp']['max']}</td>
        <td>{$result['metrics']['skin_temp']['sum']}</td>
        <td>{$result['metrics']['skin_temp']['avg']}</td>
        <td>{$result['metrics']['skin_temp']['stdev']}</td>
        <td>{$result['metrics']['skin_temp']['summary']['max_skin_temp_per_minute']}</td>
        <td>{$result['metrics']['skin_temp']['summary']['min_skin_temp_per_minute']}</td>
    </tr>
    <!-- Air temp data summary -->
    <tr>
        <td><strong>Air Temp</strong><br />
        <td>{$result['metrics']['air_temp']['min']}</td>
        <td>{$result['metrics']['air_temp']['max']}</td>
        <td>{$result['metrics']['air_temp']['sum']}</td>
        <td>{$result['metrics']['air_temp']['avg']}</td>
        <td>{$result['metrics']['air_temp']['stdev']}</td>
        <td>{$result['metrics']['air_temp']['summary']['max_air_temp_per_minute']}</td>
        <td>{$result['metrics']['air_temp']['summary']['min_air_temp_per_minute']}</td>
    </tr>
    </tbody>
    </table>
    <hr />

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
HTML;
        for ($i=0; $i< count($bodystates); $i++) {
            $html .= '<tr><td>' . strftime("%Y-%m-%d %T", $bodystates[$i][0]) . '</td>';
            $html .= '<td>' . strftime("%Y-%m-%d %T", $bodystates[$i][1]) . '</td>';
            $html .='<td>' . $bodystates[$i][2] . '</td></tr>';
        }
        $html .= <<<HTML
    </tbody>
    </table>
    <hr />

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
HTML;
        // Format and echo data to browser
        for ($i=0; $i<count($heartrates); $i++) {
            // HH:MM:SS timestamp
            $timestamp = strftime("%Y-%m-%d %T", mktime(0, 0, $i*$this->export_interval, date("n", $report_date), date("j", $report_date), date("Y", $report_date)));

            $html .= '<tr>';
            $html .= '<td>' . $timestamp . '</td>';
            $html .= '<td>' . ($heartrates[$i] == '' ? 'null' : $heartrates[$i]) . '</td>';
            $html .= '<td>' . ($steps[$i] == '' ? 'null' : $steps[$i]) . '</td>';
            $html .= '<td>' . ($calories[$i] == '' ? 'null' : $calories[$i]) . '</td>';
            $html .= '<td>' . ($gsrs[$i] == '' ? 'null' : $gsrs[$i]) . '</td>';
            $html .= '<td>' . ($skintemps[$i] == '' ? 'null' : $skintemps[$i]) . '</td>';
            $html .= '<td>' . ($airtemps[$i] == '' ? 'null' : $airtemps[$i]) . '</td>';
            $html .= '</tr>';
        }
        $html .= <<<HTML
    </tbody>
    </table>
</body>
</html>
HTML;
        return $html;

    } // end activitiesToHTML

} // end class BasisExport



?>

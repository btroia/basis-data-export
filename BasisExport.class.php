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
    private $export_offset = 0; // don't pad export start/end times
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

        if($result === false) {
            // A cURL error occurred
            throw new Exception('ERROR: cURL error - ' . curl_error($ch) . "\n");
            return false;
        }

        curl_close($ch);

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
    * Retreive user's biometric readings for given date and save to file
    * @param string $export_date Date in YYYY-MM-DD format
    * @param string $export_format Export type (json,csv,html)
    * @return bool
    * @throws Exception
    */
    function getMetrics($export_date = '', $export_format = 'json')
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
        $metrics_url = 'https://app.mybasis.com/api/v1/metricsday/me?'
                . 'day=' . $export_date
                . '&padding=' . $this->export_offset
                . '&heartrate=true'
                . '&steps=true'
                . '&calories=true'
                . '&gsr=true'
                . '&skin_temp=true'
                . '&air_temp=true';

        // Initialize the cURL resource and make api request
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $metrics_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE => $this->cookie_jar
        ));
        $result = curl_exec($ch);
        $response_code = curl_getinfo ($ch, CURLINFO_HTTP_CODE);

        if ($response_code == '401') {
            throw new Exception("ERROR: Unauthorized!\n");
            return false;
        }

        curl_close($ch);

        // Parse data from JSON response
        $json = json_decode($result, true);
        $report_date = $json['starttime']; // report date, as UNIX timestamp
        $heartrates = $json['metrics']['heartrate']['values'];
        $steps = $json['metrics']['steps']['values'];
        $calories = $json['metrics']['calories']['values'];
        $gsrs = $json['metrics']['gsr']['values'];
        $skintemps = $json['metrics']['skin_temp']['values'];
        $airtemps = $json['metrics']['air_temp']['values'];

        if ($export_format == 'html') {
            // Save results as .html file
            $file = dirname(__FILE__) . '/data/basis-data-' . $export_date . '-metrics.html';
            $html = $this->metricsToHTML($json);
            if (!file_put_contents($file, $html)) {
                throw new Exception("ERROR: Could not save data to file $file!");
                return false;
            }

        } else if ($export_format == 'csv') {
            // Save results as .csv file
            $file = dirname(__FILE__) . '/data/basis-data-' . $export_date . '-metrics.csv';

            $fp = fopen($file, 'w');
            if(!$fp) {
                throw new Exception("ERROR: Could not save data to file $file!");
                return false;
            }
            fputcsv($fp, array('timestamp', 'heartrate', 'steps', 'calories', 'gsr', 'skintemp', 'airtemp'));
            for ($i=0; $i<count($heartrates); $i++) {
                // HH:MM:SS timestamp
                $timestamp = strftime("%Y-%m-%d %H:%M:%S", mktime(0, 0, $i*$this->export_interval, date("n", $report_date), date("j", $report_date), date("Y", $report_date)));
                $row = array($timestamp, $heartrates[$i], $steps[$i], $calories[$i], $gsrs[$i], $skintemps[$i], $airtemps[$i]);

                // Add row to csv file
                fputcsv($fp, $row);
            }
            fclose($fp);

        }   else {
            // Save results as .json file
            $file = dirname(__FILE__) . '/data/basis-data-' . $export_date . '-metrics.json';
            if (!file_put_contents($file, $result)) {
                throw new Exception("ERROR: Could not save data to file $file!");
                return false;
            }
        }
    }

   /**
    * Retreive user's sleep data for given date and save to file
    * @param string $export_date Date in YYYY-MM-DD format
    * @param string $export_format Export type (json,csv,html)
    * @return bool
    * @throws Exception
    */
    function getSleep($export_date = '', $export_format = 'json')
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

        // Request sleep data from Basis for selected date. Note we're requesting all available data.
        $sleep_url = 'https://app.mybasis.com/api/v2/users/me/days/' . $export_date . '/activities?'
                . 'type=sleep'
                . '&expand=activities.stages,activities.events';

        // Initialize the cURL resource and make api request
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $sleep_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE => $this->cookie_jar
        ));
        $result = curl_exec($ch);
        $response_code = curl_getinfo ($ch, CURLINFO_HTTP_CODE);

        if ($response_code == '401') {
            throw new Exception("ERROR: Unauthorized!\n");
            return false;
        }

        curl_close($ch);

        // Parse data from JSON response
        $json = json_decode($result, true);

        // Create an array of sleep activities. Basis breaks up sleep into individual
        // events if there is an interruption longer than 15 minutes.
        $sleep = array();
        $sleep_activities = $json['content']['activities'];
        foreach ($sleep_activities as $sleep_activity) {
            // Add sleep event to array
            $sleep[] = array(
                'start_time'            => isset($sleep_activity['start_time']['timestamp']) ? $sleep_activity['start_time']['timestamp'] : '',
                'start_time_iso'        => isset($sleep_activity['start_time']['iso']) ? $sleep_activity['start_time']['iso'] : '',
                'start_time_timezone'   => isset($sleep_activity['start_time']['time_zone']['name']) ? $sleep_activity['start_time']['time_zone']['name'] : '',
                'start_time_offset'     => isset($sleep_activity['start_time']['time_zone']['offset']) ? $sleep_activity['start_time']['time_zone']['offset'] : '',
                'end_time'              => isset($sleep_activity['end_time']['timestamp']) ? $sleep_activity['end_time']['timestamp'] : '',
                'end_time_iso'          => isset($sleep_activity['end_time']['iso']) ? $sleep_activity['end_time']['iso'] : '',
                'end_time_timezone'     => isset($sleep_activity['end_time']['time_zone']['name']) ? $sleep_activity['end_time']['time_zone']['name'] : '',
                'end_time_offset'       => isset($sleep_activity['end_time']['time_zone']['offset']) ? $sleep_activity['end_time']['time_zone']['offset'] : '',
                'heart_rate_avg'        => isset($sleep_activity['heart_rate']['avg']) ? $sleep_activity['heart_rate']['avg'] : '',
                'heart_rate_min'        => isset($sleep_activity['heart_rate']['min']) ? $sleep_activity['heart_rate']['min'] : '',
                'heart_rate_max'        => isset($sleep_activity['heart_rate']['max']) ? $sleep_activity['heart_rate']['max'] : '',
                'actual_seconds'        => isset($sleep_activity['actual_seconds']) ? $sleep_activity['actual_seconds'] : '',
                'calories'              => isset($sleep_activity['calories']) ? $sleep_activity['calories'] : '',
                'light_minutes'         => isset($sleep_activity['sleep']['light_minutes']) ? $sleep_activity['sleep']['light_minutes'] : '',
                'deep_minutes'          => isset($sleep_activity['sleep']['deep_minutes']) ? $sleep_activity['sleep']['deep_minutes'] : '',
                'rem_minutes'           => isset($sleep_activity['sleep']['rem_minutes']) ? $sleep_activity['sleep']['rem_minutes'] : '',
                'interruption_minutes'  => isset($sleep_activity['sleep']['interruption_minutes']) ? $sleep_activity['sleep']['interruption_minutes'] : '',
                'unknown_minutes'       => isset($sleep_activity['sleep']['unknown_minutes']) ? $sleep_activity['sleep']['unknown_minutes'] : '',
                'interruptions'         => isset($sleep_activity['sleep']['interruptions']) ? $sleep_activity['sleep']['interruptions'] : '',
                'toss_and_turn'         => isset($sleep_activity['sleep']['toss_and_turn']) ? $sleep_activity['sleep']['toss_and_turn'] : '',
                'events'                => isset($sleep_activity['events']) ? $sleep_activity['events'] : '',
                'type'                  => isset($sleep_activity['type']) ? $sleep_activity['type'] : '',
                'state'                 => isset($sleep_activity['state']) ? $sleep_activity['state'] : '',
                'version'               => isset($sleep_activity['version']) ? $sleep_activity['version'] : '',
                'id'                    => isset($sleep_activity['id']) ? $sleep_activity['id'] : ''
            );
        }

        if ($export_format == 'html') {
            // Save results as .html file
            $file = dirname(__FILE__) . '/data/basis-data-' . $export_date . '-sleep.html';
            $html = $this->sleepToHTML($json);
            if (!file_put_contents($file, $html)) {
               throw new Exception("ERROR: Could not save data to file $file!");
               return false;
            }

        } else if ($export_format == 'csv') {
            // Save results as .csv file
            $file = dirname(__FILE__) . '/data/basis-data-' . $export_date . '-sleep.csv';

            $fp = fopen($file, 'w');
            if(!$fp) {
                throw new Exception("ERROR: Could not save data to file $file!");
                return false;
            }
            fputcsv($fp, array(
                'start time', 'start time ISO', 'start time timezone', 'start time offset',
                'end time', 'end time ISO', 'end time timezone', 'end time offset',
                'light mins', 'deep mins', 'rem mins', 'interruption mins', 'unknown mins', 'interruptions', 
                'toss turns', 'type', 'actual seconds', 'calories', 'heart rate avg', 'heart rate min', 
                'heart rate max', 'state', 'version', 'id'
                )
            );

            for ($i=0; $i<count($sleep); $i++) {
                // HH:MM:SS timestamp
                $start_time = strftime("%Y-%m-%d %H:%M:%S", $sleep[$i]['start_time']);
                $end_time = strftime("%Y-%m-%d %H:%M:%S", $sleep[$i]['end_time']);
                $row = array(
                    $start_time, $sleep[$i]['start_time_iso'], $sleep[$i]['start_time_timezone'], 
                    $sleep[$i]['start_time_offset'], $end_time, $sleep[$i]['end_time_iso'], 
                    $sleep[$i]['end_time_timezone'], $sleep[$i]['end_time_offset'],
                    $sleep[$i]['light_minutes'], $sleep[$i]['deep_minutes'], $sleep[$i]['rem_minutes'], 
                    $sleep[$i]['interruption_minutes'], $sleep[$i]['unknown_minutes'],
                    $sleep[$i]['interruptions'], $sleep[$i]['toss_and_turn'], $sleep[$i]['type'], 
                    $sleep[$i]['actual_seconds'], $sleep[$i]['calories'], $sleep[$i]['heart_rate_avg'], 
                    $sleep[$i]['heart_rate_min'], $sleep[$i]['heart_rate_max'], 
                    $sleep[$i]['state'], $sleep[$i]['version'], $sleep[$i]['id']
                    
                );

                // Add row to csv file
                fputcsv($fp, $row);
            }
            fclose($fp);

        }   else {
            // Save results as .json file
            $file = dirname(__FILE__) . '/data/basis-data-' . $export_date . '-sleep.json';
            if (!file_put_contents($file, $result)) {
                throw new Exception("ERROR: Could not save data to file $file!");
                return false;
            }
        }
    }


   /**
    * Retreive user's activity data for given date and save to file
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

        // Request activities data from Basis for selected date. Note we're requesting all available data.
        $activities_url = 'https://app.mybasis.com/api/v2/users/me/days/' . $export_date . '/activities?'
                . 'type=run,walk,bike'
                . '&expand=activities';

        // Initialize the cURL resource and make api request
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $activities_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE => $this->cookie_jar
        ));
        $result = curl_exec($ch);
        $response_code = curl_getinfo ($ch, CURLINFO_HTTP_CODE);

        if ($response_code == '401') {
            throw new Exception("ERROR: Unauthorized!\n");
            return false;
        }

        curl_close($ch);

        // Parse data from JSON response
        $json = json_decode($result, true);

        // Create an array of activities.
        $activities = array();
        $activity_items = $json['content']['activities'];
        foreach ($activity_items as $activity_item) {
            // Add activity to array
            $activities[] = array(
                'start_time'            => isset($activity_item['start_time']['timestamp']) ? $activity_item['start_time']['timestamp'] : '',
                'start_time_iso'        => isset($activity_item['start_time']['iso']) ? $activity_item['start_time']['iso'] : '',
                'start_time_timezone'   => isset($activity_item['start_time']['time_zone']['name']) ? $activity_item['start_time']['time_zone']['name'] : '',
                'start_time_offset'     => isset($activity_item['start_time']['time_zone']['offset']) ? $activity_item['start_time']['time_zone']['offset'] : '',
                'end_time'              => isset($activity_item['end_time']['timestamp']) ? $activity_item['end_time']['timestamp'] : '',
                'end_time_iso'          => isset($activity_item['end_time']['iso']) ? $activity_item['end_time']['iso'] : '',
                'end_time_timezone'     => isset($activity_item['end_time']['time_zone']['name']) ? $activity_item['end_time']['time_zone']['name'] : '',
                'end_time_offset'       => isset($activity_item['end_time']['time_zone']['offset']) ? $activity_item['end_time']['time_zone']['offset'] : '',
                'heart_rate_avg'        => isset($activity_item['heart_rate']['avg']) ? $activity_item['heart_rate']['avg'] : '',
                'heart_rate_min'        => isset($activity_item['heart_rate']['min']) ? $activity_item['heart_rate']['min'] : '',
                'heart_rate_max'        => isset($activity_item['heart_rate']['max']) ? $activity_item['heart_rate']['max'] : '',
                'actual_seconds'        => isset($activity_item['actual_seconds']) ? $activity_item['actual_seconds'] : '',
                'calories'              => isset($activity_item['calories']) ? $activity_item['calories'] : '',
                'steps'                 => isset($activity_item['steps']) ? $activity_item['steps'] : '',
                'minutes'               => isset($activity_item['minutes']) ? $activity_item['minutes'] : '',
                'type'                  => isset($activity_item['type']) ? $activity_item['type'] : '',
                'state'                 => isset($activity_item['state']) ? $activity_item['state'] : '',
                'version'               => isset($activity_item['version']) ? $activity_item['version'] : '',
                'id'                    => isset($activity_item['id']) ? $activity_item['id'] : ''
            );
        }

        if ($export_format == 'html') {
            // Save results as .html file
            $file = dirname(__FILE__) . '/data/basis-data-' . $export_date . '-activities.html';
            $html = $this->activitiesToHTML($json);
            if (!file_put_contents($file, $html)) {
                throw new Exception("ERROR: Could not save data to file $file!");
                return false;
            }

        } else if ($export_format == 'csv') {
            // Save results as .csv file
            $file = dirname(__FILE__) . '/data/basis-data-' . $export_date . '-activities.csv';

            $fp = fopen($file, 'w');
            if(!$fp) {
                throw new Exception("ERROR: Could not save data to file $file!");
                return false;
            }
            fputcsv($fp, array(
                'start time', 'start time ISO', 'start time timezone', 'start time offset',
                'end time', 'end time ISO', 'end time timezone', 'end time offset',
                'type', 'actual seconds', 'steps', 'calories', 'minutes', 'heart rate avg', 'heart rate min', 'heart rate max',
                'state', 'version', 'id'
                )
            );
            for ($i=0; $i<count($activities); $i++) {
                // HH:MM:SS timestamp
                $start_time = strftime("%Y-%m-%d %H:%M:%S", $activities[$i]['start_time']);
                $end_time = strftime("%Y-%m-%d %H:%M:%S", $activities[$i]['end_time']);
                $row = array(
                    $start_time, $activities[$i]['start_time_iso'], $activities[$i]['start_time_timezone'], 
                    $activities[$i]['start_time_offset'], $end_time, $activities[$i]['end_time_iso'], 
                    $activities[$i]['end_time_timezone'], $activities[$i]['end_time_offset'],
                    $activities[$i]['type'], $activities[$i]['actual_seconds'], $activities[$i]['steps'],
                    $activities[$i]['calories'], $activities[$i]['minutes'], $activities[$i]['heart_rate_avg'], 
                    $activities[$i]['heart_rate_min'], $activities[$i]['heart_rate_max'], $activities[$i]['state'],
                    $activities[$i]['version'], $activities[$i]['id']
                );

                // Add row to csv file
                fputcsv($fp, $row);
            }
            fclose($fp);

        }   else {
            // Save results as .json file
            $file = dirname(__FILE__) . '/data/basis-data-' . $export_date . '-activities.json';
            if (!file_put_contents($file, $result)) {
                throw new Exception("ERROR: Could not save data to file $file!");
                return false;
            }
        }
    }

    /**
    * Utility function to check/echo system configuration
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
    * Generates an HTML summary of metrics data from json response.
    * Yes, this function is *very* ugly. It's only included for legacy purposes
    * and most likely you would never use this unless you want to open up
    * something nicely formatted in a web browser.
    * @param string $json JSON response from server
    * @return string Formatted HTML summary
    */
    function metricsToHTML($json)
    {
        $result = $json;
        $report_timestamp = $json['starttime']; // report date, as UNIX timestamp
        $report_date = strftime("%Y-%m-%d", $report_timestamp);
        $heartrates = $json['metrics']['heartrate']['values'];
        $steps = $json['metrics']['steps']['values'];
        $calories = $json['metrics']['calories']['values'];
        $gsrs = $json['metrics']['gsr']['values'];
        $skintemps = $json['metrics']['skin_temp']['values'];
        $airtemps = $json['metrics']['air_temp']['values'];

        $html = <<<HTML
<html>
<head><title>My Metrics Data for {$report_date}</title>
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

    <h3>My Metrics Data for {$report_date}</h3>
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
            $timestamp = strftime("%Y-%m-%d %H:%M:%S", mktime(0, 0, $i*$this->export_interval, date("n", $report_timestamp), date("j", $report_timestamp), date("Y", $report_timestamp)));

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

    } // end metricsToHTML

    /**
    * Generates an HTML summary of sleep data from json response.
    * Yes, this function is *very* ugly. It's only included for legacy purposes
    * and most likely you would never use this unless you want to open up
    * something nicely formatted in a web browser.
    * @param string $json JSON response from server
    * @return string Formatted HTML summary
    */
    function sleepToHTML($json)
    {
        $report_date = strftime("%Y-%m-%d", $json['content']['activities'][0]['start_time']['timestamp']);
        $html = <<<HTML
<html>
<head><title>My Sleep Data for {$report_date}</title>
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
    <h3>My Sleep Data for {$report_date}</h3>
    <table id="my-data">
    <thead>
        <tr>
            <th scope="col" id="start-time">Start Time</th>
            <th scope="col" id="end-time">End Time</th>
            <th scope="col" id="hr-avg">Heart Rate Avg</th>
            <th scope="col" id="hr-min">Heart Rate Min</th>
            <th scope="col" id="hr-max">Heart Rate Max</th>
            <th scope="col" id="cals">Calories</th>
            <th scope="col" id="actual-secs">Actual Secs</th>
            <th scope="col" id="light-mins">Light Mins</th>
            <th scope="col" id="deep-mins">Deep Mins</th>
            <th scope="col" id="rem-mins">REM Mins</th>
            <th scope="col" id="inter-mins">Interruption Mins</th>
            <th scope="col" id="unknown-mins">Unknown Mins</th>
            <th scope="col" id="interrupts">Interruptions</th>
            <th scope="col" id="toss-turns">Toss Turns</th>
        </tr>
    </thead>
    <tbody>
HTML;

        $sleep = $json['content']['activities'];
        // Format and echo data to browser
        for ($i=0; $i<count($sleep); $i++) {
            $start_time = strftime("%Y-%m-%d %H:%M:%S", $sleep[$i]['start_time']['timestamp']);
            $end_time = strftime("%Y-%m-%d %H:%M:%S", $sleep[$i]['end_time']['timestamp']);
            $html .= '<tr>';
            $html .= '<td>' . $start_time . '</td>';
            $html .= '<td>' . $end_time . '</td>';
            $html .= '<td>' . (isset($sleep[$i]['heart_rate']['avg']) ? $sleep[$i]['heart_rate']['avg'] : '-') . '</td>';
            $html .= '<td>' . (isset($sleep[$i]['heart_rate']['min']) ? $sleep[$i]['heart_rate']['min'] : '-') . '</td>';
            $html .= '<td>' . (isset($sleep[$i]['heart_rate']['max']) ? $sleep[$i]['heart_rate']['max'] : '-') . '</td>';
            $html .= '<td>' . (isset($sleep[$i]['calories']) ? $sleep[$i]['calories'] : '0') . '</td>';
            $html .= '<td>' . (isset($sleep[$i]['actual_seconds']) ? $sleep[$i]['actual_seconds'] : '0') . '</td>';
            $html .= '<td>' . (isset($sleep[$i]['sleep']['light_minutes']) ? $sleep[$i]['sleep']['light_minutes'] : '0') . '</td>';
            $html .= '<td>' . (isset($sleep[$i]['sleep']['deep_minutes']) ? $sleep[$i]['sleep']['deep_minutes'] : '0') . '</td>';
            $html .= '<td>' . (isset($sleep[$i]['sleep']['rem_minutes']) ? $sleep[$i]['sleep']['rem_minutes'] : '0') . '</td>';
            $html .= '<td>' . (isset($sleep[$i]['sleep']['interruption_minutes']) ? $sleep[$i]['sleep']['interruption_minutes'] : '0') . '</td>';
            $html .= '<td>' . (isset($sleep[$i]['sleep']['unknown_minutes']) ? $sleep[$i]['sleep']['unknown_minutes'] : '0') . '</td>';
            $html .= '<td>' . (isset($sleep[$i]['sleep']['interruptions']) ? $sleep[$i]['sleep']['interruptions'] : '0') . '</td>';
            $html .= '<td>' . (isset($sleep[$i]['sleep']['toss_and_turn']) ? $sleep[$i]['sleep']['toss_and_turn'] : '0') . '</td>';
            $html .= '</tr>';
        }
        $html .= <<<HTML
    </tbody>
    </table>
</body>
</html>
HTML;
        return $html;

    } // end sleepToHTML

    /**
    * Generates an HTML summary of activities data from json response.
    * Yes, this function is *very* ugly. It's only included for legacy purposes
    * and most likely you would never use this unless you want to open up
    * something nicely formatted in a web browser.
    * @param string $json JSON response from server
    * @return string Formatted HTML summary
    */
    function activitiesToHTML($json)
    {
        $report_date = strftime("%Y-%m-%d", $json['content']['activities'][0]['start_time']['timestamp']);

        $html = <<<HTML
<html>
<head><title>My Activity Data for {$report_date}</title>
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
    <h3>My Activity Data for {$report_date}</h3>
    <table id="my-data">
    <thead>
        <tr>
            <th scope="col" id="start-time">Start Time</th>
            <th scope="col" id="end-time">End Time</th>
            <th scope="col" id="hr-avg">Heart Rate Avg</th>
            <th scope="col" id="hr-min">Heart Rate Min</th>
            <th scope="col" id="hr-max">Heart Rate Max</th>
            <th scope="col" id="actual-secs">Actual Secs</th>
            <th scope="col" id="cals">Calories</th>
            <th scope="col" id="steps">Steps</th>
            <th scope="col" id="mins">Minutes</th>
            <th scope="col" id="type">Type</th>
        </tr>
    </thead>
    <tbody>
HTML;
        $activities = $json['content']['activities'];

        // Format and echo data to browser
        for ($i=0; $i<count($activities); $i++) {
            $start_time = strftime("%Y-%m-%d %H:%M:%S", $activities[$i]['start_time']['timestamp']);
            $end_time = strftime("%Y-%m-%d %H:%M:%S", $activities[$i]['end_time']['timestamp']);
            $html .= '<tr>';
            $html .= '<td>' . $start_time . '</td>';
            $html .= '<td>' . $end_time . '</td>';
            $html .= '<td>' . (isset($activities[$i]['heart_rate']['avg']) ? $activities[$i]['heart_rate']['avg'] : '-') . '</td>';
            $html .= '<td>' . (isset($activities[$i]['heart_rate']['min']) ? $activities[$i]['heart_rate']['min'] : '-') . '</td>';
            $html .= '<td>' . (isset($activities[$i]['heart_rate']['max']) ? $activities[$i]['heart_rate']['max'] : '-') . '</td>';
            $html .= '<td>' . (isset($activities[$i]['actual_seconds']) ? $activities[$i]['actual_seconds'] : '0') . '</td>';
            $html .= '<td>' . (isset($activities[$i]['calories']) ? $activities[$i]['calories'] : '0') . '</td>';
            $html .= '<td>' . (isset($activities[$i]['steps']) ? $activities[$i]['steps'] : '0') . '</td>';
            $html .= '<td>' . (isset($activities[$i]['minutes']) ? $activities[$i]['minutes'] : '0') . '</td>';
            $html .= '<td>' . (isset($activities[$i]['type']) ? $activities[$i]['type'] : '') . '</td>';
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

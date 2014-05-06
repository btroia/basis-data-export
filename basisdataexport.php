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
 * Usage:
 * This script can be run several ways. You can edit the BASIS_USERNAME, BASIS_PASSWORD, 
 * and BASIS_EXPORT_FORMAT values under "Settings" below so you don't have to specify
 * them every time the script is run. Make sure the data/ folder is writeable!
 *
 * [Method 1] Via interactive mode
 *   a. Open a terminal window and cd to this script's directory.
 *   b. Type php basisdataexport.php
 *   c. Follow the prompts (hit ENTER to use default values)
 *   d. Your data will be save to /data/basis-data-[YYYY-MM-DD].[format]';
 *   
 * [Method 2] Via command-line arguments (useful for crons)
 *   php basisdataexport.php -h -u[username] -p[pass] -d[YYYY-MM-DD] -f[json|csv|html]
 *
 *   Options:
 *   -u  Basis username (if not used, defaults to BASIS_USERNAME)
 *   -p  Basis password (if not used, defaults to BASIS_PASSWORD)
 *   -d  Data export date (YYYY-MM-DD) (if not used, defaults to current date)
 *   -f  Data export format (json|csv|html) (if not used, defaults to json)
 *   -h  Show this help text
 * 
 * [Method 3] Via web browser 
 *   This assumes your script is in a location that is executable via a web server,
 *   i.e., http://localhost/basis-data-export/basisdataexport.php?u=[basis_username]&p=[basis_password]&d=[YYYY-MM-DD]&f=[format]
 *
 *  
*/
require_once(dirname(__FILE__) . '/BasisExport.class.php'); 

///////////////////////////////////////////////////////
// Settings
///////////////////////////////////////////////////////

// Specify your Basis username, password, and export format. Leaving blank 
// will require inputting these values manually each time the script is run.
define('BASIS_USERNAME', '');
define('BASIS_PASSWORD', '');
define('BASIS_EXPORT_FORMAT', 'json');

// Enable/disable debug mode
define('DEBUG', false);

///////////////////////////////////////////////////////
// You shouldn't need to edit anything below this line!
///////////////////////////////////////////////////////

// See if we are running in command-line mode
if (php_sapi_name() == "cli") {

    // Check for command-line arguments, otherwise enter interactive mode.
    if($argc > 1) {
        $settings = runCommandLine();
    } else {
        // Enter interactive mode
        $settings = runInteractive();
    }

} else {

    // Request has been make via web browser
    $settings = runHttp();
}

// Create instance of BasisExport class
$basis = new BasisExport($settings['basis_username'], $settings['basis_password']);
$basis->debug = DEBUG;

// Make API request to Basis
try {
    $basis->getActivities($settings['basis_export_date'], $settings['basis_export_format']);
} catch (Exception $e) {
    echo 'Exception: ',  $e->getMessage(), "\n";
}

/**
* Take parameters via command-line args
**/
function runCommandLine()
{
    $options = getopt("h::u::p::d::f::");
    $settings = array();
    $settings['basis_username'] = (!defined(BASIS_USERNAME)) ? '' : BASIS_USERNAME;
    $settings['basis_password'] = (!defined(BASIS_PASSWORD)) ? '' : BASIS_PASSWORD;
    $settings['basis_export_date'] = date('Y-m-d', strtotime('now', time()));
    $settings['basis_export_format'] = (!defined(BASIS_EXPORT_FORMAT)) ? 'json' : BASIS_EXPORT_FORMAT;

    while (list($key, $value) = each($options)) {
        if ($key == 'h') {
            echo "-------------------------\n";
            echo "Basis data export script.\n";
            echo "-------------------------\n";
            echo "Usage:\n";
            echo "php basisdataexport.php -u[username] -p[pass] -d[YYYY-MM-DD] -f[json|csv|html]\n\n";
            echo "options:\n";
            echo "-u  Basis username\n";
            echo "-p  Basis password\n";
            echo "-d  Data export date (YYYY-MM-DD). If blank will use today's date\n";
            echo "-f  Data export format (json|csv|html)\n";
            echo "-h  Show this help text\n";
            echo "-------------------------\n";
            return;
        }
        if ($key == 'u') {
            if (empty($value)) {
                die ("No username specified!\n");
            } else {
                $settings['basis_username'] = trim($value);
            }
        }
        if ($key == 'p') {
            if (empty($value)) {
                die ("No password specified!\n");
            } else {
                $settings['basis_password'] = trim($value);
            }
        }
        if ($key == 'd') {
            if (empty($value)) {
                die ("No date specified!\n");
            } else {
                $settings['basis_export_date'] = trim($value);
            }
        }
        if ($key == 'f') {
            if (empty($value)) {
                die ("No format specified!\n");
            } else {
                $settings['basis_export_format'] = trim($value);
            }
        }

    }
    return $settings;
}

/**
* Take parameters via interactive shell
**/
function runInteractive()
{
    $basis_username = (!defined(BASIS_USERNAME)) ? '' : BASIS_USERNAME;
    $basis_password = (!defined(BASIS_PASSWORD)) ? '' : BASIS_PASSWORD;
    $basis_password_mask = (!defined(BASIS_PASSWORD)) ? '' : '********';
    $basis_export_date = date('Y-m-d', strtotime('now', time()));
    $basis_export_format = (!defined(BASIS_EXPORT_FORMAT)) ? 'json' : BASIS_EXPORT_FORMAT;
    $settings = array();

    echo "-------------------------\n";
    echo "Basis data export script.\n";
    echo "-------------------------\n";
    $handle = fopen ("php://stdin","r");
    echo "Enter Basis username [$basis_username]: ";
    $input_username = trim(fgets($handle));
    $settings['basis_username'] = (empty($input_username) ? $basis_username : $input_username);
    echo "Enter Basis password [$basis_password_mask]: ";
    $input_password = trim(fgets($handle));
    $settings['basis_password'] = (empty($input_password) ? $basis_password : $input_password);
    echo "Enter data export date (YYYY-MM-DD) [$basis_export_date] : ";
    $input_export_date = trim(fgets($handle));
    $settings['basis_export_date'] = (empty($input_export_date) ? $basis_export_date : $input_export_date);
    echo "Enter export format (json|csv|html) [$basis_export_format] : ";
    $input_export_format = trim(fgets($handle));
    $settings['basis_export_format'] = (empty($input_export_format) ? $basis_export_format : $input_export_format);
    fclose($handle);

    if (DEBUG ) {
        echo "-----------------------------\n";
        echo "Using the following settings:\n";
        echo "-----------------------------\n";
        echo 'Username: ' . $settings['basis_username'] . "\n";
        echo 'Password: ' . $settings['basis_password'] . "\n";
        echo 'Date: ' . $settings['basis_export_date'] . "\n";
        echo 'Format: ' . $settings['basis_export_format'] . "\n";
        echo "-----------------------------\n";
    }

    return ($settings);
}

/**
* Take parameters via http $_GET args
**/
function runHttp()
{
    $basis_username = (!defined(BASIS_USERNAME)) ? '' : BASIS_USERNAME;
    $basis_password = (!defined(BASIS_PASSWORD)) ? '' : BASIS_PASSWORD;
    $basis_export_date = date('Y-m-d', strtotime('now', time()));
    $basis_export_format = (!defined(BASIS_EXPORT_FORMAT)) ? 'json' : BASIS_EXPORT_FORMAT;
    $settings = array();

    $settings['basis_username'] = (empty($_GET['u']) ? $basis_username : strip_tags($_GET['u']));
    $settings['basis_password'] = (empty($_GET['p']) ? $basis_password : strip_tags($_GET['p']));
    $settings['basis_export_date'] = (empty($_GET['d']) ? $basis_export_date : strip_tags($_GET['d']));
    $settings['basis_export_format'] = (empty($_GET['f']) ? $basis_export_format : strip_tags($_GET['f']));

    return ($settings);
}

?>

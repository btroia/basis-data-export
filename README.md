# Basis Data Export

Utility that exports and saves your Basis Health Tracker (B1/Peak) uploaded sensor data, as described at [https://www.quantifiedbob.com/2013/04/liberating-your-data-from-the-basis-b1-band/](https://www.quantifiedbob.com/2013/04/liberating-your-data-from-the-basis-b1-band/)
You can learn more about Basis at [http://www.mybasis.com/](http://www.mybasis.com/)

> UPDATE JANUARY 1, 2017 -

> RIP Basis - Intel has officially shut down Basis, including access to any historical data (users had until December 31, 2016 to export data).

> UPDATE AUGUST 3, 2016 -

> Intel (who acquired Basis) has notified users that they will be discontinuing all Basis devices and services on December 31, 2016. Make sure to download all of your data before then! See [http://www.mybasis.com/safety/](http://www.mybasis.com/safety/) to learn more.

## Instructions

In order to use this script, you must already have a Basis account (and a Basis B1 band).

### Usage:
This script can be run several ways. You can (and should probably) first edit the `BASIS_USERNAME`, `BASIS_PASSWORD`, and `BASIS_EXPORT_FORMAT` values under "Settings" in the file `basisdataexport.php` so you don't have to specify those values every time the script is run. Make sure the `data/` folder is writable!

```php
///////////////////////////////////////////////////////
// Settings
///////////////////////////////////////////////////////

// Specify your Basis username, password, and default export format. Leaving blank 
// will require inputting these values manually each time the script is run.
define('BASIS_USERNAME', '[YOUR BASIS USERNAME]');
define('BASIS_PASSWORD', '[YOUR BASIS PASSWORD');
define('BASIS_EXPORT_FORMAT', 'json');

// Enable/disable debug mode
define('DEBUG', false);
```

### Method 1 - Interactive Mode

```
$ php basisdataexport.php
-------------------------
Basis data export script.
-------------------------
Enter Basis username [me@somewhere.com]: me@somewhere.com
Enter Basis password [********]: 
Enter data export date (YYYY-MM-DD) [2016-08-22] : 
Enter export format (json|csv|html) [json] : 
```

1. Open a terminal window and cd to this script's directory
2. Type `php basisdataexport.php`
3. Follow the prompts (hit ENTER to use default values)
4. Your data will be saved to `/data/basis-data-[YYYY-MM-DD].[format]`


### Method 2 - Via command-line arguments (useful for crons)

```
$ php basisdataexport.php -ume@somewhere.com -pMyBasisPassword -d2016-08-22 -fcsv
```

Usage `php basisdataexport.php -h -u[username] -p[pass] -d[YYYY-MM-DD] -f[json|csv|html]`
```
Options:
  -u  Basis username (if not used, defaults to BASIS_USERNAME)
  -p  Basis password (if not used, defaults to BASIS_PASSWORD)
  -d  Data export date (YYYY-MM-DD) (if not used, defaults to current date)
  -f  Data export format (json|csv|html) (if not used, defaults to json)
  -h  Show this help text
```
Make sure there are no spaces between any flags and values!

### Method 3 - Via web browser
This requires that the scripts are in a location that is executable via a web server.

`http://localhost/basis-data-export/basisdataexport.php?u=[basis_username]&p=[basis_password]&d=[YYYY-MM-DD]&f=[format]`

## Saving Your Data
If the script runs successfully, your data will be saved in the `data/` folder. Files are saved in the format `basis-data-[YYYY-MM-DD].[format]` (i.e., `basis-data-2014-04-04.json`).

That's it! (for now).


## Biometric Data

Basis currently returns the following biometric data points. They each represent an average (i.e., for heart rate, GSR, skin/air temperature) or sum (i.e., steps, calories) over the previous 1-minute period:

- Time - time reading was taken
- Heart Rate - beats per minute
- Steps - number of steps taken
- Calories - number of calories burned
- GSR - Galvanic skin response (i.e., sweat/skin conductivity. Learn more about GSR here - [http://en.wikipedia.org/wiki/Skin_conductance](http://en.wikipedia.org/wiki/Skin_conductance))
- Skin Temperature - skin temperature (degrees F)
- Air Temperature - air temperatute (degrees F)

There are some other aggregate metrics included in the reponse such as min/max/average/standard deviation metrics for each set of data.

## Sleep Data

Basis currently returns the following sleep data points (it's in a different format than the metrics data because it's a newer version of their API):

- Start Time - local timestamp in MM/DD/YY HH:MM format
- Start Time ISO - user timestamp in ISO format
- Start Time Time Zone - user time zone, i.e., America/New_York
- Start Time Offset - minutes offset from GMT (i.e., -240 for America/New_York)
- End Time - user timestamp in MM/DD/YY HH:MM format
- End Time ISO - user timestamp in ISO format
- End Time Time Zone - user time zone, i.e., America/New_York
- End Time Offset - minutes offset from GMT (i.e., -240 for America/New_York)
- Light Minutes - number of minutes in "light sleep" stage
- Deep Minutes - number of minutes in "deep sleep" stage
- REM Minutes - number of minutes in "REM sleep" stage
- Interruption Minutes - number of minutes interrupted (i.e., woke up, went to bathroom, etc.)
- Unknown Minutes - number of minutes unknown (i.e., device wasn't able to take readings)
- Tosses and Turns - number of tosses and turns that occurred
- Type - always returns 'sleep'
- Actual Seconds - total sleep time recorded, in seconds
- Calories - number of calories burned
- Average Heart Rate - beats per minute
- Minimum Heart Rate - beats per minute (currently this always returns null)
- Maximum Heart Rate - beats per minute (currently this always returns null)
- State - returns 'complete' if event was completed at time of export
- Version - API version used (i.e., 2)
- ID - internal id for given sleep event

Sleep data is often broken into multiple segments - Basis treats each sleep activity as a separate 'event' if there is a 15-minute gap in readings (i.e., sensors couldn't detect anything).

## Activity Data

Similar to sleep data, Basis currently returns the following activity data points (walking, biking, running):

- Start Time - local timestamp in MM/DD/YY HH:MM format
- Start Time ISO - user timestamp in ISO format
- Start Time Time Zone - user time zone, i.e., America/New_York
- Start Time Offset - minutes offset from GMT (i.e., -240 for America/New_York)
- End Time - user timestamp in MM/DD/YY HH:MM format
- End Time ISO - user timestamp in ISO format
- End Time Time Zone - user time zone, i.e., America/New_York
- End Time Offset - minutes offset from GMT (i.e., -240 for America/New_York)
- Type - activity type (i.e., walk, run, bike)
- Actual Seconds - total activity time, in seconds
- Steps - number of steps taken
- Calories - number of calories burned
- Minutes - actual number of minutes activity was performed (excludes time not moving?)
- Average Heart Rate - beats per minute (currently this always returns null)
- Minimum Heart Rate - beats per minute (currently this always returns null)
- Maximum Heart Rate - beats per minute (currently this always returns null)
- State - returns 'complete' if event was completed at time of export
- Version - API version used (i.e., 2)
- ID - internal id for given sleep event

### Tips
- You can set up a cron to run once per day to automatically grab your previous day's data (assuming you are syncing your device each day)
- If you want to archive data across a date range you can use curl's [ ] syntax to do it easily (thanks to [@Edrabbit](http://twitter.com/edrabbit) for the tip!). For example, to get all of May cached in /data:

  `curl http://localhost/basis-data-export/basisdataexport.php?d=2013-05-[01-31]`

### Special Tip for Windows Users and Setting Up SSL/cURL
(Thanks to [@joshuagarity](https://github.com/joshuagarity) for the tip!)
If you haven't already, you will need to install an SSL certificate on your system in order for the script to work (because the user authentication only works over SSL, and is required by cURL). Here's how:

- Download a certificate file at http://curl.haxx.se/ca/cacert.pem and save it somewhere on your computer
- Run `update php.ini -- add curl.cainfo = "PATH_TO/cacert.pem"` where `"PATH_TO/cacert.pem"` is the location of the certificate file you just downloaded. If you are running [XAMPP](https://www.apachefriends.org/index.html), you would save the `cacert.pem` to `c:\xampp\cacert.pem`. Then, open XAMPP, click on Config, then selected `PHP.ini`. On the second line of the file paste the following:

  `curl.cainfo = "C:\xampp\cacert.pem"`

- Lastly, save the file and you should be able to run the script.



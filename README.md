# Basis Data Export

Utility that exports and saves your Basis B1 device's uploaded sensor data.
You can learn more about Basis at [http://www.mybasis.com/](http://www.mybasis.com/)

## Instructions

In order to use this script, you must already have a Basis account (and a Basis B1 band).

### Usage:
This script can be run several ways. You can (and should probably) edit the `BASIS_USERNAME`, `BASIS_PASSWORD`, and `BASIS_EXPORT_FORMAT` values under "Settings" in `basisdataexport.php` so you don't have to specify those values every time the script is run. Make sure the `data/` folder is writeable!

![basis export config](http://www.quantifiedbob.com/images/basis-screenshots/basis-export-screenshot-config.png)

### Method 1 - Interactive Mode

![basis export option 1](http://www.quantifiedbob.com/images/basis-screenshots/basis-export-screenshot-1.png)

1. Open a terminal window and cd to this script's directory.
2. Type `php basisdataexport.php`
3. Follow the prompts (hit ENTER to use default values)
4. Your data will be saved to `/data/basis-data-[YYYY-MM-DD].[format]`


### Method 2 - Via command-line arguments (useful for crons)

![basis export option 2](http://www.quantifiedbob.com/images/basis-screenshots/basis-export-screenshot-2.png)

Usage `php basisdataexport.php -h -u[username] -p[pass] -d[YYYY-MM-DD] -f[json|csv|html]`
```
Options:
  -u  Basis username (if not used, defaults to BASIS_USERNAME)
  -p  Basis password (if not used, defaults to BASIS_PASSWORD)
  -d  Data export date (YYYY-MM-DD) (if not used, defaults to current date)
  -f  Data export format (json|csv|html) (if not used, defaults to json)
  -h  Show this help text
```

### Method 3 - Via web browser
This requires that the scripts are in a location that is executable via a web server.

`http://localhost/basis-data-export/basisdataexport.php?u=[basis_username]&p=[basis_password]&d=[YYYY-MM-DD]&f=[format]`

## Saving Your Data
If the script runs successfully, your data will be saved in the `./data` folder. Files are saved in the format `basis-data-[YYYY-MM-DD].[format]` (i.e., `basis-data-2014-04-04.json`).

That's it! (for now).


## Data Values

Basis currently returns the following data points. They will represent an average (for heart rate) or sum (steps) over the previous 1-minute period:

- Time - time reading was taken
- Heart Rate - beats per minute
- Steps - number of steps taken
- Calories - number of calories burned
- GSR - Galvanic skin response (i.e., sweat/skin conductivity. Learn more about GSR here - [http://en.wikipedia.org/wiki/Skin_conductance](http://en.wikipedia.org/wiki/Skin_conductance)
- Skin Temperature - skin temperature (degrees F)
- Air Temperature - air temperatute (degrees F)

There are some other aggregate metrics included in the reponse such as min/max/average/standard deviation metrics for each set of data.

### Tips
- You can set up a cron to run once per day to automatically grab your previous day's data (assuming you are syncing your device each day)
- If you want to archive data across a date range you can use curl's [ ] syntax to do it easily (thanks to [@Edrabbit](http://twitter.com/edrabbit) for the tip!). For example, to get all of May cached in /data:

  `curl http://localhost/basis-data-export/basisdataexport.php?date=2013-05-[01-31]`




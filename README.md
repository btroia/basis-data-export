# Basis Data Export

Utility that exports and saves your Basis B1 device's uploaded sensor data.
You can learn more about Basis at [http://www.mybasis.com/](http://www.mybasis.com/)

## Instructions

### Finding Your Basis User ID
- Log into your Basis account at [http://www.mybasis.com](http://gist.github.com).
- Right-click and access your web browser's developer tools by selecting "Inspect Element" (on Chrome - you can also access this by going to the "View->Developer->Developer Tools" menu):

![basis export step 1](http://www.quantifiedbob.com/images/basis-screenshots/export1.png)

- You should now see the Developer Tools pane:

![basis export step 2](http://www.quantifiedbob.com/images/basis-screenshots/export2.png)

- Go to the "Data" menu and select "Details":

![basis export step 3](http://www.quantifiedbob.com/images/basis-screenshots/export3.png) 

- Click on the "Network" tab in the Developer Tools frame and reload the page:

![basis export step 4](http://www.quantifiedbob.com/images/basis-screenshots/export4.png)

Scroll down the list of network requests and look for a request that beings with:
"https://app.mybasis.com/api/v1/chart/123a4567b89012345678d9e.json?summary=true..."

The letters after "...chart/" and preceding ".json?..." are your Basis user id! Note this string.

### Finding Your Basis access_token
In the same developer tools screen, click on the "Resources" tab. On the left hand side, click the arrow next to "Cookies", and then click "app.mybasis.com". In the table on the right, there will be an entry in the first column called "access_token". The value in the second column is your access token.

### Exporting Your Basis Data to Your Computer

- Set the $basis_userid variable in the script to your Basis user id from the previous step.
- Run the script from an executable location. The easiest way is to place it under your webserver document root, but CURL also works.
- By default, the script will export data from the previous day. You can specify the date you would like to export by appending "?date=YYYY-MM-DD" to the script URL (change YYYY-MM-DD to the actual date you would like to export)

![basis export step 5a](http://www.quantifiedbob.com/images/basis-screenshots/export5a.png)

![basis export step 5b](http://www.quantifiedbob.com/images/basis-screenshots/export5b.png)

- Your data will be saved in JSON format in the "data/" folder in the format "basis-data-YYYY-MM-DD.json"

![basis export step 6](http://www.quantifiedbob.com/images/basis-screenshots/export6.png)

- That's it! (for now).

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

  `curl http://localhost/basisdataexport.php?date=2013-05-[01-31]`




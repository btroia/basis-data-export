#!/bin/sh

now=`date +"%Y-%m-%d" -d $1`
end=`date +"%Y-%m-%d" -d $2`

while [ "$now" != "$end" ] ;
do
        echo "Downloading: "$now
        php ../basisdataexport.php -u[username] -p[password] -d$now -f[format]
        now=`date +"%Y-%m-%d" -d "$now + 1 day"`;
done

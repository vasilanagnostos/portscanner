#!/usr/bin/env bash
echo "You want to run the scan every minute(1), hour (2) or day(3)?"
read SCAN

if [ "$SCAN" == 1 ]; then
	crontab minute.txt
elif [ "$SCAN" == 2 ]; then
    	crontab hour.txt
elif [ "$SCAN" == 3 ]; then
    	crontab day.txt
else
	echo "Invalid option!"
fi
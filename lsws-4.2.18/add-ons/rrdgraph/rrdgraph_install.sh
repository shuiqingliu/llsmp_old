#!/bin/sh

#1. specify destination dir
#2. specify lsphp5 location
#3. download from litespeedtech web server(like lsapi)

cat <<EOF

RRDtool PHP package for litespeed

RRDtool is the OpenSource industry standard, high performance data 
logging and graphing system for time series data. Serverstats
(http://serverstats.berlios.de/) is a simple tool(in PHP) for creating 
graphs using rrdtool

This PHP package is Serverstats configured for litespeed.
it can save years' real-time stats data in litespeed and disply them 
in graph at any time.

Note: Before you install this php package, to be sure you've installed
	rrdtool. in Redhat/CentOS, you can do this through
	yum install rrdtool.i386 (for 32-bit x86) or
	yum install rrdtool.x86_64 (for 64-bit x86_64)

EOF

printf "%s" "Have you installed rrdtool [y/N]? "

read PHPACC
echo    

if [ "x$PHPACC" = "x" ]; then 
		PHPACC=n
fi      
if [ `expr "$PHPACC" : '[Yy]'` -eq 0 ]; then
		echo "then install rrdtool first !"
		exit 1
fi      

SUCC=0
DEST_RECOM="/usr/local/lsws/DEFAULT/html"
while [ $SUCC -eq "0" ];  do
	cat <<EOF

Please specify the destination directory. You must have permissions to 
create and manage the directory. It is recommended to install the rrd php package 
under document root of a virtual host.

EOF
	printf "%s" "Destination [$DEST_RECOM]: "
	read DEST_DIR
	echo ""
	if [ "x$DEST_DIR" = "x" ]; then
		DEST_DIR=$DEST_RECOM
	fi
	SUCC=1
	if [ ! -d "$DEST_DIR" ]; then
		mkdir -p "$DEST_DIR"
		if [ ! $? -eq 0 ]; then
			SUCC=0
			echo "Failed to create the directory, try again"
		fi
	fi
done

SUCC=0
PHP_RECOM="/usr/local/lsws/fcgi-bin/lsphp5"
while [ $SUCC -eq "0" ];  do
	cat <<EOF

Please specify the full path of lsphp5 binary. It is used to update rrd database
in cron job. php version must be 5.0 or above.

EOF
	printf "%s" "lsphp5 location: [$PHP_RECOM]"
	read PHP_BIN
	echo ""
	if [ "x$PHP_BIN" = "x" ]; then
		PHP_BIN=$PHP_RECOM
	fi
	SUCC=1
	if [ ! -f "$PHP_BIN" ]; then
		SUCC=0
		echo "$PHP_BIN not exist, please specify again"
	fi
done

cd $DEST_DIR
RRDPKG=ls_stats.tar.gz
if [ -f $DEST_DIR/ls_stats/graph.php ]; then 
	echo "[INFO] Found rrd php package at $DEST_DIR/ls_stats/"
else
	if [ ! -f $DEST_DIR/$RRDPKG ]; then 
		wget --timeout=5 -O $RRDPKG "http://www.litespeedtech.com/packages/rrdtool/$RRDPKG"
		if [ $? -ne 0 ]; then 
			echo "WGET failed, try curl"
			curl -L "http://www.litespeedtech.com/packages/rrdtool/$RRDPKG" -o $RRDPKG
			if [ $? -ne 0 ]; then
				cat <<EOF

[ERROR] Failed to download rrd php package, please download
        it manually from http://www.litespeedtech.com/packages/rrdtool/$RRDPKG to 
        '$DEST_DIR' directory, then expand the package there.

EOF
				exit 1
			fi              
		fi              
	fi              
	gunzip -c $RRDPKG | tar xf -
	if [ $? -ne 0 ]; then
		cat <<EOF
[ERROR] Failed to expand $DEST_DIR/$RRDPKG
                Please expand it manually.

EOF
		exit 1
	fi

fi
cat <<EOF

final step: create a cron job to update web server stats 1 time / per minute.
run "crontab -e", add following line:
* * * * * $PHP_BIN $DEST_DIR/ls_stats/update.php

to view the rrd graph at any time later, access
http://virtual-host-domain/ls_stats/index.php

[OK] rrd php package for litespeed has been successfully installed. 

EOF

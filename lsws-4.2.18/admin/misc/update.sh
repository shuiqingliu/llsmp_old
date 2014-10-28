
CURDIR=`dirname "$0"`
cd $CURDIR
CURDIR=`pwd`
LSWSHOME=`dirname $CURDIR`
LSWSHOME=`dirname $LSWSHOME`

LOGFILE=$LSWSHOME/autoupdate/update.log
CURTIME=`date "+[%Y-%m-%d %k:%M:%S]"`


test_license()
{
	if [ -f "$LSWSHOME/conf/serial.no" ]; then
		cp "$LSWSHOME/conf/serial.no" "./serial.no"
	fi
	if [ -f "$LSWSHOME/conf/trial.key" ] && [ ! -f "./trial.key" ]; then
		cp "$LSWSHOME/conf/trial.key" "./trial.key"
	fi
    if [ -f "./serial.no" ]; then
        bin/lshttpd -r
    fi
	if [ -f "./license.key" ] && [ -f "./serial.no" ]; then
		output=`bin/lshttpd -t`
		if [ $? -ne 0 ]; then
		    echo $output >> $LOGFILE
	            echo "$CURTIME [ERROR] License key verification failed" >> $LOGFILE
	            exit 1
		fi
	fi
}


if [ "x$3" = "x" ]; then
	
	cat <<EOF
Usage: update.sh VERSION EDITION PLATFORM
  Upgrade to another version of LiteSpeed web server. the package file must 
  exist under $LSWSHOME/autoupdate/
  Package file should be lsws-VERSION-EDITION-PLATFORM.tar.gz

EOF
	exit 1
fi

cd $LSWSHOME/autoupdate/

echo "$CURTIME Extracting package file" >> $LOGFILE

OS=`uname -s`
PLATFORM=$3
if [ "x$OS" = "xFreeBSD" ]; then

    freebsd_ver=`uname -v`
    major_v=`expr "$freebsd_ver" : "FreeBSD [1,6-9]"`
    if [ "x$major_v" = "x9" ]; then
        SIX=6
        PLATFORM="$PLATFORM$SIX"
    fi
fi

PACKAGEFILE=$LSWSHOME/autoupdate/lsws-$1-$2-$PLATFORM.tar.gz
if [ -f $PACKAGEFILE ]; then
	gunzip -c $PACKAGEFILE | tar xf -
else
	echo "$CURTIME [ERROR] Package file $PACKAGEFILE does not exist." >> $LOGFILE
	exit 1
fi

cd $LSWSHOME/autoupdate/lsws-$1
if [ $? -ne 0 ]; then
	echo "$CURTIME [ERROR] Failed to change current directory to $LSWSHOME/autoupdate/lsws-$1" >> $LOGFILE
	exit 1
fi

source ./functions.sh
if [ $? != 0 ]; then
    . ./functions.sh
    if [ $? != 0 ]; then
        echo "$CURTIME [ERROR] Can not include 'functions.sh'." >> $LOGFILE
        exit 1
    fi
fi

init
LSWS_HOME=$LSWSHOME
INTSTALL_TYPE="upgrade"
readCurrentConfig

if [ $2 = 'ent' ] || [ $2 = 'pro' ]; then
    test_license
fi

installation
echo "$CURTIME Upgrade to $1 successfully." >> $LOGFILE

rm -rf $LSWSHOME/autoupdate/lsws-$1
rm -f $PACKAGEFILE

exit 0


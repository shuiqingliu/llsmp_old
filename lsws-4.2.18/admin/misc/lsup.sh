
CURDIR=`dirname "$0"`
cd $CURDIR
CURDIR=`pwd`
LSWSHOME=`dirname $CURDIR`
LSWSHOME=`dirname $LSWSHOME`


display_usage()
{
	cat <<EOF
Usage: lsup.sh [-f] [-v VERSION]
  
  -f 
     Force reinstall. If -f is not given and same version already installed, this upgrade command is aborted. With -f option, latest build will be downloaded and installed even within same release version.

  -v VERSION
     If VERSION is given, this command will try to install specified VERSION. Otherwise, it will get the latest version from $LSWSHOME/autoupdate/release.

  --help     display this help and exit

EOF
	exit 1
}

FORCED=N
VERSION=x
while [ "x$1" != "x" ] 
do
    if [ "x$1" = "x-f" ] ; then
	FORCED=Y
    elif [ "x$1" = "--help" ] ; then
	display_usage
    elif [ "x$1" = "x-v" ] ; then
	shift
	VERSION=$1
	if [ "x$VERSION" = "x" ] ; then
	    display_usage
	fi
    else
	display_usage
    fi
    shift;
done


# detect download method
OS=`uname -s`
DLCMD=x
if [ "x$OS" = "xFreeBSD" ] ; then
    DL=`which fetch`
    if [ $? -eq 0 ] ; then
	DLCMD="$DL -o"
    fi
fi
if [ "$DLCMD" = "x" ] ; then
    DL=`which wget`
    if [ $? -eq 0 ] ; then
	DLCMD="$DL -nv -O"
    fi
fi
if [ "$DLCMD" = "x" ] ; then
    DL=`which curl`
    if [ $? -eq 0 ] ; then
	DLCMD="$DL -L -o"
    fi
fi
if [ "$DLCMD" = "x" ] ; then
    echo "[ERROR] Fail to detect proper download method"
    exit 1
fi

# check latest release
$LSWSHOME/bin/lshttpd -U

if [ ! -f "$LSWSHOME/autoupdate/release" ] ; then
    echo "[ERROR] Fail to locate file $LSWSHOME/autoupdate/release"
    exit 1
fi

if [ ! -f "$LSWSHOME/autoupdate/platform" ] ; then
    echo "[ERROR] Fail to locate file $LSWSHOME/autoupdate/platform"
    exit 1
fi

RELEASE=`cat $LSWSHOME/autoupdate/release`
PLATFORM=`cat $LSWSHOME/autoupdate/platform`
EDITION=`expr $RELEASE : '.*-\(.*\)'`
if [ $VERSION = "x" ] ; then
    VERSION=`expr $RELEASE : '\(.*\)-'`
else
    RELEASE="$VERSION-$EDITION"
fi

CURVERSION=`cat $LSWSHOME/VERSION`
if [ "$VERSION" = "$CURVERSION" ] && [ "$FORCED" = "N" ] ; then
    echo "[ERROR] Abort - Same version already installed. If you want to do force reinstall, please use option -f"
    exit 1
fi

FILENAME="lsws-$RELEASE-$PLATFORM.tar.gz"
if [ -e "$LSWSHOME/autoupdate/$FILENAME" ] ; then
    /bin/rm -f "$LSWSHOME/autoupdate/$FILENAME"
fi

PACKAGEFILE=$LSWSHOME/autoupdate/lsws-$1-$2-$3.tar.gz


MAJOR_VERSION=`expr $RELEASE : '\([0-9]*\)\..*'`
 
# http://www.litespeedtech.com/packages/3.0/lsws-3.3.12-ent-i386-linux.tar.gz
DOWNLOAD_URL="http://www.litespeedtech.com/packages/$MAJOR_VERSION.0/$FILENAME"

echo "$DLCMD $LSWSHOME/autoupdate/$FILENAME $DOWNLOAD_URL"

$DLCMD "$LSWSHOME/autoupdate/$FILENAME" $DOWNLOAD_URL

echo "$LSWSHOME/admin/misc/update.sh $VERSION $EDITION $PLATFORM"
$LSWSHOME/admin/misc/update.sh $VERSION $EDITION $PLATFORM

echo "Restarting LSWS"
$LSWSHOME/bin/lswsctrl restart
echo "Done"

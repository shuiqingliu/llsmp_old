
removeit()
{
	FILEPATH=$LSWSHOME/$LSFILE.$NEWVER
	if [ -f $FILEPATH ] || [ -d $FILEPATH ]; then
		echo "Removing $FILEPATH"
		rm -rf $FILEPATH
	else
		echo "$FILEPATH does not exist."
	fi
}

testit()
{
	FILEPATH=$LSWSHOME/$LSFILE.$NEWVER
	if [ ! -f $FILEPATH ] && [ ! -d $FILEPATH ] ; then
		echo "[ERROR] $FILEPATH does not exist, please run installer again."
		exit 3
	fi
}

switchit()
{
	FILEPATH=$LSWSHOME/$LSFILE
	rm -f $FILEPATH
    FILENAME=`basename $FILEPATH`
    ln -sf "./$FILENAME.$NEWVER" "$FILEPATH"
}

CURDIR=`dirname "$0"`
cd $CURDIR
CURDIR=`pwd`
LSWSHOME=`dirname $CURDIR`
LSWSHOME=`dirname $LSWSHOME`


if [ "x-d" = "x$1" ]; then
	ACTION="del"
	shift
fi

if [ "x$1" = "x" ]; then
	
	cat <<EOF
Usage: mgr_ver.sh [-d] VERSION
  Switch to another version of LiteSpeed web server, or remove files installed.

Option:
  -d		Delete files installed for the version specified

EOF
	exit 1
else
	NEWVER=$1
fi

FILES="bin/lshttpd bin/lscgid bin/lswsctrl admin/html"


OLDVER=`cat $LSWSHOME/VERSION`

if [ "x$ACTION" = "xdel" ]; then
	if [ "x$OLDVER" = "x" ]; then
		echo "[ERROR] Can not find $LSWSHOME/VERSION, Please run"
		echo "mgr_ver.sh without '-d' option first to confirm the version to be used."
		exit 1
	elif [ "x$OLDVER" = "x$NEWVER" ]; then
		echo "[ERROR] Version: $NEWVER is in used, please switch to another verion first."
		exit 1
	else
		for LSFILE in $FILES 
		  do
		  removeit
		done
		exit 2
	fi		
fi

for LSFILE in $FILES 
  do
  testit
done

for LSFILE in $FILES
  do
  switchit
done

ln -sf "./lscgid.$NEWVER" "$LSWSHOME/bin/httpd"

echo "$NEWVER" > $LSWSHOME/VERSION
exit 0


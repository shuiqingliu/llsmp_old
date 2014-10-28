
CURDIR=`dirname "$0"`
cd $CURDIR
CURDIR=`pwd`
LSWSHOME=`dirname $CURDIR`
LSWSHOME=`dirname $LSWSHOME`


if [ "x$1" = "x" ]; then
	
	cat <<EOF
Usage: $0 <path_to_apache_binary> [0|1]

  the first parameter is the path to Apache httpd binary,
  The second parameter is optional, which control whether to run Apache 
     and LSWS at the same time. Default is '0' - No.

EOF
	exit 1
fi

if [ "x$2" = "x1" ]; then
    CTRL_APACHE=1
else
    CTRL_APACHE=0
fi


sed -e "s:%LSWS_HOME%:$LSWSHOME:" \
    -e "s:%APACHE_BIN%:$1:" \
    -e "s:%CTRL_APACHE%:$CTRL_APACHE:" "./ap_lsws.sh.in" > "./ap_lsws.sh"

chmod 0755 ./ap_lsws.sh



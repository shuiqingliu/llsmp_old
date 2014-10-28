
CURDIR=`dirname "$0"`
cd $CURDIR
CURDIR=`pwd`
LSWSHOME=`dirname $CURDIR`
LSWSHOME=`dirname $LSWSHOME`

if [ "x$1" = "x" ]; then
	cat <<EOF	
Usage: apimport.sh <path_to_apach_httpd_conf>

  Import Apache configuration from specified configuration file.

EOF

#	cat <<EOF
#Usage: apimport.sh <path_to_apach_httpd_conf> [<vhost_name>]
#
#  Import Apache configuration from specified configuration file
#  When <vhost_name> is specified, only configuration for that virtual host
#  will be imported.
#
#EOF
	exit 1
fi 

if [ ! -f $1 ]; then
	cat <<EOF	
[ERROR] Apache configruation file does not exist: $1.
EOF
	exit 1
fi

$CURDIR/../fcgi-bin/admin_php -q $CURDIR/apimport/ApacheMigration.php $1 $LSWSHOME $2


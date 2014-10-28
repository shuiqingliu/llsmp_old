

INST_USER=`id`
INST_USER=`expr "$INST_USER" : 'uid=.*(\(.*\)) gid=.*'`
if [ $INST_USER != "root" ]; then
	cat <<EOF
[ERROR] Only root user can install FrontPage server extension!
EOF
	exit 1
fi

if [ "x$1" = "x" ]; then
	echo "Usage: fp_install.sh <path_to_frontpage_package>"
	exit 2
fi

CURDIR=`pwd`

PKG_BASE=`dirname $1`
cd $PKG_BASE
PKG_BASE=`pwd`

PATH_FP_PKG=$PKG_BASE/`basename $1`

if [ ! -f $PATH_FP_PKG ]; then
	echo "[ERROR] File not found: $PATH_FP_PKG"
	exit 3
fi

cd $CURDIR

CURDIR=`dirname "$0"`
cd $CURDIR
CURDIR=`pwd`
LSWSHOME=`dirname $CURDIR`
LSWSHOME=`dirname $LSWSHOME`


ENABLE_CHROOT=0
CHROOT_PATH="/"
if [ -f "$LSWS_HOME/conf/httpd_config.xml" ]; then
	OLD_ENABLE_CHROOT_CONF=`grep "<enableChroot>" "$LSWS_HOME/conf/httpd_config.xml"`
	OLD_CHROOT_PATH_CONF=`grep "<chrootPath>" "$LSWS_HOME/conf/httpd_config.xml"`
	OLD_ENABLE_CHROOT=`expr "$OLD_ENABLE_CHROOT_CONF" : '.*<enableChroot>\(.*\)</enableChroot>.*'`
	OLD_CHROOT_PATH=`expr "$OLD_CHROOT_PATH_CONF" : '[^<]*<chrootPath>\([^<]*\)</chrootPath>.*'`
	if [ "x$OLD_ENABLE_CHROOT" != "x" ]; then
		ENABLE_CHROOT=$OLD_ENABLE_CHROOT
	fi
	if [ "x$OLD_CHROOT_PATH" != "x" ]; then
		CHROOT_PATH=$OLD_CHROOT_PATH
	fi
fi

if [ $ENABLE_CHROOT -eq 0 ]; then
	CHROOT_PATH=""
fi

cd $CHROOT_PATH/usr/local

tar xvfz $PATH_FP_PKG

chmod a+rx frontpage frontpage/version5.0 
cd frontpage/version5.0

chmod a+rx admin bin help nls admin/1033 bin/owsadm.exe help/1033

chmod -R a+rx exes
chmod a-x exes/_vti_bin/images/*

chmod a+r admin/1033/* bin/*  help/1033/* nls/*

if [ "x$CHROOT_PATH" != "x" ]; then
	ln -s $CHROOT_PATH/usr/local/frontpage /usr/local/frontpage
fi


#!/bin/bash

clear
echo "========================================================================="
echo "Google Perf Tools Installation for LLsMP 1.0 Written by llsmp.cn"
echo "========================================================================="
echo "LLsMP is A tool to auto-compile & install Litespeed+MySQL+PHP on Linux "
echo ""
echo "For more information please visit http://llsmp.cn/"
echo "========================================================================="
echo ""

	get_char()
	{
	SAVEDSTTY=`stty -g`
	stty -echo
	stty cbreak
	dd if=/dev/tty bs=1 count=1 2> /dev/null
	stty -raw
	stty echo
	stty $SAVEDSTTY
	}
	echo ""
	echo "Press any key to start installation or CTRL+C to cancel."
	char=`get_char`
echo "========================================================================="
echo "Installing..."
echo "========================================================================="

bit=$(getconf LONG_BIT)
if [ "$bit" = "64" ]; then
mkdir /tmp/llsmp
cd /tmp/llsmp
wget http://down.llsmp.cn/files/1.0/libunwind-1.1.tar.gz
tar zxvf libunwind-1.1.tar.gz
rm -f libunwind-1.1.tar.gz
cd libunwind-1.1
CFLAGS=-fPIC ./configure
make CFLAGS=-fPIC
make CFLAGS=-fPIC install
make
make install
fi

mkdir /tmp/llsmp
cd /tmp/llsmp
wget http://down.llsmp.cn/files/1.0/gperftools-2.1.tar.gz
tar zxvf gperftools-2.1.tar.gz
rm -f gperftools-2.1.tar.gz
cd gperftools-2.1
./configure
make && make install
echo "/usr/local/lib" > /etc/ld.so.conf.d/usr_local_lib.conf
/sbin/ldconfig
 

if [ -f /usr/local/mysql/bin/mysqld_safe ];then
sed -i '/executing mysqld_safe'/a\ "export LD_PRELOAD=\/usr\/local\/lib\/libtcmalloc.so" /usr/local/mysql/bin/mysqld_safe
else
sed -i '/executing mysqld_safe'/a\ "export LD_PRELOAD=\/usr\/local\/lib\/libtcmalloc.so" /usr/bin/mysqld_safe
fi
/etc/init.d/mysql restart
echo "========================================================================="
echo "Done"
echo "========================================================================="
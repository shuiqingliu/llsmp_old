#!/bin/bash

clear
echo "========================================================================="
echo "ionCube Installation for LLsMP 0.6 Written by llsmp.cn"
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
mkdir /tmp/llsmp
cd /tmp/llsmp

if [ -f /usr/local/lsws/fcgi-bin/lsphp-5.3.* ];then
bit=$(getconf LONG_BIT)
if [ "$bit" = "64" ]; then
wget -c http://downloads2.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64.tar.gz
tar zxvf ioncube_loaders_lin_x86-64.tar.gz
else
wget -c http://downloads2.ioncube.com/loader_downloads/ioncube_loaders_lin_x86.tar.gz
tar zxvf ioncube_loaders_lin_x86.tar.gz
fi

mv ioncube/ioncube_loader_lin_5.3.so /usr/local/lsws/lsphp5/lib/php/extensions/no-debug-non-zts-20090626/ioncube_loader_lin_5.3.so
sed -i '/\[zend\]/i\\n\[ionCube\]\nzend_extension="/usr/local/lsws/lsphp5/lib/php/extensions/no-debug-non-zts-20090626/ioncube_loader_lin_5.3.so"\n' /usr/local/lsws/lsphp5/lib/php.ini
/etc/init.d/lsws restart

else

bit=$(getconf LONG_BIT)
if [ "$bit" = "64" ]; then
wget -c http://downloads2.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64.tar.gz
tar zxvf ioncube_loaders_lin_x86-64.tar.gz
else
wget -c http://downloads2.ioncube.com/loader_downloads/ioncube_loaders_lin_x86.tar.gz
tar zxvf ioncube_loaders_lin_x86.tar.gz
fi

mv ioncube/ioncube_loader_lin_5.2.so /usr/local/lsws/lsphp5/lib/php/extensions/no-debug-non-zts-20060613/ioncube_loader_lin_5.2.so
sed -i '/\[zend\]/i\\n\[ionCube\]\nzend_extension="/usr/local/lsws/lsphp5/lib/php/extensions/no-debug-non-zts-20060613/ioncube_loader_lin_5.2.so"\n' /usr/local/lsws/lsphp5/lib/php.ini
/etc/init.d/lsws restart

fi
echo "========================================================================="
echo "Done"
echo "========================================================================="
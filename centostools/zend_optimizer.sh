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
wget http://downloads.zend.com/guard/5.5.0/ZendGuardLoader-php-5.3-linux-glibc23-x86_64.tar.gz
tar xvf ZendGuardLoader-php-5.3-linux-glibc23-x86_64.tar.gz
cp -f ZendGuardLoader-php-5.3-linux-glibc23-i386/php-5.3.x/ZendGuardLoader.so /usr/local/lsws/lsphp5/lib/php/extensions/no-debug-non-zts-20090626/
else
wget http://downloads.zend.com/guard/5.5.0/ZendGuardLoader-php-5.3-linux-glibc23-i386.tar.gz
tar xvf ZendGuardLoader-php-5.3-linux-glibc23-i386.tar.gz
cp -f ZendGuardLoader-php-5.3-linux-glibc23-i386/php-5.3.x/ZendGuardLoader.so /usr/local/lsws/lsphp5/lib/php/extensions/no-debug-non-zts-20090626/
fi
sed -i '/\[zend\]/a\\zend_optimizer.optimization_level=1\nzend_extension="/usr/local/lsws/lsphp5/lib/php/extensions/no-debug-non-zts-20090626/ZendGuardLoader.so"\n' /usr/local/lsws/lsphp5/lib/php.ini
/etc/init.d/lsws restart

else

bit=$(getconf LONG_BIT)
if [ "$bit" = "64" ]; then
wget http://downloads.zend.com/optimizer/3.3.9/ZendOptimizer-3.3.9-linux-glibc23-x86_64.tar.gz
tar xvf ZendOptimizer-3.3.9-linux-glibc23-x86_64.tar.gz
cp -f ZendOptimizer-3.3.9-linux-glibc23-x86_64/data/5_2_x_comp/ZendOptimizer.so /usr/local/lsws/lsphp5/lib/php/extensions/no-debug-non-zts-20060613/
else
wget http://downloads.zend.com/optimizer/3.3.9/ZendOptimizer-3.3.9-linux-glibc23-i386.tar.gz
tar xvf ZendOptimizer-3.3.9-linux-glibc23-i386.tar.gz
cp -f ZendOptimizer-3.3.9-linux-glibc23-i386/data/5_2_x_comp/ZendOptimizer.so /usr/local/lsws/lsphp5/lib/php/extensions/no-debug-non-zts-20060613/
fi
sed -i '/\[zend\]/a\\zend_optimizer.optimization_level=1\nzend_extension="/usr/local/lsws/lsphp5/lib/php/extensions/no-debug-non-zts-20060613/ZendOptimizer.so"\n' /usr/local/lsws/lsphp5/lib/php.ini
/etc/init.d/lsws restart
fi
echo "========================================================================="
echo "Done"
echo "========================================================================="
#!/bin/bash

clear
echo "========================================================================="
echo "eAccelerator Installation for LLsMP 1.0 Written by llsmp.cn"
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
wget http://down.llsmp.cn/files/1.0/eaccelerator-0.9.6.1.tar.bz2
tar xvf eaccelerator-0.9.6.1.tar.bz2
mv eaccelerator-0.9.6.1 /usr/local/lsws/phpbuild/eaccelerator-0.9.6.1
cd /usr/local/lsws/phpbuild/eaccelerator-0.9.6.1
/usr/local/lsws/lsphp5/bin/phpize
./configure --enable-eaccelerator=shared --with-php-config=/usr/local/lsws/lsphp5/bin/php-config --with-eaccelerator-shared-memory
make
make install
chown -R lsadm:lsadm /usr/local/lsws/phpbuild/eaccelerator-0.9.6.1
mkdir -p /tmp/eaccelerator
chmod 777 /tmp/eaccelerator

else

wget http://down.llsmp.cn/files/1.0/eaccelerator-0.9.5.3.tar.bz2
tar xvf eaccelerator-0.9.5.3.tar.bz2
mv eaccelerator-0.9.5.3 /usr/local/lsws/phpbuild/eaccelerator-0.9.5.3
cd /usr/local/lsws/phpbuild/eaccelerator-0.9.5.3
/usr/local/lsws/lsphp5/bin/phpize
./configure --enable-eaccelerator=shared --with-php-config=/usr/local/lsws/lsphp5/bin/php-config --with-eaccelerator-shared-memory
make
make install
chown -R lsadm:lsadm /usr/local/lsws/phpbuild/eaccelerator-0.9.5.3
mkdir -p /tmp/eaccelerator
chmod 777 /tmp/eaccelerator
fi
e0='\[eaccelerator\]'
e1='extension="eaccelerator.so"'
e2='eaccelerator.shm_size="1"'
e3='eaccelerator.cache_dir="/tmp/eaccelerator"'
e4='eaccelerator.enable="1"'
e5='eaccelerator.optimizer="1"'
e6='eaccelerator.check_mtime="1"'
e7='eaccelerator.debug="0"'
e8='eaccelerator.filter=""'
e9='eaccelerator.shm_max="0"'
e10='eaccelerator.shm_ttl="3600"'
e11='eaccelerator.shm_prune_period="3600"'
e12='eaccelerator.shm_only="0"'
e13='eaccelerator.compress="1"'
e14='eaccelerator.compress_level="9"'
sed -i '/\[zend\]/i\\n'$e0'\n'$e1'\n'$e2'\n'$e3'\n'$e4'\n'$e5'\n'$e6'\n'$e7'\n'$e8'\n'$e9'\n'$e10'\n'$e11'\n'$e12'\n'$e13'\n'$e14'\n' /usr/local/lsws/lsphp5/lib/php.ini
/etc/init.d/lsws restart
echo "========================================================================="
echo "Done"
echo "========================================================================="
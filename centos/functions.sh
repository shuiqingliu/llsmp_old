#!/bin/bash

check_installed()
{
if [ ! -f "/root/llsmp/.installed" ];then
INSATLL_TYPE="INSTALL"
else
echo "You have installed LLsMP already.You need a fresh install system."
exit 1
fi
}

choose_package()
{
package_i(){
echo "Please choose which type of installation you want"
echo "[1]Full Installation"
echo "[2]Custom Installation"
printf "Please input the prefix number.(1 or 2):" ; read tmp_package
case_i
}
case_i()
{
case $tmp_package in
     1)
          printf "You have chosen Full Installation"
		  echo ""
		  package="1"
          ;;

     2)
          printf "You have chosen Custom Installation"
		  echo ""
		  package="2"
          ;;

     *)
          printf "Please enter 1 or 2!"
		  package_i
          ;;
esac
}
package_i
}

custominit()
{
echo "Custom Installation"
printf "Do you want to install MySQL?[y/n]" 
read mysql_i
echo ""

printf "Do you want to install PHP?[y/n]" 
read php_i
echo ""

if [ $php_i = "y" ]; then
printf "Do you want to add extra PHP Configure Parameters?[y/n]" 
read php_conf_i
echo ""

if [ $php_conf_i = "y" ]; then
printf "Please input the extra PHP Configure Parameters(by using space between each Parameters) : "
read php_conf
echo "The extra PHP Configure Parameters are $php_conf"
fi

fi

if [ $mysql_i = "y" ] && [ $php_i = "y" ];then
printf "Do you want to install phpMyAdmin?[y/n]" 
read phpmyadmin_i
echo ""
fi
echo "========================================================================="
}


init()
{
#set up	email
email="root@localhost.com"
	echo "Please input email:"
	printf "(Default email: root@localhost.com):" 
	read email
	echo ""
	if [ "$email" = "" ]; then
		email="root@localhost.com"
	fi
	echo "========================================================================="
	echo email="$email"
	echo "========================================================================="
	
#set up	username
username="admin"
	echo "Please input username:"
	printf "(Default username: admin):" 
	read username
	echo ""
	if [ "$username" = "" ]; then
		username="admin"
	fi
	echo "========================================================================="
	echo username="$username"
	echo "========================================================================="

password_i="0"	
while [ $password_i != "1" ]
do	
#set up	password
password="admin123"
	echo "Please input Litespeed and MySQL password(AT LEAST 6 CHARACTERS!!!!!):"
	printf "(Default password: admin123):" 
	read password
	echo ""
	if [ "$password" = "" ]; then
		password="admin123"
	fi
	echo "========================================================================="
	echo password="$password"
	echo "========================================================================="
password_i="1"
#check length of password
string=${#password}
	if [ "$string" -lt "6" ]; then
		echo "AT LEAST 6 CHARACTERS!!!!PLEASE RUN THE SCRIPT AGAIN!!!"
		password_i="0"
	fi
done
}

confirm(){

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
}

sync_time()
{
#Synchronization time
rm -rf /etc/localtime
ln -s /usr/share/zoneinfo/Asia/Shanghai /etc/localtime

yum install -y ntp
ntpdate -d cn.pool.ntp.org
date
}

install_packages()
{
#Install packeages
rpm -qa|grep  httpd
rpm -e httpd
rpm -qa|grep mysql
rpm -e mysql
rpm -qa|grep php
rpm -e php
yum -y remove httpd* mysql* php*
centosversion=$(cat /etc/redhat-release | grep -o [0-9] | sed 1q)
if [ "$centosversion" = "5" ];then
rpm -Uvh http://download.fedora.redhat.com/pub/epel/5/i386/epel-release-5-4.noarch.rpm
else
rpm -Uvh http://download.fedoraproject.org/pub/epel/6/i386/epel-release-6-5.noarch.rpm
fi
bit=$(getconf LONG_BIT)
if [ $bit = "64" ]; then
yum -y install glibc flex re2c bison gcc automake mhash-devel expect ruby autoconf213 libtool gcc-c++ libjpeg-devel libpng-devel libxml2-devel curl curl-devel libmcrypt-devel freetype-devel patch make mysql-server mysql-devel zlib-devel libtool-ltdl-devel
else
yum -y install flex re2c bison gcc automake mhash-devel expect ruby autoconf213 libtool gcc-c++ libjpeg-devel libpng-devel libxml2-devel curl curl-devel libmcrypt-devel freetype-devel patch make mysql-server mysql-devel zlib-devel libtool-ltdl-devel
fi
}

install_packages_without_mysql()
{
#Install packeages
rpm -qa|grep  httpd
rpm -e httpd
rpm -qa|grep mysql
rpm -e mysql
rpm -qa|grep php
rpm -e php
yum -y remove httpd* mysql* php*
yum -y remove httpd
centosversion=$(cat /etc/redhat-release | grep -o [0-9] | sed 1q)
if [ "$centosversion" = "5" ];then
rpm -Uvh http://download.fedora.redhat.com/pub/epel/5/i386/epel-release-5-4.noarch.rpm
else
rpm -Uvh http://download.fedoraproject.org/pub/epel/6/i386/epel-release-6-5.noarch.rpm
fi
bit=$(getconf LONG_BIT)
if [ $bit = "64" ]; then
yum -y install glibc flex re2c bison gcc automake mhash-devel expect ruby autoconf213 libtool gcc-c++ libjpeg-devel libpng-devel libxml2-devel curl curl-devel freetype-devel patch make libmcrypt-devel zlib-devel libtool-ltdl-devel
else
yum -y install flex re2c bison gcc automake mhash-devel expect ruby autoconf213 libtool gcc-c++ libjpeg-devel libpng-devel libxml2-devel curl curl-devel freetype-devel patch make libmcrypt-devel zlib-devel libtool-ltdl-devel
fi
}

install_litespeed()
{
#Download litespeed
mkdir /tmp/llsmp
cd /tmp/llsmp
wget $lsws_source
tar zxf $lsws
cd lsws-$lsws_ver
chmod +x functions.sh

#Install Litespeed
expect -c "
spawn /tmp/llsmp/lsws-4.1.3/install.sh
expect \"5RetHEgU10\"
send \"\r\"
expect \"5RetHEgU11\"
send \"$username\r\"
expect \"5RetHEgU12\"
send \"$password\r\"
expect \"5RetHEgU13\"
send \"$password\r\"
expect \"5RetHEgU14\"
send \"$email\r\"
expect \"5RetHEgU1\"
send \"\r\"
expect \"5RetHEgU2\"
send \"\r\"
expect \"5RetHEgU3\"
send \"80\r\"
expect \"5RetHEgU4\"
send \"\r\"
expect \"5RetHEgU5\"
send \"Y\r\"
expect \"5RetHEgU6\"
send \"\r\"
expect \"5RetHEgU7\"
send \"N\r\"
expect \"5RetHEgU8\"
send \"Y\r\"
expect \"5RetHEgU9\"
send \"Y\r\"
"
}

install_litespeed_without_php()
{
#Download litespeed
mkdir /tmp/llsmp
cd /tmp/llsmp
wget $lsws_source
tar zxf $lsws
cd lsws-$lsws_ver
chmod +x functions.sh

#Install Litespeed
expect -c "
spawn /tmp/llsmp/lsws-4.1.3/install.sh
expect \"5RetHEgU10\"
send \"\r\"
expect \"5RetHEgU11\"
send \"$username\r\"
expect \"5RetHEgU12\"
send \"$password\r\"
expect \"5RetHEgU13\"
send \"$password\r\"
expect \"5RetHEgU14\"
send \"$email\r\"
expect \"5RetHEgU1\"
send \"\r\"
expect \"5RetHEgU2\"
send \"\r\"
expect \"5RetHEgU3\"
send \"80\r\"
expect \"5RetHEgU4\"
send \"\r\"
expect \"5RetHEgU5\"
send \"N\r\"
expect \"5RetHEgU7\"
send \"N\r\"
expect \"5RetHEgU8\"
send \"Y\r\"
expect \"5RetHEgU9\"
send \"Y\r\"
"
}

build_php()
{
#Build PHP 
export PHP_AUTOCONF=/usr/bin/autoconf-2.13
export PHP_AUTOHEADER=/usr/bin/autoheader-2.13
mkdir /usr/local/lsws/phpbuild
cd /tmp/llsmp
wget $php_52_source
wget $php_litespeed_source
wget $php_52_mail_header_patch_source
tar zxf $php_52
tar zxf $php_litespeed
cd /tmp/llsmp/php-$php_52_ver
patch -p1 < /tmp/llsmp/$php_52_mail_header_patch
mv /tmp/llsmp/litespeed /tmp/llsmp/php-$php_52_ver/sapi/litespeed/
cd /tmp/llsmp
mv php-$php_52_ver /usr/local/lsws/phpbuild
cd /usr/local/lsws/phpbuild/php-$php_52_ver
touch ac*
rm -rf autom4te.*
./buildconf --force

bit=$(getconf LONG_BIT)
if [ "$bit" = "64" ]; then
./configure '--prefix=/usr/local/lsws/lsphp5' '--with-libdir=lib64' '--with-pdo-mysql' '--with-mysql' '--with-mysqli' '--with-zlib' '--with-gd' '--enable-shmop' '--enable-sockets' '--enable-sysvsem' '--enable-sysvshm' '--enable-magic-quotes' '--enable-mbstring' '--with-iconv' '--with-litespeed' '--enable-inline-optimization' '--with-curl' '--with-curlwrappers' '--with-mcrypt' '--with-mhash' '--with-mime-magic' '--with-openssl' '--with-freetype-dir=/usr/lib' '--with-jpeg-dir=/usr/lib' '--enable-bcmath' $php_conf
else
./configure '--prefix=/usr/local/lsws/lsphp5' '--with-pdo-mysql' '--with-mysql' '--with-mysqli' '--with-zlib' '--with-gd' '--enable-shmop' '--enable-sockets' '--enable-sysvsem' '--enable-sysvshm' '--enable-magic-quotes' '--enable-mbstring' '--with-iconv' '--with-litespeed' '--enable-inline-optimization' '--with-curl' '--with-curlwrappers' '--with-mcrypt' '--with-mhash' '--with-mime-magic' '--with-openssl' '--with-freetype-dir=/usr/lib' '--with-jpeg-dir=/usr/lib' '--enable-bcmath' $php_conf
fi

make clean
echo `date`
make
make -k install
cd /usr/local/lsws/fcgi-bin
if [ -e "lsphp-$php_52_ver" ] ; then
	mv lsphp-$php_52_ver lsphp-$php_52_ver.bak
fi
cp /usr/local/lsws/phpbuild/php-$php_52_ver/sapi/litespeed/php lsphp-$php_52_ver
ln -sf lsphp-$php_52_ver lsphp5
chown -R lsadm:lsadm /usr/local/lsws/phpbuild/php-$php_52_ver
cp -f /usr/local/lsws/phpbuild/php-$php_52_ver/php.ini-dist /usr/local/lsws/lsphp5/lib/php.ini
sed -i '/extension_dir/d' /usr/local/lsws/lsphp5/lib/php.ini
sed -i '/sendmail_path/d' /usr/local/lsws/lsphp5/lib/php.ini
sed -i '/smtp_port/a\sendmail_path = \/usr\/sbin\/sendmail -t\n' /usr/local/lsws/lsphp5/lib/php.ini
echo "[zend]" >>/usr/local/lsws/lsphp5/lib/php.ini
mkdir -p /usr/local/lsws/lsphp5/lib/php/extensions/no-debug-non-zts-20060613
}


build_php_without_mysql()
{
#Build PHP 
export PHP_AUTOCONF=/usr/bin/autoconf-2.13
export PHP_AUTOHEADER=/usr/bin/autoheader-2.13
mkdir /usr/local/lsws/phpbuild
cd /tmp/llsmp
wget $php_52_source
wget $php_litespeed_source
wget $php_52_mail_header_patch_source
tar zxf $php_52
tar zxf $php_litespeed
cd /tmp/llsmp/php-$php_52_ver
patch -p1 < /tmp/llsmp/$php_52_mail_header_patch
mv /tmp/llsmp/litespeed /tmp/llsmp/php-$php_52_ver/sapi/litespeed/
cd /tmp/llsmp
mv php-$php_52_ver /usr/local/lsws/phpbuild
cd /usr/local/lsws/phpbuild/php-$php_52_ver
touch ac*
rm -rf autom4te.*
./buildconf --force

bit=$(getconf LONG_BIT)
if [ "$bit" = "64" ]; then
./configure '--prefix=/usr/local/lsws/lsphp5' '--with-libdir=lib64' '--with-pdo-mysql' '--with-mysql' '--with-mysqli' '--with-zlib' '--with-gd' '--enable-shmop' '--enable-sockets' '--enable-sysvsem' '--enable-sysvshm' '--enable-magic-quotes' '--enable-mbstring' '--with-iconv' '--with-litespeed' '--enable-inline-optimization' '--with-curl' '--with-curlwrappers' '--with-mcrypt' '--with-mhash' '--with-mime-magic' '--with-openssl' '--with-freetype-dir=/usr/lib' '--with-jpeg-dir=/usr/lib' '--enable-bcmath' $php_conf
else
./configure '--prefix=/usr/local/lsws/lsphp5' '--with-pdo-mysql' '--with-mysql' '--with-mysqli' '--with-zlib' '--with-gd' '--enable-shmop' '--enable-sockets' '--enable-sysvsem' '--enable-sysvshm' '--enable-magic-quotes' '--enable-mbstring' '--with-iconv' '--with-litespeed' '--enable-inline-optimization' '--with-curl' '--with-curlwrappers' '--with-mcrypt' '--with-mhash' '--with-mime-magic' '--with-openssl' '--with-freetype-dir=/usr/lib' '--with-jpeg-dir=/usr/lib' '--enable-bcmath' $php_conf
fi

make clean
echo `date`
make
make -k install
cd /usr/local/lsws/fcgi-bin
if [ -e "lsphp-$php_52_ver" ] ; then
	mv lsphp-$php_52_ver lsphp-$php_52_ver.bak
fi
cp /usr/local/lsws/phpbuild/php-$php_52_ver/sapi/litespeed/php lsphp-$php_52_ver
ln -sf lsphp-$php_52_ver lsphp5
chown -R lsadm:lsadm /usr/local/lsws/phpbuild/php-$php_52_ver
cp -f /usr/local/lsws/phpbuild/php-$php_52_ver/php.ini-dist /usr/local/lsws/lsphp5/lib/php.ini
sed -i '/extension_dir/d' /usr/local/lsws/lsphp5/lib/php.ini
sed -i '/sendmail_path/d' /usr/local/lsws/lsphp5/lib/php.ini
sed -i '/smtp_port/a\sendmail_path = \/usr\/sbin\/sendmail -t\n' /usr/local/lsws/lsphp5/lib/php.ini
echo "[zend]" >>/usr/local/lsws/lsphp5/lib/php.ini
mkdir -p /usr/local/lsws/lsphp5/lib/php/extensions/no-debug-non-zts-20060613
}

install_mysql()
{
#Mysql Setting
/etc/init.d/mysqld start
mysqladmin -u root password $password
mysql_version=$(mysql -V | awk '{ print $5 }' | awk -F "." '{print $1"."$2}')

if [ "$mysql_version" = "5.1" ]; then
mysql_subversion=$(mysql -V | awk '{ print $3 }' | awk -F "." '{print $2}')

if [ "$mysql_subversion" -ge 12 ];then
sed -i '/\[mysqld\]/a\skip-locking\nskip-innodb' /etc/my.cnf
else
sed -i '/\[mysqld\]/a\skip-locking\nskip-bdb\nskip-innodb' /etc/my.cnf
fi

else
sed -i '/\[mysqld\]/a\skip-locking\nskip-bdb\nskip-innodb' /etc/my.cnf

fi

/etc/init.d/mysqld restart
chkconfig --level 345 mysqld on
}

phpinfo()
{
#Download phpinfo
cd /tmp/llsmp
wget $phpinfo_source
tar zxf $phpinfo
rm -f /usr/local/lsws/DEFAULT/html/index.html
rm -f /usr/local/lsws/DEFAULT/html/phpinfo.php
mv -f $phpinfo_dir/* /usr/local/lsws/DEFAULT/html/
}

phpmyadmin()
{
#Download phpmyadmin
cd /tmp/llsmp
wget $phpmyadmin_source
tar zxf $phpmyadmin
mkdir /usr/local/lsws/DEFAULT/html/phpmyadmin
mv -f $phpmyadmin_dir/* /usr/local/lsws/DEFAULT/html/phpmyadmin
}

default_conf()
{
#Set conf
cd /tmp/llsmp
wget $default_conf_source
tar zxf $default_conf
rm -f /usr/local/lsws/DEFAULT/conf/*
mv $default_conf_dir/* /usr/local/lsws/DEFAULT/conf/
}

#Restart Litespeed
restart_lsws(){
/etc/init.d/lsws restart
}

check_llsmp_installed()
{
echo "========================================================================="
echo "Final Checking......"
if [ -f /usr/local/lsws/bin/litespeed ];then
echo "Litespeed Web Server [found]"
else
echo "Litespeed Web Server [not found]"
fi

if [ -f /usr/local/lsws/lsphp5/bin/php ];then
echo "PHP [found]"
else
echo "PHP [not found]"
fi

if [ -f /usr/bin/mysql ];then
echo "MySQL [found]"
else
echo "MySQL [not found]"
fi
}

llsmp_tool()
{
cd /tmp/llsmp
wget $centostools_source
tar zxf $centostools
mv $centostools_dir/* /root/llsmp/
}

finish()
{
echo "========================================================================="
echo "LLsMP has been set up."
echo "Please configure in the Litespeed control panel : http://<your_ip>:7080"
echo "========================================================================="
echo "For more information please visit http://llsmp.cn/"
echo "========================================================================="
echo "BYE~"
}

installed_file()
{
echo "LLsMP 0.6 CentOS" >> /root/llsmp/.installed
}
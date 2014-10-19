#!/bin/bash
echo $(date)
if [ -f ./sources ];then
source ./sources 2>/dev/null
else
wget http://down.llsmp.cn/sources
source ./sources 2>/dev/null
fi
if [ $? != 0 ]; then
    . ./sources
    if [ $? != 0 ]; then
        echo [ERROR] Can not include sources.
        exit 1
    fi
fi
clear
echo "========================================================================="
echo "LLsMP V0.6 for CentOS/RedHat Linux Written by llsmp.cn"
echo "========================================================================="
echo "A tool to auto-compile & install Litespeed+MySQL+PHP on Linux "
echo ""
echo "For more information please visit http://llsmp.cn/"
echo "========================================================================="

# Check if user is root
if [ $(id -u) != "0" ]; then
    echo "Error: You must be root to run this script, please login as root to install llsmp"
    exit 1
fi

cd `dirname "$0"`

if [ "$1" = "php5.3" ];then
source ./functions_php5.3.sh 2>/dev/null
if [ $? != 0 ]; then
    . ./functions_php5.3.sh
    if [ $? != 0 ]; then
        echo [ERROR] Can not include 'functions.sh'.
        exit 1
    fi
fi

else

source ./functions.sh 2>/dev/null
if [ $? != 0 ]; then
    . ./functions.sh
    if [ $? != 0 ]; then
        echo [ERROR] Can not include 'functions.sh'.
        exit 1
    fi
fi

fi

check_installed

if [ "$INSATLL_TYPE" == "INSTALL" ]; then
choose_package
fi

if [ "$package" == "1" ]; then
init
confirm
sync_time
install_packages
install_litespeed
build_php
install_mysql
phpinfo
phpmyadmin
default_conf
restart_lsws
llsmp_tool
check_llsmp_installed
finish
installed_file
fi

if [ "$package" = "2" ]; then
custominit
init
confirm
sync_time
	
	if [ "$mysql_i" != "y" ]; then
	install_packages_without_mysql
		else
		install_packages
		install_mysql
	fi
	
	if [ "$php_i" != "y" ] ; then
	install_litespeed_without_php
		else
		install_litespeed
	fi
	
	if [ "$php_i" == "y" ] && [ "$mysql_i" == "y" ]; then
	build_php
	fi
	
	if [ "$php_i" = "y" ] && [ "$mysql_i" != "y" ]; then
	build_php_without_mysql
	fi
	
phpinfo
	
	if [ "$phpmyadmin_i" == "y" ]; then
	phpmyadmin
	fi
	
default_conf
restart_lsws
llsmp_tool
check_llsmp_installed
finish
installed_file
fi

echo $(date)
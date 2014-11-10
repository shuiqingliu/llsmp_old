#!/bin/bash

# Check if user is root
if [ $(id -u) != "0" ]; then
    echo "Error: You must be root to run this script, use sudo sh $0"
    exit 1
fi

clear
echo "========================================================================="
echo "Create a Virtual Host for LLsMP V1.0,  Written by llsmp.cn "
echo "========================================================================="
echo "LLsMP is a tool to auto-compile & install Litespeed+MySQL+PHP on Linux "
echo "This script is a tool to Create virtual host for Litespeed "
echo "For more information please visit http://llsmp.cn/"
echo ""
echo "========================================================================="

#Domain name
domain="www.llsmp.cn"
echo "Please input domain:"
read -p "(Default domain: llsmp.cn):" domain
if [ "$domain" = "" ]; then
		domain="www.llsmp.cn"
	fi
if [ ! -f "/home/wwwroot/$domain/conf/vhconf.xml" ]; then
	echo "==========================="
	echo "domain=$domain"
	echo "===========================" 
	else
	echo "==========================="
	echo "$domain is exist!"
	echo "==========================="	
	exit 0
	fi


#More domain name
read -p "Do you want to add more domain name? (y/n)" add_more_domainame
	
if [ "$add_more_domainame" = 'y' ] || [ "$add_more_domainame" = 'Y' ]; then
	  echo "Please input domain name,example(www.llsmp.cn,blog.llsmp.cn,bbs.llsmp.cn)"
	  read -p "Please use \",\" between each domain:" moredomain
          echo "==========================="
          echo domain list="$moredomain"
          echo "==========================="
	  moredomainame=" $moredomain"
	fi
	
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
	echo "Press any key to start or CTRL+C to cancel."
	char=`get_char`
	

#Mkdir for vhost
mkdir /home/wwwroot
mkdir /home/wwwroot/$domain
mkdir /home/wwwroot/$domain/html
mkdir /home/wwwroot/$domain/conf
chown -R nobody /home/wwwroot/$domain/html
	
#add httpd conf Virtual host
cp /usr/local/lsws/conf/httpd_config.xml /usr/local/lsws/conf/httpd_config.xml.bak
v1="<virtualHost>"
v2="<name>$domain<\/name>"
v3="<vhRoot>\/home\/wwwroot\/$domain<\/vhRoot>"
v4='<configFile>$VH_ROOT\/conf\/vhconf.xml<\/configFile>'
v5="<note><\/note>"
v6="<allowSymbolLink>0<\/allowSymbolLink>"
v7="<enableScript>1<\/enableScript>"
v8="<restrained>1<\/restrained>"
v9="<maxKeepAliveReq><\/maxKeepAliveReq>"
v10="<smartKeepAlive><\/smartKeepAlive>"
v11="<setUIDMode>0<\/setUIDMode>"
v12="<staticReqPerSec><\/staticReqPerSec>"
v13="<dynReqPerSec><\/dynReqPerSec>"
v14="<outBandwidth><\/outBandwidth>"
v15="<inBandwidth><\/inBandwidth>"
v16="<\/virtualHost>"
vend="<\/virtualHostList>"
sed -i 's/'$vend'/'$v1'\n'$v2'\n'$v3'\n'$v4'\n'$v5'\n'$v6'\n'$v7'\n'$v8'\n'$v9'\n'$v10'\n'$v11'\n'$v12'\n'$v13'\n'$v14'\n'$v15'\n'$v16'\n&/' /usr/local/lsws/conf/httpd_config.xml

#add httpd conf listen
l1="<vhostMap>"
l2="<vhost>$domain<\/vhost>"
l3="<domain>$domain,$moredomain<\/domain>"
l4="<\/vhostMap>"
lend="<\/vhostMapList>"
sed -i 's/'$lend'/'$l1'\n'$l2'\n'$l3'\n'$l4'\n&/' /usr/local/lsws/conf/httpd_config.xml

#add vhost conf
cat >>/home/wwwroot/$domain/conf/vhconf.xml<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<virtualHostConfig>
  <docRoot>\$VH_ROOT/html/</docRoot>
  <adminEmails></adminEmails>
  <enableGzip>1</enableGzip>
  <index>
    <useServer>0</useServer>
    <indexFiles>index.htm,index.html,index.php</indexFiles>
    <autoIndex></autoIndex>
    <autoIndexURI></autoIndexURI>
  </index>
  <htAccess>
    <allowOverride>31</allowOverride>
    <accessFileName></accessFileName>
  </htAccess>
</virtualHostConfig>
EOF
chown -R lsadm:lsadm /home/wwwroot/$domain/conf
	
/etc/init.d/lsws restart
	
echo "========================================================================="
echo "The virtual host has been created"
echo "The path of the virtual host is /home/wwwroot/$domain/"
echo "Please upload the web files into /home/wwwroot/$domain/html"
echo "========================================================================="

#!/bin/bash
mkdir /root/llsmp
sh debian.sh $1 | tee install.log
cp install.log /root/llsmp/install.log
sed -i 's/$/<\br\>/g' install.log
cp install.log /usr/local/lsws/DEFAULT/html/installlog.html
sed -i '/email=/d' /usr/local/lsws/DEFAULT/html/installlog.html
sed -i '/username=/d' /usr/local/lsws/DEFAULT/html/installlog.html
sed -i '/password=/d' /usr/local/lsws/DEFAULT/html/installlog.html
sed -i '/5RetHEgU/d' /usr/local/lsws/DEFAULT/html/installlog.html
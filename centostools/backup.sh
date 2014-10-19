#!/bin/bash

# Check if user is root
if [ $(id -u) != "0" ]; then
    echo "Error: You must be root to run this script, use sudo sh $0"
    exit 1
fi

clear
echo "========================================================================="
echo "Backup script for LLsMP V0.6,  Written by llsmp.cn "
echo "========================================================================="
echo "LLsMP is a tool to auto-compile & install Litespeed+MySQL+PHP on Linux "
echo "This script is a tool to backup "
echo "For more information please visit http://llsmp.cn/"
echo ""
echo "========================================================================="

mkdir /root/llsmp/backup

#website file
echo "Which site would you backup?"
domain_list=$(cd /home/wwwroot/ && ls )
echo $domain_list | sed "s/ \{1,\}/\n/g"
printf "Please input the full domain:"
read domain

#mysql
printf "Do you want to backup mysql database?[y/n]"
read mysql_i
if [ $mysql_i = "y" ]; then
printf "Please input the root mysql password:"
read pd
echo "" 
printf "Which database would you backup?"
echo ""
mysql -uroot -p$pd -B -N -e 'SHOW DATABASES' | xargs | sed "s/ \{1,\}/\n/g"
printf "Please input the whole name of the database:"
read db
db_f=mysql_$(date +"%Y%m%d").sql
mysqldump --user=root -p$pd $db > /tmp/$db_f
fi


echo ""
cd /home/wwwroot
b_file=$domain_$(date +"%Y%m%d").tar.gz
tar zcf $b_file $domain 
mv $b_file /tmp

cd /tmp
llsmp_f=llsmp_$domain_$(date +"%Y%m%d").tar.gz
tar zcf $llsmp_f $b_file $db_f
mv $llsmp_f /root/llsmp/backup

echo "========================================================================="
echo "Done."
echo "The backup file is stored as /root/llsmp/backup/$llsmp_f"
echo "For more information please visit http://llsmp.cn/"
echo ""
echo "========================================================================="


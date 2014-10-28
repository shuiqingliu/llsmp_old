
CURDIR=`dirname "$0"`
cd $CURDIR
CURDIR=`pwd`
LSWSHOME=`dirname $CURDIR`
LSWSHOME=`dirname $LSWSHOME`

INST_USER=`id`
INST_USER=`expr "$INST_USER" : 'uid=.*(\(.*\)) gid=.*'`

AWS_VER=7.3


cd $LSWSHOME/add-ons

if [ -f $LSWSHOME/add-ons/awstats-$AWS_VER/wwwroot/cgi-bin/awstats.pl ]; then
	echo "[INFO] Found AWStats $AWS_VER at $LSWSHOME/add-ons/awstats-$AWS_VER."
else
	if [ ! -f $LSWSHOME/add-ons/awstats-$AWS_VER.tar.gz ]; then
		wget --timeout=5 "http://prdownloads.sourceforge.net/sourceforge/awstats/awstats-$AWS_VER.tar.gz"
		if [ $? -ne 0 ]; then
		    echo "WGET failed, try curl"
			curl -L "http://prdownloads.sourceforge.net/sourceforge/awstats/awstats-$AWS_VER.tar.gz" -o awstats-$AWS_VER.tar.gz
			if [ $? -ne 0 ]; then
				curl -L "http://prdownloads.sourceforge.net/sourceforge/awstats/awstats-$AWS_VER.tar.gz" -o awstats-$AWS_VER.tar.gz
				if [ $? -ne 0 ]; then
					cat <<EOF
[ERROR] Failed to download AWStats $AWS_VER package, please download  
        AWStats $AWS_VER package manually from http://www.awstats.org/ to 
        '$LSWSHOME/add-ons/' directory, then expand the package there.

EOF
					exit 1
				fi
			fi
		fi
	fi
	gunzip -c awstats-$AWS_VER.tar.gz | tar xf -
	if [ $? -ne 0 ]; then
		cat <<EOF
[ERROR] Failed to expand $LSWSHOME/add-ons/awstats-$AWS_VER.tar.gz. 
		Please expand it manually.

EOF
		exit 1
	fi

		
fi

DIRS=`find ./ -name awstats-$AWS_VER -type d`
chmod 0755 $DIRS
if [ $INST_USER = "root" ]; then
	chown -R root:root awstats-$AWS_VER
    if [ $? -ne 0 ]; then
        chown -R root:wheel awstats-$AWS_VER
    fi
fi

if [ -L $LSWSHOME/add-ons/awstats ]; then
	echo "[INFO] Removing old symbolic link."
	rm -f $LSWSHOME/add-ons/awstats
fi
echo "[INFO] Creating a symbolic link from './awstats-$AWS_VER' to './awstats'"
ln -sf ./awstats-$AWS_VER $LSWSHOME/add-ons/awstats

if [ $? -eq 0 ]; then
	cat <<EOF
[OK] AWStats $AWS_VER has been successfully installed as a litespeed 
     add-on module.

EOF
else
	cat <<EOF
[ERROR] Failed to create a symbolic link from './awstats-$AWS_VER' to 
        './awstats', please create it manually.
EOF
	exit 1
fi

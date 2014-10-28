#!/bin/sh

BASE_DIR=`dirname "$0"`
cd "$BASE_DIR"
BASE_DIR=`pwd`


while [ "1" -eq "1" ]; do

ERR=1
if [ -f /tmp/lshttpd/lshttpd.pid ]; then
    kill -0 `cat /tmp/lshttpd/lshttpd.pid` 2>/dev/null
    ERR=$?
fi

if [ $ERR -ne 0 ]; then
    sleep 10
    if [ -f /tmp/lshttpd/lshttpd.pid ]; then
        kill -0 `cat /tmp/lshttpd/lshttpd.pid` 2>/dev/null
        ERR=$?
    fi
fi
if [ $ERR -ne 0 ]; then
    sleep 10
    if [ -f /tmp/lshttpd/lshttpd.pid ]; then
        kill -0 `cat /tmp/lshttpd/lshttpd.pid` 2>/dev/null
        ERR=$?
    fi
fi
if [ $ERR -ne 0 ]; then
    sleep 10
    if [ -f /tmp/lshttpd/lshttpd.pid ]; then
        kill -0 `cat /tmp/lshttpd/lshttpd.pid` 2>/dev/null
        ERR=$?
    fi
fi

if [ $ERR -ne 0 ]; then
    ./lshttpd
    D=`date`
    echo "$D: LSWS stopped, start LSWS." >> $BASE_DIR/../logs/error.log
fi
sleep 2

done


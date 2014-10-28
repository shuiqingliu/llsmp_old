#!/bin/sh
if [ -f "/etc/cagefs/cagefs.mp" ] ; then
# cagefs installed first, need update mount point
    cagefsctl --create-mp
    cagefsctl --remount-all
    cagefsctl --update
fi


#!/bin/sh
#
# delete recordings older than $RECAGE days
#

RECAGE=`/usr/bin/sqlite3 /opt/sark/db/sark.db "select RECAGE from globals;"`

find /opt/sark/www/origrecs/recordings/*  -mtime +$RECAGE -type d -exec rm -rf {} +


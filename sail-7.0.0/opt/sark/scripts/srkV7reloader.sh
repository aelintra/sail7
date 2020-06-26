#!/bin/bash

SARKROOT=/opt/sark					#sark directory
DBROOT=/opt/sark/db					#db directory
SARKDB=sark.db						#updateable copy of the db
NEWDB=sark.newV7.db 				#new DB 
SARKRUNDB=sark.rdonly.db			#runtime copy of the db
LASTDB=sark.last.db					#sark db previous release
CLEANDB=sark.clean.db				#factory reset copy of the db 
CREATEDB=db_v7_create.sql			#installed db create
SYSTEMDB=db_v7_system.sql			#installed db system data
SYSMSGDB=db_v7_message.sql			#installed db system messages
SYSDEVICE=db_v7_device.sql			#installed db device table
SYSONCE=once						#once directory
SYSALWAYS=always					#always directory
SYSONCEDONE=oncedone				#applied once files
SIPFIX=/generator/srksipiaxfix.php 	#loc. of the V6 sipiaxfixup routine
GENAST=/scripts/srkgenAst			#loc. of the generator
HTTPOWNER=www-data:www-data			#default apache user/group (Debian/Ubuntu)

NEWINSTALL=true


#save existing db if it exists 
if [ -e $DBROOT/$SARKDB ]  ; then
	echo "Saving customer database as $DBROOT/last_sark.db"
	cp -a $DBROOT/$SARKDB $DBROOT/$LASTDB
	NEWINSTALL=false
else
	echo No customer database - not saving
fi

sqlite3 $DBROOT/$SARKDB 'PRAGMA synchronous=0;'
sqlite3 $DBROOT/$SARKDB 'PRAGMA journal_mode=MEMORY;' >/dev/null 2>&1

#create the db from the system files
echo Creating new database
sqlite3 $DBROOT/$NEWDB < $DBROOT/$CREATEDB

#Load the system data
echo Loading initial system data
sqlite3 $DBROOT/$NEWDB < $DBROOT/$SYSTEMDB
#Load the system messages 
if [ -e $DBROOT/$SYSMSGDB ]; then
	echo Loading system messages
	sqlite3 $DBROOT/$NEWDB < $DBROOT/$SYSMSGDB
fi
echo Loading system device data
sqlite3 $DBROOT/$NEWDB < $DBROOT/$SYSDEVICE

# run the merge routine to map the old DB to the new and merge the data
php /opt/sark/generator/srk7dbcvt.php

#run the once files
echo Running ONCE files..
if [ ! -e $SARKROOT/$SYSONCEDONE ] ; then
	echo Creating oncedone directory
	mkdir $SARKROOT/$SYSONCEDONE
fi

if [ "$(ls -A $SARKROOT/$SYSONCE)" ]; then
	for file in $(ls $SARKROOT/$SYSONCE/) ; do
		if [ ! -e $SARKROOT/$SYSONCEDONE/$file ]; then
			echo "Applying oncefile $file to the DB"
			sqlite3 $DBROOT/$NEWDB < $SARKROOT/$SYSONCE/$file
			cp -a $SARKROOT/$SYSONCE/$file $SARKROOT/$SYSONCEDONE/$file
		else 
			echo "Skipping oncefile $file because it is already applied"
		fi	
	done
else 
	echo "No ONCE files to apply (Directory is empty)"
fi

#run the always files
echo Running ALWAYS files..
if [ "$(ls -A $SARKROOT/$SYSALWAYS)" ]; then
	for file in $(ls $SARKROOT/$SYSALWAYS/) ; do
		echo "Applying alwaysfile $file to the DB"
		sqlite3 $DBROOT/$NEWDB < $SARKROOT/$SYSALWAYS/$file
	done
else 
	echo "No ALWAYS files to apply (Directory is empty)"
fi

sqlite3 $DBROOT/$SARKDB 'PRAGMA synchronous=1;'
sqlite3 $DBROOT/$SARKDB 'PRAGMA journal_mode=DELETE;' >/dev/null 2>&1

# save a copy of the original installed database (for factory reset)
[ "$NEWINSTALL" = true ] && cp $DBROOT/$SARKDB $DBROOT/$CLEANDB

#patch sipiaxfrend for nat and transport here using sipiaxfix
echo Running V6 extension fixup
php $SARKROOT$SIPFIX

# run the generator
echo Running the Generator
sh $SARKROOT$GENAST

#set db ownership
chown $HTTPOWNER $DBROOT/*

#set db perms 
chmod 664 $DBROOT/$SARKDB

# clean the firewall up
echo Running firewall sanitizer
php /opt/sark/generator/sanitize-firewall.php
echo Firewall rules are as follows
cat /etc/shorewall/sark_rules
echo running firewall check
if sudo /sbin/shorewall check ;then
	echo "\nfirewall rules checked out OK...\n\n"
else
	echo "\nfirewall check failed with errors.  \nYou must correct the firewall rules!\n\n"
fi


<?php
// +-----------------------------------------------------------------------+
// |  Copyright (c) CoCoSoft 2005-10                                  |
// +-----------------------------------------------------------------------+
// | This file is free software; you can redistribute it and/or modify     |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation; either version 2 of the License, or     |
// | (at your option) any later version.                                   |
// | This file is distributed in the hope that it will be useful           |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of        |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the          |
// | GNU General Public License for more details.                          |
// +-----------------------------------------------------------------------+
// | Author: CoCoSoft                                                           |
// +-----------------------------------------------------------------------+
// 

include("localvars.php");

$custTables = array(
	"Agent",			
	"Appl",
	"COS",
	"Cluster",
	"Device",
	"Globals",
	"Holiday",
	"Ipphone",
	"Ipphonecosclosed",
	"Ipphonecosopen",
	"ipphone_Fkey",
	"Queue",
	"Route",
	"User",
	"Dateseg",
	"Ivrmenu",
	"Lineio",
	"Meetme",
	"Speed"
);

	$v7db = 'sqlite:/opt/sark/db/sark.newV7.db';
	$v7custdata = '/opt/sark/db/v7custdata.sql';	

	if ( $argc == 2) {
		if (file_exists($argv[1])) {
			$sarkdb = 'sqlite:' . $argv[1]; 
		}
	}
		
    /*** connect to SQLite databases, old and new ***/
    try {
		$dbh = new PDO($sarkdb);
	}
	catch (Exception $e) {
		echo "Oops failed to open DB $sarkdb" . " $e\n";
		exit(4);
	}

    try {
		$v7dbh = new PDO($v7db);
	}
	catch (Exception $e) {
		echo "Oops failed to open DB $v7db" . " $e\n";
		exit(4);
	}

    /*** set the error reporting attribute ***/
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/*
 * Delete the global row from the new DB
 */

	$res = $v7dbh->query("delete from globals");
    
/*
 *  Copy/merge the two db's
 */
	$insertfile = '';
	foreach ($custTables as $table) {
/*
 *  Build an intersection of column names
 */ 
		$oldtablecols = $dbh->query("PRAGMA table_info($table)")->fetchall(PDO::FETCH_ASSOC);
		$oldtablecolumnnames = array(); 
		foreach ($oldtablecols as $col) {
			if (preg_match(' /^z_/ ', $col['name'])) {
				continue;
			}
			if (preg_match(' /^id/ ', $col['name'])) {
				continue;
			} 			 
			$oldtablecolumnnames[] = $col['name'];
		}

		$newtablecols = $v7dbh->query("PRAGMA table_info($table)")->fetchall(PDO::FETCH_ASSOC);
		$newtablecolumnnames = array();
		foreach ($newtablecols as $col) {
			if (preg_match(' /^z_/ ', $col['name'])) {
				continue;
			} 
			$newtablecolumnnames[] = $col['name'];
		}

		$columnlist = '';

		foreach ($newtablecolumnnames as $col) {
			$commoncol = null;
			$commoncol = array_search($col, $oldtablecolumnnames);
			if ($commoncol === false) {
				continue;
			}
			if (preg_match(' /^id/ ', $col)) {
				continue;
			}
			$columnlist .=  $col . ',';			 
		}
		$columnlist  = rtrim($columnlist , ',');

/*
 *  Fetch the qualifying old data.  Device is a special case.   Only bring customer 
 */
		if ($table == 'Device') {
			$count = $dbh->query("select count(*) from device  WHERE owner != 'system'")->fetchColumn();
			if ($count) {
				$oldtable = $dbh->query("select $columnlist from $table WHERE owner != 'system'")->fetchall(PDO::FETCH_ASSOC);
			}
			else {
				continue;
			}
		}
		else {
			$count = $dbh->query("select count(*) from $table")->fetchColumn();
			if ($count) {
				$oldtable = $dbh->query("select $columnlist from $table")->fetchall(PDO::FETCH_ASSOC);
			}
			else {
				continue;
			}	
		}

		$valuelist = '';
		foreach ($oldtable as $row) {
			foreach ($row as $key=>$val) {
				$valuelist .= "'" . $val . "',";
			}
			$valuelist  = rtrim($valuelist , ',');
			$insertfile .= "INSERT OR IGNORE INTO $table ($columnlist) values ($valuelist);\n";
			$res = $v7dbh->query("INSERT OR IGNORE INTO $table ($columnlist) values ($valuelist)");
			$valuelist = '';	
		}
	}
/*
 *  write the INSERT file
 */
	$fh = fopen($v7custdata, 'w') or die('Could not open file!');
	fwrite($fh,$insertfile) or die('Could not write v7 insertfile to file');
	fclose($fh);

/*
 * Parse the new ivr and queue tables and fixup the directdials
 */

	$res = NULL;

	$table = $v7dbh->query("select * from ivrmenu")->fetchall(PDO::FETCH_ASSOC);

   	foreach ($table as $row ) {
   		if (empty($row['directdial'])) {
   			$res = $v7dbh->query("SELECT MAX(directdial+1) FROM ivrmenu WHERE cluster = '" . $row['cluster'] . "'")->fetch(PDO::FETCH_COLUMN);
   			if (empty($res)) {
   				$res = $v7dbh->query("SELECT startivr FROM cluster WHERE pkey = '" . $row['cluster'] . "'")->fetch(PDO::FETCH_COLUMN);
   			}
   			$sql = $v7dbh->prepare("UPDATE ivrmenu SET directdial = ? WHERE id = ?");
   			$sql->execute(array($res,$row['id']));
   			$res = NULL;
   		}

   	}

   	$res = NULL;
   	$table = NULL;

   	$table = $v7dbh->query("select * from queue")->fetchall(PDO::FETCH_ASSOC);

   	foreach ($table as $row ) {
   		if (empty($row['directdial'])) {
   			$res = $v7dbh->query("SELECT MAX(directdial+1) FROM queue WHERE cluster = '" . $row['cluster'] . "'")->fetch(PDO::FETCH_COLUMN);
   			if (empty($res)) {
   				$res = $v7dbh->query("SELECT startqueue FROM cluster WHERE pkey = '" . $row['cluster'] . "'")->fetch(PDO::FETCH_COLUMN);
   			}
   			$sql = $v7dbh->prepare("UPDATE queue SET directdial = ? WHERE id = ?");
   			$sql->execute(array($res,$row['id']));
   			$res = NULL;
   		}

   	}

/*
 * Parse the new extensions and fixup the extended dials if necessary
 */

	$res = NULL;

	$table = $v7dbh->query("select pkey,cluster from IPphone")->fetchall(PDO::FETCH_ASSOC);

   	foreach ($table as $row ) {
   		if (strlen($row['pkey']) <= 4  && $row['cluster'] != 'default') {
/*
 *	Handle class of service before we change the extensions key
 */
			$sql = $v7dbh->prepare("UPDATE IPphoneCOSopen SET ipphone_pkey = ? WHERE ipphone_pkey = ?");
   			$sql->execute(array($id . $row['pkey'], $row['pkey']));

   			$sql = $v7dbh->prepare("UPDATE IPphoneCOSclosed SET ipphone_pkey = ? WHERE ipphone_pkey = ?");
   			$sql->execute(array($id . $row['pkey'], $row['pkey']));
/*
 *	Handle the extensions
 */
   			$id = $v7dbh->query("SELECT id FROM cluster WHERE pkey = '" . $row['cluster'] . "'")->fetch(PDO::FETCH_COLUMN); 			
   			$sql = $v7dbh->prepare("UPDATE IPphone SET pkey = ? WHERE pkey = ?");
   			$sql->execute(array($id . $row['pkey'], $row['pkey']));
   			$res = NULL;

   		}

   	}

/*
 * Parse the new conference rooms and fixup the dials if necessary
 */

	$res = NULL;

	$table = $v7dbh->query("select pkey,cluster from meetme")->fetchall(PDO::FETCH_ASSOC);

   	foreach ($table as $row ) {
   		if (strlen($row['pkey']) <= 4) {
   			$id = $v7dbh->query("SELECT id FROM cluster WHERE pkey = '" . $row['cluster'] . "'")->fetch(PDO::FETCH_COLUMN); 			
   			$sql = $v7dbh->prepare("UPDATE meetme SET pkey = ? WHERE pkey = ?");
   			$sql->execute(array($id . $row['pkey'], $row['pkey']));
   			$res = NULL;
   		}

   	}






	       

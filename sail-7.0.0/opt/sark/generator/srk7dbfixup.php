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

$directDialTables = array(

	"ivrmenu",
	"Queue",
	"Meetme",

);

	$v7db = 'sqlite:/opt/sark/db/sark.newV7.db';
	$v7custdata = '/opt/sark/db/v7custdata.sql';	

		
    /*** connect to SQLite databases, old and new ***/

    try {
		$dbh = new PDO($v7db);
	}
	catch (Exception $e) {
		echo "Oops failed to open DB $v7db" . " $e\n";
		exit(4);
	}

    /*** set the error reporting attribute ***/
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   	
   	$res = NULL; 

   	$table = $dbh->query("select * from ivrmenu")->fetchall(PDO::FETCH_ASSOC);

   	foreach ($table as $row ) {
   		if (empty($row['directdial'])) {
   			$res = $dbh->query("SELECT MAX(directdial+1) FROM ivrmenu WHERE cluster = '" . $row['cluster'] . "'")->fetch(PDO::FETCH_COLUMN);
   			if (empty($res)) {
   				$res = $dbh->query("SELECT startivr FROM cluster WHERE pkey = '" . $row['cluster'] . "'")->fetch(PDO::FETCH_COLUMN);
   			}
   			$sql = $dbh->prepare("UPDATE ivrmenu SET directdial = ? WHERE id = ?");
   			$sql->execute(array($res,$row['id']));
   			$res = NULL;
   		}

   	}

   	$res = NULL;
   	$table = NULL;

   	$table = $dbh->query("select * from queue")->fetchall(PDO::FETCH_ASSOC);

   	foreach ($table as $row ) {
   		if (empty($row['directdial'])) {
   			$res = $dbh->query("SELECT MAX(directdial+1) FROM queue WHERE cluster = '" . $row['cluster'] . "'")->fetch(PDO::FETCH_COLUMN);
   			if (empty($res)) {
   				$res = $dbh->query("SELECT startqueue FROM cluster WHERE pkey = '" . $row['cluster'] . "'")->fetch(PDO::FETCH_COLUMN);
   			}
   			$sql = $dbh->prepare("UPDATE queue SET directdial = ? WHERE id = ?");
   			$sql->execute(array($res,$row['id']));
   			$res = NULL;
   		}

   	}
    

	       

<?php

require_once("includes.php");

SessionHandler::connect();

$reports = array();

//$reports = array('Initial Damage Assessment: Khaki (UC Siyal). 14 houses. 100% of crops destroyed. Village is still flooded');

foreach($reports as $rep){
	$date = date("Y-m-d H:i:s");
	$sql = "INSERT INTO incident (incident_title, incident_mode, incident_dateadd, incident_date) ";
	$sql .= "VALUES ('".mysql_real_escape_string($rep)."', '1', '$date', '$date');";


	print "<p>$sql<p>";
	
	/**
	#COMMENTED OUT FOR NOW TO AVOID REPEATED ADDS!	
	$ins = mysql_query($sql);
	
	$idIncident = DBQuery::get_last_id();
	
	if($idIncident != '' && $ins){

		$sql = "INSERT INTO incident_automated (idIncident, idMessage) VALUES ('$idIncident', '$id');";
		$ins = mysql_query($sql);
		print "<p>$sql<p>";
	}
	else{
		print "<p>Error! Could not insert<p>";
	}
	
	*/	
	
}





?>
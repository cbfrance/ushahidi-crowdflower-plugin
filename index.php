<?php
require_once("includes.php");
/**
* Code to update ushahidi instance from crowdflower.
*
*/

SessionHandler::connect();

$date = date("Y-m-d H:i:s");

$short = "signal: ".$_POST['signal']." \npayload: ".$_POST['payload'];

if($_POST['signal'] != "unit_complete"){
	$sql = "INSERT INTO debug (content, date) values ('(NON-COMPLETE): ".mysql_real_escape_string($short)."', '$date');";
	mysql_query($sql);
	die; #We only want unit completion notifications
}
else{
	$sql = "INSERT INTO debug (content, date) values ('".mysql_real_escape_string($short)."', '$date');";
	mysql_query($sql);

}

$requesta = json_decode(urldecode($_POST['payload']), true);

$id = $requesta['data']['ushahidi_id'];
$randIdent = $requesta['data']['randIdent'];
$message = $requesta['data']['sms_text'];


if($id == ''){
	$sql = "INSERT INTO debug (content, date) values ('BAD ID: ".mysql_real_escape_string($short)."', '$date');";
	mysql_query($sql);
	print "no id\n";
	die;
}

$sql = "SELECT location_name FROM location JOIN incident ON incident.location_id = location.id WHERE incident.id = $id;";
$current_loc_name = DBQuery::return_value_from_sql($sql);
if($current_loc_name != $unknown_loc && $current_loc_name != ''){
	// SOMEONE HAS SET THIS LOCATION WHILE BEING PROCESSED AT CF: ignore CF.
	print "\n<p>Location $current_loc_name already exists in Ushahidi instance<p>\n";
	die;
}


$sql = "SELECT randomIdentifier FROM incident_automated WHERE idIncident = '$id' AND randomIdentifier = '".$randIdent."'; ";
$matchRand = DBQuery::values_exist($sql);
if(!$matchRand){
	print "nomatching rand\n$sql\n";

	$sql = "INSERT INTO debug (content, date) values ('BAD RAND: incident_id = $id for ".mysql_real_escape_string($short)."', '$date');";
	mysql_query($sql);

	//die; #security - ignore requests where the random identifier isnt the one we gave for this task
	// repress this for now and continue: some ids changed post-migration
}


$sql = "UPDATE incident_automated SET returned_json = '".mysql_real_escape_string($_POST['payload'])."', status = '2' WHERE idIncident = '$id'";
$res = mysql_query($sql);

$latitude = $requesta['results']['zload']['centroid'][0];
$longitude = $requesta['results']['zload']['centroid'][1];
if($latitude == 0 || $latitude == ''){
	$latitude = $requesta['results']['latitude_longitude_zoom']['centroid'][0];
	$longitude = $requesta['results']['latitude_longitude_zoom']['centroid'][1];
}



$judgments = $requesta['results']['judgments'];

$categories = array();
$translations = array();
$locationnames = array();
$notes = array();
$ambiguous = array(); #if location is flagged as ambiguous
$uninformative = array(); #if does not have useful information

#AGGREGATE RESPONSES FROM DIFFERENT WORKERS
foreach($judgments as $judgment){
	$cats = $judgment['data']['category'];
	$trans = $judgment['data']['sms_translation'];
	if($trans != ''){
		$translations[] = $trans;
	}
	$loc = $judgment['data']['location_name'];
	if($loc != '' && $loc != 'false'){
		$locationnames [] = $loc;
	}
	$note = $judgment['data']['notes'];
	if($note != ''){
		$notes[] = $note;
	}
	$am = $judgment['data']['sms_ambiguous'];
	if($am != ''){
		$ambiguous[] = $am;
	}
	$un = $judgment['data']['sms_uninformative'];
	if($un != ''){
		$locationnames [] = $un;
	}
	foreach($cats as $cat){
		$categories[] = $cat;
	}
	$worker_lat = $judgment['data']['latitude'];
	$worker_long = $judgment['data']['longitude'];

	if($latitude == 0 && $worker_lat != '' && $worker_lat != 0){
		$latitude = $worker_lat;
	}
	if($longitude == 0 && $worker_long != '' && $worker_long != 0){
		$longitude = $worker_long;
	}



}
$description = implode("\n", $translations)."\n\nNotes:\n".implode("\n", $notes);
$location_name = implode("  ", array_unique($locationnames));

$latitude = preg_replace("/[^0-9\-\.]/","",$latitude);
$longitude = preg_replace("/[^0-9\-\.]/","",$longitude);



$loc_id = "";
if($latitude != '' && $longitude != ''){
	$sql = "INSERT INTO location (latitude, longitude, location_name, location_date) ";
	$sql .= "VALUES('$latitude', '$longitude', '".mysql_real_escape_string($location_name)."', '".date("Y-m-d H:i:s")."') ;";
	$res = mysql_query($sql);
	$loc_id = DBQuery::get_last_id();

	print "\nnew loc_id = $loc_id\n";
}


$sql = "UPDATE incident SET incident_description = '".mysql_real_escape_string($description)."'";

if($loc_id != ''){
	$sql .= ", location_id = '$loc_id' ";
}

$sql .= " WHERE id = '$id' ; ";


if(preg_match('/WHERE id....[0-9]/',$sql)){
	#preg match as a sanity check to make sure we are only updating one record inthe main incident table
	$res = mysql_query($sql);
	print "<p>\nUPDATED incident=$id\n<p>";

}



foreach($categories as $category){
	$catd = preg_split('/\|/',$category);
	$parent = trim($catd[0]);
	$catname = trim($catd[1]);

	$cat = mysql_real_escape_string($catname);
	$par = mysql_real_escape_string($parent);
	$sql = "SELECT id FROM category WHERE category_title LIKE '$cat'; ";

	$ids = DBQuery::return_values_from_sql($sql);

	if(count($ids) == 1){
		$cat_id = $ids[0];
		$sql = "INSERT INTO incident_category (category_id, incident_id) VALUES ('$cat_id', '$id'); ";
		mysql_query($sql);
	}
	elseif(count($ids) > 1){
		#ambiguous name - get parent's
		$sql = "SELECT category.id FROM category WHERE category.category_title LIKE '$cat' ";
		$sql .= "AND category.parent_id IN (SELECT par.id FROM category AS par WHERE par.category_title LIKE '$par');";
		$pids = DBQuery::return_values_from_sql($sql);
		if(count($ids) == 1){
			$cat_id = $pids[0];
			$sql = "INSERT INTO incident_category (category_id, incident_id) VALUES ('$cat_id', '$id'); ";
			mysql_query($sql);
		}

	}
	else{
		/**
		# This code will automatically add a new category with that name - commented out for now

		$sql = "INSERT INTO category (category_title) VALUES ('$cat'); ";
		$res = mysql_query($sql);
		$cat_id = DBQuery::get_last_id();
		$sql = "INSERT INTO incident_category (category_id, incident_id) VALUES ('$cat_id', '$id'); ";
		*/

	}

}



?>
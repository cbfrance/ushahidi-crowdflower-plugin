<?php
require_once("includes.php");
/**
*
* code to post new reports without locations and categories to crowdflower.
*
*/


SessionHandler::connect();

/// FIRST, PROMOTE ALL MESSAGES FROM SMS TO REPORTS


// This line should not be read to imply that the best way to get SMS is via email! :)
$sql = "SELECT * FROM message WHERE message_from = 'EMAIL_ADDRESS_OF_SMS_ORIGIN' AND id NOT IN (SELECT idMessage FROM incident_automated);";

//print "<p>$sql<p>\n";

$res = mysql_query($sql);

//print_r($res);

while($row=mysql_fetch_assoc($res)){
	$id = $row['id'];
	$detail = $row['message_detail'];

	print "\npromoting $id ";

	$fields = preg_split("/\n/" , $detail);
	$number = $fields[0];
	$message = "";
	for($i=1;$i<count($fields);$i++){
		$message .= $fields[$i]."\n";
	}

	#print "$id $number texted: $message\n<p>\n";

	if($number != '' && $message != ''){
		$date = date("Y-m-d H:i:s");

		$sql = "INSERT INTO location (latitude, longitude, location_name, location_date) ";
		$sql .= "VALUES('30.297017883372', '69.89501953125', '$unknown_loc', '$date') ;";
		$resl = mysql_query($sql);
		$loc_id = DBQuery::get_last_id();

		if(!$loc_id){
			print "Could not add default location!";
			continue;
		}

		$sql = "INSERT INTO incident (incident_title, incident_mode, incident_dateadd, incident_date, location_id) ";
		$sql .= "VALUES ('".mysql_real_escape_string($message )."', '2', '$date', '$date', '$loc_id');";

		$ins = mysql_query($sql);
		print "<p>\n$sql\n<p>\n";

		$idIncident = DBQuery::get_last_id();

		if($idIncident != '' && $ins){
			#add phone number
			$sql = "INSERT INTO form_response (form_field_id, incident_id, form_response) VALUES ('1', '$idIncident', '".mysql_real_escape_string($number)."');";
			$ins = mysql_query($sql);

			print "<p>\n$sql\n<p>\n";

			$sql = "INSERT INTO incident_automated (idIncident, idMessage, status) VALUES ('$idIncident', '$id', 0);";
			$ins = mysql_query($sql);

			print "<p>\n$sql\n<p>\n";
		}
		else{
			print "\n<p>Error! Could not insert<p>\n";
		}
	}
}


//die;

/// SECOND, GET ALL INCIDENTS

$sql = "SELECT * FROM incident WHERE id IN (SELECT idIncident FROM incident_automated WHERE status = 0);";
$res = mysql_query($sql);

while($row=mysql_fetch_assoc($res)){
	$id = $row['id'];
	$title = $row['incident_title'];
	$desc = $row['incident_description'];

	//print "<br>";
	$randIdent = md5(rand());

	$url = $baseurl.$job."/units";

	$sms_text = $title." ".$desc;

	print "\n<p>\nAdding $sms_text \n<p>\n";
	print_r($row);

	if(preg_match('/^\s*$/',$sms_text)){
		continue;
	}

	$params = "key=".$key;
	$params .= "&unit[data][ushahidi_id]=".$id;
	$params .= "&unit[data][sms_text]=".urlencode($sms_text);
	$params .= "&unit[data][randIdent]=".$randIdent;

	#https://crowdflower.com/jobs/18242?key=e611061dd7f95274bc1cc5deabaf3d591c859501&unit[data][ushahidi_id]=1234&unit[data][sms_text]=foobarbaz

	$ch = curl_init($url);

	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	$response = curl_exec($ch);
	curl_close($ch);

	print "\n<p>\n$response\n<p>\n";

	$sql = "UPDATE incident_automated SET timePosted = '".time()."', randomIdentifier = '".$randIdent."', status = '1' WHERE idIncident = '$id'  ;";

	#update status = 1 for a message that has been posted to CF but not returned

	$updt = mysql_query($sql);


}



?>
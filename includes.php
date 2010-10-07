<?php

$baseurl = 'https://crowdflower.com/jobs/'; //URL
$job = "JOB_ID"; //JOB NUMBER
$key = "YOUR_CROWDFLOWER_KEY_HERE";
$unknown_loc = 'Unknown'; //default name for unknown loc

class SessionHandler{

	public static $connected = FALSE;
	public static $db = "DATABASE_NAME";

	public static function connect(){

		if(!SessionHandler::$connected){
			SessionHandler::$connected = mysql_connect('localhost', 'DATABASE_USER', 'PASSWORD');
     	 		mysql_select_db(SessionHandler::$db);
    		}

    		if(!SessionHandler::$connected){
			header('HTTP/1.1 503 Service Unavailable');
    			echo "\nConnection failed!\n";
			die;
    		}
    	}
}

/**
*
* Class for DB interactions.
*
* TODO: could add schema-checks
*
*/
class DBQuery{

	public static function connect(){
		SessionHandler::connect();
    	}


	/**
	* Returns an associative array of field->values for the given table_name and id
	*
	*/
	public static function get_assoc($id, $table_name, $pk_name=''){
		DBQuery::connect();
		if($pk_name == ''){
			$pk_name = 'id'.$table_name;
		}
		$sql = "SELECT * FROM `$table_name` WHERE `$pk_name` = '$id';";

		print "\n$sql\n";

		$res = mysql_query($sql);
		if($row=mysql_fetch_assoc($res)){
			return $row;
		}
		else{
			//ERROR: no
		}
	}


	/**
	* Updates the
	*
	*/
	public static function update($db_fields, $table_name, $pk_name=''){
		if($pk_name == ''){
			$pk_name = 'id'.$table_name;
		}

		if(array_key_exists($pk_name,$db_fields) && $db_fields[$pk_name] != ''){
			//update
			$sql = "UPDATE `$table_name` SET ";

			foreach($db_fields as $field=>$val){
				if($field == $pk_name || $val==''){
					continue;
				}
				$sql .= "`$field` = '".mysql_real_escape_string($val)."', ";
			}
			$sql = preg_replace('/, $/','',$sql); //remove final comma
			$sql .= " WHERE `$pk_name` = '".mysql_real_escape_string($db_fields[$pk_name])."'; ";


			if($res = mysql_query($sql)){
				return $db_fields[$pk_name];
			}
			else{
				//ERROR

				print "\nDID NOT UPDATE:\n$sql\n";
			}
		}
		else{
			$sql1 = "INSERT INTO `$table_name` (";
			$sql2 = "VALUES (";
			foreach($db_fields as $field=>$val){
				if($val==''){
					continue;
				}
				$sql1 .= "`$field`, ";
				$sql2 .= "'".mysql_real_escape_string($val)."', ";
			}
			$sql1 = preg_replace('/, $/',')',$sql1); //remove final comma
			$sql2 = preg_replace('/, $/',')',$sql2); //remove final comma

			$sql = $sql1.$sql2.';';

			if($res = mysql_query($sql)){
				print "inserting";
				return DBQuery::get_last_id();
			}
			else{
				//ERROR
				print "\nDID NOT UPDATE:\n$sql\n";
			}
		}

	}


	/**
	* returns true if a record with $field = $value exists in the given $table
	* return false otherwise
	*/
	public static function exists($field, $value, $table){
		$sql = "SELECT `$field` FROM `$table` WHERE `$field` = '$value';";
		$res = mysql_query($sql);
		if($row=mysql_fetch_assoc($res)){
			return 1;
		}
		else{
			return 0;
		}
	}





	/**
	* returns the value of $ret_field if a record with $field = $value exists in the given $table
	* return an empty string otherwise
	*/
	public static function return_value($field, $value, $table, $ret_field){
		$sql = "SELECT $ret_field FROM $table WHERE $field = '$value';";
		print "$sql";
		return DBQuery::return_value_from_sql($sql);
	}


	/**
	* returns the values from the given sql query if entry exist and the sql is valid
	* return an empty string otherwise
	*/
	public static function return_value_from_sql($sql){
		$res = mysql_query($sql);
		$v = '';
		if($row=mysql_fetch_array($res)){
			$v = $row[0];
		}
		return $v;
	}


	/**
	* returns the list of values of $ret_field if a record with $field = $value exists in the given $table
	* return an empty array otherwise
	*/
	public static function return_values($field, $value, $table, $ret_field){
		$sql = "SELECT $ret_field FROM $table WHERE $field = '$value';";
		return DBQuery::return_values_from_sql($sql);
	}


	/**
	* returns and array of results indexed by the given $field, which is assumed to be unique
	*/
	public static function return_indexed_results_from_sql($sql, $field){
		$res = mysql_query($sql);
		$ret = array();
		while($row=mysql_fetch_assoc($res)){
			$k = $row[$field];
			$cur = array();
			foreach($row as $f=>$v){
				$cur[$f] = $v;
			}
			$ret[$k] = $cur;
		}
		return $ret;
	}



	/**
	* returns the list of values of from the given sql query if entries exist and the sql is valid
	* return an empty array otherwise
	*/
	public static function return_values_from_sql($sql){
		#print "$sql";
		$res = mysql_query($sql);
		$ret = array();
		while($row=mysql_fetch_array($res)){
			$v = $row[0];
			$ret[] = $v;
		}
		return $ret;
	}





	/**
	* returns true if the sql returns a non-empty result
	*/
	public static function values_exist($sql){
		$res = mysql_query($sql);
		if($row=mysql_fetch_array($res)){
			return TRUE;
		}
		else{
			return FALSE;
		}
	}


	/**
	*
	*/
	public static function get_last_id(){
		$query = "SELECT LAST_INSERT_ID()";
		$result = mysql_query($query);
		if ($result) {
			$nrows = mysql_num_rows($result);
			$row = mysql_fetch_row($result);
			return $row[0];
		}
		else{
			return "";
		}
	}

}




?>
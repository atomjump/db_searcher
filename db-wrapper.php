<?php

//ODBC Database wrapper

function odbc_db_connect($json) {
	if($json['useODBC'] == true) {
		$dbh = odbc_connect($json['dsnHere'],$json['username'], $json['password']);
		
		//Ensure we are commiting by default for each query - this is needed by the Firebird ODBC driver when connecting
		//to the Interbase db
		odbc_autocommit($dbh, true);
		return $dbh;
	} else {
		$dbh = dbconnect($json['host'], $json['username'], $json['password'], $json['dbname']);	//Use the parent method
		return $dbh;
	}
}


function odbc_db_query($json, $dbh, $sql) {
	if($json['useODBC'] == true) {
		
		return odbc_exec($dbh, $sql);
	} else {
		error_log("Running dbquery() on " . $sql);
		return dbquery($sql);
	}
}


function odbc_db_get_field($json, $result, $field, $multi_results_false = false) {
	//Get a field from a single database result row. ASsume this is a single row returned.
	//An optional input to this is a 'multi_results_false' flag, which when set to 
	//  'true': will return the string 'DUPLICATE' if there is more than one result
	//  'false' (default): will return the first row's result if there is 1 or more results
	if($json['useODBC'] == true) {
	
		if(odbc_fetch_row($result)) {
			
			//OK, there is at least one result, prepare to return this
			$ret = odbc_result($result,$field);
			
			//Now check if there is more than one result
			if($multi_results_false == true) {
				if(odbc_fetch_row($result)) {
					//Yes, there is more than one result
					return "DUPLICATE";
				}
			}
		
			return $ret;
		} else {
			return false;
		}
	} else {
		$row = sqlsrv_fetch_array($result);
		if($row) {
			
			//OK, there is at least one result, prepare to return this
			$ret = $row[$field];
			
			//Now check if there is more than one result
			if($multi_results_false == true) {
				if(sqlsrv_fetch_array($result)) {
					//Yes, there is more than one result
					return "DUPLICATE";
				}
			}
		
			return $ret;
		} else {
			return false;
		}
	}

}


function odbc_db_err_msg($json, $err = null) {
	if($json == "exception") {
		$instr = $err;
	} else {
		if($json['useODBC'] == true) {
			$instr = odbc_errormsg();
		} else {
			$instr = dberror();
		}
	}
	
	$str = substr($instr, 0, 255);		//Prevent any really long SQL error messages

	//And make sure they are readable on the screen
	$output = filter_var($str, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW|FILTER_FLAG_STRIP_HIGH);
	
	return htmlspecialchars($output);

}

function odbc_db_code_checks($json) {
	if($json['useODBC'] == true) {
		return function_exists("odbc_connect");
	} else {
		return true;
	}
}


// replace any non-ascii character with its hex code.
//Courtesy: https://stackoverflow.com/questions/1162491/alternative-to-mysql-real-escape-string-without-connecting-to-db
function odbc_escape($value) {
    $return = '';
    for($i = 0; $i < strlen($value); ++$i) {
        $char = $value[$i];
        $ord = ord($char);
        if($char !== "'" && $char !== "\"" && $char !== '\\' && $ord >= 32 && $ord <= 126)
            $return .= $char;
        else
            $return .= '\\x' . dechex($ord);
    }
    return $return;
}

function odbc_clean_data($string) {
  //Use for cleaning input data before addition to database
  if (get_magic_quotes_gpc()) {
	$string = stripslashes($string);
  }
  $string = strip_tags($string);
  return odbc_escape($string);
}


?>
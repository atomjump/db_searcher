<?php
    include_once("classes/cls.pluginapi.php");
    include_once(dirname(__FILE__) . "/db-wrapper.php");
    
    
    function parse_message($message, $user_queries)
    {
    	//Reads and looks through each potential user query for a pattern match in the incoming
    	//message that the user has entered.
    	//e.g. User enters 'details on test'
    	//And if we find in the user queries:
    	// "details on [SEARCH]"
    	//And replies with the value of [SEARCH], in this case 'test'
    	//Note: 'details on' is a case insensitive search
    	
    	$message = trim($message);
    	$message = str_replace("\\\\r", '', $message);
    	$message = str_replace("\\\\n", '', $message);
    
    	foreach($user_queries as $query) {
    		//Remove the [SEARCH], to allow for a matcher
    		$preg_query = str_replace("[SEARCH]", "(.*)", $query);
    		$preg_query = '/\:\s' . $preg_query . '/i';
    		preg_match($preg_query, $message, $matches);
    		
    		
    		if($matches[1]) {
    			$ret = str_replace("\\r", '', $matches[1]);
    			$ret = str_replace("\\n", '', $ret);
    			return $ret;		//Early out return
    		}
    		
    	}
    	
    	return false;
    
    
    }
    
	//With thanks to Vincy https://phppot.com/php/highlighting-keywords-in-search-results-with-php/
    function highlight_keywords($text, $keyword) {
		$wordsAry = explode(" ", $keyword);
		$wordsCount = count($wordsAry);
		
		for($i=0;$i<$wordsCount;$i++) {
			$highlighted_text = "<span style='font-weight:bold;'>$wordsAry[$i]</span>";
			$text = str_ireplace($wordsAry[$i], $highlighted_text, $text);
		}

		return $text;
	}
    
    
    
    function connect_to_db($json)
    {
    
    	if(odbc_db_code_checks($json)) {

			try {
				
				$dbh = odbc_db_connect($json);
				if(!$dbh) {
					$errmsg = odbc_db_err_msg($json);
		
					return array( array( 0 => array(0 => "Sorry, I could not connect to the database. Please check your username, password, database file and host parameters.")), false);
					
				} else {
					//Connected OK!
					return array(true, $dbh);
					
				}
			} catch (Exception $e) {
				return array( array( 0 => array(0 => "Sorry, your installation is incomplete, and cannot use the database connection software.")), false);

			}
		} else {
			return array( array( 0 => array(0 => "Sorry, your installation is not working, and cannot connect to your database. Are you sure your database is installed on this machine?")), false);	
		}
    
    
    }
    
    
    function run_query($sql, $json, $dbh, $no_results_msg = null) 
    {
    	
		//Try running the query
		$sql = clean_data($sql); //E.g. "SELECT CONCATENATE('Found - ', field1, field2) AS result FROM table WHERE field1 LIKE '%[SEARCH]' LIMIT 1";
		$sql = str_replace("\\","", $sql);
		
		$result = odbc_db_query($json, $dbh, $sql);
		
		$errmsg = odbc_db_err_msg($json);
		if($errmsg) {
			error_log("Error: " . $errmsg);
		}
							
		if(!$result->num_rows || is_null($result->num_rows)) {
			$msg = "Sorry, there were no results";
			if(isset($no_results_msg)) {
				$msg = $no_results_msg;
			}	
			$retresult = array( 0 => array('result' => $msg));
			return $retresult;
		} else {
			
			//Success!
			return $result;
		}
	}
    





    class plugin_db_searcher
    {
        public function on_message($message_forum_id, $message, $message_id, $sender_id, $recipient_id, $sender_name, $sender_email, $sender_phone)
        {
        
       		$verbose = false;		//this should be false unless you want quite a few debug messages

        
                      
        	if(!isset($db_searcher_config)) {
                //Get global plugin config - but only once
                global $cnf;
                
                $path = dirname(__FILE__) . "/config/config.json";
                
                
	            $data = file_get_contents($path);
	            
                if($data) {
                    $db_searcher_config = json_decode($data, true);
                    if(!isset($db_searcher_config)) {
                        error_log("Error: db_searcher config/config.json is not valid JSON.");
                        exit(0);
                    }
                } else {
                    error_log("Error: Missing config/config.json in db_searcher plugin.");
                    exit(0);
                }
            }
            
            
            if($verbose) error_log("Read .json");
            
            $api = new cls_plugin_api();
            
            //Loop through each of the forums
            $send = false;
            
            
            
            
            
            $write_back = false;
            //Search through each of the different forum types (which have a different SQL query in each)
            for($cnt = 0; $cnt < count($db_searcher_config['forums']); $cnt++) {
            
                if(isset($db_searcher_config['forums'][$cnt]['forum_id'])) {
                	//We've already pre-saved this forum's id in the config file 
                    $forum_id = $db_searcher_config['forums'][$cnt]['forum_id'];
                } else {
                    if($db_searcher_config['forums'][$cnt]['aj'] != 'default') {
                    	//Get $forum_id from the name which is the forum that we are checking against
                        $forum_info = $api->get_forum_id($db_searcher_config['forums'][$cnt]['aj']);
                        $forum_id = $forum_info['forum_id'];                    
                        $db_searcher_config['forums'][$cnt]['forum_id'] = $forum_id;
                        $write_back = true;
                    } else {
                    	//A special case 'default'
                    	//So these queries will always be applied, no matter which forum (unless overriden by a special case). 
                        $forum_id = null;
                    }
                }
                
                
                if($message_forum_id == $forum_id) {
                    //Yes, this message is being posted onto the forum that we're checking against
                   
                    $user_queries = $db_searcher_config['forums'][$cnt]['userQueries'];
                    $helper = $db_searcher_config['forums'][$cnt]['helperName'];
                    $helper_email = $db_searcher_config['forums'][$cnt]['helperEmail'];
                    $sql_queries = $db_searcher_config['forums'][$cnt]['sqlQueries'];
					$db_id = $db_searcher_config['forums'][$cnt]['dbId'];
					$no_result = $db_searcher_config['forums'][$cnt]['noResult'];
                    $send = true;
                } else {
                	//So it is not this special case, but if there is a default, and we have not
                	//already set a special case, we can still 
                	//use these queries
                
                    if(($db_searcher_config['forums'][$cnt]['aj'] == 'default')&&($send == false)) {
                        $user_queries = $db_searcher_config['forums'][$cnt]['userQueries'];
                        $helper = $db_searcher_config['forums'][$cnt]['helperName'];
                        $helper_email = $db_searcher_config['forums'][$cnt]['helperEmail'];
                        $sql_queries = $db_searcher_config['forums'][$cnt]['sqlQueries'];
						$db_id = $db_searcher_config['forums'][$cnt]['dbId'];
						$no_result = $db_searcher_config['forums'][$cnt]['noResult'];
                        $send = true;
                    }
                }
            }
            
            if($write_back == true) {
                //OK save back the config with the new forum ids in it - this is for speed. 
                $data = json_encode($db_searcher_config, JSON_PRETTY_PRINT); //note this pretty print requires PHP ver 5.4
                file_put_contents($path, $data);      
            }
            
            if($db_searcher_config['storeInDb'] == true) {
                $sql = "SELECT var_db_searcher_json FROM tbl_layer WHERE int_layer_id = " . $message_forum_id;
				$result = $api->db_select($sql);
				if($row = $api->db_fetch_array($result))
				{
            		if($row['var_db_searcher_json']) {
            			//Ok not null
            			$forum_config = json_decode($row['var_db_searcher_json']);
            			
            			//Get individual fields
            			$user_queries = $forum_config->userQueries;
						$helper = $forum_config->helperName;
						$helper_email = $forum_config->helperEmail;
						$sql_queries = $forum_config->sqlQueries;
						$db_id = $forum_config->dbId;
						$no_result = $forum_config->noResult;
            		}
            
            	}
            }
            
            
             
            
         
            
           
            if($helper_email != "") {
                
				if($verbose) error_log("Send = " . $send);
		
				//React to this message, it was from another user
				if($send == true) {
					//Get the forum id
									  
				   
					$options = array('notification' => false, 'allow_plugins' => false);		//turn off any notifications from these messages
					
					if($verbose) error_log("message:" . $message);
					if($verbose) error_log("user_queries:" .  json_encode($user_queries));
					
					//Find the user queries of this
					$our_search = parse_message($message, $user_queries);
					
					if($verbose) error_log("Our search:" . $our_search);
									 
					
					
					if($our_search !== false) {
						//Yes, we were a matching request - run a query against the database and respond 
						
						
						
						
						
						if($verbose) error_log("DB id:" . $db_id);
					
						$mydb = array();
						foreach($db_searcher_config['databases'] as $database) {
							if($database['dbId'] == $db_id) {
								$mydb = $database;
								if($verbose) error_log("Database name selected:" . $database['dbname']);
							}
						}
						
						if($mydb) {
							
							list($new_messages, $dbh) = connect_to_db($mydb);
							
							if($dbh) {
								foreach($sql_queries as $sql_query) {
									//Replace the string [SEARCH] in the SQL, with our actual search term
									$final_sql = str_replace("[SEARCH]", $our_search, $sql_query);
							
									if($verbose) error_log("Final sql:" . $final_sql);	
									//Run the db queries
									$new_messages = run_query($final_sql, $mydb, $dbh, $no_result);
								}
							} 
						
							if($verbose) error_log("returned from query:" . json_encode($new_messages));
						
							$sender_ip = $api->get_current_user_ip();
							
							//Prep all the messages to send
							$all_messages = array();
						
							$new_message = odbc_db_fetch_array($mydb, $new_messages);
							if($new_message[0] && $new_message[0] != "") {
								
								if($verbose) error_log("Got one row: " . json_encode($new_message));
								if($verbose) error_log("Result prepping to message: " . $new_message[0]);
								
								
								$all_messages[] = $new_message[0];
							
								
								if($verbose) error_log("Finished sending 1st message");
								
								$cnt = 1;
								
								//Do any further results
								while($new_message = odbc_db_fetch_array($mydb, $new_messages)) {
									if($cnt < $db_searcher_config['maxDisplayMessages']) {
										if($verbose) error_log("Result prepping to message: " . $new_message[0]);
										$all_messages[] = $new_message[0];						
										
									} else {
										break;
									}
									$cnt++;
								}
								
								
							} else {
								//Do a no result or error message
								$all_messages[] = $no_result;
							}
						
							if($verbose) error_log("Message has been queued successfully.");
							
							//Now send the messages. Switch back to the main database.
							odbc_dbclose(null);			//Clear off our current database connection again, to allow a reconnect
							//Reconnect to our main database
							global $db_host;
							global $db_username;
							global $db_password;
							global $db_name;
							global $db;
							
							$db = dbconnect($db_host, $db_username, $db_password);
							dbselect($db_name);
							db_set_charset('utf8');
							db_misc();
							
							if($verbose) error_log("db is now:" . json_encode($db));
							
							if($db) {
								foreach($all_messages as $this_message) {
									if($verbose) error_log("Result about to message: " . $this_message);
									
									$this_message = highlight_keywords($this_message, $our_search);
									
									//Send the message
									$new_message_id = $api->new_message($helper, $this_message, $sender_ip . ":" . $sender_id, $helper_email, $sender_ip, $message_forum_id, $options);
									
								}
							
							}
														
							 
						  }		//End of if mydb
					 }   //End of our search
				}  //End of our send                  
					
			}     //End of helper
            	
                
			return true;
		} 	// End of on_message
            
    
    }	//End of class

    
?>

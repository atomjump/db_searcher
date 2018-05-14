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
    		error_log("Preg query:" . $preg_query .  "  Running against: " . $message . "\n");
    		preg_match($preg_query, $message, $matches);
    		
    		print_r($matches);
    		
    		if($matches[1]) {
    		
    			
    			$ret = str_replace("\\r", '', $matches[1]);
    			$ret = str_replace("\\n", '', $ret);
    			return $ret;
    		} else {
    			return false;
    		}
    		
    	}
    
    
    }
    

    
    
    
    
    function run_query($sql, $json, $no_results_msg = null) 
    {
    	error_log("Trying to connect to database: " . $json['dbname']);
    	
		if(odbc_db_code_checks($json)) {

			try {
				
				$dbh = odbc_db_connect($json);
				if(!$dbh) {
					$errmsg = odbc_db_err_msg($json);
		
					return array( 0 => array('result' => "Sorry, I could not connect to the database. Please check your username, password, database file and host parameters."));
					
				} else {
	
					//Try running the query
					$sql = clean_data($sql); //E.g. "SELECT CONCATENATE('Found - ', field1, field2) AS result FROM table WHERE field1 LIKE '%[SEARCH]' LIMIT 1";
					$sql = str_replace("\\","", $sql);
					error_log("Running SQL:" . $sql . " connecting to dbh:" . json_encode($json));
					
					echo "\nQuery:  " . $sql;
					$result = odbc_db_query($json, $dbh, $sql);
					
					$errmsg = odbc_db_err_msg($json);
					if($errmsg) {
						error_log("Error: " . $errmsg);
					}
					
					if ((!$result) ||(isset($result['num_rows']) && is_null($result['num_rows']))){
						
					
						$msg = "Sorry, there were no results";
						if(isset($no_results_msg)) {
							$msg = $no_results_msg;
						}	
						$result = array( 0 => array('result' => $msg));
						return $result;
					} else {
						
						//Success!
						return $result;
					}
				}
			} catch (Exception $e) {
				return array( 0 => array('result' => "Sorry, your installation is incomplete, and cannot use the database connection software."));

			}
		} else {
			return array( 0 => array('result' => "Sorry, your installation is not working, and cannot connect to your database. Are you sure your database is installed on this machine?"));	
		}
	}
    





    class plugin_db_searcher
    {
        public function on_message($message_forum_id, $message, $message_id, $sender_id, $recipient_id, $sender_name, $sender_email, $sender_phone)
        {
                      
        	if(!isset($db_searcher_config)) {
                //Get global plugin config - but only once
                global $cnf;
                
                $path = dirname(__FILE__) . "/config/config.json";
                
                
	            $data = file_get_contents($path);
	            
                if($data) {
                    $db_searcher_config = json_decode($data, true);
                    if(!isset($db_searcher_config)) {
                        echo "Error: db_searcher config/config.json is not valid JSON.";
                        exit(0);
                    }
                } else {
                    echo "Error: Missing config/config.json in db_searcher plugin.";
                    exit(0);
                }
            }
            
            
            error_log("Read .json");
            
            $api = new cls_plugin_api();
            
            //Loop through each of the forums
            $send = false;
            
            
            
            
            
            $write_back = false;
            for($cnt = 0; $cnt < count($db_searcher_config['forums']); $cnt++) {
            
                if(isset($db_searcher_config['forums'][$cnt]['forum_id'])) {
                    $forum_id = $db_searcher_config['forums'][$cnt]['forum_id'];
                } else {
                    if($db_searcher_config['forums'][$cnt]['aj'] != 'default') {
                        $forum_info = $api->get_forum_id($db_searcher_config['forums'][$cnt]['aj']);
                        $forum_id = $forum_info['forum_id'];                    
                        $db_searcher_config['forums'][$cnt]['forum_id'] = $forum_id;
                        $write_back = true;
                    } else {
                        $forum_id = null;
                    }
                }
                
                
                if($message_forum_id == $forum_id) {
                    //Yep this forum has a wait time specifically for it
                   
                    $user_queries = $db_searcher_config['forums'][$cnt]['userQueries'];
                    $new_message = $db_searcher_config['forums'][$cnt]['message'];
                    $helper = $db_searcher_config['forums'][$cnt]['helperName'];
                    $helper_email = $db_searcher_config['forums'][$cnt]['helperEmail'];
                    $sql_query = $db_searcher_config['forums'][$cnt]['sqlQuery'];
					$db_id = $db_searcher_config['forums'][$cnt]['dbId'];
					$no_result = $db_searcher_config['forums'][$cnt]['noResult'];
                    $send = true;
                } else {
                    if($db_searcher_config['forums'][$cnt]['aj'] == 'default') {
                        $user_queries = $db_searcher_config['forums'][$cnt]['userQueries'];
                        $helper = $db_searcher_config['forums'][$cnt]['helperName'];
                        $helper_email = $db_searcher_config['forums'][$cnt]['helperEmail'];
                        $sql_query = $db_searcher_config['forums'][$cnt]['sqlQuery'];
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
						$new_message = $forum_config->message;
						$helper = $forum_config->helperName;
						$helper_email = $forum_config->helperEmail;
						$sql_query = $forum_config->sqlQuery;
						$db_id = $forum_config->dbId;
						$no_result = $forum_config->noResult;
            		}
            
            	}
            }
            
            
         
            
            if($sender_email == $helper_email) {
                //Don't react to this message
                
                error_log("Sender email is same as helper email");
            } else {
                if($helper_email != "") {
                
                     error_log("Send = " . $send);
            
                    //React to this message, it was from another user
                    if($send == true) {
                        //Get the forum id
                                          
                       
                        $options = array('notification' => false);		//turn off any notifications from these messages
                        
                        error_log("message:" . $message);
                        error_log("user_queries[0]:" .  $user_queries[0]);
                        
                        //Find the user queries of this
                        $our_search = parse_message($message, $user_queries);
                        
                        error_log("Our search:" . $our_search);
                        
                        
                        if($our_search !== false) {
                        	//Yes, we were a matching request - run a query against the database and respond 
                        	
                        	//Replace the string [SEARCH] in the SQL, with our actual search term
                        	$final_sql = str_replace("[SEARCH]", $our_search, $sql_query);
                        	
                        	error_log("Final sql:" . $final_sql);
                        	error_log("DB id:" . $db_id);
                        	
                        	$mydb = array();
                        	foreach($db_searcher_config['databases'] as $database) {
                        		if($database['dbId'] == $db_id) {
                        			$mydb = $database;
                        			error_log("Database name selected:" . $database['dbname']);
                        		}
                        	}
                        	
                        	if($mydb) {
								$sender_ip = $api->get_current_user_ip();
								
								//Run the db query
								$new_messages = run_query($final_sql, $mydb, $no_result);
							
								error_log("returned from query:" . json_encode($new_messages));
							
								if($new_message = odbc_db_fetch_array($mydb, $new_messages)) {
									
									$new_message_id = $api->new_message($helper, $new_message['result'], $sender_ip . ":" . $sender_id, $helper_email, $sender_ip, $message_forum_id, $options);
									
									$cnt = 1;
									
									//Do any further results
									while($new_message = db_fetch_array($new_messages)) {
										if($cnt < $db_searcher_config['maxDisplayMessages']) {
											$new_message_id = $api->new_message($helper, $new_message['result'], $sender_ip . ":" . $sender_id, $helper_email, $sender_ip, $message_forum_id, $options);
											
										} 
										$cnt++;
									}
									
									
								} else {
									//Do a no result or error message
									$new_message_id = $api->new_message($helper, $new_messages[0]['result'], $sender_ip . ":" . $sender_id, $helper_email, $sender_ip, $message_forum_id, $options);
								}
							
								error_log("New messages 0:" . json_encode($new_messages));
								
								  
								  
								
										
								
								
								 
								 //revert back to our own database 
								 make_writable_db();
								 
							  }
                       	 }   //End of our search                     
                    	
                    }     //End of if send
                }		//End of helper email
            } 
            
            
            
            return true;

        }
    
    }

    
?>

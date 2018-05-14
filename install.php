<?php
	if(!isset($db_searcher_config)) {
        //Get global plugin config - but only once
		$path = dirname(__FILE__) . "/config/config.json";
		$data = file_get_contents ($path);
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
 
	$start_path = $db_searcher_config['serverPath'];
	
	$staging = $db_searcher_config['staging'];
	$notify = false;
	include_once($start_path . 'config/db_connect.php');	
	echo "Start path:" . $start_path . "\n";

	
	$define_classes_path = $start_path;     //This flag ensures we have access to the typical classes, before the cls.pluginapi.php is included
	
	echo "Classes path:" . $define_classes_path . "\n";
	
	require($start_path . "classes/cls.pluginapi.php");
	
	$api = new cls_plugin_api();
	
	//Insert a column into the user table - a free text json for the config on a per layer (forum) basis
	$sql = "ALTER TABLE tbl_layer ADD COLUMN `var_db_searcher_json` varchar(2000) DEFAULT NULL";
	echo "Updating user table. SQL:" . $sql . "\n";
	$result = $api->db_select($sql);
	echo "\nCompleted.  Make sure you set storeInDb as 'true' in your config/config.json file, to allow these settings per forum to be stored in the database, rather than just the config.\n";
	

?>
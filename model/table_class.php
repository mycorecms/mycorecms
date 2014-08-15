<?php

/*  Class for rendering/saving table information with supporting functions
    Copyright (C) 2007-2014 MyCoreCMS

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details. <http://www.gnu.org/licenses/>.
 */
 require_once "mysql_class.php";
 

class TableClass{
  var $db;
  var $mysql;
  var $limit;
  var $primary_key;
  var $table_name;
  var $fields;
  var $flat_table;
  var $parent_id;
  var $parent_key;
  var $page_id;
  var $default_results;
  var $where_criteria;
  var $select_criteria;
  var $order_by;
  var $variables;
  var $masks;
  var $printable;
  var $mapable;
  var $deleteable;
  var $category;
  var $tab_header;
  var $children;
  var $multiple_execute;
  var $user;
  var $reports;
  var $permissions;
  var $initialized;

    //Initialize Results
	public function __construct(&$mysql = null,&$child = null) {
      $this->variables = Array();
      $this->default_results = 25;
      $this->initialized = false;

      if(isset($mysql)){
        $this->mysql = $mysql;
        $this->primary_key = $mysql->get_primary_key();
        $this->fields = $mysql->lookup_fields();
        $this->table_name = $mysql->lookup_table_name();
        //$this->init_variables();  //Called in action_check
      }

	}
    //Dynamically puts all variables in an array + build search criteria
    public function init_variables(){
      if(!$this->initialized){
        $this->initialized = true;
        //$this->where_criteria = NULL;
        $this->category = ( isset($this->category) ? $this->category: 'Data_Entry');
        $this->masks = array(
         "zip" => "99999",
    	 "zip+4" => "99999?-9999",
         "phone number" => "999-999-9999"
         );
        $this->variables['advanced_search'] = ( isset($_REQUEST['advanced_search']) ? $_REQUEST['advanced_search'] : 0);
        $this->variables['ascending'] = ( isset($_REQUEST['ascending']) ? (bool)$_REQUEST['ascending'] : FALSE);
        $this->variables['action'] =  ( isset($_REQUEST["action"]) ? $_REQUEST["action"] : "");

        if(!isset($this->fields['updated']))
            $this->fields["updated"] = array("type"=>"timestamp", "hidden" => TRUE);
        if(!isset($this->fields['updated_by']))
            $this->fields["updated_by"] = array("type" => "text","max_length" => 45, "hidden" => TRUE);
        if(!isset($this->fields['created_by']))
            $this->fields["created_by"] = array("type" => "text","max_length" => 45,"hidden" => TRUE);
        if(!isset($this->fields['created']))
             $this->fields["created"] = array("type"=>"timestamp", "hidden" => TRUE);
        $this->mysql->fields = $this->fields;
        //Check if we submitted a parent key indicates we're in tabbed mode and we're updating the parent
        if(!isset($this->parent_key))
            $this->parent_key = ( isset($_REQUEST['parent_key']) ?$_REQUEST['parent_key'] : $this->parent_key);
        $this->parent_id = ( isset($_REQUEST[$this->parent_key]) ?$_REQUEST[$this->parent_key] : $this->parent_id);

        if(isset($this->parent_key) && isset($this->parent_id)  && isset($this->table_name)){
            if(!isset($this->fields[$this->parent_key])){   //Check if the parent key is in the child, if not add it
                $this->fields[$this->parent_key] = array("type" => "int", "hidden" => TRUE);
                $this->mysql->fields = $this->fields;
            }
        }

        foreach(array_keys($this->fields) as $key){
          if(!isset($this->variables[$key])){//don't reset a key that is already set
            switch(isset($this->fields[$key]['type']) ? $this->fields[$key]['type'] : "text"){
                case "int":
                case "table_link":
                case "range":
                case "integer":
                case "big_integer":                                                                 //SET value to null if it's empty and required
                    $this->variables[$key] = ( isset($_REQUEST[$key]) ? (int)$_REQUEST[$key] : ( (isset($this->fields[$key]['min_length']) && $this->fields[$key]['min_length'] >0) || $key == $this->primary_key ? NULL : 0));
                    if($this->variables[$key] != NULL && (isset($this->fields[$key]['searchable']) OR $this->variables['advanced_search'] == 1))
                        $this->where_criteria[] = array("field" => "$key", "operator"=>"=", "argument"=>"{$this->variables[$key]}");
                    //Check if a parent is present and set the ID
                    if(($this->variables['action'] == 'Add_New' || $this->variables['action'] == 'Edit') && $key == $this->parent_key && isset($this->parent_id))
                        $this->variables[$key] = $this->parent_id;
                break;
                case "table_link-checkboxes":
                case "checkbox-list":
                  $this->variables[$key]  = ( isset($_REQUEST[$key]) && $_REQUEST[$key]!='' ? implode(',',$_REQUEST[$key]) : NULL);
                  if($this->variables[$key] != NULL && (isset($this->fields[$key]['searchable']) OR $this->variables['advanced_search'] == 1)){
                      foreach(explode(',',$this->variables[$key]) as $value)
                        $this->where_criteria[] = array("field" => "{$key}", "operator"=>"=", "argument"=>"{$value}");
                  }
                break;
                case "double":
                case "currency":
                case "float":                                                                    //SET value to null if it's empty and required
                    $this->variables[$key] = ( isset($_REQUEST[$key]) ? (float)$_REQUEST[$key] : ( isset($this->fields[$key]['min_length']) && $this->fields[$key]['min_length'] >0 ? NULL : 0));
                    if($this->variables[$key] != NULL && (isset($this->fields[$key]['searchable']) OR $this->variables['advanced_search'] == 1))
                        $this->where_criteria[] = array("field" => "$key", "operator"=>"=", "argument"=>"{$this->variables[$key]}");
                break;
                case "checkbox":
                case "bool":                                                                    //SET value to null if it's empty and required
                    $this->variables[$key] = ( isset($_REQUEST[$key]) ? (bool)$_REQUEST[$key] : FALSE);
                    if($this->variables[$key] != NULL && (isset($this->fields[$key]['searchable']) OR $this->variables['advanced_search'] == 1))
                        $this->where_criteria[] = array("field" => "$key", "operator"=>"=", "argument"=>"{$this->variables[$key]}");
                break;
                case "list":
                case "distinct_list":
                    $this->variables[$key] = ( isset($_REQUEST[$key]) ? $_REQUEST[$key] :"");
                    if($this->variables[$key] != "" && (isset($this->fields[$key]['searchable']) OR $this->variables['advanced_search'] == 1) )
                        $this->where_criteria[] = array("field" => "$key", "operator"=>"=", "argument"=>"{$this->variables[$key]}");
                break;
                default:
                    $this->variables[$key] = ( isset($_REQUEST[$key]) ? $_REQUEST[$key] :"");
                    if($this->variables[$key] != "" && (isset($this->fields[$key]['searchable']) OR $this->variables['advanced_search'] == 1) )
                        $this->where_criteria[] = array("field" => "$key", "operator"=>"LIKE", "argument"=>"%{$this->variables[$key]}%");
                break;
           }

           //Shows defaults on Add
           if($this->variables['action'] == 'Add_New' || ($this->variables['action'] == 'Add' &&  isset($this->fields[$key]['min_length']) && $this->fields[$key]['min_length'] > 0))
                 $this->variables[$key] = ( isset($this->fields[$key]['default']) && $this->variables[$key] == '' ? ($this->fields[$key]['default']=='current_timestamp'?date('Y-m-d'):$this->fields[$key]['default']) : $this->variables[$key]);
          }
        }

        //Check if this is a child, if so disable searching and force display of only parent matching results
        if(isset($this->parent_key) && isset($this->parent_id) ){
            $this->where_criteria = NULL;
            $this->where_criteria[] = array("field" => "{$this->parent_key}", "operator"=>"=", "argument"=>"{$this->parent_id}");
        }


        //Sorting variables for jquery
        $this->variables['sort'] = ( isset($_REQUEST['sort']) ? $_REQUEST['sort'] : $this->primary_key);
        $this->variables['ascending'] = ( isset($_REQUEST['ascending']) ? (bool)$_REQUEST['ascending'] : FALSE);
        $this->variables['jquery'] = ( isset($_REQUEST['jquery']) ? TRUE : FALSE);

        $this->variables['get_key'] = ( isset($_REQUEST['get_key']) ? $_REQUEST['get_key'] : '');
        $this->variables['get_id'] = ( isset($_REQUEST['get_id']) ? (int)$_REQUEST['get_id'] : NULL);

        $this->order_by = array(array("field" => $this->variables['sort'], "ascending" => $this->variables['ascending']));
        if($this->variables['advanced_search'] == 1)
            $this->default_results = $_REQUEST['rpp'] =10;//limit results on advanced search so scrollbar appears
        //Get user information when adding/updating new entry
        if($this->variables['action'] == 'Add'){
            $this->variables['created'] = date('Y-m-d H:i:s');
            if(isset($this->user->mysql))
            $this->variables['created_by'] = $this->user->mysql->first_name." ".$this->user->mysql->last_name;
        }

        if($this->variables['action'] == 'Add' || $this->variables['action'] == 'Update' || $this->variables['action'] == 'Delete' || $this->variables['action'] == 'Set_Field'){
            $this->where_criteria = NULL;
            $this->variables['updated'] = date('Y-m-d H:i:s');
            if(isset($this->user->mysql))
            $this->variables['updated_by'] = $this->user->mysql->first_name." ".$this->user->mysql->last_name;
        }

        $this->variables['address_field'] = 'address';
        $this->variables['city_field'] = 'city';
        $this->variables['zip_field'] = 'zip';

        $this->permissions['add'] = (!isset($this->permissions['add']) ?(isset($this->user->mysql->add)?$this->user->mysql->add:FALSE):$this->permissions['add']);
        $this->permissions['edit'] = (!isset($this->permissions['edit'])?(isset($this->user->mysql->edit)?$this->user->mysql->edit:FALSE):$this->permissions['edit']);
        $this->permissions['delete'] = (!isset($this->permissions['delete'])?(isset($this->user->mysql->delete)?$this->user->mysql->delete:FALSE):$this->permissions['delete']);
        $this->permissions['export'] = (!isset($this->permissions['export'])?(isset($this->user->mysql->export)?$this->user->mysql->export:FALSE):$this->permissions['export']);

      }
    }

    //Function with case statement that handles all web requests

    public function action_check($action = NULL){
        $this->init_variables();
        $this->variables['action'] = (isset($action) ? $action : $this->variables['action']);
        switch ($this->variables['action']) {
           case "Add":

             if($this->permissions['add']){
               $captcha_check = TRUE;
             if($this->user->mysql->user_role == 'Public' AND SITE_CAPTCHA )
                  $captcha_check =  $this->recaptcha_check_answer (SITE_CAPTCHA_PRIVATE,$_SERVER["REMOTE_ADDR"],$_REQUEST["recaptcha_challenge_field"],$_REQUEST["recaptcha_response_field"]);


             if ($captcha_check){
              $this->variables['error'] = '';
              // Call routine to perform add
              	$this->mysql->clear();
              	foreach(array_keys($this->fields) as $key){
              	  if($this->fields[$key]['type'] != 'file' && ($key != $this->primary_key OR $this->variables[$key] >0 OR strlen($this->variables[$key]) >2))
                      $this->mysql->$key = $this->variables[$key];
                  }

              	if($this->mysql->last_error != '' OR !$this->mysql->save()){
              		$this->variables['error']= $this->mysql->last_error;
                  }
              	else{
              	  $this->mysql->last_error = '';//reset error from primary_id being empty
                      $this->mysql->load();
                      $this->variables[$this->primary_key] = $this->mysql->{$this->primary_key};
                      //Check for files to upload
                      foreach(array_keys($this->fields) as $key){
                        if($this->fields[$key]['type'] == 'file' && isset($_FILES[$key])){
                          //Need primary_id to store uploaded file;
                          $this->upload_file($key);
                          if($this->variables['error'] == ''){
                            $this->mysql->$key = $this->variables[$key];
                            $this->mysql->save();
                            $this->variables['error']= $this->mysql->last_error;
                          }
                        }
                      }

                  }
             }
             else
                 $this->variables['error'] = "Captcha Did Not Match!";
             if($this->variables['error'] =='')
              		$this->variables['error'] = "Added";
                  if(!$this->variables['jquery'])
                      $this->show_add_edit_form();
                  else
                      echo $this->variables['error'];
               }
               else{
                   $this->variables['error'] = "Permission Denied";
                   echo $this->variables['error'];
               }
             break;

             case "Delete":
             if($this->permissions['delete']){
             	// Call routine to perform delete
            	if ( isset($this->variables[$this->primary_key]) ){
            		$this->mysql->{$this->primary_key} = $this->variables[$this->primary_key];
            		$this->mysql->load();
                    $files=array();
                    $change = "DELETED\n";
                    foreach(array_keys($this->fields) as $key){
                	  if($this->fields[$key]['type'] == 'file')//find out if there are any files in this table
                           $files[] =  $this->mysql->$key;
                      $change .= $key."=".$this->mysql->$key."\n";
                    }
            		    //Delete info in child tables
                	if(isset($this->children) && sizeof($this->children)>0){
                            foreach($this->children as $child){
                              if($child['get_page'] != ''){
                                  if(is_int((int)$child['get_page'])&& (int)$child['get_page'] >0){
                                        $pages = new PageClass;
                                        $child_page = $pages->load((int)$child['get_page']);
                                 }
                                  else{
                                    require_once SITEPATH . "/components/".$child['get_page'];
                                    eval("\$child_page = new ".str_replace("_","",substr($child['get_page'],strrpos($child['get_page'],'/')+1,-4))."Class();");
                                  }
                                    $child_page->user = $this->user;
                                    //$child_page->parent_key = $this->primary_key;
                                    //$child_page->parent_id = $this->variables[$this->primary_key];
                                    //$child_page->init_variables();
                                    $results = $child_page->mysql->get_all(array(array("field" => "{$this->primary_key}", "operator"=>"=", "argument"=>"{$this->variables[$this->primary_key]}")));
                                    echo $child_page->mysql->last_error;
                                    if(isset($results) && sizeof($results)>0){
                                      foreach($results as $result){
                                          $child_page->variables[$child_page->primary_key] = $result->{$child_page->primary_key};
                                          $child_page->action_check('Delete');
                                      }
                                    }
                              }
                            }
                        }
                        $this->mysql->delete();
            			$this->variables['error'] = $this->variables[$this->primary_key] ." Deleted";
                        /*if(isset($files)){
                          foreach($files as $file)
                              unlink(SITEPATH.$file);  //delete any files
                        }                         */
                        require_once SITEPATH . "/components/site/change_log.php";
                        $change_log = new ChangeLogClass($this->db);
                        $change_log->mysql->clear();
                        $change_log->mysql->table_name = $this->table_name;
                        $change_log->mysql->primary_key = $this->primary_key;
                        $change_log->mysql->key_id = $this->variables[$this->primary_key];
                        $change_log->mysql->change = $change;
                        $change_log->mysql->updated = $this->variables['updated'];
                        $change_log->mysql->updated_by = $this->variables['updated_by'];
                        $change_log->mysql->save();

                    if(!$this->variables['jquery'])
                        $this->show_results();
                }
            	else
            		$this->variables['error']="Missing $this->primary_key for delete";
             }
             else{
                   $this->variables['error'] = "Permission Denied";
                   echo $this->variables['error'];
               }
             break;

             case "Update":
             if($this->permissions['edit']){
               $captcha_check = true;
               if($this->user->mysql->user_role == 'Public' AND SITE_CAPTCHA )
                  $captcha_check =  $this->recaptcha_check_answer(SITE_CAPTCHA_PRIVATE,$_SERVER["REMOTE_ADDR"],$_REQUEST["recaptcha_challenge_field"],$_REQUEST["recaptcha_response_field"]);

             if ($captcha_check){
                $change = '';
                $this->variables['error'] = '';
             	// Call routine to perform update
             	if ( isset($this->variables[$this->primary_key]) ){
            		$this->mysql->clear();
                    $this->mysql->{$this->primary_key} =$this->variables[$this->primary_key];
            		$this->mysql->load();
            		foreach(array_keys($this->fields) as $key){
                	  if($this->fields[$key]['type'] != 'file'){

                	    if($this->mysql->$key != $this->variables[$key])
                            $change .= $this->get_change($this->mysql->$key,$key)."\n";
                        $this->mysql->$key = $this->variables[$key];
                      }
                    }
            		if($this->mysql->last_error == "" && $this->mysql->save()){
            			$this->variables['message'] = "Updated";
                        $this->mysql->load();
                        //Check for files to upload
                        foreach(array_keys($this->fields) as $key){
                          if($this->fields[$key]['type'] == 'file' && isset($_FILES[$key]) && $this->upload_file($key) ){
                            //Need primary_id to store uploaded file
                            $this->variables[$this->primary_key] = $this->mysql->{$this->primary_key};
                            if($this->variables['error'] == ''){
                              $this->mysql->$key = $this->variables[$key];
                              $this->mysql->save();
                              $this->variables['error']= $this->mysql->last_error;
                            }
                          }
                        }
                   }

                   if($this->variables['error'] == '')
                    $this->variables['error']= $this->mysql->last_error;
                   if($this->variables['error'] == ''){
                     $this->variables['error'] = 'Updated';
                     if($change != ''){
                       if(isset($this->parent_id) && isset($this->parent_key))
                        $change.=$this->parent_key."=".$this->parent_id."\n";
                        require_once SITEPATH . "/components/site/change_log.php";
                        $change_log = new ChangeLogClass($this->db);
                        $change_log->mysql->clear();
                        $change_log->mysql->table_name = $this->table_name;
                        $change_log->mysql->primary_key = $this->primary_key;
                        $change_log->mysql->key_id = $this->variables[$this->primary_key];
                        $change_log->mysql->change = $change;
                        $change_log->mysql->updated = $this->variables['updated'];
                        $change_log->mysql->updated_by = $this->variables['updated_by'];
                        $change_log->mysql->save();
                     }
                   }

                } else {$this->variables['error']="Missing {$this->primary_key} for update";}
            }
             else
                 $this->variables['error'] = "Captcha Did Not Match!";
               if(!$this->variables['jquery'])
                     $this->show_add_edit_form();
               else
                    echo $this->variables['error'];
            }
            else{
                   $this->variables['error'] = "Permission Denied";
                   echo $this->variables['error'];
               }
           break;
           case "Get_results":
                /*$counts =  $this->mysql->get_counts($this->where_criteria);
                $total_results = (int)$counts['cnt'];
                $html[] = $this->calculate_limits($total_results);

                $results = $this->mysql->get_all($this->where_criteria,$this->order_by,$this->limit,$this->select_criteria);
                $html[] = $this->results_table($results,$counts);
                $html[] = $total_results;

                echo json_encode($html);   */
                $this->show_results();
                exit;
           break;
           case "Get_Row":
                $this->where_criteria = NULL;
                $this->where_criteria[] = array("field" => "$this->primary_key", "operator"=>"=", "argument"=>"{$this->variables[$this->primary_key]}");
                $results = $this->mysql->get_all($this->where_criteria,$this->order_by,$this->limit,$this->select_criteria);
                echo $this->results_table($results);
                exit;
           break;
           case "Edit":
                if(isset($this->children) && sizeof($this->children)>0)
                    $this->show_tabs();
                else
                    $this->show_add_edit_form();
           break;
           case "Add_New":
                  $this->show_add_edit_form();
           break;
           case "Edit_Field":
                  //Load up the current value
                  $this->mysql->{$this->primary_key} = $this->variables['get_id'];
                  $this->mysql->load();
                  $this->variables[$this->variables['get_key']] =$this->mysql->{$this->variables['get_key']};
                  //Display the edit field
                  echo $this->edit_field($this->variables['get_key'],'lookup_field');
           break;
           case "Set_Field":
           if($this->permissions['edit']){
                  //Load/Save the current value
                  $this->mysql->{$this->primary_key} = $this->variables['get_id'];
                  $this->mysql->load();
                  $original =  $this->mysql->{$this->variables['get_key']};
                  $this->mysql->{$this->variables['get_key']} =$this->variables[$this->variables['get_key']];
                  $this->mysql->updated =$this->variables['updated'];
                  $this->mysql->updated_by =$this->variables['updated_by'];
                  $this->mysql->save();
                  $this->mysql->load();

                  if($original != $this->mysql->{$this->variables['get_key']}){
                       $change = $this->get_change($original,$this->variables['get_key'])."\n";
                       if(isset($this->parent_id) && isset($this->parent_key))
                         $change.=$this->parent_key."=".$this->parent_id."\n";
                       require_once SITEPATH . "/components/site/change_log.php";
                        $change_log = new ChangeLogClass($this->db);
                        $change_log->mysql->clear();
                        $change_log->mysql->table_name = $this->table_name;
                        $change_log->mysql->primary_key = $this->primary_key;
                        $change_log->mysql->key_id = $this->variables['get_id'];
                        $change_log->mysql->change = $change;
                        $change_log->mysql->updated = $this->variables['updated'];
                        $change_log->mysql->updated_by = $this->variables['updated_by'];
                        $change_log->mysql->save();
                  }

                  @ob_clean();
                  //Display the field
                  $response[] = $this->view_field($this->mysql,$this->variables['get_key']);
                  $response[] = $this->mysql->last_error;

                  echo json_encode($response);
           }
           break;
           case "Export":
           if($this->permissions['export']){
                  $results = $this->mysql->get_all($this->where_criteria,$this->order_by);
                  //print_r($results);
                  $this->export($results);
            }
           break;
           case "Autocomplete":
                  $subjects = "";
                  $subject = $this->variables['sort'];
                  $results = $this->mysql->get_sql("Select DISTINCT $subject FROM $this->table_name WHERE $subject LIKE '%".$this->variables[$subject]."%'");
                  foreach($results as $result){
                       $subjects .= $result[$subject].",";
                  }
                  $subjects = trim($subjects,',');
                  echo $subjects;
                  exit;
           break;
           case "Map":
                $this->show_map($_REQUEST['map_list']);
           break;
           case "Print":
                echo $this->print_report();
                exit();
           break;
           case "PDF":
                $this->pdf_report();
           break;
           case "Print_Image":
               $this->mysql->clear();
               $this->mysql->{$this->primary_key}= $this->variables[$this->primary_key];
               $this->mysql->load();
               $key =( isset($_REQUEST['key']) ? $_REQUEST['key'] : '');
               if($this->mysql->$key!= ""){
                    //stream file to user
                    echo $this->print_image($this->mysql->$key);
                    exit();
               }
           break;
           case "Download_File":
             if($this->user->mysql->download){
               $this->mysql->clear();
               $this->mysql->{$this->primary_key}= $this->variables[$this->primary_key];
               $this->mysql->load();
               $key =( isset($_REQUEST['key']) ? $_REQUEST['key'] : '');
               if($this->mysql->$key!= ""){
                    //stream file to user
                    $this->output_file($this->mysql->$key);
                    exit();
               }
             }
             break;
           default:
           //Checks the table to make sure all fields are present
           if(!isset($this->flat_table) || !$this->flat_table)
              $this->show_results();
           else{  //If this is a flat table we need to check if there has an entry and load it
                $key_lookup = $this->mysql->get_sql("SELECT `{$this->primary_key}` FROM `{$this->table_name}` ".(isset($this->parent_key)? "WHERE {$this->parent_key}={$this->parent_id} ":"")." ORDER BY `{$this->primary_key}` DESC LIMIT 1");
                if(!isset($key_lookup[0][$this->primary_key])){ //IF there is no entry we need to create it
                    $this->variables['updated'] = date('Y-m-d H:i:s');
                    $this->variables['updated_by'] = $this->user->mysql->first_name." ".$this->user->mysql->last_name;
                    foreach(array_keys($this->fields) as $key){

                	  if($this->fields[$key]['type'] != 'file' && $key != $this->primary_key)
                        $this->mysql->$key = $this->variables[$key];
                    }
                    if(isset($this->parent_key))
                        $this->mysql->{$this->parent_key} = $this->parent_id;
                    if(!$this->mysql->save())//Try to save an entry with just default values If that fails manually save an empty entry
                        $this->mysql->set_sql("INSERT INTO `{$this->table_name}` ({$this->parent_key},updated,updated_by) VALUES ({$this->parent_id},'{$this->variables['updated']}','{$this->variables['updated_by']}')");
                    $key_lookup = $this->mysql->get_sql("SELECT {$this->primary_key} FROM {$this->table_name} ORDER BY {$this->primary_key} DESC LIMIT 1");
                    echo $this->mysql->last_error;
                }
                $this->variables[$this->primary_key] = $key_lookup[0][$this->primary_key];
                $this->action_check('Edit');
           }
           break;
        }
    }
    function show_add_edit_form(){
       echo "<script type='text/javascript'> jQuery(function(){ \n";
       foreach ( array_keys($this->fields) as $key ){
          if(isset($this->fields[$key]['mask']))
            echo "jQuery('.".( isset($class) ? "{$class} " : "{$this->table_name}_{$key}")."').mask('".(isset($this->masks[$this->fields[$key]['mask']])?$this->masks[$this->fields[$key]['mask']]:$this->fields[$key]['mask'])."');\n";
       }
       echo "}); </script>\n";

       echo "\t<input class='current_page' type='hidden' name='". ($this->variables[$this->primary_key] != '' ? "Update" : "Add New")." ".ucwords($this->mysql->lookup_table_name())."' value='http://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?get_page={$this->page_id}".( isset($this->parent_id) && isset($this->parent_key) ? "&amp;{$this->parent_key}={$this->parent_id}&amp;parent_key={$this->parent_key}" : '')."'/>\n";
       //If the ID is set we need to gather all the data for an entry
       if($this->variables[$this->primary_key] != '' && !isset($this->variables['error'])){  //If we are updating we may have new info not yet saved due to an error result
       	$this->where_criteria = array(array("field" => $this->primary_key, "operator"=>"=", "argument"=>$this->variables[$this->primary_key]));
        $results = $this->mysql->get_all($this->where_criteria);
        foreach($results as $result){
          	foreach ( array_keys($result->lookup_fields()) as $key )
                        $this->variables[$key] = $result->$key;
        }
       }
       if(!isset($this->flat_table) || !$this->flat_table){
         //Check if we are in tabbed mode to show back button or not
          if((!isset($this->children) || $this->variables[$this->primary_key] == '') AND $this->variables['jquery']!= TRUE)
           echo "<a class='back' href='http://".$_SERVER['SERVER_NAME'] .$_SERVER['PHP_SELF']."?get_page={$this->page_id}".( isset($this->parent_id) && isset($this->parent_key) ? "&amp;{$this->parent_key}={$this->parent_id}&amp;parent_key={$this->parent_key}" : '')."'>Back To ".ucwords($this->mysql->lookup_table_name())." Search</a>";
          else if((!isset($this->children) || $this->variables[$this->primary_key] == ''))
            echo "<a class='close_row' href='http://".$_SERVER['SERVER_NAME'] .$_SERVER['PHP_SELF']."?action=Get_Row&amp;get_page={$this->page_id}&amp;{$this->primary_key}={$this->variables[$this->primary_key]}&amp;{$this->parent_key}={$this->parent_id}&amp;parent_key={$this->parent_key}'>Close</a>";
        }
        echo "<fieldset>\n";
        echo "<legend>". ($this->variables[$this->primary_key] != '' ? "Update" : "Add New")." ".ucwords(str_replace("_"," ",$this->mysql->lookup_table_name()))."</legend>\n";
        //Display error info
        echo "<div class='error result' style='text-align:center;text-transform: uppercase;'>";
        if(isset($this->variables['error'])){
            $error = explode(":",$this->variables['error']);
            echo $this->variables['error'];
        }
        echo "</div>";
        if(isset($this->variables['message']))
            echo "<div class='message'>".$this->variables['message']."</div>";
                         
        echo "<form accept-charset='utf-8' enctype='multipart/form-data'  class='default_form' action='http://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?get_page={$this->page_id}".( isset($this->parent_id) && isset($this->parent_key) ? "&amp;{$this->parent_key}={$this->parent_id}&amp;parent_key={$this->parent_key}" : '')."' method='post'>\n";
        foreach ( array_keys($this->fields) as $key ){

                echo "<div class='{$key}_field_container field_container ".(isset($this->fields[$key]['hidden'])?"hidden":"")." '>\n";
                echo "\t<label for='$key' ".(isset($this->fields[$key]['label']) && strlen($this->fields[$key]['label']) > 20?"style='width:450px;'":"").">".str_replace("_"," ",str_replace("_id"," ",(isset($this->fields[$key]['label'])?$this->fields[$key]['label']:$key))).(  isset($this->fields[$key]['min_length']) && $this->fields[$key]['min_length'] > 0 ? " *" : "")."</label>\n";
                $this->edit_field($key);
                if(isset($this->fields[$key]['description']))
                    echo "<div class='description'>".$this->fields[$key]['description']."</div>\n";
                echo "</div>\n";

       }
       if($this->user->mysql->user_role == 'Public' AND SITE_CAPTCHA){
             echo "<div style='margin-left:200px' class='field_container'><div id='recaptcha_div'></div></div>\n";
             echo "<script type='text/javascript'>Recaptcha.create('".SITE_CAPTCHA_PUBLIC."','recaptcha_div', {theme: 'red',callback: Recaptcha.focus_response_field});</script> ";
       }
       if(($this->permissions['edit'] && $this->variables[$this->primary_key] != '' ) || ($this->permissions['add'] && $this->variables[$this->primary_key] == ''  ))
            echo "\t<input name='action' type='submit' value='".($this->variables[$this->primary_key] != '' ? "Update" : "Add")."' />\n"; //If no ID is set this is a new entry
      echo "</form></fieldset>\n";
    }
    function show_results(){
    echo "\t<div class='results'>\n";
      echo "\t<input class='sort' type='hidden' ascending='".$this->variables['ascending']."' value='".$this->variables['sort']."'/>\n";
      echo "\t<input class='current_page' name='".ucwords($this->mysql->lookup_table_name())." Search' type='hidden' value='http://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?get_page={$this->page_id}".( isset($this->parent_id) && isset($this->parent_key) ? "&{$this->parent_key}={$this->parent_id}&parent_key={$this->parent_key}" : '')."'/>\n";
      echo "\t<input class='advanced_search' type='hidden' value='".$this->variables['advanced_search']."'/>\n";
        $counts =  $this->mysql->get_counts($this->where_criteria);

        $total_results = (int)$counts['cnt'];
        if($this->variables['advanced_search'] != 0)
            echo "<div class='advanced_container'>\n";
        echo "<fieldset style='border:0px'>\n";
          echo "<div class='option_container'>\n";
          if(!isset($this->parent_key)){
            echo "\t<div class='table_options' style='float:left;padding:0px 10px;'>\n";
            echo "\t\t<a href='http://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?get_page={$this->page_id}".( $this->variables['advanced_search'] == 1 ? "&amp;advanced_search=1" : "")."' class='clear'>Reset Search</a>\n";
            if($this->permissions['export'] )
                echo "\t\t<a href='http://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?get_page={$this->page_id}' class='export'>Export</a>\n";
            echo "\t\t<a class='edit_link' href='http://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'].( $this->variables['advanced_search'] == 1 ? "?get_page={$this->page_id}'>Basic Search" : "?get_page={$this->page_id}&amp;advanced_search=1'>Advanced Search")."</a>\n";
            echo "\t</div>\n";
         }
            echo "\t<div class='total_results'>$total_results Results</div>\n";
            echo "\t<div class='page_list'>\n";
            echo $this->calculate_limits($total_results);
            echo "\t</div>\n";
          echo "</div>\n";

            echo "<table class='content_table'>\n";
            echo "<thead>\n";
        	echo "\t<tr>\n";
            echo "\t\t<th style='background-color:white;'>";

            if(isset($this->parent_key)&& $this->permissions['add'] )
                echo"\t\t\t<a class='edit_row' href='http://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?action=Add_New&amp;get_page={$this->page_id}".( isset($this->parent_id) && isset($this->parent_key) ? "&amp;{$this->parent_key}={$this->parent_id}&amp;parent_key={$this->parent_key}" : '')."'>Add_New</a>";
            else if (isset($this->multiple_execute)){
                echo "<div style='display:block;width:125px'>\n";
                echo "\t\t\t<input type='checkbox' class='update_checkboxes' style='float:left;'>\n";
                echo "\t\t\t<select class='bulk' name='bulk' style='float:left'><option></option>\n";
                foreach($this->multiple_execute as $this->variables['action'])
                    echo "\t\t\t\t<option>{$this->variables['action']}</option>\n";
                echo "\t\t\t</select>\n";
                echo "</div>\n";
            }
            echo "\t\t</th>\n";
            $quick_add = FALSE;
            foreach ( array_keys($this->fields) as $key ){
                //if(!isset($this->fields[$key]['searchable']) && (isset($this->fields[$key]['min_length']) && $this->fields[$key]['min_length'] > 0 && (!isset($this->fields[$key]['hidden']) || $this->fields[$key]['hidden'] != TRUE) ) ) //IF a field isn't searchable but is required, don't allow quick add
                //  $quick_add = FALSE;
                if(isset($this->fields[$key]['searchable']) || ($this->variables['advanced_search'] == 1 && (!isset($this->fields[$key]['hidden']) || $this->fields[$key]['hidden'] != TRUE)))
                    echo "\t\t<th value='{$key}' class='header'>".str_replace("_"," ",str_replace("_id"," ",$key))."</th>\n";
            }
        	echo "\t</tr>\n";
            if(!isset($this->parent_key)){
              echo "<tr>\n";
              if($quick_add)
                  echo "<td><input type='hidden' name='get_page' value'{$this->page_id}'/><input class='quick_add' type='submit' name='action' value='Add' /></td>\n";
              else
                  echo "<td>".($this->permissions['add']?"<a class='edit_row' href='http://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?action=Add_New&amp;get_page={$this->page_id}".( isset($this->parent_id) && isset($this->parent_key) ? "&amp;{$this->parent_key}={$this->parent_id}&amp;parent_key={$this->parent_key}" : '')."'>Add_New</a>":"")."</td>";

                foreach ( array_keys($this->fields) as $key ){
                  if(isset($this->fields[$key]['searchable']) || ($this->variables['advanced_search'] == 1 && (!isset($this->fields[$key]['hidden']) || $this->fields[$key]['hidden'] != TRUE))){
                    echo "<td>";
                    $this->edit_field($key,(isset($this->parent_key)?'':'Filter'));
                    echo"</td>\n";
                  }
                }
              echo "</tr>\n";
            }
            echo "</thead>\n";
            echo "<tbody>\n";
            if($total_results >0){
               $results = $this->mysql->get_all($this->where_criteria,$this->order_by,$this->limit);
               echo $this->results_table($results,$counts);
            }
            echo "</tbody>\n";
        	echo "</table>\n";

        echo "</fieldset>\n";
        if($this->variables['advanced_search'] == 1)
            echo "</div>\n";
        echo "</div>\n";
    }
    function edit_field($key,$class=NULL){
      $type = (isset($this->fields[$key]['type'])?$this->fields[$key]['type']:'text');
            echo "<div class='{$key}_{$this->table_name}_field {$type}_field'>\n";
            switch($type){
                    case "list_other":
                    case "list":
                        if($type == "list_other")
                            echo "<div id='".$key."_other_option'>\n";
                        echo "\t<select class='".( isset($class) ? "{$class} " : ( $type == "list_other" ? "{$this->table_name}_{$key} other_option" : "{$this->table_name}_{$key} ")). ( isset($error) && $key == $error[0] ? 'error ' : '')."' ".( isset($this->fields[$key]['style']) ? "style='".$this->fields[$key]['style']."'" : "") ." ".( isset($this->fields[$key]['js'])  ? ' onchange="'.$this->fields[$key]['js'].'"' : '')." name='$key'>\n";
                        echo "\t\t<option></option>\n";
                        asort($this->fields[$key]['options']);
                        foreach($this->fields[$key]['options'] as $option)
                            echo "\t\t<option ". ( $this->variables[$key] == $option ? "selected='selected'" : "") .">$option</option>\n";
                        if($type == "list_other")
                            echo "\t\t<option>Other</option>\n";
                        echo "\t</select>\n";
                        if($type == "list_other")
                            echo "</div>\n";
                    break;
                    case "range":
                        echo "\t<select class='".( isset($class) ? "{$class} " : "{$this->table_name}_{$key} "). ( isset($error) && $key == $error[0] ? 'error ' : '')."' ".( isset($this->fields[$key]['style']) ? "style='".$this->fields[$key]['style']."'" : "") ." ".( isset($this->fields[$key]['js'])  ? " onclick='".$this->fields[$key]['js']."'" : '')."  name='$key'>\n";
                        echo "\t\t<option></option>\n";
                        for($i = $this->fields[$key]['options'][0];$i <= $this->fields[$key]['options'][1];$i++)
                            echo "\t\t<option ".( $this->variables[$key] == $i ? "selected" : "") .">$i</option>\n";
                        echo "\t</select>\n";
                    break;
                    case "auto":
                        echo "<div id='".$key."_other_option'>\n";
                        $this->show_dropdown($this->table_name,$key,$key,$this->variables[$key],( isset($this->fields[$key]["where"]) ? $this->fields[$key]["where"] : " WHERE {$key} IS NOT NULL and {$key} != ''")." ORDER BY {$key} ",( isset($class) ? "{$class} other_option" : "{$this->table_name}_{$key} other_option"),NULL,( isset($this->fields[$key]['style']) ? "style='".$this->fields[$key]['style']."'" : ""));
                        echo "</div>\n";
                    break;
                    case "distinct_list":
                    case "table_link":
                        $this->show_dropdown($this->fields[$key]['lookup_table'],$this->fields[$key]['lookup_field'],$key,$this->variables[$key],( isset($this->fields[$key]["where"]) ? $this->fields[$key]["where"] : NULL), ( isset($class) ? "{$class} " : "{$this->table_name}_{$key} "),( isset($this->fields[$key]["lookup_id"]) ? $this->fields[$key]["lookup_id"] : NULL),( isset($this->fields[$key]['style']) ? "style='".$this->fields[$key]['style']."'" : ""),( isset($this->fields[$key]["js"]) ? $this->fields[$key]["js"] : NULL));
                    break;
                    case "table_link-checkboxes":
                        echo "<ul class='".( isset($class) ? "{$class} " : "{$this->table_name}_{$key} "). ( isset($error) && $key == $error[0] ? 'error ' : '')."'>\n";
                        $list = explode(',',$this->variables[$key]);
                        $results = $this->mysql->get_sql("Select DISTINCT `".( isset($this->fields[$key]["lookup_id"]) ? $this->fields[$key]["lookup_id"] : $key)."`,`".$this->fields[$key]['lookup_field']."` FROM `".$this->fields[$key]['lookup_table']."` ".( isset($this->fields[$key]["where"]) ? $this->fields[$key]["where"] : "")." ORDER BY `".$this->fields[$key]['lookup_field']."`");
                        //echo $this->mysql->last_error;
                        if(is_array($results)){
                          foreach($results as $result){
                                  echo "<li>\n";
                                  echo "\t<input ".( isset($this->fields[$key]['style']) ? "style='".$this->fields[$key]['style']."'" : "") ." class='".( isset($class) ? "{$class} " : "{$this->table_name}_{$key} "). ( isset($error) && $key == $error[0] ? 'error ' : '')."' name='{$key}[]' value='".$result[( isset($this->fields[$key]["lookup_id"]) ? $this->fields[$key]["lookup_id"] : $key)]."' ". (in_array($result[( isset($this->fields[$key]["lookup_id"]) ? $this->fields[$key]["lookup_id"] : $key)],$list) ? "checked='checked'" : "").( isset($this->fields[$key]['js'])  ? ' onclick="'.$this->fields[$key]['js'].'"' : '')." type='checkbox'/>\n";
                                  echo "\t".str_replace("_"," ",$result[$this->fields[$key]['lookup_field']])."\n";
                                  echo "</li>\n";
                          }
                        }
                        echo "</ul>\n";
                    break;
                    case "radio-list":
                        echo "<ul class='{$type}'>\n";
                        $list = explode(',',$this->variables[$key]);
                        foreach($this->fields[$key]['options'] as $option){
                                echo "<li>\n";
                                echo "\t<input  ".( isset($this->fields[$key]['style']) ? "style='".$this->fields[$key]['style']."'" : "") ." class='".( isset($class) ? "{$class} " :""). "{$this->table_name}_{$key} ". ( isset($error) && $key == $error[0] ? 'error ' : '')."' name='{$key}' value='".$option."' ". (in_array($option,$list) ? "checked='checked'" : "")." type='radio' />\n";
                                echo "\t".str_replace("_"," ",$option)."\n";
                                echo "</li>\n";
                        }
                        echo "</ul>\n";
                    break;
                    case "checkbox-list":
                        echo "<ul class='{$type}'>\n";
                        $list = explode(',',$this->variables[$key]);
                        foreach($this->fields[$key]['options'] as $option){
                                echo "<li>\n";
                                echo "\t<input  ".( isset($this->fields[$key]['style']) ? "style='".$this->fields[$key]['style']."'" : "") ." class='".( isset($class) ? "{$class} " : "{$this->table_name}_{$key} "). ( isset($error) && $key == $error[0] ? 'error ' : '')."' name='{$key}[]' value='".$option."' ". (in_array($option,$list) ? "checked='checked'" : "")." type='checkbox' />\n";
                                echo "\t".str_replace("_"," ",$option)."\n";
                                echo "</li>\n";
                        }
                        echo "</ul>\n";
                    break;
                    case "bool":
                      echo "\t\t<input style='width:auto;display:inline;margin-left:5px;border:0;'  type='radio' name='$key' class='".( isset($class) ? "{$class} " : "{$this->table_name}_{$key} "). ( isset($error) && $key == $error[0] ? 'error ' : '')."'  value='1' ".( isset($this->fields[$key]['js'])  ? ' onclick="'.$this->fields[$key]['js'].'"' : '')." ".( $this->variables[$key] == 1 ? "checked" : "") ."/>Yes\n";
                      echo "\t\t<input style='width:auto;display:inline;margin-left:20px;border:0;float:none;'  type='radio' name='$key' class='".( isset($class) ? "{$class} " : "{$this->table_name}_{$key} "). ( isset($error) && $key == $error[0] ? 'error ' : '')."'  value='0' ".( isset($this->fields[$key]['js'])  ? ' onclick="'.$this->fields[$key]['js'].'"' : '')." ".( $this->variables[$key] == 0 ? "checked" : "") ."/>No\n";
                    break;
                    case "checkbox":
                        echo "\t<input class='".( isset($class) ? "{$class} " : "{$this->table_name}_{$key} "). ( isset($error) && $key == $error[0] ? 'error ' : '')."' ".( isset($this->fields[$key]['style']) ? "style='".$this->fields[$key]['style']."'" : "") ."  name='$key' type='checkbox' value='TRUE' ". ($this->variables[$key] ==TRUE ? "checked='checked'" : "")." ".( isset($this->fields[$key]['js'])  ? ' onclick="'.$this->fields[$key]['js'].'"' : '')."/>\n";
                    break;
                    case "textarea":
                        echo "\t<textarea class='".( isset($class) ? "{$class} " : "{$this->table_name}_{$key} "). ( isset($error) && $key == $error[0] ? 'error ' : '')."' ".( isset($this->fields[$key]['style']) ? "style='".$this->fields[$key]['style']."'" : "") ."  name='$key' rows='".( isset($this->fields[$key]['rows']) ? $this->fields[$key]['rows'] : "4") ."' ".( isset($this->fields[$key]['js'])  ? ' onchange="'.$this->fields[$key]['js'].'"' : '')." cols='20'>". $this->variables[$key]."</textarea>\n";
                    break;
                    case "file":
                        if($class != 'Filter'){
                            echo "<iframe onload='check_iframe_loading(jQuery(this))' name='submit_iframe_{$this->table_name}_{$key}' src='' style='width:0;height:0;border:0px solid #fff;'><html></html></iframe>\n";
                            echo "\t<input style='margin:0;width:250px;display:inline;' type='file' name='{$key}[]' target='submit_iframe_{$this->table_name}_{$key}' multiple='true'/>";
                        }
                        else
                            echo "<input class='Filter' name='{$key}' type='text'>\n";
                    break;
                    default:
                        echo ($type=="currency"?"<div style='display:inline;float:left;'>$</div>":"").'<input  class="'.( isset($class) ? "{$class} " : "{$this->table_name}_{$key} "). ( isset($error) && $key == $error[0] ? 'error ' : '').''. ( isset($this->fields[$key]['type']) && ($this->fields[$key]['type'] =='timestamp' || $this->fields[$key]['type'] =='date') ? 'datepickerclass ' : ( $this->fields[$key]['type'] =='time' ? 'timepickerclass ' : '')).'" '.( isset($this->fields[$key]['style']) ? 'style="'.$this->fields[$key]['style'].'"' : "").' name="'.$key.'" type="'. ( $this->fields[$key]['type'] =='password' ? 'password' : 'text').'" onkeyup="'.$this->constrain_field($key).";".( isset($this->fields[$key]['js'])  ? $this->fields[$key]['js'] : '').'" value="'.$this->variables[$key].'"'.( (isset($this->fields[$key]['readyonly']) && $this->fields[$key]['readyonly']) || ((stripos($_SERVER['HTTP_USER_AGENT'],'iPad') || stripos($_SERVER['HTTP_USER_AGENT'],'android')) &&  ($this->fields[$key]['type'] =='timestamp' || $this->fields[$key]['type'] =='date'))?" readonly='TRUE'":"").'/>';
                    break;
                }
            echo "</div>\n";
    }
    function view_field(&$result,$key){
        $return = '';
        switch((isset($this->fields[$key]['type'])?$this->fields[$key]['type']:'text')){
                    case "table_link":
                         $return= $this->lookup_id($this->fields[$key]['lookup_table'],$this->fields[$key]['lookup_field'],( isset($this->fields[$key]["lookup_id"]) ? $this->fields[$key]["lookup_id"] : $key),$result->$key);
                    break;
                    case "table_link-checkboxes":
                         $list = explode(',',$result->$key);
                         foreach($list as $item)
                            $return.= $this->lookup_id($this->fields[$key]['lookup_table'],$this->fields[$key]['lookup_field'],( isset($this->fields[$key]["lookup_id"]) ? $this->fields[$key]["lookup_id"] : $key),$item)." ";
                    break;
                    case "currency":
                         $return= "$".number_format($result->$key, 2, '.', '');
                    break;
                    case "double":
                         $return= number_format($result->$key, 2, '.', '');
                    break;
                    case "int":
                    case "integer":
                            $return = $result->$key;
                    break;
                    case "file":
                        if(in_array(substr($result->$key,(strrpos($result->$key,'.')?strrpos($result->$key,'.')+1:0)),array('jpg','jpeg','bmp','png','gif','tiff')))
                            $return.= "\t\t<a style='cursor: pointer;' onclick='open_window(\"http://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?get_page={$this->page_id}&amp;action=Print_Image&amp;{$this->primary_key}={$result->{$this->primary_key}}&amp;key={$key}&amp;jquery=true&amp;rand=".rand()."\",600,700); return false;' href='".$_SERVER['PHP_SELF']."?get_page={$this->page_id}&amp;action=Print_Image&amp;{$this->primary_key}={$result->{$this->primary_key}}&amp;key={$key}&amp;jquery=true&amp;rand=".rand()."' target='_blank'><img src='view/page/images/printer.png' width='20' height='20' alt='Print' title='Print'/></a>\n";
                        if($this->user->mysql->download)
                             $return.= "<a alt='download' title='download' class='open' href='http://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?get_page={$this->page_id}&amp;action=Download_File&amp;{$this->primary_key}={$result->{$this->primary_key}}&amp;key={$key}&jquery=true' target='_blank'>".basename($result->$key)."</a>";
                        else
                             $return.=basename($result->$key);
                    break;
                    default:
                         $return= $result->$key;
                    break;
        }
        return $return;
    }
    function constrain_field($key){
        $return = '';
        switch((isset($this->fields[$key]['type'])?$this->fields[$key]['type']:'text')){
                    case "double":
                    case "currency":
                         $return= 'if(!/^[-+]?\d*\.?\d*$/.test(jQuery(this).val())){jQuery(this).val(jQuery(this).val().substring(0,jQuery(this).val().length-1));}';
                    break;
                    case "integer":
                         $return= 'if(!/^(\+|-)?\d+_*?$/.test(jQuery(this).val())){jQuery(this).val(jQuery(this).val().substring(0,jQuery(this).val().length-1));}';
                    break;
        }
        return $return;
    }
    function results_table(&$results,&$counts = NULL){
        $odd = false;
        $answer = '';
        //Display the contents of each field in array
        if(!empty($results) ){
          if(!empty($counts) ){
                $answer .=  $this->total_row($counts,$results);
          }
          foreach ( $results as $result )	{

              $answer .= "\t<tr class='".$result->{$this->primary_key}."{$this->table_name}row".( $odd ? ' odd' : '')."'>\n";
              $answer .= "\t\t<td>".$this->row_controls($result)."</td>\n";
              foreach ( array_keys($this->fields) as $key ) {
                if(isset($this->fields[$key]['searchable']) || ($this->variables['advanced_search'] == 1 && (!isset($this->fields[$key]['hidden']) || $this->fields[$key]['hidden'] != TRUE))){//Only display searchable fields in table view
                    $answer .= "\t\t<td><div lookup_id='{$result->{$this->primary_key}}' key='$key' ".(((!isset($this->fields[$key]['type']) || $this->fields[$key]['type']  != 'file') && ($key != $this->primary_key) && $this->permissions['edit'] )?"class='edit_field'":"").">".$this->view_field($result,$key)."</div></td>\n";
                }
              }
              $answer .= "\t</tr>\n";
              $odd = !$odd;
           }
         }
         return $answer;
    }
    public function total_row(&$counts,&$results){
            $answer = '';
            $totals = FALSE;
            $total_row = '<tr><td><b>TOTALS</b></td>';
            foreach ( array_keys($this->fields) as $key ){
              if(isset($this->fields[$key]['searchable']) || ($this->variables['advanced_search'] == 1 && (!isset($this->fields[$key]['hidden']) || $this->fields[$key]['hidden'] != TRUE))){
                      $total_row .= "<td>";
                      if(isset($counts[$key])  ){
                          $total_row .= $counts[$key];
                          $totals = TRUE;
                      }
                      $total_row .="</td>";
               }
            }
            if($totals)
                $answer =  $total_row."</tr>\n";
            return $answer;
    }
    public function row_controls($result){
            $answer = '';
              if (isset($this->multiple_execute))
                $answer .="<input class='checkboxes' type='checkbox' value='{$result->{$this->primary_key}}'>";
              if($this->printable)
                $answer .= "\t\t<a style='cursor: pointer;' onclick='open_window(\"http://".$_SERVER['SERVER_NAME'] .$_SERVER['PHP_SELF']."?get_page={$this->page_id}&amp;action=Print&amp;{$this->primary_key}={$result->{$this->primary_key}}&amp;jquery=true&amp;rand=".rand()."\",600,700); return false;' href='".$_SERVER['PHP_SELF']."?action=Print&amp;{$this->primary_key}={$result->{$this->primary_key}}&amp;jquery=true&amp;rand=".rand()."' target='_blank'><img src='view/page/images/printer.png' width='20' height='20' alt='Print' title='Print'/></a>\n";
              if(is_array($this->reports))
                $answer .= "\t\t<a style='cursor: pointer;' target='_blank' href='http://".$_SERVER['SERVER_NAME'] .$_SERVER['PHP_SELF']."?get_page={$this->page_id}&amp;action=PDF&amp;{$this->primary_key}={$result->{$this->primary_key}}".( isset($this->parent_id) && isset($this->parent_key) ? "&amp;{$this->parent_key}={$this->parent_id}&amp;parent_key={$this->parent_key}" : '')."&amp;jquery=true&amp;rand=".rand()."'><img src='view/page/images/pdf.png' width='20' height='20' alt='PDF' title='PDF'/></a>\n";
              if($this->mapable)
                $answer .= "\t\t<a href='' onclick='open_window(\"http://".$_SERVER['SERVER_NAME'] .$_SERVER['PHP_SELF']."?get_page={$this->page_id}&amp;action=Map&amp;jquery=true&amp;map_list={$result->{$this->primary_key}}\",500,500); return false;'><img src='view/page/images/google_maps_icon.png' width='20' height='20' alt='Map' title='Map'/></a>\n";

              $answer .="\t\t<a class='edit_row' href='http://".$_SERVER['SERVER_NAME'] .$_SERVER['PHP_SELF']."?get_page={$this->page_id}&amp;action=Edit&amp;{$this->primary_key}={$result->{$this->primary_key}}".( isset($this->parent_id) && isset($this->parent_key) ? "&amp;{$this->parent_key}={$this->parent_id}&amp;parent_key={$this->parent_key}" : '')."'><img src='view/page/images/pencil.png' alt='Edit' title='Edit' width='20' height='20' /></a>\n";
              if($this->permissions['delete'])
                $answer .= "\t\t<a row='{$result->{$this->primary_key}}{$this->table_name}' class='delete_row' href='http://".$_SERVER['SERVER_NAME'] .$_SERVER['PHP_SELF']."?get_page={$this->page_id}&amp;action=Delete&amp;$this->primary_key={$result->{$this->primary_key}}".( isset($this->parent_id) && isset($this->parent_key) ? "&amp;{$this->parent_key}={$this->parent_id}&amp;parent_key={$this->parent_key}" : '')."'><img src='view/page/images/cross.png' alt='Delete' title='Delete' width='20' height='20' /></a>";
             return $answer;
    }
    public function show_tabs(){
            //Create tabs
            if(!isset($this->flat_table) || !$this->flat_table)
                echo ($this->variables['jquery']== TRUE?"<a class='close_row' href='http://".$_SERVER['SERVER_NAME'] .$_SERVER['PHP_SELF']."?action=Get_Row&amp;get_page={$this->page_id}&amp;{$this->primary_key}={$this->variables[$this->primary_key]}".( isset($this->parent_id) && isset($this->parent_key) ? "&amp;{$this->parent_key}={$this->parent_id}&amp;parent_key={$this->parent_key}" : '')."'>Close</a>":"<a class='back' href='http://".$_SERVER['SERVER_NAME'] .$_SERVER['PHP_SELF']."?get_page={$this->page_id}'>Back To Search</a>");
            echo (isset($this->tab_header)?$this->tab_header:"");
            echo "<div class='tabs'>\n";
            echo "\t<ul>\n";
            echo "\t\t<li><a href='#tabs-parent-".$this->table_name."-{$this->primary_key}-{$this->variables[$this->primary_key]}'>".ucfirst($this->page_title)."</a></li>\n";
            foreach($this->children as $title =>$child)
              echo "\t\t<li><a href='#tabs-".str_replace(" ","",$title)."-{$this->primary_key}-{$this->variables[$this->primary_key]}'>".ucfirst($title)."</a></li>\n";
            echo "\t</ul>\n";

            //Populate tab content
            echo "\t<div id='tabs-parent-".$this->table_name."-{$this->primary_key}-{$this->variables[$this->primary_key]}'>\n";
                //$this->parent_key = $this->primary_key; //Indicates there are children present
                $this->show_add_edit_form();
            echo "\t</div>\n";
            foreach($this->children as $title =>$child){
              //Check if this is a static or generated page
              if(is_int((int)$child['get_page'])&& (int)$child['get_page'] >0){
                    $pages = new PageClass;
                    $child_page = $pages->load((int)$child['get_page']);
                    //$title = $child_page->mysql->page_title;
                }
                else{
                require_once SITEPATH . "/components/".$child['get_page'];
                eval("\$child_page = new ".str_replace("_","",substr($child['get_page'],strrpos($child['get_page'],'/')+1,-4))."Class();");
                //$title = str_replace("_","",substr($child['get_page'],strrpos($child['get_page'],'/')+1,-4));
              }
              echo "\t<div id='tabs-".str_replace(" ","",$title)."-{$this->primary_key}-{$this->variables[$this->primary_key]}' class='ui-tabs-hide'>\n";
                $child_page->user = $this->user;
                $child_page->parent_key = $this->primary_key;
                $child_page->parent_id = $this->variables[$this->primary_key];
                $child_page->default_results = 1000;
                if($child['action'] == 'Edit') //If we are in edit mode and a child page, don't show the close button
                    $child_page->show_close_link =FALSE;
                $child_page->action_check($child['action']);
              echo "\t</div>\n";
            }
            //Initialize tabs
            echo "<script type='text/javascript'>var current_tab = '#".( isset($_REQUEST['tab']) ? $_REQUEST['tab'] : 'tabs-parent-'.str_replace("_","",substr($this->page_id,strpos($this->page_id,'/')+1,-4))."-{$this->variables[$this->primary_key]}")."';";
            echo "var current_page = '".( isset($_REQUEST['page']) ? $_REQUEST['page'] : '')."';";
            echo "var index_page = '".$_SERVER['PHP_SELF']."';";
            echo 'jQuery(".tabs").tabs({ show: {effect: "slide",direction: "up",duration: 500},hide: {effect: "slide",direction: "up",duration: 500}  }).tabs("option", "disabled", false);</script>';
            echo "\t</div>\n";
    }
    function get_change($old_value,$key){
      $return = "Changed {$key} ";
      switch((isset($this->fields[$key]['type'])?$this->fields[$key]['type']:'text')){
        case "table_link-checkboxes":
            $list = explode(',',$this->variables[$key]);
            $list2 = explode(',',$old_value);
            foreach($list as $item){
                if(!in_array($item,$list2))
                    $return .= "ADDED ". $this->lookup_id($this->fields[$key]['lookup_table'],$this->fields[$key]['lookup_field'],( isset($this->fields[$key]["lookup_id"]) ? $this->fields[$key]["lookup_id"] : $key),$item,( isset($this->fields[$key]["where"]) ? $this->fields[$key]["where"] : ""))." ";
            }
            foreach($list2 as $item){
                if(!in_array($item,$list))
                    $return .= "REMOVED ".$this->lookup_id($this->fields[$key]['lookup_table'],$this->fields[$key]['lookup_field'],( isset($this->fields[$key]["lookup_id"]) ? $this->fields[$key]["lookup_id"] : $key),$item,( isset($this->fields[$key]["where"]) ? $this->fields[$key]["where"] : ""))." ";
            }
        break;
        case "table_link":
            $return.= "FROM ".$this->lookup_id($this->fields[$key]['lookup_table'],$this->fields[$key]['lookup_field'],( isset($this->fields[$key]["lookup_id"]) ? $this->fields[$key]["lookup_id"] : $key),$old_value,( isset($this->fields[$key]["where"]) ? $this->fields[$key]["where"] : ""))." TO ". $this->lookup_id($this->fields[$key]['lookup_table'],$this->fields[$key]['lookup_field'],( isset($this->fields[$key]["lookup_id"]) ? $this->fields[$key]["lookup_id"] : $key),$this->variables[$key],( isset($this->fields[$key]["where"]) ? $this->fields[$key]["where"] : "WHERE 1"));
        break;
        default:
            $return.= "FROM {$old_value} TO {$this->variables[$key]}";
        break;
      }
      return $return;
    }
    function calculate_limits($total_results)
	{   $answer ="<div style='width:500px;float:left;'>";
	    $results_per_page = ( isset($_REQUEST['rpp']) && $_REQUEST['rpp'] > 0 ? (int)$_REQUEST['rpp'] : (isset($this->default_results)?$this->default_results:25));
		$num_pages = ceil($total_results/$results_per_page);
		$current_page = (int)( isset($_REQUEST['page']) && $_REQUEST['page'] > 1 ? (int)$_REQUEST['page'] : 1); // must be numeric > 0
		if($current_page > $num_pages && $num_pages > 0) $current_page = $num_pages;
		$prev_page = $current_page-1;
		$next_page = $current_page+1;

		if($num_pages > 10)
		{
			$answer .= ($current_page != 1 && $total_results >= 10) ? "<a class=\"result_limiter\" href=\"http://{$_SERVER['SERVER_NAME']}{$_SERVER['PHP_SELF']}?get_page={$this->page_id}".( isset($this->parent_id) && isset($this->parent_key) ? "&amp;{$this->parent_key}={$this->parent_id}&amp;parent_key={$this->parent_key}" : '').(isset($this->variables['advanced_search'])?"&advanced_search=".$this->variables['advanced_search']:"")."&page=$prev_page&rpp=$results_per_page\">&laquo; Previous</a> ":"<span class=\"inactive\" href=\"#\">&laquo; Previous</span> ";
			$start_range = $current_page - floor(7/2);
			$end_range = $current_page + floor(7/2);
			if($start_range <= 0)
			{
				$end_range += abs($start_range)+1;
				$start_range = 1;
			}
			if($end_range > $num_pages)
			{
				$start_range -= $end_range-$num_pages;
				$end_range = $num_pages;
			}
			$range = range($start_range,$end_range);

			for($i=1;$i<=$num_pages;$i++)
			{
				if($range[0] > 2 && $i == $range[0])
                    $answer .= " ... ";
				// loop through all pages. if first, last, or in range, display
				if($i==1 Or $i==$num_pages Or in_array($i,$range))
					$answer .= ($i == $current_page) ? "<a title=\"Go to page $i of $num_pages\" class=\"current\" href=\"#\">$i</a> ":"<a class=\"result_limiter\" title=\"Go to page $i of $num_pages\" href=\"http://{$_SERVER['SERVER_NAME']}{$_SERVER['PHP_SELF']}?get_page={$this->page_id}".(isset($this->variables['advanced_search'])?"&advanced_search=".$this->variables['advanced_search']:"")."&page=$i&rpp=$results_per_page\">$i</a> ";
				if($range[6] < $num_pages-1 && $i == $range[6])
                    $answer .= " ... ";
			}
			$answer .= (($current_page != $num_pages && $total_results >= 10) && !isset($_GET['page']) ) ? "<a title =\"Next\" class=\"result_limiter\" href=\"http://{$_SERVER['SERVER_NAME']}{$_SERVER['PHP_SELF']}?get_page={$this->page_id}".( isset($this->parent_id) && isset($this->parent_key) ? "&amp;{$this->parent_key}={$this->parent_id}&amp;parent_key={$this->parent_key}" : '').(isset($this->variables['advanced_search'])?"&advanced_search=".$this->variables['advanced_search']:"")."&page=$next_page&rpp=$results_per_page\">Next &raquo;</a>\n":"<span class=\"inactive\" href=\"#\">&raquo; Next</span>\n";

		}
		else
		{
			for($i=1;$i<=$num_pages;$i++)
			{
				$answer .= ($i == $current_page ? "<a class=\"current\" href=\"#\">$i</a> ":"<a class=\"result_limiter\" href=\"http://{$_SERVER['SERVER_NAME']}{$_SERVER['PHP_SELF']}?get_page={$this->page_id}".( isset($this->parent_id) && isset($this->parent_key) ? "&amp;{$this->parent_key}={$this->parent_id}&amp;parent_key={$this->parent_key}" : '').(isset($this->variables['advanced_search'])?"&advanced_search=".$this->variables['advanced_search']:"")."&page=$i&rpp=$results_per_page\">$i</a> ");
			}
		}
		$low = ($current_page-1) * $results_per_page;
		$this->limit = " LIMIT $low,$results_per_page";

		$rpp_array = array(10,20,25,50,100);
        $answer .="</div>\n";
        $answer .="<div style='float:right;display:inline;'>\n";
        $items = "<option selected value=\"$results_per_page\">$results_per_page</option>";
		foreach($rpp_array as $rpp_opt)
            $items .= "<option value=\"$rpp_opt\">$rpp_opt</option>";
		$answer .= "\t\t<span class=\"result_limiter\">Results per page:<select class=\"rpp\" >$items</select></span>\n";
        $answer .="</div>\n";
        return $answer;
	}
    function lookup_id($table_name,$fields,$id_name,$id, $where = ""){
      if(isset($id) && $id != ''){
             $results = $this->mysql->get_sql("Select $fields FROM $table_name WHERE `$id_name`='$id' LIMIT 1");
             //echo $this->mysql->last_error;
             foreach($results as $result){
               $answer = "";
               foreach(explode(',',$fields) as $field)
                    $answer .= $result[$field]. " ";
               $answer = rtrim($answer);
               return $answer;
             }
       }
       else return "";
    }
    function show_dropdown($table_name,$fields,$id,$value = null, $WHERE = null, $class='Filter',$lookup_id = NULL,$style= NULL,$onchange = NULL){
             $this->mysql->last_error ='';
             echo "<select class='$class' name='{$id}' ".(isset($onchange)?'onchange="'.$onchange.'"':"")." ".(isset($style)?$style:"").">\n";
             $id = ( isset($lookup_id) ? $lookup_id : $id);
             echo "<option></option>";
             if(!isset($WHERE) || $WHERE == '')
               $WHERE = " ORDER BY ".$fields;
             $sql ="Select DISTINCT $fields,$table_name.$id FROM $table_name {$WHERE} ";
             $results = $this->mysql->get_sql(html_entity_decode($sql,ENT_QUOTES,'UTF-8'));
             if($this->mysql->last_error != '')
                     echo $this->mysql->last_error.$sql;
             if(sizeof($results) >0 && is_array($results)){
               foreach($results as $result){
                 $answer ="";
                 $fields = str_replace("`","",$fields);
                 foreach(explode(',',$fields) as $field)
                      $answer .= $result[$field]. " ";
                 if(isset($value) && $value == $result[$id])
                          echo "<option selected='selected' value='".$result[$id]."'>$answer</option>";
                 else
                          echo "<option value='".$result[$id]."'>$answer</option>";
               }
               }
             if(strpos($class,'other_option') > -1)
                echo "<option value='Other'>Other</option>";
             echo "</select>\n";
    }
    function print_report(){
    if($this->variables[$this->primary_key] != ''){
      $return = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
      $return .= '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">';
      $return .= '<head><title>'.$this->table_name.'</title>';
      $return .= '<style> @media print { .printing {visibility:hidden;width:0px;height:0px;} .footer {position: fixed;bottom: 0; } } @media screen { .footer {display: none;}}</style>';
      $return .= '<body style="background:white;width:6.5in;">';
      $return .= '<div class="printing" style="text-align:center"><a href="#" onclick="window.print();return false;">Print</a> </div>';

          $return .= "<table cellspacing='0' style='width:6.5in;' class='footer'>";
          $return .= "<tfoot>";
          $return .= "<td style='border-bottom: 2px solid black;border-top: 2px solid black;width:50px;'><img src='view/page/images/logo.png' height='40' /></td><td style='border-bottom: 2px solid black;border-top: 2px solid black;font-size: 10px;text-align:center;vertical-align:middle;'>".SITE_NAME."</td>\n";
          $return .= "</tfoot>\n";
          $return .= "</table>\n";

          $this->where_criteria = array(array("field" => $this->primary_key, "operator"=>"=", "argument"=>$this->variables[$this->primary_key]));
          $results = $this->mysql->get_all($this->where_criteria);
          foreach($results as $result){
              $return .= "<div style='width:6.5in;page-break-after:always;'>\n";
              //Header
              $return .= "<table style='width:6.5in;border-bottom: 2px solid black;border-top: 2px solid black;font-size: 24px;text-align:center;'>\n";
              $return .= "<tr><td width='150' align='left' valign='center'><img src='view/page/images/logo.png' width='150'/></td><td>{$this->table_name} Information</td></tr>";
              $return .= "</table>\n";


              $return .= "<table style='width:6.5in;'>\n";
             foreach ( array_keys($result->lookup_fields()) as $key ){
              	  if(!isset($this->fields[$key]['hidden']))
                      $return .= "<tr><td style='text-transform: uppercase;font-weight:bold;'>".str_replace("_"," ",$key).":</td><td>".$result->$key."</td></tr>\n";
                }
              }
              $return .= "</table>\n</div>\n</body></html>\n";
          return $return;
          }
     }
    function pdf_report(){
      if(!is_dir(SITEPATH."/uploads/")){
                      if (!mkdir(SITEPATH."/uploads/", 0755)){
                          $this->variables['error'] = "Error Creating Directory";
                          break;
                      }
       }
       if(!is_dir(SITEPATH."/uploads/temp/")){
                      if (!mkdir(SITEPATH."/uploads/temp/", 0755)){
                          $this->variables['error'] = "Error Creating Directory";
                          break;
                      }
       }

      $print_ids = explode('|',$this->variables[$this->primary_key]);
      $file_list = '';
      foreach($print_ids as $print_id){
          $wsdl = "https://jasperserver.com/jasperserver/services/repository?wsdl";
          $username = "username";
          $password = "password";
          $format = "PDF"; // Could be HTML, RTF, etc (but remember to update the Content-Type header above)
          $client = new SoapClient($wsdl, array('login' => $username, 'password' => $password, "trace" => 1, "exceptions" => 0));

          foreach($this->reports as $report){

              $request = "<request operationName=\"runReport\" locale=\"en\">
                  <argument name=\"RUN_OUTPUT_FORMAT\">{$format}</argument>
                  <resourceDescriptor name=\"\" wsType=\"\"
                  uriString=\"/reports/{$report}\"
                  isNew=\"false\">
                  <label>{$this->table_name}</label>
                  <parameter name='{$this->primary_key}'>{$print_id}</parameter> "
                  .(isset($this->parent_key) && isset($this->parent_id)?"<parameter name='{$this->parent_key}'>{$this->parent_id}</parameter>":"")
                  ."</resourceDescriptor>
                </request>";

               $client->runReport($request);
               //echo $request;
               //echo $client->__getLastResponse();


               $output = $client->__getLastResponse();
               //Save output as PDF
               if($output != ''){
                 $file =  SITEPATH."/uploads/temp/{$print_id}".str_replace('/','_',$report).".pdf";
                 $file_list .= $file." ";
                 file_put_contents($file, $output);
               }
           }
        }
        //Merge PDF's
        $cmd = "gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=".SITEPATH."/uploads/temp/merged.pdf ".$file_list ;
        $result = shell_exec($cmd." 2>&1");
        //Export merged PDF
        header('Content-type: application/pdf');
        header("Content-Disposition: attachment; filename={$this->table_name}.pdf");
        header("Content-Length: ".filesize(SITEPATH."/uploads/temp/merged.pdf"));
        readfile(SITEPATH."/uploads/temp/merged.pdf");

        //Delete temp files
        unlink(SITEPATH."/uploads/temp/merged.pdf");
        foreach($print_ids as $print_id){
          foreach($this->reports as $report){
              unlink(SITEPATH."/uploads/temp/{$print_id}".str_replace('/','_',$report).".pdf");
          }
        }
  }

    function valid_email_address($email_address) {
    	// First, we check that there's one @ symbol, and that the lengths are right
    	// Email invalid because wrong number of characters in one section, or wrong number of @ symbols.
    	if ( ! ereg("^[^@]{1,64}@[^@]{1,255}$", $email_address) ) return false;

    	// Split it into sections to make life easier
    	$email_array = explode("@", $email_address);
    	$local_array = explode(".", $email_array[0]);
    	for ($i = 0; $i < sizeof($local_array); $i++) {
    		if ( ! ereg("^(([A-Za-z0-9!#$%&'*+/=?^_`{|}~-][A-Za-z0-9!#$%&'*+/=?^_`{|}~\.-]{0,63})|(\"[^(\\|\")]{0,62}\"))$", $local_array[$i])) {
    			return false;
    		}
    	}
    	if (!ereg("^\[?[0-9\.]+\]?$", $email_array[1])) { // Check if domain is IP. If not, it should be valid domain name
    		$domain_array = explode(".", $email_array[1]);
    		if ( sizeof($domain_array) < 2 ) {
    			return false; // Not enough parts to domain
    		}
    		for ( $i = 0; $i < sizeof($domain_array); $i++ ) {
    			if ( ! ereg("^(([A-Za-z0-9][A-Za-z0-9-]{0,61}[A-Za-z0-9])|([A-Za-z0-9]+))$", $domain_array[$i]) ) {
    				return false;
    			}
    		}
    	}
    	return true;
    }
    /* Function to send an email, uses phpMailer
    $to = comma seperated list
    $subject = email subject
    $msg = html message, strips tags out for text version
    FROM default to SITE_EMAIL in config
    */
    function send_email($to, $subject, $msg ) {

    	require_once(SITEPATH.'/model/phpmailer.php');
        $mail = new PHPMailer();
        $mail->SetFrom(SITE_EMAIL,SITE_NAME);
        $mail->AddReplyTo(SITE_EMAIL,SITE_NAME);
        foreach(explode(",",$to) as $email){
          if ( preg_match(EMAIL_ADDRESS_REGEX, $email))
            $mail->AddAddress($email);
        }
        $mail->Subject = $subject;
        $mail->AltBody = strip_tags($msg);//Remove html from message to display as text
        $mail->MsgHTML($msg);
        if(!$mail->Send())
            echo "Mailer Error: " . $mail->ErrorInfo;

    	return true;
    }
    function export(&$results){
    	if ( sizeof($results) > 0 ) {
    		ob_clean();
                $filename =  $this->table_name.date('m_d_Y');
                header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
                header("Content-Disposition: inline; filename=\"" . $filename . ".xml\"");
		echo '<?xml version="1.0" encoding="UTF-8"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">';
		echo "<Worksheet ss:Name='{$this->table_name}'><Table>";
                $line=$ids="";
    			foreach ( array_keys($this->fields) as $key ) $line .= "<Cell><Data ss:Type='String'>". $key."</Data></Cell>";
    			echo "<Row>".$line."</Row>";
    			foreach ( $results as $result )	{
    				$line = "";
    				foreach ( array_keys($this->fields) as $key ) {
    					if(isset($this->fields[$key]['lookup_table']) && isset($this->fields[$key]['lookup_field'])) $line .= "<Cell><Data ss:Type='String'>" .htmlentities($this->lookup_id($this->fields[$key]['lookup_table'],$this->fields[$key]['lookup_field'],( isset($this->fields[$key]["lookup_id"]) ? $this->fields[$key]["lookup_id"] : $key),$result->$key), ENT_COMPAT, "UTF-8") ."</Data></Cell>";
    					else if($this->fields[$key]['type'] == 'int') $line .= "<Cell><Data ss:Type='Number'>".$result->$key."</Data></Cell>";
    					else $line .= "<Cell><Data ss:Type='String'>" . htmlentities($result->$key, ENT_COMPAT, "UTF-8")  ."</Data></Cell>";
    				}
    				echo "<Row>".$line."</Row>";
    				$ids .= $result->{$this->primary_key}.",";
    			}
    			echo "</Table></Worksheet>";
    			if(isset($this->children) && sizeof($this->children)>0){
                                  foreach($this->children as $child){
                                          if(is_int((int)$child['get_page'])&& (int)$child['get_page'] >0){
                                                  $pages = new PageClass;
                                                  $child_page = $pages->load((int)$child['get_page']);
                                           }
                                           else{
                                            require_once SITEPATH . "/components/".$child['get_page'];
                                            eval("\$child_page = new ".str_replace("_","",substr($child['get_page'],strrpos($child['get_page'],'/')+1,-4))."Class();");
                                          }
                                          echo $this->export_child($child_page,$this->primary_key,substr($ids, 0, strlen($ids)-1 ));
                                  }
                                }
    			echo "</Workbook>";
    			exit;
    	} else "No records found.";
    }
    public function export_child($child,$parent_key,$ids){
		$criteria[] = array("field" => "{$parent_key}", "operator"=>"IN", "argument"=>"{$ids}");
                $line=$ids = "";
                $results = $child->mysql->get_all($criteria);
                if(sizeof($results)>0 && isset($child->table_name) && $child->table_name != ''){
                  echo  "<Worksheet ss:Name='{$parent_key}_{$child->table_name}'><Table>";
        		foreach ( array_keys($child->fields) as $key ) $line .=  "<Cell><Data ss:Type='String'>". $key."</Data></Cell>";
        		echo "<Row>".$line."</Row>";
            		foreach ( $results as $result )	{
                          $line = '';
            			foreach ( array_keys($child->fields) as $key ) {
                                        if(isset($child->fields[$key]['lookup_table']) && isset($child->fields[$key]['lookup_field'])) $line .= "<Cell><Data ss:Type='String'>" . htmlentities($child->lookup_id($child->fields[$key]['lookup_table'],$child->fields[$key]['lookup_field'],( isset($child->fields[$key]["lookup_id"]) ? $child->fields[$key]["lookup_id"] : $key),$result->$key), ENT_COMPAT, "UTF-8")."</Data></Cell>";
            				else if($child->fields[$key]['type'] == 'int') $line .= "<Cell><Data ss:Type='Number'>".$result->$key."</Data></Cell>";
            				else $line .= "<Cell><Data ss:Type='String'>" . htmlentities($result->$key, ENT_COMPAT, "UTF-8")  ."</Data></Cell>";
            			}
            			echo "<Row>".$line."</Row>";
            			$ids .= $result->{$child->primary_key}."," ;
            		}
            		echo "</Table></Worksheet>";
                        if(isset($child->children) && sizeof($child->children)>0){
                                  foreach($child->children as $sub_child){
                                    if(is_int((int)$sub_child['get_page'])&& (int)$sub_child['get_page'] >0){
                                            $pages = new PageClass;
                                            $child_page = $pages->load((int)$sub_child['get_page']);
                                     }
                                     else{
                                          require_once SITEPATH . "/components/".$sub_child['get_page'];
                                          eval("\$child_page = new ".str_replace("_","",substr($sub_child['get_page'],strrpos($sub_child['get_page'],'/')+1,-4))."Class();");
                                     }
                                          echo $this->export_child($child_page,$child->primary_key,substr($ids, 0, strlen($ids)-1 ));
                                  }
                                }
    		}
    }
    public function export_sql(&$results,$report_name=NULL){
    	if ( sizeof($results) > 0 ) {
    			$delimeter = "\t";
    			$text_delimeter = '"';

    			ob_clean();
                $filename =  (isset($report_name)?$report_name:"Export")."_".date('m_d_Y');
                header("Content-Type: application/vnd.ms-excel");
    			header("Content-Disposition: attachment; filename=\"$filename.xls\"");

    			$line = "";
    			foreach ( array_keys($results[0]) as $key ) $line .= $text_delimeter . str_replace($delimeter, " ", $key) . $text_delimeter . $delimeter;

    			echo substr($line, 0, strlen($line)-1 ) . "\n";
                $total=0;
                $est_total=0;
    			foreach ( $results as $result )	{
    				$line = "";
    				foreach ( $result as $cell_value ) {
    					if ( is_int($cell_value) || is_double($cell_value) ) $line .= str_replace($delimeter, " ", $cell_value) . $delimeter;
    					else $line .= $text_delimeter . str_replace($delimeter, " ", $cell_value) . $text_delimeter . $delimeter;
    				}

    				echo substr($line, 0, strlen($line)-1 ) . "\n";
    			}
    			exit;
    	} else
            echo"<p>No records found.</p>";

    }
    function print_image($file){
      if($file != ''){
        $return = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
        $return .= '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">';
        $return .= '<head><title>'.$this->table_name.'</title>';
        $return .= '<style> @media print { .printing {visibility:hidden;width:0px;height:0px;} .footer {position: fixed;bottom: 0; } } @media screen { .footer {display: none;}}</style>';
        $return .= '<body style="background:white;width:6.5in;">';
        $return .= '<div class="printing" style="text-align:center"><a href="#" onclick="window.print();return false;">Print</a> </div>';
        $return .=" <img src='http://".$_SERVER['SERVER_NAME'].str_replace(SITEPATH,'',$file)."' style='max-width:700px' />";
        $return .= "</body></html>\n";
        return $return;
       }
    }
    function output_file($file)
    {
        ob_clean();
         if(!($file)) die('File not found or inaccessible!');
         $file = html_entity_decode($file,ENT_QUOTES,'UTF-8');
         // required for IE, otherwise Content-Disposition may be ignored
         if(ini_get('zlib.output_compression'))
          ini_set('zlib.output_compression', 'Off');
        //echo SITEPATH.str_replace(SITEPATH,'',$file);
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        //header("Cache-Control: private",false);
        header("Content-type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"".str_replace(' ','_',basename($file))."\"" );
        header("Content-Transfer-Encoding: binary");
        header("Content-Length: ".filesize(SITEPATH.str_replace(SITEPATH,'',$file)));
        readfile(SITEPATH.str_replace(SITEPATH,'',$file));

        exit();
    }
    function upload_file($key){
        //Cleans up multiple file uploads in an array
        $names = array( 'name' => 1, 'type' => 1, 'tmp_name' => 1, 'error' => 1, 'size' => 1);
        foreach ($_FILES[$key] as $index => $part) {
            // only deal with valid keys and multiple files
            $index = (string) $index;
            if (isset($names[$index]) && is_array($part)) {
                foreach ($part as $position => $value) {
                    $_FILES[$key][$position][$index] = $value;
                }
                // remove old key reference
                unset($_FILES[$key][$index]);
            }

        }
        foreach ($_FILES[$key] as $position => $file) {
            echo  $file['tmp_name'];
            if($file['tmp_name'] != ""){
                if(!is_dir(SITEPATH."/uploads/")){
                    if (!mkdir(SITEPATH."/uploads/", 0755)){
                        $this->variables['error'] = "Error Creating Directory";
                        return false;
                    }
                }
                $current_db = $this->mysql->get_sql('SELECT DATABASE() as db');
                if(!is_dir(SITEPATH."/uploads/{$current_db[0]['db']}/")){
                    if (!mkdir(SITEPATH."/uploads/{$current_db[0]['db']}/", 0755)){
                        $this->variables['error'] = "Error Creating Directory";
                        return false;
                    }
                }
                if(!is_dir(SITEPATH."/uploads/{$current_db[0]['db']}/{$this->table_name}/")){
                    if (!mkdir(SITEPATH."/uploads/{$current_db[0]['db']}/{$this->table_name}/", 0755)){
                        $this->variables['error'] = "Error Creating Directory";
                        return false;
                    }
                }
                if(!is_dir(SITEPATH."/uploads/{$current_db[0]['db']}/{$this->table_name}/".$this->variables[$this->primary_key]."/")){
                        if (!mkdir(SITEPATH."/uploads/{$current_db[0]['db']}/{$this->table_name}/".$this->variables[$this->primary_key]."/", 0755)){
                            $this->variables['error'] = "Error Creating Directory";
                            return false;
                        }
                        chmod(SITEPATH."/uploads/{$current_db[0]['db']}/{$this->table_name}/".$this->variables[$this->primary_key]."/",0755);
                }   //Check if we have an image, if so shrink the file size
                if(in_array($file['type'],array("image/jpeg", "image/gif", "image/png")))
                    $this->resize_img($file['tmp_name'], 1024, 768);

                if(move_uploaded_file($file['tmp_name'], SITEPATH."/uploads/{$current_db[0]['db']}/{$this->table_name}/".$this->variables[$this->primary_key]."/".basename( $file['name']))){
                    $this->variables[$key] =  "/uploads/{$current_db[0]['db']}/{$this->table_name}/".$this->variables[$this->primary_key]."/".basename( $file['name']);
                    return true;
                }
                else{
                        $this->variables['error'] = "There was an error uploading ".  basename( $file['name']).", please try again!";
                    }
            }
            return false;
         }
    }
    function resize_img($img, $width, $height){
     list($w_src, $h_src, $type) = getimagesize($img);
     if($w_src > $width || $h_src > $height){    //Only resize an image if it is larger than the preferred size
       $ratio = $w_src/$h_src;   // create new dimensions, keeping aspect ratio
       if ($width/$height > $ratio) {$width = floor($height*$ratio);} else {$height = floor($width/$ratio);}

       switch ($type)
         {case 1:   //   gif -> jpg
            $img_new = imagecreatefromgif($img);
            $img_resized = imagecreatetruecolor($width, $height);  //  resample
            imagealphablending( $img_resized, false );
            imagesavealpha( $img_resized, true );
            imagecopyresampled($img_resized, $img_new, 0, 0, 0, 0, $width, $height, $w_src, $h_src);
            imagegif($img_resized, $img);    //  save new image
            break;
          case 2:   //   jpeg -> jpg
            $img_new = imagecreatefromjpeg($img);
            $img_resized = imagecreatetruecolor($width, $height);  //  resample
            imagecopyresampled($img_resized, $img_new, 0, 0, 0, 0, $width, $height, $w_src, $h_src);
            imagejpeg($img_resized, $img);    //  save new image
            break;
          case 3:  //   png -> jpg
            $img_new = imagecreatefrompng($img);
            $img_resized = imagecreatetruecolor($width, $height);  //  resample
            imagealphablending( $img_resized, false );
            imagesavealpha( $img_resized, true );
            imagecopyresampled($img_resized, $img_new, 0, 0, 0, 0, $width, $height, $w_src, $h_src);
            imagepng($img_resized, $img);    //  save new image
            break;
         }
       if(isset($img_new)){
         imagedestroy($img_new);
         imagedestroy($img_resized);
       }
     }
  }
    function show_map($map_list){
        $map_list= trim($map_list,"|");
        $locations = explode('|',$map_list);
        $addr = array();
        $d_addr = "";
        echo "\t<fieldset>\n";
        echo "\t<legend>Map</legend>\n";
        echo "\t<div id='map_canvas'  style='width:400px; height:400px'></div>\n";


        echo '<script language="JavaScript" src="http://maps.google.com/maps/api/js?sensor=true"></script>';
        echo " \t<script type='text/javascript' language='JavaScript'> \n";
        ?>
        function draw_Map(addresses) {
            geocoder = new google.maps.Geocoder();
            var map = new google.maps.Map(document.getElementById("map_canvas"),{zoom: 10,mapTypeId: google.maps.MapTypeId.ROADMAP});
            var latlngbounds = new google.maps.LatLngBounds( );
            var infowindow = new google.maps.InfoWindow({content: 'testing'});
            for ( var i = 0; i < addresses.length; i++ )
            {

              var myLatlng = new google.maps.LatLng(addresses[i][0], addresses[i][1]);
               var marker = new google.maps.Marker({position: myLatlng, map: map, title: addresses[i][2],html: addresses[i][2]});
               google.maps.event.addListener(marker, 'click', function(event) { infowindow.setContent(this.html); infowindow.open(map, this); });
               latlngbounds.extend(myLatlng);

               map.fitBounds(latlngbounds);
               if(addresses.length == 1){
                    var listener = google.maps.event.addListener(map, "idle", function() {
                      if (map.getZoom() > 16){ map.setZoom(15);
                       map.setCenter(myLatlng); }
                      google.maps.event.removeListener(listener);
                    });
               }
            }
        }
        <?php
        echo " \t\tvar js_array = new Array(); \n";
        $i =0;
        foreach($locations as $location){
          $results = $this->mysql->get_sql("Select {$this->variables['address_field']},{$this->variables['city_field']},{$this->variables['zip_field']} FROM `{$this->table_name}` WHERE {$this->primary_key} = $location");
          //echo $this->mysql->last_error;
          if(sizeof($results) > 0){
            $addr[$location] = $results[0][$this->variables['address_field']];
            foreach($results as $result){
              $address = str_replace(" ","+",$result[$this->variables['address_field']]).",+".str_replace(" ","+",$result[$this->variables['city_field']]).",+CA";
              $json_result = json_decode(file_get_contents('http://maps.googleapis.com/maps/api/geocode/json?address='.$address.'&sensor=true'));
              if(isset($json_result->results[0]))
                echo "\t\t js_array.push([".$json_result->results[0]->geometry->location->lat.",".$json_result->results[0]->geometry->location->lng.",'".$result[$this->variables['address_field']].",".$result[$this->variables['city_field']]."']); \n";
              else //Let the end user know the address is bad
                $addr[$location] .= " BAD ADDRESS";
              $d_addr = $d_addr."" .str_replace(" ","+",$result[$this->variables['address_field']]).",+".str_replace(" ","+",$result[$this->variables['city_field']]).",+CA+to:";
              }

            $i++;
          }
        }
        $d_addr = trim($d_addr, "+to:");
        echo "\t\t draw_Map(js_array);\n ";
        echo "\t</script>\n";

        foreach($locations as $location){
            $trimmed_location = str_replace(array($location."|","|".$location, $location),"",$map_list);
            echo "\t\t<br /><a href='?get_page={$this->page_id}&amp;action=Map&amp;map_list=$trimmed_location&jquery=true'>Remove $addr[$location]</a>\n";
        }
        echo "\t\t<br /><br /><a target='_blank' href='http://maps.google.com/?saddr=1202+John+Reed+Ct,+City+of+Industry,+CA&amp;daddr=$d_addr'>Map From LA</a>";
        echo "\t\t<br /><a target='_blank' href='http://maps.google.com/?saddr=2288+Park+Ave+Chico,+CA&amp;daddr=$d_addr'>Map From Chico</a>";
        echo "\t\t<br /><a target='_blank' href='http://maps.google.com/?saddr=395+W+Bedford+Ave+Fresno,+CA&amp;daddr=$d_addr'>Map From Fresno</a>";
        echo "</fieldset>\n";

    }
    function display_results($title,&$results,&$totals = NULL){
      echo"<fieldset><legend style='font-weight:bold'>$title</legend>\n";
      if(sizeof($results) >0){
          echo "<table>\n";
            echo "<thead>\n";
        	echo "\t<tr>\n";
                foreach ( array_keys($results[0]) as $key )	echo "\t\t<th style='border-bottom:1px solid black;text-align:center;font-weight:bold;padding-left:2px'>".str_replace("_"," ",$key)."</th>\n";
        	echo "\t</tr>\n";
            echo "</thead>\n";
            if(isset($totals) && sizeof($totals) > 0){
                foreach ( $totals as $total )	{
                echo "\t<tr style='font-weight:bold;'>\n";
                  foreach ( array_keys($totals[0]) as $key ) {
                          echo "\t\t<td style='border-top:1px solid black;".(strlen($totals[0][$key]) < 5?"text-align:center;":"")."'>{$total[$key]}</td>\n";
                  }
               }
               echo "\t</tr>\n";
        }
            echo "<tbody>\n";
            foreach ( $results as $result )	{
              echo "\t<tr>\n";
                foreach ( array_keys($results[0]) as $key ) {
                        echo "\t\t<td>{$result[$key]}</td>\n";
                }
               echo "\t</tr>\n";
             }
            echo "</tbody>\n";
        	echo "</table>\n";
        }
        echo "</fieldset>\n";
    }
    function recaptcha_check_answer ($privkey, $remoteip, $challenge, $response)
    {
        //discard spam submissions
        if ($challenge == null || strlen($challenge) == 0 || $response == null || strlen($response) == 0){
                $this->variables['error'] = 'incorrect-captcha-sol';
                return false;
        }
        $content .= "privatekey=".urlencode( stripslashes($privkey))."&remoteip=".urlencode( stripslashes($remoteip))."&challenge=".urlencode( stripslashes($challenge))."&response=".urlencode( stripslashes($response));
        $http_request  = "POST /recaptcha/api/verify HTTP/1.0\r\n";
        $http_request .= "Host: www.google.com\r\n";
        $http_request .= "Content-Type: application/x-www-form-urlencoded;\r\n";
        $http_request .= "Content-Length: " . strlen($content) . "\r\n";
        $http_request .= "User-Agent: reCAPTCHA/PHP\r\n";
        $http_request .= "\r\n";
        $http_request .= $content;

        $response = '';
        if( false == ( $fs = @fsockopen("www.google.com", 80, $errno, $errstr, 10) ) ) {
                die ('Could not open socket');
        }

        fwrite($fs, $http_request);

        while ( !feof($fs) )
                $response .= fgets($fs, 1160); // One TCP-IP packet
        fclose($fs);
        $response = explode("\r\n\r\n", $response, 2);
        $answers = explode ("\n", $response [1]);
        if (trim ($answers [0]) == 'true')
                return true;
        else{
                $this->variables['error'] = $answers [1];
                return false;
        }
     }


}
?>

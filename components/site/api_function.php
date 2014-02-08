<?php
/*
 * Class for API Information
    Copyright (C) 2007-2014 MyCoreCMS

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details. <http://www.gnu.org/licenses/>.
 */


### Table/db Initialization Variables  ###
     //Rename table_template to match the filename
class ApiFunctionClass extends TableClass{
    var $children;
    public function __construct() {
      $this->variables = Array();
      $this->default_results = 25;
      $this->printable = FALSE;
      $this->table_permissions['add'] = FALSE;
      $this->table_permissions['delete'] = FALSE;
      $this->table_permissions['edit'] = FALSE;

      $this->page_id = str_replace(SITEPATH."/components/",'',__FILE__); //Let table class know what page this is
      $this->page_title = str_replace("_"," ",substr($this->page_id,(strpos($this->page_id,'/')?strrpos($this->page_id,'/')+1:0),-4)); //Set the title based on the page name
      $this->db = new MySQLDatabase(new mysqli(SITE_DB_HOST, SITE_DB_USER, SITE_DB_PASS, SITE_DB_NAME));
      if(isset($this->db)){

        $this->table_name = "api_function";
    	$this->primary_key = "api_function_id";
    	$this->fields = array(
           "api_function_id" => array("type" => "integer","min_length" => 1, "hidden" => TRUE),
           "api_id" => array("type" => "integer","min_length" => 1, "hidden" => TRUE),
           "function" => array("type"=>"text","min_length" => 1,"max_length" => 255, "searchable" => TRUE,"description"=>"The function name"),
           "example" => array("type"=>"textarea","min_length" => 1,"max_length" => 255, "searchable" => TRUE,"description"=>"How you would call the function. '[ ]' means an argument in option"),
           "description" => array("type"=>"textarea","min_length" => 0,"max_length" => 4000,"rows"=>20),
    	);
        $this->mysql = new MySQLClass($this->db->get_db(),$this->fields,$this->table_name,$this->primary_key);
        $this->init_variables();
      }
      else
        die('Missing DB Class');
	}
    public function init_variables(){
        //put any custom variables you want here
        parent::init_variables();
        //if you want to force any variables put it after the parent function
    }
}
?>
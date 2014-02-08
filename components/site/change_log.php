<?php
/*
    Handles System Change Log
    Copyright (C) 2007-2014 MyCoreCMS

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details. <http://www.gnu.org/licenses/>.
 */

class ChangeLogClass extends TableClass{
    var $children;
    public function __construct($db= NULL) {
      $this->variables = Array();
      $this->default_results = 25;
      $this->printable = FALSE;
      $this->table_permissions['add'] = FALSE;
      $this->table_permissions['delete'] = FALSE;
      $this->table_permissions['edit'] = FALSE;

      $this->page_id = str_replace(SITEPATH."/components/",'',__FILE__); //Let table class know what page this is
      $this->page_title = str_replace("_"," ",substr($this->page_id,(strpos($this->page_id,'/')?strrpos($this->page_id,'/')+1:0),-4)); //Set the title based on the page name
      if(!isset($db))
        $this->db = new MySQLDatabase();
      else
        $this->db = $db;
      if(isset($this->db)){

        $this->table_name = "change_log";
    	$this->primary_key = "change_log_id";
    	$this->fields = array(
           "change_log_id" => array("type" => "integer","min_length" => 1, "hidden" => TRUE),
           "table_name" => array("type"=>"text","min_length" => 1,"max_length"=>45, "searchable" => TRUE),
           "primary_key" => array("type"=>"text","min_length" => 1,"max_length"=>45, "searchable" => TRUE),
           "key_id" => array("type"=>"integer","min_length" => 1),
           "change" => array("type"=>"textarea","min_length" => 1, "max_length" => 3000,"searchable" => TRUE),
           "updated" => array("type"=>"timestamp","min_length" => 0, "hidden" => TRUE),
           "updated_by" => array("type"=>"text","min_length" => 0, "max_length" => 20, "hidden" => TRUE),
    	);
        $this->mysql = new MySQLClass($this->db->get_db(),$this->fields,$this->table_name,$this->primary_key);
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
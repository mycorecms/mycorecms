<?php
/*
    Handles User Role Permissions
    Copyright (C) 2007-2014 MyCoreCMS

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details. <http://www.gnu.org/licenses/>.
 */
     
class UserRoleClass extends TableClass{
    public function __construct() {
      $this->variables = Array();
      $this->default_results = 25;
      $this->printable = FALSE;
      $this->page_id = str_replace(SITEPATH."/components/",'',__FILE__); //Let table class know what page this is
      $this->page_title = str_replace("_"," ",substr($this->page_id,(strpos($this->page_id,'/')?strrpos($this->page_id,'/')+1:0),-4)); //Set the title based on the page name
      $this->db = new MySQLDatabase;

      if(isset($this->db)){
        $this->children = array(
        "Page Access"=> array("action"=>"","get_page"=>"site/user_page_access.php"),
        );

        $this->table_name = "user_role";
    	$this->primary_key = "user_role_id";
    	$this->fields = array(
           "user_role_id" => array("type" => "integer","min_length" => 1, "hidden" => TRUE),
           "role" => array("type" => "auto","min_length" => 1,"max_length"=>20, "searchable"=>TRUE),
           "last_updated" => array("type"=>"timestamp","min_length" => 0, "hidden" => TRUE),
           "updated_by" => array("type"=>"text","min_length" => 1, "max_length" => 20, "hidden" => TRUE),
    	);
        $this->mysql = new MySQLClass($this->db->get_db(),$this->fields,$this->table_name,$this->primary_key);
      }
      else
        die('Missing DB Class');
	}

}
?>
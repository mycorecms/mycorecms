<?php
/*  Example Class
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
class ExampleClass extends TableClass{
    var $children;
    public function __construct() {
      $this->variables = Array();
      $this->default_results = 25;
      $this->printable = FALSE;
      $this->page_id = str_replace(SITEPATH."/components/",'',__FILE__); //Let table class know what page this is
      $this->page_title = str_replace("_"," ",substr($this->page_id,(strpos($this->page_id,'/')?strrpos($this->page_id,'/')+1:0),-4)); //Set the title based on the page name
      $this->db = new MySQLDatabase(new mysqli(SITE_DB_HOST, SITE_DB_USER, SITE_DB_PASS, SITE_DB_NAME));
      if(isset($this->db)){

      $this->children = array(
        "Documents"=> array("action"=>"","get_page"=>"site/documents.php"),
        );

        $this->table_name = "example";
    	$this->primary_key = "example_id";
    	$this->fields = array(
           "example_id" => array("type" => "integer","min_length" => 1, "hidden" => TRUE),
           "text" => array("type"=>"text","min_length" => 1,"description"=>"*Indicates a required field"),
           "auto" => array("type"=>"auto","min_length" => 0, "searchable" => TRUE,"description"=>"A user adjustable dropdown, select other to add new values"),
           "integer" => array("type"=>"integer","min_length" => 0),
           "double" => array("type"=>"double","min_length" => 0),
           "currency" => array("type"=>"currency","min_length" => 0, "searchable" => TRUE),
           //"password" => array("type"=>"password","min_length" => 0),
           "checkbox" => array("type"=>"checkbox","min_length" => 0),
           "range" => array("type"=>"range","min_length" => 0,"options"=>array(1990,date('Y',time()))),
           "list" => array("type"=>"list","min_length" => 0,"options"=>array('bla','blabla','blabllity'),"description"=>"Static drop-down list"),
           "list_other" => array("type"=>"list+other","min_length" => 0,"options"=>array('bla','blabla','blabllity'),"description"=>"Static drop-down list, lets user specify other but does not change list"),
           "checkbox-list" => array("type"=>"checkbox-list","min_length" => 0,"options"=>array('bla','blabla','blabllity'),"description"=>"Static checkbox list"),
           "table_link" => array("type" => "table_link","min_length" => 1, "lookup_table"=>"user", "lookup_field"=>"first_name,last_name", "lookup_id"=>"user_id","searchable" => TRUE,"description"=>"Links tables via primary_id, can display any fields in the table"),
           "table_link-checkboxes" => array("type" => "table_link-checkboxes","min_length" => 0, "lookup_table"=>"project", "lookup_field"=>"project_name", "lookup_id"=>"project_id","description"=>"Links multiple table primary_id's"),
           "timestamp" => array("type"=>"timestamp","min_length" => 0, "searchable" => TRUE),
           "time" => array("type"=>"time","min_length" => 0),
           "textarea" => array("type"=>"textarea","min_length" => 0, "max_length" => 1000,"searchable" => TRUE),
           "last_updated" => array("type"=>"timestamp","min_length" => 0, "hidden" => TRUE),
           "updated_by" => array("type"=>"text","min_length" => 1, "max_length" => 20, "hidden" => TRUE),
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
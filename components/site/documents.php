<?php
/*
    Class for Uploading Documents
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

class DocumentsClass extends TableClass{    //Rename class name to match the filename  ex: filename some_page  classname = SomePageClass

    public function __construct() {
      $this->variables = Array();
      $this->default_results = 25;
      $this->db = new MySQLDatabase(new mysqli(SITE_DB_HOST, SITE_DB_USER, SITE_DB_PASS, SITE_DB_NAME));
      $this->page_id = str_replace(SITEPATH."/components/",'',__FILE__); //Let table class know what page this is
      $this->page_title = str_replace("_"," ",substr($this->page_id,(strpos($this->page_id,'/')?strrpos($this->page_id,'/')+1:0),-4)); //Set the title based on the page name 
      if(isset($this->db)){

        //min_length
        $this->table_name = "documents";
        $this->primary_key= "document_id";
        $this->fields = array(
        	 "document_id" => array("type" => "int","min_length" => 1, "hidden" => TRUE),
             "document_description" => array("type"=>"text","min_length" => 0, "max_length" => 45, "searchable"=>TRUE),
             "document_location" => array("type"=>"file","min_length" => 0, "max_length" => 255, "searchable"=>TRUE),
             //"document_type" => array("type"=>"list","min_length"=> 1, "max_length" => 45, "searchable"=>TRUE,"options"=>array('RHA_Bid_Documents','Client_RFP_Documents','Program_Plans')),
             "last_updated" => array("type" => "timestamp", "min_length" => 0, "hidden" => TRUE),
             "updated_by" => array("type" => "text","min_length" => 0, "max_length" => 45, "hidden" => TRUE),
        	 );

        $this->mysql = new MySQLClass($this->db->get_db(),$this->fields,$this->table_name,$this->primary_key);
        $this->init_variables();
        //$this->action_check();
      }
      else
        die('Missing DB Class');
	}
    public function action_check($action = NULL){
     $this->init_variables();
     //check if this is a properites page, if so update the appropriate page
        switch (isset($action) ? $action : $this->variables['action']) {
        case "Add": //Check if we have multiple files being uploaded, if so override default behavior
        if(isset($_FILES['document_location']['name'][1])){
            $files = $this->fixFilesArray($_FILES['document_location']);
              foreach ($files as $position => $file) {
                    $_FILES['document_location'] = array($file);
                    parent::action_check($action);
              }
         }
         else
             parent::action_check($action);

         break;
        default:
                parent::action_check($action);
         break;
        }
   }
   function fixFilesArray($files)
    {
        $names = array( 'name' => 1, 'type' => 1, 'tmp_name' => 1, 'error' => 1, 'size' => 1);
        foreach ($files as $key => $part) {
            // only deal with valid keys and multiple files
            $key = (string) $key;
            if (isset($names[$key]) && is_array($part)) {
                foreach ($part as $position => $value) {
                    $files[$position][$key] = $value;
                }
                // remove old key reference
                unset($files[$key]);
            }
        }
        return $files;
    }
}
?>
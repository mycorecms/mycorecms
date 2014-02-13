<?php
/*  Class for setting Settings
    Copyright (C) 2007-2014 MyCoreCMS

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details. <http://www.gnu.org/licenses/>.
 */

//$page = new PrimeContractClass; //Intialize the class, contructor will handle the rest
require_once SITEPATH . "/model/table_class.php";
### Table/db Initialization Variables  ###
     //Rename table_template to match the filename
class SettingsClass extends TableClass{
    var $children;
    public function __construct() {
      $this->variables = Array();
      $this->default_results = 25;
      $this->printable = FALSE;
      $this->flat_table = TRUE;
      $this->page_id = str_replace(SITEPATH."/components/",'',__FILE__); //Let table class know what page this is
      $this->page_title = str_replace("_"," ",substr($this->page_id,(strpos($this->page_id,'/')?strrpos($this->page_id,'/')+1:0),-4)); //Set the title based on the page name
      $this->db = new MySQLDatabase(new mysqli(SITE_DB_HOST, SITE_DB_USER, SITE_DB_PASS, SITE_DB_NAME));
      if(isset($this->db)){

        $this->table_name = "settings";
    	$this->primary_key = "settings_id";
    	$this->fields = array(
           "settings_id" => array("type" => "integer","min_length" => 1, "hidden" => TRUE),
           "email" => array("type"=>"text","min_length" => 1,"description"=>"Default Email that emails from the server come from","default"=>"admin@".str_replace("www.","",$_SERVER['SERVER_NAME'])),
           "homepage" => array("type"=>"textarea","min_length" => 1, "searchable" => TRUE,"description"=>"Information that appears on the homepage","default"=>"<p style='text-align:center'>Welcome to ".str_replace("www.","",$_SERVER['SERVER_NAME']).".<br/>Select a page to view from the menu.</p>"),
           "logo" => array("type"=>"file","min_length" => 0),
           "site_name" => array("type"=>"text","min_length" => 0,"description"=>"Used for copyright on footer","default"=>ucfirst(str_replace("www.","",$_SERVER['SERVER_NAME']))),
           "captcha" => array("type"=>"checkbox","min_length" => 0,"description"=>"Help prevent spam by enabling captcha, only applies to non logged in users."),
           "analytics_key" => array("type"=>"text","min_length" => 0,"description"=>"Your Google Universal Analytics Key, EX:UA-99999999-1"),
           "template" => array("type"=>"list","min_length" => 1,"options"=>array('page'),"default"=>"page","description"=>"Due to the dynamic nature of the system, only one template can be used at a time."),
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
        //Build a list of all available templates in the view folder
        $this->fields['template']['options'] = array();
        if(is_dir(SITEPATH."/view/")){
            if ($handle = opendir(SITEPATH."/view/")) {
                while (false !== ($entry = readdir($handle))) {
                    if (is_dir(SITEPATH."/view/".$entry)&& $entry != "." && $entry != ".."){
                        $this->fields['template']['options'][]=$entry;
                    }
                }
                closedir($handle);
            }
        }
    }
}
?>
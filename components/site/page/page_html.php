<?php
/*  Class for HTML Web Pages
    Copyright (C) 2007-2014 MyCoreCMS

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details. <http://www.gnu.org/licenses/>.
 */
require_once SITEPATH."/model/table_class.php";
require_once SITEPATH."/model/blank_class.php";

### Table/db Initialization Variables  ###

class PageHtmlClass extends TableClass{
    public function __construct() {
      $this->variables = Array();
      $this->default_results = 25;
      $this->page_id = str_replace(SITEPATH."/components/",'',__FILE__); //Let table class know what page this is
      $this->page_title = str_replace("_"," ",substr($this->page_id,(strpos($this->page_id,'/')?strrpos($this->page_id,'/')+1:0),-4)); //Set the title based on the page name
      $this->flat_table = TRUE;

      $this->db = new MySQLDatabase;
      if(isset($this->db)){
        $this->table_name = "page_html";
    	$this->primary_key = "page_id";
    	$this->fields = array(
         "page_id" => array("type" => "int", "hidden" => TRUE,"min_length" => 1),
         "html_code" => array("type" => "textarea","min_length" => 1,"searchable"=>TRUE),
         "last_updated" => array("type"=>"timestamp", "hidden"=> TRUE),
         "updated_by" => array("type" => "text","max_length" => 20, "hidden" => TRUE)
    	 );
        $this->mysql = new MySQLClass($this->db->get_db(),$this->fields,$this->table_name,$this->primary_key);
      }
      else
        die('Missing DB Class');
	}
     public function init_variables(){
        parent::init_variables();

    }
    public function action_check($action = NULL){
        $this->init_variables();
        switch (isset($action) ? $action : $this->variables['action']) {
          case "Add_New":
          case "Edit":
             parent::action_check($action);
             echo "<script>setTimeout(function(){jQuery('.page_html_html_code').each(function(){CodeMirror.fromTextArea(jQuery(this).get(0),{mode:'php',lineNumbers : true,matchBrackets : true})});jQuery('.CodeMirror').each(function(i, el){el.CodeMirror.refresh();})},100);</script>\n";
          break;
           default:
              parent::action_check($action);
           break;
        }
    }
    public function load($page_id,$custom_code){
      if($page_id < 0){
            $this->variables['error'] = 'Invalid Page Id';
            return false;
      }
      else{
        //Load up the requested page
        $this->mysql->{$this->primary_key} = $page_id;
        $this->mysql->load();
        $current_page = new BlankClass();

        $current_page->page_id = $page_id;
        $current_page->html =  "<div>{$this->mysql->html_code}</div>" ;
        return $current_page;
       }
    }
}
?>
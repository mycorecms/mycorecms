<?php
/*  Class for Query Fields
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

### Table/db Initialization Variables  ###

class PageQueryFieldClass extends TableClass{
    private $table;

    public function __construct() {
      $this->variables = Array();
      $this->default_results = 25;
      $this->page_id = str_replace(SITEPATH."/components/",'',__FILE__); //Let table class know what page this is
      $this->page_title = str_replace("_"," ",substr($this->page_id,(strpos($this->page_id,'/')?strrpos($this->page_id,'/')+1:0),-4)); //Set the title based on the page name

      $this->db = new MySQLDatabase;
      if(isset($this->db)){
         $this->table_name = "page_query_field";
    	 $this->primary_key = "page_query_field_id";
         $this->fields = array(
         "page_query_field_id" => array("type" => "int", "hidden" => TRUE,"min_length" => 1),
    	 "page_id" => array("type" => "int", "hidden" => TRUE,"min_length" => 1),
         "table_name" => array("type"=>"list","options"=>array(),"min_length" => 1, "max_length" => 45, "searchable" => TRUE,"description"=>"Select a Table to populate a list of fields","js"=>"var page = getCurrentPage(this);get_queue(page.find('.current_page').val()+ '&action=Edit_Field&get_key=table_field_id&'+ page.find('.default_form').serialize() +'&jquery=TRUE', function(msg) {page.find('.default_form .table_field_id_page_query_field_field').replaceWith(msg);});"),
         "table_field_id" => array("type" => "table_link","lookup_table"=>"page_table_field","lookup_field"=>"field_name","lookup_id"=>"page_table_field_id","min_length" => 1, "max_length" => 45, "searchable" => TRUE,"description"=>"Select a field you wish to search by.","js"=>"var page = getCurrentPage(this);get_queue(page.find('.current_page').val()+ '&action=Get_Type&table_field_id='+jQuery(this).val() +'&jquery=TRUE', function(msg) {if(msg =='timestamp'){page.find('.default_form .default_value_field_container').addClass('hidden'); page.find('.default_form .start_date_field_container, .default_form .end_date_field_container').removeClass('hidden');}});"),
         "default_value" => array("type" => "text","min_length" => 0, "max_length" => 45, "searchable" => TRUE),
         "start_date" => array("type" => "list","min_length" => 0, "max_length" => 45, "hidden"=>TRUE,"options"=>array('Current Date','-1 Month','-2 Months','-6 Months','-1 Year','-2 Years'), "description"=>"Default start date, can be left blank"),
         "end_date" => array("type" => "list","min_length" => 0, "max_length" => 45, "hidden"=>TRUE,"options"=>array('Current Date','1 Month','2 Months','6 Months','1 Year','2 Years'),"description"=>"Default end date, can be left blank"),
         "label" => array("type" => "text","min_length" => 0, "max_length" => 45, "searchable" => TRUE),
         "description" => array("type" => "text","min_length" => 0, "max_length" => 100, "searchable" => TRUE),
         "required" => array("min_length" => 0, "type"=>"checkbox"),
         //"sub_query_link" => array("min_length" => 0,"type" => "checkbox", "max_length" => 45, "searchable" => TRUE),
    	 );
        $this->mysql= new MySQLClass($this->db->get_db(),$this->fields,$this->table_name,$this->primary_key);

      }
      else
        die('Missing DB Class');
	}
    public function init_variables(){
      if(isset($_REQUEST['page_id'])){
        $table_list = $this->mysql->get_sql('SELECT `database` FROM page_query WHERE page_id = '.(int)$_REQUEST['page_id']);
        if(isset($table_list[0])){
           unset($this->fields['table_name']['options']);
           $tables = $this->mysql->get_sql("Select table_name FROM page_table WHERe `database` =  '{$table_list[0]['database']}' ");
            foreach($tables as $table){
                $this->fields['table_name']['options'][] = $table["table_name"];
            }
        }
        }
        if($this->variables['table_name'] != '')
                $this->fields['table_field_id']['where'] = "  INNER JOIN page_table ON page_table.page_id = page_table_field.page_id WHERE table_name LIKE '%".$this->variables['table_name']."%' ORDER BY field_name";

        parent::init_variables();


    }
    public function action_check($action = NULL){

      $this->init_variables();

      //Run through any requests
      switch (isset($action) ? $action : $this->variables['action']) {
         case "Get_Type":
                $type = $this->mysql->get_sql('SELECT field_type FROM page_table_field WHERE page_table_field_id = '.$this->variables['table_field_id']);
                echo (isset($type[0]['field_type'])?$type[0]['field_type']:"");

           break;
           case "Edit":
           $this->mysql->{$this->primary_key} = $this->variables[$this->primary_key];
            $this->mysql->load();
            if($this->mysql->table_field_id > 0){
              $type = $this->mysql->get_sql('SELECT field_type FROM page_table_field WHERE page_table_field_id = '.$this->mysql->table_field_id);
              if(isset($type[0]['field_type']) && $type[0]['field_type']=='timestamp'){
                  unset($this->fields['start_date']['hidden']);
                  unset($this->fields['end_date']['hidden']);
                  $this->fields['default_value']['hidden'] = TRUE;
              }
           }
            parent::action_check($action);
           break;
         default:

              parent::action_check($action);
          break;
      }
    }



}
?>
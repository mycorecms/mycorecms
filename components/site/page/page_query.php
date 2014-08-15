<?php
/*  Class for Pages of Type Query
    Copyright (C) 2007-2014 MyCoreCMS

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details. <http://www.gnu.org/licenses/>.
 */
require_once SITEPATH."/model/query_class.php";

### Table/db Initialization Variables  ###

class PageQueryClass extends TableClass{

    public function __construct() {
      $this->variables = Array();
      $this->default_results = 25;
      $this->page_id = str_replace(SITEPATH."/components/",'',__FILE__); //Let table class know what page this is
      $this->page_title = str_replace("_"," ",substr($this->page_id,(strpos($this->page_id,'/')?strrpos($this->page_id,'/')+1:0),-4)); //Set the title based on the page name
      $this->flat_table = true;
      $this->children = array(
        "Search Fields"=> array("action"=>"","get_page"=>"site/page/page_query_field.php"),
      );

      $this->db = new MySQLDatabase;
      if(isset($this->db)){
        $this->table_name = "page_query";
    	$this->primary_key = "page_id";
    	$this->fields = array(
         "page_id" => array("type" => "int", "hidden" => TRUE,"min_length" => 1),
         "query_name" => array("type" => "text","min_length" => 1, "max_length" => 100,"searchable"=>TRUE),
         "sql_query" => array("type" => "textarea","min_length" => 1,"max_length"=>5000),
         //"total_query" => array("type" => "textarea","min_length" => 0,"max_length"=>5000,"description"=>"A query to sum up values in the main query into one row."),
         //"detailed_query" => array("type" => "textarea","min_length" => 0,"max_length"=>5000,"description"=>"When the + icon is click on a row this shows a more detailed view"),
         //"row_id" => array("type" => "text","min_length" => 0,"max_length"=>45,"description"=>"The field used to link the sql query to the detail query."),
         "database" => array("type" => "list","min_length" => 1, "max_length" => 100,"options"=>array('site'),"default"=>SITE_DB_NAME),
    	 );
        $this->mysql = new MySQLClass($this->db->get_db(),$this->fields,$this->table_name,$this->primary_key);
        //$this->init_variables();
      }
      else
        die('Missing DB Class');
	}
     public function init_variables(){
        $databases = $this->mysql->get_sql('Show Databases');
        if(isset($databases[0]))
           unset($this->fields['database']['options']);
        foreach($databases as $database){
            $this->fields['database']['options'][] = $database['Database'];
        }
        parent::init_variables();
    }
    public function action_check($action = NULL){

        $this->init_variables();

        switch (isset($action) ? $action : $this->variables['action']) {
          case "Add_New":
          case "Edit":
             parent::action_check($action);
             echo "<script>setTimeout(function(){jQuery('.page_query_sql_query:visible , .page_query_total_query:visible , .page_query_detailed_query:visible').each( function(){CodeMirror.fromTextArea(jQuery(this).get(0),{mode:'text/x-mysql'}).setSize(800, 100)});jQuery('.CodeMirror').each(function(i, el){el.CodeMirror.refresh();})},300);</script>\n";

          break;
          case "Add":
          case "Update";
          //if($this->mysql->requirement_check)

                $this->mysql->get_db()->select_db($this->variables['database']);
                $results = $this->mysql->get_sql($this->variables['sql_query']);
                $this->mysql->get_db()->select_db(SITE_DB_NAME);
                if($this->mysql->last_error != '')
                    echo $this->mysql->last_error;
                else
                    parent::action_check($action);
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
        $class_name= str_replace(" ","",$this->mysql->query_name);
        if(class_exists("Query".$class_name."Class") != true)
            eval("class Query".$class_name."Class EXTENDS QueryClass { ".html_entity_decode($custom_code,ENT_QUOTES,'UTF-8')." };");
        eval("\$current_page = new Query".$class_name."Class();");
        $current_page->mysql = new MySQLClass(new mysqli(SITE_DB_HOST, SITE_DB_USER, SITE_DB_PASS, $this->mysql->database));
        $current_page->init_variables();
        $search = " WHERE 1 ";
        $filter = "";
        $search_fields = $this->mysql->get_sql('SELECT page_query_field.*,field_type,field_name,link_table,link_field,link_lookup FROM page_query_field INNER JOIN page_table_field ON page_table_field.page_table_field_id = page_query_field.table_field_id WHERE page_query_field.page_id = '.$page_id);
        if(is_array($search_fields)){
          foreach($search_fields as $search_field){
            //If this is a timestamp we need to search a start + end date
            if($search_field['field_type'] == 'timestamp'){
              $current_page->variables['start_'.$search_field['field_name']] =  ( isset($_REQUEST["start_".$search_field['field_name']]) ? date('Y-m-d',strtotime($_REQUEST["start_".$search_field['field_name']])) : ($search_field['start_date']!= ''?($search_field['start_date'] =='Current Date'?date('Y-m-d',time()):date('Y-m-d',strtotime($search_field['start_date']))):NULL));
              $current_page->variables['end_'.$search_field['field_name']] =  ( isset($_REQUEST["end_".$search_field['field_name']]) ? date('Y-m-d',strtotime($_REQUEST["end_".$search_field['field_name']])) : ($search_field['end_date']!= ''?($search_field['end_date'] =='Current Date'?date('Y-m-d',time()):date('Y-m-d',strtotime($search_field['end_date']))):NULL));
              $search .=  (isset($current_page->variables['start_'.$search_field['field_name']])?"`".$search_field['table_name']."`.`".$search_field['field_name']."` >= '".$current_page->variables['start_'.$search_field['field_name']]."' AND " :"");
              $search .=  (isset($current_page->variables['end_'.$search_field['field_name']])?"`".$search_field['table_name']."`.`".$search_field['field_name']."` <= '".$current_page->variables['end_'.$search_field['field_name']]."' AND " :"");
            }
            else
              $search .=  (isset($current_page->variables[$search_field['field_name']])?"`".$search_field['table_name']."`.`".$search_field['field_name']."` = '".$current_page->variables[$search_field['field_name']]."' AND " :"");
  
            $fields[$search_field['field_name']] = array("type"=>($search_field['field_type'] ? $search_field['field_type']:'text'));
             if($search_field['link_table'] != '' && $search_field['link_field'] != ''){
               //Load up the linked table
                $fields[$search_field['field_name']]["lookup_table"] = $search_field['table_name'];
                $fields[$search_field['field_name']]["lookup_field"] = $search_field['field_name'];

                $primary = $this->mysql->get_sql("SHOW KEYS FROM `{$this->mysql->database}`.`{$search_field['table_name']}` WHERE Key_name = 'PRIMARY' ");
                $fields[$search_field['field_name']]["lookup_id"] = $primary[0]['column_name'];
             }
            //If this is a timestamp we need a start + end date
            if($search_field['field_type'] == 'timestamp'){
                $filter .= "\t\t<label>Start ".($search_field['label'] != ""?$search_field['label']:str_replace("_"," ",$search_field['field_name'])).($this->fields[$key]['required'] > 0 ? " *" : "").":</label>\n";
                $filter .="\t\t\t<input class='Filter datepickerclass' type='text' name='start_".$search_field['field_name']."' value ='".$current_page->variables['start_'.$search_field['field_name']]."' >\n";
                $filter .=($this->fields[$key]['description']!=''?"<div class='description'>".$this->fields[$key]['description']."</div>\n":"");
                $filter .= "\t\t<label>End ".($search_field['label'] != ""?$search_field['label']:str_replace("_"," ",$search_field['field_name'])).($this->fields[$key]['required'] > 0 ? " *" : "").":</label>\n";
                $filter .="\t\t\t<input class='Filter datepickerclass' type='text' name='end_".$search_field['field_name']."' value ='".$current_page->variables['end_'.$search_field['field_name']]."' >\n";
  
            }
            else{
                $filter .= "\t\t<label>".($search_field['label'] != ""?$search_field['label']:str_replace("_"," ",$search_field['field_name'])).($this->fields[$key]['required'] > 0 ? " *" : "").":</label>\n";
                $filter .="\t\t".$current_page->edit_field($search_field['field_name'],$search_field['table_name'],$current_page->variables,$fields)."\n";
                $filter .=($this->fields[$key]['description']!=''?"<div class='description'>".$this->fields[$key]['description']."</div>\n":"");
            }
          }
        }
        //$query_page = $this->mysql->get_sql('SELECT * FROM page_query WHERE page_id ='.$page_id);
        $sql_query = html_entity_decode($this->mysql->sql_query,ENT_QUOTES,'UTF-8');
        if($pos = strrpos($sql_query,'WHERE')) //Check if we already have a WHERE
            $sql_query = substr_replace($sql_query, $search." AND ", $pos, strlen('WHERE'));
        else if($pos = strrpos($sql_query,'GROUP BY')) //If we don't have a WHERE, check for a Group
            $sql_query = substr_replace($sql_query, $search, $pos, strlen('GROUP BY'));
        else  //If there is no group, just add the search to the end
            $sql_query .= $search;

        $total_query = html_entity_decode($this->mysql->total_query,ENT_QUOTES,'UTF-8'); //stripslashes($query_page[0]['total_query']);
        if($total_query != ''){
          if($pos = strrpos($total_query,'WHERE')) //Check if we already have a WHERE
              $total_query = substr_replace($total_query, $search." AND ", $pos, strlen('WHERE'));
          else if($pos = strrpos($total_query,'GROUP BY')) //If we don't have a WHERE, check for a Group
              $total_query = substr_replace($total_query, $search, $pos, strlen('GROUP BY'));
          else  //If there is no group, just add the search to the end
              $total_query .= $search;
        }

        $detailed_query = html_entity_decode($this->mysql->detailed_query,ENT_QUOTES,'UTF-8');
        if($detailed_query != ''){
          $search .=  (isset($current_page->variables['row_id'])?"`".$this->mysql->row_id."` = '".$current_page->variables['row_id']."' AND " :"");
          if($pos = strrpos($detailed_query,'WHERE')) //Check if we already have a WHERE
              $detailed_query = substr_replace($detailed_query, $search." AND ", $pos, strlen('WHERE'));
          else if($pos = strrpos($detailed_query,'GROUP BY')) //If we don't have a WHERE, check for a Group
              $detailed_query = substr_replace($detailed_query, $search, $pos, strlen('GROUP BY'));
          else  //If there is no group, just add the search to the end
              $detailed_query .= $search;
        }

        $current_page->page_id = $page_id;
        $current_page->sql_query = $sql_query;
        $current_page->total_query = $total_query;
        $current_page->detailed_query = $detailed_query;
        $current_page->row_id = $this->mysql->row_id;
        $current_page->search_filter = $filter;



        return $current_page;
       }
    }
}
?>
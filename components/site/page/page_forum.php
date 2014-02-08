<?php
/*  Class for Pages of Type Forum
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

class PageForumClass extends TableClass{

    public function __construct() {
      $this->variables = Array();
      $this->default_results = 25;
      $this->page_id = str_replace(SITEPATH."/components/",'',__FILE__); //Let table class know what page this is
      $this->page_title = str_replace("_"," ",substr($this->page_id,(strpos($this->page_id,'/')?strrpos($this->page_id,'/')+1:0),-4)); //Set the title based on the page name
      $this->flat_table = true;

      $this->db = new MySQLDatabase;
      if(isset($this->db)){
        $this->table_name = "page_forum";
    	$this->primary_key = "page_id";
    	$this->fields = array(
         "page_id" => array("type" => "int", "hidden" => TRUE,"min_length" => 1),
         "forum_name" => array("type" => "text","min_length" => 1, "max_length" => 45,"searchable"=>TRUE,"description"=>"Letters and Numbers Only, Keep Concise"),
         "database" => array("type" => "list","min_length" => 1, "max_length" => 45,"options"=>array('site')),
         "primary_key" => array("type" => "text","min_length" => 1,"hidden"=>TRUE),
         "categories" => array("type" => "auto","min_length" => 1, "max_length" => 45,"options"=>array('General')),
    	 );
        $this->mysql = new MySQLClass($this->db->get_db(),$this->fields,$this->table_name,$this->primary_key);

        $this->init_variables();
      }
      else
        die('Missing DB Class');
	}
    public function init_variables(){
        $databases = $this->mysql->get_sql("SHOW databases WHERE `database` NOT IN ('information_schema','apsc','atmail','horde','mysql','psa') AND `database` NOT LIKE '%phpmyadmin%'");
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
           case "Add":
                parent::action_check($action);
                //Check if there was an error and then intialize the table
                if($this->mysql->last_error == ''){
                    $current_page = $this->load($this->variables[$this->primary_key]);
                    if(!$current_page->mysql->update_table())
                        die($current_page->mysql->last_error);//IF table does not exists and we can't create die!
                    $current_page->mysql->index_table();
                }
             break;

             case "Delete":
                $current_page = $this->load($this->variables[$this->primary_key]);
                if(isset($current_page->mysql) && !$current_page->mysql->delete_table())
                    echo $current_page->mysql->last_error;//IF table does not exists and we can't delete die!
                parent::action_check($action);


             break;
             case "Edit":

                $this->mysql->{$this->primary_key} = $this->variables[$this->primary_key];
                $this->mysql->load();
                if($this->mysql->database == '')
                    echo "Select a database for the table to be added to:";
                parent::action_check($action);
             break;
             case "Set_Field":
             case "Update":
                $old_field_names = array();
                $this->init_variables();
                //Load up the current un-altered table
                if(isset($this->variables['get_id']))
                    $this->variables[$this->primary_key] = $this->variables['get_id'];
                $this->mysql->{$this->primary_key} =  $this->variables[$this->primary_key];
                $this->mysql->load();

                $forum_name = $this->mysql->forum_name;
                if($forum_name != '')
                    $current_page = $this->load($this->variables[$this->primary_key]);
                if(isset($this->variables['forum_name']) && $this->variables['forum_name']!= '')
                    $this->variables['primary_key'] = $this->variables['forum_name'].'_id';

                //If the page title is changed, we need to also change the table name and primary id
                /*
                if($this->variables['action'] == 'Set_Field' && isset($_REQUEST['page_title'])){
                    $this->mysql->clear();
                    $this->mysql->{$this->primary_key} =$this->variables['get_id'];
            		$this->mysql->load();
            		foreach(array_keys($this->fields) as $key){
                        if(isset($this->variables[$key]) && $this->variables[$key] != '')
            			    $this->mysql->$key = $this->variables[$key];
                    }
            		if(!$this->mysql->save()  || $this->mysql->last_error != "")
                		$this->variables['error']= $this->mysql->last_error;
                }      */
                parent::action_check($action);
                $this->mysql->index_table();
                //Don't do anything if we have an error
                if($this->mysql->last_error ==''){
                  //Check if we need to change the table name
                  if($forum_name != '' && isset($this->variables['forum_name']) && $this->variables['forum_name'] != $forum_name && $this->variables['forum_name']!= ''){
                      $old_field_names[$this->variables['primary_key']] = $forum_name.'_id';
                      if(!$current_page->mysql->change_table_name($this->variables['forum_name']))
                        die("Table Name Change:".$current_page->mysql->last_error);
                    //Load up the new altered table
                    $current_page = $this->load($this->variables[$this->primary_key]);
                    if(!$current_page->mysql->update_table($old_field_names))
                            die("Primary Key Change:".$current_page->mysql->last_error);//IF table does not exists and we can't create die!
                    $current_page->mysql->index_table();
                  }

                }
           break;
           default:
              parent::action_check($action);
           break;
        }
    }

    public function load($page_id,$custom_code= ''){
      if($page_id < 0){
            $this->variables['error'] = 'Invalid Page Id';
            return false;
      }
      else{
        //Load up the requested page
        $this->mysql->{$this->primary_key} = $page_id;
        $this->mysql->load();
        $db = new MySQLDatabase;
        $temp = $db->get_db();
        $temp->select_db($this->mysql->database);

        $forum_fields = array(
         $this->mysql->primary_key => array("type" => "int", "hidden" => TRUE,"min_length" => 1),
         "parent_forum_id" => array("type" => "int", "hidden" => TRUE,"min_length" => 0,"default"=>0),
         "category" => array("type" => "list","min_length" => 1, "max_length" => 45,"options"=>explode(",",$this->mysql->categories),"searchable"=>TRUE),
         "topic" => array("type" => "text","min_length" => 1, "max_length" => 45,"searchable"=>TRUE),
         "comments" => array("type" => "textarea","min_length" => 1, "max_length" => 5000,"searchable"=>TRUE),
    	 );


        if(class_exists($this->mysql->database.$this->mysql->forum_name."Class") != true)
            eval("class ".$this->mysql->database.$this->mysql->forum_name."Class EXTENDS ForumClass { ".html_entity_decode($custom_code,ENT_QUOTES,'UTF-8')." };");
        eval("\$current_page = new ".$this->mysql->database.$this->mysql->forum_name."Class(new MySQLClass(\$temp,\$forum_fields,\$this->mysql->forum_name,\$this->mysql->primary_key));");

        $current_page->page_id = $page_id;
        $current_page->where_criteria[] = array("field" => "parent_forum_id", "operator"=>"=", "argument"=>"0");
        //$this->init_variables();

        return $current_page;
       }
    }
}
class ForumClass Extends TableClass{

    public function action_check($action = NULL){
      //Run through any requests
      switch (isset($action) ? $action : $this->variables['action']) {
         case "Get_Details":
              ob_clean();
              $this->init_variables();
              echo $this->forum_comments();
         break;
         default:
              parent::action_check($action);
          break;
      }
    }
    public function row_controls(&$result){
            $answer ="<div row='".$result->{$this->primary_key}."' style='cursor: pointer;' class='plus' alt='Comments' title='Comments'></div>";
            if($this->user->user_level == 'Admin' OR $this->user->user_id == $result->creator)
                $answer.= parent::row_controls($result);
            return $answer;
    }
    public function forum_comments(){
        $odd = false;
        $answer = "";
        $results = $this->mysql->get_sql('SELECT * FROM {$this->table_name} WHERE parent_forum_id='.$this->mysql->detailed_id);
        //Display the contents of each field in array
        if(!empty($results)){
          $answer .= "\t<tr class='contain".str_replace(' ','__',$this->variables[$this->primary_key])."' ". ( $odd ? ' class="odd"' : '')."><td colspan='100%'>\n";
          $answer .= "<table class='content_table'>\n";
            $answer .="<thead>\n\t<tr>\n";
                foreach ( array_keys($results[0]) as $key )	$answer .="\t\t<th>".str_replace("_"," ",$key)."</th>\n";
        	$answer .="\t</tr>\n</thead>\n<tbody>\n";
          foreach ( $results as $result )	{
              $answer .= "</tr>\n";
              foreach ( array_keys($results[0]) as $key ) {
                      $answer .= "\t\t<td>{$result[$key]}</td>\n";
              }
              $answer .= "\t</tr>\n";
              $odd = !$odd;
           }
         $answer .= "</tbody>\n</table>\n\t</td></tr>\n";
        }
         return $answer;
    }

}
?>
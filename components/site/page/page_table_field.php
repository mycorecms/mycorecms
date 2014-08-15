<?php
/*  Class for Table fields 
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

class PageTableFieldClass extends TableClass{
    private $table;

    public function __construct() {
      $this->variables = Array();
      $this->default_results = 25;
      $this->page_id = str_replace(SITEPATH."/components/",'',__FILE__); //Let table class know what page this is
      $this->page_title = str_replace("_"," ",substr($this->page_id,(strpos($this->page_id,'/')?strrpos($this->page_id,'/')+1:0),-4)); //Set the title based on the page name

      $this->db = new MySQLDatabase;
      if(isset($this->db)){
         $this->table_name = "page_table_field";
    	 $this->primary_key = "page_table_field_id";
         $this->fields = array(
         "page_table_field_id" => array("type" => "int", "hidden" => TRUE,"min_length" => 1),
    	 "page_id" => array("type" => "int", "hidden" => TRUE,"min_length" => 1),
         "field_type" => array("type"=>"list","min_length" => 1, "max_length" => 100, "searchable" => TRUE, "options"=>array('text','integer','big_integer','double','currency','password','textarea','file','image','url','email_address','checkbox','checkbox-list','bool','radio-list','range','list','list_other','auto','distinct_list','table_link','table_link-checkboxes','date','timestamp','time'),"js"=>"var page = getCurrentPage(this);page.find('input, select').css('background-color','white'); get_queue(page.find('.current_page').val()+ '&action=get_requirements&field_type='+ jQuery(this).val() +'&jquery=TRUE', function(msg) { if(msg != ''){ var fields = msg.split(',');for (var i = 0; i < fields.length; i++) {jQuery('.page_table_field_'+fields[i]).css('background-color','red'); jQuery('.page_table_field_'+fields[i]).parent().parent().removeClass('hidden');}}});"),
         "list" => array("type" => "text","min_length" => 0, "max_length" => 45, "hidden" => TRUE,"description"=>"Please list items comma seperated ex(1,2,3,etc)."),
         "field_name" => array("type" => "text","min_length" => 1, "max_length" => 45, "searchable" => TRUE,"description"=>"Letters and Numbers Only, Keep Concise"),
         "label" => array("type" => "text","min_length" => 0, "max_length" => 255, "searchable" => TRUE,"description"=>"Over-rides the field name display, can use special characters like !+?. Max length is 255."),
         "priority" => array("type"=>"range","min_length" => 1, "options" => array("1","100"), "searchable" => TRUE,"default"=>"1","description"=>"Determines the order of the fields"),
         "searchable" => array("min_length" => 0, "type"=>"checkbox", "searchable" => TRUE),
         "required" => array("min_length" => 0, "type"=>"checkbox", "searchable" => TRUE),
         "hidden" => array("min_length" => 0, "type"=>"checkbox", "searchable" => TRUE),
         "default_value" => array("type" => "text","min_length" => 0, "max_length" => 45),
         "description" => array("type" => "text","min_length" => 0, "max_length" => 100,"description"=>"Adds a description on the right"),
         "options" => array("type" => "textarea","min_length" => 0,"hidden"=>TRUE, "description"=>"Comma Seperated List of Options"),
         "range" => array("type" => "text","min_length" => 0,"hidden"=>TRUE, "max_length" => 45,"description"=>"Start Number,End Number EX(1,10)"),
         "link_table" => array("type" => "table_link","hidden"=>TRUE, "lookup_table"=>"page_table","lookup_field"=>"`database`,table_name","lookup_id"=>'page_id',"description"=>"For Distinct List + Table Link, lets you choose the table from which the data will populate", "options"=>array('test','test2'),"js"=>"var page = getCurrentPage(this);get_queue(page.find('.current_page').val()+ '&action=Edit_Field&get_key=link_field&link_table='+ jQuery(this).val() +'&jquery=TRUE', function(msg) {jQuery('.link_field_page_table_field_field').replaceWith(msg);});"),
         "link_field" => array("type" => "table_link-checkboxes", "hidden"=>TRUE,"lookup_table"=>"page_table_field","lookup_field"=>"field_name","lookup_id"=>'page_table_field_id',"description"=>"For Distinct List + Table Link, lets you choose the field data displayed in the drop-down"),
         "where" => array("type" => "text","min_length" => 0,"hidden"=>TRUE, "max_length" => 255,"description"=>"Limit Results from table link. Ex: Where disable != 1"),
         //"link_lookup" => array("type" => "table_link", "lookup_table"=>"page_table_field","lookup_field"=>"field_name","lookup_id"=>'page_table_field_id',"description"=>"For Distinct List + Table Link, lets you choose the field which is stored, must be an ID number for Table Link"),
         "field_mask" => array("type"=>"list_other","min_length" => 0, "max_length" => 45,"options"=>array("zip","zip+4","phone number")),
         "field_css" => array("min_length" => 0, "max_length" => 2000,"type"=>"textarea"),
         "field_js" => array("min_length" => 0, "max_length" => 2000,"type"=>"textarea"),
    	 );
        $this->mysql= new MySQLClass($this->db->get_db(),$this->fields,$this->table_name,$this->primary_key);
        //$this->init_variables();
      }
      else
        die('Missing DB Class');
	}
    public function init_variables(){

        //put any custom variables you want here
        parent::init_variables();
        if(isset($this->variables['page_id'])){
          require_once('page_table.php');
          $this->table = new PageTableClass;
          $this->table->mysql->page_id = $this->variables['page_id'];
          $this->table->mysql->load();
          $this->table->user = $this->user;
        }
        if(!isset($this->fields['link_field']['where']) || $this->fields['link_field']['where'] == '')
               $this->fields['link_field']['where'] = ($this->variables['link_table'] != ''?" WHERE page_id =  '{$this->variables['link_table']}' ":" WHERE page_id IS NULL" );

        $this->requirements = array(
                        'range'=> array("required_fields"=>"range"),
                        'list'=> array("required_fields"=>"options"),
                        'list_other'=> array("required_fields"=>"options"),
                        'radio-list'=> array("required_fields"=>"options"),
                        'checkbox-list'=> array("required_fields"=>"options"),
                        'distinct_list'=> array("required_fields"=>"link_table,link_field,where"),
                        'table_link'=> array("required_fields"=>"link_table,link_field,where"),
                        'table_link-checkboxes'=> array("required_fields"=>"link_table,link_field,where"),
                        );

    }
    public function action_check($action = NULL){
        $this->init_variables();
        switch (isset($action) ? $action : $this->variables['action']) {
          case "get_requirements":
                        if(isset($this->requirements[$this->variables['field_type']]))
                            echo $this->requirements[$this->variables['field_type']]['required_fields'];
            break;
           case "Add":
                foreach(explode(',',$this->requirements[$this->variables['field_type']]['required_fields']) as $key){
                        if($key != '' AND $this->variables[$key] ==0 AND $this->variables[$key] == '' AND $key!="where"){
                             echo "Missing required field: {$key}";
                             exit();
                        }
                }
                $this->variables['field_name'] = strtolower(str_replace(' ','_',$this->variables['field_name']));
                parent::action_check($action);
                //Check if there was an error and then intialize the table
                $current_page = $this->table->load($this->mysql->page_id);
                if($this->mysql->last_error == '')
                    $current_page->mysql->add_field($this->variables['field_name']);

             break;
             case "Delete":
                  $this->mysql->{$this->primary_key} = $this->variables[$this->primary_key];
                  $this->mysql->load();
                  $current_page = $this->table->load($this->mysql->page_id);
                  if(!$current_page->mysql->delete_field($this->mysql->field_name))
                    echo $current_page->mysql->last_error;//IF table does not exists and we can't create die!
                    parent::action_check($action);
             break;
             case "Update":
              $this->variables['field_name'] = strtolower(str_replace(' ','_',$this->variables['field_name']));
              if(isset($this->requirements[$this->mysql->field_type]['required_fields'])){
                 foreach(explode(',',$this->requirements[$this->variables['field_type']]['required_fields']) as $key){
                            if($key != '' AND $this->variables[$key] ==0 AND $this->variables[$key] == '' AND $key!="where"){
                                 echo "Missing required field: {$key}";
                                 exit();
                            }
                 }
              }
                $old_field_names = NULL;
                $this->mysql->{$this->primary_key} =$this->variables[$this->primary_key];
            	$this->mysql->load();
                //Special check to see if we're changing the field name, if so we need the old field name to update the table
                if($this->variables['field_name'] != $this->mysql->field_name)
                    $old_field_names[$this->variables['field_name']] = $this->mysql->field_name;
                parent::action_check($action);
                //Don't do anything if we have an error
                if($this->mysql->last_error ==''){
                  //Load up the new altered table
                  $current_page = $this->table->load($this->mysql->page_id);
                  if(!$current_page->mysql->update_table($old_field_names))
                          die($current_page->mysql->last_error);//IF table does not exists and we can't create die!
                }
           break;
           case "Set_Field":
                $old_field_names = NULL;
                $this->init_variables();
                //Special check to see if we're changing the field name, if so we need the old field name to update the table
                if($this->variables['get_key'] == 'field_name'){
                    $this->mysql->{$this->primary_key} = $this->variables['get_id'];
                    $this->mysql->load();
                    $old_field_names[$this->variables['field_name']] = $this->mysql->field_name;
                }
                parent::action_check($action);
                //Don't do anything if we have an error
                if($this->mysql->last_error ==''){
                  //Load up the new altered table
                  $current_page = $this->table->load($this->mysql->page_id);
                  if(!$current_page->mysql->update_table($old_field_names))
                          die($current_page->mysql->last_error);//IF table does not exists and we can't create die!
                }
           break;
           case "Edit":
                 if ( isset($this->variables[$this->primary_key]) ){
            	    $this->mysql->clear();
                    $this->mysql->{$this->primary_key} =$this->variables[$this->primary_key];
            	    $this->mysql->load();
            	    if(isset($this->requirements[$this->mysql->field_type]['required_fields'])){
                	foreach(explode(',',$this->requirements[$this->mysql->field_type]['required_fields']) as $key){
                	      unset($this->fields[$key]['hidden']);
                        }
                    }
                 if($this->mysql->link_table != '')
                        $this->fields['link_field']['where'] = " WHERE page_id =  '".$this->mysql->link_table."' ";
            	}
                parent::action_check($action);
            break;
           default:
              parent::action_check($action);
           break;
        }
    }

    public function load($page_id){
      if($page_id < 0){
            $this->variables['error'] = 'Invalid Page Id';
            return false;
      }
      else{
        require_once('page_table.php');
        $this->table = new PageTableClass;
        $this->table->mysql->page_id = $page_id;
        $this->table->mysql->load();
        $this->mysql->page_id  =  $page_id;
        $this->mysql->load();
        //Load up the requested page
        $field_criteria[] = array("field" => "page_id", "operator"=>"=", "argument"=>"$page_id");
        $field_order[] = array("field" => "priority", "ascending" => "TRUE");
        $field_order[] = array("field" => "page_table_field_id", "ascending" => "TRUE");


        $fields = NULL;
        //Populate page fields
        foreach($this->mysql->get_all($field_criteria,$field_order) as $a_field){
            $fields[$a_field->field_name] = array("type"=>($a_field->field_type ? $a_field->field_type:'text'),
                                                "min_length"=>($a_field->required ==1 ? 1:($a_field->field_type =='password' ? 6:0)),
                                                "max_length"=>($this->mysql->max_length(($a_field->field_type ? $a_field->field_type:'text'))),
                                                "label"=>($a_field->label != '' ? html_entity_decode($a_field->label,ENT_QUOTES,'UTF-8'):NULL),
                                                "js"=>($a_field->field_js != '' ? $a_field->field_js:NULL),
                                                "style"=>($a_field->field_css != '' ? $a_field->field_css:NULL),
                                                "mask"=>($a_field->field_mask != '' ? $a_field->field_mask:NULL),
                                                "description"=>($a_field->description != '' ? $a_field->description:NULL),
                                                "default"=>($a_field->default_value != '' ? $a_field->default_value:NULL),
                                                "options"=>($a_field->options != '' ? explode(',',$a_field->options):($a_field->range != '' ? explode(',',$a_field->range):NULL)),
                                                "searchable"=>($a_field->searchable == 1 ? TRUE:NULL),
                                                "where"=>($a_field->where !='' ? $a_field->where:NULL),
                                                "hidden"=>($a_field->hidden == 1 ? TRUE:NULL),
                                                );
                                                
        //populate required fields
        if(!isset($fields[$this->table->mysql->primary_key]))
            $fields[$this->table->mysql->primary_key] =  array("min_length"=>1,"type" => "int", "hidden" => TRUE);
         if($a_field->link_table != '' && $a_field->link_field != ''){
           //Load up the linked table
            $this->table->mysql->page_id = $a_field->link_table;
            $this->table->mysql->load();
            $fields[$a_field->field_name]["lookup_table"] = $this->table->mysql->table_name;

            $old_id = $this->mysql->{$this->primary_key};
            $lookup_fields = explode(',',$a_field->link_field);

            foreach($lookup_fields as $field){
              $fields[$a_field->field_name]["lookup_field"] = (isset($fields[$a_field->field_name]["lookup_field"])?$fields[$a_field->field_name]["lookup_field"]:"");
              $this->mysql->{$this->primary_key}  =  $field;
              $this->mysql->load();
              $fields[$a_field->field_name]["lookup_field"] .= $this->mysql->field_name.",";
            }
            $fields[$a_field->field_name]["lookup_field"] = rtrim($fields[$a_field->field_name]["lookup_field"], ',');
            $fields[$a_field->field_name]["lookup_id"] = ($a_field->field_type=='distinct_list'?$fields[$a_field->field_name]["lookup_field"]:$this->table->mysql->primary_key);


            $this->mysql->{$this->primary_key}  =  $old_id;
            $this->mysql->load();

            //reload the current page
            $this->table->mysql->page_id = $page_id;
            $this->table->mysql->load();
         }
        }
        $page_result = $this->mysql->get_sql("SELECT * FROm page WHERe page_id = {$page_id}");
        //if this table has a parent it needs the parents's primary key
        if($page_result[0]['parent_page_id'] > 0){
            //load up the page primary key
            $this->table->mysql->page_id = $page_result[0]['parent_page_id'];
            $this->table->mysql->load();
            if(!isset($fields[$this->table->mysql->primary_key]))
            $fields[$this->table->mysql->primary_key] = array("min_length"=>1,"type" => "int", "hidden" => TRUE);
            //reload the page we are working on
            $this->table->mysql->page_id = $page_id;
            $this->table->mysql->load();
        }

        return $fields;
       }
    }


}
?>
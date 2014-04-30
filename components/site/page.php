<?php
/*
    Class for Generating Web Pages
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

class PageClass extends TableClass{
    public $table_fields;
    public function __construct() {
      $this->variables = Array();
      $this->default_results = 25;
      $this->page_id = str_replace(SITEPATH."/components/",'',__FILE__); //Let table class know what page this is
      $this->page_title = str_replace("_"," ",substr($this->page_id,(strpos($this->page_id,'/')?strrpos($this->page_id,'/')+1:0),-4)); //Set the title based on the page name
      $this->db = new MySQLDatabase;
      if(isset($this->db)){
        $this->table_name = "page";
    	$this->primary_key = "page_id";
    	$this->fields = array(
    	 "page_id" => array("type" => "int", "hidden" => TRUE,"min_length" => 1),
         "page_type" => array("type" => "list","min_length" => 1, "max_length" => 45, "options" => array('Table','Query','Blank'), "searchable" => TRUE),
         "page_title" => array("type" => "text","min_length" => 1, "max_length" => 45, "unique" => TRUE, "searchable" => TRUE,"description"=>"Letters and Numbers Only"),
         "section"=>array("type"=>"list+other","min_length" => 1, "max_length" => 45, "searchable"=>TRUE, "options"=>array('site')),
         "page_css" => array("type" => "textarea","min_length" => 0, "max_length" => 2000,"hidden"=>TRUE),
         "page_js" => array("type" => "textarea","min_length" => 0, "max_length" => 2000,"hidden"=>TRUE),
         "parent_page_id" => array("type" => "table_link", "lookup_table"=>"page", "lookup_field"=>"page_title","lookup_id"=>"page_id","description"=>"Links this page as a tab inside the selected page."),
         //"priority" => array("type"=>"range","min_length" => 0, "options" => array("1","100") ),
         "custom_code" => array("type"=>"textarea","min_length" => 0),
         "hidden" => array("type"=>"checkbox","min_length" => 0 ),
    	 );
        $this->mysql = new MySQLClass($this->db->get_db(),$this->fields,$this->table_name,$this->primary_key);
        $this->multiple_execute = array('Export');
        //$this->init_variables();
      }
      else
        die('Missing DB Class');
	}
    //Pull at list of all pages + fields in the database and return
    public function get_all(){
      $pages = array();
      if((int)$this->mysql->get_counts() > 0){
        $pages = $this->mysql->get_all();
        }
    return $pages;
    }
    public function build_pages(){
    if(isset($this->variables['page_id']) && $this->variables['page_id'] > 0){
      $this->mysql->page_id = $this->variables['page_id'];
      $this->mysql->load();

      if($this->mysql->page_type != ''){
        $this->children = array(
          ucfirst($this->mysql->page_type)=> array("action"=>"","get_page"=>"site/page/page_".strtolower($this->mysql->page_type).".php"),
        );
        //Don't let page type be changed once set.
        $this->fields['page_type']['hidden'] = TRUE;
      }
      $this->fields['parent_page_id']['where'] = "WHERE page_id != {$this->mysql->page_id} ORDER BY page_title";
    }
    //Lookup any existing sections in both the page database + static folders
    $sections = $this->mysql->get_sql("SELECT GROUP_CONCAT(DISTINCT section) as section FROM page WHERE hidden != 1");
    $this->fields['section']['options'] = (isset($sections[0])?explode(',',$sections[0]['section']):NULL);
    $this->read_dir(SITEPATH."/components");
    //Get all the available page types
    if(is_dir(SITEPATH."/components/site/page/")){
                             if ($handle = opendir(SITEPATH."/components/site/page/")) {
                                while (false !== ($entry = readdir($handle))) {
                                    if (!is_dir(SITEPATH."/components/site/page/".$entry)&& $entry != "." && $entry != ".."){
                                        require_once SITEPATH."/components/site/page/".$entry;
                                        eval("\$class_page = new ".str_replace("_","",str_replace('.php','',$entry))."Class();");
                                        if(isset($class_page->children)){
                                           foreach($class_page->children as $child)
                                              $excludes[]=str_replace("site/page/","",$child['get_page']);
                                        }
                                    }
                                }
                                closedir($handle);
                            }
                            if ($handle = opendir(SITEPATH."/components/site/page/")) {
                                $this->fields['page_type']['options'] = NULL; //Clear out options
                                while (false !== ($entry = readdir($handle))) {
                                    if (!is_dir(SITEPATH."/components/site/page/".$entry)&& $entry != "." && $entry != ".." && !in_array($entry,$excludes)){
                                        $this->fields['page_type']['options'][]= ucfirst(str_replace(array('page_','.php'),"",$entry));
                                    }
                                }
                                closedir($handle);
                            }
    }

    }
   public function read_dir($path){
        if ($handle = opendir($path)) {
            while (false !== ($entry = readdir($handle))) {
                if (is_dir($path."/".$entry)&& $entry != "." && $entry != ".." && str_replace(SITEPATH."/components/","",$path."/".$entry) != "site/page"){
                    $this->fields['section']['options'][]= str_replace(SITEPATH."/components/","",$path."/".$entry);
                    $this->read_dir($path."/".$entry);
                }
            }
            closedir($handle);
        }
    }
   public function action_check($action = NULL){
     $this->init_variables();
     //check if this is a properites page, if so update the appropriate page
        switch (isset($action) ? $action : $this->variables['action']) {
          case "Add":
                parent::action_check($action);
                //Check if there was an error and then intialize the table
                if($this->mysql->last_error == ''){
                    eval("require_once 'page/page_".strtolower($this->mysql->page_type).".php';");
                    eval("\$class_page = new Page".$this->mysql->page_type."Class();");


                    $_REQUEST['primary_key'] = strtolower(str_replace(" ","_",$this->mysql->page_title))."_id";
                    $_REQUEST['table_name'] = strtolower(str_replace(" ","_",$this->mysql->page_title));
                    $_REQUEST['page_id'] = $this->mysql->page_id;
                    //Create an instance of the new sub-page
                    $class_page->user = $this->user;
                    $class_page->mysql->requirement_check = false;
                    $class_page->action_check($action);
                }
          break;
          case "Update":

            $this->mysql->{$this->primary_key} = $this->variables[$this->primary_key];
            $this->mysql->load();
            $old_title = $this->mysql->page_title;
            parent::action_check($action);
            //Change the user page access title if it gets changed
            if($this->mysql->last_error =='' && $this->variables['page_title'] != $old_title)
              $this->mysql->set_sql("UPDATE user_page_access SET page = REPLACE(page,'{$old_title}.{$this->mysql->page_id}','{$this->mysql->page_title}.{$this->mysql->page_id}')");
            //echo $this->mysql->last_error;
          break;
          case "Delete":
           $this->mysql->page_id =  $this->variables['page_id'];
           $this->mysql->load();
           $page_type = $this->mysql->page_type;
           $this->children = array(ucfirst($this->mysql->page_type)=> array("action"=>"","get_page"=>"site/page/page_".strtolower($this->mysql->page_type).".php"),);
            parent::action_check($action);
            if($this->mysql->last_error == ''){
                    eval("require_once 'page/page_".strtolower($page_type).".php';");
                    eval("\$class_page = new Page".$page_type."Class();");
                    $class_page->variables['page_id'] = $this->variables['page_id'];
                    $class_page->action_check('Delete');
                    echo $class_page->mysql->last_error;

              //Clear out the page from the menu system
              $this->mysql->set_sql("UPDATE user_page_access SET page = REPLACE(page,'{$this->mysql->page_title}.{$this->mysql->page_id},','')");
              $this->mysql->set_sql("UPDATE user_page_access SET page = REPLACE(page,',{$this->mysql->page_title}.{$this->mysql->page_id}','')");
              $this->mysql->set_sql("UPDATE user_page_access SET page = REPLACE(page,'{$this->mysql->page_title}.{$this->mysql->page_id}','')");
            }
          break;
          case "auto_populate":
                $this->auto_populate($this->mysql->escape_string($_REQUEST['dbase']));
                $this->show_results();
          break;
          case "Add_New":
          case "Edit":
          $this->build_pages();
          //echo "<script>\n";

             //echo "jQuery('.page_custom_code:visible').each( function(){CodeMirror.fromTextArea(jQuery(this).get(0),{mode:'text/x-php',lineNumbers : true,matchBrackets : true})}); </script>\n";
             //echo "CodeMirror.fromTextArea(jQuery('.page_page_js').get(0),{mode:'js'}); \n";
             //echo "CodeMirror.fromTextArea(jQuery('.page_page_css').get(0),{mode:'css'});</script> \n";
             parent::action_check($action);
             echo "<script>setTimeout(function(){jQuery('.page_custom_code:visible').each( function(){CodeMirror.fromTextArea(jQuery(this).get(0),{mode:'text/x-php',lineNumbers : true,matchBrackets : true})});jQuery('.CodeMirror').each(function(i, el){el.CodeMirror.refresh();})},100);</script>\n";

          break;
          case "Export":
           if($this->permissions['export']){
             if(!isset($_REQUEST['bulk']))
                  parent::action_check($action);
             else{
               $this->where_criteria[] = array("field" => "{$this->primary_key}", "operator"=>"IN", "argument"=>"{$_REQUEST['bulk']}");
                  $results = $this->mysql->get_all($this->where_criteria,$this->order_by);
                  //print_r($results);
                  $this->export($results);
             }
            }
           break;
          default:
                parent::action_check($action);
         break;
        }
   }
    public function load($page_id){
      if($page_id <= 0){
            $this->variables['error'] = 'Invalid Page Id';
            return false;
      }
      else{

        //Load up the requested page
        $this->mysql->clear();
        $this->mysql->{$this->primary_key} = $page_id;
        $this->mysql->load();
        $current_page = null;
        if($this->mysql->page_type != ''){
          eval("require_once 'page/page_".strtolower($this->mysql->page_type).".php';");
          eval("\$class_page = new Page".$this->mysql->page_type."Class();");
          $current_page = $class_page->load($page_id,$this->mysql->custom_code);
          $current_page->db = $this->db;
          $current_page->page_id = $page_id;
          $current_page->page_title = $this->mysql->page_title;
          $children = $this->mysql->get_sql('SELECT page_title,page_id FROM page WHERE parent_page_id = '.$page_id);
          foreach($children as $child)
              $current_page->children[ucfirst($child['page_title'])] = array("action"=>"","get_page"=>"{$child['page_id']}","table_name"=>$class_page->table_name);
        }
        return $current_page;
       }
    }
    function show_results(){
        echo "<div style='font-weight:bold;text-align:center'>";
        /*$current_dbs = $this->mysql->get_sql("SELECT GROUP_CONCAT( DISTINCT `database` SEPARATOR \"','\") as dbs FROM `page_table`");
        $databases = $this->mysql->get_sql("SHOW databases WHERE `database` NOT IN (".(isset($current_dbs[0])?"'".$current_dbs[0]['dbs']."',":"")."'site','information_schema','apsc','atmail','horde','mysql','psa') AND `database` NOT LIKE '%phpmyadmin%'");

        if(isset($databases[0])){
          echo "\nSelect an existing table to auto-populate pages:";
          echo "\t<select name='auto_populate' onchange='var page = getCurrentPage(this);get_queue(page.find(\".current_page\").val()+ \"&action=auto_populate&dbase=\"+ jQuery(this).val() +\"&jquery=TRUE\", function(msg) {page.html(msg);});'>\n";
          echo "\t\t<option></option>\n";
          foreach($databases as $database){
                echo "\t\t<option>{$database['Database']}</option>\n";
          }
          echo "\t</select><br />\n";
        }   */
        echo "*NOTE: Deleting a page of type table also deletes the table with all it's data.</div>";
        parent::show_results();
        
    }
    public function auto_populate($db_name){
      //Check if there are any tables not already present in page list
        $current_tables = $this->mysql->get_sql("SELECT GROUP_CONCAT( DISTINCT table_name SEPARATOR \"','\") as tables FROM `page_table`");
        if(isset($current_tables[0]['tables']))  //only get tables that aren't already in the database
            $table_list = $this->mysql->lookup_tables($db_name,"'".$current_tables[0]['tables']."'");
        echo $this->mysql->last_error;
        //Add any tables in database that are not already entered
        foreach($table_list as $a_table){
            //Locate Primary ID
            $this->mysql->set_sql("INSERT INTO page (page_title,page_type,secure_page,section) VALUES ('{$a_table}','Table',TRUE,'{$db_name}')");
            echo $this->mysql->last_error;
            $new_row = $this->mysql->get_sql("SELECT {$this->primary_key} FROM page WHERE page_title= '{$a_table}'");
            $new_page_id = $new_row[0][$this->primary_key];

            $column_list = $this->mysql->get_columns($a_table,$db_name);
            foreach($column_list as $column){
                if($column['Key'] == 'PRI'){
                    $this->mysql->set_sql("INSERT INTO page_table (page_id,table_name,primary_key,`database`) VALUES ({$new_page_id},'{$a_table}','".$column['Field']."','{$db_name}')");
                    echo $this->mysql->last_error;
                    break;
                }
            }
            //Create Fields
            foreach($column_list as $column)
                    $this->mysql->set_sql("INSERT INTO page_table_field ({$this->primary_key},field_name,field_type,default_value) VALUES ({$new_page_id},'".$column['Field']."','".$this->mysql->get_type($column['Type'])."','".$column['Default']."')");
        }
    }
    public function export(&$results){
    	if ( sizeof($results) > 0 ) {
		ob_clean();
                $filename =  "page_templates_".date('m_d_Y');
		header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
                header("Content-Disposition: inline; filename=\"" . $filename . ".xml\"");
		echo '<?xml version="1.0" encoding="UTF-8"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">';
		echo "<Worksheet ss:Name='page'><Table>";
		$line=$ids ="";
                foreach ( array_keys($this->fields) as $key ) $line .= "<Cell><Data ss:Type='String'>". $key."</Data></Cell>";
			echo "<Row>".$line."</Row>";
    			foreach ( $results as $result )	{
    				$line = "";
    				foreach ( array_keys($this->fields) as $key ) {
                                        if(isset($this->fields[$key]['lookup_table']) && isset($this->fields[$key]['lookup_field'])) $line .= "<Cell><Data ss:Type='String'>" .htmlentities($this->lookup_id($this->fields[$key]['lookup_table'],$this->fields[$key]['lookup_field'],( isset($this->fields[$key]["lookup_id"]) ? $this->fields[$key]["lookup_id"] : $key),$result->$key), ENT_COMPAT, "UTF-8") ."</Data></Cell>";
    					else if($this->fields[$key]['type'] == 'int') $line .= "<Cell><Data ss:Type='Number'>".$result->$key."</Data></Cell>";
    					else $line .= "<Cell><Data ss:Type='String'>" . htmlentities($result->$key, ENT_COMPAT, "UTF-8") ."</Data></Cell>";
    				}
    				echo "<Row>".$line."</Row>";
    				$ids .= $result->{$this->primary_key}.",";
    				$page_types .=  $result->page_type;
    			}
    			echo "</Table></Worksheet>";
                        foreach($this->fields['page_type']['options'] as $page_type){
                          if($page_type !=  'Blank'){
                                        eval("require_once 'page/page_".strtolower($page_type).".php';");
                                        eval("\$child_page = new Page".$page_type."Class();");
                                        $this->export_child($child_page,$this->primary_key,substr($ids, 0, strlen($ids)-1 ));
                          }
                        }
    			echo "</Workbook>";
    			exit;
    	} else
            echo"<p>No records found.</p>";

    }
}
?>
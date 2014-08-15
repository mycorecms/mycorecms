<?php
/*
    Class for Importing data + Page Templates
    Copyright (C) 2007-2014 MyCoreCMS

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details. <http://www.gnu.org/licenses/>.
 */

class ImportClass extends TableClass{

    public function __construct() {
      $this->page_id = str_replace(SITEPATH."/components/",'',__FILE__); //Let table class know what page this is
        $this->table_name = "import";
    	$this->primary_key = "import_id";
    	$this->fields = array(
    	 "import_id" => array("type" => "integer", "min_length" => 1,'searchable'=>TRUE, 'hidden'=>TRUE),
         "database" => array("type" => "list", "min_length" => 1,"options"=>array(),"default"=>SITE_DB_NAME,'searchable'=>TRUE,"js"=>"var page = getCurrentPage(this);get_queue(page.find('.current_page').val()+ '&action=Edit_Field&get_key=table_name&'+ page.find('.default_form').serialize() +'&jquery=TRUE', function(msg) {page.find('.default_form .table_name_import_field').replaceWith(msg);});"),
         "type"=>array("type" => "list", "min_length" => 1,"options"=>array('.xml','.txt','.csv'),"description"=>"All Data Exported from the CMS is in XML format. If you wish to import a custom Excel file, select 'file->save as' and specify 'Text(Tab Delimited)(*.txt)' format.","js"=>"var page = getCurrentPage(this);if(jQuery(this).val() !='.xml'){page.find('.table_name_import_field,.drop_columns_import_field').parent().removeClass('hidden');}else{page.find('.table_name_import_field,.drop_columns_import_field').parent().addClass('hidden');}"),
         "table_name" => array("type" => "list", "min_length" => 1,"options"=>array('import'),"default"=>"import",'hidden'=>TRUE),
         "truncate" => array("type" => "checkbox", "min_length" => 0,"label"=>"Delete Existing Records","description"=>"Select if restoring a table from a backup."),
         "drop_columns" => array("type" => "checkbox", "min_length" => 0,"label"=>"Delete Existing Table Layout",'hidden'=>TRUE),
         "file"=> array("type"=>"file", "min_length" => 0,'searchable'=>TRUE)
    	 );
         $this->db = new MySQLDatabase;
         $this->mysql = new MySQLClass($this->db->get_db(),$this->fields,$this->table_name,$this->primary_key);
        // $this->action_check();
        $databases = $this->mysql->get_sql('SHOW DATABASES');
        foreach($databases as $database)
            $this->fields['database']['options'][] = $database['Database'];
    }
    public function init_variables(){
        //put any custom variables you want here
        parent::init_variables();
        //if you want to force any variables put it after the parent function
        if($this->variables['database'] != ''){
            unset($this->fields['table_name']['options']);
            $this->fields['table_name']['options'] = array();
            $this->db->get_db()->select_db($this->variables['database']);
            $databases = $this->mysql->get_sql('SHOW TABLES');
            foreach($databases as $database)
                $this->fields['table_name']['options'][] = $database["Tables_in_{$this->variables['database']}"];
            $this->db->get_db()->select_db(SITE_DB_NAME);
        }
    }
     public function action_check($action = NULL){

       $this->init_variables();
        switch (isset($action) ? $action : $this->variables['action']) {
          case "Add":   //Load file into memory
          $fail=$pass=0;

                      if($this->fields['file']['type'] == 'file' && isset($_FILES['file'])){
                         //Cleans up multiple file uploads in an array
                        $names = array( 'name' => 1, 'type' => 1, 'tmp_name' => 1, 'error' => 1, 'size' => 1);
                        foreach ($_FILES['file'] as $index => $part) {
                            // only deal with valid keys and multiple files
                            $index = (string) $index;
                            if (isset($names[$index]) && is_array($part)) {
                                foreach ($part as $position => $value) {
                                    $_FILES['file'][$position][$index] = $value;
                                }
                                // remove old key reference
                                unset($_FILES['file'][$index]);
                            }
                        }
                        $this->db->get_db()->select_db($this->variables['database']);
                        
                        //Parse the XML file
                        if($this->variables['type'] =='.xml'){
                              $this->xml_import($_FILES);
                        }
                        else{

                        
                        $fields = array( "import_id" => array("type" => "integer", "min_length" => 1,'searchable'=>TRUE, 'hidden'=>TRUE));
                        if($this->variables['type'] =='.csv')
                            $delimiter = ',';
                        else
                            $delimiter ='\t';
                        IF($this->variables['truncate'])
                            $this->mysql->set_sql("TRUNCATE {$this->variables['table_name']}"); //clear out any data in import
                        IF($this->variables['drop_columns']){
                            $columns = $this->mysql->get_sql("SELECT column_name FROM information_schema.columns WHERE table_name ='{$this->variables['table_name']}' ");
                            foreach($columns as $column) //Drop all columns currently in the table
                                $this->mysql->set_sql("ALTER TABLE {$this->variables['table_name']} DROP {$column['column_name']}");
                            echo $this->mysql->last_error;
                        }
                        //import file into table
                        foreach ($_FILES['file'] as $position => $file) {
                            $file_contents = file($file['tmp_name'],FILE_IGNORE_NEW_LINES);
                            $headings = explode($delimiter,$file_contents[0]);
                            $file_types = array();
                            $file_length = array();
                            $file_type_change = array();
                            //Loop through the whole file and figure out the file types for import

                            for($i=1; $i<sizeof($file_contents); $i++) {
                              $row = explode($delimiter,$file_contents[$i]);
                              for($j=0;$j<sizeof($headings);$j++){
                                  if(strlen($row[$j] > 45))
                                      $file_length[$j] = 255;
                                  $get_type = $this->mysql->identify_type($row[$j]);
                                  if($row[$j] != '' && !isset($file_types[$j]))
                                   { $file_types[$j] = $get_type; }
                                  else if($row[$j] != '' && $file_types[$j] != $get_type ){
                                    if($get_type == 'timestamp' //Always default to a timestamp
                                      OR(($file_types[$j] == 'integer' OR ($file_types[$j] == 'big_integer' && $get_type != 'integer') OR $file_types[$j] == 'checkbox') )
                                      OR($file_types[$j] == 'text' && $get_type == 'textarea')){//default to a textarea
                                      if(isset($file_type_change[$j]) && $file_type_change > 1)//Don't change the type unless we have 2 or more instances of type changes
                                        {echo $headings[$j]." ".$file_types[$j]." ".$get_type." ".$row[$j]."<br />"; $file_types[$j] = $get_type;}
                                      else
                                        $file_type_change[$j] =(isset($file_type_change[$j])?$file_type_change[$j]+1:1 );
                                    }

                                  }
                              }
                            }
                            $bad_char = array('"',',','?','-');
                            for($j=0;$j<sizeof($headings);$j++){
                                  $fields[str_replace(" ","_",$this->mysql->escape_string(str_replace($bad_char,"",(strlen($headings[$j])> 35?substr($headings[$j],0,35):$headings[$j]))))] = array("type"=>(isset($file_types[$j])?$file_types[$j]:'text'),"min_length"=>($j==0?1:0));
                                  if(isset($file_length[$j]))
                                    $fields[str_replace(" ","_",$this->mysql->escape_string(str_replace($bad_char,"",(strlen($headings[$j])> 35?substr($headings[$j],0,35):$headings[$j]))))]["max_length"]=255;
                              }

                            $mysql = new MySQLClass($this->db->get_db(),$fields,$this->variables['table_name'],'import_id');
                            for($i=1; $i<sizeof($file_contents); $i++) {
                              $row = explode($delimiter,$file_contents[$i]);
                              $mysql->clear();
                              for($j=0;$j<sizeof($headings);$j++){
                                IF($row[$j] != 'NULL')
                                    $mysql->{str_replace(" ","_",$this->mysql->escape_string(str_replace($bad_char,"",(strlen($headings[$j])> 35?substr($headings[$j],0,35):$headings[$j]))))} = $row[$j];
                              }
                              $mysql->last_error = '';
                              $mysql->save();
                              if($mysql->last_error != '')
                                $fail +=1;
                              else
                                $pass +=1;
                           }
                              
                           }
                           echo "<div class='error'> Pass: $pass  Fail: $fail: {$mysql->last_error}</div>";
                        }
                     }




           break;
           default:
                parent::action_check();
          break;


        }
     }
     public function xml_import($file){
      foreach ($_FILES['file'] as $position => $file) {
              $dom = DOMDocument::load($file['tmp_name'] );
              $tables = array();
              $worksheets = $dom->getElementsByTagName('Worksheet');
              foreach($worksheets as $worksheet){
                    $table_name = preg_replace('/.*_id_/i', '', $worksheet->getAttribute('ss:Name'));//Replace the primary key in front of the table name
                    $rows = $worksheet->getElementsByTagName('Table')->item(0)->getElementsByTagName('Row');
                    $heading_row = $rows->item(0)->getElementsByTagName('Cell');
                    for( $i = 1; $i < $rows->length; $i++ ) {
                      $cells = $rows->item($i)->getElementsByTagName('Cell');
                      for( $c = 0; $c < $cells->length; $c++ ) {
                        $celldata = $cells->item($c)->getElementsByTagName('Data');
                          if( $celldata->length ) {
                            if( $celldata->item(0)->getAttribute('ss:Type')== 'String' ) {
                              $value = $celldata->item(0)->C14N();
                              $value = preg_replace('/<([s\/:]+)?Data([^>]+)?>/i', '', $value);
                            } else {
                              $value = $cells->item($c)->nodeValue;
                            }
                            $label = $heading_row->item($c)->nodeValue;
                            $tables[$table_name][$i-1][$label] = html_entity_decode($value);
                          }
                        }
                      }
                }
                $error_log ='';
                $ids =$searches= array();
                $fail=$pass=0;
                foreach($tables as $table_name =>$table){
                  IF($this->variables['truncate'])
                            $this->mysql->set_sql("TRUNCATE {$table_name}"); //clear out any data in import

                  $headings = array_keys($table[0]);
                  if(strpos($headings[0],'_id'))
                          $table_primary_key = $headings[0];
                  else
                          $table_primary_key =  $table_name."_id";

                            for($i=0; $i<sizeof($table); $i++) {
                              $row = $table[$i];
                              $file_types = array();
                              $file_length = array();
                              $file_type_change = array();
                              //Loop through the whole file and figure out the file types for import
                              
                              for($j=0;$j<sizeof($row);$j++){
                                  if(strlen($row[$j] > 45))
                                      $file_length[$j] = 255;
                                  $get_type = $this->mysql->identify_type($row[$j]);
                                  if($row[$j] != '' && !isset($file_types[$j]))
                                   { $file_types[$j] = $get_type; }
                                  else if($row[$j] != '' && $file_types[$j] != $get_type ){
                                    if($get_type == 'timestamp' //Always default to a timestamp
                                      OR(($file_types[$j] == 'integer' OR ($file_types[$j] == 'big_integer' && $get_type != 'integer') OR $file_types[$j] == 'checkbox') )
                                      OR($file_types[$j] == 'text' && $get_type == 'textarea')){//default to a textarea
                                      if(isset($file_type_change[$j]) && $file_type_change > 1)//Don't change the type unless we have 2 or more instances of type changes
                                        {echo $headings[$j]." ".$file_types[$j]." ".$get_type." ".$row[$j]."<br />"; $file_types[$j] = $get_type;}
                                      else
                                        $file_type_change[$j] =(isset($file_type_change[$j])?$file_type_change[$j]+1:1 );
                                    }

                                  }
                              }
                            }
                            $bad_char = array('"',',','?','-');
                            for($j=0;$j<sizeof($headings);$j++){
                                  $fields[str_replace(" ","_",$headings[$j])] = array("type"=>(isset($file_types[$j])?$file_types[$j]:'text'),"min_length"=>($j==0?1:0));
                                  if(isset($file_length[$j]))
                                    $fields[str_replace(" ","_",$headings[$j])]["max_length"]=255;
                              }
                              if(!isset($fields[$table_primary_key]))
                                    $fields[$table_primary_key] = array("type"=>"int","min_length"=>1);

                            $mysql = new MySQLClass($this->db->get_db(),$fields,$table_name,$table_primary_key);
                            for($i=0; $i<sizeof($table); $i++) {
                              $row = $table[$i];
                              $primary_id = NULL;
                              $mysql->clear();
                              $key_match = false;
                              foreach($headings as $key){
                                //Match up old keys to new keys
                                if(isset($ids[$key])){
                                            foreach(array_keys($ids[$key]) as $id){
                                                  if($row[$key] == $id){
                                                        $row[$key] = $ids[$key][$id];
                                                        $key_match = true;  //In case there is a table with a shared key
                                                  }
                                            }
                                }
                                IF($row[$key] != 'NULL' && ($key!= $table_primary_key OR $this->variables['truncate'] OR $key_match))
                                    $mysql->{str_replace(" ","_",$key)} = $row[$key];
                                else if($key == $table_primary_key)
                                   $primary_id = $row[$key];


                              }
                              $mysql->last_error = '';
                              $mysql->save();
                              $error_log .= $mysql->$table_primary_key." ";
                              if($mysql->last_error != '')
                                    $error_log .= $mysql->last_error.$table_primary_key."\r\n";
                              //$mysql->load();
                              if($primary_id>0)

                              if($mysql->last_error != '')
                                $fail +=1;
                              else
                                $pass +=1;
                           }

                           }
                           //Secondary pass for linking id's after import is done.
                           foreach($tables as $table_name =>$table){
                          $headings = array_keys($table[0]);
                          if(strpos($headings[0],'_id'))
                                  $table_primary_key = $headings[0];
                          else
                                  $table_primary_key =  $table_name."_id";

                          $table_class = null ;
                          $table_search = $this->mysql->get_sql("SELECT * FROM page_table WHERE table_name LIKE '{$table_name}' AND database LIKE '{$this->variables['database']}'");
                          IF(isset($table_search[0]['page_id']) && $table_search[0]['page_id'] >0){
                               require_once SITEPATH.'/components/site/page/page_table.php';
                               $page_table_class = new PageTableClass();
                               $table_class = $page_table_class->load($table_search[0]['page_id']);
                          }
                          else{
                            $this->read_dir(SITEPATH."/components");
                            foreach($this->variables['pages'] as $page_path){
                                      $page = substr($page_path,(strrpos($page_path,'/')?strrpos($page_path,'/')+1:0),-4);
                                      require_once $page_path;
                                      eval("\$class_page = new ".str_replace("_","",$page)."Class();");
                                      if($class_page->table_name == $table_name){
                                          $db_name = $class_page->mysql->get_sql('SELECT database() AS db_name');
                                          if($db_name[0]['db_name'] == $this->variables['database']){
                                                   $table_class = $class_page;
                                                   break;
                                          }
                                      }

                            }
                          } //Check if the class was found
                          if($table_class != null){
                              for($i=0; $i<sizeof($table); $i++) {
                              $row = $table[$i];
                              if(isset($ids[$table_class->primary_key][$row[$table_class->primary_key]])){
                                      $table_class->mysql->{$table_class->primary_key} = $ids[$table_class->primary_key][$row[$table_class->primary_key]];
                                      $table_class->mysql->load();
                                      foreach($table_class->fields as$name => $field){
                                           if(isset($field['lookup_table']) && $row[$name]!= ''){
                                             //Lookup the ID number
                                             $lookup_id = (isset($field['lookup_id']) && $field['lookup_id']!= ''?$field['lookup_id']:$name);
                                                $result = $this->mysql->get_sql("SELECT * FROM {$field['lookup_table']} WHERE CONCAT_WS(' ',{$field['lookup_field']})='{$row[$name]}'");
                                                if(isset($result[0][$lookup_id])){
                                                      $table_class->mysql->{$name} = $result[0][$lookup_id];
                                                      $table_class->mysql->save();
                                                }
                                           }
                                      }
                              
                              }
                              }
                          }




                           }

                           echo "<div class='error'> Pass: $pass  Fail: $fail: {$error_log}".$found_lookup_table;
                           //print_r($ids);
                           //print_r($tables);
                           //print_r($headings);
                           echo"</div>";
                           }
     }
     public function read_dir($path){
        if ($handle = opendir($path)) {
            while (false !== ($entry = readdir($handle))) {
                if (is_file($path."/".$entry)&& $entry != "." && $entry != ".." )
                    $this->variables['pages'][]= $path."/".$entry;
                if (is_dir($path."/".$entry)&& $entry != "." && $entry != ".." )
                    $this->read_dir($path."/".$entry);

            }
            closedir($handle);
        }
    }
}

 ?>
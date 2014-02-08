<?php
/*
 * Class for Handling Components
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
class ComponentClass extends TableClass{
    var $children;
    var $files;
    public function __construct() {
      $this->variables = Array();
      $this->default_results = 25;
      $this->printable = FALSE;

      $this->page_id = str_replace(SITEPATH."/components/",'',__FILE__); //Let table class know what page this is
      $this->page_title = str_replace("_"," ",substr($this->page_id,(strpos($this->page_id,'/')?strrpos($this->page_id,'/')+1:0),-4)); //Set the title based on the page name


     $this->db = new MySQLDatabase(new mysqli(SITE_DB_HOST, SITE_DB_USER, SITE_DB_PASS, SITE_DB_NAME));
      if(isset($this->db)){


        $this->table_name = "component";
    	$this->primary_key = "component_id";
    	$this->fields = array(
           "component_id" => array("type" => "integer","min_length" => 1, "hidden" => TRUE),
           "component" => array("type"=>"file","min_length" => 0,"max_length" => 255, "searchable" => TRUE),
           "section" => array("type"=>"list","min_length" => 1,"max_length" => 255, "searchable" => TRUE),
           "description" => array("type"=>"textarea","min_length" => 0,"max_length" => 1000, "searchable" => TRUE),
           "code" => array("type"=>"textarea","min_length" => 0,"hidden"=>TRUE),
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
    public function action_check($action = NULL){
     $this->init_variables();
     //check if this is a properites page, if so update the appropriate page
        switch (isset($action) ? $action : $this->variables['action']) {
         case "Edit":
            $this->mysql->{$this->primary_key} = $this->variables[$this->primary_key];
            $this->mysql->load();
            $this->mysql->code = file_get_contents(SITEPATH."/components/".$this->mysql->section."/".str_replace(" ","_",strtolower($this->mysql->component)).".php");
            $this->mysql->save();
            unset($this->fields['code']['hidden']);
            unset($this->fields['component']);
            //$this->fields['components']['hidden'] = TRUE;
            $this->fields['section']['hidden'] = TRUE;
            $this->fields['description']['hidden'] = TRUE;
            case "Add_New":  //Get a list of all sections for add/edit
            $sections = $this->mysql->get_sql("SELECT GROUP_CONCAT(DISTINCT section) as section FROM page WHERE hidden != 1");
            $this->fields['section']['options'] = (isset($sections[0])?explode(',',$sections[0]['section']):NULL);
            $this->build_sections(SITEPATH."/components");
            parent::action_check($action);
            echo "<script>setTimeout(function(){jQuery('.component_code:visible').each( function(){CodeMirror.fromTextArea(jQuery(this).get(0),{mode:'php',lineNumbers : true,matchBrackets : true})});jQuery('.CodeMirror').each(function(i, el){el.CodeMirror.refresh();})},100);</script>\n";
            break;
          case "Update":
            parent::action_check($action);
            if($this->mysql->last_error == '')
                file_put_contents(SITEPATH."/components/".$this->mysql->section."/".str_replace(" ","_",strtolower($this->mysql->components)).".php",html_entity_decode($this->mysql->code,ENT_QUOTES,'UTF-8'));
          break;
         case "Delete":
         if($this->user->mysql->delete){
           $this->mysql->{$this->primary_key} = $this->variables[$this->primary_key];
           $this->mysql->load();
             unlink(SITEPATH."/components/".$this->mysql->section."/".str_replace(" ","_",strtolower($this->mysql->components)).".php");
             $this->variables['error'] = $this->variables[$this->primary_key] ." Deleted";
         }
           break;
         case "Download_File":
             if($this->user->mysql->download){
               $this->mysql->clear();
               $this->mysql->{$this->primary_key}= $this->variables[$this->primary_key];
               $this->mysql->load();
               //stream file to user
               $this->output_file(SITEPATH."/components/".$this->mysql->section."/".str_replace(" ","_",strtolower($this->mysql->components)).".php");
               exit();

             }
             break;
        default:
        $this->build_pages();
                parent::action_check($action);
         break;
        }
   }
   public function build_sections($path){
        if ($handle = opendir($path)) {
            while (false !== ($entry = readdir($handle))) {
                if (is_dir($path."/".$entry)&& $entry != "." && $entry != ".." ){
                    $this->fields['section']['options'][]= str_replace(SITEPATH."/components/","",$path."/".$entry);
                    $this->build_sections($path."/".$entry);
                }
            }
            closedir($handle);
        }
    }
    public function build_pages(){
      $sections = $this->mysql->get_sql("SELECT GROUP_CONCAT(DISTINCT section) as section FROM page WHERE hidden != 1");
      $this->fields['section']['options'] = (isset($sections[0])?explode(',',$sections[0]['section']):NULL);
      $this->build_sections(SITEPATH."/components");
      $this->mysql->set_sql('TRUNCATE component');
    //Get all the available page types
    foreach($this->fields['section']['options'] as $section){
      if(is_dir(SITEPATH."/components/".$section)){
           if ($handle = opendir(SITEPATH."/components/".$section)) {
                    while (false !== ($entry = readdir($handle))) {
                    if (!is_dir(SITEPATH."/components/".$section.$entry)&& $entry != "." && $entry != ".."){
                      $file = ucwords(str_replace("_"," ",str_replace('.php','',$entry)));
                        $this->mysql->clear();
                        $this->mysql->component = $file;
                        $this->mysql->section = $section;
                        $this->mysql->updated_by=$this->mysql->created_by = 'Server';
                        $this->mysql->updated=$this->mysql->created = date('Y-m-d');
                        $this->mysql->save();
                    }
                   }
                   closedir($handle);
          }
      }
    }
    }
    function upload_file($key){
        //Cleans up multiple file uploads in an array
        $names = array( 'name' => 1, 'type' => 1, 'tmp_name' => 1, 'error' => 1, 'size' => 1);
        foreach ($_FILES[$key] as $index => $part) {
            // only deal with valid keys and multiple files
            $index = (string) $index;
            if (isset($names[$index]) && is_array($part)) {
                foreach ($part as $position => $value) {
                    $_FILES[$key][$position][$index] = $value;
                }
                // remove old key reference
                unset($_FILES[$key][$index]);
            }
        }
        foreach ($_FILES[$key] as $position => $file) {
            echo  $file['tmp_name'];
            if($file['tmp_name'] != ""){
              if(strpos($file['name'], ".php")!== false){
                if(!is_dir(SITEPATH."/components/{$this->mysql->section}/")){
                    if (!mkdir(SITEPATH."/components/{$this->mysql->section}/", 0777)){
                        $this->variables['error'] = "Error Creating Directory";
                        return false;
                    }
                }

                if(move_uploaded_file($file['tmp_name'], SITEPATH."/components/{$this->mysql->section}/".basename( strtolower($file['name'])))){
                    $this->variables[$key] =  ucwords(str_replace("_"," ",str_replace('.php','',basename($file['name']))));
                    return true;
                }
                else
                        $this->variables['error'] = "There was an error uploading ".  basename( $file['name']).", please try again!";

              }
              else
                        $this->variables['error'] = "Components must be PHP files!";
            }

            return false;
         }
    }
}
?>
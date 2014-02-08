<?php
/*
 * Class for API Information
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
class ApiClass extends TableClass{
    var $children;
    var $files;
    public function __construct() {
      $this->variables = Array();
      $this->default_results = 25;
      $this->printable = FALSE;
      $this->table_permissions['add'] = FALSE;
      $this->table_permissions['delete'] = FALSE;
      $this->table_permissions['edit'] = FALSE;

      $this->page_id = str_replace(SITEPATH."/components/",'',__FILE__); //Let table class know what page this is
      $this->page_title = str_replace("_"," ",substr($this->page_id,(strpos($this->page_id,'/')?strrpos($this->page_id,'/')+1:0),-4)); //Set the title based on the page name


     $this->db = new MySQLDatabase(new mysqli(SITE_DB_HOST, SITE_DB_USER, SITE_DB_PASS, SITE_DB_NAME));
      if(isset($this->db)){
        $this->children = array(
        "Function"=> array("action"=>"","get_page"=>"site/api_function.php"),
        );

        $this->table_name = "api";
    	$this->primary_key = "api_id";
    	$this->fields = array(
           "api_id" => array("type" => "integer","min_length" => 1, "hidden" => TRUE),
           "file" => array("type"=>"text","min_length" => 1,"max_length" => 255, "searchable" => TRUE,"description"=>"The physical location of the function"),
           "class" => array("type"=>"text","min_length" => 1,"max_length" => 255, "searchable" => TRUE,"description"=>"The class name contained in the file"),
           "example" => array("type"=>"textarea","min_length" => 1,"max_length" => 255,"description"=>"How you would call the file + class."),
           "description" => array("type"=>"textarea","min_length" => 0,"max_length" => 4000,"rows"=>20),
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
         case "":  //Build the API if this is the first page load
            $this->build_api();
            parent::action_check($action);
            break;
        case "Get_Details":
              ob_clean();
              echo $this->detailed_table($results);
         break;
        default:
                parent::action_check($action);
         break;
        }
   } /*
   public function row_controls(&$result){
            $answer = '';
               $answer .="<div row='".str_replace(' ','__',$result->{$this->primary_key})."' style='cursor: pointer;' class='plus' alt='Details' title='Details'></div>";
             return $answer;
    }

    public function detailed_table(&$results){
        $odd = false;
        $answer = "";

        //Display the contents of each field in array
        if(!empty($results)){
          $answer .= "\t<tr class='contain".str_replace(' ','__',$this->variables[{$this->primary_key}])."' ". ( $odd ? ' class="odd"' : '')."><td colspan='100%'>\n";
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
    }  */
   public function read_dir($path){
        if ($handle = opendir($path)) {
            while (false !== ($entry = readdir($handle))) {
                if (!is_dir($path."/".$entry)&& $entry != "." && $entry != "..")
                    $this->files[] = $path."/".$entry;
                else if (is_dir($path."/".$entry)&& $entry != "." && $entry != "..")
                    $this->read_dir($path."/".$entry);

            }
            closedir($handle);
        }
    }

    public function build_api(){
      $this->files = array();
      //Clear out whatever is currently in the api
      $this->mysql->set_sql('TRUNCATE api');
      $this->mysql->set_sql('TRUNCATE api_function');
      //Build a list of files in the site
      $this->read_dir(SITEPATH);
      //Go through each file and build an API
      foreach($this->files as $file){
        $tokens = @token_get_all(file_get_contents($file));
        $whitespace = 0;
        //print_r($tokens);
        //print_r(array_filter($tokens,function($t) { if($t[0] == T_FUNCTION) return $t[1]; }));
        $class = array();
        $comments = array();
        $last_function = '';
        foreach($tokens as $token) {
          switch($token[0]) {
              case T_CLASS:
                  $inClass = true;
                  break;
              case T_COMMENT:
              case T_DOC_COMMENT:
              case T_ML_COMMENT:
                  $last_comment = $token[1];
                  break;
              case T_FUNCTION:
                  if($inClass)
                    $function_found = true;
                  break;
              case T_STRING:
                  if($function_found) {
                      $function_found = false;
                      $class[$last_class]['function'][$token[1]] = array();
                      if($last_comment != '')
                          $class[$last_class]['function'][$token[1]]['comment'] = $last_comment;
                      $last_comment='';
                      $last_function = $token[1];
                  }
                  else if($extends){
                        $class[$last_class]['extends'] = $token[1];
                        $extends = false;
                  }
                  else if($inClass && $bracesCount ==0){
                      $last_class= $token[1];
                      if($last_comment != '')
                          $class[$token[1]]['comment'] = $last_comment;
                  }

                  break;
              case T_EXTENDS:
                  $extends = true;
              break;
              case T_WHITESPACE:
                if($whitespace >4){ //Used for grabbing comments in front of class/function Resets when there are too many line breaks.
                    $last_comment='';
                    $whitespace=0;
                }
                else
                    $whitespace++;
              break;
              // Anonymous functions
              case ')':
                  $function_arguments=false;
              break;
              case '(':
                if($bracesCount == 1)
                    $function_arguments=true;
              case ';':

                  $function_found = false;
                  break;
              case T_VARIABLE:
                  if($function_arguments && $last_function != ''){
                    $class[$last_class]['function'][$last_function]['argument'][$token[1]] = 'required';
                    $last_variable = $token[1];
                  }
              break;
              case "=":
                  if($function_arguments && isset($class[$last_class]['function'][$last_function]['argument'][$last_variable]))
                      $class[$last_class]['function'][$last_function]['argument'][$last_variable] = 'optional';
              break;
              // Exclude Classes
              case T_CURLY_OPEN:
              case T_DOLLAR_OPEN_CURLY_BRACES:
              case '{':
                  if($inClass)
                      $bracesCount++;
                  break;

              case '}':
                  if($inClass) {
                      $bracesCount--;
                      if($bracesCount ==0) {
                          $inClass = false;
                          $last_class = '';
                      }
                  }
                  break;

              }
      }
      foreach($class as $entry => $row){

        $this->mysql->clear();
        $this->mysql->class = $entry;
        $this->mysql->description = $row['comment'];
        $this->mysql->file = str_replace(SITEPATH,'',$file);
        $this->mysql->example = 'require_once SITEPATH."'.str_replace(SITEPATH,'',$file).'"; $'.str_replace('Class','',$entry).' = new '.$entry.';';
        $this->mysql->updated_by=$this->mysql->created_by = 'Server';
        $this->mysql->updated=$this->mysql->created = date('Y-m-d');
        $this->mysql->save();

        require_once SITEPATH."/components/site/api_function.php";
        $api_function = new ApiFunctionClass();
        if(isset($row['function'])){
          foreach($row['function'] as $function => $properties){
            if($function == '__destruct') //Ignore destruct functions
                continue;
            $api_function->mysql->clear();
            $api_function->mysql->{$this->primary_key} = $this->mysql->{$this->primary_key};
            $api_function->mysql->function = $function;
            $api_function->mysql->description = $properties['comment'];
            $function_argument = '';
            if(isset($properties['argument'])){
              foreach($properties['argument'] as $arg => $status){
                 $function_argument.= ($status!='required'?"[".$arg."]":$arg).",";
              }
            }
            //If this is the contructor, pass the arguments to the class
            if($function == '__construct'){
                $this->mysql->example = 'require_once SITEPATH."'.str_replace(SITEPATH,'',$file).'"; $'.str_replace('Class','',$entry).' = new '.$entry."(".rtrim($function_argument,',').")".';';
                $this->mysql->save();
                //echo $this->mysql->last_error;
                continue;
            }
            //$api_function->mysql->update_table();
            $api_function->mysql->example = "$".$entry."->".$function."(".rtrim($function_argument,',').")";
            $api_function->mysql->updated_by =$api_function->mysql->created_by= 'Server';
            $api_function->mysql->updated=$api_function->mysql->created = date('Y-m-d H:i:s');
            $api_function->mysql->save();
            echo $api_function->mysql->last_error;
          }
        }
      }
    }
    }
}
?>
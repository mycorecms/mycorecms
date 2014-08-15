<?php
/*  Class for writing/executing queries
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

class DatabaseClass extends QueryClass{ //Rename class name to match the filename  ex: filename some_page  classname = SomePageClass, must match for index.php to create class

public function __construct() {
      ### Table/db Initialization Variables  ###
      $this->db = new MySQLDatabase;
      $this->mysql = new MySQLClass($this->db->get_db(),$this->fields,$this->table_name,$this->primary_key);
      $this->page_id = str_replace(SITEPATH."/components/",'',__FILE__); //Let table class know what page this is
      parent::__construct();
      $this->row_id = ""; //must be set before init_variables
      $this->default_sort = ""; //must be set before init_variables
      $this->init_variables();

      $this->default_results = 25; //Default number of results
      $this->printable = FALSE; //Set to TRUE to have the print ICON show up, it will pop up a window displaying the print_report function, defaults to a basic list of fields


	}
  public function init_variables(){
     $this->sql_query = (isset($_REQUEST['query']) && $_REQUEST['query'] != ''?$_REQUEST['query']:"SHOW TABLES");
     $this->variables['database'] = (isset($this->variables['database'])?$this->variables['database']:SITE_DB_NAME);
     $this->db->get_db()->select_db($this->variables['database']);
     $this->row_id = "Tables_in_{$this->variables['database']}";
     $this->queries["Tables_in_{$this->variables['database']}"] = "SELECT * FROm ".(isset($this->variables[$this->row_id])?$this->variables[$this->row_id]:"");
     parent::init_variables();
     //echo "query:".$this->sql_query ;

  }
  public function action_check($action = NULL){
        switch (isset($action) ? $action : $this->variables['action']) {
          case "Execute":
          case "Get_results":
                  
                 if(!isset($_REQUEST['query']) || $this->strposa($_REQUEST['query'],array('Update','Delete','Truncate')) ===False){
                  parent::action_check($action);
                 }

                else{
                    $html[] = "";
                    $total_results =0;
                    if($this->mysql->set_sql($this->sql_query)){
                        //$results = $this->mysql->get_sql("mysql_affected_rows()");
                        $found = $this->mysql->get_sql("SELECT FOUND_ROWS()");
                        $affected = "Affected Rows:".mysqli_affected_rows($this->mysql->get_db());

                        $html[] = "Found Rows:".$found[0]['found_rows']." ".$affected;
                    }
                    else
                          $html[] =$this->mysql->last_error;
                    $html[] = $total_results;

                  echo json_encode($html);
                  exit;
                }

                  
             break;
             case "Add":   //Load file into memory
            $results = $this->mysql->get_sql($_REQUEST['query']);
            echo $this->mysql->last_error;
            if(stripos($_REQUEST['query'],'select') !==False){
                   $results = $this->mysql->get_sql($_REQUEST['query']);
                   $this->print_table('Results',$results);
                   echo $this->mysql->last_error;
                   break;
            }
            elseif(sizeof($results)>0)
                   print_r($results).strpos(strtolower($_REQUEST['query']),'select');
           break;
            default:
                parent::action_check($action);
                echo $this->mysql->last_error;
            break;
        }
   }
   function strposa($haystack, $needle) {
    if(!is_array($needle)) $needle = array($needle);
    foreach($needle as $query) {
        if(stripos($haystack, $query) !== false) return true; // stop on first true result
    }
    return false;
}
  public function show_filter_form(){

?>
      <fieldset>
      <legend>Filters</legend>
  	<form class="default_form" action="<?php echo $_SERVER['PHP_SELF']."?get_page={$this->page_id}" ?>" method="post">

        <label for='status'>Database</label>
        <select class="database Filter"  name="database">
            <?php
            $databases = $this->mysql->get_sql('SHOW DATABASES');
            foreach($databases as $database)
                echo "<option ".($this->variables['database']==$database["Database"]?"selected='selected'":"").">".$database["Database"]."</option>\n";
            ?>
            </select>
        <br/>
  	<label for="mysql_query">MySQL Query:</label>
        <textarea class="mysql_query Filter" name="query" rows="4" style='width:800px'></textarea>
        <input name="action" value="Get_results" type="submit" onclick =''>
    </form>
      </fieldset>
      <br/>

      <script>setTimeout(function(){jQuery('.mysql_query:visible').each( function(){CodeMirror.fromTextArea(jQuery(this).get(0),{mode:'text/x-mysql'}).setSize(800, 100)});jQuery('.CodeMirror').each(function(i, el){el.CodeMirror.refresh();})},100);</script>
  <?php
  }

}
?>

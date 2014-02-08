<?php
/* Class for rendering SQL Query Reports
    Copyright (C) 2007-2014 MyCoreCMS

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details. <http://www.gnu.org/licenses/>.
 */
require_once "table_class.php";

class QueryClass extends TableClass{
  var $sql_query;
  var $queries;
  var $row_id;
  var $default_sort;
  var $search_fitler;

  //Initialize Results
	public function __construct() {
      $this->variables = Array();
      $this->default_results = 25;
      if(!isset($this->mysql))
        $this->mysql = new MySQLClass;

	}
    public function init_variables(){
      $this->page_title = str_replace("_"," ",substr($this->page_id,(strpos($this->page_id,'/')?strrpos($this->page_id,'/')+1:0),-4)); //Set the title based on the page name
      //Generic function to grab all requests
      
      foreach($_REQUEST as $name=>$value){
        if($value != '')
            $this->variables[$name] = $this->mysql->escape_string($value);
      }
      if(is_array($this->queries)){
        foreach(array_keys($this->queries) as $row){
         if(isset($this->variables[$row]))
              $this->sql_query = $this->queries[$row];
        }
      }
        //Sorting variables
        $this->variables['sort'] = ( isset($_REQUEST['sort']) ? $this->mysql->escape_string($_REQUEST['sort']) : $this->default_sort);
        $this->variables['ascending'] = ( isset($_REQUEST['ascending']) ? ((bool)$_REQUEST['ascending']?'ASC':'DESC') : 'DESC');
        $this->variables['action'] = ( isset($_REQUEST['action']) ? $this->mysql->escape_string($_REQUEST['action']) : "");

    }
    public function action_check($action = NULL){
      $this->init_variables();
      if($this->variables['sort'] != '')
        $this->sql_query .=   " ORDER BY ".$this->variables['sort']." ".$this->variables['ascending'];
      //Run through any requests
      switch (isset($action) ? $action : $this->variables['action']) {
          case "Get_results":
                  /*$total_results =  $this->mysql->get_sql_count($this->sql_query);
                  $html[] = $this->calculate_limits($total_results);
                  $results = $this->mysql->get_sql($this->sql_query." ".(stripos($this->sql_query,'select') !==False?$this->limit:""));
                  $html[] = $this->results_table($results);
                  $html[] = $total_results;
                  //$html[] = $this->mysql->last_error.$this->sql_query." ".(stripos($this->sql_query,'select') !==False?$this->limit:"");
                  echo json_encode($html);
                  //echo json_encode($this->results_table($results));
                  exit;   */
                  $this->show_results();
             break;
         case "Details":
         
              ob_clean();
              echo "\t<div class='tab'>\n";
              $this->show_results();
              echo "</div>\n";
         break;
         case "Export":
              $results = $this->mysql->get_sql($this->sql_query);
              $this->export_sql($results,$this->page_title);
         break;
         case "Print":
                $this->print_report();
                exit();
           break;
         default:
              $this->show_filter_form();
              $this->show_results();
          break;
      }
    }

   public function show_filter_form(){

        echo "<fieldset>\n\t<legend>Filters</legend>\n";
    	echo "\t\t<form class='default_form' action='". $_SERVER["PHP_SELF"]."' method='post'>\n";
        echo (isset($this->search_filter)?$this->search_filter:"");
        //echo "\t\t\t<label for='start_date'>Start Date:</label>\n";
    	//echo "\t\t\t<input class='Filter datepickerclass' type='text' name='start_date' value ='".$variables['start_date']."' >\n";
        //echo "\t\t\t<label for='end_date'>Start Date:</label>\n";
    	//echo "\t\t\t<input class='Filter datepickerclass' type='text' name='end_date' value ='".$variables['end_date']."' >\n"';
    	echo "\t\t</form>\n</fieldset><br/>\n";


    }

    public function show_results(){
       echo "\t<div class='result'>\n";
        echo "\t<input class='sort' type='hidden' ascending='".$this->variables['ascending']."' value='".$this->variables['sort']."'/>\n";
        echo "\t<input class='current_page' name='".ucwords($this->page_title)."' type='hidden' value='".$_SERVER['PHP_SELF']."?get_page={$this->page_id}".( isset($this->parent_id) && isset($this->parent_key) ? "&amp;{$this->parent_key}={$this->parent_id}" : '')."'/>\n";
        $total_results = $this->mysql->get_sql_count($this->sql_query);

        if(is_array($this->queries)){
        foreach(array_keys($this->queries) as $row){
         if(isset($this->variables[$row]))
              echo "\t<input class='Filter' type='hidden' name='{$row}' value='".$this->variables[$row]."'/>\n";
        }
      }

         echo $this->mysql->last_error;
        echo "<fieldset style='border:0px'>\n";
          //Display error info
        if(isset($this->variables['error'])){
            $error = explode(":",$this->variables['error']);
            echo "<div class='error' style='text-align:center;text-transform: uppercase;'>".$this->variables['error']."</div>";
        }
        echo "<div class='option_container'>\n";
        echo "\t<div class='table_options' style='float:left;padding:0px 10px;'>\n";
          echo "\t\t<a href='".$_SERVER['PHP_SELF']."?get_page={$this->page_id}' class='clear'>Reset Search</a>\n";
          if($this->user->mysql->export)
            echo "\t\t<a href='".$_SERVER['PHP_SELF']."?get_page={$this->page_id}' class='export'>Export</a>\n";
          if(isset($this->pdf_report) &&$this->pdf_report )
            echo"\t\t<a href='".$_SERVER['PHP_SELF']."?get_page={$this->page_id}' class='export_pdf'><img src='/view/page/images/pdf.png' width='20' height='20' alt='PDF' title='PDF'/></a>\n";
          echo "\t</div>\n";
          echo "\t<div class='total_results'>$total_results Records</div>\n";
          echo "\t<div class='page_list'>\n";
            echo $this->calculate_limits($total_results);
          echo "\t</div>\n";
        echo "</div>\n";

            echo "<table class='content_table'>\n";
            echo "<thead>\n";
        	echo "\t<tr>\n";
            //$headers = $this->mysql->get_sql(str_replace(substr($this->sql_query,strrpos($this->sql_query,'WHERE'),strlen($this->sql_query)),'WHERE 1 LIMIT 1',$this->sql_query));
            $results =  $this->mysql->get_sql($this->sql_query." ".(stripos($this->sql_query,'select') !==False?$this->limit:""));
            if(isset($results[0])){
              foreach ( array_keys($results[0]) as $key )	{
                   echo "\t\t<th value='{$key}' class='header'>".str_replace("_"," ",$key)."</th>\n";
              }
            }
        	echo "\t</tr>\n";

            echo "</thead>\n";
            echo "<tbody>\n";
            if($total_results >0){
               //$results = $this->mysql->get_sql($this->sql_query." ".$this->limit);
               echo $this->results_table($results);
            }
            echo "</tbody>\n";
        	echo "</table>\n";

        echo "</fieldset>\n";
        echo "</div>\n";
    }
    public function results_table(&$results){
        $odd = false;
        $answer = "";
        //Display totals query if there is one.
        if(isset($this->total_query) && $this->total_query!= ''){
            $counts = $this->mysql->get_sql($this->total_query);
            echo $this->mysql->last_error;
            if(!empty($counts) ){
                foreach ( $counts as $count )	{
                $answer .= "\t<tr style='font-weight:bold'>\n";
                  foreach ( array_keys($counts[0]) as $key ) {
                        //Don't show ids
                        if(strpos('%_id',$key) === false)
                          $answer .= "\t\t<td>{$count[$key]}</td>\n";
                  }
               }
               $answer .= "\t</tr>\n";
            }
        }
        //Display the contents of each field in array
        if(!empty($results)){
          foreach ( $results as $result )	{
            $answer .= "\t<tr class='".( $odd ? "odd" : "")."'>\n";
             
             $i=0;
              foreach ( array_keys($results[0]) as $key ) {
                        $answer .= "\t\t<td>";
                        if($i==0)
                             $answer .= $this->row_controls($result);
                        $answer .=stripslashes (htmlentities($result[$key],ENT_QUOTES,'UTF-8'))."</td>\n";
               $i++;
              }
             $odd = !$odd;
           }
           $answer .= "\t</tr>\n";

         }
         return $answer;
    }
    public function row_controls(&$result){
            $answer = '';
              if(isset($this->printable) && $this->printable && $result[$this->row_id])
                  $answer .="<a style='cursor: pointer;' onclick='open_window(\"".$_SERVER['PHP_SELF']."?action=Print&amp;get_page={$this->page_id}&amp;row_id={$result[$this->row_id]}&amp;jquery=true&amp;rand=".rand()."\",600,700)' target='_blank'><img src='/view/page/images/printer.png' width='15' height='15' alt='Print' title='Print'/></a>\n";
              if($this->user->mysql->delete && $this->deleteable && $result[$this->row_id])
                  $answer .="<a class='delete_row' href='".$_SERVER['PHP_SELF']."?action=Delete&amp;get_page={$this->page_id}&amp;row_id={$result[$this->row_id]}'><img src='/view/page/images/cross.png' width='15' height='15' alt='Delete' title='Delete'/></a>";
             if(isset($this->queries[$this->row_id]) && isset($result[$this->row_id]))
               $answer .="<a style='cursor: pointer;' class='plus' alt='Details' title='Details' href='".$_SERVER['PHP_SELF']."?get_page={$this->page_id}&amp;action=Details&amp;{$this->row_id}={$result[$this->row_id]}'></a>";
             return $answer;
    }
    public function detailed_table(&$results){
        $odd = false;
        $answer = "";

        //Display the contents of each field in array
        if(!empty($results)){
          $answer .= "\t<tr class='contain".str_replace(' ','__',$this->variables[$this->row_id])."' ". ( $odd ? ' class="odd"' : '')."><td colspan='100%'>\n";
          /*$answer .= "<table class='content_table'>\n";
            $answer .="<thead>\n\t<tr>\n";
                foreach ( array_keys($results[0]) as $key )	$answer .="\t\t<th>".str_replace("_"," ",$key)."</th>\n";
        	$answer .="\t</tr>\n</thead>\n<tbody>\n";
          foreach ( $results as $result )	{
              $answer .= "</tr>\n";
              foreach ( array_keys($results[0]) as $key ) {
                      $answer .= "\t\t<td>".stripslashes (htmlentities($result[$key],ENT_QUOTES,'UTF-8'))."</td>\n";
              }
              $answer .= "\t</tr>\n";
              $odd = !$odd;
           }
         $answer .= "</tbody>\n</table>\n";  */
         $answer .= "\t</td></tr>\n";
        }
         return $answer;
    }
     public function print_table($title,&$results,&$totals = NULL){
      if(sizeof($results) > 0){
        $columns = 0;
        foreach ( array_keys($results[0]) as $key )
            $columns++;
      echo "<div style='width:650px;page-break-after:always;text-align:left;'>\n";
      echo "<table cellspacing='0' width='650' style='font-size:11px;'>";
      echo "\t<thead>\n";
      echo "<tr>\n<th colspan='{$columns}' style='padding-top:4px;border-bottom:2px solid black;border-top:2px solid black;font-size:20px;font-weight:bold;text-align:center;vertical-align:bottom;'><img style='float:left;display:inline;'src='/view/page/images/logo.png' width='120'  />{$title}</th></tr>\n";
    	echo "\t<tr>\n";
        foreach ( array_keys($results[0]) as $key )	echo "\t\t<th style='border-bottom:1px solid black;text-align:center;font-weight:bold;padding-left:2px'>".strtoupper(str_replace("_"," ",$key))."</th>\n";
    	echo "\t</tr></thead>\n";
        echo "<tbody>\n";
        foreach ( $results as $result )	{
          echo "\t<tr>\n";
          foreach ( array_keys($results[0]) as $key )
                  echo"\t\t<td".(strlen($results[0][$key]) < 5?" style='text-align:center'":"").">{$result[$key]}</td>\n";
          echo "\t</tr>\n";
       }
        if(isset($totals) && sizeof($totals) > 0){
                foreach ( $totals as $total )	{
                echo "\t<tr style='font-weight:bold;'>\n";
                  foreach ( array_keys($totals[0]) as $key ) {
                          echo "\t\t<td style='border-top:1px solid black;text-align:center'>{$total[$key]}</td>\n";
                  }
               }
               echo "\t</tr>\n";
        }
        echo "</tbody>\n";
    	echo "</table>\n";
        echo "</div>\n";
      }
    }
    function edit_field($key,$table_name,&$variables,&$fields){
            $return = "<div class='{$key}_{$this->table_name}_field {$type}_field'>\n";
            switch($type){
                    case "list+other":
                    case "list":
                        $return .= "\t<select class='Filter' name='$key'>\n";
                        $return .= "\t\t<option></option>\n";
                        foreach($fields[$key]['options'] as $option)
                            $return .= "\t\t<option ". ( $variables[$key] == $option ? "selected='selected'" : "") .">$option</option>\n";
                    break;
                    case "range":
                        $return .= "\t<select class='Filter' name='$key'>\n";
                        $return .= "\t\t<option></option>\n";
                        for($i = $fields[$key]['options'][0];$i <= $fields[$key]['options'][1];$i++)
                            $return .= "\t\t<option ".( $variables[$key] == $i ? "selected" : "") .">$i</option>\n";
                        $return .= "\t</select>\n";
                    break;
                    case "auto":
                        $this->show_dropdown($table_name,$key,$key,$variables[$key],( isset($fields[$key]["where"]) ? $fields[$key]["where"] : " WHERE {$key} IS NOT NULL and {$key} != '' ORDER BY {$key} "),"Filter");
                    break;
                    case "distinct_list":
                    case "table_link":
                        $this->show_dropdown($fields[$key]['lookup_table'],$fields[$key]['lookup_field'],$key,$variables[$key],( isset($fields[$key]["where"]) ? $fields[$key]["where"] : NULL), "Filter",( isset($fields[$key]["lookup_id"]) ? $fields[$key]["lookup_id"] : NULL));
                    break;
                    case "table_link-checkboxes":
                        $return .="<ul class='{$type}'>\n";
                        $list = explode(',',$this->variables[$key]);
                        $results = $this->mysql->get_sql("Select DISTINCT `".( isset($fields[$key]["lookup_id"]) ? $fields[$key]["lookup_id"] : $key)."`,`".$fields[$key]['lookup_field']."` FROM `".$fields[$key]['lookup_table']."` ORDER BY `".$fields[$key]['lookup_field']."`");
                        foreach($results as $result){
                                $return .= "<li>\n";
                                $return .= "\t<input class='Filter' name='{$key}[]' value='".$result[( isset($fields[$key]["lookup_id"]) ? $fields[$key]["lookup_id"] : $key)]."'  type='checkbox'/>\n";
                                $return .= "\t".str_replace("_"," ",$result[$fields[$key]['lookup_field']])."\n";
                                $return .= "</li>\n";
                        }
                        $return .= "</ul>\n";
                    break;
                    case "checkbox-list":
                        $return .= "<ul class='{$type}'>\n";
                        $list = explode(',',$this->variables[$key]);
                        foreach($fields[$key]['options'] as $option){
                                $return .= "<li>\n";
                                $return .= "\t<input class='Filter' name='{$key}[]' value='".$option."' ". (in_array($option,$list) ? "checked='checked'" : "")." type='checkbox' />\n";
                                $return .= "\t".str_replace("_"," ",$option)."\n";
                                $return .= "</li>\n";
                        }
                        $return .= "</ul>\n";
                    break;
                    case "bool":
                      $return .= "\t\t<input style='width:auto;display:inline;margin-left:5px;border:0;'  type='radio' name='$key' class='Filter'  value='1' />Yes\n";
                      $return .= "\t\t<input style='width:auto;display:inline;margin-left:20px;border:0;float:none;'  type='radio' name='$key' class='Filter'  value='0'/>No\n";
                    break;
                    case "checkbox":
                        $return .= "\t<input class='Filter'  name='$key' type='checkbox' value='TRUE' />\n";
                    break;
                    default:
                        $return .= ($type=="currency"?"<div style='display:inline;float:left;'>$</div>":"").'<input  class="Filter '. ( isset($fields[$key]['type']) && $fields[$key]['type'] =='timestamp' ? 'datepickerclass ' : ( $fields[$key]['type'] =='time' ? 'timepickerclass ' : '')).'"  name="'.$key.'" type="'. ( $fields[$key]['type'] =='password' ? 'password' : 'text').'"  value="'.$variables[$key].'"/>';
                    break;
                }
            $return .= "</div>\n";
            return $return;
    }

}
?>

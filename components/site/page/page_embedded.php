<?php
/*  Class for Embeding Web Pages
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
require_once SITEPATH."/model/blank_class.php";

### Table/db Initialization Variables  ###

class PageEmbeddedClass extends TableClass{
    public function __construct() {
      $this->variables = Array();
      $this->default_results = 25;
      $this->page_id = str_replace(SITEPATH."/components/",'',__FILE__); //Let table class know what page this is
      $this->page_title = str_replace("_"," ",substr($this->page_id,(strpos($this->page_id,'/')?strrpos($this->page_id,'/')+1:0),-4)); //Set the title based on the page name
      $this->flat_table = TRUE;

      $this->db = new MySQLDatabase;
      if(isset($this->db)){
        $this->table_name = "page_embedded";
    	$this->primary_key = "page_id";
    	$this->fields = array(
         "page_id" => array("type" => "int", "hidden" => TRUE,"min_length" => 1),
         "url" => array("type" => "url","min_length" => 1, "max_length" => 100,"default"=>"http://www.example.com","searchable"=>TRUE,"description"=>"<b>*Note</b>: Not all sites allow access."),
         "last_updated" => array("type"=>"timestamp", "hidden"=> TRUE),
         "updated_by" => array("type" => "text","max_length" => 20, "hidden" => TRUE)
    	 );
        $this->mysql = new MySQLClass($this->db->get_db(),$this->fields,$this->table_name,$this->primary_key);
      }
      else
        die('Missing DB Class');
	}
     public function init_variables(){
        parent::init_variables();

    }
    public function action_check($action = NULL){
        $this->init_variables();
        switch (isset($action) ? $action : $this->variables['action']) {
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
        $current_page = new BlankClass();

        $current_page->page_id = $page_id;
     /*   //Lookup URL, Use @ to suppress the warning message of a bad URL
        if(!$content = @file_get_contents($this->mysql->url)){
            $content = "<p style='text-align:center;font-weight:bold'>Invalid URL</p>";
        }
       $content = preg_replace("/[^[:print:]]+/", "", $content);
        preg_match( '@<meta\s+http-equiv="Content-Type"\s+content="([\w/]+)(;\s+charset=([^\s"]+))?@i', $content, $encoding );
        $content = iconv( $encoding[3], "utf-8", $content );

        $content = preg_replace
    	(
    	array(
    	// Remove invisible content
    	'@<head[^>]*?>.*?</head>@siu',
    	'@<style[^>]*?>.*?</style>@siu',
    	'@<script[^>]*?.*?</script>@siu',
    	'@<object[^>]*?.*?</object>@siu',
    	'@<embed[^>]*?.*?</embed>@siu',
    	'@<applet[^>]*?.*?</applet>@siu',
    	'@<noframes[^>]*?.*?</noframes>@siu',
    	'@<noscript[^>]*?.*?</noscript>@siu',
    	'@<noembed[^>]*?.*?</noembed>@siu',

    	// Add line breaks before & after blocks
    	'@<((br)|(hr))@iu',
    	'@</?((address)|(blockquote)|(center)|(del))@iu',
    	'@</?((div)|(h[1-9])|(ins)|(isindex)|(p)|(pre))@iu',
    	'@</?((dir)|(dl)|(dt)|(dd)|(li)|(menu)|(ol)|(ul))@iu',
    	'@</?((table)|(th)|(td)|(caption))@iu',
    	'@</?((form)|(button)|(fieldset)|(legend)|(input))@iu',
    	'@</?((label)|(select)|(optgroup)|(option)|(textarea))@iu',
    	'@</?((frameset)|(frame)|(iframe))@iu',),

    	array(' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ',
    	"\n\$0", "\n\$0", "\n\$0", "\n\$0", "\n\$0", "\n\$0","\n\$0", "\n\$0",),$content);
        // Remove all remaining tags and comments and return.
        //$content = strip_tags( $content );
        $content = html_entity_decode( $content, ENT_QUOTES, "UTF-8" );
        $tempfname = tempnam('/tmp','phpTest');
        $temp= fopen($tempfname,"w");
        fwrite($temp, $content);
        $path = stream_get_meta_data($temp);   */
        //move_uploaded_file ( $path['uri']  , $path['uri']."_2" );
        $current_page->html =  "<iframe style='margin-left:10px' width='98%' height='500px' src='http://".str_replace('http://','',$this->mysql->url)."' scrolling='yes'></iframe>" ;
        //$current_page->html = $content;
        //unlink($temp);
        return $current_page;
       }
    }
}
?>
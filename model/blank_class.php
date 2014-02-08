<?php
/*
 * Template for making a basic page with html/text
    Copyright (C) 2007-2014 MyCoreCMS

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details. <http://www.gnu.org/licenses/>.
 */

class BlankClass{
  var $db;
  var $mysql;
  var $parent_id;
  var $parent_key;
  var $page_id;
  var $page_title;
  var $html;
  var $user;

    public function __construct() {
      $this->page_id = str_replace(SITEPATH."/components/",'',__FILE__); //Let table class know what page this is
      $this->page_title = str_replace(SITEPATH."/components/",'',__FILE__); //Set the title based on the page name
    }
    public function init_variables(){
        //put any custom variables you want here
        parent::init_variables();
        //if you want to force any variables put it after the parent function
    }
     public function action_check($action = ''){
            echo "\t<input class='current_page' type='hidden' name='Homepage' value='".$_SERVER['PHP_SELF']."?get_page={$this->page_id}'/>\n";
            echo html_entity_decode($this->html,ENT_QUOTES,'UTF-8');
     }

}

 ?>
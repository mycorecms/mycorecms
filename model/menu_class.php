<?php
/*
   Creates the site menu based on user level + returns the menu
    Copyright (C) 2007-2014 MyCoreCMS

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details. <http://www.gnu.org/licenses/>.
 */
require_once SITEPATH ."/components/site/user_page_access.php";

class MenuClass {
    protected $menu = "";
    protected $login = "";
	public function show_menu($user) {
      $access = new UserPageAccessClass();
      $where_criteria[] = array("field" => "user_id", "operator"=>"=", "argument"=>"{$user->mysql->user_id}");
      $order_by[] = array("field" => 'section', "ascending" => 'TRUE');
      $menu_items = $access->mysql->get_all($where_criteria,$order_by );
      $this->menu = "<ul class='menu_list'>\n";
      $this->menu .="\t<li><a class='menu_link' href='".$_SERVER['PHP_SELF']."?get_page=index.php'>HOMEPAGE</a></li>\n";
      $section = '';
      $count = 0;
      foreach($menu_items as $menu_item){
            $pages = explode(',',$menu_item->page);
            sort($pages);
            foreach( $pages as $page)
                $count++;
      }
      if($count < 8){//If less than 8, don't show sub-menu
         foreach($menu_items as $menu_item){
            $pages = explode(',',$menu_item->page);
            foreach( $pages as $page)
                $this->menu .="\t<li><a class='menu_link' href='".$_SERVER['PHP_SELF']."?get_page=".(strrpos($page,'.php')?$menu_item->section."/".$page:preg_replace('/.*\./','',$page))."'>".strtoupper(preg_replace('/\..*$/','',$page))."</a></li>\n";
         }
         $this->menu .=" </ul> \n";
      }
      else{
          $row = array();
          foreach($menu_items as $menu_item){
                if(!strpos($menu_item->section,"/"))
                    $this->menu .="\t<li><a class='submenu' href='' view='{$menu_item->section}'>".strtoupper($menu_item->section)."</a></li>\n";
                else{
                    if(!isset($row[substr($menu_item->section,0,(strpos($menu_item->section,'/')))]))
                        $row[substr($menu_item->section,0,(strpos($menu_item->section,'/')))] = "";
                    /*$row[substr($menu_item->section,0,(strpos($menu_item->section,'/')))] .="\t<li class='dropmenu'><a>".strtoupper(substr($menu_item->section,(strpos($menu_item->section,'/')+1)))."</a>\n";
                    $row[substr($menu_item->section,0,(strpos($menu_item->section,'/')))] .="\t\t<ul>\n";
                    $pages = explode(',',$menu_item->page);
                    sort($pages);
                    foreach( $pages as $page)
                        $row[substr($menu_item->section,0,(strpos($menu_item->section,'/')))] .="\t\t\t<li><a class='menu_link' href='".$_SERVER['PHP_SELF']."?get_page=".(strrpos($page,'.php')?$menu_item->section."/".$page:preg_replace('/.*\./','',$page))."'>".strtoupper(preg_replace('/\..*$/','',$page))."</a></li>\n";
                    $row[substr($menu_item->section,0,(strpos($menu_item->section,'/')))].="\t\t </ul> \n";
                    $row[substr($menu_item->section,0,(strpos($menu_item->section,'/')))].="\t</li>\n";   */
                    $menu_insert ="\t<li class='dropmenu ".(strpos($menu_item->section,'/') == strrpos($menu_item->section,'/')?"":"sidemenu")."'><a>".strtoupper(substr($menu_item->section,(strrpos($menu_item->section,'/')+1)))."</a>\n\t\t<ul>\n";
	 	    $pages = explode(',',$menu_item->page);
	 	    sort($pages);
	 	    foreach( $pages as $page)
	 	            $menu_insert .="\t\t\t<li><a class='menu_link' href='".$_SERVER['PHP_SELF']."?get_page={$menu_item->section}/{$page}'>".strtoupper(str_replace('.php','',$page))."</a></li>\n";
 	            $menu_insert .="\t\t </ul> \n\t</li>\n";
	 	    $section = substr($menu_item->section,0,(strpos($menu_item->section,'/')));
	 	    if(strpos($menu_item->section,'/') == strrpos($menu_item->section,'/'))
	 	            $row[$section].= $menu_insert;
	 	    else {
	 	            $last_section ='';
	 	            foreach(explode("/",$menu_item->section) as $section_search){
	 	                      if($section_search !=  substr($menu_item->section,(strrpos($menu_item->section,'/')+1)))
	 	                             $last_section = $section_search;
	 	            }
	 	            $search = strpos($row[$section],"<a>".strtoupper($last_section)."</a>\n\t\t<ul>\n")+strlen("<a>".strtoupper($last_section)."</a>\n\t\t<ul>\n");
	 	            $row[$section] = substr_replace($row[$section],$menu_insert,$search,0);
                      }
                }
          }
          $this->menu .=" </ul> \n";
          $section = '';

          foreach($menu_items as $menu_item){
            if(!strpos($menu_item->section,"/")){
                $this->menu .="<ul class='sub_menu_list hidden ".str_replace('/','_',$menu_item->section)."'>\n";
                if(isset($row[$menu_item->section])) //Add Submenus
                    $this->menu .=  $row[$menu_item->section];
                $pages = explode(',',$menu_item->page);
                sort($pages);
                foreach( $pages as $page)
                    $this->menu .="\t<li><a class='menu_link' href='".$_SERVER['PHP_SELF']."?get_page=".(strrpos($page,'.php')?$menu_item->section."/".$page:preg_replace('/.*\./','',$page))."'>".strtoupper(preg_replace('/\..*$/','',$page))."</a></li>\n";
                $this->menu .=" </ul> \n";
            }
          }
      }


      $this->menu .=" </ul> \n";
    return $this->menu;
	}
    public function build_menu($menu_item){
          foreach($menu_items as $menu_item){
            if(!strpos($menu_item,"/"))
                    $this->menu .="\t<li><a class='submenu' view='{$menu_item->section}'>".strtoupper($menu_item->section)."</a></li>\n";
          }
          $this->menu .=" </ul> \n";
          $section = '';
          foreach($menu_items as $menu_item){
                $this->menu .="<ul class='sub_menu_list hidden' id='{$menu_item->section}'>\n";
                $pages = explode(',',$menu_item->page);
                sort($pages);
                foreach( $pages as $page)
                    $this->menu .="\t<li><a class='menu_link' href='".$_SERVER['PHP_SELF']."?get_page=".(strrpos($page,'.php')?$menu_item->section."/".$page:preg_replace('/.*\./','',$page))."'>".strtoupper(preg_replace('/\..*$/','',$page))."</a></li>\n";
                $this->menu .=" </ul> \n";
          }
    }

    public function show_login($class) {
      $this->login = "<ul class='login_list'>\n";
      if(isset($class)){
        if($class == 'Admin')
          $this->login .="\t<li><a class='menu_link' href='".$_SERVER['PHP_SELF']."?get_page=site/user.php&amp;action=Show_Results'>USERS</a></li> |\n";
        else if($class != 'Public')
            $this->login .="\t<li><a class='menu_link' href='".$_SERVER['PHP_SELF']."?get_page=site/user.php&amp;action=Edit'>MY ACCOUNT</a></li> |\n";
        $this->login .="\t<li><a class='menu_link' href='http://www.mycorecms.com/index.php?get_page=community/support.php'>SUPPORT</a></li> |\n";
        if($class != 'Public')
            $this->login .="\t<li><a href='".$_SERVER['PHP_SELF']."?get_page=-3&amp;action=logout'>LOGOUT</a></li>\n";
        else
            $this->login .="\t<li><a class='menu_link' href='".$_SERVER['PHP_SELF']."?get_page=-3'>LOGIN</a></li>\n";
      }
      else{
        if (isset($_REQUEST['login']) && isset($_REQUEST['pass']))
            $this->login .= "<script>alert('Invalid Username or Password');</script>\n";
        $this->login .= "<form style='margin:0px;display: inline;' method='post' action=/index.php?get_page=-3&amp;action=login>\n";
        $this->login .= "\t<label>User:</label><input name='login' type='text' id='login' />\n";
        $this->login .= "\t<label>Password:</label><input name='pass' type='password' id='pass' />\n";
        $this->login .= "\t<input type='submit' name='Login' value='Login' />\n\t</form>\n";
        //$this->login .= "\t<li><a class='menu_link' href='/lib/register.php'>Register?</a></li>\n";
      }


      $this->login .=" </ul> \n";
    return $this->login;
	}
}
?>
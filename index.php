<?php
 header("P3P: CP=\"ALL DSP COR PSAa PSDa OUR IND ONL UNI COM NAV INT STA\"");
/*Main index, calls requested page

    Copyright (C) 2007-2014 MyCoreCMS

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details. <http://www.gnu.org/licenses/>.
 */

require_once dirname(__FILE__) . "/model/settings.php";
require_once SITEPATH . "/model/table_class.php";
require_once SITEPATH . "/components/site/user.php";
require_once SITEPATH . "/components/site/page.php";

        $user = new UserClass;
        $get_page =  ( isset($_REQUEST["get_page"]) ? $user->mysql->escape_string($_REQUEST["get_page"]) : 'index.php');

        //Special Check if this is a user request
        if($get_page == -3){
           $user->page_id = $get_page;
           $user->action_check();
           exit();
        }//Check if they are logged in
        if($user->check_login()){
            //Check if this is a jQuery request or not, if not load up the template
            if(!isset($_REQUEST['jquery'])){
              ob_start(); 
              require_once SITEPATH . "/model/menu_class.php";
              require_once SITEPATH . "/model/html_class.php";
              $menu = new MenuClass;
              $web_page = new HtmlClass($menu->show_menu($user),$menu->show_login($user->mysql->user_role));
              $web_page->set_title(SITE_NAME." Database");
              echo "<div class='tab' id='page{$get_page}'>\n";

            }
            if($get_page == 'index.php'){
                require_once SITEPATH . "/model/blank_class.php";
                $class_page = new BlankClass();
                $class_page->html = (isset($settings->mysql->homepage)?$settings->mysql->homepage:"");
                $class_page->action_check();
            }
            else{
                if(strrpos($get_page,'.php'))
                    $page = substr($get_page,(strrpos($get_page,'/')?strrpos($get_page,'/')+1:0),-4);
                else
                    $page = substr($get_page,(strrpos($get_page,'/')?strrpos($get_page,'/')+1:0));
                $section = substr($get_page,0,(strrpos($get_page,'/')?strrpos($get_page,'/'):0));


              $permission_check = $user->mysql->get_sql('SELECT permissions FROM user_page_access WHERE user_id = '.$user->mysql->user_id);
              if(sizeof($permission_check)>0 && $user->mysql->last_error == '' && $get_page != ''){
                  $check = false;
                  foreach($permission_check as $permission){
                          if($check = in_array($get_page,explode(',',$permission['permissions'])))
                              break;   //break the loop if we found the page
                  }
                  if(!$check){ //If we have no results Check if any new children are present + update permissions
                        require_once SITEPATH . "/components/site/user_page_access.php";
                        $page_access = new UserPageAccessClass;
                        $page_access->update_permissions($user->mysql->user_id,$get_page);

                        //Rerun the permission check
                        $permission_check = $user->mysql->get_sql('SELECT permissions FROM user_page_access WHERE user_id = '.$user->mysql->user_id);
                        foreach($permission_check as $permission){
                          if($check = in_array($get_page,explode(',',$permission['permissions'])))
                              break;   //break the loop if we found the page
                        }
                  }//If the user has access, load the page
                  if($check){
                      if(is_int((int)$page)&& (int)$page >0){
                          $pages = new PageClass;
                          $class_page = $pages->load((int)$page);
                      }
                      else{
                        require_once SITEPATH . "/components/".$get_page;
                        eval("\$class_page = new ".str_replace("_","",$page)."Class();");
                      }
                      $class_page->user = $user; //pass the user so the class can check user_role
                      $class_page->action_check();
               }
               else
                 echo "<div style='font-weight:bold;text-align:center'>Access Denied</div>\n";
             }
             else
                 echo "<div style='font-weight:bold;text-align:center'>Access Denied</div>\n";
           }
            //Check if this is a jQuery request or not
            if(!isset($_REQUEST['jquery'])){
              echo "</div>\n";
              $web_page->append_content(ob_get_clean());
              $web_page->render();
            }
        }
         else //Load the page
            $user->show_login_form();


?>

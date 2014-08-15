<?php
/*
    Handles User Page Permissions
    Copyright (C) 2007-2014 MyCoreCMS

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details. <http://www.gnu.org/licenses/>.
 */

class UserPageAccessClass extends TableClass{
    var $children;
    public function __construct() {
      $this->variables = Array();
      $this->default_results = 25;
      $this->printable = FALSE;
      $this->page_id = str_replace(SITEPATH."/components/",'',__FILE__); //Let table class know what page this is
      $this->page_title = str_replace("_"," ",substr($this->page_id,(strpos($this->page_id,'/')?strrpos($this->page_id,'/')+1:0),-4)); //Set the title based on the page name
      $this->db = new MySQLDatabase;

      if(isset($this->db)){


        $this->table_name = "user_page_access";
    	$this->primary_key = "user_page_access_id";
    	$this->fields = array(
           "user_page_access_id" => array("type" => "integer","min_length" => 1, "hidden" => TRUE),
           "user_id" => array("type" => "integer","min_length" => 0, "hidden" => TRUE),
           "user_role_id" => array("type" => "integer","min_length" => 0, "hidden" => TRUE),
           "section"=>array("type"=>"list","min_length" => 1, "max_length" => 45, "searchable"=>TRUE, "options"=>array('test','test2'),"js"=>"var page = getCurrentPage(this);get_queue(page.find('.current_page').val()+ '&action=get_pages&section='+ jQuery(this).val() +'&jquery=TRUE', function(msg) {jQuery('.page_user_page_access_field').replaceWith(msg);});"),
           "page"=>array("type"=>"checkbox-list","min_length" => 1, "max_length" => 1000, "searchable"=>TRUE, "options"=>array()),
           "permissions"=>array("type"=>"textarea","min_length" => 1, "max_length" => 1000, "hidden"=>TRUE),
    	);
        $this->mysql = new MySQLClass($this->db->get_db(),$this->fields,$this->table_name,$this->primary_key);
      }
      else
        die('Missing DB Class');
	}
    public function init_variables(){
        $this->order_by[] = array("field" => 'section', "ascending" => true);
        //Lookup any existing sections in both the page database + static folders
        $sections = $this->mysql->get_sql("SELECT GROUP_CONCAT(DISTINCT section) as section FROM page WHERE hidden != 1");
        $this->mysql->last_error = '';
        $this->fields['section']['options'] = (isset($sections[0])?explode(',',$sections[0]['section']):NULL);
        $this->read_dir(SITEPATH."/components");
        asort($this->fields['section']['options']);
        //put any custom variables you want here
        parent::init_variables();
        //if you want to force any variables put it after the parent function
        foreach(array_keys($this->fields) as $key){
            unset($_REQUEST[$key]);   //Clear out the variables so they are not seen in building permissions
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
        switch (isset($action) ? $action : $this->variables['action']) {
            case "get_pages":
               $this->edit_field('page');
            break;
            case "Add":
                if($this->variables['page'] != '')
                    $this->variables['permissions'] = implode(',',$this->build_permissions($this->variables['page'],$this->variables['section']));

                parent::action_check($action);
                //If we are changing a user role page, we have to update all users with that role with the changes.
                if($this->mysql->last_error =='' && $this->mysql->user_role_id > 0){
                    $role = $this->mysql->get_sql('SELECT role FROM user_role WHERe user_role_id = '.$this->mysql->user_role_id);
                    $user_list = $this->mysql->get_sql("SELECT DISTINCT user.user_id,GROUP_CONCAT(section) as section FROM user LEFT JOIN user_page_access ON user.user_id = user_page_access.user_id WHERE user_role = '{$role[0]['role']}' GROUP BY user.user_id HAVING (section NOT LIKE '%{$this->mysql->section}%' OR section IS NULL)");
                    foreach($user_list as $u){
                          $this->mysql->set_sql("INSERT INTO user_page_access (user_id,section,page,permissions) VALUES ({$u['user_id']},'{$this->mysql->section}','{$this->mysql->page}','{$this->mysql->permissions}')");
                    }//Find out if there are any users that already have this section and update.
                    $new_pages = explode(',',$this->variables['page']);
                    foreach($new_pages as $new){
                        $permissions = implode(',',$this->build_permissions($new,$this->variables['section']));
                        $user_list = $this->mysql->get_sql("SELECT DISTINCT user_page_access_id FROM user INNER JOIN user_page_access ON user.user_id = user_page_access.user_id WHERE user_role = '{$role[0]['role']}' AND section = '{$this->mysql->section}' AND page NOT LIKE '%$new%'");
                        foreach($user_list as $u){
                            $this->mysql->set_sql("UPDATE user_page_access SET page = CONCAT(page,',{$new}'),permissions = CONCAT(permissions,',{$permissions}')  WHERE user_page_access_id = {$u['user_page_access_id']}");
                        }
                      }
                }
            break;
            case "Update":
                $this->mysql->{$this->primary_key} = $this->variables[$this->primary_key];
                $this->mysql->load();

                $old_pages = explode(',',$this->mysql->page);
                $new_pages = explode(',',$this->variables['page']);
                if($this->variables['page'] != '')
                    $this->variables['permissions'] = implode(',',$this->build_permissions($this->variables['page'],$this->variables['section']));
                parent::action_check($action);
                //If we are changing a user role page, we have to update all users with that role with the changes.
                if($this->mysql->last_error =='' && $this->mysql->user_role_id > 0){
                  $role = $this->mysql->get_sql('SELECT role FROM user_role WHERe user_role_id = '.$this->mysql->user_role_id);
                  if(isset($role[0])){
                    $user_list = $this->mysql->get_sql("SELECT DISTINCT user.user_id,GROUP_CONCAT(section) as section FROM user LEFT JOIN user_page_access ON user.user_id = user_page_access.user_id WHERE user_role = '{$role[0]['role']}' GROUP BY user.user_id HAVING (section NOT LIKE '%{$this->mysql->section}%' OR section IS NULL)");
                    foreach($user_list as $u){
                          $this->mysql->set_sql("INSERT INTO user_page_access (user_id,section,page,permissions) VALUES ({$u['user_id']},'{$this->mysql->section}','{$this->mysql->page}','{$this->mysql->permissions}')");
                    }
                    //Add new page
                    if(sizeof($new_page = array_diff($new_pages,$old_pages)) >0){
                      foreach($new_page as $new){
                        if($new != ''){
                          $user_list = $this->mysql->get_sql("SELECT DISTINCT user_page_access_id FROM user INNER JOIN user_page_access ON user.user_id = user_page_access.user_id WHERE user_role = '{$role[0]['role']}' AND section = '{$this->mysql->section}' AND page NOT LIKE '%$new%'");
                          $permissions = implode(',',$this->build_permissions($new,$this->variables['section']));
                          echo $this->mysql->last_error;
                          foreach($user_list as $u){
                              $this->mysql->set_sql("UPDATE user_page_access SET page = CONCAT(page,',{$new}'),permissions = CONCAT(permissions,',{$permissions}')  WHERE user_page_access_id = {$u['user_page_access_id']}");
                          }
                        }
                      }
                    }
                    //Remove old pages
                    if(sizeof($removed = array_diff($old_pages,$new_pages)) >0){
                        foreach($removed as $remove){
                          if($remove != ''){
                            $permissions = implode(',',$this->build_permissions($remove,$this->variables['section']));
                            $this->mysql->set_sql("UPDATE user_page_access,user SET page = REPLACE(page,'{$remove},',''),permissions = REPLACE(permissions,'{$permissions},','') WHERE section = '{$this->mysql->section}'  AND user_role = '{$role[0]['role']}'");
                            $this->mysql->set_sql("UPDATE user_page_access,user SET page = REPLACE(page,',{$remove}',''),permissions = REPLACE(permissions,',{$permissions}','') WHERE section = '{$this->mysql->section}'  AND user_role = '{$role[0]['role']}'");
                            $this->mysql->set_sql("UPDATE user_page_access,user SET page = REPLACE(page,'{$remove}',''),permissions = REPLACE(permissions,'{$permissions}','') WHERE section = '{$this->mysql->section}'  AND user_role = '{$role[0]['role']}'");

                          }
                      }
                    }
                  }
                  echo $this->mysql->last_error;
                }
            break;
            default:
                parent::action_check($action);
            break;
        }
   }
   function edit_field($key,$class=NULL){
                if($key == 'page'){
                  if($this->variables['get_id'] >0){
                    $this->mysql->{$this->primary_key} = $this->variables['get_id'];
                    $this->mysql->load();
                    $this->variables['section'] = $this->mysql->section;
                  }
                     $this->fields['page']['options'] = $this->build_pages($this->variables['section']);
                     parent::edit_field($key,$class);
                }
                else
                    parent::edit_field($key,$class);

   }
   public function update_permissions($user_id,$page){
     $user_pages = $this->mysql->get_sql("SELECT * FROM user_page_access WHERE page!='' AND user_id = {$user_id} ".(strrpos($page,'.php')?" AND section LIKE '".substr($page,0,strrpos($page,'/'))."'":" AND page NOT LIKE '%.php%'"));
     foreach($user_pages as $user_page){
        $this->mysql->{$this->primary_key} = $user_page[$this->primary_key];
        $this->mysql->load();
        $this->mysql->permissions = implode(',',$this->build_permissions($this->mysql->page,$this->mysql->section));
        $this->mysql->save();
     }
   }
   public function build_pages($section){
     $excludes = array();
     $options = array();
     if($section != ''){

          $pages = $this->mysql->get_sql("SELECT DISTINCT GROUP_CONCAT(CONCAT(page_title,'.',page_id)) as page FROM page WHERE hidden != 1 AND parent_page_id =0 AND section = '".$this->mysql->escape_string($section)."'");
          $options = (isset($pages[0]) && $pages[0]['page'] != ''?explode(',',$pages[0]['page']):NULL);
                           if(is_dir(SITEPATH."/components/".$this->variables['section']."/")){
                             if ($handle = opendir(SITEPATH."/components/".$section."/")) {
                                while (false !== ($entry = readdir($handle))) {
                                    if (!is_dir(SITEPATH."/components/".$section."/".$entry)&& $entry != "." && $entry != ".."){
                                        require_once SITEPATH."/components/".$section."/".$entry;
                                        eval("\$class_page = new ".str_replace("_","",str_replace('.php','',$entry))."Class();");
                                        $class_page->user = $this->user;
                                        if(isset($class_page->children)){
                                           foreach($class_page->children as $child)
                                              $excludes[]=str_replace($section."/","",$child['get_page']);
                                        }
                                    }
                                }
                                closedir($handle);
                            }
                            if ($handle = opendir(SITEPATH."/components/".$section."/")) {
                                while (false !== ($entry = readdir($handle))) {
                                    if (!is_dir(SITEPATH."/components/".$section."/".$entry)&& $entry != "." && $entry != ".." && !in_array($entry,$excludes)){
                                        $options[]= $entry;
                                    }
                                }
                                closedir($handle);
                            }
                          }
                        asort($options);
        }
        return $options;
   }

   public function build_permissions($pages,$section){
      $page_permissions = array();
      foreach(explode(',',$pages) as $page){
                      //If user has access to page.php they have access to all it's possible children
                      if($page =='page.php' && $section == 'site' ){
                        if ($handle = opendir(SITEPATH."/components/site/page/")) {
                                while (false !== ($entry = readdir($handle))) {
                                    if (!is_dir(SITEPATH."/components/site/page/".$entry)&& $entry != "." && $entry != ".." ){
                                        $page_permissions[] = "site/page/".$entry;
                                    }
                                }
                                closedir($handle);
                            }
                      }
                      if(strrpos($page,'.php'))
                        $page_permissions[] = $section."/".$page;
                      else
                        $page_permissions[] = substr($page,(strrpos($page,'.')?strrpos($page,'.')+1:0));

                        $children = $this->get_children($section."/".$page);
                        if(sizeof($children) > 0)
                            $page_permissions = array_merge($page_permissions,$children);

            }
            //print_r($page_permissions);
            return $page_permissions;
    }
    public function get_children($path){
        $children = array();

                        if(strrpos($path,'.php'))
                            $page_check = substr($path,(strrpos($path,'/')?strrpos($path,'/')+1:0),-4);
                        else
                            $page_check = substr($path,(strrpos($path,'.')?strrpos($path,'.')+1:0));

                        if(is_int((int)$page_check)&& (int)$page_check >0){
                            $pages = new PageClass;
                            $class_page = $pages->load((int)$page_check);
                        }
                        else{
                          if(is_file (SITEPATH . "/components/".$path)){ //Prevent an error if a file has been deleted from the server
                            if (!class_exists(str_replace("_","",$page_check)."Class"))
                               require_once SITEPATH . "/components/".$path;
                               eval("\$class_page = new ".str_replace("_","",$page_check)."Class();");

                          }
                        }
                        $class_page->user = $this->user;
                        if(isset($class_page->children)){
                          foreach($class_page->children as $child){
                            if(isset($child['get_page'])){
                                $children[] = $child['get_page'];
                                $child= $this->get_children($child['get_page']);
                                if(sizeof($child) >0 )
                                    $children = array_merge($children,$child);
                             }
                          }
                         }
                        return $children;
    }
}
?>
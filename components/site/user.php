<?php
/*
    Class for Handling User Accounts
    Copyright (C) 2007-2014 MyCoreCMS

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details. <http://www.gnu.org/licenses/>.
 */


class UserClass extends TableClass{

    public function __construct() {
      $this->variables = Array();
      $this->default_results = 25;
      $this->printable = FALSE;
      $this->page_id = str_replace(SITEPATH."/components/",'',__FILE__); //Let table class know what page this is
      $this->page_title = 'Users';

        $this->children = array(
        "Page Access"=> array("action"=>"","get_page"=>"site/user_page_access.php"),
        );

        $this->table_name = "user";
    	$this->primary_key = "user_id";
    	$this->fields = array(
    	 "user_id" => array("type" => "integer", "min_length" => 1,'searchable'=>TRUE, 'hidden'=>TRUE),
    	 "first_name" => array("type"=>"text","min_length" => 1, "max_length" => 10, 'searchable'=>TRUE),
         "last_name" => array("type"=>"text","min_length" => 1, "max_length" => 40, 'searchable'=>TRUE),
         "login" => array("type"=>"text","min_length" => 1, "max_length" => 40, 'searchable'=>TRUE),
         "email" => array("type"=>"email","min_length" => 1, "max_length" => 80, 'searchable'=>TRUE),
         "password" => array("type"=>"password", "min_length" => 1, "max_length" => 40),
         "validation_url" => array("type"=>"text","min_length" => 0, "max_length" => 40,"hidden"=>TRUE),
         "user_role" => array("type"=>"distinct_list","lookup_table"=>"user_role","lookup_field"=>"role","lookup_id"=>"role","min_length" => 1, "max_length" => 20, 'searchable'=>TRUE),
         "last_login" => array("type"=>"timestamp","min_length" => 0, "max_length" => 40,"hidden"=>TRUE),
         "disable" => array("type"=>"checkbox","min_length" => 0, "max_length" => 4),
         "add" => array("type"=>"checkbox","min_length" => 0, "max_length" => 4,"default"=>1),
         "edit" => array("type"=>"checkbox","min_length" => 0, "max_length" => 4,"default"=>1),
         "download" => array("type"=>"checkbox","min_length" => 0, "max_length" => 4,"default"=>1),
         "delete" => array("type"=>"checkbox","min_length" => 0, "max_length" => 4),
         "export" => array("type"=>"checkbox","min_length" => 0, "max_length" => 4),
         "login_attempt" => array("type"=>"integer","min_length" => 0, "max_length" => 4,"hidden"=>TRUE),
    	 );
         $this->db = new MySQLDatabase;
         $this->mysql = new MySQLClass($this->db->get_db(),$this->fields,$this->table_name,$this->primary_key);
    }
    public function init_variables(){
        //$this->user = $this;
        //put any custom variables you want here
        parent::init_variables();
        //Salt new passwords

    }
     public function action_check($action = NULL){
       $this->init_variables();
        switch (isset($action) ? $action : ( isset($_REQUEST["action"])  ? $_REQUEST["action"] : "")) {
         case "logout":
            setcookie("mycorecms_user", "",time()-60*60*24*100);
            //unset($_COOKIE["mycorecms_user"]);
            //session_unset();
            //session_destroy();
            header('Location: http://'.$_SERVER['SERVER_NAME']);
         break;
         case "Reset":
            if($this->variables['email']!= ''){
                $criteria = array(array("field" => "email", "operator"=>"LIKE", "argument"=>"{$this->variables['email']}"));
                $results = $this->mysql->get_all($criteria);
                //Check if this is a password reset request
                if($results && $this->mysql->last_error == ""){
                  foreach($results as $result){
                    $this->mysql->user_id = $result->user_id;
                    $this->mysql->load();
                    //Create a random validation url
                    $this->mysql->validation_url = SHA1(rand());
                    $this->mysql->save();
                    $this->mysql->load();

                    $this->mysql->last_error = "A Password Reset Email Has Been Sent to: {$this->variables['email']}";

                    $email =  $this->variables['email'];
                    $subject = SITE_NAME.' Account';

                    $message = "<p><strong>Click the link to set your password:</strong> <a href='http://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?get_page=-3&amp;action=Reset_Password&amp;user_id={$this->mysql->user_id}&amp;validation_url={$this->mysql->validation_url}'>Set Password</a></p>";
                    $message .= "<p>A Password Reset was requested for ({$this->mysql->login}) at ".$_SERVER['SERVER_NAME'].".</p><p>If you did not request to have your password reset, you can safely ignore this email and your password will not change.</p>";
                    $this->send_email($email, $subject, $message );

                 }
                    echo $this->show_login_form();
                    exit();
                }
                else{
                  $this->mysql->last_error = "No Account Found With The Email:{$this->variables['email']}";
                  echo $this->show_reset_form();
                  exit();
                }
            }//Check if we are resetting a password
            else if($this->variables['password']!= '' && $this->variables['validation_url']!= '' && $this->variables['user_id']!= '' ){
                    if(isset($_REQUEST['password2']) && $_REQUEST['password2'] == $this->variables['password']){
                        $criteria = array(array("field" => "user_id", "operator"=>"=", "argument"=>"{$this->variables['user_id']}"),
                                array("field" => "validation_url", "operator"=>"=", "argument"=>"{$this->variables['validation_url']}"),);
                        $results = $this->mysql->get_all($criteria);
                        if($results && $this->mysql->last_error == ""){
                           foreach($results as $result){
                              $this->mysql->user_id = $result->user_id;
                              $this->mysql->load();
                              //$this->mysql->validation_url = SHA1(rand());
                              $this->mysql->password = $this->variables['password'];
                              $this->mysql->login_attempt = 0;
                              $this->mysql->last_login = date('Y-m-d h:m:s');
                              $this->mysql->save();
                              $this->mysql->load();
                            }
                            if($this->mysql->last_error != ""){
                                echo $this->show_reset_form();
                                exit();
                            }
                            else{
                                //$_SESSION["mycorecms_user"] =  $this->mysql->user_id ."|". $this->mysql->validation_url ."|". $this->mysql->first_name ." ". $this->mysql->last_name;
                                setcookie("mycorecms_user",$this->mysql->user_id ."|". $this->mysql->validation_url ."|". $this->mysql->first_name ." ". $this->mysql->last_name );
                                header('Location: '.$_SERVER['PHP_SELF']);
                            }
                        }
                        else{
                          $this->mysql->last_error = "Lookup Failed!";
                          echo $this->show_reset_form();
                          exit();
                        }
                    }
                    else{
                          $this->mysql->last_error = "Password Mismatch!";
                          echo $this->show_reset_form();
                          exit();
                        }
            }
            else{
                echo $this->show_reset_form();
                exit();
            }
          break;
          case "Reset_Password":
            if($this->variables['user_id']!= '' && $this->variables['validation_url']){
                $criteria = array(array("field" => "user_id", "operator"=>"=", "argument"=>"{$this->variables['user_id']}"),
                        array("field" => "validation_url", "operator"=>"=", "argument"=>"{$this->variables['validation_url']}"),);
                $results = $this->mysql->get_all($criteria);
                if($results && $this->mysql->last_error == ""){
                  foreach($results as $result){
                    $this->mysql->user_id = $result->user_id;
                    $this->mysql->load();
                  }
                }
                else
                  $this->mysql->last_error = "Invalid Password Reset";
            }
            echo $this->show_reset_form();
            exit();
          break;
          case "Add":
          if($this->check_login()){   //Make sure the user is an admin
              if($this->mysql->user_role == 'Admin'){
                $this->variables['password'] = $this->createRandomPassword();//Set a random password for a new account
                $this->variables['validation_url'] =SHA1(rand());
                parent::action_check();
                if($this->mysql->last_error == ''){//Check if we had an error, if not email new account information
                    $role = $this->mysql->get_sql("SELECT user_role_id FROM user_role WHERE role='".$this->mysql->user_role."'");
                    if(isset($role[0])){
                      $pages = $this->mysql->get_sql("SELECT section,page FROM user_page_access WHERE user_role_id=".$role[0]['user_role_id']);
                      foreach($pages as $page){
                        $this->mysql->set_sql("INSERT INTO user_page_access (user_id,section,page) VALUES ({$this->mysql->user_id},'{$page['section']}','{$page['page']}')");
                      }
                    }
                    echo $this->mysql->last_error;
                    $email =  $this->variables['email'];
                    $subject = 'Your Site Account';

                    $message = "<p><strong>Click the link to create your password:</strong> <a href='http://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?get_page=-3&amp;action=Reset_Password&amp;user_id={$this->mysql->user_id}&amp;validation_url={$this->mysql->validation_url}'>Set Password</a></p>";
                    $message .= "<p>A new account({$this->mysql->login}) was created for you at ".$_SERVER['SERVER_NAME'].".</p>";

                    $this->send_email($email, $subject, $message );

                }
              }
            }
          break;
          case "Create_Account":
          //Double check there are no users
          $this->mysql->update_table();
          $user_count = $this->mysql->get_sql('SELECT count(*) as count FROM user');
          if($user_count[0]['count'] ==0){
            if(isset($_REQUEST['password2']) && $_REQUEST['password2'] == $this->variables['password']){
                  $this->variables['error'] = '';
                  $this->variables['user_role'] = "Admin";
                  $this->variables['add'] = TRUE;
                  $this->variables['edit'] = TRUE;
                  $this->variables['delete'] = TRUE;
                  $this->variables['export'] = TRUE;
                  $this->variables['download'] = TRUE;
                  $this->variables['validation_url'] =SHA1(rand());
                	$this->mysql->clear();
                	foreach(array_keys($this->fields) as $key){
                	  if($this->fields[$key]['type'] != 'file' && $key != $this->primary_key)
                        $this->mysql->$key = $this->variables[$key];
                    }
                	if($this->mysql->last_error != '' OR !$this->mysql->save()){
                		$this->variables['error']= $this->mysql->last_error;
                    }
                	else{
                	  $this->mysql->last_error = '';//reset error from primary_id being empty
                      $this->mysql->load();
                    }
                    if($this->variables['error'] ==''){
                        //$this->mysql->set_sql("INSERT INTO user_page_access (user_id,section,page) VALUES ({$this->mysql->user_id},'site','import.php,features.php,user.php,user_role.php,page.php')");
                        require_once('user_role.php');
                        $user_role = new UserRoleClass;
                        $user_role->mysql->update_table(); //Initialize Table
                        $user_role->mysql->role ='Admin';
                        $user_role->mysql->updated_by ='Admin';
                        $user_role->mysql->save();
                        require_once('user_page_access.php');
                        $user_page_access = new UserPageAccessClass;
                        $user_page_access->mysql->update_table(); //Initialize Table
                        $user_page_access->mysql->user_id = $this->mysql->user_id;
                        $user_page_access->mysql->user_role_id = $user_role->mysql->user_role_id;
                        $user_page_access->mysql->section = 'site';
                        $user_page_access->mysql->page = implode(',',$user_page_access->build_pages('site'));//Give access to all admin pages
                        $user_page_access->mysql->permissions = implode(',',$user_page_access->build_permissions($user_page_access->mysql->page,$user_page_access->mysql->section));
                        $user_page_access->mysql->updated_by ='Admin';
                        $user_page_access->mysql->save();
                        echo $user_page_access->mysql->last_error;
                        setcookie("mycorecms_user",$this->mysql->user_id ."|". $this->mysql->validation_url ."|". $this->mysql->first_name ." ". $this->mysql->last_name);
                        header('Location: '.$_SERVER['PHP_SELF']);
                    }
                    else
                        $this->show_initial_form();
               }
               else{
                     $this->mysql->last_error = "Password Mismatch!";
                     $this->show_initial_form();
                }
          }
          else{
                     $this->mysql->last_error = "Administrator Account Already Exists!";
                     $this->show_login_form();
            }
          break;
          case "Edit":
          case "Update":
          $this->fields['login']['readonly'] = TRUE;
          //Load up the user
          if($this->check_login()){
            if($this->mysql->user_role != 'Admin'){
                $this->variables['user_id'] = $this->mysql->user_id; //Force Only Editing your own account for security
                //Disable all permissions for security
                UNSET($this->fields['disable']);
                UNSET($this->fields['user_role']);
                UNSET($this->fields['add']);
                UNSET($this->fields['edit']);
                UNSET($this->fields['delete']);
                UNSET($this->fields['download']);
                UNSET($this->fields['export']);
                UNSET($this->children);
            }
            parent::action_check();
          }
           break;
          default:
            if(!$this->check_login() OR $this->mysql->user_role == 'Public'){
                  echo $this->show_login_form();
            }
            if($this->mysql->user_role == 'Admin')
                parent::action_check();
          break;


        }
     }
     public function show_login_form(){
      ?>
      <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
      <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
      <head><title>Site Login</title></head>
      <body>
      <div style="background-color:white;width:600px;height:400px;margin:0 auto;padding-top:10px;">
      <p align="center"><img src="<?php echo SITE_LOGO; ?>" height="68"></p>

      <h1 style="text-align:center;color: #4682b4;font: bold 18px Verdana, Arial, Helvetica, sans-serif;border-bottom: 2px dashed #E6E8ED;">Login</h1>
      <?php
        if($this->mysql->last_error != '')
            echo "<div class='error result' style='background-color: #faa !important; text-align:center;'>".$this->mysql->last_error."</div>\n";
      ?>
      <form method="post" action="<?php echo $_SERVER['PHP_SELF']."?".str_replace("&jquery=TRUE","",$_SERVER['QUERY_STRING'])?>" name="loginform">
          <table width="300" border="0" align="center" cellpadding="2" cellspacing="0">
          <tr>
            <td><strong>Login</strong></td>
            <td><input name="login" type="text"/></td>
          </tr>
          <tr>
            <td><strong>Password</strong></td>
            <td><input name="pass" type="password"/></td>
          </tr>
          <tr>
            <td>&nbsp;</td>
            <td><input type="submit" name="Submit" value="Login" style="width: 142px" /></td>
          </tr>
          <tr>
            <td><a href='?get_page=-3&amp;action=Reset'>Lost Password?</a></td>
            <td>&nbsp;</td>
          </tr>
        </table>


      </form>
      </div>
      </body>
      </html>

     <?php
     exit();
    }
    public function show_reset_form(){
      ?>
      <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
      <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
      <head><title>Account Reset</title></head>
      <body>
      <div style="background-color:white;width:600px;height:400px;margin:0 auto;padding-top:10px;">
      <p align="center"><img src="<?php echo SITE_LOGO; ?>" height="68"></p>

      <h1 style="text-align:center;color: #4682b4;font: bold 18px Verdana, Arial, Helvetica, sans-serif;border-bottom: 2px dashed #E6E8ED;"><?php echo (isset($this->variables['user_id'])?"Create a New Password":"Request Password Reset Email"); ?></h1>
      <?php
        if($this->mysql->last_error != '')
            echo "<div class='error result' style='background-color: #faa !important; text-align:center;'>".$this->mysql->last_error."</div>\n";
      ?>
      <form method="post" action="<?php echo $_SERVER['PHP_SELF']?>?get_page=-3" name="loginform">
          <table width="300" border="0" align="center" cellpadding="2" cellspacing="0">
      <?php if(isset($this->variables['user_id'])){
            echo "<input type='hidden' name='user_id' value='{$this->variables['user_id']}'>\n";
            echo "<input type='hidden' name='validation_url' value='{$this->variables['validation_url']}'>\n";
            echo "<p style='font-weight:bold;text-align:center;'>PASSWORDS MUST BE AT LEAST 8 CHARACTERS LONG AND HAVE 1 CAPITAL AND 1 NUMBER</p>\n";
            echo "<tr><td><strong>Password</strong></td><td><input name='password' type='password'/></td></tr>\n";
            echo "<tr><td><strong>Confirm Password</strong></td><td><input name='password2' type='password'/></td></tr>\n";
        }
        else
            echo "<tr><td><strong>Account Email</strong></td><td><input name='email' type='text'/></td></tr>\n";
      ?>
          <tr>
            <td>&nbsp;</td>
            <td><input type="submit" name="action" value="Reset" style="width: 142px" /></td>
          </tr>
        </table>
      </form>
      </div>
      </body>
      </html>

     <?php
     exit();
    }
    public function show_initial_form(){
      ?>
      <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
      <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
      <head><title>Account Setup</title></head>
      <body>
      <div style="background-color:white;width:600px;height:400px;margin:0 auto;padding-top:10px;">
      <p align="center"><img src="<?php echo SITE_LOGO; ?>" height="68"></p>
      <h1 style="text-align:center;color: #4682b4;font: bold 18px Verdana, Arial, Helvetica, sans-serif;border-bottom: 2px dashed #E6E8ED;">Create Your Administrator Account</h1>
      <?php
        if($this->mysql->last_error != '')
            echo "<div class='error result' style='background-color: #faa !important; text-align:center;'>".$this->mysql->last_error."</div>\n";
      ?>
      <p style='font-weight:bold;text-align:center;'>PASSWORDS MUST BE AT LEAST 8 CHARACTERS LONG AND HAVE 1 CAPITAL LETTER AND 1 NUMBER</p>
      <form method="post" action="<?php echo $_SERVER['PHP_SELF']?>?get_page=-3" name="loginform">
          <table width="300" border="0" align="center" cellpadding="2" cellspacing="0">
          <tr><td><strong>First Name</strong></td><td><input name='first_name' type='text'/></td></tr>
          <tr><td><strong>Last Name</strong></td><td><input name='last_name' type='text'/></td></tr>
          <tr><td><strong>Email</strong></td><td><input name='email' type='text'/></td></tr>
          <tr><td><strong>Login</strong></td><td><input name='login' type='text'/></td></tr>
          <tr><td><strong>Password</strong></td><td><input name='password' type='password'/></td></tr>
          <tr><td><strong>Confirm Password</strong></td><td><input name='password2' type='password'/></td></tr>
          <tr>
            <td>&nbsp;</td>
            <td><input type="submit" name="action" value="Create_Account" style="width: 142px" /></td>
          </tr>
        </table>

      </form>
      </div>
      </body>
      </html>

     <?php
     exit();
    }

    public function check_login(){
       $user_count = $this->mysql->get_sql("SELECT count(*) as count FROM user WHERE user_role = 'Admin'");
       if(isset($_REQUEST['action']) && $_REQUEST['action']== 'logout'){
            $this->action_check('logout');
            return false;
       }
       else if($user_count[0]['count'] ==0){
                $this->mysql->last_error = '';
                $this->mysql->update_table();
                   $this->show_initial_form();
       }
       else if (isset($_REQUEST['login']) && isset($_REQUEST['pass'])){
                  $login = $this->mysql->escape_string(strtolower($_REQUEST['login']));
                  $pass = $this->mysql->salt_password($_REQUEST['pass'],$login);
                  $criteria = array(array("field" => "login", "operator"=>"=", "argument"=>"{$login}"),);

                  $results = $this->mysql->get_all($criteria);
                   echo $this->mysql->last_error;
                  //Make sure we have a valid result
                  if($results && $this->mysql->last_error == ""){
                    //Will just return 1 user
                    foreach($results as $result){
                      $this->mysql->user_id = $result->user_id;
                      $this->mysql->load();                         //Check if the account has tried to login too many times in the past 15 minutes
                      if($this->mysql->password == $pass && ($this->mysql->login_attempt < 5 || (strtotime($this->mysql->last_login)+(15*60) < time()) )){
                        //Store random value for cookie
                        if($this->mysql->user_role != 'Public')
                            $this->mysql->validation_url = SHA1(rand());
                        $this->mysql->last_login = date('Y-m-d h:m:s');
                        $this->mysql->login_attempt = 0;
                        $this->mysql->save();
                        if($this->mysql->disable == 1){
                          $this->mysql->last_error= "Account Disabled!";
                          $this->show_login_form();
                          return false;
                        }
                        else{
                          //Set sessions variables so we can lookup later
                          //$_SESSION["site_user"] =  $this->mysql->user_id ."|". $this->mysql->validation_url ."|". $this->mysql->first_name ." ". $this->mysql->last_name;
                          setcookie("mycorecms_user",$this->mysql->user_id ."|". $this->mysql->validation_url ."|". $this->mysql->first_name ." ". $this->mysql->last_name);
                          header('Location: '.$_SERVER['PHP_SELF']);
                          return true;
                        }
                      }
                      else{
                        $this->mysql->last_login = date('Y-m-d h:m:s');
                        $this->mysql->login_attempt = $this->mysql->login_attempt+1;
                        $this->mysql->save();

                        if($this->mysql->login_attempt >5)
                            $this->mysql->last_error = "Your account has been locked for 15 minutes due to excessive login attempts.";
                        else
                            $this->mysql->last_error = "Login Failed! Invalid Username Or Password";
                        $this->show_login_form();
                        return false;
                      }
                    }
                  }
                  else{
                       $this->mysql->last_error = "Login Failed! Invalid Username Or Password";
                       return false;
                 }
       }
       else if (isset($_COOKIE['mycorecms_user']) ){
            $cookie_parse = explode("|",$_COOKIE['mycorecms_user']);
            //validate the user to prevent cookie hacking

            $this->mysql->clear();
            $this->mysql->user_id = (int)$cookie_parse[0];
            $this->mysql->load();
            //echo $this->mysql->last_error;
            //echo $this->mysql->validation_url."<br />".$cookie_parse[1]."<br />".$cookie_parse[0];

            if($this->mysql->validation_url == $cookie_parse[1] && $this->mysql->last_error == "")
                return true;
            else{  //If this is a jquery request we need to load the whole page
                if(isset($_REQUEST['jquery']))
                  $this->action_check('logout');
                if($this->mysql->validation_url != $cookie_parse[1])
                    $this->mysql->last_error = "Login Expired, Please Log Back In.";
                $this->show_login_form();
                return false;
            }

        }
        else{
            //Check if there is a public user in the system, if not create one
            $user_count = $this->mysql->get_sql("SELECT count(*) as count FROM user WHERE user_role = 'Public'");
            if($user_count[0]['count'] ==0){
                $this->variables['error'] = '';
                  $this->variables['first_name'] = "Public";
                  $this->variables['last_name'] = "User";
                  $this->variables['login'] = "Public";
                  $this->variables['email'] = "None@example.com";
                  $this->variables['user_role'] = "Public";
                  $this->variables['password'] = $this->createRandomPassword();
                  $this->variables['validation_url'] =SHA1(rand());
                  $this->variables['add'] = FALSE;
                  $this->variables['edit'] = FALSE;
                  $this->variables['download'] = FALSE;
                	$this->mysql->clear();
                	foreach(array_keys($this->fields) as $key){
                	  if($this->fields[$key]['type'] != 'file' && $key != $this->primary_key)
                        $this->mysql->$key = $this->variables[$key];
                    }
                	if($this->mysql->last_error != '' OR !$this->mysql->save()){
                		$this->variables['error']= $this->mysql->last_error;
                    }
                	else{
                	  $this->mysql->last_error = '';//reset error from primary_id being empty
                      $this->mysql->load();
                    }
                    if($this->variables['error'] ==''){
                        require_once('user_role.php');
                        $user_role = new UserRoleClass;
                        //$user_role->mysql->update_table(); //Initialize Table
                        $user_role->mysql->role ='Public';
                        $user_role->mysql->updated_by ='Public User';
                        $user_role->mysql->save();

                    }
            }
            $public_user = $this->mysql->get_sql("SELECT * FROM user WHERE user_role = 'Public'");
            setcookie("mycorecms_user",$public_user[0]['user_id'] ."|". $public_user[0]['validation_url'] ."|". $public_user[0]['first_name'] ." ". $public_user[0]['last_name']);
            $this->mysql->clear();
            $this->mysql->user_id = $public_user[0]['user_id'];
            $this->mysql->load();
            //header('Location: http://'.$_SERVER['SERVER_NAME']);
            return true;

        }

    }
    //Add/Change the way a field is edited
    public function edit_field($key,$class=NULL){
            switch($this->fields[$key]['type']){
                case "password":        //Only let a person change their own password, otherwise display password reset link
                     if($this->variables['user_id'] != $this->mysql->user_id){
                        IF($this->variables['action'] != 'Add_New'){
                            $this->mysql->user_id = $this->variables['user_id'];
                            $this->mysql->load();
                            echo "<a class='submit_link' href='".$_SERVER['PHP_SELF']."?get_page=-3&action=Reset&email=".$this->variables['email']."&jQuery=TRUE'>Email Password Reset</a>";
                            echo "<br />http://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?get_page=-3&amp;action=Reset_Password&amp;user_id={$this->variables['user_id']}&amp;validation_url={$this->mysql->validation_url}";
                            echo "<input name='{$key}' value='{$this->variables[$key]}' type='hidden'>";
                        }
                            echo "<br /><br />\n";
                     }
                     else
                        parent::edit_field($key,$class);

                break;
                default:
                    parent::edit_field($key,$class);
                break;
            }

    }
    public function createRandomPassword() {
      $chars = "abcdefghijkmnopqrstuvwxyz023456789";
      $pass = 'P' ;
      for($i=0;$i < 9;$i++) {
          $num = rand() % 33;
          $tmp = substr($chars, $num, 1);
          $pass = $pass . $tmp;
      }
      return $pass."1";
    }


}
 ?>
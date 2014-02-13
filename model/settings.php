<?php

/*  Site Settings
    Copyright (C) 2007-2014 MyCoreCMS

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details. <http://www.gnu.org/licenses/>.
 */

//Defines path for mapping all files
define( 'SITEPATH', str_replace('/model','',dirname(__FILE__)) );

//SET These fields to connect to your MySQL Database

define('SITE_DB_HOST', 'localhost');
define('SITE_DB_USER', 'mycorecms');
define('SITE_DB_PASS', 'mnKY4spnk');
define('SITE_DB_NAME', 'mycorecms');

test_connection(); 

//Load up user defined settingsuration
require_once SITEPATH . "/components/site/settings.php";
$settings = new SettingsClass;
$key_lookup = $settings->mysql->get_sql("SELECT `{$settings->primary_key}` FROM `{$settings->table_name}` ORDER BY `{$settings->primary_key}` LIMIT 1");
if(isset($key_lookup[0][$settings->primary_key])){
      $settings->mysql->{$settings->primary_key} =$key_lookup[0][$settings->primary_key];
      $settings->mysql->load();
}
// Admin contact address:
define('SITE_EMAIL', (isset($settings->mysql->email)?$settings->mysql->email:"no-reply@".str_replace("www.","",$_SERVER['SERVER_NAME'])));
//Directory of the template
define('SITE_TEMPLATE', (isset($settings->mysql->template)?$settings->mysql->template:"page"));
define('SITE_NAME', (isset($settings->mysql->site_name)?$settings->mysql->site_name:ucfirst(str_replace("www.","",$_SERVER['SERVER_NAME']))));
define('SITE_LOGO', (isset($settings->mysql->logo) && $settings->mysql->logo!=''?ltrim($settings->mysql->logo,"/"):"view/page/images/logo.png"));

define('SITE_GA_KEY', (isset($settings->mysql->analytics_key)?$settings->mysql->analytics_key:""));
define('SITE_CAPTCHA', (isset($settings->mysql->captcha) ?$settings->mysql->analytics_key:FALSE));
define('SITE_CAPTCHA_PUBLIC', "6Lf5i-4SAAAAAEMrHaQGBdytwHK3YfkZ0dDMC3sU");
define('SITE_CAPTCHA_PRIVATE', "6Lf5i-4SAAAAAPRI2kOJunwe2IJYHH4pEvgos00l");
define("EMAIL_ADDRESS_REGEX", '/^([a-z0-9])(([-a-z0-9._])*([a-z0-9]))*\@([a-z0-9])(([a-z0-9-])*([a-z0-9]))+(\.([a-z0-9])([-a-z0-9_-])?([a-z0-9])+)+$/i');
//define("EMAIL_ADDRESS_REGEX", "/^\w+([-+.']\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/");
define("URL_REGEX", '@^(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)$@i');
define("PASSWORD_REGEX", '/^.*(?=.{8,})(?=.*[a-z])(?=.*[A-Z])(?=.*[\d\W]).*$/');

// Adjust the time zone for PHP 5.1 and greater:
date_default_timezone_set('UTC');

//Check if we can connect to the database, if not request login/password
function test_connection(){
 $mysqli = @new mysqli(SITE_DB_HOST, SITE_DB_USER, SITE_DB_PASS, SITE_DB_NAME);
  if ($mysqli->connect_error) {
      If(isset($_REQUEST['username']) && isset($_REQUEST['password']) && isset($_REQUEST['database'])){
           $mysqli = @new mysqli(SITE_DB_HOST, $_REQUEST['username'], $_REQUEST['password']);
           if (!$mysqli->connect_error)
              $mysqli->query('CREATE DATABASE IF NOT EXISTS '.$_REQUEST['database']);
           $mysqli = @new mysqli(SITE_DB_HOST, $_REQUEST['username'], $_REQUEST['password'],$_REQUEST['database']);
           if (!$mysqli->connect_error){//If the connection works, save the changes
                $buffer = file_get_contents(__FILE__); //Get the current file
		$buffer = preg_replace("/define\('SITE_DB_USER', '.*'\);/i", "define('SITE_DB_USER', '".$_REQUEST['username']."');", $buffer,1);
		$buffer = preg_replace("/define\('SITE_DB_PASS', '.*'\);/i", "define('SITE_DB_PASS', '".$_REQUEST['password']."');", $buffer,1);
		$buffer = preg_replace("/define\('SITE_DB_NAME', '.*'\);/i", "define('SITE_DB_NAME', '".$_REQUEST['database']."');", $buffer,1);
		file_put_contents(__FILE__,$buffer);
		header('Location: '.$_SERVER['PHP_SELF']);
           }
      }


      ?>
      <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
      <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
      <head><title>Account Setup</title></head>
      <body>
      <div style="background-color:white;width:600px;height:400px;margin:0 auto;padding-top:10px;">
      <p align="center"><img src="view/page/images/logo.png" height="68"></p>
      <hr width="500" style="height: 25px;border-radius: 20px; -moz-border-radius: 20px; border: 2px solid #006633; " />

      <h1 style="text-align:center;color: #4682b4;font: bold 18px Verdana, Arial, Helvetica, sans-serif;border-bottom: 2px dashed #E6E8ED;">Mysql Connection Setup</h1>
      <?php
        if($mysqli->connect_error != '')
            echo "<div class='error result' style='background-color: #faa !important; text-align:center;'>".$mysqli->connect_error."</div>\n";
      ?>
      <p style='font-weight:bold;text-align:center;'>Please enter your mysql username, password, and default database name.</p>
      <form method="post" action="<?php echo $_SERVER['PHP_SELF']?>?get_page=-3" name="loginform">
          <table width="300" border="0" align="center" cellpadding="2" cellspacing="0">
          <tr><td><strong>Username</strong></td><td><input name='username' type='text'/></td></tr>
          <tr><td><strong>Password</strong></td><td><input name='password' type='password'/></td></tr>
          <tr><td><strong>Database Name</strong></td><td><input name='database' type='text' value ='<?php echo SITE_DB_NAME ?>'/></td></tr>
          <tr>
            <td>&nbsp;</td>
            <td><input type="submit" name="action" value="Connect" style="width: 142px" /></td>
          </tr>
        </table>

      <hr width="500" style="height: 25px;border-radius: 20px; -moz-border-radius: 20px; border: 2px solid #4682b4;" />
      </form>
      </div>
      </body>
      </html>
      <?php
      die('');
  }


}

?>
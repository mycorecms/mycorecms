<?php
/*
 * Class for handling MySQL database connections + building MySQL queries
    Copyright (C) 2007-2014 MyCoreCMS

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details. <http://www.gnu.org/licenses/>.
 */

class MySQLDatabase {

	protected $dbconnection;
	public $last_error;
	protected $data = array();
	protected $where_condition_operators = array(
	 "=",
	 "!=", "<>",
	 ">",
	 "<",
	 ">=",
	 "<=",
	 "<=>",
	 "LIKE","NOT LIKE",
	 "IS","IS NOT",
	 "IN","NOT IN",
	 );
     protected $select_condition_operators = array("SUM","AVG","MIN","MAX","COUNT");

	// Open up database connection
	public function __construct(&$dbconnection = null) {
		if( ! isset($this->dbconnection) ) {
			if ( isset($dbconnection) ) $this->dbconnection = $dbconnection;
			else {
				$this->dbconnection = new mysqli(SITE_DB_HOST, SITE_DB_USER, SITE_DB_PASS);
                $this->dbconnection->query('CREATE DATABASE IF NOT EXISTS '.SITE_DB_NAME);
				$this->dbconnection->select_db(SITE_DB_NAME);
				if ( mysqli_connect_errno() ||$this->dbconnection->error != '' )
					die("Connection failed:" . mysqli_connect_error().$this->dbconnection->error);
			}
		}
	}
    public function get_db() { return $this->dbconnection; }

	public function escape_string($src) {
	  if(is_array($src)){
	  print_r(debug_backtrace());
      print_r($src);}
		if ( isset($this->dbconnection) )
            return $this->dbconnection->real_escape_string(preg_replace("/[^\x9\xA\xD\x20-\x7F]/", "", html_entity_decode($src,ENT_QUOTES,'UTF-8')));
		else return false;
	}
    public function salt_password($password,$salt){
        return SHA1('Z1a7C31b'.$this->escape_string($password).($salt));
    }

	public function validate_single_field($validation_criteria, $field_name, $field_value) {
		if ( ! is_array($validation_criteria) ) {
			$this->last_error = "Validation criteria missing";
			return false;
		}

		$is_valid = true;

		foreach ( $validation_criteria as $validation_type => $validation_value ) {
			switch ( $validation_type ) {
			case "type":  //Check if there is any data and if the field is required before checking the type
                if ( (isset($validation_criteria[$field_name]["min_length"]) &&( $validation_criteria[$field_name]["min_length"] == 0 ) && ( strlen($field_value) == 0 )) || (!isset($validation_criteria[$field_name]["min_length"]) && strlen($field_value) == 0 )){
					break;
                }
                else if ( ! $this->validate_value_by_type($validation_value, $field_value))  {
					$is_valid = false;
					$this->last_error = ucfirst(str_replace("_"," ",$field_name)).": must be of type $validation_value.";
                    if($validation_value == 'password' )
                        $this->last_error = "Passwords must be 8 characters long and contain at least one number,upper case letter, and lower case letter.";
				}
				break;
			case "min_length"://strlen("") returns nothing!!!
				if ( strlen($field_value) < $validation_value ||  ($validation_value > 0 && $field_value == "") ) {
					$is_valid = false;
                    if($validation_value == 1)
                        $this->last_error = ucfirst(str_replace("_"," ",$field_name)).": is a required field";
                    else
					    $this->last_error = ucfirst(str_replace("_"," ",$field_name)).": has a minimum length of {$validation_value}.";
				}
				break;

			case "max_length":
				if ( strlen($field_value) > $validation_value )  {
					$is_valid = false;
					$this->last_error = ucfirst(str_replace("_"," ",$field_name)).": has a maximum length of {$validation_value}.";
				}
				break;
			}
		}
	
		return $is_valid;
	}
	
	public function validate_value_by_type($value_type, &$value) {
		$is_valid = true;
		switch ( $value_type ) {
		    case "range":
            case "big_integer":
            case "integer":
            case "table_link":
			case "int":
                 if ( ! is_numeric($value) ) $is_valid = false;
            break;
            case "password":
                 if ( ! preg_match(PASSWORD_REGEX,$value) && strlen($value) < 40 ) $is_valid = false;
            break;
            case "checkbox":
			case "boolean":
			case "bool": if ( ! is_bool($value) ) $is_valid = false; break;

			//case "timestamp": if ( strtotime($value) <= 0) $is_valid = false; break;

			case "float":
            case "currency":
			case "double": if ( ! is_numeric($value) ) $is_valid = false; break;

			case "email_address": if ( ! preg_match(EMAIL_ADDRESS_REGEX, $value) ) $is_valid = false; break;

			case "url":
			case "link": if ( ! preg_match(URL_REGEX, $value) ) $is_valid = false; break;
		}
		return $is_valid;
	}

	public function get_data() { return $this->data; }

	public function get_update_query_sets($ignore_fields=null) {
		$tmp = "";
		foreach ( $this->fields as $field_name => $field_attributes ) {
			if ( ( ! isset($ignore_fields) ) || ( ! in_array($field_name, $ignore_fields) ) ) {
				switch ( isset($field_attributes["type"]) ? $field_attributes["type"] : false ) {
					case "int":
					case "integer":
					case "float":
					case "double":
                    case "currency":
                    case "range":
                    case "table_link":
					    $tmp .= " `{$field_name}`={$this->data[$field_name]},\n";
					break;

                    case "checkbox":
                    case "bool":
					case "boolean":
					    $tmp .= " `{$field_name}`=" . ( $this->data[$field_name] ? "true" : "false" ) . ",\n";
					break;
                    case "password":
                        if(!(strlen($this->data[$field_name]) >= 40)) //Check if we already have an SHA1 or if this is a new password
					        $tmp .= " `{$field_name}`='". $this->salt_password($this->data[$field_name],$this->data['login']). "',\n";
					break;
                    case "timestamp":
					    $tmp .= " `{$field_name}`='" .($this->data[$field_name] != ''?date('Y-m-d H:i:s',strtotime($this->data[$field_name])):''). "',\n";
					break;
					default:
					    $tmp .= " `{$field_name}`='" . $this->escape_string(trim($this->data[$field_name])) . "',\n";
					break;
				}
			}
		}
		if ( strlen($tmp) > 0 ) return substr($tmp, 0, -2) . "\n";
		else {
			$this->last_error = "No fields to set";
			return false;
		}
	}

	public function get_insert_query_values($ignore_fields=null) {
		$columns = "";
		$values = "";
		foreach ( $this->fields as $field_name => $field_attributes ) {
			if ( ( ! isset($ignore_fields) ) || ( ! in_array($field_name, $ignore_fields) ) ) {
				$columns .= " `{$field_name}`,\n";
				switch ( isset($field_attributes["type"]) ? $field_attributes["type"] : false ) {
                    case "big_integer":
                    case "integer":
                    case "int":
                    case "table_link":
					    $values .= (int)$this->data[$field_name].",\n";
					break;
                    case "currency":
                    case "double":
                    case "float":
					    $values .= (float)$this->data[$field_name].",\n";
					break;
                    case "range":
                        $values .= "{$this->data[$field_name]},\n";
                    break;
                    case "checkbox":
					case "bool":
					case "boolean":
					    $values .= ( $this->data[$field_name] ? "true" : "false" ) . ",\n";
					break;
                    case "password":
					    $values .= "'" .$this->salt_password($this->data[$field_name],$this->data['login']). "',\n";
					break;
                    case "timestamp":
					    $values .= "'" .($this->data[$field_name] != ''?date('Y-m-d H:i:s',strtotime($this->data[$field_name])):''). "',\n";
					break;
					default:
					    $values .= "'" . $this->escape_string(trim($this->data[$field_name])) . "',\n";
					break;
				}
			}
		}
		if ( strlen($columns) > 0 ) {
			return array("columns" => substr($columns, 0, -2) . "\n", "values" => substr($values, 0, -2) . "\n");
		} else {
			$this->last_error = "No fields to insert";
			return false;
		}
	}

	public function get_select_query_columns() {
		$tmp = "";
		foreach ( $this->fields as $field_name => $field_attributes ) {
            $tmp .= " `{$field_name}`,\n";
		}
		if ( strlen($tmp) > 0 ) return substr($tmp, 0, -2) . "\n";
		else {
			$this->last_error = "No fields to retrieve";
			return false;
		}
    }
    public function get_sum_query_columns() {
		$sql = "";
		foreach ( $this->fields as $field_name => $field_attributes ) {
		  switch ( isset($field_attributes["type"]) ? $field_attributes["type"] : false ) {
                case "range":
				case "integer":
                case "int":
                case "double":
                case "float":
                    if(substr($field_name,-3) != '_id')
					    $sql .= ", FORMAT(SUM(`{$field_name}`),2) as `{$field_name}`";
                break;
                case "currency":
                        //$sql .= ", SUM({$field_name}) as `{$field_name}`";
					    $sql .= ", CONCAT('$',CAST(FORMAT(SUM(`{$field_name}`),2) as CHAR)) as `{$field_name}`";
				break;
            }
		}
		return $sql;
    }
	public function get_where_condition($criterias) {
		if ( ! is_array($criterias) ) {
			$this->last_error = "Criterias must be an array";
			return false;
		}

		$where_condition = "true";

		if ( sizeof($criterias) == 0 ) return $where_condition;

		foreach ( $criterias as $criteria ) {
			if ( ( ! isset($criteria["field"]) ) || ( ! isset($this->fields[$criteria["field"]]) ) ) {
				$this->last_error = "Invalid field \"{$criteria["field"]}\" in where condition criteria\n";
				return false;
			}

			if ( ( ! isset($criteria["operator"]) ) || ( ! in_array($criteria["operator"], $this->where_condition_operators) ) ) {
				$this->last_error = "Invalid operator in where condition criteria\n";
				return false;
			}

			if ( ! isset($criteria["argument"]) ) {
				$this->last_error = "No argument in where condition criteria\n";
				return false;
			}

			if ( $criteria["operator"] == "IN" || $criteria["operator"] == "NOT IN") {
				$argument = "({$criteria['argument']})";
			}
            else {
				$argument = $this->get_argument_by_type($criteria["argument"], isset($this->fields[$criteria["field"]]["type"]) ? $this->fields[$criteria["field"]]["type"] : null);
			}
            if(isset($this->fields[$criteria["field"]]['type']) && $this->fields[$criteria["field"]]['type'] == 'table_link-checkboxes')
                   $criteria['field'] = "CONCAT(',',".$criteria['field'].",',')";


			$where_condition .= " AND\n ( {$criteria['field']} {$criteria['operator']} {$argument} )";

		}

		return "WHERE ( {$where_condition} )";
	}

    public function get_join_condition($joins) {
		if ( ! is_array($join) ) {
			$this->last_error = "Join must be an array";
			return false;
		}

		$where_condition = "true";

		if ( sizeof($criterias) == 0 ) return $where_condition;

		foreach ( $criterias as $criteria ) {
			if ( ! isset($criteria["join"]) ) {
				$this->last_error = "No join in array\n";
				return false;
			}
			$join_condition .= " AND\n ( {$criteria['field']} {$criteria['operator']} {$argument} )";
		}
		return "WHERE ( {$where_condition} )";
	}

	private function get_argument_by_type(&$argument, $field_type = null) {
		switch ( isset($field_type) ? $field_type : false ) {
			case "int":
            case "range":
			case "integer":
            case "table_link":
				$tmp_argument = (int)$argument;
				break;
            case "table_link-checkboxes":
            case "checkbox-list":
				$tmp_argument = "CONCAT('%,',{$argument},',%')";
				break;

			case "float":
            case "currency":
            case "double":
				$tmp_argument = (float)$argument;
				break;

            case "checkbox":
			case "bool":
			case "boolean":
				$tmp_argument = ( (bool)$argument ? "TRUE" : "FALSE" );
				break;

			default:
				$tmp_argument = "'" . $this->escape_string($argument) . "'";
				break;
		}
		return $tmp_argument;
	}
    public function get_type($field_type) {
       $field_types = explode("(",$field_type);
		switch ( isset($field_types[0]) ? $field_types[0] : false ) {
				 case "int":
				 case "smallint":
                 case "mediumint":
					$type_match = 'integer';
					break;
                 case "bigint":
                    $type_match = 'big_integer';
					break;
                 case "timestamp":
                 case "datetime":
                 case "date":
                 case "time":
                    $type_match = 'timestamp';
                 break;
                 case "double":
                 case "float":
                 case "decimal":
					$type_match = 'double';
					break;
                 case "text":
                 case "blob":
                 case "tinytext":
                 case "mediumtext":
                 case "longtext":
                    $type_match = 'textarea';
                    break;
                 case "tinyint":
                 case "bool":
                 case "boolean":
					$type_match = 'checkbox';
					break;
				 default:
					$type_match = 'text';
					break;
		}
		return $type_match;
	}
    public function identify_type($field) {
                 if (is_bool($field))
					$type_match = 'checkbox';
                 else if(is_numeric($field) && !strpos($field,".") && strlen($field) >9)
					$type_match = 'big_integer';
                 else if(is_numeric($field) && !strpos($field,".") && strlen($field) <10)
					$type_match = 'integer';
                 else if(is_numeric($field))
					$type_match = 'double';
                 else if(strtotime($field) && (strtotime($field) > strtotime('1970-01-01')) && strlen($field) > 7 && strpos($field,':') && strpos($field,'-') && date('Y-m-d',strtotime($field))!= date('Y-m-d',strtotime("now")) && date('Y-m-d',strtotime($field))!= date('Y-m-d',strtotime("-1 day")))
                    $type_match = 'timestamp';
                 else if(is_string($field) && strlen($field) >255)
                    $type_match = 'textarea';
                 else if( preg_match(EMAIL_ADDRESS_REGEX, $field))
                    $type_match = "email_address";
    			 else if(preg_match(URL_REGEX, $field))
                    $type_match = "url";
				 else
					$type_match = 'text';
		return $type_match;
	}
    public function max_length($field_type) {
		switch ( isset($field_type) ? $field_type : false ) {
                 case 'big_integer':
					$length = 18; //The signed range is -9223372036854775808 to 9223372036854775807
					break;
                 case 'integer':
					$length = 9; //The signed range is -2147483648 to 2147483647.
					break;
                 case 'double':
					$length = 65;//Permissible values are -1.7976931348623157E+308 to   -2.2250738585072014E-308
					break;
                 case 'checkbox-list':
                 case 'textarea':
                    $length = 6000;
                    break;
                 case 'checkbox':
					$length = 1;
					break;
                 case 'url':
                 case 'file':
					$length = 255;
					break;
				 default:
					$length = 45;
					break;
		}
		return $length;
	}


	public function get_order_by_string($order_bys) {
		if ( ! is_array($order_bys) ) {
			$this->last_error = "order_bys must be an array";
			return false;
		}

		$order_by_string = "";
		foreach ( $order_bys as $order_by ) {
			if ( ( ! isset( $order_by["field"] ) ) || ( ! isset($this->fields[$order_by["field"]]) ) ) {
				$this->last_error = "Invalid field \"{$order_by["field"]}\" in order by\n";
				return false;
			}
			$order_by_string .= ", {$order_by["field"]}" . ( ( ! isset($order_by["ascending"]) ) || ( $order_by["ascending"]) ? " ASC" : " DESC" );
		}
		if ( strlen($order_by_string) > 0 ) return "ORDER BY " . substr($order_by_string, 1);
		else return "";
	}

    public function get_alter_table_sql($old_field_names) {
        if ( ! is_array($old_field_names) ) {
			$this->last_error = "old_field_names must be an array";
			return false;
		}
		$sql_string = "ALTER TABLE `{$this->table_name}` ";
		foreach ( $this->fields as $field_name => $field_attributes )
		      $sql_string .= "CHANGE COLUMN ".(isset($old_field_names[$field_name]) ?"`".$old_field_names[$field_name]."`" : "`{$field_name}` " ).$this->lookup_field_sql($field_name).", ";
		foreach ( $this->fields as $field_name => $field_attributes ){
                      if(isset($field_attributes["unique"]) && $field_attributes["unique"] == TRUE )
		           $sql_string .= " DROP INDEX `".$old_field_names[$field_name]."_UNIQUE`,  ADD UNIQUE KEY `{$field_name}_UNIQUE` (`{$field_name}`) ,";
               }
                      if(isset($old_field_names[$this->primary_key]) && $old_field_names[$this->primary_key] != $this->primary_key ){
                        $sql_string .= " DROP PRIMARY KEY , ADD PRIMARY KEY (`{$this->primary_key}`),DROP INDEX `".$old_field_names[$this->primary_key]."_UNIQUE`,  ADD UNIQUE KEY `{$this->primary_key}_UNIQUE` (`{$this->primary_key}`) ";
                      }
        $sql_string = rtrim($sql_string, ", ");
        return $sql_string;
	}
    public function get_create_table_sql() {
		$sql_string = "CREATE TABLE `{$this->table_name}` (";
		foreach ( $this->fields as $field_name => $field_attributes )
				$sql_string .= $this->lookup_field_sql($field_name).", ";
		foreach ( $this->fields as $field_name => $field_attributes ){
                      if(isset($field_attributes["unique"]) && $field_attributes["unique"] == TRUE )
		           $sql_string .= " UNIQUE KEY `{$field_name}_UNIQUE` (`{$field_name}`) ,";
               }
        $sql_string .= " PRIMARY KEY (`{$this->primary_key}`), UNIQUE KEY `{$this->primary_key}_UNIQUE` (`{$this->primary_key}`) ";
        $sql_string .= " ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1";
        return $sql_string;
	}
    //Index any fields that might link to other tables
    public function get_create_index_sql(){
      $sql_string = "ALTER TABLE `{$this->table_name}` ";
      foreach ( $this->fields as $field_name => $field_attributes ) {
		  switch ( isset($field_attributes["type"]) ? $field_attributes["type"] : false ) {
                case "table_link-checkboxes":
                case "distinct_list":
                case "table_link":   //check if the index already exists
                $result = $this->dbconnection->query("select count(1) cnt FROM INFORMATION_SCHEMA.STATISTICS WHERE table_name = '{$this->table_name}' and index_name = '{$field_name}'")->fetch_assoc();
                if ( $result['cnt'] ==0)
					    $sql_string .= " ADD INDEX `{$field_name}`(`{$field_name}` ASC),";
                break;
            }
		}
      return rtrim($sql_string,",");
    }
    public function lookup_field_sql($field) {
          $sql_string = " `{$field}` ";
		  switch ( isset($this->fields[$field]["type"]) ? $this->fields[$field]["type"] : "text" ) {
				 case "int":
                 case "range":
				 case "integer":
                 case "table_link":
					$sql_string .= " INT ".($field == $this->primary_key? "NOT NULL auto_increment":(isset($this->fields[$field]['default'])? "default ".$this->fields[$field]['default'] :"default 0"));
					break;
                 case "timestamp":
                    $sql_string .= " timestamp NULL default 0 ";
                 break;
                 case "currency":
				 case "float":
                 case "double":
					$sql_string .= " double ".(isset($this->fields[$field]['default'])? "default ".$this->fields[$field]['default'] :"default 0");
					break;
                 case 'checkbox-list':
                 case "textarea":
                    $sql_string .= " TEXT NULL ";
                    break;
                 case "checkbox":
				 case "bool":
				 case "boolean":
					$sql_string .= " TINYINT(1) NULL ".(isset($this->fields[$field]['default'])? "default 1" :"default 0");
					break;
                 case "file":
					$sql_string .= " varchar(255) NULL ";
					break;
				 default:
					$sql_string .= " varchar(".(isset($this->fields[$field]['max_length']) && $this->fields[$field]['max_length'] <= 255? $this->fields[$field]['max_length'] :"45").") NULL default '".(isset($this->fields[$field]['default'])? $this->fields[$field]['default'] :"")."'";
					break;
	    }
        return $sql_string;
	}

	public function assign_data_from_array(&$row) {
		if ( ! is_array($row) ) {
			$this->last_error = "Row is not an array";
			return false;
		}


		foreach ( $this->fields as $field_name => $field_attributes ) {
			if ( isset($row[$field_name]) ) {
				$field_value = $row[$field_name];
				switch ( isset($field_attributes["type"]) ? $field_attributes["type"] : false ) {
				 case "int":
                 case "range":
				 case "integer":
                 case "table_link":
					$this->data[$field_name] = (int)$field_value;
					break;
                 case "timestamp":
                    $this->data[$field_name] =  ( strtotime($field_value) > 0 ? date('Y-m-d', strtotime($field_value)) : "0000-00-00");
                 break;
				 case "float":
                 case "currency":
                 case "double":
					$this->data[$field_name] = (float)$field_value;
					break;

                 case "checkbox":
				 case "bool":
				 case "boolean":
					$this->data[$field_name] = (bool)$field_value;
					break;
				 default:                                                                                 //htmlentities(html_entity_decode(stripslashes ($field_value),ENT_QUOTES,'UTF-8'),ENT_QUOTES,'UTF-8')
					$this->data[$field_name] = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', htmlentities(stripslashes ($field_value),ENT_QUOTES,'UTF-8'));
					break;
				}
			}
		}
		return true;
	}

}
?>
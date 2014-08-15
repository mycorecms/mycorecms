<?php
/*
 * Class for executing MySQL and storing table structure information
    Copyright (C) 2007-2014 MyCoreCMS

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details. <http://www.gnu.org/licenses/>.
 */
require_once "mysql_database.php";

class MySQLClass extends MySQLDatabase {
	protected $table_name = "table_class";
	protected $primary_key = "table_id";
	public $fields = array(
	 "table_id" => array("type" => "int"),
	 "name" => array("min_length" => 1, "max_length" => 45),
	 );
	 public $requirement_check;
    //Create the Mysql DB connection
	public function __construct(&$dbconnection = null,&$vars = null, $table = null, $primary_key = null,$schema = null ) {
	   	if( ! isset($this->dbconnection) ) {
			if ( isset($dbconnection) ) $this->dbconnection = $dbconnection;
			else {
				$this->dbconnection = new mysqli(SITE_DB_HOST, SITE_DB_USER, SITE_DB_PASS);
				$this->dbconnection->select_db((isset($schema)?$schema:SITE_DB_NAME));
				if ( mysqli_connect_errno() )
					die("Connection failed:" . mysqli_connect_error());
			}
		}//Assign variables on creation
       if($vars != null && $primary_key != null && $table != null){
            $this->fields = $vars;
            $this->table_name = $table;
            $this->primary_key = $primary_key;
       }
       $this->clear();
       $this->requirement_check = true;
    }
    //Closing the mysql connection can cause conflicts, so we leave blank
	public function __destruct() {  }
    //Over-ride Set
	public function __set($name, $value) {
		if ( ( ! isset($this->fields[$name]) ) ) {
			$this->last_error = "$name does not exist.";
			return false;
		}
		if($this->requirement_check){
		       if ( ! $this->validate_single_field($this->fields[$name], $name, $value)  ) return false;
		}
		$this->data[$name] = $value;
		return true;
	}
    //Over-ride Get
	public function __get($name) {
		if ( ( ! isset($this->fields[$name]) ) ) {
			$this->last_error = "$name does not exist.";
			return false;
		}
		return $this->data[$name];
	}
    //Over-ride isset
	public function __isset($name) {
		if ( ( ! isset($this->fields[$name]) ) ) {
			$this->last_error = "$name does not exist.";
			return false;
		}

		return isset($this->data[$name]);
	}
    //Set a field to null, mainly for use with primary ids
    public function set_null($name) {
		if ( ( ! isset($this->fields[$name]) ) ) {
			$this->last_error = "$name does not exist.";
			return false;
		}
		$this->data[$name] = NULL;
		return true;
	}
    //Resets all variables to make sure there aren't conflicts when loading a new instance
	public function clear() {
          if(!isset($this->data))
		$this->data = array();
		foreach ( $this->fields as $field_name => $field_attributes ) {
			$this->data[$field_name] = ( isset($field_attributes["default"]) ? $field_attributes["default"] : null );
		}
	}
	// Add or Update
	public function save() {
                //Check if the table exists, if not create it
		if($this->dbconnection->query("SELECT * FROM {$this->table_name} LIMIT 1") === false)
                      $this->update_table();

        $new_record = false;
        $auto_increment = true;
        if($this->data[$this->primary_key] > 0 OR strlen($this->data[$this->primary_key]) >2){ // Check if the ID has been assigned and it is not currently in the database
                $record_check = $this->get_sql("SELECT * FROM {$this->table_name} WHERE {$this->primary_key} = '{$this->data[$this->primary_key]}'");
                if(!isset($record_check[0])){
                    $new_record = true;
                    $auto_increment = false;
                }
        }
        else
             $new_record = true;

        if($this->requirement_check){
                // Validate fields
		foreach ( $this->fields as $field_name => $field_attributes ) {
			if ( ($field_name != $this->primary_key || !$new_record) && !$this->validate_single_field($field_attributes, $field_name, $this->data[$field_name]))
				return false;
		};
	}



        //Check if this is an Add
		if ( $new_record ) {
			if ( ! ( $tmp = $this->get_insert_query_values(($auto_increment?array($this->primary_key):null)) ) ) return false;
			$sql_query = "INSERT INTO {$this->table_name}\n ({$tmp['columns']})\n VALUES ({$tmp['values']});\n";
            //echo $sql_query."<br />";                             //If we have an error, update table & re-run query
			if ( $this->dbconnection->query($sql_query) === false && $this->update_table()  && $this->dbconnection->query($sql_query) === false ) {
				$this->last_error = "Invalid Insert: ".(stristr($this->dbconnection->error,'Duplicate Entry')?'Duplicate Entry': $this->dbconnection->error)."\n";
                                return false;
			} else {
				$this->data[$this->primary_key] = $this->dbconnection->insert_id;
				return true;
			}
		} //Perform an Update
        else {
			if ( ! ( $tmp = $this->get_update_query_sets(array($this->primary_key)) ) ) return false;
			$sql_query = "UPDATE {$this->table_name}\n SET\n" .$tmp ."WHERE {$this->primary_key}={$this->data[$this->primary_key]}";
                                                            //If we have an error, update table & re-run query
			if ( $this->dbconnection->query($sql_query) === false && $this->update_table() && $this->dbconnection->query($sql_query) === false ) {
				$this->last_error = "Invalid Update: ".(stristr($this->dbconnection->error,'Duplicate Entry')?'Duplicate Entry': $this->dbconnection->error)."\n";
				return false;
			} else return true;
		}
	}

	public function delete() {
		$field_name = $this->primary_key;
		if ( ! $this->validate_single_field($this->fields[$field_name], $field_name, $this->data[$field_name])) {
			$last_error = "Invalid {$this->primary_key}";
			return false;
		}

		$sql_query = "DELETE FROM `{$this->table_name}` WHERE `{$this->primary_key}`={$this->data[$field_name]}";
		if ( $this->dbconnection->query($sql_query) === false ) {
			$this->last_error = "Invalid query: {$this->dbconnection->error}\n";
			return false;
		} else return true;
	}
    //Load up a row based on the primary id
	public function load() {
         //Check if a primary id is set
		 if ( isset($this->data[$this->primary_key]) ){
			$field_name = $this->primary_key;
			if ( ! $this->validate_single_field($this->fields[$field_name], $field_name, $this->data[$field_name])) {
				$last_error = "Invalid {$field_name}";
				return false;
			}

			if ( ! ( $tmp = $this->get_select_query_columns() ) ) return false;
			$sql_query =
			"SELECT\n" .$tmp ."FROM {$this->table_name}\n WHERE `{$field_name}`={$this->data[$field_name]}";
		}
		else{
			$this->last_error = "No field used to load record.";
            return false;
        }
        //Check for errors performing select query
		if ( ( $result = $this->dbconnection->query($sql_query) ) === false && $this->update_table() && ($result = $this->dbconnection->query($sql_query)) === false ) {
           //If we have an error, update the table and retry
            $this->last_error = "Invalid query: {$this->dbconnection->error}";
			return false;
		}
        //If nothing is found return false
		if ( ! ( $row = $result->fetch_assoc() ) ) {
			//$result->close();
			$this->last_error = "Not found";
			return false;
		} else {
			//$result->close();
			return $this->assign_data_from_array($row);
		}
	}

    public function delete_field($field_name) {
      if ( !isset($field_name)) {
			$this->last_error = "Must specify a field";
			return false;
		}
         if ( ( $result = $this->dbconnection->query("ALTER TABLE `{$this->table_name}` DROP COLUMN `{$field_name}`") ) === false ) {
			$this->last_error = "Invalid query: {$this->dbconnection->error}";
			return false;
		}
        return true;
	}
    public function add_field($field_name) {
      if ( !isset($field_name)) {
			$this->last_error = "Must specify a field";
			return false;
		}
         if ( ( $result = $this->dbconnection->query("ALTER TABLE `{$this->table_name}` ADD COLUMN ".$this->lookup_field_sql($field_name)) ) === false ) {
			$this->last_error = "Invalid query: {$this->dbconnection->error}";
			return false;
		}
        return true;
	}
    public function delete_table() {
         if ( ( $result = $this->dbconnection->query("DROP TABLE `{$this->table_name}`") ) === false ) {
			$this->last_error = "Invalid query: {$this->dbconnection->error}";
			return false;
		}
        return true;
	}
    public function change_table_name($new_table_name){
      //Change the table name and the primary id based on the table name
      if ( ( $result = $this->dbconnection->query("ALTER TABLE `{$this->table_name}` RENAME TO `{$new_table_name}`") ) === false ) {
			$this->last_error ="Invalid query: {$this->dbconnection->error}"."ALTER TABLE `{$this->table_name}` RENAME TO `{$new_table_name}`";
			return false;
	  }
      else
            return true;
    }
    //Make sure coded DB design matches the actual DB
	public function update_table($old_field_names=NULL) {
         //echo $this->dbconnection->error." update {$this->table_name}<br />";
         //Check if the table we are using exists, if not create it!
         if ( ( $result = $this->dbconnection->query("SHOW TABLES like '{$this->table_name}'") ) === false ) {
		 	$this->last_error = "Invalid query: {$this->dbconnection->error}";
			return false;
		}
        if($result->num_rows == 0){  //Table does not exists, create it!
            //$result->close();
            if($execute_sql = $this->get_create_table_sql()){
                if ( ( $result = $this->dbconnection->query($execute_sql) ) === false ) {
    			    $this->last_error = "Invalid query: {$this->dbconnection->error}".$execute_sql;
                    return false;
    		    }
    		//$result->close(); 
                $this->index_table();
                return true;
            }
            else
    			return false;
        }
         //$result->close();
        //Check if the coded table matches the MySql table if not alter it!
        if(isset($old_field_names)){
          if($execute_sql = $this->get_alter_table_sql($old_field_names)){
                if ( ( $result = $this->dbconnection->query($execute_sql) ) === false ) {
                    //Check if we have a missing column, if so add it and redo the query
                    while(preg_match("/^Unknown column '(?P<field>([^'])*)'/",$this->dbconnection->error,$matches)){
                        $this->add_field($matches['field']);
                        //reexecute sql after adding field
                        
                        if ( ( $result = $this->dbconnection->query($execute_sql) ) != false )
                            return true;
                    };
                    $this->last_error = "Invalid query: {$this->dbconnection->error} $execute_sql";
    			    return false;
    		    }
                $this->index_table(); //reindex the table
            }
            else
    			return false;
        }
        //Make sure all columns are in the table
		if ( ( $result = $this->dbconnection->query("SELECT " . $this->get_select_query_columns(). "FROM {$this->table_name} LIMIT 1") ) === false ) {
		            //Check if we have a missing column, if so add it and redo the query
                    while(preg_match("/^Unknown column '(?P<field>([^'])*)'/",$this->dbconnection->error,$matches)){
                        if(!$this->add_field($matches['field']))
                            return false; //In case we have an error adding, break the loop
                        //reexecute sql after adding field
                        if ( ( $result = $this->dbconnection->query("SELECT " . $this->get_select_query_columns(). "FROM {$this->table_name} LIMIT 1") ) != false ){
                            $this->index_table(); //reindex the table
                            return true;
                        }
                    };
			$this->last_error = "Invalid query: {$this->dbconnection->error}";
			return false;
		}
		return true;

	}
    public function index_table(){
       //Create table indexs for performance on table links
		if ( ( $result = $this->dbconnection->query($this->get_create_index_sql()) ) === false ) {
	   		$this->last_error = "Invalid query: {$this->dbconnection->error}".$this->get_create_index_sql();
			return false;
	   	}
        return true;
    }
    public function lookup_tables($db_name=SITE_DB_NAME,$compare_tables=NULL) {
		if ( ( $result = $this->dbconnection->query("SHOW FULL TABLES FROM {$db_name} WHERE table_type != 'VIEW' ".(isset($compare_tables)?" AND tables_in_".$db_name." NOT IN ({$compare_tables})":"")) ) === false ) {
			$this->last_error = "Invalid query: {$this->dbconnection->error}";
			return false;
		}
		$results = array();
        //Build results array and return
        while ( $row = $result->fetch_assoc() ) {
                $results[] = $row['Tables_in_'.$db_name];
        }
        //$result->close();
    	return( $results );
	}
    public function get_columns($table_name,$db_name=SITE_DB_NAME) {
		if ( ( $result = $this->dbconnection->query("SHOW COLUMNS FROM `{$db_name}`.`{$table_name}`") ) === false ) {
			$this->last_error = "Invalid query: {$this->dbconnection->error}";
			return false;
		}
		$results = array();
        //Build results array and return
        while ( $row = $result->fetch_assoc() ) {
            $results[] = $row;
        }
        //$result->close();
    	return( $results );

	}
    // Returns an array of all items
	public function get_all($criterias = null, $order_bys = null, $limit = null, $select_criteria = null) {

		$sql_query = "SELECT " . $this->get_select_query_columns($select_criteria) . "FROM {$this->table_name}";

		if ( isset($criterias) && is_array($criterias) && ( sizeof($criterias) > 0 ) ) {
			if ( $where_condition = $this->get_where_condition($criterias) ) $sql_query .= "\n{$where_condition}";
			else return false;
		}

		if ( isset($order_bys) && is_array($order_bys) && ( sizeof($order_bys) > 0 ) ) {
			if ( $order_by_string = $this->get_order_by_string($order_bys) ) $sql_query .= "\n{$order_by_string}";
			else return false;
		}

        if( isset($limit) && $limit != "")
            $sql_query .= "\n {$limit}";
                                                                                //If there is an error, update table and re-reun query
		if ( ( $result = $this->dbconnection->query($sql_query) ) === false && $this->update_table() && ( $result = $this->dbconnection->query($sql_query) ) === false) {
			$this->last_error = "Invalid query: {$this->dbconnection->error}";
			return false;
		}

		$results = array();

		while ( $row = $result->fetch_assoc() ) {
			$tmp = new MysqlClass($this->dbconnection,$this->fields, $this->table_name, $this->primary_key);
			if ( $tmp->assign_data_from_array($row) ) {
				$results[$tmp->__get($this->primary_key)] = $tmp;
			}
		}

		//$result->close();
		return( $results );
	}
	public function get_counts($criterias = null) {
		$sql_query = "SELECT COUNT(*) as cnt " .$this->get_sum_query_columns()." FROM {$this->table_name} ";

		if ( isset($criterias) && is_array($criterias) && ( sizeof($criterias) > 0 ) ) {
			if ( $where_condition = $this->get_where_condition($criterias) ) $sql_query .= "\n{$where_condition}";
			else return false;
		}

		if ( ( $result = $this->dbconnection->query($sql_query) ) === false ) {
			$this->last_error = "Invalid query: {$this->dbconnection->error}";
			return false;
		}
		$row = $result->fetch_assoc();
		//$result->close();
		return( $row );
	}
    public function get_sql_count($sql_query) {
        //Make sure there is a select statement
        if($sql_query != ''){
        if(stripos($sql_query,'SELECT')===false){
            if (  ( $result = $this->dbconnection->query($sql_query) ) === false ) {
			$this->last_error = "Invalid query: {$this->dbconnection->error}<br />".$sql_query;
                        return false;
		}
             $count = $result->num_rows;
             //$result->close();
            return $count;
        }

        // Replace 1st instance of SELECT with COUNT(*)
        $left_seg = substr($sql_query, 0, stripos($sql_query, 'SELECT'));
        $right_seg = substr($sql_query, (stripos($sql_query, 'SELECT') + strlen('SELECT')));
        $sql_query = $left_seg . 'SELECT SQL_CALC_FOUND_ROWS ' . $right_seg. " LIMIT 1";
        //
		if ( ( $result = $this->dbconnection->query($sql_query) ) === false ) {
			$this->last_error = "Invalid query: {$this->dbconnection->error}<br />".$sql_query;
                        return false;
		}
        if ( ( $result = $this->dbconnection->query('SELECT FOUND_ROWS()') ) === false ) {
			$this->last_error = "Invalid query: SELECT FOUND_ROWS()";
                        return false;
		}
		$row = $result->fetch_assoc();
		//$result->close();
        //Return the count
		return( $row['FOUND_ROWS()'] );
	}
    }
    public function get_sql($sql_query) {
		if ( ( $result = $this->dbconnection->query($sql_query) ) === false ) {
			$this->last_error = "Invalid query: {$this->dbconnection->error}";
			return false;
		}
		$results = array();
        //Build results array and return
        while ( $row = $result->fetch_assoc() ) {
            $results[] = $row;
        }
        //$result->close();
    	return( $results );

	}
    public function get_sql_fields($sql_query) {
		if ( ( $result = $this->dbconnection->query($sql_query) ) === false ) {
			$this->last_error = "Invalid query: {$this->dbconnection->error}";
			return false;
		}
		$results = array();
        //Build results array and return
        while ( $row = $result->fetch_field() ) {
            $results[] = $row->name;
        }
        //$result->close();
    	return( $results );

	}
    public function set_sql($sql_query) {
                $result = $this->dbconnection->query($sql_query);
		if ( ( $result ) === false ) {
			$this->last_error = "Invalid query: {$this->dbconnection->error}<br />".$sql_query;
			return false;
		}
		//else
                //      $this->last_error =   "Affected Rows:".$this->dbconnection->affected_rows.".".$this->dbconnection->num_rows;

		return true;
	}
	public function get_distinct($field_name, $criterias = null, $order_bys = null) {
		$sql_query =
		 "SELECT DISTINCT " . $field_name .
		 " FROM {$this->table_name}";

		if ( isset($criterias) && is_array($criterias) && ( sizeof($criterias) > 0 ) ) {
			if ( $where_condition = $this->get_where_condition($criterias) ) $sql_query .= "\n{$where_condition}";
			else return false;
		}

		if ( isset($order_bys) && is_array($order_bys) && ( sizeof($order_bys) > 0 ) ) {
			if ( $order_by_string = $this->get_order_by_string($order_bys) ) $sql_query .= "\n{$order_by_string}";
			else return false;
		}
		if ( ( $result = $this->dbconnection->query($sql_query) ) === false ) {
			$this->last_error = "Invalid query: {$this->dbconnection->error}";
			return false;
		}

		$results = array();
		while ( $row = $result->fetch_assoc() ) {
				$results[] = array("{$field_name}" => $row["{$field_name}"]);
		}

		//$result->close();
		return( $results );
	}

	public function lookup_table_name() { return $this->table_name; }

    public function get_last_id() { return $this->dbconnection->insert_id; }

	public function get_primary_key() { return $this->primary_key; }

	public function lookup_fields() { return $this->fields; }

}
?>
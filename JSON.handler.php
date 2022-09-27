<?php

/* simulation of a table handler with json and php */

Class JSONStorage {
  
    /*@outputs
    *
    * 400 means an error or not sucessful result
    * 200 means operation executed successfully
    * responses contain underscores as spaces
    *
    */
  
    # when instantiating set route where files will live
    public static $Root = null;
    # constructor serves the main folder
    public function __construct($route) {
        self::$Root = $route;
    }
    # self::Res handles response status
    public static function Res($status, $data=null) {
        $response = array('status' => $status, 'response' => $data);
        return json_encode($response);
    }
    # turns array into objects
    public static function Object($data, $server=false) {
        return ($server===false) ? json_decode(json_encode($data)) 
                                 : json_decode($_POST[$data]);
    }
    # table refers to the file created
    public function CreateTable($table=null) {
        # create filename with global set route
        $filename = self::$Root . $table . '.json';
        # check if file exists
        if(file_exists($filename)) {
            # if it exists return 400
            return self::Res(400, ['error' => 'table_exists']);
            exit();
        }
        # set empty array
        $tableContent = [];
        # update and create file with empty array
        file_put_contents($filename, json_encode($tableContent));
        # returns result if needed
        return self::Res(200, ['file' => $filename, 'table' => $table, 'content' => []]);
    }
    # obtain "table" or json document
    public static function GetTable($table=null) {
        if($table === null) {
            self::Res(400, ['error' => 'undeclared_table']);
            exit();
        }
        $filename = self::$Root . $table . '.json';
        # check if file exists
        if(!file_exists($filename)) {
            # if not exists return error
            return self::Res(400, ['error' => 'table_not_found']);
            exit();
        }
        # else get content and json decode it
        $tableContent = json_decode(file_get_contents($filename));
        # return response with file params
        return self::Res(200, ['table' => $table, 'filename' => $filename, 'content' => $tableContent]);
    }
    # get keys from object array
    public static function ObjectKeys($array) {
        $Keys = [];
        foreach($array as $key => $value) {
            $Keys[] = $key;
        }
        # returns first
        return $Keys[0];
    }
    # find row by key
    public function FindByKey($name, $data, $server=false) {
        $CheckTable = json_decode(self::GetTable($name));
        if($CheckTable->status === 400) {
            return self::Res(400, $CheckTable->response);
            exit();
        }
        $Table = $CheckTable->response->content;
        # convert data array to object
        $keyValuePair = self::Object($data, $server); 
        # extract keys from array
        $key = self::ObjectKeys($keyValuePair);
        # set findings array
        $findings = [];
        # extract key value pair
        $value = $keyValuePair->{$key};
        # iterate over columns to find
        for($i = 0; $i < count($Table); $i++) {
            # check if exists and return findings
            if(isset($Table[$i]->{$key}) && strtolower($Table[$i]->{$key}) === strtolower($value)) {
                $findings[] = $Table[$i];
            }
        }
        return self::Res(200, ['findings' => $findings]);
    }
    # find row by id - default method
    public function FindRow($tablename, $id, $server=false) {
        $CheckTable = json_decode(self::GetTable($tablename));
        if($CheckTable->status === 400) {
            return self::Res(400, $CheckTable->response);
            exit();
        }
        # get table content
        $Table = $CheckTable->response->content;
        # check for row existance
        $Row = json_decode(self::RowExists($Table, $id));
        # if not exists return
        if($Row->status == 400) {
            return self::Res(400, ['Row_Not_Exists']);
            exit();
        }
        # now return row content
        return self::Res(200, $Row->response->row);
    }
    public static function RowExists($table, $arr=null, $server=false) {
        # convert data array to obj
        $data = self::Object($arr, $server); 
        # search whats needed
        $SearchResult = array_search($data->id, array_column($table, '_id'));
        # check if result is empty
        if($SearchResult === false) {
            return self::Res(400, ['error' => 'Row_Not_Found']);
            exit();
        }
        # else return row values and also index for further use
        return self::Res(200, ['row' => $table[$SearchResult], 'index' => $SearchResult]);
    }
    # update row keys or create new ones
    public function UpdateRow($object, $server=false) {
        # convert array to object
        $data = self::Object($object, $server);
        # check if file exists
        $TableCheck = json_decode(self::GetTable($data->table));
        # if status is false return
        if($TableCheck->status === 400) {
            return self::Res(400, $TableCheck->response->error);
            exit();
        }
        # else retrieve data from file
        $Table = $TableCheck->response->content;
        # check if row exists in table by id
        $Row = json_decode(self::RowExists($Table, ['id' => $data->id]));
        # if not exists return
        if($Row->status === 400) {
            # add status response
            return self::Res(400, $Row->response);
            exit();
        }
        # else retrieve table row
        $TableRow = $Row->response->row;
        # iteratate them to update keys
        foreach($data->values as $key => $value) {
            # update and create new keys if needed
            if(is_numeric($value)) {
              $TableRow->{$key} = $value;
            } else {
              $TableRow->{$key} = htmlentities($value, ENT_QUOTES, 'UTF-8');
            }
        }
        # update table with new tablerow
        $Table[$Row->response->index] = $TableRow;
        # update file with new table data
        $Response = self::SecureUpdate($TableCheck->response->filename, $Table);
        # response is not a json response
        if($Response['status'] === 400) {
            return self::Res(400, ['error' => 'update_error']);
            exit();
        }
        # return success and row data
        return self::Res(200, ['row' => $TableRow]);
    }
    /* multiple inserts in one function */
    public function MultipleInserts($object, $server=false) {
        # jsonify data
        $data = self::Object($object, $server);
        # check if table exists
        $TableCheck = json_decode(self::GetTable($data->table));
        # if status is false return
        if($TableCheck->status === 400) {
            return self::Res(400, $TableCheck->response->error);
            exit();
        }
        # else retrieve data from table
        $Table = $TableCheck->response->content;
        # create inserted var
        $InsertedRows = [];
        # we will loop through the array of values
        for($i= 0; $i < count($data->values); $i++) {
            # generate an id per row of values
            $id = md5($i . uniqid('', true));
            # store it in var
            $Row = $data->values[$i];
            # iterate over values
            foreach($Row as $key => $value) {
                if(is_numeric($value)) {
                    $Row->{$key} = $value;
                } else {
                    $Row->{$key} = htmlentities($value, ENT_QUOTES, 'UTF-8');
                }
            }
            # add the id
            $Row->{'_id'} = $id;
            # append to table
            $Table[] = $Row;
            # capture inserted key value pairs
            $InsertedRows[] = $Row;
        }
        # update file with content
        $Response = self::SecureUpdate($TableCheck->response->filename, $Table);
        # response is not a json response
        if($Response['status'] === 400) {
            return self::Res(400, ['error' => 'update_error']);
            exit();
        }
        # attach data to success response
        return self::Res(200, $InsertedRows);
    }
    /* InsertRow({table: table_name, values: {object}}) */
    public function InsertRow($object, $server=false) {
        # jsonify data
        $data = self::Object($object, $server);
        # check if table exists
        $TableCheck = json_decode(self::GetTable($data->table));
        // if status is false return
        if($TableCheck->status === 400) {
            return self::Res(400, $TableCheck->response->error);
            exit();
        }
        # else retrieve data from table
        $Table = $TableCheck->response->content;
        # generate row id
        $id = md5(uniqid('', true));
        # create row variable
        $Row = $data->values;
        # iteration of values to clense data before storing
        foreach($Row as $key => $value) {
            # update and create new cols if needed and clean
            if(is_numeric($value)) {
                $Row->{$key} = $value;
            } else {
                $Row->{$key} = htmlentities($value, ENT_QUOTES, 'UTF-8');
            }
        }
        # push values into id row
        $Row->{'_id'} = $id;
        # push Row into tale
        $Table[] = $Row;
        # update table with new content
        $Response = self::SecureUpdate($TableCheck->response->filename, $Table);
        # response is not a json response
        if($Response['status'] === 400) {
            return self::Res(400, ['error' => 'update_error']);
            exit();
        }
        # attach datos 
        return self::Res(200, ['row' => $Row]);
    }
    public function DeleteRowKey($object, $server=false) {
        # jsonify data
        $data = self::Object($object);
        # check if table exists
        $TableCheck = json_decode(self::GetTable($data->table));
        # if status is false return
        if($TableCheck->status == 400) {
            return self::Res(400, $TableCheck->response->error);
            exit();
        }
        # else retrieve data from table
        $Table = $TableCheck->response->content;
        # check if row exists in table by id
        $Row = json_decode(self::RowExists($Table, ['id' => $data->id]));
        # if not exists return
        if($Row->status == 400) {
            // add status response
            return self::Res(400, $Row->response->error);
            exit();
        }
        # else retrieve table row
        $TableRow = $Row->response->row;
        # check of object key exists
        if(!isset($TableRow->{$data->key})) {
            return self::Res(400, ['error' => 'key_not_exists']);
            exit();
        }
        # delete table key
        unset($TableRow->{$data->key});
        # update table
        $Table[$Row->response->index] = $TableRow;
        # update table securily
        $Response = self::SecureUpdate($TableCheck->response->filename, $Table);
        # response is not a json response
        if($Response['status'] === 400) {
            return self::Res(400, ['error' => 'update_error']);
            exit();
        }
        # return sucess and row
        return self::Res(200, ['row' => $TableRow]);
        
    }
    public function DeleteRow($object, $server=false) {
        # jsonify data
        $data = self::Object($object);
        # check if table exists
        $TableCheck = json_decode(self::GetTable($data->table));
        # if status is false return
        if($TableCheck->status == 400) {
            return self::Res(400, $TableCheck->response->error);
            exit();
        }
        # else retrieve data from table
        $Table = $TableCheck->response->content;
        # check if row exists in table by id
        $Row = json_decode(self::RowExists($Table, ['id' => $data->id]));
        # if not exists return
        if($Row->status == 400) {
            # add status response
            return self::Res(400, $Row->response->error);
            exit();
        }
        # remove row from array
        array_splice($Table, $Row->response->index, 1);
        # update table and prevent null response
        $Response = self::SecureUpdate($TableCheck->response->filename, $Table);
        # response is not a json response
        if($Response['status'] === 400) {
            return self::Res(400, ['error' => 'update_error']);
            exit();
        }
        # return success and delted row
        return self::Res(200, ['row_deleted' => $Row->response->row]);
    }
    public function DeleteTable($table) {
        # check if table exists
        $TableCheck = json_decode(self::GetTable($table));
        # if status is false return
        if($TableCheck->status == 400) {
            return self::Res(400, $TableCheck->response->error);
            exit();
        }
        # remove file dor table data
        unlink($TableCheck->response->filename);
        # set response tu 200
        return self::Res(200, ['response' => 'table_deleted']);
    }
    public static function SecureUpdate($file, $table) {
        # if table is null return
        if($table === null) {
            return ['status' => 400];
            exit();
        }
        # check if file_exists
        if(!file_exists($file)) {
            return ['status' => 400];
            exit();
        }
        # proceed to save data
        file_put_contents($file, json_encode($table));
        return ['status' => 200];
    }
    public function TruncateTable($table) {
        # check if table exists
        $TableCheck = json_decode(self::GetTable($table));
        # if status is false return
        if($TableCheck->status == 400) {
            return self::Res(400, $TableCheck->response->error);
            exit();
        }
        # update table
        $Response = self::SecureUpdate($TableCheck->response->filename, []);
        # response is not a json response
        if($Response['status'] === 400) {
            return self::Res(400, ['error' => 'update_error']);
            exit();
        }
        # return success and response as 'table_truncated'
        return self::Res(200, ['response' => 'table_truncated']);
    }
}
?>

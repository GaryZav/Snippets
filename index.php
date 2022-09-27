<?php

# require JSON.handler.php
require_once 'JSON.handler.php';
# set path where files will live
$Path = 'public_json_files/';
# instantiate class and set Path
$JSON = new JSONStorage($Path);

/*
* examples of class methods use
*/

# creating a json file or as we call it "table"
echo $JSON->CreateTable('my-table');

# insert takes an array
echo $JSON->InsertRow([
    'table' => 'my-table',
    'values' => [
        'name' => 'John',
        'lastname' => 'Doe',
        'phone' => 99999999
    ]
]);

# insert multiple rows
echo $JSON->InsertMultipleRows([
    'table' => 'my-table',
    'values' => [
        [
          'name' => 'John', 
          'lastname' => 'Doe',
          'phone' => 999999999
        ],
        [
          'name' => 'Jane',
          'lastname' => 'Doe',
          'phone' => 777777777
        ]
    ]
]);

# find row by key takes two params table and row id
echo $JSON->FindRow('my-table', 'id');

/* 
* update one row at a time - not multiple 
* new data can be added as it is a json file
* row ids can be obtained with GetTable method
*/
echo $JSON->UpdateRow([
    'table' => 'my-table',
    'id' => 'id',
    'values' => [
        'name' => 'Johnny',
        'gender' => 'm'
    ]
]);

?>

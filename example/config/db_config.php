<?php
return array(
    'dsn'      => 'mysql:host=localhost;dbname=test',
    'username' => 'root',
    'password' => 'tommas',
    'options'  => array(
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
    ),
);

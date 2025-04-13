<?php

$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

$host = 'localhost';
$dbname = $config['db_name'];
$username = $config['db_user'];
$password = $config['db_pass'];

$mysqli = new mysqli($host, $username, $password, $dbname);

if ($mysqli->connect_errno) {
  die("Connection failed: " . $mysqli->connect_error);
}

$mysqli->set_charset('utf8mb4');

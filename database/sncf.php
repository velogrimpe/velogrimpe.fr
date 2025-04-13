<?php

$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

$host = 'localhost';
$dbname = $config['sncf_db_name'];
$username = $config['sncf_db_user'];
$password = $config['sncf_db_pass'];

$mysqli = new mysqli($host, $username, $password, $dbname);

if ($mysqli->connect_errno) {
  die("Connection failed: " . $mysqli->connect_error);
}

$mysqli->set_charset('utf8mb4');

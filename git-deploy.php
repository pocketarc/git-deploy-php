<?php

if (isset($argv[1])) {
  $deploy = $argv[1];
} else {
  $deploy = 'deploy.ini';
}

if (!@file_exists($deploy)) {
    trigger_error("File '$deploy' does not exist.");
} else {
    $servers = parse_ini_file($deploy); 
    if (!$servers) {
        trigger_error("File '$deploy' is not a valid .ini file.");
    }
}

$revision = exec('git rev-parse HEAD'); 
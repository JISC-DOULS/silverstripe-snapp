<?php
/**
 * This file exists to get around sams protection for the webservice
 * As this opens up the system to access it must ensure
 * that only webservices are requested
 */

if (strpos($_SERVER['PATH_INFO'], '/snappjsonservice/') !== 0 || $_SERVER['PATH_INFO'] == '') {
    exit;
}

//Allow for cross domain XMLHttpRequests
$headers = getallheaders();
if (isset($headers['Origin'])) {
    header("Access-Control-Allow-Origin: ".$headers['Origin']);//need to be specific * won't work
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
    header('Access-Control-Allow-Credentials: true');
    // Set the age to 20 day to improve speed/caching.
    header('Access-Control-Max-Age: 1728000');
    header('Cache-Control: no-cache');
    header('Pragma: no-cache');

}


// Exit early so the page isn't fully loaded for options requests
if (strtolower($_SERVER['REQUEST_METHOD']) == 'options') {
    exit();
}

require_once(dirname(__FILE__) . '/../../index.php');

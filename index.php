<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 'On');

require_once "vendor/autoload.php";
require_once "App/Utils/Response.php";

use App\Config\Db;
use App\Config\Routing;
use App\Utils\Response;

App\Utils\Response::headers();
Db::config();
$data = Routing::dispatch();
Response::display($data);

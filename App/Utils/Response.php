<?php
namespace App\Utils;
class Response {
    static function json($data) {
        echo json_encode($data);
    }

    static function headers() {
        header('Content-type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token , Authorization');
    }

    static function display($data) {
        echo $data;
    }

    static function status($status = 200) {
        http_response_code($status);
    }
}

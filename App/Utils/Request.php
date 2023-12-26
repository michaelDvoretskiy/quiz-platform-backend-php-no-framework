<?php
namespace App\Utils;
class Request {
    static function getBody() {
        return json_decode(file_get_contents('php://input'), true);
    }
    static function getToken() {
        return $_GET['token'];
    }
}

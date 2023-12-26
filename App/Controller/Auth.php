<?php
namespace App\Controller;

use App\Model\User;
use App\Utils\Request;
use App\Utils\Response;
use PasswordHash;

class Auth {
//    private static function wordpress_hash_password($password)
//    {
//
//        require_once('../../wp-includes/class-phpass.php');
//        // By default, use the portable hash from phpass
//        $wp_hasher = new PasswordHash(8, true);
//
//        return $wp_hasher->HashPassword( trim( $password ) );
//    }

    static function login($params = []) {
        $userData = Request::getBody();

        $userWithToken = User::checkUserPass(new User(
            $userData['username'],
            $userData['password']
        ));
        if (!$userWithToken) {
            Response::status(401);
            return Response::json(['error' => 'authentication failed']);
        }
        return Response::json($userWithToken);
    } 

    static function checkToken() {
        $tokenData = Request::getBody();
        $user = User::checkToken(
            $tokenData['token']
        );
        if (!$user) {
            Response::status(401);
            return Response::json(['error' => 'authentication failed']);
        }
        return Response::json([
            'username' => $user['username']
        ]);
    }
}

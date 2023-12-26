<?php
namespace App\Model;

use App\Middleware\DataGetter;
use App\Middleware\RightsChecker;
use Illuminate\Database\Capsule\Manager as Data;
use PasswordHash;

class User {
    private $userName;
    private $userPass;

    function __construct(string $name, string $pass = '') {
        $this->userName = $name;
        $this->userPass = $pass;
    }

    static function checkToken(string $token) {
        $results = Data::select("select id, user_email from edu_users 
            where id in(select user_id from ng_user_token where token = :token)", [
            'token' => $token
        ]);
        if (!$results) {
            return false;
        }
        return [
            'id' => $results[0]->id,
            'username' => $results[0]->user_email
        ];
    }

    static function checkUserPass(User $user) {
        $results = Data::select("select id, user_email, user_pass
            from edu_users 
            where (user_email = :email or user_login = :login)",
        [
            'email' => $user->userName,
            'login' => $user->userName
        ]);
        if (!$results) {
            return false;
        }

        require_once('../../wp-includes/class-phpass.php');
        // By default, use the portable hash from phpass
        $wp_hasher = new \PasswordHash(8, true);
        if (!$wp_hasher->CheckPassword($user->userPass, $results[0]->user_pass)) {
            return false;
        }

        $user_id = $results[0]->id;
        
        $token = bin2hex(random_bytes(64));
        Data::table("ng_user_token")->upsert(
            ['user_id' => $user_id, 'token' => $token],
            ['user_id'], ['token']
        );

        $res = Data::table("ng_user_token")
            ->select('token')
            ->where('user_id', $user_id)
            ->get();

        $roles = [];
        if (RightsChecker::isUserAdmin($user_id)) {
            $roles = [1];
        }

        return [
            'username' => $results[0]->user_email,
            'token' => $res->first()->token,
            'name' => DataGetter::getUserPersonName($user_id),
            'roles' => $roles
        ];
    }
}

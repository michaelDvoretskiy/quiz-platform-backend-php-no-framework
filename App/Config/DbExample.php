<?php

namespace App\Config;

use Illuminate\Database\Capsule\Manager as Capsule;

class DbExample {
    static $capsule;

    static function config() {
        self::$capsule = new Capsule();

        self::$capsule->addConnection([
            'driver' => 'mysql',
            'host' => 'localhost',
            'database' => '',
            'username' => '',
            'password' => '',
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
        ]);

        (self::$capsule)->setAsGlobal();
    }
}

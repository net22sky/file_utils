<?php
namespace App\Models; 
use Illuminate\Database\Capsule\Manager as Capsule;
 
class Database {
    public function __construct() 
    {
        $capsule = new Capsule;
        $capsule->addConnection([
             'driver' => 'mysql',
             'host' => '127.0.0.1',
             'database' => 'document_db',
             'username' => 'root',
             'password' => 'secret',
             'charset' => 'utf8',
             'collation' => 'utf8_unicode_ci',
             'prefix' => '',
        ]);
        // Setup the Eloquent ORM… 
        $capsule->bootEloquent();
    }
}
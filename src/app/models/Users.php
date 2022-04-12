<?php

use Phalcon\Mvc\Model;

Class Users extends Model{
    public $id;
    public $name;
    public $username;
    public $email;
    public $password;
    public $access_token;
    public $refresh_token;

}
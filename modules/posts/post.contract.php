<?php
namespace Modules\Posts;
use MilkCore\Get;

/*
 The contract classes are designed to be used as external utility classes to the module. 

class PostContract
{
    public static function get_instance() {

    }
}

// The classes are registered within Get
Get::bind('PostContract', PostContract::class);

This way, anywhere within the project, you can access the PostContract class by calling the class with
$post_contract = Get::make('PostContract');

*/
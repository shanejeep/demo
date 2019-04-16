<?php

namespace app\home\controller;

use think\Controller;
use think\Verify;
use think\Db;
use app\home\logic\StoreLogic;
use think\Cookie;

/**
 * @path("/hw")
 */
class HelloWorld extends Controller
{
    /**
     *
     * @route({"GET","/"})
     */
    public function doSomething()
    {
        return "Hello World!";
    }
}
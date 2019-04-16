<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * 2015-11-21
 */

namespace app\mobile\controller;
use think\Db;

class Douser extends MobileBase
{   
    /*
     * 初始化操作
     */
    // public function _initialize()
    // {
    //     parent::_initialize;
    // }

    public function justLogin(){
        $mobile = I('mobile');
        $token = I('token');
        $user = DB::name('users')->where('mobile',$mobile)->find();
        if(!empty($user)){
            session("user",$user);
        }
        $this->redirect("Mobile/Index/index");
    }
    
  
    
}
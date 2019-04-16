<?php

return array(
    'code'=> 'weixin',
    'name' => '微信支付',
    'version' => '1.0',
    'author' => 'IT宇宙人',
    'desc' => 'PC端+微信公众号支付',
    'icon' => 'logo.jpg',
    'scene' => 0,  // 使用场景 0 PC+手机 1 手机 2 PC
    'config' => array(
        array('name' => 'appid','label'=>'绑定支付的APPID','type' => 'text',   'value' => 'wxa1c91f2e0abc697c','hint'=>''), // * APPID：绑定支付的APPID（必须配置，开户邮件中可查看）
        array('name' => 'mchid',   'label'=>'商户号', 'type' => 'text',   'value' => '1484465292'), // * MCHID：商户号（必须配置，开户邮件中可查看）
        array('name' => 'key',  'label'=>'商户支付密钥', 'type' => 'text',   'value' => 'GoodYao2016WeiXinPayForYZJShopJS'), // KEY：商户支付密钥，参考开户邮件设置（必须配置，登录商户平台自行设置）
        array('name' => 'appsecret',  'label'=>'公众帐号secert（仅JSAPI支付的时候需要配置)', 'type' => 'text',   'value' => '34a101bf15046c05090c8943aa1ab57c'), // 公众帐号secert（仅JSAPI支付的时候需要配置)，
    ),
);
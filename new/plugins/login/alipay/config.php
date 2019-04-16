<?php
return array(
    'code'=> 'alipay',
    'name' => '支付宝快捷登录',
    'version' => '1.0',
    'author' => '彭老师',
    'desc' => '支付宝快捷登录插件 ',
    'icon' => 'logo.jpg',
    'config' => array(
        array('name' => 'alipay_partner','label'=>'合作者身份ID','type' => 'text',   'value' => ''),
		array('name' => 'alipay_key','label'=>'安全检验码','type' => 'text',   'value' => '')
    )
);
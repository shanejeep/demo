<?php
namespace app\admin\validate;
use think\Validate;
class Spec extends Validate
{       
    // 验证规则
    protected $rule = [
        ['name','require|unique:spec','规格名称必须填写|规格名称不能重复'],
        ['cat_id1', 'require', '所属分类必须选择'],        
        ['order','number','排序必须为数字'],       
    ];
    protected $scene = [
        'edit'  =>  ['name','cat_id1','order'],
    ];
      
}
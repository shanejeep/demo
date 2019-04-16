<?php
namespace app\api\model;

use think\Model;

class Goods extends Model{

    protected $table = "s_goods_copy";

    public function getIsOnSaleAttr($value)
    {
        $is_on_sale = [0=>'下架',1=>'上架',2=>'违规下架'];
        return $is_on_sale[$value];
    }
    public function store()
    {
        return $this->hasOne('store','store_id','store_id',[],'LEFT');
    }








































	
}




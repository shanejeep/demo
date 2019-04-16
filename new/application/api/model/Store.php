<?php
namespace app\api\model;
use think\Model;

class Store extends Model{

     protected $table = "s_store_copy";


     public function goods()
     {
        return $this->hasMany('goods','store_id','store_id',[],'LEFT');
     }









































	
}




<?php
/**
 *jeep
 */ 
namespace app\mobile\model;
use think\Model;
use think\Db;


class Goods extends Model{
    /**获取商品详情**/
	public function getGoodsInfo($goods_id){
		$info = M("goods")->where("goods_id",$goods_id)->find();
		return $info;
	}
	
	/**获取商品某一字段*/
	public function getGoodsField($goods_id,$field){
		$field = M("goods")->where("goods_id",$goods_id)->getField($field);
		return $field;
	}
}
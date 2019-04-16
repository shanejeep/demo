<?php
/**
 * tpshop
 * 回复模型
 * @auther：dyr
 */ 
namespace app\mobile\model;
use think\Model;
use think\Db;


class Store extends Model{
    /**
     * 店铺街
     * @author dyr
     * @param $sc_id 店铺分类ID，可不传，不传将检索所有分类
     * @param int $p 分页
     * @param int $item 每页多少条记录
     * @return mixed
     */
    public function getStreetList($sc_id=0,$p=1,$item=10)
    {
        $store_where = array('s.store_state' => 1); //@modify by wangqh. 只显示开启的店铺
        $db_prefix = C('database.prefix');
        if(!empty($sc_id)){
            $store_where['s.sc_id'] = $sc_id;
        }
        $store_list = Db::name('store')->alias('s')
            ->field('s.store_id,s.store_phone,s.store_logo,s.store_name,s.store_desccredit,s.store_servicecredit,
						s.store_deliverycredit,r1.name as province_name,r2.name as city_name,r3.name as district_name,
						s.deleted as goods_array')
            ->join($db_prefix . 'region r1','r1.id = s.province_id' ,'LEFT')
            ->join($db_prefix . 'region r2 ',' r2.id = s.city_id','LEFT')
            ->join($db_prefix .'region r3','r3.id = s.district','LEFT')
            ->where($store_where)
            ->page($p,$item)
            ->cache(true,TPSHOP_CACHE_TIME)
            ->select();
        return $store_list;
    }

    /**
     * 获取店铺商品详细
     * @param $store_id
     * @param $limit
     * @return mixed
     */
    public function getStoreGoods($store_id,$limit)
    {
        $goods_model = M('goods');
        $goods_where = array(
            'is_on_sale'=>1,
//            'is_recommend'=>1,
//            'is_hot'=>1,
            'goods_state'=>1,
            'store_id'=>$store_id
        );
        $res['goods_list'] = $goods_model->field('goods_id,goods_name,shop_price')->where($goods_where)->limit($limit)->order('sort desc')->select();
        $count_where = array(
//            'goods_state'=>1,
            'store_id'=>$store_id
        );
        $res['goods_count'] = $goods_model->where($count_where)->count();
        return $res;
    }
}
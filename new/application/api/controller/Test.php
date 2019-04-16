<?php
namespace app\api\controller;

use app\api\model\goods;
use app\api\model\Store;
use think\Config;
use think\Controller;
use think\Model;
use think\Db;
use think\Request;

class Test extends  Controller
{
    public function index()
    {
        echo "<pre>";
        //$request = Request::instance();
        $goods_model = new  Goods();
        $rs = Goods::get(171);

//        $info = Goods::all([168,169,170]);
//        $info = Db::name('goods')->where('goods_id',168)->value('goods_name');
//        $info = $goods_model->where('goods_id',168)->value('goods_name');//
//        return json(['code'=>1,'data'=>$info]);
       // $del_arr = [168,169,170];
      //  $rs = Db::name('goods_copy')->delete($del_arr);
//        $rs = $goods_model->find(171);//
        //$rs = $goods_model->where(['goods_id'=>171])->setField('goods_common',666);
//        $rs = $goods_model->field('goods_id,goods_name,is_on_sale')->page(1,10)->order('goods_id')->comment("前十个商品")->select();
//        $rs = $goods_model::destroy(168);

//        $rs = $goods_model->where('goods_id',172)->value("goods_name");
//          $rs = $goods_model::where('store_id',15)->sum('goods_id');

//        $this->assign("rs", $rs);
//        return $this->fetch();

     /*   $rs->store->company_name = 'jeep重庆科技有限公司';
        $rs->store->save();
        var_dump($rs->store->company_name);*/

//     $store = Store::get(15);
        $store_list = Store::has('goods','>',3)->select();
     var_dump($store_list);



    }





















}

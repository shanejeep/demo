<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Author: 当燃
 * Date: 2016-05-28
 */

namespace app\mobile\controller;

use think\Controller;


class Store extends MobileBase {
	public $store = array();
	
	public function _initialize() {
	    parent::_initialize();
		$store_id = I('store_id/d');
		if(empty($store_id)){
			$this->error('参数错误,店铺系列号不能为空',U('Index/index'));
		}
		$store = M('store')->where(array('store_id'=>$store_id))->find();
		if($store){
			if($store['store_state'] == 0){
				$this->error('该店铺不存在或者已关闭', U('Index/index'));
			}
			$store['mb_slide'] = explode(',', $store['mb_slide']);
			$store['mb_slide_url'] = explode(',', $store['mb_slide_url']);
			$this->store = $store;
			$this->assign('store',$store);
		}else{
			$this->error('该店铺不存在或者已关闭',U('Index/index'));
		}
		if (session('?user')) {
			$user = session('user');
			$this->user_id = $user['user_id'];
			$this->assign('user', $user); //存储用户信息
		}
	}
	
	public function index(){
		//热门商品排行
		$hot_goods = M('goods')->field('goods_content',true)->where(array('store_id'=>$this->store['store_id'],'is_on_sale'=>1))->order('sales_sum desc')->limit(10)->select();
		//新品
		$new_goods = M('goods')->field('goods_content',true)->where(array('store_id'=>$this->store['store_id'],'is_new'=>1,'is_on_sale'=>1))->order('goods_id desc')->limit(10)->select();
		//推荐商品
		$recomend_goods = M('goods')->field('goods_content',true)->where(array('store_id'=>$this->store['store_id'],'is_on_sale'=>1))->order('sort desc')->limit(10)->select();
		//所有商品
		$total_goods = M('goods')->where(array('store_id'=>$this->store['store_id'],'is_on_sale'=>1))->count();
		
		$this->assign('hot_goods',$hot_goods);
		$this->assign('new_goods',$new_goods);
		$this->assign('recomend_goods',$recomend_goods);
		$this->assign('total_goods',$total_goods);
		$total_goods = M('goods')->where(array('store_id'=>$this->store['store_id'],'is_on_sale'=>1))->count();
		$this->assign('total_goods',$total_goods);
		$this->assign('from',I('from',0));
		return $this->fetch();
	}
	
	public function goods_list(){
		$cat_id = I('cat_id/d', 0);
		$key = I('key', 'is_new');
		$p = I('p', '1');
		$sort = I('sort', 'desc');
		$keywords = I('keywords');
		$map = array('store_id' => $this->store['store_id'], 'is_on_sale' => 1);
		$cat_name = "全部商品";
		if ($cat_id > 0) {
			$cat_name = M('store_goods_class')->where(array('cat_id' => $cat_id))->getField('cat_name');
		}
		if($keywords){
			$map['goods_name'] = array('like',"%$keywords%");
		}
		$filter_goods_id = M('goods')->where($map)->where(function($query) use($cat_id){
		    if ($cat_id > 0) {
		        $query->where("store_cat_id1",$cat_id)->whereOr("store_cat_id2" , $cat_id);;
		    }else{
		        $query->where("1=1");
		    }
		})->getField("goods_id", true);
		$count = count($filter_goods_id);
		$page_count = 20;//每页多少个商品
		if ($count > 0 && $filter_goods_id>0) {
			$goods_list = M('goods')->where("goods_id in (" . implode(',', $filter_goods_id) . ")")->order("$key $sort")->page($p,$page_count)->select();
		}

		$sort = ($sort == 'desc') ? 'asc' : 'desc';
		$this->assign('sort', $sort);
		$this->assign('keys', $key);
		$link_arr = array(
				array('key' => 'is_new', 'name' => '最新', 'url' => U('Store/goods_list', array('store_id' => $this->store['store_id'], 'key' => 'is_new', 'sort' => $sort))),
				array('key' => 'sales_sum', 'name' => '销量', 'url' => U('Store/goods_list', array('store_id' => $this->store['store_id'], 'key' => 'sales_sum', 'sort' => $sort))),
				//array('key' => 'collect_sum', 'name' => '收藏', 'url' => U('Store/goods_list', array('store_id' => $this->store['store_id'], 'key' => 'collect_sum', 'sort' => $sort))),
				array('key' => 'is_recommend', 'name' => '人气', 'url' => U('Store/goods_list', array('store_id' => $this->store['store_id'], 'key' => 'is_recommend', 'sort' => $sort))),
				array('key' => 'shop_price', 'name' => '价格', 'url' => U('Store/goods_list', array('store_id' => $this->store['store_id'], 'key' => 'shop_price', 'sort' => $sort)))
		);

		$this->assign('cat_id', $cat_id);
		$this->assign('key', $key);
		$this->assign('sort', $sort);
		$this->assign('keywords', $keywords);
		$this->assign('link_arr', $link_arr);
		$this->assign('goods_list', $goods_list);
		$this->assign('cat_name', $cat_name);
		$this->assign('goods_list_total_count',$count);
		$this->assign('page_count',$page_count);
		if(IS_AJAX){
			echo $this->fetch('ajaxGoodsList');
		}else{
			echo $this->fetch();
		}
	}
	
	public function about(){
		$total_goods = M('goods')->where(array('store_id'=>$this->store['store_id'],'is_on_sale'=>1))->count();
		$this->assign('total_goods',$total_goods);
		return $this->fetch();
	}
	
	public function store_goods_class(){
		$store_goods_class_list = M('store_goods_class')->where(array('store_id'=>$this->store['store_id']))->select();
		if($store_goods_class_list){
			$sub_cat = $main_cat = array();
			foreach ($store_goods_class_list as $val){
			    if ($val['parent_id'] == 0) {
                    $main_cat[] = $val;
                } else {
                    $sub_cat[$val['parent_id']][] = $val;
                }
			}
			$this->assign('main_cat',$main_cat);
			$this->assign('sub_cat',$sub_cat);
		}
		return $this->fetch();
	}

}
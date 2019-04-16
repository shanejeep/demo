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
 * $Author: IT宇宙人 2015-08-10 $
 */

namespace app\mobile\controller;

use app\mobile\logic\ReplyLogic;
use app\home\logic\UsersLogic;


use think\AjaxPage;
use think\Page;
use think\Db;
use think;

class Goods extends MobileBase
{
    public function _initialize()
    {
        parent::_initialize();
    }
    public function index()
    {
        return $this->fetch();
    }
    
    /**
     * 分类列表显示
     */
    public function categoryList()
    {
        return $this->fetch();
    }
    
     /**
     * 商品列表页
     */
    public function goodsList()
    {
        $filter_param = array(); // 帅选数组
        $alias = I("cat");
        // $id = I('get.id/d', 0); // 当前分类id
        $brand_id = I('brand_id/d', 0);
        //$spec = I('spec',0); // 规格
        $attr = I('attr', ''); // 属性
        $sort = I('sort', 'goods_id'); // 排序
        $sort_asc = I('sort_asc', 'DESC'); // 排序
        $price = I('price', ''); // 价钱
        $start_price = trim(I('start_price', '0')); // 输入框价钱
        $end_price = trim(I('end_price', '0')); // 输入框价钱
        $sel = trim(I('sel')); //筛选货到付款,仅看有货,促销商品
        $q = urldecode(trim(I('q', ''))); // 关键字搜索
        $q && ($_GET['q'] = $filter_param['q'] = $q); //加入帅选条件中

        if ($start_price && $end_price) $price = $start_price . '-' . $end_price; // 如果输入框有价钱 则使用输入框的价钱
        $filter_param['id'] = $id; //加入帅选条件中
        $filter_param['cat'] = $alias;
        $brand_id && ($filter_param['brand_id'] = $brand_id); //加入帅选条件中
        // $spec  && ($filter_param['spec'] = $spec); //加入帅选条件中
        $attr && ($filter_param['attr'] = $attr); //加入帅选条件中
        $price && ($filter_param['price'] = $price); //加入帅选条件中
        $sel && ($filter_param['sel'] = $sel); //加入帅选条件中
        
        $goodsLogic = new \app\home\logic\GoodsLogic(); // 前台商品操作逻辑类
        // 分类菜单显示
        if(!empty($alias)){
            $id = M("GoodsCategory")->where("alias",$alias)->getField("id");
        }

        $goodsCate = M('GoodsCategory')->where("id", $id)->find();// 当前分类
        
        //($goodsCate['level'] == 1) && header('Location:'.U('Home/Channel/index',array('cat_id'=>$id))); //一级分类跳转至大分类馆
        $cateArr = $goodsLogic->get_goods_cate($goodsCate);
        
        // 帅选 品牌 规格 属性 价格
        $cat_id_arr = getCatGrandson($id);
        
        //$filter_goods_id = M('goods')->where("is_on_sale=1 and cat_id in(".  implode(',', $cat_id_arr).") ")->cache(true)->getField("goods_id",true);
        if ($goodsCate) {
            $filter_goods_id = M('goods')->where(" goods_state = 1 and is_on_sale=1 and cat_id{$goodsCate['level']} = $id")->cache(GOODS_CACHE_TIME)->getField("goods_id", true);
            // 扩展分类
            $goodsIdArr = M('goods_category_data')->where('cat_id', $id)->cache(GOODS_CACHE_TIME)->getField('goods_id', true);
            if ($goodsIdArr) {
                $filter_goods_id = array_merge($filter_goods_id, $goodsIdArr);
                $filter_goods_id = array_unique($filter_goods_id);
            }
        } else {
            $where_goods = "goods_state = 1 and is_on_sale=1";
            switch ($alias) {
                case 'mgg':
                    $shopping_country = M('country')->where("name='美国' and status = 1")->getField('id');
                    break;
                case 'azg':
                    $shopping_country = M('country')->where("name='澳大利亚' and status = 1")->getField('id');
                    break;
            }
            $shopping_country && $where_goods .= " and shopping_country = ".$shopping_country;
            $filter_goods_id = M('goods')->where($where_goods)->cache(GOODS_CACHE_TIME)->getField("goods_id", true);
        }
        
        // 过滤帅选的结果集里面找商品
        // 品牌或者价格
        if ($brand_id || $price){
            $goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id, $price); // 根据 品牌 或者 价格范围 查找所有商品id
            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_1); // 获取多个帅选条件的结果 的交集
        }
        //if($spec)// 规格
        //{
        //  $goods_id_2 = $goodsLogic->getGoodsIdBySpec($spec); // 根据 规格 查找当所有商品id
        //  $filter_goods_id = array_intersect($filter_goods_id,$goods_id_2); // 获取多个帅选条件的结果 的交集
        //}
        if ($sel) {
            $goods_id_4 = $goodsLogic->get_filter_selected($sel, $cat_id_arr);
            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_4);
        }
        // 属性
        if ($attr) {
            $goods_id_3 = $goodsLogic->getGoodsIdByAttr($attr); // 根据 规格 查找当所有商品id
            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_3); // 获取多个帅选条件的结果 的交集
        }

        
        $filter_menu = $goodsLogic->get_filter_menu($filter_param, 'goodsList'); // 获取显示的帅选菜单
        $filter_price = $goodsLogic->get_filter_price($filter_goods_id, $filter_param, 'goodsList'); // 帅选的价格期间
        $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id, $filter_param, 'goodsList', 1); // 获取指定分类下的帅选品牌
        //$filter_spec  = $goodsLogic->get_filter_spec($filter_goods_id,$filter_param,'goodsList',1); // 获取指定分类下的帅选规格
        $filter_attr = $goodsLogic->get_filter_attr($filter_goods_id, $filter_param, 'goodsList', 1); // 获取指定分类下的帅选属性
        //商品关键字查找
        $where = array(
            'goods_name' => array('like', '%' . $q . '%'),
            'goods_state' => 1,
            'is_on_sale' => 1,
            'goods_id' => array('in', implode(',', $filter_goods_id))
        );
        
        $count = count($filter_goods_id);
        $page_count = 20;
        $page = new Page($count, $page_count);
        if ($count > 0) {
            $goods_list = M('goods')->where($where)->order("$sort $sort_asc")->limit($page->firstRow . ',' . $page->listRows)->select();
            $filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
            if ($filter_goods_id2)
                $goods_images = M('goods_images')->where("goods_id in (" . implode(',', $filter_goods_id2) . ")")->cache(true)->select();
        }
        $goods_category = M('goods_category')->where('is_show=1')->cache(true)->getField('id,name,parent_id,level'); // 键值分类数组
        $this->assign('goods_list', $goods_list);
        $this->assign('goods_category', $goods_category);
        $this->assign('goods_images', $goods_images);  // 相册图片
        $this->assign('filter_menu', $filter_menu);  // 帅选菜单
        //$this->assign('filter_spec',$filter_spec);  // 帅选规格
        $this->assign('filter_attr', $filter_attr);  // 帅选属性
        $this->assign('filter_brand', $filter_brand);// 列表页帅选属性 - 商品品牌
        $this->assign('filter_price', $filter_price);// 帅选的价格期间
        $this->assign('goodsCate', $goodsCate);
        $this->assign('cateArr', $cateArr);
        foreach ($filter_param as $k => $v) {
            if(empty($v)){
                unset($filter_param[$k]);
            }
        }
        $this->assign('filter_param', $filter_param); // 帅选条件
        $this->assign('cat_id', $id);
        $this->assign('page', $page);// 赋值分页输出
        $this->assign('page_count', $page_count);//一页显示多少条
        $this->assign('sort_asc', $sort_asc == 'asc' ? 'desc' : 'asc');
        C('TOKEN_ON', false);
        
        if (request()->isAjax())
            return $this->fetch('ajaxGoodsList');
        else
            return $this->fetch();
    }
    
    /**
     * 商品列表页 ajax 翻页请求 搜索
     */
    public function ajaxGoodsList()
    {
        $where = '';
        
        $cat_id = I("id/d", 0); // 所选择的商品分类id
        if ($cat_id > 0) {
            $grandson_ids = getCatGrandson($cat_id);
            $where .= " WHERE cat_id in(" . implode(',', $grandson_ids) . ") "; // 初始化搜索条件
        }
        
        $result = Db::query("select count(1) as count from __PREFIX__goods $where ");
        $count = $result[0]['count'];
        $page = new AjaxPage($count, 10);
        
        $order = " order by goods_id desc"; // 排序
        $limit = " limit " . $page->firstRow . ',' . $page->listRows;
        $list = Db::query("select *  from __PREFIX__goods $where $order $limit");
        
        $this->assign('lists', $list);
        $html = $this->fetch('ajaxGoodsList'); //return $this->fetch('ajax_goods_list');
        exit($html);
    }
    
      /**
     * 商品列表页
     */
    public function storeList()
    {
        
        $filter_param = array(); // 帅选数组
        $q = trim(I('q')); //
        $q && ($filter_param['q'] = $q); //加入帅选条件中
        $filter_menu['store_name']=array('like','%'.$q.'%');
        $count=M('store')->where($filter_menu)->count();
        $page_count = 4;
        $page = new Page($count, $page_count);
        if ($count > 0) {
            $store_list = M('store')->where($filter_menu)->limit($page->firstRow . ',' . $page->listRows)->select();
            foreach ($store_list as $k => &$v) {
                $v['goodsList'] = M('goods')->field('goods_id')->where('store_id',$v['store_id'])->order("goods_id DESC")->limit('0,3')->select();
            }
            unset($v);
        }
        $this->assign('store_list', $store_list);
        $this->assign('filter_menu', $filter_menu);  // 帅选菜单
        $this->assign('filter_param', $filter_param); // 帅选条件
        $this->assign('page', $page);// 赋值分页输出
        $this->assign('page_count', $page_count);//一页显示多少条
        C('TOKEN_ON', false);
        if (request()->isAjax())
            return $this->fetch('ajaxStoreList');
        else
            return $this->fetch();
    }
    
    /**
     * 商品列表页 ajax 翻页请求 搜索
     */
    public function ajaxStoreList()
    {
        $where = '';
        
         $q = trim(I('q')); //
        if (!empty($q)) {
            $where .= " WHERE store_name  LIKE '%".$q."%' "; // 初始化搜索条件
        }
        
        $result = Db::query("select count(1) as count from __PREFIX__store $where ");
        $count = $result[0]['count'];
        $page = new AjaxPage($count, 10);
        
        $order = " order by store_id desc"; // 排序
        $limit = " limit " . $page->firstRow . ',' . $page->listRows;
        $list = Db::query("select *  from __PREFIX__store $where $order $limit");
        if (count($list) > 0) {
            $store_list = M('store')->where($filter_menu)->limit($page->firstRow . ',' . $page->listRows)->select();
            foreach ($store_list as $k => &$v) {
                $v['goodsList'] = M('goods')->field('goods_id')->where('store_id',$v['store_id'])->order("goods_id DESC")->limit('0,3')->select();
            }
            unset($v);
        }
        $this->assign('lists', $list);
        $html = $this->fetch('ajaxStoreList'); //return $this->fetch('ajax_goods_list');
        exit($html);
    }
    
        /**
     * 商品搜索列表页
     */
    public function search()
    {
        $type= I("t");
        if($type == 1){
            $filter_param = array(); // 帅选数组
            // $id = I('get.id/d', 0); // 当前分类id
            $brand_id = I('brand_id/d', 0);
            $sort = I('sort', 'goods_id'); // 排序
            $sort_asc = I('sort_asc', 'DESC'); // 排序
            $price = I('price', ''); // 价钱
            $start_price = trim(I('start_price', '0')); // 输入框价钱
            $end_price = trim(I('end_price', '0')); // 输入框价钱
            if ($start_price && $end_price) $price = $start_price . '-' . $end_price; // 如果输入框有价钱 则使用输入框的价钱
            $filter_param['id'] = $id; //加入帅选条件中
            $brand_id && ($filter_param['brand_id'] = $brand_id); //加入帅选条件中
            $price && ($filter_param['price'] = $price); //加入帅选条件中
            $q = urldecode(trim(I('q', ''))); // 关键字搜索
            $q && ($_GET['q'] = $filter_param['q'] = $q); //加入帅选条件中
            //if(empty($q))
            //    $this->error ('请输入搜索关键词');
            $where = array(
                'goods_name' => array('like', '%' . $q . '%'),
                'goods_state' => 1,
                'is_on_sale' => 1,
            );
            $goodsLogic = new \app\home\logic\GoodsLogic(); // 前台商品操作逻辑类
            $filter_goods_id = M('goods')->where($where)->cache(true)->getField("goods_id", true);  //bind会提示绑定q失败，临时用这个测试
    //      $filter_goods_id = M('goods')->where(" goods_state = 1 and is_on_sale=1 and goods_name like :q  ")->bind(['q'=> "%{$q}%"])->cache(true)->getField("goods_id",true);
            
            // 过滤帅选的结果集里面找商品
            if ($brand_id || $price)// 品牌或者价格
            {
                $goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id, $price); // 根据 品牌 或者 价格范围 查找所有商品id
                $filter_goods_id = array_intersect($filter_goods_id, $goods_id_1); // 获取多个帅选条件的结果 的交集
            }
            
            $filter_menu = $goodsLogic->get_filter_menu($filter_param, 'search'); // 获取显示的帅选菜单
            $filter_price = $goodsLogic->get_filter_price($filter_goods_id, $filter_param, 'search'); // 帅选的价格期间
            $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id, $filter_param, 'search', 1); // 获取指定分类下的帅选品牌
            
            $count = count($filter_goods_id);
            $page = new Page($count, 20);
            if ($count > 0 && $filter_goods_id > 0) {
                $goods_list = M('goods')->where("goods_id in (" . implode(',', $filter_goods_id) . ")")->order("$sort $sort_asc")->limit($page->firstRow . ',' . $page->listRows)->select();
                $filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
                if ($filter_goods_id2)
                    $goods_images = M('goods_images')->where("goods_id in (" . implode(',', $filter_goods_id2) . ")")->cache(true)->select();
            }
            $goods_category = M('goods_category')->where('is_show=1')->cache(true)->getField('id,name,parent_id,level'); // 键值分类数组
            $this->assign('goods_list', $goods_list);
            $this->assign('goods_category', $goods_category);
            $this->assign('goods_images', $goods_images);  // 相册图片
            $this->assign('filter_menu', $filter_menu);  // 帅选菜单
            $this->assign('filter_brand', $filter_brand);// 列表页帅选属性 - 商品品牌
            $this->assign('filter_price', $filter_price);// 帅选的价格期间
            foreach ($filter_param as $k => $v) {
                if(empty($v)){
                    unset($filter_param[$k]);
                }
            }
            $this->assign('filter_param', $filter_param); // 帅选条件
            $this->assign('page', $page);// 赋值分页输出
            $this->assign('sort_asc', $sort_asc == 'asc' ? 'desc' : 'asc');
            C('TOKEN_ON', false);
            
            if ($_GET['is_ajax'])
                return $this->fetch('ajaxGoodsList');
            else
                return $this->fetch("goodsList");
        }elseif($type == 2){
            $filter_param = array(); // 帅选数组
            $q = trim(I('q')); //
            $q && ($filter_param['q'] = $q); //加入帅选条件中
            $filter_menu['store_name']=array('like','%'.$q.'%');
            $count=M('store')->where($filter_menu)->count();
            $page_count = 20;
            $page = new Page($count, $page_count);
            if ($count > 0) {
                $store_list = M('store')->where($filter_menu)->limit($page->firstRow . ',' . $page->listRows)->select();
                foreach ($store_list as $k => &$v) {
                    $v['goodsList'] = M('goods')->field('goods_id')->where('store_id',$v['store_id'])->order("goods_id DESC")->limit('0,3')->select();
                }
                unset($v);
            }
            $this->assign('store_list', $store_list);
            $this->assign('filter_menu', $filter_menu);  // 帅选菜单
            $this->assign('filter_param', $filter_param); // 帅选条件
            $this->assign('page', $page);// 赋值分页输出
            $this->assign('page_count', $page_count);//一页显示多少条
            C('TOKEN_ON', false);
            if (request()->isAjax())
                return $this->fetch('ajaxStoreList');
            else
                return $this->fetch("storeList");
        }else{
              return $this->fetch();
        }
    }

    public function abcx(){
        $carr = M("goods_category")->where('is_drug',1)->getField('id',true);
        M('goods')->where("cat_id1 IN (".implode(",",$carr).")")->save(array('is_drug'=>1,'drug_attr'=>3,'drug_sn'=>'666',"drug_mfrs"=>"重庆医药","drug_consult"=>'15265845475',"goods_common"=>"处方药"));
        echo "ok";
    }
    
    /**
     * 商品详情页
     */
    public function goodsInfo()
    {
        C('TOKEN_ON', true);
        $goodsLogic = new \app\home\logic\GoodsLogic();
        $goods_id = I("get.id/d", 0);
        $mb_type = I('t',0);  //分享通过web进入的
        $from = $url_type = I('from',0);  //来源路径 //是否从app首页直接点进商品 0:商城进入 1:app首页进入
        $this->assign('from', $from);
        $goods = M('Goods')->where("goods_id", $goods_id)->find();
        if (empty($goods)) {
            $this->error('此商品不存在');
        }
        // if($goods['drug_attr'] == 3){
        //      $this->redirect(url('Mobile/Goods/drugInfo', array('id' => $goods_id,'from'=>$url_type,'t'=>$mb_type)));
        // }
        if (($goods['is_on_sale'] != 1)) {
        }
        if ($goods['brand_id']) {
            $brnad = M('brand')->where("id ", $goods['brand_id'])->find();
            $goods['brand_name'] = $brnad['name'];
        }
        $this->assign('url_type',$url_type);
        $this->assign('mb_type',$mb_type);
        $goods_images_list = M('GoodsImages')->where("goods_id", $goods_id)->select(); // 商品图册
        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsAttr')->where("goods_id", $goods_id)->select(); // 查询商品属性表
        $filter_spec = $goodsLogic->get_spec($goods_id);
        
        $spec_goods_price = M('spec_goods_price')->where("goods_id", $goods_id)->getField("key,price,store_count"); // 规格 对应 价格 库存表
        //M('Goods')->where("goods_id=$goods_id")->save(array('click_count'=>$goods['click_count']+1 )); //统计点击数
        $commentStatistics = $goodsLogic->commentStatistics($goods_id);// 获取某个商品的评论统计
        $this->assign('spec_goods_price', json_encode($spec_goods_price, true)); // 规格 对应 价格 库存表
        $comment_where = array();
        $comment_where['g.goods_id'] = $goods_id;
        $comment_where['g.is_send'] = array('in', '0,1,2');
        $comment_where['o.pay_status'] = 1;
        $goods['sale_num'] = M('order_goods')->alias('g')->join('__ORDER__ o', 'o.order_id = g.order_id', 'LEFT')->where($comment_where)->count();
        
        //商品促销:1团购2抢购3优惠促销
        if ($goods['prom_type'] == 1) {
            $prom_goods = M('prom_goods')->where("id", $goods['prom_id'])->find();
            $this->assign('prom_goods', $prom_goods);// 商品促销
            $goods['flash_sale'] = get_goods_promotion($goods['goods_id']);
            $flash_sale = M('flash_sale')->where("id", $goods['prom_id'])->find();
            $this->assign('flash_sale', $flash_sale);
        }
        
        $this->assign('commentStatistics', $commentStatistics);//评论概览
        $this->assign('goods_attribute', $goods_attribute);//属性值
        $this->assign('goods_attr_list', $goods_attr_list);//属性列表
        $this->assign('filter_spec', $filter_spec);//规格参数
        $this->assign('goods_images_list', $goods_images_list);//商品缩略图
        $goods['market_price'] = $goods['market_price'] ? $goods['market_price'] : $goods['shop_price']; // 仿制除数为0的情况
        $goods['discount'] = round($goods['shop_price'] / $goods['market_price'], 2) * 10;
        switch ($goods['sales_model']) {
            case 1:
                $goods['sales_model_title'] = '国内现货';
                break;
            case 2:
                $goods['sales_model_title'] = '海外直邮';
                break;
            case 3:
                $goods['sales_model_title'] = '保税区发货';
                break;
            default:
                $goods['sales_model_title'] = '国内现货';
        }
        $this->assign('goods', $goods);
        if ($goods['store_id'] > 0) {
            $store = M('store')->where(array('store_id' => $goods['store_id']))->find();
            if (!$store['store_logo']) {
                $store['store_logo'] = '/public/images/not_adv.jpg';
            }
            $store_user_mobile = M('users')->where('user_id=' . $store['user_id'])->getField('mobile');
            $logic = new UsersLogic();
            $storeRes = $logic->getImId($store_user_mobile, 0);
            if ($storeRes['respose_info']['data'][0]['user']['easemob_id']) {
                $storeAPIArr = array('easemob_id' => $storeRes['respose_info']['data'][0]['user']['easemob_id'], 'user_id' => $storeRes['respose_info']['data'][0]['user']['user_id']);
                $storeAPIJson = urlencode(json_encode($storeAPIArr));
                $this->assign('store_json', $storeAPIJson);
            }
            $this->assign('storeRes', $storeRes);
            $this->assign('store', $store);
        }

        //根据商品属性搞一波OTC图片
        switch ($goods['drug_attr']) {
            case '1':
               $otcImage = "http://goodyao.oss-cn-hangzhou.aliyuncs.com/icons/rotc.png";
                break;
            case '2':
                $otcImage = "http://goodyao.oss-cn-hangzhou.aliyuncs.com/icons/gotc.png";
                break;
            case '3':
                $otcImage = "http://goodyao.oss-cn-hangzhou.aliyuncs.com/icons/rx.png";
                break;
            default:
                break;
        }
        $this->assign("otcImage", $otcImage);
        $user_id = cookie('user_id');
        $collect = M('goods_collect')->where(array("goods_id" => $goods_id, "user_id" => $user_id))->count();
        $this->assign('collect', $collect);
        if($goods['drug_attr'] == 3){
             return $this->fetch("drugInfo");
             exit;
        }
        return $this->fetch();
        
       
    }
    
    /**
     * 商品详情页
     */
    public function detail()
    {
        //  form表单提交
        C('TOKEN_ON', true);
        $goods_id = I("get.id/d", 0);
        $goods = M('Goods')->where("goods_id", $goods_id)->find();
        $this->assign('goods', $goods);
        return $this->fetch();
    }
    
    /*
     * 商品评论
     */
    public function comment()
    {
        $goods_id = I("goods_id/d", '0');
        $this->assign('goods_id', $goods_id);
        return $this->fetch();
    }
    
    /**
     * @author dyr
     * 商品评论ajax分页
     */
    public function ajaxComment()
    {
        $goods_id = I("goods_id/d", '0');
        $commentType = I('commentType', '1'); // 1 全部 2好评 3 中评 4差评 5晒图
        if ($commentType == 5) {
            $where = "c.is_show = 1 and c.goods_id = :goods_id and c.parent_id = 0 and c.img !='' and c.img NOT LIKE 'N;%' and c.deleted = 0";
            
        } else {
            $typeArr = array('1' => '0,1,2,3,4,5', '2' => '4,5', '3' => '3', '4' => '0,1,2');
            $where = "c.is_show = 1 and c.goods_id = :goods_id and c.parent_id = 0 and ceil(c.goods_rank) in($typeArr[$commentType]) and c.deleted = 0";
        }
        $count = Db::name('comment')->alias('c')->where($where)->bind(['goods_id' => $goods_id])->count();
        
        $page_count = 20;
        $page = new AjaxPage($count, $page_count);
//        $show = $page->show();
        $list = Db::name('comment')->alias('c')
            ->field("u.head_pic,u.nickname,u.mobile,c.add_time,c.spec_key_name,c.content,
                    c.impression,c.comment_id,c.zan_num,c.reply_num,c.goods_rank,
                    c.img,c.parent_id,o.pay_time")
            ->join('__USERS__ u', 'u.user_id = c.user_id', 'LEFT')
            ->join('__ORDER__ o', 'o.order_id = c.order_id', 'LEFT')
            ->where($where)
            ->bind(['goods_id' => $goods_id])
            ->order("c.add_time desc")
            ->limit($page->firstRow . ',' . $page->listRows)->select();
        $replyList = M('Comment')->where(['goods_id' => $goods_id, 'parent_id' => ['>', 0]])->order("add_time desc")->select();
        $reply_logic = new ReplyLogic();
        foreach ($list as $k => &$v) {
            $list[$k]['img'] = unserialize($v['img']); // 晒单图片
            $list[$k]['parent_id'] = $reply_logic->getReplyList($v['comment_id'], 5);
            if($v['is_true'] == 0 || $v['order_id'] == 0){
                if(empty($v['dummy_mb'])){
                    $mb_arrs[]='13'.rand(112312312,987659999);
                    $mb_arrs[]='15'.rand(112312312,987659999);
                    $mb_arrs[]='17'.rand(112312312,687659999);
                    $rand_key = array_rand($mb_arrs,1);
                    M('comment')->where('comment_id',$v['comment_id'])->save(array('dummy_mb'=>$mb_arrs[$rand_key]));
                    $v['mobile'] = $mb_arrs[$rand_key]; // 用户电话
                }else{
                    $v['mobile'] = $v['dummy_mb']; //用户电话
                }
            }
            $v['mobile'] = substr_replace($v['mobile'],'****',3,4); // 晒单图片
            //虚拟订单时间处理
            if($v['add_time'] == 1525416208 || $v['add_time'] == 0){
                $v['add_time'] = rand(1528214400,time());
                M('comment')->where('comment_id',$v['comment_id'])->save(array('add_time'=>$v['add_time']));
            }

        }
        unset($v);
        $goodsLogic = new \app\home\logic\GoodsLogic();
        $commentStatistics = $goodsLogic->commentStatistics($goods_id);// 获取某个商品的评论统计
        $this->assign('commentStatistics', $commentStatistics);//评论概览
        $this->assign('commentlist', $list);// 商品评论
        $this->assign('replyList', $replyList); // 管理员回复
        $this->assign('commentType', $commentType);// 1 全部 2好评 3 中评 4差评 5晒图
        $this->assign('goods_id', $goods_id);//商品id
        $this->assign('current_count', $page_count * I('p'));//当前条
        $this->assign('count', $count);//总条数
        $this->assign('page_count', $page_count);//页数
        $this->assign('p', I('p'));//页数
        echo $this->fetch();
    }
    
    /*
     * 获取商品规格
     */
    public function goodsAttr()
    {
        $goods_id = I("get.goods_id/d", '0');
        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsAttr')->where("goods_id", $goods_id)->select(); // 查询商品属性表
        $this->assign('goods_attr_list', $goods_attr_list);
        $this->assign('goods_attribute', $goods_attribute);
        return $this->fetch();
    }
    
  
    
    /**
     * 回复显示页
     * @author dyr
     */
    public function reply()
    {
        $comment_id = I('get.comment_id/d', 1);
        $page = (I('get.page', 1) <= 0) ? 1 : I('get.page', 1);//页数
        $list_num = 30;//每页条数
        $reply_logic = new ReplyLogic();
        $reply_list = $reply_logic->getRaplyPage($comment_id, $page - 1, $list_num);
        $page_sum = ceil($reply_list['count'] / $list_num);
        $comment_info = M('comment')->where(array('comment_id' => $comment_id))->find();
        $comment_info['img'] = unserialize($comment_info['img']);
        if (empty($comment_info)) {
            $this->error('找不到该商品');
        }
        $goods_info = M('goods')->where(array('goods_id' => $comment_info['goods_id']))->find();
        $order_info = M('order')->where(array('order_id' => $comment_info['order_id']))->find();
        $goods_rank = M('comment')->where(array('goods_id' => $comment_info['goods_id'], 'store_id' => $comment_info['store_id']))->avg('goods_rank');
        $order_goods_info = M('order_goods')->where(array('goods_id' => $comment_info['goods_id'], 'order_id' => $comment_info['order_id']))->find();
        $this->assign('goods_rank', number_format($goods_rank, 1));
        $this->assign('goods_info', $goods_info);//商品内容
        $this->assign('order_info', $order_info);//订单内容
        $this->assign('order_goods_info', $order_goods_info);//订单商品内容
        $this->assign('comment_info', $comment_info);//评价内容
        $this->assign('page_sum', intval($page_sum));//总页数
        $this->assign('page_current', intval($page));//当前页
        $this->assign('reply_count', $reply_list['count']);//总回复数
        $this->assign('reply_list', $reply_list['list']);//回复列表
        $this->assign('floor', $reply_list['count'] - (intval($page) - 1) * $list_num);//楼层
        return $this->fetch();
    }
    
    /**
     * 用户收藏某一件商品
     * @param type $goods_id
     */
    public function collect_goods($goods_id)
    {
        $goods_id = I('goods_id/d');
        $goodsLogic = new \app\home\logic\GoodsLogic();
        $result = $goodsLogic->collect_goods(cookie('user_id'), $goods_id);
        exit(json_encode($result));
    }

    /**
     * @author dengxing
     * 收藏商品列表
     */
    public function claimGoods()
    {
        $filter_param = array(); // 筛选数组
        $is_have = I('get.is_have', 0); // 0所有商品 1已收藏商品
        $this->assign('is_have', $is_have);
        $doctor_phone = I('get.doctor_id', ''); // 当前医生id 发送人  环信会员用户名
        $member_phone = I('get.member_id', ''); // 信息接收人id  环信会员用户名
        $doctor_id =M('users')->where('mobile',$doctor_phone)->getField('user_id');
        $member_id =M('users')->where('mobile',$member_phone)->getField('user_id');
        $this->assign('doctor_id', $doctor_id);
        $this->assign('member_id', $member_id);
        $this->assign('token',I('token',''));

        $id = I('get.id/d', 0); // 当前分类id
        $brand_id = I('brand_id/d', 0);
        $attr = I('attr', ''); // 属性
        $sort = I('sort', 'goods_id'); // 排序
        $sort_asc = I('sort_asc', 'DESC'); // 排序
        $price = I('price', ''); // 价钱
        $start_price = trim(I('start_price', '0')); // 输入框价钱
        $end_price = trim(I('end_price', '0')); // 输入框价钱
        $sel = trim(I('sel')); //筛选货到付款,仅看有货,促销商品
        $q = I('q','');
        $this->assign('q', $q);
        if ($start_price && $end_price) $price = $start_price . '-' . $end_price; // 如果输入框有价钱 则使用输入框的价钱
        $filter_param['id'] = $id; //加入筛选条件中
        $brand_id && ($filter_param['brand_id'] = $brand_id); //加入筛选条件中
        // $spec  && ($filter_param['spec'] = $spec); //加入筛选条件中
        $attr && ($filter_param['attr'] = $attr); //加入筛选条件中
        $price && ($filter_param['price'] = $price); //加入筛选条件中
        $sel && ($filter_param['sel'] = $sel); //加入筛选条件中

        $goodsLogic = new \app\home\logic\GoodsLogic(); // 前台商品操作逻辑类

        $q = urldecode(trim(I('q', ''))); // 关键字搜索
        $q && ($_GET['q'] = $filter_param['q'] = $q); //加入帅选条件中
        //if(empty($q))
        //    $this->error ('请输入搜索关键词');
        $where = array(
            'goods_name' => array('like', '%' . $q . '%'),
            'goods_state' => 1,
            'is_on_sale' => 1,
            'sales_commission' => array('gt',0),
            'promote_commission' => array('gt',0),
        );
        /*-------------- deng start ------------*/
        // 分类菜单显示
        $cateArr = M('GoodsCategory')->where("parent_id", '0')->select();// 当前分类
        foreach ($cateArr as &$gclv){
            $goodsCate = M('GoodsCategory')->where("id", $gclv['id'])->find();// 当前分类
            $gclv['sub_menu'] = $goodsLogic->get_goods_cate($goodsCate);
        }
        /*-------------- deng start ------------*/
        $goodsCate = M('GoodsCategory')->where("id", $id)->find();// 当前分类
        // 筛选 品牌 规格 属性 价格
        $cat_id_arr = getCatGrandson($id);
        if ($goodsCate) {
            //$filter_goods_id = M('goods')->where(" goods_state = 1 and is_on_sale=1 and (sales_commission > 0 or promote_commission >0) and cat_id{$goodsCate['level']} = $id")->cache(GOODS_CACHE_TIME)->getField("goods_id", true);
            $filter_goods_id = M('goods')->where("cat_id{$goodsCate['level']} = $id")->where($where)->getField("goods_id", true);
            if ($is_have > 0) { //收藏列表
                //$filter_goods_id = M('goods g')->where(" g.goods_state = 1 and g.is_on_sale=1 and cr.user_id={$doctor_id} and (g.sales_commission > 0 or g.promote_commission >0) and g.cat_id{$goodsCate['level']} = $id")->cache(GOODS_CACHE_TIME)->join('claim_record cr', 'cr.goods_id=g.goods_id', 'right')->getField("g.goods_id", true);
                $filter_goods_id = M('goods g')->where(" g.goods_state = 1 and g.is_on_sale=1 and cr.user_id={$doctor_id} and (g.sales_commission > 0 or g.promote_commission >0) and g.cat_id{$goodsCate['level']} = $id")->join('claim_record cr', 'cr.goods_id=g.goods_id', 'left')->getField("g.goods_id", true);
            }else{
                $filter_love_goods_id = M('goods g')->where(" g.goods_state = 1 and g.is_on_sale=1 and g.sales_commission >0")->getField("g.goods_id", true);
                $filter_love_goods_id = M('claim_record')->where("user_id = {$doctor_id} and goods_id in (" . implode(',', $filter_love_goods_id) . ")")->getField("goods_id",true);
                if(is_array($filter_goods_id) && is_array($filter_love_goods_id)){
                    foreach ($filter_goods_id as $fgik=>$fgiv){
                        if(in_array($fgiv,$filter_love_goods_id))   unset($filter_goods_id[$fgik]);
                    }
                }
            }
            /*-------------- deng end ------------*/
            // 扩展分类
            //  $goodsIdArr = M('goods_category_data')->where('cat_id', $id)->cache(GOODS_CACHE_TIME)->getField('goods_id', true);
            $goodsIdArr = M('goods_category_data')->where('cat_id', $id)->getField('goods_id', true);
            if ($goodsIdArr) {
                $filter_goods_id = array_merge($filter_goods_id, $goodsIdArr);
                $filter_goods_id = array_unique($filter_goods_id);
            }
        } else {
            // $filter_goods_id = M('goods')->where(" goods_state = 1 and is_on_sale=1 and (sales_commission > 0 or promote_commission >0)")->cache(GOODS_CACHE_TIME)->getField("goods_id", true);
            $filter_goods_id = M('goods')->where(" goods_state = 1 and is_on_sale=1 and (sales_commission > 0 or promote_commission >0)")->getField("goods_id", true);
            if ($is_have > 0) { //收藏列表
                // $filter_goods_id = M('goods g')->where(" g.goods_state = 1 and g.is_on_sale=1 and cr.user_id={$doctor_id} and g.sales_commission >0")->cache(GOODS_CACHE_TIME)->join('claim_record cr', 'cr.goods_id=g.goods_id', 'right')->getField("g.goods_id", true);
                $filter_goods_id = M('goods g')->where(" g.goods_state = 1 and g.is_on_sale=1 and cr.user_id={$doctor_id} and g.sales_commission >0")->join('claim_record cr', 'cr.goods_id=g.goods_id', 'right')->getField("g.goods_id", true);
            }
            /*-------------- deng start ------------*/
            else{
                $filter_love_goods_id = M('goods g')->where(" g.goods_state = 1 and g.is_on_sale=1 and cr.user_id={$doctor_id} and g.sales_commission >0")->join('claim_record cr', 'cr.goods_id=g.goods_id', 'right')->getField("g.goods_id", true);
                //file_put_contents('claim_goods.txt',json_encode($filter_goods_id)."\r\n\r\n".json_encode($filter_love_goods_id));
                if(is_array($filter_goods_id) && is_array($filter_love_goods_id)){
                    foreach ($filter_goods_id as $fgik=>$fgiv){
                        if(in_array($fgiv,$filter_love_goods_id))unset($filter_goods_id[$fgik]);
                    }
                }
            }
            /*-------------- deng end ------------*/
        }
        // 过滤筛选的结果集里面找商品
        if ($brand_id || $price)// 品牌或者价格
        {
            $goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id, $price); // 根据 品牌 或者 价格范围 查找所有商品id
            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_1); // 获取多个筛选条件的结果 的交集
        }
        if ($sel) {
            $goods_id_4 = $goodsLogic->get_filter_selected($sel, $cat_id_arr);
            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_4);
        }
        if ($attr)// 属性
        {
            $goods_id_3 = $goodsLogic->getGoodsIdByAttr($attr); // 根据 规格 查找当所有商品id
            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_3); // 获取多个筛选条件的结果 的交集
        }
        /*-------------- deng start ------------*/
        /*$filter_menu = $goodsLogic->get_filter_menu($filter_param, 'goodsList'); // 获取显示的筛选菜单
        $filter_price = $goodsLogic->get_filter_price($filter_goods_id, $filter_param, 'goodsList'); // 筛选的价格期间
        $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id, $filter_param, 'goodsList', 1); // 获取指定分类下的筛选品牌
        $filter_attr = $goodsLogic->get_filter_attr($filter_goods_id, $filter_param, 'goodsList', 1); // 获取指定分类下的筛选属性*/
        $filter_menu = $goodsLogic->get_filter_menu($filter_param, 'claimGoods'); // 获取显示的筛选菜单
        $filter_price = $goodsLogic->get_filter_price($filter_goods_id, $filter_param, 'claimGoods'); // 筛选的价格期间
        $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id, $filter_param, 'claimGoods', 1); // 获取指定分类下的筛选品牌
        $filter_attr = $goodsLogic->get_filter_attr($filter_goods_id, $filter_param, 'claimGoods', 1); // 获取指定分类下的筛选属性
        /*-------------- deng end ------------*/
        $count = count($filter_goods_id);
        $page_count = 20;
        $page = new Page($count, $page_count);
        if ($count > 0) {
            /*-------------- deng start ------------*/
            $goods_list_where = "g.goods_id in (" . implode(',', $filter_goods_id) . ") and g.is_on_sale = 1 and g.goods_state= 1 and (g.sales_commission > 0 or g.promote_commission >0) ";
            $have_goods = M('claim_record')->where('user_id',$doctor_id)->getField('goods_id',true);
            if($is_have == 0){
                $goods_list_where .= " and st.dis > 0 ";
                $goods_list_where .= " and g.goods_id not in (" . implode(',', $have_goods) . ")";//收藏列表
            }else{
                $goods_list_where .= "and cr.user_id = {$doctor_id} ";//收藏列表
            }
            // $q = input('q','');
            if(!empty($q))  $goods_list_where .= "and goods_name like '%{$q}%'";
            $goods_list = M('goods g')->where($goods_list_where)->field('cr.*,cr.user_id as cr_user_id,g.*,st.dis')->order("g.$sort $sort_asc")->join('claim_record cr', 'cr.goods_id=g.goods_id', 'left')->join('store st','st.store_id = g.store_id','left')->group('g.goods_id')->limit($page->firstRow . ',' . $page->listRows)->select();
           /*-------------- deng end ------------*/
            $filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
            $filter_goods_id2 = array_filter($filter_goods_id2);
            if ($filter_goods_id2) $goods_images = M('goods_images')->where("goods_id in (" . implode(',', $filter_goods_id2) . ")")->cache(true)->select();
        }
        $goods_category = M('goods_category')->where('is_show=1')->getField('id,name,parent_id,level'); // 键值分类数组
        if(!empty($goods_list)){
            foreach ($goods_list as $k => &$v) {
            $v['did'] = $doctor_id;
            }
            unset($v);
        }
 
        $this->assign('goods_list', $goods_list);
        $this->assign('goods_category', $goods_category);
        $this->assign('goods_images', $goods_images);  // 相册图片
        $this->assign('filter_menu', $filter_menu);  // 筛选菜单
        //$this->assign('filter_spec',$filter_spec);  // 筛选规格
        $this->assign('filter_attr', $filter_attr);  // 筛选属性
        $this->assign('filter_brand', $filter_brand);// 列表页筛选属性 - 商品品牌
        $this->assign('filter_price', $filter_price);// 筛选的价格期间
        $this->assign('goodsCate', $goodsCate);
        $this->assign('cateArr', $cateArr);
        $this->assign('filter_param', $filter_param); // 筛选条件
        $this->assign('cat_id', $id);
        $this->assign('page', $page);// 赋值分页输出
        $this->assign('page_count', $page_count);//一页显示多少条
        $this->assign('sort_asc', $sort_asc == 'asc' ? 'desc' : 'asc');
        $this->assign("app_type",I('app_type'));
        C('TOKEN_ON', false);
        if (request()->isAjax()) {
            return $this->fetch('ajaxclaimGoods');
        } else {
            return $this->fetch('claimGoods');
        }
    }

    /**
     * @author dengxing
     * 发送图片信息
     * 文字描述 在ext里
     */
    public function sendMsg()
    {
        $goods_id = I('get.gid', 1129);
        $doctor_id = I('get.doctor_id', ''); // 当前医生id
        $member_id = I('get.member_id', ''); // 当前会员id
        if ($doctor_id == '' || $member_id == '') {
            $this->error('请先登录', url('Mobile/User/login'));
            exit;
        }
        if (!is_numeric($goods_id)) {
            echo json_encode(array('sta' => 0, 'msg' => '发送失败'));
            exit;
        }
        $goods = M('goods')->where(array('goods_id' => $goods_id))->find();
        if (empty($goods)) {
            echo json_encode(array('sta' => 0, 'msg' => '发送失败'));
            exit;
        }
        $hxmodel = new HxController();
        //获取图片
        $result = $hxmodel->uploadFile($goods['original_img']);
        if (is_array($result) && !empty($result) && isset($result['action'])) {
            $content = array(
                'uuid' => $result['entities'][0]['uuid'],
                'secret' => $result['entities'][0]['share-secret'],
                'size' => array(
                    'width' => 400,
                    'height' => 400,
                ),
            );
            $goodsinfo = url('Goods/claimGoodsInfo', array('id' => $goods_id, 'doctor_id' => $doctor_id, 'member_id' => $member_id));
            $mobile = M('users')->where(array('user_id' => $goods_id))->getField('mobile');
            $re = $hxmodel->sendGraphic($doctor_id, array($member_id), $content, 'users', array('goods_id' => $goods_id, 'goods_name' => $goods['goods_name'], 'shop_price' => $goods['shop_price'], 'send_member_id' => $doctor_id, 'send_member_mobile' => $mobile, 'receive_member_id' => $member_id, 'goods_url' => 'http://' . $_SERVER['HTTP_HOST'] . $goodsinfo));
            $result = json_decode($re, true);
            if ($result['data'][$member_id] == 'success') {
                echo json_encode(array('sta' => 1, 'msg' => '发送成功'));
                exit;
            }
        }
        echo json_encode(array('sta' => 0, 'msg' => '发送失败'));
        exit;
    }

    /**
     * @author dengxing
     * 组装json数据
     */
    public function assemblyInfo()
    {
        $goods_id=I('get.gid','');
        $doctor_id = I('get.doctor_id', ''); // 当前医生id
        $member_id = I('get.member_id', ''); // 当前会员id
        $token = I('get.to', ''); // 当前会员id
        $goods=M('goods')->where(array('goods_id'=>$goods_id))->find();
        if(!empty($goods)){
            $head_url='http://'.$_SERVER['HTTP_HOST'].'/mobile/goods/claimGoodsInfo/id/'.$goods_id.'/doctor_id/'.$doctor_id.'/member_id/'.$member_id.'/from/1';
            echo json_encode(array('goods_id'=>$goods['goods_id'],'goods_name'=>$goods['goods_name'],'goods_price'=>$goods['shop_price'],'sales_commission'=>$goods['sales_commission'],'promote_commission'=>$goods['promote_commission'],'goods_img'=>$goods['original_img'],'open_url'=>$head_url,'describe'=>$goods['goods_remark']));exit;
        }
        echo json_encode(array('goods_id'=>'','goods_name'=>'','goods_price'=>'','sales_commission'=>0,'promote_commission'=>0,'goods_img'=>'','open_url'=>'','describe'=>''));exit;
    }
    
      /**
     * @author jeep
     * 分享商品组装json
     */
    public function shareInfo()
    {
        $goods_id=I('get.id','');
        $doctor_id = I('get.doctor_id', ''); // 当前医生id
        $goods=M('goods')->where(array('goods_id'=>$goods_id))->find();
        $goodsJson['drugtext'] = $goodsJson['drugimage'] =  $goodsJson['drugtitle'] = $goodsJson['drugurl'] = '';
        if(!empty($goods)){
            $goodsJson['drugtext'] = $goods['goods_remark'];
            $goodsJson['drugimage'] = $goods['original_img'];
            $goodsJson['drugtitle'] = $goods['goods_name'];
            $goodsJson['drugurl'] = "http://".$_SERVER['HTTP_HOST']."/Mobile/goods/claimGoodsInfo/doctor_id/$doctor_id/t/1/id/".$goods['goods_id'];
        }
        echo json_encode($goodsJson);
        exit;
    }

    /**
     * @author jeep
     * app生成图片json
     */
    public function qrcode()
    {
        $goods_id=I('get.id','');
        $doctor_id = I('get.doctor_id', ''); // 当前医生id
        $goods=M('goods')->where(array('goods_id'=>$goods_id))->find();
        $goodsJson['name'] = $goodsJson['price'] = $goodsJson['url'] = '';
        if(!empty($goods)){
            $goodsJson['name'] = $goods['goods_name'];
            $goodsJson['price'] = $goods['shop_price'];
            $goodsJson['url'] = "http://".$_SERVER['HTTP_HOST']."/Mobile/goods/claimGoodsInfo/doctor_id/$doctor_id/t/1/id/".$goods['goods_id'];
        }
        echo json_encode($goodsJson);
        exit;
    }

    /**
     * @author dengxing
     * 收藏商品详情
     */
    public function claimGoodsInfo()
    {
        $_SESSION['order_preview_backurl'] = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        C('TOKEN_ON', true);
        $goodsLogic = new \app\home\logic\GoodsLogic();
        $goods_id = I("get.id/d", 0);
        $doctor_id = I('get.doctor_id', ''); // 当前医生id
        $member_id = I('get.member_id', ''); // 当前会员id
        $mb_type = I('t',0);  //分享通过web进入的
        $url_type = I('from',0);  //来源路径
        $this->assign('url_type',$url_type);

        if($this->app_type == 3 || $this->app_type == 4){
             $this->redirect(url('Mobile/Goods/goodsInfo', array('id' => $goods_id,'from'=>$url_type,'t'=>$mb_type)));
        }
        //如果为1测不需要登录，是分享出来的
        if(empty($mb_type)){
            if ($doctor_id == '' || $member_id == '') {
                $this->error('请先登录',url('Mobile/User/login'));
                exit;
            }
              
        }else{
            if(empty($member_id)){
                $member_id = session("user.user_id");
            }
            $this->assign("mb_type",$mb_type);

        }

        $this->assign("doctor_id",$doctor_id);
        $this->assign("member_id",$member_id);
        
        //登录信息
        $user = M('users')->where("user_id",$member_id)->find();

        session('doctor_id', $doctor_id);
        $goods = M('Goods')->where("goods_id", $goods_id)->find();
        //已被取消的分销直接跳正常商品
        $dis = M('store')->where('store_id',$goods['store_id'])->getField('dis');
        if($dis == 0){
             $this->redirect(url('Mobile/Goods/goodsInfo', array('id' => $goods_id)));
        }
        if (empty($goods)) {
            $this->error('此商品不存在');
        }
        if (($goods['is_on_sale'] != 1)) {
        }
        if ($goods['brand_id']) {
            $brnad = M('brand')->where("id ", $goods['brand_id'])->find();
            $goods['brand_name'] = $brnad['name'];
        }
        $goods_images_list = M('GoodsImages')->where("goods_id", $goods_id)->select(); // 商品图册
        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsAttr')->where("goods_id", $goods_id)->select(); // 查询商品属性表
        $filter_spec = $goodsLogic->get_spec($goods_id);

        $spec_goods_price = M('spec_goods_price')->where("goods_id", $goods_id)->getField("key,price,store_count"); // 规格 对应 价格 库存表
        //M('Goods')->where("goods_id=$goods_id")->save(array('click_count'=>$goods['click_count']+1 )); //统计点击数
        $commentStatistics = $goodsLogic->commentStatistics($goods_id);// 获取某个商品的评论统计
        $this->assign('spec_goods_price', json_encode($spec_goods_price, true)); // 规格 对应 价格 库存表
        $comment_where = array();
        $comment_where['g.goods_id'] = $goods_id;
        $comment_where['g.is_send'] = array('in', '0,1,2');
        $comment_where['o.pay_status'] = 1;
        $goods['sale_num'] = M('order_goods')->alias('g')->join('__ORDER__ o', 'o.order_id = g.order_id', 'LEFT')->where($comment_where)->count();

        //商品促销:1团购2抢购3优惠促销
        if ($goods['prom_type'] == 1) {
            $prom_goods = M('prom_goods')->where("id", $goods['prom_id'])->find();
            $this->assign('prom_goods', $prom_goods);// 商品促销
            $goods['flash_sale'] = get_goods_promotion($goods['goods_id']);
            $flash_sale = M('flash_sale')->where("id", $goods['prom_id'])->find();
            $this->assign('flash_sale', $flash_sale);
        }

        $this->assign('commentStatistics', $commentStatistics);//评论概览
        $this->assign('goods_attribute', $goods_attribute);//属性值
        $this->assign('goods_attr_list', $goods_attr_list);//属性列表
        $this->assign('filter_spec', $filter_spec);//规格参数
        $this->assign('goods_images_list', $goods_images_list);//商品缩略图
        $goods['market_price'] = $goods['market_price'] ? $goods['market_price'] : $goods['shop_price']; // 仿制除数为0的情况
        $goods['discount'] = round($goods['shop_price'] / $goods['market_price'], 2) * 10;
        switch ($goods['sales_model']) {
            case 1:
                $goods['sales_model_title'] = '国内现货';
                break;
            case 2:
                $goods['sales_model_title'] = '海外直邮';
                break;
            case 3:
                $goods['sales_model_title'] = '保税区发货';
                break;
            default:
                $goods['sales_model_title'] = '国内现货';
        }
        $this->assign('goods', $goods);
        if ($goods['store_id'] > 0) {
            $store = M('store')->where(array('store_id' => $goods['store_id']))->find();
            //dump($store);exit;
            if (!$store['store_logo']) {
                $store['store_logo'] = '/public/images/not_adv.jpg';
            }
            $store_user_mobile = M('users')->where('user_id=' . $store['user_id'])->getField('mobile');
            $logic = new UsersLogic();
            $storeRes = $logic->getImId($store_user_mobile, 0);
            if ($storeRes['respose_info']['data'][0]['user']['easemob_id']) {
                $storeAPIArr = array('easemob_id' => $storeRes['respose_info']['data'][0]['user']['easemob_id'], 'user_id' => $storeRes['respose_info']['data'][0]['user']['user_id']);
                $storeAPIJson = urlencode(json_encode($storeAPIArr));
                $this->assign('store_json', $storeAPIJson);
            }
            $this->assign('storeRes', $storeRes);
            $this->assign('store', $store);
        }

        //根据商品属性搞一波OTC图片
        switch ($goods['drug_attr']) {
            case '1':
                $otcImage = "http://goodyao.oss-cn-hangzhou.aliyuncs.com/icons/rotc.png";
                break;
            case '2':
                $otcImage = "http://goodyao.oss-cn-hangzhou.aliyuncs.com/icons/gotc.png";
                break;
            case '3':
                $otcImage = "http://goodyao.oss-cn-hangzhou.aliyuncs.com/icons/rx.png";
                break;
            default:
                break;
        }
        $this->assign("otcImage", $otcImage);
        $user_id = cookie('user_id');
        $collect = M('goods_collect')->where(array("goods_id" => $goods_id, "user_id" => $user_id))->count();
        $this->assign('collect', $collect);

        return $this->fetch();
    }


    /**
     * @author jeep
     * 商品预览
     */
    public function claimGoodsView()
    {
        $_SESSION['order_preview_backurl'] = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        C('TOKEN_ON', true);
        $goodsLogic = new \app\home\logic\GoodsLogic();
        $goods_id = I("get.id/d", 0);
        $doctor_id = I('get.did', 0); // 当前医生id
        $member_id = I('get.mid', 0); // 当前会员id
        $mb_type = I('t',0);  //分享通过web进入的  
        $this->assign('url_type',I('from',0)); //来源路径

        // if($this->app_type == 3 || $this->app_type == 4){
        //      $this->redirect(url('Mobile/Goods/goodsInfo', array('id' => $goods_id,'from'=>$url_type,'t'=>$mb_type)));
        // }

        $this->assign("did",$doctor_id);
        $this->assign("mid",$member_id);
        
        //登录信息
        $user = M('users')->where("user_id",$member_id)->find();
        session('doctor_id', $doctor_id);
        $goods = M('Goods')->where("goods_id", $goods_id)->find();
        //已被取消的分销直接跳正常商品
        $dis = M('store')->where('store_id',$goods['store_id'])->getField('dis');
        if($dis == 0){
             $this->redirect(url('Mobile/Goods/goodsInfo', array('id' => $goods_id)));
        }
        if (empty($goods))      $this->error('此商品不存在');
        if ($goods['brand_id']) {
            $brnad = M('brand')->where("id ", $goods['brand_id'])->find();
            $goods['brand_name'] = $brnad['name'];
        }
        $goods_images_list = M('GoodsImages')->where("goods_id", $goods_id)->select(); // 商品图册
        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsAttr')->where("goods_id", $goods_id)->select(); // 查询商品属性表
        $filter_spec = $goodsLogic->get_spec($goods_id);

        $spec_goods_price = M('spec_goods_price')->where("goods_id", $goods_id)->getField("key,price,store_count"); // 规格 对应 价格 库存表
        //M('Goods')->where("goods_id=$goods_id")->save(array('click_count'=>$goods['click_count']+1 )); //统计点击数
        $commentStatistics = $goodsLogic->commentStatistics($goods_id);// 获取某个商品的评论统计
        $this->assign('spec_goods_price', json_encode($spec_goods_price, true)); // 规格 对应 价格 库存表
        $comment_where = array();
        $comment_where['g.goods_id'] = $goods_id;
        $comment_where['g.is_send'] = array('in', '0,1,2');
        $comment_where['o.pay_status'] = 1;
        $goods['sale_num'] = M('order_goods')->alias('g')->join('__ORDER__ o', 'o.order_id = g.order_id', 'LEFT')->where($comment_where)->count();

        //商品促销:1团购2抢购3优惠促销
        if ($goods['prom_type'] == 1) {
            $prom_goods = M('prom_goods')->where("id", $goods['prom_id'])->find();
            $this->assign('prom_goods', $prom_goods);// 商品促销
            $goods['flash_sale'] = get_goods_promotion($goods['goods_id']);
            $flash_sale = M('flash_sale')->where("id", $goods['prom_id'])->find();
            $this->assign('flash_sale', $flash_sale);
        }

        $this->assign('commentStatistics', $commentStatistics);//评论概览
        $this->assign('goods_attribute', $goods_attribute);//属性值
        $this->assign('goods_attr_list', $goods_attr_list);//属性列表
        $this->assign('filter_spec', $filter_spec);//规格参数
        $this->assign('goods_images_list', $goods_images_list);//商品缩略图
        $goods['market_price'] = $goods['market_price'] ? $goods['market_price'] : $goods['shop_price']; // 仿制除数为0的情况
        $goods['discount'] = round($goods['shop_price'] / $goods['market_price'], 2) * 10;
        switch ($goods['sales_model']) {
            case 1:
                $goods['sales_model_title'] = '国内现货';
                break;
            case 2:
                $goods['sales_model_title'] = '海外直邮';
                break;
            case 3:
                $goods['sales_model_title'] = '保税区发货';
                break;
            default:
                $goods['sales_model_title'] = '国内现货';
        }
        $this->assign('goods', $goods);
        if ($goods['store_id'] > 0) {
            $store = M('store')->where(array('store_id' => $goods['store_id']))->find();
            if (!$store['store_logo']) {
                $store['store_logo'] = '/public/images/not_adv.jpg';
            }
            $store_user_mobile = M('users')->where('user_id=' . $store['user_id'])->getField('mobile');
            $logic = new UsersLogic();
            $storeRes = $logic->getImId($store_user_mobile, 0);
            if ($storeRes['respose_info']['data'][0]['user']['easemob_id']) {
                $storeAPIArr = array('easemob_id' => $storeRes['respose_info']['data'][0]['user']['easemob_id'], 'user_id' => $storeRes['respose_info']['data'][0]['user']['user_id']);
                $storeAPIJson = urlencode(json_encode($storeAPIArr));
                $this->assign('store_json', $storeAPIJson);
            }
            $this->assign('storeRes', $storeRes);
            $this->assign('store', $store);
        }
        //药事服务收藏
        $hasCollect = M("claim_record")->where("goods_id",$goods_id)->where("user_id",$doctor_id)->count();
        if($hasCollect > 0){
            $this->assign("hasCollect",2);
        }else{
             $this->assign("hasCollect",1);
        }
        return $this->fetch();
    }

    /**
     * @author dengxing
     * 收藏商品
     */
    public function claim()
    {
        $goods_id = I('get.gid', '');
        $doctor_id = I('get.doctor_id', ''); // 当前医生id
        $action = I('get.action', ''); // 1收藏  2取消收藏
        if ($doctor_id == '') {
            $this->error('请先登录', url('Mobile/User/login'));
            exit;
        }
        if (!is_numeric($goods_id)) {
            echo json_encode(array('sta' => 0, 'msg' => '操作失败'));
            exit;
        }
        $goods = M('goods')->where(array('goods_id' => $goods_id))->find();
        if (empty($goods)) {
            echo json_encode(array('sta' => 0, 'msg' => '操作失败'));
            exit;
        }
        if ($action == 1) {
            $re = M('claim_record')->where(array('goods_id' => $goods_id, 'user_id' => $doctor_id))->find();
            if (!empty($re)) {
                echo json_encode(array('sta' => 1, 'msg' => '收藏成功'));
                exit;
            } else {
                $re = M('claim_record')->insert(array('goods_id' => $goods_id, 'user_id' => $doctor_id, 'add_time' => time()));
                if ($re) echo json_encode(array('sta' => 1, 'msg' => '收藏成功'));
                exit;
            }
            echo json_encode(array('sta' => 0, 'msg' => '收藏失败'));
        } elseif ($action == 2) { //取消收藏
            $re = M('claim_record')->where(array('goods_id' => $goods_id, 'user_id' => $doctor_id))->delete();
            echo json_encode(array('sta' => 1, 'msg' => '取消收藏成功'));
            exit;
        }
    }

        /**用户获取优惠券*/
    public function get_coupon(){
        $p_url = "http://testzt.yzjia.com/zt/topic/gep/access_type/".I('access_type',0)."/app_type/".I('app_type',0)."/token/".I('token',0).".html";
        $coupon_id = I('coupon_id');
        $user = session('user');
        if(empty($user)){
            $_SESSION['order_preview_backurl'] = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
           $this->error('请先登录，再领取优惠券！', url('Mobile/user/login'));  //denglu
        }
        $coupon_info = M('coupon')->where('id',$coupon_id)->find();
        if(empty($coupon_info))     $this->error('优惠券已被抢光啦！', $p_url,'',3);  //找不到优惠券
        if($coupon_info['send_num'] >= $coupon_info['createnum'])     $this->error('优惠券已被抢光啦！', $p_url,'',3);  //找不到优惠券
        $my_coupon_count = M('coupon_list')->where('cid',$coupon_id)->where('uid',$user['user_id'])->count();
        if($my_coupon_count > 0)    $this->error('您已经领过该优惠券啦！', $p_url,'',3);  //已领取优惠券
        if($coupon_info['use_end_time'] < time())    $this->error('优惠券已过期！', $p_url,'',3);  //优惠券guoqi
        $data['cid'] = $coupon_id;
        $data['type'] = 6;
        $data['uid'] = $user['user_id'];
        $data['order_id'] = $data['use_time'] = 0;
        $data['send_time'] = time();
        $data['over_time'] = $coupon_info['use_end_time'];
        $rs = M('coupon_list')->add($data);

      $this->assign('app_type',I('app_type',0));
      $this->assign('access_type',I('access_type',0));
      $this->assign('token', I('token',0));

      return $this->fetch('useNow');

    }

}
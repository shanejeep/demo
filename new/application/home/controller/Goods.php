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
namespace app\home\controller;


use app\home\logic\GoodsLogic;
use app\home\logic\ReplyLogic;
use think\AjaxPage;

use think\Db;
use think\Page;
use think\Verify;


class Goods extends Base
{
    public function index()
    {
        return $this->fetch();
    }

    /**
     * 商品详情页
     */
    public function goodsInfo()
    {
        //  form表单提交
        C('TOKEN_ON', true);
        $goodsLogic = new GoodsLogic();
        $goods_id = I("get.id/d");
        $goods = M('Goods')->where(array('goods_id' => $goods_id))->find();
        if (empty($goods) || ($goods['is_on_sale'] != 1)) {
            $this->error('该商品已经下架', U('Index/index'));
        }
        if (cookie('user_id')) {
            $goodsLogic->add_visit_log(cookie('user_id'), $goods);
        }
        if ($goods['brand_id']) {
            $brand = M('brand')->where("id", $goods['brand_id'])->find();
            $goods['brand_name'] = $brand['name'];
            $this->assign('brand', $brand);
        }
        $goods_images_list = M('GoodsImages')->where(array('goods_id' => $goods_id))->order("img_sort asc")->select(); // 商品 图册
        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsAttr')->where("goods_id", $goods_id)->select(); // 查询商品属性表
        $filter_spec = $goodsLogic->get_spec($goods_id);

        //商品是否正在促销中        
        if ($goods['prom_type'] == 1) {
            $goods['flash_sale'] = get_goods_promotion($goods['goods_id']);
            $flash_sale = M('flash_sale')->where("id", $goods['prom_id'])->find();
            $this->assign('flash_sale', $flash_sale);
        }
        $point_rate = tpCache('shopping.point_rate');
        $spec_goods_price = M('spec_goods_price')->where(array('goods_id' => $goods_id))->getField("key,price,store_count"); // 规格 对应 价格 库存表
        M('Goods')->where("goods_id", $goods_id)->save(array('click_count' => $goods['click_count'] + 1)); //统计点击数
        $commentStatistics = $goodsLogic->commentStatistics($goods_id);// 获取某个商品的评论统计
        $store_logic = new \app\home\logic\StoreLogic();
        $commentStoreStatistics = $store_logic->storeCommentStatistics($goods['store_id']);//获取商家的评论统计
        $goodsTotalComment = $goodsLogic->getGoodsTotalComment($goods_id); //获取商品达人评价
        $this->assign('commentStoreStatistics', $commentStoreStatistics); // 商家评论概览
        $this->assign('spec_goods_price', json_encode($spec_goods_price, true)); // 规格 对应 价格 库存表
        $this->assign('navigate_goods', navigate_goods($goods_id, 1));// 面包屑导航
        $this->assign('commentStatistics', $commentStatistics);//评论概览
        $this->assign('goods_attribute', $goods_attribute);//属性值
        $this->assign('goods_attr_list', $goods_attr_list);//属性列表
        $this->assign('filter_spec', $filter_spec);//规格参数
        $this->assign('goods_images_list', $goods_images_list);//商品缩略图
        $this->assign('siblings_cate', $goodsLogic->get_siblings_cate($goods['cat_id2']));//相关分类
        $this->assign('look_see', $goodsLogic->get_look_see($goods));//看了又看
        $this->assign('goods', $goods);
        $this->assign('point_rate', $point_rate);
        $this->assign('goodsTotalComment', $goodsTotalComment);
        if ($goods['store_id'] > 0) {
            $store = M('store')->where(array('store_id' => $goods['store_id']))->find();
            empty($store['sc_id'])  ? ($store['sc_id']= 0) : $store['sc_id']=$store['sc_id'];
            $comparisonStoreStatistics = $store_logic->storeComparison($store['sc_id']);//获取业内的评论统计
            $comparisonStatistics = $store_logic->storeMatch($comparisonStoreStatistics, $comparisonStoreStatistics);//获取商家的评论统计
            $this->assign('comparisonStoreStatistics', $comparisonStoreStatistics); // 行业评论概览
            $this->assign('comparisonStatistics', $comparisonStatistics); // 商家行业百分比
            $this->assign('store', $store);
            $store_goods_class_list = M('store_goods_class')->where(array('store_id' => $goods['store_id']))->cache(true)->select();
            if ($store_goods_class_list) {
                $sub_cat = $main_cat = array();
                foreach ($store_goods_class_list as $val) {
                    if ($val['parent_id'] == 0) {
                        $main_cat[] = $val;
                    } else {
                        $sub_cat[$val['parent_id']][] = $val;
                    }
                }
                $this->assign('main_cat', $main_cat);
                $this->assign('sub_cat', $sub_cat);
            }
            return $this->fetch('detail');
        } else {
            return $this->fetch();
        }
    }

    /**
     *  查询配送地址，并执行回调函数
     */
    public function region()
    {
        $fid = I('fid/d');
        $callback = I('callback');
        $parent_region = Db::name('region')->field('id,name')->where(array('parent_id' => $fid))->select();
        echo $callback . '(' . json_encode($parent_region) . ')';
        exit;
    }

    /**
     * 商品物流配送和运费
     */
    public function dispatching()
    {
        $goods_id = I('goods_id/d');//143
        $region_id = I('region_id/d');//28242
        $goods_logic = new GoodsLogic();
        $dispatching_data = $goods_logic->getGoodsDispatching($goods_id, $region_id);
        $this->ajaxReturn($dispatching_data);
    }

    /**
     * 获取可发货地址
     */
    public function getRegion()
    {
        $goodsLogic = new GoodsLogic();
        $region_list = $goodsLogic->getRegionList();//获取配送地址列表
        $region_list['status'] = 1;
        $this->ajaxReturn($region_list);
    }


    /**
     * 商品列表页
     */
    public function goodsList()
    {

        $key = md5($_SERVER['REQUEST_URI'] . $_POST['start_price'] . '_' . $_POST['end_price']);
        $html = S($key);
        if (!empty($html)) {
            exit($html);
        }

        $filter_param = array(); // 帅选数组                        
        $id = I('get.id/d', 0); // 当前分类id
        $brand_id = I('get.brand_id/d', 0);
        
        $own_shop = I('get.own_shop/d', 0);     //自营商品
        $recommend = I('get.recommend/d', 0);    //推荐商品
        $stock = I('get.stock/d', 0);    //显示有货
        $promotion = I('get.promotion/d', 0);    //促销商品
        
        //$spec = I('get.spec',0); // 规格 
        $attr = I('get.attr', ''); // 属性
        $sort = I('get.sort', 'goods_id'); // 排序
        $sort_asc = I('get.sort_asc', 'asc'); // 排序
        $price = I('get.price', ''); // 价钱
        $start_price = trim(I('post.start_price', '0')); // 输入框价钱
        $end_price = trim(I('post.end_price', '0')); // 输入框价钱
        if ($start_price && $end_price) $price = $start_price . '-' . $end_price; // 如果输入框有价钱 则使用输入框的价钱

        $filter_param['id'] = $id; //加入帅选条件中                       
        $brand_id && ($filter_param['brand_id'] = $brand_id); //加入帅选条件中
        //$spec  && ($filter_param['spec'] = $spec); //加入帅选条件中
        $attr && ($filter_param['attr'] = $attr); //加入帅选条件中
        $price && ($filter_param['price'] = $price); //加入帅选条件中

        $goodsLogic = new GoodsLogic(); // 前台商品操作逻辑类

        // 分类菜单显示
        $goodsCate = M('GoodsCategory')->where("id", $id)->find();// 当前分类
        //($goodsCate['level'] == 1) && header('Location:'.U('Home/Channel/index',array('cat_id'=>$id))); //一级分类跳转至大分类馆        
        $cateArr = $goodsLogic->get_goods_cate($goodsCate);

        // 帅选 品牌 规格 属性 价格
        //$cat_id_arr = getCatGrandson ($id);        
        if ($goodsCate) {
            $filter_goods_id = M('goods')->where(['is_on_sale' => 1, 'goods_state' => 1, 'cat_id' . $goodsCate['level'] => $id])->cache(true)->getField("goods_id", true);
        } else {
            $filter_goods_id = M('goods')->where("is_on_sale=1 and goods_state = 1")->cache(true)->getField("goods_id", true);
        }
        $this->assign('filter_goods_id_str', implode(',', $filter_goods_id));
        // 过滤筛选的结果集里面找商品
        if ($brand_id || $price)// 品牌或者价格
        {
            $goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id, $price); // 根据 品牌 或者 价格范围 查找所有商品id
            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_1); // 获取多个帅选条件的结果 的交集
        }
        
        if ($own_shop || $recommend || $stock || $promotion)// 自营商品 , 是否推荐 , 促销商品 , 显示有货
        {
            $goods_id_1 = $goodsLogic->getGoodsIdByCheckbox($own_shop, $recommend, $promotion, $stock);//根据自营商品 , 是否推荐 , 促销商品 , 显示有货 条件帅选出 商品id
            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_1); // 获取多个帅选条件的结果 的交集
        }
        
        //if($spec)// 规格
        //{
        //    $goods_id_2 = $goodsLogic->getGoodsIdBySpec($spec); // 根据 规格 查找当所有商品id
        //    $filter_goods_id = array_intersect($filter_goods_id,$goods_id_2); // 获取多个帅选条件的结果 的交集
        //}
        if ($attr)// 属性
        {
            $goods_id_3 = $goodsLogic->getGoodsIdByAttr($attr); // 根据 规格 查找当所有商品id
            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_3); // 获取多个帅选条件的结果 的交集
        }

        $filter_menu = $goodsLogic->get_filter_menu($filter_param, 'goodsList'); // 获取显示的帅选菜单
        $filter_price = $goodsLogic->get_filter_price($filter_goods_id, $filter_param, 'goodsList'); // 帅选的价格期间
        $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id, $filter_param, 'goodsList', 1); // 获取指定分类下的帅选品牌
        //$filter_spec = $goodsLogic->get_filter_spec($filter_goods_id, $filter_param, 'goodsList', 1); // 获取指定分类下的帅选规格
        $filter_attr = $goodsLogic->get_filter_attr($filter_goods_id, $filter_param, 'goodsList', 1); // 获取指定分类下的帅选属性

        $count = count($filter_goods_id);
        $page = new Page($count, 40);
        if ($count > 0) {
            $goods_list = M('goods')->alias('g')->join('__STORE__ s', 's.store_id = g.store_id')->where("goods_id", "in", implode(',', $filter_goods_id))->order("$sort $sort_asc")->limit($page->firstRow . ',' . $page->listRows)->select();
            $filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
            if ($filter_goods_id2)
                $goods_images = M('goods_images')->where("goods_id", "in", implode(',', $filter_goods_id2))->cache(true)->select();
        }
        $goods_category = M('goods_category')->where('is_show=1')->cache(true)->getField('id,name,parent_id,level'); // 键值分类数组
        $navigate_cat = navigate_goods($id); // 面包屑导航         
        $this->assign('goods_list', $goods_list);
        $this->assign('navigate_cat', $navigate_cat);
        $this->assign('goods_category', $goods_category);
        $this->assign('goods_images', $goods_images);  // 相册图片
        $this->assign('filter_menu', $filter_menu);  // 帅选菜单
        //$this->assign('filter_spec', $filter_spec);  // 帅选规格
        $this->assign('filter_attr', $filter_attr);  // 帅选属性
        $this->assign('filter_brand', $filter_brand);  // 列表页帅选属性 - 商品品牌
        $this->assign('filter_price', $filter_price);// 帅选的价格期间
        $this->assign('goodsCate', $goodsCate);
        $this->assign('cateArr', $cateArr);
        $this->assign('filter_param', $filter_param); // 帅选条件
        $this->assign('cat_id', $id);
        $this->assign('page', $page);// 赋值分页输出
        C('TOKEN_ON', false);
        $html = $this->fetch();
        S($key, $html);
        echo $html;
    }

    /**
     * @author dyr
     * 回复显示页
     */
    public function reply()
    {
        $comment_id = I('get.comment_id/d', 1);
        $page = (I('get.page', 1) <= 0) ? 1 : I('get.page', 1);//页数
        $list_num = 10;//每页条数
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
        $this->assign('goods_info', $goods_info);
        $this->assign('order_info', $order_info);
        $this->assign('order_goods_info', $order_goods_info);
        $this->assign('comment_info', $comment_info);
        $this->assign('page_sum', intval($page_sum));//总页数
        $this->assign('page_current', intval($page));//当前页
        $this->assign('reply_count', $reply_list['count']);//总回复数
        $this->assign('reply_list', $reply_list['list']);//回复列表
        return $this->fetch();
    }

    /**
     * @author dyr
     * 获取回复
     */
    public function ajaxReply()
    {
        $comment_id = I('post.comment_id/d', 1);
        $reply_logic = new ReplyLogic();
        $reply_list = $reply_logic->getReplyList($comment_id, 4);
        exit(json_encode($reply_list));
    }


    /**
     * 商品搜索列表页
     */
    public function search()
    {
        //C('URL_MODEL',0);
        $filter_param = array(); // 帅选数组                        
        $id = I('get.id/d', 0); // 当前分类id
        $brand_id = I('brand_id/d', 0);
        $sort = I('sort', 'goods_id'); // 排序
        $sort_asc = I('sort_asc', 'asc'); // 排序
        $price = I('price', ''); // 价钱
        $start_price = trim(I('start_price', '0')); // 输入框价钱
        $end_price = trim(I('end_price', '0')); // 输入框价钱
        $store_id = I('store_id/d');
        if ($start_price && $end_price) $price = $start_price . '-' . $end_price; // 如果输入框有价钱 则使用输入框的价钱
        $q = urldecode(trim(I('q', ''))); // 关键字搜索
        empty($q) && $this->error('请输入搜索词');

        $id && ($filter_param['id'] = $id); //加入帅选条件中                       
        $brand_id && ($filter_param['brand_id'] = $brand_id); //加入帅选条件中
        $price && ($filter_param['price'] = $price); //加入帅选条件中
        $q && ($_GET['q'] = $filter_param['q'] = $q); //加入帅选条件中

        $goodsLogic = new GoodsLogic(); // 前台商品操作逻辑类

        $where = array(
            'is_on_sale' => 1,
            'goods_state' => 1
        );
        if ($store_id) {
            $where['store_id'] = $store_id;
        }

        //引入
        if (file_exists(PLUGIN_PATH . 'coreseek/sphinxapi.php')) {
            require_once(PLUGIN_PATH . 'coreseek/sphinxapi.php');
            $cl = new \SphinxClient();
            $cl->SetServer(C('SPHINX_HOST'), C('SPHINX_PORT'));
            $cl->SetConnectTimeout(10);
            $cl->SetArrayResult(true);
            $cl->SetMatchMode(SPH_MATCH_ANY);
            $res = $cl->Query($q, "mysql");
            if ($res) {
                $goods_id_array = array();
                if(array_key_exists('matches',$res)){
                    foreach ($res['matches'] as $key => $value) {
                        $goods_id_array[] = $value['id'];
                    }
                }
                if (!empty($goods_id_array)) {
                    $where['goods_id'] = array('in', $goods_id_array);
                } else {
                    $where['goods_id'] = 0;
                }
            } else {
                $where['goods_name'] = array('like', '%' . $q . '%');
            }
        } else {
            $where['goods_name'] = array('like', '%' . $q . '%');
        }

        if ($id) {
            // 分类菜单显示
            $goodsCate = M('GoodsCategory')->where("id", $id)->find();// 当前分类
//            $cat_id_arr = getCatGrandson ($id);
            $where['cat_id' . $goodsCate['level']] = $id;
        }

        $search_goods = M('goods')->where($where)->getField('goods_id,cat_id3');
        $filter_goods_id = array_keys($search_goods);
        $filter_cat_id = array_unique($search_goods); // 分类需要去重
        if ($filter_cat_id) {
            $cateArr = M('goods_category')->where("id", "in", implode(',', $filter_cat_id))->select();
            $tmp = $filter_param;
            foreach ($cateArr as $k => $v) {
                $tmp['id'] = $v['id'];
                $cateArr[$k]['href'] = U("/Home/Goods/search", $tmp);
            }
        }
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
        if ($count > 0) {
            $goods_list = M('goods')->where(['is_on_sale' => 1, 'goods_id' => ['in', implode(',', $filter_goods_id)]])->order("$sort $sort_asc")->limit($page->firstRow . ',' . $page->listRows)->select();
            $filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
            if ($filter_goods_id2)
                $goods_images = M('goods_images')->where("goods_id", "in", implode(',', $filter_goods_id2))->select();
        }

        $this->assign('goods_list', $goods_list);
        $this->assign('goods_images', $goods_images);  // 相册图片
        $this->assign('filter_menu', $filter_menu);  // 帅选菜单
        $this->assign('filter_brand', $filter_brand);  // 列表页帅选属性 - 商品品牌
        $this->assign('filter_price', $filter_price);// 帅选的价格期间
        $this->assign('cateArr', $cateArr);
        $this->assign('filter_param', $filter_param); // 帅选条件
        $this->assign('cat_id', $id);
        $this->assign('q', I('q'));
        $this->assign('page', $page);// 赋值分页输出
        C('TOKEN_ON', false);
        return $this->fetch();
    }

    /**
     * 商品咨询ajax分页
     */
    public function ajax_consult()
    {
        $goods_id = I("goods_id/d", '0');
        $consult_type = I('consult_type', '0'); // 0全部咨询  1 商品咨询 2 支付咨询 3 配送 4 售后

        $where = ['parent_id' => 0, 'goods_id' => $goods_id];
        if ($consult_type > 0) {
            $where['consult_type'] = $consult_type;
        }

        $count = M('GoodsConsult')->where($where)->count();
        $page = new AjaxPage($count, 5);
        $show = $page->show();
        $list = M('GoodsConsult')->where($where)->order("id desc")->limit($page->firstRow . ',' . $page->listRows)->select();
        $replyList = M('GoodsConsult')->where("parent_id > 0")->order("id desc")->select();

        $this->assign('consultCount', $count);// 商品咨询数量
        $this->assign('consultList', $list);// 商品咨询
        $this->assign('replyList', $replyList); // 管理员回复
        $this->assign('page', $show);// 赋值分页输出
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
//            $where = "c.is_show = 1 and c.goods_id = $goods_id and c.parent_id = 0 and c.img !='' and c.img NOT LIKE 'N;%' and c.deleted = 0";
            $where = array(
                'c.is_show' => 1,
                'c.goods_id' => $goods_id,
                'c.parent_id' => 0,
                'c.img' => ["exp", "!='' and c.img NOT LIKE 'N;%'"],
                'c.deleted' => 0
            );
        } else {
            $typeArr = array('1' => '0,1,2,3,4,5', '2' => '4,5', '3' => '3', '4' => '0,1,2');
//            $where = "c.is_show = 1 and c.goods_id = $goods_id and c.parent_id = 0 and ceil(c.goods_rank) in($typeArr[$commentType]) and c.deleted = 0";
            $where = array(
                'c.is_show' => 1,
                'c.goods_id' => $goods_id,
                'c.parent_id' => 0,
                'ceil(c.goods_rank)' => ["IN", $typeArr[$commentType]],
                'c.deleted' => 0
            );
        }
        $count = M('comment')->alias('c')->where($where)->count();
        var_dump($where);
        $page = new AjaxPage($count, 5);
        $show = $page->show();
        $list = M('comment')->alias('c')
            ->field("u.head_pic,u.nickname,c.add_time,c.spec_key_name,c.content,
                    c.impression,c.comment_id,c.zan_num,c.is_anonymous,c.reply_num,c.goods_rank,
                    c.img,c.parent_id,o.pay_time,o.pay_time as seller_comment")
            ->join('__USERS__ u', 'u.user_id = c.user_id', 'LEFT')
            ->join('__ORDER__ o ', 'o.order_id = c.order_id', 'LEFT')
            ->where($where)
            ->order("c.add_time desc")
            ->limit($page->firstRow . ',' . $page->listRows)->select();
            echo M()->getLastsql();
            echo "<pre>";
            var_dump($list);
//        $replyList = M('Comment')->where(['goods_id' => $goods_id, 'parent_id' => ['gt', 0]])->order("add_time desc")->select();
        $reply_logic = new ReplyLogic();
        foreach ($list as $k => $v) {
            $list[$k]['img'] = unserialize($v['img']); // 晒单图片
            $list[$k]['parent_id'] = $reply_logic->getReplyList($v['comment_id'], 5);
            $list[$k]['seller_comment'] =  Db::name('comment')->where(['goods_id' => $goods_id, 'parent_id' => $list[$k]['comment_id']])->order("add_time desc")->select();
        }
        $this->assign('goods_id', $goods_id);
        $this->assign('commentlist', $list);// 商品评论
//        $this->assign('replyList', $replyList); // 管理员回复
        $this->assign('page', $show);// 赋值分页输出
        return $this->fetch();
    }


    /**
     *  商品咨询
     */
    public function goodsConsult()
    {
        //  form表单提交
        C('TOKEN_ON', true);
        $goods_id = I("goods_id/d", '0'); // 商品id
        $consult_type = I("consult_type", '1'); // 商品咨询类型
        $username = I("username", 'TPshop用户'); // 网友咨询
        $content = I("content"); // 咨询内容

        $verify = new Verify();
        if (!$verify->check(I('post.verify_code'), 'consult')) {
            $this->error("验证码错误");
        }

        $goodsConsult = M('goodsConsult');
        if (!$goodsConsult->autoCheckToken($_POST)) {
            $this->error('你已经提交过了!', U('/Home/Goods/goodsInfo', array('id' => $goods_id)));
            exit;
        }

        $data = array(
            'goods_id' => $goods_id,
            'consult_type' => $consult_type,
            'username' => $username,
            'content' => $content,
            'add_time' => time(),
        );
        $goodsConsult->add($data);
        $this->success('咨询已提交!', U('/Home/Goods/goodsInfo', array('id' => $goods_id)));
    }

    /**
     * 用户收藏某一件商品
     */
    public function collect_goods()
    {
        $goods_id = I('goods_id/d');
        $goodsLogic = new GoodsLogic();
        $result = $goodsLogic->collect_goods(cookie('user_id'), $goods_id);
        exit(json_encode($result));
    }

    /**
     * 加入购物车弹出
     */
    public function open_add_cart()
    {
        return $this->fetch();
    }

    public function integralMall()
    {
        $cat_id = I('get.id/d');
        $minValue = I('get.minValue');
        $maxValue = I('get.maxValue');
        $brandType = I('get.brandType');
        $point_rate = tpCache('shopping.point_rate');
        $is_new = I('get.is_new', 0);
        $exchange = I('get.exchange', 0);
        $goods_where = array(
            'is_on_sale' => 1,
        );
        //积分兑换筛选
        $exchange_integral_where_array = array(array('gt', 0));
        // 分类id
        if (!empty($cat_id)) {
            $store_id_arr = M('store')->where(array('sc_id' => $cat_id))->cache(true, TPSHOP_CACHE_TIME)->getField('store_id', true);
            if (!empty($store_id_arr)) {
                $store_id_str = implode($store_id_arr, ',');
                $goods_where['store_id'] = array('in', $store_id_str);
            } else {
                $goods_where['store_id'] = -1;
            }
        }
        //积分截止范围
        if (!empty($maxValue)) {
            array_push($exchange_integral_where_array, array('elt', $maxValue));
        }
        //积分起始范围
        if (!empty($minValue)) {
            array_push($exchange_integral_where_array, array('egt', $minValue));
        }
        //积分+金额
        if ($brandType == 1) {
            array_push($exchange_integral_where_array, array('exp', ' < shop_price* ' . $point_rate));
        }
        //全部积分
        if ($brandType == 2) {
            array_push($exchange_integral_where_array, array('exp', ' = shop_price* ' . $point_rate));
        }
        //新品
        if ($is_new == 1) {
            $goods_where['is_new'] = $is_new;
        }
        //我能兑换
        $user_id = cookie('user_id');
        if ($exchange == 1 && !empty($user_id)) {
            $user_pay_points = intval(M('users')->where(array('user_id' => $user_id))->getField('pay_points'));
            if ($user_pay_points !== false) {
                array_push($exchange_integral_where_array, array('lt', $user_pay_points));
            }
        }

        $goods_where['exchange_integral'] = $exchange_integral_where_array;
        $goods_list_count = M('goods')->where($goods_where)->count();
        $page = new Page($goods_list_count, 10);
        $goods_list = M('goods')->where($goods_where)->limit($page->firstRow . ',' . $page->listRows)->select();
        $store_category = M('store_class')->where('')->select();

        $this->assign('goods_list', $goods_list);
        $this->assign('page', $page->show());
        $this->assign('goods_list_count', $goods_list_count);
        $this->assign('store_category', $store_category);//商品1级分类
        $this->assign('point_rate', $point_rate);//兑换率
        $this->assign('pages', $page);
        return $this->fetch();
    }

    /**
     * 商品列表热卖商品ajax
     * @author dyr
     */
    public function ajaxHotGoods()
    {
        $p = I('p', 1);
        $item = I('i', 5);
        $filter_goods_id_str = I('filter_goods_id_str');
        $goods_where = array('is_hot' => 1, 'is_on_sale' => 1, 'goods_state' => 1);
        if ($filter_goods_id_str) {
            $goods_where['goods_id'] = array('IN', $filter_goods_id_str);
        }
        $goods = M('goods')
            ->where($goods_where)
            ->order('sort DESC')
            ->page($p, $item)
            ->cache(true, TPSHOP_CACHE_TIME)
            ->select();
        $this->assign('goods', $goods);
        return $this->fetch();
    }

    /**
     * 商品列表销售排行商品ajax
     * @author dyr
     */
    public function ajaxSalesGoods()
    {
        $p = I('p', 1);
        $item = I('i', 5);
        $filter_goods_id_str = I('filter_goods_id_str');
        $goods_where = array('is_on_sale' => 1, 'goods_state' => 1);
        if ($filter_goods_id_str) {
            $goods_where['goods_id'] = array('IN', $filter_goods_id_str);
        }
        $goods = M('goods')
            ->where($goods_where)
            ->order('sort DESC')
            ->page($p, $item)
            ->order('sales_sum desc')
            ->cache(true, TPSHOP_CACHE_TIME)
            ->select();
        $this->assign('goods', $goods);
        return $this->fetch();
    }

    /**
     * 全部商品分类
     * @author lxl
     * @time17-4-18
     */
    public function all_category(){
        return $this->fetch();
    }

    /**
     * 全部品牌列表
     * @author lxl
     * @time17-4-18
     */
    public function all_brand(){
        return $this->fetch();
    }
    
}
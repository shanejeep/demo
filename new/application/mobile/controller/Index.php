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
 * $Author: 当燃 2016-01-09
 */

namespace app\mobile\controller;

class Index extends MobileBase
{
    
    public function test()
    {
        
        echo " name : " . MODULE_NAME;
        
    }
    
    public function index()
    {
        /*
            //获取微信配置
            $wechat_list = M('wx_user')->select();
            $wechat_config = $wechat_list[0];
            $this->weixin_config = $wechat_config;        
            // 微信Jssdk 操作类 用分享朋友圈 JS            
            $jssdk = new \Mobile\Logic\Jssdk($this->weixin_config['appid'], $this->weixin_config['appsecret']);
            $signPackage = $jssdk->GetSignPackage();              
            print_r($signPackage);
        */
        $hot_goods = M('goods')->where("is_hot=1 and is_on_sale=1")->order('goods_id DESC')->limit(20)->cache(true, TPSHOP_CACHE_TIME)->select();//首页热卖商品
        $thems = M('goods_category')->where('level=1')->order('sort_order')->limit(9)->cache(true, TPSHOP_CACHE_TIME)->select();
        $this->assign('thems', $thems);
        $this->assign('hot_goods', $hot_goods);
        $favourite_goods = M('goods')->where("is_recommend=1 and is_on_sale=1")->order('goods_id DESC')->limit(20)->select();//首页推荐商品
        $this->assign('favourite_goods', $favourite_goods);
        // 用户信息
        $user_info = session('user');
        $this->assign('user_info', $user_info);
        return $this->fetch();
    }
    
    /**
     * 返回到APP
     * @return mixed
     */
    public function return_app()
    {
        // IOS
        if (in_array($this->app_type, array(1,3))) {
            $page = 'return_app_ios';
        } else { // android
            $page = 'return_app_android';
        }
        return $this->fetch($page);
    }

        /**
     * 返回到APP
     * @return mixed
     */
    public function return_app_topic()
    {
        // var_dump($this->app_type);
        // IOS
        if (in_array($this->app_type, array(1,3))) {
            $page = 'return_app_ios';
        } else { // android
            $page = 'return_app_android';
        }
        return $this->fetch($page);
    }
    
    /**
     * 分类列表显示
     */
    public function categoryList()
    {
        return $this->fetch();
    }
    
    /**
     * 模板列表
     */
    public function mobanlist()
    {
        $arr = glob("D:/wamp/www/svn_tpshop/mobile--html/*.html");
        foreach ($arr as $key => $val) {
            $html = end(explode('/', $val));
            echo "<a href='http://www.php.com/svn_tpshop/mobile--html/{$html}' target='_blank'>{$html}</a> <br/>";
        }
    }
    
    /**
     * 商品列表页
     */
    public function goodsList()
    {
        $goodsLogic = new \app\home\logic\GoodsLogic(); // 前台商品操作逻辑类
        $id = I('get.id/d', 0); // 当前分类id
        $lists = getCatGrandson($id);
        $this->assign('lists', $lists);
        return $this->fetch();
    }
    
    public function ajaxGetMore()
    {
        $p = I('p/d', 1);
        $favourite_goods = M('goods')->where("is_recommend=1 and is_on_sale=1  and goods_state = 1 ")->order('sort DESC')->page($p, 10)->cache(true, TPSHOP_CACHE_TIME)->select();//首页推荐商品
        $this->assign('favourite_goods', $favourite_goods);
        echo $this->fetch();
    }
    
    /**
     * 店铺街
     * @author dyr
     * @time 2016/08/15
     */
    public function street()
    {
        $store_class = M('store_class')->where('')->select();
        $this->assign('store_class', $store_class);//店铺分类
        return $this->fetch();
    }
    
    /**
     * ajax 获取店铺街
     */
    public function ajaxStreetList()
    {
        $p = I('p', 1);
        $sc_id = I('get.sc_id/d', '');
        $store_list = D('store')->getStreetList($sc_id, $p, 10);
        foreach ($store_list as $key => $value) {
            $store_list[$key]['goods_array'] = D('store')->getStoreGoods($value['store_id'], 3);
        }
        $this->assign('store_list', $store_list);
        echo $this->fetch();
        
    }
    
    /**
     * 品牌街
     * @author dyr
     * @time 2016/08/15
     */
    public function brand()
    {
        $brand_model = M('brand');
        $brand_where['status'] = 0;
        $brand_where['is_hot'] = 1;
        //$brand_class = $brand_model->field('cat_name')->group('cat_name')->order(array('sort'=>'desc','id'=>'asc'))->where($brand_where)->select();
        $brand_list = $brand_model->field('id,name,logo,url')->order(array('sort' => 'desc'))->cache(true)->where($brand_where)->select();
        $brand_count = count($brand_list);
        for ($i = 0; $i < $brand_count; $i++) {
            if (($i + 1) % 4 == 0) {
                $brand_list[$i]['brandLink'] = 'brandRightLink';
            } else {
                $brand_list[$i]['brandLink'] = 'brandLink';
            }
        }
        $this->assign('brand_list', $brand_list);//品牌列表
        //$this->assign('brand_class', $brand_class);//品牌分类
        return $this->fetch();
    }
    
    //微信Jssdk 操作类 用分享朋友圈 JS
    public function ajaxGetWxConfig()
    {
        $askUrl = I('askUrl');//分享URL
        $weixin_config = M('wx_user')->find(); //获取微信配置
        $jssdk = new \app\mobile\logic\Jssdk($weixin_config['appid'], $weixin_config['appsecret']);
        $signPackage = $jssdk->GetSignPackage(urldecode($askUrl));
        if ($signPackage) {
            $this->ajaxReturn($signPackage, 'JSON');
        } else {
            return false;
        }
    }
}
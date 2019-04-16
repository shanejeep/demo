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

use think\Db;
use app\home\logic\UsersLogic;
use app\home\controller\Uploadify;

class Cart extends MobileBase
{
    
    public $cartLogic; // 购物车逻辑操作类    
    public $user_id = 0;
    public $user = array();
    
    /**
     * 析构流函数
     */
    public function __construct()
    {
        parent::__construct();
        $this->cartLogic = new \app\home\logic\CartLogic();
        if (session('?user')) {
            $user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;
            $this->user_id = $user['user_id'];
            $this->assign('user', $user); //存储用户信息
            // 给用户计算会员价 登录前后不一样
            //if($user){
            //    $user[discount] = (empty($user[discount])) ? 1 : $user[discount];
            //    M('Cart')->execute("update `__PREFIX__cart` set member_goods_price = goods_price * {$user[discount]} where (user_id ={$user[user_id]} or session_id = '{$this->session_id}') and prom_type = 0");
            //}
            
        }
    }
    
    //商品列表
    public function cart()
    {
        $cart_cnt = M('cart')->where('user_id=' . $this->user_id)->count();
        $this->assign('cart_cnt', $cart_cnt);
        return $this->fetch('cart');
    }

    //预约列表
    public function subscribe()
    {
        $cart_cnt = M('cart')->where('user_id=' . $this->user_id)->count();
        $this->assign('cart_cnt', $cart_cnt);
        return $this->fetch('subscribe');
    }
    
    /**
     * 将商品加入购物车
     */
    function addCart()
    {
        $goods_id = I("goods_id/d"); // 商品id
        $goods_num = I("goods_num/d");// 商品数量
        $goods_spec = I("goods_spec"); // 商品规格                
        $goods_spec = json_decode($goods_spec, true); //app 端 json 形式传输过来
        $unique_id = I("unique_id"); // 唯一id  类似于 pc 端的session id
        $user_id = I("user_id/d", 0); // 用户id
        $result = $this->cartLogic->addCart($goods_id, $goods_num, $goods_spec, $unique_id, $user_id); // 将商品加入购物车
        exit(json_encode($result));
    }
    
    /**
     * ajax 将商品加入购物车
     */
    function ajaxAddCart()
    {
        $goods_id = I("goods_id/d"); // 商品id
        $goods_num = I("goods_num/d");// 商品数量
        $goods_spec = I("goods_spec/a"); // 商品规格
        $result = $this->cartLogic->addCart($goods_id, $goods_num, $goods_spec, $this->session_id, $this->user_id); // 将商品加入购物车
        exit(json_encode($result));
    }
    
    /*
     * 请求获取购物车列表
     */
    public function cartList()
    {
        $cart_form_data = $_POST["cart_form_data"]; // goods_num 购物车商品数量
        $cart_form_data = json_decode($cart_form_data, true); //app 端 json 形式传输过来
        
        $unique_id = I("unique_id"); // 唯一id  类似于 pc 端的session id
        $user_id = I("user_id/d"); // 用户id
        $where['session_id'] = $unique_id; // 默认按照 $unique_id 查询
        $user_id && $where['user_id'] = $user_id; // 如果这个用户已经登录则按照用户id查询
        $cartList = M('Cart')->where($where)->getField("id,goods_num,selected");
        
        if ($cart_form_data) {
            // 修改购物车数量 和勾选状态
            foreach ($cart_form_data as $key => $val) {
                $data['goods_num'] = $val['goodsNum'];
                $data['selected'] = $val['selected'];
                $cartID = $val['cartID'];
                if (($cartList[$cartID]['goods_num'] != $data['goods_num']) || ($cartList[$cartID]['selected'] != $data['selected']))
                    M('Cart')->where("id", $cartID)->save($data);
            }
            //$this->assign('select_all', $_POST['select_all']); // 全选框
        }
        
        $result = $this->cartLogic->cartList($this->user, $unique_id, 0);
        exit(json_encode($result));
    }
    
    /**
     * 购物车第二步确定页面
     */
    public function cart2()
    {
        //初始化请求参数方便后面好返回
        $myRes = request()->get();
        $myRes['source'] = 'cart2';
        session('refer_url',$myRes);
        //直接购买获取销售模式参数为sm，购物车为s ,因为框架s是特殊参数，无法通过普通模式传参
        I('sm', 0) == 0 ?$order_sales_model = I('s', 0) :  $order_sales_model = I('sm', 0);
        $is_drug = I("is_drug",0);
        if($this->user_id > 0 && !empty($_SESSION['openid']) && empty(session('user.mobile'))){
//             $this->error('请先绑定手机号', U('User/bind_mobile'));
            $this->redirect(U('User/bind_mobile'));
        }
        if ($this->user_id == 0) {
            header("location:" . $this->login_url);
        }
        $goods_id = I("get.goods_id/d",0);
        //直接购买
        if($goods_id > 0){
            //处理购物车默认选择 如果重复则需要再次选中
            M("cart")->where('user_id',$this->user_id)->where('goods_id','neq',$goods_id)->update(['selected'=>0]);
            $cartGoodsNum = M('cart')->where('goods_id',$goods_id)->where('user_id',$this->user_id)->getField('goods_num');
            if($cartGoodsNum > 0){
                M('cart')->where('goods_id',$goods_id)->where('user_id',$this->user_id)->setField('goods_num',I("num/d",1));   //如果点击直接购买，修改购物车中数量
            }else{
                $this->cartLogic->addCart($goods_id, I("num/d",1), [], $this->session_id, $this->user_id); // 将商品加入购物车
            }
        }
        $is_idCard = 0;
        $address_id = I('address_id/d');
        if ($address_id)
            $address = M('user_address')->where("address_id", $address_id)->find();
        else
            $address = M('user_address')->where(["user_id" => $this->user_id, "is_default" => 1])->find();
        if (empty($address)) {
            header("Location: " . U('Mobile/User/add_address', $myRes));
            exit;
        } else {
            $UserLogic = new UsersLogic();
            $idCardInfo = $UserLogic->getAddressNameIdCard($address['consignee']);
            if (!empty($idCardInfo)) $is_idCard = 1;
            $this->assign('address', $address);
        }

         $info_id = I("info_id",0);
         $info = M('user_info')->field('id,user')->where("id",$info_id)->find();
        if(empty($info['user'])){
             $info = M('user_info')->field('id,user')->where("uid",$this->user_id)->find();
        }

       
        if ($this->cartLogic->cart_count($this->user_id, 1, $order_sales_model) == 0)
            $this->error('你的购物车没有选中商品', U('mobile/cart/cart'));
        $result = $this->cartLogic->cartList($this->user, $this->session_id, 1, 1, $order_sales_model, 1,0,$is_drug); // 获取购物车商品
        // 检查是否相同销售模式产品
        /*if (count($result['sales_model_list']) > 1) {
            $this->error('请选择相同销售模式商品', 'Cart/cart');
        }
        if(!$order_sales_model) $order_sales_model = $result['sales_model_list'][0];*/
        $this->assign('s', $order_sales_model);
        
        $store_id_arr = M('cart')->where("user_id = {$this->user_id} and selected = 1  and is_drug={$is_drug} and sales_model={$order_sales_model}")->getField('store_id', true); // 获取所有店铺id
        $storeList = M('store')->where("store_id in (" . implode(',', $store_id_arr) . ")")->cache(true, TPSHOP_CACHE_TIME)->select(); // 找出所有商品对应的店铺id
        
        $shippingList = M('shipping_area')->where(" store_id in (" . implode(',', $store_id_arr) . ") and is_default = 1 and is_close = 1")->group("store_id,shipping_code")->getField('shipping_area_id,shipping_code,store_id');// 物流公司
        $shippingList2 = M('plugin')->where("type = 'shipping'")->cache(true, TPSHOP_CACHE_TIME)->getField('code,name'); // 查找物流插件
        foreach ($shippingList as $k => $v)
            $shippingList[$k]['name'] = $shippingList2[$v['shipping_code']];
        $sql = "select c1.name,c1.money,c1.condition,c1.isplatform,c2.store_id, c2.* from __PREFIX__coupon as c1 inner join __PREFIX__coupon_list as c2  on c2.cid = c1.id and c1.type in(0,1,2,3,5) and c2.order_id = 0 
            where c2.uid = {$this->user_id}  and " . time() . " < c1.use_end_time and (c2.store_id in (" . implode(',', $store_id_arr) . ") or c2.store_id = 0) and c2.over_time >=" . time();
        $all_couponList = Db::query($sql);
        // 找出这个用户的优惠券 没过期的  并且 订单金额达到 condition 优惠券指定标准的优惠券]
        // 店铺优惠券
        foreach ($result['store_price'] as $k => $v) {
            foreach ($all_couponList as $key => $val) {
                if ($k == $val['store_id'] && $val['condition'] <= $v) {
                    $couponList[$k][] = $val;
                }
            }
        }
        // 平台优惠券
        foreach ($all_couponList as $key => $val) {
            if ($val['store_id'] == 0 && $val['condition'] <= $result['total_price']['total_fee']) {
                $couponList[0][] = $val;
            }
        }
        //页面标题
        $is_drug == 0 ? $pagetitle = "确认订单" : $pagetitle = "医之佳";
        //修改订单为预约
        $is_drug == 0 ? $orderword = "订单" : $orderword = "预约";
        $is_drug == 0 ? $goodsword = "商品" : $goodsword = "药品预约";
        $this->assign("is_drug",$is_drug);
        $this->assign("info_id",$info['id']);
         $this->assign("info_name",$info['user']);
        $this->assign("address",$address);
        $this->assign("pagetitle",$pagetitle);
        $this->assign("orderword",$orderword);
        $this->assign("goodsword",$goodsword);
        $this->assign('is_idCard', $is_idCard);
        $this->assign('storeList', $storeList); // 店铺列表
        $this->assign('couponList', $couponList); // 优惠券列表
        $this->assign('shippingList', $shippingList); // 物流公司
        $this->assign('cartList', $result['cartList']); // 购物车的商品
        $this->assign('order_sales_model', $order_sales_model); // 销售模式
        $this->assign('total_price', $result['total_price']); // 总计
        return $this->fetch();
    }
    
    /**
     * ajax 获取订单商品价格 或者提交 订单
     * 购物车的is_drug 和订单的is_drug 都是区分是否处方药，有别于goods的is_drug ,goods的is_drug 是区分是否药品
     */
    public function cart3()
    {
        if ($this->user_id == 0) exit(json_encode(array('status' => -100, 'msg' => "登录超时请重新登录!", 'result' => null))); // 返回结果状态
        $address_id = I("address_id/d"); //  收货地址id
        $shipping_code = I("shipping_code/a"); //  物流编号
        $user_note = I('user_note/a'); // 给卖家留言        
        $couponTypeSelect = I("couponTypeSelect/a"); //  优惠券类型  1 下拉框选择优惠券 2 输入框输入优惠券代码
        $coupon_id = I("coupon_id/a"); //  优惠券id
        $couponCode = I("couponCode/a"); //  优惠券代码
        $invoice_title = I('invoice_title'); // 发票
        $pay_points = I("pay_points/d", 0); //  使用积分
        $user_money = I("user_money/f", 0); //  使用余额
        $sales_model_id = I("s/d", 0); //  销售模式ID
        $user_true['true_name']=I("true_name");
        $user_true['id_card']=I("id_card");
        $is_drug = I('is_drug',0);
        $info_id = I('info_id',0);
        $user_money = $user_money ? $user_money : 0;
        if ($this->cartLogic->cart_count($this->user_id, 1, $sales_model_id) == 0) exit(json_encode(array('status' => -2, 'msg' => '你的购物车没有选中商品', 'result' => null))); // 返回结果状态
        if (!$address_id) exit(json_encode(array('status' => -3, 'msg' => '请先填写收货人信息', 'result' => null))); // 返回结果状态
        if (!$shipping_code) exit(json_encode(array('status' => -4, 'msg' => '请选择物流信息', 'result' => null))); // 返回结果状态
        $address = M('UserAddress')->where("address_id", $address_id)->find();
        $order_goods = M('cart')->where(["user_id" => $this->user_id, "selected" => 1,"is_drug"=>$is_drug , "sales_model" => $sales_model_id])->select();
        $yj_money = array();
        if (!empty($order_goods)) {
            foreach ($order_goods as $k => $v) {
                $sales_commission=M('goods')->where(["goods_id" => $v['goods_id']])->getField('sales_commission');
                if (isset($yj_money[$v['store_id']])) {
                    $yj_money[$v['store_id']] +=$sales_commission*$v['goods_num'] ;
                } else {
                    $yj_money[$v['store_id']] = $sales_commission*$v['goods_num'] ;
                }
            }
        }
        $result = calculate_price($this->user_id, $order_goods, $shipping_code, 0, $address[province], $address[city], $address[district], $pay_points, $user_money, $coupon_id, $couponCode);
        if ($result['status'] < 0) exit(json_encode($result));
        $car_price = array(
            'goods_custom_duty_price' => $result['result']['goods_custom_duty_price'], // 商品关税
            'postFee' => $result['result']['shipping_price'], // 物流费
            'couponFee' => $result['result']['coupon_price'], // 优惠券
            'balance' => $result['result']['user_money'], // 使用用户余额100
            'pointsFee' => $result['result']['integral_money'], // 积分支付
            'payables' => array_sum($result['result']['store_order_amount']), // 订单总额 减去 积分 减去余额
            'goodsFee' => $result['result']['goods_price'],// 总商品价格
            'order_prom_amount' => array_sum($result['result']['store_order_prom_amount']), // 总订单优惠活动优惠了多少钱
            'store_order_prom_type' => $result['result']['store_order_prom_type'], // 每个商家订单优惠活动的id号
            'store_order_prom_id' => $result['result']['store_order_prom_id'], // 每个商家订单优惠活动的id号
            'store_order_prom_amount' => $result['result']['store_order_prom_amount'], // 每个商家订单活动优惠了多少钱
            'store_order_amount' => $result['result']['store_order_amount'], // 每个商家订单优惠后多少钱, -- 应付金额
            'store_shipping_price' => $result['result']['store_shipping_price'],  //每个商家的物流费
            'store_coupon_price' => $result['result']['store_coupon_price'],  //每个商家的优惠券抵消金额
            'store_point_count' => $result['result']['store_point_count'], // 每个商家平摊使用了多少积分
            'store_balance' => $result['result']['store_balance'], // 每个商家平摊用了多少余额
            'platform_balance' => $result['result']['platform_balance'], // //每个商家平摊用了多少平台优惠券金额
            'store_goods_price' => $result['result']['store_goods_price'], // 每个商家的商品总价
            'store_custom_duty_price' => $result['result']['store_custom_duty_price'], // 每个商家的商品关税
            'sales_model' => $sales_model_id, // 销售模式
            'yj_money' => $yj_money, // 佣金
        );

       //订单附加参数
        $params = array(
            "user_note" => $user_note,
            "user_true" => $user_true,
            "immed_buy" => 1,
            "is_drug" => $is_drug,
            "info_id" => $info_id,
        );
        // 提交订单
        if ($_REQUEST['act'] == 'submit_order') {

            //如果是处方药则需要添加病例 
            if($is_drug == 1){
                // if(empty(request()->file()))  exit(json_encode(array('status' => -3, 'msg' => '请上传病例！', 'result' => null))); // 返回结果状态
                // $img_arr = $this->up_img('bl');
                // if($img_arr == false)   exit(json_encode(array('status' => -3, 'msg' => '网络开小差啦，请重新上传！', 'result' => null))); // 返回结果状态
                I('case1') && $params['case1'] = I('case1');
                I('case2') && $params['case2'] = I('case2');
                I('case3') && $params['case3'] = I('case3');
                I('case4') && $params['case4'] = I('case4');
            }

            if (empty($coupon_id) && !empty($couponCode)) {
                foreach ($couponCode as $k => $v)
                    $coupon_id[$k] = M('CouponList')->where("code", $v)->where("store_id", $k)->getField('id');
            }

            $result = $this->cartLogic->addOrder($this->user_id, $address_id, $shipping_code, $invoice_title, $coupon_id, $car_price,$params); // 添加订单
            //订单添加过后清除referurl
            session('refer_url',null);
            exit(json_encode($result));
        }
        $return_arr = array('status' => 1, 'msg' => '计算成功', 'result' => $car_price); // 返回结果状态
        exit(json_encode($return_arr));
    }
    
    /*
     * 订单支付页面
     */
    public function cart4()
    {
        $is_drug = I("is_drug");
        // 如果是主订单号过来的, 说明可能是合并付款的
        $master_order_sn = I('master_order_sn', '');
        if ($master_order_sn) {
            $sum_order_amount = M('order')->where("master_order_sn", $master_order_sn)->sum('order_amount');
            if ($sum_order_amount == 0) {
                $order_order_list = U("User/order_list");
                header("Location: $order_order_list");
                exit;
            }
        } else {
            $order_id = I('order_id/d', 0);
            $order = M('Order')->where("order_id", $order_id)->find();
            // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
            if ($order['pay_status'] == 1) {
                $order_detail_url = U("Mobile/User/order_detail", array('id' => $order_id));
                header("Location: $order_detail_url");
            }
            // 是否是失效的订单
            if ($order['order_status'] == 5) {
                $this->error('订单已过期！',U("Mobile/User/order_list"));
                exit;
            }
        }
        
        //微信浏览器
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger'))
            $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and code in('weixin','cod')")->select();
        else
            $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and  scene in(3)")->select();
        $paymentList = convert_arr_key($paymentList, 'code');
        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if ($val['config_value']['is_bank'] == 2) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
            
            /*if ($this->access_type != 1) {
                if (($key == 'weixin' && !is_weixin()) || ($key == 'alipayMobile' && is_weixin())) {
                    unset($paymentList[$key]);
                }
                unset($paymentList['appWeixinPay']);
            }*/
            
            // APP 端
            if ($this->access_type == 1) {
                switch ($this->app_type) {
                    case 1: // 患者端IOS
                    case 2: //患者端Android
                        unset($paymentList['weixinAppDoctorPay']);
						unset($paymentList['alipayAppDoctorPay']);
						unset($paymentList['alipayWap']);
						
                        break;
                    case 3: //医生端IOS
                    case 4: //医生端Android
                        unset($paymentList['weixinAppPatientsPay']);
						unset($paymentList['alipayAppPatientsPay']);
						unset($paymentList['alipayWap']);
                        break;
                    default:
                        exit('pay Error');
                }
            } else { // WAP 端
                if (($key == 'alipayMobile' && is_weixin())) {
                    unset($paymentList[$key]);
                }
                // WAP端去掉微信支付
                unset($paymentList['weixinAppPatientsPay']);
                unset($paymentList['weixinAppDoctorPay']);
			    unset($paymentList['alipayAppPatientsPay']);
			    unset($paymentList['alipayAppDoctorPay']);
            }
    
            if ($this->access_type == 1) {
                switch ($this->app_type) {
                    case 1: // 患者端IOS
                        break;
                    case 3: //医生端IOS
                        //unset($paymentList['weixinAppDoctorPay']);
                    break;
                    case 2: //患者端Android
                    case 4: //医生端Android
                        //unset($paymentList['weixinAppPatientsPay']);
                        break;
                    default:
                        //
                }
                 unset($paymentList["weixin"]);
            }
    
            /*//判断当前浏览器显示支付方式
            if(($key == 'weixin' && !is_weixin()) || ($key == 'alipayMobile' && is_weixin())){
                unset($paymentList[$key]);
            }*/
        }
        $bank_img = include APP_PATH . 'home/bank.php'; // 银行对应图片
        //$payment = M('Plugin')->where("`type`='payment' and status = 1")->select();
        $this->assign('paymentList', $paymentList);
        $this->assign('bank_img', $bank_img);
        $this->assign('master_order_sn', $master_order_sn); // 主订单号
        $this->assign('sum_order_amount', $sum_order_amount); // 所有订单应付金额        
        $this->assign('order', $order);
        $this->assign('bankCodeList', $bankCodeList);
        $this->assign('pay_date', date('Y-m-d H:i', $order['add_time'] + 60*60*24));
        $is_drug == 1 ? $orderword = "预约" : $orderword = "订单";
        $is_drug == 1 ? $pagetitle = "医之佳" : $pagetitle = "订单支付";
        $this->assign("pagetitle",$pagetitle);
        $this->assign("orderword",$orderword);
        return $this->fetch();
    }
    
    /*
    * ajax 请求获取购物车列表
    */
    public function ajaxCartList()
    {
        $post_goods_num = I("goods_num/a"); // goods_num 购物车商品数量
        $post_cart_select = I("cart_select/a"); // 购物车选中状
        $where['is_drug']  = I('is_drug',0);
        $where['session_id'] = $this->session_id; // 默认按照 session_id 查询
        $this->user_id && $where['user_id'] = $this->user_id; // 如果这个用户已经等了则按照用户id查询
         $where['doc_id'] = ['eq',0];
        $cartList = M('Cart')->where($where)->getField("id,goods_num,selected,prom_type,prom_id,sales_model");
        if ($post_goods_num) {
            // 修改购物车数量 和勾选状态
            foreach ($post_goods_num as $key => $val) {
                $data['goods_num'] = $val < 1 ? 1 : $val;
                if ($cartList[$key]['prom_type'] == 1) //限时抢购 不能超过购买数量
                {
                    $flash_sale = M('flash_sale')->where("id", $cartList[$key]['prom_id'])->find();
                    $data['goods_num'] = $data['goods_num'] > $flash_sale['buy_limit'] ? $flash_sale['buy_limit'] : $data['goods_num'];
                }
                
                $data['selected'] = $post_cart_select[$key] ? 1 : 0;
                if (($cartList[$key]['goods_num'] != $data['goods_num']) || ($cartList[$key]['selected'] != $data['selected']))
                    M('Cart')->where("id", $key)->save($data);
            }
            $this->assign('select_all', $_POST['select_all']); // 全选框
        }
        $result = $this->cartLogic->cartList($this->user, $this->session_id, 1, 1, 0,0,0,I("is_drug",0));
        $result['default_sales_model'] = 0;
        if ($result['selectedSalesModelCnt']) {
            if ($result['selectedSalesModelCnt'][1] > 0 && $result['selectedSalesModelCnt'][2] == 0 && $result['selectedSalesModelCnt'][3] == 0) {
                $result['default_sales_model'] = 1;
            } elseif ($result['selectedSalesModelCnt'][1] == 0 && $result['selectedSalesModelCnt'][2] > 0 && $result['selectedSalesModelCnt'][3] == 0) {
                $result['default_sales_model'] = 2;
            } elseif($result['selectedSalesModelCnt'][1] == 0 && $result['selectedSalesModelCnt'][2] == 0 && $result['selectedSalesModelCnt'][3] > 0){
                $result['default_sales_model'] = 3;
            }
        }
        
        if (empty($result['total_price']))
            $result['total_price'] = Array('total_fee' => 0, 'cut_fee' => 0, 'num' => 0, 'atotal_fee' => 0, 'acut_fee' => 0, 'anum' => 0);
        $storeList = M('store')->getField("store_id,store_name"); // 找出商家
        $this->assign('storeList', $storeList); // 商家列表       
        $this->assign('cartList', $result['cartList']); // 购物车的商品
        $this->assign('selectedSalesModelCnt', $result['selectedSalesModelCnt']); // 购物车的商品
        $this->assign('default_sales_model', $result['default_sales_model']);
        $this->assign('total_price', $result['total_price']); // 总计    
        $this->assign("is_drug",I('is_drug',0));   
        $fee_val = (I("is_drug",0) == 0 ) ? "去结算" : "去预约" ;
        $this->assign("fee_val",$fee_val);
        return $this->fetch('ajax_cart_list');
    }

        /*
    * ajax 请求获取购物车列表
    */
    public function ajaxCartListdoc()
    {
        $post_goods_num = I("goods_num/a"); // goods_num 购物车商品数量
        $post_cart_select = I("cart_select/a"); // 购物车选中状态
        $where['session_id'] = $this->session_id; // 默认按照 session_id 查询
        $this->user_id && $where['user_id'] = $this->user_id; // 如果这个用户已经等了则按照用户id查询
        $where['doc_id'] = ['gt',0];
        $cartList = M('Cart')->where($where)->getField("id,goods_num,selected,prom_type,prom_id,sales_model");
        if ($post_goods_num) {
            // 修改购物车数量 和勾选状态
            foreach ($post_goods_num as $key => $val) {
                $data['goods_num'] = $val < 1 ? 1 : $val;
                if ($cartList[$key]['prom_type'] == 1) //限时抢购 不能超过购买数量
                {
                    $flash_sale = M('flash_sale')->where("id", $cartList[$key]['prom_id'])->find();
                    $data['goods_num'] = $data['goods_num'] > $flash_sale['buy_limit'] ? $flash_sale['buy_limit'] : $data['goods_num'];
                }
                
                $data['selected'] = $post_cart_select[$key] ? 1 : 0;
                if (($cartList[$key]['goods_num'] != $data['goods_num']) || ($cartList[$key]['selected'] != $data['selected']))
                    M('Cart')->where("id", $key)->save($data);
            }
            $this->assign('select_all', $_POST['select_all']); // 全选框
        }
        $result = $this->cartLogic->cartList($this->user, $this->session_id, 1, 1, 0);
        $result['default_sales_model'] = 0;
        if ($result['selectedSalesModelCnt']) {
            if ($result['selectedSalesModelCnt'][1] > 0 && $result['selectedSalesModelCnt'][2] == 0 && $result['selectedSalesModelCnt'][3] == 0) {
                $result['default_sales_model'] = 1;
            } elseif ($result['selectedSalesModelCnt'][1] == 0 && $result['selectedSalesModelCnt'][2] > 0 && $result['selectedSalesModelCnt'][3] == 0) {
                $result['default_sales_model'] = 2;
            } elseif($result['selectedSalesModelCnt'][1] == 0 && $result['selectedSalesModelCnt'][2] == 0 && $result['selectedSalesModelCnt'][3] > 0){
                $result['default_sales_model'] = 3;
            }
        }
        
        if (empty($result['total_price']))
            $result['total_price'] = Array('total_fee' => 0, 'cut_fee' => 0, 'num' => 0, 'atotal_fee' => 0, 'acut_fee' => 0, 'anum' => 0);
        $storeList = M('store')->getField("store_id,store_name"); // 找出商家
        $this->assign('storeList', $storeList); // 商家列表       
        $this->assign('cartList', $result['cartList']); // 购物车的商品
        $this->assign('selectedSalesModelCnt', $result['selectedSalesModelCnt']); // 购物车的商品
        $this->assign('default_sales_model', $result['default_sales_model']);
        $this->assign('total_price', $result['total_price']); // 总计       
        return $this->fetch('ajax_cart_list_doc');
    }
    
    /*
     * ajax 获取用户收货地址 用于购物车确认订单页面
     */
    public function ajaxAddress()
    {
        $regionList = get_region_list();
        $address_list = M('UserAddress')->where("user_id ", $this->user_id)->select();
        $c = M('UserAddress')->where(array("user_id" => $this->user_id, 'is_default' => 1))->count(); // 看看有没默认收货地址
        if ((count($address_list) > 0) && ($c == 0)) // 如果没有设置默认收货地址, 则第一条设置为默认收货地址
            $address_list[0]['is_default'] = 1;
        
        $this->assign('regionList', $regionList);
        $this->assign('address_list', $address_list);
        return $this->fetch('ajax_address');
    }
    
    /**
     * ajax 删除购物车的商品
     */
    public function ajaxDelCart()
    {
        $ids = I("ids"); // 商品 ids
        $result = M("Cart")->where("id", "in", $ids)->delete(); // 删除id为5的用户数据
        $return_arr = array('status' => 1, 'msg' => '删除成功', 'result' => ''); // 返回结果状态
        exit(json_encode($return_arr));
    }
    
    /**检查是否有已下架商品**/
    public function check_on_sale()
    {
        $goods_id = I('goods_id');
        $shopauth = I('shopauth');
        $order_id = I('order_id');
        
        if ($goods_id != 0) {
            $goods_id && $is_on_sale = M('goods')->where('goods_id=' . $goods_id)->getField("is_on_sale");
            if ($is_on_sale != 1) {
                exit(json_encode(array('status' => -7, 'msg' => '商品已下架！')));
            }
            $this->ajaxReturn(array('status' => 1));
        }
        if ($shopauth != 0) {
            $goodsArr = M('cart')->where(["user_id" => $this->user_id, 'session_id' => $this->session_id])->getField('goods_id', true);
            $goodsArr && $saleArr = M('goods')->where('goods_id in (' . implode(',', $goodsArr) . ')')->getField('is_on_sale', true);
            if (in_array(0, $saleArr) || in_array(2, $saleArr)) {
                exit(json_encode(array('status' => -7, 'msg' => '包含已下架商品！')));
            }
            $this->ajaxReturn(array('status' => 1));
        }
        if ($order_id != 0) {
            $goodsArr = M('order_goods')->where('order_id = ' . $order_id)->getField('goods_id', true);
            $goodsArr && $saleArr = M('goods')->where('goods_id in (' . implode(',', $goodsArr) . ')')->getField('is_on_sale', true);
            if (in_array(0, $saleArr) || in_array(2, $saleArr)) {
                exit(json_encode(array('status' => -7, 'msg' => '包含已下架商品！')));
            }
            $this->ajaxReturn(array('status' => 1));
        }
        
    }

    /**
     * @author dengxing
     * @return mixed
     * 医生销售商品 订单预览
     */
    public function ajaxSave()
    {
        $goods_id = I("goods_id/d"); // 商品id
        $goods_num = I("goods_num/d");// 商品数量
        $goods_spec = I("goods_spec/a"); // 商品规格
        $result = $this->getCartInfo($goods_id, $goods_num, $goods_spec, $this->session_id, $this->user_id); // 将商品加入购物车

        if($result['status']=='-4'){
            exit(json_encode(array('status' => '0', 'msg' => '商品库存不足')));
        }
        //商品详情存入seesion中
        session('cart_info', serialize($result));
        $cart_info = session('cart_info');
        $cart_info=unserialize($cart_info);
        if($cart_info==false){
            exit(json_encode(array('status' => '0', 'msg' => '请稍后再试')));
        }
        exit(json_encode(array('status' => '1', 'msg' => '操作成功')));
    }

    /**
     * @param $goods_id
     * @param $goods_num
     * @param $goods_spec
     * @param $session_id
     * @param int $user_id
     * @return array
     * @author dengxing
     * 获取立即购买[销售佣金] 商品信息
     */
    public function getCartInfo($goods_id, $goods_num, $goods_spec='', $session_id, $user_id = 0)
    {
        $goods = M('Goods')->where("goods_id", $goods_id)->find(); // 找出这个商品
        $specGoodsPriceList = M('SpecGoodsPrice')->where("goods_id", $goods_id)->getField("key,key_name,price,store_count,sku"); // 获取商品对应的规格价钱 库存 条码
        $where = " session_id = '$session_id' ";
        $user_id = $user_id ? $user_id : 0;
        if ($user_id)
            $where .= "  or user_id= $user_id ";
        $catr_count = M('Cart')->where($where)->count(); // 查找购物车商品总数量
        if ($catr_count >= 20)
            return array('status' => -9, 'msg' => '购物车最多只能放20种商品', 'result' => '');
        if (!empty($specGoodsPriceList) && empty($goods_spec)) {
            // 有商品规格 但是前台没有传递过来
            return array('status' => -1, 'msg' => '必须传递商品规格', 'result' => '');
        }

        if ($goods_num <= 0)
            return array('status' => -2, 'msg' => '购买商品数量不能为0', 'result' => '');
        if (empty($goods))
            return array('status' => -3, 'msg' => '购买商品不存在', 'result' => '');
        if ($goods['prom_type'] > 0 && $user_id == 0)
            return array('status' => -101, 'msg' => '购买活动商品必须先登录', 'result' => '');

        //限时抢购 不能超过购买数量
        if ($goods['prom_type'] == 1) {
            $flash_sale = M('flash_sale')->where("id = {$goods['prom_id']} and " . time() . " > start_time and " . time() . " < end_time and goods_num > buy_num")->find(); // 限时抢购活动
            if ($flash_sale) {
                $cart_goods_num = M('Cart')->where("($where) and goods_id = {$goods['goods_id']}")->getField('goods_num');
                // 如果购买数量 大于每人限购数量
                if (($goods_num + $cart_goods_num) > $flash_sale['buy_limit']) {
                    $cart_goods_num && $error_msg = "你当前购物车已有 $cart_goods_num 件!";
                    return array('status' => -4, 'msg' => "每人限购 {$flash_sale['buy_limit']}件 $error_msg", 'result' => '');
                }
                // 如果剩余数量 不足 限购数量, 就只能买剩余数量
                if (($flash_sale['goods_num'] - $flash_sale['buy_num']) < $flash_sale['buy_limit'])
                    return array('status' => -4, 'msg' => "库存不够,你只能买" . ($flash_sale['goods_num'] - $flash_sale['buy_num']) . "件了.", 'result' => '');
            }
        }
        if (is_array($goods_spec)) {
            foreach ($goods_spec as $key => $val) { // 处理商品规格
                $spec_item[] = $val; // 所选择的规格项
            }
        }
        if (!empty($spec_item)) // 有选择商品规格
        {
            sort($spec_item);
            $spec_key = implode('_', $spec_item);
            if ($specGoodsPriceList[$spec_key]['store_count'] < $goods_num)
                return array('status' => -4, 'msg' => '商品库存不足', 'result' => '');
            $spec_price = $specGoodsPriceList[$spec_key]['price']; // 获取规格指定的价格
        } elseif (($goods['store_count'] < $goods_num)) {
            return array('status' => -4, 'msg' => '商品库存不足', 'result' => '');
        }
        $where = " goods_id = $goods_id and spec_key = '$spec_key' "; // 查询购物车是否已经存在这商品
        if ($user_id > 0)
            $where .= " and (session_id = '$session_id' or user_id = $user_id) ";
        else
            $where .= " and  session_id = '$session_id' ";
        $price = $spec_price ? $spec_price : $goods['shop_price']; // 如果商品规格没有指定价格则用商品原始价格
        // 商品参与促销
        if ($goods['prom_type'] > 0) {
            $prom = get_goods_promotion($goods_id, $user_id);
            $price = $prom['price'];
            $goods['prom_type'] = $prom['prom_type'];
            $goods['prom_id'] = $prom['prom_id'];
        } else {
            $prom_platform = M('prom_order')->where('type = 3 and isplatform = 1')->count();
            if ($prom_platform > 0) {
                $goods['prom_type'] = 3;
                $goods['prom_id'] = 1;
            }
        }
        if($spec_key==false){
            $specGoodsPriceinfo = M('SpecGoodsPrice')->where("goods_id", $goods_id)->find(); // 获取商品对应的规格价钱 库存 条码
            if(!empty($specGoodsPriceinfo)){
                $spec_key=$specGoodsPriceList['key'];
            }
        }
        ;
        ($goods['drug_attr'] == 3) ? $is_drug = 1 : $is_drug = 0;
        $data = array(
            'user_id' => $user_id,   // 用户id
            'session_id' => $session_id,   // sessionid
            'goods_id' => $goods_id,   // 商品id
            'goods_sn' => $goods['goods_sn'],   // 商品货号
            'goods_name' => $goods['goods_name'],   // 商品名称
            'market_price' => $goods['market_price'],   // 市场价
            'sales_model' => $goods['sales_model'], // 销售模式
            'goods_price' => $price,  // 购买价
            'member_goods_price' => $price,  // 会员折扣价 默认为 购买价
            'goods_num' => $goods_num, // 购买数量
            'spec_key' => "{$spec_key}", // 规格key
            'spec_key_name' => "{$specGoodsPriceList[$spec_key]['key_name']}", // 规格 key_name
            'sku' => "{$specGoodsPriceList[$spec_key]['sku']}", // 商品条形码
            'add_time' => time(), // 加入购物车时间
            'prom_type' => $goods['prom_type'],   // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
            'prom_id' => $goods['prom_id'],   // 活动id
            'store_id' => $goods['store_id'],   // 店铺id
            'selected' => 1, //选中
            'is_drug' => $is_drug,
        );
        return $data;
    }

    /**
     * @author dengxing
     * @return mixed
     * 医生销售商品 订单预览
     */
    public function order_preview()
    {
        //初始化请求参数方便后面好返回
        $myRes = request()->get();
        $myRes['source'] = 'order_preview';
        session('refer_url',$myRes);
        $order_sales_model = I('s', 1);
        if ($this->user_id > 0 && !empty($_SESSION['openid']) && empty(session('user.mobile'))) {
           $this->redirect(U('User/bind_mobile')."?referurl=".$_SERVER['HTTP_REFERER']);
        }
        if ($this->user_id == 0) {
            header("location:" . $this->login_url);

        }
        $is_idCard = 0;
        $address_id = I('address_id/d');
        if ($address_id)
            $address = M('user_address')->where("address_id", $address_id)->find();
        else
            $address = M('user_address')->where(["user_id" => $this->user_id, "is_default" => 1])->find();
        if (empty($address)) {
            header("Location: " . U('Mobile/User/add_address', $myRes));
            exit;
        } else {
            $UserLogic = new UsersLogic();
            $idCardInfo = $UserLogic->getAddressNameIdCard($address['consignee']);
            if (!empty($idCardInfo)) $is_idCard = 1;
            $this->assign('address', $address);
        }
        $this->assign('is_idCard', $is_idCard);
        //用药人信息
        $info_id = I("info_id",0);
        $info = M('user_info')->field('id,user')->where("id",$info_id)->find();
        if(empty($info['user'])){
            $info = M('user_info')->field('id,user')->where("uid",$this->user_id)->find();
        }
        $this->assign("info_id",$info['id']);
        $this->assign("info_name",$info['user']);
        //end用药信息
        $result = $this->cartLogic->cartInfoList($this->user, $this->session_id, 1, 1, $order_sales_model, 1); // 获取购物车商品
        $this->assign('s', $order_sales_model);

        $store_id_arr = array($result['cartList'][0]['store_id']); // 获取所有店铺id
        $store_id_str = implode(',', $store_id_arr);
        if(empty($store_id_str) && !empty($_SESSION['order_preview_backurl'])){
            header("Location: " . $_SESSION['order_preview_backurl']);
            exit;
        }
        $storeList = M('store')->where("store_id in (" . implode(',', $store_id_arr) . ")")->cache(true, TPSHOP_CACHE_TIME)->select(); // 找出所有商品对应的店铺id

        $shippingList = M('shipping_area')->where(" store_id in (" . implode(',', $store_id_arr) . ") and is_default = 1 and is_close = 1")->group("store_id,shipping_code")->getField('shipping_area_id,shipping_code,store_id');// 物流公司
        $shippingList2 = M('plugin')->where("type = 'shipping'")->cache(true, TPSHOP_CACHE_TIME)->getField('code,name'); // 查找物流插件
        foreach ($shippingList as $k => $v)
            $shippingList[$k]['name'] = $shippingList2[$v['shipping_code']];
        $sql = "select c1.name,c1.money,c1.condition,c1.isplatform,c2.store_id, c2.* from __PREFIX__coupon as c1 inner join __PREFIX__coupon_list as c2  on c2.cid = c1.id and c1.type in(0,1,2,3,5) and c2.order_id = 0 
            where c2.uid = {$this->user_id}  and " . time() . " < c1.use_end_time and (c2.store_id in (" . implode(',', $store_id_arr) . ") or c2.store_id = 0) and c2.over_time >=" . time();
        $all_couponList = Db::query($sql);
        // 找出这个用户的优惠券 没过期的  并且 订单金额达到 condition 优惠券指定标准的优惠券]
        // 店铺优惠券
        foreach ($result['store_price'] as $k => $v) {
            foreach ($all_couponList as $key => $val) {
                if ($k == $val['store_id'] && $val['condition'] <= $v) {
                    $couponList[$k][] = $val;
                }
            }
        }
        // 平台优惠券
        foreach ($all_couponList as $key => $val) {
            if ($val['store_id'] == 0 && $val['condition'] <= $result['total_price']['total_fee']) {
                $couponList[0][] = $val;
            }
        }
        $this->assign('storeList', $storeList); // 店铺列表
        $this->assign('couponList', $couponList); // 优惠券列表
        $this->assign('shippingList', $shippingList); // 物流公司
        $this->assign('cartList', $result['cartList']); // 购物车的商品
        $this->assign('order_sales_model', $order_sales_model); // 销售模式
        $this->assign('is_drug',I('is_drug',0));
        $this->assign('total_price', $result['total_price']); // 总计
        return $this->fetch();
    }

    /**
     * @author dengxing
     * 生成订单or 计算运费
     */
    public function createOrder()
    {
        if($this->user_id > 0 && !empty($_SESSION['openid']) && empty(session('user.mobile'))){
             $this->error('请先绑定手机号', U('User/bind_mobile'));
        }
        if ($this->user_id == 0)
            exit(json_encode(array('status' => -100, 'msg' => "登录超时请重新登录!", 'result' => null))); // 返回结果状态
        $goods_id = I("get.goods_id/d",0);
        //直接购买
        if($goods_id > 0){
            //处理购物车默认选择 如果重复则需要再次选中
            M("cart")->where('user_id',$this->user_id)->update(['selected'=>0]);
        }

        $address_id = I("address_id/d"); //  收货地址id
        $shipping_code = I("shipping_code/a"); //  物流编号
        $user_note = I('user_note/a'); // 给卖家留言
        $couponTypeSelect = I("couponTypeSelect/a"); //  优惠券类型  1 下拉框选择优惠券 2 输入框输入优惠券代码
        $coupon_id = I("coupon_id/a"); //  优惠券id
        $couponCode = I("couponCode/a"); //  优惠券代码
        $invoice_title = I('invoice_title'); // 发票
        $pay_points = I("pay_points/d", 0); //  使用积分
        $user_money = I("user_money/f", 0); //  使用余额
        $sales_model_id = I("s/d", 0); //  销售模式ID
		$user_true['true_name']=I("true_name");
        $user_true['id_card']=I("id_card");
        $info_id = I('info_id',0);
        $is_drug = I('is_drug',0);
        $user_money = $user_money ? $user_money : 0;
        // if ($this->cartLogic->cart_count($this->user_id, 1, $sales_model_id) == 0) exit(json_encode(array('status' => -2, 'msg' => '你的购物车没有选中商品', 'result' => null))); // 返回结果状态
        if (!$address_id) exit(json_encode(array('status' => -3, 'msg' => '请先填写收货人信息', 'result' => null))); // 返回结果状态
        if (!$shipping_code) exit(json_encode(array('status' => -4, 'msg' => '请选择物流信息', 'result' => null))); // 返回结果状态
        $address = M('UserAddress')->where("address_id", $address_id)->find();
        //$order_goods = M('cart')->where(["user_id" => $this->user_id, "selected" => 1, "sales_model" => $sales_model_id])->select();
        $cartListinfo = session('cart_info');
        $order_goods[0] = unserialize($cartListinfo);
        $sales_commission_money = array();
        $promote_commission_money = array();
        if (!empty($order_goods)) {
            foreach ($order_goods as $k => $v) {
                $commission = M('goods')->where(["goods_id" => $v['goods_id']])->field('sales_commission,promote_commission')->find();
                if (isset($sales_commission_money[$v['store_id']])) {
                    $sales_commission_money[$v['store_id']] += $commission['sales_commission'] * $v['goods_num'];
                } else {
                    $sales_commission_money[$v['store_id']] = $commission['sales_commission'] * $v['goods_num'];
                }
                if (isset($promote_commission_money[$v['store_id']])) {
                    $promote_commission_money[$v['store_id']] += $commission['promote_commission'] * $v['goods_num'];
                } else {
                    $promote_commission_money[$v['store_id']] = $commission['promote_commission'] * $v['goods_num'];
                }
            }
        }

        $result = calculate_price($this->user_id, $order_goods, $shipping_code, 0, $address[province], $address[city], $address[district], $pay_points, $user_money, $coupon_id, $couponCode);

        if ($result['status'] < 0) exit(json_encode($result));
        $car_price = array(
            'goods_custom_duty_price' => $result['result']['goods_custom_duty_price'], // 商品关税
            'postFee' => $result['result']['shipping_price'], // 物流费
            'couponFee' => $result['result']['coupon_price'], // 优惠券
            'balance' => $result['result']['user_money'], // 使用用户余额100
            'pointsFee' => $result['result']['integral_money'], // 积分支付
            'payables' => array_sum($result['result']['store_order_amount']), // 订单总额 减去 积分 减去余额
            'goodsFee' => $result['result']['goods_price'],// 总商品价格
            'order_prom_amount' => array_sum($result['result']['store_order_prom_amount']), // 总订单优惠活动优惠了多少钱
            'store_order_prom_type' => $result['result']['store_order_prom_type'], // 每个商家订单优惠活动的id号
            'store_order_prom_id' => $result['result']['store_order_prom_id'], // 每个商家订单优惠活动的id号
            'store_order_prom_amount' => $result['result']['store_order_prom_amount'], // 每个商家订单活动优惠了多少钱
            'store_order_amount' => $result['result']['store_order_amount'], // 每个商家订单优惠后多少钱, -- 应付金额
            'store_shipping_price' => $result['result']['store_shipping_price'],  //每个商家的物流费
            'store_coupon_price' => $result['result']['store_coupon_price'],  //每个商家的优惠券抵消金额
            'store_point_count' => $result['result']['store_point_count'], // 每个商家平摊使用了多少积分
            'store_balance' => $result['result']['store_balance'], // 每个商家平摊用了多少余额
            'platform_balance' => $result['result']['platform_balance'], // //每个商家平摊用了多少平台优惠券金额
            'store_goods_price' => $result['result']['store_goods_price'], // 每个商家的商品总价
            'store_custom_duty_price' => $result['result']['store_custom_duty_price'], // 每个商家的商品关税
            'sales_model' => $sales_model_id, // 销售模式
            'sales_commission_money' => $sales_commission_money, // 销售佣金 dengxing
            'promote_commission_money' => $promote_commission_money, // 推广佣金 dengxing
        );

        //订单附加参数
        $params = array(
            "user_note" => $user_note,
            "user_true" => $user_true,
            "immed_buy" => 2,
            "is_drug" => $is_drug,
            "info_id" => $info_id,
        );
        // 提交订单
        if ($_REQUEST['act'] == 'submit_order') {
            if (empty($coupon_id) && !empty($couponCode)) {
                foreach ($couponCode as $k => $v)
                    $coupon_id[$k] = M('CouponList')->where("code", $v)->where("store_id", $k)->getField('id');
            }

            //如果是处方药则需要添加病例
            if($is_drug == 1){
                /*if(empty(request()->file()))  exit(json_encode(array('status' => -3, 'msg' => '请上传病例！', 'result' => null))); // 返回结果状态
                $img_arr = $this->up_img('bl');
                if($img_arr == false)   exit(json_encode(array('status' => -3, 'msg' => '网络开小差啦，请重新上传！', 'result' => null))); // 返回结果状态
                $img_arr['case1'] && $params['case1'] = $img_arr['case1'];
                $img_arr['case2'] && $params['case2'] = $img_arr['case2'];
                $img_arr['case3'] && $params['case3'] = $img_arr['case3'];
                $img_arr['case4'] && $params['case4'] = $img_arr['case4'];*/
                I('case1') && $params['case1'] = I('case1');
                I('case2') && $params['case2'] = I('case2');
                I('case3') && $params['case3'] = I('case3');
                I('case4') && $params['case4'] = I('case4');
            }
            if (empty($coupon_id) && !empty($couponCode)) {
                foreach ($couponCode as $k => $v)
                    $coupon_id[$k] = M('CouponList')->where("code", $v)->where("store_id", $k)->getField('id');
            }

            $result = $this->cartLogic->addOrder($this->user_id, $address_id, $shipping_code, $invoice_title, $coupon_id, $car_price, $params); // 添加订单

//            exit(json_encode(array('status'=>1,'msg'=>'操作成功')));
            exit(json_encode($result));
        }
        $return_arr = array('status' => 1, 'msg' => '计算成功', 'result' => $car_price); // 返回结果状态
        exit(json_encode($return_arr));
    }

    //图片上传 已修改成oss
    public function up_img($img_type, $name = null, $oldurl = null)
    {
        $file = new Uploadify();
        return $file->oss_upload($img_type, $name, $oldurl);
    }
}

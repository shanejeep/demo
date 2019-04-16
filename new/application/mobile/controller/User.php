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
 * 2015-11-21
 */

namespace app\mobile\controller;

use app\home\logic\StoreLogic;
use app\home\logic\UsersLogic;
use app\mobile\logic\OrderGoodsLogic;
use app\common\logic\OrderLogic;
use think\Page;
use think\Verify;
use think\Db;
use app\common\logic\AppUser;

class User extends MobileBase
{
    
    public $user_id = 0;
    public $user = array();
    
    /*
    * 初始化操作
    */
    public function _initialize()
    {
        parent::_initialize();
        if (session('?user')) {
            $user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;
            $this->user_id = $user['user_id'];
            //用户过期订单处理
            $un_use['add_time'] = ['lt',time()-60*60*24];
            $un_use['user_id'] = ['eq',$this->user_id];
            $un_use['pay_status'] = ['eq',0];
           $no_use =  Db::name('order')->where($un_use)->count();
           if($no_use > 0){
               Db::name('order')->where($un_use)->save(['order_status'=>5]);
           }

            $this->assign('user', $user); //存储用户信息
            
        }
        $nologin = array(
            'login', 'pop_login', 'do_login', 'logout', 'verify', 'set_pwd', 'finished',
            'verifyHandle', 'reg', 'send_sms_reg_code', 'bind_mobile','find_pwd','q_login', 'check_validate_code',
            'forget_pwd', 'check_captcha', 'check_username', 'send_validate_code', 'express',
        );
        if (!$this->user_id && !in_array(ACTION_NAME, $nologin)) {
            header("location:" . $this->login_url);
            exit;
        }
        $order_status_coment = array(
            'WAITPAY' => '待付款 ', //订单查询状态 待支付
            'WAITSEND' => '待发货', //订单查询状态 待发货
            'WAITRECEIVE' => '待收货', //订单查询状态 待收货
            'WAITCCOMMENT' => '待评价', //订单查询状态 待评价
        );
        $this->assign('order_status_coment', $order_status_coment);
    }
    
    public function android_login()
    {
        return $this->fetch();
    }
    
    public function ios_login()
    {
        return $this->fetch();
    }
    
    /*
     * 用户中心首页
     */
    public function index()
    {
        $order_count = M('order')->where("user_id", $this->user_id)->count(); // 我的订单数
        $goods_collect_count = M('goods_collect')->where("user_id", $this->user_id)->count(); // 我的商品收藏
        
        $comment_where = array();
        $comment_where['user_id'] = $this->user_id;
        $comment_where['goods_id'] = array('neq', 0);
        $comment_count = M('comment')->where($comment_where)->count();//  我的商品评论数
        
        $coupon_count = M('coupon_list')->where("uid =". $this->user_id." and over_time > ".time() . " and use_time = 0 and order_id = 0")->count(); // 我的优惠券数量
        $level_name = M('user_level')->where("level_id", $this->user['level'])->getField('level_name'); // 等级名称
        $this->assign('level_name', $level_name);
        $this->assign('order_count', $order_count);
        $this->assign('goods_collect_count', $goods_collect_count);
        $this->assign('comment_count', $comment_count);
        $this->assign('coupon_count', $coupon_count);
        
        $user_id = $this->user_id;
        $count_return = M('return_goods')->where("user_id=$user_id and status<2")->count();   //退换货数量
        $wait_pay = M('order')->where("user_id = :user_id " . C('WAITPAY'))->bind(['user_id' => $user_id])->count(); //待付款数量
        $wait_receive = M('order')->where("user_id = :user_id " . C('WAITRECEIVE'))->bind(['user_id' => $user_id])->count(); //待收货数量
        $comment = DB::query("select COUNT(1) as comment from __PREFIX__order_goods as og left join __PREFIX__order as o on o.order_id = og.order_id where o.user_id = $this->user_id and og.is_send = 1 and og.is_comment = 0 ");  //我的待评论订单
        $wait_comment = $comment[0][comment];
        $count_sundry_status = array($wait_pay, $wait_receive, $wait_comment, $count_return);
        $this->assign('count_sundry_status', $count_sundry_status);  //各种数量 
        
        $storeLogic = new StoreLogic();
        $storeNum = $storeLogic->getCollectNum($this->user_id);
        $this->assign('storeNum', $storeNum);
        
        return $this->fetch();
    }
    
    
    public function logout()
    {
        session_unset();
        session_destroy();
        setcookie('cn', '', time() - 3600, '/');
        setcookie('user_id', '', time() - 3600, '/');
        //$this->success("退出成功",U('Mobile/Index/index'));
        header("Location:" . U('Mobile/Index/index'));
        exit();
    }
    
    /*
     * 账户资金
     */
    public function account()
    {
        $user = session('user');
        //获取账户资金记录
        $logic = new UsersLogic();
        $data = $logic->get_account_log($this->user_id, I('get.type'));
        $account_log = $data['result'];
        
        $this->assign('user', $user);
        $this->assign('account_log', $account_log);
        $this->assign('page', $data['show']);
        
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_account_list');
            exit;
        }
        return $this->fetch();
    }
    
    public function coupon()
    {
        //
        $logic = new UsersLogic();
        $data = $logic->get_coupon($this->user_id, $_REQUEST['type']);
        $coupon_list = $data['result'];
        $this->assign('coupon_list', $coupon_list);
        $this->assign('page', $data['show']);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_coupon_list');
            exit;
        }
        return $this->fetch();
    }
    
    /**
     *  登录
     */
    public function login()
    {
        if ($this->user_id > 0) {
            $this->redirect(U('Mobile/User/index'));
        }
        $referurl = I('reurl','');
        if(empty($referurl)){
            $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("Mobile/User/index");  
        }
        //判断是否药事分销
        $order_preview_backurl = $_SESSION['order_preview_backurl'];
        if(!empty($order_preview_backurl)){
            $referurl = $order_preview_backurl;
        }
        $this->assign('referurl', $referurl);
        return $this->fetch();
    }
    
    
    public function do_login()
    {
        $username = I('post.username');
        $password = I('post.password');
        $username = trim($username);
        $password = trim($password);
        if (I('dk') !== 'Mobile') {
            //验证码验证
            $verify_code = I('post.verify_code');
            $verify = new Verify();
            if (!$verify->check($verify_code, 'user_login')) {
                $res = array('status' => 0, 'msg' => '验证码错误');
                exit(json_encode($res));
            }
        }
        
        $logic = new UsersLogic();
        $res = $logic->login($username, $password);
        if ($res['status'] == 1) {
            $res['url'] = urldecode(I('post.referurl'));
            session('user', $res['result']);
            setcookie('user_id', $res['result']['user_id'], null, '/');
            setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
            $nickname = empty($res['result']['nickname']) ? $username : $res['result']['nickname'];
            setcookie('uname', $nickname, null, '/');
            setcookie('cn', 0, time() - 3600, '/');
            $cartLogic = new \app\home\logic\CartLogic();
            $cartLogic->login_cart_handle($this->session_id, $res['result']['user_id']);  //用户登录后 需要对购物车 一些操作
        }
        exit(json_encode($res));
    }
    
    /**
     *  注册
     */
    public function reg()
    {
        // exit('Access Forbidden');
        if ($this->user_id > 0) {
            $this->redirect(U('Mobile/User/index'));
        }
        $reg_sms_enable = tpCache('sms.regis_sms_enable');
        $reg_smtp_enable = tpCache('sms.regis_smtp_enable');
        if (IS_POST) {
            $logic = new UsersLogic();
            //验证码检验
            // $this->verifyHandle('reg');
            $username = I('post.username', '');
            $password = I('post.password', '');
            $password2 = I('post.password2', '');
            //是否开启注册验证码机制
            $code = I('post.mobile_code', '');
            $scene = I('post.scene', 1);
            
            $session_id = session_id();
            
            if (check_mobile($username)) {
                $check_code = $logic->check_validate_code($code, $username, 'phone', $session_id, $scene);
                if ($check_code['status'] != 1) {
                    $this->error($check_code['msg']);
                }
            }
            //是否开启注册邮箱验证码机制
            if (check_email($username)) {
                $check_code = $logic->check_validate_code($code, $username);
                if ($check_code['status'] != 1) {
                    $this->error($check_code['msg']);
                }
            }
            
            $data = $logic->api_reg($username, $password, $password2);
            if ($data['status'] != 1)
                $this->error($data['msg']);
            session('user', $data['result']);
            setcookie('user_id', $data['result']['user_id'], null, '/');
            setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
            $cartLogic = new \app\home\logic\CartLogic();
            $cartLogic->login_cart_handle($this->session_id, $data['result']['user_id']);  //用户登录后 需要对购物车 一些操作

            //判断是否药事分销
            $order_preview_backurl = $_SESSION['order_preview_backurl'];
            if(!empty($order_preview_backurl)){
                $this->success($data['msg'], $order_preview_backurl);
            }else{
                 $this->success($data['msg'], U('Mobile/User/index'));
            }
           
            exit;
        }
        $this->assign('regis_sms_enable', $reg_sms_enable); // 注册启用短信：
        $this->assign('regis_smtp_enable', $reg_smtp_enable); // 注册启用邮箱：
        $sms_time_out = tpCache('sms.sms_time_out') > 0 ? tpCache('sms.sms_time_out') : 120;
        $this->assign('sms_time_out', $sms_time_out); // 手机短信超时时间
        return $this->fetch();
    }

    //  绑定手机
    public function bind_mobile()
    {
        if (IS_POST) {
            $logic = new \app\home\logic\UsersLogic();
            $referurl = I('referurl');
            //验证码检验
            $username = I('post.username', '');
            $password = '12345678a';
            $code = I('post.mobile_code', '');
            $scene = I('post.scene', 1);
            $session_id = session_id();
            //是否开启注册验证码机制
            if (check_mobile($username)) {
                $reg_sms_enable = tpCache('sms.regis_sms_enable');
                // if (!$reg_sms_enable) {
                    // $this->verifyHandle('user_reg');
                // }
                $check_code = $logic->check_validate_code($code, $username, 'phone', $session_id, $scene);
                if ($check_code['status'] != 1) {
                    $this->error($check_code['msg']);
                }
            }
            $user_info=M('users')->where('mobile = '.trim($username))->find();
            if(empty($user_info)){
                $data = $logic->api_reg($username, $password, $password);
                if ($data['status'] != 1) 
                    $this->error($data['msg']);
            }else{
                $data['result']=$user_info;
            }

            //删除微信账户，建立电话账户
            $where_op['openid'] = $_SESSION['openid'];
            $opinfo=M('users')->where($where_op)->select();
            // $map['password'] = $password;
            $map=array();
            foreach ($opinfo as $k => $v) {
                if(empty($v['mobile'])){
                    $map['openid'] = $v['openid'];
                    $map['nickname'] = $v['nickname'];
                    $map['oauth'] = $v['oauth'];
                    $map['head_pic'] = $v['head_pic'];
                    $map['sex'] = $v['sex'] === null ? 0 : $v['sex'];
                    M('users')->where("user_id = ".$v['user_id'])->delete(); 
                }else{
                    M('users')->where('user_id',$v['user_id'])->save(array("openid"=>'',"oauth"=>''));
                }
            }
            if(!empty($map)){
                M('users')->where('user_id = '.$data['result']['user_id'])->save($map);
            }
            session('user', $data['result']);
            setcookie('user_id', $data['result']['user_id'], null, '/');
            setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
            $nickname = empty($data['result']['nickname']) ? $username : $data['result']['nickname'];
            setcookie('uname', $nickname, null, '/');
            $cartLogic = new \app\mobile\logic\CartLogic();
            $cartLogic->login_cart_handle($this->session_id, $data['result']['user_id']);  //用户登录后 需要对购物车 一些操作
            if(empty($referurl)){
                $referurl = U('Mobile/Cart/cart');
            }
            //判断是否药事分销
            $order_preview_backurl = $_SESSION['order_preview_backurl'];
            if(!empty($order_preview_backurl)){
                 $referurl =  $order_preview_backurl;
            }
            $this->success('绑定成功！', $referurl);
            exit;
        }
        $referurl = I("referurl");
        $this->assign('referurl', $referurl); // 手机短信超时时间
        $this->assign('regis_sms_enable', tpCache('sms.regis_sms_enable')); // 注册启用短信：
        $this->assign('regis_smtp_enable', tpCache('smtp.regis_smtp_enable')); // 注册启用邮箱：
        $sms_time_out = tpCache('sms.sms_time_out') > 0 ? tpCache('sms.sms_time_out') : 120;
        $this->assign('sms_time_out', $sms_time_out); // 手机短信超时时间
        return $this->fetch();
    }
    //  绑定手机
    public function q_login()
    {
        if (IS_POST) {
            $logic = new \app\home\logic\UsersLogic();
            $referurl = I('referurl');
            //验证码检验
            $username = I('post.username', '');
            $password = '12345678a';
            $code = I('post.mobile_code', '');
            $scene = I('post.scene', 1);
            $session_id = session_id();
            //是否开启注册验证码机制
            if (check_mobile($username)) {
                $reg_sms_enable = tpCache('sms.regis_sms_enable');
                // if (!$reg_sms_enable) {
                // $this->verifyHandle('user_reg');
                // }
                $check_code = $logic->check_validate_code($code, $username, 'phone', $session_id, $scene);
                if ($check_code['status'] != 1) {
                    $this->error($check_code['msg']);
                }
            }
            $user_info=M('users')->where('mobile = '.trim($username))->find();
            if(empty($user_info)){
                $data = $logic->api_reg($username, $password, $password);
                if ($data['status'] != 1)
                    $this->error($data['msg']);
            }else{
                $data['result']=$user_info;
            }
            if(is_weixin()){
                //删除微信账户，建立电话账户
                $where_op['openid'] = $_SESSION['openid'];
                $opinfo=M('users')->where($where_op)->select();
                // $map['password'] = $password;
                $map=array();
                foreach ($opinfo as $k => $v) {
                    if(empty($v['mobile'])){
                        $map['openid'] = $v['openid'];
                        $map['nickname'] = $v['nickname'];
                        $map['oauth'] = $v['oauth'];
                        $map['head_pic'] = $v['head_pic'];
                        $map['sex'] = $v['sex'] === null ? 0 : $v['sex'];
                        M('users')->where("user_id = ".$v['user_id'])->delete();
                    }else{
                        M('users')->where('user_id',$v['user_id'])->save(array("openid"=>'',"oauth"=>''));
                    }
                }
                if(!empty($map)){
                    M('users')->where('user_id = '.$data['result']['user_id'])->save($map);
                }
            }
            session('user', $data['result']);
            setcookie('user_id', $data['result']['user_id'], null, '/');
            setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
            $nickname = empty($data['result']['nickname']) ? $username : $data['result']['nickname'];
            setcookie('uname', $nickname, null, '/');
            $cartLogic = new \app\mobile\logic\CartLogic();
            $cartLogic->login_cart_handle($this->session_id, $data['result']['user_id']);  //用户登录后 需要对购物车 一些操作
            if(empty($referurl)){
                $referurl = U('Mobile/Cart/cart');
            }
            //判断是否药事分销
            $order_preview_backurl = $_SESSION['order_preview_backurl'];
            if(!empty($order_preview_backurl)){
                $referurl =  $order_preview_backurl;
            }
            $this->redirect($referurl);
            exit;
        }
        $referurl = I("referurl");
        $this->assign('referurl', $referurl); // 手机短信超时时间
        $this->assign('regis_sms_enable', tpCache('sms.regis_sms_enable')); // 注册启用短信：
        $this->assign('regis_smtp_enable', tpCache('smtp.regis_smtp_enable')); // 注册启用邮箱：
        $sms_time_out = tpCache('sms.sms_time_out') > 0 ? tpCache('sms.sms_time_out') : 120;
        $this->assign('sms_time_out', $sms_time_out); // 手机短信超时时间
        return $this->fetch();
    }
    
    /*
     * 订单列表
     */
    public function order_list()
    {
        $where = ' user_id=' . $this->user_id;
        //条件搜索 
        if (in_array(strtoupper(I('type')), array('WAITCCOMMENT', 'COMMENTED'))) {
            $where .= " AND order_status in(1,4) "; //代评价 和 已评价
        } elseif (I('type')) {
            $where .= C(strtoupper(I('type')));
        }
        $count = M('order')->where($where)->count();
        $Page = new Page($count, 10);
        
        $show = $Page->show();
        $order_str = "order_id DESC";
        $order_list = M('order')->order($order_str)->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        
        //获取订单商品
        //$model = new UsersLogic();
        $orderLogic = new OrderLogic();
        foreach ($order_list as $k => $v) {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            $data = $orderLogic->get_order_goods($v['order_id']);
            $order_list[$k]['goods_list'] = $data['result'];
			//获取店聊天的json
            $seller_user = M('store')->where('store_id',$v['store_id'])->getField('user_id');
            $store_user_mobile = M('users')->where('user_id',$seller_user)->getField('mobile');
            $logic = new UsersLogic();
            $storeRes = $logic->getImId($store_user_mobile, 0);
            if ($storeRes['respose_info']['data'][0]['user']['easemob_id']) {
                $storeAPIArr = array('easemob_id' => $storeRes['respose_info']['data'][0]['user']['easemob_id'], 'user_id' => $storeRes['respose_info']['data'][0]['user']['user_id']);
                $storeAPIJson = urlencode(json_encode($storeAPIArr));
                $order_list[$k]['store_json'] = $storeAPIJson;
            }
        }
        $storeList = M('store')->getField('store_id,store_name,store_qq'); // 找出所有商品对应的店铺id
        $this->assign('storeList', $storeList); // 店铺列表
        $this->assign('order_status', C('ORDER_STATUS'));
        $this->assign('shipping_status', C('SHIPPING_STATUS'));
        $this->assign('pay_status', C('PAY_STATUS'));
        $this->assign('page', $show);
        $this->assign('lists', $order_list);
        $this->assign('active', 'order_list');
        $this->assign('active_status', I('get.type'));
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_order_list');
            exit;
        }
        return $this->fetch();
    }
    
    /*
     * 订单详情
     */
    public function order_detail()
    {
        header("Content-type: text/html; charset=utf-8");
        $id = I('get.id/d');
        if (empty($id)) {
            $this->error('参数错误');
        }
        $map['order_id'] = $id;
        $map['user_id'] = $this->user_id;
        $order_info = M('order')->where($map)->find();
        $order_info = set_btn_order_status($order_info);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
        if (!$order_info) {
            $this->error('没有获取到订单信息');
            exit;
        }
        //获取订单商品
        //$model = new UsersLogic();
        $orderLogic = new OrderLogic();
        $data = $orderLogic->get_order_goods($order_info['order_id']);
        $order_info['goods_list'] = $data['result'];
        // $order_info['total_fee'] = $order_info['goods_price'] + $order_info['shipping_price'] - $order_info['integral_money'] - $order_info['coupon_price'] - $order_info['discount'];
        //上面代码满减没有算到
        $order_info['total_fee'] = $order_info['order_amount'];
        //$region_list = get_region_list();
        $store = M('store')->where("store_id", $order_info['store_id'])->find(); // 找出这个商家
        // 店铺地址id
        $ids[] = $store['province_id'];
        $ids[] = $store['city_id'];
        $ids[] = $store['district'];
        
        $ids[] = $order_info['province'];
        $ids[] = $order_info['city'];
        $ids[] = $order_info['district'];
        if (!empty($ids))
            $regionLits = M('region')->where("id in (" . implode(',', $ids) . ")")->getField("id,name");
        
        $invoice_no = M('DeliveryDoc')->where("order_id", $id)->getField('invoice_no', true);
        $order_info['invoice_no'] = implode(' , ', $invoice_no);
        
        if ($order_info['order_amount'] == 0 && !$order_info['pay_name']) {
            $order_info['pay_name'] = orderOtherPayName($order_info);
        }
        //获取订单操作记录
        $order_action = M('order_action')->where(array('order_id' => $id))->select();
		$order_ps=I("ps");
		$pay_status=M('order')->where('order_id',$order_info['order_id'])->getField("pay_status");
		if($order_ps == 1 && $pay_status == 0){
			$order_info['order_status_desc'] = "支付中";
			$this->assign('order_ps', $order_ps);
		}
      if($pay_status == 1 && $order_info['order_status'] == 5){
            $order_info['order_status_desc'] = "审核未通过";
        }
        $order_info['coupon_price'] += $order_info['platform_coupon_price'];
        $this->assign('store', $store);
        $this->assign('order_status', C('ORDER_STATUS'));
        $this->assign('shipping_status', C('SHIPPING_STATUS'));
        $this->assign('pay_status', C('PAY_STATUS'));
        $order_info['is_drug'] == 0 ? $orderword = "订单" : $orderword = "预约";
        $this->assign("orderword",$orderword);
        //$this->assign('region_list',$region_list);
        $this->assign('regionLits', $regionLits);
        $this->assign('order_info', $order_info);
        $this->assign('order_action', $order_action);
        return $this->fetch();
    }
    
    public function express()
    {
        $order_id = I('get.order_id/d', 195);
        $order_goods = M('order_goods')->where("order_id", $order_id)->select();
        $delivery = M('delivery_doc')->where("order_id", $order_id)->limit(1)->find();
        $this->assign('order_goods', $order_goods);
        $this->assign('delivery', $delivery);
        return $this->fetch();
    }
    
    /*
     * 取消订单
     */
    public function cancel_order()
    {
        $id = I('get.id/d');
        //检查是否有积分，余额支付
        $logic = new OrderLogic();
        $data = $logic->cancel_order($this->user_id, $id);
        if ($data['status'] < 0) {
            $this->error($data['msg'], U('user/order_list'));
        } else {
            $this->success($data['msg'], U('user/order_list'));
        }
        /*$this->error($data['msg']);
        $this->success($data['msg']);*/
    }
    
    /*
     * 用户地址列表
     */
    public function address_list()
    {
        $address_lists = get_user_address_list($this->user_id);
        $s = I('s', 0);
        $this->assign('s', $s);
        $region_list = get_region_list();
        $this->assign('region_list', $region_list);
        $this->assign('lists', $address_lists);
        return $this->fetch();
    }
    
    /*
     * 添加地址
     */
    public function add_address()
    {
        if (IS_POST) {
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id, 0, I('post.'));
            $resarr = session("refer_url");
            $resarr['address_id'] = $data['result'];
            if ($data['status'] != 1){
                $this->error($data['msg']);
            }elseif (I('source','') == 'cart2') {
                header('Location:' . U('Mobile/Cart/cart2', $resarr));
                exit;
            }elseif (I('source','') == 'order_preview') {
                header('Location:' . U('Mobile/Cart/order_preview', $resarr));
                exit;
            }
            $this->success($data['msg'], U('Mobile/User/address_list'));
            exit();
        }
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $this->assign('province', $p);
        $this->assign('s',$_GET['s']);
        return $this->fetch();
        
    }
    
    /*
     * 地址编辑
     */
    public function edit_address()
    {
        $s = I('s', 0);
        $id = I('id/d');
        $address = M('user_address')->where(array('address_id' => $id, 'user_id' => $this->user_id))->find();
        if (IS_POST) {
            $resarr = session("refer_url");
            $resarr['address_id'] = $id;
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id, $id, I('post.'));
            if (I('source','') == 'cart2') {
                header('Location:' . U('/Mobile/Cart/cart2', $resarr));
                exit;
            }elseif (I('source','') == 'order_preview') {
                header('Location:' . U('/Mobile/Cart/order_preview', $resarr));
                exit;
            }else
                $this->success($data['msg'], U('/Mobile/User/address_list'));
            exit();
        }
        //获取省份
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $c = M('region')->where(array('parent_id' => $address['province'], 'level' => 2))->select();
        $d = M('region')->where(array('parent_id' => $address['city'], 'level' => 3))->select();
        if ($address['twon']) {
            $e = M('region')->where(array('parent_id' => $address['district'], 'level' => 4))->select();
            $this->assign('twon', $e);
        }
        $this->assign('s', $s);
        $this->assign('province', $p);
        $this->assign('city', $c);
        $this->assign('district', $d);
        
        $this->assign('address', $address);
        return $this->fetch();
    }
    
    /*
     * 设置默认收货地址
     */
    public function set_default()
    {
        $id = I('get.id/d');
        $source = I('get.source');
        M('user_address')->where(array('user_id' => $this->user_id))->save(array('is_default' => 0));
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->save(array('is_default' => 1));
        if ($source == 'cart2') {
            header("Location:" . U('Mobile/Cart/cart2'));
        }     /*------deng start------*/
        elseif ($source == 'order_preview') {
            header('Location:' . U('/Mobile/Cart/order_preview', array('address_id' => $id)));
            exit;
        }
		/*-------deng end-------*/
		else {
            header("Location:" . U('Mobile/User/address_list'));
        }
        exit();
    }
    
    /*
     * 地址删除
     */
    public function del_address()
    {
        $id = I('get.id/d', '');
        
        $address = M('user_address')->where("address_id", $id)->find();
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->delete();
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if ($address['is_default'] == 1) {
            $address2 = M('user_address')->where("user_id", $this->user_id)->find();
            $address2 && M('user_address')->where("address_id", $address2['address_id'])->save(array('is_default' => 1));
        }
        if (!$row)
            $this->error('操作失败', U('User/address_list'));
        else
            $this->success("操作成功", U('User/address_list'));
    }


    /*
     *用药人列表
     */
    public function info_list()
    {
        $lists = M('user_info')->where(array('uid' => $this->user_id))->select();
        $this->assign('s', I('s', 0));
        $this->assign('source', I('source', 0));
         $this->assign('address_id', I('address_id', 0));
        $this->assign('lists', $lists);
        $this->assign("info_id",I('info_id'));
        $this->assign('active', 'info_list');
        
        return $this->fetch();
    }
    
    /*
     * 添加用药信息
     */
    public function add_info()
    {
        if (IS_POST) {
            $data = I('post.');
            $data['uid'] = $this->user_id ;
            if(empty($data['user']) || empty($data['sex']) || empty($data['birth']) || empty($data['mobile']) || empty($data['idcard']) ){
                $this->error("请完善信息！");
            }
            $rs = M("user_info")->add($data);
            $resarr = session("refer_url");
            $resarr['info_id'] = $rs;
            $source = I('source','');
            if ($source == 'drug') {
                header('Location:' . U('Mobile/Cart/cart2', $resarr));
                exit;
            }elseif ($source == 'order_preview') {
                header('Location:' . U('Mobile/Cart/order_preview', $resarr));
                exit;
            }
            $this->success("添加成功！", U('Mobile/User/info_list'));
            exit();
        }
        $this->assign('s',$_GET['s']);
        return $this->fetch();
        
    }
    
    /*
     * 用药人编辑
     */
    public function edit_info()
    {
       $s =  $data = I('post.');
        $data['uid'] = $this->user_id ;
        $id = I("id");
        if(empty($id)){
            $this->error("未指定用药人",U('info_list'));
        }
        $address = M('user_info')->where(array('id' => $id))->find();
        if (IS_POST) {
            M("user_info")->where('id',$id)->save($data);
            if (I('source','') == 'drug') {
                header('Location:' . U('Mobile/Cart/cart2', array('address_id' => $data['address_id'],'is_drug'=>1,'info_id'=>$id, 's' => $data['s'])));
                exit;
            }elseif (I('source','') == 'order_preview') {
                header('Location:' . U('Mobile/Cart/order_preview', array('address_id' => $id,'s'=>$s)));
                exit;
            }
            else
                $this->success($data['msg'], U('/Mobile/User/info_list'));
            exit();
        }
        //获取省份
        $this->assign('s', $s);
        $this->assign("address",$address);
        return $this->fetch();
    }
    
    
    /*
     * 设置默认收货地址
     */
    public function set_info()
    {
        $id = I('get.id/d');
        M('user_address')->where(array('user_id' => $this->user_id))->save(array('is_default' => 0));
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->save(array('is_default' => 1));
        if (!$row)
            $this->error('操作失败');
        $this->success("操作成功");
    }
    
    /*
     * 地址用药人
     */
    public function del_info()
    {
        $id = I('get.id/d'); 
        $address = M('user_info')->where("id", $id)->find();
        $row = M('user_info')->where(array('uid' => $this->user_id, 'id' => $id))->delete();
        if (!$row)
            $this->error('操作失败', U('User/info_list',array('address_id' => $_GET['address_id'],'source'=>I('source'),'info_id'=>$_GET['info_id'], 's' => $_GET['s'])));
        else
            $this->success("操作成功", U('User/info_list',array('address_id' => $_GET['address_id'],'source'=>I('source'), 's' => $_GET['s'])));
    }
    
    /*
     * 评论晒单
     */
    public function comment()
    {
        $user_id = $this->user_id;
        $status = I('get.status');
        $logic = new UsersLogic();
        $result = $logic->get_comment($user_id, $status); //获取评论列表
        $this->assign('comment_list', $result['result']);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_comment_list');
            exit;
        }
        return $this->fetch();
    }
    
    /*
     *添加评论
     */
    public function add_comment()
    {
        if (IS_POST) {
            // 晒图片
            $files = request()->file('comment_img_file');
            $save_url = 'public/upload/comment/' . date('Y-m-d', time());
            foreach ($files as $file) {
                // 移动到框架应用根目录/public/uploads/ 目录下
                $info = $file->rule('uniqid')->validate(['size' => 1024 * 1024 * 3, 'ext' => 'jpg,png,gif,jpeg'])->move($save_url);
                if ($info) {
                    // 成功上传后 获取上传信息
                    // 输出 jpg
                    $comment_img[] = '/' . $save_url . '/' . $info->getFilename();
                } else {
                    // 上传失败获取错误信息
                    $this->error($file->getError());
                }
            }
            if (!empty($comment_img)) {
                $add['img'] = serialize($comment_img);
            }
            
            $user_info = session('user');
            $logic = new UsersLogic();
            $add['goods_id'] = I('goods_id/d');
            $add['email'] = $user_info['email'];
            $hide_username = I('hide_username');
            if (empty($hide_username)) {
                $add['username'] = $user_info['nickname'];
            }
            $add['order_id'] = I('order_id/d');
            $add['service_rank'] = I('service_rank');
            $add['deliver_rank'] = I('deliver_rank');
            $add['goods_rank'] = I('goods_rank');
            //$add['content'] = htmlspecialchars(I('post.content'));
            $add['content'] = I('content');
            $add['add_time'] = time();
            $add['ip_address'] = getIP();
            $add['user_id'] = $this->user_id;
            
            //添加评论
            $row = $logic->add_comment($add);
            if ($row[status] == 1) {
                $this->success('评论成功', U('/Mobile/Goods/goodsInfo', array('id' => $add['goods_id'])));
                exit();
            } else {
                $this->error($row[msg]);
            }
        }
        $rec_id = I('rec_id/d', 0);
        $order_goods = M('order_goods')->where("rec_id", $rec_id)->find();
        $this->assign('order_goods', $order_goods);
        return $this->fetch();
    }
    
    
    /**
     * @time 2016/8/5
     * @author dyr
     * 订单评价列表
     */
    public function comment_list()
    {
        $order_id = I('get.order_id/d');
        $store_id = I('get.store_id/d');
        $goods_id = I('get.goods_id/d');
        $part_finish = I('get.part_finish/d', 0);
        if (empty($order_id) || empty($store_id)) {
            $this->error("参数错误");
        } else {
            //查找店铺信息
            $store_where['store_id'] = $store_id;
            $store_info = M('store')->field('store_id,store_name,store_phone,store_address,store_logo')->where($store_where)->find();
            if (empty($store_info)) {
                $this->error("该商家不存在");
            }

            //查找订单是否已经被用户评价
            $order_comment_where['order_id'] = $order_id;
            $order_comment_where['deleted'] = 0;
            $order_info = M('order')->field('order_id,order_sn,is_comment,add_time')->where($order_comment_where)->find();
            //查找订单下的所有未评价的商品
            $order_goods_logic = new OrderGoodsLogic();
            $no_comment_goods = $order_goods_logic->get_no_comment_goods($order_id, $goods_id);
            $this->assign('store_info', $store_info);
            $this->assign('order_info', $order_info);
            $this->assign('no_comment_goods', $no_comment_goods);
            $this->assign('part_finish', $part_finish);
            return $this->fetch();
        }
    }
    
    /**
     * @time 2016/8/5
     * @author dyr
     *  添加评论
     */
    public function conmment_add()
    {

        $anonymous = I('post.anonymous');
        $store_score['describe_score'] = I('post.store_packge_hidden');
        $store_score['seller_score'] = I('post.store_speed_hidden');
        $store_score['logistics_score'] = I('post.store_sever_hidden');
        $order_id = $store_score['order_id'] = $store_score_where['order_id'] = I('post.order_id/d');
        $goods_id = I('post.goods_id/d');
        $content = I('post.content');
        $spec_key_name = I('post.spec_key_name');
        $rank = I('post.rank');
        $tag = I('tag/a');
        $store_score['user_id'] = $store_score_where['user_id'] = $this->user_id;
        $store_score_where['deleted'] = 0;
        $store_id = M('order')->where(array('order_id' => $store_score_where['order_id']))->getField('store_id');
        $store_score['store_id'] = $store_id;
        //已评价商品退出评价
       $is_comment =  M('order_goods')->where(array('order_id' => $store_score['order_id'], 'goods_id' => $goods_id))->find();
        if(empty($is_comment)){
            $this->error("您评价的商品或订单不存在！");
        }
       if($is_comment['is_comment'] == 1){
           $this->error("您已评价过此商品！");
       }
    //处理商品评价
        if ($_FILES[comment_img_file][tmp_name][0]) {
            $comment_img_file = request()->file('comment_img_file');
            if (!empty($comment_img_file)) {
                $comment_img = $this->up_img('comment', 'comment_img_file');
                if (!$comment_img) {
                    $this->error('上传图片过大！');
                }
                $comment['img'] = serialize($comment_img); // 上传的图片文件
            }
        }

        //处理订单评价
        if (!empty($store_score['describe_score']) && !empty($store_score['seller_score']) && !empty($store_score['logistics_score'])) {
            $order_comment = M('order_comment')->where($store_score_where)->find();
            if ($order_comment) {
                M('order_comment')->where($store_score_where)->save($store_score);
                M('order')->where(array('order_id' => $order_id))->save(array('is_comment' => 1));
            } else {
                M('order_comment')->add($store_score);//订单打分
                M('order')->where(array('order_id' => $order_id))->save(array('is_comment' => 1));
            }
            //订单打分后更新店铺评分
            $store_logic = new StoreLogic();
            $store_logic->updateStoreScore($store_id);
        }

        $comment['goods_id'] = $goods_id;
        $comment['order_id'] = $order_id;
        $comment['store_id'] = $store_id;
        $comment['user_id'] = $this->user_id;
        $comment['content'] = $content;
        $comment['ip_address'] = getIP();
        $comment['spec_key_name'] = $spec_key_name;
        $comment['goods_rank'] = $rank;
//        $comment['img'] = (empty($value['commment_img'][0])) ? '' : serialize($value['commment_img']);
        $comment['impression'] = (empty($tag[0])) ? '' : implode(',', $tag);
        $comment['is_anonymous'] = empty($anonymous) ? 1 : 0;
        $comment['add_time'] = time();
        M('comment')->add($comment);//想评论表插入数据
        M('order_goods')->where(array('order_id' => $store_score['order_id'], 'goods_id' => $goods_id))->save(array('is_comment' => 1));
        M('goods')->where(array('goods_id' => $goods_id))->setInc('comment_count', 1);
        M('order')->where('order_id',$order_id)->save(['order_status' => 4]);
        unset($comment);

        $this->success("评论成功", U('User/comment'));
    }
    
    /*
     * 个人信息
     */
    public function userinfo()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        if (IS_POST) {
            I('post.nickname') ? $post['nickname'] = I('post.nickname') : false; //昵称
            I('post.qq') ? $post['qq'] = I('post.qq') : false;  //QQ号码
            I('post.head_pic') ? $post['head_pic'] = I('post.head_pic') : false; //头像地址
            I('post.sex') ? $post['sex'] = I('post.sex') : $post['sex'] = 0;  // 性别
            I('post.birthday') ? $post['birthday'] = strtotime(I('post.birthday')) : false;  // 生日
            I('post.province') ? $post['province'] = I('post.province') : false;  //省份
            I('post.city') ? $post['city'] = I('post.city') : false;  // 城市
            I('post.district') ? $post['district'] = I('post.district') : false;  //地区
            I('post.email') ? $post['email'] = I('post.email') : false; //邮箱
            I('post.mobile') ? $post['mobile'] = I('post.mobile') : false; //手机
            $email = I('post.email');
            $mobile = I('post.mobile');
            $code = I('post.mobile_code', '');
            $scene = I('post.scene', 6);
            
            if (!empty($email)) {
                $c = M('users')->where(['email' => input('post.email'), 'user_id' => ['<>', $this->user_id]])->count();
                $c && $this->error("邮箱已被使用");
            }
            if (!empty($mobile)) {
                $c = M('users')->where(['mobile' => input('post.mobile'), 'user_id' => ['<>', $this->user_id]])->count();
                $c && $this->error("手机已被使用");
                if (!$code)
                    $this->error('请输入验证码');
                $check_code = $userLogic->check_validate_code($code, $mobile, 'phone', $this->session_id, $scene);
                if ($check_code['status'] != 1)
                    $this->error($check_code['msg']);
            }
            
            if (!$userLogic->update_info($this->user_id, $post))
                $this->error("保存失败");
            $this->success("操作成功",U("Mobile/user/index"),'',1);
            exit;
        }
        //  获取省份
        $province = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        //  获取订单城市
        $city = M('region')->where(array('parent_id' => $user_info['province'], 'level' => 2))->select();
        //  获取订单地区
        $area = M('region')->where(array('parent_id' => $user_info['city'], 'level' => 3))->select();
        $this->assign('province', $province);
        $this->assign('city', $city);
        $this->assign('area', $area);
        $this->assign('user', $user_info);
        $this->assign('sex', C('SEX'));
        
        $action = I('get.action');
        if ($action != '') {
            return $this->fetch("$action");
        }
        return $this->fetch();
    }
    
    /*
     * 邮箱验证
     */
    public function email_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['email_validated'] == 0)
            $step = 2;
        //原邮箱验证是否通过
        if ($user_info['email_validated'] == 1 && session('email_step1') == 1)
            $step = 2;
        if ($user_info['email_validated'] == 1 && session('email_step1') != 1)
            $step = 1;
        if (IS_POST) {
            $email = I('post.email');
            $code = I('post.code');
            $info = session('email_code');
            if (!$info)
                $this->error('非法操作');
            if ($info['email'] == $email || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('email_code', null);
                    session('email_step1', null);
                    if (!$userLogic->update_email_mobile($email, $this->user_id))
                        $this->error('邮箱已存在');
                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('email_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/email_validate', array('step' => 2)));
                }
                exit;
            }
            $this->error('验证码邮箱不匹配');
        }
        $this->assign('step', $step);
        return $this->fetch();
    }
    
    /*
    * 手机验证
    */
    public function mobile_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['mobile_validated'] == 0)
            $step = 2;
        //原手机验证是否通过
        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') == 1)
            $step = 2;
        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') != 1)
            $step = 1;
        if (IS_POST) {
            $mobile = I('post.mobile');
            $code = I('post.code');
            $info = session('mobile_code');
            if (!$info)
                $this->error('非法操作');
            if ($info['email'] == $mobile || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('mobile_code', null);
                    session('mobile_step1', null);
                    if (!$userLogic->update_email_mobile($mobile, $this->user_id, 2))
                        $this->error('手机已存在');
                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('mobile_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/mobile_validate', array('step' => 2)));
                }
                exit;
            }
            $this->error('验证码手机不匹配');
        }
        $this->assign('step', $step);
        return $this->fetch();
    }
    
    /**
     *  我的收藏
     * @author lxl
     * @time17-3-28
     */
    public function collect_list()
    {
//        $userLogic = new UsersLogic();
//        $data = $userLogic->get_goods_collect($this->user_id);
//        $this->assign('page', $data['show']);// 赋值分页输出
//        $this->assign('goods_list', $data['result']);
//        if ($_GET['is_ajax']) {
//            return $this->fetch('ajax_collect_list');
//            exit;
//        }
//        return $this->fetch();
        
        $type = I('get.collect_type/d', '');
        if ($type == '') {
            //商品收藏
            $userLogic = new UsersLogic();
            $data = $userLogic->get_goods_collect($this->user_id);
            $this->assign('page', $data['show']);// 赋值分页输出
        } else {
            //店铺收藏
            $sc_id = I('get.sc_id/d');
            $storeLogic = new StoreLogic();
            $data = $storeLogic->getCollectStore($this->user_id, $sc_id);
        }
        $this->assign('lists', $data['result']);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_collect_list');
            exit;
        }
        return $this->fetch();
    }
    
    /*
     *取消收藏
     */
    public function cancel_collect()
    {
        $collect_id = I('collect_id/d');
        $user_id = $this->user_id;
        if (M('goods_collect')->where(["collect_id" => $collect_id, "user_id" => $user_id])->delete()) {
            $this->success("取消收藏成功", U('User/collect_list'));
        } else {
            $this->error("取消收藏失败", U('User/collect_list'));
        }
    }
    
    /**
     *  删除一个收藏店铺
     * @author lxl
     * @time17-3-28
     */
    public function del_store_collect()
    {
        $id = I('get.log_id/d');
        if (!$id)
            $this->error("缺少ID参数");
        $store_id = M('store_collect')->where(array('log_id' => $id, 'user_id' => $this->user_id))->getField('store_id');
        $row = M('store_collect')->where(array('log_id' => $id, 'user_id' => $this->user_id))->delete();
        M('store')->where(array('store_id' => $store_id))->setDec('store_collect');
        if ($row) {
            $this->success("取消收藏成功", U('User/collect_list'));
        } else {
            $this->error("取消收藏失败", U('User/collect_list'));
        }
    }
    
    public function message_list()
    {
        C('TOKEN_ON', true);
        if (IS_POST) {
            $this->verifyHandle('message');
            
            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $user = session('user');
            $data['user_name'] = $user['nickname'];
            $data['msg_time'] = time();
            if (M('feedback')->add($data)) {
                $this->success("留言成功", U('User/message_list'));
                exit;
            } else {
                $this->error('留言失败', U('User/message_list'));
                exit;
            }
        }
        $msg_type = array(0 => '留言', 1 => '投诉', 2 => '询问', 3 => '售后', 4 => '求购');
        $count = M('feedback')->where("user_id=" . $this->user_id)->count();
        $Page = new Page($count, 100);
        $Page->rollPage = 2;
        $message = M('feedback')->where("user_id=" . $this->user_id)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $showpage = $Page->show();
        header("Content-type:text/html;charset=utf-8");
        $this->assign('page', $showpage);
        $this->assign('message', $message);
        $this->assign('msg_type', $msg_type);
        return $this->fetch();
    }
    
    public function points()
    {
        $type = I('type', 'all');
        $this->assign('type', $type);
        if ($type == 'recharge') {
            $count = M('recharge')->where("user_id=" . $this->user_id)->count();
            $Page = new Page($count, 16);
            $account_log = M('recharge')->where("user_id=" . $this->user_id)->order('order_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else if ($type == 'points') {
            $count = M('account_log')->where("user_id=" . $this->user_id . " and pay_points!=0 ")->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where("user_id=" . $this->user_id . " and pay_points!=0 ")->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else {
            $count = M('account_log')->where("user_id=" . $this->user_id)->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where("user_id=" . $this->user_id)->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        }
        $showpage = $Page->show();
        $this->assign('account_log', $account_log);
        $this->assign('page', $showpage);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_points');
            exit;
        }
        return $this->fetch();
    }
    
    /*
     * 密码修改
     */
    public function password()
    {
        //检查是否第三方登录用户
        $logic = new UsersLogic();
        $data = $logic->get_info($this->user_id);
        $user = $data['result'];
        if ($user['mobile'] == '' && $user['email'] == '')
            $this->error('请先到电脑端绑定手机', U('/Mobile/User/index'));
        if (IS_POST) {
            $userLogic = new UsersLogic();
            $data = $userLogic->password($this->user_id, I('post.new_password'), I('post.confirm_password')); // 获取用户信息
            if ($data['status'] == -1)
                $this->error($data['msg']);
            $this->success($data['msg']);
            exit;
        }
        return $this->fetch();
    }
    
    function forget_pwd()
    {
        if ($this->user_id > 0) {
            header("Location: " . U('User/Index'));
        }
        return $this->fetch();
    }
    
    function find_pwd()
    {
        if ($this->user_id > 0) {
            header("Location: " . U('User/Index'));
        }
        $username = I('username');
        if (IS_POST) {
            if (!empty($username)) {
                $field = 'mobile';
                if (check_email($username)) {
                    $field = 'email';
                }
                $user = M('users')->where("email='$username' or mobile='$username'")->find();
                if (!$user) {
                    $this->error("用户名不存在，请检查", U('User/forget_pwd'));
                }
            }
        }
        $this->assign('user', $user);
        return $this->fetch();
    }
    
    
    public function set_pwd()
    {
        if ($this->user_id > 0) {
            header("Location: " . U('User/Index'));
        }
        // $check = session('validate_code');
        // if (empty($check)) {
        //     header("Location:" . U('User/forget_pwd'));
        // } elseif ($check['is_check'] == 0) {
        //     $this->error('验证码还未验证通过', U('User/forget_pwd'));
        // }
        if (IS_POST) {
            $password = I('post.password');
            $password2 = I('post.password2');
            if ($password2 != $password) {
                $this->error('两次密码不一致', U('User/forget_pwd'));
            }
            $user = M('users')->where("mobile = '{$check['sender']}' or email = '{$check['sender']}'")->find();
            if ($user) {
                M('users')->where("user_id=" . $user['user_id'])->save(array('password' => encryptUserPasswd($password)));
                session('validate_code', null);
                $this->success('新密码已设置行牢记新密码', U('User/index'));
                exit;
            } else {
                $this->error('操作失败，请稍后再试', U('User/forget_pwd'));
            }
        }
        $is_set = I('is_set', 0);
        $this->assign('is_set', $is_set);
        return $this->fetch();
    }
    
    /**
     * 验证码验证
     * $id 验证码标示
     */
    private function verifyHandle($id)
    {
        $verify = new Verify();
        if (!$verify->check(I('post.verify_code'), $id ? $id : 'user_login')) {
            $this->error("验证码错误");
        }
    }
    
    /**验证码接口*/
    public function check_captcha()
    {
        $verify = new Verify();
        // if (!$verify->check(I('post.verify_code'), I('post.id'))) {
            // $res = array('status' => -1, 'msg' => '图像验证码错误');
            // ajaxReturn($res);
        // }
        $res = array('status' => 1, 'msg' => '验证成功！');
        ajaxReturn($res);
        
    }
    
    /**
     * 验证码获取
     */
    public function verify()
    {
        //验证码类型
        $type = I('get.type') ? I('get.type') : 'user_login';
        $config = array(
            'fontSize' => 40,
            'length' => 4,
            'useCurve' => true,
            'useNoise' => false,
        );
        $Verify = new Verify($config);
        $Verify->entry($type);
        exit();
    }
    
    /**
     * 账户管理
     */
    public function accountManage()
    {
        return $this->fetch();
    }
    
    public function order_confirm()
    {
        $id = I('get.id/d', 0);
        $data = confirm_order($id, $this->user_id);
        if ($data['status'] != 1) {
            $this->error($data['msg'], U('Mobile/User/order_list'));
        } else {
            $this->success($data['msg'], U('Mobile/User/order_detail', ['id' => $id]));
        }
    }
    
    /**
     * 申请退货
     */
    public function return_goods()
    {
        $order_id = I('order_id/d', 0);
        $order_sn = I('order_sn', 0);
        $goods_id = I('goods_id/d', 0);
        $spec_key = I('spec_key');
        
        $order = M('order')->where(["order_id" => $order_id, "user_id" => $this->user_id])->find();
        if (!$order) {
            $this->error('非法操作');
            exit;
        }
        $goods = M('goods')->where("goods_id = $goods_id")->find();
        $store = M('store')->where(array('store_id' => $goods['store_id']))->find();
        $return_goods = M('return_goods')->where("order_id = $order_id and goods_id = $goods_id and spec_key = '$spec_key'")->find();
        if (!empty($return_goods)) {
            $this->success('已经提交过退货申请!', U('Mobile/User/return_goods_info', array('id' => $return_goods['id'])));
            exit;
        }
        if (IS_POST) {
            $files = $this->request->file("return_imgs");
            $validate = ['size' => 1024 * 1024 * 3, 'ext' => 'jpg,png,gif,jpeg'];
            $dir = 'public/upload/return_goods/';
            if (!($_exists = file_exists($dir))) {
                $isMk = mkdir($dir);
            }
            $parentDir = date('Ymd');
            foreach ($files as $key => $file) {
                $info = $file->rule($parentDir)->validate($validate)->move($dir, true);
                if ($info) {
                    $filename = $info->getFilename();
                    $new_name = '/' . $dir . $parentDir . '/' . $filename;
                    $return_imgs[] = $new_name;
                } else {
                    $this->error($info->getError());//上传错误提示错误信息
                }
            }
            if (!empty($return_imgs)) {
                $data['imgs'] = implode(',', $return_imgs);// 上传的图片文件
            }
            $data['order_id'] = $order_id;
            $data['order_sn'] = $order_sn;
            $data['goods_id'] = $goods_id;
            $data['addtime'] = time();
            $data['user_id'] = $this->user_id;
            $data['type'] = I('type'); // 服务类型  退货 或者 换货
            $data['reason'] = I('reason'); // 问题描述     
            $data['spec_key'] = I('spec_key'); // 商品规格
            $data['store_id'] = $store['store_id'];
            $data['refunf'] = $order['order_amount'];
            M('return_goods')->add($data);
            $this->success('申请成功,客服第一时间会帮你处理', U('Mobile/User/order_list'));
            exit;
        }
        
        $province_name = M('region')->where(array('id' => $store['province_id']))->getField('name');
        $city_name = M('region')->where(array('id' => $store['city_id']))->getField('name');
        $district_name = M('region')->where(array('id' => $store['district']))->getField('name');
        $store_region = $province_name . ',' . $city_name . ',' . $district_name . ',';
        $this->assign('goods', $goods);
        $this->assign('order_id', $order_id);
        $this->assign('order_sn', $order_sn);
        $this->assign('goods_id', $goods_id);
        $this->assign('store_region', $store_region);
        $this->assign('store', $store);
        return $this->fetch();
    }
    
    /**
     * 退换货列表
     */
    public function return_goods_list()
    {
        $count = M('return_goods')->where("user_id = {$this->user_id}")->count();
        $page = new Page($count, 4);
        $list = M('return_goods')->where("user_id = {$this->user_id}")->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');
        if (!empty($goods_id_arr))
            $goodsList = M('goods')->where("goods_id in (" . implode(',', $goods_id_arr) . ")")->getField('goods_id,goods_name');
        $this->assign('goodsList', $goodsList);
        $this->assign('list', $list);
        $this->assign('page', $page->show());// 赋值分页输出                    	    	
        if ($_GET['is_ajax']) {
            return $this->fetch('return_ajax_goods_list');
            exit;
        }
        $this->assign('state', C('REFUND_STATUS'));
        return $this->fetch();
    }
    
    /**
     *  退货详情
     */
    public function return_goods_info()
    {
        $id = I('id/d', 0);
        $return_goods = M('return_goods')->where("id = $id")->find();
        if ($return_goods['imgs'])
            $return_goods['imgs'] = explode(',', $return_goods['imgs']);
        $goods = M('goods')->where("goods_id = {$return_goods['goods_id']} ")->find();
        $this->assign('goods', $goods);
        $this->assign('return_goods', $return_goods);
        return $this->fetch();
    }
    
    public function recharge()
    {
        $order_id = I('order_id/d');
        $paymentList = M('Plugin')->where("`type`='payment' and code!='cod' and status = 1 and  scene in(0,1)")->select();
        //微信浏览器
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and code='weixin'")->select();
        }
        $paymentList = convert_arr_key($paymentList, 'code');
        
        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if ($val['config_value']['is_bank'] == 2) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
        }
        $bank_img = include APP_PATH . 'home/bank.php'; // 银行对应图片
        $payment = M('Plugin')->where("`type`='payment' and status = 1")->select();
        $this->assign('paymentList', $paymentList);
        $this->assign('bank_img', $bank_img);
        $this->assign('bankCodeList', $bankCodeList);
        
        if ($order_id > 0) {
            $order = M('recharge')->where("order_id = $order_id")->find();
            $this->assign('order', $order);
        }
        return $this->fetch();
    }
    
    /**
     * 申请提现记录
     */
    public function withdrawals()
    {
        
        C('TOKEN_ON', true);
        if (IS_POST) {
            $this->verifyHandle('withdrawals');
            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $data['create_time'] = time();
            $distribut_min = tpCache('distribut.min'); // 最少提现额度
            $distribut_need = tpCache('distribut.need'); //满多少才能提
            if ($data['money'] < $distribut_min) {
                $this->error('每次最少提现额度' . $distribut_min);
            }
            if ($data['money'] > $this->user['user_money']) {
                $this->error("你最多可提现{$this->user['user_money']}账户余额.");
            }
            if ($this->user['user_money'] < $distribut_need) {
                $this->error('账户余额最少达到' . $distribut_need . '才能提现');
            }
            
            $withdrawal = M('withdrawals')->where(array('user_id' => $this->user_id, 'status' => 0))->sum('money');
            if ($this->user['user_money'] < ($withdrawal + $data['money'])) {
                $this->error('您有提现申请待处理，本次提现余额不足');
            }
            if (M('withdrawals')->add($data)) {
                $bank['bank_name'] = $data['bank_name'];
                $bank['bank_card'] = $data['account_bank'];
                $bank['realname'] = $data['account_name'];
                M('users')->where(array('user_id' => $this->user_id))->save($bank);
                $this->success("已提交申请");
                exit;
            } else {
                $this->error('提交失败,联系客服!');
            }
        }
        
        $where = " user_id = {$this->user_id}";
        $count = M('withdrawals')->where($where)->count();
        $page = new Page($count, 16);
        $list = M('withdrawals')->where($where)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();
        
        $this->assign('page', $page->show());// 赋值分页输出
        $this->assign('list', $list); // 下线
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_withdrawals_list');
            
        }
        $order_count = M('order')->where("user_id = {$this->user_id}")->count(); // 我的订单数
        $goods_collect_count = M('goods_collect')->where("user_id = {$this->user_id}")->count(); // 我的商品收藏
        $comment_count = M('comment')->where("user_id = {$this->user_id}")->count();//  我的评论数
        $coupon_count = M('coupon_list')->where("uid = {$this->user_id}")->count(); // 我的优惠券数量
        $level_name = M('user_level')->where("level_id = '{$this->user['level']}'")->getField('level_name'); // 等级名称
        $this->assign('level_name', $level_name);
        $this->assign('order_count', $order_count);
        $this->assign('goods_collect_count', $goods_collect_count);
        $this->assign('comment_count', $comment_count);
        $this->assign('coupon_count', $coupon_count);
        return $this->fetch();
    }
    
    /**
     * 申请提现记录列表
     */
    public function withdrawals_list()
    {
        $withdrawals_where['user_id'] = $this->user_id;
        $count = M('withdrawals')->where($withdrawals_where)->count();
        $pagesize = C('PAGESIZE') == 0 ? 10 : C('PAGESIZE');
        $page = new Page($count, $pagesize);
        $list = M('withdrawals')->where($withdrawals_where)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();
        
        $this->assign('page', $page->show());// 赋值分页输出
        $this->assign('list', $list); // 下线
        if (I('is_ajax')) {
            return $this->fetch('ajax_withdrawals_list');
        }
        return $this->fetch();
    }
    
    /**
     * 我的关注
     * @author lhb
     * @time   2017/4
     */
    public function myfocus()
    {
        /* 获取收藏的商家数量 */
        $sc_id = I('get.sc_id/d');
        $storeLogic = new StoreLogic();
        $storeNum = $storeLogic->getCollectNum($this->user_id, $sc_id);
        /* 获取收藏的商品数量 */
        $goodsNum = M('goods_collect')->where(array('user_id' => $this->user_id))->count();
        $this->assign('storeNum', $storeNum);
        $this->assign('goodsNum', $goodsNum);
        
        $type = I('get.focus_type/d', 0);
        if ($type == 0) {
            //商品收藏
            $userLogic = new UsersLogic();
            $data = $userLogic->get_goods_collect($this->user_id);
            $this->assign('goodsList', $data['result']);
        } else {
            //店铺收藏
            $data = $storeLogic->getCollectStore($this->user_id, $sc_id);
            $this->assign('storeList', $data['result']);
        }
        
        if (I('get.is_ajax')) {
            return $this->fetch('ajax_myfocus');
        }
        return $this->fetch();
    }
    
    /*
     * 待收货
     */
    public function wait_receive()
    {
        $where = ' user_id=' . $this->user_id;
        //条件搜索 
        if (in_array(strtoupper(I('type')), array('WAITCCOMMENT', 'COMMENTED'))) {
            $where .= " AND order_status in(1,4) "; //代评价 和 已评价
        } elseif (I('type')) {
            $where .= C(strtoupper(I('type')));
        }
        $count = M('order')->where($where)->count();
        $Page = new Page($count, 10);
        
        $order_str = "order_id DESC";
        $order_list = M('order')->order($order_str)->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        
        //获取订单商品
        $orderLogic = new OrderLogic();
        foreach ($order_list as $k => $v) {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            
            $invoice_no = M('DeliveryDoc')->where("order_id", $v['order_id'])->getField('invoice_no', true);
            $order_list[$k]['invoice_no'] = implode(' , ', $invoice_no);
            
            $data = $orderLogic->get_order_goods($v['order_id']);
            $order_list[$k]['goods_list'] = $data['result'];
            
            $count_goods_num = 0;
            foreach ($order_list[$k]['goods_list'] as $kk => $vv) {
                $count_goods_num += $vv['goods_num'];
            }
            $order_list[$k]['count_goods_num'] = $count_goods_num;
        }
        $this->assign('order_list', $order_list);
        
        $storeList = M('store')->getField('store_id,store_name,store_qq'); // 找出所有商品对应的店铺id
        $this->assign('storeList', $storeList); // 店铺列表
        
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_wait_receive');
        }
        return $this->fetch();
    }
    
    /**
     *  用户消息通知
     * @author dyr
     * @time 2016/09/01
     */
    public function message_notice()
    {
        return $this->fetch('user/message_notice');
    }
    
    /**
     * ajax用户消息通知请求
     * @author dyr
     * @time 2016/09/01
     */
    public function ajax_message_notice()
    {
        $type = I('type', 0);
        $user_logic = new UsersLogic();
        $message_model = new Message();
        if ($type == 1) {
            //系统消息
            $user_sys_message = $message_model->getUserMessageNotice();
            $user_logic->setSysMessageForRead();
        } else if ($type == 2) {
            //活动消息：后续开发
            $user_sys_message = array();
        } else {
            //全部消息：后续完善
            $user_sys_message = $message_model->getUserMessageNotice();
        }
        $this->assign('messages', $user_sys_message);
        return $this->fetch('user/ajax_message_notice');
        
    }
    
    /**
     * 设置消息通知
     */
    public function set_notice()
    {
        //暂无数据
        return $this->fetch();
    }
	
	/***/
	public function get_order_status(){
		$order=I("order_id");
		$status=M('order')->where('order',$order_id)->getField('pay_status');
		echo json_encode(array('status'=>$status));
	}
	
	 /**关系绑定用户注册*/
    public function bind_reger(){
        // if ($this->user_id > 0) {
        //     $this->redirect(U('Mobile/User/index'));
        // }
        $reg_sms_enable = tpCache('sms.regis_sms_enable');
        $reg_smtp_enable = tpCache('sms.regis_smtp_enable');
        if (IS_POST) {
            $logic = new UsersLogic();
            //验证码检验
            // $this->verifyHandle('reg');
            $username = I('post.username', '');
            $password = I('post.password', '');
            $password2 = I('post.password2', '');
            //是否开启注册验证码机制
            $code = I('post.mobile_code', '');
            $scene = I('post.scene', 1);

            $session_id = session_id();

            // if (check_mobile($username)) {
            //     $check_code = $logic->check_validate_code($code, $username, 'phone', $session_id, $scene);
            //     if ($check_code['status'] != 1) {
            //         $this->error($check_code['msg']);
            //     }
            // }
            // //是否开启注册邮箱验证码机制
            // if (check_email($username)) {
            //     $check_code = $logic->check_validate_code($code, $username);
            //     if ($check_code['status'] != 1) {
            //         $this->error($check_code['msg']);
            //     }
            // }

            $data = $logic->api_reg($username, $password, $password2);
            if ($data['status'] != 1)
                $this->error($data['msg']);
            session('user', $data['result']);
            setcookie('user_id', $data['result']['user_id'], null, '/');
            setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
            $cartLogic = new \app\home\logic\CartLogic();
            $cartLogic->login_cart_handle($this->session_id, $data['result']['user_id']);  //用户登录后 需要对购物车 一些操作
            $this->success($data['msg'], U('Mobile/User/index'));
            exit;
        }
        $this->assign("pid",I("pid"));
        $this->assign('regis_sms_enable', $reg_sms_enable); // 注册启用短信：
        $this->assign('regis_smtp_enable', $reg_smtp_enable); // 注册启用邮箱：
        $sms_time_out = tpCache('sms.sms_time_out') > 0 ? tpCache('sms.sms_time_out') : 120;
        $this->assign('sms_time_out', $sms_time_out); // 手机短信超时时间
        return $this->fetch();
        $this->fetch();
    }
	
	  /********************dengxing begin****************************/
    /**
     * @author dengxing
     * @return mixed
     * status 状态 1未提现 2提现中 3已提现 4全部(基于order_status 2,4)
     * type 佣金类型  1销售佣金  2推广佣金
     */
    public function commission()
    {

        $user_id = $this->user_id;
        $user_commission= M('users')->where(array('user_id' => $user_id))->getField('(sales_commission+promote_commission)');
        $this->assign('commission', $user_commission);

        $status = I('get.status', 4);
        $type = I('get.type', 1);
        $_GET['type']=$type;
        $logic = new UsersLogic();
        $result = $logic->get_commission($user_id, $status, $type); //获取评论列表
		if(!empty($result['result'])){
			foreach($result['result'] as $key => &$val){
				$val['goods_name'] = M('order_goods')->where('order_id',$val['order_id'])->getField('goods_name');
			}
			unset($val);
		}

        $this->assign('commission_list', $result['result']);
		// echo "<pre>";
		// var_dump($result);exit;
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_commission_list');
            exit;
        }
        return $this->fetch();
    }

    /**
     * @author dengxing
     * 申请提现
     */
    public function withdrawal()
    {
        $user_id = $this->user_id;

        if (request()->isPost()) {
            //验证码验证
            $this->verifyHandle('withdrawals');
            $user_commission_arr = M('users')->field('sales_commission,promote_commission')->where(array('user_id' => $user_id))->find();
            $user_commission= M('users')->where(array('user_id' => $user_id))->getField('(sales_commission+promote_commission)');

            if(!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $_POST['money']))$this->error('金额格式错误');
            if(sprintf("%.2f", $_POST['money']) < 100) $this->error('可提金额需大于100');
            if($user_commission<$_POST['money']) $this->error('可提金额不足');
            //暂扣可用金额
            $commission_sum = $user_commission_arr['sales_commission']-$_POST['money'];
            $sales_commission=$_POST['money'];//本次提现的销售佣金
            $promote_commission=0;//本次提现的推广佣金
            if($commission_sum<0){
                $promote_commission = abs($commission_sum);
                $sales_commission = $user_commission_arr['sales_commission'];
            }

            //写入记录
            $data = array(
                'user_id' => $user_id,
                'money' => sprintf("%.2f", $_POST['money']),
                'create_time' => time(),
                'bank_name' => $_POST['bank_name'],
                'bank_card' => $_POST['bank_card'],
                'realname' => $_POST['realname'],
                'remark' => $_POST['remark'],
                'sales_commission'  =>  $sales_commission,//销售佣金
                'promote_commission'    =>  $promote_commission,//推广佣金
                'status' => 0,
            );
            Db::startTrans();
            $re = M('withdrawal')->insert($data);
            if ($re == false) {
                Db::rollback();
                $this->error('请稍后再试');
                exit;
            }
            //-----销售佣金------
            if($sales_commission>0){
                $dec_result = M('users')->where(array('user_id' => $user_id))->setDec('sales_commission',$sales_commission);
                if ($dec_result == false) {
                    Db::rollback();
                    $this->error('请稍后再试');
                    exit;
                }
            }
            //---销售佣金 END----
            //-----推广佣金------
            if($promote_commission>0){
                $dec_result = M('users')->where(array('user_id' => $user_id))->setDec('promote_commission',$promote_commission);
                if ($dec_result == false) {
                    Db::rollback();
                    $this->error('请稍后再试');
                    exit;
                }
            }
            //---推广佣金 END----
            Db::commit();
            $this->success('申请成功', U('User/commission'));
            exit;
        }else{

            return $this->fetch();
        }

    }

    /**
     * @author dengxing
     * 申请提现
     */
    public function withdrawal_old()
    {
        $user_id = $this->user_id;
        if (request()->isPost()) {
            //验证码验证
            $this->verifyHandle('withdrawals');
            $order_ids = session('order_ids');
            session('order_ids', null);
            $kt_commission=session('kt_commission');
            session('kt_commission', null);
            $user_commission= M('users')->where(array('user_id' => $user_id))->getField('(sales_commission+promote_commission)');
            /*----------deng start------------*/
            if(sprintf("%.2f", $_POST['money']) < 100){
                //可提金额不足
                //$this->error('请稍后再试');
                $this->error('可提金额需大于100');
            }
            if($user_commission<$_POST['money'] || sprintf("%.2f", $_POST['money']) != sprintf("%.2f", $kt_commission)){
                //可提金额不足
                //$this->error('请稍后再试');
                $this->error('可提金额不足');
            }
            /*-----------deng end-------------*/
            //写入记录
            $data = array(
                'user_id' => $user_id,
                'money' => sprintf("%.2f", $_POST['money']),
                'create_time' => time(),
                'bank_name' => $_POST['bank_name'],
                'bank_card' => $_POST['bank_card'],
                'realname' => $_POST['realname'],
                'remark' => $_POST['remark'],
                'order_ids' => serialize($order_ids),
                'status' => 0,
            );
            Db::startTrans();
            $re = M('withdrawal')->insert($data);
            if ($re == false) {
                Db::rollback();
                $this->error('请稍后再试');
                exit;
            }
            //更新订单提现状态
            if(empty($order_ids)){
                Db::rollback();
                $this->error('请稍后再试');
                exit;
            }
            //暂扣可用金额
            $sales_commission=0;//本次提现的销售佣金
            $sales_order_id=array();
            $promote_order_id=array();
            $promote_commission=0;//本次提现的推广佣金
            /*----------deng start------------*/
            //-----销售佣金------
            if(!empty($order_ids['s'])){
                $order_ids_s = $order_ids['s'];
                $orderData = M('order')->where(array('order_status' => array('in', array(2, 4)),'order_id' =>array('in',$order_ids_s)))->select();
                foreach ($orderData as $k=>$v){
                    if($v['get_scommis_uid']==$this->user_id){
                        $sales_commission+=$v['sales_commission'];
                        $sales_order_id[]=$v['order_id'];
                    }
                }
                if(!empty($sales_order_id)){
                    $result = M('order')->where(array('order_status' => array('in', array(2, 4)), 'sales_withdrawal' => 1, 'order_id' =>array('in',$sales_order_id)))->update(array('sales_withdrawal' => 2));
                    if ($result == false) {
                        Db::rollback();
                        $this->error('请稍后再试');
                        exit;
                    }
                }
                if($sales_commission>0){
                    $dec_result = M('users')->where(array('user_id' => $user_id))->setDec('sales_commission',$sales_commission);
                    if ($dec_result == false) {
                        Db::rollback();
                        $this->error('请稍后再试');
                        exit;
                    }
                }
            }
            //---销售佣金 END----
            //-----推广佣金------
            if (!empty($order_ids['p'])){
                $order_ids_p = $order_ids['p'];
                $orderData = M('order')->where(array('order_status' => array('in', array(2, 4)),'order_id' =>array('in',$order_ids_p)))->select();
                foreach ($orderData as $k=>$v){
                    if($v['get_pcommis_uid']==$this->user_id){
                        $promote_commission+=$v['promote_commission'];
                        $promote_order_id[]=$v['order_id'];
                    }
                }
                if(!empty($promote_order_id)){
                    $result = M('order')->where(array('order_status' => array('in', array(2, 4)), 'promote_withdrawal' => 1, 'order_id' =>array('in',$promote_order_id)))->update(array('promote_withdrawal' => 2));
                    if ($result == false) {
                        Db::rollback();
                        $this->error('请稍后再试');
                        exit;
                    }
                }
                if($promote_commission>0){
                    $dec_result = M('users')->where(array('user_id' => $user_id))->setDec('promote_commission',$promote_commission);
                    if ($dec_result == false) {
                        Db::rollback();
                        $this->error('请稍后再试');
                        exit;
                    }
                }
            }
            //---推广佣金 END----
            Db::commit();
            $this->success('申请成功', U('User/commission', array('type' => 1, 'status' => 4)));
            exit;
        }else{
            //获取可提所有金额
            $commission_max=M('config')->where(array('name'=>'commission_max'))->getField('value');

            //推广佣金
            $pcommission = M('order')->field('promote_commission,order_id')->where(array('order_status' => array('in', array('2', '4')), 'promote_withdrawal' => 1, 'get_pcommis_uid' => $user_id))->select();
            $kt_commission=0;
            $kt_pcommission=array();
            $is_for=1;//是否循环下一组数据
            if(!empty($pcommission)){
                foreach ($pcommission as $k=>$v){
                    $kt_commission+=$v['promote_commission'];
                    $kt_pcommission[]=array('promote_commission'=>$v['promote_commission'],'order_id'=>$v['order_id']);
                    if($kt_commission==$commission_max){
                        $is_for=0;
                        break;
                    }
                    if($kt_commission>$commission_max){
                        $kt_commission=$kt_commission-$v['promote_commission'];
                        unset($kt_pcommission[$k]);
                        $is_for=0;
                        continue;
                    }
                }
            }
            //销售佣金
            if($is_for==1){
                $kt_scommission=array();
                $scommission = M('order')->field('sales_commission,order_id')->where(array('order_status' => array('in', array('2', '4')), 'sales_withdrawal' => 1, 'get_scommis_uid' => $user_id))->select();
                if(!empty($scommission)){
                    foreach ($scommission as $key=>$vaule){
                        $kt_commission+=$vaule['sales_commission'];
                        $kt_scommission[]=array('sales_commission'=>$vaule['sales_commission'],'order_id'=>$vaule['order_id']);
                        if($kt_commission==$commission_max){
                            break;
                        }
                        if($kt_commission>$commission_max){
                            $kt_commission=sprintf('%.2f',$kt_commission-$vaule['sales_commission']);
                            unset($kt_scommission[$key]);
                            continue;
                        }
                    }
                }
            }
            /*----------deng start------------*/
            /*$order_ids_a = array_column($kt_pcommission, 'order_id');
            $order_ids_b = array_column($kt_scommission, 'order_id');
            if (!empty($order_ids_a) && empty($order_ids_b)) {
                $order_id = $order_ids_a;
            }
            if (!empty($order_ids_b) && empty($order_ids_a)) {
                $order_id = $order_ids_b;
            }
            if (!empty($order_ids_a) && !empty($order_ids_b)) {
                $order_id = array_merge($order_ids_a, $order_ids_a);
            }*/
            if(!empty($kt_pcommission))$order_id['p'] = array_column($kt_pcommission, 'order_id');//推广佣金关联订单ID
            if(!empty($kt_scommission))$order_id['s'] = array_column($kt_scommission, 'order_id');//销售佣金关联订单ID
            /*-----------deng end-------------*/
            if (empty($order_id)) {
                $this->error('暂时没有可提佣金', U('User/index'));
                exit;
            }
            session('order_ids', $order_id);
            session('kt_commission', $kt_commission);
//        $promote_commission = array_sum(array_column($pcommission, 'promote_commission'));
//        $sales_commission = array_sum(array_column($scommission, 'sales_commission'));
            $this->assign('commission', $kt_commission);
            return $this->fetch();
        }

    }

    public function withdrawal_list()
    {
        $withdrawals_where['user_id'] = $this->user_id;
        $count = M('withdrawal')->where($withdrawals_where)->count();
        $pagesize = C('PAGESIZE') == 0 ? 10 : C('PAGESIZE');
        $page = new Page($count, $pagesize);
        $list = M('withdrawal')->where($withdrawals_where)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();

        $this->assign('page', $page->show());// 赋值分页输出
        $this->assign('lists', $list); // 下线
        if (I('is_ajax')) {
            return $this->fetch('ajax_withdrawal_list');
        }
        return $this->fetch();
    }
    /********************dengxing end****************************/
    
}
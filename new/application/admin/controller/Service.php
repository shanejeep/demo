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
 * Date: 2017-04-21
 */

namespace app\admin\controller;

use think\Page;
use think\AjaxPage;

class Service extends Base
{
    public function index()
    {
        return $this->fetch();
    }
    
    public function detail()
    {
        $id = I('get.id');
        $res = M('comment')->where(array('comment_id' => $id))->find();
        if (!$res) {
            exit($this->error('不存在该评论'));
        }
        if (IS_POST) {
            $add['parent_id'] = $id;
            $add['content'] = I('post.content');
            $add['goods_id'] = $res['goods_id'];
            $add['add_time'] = time();
            $add['username'] = '平台';
            $add['is_show'] = 1;
            //$add['seller_id'] = session('seller_id');
            $row = M('comment')->add($add);
            if ($row) {
                $this->success('添加成功');
                exit;
            } else {
                $this->error('添加失败');
            }
        }
        $reply = M('comment')->where(array('parent_id' => $id))->select(); // 评论回复列表
        $this->assign('comment', $res);
        $this->assign('reply', $reply);
        return $this->fetch();
    }
    
    
    public function del()
    {
        $id = I('get.id');
        $row = M('comment')->where(array('comment_id' => $id))->delete();
        if ($row) {
            $this->success('删除成功');
            exit;
        } else {
            $this->error('删除失败');
        }
    }
    
    public function op()
    {
        $type = I('post.type');
        $selected_id = I('post.selected');
        if (!in_array($type, array('del', 'show', 'hide')) || !$selected_id)
            $this->error('非法操作');
        $where = "comment_id IN ({$selected_id})";
        if ($type == 'del') {
            $where .= " OR parent_id IN ({$selected_id})";
            $row = M('comment')->where($where)->delete(); //删除回复
        }
        if ($type == 'show') {
            $row = M('comment')->where($where)->save(array('is_show' => 1));
        }
        if ($type == 'hide') {
            $row = M('comment')->where($where)->save(array('is_show' => 0));
        }
        if (!$row)
            $this->error('操作失败');
        $this->success('操作成功');
        
    }
    
    public function ajaxindex()
    {
        $model = M('');
        $username = I('nickname');
        $content = I('content');
        $where['c.parent_id'] = 0;
        if ($username) {
            $where['u.nickname'] = $username;
        }
        if ($content) {
            $where['c.content'] = array('like', '%' . $content . '%');
        }
        $count = $model->table(C('DB_PREFIX') . 'comment c')->join('LEFT JOIN __USERS__ u ON u.user_id = c.user_id')->where($where)->count();
        $Page = new AjaxPage($count, 16);
        $show = $Page->show();
        
        $comment_list = $model->field('c.*,u.nickname as nickname')->table(C('DB_PREFIX') . 'comment c')->join('LEFT JOIN __USERS__ u ON u.user_id = c.user_id')->where($where)->order('add_time DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        if (!empty($comment_list)) {
            $goods_id_arr = get_arr_column($comment_list, 'goods_id');
            $goods_list = M('Goods')->where("goods_id in (" . implode(',', $goods_id_arr) . ")")->getField("goods_id,goods_name");
        }
        
        $this->assign('goods_list', $goods_list);
        $this->assign('comment_list', $comment_list);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);// 赋值分页输出
        return $this->fetch();
    }
    
    public function ask_list()
    {
        $this->display();//咨询列表
    }
    
    public function ajax_ask_list()
    {
        $model = M('goods_consult');
        $username = I('username');
        $content = I('content');
        $where = '';
        if ($username) {
            $where = " AND username like'%$username%'";
        }
        if ($content) {
            $where = " AND content like '%{$content}%'";
        }
        $sql = "SELECT COUNT(1) as total_count FROM __PREFIX__goods_consult WHERE parent_id=0" . $where;
        $count = M()->query($sql);
        $Page = new AjaxPage($count[0]['total_count'], 15);
        $show = $Page->show();
        
        $sql = "SELECT * FROM __PREFIX__goods_consult WHERE parent_id=0" . $where . ' ORDER BY add_time DESC LIMIT ' . $Page->firstRow . ',' . $Page->listRows;
        $comment_list = M()->query($sql);
        if (!empty($comment_list)) {
            $goods_id_arr = get_arr_column($comment_list, 'goods_id');
            $goods_list = M('Goods')->where("goods_id in (" . implode(',', $goods_id_arr) . ")")->getField("goods_id,goods_name");
        }
        $consult_type = array(0 => '默认咨询', 1 => '商品咨询', 2 => '支付咨询', 3 => '配送', 4 => '售后');
        $this->assign('consult_type', $consult_type);
        $this->assign('goods_list', $goods_list);
        $this->assign('comment_list', $comment_list);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);// 赋值分页输出
        return $this->fetch();
    }
    
    public function return_list()
    {
        //搜索条件
        $where['type'] = array('gt', 0);
        $status = I('status');
        if ($status || $status == '0') {
            $where['status'] = $status;
        }
        $order_sn = I('order_sn');
        if ($order_sn) $where['order_sn'] = $order_sn;
        $begin = strtotime(I('add_time_begin'));
        $end = strtotime(I('add_time_end'));
        if ($begin && $end) {
            $where['addtime'] = array('between', "$begin,$end");
        }
        $count = M('return_goods')->where($where)->count();
        $Page = new AjaxPage($count, 20);
        $show = $Page->show();
        $list = M('return_goods')->where($where)->order("id desc")->limit("{$Page->firstRow},{$Page->listRows}")->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');
        if (!empty($goods_id_arr))
            $goods_list = M('goods')->where("goods_id in (" . implode(',', $goods_id_arr) . ")")->getField('goods_id,goods_name');
        $this->assign('goods_list', $goods_list);
        $store_list = M('store')->getField('store_id,store_name');
        $this->assign('store_list', $store_list);
        $state = C('REFUND_STATUS');
        $this->assign('list', $list);
        $this->assign('state', $state);
        $this->assign('page', $show);// 赋值分页输出
        return $this->fetch();
    }
    
    public function refund_list()
    {
        $where['type'] = 0;
        $status = I('status');
        if (empty($status)) {
            $where['status'] = 3;
        }
        $qtype = I('qtype');
        $qv = I('qv');
        if ($qtype == 'order_sn') $where['order_sn'] = $qv;
        $begin = strtotime(I('add_time_begin'));
        $end = strtotime(I('add_time_end'));
        if ($begin && $end) {
            $where['addtime'] = array('between', "$begin,$end");
        }
        $count = M('return_goods')->where($where)->count();
        $Page = new Page($count, 20);
        $show = $Page->show();
        $list = M('return_goods')->where($where)->order("id desc")->limit("{$Page->firstRow},{$Page->listRows}")->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');
        if (!empty($goods_id_arr)) {
            $goods_list = M('goods')->where("goods_id in (" . implode(',', $goods_id_arr) . ")")->getField('goods_id,goods_name');
        }
        $store_list = M('store')->getField('store_id,store_name');
        $this->assign('store_list', $store_list);
        $this->assign('goods_list', $goods_list);
        $this->assign('state', C('REFUND_STATUS'));
        $this->assign('list', $list);
        $this->assign('page', $show);
        $this->assign('pager', $Page);
        return $this->fetch();
    }
    
    public function refund_info()
    {
        if (IS_POST) {
            $data = I('post.');
            $data['status'] = 5;
            $data['refundtime'] = time();
            $return_goods = M('return_goods')->where(array('id' => $data['id']))->find();
            empty($return_goods) && $this->error("参数有误");
            if ($data['refund_type'] == 1) {
                $rec_goods = M('order_goods')->where(array('order_id' => $return_goods['order_id'], 'goods_id' => $return_goods['goods_id']))->find();
                $this->paymen_refund($rec_goods);
            } else {
                //处理退款
                accountLog($return_goods['user_id'], $return_goods['refund'], 0, '用户申请订单退款', 0, $return_goods['order_id'], $return_goods['order_sn']);
            }
            M('return_goods')->where(array('id' => $data['id']))->save($data);
            unset($data['id']);
            $data['type'] = 2;
            $data['log_type_id'] = $data['id'];
            $data['user_id'] = $return_goods['user_id'];
            expenseLog($data);//退款记录日志
            M('order_goods')->where(array('order_id' => $return_goods['order_id'], 'goods_id' => $return_goods['goods_id']))->save(array('is_send' => 3));
            $this->success('操作成功!', U('Service/refund_list'));
            exit;
        }
        
        $id = I('id');
        $return_goods = M('return_goods')->where(array('id' => $id))->find();
        empty($return_goods) && $this->error("参数有误");
        if ($return_goods['imgs']) $return_goods['imgs'] = explode(',', $return_goods['imgs']);
        $user = get_user_info($return_goods['user_id']);
        $order_goods = M('order_goods')->where("order_id ={$return_goods['order_id']} and goods_id = {$return_goods['goods_id']} and spec_key = '{$return_goods['spec_key']}'")->find();
        $this->assign('user', $user);
        $order = M('order')->where(array('order_id' => $return_goods['order_id']))->find();
        $this->assign('order', $order);//退货订单信息
        $this->assign('order_goods', $order_goods);//退货订单商品
        $this->assign('return_goods', $return_goods);// 退换货申请信息
        return $this->fetch();
    }
    
    /*退款金额原路退回*/
    public function paymen_refund($rec_goods)
    {
        if ($rec_goods) {
            $order = M('order')->where(array('order_id' => $rec_goods['order_id']))->find();
            if ($order['pay_code'] == 'weixin' || $order['pay_code'] == 'alipay') {
                $return_money = I('refund_money/f');
                $return_money = ($return_money > $rec_goods['goods_price']) ? $rec_goods['goods_price'] : $return_money;
                if ($order['pay_code'] == 'weixin') {
                    include_once PLUGIN_PATH . "payment/weixin/weixin.class.php";
                    $payment_obj = new \weixin();
                    $data = array('transaction_id' => $order['transaction_id'], 'total_fee' => $order['order_amount'], 'refund_fee' => $return_money);
                    $result = $payment_obj->payment_refund($data);
                    if ($result['return_code'] == 'SUCCESS') {
                        M('order_goods')->where(array('rec_id' => $rec_goods['rec_id']))->save(array('status' => 3));
                        $this->success('退款成功');
                    } else {
                        $this->error($result['return_msg']);
                    }
                } else {
                    include_once PLUGIN_PATH . "payment/alipay/alipay.class.php";
                    $payment_obj = new \alipay();
                    $detail_data = $order['transaction_id'] . '^' . $return_money . '^' . '用户申请订单退款';
                    $data = array('batch_no' => date('YmdHi') . $rec_goods['rec_id'], 'batch_num' => 1, 'detail_data' => $detail_data);
                    $payment_obj->payment_refund($data);
                }
            } else {
                $this->error('该订单支付方式不支持在线退回');
            }
        }
    }
    
    public function consult_info()
    {
        $id = I('get.id');
        $res = M('goods_consult')->where(array('id' => $id))->find();
        if (!$res) {
            exit($this->error('不存在该评论'));
        }
        if (IS_POST) {
            $add['parent_id'] = $id;
            $add['content'] = I('post.content');
            $add['goods_id'] = $res['goods_id'];
            $add['add_time'] = time();
            $add['username'] = 'admin';
            $add['is_show'] = 1;
            $row = M('comment')->add($add);
            if ($row) {
                $this->success('添加成功');
                exit;
            } else {
                $this->error('添加失败');
            }
        }
        $reply = M('goods_consult')->where(array('parent_id' => $id))->select(); // 咨询回复列表
        $this->assign('comment', $res);
        $this->assign('reply', $reply);
        return $this->fetch();
    }
    
    public function ask_handle()
    {
        $type = I('post.type');
        $selected_id = I('post.selected');
        if (!in_array($type, array('del', 'show', 'hide')) || !$selected_id)
            $this->error('非法操作');
        $where = "id IN ({$selected_id})";
        if ($type == 'del') {
            //删除咨询
            $where .= " OR parent_id IN ({$selected_id})";
            $row = M('goods_consult')->where($where)->delete();
        }
        if ($type == 'show') {
            $row = M('goods_consult')->where($where)->save(array('is_show' => 1));
        }
        if ($type == 'hide') {
            $row = M('goods_consult')->where($where)->save(array('is_show' => 0));
        }
        if (!$row)
            $this->error('操作失败');
        $this->success('操作成功');
    }
    
    public function complain_list()
    {
        $user_name = I('user_name');
        $store_name = I('user_name');
        $complain_state = I('complain_state', 1);
        $map = array();
        if ($complain_state) {
            $map['complain_state'] = $complain_state;
            $this->assign('complain_state', $complain_state);
        }
        if ($user_name) {
            $map['user_name'] = $user_name;
        }
        if ($store_name) {
            $map['store_name'] = $store_name;
        }
        $count = M('complain')->where($map)->count();
        $page = new Page($count);
        $lists = M('complain')->where($map)->order('complain_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        if ($lists) {
            foreach ($lists as $k => $v) {
                if (!empty($v['complain_pic'])) {
                    $lists[$k]['complain_pic'] = unserialize($v['complain_pic']);
                }
            }
        }
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);
        return $this->fetch();
    }
    
    public function complain_detail()
    {
        $complain_id = I('complain_id/d');
        $complain = M('complain')->where(array('complain_id' => $complain_id))->find();
        $order = M('order')->where(array('order_id' => $complain['order_id']))->find();
        $order_goods = M('order_goods')->where(array('order_id' => $complain['order_id'], 'goods_id' => $complain['order_goods_id']))->find();
        if (!empty($complain['complain_pic'])) {
            $complain['complain_pic'] = unserialize($complain['complain_pic']);
        }
        if (!empty($complain['appeal_pic'])) {
            $complain['appeal_pic'] = unserialize($complain['appeal_pic']);
        }
        $this->assign('complain', $complain);
        $this->assign('order', $order);
        $this->assign('order_goods', $order_goods);
        return $this->fetch();
    }
    
    
    public function get_complain_talk()
    {
        $complain_id = I('complain_id/d');
        $complain_info = M('complain')->where(array('complain_id' => $complain_id))->find();
        $complain_info['member_status'] = 'accused';
        $complain_talk_list = M('complain_talk')->where(array('complain_id' => $complain_id))->order('talk_id desc')->select();
        $talk_list = array();
        if (!empty($complain_talk_list)) {
            foreach ($complain_talk_list as $i => $talk) {
                $talk_list[$i]['css'] = $talk['talk_member_type'];
                $talk_list[$i]['talk'] = date("Y-m-d H:i:s", $talk['talk_time']);
                switch ($talk['talk_member_type']) {
                    case 'accuser':
                        $talk_list[$i]['talk'] .= '投诉人';
                        break;
                    case 'accused':
                        $talk_list[$i]['talk'] .= '被投诉店铺';
                        break;
                    case 'admin':
                        $talk_list[$i]['talk'] .= '管理员';
                        break;
                    default:
                        $talk_list[$i]['talk'] .= '未知';
                }
                if (intval($talk['talk_state']) === 2) {
                    $talk['talk_content'] = '<该对话被管理员屏蔽>';
                    $forbit_link = '';
                } else {
                    $forbit_link = "&nbsp;&nbsp;<a href='#' onclick=forbit_talk(" . $talk['talk_id'] . ")>屏蔽</a>";
                }
                $talk_list[$i]['talk'] .= '(' . $talk['talk_member_name'] . ')说:' . $talk['talk_content'] . $forbit_link;
            }
        }
        echo json_encode($talk_list);
    }
    
    
    public function publish_complain_talk()
    {
        $complain_id = I('complain_id/d');
        $complain_talk = trim(I('complain_talk'));
        $talk_len = strlen($complain_talk);
        if ($talk_len > 0 && $talk_len < 255) {
            $complain_info = M('complain')->where(array('complain_id' => $complain_id))->find();
            $complain_state = intval($complain_info['complain_state']);
            $param = array();
            $admin = getAdminInfo(session('admin_id'));
            $param['complain_id'] = $complain_id;
            $param['talk_member_id'] = session('admin_id');
            $param['talk_member_name'] = $admin['user_name'];
            $param['talk_member_type'] = 'admin';
            $param['talk_content'] = $complain_talk;
            $param['talk_state'] = 1;
            $param['talk_admin'] = 0;
            $param['talk_time'] = time();
            if (M('complain_talk')->add($param)) {
                echo json_encode('success');
            } else {
                echo json_encode('error2');
            }
        } else {
            echo json_encode('error1');
        }
    }
    
    public function forbit_talk()
    {
        $talk_id = I('talk_id/d');
        if (!empty($talk_id)) {
            $update_array = array();
            $update_array['talk_state'] = 2;
            $update_array['talk_admin'] = session('admin_id');
            if (M('complain_talk')->where(array('talk_id' => $talk_id))->save($update_array)) {
                echo json_encode('success');
            } else {
                echo json_encode('error2');
            }
        } else {
            echo json_encode('error1');
        }
    }
    
    public function complain_close()
    {
        $complain_id = I('complain_id/d');
        $final_handle_message = trim($_POST['final_handle_message']);
        if (strlen($final_handle_message) < 255) {
            $update_array['final_handle_msg'] = $final_handle_message;
            $update_array['final_handle_time'] = time();
            $update_array['final_handle_admin_id'] = session('admin_id');
            $update_array['complain_state'] = 4;
            if (M('complain')->where(array('complain_id' => $complain_id))->save($update_array)) {
                adminLog('关闭投诉' . $complain_id, 4);
                $this->success("操作成功");
                exit;
            } else {
                $this->error('操作失败');
            }
        } else {
            $this->error('操作失败');
        }
    }
    
    public function complain_setting()
    {
        if (IS_POST) {
            $param = I('post.');
            tpCache('complain', $param);
            $this->success("操作成功");
            exit;
        }
        $this->assign('complain_time_limit', tpCache('complain.complain_time_limit'));//当前配置项
        return $this->fetch();
    }
    
    public function complain_subject_list()
    {
        $count = M('complain_subject')->where(array('subject_state' => 1))->count();
        $page = new Page($count);
        $lists = M('complain_subject')->where(array('subject_state' => 1))->limit($page->firstRow . ',' . $page->listRows)->select();
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('list', $lists);
        return $this->fetch();
    }
    
    public function subject_del()
    {
        $subject_id = I('del_id');
        if ($subject_id > 0) {
            if (M('complain_subject')->where(array('subject_id' => $subject_id))->save(array('subject_state' => 2))) {
                respose(1);
            } else {
                respose('删除失败');
            }
        }
    }
    
    public function complain_subject_info()
    {
        if (IS_POST) {
            $data = I('post.');
            $data['subject_state'] = 1;
            if (M('complain_subject')->add($data)) {
                $this->success('添加成功', U('Service/complain_subject_list'));
                exit;
            } else {
                $this->error('添加失败,', U('Service/complain_subject_list'));
            }
        }
        return $this->fetch();
    }
    
    
    public function expose_list()
    {
        $nickname = I('expose_user_name');
        $expose_state = I('expose_state', 1);
        $map = array();
        if ($expose_state) {
            $map['expose_state'] = $expose_state;
            $this->assign('expose_state', $expose_state);
        }
        if ($nickname) {
            $map['expose_user_name'] = $nickname;
        }
        $handle_type = array(1 => '无效举报', 2 => '恶意举报', 3 => '有效举报');
        $count = M('expose')->where($map)->count();
        $page = new Page($count);
        $lists = M('expose')->where($map)->order('expose_id desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        if ($lists) {
            foreach ($lists as $k => $v) {
                if (!empty($v['expose_pic'])) {
                    $lists[$k]['expose_pic'] = unserialize($v['expose_pic']);
                }
            }
        }
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);
        $this->assign('expose_state', $expose_state);
        $this->assign('handle_type', $handle_type);
        return $this->fetch();
    }
    
    public function expose_detail()
    {
        if (IS_POST) {
            $data = I('post.');
            $data['expose_handle_time'] = time();
            $data['expose_handle_admin_id'] = session('admin_id');
            $data['expose_state'] = 2;
            $expose = M('expose')->where(array('expose_id' => $data['expose_id']))->find();
            if ($expose && M('expose')->where(array('expose_id' => $data['expose_id']))->save($data)) {
                if ($data['expose_handle_type'] == 3) {
                    M('goods')->where(array('goods_id' => $expose['expose_goods_id']))->save(array('is_on_sale' => 2, 'close_reason' => '举报违规下架'));
                    adminLog('处理举报信息,下架商品(' . $expose['expose_goods_name'] . ')', 6);
                }
                $this->success('处理成功', U('Service/expose_list'));
                exit;
            } else {
                $this->error('处理失败');
            }
        }
        $expose_id = I('expose_id/d');
        $expose = M('expose')->where(array('expose_id' => $expose_id))->find();
        if (!$expose) {
            $this->error('该举报不存在');
        }
        if (!empty($expose['expose_pic'])) {
            $expose['expose_pic'] = unserialize($expose['expose_pic']);
        }
        $this->assign('expose', $expose);
        return $this->fetch();
    }
    
    public function expose_type_list()
    {
        $count = M('expose_type')->where(array('expose_type_state' => 1))->count();
        $page = new Page($count);
        $lists = M('expose_type')->where(array('expose_type_state' => 1))->limit($page->firstRow . ',' . $page->listRows)->select();
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('list', $lists);
        return $this->fetch();
    }
    
    public function expose_type_info()
    {
        if (IS_POST) {
            $data = I('post.');
            $data['expose_type_state'] = 1;
            if (M('expose_type')->add($data)) {
                $this->success('添加成功', U('Service/expose_type_list'));
                exit;
            } else {
                $this->error('添加失败,', U('Service/expose_type_list'));
            }
        }
        return $this->fetch();
    }
    
    public function expose_type_del()
    {
        $expose_type_id = I('del_id');
        if ($expose_type_id > 0) {
            if (M('expose_type')->where(array('expose_type_id' => $expose_type_id))->save(array('expose_type_state' => 2))) {
                respose(1);
            } else {
                respose('删除失败');
            }
        }
    }
    
    public function expose_subject_list()
    {
        $count = M('expose_subject')->where(array('expose_subject_state' => 1))->count();
        $page = new Page($count);
        $lists = M('expose_subject')->where(array('expose_subject_state' => 1))->limit($page->firstRow . ',' . $page->listRows)->select();
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('list', $lists);
        return $this->fetch();
    }
    
    public function expose_subject_info()
    {
        if (IS_POST) {
            $data = I('post.');
            $data['expose_subject_state'] = 1;
            $expose_subject_type = explode(',', $data['expose_subject_type']);
            $data['expose_subject_type_id'] = $expose_subject_type[0];
            $data['expose_subject_type_name'] = $expose_subject_type[1];
            if (M('expose_subject')->add($data)) {
                $this->success('添加成功', U('Service/expose_subject_list'));
                exit;
            } else {
                $this->error('添加失败,', U('Service/expose_subject_list'));
            }
        }
        $expose_type_list = M('expose_type')->where(array('expose_type_state' => 1))->select();
        $this->assign('expose_type_list', $expose_type_list);
        return $this->fetch();
    }
    
    public function expose_subject_del()
    {
        $expose_subject_id = I('del_id');
        if ($expose_subject_id > 0) {
            if (M('expose_subject')->where(array('expose_subject_id' => $expose_subject_id))->save(array('expose_subject_state' => 2))) {
                respose(1);
            } else {
                respose('删除失败');
            }
        }
    }
}
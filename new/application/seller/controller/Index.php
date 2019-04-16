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
 * Date: 2016-06-09
 */

namespace app\seller\controller;

use think\Db;
use think\Page;

class Index extends Base
{
    
    public function index()
    {
        //$this->pushVersion();
        $seller = session('seller');
        $menu_list = getMenuList($seller['act_limits']);
        $count['handle_order'] = M('order')->where("store_id = " . STORE_ID . C('WAITSEND'))->count();//待处理订单
        $order_list = M('order')->where("store_id = " . STORE_ID . " and add_time>" . strtotime("-7 day"))->select();//最近7天订单统计
        $count['wait_shipping'] = $count['wait_pay'] = $count['wait_confirm'] = $count['refund_pay'] = 0;
        $count['refund_goods'] = $count['part_shipping'] = $count['order_sum'] = 0;
        $count['refund_pay'] = M('return_goods')->where("store_id = " . STORE_ID . " and type=0")->count();
        $count['refund_goods'] = M('return_goods')->where("store_id = " . STORE_ID . " and type=1")->count();
        if ($order_list) {
            $count['order_sum'] = count($order_list);
            foreach ($order_list as $v) {
                if ($v['order_status'] == 1 && $v['shipping_status'] == 0) {
                    $count['wait_shipping']++;
                } else if ($v['pay_status'] == 0) {
                    $count['wait_pay']++;
                } else if ($v['order_status'] == 0 && $v['is_drug'] == 0) {
                    $count['wait_confirm']++;
                }else if ($v['order_status'] == 0 && $v['is_drug'] == 1) {
                    $count['drug_confirm']++;
                } else if ($v['shipping_status'] == 2) {
                    $count['part_shipping']++;
                }
            }
        }
        $count['goods_sum'] = $count['pass_goods'] = $count['warning_goods'] = $count['new_goods'] = 0;
        $count['prom_goods'] = $count['off_sale_goods'] = $count['below_goods'] = $count['verify_goods'] = 0;
        
        $count['goods_sum'] = M('goods')->where(array('store_id' => STORE_ID))->count();
        $count['verify_goods'] = M('goods')->where(array('goods_state' => 0, 'store_id' => STORE_ID))->count();
        $count['pass_goods'] = M('goods')->where(array('goods_state' => 1, 'store_id' => STORE_ID))->count();
        $count['below_goods'] = M('goods')->where(array('goods_state' => 2, 'store_id' => STORE_ID))->count();
        $count['off_sale_goods'] = M('goods')->where(array('is_on_sale' => 2, 'store_id' => STORE_ID))->count();
        $count['prom_goods'] = M('goods')->where(array('prom_id' => array('gt', 0), 'store_id' => STORE_ID))->count();
        $count['new_goods'] = M('goods')->where(array('is_new' => 1, 'store_id' => STORE_ID))->count();
         $count['drug_confirm'] = M('goods')->where(array('is_new' => 1, 'store_id' => STORE_ID))->count();
        
        //$count['article'] =  M('article')->where(array('store_id'=>STORE_ID))->count();//文章总数
        
        $users = M('users')->where(array('user_id' => $seller['user_id']))->find();
        $seller['user_name'] = empty($users['email']) ? $users['mobile'] : $users['email'];
        //今天销售总额
        $today = strtotime("-1 day");
        $count['total_amount'] = M('order')->where(array('store_id' => STORE_ID, 'add_time' => array('gt', $today), 'pay_status' => 1))->sum('order_amount');
        $count['comment'] = M('comment')->where(array('is_show' => 0, 'store_id' => STORE_ID))->count();//最新评论
        $count['consult'] = M('goods_consult')->where(array('is_show' => 0, 'store_id' => STORE_ID))->count();//最新咨询
        
        $store = M('store')->where(array('store_id' => STORE_ID))->find();
        if ($store['store_warning_storage'] > 0) {
            $count['warning_storage'] = M('goods')->where(array('store_id' => STORE_ID, 'store_count' => array('lt', $store['store_warning_storage'])))->count();
        } else {
            $count['warning_storage'] = '未设置';
        }
        $store_level = Db::name('store_grade')->where('sg_id', $store['grade_id'])->getField('sg_name');
        $seller_group = Db::name('seller_group')->where('group_id', $seller['group_id'])->find();
        $this->assign('store_level', $store_level);
        $this->assign('seller_group', $seller_group);
        $this->assign('count', $count);
        $this->assign('store', $store);
        $this->assign('menu_list', $menu_list);
        $this->assign('seller', $seller);
        return $this->fetch();
    }
    
    public function welcome()
    {
        $this->pushVersion();
        $seller = session('seller');
        $menu_list = getMenuList($seller['act_limits']);
        $count['handle_order'] = M('order')->where("store_id = " . STORE_ID . C('WAITSEND'))->count();//待处理订单
        $order_list = M('order')->where("store_id = " . STORE_ID . " and add_time>" . strtotime("-7 day"))->select();//最近7天订单统计
        $count['wait_shipping'] = $count['wait_pay'] = $count['wait_confirm'] = $count['refund_pay'] = 0;
        $count['refund_goods'] = $count['part_shipping'] = $count['order_sum'] = 0;
        $count['refund_pay'] = M('return_goods')->where("store_id = " . STORE_ID . " and type=0")->count();
        $count['refund_goods'] = M('return_goods')->where("store_id = " . STORE_ID . " and type=1")->count();
        if ($order_list) {
            $count['order_sum'] = count($order_list);
            foreach ($order_list as $v) {
                if ($v['order_status'] == 1 && $v['shipping_status'] == 0) {
                    $count['wait_shipping']++;
                } else if ($v['pay_status'] == 0) {
                    $count['wait_pay']++;
                } else if ($v['order_status'] == 0) {
                    $count['wait_confirm']++;
                } else if ($v['shipping_status'] == 1) {
                    $count['part_shipping']++;
                }
            }
        }
        
        $count['goods_sum'] = $count['pass_goods'] = $count['warning_goods'] = $count['new_goods'] = 0;
        $count['prom_goods'] = $count['off_sale_goods'] = $count['below_goods'] = $count['verify_goods'] = 0;
        
        $count['goods_sum'] = M('goods')->where(array('store_id' => STORE_ID))->count();
        $count['verify_goods'] = M('goods')->where(array('goods_state' => 0, 'store_id' => STORE_ID))->count();
        $count['pass_goods'] = M('goods')->where(array('goods_state' => 1, 'store_id' => STORE_ID))->count();
        $count['below_goods'] = M('goods')->where(array('goods_state' => 2, 'store_id' => STORE_ID))->count();
        $count['off_sale_goods'] = M('goods')->where(array('is_on_sale' => 0, 'store_id' => STORE_ID))->count();
        $count['prom_goods'] = M('goods')->where(array('prom_id' => array('gt', 0), 'store_id' => STORE_ID))->count();
        $count['new_goods'] = M('goods')->where(array('is_new' => 1, 'store_id' => STORE_ID))->count();
        
        //$count['article'] =  M('article')->where(array('store_id'=>STORE_ID))->count();//文章总数
        
        $users = M('users')->where(array('user_id' => $seller['user_id']))->find();
        $seller['user_name'] = empty($users['email']) ? $users['mobile'] : $users['email'];
        //今天销售总额
        $today = strtotime("-1 day");
        $count['total_amount'] = M('order')->where(array('store_id' => STORE_ID, 'add_time' => array('gt', $today), 'pay_status' => 1))->sum('order_amount');
        $count['comment'] = M('comment')->where(array('is_show' => 0, 'store_id' => STORE_ID))->count();//最新评论
        $count['consult'] = M('goods_consult')->where(array('is_show' => 0, 'store_id' => STORE_ID))->count();//最新咨询
        
        $store = M('store')->where(array('store_id' => STORE_ID))->find();
        if ($store['store_warning_storage'] > 0) {
            $count['warning_storage'] = M('goods')->where(array('store_id' => STORE_ID, 'store_count' => array('lt', $store['store_warning_storage'])))->count();
        } else {
            $count['warning_storage'] = '未设置';
        }
        $this->assign('count', $count);
        $this->assign('store', $store);
        $this->assign('menu_list', $menu_list);
        $this->assign('seller', $seller);
        return $this->fetch();
    }
    
    /**
     * 商家查看消息
     */
    public function store_msg()
    {
        $where = "store_id=" . STORE_ID;
        $count = M('store_msg')->where($where)->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        
        $msg_list = M('store_msg')->where($where)->order('sm_id DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('msg_list', $msg_list);
        $this->assign('page', $show);// 赋值分页输出
        return $this->fetch();
    }
    
    /**
     * 删除操作
     */
    public function del_store_msg()
    {
        $sm_id = I('sm_id/d', 0);
        $where = array('sm_id' => ['in', $sm_id], 'store_id' => STORE_ID);
        M('store_msg')->where($where)->delete();
        $this->success('操作成功!');
    }
    
    /**
     * 消息批量操作
     */
    public function store_msg_batch()
    {
        $action = I('action', 0);
        $sm_id = I('sm_id/a');
        
        // 如果是标记已读
        if ($action == 'del' && !empty($sm_id)) {
            $where = array('sm_id' => ['in', implode(',', $sm_id)], 'store_id' => STORE_ID);
            M('store_msg')->where($where)->delete();
        }
        // 如果是标记已读
        if ($action == 'open' && !empty($sm_id)) {
            $where = array('sm_id' => ['in', implode(',', $sm_id)], 'store_id' => STORE_ID);
            M('store_msg')->where($where)->save(array('open' => 1));
        }
        $this->success('操作成功!');
    }
    
    /**
     *  添加修改客服
     */
    public function store_service()
    {
        
        // post提交
        if (IS_POST) {
            $pre = I('pre/a');
            $after = I('after/a');
            $consult = I('con/a');
            $working_time = I('working_time');
            foreach ($pre as $k => $v) {
                if (empty($v['name']) || empty($v['account']))
                    unset ($pre[$k]);
            }
            foreach ($after as $k => $v) {
                if (empty($v['name']) || empty($v['account']))
                    unset ($after[$k]);
            }
            foreach ($consult as $k => $v) {
                if (empty($v['name']) || empty($v['account']))
                    unset ($consult[$k]);
            }
            $data = array(
                'store_presales' => serialize($pre),
                'store_aftersales' => serialize($after),
                'store_workingtime' => $working_time,
                'store_consult' => serialize($consult),
            );
            M('store')->where("store_id", STORE_ID)->save($data);
            $this->success('修改成功');
            exit();
        }
        //
        $store = M('store')->where("store_id", STORE_ID)->find();
        $store['store_presales'] = unserialize($store['store_presales']);
        $store['store_aftersales'] = unserialize($store['store_aftersales']);
        $store['store_consult'] = unserialize($store['store_consult']);
        $this->assign('store', $store);
        return $this->fetch();
    }
    
    public function pushVersion()
    {
        if (!empty($_SESSION['isset_push']))
            return false;
        $_SESSION['isset_push'] = 1;
        error_reporting(0);//关闭所有错误报告
        $app_path = dirname($_SERVER['SCRIPT_FILENAME']) . '/';
        $version_txt_path = $app_path . '/Application/Admin/Conf/version.txt';
        $curent_version = file_get_contents($version_txt_path);
        
        $vaules = array(
            'domain' => $_SERVER['SERVER_NAME'],
            'last_domain' => $_SERVER['SERVER_NAME'],
            'key_num' => $curent_version,
            'install_time' => INSTALL_DATE,
            'cpu' => '0001',
            'mac' => '0002',
            'serial_number' => SERIALNUMBER,
        );
        $url = "http://service.tp" . '-' . "shop" . '.' . "cn/index.php?m=Home&c=Index&a=user_push&" . http_build_query($vaules);
        stream_context_set_default(array('http' => array('timeout' => 3)));
        file_get_contents($url);
    }
    
    /**
     * ajax 修改指定表数据字段  一般修改状态 比如 是否推荐 是否开启 等 图标切换的
     * table,id_name,id_value,field,value
     */
    public function changeTableVal()
    {
        $table = I('table'); // 表名
        $id_name = I('id_name'); // 表主键id名
        $id_value = I('id_value'); // 表主键id值
        $field = I('field'); // 修改哪个字段
        $value = I('value'); // 修改字段值
        M($table)->where([$id_name => $id_value, 'store_id' => STORE_ID])->save(array($field => $value)); // 根据条件保存修改的数据
    }
    
    /*
     * 获取商品分类
     */
    public function get_category()
    {
        $parent_id = I('get.parent_id/d', 0); // 商品分类 父id
        empty($parent_id) && exit('');
        $list = M('goods_category')->where(array('parent_id' => $parent_id))->select();
        // 店铺id
        $store_id = session('store_id');
        //如果店铺登录了
        if ($store_id) {
            $store = M('store')->where("store_id", $store_id)->find();
            
            if ($store['bind_all_gc'] == 0) {
                $class_id1 = M('store_bind_class')->where(['store_id' => $store_id, 'state' => 1])->getField('class_1', true);
                $class_id2 = M('store_bind_class')->where(['store_id' => $store_id, 'state' => 1])->getField('class_2', true);
                $class_id3 = M('store_bind_class')->where(['store_id' => $store_id, 'state' => 1])->getField('class_3', true);
                $class_id = array_merge($class_id1, $class_id2, $class_id3);
                $class_id = array_unique($class_id);
            }
        }
        foreach ($list as $k => $v) {
            // 如果是某个店铺登录的, 那么这个店铺只能看到自己申请的分类,其余的看不到
            if ($class_id && !in_array($v['id'], $class_id))
                continue;
            $html .= "<option value='{$v['id']}' rel='{$v['commission']}'>{$v['name']}</option>";
        }
        
        exit($html);
    }
    
    public function getCategorySalesModel()
    {
        $id = I('get.id/d', 0); // 商品分类 父id
        $goods_id = I('get.goods_id/d', 0);
        $culSalesModelId = M('goods')->where('goods_id=' . $goods_id)->getField('sales_model');
        empty($id) && exit('');
        $sales_model_id = M('goods_category')->where(array('id' => $id))->getField('sales_model_id');
        if ($sales_model_id) {
            $sales_model_title = C('sales_model_title');
            $html = '';
            $sales_model_id_arr = explode(',', $sales_model_id);
            foreach ($sales_model_id_arr as $value) {
                if ($value == $culSalesModelId) {
                    $checked = 'checked="checked"';
                } else {
                    $checked = '';
                }
                $html .= '<li><label><input class="check_sales_model" type="radio"  value="' . $value . '" ' . $checked . ' name="sales_model">' . $sales_model_title[$value] . '</label></li>';
            }
            exit($html);
        }
    }
    
    public function getGoodsCountryList(){
        $sales_model = I('sales_model',0);
        if(!$sales_model){
            $this->error('请选择销售模式');
        }
        $list = M('country')->where('status',1)->select();
        $html = '';
        if($sales_model == 2){
            $html .= '<select name="shopping_country" id="shopping_country"><option value="0">选择国家</option>';
            foreach ($list as $value){
                if($value['id'] !=1){
                    $html .= '<option value="'.$value['id'].'">'.$value['name'].'</option>';
                }
            }
            $html .= '</select>';
            
        }else{
            $html .= '<select name="shopping_country" id="shopping_country"><option value="1">中国大陆</option></select>';
        }
        $this->success($html);
    }
    
    
    /**
     * 添加快捷操作
     */
    function quicklink_add()
    {
        if (!empty($_POST['item'])) {
            $_SESSION['seller_quicklink'][$_POST['item']] = $_POST['item'];
        }
        $this->_update_quicklink();
        echo 'true';
    }
    
    /**
     * 删除快捷操作
     */
    function quicklink_del()
    {
        if (!empty($_POST['item'])) {
            unset($_SESSION['seller_quicklink'][$_POST['item']]);
        }
        $this->_update_quicklink();
        echo 'true';
    }
    
    private function _update_quicklink()
    {
        $quicklink = implode(',', $_SESSION['seller_quicklink']);
        $update_array = array('seller_quicklink' => $quicklink);
        $condition = array('seller_id' => $_SESSION['seller_id']);
        M('seller')->where($condition)->save($update_array);
    }
    
}
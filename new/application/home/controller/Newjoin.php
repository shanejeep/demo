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
 * Date: 2016-05-29
 */

namespace app\home\controller;

class Newjoin extends Base
{
    public $user_id;
    public $apply = array();
    public $store_info = array();
    
    public function _initialize()
    {
        parent::_initialize();
        $this->user_id = cookie('user_id');
        if (empty($this->user_id) && ACTION_NAME != 'index') {
            $this->redirect(U('User/login_new_join'));
        } else if (!empty($this->user_id)) {
            $this->apply = M('store_apply')->where(array('user_id' => $this->user_id))->find();
            $this->store_info = M('store')->where(array('user_id' => $this->user_id))->find();
        }
        $user = get_user_info($this->user_id);
        if ($user && empty($user['password'])) {
            $this->error('您使用的是第三方账号登录，请先设置账号密码', U('User/password'));
        }
        $this->assign('user', $user);
    }
    
    public function index2()
    {
        return $this->fetch();
    }
    public function index()
    {
        $data = array();
        // 信息公告
        $data['article']['ganggao'] = M('article')->field('article_id,title')->where('cat_id = 24')->where('is_open =1')->order('article_id desc')->limit(1)->cache(true)->find();
        // 招商标准
        $data['article']['zhangshang'] = M('article')->field('article_id,title')->where('cat_id = 25')->where('is_open =1')->order('article_id desc')->limit(1)->cache(true)->find();
        // 自费标准
        $data['article']['zifei'] = M('article')->field('article_id,title')->where('cat_id = 26')->where('is_open =1')->order('article_id desc')->limit(1)->cache(true)->find();
        // 商城优势
        $data['article']['youshi'] = M('article')->field('article_id,title')->where('cat_id = 27')->where('is_open =1')->order('article_id desc')->limit(1)->cache(true)->find();
        // 帮助中心
        $data['article']['help'] = M('article')->field('article_id,title')->where('cat_id = 15')->where('is_open =1')->order('article_id desc')->limit(1)->cache(true)->find();
        // 联系方式
        $data['article']['contact'] = M('article')->field('article_id,title')->where('cat_id = 13')->where('is_open =1')->order('article_id desc')->limit(1)->cache(true)->find();
        // 申请入驻
        $data['article']['shengqingruzhu'] = M('article')->field('article_id,title')->where('cat_id = 0')->where('is_open =1')->order('article_id desc')->limit(3)->cache(true)->select();
        // 签订协议
        $data['article']['qiandingxieyi'] = M('article')->field('article_id,title')->where('cat_id = 0')->where('is_open =1')->order('article_id desc')->limit(3)->cache(true)->select();
        // 准备开店
        $data['article']['zhunbeikaizhang'] = M('article')->field('article_id,title')->where('cat_id = 0')->where('is_open =1')->order('article_id desc')->limit(3)->cache(true)->select();
        // 商品销售
        $data['article']['shangpingxiaoshou'] = M('article')->field('article_id,title')->where('cat_id = 0')->where('is_open =1')->order('article_id desc')->limit(3)->cache(true)->select();
        $this->assign('data',$data);
        return $this->fetch();
    }
    public function index_join()
    {
        $user_id=$this->user_id;
        $info= M('store_apply')->where('user_id',$user_id)->find();
        if(!empty($info)){
            if($info['apply_state'] == 1){
                 $this->success('系统已经通过您提交的申请，请登录商家中心！',U('seller/Admin/login'));
            }
            if($info['apply_state'] == 0){
                 $this->success('您已提交申请，请等待审核！',U('home/newjoin/index'));
            }
          exit;
        }
        if(IS_POST){
            $data = I('post.');
            $is_ajax=I('is_ajax');
            list($data['contacts_name'],$data['contacts_mobile'],$data['contacts_email'])=array($data['store_person_name'],$data['store_person_mobile'],$data['store_person_email']);
            $data['user_id'] = $this->user_id;
            $data['seller_name'] = M('users')->where('user_id',$this->user_id)->getField('mobile');
            $data['add_time'] = time();
            //图片上传
            $imgs=$this->up_img("store_apply");
            if(!empty($imgs)){
                foreach ($imgs as $k => $v) {
                   $data[$k]=$v;
                }
            }else{
                 echo json_encode(array('status'=>0,'msg'=>'上传图片为空或上传图片大于1M！'));
                 exit;
            }
            //店铺信息验证
            if(empty($data['company_province']) || empty($data['company_city']) || empty($data['company_district']) || empty($data['store_name']) || empty($data['store_logo']) ){
                   echo json_encode(array('status'=>0,'msg'=>'请完善店铺信息！'));
                 exit;
            }
             //店铺负责人信息
            if(empty($data['store_person_name']) || empty($data['store_person_mobile']) || empty($data['store_person_email']) || empty($data['store_person_cert']) || empty($data['store_person_cert_f'])){
                echo json_encode(array('status'=>0,'msg'=>'请完善店主或店铺负责人信息！'));
                 exit;
            }

            if($data['apply_type'] == 0){
                //企业信息验证
                if(empty($data['company_name']) || empty($data['sc_id']) || empty($data['business_licence_cert'])){
                    echo json_encode(array('status'=>0,'msg'=>'请完善企业信息！'));
                     exit;
                }
               
            }
            //提交数据
            M('store_apply')->add($data);
            if($is_ajax == 1){
                echo json_encode(array('status'=>1));
            }else{
                 $this->success('提交成功，请耐心等待审核！',U('home/newJoin/index'));
            }
             exit;
            
        }
        $rate_list = array(0 => 0, 3 => 3, 6 => 6, 7 => 7, 11 => 11, 13 => 13, 17 => 17);
        $company_type = config('company_type');
        $this->assign('company_type', $company_type);
        $this->assign('apply', $this->apply);
        $this->assign('rate_list', $rate_list);
        $province = M('region')->where(array('parent_id' => 0))->select();
        $this->assign('province', $province);
        $this->assign('city', M('region')->where(array('parent_id' => $this->apply['company_province']))->select());
        $this->assign('district', M('region')->where(array('parent_id' => $this->apply['company_city']))->select());

        $this->assign('store_class', M('store_class')->select());
        $this->assign('goods_category', M('goods_category')->where(array('parent_id' => 0))->select());
        $this->assign('province', M('region')->where(array('parent_id' => 0, 'level' => 1))->select());
        return $this->fetch();
    }
    public function contact()
    {
        if ($this->apply['apply_state'] == 1) $this->redirect(U('Newjoin/apply_info'));
        if (IS_POST) {
            $data = I('post.');
            if (empty($this->apply)) {
                $data['user_id'] = $this->user_id;
                $data['add_time'] = time();
                if (M('store_apply')->add($data)) {
                    if ($data['apply_type'] == 0) {
                        $this->redirect(U('Newjoin/basic_info'));
                    } else {
                        $this->redirect(U('Newjoin/basic_info', array('apply_type' => 1)));
                    }
                } else {
                    $this->error('服务器繁忙,请联系官方客服');
                }
            } else {
                M('store_apply')->where(array('user_id' => $this->user_id))->save($data);
                $this->redirect(U('Newjoin/basic_info', array('apply_type' => $data['apply_type'])));
            }
        }
        $this->assign('apply', $this->apply);
        return $this->fetch();
    }
    
    public function basic_info()
    {
        if ($this->apply['apply_state'] == 1) $this->redirect(U('Newjoin/apply_info'));
        if (IS_POST) {
            $data = I('post.supplier/a');
            M('store_apply')->where(array('user_id' => $this->user_id))->save($data);
            $this->redirect(U('Newjoin/seller_info'));
        }
        $rate_list = array(0 => 0, 3 => 3, 6 => 6, 7 => 7, 11 => 11, 13 => 13, 17 => 17);
        $company_type = config('company_type');
        $this->assign('company_type', $company_type);
        $this->assign('apply', $this->apply);
        $this->assign('rate_list', $rate_list);
        $province = M('region')->where(array('parent_id' => 0))->select();
        $this->assign('province', $province);
        if (!empty($this->apply['company_province'])) {
            $this->assign('city', M('region')->where(array('parent_id' => $this->apply['company_province']))->select());
            $this->assign('district', M('region')->where(array('parent_id' => $this->apply['company_city']))->select());
        }
        $apply_type = I('apply_type', 0);
        if ($apply_type == 1 || $this->apply['apply_type'] == 1) {
            $this->assign('store_class', M('store_class')->getField('sc_id,sc_name'));
            $this->assign('goods_category', M('goods_category')->where(array('parent_id' => 0))->getField('id,name'));
            $this->assign('province', M('region')->where(array('parent_id' => 0, 'level' => 1))->select());
            return $this->fetch('basic');
        } else {
            return $this->fetch();
        }
    }
    
    public function agreement()
    {
        if (empty($this->user_id)) $this->success('请先登录', U('Home/User/login_new_join'));
        if ($this->store_info) $this->error('店铺已创建', U('seller/Admin/login'));
        if (!empty($this->apply)) {
            if ($this->apply['apply_state'] == 1) {
                $this->redirect(U('Newjoin/apply_info'));
            } else if ($this->apply['apply_state'] == 0 && empty($this->apply['company_name'])) {
                $this->redirect(U('Newjoin/basic_info'));
            } else if (empty($this->apply['store_name'])) {
                if ($this->apply['apply_type'] == 1) {
                    $this->redirect(U('Newjoin/basic'));
                } else {
                    $this->redirect(U('Newjoin/seller_info'));
                }
            } else if ($this->apply['apply_state'] == 0 && empty($this->apply['business_licence_cert'])) {
                $this->redirect(U('Newjoin/remark'));
            } else {
                $this->redirect(U('Newjoin/apply_info'));
            }
        }
        if (IS_POST) {
            $this->redirect(U('Newjoin/contact'));
        }
        return $this->fetch();
    }
    
    public function seller_info()
    {
        if ($this->apply['apply_state'] == 1) $this->redirect(U('Newjoin/apply_info'));
        if (IS_POST) {
            $data = I('post.');
            if (!empty($data['store_class_ids'])) {
                $data['store_class_ids'] = serialize($data['store_class_ids']);
            }
            if ($this->apply['apply_type'] == 1) {
                //个人申请
                if (empty($this->apply['legal_identity_cert']) || empty($this->apply['store_person_cert'])) {
                    foreach ($_FILES as $k => $v) {
                        if (empty($v['tmp_name'])) {
                            $this->error('请上传必要证件');
                        }
                    }
                    
                    $files = $this->request->file();
                    $savePath = 'public/upload/store/cert/' . date('Y-m-d') . '/';
                    if (!($_exists = file_exists($savePath))) {
                        $isMk = mkdir($savePath);
                    }
                    foreach ($files as $key => $file) {
                        $info = $file->rule(function ($file) {
                            return md5(mt_rand()); // 使用自定义的文件保存规则
                        })->validate(['size' => 1024 * 1024 * 10, 'ext' => 'jpg,png,gif,jpeg'])->move($savePath, true);
                        if ($info) {
                            $filename = $info->getFilename();
                            $new_name = '/' . $savePath . $filename;
                            $data[$key] = $new_name;
                        } else {
                            $this->error($file->getError());//上传错误提示错误信息
                        }
                    }
                }
            }
            
            M('store_apply')->where(array('user_id' => $this->user_id))->save($data);
            if ($this->apply['apply_type'] == 1) {
                $this->redirect(U('Newjoin/apply_info'));
            } else {
                $this->redirect(U('Newjoin/remark'));
            }
        }
        $this->assign('apply', $this->apply);
        $this->assign('store_class', M('store_class')->getField('sc_id,sc_name'));
        if (!empty($this->apply['store_class_ids'])) {
            $goods_cates = M('goods_category')->getField('id,name,commission');
            $store_class_ids = unserialize($this->apply['store_class_ids']);
            foreach ($store_class_ids as $val) {
                $cat = explode(',', $val);
                $bind_class_list[] = array('class_1' => $goods_cates[$cat[0]]['name'], 'class_2' => $goods_cates[$cat[1]]['name'],
                    'class_3' => $goods_cates[$cat[2]]['name'] . '(分佣比例：' . $goods_cates[$cat[2]]['commission'] . '%)', 'value' => $val
                );
            }
            $this->assign('bind_class_list', $bind_class_list);
        }
        $this->assign('goods_category', M('goods_category')->where(array('parent_id' => 0))->getField('id,name'));
        $this->assign('province', M('region')->where(array('parent_id' => 0, 'level' => 1))->select());
        if (!empty($this->apply['bank_province'])) {
            $this->assign('city', M('region')->where(array('parent_id' => $this->apply['bank_province']))->select());
        }
        return $this->fetch();
    }
    
    public function query_progress()
    {
        return $this->fetch();
    }
    
    public function remark()
    {
        if ($this->apply['apply_state'] == 1) $this->redirect(U('Newjoin/apply_info'));
        if (IS_POST) {
            $data = I('post.');
            $flag = false;
            foreach ($_FILES as $k => $v) {
                if (!empty($v['tmp_name'])) {
                    $flag = true;
                }
            }
            if ($flag) {
                $files = $this->request->file();
                $savePath = 'public/upload/store/cert/' . date('Y-m-d') . '/';
                
                if (!($_exists = file_exists($savePath))) {
                    $isMk = mkdir($savePath);
                }
                
                foreach ($files as $key => $file) {
                    $info = $file->rule(function ($file) {
                        return md5(mt_rand()); // 使用自定义的文件保存规则
                    })->validate(['size' => 1024 * 1024 * 10, 'ext' => 'jpg,png,gif,jpeg'])->move($savePath, true);
                    if ($info) {
                        $filename = $info->getFilename();
                        $new_name = '/' . $savePath . $filename;
                        $data[$key] = $new_name;
                    } else {
                        $this->error($file->getError());//上传错误提示错误信息
                    }
                }
            }
            $data['apply_state'] = 0;//每次提交资料回到待审核状态
            M('store_apply')->where(array('user_id' => $this->user_id))->save($data);
            $this->success('提交成功', U('Newjoin/apply_info'));
        }
        
        $this->assign('apply', $this->apply);
        return $this->fetch();
    }
    
    public function apply_info()
    {
        $this->assign('apply', $this->apply);
        if (IS_POST) {
            $paying_amount_cert = I('paying_amount_cert');
            if (empty($paying_amount_cert)) {
                $this->error('请上传支付凭证');
            } else {
                M('store_apply')->where(array('user_id' => $this->user_id))->save(array('paying_amount_cert' => $paying_amount_cert));
                $this->success('提交成功');
            }
        }
        return $this->fetch();
    }
    
    public function check_company()
    {
        $company_name = I('company_name');
        if (empty($company_name)) exit('fail');
        if ($company_name && M('store_apply')->where(array('company_name' => $company_name, 'user_id' => array('neq', $this->user_id)))->count() > 0) {
            exit('fail');
        }
        exit('success');
    }
    
    public function check_store()
    {
        $store_name = I('store_name');
        if (empty($store_name)) exit('fail');
        if (M('store_apply')->where(array('store_name' => $store_name))->count() > 0) {
            exit('fail');
        }
        exit('success');
    }
    
    public function check_seller()
    {
        $seller_name = I('seller_name');
        if (empty($seller_name)) exit('fail');
        if (M('seller')->where(array('seller_name' => $seller_name))->count() > 0) {
            exit('fail');
        }
        exit('success');
    }
    
    public function question()
    {
        $cat_id = I('cat_id/d');
        $article = M('article')->where("cat_id", $cat_id)->select();
        if ($article) {
            $parent = M('article_cat')->where(array('cat_id' => $cat_id))->find();
            $this->assign('cat_name', $parent['cat_name']);
            $this->assign('article', $article[0]);
            $this->assign('article_list', $article);
        }
        return $this->fetch('article/detail_new_join');
    }
}
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
 * Author: IT宇宙人
 * Date: 2015-09-09
 */

namespace app\seller\controller;

use think\Db;
use think\Page;
use think\AjaxPage;
use app\seller\logic\GoodsLogic;
use OSS\OssClient;
use OSS\Core\OssException;

class Goods extends Base
{
    
    
    public function ajaxGetExtendCat()
    {
    
    }
    
    /**
     * 获取商品分类 的帅选规格 复选框
     */
    public function ajaxGetSpecList()
    {
        $GoodsLogic = new GoodsLogic();
        $_REQUEST['category_id'] = $_REQUEST['category_id'] ? $_REQUEST['category_id'] : 0;
        $filter_spec = M('GoodsCategory')->where("id", $_REQUEST['category_id'])->getField('filter_spec');
        $filter_spec_arr = explode(',', $filter_spec);
        $str = $GoodsLogic->GetSpecCheckboxList($_REQUEST['type_id'], $filter_spec_arr);
        $str = $str ? $str : '没有可帅选的商品规格';
        exit($str);
    }
    
    /**
     * 获取商品分类 的帅选属性 复选框
     */
    public function ajaxGetAttrList()
    {
        $GoodsLogic = new GoodsLogic();
        $_REQUEST['category_id'] = $_REQUEST['category_id'] ? $_REQUEST['category_id'] : 0;
        $filter_attr = M('GoodsCategory')->where("id", $_REQUEST['category_id'])->getField('filter_attr');
        $filter_attr_arr = explode(',', $filter_attr);
        $str = $GoodsLogic->GetAttrCheckboxList($_REQUEST['type_id'], $filter_attr_arr);
        $str = $str ? $str : '没有可帅选的商品属性';
        exit($str);
    }
    
    /**
     * 删除分类
     */
    public function delGoodsCategory()
    {
        // 判断子分类
        $GoodsCategory = M("GoodsCategory");
        $count = $GoodsCategory->where("parent_id", $_GET['id'])->count("id");
        $count > 0 && $this->error('该分类下还有分类不得删除!', U('Admin/Goods/categoryList'));
        // 判断是否存在商品
        $goods_count = M('Goods')->where("cat_id", $_GET['id'])->count('1');
        $goods_count > 0 && $this->error('该分类下有商品不得删除!', U('Admin/Goods/categoryList'));
        // 删除分类
        $GoodsCategory->where("id", $_GET['id'])->delete();
        $this->success("操作成功!!!", U('Admin/Goods/categoryList'));
    }
    
    /**
     *  商品列表
     */
    public function goodsList()
    {
        checkIsBack();
        $this->assign("is_drug",I("is_drug",0));
        $store_goods_class_list = M('store_goods_class')->where(['parent_id' => 0, 'store_id' => STORE_ID])->select();
        $this->assign('store_goods_class_list', $store_goods_class_list);
        $suppliers_list = M('suppliers')->where(array('store_id' => STORE_ID))->select();
        $this->assign('suppliers_list', $suppliers_list);
        return $this->fetch('goodsList');
    }

    /**
     *  药品列表
     */
    public function drugList()
    {
        checkIsBack();
        $store_goods_class_list = M('store_goods_class')->where(['parent_id' => 0, 'store_id' => STORE_ID])->select();
        $this->assign('store_goods_class_list', $store_goods_class_list);
        $suppliers_list = M('suppliers')->where(array('store_id' => STORE_ID))->select();
        $this->assign('suppliers_list', $suppliers_list);
        return $this->fetch('drugList');
    }
    
    // 商品二维码，文件名输出
    public function goods_qrcode()
    {
        $server_name = 'http://' . $_SERVER['SERVER_NAME'];
        $goods_id = I('goods_id', 0);
        $filename = "public/upload/goods/qrcode/qrcode_{$goods_id}.png";
        $aliyun_oss = C('aliyun_oss');
        Vendor('.aliyuncs.oss-sdk-php.autoload');
        $ossClient= new OssClient($aliyun_oss['accessKeyId'],$aliyun_oss['accessKeySecret'],$aliyun_oss['endpoint'],false);
        $object  = "upload/goods/qrcode/qrcode_{$goods_id}.png";
        $exist = $ossClient->doesObjectExist($aliyun_oss['bucket'], $object);
        if(!$exist){
            vendor('phpqrcode.phpqrcode');   // 导入Vendor类库包 Library/Vendor/Zend/Server.class.php
            error_reporting(E_ERROR);
            $url = "{$server_name}/goods/{$goods_id}.html";
            $url = urldecode($url);
            $errorCorrectionLevel = 'L';  // 纠错级别：L、M、Q、H
            $matrixPointSize = 6;   // 点的大小：1到10
            \QRcode::png($url, $filename, $errorCorrectionLevel, $matrixPointSize, 2);
            $ossClient->uploadFile($aliyun_oss['bucket'], $object, $filename);
            // $fileurl = str_replace('application/seller/controller/Goods.php', $filename, __FILE__);
            // unlink($fileurl);
        }
		$goods_name = M("goods")->where("goods_id",$goods_id)->getField("goods_name");
        $this->assign('qrcode_url', $aliyun_oss['ossurl'].$object);
		$this->assign('goods_name', $goods_name);
        return $this->fetch();
    }
    
    /**
     *  商品列表
     */
    public function ajaxGoodsList()
    {
        $where['store_id'] = STORE_ID;
        $intro = I('intro', 0);
        $store_cat_id1 = I('store_cat_id1', '');
        $key_word = trim(I('key_word', ''));
        $orderby1 = I('post.orderby1', '');
        $orderby2 = I('post.orderby2', '');
        if (!empty($intro)) {
            $where[$intro] = 1;
        }
        if ($store_cat_id1 !== '') {
            $where['store_cat_id1'] = $store_cat_id1;
        }
        $where['is_on_sale'] = 1;
        $where['is_drug'] = I("is_drug",0);
        $where['goods_state'] = 1;
        if ($key_word !== '') {
            $where['goods_name|goods_sn'] = array('like', '%' . $key_word . '%');
        }
        $order_str = array();
        if ($orderby1 !== '') {
            $order_str[$orderby1] = $orderby2;
        }
        $model = M('Goods');
        $count = $model->where($where)->count();
        $Page = new AjaxPage($count, 10);
        
        //是否从缓存中获取Page
        if (session('is_back') == 1) {
            $Page = getPageFromCache();
            //重置获取条件
            delIsBack();
        }
        $goodsList = $model->where($where)->order($order_str)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        cachePage($Page);
        $show = $Page->show();
        
        $catList = M('goods_category')->cache(true)->select();
        $catList = convert_arr_key($catList, 'id');
        $store_warning_storage = M('store')->where('store_id', STORE_ID)->getField('store_warning_storage');
        //药品属性
        $this->assign("drug_attr",C("drug_attr"));
        $this->assign('store_warning_storage', $store_warning_storage);
        $this->assign('catList', $catList);
        $this->assign('is_drug', I("is_drug",0));
        $this->assign('goodsList', $goodsList);
        $this->assign('page', $show);// 赋值分页输出
        return $this->fetch();
    }
    
    //检查店家是否绑定有经营类目
    public function ajaxCheckCat()
    {
        $where['store_id'] = STORE_ID;
        $where['state'] = 1;
        $catCount = M('store_bind_class')->where($where)->count();
        $catCount == 0 ? $status = 0 : $status = 1;
        $this->ajaxReturn(array('status' => $status));
    }
    
    public function goods_offline()
    {
        $where['store_id'] = STORE_ID;
        $model = M('Goods');
        if (I('is_on_sale') == 2) {
            $where['is_on_sale'] = 2;
        } else {
            $where['is_on_sale'] = 0;
        }
        $goods_state = I('goods_state', '', 'string'); // 商品状态  0待审核 1审核通过 2审核失败
        if ($goods_state != '') {
            $where['goods_state'] = intval($goods_state);
        }
        $store_cat_id1 = I('store_cat_id1', '');
        if ($store_cat_id1 !== '') {
            $where['store_cat_id1'] = $store_cat_id1;
        }
        $key_word = trim(I('key_word', ''));
        if ($key_word !== '') {
            $where['goods_name|goods_sn'] = array('like', '%' . $key_word . '%');
        }
        $count = $model->where($where)->count();
        $Page = new Page($count, 10);
        $goodsList = $model->where($where)->order('goods_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $show = $Page->show();
        $store_goods_class_list = M('store_goods_class')->where(['parent_id' => 0, 'store_id' => STORE_ID])->select();
        $this->assign('store_goods_class_list', $store_goods_class_list);
        $suppliers_list = M('suppliers')->where(array('store_id' => STORE_ID))->select();
         $parent_cat = M("goods_category")->where('parent_id',0)->where('is_show',1)->getField("id,name");
        $this->assign('suppliers_list', $suppliers_list);
        $this->assign('state', C('goods_state'));
        $this->assign('goodsList', $goodsList);
        $this->assign("parent_cat",$parent_cat);
        $this->assign("drug_attr",C("drug_attr")); //药品属性
        $this->assign('page', $show);// 赋值分页输出
        return $this->fetch();
    }
    
    public function stock_list()
    {
        $model = M('stock_log');
        $map['store_id'] = STORE_ID;
        $mtype = I('mtype');
        if ($mtype == 1) {
            $map['stock'] = array('gt', 0);
        }
        if ($mtype == -1) {
            $map['stock'] = array('lt', 0);
        }
        $goods_name = I('goods_name');
        if ($goods_name) {
            $map['goods_name'] = array('like', "%$goods_name%");
        }
        $ctime = I('ctime');
        if ($ctime) {
            $gap = explode(' - ', $ctime);
            $this->assign('ctime', $gap[0] . ' - ' . $gap[1]);
            $this->assign('start_time', $gap[0]);
            $this->assign('end_time', $gap[1]);
            $map['ctime'] = array(array('gt', strtotime($gap[0])), array('lt', strtotime($gap[1])));
        }
        $count = $model->where($map)->count();
        $Page = new Page($count, 20);
        $show = $Page->show();
        $this->assign('page', $show);// 赋值分页输出
        $stock_list = $model->where($map)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        foreach($stock_list as $k => &$v){
            $v['drug_attr'] = M("goods")->where("goods_id",$v['goods_id'])->getField("drug_attr");
        }
        unset($v);
          $this->assign("drug_attr",C("drug_attr")); //药品属性
        $this->assign('stock_list', $stock_list);
        return $this->fetch();
    }
    
    /**
     * 添加修改商品
     */
    public function addEditGoods()
    {
        $GoodsLogic = new GoodsLogic();
        $Goods = new \app\admin\model\Goods();
        $goods_id = I('goods_id/d', 0);
        $type = $goods_id > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
        if ($goods_id > 0) {
            $c = M('goods')->where(['goods_id' => $goods_id, 'store_id' => STORE_ID])->count();
            if ($c == 0)
                $this->error("非法操作", U('Goods/goodsList'));
        }
        //ajax提交验证
        if (($_GET['is_ajax'] == 1) && IS_POST) {
            // 数据验证
            $validate = \think\Loader::validate('admin/Goods');
            if (!$validate->batch()->check(input('post.'))) {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'data' => $error,
                );
                $this->ajaxReturn($return_arr);
            }
            $data = input('post.');
            $Goods->data($data, true); // 收集数据
            
            $pwd = session('pwd');
            $adminPwd = C('seller_admin_pwd'); //'seller_admin_pwd' => 'yzjadmin2017*',
            if ($pwd != $adminPwd) {
                $Goods->on_time = time(); // 上架时间
            }
			//查询相册图片是否过大
            $goods_images_size=$_FILES['goods_images']['size'];
            foreach($goods_images_size as $k =>$v){
                if($v > 4*1024*1024){
                     $this->ajaxReturn(array('status' => -1, 'msg' => '相册图片不能大于1M！', 'data' => ''));
                }
            }
			
            //查询老数据
            empty($Goods->goods_id) ? $old_original_img = null : $old_original_img = M('goods')->where("goods_id=" . $Goods->goods_id)->getField('original_img');
            
            $original_img = request()->file('original_img');
            if (!empty($original_img)) {
                $info = $this->up_img('goods', 'original_img', $old_original_img);
                if (!$info) {
                    $this->ajaxReturn(array('status' => -1, 'msg' => '上传图片过大！', 'data' => ''));
                }
                $Goods->original_img = $info['original_img'];
            }
            $cat_id3 = I('cat_id3', 0);
            $_POST['extend_cat_id_2'] && ($Goods->extend_cat_id = I('extend_cat_id_2'));
            $_POST['extend_cat_id_3'] && ($Goods->extend_cat_id = I('extend_cat_id_3'));
            $Goods->shipping_area_ids = implode(',', I('shipping_area_ids/a', []));
            $Goods->shipping_area_ids = $Goods->shipping_area_ids ? $Goods->shipping_area_ids : '';
            
            $type_id = M('goods_category')->where("id", $cat_id3)->getField('type_id'); // 找到这个分类对应的type_id
            
            $stores = M('store')->where(array('store_id' => STORE_ID))->getField('store_id , goods_examine,is_own_shop', 1);
            $store_goods_examine = $stores[STORE_ID]['goods_examine'];
            
            //总平台自营标识为2 , 第三方自营店标识为1
            // $is_own_shop = (STORE_ID == 1) ? 2 : ($stores[STORE_ID]['is_own_shop']);
            $my_store_id = STORE_ID;
            $is_own_shop = ($stores[$my_store_id]['is_own_shop'] == 1) ? 2 : 1;
            
            
            $Goods->is_own_shop = $is_own_shop;
            $Goods->goods_type = $type_id ? $type_id : 0;
            $Goods->store_id = STORE_ID; // 店家id
            $virtual_indate = I('virtual_indate');//虚拟商品有效期
            $Goods->virtual_indate = !empty($virtual_indate) ? strtotime($virtual_indate) : 0;
            if ($store_goods_examine) {
                $Goods->goods_state = 0; // 待审核
                $Goods->is_on_sale = 0; // 下架
            } else {
                $Goods->goods_state = 1; // 出售中
            }
           /*  
            if ($Goods->distribut > ($Goods->shop_price / 2))
                $this->ajaxReturn(array('status' => -1, 'msg' => '分销的分成金额不得超过商品金额的50%', 'data' => '')); */
			/*------------deng start-------------*/
            //总的佣金<=商品价格
			if(empty($_POST['sales_commission'])){
				$Goods->sales_commission = 0;
			}
			if(empty($_POST['promote_commission'])){
				$Goods->promote_commission = 0;
			}
            if (($Goods->sales_commission+$Goods->promote_commission) > ($Goods->shop_price))
                $this->ajaxReturn(array('status' => -1, 'msg' => '商品销售佣金、商品推广佣金之和不得超过商品本店售价', 'data' => ''));
            /*------------deng end-------------*/
            
            if ($type == 2) {
                $goods = M('goods')->where(array('goods_id' => $goods_id, 'store_id' => STORE_ID))->find();
                if ($goods) {
                    // 修改商品后购物车的商品价格也修改一下
                    Db::name('cart')->where("goods_id", $goods_id)->where("spec_key = ''")->save(array(
                        'market_price' => $_POST['market_price'], //市场价
                        'goods_price' => $_POST['shop_price'], // 本店价
                        'member_goods_price' => $_POST['shop_price'], // 会员折扣价
                        'sales_model' => $_POST['sales_model'],
                    ));
                    if (empty($_POST['item']) && $_POST['store_count'] != $goods['store_count']) {
                        $_POST['store_count'] = $_POST['store_count'] - $goods['store_count'];
                        update_stock_log(session('admin_id'), $_POST['store_count'], array('goods_id' => $goods_id, 'goods_name' => $_POST['goods_name'], 'store_id' => STORE_ID));
                    } else {
                        unset($Goods->store_count);
                    }
                    $update = $Goods->isUpdate(true)->save(); // 写入数据到数据库
                    // 更新成功后删除缩略图
                    if ($update !== false) {
                        delFile("./public/upload/goods/thumb/$goods_id", true);
                    }
                } else {
                    $this->ajaxReturn(array('status' => -1, 'msg' => '非法操作'), 'JSON');
                }
            } else {
                $Goods->save(); // 新增数据到数据库
                $goods_id = $Goods->getLastInsID();
                //商品进出库记录日志
                if (empty($_POST['item'])) {
                    update_stock_log(session('admin_id'), $_POST['store_count'], array('goods_id' => $goods_id, 'goods_name' => $_POST['goods_name'], 'store_id' => STORE_ID));
                }
            }
            $Goods->afterSave($goods_id, STORE_ID);
            $GoodsLogic->saveGoodsAttr($goods_id, $type_id, STORE_ID); // 处理商品 属性
            $return_arr = array(
                'status' => 1,
                'msg' => '操作成功',
                'data' => array('url' => U('Goods/goodsList')),
            );
            //重定向, 调整之前URL是设置参数获取方式
            session("is_back", 1);
            $this->ajaxReturn($return_arr);
            
        }
        
        $goodsInfo = M('Goods')->where('goods_id', I('get.goods_id/d', 0))->find();
        if ($goodsInfo) {
            $goodsInfo['ext_cat_list'] = $Goods->getGoodsExtendCat($goods_id);
        }
        $store = M('store')->where(array('store_id' => STORE_ID))->find();
        if ($store['bind_all_gc'] == 1) {
            $cat_list = M('goods_category')->where(['parent_id' => 0,'is_drug'=>0])->select();//自营店已绑定所有分类
        } else {
            //自营店已绑定所有分类
            $cat_list = Db::name('goods_category')->where(['parent_id' => 0,'is_drug'=>0])->where('id', 'IN', function ($query) {
                $query->name('store_bind_class')->where('store_id', STORE_ID)->where('state', 1)->field('class_1');
            })->select();
        }
        $store_goods_class_list = M('store_goods_class')->where(['parent_id' => 0, 'store_id' => STORE_ID])->select(); //店铺内部分类
        $brandList = $GoodsLogic->getSortBrands();
        $map = array();
        $map['status'] = 1;
        if ($goodsInfo) {
            $map['id'] = ($goodsInfo['sales_model'] == 2) ? array('gt', 1) : 1;
        }
        $countryList = M('country')->where($map)->select();
        $goodsType = M("GoodsType")->select();
        $suppliersList = M("suppliers")->select();
        $plugin_shipping = M('plugin')->where(array('type' => array('eq', 'shipping')))->select();//插件物流
        $shipping_area = D('Shipping_area')->getShippingArea(STORE_ID);//配送区域
        $goods_shipping_area_ids = explode(',', $goodsInfo['shipping_area_ids']);
        $this->assign('goods_shipping_area_ids', $goods_shipping_area_ids);
        $this->assign('shipping_area', $shipping_area);
        $this->assign('plugin_shipping', $plugin_shipping);
        $this->assign('cat_list', $cat_list);
        $this->assign('store_goods_class_list', $store_goods_class_list);
        $this->assign('brandList', $brandList);
        $this->assign('countryList', $countryList);
        $this->assign('goodsType', $goodsType);
        $this->assign('suppliersList', $suppliersList);
        $this->assign('goodsInfo', $goodsInfo);  // 商品详情
        $goodsImages = M("GoodsImages")->where('goods_id', I('get.goods_id/d', 0))->select();
        $this->assign('goodsImages', $goodsImages);  // 商品相册
        $image_count = count($goodsImages);
        $this->assign('image_count', $image_count);
        $this->initEditor(); // 编辑器
        return $this->fetch('_goods');
    }

      /**
     * 添加修改商品
     */
    public function addEditDrug()
    {
        $GoodsLogic = new GoodsLogic();
        $Goods = new \app\admin\model\Goods();
        $goods_id = I('goods_id/d', 0);
        $type = $goods_id > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
        if ($goods_id > 0) {
            $c = M('goods')->where(['goods_id' => $goods_id, 'store_id' => STORE_ID])->count();
            if ($c == 0)
                $this->error("非法操作", U('Goods/goodsList'));
        }
        //ajax提交验证
        if (($_GET['is_ajax'] == 1) && IS_POST) {
            // 数据验证
            $validate = \think\Loader::validate('admin/Goods');
            if (!$validate->batch()->check(input('post.'))) {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'data' => $error,
                );
                $this->ajaxReturn($return_arr);
            }
            $data = input('post.');
            $Goods->data($data, true); // 收集数据
            
            $pwd = session('pwd');
            $adminPwd = C('seller_admin_pwd'); //'seller_admin_pwd' => 'yzjadmin2017*',
            if ($pwd != $adminPwd) {
                $Goods->on_time = time(); // 上架时间
            }
            //查询相册图片是否过大
            $goods_images_size=$_FILES['goods_images']['size'];
            foreach($goods_images_size as $k =>$v){
                if($v > 1024*1024){
                     $this->ajaxReturn(array('status' => -1, 'msg' => '相册图片不能大于1M！', 'data' => ''));
                }
            }
            
            //查询老数据
            empty($Goods->goods_id) ? $old_original_img = null : $old_original_img = M('goods')->where("goods_id=" . $Goods->goods_id)->getField('original_img');
            
            $original_img = request()->file('original_img');
            if (!empty($original_img)) {
                $info = $this->up_img('goods', 'original_img', $old_original_img);
                if (!$info) {
                    $this->ajaxReturn(array('status' => -1, 'msg' => '上传图片过大！', 'data' => ''));
                }
                $Goods->original_img = $info['original_img'];
            }
            $cat_id3 = I('cat_id3', 0);
            $_POST['extend_cat_id_2'] && ($Goods->extend_cat_id = I('extend_cat_id_2'));
            $_POST['extend_cat_id_3'] && ($Goods->extend_cat_id = I('extend_cat_id_3'));
            $Goods->shipping_area_ids = implode(',', I('shipping_area_ids/a', []));
            $Goods->shipping_area_ids = $Goods->shipping_area_ids ? $Goods->shipping_area_ids : '';
            
            $type_id = M('goods_category')->where("id", $cat_id3)->getField('type_id'); // 找到这个分类对应的type_id
            
            $stores = M('store')->where(array('store_id' => STORE_ID))->getField('store_id , goods_examine,is_own_shop', 1);
            $store_goods_examine = $stores[STORE_ID]['goods_examine'];
            
            //总平台自营标识为2 , 第三方自营店标识为1
            // $is_own_shop = (STORE_ID == 1) ? 2 : ($stores[STORE_ID]['is_own_shop']);
            $my_store_id = STORE_ID;
            $is_own_shop = ($stores[$my_store_id]['is_own_shop'] == 1) ? 2 : 1;
            
            
            $Goods->is_own_shop = $is_own_shop;
            $Goods->goods_type = $type_id ? $type_id : 0;
            $Goods->store_id = STORE_ID; // 店家id
            $virtual_indate = I('virtual_indate');//虚拟商品有效期
            $Goods->virtual_indate = !empty($virtual_indate) ? strtotime($virtual_indate) : 0;
            if ($store_goods_examine) {
                $Goods->goods_state = 0; // 待审核
                $Goods->is_on_sale = 0; // 下架
            } else {
                $Goods->goods_state = 1; // 出售中
            }
           /*  
            if ($Goods->distribut > ($Goods->shop_price / 2))
                $this->ajaxReturn(array('status' => -1, 'msg' => '分销的分成金额不得超过商品金额的50%', 'data' => '')); */
            /*------------deng start-------------*/
            //总的佣金<=商品价格
            if(empty($_POST['sales_commission'])){
                $Goods->sales_commission = 0;
            }
            if(empty($_POST['promote_commission'])){
                $Goods->promote_commission = 0;
            }
            if (($Goods->sales_commission+$Goods->promote_commission) > ($Goods->shop_price))
                $this->ajaxReturn(array('status' => -1, 'msg' => '商品销售佣金、商品推广佣金之和不得超过商品本店售价', 'data' => ''));
            /*------------deng end-------------*/
            
            if ($type == 2) {
                $goods = M('goods')->where(array('goods_id' => $goods_id, 'store_id' => STORE_ID))->find();
                if ($goods) {
                    // 修改商品后购物车的商品价格也修改一下
                    Db::name('cart')->where("goods_id", $goods_id)->where("spec_key = ''")->save(array(
                        'market_price' => $_POST['market_price'], //市场价
                        'goods_price' => $_POST['shop_price'], // 本店价
                        'member_goods_price' => $_POST['shop_price'], // 会员折扣价
                        'sales_model' => $_POST['sales_model'],
                    ));
                    if (empty($_POST['item']) && $_POST['store_count'] != $goods['store_count']) {
                        $_POST['store_count'] = $_POST['store_count'] - $goods['store_count'];
                        update_stock_log(session('admin_id'), $_POST['store_count'], array('goods_id' => $goods_id, 'goods_name' => $_POST['goods_name'], 'store_id' => STORE_ID));
                    } else {
                        unset($Goods->store_count);
                    }
                    $update = $Goods->isUpdate(true)->save(); // 写入数据到数据库
                    // 更新成功后删除缩略图
                    if ($update !== false) {
                        delFile("./public/upload/goods/thumb/$goods_id", true);
                    }
                } else {
                    $this->ajaxReturn(array('status' => -1, 'msg' => '非法操作'), 'JSON');
                }
            } else {
                $Goods->save(); // 新增数据到数据库
                $goods_id = $Goods->getLastInsID();
                //商品进出库记录日志
                if (empty($_POST['item'])) {
                    update_stock_log(session('admin_id'), $_POST['store_count'], array('goods_id' => $goods_id, 'goods_name' => $_POST['goods_name'], 'store_id' => STORE_ID));
                }
            }
            $Goods->afterSave($goods_id, STORE_ID);
            $GoodsLogic->saveGoodsAttr($goods_id, $type_id, STORE_ID); // 处理商品 属性
            $return_arr = array(
                'status' => 1,
                'msg' => '操作成功',
                'data' => array('url' => U('Goods/goodsList')),
            );
            //重定向, 调整之前URL是设置参数获取方式
            session("is_back", 1);
            $this->ajaxReturn($return_arr); 
        }
        
        $goodsInfo = M('Goods')->where('goods_id', I('get.goods_id/d', 0))->find();
        if ($goodsInfo) {
            $goodsInfo['ext_cat_list'] = $Goods->getGoodsExtendCat($goods_id);
        }
        $store = M('store')->where(array('store_id' => STORE_ID))->find();
         $store['store_consult'] = unserialize($store['store_consult']);
        if ($store['bind_all_gc'] == 1) {
            $cat_list = M('goods_category')->where(['parent_id' => 0,'is_drug'=>1])->select();//自营店已绑定所有分类
        } else {
            //自营店已绑定所有分类
            $cat_list = Db::name('goods_category')->where(['parent_id' => 0,'is_drug'=>1])->where('id', 'IN', function ($query) {
                $query->name('store_bind_class')->where('store_id', STORE_ID)->where('state', 1)->field('class_1');
            })->select();
        }
        $store_goods_class_list = M('store_goods_class')->where(['parent_id' => 0, 'store_id' => STORE_ID])->select(); //店铺内部分类
        $brandList = $GoodsLogic->getSortBrands();
        $map = array();
        $map['status'] = 1;
        if ($goodsInfo) {
            $map['id'] = ($goodsInfo['sales_model'] == 2) ? array('gt', 1) : 1;
        }
        $countryList = M('country')->where($map)->select();
        $goodsType = M("GoodsType")->select();
        $suppliersList = M("suppliers")->select();
        $plugin_shipping = M('plugin')->where(array('type' => array('eq', 'shipping')))->select();//插件物流
        $shipping_area = D('Shipping_area')->getShippingArea(STORE_ID);//配送区域
        $goods_shipping_area_ids = explode(',', $goodsInfo['shipping_area_ids']);
        $this->assign("store",$store);
        $this->assign('goods_shipping_area_ids', $goods_shipping_area_ids);
        $this->assign('shipping_area', $shipping_area);
        $this->assign('plugin_shipping', $plugin_shipping);
        $this->assign('cat_list', $cat_list);
        $this->assign('store_goods_class_list', $store_goods_class_list);
        $this->assign('brandList', $brandList);
        $this->assign('countryList', $countryList);
        $this->assign('goodsType', $goodsType);
        $this->assign('suppliersList', $suppliersList);
        $this->assign('goodsInfo', $goodsInfo);  // 商品详情
        $goodsImages = M("GoodsImages")->where('goods_id', I('get.goods_id/d', 0))->select();
        $this->assign('goodsImages', $goodsImages);  // 商品相册
        $image_count = count($goodsImages);
        $this->assign('image_count', $image_count);
        $this->initEditor(); // 编辑器
        return $this->fetch('_drug');
    }

    public function add_drug_consult()
    {
        $sup = $sub_sup = [];
        list($sup['name'] , $sup['type'] ,$sup['account']) = array( I('name'),'tel',I('account'));
        $old_consult = M('store')->where('store_id',STORE_ID)->getField("store_consult");
         empty($old_consult) ? $sub_sup = [] : $sub_sup = unserialize($old_consult);
         array_push($sub_sup, $sup);
         M('store')->where('store_id',STORE_ID)->setField("store_consult",serialize($sub_sup));
        $this->ajaxReturn(array('status' => 1, 'msg' => '添加成功！'));
    }

    public function get_drug_consult()
    {
        $consult = M('store')->where('store_id',STORE_ID)->getField("store_consult");
        $consult = unserialize($consult);
        $str = "<option value='0'>选择药品咨询人</option>";
        foreach($consult as $key => $v){
            $str.="<option value='".$v['account']."' >".$v['name']."</option>";
        }
        $this->ajaxReturn(array('status' => 1, 'msg' => '添加成功！','str'=>$str)); 
    }



    
    public function ajax_del_img()
    {
        $img_id = I('img_id');
        M('goodsImages')->where('img_id', $img_id)->delete();
        $this->ajaxReturn(array('status' => 1, 'msg' => '删除成功！'));
    }
    
    /**
     * 更改指定表的指定字段
     */
    public function updateField()
    {
        $primary = array(
            'goods' => 'goods_id',
            'goods_attribute' => 'attr_id',
            'ad' => 'ad_id',
        );
        $id = I('id/d', 0);
        $field = I('field');
        $value = I('value');
        Db::name($_POST['table'])->where($primary[$_POST['table']], $id)->where('store_id', STORE_ID)->save(array($field => $value));
        $return_arr = array(
            'status' => 1,
            'msg' => '操作成功',
            'data' => array('url' => U('Goods/goodsAttributeList')),
        );
        $this->ajaxReturn($return_arr);
    }
    
    /**
     * 动态获取商品属性输入框 根据不同的数据返回不同的输入框类型
     */
    public function ajaxGetAttrInput()
    {
        $cat_id3 = I('cat_id3/d', 0);
        $goods_id = I('goods_id/d', 0);
        empty($cat_id3) && exit('');
        $type_id = M('goods_category')->where("id", $cat_id3)->getField('type_id'); // 找到这个分类对应的type_id
        empty($type_id) && exit('');
        $GoodsLogic = new GoodsLogic();
        $str = $GoodsLogic->getAttrInput($goods_id, $type_id);
        exit($str);
    }
    
    /**
     * 删除商品
     */
    public function delGoods()
    {
        $goods_id = I('id/d');
        $error = '';
        
        // 判断此商品是否有订单
        $c1 = M('OrderGoods')->where("goods_id", $goods_id)->count('1');
        $c1 && $error .= '此商品有订单,不得删除! <br/>';
        
        // 商品团购
        $c1 = M('group_buy')->where("goods_id", goods_id)->count('1');
        $c1 && $error .= '此商品有团购,不得删除! <br/>';
        
        // 商品退货记录
        $c1 = M('return_goods')->where("goods_id", $goods_id)->count('1');
        $c1 && $error .= '此商品有退货记录,不得删除! <br/>';
        
        if ($error) {
            $return_arr = array('status' => -1, 'msg' => $error, 'data' => '',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
            $this->ajaxReturn($return_arr);
        }
        
        // 删除此商品        
        $result = M("Goods")->where(['goods_id' => $goods_id, 'store_id' => STORE_ID])->delete();  //商品表
        if ($result) {
            M("cart")->where('goods_id', $goods_id)->delete();  // 购物车
            M("comment")->where('goods_id', $goods_id)->delete();  //商品评论
            M("goods_consult")->where('goods_id', $goods_id)->delete();  //商品咨询
            M("goods_images")->where('goods_id', $goods_id)->delete();  //商品相册
            M("spec_goods_price")->where('goods_id', $goods_id)->delete();  //商品规格
            M("spec_image")->where('goods_id', $goods_id)->delete();  //商品规格图片
            M("goods_attr")->where('goods_id', $goods_id)->delete();  //商品属性
            M("goods_collect")->where('goods_id', $goods_id)->delete();  //商品收藏
        }
        $return_arr = array('status' => 1, 'msg' => '操作成功', 'data' => '',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
        $this->ajaxReturn($return_arr);
    }
    
    /**
     * ajax 获取 品牌列表
     */
    public function getBrandByCat()
    {
        $db_prefix = C('database.prefix');
        $type_id = I('type_id/d');
        if ($type_id) {
//            $list = M('brand')->join("left join {$db_prefix}brand_type on {$db_prefix}brand.id = {$db_prefix}brand_type.brand_id and  type_id = $type_id")->order('id')->select();
            $list = Db::name('brand')->alias('b')->join('__BRAND_TYPE__ t', 't.brand_id = b.id', 'LEFT')->where(['t.type_id' => $type_id])->order('b.id')->select();
        } else {
            $list = M('brand')->order('id')->select();
        }
//        $goods_category_list = M('goods_category')->where("id in(select cat_id1 from {$db_prefix}brand) ")->getField("id,name,parent_id");
        $goods_category_list = Db::name('goods_category')
            ->where('id', 'IN', function ($query) {
                $query->name('brand')->where('')->field('cat_id1');
            })
            ->getField("id,name,parent_id");
        $goods_category_list[0] = array('id' => 0, 'name' => '默认');
        asort($goods_category_list);
        $this->assign('goods_category_list', $goods_category_list);
        $this->assign('type_id', $type_id);
        $this->assign('list', $list);
        return $this->fetch();
    }
    
    
    /**
     * ajax 获取 规格列表
     */
    public function getSpecByCat()
    {
        
        $db_prefix = C('database.prefix');
        $type_id = I('type_id/d');
        if ($type_id) {
//            $list = M('spec')->join("left join {$db_prefix}spec_type on {$db_prefix}spec.id = {$db_prefix}spec_type.spec_id  and  type_id = $type_id")->order('id')->select();
            $list = Db::name('spec')->alias('s')->join('__SPEC_TYPE__ t', 't.spec_id = s.id', 'LEFT')->where(['t.type_id' => $type_id])->order('s.id')->select();
        } else {
            $list = M('spec')->order('id')->select();
        }
//        $goods_category_list = M('goods_category')->where("id in(select cat_id1 from {$db_prefix}spec) ")->getField("id,name,parent_id");
        $goods_category_list = Db::name('goods_category')
            ->where('id', 'IN', function ($query) {
                $query->name('spec')->where('')->field('cat_id1');
            })
            ->getField("id,name,parent_id");
        $goods_category_list[0] = array('id' => 0, 'name' => '默认');
        asort($goods_category_list);
        $this->assign('goods_category_list', $goods_category_list);
        $this->assign('type_id', $type_id);
        $this->assign('list', $list);
        return $this->fetch();
    }
    
    /**
     * 初始化编辑器链接
     * 本编辑器参考 地址 http://fex.baidu.com/ueditor/
     */
    private function initEditor()
    {
        $this->assign("URL_upload", U('Admin/Ueditor/imageUp', array('savepath' => 'goods'))); // 图片上传目录
        $this->assign("URL_imageUp", U('Admin/Ueditor/imageUp', array('savepath' => 'article'))); //  不知道啥图片
        $this->assign("URL_fileUp", U('Admin/Ueditor/fileUp', array('savepath' => 'article'))); // 文件上传s
        $this->assign("URL_scrawlUp", U('Admin/Ueditor/scrawlUp', array('savepath' => 'article')));  //  图片流
        $this->assign("URL_getRemoteImage", U('Admin/Ueditor/getRemoteImage', array('savepath' => 'article'))); // 远程图片管理
        $this->assign("URL_imageManager", U('Admin/Ueditor/imageManager', array('savepath' => 'article'))); // 图片管理
        $this->assign("URL_getMovie", U('Admin/Ueditor/getMovie', array('savepath' => 'article'))); // 视频上传
        $this->assign("URL_Home", "");
    }
    
    /**
     * 动态获取商品规格选择框 根据不同的数据返回不同的选择框
     */
    public function ajaxGetSpecSelect()
    {
        $goods_id = I('goods_id/d', 0);
        $cat_id3 = I('cat_id3/d', 0);
        empty($cat_id3) && exit('');
        $goods_id = $goods_id ? $goods_id : 0;
        
        $type_id = M('goods_category')->where("id", $cat_id3)->getField('type_id'); // 找到这个分类对应的type_id
        empty($type_id) && exit('');
        $spec_id_arr = M('spec_type')->where("type_id", $type_id)->getField('spec_id', true); // 找出这个类型的 所有 规格id
        empty($spec_id_arr) && exit('');
        
        $specList = D('Spec')->where("id", "in", implode(',', $spec_id_arr))->order('`order` desc')->select(); // 找出这个类型的所有规格
        if ($specList) {
            foreach ($specList as $k => $v) {
                $specList[$k]['spec_item'] = D('SpecItem')->where(['store_id' => STORE_ID, 'spec_id' => $v['id']])->getField('id,item'); // 获取规格项
            }
        }
        
        $items_id = M('SpecGoodsPrice')->where("goods_id", $goods_id)->getField("GROUP_CONCAT(`key` SEPARATOR '_') AS items_id");
        $items_ids = explode('_', $items_id);
        
        // 获取商品规格图片                
        if ($goods_id) {
            $specImageList = M('SpecImage')->where("goods_id", $goods_id)->getField('spec_image_id,src');
        }
        $this->assign('specImageList', $specImageList);
        
        $this->assign('items_ids', $items_ids);
        $this->assign('specList', $specList);
        return $this->fetch('ajax_spec_select');
    }
    
    /**
     * 动态获取商品规格输入框 根据不同的数据返回不同的输入框
     */
    public function ajaxGetSpecInput()
    {
        $GoodsLogic = new GoodsLogic();
        $goods_id = I('get.goods_id/d', 0);
        $spec_arr = I('spec_arr/a', []);
        $str = $GoodsLogic->getSpecInput($goods_id, $spec_arr, STORE_ID);
        exit($str);
    }
    
    /**
     * 商家发布商品时添加的规格
     */
    public function addSpecItem()
    {
        $spec_id = I('spec_id/d', 0); // 规格id
        $spec_item = I('spec_item', '', 'trim');// 规格项
        
        $c = M('spec_item')->where(['store_id' => STORE_ID, 'item' => $spec_item, 'spec_id' => $spec_id])->count();
        if ($c > 0) {
            $return_arr = array(
                'status' => -1,
                'msg' => '规格已经存在',
                'data' => '',
            );
            exit(json_encode($return_arr));
        }
        $data = array(
            'spec_id' => $spec_id,
            'item' => $spec_item,
            'store_id' => STORE_ID,
        );
        M('spec_item')->add($data);
        
        $return_arr = array(
            'status' => 1,
            'msg' => '添加成功!',
            'data' => '',
        );
        exit(json_encode($return_arr));
    }
    
    /**
     * 商家发布商品时删除的规格
     */
    public function delSpecItem()
    {
        $spec_id = I('spec_id/d', 0); // 规格id
        $spec_item = I('spec_item', '', 'trim');// 规格项
        $spec_item_id = I('spec_item_id/d', 0); //规格项 id
        
        if (!empty($spec_item_id)) {
            $id = $spec_item_id;
        } else {
            $id = M('spec_item')->where(['store_id' => STORE_ID, 'item' => $spec_item, 'spec_id' => $spec_id])->getField('id');
        }
        
        if (empty($id)) {
            $return_arr = array('status' => -1, 'msg' => '规格不存在');
            exit(json_encode($return_arr));
        }
        $c = M("SpecGoodsPrice")->where("store_id", STORE_ID)->where(" `key` REGEXP :id1 OR `key` REGEXP :id2 OR `key` REGEXP :id3 or `key` = :id4")->bind(['id1' => '^' . $id . '_', 'id2' => '_' . $id . '_', 'id3' => '_' . $id . '$', 'id4' => $id])->count(); // 其他商品用到这个规格不得删除
        if ($c) {
            $return_arr = array('status' => -1, 'msg' => '此规格其他商品使用中,不得删除');
            exit(json_encode($return_arr));
        }
        M('spec_item')->where(['id' => $id, 'store_id' => STORE_ID])->delete(); // 删除规格项
        M('spec_image')->where(['spec_image_id' => $id, 'store_id' => STORE_ID])->delete(); // 删除规格图片选项
        $return_arr = array('status' => 1, 'msg' => '删除成功!');
        exit(json_encode($return_arr));
    }
    
    /**
     * 商品规格列表
     */
    public function specList()
    {
        $cat_list = M('goods_category')->where("parent_id = 0")->getField('id,name,parent_id'); // 已经改成联动菜单                
        $this->assign('cat_list', $cat_list);
        return $this->fetch();
    }
    
    /**
     *  商品规格列表
     */
    public function ajaxSpecList()
    {
        //ob_start('ob_gzhandler'); // 页面压缩输出
        $cat_id3 = I('cat_id3/d', 0);
        $spec_id = I('spec_id/d', 0);
        $type_id = M('goods_category')->where("id", $cat_id3)->getField('type_id'); // 获取这个分类对应的类型
        if (empty($cat_id3) || empty($type_id)) exit('');
        
        $spec_id_arr = M('spec_type')->where("type_id", $type_id)->getField('spec_id', true); // 获取这个类型所拥有的规格
        if (empty($spec_id_arr)) exit('');
        
        $spec_id = $spec_id ? $spec_id : $spec_id_arr[0]; //没有传值则使用第一个
        
        $specList = M('spec')->where("id", "in", implode(',', $spec_id_arr))->getField('id,name,cat_id1,cat_id2,cat_id3');
        $specItemList = M('spec_item')->where(['store_id' => STORE_ID, 'spec_id' => $spec_id])->order('id')->select(); // 获取这个类型所拥有的规格
        //I('cat_id1')   && $where = "$where and cat_id1 = ".I('cat_id1') ;                       
        $this->assign('spec_id', $spec_id);
        $this->assign('specList', $specList);
        $this->assign('specItemList', $specItemList);
        return $this->fetch();
    }
    
    /**
     *  批量添加修改规格
     */
    public function batchAddSpecItem()
    {
        $spec_id = I('spec_id/d', 0);
        $item = I('item/a');
        $spec_item = M('spec_item')->where(['store_id' => STORE_ID, 'spec_id' => $spec_id])->getField('id,item');
        foreach ($item as $k => $v) {
            $v = trim($v);
            if (empty($v)) continue; // 值不存在 则跳过不处理
            // 如果spec_id 存在 并且 值不相等 说明值被改动过
            if (array_key_exists($k, $spec_item) && $v != $spec_item[$k]) {
                M('spec_item')->where(['id' => $k, 'store_id' => STORE_ID])->save(array('item' => $v));
                // 如果这个key不存在 并且规格项也不存在 说明 需要插入
            } elseif (!array_key_exists($k, $spec_item) && !in_array($v, $spec_item)) {
                M('spec_item')->add(array('spec_id' => $spec_id, 'item' => $v, 'store_id' => STORE_ID));
            }
        }
        $this->success('操作成功!');
    }
    
    /**
     * 品牌列表
     */
    public function brandList()
    {
        $keyword = I('keyword');
        $brand_model = Db::name('brand');
        $brand_where['store_id'] = STORE_ID;
        if ($keyword) {
            $brand_where['name'] = ['like', '%' . $keyword . '%'];
        }
        $count = $brand_model->where($brand_where)->count();
        $Page = new Page($count, 16);
        $brandList = $brand_model->where($brand_where)->order("`sort` asc")->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $show = $Page->show();
        $cat_list = M('goods_category')->where("parent_id = 0")->getField('id,name'); // 已经改成联动菜单
        $this->assign('cat_list', $cat_list);
        $this->assign('show', $show);
        $this->assign('brandList', $brandList);
        return $this->fetch('brandList');
    }
    
    /**
     * 添加修改编辑  商品品牌
     */
    public function addEditBrand()
    {
        $id = I('id/d', 0);
        if (IS_POST) {
            $files = request()->file();
            $data = input('post.');
            
            if (!empty($files)) {
                $info = $this->up_img('brand', null, $logo);
                if ($info == false) $this->error("您上传的图片过大！", U('Store/store_setting'));
            }
            if (!empty($info)) {
                foreach ($info as $k => $v) {
                    $data[$k] = $v;
                }
            }
            if ($id) {
                Db::name('brand')->update($data);
            } else {
                $data['store_id'] = STORE_ID;
                M("Brand")->insert($data);
            }
            
            $this->success("操作成功!!!", U('Seller/Goods/brandList', array('p' => input('p'))));
            exit;
        }
        $cat_list = M('goods_category')->where("parent_id = 0")->select(); // 已经改成联动菜单
        $this->assign('cat_list', $cat_list);
        $brand = Db::name('brand')->where(array('id' => $id, 'store_id' => STORE_ID))->find();
        $this->assign('brand', $brand);
        return $this->fetch('_brand');
    }
    
    /**
     * 删除品牌
     */
    public function delBrand()
    {
        $model = M("Brand");
        $id = I('id/d');
        $model->where(['id' => $id, 'store_id' => STORE_ID])->delete();
        $return_arr = array('status' => 1, 'msg' => '操作成功', 'data' => '',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
        $this->ajaxReturn($return_arr);
    }
    
    public function brand_save()
    {
        $data = I('post.');
        if ($data['act'] == 'del') {
            $goods_count = M('Goods')->where("brand_id", $data['id'])->count('1');
            if ($goods_count) respose(array('status' => -1, 'msg' => '此品牌有商品在用不得删除!'));
            $r = M('brand')->where('id', $data['id'])->delete();
            if ($r) {
                respose(array('status' => 1));
            } else {
                respose(array('status' => -1, 'msg' => '操作失败'));
            }
        } else {
            if (empty($data['id'])) {
                $data['store_id'] = STORE_ID;
                $r = M('brand')->add($data);
            } else {
                $r = M('brand')->where('id', $data['id'])->save($data);
            }
        }
        if ($r) {
            $this->success("操作成功", U('Store/brand_list'));
        } else {
            $this->error("操作失败", U('Store/brand_list'));
        }
    }
    
    /**
     * 删除商品相册图
     */
    public function del_goods_images()
    {
        $path = I('filename', '');
        $goods_images = M('goods_images')->where(array('image_url' => $path))->select();
        $goods_images = M('goods_images')->where(array('image_url' => $path))->select();
        foreach ($goods_images as $key => $val) {
            $goods = M('goods')->where(array('goods_id' => $goods_images[$key]['goods_id']))->find();
            if ($goods['store_id'] == STORE_ID) {
                M('goods_images')->where(array('img_id' => $goods_images[$key]['img_id']))->delete();
            }
        }
    }
    
    /**
     * 重新申请商品审核
     */
    public function goodsUpLine()
    {
        $goods_ids = input('goods_ids');
        $res = Db::name('goods')->where('goods_id', 'in', $goods_ids)->where('store_id', STORE_ID)->update(['is_on_sale' => 0, 'goods_state' => 0]);
        if ($res !== false) {
            $this->success('操作成功');
        } else {
            $this->success('操作失败');
        }
        
    }
}
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

namespace app\admin\controller;

use app\admin\logic\GoodsLogic;
use think\AjaxPage;
use think\Page;
use think\Db;

class Goods extends Base
{
    
    /**
     *  商品分类列表
     */
    public function categoryList()
    {
        $GoodsLogic = new GoodsLogic();
        $is_drug = I('is_drug',0);
        if($is_drug == 1){
            $cat_list = $GoodsLogic->drug_cat_list();
        }else{
              $cat_list = $GoodsLogic->goods_cat_list();
        }
      
        $goods_type = M('goods_type')->getField('id,name');
        $this->assign('goods_type', $goods_type);
        $this->assign('cat_list', $cat_list);
        $this->assign('is_drug',$is_drug);
         $is_drug == 0 ? $orderword = "商品" : $orderword = "药品";
        $this->assign("orderword",$orderword);
        return $this->fetch();
    }
    
    /**
     * 添加修改商品分类
     * 手动拷贝分类正则 ([\u4e00-\u9fa5/\w]+)  ('393','$1'),
     * select * from tp_goods_category where id = 393
     * select * from tp_goods_category where parent_id = 393
     * update tp_goods_category  set parent_id_path = concat_ws('_','0_76_393',id),`level` = 3 where parent_id = 393
     * insert into `tp_goods_category` (`parent_id`,`name`) values
     * ('393','时尚饰品'),
     */
    public function addEditCategory()
    {
        $GoodsLogic = new GoodsLogic();
        $db_prefix = C('database.prefix');
        $is_drug = I("is_drug",0);
        if (IS_GET) {
            $goods_category_info = D('GoodsCategory')->where('id=' . I('GET.id', 0))->find();
            if ($goods_category_info['level'] == 3) {
                $goods_category_info['custom_duty'] = $goods_category_info['custom_duty'] / 100;
            }
            if ($goods_category_info['sales_model_id']) {
                $goods_category_info['sales_model_id'] = explode(',', $goods_category_info['sales_model_id']);
            }
            $level_cat = $GoodsLogic->find_parent_cat($goods_category_info['id']); // 获取分类默认选中的下拉框
            $cat_list = M('goods_category')->where("parent_id = 0 and is_drug=".$is_drug)->select(); // 已经改成联动菜单
            $this->assign('level_cat', $level_cat);
            $this->assign('cat_list', $cat_list);
            
            $this->assign('goods_category_info', $goods_category_info);
            $goods_category_list = M('goods_category')->where("id in(select cat_id1 from {$db_prefix}goods_type) and is_drug=".$is_drug)->getField("id,name,parent_id");
            $goods_category_list[0] = array('id' => 0, 'name' => '默认');
            asort($goods_category_list);
            $this->assign('goods_category_list', $goods_category_list);
            $goods_type_list = M('goods_type')->select(); // 所有类型id
            $this->assign('goods_type_list', $goods_type_list);
            $salesModeList = C('sales_model_title');
            $this->assign("is_drug",$is_drug);
            $is_drug == 0 ? $orderword = "商品" : $orderword = "药品";
            $this->assign("orderword",$orderword);
            $this->assign('salesModeList', $salesModeList);
            return $this->fetch('_category');
            exit;
        }
        $delimg = null;
        $GoodsCategory = D('GoodsCategory'); //
        
        $type = $_POST['id'] > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
        //ajax提交验证
        if ($_GET['is_ajax'] == 1) {
            // 数据验证
            $validate = \think\Loader::validate('GoodsCategory');
            if (!$validate->batch()->check(input('post.'))) {
                $error = $validate->getError();
                $error_msg = array_values($error);
                //  编辑
                $return_arr = array(
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'data' => $error,
                );
                $this->ajaxReturn($return_arr);
            } else {
                $GoodsCategory->data(input('post.'), true); // 收集数据
                $GoodsCategory->parent_id = $_POST['parent_id_1'];
                $_POST['parent_id_2'] && ($GoodsCategory->parent_id = $_POST['parent_id_2']);
                $_POST['is_nav'] && $GoodsCategory->is_nav = $_POST['is_nav'];
                $_POST['is_index'] && $GoodsCategory->is_index = $_POST['is_index'];
                $_POST['custom_duty'] && $GoodsCategory->custom_duty = trim($_POST['custom_duty']) * 100;
                if ($GoodsCategory->id > 0 && $GoodsCategory->parent_id == $GoodsCategory->id) {
                    //  编辑
                    $return_arr = array(
                        'status' => -1,
                        'msg' => '上级分类不能为自己',
                        'data' => '',
                    );
                    $this->ajaxReturn($return_arr);
                }
                // 平台抽成比例
                if ($GoodsCategory->commission > 100) {
                    //  编辑
                    $return_arr = array(
                        'status' => -1,
                        'msg' => '抽成比例不得超过100%',
                        'data' => '',
                    );
                    $this->ajaxReturn($return_arr);
                }
				$alias=$_POST['alias'];
                $alias_id=M('goods_category')->where('alias',$alias)->getField('id');
                if(($_POST['id'] > 0 && $alias_id != $_POST['id'] && $alias_id > 0 ) || ($alias_id > 0 && empty($_POST['id'] ))){
                        $return_arr = array(
                        'status' => -1,
                        'msg' => '分类别名已存在！',
                        'data' => '',
                    );
                    $this->ajaxReturn($return_arr);
                }
                //编辑判断
                if ($type == 2) {
                    $delimg = M('goods_category')->where(array('id' => $_POST['id']))->getField('image', false);
                    $delimgArr = explode('/', $delimg);
                    if ($delimgArr[1] == 'public') $delimg = null;
                    $children_where = array(
                        'parent_id_path' => array('like', '%_' . $_POST['id'] . "_%")
                    );
                    $cul_level = M('goods_category')->where(array('id' => $_POST['id']))->getField('level', false);
                    if ($cul_level == 1) {
                        if (!isset($GoodsCategory->sales_model_id) || empty($GoodsCategory->sales_model_id)) {
                            $return_arr = array(
                                'status' => -1,
                                'msg' => '请选择销售模式',
                                'data' => array(
                                    'sales_model_id' => '请选择销售模式',
                                ),
                            );
                            $this->ajaxReturn($return_arr);
                        } else {
                            $GoodsCategory->sales_model_id = implode(',', $GoodsCategory->sales_model_id);
                        }
                    } else {
                        $GoodsCategory->sales_model_id = 0;
                    }
                    $children = M('goods_category')->where($children_where)->max('level');
                    if ($_POST['parent_id_1']) {
                        if ($children - $cul_level > 1) {
                            $return_arr = array(
                                'status' => -1,
                                'msg' => '商品分类最多为三级',
                                'data' => '',
                            );
                            $this->ajaxReturn($return_arr);
                        }
                    }
                    if ($_POST['parent_id_2']) {
                        if ($children - $cul_level > 0) {
                            $return_arr = array(
                                'status' => -1,
                                'msg' => '商品分类最多为三级',
                                'data' => '',
                            );
                            $this->ajaxReturn($return_arr);
                        }
                    }
                }
                //保存图片
                if (!empty(request()->file())) {
                    $info = $this->up_img('ad', null, $delimg);
                    if (!$info) {
                        $return_arr = array(
                            'status' => -1,
                            'msg' => '你上传的图片过大！',
                            'data' => '',
                        );
                        $this->ajaxReturn($return_arr);
                    }
                    if (!empty($info)) {
                        foreach ($info as $k => $v) {
                            $GoodsCategory->$k = $v;
                        }
                    }
                }
                if ($type == 2)
                    $GoodsCategory->isUpdate(true)->save(); // 写入数据到数据库 // 写入数据到数据库
                else {
                    $GoodsCategory->save(); // 写入数据到数据库
                    $_POST['id'] = $GoodsCategory->getLastInsID();
                }
                
                $GoodsLogic->refresh_cat($_POST['id']);
                // 修改它下面的所有分类的 type_id 等于它的type_id
                $category = M('goods_category')->where("id = {$_POST['id']}")->find();
                M('goods_category')->where("parent_id_path like '{$category['parent_id_path']}\_%'")->save(array('type_id' => $_POST['type_id'], 'commission' => $_POST['commission']));
                $go_url = '';
				($is_drug == 1) ? $go_url = U('Admin/Goods/categoryList')."?is_drug=1" : $go_url = U('Admin/Goods/categoryList');
                $return_arr = array(
                    'status' => 1,
                    'msg' => '操作成功',
                    'data' => array('url' => $go_url),
                );
                $this->ajaxReturn($return_arr);
            }
        }
        
    }
    
    /**
     * 获取商品分类 的帅选规格 复选框
     */
    public function ajaxGetSpecList()
    {
        $GoodsLogic = new GoodsLogic();
        $_REQUEST['category_id'] = $_REQUEST['category_id'] ? $_REQUEST['category_id'] : 0;
        $filter_spec = M('GoodsCategory')->where("id = " . $_REQUEST['category_id'])->getField('filter_spec');
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
        $filter_attr = M('GoodsCategory')->where("id = " . $_REQUEST['category_id'])->getField('filter_attr');
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
        $id = I('id/d');
        if (empty($id)) {
            $this->error('非法操作');
        }
        $count = Db::name('goods_category')->where("parent_id", $id)->count("id");
        if ($count > 0) {
            $this->error('该分类下还有分类不得删除!');
        }
        // 判断是否存在商品
        $goods_count = Db::name('goods')->where(['cat_id1|cat_id2|cat_id3' => $id])->count('goods_id');
        if ($goods_count > 0) {
            $this->error('该分类下有商品不得删除!');
        }
        // 删除分类
        $del = Db::name('goods_category')->where("id", $id)->delete();
        if ($del !== false) {
            $this->success("删除成功!!!", U('Admin/Goods/categoryList'));
        } else {
            $this->error("删除失败!!!", U('Admin/Goods/categoryList'));
        }
    }
    
    /**
     *  商品列表
     */
    public function goodsList()
    {
        $GoodsLogic = new GoodsLogic();
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();
        $this->assign('categoryList', $categoryList);
        $this->assign('brandList', $brandList);
        return $this->fetch();
    }

    /**
     *  商品列表
     */
    public function drugList()
    {
        $GoodsLogic = new GoodsLogic();
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();
        $this->assign('categoryList', $categoryList);
        $this->assign('brandList', $brandList);
        return $this->fetch();
    }
    
    /**
     *  商品列表
     */
    public function ajaxGoodsList()
    {
        $where = ' 1 = 1 '; // 搜索条件     
        $store_name = I('shop_word');
        if (!empty($store_name)) {
            $storeArr = M('store')->where("store_name like '%" . $store_name . "%'")->getField('store_id', true);
            if (!empty($storeArr)) {
                $where = "$where and store_id in (" . implode(',', $storeArr) . ")";
            }
        }
        $where = "$where and is_drug = " . I('is_drug',0);
        I('is_own_shop') && $where = "$where and is_own_shop = " . I('is_own_shop');
        I('intro') && $where = "$where and " . I('intro') . " = 1";
        I('brand_id') && $where = "$where and brand_id = " . I('brand_id');
        (I('goods_state') !== '') && $where = "$where and goods_state = " . I('goods_state');
        (I('is_on_sale') !== '') && $where = "$where and is_on_sale = " . I('is_on_sale');
        $cat_id = I('cat_id');
        // 关键词搜索               
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if ($key_word) {
            $where = "$where and (goods_name like '%$key_word%' or goods_sn like '%$key_word%')";
        }
        
        if ($cat_id > 0) {
            $where .= " and (cat_id1 = $cat_id or cat_id2 = $cat_id or cat_id3 = $cat_id ) "; // 初始化搜索条件
        }
		$where .=" and is_on_sale = 1";
		
        $model = M('Goods');
        $count = $model->where($where)->count();
        $Page = new AjaxPage($count, 250);
        $show = $Page->show();
        $order_str = "`{$_POST['orderby1']}` {$_POST['orderby2']}";
        $goodsList = $model->where($where)->order($order_str)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $store_id_list = get_arr_column($goodsList, 'store_id');
        if (!empty($store_id_list)) {
            $store_list = M('store')->where("store_id", "in", implode(',', $store_id_list))->getField('store_id,store_name');
        }
        $this->assign('store_list', $store_list);
        $catList = M('goods_category')->select();
        $catList = convert_arr_key($catList, 'id');
        $store_type = array('平台联营', '加盟店', '平台自营');
        $this->assign('store_type', $store_type);
        $goods_state = C('goods_state');
        $this->assign('catList', $catList);
        $this->assign('goodsList', $goodsList);
        $this->assign('goods_state', $goods_state);
        $this->assign("drug_attr",C('drug_attr'));
        $this->assign('page', $show);// 赋值分页输出
        return $this->fetch();
    }
    
    public function stock_list()
    {
        $model = M('stock_log');
        $map = array();
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
            $this->assign('start_time', $gap[0]);
            $this->assign('end_time', $gap[1]);
            $this->assign('ctime', $gap[0] . ' - ' . $gap[1]);
            $map['ctime'] = array(array('gt', strtotime($gap[0])), array('lt', strtotime($gap[1])));
        }
        $count = $model->where($map)->count();
        $Page = new Page($count, 20);
        $show = $Page->show();
        $this->assign('pager', $Page);
        $this->assign('page', $show);// 赋值分页输出
        $stock_list = $model->where($map)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('stock_list', $stock_list);
        if ($stock_list) {
            $uids = get_arr_column($stock_list, 'store_id');
            $store = M('store')->where("store_id in (" . implode(',', $uids) . ")")->getField('store_id,store_name');
            $this->assign('store', $store);
        }
        return $this->fetch();
    }
    
    /**
     * 添加修改商品
     */
    public function addEditGoods()
    {
        $GoodsLogic = new GoodsLogic();
        $Goods = D('Admin/Goods'); //
        $goods_id = I('goods_id', 0);
        $type = $goods_id > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
        //
        // if ($goods_id > 0) {
        //     $c = M('goods')->where("goods_id = $goods_id and store_id = 1")->count();
        //     if ($c == 0)
        //         $this->error("非法操作", U('Goods/goodsList'));
        // }
        
        //
        //ajax提交验证
        if (($_GET['is_ajax'] == 1) && IS_POST) {
            C('TOKEN_ON', false);
            if (!$Goods->create(NULL, $type))// 根据表单提交的POST数据创建数据对象
            {
                //  编辑
                $error = $Goods->getError();
                $error_msg = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'data' => $error,
                );
                $this->ajaxReturn(json_encode($return_arr));
            } else {
                $Goods->on_time = time(); // 上架时间
                $cat_id3 = I('cat_id3', 0);
                $_POST['extend_cat_id_2'] && ($Goods->extend_cat_id = I('extend_cat_id_2'));
                $_POST['extend_cat_id_3'] && ($Goods->extend_cat_id = I('extend_cat_id_3'));
                $Goods->shipping_area_ids = implode(',', $_POST['shipping_area_ids']);
                $Goods->shipping_area_ids = $Goods->shipping_area_ids ? $Goods->shipping_area_ids : '';
                
                $type_id = M('goods_category')->where("id = $cat_id3")->getField('type_id'); // 找到这个分类对应的type_id
                $store_goods_examine = M('store')->where(array('store_id' => 1))->getField('goods_examine');
                $Goods->goods_type = $type_id ? $type_id : 0;
                $Goods->store_id = 1; // 店家id
                if ($store_goods_examine) {
                    $Goods->goods_state = 0; // 待审核
                } else {
                    $Goods->goods_state = 1; // 出售中
                }
                
                if ($Goods->distribut > ($Goods->shop_price / 2))
                    $this->ajaxReturn(json_encode(array('status' => -1, 'msg' => '分销的分成金额不得超过商品金额的50%', 'data' => '')));
                
                if ($type == 2) {
                    $goods = M('goods')->where(array('goods_id' => $goods_id, 'store_id' => 1))->find();
                    if ($goods) {
                        // 修改商品后购物车的商品价格也修改一下
                        M('cart')->where("goods_id = $goods_id and spec_key = ''")->save(array(
                            'market_price' => $_POST['market_price'], //市场价
                            'goods_price' => $_POST['shop_price'], // 本店价
                            'member_goods_price' => $_POST['shop_price'], // 会员折扣价
                        ));
                        if (empty($_POST['item']) && $_POST['store_count'] != $goods['store_count']) {
                            $_POST['store_count'] = $_POST['store_count'] - $goods['store_count'];
                            update_stock_log(session('admin_id'), $_POST['store_count'], array('goods_id' => $goods_id, 'goods_name' => $_POST['goods_name'], 'store_id' => 1));
                        } else {
                            unset($Goods->store_count);
                        }
                        $Goods->save(); // 编辑数据到数据库
                    } else {
                        $this->ajaxReturn(array('status' => -1, 'msg' => '非法操作'), 'JSON');
                    }
                } else {
                    $goods_id = $Goods->add(); // 新增数据到数据库
                    //商品进出库记录日志
                    if (empty($_POST['item'])) {
                        update_stock_log(session('admin_id'), $_POST['store_count'], array('goods_id' => $goods_id, 'goods_name' => $_POST['goods_name'], 'store_id' => 1));
                    }
                }
                $Goods->afterSave($goods_id, 1);
                $GoodsLogic->saveGoodsAttr($goods_id, $type_id, 1); // 处理商品 属性
                //删除商品html缓存
                unlink("./runtime/html/home_goods_goodsinfo_{$goods_id}.html");
                // 删除缩略图
                delFile("./public/upload/goods/thumb/$goods_id");
                $return_arr = array(
                    'status' => 1,
                    'msg' => '操作成功',
                    'data' => array('url' => U('Goods/goodsList')),
                );
                //重定向, 调整之前URL是设置参数获取方式
                session("is_back", 1);
                $this->ajaxReturn(json_encode($return_arr));
            }
        }
        
        $goodsInfo = M('Goods')->where('goods_id=' . I('GET.goods_id', 0))->find();
        $cat_list = M('goods_category')->where("parent_id = 0")->select();//自营店已绑定所有分类
        
        $store_goods_class_list = M('store_goods_class')->where("parent_id = 0 and store_id = 1")->select(); //店铺内部分类
        $brandList = $GoodsLogic->getSortBrands();
        $goodsType = M("GoodsType")->select();
        $suppliersList = M("suppliers")->select();
        $plugin_shipping = M('plugin')->where(array('type' => array('eq', 'shipping')))->select();//插件物流
        $shipping_areas = M('shipping_area')->where(array('store_id' => 1))->select();
        foreach ($shipping_areas as $key => $val) {
            $shipping_areas[$key]['config'] = unserialize($shipping_areas[$key]['config']);
        }
        $goods_shipping_area_ids = explode(',', $goodsInfo['shipping_area_ids']);
        $this->assign('goods_shipping_area_ids', $goods_shipping_area_ids);
        $this->assign('shipping_area', $shipping_areas);
        $this->assign('plugin_shipping', $plugin_shipping);
        $this->assign('cat_list', $cat_list);
        $this->assign('store_goods_class_list', $store_goods_class_list);
        $this->assign('brandList', $brandList);
        $this->assign('goodsType', $goodsType);
        $this->assign('suppliersList', $suppliersList);
        $this->assign('goodsInfo', $goodsInfo);  // 商品详情
        $goodsImages = M("GoodsImages")->where('goods_id =' . I('GET.goods_id', 0))->select();
        $this->assign('goodsImages', $goodsImages);  // 商品相册
        $this->initEditor(); // 编辑器
        return $this->fetch('_goods');
    }
    
    /**
     * 商品类型  用于设置商品的属性
     */
    public function goodsTypeList()
    {
        $model = M("GoodsType");
        $count = $model->count();
        $pager = new Page($count, 10);
        $goodsTypeList = $model->order("id desc")->limit($pager->firstRow . ',' . $pager->listRows)->select();
        $this->assign('pager', $pager);
        $this->assign('goodsTypeList', $goodsTypeList);
        return $this->fetch('goodsTypeList');
    }
    
    
    /**
     * 添加修改编辑  商品属性类型
     */
    public function addEditGoodsType()
    {
        $id = I('id') ? I('id') : 0;
        $model = M("GoodsType");
        if (IS_POST) {
            $spec_id_array = I('post.spec_id/a', []);//规格数组
            $brand_id_array = I('post.brand_id/a', []);//品牌数组
            $attr_name_array = I('post.attr_name/a', []);//属性名数组
            $attr_values_array = I('post.attr_values/a', []);//属性值数组
            $attr_index_array = I('post.attr_index/a', []);//属性显示
            $order_array = I('post.order/a', []);//属性排序数组
            $attr_id_array = I('post.attr_id/a', []);//属性id数组
            
            $data = $this->request->post();
            
            if ($id) {
                // 编辑操作
                DB::name('GoodsType')->update($data); //$model->save();
            } else {
                // 添加操作
                DB::name('GoodsType')->insert($data);//$model->add();
                $id = DB::name('GoodsType')->getLastInsID();
            }
            if ($id) {
                // 类型规格对应关系表
                $spec_data_list = array();
                if (!empty($spec_id_array)) {
                    foreach ($spec_id_array as $k => $v) {
                        $spec_data_list[] = array('type_id' => $id, 'spec_id' => $v);
                    }
                }
                M('spec_type')->where("type_id = $id")->delete(); // 先把类型规格 表对应的 删除掉 然后再重新添加
                if (count($spec_data_list) > 0) {
                    M('spec_type')->insertAll($spec_data_list);
                }
                // 类型品牌对应关系表
                $brand_id_list = array();
                if (!empty($spec_id_array)) {
                    foreach ($brand_id_array as $k => $v) {
                        $brand_id_list[] = array('type_id' => $id, 'brand_id' => $v);
                    }
                }
                M('brand_type')->where("type_id = $id")->delete(); // 先把类型规格 表对应的 删除掉 然后再重新添加
                if (count($brand_id_list) > 0) {
                    M('brand_type')->insertAll($brand_id_list);
                }
                //处理商品属性
                $attr_name_list = array();
                foreach ($attr_name_array as $k => $v) {
                    $attr_values_array[$k] = str_replace('_', '', $attr_values_array[$k]); // 替换特殊字符
                    $attr_values_array[$k] = str_replace('@', '', $attr_values_array[$k]); // 替换特殊字符
                    $attr_values_array[$k] = trim($attr_values_array[$k]);
                    $attr_index_array[$k] = $attr_index_array[$k] ? $attr_index_array[$k] : 0; // 是否显示
                    $attribute = array(
                        'attr_name' => $v,
                        'type_id' => $id,
                        'attr_index' => $attr_index_array[$k],
                        'attr_values' => $attr_values_array[$k],
                        'attr_input_type' => '1',
                        'order' => $order_array[$k],
                    );
                    if (empty($attr_id_array[$k])) {
                        $attr_name_list[] = $attribute;
                    } else {
                        $attribute['attr_id'] = $attr_id_array[$k];
                        M('goods_attribute')->update($attribute);
                    }
                }
                if (count($attr_name_list) > 0) {
                    // 插入属性
                    M('goods_attribute')->insertAll($attr_name_list);
                }
            }
            $this->success("操作成功!!!", U('Admin/Goods/addEditGoodsType', array('id' => $id)));
            exit;
        }
        $goodsType = $model->where("id = $id")->find();
        $cat_list = M('goods_category')->where("parent_id = 0")->select(); // 已经改成联动菜单
        $attributeList = M('goods_attribute')->where("type_id = $id")->select();
        $this->assign('attributeList', $attributeList);
        $this->assign('cat_list', $cat_list);
        $this->assign('goodsType', $goodsType);
        return $this->fetch('_goodsType');
    }
    
    /**
     * 商品属性列表
     */
    public function goodsAttributeList()
    {
        $goodsTypeList = M("GoodsType")->select();
        $this->assign('goodsTypeList', $goodsTypeList);
        return $this->fetch();
    }
    
    /**
     *  商品属性列表
     */
    public function ajaxGoodsAttributeList()
    {
        //ob_start('ob_gzhandler'); // 页面压缩输出
        $where = ' 1 = 1 '; // 搜索条件                        
        I('type_id') && $where = "$where and type_id = " . I('type_id');
        // 关键词搜索               
        $model = M('GoodsAttribute');
        $count = $model->where($where)->count();
        $Page = new AjaxPage($count, 13);
        $show = $Page->show();
        $goodsAttributeList = $model->where($where)->order('`order` desc,attr_id DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $goodsTypeList = M("GoodsType")->getField('id,name');
        $attr_input_type = array(0 => '手工录入', 1 => ' 从列表中选择', 2 => ' 多行文本框');
        $this->assign('attr_input_type', $attr_input_type);
        $this->assign('goodsTypeList', $goodsTypeList);
        $this->assign('goodsAttributeList', $goodsAttributeList);
        $this->assign('page', $show);// 赋值分页输出
        return $this->fetch();
    }
    
    /**
     * 添加修改编辑  商品属性
     */
    public function addEditGoodsAttribute()
    {
        
        $model = D("GoodsAttribute");
        $type = $_POST['attr_id'] > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
        $_POST['attr_values'] = str_replace('_', '', $_POST['attr_values']); // 替换特殊字符
        $_POST['attr_values'] = str_replace('@', '', $_POST['attr_values']); // 替换特殊字符
        $_POST['attr_values'] = trim($_POST['attr_values']);
        
        if (($_GET['is_ajax'] == 1) && IS_POST)//ajax提交验证
        {
            C('TOKEN_ON', false);
            if (!$model->create(NULL, $type))// 根据表单提交的POST数据创建数据对象
            {
                //  编辑
                $return_arr = array(
                    'status' => -1,
                    'msg' => '',
                    'data' => $model->getError(),
                );
                $this->ajaxReturn(json_encode($return_arr));
            } else {
                // C('TOKEN_ON',true); //  form表单提交
                if ($type == 2) {
                    $model->save(); // 写入数据到数据库
                } else {
                    $insert_id = $model->add(); // 写入数据到数据库
                }
                $return_arr = array(
                    'status' => 1,
                    'msg' => '操作成功',
                    'data' => array('url' => U('Admin/Goods/goodsAttributeList')),
                );
                $this->ajaxReturn(json_encode($return_arr));
            }
        }
        // 点击过来编辑时
        $_GET['attr_id'] = $_GET['attr_id'] ? $_GET['attr_id'] : 0;
        $goodsTypeList = M("GoodsType")->select();
        $goodsAttribute = $model->find($_GET['attr_id']);
        $this->assign('goodsTypeList', $goodsTypeList);
        $this->assign('goodsAttribute', $goodsAttribute);
        return $this->fetch('_goodsAttribute');
    }
    
    /**
     * 更改指定表的指定字段
     */
    public function updateField()
    {
        $primary = array(
            'goods' => 'goods_id',
            'goods_category' => 'id',
            'brand' => 'id',
            'goods_attribute' => 'attr_id',
            'ad' => 'ad_id',
        );
        $model = D($_POST['table']);
        $model->$primary[$_POST['table']] = $_POST['id'];
        $model->$_POST['field'] = $_POST['value'];
        $model->save();
        $return_arr = array(
            'status' => 1,
            'msg' => '操作成功',
            'data' => array('url' => U('Admin/Goods/goodsAttributeList')),
        );
        $this->ajaxReturn(json_encode($return_arr));
    }
    
    /**
     * 动态获取商品属性输入框 根据不同的数据返回不同的输入框类型
     */
    public function ajaxGetAttrInput()
    {
        $GoodsLogic = new GoodsLogic();
        $str = $GoodsLogic->getAttrInput($_REQUEST['goods_id'], $_REQUEST['type_id']);
        exit($str);
    }
    
    /**
     * 删除商品
     */
    public function delGoods()
    {
        $goods_id = $_GET['id'];
        $error = '';
        
        // 判断此商品是否有订单
        $c1 = M('OrderGoods')->where("goods_id = $goods_id")->count('1');
        $c1 && $error .= '此商品有订单,不得删除! <br/>';
        
        
        // 商品团购
        $c1 = M('group_buy')->where("goods_id = $goods_id")->count('1');
        $c1 && $error .= '此商品有团购,不得删除! <br/>';
        
        // 商品退货记录
        $c1 = M('return_goods')->where("goods_id = $goods_id")->count('1');
        $c1 && $error .= '此商品有退货记录,不得删除! <br/>';
        
        if ($error) {
            $return_arr = array('status' => -1, 'msg' => $error, 'data' => '',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
            $this->ajaxReturn(json_encode($return_arr));
        }
        
        // 删除此商品        
        M("Goods")->where('goods_id =' . $goods_id)->delete();  //商品表
        M("cart")->where('goods_id =' . $goods_id)->delete();  // 购物车
        M("comment")->where('goods_id =' . $goods_id)->delete();  //商品评论
        M("goods_consult")->where('goods_id =' . $goods_id)->delete();  //商品咨询
        M("goods_images")->where('goods_id =' . $goods_id)->delete();  //商品相册
        M("spec_goods_price")->where('goods_id =' . $goods_id)->delete();  //商品规格
        M("spec_image")->where('goods_id =' . $goods_id)->delete();  //商品规格图片
        M("goods_attr")->where('goods_id =' . $goods_id)->delete();  //商品属性
        M("goods_collect")->where('goods_id =' . $goods_id)->delete();  //商品收藏
        
        $return_arr = array('status' => 1, 'msg' => '操作成功', 'data' => '',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
        $this->ajaxReturn(json_encode($return_arr));
    }
    
    /**
     * 删除商品类型
     */
    public function delGoodsType()
    {
        // 判断 商品规格        `tp_spec_type`   `tp_brand_type` 
        $count = M("spec_type")->where("type_id = {$_GET['id']}")->count("1");
        $count > 0 && $this->error('该类型下有商品规格不得删除!', U('Admin/Goods/goodsTypeList'));
        
        $count = M("brand_type")->where("type_id = {$_GET['id']}")->count("1");
        $count > 0 && $this->error('该类型下有管理品牌不得删除!', U('Admin/Goods/goodsTypeList'));
        
        // 判断 商品属性        
        $count = M("GoodsAttribute")->where("type_id = {$_GET['id']}")->count("1");
        $count > 0 && $this->error('该类型下有商品属性不得删除!', U('Admin/Goods/goodsTypeList'));
        // 删除分类
        M('GoodsType')->where("id = {$_GET['id']}")->delete();
        $this->success("操作成功!!!", U('Admin/Goods/goodsTypeList'));
    }
    
    /**
     * 删除商品属性
     */
    public function delGoodsAttribute()
    {
        $id = I('id');
        if (empty($id)) return;
        // 删除 属性
        M("GoodsAttr")->where("attr_id = $id")->delete();
        M('GoodsAttribute')->where("attr_id = $id")->delete();
    }
    
    /**
     * 删除商品规格
     */
    public function delGoodsSpec()
    {
        // 判断 商品规格项
        $count = M("SpecItem")->where("spec_id = {$_GET['id']}")->count("1");
        $count > 0 && $this->error('此规格有规格值不得删除!', U('Admin/Goods/specList'));
        // 删除分类
        M('Spec')->where("id = {$_GET['id']}")->delete();
        M('SpecType')->where("spec_id = {$_GET['id']}")->delete();
        $this->success("操作成功!!!", U('Admin/Goods/specList'));
    }
    
    /**
     * 品牌列表
     */
    public function brandList()
    {
        $model = M("Brand");
        $where = " 1 = 1 ";
        $status = I('status');
        $keyword = I('keyword');
        $status && $where .= " and status = $status ";
        $keyword && $where .= " and name like '%$keyword%' ";
        $count = $model->where($where)->count();
        $pager = new Page($count, 10);
        $brandList = $model->where($where)->order("`sort` desc, FIELD(`status`, 1, 2, 0)")->limit($pager->firstRow . ',' . $pager->listRows)->select();
        $cat_list = M('goods_category')->where("parent_id = 0")->getField('id,name'); // 已经改成联动菜单
        $this->assign('cat_list', $cat_list);
        $this->assign('pager', $pager);
        $this->assign('brandList', $brandList);
        return $this->fetch('brandList');
    }
    
    /**
     * ajax 获取 品牌列表
     */
    public function getBrandByCat()
    {
        $db_prefix = C('database.prefix');
        $cat_id = I('cat_id');
        $level = I('l');
        $type_id = I('type_id');
        
        if ($type_id)
            //$list = M('brand')->join("left join {$db_prefix}brand_type on {$db_prefix}brand.id = {$db_prefix}brand_type.brand_id and  type_id = $type_id")->order('id')->select();    
            $list = DB::query("SELECT * FROM `__PREFIX__brand` `b` LEFT JOIN `__PREFIX__brand_type` `t` ON `b`.`id`=`t`.`brand_id` and  type_id = :type_id ORDER BY id", ["type_id" => $type_id]);
        else
            $list = M('brand')->order('id')->select();
        
        $goods_category_list = M('goods_category')->where("id in(select cat_id1 from {$db_prefix}brand) ")->getField("id,name,parent_id");
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
        $cat_id = I('cat_id');
        $level = I('l');
        $type_id = I('type_id');
        
        
        if ($type_id)
            //$list = M('spec')->join("left join {$db_prefix}spec_type on {$db_prefix}spec.id = {$db_prefix}spec_type.spec_id  and  type_id = $type_id")->order('id')->select();
            $list = DB::query("SELECT * FROM `__PREFIX__spec` `s` LEFT JOIN `__PREFIX__spec_type` `t` ON `s`.`id`=`t`.`spec_id` and  type_id = :type_id ORDER BY id", ["type_id" => $type_id]);
        else
            $list = M('spec')->order('id')->select();
        
        $goods_category_list = M('goods_category')->where("id in(select cat_id1 from {$db_prefix}spec) ")->getField("id,name,parent_id");
        $goods_category_list[0] = array('id' => 0, 'name' => '默认');
        asort($goods_category_list);
        $this->assign('goods_category_list', $goods_category_list);
        $this->assign('type_id', $type_id);
        $this->assign('list', $list);
        return $this->fetch();
    }
    
    /**
     * 添加修改编辑  商品品牌
     */
    public function addEditBrand()
    {
        $id = I('id', 0);
        
        if (IS_POST) {
            $data = input('post.');
            if ($id)
                M("Brand")->update($data);
            else
                M("Brand")->insert($data);
            
            $this->success("操作成功!!!", U('Admin/Goods/brandList', array('p' => $_GET['p'])));
            exit;
        }
        $cat_list = M('goods_category')->where("parent_id = 0")->select(); // 已经改成联动菜单
        $this->assign('cat_list', $cat_list);
        $brand = M("Brand")->where("id = $id")->find();
        $this->assign('brand', $brand);
        return $this->fetch('_brand');
    }
    
    /**
     * 删除品牌
     */
    public function delBrand()
    {
        // 判断此品牌是否有商品在使用
        $goods_count = M('Goods')->where("brand_id = {$_GET['id']}")->count('1');
        if ($goods_count) {
            $return_arr = array('status' => -1, 'msg' => '此品牌有商品在用不得删除!', 'data' => '',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
            $this->ajaxReturn($return_arr);
        }
        
        $model = M("Brand");
        $model->where('id =' . $_GET['id'])->delete();
        $return_arr = array('status' => 1, 'msg' => '操作成功', 'data' => '',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
        $this->ajaxReturn($return_arr);
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
        $where = ' 1 = 1 '; // 搜索条件                        
        I('cat_id1') && $where = "$where and cat_id1 = " . I('cat_id1');
        // 关键词搜索               
        $model = D('spec');
        $count = $model->where($where)->count();
        $pager = new AjaxPage($count, 13);
        //$show = $pager->show();
        
        $cat_list = M('goods_category')->where("parent_id = 0")->getField('id,name'); // 已经改成联动菜单        
        $specList = $model->where($where)->order('`cat_id1` desc')->limit($pager->firstRow . ',' . $pager->listRows)->select();
        $this->assign('cat_list', $cat_list);
        $this->assign('specList', $specList);
        $this->assign('pager', $pager);// 赋值分页输出
        return $this->fetch();
    }
    
    /**
     * 添加修改编辑  商品规格
     */
    public function addEditSpec()
    {
        
        $model = D("spec");
        $type = $_POST['id'] > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
        if (($_GET['is_ajax'] == 1) && IS_POST)//ajax提交验证
        {
            
            // 数据验证
            $validate = \think\Loader::validate('Spec');
            $post_data = input('post.');
            
            
            // C('TOKEN_ON',true); //  form表单提交
            if ($type == 2) {
                //更新数据
                $check = $validate->scene('edit')->batch()->check($post_data);
            } else {
                //插入数据
                $check = $validate->batch()->check($post_data);
            }
            if (!$check) {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'data' => $error,
                );
                $this->ajaxReturn($return_arr);
            }
            $model->data($post_data, true); // 收集数据
            if ($type == 2) {
                $model->isUpdate(true)->save(); // 写入数据到数据库
                $model->afterSave(I('id'));
            } else {
                $model->save(); // 写入数据到数据库
                $insert_id = $model->getLastInsID();
                $model->afterSave($insert_id);
            }
            
            $return_arr = array(
                'status' => 1,
                'msg' => '操作成功',
                'data' => array('url' => U('Admin/Goods/specList')),
            );
            $this->ajaxReturn($return_arr);
            
        }
        // 点击过来编辑时
        $id = I('id/d', 0);
        $spec = $model->find($id);
        $cat_list = M('goods_category')->where("parent_id = 0")->getField('id,name,parent_id'); // 已经改成联动菜单
        $this->assign('cat_list', $cat_list);
        $this->assign('spec', $spec);
        return $this->fetch('_spec');
    }
    
    
    /**
     * 动态获取商品规格选择框 根据不同的数据返回不同的选择框
     */
    public function ajaxGetSpecSelect()
    {
        $goods_id = $_GET['goods_id'] ? $_GET['goods_id'] : 0;
        $GoodsLogic = new GoodsLogic();
        //$_GET['spec_type'] =  13;
        $specList = D('Spec')->where("type_id = " . $_GET['spec_type'])->order('`order` desc')->select();
        foreach ($specList as $k => $v)
            $specList[$k]['spec_item'] = D('SpecItem')->where("spec_id = " . $v['id'])->getField('id,item'); // 获取规格项
        
        $items_id = M('SpecGoodsPrice')->where('goods_id = ' . $goods_id)->getField("GROUP_CONCAT(`key` SEPARATOR '_') AS items_id");
        $items_ids = explode('_', $items_id);
        
        // 获取商品规格图片                
        if ($goods_id) {
            $specImageList = M('SpecImage')->where("goods_id = $goods_id")->getField('spec_image_id,src');
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
        $goods_id = $_REQUEST['goods_id'] ? $_REQUEST['goods_id'] : 0;
        $str = $GoodsLogic->getSpecInput($goods_id, $_POST['spec_arr']);
        exit($str);
    }
    
    /**
     * 商品批量操作
     */
    public function act()
    {
        $act = I('post.act', '');
        $goods_ids = I('post.goods_ids');
        $goods_state = I('post.goods_state');
        $reason = I('post.reason', '无备注');
        $return_success = array('status' => 1, 'msg' => '操作成功', 'data' => '');
        if ($act == 'hot') {
            $hot_condition['goods_id'] = array('in', $goods_ids);
            M('goods')->where($hot_condition)->save(array('is_hot' => 1));
            $this->ajaxReturn($return_success);
        }
        if ($act == 'recommend') {
            $recommend_condition['goods_id'] = array('in', $goods_ids);
            M('goods')->where($recommend_condition)->save(array('is_recommend' => 1));
            $this->ajaxReturn($return_success);
        }
        if ($act == 'new') {
            $new_condition['goods_id'] = array('in', $goods_ids);
            M('goods')->where($new_condition)->save(array('is_new' => 1));
            $this->ajaxReturn($return_success);
        }
        if ($act == 'takeoff') {
            M('goods')->where(array('goods_id' => $goods_ids))->save(array('is_on_sale' => 2, 'close_reason' => $reason));
            adminLog('违规下架商品ID(' . $goods_ids . ')', 6);
            $this->ajaxReturn($return_success);
        }
        if ($act == 'examine') {
            $goods_array = explode(',', $goods_ids);
            $goods_state_cg = C('goods_state');
            if (!array_key_exists($goods_state, $goods_state_cg)) {
                $return_success = array('status' => -1, 'msg' => '操作失败，商品没有这种属性', 'data' => '');
                $this->ajaxReturn($return_success);
            }
            foreach ($goods_array as $key => $val) {
                $update_goods_state = M('goods')->where("goods_id = $val")->save(array('goods_state' => $goods_state));
                if ($update_goods_state) {
                    $update_goods = M('goods')->where(array('goods_id' => $val))->find();
                    // 给商家发站内消息 告诉商家商品被批量操作
                    $store_msg = array(
                        'store_id' => $update_goods['store_id'],
                        'content' => "您的商品\"{$update_goods[goods_name]}\"被{$goods_state_cg[$goods_state]}",
                        'addtime' => time(),
                    );
                    M('store_msg')->add($store_msg);
                }
            }
            $this->ajaxReturn($return_success);
        }
        $return_fail = array('status' => -1, 'msg' => '没有找到该批量操作', 'data' => '');
        $this->ajaxReturn($return_fail);
    }
    
    /*获取select的input*/
    public function ajax_getcat($pid)
    {
        $cat_list = M('goods_category')->field('id,name')->where('parent_id', $pid)->select();
        if (!empty($cat_list)) {
            $cat_str = '<option value="">请选择分类</option>';
            foreach ($cat_list as $k => $v) {
                $cat_str .= '<option value="' . $v['id'] . '">' . $v['name'] . '</option>';
            }
        } else {
            $cat_str = '<option val="">请选择分类</option>';
        }
        $this->ajaxReturn(array('status' => 1, 'data' => $cat_str));
    }
    
    /*批量转移商品分类*/
    public function do_cat()
    {
        list($data['cat_id1'], $data['cat_id2'], $data['cat_id3'], $goods_ids) = array(I('post.c1'), I('post.c2'), I('post.c3'), I('post.goods_ids'));
        $cat_str = $data['cat_id1'] . '_' . $data['cat_id2'] . '_' . $data['cat_id3'];
        $goodsList = M('goods')->field('goods_id,cat_id1,cat_id2,cat_id3,store_id')->where("goods_id in ({$goods_ids})")->select();
        $undoing = '';
        $dostatus = 0;
        foreach ($goodsList as $k => $v) {
            $calss_bind = M('store_bind_class')->where('store_id=' . $v['store_id'] . ' and state = 1')->select();
            $curr_class1 = array_column($calss_bind, "class_1");
            $curr_class2 = array_column($calss_bind, "class_2");
            $curr_class3 = array_column($calss_bind, "class_3");
            if (!in_array($data['cat_id1'], $curr_class1) || !in_array($data['cat_id2'], $curr_class2) || !in_array($data['cat_id3'], $curr_class3)) {
                $dostatus = 1;
                $undoing .= "商品{$v['goods_id']},店铺id {$v['store_id']}，未添加此经营分类！！<br/>";
            }
        }
        if ($dostatus == 0) {
            foreach ($goodsList as $k => $v) {
                M('goods')->where('goods_id = ' . $v['goods_id'])->save($data);  //主分类修改
                list($data1['cat'], $data1['cat_str']) = array($data['cat_id1'], $cat_str); //data分类表1级
                M('goods_category_data')->where('goods_id = ' . $v['goods_id'] . ' and is_ext = 0 and level = 1')->save($data1);
                list($data1['cat'], $data1['cat_str']) = array($data['cat_id2'], $cat_str); //data分类表2级
                M('goods_category_data')->where('goods_id = ' . $v['goods_id'] . ' and is_ext = 0 and level = 2')->save($data1);
                list($data1['cat'], $data1['cat_str']) = array($data['cat_id3'], $cat_str); //data分类表3级
                M('goods_category_data')->where('goods_id = ' . $v['goods_id'] . ' and is_ext = 0 and level = 3')->save($data1);
                //扩展分类是否和主分类重复
                $map = 'goods_id = ' . $v['goods_id'] . ' and is_ext = 1 and cat_str="' . $cat_str . '"';
                $extcount = M('goods_category_data')->where($map)->count();
                if ($extcount > 0) M('goods_category_data')->where($map)->delete();
            }
            $reArr['msg'] = '修改成功！';
            $reArr['status'] = 1;
        } else {
            $reArr['msg'] = $undoing;
            $reArr['status'] = -1;
        }
        $this->ajaxReturn($reArr);
    }


    /*批量转移商品分类*/
    public function do_down()
    {
        $goods_ids = I('post.goods_ids');
        $map['goods_id'] = array('IN',$goods_ids);
        M('goods')->where($map)->update(array('is_on_sale'=>2));
        $reArr['msg'] = '修改成功！';
        $reArr['status'] = 1;
   
        $this->ajaxReturn($reArr);
    }

    //改变销售数量
    public function ajaxChangeSales(){
        $goods_id=I('goods_id');
        $count=I('sale_count',0);
        if($goods_id){
            M('goods')->where('goods_id',$goods_id)->setField('sale_sum',$count);
        }
        $this->ajaxReturn(array('status'=>1,'message'=>'修改成功！'));
    }
}
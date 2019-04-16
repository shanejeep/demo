<?php
/**
 * @author: Spjiang<jiangshengping@outlook.com>
 * @time: 2017/8/4 14:49
 */
use think\Route;
Route::get('categoryList/','Mobile/Goods/categoryList');   //分类列表
Route::get('list/','Mobile/Goods/goodsList');   //商品列表
Route::get('search','Mobile/Goods/search');   //商品搜索
Route::get('cart/','Mobile/Cart/cart');   //购物车
Route::get('goods/:id','Mobile/Goods/goodsInfo');   //商品详情
Route::get('store/:store_id','Mobile/Store/index');   //店铺详情
Route::get('/zhaoshang','Home/Newjoin/index');   //招商地址
Route::get('drug/:id','Mobile/Goods/drugInfo');   //药品详情
Route::get('subscribe/','Mobile/Cart/subscribe');   //预约清单



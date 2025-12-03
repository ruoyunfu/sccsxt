<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2024 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

use think\facade\Route;
use app\common\middleware\UserTokenMiddleware;
use app\common\middleware\MerchantServerMiddleware;
use app\common\middleware\AllowOriginMiddleware;

Route::group('api/', function () {

    //客服聊天
    Route::group('service', function () {
        Route::get('history/:id', 'api.store.service.Service/chatHistory');
        Route::get('list', 'api.store.service.Service/getList');
        Route::get('mer_history/:merId/:id', 'api.store.service.Service/serviceHistory');
        Route::get('user_list/:merId', 'api.store.service.Service/serviceUserList');
        //客服扫码登录
        Route::post('scan_login/:key', 'api.store.service.Service/scanLogin');
        Route::get('user/:merId/:uid', 'api.store.service.Service/user');
        Route::post('mark/:merId/:uid', 'api.store.service.Service/mark');
    });

    //客服商品管理
    Route::group('server/:merId', function () {
        //商品
        Route::post('product/create', 'StoreProduct/create');
        Route::post('product/update/:id', 'StoreProduct/update');
        Route::get('product/detail/:id', 'StoreProduct/detail');
        Route::post('product/delete/:id', 'StoreProduct/delete');
        Route::post('product/status/:id', 'StoreProduct/switchStatus');
        Route::get('product/lst', 'StoreProduct/lst');
        Route::get('product/title', 'StoreProduct/title');
        Route::post('product/restore/:id', 'StoreProduct/restore');
        Route::post('product/destory/:id', 'StoreProduct/destory');
        Route::post('product/good/:id', 'StoreProduct/updateGood');
        Route::get('product/config', 'StoreProduct/config');

        //商品分类
        Route::get('category/lst', 'StoreCategory/lst');
        Route::post('category/create', 'StoreCategory/create');
        Route::post('category/update/:id', 'StoreCategory/update');
        Route::get('category/detail/:id', 'StoreCategory/detail');
        Route::post('category/status/:id', 'StoreCategory/switchStatus');
        Route::post('category/delete/:id', 'StoreCategory/delete');
        Route::get('category/list', 'StoreCategory/getList');
        Route::get('category/select', 'StoreCategory/getTreeList');
        Route::get('category/brandlist', 'StoreCategory/BrandList');

        //运费模板
        Route::get('template/lst', 'ShippingTemplate/lst');
        Route::post('template/create', 'ShippingTemplate/create');
        Route::post('template/update/:id', 'ShippingTemplate/update');
        Route::get('template/select', 'ShippingTemplate/getList');
        Route::get('template/detail/:id', 'ShippingTemplate/detail');
        Route::post('template/delete', 'ShippingTemplate/batchDelete');

        //品牌管理
        Route::get('attr/lst', 'StoreProductAttrTemplate/lst');
        Route::post('attr/create', 'StoreProductAttrTemplate/create');
        Route::post('attr/update/:id', 'StoreProductAttrTemplate/update');
        Route::get('attr/detail/:id', 'StoreProductAttrTemplate/detail');
        Route::post('attr/delete', 'StoreProductAttrTemplate/batchDelete');
        Route::get('attr/detail/:id', 'StoreProductAttrTemplate/detail');
        Route::get('attr/list', 'StoreProductAttrTemplate/getlist');
    })->prefix('api.server.')->middleware(MerchantServerMiddleware::class, ['reqire' => true,'auth' => 1]);

    Route::group(function () {
        //管理员订单
        Route::group('admin/:merId', function () {
            Route::get('/statistics', '/orderStatistics');
            Route::get('/order_price', '/orderDetail');
            Route::get('/order_list', '/orderList');
            Route::get('/order/:id', '/order');
            Route::post('/mark/:id', '/mark');
            Route::post('/price/:id', '/price');
            Route::post('/delivery/:id', '/delivery');
            Route::post('/verify/:id', '/verify');
            Route::get('/pay_price', '/payPrice');
            Route::get('/pay_number', '/payNumber');
            Route::get('/mer_form', '/getFormData');
            Route::get('/dump_temp', '/getFormData');
            Route::get('/delivery_config', '/getDeliveryConfig');
            Route::get('/delivery_options', '/getDeliveryOptions');
            Route::get('/delivery/options', '/options');
            // 预约
            Route::group('reservation', function () {
                Route::get('staffs','/staffList');
                Route::post('dispatch/:id', '/reservationDispatch');
                Route::post('updateDispatch/:id', '/reservationUpdateDispatch');
                Route::post('reschedule/:id', '/reservationReschedule');
                Route::post('verify/:id', '/reservationVerify');
                Route::get('config', '/reservationConfig');
            });
            // 同城配送
            Route::group('delivery', function () {
                Route::get('person','/deliveryPersonList');
                Route::post('dispatch/:id', '/deliveryDispatch');
                Route::post('updateDispatch/:id', '/deliveryUpdateDispatch');
                Route::post('confirm/:id', '/deliveryConfirm');
            });
        })->prefix('api.server.StoreOrder');
        //管理员退款单
        Route::group('server/:merId/refund', function () {
            //退款单
            Route::get('check/:id', '/check');
            Route::post('create', '/create');
            Route::post('compute', '/compute');
            Route::get('lst', '/lst');
            Route::get('detail/:id', '/detail');
            Route::get('get/:id', '/getRefundPrice');
            Route::post('confirm/:id', '/refundPrice');
            Route::get('express/:id', '/express');
            Route::post('status/:id', '/switchStatus');
            Route::post('mark/:id', '/mark');
        })->prefix('api.server.StoreRefundOrder');

    })->middleware(MerchantServerMiddleware::class, ['reqire' => true,'auth' => 0]);

    Route::group(function () {
        //核销订单
        Route::group('verifier/:merId', function () {
            Route::get('order/:id', '/detail');
            Route::post(':id', '/verify');
        })->prefix('api.store.order.StoreOrderVerify');
    })->middleware(MerchantServerMiddleware::class, ['reqire' => false,'auth' => 0],['reqire' => false]);

    Route::group(function () {
        Route::group('staffs/', function () {
            Route::get('order_lst', '/order_lst');
            Route::get('order/:id', '/orderDetail');
            Route::post('order/:id/dispatch', '/reservationDispatch');
            Route::post('order/:id/verifier', '/verify');
            Route::post('order/:id/check', '/checkIn');
            Route::post('order/:id/trace', '/addTrace');
            Route::post('order/:id/mark', '/mark');
            Route::get('reservation/config', '/reservationConfig');
        })->prefix('api.store.service.Staffs');
    })->middleware(MerchantServerMiddleware::class, [],['reqire' => true]);

    Route::group(function () {
        Route::group('delivery/', function () {
            Route::get('order_lst', '/order_lst')->name('deliveryOrderLst');
            Route::get('order/:id', '/orderDetail')->name('deliveryOrderDetail');
            Route::post('order/:id/receive', '/receive');
            Route::post('order/:id/confirm', '/confirm');
            Route::post('order/:id/mark', '/mark');
        })->prefix('api.store.service.Delivery');
    })->middleware(MerchantServerMiddleware::class, [],[],['reqire' => true]);

})->middleware(AllowOriginMiddleware::class)
    ->middleware(UserTokenMiddleware::class,true);

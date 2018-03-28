<?php

namespace {

    use function foo\func;

    define('BOOTSTRAP', true);
    $_SERVER['HTTP_USER_AGENT'] = 'user-agent';
    $_SERVER['REMOTE_ADDR'] = 'remote-addr';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SESSION['auth'] = ['user_id'  =>  1];
    $_REQUEST['product_id'] = 2;

    function fn_exim_get_product_url() {
        return 'some-url';
    }

    function fn_get_product_price() {
        return 12;
    }

    function fn_get_user_name() {
        return 'moosend';
    }

    function fn_get_user_info() {
        return ['email' =>  'admin@moosend.com'];
    }

    function fn_get_order_info() {
        return ['email' => 'admin@moosend.com'];
    }

    function fn_get_product_data() {
        return [
            'product_id'    => 12,
            'main_pair' =>  [
                'detailed'  =>  [
                    'image_path'    =>  'https://image-path.com/image.png'
                ]
            ],
            'product'   =>  'product',
            'amount'    => 12,
            'price' =>  12.122,
            'header_features'   =>  array(

            ),
            'category_ids'  =>  [24]
        ];
    }

    function fn_gather_additional_product_data() {

    }

    function fn_get_category_name() {
        return 'some-category-name';
    }

    require_once __DIR__ . '/../func.php';
}

namespace Tygh {

    class Registry {

        private static $site_id = 'some-id';

        public static function get($string = null) {
            switch ($string) {
                case 'runtime.controller':
                    return 'products';
                case 'runtime.mode':
                    return 'quick_view';
                default:
                    return ['site_id' => self::$site_id];
            }
        }

        public static function set($name) {
            self::$site_id = $name;
        }

    }
}

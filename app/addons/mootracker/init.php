<?php

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

fn_register_hooks(
    'dispatch_before_send_response',
    'set_admin_notification',
    'get_product_data_post',
    'post_add_to_cart',
    'login_user_post',
    'place_order'
);

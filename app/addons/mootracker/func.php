<?php

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

require_once __DIR__ . '/vendor/autoload.php';

use Tygh\Registry;

$tracker = null;

function set_tracker_factory(\Moosend\TrackerFactory $trackerFactory = null, $site_id)
{
    global $tracker;
    $tracker = $trackerFactory->create($site_id);
    $tracker->init($site_id);
    return $tracker;
}

function get_tracker_factory($site_id)
{
    global $tracker;
    if (is_null($tracker)) {
        $trackerFactory = new \Moosend\TrackerFactory();
        $tracker = $trackerFactory->create($site_id);
        $tracker->init($site_id);
    }
    return $tracker;
}

/**
 * @param array $product_data
 * @return array
 */
function format_product_properties($product_data)
{
    return array(
        array(
            'product' => array(
                'itemCode' => $product_data['product_id'],
                'itemPrice' => floatval($product_data['price']),
                'itemUrl' => fn_exim_get_product_url($product_data['product_id']),
                'itemQuantity' => intval($product_data['amount']),
                'itemTotal' => floatval($product_data['price']),
                'itemImage' => $product_data['main_pair']['detailed']['image_path'],
                'itemName' => $product_data['product'],
                'itemDescription' => strip_tags($product_data['meta_description']),
                'itemCategory' => get_category_names($product_data['category_ids']),
                'itemManufacturer' => get_product_manufacturer($product_data)
            )
        )
    );
}

/**
 * Utility function, return the current url
 *
 * @return string
 */
function getCurrentUrl()
{
    if (php_sapi_name() == 'cli') {
        return '';
    }

    $protocol = 'http://';

    if ((isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1))
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) {
        $protocol = 'https://';
    }

    $url = $protocol . $_SERVER['HTTP_HOST'];

    $url .= $_SERVER['REQUEST_URI'];

    return $url;
}

/**
 * Display a notification error in admin when website id is empty.
 * @return void
 */
function fn_mootracker_set_admin_notification()
{
    $website_id = Registry::get('addons.mootracker')['site_id'];
    if (empty($website_id)) {
        $message = __('mootracker_no_website_id_notification', array('[addon_link]' => fn_url('addons.update&addon=mootracker')));
        fn_set_notification('E', __('warning'), $message, 'K');
    }
}

/**
 * Track pageView events only for client-side pages and non-product pages
 * @param  $status     [description]
 * @param  {string} $area       [Can be 'C' or 'A' - C = Client, A = Admin]
 * @param  {string} $controller [Each page has it's own controller, can be 'products' or 'index', etc.]
 * @return void
 */
function fn_mootracker_dispatch_before_send_response($status, $area, $controller)
{
    $site_id = Registry::get('addons.mootracker')['site_id'];

    if ($area == 'C' && $controller !== 'products') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return;
        }

        if (!empty($site_id)) {
            $tracker = get_tracker_factory($site_id);

            //page view
            $actual_link = getCurrentUrl();
            try {
                $tracker->pageView($actual_link);
            } catch (Exception $err) {
                trigger_error('Could not track events for MooTracker', E_USER_WARNING);
            }
        }
    }
    //JS TRACKER
    if (!empty($site_id) && strpos($_SERVER['REQUEST_URI'],'admin.php') == false){
        try {
            echo '<script type="text/javascript">!function(t,n,e,o,a){function d(t){var n=~~(Date.now()/3e5),o=document.createElement(e);o.async=!0,o.src=t+"?ts="+n;var a=document.getElementsByTagName(e)[0];a.parentNode.insertBefore(o,a)}t.MooTrackerObject=a,t[a]=t[a]||function(){return t[a].q?void t[a].q.push(arguments):void(t[a].q=[arguments])},window.attachEvent?window.attachEvent("onload",d.bind(this,o)):window.addEventListener("load",d.bind(this,o),!1)}(window,document,"script","//cdn.stat-track.com/statics/moosend-tracking.min.js","mootrack");mootrack(\'init\',"'.$site_id.'");</script>';
        } catch (Exception $err) {
            trigger_error('Could not track events for MooTracker JS', E_USER_WARNING);
        }
    }
}

/**
 * It adds a product to cart. Last parameter "$update", verifies any possible changes made in cart.
 * @param  {object} $product_data [description]
 * @param  {array} $cart         [description]
 * @param  {object} $auth         [description]
 * @param  {boolean} $update       [description]
 * @return {void}
 */
function fn_mootracker_post_add_to_cart($product_data, $cart, $auth, $update)
{
    $site_id = Registry::get('addons.mootracker')['site_id'];
    $productArray = array_keys($product_data);
    if (empty($site_id)) {
        return;
    }

    if ($update) {
        $product_groups = $cart['product_groups'];
        $store = $product_groups[0];
        $old_amounts = array();
        $new_amounts = array();
        $difference = array();
        
        foreach ($productArray as $product_id) {
            $old_amounts[$product_id] = intval($store['products'][$product_id]['amount']);
            $new_amounts[$product_id] = intval($product_data[$product_id]['amount']);
            $difference[$product_id] = $new_amounts[$product_id] - $old_amounts[$product_id];
        }
        
        foreach ($difference as $product_id => $quantity) {
            if ($quantity > 0) {
                add_to_cart($product_id, $quantity);
            }
            if ($quantity < 0) {
                remove_from_cart($product_id, abs($quantity));
            }
        }
    } else {
        $product_id = $product_data[$productArray[0]]['product_id'];

        if ($product_data[$productArray[0]]['amount'] === "0") {
            return fn_delete_cart_product($cart);
        }

        $tracker = get_tracker_factory($site_id);

        $product = fn_get_product_data($product_id, $auth);
        fn_gather_additional_product_data($product, false, false, false, true, false);
        $categories = $product['category_ids'];

        //get post thumbnail
        $large_image_url = $product['main_pair']['detailed']['image_path'];
        $productUrl = fn_exim_get_product_url($product_id);
        $quantity = $product_data[$productArray[0]]['amount'];

        $itemPrice = $product['price'];
        $itemTotalPrice = $itemPrice * $quantity;
        $props = array();
        $props['itemCategory'] = get_category_names($product['category_ids']);
        $props['itemManufacturer'] = get_product_manufacturer($product);

        $tracker->addToOrder($product_id, $itemPrice, $productUrl, $quantity, $itemTotalPrice, $product['product'], $large_image_url, $props, true)->wait();
    }
}

function fn_mootracker_delete_cart_product(&$cart, &$cart_id)
{
    if (!empty($cart_id)) {
        if (isset($cart['products'])) {
            $site_id = Registry::get('addons.mootracker')['site_id'];

            if (!empty($site_id)) {
                $cartProduct = $cart['products'][$cart_id];
                $product_id = $cart['products'][$cart_id]['product_id'];
                $tracker = get_tracker_factory($site_id);

                $auth = &Tygh::$app['session']['auth'];

                $product = fn_get_product_data($product_id, $auth);
                fn_gather_additional_product_data($product, false, false, false, true, false);
                $categories = $product['category_ids'];

                //get post thumbnail
                $large_image_url = $product['main_pair']['detailed']['image_path'];
                $productUrl = fn_exim_get_product_url($product_id);

                $itemPrice = $product['price'] * $cartProduct['amount'];
                $itemTotalPrice = $itemPrice;
                $props = array();
                $props['itemCategory'] = get_category_names($product['category_ids']);
                $props['itemManufacturer'] = get_product_manufacturer($product);

                $tracker->removeFromOrder($product_id, $itemPrice, $productUrl, $itemTotalPrice, $product['product'], $large_image_url, $props, true)->wait();
            }
        }
    }
}

function fn_mootracker_login_user_post()
{
    $auth_session = $_SESSION['auth'];
    $site_id = Registry::get('addons.mootracker')['site_id'];

    if ($auth_session['user_id']) {
        if (!empty($site_id)) {
            $tracker = get_tracker_factory($site_id);

            $userName = fn_get_user_name($_SESSION['auth']['user_id']);
            $userEmail = fn_get_user_info($_SESSION['auth']['user_id'])['email'];

            if (!$tracker->isIdentified($userEmail)) {
                $tracker->identify($userEmail, $userName, array(), true)->wait();
            }
        }
    }
}

function fn_mootracker_place_order($order_id, $action, $order_status, $cart, $auth)
{
    $site_id = Registry::get('addons.mootracker')['site_id'];

    if (!empty($site_id)) {
        $tracker = get_tracker_factory($site_id);
        $products = $cart['products'];

        if (empty($site_id) || empty($tracker)) {
            return;
        }

        $order = fn_get_order_info($order_id);
        $total = array_sum(array_column($products, 'amount'));

        if ($order['email']) {
            if (!$tracker->isIdentified($order['email'])) {
                $tracker->identify($order['email'], '', [], true)->wait();
            }
        }

        $trackerOrder = $tracker->createOrder($total);

        foreach ($products as $product) {
            $instantiatedProduct = fn_get_product_data($product['product_id'], $_SESSION['auth']);
            fn_gather_additional_product_data($instantiatedProduct, false, false, false, true, false);

            $large_image_url = $instantiatedProduct['main_pair']['detailed']['image_path'];
            $productUrl = fn_exim_get_product_url($instantiatedProduct['product_id']);

            $itemCode = $instantiatedProduct['product_id'];
            $itemPrice = $instantiatedProduct['price'];
            $itemQuantity = intval($instantiatedProduct['amount']);
            $itemPriceTotal = floatval($instantiatedProduct['price']);
            $props = array();
            $props['itemCategory'] = get_category_names($instantiatedProduct['category_ids']);
            $props['itemManufacturer'] = get_product_manufacturer($instantiatedProduct);

            $trackerOrder->addProduct($itemCode, $itemPrice, $productUrl, $itemQuantity, $itemPriceTotal, $instantiatedProduct['product'], $large_image_url, $props);
        }

        $tracker->orderCompleted($trackerOrder, true)->wait();
    }
}

/**
 * Returns categoryIds string based on categoryIds array of strings parameter.
 * @param  {array} $category_ids
 * @return {string}
 */
function get_category_names($category_ids)
{
    $category_names = array_map(function ($category_id) {
        return fn_get_category_name($category_id);
    }, $category_ids);
    return implode(", ", $category_names) ?: null;
}

/**
 * Get product manufacturer
 * @param  {array} $product
 * @return {string|null}
 */
function get_product_manufacturer($product)
{
    $header_features = $product['header_features'];
    if (is_array($header_features)) {
        foreach ($header_features as $key => $value) {
            if (array_key_exists('description', $value)) {
                if ($value['description'] == 'Brand') {
                    return $value['variant'];
                }
            }
        }
    }
    return null;
}

/**
 * Track Product Page View, supports view and quick_view products.
 * @param  {array} $product_data Product Data
 * @param  {object} $auth authentication object
 * @return {void}
 */
function fn_mootracker_get_product_data_post($product_data, $auth)
{
    if (Registry::get('runtime.controller') == 'products' &&
        in_array(Registry::get('runtime.mode'), array('view', 'quick_view'))
    ) {
        $site_id = Registry::get('addons.mootracker')['site_id'];

        if (empty($site_id)) {
            return;
        }

        $tracker = get_tracker_factory($site_id);
        $product_id = $_REQUEST['product_id'];
        fn_gather_additional_product_data($product_data, false, false, false, true, false);

        $productUrl = fn_exim_get_product_url($product_id);
        $product = format_product_properties($product_data);

        $tracker->pageView($productUrl, $product, true)->wait();
    }
}

/**
 * Adds a product to cart
 * @param string|int $product_id
 * @param int $quantity
 * @return void
 */
function add_to_cart($product_id, $quantity) {
    $site_id = Registry::get('addons.mootracker')['site_id'];
    $tracker = get_tracker_factory($site_id);
    $auth = &Tygh::$app['session']['auth'];

    $product = fn_get_product_data($product_id, $auth);
    fn_gather_additional_product_data($product, false, false, false, true, false);
    $categories = $product['category_ids'];

    //get post thumbnail
    $large_image_url = $product['main_pair']['detailed']['image_path'];
    $productUrl = fn_exim_get_product_url($product_id);

    $itemPrice = $product['price'];
    $itemTotalPrice = $itemPrice * $quantity;
    $props = array();
    $props['itemCategory'] = get_category_names($product['category_ids']);
    $props['itemManufacturer'] = get_product_manufacturer($product);

    $tracker->addToOrder($product_id, $itemPrice, $productUrl, $quantity, $itemTotalPrice, $product['product'], $large_image_url, $props, true)->wait();
}

/**
 * Remove a product from cart
 * @param string|int $product_id
 * @param int $quantity
 * @return void
 */
function remove_from_cart($product_id, $quantity) {
    $site_id = Registry::get('addons.mootracker')['site_id'];
    $tracker = get_tracker_factory($site_id);
    $auth = &Tygh::$app['session']['auth'];

    $product = fn_get_product_data($product_id, $auth);
    fn_gather_additional_product_data($product, false, false, false, true, false);
    $categories = $product['category_ids'];

    //get post thumbnail
    $large_image_url = $product['main_pair']['detailed']['image_path'];
    $productUrl = fn_exim_get_product_url($product_id);

    $itemPrice = $product['price'] * 1;
    $itemTotalPrice = $itemPrice;
    $props = array();
    $props['itemCategory'] = get_category_names($product['category_ids']);
    $props['itemManufacturer'] = get_product_manufacturer($product);

    $i = 0;
    while ($i++ < $quantity) {
        $tracker->removeFromOrder($product_id, $itemPrice, $productUrl, $itemTotalPrice, $product['product'], $large_image_url, $props, true)->wait();
    }
}

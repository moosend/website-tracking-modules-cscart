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

function fn_mootracker_set_admin_notification()
{
    $website_id = Registry::get('addons.mootracker')['site_id'];
    if (empty($website_id)) {
        $message = __('mootracker_no_website_id_notification', array('[addon_link]' => fn_url('addons.update&addon=mootracker')));
        fn_set_notification('E', __('warning'), $message, 'K');
    }
}

function fn_mootracker_dispatch_before_send_response($status, $area, $controller)
{
    if ($area == 'C' && $controller !== 'products') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return;
        }

        $site_id = Registry::get('addons.mootracker')['site_id'];

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
}

function fn_mootracker_post_add_to_cart($product_data, $cart, $auth)
{
    $productArray = array_keys($product_data);
    $product_id = $product_data[$productArray[0]]['product_id'];
    $site_id = Registry::get('addons.mootracker')['site_id'];

    if (!empty($site_id)) {
        $tracker = get_tracker_factory($site_id);

        $product = fn_get_product_data($product_id, $auth);
        fn_gather_additional_product_data($product, false, false, false, true, false);
        $categories = $product['category_ids'];

        //get post thumbnail
        $large_image_url = $product['main_pair']['detailed']['image_path'];
        $productUrl = fn_exim_get_product_url($product_id);
        $quantity = $product['amount'];

        $itemPrice = $product['price'];
        $itemTotalPrice = $itemPrice;
        $props = array();
        $props['itemCategory'] = get_category_names($product['category_ids']);
        $props['itemManufacturer'] = get_product_manufacturer($product);

        try {
            $tracker->addToOrder($product_id, $itemPrice, $productUrl, $quantity, $itemTotalPrice, $product['product'], $large_image_url, $props);
        } catch (Exception $err) {
            trigger_error('Could not track events for MooTracker', E_USER_WARNING);
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
                try {
                    $tracker->identify($userEmail, $userName);
                } catch (Exception $err) {
                    trigger_error('Could not track events for MooTracker', E_USER_WARNING);
                }
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
                $tracker->identify($order['email'], '', [], true);
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

        try {
            $tracker->orderCompleted($trackerOrder);
        } catch (Exception $err) {
            trigger_error('Could not track events for MooTracker', E_USER_WARNING);
        }
    }
}

function get_category_names($category_ids)
{
    $category_names = array_map(function ($category_id) {
        return fn_get_category_name($category_id);
    }, $category_ids);
    return implode(", ", $category_names) ?: null;
}

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

        try {
            $tracker->pageView($productUrl, format_product_properties($product_data));
        } catch (Exception $err) {
            trigger_error('Could not track events for MooTracker', E_USER_WARNING);
        }
    }
}

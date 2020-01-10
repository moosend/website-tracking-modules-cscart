<?php

class MooTrackerTest extends \PHPUnit\Framework\TestCase {

    public $tracker;
    public $trackerFactory;

    public function setUp() {
        $this->tracker = $this->getMockBuilder(\Moosend\Tracker::class)->disableOriginalConstructor()->getMock();

        $this->trackerFactory = $this->getMockBuilder(\Moosend\TrackerFactory::class)->getMock();
        $this->trackerFactory->method('create')->willReturn($this->tracker);

        $_SERVER['HTTP_HOST'] = 'host';
        $_SERVER['REQUEST_URI'] = 'uri';
    }

    public function test_it_doesnt_call_pageView_if_admin() {
        $this->tracker->expects($this->never())->method('pageView');

        set_tracker_factory($this->trackerFactory, \Tygh\Registry::get());
        fn_mootracker_dispatch_before_send_response('status', 'A', 'index');
    }

    public function test_it_doesnt_call_pageView_if_there_is_no_site_id() {
        $this->tracker->expects($this->never())->method('pageView');

        set_tracker_factory($this->trackerFactory, null);
        fn_mootracker_dispatch_before_send_response('status', 'A', 'index');
    }

    public function test_it_calls_page_view_if_client() {
        $this->tracker->expects($this->once())->method('pageView');

        set_tracker_factory($this->trackerFactory, \Tygh\Registry::get());
        fn_mootracker_dispatch_before_send_response('status', 'C', 'index');
    }

    public function test_add_to_cart() {
        $product_data = fn_get_product_data();

        $this->tracker->expects($this->once())->method('addToOrder');
        $this->tracker->expects($this->exactly(1))
            ->method('addToOrder')
            ->with(12, 12.122, 'some-url', 12, 12.122, 'product', 'https://image-path.com/image.png', array(
                'itemCategory'  => fn_get_category_name(),
                'itemManufacturer'  =>  null
            ));

        set_tracker_factory($this->trackerFactory, \Tygh\Registry::get());
        fn_mootracker_post_add_to_cart($product_data, null, null);
    }

    public function test_doesnt_add_to_cart_when_there_is_no_site_id() {
        $product_data =  fn_get_product_data();

        $this->tracker->expects($this->never())->method('addToOrder');

        \Tygh\Registry::set('');
        set_tracker_factory($this->trackerFactory, '');

        fn_mootracker_post_add_to_cart($product_data, null, null);

        \Tygh\Registry::set(\Tygh\Registry::get());
    }

    public function test_delete_cart_product() {
        $this->tracker->expects($this->once())->method('removeFromOrder');

        set_tracker_factory($this->trackerFactory, \Tygh\Registry::get());
        $cart_id = 123;
        $cart = array(
            'products' => array(
                $cart_id => array(
                    'product_id' => 123
                )
            )
        );
        fn_mootracker_delete_cart_product($cart, $cart_id);
        \Tygh\Registry::set('');
    }

    public function test_it_does_not_track_identify_if_there_is_no_site_id() {
        $this->tracker->expects($this->never())->method('identify');

        set_tracker_factory($this->trackerFactory, '');
        fn_mootracker_login_user_post();
    }

    public function test_it_identifies_if_there_is_site_id() {
        $this->tracker->expects($this->once())->method('identify');

        \Tygh\Registry::set(\Tygh\Registry::get());
        set_tracker_factory($this->trackerFactory, \Tygh\Registry::get());
        fn_mootracker_login_user_post();
    }

    public function test_it_tracks_order_complete_event() {
        $orderMock = $this->getMockBuilder(\Moosend\Models\Order::class)->disableOriginalConstructor()->getMock();
        $this->tracker->method('createOrder')->willReturn($orderMock);

        $this->tracker->expects($this->once())->method('orderCompleted');
        $cart = ['products'  =>
            [
                'product_id'    =>  12
            ]
        ];

        set_tracker_factory($this->trackerFactory, \Tygh\Registry::get());
        fn_mootracker_place_order(1, '', '', $cart, 'auth');
    }

    public function test_it_does_not_track_product_page_view_if_there_is_no_site_id() {
        $product_data =  [
            'product_id'    => 12,
            'main_pair' =>  [
                'detailed'  =>  [
                    'image_path'    =>  'https://image-path.com/image.png'
                ]
            ],
            'product'   =>  'product',
            'amount'    => 12,
            'meta_description'  =>  'Some Description',
            'price' => 12.22,
            'main_category' =>  2
        ];

        $this->tracker->expects($this->never())->method('pageView');

        \Tygh\Registry::set('');
        set_tracker_factory($this->trackerFactory, '');
        fn_mootracker_get_product_data_post($product_data, null);
    }

    public function test_it_does_product_page_view_if_there_is_no_site_id() {
        $product_data =  [
            'product_id'    => 12,
            'main_pair' =>  [
                'detailed'  =>  [
                    'image_path'    =>  'https://image-path.com/image.png'
                ]
            ],
            'product'   =>  'product',
            'amount'    => 12,
            'meta_description'  =>  'Some Description',
            'price' => 12.22,
            'main_category' =>  2,
            'category_ids' => array(
                'Category 1',
                'Category 2'
            ),
            'header_features' => array()
        ];

        $this->tracker->expects($this->never())->method('pageView');

        set_tracker_factory($this->trackerFactory, \Tygh\Registry::get());
        fn_mootracker_get_product_data_post($product_data, null);
    }

}

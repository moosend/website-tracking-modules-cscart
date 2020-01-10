# CSCart Plugin Documentation

## Installing add-on to your shop

### Installing it from dashboard
Compress *mootracker* folder and login to your dashboard. Visit *Addons > Manage Add-ons* and click *Upload & install add-on*. After a modal is shown click local to select compressed zip file and then click *Upload & install*.

### Configuring the plugin
Login into CSCart administrator dashboard from this URL: https://ecommerce.services.moostaging.com/cscart/. Credentials can be shared via LastPass.

Navigate to *Add-ons > Manage Add-ons* and click *Settings* to configure the Website ID for this shop.

## Application Architecture
To track visitor activity this plugin uses hooks. Hooks are registered in *init.php* file. By using hooks, it means that you can extend functions to use for your own purpose. Generally a hook includes as many parameters as original hook has (You can view a hook by searching into CSCart codebase). The hook this plugin uses are:

1. *dispatch_before_send_response* - a hook which dispatches before a response is sent (only for pageViews, not productViews)

2. *get_product_data_post* - a hook which runs after you receive product data (for product views)

3. *post_add_to_cart* - a hook which runs after cart is modified that makes it efficient to track add to order events.

4. *login_user_post* - a hook which runs after a user is logged in that makes it efficient to track identify events.

4. *place_order* - a hook which runs before an order is created that makes it efficient to track order completed events.

There is no functionality implemented for Settings, CSCart handles it based on sections you define on *addon.xml*

## Additional Info
`site_checkbox` that is declared in addon.xml is useless and that is because CS-Cart has a bug on admin where if you specify only one input then layout doesn't look well. This checkbox is hidden (has display: none;).

## Extending Hooks
To extend a simple hook, it should be done as it follows:

1. You need to register a hook, (ex. fn_register_hooks('my_hook')) in *init.php*
2. After you have registered a hook, you can extend it in *func.php* like:
```
function fn_{name of add-on}_my_hook() {}
```
so in our case, it would be:
```
function fn_mootracker_my_hook() {}
```

In order to run unit tests from your terminal run : `composer test`

## How to push updates to the addon

This needs to be done in [Github](https://github.com/moosend/website-tracking-modules-cscart).

Once you have made some changes in your repository and are ready to roll out a new release, follow these steps:

Commit your changes:

`$ git add .`

`$ git commit -m "Functionality added."`

Add a tag that conforms to Semantic Versioning:

`$ git tag 1.1.0`

Push the changes in the branch to the remote repository:

`$ git push origin master`

Push the tag to the remote repository:

`$ git push origin 1.1.0`

Once the webhook is processed, the package will appear on the Product packages tab of the add-on editing page in the Marketplace.

By default, the package is Disabled, i.e. unavailable to customers. That way you can test the package before release. Once you’re ready to distribute the package, change its status to Active:

## TODOs
1. Test mootracker add-on in CS-Cart Multi-Vendor Software (https://www.cs-cart.com/multivendor.html)
2. Fix notification message in admin dashboard when website id is empty (see fn_mootracker_set_admin_notification function in func.php)
3. Add more unit-tests.


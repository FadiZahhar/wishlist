<?php

/*
    Plugin Name: Woocommerce wishlist
    Plugin URI: https://www.wmvp.dev
    Description: Ajax wishlist for WooCommerce
    Author: Pro-Freelancer
    Version: 1.0
    Author URI: https://www.wmvp.dev
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/*
    1. Add wishlist to product
    2. Wishlist table shortcode
    3. Wishlist option in the user profile
    4. Extend rest API for products
*/



add_action('init','plugin_init');
function plugin_init(){
    if (class_exists("Woocommerce")) {
        
        
        /*
        
        Here we enqueue the main style.css file and the main.js file for the plugin, also we pass some parameters to the main.js file to work with:

        ajaxUrl – required to fetch some data from WordPress, like current User ID
        ajaxPost – required to update user wishlist
        restUrl – required to list the wishlist items in the wishlist table
        shopName – required to add wishlist items to the session storage for non-registered or non-logged-in users
        And some strings instead of hardcoding them into the js file, in case they need to be translatable.
        
        So for now create a css, and js folder and put the corresponding files inside those folders: style.css in the css folder and main.js in the js folder.
        
        */

        function wishlist_plugin_scripts_styles(){
            wp_enqueue_style( 'wishlist-style', plugins_url('/css/style.css', __FILE__ ), array(), '1.0.0' );
            wp_enqueue_script( 'wishlist-main', plugins_url('/js/main.js', __FILE__ ), array('jquery'), '', true);
            wp_localize_script(
                'main',
                'opt',
                array(
                    'ajaxUrl'        => admin_url('admin-ajax.php'),
                    'ajaxPost'       => admin_url('admin-post.php'),
                    'restUrl'        => rest_url('wp/v2/product'),
                    'shopName'       => sanitize_title_with_dashes(sanitize_title_with_dashes(get_bloginfo('name'))),
                    'inWishlist'     => esc_html__("Already in wishlist","text-domain"),
                    'removeWishlist' => esc_html__("Remove from wishlist","text-domain"),
                    'buttonText'     => esc_html__("Details","text-domain"),
                    'error'          => esc_html__("Something went wrong, could not add to wishlist","text-domain"),
                    'noWishlist'     => esc_html__("No wishlist found","text-domain"),
                )
            );
        }
        add_action( 'wp_enqueue_scripts', 'wishlist_plugin_scripts_styles' );

        /*
        6.
        Our first AJAX request gets the user id and the user wishlist data from WordPress. This is done with a custom AJAX action added to the plugin code file:
        
        */
        // Get current user data
        function fetch_user_data() {
            if (is_user_logged_in()){
                $current_user = wp_get_current_user();
                $current_user_wishlist = get_user_meta( $current_user->ID, 'wishlist',true);
    			echo json_encode(array('user_id' => $current_user->ID,'wishlist' => $current_user_wishlist));
            }
            die();
        }
        add_action( 'wp_ajax_fetch_user_data', 'fetch_user_data' );
        add_action( 'wp_ajax_nopriv_fetch_user_data', 'fetch_user_data' );
        
        /*
        Hook the Wishlist Toggle
        Here we add a wishlist toggle to each product in the loop and to each single product layout, 
        using the woocommerce_before_shop_loop_item_title and woocommerce_single_product_summary hooks.

        Here I want to point out the data-product attribute that contains the product ID–this is required to power the wishlist functionality. 
        And also take a closer look at the SVG icon–this is required to power the animation. 
        
        */
        // Add wishlist to product
        add_action('woocommerce_before_shop_loop_item_title','wishlist_toggle',15);
        add_action('woocommerce_single_product_summary','wishlist_toggle',25);
        function wishlist_toggle(){

            global $product;
            echo '<span class="wishlist-title">'.esc_attr__("Add to wishlist","text-domain").'</span><a class="wishlist-toggle" data-product="'.esc_attr($product->get_id()).'" href="#" title="'.esc_attr__("Add to wishlist","text-domain").'">'.file_get_contents(plugins_url( 'images/icon.svg', __FILE__ )).'</a>';
        }
        
        /*
        5. Wishlist Custom Option in the User Profile
        Our wishlist functionality will work both for logged-in users and guest users. With logged-in users we’ll store the wishlist information in the user’s metadata, and with guest users we’ll store the wishlist in the session storage. 
        You can also store the guest users’ wishlist in local storage, the difference being that session storage is destroyed when the user closes the tab or browser, 
        and local storage is destroyed when the browser cache is cleared. It is up to you which option you use for guest users.
        
        
        All we do here is create a text field input that will hold the wishlist items comma-separated IDs. With show_user_profile and edit_user_profile actions we add the structure of the input field, and with personal_options_update and edit_user_profile_update actions we power the save functionality. 

        So once the wishlist is updated it will save to the database. I you go to your profile page you will see a new text field added to it. 
        Add whatever value you want and hit save to test if the update functionality works. With admin CSS you can hide this field if you don’t want users to see it. 
        I will leave it as is.
        
        */

        // Wishlist option in the user profile
        add_action( 'show_user_profile', 'wishlist_user_profile_field' );
        add_action( 'edit_user_profile', 'wishlist_user_profile_field' );
        function wishlist_user_profile_field( $user ) { ?>
            <table class="form-table wishlist-data">
                <tr>
                    <th><?php echo esc_attr__("Wishlist","text-domain"); ?></th>
                    <td>
                        <input type="text" name="wishlist" id="wishlist" value="<?php echo esc_attr( get_the_author_meta( 'wishlist', $user->ID ) ); ?>" class="regular-text" />
                    </td>
                </tr>
            </table>
        <?php }

        add_action( 'personal_options_update', 'save_wishlist_user_profile_field' );
        add_action( 'edit_user_profile_update', 'save_wishlist_user_profile_field' );
        function save_wishlist_user_profile_field( $user_id ) {
            if ( !current_user_can( 'edit_user', $user_id ) ) {
                return false;
            }
            update_user_meta( $user_id, 'wishlist', $_POST['wishlist'] );
        }

        function update_wishlist_ajax(){
            if (isset($_POST["user_id"]) && !empty($_POST["user_id"])) {
                $user_id   = $_POST["user_id"];
                $user_obj = get_user_by('id', $user_id);
                if (!is_wp_error($user_obj) && is_object($user_obj)) {
                    update_user_meta( $user_id, 'wishlist', $_POST["wishlist"]);
                }
            }
            die();
        }
        add_action('admin_post_nopriv_user_wishlist_update', 'update_wishlist_ajax');
        add_action('admin_post_user_wishlist_update', 'update_wishlist_ajax');
        
        
        /*
        4. Create Wishlist Table Shortcode
        */
        // Wishlist table shortcode
        add_shortcode('wishlist', 'wishlist');
        function wishlist( $atts, $content = null ) {

            extract(shortcode_atts(array(), $atts));

            return '<table class="wishlist-table loading">
                        <tr>
                            <th><!-- Left for image --></th>
                            <th>'.esc_html__("Name","text-domain").'</th>
                            <th>'.esc_html__("Price","text-domain").'</th>
                            <th>'.esc_html__("Stock","text-domain").'</th>
                            <th><!-- Left for button --></th>
                        </tr>
                    </table>';

        }
        
        
        
        /*
        
        Here we are using the WordPress REST API to get the products by ID in the wishlist array. 

        For each of the products we get we are adding a table row with the required data to display. We need the product image, title, stock status, button and price. 
        
        Here we have two options for the REST API: 
        
        using the WordPress REST API 
        using the WooCommerce REST API. 
        The difference here is that product data is already present in the Woocommerce REST API, but an API key is required. With the default WordPress REST API product data is absent by default, but can be added, and no API key is required. For such a simple task as a wishlist I don’t think that an API key is needed, so we will do it by extending the default WordPress REST API to return our product price, image code and the stock level.
        
        Go to the main plugin file and at the very bottom add the following code:
        
        */

        // Extend REST API
        function rest_register_fields(){

            register_rest_field('product',
                'price',
                array(
                    'get_callback'    => 'rest_price',
                    'update_callback' => null,
                    'schema'          => null
                )
            );

            register_rest_field('product',
                'stock',
                array(
                    'get_callback'    => 'rest_stock',
                    'update_callback' => null,
                    'schema'          => null
                )
            );

            register_rest_field('product',
                'image',
                array(
                    'get_callback'    => 'rest_img',
                    'update_callback' => null,
                    'schema'          => null
                )
            );
        }
        add_action('rest_api_init','rest_register_fields');

        function rest_price($object,$field_name,$request){

            global $product;

            $id = $product->get_id();

            if ($id == $object['id']) {
                return $product->get_price();
            }

        }

        function rest_stock($object,$field_name,$request){

            global $product;

            $id = $product->get_id();

            if ($id == $object['id']) {
                return $product->get_stock_status();
            }

        }

        function rest_img($object,$field_name,$request){

            global $product;

            $id = $product->get_id();

            if ($id == $object['id']) {
                return $product->get_image();
            }

        }

        function maximum_api_filter($query_params) {
            $query_params['per_page']["maximum"]=100;
            return $query_params;
        }
        add_filter('rest_product_collection_params', 'maximum_api_filter');
    }
}

/*
Once the remove icon is clicked (make sure you have a remove.svg in the images folder, you can use whatever icon you want),
we need to check if the user is logged-in. If so, we then remove the item ID from the wishlist using AJAX with the user_wishlist_update action. 
If the user is a guest we need to remove the item ID from the session/local storage.

Now go to your wishlist and refresh the page. Once you click on the remove icon your item will be removed from the wishlist.


*/
?>

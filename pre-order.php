<?php
/*
Plugin Name: WooCommerce Preorder Button Text 
Description: Change the Add to Cart button text for pre-order products and add pre-order options.
Version: 1.0.1
Author: Your Name
*/

class WooCommerce_Preorder_Plugin {
    
    public function __construct() {
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_preorder_fields'));
        add_filter('woocommerce_product_single_add_to_cart_text', array($this, 'custom_preorder_button_text'), 10, 1);
        add_filter('woocommerce_product_add_to_cart_text', array($this, 'custom_preorder_button_text'), 10, 1);
        add_action('wp', array($this, 'schedule_preorder_availability_update'));
        add_action('update_preorder_availability', array($this, 'update_preorder_availability'));
        add_action('woocommerce_order_status_processing', array($this, 'send_preorder_purchase_notification'), 10, 1);
        add_action('woocommerce_process_product_meta', array($this, 'handle_dynamic_inventory'));
    }

    // Add custom fields to the product editor for pre-order options
    public function add_preorder_fields() {
        echo '<div class="options_group">';
        
        // Checkbox for marking a product as a pre-order
        woocommerce_wp_checkbox( array(
            'id'            => '_is_pre_order',
            'label'         => __( 'Offer as Pre-order', 'woocommerce' ),
            'description'   => __( 'Check this if you want to offer this product as a pre-order.', 'woocommerce' ),
            'desc_tip'      => true,
        ) );
        
        // Date selection for pre-order products
        woocommerce_wp_text_input( array(
            'id'            => '_pre_order_date',
            'label'         => __( 'Pre-order Date', 'woocommerce' ),
            'placeholder'   => __( 'YYYY-MM-DD', 'woocommerce' ),
            'description'   => __( 'Select the date when this pre-order will be available.', 'woocommerce' ),
            'desc_tip'      => true,
            'type'          => 'date',
        ) );
        
        // Dynamic checkbox for dynamic inventory
        woocommerce_wp_checkbox( array(
            'id'            => '_dynamic_inventory',
            'label'         => __( 'Dynamic Inventory', 'woocommerce' ),
            'description'   => __( 'Check this if you want to enable dynamic inventory for this product.', 'woocommerce' ),
            'desc_tip'      => true,
        ) );
        
        // Text input for pre-order price
        woocommerce_wp_text_input( array(
            'id'            => '_pre_order_price',
            'label'         => __( 'Pre-order Price', 'woocommerce' ),
            'placeholder'   => __( 'Enter pre-order price', 'woocommerce' ),
            'description'   => __( 'Enter the price for this product when it is in pre-order mode.', 'woocommerce' ),
            'desc_tip'      => true,
            'type'          => 'number',
            'custom_attributes' => array(
                'step' => 'any',
            ),
        ) );
        
        // Text input for pre-order discount
        woocommerce_wp_text_input( array(
            'id'            => '_pre_order_discount',
            'label'         => __( 'Pre-order Discount', 'woocommerce' ),
            'placeholder'   => __( 'Enter pre-order discount', 'woocommerce' ),
            'description'   => __( 'Enter the discount for this product during the pre-order period.', 'woocommerce' ),
            'desc_tip'      => true,
            'type'          => 'number',
            'custom_attributes' => array(
                'step' => 'any',
            ),
        ) );
        
        echo '</div>';
    }

    // Callback function to modify the Add to Cart button text and handle pre-order price
    public function custom_preorder_button_text($text) {
        global $product;
        
        // Check if the product is a pre-order
        if ($product && $product->is_type('simple') && 'yes' === get_post_meta($product->get_id(), '_is_pre_order', true)) {
            // Get pre-order price and discount if set, otherwise use regular price
            $pre_order_price = get_post_meta($product->get_id(), '_pre_order_price', true);
            $pre_order_discount = get_post_meta($product->get_id(), '_pre_order_discount', true);
            
            // Debugging: Log pre-order price and discount
            error_log('Pre-order Price: ' . $pre_order_price);
            error_log('Pre-order Discount: ' . $pre_order_discount);
            
            // If both pre-order price and discount are set, calculate discounted price
            if ($pre_order_price !== '' && $pre_order_discount !== '') {
                // Convert pre-order price and discount to float for calculation
                $pre_order_price = floatval($pre_order_price);
                $pre_order_discount = floatval($pre_order_discount);
                
                // Calculate discounted price
                $discounted_price = $pre_order_price - ($pre_order_price * $pre_order_discount / 100);
                
                // Set the discounted price for the product
                $product->set_price($discounted_price);
            }
            
            $text = __('Hi, Pre-order Now', 'woocommerce'); // Change the button text for pre-orders
        }
        
        return $text;
    }

    // Schedule a task to update product availability when pre-order period ends
    public function schedule_preorder_availability_update() {
        if (!wp_next_scheduled('update_preorder_availability')) {
            wp_schedule_event(time(), 'daily', 'update_preorder_availability');
        }
    }

    // Callback function to update product availability
    public function update_preorder_availability() {
        // Get all pre-order products
        $preorder_products = new WP_Query(array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => '_is_pre_order',
                    'value'   => 'yes',
                    'compare' => '=',
                ),
            ),
        ));
        
        if ($preorder_products->have_posts()) {
            while ($preorder_products->have_posts()) {
                $preorder_products->the_post();
                $product_id = get_the_ID();
                $pre_order_date = get_post_meta($product_id, '_pre_order_date', true);
                
                // If pre-order date has passed, update product availability
                if (strtotime($pre_order_date) < time()) {
                    update_post_meta($product_id, '_is_pre_order', 'no');
                }
            }
            wp_reset_postdata();
        }
    }

    // Hook into WooCommerce order processing to send email notification for pre-order products
    public function send_preorder_purchase_notification($order_id) {
        // Get the order object
        $order = wc_get_order($order_id);
        
        // Loop through order items to check if any are pre-order products
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            
            // Check if the product is a pre-order
            if ('yes' === get_post_meta($product_id, '_is_pre_order', true)) {
                $product_name = $item->get_name();
                $product_qty = $item->get_quantity();
                
                // Get the admin email
                $admin_email = get_option('admin_email');
                
                // Email subject
                $subject = 'Pre-order Product Purchase Notification';
                
                // Email body
                $body = 'A pre-order product has been purchased:' . PHP_EOL;
                $body .= 'Product: ' . $product_name . PHP_EOL;
                $body .= 'Quantity: ' . $product_qty . PHP_EOL;
                $body .= 'Order ID: ' . $order_id . PHP_EOL;
                
                // Send the email
                wp_mail($admin_email, $subject, $body);
                break; // Stop checking further items once a pre-order is found
            }
        }
    }

    // Hook into WooCommerce to handle dynamic inventory
    public function handle_dynamic_inventory($product_id) {
        $dynamic_inventory = isset($_POST['_dynamic_inventory']) ? 'yes' : 'no';
        update_post_meta($product_id, '_dynamic_inventory', $dynamic_inventory);
    }
}

new WooCommerce_Preorder_Plugin();

<?php
/*
Plugin Name: WooCommerce Preorder Button Text 
Description: Change the Add to Cart button text for pre-order products and add pre-order options.
Version: 1.0.1
Author: Your Name
*/

// Add custom fields to the product editor for pre-order options
function add_preorder_fields() {
    global $woocommerce, $post;

    echo '<div class="options_group">';

    // Checkbox for marking a product as a pre-order
    woocommerce_wp_checkbox( array(
        'id'            => '_is_pre_order',
        'label'         => __( 'Offer as Pre-order', 'pre-order' ),
        'description'   => __( 'Check this if you want to offer this product as a pre-order.', 'pre-order' ),
        'desc_tip'      => true,
    ) );

    // Date selection for pre-order products
    woocommerce_wp_text_input( array(
        'id'            => '_pre_order_date',
        'label'         => __( 'Pre-order Date', 'pre-order' ),
        'placeholder'   => __( 'YYYY-MM-DD', 'pre-order' ),
        'description'   => __( 'Select the date when this pre-order will be available.', 'pre-order' ),
        'desc_tip'      => true,
        'type'          => 'date',
    ) );

    // Dynamic checkbox for dynamic inventory
    woocommerce_wp_checkbox( array(
        'id'            => '_dynamic_inventory',
        'label'         => __( 'Dynamic Inventory', 'pre-order' ),
        'description'   => __( 'Check this if you want to enable dynamic inventory for this product.', 'pre-order' ),
        'desc_tip'      => true,
    ) );

    // Text input for pre-order price
    woocommerce_wp_text_input( array(
        'id'            => '_pre_order_price',
        'label'         => __( 'Pre-order Price', 'pre-order' ),
        'placeholder'   => __( 'Enter pre-order price', 'pre-order' ),
        'description'   => __( 'Enter the price for this product when it is in pre-order mode.', 'pre-order' ),
        'desc_tip'      => true,
        'type'          => 'number',
        'custom_attributes' => array(
            'step' => 'any',
        ),
    ) );

    // Text input for pre-order discount
    woocommerce_wp_text_input( array(
        'id'            => '_pre_order_discount',
        'label'         => __( 'Pre-order Discount', 'pre-order' ),
        'placeholder'   => __( 'Enter pre-order discount', 'pre-order' ),
        'description'   => __( 'Enter the discount for this product during the pre-order period.', 'pre-order' ),
        'desc_tip'      => true,
        'type'          => 'number',
        'custom_attributes' => array(
            'step' => 'any',
        ),
    ) );

    echo '</div>';
}
add_action( 'woocommerce_product_options_general_product_data', 'add_preorder_fields' );

// Save custom fields data when the product is saved
function save_preorder_fields($post_id) {
    // Save checkbox data
    $is_pre_order = isset($_POST['_is_pre_order']) ? 'yes' : 'no';
    update_post_meta($post_id, '_is_pre_order', $is_pre_order);

    // Save date input data
    $pre_order_date = isset($_POST['_pre_order_date']) ? $_POST['_pre_order_date'] : '';
    update_post_meta($post_id, '_pre_order_date', $pre_order_date);

    // Save dynamic inventory data
    $dynamic_inventory = isset($_POST['_dynamic_inventory']) ? 'yes' : 'no';
    update_post_meta($post_id, '_dynamic_inventory', $dynamic_inventory);

    // Save pre-order price data
    $pre_order_price = isset($_POST['_pre_order_price']) ? $_POST['_pre_order_price'] : '';
    update_post_meta($post_id, '_pre_order_price', $pre_order_price);

    // Save pre-order discount data
    $pre_order_discount = isset($_POST['_pre_order_discount']) ? $_POST['_pre_order_discount'] : '';
    update_post_meta($post_id, '_pre_order_discount', $pre_order_discount);
}
add_action('woocommerce_process_product_meta', 'save_preorder_fields');

// Hook into WooCommerce to modify the Add to Cart button text and handle pre-order price
function custom_preorder_button_text($text) {
    global $product;

    // Check if the product is a pre-order
    if ($product && $product->is_type('simple') && 'yes' === get_post_meta($product->get_id(), '_is_pre_order', true)) {
        // Get pre-order price and discount if set, otherwise use regular price
        $pre_order_price = get_post_meta($product->get_id(), '_pre_order_price', true);
        $pre_order_discount = get_post_meta($product->get_id(), '_pre_order_discount', true);
        
        // Apply the pre-order price if set
        if ($pre_order_price !== '') {
            $product->set_price($pre_order_price);
        }
        
        // Apply the pre-order discount if set
        if ($pre_order_discount !== '') {
            $discounted_price = $product->get_regular_price() - ($product->get_regular_price() * $pre_order_discount / 100);
            $product->set_sale_price($discounted_price);
        }
        
        $text = __('Pre-order Now', 'pre-order'); // Change the button text for pre-orders
    }

    return $text;
}
add_filter('woocommerce_product_single_add_to_cart_text', 'custom_preorder_button_text', 10, 1);
add_filter('woocommerce_product_add_to_cart_text', 'custom_preorder_button_text', 10, 1);

// Schedule a task to update product availability when pre-order period ends
function schedule_preorder_availability_update() {
    if (!wp_next_scheduled('update_preorder_availability')) {
        wp_schedule_event(time(), 'daily', 'update_preorder_availability');
    }
}
add_action('wp', 'schedule_preorder_availability_update');

// Callback function to update product availability
function update_preorder_availability() {
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
add_action('update_preorder_availability', 'update_preorder_availability');

// Hook into WooCommerce to handle dynamic inventory
function handle_dynamic_inventory($product_id) {
    $dynamic_inventory = isset($_POST['_dynamic_inventory']) ? 'yes' : 'no';
    update_post_meta($product_id, '_dynamic_inventory', $dynamic_inventory);
}
add_action('woocommerce_process_product_meta', 'handle_dynamic_inventory');

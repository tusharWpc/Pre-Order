<?php
/*
Plugin Name: WooCommerce Preorder Button Text 
Description: Change the Add to Cart button text for pre-order products and add pre-order options.
Version: 1.0
Author: Your Name
*/

// Add custom fields to the product editor for pre-order options
function add_preorder_fields() {
    global $woocommerce, $post;

    echo '<div class="options_group">';

    // Checkbox for marking a product as a pre-order
    woocommerce_wp_checkbox( array(
        'id'            => '_is_pre_order',
        'label'         => __( 'Hi, Offer as Pre-order', 'woocommerce' ),
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

    echo '</div>';
}
add_action( 'woocommerce_product_options_general_product_data', 'add_preorder_fields' );

// Hook into WooCommerce to modify the Add to Cart button text
function custom_preorder_button_text($text) {
    global $product;

    // Check if the product is a pre-order
    if ($product && $product->is_type('simple') && 'yes' === get_post_meta($product->get_id(), '_is_pre_order', true)) {
        $text = __('hey, Pre-order Now', 'woocommerce'); // Change the button text for pre-orders
    }

    return $text;
}
add_filter('woocommerce_product_single_add_to_cart_text', 'custom_preorder_button_text', 10, 1);
add_filter('woocommerce_product_add_to_cart_text', 'custom_preorder_button_text', 10, 1);

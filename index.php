<?php
/*
Plugin Name: NF Pre-Order Plugin
Description: Allow users to pre-order products.
Version: 1.0
Author: NF Tushar
*/

namespace Woocommerce_Preorders;

class PreorderPlugin {
    
    public function __construct() {
        add_action( 'woocommerce_product_after_variable_attributes', [$this, 'customVariationsFields'], 10, 3 );
        add_action( 'woocommerce_product_options_stock_status', [$this, 'customSimpleFields'] );
        add_action( 'woocommerce_save_product_variation', [$this, 'customVariationsFieldsSave'], 10, 2 );
        add_action( 'woocommerce_process_product_meta', [$this, 'customSimpleFieldsSave'], 10, 2 );
        add_action( 'admin_enqueue_scripts', [$this, 'enqueue_datepicker'] ); // Enqueue datepicker script

        add_action( 'init', [$this, 'schedule_preorder_products'] );
        add_action( 'init', [$this, 'make_preorder_products_available'] );
        add_action( 'woocommerce_checkout_order_processed', [$this, 'send_admin_email_for_preorder_product'], 10, 1 );
        add_action( 'woocommerce_order_status_completed', [$this, 'send_customer_email_for_preorder_product'], 10, 1 );
    }

    // Enqueue jQuery UI Datepicker
    public function enqueue_datepicker() {
        wp_enqueue_script( 'jquery-ui-datepicker' );
        wp_enqueue_style( 'jquery-ui-datepicker-style', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css' );
    }

    // Schedule pre-order products
    public function schedule_preorder_products() {
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => '_is_preorder',
                    'value'   => 'yes',
                    'compare' => '=',
                ),
            ),
        );

        $preorder_products = new \WP_Query($args);

        if ($preorder_products->have_posts()) {
            while ($preorder_products->have_posts()) {
                $preorder_products->the_post();

                $preorder_available_date = get_post_meta(get_the_ID(), '_preorder_available_date', true);
                $preorder_end_date = get_post_meta(get_the_ID(), '_preorder_end_date', true);

                // Add code here to schedule pre-order products
                // For example, you can update post status to 'wc-preorder' on the available date
                // and update post status to 'publish' on the end date
            }

            wp_reset_postdata();
        }
    }

    // Automatically make pre-order products available when pre-order period ends
    public function make_preorder_products_available() {
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'wc-preorder',
            'meta_query'     => array(
                array(
                    'key'     => '_preorder_end_date',
                    'value'   => current_time('mysql'),
                    'compare' => '<=',
                    'type'    => 'DATETIME',
                ),
            ),
        );

        $preorder_products = new \WP_Query($args);

        if ($preorder_products->have_posts()) {
            while ($preorder_products->have_posts()) {
                $preorder_products->the_post();

                // Add code here to make pre-order products available
                // For example, you can update post status to 'publish'
                // and reset pre-order fields like _is_preorder, _preorder_available_date, _preorder_end_date
            }

            wp_reset_postdata();
        }
    }

    // Send email to admin for pre-order product purchase
    public function send_admin_email_for_preorder_product($order_id) {
        $order = wc_get_order($order_id);
        $items = $order->get_items();

        foreach ($items as $item) {
            $product_id = $item->get_product_id();
            $is_preorder = get_post_meta($product_id, '_is_preorder', true);

            if ($is_preorder === 'yes') {
                // Send email notification to admin
                $admin_email = get_option('admin_email');
                $subject = 'Pre-order Product Purchase Notification';
                $message = 'A pre-order product has been purchased. Order ID: ' . $order_id;
                wp_mail($admin_email, $subject, $message);
                break; // Stop loop after finding one pre-order product
            }
        }
    }

    // Send email to customer for pre-order confirmation
    public function send_customer_email_for_preorder_product($order_id) {
        $order = wc_get_order($order_id);
        $items = $order->get_items();

        foreach ($items as $item) {
            $product_id = $item->get_product_id();
            $is_preorder = get_post_meta($product_id, '_is_preorder', true);

            if ($is_preorder === 'yes') {
                // Send email confirmation to customer
                $customer_email = $order->get_billing_email();
                $subject = 'Pre-order Product Confirmation';
                $message = 'Thank you for your pre-order. Your order ID is: ' . $order_id;
                wp_mail($customer_email, $subject, $message);
                break; // Stop loop after finding one pre-order product
            }
        }
    }

    public function customVariationsFields( $loop, $variation_data, $variation ) {
        echo '<div class="options_group form-row form-row-full">';
        woocommerce_wp_text_input(
            [
                'id'          => '_pre_order_date_' . $variation->ID,
                'label'       => __( 'My Pre Order Date', 'pre-orders-for-woocommerce' ),
                'placeholder' => date( 'Y-m-d h:i:s' ),
                'class'       => 'datepicker',
                'desc_tip'    => true,
                'description' => __( 'Choose when the product will be available.', 'pre-orders-for-woocommerce' ),
                'value'       => get_post_meta( $variation->ID, '_pre_order_date', true ),
            ]
        );
        echo '</div>';
    }

    public function customSimpleFields() {
        echo '<div class="options_group form-row form-row-full hide_if_variable">';
        woocommerce_wp_checkbox(
            [
                'id'          => '_is_preorder',
                'label'       => __( 'My Pre Order Product', 'pre-orders-for-woocommerce' ),
                'description' => __( 'Check this if you want to offer this product as pre-order', 'pre-orders-for-woocommerce' ),
                'value'       => get_post_meta( get_the_ID(), '_is_preorder', true ),
            ]
        );
        woocommerce_wp_text_input(
            [
                'id'          => '_preorder_available_date',
                'label'       => __( 'Pre Order Available Date', 'pre-orders-for-woocommerce' ),
                'placeholder' => date( 'Y-m-d h:i:s' ),
                'class'       => 'datepicker',
                'desc_tip'    => true,
                'description' => __( 'Choose when the product will be available for pre-order.', 'pre-orders-for-woocommerce' ),
                'value'       => get_post_meta( get_the_ID(), '_preorder_available_date', true ),
            ]
        );
        woocommerce_wp_text_input(
            [
                'id'          => '_preorder_end_date',
                'label'       => __( 'Pre Order End Date', 'pre-orders-for-woocommerce' ),
                'placeholder' => date( 'Y-m-d h:i:s' ),
                'class'       => 'datepicker',
                'desc_tip'    => true,
                'description' => __( 'Choose when the pre-order period ends.', 'pre-orders-for-woocommerce' ),
                'value'       => get_post_meta( get_the_ID(), '_preorder_end_date', true ),
            ]
        );
        echo '</div>';
        echo '<div class="options_group form-row form-row-full hide_if_simple"></div>';
    }

    public function customVariationsFieldsSave( $post_id ) {
        $product = wc_get_product( $post_id );
        $is_preorder_variation = isset( $_POST['_is_preorder_' . $post_id] ) ? 'yes' : 'no';
        $product->update_meta_data( '_is_preorder', $is_preorder_variation );
        if ( $is_preorder_variation == 'yes' ) {
            $pre_order_date_value = isset( $_POST['_pre_order_date_' . $post_id] ) ? esc_html( $_POST['_pre_order_date_' . $post_id] ) : '';
            $product->update_meta_data( '_pre_order_date', esc_attr( $pre_order_date_value ) );
        } else {
            $product->update_meta_data( '_pre_order_date', '' );
        }
        $product->save();
    }

    public function customSimpleFieldsSave( $post_id ) {
        $product = wc_get_product( $post_id );
        $is_preorder = isset( $_POST['_is_preorder'] ) ? 'yes' : 'no';
        $product->update_meta_data( '_is_preorder', $is_preorder );
        if ( $is_preorder == 'yes' ) {
            $preorder_available_date = isset( $_POST['_preorder_available_date'] ) ? esc_html( $_POST['_preorder_available_date'] ) : '';
            $product->update_meta_data( '_preorder_available_date', esc_attr( $preorder_available_date ) );

            $preorder_end_date = isset( $_POST['_preorder_end_date'] ) ? esc_html( $_POST['_preorder_end_date'] ) : '';
            $product->update_meta_data( '_preorder_end_date', esc_attr( $preorder_end_date ) );
        } else {
            $product->update_meta_data( '_preorder_available_date', '' );
            $product->update_meta_data( '_preorder_end_date', '' );
        }
        $product->save();
    }

}

new PreorderPlugin();
?>
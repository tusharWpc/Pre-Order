<?php
/*
Plugin Name: NF Pre-Order Plugin
Description: Allow users to pre-order products.
Version: 1.0
Author: NF Tushar
*/

namespace Woocommerce_Preorders;

class PreorderPlugin {

    // Constructor with existing plugin functionality
    public function __construct() {
        // Existing plugin actions...
        add_action( 'admin_menu', [$this, 'nf_preorder_plugin_menu'] );
        add_action( 'admin_init', [$this, 'nf_preorder_plugin_settings'] );

        // Additional actions for product customization and email handling
        add_action( 'woocommerce_product_after_variable_attributes', [$this, 'customVariationsFields'], 10, 3 );
        add_action( 'woocommerce_product_options_stock_status', [$this, 'customSimpleFields'] );
        add_action( 'woocommerce_save_product_variation', [$this, 'customVariationsFieldsSave'], 10, 2 );
        add_action( 'woocommerce_process_product_meta', [$this, 'customSimpleFieldsSave'], 10, 2 );
        add_action( 'admin_enqueue_scripts', [$this, 'enqueue_datepicker'] ); // Enqueue datepicker script

        add_action( 'init', [$this, 'schedule_preorder_products'] );
        add_action( 'init', [$this, 'make_preorder_products_available'] );
        add_action( 'woocommerce_checkout_order_processed', [$this, 'send_admin_email_for_preorder_product'], 10, 1 );
        add_action( 'woocommerce_order_status_completed', [$this, 'send_customer_email_for_preorder_product'], 10, 1 );

        // New action for setting a different fixed price for pre-order products
        add_action( 'woocommerce_before_calculate_totals', [$this, 'set_preorder_product_price'], 10, 1 );
    }

    // Add a menu item for your plugin in the dashboard
    public function nf_preorder_plugin_menu() {
        add_menu_page(
            'NF Pre-Order Plugin Settings',
            'Pre-Order Settings',
            'manage_options',
            'nf-preorder-plugin-settings',
            [$this, 'nf_preorder_plugin_settings_page'],
            'dashicons-cart', // Icon for your menu item, you can choose from WordPress dashicons
            99 // Position of the menu item in the dashboard menu
        );
    }

    // Create the settings page content
    public function nf_preorder_plugin_settings_page() {
        ?>
<div class="wrap">
    <h2>NF Pre-Order Plugin Settings</h2>
    <form method="post" action="options.php">
        <?php
                // Add nonce for security
                settings_fields('nf_preorder_plugin_options');
                // Output your settings fields
                do_settings_sections('nf-preorder-plugin-settings');
                // Add a submit button
                submit_button('Save Settings');
                ?>
    </form>
</div>
<?php
    }

    // Register and initialize plugin settings
    public function nf_preorder_plugin_settings() {
        // Register a new setting for your plugin
        register_setting('nf_preorder_plugin_options', 'nf_preorder_plugin_options');

        // Add a section for your settings
        add_settings_section(
            'nf_preorder_plugin_general_section',
            'General Settings',
            [$this, 'nf_preorder_plugin_general_section_callback'],
            'nf-preorder-plugin-settings'
        );

        // Add fields to the section
        add_settings_field(
            'preorder_email_template',
            'Pre-Order Email Template',
            [$this, 'preorder_email_template_callback'],
            'nf-preorder-plugin-settings',
            'nf_preorder_plugin_general_section'
        );
    }

    // Callback function for the general settings section
    public function nf_preorder_plugin_general_section_callback() {
        echo '<p>Configure general settings for your NF Pre-Order Plugin.</p>';
    }

    // Callback function for the pre-order email template field
    public function preorder_email_template_callback() {
        $options = get_option('nf_preorder_plugin_options');
        $template = isset($options['preorder_email_template']) ? $options['preorder_email_template'] : '';
        echo '<textarea id="preorder_email_template" name="nf_preorder_plugin_options[preorder_email_template]" rows="5" cols="50">' . esc_textarea($template) . '</textarea>';
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
    
                $product_id = get_the_ID();
    
                // Update product status to 'publish' or any other appropriate status
                $product = wc_get_product($product_id);
                $product->set_status('publish');
                $product->save();
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

    // Callback function to add custom fields for variations
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

    // Callback function to add custom fields for simple products
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
                'class'       => 'datepicker', // Add the class 'datepicker'
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
                'class'       => 'datepicker', // Add the class 'datepicker'
                'desc_tip'    => true,
                'description' => __( 'Choose when the pre-order period ends.', 'pre-orders-for-woocommerce' ),
                'value'       => get_post_meta( get_the_ID(), '_preorder_end_date', true ),
            ]
        );

        // Add field for pre-order price
        woocommerce_wp_text_input(
            [
                'id'          => '_preorder_price',
                'label'       => __( 'Pre Order Price', 'pre-orders-for-woocommerce' ),
                'desc_tip'    => true,
                'description' => __( 'Set a different fixed price for pre-order.', 'pre-orders-for-woocommerce' ),
                'value'       => get_post_meta( get_the_ID(), '_preorder_price', true ),
            ]
        );

        echo '</div>';
        echo '<div class="options_group form-row form-row-full hide_if_simple"></div>';
    }
    
    // Enqueue datepicker script
    public function enqueue_datepicker() {
        wp_enqueue_script( 'jquery-ui-datepicker' );
        wp_enqueue_style( 'jquery-ui-datepicker-style', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css' );
        // Add additional script to initialize datepicker
        wp_add_inline_script( 'jquery-ui-datepicker', 'jQuery(function($) { $(".datepicker").datepicker(); });' );
    }
    
    // Save custom fields for variations
    public function customVariationsFieldsSave( $post_id ) {
        $product = wc_get_product( $post_id );
        $is_preorder_variation = isset( $_POST['_is_preorder_' . $post_id] ) ? 'yes' : 'no';
        $product->update_meta_data( '_is_preorder', $is_preorder_variation );
        if ( $is_preorder_variation == 'yes' ) {
            $pre_order_date_value = isset( $_POST['_pre_order_date_' . $post_id] ) ? $_POST['_pre_order_date_' . $post_id] : '';
            $product->update_meta_data( '_pre_order_date', $pre_order_date_value );
        }
        $product->save();
    }

    // Save custom fields for simple products
    public function customSimpleFieldsSave( $post_id ) {
        $product = wc_get_product( $post_id );
        $is_preorder = isset( $_POST['_is_preorder'] ) ? 'yes' : 'no';
        $product->update_meta_data( '_is_preorder', $is_preorder );
        if ( $is_preorder == 'yes' ) {
            $preorder_available_date = isset( $_POST['_preorder_available_date'] ) ? $_POST['_preorder_available_date'] : '';
            $product->update_meta_data( '_preorder_available_date', $preorder_available_date );

            $preorder_end_date = isset( $_POST['_preorder_end_date'] ) ? $_POST['_preorder_end_date'] : '';
            $product->update_meta_data( '_preorder_end_date', $preorder_end_date );

            // Save pre-order price
            $preorder_price = isset( $_POST['_preorder_price'] ) ? $_POST['_preorder_price'] : '';
            $product->update_meta_data( '_preorder_price', $preorder_price );
        }
        $product->save();
    }

    // Set pre-order product price
    public function set_preorder_product_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
            return;
        }

        // Loop through cart items
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $product = $cart_item['data'];

            // Check if the product is a pre-order
            $is_preorder = $product->get_meta( '_is_preorder', true );

            if ( $is_preorder === 'yes' ) {
                // Get the pre-order price
                $preorder_price = $product->get_meta( '_preorder_price', true );

                if ( ! empty( $preorder_price ) ) {
                    // Set the pre-order price for the product
                    $product->set_price( $preorder_price );
                }
            }
        }
    }
}

new PreorderPlugin();
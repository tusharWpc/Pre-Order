<?php
/*
Plugin Name: WooCommerce Preorder Button Text
Description: Change the Add to Cart button text for pre-order products and add pre-order options.
Version: 1.0.3
Author: NF Tushar
*/

class WooCommerce_Preorder_Button_Text {
    public function __construct() {
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_preorder_fields'));
        add_action('admin_footer', array($this, 'show_hide_preorder_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_preorder_fields'));
        add_filter('woocommerce_product_single_add_to_cart_text', array($this, 'custom_preorder_button_text'), 10, 1);
        add_filter('woocommerce_product_add_to_cart_text', array($this, 'custom_preorder_button_text'), 10, 1);
        add_action('wp', array($this, 'schedule_preorder_availability_update'));
        add_action('update_preorder_availability', array($this, 'update_preorder_availability'));
        add_action('woocommerce_before_add_to_cart_form', array($this, 'display_preorder_date_and_time'), 15);
        add_filter('woocommerce_product_get_price', array($this, 'custom_preorder_price'), 10, 2);
        add_filter('woocommerce_product_get_regular_price', array($this, 'custom_preorder_price'), 10, 2);
        add_filter('woocommerce_product_variation_get_regular_price', array($this, 'custom_preorder_price'), 10, 2);
        add_filter('woocommerce_product_variation_get_price', array($this, 'custom_preorder_price'), 10, 2);
    }

    // Add custom fields to the product editor for pre-order options
    public function add_preorder_fields() {
        global $woocommerce, $post;

        echo '<div class="options_group">';
        
        // Checkbox for marking a product as a pre-order
        woocommerce_wp_checkbox(array(
            'id'            => '_is_pre_order',
            'label'         => __('Offer as Pre-order', 'pre-order'),
            'description'   => __('Check this if you want to offer this product as a pre-order.', 'pre-order'),
            'desc_tip'      => true,
        ));

        // Other fields/buttons hidden by default
        $style = 'style="display: none;"';

        if (get_post_meta($post->ID, '_is_pre_order', true) === 'yes') {
            $style = ''; // Show fields/buttons if the product is set as a pre-order
        }

        // Date selection for pre-order products
        echo '<div class="pre-order-fields" ' . $style . '>';
        woocommerce_wp_text_input(array(
            'id'            => '_pre_order_date',
            'label'         => __('Pre-order Date', 'pre-order'),
            'placeholder'   => __('YYYY-MM-DD', 'pre-order'),
            'description'   => __('Select the date when this pre-order will be available.', 'pre-order'),
            'desc_tip'      => true,
            'type'          => 'date',
        ));

        // Time selection for pre-order products
        woocommerce_wp_text_input(array(
            'id'            => '_pre_order_time',
            'label'         => __('Pre-order Time', 'pre-order'),
            'placeholder'   => __('HH:MM', 'pre-order'),
            'description'   => __('Select the time when this pre-order will be available.', 'pre-order'),
            'desc_tip'      => true,
            'type'          => 'time',
        ));

        // Dynamic checkbox for dynamic inventory
        woocommerce_wp_checkbox(array(
            'id'            => '_dynamic_inventory',
            'label'         => __('Dynamic Inventory', 'pre-order'),
            'description'   => __('Check this if you want to enable dynamic inventory for this product.', 'pre-order'),
            'desc_tip'      => true,
        ));

        // Text input for pre-order price
        woocommerce_wp_text_input(array(
            'id'            => '_pre_order_price',
            'label'         => __('Pre-order Price', 'pre-order'),
            'placeholder'   => __('Enter pre-order price', 'pre-order'),
            'description'   => __('Enter the price for this product when it is in pre-order mode.', 'pre-order'),
            'desc_tip'      => true,
            'type'          => 'number',
            'custom_attributes' => array(
                'step' => 'any',
            ),
        ));

        // Text input for pre-order discount
        woocommerce_wp_text_input(array(
            'id'            => '_pre_order_discount',
            'label'         => __('Pre-order Discount', 'pre-order'),
            'placeholder'   => __('Enter pre-order discount', 'pre-order'),
            'description'   => __('Enter the discount for this product during the pre-order period.', 'pre-order'),
            'desc_tip'      => true,
            'type'          => 'number',
            'custom_attributes' => array(
                'step' => 'any',
            ),
        ));

        echo '</div>'; // End .pre-order-fields

        echo '</div>'; // End .options_group
    }

    // Enqueue JavaScript to show/hide fields/buttons based on checkbox state
    public function show_hide_preorder_fields() {
        ?>
<script>
jQuery(document).ready(function($) {
    var checkbox = $('#_is_pre_order');
    var preorderFields = $('.pre-order-fields');

    // Show/hide fields on checkbox change
    checkbox.change(function() {
        if (checkbox.is(':checked')) {
            preorderFields.slideDown();
        } else {
            preorderFields.slideUp();
        }
    });

    // Trigger change event on page load if checkbox is checked
    if (checkbox.is(':checked')) {
        preorderFields.show();
    }
});
</script>
<?php
    }

    // Save custom fields data when the product is saved
    public function save_preorder_fields($post_id) {
        // Validate and sanitize input
        $is_pre_order = isset($_POST['_is_pre_order']) ? 'yes' : 'no';
        update_post_meta($post_id, '_is_pre_order', sanitize_text_field($is_pre_order));

        $pre_order_date = isset($_POST['_pre_order_date']) ? sanitize_text_field($_POST['_pre_order_date']) : '';
        update_post_meta($post_id, '_pre_order_date', $pre_order_date);

        $pre_order_time = isset($_POST['_pre_order_time']) ? sanitize_text_field($_POST['_pre_order_time']) : '';
        update_post_meta($post_id, '_pre_order_time', $pre_order_time);

        $dynamic_inventory = isset($_POST['_dynamic_inventory']) ? 'yes' : 'no';
        update_post_meta($post_id, '_dynamic_inventory', sanitize_text_field($dynamic_inventory));

        $pre_order_price = isset($_POST['_pre_order_price']) ? wc_format_decimal($_POST['_pre_order_price']) : '';
        update_post_meta($post_id, '_pre_order_price', $pre_order_price);

        $pre_order_discount = isset($_POST['_pre_order_discount']) ? wc_format_decimal($_POST['_pre_order_discount']) : '';
        update_post_meta($post_id, '_pre_order_discount', $pre_order_discount);
    }

    // Hook into WooCommerce to modify the Add to Cart button text and handle pre-order price
    public function custom_preorder_button_text($text) {
        global $product;

        if ($product && $product->is_type('simple') && 'yes' === get_post_meta($product->get_id(), '_is_pre_order', true)) {
            $pre_order_price = get_post_meta($product->get_id(), '_pre_order_price', true);
            $pre_order_discount = get_post_meta($product->get_id(), '_pre_order_discount', true);
            
            if ($pre_order_price !== '') {
                $product->set_price($pre_order_price);
                add_filter('woocommerce_get_price_html', array($this, 'custom_preorder_price_html'), 10, 2);
            }
            
            if ($pre_order_discount !== '') {
                $discounted_price = $product->get_regular_price() - ($product->get_regular_price() * $pre_order_discount / 100);
                $product->set_sale_price($discounted_price);
            }
            
            $text = __('Pre-order Now', 'pre-order');
            
            // Add action to send email when pre-order is placed
            add_action('woocommerce_order_status_pending_to_processing_notification', array($this, 'send_preorder_confirmation_email'), 10, 2);
        }

        return $text;
    }

    // Display the date and time under the pre-order button
    public function display_preorder_date_and_time() {
        global $product;

        if ($product && $product->is_type('simple') && 'yes' === get_post_meta($product->get_id(), '_is_pre_order', true)) {
            $pre_order_date = get_post_meta($product->get_id(), '_pre_order_date', true);
            $pre_order_time = get_post_meta($product->get_id(), '_pre_order_time', true);

            if ($pre_order_date && $pre_order_time) {
                echo '<div class="preorder-availability">';
                echo '<strong>Pre-order Available on:</strong> ' . date_i18n(get_option('date_format'), strtotime($pre_order_date)) . ' at ' . date_i18n(get_option('time_format'), strtotime($pre_order_time));
                echo '</div>';
            }
        }
    }

    // Modify the product price for pre-order products
    public function custom_preorder_price($price, $product) {
        // Check if the product is marked as a pre-order
        if ('yes' === get_post_meta($product->get_id(), '_is_pre_order', true)) {
            $pre_order_price = get_post_meta($product->get_id(), '_pre_order_price', true);
            
            // If a pre-order price is set, use it
            if (!empty($pre_order_price)) {
                $price = $pre_order_price;
            }
        }

        return $price;
    }

    
// Modify the product price HTML for pre-order products
public function custom_preorder_price_html($price, $product) {
    $pre_order_price = get_post_meta($product->get_id(), '_pre_order_price', true);
    $pre_order_discount = get_post_meta($product->get_id(), '_pre_order_discount', true);
    
    if ($pre_order_price !== '') {
        if ($pre_order_discount !== '') {
            $discounted_price = $pre_order_price - ($pre_order_price * $pre_order_discount / 100);
            $discount_price_html = wc_price($discounted_price);
            $price_html = wc_price($pre_order_price);
            
            // Combine pre-order price and discount price HTML
            $price = sprintf(
                __('Pre-order Price: %s <br> Discounted Price: %s', 'pre-order'),
                $price_html,
                $discount_price_html
            );
        } else {
            // Display only pre-order price if no discount is set
            $price = wc_price($pre_order_price);
        }
        
        // Add small text indicating pre-order price
        $price .= ' <small class="preorder-text">' . __('(Pre-order Price)', 'pre-order') . '</small>';
    }
    
    return $price;
}



    // Schedule a task to update product availability when pre-order period ends
    public function schedule_preorder_availability_update() {
        if (!wp_next_scheduled('update_preorder_availability')) {
            wp_schedule_event(time(), 'daily', 'update_preorder_availability');
        }
    }

    // Callback function to update product availability
    public function update_preorder_availability() {
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

                if (strtotime($pre_order_date) < time()) {
                    update_post_meta($product_id, '_is_pre_order', 'no');
                }
            }
            wp_reset_postdata();
        }
    }

    // Send pre-order confirmation email to customer
    public function send_preorder_confirmation_email($order_id, $order) {
        // Check if the order contains pre-order products
        $preorder_products = false;
        foreach ($order->get_items() as $item) {
            if ('yes' === get_post_meta($item->get_product_id(), '_is_pre_order', true)) {
                $preorder_products = true;
                break;
            }
        }

        if ($preorder_products) {
            // Get customer email
            $email = $order->get_billing_email();
            
            // Email subject
            $subject = __('Pre-order Confirmation', 'pre-order');
            
            // Email body
            $message = __('Thank you for placing a pre-order. Your order will be processed as soon as the product becomes available.', 'pre-order');
            
            // Send email
            wp_mail($email, $subject, $message);
        }
    }
}

new WooCommerce_Preorder_Button_Text();
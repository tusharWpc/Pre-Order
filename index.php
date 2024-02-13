<?php
/*
Plugin Name: NF Pre-Order Plugin
Description: Allow users to pre-order products.
Version: 1.0
Author: NF Tushar
*/ 

// Namespace declaration - Remove if not using namespaces
namespace Woocommerce_Preorders;

class Tabs {

    public function __construct() {
        // Variations tab
        add_action( 'woocommerce_product_after_variable_attributes', [$this, 'customVariationsFields'], 10, 3 ); // After all Variation fields

        // Inventory tab
        add_action( 'woocommerce_product_options_stock_status', [$this, 'customSimpleFields'] );

        add_action( 'woocommerce_save_product_variation', [$this, 'customVariationsFieldsSave'], 10, 2 );
        add_action( 'woocommerce_process_product_meta', [$this, 'customSimpleFieldsSave'], 10, 2 );
    }

    /**
     * Add our Custom Fields to variable products
     * @param $loop
     * @param $variation_data
     * @param $variation
     */
    public function customVariationsFields( $loop, $variation_data, $variation ) {
        echo '<div class="options_group form-row form-row-full">';
        woocommerce_wp_checkbox(
            [
                'id'          => '_is_pre_order_' . $variation->ID,
                'label'       => __( '29 no line Pre Order Product', 'pre-orders-for-woocommerce' ),
                'description' => __( ' Check this if you want to offer this product as pre-order', 'pre-orders-for-woocommerce' ),
                'value'       => get_post_meta( $variation->ID, '_is_pre_order', true ),
                'input_class' => 'wceazy-preorder-checkbox', // Add a class to identify the checkbox
            ]
        );

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

    /**
     * Save our variable product fields
     *
     * @param $post_id
     */
    public function customVariationsFieldsSave( $post_id ) {
        $product = wc_get_product( $post_id );

        $is_pre_order_variation = isset( $_POST['_is_my_pre_order_' . $post_id] ) ? 'yes' : 'no';
        $product->update_meta_data( '_is_pre_order', $is_pre_order_variation );

        if ( $is_pre_order_variation == 'yes' ) {
            $pre_order_date_value = esc_html( $_POST['_pre_order_date_' . $post_id] );
            $product->update_meta_data( '_pre_order_date', esc_attr( $pre_order_date_value ) );
        }

        $product->save();
    }

    public function customSimpleFields() {
        echo '<div class="options_group form-row form-row-full hide_if_variable">';
        woocommerce_wp_checkbox(
            [
                'id'          => '_is_pre_order',
                'label'       => __( 'NF Pre Order Product', 'pre-orders-for-woocommerce' ),
                'description' => __( ' Check this if you want to offer this product as pre-order', 'pre-orders-for-woocommerce' ),
                'value'       => get_post_meta( get_the_ID(), '_is_pre_order', true ),
                'input_class' => 'wceazy-preorder-checkbox', // Add a class to identify the checkbox
            ]
        );

        woocommerce_wp_text_input(
            array(
                'id'          => '_pre_order_date',
                'label'       => __( 'Pre Order Date', 'pre-orders-for-woocommerce' ),
                'placeholder' => date( 'Y-m-d h:i:s' ),
                'class'       => 'datepicker',
                'desc_tip'    => true,
                'description' => __( "Choose when the product will be available.", "preorders" ),
                'value'       => get_post_meta( get_the_ID(), '_pre_order_date', true ),
            )
        );

        echo '</div>';
        echo '<div class="options_group form-row form-row-full hide_if_simple">';
        ?>
        <div class="preorder-variable-notice notice-info">
            <p>Pre-Order options are available under each variation <a
                    href="https://brightplugins.com/docs/how-to-add-preorder-feature-into-a-variable-product/"
                    target="_blank">Read More</a></p>
        </div>
        <?php

        echo '</div>';
    }

    /**
     * @param $post_id
     */
    public function customSimpleFieldsSave( $post_id ) {
        $product      = wc_get_product( $post_id );
        $is_pre_order = isset( $_POST['_is_pre_order'] ) ? 'yes' : 'no';
        $product->update_meta_data( '_is_pre_order', $is_pre_order );

        if ( $is_pre_order == 'yes' ) {
            $pre_order_date_value = esc_html( $_POST['_pre_order_date'] );
            $product->update_meta_data( '_pre_order_date', esc_attr( $pre_order_date_value ) );
        } else {
            $product->update_meta_data( '_pre_order_date', '' );
        }

        $product->save();
    }
}

// Initialize the class
new Tabs();

?>
<script>
function wceazy_update_module_status(checkbox) {
    // Check if the checkbox is checked
    var isChecked = checkbox.checked;

    // Perform your actions based on the checkbox status
    if (isChecked) {
        // Checkbox is checked
        // Perform your desired actions here
    } else {
        // Checkbox is unchecked
        // Perform your desired actions here
    }
}
</script>

<?php
/**
 * Plugin Name:       Product Price Markup for Woocommece
 * Description:       Woocommerce product backend page add markup(%) field in which add number which you want to increase price of product using plugin.
 * Version:           1.0.0
 * Requires at least: 4.0
 * Requires PHP:      4.0
 * Author:            Brightness Group
 * Author URI:        https://www.brightness-group.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       markup

Markup Price Plugin is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.
 
Markup Price Plugin is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License
along with Markup Price Plugin. If not, see {License URI}.
 */

register_activation_hook( __FILE__, 'markup_install' );
function markup_install() {
    if (!is_plugin_active('woocommerce/woocommerce.php') )
    {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die( 'VDB Woocommerce Plugin requires Woocommerce Plugin so please install first woocommerce and after it active Product Price Markup for Woocommece Plugin.' );
    }
}


add_action( 'add_meta_boxes', 'markup_add_meta_boxes' );
if ( ! function_exists( 'markup_add_meta_boxes' ) )
{
    function markup_add_meta_boxes()
    {
        add_meta_box( 'mv_other_fields', __('Markup Addition Field','markup'), 'markup_add_fields', 'product', 'side', 'core' );
    }
}

// Adding Meta field in the meta container admin shop_order pages
if ( ! function_exists( 'markup_add_fields' ) )
{
    function markup_add_fields()
    {
        global $post;

        $meta_field_data = get_post_meta( $post->ID, '_markup', true ) ? get_post_meta( $post->ID, '_markup', true ) : '';
        _e('Markup Addition Price (%)','markup');
        echo '<input type="hidden" name="markup_field_nonce" value="' . wp_create_nonce() . '">
        <p style="border-bottom:solid 1px #eee;padding-bottom:13px;">
            <input type="number" style="width:250px;";" name="markup" value="' . $meta_field_data . '"></p>';

    }
}

// Save the data of the Meta field
add_action( 'save_post', 'markup_save_wc_markup_fields', 10, 1 );
if ( ! function_exists( 'markup_save_wc_markup_fields' ) )
{

    function markup_save_wc_markup_fields( $post_id ) {

        // We need to verify this with the proper authorization (security stuff).

        // Check if our nonce is set.
        if ( ! isset( $_POST[ 'markup_field_nonce' ] ) ) {
            return $post_id;
        }
        $nonce = $_REQUEST[ 'markup_field_nonce' ];

        //Verify that the nonce is valid.
        if ( ! wp_verify_nonce( $nonce ) ) {
            return $post_id;
        }

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $post_id;
        }

        // Check the user's permissions.
        if ( 'page' == $_POST[ 'post_type' ] ) {

            if ( ! current_user_can( 'edit_page', $post_id ) ) {
                return $post_id;
            }
        } else {

            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return $post_id;
            }
        }
        // --- Its safe for us to save the data ! --- //

        // Sanitize user input  and update the meta field in the database.
        update_post_meta( $post_id, '_markup', sanitize_text_field($_POST[ 'markup' ]) );
    }
}

function markup_price_multiplier() {
    global $product;
    $product_slug = $product->slug;
    $product_array = get_page_by_path( $product_slug, 'ARRAY_N', 'product' );
    $product_id = $product_array[0];
    $markup = (int)get_post_meta( $product_id, '_markup', true );
    if($markup){
        $percentage = $markup/100;
    } else {
        $percentage = 0;
    }
    //echo '<pre>';var_dump($markup);
    //exit;
    return 1+$percentage; // x2 for testing
}

// Simple, grouped and external products
add_filter('woocommerce_product_get_price', 'markup_custom_price', 99, 2 );
add_filter('woocommerce_product_get_regular_price', 'markup_custom_price', 99, 2 );
// Variations
add_filter('woocommerce_product_variation_get_regular_price', 'markup_custom_price', 99, 2 );
add_filter('woocommerce_product_variation_get_price', 'markup_custom_price', 99, 2 );
function markup_custom_price( $price, $product ) {
    $markup_price = markup_price_multiplier();
    if(is_numeric($price) && is_numeric($markup_price)){
        return $price * $markup_price;
    } 
}

// Variable (price range)
add_filter('woocommerce_variation_prices_price', 'markup_variable_price', 99, 3 );
add_filter('woocommerce_variation_prices_regular_price', 'markup_variable_price', 99, 3 );
function markup_variable_price( $price, $variation, $product ) {
    $markup_price = markup_price_multiplier();
    if(is_numeric($price) && is_numeric($markup_price)){
        return $price * $markup_price;
    } 
}

// Handling price caching (see explanations at the end)
add_filter( 'woocommerce_get_variation_prices_hash', 'markup_multiplier_to_variation_prices_hash', 99, 1 );
function markup_multiplier_to_variation_prices_hash( $hash ) {
    $hash[] = markup_price_multiplier();
    return $hash;
}
add_action( 'woocommerce_before_calculate_totals', 'markup_recalc_price' );
 
function markup_recalc_price( $cart_object ) {
    foreach ( $cart_object->get_cart() as $hash => $value ) {
        $product_id = $value['product_id'];
        $markup = (int)get_post_meta( $product_id, '_markup', true );
        if($markup){
            $percentage = $markup/100;
        } else {
            $percentage = 0;
        }
        $value['data']->set_price($value['data']->get_regular_price() *(1+$percentage));
    }
}

?>
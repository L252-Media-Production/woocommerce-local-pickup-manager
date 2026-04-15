<?php
defined( 'ABSPATH' ) || exit;

class GNYC_Pickup_Post_Types {

    public function __construct() {
        add_action( 'init', [ $this, 'register_pickup_location_cpt' ] );
    }

    public function register_pickup_location_cpt() {
        register_post_type( 'pickup_location', [
            'labels' => [
                'name'               => 'Pickup Locations',
                'singular_name'      => 'Pickup Location',
                'add_new'            => 'Add New Location',
                'add_new_item'       => 'Add New Pickup Location',
                'edit_item'          => 'Edit Pickup Location',
                'view_item'          => 'View Pickup Location',
                'search_items'       => 'Search Pickup Locations',
                'not_found'          => 'No pickup locations found',
                'not_found_in_trash' => 'No pickup locations found in trash',
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => 'woocommerce',
            'menu_icon'    => 'dashicons-location',
            'supports'     => [ 'title' ],
            'show_in_rest' => false,
        ] );
    }
}

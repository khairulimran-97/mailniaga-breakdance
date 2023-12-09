<?php
function fetch_mailniaga_lists() {
      $api_key = get_option( 'mailniaga_api_key' );
    if ( ! $api_key ) {
        return;
    }

    $response = wp_remote_get( 'https://manage.mailniaga.com/api/v1/lists?api_token=' . $api_key );

    if( is_wp_error( $response ) ) {
        return;
    }

    $lists = json_decode( wp_remote_retrieve_body( $response ), true );

    $options = [];
    foreach( $lists as $list ) {
        $options[ $list['uid'] ] = $list['name'];
    }

    update_option( 'mailniaga_lists', $options );
}
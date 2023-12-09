<?php
function enqueue_bootstrap($hook) {
    if( 'toplevel_page_mailniaga-settings' != $hook ) {
        return;
    }
    wp_enqueue_style( 'bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css' );
}
add_action( 'admin_enqueue_scripts', 'enqueue_bootstrap' );

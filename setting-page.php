<?php

/**
 * Register the settings page for this plugin.
 *
 * @since 1.0.0
 */

function mailniaga_settings_page() {
    $icon_url = plugin_dir_url( __FILE__ ) . 'mailniaga.png';
    add_menu_page(
        'MailNiaga Settings',
        'MailNiaga',
        'manage_options',
        'mailniaga-settings',
        'mailniaga_settings_page_content',
        $icon_url,
        3
    );
}
add_action( 'admin_menu', 'mailniaga_settings_page' );


/**
 * Render the settings page for this plugin.
 *
 * @since 1.0.0
 */
 
function mailniaga_settings_page_content() {
      if ( isset( $_POST['refresh_lists'] ) ) {
        fetch_mailniaga_lists();
    }
    settings_errors( 'mailniaga_api_key' );
    $mailniaga_lists = get_option( 'mailniaga_lists', [] );
    ?>
    <div class="wrap">
        <h2 class="mb-4">MailNiaga v2 Settings</h2>
        <form method="post" action="options.php" class="mb-4">
            <?php
            settings_fields( 'mailniaga-settings-group' );
            do_settings_sections( 'mailniaga-settings-group' );
            ?>
            <div class="form-group">
                <label for="mailniaga_api_key">API Key</label>
                <input type="text" class="form-control" id="mailniaga_api_key" name="mailniaga_api_key" value="<?php echo esc_attr( get_option( 'mailniaga_api_key' ) ); ?>" />
                <small class="form-text text-muted">Enter your MailNiaga API key here. Get it from <a href="https://manage.mailniaga.com/account/api" target="_blank" >https://manage.mailniaga.com/account/api</a></small>
            </div>
            <?php submit_button( 'Save Changes', 'primary', 'submit', true, array( 'class' => 'btn btn-primary' ) ); ?>
        </form>
        <form method="post" class="mb-4">
            <input type="submit" name="refresh_lists" class="btn btn-secondary" value="Refresh Lists" />
        </form>
        <!-- Display MailNiaga lists -->
        <h2 class="mb-4">Current MailNiaga Lists</h2>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>List ID</th>
                    <th>List Name</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach( $mailniaga_lists as $list_id => $list_name ): ?>
                <tr>
                    <td><?php echo esc_html( $list_id ); ?></td>
                    <td><?php echo esc_html( $list_name ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}


/**
 * Register the setting to store the API key.
 *
 * @since 1.0.0
 */
 
function mailniaga_register_settings() {
    register_setting( 'mailniaga-settings-group', 'mailniaga_api_key', 'mailniaga_validate_api_key' );
}
add_action( 'admin_init', 'mailniaga_register_settings' );


/**
 * Validate the API key.
 *
 * @since 1.0.0
 */

function mailniaga_validate_api_key( $input ) {
     // Get the old API key
    $old_api_key = get_option( 'mailniaga_api_key' );

    // Check if the API key is empty.
    if ( empty( $input ) ) {
        // Add an error message to the queue.
        add_settings_error( 'mailniaga_api_key', 'mailniaga_api_key_error', 'Error: API key cannot be empty.', 'error' );
        return $old_api_key;
    }

    // Fetch the MailNiaga lists
    fetch_mailniaga_lists(); // Fetch lists right after the key is validated and before it is stored

    // Add a success message to the queue.
    add_settings_error( 'mailniaga_api_key', 'mailniaga_api_key_updated', 'API key updated successfully. Lists refreshed.', 'updated' );
    return $input;
}

function check_and_fetch_mailniaga_lists() {
    if ( isset( $_REQUEST['settings-updated'] ) && $_REQUEST['settings-updated'] ) {
        fetch_mailniaga_lists();
    }
}
add_action( 'admin_init', 'check_and_fetch_mailniaga_lists' );
register_activation_hook( __FILE__, 'fetch_mailniaga_lists' );

add_action('admin_init', 'fetch_mailniaga_lists');

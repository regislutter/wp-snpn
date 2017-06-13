<?php
/**
 * Plugin Name: Simple New Post Notification
 * Plugin URI: http://zetura.fr/projects-and-more/simple-new-post-notification-wordpress-plugin
 * Description: Send notifications to your users when you publish a new post
 * Version: 1.0.0
 * Author: Régis Lutter
 * Author URI: http://zetura.fr
 * License: GPL2
 */

if (!function_exists('write_log')) {
    function write_log ( $log )  {
        if ( true === WP_DEBUG ) {
            if ( is_array( $log ) || is_object( $log ) ) {
                error_log( print_r( $log, true ), 3, "intranet.log" );
            } else {
                error_log( $log, 3, "intranet.log" );
            }
        }
    }
}

/**
 * Add meta box to the post
 */
add_action( 'load-post.php', 'snpn_post_meta_boxes_setup' );
add_action( 'load-post-new.php', 'snpn_post_meta_boxes_setup' );

/* Meta box setup function. */
function snpn_post_meta_boxes_setup() {
    global $current_user;
    if($current_user->roles[0] == 'administrator' || $current_user->roles[0] == 'editor') {
        /* Add meta boxes on the 'add_meta_boxes' hook. */
        add_action('add_meta_boxes', 'snpn_add_post_meta_boxes');

        /* Save post meta on the 'save_post' hook. */
        // add_action( 'save_post', 'snpn_save_post_notification_meta', 10, 2 );
    }
}

/* Create one or more meta boxes to be displayed on the post editor screen. */
function snpn_add_post_meta_boxes() {
    write_log('Display meta box');

    add_meta_box(
        'snpn-post-notification',      // Unique ID
        esc_html__( 'Simple New Post Notification', 'snpn-plugin' ),    // Title
        'snpn_post_notification_meta_box',   // Callback function
        'post',         // Admin page (or post type)
        'side',         // Context
        'default'         // Priority
    );
}

/* Display the post meta box. */
function snpn_post_notification_meta_box( $object, $box ) { ?>
    <?php wp_nonce_field( basename( __FILE__ ), 'snpn_post_notification_nonce' ); ?>

    <p>
        <input class="widefat" type="checkbox" name="snpn-post-notification" id="snpn-post-notification" value="true" <?php if(get_post_meta( $object->ID, 'snpn_post_notification', true )){ echo('checked="checked"'); } ?> /><label for="snpn-post-notification"><?php _e( "Send an email notification to all users.", 'snpn-plugin' ); ?></label>
    </p>
<?php }

/* Save the meta box's post metadata. */
function snpn_save_post_notification_meta( $post_id, $post ) {

    /* Verify the nonce before proceeding. */
    if ( !isset( $_POST['snpn_post_notification_nonce'] ) || !wp_verify_nonce( $_POST['snpn_post_notification_nonce'], basename( __FILE__ ) ) )
        return $post_id;

    /* Get the post type object. */
    $post_type = get_post_type_object( $post->post_type );

    /* Check if the current user has permission to edit the post. */
    if ( !current_user_can( $post_type->cap->edit_post, $post_id ) )
        return $post_id;

    /* Get the posted data and sanitize it for use as an HTML class. */
    $new_meta_value = ( isset( $_POST['snpn-post-notification'] ) ? sanitize_html_class( $_POST['snpn-post-notification'] ) : '' );

    /* Get the meta key. */
    $meta_key = 'snpn_post_notification';

    /* Get the meta value of the custom field key. */
    $meta_value = get_post_meta( $post_id, $meta_key, true );

    /* If a new meta value was added and there was no previous value, add it. */
    if ( $new_meta_value && '' == $meta_value )
        add_post_meta( $post_id, $meta_key, $new_meta_value, true );

    /* If the new meta value does not match the old value, update it. */
    elseif ( $new_meta_value && $new_meta_value != $meta_value )
        update_post_meta( $post_id, $meta_key, $new_meta_value );

    /* If there is no new meta value but an old value exists, delete it. */
    elseif ( '' == $new_meta_value && $meta_value )
        delete_post_meta( $post_id, $meta_key, $meta_value );
}

/**
 * Send a notification when a post is published
 *
 * @param $ID Post ID
 * @param $post Post object
 */
function post_published_notification( $ID, $post ) {
    write_log('Post published');

    // If the option is checked in the post
    if(isset( $_POST['snpn-post-notification'] ) && sanitize_html_class( $_POST['snpn-post-notification'] ) == 'true') {
    // if(get_post_meta($ID, 'snpn_post_notification', true )){
        write_log('Send mail');

        $title = $post->post_title;
        $permalink = get_permalink( $ID );

        if(get_option('snpn_to') && !empty(get_option('snpn_to'))){
            $to = get_option('snpn_to'); //sprintf( '%s <%s>', $name, $email );
        } else {
            $to[] = array_filter(get_users(['orderby' => 'display_name']), function($user){
                return (!get_user_meta($user->ID, 'adi_user_disabled', true) && isset($user->user_email) && !empty($user->user_email));
            });
        }

        // Send to debug email if debug mode is checked
        if(get_option('snpn_debug') && get_option('snpn_debug_email')){
            $to = get_option('snpn_debug_email');
        }

        // Title
        if(get_option('snpn_mail_title')) {
            $subject = sprintf( get_option('snpn_mail_title') . ' %s', $title );
        } else {
            $subject = sprintf( 'New post : %s', $title );
        }

        // Message
        if(get_option('snpn_mail_message')) {
            $message = sprintf( get_option('snpn_mail_message') . "\n\n", $title );
        } else {
            $message = sprintf( 'A new post has been published : “%s”' . "\n\n", $title );
        }

        // Permalink
        if(get_option('snpn_mail_permalink')) {
            $message .= sprintf( get_option('snpn_mail_message') . ' %s', $permalink );
        } else {
            $message .= sprintf( 'See the post : %s', $permalink );
        }

        $headers[] = '';

        wp_mail( $to, $subject, $message, $headers );
    }
}
add_action( 'publish_post', 'post_published_notification', 10, 2 );

function mailer_config(PHPMailer $mailer){
    write_log('Configure Mailer');

    $mailer->isSMTP();
    $mailer->Host = get_option('snpn_host');                    // your SMTP server
    $mailer->SMTPDebug = 0;                                     // write 0 if you don't want to see client/server communication in page
    $mailer->CharSet  = "utf-8";
    if(get_option('snpn_smtp_auth')) {
        $mailer->SMTPAuth = true;                               // Enable SMTP authentication
        $mailer->Username = get_option('snpn_username');        // SMTP username
        $mailer->Password = get_option('snpn_password');        // SMTP password
    }
    $opt_secure = get_option('snpn_secure');
    if($opt_secure) {
        $mailer->SMTPSecure = $opt_secure;                      // Enable TLS or SSL encryption
    }
    $mailer->Port = get_option('snpn_port') ? get_option('snpn_port') : 587;    // TCP port to connect to
    $mailer->setFrom(get_option('snpn_from'), 'Mailer');                        // Email sender
    $mailer->isHTML(get_option('snpn_html'));                                   // Set email format to HTML

    if(get_option('snpn_debug')){
        $mailer->SMTPDebug = 2; // write 0 if you don't want to see client/server communication in page
    }
}
add_action( 'phpmailer_init', 'mailer_config', 10, 1);

/**
 * Plugin settings page
 */
add_action('admin_menu', 'snpn_menu');

function snpn_menu() {
//    add_menu_page('Simple New Post Notification', 'New post notification', 'administrator', 'snpn-plugin', 'snpn_settings_page', 'dashicons-email-alt');
    add_options_page('Simple New Post Notification', 'New post notification', 'administrator', 'snpn-plugin', 'snpn_settings_page');
}

function snpn_settings_page() { ?>
    <div class="wrap">
        <h2><?php _e( 'Email texts', 'snpn-plugin' ) ?></h2>

        <form method="post" action="options.php">
            <?php settings_fields( 'snpn-settings-group' ); ?>
            <?php do_settings_sections( 'snpn-settings-group' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Title for new post email</th>
                    <td><input type="text" name="snpn_mail_title" placeholder="Email title" value="<?php echo esc_attr( get_option('snpn_mail_title') ); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Message for new post email</th>
                    <td><input type="text" name="snpn_mail_message" placeholder="Email message" value="<?php echo esc_attr( get_option('snpn_mail_message') ); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Text for the permalink in the email</th>
                    <td><input type="text" name="snpn_mail_permalink" placeholder="Permalink text" value="<?php echo esc_attr( get_option('snpn_mail_permalink') ); ?>" /></td>
                </tr>
            </table>

            <?php submit_button(); ?>

        </form>

        <h2><?php _e( 'Mailer configuration', 'snpn-plugin' ) ?></h2>

        <form method="post" action="options.php">
            <?php settings_fields( 'snpn-settings-group' ); ?>
            <?php do_settings_sections( 'snpn-settings-group' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">SMTP server host</th>
                    <td><input type="text" name="snpn_host" placeholder="smtp.yourserver.com" value="<?php echo esc_attr( get_option('snpn_host') ); ?>" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">TCP port to connect to (587)</th>
                    <td><input type="input" name="snpn_port" placeholder="587" value="<?php echo( get_option('snpn_port') ? esc_attr(get_option('snpn_port')) : '587' ); ?>" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Use SMTP authentication</th>
                    <td><input type="checkbox" name="snpn_smtp_auth" value="true" <?php if(get_option('snpn_smtp_auth')){ echo('checked="checked"'); } ?> /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">SMTP username (used if SMTP authentication is checked)</th>
                    <td><input type="text" name="snpn_username" placeholder="user@yourserver.com" value="<?php echo esc_attr( get_option('snpn_username') ); ?>" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">SMTP password (used if SMTP authentication is checked)</th>
                    <td><input type="password" name="snpn_password" value="<?php echo esc_attr( get_option('snpn_password') ); ?>" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Secured connexion</th>
                    <?php $opt_secure = get_option('snpn_secure'); ?>
                    <td><input type="radio" name="snpn_secure" value="" <?php if(!isset($opt_secure) || $opt_secure == ''){ echo('checked="checked"'); } ?> /> Disabled
                        <input type="radio" name="snpn_secure" value="tls" <?php if($opt_secure == 'tls'){ echo('checked="checked"'); } ?> /> TLS
                        <input type="radio" name="snpn_secure" value="ssl" <?php if($opt_secure == 'ssl'){ echo('checked="checked"'); } ?> /> SSL</td>
                </tr>

                <tr valign="top">
                    <th scope="row">Send email to (leave empty to send to all users)</th>
                    <td><input type="email" name="snpn_to" placeholder="distribution-list@yourserver.com" value="<?php echo esc_attr( get_option('snpn_to') ); ?>" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Email of the sender</th>
                    <td><input type="email" name="snpn_from" placeholder="service@yourserver.com" value="<?php echo esc_attr( get_option('snpn_from') ); ?>" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Format mail in HTML</th>
                    <td><input type="checkbox" name="snpn_html" value="true" <?php if(get_option('snpn_html')){ echo('checked="checked"'); } ?> /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Debug mode</th>
                    <td><input type="checkbox" name="snpn_debug" value="true" <?php if(get_option('snpn_debug')){ echo('checked="checked"'); } ?> /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Debug email</th>
                    <td><input type="email" name="snpn_debug_email" placeholder="developer@yourserver.com" value="<?php echo esc_attr( get_option('snpn_debug_email') ); ?>" /></td>
                </tr>
            </table>

            <?php submit_button(); ?>

        </form>
    </div>
<?php }

function snpn_settings() {
    register_setting( 'snpn-settings-group', 'snpn_host' );
    register_setting( 'snpn-settings-group', 'snpn_port' );
    register_setting( 'snpn-settings-group', 'snpn_smtp_auth' );
    register_setting( 'snpn-settings-group', 'snpn_username' );
    register_setting( 'snpn-settings-group', 'snpn_password' );
    register_setting( 'snpn-settings-group', 'snpn_secure' );
    register_setting( 'snpn-settings-group', 'snpn_to' );
    register_setting( 'snpn-settings-group', 'snpn_from' );
    register_setting( 'snpn-settings-group', 'snpn_html' );
    register_setting( 'snpn-settings-group', 'snpn_debug' );
    register_setting( 'snpn-settings-group', 'snpn_debug_email' );
}
add_action( 'admin_init', 'snpn_settings' );

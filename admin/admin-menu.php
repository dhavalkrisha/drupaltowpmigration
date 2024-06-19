<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

add_action( 'admin_menu', 'drupal_importer_admin_menu' );

function drupal_importer_admin_menu() {
    add_menu_page(
        'Drupal Importer',
        'Drupal Importer',
        'manage_options',
        'drupal-importer',
        'drupal_importer_settings_page'
    );
}

function drupal_importer_settings_page() {
    ?>
    <div class="wrap">
        <h1>Drupal to WordPress Importer</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'drupal_importer_settings' );
            do_settings_sections( 'drupal_importer' );
            submit_button();
            ?>
        </form>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="drupal_import_posts">
            <input type="submit" class="button button-primary" value="Import Posts from Drupal">
        </form>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="drupal_import_pages">
            <input type="submit" class="button button-primary" value="Import Pages from Drupal">
        </form>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="drupal_import_users">
            <input type="submit" class="button button-primary" value="Import Users from Drupal">
        </form>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="drupal_import_taxonomies">
            <input type="submit" class="button button-primary" value="Import Taxonomies from Drupal">
        </form>
    </div>
    <?php
}

add_action( 'admin_init', 'drupal_importer_settings_init' );

function drupal_importer_settings_init() {
    register_setting( 'drupal_importer_settings', 'drupal_importer_settings' );

    add_settings_section(
        'drupal_importer_section',
        'Drupal Database Settings',
        'drupal_importer_section_callback',
        'drupal_importer'
    );

    add_settings_field(
        'drupal_host',
        'Drupal Database Host',
        'drupal_importer_field_callback',
        'drupal_importer',
        'drupal_importer_section',
        array( 'label_for' => 'drupal_host' )
    );

    add_settings_field(
        'drupal_db',
        'Drupal Database Name',
        'drupal_importer_field_callback',
        'drupal_importer',
        'drupal_importer_section',
        array( 'label_for' => 'drupal_db' )
    );

    add_settings_field(
        'drupal_user',
        'Drupal Database User',
        'drupal_importer_field_callback',
        'drupal_importer',
        'drupal_importer_section',
        array( 'label_for' => 'drupal_user' )
    );

    add_settings_field(
        'drupal_pass',
        'Drupal Database Password',
        'drupal_importer_field_callback',
        'drupal_importer',
        'drupal_importer_section',
        array( 'label_for' => 'drupal_pass' )
    );

    add_settings_field(
        'drupal_site_url',
        'Drupal Site URL',
        'drupal_importer_field_callback',
        'drupal_importer',
        'drupal_importer_section',
        array( 'label_for' => 'drupal_site_url' )
    );

    add_settings_field(
        'drupal_media_path',
        'Drupal Media Path',
        'drupal_importer_field_callback',
        'drupal_importer',
        'drupal_importer_section',
        array( 'label_for' => 'drupal_media_path' )
    );

    add_settings_field(
        'drupal_table_prefix',
        'Drupal Table Prefix',
        'drupal_importer_field_callback',
        'drupal_importer',
        'drupal_importer_section',
        array( 'label_for' => 'drupal_table_prefix' )
    );
}

function drupal_importer_section_callback() {
    echo '<p>Enter your Drupal database credentials and settings.</p>';
}

function drupal_importer_field_callback( $args ) {
    $options = get_option( 'drupal_importer_settings' );
    ?>
    <input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="drupal_importer_settings[<?php echo esc_attr( $args['label_for'] ); ?>]" value="<?php echo esc_attr( $options[ $args['label_for'] ] ); ?>">
    <?php
}

add_action('admin_post_drupal_import_posts', 'drupal_importer_handle_post_import');
add_action('admin_post_drupal_import_pages', 'drupal_importer_handle_page_import');
add_action('admin_post_drupal_import_users', 'drupal_importer_handle_user_import');
add_action('admin_post_drupal_import_taxonomies', 'drupal_importer_handle_taxonomy_import');

function drupal_importer_handle_post_import() {
    $options = get_option( 'drupal_importer_settings' );

    if ( !empty($options['drupal_host']) && !empty($options['drupal_db']) && !empty($options['drupal_user']) && !empty($options['drupal_pass']) && !empty($options['drupal_site_url']) && !empty($options['drupal_media_path'])  ) {
        $importer = new Drupal_Importer( $options['drupal_host'], $options['drupal_db'], $options['drupal_user'], $options['drupal_pass'], $options['drupal_site_url'], $options['drupal_media_path'], $options['drupal_table_prefix'] );
        $importer->import_posts();
        wp_redirect(admin_url('admin.php?page=drupal-importer&message=posts_imported'));
        exit;
    } else {
        wp_redirect(admin_url('admin.php?page=drupal-importer&message=error'));
        exit;
    }
}

function drupal_importer_handle_page_import() {
    $options = get_option( 'drupal_importer_settings' );

    if ( !empty($options['drupal_host']) && !empty($options['drupal_db']) && !empty($options['drupal_user']) && !empty($options['drupal_pass']) && !empty($options['drupal_site_url']) && !empty($options['drupal_media_path'])  ) {
        $importer = new Drupal_Importer( $options['drupal_host'], $options['drupal_db'], $options['drupal_user'], $options['drupal_pass'], $options['drupal_site_url'], $options['drupal_media_path'], $options['drupal_table_prefix'] );
        $importer->import_pages();
        wp_redirect(admin_url('admin.php?page=drupal-importer&message=pages_imported'));
        exit;
    } else {
        wp_redirect(admin_url('admin.php?page=drupal-importer&message=error'));
        exit;
    }
}

function drupal_importer_handle_user_import() {
    $options = get_option( 'drupal_importer_settings' );

    if ( !empty($options['drupal_host']) && !empty($options['drupal_db']) && !empty($options['drupal_user']) && !empty($options['drupal_pass']) && !empty($options['drupal_site_url']) && !empty($options['drupal_media_path'])  ) {
        $importer = new Drupal_Importer( $options['drupal_host'], $options['drupal_db'], $options['drupal_user'], $options['drupal_pass'], $options['drupal_site_url'], $options['drupal_media_path'], $options['drupal_table_prefix'] );
        $importer->import_users();
        wp_redirect(admin_url('admin.php?page=drupal-importer&message=users_imported'));
        exit;
    } else {
        wp_redirect(admin_url('admin.php?page=drupal-importer&message=error'));
        exit;
    }
}

function drupal_importer_handle_taxonomy_import() {
    $options = get_option( 'drupal_importer_settings' );

    if ( !empty($options['drupal_host']) && !empty($options['drupal_db']) && !empty($options['drupal_user']) && !empty($options['drupal_pass']) && !empty($options['drupal_site_url']) && !empty($options['drupal_media_path'])  ) {
        $importer = new Drupal_Importer( $options['drupal_host'], $options['drupal_db'], $options['drupal_user'], $options['drupal_pass'], $options['drupal_site_url'], $options['drupal_media_path'], $options['drupal_table_prefix'] );
        $importer->import_taxonomies();
        wp_redirect(admin_url('admin.php?page=drupal-importer&message=taxonomies_imported'));
        exit;
    } else {
        wp_redirect(admin_url('admin.php?page=drupal-importer&message=error'));
        exit;
    }
}

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Drupal_Importer {
    private $drupal_db;
    private $site_url;
    private $media_path;
    private $table_prefix;

    public function __construct( $host, $db, $user, $pass, $site_url, $media_path, $table_prefix ) {
        if ( empty($host) || empty($db) || empty($user) || empty($pass) || empty($site_url) || empty($media_path) ) {
            throw new Exception('Drupal database settings are not properly configured.');
        }

        $this->drupal_db = new wpdb( $user, $pass, $db, $host );
        $this->site_url = $site_url;
        $this->media_path = $media_path;
        $this->table_prefix = $table_prefix;

        if ( ! $this->drupal_db->check_connection() ) {
            throw new Exception('Could not connect to the Drupal database.');
        }
    }

    public function import_posts() {
        $drupal_posts = $this->drupal_db->get_results( "
            SELECT 
                n.nid AS ID,
                n.title AS post_title,
                n.created AS post_date,
                n.changed AS post_modified,
                b.body_value AS post_content,
                u.uid AS post_author,
                u.mail AS author_email,
                GROUP_CONCAT(DISTINCT tfd.name SEPARATOR ', ') AS categories,
                f.uri AS post_image
            FROM 
                {$this->table_prefix}node_field_data n
            LEFT JOIN 
                {$this->table_prefix}node__body b ON n.nid = b.entity_id
            LEFT JOIN 
                {$this->table_prefix}users_field_data u ON n.uid = u.uid
            LEFT JOIN 
                {$this->table_prefix}taxonomy_index ti ON n.nid = ti.nid
            LEFT JOIN 
                {$this->table_prefix}taxonomy_term_field_data tfd ON ti.tid = tfd.tid
            LEFT JOIN 
                {$this->table_prefix}taxonomy_term_field_data tvd ON tfd.vid = tvd.vid
            LEFT JOIN 
                {$this->table_prefix}file_managed f ON n.nid = f.fid
            WHERE 
                n.type = 'article' AND n.status = 1
            GROUP BY 
                n.nid
        " );

        foreach ( $drupal_posts as $post ) {
            // Import user if not exists
            $user = get_user_by('login', $post->post_author);
            if (!$user) {
                $user_data = array(
                    'user_login' => $post->post_author,
                    'user_pass'  => wp_hash_password(wp_generate_password()),
                    'user_email' => sanitize_email($post->author_email),
                    'display_name' => sanitize_text_field($post->post_author),
                );
                $user_id = wp_insert_user($user_data);
            } else {
                $user_id = $user->ID;
            }

            // Handle content images
            $post_content = $this->import_content_images($post->post_content);

            // Prepare post data
            $post_data = array(
                'post_title'    => sanitize_text_field($post->post_title),
                'post_content'  => wp_kses_post($post_content),
                'post_status'   => 'publish',
                'post_author'   => $user_id,
                'post_date'     => date('Y-m-d H:i:s', $post->post_date),
                'post_modified' => date('Y-m-d H:i:s', $post->post_modified),
                'post_type'     => 'post',
            );

            // Insert post into WordPress
            $post_id = wp_insert_post($post_data);

            // Add taxonomy terms to post
            $categories = explode(', ', $post->categories);
            foreach ($categories as $category) {
                $term = term_exists($category, 'category');
                if (!$term) {
                    $term = wp_insert_term($category, 'category');
                }
                if (!is_wp_error($term)) {
                    wp_set_post_terms($post_id, $term['term_id'], 'category', true);
                }
            }

            // Handle post image
            if ($post->post_image) {
                $image_url = $this->media_path . $post->post_image;
                if (strpos($image_url, 'public://') === 0) {
                    $image_url = str_replace('public://', $this->site_url . $this->media_path, $image_url);
                }
                $image_id = $this->import_image($image_url, $post_id);
                if (!is_wp_error($image_id)) {
                    set_post_thumbnail($post_id, $image_id);
                }
            }
        }
    }

    public function import_pages() {
        $drupal_pages = $this->drupal_db->get_results( "
            SELECT 
                n.nid AS ID,
                n.title AS post_title,
                n.created AS post_date,
                n.changed AS post_modified,
                b.body_value AS post_content,
                u.uid AS post_author,
                u.mail AS author_email,
                f.uri AS post_image
            FROM 
                {$this->table_prefix}node_field_data n
            LEFT JOIN 
                {$this->table_prefix}node__body b ON n.nid = b.entity_id
            LEFT JOIN 
                {$this->table_prefix}users_field_data u ON n.uid = u.uid
            LEFT JOIN 
                {$this->table_prefix}file_managed f ON n.nid = f.fid
            WHERE 
                n.type = 'page' AND n.status = 1
            GROUP BY 
                n.nid
        " );
        
        foreach ( $drupal_pages as $page ) {
            // Import user if not exists
            $user = get_user_by('login', $page->post_author);
            if (!$user) {
                $user_data = array(
                    'user_login' => $page->post_author,
                    'user_pass'  => wp_hash_password(wp_generate_password()),
                    'user_email' => sanitize_email($page->author_email),
                    'display_name' => sanitize_text_field($page->post_author),
                );
                $user_id = wp_insert_user($user_data);
            } else {
                $user_id = $user->ID;
            }

            // Handle content images
            $page_content = $this->import_content_images($page->post_content);

            // Prepare page data
            $page_data = array(
                'post_title'    => sanitize_text_field($page->post_title),
                'post_content'  => wp_kses_post($page_content),
                'post_status'   => 'publish',
                'post_author'   => $user_id,
                'post_date'     => wp_date('Y-m-d H:i:s', $page->post_date),
                'post_modified' => wp_date('Y-m-d H:i:s', $page->post_modified),
                'post_type'     => 'page',
            );

            // Insert page into WordPress
            $page_id = wp_insert_post($page_data);

            // Handle page image
            if ($page->post_image) {
                $image_url = $this->media_path . $page->post_image;
                if (strpos($image_url, 'public://') === 0) {
                    $image_url = str_replace('public://', $this->site_url . $this->media_path, $image_url);
                }
                $image_id = $this->import_image($image_url, $page_id);
                if (!is_wp_error($image_id)) {
                    set_post_thumbnail($page_id, $image_id);
                }
            }
        }
    }

    public function import_users() {
        $drupal_users = $this->drupal_db->get_results( "SELECT uid, name, mail FROM {$this->table_prefix}users_field_data" );

        foreach ( $drupal_users as $user ) {
            $user_data = array(
                'user_login' => $user->name,
                'user_pass'  => wp_hash_password(wp_generate_password()),
                'user_email' => $user->mail,
                'display_name' => $user->name,
                'role'       => 'subscriber',
            );

            // Insert the user into WordPress
            wp_insert_user( $user_data );
        }
    }

    public function import_taxonomies() {
        $drupal_taxonomies = $this->drupal_db->get_results( "SELECT tfd.tid, tfd.name, tvd.name AS taxonomy_name FROM {$this->table_prefix}taxonomy_term_field_data tfd LEFT JOIN {$this->table_prefix}taxonomy_term_field_data tvd ON tfd.vid = tvd.vid" );

        foreach ( $drupal_taxonomies as $taxonomy ) {
            // Insert the taxonomy into WordPress
            wp_insert_term( $taxonomy->name, $taxonomy->taxonomy_name );
        }
    }

    private function import_image($image_url, $post_id) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $upload_dir = wp_upload_dir();
        $image_data = file_get_contents($image_url);
        $filename = basename($image_url);
        $filename = sanitize_file_name(str_replace(array('%20','%28','%29','%281'),'-',$filename));

        if (wp_mkdir_p($upload_dir['path'])) {
            $file = $upload_dir['path'] . '/' . $filename;
        } else {
            $file = $upload_dir['basedir'] . '/' . $filename;
        }

        file_put_contents($file, $image_data);

        $wp_filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title'     => sanitize_file_name($filename),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );

        $attach_id = wp_insert_attachment($attachment, $file, $post_id);
        $attach_data = wp_generate_attachment_metadata($attach_id, $file);
        wp_update_attachment_metadata($attach_id, $attach_data);

        return $attach_id;
    }

    private function import_content_images($content) {
        // Find all image tags
        preg_match_all('/<img[^>]+src="([^">]+)"/i', $content, $matches);
        $image_urls = array_unique($matches[1]);

        foreach ($image_urls as $image_url) {
            // Handle relative URLs if necessary
            if (strpos($image_url, 'public://') === 0) {
                $image_url = str_replace('public://', $this->site_url . $this->media_path, $image_url);
            }

            // Import image and get new URL
            $new_image_id = $this->import_image($image_url, 0);
            $new_image_url = wp_get_attachment_url($new_image_id);

            // Replace old URL with new URL in content
            $content = str_replace($image_url, $new_image_url, $content);
        }

        return $content;
    }
}

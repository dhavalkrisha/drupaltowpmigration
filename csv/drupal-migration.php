<?php
// Load WordPress functions
require_once( 'wp-load.php' );
require_once( ABSPATH . 'wp-admin/includes/post.php' );

// Function to import taxonomy terms from CSV
function import_drupal_taxonomy( $csv_file )
{
    if ( ( $handle = fopen( $csv_file, 'r' ) ) !== FALSE ) {
        fgetcsv( $handle, 1000, ',' ); // Skip header row
        while ( ( $data = fgetcsv($handle, 1000, ',' ) ) !== FALSE ) {
            $term_name = $data[8]; // Name
            $term_description = $data[9]; // Description
            
            // Check if the term already exists
            $existing_term = term_exists( $term_name, 'category' );
            
            if ( !$existing_term ) {
                $term_id = wp_insert_term(
                    $term_name, // Term Name
                    'category', // Taxonomy name
                    array(
                        'description' => $term_description, // Description
                    )
                );

                if ( is_wp_error( $term_id ) ) {
                    echo '<br> Error inserting term: ' . $term_id->get_error_message();
                } else {
                    echo 'Category imported successfully: ' . $term_name . '<br>';
                }
            } else {
                echo 'Category already exists: ' . $term_name . '<br>';
            }
        }
        fclose($handle);
    } else {
        echo 'Error opening CSV file';
    }
}

// Function to import users from CSV
function import_drupal_users( $csv_file )
{
    if ( ( $handle = fopen( $csv_file, 'r' ) ) !== FALSE ) {
        fgetcsv($handle, 1000, ','); // Skip header row
        echo '<br>';
        while( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== FALSE ) {
            $user_login = $data[5]; // Username 
            $user_email = $data[6]; // Email
            $user_timezone = $data[7]; // Timezone
            $user_status = $data[9] == 1 ? 'active' : 'inactive'; // Status
            $first_name = $data[16]; // First Name
            $last_name = $data[17]; // Last Name
            
            // Extract user roles from CSV
            $user_roles = explode( '|', $data[14] ); // Assuming roles data is in column 14
            
            // Create user
            $user_id = wp_insert_user( array(
                'user_login' => $user_login,
                'user_pass' => wp_generate_password(),
                'user_email' => $user_email,
                'role' => '',
                'first_name' => $first_name,
                'last_name' => $last_name,
            ) );

            if ( is_wp_error( $user_id ) ) {
                echo '<br> Error creating user: ' .$user_login . ' ' . $user_id->get_error_message();
                continue; // Skip to the next iteration of the loop
            }

            // Assign roles to the user
            foreach ( $user_roles as $drupal_role ) {
                // Map Drupal roles to WordPress roles
                $role_mapping = array(
                    'administrator' => 'administrator',
                    'content_editor' => 'editor',
                );

                if ( isset( $role_mapping[$drupal_role] ) ) {
                    $wordpress_role = $role_mapping[$drupal_role];
                    $user = new WP_User( $user_id );
                    $user->add_role( $wordpress_role );
                }
            }

            // Update user status
            wp_update_user( array(
                'ID' => $user_id,
                'user_status' => $user_status
            ) );

            // Update user timezone
            update_user_meta( $user_id, 'timezone_string', $user_timezone );

            echo 'User imported successfully: ' . $user_login . '<br>';
        }
        echo '<br>';
        fclose($handle);
    } else {
        echo 'Error opening CSV file';
    }
}

// Function to import posts from CSV
function import_drupal_posts( $post_csv )
{
    // Now import posts
    if ( ( $handle = fopen( $post_csv, 'r' ) ) !== FALSE ) {
        fgetcsv( $handle, 1000, ',' ); // Skip header row
        while ( ( $data = fgetcsv($handle, 1000, ',' ) ) !== FALSE ) {
            $post_type_data = explode( '|', $data[4] );
            $post_type = $post_type_data[0]; // Type
            $post_title = $data[10]; // Title
            $post_content = $data[18]; // Body
            $post_status = $data[8] == 1 ? 'publish' : 'draft'; // Status
            $post_author_login = $data[9]; // User Login
            $post_date = explode( '|', $data[11] ); // Creation date
            $created_date = $post_date[0];
            $featured_image = array_key_exists( 22, $data ) ? $data[22] : null;

            if( $featured_image != NULL ){
                $image_data = explode( '|', $featured_image ); // Image data
                $image_url = end( $image_data );
            }
            
            // Split content based on |basic_html| and take the first part
            $post_content_parts = explode( '|basic_html|', $post_content );
            $post_content = trim( $post_content_parts[0] );

            if ( $post_type == 'article' ) {
                $post_type_wordpress = 'post';
            } elseif ( $post_type == 'page' ) {
                $post_type_wordpress = 'page';
            } else {
                $post_type_wordpress = $post_type;
            }

            // Check if post already exists
            $existing_post = check_post_exists( $post_title, $post_type_wordpress );

            if ( $existing_post ) {
                // Display message if post or page already exists.
                echo "<br>The post or page with the title \"$post_title\" already exists..!!";
                continue;
            }

            // Get author by user login
            $author = get_user_by( 'login', $post_author_login );
            $post_author_id = $author ? $author->ID : 1; // Default to admin if author not found

            // Insert post
            $post_id = wp_insert_post( array(
                'post_title' => $post_title,
                'post_content' => $post_content,
                'post_status' => $post_status,
                'post_author' => $post_author_id,
                'post_date' => $created_date,
                'post_type' => $post_type_wordpress, // Set post type
            ));

            if ( is_wp_error( $post_id ) ) {
                echo 'Error inserting post: ' . $post_id->get_error_message();
            } else {
                // Assign categories to post if available
                if ( !empty( $data[21] ) ) {
                    $categories = explode( '|', $data[21] ); // Split category field by '|'
                    $category_ids = array();
                    foreach ( $categories as $category ) {
                        $term = get_term_by( 'name', $category, 'category' );
                        if ($term) {
                            $category_ids[] = $term->term_id;
                        }
                    }
                    if ( !empty( $category_ids ) ) {
                        wp_set_post_categories( $post_id, $category_ids );
                    }
                }

                // Upload featured image and set it as post thumbnail
                if ( !empty( $image_url ) && $featured_image != NULL ) {
                    $featured_image_id = upload_featured_image( $image_url, $post_id );
                    if ( $featured_image_id ) {
                        set_post_thumbnail($post_id, $featured_image_id);
                    }
                }
                echo "<br>Post imported successfully: $post_title";
            }
        }
        fclose( $handle );
    } else {
        echo 'Error opening CSV file';
    }
}

// Function to check existing post.
function check_post_exists( $title, $post_type )
{
    $post_id = post_exists( $title, '', '', $post_type );
    return $post_id ? true : false;
}

// Function to set featured image for post.
function upload_featured_image( $image_url, $post_id )
{
    $upload_dir = wp_upload_dir(); // Get upload directory path
    $image_data = @file_get_contents( $image_url ); // Try to download the image

    if ( $image_data === false ) {
        // Error downloading the image.
        return new WP_Error( 'Image_download_failed', 'Failed to download the image from the URL.' );
    }

    $filename = wp_unique_filename( $upload_dir['path'], basename( $image_url ) );

    // Write the image data to the file.
    $file = fopen( $upload_dir['path'] . '/' . $filename, 'w' );

    if ( $file ) {
        fwrite( $file, $image_data );
        fclose( $file );

        // Create attachment data
        $attachment = array(
            'post_mime_type' => 'image/jpeg', // Adjust mime type if necessary
            'post_title'     => sanitize_file_name( $filename ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );

        // Insert the attachment into the media library.
        $attach_id = wp_insert_attachment( $attachment, $upload_dir['path'] . '/' . $filename, $post_id );
        
        if ( !is_wp_error( $attach_id ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attach_data = wp_generate_attachment_metadata( $attach_id, $upload_dir['path'] . '/' . $filename );
            wp_update_attachment_metadata( $attach_id, $attach_data );
            return $attach_id;
        } else {
            return $attach_id;
        }
    } else {
        return new WP_Error( 'File_open_failed', 'Failed to open the file for writing.' );
    }
}

// Path to CSV files
$taxonomy_csv = 'texo_api.csv';
$user_csv = 'users_api.csv';
$post_csv = 'nodes_api.csv';

// Import taxonomy terms
import_drupal_taxonomy( $taxonomy_csv );

// Import users
import_drupal_users( $user_csv );

// Import all data
import_drupal_posts( $post_csv );
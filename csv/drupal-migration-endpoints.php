<?php
require 'wp-load.php';
require_once( ABSPATH . 'wp-admin/includes/post.php' );
// Drupal JSON:API endpoint
$drupal_article_endpoint = 'https://dev-krishatest.pantheonsite.io/jsonapi/node/article';
$drupal_user_endpoint = 'https://dev-krishatest.pantheonsite.io/jsonapi/user/user';

// Retrieve data from Drupal JSON:API User
$response = wp_remote_get( $drupal_user_endpoint );
echo '<pre>';
$json_value = json_decode( $response['body'] );
//var_dump($json_value);

foreach ( $json_value->data as $value ) {
	$user_data = array();
	$user_attributes = $value->attributes;
	$user_relationships = $value->relationships;
	$user_roles = $value->relationships->roles->data;
	foreach( $user_roles as $role ){
    	$role_meta = json_decode( json_encode( $role->meta ), true );
    	$user_data['user_roles'][] = $role_meta['drupal_internal__target_id'];
	}
	//var_dump($user_data['user_role']);
	$username = $user_attributes->name;
	$user_email = $user_attributes->mail;
	
	if( $username && $user_email ){
		$user_data['display_name'] = $user_attributes->display_name;
		$user_data['first_name'] = $user_attributes->field_first_name;
		$user_data['last_name'] = $user_attributes->field_last_name;
		create_users( $user_email, $user_data );	
	}
}

// Retrieve data from Drupal JSON:API Article
$post_response = wp_remote_get( $drupal_article_endpoint );
echo '<pre>';
// Decode JSON data
$post_json_value = json_decode( $post_response['body'] );
for( $i=0; $i<count($post_json_value->data );$i++){
    $postdata = $post_json_value->data[$i]->attributes; 
    $post_title = $postdata->title;
    $post_content = $postdata->body->value;
    create_wordpress_post($post_title,$post_content);
}

function create_wordpress_post($post_title, $post_content) {
    // Check if post already exists
    $existing_post = check_post_exists( $post_title );

    if ($existing_post) {
        // Post already exists, skip insertion
        echo 'Post already exists: ' . $post_title . '<br>';
        return;
    }

    // Prepare post data
    $new_post = array(
        'post_title' => $post_title,
        'post_content' => $post_content,
        'post_status' => 'publish',
        'post_type' => 'post',
    );

    // Insert the post into the database
    $post_id = wp_insert_post($new_post);

    // Check if post was inserted successfully
    if ($post_id) {
        echo 'Post inserted successfully with ID: ' . $post_id . '<br>';
    } else {
        echo 'Error inserting post: ' . $post_title . '<br>';
    }
}

// Function to check existing post.
function check_post_exists( $title ) {
    $post_id = post_exists( $title, '', '', 'post' );
    return $post_id ? true : false;
}

// Function to create user.
function create_users( $user_email, $user_data ) {
    $user_id = email_exists( $user_email );

    if ( ! $user_id ) {
        $author_username = $user_data['display_name'];
        $author_email = $user_email;
        //$user_id = wp_create_user( $author_username, wp_generate_password(), $author_email );

        $user_id = wp_insert_user( array(
		    'user_login' => $author_username,
		    'user_pass' => wp_generate_password(),
		    'user_email' => $author_email,
		    'role' => '',
		) );

        // Role mapping from Drupal to WordPress
        $role_mapping = array(
            'administrator' => 'administrator',
            'content_editor' => 'editor',
        );

        // Assign roles based on role mapping
        if ( isset( $user_data['user_roles'] ) && is_array( $user_data['user_roles'] ) ) {
            foreach ( $user_data['user_roles'] as $drupal_role ) {
                if ( isset( $role_mapping[ $drupal_role ] ) ) {
                    $wordpress_role = $role_mapping[ $drupal_role ];
                    $user = new WP_User( $user_id );
                    $user->add_role( $wordpress_role );
                }
            }
        }

        // Update user information
        if ( $user_id && isset( $user_data['first_name'] ) ) {
            wp_update_user( array( 'ID' => $user_id, 'first_name' => $user_data['first_name'] ) );
        }
        if ( $user_id && isset( $user_data['last_name'] ) ) {
            wp_update_user( array( 'ID' => $user_id, 'last_name' => $user_data['last_name'] ) );
        }

        echo 'User created successfully..!!<br>';
    } else {
        echo 'User already exists..!!<br>';
    }

    return $user_id;
}


echo 'Last line: Script execution completed <br>';
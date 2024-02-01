<?php
/*
Plugin Name: Node-SSo plugin
Description: Provides an API response for the currently logged-in user.
Version: 1.0
Author: ME
*/

// Plugin code will go here

register_activation_hook(__FILE__, 'create_the_custom_table');
add_action('rest_api_init', 'register_checkauth_route');
add_action('wp_login', 'jwt_login_hook', 10, 2);
add_action('wp_logout', 'jwt_logout_hook');

add_filter('login_redirect', 'ywp_redirect_wp_login');
function ywp_redirect_wp_login()
{
  if ( isset( $_GET['redirect_to'] ) ) {
      $redirect = esc_url( $_GET['redirect_to'] );
  } else {
      $redirect = home_url();
  }

  return $redirect;
}
// add_action( 'login_init', 'jwt_login_init' );

// function jwt_login_init()
// {
//   global $wpdb;
//   $token_table = $wpdb->prefix . "users_tokens";
//   $userAgent = $_SERVER['HTTP_USER_AGENT'];

//   $query = $wpdb->prepare("SELECT COUNT(*) FROM {$token_table} WHERE user_agent = %s", $userAgent);
//   $row_count = $wpdb->get_var($query);

//   if ($row_count > 0) {
//     $whereData = array(
//       'user_agent' => $userAgent
//     );
//     $updateData = array(
//       'jwt_token' => '',
//       'is_logged' => false,
//     );

//     $wpdb->update($token_table, $updateData, $whereData);
//   }
// }

function create_the_custom_table()
{
  global $wpdb;

  $chartset_collate = $wpdb->get_charset_collate();
  $token_table = $wpdb->prefix . "users_tokens";

  $sql = "CREATE TABLE $token_table (
  id mediumint(9) NOT NULL AUTO_INCREMENT,
  user_email text NOT NULL,
  user_agent text NOT NULL,
  jwt_token text NOT NULL,
  is_logged bit DEFAULT 0,
  PRIMARY KEY (id)
) $chartset_collate;";

  require_once(ABSPATH . "wp-admin/includes/upgrade.php");
  dbDelta($sql);
}

function register_checkauth_route()
{
  register_rest_route(
    'node-sso/v1',
    '/checkauth',
    array(
      'methods' => 'POST',
      'callback' => 'api_checkauth_callback',
    ),
  );
}

function api_checkauth_callback($request)
{
  global $wpdb;
  $token_table = $wpdb->prefix . "users_tokens";
  $user_agent = $_SERVER['HTTP_USER_AGENT'];
  $raw_data = file_get_contents('php://input');
  // $user_email = isset($request_data['user_email']) ? $request_data['user_email'] : '';
  $request_data = json_decode($raw_data);

  $user_email = isset($request_data->user_email) ? $request_data->user_email : '';

  $sql = "";
  if ($user_email) {
    $sql = "SELECT * FROM {$token_table} WHERE user_email = '" . $user_email . "' AND user_agent = '" . $user_agent . "'";
  } else {
    $sql = "SELECT * FROM {$token_table} WHERE user_agent = '" . $user_agent . "'";
  }

  $query = $wpdb->prepare($sql);
  $user = $wpdb->get_row($query);

  $resultData = array();
  if ($user) {
    $resultData = array(
      'success' => $user->is_logged,
      'user' => $user
    );
  } else {
    $resultData = array(
      'success' => false,
      'user' => null
    );
  }
  return rest_ensure_response($resultData);
}

function jwt_login_hook($user_login, $user)
{
  global $wpdb;
  $token_table = $wpdb->prefix . "users_tokens";
  $userAgent = $_SERVER['HTTP_USER_AGENT'];

  // Get JWT with Simple JWT Login plugin Rest API
  $url = 'https://expresslogbooks.com' . '/?rest_route=/simple-jwt-login/v1/auth&email=' . $user->user_email . '&password_hash=' . $user->user_pass;
  $response = wp_remote_post($url, array());
  $response_body = json_decode(wp_remote_retrieve_body($response));

  if ($response_body && $response_body->success) {
    // Check if the row is exist
    $query = $wpdb->prepare("SELECT COUNT(*) FROM {$token_table} WHERE user_email = %s AND user_agent = %s", $user->user_email, $userAgent);
    $row_count = $wpdb->get_var($query);

    if ($row_count > 0) {
      // If exist, update row
      $whereData = array(
        'user_email' => $user->user_email,
        'user_agent' => $userAgent
      );
      $updateData = array(
        'jwt_token' => $response_body->data->jwt,
        'is_logged' => true,
      );
      setcookie('wp-update', $user->user_email, time() + 3600, '/');

      $wpdb->update($token_table, $updateData, $whereData);
    } else {
      // If ot exist, insert row
      $insertData = array(
        'user_email' => $user->user_email,
        'user_agent' => $userAgent,
        'jwt_token' => $response_body->data->jwt,
        'is_logged' => true
      );

      $wpdb->insert($token_table, $insertData);
    }
  }
}

function jwt_logout_hook($user_id)
{
  global $wpdb;
  $token_table = $wpdb->prefix . "users_tokens";
  $user_table = $wpdb->prefix . "users";
  $userAgent = $_SERVER['HTTP_USER_AGENT'];

  $query = $wpdb->prepare("SELECT * FROM {$user_table} WHERE ID = %s", $user_id);
  $user = $wpdb->get_row($query);

  setcookie('logout_user_email', $user->user_email, time() + 3600, '/');

  $query = $wpdb->prepare("SELECT COUNT(*) FROM {$token_table} WHERE user_email = %s AND user_agent = %s", $user->user_email, $userAgent);
  $row_count = $wpdb->get_var($query);

  if ($row_count > 0) {
    $whereData = array(
      'user_email' => $user->user_email,
      'user_agent' => $userAgent
    );
    $updateData = array(
      'jwt_token' => '',
      'is_logged' => false,
    );

    $wpdb->update($token_table, $updateData, $whereData);
  }
}
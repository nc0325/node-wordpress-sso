
add_action('rest_api_init', 'my_api_plugin_register_route');
add_action('wp_login', 'my_login_hook', 10, 2);
add_action('wp_logout', 'my_logout_hook');

function my_login_hook($user_login, $user)
{
  // Your code here to handle the user login event
  // Access the $user_login and $user objects as needed

  // Example: Display a welcome message
  setcookie('user_email', $user->user_email, time() + 3600, '/');
}

function my_logout_hook()
{
  // Your code here to handle the user login event
  // Access the $user_login and $user objects as needed

  // Example: Display a welcome message
  setcookie('user_email', null);
}


function my_api_plugin_register_route()
{
  register_rest_route(
    'node-sso/v1',
    '/user',
    array(
      'methods' => 'GET',
      'callback' => 'getUser',
      'permission_callback' => 'permission_check'
    )
  );
  register_rest_route(
    'node-sso/v1',
    '/parse-cookie',
    array(
      'methods' => 'GET',
      'callback' => 'get_current_user_email',
    )
  );
}
function get_current_user_email()
{
  // Get the WordPress cookie name
  $cookie_value = $_REQUEST['cookie'];

  // Parse the cookie value to extract the user data
  $cookie_parts = explode('|', $cookie_value);
  if (count($cookie_parts) >= 3) {
    list(, , $user_data) = $cookie_parts;

    // Decode the user data
    $user_data = base64_decode($user_data);

    // Extract the user email from the decoded data
    if ($user_data) {
      $user_data = maybe_unserialize($user_data);
      if (is_object($user_data) && isset($user_data->data) && isset($user_data->data->user_email)) {
        return $user_data->data->user_email;
      }
    }
  }

  // Return null if the user email cannot be retrieved
  return '';
}

function getUser($request)
{
  $user = wp_get_current_user();

  if ($user->ID) {
    // User is logged in
    $response = array(
      'success' => true,
      'data' => array(
        'id' => $user->ID,
        'username' => $user->user_login,
        'email' => $user->user_email
        // Add more user data as needed
      )
    );
  } else {
    // User is not logged in
    $response = array(
      'success' => false,
      'message' => 'User is not logged in'
    );
  }

  return rest_ensure_response($response);
}

function permission_check()
{
  if (!is_user_logged_in()) {
    return new WP_Error('rest_forbidden', __('You are not logged in.'), array('status' => 401));
  }

  return true;
}
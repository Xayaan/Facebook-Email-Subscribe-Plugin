<?php
/*
Plugin Name: InstaGrab Beast Pro
Description: Get visitor emails through a Facebook button
Version: 1.0
*/

//{{{PHP_ENCODE}}}
define('RGT_FB_EMAIL_BUTTON_DIR', plugin_dir_path(__FILE__));
define('RGT_FB_EMAIL_BUTTON_URL', plugin_dir_url(__FILE__));


//// DATABASE ////

function rgt_fb_email_button_db_install() {
   global $wpdb;
   $table_name = $wpdb->prefix . "fb_email";
   $charset_collate = $wpdb->get_charset_collate();
   $sql = "CREATE TABLE IF NOT EXISTS $table_name (
     id mediumint(9) NOT NULL AUTO_INCREMENT,
     email varchar(255) NOT NULL,
     UNIQUE KEY id (id),
     UNIQUE KEY email (email)
   );";

   require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
   dbDelta($sql);
}
register_activation_hook(__FILE__, 'rgt_fb_email_button_db_install');

function rgt_fb_email_button_add_email($email) {
  global $wpdb;
  $table_name = $wpdb->prefix . "fb_email";
  $email_exists = $wpdb->get_row("SELECT * FROM $table_name WHERE email='$email'");
  if (!$email_exists) {
    $wpdb->insert($table_name, array('email' => $email));
  }
}

function rgt_fb_email_button_get_emails($limit=0) {
  global $wpdb;
  $table_name = $wpdb->prefix . "fb_email";
  $select = "SELECT * FROM $table_name ORDER BY id DESC" . ($limit ? " LIMIT $limit" : '') . ';';
  $result = $wpdb->get_results($select);
  return $result;
}

function add_fake_emails_for_testing() {
  for ($i=1; $i <= 30; $i++) {
    rgt_fb_email_button_add_email("email$i@test.net");
  }
}

//////////////////

//// Menus ////

add_action('admin_menu', 'rgt_fb_email_button_add_menus');
add_action('admin_init', 'rgt_fb_email_button_settings_init');
function rgt_fb_email_button_add_menus(){
  add_options_page("InstaGrab Beast Pro Settings", "InstaGrab Settings", "manage_options", "rgt_fb_email_button", "rgt_fb_email_button_options_page");
  $menu_page = add_menu_page("InstaGrab Beast Pro Email Addresses", "InstaGrab Email Addresses", "manage_options", "rgt_fb_email_button_list", "rgt_fb_email_button_list_page");
  add_action('load-'.$menu_page, 'do_email_export');
}

function rgt_fb_email_button_settings_init(){
	register_setting('fb_email_button_options_page', 'rgt_fb_email_button_settings');

	add_settings_section(
		'rgt_fb_email_button_pluginPage_section',
		'InstaGrab Beast Pro Cofig',
		'rgt_fb_email_button_settings_section_callback',
		'fb_email_button_options_page',
		'general'
	);

	add_settings_field(
		'rgt_fb_email_button_app_id',
		'Facebook App ID',
		'rgt_fb_email_button_app_id_render',
		'fb_email_button_options_page',
		'rgt_fb_email_button_pluginPage_section'
	);

	add_settings_field(
		'rgt_fb_email_button_app_secret',
		'Facebook App Secret',
		'rgt_fb_email_button_app_secret_render',
		'fb_email_button_options_page',
		'rgt_fb_email_button_pluginPage_section'
	);

	add_settings_field(
		'rgt_fb_email_button_email_count',
		'Number of emails to preview',
		'rgt_fb_email_button_app_email_count_render',
		'fb_email_button_options_page',
		'rgt_fb_email_button_pluginPage_section'
	);
}

function rgt_fb_email_button_app_id_render() {

	$options = get_option('rgt_fb_email_button_settings');
	?>
	<input type='text' name='rgt_fb_email_button_settings[rgt_fb_email_button_app_id]' value='<?php echo $options['rgt_fb_email_button_app_id']; ?>'>
	<?php

}

function rgt_fb_email_button_app_secret_render() {

	$options = get_option('rgt_fb_email_button_settings');
	?>
	<input type='text' name='rgt_fb_email_button_settings[rgt_fb_email_button_app_secret]' value='<?php echo $options['rgt_fb_email_button_app_secret']; ?>'>
	<?php

}

function rgt_fb_email_button_app_email_count_render() {

	$count = get_option('rgt_fb_email_button_settings')['rgt_fb_email_button_email_count'];
    if (!$count or $count < 1)
	  $count = '';
	?>
	<input type='text' name='rgt_fb_email_button_settings[rgt_fb_email_button_email_count]' value='<?php echo $count; ?>'>
	<?php

}

function rgt_fb_email_button_settings_section_callback() {

	echo 'Settings to link your Facebook App';

}

function rgt_fb_email_button_options_page() {
//	include_once(WP_PLUGIN_DIR.'/facebook-email-button/validateaactivatewp.php');
//	if (!function_exists('validate_actwp_ae12e7b66004bffc56748902ab9b1cbe'))
//		die();
//	validate_actwp_ae12e7b66004bffc56748902ab9b1cbe();

	?>
	<form action='options.php' method='post'>

		<?php
		settings_fields('fb_email_button_options_page');
		do_settings_sections('fb_email_button_options_page');
		submit_button();
		?>

	</form>
	<?php

}

function rgt_fb_email_button_list_page() {
//  include_once(WP_PLUGIN_DIR.'/facebook-email-button/validateaactivatewp.php');
//  if (!function_exists('validate_actwp_ae12e7b66004bffc56748902ab9b1cbe'))
//    die();
//  validate_actwp_ae12e7b66004bffc56748902ab9b1cbe();
  $count = get_option('rgt_fb_email_button_settings')['rgt_fb_email_button_email_count'];
  if (!$count or $count < 1)
    $count = 15;
  $current_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
  $csv_url = wp_nonce_url($current_url, 'fb_email_export', 'csv');
  echo "<h2>Currently subscribed emails</h2>
  <p>Displaying most recent $count emails. <a href=\"$csv_url\">Download all as CSV</a></p>\n";
  $emails = rgt_fb_email_button_get_emails($count);
  if (!$emails) {
	  echo "<p><em>No emails yet</em></p>";
  } else {
    foreach($emails as $email) {
	  $data = $email->email;
	  echo "<p>$data</p>\n";
	}
  }
}

function do_email_export() {
  if (isset($_GET['csv']) and wp_verify_nonce($_GET['csv'], 'fb_email_export')) {
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=fb_emails.csv");
    header("Pragma: no-cache");
    header("Expires: 0");
    $data = "email\n";
    $results = rgt_fb_email_button_get_emails();
	$emails = array(array('email'));
	foreach($results as $email) {
		$emails[] = array($email->email);
	}
	
	$op = fopen('php://output','w');
	foreach($emails as $row)
	{
		fputcsv($op,$row);
	}
	fclose($op);
	exit();
  }
}

//////////////////

//// SHORTCODE ////

function rgt_fb_email_button_error_wrapper($msg) {
	$msg = "<div class=\"error\"><strong>Error: $msg</strong></div>";
	return $msg;
}

function rgt_fb_email_button_start_session() {
	if (!session_id())
		session_start();
}
add_action('init', 'rgt_fb_email_button_start_session');

function rgt_fb_email_button_shortcode() {
  $options = get_option('rgt_fb_email_button_settings');
  $app_id =  $options['rgt_fb_email_button_app_id'];
  $app_secret =  $options['rgt_fb_email_button_app_secret'];
  $site_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
  wp_register_style('rgt_fb_email_button_css', plugins_url('fb_email_button_style.css',__FILE__));
  wp_enqueue_style('rgt_fb_email_button_css');

  if (!($app_id and $app_secret)) {
    $error = rgt_fb_email_button_error_wrapper('Facebook App ID and App Secret must be set in the Settings before using this plugin.');
    return $error;
  } else {
    require_once __DIR__ . '/Facebook_SDK/autoload.php';

    $fb = new Facebook\Facebook([
      'app_id' => $app_id,
      'app_secret' => $app_secret,
      'default_graph_version' => 'v2.5',
      'default_access_token' => $app_id . '|' . $app_secret
    ]);
    $helper = $fb->getRedirectLoginHelper();

    if (isset($_GET['code'])) {  // API Callback
      try {
        $accessToken = $helper->getAccessToken();
      } catch(Facebook\Exceptions\FacebookResponseException $e) {
        $error = rgt_fb_email_button_error_wrapper('Invalid Facebook App Info.');
        return $error;
      } catch(Facebook\Exceptions\FacebookSDKException $e) {
        $error = rgt_fb_email_button_error_wrapper('Invalid Facebook App Info.');
        return $error;
      }

      if (isset($accessToken)) {
        $oAuth2Client = $fb->getOAuth2Client();
        $longLivedAccessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
        $_SESSION['rgt_fb_email_button_facebook_access_token'] = (string) $longLivedAccessToken;

        // Get data
        $fb->setDefaultAccessToken($longLivedAccessToken);
        try {
          $response = $fb->get('/me?fields=email');
        } catch(Facebook\Exceptions\FacebookResponseException $e) {
          $error = rgt_fb_email_button_error_wrapper('Graph returned an error: ' . $e->getMessage());
          return $error;
        } catch(Facebook\Exceptions\FacebookSDKException $e) {
          $error = rgt_fb_email_button_error_wrapper('Facebook SDK returned an error: ' . $e->getMessage());
          return $error;
        }

        $graphObject = $response->getGraphObject();
        $id = $graphObject->getProperty('id');
        $email = $graphObject->getProperty('email');

        rgt_fb_email_button_add_email($email);
		
		$js_redirect = "<script>window.location = '" . strtok($site_url, '?') . "'</script>";
		return $js_redirect;
      }
    }

    if (!isset($_SESSION['rgt_fb_email_button_facebook_access_token']) or !$_SESSION['rgt_fb_email_button_facebook_access_token']) {
      $permissions = array('public_profile', 'email');
      $loginUrl = $helper->getLoginUrl($site_url, $permissions);
      $button = "<a class=\"btn_blue\" onclick=\"location.href='$loginUrl'\">Subscribe</a>";
    } else {
      $button = "<a class=\"btn_green\">Subscribed!</a>";
    }

    return $button;
  }
}
add_shortcode('instagrab-button', 'rgt_fb_email_button_shortcode');

//////////////////

//{{{/PHP_ENCODE}}}

?>

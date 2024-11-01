<?php
/*
Plugin Name: twitface
Description: A very simple Wordpress plugin that allows the user to enter their Facebook and/or Twitter login details and have all of their blog posts submitted to their Twitter and/or Facebook accounts as tweets and status.</a>
Version: 1.0
*/

global $table_prefix, $wp_version;
require_once(ABSPATH . WPINC . '/pluggable.php');

//require_once('aktt_tweet.php');

define(Twitface_DEBUG, false);
define(Twitface_TESTING, false);
define('AKTT_API_POST_STATUS', 'http://twitter.com/statuses/update.json');

$facebook_config['debug'] = Twitface_TESTING && !$_POST['action'];

define(Twitface_FB_APIKEY, '21e0776b27318e5867ec665a5b18a850');
define(Twitface_FB_SECRET, 'f342d13c5094bef736842e4832420e8f');
define(Twitface_TEMPLATE_ID, 32227697731);
define(Twitface_FB_APIVERSION, '1.0');
define(Twitface_FB_DOCPREFIX,
	'http://wiki.developers.facebook.com/documentation.php?v='
	. Twitface_FB_APIVERSION . '&method=');
define(Twitface_FB_MAXACTIONLEN, 60);

define(Twitface_OPTIONS, 'Twitface_options');
define(Twitface_OPTION_SCHEMAVERS, 'schemavers');
define(Twitface_OPTION_FACEBOOK, 'facebook');
define(Twitface_OPTION_TWITTER, 'twitter');

define(Twitface_ERRORLOGS, $table_prefix . 'Twitface_errorlogs');
define(Twitface_POSTLOGS, $table_prefix . 'Twitface_postlogs');
define(Twitface_USERDATA, $table_prefix . 'Twitface_userdata');
define(Twitface_TWITTERDATA, $table_prefix . 'Twitface_twitterdata');

define(Twitface_EXCERPT_SHORTSTORY, 256);
define(Twitface_EXCERPT_WIDEBOX, 96);
define(Twitface_EXCERPT_NARROWBOX, 40);
define(CUSTOMFACEBOOK_OPTION_FACEBOOK, 'facebook');
define(CUSTOMFACEBOOK_OPTION_TWITTER, 'twitter');
define(Twitface_MINIMUM_ADMIN_LEVEL, 2);	/* Author role or above. */
define(Twitface_OPTIONS_PAGENAME, 'Twitface');
define(Twitface_OPTIONS_URL, 'admin.php?page=' . Twitface_OPTIONS_PAGENAME);

define(Twitface_SCHEMA_VERSION, 5);

$Twitface_wp_version_tuple = explode('.', $wp_version);
define(Twitface_WP_VERSION, $Twitface_wp_version_tuple[0] * 10 +
	$Twitface_wp_version_tuple[1]);

if (function_exists('json_encode')) {
	define(Twitface_JSON_ENCODE, 'PHP');
} else {
	define(Twitface_JSON_ENCODE, 'Twitface');
}

if (function_exists('simplexml_load_string')) {
	define(Twitface_SIMPLEXML, 'PHP');
} else {
	define(Twitface_SIMPLEXML, 'Facebook');
}

if (substr(phpversion(), 0, 2) == '5.') {
	define(FACEBOOK_PHP_API, 'PHP5');
} else {
	define(FACEBOOK_PHP_API, 'PHP4');
}

function Twitface_debug($message) {
	if (Twitface_DEBUG) {
		$fp = fopen('/tmp/wb.log', 'a');
		$date = date('D M j, g:i:s a');
		fwrite($fp, "$date: $message");
		fclose($fp);
	}
}

function Twitface_load_apis() {
	if (defined('Twitface_APIS_LOADED')) {
		return;
	}
	if (Twitface_JSON_ENCODE == 'Twitface') {
		function json_encode($var) {
			if (is_array($var)) {
				$encoded = '{';
				$first = true;
				foreach ($var as $key => $value) {
					if (!$first) {
						$encoded .= ',';
					} else {
						$first = false;
					}
					$encoded .= "\"$key\":"
						. json_encode($value);
				}
				$encoded .= '}';
				return $encoded;
			}
			if (is_string($var)) {
				return "\"$var\"";
			}
			return $var;
		}
	}
	if (FACEBOOK_PHP_API == 'PHP5') {
		require_once('facebook-platform/php/facebook.php');
		require_once('Twitface_php5.php');
	}
	define(Twitface_APIS_LOADED, true);
}
/************* get the twitter username, pasword ***************/
function Twitface_get_twitter()
{
	global $user_ID,$wpdb;

	$rows = $wpdb->get_results('
		SELECT *
		FROM wp_Twitface_twitterdata
		WHERE user_id = ' . $user_ID . '
		');
	if ($rows) {
		$rows[0]->twitter_username = unserialize($rows[0]->twitter_username);
		$rows[0]->twitter_password =
			unserialize($rows[0]->twitter_password);
		return $rows[0];
	}
	return null;
	
}
/***************** function to post the blog in twitter *****************/
function do_tweet($tw_text = '') {
$twitter_details = Twitface_get_twitter();

		if (empty($twitter_details->twitter_username) 
			|| empty($twitter_details->twitter_password) 
			|| empty($tw_text)
			
		) {
			return;
		}
		$tweet = apply_filters('do_tweet', $tw_text); // return false here to not tweet
		if (!$tweet) {
			return;
		}
		require_once(ABSPATH.WPINC.'/class-snoopy.php');
		$snoop = new Snoopy;
		$snoop->agent = 'Twitter Tools http://alexking.org/projects/wordpress';
		$snoop->rawheaders = array(
			'X-Twitter-Client' => 'Twitter Tools'
			, 'X-Twitter-Client-Version' => '1.5'
			, 'X-Twitter-Client-URL' => 'http://alexking.org/projects/wordpress/twitter-tools.xml'
		);
		$snoop->user = $twitter_details->twitter_username;
		$snoop->pass = $twitter_details->twitter_password;
		$snoop->submit(
			AKTT_API_POST_STATUS
			, array(
				'status' => $tw_text
				, 'source' => 'Twitfacetools'
			)
		);
		
		return false;
	}
	
	//function do_blog_post_tweet($post_id = 0) {
		function do_blog_post_tweet($post = 0) {
// this is only called on the publish_post hook
		/*if ($this->notify_twitter == '0'
			|| $post_id == 0
			|| get_post_meta($post_id, 'aktt_tweeted', true) == '1'
			|| get_post_meta($post_id, 'aktt_notify_twitter', true) == 'no'
		) {
			
			return;
		}*/
		
		
		//$post = get_post($post_id);
		$post = get_post($post->ID);
		//echo 'out of if';
		/*if ($post->post_date <= $this->install_date) {
			return;
		}*/
		// check for private posts
		if ($post->post_status == 'private') {
			return;
		}
		//$tweet = new aktt_tweet;
		//$url = apply_filters('tweet_blog_post_url', get_permalink($post_id));
		$url = get_permalink($post->ID);
		
		//$url = $post->guid;
		
		//$tweet->tw_text = sprintf(__($this->tweet_format, 'twitter-tools'), @html_entity_decode($post->post_title, ENT_COMPAT, 'UTF-8'), $url);
		//$tweet_text = @html_entity_decode($post->post_title, ENT_COMPAT, 'UTF-8') . '&nbsp;' . $url;
		$tweet_text = $post->post_title . '&nbsp;' . $url;
		$tweet = apply_filters('do_blog_post_tweet', $post); // return false here to not tweet
		
		if (!$tweet) { 
			return;
		}
		
		do_tweet($tweet_text);
		//add_post_meta($post_id, 'aktt_tweeted', '1', true);
	}
	/******************************* end of posting blog in twitter *********************************/
	
/******************************************************************************
 * Twitface options.
 */

function Twitface_options() {
	return get_option(Twitface_OPTIONS);
}

function Twitface_set_options($options) {
	update_option(Twitface_OPTIONS, $options);
}

function Twitface_get_option($key) {
	$options = Twitface_options();
	return isset($options[$key]) ? $options[$key] : null;
}

function Twitface_set_option($key, $value) {
	$options = Twitface_options();
	$options[$key] = $value;
	Twitface_set_options($options);
}

function Twitface_delete_option($key) {
	$options = Twitface_options();
	unset($options[$key]);
	update_option(Twitface_OPTIONS, $options);
}

/******************************************************************************
 * Plugin deactivation - tidy up database.
 */

function Twitface_deactivate() {
	global $wpdb;

	wp_cache_flush();
	$errors = array();
	foreach (array(
			Twitface_ERRORLOGS,
			Twitface_POSTLOGS,
			Twitface_USERDATA,
			Twitface_TWITTERDATA,
			) as $tablename) {
		$result = $wpdb->query("
			DROP TABLE IF EXISTS $tablename
			");
		if ($result === false)
			$errors[] = "Failed to drop $tablename";
	}
	delete_option(Twitface_OPTIONS);
	wp_cache_flush();

	if ($errors) {
		echo '<div id="message" class="updated fade">' . "\n";
		foreach ($errors as $errormsg) {
			_e("$errormsg<br />\n");
		}
		echo "</div>\n";
	}
}

/******************************************************************************
 * DB schema.
 */

function Twitface_upgrade() {

	global $wpdb, $table_prefix;

	$options = Twitface_options();

	if ($options && isset($options[Twitface_OPTION_SCHEMAVERS]) &&
			$options[Twitface_OPTION_SCHEMAVERS] ==
			Twitface_SCHEMA_VERSION) {
		return;
	}

	wp_cache_flush();
	if (!$options || !isset($options[Twitface_OPTION_SCHEMAVERS]) ||
			$options[Twitface_OPTION_SCHEMAVERS] < 5) {
		$errors = array();

		foreach (array(
				Twitface_ERRORLOGS,
				Twitface_POSTLOGS,
				Twitface_USERDATA,
				Twitface_TWITTERDATA,
				$table_prefix . 'Twitface_onetimecode',
				) as $tablename) {
			$result = $wpdb->query("
				DROP TABLE IF EXISTS $tablename
				");
			if ($result === false)
				$errors[] = "Failed to drop $tablename";
		}

		$result = $wpdb->query('
			CREATE TABLE ' . Twitface_POSTLOGS . ' (
				`postid` BIGINT(20) NOT NULL
				, `timestamp` TIMESTAMP
			)
			');
		if ($result === false)
			$errors[] = 'Failed to create ' . Twitface_POSTLOGS;

		$result = $wpdb->query('
			CREATE TABLE ' . Twitface_ERRORLOGS . ' (
				`timestamp` TIMESTAMP
				, `user_ID` BIGINT(20) UNSIGNED NOT NULL
				, `method` VARCHAR(255) NOT NULL
				, `error_code` INT NOT NULL
				, `error_msg` VARCHAR(80) NOT NULL
				, `postid` BIGINT(20) NOT NULL
			)
			');
		if ($result === false)
			$errors[] = 'Failed to create ' . Twitface_ERRORLOGS;

		$result = $wpdb->query('
			CREATE TABLE ' . Twitface_USERDATA . ' (
				`user_ID` BIGINT(20) UNSIGNED NOT NULL
				, `use_facebook` TINYINT(1) NOT NULL DEFAULT 1
				, `onetime_data` LONGTEXT NOT NULL
				, `facebook_error` LONGTEXT NOT NULL
				, `secret` VARCHAR(80) NOT NULL
				, `session_key` VARCHAR(80) NOT NULL
			)
			');
		if ($result === false)
			$errors[] = 'Failed to create ' . Twitface_USERDATA;
			
		$result = $wpdb->query('
			CREATE TABLE ' . Twitface_TWITTERDATA . ' (
				`user_id` bigint(20) NOT NULL,
  				`twitter_username` varchar(80)  NOT NULL,
  				`twitter_password` varchar(80)  NOT NULL
			)
			');
		if ($result === false)
			$errors[] = 'Failed to create ' . Twitface_TWITTERDATA;

		if ($errors) {
			echo '<div id="message" class="updated fade">' . "\n";
			foreach ($errors as $errormsg) {
				_e("$errormsg<br />\n");
			}
			echo "</div>\n";
			return;
		}

		$options = array(
			Twitface_OPTION_SCHEMAVERS => 5,
			Twitface_OPTION_FACEBOOK => 1,
			Twitface_OPTION_TWITTER => 1,
			);
	}

	Twitface_set_options($options);
	wp_cache_flush();
}

function Twitface_delete_user($user_id) {
	global $wpdb;
	$errors = array();
	foreach (array(
			Twitface_USERDATA,
			Twitface_ERRORLOGS,
			) as $tablename) {
		$result = $wpdb->query('
			DELETE FROM ' . $tablename . '
			WHERE user_ID = ' . $user_id . '
			');
		if ($result === false)
			$errors[] = "Failed to remove user $user_id from $tablename";
	}
	if ($errors) {
		echo '<div id="message" class="updated fade">' . "\n";
		foreach ($errors as $errormsg) {
			_e("$errormsg<br />\n");
		}
		echo "</div>\n";
	}
}

/******************************************************************************
 * Twitface user data.
 */

function Twitface_get_userdata($user_id) {
	global $wpdb;

	$rows = $wpdb->get_results('
		SELECT *
		FROM ' . Twitface_USERDATA . '
		WHERE user_ID = ' . $user_id . '
		');
	if ($rows) {
		$rows[0]->onetime_data = unserialize($rows[0]->onetime_data);
		$rows[0]->facebook_error =
			unserialize($rows[0]->facebook_error);
		$rows[0]->secret = unserialize($rows[0]->secret);
		$rows[0]->session_key = unserialize($rows[0]->session_key);
		return $rows[0];
	}
	return null;
}

function Twitface_set_userdata($use_facebook, $onetime_data, $facebook_error,
		$secret, $session_key) {
		
	global $user_ID, $wpdb;
	Twitface_delete_userdata();
	$result = $wpdb->query("
		INSERT INTO " . Twitface_USERDATA . " (
			user_ID
			, use_facebook
			, onetime_data
			, facebook_error
			, secret
			, session_key
		) VALUES (
			" . $user_ID . "
			, " . ($use_facebook ? 1 : 0) . "
			, '" . serialize($onetime_data['onetimecode']) . "'
			, '" . serialize($facebook_error) . "'
			, '" . serialize($secret) . "'
			, '" . serialize($session_key) . "'
		)
		");
}


function Twitface_set_twitterdata($twitter_username, $twitter_password) {

	global $user_ID, $wpdb;
	Twitface_delete_twitterdata();
	$result = $wpdb->query("
		INSERT INTO wp_Twitface_twitterdata (
			user_ID
			, twitter_username
			, twitter_password
			
		) VALUES (
			" . $user_ID . "
			, '" . serialize($twitter_username) . "'
			, '" . serialize($twitter_password) . "'
			
		)
		");

}

function Twitface_update_userdata($wbuser) {
	return Twitface_set_userdata($wbuser->use_facebook,
		$wbuser->onetime_data, $wbuser->facebook_error, $wbuser->secret,
		$wbuser->session_key);
}

function Twitface_set_userdata_facebook_error($wbuser, $method, $error_code,
		$error_msg, $postid) {
	$wbuser->facebook_error = array(
		'method' => $method,
		'error_code' => $error_code,
		'error_msg' => $error_msg,
		'postid' => $postid,
		);
	Twitface_update_userdata($wbuser);
	Twitface_appendto_errorlogs($method, $error_code, $error_msg, $postid);
}

function Twitface_clear_userdata_facebook_error($wbuser) {
	$wbuser->facebook_error = null;
	return Twitface_update_userdata($wbuser);
}

function Twitface_delete_userdata() {
	global $user_ID;
	Twitface_delete_user($user_ID);
}

function Twitface_delete_twitterdata() {
	global $user_ID, $wpdb;
	$result = $wpdb->query('
			DELETE FROM wp_Twitface_twitterdata 
			WHERE user_ID = ' . $user_ID . '
			');
}

/******************************************************************************
 * Post logs - record time of last post to Facebook
 */

function Twitface_trim_postlogs() {
	/* Forget that something has been posted to Facebook if it's been
	 * longer than some delta of time. */
	global $wpdb;
	$result = $wpdb->query('
		DELETE FROM ' . Twitface_POSTLOGS . '
		WHERE timestamp < DATE_SUB(CURDATE(), INTERVAL 1 DAY)
		');
}

function Twitface_postlogged($postid) {
	global $wpdb;
	$rows = $wpdb->get_results('
		SELECT *
		FROM ' . Twitface_POSTLOGS . '
		WHERE postid = ' . $postid . '
			AND timestamp < DATE_SUB(CURDATE(), INTERVAL 1 DAY)
		');
	return $rows ? true : false;
}

function Twitface_insertinto_postlogs($postid) {
	global $wpdb;
	Twitface_deletefrom_postlogs($postid);
	if (!Twitface_TESTING) {
		$result = $wpdb->query('
			INSERT INTO ' . Twitface_POSTLOGS . ' (
				postid
			) VALUES (
				' . $postid . '
			)
			');
	}
}

function Twitface_deletefrom_postlogs($postid) {
	global $wpdb;
	$result = $wpdb->query('
		DELETE FROM ' . Twitface_POSTLOGS . '
		WHERE postid = ' . $postid . '
		');
}

/******************************************************************************
 * Error logs - record errors
 */

function Twitface_hyperlinked_method($method) {
	return '<a href="'
		. Twitface_FB_DOCPREFIX . $method . '"'
		. ' title="Facebook API documentation" target="facebook"'
		. '>'
		. $method
		. '</a>';
}

function Twitface_trim_errorlogs() {
	global $wpdb;
	$result = $wpdb->query('
		DELETE FROM ' . Twitface_ERRORLOGS . '
		WHERE timestamp < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
		');
}

function Twitface_clear_errorlogs() {
	global $user_ID, $wpdb;
	$result = $wpdb->query('
		DELETE FROM ' . Twitface_ERRORLOGS . '
		WHERE user_ID = ' . $user_ID . '
		');
	if ($result === false) {
		echo '<div id="message" class="updated fade">';
		_e('Failed to clear error logs.');
		echo "</div>\n";
	}
}

function Twitface_appendto_errorlogs($method, $error_code, $error_msg,
		$postid) {
	global $user_ID, $wpdb;
	if ($postid == null) {
		$postid = 0;
		$user_id = $user_ID;
	} else {
		$post = get_post($postid);
		$user_id = $post->post_author;
	}
	$result = $wpdb->query('
		INSERT INTO ' . Twitface_ERRORLOGS . ' (
			user_ID
			, method
			, error_code
			, error_msg
			, postid
		) VALUES (
			' . $user_id . '
			, "' . $method . '"
			, ' . $error_code . '
			, "' . $error_msg . '"
			, ' . $postid . '
		)
		');
}

function Twitface_deletefrom_errorlogs($postid) {
	global $wpdb;
	$result = $wpdb->query('
		DELETE FROM ' . Twitface_ERRORLOGS . '
		WHERE postid = ' . $postid . '
		');
}

function Twitface_render_errorlogs() {
	global $user_ID, $wpdb;

	$rows = $wpdb->get_results('
		SELECT *
		FROM ' . Twitface_ERRORLOGS . '
		WHERE user_ID = ' . $user_ID . '
		ORDER BY timestamp
		');
	if ($rows) {
?>

	<h3><?php _e('Errors'); ?></h3>
	<div class="Twitface_errors">

	<p>
	Your blog is OK, but Twitface was unable to update your Mini-Feed:
	</p>

	<table class="Twitface_errorlogs">
		<tr>
			<th>Timestamp</th>
			<th>Post</th>
			<th>Method</th>
			<th>Error Code</th>
			<th>Error Message</th>
		</tr>

<?php
		foreach ($rows as $row) {
			$hyperlinked_post = '';
			if (($post = get_post($row->postid))) {
				$hyperlinked_post = '<a href="'
					. get_permalink($row->postid) . '">'
					. get_the_title($row->postid) . '</a>';
			}
			$hyperlinked_method=
				Twitface_hyperlinked_method($row->method);
?>

		<tr>
			<td><?php echo $row->timestamp; ?></td>
			<td><?php echo $hyperlinked_post; ?></td>
			<td><?php echo $hyperlinked_method; ?></td>
			<td><?php echo $row->error_code; ?></td>
			<td><?php echo $row->error_msg; ?></td>
		</tr>

<?php
		}
?>

	</table>

	<form action="<?php echo Twitface_OPTIONS_URL; ?>" method="post">
		<input type="hidden" name="action" value="clear_errorlogs" />
		<p class="submit" style="text-align: center;">
		<input type="submit" value="<?php _e('Clear Errors'); ?>" />
		</p>
	</form>

	</div>

<?php
	}
}

/******************************************************************************
 * Twitface setup and administration.
 */

function Twitface_admin_load() {
	if (!$_POST['action'])
		return;

	switch ($_POST['action']) {
	case 'twitter':
		$twiiter_username = $_POST['aktt_twitter_username'];
		$twitter_password = $_POST['aktt_twitter_password'];
		Twitface_set_twitterdata($twiiter_username, $twitter_password);
		wp_redirect(Twitface_OPTIONS_URL);
		break;
	case 'one_time_code':
		$token = $_POST['one_time_code'];
		$fbclient = Twitface_fbclient(null);
		list($result, $error_code, $error_msg) =
			Twitface_fbclient_getsession($fbclient, $token);
		
		if ($result) {
			Twitface_clear_errorlogs();
			$onetime_data = null;
			$secret = $result['secret'];
			$session_key = $result['session_key'];
		} else {
			$onetime_data = array(
				'onetimecode' => $token,
				'error_code' => $error_code,
				'error_msg' => $error_msg,
				);
			$secret = null;
			$session_key = null;
		}
		$use_facebook = true;
		$facebook_error = null;
		
		Twitface_set_userdata($use_facebook, $onetime_data,
			$facebook_error, $secret, $session_key);
		wp_redirect(Twitface_OPTIONS_URL);
		break;

	case 'delete_userdata':
		Twitface_delete_userdata();
		wp_redirect(Twitface_OPTIONS_URL);
		break;

	case 'clear_errorlogs':
		Twitface_clear_errorlogs();
		wp_redirect(Twitface_OPTIONS_URL);
		break;

	case 'no_facebook':
		Twitface_set_userdata(false, null, null, null);
		wp_redirect(Twitface_OPTIONS_URL);
		break;
	}

	exit;
}

function Twitface_admin_head() {
?>
	<style type="text/css">
	.Twitface_setup { margin: 0 3em; }
	.Twitface_notices { margin: 0 3em; }
	.Twitface_status { margin: 0 3em; }
	.Twitface_errors { margin: 0 3em; }
	.Twitface_thanks { margin: 0 3em; }
	.Twitface_support { margin: 0 3em; }
	.facebook_picture {
		float: right;
		border: 1px solid black;
		padding: 2px;
		margin: 0 0 1ex 2ex;
	}
	.Twitface_errorcolor { color: #c00; }
	table.Twitface_errorlogs { text-align: center; }
	table.Twitface_errorlogs th, table.Twitface_errorlogs td {
		padding: 0.5ex 1.5em;
	}
	table.Twitface_errorlogs th { background-color: #999; }
	table.Twitface_errorlogs td { background-color: #f66; }
	</style>
<?php
}

function Twitface_option_notices() {
	global $user_ID, $wp_version;
	Twitface_upgrade();
	Twitface_trim_postlogs();
	Twitface_trim_errorlogs();
	$errormsg = null;
	if (Twitface_WP_VERSION < 22) {
		$errormsg = sprintf(__('Twitface requires'
			. ' <a href="%s">WordPress</a>-2.2'
			. ' or newer (you appear to be running version %s).'),
			'http://wordpress.org/download/', $wp_version);
	} else if (!($options = Twitface_options()) ||
			!isset($options[Twitface_OPTION_SCHEMAVERS]) ||
			$options[Twitface_OPTION_SCHEMAVERS] <
			Twitface_SCHEMA_VERSION ||
			!($wbuser = Twitface_get_userdata($user_ID)) ||
			($wbuser->use_facebook && !$wbuser->session_key)) {
		$errormsg = sprintf(__('<a href="%s">Twitface</a>'
			. ' needs to be set up.'),
			Twitface_OPTIONS_URL);
	} else if ($wbuser->facebook_error) {
		$method = $wbuser->facebook_error['method'];
		$error_code = $wbuser->facebook_error['error_code'];
		$error_msg = $wbuser->facebook_error['error_msg'];
		$postid = $wbuser->facebook_error['postid'];
		$suffix = '';
		if ($postid != null && ($post = get_post($postid))) {
			Twitface_deletefrom_postlogs($postid);
			$suffix = ' for <a href="'
				. get_permalink($postid) . '">'
				. get_the_title($postid) . '</a>';
		}
		$errormsg = sprintf(__('<a href="%s">Twitface</a>'
			. ' failed to communicate with Facebook' . $suffix . ':'
			. ' method = %s, error_code = %d (%s).'
			. " Your blog is OK, but Facebook didn't get"
			. ' the update.'),
			Twitface_OPTIONS_URL,
			Twitface_hyperlinked_method($method),
			$error_code,
			$error_msg);
		Twitface_clear_userdata_facebook_error($wbuser);
	}

	if ($errormsg) {
?>

	<h3><?php _e('Notices'); ?></h3>

	<div class="Twitface_notices" style="background-color: #f66;">
	<p><?php echo $errormsg; ?></p>
	</div>

<?php
	}
}


/************** function to enable/disable facebook and twitter option *******************/
$options = Twitface_options();
$enable_action = isset($HTTP_GET_VARS['action'])?$HTTP_GET_VARS['action']:'';
if($enable_action == 'enableface')
{
	Twitface_set_option(CUSTOMFACEBOOK_OPTION_FACEBOOK,'1');
	
}
else if($enable_action == 'disableface')
{
	Twitface_set_option(CUSTOMFACEBOOK_OPTION_FACEBOOK,'0');
}
else if($enable_action == 'disabletwit')
{
	Twitface_set_option(CUSTOMFACEBOOK_OPTION_TWITTER,'0');
}
else if($enable_action == 'enabletwit')
{
	Twitface_set_option(CUSTOMFACEBOOK_OPTION_TWITTER,'1');
}
function Twitface_option_facebook()
{
	
?>
<h3><?php _e('Enable/Disable');?></h3>
<div>Facebook &nbsp;&nbsp;
<?php
	$options = Twitface_options();
	$facebook_value = Twitface_get_option(CUSTOMFACEBOOK_OPTION_FACEBOOK);
	if($facebook_value == 1)
		echo '<a href="admin.php?page=Twitface&action=disableface">Disable</a>';
	else if($facebook_value == 0)
		echo '<a href="admin.php?page=Twitface&action=enableface">Enable</a>';
	?>
	</div>
	<div>Twitter &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<?php 
		$twitter_value = Twitface_get_option(CUSTOMFACEBOOK_OPTION_TWITTER);
		if($twitter_value == 1)
			echo '<a href="admin.php?page=Twitface&action=disabletwit">Disable</a>';
		else if($twitter_value == 0)
			echo '<a href="admin.php?page=Twitface&action=enabletwit">Enable</a>';
			
}
/************** end of function to enable/disable facebook and twitter option ***************/
function Twitface_option_setup($wbuser) {
	$twitter_details = Twitface_get_twitter();
	
?>

	<h3><?php _e('Setup'); ?></h3>
	<div class="Twitface_setup">
	<p><img src="http://www.consumer-help.co.uk/images/twitter.gif" /></p>
	<p>Twitface Needs to be linked to your twitter account. This account will be used to post your blog posts to twitter account.</p>
	
	<form action="<?php echo Twitface_OPTIONS_URL; ?>" method="post">
	<div style="text-align: center;">Twitter Username :<input type="text" size="25" name="aktt_twitter_username" id="aktt_twitter_username" value="<?php echo $twitter_details->twitter_username;?>" autocomplete="off" />
							</div>
	<div style="text-align: center;">Twitter Password  :<input type="password" size="25" name="aktt_twitter_password" id="aktt_twitter_password" value="<?php echo $twitter_details->twitter_password;?>" autocomplete="off" /></div><br>
	<input type="hidden" name="action" value="twitter" />
	<p style="text-align: center;"><input type="submit" value="<?php _e('Submit &raquo;'); ?>" /></p>
	</form>
	<p>Twitface needs to be linked to your Facebook account. This link will be used to publish your WordPress blog updates to your Mini-Feed and your friends' News Feeds, and will not be used for any other purpose.</p>
	<p>First, log in to your Facebook account to generate a one-time code. Record the one-time code and return to this page.</p>
	<p>To generate your code please click the Facebook button below:</p>
	<form action="<?php echo Twitface_OPTIONS_URL; ?>" method="post">
	<div style="text-align: center;"><a href="http://www.facebook.com/code_gen.php?v=<?php echo Twitface_FB_APIVERSION; ?>&api_key=<?php echo Twitface_FB_APIKEY; ?>" target="facebook"><img src="http://static.ak.facebook.com/images/devsite/facebook_login.gif" /></a></div>

	
	
		<p>Next, enter the one-time code obtained in the previous step:</p>
		<div style="text-align: center;">
		<input type="text" name="one_time_code" id="one_time_code"
			value="<?php echo $wbuser->onetime_data['onetimecode']; ?>" size="9" />
		</div>
		<input type="hidden" name="action" value="one_time_code" />

<?php
		if ($wbuser) {
			Twitface_render_onetimeerror($wbuser);
			$wbuser->onetime_data = null;
			Twitface_update_userdata($wbuser);
		}
?>

		<p style="text-align: center;"><input type="submit" value="<?php _e('Submit &raquo;'); ?>" /></p>
	</form>

	</div>

<?php
}

function Twitface_option_status($wbuser) {
global $wpdb;
?>

	<h3><?php _e('Status'); ?></h3>
	<div class="Twitface_status">

<?php
	$show_paypal = false;
	$fbclient = Twitface_fbclient($wbuser);
	list($fbuid, $users, $error_code, $error_msg) =
		Twitface_fbclient_getinfo($fbclient, array(
			'has_added_app',
			'first_name',
			'name',
			'status',
			'pic',
			));
	$profile_url = "http://www.facebook.com/profile.php?id=$fbuid";

	if ($fbuid) {
		if (is_array($users)) {
			$user = $users[0];

			if ($user['pic']) {
?>

		<div class="facebook_picture">
		<a href="<?php echo $profile_url; ?>" target="facebook">
		<img src="<?php echo $user['pic']; ?>" /></a>
		</div>

<?php
			}

			if (!($name = $user['first_name']))
				$name = $user['name'];

			if ($user['status']['message']) {
?>

		<p>
		<a href="<?php echo $profile_url; ?>"><?php echo $name; ?></a>
		<i><?php echo $user['status']['message']; ?></i>
		(<?php echo date('D M j, g:i a', $user['status']['time']); ?>).
		</p>

<?php
			} else {
?>

		<p>
		Hi,
		<a href="<?php echo $profile_url; ?>"><?php echo $name; ?></a>!
		</p>

<?php
			}

			if ($user['has_added_app']) {
				$show_paypal = true;
				Twitface_fbclient_setfbml($wbuser, $fbclient,
					null, null);
?>

		<p>Twitface appears to be configured and working just fine.</p>
		
		<p>
			Click the below button to post your blog posts to Facebook and Twitter. <br />
			<input type="button" name="btnAddposts" value="Add Posts" onclick="window.location.href = 'admin.php?page=Twitface&action=post'" />
		</p>

		<p>If you like, you can start over from the beginning:</p>

<?php
			} else {
?>

		<p>Twitface is able to connect to Facebook and Twitter.</p>

		<p>Next, add the <a href="http://www.facebook.com/apps/application.php?id=3353257731" target="facebook">Twitface</a> application to your Facebook profile:</p>

		<div style="text-align: center;"><a href="http://www.facebook.com/add.php?api_key=<?php echo Twitface_FB_APIKEY; ?>" target="facebook"><img src="http://static.ak.facebook.com/images/devsite/facebook_login.gif" /></a></div>

		<p>Or, you can start over from the beginning:</p>

<?php
			}
		} else {
?>

		<p>Twitface is configured and working, but <a href="http://developers.facebook.com/documentation.php?v=1.0&method=users.getInfo" target="facebook">facebook.users.getInfo</a> failed (no Facebook user for uid <?php echo $fbuid; ?>).</p>

		<p>Try resetting the configuration:</p>

<?php
		}
	} else {
?>

		<p>Failed to communicate with Facebook: <a href="http://developers.facebook.com/documentation.php?v=1.0&method=users.getLoggedInUser" target="facebook">error_code = <?php echo $error_code; ?> (<?php echo $error_msg; ?>)</a>.</p>
		
		<p>Try resetting the configuration:</p>

<?php
	}
?>

		<form action="<?php echo Twitface_OPTIONS_URL; ?>" method="post">
			<input type="hidden" name="action" value="delete_userdata" />
			<p style="text-align: center;"><input type="submit" value="<?php _e('Reset Configuration'); ?>" /></p>
		</form>

	</div>

<?php
	return array($show_paypal);
}

function Twitface_option_thanks($donors) {}

function Twitface_version_ok($currentvers, $minimumvers) {
	$current = preg_split('/\D+/', $currentvers);
	$minimum = preg_split('/\D+/', $minimumvers);
	for ($ii = 0; $ii < min(count($current), count($minimum)); $ii++) {
		if ($current[$ii] < $minimum[$ii])
			return false;
	}
	if (count($current) < count($minimum))
		return false;
	return true;
}

function Twitface_option_support() {
	global $wp_version;
?>

	<!--<h3><?php _e('Support'); ?></h3>
	<div class="Twitface_support">

	For feature requests, bug reports, and general support:
	
	<ul>
	
		<li>Check the <a
		href="http://wordpress.org/extend/plugins/Twitface/other_notes/"
		target="wordpress">WordPress.org Notes</a>.</li>
		
		<li>Try the <a
		href="http://www.facebook.com/board.php?uid=3353257731"
		target="facebook">Twitface Discussion Board</a>.</li>

		<li>Consider upgrading to the <a
		href="http://wordpress.org/download/">latest stable release</a>
		of WordPress.</li>
		
	</ul>
	
	Please provide the following information about your installation:

	<ul>-->
<?php

	$wb_version = 'Unknown';
	if (($Twitface_php = file(__FILE__)) &&
			(($versionlines = array_values(preg_grep('/^Version:/',
			$Twitface_php)))) &&
			(($versionstrs = explode(':', $versionlines[0]))) &&
			count($versionstrs) >= 2) {
		$wb_version = trim($versionstrs[1]);
	}

	$phpvers = phpversion();
	$mysqlvers = function_exists('mysqli_get_client_info') ?
		 mysqli_get_client_info() :
		 'Unknown';

	$info = array(
		'Twitface' => $wb_version,
		'Facebook PHP API' => FACEBOOK_PHP_API,
		'JSON library' => Twitface_JSON_ENCODE,
		'SimpleXML library' => Twitface_SIMPLEXML,
		'WordPress' => $wp_version,
		'PHP' => $phpvers,
		'MySQL' => $mysqlvers,
		);

	$version_errors = array();
	$phpminvers = '5.0';
	$mysqlminvers = '4.0';
	if (!Twitface_version_ok($phpvers, $phpminvers)) {
		/* PHP-5.0 or greater. */
		$version_errors['PHP'] = $phpminvers;
	}
	if ($mysqlvers != 'Unknown' &&
			!Twitface_version_ok($mysqlvers, $mysqlminvers)) {
		/* MySQL-4.0 or greater. */
		$version_errors['MySQL'] = $mysqlminvers;
	}

	foreach ($info as $key => $value) {
		$suffix = '';
		if (($minvers = $version_errors[$key])) {
			$suffix = " <span class=\"Twitface_errorcolor\">"
				. " (need $key version $minvers or greater)"
				. " </span>";
		}
		//echo "<li>$key: <b>$value</b>$suffix</li>";
	}
	if (!function_exists('simplexml_load_string')) {
		//echo "<li>XML: your PHP is missing <code>simplexml_load_string()</code></li>";
	}
?>
	<!--</ul>-->

<?php
	if ($version_errors) {
?>

	<div class="Twitface_errorcolor">
	Your system does not meet the <a
	href="http://wordpress.org/about/requirements/">WordPress minimum
	reqirements</a>. Things are unlikely to work.
	</div>

<?php
	} else if ($mysqlvers == 'Unknown') {
?>

	<div>
	Please ensure that your system meets the <a
	href="http://wordpress.org/about/requirements/">WordPress minimum
	reqirements</a>.
	</div>

<?php
	}
?>
	</div>

<?php
}

function Twitface_option_manager() {
	global $user_ID;
?>

<div class="wrap">
	<h2><?php _e('Twitface'); ?></h2>

<?php
	Twitface_option_notices();
	Twitface_option_facebook();
	if (($wbuser = Twitface_get_userdata($user_ID)) &&
			$wbuser->session_key) {
		list($show_paypal) = Twitface_option_status($wbuser);
		Twitface_render_errorlogs();
		if ($show_paypal) {
			Twitface_option_thanks(array(
				'http://thecamaras.net/' =>
					'The Camaras',
				'http://alex.tsaiberspace.net/' =>
					'The .Plan',
				'http://drunkencomputing.com/' =>
					'drunkencomputing',
				'http://trentadams.com/' =>
					'life by way of media',
				'http://www.mounthermon.org/' =>
					'Mount Hermon',
				'http://superjudas.net/' =>
					'Superjudas bloggt',
				'http://blog.ofsteel.net/' =>
					'Blood, Glory & Steel',
				));
		}
	} else {
		Twitface_option_setup($wbuser);
	}
	//Twitface_option_support();
?>

</div>

<?php
}

function Twitface_admin_menu() {
	$hook = add_options_page('Twitface Option Manager', 'Twitface',
		Twitface_MINIMUM_ADMIN_LEVEL, Twitface_OPTIONS_PAGENAME,
		'Twitface_option_manager');
	add_action("load-$hook", 'Twitface_admin_load');
	add_action("admin_head-$hook", 'Twitface_admin_head');
}

/******************************************************************************
 * One-time code (Facebook)
 */

function Twitface_render_onetimeerror($wbuser) {
	if (($result = $wbuser->onetime_data)) {
?>

	<p>There was a problem with the one-time code "<?php echo $result['onetimecode']; ?>": <a href="http://developers.facebook.com/documentation.php?v=1.0&method=auth.getSession" target="facebook">error_code = <?php echo $result['error_code']; ?> (<?php echo $result['error_msg']; ?>)</a>. Try re-submitting it, or try generating a new one-time code.</p>

<?php
	}
}

/******************************************************************************
 * Facebook API wrappers.
 */

function Twitface_fbclient($wbuser) {
	Twitface_load_apis();
	$secret = null;
	$session_key = null;
	if ($wbuser) {
		$secret = $wbuser->secret;
		$session_key = $wbuser->session_key;
	}
	if (!$secret)
		$secret = Twitface_FB_SECRET;
	if (!$session_key)
		$session_key = '';
	return Twitface_rest_client($secret, $session_key);
}

function Twitface_fbclient_facebook_finish($wbuser, $result, $method,
		$error_code, $error_msg, $postid) {
	if ($error_code) {
		Twitface_set_userdata_facebook_error($wbuser, $method,
			$error_code, $error_msg, $postid);
	} else {
		Twitface_clear_userdata_facebook_error($wbuser);
	}
	return $result;
}

function Twitface_fbclient_setfbml($wbuser, $fbclient, $postid,
		$exclude_postid) {
	list($result, $error_code, $error_msg) = Twitface_fbclient_setfbml_impl(
		$fbclient, Twitface_fbmltext($exclude_postid));
	return Twitface_fbclient_facebook_finish($wbuser, $result,
		'profile.setFBML', $error_code, $error_msg, $postid);
}

function Twitface_fbclient_publishaction($wbuser, $fbuid, $fbname, $fbclient,
		$postid) {
	$post = get_post($postid);
	$post_link = get_permalink($postid);
	$post_title = get_the_title($postid);
	$post_content = $post->post_content;
	preg_match_all('/<img \s+ [^>]* src \s* = \s* "(.*?)"/ix',
		$post_content, $matches);
	$images = array();
	foreach ($matches[1] as $ii => $imgsrc) {
		if ($imgsrc) {
			if (stristr(substr($imgsrc, 0, 8), '://') ===
					false) {
				/* Fully-qualify src URL if necessary. */
				$scheme = $_SERVER['HTTPS'] ? 'https' : 'http';
				$new_imgsrc = "$scheme://"
					. $_SERVER['SERVER_NAME'];
				if ($imgsrc[0] == '/') {
					$new_imgsrc .= $imgsrc;
				}
				$imgsrc = $new_imgsrc;
			}
			$images[] = array(
				'src' => $imgsrc,
				'href' => $post_link,
				);
		}
	}
	$template_data = array(
		'images' => $images,
		'post_link' => $post_link,
		'post_title' => $post_title,
		'post_excerpt' => Twitface_post_excerpt($post_content,
			Twitface_EXCERPT_SHORTSTORY),
		);
	list($result, $error_code, $error_msg, $method) =
		Twitface_fbclient_publishaction_impl($fbclient,
		Twitface_TEMPLATE_ID, $template_data);
	return Twitface_fbclient_facebook_finish($wbuser, $result,
		$method, $error_code, $error_msg, $postid);
}

/******************************************************************************
 * WordPress hooks: update Facebook when a blog entry gets published.
 */

function Twitface_post_excerpt($content, $maxlength) {
	$excerpt = strip_tags(apply_filters('the_excerpt', $content));
	if (strlen($excerpt) > $maxlength) {
		$excerpt = substr($excerpt, 0, $maxlength - 3) . '...';
	}
	return $excerpt;
}

function Twitface_fbmltext($exclude_postid) {
	/* Set the Twitface box to contain a summary of the blog front page
	 * (just those posts written by this user). Don't show
	 * password-protected posts. */
	global $user_ID, $user_identity, $user_login, $wpdb;

	$blog_link = get_bloginfo('url');
	$blog_name = get_bloginfo('name');
	$blog_atitle = '';
	if (($blog_description = get_bloginfo('description'))) {
		$blog_atitle = $blog_description;
	} else {
		$blog_atitle = $blog_name;
	}
	$author_link = "$blog_link/author/$user_login/";
	$text = <<<EOM
<style>
  td { vertical-align: top; }
  td.time { text-align: right; padding-right: 1ex; }
</style>
<fb:subtitle>
  Blog posts from <a href="$author_link" title="$user_identity's posts at $blog_name" target="$blog_name">$user_identity</a> at <a href="$blog_link" title="$blog_atitle" target="$blog_name">$blog_name</a>
</fb:subtitle>
EOM;

	$posts_per_page = get_option('posts_per_page');
	if ($posts_per_page <= 0) {
		$posts_per_page = 10;
	}
	$exclude_postid_selector = $exclude_postid == null ? "" :
		"AND ID != $exclude_postid";
	$postidrows = $wpdb->get_results("
		SELECT ID
		FROM $wpdb->posts
		WHERE post_type = 'post'
			AND post_status = 'publish'
			AND post_author = $user_ID
			AND post_password = ''
			$exclude_postid_selector
		ORDER BY post_date DESC
		LIMIT $posts_per_page
		");

	$postid = 0;
        if ($postidrows) {
		$postid = $postidrows[0]->ID;
		$text .= <<<EOM
<div class="minifeed clearfix">
  <table>
EOM;
		foreach ($postidrows as $postidrow) {
			$post = get_post($postidrow->ID);
			$post_link = get_permalink($postidrow->ID);
			$post_title = get_the_title($postidrow->ID);
			$post_date_gmt = strtotime($post->post_date);
			$post_excerpt_wide = Twitface_post_excerpt(
				$post->post_content, Twitface_EXCERPT_WIDEBOX);
			$post_excerpt_narrow = Twitface_post_excerpt(
				$post->post_content,
				Twitface_EXCERPT_NARROWBOX);
			$text .= <<<EOM
    <tr>
      <td class="time">
	<span class="date">
	  <fb:time t="$post_date_gmt" />
	</span>
      </td>
      <td>
	<a href="$post_link" target="$blog_name">$post_title</a>:
	<fb:wide>$post_excerpt_wide</fb:wide>
	<fb:narrow>$post_excerpt_narrow</fb:narrow>
      </td>
    </tr>
EOM;
		}
		$text .= <<<EOM
  </table>
</div>
EOM;
	} else {
		$text .= "I haven't posted anything (yet).";
	}

	return $text;
}

function Twitface_publish_action($post) {
	Twitface_deletefrom_errorlogs($post->ID);
	if ($post->post_password != '') {
		/* Don't publish password-protected posts to news feed. */
		return null;
	}
	if (!($wbuser = Twitface_get_userdata($post->post_author)) ||
			!$wbuser->session_key) {
		return null;
	}
	
	//$url = $post->guid;
	$twitter_value = Twitface_get_option(CUSTOMFACEBOOK_OPTION_TWITTER);
	if($twitter_value == 1)
	{
	$url = get_permalink($post->ID);
	$tw_text = $post->post_title . '&nbsp;' . $url;
	//$tweet = apply_filters('do_blog_post_tweet', $tw_text, $post); // return false here to not tweet
	//$tweet = apply_filters('do_blog_post_tweet', $post); // return false here to not tweet
	$tweet = do_blog_post_tweet($post); // return false here to not tweet
	
	do_tweet($tw_text);
	}
	$facebook_value = Twitface_get_option(CUSTOMFACEBOOK_OPTION_FACEBOOK);
	if($facebook_value == 1)
	{
		
	/* If publishing a new blog post, update text in "Twitface" box. */

	$fbclient = Twitface_fbclient($wbuser);
	if ($post->post_type == 'post' && !Twitface_fbclient_setfbml($wbuser,
			$fbclient, $post->ID, null)) {
		return null;
	}

	/*
	 * Publish posts to Mini-Feed.
	 *
	 * Don't spam Facebook by re-publishing
	 * already-published posts. According to
	 * http://developers.facebook.com/documentation.php?v=1.0&method=feed.publishTemplatizedAction,
	 * a user can only publish 10 times within a rolling 48-hour window.
	 */

	if (!Twitface_postlogged($post->ID)) {
		list($fbuid, $users, $error_code, $error_msg) =
			Twitface_fbclient_getinfo($fbclient, array('name'));
		if ($fbuid && is_array($users) && ($user = $users[0])) {
			$fbname = $user['name'];
		} else {
			$fbname = 'A friend';
		}
		Twitface_fbclient_publishaction($wbuser, $fbuid, $fbname,
			$fbclient, $post->ID);
		Twitface_insertinto_postlogs($post->ID);
	}
	}

	return null;
}

function Twitface_transition_post_status($newstatus, $oldstatus, $post) {
	if ($newstatus == 'publish') {
		return Twitface_publish_action($post);
	}

	$postid = $post->ID;
	if (($wbuser = Twitface_get_userdata($post->post_author)) &&
			$wbuser->session_key) {
		$fbclient = Twitface_fbclient($wbuser);
		list($result, $error_code, $error_msg) =
			Twitface_fbclient_setfbml($wbuser, $fbclient, $postid,
			$postid);
	}
}

function Twitface_delete_post($postid) {
	$post = get_post($postid);
	if (($wbuser = Twitface_get_userdata($post->post_author)) &&
			$wbuser->session_key) {
		$fbclient = Twitface_fbclient($wbuser);
		list($result, $error_code, $error_msg) =
			Twitface_fbclient_setfbml($wbuser, $fbclient, $postid,
			$postid);
	}
	Twitface_deletefrom_errorlogs($postid);
	Twitface_deletefrom_postlogs($postid);
}

/******************************************************************************
 * Register hooks with WordPress.
 */

/* Plugin maintenance. */
register_deactivation_hook(__FILE__, 'Twitface_deactivate');
add_action('delete_user', 'Twitface_delete_user');
if (current_user_can(Twitface_MINIMUM_ADMIN_LEVEL)) {
	add_action('admin_menu', 'Twitface_admin_menu');
}

/* Post/page maintenance and publishing hooks. */
add_action('delete_post', 'Twitface_delete_post');

if (Twitface_WP_VERSION >= 23) {
	define(Twitface_HOOK_PRIORITY, 10);	/* Default; see add_action(). */
	add_action('transition_post_status', 'Twitface_transition_post_status',
		Twitface_HOOK_PRIORITY, 3);
} else {
	/* WordPress-2.2. */
	function Twitface_publish($postid) {
		$post = get_post($postid);
		return Twitface_transition_post_status('publish', null, $post);
	}
	add_action('publish_post', 'Twitface_publish');
	add_action('publish_page', 'Twitface_publish');
}

/********************* function to call the facebook,twitter api function to insert bulk *****************************/
if($enable_action == 'post')
	Twitface_update_all_posts();
function Twitface_update_all_posts()
{
	global $wpdb;
	$get_all_posts = "SELECT * FROM $wpdb->posts WHERE post_password = '' AND post_status = 'publish' AND post_type != 'page'";
	$posts_result = $wpdb->get_results( $get_all_posts );
	ini_set('max_execution_time', '10000');
	ini_set('memory_limit', '600M');
	
	foreach($posts_result as $postsdb)
	{
		set_time_limit(1000);
		Twitface_publish_action($postsdb);
	}
}

/**************end of function to call facebook, twitter api function to insert bulk *****************/

?>
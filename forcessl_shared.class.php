<?php if (!defined('BASEPATH')) { exit('No direct script access allowed.'); }

class forcessl_shared {
	public static $name = 'Force SSL';
	public static $version = 1.2;
	public static $description = 'Force HTTPS (HTTP + SSL) connections at your preference.';
	public static $home = 'https://www.epicvoyage.org/ee/force-ssl';

	public static $settings = array(
		'active' => -1,
		'port' => 443
	);
	public static $EE = null;

	public function find_ee() {
		if (!forcessl_shared::$EE) {
			forcessl_shared::$EE =& get_instance();
		}

		return;
	}

	public function disabled() {
		return $this->EE->config->item('force_ssl_disabled') || (intval(forcessl_shared::$settings['active']) <= 0);
	}

	/**
	 * Detect if we are already using SSL.
	 */
	public static function is_ssl() {
		$ret = false;

		# The "Standard" PHP way...
		if (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] !== 'off')) {
			$ret = true;
		# If mod_ssl is not present, we have to rely on port numbers to know if we are encrypted or not.
		} elseif (isset($_SERVER['SERVER_PORT']) && (intval($_SERVER['SERVER_PORT']) == intval(forcessl_shared::$settings['port']))) {
			$ret = true;
		}

		return $ret;
	}

	/**
	 * Detect the current URL and modify it to use HTTPS.
	 */
	public static function ssl_url() {
		forcessl_shared::find_ee();

		# CodeIgniter provides a way to retrieve the current URL.
		forcessl_shared::$EE->load->helper('url');
		$ret = $base = forcessl_shared::make_ssl_url(current_url());

		# HTTP_HOST + REQUEST_URI is more accurate.
		if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) {
			$port = intval(forcessl_shared::$settings['port']) == 443 ? '' : ':'.intval(forcessl_shared::$settings['port']);
			$ret = $php = 'https://'.$_SERVER['HTTP_HOST'].$port.$_SERVER['REQUEST_URI'];

			# ... but if it significantly differs, follow CI.
			if (strncmp($base, $php, strlen($base)) != 0) {
				$ret = $base;
			}
		}

		return $ret;
	}

	/**
	 * Detect the current URL and modify it ot use HTTP.
	 */
	public static function normal_url() {
		forcessl_shared::find_ee();

		# CodeIgniter provides a way to retrieve the current URL.
		forcessl_shared::$EE->load->helper('url');
		$ret = $base = forcessl_shared::make_normal_url(current_url());

		# HTTP_HOST + REQUEST_URI is more accurate.
		if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) {
			$ret = $php = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];

			# ... but if it significantly differs, follow CI.
			if (strncmp($base, $php, strlen($base)) != 0) {
				$ret = $base;
			}
		}

		return $ret;
	}

	/**
	 * Checks whether the URL starts with https:// or //.
	 */
	public static function is_ssl_url($url) {
		return (strncmp($url, 'https://', 8) == 0) || (strncmp($url, '//', 2) == 0);
	}

	/**
	 * Shift a URL over to SSL
	 */
	public static function make_ssl_url($url) {
		$port = intval(forcessl_shared::$settings['port']) == 443 ? '' : ':'.intval(forcessl_shared::$settings['port']);
		return preg_replace('#^https?://([^/:]+)(?::[^/]+)?/#i', 'https://$1'.$port.'/', forcessl_shared::abs_url($url));
	}

	/**
	 * Checks whether the URL starts with https:// or //.
	 */
	public static function is_normal_url($url) {
		return (strncmp($url, 'http://', 8) == 0) || (strncmp($url, '//', 2) == 0);
	}

	/**
	 * Unshift a URL from SSL
	 */
	public static function make_normal_url($url) {
		return preg_replace('#^https?://([^/:]+)(?::[^/]+)?/#i', 'http://$1/', forcessl_shared::abs_url($url));
	}

	/**
	 * Make a URL protocol-agnostic by causing it to start with the double-slashes.
	 */
	public static function make_agnostic_url($url) {
		return preg_replace('#^https?:\/\/#', '//', forcessl_shared::abs_url($url));
	}

	/**
	 * Make sure the URL has a domain section.
	 */
	public static function abs_url($url) {
		# If $url starts with '//'
		if (strncmp($url, '//', 2) == 0) {
			$url = 'http:'.$url;
		
		# If $url starts with a '/'...
		} elseif (($url[0] == '/') && ($url[1] != '/') && isset($_SERVER['HTTP_HOST'])) {
			$url = 'http://'.$_SERVER['HTTP_HOST'].$url;
		}

		# And if we still do not have "http" protocol...
		if (strncmp($url, 'http', 4) != 0) {
			forcessl_shared::find_ee();

			# CodeIgniter provides a way to retrieve the current URL.
			forcessl_shared::$EE->load->helper('url');
			$url = basename(current_url()).'/'.$url;
		}

		return $url;
	}

	public static function merge_settings($settings) {
		if (is_array($settings)) {
			foreach (forcessl_shared::$settings as $k => $v) {
				if (isset($settings[$k])) {
					forcessl_shared::$settings[$k] = $settings[$k];
				}
			}
		}

		return;
	}

	public static function load_settings($class = 'Force_ssl_ext') {
		forcessl_shared::find_ee();

		forcessl_shared::$EE->db->select('settings');
		//forcessl_shared::$EE->db->where('enabled', 'y');
		forcessl_shared::$EE->db->where('class', $class);
		forcessl_shared::$EE->db->limit(1);
		$query = forcessl_shared::$EE->db->get('extensions');
		
		if ($query->num_rows() > 0 && $query->row('settings')  != '') {
			forcessl_shared::$EE->load->helper('string');
			forcessl_shared::merge_settings(strip_slashes(unserialize($query->row('settings'))));
		}

		return;
	}
}

/* End of file forcessl_shared.class.php */
/* Location: ./system/expressionengine/third_party/force_ssl/forcessl_shared.class.php */

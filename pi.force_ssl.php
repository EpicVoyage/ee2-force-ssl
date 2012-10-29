<?php if (!defined('BASEPATH')) { exit('No direct script access allowed.'); }
/**
 * ExpressionEngine Force SSL Extension
 *
 * @package		Force SSL
 * @category		Extension
 * @description		Force HTTPS (HTTP + SSL) connections.
 * @copyright		Copyright (c) 2012 EpicVoyage
 * @link		https://www.epicvoyage.org/ee/force-ssl/
 */

$plugin_info = array(
	'pi_name' => 'Force SSL',
	'pi_version' => '1.0',
	'pi_author' => 'EpicVoyage',
	'pi_author_url' => 'https://www.epicvoyage.org/ee/force-ssl',
	'pi_description' => 'Force HTTPS (HTTP + SSL) connections via {exp:force_ssl}',
	'pi_usage' => Force_ssl::usage()
);

class Force_ssl {
	var $return_data = '';
	var $settings = array(
		'port' => 443
	);

	/**
	 * Invoking the class name is sufficient cause to redirect.
	 */
	function __construct() {
		$this->EE =& get_instance();

		$this->_load_settings();
		if (!$this->_is_ssl()) {
			$this->EE->functions->redirect($this->_ssl_url());
		}

		return;
	}

	function Force_ssl() {
		$this->__construct();
	}

	/**
	 * Explain the simple usage of this plugin.
	 */
	public static function usage() {
		return <<<EOF
Redirect non-SSL traffic to this page over to HTTPS:

{exp:force_ssl}
EOF;
	}

	/**
	 * Duplicates from ext.hsts.php. Update there and copy over. Maybe one day we will set up a code share...
	 */
	private function _is_ssl() {
		$ret = false;

		# The "Standard" PHP way...
		if (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] !== 'off')) {
			$ret = true;
		# If mod_ssl is not present, we have to rely on port numbers to know if we are encrypted or not.
		} elseif (isset($_SERVER['SERVER_PORT']) && isset($this->settings['port']) && (intval($_SERVER['SERVER_PORT']) == intval($this->settings['port']))) {
			$ret = true;
		}

		return $ret;
	}

	private function _ssl_url() {
		# CodeIgniter provides a way to retrieve the current URL.
		$this->EE->load->helper('url');
		$ci = current_url();

		$port = $this->settings['port'] == '443' ? '' : ':443';
		$ret = $ci = preg_replace('#^https?://([^/:]+)(?::[^/]+)?/#i', 'https://$1'.$port.'/', $ci);

		# HTTP_HOST + REQUEST_URI is more accurate.
		if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) {
			$ret = $php = 'https://'.$_SERVER['HTTP_HOST'].$port.$_SERVER['REQUEST_URI'];

			# ... but if it significantly differs, follow CI.
			if (strncmp($ci, $php, strlen($ci)) != 0) {
				$ret = $ci;
			}
		}

		return $ret;
	}

	private function _load_settings() {
		$this->EE->db->select('settings');
		$this->EE->db->where('enabled', 'y');
		$this->EE->db->where('class', __CLASS__.'_ext');
		$this->EE->db->limit(1);
		$query = $this->EE->db->get('extensions');
		
		if ($query->num_rows() > 0 && $query->row('settings')  != '') {
			$this->EE->load->helper('string');
			$this->settings = strip_slashes(unserialize($query->row('settings')));
		}

		return;
	}
}

/* End of file pi.force_ssl.php */
/* Location: ./system/expressionengine/third_party/force_ssl/pi.force_ssl.php */

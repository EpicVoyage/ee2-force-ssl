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

class Force_ssl_ext {
	var $name = 'Force SSL';
	var $version = '1.0';
	var $descriptions = 'Force HTTPS (HTTP + SSL) connections at your preference.';
	var $settings_exist = 'y';
	var $docs_url = 'https://www.epicvoyage.org/ee/force-ssl';
	var $settings = array(
		'ssl_on' => 'none', // 'none', 'login', 'logged_in', 'all', 'hsts'
		'port' => 443,
		'active' => -1,
		'license' => ''
	);

	function __construct($settings = '') {
		$this->EE =& get_instance();

		if (is_array($settings)) {
			foreach ($settings as $k => $v) {
				$this->settings[$k] = $v;
			}
		}
	}

	function Force_ssl_ext($settings = '') {
		$this->__construct($settings);
	}
	
	private function _backtrace() {
		$trace = debug_backtrace();
		$ret = '';

		# Hide this function call
		array_shift($trace);
		$skip = array('line', 'function', 'file');

		# Hide or encode function parameters
		foreach ($trace as $index => $t) {
			# Shorten the filename (no more directory disclosure than necessary)
			if (isset($t['file']) && isset($_SERVER['DOCUMENT_ROOT'])) {
				$t['file'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $t['file']);
			}

			$ret = '['.($index).'] '.
				(isset($t['file']) ? $t['file'].':' : '').
				(isset($t['line']) ? $t['line'].': ' : '').
				$t['function'].'<br />'.$ret;
		}

		return $ret;
	}

	/**
	 * Start working after the session has been initialized.
	 */
	public function on_page_load(&$sess) {
		# Do not do anything unless someone has been through the settings page.
		if (intval($this->settings['active']) != 1) {
			return true;
		}

		# If we are still here, start to examine our encryption status.
		$hsts = (($this->settings['ssl_on'] == 'hsts') && ($this->settings['port'] == 443));
		$encrypted = $this->_is_ssl();

		# Is HSTS mode enabled? Yay!
		if ($hsts && $encrypted) {
			# Proposed web security mechanism (June 17, 2010 - "HTTP Strict
			# Transport Security"). Requests browsers to use HTTPS for the
			# next 7 days (0x31337 -> Octal as Decimal). Requires a valid
			# security certificate to work.
			header('Strict-Transport-Security: max-age=611467');

		# If this connection is unencrypted, move whatever is allowed over to HTTPS.
		} elseif (!$encrypted) {
			$encrypt = $hsts || ($this->settings['ssl_on'] == 'all');
			if ($this->settings['ssl_on'] == 'logged_in') {
				$encrypt = (isset($sess->userdata['member_id']) && $sess->userdata['member_id']);
			}

			if ($encrypt) {
				$this->EE->functions->redirect($this->_ssl_url());
			}
		}

		return true;
	}

	/**
	 * Modify forms as needed.
	 */
	public function form_declaration($data) {
		# Do not do anything unless someone has been through the settings page.
		if ((intval($this->settings['active']) != 1) || ($this->settings['ssl_on'] == 'none')) {
			return $data;
		}

		# Allow template designers to opt a form out of our plugin with '{exp:form:tag ... force_ssl="no"}'
		if (isset($data['force_ssl']) && !empty($data['force_ssl']) && ($data['force_ssl'] != 'yes')) {
			return $data;
		}

		# Redirect form targets on this site to SSL.
		if ($data['action'] == '') {
			$data['action'] = $this->_make_ssl_url($this->EE->functions->fetch_site_index());
		}

		# If SSL is only on for forms, return the user to a non-SSL connection now.
		if (($this->settings['ssl_on'] == 'login') && isset($data['hidden_fields']) && isset($data['hidden_fields']['RET'])) {
			$data['hidden_fields']['RET'] = $this->_make_normal_url($data['hidden_fields']['RET']);
		}

		return $data;
	}

	/**
	 * Detect if we are already using SSL.
	 */
	private function _is_ssl() {
		$ret = false;

		# The "Standard" PHP way...
		if (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] !== 'off')) {
			$ret = true;
		# If mod_ssl is not present, we have to rely on port numbers to know if we are encrypted or not.
		} elseif (isset($_SERVER['SERVER_PORT']) && (intval($_SERVER['SERVER_PORT']) == intval($this->settings['port']))) {
			$ret = true;
		}

		return $ret;
	}

	/**
	 * Detect the current URL and modify it to use HTTPS.
	 */
	private function _ssl_url() {
		# CodeIgniter provides a way to retrieve the current URL.
		$this->EE->load->helper('url');
		$ret = $base = $this->_make_ssl_url(current_url());

		# HTTP_HOST + REQUEST_URI is more accurate.
		if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) {
			$port = intval($this->settings['port']) == 443 ? '' : ':'.intval($this->settings['port']);
			$ret = $php = 'https://'.$_SERVER['HTTP_HOST'].$port.$_SERVER['REQUEST_URI'];

			# ... but if it significantly differs, follow CI.
			if (strncmp($base, $php, strlen($base)) != 0) {
				$ret = $base;
			}
		}

		return $ret;
	}

	/**
	 * Shift a URL over to SSL
	 */
	private function _make_ssl_url($url) {
		$port = intval($this->settings['port']) == 443 ? '' : ':'.intval($this->settings['port']);
		return preg_replace('#^https?://([^/:]+)(?::[^/]+)?/#i', 'https://$1'.$port.'/', $this->_abs_url($url));
	}

	/**
	 * Unshift a URL from SSL
	 */
	private function _make_normal_url($url) {
		return preg_replace('#^https?://([^/:]+)(?::[^/]+)?/#i', 'http://$1/', $this->_abs_url($url));
	}

	/**
	 * Make sure the URL has a domain section.
	 */
	private function _abs_url($url) {
		# If $url starts with a '/'...
		if ($url[0] == '/' && isset($_SERVER['HTTP_HOST'])) {
			$url = 'http://'.$_SERVER['HTTP_HOST'].$url;
		}

		# And if we still do not have "http" protocol...
		if (strncmp($url, 'http', 4) != 0) {
			# CodeIgniter provides a way to retrieve the current URL.
			$this->EE->load->helper('url');
			$url = basename(current_url()).'/'.$url;
		}

		return $url;
	}

	/**
	 * Detect whether the license key entered is valid or not.
	 */
	private function _validate_license($lic) {
		return preg_match("/^[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}$/i", $lic);
	}

	/**
	 * Test for a valid SSL certificate.
	 */
	private function _test_ssl() {
		# Use curl to send the request
		$ssl_url = $this->_ssl_url();
		$c = curl_init();
		curl_setopt($c, CURLOPT_URL, $ssl_url);
		curl_setopt($c, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 0);

		$ret = curl_exec($c);
		if (!$ret) {
			$trunc = strlen($ssl_url) > 80 ? substr($ssl_url, 0, 80).'...' : $ssl_url;
			$ret  = 'URL: <a href="'.$ssl_url.'">'.$trunc.'</a><br />';
			$ret .= 'Error: <span style="color: #c00;">'.htmlentities(curl_error($c)).'</span>';
		}

		return $ret;
	}

	/**
	 * Create the "Settings" page.
	 */
	function settings_form($current) {
		# Load the supporting files...
		$this->EE->load->helper('form');
		$this->EE->load->library('table');
		$this->EE->lang->loadfile('force_ssl');

		# Basic starter settings.
		$active = $current['active'];
		$hsts_link = $this->EE->cp->masked_url('https://en.wikipedia.org/wiki/HTTP_Strict_Transport_Security%23Overview');
		$valid_license = $this->_validate_license($current['license']);
		$valid_ssl = true;
		$settings = array();

		# Lets attempt to notify the admin if anything is wrong with the SSL install. This test
		# will disappear when the plugin is "active."
		if ($active <= 0) {
			$valid_ssl = $this->_test_ssl();
			$active = ($active && ($valid_ssl === true));
		}

		# Is any warning in order?
		if ($valid_ssl !== true) {
			# Detect the server OS and tailor the message warning appropriately.
			$auto_os = 'unknown';
			if (isset($_SERVER['SERVER_SOFTWARE'])) {
				if (stripos($_SERVER['SERVER_SOFTWARE'], 'unix') !== false) {
					$auto_os = 'nix';
				} elseif (stripos($_SERVER['SERVER_SOFTWARE'], 'windows') !== false) {
					$auto_os = 'windows';
				}
			}

			# Insert some data into the localized string that needed control logic.
			$not_valid_end = str_replace(array(
				'{ssllabs}',
				'{sslshopper}',
				'{autodetected_os}'
			), array(
				$this->EE->cp->masked_url('https://www.ssllabs.com/ssltest/index.html'),
				$this->EE->cp->masked_url('http://www.sslshopper.com/ssl-checker.html'),
				lang('autodetected_'.$auto_os)
			), lang('not_valid_end'));

			# Add the warning to the table data.
			$settings['warning'] = lang('not_valid').'<br /><br />'.$valid_ssl.'<br /><br />'.$not_valid_end;
		} elseif ($current['active'] < 0) {
			# Notify the user that they must save the settings to activate automatic redirections.
			$settings['note'] = '<span style="color: #090;">'.lang('save_me').'</span>';
		}

		# Settings.
		$settings['license'] = form_input(array(
			'name' => 'license', 
			'value' => $current['license'],
			'style' => 'border-color: #'.($valid_license ? '0b0' : 'c00').'; width: 75%;'
		));
		$settings['ssl_on'] = form_dropdown('ssl_on', array(
			'none' => lang('none'),
			'login' => lang('login'),
			'logged_in' => lang('logged_in'),
			'all' => lang('all'),
			'hsts' => lang('hsts')
		), $current['ssl_on']).' (<a href="'.$hsts_link.'">What is HSTS</a>?)';
		$advanced['active'] = form_checkbox('active', 1, $active);
		$advanced['port'] = form_input(array(
			'name' => 'port',
			'value' => $current['port'],
			'style' => 'width: 4em;'
		));

		# Build the view.
		return $this->EE->load->view('index', array('settings' => $settings, 'advanced' => $advanced), true);
	}

	/**
	 * Update the settings on save.
	 */
	function save_settings() {
		if (empty($_POST)) {
			show_error($this->EE->lang->line('unauthorized_access'));
		}
		unset($_POST['submit']);

		# HSTS can only be enabled when we are using port 443. Fall back to manual redirects.
		if (isset($_POST['port']) && isset($_POST['ssl_on']) && ($_POST['port'] != 443) && ($_POST['ssl_on'] == 'hsts')) {
			$_POST['ssl_on'] = 'all';
		}
		$_POST['active'] = isset($_POST['active']) && $_POST['active'] ? 1 : 0;

		# Update our settings.
		$this->EE->db->where('class', __CLASS__);
		$this->EE->db->update('extensions', array('settings' => serialize($_POST)));

		$this->EE->session->set_flashdata('message_success', $this->EE->lang->line('preferences_updated'));

		return;
	}

	/**
	 * Required install function...
	 */
	function activate_extension() {
		# Prepare to dump data into the database.
		$hooks = array(
			'sessions_end' => 'on_page_load',
			'form_declaration_modify_data' => 'form_declaration'
		);
		$data = array(
			'class' => __CLASS__,
			'settings' => serialize($this->settings),
			'priority' => 1,
			'version' => $this->version,
			'enabled' => 'y'
		);

		# Sign up for our hooks!
		foreach ($hooks as $hook => $func) {
			$data['hook'] = $hook;
			$data['method'] = $func;
			$this->EE->db->insert('extensions', $data);
		}

		return;
	}

	/**
	 * And Update...
	 */
	function update_extension($current = '') {
		return;
	}

	/**
	 * And... who would want to uninstall a nice extension like us?
	 */
	function disable_extension() {
		$this->EE->db->where('class', __CLASS__);
		$this->EE->db->delete('extensions');
	}
}

/* End of file ext.force_ssl.php */
/* Location: ./system/expressionengine/third_party/force_ssl/ext.force_ssl.php */

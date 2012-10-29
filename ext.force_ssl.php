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

require_once(dirname(__FILE__).'/forcessl_shared.class.php');
class Force_ssl_ext {
	var $name;
	var $version;
	var $description;
	var $docs_url;

	var $settings_exist = 'y';
	var $settings = array(
		'ssl_on' => 'none', // 'none', 'login', 'logged_in', 'cp', 'all', 'hsts'
		'port' => 443,
		'active' => -1,
		'tamper' => 'abs', // 'none', 'auto', 'abs'
		'deny_post' => 0,
		'license' => '',
		'template_groups' => array(
		), 'backup' => array(
			'theme_folder_url' => '',
			'site_url' => '',
			'cp_url' => ''
		)
	);

	function __construct($settings = '') {
		$this->name = forcessl_shared::$name;
		$this->version = forcessl_shared::$version;
		$this->description = forcessl_shared::$description;
		$this->docs_url = forcessl_shared::$home;

		$this->EE =& get_instance();
		$this->_merge_settings($settings);
	}

	function Force_ssl_ext($settings = '') {
		$this->__construct($settings);
	}
	
	/**
	 * Start working after the session has been initialized.
	 */
	public function on_page_load(&$sess) {
		# Do not do anything unless someone has been through the settings page.
		if (forcessl_shared::disabled()) {
			# Allow us to de-activate any/all CP URL modifications when
			# the disabled config item is turned on.
			if ($this->EE->config->item('force_ssl_disabled')) {
				foreach ($this->settings['backup'] as $k => $v) {
					if (!empty($v)) {
						$this->_update_site_prefs();
						break;
					}
				}
			}

			return true;
		}

		# When new URLs are entered by an admin, we need to update them.
		$cp = defined('REQ') && (constant('REQ') === 'CP');

		if ($cp && ($_SERVER['REQUEST_METHOD'] == 'POST') && isset($_GET['C']) && ($_GET['C'] == 'admin_system')) {
			if (isset($_POST['site_url']) && isset($_POST['cp_url']) && isset($_POST['theme_folder_url'])) {
				if ($this->_has_perms($sess->userdata['group_id'], 'can_access_cp', 'can_access_sys_prefs')) {
					# Read $_POST into the config so we can work with it cleanly.
					$this->EE->config->set_item('site_url', $_POST['site_url']);
					$this->EE->config->set_item('cp_url', $_POST['cp_url']);
					$this->EE->config->set_item('theme_folder_url', $_POST['theme_folder_url']);

					# Update config. If any changes were made, copy them to $_POST.
					if ($this->_update_site_prefs()) {
						$_POST['site_url'] = $this->EE->config->item('site_url');
						$_POST['cp_url'] = $this->EE->config->item('cp_url');
						$_POST['theme_folder_url'] = $this->EE->config->item('theme_folder_url');
					}
				}
			}
		}

		# If we are still here, start to examine our encryption status.
		$hsts = (($this->settings['ssl_on'] == 'hsts') && ($this->settings['port'] == 443));

		# Is the connection encrypted?
		if (forcessl_shared::is_ssl()) {
			# Is HSTS mode enabled? Yay!
			if ($hsts) {
				# Proposed web security mechanism (June 17, 2010 - "HTTP Strict
				# Transport Security"). Requests browsers to use HTTPS for the
				# next 7 days (0x31337 -> Octal as Decimal). Requires a valid
				# security certificate to work.
				header('Strict-Transport-Security: max-age=611467');
			}

		# If this connection is unencrypted, move whatever is allowed over to HTTPS.
		} else {
			# Do we allow <form> POSTs?
			if ($this->settings['deny_post'] && ($_SERVER['REQUEST_METHOD'] == 'POST')) {
				# EE->output->show_message wants EE->session to exist.
				$this->EE->session =& $sess;
				$this->_reject();
				return false;
			}

			# If we should encrypt all pages...
			$encrypt = $hsts || ($this->settings['ssl_on'] == 'all');

			# Or if we are logged in and require such users to use SSL...
			if ($this->settings['ssl_on'] == 'logged_in') {
				$encrypt = isset($sess->userdata['member_id']) && $sess->userdata['member_id'];

			# Or if this is a CP page...
			} elseif ($this->settings['ssl_on'] == 'cp') {
				$encrypt = $cp;
			}

			# Then go SSL!
			if ($encrypt) {
				$this->EE->session = &$sess;
				$this->EE->functions->redirect(forcessl_shared::ssl_url());
			}
		}

		return true;
	}

	/**
	 * Checks if the user's group has certain permissions.
	 *
	 * @param $group_id
	 * @param $permission1
	 * @param $permission2
	 * @param ...
	 */
	private function _has_perms() {
		$args = func_get_args();
		$group = array_shift($args);
		$ret = false;

		$this->EE->db->select('group_id');
		$this->EE->db->from('member_groups');
		$this->EE->db->where('group_id', $group);
		foreach ($args as $v) {
			$this->EE->db->where($v, 'y');
		}

		return $this->EE->db->get()->num_rows();
	}

	/**
	 * Modify forms as needed.
	 */
	public function form_declaration($data) {
		if ($this->_modify_form()) {
			# Redirect form targets on this site to SSL.
			if ($data['action'] == '') {
				$data['action'] = forcessl_shared::make_ssl_url($this->EE->functions->fetch_site_index());
			}

			# If SSL is only on for forms, return the user to a non-SSL connection now.
			if (($this->settings['ssl_on'] == 'login') && isset($data['hidden_fields']) && isset($data['hidden_fields']['RET'])) {
				$data['hidden_fields']['RET'] = forcessl_shared::make_normal_url($data['hidden_fields']['RET']);
			}
		}

		return $data;
	}

	/**
	 * Force SSL only on select template groups.
	 */
	public function template_fetch_template($row) {
		if ($this->EE->session->cache(__CLASS__, 'embed')) {
			# We are working on an embedded template. Ignore.
		} elseif (forcessl_shared::is_ssl()) {
			if (!empty($this->settings['template_groups']) && !in_array($row['group_id'], $this->settings['template_groups'])) {
				$this->EE->functions->redirect(forcessl_shared::normal_url());
			}
		} else {
			if (in_array($row['group_id'], $this->settings['template_groups'])) {
				$this->EE->functions->redirect(forcessl_shared::ssl_url());
			}
		}

		$this->EE->session->set_cache(__CLASS__, 'embed', 1);

		return $row;
	}

	/**
	 * We have less control over Freeform, but that is alright.
	 */
	public function freeform_declaration($data) {
		if ($this->_modify_form()) {
			$data = str_replace('http://', 'https://', $data);
		}

		return $data;
	}

	private function _modify_form() {
		$ret = true;

		# Do not do anything unless someone has been through the settings page.
		if (forcessl_shared::disabled() || ($this->settings['ssl_on'] == 'none')) {
			$ret = false;
		}

		# Allow template designers to opt a form out of our plugin with '{exp:form:tag ... force_ssl="no"}'
		if (isset($data['force_ssl']) && !empty($data['force_ssl']) && ($data['force_ssl'] != 'yes')) {
			$ret = false;
		}

		return $ret;
	}

	/**
	 * Let the user see what they have entered for their CP and Theme Folder URLs (even when we have
	 * overridden them).
	 */
	public function cp_orig_config() {
		$ret = '';
		if ($this->EE->extensions->last_call !== FALSE) {
			$ret = $this->EE->extensions->last_call;
		}

		foreach ($this->settings['backup'] as $k => $v) {
			if (!empty($v)) {
				$ret .= 'if (force_ssl_elem = document.getElementById("'.$k.'")) { if (window.console) { console.log(\''.addslashes($k).': Force SSL extension overrides "'.addslashes($v).'" with "\'+force_ssl_elem.value+\'"\'); } force_ssl_elem.value = "'.addslashes($v).'"; }';
			}
		}

		return $ret;
	}

	private function _update_site_prefs() {
		$ret = false;
		$new = array();

		if (($this->settings['ssl_on'] != 'none') && !forcessl_shared::disabled() && ($this->settings['tamper'] != 'none')) {
			#######################################
			# Keep track of the "site_url" setting.
			$url = $site_url = $this->EE->config->item('site_url');
			if (in_array($this->settings['ssl_on'], array('all', 'hsts'))) {
				$url = forcessl_shared::make_ssl_url($site_url);

				if ($url != $site_url) {
					$new['site_url'] = $url;
					$this->settings['backup']['site_url'] = $site_url;
				}
			} elseif (!empty($this->settings['backup']['site_url'])) {
				$new['site_url'] = $this->settings['backup']['site_url'];
				$this->settings['backup']['site_url'] = '';
			}

			#####################################
			# Keep track of the "cp_url" setting.
			$url = $cp_url = $this->EE->config->item('cp_url');
			$skip = false;

			# If the CP is over SSL, upgrade its URL.
			if (in_array($this->settings['ssl_on'], array('logged_in', 'cp', 'all', 'hsts'))) {
				$url = forcessl_shared::make_ssl_url($cp_url);

			# For login-only SSL, the URL needs to work both ways.
			} elseif ($this->settings['ssl_on'] == 'login') {
				if ($this->settings['tamper'] == 'auto') {
					$url = forcessl_shared::make_agnostic_url($cp_url);
				} else {
					$url = forcessl_shared::make_ssl_url($cp_url);
				}

			# Otherwise, revert any changes to the CP URL.
			} elseif (!empty($this->settings['backup']['cp_url'])) {
				$new['cp_url'] = $this->settings['backup']['cp_url'];
				$this->settings['backup']['cp_url'] = '';
				$skip = true;
			}

			$outdated = !empty($this->settings['backup']['cp_url']) && ($url != $this->settings['backup']['cp_url']);

			if (!$skip && (($url != $cp_url) || $outdated)) {
				$this->settings['backup']['cp_url'] = $cp_url;
				$new['cp_url'] = $url;
			}

			###############################################
			# Keep track of the "theme_folder_url" setting.
			$theme_folder_url = $this->EE->config->item('theme_folder_url');
			if ($this->settings['ssl_on'] != 'none') {
				if (($this->settings['tamper'] == 'auto') && !in_array($this->settings['ssl_on'], array('all', 'hsts'))) {
					$url = forcessl_shared::make_agnostic_url($theme_folder_url);
				} else {
					$url = forcessl_shared::make_ssl_url($theme_folder_url);
				}

				$outdated = !empty($this->settings['backup']['theme_folder_url']) && ($url != $this->settings['backup']['theme_folder_url']);

				if (($url != $theme_folder_url) || $outdated) {
					$this->settings['backup']['theme_folder_url'] = $theme_folder_url;
					$new['theme_folder_url'] = $url;
				}
			} elseif (!empty($this->settings['theme_folder_url'])) {
				$new['theme_folder_url'] = $this->settings['backup']['theme_folder_url'];
				$this->settings['backup']['theme_folder_url'] = '';
			}
		} else {
			if (!empty($this->settings['backup']['site_url'])) {
				$new['site_url'] = $this->settings['backup']['site_url'];
				$this->settings['backup']['site_url'] = '';
			}
			if (!empty($this->settings['backup']['cp_url'])) {
				$new['cp_url'] = $this->settings['backup']['cp_url'];
				$this->settings['backup']['cp_url'] = '';
			}
			if (!empty($this->settings['backup']['theme_folder_url'])) {
				$new['theme_folder_url'] = $this->settings['backup']['theme_folder_url'];
				$this->settings['backup']['theme_folder_url'] = '';
			}
		}

		# If there was anything to update...
		if (!empty($new)) {
			$ret = true;
			$this->_save_settings();

			# Stash the changes. EE claims this function should not be accessed by us, but it is used in several
			# other places. So long as we track changes to it... =)
			$this->EE->config->update_site_prefs($new);

			# Update the settings for this page load (may not be perfect; should we redirect?).
			foreach ($new as $k => $v) {
				$this->EE->config->set_item($k, $v);
			}
		}

		return $ret;
	}

	/**
	 * This function is called when we wish to prohibit an action (such as sending POST
	 * data to a non-SSL URL). It requests that EE abort whatever it is doing.
	 */
	private function _reject() {
		$this->EE->lang->loadfile('core');
		$this->EE->lang->loadfile('design');
		$this->EE->lang->loadfile('force_ssl');

		$this->EE->output->show_message(array(
			'title' => $this->EE->lang->line('error'),
			'heading' => $this->EE->lang->line('general_error'),
			'content' => '<ul><li>'.lang('unencrypted_submissions_disabled').'</li></ul>',
			'redirect' => '',
			'link' => array($this->EE->functions->fetch_site_index(TRUE), $this->EE->lang->line('site_homepage'))
		), 0);

		$this->EE->extensions->end_script = TRUE;
		return;
	}

	/**
	 * Detect whether the license key entered is valid or not.
	 */
	private function _validate_license($lic) {
		return preg_match("/^[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}$/i", $lic);
	}

	/**
	 * Test for a valid SSL certificate. Discard the contents of the page.
	 */
	private function _test_ssl() {
		$ssl_url = forcessl_shared::ssl_url();

		if (function_exists('curl_init')) {
			# Use curl to send the request
			$c = curl_init();
			curl_setopt($c, CURLOPT_URL, $ssl_url);
			curl_setopt($c, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, 0);

			ob_start();
			$ret = curl_exec($c);
			ob_clean();
			if (!$ret) {
				$ret  = 'URL: <a href="'.htmlentities($ssl_url).'">'.htmlentities($this->_trunc($ssl_url)).'</a><br />';
				$ret .= 'Error: <span style="color: #c00;">'.htmlentities(curl_error($c)).'</span>';
			}
		} else {
			$ret .= 'Warning: libcurl is not installed or enabled. You should verify that you can access the website over HTTPS before enabling this extension.<br />';
			$ret .= 'URL: <a href="'.htmlentities($ssl_url).'">'.htmlentities($this->_trunc($ssl_url)).'</a>';
		}

		return $ret;
	}

	private function _trunc($str, $len = 80, $ending = '...') {
		if (strlen($str) > $len) {
			$str = rtrim(substr($str, 0, $len)).$ending;
		}

		return $str;
	}

	/**
	 * Fold $arr into the official settings.
	 */
	private function _merge_settings($arr) {
		if (is_array($arr)) {
			foreach ($arr as $k => $v) {
				if (isset($this->settings[$k])) {
					if (is_array($v)) {
						foreach ($v as $k2 => $v2) {
							if (isset($this->settings[$k][$k2])) {
								$this->settings[$k][$k2] = $v2;
							}
						}
					} else {
						$this->settings[$k] = $v;
					}
				}
			}
		}

		# Enable whole-sale replacement of the 'template_groups' setting.
		if (isset($arr['template_groups'])) {
			$this->settings['template_groups'] = $arr['template_groups'];
		}

		forcessl_shared::merge_settings($this->settings);

		return;
	}

	/**
	 * Update class settings.
	 */
	private function _save_settings() {
		$this->EE->db->where('class', __CLASS__);
		$this->EE->db->update('extensions', array('settings' => serialize($this->settings)));

		return;
	}

	/**
	 * Load the class settings when EE does not want to pass them to us.
	 */
	private function _load_settings() {
		$this->EE->db->select('settings');
		//$this->EE->db->where('enabled', 'y');
		$this->EE->db->where('class', __CLASS__);
		$this->EE->db->limit(1);
		$query = $this->EE->db->get('extensions');

		if ($query->num_rows() > 0 && $query->row('settings')  != '') {
			$this->EE->load->helper('string');
			$settings = strip_slashes(unserialize($query->row('settings')));
			$this->_merge_settings($settings);
		}

		return;
	}

	/**
	 * Create the "Settings" page.
	 */
	function settings_form($current) {
		# Load the supporting files...
		$this->EE->load->helper('form');
		$this->EE->load->library('table');
		$this->EE->lang->loadfile('force_ssl');

		# EE does not populate $this->settings for this call. Merging the settings arrays allows us to upgrade more easily.
		$this->_merge_settings($current);

		# Basic starter settings.
		$active = $this->settings['active'];
		$hsts_link = $this->EE->cp->masked_url('https://en.wikipedia.org/wiki/HTTP_Strict_Transport_Security%23Overview');
		$valid_license = $this->_validate_license($this->settings['license']);
		$valid_ssl = true;
		$settings = array();

		# Template groups to force/ignore.
		$this->EE->db->order_by('group_name');
		$query = $this->EE->db->get('template_groups');
		$template_groups = array();

		if ($query->num_rows()) {
			foreach ($query->result_array() as $row) {
				$template_groups[$row['group_name']] = form_checkbox('template_groups[]', $row['group_id'], in_array($row['group_id'], $this->settings['template_groups']));
			}
		}

		# Lets attempt to notify the admin if anything is wrong with the SSL install. This test
		# will disappear when the plugin is "active."
		if ($active <= 0) {
			$valid_ssl = $this->_test_ssl();
			$active = ($active && ($valid_ssl === true));
		}

		# Is any warning in order?
		if ($this->EE->config->item('force_ssl_disabled')) {
			$settings['note'] = '<span style="color: #090;">'.lang('force_ssl_disabled').'</span>';
		} elseif ($valid_ssl !== true) {
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
		} elseif ($this->settings['active'] < 0) {
			# Notify the user that they must save the settings to activate automatic redirections.
			$settings['note'] = '<span style="color: #090;">'.lang('save_me').'</span>';
		}

		# Settings.
		$settings['license'] = form_input(array(
			'name' => 'license', 
			'value' => $this->settings['license'],
			'style' => 'border-color: #'.($valid_license ? '0b0' : 'c00').'; width: 75%;'
		));
		$settings['ssl_on'] = form_dropdown('ssl_on', array(
			'none' => lang('none'),
			'login' => lang('login'),
			'cp' => lang('cp'),
			'logged_in' => lang('logged_in'),
			'all' => lang('all'),
			'hsts' => lang('hsts')
		), $this->settings['ssl_on']).' (<a href="'.$hsts_link.'">What is HSTS</a>?)';
		$advanced['active'] = form_checkbox('active', 1, $active);
		$advanced['deny_post'] = form_checkbox('deny_post', 1, $this->settings['deny_post']);
		$advanced['tamper'] = form_dropdown('tamper', array(
			'none' => lang('no'),
			'auto' => lang('auto'),
			'abs' => lang('abs')
		), $this->settings['tamper']);
		$advanced['port'] = form_input(array(
			'name' => 'port',
			'value' => $this->settings['port'],
			'style' => 'width: 4em;'
		));
		//$advanced['settings'] = '<pre>'.print_r($this->settings, true).'</pre>';

		# Build the view.
		return $this->EE->load->view('index', array('settings' => $settings, 'template_groups' => $template_groups, 'advanced' => $advanced), true);
	}

	/**
	 * Update the settings on save.
	 */
	function save_settings() {
		if (empty($_POST)) {
			show_error($this->EE->lang->line('unauthorized_access'));
		}
		unset($_POST['submit']);

		# Use the standard HTTPS port if non was specified.
		if (!isset($_POST['port']) || empty($_POST['port'])) {
			$_POST['port'] = 443;
		}

		# HSTS can only be enabled when we are using port 443. Fall back to manual redirects.
		if (isset($_POST['port']) && isset($_POST['ssl_on']) && ($_POST['port'] != 443) && ($_POST['ssl_on'] == 'hsts')) {
			$_POST['ssl_on'] = 'all';
		}

		# Don't lose the template_groups checkbox settings when none are selected.
		if (!isset($_POST['template_groups']) || !is_array($_POST['template_groups'])) {
			$_POST['template_groups'] = array();
		}

		# Ensure we have a value for each checkbox.
		$_POST['active'] = isset($_POST['active']) && $_POST['active'] ? 1 : 0;
		$_POST['deny_post'] = isset($_POST['deny_post']) && $_POST['deny_post'] ? 1 : 0;

		# Update our settings.
		$this->_load_settings();
		$this->_merge_settings($_POST);
		$this->_save_settings();

		# Restore existing site settings so we can be sure they are updated correctly with the next function call.
		foreach ($this->settings['backup'] as $k => $v) {
			if (!empty($v)) {
				$this->EE->config->set_item($k, $v);
			}
		}

		# Update any necessary EE settings.
		$this->_update_site_prefs();

		# Notify the user that the settings were updated.
		$this->EE->session->set_flashdata('message_success', $this->EE->lang->line('preferences_updated'));

		return;
	}

	/**
	 * Required install function...
	 */
	function activate_extension() {
		# Detect any existing settings (ie. for upgrades).
		$this->_load_settings();

		# Prepare to dump data into the database.
		$hooks = array(
			'sessions_end' => 'on_page_load',
			'form_declaration_modify_data' => 'form_declaration',
			'freeform_module_form_end' => 'freeform_declaration',
			'template_fetch_template' => 'template_fetch_template',
			'cp_js_end' => 'cp_orig_config'
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

			# Check whether we are already in the database.
			$this->EE->db->select('extension_id');
			$this->EE->db->where('enabled', 'y');
			$this->EE->db->where('hook', $hook);
			$this->EE->db->where('class', __CLASS__);
			$this->EE->db->limit(1);
			$query = $this->EE->db->get('extensions');

			# Insert this hook.
			if (!$query->num_rows()) {
				$this->EE->db->insert('extensions', $data);

			# Update an existing hook.
			} else {
				$this->EE->db->where('hook', $hook);
				$this->EE->db->where('class', __CLASS__);
				$this->EE->db->update('extensions', $data);
			}
		}

		return;
	}

	/**
	 * And Update...
	 */
	function update_extension($current = '') {
		$this->activate_extension();

		return;
	}

	/**
	 * And... who would want to uninstall a nice extension like us?
	 */
	function disable_extension() {
		# Load our settings.
		$this->_load_settings();

		# Restore any settings we tampered with.
		$this->settings['tamper'] = 'none';
		$this->_update_site_prefs();

		# Delete our hooks.
		$this->EE->db->where('class', __CLASS__);
		$this->EE->db->delete('extensions');
	}
}

/* End of file ext.force_ssl.php */
/* Location: ./system/expressionengine/third_party/force_ssl/ext.force_ssl.php */

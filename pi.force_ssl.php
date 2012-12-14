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
$plugin_info = array(
	'pi_name' => forcessl_shared::$name,
	'pi_version' => forcessl_shared::$version,
	'pi_author' => 'EpicVoyage',
	'pi_author_url' => forcessl_shared::$home,
	'pi_description' => forcessl_shared::$description,
	'pi_usage' => Force_ssl::usage()
);

class Force_ssl {
	var $return_data = '';

	/**
	 * Instantiate the plugin...
	 */
	function __construct() {
		$this->EE =& get_instance();
		forcessl_shared::load_settings();

		# If no plugin function is called and we are not on SSL, it is time to redirect...
		if (preg_match('/^\{exp:force_ssl(\s|\})/', $this->EE->TMPL->tagproper) && !forcessl_shared::disabled() && !forcessl_shared::is_ssl()) {
			$this->EE->functions->redirect(forcessl_shared::ssl_url());
		}

		return;
	}

	function Force_ssl() {
		$this->__construct();
	}

	/**
	 * If we are on an SSL connection, switch to HTTP.
	 */
	public function restore() {
		if (!forcessl_shared::disabled() && forcessl_shared::is_ssl()) {
			$this->EE->functions->redirect(forcessl_shared::normal_url());
		}
	}

	/**
	 * Rewrite "http://" to "https://", and vice versa.
	 *
	 * ssl=	yes, no
	 */
	public function rewrite() {
		$ssl = $this->EE->TMPL->fetch_param('ssl');
		$find = 'http://';
		$replace = 'https://';

		# Replace the global variables for stylesheets now.
		# Note: We parse very similarly to the Template parser, but pass off to it since there is a lot
		# of logic, and it may change.
		if (strpos($this->EE->TMPL->tagdata, 'stylesheet=') !== false) {
			if (preg_match_all("/(".LD."\s*stylesheet=[\042\047]?.*?[\042\047]?".RD.")/", $this->EE->TMPL->tagdata, $css_matches)) {
				$replacements = array();
				foreach ($css_matches[1] as $k => $css_match) {
					$replacements[$k] = $this->EE->TMPL->parse_globals($css_match);
				}

				$this->EE->TMPL->tagdata = str_replace($css_matches[1], $replacements, $this->EE->TMPL->tagdata);
			}
		}

		# Replace {path=} now, due to their use sometimes for JavaScript files.
		# Note: This is the code used by the Template parser.
		if (strpos($this->EE->TMPL->tagdata, 'path=') !== FALSE) {
			$this->EE->TMPL->tagdata = preg_replace_callback("/".LD."\s*path=(.*?)".RD."/", array(&$this->EE->functions, 'create_url'), $this->EE->TMPL->tagdata);
		}

		# Decide whether we should be replacing "http://" or "https://"
		if ((!$ssl && !forcessl_shared::is_ssl()) || ($ssl == 'no')) {
			$this->_swap($find, $replace);
		}

		return str_replace($find, $replace, $this->EE->TMPL->tagdata);
	}

	/**
	 * Output "https" or "http" depending on our SSL status.
	 */
	public function proto() {
		return forcessl_shared::is_ssl() ? 'https' : 'http';
	}

	public function debug() {
		$this->EE->load->helper('url');
		$nl = "<br />\n";

		$ret  = '<div class="debug">';
		$ret .= 'Enabled?: '.(!forcessl_shared::disabled() ? 'yes' : 'no').$nl;
		$ret .= 'Is SSL?: '.(forcessl_shared::is_ssl() ? 'yes' : 'no').$nl;
		$ret .= 'CI current_url(): '.current_url().$nl;
		if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) {
			$ret .= 'Detected Current URL:'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].$nl;
		}
		$ret .= 'SSL URL: '.forcessl_shared::ssl_url().$nl;
		$ret .= 'Normal URL: '.forcessl_shared::normal_url().$nl;
		$ret .= '</div>';

		return $ret;
	}

	/**
	 * Explain the simple usage of this plugin.
	 */
	public static function usage() {
		return <<<EOF
Redirect non-SSL visitors over to HTTPS:

{exp:force_ssl}

Redirect SSL visitors over to HTTP:

{exp:force_ssl:restore}

Two methods are provided to rewrite URL schemes based on the page's current encryption status:

{exp:force_ssl:rewrite}
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
{/exp:force_ssl:rewrite}

And:

<script type="text/javascript" src="{exp:force_ssl:proto}://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
EOF;
	}

	private function _swap(&$first, &$second) {
		$temp = $first;
		$first = $second;
		$second = $temp;

		return;
	}
}

/* End of file pi.force_ssl.php */
/* Location: ./system/expressionengine/third_party/force_ssl/pi.force_ssl.php */

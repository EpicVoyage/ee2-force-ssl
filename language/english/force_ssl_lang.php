<?php
$lang['license'] = 'License Key';

$lang['ssl_on'] = 'Automatically Force SSL for';
$lang['none'] = 'Nothing';
$lang['login'] = 'Form Submissions';
$lang['cp'] = 'Control Panel';
$lang['logged_in'] = 'Authenticated Users';
$lang['all'] = 'All Pages';
$lang['hsts'] = 'HSTS Mode';

$lang['save_me'] = 'We detected a valid SSL certificate on this server. Please choose your settings and hit "Submit". You should not see this message after the extension is configured.';
$lang['not_valid'] = 'Oops! We were not able to automatically verify the SSL certificate for this domain.';

# Note that {ssllabs}, {sslshopper} and {autodetected_os} will be replaced in this string.
$lang['not_valid_end'] = 'In some situations this error is caused by an outdated {autodetected_os} on the server. You should use a service like <a href="{ssllabs}">SSL Labs\' Test</a> or <a href="{sslshopper}">SSL Shopper\'s Checker</a> to verify the SSL certificate\'s expiration date and installation chain.<br /><br />If everything looks good, please select "Active" from the Advanced Preferences below.';

$lang['autodetected_unknown'] = 'Windows installation or *nix package';
$lang['autodetected_nix'] = '*nix package';
$lang['autodetected_windows'] = 'Windows installation';

$lang['preferences'] = 'Preferences';
$lang['advanced_preferences'] = 'Advanced Preferences';
$lang['show_advanced_preferences'] = 'Show Advanced Preferences';
$lang['hide_advanced_preferences'] = 'Hide Advanced Preferences';
$lang['warning'] = 'WARNING';
$lang['note'] = 'NOTE';
$lang['active'] = 'Active';
$lang['deny_post'] = 'Deny unencrypted &lt;form&gt; submissions<br /><em>Please verify all site functionality if you enable this.</em>';
$lang['tamper'] = 'Tamper with Theme and CP URLs, if required';
$lang['auto'] = 'Automatic (better for caching)';
$lang['abs'] = 'Absolute URLs (older browser support)';
$lang['port'] = 'HTTPS port<br /><em>if you do not know what this is, it should be "443"</em>';

$lang['unencrypted_submissions_disabled'] = 'We are sorry but for security reasons we cannot allow this action to proceed. Please contact your system administrator.';
$lang['force_ssl_disabled'] = 'This module has been disabled in your config.php file. Please look for "force_ssl_disabled."';

/* End of file force_ssl_lang.php */
/* Location: ./system/expressionengine/third_party/force_ssl/language/english/force_ssl_lang.php */

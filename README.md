# ee2-force-ssl
ExpressionEngine 2: Force HTTPS (HTTP + SSL) connections.

# Usage
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

<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

render_header('API Documentation - IPRN SMS Panel');
?>
<h1 class="h4 mb-3">API Documentation</h1>
<p class="small text-muted">
    The IPRN SMS Panel exposes your own SMPP/HTTP gateways and routing. Below are example patterns that you can adapt.
</p>

<h2 class="h5 mt-4">HTTP Example</h2>
<pre class="bg-dark text-light p-3 small rounded">
GET https://your-sms-gateway.example/send
    ?username=api_user
    &amp;password=api_pass
    &amp;to=8801612345678
    &amp;from=IPRNDEM
    &amp;text=Test+message
    &amp;route=Afghanistan+RTX+761
</pre>

<h2 class="h5 mt-4">SMPP Bind Example</h2>
<pre class="bg-dark text-light p-3 small rounded">
system_id: api_user
password:  api_pass
system_type: IPRN
interface_version: 0x34
addr_ton: 1
addr_npi: 1
address_range: 88016*
</pre>

<p class="small text-muted">
    Use the ranges defined in the admin panel to decide which short codes / long codes are routed through which
    gateways. The panel itself does not send SMS; it provides rating, balance, and reporting logic around your
    existing infrastructure.
</p>
<?php
render_footer();
<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

render_header('Compliance - IPRN SMS Panel');
?>
<h1 class="h4 mb-3">Regulatory &amp; Compliance</h1>
<p class="small text-muted">
    This demo panel is intended for use by experienced operators who understand and comply with all applicable
    telecommunications and data protection regulations, including but not limited to BTRC, TCPA, and GDPR.
</p>
<ul class="small text-muted">
    <li>Ensure all SMS traffic is permission-based and follows local opt-in/opt-out rules.</li>
    <li>Store and process personal data in accordance with GDPR and local data protection laws.</li>
    <li>Respect do-not-disturb (DND) and national do-not-call (NDNC) registries where applicable.</li>
    <li>Configure logging and retention policies appropriate for your jurisdiction.</li>
</ul>
<?php
render_footer();
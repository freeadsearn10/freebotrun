<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $email === '' || $message === '') {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'A valid email is required.';
    } else {
        // In a real deployment you would send an email or store the message.
        $sent = true;
    }

    if ($error) {
        flash('error', $error);
    } elseif ($sent) {
        flash('success', 'Your message has been received.');
    }

    header('Location: contact.php');
    exit;
}

render_header('Contact - IPRN SMS Panel');
?>
<h1 class="h4 mb-3">Contact</h1>
<p class="small text-muted">
    Use this form to send feedback or business inquiries about your IPRN SMS deployment.
</p>
<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="name">Name</label>
                        <input type="text" id="name" name="name" class="form-control"
                               value="<?php echo e($_POST['name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control"
                               value="<?php echo e($_POST['email'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="message">Message</label>
                        <textarea id="message" name="message" rows="4" class="form-control" required><?php
                            echo e($_POST['message'] ?? '');
                        ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Send</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
render_footer();
<?php
require_once __DIR__ . '/app.php';

$pendingUserId = $_SESSION['pending_mfa_user_id'] ?? null;
$mfaStep = is_int($pendingUserId) && $pendingUserId > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['mfa_code'])) {
        $code = $_POST['mfa_code'];
        $attempts = (int) ($_SESSION['mfa_attempts'] ?? 0);
        $pendingUser = $mfaStep
            ? db()->one('SELECT id, mfa_secret FROM users WHERE id = ?', [$pendingUserId])
            : null;
        if (is_string($code) && $pendingUser !== null && $attempts < 5 && verify_totp((string) $pendingUser['mfa_secret'], trim($code))) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $pendingUser['id'];
            $_SESSION['mfa_verified'] = true;
            unset($_SESSION['pending_mfa_user_id'], $_SESSION['mfa_attempts'], $_SESSION['csrf_token']);
            set_flash('success', 'You’re securely signed in.');
            redirect('account.php');
        }
        $attempts++;
        if ($attempts >= 5) {
            unset($_SESSION['pending_mfa_user_id'], $_SESSION['mfa_attempts']);
            set_flash('error', 'Too many verification attempts. Sign in again to continue.');
            redirect('login.php');
        }
        $_SESSION['mfa_attempts'] = $attempts;
        set_flash('error', 'That verification code didn’t match. Please try again.');
        redirect('login.php');
    }

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    if (!is_string($email) || !is_string($password)) {
        set_flash('error', 'We couldn’t verify those details. Please try again.');
        redirect('login.php');
    }
    $attempts = $_SESSION['login_attempts'] ?? ['started' => time(), 'count' => 0];
    if (!is_array($attempts) || time() - (int) ($attempts['started'] ?? 0) > 900) {
        $attempts = ['started' => time(), 'count' => 0];
    }
    if ((int) $attempts['count'] >= 8) {
        set_flash('error', 'For your security, please wait a little before trying again.');
        redirect('login.php');
    }

    $email = strtolower(trim($email));
    $user = filter_var($email, FILTER_VALIDATE_EMAIL)
        ? db()->one('SELECT id, name, email, password_hash, mfa_enabled FROM users WHERE email = ?', [$email])
        : null;
    if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
        $attempts['count'] = (int) $attempts['count'] + 1;
        $_SESSION['login_attempts'] = $attempts;
        set_flash('error', 'We couldn’t verify those details. Check your email and password, then try again.');
        redirect('login.php');
    }

    unset($_SESSION['login_attempts']);
    if ((bool) $user['mfa_enabled']) {
        session_regenerate_id(true);
        $_SESSION['pending_mfa_user_id'] = (int) $user['id'];
        $_SESSION['mfa_attempts'] = 0;
        unset($_SESSION['user_id'], $_SESSION['mfa_verified'], $_SESSION['csrf_token']);
        redirect('login.php');
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['mfa_verified'] = true;
    unset($_SESSION['csrf_token']);
    set_flash('success', 'You’re securely signed in.');
    redirect('account.php');
}

$pendingUserId = $_SESSION['pending_mfa_user_id'] ?? null;
$mfaStep = is_int($pendingUserId) && $pendingUserId > 0;
if (current_user() !== null) {
    redirect('account.php');
}
site_shell_start('Sign in', 'auth-page');
?>
    <section class="auth-layout auth-layout-reverse" aria-labelledby="login-title">
        <div class="auth-aside">
            <p class="section-eyebrow"><?= $mfaStep ? 'ONE LAST LITTLE STEP' : 'GOOD TO HAVE YOU BACK' ?></p>
            <h1><?= $mfaStep ? 'Your account,<br><em>protected.</em>' : 'Come on<br><em>in.</em>' ?></h1>
            <p><?= $mfaStep ? 'Enter the six-digit code from your authenticator app to finish signing in.' : 'Your favourites, your orders, and the thoughtful details that make it yours.' ?></p>
            <div class="auth-aside-note"><span aria-hidden="true">✳</span> A calmer way to find the good stuff.</div>
        </div>
        <div class="auth-card">
            <p class="section-eyebrow"><?= $mfaStep ? 'MULTI-FACTOR VERIFICATION' : 'YOUR CLOUDCART ACCOUNT' ?></p>
            <h2 id="login-title"><?= $mfaStep ? 'One more<br>moment.' : 'Welcome<br>back.' ?></h2>
            <?php if ($mfaStep): ?>
                <form method="post" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <label class="form-field">
                        <span>6-digit authenticator code</span>
                        <input name="mfa_code" type="text" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" minlength="6" maxlength="6" required autofocus>
                        <small>Codes refresh every 30 seconds.</small>
                    </label>
                    <button class="button button-dark button-full" type="submit">Verify and sign in <span aria-hidden="true">↗</span></button>
                </form>
                <p class="auth-switch"><a href="login.php">Start again</a></p>
            <?php else: ?>
                <p class="auth-intro">Sign in to pick up where you left off.</p>
                <form method="post" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <label class="form-field">
                        <span>Email address</span>
                        <input name="email" type="email" autocomplete="email" maxlength="254" required>
                    </label>
                    <label class="form-field">
                        <span>Password</span>
                        <input name="password" type="password" autocomplete="current-password" required>
                    </label>
                    <button class="button button-dark button-full" type="submit">Sign in <span aria-hidden="true">↗</span></button>
                </form>
                <?php if (entraid_configured()): ?>
                    <div class="auth-divider"><span>or</span></div>
                    <a class="button button-light button-full" href="oauth.php?action=start">Continue with Microsoft <span aria-hidden="true">↗</span></a>
                <?php endif; ?>
                <p class="auth-switch">New around here? <a href="register.php">Create an account</a></p>
            <?php endif; ?>
            <p class="auth-privacy">Your details stay yours. We use secure sign-in to keep them that way.</p>
        </div>
    </section>
<?php site_shell_end(); ?>

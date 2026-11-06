<?php
require_once __DIR__ . '/app.php';

if (current_user() !== null) {
    redirect('account.php');
}

site_shell_start('Create your account', 'auth-page');
?>
    <section class="auth-layout" aria-labelledby="register-title">
        <div class="auth-aside">
            <p class="section-eyebrow">WELCOME TO THE GOOD STUFF</p>
            <h1>A little more<br><em>you.</em></h1>
            <p>Save your favourites, keep an eye on delivery, and find your next everyday favourite.</p>
            <div class="auth-aside-note"><span aria-hidden="true">✳</span> Thoughtfully chosen. Yours to enjoy.</div>
        </div>
        <div class="auth-card">
            <p class="section-eyebrow">YOUR OWN LITTLE CORNER</p>
            <h2 id="register-title">Make yourself<br>at home.</h2>
            <p class="auth-intro">Create your free CloudCart account in just a moment.</p>
            <form action="process_register.php" method="post" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label class="form-field">
                    <span>Your name</span>
                    <input name="name" type="text" autocomplete="name" maxlength="120" required>
                </label>
                <label class="form-field">
                    <span>Email address</span>
                    <input name="email" type="email" autocomplete="email" maxlength="254" required>
                </label>
                <label class="form-field">
                    <span>Create a password</span>
                    <input name="password" type="password" autocomplete="new-password" minlength="12" required aria-describedby="password-hint">
                    <small id="password-hint">At least 12 characters. A longer phrase is easier to remember.</small>
                </label>
                <button class="button button-dark button-full" type="submit">Create my account <span aria-hidden="true">↗</span></button>
            </form>
            <p class="auth-switch">Already one of us? <a href="login.php">Sign in</a></p>
            <p class="auth-privacy">Your details stay yours. We only use them to look after your account and orders.</p>
        </div>
    </section>
<?php site_shell_end(); ?>

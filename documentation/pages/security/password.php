<?php
require_once __DIR__ . '/../../../resources/icons.php';
$pageTitle = 'Password Protection';
$pageDescription = 'Learn how to set up password protection, biometric login with Windows Hello, Touch ID or your Linux login, and auto-lock in Argo Books.';
$currentPage = 'password';
$pageCategory = 'security';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Secure access to your business data with password protection and biometric login integration.</p>

            <h2>Setting Up Password Protection</h2>
            <p>Adding a password encrypts your company file so that only you can access it. This is the step that turns encryption on: until a company file has a password, it is stored unencrypted and anyone who obtains the file can read it. See <a href="encryption.php" class="link">Encryption</a> for what that protects and how.</p>
            <ol class="steps-list">
                <li>Go to "Settings"</li>
                <li>Select the "Security" tab</li>
                <li>Click "Add Password"</li>
                <li>Enter and confirm your password</li>
            </ol>

            <p>Once a password is set, you'll be required to enter it each time you open the company file.</p>

            <h3 id="locked-out">If you're locked out</h3>
            <p>Try these in order.</p>

            <ol class="steps-list">
                <li><strong>Biometric login, if you enabled it.</strong> If biometric login (Windows Hello, Touch ID or your Linux login) is still set up for this file, on this computer, under the same user account, open the file with it. Then go straight to Settings &gt; Security and change the password to something you'll keep.</li>
                <li><strong>A backup made before you set the password.</strong> A backup from before the password existed isn't encrypted and will open normally. Backups made afterwards use the same password as the file, so those won't help.</li>
                <li><strong>Ask us to recover it.</strong> Email <a class="link" href="mailto:contact@argorobots.com">contact@argorobots.com</a> and we'll walk you through it.</li>
            </ol>

            <h3>How recovery works</h3>
            <p>You will have to send us the company file. We confirm you're the owner, unlock it using a key only we hold, and send back a copy with the password removed. You can then open it and set a new password.</p>

            <p><strong>We have to be able to confirm the file is yours.</strong> Anyone who steals a laptop also has the company file, so simply possessing it isn't proof. Expect us to check it against your account and purchase details before we unlock anything.</p>

            <p>Password recovery is a safety net, not a substitute for remembering your password. Make sure to write it down or keep it stored in a password manager.</p>

            <h2>Changing Your Password</h2>
            <p>To change an existing password:</p>
            <ol class="steps-list">
                <li>Go to "Settings > Security"</li>
                <li>Click the "Change" button next to the Password section</li>
                <li>Enter your current password, then set a new one</li>
            </ol>

            <h2 id="biometric-login">Biometric Login (Premium)</h2>
            <div class="info-box">
                <p><strong>Premium Feature:</strong> Biometric login is available with the Premium plan.
                <a href="../getting-started/version-comparison.php" class="link">Compare versions</a></p>
            </div>

            <p>Argo Books can use your computer's own sign-in, so you unlock your company file with Windows Hello on Windows, Touch ID on a Mac, or your system login on Linux, instead of typing your password each time.</p>

            <h3>Enabling Biometric Login</h3>
            <ol class="steps-list">
                <li>First, set up a password (see above)</li>
                <li>In Settings &gt; Security, enable the <strong>Biometric Login</strong> toggle below the password section</li>
                <li>Verify your identity when your computer prompts you</li>
                <li>Next time you open your company file, a biometric login button will appear alongside the password field</li>
            </ol>

            <h3>Platform Support</h3>
            <ul>
                <li><strong>Windows:</strong> Uses Windows Hello (fingerprint reader, facial recognition camera, or PIN)</li>
                <li><strong>macOS:</strong> Uses Touch ID. It needs a Mac with Touch ID and at least one fingerprint added in System Settings &gt; Touch ID &amp; Password. The Touch ID prompt only takes a fingerprint, so if it can't be used, type your company file password as usual.</li>
                <li><strong>Linux:</strong> Uses your system's sign-in prompt, which accepts your account password, or your fingerprint if a reader is set up with fprintd. It needs polkit and <code>secret-tool</code> (from the libsecret-tools package) installed.</li>
            </ul>

            <h3>How Biometric Login Stores Your Password</h3>
            <p>Biometrics don't replace your password, they unlock it. When you enable the toggle, Argo Books hands your password to your operating system's protected storage, which ties it to your computer and to your signed-in user account: Windows' protected storage on Windows, the login keychain on a Mac (listed as "Argo Books" in Keychain Access), and your desktop's keyring on Linux. Your fingerprint or face is never seen by Argo Books; your computer simply confirms it's you and releases the password back.</p>

            <p>Two things follow from that:</p>
            <ul>
                <li>Biometric login only works on that computer, under that user account. Copy the file to another machine and you'll need the password.</li>
                <li>It is as strong as your computer login. Anyone who can sign in as you could open the file, so keep a strong password on your computer account.</li>
            </ul>

            <div class="info-box">
                <strong>Note:</strong> Biometric login requires a password to be set first, and compatible hardware configured in your operating system settings. If your computer can't use it, turning on the toggle shows a message explaining why, such as no fingerprint being set up.</div>

            <h2>Auto-Lock</h2>
            <p>When a password is set, Argo Books can automatically lock your company file after a period of inactivity. This protects your data if you step away from your computer. You can configure the auto-lock timeout in Settings > Security.</p>

            <div class="page-navigation">
                <a href="encryption.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Encryption</span>
                </a>
                <a href="backups.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Regular Backups &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>

# Local Email Setup with MailHog

When running the Argo Books website locally on Laragon, PHP's `mail()` function won't work without a mail server. MailHog is a fake SMTP server that catches all emails locally and displays them in a web interface - no emails are actually sent.

## Setup Instructions

### 1. Download MailHog

1. Go to https://github.com/mailhog/MailHog/releases
2. Download `MailHog_windows_386.exe` (or `MailHog_windows_amd64.exe`)
3. Save it somewhere convenient

### 2. Create and configure php.ini

Laragon doesn't ship with a `php.ini` by default, only template files. You need to create one first:

1. Open your Laragon PHP folder (e.g. `C:\laragon\bin\php\php-8.x.x-nts-Win32-vs17-x64`)
2. Copy `php.ini-development` and rename the copy to `php.ini`
3. Open the new `php.ini` and find the `[mail function]` section. Update these settings:

```ini
[mail function]
SMTP=localhost
smtp_port=1025
sendmail_from = noreply@localhost
```

Make sure `sendmail_path` is commented out (has a `;` in front of it). On Windows, PHP uses `SMTP` and `smtp_port` directly. No sendmail needed.

### 3. Run MailHog

Double-click `MailHog_windows_386.exe`. A console window will open. Keep it running (you can minimize it).

### 4. Restart Apache

In Laragon, right-click the tray icon and select "Reload Apache" (or stop and start all services).

### 5. View Emails

Open http://localhost:8025 in your browser. All captured emails will appear here.

## How It Works

```
PHP mail() → SMTP localhost:1025 → MailHog → Web UI (port 8025)
```

## Troubleshooting

### Emails not appearing in MailHog

1. **Check MailHog is running by opening CMD and running:**
   ```
   netstat -an | findstr 1025
   ```
   You should see `0.0.0.0:1025` in LISTENING state.

2. **Make sure php.ini exists and is being loaded.** Create a file called `phpinfo.php` in your project root:
   ```php
   <?php phpinfo();
   ```
   Open it in your browser and search for "Loaded Configuration File". It should show the path to your `php.ini`. Also check that `SMTP` shows `localhost` and `smtp_port` shows `1025`.

3. **Test PHP mail directly:** Create `test_mail.php` in your project root:
   ```php
   <?php
   $result = mail("test@example.com", "Test", "Test body");
   var_dump($result);
   ```
   Should output `bool(true)`.
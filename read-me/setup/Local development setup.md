# Local development setup

The stack is the same on both platforms (PHP, MySQL, Composer, no build step); only the
way you install it differs. Windows uses Laragon, macOS uses Homebrew. Follow the section
for your machine.

## Windows (Laragon)

### Step 1: Install Laragon

1. Download Laragon from [https://laragon.org/download/](https://laragon.org/download/)
2. Install Laragon (default location: `C:\laragon`)
3. Open Laragon and click **Start All** to start Apache and MySQL

### Step 2: Install Composer

1. Download and install Composer from [https://getcomposer.org/](https://getcomposer.org/)
2. During installation, make sure it detects your `php.exe` from `C:\laragon\bin\php\php-8.3.26-Win32-vs16-x64`
3. Restart your computer to finish installing Composer
4. Open Command Prompt and run `composer -V` to verify Composer is installed

### Step 3: Set Up the Project

1. Place the project files directly in Laragon's `www` directory: `C:\laragon\www\argo-books-website`
   - The folder name will become part of your URL (e.g., folder `argo-books-website` → URL `localhost/argo-books-website`)
   - Avoid spaces in the folder name
2. Open Command Prompt and navigate to that directory:

```bash
cd C:\laragon\www\argo-books-website
```

3. Run the following command to install PHP dependencies:

```bash
composer install
```

This will download all required dependencies into the `vendor/` folder.

### Step 4: Set Up the Database

You need to create a MySQL database and import the schema.

**What is HeidiSQL?** HeidiSQL is a database management tool that comes with Laragon. It lets you manage MySQL databases through a visual interface (similar to phpMyAdmin).

1. **Open HeidiSQL**:
   - In Laragon, click the **Database** button
   - HeidiSQL will open and connect automatically

2. **Create the Database**:
   - Right-click in the left sidebar
   - Select **Create new → Database**
   - Name it: `argo_books`
   - Click **OK**

3. **Import the Schema**:
   - Click on the **argo_books** database in the left sidebar
   - Go to **File → Run SQL file...**
   - Navigate to your project folder and select: `mysql_schema.sql`
   - The tables will be created automatically

4. **Verify the Import**:
   - Expand the **argo_books** database in the left sidebar
   - You should see all the tables listed

## macOS (Homebrew)

macOS ships neither PHP (removed in macOS 12) nor MySQL, so Homebrew provides both.

There is no Apache or nginx here. PHP's built-in server stands in, with `router.php`
supplying the parts of `.htaccess` it would otherwise ignore, chiefly the roughly 120
rewrite rules that route `/v1`, the `/api` surface, the portal and invoice token URLs,
`/download/avalonia/...`, and every guide article slug at the web root. None of those
paths exist on disk, so without the router they 404 locally while working in
production. It also serves from `/` rather than a subfolder, which matches production
and sidesteps the subfolder detection in `resources/scripts/main.js`.

### Step 1: Install Homebrew

Homebrew is the macOS package manager, filling the role Laragon's bundled binaries play
on Windows. Run this in Terminal and enter your Mac password when prompted:

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

Then put it on your `PATH` (Apple Silicon):

```bash
echo 'eval "$(/opt/homebrew/bin/brew shellenv)"' >> ~/.zprofile
eval "$(/opt/homebrew/bin/brew shellenv)"
```

Verify with `brew --version`.

### Step 2: Install PHP, MySQL and Composer

```bash
brew install php mysql composer
brew services start mysql
```

`brew services start` also registers MySQL to come back at login, which is the
equivalent of leaving Laragon's **Start All** on. Confirm it is up with `mysqladmin ping`.

### Step 3: Set Up the Project

Put the project anywhere you like. Unlike Laragon there is no `www` directory it has to
live in, and the folder name does not become part of the URL.

```bash
cd ~/Desktop/Argo-Books-website
composer install
```

### Step 4: Set Up the Database

Homebrew's MySQL starts with a passwordless `root`, which is fine for a local-only
install. There is no HeidiSQL on macOS, so use the `mysql` client that came with it:

```bash
mysql -u root -e "CREATE DATABASE argo_books CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root argo_books < mysql_schema.sql
```

Verify with `mysql -u root argo_books -e "SHOW TABLES;"`.

For the PHPUnit suite, repeat both commands against `argo_books_test` and add a matching
`.env.testing`. See [PHPUnit suite](../../tests/README.md).

### Step 5: Create `.env`

`db_connect.php` loads `.env` on every request and throws if it is absent. Write a
local-only one rather than copying the production file across: sandbox and production
share the same remote database, so pointing a dev machine at it puts test rows into live
customer data.

```bash
cat > .env <<'ENV'
APP_ENV=sandbox
SITE_URL=http://localhost:8000
DB_HOST=127.0.0.1
DB_NAME=argo_books
DB_USERNAME=root
DB_PASSWORD=
ENV
echo "PORTAL_ENCRYPTION_KEY=$(openssl rand -hex 32)" >> .env
```

The third-party keys (Stripe, PayPal, Square, Gemini) are deliberately left unset. Copy
individual values over only when you need to exercise that specific integration locally.
Note that a locally generated `PORTAL_ENCRYPTION_KEY` will not decrypt anything that was
encrypted in production, which is intended.

## Running Locally

### Windows

1. Open Laragon and click **Start All**
2. Navigate to http://localhost/argo-books-website in your browser (adjust the folder name if different)
3. The website should now be running locally
4. To view emails sent by the application, open http://localhost:8025 (requires MailHog setup, see [Local email setup](Local%20email%20setup.md))

### macOS

```bash
cd ~/Desktop/Argo-Books-website
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 router.php
```

Then open http://localhost:8000. Stop it with Ctrl+C.

`PHP_CLI_SERVER_WORKERS` is not optional. The built-in server handles one request at a
time by default, and the header fetches `community/get_avatar_info.php` while the page
request is still open; on a single worker those two deadlock and the page hangs.

`router.php` stands in for `.htaccess`, which the built-in server ignores completely.
It parses the file at request time rather than duplicating it, so the two cannot drift,
and covers three things:

- The rewrite rules, without which every guide article, API route and token URL 404s.
- The deny rules. Without them `http://localhost:8000/.env` is served as plain text.
- `mod_dir`'s `DirectorySlash` 301 and a real 404. The built-in server serves a
  directory's `index.php` at the unslashed URL, which leaves the browser resolving
  `href="style.css"` as `/style.css`, and when a path matches nothing it walks up the
  tree for an `index.php` and serves the homepage with a 200.

Rules guarded by a `RewriteCond` are skipped: the only ones are the canonical host and
scheme redirects, which `.htaccess` already scopes to the production hostname.

It is only ever loaded by `php -S` and does nothing in production, where Apache reads
`.htaccess` directly.

For local mail, `brew install mailpit && brew services start mailpit` is the macOS
counterpart to MailHog; its inbox is also at http://localhost:8025.

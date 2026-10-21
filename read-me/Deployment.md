# Deployment

Every push to the `main` branch triggers `.github/workflows/deploy.yml`, which uploads the changed files to the production and dev servers over SFTP.

## How a deploy works

1. **Checkout**: GitHub spins up a fresh Ubuntu runner and clones the repo.
2. **Determine changed files**: runs `git diff HEAD~1 HEAD` to figure out:
   - `mode`: `incremental` (normal pushes) or `full` (first commit or manually-triggered full deploy).
   - `composer_changed`: whether `composer.json` or `composer.lock` changed in this commit.
3. **Cache + install Composer deps** (only if `composer_changed = true`). Restores `vendor/` from the GitHub Actions cache keyed on `composer.lock`'s hash, then runs `composer install --no-dev --optimize-autoloader`. Skipped entirely on pushes that don't touch composer files.
4. **Setup SSH + install lftp**: preps the connection to the server.
5. **Upload**: runs the matching deploy step:
   - **Incremental:** uploads only files that changed in this commit. Deletes files that were deleted. Mirrors `vendor/` only if `composer_changed = true`.
   - **Full mirror:** uploads everything (with a small exclude list; see below).

Both modes deploy to **production** AND **dev** in the same run.

## What's never uploaded

Everything in `.gitignore` is automatically skipped (it's not in the runner's checkout). On top of that, lftp explicitly excludes:

- `.git/`, `.github/`, `.gitignore`
- `README.md`, `CLAUDE.md`, `read-me/`
- every other `.md` file, except `api/agent/playbook.md` and `api/agent/facts.md`, which the agent endpoint reads from the server
- `composer.json`, `composer.lock` (the runner uses them, but they don't go to the server)
- `mysql_schema.sql`
- `phpunit.xml`, `tests/` (PHPUnit suite, local-only)
- `.ftp-deploy-sync-state.json`

A full mirror deletes anything on the server that is not in the repo, except what it excludes. So it also leaves alone the release files uploaded by hand with FileZilla, which are not in git:

- `resources/downloads/<version>/`, the downloads and translations for each app release
- the `.nupkg` and `.exe` files in `resources/updates/` and `resources/updates-sandbox/`, the app's update packages. The update lists and signatures beside them are in the repo and deploy as usual.

## Forcing a full re-upload

Go to **Actions -> Deploy to server -> Run workflow** in GitHub, set `full_deploy` to `true`, and click Run. This mirrors every tracked file to both servers (still respecting the exclude list above).

Useful when:
- You suspect the server has drifted from `main` somehow (shouldn't happen)
- You're setting up a new environment from scratch

## Common deploy outcomes

| Push contents | composer install? | vendor/ uploaded? | Typical time |
|---|---|---|---|
| PHP / CSS / JS / docs / schema edits | skipped | no | ~15-20s |
| `composer.lock` changed (e.g. `composer update`) | runs (with cache) | yes | ~30-60s |
| First commit or manual `full_deploy` | runs | yes (full mirror) | 1-2 min |

## Troubleshooting

### Deploy failed at "Install lftp"

Usually a transient DNS or apt-mirror issue on GitHub's Ubuntu runners (`Temporary failure resolving 'azure.archive.ubuntu.com'`). Not your code. Re-run the workflow from the Actions tab and it almost always passes.

### Deploy succeeded but the site isn't updated

1. Make sure you cleared the cache in your browser then refresh the site.
2. If a file you expected to change isn't in the log, check that it isn't in the exclude list above.
3. Check the workflow log's "Deploying changed files to ..." section. It prints exactly which files lftp put / deleted.

### Vendor on the server looks stale

Force a full deploy (see above). That re-mirrors `vendor/` regardless of whether `composer.lock` was touched in the latest commit.

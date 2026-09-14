# First-Run Install Tracking

How the funnel attributes a desktop install back to the original ad click, and how to test it end-to-end without your own machine silently filtering itself out.

The flow has three moving parts:

1. **Website**: Receives the `app_first_run` POST at `/api/track-app-event.php`, resolves the install token back to a `visitor_id`, and writes a row to `referral_events`.
2. **Desktop app**: `ArgoBooks.Core.Services.FirstRunReporter` fires the POST once per machine on the first successful launch.
3. **Installer**: On Windows, the Advanced Installer custom action writes an 8-character HMAC token to `%LOCALAPPDATA%\ArgoBooks\install_token.txt` during install. On Mac and Linux the token is embedded in the installer filename.

The admin funnel at `/admin/marketing-funnel/` reads the resulting rows from `referral_events` to populate the "App first run" stage.

The POST also carries the app's `X-Device-Id` header. The server stores its SHA-256 as `device_hash` in `event_data`, which is the same hash free telemetry is filed under (`device:<hash>`). That is how the User Activity cards in `/admin/app-stats/` show a user's referral link and survey answer. Installs reported by app versions older than 2.0.16 have no `device_hash`, so their cards show no referral.

---

## Why re-testing from your own machine does nothing

Your first successful test install does land in the funnel and the "App first run" stage in `/admin/marketing-funnel/` increments by one. This section is about what happens on the second attempt. By design, two independent guards stop the same machine from generating a new first-run row, so any later test from the same machine appears silent until you walk through the reset procedure below. The guards exist so a single user re-launching the app does not double-count themselves in the funnel.

### Guard 1: client-side marker file

`FirstRunReporter.ReportIfFirstRunAsync` writes a marker after a successful POST:

```
%LOCALAPPDATA%\ArgoBooks\first_run_reported.marker
```

If the marker exists, the reporter returns immediately and never tries to POST again. This file lives in `%LOCALAPPDATA%`, **not** in the app's install folder, so uninstalling and reinstalling does not delete it. The next install on the same machine is still treated as "already reported".

The marker file's content records the outcome of the POST and is the fastest client-side diagnostic when something looks wrong:

```
reported_at=<UTC ISO timestamp>
reason=<token | no_token | gave_up_after_retries>
```

- `reason=token` means the reporter found `install_token.txt`, sent the token, and got a 2xx response. This is the healthy case.
- `reason=no_token` means the reporter ran successfully and posted to the server, but did not find `install_token.txt` on disk, so it sent an empty token. The server still wrote a row, just with `visitor_id = NULL` and `token_match = false`. **This is what you see when the installer's `WriteInstallToken` custom action did not run.**
- `reason=gave_up_after_retries` means the reporter tried the POST three times, hit network failures on every attempt, and stopped retrying. No row was written server-side.

So if the funnel shows a missing or unattributed install, reading this marker on the affected machine tells you which side of the chain to look at without opening the database.

### Guard 2: server-side machine_uuid dedupe

The reporter also stamps each POST with a stable per-machine UUID stored in:

```
%LOCALAPPDATA%\ArgoBooks\machine_uuid.txt
```

`/api/track-app-event.php` checks the most recent `referral_events` rows for any existing `app_first_run` with the same `machine_uuid` JSON field **in the same environment**. A match returns `{"success": true, "duplicate": true}` and writes nothing. The machine_uuid file also survives uninstall.

The environment part matters because production and sandbox share one database. Without it, a sandbox test install would permanently stop that same machine from ever producing a production row, and the funnel would undercount with nothing to show for it.

The combined effect: once a machine has produced one `app_first_run` row in a given environment, that machine is permanently locked out of producing another in that environment, even after wiping the local marker. To re-test, both the local marker and the corresponding database row need to be cleared.

---

## End-to-end test procedure

### Step 1: read your current machine UUID

You need this value to target the right database row in Step 2. Read it and copy it to your clipboard.

Windows:

```
type "%LOCALAPPDATA%\ArgoBooks\machine_uuid.txt"
```

Mac:

```
cat "$HOME/Library/Application Support/ArgoBooks/machine_uuid.txt"
```

Linux:

```
cat "$HOME/.local/share/ArgoBooks/machine_uuid.txt"
```

Copy the UUID. If the file does not exist, the app has not produced a first-run event from this machine yet and you can skip ahead to Step 4.

### Step 2: clear the matching database row

In HeidiSQL or the MySQL CLI, delete the existing `app_first_run` row for that UUID:

```sql
DELETE FROM referral_events
 WHERE event_type = 'app_first_run'
   AND JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.machine_uuid')) = '<paste the uuid here>';
```

### Step 3: clear the local guard files

Now that the database row is gone, wipe the local marker and UUID file so the next app launch posts again.

Windows:

```
del "%LOCALAPPDATA%\ArgoBooks\first_run_reported.marker"
del "%LOCALAPPDATA%\ArgoBooks\machine_uuid.txt"
```

Mac:

```
rm "$HOME/Library/Application Support/ArgoBooks/first_run_reported.marker"
rm "$HOME/Library/Application Support/ArgoBooks/machine_uuid.txt"
```

Linux:

```
rm "$HOME/.local/share/ArgoBooks/first_run_reported.marker"
rm "$HOME/.local/share/ArgoBooks/machine_uuid.txt"
```

This forces the app to mint a fresh `machine_uuid` on the next launch.

**Optional**: if you also want to re-test the first-run UX (welcome tutorial, source survey, setup checklist), delete `settings.json` from the Roaming AppData location too. This is separate from the telemetry path and lives in a different folder.

Windows:

```
del "%APPDATA%\ArgoBooks\settings.json"
```

Mac and Linux: same path as above, the `GetAppDataPath()` returns the same folder used for the local guards (so it lives alongside `machine_uuid.txt`).

### Step 4: produce a fresh landing -> download -> install chain

A test install only attributes back to a visitor_id if a landing event from that visitor exists in the last 14 days (see `resolve_visitor_from_token` in `/api/track-app-event.php`).

1. In an incognito window, visit any landing page with a tracking source, for example:

   ```
   https://argorobots.com/for-contractors/?source=ads-contractors
   ```

   This creates a `landing` row in `referral_events` and sets the `argo_visitor_id` cookie.

2. Click through to `/downloads/` (this writes a `downloads_page` row).

3. Click the download button for your platform (this writes a `download_click` row and serves the installer with an embedded token).

4. Run the installer.

5. Launch the app once with internet enabled. `FirstRunReporter` fires on app startup, posts to `/api/track-app-event.php`, and writes the marker on success.

### Step 5: verify the event landed

In HeidiSQL:

```sql
SELECT id, event_type, source_code, visitor_id, ip_address, created_at,
       JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.platform'))     AS platform,
       JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.app_version'))  AS app_version,
       JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.machine_uuid')) AS machine_uuid,
       JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.token_match'))  AS token_match
  FROM referral_events
 WHERE event_type = 'app_first_run'
 ORDER BY created_at DESC
 LIMIT 5;
```

You should see your row. Check:

- `token_match` is `true`. If `false`, the HMAC did not match any recent visitor, which means either the token file was not written by the installer or the token does not correspond to a landing in the last 14 days.
- `source_code` is the source from your landing URL (for the example above, `ads-contractors`).
- `app_version` matches what you installed.

Then refresh `/admin/marketing-funnel/`. The "App first run" stage should increment by one.

---

## What to check when real users are not showing up

If your own test install succeeds end-to-end but the funnel still shows 0 first runs from real users, work through these in order. Most are not visible in the funnel directly.

### Time between download and install

Some users download the installer and install it days or weeks later. Give the funnel a few before drawing conclusions from "downloads vs first runs" ratios.

### Token resolution missing the 14-day window

`resolve_visitor_from_token` only scans landings from the last 14 days. A user who landed 15+ days ago and installs today will have their event written with `visitor_id = null` (no attribution), but the row is still written and counted in the funnel under "all traffic". To check whether unattributed first runs are arriving:

```sql
SELECT COUNT(*) FROM referral_events
 WHERE event_type = 'app_first_run'
   AND visitor_id IS NULL
   AND created_at >= NOW() - INTERVAL 30 DAY;
```

If this is greater than zero, attribution is missing the window but the install itself is being tracked.

### The token file is not being written by the installer

On Windows, the Advanced Installer `WriteInstallToken` custom action writes `install_token.txt` to `%LOCALAPPDATA%\ArgoBooks\` during install. If a packaging change stopped that action from running, every install posts with an empty token and lands as `visitor_id = null`. See `Argo-Books-Avalonia/docs/setup/Advanced Installer project setup.md` for the custom action setup.

**Fastest check** (works any time after first launch): read the marker file.

```
type "%LOCALAPPDATA%\ArgoBooks\first_run_reported.marker"
```

If you see `reason=no_token`, the installer is not writing `install_token.txt`. The custom action either did not run or could not extract the token from the installer's filename. This is your confirmation; no further diagnosis on the desktop side is needed.

**Live-installer check** (works only between install and first launch): run the installer but **do not open the app yet**. Then look for the file:

```
type "%LOCALAPPDATA%\ArgoBooks\install_token.txt"
```

If the file exists and has an 8-character hex string, the installer is doing its job. If the file is missing, the custom action did not fire. Note that `FirstRunReporter` deletes `install_token.txt` after a successful first-run POST, so once the app has launched once this file is gone whether or not the install was attributed.

### Outbound POST blocked by Windows Defender / firewall on first launch

The reporter retries up to 3 times before writing a `gave_up_after_retries` marker. After that, the install will never report. Visible in the user's local `first_run_reported.marker` content but not visible to you.

### The user installed offline

Argo Books is marketed as offline-capable, and a user who installs on a machine without internet and never connects will never produce a first-run event. The reporter's `MaxRetryAttempts` guard eventually gives up and writes the marker. There is no recovery path here.

---

## Files referenced

- `api/track-app-event.php` - website POST receiver
- `track_referral_event.php` - shared event writer with bot and admin-session filters
- `statistics.php` - `is_likely_bot()` (bypassed for desktop events via `allow_bot = true`)
- `admin/marketing-funnel/index.php` - funnel display
- `ArgoBooks.Core/Services/FirstRunReporter.cs` - desktop reporter, marker file logic, token resolution
- `ArgoBooks/App.axaml.cs` - kicks off the reporter on app startup
- `Argo-Books-Avalonia/docs/setup/Advanced Installer project setup.md` - Windows installer custom action that writes the token file

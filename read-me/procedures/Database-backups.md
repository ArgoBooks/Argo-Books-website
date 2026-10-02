# Database Backups

The host runs **JetBackup 5**, which backs up the database on a schedule automatically. There is nothing to set up and no cron to write.

## Getting a scheduled backup

cPanel, then **JetBackup 5**, then **Databases** under *Restore & Download*. Pick the date you want, then either restore it in place or download it.

## Create a manual backup

JetBackup only has the last scheduled run, so take a manual one before anything risky: a migration, a bulk update, dropping tables.

1. cPanel, then **phpMyAdmin**.
2. Click **`argorobots_argo_books`** in the left sidebar first. Exporting from the home screen dumps every database on the account instead.
3. **Export** tab, choose **Custom**.
4. Set three things:
   - **Output**: *Save output to a file*, Compression **gzipped**
   - **Object creation options**: tick **Add DROP TABLE / VIEW / PROCEDURE / FUNCTION / EVENT**
5. **Export**. Takes a minute or two.

Gzip because phpMyAdmin's import limit is smaller than the 69 MB an uncompressed export produces, and about 15 MB compressed fits. DROP TABLE because without it the file cannot be imported over an existing database.

## Checking a manual export worked

A truncated export still looks like a normal file. In Git Bash:

```bash
gzip -dc "/c/Users/evand/Desktop/Database backups/2026-08-29/argorobots_argo_books.sql.gz" | tail -3
```

It should end with:

```
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
```

Anything else means the export timed out or the download broke. Redo it.

## Restoring a manual export

phpMyAdmin, select `argorobots_argo_books`, **Import**, pick the `.sql.gz`, **Import**. It reads gzip directly, so do not decompress first.

## Keep the occasional local copy

JetBackup's copies live with the host. If the account goes away, a billing problem or a suspension, the backups go with it. The local folder is the one that survives that, so it is worth a manual export every few months even though the scheduled ones exist.

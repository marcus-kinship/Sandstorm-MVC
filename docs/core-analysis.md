# Core analysis: Sys, Dll and SystemException

Date: 2026-10-29. Measured with PHP 8.4.25 and the PHP built-in server (`server.php`) on Windows.

Goal: the framework should use as little CPU and memory as possible per request, so the CPU is left for the site's own code (`site/`).

## Status

**Step 1 (SystemException) is done.** Throwing no longer renders, so `catch` works; uncaught exceptions go through `set_exception_handler`; error pages send status 500; devmode off gives a 313-byte page with an error id, and warnings, notices and deprecations are logged instead of stopping the page; every error is one line in PHP's error log; the background image is an embedded 1280×800 WebP (56 KB instead of 1.3 MB). Measured on PHP 8.4.25:

| | Before | After |
|---|---|---|
| Error page, devmode | 1.3 MB, up to 187 ms | 93–97 KB, 10–30 ms |
| Error page, devmode off | PHP fatal error with server paths, status 200 | 313 B, about 5 ms, status 500 |
| Warning or deprecation, devmode off | Page stops | Logged, page continues |

**Step 2 (shared DB connection) is done.** The MySQL `Driver` keeps one connection per server, user, database and charset for the whole request; every `DatabaseConnection` still has its own `Driver` object, so settings such as `getError`, `func` and `type` do not leak between them. `DatabaseConnection` no longer closes the connection in its destructor (`->close()` does it on purpose), and `check()` reuses the open connection. Errors are logged to `ExceptionLog` again, but only when the page already has a connection open. Tested against MySQL: 20 × `new DatabaseConnection()` with a query open one connection instead of 20. `EscapeString::safe_vsprintf()` no longer has the PHP 7 branch.

**Step 3 (request path) is done**, following `docs/code-standard.md`:

- `Start`: no session start; one output buffer that is now really gzipped (before, the compressed buffer was thrown away and the page sent uncompressed); the controller renders inside the buffer; 404 sends status 404; `@router` arguments are read for the method that is actually called (before, a missing method failed in Reflection); `?setLang=` only accepts a locale that exists in `app/locales`.
- `Session`: starts on first use; `Session::exists()` lets the language be read only for visitors who already have a session.
- `config`: settings files are read once per request; the autoloader never prints (before, "Could not find class or trait" ended up on pages) and returns quietly so `class_exists()` works; `config::set()` works again (it called a class that does not exist and printed with `var_export`).
- `IController::callback()`: no second gzip on top of `ob_gzhandler`.
- `site/errorpages/404.php` and `500.php`: about 1 KB each, compressed.

| Start page `/` (framework only) | Before | After |
|---|---|---|
| With opcache, median | 1.42 ms | 1.06 ms |
| Without opcache, median | 4.2 ms | 3.8 ms |
| Files loaded | 17 | 13 |
| Peak memory | 786 KB | 451 KB |
| Sent to the browser | 33 KB (not compressed) | 4.9 KB (gzip) |
| Session file and cookie | Every visitor | Only when the session is used |

The router cache from the plan was left out: PHP forgets static data between requests, and reading one docblock costs about 20 µs.

## Next: code quality per group

The rest of `app/` is brought up to `docs/code-standard.md` one group at a time, each committed and tested on its own:

| Group | Files |
|---|---|
| Databases | `DatabaseConnection.php`, `DatabaseManager.php`, `DatabaseConnection/*` (Mysql Select/Insert/Update/Delete, ArgumentEscaper, QueryTemplate, ...), `Record.php`, `Record/*`, `Scheme.php`, `Querybuilder.php` |
| Session | `Session.php`, `Session/Core.php`, `Session/Store.php`, `Session_old.php` (remove) |
| File system | `Filesystem.php`, `FileInfo.php`, `FolderInfo.php`, `Dll/FilesystemIO.php`, `Dll/FileInfoIO.php`, `Dll/FolderInfoIO.php` |
| Cookie | `CookieManager.php`, `Curl.php` |
| Config | `config/config.class.php`, `config/settings/*`, `config/mysql/*` |
| Language | `Language.php`, `app/locales/*` |
| Controllers | `controller/ApIController.php`, `controller/IController.php`, `controller/Iproperty.php` |
| Validation | `Dll/Validation.php` (to be developed further), together with `Sys/Request/Validator.php` |
| Forms | `Sys/Formi.php`, `Dll/Forms/*` (text, textarea, hidden, dropmenu, time, table, tableboxs, getMessage) |
| Listview | `Dll/Listview.php` |

## 1. What one request costs today

The start page `/` loads 17 files. The framework itself (without the welcome page's MySQL check) takes:

| | Median per request |
|---|---|
| Without opcache | 4.2 ms |
| With opcache | 1.4 ms |

Breakdown with opcache (median, microseconds):

| Step | µs | Comment |
|---|---|---|
| Session start | 487 | Runs on **every** request, also for visitors who never use the session. Writes a session file and sets a cookie. |
| Boot: config, autoloader, SystemException register | 397 | `SystemException.php` (535 lines) and `config.class.php` load on every request. |
| Language (gettext) | 181 | `putenv` ×3, `setlocale`, `bindtextdomain` on every request. |
| Controller action | 114 | |
| Load controller file | 102 | |
| Render view | 88 | |
| Routing, `config::get`, reflection, gzip | ~90 | `config::get()` includes and builds `default_ini` **twice per call**. `@router` is read with Reflection on every request. |

An unknown URL (for example `/does-not-exist`) costs **187 ms and 4.9 MB**, because `site/errorpages/404.php` is missing and the full SystemException page is rendered (1.3 MB HTML).

Opening a MySQL connection costs 10–30 ms, which is more than the whole framework. That is why connection handling (section 3) is the biggest win for pages that use the database.

## 2. SystemException

`app/Sys/SystemException.php` plus `SystemException/` (Handles, Views, Renderer, Logger, OutputData): about 3,600 lines.

### Bugs and risks (fix first)

1. **Production mode crashes inside the error handling and leaks paths.** With `HTTP_devmode false`, `/does-not-exist` gives PHP's own fatal error, because `OutputData.php:118` calls `config` without `\` inside a namespace. That fatal error shows full server paths and a stack trace to the visitor. In devmode, the page with source code, stack trace, POST data, loaded classes and server data is shown, and nothing keeps that page from production (`DefaultHandle` has an empty `if ($_SERVER['HTTP_devmode'] == "true") { }`).
2. **No HTTP status.** The error page is sent with `200 OK`. It should be `500` (and `404` for missing pages), so browsers, search engines and monitoring see the error.
3. **The constructor renders and exits.** `new SystemException(...)` cleans the output buffer, renders the page and calls `exit()`. A `try { } catch (SystemException $e)` never gets to run (two places in the code rely on it, for example the autoloader in `config.class.php`). Throwing must be separate from rendering.
4. **No `set_exception_handler`.** Uncaught exceptions only reach the page through the shutdown function, where the message text is parsed with regular expressions to find the error type, file and stack trace.
5. **Every warning and deprecation stops the page**, also in production (`set_error_handler` with `E_ALL` throws for everything). In production a notice or deprecation should be logged, not stop the site.
6. **The logger connects to MySQL on every error**: `DatabaseConnection::check()` opens one connection, the logger another, then `SHOW TABLES` – inside error handling. If the database is the problem, this adds 10–30 ms or a timeout to every error.

### CPU and memory

- `debug_backtrace()` **with arguments** is taken in the constructor and in the error handler (twice per error). Use `DEBUG_BACKTRACE_IGNORE_ARGS` and a depth limit, or the exception's own `getTrace()`.
- `Views/include/header.php` is **1.3 MB**, almost all of it one base64 image, plus jQuery and highlight.js from CDN. A small page with inline CSS and no image is enough.
- `getErrorList()`, `ErrorCodeToLongText()` and `ErrorCodeToShortText()` build arrays and call gettext on every call. These can be constants that are translated only when the page is rendered.
- `global $start_minne` is unused.

### Proposed design

```
SystemException extends Exception        – only data, no side effects (can be caught)
ErrorHandler::register()                 – set_error_handler, set_exception_handler, shutdown
  ├─ devmode = true  → DevPage (today's page, slimmed down, status 500)
  └─ devmode = false → short "Something went wrong" page (status 500), details only to the log
ExceptionLogger                          – lazy: file log by default (error_log), DB log only if
                                           a connection is already open; never opens one itself
```

Notices and deprecations: devmode shows them in a list at the bottom of the page instead of stopping it; production logs them.

## 3. DatabaseConnection, Record, Session

### DatabaseConnection

- **Every `new DatabaseConnection()` opens a new MySQL connection.** It is used in 36 places, for example `Record::save()` (one new connection per save), `Session.php` (4) and `Dll/Tagging.php` (8). Fix: `DatabaseManager` keeps **one driver per pool per request** and `DatabaseConnection` reuses it. Same API, nothing in `site/` needs changing.
- `DatabaseConnection::check()` also opens its own connection; it should reuse the shared one.
- `QueryCache` (in-memory, max 1,000 entries) is good. Check that it is cleared after `INSERT`/`UPDATE`/`DELETE` on the same table.

### Record

- `save()` creates a new `DatabaseConnection` each time (see above).
- On a failed insert, `save()` tries to "repair" the table up to one time per column, which can run many `ALTER TABLE`s in one request. Keep it for devmode only.
- `Record` (old, short tags) and `Record\From` plus traits (new) exist side by side. Choose one entry point.

### Session

- `Session::start()` runs on every request (about 0.5 ms, a session file and a cookie for every visitor). Make it **lazy**: start the session the first time `Session::get/set` is called. This also removes PHP's session file lock for pages that do not use the session, so parallel requests (images, AJAX) are not queued.
- The "online" functions (`addOnline`, `typeOnline`, `removeOnline`) each open a new DB connection; they will use the shared connection after the DatabaseConnection fix.
- `Session_old.php` defines the same class name `Session` and is not used: delete it.

## 4. Other quick wins in the core

| What | Today | Proposal |
|---|---|---|
| `config::get()` | include + `new default_ini()` twice per call | Static cache: build once per request |
| `@router` arguments | `ReflectionMethod` + regex per request | Cache per controller/method in a static array (or in a generated file in devmode) |
| 404 | Full error page, 187 ms / 4.9 MB, status 200 | Small `site/errorpages/404.php` and `500.php`, status 404/500 |
| Language | gettext set up on every request | Keep, but skip `putenv` ×3 when the locale is already set |
| Short tags `<?` | Used in 59 files under `app/` | Change to `<?php`, so `short_open_tag` is no longer required |

## 5. Old and broken files

**Broken (calls methods that no longer exist, `mysql_escape_mimic`):**
`Sys/Querybuilder.php`, `Sys/Scheme.php`, `Dll/Tagging.php`, `Dll/View/view.php`.
`Sys/Record/QueryBuilder` and `Sys/Record/SQL/Scheme.php` replace the first two.

**Duplicates (old and new version of the same thing):**

| Old | New |
|---|---|
| `Sys/Session_old.php` | `Sys/Session.php` + `Sys/Session/` |
| `Sys/Querybuilder.php` | `Sys/Record/QueryBuilder/` |
| `Sys/Scheme.php` | `Sys/Record/SQL/Scheme.php` |
| `Dll/FileInfoIO.php`, `Dll/FolderInfoIO.php`, `Dll/FilesystemIO.php` | `Sys/FileInfo.php`, `Sys/FolderInfo.php`, `Sys/Filesystem.php` |
| `Dll/Table.php`, `Dll/Rtable.php` | `Sys/Record/SQL/Scheme.php` |

**Empty or stubs:** `Dll/Comments.php` (empty class), `Sys/Record/Step/*.php` (0 bytes), `Sys/SystemException/Views/include/footer.php` (0 bytes).

**Not used by the core** (may still be used by site code, so check before removing):
`Sys/extendDatabasRouter.php`, `Sys/Install.php`, `Sys/c.php`, `Sys/Mutator.php`, `Sys/ServerInfo.php`, `Sys/CookieManager.php`, `Dll/Searchf.php`, `Dll/Flatdata.php`, `Dll/Listview.php`, `Dll/Wtime.php`, `Dll/Backtraces.php`, `Dll/Bb.php`, `Dll/Captcha.php`, `Dll/StopWatch.php`.

## 6. Plan, in order of value

| Step | What | Gain |
|---|---|---|
| 1 | **SystemException**: separate throw from rendering, `set_exception_handler`, status 500/404, short page in production, slim dev page, lazy file logger, no backtrace args | Security (no leak in production), errors 187 ms / 4.9 MB → a few ms / < 1 MB, `catch` works |
| 2 | **One DB connection per pool per request** in `DatabaseManager`/`DatabaseConnection` | 10–30 ms saved for every extra `new DatabaseConnection()` / `Record::save()` |
| 3 | **Lazy session**, `config::get` cache, router cache, 404/500 pages | About 0.5–0.7 ms of 1.4 ms per request (about 40–50 %), no session lock |
| 4 | **Old files**: delete duplicates and stubs, rewrite or delete the broken ones, `<?` → `<?php` | Less to maintain, no fatal errors when old classes are used |

Each step can be committed and tested on its own, with the welcome page and a measurement like the one above before and after.

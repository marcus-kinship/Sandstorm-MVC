# Code standard

The model files are `Sys/DatabaseConnection/EscapeString.php`, `Sys/Session/Store.php`, `Sys/DatabaseConnection/Mysql/Driver.php` and `Sys/Request/*`. New and rewritten code in `app/` follows them.

## Language and version

- PHP 8.0 or later. No branches for PHP 7.
- `<?php`, never the short tag `<?` in classes. (Views may use `<?=`.)
- Code, comments, docblocks and commit messages in English. User-facing text goes through `_()`.

## Structure

- One class per file, the file named after the class.
- New parts of the core live under a namespace that matches the folder (`Sys\Session\Store` in `app/Sys/Session/Store.php`). Classes that sites call by name (`config`, `Start`, `Session`, `IController`, `DatabaseConnection`, `Record`, ...) keep their global name, so site code keeps working.
- Inside a namespace, global classes are written with a leading `\` (`\config::get()`, `\DatabaseConnection`).

## Types and docblocks

- Typed properties, parameters and return types (`private array $cache = []`, `function get(string $key): mixed`).
- A docblock on every class (what it is for) and on every public method (`@param`, `@return`, `@throws`).
- Comments say why, not what.

## Errors

- Throw an exception (`SystemException`, `DatabaseException`, `\InvalidArgumentException`, ...). Never `print`, `echo`, `die()` or `exit()` to report an error.
- An autoloader never throws or prints: if the class is not there it returns, and PHP reports the missing class.
- No `@` to hide errors, except where PHP gives no other way (and then with a comment).

## CPU and memory

The framework's job is to use as little as possible so the site gets the rest.

- Do work only when it is needed: open the session, the database connection and the logger the first time they are used.
- Build something once per request and keep it (`static` cache) when it is read many times, for example configuration.
- No `debug_backtrace()` with arguments, no full-file reads on the normal request path, no work in loops that can be done once before the loop.
- Output is compressed once, by `ob_gzhandler` in `Start`. Do not gzip again.
- Measure before and after (see `docs/core-analysis.md`).

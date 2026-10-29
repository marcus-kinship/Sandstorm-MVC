@echo off
rem Start Sandstorm with PHP's built-in server (double-click or run "start.bat [port]").
rem
rem Uses the php in PATH. To use another PHP for this project only, put the
rem full path to php.exe on the first line of a file named php.local, e.g.:
rem   C:\PHP\version_8.4.25\php.exe

cd /d "%~dp0"

set "PHP=php"
if exist php.local set /p PHP=<php.local

"%PHP%" server.php %*
pause

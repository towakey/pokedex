@echo off
set PATH=C:\Users\kyosg\.cargo\bin;%PATH%
cd /d "%~dp0"
npm run tauri dev
pause

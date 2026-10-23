================================================================================
 ClassCheck for Windows — Offline & Standalone Distribution
================================================================================

ClassCheck is a modern, offline-first classroom management, seating arrangement, 
attendance tracking, oral recitation, and grading platform designed for teachers.

--------------------------------------------------------------------------------
 1. QUICK START OPTIONS
--------------------------------------------------------------------------------

OPTION A: Windows Setup Installer (Recommended)
  1. Double-click "ClassCheck_Setup.exe".
  2. Follow the setup wizard to choose an installation folder (defaults to 
     your local user app data folder, requiring NO Administrator privileges).
  3. Click "Install ClassCheck".
  4. Desktop and Start Menu shortcuts will be created automatically.
  5. The application will launch and open your default web browser.

OPTION B: Portable Zero-Install (USB Flash Drive)
  1. Extract "ClassCheck_Portable.zip" to any folder or USB flash drive.
  2. Double-click "ClassCheck.exe" to start.
  3. ClassCheck runs self-contained directly from that folder.

--------------------------------------------------------------------------------
 2. SYSTEM REQUIREMENTS
--------------------------------------------------------------------------------
  - Operating System: Windows 10, Windows 11, Windows 8.1, Windows 7 (64-bit).
  - Classroom records and manual score entry work offline.
  - Voice entry uses local Whisper. First use needs internet to download/cache
    the speech model in your browser. Audio stays on your device.
  - Dependencies: None! (Portable PHP 8.5.12 and all MSVC runtime DLLs are bundled).
  - Web Browser: Microsoft Edge, Google Chrome, Mozilla Firefox, or Brave.

--------------------------------------------------------------------------------
 3. DATA BACKUP & RESTORATION
--------------------------------------------------------------------------------
  - All your classroom data, student rosters, seating arrangements, attendance, 
    recitations, and grades are stored locally in:
      database/database.sqlite
  - To back up your data at any time:
    Right-click the ClassCheck icon in your Windows System Tray (near the clock)
    and click "Back Up Database Now". A timestamped backup file will be created in:
      database/backups/
  - UPDATING: Close ClassCheck and run the new ClassCheck_Setup.exe. Choose the
    SAME installation folder. Do not uninstall or extract a portable ZIP over it.
  - Setup preserves your existing .env (including APP_KEY), database files,
    SQLite WAL/SHM files, storage, and uploaded work. Before replacing code, it
    creates and checks a timestamped backup under update-backups/.
  - On first launch, pending database migrations receive a separate consistent
    SQLite backup. Existing records are verified before committing the update;
    an error stops startup and rolls back the database migration.
  - To transfer data: exit ClassCheck first, then copy .env, database/ and
    storage/ together. Keep the original copy until you verify the new one.

--------------------------------------------------------------------------------
 4. MANAGING THE RUNNING APPLICATION
--------------------------------------------------------------------------------
  - When ClassCheck is running, look for the ClassCheck icon in your Windows 
    Taskbar System Tray (bottom-right corner).
  - Right-click the tray icon to:
      * Open ClassCheck in Browser
      * Open Data Folder (Database)
      * Back Up Database Now
      * Restart Server
      * Exit ClassCheck cleanly

--------------------------------------------------------------------------------
 5. UNINSTALLATION
--------------------------------------------------------------------------------
  - Open Windows Settings -> Apps -> Installed apps.
  - Find "ClassCheck Classroom Platform" and click "Uninstall".
  - You will be asked if you want to keep your database. If you select "Yes", 
    your student grades and records will remain safely saved on your computer.

================================================================================
 Copyright (c) 2026 ClassCheck. All rights reserved.
================================================================================

using System;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Net;
using System.Net.Sockets;
using System.Reflection;
using System.Runtime.InteropServices;
using System.Threading;
using System.Windows.Forms;

[assembly: AssemblyTitle("ClassCheck Desktop")]
[assembly: AssemblyDescription("ClassCheck Classroom Management Platform")]
[assembly: AssemblyConfiguration("")]
[assembly: AssemblyCompany("ClassCheck")]
[assembly: AssemblyProduct("ClassCheck")]
[assembly: AssemblyCopyright("Copyright © 2026 ClassCheck. All rights reserved.")]
[assembly: AssemblyTrademark("")]
[assembly: AssemblyCulture("")]
[assembly: ComVisible(false)]
[assembly: Guid("e5f7a18b-76b3-4c91-a83d-9d4a8e32c1f9")]
[assembly: AssemblyVersion("1.0.0.0")]
[assembly: AssemblyFileVersion("1.0.0.0")]

namespace ClassCheck
{
    static class Program
    {
        private static Mutex appMutex;
        private static Process phpProcess;
        private static NotifyIcon trayIcon;
        private static int assignedPort = 8000;
        private static string appRoot;
        private static string phpExe;
        private static string artisanScript;
        private static string serverScript;
        private static string activeUrlFile;
        private static string serverLogFile;

        [STAThread]
        static void Main()
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);

            appRoot = AppDomain.CurrentDomain.BaseDirectory.TrimEnd('\\', '/');
            activeUrlFile = Path.Combine(appRoot, "storage", "app", "active_url.txt");
            serverLogFile = Path.Combine(appRoot, "storage", "logs", "php_server.log");

            // 1. Single Instance Check
            bool createdNew;
            appMutex = new Mutex(true, "ClassCheck_SingleInstance_Mutex_Global", out createdNew);
            if (!createdNew)
            {
                // Already running - read active URL and open in browser
                string runningUrl = "http://127.0.0.1:8000";
                try
                {
                    if (File.Exists(activeUrlFile))
                    {
                        string savedUrl = File.ReadAllText(activeUrlFile).Trim();
                        if (!string.IsNullOrEmpty(savedUrl))
                        {
                            runningUrl = savedUrl;
                        }
                    }
                    Process.Start(new ProcessStartInfo(runningUrl) { UseShellExecute = true });
                }
                catch { }
                return;
            }

            // 2. Locate PHP Binary
            phpExe = Path.Combine(appRoot, "bin", "php", "php.exe");
            if (!File.Exists(phpExe))
            {
                phpExe = Path.Combine(appRoot, "php", "php.exe");
                if (!File.Exists(phpExe))
                {
                    phpExe = "php.exe";
                }
            }

            artisanScript = Path.Combine(appRoot, "artisan");
            serverScript = Path.Combine(appRoot, "server.php");

            // 3. Find an Available Port
            assignedPort = GetFreePort(8000);

            // 4. Initialize Environment, Folders & SQLite Database
            InitializeApp();

            // 5. Start PHP Built-in Web Server Directly
            bool started = StartPhpServer();
            if (!started)
            {
                StopPhpServer();
                return;
            }

            // 6. Active Health Check Polling (Wait until HTTP server is accepting requests)
            bool isHealthy = WaitForServerReady(assignedPort, 15);
            if (!isHealthy)
            {
                string errorDetails = "Server did not respond within 15 seconds.";
                if (File.Exists(serverLogFile))
                {
                    try
                    {
                        string logs = File.ReadAllText(serverLogFile).Trim();
                        if (!string.IsNullOrEmpty(logs))
                        {
                            errorDetails = logs;
                        }
                    }
                    catch { }
                }

                MessageBox.Show(
                    "ClassCheck web server failed to start properly on port " + assignedPort + ".\n\nDetails:\n" + errorDetails,
                    "ClassCheck Startup Error",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Error
                );
                StopPhpServer();
                return;
            }

            // Save active URL for subsequent launches
            try
            {
                string storageApp = Path.Combine(appRoot, "storage", "app");
                if (!Directory.Exists(storageApp)) Directory.CreateDirectory(storageApp);
                File.WriteAllText(activeUrlFile, "http://127.0.0.1:" + assignedPort);
            }
            catch { }

            // 7. Setup System Tray Icon
            SetupTray();

            // 8. Open Default Browser (Verified alive)
            OpenBrowser();

            // 9. Cleanup Handlers
            AppDomain.CurrentDomain.ProcessExit += (s, e) => StopPhpServer();
            Application.ApplicationExit += (s, e) => StopPhpServer();

            Application.Run();
        }

        private static int GetFreePort(int startingPort)
        {
            for (int port = startingPort; port < startingPort + 100; port++)
            {
                try
                {
                    TcpListener listener = new TcpListener(IPAddress.Loopback, port);
                    listener.Start();
                    listener.Stop();
                    return port;
                }
                catch
                {
                    // Port in use, continue to next
                }
            }
            return startingPort;
        }

        private static void InitializeApp()
        {
            try
            {
                string databaseDir = Path.Combine(appRoot, "database");
                if (!Directory.Exists(databaseDir)) Directory.CreateDirectory(databaseDir);

                string sqliteFile = Path.Combine(databaseDir, "database.sqlite");
                if (!File.Exists(sqliteFile))
                {
                    File.WriteAllText(sqliteFile, "");
                }

                string storageDir = Path.Combine(appRoot, "storage");
                string[] subDirs = new string[] {
                    Path.Combine(storageDir, "app"),
                    Path.Combine(storageDir, "app", "public"),
                    Path.Combine(storageDir, "app", "public", "photos"),
                    Path.Combine(storageDir, "app", "public", "modules"),
                    Path.Combine(storageDir, "framework"),
                    Path.Combine(storageDir, "framework", "cache"),
                    Path.Combine(storageDir, "framework", "cache", "data"),
                    Path.Combine(storageDir, "framework", "sessions"),
                    Path.Combine(storageDir, "framework", "views"),
                    Path.Combine(storageDir, "logs"),
                    Path.Combine(appRoot, "bootstrap", "cache")
                };

                foreach (string dir in subDirs)
                {
                    if (!Directory.Exists(dir)) Directory.CreateDirectory(dir);
                }

                // Ensure .env exists with correct configuration
                string envFile = Path.Combine(appRoot, ".env");
                if (!File.Exists(envFile))
                {
                    string defaultEnv = "APP_NAME=ClassCheck\n" +
                                       "APP_ENV=production\n" +
                                       "APP_KEY=base64:7K0bE2q5Qv7X6g9r8+t1uF2w3x4y5z6a7b8c9d0e1f2=\n" +
                                       "APP_DEBUG=false\n" +
                                       "APP_URL=http://127.0.0.1:" + assignedPort + "\n" +
                                       "LOG_CHANNEL=stack\n" +
                                       "LOG_DEPRECATIONS_CHANNEL=null\n" +
                                       "LOG_LEVEL=info\n" +
                                       "DB_CONNECTION=sqlite\n" +
                                       "DB_FOREIGN_KEYS=true\n" +
                                       "SESSION_DRIVER=file\n" +
                                       "SESSION_LIFETIME=120\n" +
                                       "CACHE_STORE=file\n" +
                                       "QUEUE_CONNECTION=sync\n" +
                                       "FILESYSTEM_DISK=local\n";
                    File.WriteAllText(envFile, defaultEnv);
                }

                // Ensure server.php exists in root
                if (!File.Exists(serverScript))
                {
                    string defaultServerScript = "<?php\n" +
                        "$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');\n" +
                        "if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) { return false; }\n" +
                        "require_once __DIR__.'/public/index.php';\n";
                    File.WriteAllText(serverScript, defaultServerScript);
                }

                // Run database migrations silently
                if (File.Exists(artisanScript))
                {
                    RunPhpCommand("migrate --force");
                }
            }
            catch (Exception ex)
            {
                Debug.WriteLine("Init error: " + ex.Message);
            }
        }

        private static void RunPhpCommand(string args)
        {
            try
            {
                ProcessStartInfo psi = new ProcessStartInfo
                {
                    FileName = phpExe,
                    Arguments = string.Format("\"{0}\" {1}", artisanScript, args),
                    WorkingDirectory = appRoot,
                    CreateNoWindow = true,
                    UseShellExecute = false,
                    WindowStyle = ProcessWindowStyle.Hidden
                };
                using (Process p = Process.Start(psi))
                {
                    p.WaitForExit(30000);
                }
            }
            catch { }
        }

        private static bool StartPhpServer()
        {
            try
            {
                StopPhpServer(); // Clean up any existing process

                // Ensure logs directory exists
                string logsDir = Path.Combine(appRoot, "storage", "logs");
                if (!Directory.Exists(logsDir)) Directory.CreateDirectory(logsDir);

                // Run PHP built-in server directly using router script server.php
                string arguments;
                if (File.Exists(serverScript))
                {
                    arguments = string.Format("-S 127.0.0.1:{0} \"{1}\"", assignedPort, serverScript);
                }
                else
                {
                    string publicDir = Path.Combine(appRoot, "public");
                    arguments = string.Format("-S 127.0.0.1:{0} -t \"{1}\"", assignedPort, publicDir);
                }

                ProcessStartInfo psi = new ProcessStartInfo
                {
                    FileName = phpExe,
                    Arguments = arguments,
                    WorkingDirectory = appRoot,
                    CreateNoWindow = true,
                    UseShellExecute = false,
                    WindowStyle = ProcessWindowStyle.Hidden,
                    RedirectStandardError = true,
                    RedirectStandardOutput = true
                };

                // Add necessary environment variables
                psi.EnvironmentVariables["APP_ENV"] = "production";
                psi.EnvironmentVariables["APP_URL"] = "http://127.0.0.1:" + assignedPort;

                phpProcess = new Process();
                phpProcess.StartInfo = psi;

                // Pipe PHP output/errors to log file for debugging
                phpProcess.ErrorDataReceived += (s, e) =>
                {
                    if (e != null && !string.IsNullOrEmpty(e.Data))
                    {
                        try { File.AppendAllText(serverLogFile, "[" + DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss") + " ERROR] " + e.Data + Environment.NewLine); } catch { }
                    }
                };
                phpProcess.OutputDataReceived += (s, e) =>
                {
                    if (e != null && !string.IsNullOrEmpty(e.Data))
                    {
                        try { File.AppendAllText(serverLogFile, "[" + DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss") + " INFO] " + e.Data + Environment.NewLine); } catch { }
                    }
                };

                phpProcess.Start();
                phpProcess.BeginErrorReadLine();
                phpProcess.BeginOutputReadLine();

                return true;
            }
            catch (Exception ex)
            {
                MessageBox.Show("Failed to launch PHP executable:\n" + ex.Message + "\n\nPath: " + phpExe, "ClassCheck Error", MessageBoxButtons.OK, MessageBoxIcon.Error);
                return false;
            }
        }

        private static bool WaitForServerReady(int port, int timeoutSeconds)
        {
            Stopwatch sw = Stopwatch.StartNew();
            string testUrl = "http://127.0.0.1:" + port;

            while (sw.Elapsed.TotalSeconds < timeoutSeconds)
            {
                if (phpProcess != null && phpProcess.HasExited)
                {
                    return false;
                }

                try
                {
                    HttpWebRequest request = (HttpWebRequest)WebRequest.Create(testUrl);
                    request.Method = "GET";
                    request.Timeout = 1000;
                    request.ReadWriteTimeout = 1000;

                    using (HttpWebResponse response = (HttpWebResponse)request.GetResponse())
                    {
                        if ((int)response.StatusCode >= 200 && (int)response.StatusCode < 400)
                        {
                            return true;
                        }
                    }
                }
                catch (WebException wex)
                {
                    HttpWebResponse errRes = wex.Response as HttpWebResponse;
                    if (errRes != null)
                    {
                        // Even a 404 or 302 means the server is UP and responding to HTTP
                        if ((int)errRes.StatusCode < 500)
                        {
                            return true;
                        }
                    }
                }
                catch
                {
                    // Socket not yet accepting, sleep and retry
                }

                Thread.Sleep(150);
            }

            return false;
        }

        private static void StopPhpServer()
        {
            try
            {
                if (File.Exists(activeUrlFile))
                {
                    File.Delete(activeUrlFile);
                }
            }
            catch { }

            try
            {
                if (phpProcess != null && !phpProcess.HasExited)
                {
                    phpProcess.Kill();
                    phpProcess.WaitForExit(2000);
                    phpProcess.Dispose();
                    phpProcess = null;
                }
            }
            catch { }

            if (trayIcon != null)
            {
                try
                {
                    trayIcon.Visible = false;
                    trayIcon.Dispose();
                    trayIcon = null;
                }
                catch { }
            }
        }

        private static void OpenBrowser()
        {
            try
            {
                string url = "http://127.0.0.1:" + assignedPort;
                Process.Start(new ProcessStartInfo(url) { UseShellExecute = true });
            }
            catch (Exception ex)
            {
                MessageBox.Show("Could not open browser automatically:\n" + ex.Message + "\n\nPlease navigate to http://127.0.0.1:" + assignedPort, "ClassCheck", MessageBoxButtons.OK, MessageBoxIcon.Information);
            }
        }

        private static void SetupTray()
        {
            try
            {
                trayIcon = new NotifyIcon
                {
                    Text = "ClassCheck (Running on http://127.0.0.1:" + assignedPort + ")",
                    Icon = SystemIcons.Application,
                    Visible = true
                };

                ContextMenuStrip menu = new ContextMenuStrip();

                ToolStripMenuItem titleItem = new ToolStripMenuItem("🏫 ClassCheck (Port " + assignedPort + ")")
                {
                    Enabled = false,
                    Font = new Font(menu.Font, FontStyle.Bold)
                };
                menu.Items.Add(titleItem);
                menu.Items.Add(new ToolStripSeparator());

                ToolStripMenuItem openItem = new ToolStripMenuItem("🌐 Open ClassCheck in Browser", null, (s, e) => OpenBrowser());
                openItem.Font = new Font(menu.Font, FontStyle.Bold);
                menu.Items.Add(openItem);

                ToolStripMenuItem folderItem = new ToolStripMenuItem("📁 Open Data Folder (Database)", null, (s, e) =>
                {
                    try
                    {
                        string dbDir = Path.Combine(appRoot, "database");
                        if (!Directory.Exists(dbDir)) dbDir = appRoot;
                        Process.Start(new ProcessStartInfo("explorer.exe", "\"" + dbDir + "\"") { UseShellExecute = true });
                    }
                    catch { }
                });
                menu.Items.Add(folderItem);

                ToolStripMenuItem backupItem = new ToolStripMenuItem("💾 Backup Database Now", null, (s, e) => BackupDatabase());
                menu.Items.Add(backupItem);

                ToolStripMenuItem restartItem = new ToolStripMenuItem("🔄 Restart Server", null, (s, e) =>
                {
                    StartPhpServer();
                    if (WaitForServerReady(assignedPort, 10))
                    {
                        OpenBrowser();
                    }
                });
                menu.Items.Add(restartItem);

                menu.Items.Add(new ToolStripSeparator());

                ToolStripMenuItem exitItem = new ToolStripMenuItem("❌ Exit ClassCheck", null, (s, e) =>
                {
                    StopPhpServer();
                    if (appMutex != null)
                    {
                        try { appMutex.ReleaseMutex(); } catch { }
                        appMutex.Dispose();
                        appMutex = null;
                    }
                    Application.Exit();
                });
                menu.Items.Add(exitItem);

                trayIcon.ContextMenuStrip = menu;
                trayIcon.DoubleClick += (s, e) => OpenBrowser();

                trayIcon.ShowBalloonTip(3000, "ClassCheck is Running", "ClassCheck is active at http://127.0.0.1:" + assignedPort, ToolTipIcon.Info);
                trayIcon.BalloonTipClicked += (s, e) => OpenBrowser();
            }
            catch { }
        }

        private static void BackupDatabase()
        {
            try
            {
                string dbFile = Path.Combine(appRoot, "database", "database.sqlite");
                if (!File.Exists(dbFile) || new FileInfo(dbFile).Length == 0)
                {
                    MessageBox.Show("No active database records found to backup.", "ClassCheck Backup", MessageBoxButtons.OK, MessageBoxIcon.Information);
                    return;
                }

                string backupDir = Path.Combine(appRoot, "database", "backups");
                if (!Directory.Exists(backupDir)) Directory.CreateDirectory(backupDir);

                string timestamp = DateTime.Now.ToString("yyyy-MM-dd_HHmmss");
                string backupFile = Path.Combine(backupDir, "database_backup_" + timestamp + ".sqlite");

                File.Copy(dbFile, backupFile, true);

                if (trayIcon != null)
                {
                    trayIcon.ShowBalloonTip(4000, "Database Backup Complete", "Backup saved to database/backups/\nFilename: database_backup_" + timestamp + ".sqlite", ToolTipIcon.Info);
                }

                DialogResult dr = MessageBox.Show(
                    "Database successfully backed up!\n\nLocation: " + backupFile + "\n\nWould you like to open the backups folder?",
                    "ClassCheck Database Backup",
                    MessageBoxButtons.YesNo,
                    MessageBoxIcon.Information
                );

                if (dr == DialogResult.Yes)
                {
                    Process.Start(new ProcessStartInfo("explorer.exe", "\"" + backupDir + "\"") { UseShellExecute = true });
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show("Failed to backup database:\n" + ex.Message, "Backup Error", MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }
    }
}

using System;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.IO.Compression;
using System.Reflection;
using System.Runtime.InteropServices;
using System.Text;
using System.Threading;
using System.Windows.Forms;
using Microsoft.Win32;

[assembly: AssemblyTitle("ClassCheck Setup")]
[assembly: AssemblyDescription("ClassCheck Classroom Management Platform Installer")]
[assembly: AssemblyConfiguration("")]
[assembly: AssemblyCompany("ClassCheck")]
[assembly: AssemblyProduct("ClassCheck")]
[assembly: AssemblyCopyright("Copyright © 2026 ClassCheck. All rights reserved.")]
[assembly: AssemblyTrademark("")]
[assembly: AssemblyCulture("")]
[assembly: ComVisible(false)]
[assembly: Guid("d4e6b29a-65a2-4b80-972c-8c3b7d21b0e8")]
[assembly: AssemblyVersion("1.0.0.0")]
[assembly: AssemblyFileVersion("1.0.0.0")]

namespace ClassCheckInstaller
{
    public class InstallerForm : Form
    {
        private TextBox txtPath;
        private Button btnBrowse;
        private CheckBox chkDesktop;
        private CheckBox chkStartMenu;
        private CheckBox chkLaunch;
        private Button btnInstall;
        private Button btnCancel;
        private ProgressBar progressBar;
        private Label lblStatus;
        private Panel headerPanel;

        public InstallerForm()
        {
            InitializeComponent();
        }

        private void InitializeComponent()
        {
            this.Text = "ClassCheck Setup Wizard";
            this.Size = new Size(560, 420);
            this.StartPosition = FormStartPosition.CenterScreen;
            this.FormBorderStyle = FormBorderStyle.FixedDialog;
            this.MaximizeBox = false;
            this.MinimizeBox = false;
            this.BackColor = Color.FromArgb(248, 249, 250);
            this.Font = new Font("Segoe UI", 9.5F);

            // Header Banner
            headerPanel = new Panel
            {
                Dock = DockStyle.Top,
                Height = 85,
                BackColor = Color.FromArgb(22, 101, 52) // Emerald dark
            };

            Label lblTitle = new Label
            {
                Text = "ClassCheck for Windows",
                Font = new Font("Segoe UI", 14F, FontStyle.Bold),
                ForeColor = Color.White,
                Location = new Point(20, 16),
                AutoSize = true
            };

            Label lblSubtitle = new Label
            {
                Text = "Classroom Seating, Attendance, Recitation & Grading Platform",
                Font = new Font("Segoe UI", 9F),
                ForeColor = Color.FromArgb(220, 252, 231),
                Location = new Point(21, 46),
                AutoSize = true
            };

            headerPanel.Controls.Add(lblTitle);
            headerPanel.Controls.Add(lblSubtitle);
            this.Controls.Add(headerPanel);

            // Installation Path Section
            Label lblPathDesc = new Label
            {
                Text = "Install ClassCheck to the following location:",
                Location = new Point(24, 105),
                AutoSize = true,
                Font = new Font("Segoe UI", 9.5F, FontStyle.Bold)
            };
            this.Controls.Add(lblPathDesc);

            string defaultPath = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "ClassCheck");
            txtPath = new TextBox
            {
                Text = defaultPath,
                Location = new Point(24, 135),
                Size = new Size(400, 28)
            };
            this.Controls.Add(txtPath);

            btnBrowse = new Button
            {
                Text = "Browse...",
                Location = new Point(434, 133),
                Size = new Size(85, 30),
                BackColor = Color.White
            };
            btnBrowse.Click += (s, e) =>
            {
                using (FolderBrowserDialog fbd = new FolderBrowserDialog())
                {
                    fbd.SelectedPath = txtPath.Text;
                    if (fbd.ShowDialog() == DialogResult.OK)
                    {
                        txtPath.Text = Path.Combine(fbd.SelectedPath, "ClassCheck");
                    }
                }
            };
            this.Controls.Add(btnBrowse);

            // Options Checkboxes
            chkDesktop = new CheckBox
            {
                Text = "Create a Desktop shortcut",
                Checked = true,
                Location = new Point(24, 185),
                AutoSize = true
            };
            this.Controls.Add(chkDesktop);

            chkStartMenu = new CheckBox
            {
                Text = "Create a Start Menu shortcut",
                Checked = true,
                Location = new Point(24, 215),
                AutoSize = true
            };
            this.Controls.Add(chkStartMenu);

            chkLaunch = new CheckBox
            {
                Text = "Launch ClassCheck immediately after setup",
                Checked = true,
                Location = new Point(24, 245),
                AutoSize = true
            };
            this.Controls.Add(chkLaunch);

            // Progress Bar & Status
            lblStatus = new Label
            {
                Text = "Ready to install. Click Install to begin.",
                Location = new Point(24, 280),
                Size = new Size(495, 20),
                ForeColor = Color.FromArgb(75, 85, 99)
            };
            this.Controls.Add(lblStatus);

            progressBar = new ProgressBar
            {
                Location = new Point(24, 305),
                Size = new Size(495, 22),
                Style = ProgressBarStyle.Blocks,
                Visible = false
            };
            this.Controls.Add(progressBar);

            // Bottom Buttons
            btnInstall = new Button
            {
                Text = "Install ClassCheck",
                Location = new Point(360, 340),
                Size = new Size(160, 35),
                BackColor = Color.FromArgb(22, 101, 52),
                ForeColor = Color.White,
                Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
                FlatStyle = FlatStyle.Flat
            };
            btnInstall.FlatAppearance.BorderSize = 0;
            btnInstall.Click += BtnInstall_Click;
            this.Controls.Add(btnInstall);

            btnCancel = new Button
            {
                Text = "Cancel",
                Location = new Point(265, 340),
                Size = new Size(85, 35),
                BackColor = Color.White
            };
            btnCancel.Click += (s, e) => this.Close();
            this.Controls.Add(btnCancel);
        }

        private void BtnInstall_Click(object sender, EventArgs e)
        {
            string installPath = txtPath.Text.Trim();
            if (string.IsNullOrEmpty(installPath))
            {
                MessageBox.Show("Please specify a valid installation directory.", "Invalid Path", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                return;
            }

            btnInstall.Enabled = false;
            btnBrowse.Enabled = false;
            btnCancel.Enabled = false;
            txtPath.Enabled = false;
            chkDesktop.Enabled = false;
            chkStartMenu.Enabled = false;
            chkLaunch.Enabled = false;

            progressBar.Visible = true;
            progressBar.Value = 10;
            lblStatus.Text = "Preparing installation directory...";

            Thread installThread = new Thread(() =>
            {
                try
                {
                    // 1. Terminate any running ClassCheck instances before replacing files
                    try
                    {
                        Process[] existing = Process.GetProcessesByName("ClassCheck");
                        foreach (Process p in existing)
                        {
                            try { p.Kill(); p.WaitForExit(1500); } catch { }
                        }
                    }
                    catch { }

                    if (!Directory.Exists(installPath))
                    {
                        Directory.CreateDirectory(installPath);
                    }

                    this.Invoke(new Action(() =>
                    {
                        progressBar.Value = 30;
                        lblStatus.Text = "Extracting ClassCheck components and portable PHP runtime...";
                    }));

                    // 2. Extract payload safely with overwrite support while preserving database
                    string localZip = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "payload.zip");
                    if (File.Exists(localZip))
                    {
                        using (ZipArchive archive = ZipFile.OpenRead(localZip))
                        {
                            ExtractArchiveSafely(archive, installPath);
                        }
                    }
                    else
                    {
                        // Check if payload is embedded in resource
                        Assembly asm = Assembly.GetExecutingAssembly();
                        using (Stream stream = asm.GetManifestResourceStream("payload.zip"))
                        {
                            if (stream != null)
                            {
                                using (ZipArchive archive = new ZipArchive(stream))
                                {
                                    ExtractArchiveSafely(archive, installPath);
                                }
                            }
                            else
                            {
                                // If not found, copy all files from current directory
                                CopyDirectory(AppDomain.CurrentDomain.BaseDirectory, installPath);
                            }
                        }
                    }

                    this.Invoke(new Action(() =>
                    {
                        progressBar.Value = 75;
                        lblStatus.Text = "Configuring shortcuts and application environment...";
                    }));

                    // 3. Ensure essential runtime folders exist
                    string[] essentialDirs = new string[] {
                        Path.Combine(installPath, "database"),
                        Path.Combine(installPath, "storage", "app", "public", "photos"),
                        Path.Combine(installPath, "storage", "app", "public", "modules"),
                        Path.Combine(installPath, "storage", "framework", "cache", "data"),
                        Path.Combine(installPath, "storage", "framework", "sessions"),
                        Path.Combine(installPath, "storage", "framework", "views"),
                        Path.Combine(installPath, "storage", "logs"),
                        Path.Combine(installPath, "bootstrap", "cache")
                    };
                    foreach (string dir in essentialDirs)
                    {
                        if (!Directory.Exists(dir)) Directory.CreateDirectory(dir);
                    }

                    string mainExe = Path.Combine(installPath, "ClassCheck.exe");
                    string uninstallerExe = Path.Combine(installPath, "Uninstall.exe");

                    // Copy current setup executable as uninstaller
                    try
                    {
                        string currentSetupPath = Assembly.GetExecutingAssembly().Location;
                        if (File.Exists(currentSetupPath))
                        {
                            File.Copy(currentSetupPath, uninstallerExe, true);
                        }
                    }
                    catch { }

                    // 4. Create Shortcuts via Native IShellLink COM
                    if (chkDesktop.Checked)
                    {
                        string desktopFolder = Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory);
                        string desktopLnk = Path.Combine(desktopFolder, "ClassCheck.lnk");
                        CreateNativeShortcut(desktopLnk, mainExe, installPath, "ClassCheck Classroom Platform");
                    }

                    if (chkStartMenu.Checked)
                    {
                        string programsFolder = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.Programs), "ClassCheck");
                        if (!Directory.Exists(programsFolder)) Directory.CreateDirectory(programsFolder);
                        string startMenuLnk = Path.Combine(programsFolder, "ClassCheck.lnk");
                        CreateNativeShortcut(startMenuLnk, mainExe, installPath, "ClassCheck Classroom Platform");
                    }

                    // 5. Register in Windows Add/Remove Programs
                    RegisterUninstaller(installPath, mainExe, uninstallerExe);

                    this.Invoke(new Action(() =>
                    {
                        progressBar.Value = 100;
                        lblStatus.Text = "Installation completed successfully!";
                        lblStatus.ForeColor = Color.FromArgb(22, 101, 52);

                        btnInstall.Text = "Launch & Close";
                        btnInstall.Enabled = true;
                        btnInstall.Click -= BtnInstall_Click;
                        btnInstall.Click += (s, ev) =>
                        {
                            if (chkLaunch.Checked && File.Exists(mainExe))
                            {
                                Process.Start(new ProcessStartInfo(mainExe) { WorkingDirectory = installPath });
                            }
                            this.Close();
                        };
                    }));
                }
                catch (Exception ex)
                {
                    this.Invoke(new Action(() =>
                    {
                        lblStatus.Text = "Error during installation: " + ex.Message;
                        lblStatus.ForeColor = Color.Red;
                        btnInstall.Enabled = true;
                        btnCancel.Enabled = true;
                        MessageBox.Show("An error occurred during installation:\n" + ex.Message, "Installation Error", MessageBoxButtons.OK, MessageBoxIcon.Error);
                    }));
                }
            });

            installThread.IsBackground = true;
            installThread.Start();
        }

        private static void ExtractArchiveSafely(ZipArchive archive, string destinationDir)
        {
            foreach (ZipArchiveEntry entry in archive.Entries)
            {
                string completeFileName = Path.GetFullPath(Path.Combine(destinationDir, entry.FullName));
                if (!completeFileName.StartsWith(Path.GetFullPath(destinationDir), StringComparison.OrdinalIgnoreCase))
                {
                    continue; // Prevent zip-slip
                }

                if (string.IsNullOrEmpty(entry.Name))
                {
                    // Directory entry
                    Directory.CreateDirectory(completeFileName);
                    continue;
                }

                string directoryPath = Path.GetDirectoryName(completeFileName);
                if (!Directory.Exists(directoryPath))
                {
                    Directory.CreateDirectory(directoryPath);
                }

                // If this is database.sqlite and already exists on disk with data, do NOT overwrite it!
                if (entry.FullName.EndsWith("database.sqlite", StringComparison.OrdinalIgnoreCase) && File.Exists(completeFileName))
                {
                    long existingSize = new FileInfo(completeFileName).Length;
                    if (existingSize > 0)
                    {
                        continue; // Preserve user's database records
                    }
                }

                entry.ExtractToFile(completeFileName, true);
            }
        }

        private static void RegisterUninstaller(string installPath, string mainExe, string uninstallerExe)
        {
            try
            {
                using (RegistryKey key = Registry.CurrentUser.CreateSubKey(@"Software\Microsoft\Windows\CurrentVersion\Uninstall\ClassCheck"))
                {
                    if (key != null)
                    {
                        key.SetValue("DisplayName", "ClassCheck Classroom Platform");
                        key.SetValue("DisplayVersion", "1.0.0");
                        key.SetValue("Publisher", "ClassCheck");
                        key.SetValue("InstallLocation", installPath);
                        key.SetValue("DisplayIcon", mainExe + ",0");
                        key.SetValue("UninstallString", "\"" + (File.Exists(uninstallerExe) ? uninstallerExe : mainExe) + "\" --uninstall");
                        key.SetValue("NoModify", 1, RegistryValueKind.DWord);
                        key.SetValue("NoRepair", 1, RegistryValueKind.DWord);
                        key.SetValue("EstimatedSize", 160000, RegistryValueKind.DWord); // ~160 MB
                    }
                }
            }
            catch { }
        }

        // Standard Win32 IShellLink COM interface to create .lnk shortcuts cleanly without scripting engines
        private static void CreateNativeShortcut(string shortcutPath, string targetPath, string workingDir, string description)
        {
            try
            {
                IShellLinkW link = (IShellLinkW)new CShellLink();
                link.SetPath(targetPath);
                link.SetWorkingDirectory(workingDir);
                link.SetDescription(description);
                link.SetIconLocation(targetPath, 0);

                IPersistFile file = (IPersistFile)link;
                file.Save(shortcutPath, false);
            }
            catch { }
        }

        private static void CopyDirectory(string sourceDir, string targetDir)
        {
            foreach (string dirPath in Directory.GetDirectories(sourceDir, "*", SearchOption.AllDirectories))
            {
                string relativePath = dirPath.Substring(sourceDir.Length).TrimStart('\\', '/');
                if (relativePath.StartsWith("dist") || relativePath.StartsWith(".git")) continue;
                Directory.CreateDirectory(Path.Combine(targetDir, relativePath));
            }

            foreach (string newPath in Directory.GetFiles(sourceDir, "*.*", SearchOption.AllDirectories))
            {
                string relativePath = newPath.Substring(sourceDir.Length).TrimStart('\\', '/');
                if (relativePath.StartsWith("dist") || relativePath.StartsWith(".git")) continue;
                File.Copy(newPath, Path.Combine(targetDir, relativePath), true);
            }
        }

        private static void RunUninstaller()
        {
            DialogResult confirm = MessageBox.Show(
                "Are you sure you want to uninstall ClassCheck from this computer?",
                "Uninstall ClassCheck",
                MessageBoxButtons.YesNo,
                MessageBoxIcon.Question
            );

            if (confirm != DialogResult.Yes) return;

            DialogResult keepData = MessageBox.Show(
                "Do you want to RETAIN your database and student attendance/grade records?\n\nClick 'Yes' to preserve your database for future use.\nClick 'No' to remove all data completely.",
                "Preserve Data",
                MessageBoxButtons.YesNoCancel,
                MessageBoxIcon.Question
            );

            if (keepData == DialogResult.Cancel) return;

            string appDir = AppDomain.CurrentDomain.BaseDirectory.TrimEnd('\\', '/');

            // 1. Terminate running instances
            try
            {
                foreach (Process p in Process.GetProcessesByName("ClassCheck"))
                {
                    try { p.Kill(); p.WaitForExit(1500); } catch { }
                }
            }
            catch { }

            // 2. Remove shortcuts
            try
            {
                string desktopLnk = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory), "ClassCheck.lnk");
                if (File.Exists(desktopLnk)) File.Delete(desktopLnk);

                string startMenuDir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.Programs), "ClassCheck");
                if (Directory.Exists(startMenuDir)) Directory.Delete(startMenuDir, true);
            }
            catch { }

            // 3. Remove Registry Entry
            try
            {
                Registry.CurrentUser.DeleteSubKeyTree(@"Software\Microsoft\Windows\CurrentVersion\Uninstall\ClassCheck", false);
            }
            catch { }

            // 4. Remove Files
            try
            {
                if (keepData == DialogResult.Yes)
                {
                    // Remove everything EXCEPT database/ and storage/app/
                    foreach (string file in Directory.GetFiles(appDir))
                    {
                        try { File.Delete(file); } catch { }
                    }
                    foreach (string dir in Directory.GetDirectories(appDir))
                    {
                        string dirName = Path.GetFileName(dir).ToLower();
                        if (dirName != "database" && dirName != "storage")
                        {
                            try { Directory.Delete(dir, true); } catch { }
                        }
                    }
                }
                else
                {
                    // Clean uninstall batch script to remove remaining directory
                    string tempBatch = Path.Combine(Path.GetTempPath(), "cc_uninst_" + Guid.NewGuid().ToString("N") + ".bat");
                    string batContent = "@echo off\n" +
                                        "timeout /t 2 /nobreak > nul\n" +
                                        "rmdir /s /q \"" + appDir + "\"\n" +
                                        "del \"%~f0\"\n";
                    File.WriteAllText(tempBatch, batContent);
                    Process.Start(new ProcessStartInfo(tempBatch) { CreateNoWindow = true, WindowStyle = ProcessWindowStyle.Hidden });
                }
            }
            catch { }

            MessageBox.Show("ClassCheck was successfully uninstalled from your computer.", "Uninstalled", MessageBoxButtons.OK, MessageBoxIcon.Information);
        }

        [STAThread]
        static void Main(string[] args)
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);

            if (args != null && args.Length > 0 && Array.Exists(args, a => a.Equals("--uninstall", StringComparison.OrdinalIgnoreCase)))
            {
                RunUninstaller();
                return;
            }

            Application.Run(new InstallerForm());
        }
    }

    [ComImport]
    [Guid("00021401-0000-0000-C000-000000000046")]
    internal class CShellLink
    {
    }

    [ComImport]
    [InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
    [Guid("000214F9-0000-0000-C000-000000000046")]
    internal interface IShellLinkW
    {
        void GetPath([Out, MarshalAs(UnmanagedType.LPWStr)] StringBuilder pszFile, int cchMaxPath, out IntPtr pfd, uint fFlags);
        void GetIDList(out IntPtr ppidl);
        void SetIDList(IntPtr pidl);
        void GetDescription([Out, MarshalAs(UnmanagedType.LPWStr)] StringBuilder pszName, int cchMaxName);
        void SetDescription([MarshalAs(UnmanagedType.LPWStr)] string pszName);
        void GetWorkingDirectory([Out, MarshalAs(UnmanagedType.LPWStr)] StringBuilder pszDir, int cchMaxPath);
        void SetWorkingDirectory([MarshalAs(UnmanagedType.LPWStr)] string pszDir);
        void GetArguments([Out, MarshalAs(UnmanagedType.LPWStr)] StringBuilder pszArgs, int cchMaxPath);
        void SetArguments([MarshalAs(UnmanagedType.LPWStr)] string pszArgs);
        void GetHotkey(out short pwHotkey);
        void SetHotkey(short wHotkey);
        void GetShowCmd(out int piShowCmd);
        void SetShowCmd(int iShowCmd);
        void GetIconLocation([Out, MarshalAs(UnmanagedType.LPWStr)] StringBuilder pszIconPath, int cchIconPath, out int piIcon);
        void SetIconLocation([MarshalAs(UnmanagedType.LPWStr)] string pszIconPath, int iIcon);
        void SetRelativePath([MarshalAs(UnmanagedType.LPWStr)] string pszPathRel, uint dwReserved);
        void Resolve(IntPtr hwnd, uint fFlags);
        void SetPath([MarshalAs(UnmanagedType.LPWStr)] string pszFile);
    }

    [ComImport]
    [InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
    [Guid("0000010b-0000-0000-C000-000000000046")]
    internal interface IPersistFile
    {
        void GetClassID(out Guid pClassID);
        void IsDirty();
        void Load([MarshalAs(UnmanagedType.LPWStr)] string pszFileName, uint dwMode);
        void Save([MarshalAs(UnmanagedType.LPWStr)] string pszFileName, [MarshalAs(UnmanagedType.Bool)] bool fRemember);
        void SaveCompleted([MarshalAs(UnmanagedType.LPWStr)] string pszFileName);
        void GetCurFile([Out, MarshalAs(UnmanagedType.LPWStr)] string ppszFileName);
    }
}

using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Reflection;
using System.Runtime.InteropServices;
using Microsoft.Win32;

namespace ClassCheckInstaller
{
    public static class InstallDiscovery
    {
        public static string ExistingFolder(string candidate)
        {
            if (string.IsNullOrWhiteSpace(candidate)) return null;
            try
            {
                string path = Environment.ExpandEnvironmentVariables(candidate.Trim().Trim('"'));
                if (path.EndsWith(".exe", StringComparison.OrdinalIgnoreCase)) path = Path.GetDirectoryName(path);
                path = Path.GetFullPath(path).TrimEnd('\\', '/');
                foreach (string folder in new[] { path, Path.Combine(path, "ClassCheck") })
                    if (File.Exists(Path.Combine(folder, "ClassCheck.exe")) && File.Exists(Path.Combine(folder, "artisan")) && Directory.Exists(Path.Combine(folder, "public"))) return folder;
            }
            catch { }
            return null;
        }

        // Injectable candidates let regression tests cover missing/stale registry entries.
        public static string Find(IEnumerable<string> candidates)
        {
            foreach (string candidate in candidates)
            {
                string folder = ExistingFolder(candidate);
                if (folder != null) return folder;
            }
            return null;
        }

        public static string Detect()
        {
            var candidates = new List<string>();
            foreach (Process process in Process.GetProcessesByName("ClassCheck"))
                using (process) { try { candidates.Add(process.MainModule.FileName); } catch { } }

            // Portable/older installers often have working shortcuts but no uninstall key.
            foreach (Environment.SpecialFolder special in new[] { Environment.SpecialFolder.DesktopDirectory, Environment.SpecialFolder.CommonDesktopDirectory, Environment.SpecialFolder.Programs, Environment.SpecialFolder.CommonPrograms })
            {
                string root = Environment.GetFolderPath(special);
                if (string.IsNullOrEmpty(root)) continue;
                candidates.Add(ReadShortcut(Path.Combine(root, "ClassCheck.lnk")));
                candidates.Add(ReadShortcut(Path.Combine(root, "ClassCheck", "ClassCheck.lnk")));
            }
            foreach (RegistryHive hive in new[] { RegistryHive.CurrentUser, RegistryHive.LocalMachine })
            foreach (RegistryView view in new[] { RegistryView.Registry64, RegistryView.Registry32 })
            {
                try
                {
                    using (RegistryKey root = RegistryKey.OpenBaseKey(hive, view))
                    using (RegistryKey key = root.OpenSubKey(@"Software\Microsoft\Windows\CurrentVersion\Uninstall\ClassCheck"))
                        if (key != null) candidates.Add(Convert.ToString(key.GetValue("InstallLocation")));
                }
                catch { }
            }
            candidates.Add(Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "ClassCheck"));
            foreach (DriveInfo drive in DriveInfo.GetDrives())
            {
                try
                {
                    if (!drive.IsReady || (drive.DriveType != DriveType.Fixed && drive.DriveType != DriveType.Removable)) continue;
                    candidates.Add(Path.Combine(drive.RootDirectory.FullName, "ClassCheck"));
                }
                catch { }
            }
            return Find(candidates);
        }

        private static string ReadShortcut(string path)
        {
            if (!File.Exists(path)) return null;
            object shell = null, shortcut = null;
            try
            {
                Type type = Type.GetTypeFromProgID("WScript.Shell");
                if (type == null) return null;
                shell = Activator.CreateInstance(type);
                shortcut = type.InvokeMember("CreateShortcut", BindingFlags.InvokeMethod, null, shell, new object[] { path });
                return Convert.ToString(shortcut.GetType().InvokeMember("TargetPath", BindingFlags.GetProperty, null, shortcut, null));
            }
            catch { return null; }
            finally
            {
                if (shortcut != null && Marshal.IsComObject(shortcut)) Marshal.ReleaseComObject(shortcut);
                if (shell != null && Marshal.IsComObject(shell)) Marshal.ReleaseComObject(shell);
            }
        }
    }
}

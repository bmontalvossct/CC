using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.IO.Compression;
using System.Security.Cryptography;

namespace ClassCheckInstaller
{
    // Shared by the setup UI and the upgrade integration tests.
    public static class SafeUpdate
    {
        public static void StopApplication(string destination)
        {
            string root = Path.GetFullPath(destination).TrimEnd('\\', '/') + Path.DirectorySeparatorChar;
            foreach (string name in new[] { "ClassCheck", "php", "php-win", "php-cgi" })
            foreach (Process process in Process.GetProcessesByName(name))
            {
                using (process)
                {
                    string path;
                    try { path = process.MainModule.FileName; }
                    catch { continue; } // Unrelated/protected processes are never terminated.
                    if (!Path.GetFullPath(path).StartsWith(root, StringComparison.OrdinalIgnoreCase)) continue;
                    process.Kill();
                    if (!process.WaitForExit(10000)) throw new IOException("Close ClassCheck before updating.");
                }
            }
        }

        public static string Install(ZipArchive archive, string destination)
        {
            string root = Path.GetFullPath(destination).TrimEnd('\\', '/') + Path.DirectorySeparatorChar;
            // Validate the entire archive before making any changes.
            foreach (ZipArchiveEntry entry in archive.Entries) Resolve(root, entry.FullName);
            Directory.CreateDirectory(root);
            string backup = Backup(root);
            foreach (ZipArchiveEntry entry in archive.Entries)
            {
                string target = Resolve(root, entry.FullName);
                if (string.IsNullOrEmpty(entry.Name)) { Directory.CreateDirectory(target); continue; }
                if (File.Exists(target) && Preserve(entry.FullName)) continue;
                if (File.Exists(target) && new FileInfo(target).Length == entry.Length)
                {
                    using (Stream contents = entry.Open())
                        if (Hash(contents) == Hash(target)) continue;
                }
                Directory.CreateDirectory(Path.GetDirectoryName(target));
                // Windows scanners can briefly hold newly written application files.
                for (int attempt = 0; ; attempt++)
                {
                    try { entry.ExtractToFile(target, true); break; }
                    catch (IOException) { if (attempt >= 20) throw; System.Threading.Thread.Sleep(250); }
                }
            }
            // Do not report success until application code and frontend assets match
            // this installer. Data/configuration entries are deliberately excluded.
            foreach (ZipArchiveEntry entry in archive.Entries)
            {
                if (string.IsNullOrEmpty(entry.Name) || Preserve(entry.FullName)) continue;
                string target = Resolve(root, entry.FullName);
                if (!File.Exists(target) || new FileInfo(target).Length != entry.Length)
                    throw new IOException("Update verification failed for " + entry.FullName);
                using (Stream contents = entry.Open())
                    if (Hash(contents) != Hash(target)) throw new IOException("Update verification failed for " + entry.FullName);
            }
            File.WriteAllText(Path.Combine(root, "last-update.txt"), "Verified application update: " + DateTime.Now.ToString("s") + Environment.NewLine + "Installation: " + root + Environment.NewLine + "Data backup: " + (backup ?? "New installation") + Environment.NewLine);
            return backup;
        }

        private static string Resolve(string root, string relative)
        {
            if (relative.IndexOf(':') >= 0 || Path.IsPathRooted(relative)) throw new IOException("Invalid installer archive path.");
            string target = Path.GetFullPath(Path.Combine(root, relative.Replace('/', Path.DirectorySeparatorChar)));
            if (!target.StartsWith(root, StringComparison.OrdinalIgnoreCase)) throw new IOException("Invalid installer archive path.");
            // Do not follow junctions or symlinks into another installation/data folder.
            string parent = target;
            while (parent != null && parent.Length >= root.TrimEnd('\\').Length)
            {
                if ((File.Exists(parent) || Directory.Exists(parent)) && (File.GetAttributes(parent) & FileAttributes.ReparsePoint) != 0)
                    throw new IOException("The destination contains a linked folder: " + parent + ". Use the original installation folder.");
                parent = Path.GetDirectoryName(parent);
            }
            return target;
        }

        private static bool Preserve(string relative)
        {
            string path = relative.Replace('\\', '/').TrimStart('/');
            if (path.Equals(".env", StringComparison.OrdinalIgnoreCase) || path.StartsWith(".env.", StringComparison.OrdinalIgnoreCase)) return true;
            if (path.StartsWith("storage/", StringComparison.OrdinalIgnoreCase) || path.StartsWith("public/uploads/", StringComparison.OrdinalIgnoreCase) || path.StartsWith("public/storage/", StringComparison.OrdinalIgnoreCase)) return true;
            // Only migration/factory/seeder code may be replaced inside database/.
            if (path.StartsWith("database/", StringComparison.OrdinalIgnoreCase))
                return !(path.StartsWith("database/migrations/", StringComparison.OrdinalIgnoreCase) || path.StartsWith("database/factories/", StringComparison.OrdinalIgnoreCase) || path.StartsWith("database/seeders/", StringComparison.OrdinalIgnoreCase));
            return false;
        }

        private static string Backup(string root)
        {
            var files = new List<string>();
            foreach (string file in Directory.GetFiles(root, ".env*")) files.Add(file);
            foreach (string relative in new[] { "database", "storage", "public/uploads" })
            {
                string dir = Path.Combine(root, relative);
                if (Directory.Exists(dir)) Collect(dir, files);
            }
            if (files.Count == 0) return null;
            string backup = Path.Combine(root, "update-backups", DateTime.Now.ToString("yyyyMMdd-HHmmss") + "-" + Guid.NewGuid().ToString("N").Substring(0, 8));
            Directory.CreateDirectory(backup);
            var manifest = new List<string>();
            // A failure (locked file, full disk, bad checksum) aborts before extraction.
            foreach (string file in files)
            {
                string relative = file.Substring(root.Length);
                string copy = Path.Combine(backup, relative);
                Directory.CreateDirectory(Path.GetDirectoryName(copy));
                using (var input = new FileStream(file, FileMode.Open, FileAccess.Read, FileShare.Read))
                using (var output = new FileStream(copy, FileMode.CreateNew, FileAccess.Write, FileShare.None)) input.CopyTo(output);
                string hash = Hash(file);
                if (hash != Hash(copy)) throw new IOException("Backup verification failed for " + relative);
                manifest.Add(hash + "  " + relative);
            }
            File.WriteAllLines(Path.Combine(backup, "SHA256SUMS.txt"), manifest);
            return backup;
        }

        private static void Collect(string directory, List<string> files)
        {
            if ((File.GetAttributes(directory) & FileAttributes.ReparsePoint) != 0) throw new IOException("Cannot back up linked data folder: " + directory);
            foreach (string file in Directory.GetFiles(directory))
            {
                if ((File.GetAttributes(file) & FileAttributes.ReparsePoint) != 0) throw new IOException("Cannot back up linked data file: " + file);
                files.Add(file);
            }
            foreach (string child in Directory.GetDirectories(directory)) Collect(child, files);
        }

        private static string Hash(string path)
        {
            using (var stream = File.OpenRead(path)) return Hash(stream);
        }

        private static string Hash(Stream stream)
        {
            using (var sha = SHA256.Create()) return BitConverter.ToString(sha.ComputeHash(stream)).Replace("-", "").ToLowerInvariant();
        }
    }
}

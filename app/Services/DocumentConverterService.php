<?php

namespace App\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class DocumentConverterService
{
    /**
     * Common static paths where LibreOffice is typically installed on Windows.
     * Dynamic glob scanning is also performed in getLibreOfficeBinary().
     *
     * @var array<int, string>
     */
    protected array $windowsSearchPaths = [
        'C:\Program Files\LibreOffice\program\soffice.exe',
        'C:\Program Files\LibreOffice\program\soffice.com',
        'C:\Program Files (x86)\LibreOffice\program\soffice.exe',
        'C:\Program Files (x86)\LibreOffice\program\soffice.com',
        // Version-specific folder names used by winget / MSI installs
        'C:\Program Files\LibreOffice 24\program\soffice.exe',
        'C:\Program Files\LibreOffice 24\program\soffice.com',
        'C:\Program Files\LibreOffice 7\program\soffice.exe',
        'C:\Program Files\LibreOffice 7\program\soffice.com',
        'C:\Program Files\LibreOffice 25\program\soffice.exe',
        'C:\Program Files\LibreOffice 25\program\soffice.com',
        'C:\Program Files\LibreOffice 6\program\soffice.exe',
        'C:\Program Files\LibreOffice 6\program\soffice.com',
        // Alternate Program Files (x86) versioned paths
        'C:\Program Files (x86)\LibreOffice 24\program\soffice.exe',
        'C:\Program Files (x86)\LibreOffice 7\program\soffice.exe',
    ];

    protected ?string $resolvedBinaryPath = null;

    public function isAvailable(): bool
    {
        return $this->getLibreOfficeBinary() !== null;
    }

    public function getLibreOfficeBinary(): ?string
    {
        if ($this->resolvedBinaryPath !== null) {
            return $this->resolvedBinaryPath;
        }

        // 1. Check LIBREOFFICE_BINARY env override first
        $customBinary = env('LIBREOFFICE_BINARY');
        if ($customBinary && file_exists($customBinary) && is_file($customBinary)) {
            return $this->resolvedBinaryPath = $customBinary;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            // 2. Check static well-known paths
            foreach ($this->windowsSearchPaths as $path) {
                if (file_exists($path) && is_file($path)) {
                    return $this->resolvedBinaryPath = $path;
                }
            }

            // 3. Dynamic glob scan of both Program Files directories for any
            //    folder starting with "LibreOffice" — covers any version number
            $programFileDirs = [
                'C:\Program Files',
                'C:\Program Files (x86)',
            ];
            foreach ($programFileDirs as $base) {
                if (! is_dir($base)) {
                    continue;
                }
                $entries = @scandir($base);
                if (! $entries) {
                    continue;
                }
                foreach ($entries as $entry) {
                    if (stripos($entry, 'LibreOffice') === 0) {
                        foreach (['soffice.exe', 'soffice.com'] as $bin) {
                            $candidate = $base . DIRECTORY_SEPARATOR . $entry
                                . DIRECTORY_SEPARATOR . 'program'
                                . DIRECTORY_SEPARATOR . $bin;
                            if (file_exists($candidate) && is_file($candidate)) {
                                return $this->resolvedBinaryPath = $candidate;
                            }
                        }
                    }
                }
            }
        }

        // 4. Fall back to PATH lookup (works on Linux/macOS and Windows if soffice is in PATH)
        $lookupCmd = PHP_OS_FAMILY === 'Windows'
            ? 'where.exe soffice.exe soffice.com soffice 2>nul'
            : 'which soffice 2>/dev/null';

        $found = $this->lookupExecutableInPath($lookupCmd);
        if ($found) {
            return $this->resolvedBinaryPath = $found;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            $found = $this->lookupExecutableInPath('which libreoffice 2>/dev/null');
            if ($found) {
                return $this->resolvedBinaryPath = $found;
            }
        }

        return null;
    }

    /**
     * Convert an uploaded Word document (.doc or .docx) to PDF.
     *
     * @param  UploadedFile  $file
     * @return array{content: string, original_name: string, pdf_name: string}
     *
     * @throws Exception
     */
    public function convertToPdf(UploadedFile $file): array
    {
        $binaryPath = $this->getLibreOfficeBinary();

        if (! $binaryPath) {
            throw new Exception(
                'LibreOffice is not installed or could not be detected on this server.'
            );
        }

        $originalClientName = $file->getClientOriginalName();
        $extension          = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['doc', 'docx'], true)) {
            throw new Exception('Unsupported file type. Only .doc and .docx files can be converted.');
        }

        $uuid        = (string) Str::uuid();
        $baseTempDir = storage_path('app' . DIRECTORY_SEPARATOR . 'temp_conversions');
        $workDir     = $baseTempDir . DIRECTORY_SEPARATOR . $uuid;

        // Profile dir goes in the OS temp directory — OUTSIDE htdocs/web root.
        // LibreOffice exits with code 1 if its profile path is inside a directory
        // that has restricted permissions (like htdocs on Apache/IIS).
        $profileBaseDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lo_profiles';
        @mkdir($profileBaseDir, 0755, true);
        $profileDir = $profileBaseDir . DIRECTORY_SEPARATOR . $uuid;

        if (! is_dir($workDir) && ! mkdir($workDir, 0755, true) && ! is_dir($workDir)) {
            throw new Exception('Failed to create temporary work directory for conversion.');
        }
        if (! is_dir($profileDir) && ! mkdir($profileDir, 0755, true) && ! is_dir($profileDir)) {
            throw new Exception('Failed to create temporary profile directory for conversion.');
        }

        $inputFileName   = 'document.' . $extension;
        $inputFilePath   = $workDir . DIRECTORY_SEPARATOR . $inputFileName;
        $expectedPdfPath = $workDir . DIRECTORY_SEPARATOR . 'document.pdf';

        try {
            $file->move($workDir, $inputFileName);

            if (! file_exists($inputFilePath)) {
                throw new Exception('Uploaded file could not be saved to the conversion workspace.');
            }

            [$exitCode, $stdout, $stderr] = $this->runLibreOffice(
                $binaryPath, $workDir, $profileDir, $inputFilePath
            );

            if (! file_exists($expectedPdfPath) || filesize($expectedPdfPath) === 0) {
                $detail = trim($stderr . ' ' . $stdout);
                throw new Exception(
                    'LibreOffice could not convert the document. ' .
                    ($detail !== ''
                        ? 'Details: ' . $detail
                        : 'No output file was generated (exit code ' . $exitCode . ').')
                );
            }

            $pdfContent = file_get_contents($expectedPdfPath);
            if ($pdfContent === false || strlen($pdfContent) === 0) {
                throw new Exception('The converted PDF file could not be read or is empty.');
            }

            $baseName    = pathinfo($originalClientName, PATHINFO_FILENAME);
            $pdfFileName = ($baseName !== '' ? $baseName : 'document') . '.pdf';

            return [
                'content'       => $pdfContent,
                'original_name' => $originalClientName,
                'pdf_name'      => $pdfFileName,
            ];
        } finally {
            $this->removeDirectoryRecursively($workDir);
            $this->removeDirectoryRecursively($profileDir);
        }
    }

    /**
     * Run LibreOffice headless conversion.
     *
     * The core challenge on Windows: every approach that passes pipe or file
     * handles for stdout/stderr to a child process via proc_open() causes
     * LibreOffice to hang or exit with code 1 when PHP is running as a
     * non-interactive server process (php artisan serve, Apache mod_php).
     * This is because Windows inherits console handles into child processes;
     * when there is no real console attached, LibreOffice's VCL layer fails
     * to initialise and aborts.
     *
     * Solution: write a .bat wrapper that sets the necessary environment
     * (SAL_USE_VCLPLUGIN=svp to force the headless rendering plugin, PATH
     * so LO finds its own DLLs), runs soffice.exe with I/O fully redirected
     * to files inside the batch file itself (so PHP never holds any handle
     * to those files), and writes a sentinel file when done. PHP polls for
     * the sentinel rather than waiting on any process handle — completely
     * avoids all inheritance and pipe-deadlock issues.
     *
     * @return array{int, string, string}  [exitCode, stdout, stderr]
     */
    protected function runLibreOffice(
        string $binaryPath,
        string $workDir,
        string $profileDir,
        string $inputFile
    ): array {
        $profileUrl   = 'file:///' . str_replace('\\', '/', $profileDir);
        $stdoutPath   = $workDir . DIRECTORY_SEPARATOR . 'lo_stdout.txt';
        $stderrPath   = $workDir . DIRECTORY_SEPARATOR . 'lo_stderr.txt';
        $doneFlagPath = $workDir . DIRECTORY_SEPARATOR . 'lo_done.txt';
        $batPath      = $workDir . DIRECTORY_SEPARATOR . 'lo_run.bat';

        if (PHP_OS_FAMILY !== 'Windows') {
            // On Linux/macOS proc_open with file descriptors works fine.
            $descriptors = [
                0 => ['file', '/dev/null', 'r'],
                1 => ['file', $stdoutPath, 'w'],
                2 => ['file', $stderrPath, 'w'],
            ];
            $loDir       = dirname($binaryPath);
            $currentPath = getenv('PATH') ?: '';
            $cmd = sprintf(
                '"%s" "-env:UserInstallation=%s" --headless --norestore --nofirststartwizard --convert-to pdf --outdir "%s" "%s"',
                $binaryPath, $profileUrl, $workDir, $inputFile
            );
            $env  = ['PATH' => $loDir . ':' . $currentPath, 'SAL_USE_VCLPLUGIN' => 'svp'];
            $proc = proc_open($cmd, $descriptors, $pipes, $workDir, $env);
            if (! is_resource($proc)) {
                throw new Exception('Failed to start the LibreOffice process.');
            }
            $timeout = 120; $deadline = time() + $timeout; $status = [];
            while (time() < $deadline) {
                usleep(250000);
                $status = proc_get_status($proc);
                if (! $status['running']) break;
            }
            $ec = proc_close($proc);
            if ($ec === -1) $ec = $status['exitcode'] ?? -1;
            if (! empty($status['running'])) {
                throw new Exception('LibreOffice conversion timed out after ' . $timeout . ' seconds.');
            }
            return [
                $ec,
                file_exists($stdoutPath) ? (string) file_get_contents($stdoutPath) : '',
                file_exists($stderrPath) ? (string) file_get_contents($stderrPath) : '',
            ];
        }

        // ── Windows path ──────────────────────────────────────────────────────
        //
        // Build the PATH string: LO program dir first so its DLLs are found.
        $loDir       = dirname($binaryPath);
        $currentPath = getenv('PATH') ?: getenv('Path') ?: '';
        $pathEnv     = $loDir . ';' . $currentPath;

        // Write a batch file that:
        //  • Sets PATH and SAL_USE_VCLPLUGIN so LO starts in headless mode
        //  • Runs soffice.exe with stdout/stderr redirected to files
        //    (the redirection is inside the .bat, so PHP holds NO handles)
        //  • Writes a sentinel file after soffice exits so PHP knows it's done
        $bat  = "@echo off\r\n";
        $bat .= "SET PATH={$pathEnv}\r\n";
        $bat .= "SET SAL_USE_VCLPLUGIN=svp\r\n";
        $bat .= sprintf(
            "\"%s\" \"-env:UserInstallation=%s\" --headless --norestore --nofirststartwizard --convert-to pdf --outdir \"%s\" \"%s\" > \"%s\" 2> \"%s\"\r\n",
            $binaryPath, $profileUrl, $workDir, $inputFile, $stdoutPath, $stderrPath
        );
        $bat .= "echo done > \"{$doneFlagPath}\"\r\n";

        file_put_contents($batPath, $bat);

        // Launch via cmd.exe /C — cmd.exe is the parent that holds the console
        // handles; soffice.exe is the grandchild and inherits nothing from PHP.
        // popen() gives us a handle to cmd.exe which we drain and close
        // immediately; soffice continues running independently.
        $handle = popen('cmd.exe /C "' . $batPath . '" 2>nul', 'r');
        if ($handle) {
            while (! feof($handle)) {
                fread($handle, 512);
            }
            pclose($handle);
        }

        // Poll for the sentinel file (LibreOffice finished) — 120 s max.
        $timeout  = 120;
        $deadline = time() + $timeout;

        while (! file_exists($doneFlagPath) && time() < $deadline) {
            usleep(300000); // 300 ms between checks
        }

        if (! file_exists($doneFlagPath)) {
            // Timed out — best-effort kill
            @shell_exec('taskkill /F /IM soffice.exe 2>nul');
            throw new Exception('LibreOffice conversion timed out after ' . $timeout . ' seconds.');
        }

        $stdout = file_exists($stdoutPath) ? trim((string) file_get_contents($stdoutPath)) : '';
        $stderr = file_exists($stderrPath) ? trim((string) file_get_contents($stderrPath)) : '';

        // Success is determined by whether the PDF exists (checked by caller).
        return [0, $stdout, $stderr];
    }

    /**
     * Installation instructions shown when LibreOffice is not detected.
     *
     * @return array<int, string>
     */
    public function getInstallationInstructions(): array
    {
        return [
            'Install via Windows Package Manager: Open PowerShell as Administrator and run: winget install TheDocumentFoundation.LibreOffice',
            'Install via Official Webpage: Download from https://www.libreoffice.org/download/download-libreoffice/ and run the setup.',
            'Default install path: C:\\Program Files\\LibreOffice\\program\\soffice.exe — DocuLite detects this automatically.',
            'After installation restart Apache: Open the XAMPP Control Panel and click Stop then Start next to Apache.',
        ];
    }

    protected function lookupExecutableInPath(string $command): ?string
    {
        try {
            $output = @shell_exec($command);
            if ($output) {
                $lines = array_filter(array_map('trim', explode("\n", $output)));
                foreach ($lines as $line) {
                    if (file_exists($line) && is_file($line)) {
                        return $line;
                    }
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    protected function removeDirectoryRecursively(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = @scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectoryRecursively($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}

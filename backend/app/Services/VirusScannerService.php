<?php

declare(strict_types=1);

namespace App\Services;

class VirusScannerService
{
    private const EICAR_TEST_STRING = 'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

    public function scanFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [
                'clean' => false,
                'threat' => 'File not found for scanning.',
            ];
        }

        $size = filesize($filePath);
        if ($size === 0) {
            return [
                'clean' => true,
                'threat' => null,
            ];
        }

        $handle = @fopen($filePath, 'rb');
        if (!$handle) {
            return [
                'clean' => false,
                'threat' => 'EICAR or malicious file blocked and quarantined by antivirus.',
            ];
        }

        $buffer = fread($handle, min($size, 524288));
        fclose($handle);

        if ($buffer === false) {
            return ['clean' => false, 'threat' => 'Error reading file stream.'];
        }

        // 1. Check EICAR standard antivirus test signature
        if (str_contains($buffer, self::EICAR_TEST_STRING)) {
            return [
                'clean' => false,
                'threat' => 'Win32/EICAR_Standard_Test_File detected.',
            ];
        }

        // 2. Check for Windows PE Executable signature (MZ header) in disguised files
        if (str_starts_with($buffer, "MZ")) {
            return [
                'clean' => false,
                'threat' => 'Executable binary payload disguised as document/image.',
            ];
        }

        // 3. Check for Linux ELF binary header
        if (str_starts_with($buffer, "\x7FELF")) {
            return [
                'clean' => false,
                'threat' => 'ELF executable payload detected.',
            ];
        }

        // 4. Check for embedded PHP / script tags inside uploaded images or media
        if (preg_match('/(<\?php|<\?=|<script\b)/i', $buffer)) {
            return [
                'clean' => false,
                'threat' => 'Embedded executable script tags detected in file stream.',
            ];
        }

        return [
            'clean' => true,
            'threat' => null,
        ];
    }
}

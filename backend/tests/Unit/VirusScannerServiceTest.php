<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\VirusScannerService;
use Tests\TestCase;

class VirusScannerServiceTest extends TestCase
{
    private VirusScannerService $scanner;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = new VirusScannerService();
        $this->tempDir = sys_get_temp_dir() . '/virus_test_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $f) {
                @unlink($f);
            }
            @rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_scan_returns_clean_for_safe_file(): void
    {
        $filePath = $this->tempDir . '/safe.txt';
        file_put_contents($filePath, 'Hello world, this is a clean document.');

        $result = $this->scanner->scanFile($filePath);

        $this->assertTrue($result['clean']);
        $this->assertNull($result['threat']);
    }

    public function test_scan_detects_eicar_test_string(): void
    {
        $filePath = $this->tempDir . '/eicar.com';
        file_put_contents($filePath, 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*');

        $result = $this->scanner->scanFile($filePath);

        $this->assertFalse($result['clean']);
        $this->assertStringContainsString('EICAR', $result['threat']);
    }

    public function test_scan_detects_disguised_mz_windows_executable(): void
    {
        $filePath = $this->tempDir . '/disguised.jpg';
        file_put_contents($filePath, "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff");

        $result = $this->scanner->scanFile($filePath);

        $this->assertFalse($result['clean']);
        $this->assertStringContainsString('Executable binary', $result['threat']);
    }

    public function test_scan_detects_elf_linux_executable(): void
    {
        $filePath = $this->tempDir . '/binary.png';
        file_put_contents($filePath, "\x7FELF\x02\x01\x01\x00" . str_repeat('A', 50));

        $result = $this->scanner->scanFile($filePath);

        $this->assertFalse($result['clean']);
        $this->assertStringContainsString('ELF executable', $result['threat']);
    }

    public function test_scan_detects_embedded_php_or_script_tags(): void
    {
        $filePath = $this->tempDir . '/malicious.pdf';
        file_put_contents($filePath, "%PDF-1.4\n<script>alert('xss');</script>");

        $result = $this->scanner->scanFile($filePath);

        $this->assertFalse($result['clean']);
        $this->assertStringContainsString('Embedded executable script tags', $result['threat']);
    }

    public function test_scan_handles_missing_file_gracefully(): void
    {
        $result = $this->scanner->scanFile($this->tempDir . '/non_existent_file.tmp');

        $this->assertFalse($result['clean']);
        $this->assertStringContainsString('not found', $result['threat']);
    }

    public function test_scan_allows_empty_file(): void
    {
        $filePath = $this->tempDir . '/empty.txt';
        file_put_contents($filePath, '');

        $result = $this->scanner->scanFile($filePath);

        $this->assertTrue($result['clean']);
        $this->assertNull($result['threat']);
    }
}

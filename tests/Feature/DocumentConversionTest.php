<?php

namespace Tests\Feature;

use App\Services\DocumentConverterService;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class DocumentConversionTest extends TestCase
{
    public function test_conversion_fails_when_no_file_uploaded(): void
    {
        $response = $this->postJson('/convert', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    public function test_conversion_rejects_unsupported_extensions(): void
    {
        $file = UploadedFile::fake()->create('document.txt', 100, 'text/plain');

        $response = $this->postJson('/convert', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
    }

    public function test_status_endpoint_returns_readiness_status(): void
    {
        $response = $this->getJson('/convert/status');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'ready',
            'binary',
            'instructions',
        ]);
    }

    public function test_conversion_returns_503_when_libreoffice_not_installed(): void
    {
        // Bind service with isAvailable returning false
        $mockService = Mockery::mock(DocumentConverterService::class);
        $mockService->shouldReceive('isAvailable')->once()->andReturn(false);
        $mockService->shouldReceive('getInstallationInstructions')->once()->andReturn([
            'Install LibreOffice via winget',
        ]);

        $this->app->instance(DocumentConverterService::class, $mockService);

        $file = UploadedFile::fake()->create('report.docx', 500, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $response = $this->postJson('/convert', [
            'file' => $file,
        ]);

        $response->assertStatus(503);
        $response->assertJson([
            'success' => false,
            'error_code' => 'LIBREOFFICE_NOT_INSTALLED',
        ]);
    }

    public function test_conversion_succeeds_and_returns_pdf_download(): void
    {
        $mockService = Mockery::mock(DocumentConverterService::class);
        $mockService->shouldReceive('isAvailable')->once()->andReturn(true);
        $mockService->shouldReceive('convertToPdf')->once()->andReturn([
            'content' => '%PDF-1.4 mock pdf content',
            'original_name' => 'quarterly_report.docx',
            'pdf_name' => 'quarterly_report.pdf',
        ]);

        $this->app->instance(DocumentConverterService::class, $mockService);

        $file = UploadedFile::fake()->create('quarterly_report.docx', 200, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $response = $this->post('/convert', [
            'file' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('quarterly_report.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertEquals('%PDF-1.4 mock pdf content', $response->getContent());
    }

    public function test_conversion_accepts_doc_files(): void
    {
        $mockService = Mockery::mock(DocumentConverterService::class);
        $mockService->shouldReceive('isAvailable')->once()->andReturn(true);
        $mockService->shouldReceive('convertToPdf')->once()->andReturn([
            'content' => '%PDF-1.4 mock binary doc conversion',
            'original_name' => 'legacy_doc.doc',
            'pdf_name' => 'legacy_doc.pdf',
        ]);

        $this->app->instance(DocumentConverterService::class, $mockService);

        $file = UploadedFile::fake()->create('legacy_doc.doc', 300, 'application/msword');

        $response = $this->post('/convert', [
            'file' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('legacy_doc.pdf', (string) $response->headers->get('Content-Disposition'));
    }
}

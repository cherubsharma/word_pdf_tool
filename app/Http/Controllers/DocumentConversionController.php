<?php

namespace App\Http\Controllers;

use App\Services\DocumentConverterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Throwable;

class DocumentConversionController extends Controller
{
    public function __construct(
        protected DocumentConverterService $converterService
    ) {}

    /**
     * Check server conversion readiness and LibreOffice status.
     */
    public function status(): JsonResponse
    {
        $isAvailable = $this->converterService->isAvailable();

        return response()->json([
            'ready' => $isAvailable,
            'binary' => $isAvailable ? $this->converterService->getLibreOfficeBinary() : null,
            'instructions' => $isAvailable ? [] : $this->converterService->getInstallationInstructions(),
        ]);
    }

    /**
     * Handle Word document upload and convert to PDF.
     */
    public function convert(Request $request): Response|JsonResponse
    {
        // 1. Validation: check file presence, extensions, and max size (25MB)
        $validator = Validator::make($request->all(), [
            'file' => [
                'required',
                'file',
                'max:25600', // 25 MB limit
                'extensions:doc,docx',
            ],
        ], [
            'file.required' => 'Please select a Word document to convert.',
            'file.file' => 'The uploaded item must be a valid document file.',
            'file.extensions' => 'Only .doc and .docx Word documents are supported.',
            'file.max' => 'The file size must not exceed 25MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first('file'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $uploadedFile = $request->file('file');
        $extension = strtolower($uploadedFile->getClientOriginalExtension());

        if (! in_array($extension, ['doc', 'docx'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file extension. Please upload a .doc or .docx document.',
            ], 422);
        }

        // 2. Check LibreOffice server-side availability
        if (! $this->converterService->isAvailable()) {
            return response()->json([
                'success' => false,
                'error_code' => 'LIBREOFFICE_NOT_INSTALLED',
                'message' => 'LibreOffice is not installed or detected on the server. Server-side conversion requires LibreOffice to preserve high-fidelity document formatting.',
                'instructions' => $this->converterService->getInstallationInstructions(),
            ], 503);
        }

        // 3. Convert document and return PDF binary download
        try {
            $conversion = $this->converterService->convertToPdf($uploadedFile);

            return response($conversion['content'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . addcslashes($conversion['pdf_name'], '"') . '"',
                'X-Original-Filename' => rawurlencode($conversion['original_name']),
                'X-Converted-Filename' => rawurlencode($conversion['pdf_name']),
                'Cache-Control' => 'no-cache, private',
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Conversion failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}

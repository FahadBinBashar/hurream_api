<?php

namespace App\Http\Controllers;

use App\Core\Request;
use App\Services\ReceiptInvoiceService;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DocumentController extends Controller
{
    public function __construct(private readonly ReceiptInvoiceService $service = new ReceiptInvoiceService())
    {
    }

    public function receipt(Request $request, array $params)
    {
        $receiptNo = $params['receipt_no'] ?? null;
        if (!$receiptNo) {
            return $this->json(['message' => 'Receipt number is required'], 400);
        }

        $record = $this->service->getReceiptData($receiptNo);
        if (!$record) {
            return $this->json(['message' => 'Receipt not found'], 404);
        }

        $format = strtolower((string)$request->input('format', 'html'));
        $html = $this->service->renderReceiptHtml($record);

        if ($format === 'pdf' || $request->input('download')) {
            $path = $this->service->generateReceiptPdf($record);
            return new HttpResponse(
                file_get_contents($path),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
                ]
            );
        }

        return new HttpResponse($html, 200, ['Content-Type' => 'text/html']);
    }

    public function invoice(Request $request, array $params)
    {
        $invoiceNo = $params['invoice_no'] ?? null;
        if (!$invoiceNo) {
            return $this->json(['message' => 'Invoice number is required'], 400);
        }

        $record = $this->service->getInvoiceData($invoiceNo);
        if (!$record) {
            return $this->json(['message' => 'Invoice not found'], 404);
        }

        $format = strtolower((string)$request->input('format', 'html'));
        $html = $this->service->renderInvoiceHtml($record);

        if ($format === 'pdf' || $request->input('download')) {
            $path = $this->service->generateInvoicePdf($record);
            return new HttpResponse(
                file_get_contents($path),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
                ]
            );
        }

        return new HttpResponse($html, 200, ['Content-Type' => 'text/html']);
    }
}

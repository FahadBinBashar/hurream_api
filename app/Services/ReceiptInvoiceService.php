<?php

namespace App\Services;

use App\Core\Database;
use App\Support\Env;
use GdImage;
use PDO;
use RuntimeException;

class ReceiptInvoiceService
{
    private const RECEIPT_TEMPLATE = 'receipt.html';
    private const INVOICE_TEMPLATE = 'invoice.html';
    private const FONT_PATH = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';

    public function getReceiptData(string $receiptNo): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT p.*, s.invoice_no, s.total_amount, s.total_shares, s.sale_type, c.name AS customer_name, c.phone AS customer_phone, '
            . 'c.email AS customer_email, c.address AS customer_address, pr.project_code, pr.project_name '
            . 'FROM share_sale_payments p '
            . 'JOIN share_sales s ON s.id = p.share_sale_id '
            . 'JOIN customers c ON c.id = p.customer_id '
            . 'JOIN projects pr ON pr.id = s.project_id '
            . 'WHERE p.receipt_no = :receipt'
        );
        $stmt->execute(['receipt' => $receiptNo]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$record) {
            return null;
        }

        $record['receipt_url'] = $this->receiptUrl($receiptNo);
        $record['amount'] = (float)($record['amount'] ?? 0);
        $record['total_amount'] = (float)($record['total_amount'] ?? 0);

        return $record;
    }

    public function getInvoiceData(string $invoiceNo): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT s.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email, c.address AS customer_address, '
            . 'pr.project_code, pr.project_name '
            . 'FROM share_sales s '
            . 'JOIN customers c ON c.id = s.customer_id '
            . 'JOIN projects pr ON pr.id = s.project_id '
            . 'WHERE s.invoice_no = :invoice'
        );
        $stmt->execute(['invoice' => $invoiceNo]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$record) {
            return null;
        }

        $paymentStmt = $pdo->prepare(
            'SELECT amount, payment_channel, receipt_no, received_at FROM share_sale_payments WHERE share_sale_id = :sale ORDER BY received_at ASC'
        );
        $paymentStmt->execute(['sale' => $record['id']]);
        $payments = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

        $totalPaid = 0.0;
        foreach ($payments as $payment) {
            $totalPaid += (float)($payment['amount'] ?? 0);
        }

        $record['payments'] = $payments;
        $record['total_paid'] = $totalPaid;
        $record['balance'] = max(0, ((float)($record['total_amount'] ?? 0)) - $totalPaid);
        $record['invoice_url'] = $this->invoiceUrl($invoiceNo);

        return $record;
    }

    public function renderReceiptHtml(array $data): string
    {
        $template = $this->loadTemplate(self::RECEIPT_TEMPLATE);
        $replacements = [
            '{{sale_id}}' => $data['share_sale_id'] ?? $data['id'] ?? '',
            '{{receipt_no}}' => $data['receipt_no'] ?? '',
            '{{invoice_no}}' => $data['invoice_no'] ?? '',
            '{{project_code}}' => $data['project_code'] ?? '',
            '{{project_name}}' => $data['project_name'] ?? '',
            '{{customer_name}}' => $data['customer_name'] ?? '',
            '{{customer_phone}}' => $data['customer_phone'] ?? '',
            '{{customer_email}}' => $data['customer_email'] ?? '',
            '{{customer_address}}' => $data['customer_address'] ?? '',
            '{{amount_paid}}' => number_format((float)($data['amount'] ?? 0), 2),
            '{{payment_channel}}' => ucfirst(str_replace('_', ' ', (string)($data['payment_channel'] ?? ''))),
            '{{received_at}}' => $data['received_at'] ?? '',
            '{{receipt_url}}' => $data['receipt_url'] ?? '',
            '{{qr_code}}' => $this->qrDataUri($data['receipt_url'] ?? ''),
        ];

        return strtr($template, $replacements);
    }

    public function renderInvoiceHtml(array $data): string
    {
        $template = $this->loadTemplate(self::INVOICE_TEMPLATE);
        $paymentRows = '';
        foreach ($data['payments'] ?? [] as $payment) {
            $paymentRows .= '<tr>'
                . '<td>' . htmlspecialchars($payment['receipt_no'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars(ucfirst(str_replace('_', ' ', (string)($payment['payment_channel'] ?? ''))), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="text-align:right">' . number_format((float)($payment['amount'] ?? 0), 2) . '</td>'
                . '<td>' . htmlspecialchars($payment['received_at'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
        }

        if ($paymentRows === '') {
            $paymentRows = '<tr><td colspan="4">No payments recorded yet.</td></tr>';
        }

        $replacements = [
            '{{sale_id}}' => $data['id'] ?? '',
            '{{invoice_no}}' => $data['invoice_no'] ?? '',
            '{{project_code}}' => $data['project_code'] ?? '',
            '{{project_name}}' => $data['project_name'] ?? '',
            '{{customer_name}}' => $data['customer_name'] ?? '',
            '{{customer_phone}}' => $data['customer_phone'] ?? '',
            '{{customer_email}}' => $data['customer_email'] ?? '',
            '{{customer_address}}' => $data['customer_address'] ?? '',
            '{{sale_type}}' => ucfirst((string)($data['sale_type'] ?? '')),
            '{{total_shares}}' => $data['total_shares'] ?? 0,
            '{{bonus_shares}}' => $data['bonus_shares'] ?? 0,
            '{{share_price}}' => number_format((float)($data['share_price'] ?? 0), 2),
            '{{total_amount}}' => number_format((float)($data['total_amount'] ?? 0), 2),
            '{{total_paid}}' => number_format((float)($data['total_paid'] ?? 0), 2),
            '{{balance}}' => number_format((float)($data['balance'] ?? 0), 2),
            '{{payment_rows}}' => $paymentRows,
            '{{invoice_url}}' => $data['invoice_url'] ?? '',
            '{{qr_code}}' => $this->qrDataUri($data['invoice_url'] ?? ''),
        ];

        return strtr($template, $replacements);
    }

    public function generateReceiptPdf(array $data): string
    {
        [$jpeg, $width, $height] = $this->buildReceiptImage($data);
        $path = storage_path('documents/receipts/' . ($data['receipt_no'] ?? uniqid('receipt_')) . '.pdf');

        return $this->storePdfFromJpeg($jpeg, $width, $height, $path);
    }

    public function generateInvoicePdf(array $data): string
    {
        [$jpeg, $width, $height] = $this->buildInvoiceImage($data);
        $path = storage_path('documents/invoices/' . ($data['invoice_no'] ?? uniqid('invoice_')) . '.pdf');

        return $this->storePdfFromJpeg($jpeg, $width, $height, $path);
    }

    private function buildReceiptImage(array $data): array
    {
        $width = 1000;
        $height = 1200;
        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $border = imagecolorallocate($image, 220, 220, 220);
        $heading = imagecolorallocate($image, 30, 64, 175);
        $text = imagecolorallocate($image, 33, 37, 41);

        imagefill($image, 0, 0, $white);
        imagerectangle($image, 10, 10, $width - 10, $height - 10, $border);

        $y = 70;
        imagettftext($image, 32, 0, 40, $y, $heading, self::FONT_PATH, 'Payment Receipt');
        $y += 40;
        imageline($image, 40, $y, $width - 40, $y, $border);
        $y += 40;

        $lines = [
            'Receipt No: ' . ($data['receipt_no'] ?? ''),
            'Invoice No: ' . ($data['invoice_no'] ?? ''),
            'Sale ID: ' . ($data['share_sale_id'] ?? $data['id'] ?? ''),
            'Project: ' . (($data['project_code'] ?? '') . ' - ' . ($data['project_name'] ?? '')),
            'Customer: ' . ($data['customer_name'] ?? ''),
            'Phone: ' . ($data['customer_phone'] ?? ''),
            'Email: ' . ($data['customer_email'] ?? ''),
            'Address: ' . ($data['customer_address'] ?? ''),
            'Amount Paid: ' . number_format((float)($data['amount'] ?? 0), 2),
            'Payment Channel: ' . ucfirst(str_replace('_', ' ', (string)($data['payment_channel'] ?? ''))),
            'Received At: ' . ($data['received_at'] ?? ''),
            'Receipt Link: ' . ($data['receipt_url'] ?? ''),
        ];

        foreach ($lines as $line) {
            imagettftext($image, 20, 0, 40, $y, $text, self::FONT_PATH, $line);
            $y += 35;
        }

        $qr = $this->buildQrImage($data['receipt_url'] ?? '');
        $qrSize = imagesx($qr);
        imagecopy($image, $qr, $width - $qrSize - 60, 80, 0, 0, $qrSize, $qrSize);

        $jpeg = $this->imageToJpeg($image);
        imagedestroy($image);
        imagedestroy($qr);

        return [$jpeg, $width, $height];
    }

    private function buildInvoiceImage(array $data): array
    {
        $width = 1000;
        $height = 1400;
        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $border = imagecolorallocate($image, 220, 220, 220);
        $heading = imagecolorallocate($image, 25, 135, 84);
        $text = imagecolorallocate($image, 33, 37, 41);

        imagefill($image, 0, 0, $white);
        imagerectangle($image, 10, 10, $width - 10, $height - 10, $border);

        $y = 70;
        imagettftext($image, 32, 0, 40, $y, $heading, self::FONT_PATH, 'Invoice');
        $y += 40;
        imageline($image, 40, $y, $width - 40, $y, $border);
        $y += 40;

        $lines = [
            'Invoice No: ' . ($data['invoice_no'] ?? ''),
            'Sale ID: ' . ($data['id'] ?? ''),
            'Project: ' . (($data['project_code'] ?? '') . ' - ' . ($data['project_name'] ?? '')),
            'Customer: ' . ($data['customer_name'] ?? ''),
            'Phone: ' . ($data['customer_phone'] ?? ''),
            'Email: ' . ($data['customer_email'] ?? ''),
            'Address: ' . ($data['customer_address'] ?? ''),
            'Sale Type: ' . ucfirst((string)($data['sale_type'] ?? '')),
            'Shares: ' . ($data['total_shares'] ?? 0) . ' (+' . ($data['bonus_shares'] ?? 0) . ' bonus)',
            'Share Price: ' . number_format((float)($data['share_price'] ?? 0), 2),
            'Total Amount: ' . number_format((float)($data['total_amount'] ?? 0), 2),
            'Total Paid: ' . number_format((float)($data['total_paid'] ?? 0), 2),
            'Balance: ' . number_format((float)($data['balance'] ?? 0), 2),
            'Invoice Link: ' . ($data['invoice_url'] ?? ''),
        ];

        foreach ($lines as $line) {
            imagettftext($image, 20, 0, 40, $y, $text, self::FONT_PATH, $line);
            $y += 35;
        }

        $qr = $this->buildQrImage($data['invoice_url'] ?? '');
        $qrSize = imagesx($qr);
        imagecopy($image, $qr, $width - $qrSize - 60, 80, 0, 0, $qrSize, $qrSize);

        $y += 20;
        imagettftext($image, 24, 0, 40, $y, $heading, self::FONT_PATH, 'Payments');
        $y += 20;
        imageline($image, 40, $y, $width - 40, $y, $border);
        $y += 30;

        foreach ($data['payments'] ?? [] as $payment) {
            $line = sprintf(
                '%s | %s | %s | %s',
                $payment['receipt_no'] ?? '',
                ucfirst(str_replace('_', ' ', (string)($payment['payment_channel'] ?? ''))),
                number_format((float)($payment['amount'] ?? 0), 2),
                $payment['received_at'] ?? ''
            );
            imagettftext($image, 18, 0, 60, $y, $text, self::FONT_PATH, $line);
            $y += 28;
        }

        if (empty($data['payments'])) {
            imagettftext($image, 18, 0, 60, $y, $text, self::FONT_PATH, 'No payments recorded yet.');
        }

        $jpeg = $this->imageToJpeg($image);
        imagedestroy($image);
        imagedestroy($qr);

        return [$jpeg, $width, $height];
    }

    private function qrDataUri(string $value): string
    {
        $qrImage = $this->buildQrImage($value);
        ob_start();
        imagepng($qrImage);
        $png = ob_get_clean();
        imagedestroy($qrImage);

        return 'data:image/png;base64,' . base64_encode($png);
    }

    private function buildQrImage(string $value, int $size = 240): GdImage
    {
        $image = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 30, 30, 30);
        imagefill($image, 0, 0, $white);

        // Basic deterministic pattern using hash as fallback; not a full QR implementation but gives a scannable-like grid.
        $hash = hash('sha256', $value ?: uniqid('qr_', true));
        $bits = '';
        foreach (str_split($hash) as $char) {
            $bits .= str_pad(base_convert($char, 16, 2), 4, '0', STR_PAD_LEFT);
        }

        $gridSize = 29;
        $cell = (int)floor($size / $gridSize);
        $index = 0;
        for ($y = 0; $y < $gridSize; $y++) {
            for ($x = 0; $x < $gridSize; $x++) {
                $bit = $bits[$index % strlen($bits)] ?? '0';
                if ($bit === '1') {
                    imagefilledrectangle(
                        $image,
                        $x * $cell,
                        $y * $cell,
                        ($x + 1) * $cell,
                        ($y + 1) * $cell,
                        $black
                    );
                }
                $index++;
            }
        }

        imagerectangle($image, 0, 0, $size - 1, $size - 1, $black);

        return $image;
    }

    private function imageToJpeg(GdImage $image): string
    {
        ob_start();
        imagejpeg($image, null, 90);
        return ob_get_clean();
    }

    private function storePdfFromJpeg(string $jpegData, int $widthPx, int $heightPx, string $path): string
    {
        $widthPt = $widthPx * 0.75; // approximate conversion from px to pt at 96 DPI
        $heightPt = $heightPx * 0.75;

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>', $widthPt, $heightPt);
        $objects[] = sprintf("<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream", $widthPx, $heightPx, strlen($jpegData), $jpegData);
        $contentStream = "q\n{$widthPt} 0 0 {$heightPt} 0 0 cm\n/Im0 Do\nQ";
        $objects[] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($contentStream), $contentStream);

        $pdf = "%PDF-1.3\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        $directory = dirname($path);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException('Unable to create directory for PDF generation: ' . $directory);
            }
        }

        file_put_contents($path, $pdf);

        return $path;
    }

    private function receiptUrl(string $receiptNo): string
    {
        $base = rtrim(config('app.url', Env::get('APP_URL', 'http://localhost')), '/');
        return $base . '/receipt/' . $receiptNo;
    }

    private function invoiceUrl(string $invoiceNo): string
    {
        $base = rtrim(config('app.url', Env::get('APP_URL', 'http://localhost')), '/');
        return $base . '/invoice/' . $invoiceNo;
    }

    private function loadTemplate(string $template): string
    {
        $path = base_path('resources/documents/' . $template);
        if (!file_exists($path)) {
            throw new RuntimeException('Template not found: ' . $template);
        }

        return file_get_contents($path);
    }
}

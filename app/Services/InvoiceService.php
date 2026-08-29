<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Presenters\OrderReceiptPresenter;
use Illuminate\Support\Facades\View;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use RuntimeException;

final class InvoiceService
{
    /**
     * Where to place SolaimanLipi.ttf for Bangla ligature support.
     * Example: storage/fonts/SolaimanLipi.ttf
     * Download: https://www.omicronlab.com/bangla-fonts.html -> SolaimanLipi
     * Alternative: Google Fonts NotoSansBengali -> NotoSansBengali-Regular.ttf (rename to SolaimanLipi.ttf or update fontdata)
     * Ensure storage/fonts and storage/app/mpdf/tmp are writable (php artisan storage:link, chmod 775).
     */
    public const FONT_PATH = 'fonts/SolaimanLipi.ttf';

    public const FONT_NAME = 'solaimanlipi';

    /**
     * Generate PDF bytes for the given order.
     *
     * @throws RuntimeException if Bangla font missing
     * @throws MpdfException
     */
    public function pdfBytes(Order $order): string
    {
        $order->loadMissing([
            'tenant.themeSettings',
            'tenant.settings',
            'tenant.primaryDomain',
            'customer',
            'items.serialNumbers',
            'payments.paymentMethod',
            'shippingMethod',
        ]);

        $this->ensureFontExists();
        $this->ensureTempDir();

        $presenter = new OrderReceiptPresenter($order);
        $receipt = $presenter->data();
        // Prepare logo for mPDF (absolute filesystem path or base64)
        $receipt['store']['logo_absolute'] = $this->logoAbsolutePath($receipt['store']['logo_url'] ?? null, $order);
        $receipt['store']['logo_base64'] = $this->logoBase64($receipt['store']['logo_absolute']);

        $html = View::make('pdf.invoice', ['receipt' => $receipt, 'order' => $order])->render();

        $mpdf = $this->makeMpdf();

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    public function filename(Order $order): string
    {
        $invoice = $order->getAttribute('invoice_number') ?: $order->getAttribute('order_number');

        return 'INV-'.preg_replace('/[^A-Za-z0-9\-_]/', '-', (string) $invoice).'.pdf';
    }

    /**
     * @throws MpdfException
     */
    private function makeMpdf(): Mpdf
    {
        $defaultConfig = (new ConfigVariables)->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new FontVariables)->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $fontDirs[] = storage_path('fonts');

        $fontData[self::FONT_NAME] = [
            'R' => 'SolaimanLipi.ttf',
            'useOTL' => 0xFF,
            'useKashida' => 75,
        ];

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_top' => 9,
            'margin_bottom' => 9,
            'margin_left' => 9,
            'margin_right' => 9,
            'default_font' => self::FONT_NAME,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'tempDir' => storage_path('app/mpdf/tmp'),
            'fontDir' => $fontDirs,
            'fontdata' => $fontData,
            'curlAllowUnsafeSslRequests' => true,
        ]);
    }

    private function ensureFontExists(): void
    {
        $path = storage_path(self::FONT_PATH);

        if (! file_exists($path)) {
            throw new RuntimeException(
                'Bangla font missing at '.self::FONT_PATH.' ('.$path.'). '.
                'Place SolaimanLipi.ttf at storage/fonts/SolaimanLipi.ttf. '.
                'Download from https://www.omicronlab.com/bangla-fonts.html or Google Fonts Noto Sans Bengali. '.
                'Ensure storage/fonts is readable and storage/app/mpdf/tmp is writable.'
            );
        }
    }

    private function ensureTempDir(): void
    {
        $dir = storage_path('app/mpdf/tmp');

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    private function logoAbsolutePath(?string $logoUrl, Order $order): ?string
    {
        $tenant = $order->getRelationValue('tenant');
        $theme = $tenant?->getRelationValue('themeSettings');
        $logoPath = $theme?->getAttribute('logo_path');

        if (! filled($logoPath)) {
            return null;
        }

        $absolute = storage_path('app/public/'.$logoPath);

        return file_exists($absolute) ? $absolute : null;
    }

    private function logoBase64(?string $absolutePath): ?string
    {
        if ($absolutePath === null || ! file_exists($absolutePath)) {
            return null;
        }

        $mime = mime_content_type($absolutePath) ?: 'image/png';
        $data = base64_encode((string) file_get_contents($absolutePath));

        return 'data:'.$mime.';base64,'.$data;
    }
}

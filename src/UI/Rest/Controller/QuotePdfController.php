<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Identity\Repository\CustomerRepository;
use Pet\UI\Rest\Support\PortalPermissionHelper;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * QuotePdfController
 *
 * Serves print-ready HTML for a quote at GET /pet/v1/quotes/{id}/pdf.
 * Returns an HTML document (not JSON). The browser can print-to-PDF directly.
 *
 * Authentication: standard WP REST cookie auth + nonce.
 * The portal opens the URL in a new tab; the endpoint self-prints on load.
 */
class QuotePdfController implements RestController
{
    private const NAMESPACE = 'pet/v1';

    private QuoteRepository    $quoteRepository;
    private CustomerRepository $customerRepository;

    public function __construct(
        QuoteRepository    $quoteRepository,
        CustomerRepository $customerRepository
    ) {
        $this->quoteRepository    = $quoteRepository;
        $this->customerRepository = $customerRepository;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/quotes/(?P<id>\d+)/pdf', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'renderPdf'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return PortalPermissionHelper::check('pet_sales', 'pet_hr', 'pet_manager');
    }

    public function renderPdf(WP_REST_Request $request): never
    {
        $id    = (int) $request->get_param('id');
        $quote = $this->quoteRepository->findById($id);

        if (!$quote) {
            status_header(404);
            echo '<p style="padding:40px;font-family:sans-serif;">Quote not found.</p>';
            exit;
        }

        // Resolve customer name
        $customerName = '';
        try {
            $customer     = $this->customerRepository->findById($quote->customerId());
            $customerName = $customer ? $customer->name() : '';
        } catch (\Throwable) {
            $customerName = 'Customer #' . $quote->customerId();
        }

        // Collect blocks data (the quote entity surfaces them via components())
        // We build a flat block list from the serialised component data.
        $blocks = [];
        foreach ($quote->components() as $component) {
            $type = $component->type();
            $row  = [
                'type'        => $type,
                'description' => $component->description(),
                'sellValue'   => $component->sellValue(),
                'internalCost'=> $component->internalCost(),
            ];
            $blocks[] = $row;
        }

        $totalValue    = $quote->totalValue();
        $totalCost     = $quote->totalInternalCost();
        $margin        = $totalValue > 0 ? round((($totalValue - $totalCost) / $totalValue) * 100, 1) : 0;
        $currency      = $quote->currency();
        $printDate     = date('j F Y');
        $state         = $quote->state()->toString();

        $html = $this->buildHtml(
            $quote->title(),
            $quote->description(),
            $customerName,
            $currency,
            $totalValue,
            $totalCost,
            $margin,
            $state,
            $quote->version(),
            $printDate,
            $blocks
        );

        // Serve raw HTML — bypass WP REST JSON wrapper
        status_header(200);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
        echo $html;
        exit;
    }

    // ── HTML generation ────────────────────────────────────────────────────

    private function fmt(float $amount, string $currency): string
    {
        $symbol = match ($currency) {
            'USD'   => '$',
            'EUR'   => '€',
            default => '£',
        };
        return $symbol . number_format($amount, 2);
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function buildHtml(
        string $title,
        ?string $description,
        string $customerName,
        string $currency,
        float $totalValue,
        float $totalCost,
        float $margin,
        string $state,
        int $version,
        string $printDate,
        array $blocks
    ): string {
        $titleEsc       = $this->esc($title);
        $customerEsc    = $this->esc($customerName);
        $descEsc        = $description ? nl2br($this->esc($description)) : '';
        $totalValueFmt  = $this->fmt($totalValue, $currency);
        $totalCostFmt   = $this->fmt($totalCost, $currency);
        $printDateEsc   = $this->esc($printDate);
        $stateEsc       = $this->esc(ucfirst(str_replace('_', ' ', $state)));

        $rowsHtml = '';
        foreach ($blocks as $i => $block) {
            $bg   = $i % 2 === 0 ? '#fff' : '#f9fafb';
            $desc = $this->esc($block['description'] ?? '(no description)');
            $val  = $this->fmt((float) ($block['sellValue'] ?? 0), $currency);
            $type = $this->esc($this->blockTypeLabel($block['type'] ?? ''));
            $rowsHtml .= "<tr style=\"background:{$bg}\">"
                . "<td style=\"padding:8px 12px;border-bottom:1px solid #e5e7eb;font-size:12px;color:#6b7280;\">{$type}</td>"
                . "<td style=\"padding:8px 12px;border-bottom:1px solid #e5e7eb;font-size:13px;\">{$desc}</td>"
                . "<td style=\"padding:8px 12px;border-bottom:1px solid #e5e7eb;font-size:13px;font-weight:600;text-align:right;\">{$val}</td>"
                . "</tr>\n";
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="3" style="padding:20px;text-align:center;color:#9ca3af;font-size:13px;">No line items on this quote</td></tr>';
        }

        $marginColor = $margin >= 40 ? '#16a34a' : ($margin >= 20 ? '#d97706' : '#e11d48');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$titleEsc} — Quote</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; color: #111827; background: #fff; }

    .page { max-width: 840px; margin: 0 auto; padding: 48px 40px; }

    /* Header */
    .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 36px; border-bottom: 3px solid #2563eb; padding-bottom: 20px; }
    .brand { font-size: 22px; font-weight: 800; color: #2563eb; letter-spacing: -0.5px; }
    .brand span { color: #111827; }
    .meta { text-align: right; font-size: 12px; color: #6b7280; line-height: 1.6; }
    .meta strong { color: #111827; font-size: 13px; }

    /* Quote title block */
    .quote-title { font-size: 24px; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 4px; }
    .quote-sub { font-size: 13px; color: #6b7280; margin-bottom: 24px; }

    /* Info grid */
    .info-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 32px; }
    .info-cell label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #9ca3af; margin-bottom: 3px; }
    .info-cell .val { font-size: 14px; font-weight: 600; color: #111827; }

    /* Description */
    .description { font-size: 13px; color: #374151; line-height: 1.7; background: #f9fafb; border-left: 3px solid #2563eb; padding: 14px 16px; border-radius: 0 6px 6px 0; margin-bottom: 28px; }

    /* Table */
    table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
    thead th { background: #f3f4f6; padding: 10px 12px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #6b7280; text-align: left; }
    thead th:last-child { text-align: right; }

    /* Totals */
    .totals { margin-left: auto; width: 280px; }
    .totals-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; border-bottom: 1px solid #f0f0f0; }
    .totals-row.total { font-size: 16px; font-weight: 700; border-top: 2px solid #111827; border-bottom: none; padding-top: 10px; margin-top: 4px; }
    .totals-row .label { color: #6b7280; }
    .totals-row.total .label { color: #111827; }

    /* Footer */
    .footer { margin-top: 48px; padding-top: 16px; border-top: 1px solid #e5e7eb; font-size: 11px; color: #9ca3af; display: flex; justify-content: space-between; }

    @media print {
      body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .page { padding: 0; max-width: 100%; }
      .no-print { display: none !important; }
    }
  </style>
</head>
<body>
  <!-- Print button — hidden when printing -->
  <div class="no-print" style="background:#f9fafb;border-bottom:1px solid #e5e7eb;padding:12px 24px;display:flex;align-items:center;gap:12px;">
    <button onclick="window.print()" style="padding:9px 18px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
      🖨 Print / Save as PDF
    </button>
    <span style="font-size:13px;color:#6b7280;">Use your browser's print dialog to save as PDF. Choose "Save as PDF" as the destination.</span>
  </div>

  <div class="page">
    <!-- Header -->
    <div class="header">
      <div class="brand">PET <span>Portal</span></div>
      <div class="meta">
        <strong>QUOTATION</strong><br>
        Prepared: {$printDateEsc}<br>
        Status: {$stateEsc}<br>
        Version: v{$version}
      </div>
    </div>

    <!-- Quote title -->
    <div class="quote-title">{$titleEsc}</div>
    <div class="quote-sub">Prepared for: <strong>{$customerEsc}</strong></div>

    <!-- Info grid -->
    <div class="info-grid">
      <div class="info-cell">
        <label>Customer</label>
        <div class="val">{$customerEsc}</div>
      </div>
      <div class="info-cell">
        <label>Total Value</label>
        <div class="val">{$totalValueFmt}</div>
      </div>
      <div class="info-cell">
        <label>Margin</label>
        <div class="val" style="color:{$marginColor};">{$margin}%</div>
      </div>
    </div>

    <!-- Description -->
    {$this->descriptionSection($descEsc)}

    <!-- Line items table -->
    <table>
      <thead>
        <tr>
          <th style="width:140px">Type</th>
          <th>Description</th>
          <th style="width:120px;text-align:right">Value</th>
        </tr>
      </thead>
      <tbody>
        {$rowsHtml}
      </tbody>
    </table>

    <!-- Totals -->
    <div class="totals">
      <div class="totals-row">
        <span class="label">Subtotal (cost)</span>
        <span>{$totalCostFmt}</span>
      </div>
      <div class="totals-row total">
        <span class="label">Total (sell)</span>
        <span>{$totalValueFmt}</span>
      </div>
    </div>

    <!-- Footer -->
    <div class="footer">
      <span>Generated by PET Portal</span>
      <span>Confidential — for discussion purposes only</span>
    </div>
  </div>

  <script>
    // Auto-trigger print when opened directly (not in iframe)
    if (window.self === window.top) {
      // Small delay so the page renders before print dialog
      window.addEventListener('load', function() {
        setTimeout(function() {
          // Don't auto-print; let user click the button.
          // Uncomment next line to auto-print:
          // window.print();
        }, 300);
      });
    }
  </script>
</body>
</html>
HTML;
    }

    private function descriptionSection(string $descEsc): string
    {
        if ($descEsc === '') return '';
        return "<div class=\"description\">{$descEsc}</div>\n";
    }

    private function blockTypeLabel(string $type): string
    {
        return match ($type) {
            'OnceOffSimpleServiceBlock' => 'Service',
            'OnceOffProjectBlock'       => 'Project',
            'HardwareBlock'             => 'Hardware',
            'PriceAdjustmentBlock'      => 'Adjustment',
            'TextBlock'                 => 'Note',
            'implementation'            => 'Implementation',
            'recurring_service'         => 'Recurring Service',
            'catalog'                   => 'Catalog',
            'once_off_service'          => 'Once-off Service',
            default                     => $type,
        };
    }
}

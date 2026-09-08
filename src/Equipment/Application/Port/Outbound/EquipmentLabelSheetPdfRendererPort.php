<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

/**
 * Port EquipmentLabelSheetPdfRendererPort.
 *
 * Renders the printable QR label sheet PDF from a flat, presentation-shaped
 * context array. Each entry of the context's `labels` list carries a
 * `qrValue` — the exact string to encode into that label's QR code — plus
 * the human-readable identification lines; the adapter owns the QR encoding
 * (a vendor concern) and the HTML-to-PDF conversion. Mirrors
 * {@see EquipmentReportPdfRendererPort}; the dompdf adapter lives in
 * `Equipment\Infrastructure\Pdf`.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EquipmentLabelSheetPdfRendererPort
{
  // #region Methods
  /**
   * Method render.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $context the Twig template context
   *
   * @return string the rendered PDF bytes
   */
  public function render(array $context): string;
  // #endregion
}

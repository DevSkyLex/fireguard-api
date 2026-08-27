<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

/**
 * Port EquipmentReportPdfRendererPort.
 *
 * Renders the equipment sheet PDF from a flat, presentation-shaped context
 * array. Mirrors `Intervention\Application\Port\Outbound\InterventionReportPdfRendererPort`;
 * the dompdf adapter lives in `Equipment\Infrastructure\Pdf`.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EquipmentReportPdfRendererPort
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

<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

/**
 * Port InterventionReportPdfRendererPort.
 *
 * Renders a Twig context into the intervention report PDF bytes.
 * Module-local: implemented by
 * `Intervention\Infrastructure\Pdf\DompdfInterventionReportRenderer`. The
 * context is a plain array (mirrors
 * `Compliance\Application\Port\Outbound\SafetyRegisterPdfRendererPort`'s
 * convention, itself mirroring
 * `Notification\Infrastructure\Adapter\Channel\EmailNotificationChannelAdapter`'s
 * Twig context) so the renderer stays framework-agnostic and does not depend
 * on Application use-case Result types.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionReportPdfRendererPort
{
  // #region Methods
  /**
   * Method render.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $context the Twig template context for the intervention report document
   *
   * @return string the rendered PDF bytes
   */
  public function render(array $context): string;
  // #endregion
}

<?php

declare(strict_types=1);

namespace Compliance\Application\Port\Outbound;

/**
 * Port SafetyRegisterPdfRendererPort.
 *
 * Renders a Twig context into the "registre de sécurité" PDF bytes.
 * Module-local: implemented by `Compliance\Infrastructure\Pdf\DompdfSafetyRegisterRenderer`.
 * The context is a plain array (mirrors
 * `Notification\Infrastructure\Adapter\Channel\EmailNotificationChannelAdapter`'s
 * Twig context convention) so the renderer stays framework-agnostic and
 * does not depend on Application use-case Result types.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface SafetyRegisterPdfRendererPort
{
  // #region Methods
  /**
   * Method render.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $context the Twig template context for the safety register document
   *
   * @return string the rendered PDF bytes
   */
  public function render(array $context): string;
  // #endregion
}

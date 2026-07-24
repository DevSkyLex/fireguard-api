<?php

declare(strict_types=1);

namespace Compliance\Infrastructure\Pdf;

use Compliance\Application\Port\Outbound\SafetyRegisterPdfRendererPort;
use Dompdf\{Dompdf, Options};
use Twig\Environment;

/**
 * Adapter DompdfSafetyRegisterRenderer.
 *
 * Renders `templates/compliance/safety_register.html.twig` (mirrors
 * `Notification\Infrastructure\Adapter\Channel\EmailNotificationChannelAdapter`'s
 * Twig usage) and converts the resulting HTML to PDF bytes with dompdf.
 *
 * SSRF hardening: remote resource loading (`isRemoteEnabled`) and inline PHP
 * evaluation (`isPhpEnabled`) are both explicitly disabled — a regulatory
 * document must never fetch attacker-controlled network resources or
 * execute code embedded in the template context.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DompdfSafetyRegisterRenderer implements SafetyRegisterPdfRendererPort
{
  // #region Constants
  private const string TEMPLATE = 'compliance/safety_register.html.twig';

  private const string PAPER_SIZE = 'A4';

  private const string PAPER_ORIENTATION = 'portrait';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param Environment $twig the Twig renderer
   */
  public function __construct(
    private Environment $twig,
  ) {
  }
  // #endregion

  // #region Methods
  public function render(array $context): string
  {
    $html = $this->twig->render(self::TEMPLATE, $context);

    $options = new Options();
    $options->setIsRemoteEnabled(false);
    $options->setIsPhpEnabled(false);
    $options->setIsHtml5ParserEnabled(true);
    $options->setDefaultFont('DejaVu Sans');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper(self::PAPER_SIZE, self::PAPER_ORIENTATION);
    $dompdf->render();

    return (string) $dompdf->output();
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Pdf;

use Dompdf\{Dompdf, Options};
use Intervention\Application\Port\Outbound\InterventionReportPdfRendererPort;
use Twig\Environment;

/**
 * Adapter DompdfInterventionReportRenderer.
 *
 * Renders `templates/intervention/report.html.twig` (mirrors
 * `Compliance\Infrastructure\Pdf\DompdfSafetyRegisterRenderer`'s Twig usage)
 * and converts the resulting HTML to PDF bytes with dompdf.
 *
 * SSRF hardening: remote resource loading (`isRemoteEnabled`) and inline PHP
 * evaluation (`isPhpEnabled`) are both explicitly disabled — a report built
 * from organization-supplied data must never fetch attacker-controlled
 * network resources or execute code embedded in the template context.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DompdfInterventionReportRenderer implements InterventionReportPdfRendererPort
{
  // #region Constants
  private const string TEMPLATE = 'intervention/report.html.twig';

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

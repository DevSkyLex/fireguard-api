<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Pdf;

use Dompdf\{Dompdf, Options};
use Inspection\Application\Port\Outbound\NonConformityReportPdfRendererPort;
use Twig\Environment;

/**
 * Adapter DompdfNonConformityReportRenderer.
 *
 * Renders `templates/inspection/non_conformities_report.html.twig` and
 * converts the resulting HTML to PDF bytes with dompdf — mirrors
 * `Intervention\Infrastructure\Pdf\DompdfInterventionReportRenderer`.
 *
 * SSRF hardening: remote resource loading (`isRemoteEnabled`) and inline PHP
 * evaluation (`isPhpEnabled`) are both explicitly disabled — a generated
 * document must never fetch attacker-controlled network resources or
 * execute code embedded in the template context.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DompdfNonConformityReportRenderer implements NonConformityReportPdfRendererPort
{
  // #region Constants
  private const string TEMPLATE = 'inspection/non_conformities_report.html.twig';

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

    // Page numbering ("X / Y", language-neutral): dompdf's canvas substitutes
    // {PAGE_NUM} and {PAGE_COUNT} in page_text(). This is an adapter-side API
    // call, NOT inline PHP in the document — isPhpEnabled stays off. The CSS
    // counter(pages) alternative renders 0 in dompdf 3.x, so this is the only
    // dompdf-compatible way to print the total page count.
    $canvas = $dompdf->getCanvas();
    $canvas->page_text(
      $canvas->get_width() - 76.0,
      $canvas->get_height() - 46.0,
      '{PAGE_NUM} / {PAGE_COUNT}',
      $dompdf->getFontMetrics()->getFont('DejaVu Sans') ?? '',
      7.0,
      [0.48, 0.55, 0.59],
    );

    return (string) $dompdf->output();
  }
  // #endregion
}

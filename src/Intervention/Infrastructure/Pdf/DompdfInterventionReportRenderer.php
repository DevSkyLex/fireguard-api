<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Pdf;

use Dompdf\{Dompdf, Options};
use Intervention\Application\Port\Outbound\InterventionReportPdfRendererPort;
use Twig\Environment;

/**
 * Class DompdfInterventionReportRenderer
 *
 * Renders intervention report Twig context to PDF bytes with external resources and PHP disabled.
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
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DompdfInterventionReportRenderer implements InterventionReportPdfRendererPort
{
  // #region Constants
  /**
   * Constant TEMPLATE
   *
   * Twig template used to render an intervention report.
   *
   * @access private
   */
  private const string TEMPLATE = 'intervention/report.html.twig';

  /**
   * Constant PAPER_SIZE
   *
   * Page size used for rendered intervention reports.
   *
   * @access private
   */
  private const string PAPER_SIZE = 'A4';

  /**
   * Constant PAPER_ORIENTATION
   *
   * Page orientation used for rendered intervention reports.
   *
   * @access private
   */
  private const string PAPER_ORIENTATION = 'portrait';
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Provides Twig for rendering the report template.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Environment $twig the Twig renderer
   *
   * @return void
   */
  public function __construct(
    private Environment $twig,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method render
   *
   * Renders the report template and returns a PDF document with page numbering.
   *
   * @access public
   *
   * @param array<string, mixed> $context data supplied to the Twig report template
   *
   * @return string rendered PDF bytes
   */
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

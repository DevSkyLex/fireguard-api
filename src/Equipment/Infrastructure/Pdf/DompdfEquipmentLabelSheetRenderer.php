<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Pdf;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Dompdf\{Dompdf, Options};
use Equipment\Application\Port\Outbound\EquipmentLabelSheetPdfRendererPort;
use Twig\Environment;

use function array_map;
use function base64_encode;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Adapter DompdfEquipmentLabelSheetRenderer.
 *
 * Renders `templates/equipment/labels.html.twig` and converts the resulting
 * HTML to PDF bytes with dompdf — mirrors {@see DompdfEquipmentReportRenderer}.
 * Before rendering, each label row's `qrValue` is encoded into a QR code SVG
 * (bacon/bacon-qr-code, pure PHP, no image extension) and injected as
 * `qrDataUri`, a base64 `data:image/svg+xml` URI. The data-URI `<img>` form
 * is deliberate and MEASURED: dompdf 3.1 silently drops inline `<svg>`
 * elements (empty page content stream), while the same SVG through an `<img>`
 * data URI is rendered as vector paths by php-svg-lib. Error correction
 * level M (~15% damage recovery) suits stickers exposed to field wear.
 *
 * SSRF hardening: remote resource loading (`isRemoteEnabled`) and inline PHP
 * evaluation (`isPhpEnabled`) are both explicitly disabled — the QR images
 * are self-contained data URIs, so nothing needs the network.
 *
 * No page numbering: a label sheet is cut apart, a page counter would end up
 * printed across a sticker.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DompdfEquipmentLabelSheetRenderer implements EquipmentLabelSheetPdfRendererPort
{
  // #region Constants
  private const string TEMPLATE = 'equipment/labels.html.twig';

  private const string PAPER_SIZE = 'A4';

  private const string PAPER_ORIENTATION = 'portrait';

  /**
   * Constant QR_SVG_SIZE.
   *
   * Nominal SVG viewport size in px. Purely internal — the template scales
   * the image to the label cell in mm, and the output stays vector.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int QR_SVG_SIZE = 300;
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
    if (isset($context['labels']) && is_array($context['labels'])) {
      $context['labels'] = array_map(
        fn (mixed $label): mixed => is_array($label) && is_string($label['qrValue'] ?? null)
          ? $label + ['qrDataUri' => $this->buildQrDataUri($label['qrValue'])]
          : $label,
        $context['labels'],
      );
    }

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

  /**
   * Method buildQrDataUri.
   *
   * Encodes one QR value into an SVG image and wraps it as a base64
   * `data:image/svg+xml` URI for a dompdf-renderable `<img>` element.
   * Margin 0 — the label template provides the quiet zone as white cell
   * padding, keeping the printed module size as large as possible.
   *
   * @since 1.0.0
   *
   * @param string $value the value to encode
   *
   * @return string the SVG data URI
   */
  private function buildQrDataUri(string $value): string
  {
    $writer = new Writer(new ImageRenderer(
      new RendererStyle(self::QR_SVG_SIZE, 0),
      new SvgImageBackEnd(),
    ));

    $svg = $writer->writeString($value, Encoder::DEFAULT_BYTE_MODE_ECODING, ErrorCorrectionLevel::M());

    return sprintf('data:image/svg+xml;base64,%s', base64_encode($svg));
  }
  // #endregion
}

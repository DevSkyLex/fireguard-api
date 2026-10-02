<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Infrastructure\Pdf;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use DOMElement;
use DOMNode;
use Dompdf\{Canvas, Dompdf, Frame, Options};
use Equipment\Infrastructure\Pdf\DompdfEquipmentLabelSheetRenderer;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

use function array_values;
use function base64_encode;
use function count;
use function dirname;
use function intdiv;
use function preg_match_all;
use function sprintf;
use function str_contains;
use function str_repeat;
use function trim;

/**
 * Test DompdfEquipmentLabelSheetRendererTest
 *
 * The label sheet maps a physical die-cut Avery L7159 grid, so a layout drift of a few
 * millimetres prints across the stickers. dompdf silently ignores several CSS constructs
 * (universal margin reset over `@page`, `table-layout: fixed` widths), so these tests
 * measure the rendered frame positions rather than the template source.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DompdfEquipmentLabelSheetRenderer::class)]
final class DompdfEquipmentLabelSheetRendererTest extends TestCase
{
  // #region Properties
  /**
   * Constant TEMPLATE.
   *
   * The template documenting and implementing the L7159 grid.
   *
   * @access private
   */
  private const string TEMPLATE = 'equipment/labels.html.twig';

  /**
   * Constant LABELS_PER_SHEET.
   *
   * Avery L7159: 3 columns x 8 rows.
   *
   * @access private
   */
  private const int LABELS_PER_SHEET = 24;

  /**
   * Constant TOLERANCE.
   *
   * Allowed deviation in pt (~0.035 mm) between a measured and a documented coordinate;
   * the defects this guards against are 20 pt and more.
   *
   * @access private
   */
  private const float TOLERANCE = 0.1;
  // #endregion

  // #region Methods
  /**
   * Method testLabelsSitOnTheDocumentedL7159Grid
   *
   * Checks the border box of every label against the sheet margins and the 66 mm x 33.9 mm
   * pitch, including the second sheet restarting at the top margin.
   *
   * @access public
   *
   * @param int $count the number of labels rendered
   * @param int $expectedPages the number of sheets expected
   *
   * @return void
   */
  #[Test]
  #[DataProvider('labelCounts')]
  public function testLabelsSitOnTheDocumentedL7159Grid(int $count, int $expectedPages): void
  {
    $rendered = $this->renderFrames($count);

    self::assertSame($expectedPages, $rendered['pages']);
    self::assertCount($count, $rendered['labels']);
    foreach (array_values($rendered['labels']) as $index => $label) {
      $slot = $index % self::LABELS_PER_SHEET;
      $column = $slot % 3;
      $row = intdiv($slot, 3);
      $where = sprintf('label #%d (sheet %d, row %d, column %d)', $index, intdiv($index, self::LABELS_PER_SHEET) + 1, $row, $column);

      self::assertSame(intdiv($index, self::LABELS_PER_SHEET) + 1, $label['page'], $where . ' is on the wrong sheet.');
      self::assertEqualsWithDelta(self::pt(7.25 + $column * 66.0), $label['box']['x'], self::TOLERANCE, $where . ': x must follow the 7.25 mm margin and the 66 mm pitch.');
      self::assertEqualsWithDelta(self::pt(12.9 + $row * 33.9), $label['box']['y'], self::TOLERANCE, $where . ': y must follow the 12.9 mm margin and the 33.9 mm pitch.');
      self::assertEqualsWithDelta(self::pt(63.5), $label['box']['w'], self::TOLERANCE, $where . ': width must be 63.5 mm.');
      self::assertEqualsWithDelta(self::pt(33.9), $label['box']['h'], self::TOLERANCE, $where . ': height must be 33.9 mm.');
    }
    $this->assertQrGeometry($rendered['labels'], $rendered['canvas']);
  }

  /**
   * Method labelCounts
   *
   * Provides a full sheet and a sheet followed by a partial one.
   *
   * @access public
   *
   * @return array<string, array{0: int, 1: int}> the label count and the expected sheet count
   */
  public static function labelCounts(): array
  {
    return [
      ...self::pageCounts(),
      'a full sheet and a partial one' => [30, 2],
    ];
  }

  /**
   * Method testEveryTextLineStaysInsideItsOwnLabelAtTheDocumentedOffset
   *
   * Checks the QR/text split inside a label: text starts after the 27.9 mm QR cell and the
   * 1 mm text padding, and no line crosses the label border (a die-cut sticker clips it).
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testEveryTextLineStaysInsideItsOwnLabelAtTheDocumentedOffset(): void
  {
    $rendered = $this->renderFrames(self::LABELS_PER_SHEET);

    $linesByLabel = [];
    foreach ($rendered['texts'] as $text) {
      self::assertNotNull($text['owner'], 'Text "' . $text['text'] . '" must belong to a label cell.');
      $linesByLabel[$text['owner']][] = $text;
    }
    self::assertCount(self::LABELS_PER_SHEET, $linesByLabel);

    foreach ($rendered['labels'] as $path => $label) {
      $lines = $linesByLabel[$path] ?? [];
      $labelBox = $label['box'];
      $all = '';
      foreach ($lines as $line) {
        $all .= ' ' . $line['text'];
        $box = $line['box'];
        self::assertGreaterThanOrEqual($labelBox['x'] - self::TOLERANCE, $box['x'], '"' . $line['text'] . '" crosses the label left edge.');
        self::assertLessThanOrEqual($labelBox['x'] + $labelBox['w'] + self::TOLERANCE, $box['x'] + $box['w'], '"' . $line['text'] . '" crosses the label right edge.');
        self::assertGreaterThanOrEqual($labelBox['y'] - self::TOLERANCE, $box['y'], '"' . $line['text'] . '" crosses the label top edge.');
        self::assertLessThanOrEqual($labelBox['y'] + $labelBox['h'] + self::TOLERANCE, $box['y'] + $box['h'], '"' . $line['text'] . '" crosses the label bottom edge.');
        if (str_contains($line['text'], 'Extincteur')) {
          self::assertEqualsWithDelta($labelBox['x'] + self::pt(24.0 + 3.0 + 0.9 + 1.0), $box['x'], self::TOLERANCE, 'The label name must start after the QR cell (24 mm image + 3 mm + 0.9 mm padding) and the 1 mm text padding.');
        }
      }
      foreach (['Extincteur', 'Poudre ABC 6 kg', 'N° série SN-', 'Entrepôt Nord', 'Couloir B'] as $expected) {
        self::assertStringContainsString($expected, $all, 'Label text "' . $expected . '" must be rendered inside its label.');
      }
    }
  }

  /**
   * Method testTheRealRendererKeepsOneSheetPerTwentyFourLabels
   *
   * Runs the production adapter: the 8 rows fill the A4 body exactly, so a margin or height
   * drift would push the last row onto an extra page between sheets.
   *
   * @access public
   *
   * @param int $count the number of labels rendered
   * @param int $expectedPages the number of PDF pages expected
   *
   * @return void
   */
  #[Test]
  #[DataProvider('pageCounts')]
  public function testTheRealRendererKeepsOneSheetPerTwentyFourLabels(int $count, int $expectedPages): void
  {
    $pdf = new DompdfEquipmentLabelSheetRenderer($this->twig())->render(['lang' => 'fr', 'labels' => $this->labels($count, true)]);

    self::assertStringStartsWith('%PDF-', $pdf);
    self::assertSame($expectedPages, preg_match_all('#/Type\s*/Page\b#', $pdf), 'The rendered PDF must hold one page per started block of 24 labels.');
  }

  /**
   * Method pageCounts
   *
   * Provides the sheet boundaries, where an off-by-one in the page fit would show.
   *
   * @access public
   *
   * @return array<string, array{0: int, 1: int}> the label count and the expected page count
   */
  public static function pageCounts(): array
  {
    return [
      'one label' => [1, 1],
      'exactly one sheet' => [24, 1],
      'one label over a sheet' => [25, 2],
      'exactly two sheets' => [48, 2],
      'one label over two sheets' => [49, 3],
    ];
  }

  /**
   * Method testMaximumFieldsStayClippedWithoutMovingTheGridOrQr
   *
   * Paints maximal, word-wrapped and unbroken field values with genuine QR images,
   * including the final row where overflow used to create an extra page.
   *
   * @access public
   *
   * @param int $count the number of labels
   * @param bool $lastRowOnly whether only the final row contains maximal values
   * @param int $expectedPages the expected sheet count
   *
   * @return void
   */
  #[Test]
  #[DataProvider('maximumCases')]
  public function testMaximumFieldsStayClippedWithoutMovingTheGridOrQr(int $count, bool $lastRowOnly, int $expectedPages): void
  {
    $rendered = $this->renderFrames($count, true, $lastRowOnly);
    self::assertSame($expectedPages, $rendered['pages']);
    self::assertCount($count, $rendered['labels']);
    foreach (array_values($rendered['labels']) as $index => $label) {
      $slot = $index % self::LABELS_PER_SHEET;
      self::assertSame(intdiv($index, self::LABELS_PER_SHEET) + 1, $label['page']);
      self::assertEqualsWithDelta(self::pt(7.25 + ($slot % 3) * 66.0), $label['box']['x'], self::TOLERANCE);
      self::assertEqualsWithDelta(self::pt(12.9 + intdiv($slot, 3) * 33.9), $label['box']['y'], self::TOLERANCE);
      self::assertEqualsWithDelta(self::pt(63.5), $label['box']['w'], self::TOLERANCE);
      self::assertEqualsWithDelta(self::pt(33.9), $label['box']['h'], self::TOLERANCE);
    }
    $this->assertQrGeometry($rendered['labels'], $rendered['canvas']);

    $clippedLines = 0;
    $paintedOwners = [];
    foreach ($rendered['canvas']->texts as $text) {
      if ('' === trim($text['text'])) {
        continue;
      }
      self::assertNotNull($text['owner']);
      self::assertArrayHasKey($text['owner'], $rendered['labels']);
      $paintedOwners[$text['owner']] = true;
      $label = $rendered['labels'][$text['owner']];
      self::assertSame($label['page'], $text['page']);
      $clip = $text['clip'];
      self::assertNotNull($clip, 'Text painting must use the bounded inner clipping region.');
      self::assertEqualsWithDelta(self::pt(32.6), $clip['w'], self::TOLERANCE);
      self::assertLessThanOrEqual(self::pt(27.9) + self::TOLERANCE, $clip['h']);
      self::assertEqualsWithDelta($label['box']['x'] + self::pt(28.9), $clip['x'], self::TOLERANCE);
      self::assertGreaterThanOrEqual($label['box']['y'] - self::TOLERANCE, $clip['y']);
      self::assertLessThanOrEqual($label['box']['y'] + $label['box']['h'] + self::TOLERANCE, $clip['y'] + $clip['h']);
      self::assertLessThanOrEqual($label['box']['x'] + $label['box']['w'] + self::TOLERANCE, $clip['x'] + $clip['w']);
      if ($text['box']['y'] + $text['box']['h'] > $clip['y'] + $clip['h'] + self::TOLERANCE) {
        ++$clippedLines;
      }
    }
    self::assertCount($count, $paintedOwners);
    self::assertGreaterThan(0, $clippedLines, 'The extreme fixture must exercise actual clipping, rather than merely fitting.');

    $pdf = new DompdfEquipmentLabelSheetRenderer($this->twig())->render(['lang' => 'fr', 'labels' => $this->maximumLabels($count, true, $lastRowOnly)]);
    self::assertStringStartsWith('%PDF-', $pdf);
    self::assertSame($expectedPages, preg_match_all('#/Type\s*/Page\b#', $pdf));
  }

  /**
   * Method maximumCases
   *
   * @access public
   *
   * @return array<string, array{0: int, 1: bool, 2: int}> maximal content and pagination scenarios
   */
  public static function maximumCases(): array
  {
    return [
      'every label has maximal values' => [24, false, 1],
      'only the last full row has maximal values' => [24, true, 1],
      'the final label on a third sheet has maximal values' => [49, true, 3],
    ];
  }

  /**
   * Method assertQrGeometry
   *
   * Verifies real image painting stays at the original 3 mm inset and 24 mm size.
   *
   * @access private
   *
   * @param array<string, array{page: int, box: array{x: float, y: float, w: float, h: float}}> $labels label boxes
   * @param RecordingLabelCanvas $canvas the real painting command recorder
   *
   * @return void
   */
  private function assertQrGeometry(array $labels, RecordingLabelCanvas $canvas): void
  {
    self::assertCount(count($labels), $canvas->images);
    foreach ($canvas->images as $image) {
      self::assertNotNull($image['owner']);
      self::assertArrayHasKey($image['owner'], $labels);
      $label = $labels[$image['owner']];
      self::assertSame($label['page'], $image['page']);
      self::assertEqualsWithDelta($label['box']['x'] + self::pt(3.0), $image['box']['x'], self::TOLERANCE);
      self::assertEqualsWithDelta($label['box']['y'] + self::pt(3.0), $image['box']['y'], self::TOLERANCE);
      self::assertEqualsWithDelta(self::pt(24.0), $image['box']['w'], self::TOLERANCE);
      self::assertEqualsWithDelta(self::pt(24.0), $image['box']['h'], self::TOLERANCE);
    }
  }

  /**
   * Method maximumLabels
   *
   * Uses the allowed serial/subtype (100), facility (120) and location (255)
   * lengths, combining a wide unbroken serial with many wrapped lines.
   *
   * @access private
   *
   * @param int $count the number of labels
   * @param bool $withQrValue whether the production renderer will encode the QR
   * @param bool $lastRowOnly whether only the final row uses maximal content
   *
   * @return list<array<string, string>> label rows
   */
  private function maximumLabels(int $count, bool $withQrValue, bool $lastRowOnly): array
  {
    $labels = $this->labels($count, $withQrValue);
    $first = $lastRowOnly ? intdiv($count - 1, 3) * 3 : 0;
    for ($index = $first; $index < $count; ++$index) {
      $labels[$index]['subType'] = trim(str_repeat('WW ', 33));
      $labels[$index]['serialNumber'] = str_repeat('W', 100);
      $labels[$index]['facilityName'] = trim(str_repeat('WW ', 40));
      $labels[$index]['locationLabel'] = trim(str_repeat('WW ', 85));
    }

    return $labels;
  }

  /**
   * Method renderFrames
   *
   * Renders the real template with the renderer's dompdf options and records, through
   * `end_frame` callbacks, the border box of every label cell and of every text line.
   * The options mirror {@see DompdfEquipmentLabelSheetRenderer::render()}: the production
   * adapter does not expose its Dompdf instance, and the HTML5 parser setting changes how
   * the template is read.
   *
   * @access private
   *
   * @param int $count the number of labels to render
   * @param bool $maximum whether to populate maximal field values
   * @param bool $lastRowOnly whether only the final row uses maximal values
   *
   * @return array{
   *   pages: int,
   *   labels: array<string, array{page: int, box: array{x: float, y: float, w: float, h: float}}>,
   *   texts: list<array{text: string, owner: string|null, box: array{x: float, y: float, w: float, h: float}}>,
   *   canvas: RecordingLabelCanvas
   * } the page count, the label cells keyed by DOM path, and the text lines
   */
  private function renderFrames(int $count, bool $maximum = false, bool $lastRowOnly = false): array
  {
    $rows = $maximum ? $this->maximumLabels($count, false, $lastRowOnly) : $this->labels($count, false);
    $html = $this->twig()->render(self::TEMPLATE, ['lang' => 'fr', 'labels' => $rows]);

    $options = new Options();
    $options->setIsRemoteEnabled(false);
    $options->setIsPhpEnabled(false);
    $options->setIsHtml5ParserEnabled(true);
    $options->setDefaultFont('DejaVu Sans');

    $labels = [];
    $texts = [];
    $dompdf = new Dompdf($options);
    $dompdf->setPaper('A4', 'portrait');
    $canvas = new RecordingLabelCanvas('A4', 'portrait', $dompdf);
    $dompdf->setCanvas($canvas);
    $dompdf->setCallbacks([[
      'event' => 'begin_frame',
      'f' => static function (Frame $frame) use ($canvas): void {
        $node = $frame->get_node();
        $canvas->owner = $node instanceof DOMElement && 'td' === $node->tagName && 'label' === $node->getAttribute('class')
          ? $node->getNodePath()
          : self::owningLabelPath($node);
      },
    ], [
      'event' => 'end_frame',
      'f' => static function (Frame $frame, Canvas $surface) use (&$labels, &$texts, $canvas): void {
        $node = $frame->get_node();
        if ($node instanceof DOMElement && 'td' === $node->tagName && 'label' === $node->getAttribute('class') && $node->getElementsByTagName('img')->length > 0) {
          $labels[(string) $node->getNodePath()] = ['page' => $surface->get_page_number(), 'box' => self::box($frame)];

          return;
        }
        if ($frame->is_text_node() && '' !== trim($node->textContent)) {
          $box = self::box($frame);
          $texts[] = ['text' => trim($node->textContent), 'owner' => self::owningLabelPath($node), 'box' => $canvas->translatedBox($box['x'], $box['y'], $box['w'], $box['h'])];
        }
      },
    ]]);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return ['pages' => $canvas->get_page_count(), 'labels' => $labels, 'texts' => $texts, 'canvas' => $canvas];
  }

  /**
   * Method box
   *
   * Reads the rendered border box of a frame as a typed shape.
   *
   * @access private
   *
   * @param Frame $frame the rendered frame
   *
   * @return array{x: float, y: float, w: float, h: float} the border box in pt
   */
  private static function box(Frame $frame): array
  {
    $box = $frame->get_border_box();

    return ['x' => (float) $box['x'], 'y' => (float) $box['y'], 'w' => (float) $box['w'], 'h' => (float) $box['h']];
  }

  /**
   * Method owningLabelPath
   *
   * Finds the DOM path of the label cell a node sits in.
   *
   * @access private
   *
   * @param DOMNode $node the node to resolve
   *
   * @return string|null the label cell path, or null outside any label
   */
  private static function owningLabelPath(DOMNode $node): ?string
  {
    for ($current = $node->parentNode; null !== $current; $current = $current->parentNode) {
      if ($current instanceof DOMElement && 'td' === $current->tagName && 'label' === $current->getAttribute('class')) {
        return $current->getNodePath();
      }
    }

    return null;
  }

  /**
   * Method labels
   *
   * Builds label rows shaped like the controller context.
   *
   * @access private
   *
   * @param int $count the number of labels
   * @param bool $withQrValue true to leave QR encoding to the adapter, false to inject real QR images
   *
   * @return list<array<string, string>> the label rows
   */
  private function labels(int $count, bool $withQrValue): array
  {
    $writer = new Writer(new ImageRenderer(new RendererStyle(300, 0), new SvgImageBackEnd()));
    $labels = [];
    for ($index = 0; $index < $count; ++$index) {
      $qrValue = '/api/equipment/' . sprintf('018dbf70-9010-7000-8000-%012d', $index);
      $qrDataUri = 'data:image/svg+xml;base64,' . base64_encode($writer->writeString($qrValue, Encoder::DEFAULT_BYTE_MODE_ECODING, ErrorCorrectionLevel::M()));
      $labels[] = [
        ...($withQrValue ? ['qrValue' => $qrValue] : ['qrDataUri' => $qrDataUri]),
        'type' => 'Extincteur',
        'subType' => 'Poudre ABC 6 kg',
        'serialNumber' => sprintf('SN-%06d', $index),
        'facilityName' => 'Entrepôt Nord',
        'locationLabel' => 'Couloir B',
      ];
    }

    return $labels;
  }

  /**
   * Method twig
   *
   * Builds Twig over the real templates and PDF translation catalogues.
   *
   * @access private
   *
   * @return Environment the Twig environment
   */
  private function twig(): Environment
  {
    $root = dirname(__DIR__, 5);
    $translator = new Translator('fr');
    $translator->addLoader('yaml', new YamlFileLoader());
    foreach (['en', 'fr', 'es'] as $language) {
      $translator->addResource('yaml', $root . '/translations/pdf.' . $language . '.yaml', $language, 'pdf');
    }
    $twig = new Environment(new FilesystemLoader($root . '/templates'), ['autoescape' => 'html', 'strict_variables' => true]);
    $twig->addExtension(new TranslationExtension($translator));

    return $twig;
  }

  /**
   * Method pt
   *
   * Converts millimetres to PDF points.
   *
   * @access private
   *
   * @param float $millimetres the length in mm
   *
   * @return float the length in pt
   */
  private static function pt(float $millimetres): float
  {
    return $millimetres / 25.4 * 72.0;
  }
  // #endregion
}

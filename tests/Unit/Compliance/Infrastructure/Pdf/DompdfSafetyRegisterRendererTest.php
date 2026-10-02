<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Infrastructure\Pdf;

use Compliance\Application\Port\Outbound\SafetyRegisterPdfRendererPort;
use Compliance\Infrastructure\Pdf\DompdfSafetyRegisterRenderer;
use DOMElement;
use Dompdf\{Canvas, Dompdf, Frame, Options};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Loader\{ArrayLoader, ChainLoader, FilesystemLoader};

use function dirname;
use function max;
use function min;
use function str_contains;
use function str_repeat;
use function str_starts_with;
use function strlen;
use function trim;

/**
 * Test DompdfSafetyRegisterRendererTest.
 *
 * The safety register is a regulatory document rendered from
 * organization-supplied data, so the SSRF hardening matters: the produced
 * PDF must never be able to pull a remote resource referenced in the
 * template context.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DompdfSafetyRegisterRenderer::class)]
final class DompdfSafetyRegisterRendererTest extends TestCase
{
  private const string TEMPLATE = 'compliance/safety_register.html.twig';

  #[Test]
  public function testItImplementsTheRendererPort(): void
  {
    $renderer = new DompdfSafetyRegisterRenderer($this->twig('<html><body>Registre</body></html>'));

    self::assertInstanceOf(SafetyRegisterPdfRendererPort::class, $renderer);
  }

  #[Test]
  public function testItRendersTheSafetyRegisterTemplateWithTheGivenContext(): void
  {
    /** @var Environment&MockObject $twig */
    $twig = $this->createMock(Environment::class);
    $twig->expects(self::once())
      ->method('render')
      ->with(self::TEMPLATE, ['scope' => 'organization', 'organizationId' => 'org-1'])
      ->willReturn('<html><body><h1>Registre de sécurité</h1></body></html>');

    $pdf = new DompdfSafetyRegisterRenderer($twig)->render(['scope' => 'organization', 'organizationId' => 'org-1']);

    self::assertTrue(str_starts_with($pdf, '%PDF-'), 'The renderer must return PDF bytes.');
    self::assertGreaterThan(0, strlen($pdf));
  }

  #[Test]
  public function testItProducesPdfBytesForAFacilityScopedRegister(): void
  {
    $html = '<html><body><table><tr><td>Bâtiment A</td><td>non_compliant</td></tr></table></body></html>';

    $pdf = new DompdfSafetyRegisterRenderer($this->twig($html))->render(['scope' => 'facility']);

    self::assertTrue(str_starts_with($pdf, '%PDF-'));
  }

  #[Test]
  public function testItDoesNotFetchRemoteResourcesReferencedInTheHtml(): void
  {
    // Remote loading is disabled, so an attacker-controlled image URL in the
    // register data cannot turn PDF generation into an outbound request.
    $html = '<html><body><img src="http://127.0.0.1:9/secret.png" alt="x"></body></html>';

    $pdf = new DompdfSafetyRegisterRenderer($this->twig($html))->render([]);

    self::assertTrue(str_starts_with($pdf, '%PDF-'));
  }

  #[Test]
  public function testATemplateFailureBubblesUp(): void
  {
    $twig = new Environment(new ArrayLoader([]));

    $this->expectException(LoaderError::class);

    new DompdfSafetyRegisterRenderer($twig)->render([]);
  }

  /**
   * Method testTheCommonFooterLabelsAndEscapesTheRegisteredOfficeInAllSupportedLanguages
   *
   * Exercises the real shared template and translation catalogues with untrusted address data.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testTheCommonFooterLabelsAndEscapesTheRegisteredOfficeInAllSupportedLanguages(): void
  {
    $twig = $this->documentTwig();
    foreach (['en' => 'Registered office', 'fr' => 'Siège social', 'es' => 'Domicilio social'] as $language => $label) {
      $html = $twig->render('pdf/layout.html.twig', [
        'lang' => $language,
        'generatedAtFormatted' => '30/09/2026',
        'org' => [
          'name' => 'Acme',
          'registeredAddress' => ['line1' => '<script>alert(1)</script>', 'city' => 'Paris & Lyon', 'countryCode' => 'FR'],
        ],
      ]);

      self::assertStringContainsString($label, $html);
      self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
      self::assertStringContainsString('Paris &amp; Lyon', $html);
      self::assertStringNotContainsString('<script>', $html);
    }
  }

  /**
   * Method testLegacyAndEmptyAddressContextsRenderWithoutARegisteredOfficeLabel
   *
   * Preserves the pre-address rendering contract for absent or empty legal profiles.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testLegacyAndEmptyAddressContextsRenderWithoutARegisteredOfficeLabel(): void
  {
    $twig = $this->documentTwig();
    foreach ([['name' => 'Legacy'], ['name' => 'Empty', 'registeredAddress' => null], ['name' => 'Partial', 'registeredAddress' => ['line1' => null]]] as $org) {
      $html = $twig->render('pdf/layout.html.twig', ['org' => $org, 'lang' => 'fr', 'generatedAtFormatted' => '30/09/2026']);

      self::assertStringNotContainsString('Siège social', $html);
      self::assertStringContainsString('Généré le 30/09/2026', $html);
    }
  }

  /**
   * Method testTheRealRegisterTemplateExplainsTheConfiguredRulesWithoutChangingStatusCodes
   *
   * Verifies explanatory scope while retaining observed non-conformity terminology.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testTheRealRegisterTemplateExplainsTheConfiguredRulesWithoutChangingStatusCodes(): void
  {
    $html = $this->documentTwig()->render(self::TEMPLATE, [
      'org' => ['name' => 'Acme'],
      'lang' => 'fr',
      'generatedAtFormatted' => '30/09/2026',
      'scope' => 'organization',
      'facilityId' => null,
      'organizationStatus' => 'non_compliant',
      'facilities' => [],
      'totals' => [],
    ]);

    self::assertStringContainsString('État du suivi selon les règles configurées dans cette organisation.', $html);
    self::assertStringContainsString('Ce registre ne constitue pas une certification réglementaire.', $html);
    self::assertStringContainsString('status-non_compliant', $html);
    self::assertStringContainsString('Non conforme', $html);
  }

  /**
   * Method testDompdfPlacesTheHeaderRegisteredOfficeAndContentWithinThePage
   *
   * Checks actual rendered text coordinates so a page-margin reset cannot hide legal identity.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testDompdfPlacesTheHeaderRegisteredOfficeAndContentWithinThePage(): void
  {
    $html = $this->documentTwig()->render(self::TEMPLATE, [
      'org' => [
        'name' => 'Acme header identity',
        'legalName' => 'Acme legal identity',
        'registeredAddress' => ['line1' => 'Original registered office', 'city' => 'Paris', 'countryCode' => 'FR'],
      ],
      'lang' => 'fr',
      'generatedAtFormatted' => '30/09/2026',
      'scope' => 'organization',
      'facilityId' => null,
      'facilities' => [],
      'totals' => [],
    ]);
    $options = new Options();
    $options->setIsRemoteEnabled(false);
    $options->setIsPhpEnabled(false);
    $options->setDefaultFont('DejaVu Sans');
    $dompdf = new Dompdf($options);
    $visibleTexts = [];
    $dompdf->setCallbacks([[
      'event' => 'end_frame',
      'f' => static function (Frame $frame) use (&$visibleTexts): void {
        if ($frame->is_text_node()) {
          $position = $frame->get_position();
          self::assertIsArray($position);
          $visibleTexts[] = ['text' => $frame->get_node()->textContent, 'position' => $position];
        }
      },
    ]]);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $found = [];
    foreach ($visibleTexts as $text) {
      foreach (['Acme header identity', 'Acme legal identity', 'Original registered office', 'Registre de sécurité —'] as $marker) {
        if (!str_contains($text['text'], $marker)) {
          continue;
        }
        $found[$marker] = true;
        self::assertGreaterThanOrEqual(30.0, $text['position']['x'], $marker . ' must respect the page inset.');
        self::assertGreaterThanOrEqual(15.0, $text['position']['y'], $marker . ' must not be above the page.');
        self::assertLessThan(825.0, $text['position']['y'], $marker . ' must not be below the page.');
      }
    }
    self::assertCount(4, $found, 'The header, legal identity, office and content must all be rendered.');
  }

  /**
   * Method testMaximumLegalIdentityDoesNotOverlapPaginationOrMultipageContent
   *
   * Measures rendered text boxes with intermediate and maximum field lengths and repeated body content.
   *
   * @access public
   *
   * @param array<string, int> $lengths the field lengths
   * @param int $rows the number of body rows
   * @param int $expectedPages the expected rendered page count
   *
   * @return void
   */
  #[Test]
  #[DataProvider('legalProfileSizes')]
  public function testMaximumLegalIdentityDoesNotOverlapPaginationOrMultipageContent(array $lengths, int $rows, int $expectedPages): void
  {
    $twig = $this->documentTwig();
    $twig->setLoader(new ChainLoader([
      new ArrayLoader(['stress.html.twig' => "{% extends 'pdf/layout.html.twig' %}{% block content %}{% for row in 1..rows %}<div style='height:20px'>Body marker {{ row }}</div>{% endfor %}{% endblock %}"]),
      $twig->getLoader(),
    ]));
    $html = $twig->render('stress.html.twig', [
      'rows' => $rows,
      'org' => [
        'name' => 'Maximum profile',
        'legalName' => str_repeat('W', $lengths['legalName']),
        'registrationNumber' => str_repeat('W', $lengths['registrationNumber']),
        'vatNumber' => str_repeat('W', $lengths['vatNumber']),
        'registeredAddress' => [
          'line1' => str_repeat('W', $lengths['line1']),
          'line2' => str_repeat('W', $lengths['line2']),
          'postalCode' => str_repeat('W', $lengths['postalCode']),
          'city' => str_repeat('W', $lengths['city']),
          'region' => str_repeat('W', $lengths['region']),
          'countryCode' => 'FR',
        ],
      ],
      'lang' => 'fr',
      'generatedAtFormatted' => '30/09/2026',
    ]);
    $options = new Options();
    $options->setIsRemoteEnabled(false);
    $options->setIsPhpEnabled(false);
    $options->setDefaultFont('DejaVu Sans');
    $dompdf = new Dompdf($options);
    $footerTops = [];
    $bodyBottoms = [];
    $dompdf->setCallbacks([[
      'event' => 'end_frame',
      'f' => static function (Frame $frame, Canvas $canvas) use (&$footerTops, &$bodyBottoms): void {
        if (!$frame->is_text_node() || '' === trim($frame->get_node()->textContent)) {
          return;
        }
        $page = $canvas->get_page_number();
        $box = $frame->get_border_box();
        if (str_contains($frame->get_node()->textContent, 'Body marker')) {
          $bodyBottoms[$page] = max($bodyBottoms[$page] ?? 0.0, $box['y'] + $box['h']);
        }
        for ($node = $frame->get_node()->parentNode; null !== $node; $node = $node->parentNode) {
          if (!$node instanceof DOMElement || 'pdf-footer' !== $node->getAttribute('class')) {
            continue;
          }
          self::assertLessThanOrEqual($canvas->get_width() - 84.0, $box['x'] + $box['w'], 'Footer text must remain left of the pagination area.');
          self::assertGreaterThan(0.0, $box['y']);
          self::assertLessThan($canvas->get_height() - 8.0, $box['y'] + $box['h'], 'Footer text must remain inside the page.');
          $footerTops[$page] = min($footerTops[$page] ?? $canvas->get_height(), $box['y']);

          break;
        }
      },
    ]]);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    self::assertSame($expectedPages, $dompdf->getCanvas()->get_page_count());
    self::assertCount($dompdf->getCanvas()->get_page_count(), $footerTops);
    foreach ($bodyBottoms as $page => $bottom) {
      self::assertLessThan($footerTops[$page], $bottom, 'Body content must remain above the legal footer on each page.');
    }
  }

  /**
   * Method legalProfileSizes
   *
   * Includes an intermediate one-page profile and the maximum unbroken multipage profile.
   *
   * @access public
   *
   * @return array<string, array{0: array<string, int>, 1: int, 2: int}> the field lengths, body rows and expected page counts
   */
  public static function legalProfileSizes(): array
  {
    return [
      'intermediate 450 characters' => [['legalName' => 255, 'registrationNumber' => 0, 'vatNumber' => 0, 'line1' => 150, 'line2' => 43, 'postalCode' => 0, 'city' => 0, 'region' => 0], 20, 1],
      'maximum unbroken fields' => [['legalName' => 255, 'registrationNumber' => 64, 'vatNumber' => 64, 'line1' => 255, 'line2' => 255, 'postalCode' => 32, 'city' => 128, 'region' => 128], 100, 3],
    ];
  }

  private function twig(string $html): Environment
  {
    return new Environment(new ArrayLoader([self::TEMPLATE => $html]));
  }

  private function documentTwig(): Environment
  {
    $root = dirname(__DIR__, 5);
    $translator = new Translator('en');
    $translator->addLoader('yaml', new YamlFileLoader());
    foreach (['en', 'fr', 'es'] as $language) {
      $translator->addResource('yaml', $root . '/translations/pdf.' . $language . '.yaml', $language, 'pdf');
    }
    $twig = new Environment(new FilesystemLoader($root . '/templates'), ['autoescape' => 'html', 'strict_variables' => true]);
    $twig->addExtension(new TranslationExtension($translator));

    return $twig;
  }
}

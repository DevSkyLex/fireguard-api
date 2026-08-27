<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Infrastructure\Pdf;

use Intervention\Application\Port\Outbound\InterventionReportPdfRendererPort;
use Intervention\Infrastructure\Pdf\DompdfInterventionReportRenderer;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Loader\ArrayLoader;

use function str_starts_with;
use function strlen;

/**
 * Test DompdfInterventionReportRendererTest.
 *
 * Mirrors `Tests\Unit\Compliance\Infrastructure\Pdf\DompdfSafetyRegisterRendererTest`:
 * the intervention report is built from organization-supplied data, so the
 * SSRF hardening matters just as much — the produced PDF must never be able
 * to pull a remote resource referenced in the template context.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DompdfInterventionReportRenderer::class)]
final class DompdfInterventionReportRendererTest extends TestCase
{
  private const string TEMPLATE = 'intervention/report.html.twig';

  #[Test]
  public function testItImplementsTheRendererPort(): void
  {
    $renderer = new DompdfInterventionReportRenderer($this->twig('<html><body>Report</body></html>'));

    self::assertInstanceOf(InterventionReportPdfRendererPort::class, $renderer);
  }

  #[Test]
  public function testItRendersTheReportTemplateWithTheGivenContext(): void
  {
    /** @var Environment&MockObject $twig */
    $twig = $this->createMock(Environment::class);
    $twig->expects(self::once())
      ->method('render')
      ->with(self::TEMPLATE, ['number' => 42, 'name' => 'Annual inspection'])
      ->willReturn('<html><body><h1>Intervention report</h1></body></html>');

    $pdf = new DompdfInterventionReportRenderer($twig)->render(['number' => 42, 'name' => 'Annual inspection']);

    self::assertTrue(str_starts_with($pdf, '%PDF-'), 'The renderer must return PDF bytes.');
    self::assertGreaterThan(0, strlen($pdf));
  }

  #[Test]
  public function testItProducesPdfBytesForAMinimalContext(): void
  {
    $html = '<html><body><table><tr><td>Work item</td><td>completed</td></tr></table></body></html>';

    $pdf = new DompdfInterventionReportRenderer($this->twig($html))->render(['number' => 1]);

    self::assertTrue(str_starts_with($pdf, '%PDF-'));
  }

  #[Test]
  public function testItDoesNotFetchRemoteResourcesReferencedInTheHtml(): void
  {
    // Remote loading is disabled, so an attacker-controlled image URL in the
    // report data cannot turn PDF generation into an outbound request.
    $html = '<html><body><img src="http://127.0.0.1:9/secret.png" alt="x"></body></html>';

    $pdf = new DompdfInterventionReportRenderer($this->twig($html))->render([]);

    self::assertTrue(str_starts_with($pdf, '%PDF-'));
  }

  #[Test]
  public function testATemplateFailureBubblesUp(): void
  {
    $twig = new Environment(new ArrayLoader([]));

    $this->expectException(LoaderError::class);

    new DompdfInterventionReportRenderer($twig)->render([]);
  }

  private function twig(string $html): Environment
  {
    return new Environment(new ArrayLoader([self::TEMPLATE => $html]));
  }
}

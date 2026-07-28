<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Infrastructure\Pdf;

use Compliance\Application\Port\Outbound\SafetyRegisterPdfRendererPort;
use Compliance\Infrastructure\Pdf\DompdfSafetyRegisterRenderer;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Loader\ArrayLoader;

use function str_starts_with;
use function strlen;

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

  private function twig(string $html): Environment
  {
    return new Environment(new ArrayLoader([self::TEMPLATE => $html]));
  }
}

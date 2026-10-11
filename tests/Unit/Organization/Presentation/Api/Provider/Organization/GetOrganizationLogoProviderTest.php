<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Get;
use Organization\Application\UseCase\Query\Organization\GetOrganizationLogo\{GetOrganizationLogoQuery, GetOrganizationLogoResult};
use Organization\Presentation\Api\Provider\Organization\GetOrganizationLogoProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Test GetOrganizationLogoProviderTest.
 *
 * The logo is served straight from storage to anonymous browsers, so the
 * response headers are the only thing standing between a stored file and a
 * content-sniffing attack: the type must be pinned to image/webp and
 * sniffing explicitly disabled. A missing logo must also be a plain 404
 * rather than a storage error bubbling out.
 *
 * @category Provider Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetOrganizationLogoProvider::class)]
final class GetOrganizationLogoProviderTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655484001';
  // #endregion

  // #region Methods
  #[Test]
  public function testProvideStreamsTheStoredLogoWithHardenedHeaders(): void
  {
    /** @var QueryBusPort&MockObject $fileStorage */
    $fileStorage = $this->createMock(QueryBusPort::class);
    $fileStorage->expects(self::once())
      ->method('ask')
      ->with(new GetOrganizationLogoQuery(self::ORGANIZATION_ID))
      ->willReturn(new GetOrganizationLogoResult('webp-bytes'));

    $response = new GetOrganizationLogoProvider($fileStorage)
      ->provide($this->operation(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertInstanceOf(Response::class, $response);
    self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    self::assertSame('webp-bytes', $response->getContent());
    self::assertSame('image/webp', $response->headers->get('Content-Type'));
    // Symfony re-orders the Cache-Control directives, so assert on both
    // parts rather than a literal header string.
    self::assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
    self::assertStringContainsString('max-age=86400', (string) $response->headers->get('Cache-Control'));
    self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
  }

  #[Test]
  public function testProvideReturnsNotFoundWhenNoLogoIsStored(): void
  {
    $fileStorage = $this->createStub(QueryBusPort::class);
    $fileStorage->method('ask')->willReturn(new GetOrganizationLogoResult(null));

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Organization logo not found.');

    new GetOrganizationLogoProvider($fileStorage)
      ->provide($this->operation(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testANonStringOrganizationIdFallsBackToAnEmptyLookup(): void
  {
    /** @var QueryBusPort&MockObject $fileStorage */
    $fileStorage = $this->createMock(QueryBusPort::class);
    $fileStorage->expects(self::once())
      ->method('ask')
      ->with(new GetOrganizationLogoQuery(''))
      ->willReturn(new GetOrganizationLogoResult('webp-bytes'));

    new GetOrganizationLogoProvider($fileStorage)
      ->provide($this->operation(), ['organizationId' => 42]);
  }

  #[Test]
  public function testAMissingOrganizationIdFallsBackToAnEmptyLookup(): void
  {
    /** @var QueryBusPort&MockObject $fileStorage */
    $fileStorage = $this->createMock(QueryBusPort::class);
    $fileStorage->expects(self::once())
      ->method('ask')
      ->with(new GetOrganizationLogoQuery(''))
      ->willReturn(new GetOrganizationLogoResult('webp-bytes'));

    new GetOrganizationLogoProvider($fileStorage)->provide($this->operation(), []);
  }

  private function operation(): Get
  {
    return new Get();
  }
  // #endregion
}

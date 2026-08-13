<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Query\Publication\GetPublication;

use DateTimeImmutable;
use Intervention\Application\Contract\Publication\{InterventionPublicationContext, PublicationView};
use Intervention\Application\Port\Outbound\PublicationRepositoryPort;
use Intervention\Application\UseCase\Query\Publication\GetPublication\{
  GetPublicationHandler,
  GetPublicationQuery
};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, PublicationNotFoundException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test GetPublicationHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GetPublicationHandlerTest extends TestCase
{
  private const string USER_ID = 'user-1';

  private const string PUBLICATION_ID = 'publication-1';

  private const string INTERVENTION_ID = 'intervention-1';

  private const string ORGANIZATION_ID = 'organization-1';

  private const string PERMISSION = 'organization.interventions.read';

  #[Test]
  public function itReturnsThePublicationWhenFoundAndAuthorized(): void
  {
    $publication = $this->publication();

    $repository = $this->createMock(PublicationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('find')
      ->with(self::PUBLICATION_ID)
      ->willReturn($publication);
    $repository->expects(self::once())
      ->method('interventionContext')
      ->with(self::INTERVENTION_ID)
      ->willReturn($this->context());

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORGANIZATION_ID, self::PERMISSION)
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $handler = new GetPublicationHandler($repository, $authorization);

    $result = $handler(new GetPublicationQuery(self::USER_ID, self::PUBLICATION_ID));

    self::assertSame($publication, $result->publication);
  }

  #[Test]
  public function itThrowsWhenThePublicationDoesNotExist(): void
  {
    $repository = $this->createMock(PublicationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('find')
      ->with(self::PUBLICATION_ID)
      ->willReturn(null);
    $repository->expects(self::never())->method('interventionContext');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    $handler = new GetPublicationHandler($repository, $authorization);

    $this->expectException(PublicationNotFoundException::class);
    $this->expectExceptionMessage('Publication with ID "publication-1" not found.');

    $handler(new GetPublicationQuery(self::USER_ID, self::PUBLICATION_ID));
  }

  #[Test]
  public function itThrowsWhenTheInterventionContextIsMissing(): void
  {
    $repository = $this->createMock(PublicationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('find')
      ->with(self::PUBLICATION_ID)
      ->willReturn($this->publication());
    $repository->expects(self::once())
      ->method('interventionContext')
      ->with(self::INTERVENTION_ID)
      ->willReturn(null);

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::never())->method('resolveAccess');

    $handler = new GetPublicationHandler($repository, $authorization);

    $this->expectException(PublicationNotFoundException::class);
    $this->expectExceptionMessage('Publication with ID "publication-1" not found.');

    $handler(new GetPublicationQuery(self::USER_ID, self::PUBLICATION_ID));
  }

  #[Test]
  public function itThrowsWhenTheUserLacksTheReadPermission(): void
  {
    $repository = $this->createStub(PublicationRepositoryPort::class);
    $repository->method('find')->willReturn($this->publication());
    $repository->method('interventionContext')->willReturn($this->context());

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORGANIZATION_ID, self::PERMISSION)
      ->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $handler = new GetPublicationHandler($repository, $authorization);

    $this->expectException(InterventionAccessDeniedException::class);
    $this->expectExceptionMessage('Missing organization.interventions.read permission.');

    $handler(new GetPublicationQuery(self::USER_ID, self::PUBLICATION_ID));
  }

  #[Test]
  public function itThrowsNotFoundWhenTheCallerIsOutsideTheOwningOrganization(): void
  {
    $repository = $this->createStub(PublicationRepositoryPort::class);
    $repository->method('find')->willReturn($this->publication());
    $repository->method('interventionContext')->willReturn($this->context());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $handler = new GetPublicationHandler($repository, $authorization);

    // Byte-for-byte the response `itThrowsWhenThePublicationDoesNotExist`
    // asserts: a caller from another organization must not be able to tell a
    // real publication id from an imaginary one.
    $this->expectException(PublicationNotFoundException::class);
    $this->expectExceptionMessage('Publication with ID "publication-1" not found.');

    $handler(new GetPublicationQuery(self::USER_ID, self::PUBLICATION_ID));
  }

  private function publication(): PublicationView
  {
    return new PublicationView(
      self::PUBLICATION_ID,
      self::INTERVENTION_ID,
      42,
      'completed',
      null,
      new DateTimeImmutable(),
      new DateTimeImmutable(),
    );
  }

  private function context(): InterventionPublicationContext
  {
    return new InterventionPublicationContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'submitted', 42);
  }
}

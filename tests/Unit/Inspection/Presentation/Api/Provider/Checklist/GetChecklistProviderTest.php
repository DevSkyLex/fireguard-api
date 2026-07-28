<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Provider\Checklist;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\UseCase\Query\Checklist\GetChecklist\{ChecklistItemResult, GetChecklistQuery, GetChecklistResult};
use Inspection\Domain\Exception\ChecklistNotFoundException;
use Inspection\Presentation\Api\Provider\Checklist\GetChecklistProvider;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;

/**
 * Test GetChecklistProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetChecklistProvider::class)]
final class GetChecklistProviderTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440601';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440602';

  private const string CHECKLIST_ID = '550e8400-e29b-41d4-a716-446655440603';

  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new GetChecklistProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), $this->uriVariables());
  }

  #[Test]
  public function testProvideThrowsWhenTheUriVariablesAreIncomplete(): void
  {
    $provider = new GetChecklistProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $this->securityWithUser(),
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new Get(), ['organizationId' => self::ORG_ID]);
  }

  #[Test]
  public function testProvideThrowsWhenThePermissionIsMissing(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $provider = new GetChecklistProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      security: $this->securityWithUser(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), $this->uriVariables());
  }

  #[Test]
  public function testProvideMapsTheResultOntoTheOutput(): void
  {
    $now = new DateTimeImmutable('2026-01-15T10:00:00+00:00');

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetChecklistQuery $query): bool => self::ORG_ID === $query->organizationId
        && self::CHECKLIST_ID === $query->checklistId))
      ->willReturn(new GetChecklistResult(
        checklistId: self::CHECKLIST_ID,
        organizationId: self::ORG_ID,
        name: 'Annual Safety Checklist',
        version: '1.0',
        status: 'active',
        items: [new ChecklistItemResult('item-1', 'Check pressure gauge', 1, true, 'Between 12 and 15 bar')],
        createdAt: $now,
        updatedAt: $now,
        referenceCode: 'CHK-001',
      ));

    $provider = new GetChecklistProvider(
      queryBus: $queryBus,
      authorization: $this->grantingAuthorization(),
      security: $this->securityWithUser(),
    );

    $output = $provider->provide(new Get(), $this->uriVariables());

    self::assertSame(self::CHECKLIST_ID, $output->id);
    self::assertSame(self::ORG_ID, $output->organizationId);
    self::assertSame('Annual Safety Checklist', $output->name);
    self::assertSame('CHK-001', $output->referenceCode);
    self::assertSame('1.0', $output->version);
    self::assertSame('active', $output->status);
    self::assertSame('2026-01-15T10:00:00+00:00', $output->createdAt);
    self::assertSame('2026-01-15T10:00:00+00:00', $output->updatedAt);
    self::assertSame(1, $output->itemCount);
    self::assertCount(1, $output->items);
    self::assertSame('item-1', $output->items[0]->id);
    self::assertSame('Check pressure gauge', $output->items[0]->label);
    self::assertSame(1, $output->items[0]->position);
    self::assertTrue($output->items[0]->required);
    self::assertSame('Between 12 and 15 bar', $output->items[0]->description);
  }

  #[Test]
  public function testProvideMapsAMissingChecklistToNotFound(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->provideWithFailure(ChecklistNotFoundException::withId(self::CHECKLIST_ID));
  }

  #[Test]
  public function testProvideMapsAnInvalidArgumentToBadRequest(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->provideWithFailure(new InvalidArgumentException('Invalid checklist id.'));
  }

  #[Test]
  public function testProvideUnwrapsAMissingChecklistFromAMessengerFailure(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->provideWithFailure(MessengerRuntimeException::wrap(ChecklistNotFoundException::withId(self::CHECKLIST_ID)));
  }

  #[Test]
  public function testProvideUnwrapsAnInvalidArgumentFromAMessengerFailure(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->provideWithFailure(MessengerRuntimeException::wrap(new InvalidArgumentException('Invalid checklist id.')));
  }

  #[Test]
  public function testProvideRethrowsAnUnrelatedMessengerFailure(): void
  {
    $this->expectException(MessengerRuntimeException::class);

    $this->provideWithFailure(MessengerRuntimeException::wrap(new RuntimeException('database is down')));
  }

  private function provideWithFailure(Throwable $failure): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException($failure);

    $provider = new GetChecklistProvider(
      queryBus: $queryBus,
      authorization: $this->grantingAuthorization(),
      security: $this->securityWithUser(),
    );

    $provider->provide(new Get(), $this->uriVariables());
  }

  /**
   * @return array<string, string>
   */
  private function uriVariables(): array
  {
    return ['organizationId' => self::ORG_ID, 'checklistId' => self::CHECKLIST_ID];
  }

  private function grantingAuthorization(): OrganizationAuthorizationPort
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    return $authorization;
  }

  private function securityWithUser(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }
}

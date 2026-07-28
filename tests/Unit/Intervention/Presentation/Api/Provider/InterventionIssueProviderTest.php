<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Provider;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\Contract\Resource\InterventionIssue;
use Intervention\Application\UseCase\Query\Workflow\ListInterventionIssues\{ListInterventionIssuesQuery, ListInterventionIssuesResult};
use Intervention\Domain\Exception\InterventionNotFoundException;
use Intervention\Presentation\Api\Provider\InterventionIssueProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

/**
 * Test InterventionIssueProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionIssueProvider::class)]
final class InterventionIssueProviderTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655441500';

  // #region Methods
  #[Test]
  public function testProvideMapsIssuesAndRewritesTheInterventionResourceType(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListInterventionIssuesQuery $query): bool => self::USER_ID === $query->userId
        && self::INTERVENTION_ID === $query->interventionId))
      ->willReturn(new ListInterventionIssuesResult([
        new InterventionIssue('blocker', 'intervention', self::INTERVENTION_ID, null, 'No work items.'),
        new InterventionIssue('warning', 'facilities', '550e8400-e29b-41d4-a716-446655441501', 'name', 'Missing name.'),
      ]));

    $outputs = new InterventionIssueProvider($queryBus, $this->security())
      ->provide(new GetCollection(), ['id' => self::INTERVENTION_ID]);

    self::assertCount(2, $outputs);
    self::assertSame('blocker', $outputs[0]->severity);
    self::assertSame('/api/interventions/' . self::INTERVENTION_ID, $outputs[0]->resource);
    self::assertNull($outputs[0]->field);
    self::assertSame('No work items.', $outputs[0]->message);
    self::assertSame('/api/facilities/550e8400-e29b-41d4-a716-446655441501', $outputs[1]->resource);
    self::assertSame('name', $outputs[1]->field);
  }

  #[Test]
  public function testProvideRejectsAMissingInterventionId(): void
  {
    $provider = new InterventionIssueProvider($this->createStub(QueryBusPort::class), $this->security());

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideRequiresAnAuthenticatedUser(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new InterventionIssueProvider($this->createStub(QueryBusPort::class), $security);

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['id' => self::INTERVENTION_ID]);
  }

  #[Test]
  public function testProvideMapsADomainFailureToItsHttpEquivalent(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(InterventionNotFoundException::withId(self::INTERVENTION_ID));

    $provider = new InterventionIssueProvider($queryBus, $this->security());

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new GetCollection(), ['id' => self::INTERVENTION_ID]);
  }

  private function security(): Security
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
  // #endregion
}

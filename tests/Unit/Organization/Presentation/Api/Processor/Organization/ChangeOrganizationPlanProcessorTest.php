<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan\ChangeOrganizationPlanResult;
use Organization\Application\UseCase\Command\Organization\ChangeSelfServiceOrganizationPlan\ChangeSelfServiceOrganizationPlanCommand;
use Organization\Application\UseCase\Query\Organization\GetOrganization\{GetOrganizationQuery, GetOrganizationResult};
use Organization\Presentation\Api\Dto\Input\Organization\ChangeOrganizationPlanInput;
use Organization\Presentation\Api\Processor\Organization\ChangeOrganizationPlanProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

#[CoversClass(ChangeOrganizationPlanProcessor::class)]
final class ChangeOrganizationPlanProcessorTest extends TestCase
{
  private const string ORGANIZATION = '550e8400-e29b-41d4-a716-446655440010';

  private const string USER = '550e8400-e29b-41d4-a716-446655440001';

  private const string PLAN = '22222222-2222-4222-8222-222222222222';

  #[Test]
  public function carriesTheAuthenticatedActorAndAcknowledgementToSelfService(): void
  {
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::once())->method('dispatch')->with(new ChangeSelfServiceOrganizationPlanCommand(self::ORGANIZATION, self::PLAN, self::USER, true))->willReturn(new ChangeOrganizationPlanResult(self::ORGANIZATION, self::PLAN));
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->with(new GetOrganizationQuery(self::ORGANIZATION))->willReturn(new GetOrganizationResult(self::ORGANIZATION, 'Example', 'example', self::USER, self::USER, 'active', true, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-02'), planId: self::PLAN, planName: 'Free'));
    $security = $this->security();
    $input = new ChangeOrganizationPlanInput();
    $input->planId = self::PLAN;
    $input->acknowledgeOveruse = true;
    $output = new ChangeOrganizationPlanProcessor($commands, $queries, $security)->process($input, new Patch(), ['id' => self::ORGANIZATION]);
    self::assertSame(self::ORGANIZATION, $output->id);
    self::assertSame(self::PLAN, $output->planId);
    self::assertSame('Free', $output->planName);
    self::assertSame('Example', $output->name);
    self::assertSame('2026-01-01T00:00:00+00:00', $output->createdAt);
  }

  #[Test]
  public function refusesAnUnauthenticatedRequest(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $this->expectException(AccessDeniedHttpException::class);
    new ChangeOrganizationPlanProcessor($commands, $this->createStub(QueryBusPort::class), $security)->process(new ChangeOrganizationPlanInput(), new Patch(), ['id' => self::ORGANIZATION]);
  }

  #[Test]
  public function refusesAMissingOrganizationIdentifier(): void
  {
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $this->expectException(BadRequestHttpException::class);
    new ChangeOrganizationPlanProcessor($commands, $this->createStub(QueryBusPort::class), $this->security())->process(new ChangeOrganizationPlanInput(), new Patch());
  }

  #[Test]
  public function refusesANonStringOrganizationIdentifier(): void
  {
    $this->expectException(BadRequestHttpException::class);
    new ChangeOrganizationPlanProcessor($this->createStub(CommandBusPort::class), $this->createStub(QueryBusPort::class), $this->security())->process(new ChangeOrganizationPlanInput(), new Patch(), ['id' => 42]);
  }

  private function security(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(self::USER, 'user@example.com', 'hashed-password', ['ROLE_USER'], [], true));

    return $security;
  }
}

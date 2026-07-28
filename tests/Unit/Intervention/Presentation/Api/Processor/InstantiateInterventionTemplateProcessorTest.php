<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\UseCase\Command\Template\InstantiateInterventionTemplate\{
  InstantiateInterventionTemplateCommand,
  InstantiateInterventionTemplateResult
};
use Intervention\Domain\Exception\InterventionNotFoundException;
use Intervention\Presentation\Api\Dto\Input\InstantiateInterventionTemplateInput;
use Intervention\Presentation\Api\Processor\InstantiateInterventionTemplateProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

/**
 * Test InstantiateInterventionTemplateProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InstantiateInterventionTemplateProcessor::class)]
final class InstantiateInterventionTemplateProcessorTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string TEMPLATE_ID = '550e8400-e29b-41d4-a716-446655441700';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655441500';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655441501';

  private const string SITE_ID = '550e8400-e29b-41d4-a716-446655441502';

  // #region Methods
  #[Test]
  public function testProcessTranslatesTheOverridesAndReturnsTheCreatedIntervention(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (InstantiateInterventionTemplateCommand $command): bool {
        self::assertSame(self::USER_ID, $command->userId);
        self::assertSame(self::TEMPLATE_ID, $command->templateId);
        self::assertSame('Spring campaign', $command->name);
        self::assertSame(self::SITE_ID, $command->siteId);
        self::assertSame(self::MEMBER_ID, $command->responsibleId);
        self::assertSame('2026-03-01T09:00:00+00:00', $command->plannedStartAt?->format('c'));

        return true;
      }))
      ->willReturn(new InstantiateInterventionTemplateResult(self::INTERVENTION_ID, 12));

    $input = new InstantiateInterventionTemplateInput();
    $input->name = 'Spring campaign';
    $input->site = '/api/facilities/' . self::SITE_ID;
    $input->responsible = '/api/organizations/' . self::ORG_ID . '/members/' . self::MEMBER_ID;
    $input->plannedStartAt = '2026-03-01T09:00:00+00:00';

    $output = $this->processor($commandBus)->process($input, new Post(), ['id' => self::TEMPLATE_ID]);

    self::assertSame(self::INTERVENTION_ID, $output->interventionId);
    self::assertSame(12, $output->number);
  }

  #[Test]
  public function testProcessFallsBackToAnEmptyInputWhenNoBodyWasSent(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (InstantiateInterventionTemplateCommand $command): bool {
        self::assertNull($command->name);
        self::assertNull($command->siteId);
        self::assertNull($command->responsibleId);
        self::assertNull($command->plannedStartAt);

        return true;
      }))
      ->willReturn(new InstantiateInterventionTemplateResult(self::INTERVENTION_ID, 1));

    $output = $this->processor($commandBus)->process(null, new Post(), ['id' => self::TEMPLATE_ID]);

    self::assertSame(1, $output->number);
  }

  #[Test]
  public function testProcessRequiresTheTemplateId(): void
  {
    $processor = $this->processor($this->createStub(CommandBusPort::class));

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Post(), []);
  }

  #[Test]
  public function testProcessRequiresAnAuthenticatedUser(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new InstantiateInterventionTemplateProcessor($this->createStub(CommandBusPort::class), $security);

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post(), ['id' => self::TEMPLATE_ID]);
  }

  #[Test]
  public function testProcessMapsADomainFailureToItsHttpEquivalent(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(InterventionNotFoundException::withId(self::TEMPLATE_ID));

    $this->expectException(NotFoundHttpException::class);

    $this->processor($commandBus)->process(null, new Post(), ['id' => self::TEMPLATE_ID]);
  }

  private function processor(CommandBusPort $commandBus): InstantiateInterventionTemplateProcessor
  {
    return new InstantiateInterventionTemplateProcessor($commandBus, $this->security());
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

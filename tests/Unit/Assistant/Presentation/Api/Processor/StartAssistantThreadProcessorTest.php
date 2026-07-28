<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use Assistant\Application\Contract\Thread\AssistantThreadView;
use Assistant\Application\UseCase\Command\Thread\StartAssistantThread\{StartAssistantThreadCommand, StartAssistantThreadResult};
use Assistant\Domain\Exception\AssistantValidationException;
use Assistant\Presentation\Api\Dto\Input\StartAssistantThreadInput;
use Assistant\Presentation\Api\Dto\Output\AssistantThreadOutput;
use Assistant\Presentation\Api\Factory\AssistantThreadOutputFactory;
use Assistant\Presentation\Api\Processor\StartAssistantThreadProcessor;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, UnprocessableEntityHttpException};

/**
 * Test StartAssistantThreadProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(StartAssistantThreadProcessor::class)]
final class StartAssistantThreadProcessorTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string USER_ID = 'user-id';

  #[Test]
  public function testProcessDispatchesTheCommandAndReturnsTheThreadOutput(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $input = new StartAssistantThreadInput();
    $input->title = 'Fire safety questions';

    $captured = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(function (StartAssistantThreadCommand $command) use (&$captured): StartAssistantThreadResult {
        $captured = $command;

        return new StartAssistantThreadResult(new AssistantThreadView(
          id: 'thread-1',
          organizationId: self::ORGANIZATION_ID,
          memberId: self::USER_ID,
          title: 'Fire safety questions',
          model: null,
          createdAt: new DateTimeImmutable('2026-07-18T00:00:00+00:00'),
          updatedAt: new DateTimeImmutable('2026-07-18T00:00:00+00:00'),
          lastMessageAt: null,
        ));
      });

    $processor = new StartAssistantThreadProcessor($commandBus, $security, new AssistantThreadOutputFactory());

    $output = $processor->process($input, new Post(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertInstanceOf(StartAssistantThreadCommand::class, $captured);
    self::assertSame(self::USER_ID, $captured->actorUserId);
    self::assertSame(self::ORGANIZATION_ID, $captured->organizationId);
    self::assertSame('Fire safety questions', $captured->title);

    self::assertInstanceOf(AssistantThreadOutput::class, $output);
    self::assertSame('thread-1', $output->id);
    self::assertNull($output->model);
  }

  #[Test]
  public function testProcessRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new StartAssistantThreadProcessor(
      $this->createStub(CommandBusPort::class),
      $security,
      new AssistantThreadOutputFactory(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new StartAssistantThreadInput(), new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessRequiresAnOrganizationIdUriVariable(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $processor = new StartAssistantThreadProcessor(
      $this->createStub(CommandBusPort::class),
      $security,
      new AssistantThreadOutputFactory(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(new StartAssistantThreadInput(), new Post(), []);
  }

  #[Test]
  public function testProcessMapsADomainFailureToAnHttpException(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      AssistantValidationException::modelNotAllowed('gpt-4'),
    );

    $processor = new StartAssistantThreadProcessor($commandBus, $security, new AssistantThreadOutputFactory());

    $this->expectException(UnprocessableEntityHttpException::class);
    $this->expectExceptionMessage('The model "gpt-4" is not permitted by the configured allowlist.');

    $processor->process(new StartAssistantThreadInput(), new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }
}

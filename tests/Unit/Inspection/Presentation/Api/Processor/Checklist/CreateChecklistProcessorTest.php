<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Processor\Checklist;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\UseCase\Command\Checklist\CreateChecklist\{CreateChecklistCommand, CreateChecklistResult};
use Inspection\Presentation\Api\Dto\Input\Checklist\{ChecklistItemInput, CreateChecklistInput};
use Inspection\Presentation\Api\Dto\Output\Checklist\ChecklistOutput;
use Inspection\Presentation\Api\Processor\Checklist\CreateChecklistProcessor;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function count;

#[CoversClass(CreateChecklistProcessor::class)]
final class CreateChecklistProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string CHECKLIST_ID = '550e8400-e29b-41d4-a716-446655440030';

  private const string ITEM_ID = '550e8400-e29b-41d4-a716-446655440031';

  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new CreateChecklistProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      authorization: $this->createMock(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: $this->makeInput(),
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProcessThrowsWhenOrganizationIdMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $processor = new CreateChecklistProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      authorization: $this->createMock(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(
      data: $this->makeInput(),
      operation: new Post(),
      uriVariables: [],
    );
  }

  #[Test]
  public function testProcessThrowsWhenPermissionDenied(): void
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $processor = new CreateChecklistProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: $this->makeInput(),
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProcessDispatchesCommandAndReturnsOutput(): void
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $now = new DateTimeImmutable('2026-01-15T10:00:00+00:00');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (CreateChecklistCommand $command): bool {
        return self::ORG_ID === $command->organizationId
          && 'Contrôle Extincteur' === $command->name
          && '1.0' === $command->version
          && 1 === count($command->items)
          && 'Vérifier la pression' === $command->items[0]['label']
          && true === ($command->items[0]['required'] ?? false)
          && 0 === ($command->items[0]['position'] ?? -1);
      }))
      ->willReturn(new CreateChecklistResult(
        checklistId: self::CHECKLIST_ID,
        organizationId: self::ORG_ID,
        name: 'Contrôle Extincteur',
        version: '1.0',
        status: 'active',
        items: [
          ['id' => self::ITEM_ID, 'label' => 'Vérifier la pression', 'description' => null, 'required' => true, 'position' => 0],
        ],
        createdAt: $now,
        updatedAt: $now,
      ));

    $processor = new CreateChecklistProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(
      data: $this->makeInput(),
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );

    self::assertInstanceOf(ChecklistOutput::class, $output);
    self::assertSame(self::CHECKLIST_ID, $output->id);
    self::assertSame(self::ORG_ID, $output->organizationId);
    self::assertSame('Contrôle Extincteur', $output->name);
    self::assertSame('1.0', $output->version);
    self::assertSame('active', $output->status);
    self::assertCount(1, $output->items);
    self::assertSame(self::ITEM_ID, $output->items[0]->id);
    self::assertSame('Vérifier la pression', $output->items[0]->label);
  }

  #[Test]
  public function testProcessThrowsBadRequestOnInvalidArgument(): void
  {
    $processor = $this->makeAuthorizedProcessor(
      commandException: new InvalidArgumentException('Name too long.'),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(
      data: $this->makeInput(),
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProcessUnwrapsBadRequestFromMessengerException(): void
  {
    $processor = $this->makeAuthorizedProcessor(
      commandException: MessengerRuntimeException::wrap(
        new InvalidArgumentException('Name too long.'),
      ),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(
      data: $this->makeInput(),
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  private function makeAuthorizedProcessor(Throwable $commandException): CreateChecklistProcessor
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($commandException);

    return new CreateChecklistProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );
  }

  private function makeInput(): CreateChecklistInput
  {
    $item = new ChecklistItemInput();
    $item->label = 'Vérifier la pression';
    $item->description = null;
    $item->required = true;
    $item->position = 0;

    $input = new CreateChecklistInput();
    $input->name = 'Contrôle Extincteur';
    $input->version = '1.0';
    $input->items = [$item];

    return $input;
  }

  private function createSecurityUser(): SecurityUser
  {
    return new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}

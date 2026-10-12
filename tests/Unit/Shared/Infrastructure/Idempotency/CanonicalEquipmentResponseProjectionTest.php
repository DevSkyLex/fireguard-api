<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Idempotency;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Equipment\Application\Contract\Equipment\CanonicalEquipmentView;
use Equipment\Application\UseCase\Command\Equipment\PatchCanonicalEquipment\{PatchCanonicalEquipmentCommand, PatchCanonicalEquipmentResult};
use Equipment\Application\UseCase\Query\Equipment\GetCanonicalEquipment\{GetCanonicalEquipmentQuery, GetCanonicalEquipmentResult};
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult;
use Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail\{GetEquipmentDetailQuery, GetEquipmentDetailResult};
use Equipment\Infrastructure\Adapter\Idempotency\ResponseProjection\EquipmentHttpIdempotencyResponseProjectionAdapter;
use Equipment\Presentation\Api\Dto\Input\Equipment\PatchCanonicalEquipmentInput;
use Equipment\Presentation\Api\Factory\EquipmentOutputFactory;
use Equipment\Presentation\Api\Http\{MergePatchFields, RevisionGuard};
use Equipment\Presentation\Api\Processor\Equipment\CanonicalEquipmentMutationProcessor;
use Equipment\Presentation\Api\Provider\Equipment\CanonicalEquipmentProvider;
use Equipment\Presentation\Api\Service\EquipmentDetailReader;
use Intervention\Application\Port\Inbound\InterventionResourcesPort;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\{HttpIdempotencyOperation, HttpIdempotencyQueryProjection};
use Shared\Application\Contract\Idempotency\{IdempotencyBegin, IdempotencyLease, IdempotencySnapshot};
use Shared\Application\Message\{CommandMessage, QueryMessage, ResultMessage};
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyPort;
use Shared\Infrastructure\Http\RevisionEtagSubscriber;
use Shared\Infrastructure\Idempotency\{HmacIdempotencyFingerprintAdapter, HttpIdempotencyExecutionContext, HttpIdempotencyResponseProjectionCoordinator, HttpIdempotencyResultCodec};
use Shared\Infrastructure\Idempotency\ResponseProjection\HttpIdempotencyResponseProjectionCodec;
use Shared\Infrastructure\Symfony\Adapter\Inbound\MessengerQueryBusAdapter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, RequestStack};
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\{Envelope, MessageBusInterface};
use Symfony\Component\Messenger\Stamp\HandledStamp;

use function base64_encode;
use function str_repeat;

/**
 * Test CanonicalEquipmentResponseProjectionTest.
 *
 * Executes the real canonical processor and provider around a before-commit application receipt.
 *
 * @category Unit Tests
 */
#[CoversClass(HttpIdempotencyResponseProjectionCoordinator::class)]
#[CoversClass(HttpIdempotencyExecutionContext::class)]
#[CoversClass(CanonicalEquipmentMutationProcessor::class)]
#[CoversClass(CanonicalEquipmentProvider::class)]
#[CoversClass(RevisionEtagSubscriber::class)]
final class CanonicalEquipmentResponseProjectionTest extends TestCase
{
  #[Test]
  public function itReplaysTheOriginalPatchOutputAndEtagWithoutASecondWriteOrFalseRevisionFailure(): void
  {
    $revision = 2;
    $effects = 0;
    $receipt = null;
    $stack = new RequestStack();
    $request = new Request([], [], [], [], [], ['REQUEST_METHOD' => 'PATCH'], '{"name":"original"}');
    $request->headers->set('If-Match', '"revision-2"');
    $stack->push($request);
    $readBus = $this->createMock(MessageBusInterface::class);
    $readBus->expects(self::exactly(3))->method('dispatch')->willReturnCallback(static function (QueryMessage $query) use (&$revision): Envelope {
      $canonical = new CanonicalEquipmentView('equipment', 'organization', 'published', null, $revision);
      $result = $query instanceof GetCanonicalEquipmentQuery
        ? new GetCanonicalEquipmentResult($canonical)
        : new GetEquipmentDetailResult(new GetEquipmentResult(
          equipmentId: 'equipment',
          organizationId: 'organization',
          facilityId: null,
          type: 'extinguisher',
          subType: null,
          brand: null,
          model: null,
          serialNumber: null,
          locationLabel: null,
          status: 'operational',
          installedAt: null,
          commissionedAt: null,
          tags: [['id' => 'tag', 'name' => 'Saved tag', 'organizationId' => 'organization']],
          createdAt: new DateTimeImmutable('2030-01-01'),
          updatedAt: new DateTimeImmutable('2030-01-02'),
          name: 'revision-' . $revision,
        ), $canonical);

      return new Envelope($query, [new HandledStamp($result, 'equipment-query')]);
    });
    $projection = new HttpIdempotencyResponseProjectionCoordinator($stack, new HttpIdempotencyResponseProjectionCodec(), $readBus, [new EquipmentHttpIdempotencyResponseProjectionAdapter()]);
    $queries = new MessengerQueryBusAdapter($readBus, $projection);
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::once())->method('beginTransaction');
    $connection->expects(self::once())->method('commit')->willReturnCallback(static function () use (&$revision): void { $revision = 4; });
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::once())->method('claimWithinTransaction');
    $engine->expects(self::once())->method('beforeCommit')->willReturnCallback(static function (IdempotencyLease $lease, IdempotencySnapshot $snapshot) use (&$receipt, &$revision): void {
      self::assertSame(3, $revision);
      $receipt = $snapshot;
    });
    $context = new HttpIdempotencyExecutionContext($stack, new HttpIdempotencyResultCodec(), new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('x', 32))), $connection, $this->createStub(Connection::class), responseProjections: $projection);
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::exactly(2))->method('dispatch')->willReturnCallback(static function (CommandMessage $command) use ($context, &$revision, &$effects): ResultMessage {
      self::assertInstanceOf(PatchCanonicalEquipmentCommand::class, $command);
      self::assertSame(2, $command->expectedRevision);

      return $context->dispatchPrimary($command, static function () use (&$revision, &$effects): ResultMessage {
        ++$effects;
        $revision = 3;

        return new PatchCanonicalEquipmentResult('equipment', 'operational', 3);
      });
    });
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser('actor', 'actor@example.test', 'unused'));
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
    $provider = new CanonicalEquipmentProvider($queries, $security, $stack, new EquipmentDetailReader($queries, new EquipmentOutputFactory()));
    $processor = new CanonicalEquipmentMutationProcessor($commands, $queries, $authorization, $security, $stack, $provider, $this->createStub(InterventionResourcesPort::class), new RevisionGuard($stack), new MergePatchFields($stack));
    $operation = self::operation();
    $context->activate($operation, $engine, new IdempotencyBegin(lease: new IdempotencyLease('scope', 'key', 'input', 'fence', new DateTimeImmutable('2031-01-01'))), ['organizationId' => 'organization']);
    $input = new PatchCanonicalEquipmentInput();
    $input->name = 'original';
    $original = $processor->process($input, new Patch(), ['id' => 'equipment']);
    self::assertInstanceOf(\Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput::class, $original);
    self::assertSame(3, $original->revision);
    self::assertSame('revision-3', $original->name);
    self::assertSame(4, $revision);
    self::assertInstanceOf(IdempotencySnapshot::class, $receipt);
    self::assertSame(200, $receipt->statusCode);
    $context->deactivate();

    // The HTTP boundary independently verifies current organization and read permission before activation.
    $context->activate($operation, $engine, new IdempotencyBegin(replay: $receipt), ['organizationId' => 'organization']);
    $replayed = $processor->process($input, new Patch(), ['id' => 'equipment']);
    self::assertInstanceOf(\Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput::class, $replayed);
    self::assertEquals($original, $replayed);
    self::assertSame(1, $effects);
    self::assertSame(4, $revision);
    $response = new JsonResponse(['revision' => $replayed->revision, 'name' => $replayed->name]);
    new RevisionEtagSubscriber()(new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));
    self::assertSame('"revision-3"', $response->getEtag());
    self::assertSame(200, $response->getStatusCode());
  }

  private static function operation(): HttpIdempotencyOperation
  {
    return new HttpIdempotencyOperation('canonical_equipment_patch', 'main', 'bus_owner', PatchCanonicalEquipmentCommand::class, PatchCanonicalEquipmentResult::class, queryProjections: [
      new HttpIdempotencyQueryProjection(GetCanonicalEquipmentQuery::class, GetCanonicalEquipmentResult::class, [CanonicalEquipmentView::class]),
      new HttpIdempotencyQueryProjection(GetEquipmentDetailQuery::class, GetEquipmentDetailResult::class, [GetEquipmentResult::class, CanonicalEquipmentView::class], captureBeforePrimary: false),
    ]);
  }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Idempotency;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\{HttpIdempotencyAuthorizationRequest, HttpIdempotencyOperation, HttpIdempotencyPrincipal};
use Shared\Application\Contract\Idempotency\{IdempotencyBegin, IdempotencyDeniedException, IdempotencyProof, IdempotencyScope, IdempotencySnapshot};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAuthorizationEvidencePort, HttpIdempotencyOperationContributionPort, HttpIdempotencyPort, HttpIdempotencyPrincipalPort, HttpIdempotencyReplayAuthorizationPort, SensitiveIdempotencyProofPort};
use Shared\Application\Service\Idempotency\HttpIdempotencyOperationRegistry;
use Shared\Infrastructure\Idempotency\{HmacIdempotencyFingerprintAdapter, HttpIdempotencyEngineCatalog, HttpIdempotencyExecutionContext, HttpIdempotencyPolicyRegistry, HttpIdempotencyRequestInput, HttpIdempotencyResultCodec, HttpIdempotencySubscriber};
use Symfony\Component\HttpFoundation\{Request, RequestStack, Response};
use Symfony\Component\HttpKernel\Event\{ExceptionEvent, FinishRequestEvent, RequestEvent, ResponseEvent};
use Symfony\Component\HttpKernel\HttpKernelInterface;

use function base64_encode;
use function str_repeat;

/**
 * Class HttpIdempotencySubscriberTest
 *
 * Verifies fresh owner gates precede replay decoding and stable public failures are independent of kernel response capture.
 *
 * @category EventSubscriber Tests
 */
#[CoversClass(HttpIdempotencySubscriber::class)]
final class HttpIdempotencySubscriberTest extends TestCase
{
  #[Test]
  public function onlyKeyedInvalidOrMandatorySensitiveIntentsTrackTheAuthenticatedPrincipal(): void
  {
    foreach ([[null, false, false], [['key'], false, true], [null, true, true], [['first', 'second'], false, true]] as [$keys, $required, $tracked]) {
      $request = $this->request();
      if (null !== $keys) {
        $request->headers->set('Idempotency-Key', $keys);
      }
      $stack = new RequestStack();
      $stack->push($request);
      $fingerprint = new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('x', 32)));
      $context = new HttpIdempotencyExecutionContext($stack, new HttpIdempotencyResultCodec(), $fingerprint, $this->createStub(Connection::class), $this->createStub(Connection::class));
      $principals = $this->createStub(HttpIdempotencyPrincipalPort::class);
      $principals->method('current')->willReturn(new HttpIdempotencyPrincipal('actor'));
      $policy = $this->createMock(SensitiveIdempotencyProofPort::class);
      $policy->method('supports')->willReturn(true);
      $policy->expects(self::never())->method('authorize');
      $evidence = $this->createMock(HttpIdempotencyAuthorizationEvidencePort::class);
      $evidence->expects($tracked ? self::once() : self::never())->method('begin')->with('actor');
      $engine = $this->createStub(HttpIdempotencyPort::class);
      $subscriber = new HttpIdempotencySubscriber(new HttpIdempotencyOperationRegistry([new SubscriberTestContribution()]), new HttpIdempotencyPolicyRegistry([], [$policy]), new HttpIdempotencyEngineCatalog($engine, $engine), new HttpIdempotencyRequestInput($fingerprint), $context, $principals, $this->createStub(ResourceMetadataCollectionFactoryInterface::class), $required, $evidence);
      $subscriber->onRequest($this->event($request));
      self::assertTrue($request->attributes->has(\Shared\Infrastructure\Idempotency\HttpIdempotencyCommandPreparation::ATTRIBUTE));
      self::assertNull($context->executionLease());
    }
  }

  #[Test]
  public function itChecksTheCurrentGateBeforeBeginAndPublishesOnlyAReplayMarker(): void
  {
    $request = $this->request('same-key');
    $steps = [];
    $scope = new IdempotencyScope('actor', null, null, 'subscriber_test', '/api/items/gone');
    $proof = new IdempotencyProof(['organizationId' => 'original-org'], new DateTimeImmutable('2030-01-01T00:00:00.123456Z'));
    $selected = new IdempotencyProof($proof->metadata, new DateTimeImmutable('2030-01-01T01:00:00.123456+01:00'));
    self::assertNotSame($proof, $selected);
    self::assertNotSame($proof->expiresAt, $selected->expiresAt);
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::once())->method('inspectProof')->with($scope, 'same-key')->willReturnCallback(static function () use (&$steps, $proof): IdempotencyProof {
      $steps[] = 'inspect';

      return $proof;
    });
    $engine->expects(self::once())->method('begin')->willReturnCallback(static function (IdempotencyScope $scope, string $key, mixed $input, ?callable $authorize) use (&$steps, $selected): IdempotencyBegin {
      $steps[] = 'begin';
      self::assertNotNull($authorize);
      $authorize($selected);

      return new IdempotencyBegin(replay: new IdempotencySnapshot(200, new HttpIdempotencyResultCodec()->encode(new SubscriberTestResult('original'), SubscriberTestContribution::definitions()[0])));
    });
    $gate = $this->createMock(HttpIdempotencyReplayAuthorizationPort::class);
    $gate->method('supports')->willReturn(true);
    $gate->expects(self::once())->method('scope')->willReturnCallback(static function (HttpIdempotencyAuthorizationRequest $facts) use (&$steps, $scope): IdempotencyScope {
      self::assertSame('actor', $facts->principalId);
      self::assertSame("is_granted('ROLE_USER')", $facts->platformSecurityExpression);
      $steps[] = 'scope';

      return $scope;
    });
    $gate->expects(self::once())->method('authorize')->with(self::isInstanceOf(HttpIdempotencyAuthorizationRequest::class), $proof)->willReturnCallback(static function () use (&$steps): array {
      $steps[] = 'gate';

      return ['organizationId' => 'original-org'];
    });
    [$subscriber, $context] = $this->subscriber($request, $engine, $gate);
    $subscriber->onRequest($this->event($request));
    self::assertSame(['scope', 'inspect', 'gate', 'begin'], $steps);
    $result = $context->dispatchPrimary(new SubscriberTestCommand(), static function (): ResultMessage { self::fail('Replay cannot mutate.'); });
    self::assertInstanceOf(SubscriberTestResult::class, $result);
    self::assertSame('original', $result->value);
    $response = new Response('projected original');
    $subscriber->onResponse(new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));
    self::assertSame('true', $response->headers->get('Idempotency-Replayed'));
    $subscriber->onFinish(new FinishRequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    self::assertFalse($context->isReplay());
  }

  #[Test]
  public function itNeverOpensTheBodyWhenCurrentOwnerAccessIsDenied(): void
  {
    $request = $this->request('same-key');
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::never())->method('begin');
    $gate = $this->createMock(HttpIdempotencyReplayAuthorizationPort::class);
    $gate->method('supports')->willReturn(true);
    $gate->method('scope')->willReturn(new IdempotencyScope('actor', null, null, 'subscriber_test', '/api/items/gone'));
    $gate->expects(self::once())->method('authorize')->willThrowException(new IdempotencyDeniedException('access_denied'));
    [$subscriber] = $this->subscriber($request, $engine, $gate);
    $this->expectException(IdempotencyDeniedException::class);
    $this->expectExceptionMessage('access_denied');
    $subscriber->onRequest($this->event($request));
  }

  #[Test]
  public function aCompletionAfterPendingInspectionChecksTheOriginalOrdinaryScopeBeforeReplay(): void
  {
    foreach ([true, false] as $allowed) {
      $request = $this->request('same-key');
      $proof = new IdempotencyProof(['organizationId' => 'original-org', 'authorizationRequirements' => 'original-write-permission'], new DateTimeImmutable('2030-01-01'));
      $engine = $this->createMock(HttpIdempotencyPort::class);
      $engine->expects(self::once())->method('inspectProof')->willReturn(null);
      $engine->expects(self::once())->method('begin')->willReturnCallback(static function (IdempotencyScope $scope, string $key, mixed $input, ?callable $authorize) use ($proof): IdempotencyBegin {
        self::assertNotNull($authorize);
        $authorize($proof);

        return new IdempotencyBegin(replay: new IdempotencySnapshot(200, new HttpIdempotencyResultCodec()->encode(new SubscriberTestResult('committed-original'), SubscriberTestContribution::definitions()[0])));
      });
      $gate = $this->createMock(HttpIdempotencyReplayAuthorizationPort::class);
      $gate->method('supports')->willReturn(true);
      $gate->method('scope')->willReturn(new IdempotencyScope('actor', null, null, 'subscriber_test', '/api/items/gone'));
      $gate->expects(self::exactly(2))->method('authorize')->willReturnCallback(static function (HttpIdempotencyAuthorizationRequest $facts, ?IdempotencyProof $selected) use ($proof, $allowed): array {
        if (null !== $selected) {
          self::assertSame($proof, $selected);
          if (!$allowed) {
            throw new IdempotencyDeniedException('access_denied');
          }
        }

        return null === $selected ? [] : $selected->metadata;
      });
      [$subscriber, $context] = $this->subscriber($request, $engine, $gate);

      try {
        $subscriber->onRequest($this->event($request));
        self::assertTrue($allowed);
        $result = $context->dispatchPrimary(new SubscriberTestCommand(), static function (): ResultMessage { self::fail('Completed ordinary mutation cannot execute again.'); });
        self::assertInstanceOf(SubscriberTestResult::class, $result);
        self::assertSame('committed-original', $result->value);
      } catch (IdempotencyDeniedException $failure) {
        self::assertFalse($allowed);
        self::assertSame('access_denied', $failure->reason);
        self::assertFalse($context->isReplay());
      }
    }
  }

  #[Test]
  public function itRequiresTheHeaderOnlyAfterTheCurrentGateInStrictMode(): void
  {
    $request = $this->request();
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::never())->method('begin');
    $engine->expects(self::never())->method('inspectProof');
    $gate = $this->createMock(HttpIdempotencyReplayAuthorizationPort::class);
    $gate->method('supports')->willReturn(true);
    $gate->method('scope')->willReturn(new IdempotencyScope('actor', null, null, 'subscriber_test', '/api/items/gone'));
    $gate->expects(self::once())->method('authorize')->with(self::isInstanceOf(HttpIdempotencyAuthorizationRequest::class), null)->willReturn([]);
    [$subscriber] = $this->subscriber($request, $engine, $gate);
    $this->expectException(IdempotencyDeniedException::class);
    $this->expectExceptionMessage('key_required');
    $subscriber->onRequest($this->event($request));
  }

  #[Test]
  public function compatibilityModeOverridesThePerOperationHeaderRequirement(): void
  {
    $request = $this->request();
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::never())->method('begin');
    $gate = $this->createMock(HttpIdempotencyReplayAuthorizationPort::class);
    $gate->expects(self::never())->method('scope');
    [$subscriber, $context] = $this->subscriber($request, $engine, $gate, false);
    $subscriber->onRequest($this->event($request));
    self::assertNull($context->executionLease());
  }

  #[Test]
  public function itRejectsMultipleOrInvalidOpaqueKeysBeforeReserving(): void
  {
    $request = $this->request();
    $request->headers->set('Idempotency-Key', ['first', 'second']);
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::never())->method('begin');
    [$subscriber] = $this->subscriber($request, $engine, $this->createStub(HttpIdempotencyReplayAuthorizationPort::class));
    $this->expectException(IdempotencyDeniedException::class);
    $this->expectExceptionMessage('key_invalid');
    $subscriber->onRequest($this->event($request));
  }

  #[Test]
  public function itMapsStableFailuresAndPreservesOwnerProtocolResponses(): void
  {
    $request = $this->request();
    [$subscriber] = $this->subscriber($request, $this->createStub(HttpIdempotencyPort::class), $this->createStub(HttpIdempotencyReplayAuthorizationPort::class));
    foreach ([['key_required', 400, 'idempotency_key_required'], ['key_invalid', 400, 'idempotency_key_invalid'], ['input_mismatch', 409, 'idempotency_conflict'], ['in_progress', 409, 'idempotency_in_progress'], ['expired', 409, 'idempotency_replay_expired'], ['commit_unknown', 409, 'idempotency_outcome_unknown'], ['outcome_unknown', 409, 'idempotency_outcome_unknown'], ['access_denied', 403, 'access_denied'], ['authorization_unavailable', 403, 'access_denied'], ['scope_not_found', 404, 'resource_not_found']] as [$reason, $status, $code]) {
      $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new IdempotencyDeniedException($reason));
      $subscriber->onException($event);
      $response = $event->getResponse();
      self::assertInstanceOf(Response::class, $response);
      self::assertSame($status, $response->getStatusCode());
      self::assertStringContainsString('"code":"' . $code . '"', $response->getContent() ?: '');
      self::assertSame('in_progress' === $reason ? '1' : null, $response->headers->get('Retry-After'));
      self::assertStringNotContainsString('operationCompleted', $response->getContent() ?: '');
    }
    $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new IdempotencyDeniedException('key_required'));
    $protocol = new Response('{"error":"invalid_request"}', 400);
    $event->setResponse($protocol);
    $subscriber->onException($event);
    self::assertSame($protocol, $event->getResponse());
  }

  #[Test]
  public function onlyAnEngineAuthenticatedExpiredMatchingCompletionMayAcknowledgeTheOperation(): void
  {
    $request = $this->request();
    [$subscriber] = $this->subscriber($request, $this->createStub(HttpIdempotencyPort::class), $this->createStub(HttpIdempotencyReplayAuthorizationPort::class));
    $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new IdempotencyDeniedException('expired', operationCompleted: true));
    $subscriber->onException($event);
    $response = $event->getResponse();
    self::assertInstanceOf(Response::class, $response);
    self::assertSame(409, $response->getStatusCode());
    self::assertStringContainsString('"operationCompleted":true', $response->getContent() ?: '');
    self::assertStringContainsString('idempotency_replay_expired', $response->getContent() ?: '');
  }

  private function request(?string $key = null): Request
  {
    $request = Request::create('/api/items/gone', 'DELETE', server: ['CONTENT_TYPE' => 'application/json'], content: '{"value":1}');
    $request->attributes->set('_api_operation', new Post(name: 'subscriber_test', security: "is_granted('ROLE_USER')"));
    if (null !== $key) {
      $request->headers->set('Idempotency-Key', $key);
    }

    return $request;
  }

  private function event(Request $request): RequestEvent
  {
    return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
  }

  /**
   * @return array{HttpIdempotencySubscriber, HttpIdempotencyExecutionContext}
   */
  private function subscriber(Request $request, HttpIdempotencyPort $engine, HttpIdempotencyReplayAuthorizationPort $gate, bool $required = true): array
  {
    $stack = new RequestStack();
    $stack->push($request);
    $fingerprint = new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('x', 32)));
    $context = new HttpIdempotencyExecutionContext($stack, new HttpIdempotencyResultCodec(), $fingerprint, $this->createStub(Connection::class), $this->createStub(Connection::class));
    $principals = $this->createStub(HttpIdempotencyPrincipalPort::class);
    $principals->method('current')->willReturn(new HttpIdempotencyPrincipal('actor'));

    return [new HttpIdempotencySubscriber(new HttpIdempotencyOperationRegistry([new SubscriberTestContribution()]), new HttpIdempotencyPolicyRegistry([$gate], []), new HttpIdempotencyEngineCatalog($engine, $engine), new HttpIdempotencyRequestInput($fingerprint), $context, $principals, $this->createStub(ResourceMetadataCollectionFactoryInterface::class), $required), $context];
  }
}

final readonly class SubscriberTestCommand implements CommandMessage
{
}

final readonly class SubscriberTestResult implements ResultMessage
{
  public function __construct(public string $value)
  {
  }
}

final readonly class SubscriberTestContribution implements HttpIdempotencyOperationContributionPort
{
  public static function definitions(): array
  {
    return [new HttpIdempotencyOperation('subscriber_test', 'main', 'bus_owner', SubscriberTestCommand::class, SubscriberTestResult::class, requiredKey: true)];
  }
}

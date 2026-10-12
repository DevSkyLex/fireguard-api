<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Infrastructure\Adapter\Idempotency;

use Approval\Infrastructure\Adapter\Idempotency\ApprovalHttpIdempotencyOperationContributionAdapter;
use Billing\Infrastructure\Adapter\Idempotency\BillingHttpIdempotencyOperationContributionAdapter;
use Calendar\Infrastructure\Adapter\Idempotency\CalendarHttpIdempotencyOperationContributionAdapter;
use Messaging\Infrastructure\Adapter\Idempotency\MessagingHttpIdempotencyOperationContributionAdapter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Webhook\Infrastructure\Adapter\Idempotency\WebhookHttpIdempotencyOperationContributionAdapter;

use function array_merge;

/**
 * Class OrdinaryModuleIdempotencyTest
 *
 * Checks exact mutable route ownership, immutable result schemas and short secret classifications at composition.
 *
 * @category Test
 */
final class OrdinaryModuleIdempotencyTest extends TestCase
{
  /**
   * Method testDescriptorsUseRealImmutableMessagesAndExplicitDatabaseOwnership
   */
  public function testDescriptorsUseRealImmutableMessagesAndExplicitDatabaseOwnership(): void
  {
    $operations = array_merge(
      ApprovalHttpIdempotencyOperationContributionAdapter::definitions(),
      BillingHttpIdempotencyOperationContributionAdapter::definitions(),
      CalendarHttpIdempotencyOperationContributionAdapter::definitions(),
      MessagingHttpIdempotencyOperationContributionAdapter::definitions(),
      WebhookHttpIdempotencyOperationContributionAdapter::definitions(),
    );
    self::assertCount(47, $operations);
    $names = [];
    foreach ($operations as $operation) {
      self::assertTrue($operation->requiredKey, $operation->name);
      self::assertNotContains($operation->name, $names);
      $names[] = $operation->name;
      self::assertTrue(new ReflectionClass($operation->resultClass)->isReadOnly(), $operation->name);
      foreach ($operation->contractClasses as $class) {
        self::assertTrue(new ReflectionClass($class)->isReadOnly(), $operation->name);
      }
      foreach ($operation->queryProjections as $projection) {
        self::assertFalse($projection->captureBeforePrimary, $operation->name);
        self::assertTrue(new ReflectionClass($projection->resultClass)->isReadOnly(), $operation->name);
        foreach ($projection->contractClasses as $class) {
          self::assertTrue(new ReflectionClass($class)->isReadOnly(), $operation->name);
        }
      }
    }
    self::assertNotContains('stripeWebhook', $names);
  }

  /**
   * Method testFeedTokensAndMediaKeepTheirActualTransactionOwner
   */
  public function testFeedTokensAndMediaKeepTheirActualTransactionOwner(): void
  {
    $calendar = $this->byName(CalendarHttpIdempotencyOperationContributionAdapter::definitions());
    self::assertSame('main', $calendar['createCalendarFeedToken']->database);
    self::assertTrue($calendar['createCalendarFeedToken']->secret);
    self::assertSame('main', $calendar['deleteCalendarFeedToken']->database);
    self::assertFalse($calendar['deleteCalendarFeedToken']->secret);
    $messaging = $this->byName(MessagingHttpIdempotencyOperationContributionAdapter::definitions());
    self::assertSame('root_owner', $messaging['add_message_attachment']->transactionMode);
    self::assertSame('root_owner', $messaging['delete_message_attachment']->transactionMode);
    self::assertSame('root_owner', $messaging['_api_/conversations/{conversationId}/messages_post']->transactionMode);
    self::assertSame('root_owner', $messaging['put_message']->transactionMode);
    self::assertSame('root_owner', $messaging['post_reply']->transactionMode);
    self::assertSame('message.id', $messaging['post_reply']->resultIdentifierProperty);
    self::assertCount(1, $messaging['post_reply']->queryProjections);
    self::assertCount(0, $messaging['unsave_message']->queryProjections);
  }

  /**
   * Method byName
   *
   * @param list<HttpIdempotencyOperation> $operations exact owner declarations
   *
   * @return array<string, HttpIdempotencyOperation> named declarations
   */
  private function byName(array $operations): array
  {
    $result = [];
    foreach ($operations as $operation) {
      $result[$operation->name] = $operation;
    }

    return $result;
  }
}

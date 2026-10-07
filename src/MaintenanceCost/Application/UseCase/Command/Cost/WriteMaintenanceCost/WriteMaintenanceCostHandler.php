<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Command\Cost\WriteMaintenanceCost;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Intervention\Application\Contract\Cost\InterventionCostContext;
use Intervention\Application\Port\Inbound\InterventionCostSourceFactsPort;
use MaintenanceCost\Application\Contract\Cost\{MaintenanceCostPlanning, MaintenanceExpense};
use MaintenanceCost\Application\Port\Inbound\MaintenanceCurrencyPort;
use MaintenanceCost\Application\Port\Outbound\MaintenanceCostStorePort;
use MaintenanceCost\Application\Service\{MaintenanceCostAccessGuard, MaintenanceCostProjection};
use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use MaintenanceCost\Domain\Service\MaintenanceCostCalculator;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, TransactionManagerPort, UuidGeneratorPort};
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\{DecimalAmount, Uuid};

use function array_key_exists;
use function count;
use function hash;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function json_encode;
use function mb_strlen;
use function preg_match;
use function strtolower;
use function substr;
use function trim;

use const JSON_THROW_ON_ERROR;

/** Class WriteMaintenanceCostHandler. Versioned preparation and immutable expenses under the main work lock. @category UseCase */
final readonly class WriteMaintenanceCostHandler implements CommandHandler
{
  public function __construct(private MaintenanceCostAccessGuard $access, private InterventionCostSourceFactsPort $work, private MaintenanceCostStorePort $store, private MaintenanceCurrencyPort $currencies, private MaintenanceCostProjection $projection, private MaintenanceCostCalculator $calculator, private TransactionManagerPort $transactions, private UuidGeneratorPort $uuids, private ClockPort $clock)
  {
  }

  public function __invoke(WriteMaintenanceCostCommand $command): WriteMaintenanceCostResult
  {
    $this->access->assertAccess($command->actorId, $command->organizationId, true);
    // Mutations return the complete private projection, so its dedicated
    // read entitlement is required independently from management rights.
    $this->access->assertAccess($command->actorId, $command->organizationId, false);

    return $this->transactions->transactional(function () use ($command): WriteMaintenanceCostResult {
      $context = $this->work->context($command->organizationId, $command->interventionId, true) ?? throw MaintenanceCostException::notFound();
      if ('planning' === $command->action) {
        $this->planning($command, $context);
      } elseif ('expense' === $command->action) {
        $this->expense($command, $context);
      } else {
        throw MaintenanceCostException::invalid('Unknown cost mutation.');
      }

      return new WriteMaintenanceCostResult($this->projection->view($command->organizationId, $command->interventionId));
    });
  }

  private function planning(WriteMaintenanceCostCommand $command, InterventionCostContext $context): void
  {
    if (in_array($context->status, ['submitted', 'published', 'abandoned'], true)) {
      throw MaintenanceCostException::conflict('Financial preparation is closed for this intervention.');
    }
    if (null === $command->expectedRevision) {
      throw MaintenanceCostException::preconditionRequired();
    }
    $current = $this->store->planning($command->organizationId, $command->interventionId);
    if ($current->revision !== $command->expectedRevision) {
      throw MaintenanceCostException::stale();
    }
    foreach ($command->values as $key => $value) {
      if (!in_array($key, ['plannedBudget', 'estimatedMinutes', 'resources'], true)) {
        throw MaintenanceCostException::invalid('Unknown planning field.');
      }
    }
    $budget = array_key_exists('plannedBudget', $command->values) ? $this->nullableAmount($command->values['plannedBudget']) : $current->plannedBudget;
    $minutes = array_key_exists('estimatedMinutes', $command->values) ? $this->nullableMinutes($command->values['estimatedMinutes']) : $current->estimatedMinutes;
    $resources = array_key_exists('resources', $command->values) ? $this->resources($command->values['resources'], $context) : $current->resources;
    $this->store->savePlanning($command->organizationId, $command->interventionId, new MaintenanceCostPlanning($budget, $minutes, $resources, $current->revision + 1));
  }

  private function expense(WriteMaintenanceCostCommand $command, InterventionCostContext $context): void
  {
    foreach ($command->values as $key => $value) {
      if (!in_array($key, ['clientId', 'amount', 'description', 'incurredAt', 'workItemId', 'adjustmentOf'], true)) {
        throw MaintenanceCostException::invalid('Unknown expense field.');
      }
    }
    $clientId = $this->string($command->values['clientId'] ?? null, 64);
    if (1 === preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-7][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $clientId)) {
      $clientId = strtolower($clientId);
    }
    $this->store->lockExpenseIdentity($command->organizationId, $clientId);
    $description = $this->string($command->values['description'] ?? null, 2000);
    $rawAmount = $this->string($command->values['amount'] ?? null, 26);
    $amount = $this->amount($rawAmount, true);
    $date = $this->string($command->values['incurredAt'] ?? null, 40);
    if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D', $date)) {
      throw MaintenanceCostException::invalid('The expense date must identify its timezone.');
    }

    try {
      $incurredAt = new DateTimeImmutable($date);
    } catch (DateMalformedStringException) {
      throw MaintenanceCostException::invalid('The expense date is invalid.');
    }
    if ($incurredAt->format('Y-m-d\TH:i:s') !== substr($date, 0, 19)) {
      throw MaintenanceCostException::invalid('The expense date is not a valid calendar date.');
    }
    $incurredAt = $incurredAt->setTimezone(new DateTimeZone('UTC'));
    if ($incurredAt > $this->clock->now()) {
      throw MaintenanceCostException::invalid('An actual expense cannot be dated in the future.');
    }
    $workItemId = $this->nullableId($command->values['workItemId'] ?? null);
    if (null !== $workItemId && !in_array($workItemId, $context->workItemIds, true)) {
      throw MaintenanceCostException::notFound();
    }
    $adjustmentOf = $this->nullableId($command->values['adjustmentOf'] ?? null);
    if (null !== $adjustmentOf) {
      $original = $this->store->expense($command->organizationId, $adjustmentOf);
      if (null === $original || $original->interventionId !== $command->interventionId || null !== $original->adjustmentOf) {
        throw MaintenanceCostException::notFound();
      }
    } elseif (DecimalAmount::fromString($amount)->isNegative()) {
      throw MaintenanceCostException::invalid('A negative expense requires its original expense and a correction reason.');
    }
    $payloadHash = hash('sha256', json_encode([$command->interventionId, $workItemId, $amount, $description, $incurredAt->format('c'), $adjustmentOf], JSON_THROW_ON_ERROR));
    $existing = $this->store->expenseByClientId($command->organizationId, $clientId);
    if (null !== $existing) {
      if ($existing->payloadHash !== $payloadHash || $existing->createdBy !== $command->actorId) {
        throw MaintenanceCostException::conflict('This expense identifier is already used by another declaration.');
      }

      return;
    }
    $currency = $this->currencies->lock($command->organizationId);
    $this->store->saveExpense(new MaintenanceExpense($this->uuids->generate(), $command->organizationId, $command->interventionId, $workItemId, $clientId, $amount, $currency, $description, $incurredAt, $adjustmentOf, $command->actorId, $payloadHash, $this->clock->now()));
  }

  /**
   * @return list<array{workItemId:?string,kind:string,description:string,quantity:?string,unitCost:?string,estimatedMinutes:?int,amount:?string}>
   */
  private function resources(mixed $input, InterventionCostContext $context): array
  {
    if (!is_array($input) || count($input) > 1000) {
      throw MaintenanceCostException::invalid('At most one thousand estimated resources may be prepared.');
    }
    $result = [];
    foreach ($input as $resource) {
      if (!is_array($resource)) {
        throw MaintenanceCostException::invalid('Estimated resources must be structured objects.');
      }
      foreach ($resource as $key => $value) {
        if (!in_array($key, ['workItemId', 'kind', 'description', 'quantity', 'unitCost', 'estimatedMinutes', 'amount'], true)) {
          throw MaintenanceCostException::invalid('Unknown estimated resource field.');
        }
      }
      $workItemId = $this->nullableId($resource['workItemId'] ?? null);
      if (null !== $workItemId && !in_array($workItemId, $context->workItemIds, true)) {
        throw MaintenanceCostException::notFound();
      }
      $kind = $this->string($resource['kind'] ?? null, 16);
      if (!in_array($kind, ['time', 'material', 'external'], true)) {
        throw MaintenanceCostException::invalid('Unknown estimated resource kind.');
      }
      $quantity = $this->nullableAmount($resource['quantity'] ?? null);
      $unitCost = $this->nullableAmount($resource['unitCost'] ?? null);
      $minutes = $this->nullableMinutes($resource['estimatedMinutes'] ?? null);
      $amount = $this->nullableAmount($resource['amount'] ?? null);
      if ('time' === $kind) {
        if (null !== $quantity) {
          throw MaintenanceCostException::invalid('A time estimate cannot include a material quantity.');
        }
        $amount = null !== $unitCost && null !== $minutes ? $this->calculator->timeAmount($unitCost, $minutes) : null;
      } elseif ('material' === $kind) {
        if (null !== $minutes) {
          throw MaintenanceCostException::invalid('A material estimate cannot include work minutes.');
        }
        $amount = null !== $unitCost && null !== $quantity ? DecimalAmount::fromString($unitCost)->multiply(DecimalAmount::fromString($quantity))->toString() : null;
      } elseif (null !== $quantity || null !== $unitCost || null !== $minutes) {
        throw MaintenanceCostException::invalid('An external estimate uses its explicit amount without quantity, rate or minutes.');
      }
      $result[] = ['workItemId' => $workItemId, 'kind' => $kind, 'description' => $this->string($resource['description'] ?? null, 1000), 'quantity' => $quantity, 'unitCost' => $unitCost, 'estimatedMinutes' => $minutes, 'amount' => $amount];
    }

    return $result;
  }

  private function nullableAmount(mixed $value): ?string
  {
    return null === $value ? null : $this->amount($this->string($value, 26));
  }

  private function amount(string $value, bool $signed = false): string
  {
    try {
      $amount = DecimalAmount::fromString($value);
    } catch (InvalidValueException $error) {
      throw MaintenanceCostException::invalid($error->getMessage());
    }
    if (!$signed && $amount->isNegative()) {
      throw MaintenanceCostException::invalid('A planned amount or quantity cannot be negative.');
    }

    return $amount->toString();
  }

  private function nullableMinutes(mixed $value): ?int
  {
    if (null !== $value && (!is_int($value) || $value < 0 || $value > 100000000)) {
      throw MaintenanceCostException::invalid('Estimated minutes must be a bounded non-negative integer.');
    }

    return $value;
  }

  private function nullableId(mixed $value): ?string
  {
    if (null === $value) {
      return null;
    }
    $id = $this->string($value, 36);
    new Uuid($id);

    return $id;
  }

  private function string(mixed $value, int $maximum): string
  {
    if (!is_string($value) || '' === trim($value) || mb_strlen(trim($value)) > $maximum) {
      throw MaintenanceCostException::invalid('A non-empty bounded string is required.');
    }

    return trim($value);
  }
}

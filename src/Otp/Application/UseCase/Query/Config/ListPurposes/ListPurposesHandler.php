<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Query\Config\ListPurposes;

use Otp\Domain\ValueObject\OtpPurpose;
use Shared\Application\Message\QueryHandler;

/**
 * Handler ListPurposesHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListPurposesHandler implements QueryHandler
{
  // #region Methods
  public function __invoke(ListPurposesQuery $query): ListPurposesResult
  {
    $items = [];

    foreach (OtpPurpose::cases() as $purpose) {
      $items[] = new PurposeResult(
        value: $purpose->value,
        label: $purpose->getLabel(),
        ttlSeconds: $purpose->getDefaultTtlSeconds(),
        maxAttempts: $purpose->getDefaultMaxAttempts(),
      );
    }

    return new ListPurposesResult(items: $items);
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Customer\Domain\ValueObject;

/**
 * Class CustomerDetails
 *
 * Groups customer descriptive fields for creation, edits and exact persisted restoration.
 * Customer validates supplied fields; restoring retained state must not normalize them again.
 *
 * @category ValueObject
 */
final readonly class CustomerDetails
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries descriptive fields without replacing the aggregate's validation policy.
   *
   * @access public
   *
   * @param string $name the supplied or persisted customer name
   * @param string|null $code the nullable organization-specific customer code
   * @param string|null $email the nullable email address
   * @param string|null $phone the nullable phone number
   * @param array<mixed> $contacts supplied contacts or the retained normalized list
   *
   * @return void
   */
  public function __construct(
    public string $name,
    public ?string $code,
    public ?string $email,
    public ?string $phone,
    public array $contacts,
  ) {
  }
  // #endregion
}

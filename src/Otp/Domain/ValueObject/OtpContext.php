<?php

declare(strict_types=1);

namespace Otp\Domain\ValueObject;

/**
 * ValueObject OtpContext
 * @final
 *
 * Holds business context for the OTP (transaction ID, description, etc.).
 *
 * @category ValueObject
 * @package Otp\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OtpContext
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of the 
   * OtpContext class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string|null $transactionId The transaction ID.
   * @param string|null $description Human-readable description.
   * @param array<string, mixed>|null $data Additional custom data.
   */
  public function __construct(
    public ?string $transactionId = null,
    public ?string $description = null,
    public ?array $data = null,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method create
   * @static
   *
   * Creates a new OtpContext instance.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string|null $transactionId The transaction ID.
   * @param string|null $description Human-readable description.
   * @param array<string, mixed>|null $data Additional custom data.
   *
   * @return self
   */
  public static function create(
    ?string $transactionId = null,
    ?string $description = null,
    ?array $data = null,
  ): self {
    return new self(
      transactionId: $transactionId,
      description: $description,
      data: $data,
    );
  }

  /**
   * Method fromArray
   * @static
   *
   * Creates from an array.
   *
   * @access public
   * @since 1.0.0
   *
   * @param array<string, mixed> $inputData The data array.
   *
   * @return self
   */
  public static function fromArray(array $inputData): self
  {
    $transactionId = $inputData['transaction_id'] ?? $inputData['transactionId'] ?? null;
    $description = $inputData['description'] ?? null;
    $data = $inputData['data'] ?? null;

    return new self(
      transactionId: is_string($transactionId) ? $transactionId : null,
      description: is_string($description) ? $description : null,
      data: is_array($data) ? self::filterStringKeys($data) : null,
    );
  }

  /**
   * Filter array to ensure string keys.
   *
   * @param array<mixed, mixed> $arr
   * @return array<string, mixed>
   */
  private static function filterStringKeys(array $arr): array
  {
    $result = [];
    foreach ($arr as $key => $value) {
      if (is_string($key)) {
        $result[$key] = $value;
      }
    }
    return $result;
  }

  /**
   * Method toArray
   *
   * Converts to an array.
   *
   * @access public
   * @since 1.0.0
   *
   * @return array<string, mixed>
   */
  public function toArray(): array
  {
    return [
      'transaction_id' => $this->transactionId,
      'description' => $this->description,
      'data' => $this->data,
    ];
  }

  /**
   * Method isEmpty
   *
   * Checks if all fields are null.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool
   */
  public function isEmpty(): bool
  {
    return $this->transactionId === null
      && $this->description === null
      && $this->data === null;
  }
  //#endregion
}

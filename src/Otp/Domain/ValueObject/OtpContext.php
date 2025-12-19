<?php

declare(strict_types=1);

namespace Otp\Domain\ValueObject;

use function is_array;
use function is_string;

/**
 * ValueObject OtpContext.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OtpContext
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the
     * OtpContext class.
     *
     * @since 1.0.0
     *
     * @param string|null               $transactionId the transaction ID
     * @param string|null               $description   human-readable description
     * @param array<string, mixed>|null $data          additional custom data
     */
    public function __construct(
        public ?string $transactionId = null,
        public ?string $description = null,
        public ?array $data = null,
    ) {
    }
    // #endregion

    // #region Methods
    /**
     * Method create.
     *
     * @static
     *
     * Creates a new OtpContext instance.
     *
     * @since 1.0.0
     *
     * @param string|null               $transactionId the transaction ID
     * @param string|null               $description   human-readable description
     * @param array<string, mixed>|null $data          additional custom data
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
     * Method fromArray.
     *
     * @static
     *
     * Creates from an array.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $inputData the data array
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
     *
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
     * Method toArray.
     *
     * Converts to an array.
     *
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
     * Method isEmpty.
     *
     * Checks if all fields are null.
     *
     * @since 1.0.0
     */
    public function isEmpty(): bool
    {
        return null === $this->transactionId
          && null === $this->description
          && null === $this->data;
    }
    // #endregion
}

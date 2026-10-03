<?php

declare(strict_types=1);

namespace App\Api;

/** L'API a répondu 422 : on remonte les violations (propertyPath → message) pour les afficher dans les formulaires. */
final class ApiValidationException extends \RuntimeException
{
    /**
     * @param list<array{propertyPath: string, message: string}> $violations
     */
    public function __construct(public readonly array $violations)
    {
        parent::__construct(implode(' ', array_column($violations, 'message')));
    }
}

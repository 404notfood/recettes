<?php

namespace App\Services\Menus;

use RuntimeException;

class PlanningException extends RuntimeException
{
    /** @param array<string,mixed> $diagnostics Safe metadata only: never include prompts, credentials or response bodies. */
    public function __construct(string $message, public readonly array $diagnostics = [])
    {
        parent::__construct($message);
    }
}

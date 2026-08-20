<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use LogicException;
use Stringable;

/**
 * The refund tool *definition* the model sees. Never executes directly: the
 * bound tool routes it through the orders.refund capability, whose
 * confirmation gate pauses the run for a human before anything moves.
 */
final class RefundOrderTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Request a full refund of one of the customer\'s own delivered orders. Requires human approval before executing.';
    }

    public function handle(Request $request): Stringable|string
    {
        throw new LogicException('The Verdict-bound tool handles every invocation; this definition never executes.');
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'order_number' => $schema->string()->description('The order number to refund, for example ORD-1001.'),
            'reason' => $schema->string()->description('Why the customer wants the refund.'),
        ];
    }
}

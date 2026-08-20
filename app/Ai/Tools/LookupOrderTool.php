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
 * The tool *definition* the model sees. It never executes: the agent hands
 * Verdict this definition via VerdictManager::bound(), and the bound tool
 * routes every invocation through the orders.lookup capability instead
 * (docs/adoption-guide.md § "Registering capabilities: affirm, don't wire").
 */
final class LookupOrderTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Look up the status and contents of one of the customer\'s own orders by its order number.';
    }

    public function handle(Request $request): Stringable|string
    {
        throw new LogicException('The Verdict-bound tool handles every invocation; this definition never executes.');
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'order_number' => $schema->string()->description('The order number, for example ORD-1001.'),
        ];
    }
}

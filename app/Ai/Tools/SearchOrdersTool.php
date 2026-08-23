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
 * The set-returning tool *definition* the model sees. Like LookupOrderTool it
 * never executes: VerdictManager::bound() routes every invocation through the
 * orders.search capability. Note what the schema does NOT offer — no customer
 * or email argument. Whose orders are searched is not the model's to say; the
 * capability resolves that from the trusted context (verdict#251).
 */
final class SearchOrdersTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Search the customer\'s own orders by status and/or product name. Returns every matching order.';
    }

    public function handle(Request $request): Stringable|string
    {
        throw new LogicException('The Verdict-bound tool handles every invocation; this definition never executes.');
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('Optional order status: paid, shipped, or delivered.'),
            'product' => $schema->string()->description('Optional product name (or part of one) the order must contain, for example "Desk Lamp".'),
        ];
    }
}

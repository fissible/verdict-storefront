<?php

declare(strict_types=1);

namespace App\Replay;

use Generator;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Gateway\StepTextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Streaming\Events\StreamEvent;
use LogicException;

/**
 * The default mode's model substitute (#237 design: replay by default, live
 * by opt-in): drives the REAL laravel/ai pipeline from recorded proposal
 * steps. Only this class stands in for the model — Verdict evaluation, the
 * execution-target refresh, the confirmation pause, the approval round-trip,
 * and every evidence row are live.
 *
 * Deliberately an app-owned StepTextGateway rather than Agent::fake(): a
 * faked agent never resumes tools, so it cannot demonstrate the approval
 * matrix (verdict#233/#235). Same substitution those verification tests use.
 *
 * It decides what to return from the incoming messages, not a step counter:
 * each dispatch is its own generation loop starting at step 0, and on a
 * resume the pending call has already been replayed from history.
 */
final readonly class ReplayGateway implements StepTextGateway
{
    public function __construct(private ReplayScripts $scripts) {}

    /**
     * @param  Message[]  $messages
     * @param  Tool[]  $tools
     * @param  array<string, Type>|null  $schema
     */
    public function generateTextStep(
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
        ?int $timeout,
        StepContext $stepContext,
    ): StepResponse {
        $script = $this->scripts->find($this->lastUserPrompt($messages));

        if ($script === null) {
            return $this->respond(
                'This demo is running in replay mode — no model is live, and there is no recording '
                .'for that request. Try one of the canned prompts, or set DEMO_MODE=live with a '
                .'configured provider to talk to a real model.',
            );
        }

        $step = $script->steps[$this->stepIndex($messages)] ?? end($script->steps);

        if (! empty($step['tool_calls'])) {
            return new StepResponse(
                text: $step['text'] ?? '',
                toolCalls: array_map(
                    fn (array $call): ToolCall => new ToolCall($call['id'], $call['name'], $call['arguments']),
                    $step['tool_calls'],
                ),
                finishReason: FinishReason::ToolCalls,
                usage: new Usage,
                meta: $this->meta(),
            );
        }

        return $this->respond($step['text'] ?? '');
    }

    /**
     * @param  Message[]  $messages
     * @param  Tool[]  $tools
     * @param  array<string, Type>|null  $schema
     * @return Generator<int, StreamEvent, mixed, StepResponse|null>
     */
    public function generateStreamStep(
        string $invocationId,
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
        ?int $timeout,
        StepContext $stepContext,
    ): Generator {
        throw new LogicException('Replay mode supports synchronous prompts only.');
    }

    /**
     * By role, not instanceof UserMessage: a resume rehydrates history from
     * the durable conversation store, and those messages are not necessarily
     * UserMessage instances.
     *
     * @param  Message[]  $messages
     */
    private function lastUserPrompt(array $messages): string
    {
        foreach (array_reverse($messages) as $message) {
            if ($message->role === MessageRole::User && filled($message->content)) {
                return (string) $message->content;
            }
        }

        return '';
    }

    /**
     * How many recorded steps this conversation has already consumed: each
     * assistant turn that carried tool calls advanced the script by one.
     *
     * @param  Message[]  $messages
     */
    private function stepIndex(array $messages): int
    {
        $consumed = 0;

        foreach ($messages as $message) {
            if ($message instanceof AssistantMessage && $message->toolCalls->isNotEmpty()) {
                $consumed++;
            }
        }

        return $consumed;
    }

    private function respond(string $text): StepResponse
    {
        return new StepResponse(
            text: $text,
            toolCalls: [],
            finishReason: FinishReason::Stop,
            usage: new Usage,
            meta: $this->meta(),
        );
    }

    private function meta(): Meta
    {
        // Honest meta: no provider served this step.
        return new Meta('replay', 'recorded-proposals');
    }
}

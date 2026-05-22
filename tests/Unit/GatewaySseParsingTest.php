<?php

declare(strict_types=1);

namespace AIGateway\Tests\Unit;

use AIGateway\Core\Gateway;
use PHPUnit\Framework\TestCase;

final class GatewaySseParsingTest extends TestCase
{
    public function test_non_data_line_returns_defaults(): void
    {
        $result = $this->invokeExtractSSE(": heartbeat\n\n");
        self::assertNull($result['id']);
        self::assertNull($result['usage']);
    }

    public function test_done_signal_returns_defaults(): void
    {
        $result = $this->invokeExtractSSE("data: [DONE]\n\n");
        self::assertNull($result['id']);
        self::assertNull($result['usage']);
    }

    public function test_parses_usage_from_sse_data(): void
    {
        $chunk = 'data: {"id":"chatcmpl-abc","model":"deepseek-v4-flash","usage":{"prompt_tokens":10,"completion_tokens":5,"total_tokens":15}}';
        $result = $this->invokeExtractSSE($chunk);

        self::assertSame('chatcmpl-abc', $result['id']);
        self::assertSame('deepseek-v4-flash', $result['model']);
        self::assertNotNull($result['usage']);
        self::assertSame(10, $result['usage']->promptTokens);
        self::assertSame(5, $result['usage']->completionTokens);
        self::assertSame(15, $result['usage']->totalTokens);
    }

    public function test_handles_multiple_events_in_one_chunk(): void
    {
        $chunk = "data: {\"id\":\"first\"}\n\ndata: {\"id\":\"last\",\"usage\":{\"prompt_tokens\":5,\"completion_tokens\":3,\"total_tokens\":8}}\n\n";
        $result = $this->invokeExtractSSE($chunk);
        self::assertSame('last', $result['id']);
        self::assertSame(5, $result['usage']->promptTokens);
    }

    public function test_skips_partial_json(): void
    {
        $chunk = 'data: {"incomplete": true';
        $result = $this->invokeExtractSSE($chunk);
        self::assertNull($result['id']);
        self::assertNull($result['usage']);
    }

    private function invokeExtractSSE(string $chunk): array
    {
        $ref = new \ReflectionMethod(Gateway::class, 'extractStreamMetadataFromSseChunk');
        $ref->setAccessible(true);
        $gateway = new Gateway(
            modelRegistry: new \AIGateway\Routing\ModelRegistry([]),
            providers: [],
        );
        return $ref->invoke($gateway, $chunk);
    }
}

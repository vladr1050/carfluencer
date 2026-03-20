<?php

namespace Tests\Feature;

use App\Models\ContentBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentBlocksApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_active_content_blocks(): void
    {
        ContentBlock::query()->create([
            'key' => 'notice_home',
            'title' => 'Notice',
            'body' => 'Hello',
            'active' => true,
        ]);
        ContentBlock::query()->create([
            'key' => 'draft_block',
            'title' => 'Draft',
            'body' => 'Hidden',
            'active' => false,
        ]);

        $response = $this->getJson('/api/content-blocks');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'notice_home');
    }

    public function test_filters_by_keys_query_parameter(): void
    {
        ContentBlock::query()->create([
            'key' => 'a',
            'title' => 'A',
            'body' => '1',
            'active' => true,
        ]);
        ContentBlock::query()->create([
            'key' => 'b',
            'title' => 'B',
            'body' => '2',
            'active' => true,
        ]);

        $response = $this->getJson('/api/content-blocks?keys=b');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'b');
    }
}

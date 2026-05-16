<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_send_message_and_event_is_dispatched()
    {
        Event::fake([MessageSent::class]);

        $user = User::factory()->create();
        $chatId = $this->createRoomFor($user->id);

        $response = $this->actingAs($user)->postJson("/api/chats/{$chatId}/messages", [
            'body' => '<script>alert(1)</script>',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message.body', '<script>alert(1)</script>');

        $this->assertDatabaseHas('messages', [
            'message' => '<script>alert(1)</script>',
        ]);
        Event::assertDispatched(MessageSent::class);
    }

    public function test_non_member_cannot_read_or_send_messages()
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $chatId = $this->createRoomFor($owner->id);

        $this->actingAs($outsider)
            ->getJson("/api/chats/{$chatId}/messages")
            ->assertForbidden();

        $this->actingAs($outsider)
            ->postJson("/api/chats/{$chatId}/messages", ['body' => 'nope'])
            ->assertForbidden();
    }

    public function test_empty_messages_are_rejected()
    {
        $user = User::factory()->create();
        $chatId = $this->createRoomFor($user->id);

        $this->actingAs($user)
            ->postJson("/api/chats/{$chatId}/messages", ['body' => ''])
            ->assertStatus(422);
    }

    public function test_delete_is_not_a_magic_history_delete()
    {
        $user = User::factory()->create();
        $chatId = $this->createRoomFor($user->id);

        $this->actingAs($user)
            ->postJson("/api/chats/{$chatId}/messages", ['body' => 'delete'])
            ->assertCreated();

        $this->assertDatabaseHas('messages', [
            'message' => 'delete',
        ]);
    }

    private function createRoomFor(int $userId): int
    {
        $chatId = DB::table('chats')->insertGetId([
            'name' => 'Test room ' . uniqid(),
            'type' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('chat_user')->insert([
            'chat_id' => $chatId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $chatId;
    }
}

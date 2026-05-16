<?php

namespace App\Jobs;

use App\Events\MessageSent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GenerateAssistantReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $chatId;
    private int $requestingUserId;

    public function __construct(int $chatId, int $requestingUserId)
    {
        $this->chatId = $chatId;
        $this->requestingUserId = $requestingUserId;
    }

    public function handle(): void
    {
        $assistant = $this->assistantUser();
        $this->attachUser($this->chatId, (int) $assistant->id);

        $body = $this->buildSafeReply();
        $chatUserId = $this->chatUserId($this->chatId, (int) $assistant->id);

        $messageId = DB::table('messages')->insertGetId([
            'chat_user_id' => $chatUserId,
            'message' => $body,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        broadcast(new MessageSent($this->messagePayload($messageId)));
    }

    private function buildSafeReply(): string
    {
        $latestUserMessage = DB::table('messages as m')
            ->join('chat_user as cu', 'm.chat_user_id', '=', 'cu.id')
            ->where('cu.chat_id', $this->chatId)
            ->where('cu.user_id', $this->requestingUserId)
            ->orderByDesc('m.created_at')
            ->value('m.message');

        if (! $latestUserMessage) {
            return 'I am here and ready to help in this chat.';
        }

        return 'I saw your message. AI provider wiring is intentionally stubbed for now, so I can acknowledge requests without exposing secrets or tools. Next step: connect a provider behind this queue job with a strict prompt-injection policy.';
    }

    private function assistantUser(): User
    {
        $assistant = User::firstOrCreate(
            ['name' => 'assistant'],
            [
                'full_name' => 'AI Assistant',
                'password' => Hash::make(Str::random(40)),
                'img_path' => 'https://bootdey.com/img/Content/avatar/avatar6.png',
                'type' => 'assistant',
            ]
        );

        if ($assistant->type !== 'assistant') {
            $assistant->update(['type' => 'assistant']);
        }

        return $assistant;
    }

    private function attachUser(int $chatId, int $userId): void
    {
        $exists = DB::table('chat_user')
            ->where('chat_id', $chatId)
            ->where('user_id', $userId)
            ->exists();

        if (! $exists) {
            DB::table('chat_user')->insert([
                'chat_id' => $chatId,
                'user_id' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    private function chatUserId(int $chatId, int $userId): int
    {
        return (int) DB::table('chat_user')
            ->where('chat_id', $chatId)
            ->where('user_id', $userId)
            ->value('id');
    }

    private function messagePayload(int $messageId): array
    {
        $message = DB::table('messages as m')
            ->join('chat_user as cu', 'm.chat_user_id', '=', 'cu.id')
            ->join('users as us', 'cu.user_id', '=', 'us.id')
            ->where('m.id', $messageId)
            ->select(
                'm.id',
                'm.message',
                'm.created_at',
                'cu.chat_id',
                'us.id as user_id',
                'us.name',
                'us.img_path',
                'us.type'
            )
            ->first();

        return [
            'id' => (int) $message->id,
            'chat_id' => (int) $message->chat_id,
            'user_id' => (int) $message->user_id,
            'user_name' => $message->name,
            'user_type' => $message->type ?: 'human',
            'user_avatar_url' => $this->avatarUrl($message->user_id, $message->img_path),
            'body' => $message->message,
            'created_at' => Carbon::parse($message->created_at)->toIso8601String(),
        ];
    }

    private function avatarUrl($userId, ?string $path): string
    {
        if (! $path) {
            return 'https://bootdey.com/img/Content/avatar/avatar6.png';
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return asset('storage/img_paths/' . $userId . '/' . $path);
    }
}

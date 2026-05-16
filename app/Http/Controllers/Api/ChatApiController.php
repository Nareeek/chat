<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateAssistantReply;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ChatApiController extends Controller
{
    public function index(): JsonResponse
    {
        $this->ensurePredefinedRooms();

        $userId = Auth::id();

        $rooms = DB::table('chats as ch')
            ->join('chat_user as cu', 'ch.id', '=', 'cu.chat_id')
            ->where('cu.user_id', $userId)
            ->where('ch.type', false)
            ->select('ch.id', 'ch.name', 'ch.created_at')
            ->orderBy('ch.name')
            ->get()
            ->map(function ($room) {
                return [
                    'id' => (int) $room->id,
                    'name' => $room->name,
                    'created_at' => $this->formatDate($room->created_at),
                ];
            });

        $directChats = DB::table('chats as ch')
            ->join('chat_user as mine', 'ch.id', '=', 'mine.chat_id')
            ->join('chat_user as other_link', 'ch.id', '=', 'other_link.chat_id')
            ->join('users as other', 'other.id', '=', 'other_link.user_id')
            ->where('ch.type', true)
            ->where('mine.user_id', $userId)
            ->where('other_link.user_id', '<>', $userId)
            ->select(
                'ch.id as chat_id',
                'ch.name as chat_name',
                'other.id as user_id',
                'other.name',
                'other.full_name',
                'other.img_path',
                'other.type',
                'ch.created_at'
            )
            ->orderBy('other.name')
            ->get()
            ->map(function ($chat) {
                return [
                    'id' => (int) $chat->chat_id,
                    'name' => $chat->chat_name,
                    'user_id' => (int) $chat->user_id,
                    'user_name' => $chat->name,
                    'full_name' => $chat->full_name,
                    'user_type' => $chat->type ?: 'human',
                    'avatar_url' => User::avatarUrlFor($chat->user_id, $chat->img_path),
                    'created_at' => $this->formatDate($chat->created_at),
                ];
            });

        return response()->json([
            'direct_chats' => $directChats,
            'rooms' => $rooms,
        ]);
    }

    public function messages(int $chat): JsonResponse
    {
        $this->authorizeChatMember($chat);

        return response()->json([
            'messages' => $this->chatMessages($chat),
        ]);
    }

    public function storeMessage(Request $request, int $chat): JsonResponse
    {
        $this->authorizeChatMember($chat);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $chatUserId = $this->chatUserId($chat, Auth::id());

        $messageId = DB::table('messages')->insertGetId([
            'chat_user_id' => $chatUserId,
            'message' => $data['body'],
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $payload = $this->messagePayload($messageId);
        broadcast(new MessageSent($payload))->toOthers();

        if ($this->shouldTriggerAssistant($data['body'], Auth::id(), $chat)) {
            GenerateAssistantReply::dispatch($chat, Auth::id());
        }

        return response()->json(['message' => $payload], 201);
    }

    public function createRoom(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('chats', 'name')],
        ]);

        $chatId = DB::transaction(function () use ($data) {
            $chatId = DB::table('chats')->insertGetId([
                'name' => $data['name'],
                'type' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            $this->attachUser($chatId, Auth::id());

            return $chatId;
        });

        return response()->json([
            'room' => $this->roomPayload($chatId),
        ], 201);
    }

    public function joinRoom(int $chat): JsonResponse
    {
        $room = DB::table('chats')->where('id', $chat)->where('type', false)->first();
        abort_unless($room, 404);

        $this->attachUser($chat, Auth::id());

        return response()->json([
            'room' => $this->roomPayload($chat),
            'messages' => $this->chatMessages($chat),
        ]);
    }

    public function createDirectChat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id', 'not_in:' . Auth::id()],
        ]);

        $chatId = $this->findDirectChat(Auth::id(), (int) $data['user_id']);

        if (! $chatId) {
            $friend = User::findOrFail($data['user_id']);
            $chatId = DB::transaction(function () use ($friend) {
                $chatId = DB::table('chats')->insertGetId([
                    'name' => Auth::user()->name . " and " . $friend->name . " chat",
                    'type' => true,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                $this->attachUser($chatId, Auth::id());
                $this->attachUser($chatId, $friend->id);

                return $chatId;
            });
        }

        return response()->json([
            'chat' => $this->directChatPayload($chatId, (int) $data['user_id']),
            'messages' => $this->chatMessages($chatId),
        ], 201);
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['nullable', 'string', 'max:80'],
        ]);

        $query = trim($data['query'] ?? '');
        $userId = Auth::id();

        $users = User::query()
            ->where('id', '<>', $userId)
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where('name', 'like', $query . '%');
            })
            ->select('id', 'name', 'full_name', 'img_path', 'type')
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => (int) $user->id,
                    'name' => $user->name,
                    'full_name' => $user->full_name,
                    'user_type' => $user->type ?: 'human',
                    'avatar_url' => $user->avatar_url,
                ];
            });

        $rooms = DB::table('chats')
            ->where('type', false)
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where('name', 'like', $query . '%');
            })
            ->select('id', 'name', 'created_at')
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(function ($room) {
                return [
                    'id' => (int) $room->id,
                    'name' => $room->name,
                    'created_at' => $this->formatDate($room->created_at),
                ];
            });

        return response()->json([
            'users' => $users,
            'rooms' => $rooms,
        ]);
    }

    public function inviteAssistant(int $chat): JsonResponse
    {
        $this->authorizeChatMember($chat);

        $assistant = $this->assistantUser();
        $this->attachUser($chat, $assistant->id);

        return response()->json([
            'assistant' => [
                'id' => (int) $assistant->id,
                'name' => $assistant->name,
                'avatar_url' => $assistant->avatar_url,
            ],
        ]);
    }

    private function ensurePredefinedRooms(): void
    {
        foreach (['General', 'Other'] as $name) {
            $chat = DB::table('chats')->where('name', $name)->where('type', false)->first();

            if (! $chat) {
                $chatId = DB::table('chats')->insertGetId([
                    'name' => $name,
                    'type' => false,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            } else {
                $chatId = $chat->id;
            }

            $this->attachUser((int) $chatId, Auth::id());
        }
    }

    private function authorizeChatMember(int $chatId): void
    {
        abort_unless($this->isChatMember($chatId, Auth::id()), 403);
    }

    private function isChatMember(int $chatId, int $userId): bool
    {
        return DB::table('chat_user')
            ->where('chat_id', $chatId)
            ->where('user_id', $userId)
            ->exists();
    }

    private function attachUser(int $chatId, int $userId): void
    {
        if (! $this->isChatMember($chatId, $userId)) {
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
        $chatUser = DB::table('chat_user')
            ->where('chat_id', $chatId)
            ->where('user_id', $userId)
            ->first();

        abort_unless($chatUser, 403);

        return (int) $chatUser->id;
    }

    private function chatMessages(int $chatId)
    {
        return DB::table('messages as m')
            ->join('chat_user as cu', 'm.chat_user_id', '=', 'cu.id')
            ->join('users as us', 'cu.user_id', '=', 'us.id')
            ->where('cu.chat_id', $chatId)
            ->select('m.id')
            ->orderBy('m.created_at')
            ->limit(200)
            ->get()
            ->map(function ($message) {
                return $this->messagePayload((int) $message->id);
            });
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

        abort_unless($message, 404);

        return [
            'id' => (int) $message->id,
            'chat_id' => (int) $message->chat_id,
            'user_id' => (int) $message->user_id,
            'user_name' => $message->name,
            'user_type' => $message->type ?: 'human',
            'user_avatar_url' => User::avatarUrlFor($message->user_id, $message->img_path),
            'body' => $message->message,
            'created_at' => $this->formatDate($message->created_at),
        ];
    }

    private function roomPayload(int $chatId): array
    {
        $room = DB::table('chats')->where('id', $chatId)->where('type', false)->first();
        abort_unless($room, 404);

        return [
            'id' => (int) $room->id,
            'name' => $room->name,
            'created_at' => $this->formatDate($room->created_at),
        ];
    }

    private function directChatPayload(int $chatId, int $friendId): array
    {
        $friend = User::findOrFail($friendId);

        return [
            'id' => $chatId,
            'name' => $friend->name,
            'user_id' => $friend->id,
            'user_name' => $friend->name,
            'full_name' => $friend->full_name,
            'user_type' => $friend->type ?: 'human',
            'avatar_url' => $friend->avatar_url,
        ];
    }

    private function findDirectChat(int $userId, int $friendId): ?int
    {
        $chat = DB::table('chats as ch')
            ->join('chat_user as mine', 'ch.id', '=', 'mine.chat_id')
            ->join('chat_user as friend', 'ch.id', '=', 'friend.chat_id')
            ->where('ch.type', true)
            ->where('mine.user_id', $userId)
            ->where('friend.user_id', $friendId)
            ->select('ch.id')
            ->first();

        return $chat ? (int) $chat->id : null;
    }

    private function shouldTriggerAssistant(string $body, int $senderId, int $chatId): bool
    {
        if (! filter_var(env('AI_ASSISTANT_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $mentionsAssistant = stripos($body, '@assistant') !== false;

        if (! $mentionsAssistant) {
            $assistantId = User::where('name', 'assistant')->value('id');

            return $assistantId
                && $senderId !== (int) $assistantId
                && $this->isChatMember($chatId, (int) $assistantId)
                && $this->findDirectChat($senderId, (int) $assistantId) === $chatId;
        }

        $assistant = $this->assistantUser();

        if ($senderId === (int) $assistant->id) {
            return false;
        }

        if (! $this->isChatMember($chatId, (int) $assistant->id)) {
            return true;
        }

        return true;
    }

    private function assistantUser(): User
    {
        $assistant = User::firstOrCreate(
            ['name' => 'assistant'],
            [
                'full_name' => 'AI Assistant',
                'password' => Hash::make(Str::random(40)),
                'img_path' => User::DEFAULT_AVATAR_URL,
                'type' => 'assistant',
            ]
        );

        if ($assistant->type !== 'assistant') {
            $assistant->update(['type' => 'assistant']);
        }

        return $assistant;
    }

    private function formatDate($date): string
    {
        return Carbon::parse($date)->toIso8601String();
    }
}

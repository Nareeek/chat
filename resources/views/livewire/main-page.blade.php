@php($currentUser = \Illuminate\Support\Facades\Auth::user())

<div
    class="chat-shell"
    id="chat-app"
    data-user-id="{{ $currentUser->id }}"
    data-user-name="{{ $currentUser->name }}"
    data-user-avatar-url="{{ $currentUser->avatar_url }}"
>
    <aside class="chat-sidebar">
        <div class="chat-toolbar">
            <input type="search" id="searching" class="search-bar" placeholder="Search users or rooms" autocomplete="off">
            <select id="ddlist" class="search-results" hidden></select>
        </div>

        <section class="chat-list-section">
            <div class="chat-section-heading">
                <h4>Direct chats</h4>
            </div>
            <div id="friend_list" class="chat-list-stack"></div>
        </section>

        <section class="chat-list-section">
            <div class="chat-section-heading">
                <h4>Rooms</h4>
                <button class="adding_room plus-button plus-button--small" type="button" aria-label="Create room"></button>
            </div>
            <div id="rooms_part" class="chat-list-stack"></div>
        </section>
    </aside>

    <section class="chat-panel">
        <div class="chat-panel-header">
            <h3 id="header">Choose a chat</h3>
            <button type="button" id="invite_assistant" class="assistant_btn" hidden>Invite assistant</button>
        </div>

        <div class="room-create" id="room_create" hidden>
            <input type="text" class="room_name" placeholder="Room name" maxlength="80">
            <button type="button" class="new_room_name_btn">Create</button>
        </div>

        <div class="msg_history" aria-live="polite">
            <p class="empty_state">Select a user or room to start messaging.</p>
        </div>

        <form class="type_msg" id="message_form">
            <input type="text" class="write_msg" placeholder="Type a message" maxlength="4000" autocomplete="off" disabled>
            <button class="msg_send_btn" type="submit" id="send_message" disabled aria-label="Send message">
                Send
            </button>
        </form>
    </section>

    <script defer src="{{ asset('js/chat_scripts.js') }}"></script>
</div>

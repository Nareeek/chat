(function () {
    const app = document.getElementById('chat-app');

    if (!app) {
        return;
    }

    const state = {
        myId: Number(app.dataset.userId),
        myName: app.dataset.userName || 'You',
        myAvatarUrl: app.dataset.userAvatarUrl || 'https://bootdey.com/img/Content/avatar/avatar6.png',
        currentChatId: null,
        currentChannel: null,
        messages: new Set(),
        searchTimer: null,
        nextClientMessageId: 1,
    };

    const elements = {
        search: document.getElementById('searching'),
        searchResults: document.getElementById('ddlist'),
        directList: document.getElementById('friend_list'),
        roomList: document.getElementById('rooms_part'),
        header: document.getElementById('header'),
        history: document.querySelector('.msg_history'),
        form: document.getElementById('message_form'),
        input: document.querySelector('.write_msg'),
        send: document.getElementById('send_message'),
        roomCreate: document.getElementById('room_create'),
        roomInput: document.querySelector('.room_name'),
        roomButton: document.querySelector('.new_room_name_btn'),
        addRoom: document.querySelector('.adding_room'),
        inviteAssistant: document.getElementById('invite_assistant'),
    };

    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function socketHeaders() {
        const socketId = window.Echo && typeof window.Echo.socketId === 'function'
            ? window.Echo.socketId()
            : null;

        return socketId ? { 'X-Socket-ID': socketId } : {};
    }

    function api(path, options = {}) {
        return fetch(path, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                ...socketHeaders(),
                ...(options.headers || {}),
            },
            ...options,
        }).then(async response => {
            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                const message = data.message || 'Request failed';
                throw new Error(message);
            }

            return data;
        });
    }

    function setEmptyState(message) {
        elements.history.replaceChildren();
        const empty = document.createElement('p');
        empty.className = 'empty_state';
        empty.textContent = message;
        elements.history.appendChild(empty);
    }

    function avatar(src, alt) {
        const img = document.createElement('img');
        img.alt = alt;
        img.src = src || 'https://bootdey.com/img/Content/avatar/avatar6.png';
        img.decoding = 'async';
        img.loading = 'lazy';
        return img;
    }

    function displayDate(message, status) {
        return status === 'sending' ? 'sending...' : new Date(message.created_at).toLocaleString();
    }

    function applyMessage(list, message, status) {
        const item = list.querySelector('li');
        const img = list.querySelector('.message-img img');
        const name = list.querySelector('.name');
        const text = list.querySelector('.text');
        const date = list.querySelector('.date');
        const id = Number(message.id);

        if (id) {
            state.messages.add(id);
            list.dataset.messageId = String(id);
        }

        item.className = Number(message.user_id) === state.myId ? 'out' : 'in';

        if (status) {
            item.classList.add(status);
        }

        img.alt = message.user_name || 'Avatar';
        img.src = message.user_avatar_url || 'https://bootdey.com/img/Content/avatar/avatar6.png';
        name.textContent = message.user_name || 'You';
        text.textContent = message.body;
        date.textContent = displayDate(message, status);
    }

    function chatRow(label, detail, imageUrl, onClick, className, chatId) {
        const row = document.createElement('button');
        row.type = 'button';
        row.className = className;
        row.dataset.chatId = chatId;
        row.addEventListener('click', onClick);

        const image = document.createElement('span');
        image.className = 'chat_img';
        image.appendChild(avatar(imageUrl, label));

        const text = document.createElement('span');
        text.className = 'chat_ib';

        const title = document.createElement('strong');
        title.textContent = label;
        text.appendChild(title);

        if (detail) {
            const meta = document.createElement('small');
            meta.textContent = detail;
            text.appendChild(meta);
        }

        row.append(image, text);
        return row;
    }

    function renderChatLists(data) {
        elements.directList.replaceChildren();
        elements.roomList.replaceChildren();

        data.direct_chats.forEach(chat => {
            elements.directList.appendChild(chatRow(
                chat.user_name,
                chat.full_name || chat.name,
                chat.avatar_url,
                () => openChat(chat.id, chat.user_name),
                'chat_list',
                chat.id
            ));
        });

        data.rooms.forEach(room => {
            elements.roomList.appendChild(chatRow(
                room.name,
                'Room',
                'https://cdn.iconscout.com/icon/premium/png-256-thumb/chat-room-3-1058983.png',
                () => openChat(room.id, room.name),
                'room_list',
                room.id
            ));
        });
    }

    function appendMessage(message, status) {
        const id = Number(message.id);
        const clientId = message.client_id;

        if (clientId) {
            const pending = Array.from(elements.history.querySelectorAll('[data-client-message-id]'))
                .find(list => list.dataset.clientMessageId === clientId);

            if (pending) {
                applyMessage(pending, message, status);
                elements.history.scrollTop = elements.history.scrollHeight;
                return pending;
            }
        }

        if (id && state.messages.has(id)) {
            return;
        }

        if (id) {
            state.messages.add(id);
        }

        elements.history.querySelectorAll('.empty_state').forEach(empty => empty.remove());

        const list = document.createElement('ul');
        list.className = 'message-list';

        if (message.client_id) {
            list.dataset.clientMessageId = message.client_id;
        }

        const item = document.createElement('li');
        item.className = Number(message.user_id) === state.myId ? 'out' : 'in';

        if (status) {
            item.classList.add(status);
        }

        const image = document.createElement('div');
        image.className = 'message-img';
        image.appendChild(avatar(message.user_avatar_url, message.user_name || 'Avatar'));

        const body = document.createElement('div');
        body.className = 'message-body';

        const bubble = document.createElement('div');
        bubble.className = 'chat-message';

        const name = document.createElement('h5');
        name.className = 'name';
        name.textContent = message.user_name || 'You';

        const text = document.createElement('p');
        text.className = 'text';
        text.textContent = message.body;

        const date = document.createElement('p');
        date.className = 'date';
        date.textContent = status === 'sending' ? 'sending...' : new Date(message.created_at).toLocaleString();

        bubble.append(name, text, date);
        body.appendChild(bubble);
        item.append(image, body);
        list.appendChild(item);
        applyMessage(list, message, status);
        elements.history.appendChild(list);
        elements.history.scrollTop = elements.history.scrollHeight;

        return list;
    }

    function enableComposer(enabled) {
        elements.input.disabled = !enabled;
        elements.send.disabled = !enabled || elements.input.value.trim() === '';
        elements.inviteAssistant.hidden = !enabled;
    }

    function markActive(chatId) {
        document.querySelectorAll('.chat_list, .room_list').forEach(row => {
            if (Number(row.dataset.chatId) === Number(chatId)) {
                row.classList.add('active_chat', 'active_messaging');
            } else {
                row.classList.remove('active_chat', 'active_messaging');
            }
        });
    }

    function subscribe(chatId) {
        if (!window.Echo) {
            return;
        }

        if (state.currentChannel) {
            window.Echo.leave(state.currentChannel);
        }

        state.currentChannel = 'chat.' + chatId;
        window.Echo.private(state.currentChannel)
            .listen('.message.sent', event => {
                if (Number(event.message.chat_id) === Number(state.currentChatId)) {
                    appendMessage(event.message);
                }
            });
    }

    function openChat(chatId, name) {
        state.currentChatId = chatId;
        state.messages = new Set();
        elements.header.textContent = name;
        markActive(chatId);
        elements.input.value = '';
        enableComposer(true);
        setEmptyState('Loading messages...');
        subscribe(chatId);

        api('/api/chats/' + chatId + '/messages')
            .then(data => {
                elements.history.replaceChildren();

                if (!data.messages.length) {
                    setEmptyState('No messages yet.');
                    return;
                }

                data.messages.forEach(message => appendMessage(message));
                markActive(chatId);
            })
            .catch(error => {
                enableComposer(false);
                setEmptyState(error.message);
            });
    }

    function loadChats() {
        api('/api/chats')
            .then(renderChatLists)
            .catch(error => setEmptyState(error.message));
    }

    function renderSearchResults(data) {
        elements.searchResults.replaceChildren();

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Select result';
        elements.searchResults.appendChild(placeholder);

        data.users.forEach(user => {
            const option = document.createElement('option');
            option.value = 'user:' + user.id;
            option.textContent = 'User: ' + user.name;
            elements.searchResults.appendChild(option);
        });

        data.rooms.forEach(room => {
            const option = document.createElement('option');
            option.value = 'room:' + room.id;
            option.textContent = 'Room: ' + room.name;
            elements.searchResults.appendChild(option);
        });

        elements.searchResults.hidden = data.users.length + data.rooms.length === 0;
    }

    elements.input.addEventListener('input', () => {
        elements.send.disabled = elements.input.value.trim() === '';
    });

    elements.form.addEventListener('submit', event => {
        event.preventDefault();

        const body = elements.input.value.trim();

        if (!state.currentChatId || body === '') {
            return;
        }

        elements.input.value = '';
        enableComposer(true);

        const clientId = 'pending-' + state.nextClientMessageId++;
        const pending = appendMessage({
            id: 0,
            client_id: clientId,
            chat_id: state.currentChatId,
            user_id: state.myId,
            user_name: state.myName,
            user_avatar_url: state.myAvatarUrl,
            body,
            created_at: new Date().toISOString(),
        }, 'sending');

        api('/api/chats/' + state.currentChatId + '/messages', {
            method: 'POST',
            body: JSON.stringify({ body, client_id: clientId }),
        })
            .then(data => {
                if (pending && pending.isConnected) {
                    applyMessage(pending, data.message);
                    elements.history.scrollTop = elements.history.scrollHeight;
                    return;
                }

                appendMessage(data.message);
            })
            .catch(error => {
                const item = pending ? pending.querySelector('li') : null;

                if (item) {
                    item.classList.remove('sending');
                    item.classList.add('failed');
                    const date = item.querySelector('.date');
                    if (date) {
                        date.textContent = error.message;
                    }
                }
            });
    });

    elements.search.addEventListener('input', () => {
        window.clearTimeout(state.searchTimer);
        state.searchTimer = window.setTimeout(() => {
            api('/api/search?query=' + encodeURIComponent(elements.search.value.trim()))
                .then(renderSearchResults)
                .catch(() => {
                    elements.searchResults.hidden = true;
                });
        }, 250);
    });

    elements.searchResults.addEventListener('change', () => {
        const [type, id] = elements.searchResults.value.split(':');

        if (!type || !id) {
            return;
        }

        const request = type === 'user'
            ? api('/api/direct-chats', { method: 'POST', body: JSON.stringify({ user_id: Number(id) }) })
            : api('/api/rooms/' + id + '/join', { method: 'POST' });

        request.then(data => {
            elements.search.value = '';
            elements.searchResults.hidden = true;
            loadChats();

            if (type === 'user') {
                openChat(data.chat.id, data.chat.user_name);
            } else {
                openChat(data.room.id, data.room.name);
            }
        }).catch(error => setEmptyState(error.message));
    });

    elements.addRoom.addEventListener('click', () => {
        elements.roomCreate.hidden = !elements.roomCreate.hidden;
        elements.roomInput.focus();
    });

    elements.roomButton.addEventListener('click', () => {
        const name = elements.roomInput.value.trim();

        if (!name) {
            return;
        }

        api('/api/rooms', {
            method: 'POST',
            body: JSON.stringify({ name }),
        }).then(data => {
            elements.roomInput.value = '';
            elements.roomCreate.hidden = true;
            loadChats();
            openChat(data.room.id, data.room.name);
        }).catch(error => setEmptyState(error.message));
    });

    elements.inviteAssistant.addEventListener('click', () => {
        if (!state.currentChatId) {
            return;
        }

        api('/api/chats/' + state.currentChatId + '/assistant', { method: 'POST' })
            .then(() => {
                elements.inviteAssistant.textContent = 'Assistant invited';
                window.setTimeout(() => {
                    elements.inviteAssistant.textContent = 'Invite assistant';
                }, 1500);
            })
            .catch(error => setEmptyState(error.message));
    });

    enableComposer(false);
    loadChats();
})();

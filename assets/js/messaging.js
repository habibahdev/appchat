class MessagingClient {
    constructor({ mercureUrl, conversationId = null }) {
        this.mercureUrl = mercureUrl;
        this.conversationId = conversationId;
        this.eventSource = null;
        this.heartbeatInterval = null;
    }

    async init() {
        await fetch('/mercure/auth', { credentials: 'include '});
        this.subscribe();
        this.startHeartbeat();
        window.addEventListener('beforeunload', () => this.goOffline());
    }

    subscribe() {
        const url = new URL(this.mercureUrl);
        url.searchParams.append('topic', `/users/${window.currentUserId}/notifications`);
        url.searchParams.append('topic', '/presence');
        if (this.conversationId) {
            url.searchParams.append('topic', `/conversations/${this.conversationId}`);
            url.searchParams.append('topic', `/conversations/${this.conversationId}/reads`);
        }

        this.eventSource = new EventSource(url, { withCredentials: true });
        this.eventSource.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.handleUpdate(data);
        };

        this.eventSource.onerror = () => {
            console.warn('Connexion mercure perdue, tentative de reconnexion');
        };
    }

    handleUpdate(data) {
        if (data.action === 'edit') {
            this.onMessageEdited?.(data);
        } else if (data.action === 'delete') {
            this.onMessageDeleted?.(data);
        } else if (data.messageId !== undefined && data.readBy !== undefined) {
            this.onMessageRead?.(data);
        } else if (data.userId !== undefined && data.isOnline !== undefined) {
            this.onPresenceChange?.(data);
        } else if (data.userId !== undefined && data.userName !== undefined && data.isOnline === undefined) {
            this.onTyping?.(data);
        } else if (data.type !== undefined) {
            this.onNotification?.(data);
        } else if (data.id !== undefined && data.senderId !== undefined) {
            this.onNewMessage?.(data);
        }
    }

    notifyTyping(conversationId) {
        const now = Date.now();
        if (this._lastTypingSent && now - this._lastTypingSent < 3000) {
            return;
        }
        this._lastTypingSent = now;

        fetch(`/conversations/${conversationId}/messages/typing`, {
            method: 'POST',
            credentials: 'include',
        });
    }

    startHeartbeat() {
        this.sendHeartbeat();
        this.heartbeatInterval = setInterval(() => this.sendHeartbeat(), 25000);
    }

    sendHeartbeat() {
        fetch('/presence/heartbeat', { method: 'POST', credentials: 'include' });
    }

    goOffline() {
        clearInterval(this.heartbeatInterval);
        navigator.sendBeacon('/presence/offline');
    }

    async sendMessage(conversationId, { content, files = []}) {
        const formData = new FormData();
        if (content) formData.append('content', content);
        files.forEach((file) => formData.append('attachments[]', file));
        const response = await fetch(`/conversations/${conversationId}/messages`, {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        return response.json();
    }

    async editMessage(conversationId, messageId, content) {
        const formData = new FormData();
        formData.append('content', content);

        return fetch(`/conversations/${conversationId}/messages/${messageId}/edit`, {
            method: 'POST',
            body: formData,
            credentials: 'include',
        }).then((r) => r.json());
    }

    async deleteMessage(conversationId, messageId) {
        return fetch(`/conversations/${conversationId}/messages/${messageId}`, {
            method: 'DELETE',
            credentials: 'include',
        }).then((r) => r.json());
    }

    markMessageRead(conversationId, messageId) {
        return fetch(`/conversations/${conversationId}/messages/${messageId}/read`, {
            method: 'POST',
            credentials: 'include'
        });
    }
}

export default MessagingClient;
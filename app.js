// ============================================
// BLITZ v1.2 - JavaScript principal
// ============================================

console.log('✅ Blitz v1.2 chargé');

// État global
const AppState = {
    userId: null,
    pseudo: null,
    sessionId: null,
    csrfToken: null,
    isTemporary: false,
    isAdmin: false,
    bio: '',
    profilePic: '',
    emojiAvatar: '',
    currentChannel: 'general',
    currentView: 'chat',
    lastMessageId: 0,
    lastDmId: {},
    sse: {
        messages: null,
        dms: null
    },
    intervals: {
        users: null,
        heartbeat: null,
        dmPolling: null
    },
    unreadDMs: {},
    messagesCache: {
        general: [],
        dms: {}
    }
};

// ============================================
// SYSTÈME DE TOAST (remplace alert)
// ============================================
const Toast = {
    container: null,
    
    init() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'toastContainer';
            document.body.appendChild(this.container);
        }
    },
    
    show(message, type = 'info', duration = 4000) {
        this.init();
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        const icons = {
            success: '✓',
            error: '✗',
            warning: '⚠',
            info: 'ℹ'
        };
        
        toast.innerHTML = `
            <div class="toast-icon">${icons[type] || icons.info}</div>
            <div class="toast-content">
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close">×</button>
        `;
        
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => {
            toast.style.animation = 'fadeOut 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        });
        
        toast.addEventListener('click', (e) => {
            if (e.target !== closeBtn) {
                toast.style.animation = 'fadeOut 0.3s ease-out';
                setTimeout(() => toast.remove(), 300);
            }
        });
        
        this.container.appendChild(toast);
        
        if (duration > 0) {
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.style.animation = 'fadeOut 0.3s ease-out';
                    setTimeout(() => toast.remove(), 300);
                }
            }, duration);
        }
    },
    
    success(message) {
        this.show(message, 'success');
    },
    
    error(message) {
        this.show(message, 'error');
    },
    
    warning(message) {
        this.show(message, 'warning');
    },
    
    info(message) {
        this.show(message, 'info');
    }
};

// ============================================
// UTILITAIRES
// ============================================
const Utils = {
    generateColor(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        return `hsl(${hash % 360}, 65%, 50%)`;
    },

    createPfp(pseudo, size = 40, profilePic = '', emojiAvatar = '') {
        const pfp = document.createElement('div');
        pfp.className = 'pfp';
        pfp.style.width = `${size}px`;
        pfp.style.height = `${size}px`;

        // Priorité : emoji > photo > initiale
        if (emojiAvatar && emojiAvatar !== '') {
            pfp.style.fontSize = `${size * 0.6}px`;
            pfp.textContent = emojiAvatar;
            pfp.style.backgroundColor = 'transparent';
        } else if (profilePic && profilePic !== '') {
            pfp.style.backgroundImage = `url(${profilePic})`;
            pfp.style.backgroundSize = 'cover';
            pfp.style.backgroundPosition = 'center';
        } else {
            pfp.style.fontSize = `${size * 0.4}px`;
            pfp.style.backgroundColor = this.generateColor(pseudo);
            pfp.textContent = pseudo.charAt(0).toUpperCase();
        }

        return pfp;
    },

    formatTime(timestamp) {
        const date = new Date(timestamp * 1000);
        return `${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
    },

    formatDate(timestamp) {
        const date = new Date(timestamp * 1000);
        const now = new Date();
        const diffTime = Math.abs(now - date);
        const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays === 0) return 'Aujourd\'hui';
        if (diffDays === 1) return 'Hier';
        if (diffDays < 7) return `${diffDays} jours`;
        if (diffDays < 30) return `${Math.floor(diffDays / 7)} semaines`;
        if (diffDays < 365) return `${Math.floor(diffDays / 30)} mois`;
        return `${Math.floor(diffDays / 365)} ans`;
    },

    async requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            await Notification.requestPermission();
        }
    },

    showNotification(title, body) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(title, { body, icon: '/favicon.ico' });
        }
    },
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    parseMarkdown(text) {
        let html = this.escapeHtml(text);
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
        html = html.replace(/`(.*?)`/g, '<code>$1</code>');
        html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>');
        html = html.replace(/^&gt; (.+)$/gm, '<blockquote>$1</blockquote>');
        return html;
    }
};

// ============================================
// AUTHENTIFICATION
// ============================================
const Auth = {
    init() {
        console.log('🔐 Init auth...');
        
        const persistentBtn = document.getElementById('persistentBtn');
        const temporaryBtn = document.getElementById('temporaryBtn');
        
        if (!persistentBtn || !temporaryBtn) {
            console.error('❌ Boutons introuvables');
            return;
        }
        
        persistentBtn.addEventListener('click', () => {
            document.getElementById('accountTypeChoice').style.display = 'none';
            document.getElementById('registerForm').style.display = 'block';
            document.getElementById('backToChoice').style.display = 'block';
        });
        
        temporaryBtn.addEventListener('click', () => {
            document.getElementById('accountTypeChoice').style.display = 'none';
            document.getElementById('tempForm').style.display = 'block';
            document.getElementById('backToChoice').style.display = 'block';
        });
        
        document.getElementById('showLoginLink').addEventListener('click', () => {
            document.getElementById('registerForm').style.display = 'none';
            document.getElementById('loginForm').style.display = 'block';
        });
        
        document.getElementById('showRegisterLink').addEventListener('click', () => {
            document.getElementById('loginForm').style.display = 'none';
            document.getElementById('registerForm').style.display = 'block';
        });
        
        document.getElementById('backToChoice').addEventListener('click', () => {
            document.getElementById('accountTypeChoice').style.display = 'grid';
            document.getElementById('registerForm').style.display = 'none';
            document.getElementById('loginForm').style.display = 'none';
            document.getElementById('tempForm').style.display = 'none';
            document.getElementById('backToChoice').style.display = 'none';
        });
        
        document.getElementById('registerBtn').addEventListener('click', () => this.register());
        document.getElementById('loginBtn').addEventListener('click', () => this.login());
        document.getElementById('tempBtn').addEventListener('click', () => this.createTemporary());
        
        ['registerPassword', 'loginPassword', 'tempPseudo'].forEach(id => {
            document.getElementById(id).addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    if (id === 'registerPassword') this.register();
                    else if (id === 'loginPassword') this.login();
                    else this.createTemporary();
                }
            });
        });
    },

    async register() {
        const pseudo = document.getElementById('registerPseudo').value.trim();
        const password = document.getElementById('registerPassword').value;
        const errorEl = document.getElementById('registerError');
        
        errorEl.textContent = '';
        
        if (!pseudo || pseudo.length < 2 || pseudo.length > 20) {
            errorEl.textContent = 'Pseudo invalide (2-20 caractères)';
            return;
        }
        
        if (!password || password.length < 6) {
            errorEl.textContent = 'Mot de passe trop court (min 6 caractères)';
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'register');
        formData.append('pseudo', pseudo);
        formData.append('password', password);
        formData.append('is_temporary', '0');
        
        try {
            const response = await fetch('auth.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                this.onAuthSuccess(data);
                Toast.success('Compte créé avec succès !');
            } else {
                errorEl.textContent = data.error || 'Erreur d\'inscription';
            }
        } catch (error) {
            errorEl.textContent = 'Erreur de connexion au serveur';
        }
    },

    async login() {
        const pseudo = document.getElementById('loginPseudo').value.trim();
        const password = document.getElementById('loginPassword').value;
        const errorEl = document.getElementById('loginError');
        
        errorEl.textContent = '';
        
        if (!pseudo || !password) {
            errorEl.textContent = 'Pseudo et mot de passe requis';
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'login');
        formData.append('pseudo', pseudo);
        formData.append('password', password);
        
        try {
            const response = await fetch('auth.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                this.onAuthSuccess(data);
                Toast.success('Connexion réussie !');
            } else {
                errorEl.textContent = data.error || 'Erreur de connexion';
            }
        } catch (error) {
            errorEl.textContent = 'Erreur de connexion au serveur';
        }
    },

    async createTemporary() {
        const pseudo = document.getElementById('tempPseudo').value.trim();
        const errorEl = document.getElementById('tempError');
        
        errorEl.textContent = '';
        
        if (!pseudo || pseudo.length < 2 || pseudo.length > 20) {
            errorEl.textContent = 'Pseudo invalide (2-20 caractères)';
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'register');
        formData.append('pseudo', pseudo);
        formData.append('is_temporary', '1');
        
        try {
            const response = await fetch('auth.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                this.onAuthSuccess(data);
                Toast.success('Compte temporaire créé !');
            } else {
                errorEl.textContent = data.error || 'Erreur de création';
            }
        } catch (error) {
            errorEl.textContent = 'Erreur de connexion au serveur';
        }
    },

    onAuthSuccess(data) {
        AppState.userId = data.user_id;
        AppState.pseudo = data.pseudo;
        AppState.sessionId = data.session_id;
        AppState.csrfToken = data.csrf_token;
        AppState.isTemporary = data.is_temporary === 1 || data.is_temporary === true;
        AppState.isAdmin = data.is_admin === 1 || data.is_admin === true;
        AppState.bio = data.bio || '';
        AppState.profilePic = data.profile_pic || '';
        AppState.emojiAvatar = data.emoji_avatar || '';

        document.getElementById('loginModal').style.display = 'none';
        document.getElementById('app').style.display = 'block';

        document.getElementById('currentUsername').textContent = AppState.pseudo;
        const currentUserPfp = document.getElementById('currentUserPfp');
        const pfp = Utils.createPfp(AppState.pseudo, 32, AppState.profilePic, AppState.emojiAvatar);
        currentUserPfp.replaceWith(pfp);
        pfp.id = 'currentUserPfp';

        Utils.requestNotificationPermission();

        App.start();
    }
};

// ============================================
// CHAT GÉNÉRAL
// ============================================
const Chat = {
    async sendMessage(text) {
        if (!text || text.trim().length === 0) return;

        // Affichage instantané (optimistic update)
        const tempMsg = {
            id: Date.now(),
            pseudo: AppState.pseudo,
            message: text.trim(),
            timestamp: Math.floor(Date.now() / 1000),
            profile_pic: AppState.profilePic,
            _temp: true
        };
        
        AppState.messagesCache.general.push(tempMsg);
        this.renderMessages();

        const formData = new FormData();
        formData.append('user_id', AppState.userId);
        formData.append('pseudo', AppState.pseudo);
        formData.append('message', text.trim());
        formData.append('csrf_token', AppState.csrfToken);

        try {
            const response = await fetch('send_message.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            // Remplacer le message temporaire par le vrai
            if (data.success) {
                const index = AppState.messagesCache.general.findIndex(m => m.id === tempMsg.id);
                if (index !== -1) {
                    AppState.messagesCache.general.splice(index, 1);
                }
            } else {
                // Supprimer le message temporaire en cas d'erreur
                const index = AppState.messagesCache.general.findIndex(m => m.id === tempMsg.id);
                if (index !== -1) {
                    AppState.messagesCache.general.splice(index, 1);
                    this.renderMessages();
                }
                Toast.error('Erreur d\'envoi du message');
            }
        } catch (error) {
            console.error('Erreur envoi:', error);
            // Supprimer le message temporaire
            const index = AppState.messagesCache.general.findIndex(m => m.id === tempMsg.id);
            if (index !== -1) {
                AppState.messagesCache.general.splice(index, 1);
                this.renderMessages();
            }
            Toast.error('Erreur d\'envoi du message');
        }
    },

    async loadInitialMessages() {
        try {
            const response = await fetch('get_messages.php?last_id=0');
            const data = await response.json();

            if (data.messages && data.messages.length > 0) {
                const lastMsg = data.messages[data.messages.length - 1];
                AppState.lastMessageId = lastMsg.id;
                
                data.messages.forEach(msg => {
                    if (!AppState.messagesCache.general.find(m => m.id === msg.id)) {
                        AppState.messagesCache.general.push(msg);
                    }
                });
                
                this.renderMessages();
            }
        } catch (error) {
            console.error('Erreur chargement:', error);
        }
    },

    startSSE() {
        if (AppState.sse.messages) {
            AppState.sse.messages.close();
        }

        const url = `sse_messages.php?last_id=${AppState.lastMessageId}`;
        AppState.sse.messages = new EventSource(url);

        AppState.sse.messages.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);

                if (data.messages && data.messages.length > 0) {
                    const lastMsg = data.messages[data.messages.length - 1];
                    AppState.lastMessageId = Math.max(AppState.lastMessageId, lastMsg.id);

                    data.messages.forEach(msg => {
                        if (!AppState.messagesCache.general.find(m => m.id === msg.id)) {
                            AppState.messagesCache.general.push(msg);
                        }
                    });

                    if (AppState.currentChannel === 'general') {
                        this.renderMessages();
                    }
                }
            } catch (error) {
                console.error('Erreur SSE:', error);
            }
        };

        AppState.sse.messages.onerror = () => {
            AppState.sse.messages.close();
            setTimeout(() => this.startSSE(), 2000);
        };
    },

    renderMessages() {
        const container = document.getElementById('messages');
        const wasAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 50;

        const messages = AppState.messagesCache.general.sort((a, b) => a.id - b.id);

        // Identifier les messages déjà affichés
        const existingMsgIds = new Set();
        container.querySelectorAll('.message[data-msg-id]').forEach(el => {
            existingMsgIds.add(parseInt(el.dataset.msgId));
        });

        // Ajouter seulement les nouveaux messages
        messages.forEach((msg, index) => {
            if (existingMsgIds.has(msg.id)) {
                return; // Message déjà affiché
            }

            const messageEl = document.createElement('div');
            messageEl.className = 'message';
            messageEl.dataset.msgId = msg.id;

            const prevMsg = index > 0 ? messages[index - 1] : null;
            let shouldCompress = false;

            if (prevMsg && prevMsg.pseudo === msg.pseudo && msg.timestamp - prevMsg.timestamp < 300) {
                shouldCompress = true;
            }

            if (shouldCompress) {
                messageEl.classList.add('message-compressed');
                const textEl = document.createElement('div');
                textEl.className = 'message-text-only';
                textEl.innerHTML = Utils.parseMarkdown(msg.message);

                const timeEl = document.createElement('span');
                timeEl.className = 'message-time-inline';
                timeEl.textContent = Utils.formatTime(msg.timestamp);

                textEl.appendChild(timeEl);
                messageEl.appendChild(textEl);
            } else {
                const pfp = Utils.createPfp(msg.pseudo, 40, msg.profile_pic || '');
                pfp.style.cursor = 'pointer';
                pfp.addEventListener('click', () => Profile.show(msg.pseudo));

                const contentEl = document.createElement('div');
                contentEl.className = 'message-content';

                const headerEl = document.createElement('div');
                headerEl.className = 'message-header';

                const authorEl = document.createElement('span');
                authorEl.className = 'message-author';
                authorEl.textContent = msg.pseudo;
                authorEl.style.cursor = 'pointer';
                authorEl.addEventListener('click', () => Profile.show(msg.pseudo));

                const timeEl = document.createElement('span');
                timeEl.className = 'message-time';
                timeEl.textContent = Utils.formatTime(msg.timestamp);

                headerEl.appendChild(authorEl);
                headerEl.appendChild(timeEl);

                const textEl = document.createElement('div');
                textEl.className = 'message-text';
                textEl.innerHTML = Utils.parseMarkdown(msg.message);

                contentEl.appendChild(headerEl);
                contentEl.appendChild(textEl);

                messageEl.appendChild(pfp);
                messageEl.appendChild(contentEl);
            }

            container.appendChild(messageEl);
        });

        if (wasAtBottom) {
            container.scrollTop = container.scrollHeight;
        }
    }
};

// ============================================
// MESSAGES PRIVÉS (CORRIGÉ)
// ============================================
const DM = {
    async openConversation(withUser) {
        console.log('💬 Ouverture DM avec:', withUser);
        
        // Changer le canal ET la vue
        AppState.currentChannel = `dm:${withUser}`;
        AppState.currentView = 'chat';
        
        // Mettre à jour le titre
        document.getElementById('chatTitle').textContent = `@ ${withUser}`;
        
        // Désactiver tous les items
        document.querySelectorAll('.channel-item, .dm-item').forEach(item => {
            item.classList.remove('active');
        });
        
        // Afficher la vue chat
        document.getElementById('chatView').style.display = 'flex';
        document.getElementById('forumView').style.display = 'none';
        document.getElementById('mypageView').style.display = 'none';
        
        // Créer/activer l'item DM
        let dmItem = document.querySelector(`.dm-item[data-user="${withUser}"]`);
        if (!dmItem) {
            dmItem = this.createDmItem(withUser);
            document.getElementById('dmsList').prepend(dmItem);
        }
        dmItem.classList.add('active');

        // Réinitialiser le compteur de non-lus et masquer le badge
        AppState.unreadDMs[withUser] = 0;
        const badge = dmItem.querySelector('.dm-badge');
        if (badge) {
            badge.style.display = 'none';
        }

        // Marquer les messages comme lus côté serveur
        this.markAsRead(withUser);

        // Initialiser le cache si nécessaire
        if (!AppState.messagesCache.dms[withUser]) {
            AppState.messagesCache.dms[withUser] = [];
            AppState.lastDmId[withUser] = 0;
        }

        // Charger les messages existants
        await this.loadMessages(withUser);

        console.log('✅ DM ouvert');
    },

    async markAsRead(fromUser) {
        try {
            const formData = new FormData();
            formData.append('from_user', fromUser);

            await fetch('mark_dm_read.php', {
                method: 'POST',
                body: formData
            });
        } catch (error) {
            console.error('Erreur marquage comme lu:', error);
        }
    },
    
    async loadMessages(withUser) {
        try {
            const response = await fetch(`get_dm.php?user1=${encodeURIComponent(AppState.pseudo)}&user2=${encodeURIComponent(withUser)}&last_id=0`);
            const data = await response.json();
            
            if (data.messages && data.messages.length > 0) {
                AppState.messagesCache.dms[withUser] = data.messages;
                const lastMsg = data.messages[data.messages.length - 1];
                AppState.lastDmId[withUser] = lastMsg.id;
            }
            
            this.renderMessages(withUser);
            
            // Démarrer le polling automatique pour ce DM
            this.startDMPolling(withUser);
        } catch (error) {
            console.error('Erreur chargement DMs:', error);
            Toast.error('Impossible de charger les messages');
        }
    },
    
    startDMPolling(withUser) {
        // Arrêter le polling précédent si existant
        if (AppState.intervals.dmPolling) {
            clearInterval(AppState.intervals.dmPolling);
        }
        
        // Polling toutes les 2 secondes
        AppState.intervals.dmPolling = setInterval(async () => {
            // Vérifier qu'on est toujours sur ce DM
            if (AppState.currentChannel !== `dm:${withUser}`) {
                clearInterval(AppState.intervals.dmPolling);
                return;
            }
            
            try {
                const lastId = AppState.lastDmId[withUser] || 0;
                const response = await fetch(`get_dm.php?user1=${encodeURIComponent(AppState.pseudo)}&user2=${encodeURIComponent(withUser)}&last_id=${lastId}`);
                const data = await response.json();
                
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        if (!AppState.messagesCache.dms[withUser].find(m => m.id === msg.id)) {
                            AppState.messagesCache.dms[withUser].push(msg);
                        }
                    });
                    
                    const lastMsg = data.messages[data.messages.length - 1];
                    AppState.lastDmId[withUser] = lastMsg.id;
                    
                    this.renderMessages(withUser);
                }
            } catch (error) {
                console.error('Erreur polling DM:', error);
            }
        }, 2000);
    },

    createDmItem(withUser) {
        const dmItem = document.createElement('div');
        dmItem.className = 'dm-item';
        dmItem.dataset.user = withUser;

        const pfp = Utils.createPfp(withUser, 32);

        const contentWrapper = document.createElement('div');
        contentWrapper.style.flex = '1';
        contentWrapper.style.display = 'flex';
        contentWrapper.style.flexDirection = 'column';

        const name = document.createElement('span');
        name.className = 'dm-item-name';
        name.textContent = withUser;

        const preview = document.createElement('span');
        preview.className = 'dm-preview';
        preview.textContent = '';
        preview.style.fontSize = '12px';
        preview.style.color = 'var(--text-tertiary)';
        preview.style.overflow = 'hidden';
        preview.style.textOverflow = 'ellipsis';
        preview.style.whiteSpace = 'nowrap';

        contentWrapper.appendChild(name);
        contentWrapper.appendChild(preview);

        const badge = document.createElement('span');
        badge.className = 'dm-badge';
        badge.style.display = 'none';
        badge.textContent = '0';

        dmItem.appendChild(pfp);
        dmItem.appendChild(contentWrapper);
        dmItem.appendChild(badge);

        dmItem.addEventListener('click', () => {
            this.openConversation(withUser);
            // Réinitialiser le compteur de non-lus
            AppState.unreadDMs[withUser] = 0;
            badge.style.display = 'none';
        });

        return dmItem;
    },
    
    renderMessages(withUser) {
        const container = document.getElementById('messages');

        const messages = AppState.messagesCache.dms[withUser] || [];

        if (messages.length === 0) {
            // Vider seulement si vraiment vide
            if (container.children.length > 0) {
                container.innerHTML = '';
            }
            const emptyEl = document.createElement('div');
            emptyEl.style.textAlign = 'center';
            emptyEl.style.color = 'var(--text-tertiary)';
            emptyEl.style.marginTop = '40px';
            emptyEl.textContent = 'Aucun message pour le moment';
            container.appendChild(emptyEl);
            return;
        }

        // Retirer le message "Aucun message" s'il existe
        const emptyState = container.querySelector('div[style*="textAlign"]');
        if (emptyState) {
            emptyState.remove();
        }

        // Identifier les messages déjà affichés
        const existingMsgIds = new Set();
        container.querySelectorAll('.message[data-msg-id]').forEach(el => {
            existingMsgIds.add(parseInt(el.dataset.msgId));
        });

        // Ajouter seulement les nouveaux messages
        messages.sort((a, b) => a.timestamp - b.timestamp).forEach(msg => {
            if (existingMsgIds.has(msg.id)) {
                return; // Message déjà affiché
            }

            const messageEl = document.createElement('div');
            messageEl.className = 'message';
            messageEl.dataset.msgId = msg.id;

            // Utiliser la photo de profil du message
            const pfp = Utils.createPfp(msg.from_user, 40, msg.profile_pic || '');
            pfp.style.cursor = 'pointer';
            pfp.addEventListener('click', () => Profile.show(msg.from_user));

            const contentEl = document.createElement('div');
            contentEl.className = 'message-content';

            const headerEl = document.createElement('div');
            headerEl.className = 'message-header';

            const authorEl = document.createElement('span');
            authorEl.className = 'message-author';
            authorEl.textContent = msg.from_user;
            authorEl.style.cursor = 'pointer';
            authorEl.addEventListener('click', () => Profile.show(msg.from_user));

            const timeEl = document.createElement('span');
            timeEl.className = 'message-time';
            timeEl.textContent = Utils.formatTime(msg.timestamp);

            headerEl.appendChild(authorEl);
            headerEl.appendChild(timeEl);

            const textEl = document.createElement('div');
            textEl.className = 'message-text';
            textEl.innerHTML = Utils.parseMarkdown(msg.message);

            contentEl.appendChild(headerEl);
            contentEl.appendChild(textEl);

            messageEl.appendChild(pfp);
            messageEl.appendChild(contentEl);

            container.appendChild(messageEl);
        });

        container.scrollTop = container.scrollHeight;
    },
    
    async sendMessage(to, text) {
        if (!text || text.trim().length === 0) return;

        // Affichage instantané
        const tempMsg = {
            id: Date.now(),
            from_user: AppState.pseudo,
            to_user: to,
            message: text.trim(),
            timestamp: Math.floor(Date.now() / 1000),
            _temp: true
        };
        
        if (!AppState.messagesCache.dms[to]) {
            AppState.messagesCache.dms[to] = [];
        }
        
        AppState.messagesCache.dms[to].push(tempMsg);
        this.renderMessages(to);

        const formData = new FormData();
        formData.append('from', AppState.pseudo);
        formData.append('to', to);
        formData.append('message', text.trim());

        try {
            const response = await fetch('send_dm.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                // Remplacer le message temporaire par le vrai
                const index = AppState.messagesCache.dms[to].findIndex(m => m.id === tempMsg.id);
                if (index !== -1) {
                    AppState.messagesCache.dms[to][index] = {
                        ...tempMsg,
                        id: data.id,
                        _temp: false
                    };
                }
            } else {
                // Supprimer en cas d'erreur
                const index = AppState.messagesCache.dms[to].findIndex(m => m.id === tempMsg.id);
                if (index !== -1) {
                    AppState.messagesCache.dms[to].splice(index, 1);
                    this.renderMessages(to);
                }
                Toast.error(data.error || 'Erreur d\'envoi');
                console.error('Erreur DM:', data.error);
            }
        } catch (error) {
            console.error('Erreur envoi DM:', error);
            // Supprimer le message temporaire
            const index = AppState.messagesCache.dms[to].findIndex(m => m.id === tempMsg.id);
            if (index !== -1) {
                AppState.messagesCache.dms[to].splice(index, 1);
                this.renderMessages(to);
            }
            Toast.error('Erreur d\'envoi du message');
        }
    },

    async checkAllDMs() {
        try {
            // Récupérer les DMs non lus pour tous les utilisateurs
            const response = await fetch('get_unread_dms.php');
            const data = await response.json();

            if (data.success && data.unread) {
                // Mettre à jour les badges et aperçus
                Object.keys(data.unread).forEach(fromUser => {
                    if (fromUser === AppState.pseudo) return;

                    const dmData = data.unread[fromUser];
                    let dmItem = document.querySelector(`.dm-item[data-user="${fromUser}"]`);

                    if (!dmItem) {
                        // Créer l'item s'il n'existe pas
                        dmItem = this.createDmItem(fromUser);
                        document.getElementById('dmsList').prepend(dmItem);
                    }

                    // Mettre à jour le badge
                    const badge = dmItem.querySelector('.dm-badge');
                    const preview = dmItem.querySelector('.dm-preview');

                    if (dmData.count > 0 && AppState.currentChannel !== `dm:${fromUser}`) {
                        AppState.unreadDMs[fromUser] = dmData.count;
                        badge.textContent = dmData.count;
                        badge.style.display = 'block';

                        // Afficher l'aperçu du dernier message
                        if (dmData.last_message) {
                            const previewText = dmData.last_message.length > 30
                                ? dmData.last_message.substring(0, 30) + '...'
                                : dmData.last_message;
                            preview.textContent = previewText;
                        }

                        // Afficher une notification
                        if (dmData.count === 1) { // Nouvelle notification uniquement pour le premier
                            Toast.info(`Nouveau message de ${fromUser}`);
                            Utils.showNotification('Nouveau message privé', `${fromUser}: ${dmData.last_message}`);
                        }
                    } else {
                        badge.style.display = 'none';
                        if (dmData.last_message) {
                            const previewText = dmData.last_message.length > 30
                                ? dmData.last_message.substring(0, 30) + '...'
                                : dmData.last_message;
                            preview.textContent = previewText;
                        }
                    }
                });
            }
        } catch (error) {
            console.error('Erreur vérification DMs:', error);
        }
    }
};

// ============================================
// PRÉSENCE
// ============================================
const Presence = {
    async updateOnlineUsers() {
        try {
            const response = await fetch('get_online_users.php');
            const data = await response.json();

            if (data.users) {
                this.displayOnlineUsers(data.users);
            }
        } catch (error) {
            console.error('Erreur users:', error);
        }
    },

    displayOnlineUsers(users) {
        const usersList = document.getElementById('usersList');
        const onlineCount = document.getElementById('onlineCount');

        onlineCount.textContent = users.length;
        usersList.innerHTML = '';

        users.forEach(user => {
            if (user.pseudo === AppState.pseudo) return;
            
            const userItem = document.createElement('div');
            userItem.className = 'user-item';

            const pfp = Utils.createPfp(user.pseudo, 32, user.profile_pic || '');
            pfp.style.cursor = 'pointer';
            pfp.addEventListener('click', () => Profile.show(user.pseudo));
            
            const username = document.createElement('span');
            username.textContent = user.pseudo;
            username.style.cursor = 'pointer';
            username.addEventListener('click', () => Profile.show(user.pseudo));

            const dmBtn = document.createElement('button');
            dmBtn.className = 'user-dm-btn';
            dmBtn.textContent = 'Message';
            dmBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                DM.openConversation(user.pseudo);
            });

            userItem.appendChild(pfp);
            userItem.appendChild(username);
            userItem.appendChild(dmBtn);

            usersList.appendChild(userItem);
        });
    },

    async sendHeartbeat() {
        const formData = new FormData();
        formData.append('user_id', AppState.userId);
        formData.append('pseudo', AppState.pseudo);
        formData.append('session_id', AppState.sessionId);

        try {
            await fetch('heartbeat.php', {
                method: 'POST',
                body: formData
            });
        } catch (error) {
            console.error('Erreur heartbeat:', error);
        }
    },

    startHeartbeat() {
        this.sendHeartbeat();
        this.updateOnlineUsers();

        AppState.intervals.heartbeat = setInterval(() => this.sendHeartbeat(), 30000);
        AppState.intervals.users = setInterval(() => this.updateOnlineUsers(), 5000);
    }
};

// ============================================
// PROFIL
// ============================================
const Profile = {
    async show(pseudo) {
        try {
            const response = await fetch(`profile.php?pseudo=${encodeURIComponent(pseudo)}`);
            const data = await response.json();

            if (data.success) {
                const user = data.user;

                document.getElementById('profilePseudo').textContent = user.pseudo;

                const pfpLarge = document.getElementById('profilePicLarge');
                pfpLarge.innerHTML = '';
                const pfp = Utils.createPfp(user.pseudo, 100, user.profile_pic || '', user.emoji_avatar || '');
                pfpLarge.appendChild(pfp);
                
                document.getElementById('profileBioContent').textContent = user.bio || 'Aucune biographie';
                document.getElementById('profilePostCount').textContent = user.post_count || 0;
                document.getElementById('profileJoinedAt').textContent = Utils.formatDate(user.created_at);
                
                const badges = document.getElementById('profileBadges');
                badges.innerHTML = '';
                if (user.is_admin) {
                    const badge = document.createElement('span');
                    badge.className = 'badge admin-badge';
                    badge.textContent = '👑 Admin';
                    badges.appendChild(badge);
                }
                
                const msgBtn = document.getElementById('profileMessageBtn');
                if (user.pseudo === AppState.pseudo) {
                    msgBtn.style.display = 'none';
                } else {
                    msgBtn.style.display = 'block';
                    msgBtn.onclick = () => {
                        document.getElementById('profileModal').style.display = 'none';
                        DM.openConversation(user.pseudo);
                    };
                }
                
                document.getElementById('profileModal').style.display = 'flex';
            }
        } catch (error) {
            console.error('Erreur profil:', error);
            Toast.error('Impossible de charger le profil');
        }
    }
};

// ============================================
// PARAMÈTRES
// ============================================
const Settings = {
    selectedEmoji: null,

    init() {
        document.getElementById('settingsBtn').addEventListener('click', () => {
            document.getElementById('bioInput').value = AppState.bio;
            if (AppState.emojiAvatar) {
                document.getElementById('currentEmojiPreview').textContent = AppState.emojiAvatar;
                this.selectedEmoji = AppState.emojiAvatar;
            }
            document.getElementById('settingsModal').style.display = 'flex';
        });

        // Fermeture des modals
        document.getElementById('closeSettingsModal').addEventListener('click', () => {
            document.getElementById('settingsModal').style.display = 'none';
        });

        document.getElementById('closeProfileModal').addEventListener('click', () => {
            document.getElementById('profileModal').style.display = 'none';
        });

        // Fermeture en cliquant dehors
        document.getElementById('settingsModal').addEventListener('click', (e) => {
            if (e.target.id === 'settingsModal') {
                document.getElementById('settingsModal').style.display = 'none';
            }
        });

        document.getElementById('profileModal').addEventListener('click', (e) => {
            if (e.target.id === 'profileModal') {
                document.getElementById('profileModal').style.display = 'none';
            }
        });

        document.getElementById('saveBioBtn').addEventListener('click', () => this.saveBio());

        // Sélection d'emoji
        document.querySelectorAll('.emoji-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                document.querySelectorAll('.emoji-btn').forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                this.selectedEmoji = btn.dataset.emoji;
                document.getElementById('currentEmojiPreview').textContent = this.selectedEmoji;
            });
        });

        document.getElementById('saveEmojiBtn').addEventListener('click', () => this.saveEmoji());

        document.getElementById('deleteAccountBtn').addEventListener('click', () => this.deleteAccount());
    },
    
    async saveBio() {
        const bio = document.getElementById('bioInput').value;

        const formData = new FormData();
        formData.append('action', 'update_bio');
        formData.append('bio', bio);

        try {
            const response = await fetch('profile.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                AppState.bio = bio;
                Toast.success('Biographie mise à jour');
            } else {
                Toast.error(data.error || 'Erreur');
            }
        } catch (error) {
            Toast.error('Erreur serveur');
        }
    },

    async saveEmoji() {
        if (!this.selectedEmoji) {
            Toast.warning('Sélectionne un emoji d\'abord');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'update_emoji');
        formData.append('emoji', this.selectedEmoji);

        try {
            const response = await fetch('profile.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                AppState.emojiAvatar = this.selectedEmoji;
                Toast.success('Avatar emoji mis à jour !');

                // Mettre à jour l'affichage
                const currentUserPfp = document.getElementById('currentUserPfp');
                if (currentUserPfp) {
                    const newPfp = Utils.createPfp(AppState.pseudo, 32, '', this.selectedEmoji);
                    currentUserPfp.replaceWith(newPfp);
                    newPfp.id = 'currentUserPfp';
                }
            } else {
                Toast.error(data.error || 'Erreur');
            }
        } catch (error) {
            console.error('Erreur:', error);
            Toast.error('Erreur serveur');
        }
    },
    
    async uploadProfilePic(file) {
        if (!file) return;
        
        // Vérifier le type de fichier
        if (!file.type.startsWith('image/')) {
            Toast.error('Fichier invalide (image requise)');
            return;
        }
        
        // Vérifier la taille (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
            Toast.error('Image trop volumineuse (max 5MB)');
            return;
        }
        
        Toast.info('Upload en cours...');
        
        const formData = new FormData();
        formData.append('action', 'upload_profile_pic');
        formData.append('profile_pic', file);
        
        try {
            const response = await fetch('profile.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                AppState.profilePic = data.profile_pic;
                Toast.success('Photo mise à jour !');
                
                // Mettre à jour l'affichage sans recharger
                const currentUserPfp = document.getElementById('currentUserPfp');
                if (currentUserPfp) {
                    const newPfp = Utils.createPfp(AppState.pseudo, 32, data.profile_pic);
                    currentUserPfp.replaceWith(newPfp);
                    newPfp.id = 'currentUserPfp';
                }
            } else {
                Toast.error(data.error || 'Erreur d\'upload');
            }
        } catch (error) {
            console.error('Erreur upload:', error);
            Toast.error('Erreur serveur');
        }
    },
    
    async deleteAccount() {
        // Toast custom pour confirmation
        Toast.warning('Clique à nouveau pour confirmer la suppression');
        
        setTimeout(async () => {
            const formData = new FormData();
            formData.append('action', 'delete_account');
            
            try {
                const response = await fetch('auth.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    Toast.success('Compte supprimé');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Toast.error(data.error || 'Erreur');
                }
            } catch (error) {
                Toast.error('Erreur serveur');
            }
        }, 100);
    }
};

// ============================================
// APPLICATION PRINCIPALE
// ============================================
const App = {
    init() {
        console.log('🚀 Init app...');
        
        Auth.init();
        Settings.init();

        // Navigation
        document.querySelectorAll('.channel-item').forEach(item => {
            item.addEventListener('click', (e) => {
                const channel = e.currentTarget.dataset.channel;
                
                document.querySelectorAll('.channel-item, .dm-item').forEach(i => {
                    i.classList.remove('active');
                });
                e.currentTarget.classList.add('active');
                
                if (channel === 'general') {
                    AppState.currentChannel = 'general';
                    AppState.currentView = 'chat';
                    document.getElementById('chatTitle').textContent = 'Chat général';
                    document.getElementById('chatView').style.display = 'flex';
                    document.getElementById('forumView').style.display = 'none';
                    document.getElementById('mypageView').style.display = 'none';
                    Chat.renderMessages();
                } else if (channel === 'forum') {
                    AppState.currentView = 'forum';
                    document.getElementById('chatView').style.display = 'none';
                    document.getElementById('forumView').style.display = 'block';
                    document.getElementById('mypageView').style.display = 'none';
                } else if (channel === 'mypage') {
                    AppState.currentView = 'mypage';
                    document.getElementById('chatView').style.display = 'none';
                    document.getElementById('forumView').style.display = 'none';
                    document.getElementById('mypageView').style.display = 'block';
                }
            });
        });

        // Envoi de message
        const messageForm = document.getElementById('messageForm');
        const messageInput = document.getElementById('messageInput');

        messageForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const text = messageInput.value;
            
            if (text.trim()) {
                if (AppState.currentChannel === 'general') {
                    await Chat.sendMessage(text);
                } else if (AppState.currentChannel.startsWith('dm:')) {
                    const withUser = AppState.currentChannel.substring(3);
                    await DM.sendMessage(withUser, text);
                }
                messageInput.value = '';
            }
        });

        // Cleanup
        window.addEventListener('beforeunload', () => {
            if (AppState.sse.messages) AppState.sse.messages.close();
            if (AppState.intervals.heartbeat) clearInterval(AppState.intervals.heartbeat);
            if (AppState.intervals.users) clearInterval(AppState.intervals.users);
        });
        
        console.log('✅ App initialisée');
    },

    start() {
        console.log('▶️ Démarrage...');
        Presence.startHeartbeat();
        Chat.loadInitialMessages();
        Chat.startSSE();

        // Vérifier les DMs non lus toutes les 5 secondes
        setInterval(() => {
            DM.checkAllDMs();
        }, 5000);

        // Première vérification immédiate
        DM.checkAllDMs();

        console.log('✅ App démarrée');
    }
};

// Démarrage
document.addEventListener('DOMContentLoaded', () => {
    console.log('📱 DOM chargé');
    App.init();
});



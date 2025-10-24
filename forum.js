// ============================================
// FORUM - Module JavaScript
// ============================================

const Forum = {
    currentFilter: 'recent',
    posts: [],
    currentPostId: null,
    
    async init() {
        console.log('🎨 Init forum...');
        
        // Boutons de filtres
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const filter = e.target.dataset.filter;
                this.changeFilter(filter);
            });
        });
        
        // Bouton créer un post
        document.getElementById('createPostBtn').addEventListener('click', () => {
            this.showCreatePostModal();
        });
        
        // Charger les posts initiaux
        await this.loadPosts();
    },
    
    changeFilter(filter) {
        this.currentFilter = filter;
        
        // Mettre à jour l'UI
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`.filter-btn[data-filter="${filter}"]`).classList.add('active');
        
        // Recharger les posts
        this.loadPosts();
    },
    
    async loadPosts() {
        try {
            const response = await fetch(`get_forum_posts.php?filter=${this.currentFilter}&limit=20&offset=0`);
            const data = await response.json();
            
            if (data.success) {
                this.posts = data.posts;
                this.renderPosts();
            }
        } catch (error) {
            console.error('Erreur chargement posts:', error);
            Toast.error('Impossible de charger les posts');
        }
    },
    
    renderPosts() {
        const container = document.getElementById('forumPosts');
        container.innerHTML = '';
        
        if (this.posts.length === 0) {
            container.innerHTML = '<div class="empty-state">Aucun post pour le moment. Sois le premier à poster !</div>';
            return;
        }
        
        this.posts.forEach(post => {
            const postEl = this.createPostElement(post);
            container.appendChild(postEl);
        });
    },
    
    createPostElement(post) {
        const postEl = document.createElement('div');
        postEl.className = 'forum-post';
        postEl.dataset.postId = post.id;
        
        // Votes
        const votesEl = document.createElement('div');
        votesEl.className = 'post-votes';
        votesEl.innerHTML = `
            <button class="vote-btn upvote" data-post-id="${post.id}" data-vote="1">
                ⬆️
            </button>
            <span class="vote-count">${post.upvotes - post.downvotes}</span>
            <button class="vote-btn downvote" data-post-id="${post.id}" data-vote="-1">
                ⬇️
            </button>
        `;
        
        // Contenu
        const contentEl = document.createElement('div');
        contentEl.className = 'post-content';
        
        // Header
        const headerEl = document.createElement('div');
        headerEl.className = 'post-header';
        
        const pfp = Utils.createPfp(post.pseudo, 32, post.profile_pic || '');
        pfp.addEventListener('click', () => Profile.show(post.pseudo));
        
        const infoEl = document.createElement('div');
        infoEl.className = 'post-info';
        infoEl.innerHTML = `
            <span class="post-author" onclick="Profile.show('${post.pseudo}')">${post.pseudo}</span>
            <span class="post-time">${Utils.formatDate(post.created_at)}</span>
        `;
        
        headerEl.appendChild(pfp);
        headerEl.appendChild(infoEl);
        
        // Titre
        const titleEl = document.createElement('h3');
        titleEl.className = 'post-title';
        titleEl.textContent = post.title;
        titleEl.addEventListener('click', () => this.openPost(post.id));
        
        // Corps
        const bodyEl = document.createElement('div');
        bodyEl.className = 'post-body';
        bodyEl.innerHTML = Utils.parseMarkdown(post.content);
        
        // Média
        let mediaEl = null;
        if (post.media_url) {
            mediaEl = document.createElement('img');
            mediaEl.className = 'post-image';
            mediaEl.src = post.media_url;
            mediaEl.alt = 'Image du post';
        } else if (post.youtube_url) {
            const videoId = this.extractYouTubeId(post.youtube_url);
            if (videoId) {
                mediaEl = document.createElement('div');
                mediaEl.className = 'post-video';
                mediaEl.innerHTML = `<iframe src="https://www.youtube.com/embed/${videoId}" frameborder="0" allowfullscreen></iframe>`;
            }
        }
        
        // Footer
        const footerEl = document.createElement('div');
        footerEl.className = 'post-footer';
        footerEl.innerHTML = `
            <button class="post-action-btn" onclick="Forum.openPost(${post.id})">
                💬 ${post.comment_count} commentaire${post.comment_count > 1 ? 's' : ''}
            </button>
            ${post.user_id === AppState.userId || AppState.isAdmin ? `
                <button class="post-action-btn delete-btn" onclick="Forum.deletePost(${post.id})">
                    🗑️ Supprimer
                </button>
            ` : ''}
        `;
        
        contentEl.appendChild(headerEl);
        contentEl.appendChild(titleEl);
        contentEl.appendChild(bodyEl);
        if (mediaEl) contentEl.appendChild(mediaEl);
        contentEl.appendChild(footerEl);
        
        postEl.appendChild(votesEl);
        postEl.appendChild(contentEl);
        
        // Event listeners pour les votes
        votesEl.querySelectorAll('.vote-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const postId = parseInt(btn.dataset.postId);
                const voteType = parseInt(btn.dataset.vote);
                this.votePost(postId, voteType);
            });
        });
        
        return postEl;
    },
    
    async votePost(postId, voteType) {
        const formData = new FormData();
        formData.append('post_id', postId);
        formData.append('vote_type', voteType);
        
        try {
            const response = await fetch('vote_post.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                // Mettre à jour l'affichage
                const postEl = document.querySelector(`.forum-post[data-post-id="${postId}"]`);
                if (postEl) {
                    const voteCount = postEl.querySelector('.vote-count');
                    voteCount.textContent = data.upvotes - data.downvotes;
                    
                    // Mettre à jour les boutons
                    const upvoteBtn = postEl.querySelector('.upvote');
                    const downvoteBtn = postEl.querySelector('.downvote');
                    upvoteBtn.classList.toggle('active', data.user_vote === 1);
                    downvoteBtn.classList.toggle('active', data.user_vote === -1);
                }
            } else {
                Toast.error(data.error || 'Erreur de vote');
            }
        } catch (error) {
            console.error('Erreur vote:', error);
            Toast.error('Erreur de vote');
        }
    },
    
    showCreatePostModal() {
        // Créer le modal dynamiquement
        const modalHTML = `
            <div id="createPostModal" class="modal">
                <div class="modal-content">
                    <button class="modal-close" onclick="document.getElementById('createPostModal').remove()">×</button>
                    <h2>Créer un post</h2>
                    
                    <form id="createPostForm">
                        <input type="text" id="postTitle" placeholder="Titre du post" maxlength="200" required>
                        <textarea id="postContent" placeholder="Contenu (markdown supporté)" maxlength="5000" rows="8" required></textarea>
                        
                        <div class="form-group">
                            <label>Image (optionnel, max 3MB)</label>
                            <input type="file" id="postImage" accept="image/jpeg,image/png,image/webp,image/gif">
                        </div>
                        
                        <div class="form-group">
                            <label>URL YouTube (optionnel)</label>
                            <input type="url" id="postYouTube" placeholder="https://www.youtube.com/watch?v=...">
                        </div>
                        
                        <button type="submit" class="primary-btn">Publier</button>
                    </form>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        const modal = document.getElementById('createPostModal');
        modal.style.display = 'flex';
        
        // Fermer en cliquant dehors
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
        
        // Soumettre le formulaire
        document.getElementById('createPostForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.createPost();
        });
    },
    
    async createPost() {
        const title = document.getElementById('postTitle').value.trim();
        const content = document.getElementById('postContent').value.trim();
        const imageFile = document.getElementById('postImage').files[0];
        const youtubeUrl = document.getElementById('postYouTube').value.trim();
        
        if (!title || !content) {
            Toast.warning('Titre et contenu requis');
            return;
        }
        
        const formData = new FormData();
        formData.append('title', title);
        formData.append('content', content);
        if (imageFile) formData.append('media', imageFile);
        if (youtubeUrl) formData.append('youtube_url', youtubeUrl);
        
        try {
            const response = await fetch('create_post.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                Toast.success('Post créé !');
                document.getElementById('createPostModal').remove();
                await this.loadPosts();
            } else {
                Toast.error(data.error || 'Erreur de création');
            }
        } catch (error) {
            console.error('Erreur création post:', error);
            Toast.error('Erreur serveur');
        }
    },
    
    async deletePost(postId) {
        if (!confirm('Supprimer ce post définitivement ?')) return;
        
        const formData = new FormData();
        formData.append('post_id', postId);
        
        try {
            const response = await fetch('delete_post.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                Toast.success('Post supprimé');
                await this.loadPosts();
            } else {
                Toast.error(data.error || 'Erreur de suppression');
            }
        } catch (error) {
            console.error('Erreur suppression:', error);
            Toast.error('Erreur serveur');
        }
    },
    
    async openPost(postId) {
        this.currentPostId = postId;
        
        // Créer le modal pour afficher le post complet avec commentaires
        const post = this.posts.find(p => p.id === postId);
        if (!post) return;
        
        const modalHTML = `
            <div id="postDetailModal" class="modal">
                <div class="modal-content post-detail-modal">
                    <button class="modal-close" onclick="document.getElementById('postDetailModal').remove()">×</button>
                    
                    <div class="post-detail">
                        <div class="post-header">
                            ${Utils.createPfp(post.pseudo, 40, post.profile_pic || '').outerHTML}
                            <div class="post-info">
                                <span class="post-author">${post.pseudo}</span>
                                <span class="post-time">${Utils.formatDate(post.created_at)}</span>
                            </div>
                        </div>
                        
                        <h2 class="post-title">${post.title}</h2>
                        <div class="post-body">${Utils.parseMarkdown(post.content)}</div>
                        
                        ${post.media_url ? `<img src="${post.media_url}" class="post-image">` : ''}
                        ${post.youtube_url ? `<div class="post-video"><iframe src="https://www.youtube.com/embed/${this.extractYouTubeId(post.youtube_url)}" frameborder="0" allowfullscreen></iframe></div>` : ''}
                        
                        <div class="post-stats">
                            <span>⬆️ ${post.upvotes}</span>
                            <span>⬇️ ${post.downvotes}</span>
                            <span>💬 ${post.comment_count}</span>
                        </div>
                    </div>
                    
                    <div class="comments-section">
                        <h3>Commentaires</h3>
                        
                        <form id="addCommentForm" class="comment-form">
                            <textarea id="commentContent" placeholder="Ajouter un commentaire..." maxlength="2000" rows="3" required></textarea>
                            <button type="submit" class="primary-btn">Commenter</button>
                        </form>
                        
                        <div id="commentsList" class="comments-list">
                            <div class="loading">Chargement des commentaires...</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        const modal = document.getElementById('postDetailModal');
        modal.style.display = 'flex';
        
        // Charger les commentaires
        await this.loadComments(postId);
        
        // Event listener pour le formulaire
        document.getElementById('addCommentForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.addComment(postId);
        });
        
        // Fermer en cliquant dehors
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    },
    
    async loadComments(postId) {
        try {
            const response = await fetch(`get_comments.php?post_id=${postId}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderComments(data.comments);
            }
        } catch (error) {
            console.error('Erreur chargement commentaires:', error);
            document.getElementById('commentsList').innerHTML = '<div class="error">Erreur de chargement</div>';
        }
    },
    
    renderComments(comments) {
        const container = document.getElementById('commentsList');
        container.innerHTML = '';
        
        if (comments.length === 0) {
            container.innerHTML = '<div class="empty-state">Aucun commentaire pour le moment</div>';
            return;
        }
        
        comments.forEach(comment => {
            const commentEl = this.createCommentElement(comment);
            container.appendChild(commentEl);
        });
    },
    
    createCommentElement(comment, depth = 0) {
        const commentEl = document.createElement('div');
        commentEl.className = 'comment';
        commentEl.style.marginLeft = `${depth * 20}px`;
        
        const pfp = Utils.createPfp(comment.pseudo, 32, comment.profile_pic || '');
        
        commentEl.innerHTML = `
            <div class="comment-header">
                ${pfp.outerHTML}
                <span class="comment-author">${comment.pseudo}</span>
                <span class="comment-time">${Utils.formatTime(comment.created_at)}</span>
            </div>
            <div class="comment-content">${Utils.parseMarkdown(comment.content)}</div>
            <div class="comment-actions">
                <button class="comment-reply-btn" onclick="Forum.replyToComment(${comment.id}, '${comment.pseudo}')">Répondre</button>
            </div>
        `;
        
        // Ajouter les réponses
        if (comment.replies && comment.replies.length > 0) {
            const repliesEl = document.createElement('div');
            repliesEl.className = 'comment-replies';
            comment.replies.forEach(reply => {
                repliesEl.appendChild(this.createCommentElement(reply, depth + 1));
            });
            commentEl.appendChild(repliesEl);
        }
        
        return commentEl;
    },
    
    async addComment(postId, parentCommentId = null) {
        const content = document.getElementById('commentContent').value.trim();
        
        if (!content) {
            Toast.warning('Le commentaire ne peut pas être vide');
            return;
        }
        
        const formData = new FormData();
        formData.append('post_id', postId);
        formData.append('content', content);
        if (parentCommentId) formData.append('parent_comment_id', parentCommentId);
        
        try {
            const response = await fetch('add_comment.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                Toast.success('Commentaire ajouté');
                document.getElementById('commentContent').value = '';
                await this.loadComments(postId);
                
                // Mettre à jour le compteur de commentaires
                await this.loadPosts();
            } else {
                Toast.error(data.error || 'Erreur');
            }
        } catch (error) {
            console.error('Erreur ajout commentaire:', error);
            Toast.error('Erreur serveur');
        }
    },
    
    replyToComment(commentId, authorPseudo) {
        const textarea = document.getElementById('commentContent');
        textarea.value = `@${authorPseudo} `;
        textarea.focus();
        // On pourrait améliorer en stockant le parent_comment_id
    },
    
    extractYouTubeId(url) {
        const regExp = /^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#&?]*).*/;
        const match = url.match(regExp);
        return (match && match[7].length === 11) ? match[7] : null;
    }
};

// Initialiser le forum quand on change de vue
document.addEventListener('DOMContentLoaded', () => {
    const forumChannel = document.querySelector('.channel-item[data-channel="forum"]');
    if (forumChannel) {
        forumChannel.addEventListener('click', () => {
            setTimeout(() => Forum.init(), 100);
        });
    }
});

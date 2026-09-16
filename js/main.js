// Community-Dating-Platform JavaScript

// Toggle Profile Menu
function toggleProfileMenu() {
    const menu = document.getElementById('profile-menu');
    if (menu) {
        menu.classList.toggle('active');
    }
}

// Toggle Mobile Menu
function toggleMobileMenu() {
    const menu = document.getElementById('mobile-menu');
    if (menu) {
        menu.classList.toggle('active');
    }
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    const profileBtn = document.querySelector('.profile-btn');
    const menu = document.getElementById('profile-menu');
    
    if (profileBtn && menu && !profileBtn.contains(event.target) && !menu.contains(event.target)) {
        menu.classList.remove('active');
    }
});

// Like Post
function likePost(postId) {
    fetch('ajax/like-post.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'post_id=' + postId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(error => console.error('Error:', error));
}

// Comment Post
function commentPost(postId) {
    const content = prompt('Kommentar eingeben:');
    if (content) {
        fetch('ajax/comment-post.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'post_id=' + postId + '&content=' + encodeURIComponent(content)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message);
            }
        })
        .catch(error => console.error('Error:', error));
    }
}

// Like User
function likeUser(userId) {
    fetch('ajax/like-user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'user_id=' + userId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(error => console.error('Error:', error));
}

// Send Message
function sendMessage(recipientId) {
    const message = document.getElementById('message-input');
    if (!message || !message.value.trim()) {
        alert('Bitte geben Sie eine Nachricht ein');
        return;
    }

    fetch('ajax/send-message.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'recipient_id=' + recipientId + '&message=' + encodeURIComponent(message.value)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            message.value = '';
            loadMessages(recipientId);
        } else {
            alert(data.message);
        }
    })
    .catch(error => console.error('Error:', error));
}

// Load Messages
function loadMessages(threadId) {
    fetch('ajax/get-messages.php?thread_id=' + threadId)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const messagesContainer = document.getElementById('messages-container');
            if (messagesContainer) {
                messagesContainer.innerHTML = data.messages;
                // Scroll to bottom
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

// Auto-refresh messages every 3 seconds
let messageRefreshInterval = null;
function startMessageRefresh(threadId) {
    messageRefreshInterval = setInterval(() => {
        loadMessages(threadId);
    }, 3000);
}

function stopMessageRefresh() {
    if (messageRefreshInterval) {
        clearInterval(messageRefreshInterval);
    }
}

// Notification Badge
function loadNotifications() {
    fetch('ajax/get-notifications.php')
    .then(response => response.json())
    .then(data => {
        const notificationBadge = document.querySelector('.notification-icon .badge');
        if (notificationBadge && data.count > 0) {
            notificationBadge.textContent = data.count;
        }
    })
    .catch(error => console.error('Error:', error));
}

// Load notifications every 30 seconds
setInterval(loadNotifications, 30000);

// Mark Notification as Read
function markNotificationRead(notificationId) {
    fetch('ajax/mark-notification-read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'notification_id=' + notificationId
    })
    .then(response => response.json())
    .catch(error => console.error('Error:', error));
}

// Add Favorite
function addFavorite(userId) {
    fetch('ajax/add-favorite.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'user_id=' + userId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Zu Favoriten hinzugefügt');
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(error => console.error('Error:', error));
}

// Block User
function blockUser(userId) {
    if (!confirm('Möchten Sie diesen Benutzer wirklich blockieren?')) {
        return;
    }

    fetch('ajax/block-user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'user_id=' + userId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Benutzer blockiert');
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(error => console.error('Error:', error));
}

// Report User
function reportUser(userId) {
    const reason = prompt('Grund für die Meldung eingeben:');
    if (!reason) return;

    fetch('ajax/report-user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'user_id=' + userId + '&reason=' + encodeURIComponent(reason)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Vielen Dank für die Meldung. Unser Team wird sich darum kümmern.');
        } else {
            alert(data.message);
        }
    })
    .catch(error => console.error('Error:', error));
}

// Format Date
function formatDate(dateString) {
    const options = { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleDateString('de-DE', options);
}

// Search Filter
function applySearch() {
    const form = document.getElementById('search-form');
    if (form) {
        form.submit();
    }
}

// Image Preview
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById(previewId);
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Confirm Action
function confirmAction(message) {
    return confirm(message);
}

// Show Alert
function showAlert(message, type = 'success') {
    const alert = document.createElement('div');
    alert.className = 'alert alert-' + type;
    alert.textContent = message;
    document.body.insertBefore(alert, document.body.firstChild);
    
    setTimeout(() => {
        alert.remove();
    }, 5000);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Load notifications
    loadNotifications();
});

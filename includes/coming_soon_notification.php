<!-- Coming Soon Notification -->
<div id="comingSoonNotification" class="coming-soon-notification" style="display: none;">
    <div class="notification-header">
        <h5>
            <div class="icon-wrapper">
                <i class="fas fa-clock"></i>
            </div>
            Coming Soon!
        </h5>
        <button class="close-btn" onclick="closeComingSoon()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="notification-body">
        <p class="mb-2"><strong>Courses are coming soon!</strong></p>
        <p class="mb-0">We're working hard to bring you the best trading courses. Stay tuned for updates!</p>
    </div>
</div>

<style>
    /* Coming Soon Notification */
    .coming-soon-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        padding: 20px 30px;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        z-index: 9999;
        max-width: 400px;
        animation: slideInRight 0.5s ease-out;
        border: 2px solid rgba(255, 255, 255, 0.2);
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .coming-soon-notification .notification-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 15px;
    }
    
    .coming-soon-notification .notification-header h5 {
        margin: 0;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .coming-soon-notification .notification-body {
        line-height: 1.6;
    }
    
    .coming-soon-notification .close-btn {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
    }
    
    .coming-soon-notification .close-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: rotate(90deg);
    }
    
    .coming-soon-notification .icon-wrapper {
        width: 50px;
        height: 50px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 10px;
    }
</style>

<script>
    function showComingSoon() {
        const notification = document.getElementById('comingSoonNotification');
        if (notification) {
            notification.style.display = 'block';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                closeComingSoon();
            }, 5000);
        }
    }
    
    function closeComingSoon() {
        const notification = document.getElementById('comingSoonNotification');
        if (notification) {
            notification.style.animation = 'slideInRight 0.5s ease-out reverse';
            setTimeout(() => {
                notification.style.display = 'none';
                notification.style.animation = 'slideInRight 0.5s ease-out';
            }, 500);
        }
    }
</script>

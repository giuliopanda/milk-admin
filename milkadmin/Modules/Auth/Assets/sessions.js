/**
 * SessionManager - Session management with automatic renewal and warning popup
 */
class SessionManager {
    constructor() {
        this.lastActivity = Date.now();
        this.checkInterval = null;
        this.popupCheckInterval = null;
        this.modal = null;
        this.isPopupOpen = false;
        
        this.setupActivityListeners();
        this.startMonitoring();
    }
    
    /**
     * Sets up user activity listeners
     */
    setupActivityListeners() {
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        events.forEach(event => {
            document.addEventListener(event, () => {
                this.lastActivity = Date.now();
            }, true);
        });
    }
    
    /**
     * Starts monitoring every 5 minutes
     */
    startMonitoring() {
        // Check every 5 minutes
        this.checkInterval = setInterval(() => {
            this.checkSession();
        }, 5 * 60 * 1000);
    }
    
    /**
     * Checks session status
     */
    async checkSession() {
        // If popup is open, do nothing
        if (this.isPopupOpen) return;
        
        const inactiveMinutes = (Date.now() - this.lastActivity) / (1000 * 60);
        
        // If user is active (movement in the last 9.5 min)
        if (inactiveMinutes < 9.5) {
            await this.refreshSession();
        } else {
            // Check how much time remains
            const sessionInfo = await this.getSessionInfo();
            if (sessionInfo && sessionInfo.remaining_minutes <= 10) {
                this.showWarningPopup(sessionInfo.remaining_minutes);
            }
        }
    }
    
    /**
     * Gets session information
     */
    async getSessionInfo() {
        try {
            const response = await fetch(`${milk_url}?page=auth&action=session-info`, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            const data = await response.json();
            return data.success ? data.session_info : null;
        } catch (error) {
            return null;
        }
    }
    
    /**
     * Refreshes the session
     */
    async refreshSession(force = false) {
        if (this.isPopupOpen && force !== true) return;
        try {
            const response = await fetch(`${milk_url}?page=auth&action=refresh-session`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            const data = await response.json();
            
            // If session is no longer active, redirect to login
            if (!data.success || (data.session_info && !data.session_info.active)) {
                this.redirectToLogin();
                return false;
            }
            
            return true;
        } catch (error) {
            return false;
        }
    }
    
    /**
     * Shows the warning popup
     */
    showWarningPopup(minutes) {
        if (this.isPopupOpen) return;
        
        this.isPopupOpen = true;
        this.createPopupHTML(minutes);
        
        this.modal = new bootstrap.Modal(document.getElementById('sessionWarningModal'), {
            backdrop: 'static',
            keyboard: false
        });
        
        this.modal.show();
        
        // Start checking every 30 seconds
        this.popupCheckInterval = setInterval(() => {
            this.updatePopupTimer();
        }, 30 * 1000);
        
        // Handle continue button click
        document.getElementById('continueSessionBtn').addEventListener('click', () => {
            this.continueSession();
        });
    }
    
    /**
     * Creates the popup HTML
     */
    createPopupHTML(minutes) {
        const modalHTML = `
            <div class="modal fade" id="sessionWarningModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title">
                                <i class="bi bi-exclamation-triangle-fill"></i> Are you still there?
                            </h5>
                        </div>
                        <div class="modal-body text-center">
                            <p>Your session will expire in:</p>
                            <div class="display-4 text-danger mb-3" id="minutesRemaining">${minutes} minute${minutes === 1 ? '' : 's'}</div>
                            <p class="text-body-secondary">Click "Continue" to stay connected.</p>
                        </div>
                        <div class="modal-footer justify-content-center">
                            <button type="button" class="btn btn-primary btn-lg" id="continueSessionBtn">
                                <i class="bi bi-check-lg"></i> Continue Session
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }
    
    /**
     * Updates the timer in the popup
     */
    async updatePopupTimer() {
        const sessionInfo = await this.getSessionInfo();
        
        if (!sessionInfo) return;
        
        // If session has expired
        if (!sessionInfo.active || sessionInfo.remaining_minutes <= 0) {
            this.redirectToLogin();
            return;
        }
        
        // If more than 5 minutes remain, close the popup
        if (sessionInfo.remaining_minutes > 5) {
            this.closePopup();
            return;
        }
        
        // Update the minutes display
        const minutesEl = document.getElementById('minutesRemaining');
        if (minutesEl) {
            minutesEl.textContent = `${sessionInfo.remaining_minutes} minute${sessionInfo.remaining_minutes === 1 ? '' : 's'}`;
        }
    }
    
    /**
     * Continues the session
     */
    async continueSession() {
        const success = await this.refreshSession(true);
        
        if (success) {
            this.closePopup();
            this.lastActivity = Date.now();
        }
    }
    
    /**
     * Closes the popup
     */
    closePopup() {
        if (this.popupCheckInterval) {
            clearInterval(this.popupCheckInterval);
            this.popupCheckInterval = null;
        }
        
        if (this.modal) {
            this.modal.hide();
            this.modal = null;
        }
        
        const modalEl = document.getElementById('sessionWarningModal');
        if (modalEl) {
            modalEl.remove();
        }
        
        this.isPopupOpen = false;
    }
    
    /**
     * Redirects to login page
     */
    redirectToLogin() {
        this.destroy();
        setTimeout(() => {
        window.location.href = milk_url+'?page=auth&action=login';
        }, 1000);
       
    }
    
    /**
     * Destroys the session manager
     */
    destroy() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
            this.checkInterval = null;
        }
        
        this.closePopup();
    }
}

// Initialization
document.addEventListener('DOMContentLoaded', function() {
    window.sessionManager = new SessionManager();
    setTimeout(() => {
        window.sessionManager.refreshSession();
    }, 5000);
});

// Cleanup before leaving the page
window.addEventListener('beforeunload', () => {
    if (window.sessionManager) {
        window.sessionManager.destroy();
    }
});
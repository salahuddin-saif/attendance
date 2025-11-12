// auth.js - Authentication utility for GitHub Pages
class Auth {
    constructor() {
        this.sessionKey = 'admin_session';
    }

    login(username, password) {
        const admin = db.validateAdminLogin(username, password);
        if (admin) {
            const session = {
                logged_in: true,
                username: admin.username,
                login_time: new Date().toISOString()
            };
            localStorage.setItem(this.sessionKey, JSON.stringify(session));
            return true;
        }
        return false;
    }

    logout() {
        const session = this.getSession();
        if (session && session.username) {
            db.logActivity('ADMIN_LOGOUT', `Admin logged out: ${session.username}`);
        }
        localStorage.removeItem(this.sessionKey);
    }

    isLoggedIn() {
        const session = this.getSession();
        return session && session.logged_in === true;
    }

    getSession() {
        try {
            const session = localStorage.getItem(this.sessionKey);
            return session ? JSON.parse(session) : null;
        } catch (e) {
            return null;
        }
    }

    requireLogin() {
        if (!this.isLoggedIn()) {
            window.location.href = 'admin_login.html';
            return false;
        }
        return true;
    }
}

// Create global auth instance
const auth = new Auth();


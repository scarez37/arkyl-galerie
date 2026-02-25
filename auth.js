/**
 * 🔐 AuthManager - Module d'Authentification Centralisé pour ARKYL
 * 
 * Ce module unifie la gestion de l'authentification entre :
 * - app.js (clés complexes)
 * - artist_dashboard.html (clés simples)
 * 
 * @version 1.0.0
 * @author ARKYL Team
 */

const AuthManager = {
    
    /**
     * Configuration des clés localStorage
     */
    KEYS: {
        // Clés simples (compatibilité artist_dashboard.html)
        USER_ID: 'user_id',
        USER_NAME: 'user_name',
        USER_EMAIL: 'user_email',
        
        // Clés complexes (app.js)
        CURRENT_USER: 'arkyl_current_user',
        ARTIST_ACCOUNT_PREFIX: 'arkyl_artist_account_',
        ARTIST_ACCOUNT_EMAIL_PREFIX: 'arkyl_artist_account_email_',
        
        // Token de session (à implémenter)
        SESSION_TOKEN: 'arkyl_session_token',
        SESSION_EXPIRES: 'arkyl_session_expires'
    },
    
    /**
     * 🔑 Connexion - Sauvegarde les données utilisateur dans TOUS les systèmes
     * @param {Object} userData - Données retournées par l'API de connexion
     * @returns {boolean} - true si succès, false sinon
     */
    login(userData) {
        try {
            // Validation des données minimales requises
            const userId = userData.user_id || userData.id || userData.artist_id || userData.userId;
            const userName = userData.user_name || userData.name || userData.username || userData.artist_name;
            const userEmail = userData.user_email || userData.email || userData.user_mail;
            
            if (!userId || !userName || !userEmail) {
                console.error('❌ AuthManager.login: Données incomplètes', userData);
                return false;
            }
            
            // ── 1. Clés simples (artist_dashboard.html) ──
            localStorage.setItem(this.KEYS.USER_ID, String(userId));
            localStorage.setItem(this.KEYS.USER_NAME, userName);
            localStorage.setItem(this.KEYS.USER_EMAIL, userEmail);
            
            // ── 2. Compte artiste (app.js) ──
            const artistAccount = {
                id: userId,
                name: userName,
                email: userEmail,
                country: userData.country || 'Côte d\'Ivoire',
                avatar: userData.avatar || '',
                isArtist: true
            };
            
            // Clé par ID
            localStorage.setItem(
                `${this.KEYS.ARTIST_ACCOUNT_PREFIX}${userId}`,
                JSON.stringify(artistAccount)
            );
            
            // Clé par email (fallback)
            localStorage.setItem(
                `${this.KEYS.ARTIST_ACCOUNT_EMAIL_PREFIX}${userEmail.toLowerCase()}`,
                JSON.stringify(artistAccount)
            );
            
            // ── 3. Utilisateur courant (app.js) ──
            const currentUser = {
                id: userId,
                googleId: userId,
                email: userEmail,
                name: userName,
                picture: userData.avatar || '',
                isArtist: true,
                artistName: userName,
                isAdmin: userData.isAdmin || false
            };
            
            localStorage.setItem(
                this.KEYS.CURRENT_USER,
                JSON.stringify(currentUser)
            );
            
            // ── 4. Token de session (optionnel) ──
            if (userData.session_token) {
                localStorage.setItem(this.KEYS.SESSION_TOKEN, userData.session_token);
                
                // Expiration : 1 heure par défaut
                const expiresIn = userData.expires_in || 3600000; // millisecondes
                localStorage.setItem(
                    this.KEYS.SESSION_EXPIRES,
                    String(Date.now() + expiresIn)
                );
            }
            
            console.log('✅ AuthManager: Connexion réussie pour', userName, '(ID:', userId, ')');
            console.log('✅ Toutes les clés sauvegardées avec succès');
            
            return true;
            
        } catch (error) {
            console.error('❌ AuthManager.login: Erreur', error);
            return false;
        }
    },
    
    /**
     * 🚪 Déconnexion - Supprime TOUTES les clés d'authentification
     * @returns {boolean} - true si succès, false sinon
     */
    logout() {
        try {
            // Récupérer les données AVANT suppression
            const userId = localStorage.getItem(this.KEYS.USER_ID);
            const userEmail = localStorage.getItem(this.KEYS.USER_EMAIL);
            
            // ── 1. Clés simples ──
            localStorage.removeItem(this.KEYS.USER_ID);
            localStorage.removeItem(this.KEYS.USER_NAME);
            localStorage.removeItem(this.KEYS.USER_EMAIL);
            
            // ── 2. Clés complexes ──
            localStorage.removeItem(this.KEYS.CURRENT_USER);
            
            // ── 3. Clés artiste dynamiques ──
            if (userId) {
                localStorage.removeItem(`${this.KEYS.ARTIST_ACCOUNT_PREFIX}${userId}`);
            }
            if (userEmail) {
                localStorage.removeItem(`${this.KEYS.ARTIST_ACCOUNT_EMAIL_PREFIX}${userEmail.toLowerCase()}`);
            }
            
            // ── 4. Token de session ──
            localStorage.removeItem(this.KEYS.SESSION_TOKEN);
            localStorage.removeItem(this.KEYS.SESSION_EXPIRES);
            
            console.log('✅ AuthManager: Déconnexion complète - Toutes les clés supprimées');
            
            return true;
            
        } catch (error) {
            console.error('❌ AuthManager.logout: Erreur', error);
            return false;
        }
    },
    
    /**
     * 🔍 Vérifier si l'utilisateur est authentifié
     * @returns {boolean} - true si connecté, false sinon
     */
    isAuthenticated() {
        // Vérifier la présence des clés essentielles
        const hasSimpleKeys = !!localStorage.getItem(this.KEYS.USER_ID);
        const hasComplexKeys = !!localStorage.getItem(this.KEYS.CURRENT_USER);
        
        // Vérifier l'expiration de la session (si implémentée)
        if (this.isSessionExpired()) {
            console.warn('⚠️ Session expirée');
            this.logout();
            return false;
        }
        
        return hasSimpleKeys && hasComplexKeys;
    },
    
    /**
     * 👤 Récupérer les données de l'utilisateur courant
     * @returns {Object|null} - Objet utilisateur ou null si non connecté
     */
    getCurrentUser() {
        if (!this.isAuthenticated()) {
            return null;
        }
        
        try {
            // Priorité aux clés complexes (plus complètes)
            const currentUserStr = localStorage.getItem(this.KEYS.CURRENT_USER);
            if (currentUserStr) {
                return JSON.parse(currentUserStr);
            }
            
            // Fallback vers clés simples
            return {
                id: localStorage.getItem(this.KEYS.USER_ID),
                name: localStorage.getItem(this.KEYS.USER_NAME),
                email: localStorage.getItem(this.KEYS.USER_EMAIL),
                isArtist: true
            };
            
        } catch (error) {
            console.error('❌ AuthManager.getCurrentUser: Erreur', error);
            return null;
        }
    },
    
    /**
     * 🆔 Récupérer l'ID de l'utilisateur courant
     * @returns {string|null}
     */
    getUserId() {
        return localStorage.getItem(this.KEYS.USER_ID);
    },
    
    /**
     * 📧 Récupérer l'email de l'utilisateur courant
     * @returns {string|null}
     */
    getUserEmail() {
        return localStorage.getItem(this.KEYS.USER_EMAIL);
    },
    
    /**
     * 👤 Récupérer le nom de l'utilisateur courant
     * @returns {string|null}
     */
    getUserName() {
        return localStorage.getItem(this.KEYS.USER_NAME);
    },
    
    /**
     * ⏰ Vérifier si la session a expiré
     * @returns {boolean}
     */
    isSessionExpired() {
        const expires = localStorage.getItem(this.KEYS.SESSION_EXPIRES);
        
        if (!expires) {
            return false; // Pas d'expiration configurée
        }
        
        return Date.now() > parseInt(expires);
    },
    
    /**
     * 🔄 Rafraîchir la session (prolonger l'expiration)
     * @param {number} expiresIn - Durée en millisecondes (défaut: 1h)
     */
    refreshSession(expiresIn = 3600000) {
        if (this.isAuthenticated()) {
            localStorage.setItem(
                this.KEYS.SESSION_EXPIRES,
                String(Date.now() + expiresIn)
            );
            console.log('✅ Session rafraîchie');
        }
    },
    
    /**
     * 🛡️ Vérifier la session avec le serveur (à implémenter)
     * @param {string} apiUrl - URL de l'API de vérification
     * @returns {Promise<boolean>}
     */
    async verifySessionWithServer(apiUrl) {
        const userId = this.getUserId();
        const token = localStorage.getItem(this.KEYS.SESSION_TOKEN);
        
        if (!userId) {
            return false;
        }
        
        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_id: userId,
                    session_token: token
                })
            });
            
            const result = await response.json();
            
            if (!result.success) {
                console.warn('⚠️ Session invalide côté serveur');
                this.logout();
                return false;
            }
            
            // Rafraîchir la session locale
            this.refreshSession();
            return true;
            
        } catch (error) {
            console.error('❌ Erreur vérification serveur:', error);
            return false;
        }
    },
    
    /**
     * 🔧 Réparer les clés manquantes (migration/synchronisation)
     * Utile pour corriger les incohérences entre anciens et nouveaux systèmes
     */
    repairAuth() {
        console.log('🔧 Réparation de l\'authentification...');
        
        const userId = localStorage.getItem(this.KEYS.USER_ID);
        const userName = localStorage.getItem(this.KEYS.USER_NAME);
        const userEmail = localStorage.getItem(this.KEYS.USER_EMAIL);
        
        if (userId && userName && userEmail) {
            // Recréer toutes les clés à partir des clés simples
            this.login({
                user_id: userId,
                user_name: userName,
                user_email: userEmail
            });
            
            console.log('✅ Authentification réparée');
        } else {
            console.warn('⚠️ Impossible de réparer : données insuffisantes');
        }
    }
};

// ────────────────────────────────────────────────────────────────────
// UTILISATION RECOMMANDÉE
// ────────────────────────────────────────────────────────────────────

/**
 * EXEMPLE 1 : Connexion (dans connexion.html)
 * 
 * fetch('api_connexion.php', { ... })
 *   .then(response => response.json())
 *   .then(result => {
 *       if (result.success) {
 *           if (AuthManager.login(result)) {
 *               window.location.href = 'artist_dashboard.html';
 *           }
 *       }
 *   });
 */

/**
 * EXEMPLE 2 : Vérification d'authentification (dans artist_dashboard.html)
 * 
 * if (!AuthManager.isAuthenticated()) {
 *     window.location.href = 'connexion.html';
 * } else {
 *     const user = AuthManager.getCurrentUser();
 *     console.log('Connecté en tant que:', user.name);
 * }
 */

/**
 * EXEMPLE 3 : Déconnexion (dans app.js OU artist_dashboard.html)
 * 
 * function handleLogout() {
 *     if (confirm('Se déconnecter ?')) {
 *         AuthManager.logout();
 *         window.location.href = 'index.html';
 *     }
 * }
 */

/**
 * EXEMPLE 4 : Vérification périodique de la session
 * 
 * // Vérifier toutes les 5 minutes
 * setInterval(() => {
 *     if (AuthManager.isSessionExpired()) {
 *         alert('Session expirée. Veuillez vous reconnecter.');
 *         AuthManager.logout();
 *         window.location.href = 'connexion.html';
 *     }
 * }, 300000);
 */

// Export pour utilisation en module ES6 (optionnel)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AuthManager;
}

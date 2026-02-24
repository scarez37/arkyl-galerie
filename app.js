
// ==========================================
// UTILITAIRE RENDU GALERIE CENTRALISÉ
// ==========================================

function refreshGalleryByPage(pageId) {
    if (pageId === 'homePage') {
        (typeof afficherOeuvresFiltrees === 'function' && window.toutesLesOeuvres?.length > 0)
            ? afficherOeuvresFiltrees()
            : (typeof chargerLaVraieGalerie === 'function' ? chargerLaVraieGalerie() : null);
    } else if (pageId === 'favoritesPage' && typeof renderFavorites === 'function') {
        renderFavorites();
    } else if (pageId === 'cartPage' && typeof renderCart === 'function') {
        renderCart();
    }
}


function enterGallery() {
            const introPage = document.getElementById('intro-page');
            const mainContent = document.getElementById('main-content');
            introPage.classList.add('fade-out');
            setTimeout(() => {
                introPage.style.display = 'none';
                mainContent.classList.add('visible');
                if (typeof init === 'function') init();
            }, 1000);
        }

        document.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && document.getElementById('intro-page').style.display !== 'none') {
                enterGallery();
            }
        });

        // Variables pour optimisation (déclarées une seule fois)
        let productsCache = null;
        let favoritesCache = null;
        let lastRenderTime = {};
        let autoRefreshInterval = null;

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        function startAutoRefresh() {
            if (autoRefreshInterval) return;
            autoRefreshInterval = setInterval(() => {
                const currentPage = document.querySelector('.page.active');
                if (!currentPage) return;
                const pageId = currentPage.id;
                const now = Date.now();
                const lastRender = lastRenderTime[pageId] || 0;
                if (now - lastRender > 30000) {
                    if (pageId === 'homePage' && typeof renderProducts === 'function') (typeof afficherOeuvresFiltrees === 'function' && window.toutesLesOeuvres?.length > 0) ? afficherOeuvresFiltrees() : (typeof chargerLaVraieGalerie === 'function' ? chargerLaVraieGalerie() : null);
                    else if (pageId === 'favoritesPage' && typeof renderFavorites === 'function') renderFavorites();
                    else if (pageId === 'cartPage' && typeof renderCart === 'function') renderCart();
                    lastRenderTime[pageId] = now;
                }
            }, 30000);
        }

        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
        }

        function showUpdateIndicator() {
            const indicator = document.getElementById('autoUpdateIndicator');
            if (indicator) {
                indicator.classList.add('show');
                setTimeout(() => indicator.classList.remove('show'), 3000);
            }
        }

        function triggerManualRefresh() {
            const currentPage = document.querySelector('.page.active');
            if (!currentPage) return;
            const pageId = currentPage.id;
            if (pageId === 'homePage' && typeof renderProducts === 'function') (typeof afficherOeuvresFiltrees === 'function' && window.toutesLesOeuvres?.length > 0) ? afficherOeuvresFiltrees() : (typeof chargerLaVraieGalerie === 'function' ? chargerLaVraieGalerie() : null);
            else if (pageId === 'favoritesPage' && typeof renderFavorites === 'function') renderFavorites();
            else if (pageId === 'cartPage' && typeof renderCart === 'function') renderCart();
            if (typeof updateBadges === 'function') updateBadges();
            if (typeof renderNotifications === 'function') renderNotifications();
            showUpdateIndicator();
            if (typeof showToast === 'function') showToast('✅ Page actualisée');
            lastRenderTime[pageId] = Date.now();
        }

        window.addEventListener('storage', (e) => {
            if (e.key === 'favorites') {
                const currentPage = document.querySelector('.page.active');
                if (currentPage) {
                    const pageId = currentPage.id;
                    if (pageId === 'favoritesPage' && typeof renderFavorites === 'function') renderFavorites();
                }
                if (typeof updateBadges === 'function') updateBadges();
            }
        });

        // ===== GLASS EFFECT GLOBAL APPLICATION =====
        (function applyGlass() {
            const selectors = [
                '.product-card', '.filter-btn', '.stat-card', '.orders-stat-card',
                '.benefit-card', '.back-button', '.action-btn', '.btn-large',
                '.admin-tab-btn', '.quick-action-btn', '.news-ticker-container',
                '.notifications-panel', '.notification-modal', '.news-lightbox',
                '.modal-content', '.reg-modal-content', '.chat-window', '.toast',
                '.nav-menu', '.ticker-nav-btn', '.like-button', '.product-price',
                '.cart-items', '.empty-orders', '.search-bar', '.artist-edit-modal',
                '.orders-stat-card', '.stat-card', '.overview-section', '.quick-action-btn',
            ];
            function apply() {
                selectors.forEach(sel => {
                    document.querySelectorAll(sel + ':not(.glass)').forEach(el => el.classList.add('glass'));
                });
            }
            document.addEventListener('DOMContentLoaded', apply);
            new MutationObserver(apply).observe(document.documentElement, { childList: true, subtree: true });
        })();

        document.addEventListener('DOMContentLoaded', () => {

            // ── DÉTECTION RETOUR STRIPE — EN PREMIER AVANT TOUT ─────────
            // Capturer les params avant que navigateTo() ne nettoie l'URL
            const _rawParams = new URLSearchParams(window.location.search);
            const _stripeSession = _rawParams.get('session_id');
            const _stripeOrderId = _rawParams.get('order_id');
            const _isStripeReturn = !!(_stripeSession || _stripeOrderId);
            if (_isStripeReturn) {
                // Stocker en mémoire pour traitement après chargement complet
                window._isStripeReturn = true;
                window._pendingStripeSession = _stripeSession;
                window._pendingStripeOrderId = _stripeOrderId;
                // Nettoyer l'URL immédiatement pour éviter re-trigger au refresh
                window.history.replaceState({}, document.title, window.location.pathname);
                console.log('💳 Retour Stripe détecté — session:', _stripeSession, 'order:', _stripeOrderId);
            }

            // ── RESTAURATION DE SESSION (auto-login) ────────────────────
            (function restoreSession() {
                try {
                    const raw = localStorage.getItem('arkyl_arkyl_current_user')
                             || localStorage.getItem('arkyl_current_user');
                    if (!raw) return;
                    const savedUser = JSON.parse(raw);
                    if (!savedUser || !savedUser.email) return;
                    currentUser = savedUser;
                    console.log('✅ Session restaurée :', savedUser.name || savedUser.email);
                    // Mettre à jour l'interface (avatar, nom, menus)
                    if (typeof updateAuthUI === 'function') updateAuthUI();
                    // Charger le panier depuis la BDD
                    const uid = savedUser.id || savedUser.googleId || savedUser.email;
                    if (typeof chargerPanierUtilisateur === 'function') chargerPanierUtilisateur(uid);
                    if (typeof chargerAdresseUtilisateur === 'function') chargerAdresseUtilisateur(uid);
                    // Charger la ville La Poste
                    try {
                        const _rv = localStorage.getItem('arkyl_poste_ville_' + uid);
                        if (typeof posteVille !== 'undefined') posteVille = _rv ? JSON.parse(_rv) : '';
                        const _rc = localStorage.getItem('arkyl_transport_cie_' + uid);
                        if (typeof transportCompagnie !== 'undefined') transportCompagnie = _rc ? JSON.parse(_rc) : '';
                        const _rl = localStorage.getItem('arkyl_mainpropre_lieu_' + uid);
                        if (typeof mainPropreLieu !== 'undefined') mainPropreLieu = _rl ? JSON.parse(_rl) : '';
                    } catch(e) {}
                } catch(e) {
                    console.warn('Impossible de restaurer la session :', e);
                }
            })();

            const urlParams = new URLSearchParams(window.location.search);

            if (urlParams.get('mode') === 'artist') {
                // Vérifier que l'artiste a bien un compte
                const hasArtistAccount = safeStorage.get('arkyl_artist_account', null) || _memStore['arkyl_artist_account'] || null;
                if (hasArtistAccount) {
                    console.log('🎨 Mode artiste détecté via URL - Activation automatique...');
                    setTimeout(() => {
                        switchToArtistMode();
                    } 500);
                }
            }
            
            setTimeout(() => {
                startAutoRefresh();
                showUpdateIndicator();
            } 2000);
        });

        window.addEventListener('beforeunload', () => {
            stopAutoRefresh();
            
            // Sauvegarder la page active et son contexte avant le rechargement
            const activePage = document.querySelector('.page.active');
            if (activePage) {
                const pageState = {
                    pageId: activePage.id,
                    timestamp: Date.now()
                };
                
                // Si on est sur un profil artiste, sauvegarder le nom de l'artiste
                if (activePage.id === 'artistDetailPage') {
                    const artistNameElement = document.querySelector('.artist-detail-name');
                    if (artistNameElement) {
                        pageState.artistName = artistNameElement.textContent.trim();
                    }
                }
                
                // Si on est sur un détail de produit, sauvegarder l'ID
                if (activePage.id === 'productDetailPage') {
                    const productTitleElement = document.querySelector('.product-detail-title');
                    if (productTitleElement && window.currentProductId) {
                        pageState.productId = window.currentProductId;
                    }
                }
                
                safeStorage.set('arkyl_last_page', pageState);
            }
        });

        const logoContainer = document.querySelector('.logo-container');
        if (logoContainer) {
            let lastLogoTap = 0;
            logoContainer.addEventListener('click', (e) => {
                const now = Date.now();
                const timeSinceLastTap = now - lastLogoTap;
                if (timeSinceLastTap < 300 && timeSinceLastTap > 0) {
                    e.preventDefault();
                    triggerManualRefresh();
                }
                lastLogoTap = now;
            });
        }

        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                triggerManualRefresh();
            }
            
            // Shortcut Ctrl+H to go back to home/gallery
            if ((e.ctrlKey || e.metaKey) && e.key === 'h') {
                e.preventDefault();
                if (currentUser && currentUser.isAdmin) {
                    backToClientMode();
                } else {
                    navigateTo('home');
                    showToast('🏠 Page d\'accueil');
                }
            }
        });


        // ==================== SAFE STORAGE HELPERS ====================
        // Persiste dans localStorage avec fallback mémoire
        const _memStore = {};
        const safeStorage = {
            get: (key, defaultValue = null) => {
                try {
                    const raw = localStorage.getItem(key);
                    if (raw !== null) return JSON.parse(raw);
                } catch(e) {}
                return (key in _memStore) ? _memStore[key] : defaultValue;
            }
            set: (key, value) => {
                try {
                    localStorage.setItem(key, JSON.stringify(value));
                } catch(e) {}
                _memStore[key] = value;
                return true;
            }
            remove: (key) => {
                try { localStorage.removeItem(key); } catch(e) {}
                delete _memStore[key];
                return true;
            }
        };

        // ==================== DATA ====================
        window.currentCategory = 'all';
        let currentCategory = 'all';
        let favorites = safeStorage.get('arkyl_favorites', []);
        let cartItems = [];
        let orderHistory = safeStorage.get('arkyl_orders', []);
        let notifications = safeStorage.get('arkyl_notifications', [
            { id: 1, title: 'Bienvenue!', text: 'Découvrez nos nouvelles œuvres d\'art', time: 'Il y a 2h', unread: true }
            { id: 2, title: 'Promotion', text: '-20% sur toutes les sculptures cette semaine', time: 'Il y a 5h', unread: true }
            { id: 3, title: 'Nouvel artiste', text: 'Kofi Mensah a ajouté de nouvelles peintures', time: 'Hier', unread: false }
        ]);

        const sampleProducts = [];

        // Cache des données
        let appData = {
            artworks: [],
            artists: {}
            news: [],
            orders: [],
            users: [],
            interactions: { likes: [], comments: [] }
            settings: {}
            metadata: {}
        };

        // ==================== GOOGLE AUTHENTICATION ====================
        const ADMIN_EMAILS = ['scarez37@gmail.com', 'arkyl.app@gmail.com'];
        
        // ==================== ARTISTS DATA ====================
        let artistsData = {};
        
        // Données artistes initialisées en mémoire uniquement

        // ==================== FONCTIONS DE GESTION DES DONNÉES ====================

        // saveAppData — données en mémoire uniquement, pas de persistence
        async function saveAppData() {
            return true;
        }
        
        
        let currentUser = null; // Sera restauré depuis localStorage au démarrage

        // ============ SYSTÈME DE CONNEXION GOOGLE AUTHENTIQUE ============
        
        // Configuration Google Sign-In
       
        const GOOGLE_CLIENT_ID = '814095132615-nug0r3e9cgdc5kv4uj2du10e7dkas88b.apps.googleusercontent.com';
        
        // Initialiser Google Sign-In
        function initializeGoogleSignIn() {
            console.log('🔍 Tentative d\'initialisation de Google Sign-In...');
            console.log('📋 Client ID:', GOOGLE_CLIENT_ID);
            console.log('🌐 Type de google:', typeof google);
            
            if (typeof google !== 'undefined' && google.accounts && google.accounts.id) {
                try {
                    google.accounts.id.initialize({
                        client_id: GOOGLE_CLIENT_ID,
                        callback: handleGoogleCredentialResponse,
                        auto_select: false,
                        cancel_on_tap_outside: true
                    });
                    
                    // Rendre le bouton Google Sign-In
                    const loginBtn = document.getElementById('googleLoginBtn');
                    if (loginBtn) {
                        google.accounts.id.renderButton(
                            loginBtn,
                            {
                                theme: 'outline',
                                size: 'medium',
                                text: 'signin',
                                shape: 'pill',
                                logo_alignment: 'left',
                                width: 200
                            }
                        );
                        console.log('✅ Google Sign-In initialisé avec succès!');
                    } else {
                        console.error('❌ Élément googleLoginBtn non trouvé');
                    }
                } catch (error) {
                    console.error('❌ Erreur lors de l\'initialisation Google Sign-In:', error);
                    showGoogleSignInError();
                }
            } else {
                console.error('❌ Bibliothèque Google Sign-In non chargée');
                console.log('ℹ️ Vérifiez votre connexion Internet et que vous utilisez un serveur HTTP');
                // Réessayer après un délai
                setTimeout(() => {
                    console.log('🔄 Nouvelle tentative dans 2 secondes...');
                    if (typeof google !== 'undefined' && google.accounts && google.accounts.id) {
                        initializeGoogleSignIn();
                    } else {
                        showGoogleSignInError();
                    }
                } 2000);
            }
        }
        
        // Gérer la réponse d'authentification Google
        function handleGoogleCredentialResponse(response) {
            try {
                // Décoder le JWT token pour extraire les informations utilisateur
                const payload = parseJwt(response.credential);
                
                // Vérifier s'il existe un compte artiste avec cet email
                const existingArtistAccount = safeStorage.get('arkyl_artist_account', null);
                let isArtist = false;
                let artistName = null;
                
                if (existingArtistAccount && existingArtistAccount.email === payload.email) {
                    // Compte artiste trouvé avec le même email Google
                    isArtist = true;
                    artistName = existingArtistAccount.name;
                    console.log('✅ Compte artiste existant détecté:', artistName);
                }
                
                // Créer les données utilisateur à partir du profil Google
                const userData = {
                    id: payload.sub,
                    email: payload.email,
                    name: payload.name || payload.email.split('@')[0],
                    picture: payload.picture || `https://ui-avatars.com/api/?name=${encodeURIComponent(payload.name)}&background=random`,
                    googleId: payload.sub,
                    emailVerified: payload.email_verified,
                    isAdmin: ADMIN_EMAILS.includes(payload.email.toLowerCase()),
                    isArtist: isArtist,
                    artistName: artistName
                };
                
                console.log('✅ Authentification Google réussie:', userData.email);
                
                // Sauvegarder et mettre à jour l'interface
                if (isArtist) {
                    loginUser(userData, `✅ Bienvenue ${artistName} ! Compte artiste connecté.`);
                } else {
                    loginUser(userData, '✅ Connexion Google réussie!');
                }
                
            } catch (error) {
                console.error('❌ Erreur lors de l\'authentification Google:', error);
                showToast('❌ Erreur de connexion. Veuillez réessayer.');
            }
        }
        
        // Décoder le JWT token
        function parseJwt(token) {
            const base64Url = token.split('.')[1];
            const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
            const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join(''));
            return JSON.parse(jsonPayload);
        }
        
        // Afficher erreur si Google Sign-In n'est pas disponible
        function showGoogleSignInError() {
            const loginBtn = document.getElementById('googleLoginBtn');
            if (loginBtn) {
                loginBtn.innerHTML = `
                    <div style="padding: 20px; background: rgba(255,71,87,0.1); border: 2px solid #ff4757; border-radius: 12px; text-align: left; max-width: 500px;">
                        <p style="margin: 0 0 10px 0; color: #ff4757; font-weight: 700; font-size: 16px;">⚠️ Google Sign-In non disponible</p>
                        <p style="margin: 5px 0; font-size: 13px; color: #444; line-height: 1.5;">
                            <strong>Cause possible :</strong>
                        </p>
                        <ul style="margin: 5px 0; padding-left: 20px; font-size: 12px; color: #666; line-height: 1.6;">
                            <li>Vous ouvrez le fichier en double-cliquant dessus (file://)</li>
                            <li>Vous devez utiliser un serveur HTTP local</li>
                            <li>Pas de connexion Internet</li>
                        </ul>
                        <p style="margin: 10px 0 5px 0; font-size: 13px; color: #444;">
                            <strong>✅ Solution :</strong>
                        </p>
                        <pre style="background: #2d2d2d; color: #0f0; padding: 10px; border-radius: 6px; font-size: 12px; overflow-x: auto; margin: 5px 0;">python3 -m http.server 8000</pre>
                        <p style="margin: 5px 0 0 0; font-size: 11px; color: #666;">
                            Puis ouvrez: <strong>http://localhost:8000/index_unified_google_auth_CONFIGURE.html</strong>
                        </p>
                        <p style="margin: 10px 0 0 0; font-size: 11px; color: #888;">
                            Appuyez sur F12 → Console pour voir plus de détails
                        </p>
                    </div>
                `;
                loginBtn.style.display = 'block';
            }
        }
        

        // Gestion de l'Espace Artiste (bouton 🎨)
        function handleArtistSpace() {
           
            // Vérifier d'abord si l'artiste a un compte enregistré
            const hasArtistAccount = safeStorage.get('arkyl_artist_account', null) || _memStore['arkyl_artist_account'] || null;
            
            if (hasArtistAccount) {
                // Compte trouvé → Activer le mode artiste directement
                console.log('🎨 Activation du mode artiste...');
                switchToArtistMode();
                return;
            }
            
            // Pas de compte → Rediriger vers la page de connexion
            console.log('➡️ Redirection vers connexion.html');
            window.location.href = 'connexion.html';
        }

        // Connexion rapide pour les artistes existants
        function handleQuickArtistLogin() {
            const email = prompt('🎨 CONNEXION ARTISTE\n\n' +
                'Entrez votre email artiste:\n\n' +
                'Exemple: artiste@exemple.com'
            );
            
            if (!email || !email.trim()) return;

            const cleanEmail = email.trim();
            
            // Vérifier si l'email correspond à un artiste dans artistsData
            let artistName = null;
            for (const [name, data] of Object.entries(artistsData)) {
                if (data.email.toLowerCase() === cleanEmail.toLowerCase()) {
                    artistName = name;
                    break;
                }
            }

            if (!artistName) {
                alert('❌ Cet email ne correspond à aucun artiste enregistré.\n\nUtilisez "Devenir Artiste" pour créer un nouveau profil.');
                return;
            }

            // Mettre à jour l'utilisateur actuel en artiste
            currentUser.isArtist = true;
            currentUser.artistName = artistName;
            currentUser.name = artistName;
            currentUser.email = cleanEmail;
            currentUser.picture = artistsData[artistName].profileImage || currentUser.picture;

            // Sauvegarder
            safeStorage.set('arkyl_current_user', currentUser);
            updateAuthUI();

            showToast('🎨 Espace Artiste activé avec succès!');
            addNotification('Espace Artiste Activé', `Bienvenue ${artistName}! Vous pouvez maintenant gérer votre profil et vos œuvres.`);
            
            // Ouvrir directement le profil artiste
            setTimeout(() => {
                openArtistEditModal();
            }, 1000);
        }

        function loginUser(userData, successMessage) {
            currentUser = userData;
            safeStorage.set('arkyl_current_user', userData);
            updateAuthUI();

           
            const _loginUid = userData.id || userData.googleId || userData.email;
            chargerPanierUtilisateur(_loginUid);
            chargerAdresseUtilisateur(_loginUid); // charger l'adresse liée à ce compte
            // Charger la compagnie transport et ville La Poste du compte
            try {
                const _rawVille = localStorage.getItem('arkyl_poste_ville_' + _loginUid);
                posteVille = _rawVille ? JSON.parse(_rawVille) : '';
                const _rawCie = localStorage.getItem('arkyl_transport_cie_' + _loginUid);
                transportCompagnie = _rawCie ? JSON.parse(_rawCie) : '';
                const _rawLieu = localStorage.getItem('arkyl_mainpropre_lieu_' + _loginUid);
                mainPropreLieu = _rawLieu ? JSON.parse(_rawLieu) : '';
            } catch(e) { posteVille = ''; transportCompagnie = ''; mainPropreLieu = ''; }

            if (userData.isAdmin) {
                showToast(`🎉 Bienvenue Admin ${userData.name}!`);
                setTimeout(() => { goToAdmin(); } 1500);
            } else {
                showToast(successMessage);
            }
        }

        // Charge le panier depuis la BDD et enrichit avec les données produits
        async function chargerPanierUtilisateur(userId) {
            if (!userId) return;
            try {
                const response = fetch(
                    `https://arkyl-galerie.onrender.com/api_get_panier.php?user_id=${encodeURIComponent(userId)}`
                );
                const data = await response.json();
                if (!data.success) return;

                // Utiliser la vraie galerie API en priorité
                const allProducts = (window.toutesLesOeuvres && window.toutesLesOeuvres.length > 0)
                    ? window.toutesLesOeuvres
                    : getProducts();

                // Enrichir chaque item BDD avec les données complètes du produit (emoji, price, etc.)
                // PHP retourne data.data (pas data.items)
                cartItems = (data.data || data.items || []).map(item => {
                    const pid = item.artwork_id || item.id;
                    const product = allProducts.find(p => String(p.id) === String(pid));
                    if (product) {
                        return {
                            ...product,
                            id: String(product.id),
                            image: product.image || product.image_url || (product.photos && product.photos[0]) || '',
                            quantity: item.quantity || 1
                        };
                    }
                    return { ...item, id: String(pid), quantity: item.quantity || 1 };
                }).filter(item => item.id); // enlever les items sans id valide

                updateBadges();

                // Si la page panier est déjà ouverte, la rafraîchir
                const cartPage = document.getElementById('cartPage');
                if (cartPage && cartPage.classList.contains('active')) renderCart();

                console.log(`🛒 Panier chargé : ${cartItems.length} article(s)`);
            } catch (e) {
                console.warn('Panier BDD inaccessible :', e.message);
            }
        }

        function handleLogout() {
            const userName = currentUser ? currentUser.name : 'utilisateur';
            const userType = currentUser && currentUser.isArtist ? 'Artiste' : currentUser && currentUser.isAdmin ? 'Admin' : 'Utilisateur';
            
            // Vérifier s'il y a aussi un compte artiste
            const hasArtistAccount = safeStorage.get('arkyl_artist_account', null) || _memStore['arkyl_artist_account'] || null;
            const confirmMessage = hasArtistAccount 
                ? `🚪 Se déconnecter?\n\nVous êtes actuellement connecté comme ${userType}: ${userName}\n\n⚠️ Votre compte artiste sera également déconnecté.\n\nCliquez OK pour vous déconnecter.`
                : `🚪 Se déconnecter?\n\nVous êtes actuellement connecté comme ${userType}: ${userName}\n\nCliquez OK pour vous déconnecter.`;
            
            if (!confirm(confirmMessage)) return;
            
            // Déconnecter de Google
            if (typeof google !== 'undefined' && google.accounts && google.accounts.id) {
                google.accounts.id.disableAutoSelect();
            }

            // Super-nettoyage : toutes les clés possibles liées aux comptes et aux œuvres
            const keysToDestroy = [
                'arkyl_current_user',
                'arkyl_arkyl_current_user',
                'arkyl_user',
                'arkyl_artist_account',
                'arkyl_products',
                'arkyl_pending_order',
                'arkyl_cart',
                'arkyl_orders',
                'arkyl_favorites',
                'arkyl_notifications',
                'arkyl_last_page',
            ];
            keysToDestroy.forEach(key => {
                localStorage.removeItem(key);
                safeStorage.remove(key);
                delete _memStore[key];
            });

            // Réinitialiser les variables globales
            currentUser = null;
            if (typeof window.toutesLesOeuvres !== 'undefined') window.toutesLesOeuvres = [];

            showToast('👋 Déconnecté avec succès - À bientôt!');

            // Rechargement avec paramètre ?clear= pour vider toute la RAM du navigateur
            setTimeout(() => {
                window.location.href = window.location.pathname + '?clear=' + Date.now();
            } 600);
        }

        function updateAuthUI() {
            const loginBtn = document.getElementById('googleLoginBtn');
            const profileBtn = document.getElementById('userProfileBtn');
            const userAvatar = document.getElementById('userAvatar');
            const userName = document.getElementById('userName');
            const adminBadge = document.getElementById('adminBadge');
            const adminMenuBtn = document.getElementById('adminMenuBtn');
            const adminNewsMenuBtn = document.getElementById('adminNewsMenuBtn');
            const adminOrdersMenuBtn = document.getElementById('adminOrdersMenuBtn');
            const artistMenuBtn = document.getElementById('artistMenuBtn');
            const backToClientBtn = document.getElementById('backToClientBtn');
            const floatingGalleryBtn = document.getElementById('floatingGalleryBtn');
            
            // Update user menu header
            const userMenuName = document.getElementById('userMenuName');
            const userMenuType = document.getElementById('userMenuType');

            if (currentUser) {
                // Show profile, hide login
                loginBtn.style.display = 'none';
                profileBtn.style.display = 'flex';
                
                // Update user menu header
                if (userMenuName) userMenuName.textContent = currentUser.name;
                if (userMenuType) {
                    if (currentUser.isAdmin) {
                        userMenuType.textContent = '👑 Administrateur';
                        userMenuType.style.background = 'linear-gradient(135deg, #ff4757, #c23616)';
                        userMenuType.style.color = 'white';
                    } else if (currentUser.isArtist) {
                        userMenuType.textContent = '🎨 Artiste';
                        userMenuType.style.background = 'linear-gradient(135deg, var(--or), var(--bronze))';
                        userMenuType.style.color = '#1a1a1a';
                    } else {
                        userMenuType.textContent = '👤 Visiteur';
                        userMenuType.style.background = 'rgba(255,255,255,0.15)';
                        userMenuType.style.color = 'white';
                    }
                }
                
                // Handle artist profile image or regular avatar
                if (currentUser.isArtist && currentUser.artistName) {
                    const artistData = artistsData[currentUser.artistName];
                    if (artistData.profileImage) {
                        userAvatar.src = artistData.profileImage;
                    } else {
                        userAvatar.src = currentUser.picture;
                    }
                    // Add artist badge to username display
                    userName.innerHTML = `${currentUser.name} <span class="artist-badge-nav">Artiste</span>`;
                } else {
                    userAvatar.src = currentUser.picture;
                    userName.textContent = currentUser.name;
                }
                
                // Show admin badge and buttons if admin
                if (currentUser.isAdmin) {
                    adminBadge.style.display = 'block';
                    
                    // Check if we're on admin page or client page
                    const isAdminPage = window.location.pathname.includes('arkyl_admin.html');
                    
                    if (isAdminPage) {
                        // On admin page: hide "Panneau Admin", show "Retour Galerie"
                        adminMenuBtn.style.display = 'none';
                        adminNewsMenuBtn.style.display = 'none';
                        if (adminOrdersMenuBtn) adminOrdersMenuBtn.style.display = 'none';
                        backToClientBtn.style.display = 'flex';
                        floatingGalleryBtn.classList.add('show');
                    } else {
                        // On client page: show "Panneau Admin" and "Gérer Actualités"
                        adminMenuBtn.style.display = 'flex';
                        adminNewsMenuBtn.style.display = 'flex';
                        if (adminOrdersMenuBtn) adminOrdersMenuBtn.style.display = 'flex';
                        backToClientBtn.style.display = 'none';
                        floatingGalleryBtn.classList.remove('show');
                    }
                } else {
                    adminBadge.style.display = 'none';
                    adminMenuBtn.style.display = 'none';
                    adminNewsMenuBtn.style.display = 'none';
                    if (adminOrdersMenuBtn) adminOrdersMenuBtn.style.display = 'none';
                    backToClientBtn.style.display = 'none';
                    floatingGalleryBtn.classList.remove('show');
                }
                
                // Show artist menu button if user is an artist
                if (currentUser.isArtist) {
                    artistMenuBtn.style.display = 'flex';
                } else {
                    artistMenuBtn.style.display = 'none';
                }
            } else {
                // Show login, hide profile
                loginBtn.style.display = 'flex';
                profileBtn.style.display = 'none';
                floatingGalleryBtn.classList.remove('show');
            }
        }

        // === SYSTÈME ORBITAL HAMBURGER ===
        let orbitalOpen = false;

        function positionOrbitalItems() {
            const allItems = document.querySelectorAll('#hamburgerDropdown .hamburger-menu-item');
            const items = Array.from(allItems).filter(el => {
                const computed = window.getComputedStyle(el);
                return el.style.display !== 'none' && computed.display !== 'none';
            });

            // Items principaux à gauche (excl. authContainer)
            const mainItems = items.filter(el => el.id !== 'authContainer');
            const n = mainItems.length;
            const navbarHeight = 80;
            const itemSpacing = 44;
            const startY = navbarHeight + 20;

            mainItems.forEach((item, i) => {
                item.style.top = (startY + i * itemSpacing) + 'px';
                if (orbitalOpen) {
                    item.style.transform = 'translateX(0) scale(1)';
                    item.style.opacity = '1';
                    item.style.pointerEvents = 'auto';
                    item.style.transitionDelay = (i * 0.05) + 's';
                } else {
                    item.style.transform = 'translateX(-120%) scale(0.9)';
                    item.style.opacity = '0';
                    item.style.pointerEvents = 'none';
                    item.style.transitionDelay = ((n - i - 1) * 0.03) + 's';
                }
            });

            // authContainer à droite — séparé, indépendant
            const authItem = document.getElementById('authContainer');
            if (authItem) {
                if (orbitalOpen) {
                    authItem.classList.add('orbital-visible');
                    authItem.style.transitionDelay = '0.1s';
                } else {
                    authItem.classList.remove('orbital-visible');
                    authItem.style.transitionDelay = '0s';
                }
            }
        }

        function toggleHamburgerMenu() {
            const btn = document.getElementById('hamburgerBtn');
            const dropdown = document.getElementById('hamburgerDropdown');
            orbitalOpen = !orbitalOpen;
            btn.classList.toggle('active', orbitalOpen);
            dropdown.classList.toggle('open', orbitalOpen);
            positionOrbitalItems();
            if (orbitalOpen) updateHamburgerOrderBadges();
        }

        function closeHamburgerMenu() {
            const btn = document.getElementById('hamburgerBtn');
            const dropdown = document.getElementById('hamburgerDropdown');
            orbitalOpen = false;
            btn.classList.remove('active');
            dropdown.classList.remove('open');
            positionOrbitalItems();
        }

        // Fermer le menu hamburger en cliquant ailleurs
        document.addEventListener('click', function(e) {
            const btn = document.getElementById('hamburgerBtn');
            const dropdown = document.getElementById('hamburgerDropdown');
            if (btn && dropdown && !btn.contains(e.target) && !dropdown.contains(e.target)) {
                closeHamburgerMenu();
            }
        });

        // Repositionner si fenêtre redimensionnée
        window.addEventListener('resize', function() {
            positionOrbitalItems();
        });

        function toggleUserMenu() {
            const dropdown = document.getElementById('userMenuDropdown');
            dropdown.classList.toggle('show');
        }

        function goToAdmin() {
            if (!currentUser || !currentUser.isAdmin) {
                showToast('⚠️ Accès réservé aux administrateurs');
                return;
            }
            
            // Close menu and navigate to admin page
            document.getElementById('userMenuDropdown').classList.remove('show');
            navigateTo('admin');
            switchAdminTab('overview'); // Show overview by default
        }

        function goToAdminNews() {
            if (!currentUser || !currentUser.isAdmin) {
                showToast('⚠️ Accès réservé aux administrateurs');
                return;
            }
            
            // Close menu and navigate to admin news page
            document.getElementById('userMenuDropdown').classList.remove('show');
            navigateTo('adminNews');
            renderNewsList();
        }

        // ==================== ADMIN MANAGEMENT FUNCTIONS ====================
        
        // Get products from memory cache or use default
        function getProducts() {
            return safeStorage.get('arkyl_products', sampleProducts);
        }

        // Save products to memory cache
        function saveProducts(products) {
            safeStorage.set('arkyl_products', products);
        }

        // Switch between admin tabs
        function switchAdminTab(tab) {
            // Update tab buttons
            document.querySelectorAll('.admin-tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.admin-section').forEach(section => section.classList.remove('active'));
            
            if (tab === 'overview') {
                document.getElementById('adminTabOverview').classList.add('active');
                document.getElementById('adminOverviewSection').classList.add('active');
                renderAdminOverview();
            } else if (tab === 'artworks') {
                document.getElementById('adminTabArtworks').classList.add('active');
                document.getElementById('adminArtworksSection').classList.add('active');
                renderAdminArtworks();
            } else if (tab === 'artists') {
                document.getElementById('adminTabArtists').classList.add('active');
                document.getElementById('adminArtistsSection').classList.add('active');
                renderAdminArtists();
            }
        }

        // ========== ADMIN SEARCH FUNCTIONS ==========
        
        function performAdminSearch() {
            const query = document.getElementById('adminSearchInput').value.trim().toLowerCase();
            
            if (!query) {
                showToast('⚠️ Veuillez entrer un terme de recherche');
                return;
            }

            const products = getProducts();
            // Utilise la variable globale newsItems (chargée depuis le serveur)
            
            // Search in artworks
            const artworkResults = products.filter(p => 
                p.title.toLowerCase().includes(query) || 
                p.artist.toLowerCase().includes(query) ||
                p.category.toLowerCase().includes(query) ||
                (p.badge && p.badge.toLowerCase().includes(query))
            );

            // Search in artists
            const artistResults = Object.keys(artistsData).filter(artistName =>
                artistName.toLowerCase().includes(query) ||
                (artistsData[artistName].bio && artistsData[artistName].bio.toLowerCase().includes(query)) ||
                (artistsData[artistName].specialty && artistsData[artistName].specialty.toLowerCase().includes(query))
            );

            // Search in news
            const newsResults = newsItems.filter(news =>
                (news.text && news.text.toLowerCase().includes(query)) ||
                (news.gradient && news.gradient.toLowerCase().includes(query))
            );

            // Display results
            displayAdminSearchResults(artworkResults, artistResults, newsResults, query);
        }

        function displayAdminSearchResults(artworks, artists, news, query) {
            const resultsContainer = document.getElementById('adminSearchResults');
            const resultsTitle = document.getElementById('adminSearchResultsTitle');
            const resultsContent = document.getElementById('adminSearchResultsContent');

            const totalResults = artworks.length + artists.length + news.length;

            if (totalResults === 0) {
                resultsTitle.textContent = `Aucun résultat pour "${query}"`;
                resultsContent.innerHTML = `
                    <p style="color: rgba(255,255,255,0.7); text-align: center; padding: 20px;">
                        Aucune œuvre, artiste ou actualité ne correspond à votre recherche.
                    </p>
                `;
                resultsContainer.style.display = 'block';
                return;
            }

            resultsTitle.textContent = `${totalResults} résultat(s) trouvé(s) pour "${query}"`;
            
            let html = '<div style="display: flex; flex-direction: column; gap: 20px;">';

            // Artworks Results
            if (artworks.length > 0) {
                html += `
                    <div>
                        <h4 style="font-family: 'Inter', sans-serif; font-size: 16px; font-weight: 600; color: #c026d3; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                            <span>🖼️</span>
                            <span>Œuvres (${artworks.length})</span>
                        </h4>
                        <div style="display: grid; gap: 10px;">
                `;
                
                artworks.forEach(artwork => {
                    html += `
                        <div style="background: rgba(255,255,255,0.05); border-radius: 12px; padding: 15px; display: flex; align-items: center; gap: 15px; transition: all 0.3s ease; cursor: pointer; border: 1px solid rgba(255,255,255,0.1);" onmouseover="this.style.background='rgba(255,255,255,0.08)'" onmouseout="this.style.background='rgba(255,255,255,0.05)'" onclick="switchAdminTab('artworks'); setTimeout(() => document.getElementById('artwork-${artwork.id}')?.scrollIntoView({behavior: 'smooth', block: 'center'}), 300);">
                            <img loading="lazy" src="${artwork.image}" alt="${artwork.title}" style="width: 60px; height: 60px; border-radius: 8px; object-fit: cover;">
                            <div style="flex: 1;">
                                <div style="font-family: 'Inter', sans-serif; font-size: 15px; font-weight: 600; color: white; margin-bottom: 3px;">${artwork.title}</div>
                                <div style="font-family: 'Inter', sans-serif; font-size: 13px; color: rgba(255,255,255,0.6);">par ${artwork.artist} • ${artwork.category}</div>
                            </div>
                            <div style="font-family: 'Inter', sans-serif; font-size: 14px; font-weight: 600; color: #9333ea;">${formatPrice(artwork.price)}</div>
                        </div>
                    `;
                });
                
                html += '</div></div>';
            }

            // Artists Results
            if (artists.length > 0) {
                html += `
                    <div>
                        <h4 style="font-family: 'Inter', sans-serif; font-size: 16px; font-weight: 600; color: #c026d3; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                            <span>👨‍🎨</span>
                            <span>Artistes (${artists.length})</span>
                        </h4>
                        <div style="display: grid; gap: 10px;">
                `;
                
                artists.forEach(artistName => {
                    const artist = artistsData[artistName];
                    html += `
                        <div style="background: rgba(255,255,255,0.05); border-radius: 12px; padding: 15px; display: flex; align-items: center; gap: 15px; transition: all 0.3s ease; cursor: pointer; border: 1px solid rgba(255,255,255,0.1);" onmouseover="this.style.background='rgba(255,255,255,0.08)'" onmouseout="this.style.background='rgba(255,255,255,0.05)'" onclick="switchAdminTab('artists'); setTimeout(() => document.querySelector('[data-artist-name=\\'${artistName}\\']')?.scrollIntoView({behavior: 'smooth', block: 'center'}), 300);">
                            <div style="width: 60px; height: 60px; border-radius: 50%; background: ${artist.avatarGradient || 'linear-gradient(135deg, #667eea, #764ba2)'}; display: flex; align-items: center; justify-content: center; font-size: 28px;">
                                ${artist.avatar || '👤'}
                            </div>
                            <div style="flex: 1;">
                                <div style="font-family: 'Inter', sans-serif; font-size: 15px; font-weight: 600; color: white; margin-bottom: 3px;">${artistName}</div>
                                <div style="font-family: 'Inter', sans-serif; font-size: 13px; color: rgba(255,255,255,0.6);">${artist.specialty || 'Artiste'}</div>
                            </div>
                        </div>
                    `;
                });
                
                html += '</div></div>';
            }

            // News Results
            if (news.length > 0) {
                html += `
                    <div>
                        <h4 style="font-family: 'Inter', sans-serif; font-size: 16px; font-weight: 600; color: #c026d3; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                            <span>📰</span>
                            <span>Actualités (${news.length})</span>
                        </h4>
                        <div style="display: grid; gap: 10px;">
                `;
                
                news.forEach((item, index) => {
                    html += `
                        <div style="background: rgba(255,255,255,0.05); border-radius: 12px; padding: 15px; display: flex; align-items: center; gap: 15px; transition: all 0.3s ease; cursor: pointer; border: 1px solid rgba(255,255,255,0.1);" onmouseover="this.style.background='rgba(255,255,255,0.08)'" onmouseout="this.style.background='rgba(255,255,255,0.05)'" onclick="goToAdminNews();">
                            <div style="font-size: 32px;">${item.icon || '📢'}</div>
                            <div style="flex: 1;">
                                <div style="font-family: 'Inter', sans-serif; font-size: 14px; color: white;">${item.text}</div>
                            </div>
                        </div>
                    `;
                });
                
                html += '</div></div>';
            }

            html += '</div>';
            
            resultsContent.innerHTML = html;
            resultsContainer.style.display = 'block';
            
            showToast(`✅ ${totalResults} résultat(s) trouvé(s)`);
        }

        function clearAdminSearch() {
            document.getElementById('adminSearchInput').value = '';
            document.getElementById('adminSearchResults').style.display = 'none';
            showToast('🔄 Recherche effacée');
        }

        // ========== ARTWORKS FILTER FUNCTIONS ==========
        
        function filterArtworks() {
            const query = document.getElementById('artworksSearchInput').value.trim().toLowerCase();
            const container = document.getElementById('adminArtworksList');
            const products = getProducts();
            
            if (!query) {
                // Show all artworks if search is empty
                renderAdminArtworks();
                return;
            }
            
            const filteredProducts = products.filter(p => 
                p.title.toLowerCase().includes(query) || 
                p.artist.toLowerCase().includes(query) ||
                p.category.toLowerCase().includes(query) ||
                (p.badge && p.badge.toLowerCase().includes(query))
            );
            
            if (filteredProducts.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 60px 20px; opacity: 0.7;">
                        <div style="font-size: 60px; margin-bottom: 20px;">🔍</div>
                        <h3>Aucun résultat</h3>
                        <p>Aucune œuvre ne correspond à "${query}"</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = filteredProducts.map(product => `
                <div class="admin-item-card" id="artwork-${product.id}">
                    <div class="admin-item-image">
                        <img loading="lazy" src="${product.image}" alt="${product.title}" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect fill=%22%23ddd%22 width=%22100%22 height=%22100%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2240%22%3E🎨%3C/text%3E%3C/svg%3E'">
                    </div>
                    <div class="admin-item-info">
                        <div class="admin-item-title">${product.title}</div>
                        <div class="admin-item-subtitle">par ${product.artist}</div>
                        <div class="admin-item-meta">
                            <span>💰 ${formatPrice(product.price)}</span>
                            <span>🏷️ ${product.category}</span>
                            <span>🏆 ${product.badge}</span>
                        </div>
                    </div>
                    <div class="admin-item-actions">
                        <button class="admin-btn-edit" onclick="editArtwork(${product.id})">✏️ Modifier</button>
                        <button class="admin-btn-delete" onclick="deleteArtistArtwork(${product.id})">🗑️ Supprimer</button>
                    </div>
                </div>
            `).join('');
        }
        
        function clearArtworksSearch() {
            document.getElementById('artworksSearchInput').value = '';
            renderAdminArtworks();
            showToast('🔄 Recherche effacée');
        }

        // ========== ARTISTS FILTER FUNCTIONS ==========
        
        function filterArtists() {
            const query = document.getElementById('artistsSearchInput').value.trim().toLowerCase();
            const container = document.getElementById('adminArtistsList');
            const artists = Object.entries(artistsData);
            
            if (!query) {
                // Show all artists if search is empty
                renderAdminArtists();
                return;
            }
            
            const filteredArtists = artists.filter(([name, data]) =>
                name.toLowerCase().includes(query) ||
                (data.email && data.email.toLowerCase().includes(query)) ||
                (data.specialty && data.specialty.toLowerCase().includes(query)) ||
                (data.bio && data.bio.toLowerCase().includes(query))
            );
            
            if (filteredArtists.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 60px 20px; opacity: 0.7;">
                        <div style="font-size: 60px; margin-bottom: 20px;">🔍</div>
                        <h3>Aucun résultat</h3>
                        <p>Aucun artiste ne correspond à "${query}"</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = filteredArtists.map(([name, data]) => `
                <div class="admin-item-card" data-artist-name="${name}">
                    <div class="admin-item-image admin-artist-avatar">
                        ${data.avatar}
                    </div>
                    <div class="admin-item-info">
                        <div class="admin-item-title">${name}</div>
                        <div class="admin-item-subtitle">${data.specialty}</div>
                        <div class="admin-item-meta">
                            <span>📧 ${data.email}</span>
                            <span>👥 ${data.followers} followers</span>
                            <span>🖼️ ${data.works} œuvres</span>
                            <span>⭐ ${data.rating}/5</span>
                        </div>
                    </div>
                    <div class="admin-item-actions">
                        <button class="admin-btn-edit" onclick="editArtist('${name}')">✏️ Modifier</button>
                        <button class="admin-btn-delete" onclick="deleteArtist('${name}')">🗑️ Supprimer</button>
                    </div>
                </div>
            `).join('');
        }
        
        function clearArtistsSearch() {
            document.getElementById('artistsSearchInput').value = '';
            renderAdminArtists();
            showToast('🔄 Recherche effacée');
        }

        // ========== ADMIN OVERVIEW DASHBOARD ==========
        
        function renderAdminOverview() {
            const products = getProducts();
            const artists = Object.keys(artistsData);
            // Utilise la variable globale newsItems (chargée depuis le serveur)
            
            // Update statistics
            document.getElementById('overviewArtworksCount').textContent = products.length;
            document.getElementById('overviewArtistsCount').textContent = artists.length;
            document.getElementById('overviewNewsCount').textContent = newsItems.length;
            
            // Calculate total value
            const totalValue = products.reduce((sum, p) => sum + (p.price || 0), 0);
            document.getElementById('overviewTotalValue').textContent = formatPrice(totalValue).replace(' FCFA', '');
            
            // Render top artists
            renderTopArtists();
            
            // Render recent activity
            renderRecentActivity();
        }
        
        function renderTopArtists() {
            const container = document.getElementById('topArtistsList');
            const products = getProducts();
            
            // Count artworks per artist
            const artistCounts = {};
            products.forEach(p => {
                artistCounts[p.artist] = (artistCounts[p.artist] || 0) + 1;
            });
            
            // Sort by count
            const sortedArtists = Object.entries(artistCounts)
                .sort((a, b) => b[1] - a[1])
                .slice(0, 5);
            
            if (sortedArtists.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 20px; opacity: 0.6;">
                        <div style="font-size: 2em; margin-bottom: 10px;">👨‍🎨</div>
                        <p>Aucun artiste pour le moment</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = sortedArtists.map(([name, count], index) => {
                const artistData = artistsData[name] || {};
                const medal = index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : `${index + 1}`;
                
                return `
                    <div class="top-artist-item">
                        <div class="top-artist-rank">${medal}</div>
                        <div class="top-artist-avatar" style="overflow:hidden;">
                            ${artistData.avatar && (artistData.avatar.startsWith('http') || artistData.avatar.startsWith('data:'))
                                ? buildMiniAvatar({ profile_image: artistData.avatar, avatar: '🎨', avatar_style: artistData.avatarStyle || 'slices', name } 60, null)
                                : '<div style="font-size:24px;display:flex;align-items:center;justify-content:center;width:100%;height:100%;">👤</div>'
                            }
                        </div>
                        <div class="top-artist-info">
                            <div class="top-artist-name">${name}</div>
                            <div class="top-artist-count">${count} œuvre${count > 1 ? 's' : ''}</div>
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        function renderRecentActivity() {
            const container = document.getElementById('recentActivityList');
            const activities = safeStorage.get('arkyl_admin_activity', []);
            
            if (activities.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 20px; opacity: 0.6;">
                        <div style="font-size: 2em; margin-bottom: 10px;">⏱️</div>
                        <p>Aucune activité récente</p>
                    </div>
                `;
                return;
            }
            
            const recentActivities = activities.slice(0, 5);
            
            container.innerHTML = recentActivities.map(activity => `
                <div class="recent-activity-item">
                    <div class="activity-icon">${activity.icon}</div>
                    <div class="activity-details">
                        <div class="activity-title">${activity.title}</div>
                        <div class="activity-time">${formatActivityTime(activity.timestamp)}</div>
                    </div>
                </div>
            `).join('');
        }
        
        function logAdminActivity(icon, title) {
            const activities = safeStorage.get('arkyl_admin_activity', []);
            activities.unshift({
                icon,
                title,
                timestamp: Date.now()
            });
            
            // Keep only last 20 activities
            if (activities.length > 20) {
                activities.length = 20;
            }
            
            safeStorage.set('arkyl_admin_activity', activities);
        }
        
        function formatActivityTime(timestamp) {
            const now = Date.now();
            const diff = now - timestamp;
            const minutes = Math.floor(diff / 60000);
            const hours = Math.floor(diff / 3600000);
            const days = Math.floor(diff / 86400000);
            
            if (minutes < 1) return 'À l\'instant';
            if (minutes < 60) return `Il y a ${minutes} min`;
            if (hours < 24) return `Il y a ${hours}h`;
            if (days < 7) return `Il y a ${days}j`;
            
            return new Date(timestamp).toLocaleDateString('fr-FR');
        }

        // ========== ARTWORKS MANAGEMENT ==========
        
        function renderAdminArtworks() {
            const container = document.getElementById('adminArtworksList');
            const products = getProducts();
            
            if (products.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 60px 20px; opacity: 0.7;">
                        <div style="font-size: 60px; margin-bottom: 20px;">🖼️</div>
                        <h3>Aucune œuvre</h3>
                        <p>Ajoutez votre première œuvre pour commencer</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = products.map(product => `
                <div class="admin-item-card" id="artwork-${product.id}">
                    <div class="admin-item-image">
                        <img loading="lazy" src="${product.image}" alt="${product.title}" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect fill=%22%23ddd%22 width=%22100%22 height=%22100%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2240%22%3E🎨%3C/text%3E%3C/svg%3E'">
                    </div>
                    <div class="admin-item-info">
                        <div class="admin-item-title">${product.title}</div>
                        <div class="admin-item-subtitle">par ${product.artist}</div>
                        <div class="admin-item-meta">
                            <span>💰 ${formatPrice(product.price)}</span>
                            <span>🏷️ ${product.category}</span>
                            <span>🏆 ${product.badge}</span>
                        </div>
                    </div>
                    <div class="admin-item-actions">
                        <button class="admin-btn-edit" onclick="editArtwork(${product.id})">✏️ Modifier</button>
                        <button class="admin-btn-delete" onclick="deleteArtistArtwork(${product.id})">🗑️ Supprimer</button>
                    </div>
                </div>
            `).join('');
        }

        function openAddArtworkModal() {
            document.getElementById('artworkModalTitle').textContent = '➕ Nouvelle Œuvre';
            document.getElementById('artworkTitle').value = '';
            document.getElementById('artworkArtist').value = '';
            document.getElementById('artworkCategory').value = 'Peinture';
            document.getElementById('artworkPrice').value = '';
            document.getElementById('artworkImage').value = '';
            document.getElementById('artworkBadge').value = 'Nouveau';
            document.getElementById('artworkEditId').value = '';
            
            // Populate artist dropdown
            populateArtistDropdown();
            
            document.getElementById('adminArtworkModal').style.display = 'flex';
        }

        function populateArtistDropdown() {
            const select = document.getElementById('artworkArtist');
            const artistNames = Object.keys(artistsData);
            
            select.innerHTML = '<option value="">-- Sélectionner un artiste --</option>' + 
                artistNames.map(name => `<option value="${name}">${name}</option>`).join('');
        }

        function editArtwork(id) {
            const products = getProducts();
            const product = products.find(p => p.id === id);
            
            if (!product) return;
            
            document.getElementById('artworkModalTitle').textContent = '✏️ Modifier l\'œuvre';
            document.getElementById('artworkTitle').value = product.title;
            document.getElementById('artworkArtist').value = product.artist;
            document.getElementById('artworkCategory').value = product.category;
            document.getElementById('artworkPrice').value = product.price;
            document.getElementById('artworkImage').value = product.image;
            document.getElementById('artworkBadge').value = product.badge;
            document.getElementById('artworkEditId').value = id;
            
            populateArtistDropdown();
            
            document.getElementById('adminArtworkModal').style.display = 'flex';
        }

        function saveAdminArtwork() {
            const title = document.getElementById('artworkTitle').value.trim();
            const artist = document.getElementById('artworkArtist').value;
            const category = document.getElementById('artworkCategory').value;
            const price = parseInt(document.getElementById('artworkPrice').value);
            const image = document.getElementById('artworkImage').value.trim();
            const badge = document.getElementById('artworkBadge').value;
            const editId = document.getElementById('artworkEditId').value;
            
            if (!title || !artist || !price || !image) {
                showToast('⚠️ Veuillez remplir tous les champs');
                return;
            }
            
            const products = getProducts();
            
            if (editId) {
                // Update existing artwork
                const index = products.findIndex(p => p.id === parseInt(editId));
                if (index !== -1) {
                    products[index] = {
                        ...products[index],
                        title, artist, category, price, image, badge
                    };
                    showToast('✅ Œuvre mise à jour avec succès');
                    logAdminActivity('✏️', `Œuvre modifiée: ${title}`);
                }
            } else {
                // Add new artwork
                const newId = Math.max(...products.map(p => p.id), 0) + 1;
                products.push({
                    id: newId,
                    title, artist, category, price, image, badge
                });
                showToast('✅ Nouvelle œuvre ajoutée');
                logAdminActivity('➕', `Nouvelle œuvre: ${title}`);
            }
            
            saveProducts(products);
            closeArtworkModal();
            renderAdminArtworks();
            
            // Update the main gallery if it's visible
            if (document.getElementById('homePage').classList.contains('active')) {
                (typeof afficherOeuvresFiltrees === 'function' && window.toutesLesOeuvres?.length > 0) ? afficherOeuvresFiltrees() : (typeof chargerLaVraieGalerie === 'function' ? chargerLaVraieGalerie() : null);
            }
        }

        function deleteArtwork(id) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cette œuvre ?')) return;
            
            let products = getProducts();
            const artwork = products.find(p => p.id === id);
            const artworkTitle = artwork ? artwork.title : 'Œuvre';
            
            products = products.filter(p => p.id !== id);
            saveProducts(products);
            
            showToast('🗑️ Œuvre supprimée');
            logAdminActivity('🗑️', `Œuvre supprimée: ${artworkTitle}`);
            renderAdminArtworks();
            
            // Update the main gallery if it's visible
            if (document.getElementById('homePage').classList.contains('active')) {
                (typeof afficherOeuvresFiltrees === 'function' && window.toutesLesOeuvres?.length > 0) ? afficherOeuvresFiltrees() : (typeof chargerLaVraieGalerie === 'function' ? chargerLaVraieGalerie() : null);
            }
        }

        function closeArtworkModal() {
            document.getElementById('adminArtworkModal').style.display = 'none';
        }

        // ========== ARTISTS MANAGEMENT ==========
        
        function renderAdminArtists() {
            const container = document.getElementById('adminArtistsList');
            const artists = Object.entries(artistsData);
            
            if (artists.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 60px 20px; opacity: 0.7;">
                        <div style="font-size: 60px; margin-bottom: 20px;">👨‍🎨</div>
                        <h3>Aucun artiste</h3>
                        <p>Ajoutez votre premier artiste pour commencer</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = artists.map(([name, data]) => `
                <div class="admin-item-card" data-artist-name="${name}">
                    <div class="admin-item-image admin-artist-avatar">
                        ${data.avatar}
                    </div>
                    <div class="admin-item-info">
                        <div class="admin-item-title">${name}</div>
                        <div class="admin-item-subtitle">${data.specialty}</div>
                        <div class="admin-item-meta">
                            <span>📧 ${data.email}</span>
                            <span>👥 ${data.followers} followers</span>
                            <span>🖼️ ${data.works} œuvres</span>
                            <span>⭐ ${data.rating}/5</span>
                        </div>
                    </div>
                    <div class="admin-item-actions">
                        <button class="admin-btn-edit" onclick="editArtist('${name}')">✏️ Modifier</button>
                        <button class="admin-btn-delete" onclick="deleteArtist('${name}')">🗑️ Supprimer</button>
                    </div>
                </div>
            `).join('');
        }

        function openAddArtistModal() {
            document.getElementById('artistModalTitle').textContent = '➕ Nouvel Artiste';
            document.getElementById('artistName').value = '';
            document.getElementById('artistEmail').value = '';
            document.getElementById('artistAvatar').value = '👨🏿‍🎨';
            document.getElementById('artistSpecialty').value = '';
            document.getElementById('artistBio').value = '';
            document.getElementById('artistEditName').value = '';
            
            document.getElementById('adminArtistModal').style.display = 'flex';
        }

        function editArtist(name) {
            const artist = artistsData[name];
            if (!artist) return;
            
            document.getElementById('artistModalTitle').textContent = '✏️ Modifier l\'artiste';
            document.getElementById('artistName').value = name;
            document.getElementById('artistEmail').value = artist.email;
            document.getElementById('artistAvatar').value = artist.avatar;
            document.getElementById('artistSpecialty').value = artist.specialty;
            document.getElementById('artistBio').value = artist.bio;
            document.getElementById('artistEditName').value = name;
            
            document.getElementById('adminArtistModal').style.display = 'flex';
        }

        function saveArtist() {
            const name = document.getElementById('artistName').value.trim();
            const email = document.getElementById('artistEmail').value.trim();
            const avatar = document.getElementById('artistAvatar').value;
            const specialty = document.getElementById('artistSpecialty').value.trim();
            const bio = document.getElementById('artistBio').value.trim();
            const editName = document.getElementById('artistEditName').value;
            
            if (!name || !email || !specialty || !bio) {
                showToast('⚠️ Veuillez remplir tous les champs');
                return;
            }
            
            if (editName && editName !== name) {
                // Rename artist - delete old and create new
                delete artistsData[editName];
                
                // Update artworks with the new artist name
                let products = getProducts();
                products = products.map(p => {
                    if (p.artist === editName) {
                        return { ...p, artist: name };
                    }
                    return p;
                });
                saveProducts(products);
            }
            
            artistsData[name] = {
                email,
                avatar,
                profileImage: artistsData[editName]?.profileImage || null,
                specialty,
                bio,
                followers: artistsData[editName]?.followers || 0,
                works: artistsData[editName]?.works || 0,
                rating: artistsData[editName]?.rating || 0
            };
            
            saveArtistsData();
            closeArtistModal();
            renderAdminArtists();
            
            logAdminActivity(editName ? '✏️' : '➕', editName ? `Artiste modifié: ${name}` : `Nouvel artiste: ${name}`);
            showToast(editName ? '✅ Artiste mis à jour' : '✅ Nouvel artiste ajouté');
        }

        function deleteArtist(name) {
            // Check if artist has artworks
            const products = getProducts();
            const hasArtworks = products.some(p => p.artist === name);
            
            if (hasArtworks) {
                if (!confirm(`⚠️ L'artiste "${name}" a des œuvres associées. Voulez-vous vraiment le supprimer ? Les œuvres seront conservées.`)) {
                    return;
                }
            } else {
                if (!confirm(`Êtes-vous sûr de vouloir supprimer l'artiste "${name}" ?`)) {
                    return;
                }
            }
            
            delete artistsData[name];
            saveArtistsData();
            
            logAdminActivity('🗑️', `Artiste supprimé: ${name}`);
            showToast('🗑️ Artiste supprimé');
            renderAdminArtists();
        }

        function closeArtistModal() {
            document.getElementById('adminArtistModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('artist-edit-modal')) {
                event.target.style.display = 'none';
            }
        };

        function backToClientMode() {
            // Close user menu
            document.getElementById('userMenuDropdown').classList.remove('show');
            
            // If we're on admin page, go back to main gallery
            if (window.location.pathname.includes('arkyl_admin.html')) {
                window.location.href = 'arkyl_fixed_v3.html';
            } else {
                // If already on main page, just navigate to home
                navigateTo('home');
                showToast('🏪 Retour à la galerie');
            }
        }

        function goToOrders() {
            // Close user menu
            document.getElementById('userMenuDropdown').classList.remove('show');
            
            // Navigate to orders page
            navigateTo('orders');
        }

        function goToAdminOrders() {
            // Close user menu
            document.getElementById('userMenuDropdown').classList.remove('show');
            
            // Navigate to admin orders page
            navigateTo('adminOrders');
        }

        // Close user menu when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userMenuDropdown');
            const profileBtn = document.getElementById('userProfileBtn');
            
            if (dropdown && profileBtn) {
                if (!dropdown.contains(event.target) && !profileBtn.contains(event.target)) {
                    dropdown.classList.remove('show');
                }
            }
        });

        // ==================== NAVIGATION ====================
        function navigateTo(page) {
            const targetPage = document.getElementById(page + 'Page');
            if (!targetPage) {
                console.warn('navigateTo: page introuvable →', page + 'Page');
                return;
            }
            document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
            targetPage.classList.add('active');
            
            if (page === 'favorites') renderFavorites();
            else if (page === 'cart') renderCart();
            else if (page === 'orders') renderOrders();
            else if (page === 'adminOrders') renderAdminOrders();
            else if (page === 'home') {
                // renderProducts() est désactivée — on utilise chargerLaVraieGalerie()
                if (window.toutesLesOeuvres && window.toutesLesOeuvres.length > 0) {
                    if (typeof afficherOeuvresFiltrees === 'function') afficherOeuvresFiltrees();
                } else if (typeof chargerLaVraieGalerie === 'function') {
                    chargerLaVraieGalerie();
                }
            }
            else if (page === 'myArtists') renderMyArtistsPage(); // Appel async géré automatiquement
            // productDetail and artistDetail don't need special handling
            
            window.scrollTo(0, 0);
        }

        // ==================== PRODUCTS ====================
        // ============ FONCTION DÉSACTIVÉE - REMPLACÉE PAR chargerLaVraieGalerie() ============
        async function renderProducts() {
            // ⚠️ FONCTION DÉSACTIVÉE : La galerie est maintenant gérée par chargerLaVraieGalerie()
            // Cette fonction est conservée vide pour éviter les erreurs des autres scripts qui l'appellent
            console.log('ℹ️ renderProducts() désactivée - utiliser chargerLaVraieGalerie() à la place');
            return;
        }

        // Tout vient de l'API uniquement

        // ============ GESTION DES LIKES AVEC DONNÉES CENTRALISÉES ============
        async function toggleLikeDB(event, artworkId) {
            event.stopPropagation();
            
            // Vérifier si l'utilisateur est connecté
            if (!currentUser) {
                alert("⚠️ Connectez-vous pour aimer une œuvre !");
                return;
            }
            
            const btnElement = event.target;
            
            try {
                // Chercher si le like existe déjà
                const likeIndex = appData.interactions.likes.findIndex(
                    like => like.artwork_id === artworkId && like.user_email === currentUser.email
                );
                
                if (likeIndex !== -1) {
                    // Supprimer le like
                    appData.interactions.likes.splice(likeIndex, 1);
                    btnElement.innerHTML = '🤍';
                    showToast('Retiré des favoris');
                } else {
                    // Ajouter le like
                    appData.interactions.likes.push({
                        artwork_id: artworkId,
                        user_email: currentUser.email,
                        created_at: new Date().toISOString()
                    });
                    btnElement.innerHTML = '❤️';
                    showToast('Ajouté aux favoris ❤️');
                }
                
                // Sauvegarder les modifications
                saveAppData();
                
            } catch (error) {
                console.error('❌ Erreur lors du like:', error);
            }
        }

        // Charger les likes de l'utilisateur
        async function loadUserLikes() {
            if (!currentUser) return;
            
            try {
                const response = fetch(`api_interactions.php?action=get_user_likes&user_email=${encodeURIComponent(currentUser.email)}`);
                const result = await response.json();
                
                if (result.success && result.data) {
                    // Mettre à jour les cœurs dans l'interface
                    result.data.forEach(artworkId => {
                        const likeBtn = document.querySelector(`.like-button[onclick*="${artworkId}"]`);
                        if (likeBtn) {
                            likeBtn.innerHTML = '❤️';
                        }
                    });
                }
            } catch (error) {
                console.log('⚠️ Impossible de charger les likes');
            }
        }

        

        // Multi-sélection catégories

        // Init opacity boutons catégorie
        document.addEventListener('DOMContentLoaded', function() {
            const cats = document.getElementById('categoryBtns');
            if (cats) {
                cats.querySelectorAll('.filter-btn').forEach(b => b.style.opacity = '0');
                cats.dataset.open = '0';
            }
        });
        function selectCategory(btn) {
            const cats = document.getElementById('categoryBtns');
            const allBtns = cats ? cats.querySelectorAll('.filter-btn[data-cat]') : [];
            const cat = btn.dataset.cat;
            // Sélection unique : désactiver tous puis activer celui-ci
            // Si déjà actif et pas "all" => désélectionner (= tout afficher)
            if (btn.classList.contains('active') && cat !== 'all') {
                btn.classList.remove('active');
                window.currentCategory = 'all';
                currentCategory = 'all';
            } else {
                allBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                window.currentCategory = cat;
                currentCategory = cat;
            }
            if (typeof window.afficherOeuvresFiltrees === 'function') {
                window.afficherOeuvresFiltrees();
            } else {
                (typeof afficherOeuvresFiltrees === 'function' && window.toutesLesOeuvres?.length > 0) ? afficherOeuvresFiltrees() : (typeof chargerLaVraieGalerie === 'function' ? chargerLaVraieGalerie() : null);
            }
        }

        function toggleCategoryBtns(e) {
            if (e) e.stopPropagation();
            const cats = document.getElementById('categoryBtns');
            const btns = Array.from(cats.querySelectorAll('.filter-btn'));
            const isOpen = cats.dataset.open === '1';
            const n = btns.length;
            // Directions douces en arc horizontal
            const directions = [
                { tx: '-80px', ty: '-8px'  }
                { tx: '-60px', ty: '-25px' }
                { tx: '-35px', ty: '-38px' }
                { tx: '0px',   ty: '-45px' }
                { tx: '35px',  ty: '-38px' }
                { tx: '60px',  ty: '-25px' }
                { tx: '80px',  ty: '-8px'  }
                { tx: '80px',  ty: '18px'  }
                { tx: '55px',  ty: '35px'  }
                { tx: '20px',  ty: '45px'  }
                { tx: '-20px', ty: '45px'  }
            ];
            if (isOpen) {
                // Fermeture : blur + scale + glissement
                cats.dataset.open = '0';
                btns.forEach((btn, i) => {
                    const ri = n - 1 - i; // ordre inverse
                    const d = directions[ri % directions.length];
                    btn.style.setProperty('--tx', d.tx);
                    btn.style.setProperty('--ty', d.ty);
                    btn.style.pointerEvents = 'none';
                    btn.style.animationDelay = (i * 0.025) + 's';
                    btn.style.animation = 'none';
                    requestAnimationFrame(() => {
                        btn.style.animation = `btnEclate 0.4s cubic-bezier(0.4,0,0.6,1) forwards`;
                        btn.style.animationDelay = (i * 0.025) + 's';
                    });
                });
                setTimeout(() => {
                    btns.forEach(btn => {
                        btn.style.animation = '';
                        btn.style.opacity = '0';
                        btn.style.pointerEvents = '';
                    });
                } 500);
            } else {
                // Ouverture : depuis la direction avec spring
                cats.dataset.open = '1';
                btns.forEach((btn, i) => {
                    const d = directions[i % directions.length];
                    btn.style.setProperty('--tx', d.tx);
                    btn.style.setProperty('--ty', d.ty);
                    btn.style.opacity = '0';
                    btn.style.animation = 'none';
                    btn.style.pointerEvents = 'auto';
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            btn.style.animation = `btnEntre 0.5s cubic-bezier(0.34,1.4,0.64,1) forwards`;
                            btn.style.animationDelay = (i * 0.04) + 's';
                        });
                    });
                });
            }
        }
        function closeFilterPanel() {
            const cats = document.getElementById('categoryBtns');
            if (cats && cats.dataset.open === '1') toggleCategoryBtns(null);
        }

        function filterProducts(category, btn) {
            const cats = document.getElementById('categoryBtns');
            const allBtns = cats ? cats.querySelectorAll('.filter-btn') : [];
            if (category === 'all') {
                allBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                window.currentCategory = 'all';
            } else if (btn.classList.contains('active')) {
                btn.classList.remove('active');
                window.currentCategory = 'all';
            } else {
                allBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                window.currentCategory = category;
            }
            currentCategory = window.currentCategory;
            if (typeof window.afficherOeuvresFiltrees === 'function') {
                window.afficherOeuvresFiltrees();
            } else {
                (typeof afficherOeuvresFiltrees === 'function' && window.toutesLesOeuvres?.length > 0) ? afficherOeuvresFiltrees() : (typeof chargerLaVraieGalerie === 'function' ? chargerLaVraieGalerie() : null);
            }
        }

        // ==================== FONCTION RAFRAÎCHIR LA GALERIE ====================

        // ===== ANIMATION CANVAS CROIX =====
        (function() {
            const SHAPES = ['cross', 'square', 'triangle', 'circle', 'x', 'star', 'diamond', 'cross'];
            const SHAPE_LABELS = ['✝', '■', '▲', '●', '✕', '★', '◆', '✝'];
            const DURATION = 400; // ms par forme
            let animFrame = null;
            let animStartTime = null;
            let isAnimating = false;
            let shapeIndex = 0;
            let morphProgress = 0; // 0→1 entre deux formes

            function getGold(alpha) {
                return `rgba(212,175,55,${alpha})`;
            }

            // Retourne les points d'une forme en coordonnées [-1,1]
            function getShapePoints(shape, t) {
                const pts = [];
                switch(shape) {
                    case 'cross': {
                        // Croix latine : 12 points
                        const w=0.28, h=0.85, arm=0.28, armY=-0.12;
                        return [
                            [-w, -h],[w,-h],[w,armY-arm],[1-0.1,armY-arm],
                            [1-0.1,armY+arm],[w,armY+arm],[w,h],[-w,h],
                            [-w,armY+arm],[-1+0.1,armY+arm],[-1+0.1,armY-arm],[-w,armY-arm]
                        ];
                    }
                    case 'square': {
                        return [[-0.8,-0.8],[0.8,-0.8],[0.8,0.8],[-0.8,0.8]];
                    }
                    case 'triangle': {
                        return [[0,-0.9],[0.85,0.75],[-0.85,0.75]];
                    }
                    case 'circle': {
                        const n=12;
                        for(let i=0;i<n;i++){
                            const a=Math.PI*2*i/n - Math.PI/2;
                            pts.push([Math.cos(a)*0.82, Math.sin(a)*0.82]);
                        }
                        return pts;
                    }
                    case 'x': {
                        const s=0.28;
                        return [
                            [0,-s],[0.7-s,-0.85],[0.85,-0.7],[s,0],
                            [0.85,0.7],[0.7-s,0.85],[0,s],[-(0.7-s),0.85],
                            [-0.85,0.7],[-s,0],[-0.85,-0.7],[-(0.7-s),-0.85]
                        ];
                    }
                    case 'star': {
                        const n=5, r1=0.85, r2=0.38;
                        for(let i=0;i<n*2;i++){
                            const a=Math.PI*i/n - Math.PI/2;
                            const r=i%2===0?r1:r2;
                            pts.push([Math.cos(a)*r, Math.sin(a)*r]);
                        }
                        return pts;
                    }
                    case 'diamond': {
                        return [[0,-0.9],[0.65,0],[0,0.9],[-0.65,0]];
                    }
                }
                return [[0,0]];
            }

            function normalizePoints(pts, n) {
                // Interpoler pour avoir exactement n points
                if(pts.length === n) return pts;
                const result = [];
                for(let i=0;i<n;i++){
                    const t = i/n * pts.length;
                    const i0 = Math.floor(t) % pts.length;
                    const i1 = (i0+1) % pts.length;
                    const f = t - Math.floor(t);
                    result.push([
                        pts[i0][0]*(1-f) + pts[i1][0]*f,
                        pts[i0][1]*(1-f) + pts[i1][1]*f
                    ]);
                }
                return result;
            }

            function lerpPts(a, b, t) {
                const n = Math.max(a.length, b.length);
                const an = normalizePoints(a, n);
                const bn = normalizePoints(b, n);
                // easeInOutCubic
                const e = t < 0.5 ? 4*t*t*t : 1-Math.pow(-2*t+2,3)/2;
                return an.map((p,i) => [
                    p[0]*(1-e)+bn[i][0]*e,
                    p[1]*(1-e)+bn[i][1]*e
                ]);
            }

            function drawJesusCross(ctx, size, glow) {
                ctx.clearRect(0,0,size,size);
                const s = size;
                const grad = ctx.createLinearGradient(0,0,s,s);
                grad.addColorStop(0, '#f5e6c8');
                grad.addColorStop(0.4, '#d4af37');
                grad.addColorStop(1, '#8B6914');
                if(glow>0){ ctx.shadowColor='#ffe066'; ctx.shadowBlur=8*glow; }
                // Montant vertical (légèrement arrondi)
                const vx=s*0.38, vw=s*0.24;
                const vy=s*0.04, vh=s*0.76;
                // Bras horizontal
                const hx=s*0.12, hw=s*0.76;
                const hy=s*0.24, hh=s*0.22;
                // Pied effilé
                // Dessin
                ctx.beginPath();
                // montant haut
                ctx.moveTo(vx, vy);
                ctx.lineTo(vx+vw, vy);
                // bras droit haut
                ctx.lineTo(vx+vw, hy);
                ctx.lineTo(hx+hw, hy);
                ctx.lineTo(hx+hw, hy+hh);
                // retour montant droit bas
                ctx.lineTo(vx+vw, hy+hh);
                // bas montant → pied effilé
                ctx.lineTo(vx+vw, vy+vh);
                // pointe du pied
                ctx.lineTo(s/2, s*0.97);
                ctx.lineTo(vx, vy+vh);
                // bras gauche bas
                ctx.lineTo(vx, hy+hh);
                ctx.lineTo(hx, hy+hh);
                ctx.lineTo(hx, hy);
                ctx.lineTo(vx, hy);
                ctx.closePath();
                ctx.fillStyle = grad;
                ctx.fill();
                // Reflet lumineux
                ctx.fillStyle = 'rgba(255,255,255,0.28)';
                ctx.fillRect(s/2+s*0.02, vy+2, s*0.06, s*0.18);
                if(glow>0) ctx.shadowBlur=0;
            }

            function drawShape(ctx, pts, size, glow, isJesusCross) {
                if(isJesusCross) { drawJesusCross(ctx, size, glow); return; }
                const cx=size/2, cy=size/2, r=size/2*0.82;
                ctx.clearRect(0,0,size,size);
                if(glow>0) {
                    ctx.save();
                    ctx.shadowColor = '#ffe066';
                    ctx.shadowBlur = 8*glow;
                }
                const grad = ctx.createLinearGradient(0,0,size,size);
                grad.addColorStop(0, '#f5e6c8');
                grad.addColorStop(0.4, '#d4af37');
                grad.addColorStop(1, '#8B6914');
                ctx.beginPath();
                pts.forEach(([x,y],i)=>{
                    const px=cx+x*r, py=cy+y*r;
                    i===0?ctx.moveTo(px,py):ctx.lineTo(px,py);
                });
                ctx.closePath();
                ctx.fillStyle=grad;
                ctx.fill();
                ctx.strokeStyle='rgba(255,240,180,0.5)';
                ctx.lineWidth=0.8;
                ctx.stroke();
                if(glow>0) ctx.restore();
            }

            function animate(now) {
                const canvas = document.getElementById('crossCanvas');
                if(!canvas) return;
                const ctx = canvas.getContext('2d');
                const size = canvas.width;

                if(!animStartTime) animStartTime = now;
                const elapsed = now - animStartTime;
                const totalShapes = SHAPES.length;
                const totalDuration = DURATION * (totalShapes - 1);

                if(elapsed >= totalDuration) {
                    // Dessiner la croix finale
                    drawJesusCross(ctx, size, 0);
                    isAnimating = false;
                    return;
                }

                const progress = elapsed / DURATION;
                const si = Math.min(Math.floor(progress), totalShapes - 2);
                const t = progress - si;

                const shapeA = SHAPES[si];
                const shapeB = SHAPES[si+1];
                const ptsA = getShapePoints(shapeA);
                const ptsB = getShapePoints(shapeB);
                const morphed = lerpPts(ptsA, ptsB, t);
                const glow = Math.sin(t * Math.PI) * 0.8;
                // Si on repart/arrive sur cross → dessiner Jésus cross en fin
                const isJesusCross = (shapeA === 'cross' && t < 0.15) || (shapeB === 'cross' && t > 0.85);
                drawShape(ctx, morphed, size, glow, isJesusCross);

                animFrame = requestAnimationFrame(animate);
            }

            window.startCrossAnimation = function() {
                if(isAnimating) return;
                isAnimating = true;
                animStartTime = null;
                if(animFrame) cancelAnimationFrame(animFrame);
                animFrame = requestAnimationFrame(animate);
            };

            // Dessiner la croix par défaut au chargement
            document.addEventListener('DOMContentLoaded', function() {
                const canvas = document.getElementById('crossCanvas');
                if(!canvas) return;
                const ctx = canvas.getContext('2d');
                drawJesusCross(ctx, canvas.width, 0);
            });
        })();
        // ===== FIN ANIMATION CANVAS =====

        async function rafraichirGalerie(event) {
            const btn = document.getElementById('rafraichirBtn');
            if (btn) btn.disabled = true;

            // Lancer l'animation canvas
            if (typeof window.startCrossAnimation === 'function') {
                window.startCrossAnimation();
            }

            try {
                console.log('🔄 Rafraîchissement de la galerie...');
                if (typeof chargerLaVraieGalerie === 'function') {
                    chargerLaVraieGalerie();
                    showToast('✅ Galerie rafraîchie !');
                } else {
                    location.reload();
                }
            } catch (error) {
                console.error('❌ Erreur rafraîchissement:', error);
                showToast('❌ Erreur lors du rafraîchissement');
            } finally {
                if (btn) btn.disabled = false;
            }
        }

        async function toggleFavorite(event, productId) {
            event.stopPropagation();

            // Cibler le bouton parent correct (pas un enfant SVG/span)
            const btn = event.currentTarget || event.target.closest('button') || event.target;

           
            const wasAlreadyFav = favorites.includes(productId);
            const adding = !wasAlreadyFav;

            btn.textContent = adding ? '❤️' : '🤍';
            btn.classList.remove('fav-btn-pop', 'fav-btn-unpop');
            void btn.offsetWidth; // reset animation
            btn.classList.add(adding ? 'fav-btn-pop' : 'fav-btn-unpop');
            if (adding) spawnHeartParticles(btn, true);
            setTimeout(() => btn.classList.remove('fav-btn-pop', 'fav-btn-unpop'), 700);

            // Mise à jour locale immédiate
            if (adding) {
                if (!favorites.includes(productId)) favorites.push(productId);
            } else {
                const idx = favorites.indexOf(productId);
                if (idx > -1) favorites.splice(idx, 1);
            }
            safeStorage.set('arkyl_favorites', favorites);
            updateBadges();

            // Appel API en arrière-plan (sans bloquer l'UI)
            try {
                const userId = currentUser?.id || createGuestSession();
                const response = fetch('https://arkyl-galerie.onrender.com/api_ajouter_favoris.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                    body: JSON.stringify({ artwork_id: productId, user_id: userId })
                });
                const responseText = await response.text();
                const result = JSON.parse(responseText);

                if (result.success) {
                    // Synchro avec le serveur (au cas où il diverge du local)
                    const serverFav = result.is_favorite;
                    if (serverFav !== adding) {
                        if (serverFav && !favorites.includes(productId)) favorites.push(productId);
                        else if (!serverFav) { const i = favorites.indexOf(productId); if (i > -1) favorites.splice(i, 1); }
                        safeStorage.set('arkyl_favorites', favorites);
                        updateBadges();
                    }
                    showToast(result.message || (adding ? '❤️ Ajouté aux favoris' : '🤍 Retiré des favoris'));
                    if (adding) {
                        const product = getProducts().find(p => p.id === productId);
                        if (product) addNotification('Favori ajouté', `"${product.title}" ajouté aux favoris`);
                    }
                } else {
                    showToast(adding ? '❤️ Ajouté aux favoris' : '🤍 Retiré des favoris');
                }
            } catch (error) {
                // API down → on garde le state local, toast discret
                console.warn('API favoris indisponible, état local conservé');
                showToast(adding ? '❤️ Ajouté aux favoris' : '🤍 Retiré des favoris');
            }

            // Refresh UI APRÈS l'animation (délai 400ms pour qu'on la voie)
            setTimeout(() => {
                if (typeof afficherOeuvresFiltrees === 'function' && window.toutesLesOeuvres?.length > 0) {
                    afficherOeuvresFiltrees();
                } else if (typeof chargerLaVraieGalerie === 'function') {
                    chargerLaVraieGalerie();
                }
            } 400);
        }

        // ===== UTILITAIRES D'ANIMATION =====

        function spawnCartParticles(btn) {
            const rect = btn.getBoundingClientRect();
            const cx = rect.left + rect.width / 2;
            const cy = rect.top + rect.height / 2;
            const items = ['🛒', '✨', '⭐', '🛍️'];
            for (let i = 0; i < 6; i++) {
                const el = document.createElement('span');
                el.className = 'cart-particle';
                el.textContent = items[i % items.length];
                const angle = (i / 6) * Math.PI * 2;
                const dist = 55 + Math.random() * 40;
                el.style.cssText = `left:${cx}px; top:${cy}px; --tx:${Math.cos(angle)*dist}px; --ty:${Math.sin(angle)*dist - 30}px; animation-delay:${i * 40}ms;`;
                document.body.appendChild(el);
                setTimeout(() => el.remove(), 1100);
            }
        }

        function spawnHeartParticles(btn, adding) {
            if (!adding) return;
            const rect = btn.getBoundingClientRect();
            const cx = rect.left + rect.width / 2;
            const cy = rect.top + rect.height / 2;
            for (let i = 0; i < 7; i++) {
                const el = document.createElement('span');
                el.className = 'heart-particle';
                el.textContent = ['❤️','💖','💗','💕'][i % 4];
                const angle = -Math.PI / 2 + (Math.random() - 0.5) * Math.PI * 1.4;
                const dist = 40 + Math.random() * 50;
                el.style.cssText = `left:${cx}px; top:${cy}px; --tx:${Math.cos(angle)*dist}px; --ty:${Math.sin(angle)*dist}px; --rot:${(Math.random()-0.5)*60}deg; animation-delay:${i * 50}ms; font-size:${10+Math.random()*10}px;`;
                document.body.appendChild(el);
                setTimeout(() => el.remove(), 1200);
            }
        }

        async function addToCart(event, productId) {
            event.stopPropagation();

            // Utilisateur non connecté → impossible de sauvegarder le panier
            if (!currentUser?.id && !currentUser?.googleId && !currentUser?.email) {
                showToast('🔒 Connectez-vous pour ajouter au panier');
                setTimeout(() => {
                    const googleBtn = document.getElementById('googleLoginBtn');
                    if (googleBtn) googleBtn.click();
                } 800);
                return;
            }

            const btn = event.currentTarget || event.target.closest('button') || event.target;
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '⏳';
            btn.disabled = true;

            try {
                const response = fetch('https://arkyl-galerie.onrender.com/api_ajouter_panier.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                    body: JSON.stringify({ artwork_id: productId, user_id: currentUser.id || currentUser.googleId || currentUser.email, quantity: 1 })
                });

                const result = await response.json();

                if (result.success) {
                    // Chercher d'abord dans la vraie galerie API, puis fallback sur getProducts()
                    const allProducts = (window.toutesLesOeuvres && window.toutesLesOeuvres.length > 0)
                        ? window.toutesLesOeuvres
                        : getProducts();
                    const product = allProducts.find(p => String(p.id) === String(productId));
                    const existing = cartItems.find(i => String(i.id) === String(productId));
                    if (existing) {
                        if (existing.quantity >= 10) { showToast('⚠️ Maximum 10 articles par œuvre'); return; }
                        existing.quantity++;
                    } else if (product) {
                        // Normaliser les champs (l'API utilise image_url, le panier utilise image)
                        cartItems.push({
                            ...product,
                            id: String(product.id),
                            image: product.image || product.image_url || (product.photos && product.photos[0]) || '',
                            quantity: 1
                        });
                    } else {
                        // Produit introuvable localement — on l'ajoute avec les infos minimales
                        cartItems.push({ id: String(productId), title: 'Œuvre', price: 0, quantity: 1 });
                    }
                    updateBadges();

                    btn.innerHTML = '✓';
                    btn.classList.add('cart-added');
                    spawnCartParticles(btn);
                    setTimeout(() => btn.classList.remove('cart-added'), 700);
                    showToast(result.message || `✅ "${product?.title || 'Œuvre'}" ajouté au panier`);
                    if (product) addNotification('Panier mis à jour', `"${product.title}" ajouté au panier`);
                } else {
                    showToast('❌ ' + (result.message || "Impossible d'ajouter au panier"));
                }

            } catch (error) {
                console.warn('Erreur addToCart :', error.message);
                showToast('❌ Erreur de connexion. Réessayez.');
            } finally {
                setTimeout(() => { btn.innerHTML = originalHTML; btn.disabled = false; } 700);
            }
        }


        // Fonction pour créer une session guest si pas connecté
        function createGuestSession() {
            const guestId = 'guest_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            _memStore['guest_session_id'] = guestId;
            console.log('🆔 Session guest créée:', guestId);
            return guestId;
        }

        // ==================== FAVORITES ====================
        async function renderFavorites() {
            const container = document.getElementById('favoritesContainer');
            const emptyState = document.getElementById('emptyFavorites');

            // Loader
            container.style.display = 'grid';
            emptyState.style.display = 'none';
            container.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:60px 20px;opacity:0.7;">
                <div style="font-size:40px;margin-bottom:12px;">⏳</div>
                <div>Chargement de vos favoris...</div>
            </div>`;

            const userId = currentUser?.id || createGuestSession();

            try {
                const resp = fetch(
                    `https://arkyl-galerie.onrender.com/api_get_favoris.php?user_id=${encodeURIComponent(userId)}&t=${Date.now()}`
                );

                if (!data.success || !data.data || data.data.length === 0) {
                    // Fallback local
                    const allProducts = getProducts();
                    const localFavs = allProducts.filter(p => favorites.includes(p.id));
                    if (localFavs.length === 0) {
                        container.style.display = 'none';
                        emptyState.style.display = 'block';
                        return;
                    }
                    _renderFavoritesCards(container, localFavs.map(p => ({
                        id: p.id,
                        title: p.title,
                        price: p.price,
                        artist_name: p.artist,
                        badge: p.badge || 'Disponible',
                        image_url: p.image || p.photo,
                        photos: p.photos || (p.image ? [p.image] : [])
                    })));
                    return;
                }

                // Sync local favorites list avec le serveur
                favorites = data.data.map(f => f.id);
                safeStorage.set('arkyl_favorites', favorites);
                updateBadges();

                _renderFavoritesCards(container, data.data);

            } catch(e) {
                // Fallback local si réseau indisponible
                const allProducts = getProducts();
                const localFavs = allProducts.filter(p => favorites.includes(p.id));
                if (localFavs.length === 0) {
                    container.style.display = 'none';
                    emptyState.style.display = 'block';
                    return;
                }
                _renderFavoritesCards(container, localFavs.map(p => ({
                    id: p.id,
                    title: p.title,
                    price: p.price,
                    artist_name: p.artist,
                    badge: p.badge || 'Disponible',
                    image_url: p.image || p.photo,
                    photos: p.photos || (p.image ? [p.image] : [])
                })));
            }
        }

        function _renderFavoritesCards(container, products) {
            if (!products || products.length === 0) {
                container.style.display = 'none';
                document.getElementById('emptyFavorites').style.display = 'block';
                return;
            }
            container.style.display = 'grid';
            document.getElementById('emptyFavorites').style.display = 'none';

            container.innerHTML = products.map(product => {
                const photos = product.photos && product.photos.length > 0
                    ? product.photos
                    : (product.image_url ? [product.image_url] : []);
                const mainImg = photos[0] || null;
                const artistName = product.artist_name || product.artist || 'Artiste inconnu';

                const imageHTML = mainImg
                    ? `<img src="${mainImg}" alt="${product.title}"
                            style="width:100%;height:100%;object-fit:contain;background:rgba(0,0,0,0.2);border-radius:20px;"
                            loading="lazy"
                            onerror="this.style.display='none'">`
                    : `<div style="font-size:60px;display:flex;align-items:center;justify-content:center;height:100%;">🎨</div>`;

                return `
                <div class="product-card" onclick="viewProductDetailFromAPI(${product.id})">
                    <div class="product-image" style="position:relative;">
                        <span class="product-badge">${product.badge || 'Disponible'}</span>
                        <button class="like-button" onclick="toggleFavorite(event, ${product.id})">❤️</button>
                        ${imageHTML}
                    </div>
                    <div class="product-info">
                        <div class="product-title">${product.title}</div>
                        <div class="product-artist" onclick="viewArtistDetail(event, '${artistName}')">par ${artistName}</div>
                        <div class="product-footer">
                            <div class="product-price">${formatPrice(product.price)}</div>
                            <button class="add-cart-btn" onclick="addToCart(event, ${product.id})">+ Panier</button>
                        </div>
                    </div>
                </div>`;
            }).join('');
        }

        // ==================== UTILITAIRES IMAGE ====================
        function getDefaultEmoji(item) {
            // Retourner l'emoji du produit ou un emoji par défaut selon la catégorie
            if (item.emoji) return item.emoji;
            
            const categoryEmojis = {
                'Peinture': '🎨',
                'Sculpture': '🗿',
                'Abstrait': '🌈',
                'Plastique': '🎭',
                'Céramique': '🏺',
                'Textile': '🧵',
                'Photo': '📸'
            };
            
            return categoryEmojis[item.category] || '🎨';
        }

        function getItemImageHtml(item) {
            const emoji = getDefaultEmoji(item);
            // Utiliser l'image réelle du produit si elle existe, sinon afficher l'emoji
            if (item.image) {
                return `
                    <div class="item-image">
                        <img loading="lazy" src="${item.image}" alt="${item.title}" onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\\'font-size:50px;\\'>${emoji}</div>';">
                        <div class="emoji-overlay">${emoji}</div>
                    </div>
                `;
            } else {
                return `
                    <div class="item-image">
                        <div style="font-size: 50px;">${emoji}</div>
                    </div>
                `;
            }
        }

        function getOrderItemImageHtml(item) {
            const emoji = getDefaultEmoji(item);
            // Utiliser l'image réelle du produit si elle existe, sinon afficher l'emoji
            if (item.image) {
                return `
                    <div class="order-item-image">
                        <img loading="lazy" src="${item.image}" alt="${item.title}" onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\\'font-size:1.5rem;\\'>${emoji}</div>';">
                        <div class="emoji-small">${emoji}</div>
                    </div>
                `;
            } else {
                return `
                    <div class="order-item-image">
                        <div style="font-size: 1.5rem;">${emoji}</div>
                    </div>
                `;
            }
        }

        // ==================== PANIER — STYLE JUMIA ARKYL ====================

        // ── Adresse client (liée au compte utilisateur) ──────────────────
        let clientAddress = null; // chargée au login via chargerAdresseUtilisateur()

        function _addressKey(userId) {
            return 'arkyl_address_' + (userId || 'guest');
        }

        function chargerAdresseUtilisateur(userId) {
            try {
                const raw = localStorage.getItem(_addressKey(userId));
                clientAddress = raw ? JSON.parse(raw) : null;
            } catch(e) { clientAddress = null; }
        }

        function saveClientAddress() {
            const nom      = document.getElementById('addr-nom')?.value.trim();
            const tel      = document.getElementById('addr-tel')?.value.trim();
            const quartier = document.getElementById('addr-quartier')?.value.trim();
            const ville    = document.getElementById('addr-ville')?.value.trim();
            const pays     = document.getElementById('addr-pays')?.value.trim();
            const detail   = document.getElementById('addr-detail')?.value.trim();

            if (!nom || !tel || !ville) {
                showToast('⚠️ Nom, téléphone et ville sont obligatoires');
                return;
            }

            clientAddress = { nom, tel, quartier, ville, pays: pays || "Côte d'Ivoire", detail };
            const _uid = currentUser?.id || currentUser?.googleId || currentUser?.email;
            try { localStorage.setItem(_addressKey(_uid), JSON.stringify(clientAddress)); } catch(e) {}
            showToast('✅ Adresse enregistrée');
            renderCart();
        }

        function editAddress() {
            const card = document.getElementById('address-card');
            if (!card) return;
            card.innerHTML = renderAddressForm(true);
        }

        function cancelAddressEdit() {
            renderCart();
        }

        function renderAddressContent() {
            if (clientAddress && clientAddress.nom) {
                return `<div class="address-display">
                    <div class="address-display-text">
                        <strong>${clientAddress.nom}</strong>
                        📞 ${clientAddress.tel}<br>
                        📌 ${[clientAddress.quartier, clientAddress.ville, clientAddress.pays].filter(Boolean).join(', ')}
                        ${clientAddress.detail ? '<br>ℹ️ ' + clientAddress.detail : ''}
                    </div>
                    <button class="address-edit-btn" onclick="editAddress()">✏️ Modifier</button>
                </div>`;
            }
            return `<div class="address-missing-msg">
                        ⚠️ Aucune adresse renseignée — obligatoire pour commander
                    </div>
                    ${renderAddressForm(false)}`;
        }

        function renderAddressForm(withCancel) {
            const a = clientAddress || {};
            return `<div class="address-form">
                <div class="address-form-row">
                    <input class="address-input" id="addr-nom"      placeholder="Nom complet *"       value="${a.nom      || ''}">
                    <input class="address-input" id="addr-tel"      placeholder="Téléphone *"          value="${a.tel      || ''}" type="tel">
                </div>
                <div class="address-form-row">
                    <input class="address-input" id="addr-quartier" placeholder="Quartier / Zone"      value="${a.quartier || ''}">
                    <input class="address-input" id="addr-ville"    placeholder="Ville *"              value="${a.ville    || ''}">
                </div>
                <input class="address-input full-width" id="addr-pays"   placeholder="Pays"            value="${a.pays    || "Côte d'Ivoire"}">
                <input class="address-input full-width" id="addr-detail" placeholder="Précisions (immeuble, point de repère...)" value="${a.detail  || ''}">
                <div style="display:grid;grid-template-columns:${withCancel ? '1fr 1fr' : '1fr'};gap:8px;">
                    ${withCancel ? '<button class="address-cancel-btn" onclick="cancelAddressEdit()">Annuler</button>' : ''}
                    <button class="address-save-btn" onclick="saveClientAddress()">💾 Enregistrer l'adresse</button>
                </div>
            </div>`;
        }

        function renderCart() {
            const container = document.getElementById('cartContainer');

            if (cartItems.length === 0) {
                container.innerHTML = `
                    <div style="text-align:center;padding:80px 20px;">
                        <div style="font-size:70px;margin-bottom:16px;">🛒</div>
                        <h3 style="font-size:20px;margin-bottom:8px;">Votre panier est vide</h3>
                        <p style="opacity:0.6;margin-bottom:24px;">Découvrez nos magnifiques œuvres d'art</p>
                        <button class="modal-btn" onclick="navigateTo('home')">Parcourir les œuvres</button>
                    </div>
                `;
                return;
            }

            let subtotal = 0;
            const itemsHtml = cartItems.map(item => {
                const itemTotal = item.price * item.quantity;
                subtotal += itemTotal;
                const imgHtml = item.image
                    ? `<img loading="lazy" src="${item.image}" alt="${item.title}" style="width:100%;height:100%;object-fit:contain;" onerror="this.style.display='none'">`
                    : `<span style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;font-size:48px;">${item.emoji || '🎨'}</span>`;
                const emojiHtml = '';
                return `
                    <div class="cart-item" id="cart-item-${item.id}">
                        <div class="cart-item-img">${imgHtml}${emojiHtml}</div>
                        <div class="cart-item-body">
                            <div class="cart-item-title">${item.title}</div>
                            <div class="cart-item-artist">par ${item.artist || 'Artiste ARKYL'}</div>
                            <div class="cart-item-price-row">
                                <span class="cart-item-price">${formatPrice(item.price)}</span>
                                <span class="cart-item-unit">/ unité</span>
                            </div>
                            <div class="cart-item-actions">
                                <div class="qty-control">
                                    <button onclick="changeQuantity('${item.id}', -1)" ${item.quantity <= 1 ? 'disabled' : ''}>−</button>
                                    <span class="qty-val">${item.quantity}</span>
                                    <button onclick="changeQuantity('${item.id}', 1)" ${item.quantity >= 10 ? 'disabled' : ''}>+</button>
                                </div>
                                <button class="cart-remove-btn" id="remove-btn-${item.id}" onclick="confirmRemoveFromCart('${item.id}', this)">
                                    🗑️ Supprimer
                                </button>
                                ${item.quantity > 1 ? `<span class="cart-item-subtotal">= ${formatPrice(itemTotal)}</span>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            const datePoste       = getDeliveryDate(5);
            const dateBus         = getDeliveryDate(7);
            const dateMainPropre  = getDeliveryDate(1);
            // posteVille est une variable globale mise à jour par confirmerVillePoste()
            const shipping = 3000;
            const total = subtotal + shipping;
            const totalArticles = cartItems.reduce((s,i) => s + i.quantity, 0);

            container.innerHTML = `
                <div class="cart-page-wrapper">
                    <div class="cart-header-bar">
                        <h1>🛒 Mon Panier</h1>
                        <span class="cart-count-tag">${totalArticles} article${totalArticles > 1 ? 's' : ''}</span>
                    </div>
                    <div class="cart-grid">
                        <div class="cart-items-col">${itemsHtml}</div>
                        <div class="summary-card">
                            <div class="summary-title">Récapitulatif</div>
                            <div class="summary-row"><span>Sous-total</span><span>${formatPrice(subtotal)}</span></div>
                            <div class="summary-row"><span>Livraison</span><span id="shippingCost">${formatPrice(shipping)}</span></div>
                            <div class="summary-row total"><span>Total</span><span id="totalCost">${formatPrice(total)}</span></div>
                            <div class="cart-guarantees">
                                <div class="guarantee-item"><span>🔒</span><span>Paiement 100% sécurisé</span></div>
                                <div class="guarantee-item"><span>✅</span><span>Fonds libérés après réception</span></div>
                                <div class="guarantee-item"><span>⏱️</span><span>Protection acheteur 21 jours</span></div>
                            </div>
                            <div class="shipping-section">
                                <div class="shipping-label">🚚 Livraison</div>
                                <div class="shipping-options">
                                    <div>
                                        <div class="shipping-option selected" data-cost="3000" data-mode="poste" onclick="selectShipping(this)">
                                            <div class="shipping-radio"></div>
                                            <div class="shipping-info-col">
                                                <div class="shipping-name">📮 La Poste</div>
                                                <div class="shipping-detail">3-5 jours · estimée le ${datePoste}${posteVille ? ' · ' + posteVille : ''}</div>
                                            </div>
                                            <span class="shipping-price-tag">3 000 FCFA</span>
                                        </div>
                                        <div class="poste-city-panel ${posteVille ? '' : 'open'}" id="poste-city-panel">
                                            <div class="poste-city-inner">
                                                <div class="poste-city-label">📌 Ville de destination pour La Poste</div>
                                                <div class="poste-city-row">
                                                    <input class="poste-city-input" id="poste-city-input" placeholder="Ex : Abidjan, Bouaké, Yamoussoukro…" value="${posteVille || ''}">
                                                    <button class="poste-city-confirm" onclick="confirmerVillePoste()">✓ OK</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="shipping-option" data-cost="1500" data-mode="transport" onclick="selectShipping(this)">
                                            <div class="shipping-radio"></div>
                                            <div class="shipping-info-col">
                                                <div class="shipping-name">🚌 Transport en commun</div>
                                                <div class="shipping-detail">4-7 jours · estimée le ${dateBus}${transportCompagnie ? ' · ' + transportCompagnie : ''}</div>
                                            </div>
                                            <span class="shipping-price-tag">1 500 FCFA</span>
                                        </div>
                                        <div class="transport-panel ${transportCompagnie ? '' : 'open'}" id="transport-panel">
                                            <div class="transport-inner">
                                                <div class="transport-label">🚌 Choisir la compagnie de transport</div>
                                                <div class="transport-list" id="transport-list">
                                                    ${renderTransportList()}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="shipping-option" data-cost="0" data-mode="mainpropre" onclick="selectShipping(this)">
                                            <div class="shipping-radio"></div>
                                            <div class="shipping-info-col">
                                                <div class="shipping-name">🤝 Main propre</div>
                                                <div class="shipping-detail">Sur rendez-vous · dès le ${dateMainPropre}${mainPropreLieu ? ' · ' + mainPropreLieu : ''}</div>
                                            </div>
                                            <span class="shipping-price-tag shipping-free">Gratuit</span>
                                        </div>
                                        <div class="mainpropre-panel ${mainPropreLieu ? '' : 'open'}" id="mainpropre-panel">
                                            <div class="mainpropre-inner">
                                                <div class="mainpropre-warning">
                                                    <span class="mainpropre-warning-icon">⚠️</span>
                                                    <span>Choisissez un <strong>lieu public et ouvert</strong> (marché, centre commercial, devant une banque…). Tout lieu isolé ou privé pourra entraîner un <strong>refus de livraison</strong>.</span>
                                                </div>
                                                <div class="poste-city-row">
                                                    <input class="poste-city-input" id="mainpropre-lieu-input" placeholder="Ex : Marché Cocody, Mall Playtime, Devant SGBCI…" value="${mainPropreLieu || ''}">
                                                    <button class="poste-city-confirm" onclick="confirmerLieuMainPropre()">✓ OK</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="address-section">
                                <div class="shipping-label">📍 Adresse de livraison</div>
                                <div class="address-card ${clientAddress && clientAddress.nom ? 'filled' : 'missing'}" id="address-card">
                                    ${renderAddressContent()}
                                </div>
                            </div>

                            <div class="payment-section">
                                <div class="shipping-label">💳 Paiement</div>
                                <div class="stripe-payment-info">
                                    <div class="stripe-logo-row">
                                        <span class="stripe-lock-icon">🔒</span>
                                        <span class="stripe-label">Paiement sécurisé par <strong>Stripe</strong></span>
                                    </div>
                                    <div class="stripe-cards-row">
                                        <span class="stripe-card-chip">Visa</span>
                                        <span class="stripe-card-chip">Mastercard</span>
                                        <span class="stripe-card-chip">American Express</span>
                                    </div>
                                    <p class="stripe-info-text">Vous serez redirigé vers la page de paiement sécurisée Stripe. Vos données bancaires ne transitent jamais par nos serveurs.</p>
                                </div>
                            </div>
                            <button id="checkout-btn" class="checkout-btn" onclick="validerPaiementStripe()">COMMANDER — ${formatPrice(total)}</button>
                            <div class="security-badge">🔒 Paiement sécurisé — Tiers de Confiance ARKYL</div>
                        </div>
                    </div>
                </div>
            `;


        }

        function getDeliveryDate(maxWorkDays) {
            const jours = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
            const mois  = ['jan','fév','mars','avr','mai','juin','juil','août','sep','oct','nov','déc'];
            const d = new Date();
            if (!maxWorkDays) return `${jours[d.getDay()]} ${d.getDate()} ${mois[d.getMonth()]}`;
            let added = 0;
            while (added < maxWorkDays) {
                d.setDate(d.getDate() + 1);
                if (d.getDay() !== 0 && d.getDay() !== 6) added++;
            }
            return `${jours[d.getDay()]} ${d.getDate()} ${mois[d.getMonth()]}`;
        }

        // ── Compagnies transport interurbain CI ─────────────────────────
        const COMPAGNIES_TRANSPORT_CI = [
            { nom: 'Océan',          desc: 'Océan Transport — réseau Abidjan & intérieur' }
            { nom: 'SBTA',           desc: 'Société de Bus et Transport en Afrique — national' }
            { nom: 'CTE',            desc: 'Compagnie de Transport de l\'Est — Abengourou & Est CI' }
            { nom: 'AT',             desc: 'Abidjan Transit — liaisons urbaines & périurbaines' }
            { nom: 'UTB',            desc: 'Union des Transports de Bouaké — réseau national' }
            { nom: 'STIF',           desc: 'Société de Transport Interurbain — Daloa & national' }
            { nom: 'STGB',           desc: 'Société de Transport Grand Bassam' }
            { nom: 'Sans Frontières',desc: 'Liaisons CI, Burkina Faso, Mali' }
            { nom: 'MST',            desc: 'Mon Service Transport — Abidjan & intérieur' }
            { nom: 'KTC',            desc: 'Korhogo Transport & Compagnie — Nord CI' }
            { nom: 'Trans Ivoir',    desc: 'Liaisons interurbaines Centre-Ouest' }
            { nom: 'Bouaké Express', desc: 'Bouaké ↔ Abidjan express' }
            { nom: 'SOTRA',          desc: 'Transport urbain Abidjan & grandes villes' }
            { nom: 'Autre',          desc: 'Autre compagnie / taxi-brousse' }
        ];

        let transportCompagnie = (function() {
            try {
                const uid = currentUser?.id || currentUser?.googleId || currentUser?.email || 'guest';
                const raw = localStorage.getItem('arkyl_transport_cie_' + uid);
                return raw ? JSON.parse(raw) : '';
            } catch(e) { return ''; }
        })();

        function _saveTransportCompagnie(nom) {
            transportCompagnie = nom;
            try {
                const uid = currentUser?.id || currentUser?.googleId || currentUser?.email || 'guest';
                localStorage.setItem('arkyl_transport_cie_' + uid, JSON.stringify(nom));
            } catch(e) {}
        }

        function renderTransportList() {
            return COMPAGNIES_TRANSPORT_CI.map(c => `
                <div class="transport-item ${transportCompagnie === c.nom ? 'selected' : ''}"
                     onclick="selectionnerCompagnie('${c.nom.replace(/'/g, "\'")}')">
                    <div class="transport-item-radio"></div>
                    <div class="transport-item-info">
                        <div class="transport-item-name">${c.nom}</div>
                        <div class="transport-item-desc">${c.desc}</div>
                    </div>
                </div>
            `).join('');
        }

        function selectionnerCompagnie(nom) {
            _saveTransportCompagnie(nom);
            // Mettre à jour visuellement sans re-render complet
            document.querySelectorAll('.transport-item').forEach(el => el.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            // Mettre à jour le détail de l'option
            const detail = document.querySelector('.shipping-option[data-mode="transport"] .shipping-detail');
            if (detail) detail.textContent = `4-7 jours · estimée le ${getDeliveryDate(7)} · ${nom}`;
            // Fermer le panneau
            const panel = document.getElementById('transport-panel');
            if (panel) panel.classList.remove('open');
            showToast('✅ Compagnie sélectionnée : ' + nom);
        }

        // ── Lieu Main propre — persisté par compte ─────────────────────
        let mainPropreLieu = (function() {
            try {
                const uid = currentUser?.id || currentUser?.googleId || currentUser?.email || 'guest';
                const raw = localStorage.getItem('arkyl_mainpropre_lieu_' + uid);
                return raw ? JSON.parse(raw) : '';
            } catch(e) { return ''; }
        })();

        function _saveMainPropreLieu(lieu) {
            mainPropreLieu = lieu;
            try {
                const uid = currentUser?.id || currentUser?.googleId || currentUser?.email || 'guest';
                localStorage.setItem('arkyl_mainpropre_lieu_' + uid, JSON.stringify(lieu));
            } catch(e) {}
        }

        function confirmerLieuMainPropre() {
            const val = document.getElementById('mainpropre-lieu-input')?.value.trim();
            if (!val) { showToast('⚠️ Veuillez indiquer un lieu de rendez-vous'); return; }
            _saveMainPropreLieu(val);
            const detail = document.querySelector('.shipping-option[data-mode="mainpropre"] .shipping-detail');
            if (detail) detail.textContent = `Sur rendez-vous · dès le ${getDeliveryDate(1)} · ${val}`;
            const panel = document.getElementById('mainpropre-panel');
            if (panel) panel.classList.remove('open');
            showToast('✅ Lieu enregistré : ' + val);
        }

        // Ville La Poste — persistée par compte
        let posteVille = (function() {
            try {
                const uid = currentUser?.id || currentUser?.googleId || currentUser?.email || 'guest';
                const raw = localStorage.getItem('arkyl_poste_ville_' + uid);
                return raw ? JSON.parse(raw) : '';
            } catch(e) { return ''; }
        })();

        function _savePosteVille(ville) {
            posteVille = ville;
            try {
                const uid = currentUser?.id || currentUser?.googleId || currentUser?.email || 'guest';
                localStorage.setItem('arkyl_poste_ville_' + uid, JSON.stringify(ville));
            } catch(e) {}
        }

        function confirmerVillePoste() {
            const val = document.getElementById('poste-city-input')?.value.trim();
            if (!val) { showToast('⚠️ Veuillez indiquer une ville'); return; }
            _savePosteVille(val);
            // Mettre à jour l'affichage dans le détail sans re-render complet
            const detail = document.querySelector('.shipping-option[data-mode="poste"] .shipping-detail');
            if (detail) detail.textContent = `3-5 jours · estimée le ${getDeliveryDate(5)} · ${val}`;
            // Fermer le panneau
            const panel = document.getElementById('poste-city-panel');
            if (panel) panel.classList.remove('open');
            showToast('✅ Ville enregistrée : ' + val);
        }

        function selectShipping(el) {
            document.querySelectorAll('.shipping-option').forEach(o => o.classList.remove('selected'));
            el.classList.add('selected');
            // Gérer les panneaux selon le mode sélectionné
            const postePanel      = document.getElementById('poste-city-panel');
            const transportPanel  = document.getElementById('transport-panel');
            const mainproprePanel = document.getElementById('mainpropre-panel');
            // Fermer tous les panneaux d'abord
            [postePanel, transportPanel, mainproprePanel].forEach(p => p?.classList.remove('open'));
            if (el.dataset.mode === 'poste') {
                if (postePanel) postePanel.classList.add('open');
                setTimeout(() => document.getElementById('poste-city-input')?.focus(), 350);
            } else if (el.dataset.mode === 'transport') {
                if (transportPanel) transportPanel.classList.add('open');
            } else if (el.dataset.mode === 'mainpropre') {
                if (mainproprePanel) mainproprePanel.classList.add('open');
                setTimeout(() => document.getElementById('mainpropre-lieu-input')?.focus(), 350);
            }
            updateCartTotal();
        }

        function updateCartTotal() {
            const subtotal = cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const sel = document.querySelector('.shipping-option.selected');
            const shipping = sel ? parseInt(sel.dataset.cost) : 3000;
            const total = subtotal + shipping;
            const elS = document.getElementById('shippingCost');
            const elT = document.getElementById('totalCost');
            const btn = document.getElementById('checkout-btn');
            if (elS) elS.textContent = shipping === 0 ? 'Gratuit' : formatPrice(shipping);
            if (elT) elT.textContent = formatPrice(total);
            if (btn) btn.textContent = `COMMANDER — ${formatPrice(total)}`;
        }

        function changeQuantity(itemId, change) {
            const idx = cartItems.findIndex(i => String(i.id) === String(itemId));
            if (idx === -1) return;
            const newQty = cartItems[idx].quantity + change;
            if (newQty < 1 || newQty > 10) return;
            cartItems[idx].quantity = newQty;
            updateBadges();
            renderCart();

            // Sync BDD en arrière-plan (si connecté)
            const _userId = currentUser?.id || currentUser?.googleId || currentUser?.email;
            if (_userId) {
                fetch('https://arkyl-galerie.onrender.com/api_supprimer_panier.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                    body: JSON.stringify({ action: 'quantite', user_id: _userId, artwork_id: itemId, quantity: newQty })
                }).catch(e => console.warn('Sync quantité BDD échouée :', e.message));
            }
        }

        const _removeTimers = {};

        function confirmRemoveFromCart(itemId, btn) {
            if (btn.classList.contains('confirming')) {
                clearTimeout(_removeTimers[itemId]);
                delete _removeTimers[itemId];
                const cartItem = document.getElementById(`cart-item-${itemId}`);
                if (cartItem) { cartItem.classList.add('removing'); setTimeout(() => removeFromCart(itemId), 350); }
                else removeFromCart(itemId);
                return;
            }
            btn.classList.add('confirming');
            btn.textContent = '⚠️ Confirmer ?';
            _removeTimers[itemId] = setTimeout(() => {
                if (btn && btn.classList.contains('confirming')) {
                    btn.classList.remove('confirming');
                    btn.textContent = '🗑️ Supprimer';
                }
                delete _removeTimers[itemId];
            } 3000);
        }

        function removeFromCart(itemId) {
            const idx = cartItems.findIndex(i => String(i.id) === String(itemId));
            if (idx === -1) return;
            const item = cartItems[idx];
            cartItems.splice(idx, 1);
            updateBadges();
            renderCart();
            showToast(`✅ "${item.title}" retiré du panier`);
            const _userId = currentUser?.id || currentUser?.googleId || currentUser?.email;
            if (_userId) {
                fetch('https://arkyl-galerie.onrender.com/api_supprimer_panier.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                    body: JSON.stringify({ action: 'supprimer', user_id: _userId, artwork_id: itemId })
                }).catch(e => console.warn('Sync BDD échouée :', e.message));
            }
        }


        // ==================== STRIPE CHECKOUT ====================
        async function validerPaiementStripe() {
            const btn = document.getElementById('checkout-btn');
            if (!btn) return;

            if (cartItems.length === 0) {
                showToast('⚠️ Votre panier est vide');
                return;
            }

            // Vérifier que l'adresse est renseignée
            if (!clientAddress || !clientAddress.nom || !clientAddress.tel || !clientAddress.ville) {
                showToast('📍 Veuillez renseigner votre adresse de livraison');
                // Scroller vers la section adresse
                const card = document.getElementById('address-card');
                if (card) { card.classList.add('missing'); card.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '🔒 Connexion sécurisée en cours...';

            const userId = currentUser?.id || currentUser?.googleId || currentUser?.email;
            if (!userId) {
                showToast('🔒 Connectez-vous pour finaliser la commande');
                btn.disabled = false;
                btn.innerHTML = '🛒 Finaliser la commande';
                setTimeout(() => {
                    const googleBtn = document.getElementById('googleLoginBtn');
                    if (googleBtn) googleBtn.click();
                } 800);
                return;
            }

            try {
                // Préparer le fallback panier (au cas où la BDD serait désynchronisée)
                const cartFallback = cartItems.map(item => ({
                    id:       item.id       || item.artwork_id,
                    quantity: item.quantity || 1
                }));

                // Récupérer le mode et coût de livraison sélectionnés
                const selShipping = document.querySelector('.shipping-option.selected');
                const shippingCost = selShipping ? parseInt(selShipping.dataset.cost) : 3000;
                const shippingMode = selShipping ? (selShipping.dataset.mode || 'poste') : 'poste';
                const shippingNames = {
                    'poste':       'La Poste — Livraison à domicile',
                    'transport':   'Compagnie de transport',
                    'mainpropre':  'Remise en main propre'
                };
                const shippingLabel = shippingNames[shippingMode] || 'Frais de livraison';

                const response = fetch('api_stripe_checkout.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                    body: JSON.stringify({
                        user_id:       userId,
                        cart_items:    cartFallback,
                        shipping_cost: shippingCost,
                        shipping_mode: shippingMode,
                        shipping_label: shippingLabel
                    })
                });

                // 🔍 DEBUG — lit la réponse brute pour voir l'erreur PHP exacte
                const texteBrut = await response.text();
                console.log('🔍 RÉPONSE BRUTE DU SERVEUR PHP :', texteBrut);

                let data;
                try {
                    data = JSON.parse(texteBrut);
                } catch (parseError) {
                    console.error('❌ Le PHP a renvoyé du texte au lieu de JSON :', texteBrut);
                    alert('❌ Erreur PHP — ouvre la console (F12) et copie la ligne "RÉPONSE BRUTE" pour diagnostiquer.');
                    btn.disabled = false;
                    btn.innerHTML = '🛒 Finaliser la commande';
                    return;
                }

                if (data.success && data.url) {
                    // Sauvegarder la commande AVANT la redirection (Stripe peut échouer)
                    const selShippingEl = document.querySelector('.shipping-option.selected');
                    const shippingCostVal = selShippingEl ? parseInt(selShippingEl.dataset.cost) : 3000;
                    const subtotal = cartItems.reduce((s, i) => s + (i.price || 0) * (i.quantity || 1), 0);
                    const tax = Math.round(subtotal * 0.18);
                    const total = subtotal + tax + shippingCostVal;

                    const pendingOrder = {
                        stripe_session_id: data.session_id || null,
                        user_id:    userId,
                        user_name:  currentUser?.name || '',
                        user_email: currentUser?.email || '',
                        items: cartItems.map(i => ({
                            id: i.id || i.artwork_id,
                            artwork_id: i.id || i.artwork_id,
                            title: i.title,
                            artist: i.artist,
                            artist_id: i.artistId || '',
                            price: i.price,
                            quantity: i.quantity || 1,
                            image: i.image_url || i.image || ''
                        })),
                        subtotal,
                        tax,
                        shippingCost: shippingCostVal,
                        shippingMode,
                        shippingName: shippingLabel,
                        shippingAddress: clientAddress ? `${clientAddress.nom} ${clientAddress.tel} ${clientAddress.ville}${clientAddress.quartier ? ', ' + clientAddress.quartier : ''}` : '',
                        paymentMethod: 'Stripe',
                        total,
                        status: 'En préparation',
                        escrow_status: 'payée_en_attente',
                        date: new Date().toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' }),
                        id: Date.now(),
                        savedAt: Date.now()
                    };
                    safeStorage.set('arkyl_pending_order', pendingOrder);
                    console.log('💾 Commande sauvegardée en attente de retour Stripe');
                    window.location.href = data.url;
                } else {
                    alert('❌ ' + (data.message || 'Erreur inconnue du serveur'));
                    btn.disabled = false;
                    btn.innerHTML = '🛒 Finaliser la commande';
                }
            } catch (error) {
                console.error('❌ Erreur réseau / fetch :', error);
                alert('❌ Impossible de joindre le serveur. Vérifie ta connexion ou que le fichier PHP est bien déployé.');
                btn.disabled = false;
                btn.innerHTML = '🛒 Finaliser la commande';
            }
        }
        // ==================== NETTOYAGE CACHE APP ====================
        function clearAppCache() {
            const keysToKeep = ['arkyl_current_user', 'arkyl_orders', 'guest_session_id', 'arkyl_pending_order'];
            const allKeys = Object.keys(_memStore).filter(k => k.startsWith('arkyl_') || k.startsWith('guest_'));
            const removed = [];

            allKeys.forEach(key => {
                if (!keysToKeep.includes(key)) {
                    delete _memStore[key];
                    removed.push(key);
                }
            });

            // Réinitialiser les variables en mémoire
            cartItems     = [];
            favorites     = [];
            notifications = [];
            updateBadges();

            console.log('🧹 Cache nettoyé :', removed);

            if (removed.length === 0) {
                showToast('✅ Cache déjà propre — aucune donnée fantôme trouvée');
            } else {
                showToast(`🧹 Cache vidé ! ${removed.length} donnée${removed.length > 1 ? 's' : ''} fantôme${removed.length > 1 ? 's' : ''} supprimée${removed.length > 1 ? 's' : ''}`);
            }
        }


        // ==================== ORDERS PAGE ====================

        // ===== RETOUR STRIPE : traiter commande en attente =====
        async function processStripeReturn(sessionId, arkylOrderId) {
            console.log('🔄 processStripeReturn appelé — session:', sessionId, 'order:', arkylOrderId);
            const pending = safeStorage.get('arkyl_pending_order', null);

            if (!pending) {
                // Pas de commande locale — mais paiement confirmé par Stripe
                // Créer une commande minimale à partir des infos disponibles
                console.log('⚠️ Pas de commande en attente — création minimale');
                const minOrder = {
                    id: Date.now(),
                    order_number: arkylOrderId || ('ARK-' + Date.now().toString(36).toUpperCase()),
                    stripe_session_id: sessionId,
                    user_id: currentUser?.id || currentUser?.googleId || currentUser?.email || '',
                    user_name: currentUser?.name || '',
                    user_email: currentUser?.email || '',
                    items: cartItems.length > 0 ? cartItems.map(i => ({...i, artwork_id: i.id})) : [],
                    subtotal: cartItems.reduce((s,i) => s+(i.price||0)*(i.quantity||1), 0),
                    tax: 0,
                    shippingCost: 0,
                    total: cartItems.reduce((s,i) => s+(i.price||0)*(i.quantity||1), 0),
                    shippingName: 'À confirmer',
                    paymentMethod: 'Stripe',
                    status: 'En préparation',
                    escrow_status: 'payée_en_attente',
                    date: new Date().toLocaleDateString('fr-FR', {day:'2-digit',month:'long',year:'numeric'}),
                };
                orderHistory = safeStorage.get('arkyl_orders', []);
                orderHistory.unshift(minOrder);
                safeStorage.set('arkyl_orders', orderHistory);
                cartItems = []; safeStorage.set('arkyl_cart', []); updateBadges();
                syncOrderToServer(minOrder).catch(() => {});
                setTimeout(() => {
                    showToast('🎉 Paiement confirmé ! Commande ' + minOrder.order_number + ' créée.');
                    navigateTo('orders');
                    showOrderSuccessModal(minOrder);
                } 300);
                return;
            }

            // Éviter le double traitement (si page rechargée)
            if (pending.processed) {
                console.log('ℹ️ Commande déjà traitée');
                safeStorage.remove('arkyl_pending_order');
                return;
            }

            console.log('✅ Traitement du retour Stripe — commande:', pending.id);

            // Marquer comme traitée immédiatement
            pending.processed = true;
            safeStorage.set('arkyl_pending_order', pending);

            // Construire l'objet commande final
            const order = {
                ...pending,
                order_number: 'ARK-' + Date.now().toString(36).toUpperCase(),
                stripe_session_id: sessionId || pending.stripe_session_id,
            };

            // Ajouter à l'historique local
            orderHistory = safeStorage.get('arkyl_orders', []);
            // Éviter les doublons
            const alreadyExists = orderHistory.some(o =>
                o.id === order.id ||
                (o.stripe_session_id && o.stripe_session_id === sessionId)
            );

            if (!alreadyExists) {
                orderHistory.unshift(order);
                safeStorage.set('arkyl_orders', orderHistory);
                console.log('💾 Commande ajoutée à l\'historique local');

                // Vider le panier
                cartItems = [];
                safeStorage.set('arkyl_cart', []);
                updateBadges();

                // Sync avec le serveur en arrière-plan
                syncOrderToServer(order).then(() => {
                    console.log('🌐 Commande synchronisée avec le serveur');
                }).catch(() => {
                    console.log('⚠️ Sync serveur échouée — commande conservée localement');
                });

                // Envoyer les notifications
                sendOrderNotifications(order);

                // Nettoyer l'URL sans recharger la page
                const cleanUrl = window.location.pathname;
                window.history.replaceState({} document.title, cleanUrl);

                // Afficher la page commandes avec confirmation
                setTimeout(() => {
                    showToast('🎉 Paiement confirmé ! Commande ' + order.order_number + ' créée.');
                    navigateTo('orders');
                    // Afficher modal de succès
                    showOrderSuccessModal(order);
                } 400);

            } else {
                console.log('ℹ️ Commande déjà dans l\'historique');
            }

            // Nettoyer la commande en attente
            safeStorage.remove('arkyl_pending_order');
        }

        function showOrderSuccessModal(order) {
            const existing = document.getElementById('orderSuccessModal');
            if (existing) existing.remove();

            const total = order.total || 0;
            const itemsCount = (order.items || []).reduce((s, i) => s + (i.quantity || 1), 0);

            const modal = document.createElement('div');
            modal.id = 'orderSuccessModal';
            modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px;';
            modal.innerHTML = `
                <div style="background:linear-gradient(135deg,#1a1a2e,#16213e);border:1px solid rgba(212,175,55,0.4);border-radius:24px;padding:40px;max-width:460px;width:100%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.6);">
                    <div style="font-size:70px;margin-bottom:16px;">🎉</div>
                    <h2 style="font-size:26px;font-weight:900;color:var(--or);margin-bottom:8px;">Paiement confirmé !</h2>
                    <p style="opacity:0.8;margin-bottom:6px;">Commande <strong style="color:var(--ocre);">${order.order_number}</strong></p>
                    <p style="opacity:0.6;font-size:14px;margin-bottom:24px;">${itemsCount} œuvre${itemsCount > 1 ? 's' : ''} · ${formatPrice(total)}</p>

                    <div style="background:rgba(76,175,80,0.15);border:1px solid rgba(76,175,80,0.4);border-radius:14px;padding:16px;margin-bottom:24px;font-size:14px;text-align:left;">
                        <div style="font-weight:700;margin-bottom:8px;color:#a5d6a7;">🔒 Fonds sécurisés</div>
                        <div style="opacity:0.8;line-height:1.6;">Votre paiement est bloqué sur le compte ARKYL. Il sera libéré à l'artiste uniquement après que vous ayez confirmé la réception de votre œuvre.</div>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:12px;">
                        <button onclick="document.getElementById('orderSuccessModal').remove(); navigateTo('orders');"
                            style="padding:14px;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;background:linear-gradient(135deg,var(--terre-cuite),var(--terre-sombre));color:white;">
                            📦 Suivre ma commande
                        </button>
                        <button onclick="document.getElementById('orderSuccessModal').remove(); navigateTo('home');"
                            style="padding:14px;border:none;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;background:rgba(255,255,255,0.08);color:white;">
                            Retour à la galerie
                        </button>
                    </div>
                </div>`;
            document.body.appendChild(modal);
        }

        const ORDERS_API = 'https://arkyl-galerie.onrender.com/api_commandes.php';

        const DELIVERY_STATUSES = [
            { key: 'En préparation', icon: '📦', label: 'En préparation', color: '#ff9800' }
            { key: 'Préparée',       icon: '✅', label: 'Préparée',       color: '#ff9800' }
            { key: 'Expédiée',       icon: '🚚', label: 'Expédiée',       color: '#2196f3' }
            { key: 'En transit',     icon: '🛵', label: 'En transit',     color: '#2196f3' }
            { key: 'Livrée',         icon: '📬', label: 'Livrée',         color: '#4caf50' }
        ];

        const CARRIERS = [
            { id: 'laposte_ci',  name: '🇨🇮 La Poste CI',     url: 'https://www.laposte.ci/suivi-de-colis?num=' }
            { id: 'dhl',         name: 'DHL',                  url: 'https://www.dhl.com/fr-fr/home/tracking.html?tracking-id=' }
            { id: 'chronopost',  name: 'Chronopost',           url: 'https://www.chronopost.fr/tracking-no-cms/suivi-page?listeNumerosLT=' }
            { id: 'colissimo',   name: 'Colissimo',            url: 'https://www.laposte.fr/outils/suivre-vos-envois?code=' }
            { id: 'fedex',       name: 'FedEx',                url: 'https://www.fedex.com/fr-fr/tracking.html?tracknumbers=' }
            { id: 'ups',         name: 'UPS',                  url: 'https://www.ups.com/track?tracknum=' }
            { id: 'gls',         name: 'GLS',                  url: 'https://gls-group.com/track/' }
            { id: 'bolloré',     name: 'Bolloré Logistics',    url: '' }
            { id: 'nsia',        name: 'NSIA Transport',        url: '' }
            { id: 'other',       name: 'Autre transporteur',   url: '' }
        ];

        function getTrackingUrl(carrier, trackingNumber) {
            if (!trackingNumber) return null;
            const c = CARRIERS.find(x => x.id === carrier);
            if (!c || !c.url) return null;
            return c.url + encodeURIComponent(trackingNumber);
        }

        function statusColor(status) {
            const s = DELIVERY_STATUSES.find(x => x.key === status);
            return s ? s.color : '#aaa';
        }

        function statusIcon(status) {
            const s = DELIVERY_STATUSES.find(x => x.key === status);
            return s ? s.icon : '📦';
        }

        async function syncOrderToServer(order) {
            try {
                const items = order.items.map(i => ({
                    artwork_id: i.id || i.artwork_id,
                    title: i.title,
                    artist: i.artist,
                    artist_id: i.artistId || i.artist_id || '',
                    price: i.price,
                    quantity: i.quantity || 1,
                    image: i.image || i.image_url || ''
                }));
                const resp = fetch(ORDERS_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                    body: JSON.stringify({
                        action: 'create',
                        user_id: currentUser?.id || currentUser?.googleId || currentUser?.email || '',
                        user_name: order.user || currentUser?.name || '',
                        user_email: order.userEmail || currentUser?.email || '',
                        subtotal: order.subtotal,
                        tax: order.tax,
                        shipping_cost: order.shippingCost || 0,
                        total: order.total,
                        shipping_name: order.shippingName || '',
                        shipping_mode: order.shippingMode || '',
                        shipping_address: order.shippingAddress || '',
                        payment_method: order.paymentMethod || '',
                        items
                    })
                });
                if (r.success) {
                    // Sauvegarder le server_id localement
                    order.server_id = r.order_id;
                    order.order_number = r.order_number;
                    safeStorage.set('arkyl_orders', orderHistory);
                }
            } catch(e) { /* silencieux */ }
        }

        async function loadOrdersFromServer() {
            const userId = currentUser?.id || currentUser?.googleId || currentUser?.email;
            if (!userId) return false;
            try {
                const resp = fetch(`${ORDERS_API}?action=list&user_id=${encodeURIComponent(userId)}&t=${Date.now()}`);
                if (data.success && data.orders.length > 0) {
                    // Fusionner avec localStorage — server prioritaire
                    const serverIds = data.orders.map(o => String(o.id));
                    const localOnly = orderHistory.filter(o => !o.server_id || !serverIds.includes(String(o.server_id)));
                    orderHistory = [...data.orders.map(o => ({
                        ...o,
                        server_id: o.id,
                        items: o.items || [],
                        date: o.created_at ? new Date(o.created_at).toLocaleDateString('fr-FR', { day:'2-digit', month:'long', year:'numeric' }) : o.date,
                        shippedDate: o.shipped_at ? new Date(o.shipped_at).toLocaleDateString('fr-FR') : null,
                        deliveredDate: o.delivered_at ? new Date(o.delivered_at).toLocaleDateString('fr-FR') : null,
                        tracking_number: o.tracking_number,
                        tracking_url: o.tracking_url,
                        carrier: o.carrier,
                        escrow_status: o.escrow_status || 'payée_en_attente',
                        escrow_auto_release_date: o.escrow_auto_release_date,
                    })), ...localOnly];
                    safeStorage.set('arkyl_orders', orderHistory);
                    return true;
                }
            } catch(e) {}
            return false;
        }

        function buildOrderTimeline(order) {
            const es = order.escrow_status || 'payée_en_attente';
            const steps = [
                { key: 'payée_en_attente', icon: '💳', label: 'Commande validée' }
                { key: 'expédiée',         icon: '🚚', label: 'Expédiée' }
                { key: 'livrée_confirmée', icon: '📬', label: 'Réception confirmée' }
                { key: 'fonds_libérés',    icon: '✅', label: 'Transaction complète' }
            ];
            const escrowOrder = ['payée_en_attente','expédiée','livrée_confirmée','fonds_libérés'];
            const stepIdx = escrowOrder.indexOf(es);

            // Timeline détaillée depuis le serveur
            const serverTimeline = (order.timeline || []).map(t => `
                <div style="display:flex; gap:12px; align-items:flex-start; padding:8px 0; border-bottom:1px solid rgba(255,255,255,0.06);">
                    <div style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;">
                        ${statusIcon(t.status)}
                    </div>
                    <div style="flex:1;">
                        <div style="font-weight:600;font-size:13px;color:${statusColor(t.status)};">${t.status}</div>
                        <div style="font-size:12px;opacity:0.7;margin-top:2px;">${t.note || ''}</div>
                        <div style="font-size:11px;opacity:0.5;margin-top:2px;">${t.created_at ? new Date(t.created_at).toLocaleString('fr-FR') : ''} ${t.updated_by_role === 'artist' ? '· Artiste' : t.updated_by_role === 'admin' ? '· Admin' : ''}</div>
                    </div>
                </div>
            `).join('');

            const escrowSteps = steps.map((s, i) => `
                ${i > 0 ? `<div class="escrow-connector ${i <= stepIdx ? 'done' : ''}"></div>` : ''}
                <div class="escrow-step ${i < stepIdx ? 'done' : i === stepIdx ? 'active' : ''}">
                    <div class="escrow-step-dot">${i < stepIdx ? '✓' : s.icon}</div>
                    <div class="escrow-step-label">${s.label}</div>
                </div>
            `).join('');

            return { escrowSteps, serverTimeline };
        }

        async function renderOrders() {
            const container = document.getElementById('ordersContainer');
            container.innerHTML = '<div style="text-align:center;padding:60px;opacity:0.6;">⏳ Chargement de vos commandes...</div>';

            loadOrdersFromServer();

            if (orderHistory.length === 0) {
                container.innerHTML = `
                    <div class="empty-orders">
                        <div class="empty-orders-icon">📦</div>
                        <div class="empty-orders-text">Aucune commande pour le moment</div>
                        <div class="empty-orders-subtext">Vos commandes apparaîtront ici une fois validées</div>
                        <button class="btn" onclick="navigateTo('home')" style="background:linear-gradient(135deg,var(--terre-cuite),var(--terre-sombre));border:none;color:white;padding:14px 28px;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;">
                            🛍️ Découvrir la galerie
                        </button>
                    </div>`;
                return;
            }

            const totalOrders = orderHistory.length;
            const totalSpent  = orderHistory.reduce((s, o) => s + (parseFloat(o.total) || 0), 0);
            const totalItems  = orderHistory.reduce((s, o) => s + (o.items || []).reduce((ss, i) => ss + (i.quantity || 1), 0), 0);

            const statsHtml = `
                <div class="orders-stats">
                    <div class="orders-stat-card"><div class="orders-stat-icon">📦</div><div class="orders-stat-value">${totalOrders}</div><div class="orders-stat-label">Commandes</div></div>
                    <div class="orders-stat-card"><div class="orders-stat-icon">💰</div><div class="orders-stat-value">${formatPrice(totalSpent)}</div><div class="orders-stat-label">Total dépensé</div></div>
                    <div class="orders-stat-card"><div class="orders-stat-icon">🎨</div><div class="orders-stat-value">${totalItems}</div><div class="orders-stat-label">Œuvres achetées</div></div>
                </div>`;

            const ordersHtml = orderHistory.map(order => {
                const es = order.escrow_status || 'payée_en_attente';
                const { escrowSteps, serverTimeline } = buildOrderTimeline(order);
                const trackUrl = order.tracking_url || getTrackingUrl(order.carrier, order.tracking_number);

                const trackingBlock = order.tracking_number ? `
                    <div style="background:rgba(33,150,243,0.12);border:1px solid rgba(33,150,243,0.35);border-radius:12px;padding:14px 18px;margin:12px 0;">
                        <div style="font-weight:700;font-size:13px;margin-bottom:6px;color:#90caf9;">📦 Suivi de livraison</div>
                        <div style="font-size:14px;">
                            ${order.carrier ? `<span style="opacity:0.7;">${order.carrier.toUpperCase()} · </span>` : ''}
                            <strong>${order.tracking_number}</strong>
                        </div>
                        ${trackUrl ? `<a href="${trackUrl}" target="_blank" rel="noopener" style="display:inline-block;margin-top:10px;padding:8px 18px;background:linear-gradient(135deg,#2196f3,#1976d2);color:white;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">🔍 Suivre mon colis</a>` : ''}
                        ${order.shipping_proof_url ? `<a href="${order.shipping_proof_url}" target="_blank" style="display:inline-block;margin:10px 0 0 10px;padding:8px 18px;background:rgba(255,255,255,0.1);color:white;border-radius:8px;font-size:13px;text-decoration:none;">📎 Voir preuve d'expédition</a>` : ''}
                    </div>` : '';

                const confirmBtn = es === 'expédiée' ? `
                    <button class="confirm-reception-btn" onclick="confirmReception('${order.server_id || order.id}')">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                        J'ai bien reçu mon œuvre — Libérer les fonds
                    </button>` : '';

                const countdownHtml = es === 'expédiée' && order.escrow_auto_release_date ? (() => {
                    const daysLeft = Math.max(0, Math.ceil((new Date(order.escrow_auto_release_date) - Date.now()) / 86400000));
                    return `<div class="escrow-countdown">⏱ Libération automatique dans ${daysLeft} jour${daysLeft > 1 ? 's' : ''} si aucun litige</div>`;
                })() : '';

                const timelineBlock = serverTimeline ? `
                    <details style="margin-top:12px;">
                        <summary style="cursor:pointer;font-size:13px;font-weight:600;opacity:0.8;padding:8px 0;">🕐 Historique détaillé</summary>
                        <div style="margin-top:10px;padding:10px;background:rgba(0,0,0,0.2);border-radius:10px;">${serverTimeline}</div>
                    </details>` : '';

                return `
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <div class="order-id">Commande ${order.order_number || '#' + (order.server_id || order.id)}</div>
                            <div class="order-date">📅 ${order.date || new Date(order.created_at).toLocaleDateString('fr-FR')}</div>
                        </div>
                        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
                            <span class="order-status" style="background:${statusColor(order.status)}22;color:${statusColor(order.status)};border:1px solid ${statusColor(order.status)}44;padding:5px 14px;border-radius:20px;font-size:12px;font-weight:700;">
                                ${statusIcon(order.status)} ${order.status}
                            </span>
                            <span class="${es === 'fonds_libérés' ? 'funds-released-badge' : 'funds-locked-badge'}">
                                ${es === 'fonds_libérés' ? '✅ Fonds libérés' : '🔒 Fonds sécurisés'}
                            </span>
                        </div>
                    </div>

                    <div class="escrow-progress">${escrowSteps}</div>

                    ${trackingBlock}
                    ${countdownHtml}
                    ${confirmBtn}

                    <div style="background:rgba(0,0,0,0.2);border-radius:12px;padding:15px;margin:12px 0;">
                        ${(order.items || []).map(item => `
                            <div style="display:flex;gap:12px;align-items:center;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.08);">
                                ${item.image || item.image_url ? `<img src="${item.image || item.image_url}" style="width:48px;height:48px;border-radius:8px;object-fit:cover;">` : '<div style="width:48px;height:48px;border-radius:8px;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;">🎨</div>'}
                                <div style="flex:1;">
                                    <div style="font-weight:600;font-size:14px;">${item.title}</div>
                                    <div style="font-size:12px;opacity:0.6;">par ${item.artist || item.artist_name}</div>
                                </div>
                                <div style="font-weight:700;color:var(--ocre);">${formatPrice((item.price || 0) * (item.quantity || 1))}</div>
                            </div>`).join('')}
                        <div style="display:flex;justify-content:space-between;padding-top:12px;font-size:15px;font-weight:700;">
                            <span>Total</span><span style="color:var(--ocre);">${formatPrice(parseFloat(order.total) || 0)}</span>
                        </div>
                    </div>

                    ${timelineBlock}
                </div>`;
            }).join('');

            container.innerHTML = statsHtml + ordersHtml;
        }


        function updateAdminOrderCounts(orders) {
            document.getElementById('countAll').textContent = orders.length;
            document.getElementById('countPending').textContent = orders.filter(o => o.status === 'En préparation').length;
            document.getElementById('countShipped').textContent = orders.filter(o => o.status === 'Expédiée').length;
            document.getElementById('countDelivered').textContent = orders.filter(o => o.status === 'Livrée').length;
        }

        function filterAdminOrders(status) {
            currentAdminFilter = status;
            
            // Update active button
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            const activeBtn = document.querySelector(`[data-filter="${status}"]`);
            if (activeBtn) activeBtn.classList.add('active');
            
            // Re-render orders
            renderAdminOrders();
        }

        function changeOrderStatus(orderId, newStatus) {
            const orders = safeStorage.get('arkyl_orders', []);
            const order = orders.find(o => o.id == orderId);
            
            if (order) {
                const oldStatus = order.status;
                order.status = newStatus;
                
                // Add timestamp for status change
                const now = new Date();
                const formattedDate = now.toLocaleDateString('fr-FR', {
                    day: '2-digit',
                    month: 'long',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                if (newStatus === 'Expédiée') {
                    order.shippedDate = formattedDate;
                    order.escrow_status = 'expédiée';
                    order.escrow_shipped_at = new Date().toISOString();
                } else if (newStatus === 'Livrée') {
                    order.deliveredDate = formattedDate;
                    order.escrow_status = 'fonds_libérés';
                    order.escrow_confirmed_at = new Date().toISOString();
                    order.escrow_released_at  = new Date().toISOString();
                }
                
                safeStorage.set('arkyl_orders', orders);
                orderHistory = orders;
                
                // 🔔 ENVOYER DES NOTIFICATIONS À TOUS LES CONCERNÉS
                sendStatusChangeNotifications(order, oldStatus, newStatus);
                
                const emoji = newStatus === 'Expédiée' ? '🚚' : '✅';
                const message = newStatus === 'Expédiée' 
                    ? `${emoji} Commande #${orderId} expédiée !` 
                    : `${emoji} Commande #${orderId} livrée avec succès !`;
                
                showToast(message);
                
                // Change filter to show the new status section
                setTimeout(() => {
                    filterAdminOrders(newStatus);
                } 300);
            }
        }

        // ===== TIERS DE CONFIANCE — ACHETEUR =====
        async function confirmReception(orderId) {
            const btn = event?.target?.closest('.confirm-reception-btn');
            if (btn) { btn.disabled = true; btn.textContent = '⏳ Confirmation en cours...'; }

            try {
                const resp = fetch(ORDERS_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                    body: JSON.stringify({ action: 'confirm_reception', order_id: orderId })
                });
                if (data.success) {
                    showToast('✅ Réception confirmée ! Fonds en cours de libération...');
                    addNotification('Œuvre reçue', 'Merci d\'avoir confirmé la réception. Les fonds sont transférés à l\'artiste.');
                    setTimeout(async () => {
                        showToast('💰 Fonds libérés — l\'artiste a été payé !');
                        renderOrders();
                    } 2000);
                }
            } catch(e) {
                // Fallback local
                const orders = safeStorage.get('arkyl_orders', []);
                const order  = orders.find(o => o.id == orderId || o.server_id == orderId);
                if (order) {
                    order.escrow_status = 'fonds_libérés';
                    order.status = 'Livrée';
                    safeStorage.set('arkyl_orders', orders);
                    orderHistory = orders;
                }
                showToast('✅ Réception confirmée (hors ligne)');
                renderOrders();
            }
        }

        // ===== TIERS DE CONFIANCE — ADMIN : marquer expédiée avec preuve =====
        function markAsShippedWithProof(orderId) {
            const tracking = document.getElementById(`tracking-${orderId}`)?.value?.trim();
            const proofInput = document.getElementById(`proof-${orderId}`);
            const proofFile  = proofInput?.files?.[0];

            if (!tracking && !proofFile) {
                showToast('⚠️ Ajoutez au moins un numéro de suivi ou une photo de reçu');
                return;
            }

            const orders = safeStorage.get('arkyl_orders', []);
            const order  = orders.find(o => o.id == orderId);
            if (!order) return;

            order.escrow_status    = 'expédiée';
            order.escrow_shipped_at = new Date().toISOString();
            order.status = 'Expédiée';
            if (tracking) order.tracking_number = tracking;

            // Si photo uploadée, lire en base64 (simulation locale)
            if (proofFile) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    order.shipping_proof_url = e.target.result;
                    safeStorage.set('arkyl_orders', orders);
                    orderHistory = orders;
                    renderAdminOrders();
                    showToast('🚚 Commande marquée expédiée avec preuve !');
                    addNotification('Preuve d\'expédition', `Commande ${order.order_number || '#'+orderId} : preuve d'expédition enregistrée`);
                };
                
            // NOUVELLE VERSION : Upload direct vers Cloudinary
            if (proofFile) {
                showToast('⏳ Upload de la preuve en cours...');
                try {
                    // 1. On envoie l'image sur le Cloud
                    const secureUrl = uploadImageToCloudinary(proofFile);
                    
                    // 2. On met juste l'URL propre dans le payload JSON
                    payload.shipping_proof_url = secureUrl;
                    
                    // 3. On envoie au serveur PHP
                    sendStatusUpdate(payload);
                } catch (e) {
                    showToast('❌ Échec de l\'envoi de l\'image de preuve');
                }
            } else {
                sendStatusUpdate(payload);
            }

            renderArtistOrders();
        }

        // ===== BADGES HAMBURGER — commandes en cours =====
        function updateHamburgerOrderBadges() {
            const orders = safeStorage.get('arkyl_orders', []);
            const shippingBadge = document.getElementById('hamburgerShippingBadge');
            const confirmBtn    = document.getElementById('hamburgerConfirmBtn');
            const hasShipped    = orders.some(o => o.escrow_status === 'expédiée');
            if (shippingBadge)  shippingBadge.style.display = hasShipped ? 'inline-block' : 'none';
            if (confirmBtn) {
                confirmBtn.style.display = hasShipped ? 'flex' : 'none';
                // Recalculer les positions après changement de visibilité
                setTimeout(() => positionOrbitalItems(), 10);
            }
        }

        function toggleNotifications() {
            const panel = document.getElementById('notificationsPanel');
            panel.classList.toggle('show');
            renderNotifications();
        }

        // Fermer les notifications en cliquant ailleurs
        document.addEventListener('click', function(e) {
            const panel = document.getElementById('notificationsPanel');
            const btn = document.querySelector('[onclick="toggleNotifications()"]');
            if (panel && panel.classList.contains('show') && !panel.contains(e.target) && btn && !btn.contains(e.target)) {
                panel.classList.remove('show');
            }
        });

        function renderNotifications() {
            const container = document.getElementById('notificationsList');
            if (notifications.length === 0) {
                container.innerHTML = '<p style="text-align:center;opacity:0.7;">Aucune notification</p>';
                return;
            }

            container.innerHTML = notifications.map(notif => {
                // Ajouter une mini-image si disponible
                const miniImage = notif.packageImage ? `
                    <div style="width: 50px; height: 50px; border-radius: 8px; overflow: hidden; margin-right: 12px; flex-shrink: 0;">
                        <img loading="lazy" src="${notif.packageImage}" 
                             alt="Colis" 
                             style="width: 100%; height: 100%; object-fit: cover;"
                             onerror="this.parentElement.style.display='none'">
                    </div>
                ` : '';
                
                return `
                <div class="notif-item ${notif.unread ? 'unread' : ''}" 
                     ${notif.type ? `data-type="${notif.type}"` : ''} 
                     onclick="openNotificationDetail(${notif.id})"
                     style="display: flex; align-items: center;">
                    ${miniImage}
                    <div style="flex: 1; min-width: 0;">
                        <div class="notif-title">${notif.title}</div>
                        <div class="notif-text" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${notif.text}</div>
                        <div class="notif-time">${notif.time}</div>
                    </div>
                </div>
            `;
            }).join('');
        }

        function markAsRead(id) {
            const notif = notifications.find(n => n.id === id);
            if (notif) notif.unread = false;
            safeStorage.set('arkyl_notifications', notifications);
            renderNotifications();
            updateBadges();
        }

        function markAllAsRead() {
            if (notifications.length === 0) {
                showToast('📭 Aucune notification à marquer');
                return;
            }
            
            const unreadCount = notifications.filter(n => n.unread).length;
            
            if (unreadCount === 0) {
                showToast('✓ Toutes les notifications sont déjà lues');
                return;
            }
            
            // Marquer toutes les notifications comme lues
            notifications.forEach(notif => {
                notif.unread = false;
            });
            
            safeStorage.set('arkyl_notifications', notifications);
            renderNotifications();
            updateBadges();
            
            showToast(`✓ ${unreadCount} notification${unreadCount > 1 ? 's' : ''} marquée${unreadCount > 1 ? 's' : ''} comme lue${unreadCount > 1 ? 's' : ''}`);
        }

        // ==================== NOTIFICATION DETAIL MODAL ====================
        function openNotificationDetail(id) {
            const notif = notifications.find(n => n.id === id);
            if (!notif) return;

            // Marquer comme lu
            markAsRead(id);

            // Fermer le panneau de notifications
            document.getElementById('notificationsPanel').classList.remove('show');

            // Remplir le modal avec les détails
            const modal = document.getElementById('notificationModal');
            
            // Icône selon le type
            let icon = '🔔';
            let typeName = 'Notification';
            
            if (notif.type === 'order-client') {
                icon = '🎉';
                typeName = 'Commande Client';
            } else if (notif.type === 'order-admin') {
                icon = '💰';
                typeName = 'Nouvelle Vente';
            } else if (notif.type === 'order-status') {
                icon = '📦';
                typeName = 'Mise à jour';
            } else if (notif.type === 'order-artist') {
                icon = '🎨';
                typeName = 'Vente Artiste';
            }

            document.getElementById('notificationModalIcon').textContent = icon;
            document.getElementById('notificationModalType').textContent = typeName;
            document.getElementById('notificationModalTitle').textContent = notif.title;
            document.getElementById('notificationModalTime').textContent = notif.time;

            // Afficher l'image du colis si disponible
            const packageImageContainer = document.getElementById('notificationModalPackageImage');
            if (notif.packageImage) {
                packageImageContainer.innerHTML = `
                    <div style="width: 100%; height: 200px; border-radius: 16px; overflow: hidden; margin-bottom: 20px; box-shadow: 0 8px 24px rgba(0,0,0,0.3);">
                        <img loading="lazy" src="${notif.packageImage}" 
                             alt="Colis" 
                             style="width: 100%; height: 100%; object-fit: cover;"
                             onerror="this.parentElement.style.display='none'">
                    </div>
                `;
            } else {
                packageImageContainer.innerHTML = '';
            }

            // Parser les détails du texte
            const details = parseNotificationDetails(notif);
            const detailsHTML = details.map(detail => `
                <div class="notification-modal-detail-item">
                    <span class="notification-modal-detail-label">${detail.label}</span>
                    <span class="notification-modal-detail-value">${detail.value}</span>
                </div>
            `).join('');

            document.getElementById('notificationModalDetails').innerHTML = detailsHTML;

            // Afficher la galerie d'articles si disponible
            const itemsGalleryContainer = document.getElementById('notificationModalItemsGallery');
            if (notif.orderDetails && notif.orderDetails.items && notif.orderDetails.items.length > 0) {
                const itemsGalleryHTML = `
                    <div style="margin: 20px 0;">
                        <div style="font-size: 16px; font-weight: 600; margin-bottom: 15px; color: var(--ocre);">
                            🎨 Articles commandés
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px;">
                            ${notif.orderDetails.items.map(item => {
                                const emoji = getDefaultEmoji(item);
                                const imageHtml = item.image 
                                    ? `<img loading="lazy" src="${item.image}" 
                                            alt="${item.title}" 
                                            style="width: 100%; height: 100%; object-fit: cover;"
                                            onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\\'font-size:3rem;display:flex;align-items:center;justify-content:center;height:100%;\\'>${emoji}</div>';">`
                                    : `<div style="font-size: 3rem; display: flex; align-items: center; justify-content: center; height: 100%;">${emoji}</div>`;
                                
                                return `
                                    <div style="background: rgba(255,255,255,0.05); border-radius: 12px; padding: 10px; text-align: center; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer;" 
                                         onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 20px rgba(0,0,0,0.3)';" 
                                         onmouseout="this.style.transform=''; this.style.boxShadow='';">
                                        <div style="width: 100%; height: 100px; border-radius: 8px; overflow: hidden; margin-bottom: 8px; position: relative; background: linear-gradient(135deg, rgba(212, 165, 116, 0.2), rgba(196, 106, 75, 0.2));">
                                            ${imageHtml}
                                            ${item.image ? `<div style="position: absolute; bottom: 4px; right: 4px; background: rgba(255,255,255,0.95); border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; font-size: 1rem; box-shadow: 0 2px 6px rgba(0,0,0,0.15);">
                                                ${emoji}
                                            </div>` : ''}
                                        </div>
                                        <div style="font-size: 12px; font-weight: 600; margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${item.title}">
                                            ${item.title}
                                        </div>
                                        <div style="font-size: 11px; opacity: 0.7; margin-bottom: 4px;">
                                            ${item.artist}
                                        </div>
                                        <div style="font-size: 12px; font-weight: 700; color: var(--ocre);">
                                            ${item.quantity > 1 ? `${item.quantity}× ` : ''}${formatPrice(item.price * item.quantity)}
                                        </div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                `;
                itemsGalleryContainer.innerHTML = itemsGalleryHTML;
            } else if (notif.artistDetails && notif.artistDetails.items && notif.artistDetails.items.length > 0) {
                // Pour les notifications artistes
                const itemsGalleryHTML = `
                    <div style="margin: 20px 0;">
                        <div style="font-size: 16px; font-weight: 600; margin-bottom: 15px; color: var(--ocre);">
                            🎨 Vos œuvres vendues
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px;">
                            ${notif.artistDetails.items.map(item => {
                                const emoji = getDefaultEmoji(item);
                                const imageHtml = item.image 
                                    ? `<img loading="lazy" src="${item.image}" 
                                            alt="${item.title}" 
                                            style="width: 100%; height: 100%; object-fit: cover;"
                                            onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\\'font-size:3rem;display:flex;align-items:center;justify-content:center;height:100%;\\'>${emoji}</div>';">`
                                    : `<div style="font-size: 3rem; display: flex; align-items: center; justify-content: center; height: 100%;">${emoji}</div>`;
                                
                                return `
                                    <div style="background: rgba(255,255,255,0.05); border-radius: 12px; padding: 10px; text-align: center; cursor: pointer;" 
                                         onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 20px rgba(0,0,0,0.3)';" 
                                         onmouseout="this.style.transform=''; this.style.boxShadow='';">
                                        <div style="width: 100%; height: 100px; border-radius: 8px; overflow: hidden; margin-bottom: 8px; position: relative; background: linear-gradient(135deg, rgba(212, 165, 116, 0.2), rgba(196, 106, 75, 0.2));">
                                            ${imageHtml}
                                            ${item.image ? `<div style="position: absolute; bottom: 4px; right: 4px; background: rgba(255,255,255,0.95); border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; font-size: 1rem; box-shadow: 0 2px 6px rgba(0,0,0,0.15);">
                                                ${emoji}
                                            </div>` : ''}
                                        </div>
                                        <div style="font-size: 12px; font-weight: 600; margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${item.title}">
                                            ${item.title}
                                        </div>
                                        <div style="font-size: 12px; font-weight: 700; color: var(--ocre);">
                                            ${item.quantity > 1 ? `${item.quantity}× ` : ''}${formatPrice(item.price * item.quantity)}
                                        </div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                `;
                itemsGalleryContainer.innerHTML = itemsGalleryHTML;
            } else {
                itemsGalleryContainer.innerHTML = '';
            }

            // Gérer les actions selon le type
            const actionsContainer = document.getElementById('notificationModalActions');
            if (notif.orderId) {
                actionsContainer.innerHTML = `
                    <button class="notification-modal-btn notification-modal-btn-primary" onclick="viewOrder(${notif.orderId})">
                        📋 Voir la commande
                    </button>
                    <button class="notification-modal-btn notification-modal-btn-secondary" onclick="closeNotificationModal()">
                        Fermer
                    </button>
                `;
            } else {
                actionsContainer.innerHTML = `
                    <button class="notification-modal-btn notification-modal-btn-primary" onclick="closeNotificationModal()">
                        OK, compris
                    </button>
                `;
            }

            modal.classList.add('show');
        }

        function parseNotificationDetails(notif) {
            const details = [];
            const text = notif.text;

            // Parser selon le type
            if (notif.type === 'order-client' || notif.type === 'order-admin' || notif.type === 'order-artist') {
                // Extraire les informations de la commande
                const commandeMatch = text.match(/Commande #(\d+)/);
                const articlesMatch = text.match(/(\d+) article[s]?/);
                const oeuvresMatch = text.match(/(\d+) œuvre[s]?/);
                const montantMatch = text.match(/Montant total:\s*([\d\s]+)\s*(?:CFA|FCFA)/);
                const revenuMatch = text.match(/Revenu:\s*([\d\s]+)\s*(?:CFA|FCFA)/);
                const totalMatch = !montantMatch && !revenuMatch ? text.match(/(\d[\d\s]*\d+)\s*(?:CFA|FCFA)/) : null;
                const paiementMatch = text.match(/Paiement:\s*([^•]+)/);
                const modeMatch = text.match(/Mode de paiement:\s*([^•]+)/);
                const livraisonMatch = text.match(/Livraison:\s*([^•]+)/);
                const adresseMatch = text.match(/Adresse:\s*([^•]+)/);
                const statutMatch = text.match(/Statut:\s*([^•]+?)(?:•|$)/);
                const clientMatch = text.match(/Client:\s*([^•(]+)/);
                const emailMatch = text.match(/\(([^)]+@[^)]+)\)/);
                const taxesMatch = text.match(/(?:dont|TVA[^:]*:)\s*([\d\s]+)\s*(?:CFA|FCFA)/);
                const sousTotalMatch = text.match(/Sous-total:\s*([\d\s]+)\s*(?:CFA|FCFA)/);
                const articlesListMatch = text.match(/Articles:\s*([^•]+)/);
                const oeuvresListMatch = text.match(/Vos œuvres:\s*([^•(]+)/);

                if (commandeMatch) details.push({ label: '📋 Numéro', value: `#${commandeMatch[1]}` });
                
                if (notif.type === 'order-artist' && notif.artistName) {
                    details.push({ label: '🎨 Artiste', value: notif.artistName });
                }
                
                if (clientMatch) {
                    let clientValue = clientMatch[1].trim();
                    if (emailMatch) clientValue += ` (${emailMatch[1]})`;
                    details.push({ label: '👤 Client', value: clientValue });
                }
                
                if (oeuvresMatch && notif.type === 'order-artist') {
                    details.push({ label: '🖼️ Nombre d\'œuvres', value: oeuvresMatch[1] });
                } else if (articlesMatch) {
                    details.push({ label: '🛍️ Nombre d\'articles', value: articlesMatch[1] });
                }
                
                if (oeuvresListMatch) {
                    details.push({ label: '🎨 Vos œuvres', value: oeuvresListMatch[1].trim() });
                } else if (articlesListMatch) {
                    details.push({ label: '📦 Articles', value: articlesListMatch[1].trim() });
                }
                
                if (sousTotalMatch) details.push({ label: '💵 Sous-total', value: `${sousTotalMatch[1].trim()} FCFA` });
                if (taxesMatch) details.push({ label: '📊 TVA (18%)', value: `${taxesMatch[1].trim()} FCFA` });
                
                if (revenuMatch) {
                    details.push({ label: '💰 Votre revenu', value: `${revenuMatch[1].trim()} FCFA` });
                } else if (montantMatch || totalMatch) {
                    const amount = montantMatch ? montantMatch[1] : totalMatch[1];
                    details.push({ label: '💰 Montant total', value: `${amount.trim()} FCFA` });
                }
                
                if (modeMatch) {
                    details.push({ label: '💳 Mode de paiement', value: modeMatch[1].trim() });
                } else if (paiementMatch) {
                    details.push({ label: '💳 Paiement', value: paiementMatch[1].trim() });
                }
                
                if (livraisonMatch) details.push({ label: '🚚 Type de livraison', value: livraisonMatch[1].trim() });
                if (adresseMatch && adresseMatch[1].trim() !== 'À définir') {
                    details.push({ label: '📍 Adresse de livraison', value: adresseMatch[1].trim() });
                }
                if (statutMatch) details.push({ label: '📦 Statut', value: statutMatch[1].trim() });
                
            } else if (notif.type === 'order-status') {
                // Extraire les informations de mise à jour
                const commandeMatch = text.match(/Commande #(\d+)/);
                const articlesMatch = text.match(/(\d+) article[s]?/);
                const montantMatch = text.match(/(\d[\d\s]*\d+)\s*(?:CFA|FCFA)/);
                const changeMatch = text.match(/Statut:\s*(.+?)\s*→\s*(.+?)(?:$|•)/);
                const clientMatch = text.match(/Client:\s*([^•(]+)/);
                const emailMatch = text.match(/\(([^)]+@[^)]+)\)/);
                const articlesListMatch = text.match(/Articles:\s*([^•]+)/);
                const livraisonMatch = text.match(/Livraison:\s*([^•]+)/);
                const paiementMatch = text.match(/Paiement:\s*([^•]+)/);

                if (commandeMatch) details.push({ label: '📋 Numéro', value: `#${commandeMatch[1]}` });
                if (clientMatch) {
                    let clientValue = clientMatch[1].trim();
                    if (emailMatch) clientValue += ` (${emailMatch[1]})`;
                    details.push({ label: '👤 Client', value: clientValue });
                }
                if (articlesMatch) details.push({ label: '🛍️ Nombre d\'articles', value: articlesMatch[1] });
                if (articlesListMatch) details.push({ label: '📦 Articles', value: articlesListMatch[1].trim() });
                if (montantMatch) details.push({ label: '💰 Montant', value: montantMatch[0] });
                if (paiementMatch) details.push({ label: '💳 Paiement', value: paiementMatch[1].trim() });
                if (livraisonMatch) details.push({ label: '🚚 Livraison', value: livraisonMatch[1].trim() });
                
                if (changeMatch) {
                    details.push({ label: '📦 Ancien statut', value: changeMatch[1].trim() });
                    details.push({ label: '✅ Nouveau statut', value: changeMatch[2].trim() });
                }
                
                // Ajouter la date de changement si disponible
                if (notif.statusChange && notif.statusChange.date) {
                    details.push({ label: '📅 Date de mise à jour', value: notif.statusChange.date });
                }
            } else {
                // Notification générique
                details.push({ label: '📝 Message', value: text });
            }

            return details;
        }

        function closeNotificationModal() {
            document.getElementById('notificationModal').classList.remove('show');
        }

        function viewOrder(orderId) {
            closeNotificationModal();
            // Naviguer vers la page des commandes ou afficher les détails
            navigateTo('orders');
            showToast(`📋 Affichage de la commande #${orderId}`);
        }

        function addNotification(title, text) {
            const newNotif = {
                id: Date.now(),
                title: title,
                text: text,
                time: 'À l\'instant',
                unread: true
            };
            notifications.unshift(newNotif);
            safeStorage.set('arkyl_notifications', notifications);
            updateBadges();
        }

        // 📦 FONCTION: Générer une image visuelle de colis selon le statut et le nombre d'articles
        function generatePackageImage(itemCount, status) {
            // Sélectionner l'image selon le statut
            const statusImages = {
                'new': 'https://images.unsplash.com/photo-1607166452427-7e4477079cb9?w=400&h=400&fit=crop&q=80', // Colis emballé neuf
                'En préparation': 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?w=400&h=400&fit=crop&q=80', // Colis en préparation
                'Expédiée': 'https://images.unsplash.com/photo-1566576721346-d4a3b4eaeb55?w=400&h=400&fit=crop&q=80', // Camion de livraison
                'En livraison': 'https://images.unsplash.com/photo-1578575437130-527eed3abbec?w=400&h=400&fit=crop&q=80', // Livreur
                'Livrée': 'https://images.unsplash.com/photo-1600880292203-757bb62b4baf?w=400&h=400&fit=crop&q=80', // Colis livré
                'Annulée': 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=400&h=400&fit=crop&q=80' // Colis annulé
            };
            
            // Images alternatives selon le nombre d'articles (pour variété visuelle)
            const sizeVariations = [
                'https://images.unsplash.com/photo-1607166452427-7e4477079cb9?w=400&h=400&fit=crop&q=80', // Petit colis
                'https://images.unsplash.com/photo-1609081844754-5d9caa01e0e0?w=400&h=400&fit=crop&q=80', // Colis moyen
                'https://images.unsplash.com/photo-1605902711834-8b11c3e3ef2f?w=400&h=400&fit=crop&q=80'  // Grand colis
            ];
            
            // Choisir l'image selon le statut ou le nombre d'articles
            let imageUrl = statusImages[status] || statusImages['new'];
            
            // Pour les nouvelles commandes, varier selon la taille
            if (status === 'new' && itemCount > 0) {
                if (itemCount <= 2) {
                    imageUrl = sizeVariations[0]; // Petit
                } else if (itemCount <= 5) {
                    imageUrl = sizeVariations[1]; // Moyen
                } else {
                    imageUrl = sizeVariations[2]; // Grand
                }
            }
            
            return imageUrl;
        }

        // 🎯 NOUVELLE FONCTION: Envoyer des notifications détaillées pour les commandes
        function sendOrderNotifications(order) {
            const itemsCount = order.items.reduce((sum, item) => sum + item.quantity, 0);
            const itemsList = order.items.map(item => `${item.quantity}x ${item.title}`).join(', ');
            
            // Récupérer les artistes concernés par cette commande
            const artistsInOrder = [...new Set(order.items.map(item => item.artist).filter(Boolean))];
            
            // 📦 Générer une image de colis selon le nombre d'articles
            const packageImage = generatePackageImage(itemsCount, 'new');
            
            // 📦 NOTIFICATION CLIENT - Détaillée et informative
            const clientNotification = {
                id: Date.now(),
                title: '🎉 Commande confirmée !',
                text: `Merci pour votre achat ! Votre commande a été confirmée avec succès • Commande #${order.id} • ${itemsCount} article${itemsCount > 1 ? 's' : ''} • Montant total: ${formatPrice(order.total)} (dont ${formatPrice(order.tax)} de TVA) • Articles: ${itemsList} • Paiement: ${order.paymentMethod} • Livraison: ${order.shippingName} • Adresse: ${order.shippingAddress || 'À définir'} • Statut: ${order.status}`,
                time: 'À l\'instant',
                unread: true,
                type: 'order-client',
                orderId: order.id,
                packageImage: packageImage,
                orderDetails: {
                    items: order.items,
                    total: order.total,
                    subtotal: order.subtotal,
                    tax: order.tax,
                    shipping: order.shippingName,
                    payment: order.paymentMethod,
                    date: order.date
                }
            };
            
            // 💼 NOTIFICATION ADMIN - Informations de gestion
            if (currentUser && currentUser.isAdmin) {
                const adminNotification = {
                    id: Date.now() + 1, // ID unique différent
                    title: '💰 Nouvelle vente !',
                    text: `Une nouvelle commande vient d'être passée • Client: ${order.user}${order.userEmail ? ` (${order.userEmail})` : ''} • Commande #${order.id} • ${itemsCount} article${itemsCount > 1 ? 's' : ''} • Montant total: ${formatPrice(order.total)} (Sous-total: ${formatPrice(order.subtotal)} + TVA: ${formatPrice(order.tax)}) • Articles: ${itemsList} • Paiement: ${order.paymentMethod} • Livraison: ${order.shippingName} • Adresse: ${order.shippingAddress || 'À définir'} • Statut: ${order.status}`,
                    time: 'À l\'instant',
                    unread: true,
                    type: 'order-admin',
                    orderId: order.id,
                    packageImage: packageImage,
                    orderDetails: {
                        customer: order.user,
                        customerEmail: order.userEmail,
                        items: order.items,
                        total: order.total,
                        subtotal: order.subtotal,
                        tax: order.tax,
                        shipping: order.shippingName,
                        payment: order.paymentMethod,
                        date: order.date
                    }
                };
                
                // Ajouter la notification admin
                notifications.unshift(adminNotification);
            }
            
            // 🎨 NOTIFICATIONS ARTISTES - Pour chaque artiste dont l'œuvre a été vendue
            artistsInOrder.forEach((artistName, index) => {
                const artistItems = order.items.filter(item => item.artist === artistName);
                const artistItemsCount = artistItems.reduce((sum, item) => sum + item.quantity, 0);
                const artistItemsList = artistItems.map(item => `${item.quantity}x ${item.title}`).join(', ');
                const artistRevenue = artistItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                const artistPackageImage = generatePackageImage(artistItemsCount, 'new');
                
                const artistNotification = {
                    id: Date.now() + 2 + index, // ID unique
                    title: '🎨 Vente de votre œuvre !',
                    text: `Félicitations ! Vos œuvres ont été vendues • Commande #${order.id} • Client: ${order.user} • ${artistItemsCount} œuvre${artistItemsCount > 1 ? 's' : ''} vendue${artistItemsCount > 1 ? 's' : ''} • Vos œuvres: ${artistItemsList} • Revenu: ${formatPrice(artistRevenue)} • Mode de paiement: ${order.paymentMethod} • Livraison: ${order.shippingName} • Statut: ${order.status}`,
                    time: 'À l\'instant',
                    unread: true,
                    type: 'order-artist',
                    orderId: order.id,
                    artistName: artistName,
                    packageImage: artistPackageImage,
                    artistDetails: {
                        items: artistItems,
                        revenue: artistRevenue,
                        itemsCount: artistItemsCount,
                        customer: order.user,
                        date: order.date
                    }
                };
                
                notifications.unshift(artistNotification);
            });
            
            // Ajouter la notification client
            notifications.unshift(clientNotification);
            
            // Sauvegarder et mettre à jour
            safeStorage.set('arkyl_notifications', notifications);
            updateBadges();
            
            // 📧 Simuler un email de confirmation (en production, appeler une API)
            console.log('📧 ═══════════════════════════════════════════════');
            console.log('📨 Email de confirmation envoyé à:', order.userEmail || order.user);
            console.log('📋 Détails de la commande:');
            console.log('   • Commande #:', order.id);
            console.log('   • Client:', order.user);
            console.log('   • Articles:', itemsList);
            console.log('   • Sous-total:', formatPrice(order.subtotal));
            console.log('   • TVA (18%):', formatPrice(order.tax));
            console.log('   • Total:', formatPrice(order.total));
            console.log('   • Paiement:', order.paymentMethod);
            console.log('   • Livraison:', order.shippingName);
            console.log('   • Adresse:', order.shippingAddress || 'À définir');
            console.log('   • Statut:', order.status);
            console.log('   • Date:', order.date);
            console.log('');
            console.log('🎨 Notifications envoyées aux artistes:');
            artistsInOrder.forEach(artistName => {
                console.log(`   • ${artistName}`);
            });
            console.log('═══════════════════════════════════════════════');
            
            // Afficher une notification de confirmation
            showToast('🎉 Commande confirmée ! Notifications envoyées.');
        }

        // 🚚 NOUVELLE FONCTION: Mettre à jour le statut d'une commande
        ,
                'Expédiée': { icon: '🚚', client: 'Votre commande a été expédiée !', admin: 'Commande expédiée' }
                'En livraison': { icon: '🛵', client: 'Votre commande est en cours de livraison', admin: 'Commande en livraison' }
                'Livrée': { icon: '✅', client: 'Votre commande a été livrée avec succès !', admin: 'Commande livrée' }
                'Annulée': { icon: '❌', client: 'Votre commande a été annulée', admin: 'Commande annulée' }
            };
            
            const statusInfo = statusMessages[newStatus] || statusMessages['En préparation'];
            
            // Notification client
            const clientNotification = {
                id: Date.now(),
                title: `${statusInfo.icon} Mise à jour de commande`,
                text: `${statusInfo.client} • Commande #${orderId} • Statut: ${oldStatus} → ${newStatus}`,
                time: 'À l\'instant',
                unread: true,
                type: 'order-status',
                orderId: orderId
            };
            
            notifications.unshift(clientNotification);
            
            // Notification admin si applicable
            if (currentUser && currentUser.isAdmin) {
                const adminNotification = {
                    id: Date.now() + 1,
                    title: `📊 Statut modifié`,
                    text: `${statusInfo.admin} • Commande #${orderId} • Client: ${order.user} • ${oldStatus} → ${newStatus}`,
                    time: 'À l\'instant',
                    unread: true,
                    type: 'order-admin',
                    orderId: orderId
                };
                
                notifications.unshift(adminNotification);
            }
            
            safeStorage.set('arkyl_notifications', notifications);
            updateBadges();
            renderNotifications();
        }

        // 🔔 NOUVELLE FONCTION: Envoyer des notifications lors des changements de statut
        function sendStatusChangeNotifications(order, oldStatus, newStatus) {
            const itemsCount = order.items.reduce((sum, item) => sum + item.quantity, 0);
            const itemsList = order.items.map(item => `${item.quantity}x ${item.title}`).join(', ');
            
            // Récupérer les artistes concernés par cette commande
            const artistsInOrder = [...new Set(order.items.map(item => item.artist).filter(Boolean))];
            
            // 📦 Générer l'image de colis selon le statut
            const packageImage = generatePackageImage(itemsCount, newStatus);
            
            // Configuration des messages selon le statut
            const statusConfig = {
                'En préparation': {
                    icon: '📦',
                    clientTitle: '📦 Commande en préparation',
                    clientMessage: 'Votre commande est actuellement en cours de préparation par notre équipe',
                    adminTitle: '📦 Commande en cours de préparation',
                    adminMessage: 'La commande est maintenant en préparation',
                    artistTitle: '📦 Préparation de votre œuvre',
                    artistMessage: 'Votre œuvre est en cours de préparation pour livraison'
                }
                'Expédiée': {
                    icon: '🚚',
                    clientTitle: '🚚 Commande expédiée !',
                    clientMessage: 'Bonne nouvelle ! Votre commande a été expédiée et est en route vers vous',
                    adminTitle: '🚚 Commande expédiée avec succès',
                    adminMessage: 'La commande a été expédiée au client',
                    artistTitle: '🚚 Votre œuvre a été expédiée !',
                    artistMessage: 'Votre œuvre est en route vers le client'
                }
                'En livraison': {
                    icon: '🛵',
                    clientTitle: '🛵 Commande en cours de livraison',
                    clientMessage: 'Votre commande est actuellement en cours de livraison et arrivera bientôt',
                    adminTitle: '🛵 Commande en cours de livraison',
                    adminMessage: 'La commande est en livraison chez le client',
                    artistTitle: '🛵 Livraison en cours',
                    artistMessage: 'Votre œuvre est actuellement en cours de livraison'
                }
                'Livrée': {
                    icon: '✅',
                    clientTitle: '✅ Commande livrée !',
                    clientMessage: 'Votre commande a été livrée avec succès ! Merci pour votre confiance',
                    adminTitle: '✅ Livraison confirmée',
                    adminMessage: 'La commande a été livrée au client avec succès',
                    artistTitle: '✅ Œuvre livrée avec succès !',
                    artistMessage: 'Félicitations ! Votre œuvre a été livrée au client'
                }
                'Annulée': {
                    icon: '❌',
                    clientTitle: '❌ Commande annulée',
                    clientMessage: 'Votre commande a été annulée. Contactez-nous pour plus d\'informations',
                    adminTitle: '❌ Commande annulée',
                    adminMessage: 'La commande a été annulée',
                    artistTitle: '❌ Commande annulée',
                    artistMessage: 'La commande de votre œuvre a été annulée'
                }
            };
            
            const config = statusConfig[newStatus] || statusConfig['En préparation'];
            
            // 📧 NOTIFICATION CLIENT - Détaillée et informative
            const clientNotification = {
                id: Date.now(),
                title: config.clientTitle,
                text: `${config.clientMessage} • Commande #${order.id} • ${itemsCount} article${itemsCount > 1 ? 's' : ''} • ${formatPrice(order.total)} • Articles: ${itemsList} • Livraison: ${order.shippingName} • Paiement: ${order.paymentMethod} • Statut: ${oldStatus} → ${newStatus}`,
                time: 'À l\'instant',
                unread: true,
                type: 'order-status',
                orderId: order.id,
                packageImage: packageImage,
                statusChange: {
                    from: oldStatus,
                    to: newStatus,
                    date: order.shippedDate || order.deliveredDate || new Date().toLocaleDateString('fr-FR')
                }
            };
            
            // 💼 NOTIFICATION ADMIN - Informations de gestion
            const adminNotification = {
                id: Date.now() + 1, // ID unique différent
                title: config.adminTitle,
                text: `${config.adminMessage} • Commande #${order.id} • Client: ${order.user}${order.userEmail ? ` (${order.userEmail})` : ''} • ${itemsCount} article${itemsCount > 1 ? 's' : ''} • Montant: ${formatPrice(order.total)} • Mode: ${order.paymentMethod} • Livraison: ${order.shippingName} • Statut: ${oldStatus} → ${newStatus}`,
                time: 'À l\'instant',
                unread: true,
                type: 'order-admin',
                orderId: order.id,
                packageImage: packageImage,
                statusChange: {
                    from: oldStatus,
                    to: newStatus,
                    date: order.shippedDate || order.deliveredDate || new Date().toLocaleDateString('fr-FR')
                }
            };
            
            // 🎨 NOTIFICATIONS ARTISTES - Pour chaque artiste concerné
            artistsInOrder.forEach((artistName, index) => {
                const artistItems = order.items.filter(item => item.artist === artistName);
                const artistItemsCount = artistItems.reduce((sum, item) => sum + item.quantity, 0);
                const artistItemsList = artistItems.map(item => `${item.quantity}x ${item.title}`).join(', ');
                const artistRevenue = artistItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                const artistPackageImage = generatePackageImage(artistItemsCount, newStatus);
                
                const artistNotification = {
                    id: Date.now() + 2 + index, // ID unique
                    title: config.artistTitle,
                    text: `${config.artistMessage} • Commande #${order.id} • Client: ${order.user} • Vos œuvres: ${artistItemsList} (${artistItemsCount} œuvre${artistItemsCount > 1 ? 's' : ''}) • Revenu: ${formatPrice(artistRevenue)} • Livraison: ${order.shippingName} • Statut: ${oldStatus} → ${newStatus}`,
                    time: 'À l\'instant',
                    unread: true,
                    type: 'order-artist',
                    orderId: order.id,
                    artistName: artistName,
                    packageImage: artistPackageImage,
                    statusChange: {
                        from: oldStatus,
                        to: newStatus,
                        date: order.shippedDate || order.deliveredDate || new Date().toLocaleDateString('fr-FR')
                    }
                    artistDetails: {
                        items: artistItems,
                        revenue: artistRevenue,
                        itemsCount: artistItemsCount
                    }
                };
                
                notifications.unshift(artistNotification);
            });
            
            // Ajouter les notifications
            notifications.unshift(clientNotification);
            
            // Ajouter la notification admin si l'utilisateur est admin
            if (currentUser && currentUser.isAdmin) {
                notifications.unshift(adminNotification);
            }
            
            // Sauvegarder et mettre à jour l'interface
            safeStorage.set('arkyl_notifications', notifications);
            updateBadges();
            renderNotifications();
            
            // 📧 Log console pour simuler l'envoi d'emails/SMS
            console.log('📧 ═══════════════════════════════════════════════');
            console.log('📨 Notification envoyée au client:', order.user);
            console.log('📋 Commande:', `#${order.id}`);
            console.log('📦 Nouveau statut:', `${oldStatus} → ${newStatus}`);
            console.log('💰 Montant:', formatPrice(order.total));
            console.log('📧 Email client:', order.userEmail || 'Non fourni');
            console.log('🔔 Message client:', config.clientMessage);
            console.log('');
            console.log('🎨 Notifications envoyées aux artistes:');
            artistsInOrder.forEach(artistName => {
                const artistItems = order.items.filter(item => item.artist === artistName);
                const artistRevenue = artistItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                console.log(`   • ${artistName} - ${artistItems.length} œuvre(s) - ${formatPrice(artistRevenue)}`);
            });
            console.log('═══════════════════════════════════════════════');
            
            // Afficher une notification toast pour confirmer l'envoi
            showToast(`${config.icon} Notifications envoyées avec succès !`);
        }

        // ==================== CHAT ====================

        // ==================== PRODUCT DETAIL ====================
        function viewProductDetail(productId) {
            showLoading();
            
            setTimeout(() => {
                const allProducts = getProducts();
                const product = allProducts.find(p => p.id === productId);
                if (!product) {
                    hideLoading();
                    return;
                }

                // Récupérer toutes les photos (tableau ou photo unique)
                const photos = product.photos && product.photos.length > 0 
                    ? product.photos 
                    : (product.image ? [product.image] : []);
                
                // Créer le carrousel d'images si plusieurs photos
                let imageSection = '';
                if (photos.length > 1) {
                    imageSection = `
                        <div class="product-detail-image" style="position: relative;">
                            <!-- Image principale -->
                            <img id="mainProductImage" src="${photos[0]}" alt="${product.title}" style="width:100%;height:100%;object-fit:cover;border-radius:20px;cursor:pointer;" loading="lazy" onclick="openImageLightbox('${photos[0]}')" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22400%22%3E%3Crect fill=%22%23ddd%22 width=%22400%22 height=%22400%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2248%22%3E🎨%3C/text%3E%3C/svg%3E'">
                            
                            <!-- Indicateur de photo (1/5) -->
                            <div style="position: absolute; top: 15px; left: 15px; background: rgba(0,0,0,0.7); color: white; padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 600;">
                                <span id="currentPhotoIndex">1</span>/${photos.length}
                            </div>
                            
                            <!-- Bouton like -->
                            <button class="like-button" onclick="toggleFavorite(event, ${product.id})" style="position:absolute;top:15px;right:15px;">
                                ${favorites.includes(product.id) ? '❤️' : '🤍'}
                            </button>
                            
                            <!-- Boutons navigation -->
                            ${photos.length > 1 ? `
                                <button onclick="previousPhoto()" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.7); color: white; border: none; width: 45px; height: 45px; border-radius: 50%; cursor: pointer; font-size: 20px; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.3);" onmouseover="this.style.background='rgba(0,0,0,0.9)'; this.style.transform='translateY(-50%) scale(1.1)'" onmouseout="this.style.background='rgba(0,0,0,0.7)'; this.style.transform='translateY(-50%) scale(1)'">
                                    ‹
                                </button>
                                <button onclick="nextPhoto()" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.7); color: white; border: none; width: 45px; height: 45px; border-radius: 50%; cursor: pointer; font-size: 20px; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.3);" onmouseover="this.style.background='rgba(0,0,0,0.9)'; this.style.transform='translateY(-50%) scale(1.1)'" onmouseout="this.style.background='rgba(0,0,0,0.7)'; this.style.transform='translateY(-50%) scale(1)'">
                                    ›
                                </button>
                            ` : ''}
                            
                            <!-- Miniatures des photos -->
                            <div style="display: flex; gap: 10px; margin-top: 15px; overflow-x: auto; padding: 5px 0;">
                                ${photos.map((photo, index) => `
                                    <div onclick="changeMainPhoto(${index})" style="width: 80px; height: 80px; border-radius: 12px; overflow: hidden; cursor: pointer; border: 3px solid ${index === 0 ? 'rgba(212, 165, 116, 0.8)' : 'rgba(255,255,255,0.3)'}; transition: all 0.3s ease; flex-shrink: 0;" class="thumbnail-photo" data-index="${index}" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                                        <img loading="lazy" src="${photo}" alt="Photo ${index + 1}" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `;
                } else {
                    // Une seule photo - affichage classique
                    imageSection = `
                        <div class="product-detail-image">
                            <img src="${photos[0] || product.image}" alt="${product.title}" style="width:100%;height:100%;object-fit:cover;border-radius:20px;cursor:pointer;" loading="lazy" onclick="openImageLightbox('${photos[0] || product.image}')" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22400%22%3E%3Crect fill=%22%23ddd%22 width=%22400%22 height=%22400%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2248%22%3E🎨%3C/text%3E%3C/svg%3E'">
                            <button class="like-button" onclick="toggleFavorite(event, ${product.id})" style="position:absolute;top:20px;right:20px;">
                                ${favorites.includes(product.id) ? '❤️' : '🤍'}
                            </button>
                        </div>
                    `;
                }
                
                // Créer les métadonnées avec les vraies données
                let dimensionsText = 'Non spécifiées';
                let dims2 = product.dimensions;
                if (dims2 && typeof dims2 === 'string') {
                    try { dims2 = JSON.parse(dims2); } catch(e) { dims2 = null; }
                }
                if (dims2 && (dims2.width || dims2.height)) {
                    const parts = [];
                    if (dims2.width) parts.push(`L ${dims2.width} cm`);
                    if (dims2.height) parts.push(`H ${dims2.height} cm`);
                    dimensionsText = parts.join(' × ');
                }
                
                let techniqueText = product.technique || product.techniqueCustom || 'Non spécifiée';
                if (product.technique && product.techniqueCustom && product.technique !== product.techniqueCustom) {
                    techniqueText = `${product.technique} (${product.techniqueCustom})`;
                }
                
                // Gérer les valeurs undefined
                const title = product.title || 'Sans titre';
                const artist = product.artist || 'Artiste inconnu';
                const category = product.category || 'Non spécifiée';
                const description = product.description || `Cette magnifique œuvre capture l'essence de l'art africain contemporain. 
                                    Créée avec passion et savoir-faire, elle représente ${title.toLowerCase()} 
                                    à travers le regard unique de ${artist}. Les couleurs vibrantes et 
                                    la composition harmonieuse en font une pièce exceptionnelle pour tout collectionneur 
                                    d'art africain.`;

                const container = document.getElementById('productDetailContainer');
                container.innerHTML = `
                    <div class="product-detail">
                        <div class="product-detail-grid">
                            ${imageSection}
                            <div class="product-detail-info">
                                <div class="product-detail-title">${title}</div>
                                <div class="product-detail-artist" onclick="viewArtistDetail(event, '${artist}')">
                                    👨‍🎨 par ${artist}
                                </div>
                                <div class="product-detail-price">${formatPrice(product.price)}</div>
                            
                            <div class="product-detail-meta">
                                <div class="meta-item">
                                    <div class="meta-label">Catégorie</div>
                                    <div class="meta-value">${category}</div>
                                </div>
                                <div class="meta-item">
                                    <div class="meta-label">Statut</div>
                                    <div class="meta-value">${product.badge || 'Disponible'}</div>
                                </div>
                                <div class="meta-item">
                                    <div class="meta-label">Dimensions</div>
                                    <div class="meta-value">${dimensionsText}</div>
                                </div>
                                <div class="meta-item">
                                    <div class="meta-label">Technique</div>
                                    <div class="meta-value">${techniqueText}</div>
                                </div>
                            </div>

                            <div class="product-detail-description">
                                <h3 style="margin-bottom:10px;">Description</h3>
                                <p>${description}</p>
                            </div>

                            <div class="product-detail-actions">
                                <button class="btn-large btn-primary" onclick="addToCartFromDetail(${product.id})">
                                    🛒 Ajouter au panier
                                </button>
                                <button class="btn-large btn-secondary" onclick="toggleFavorite(event, ${product.id})">
                                    ${favorites.includes(product.id) ? '❤️ Dans les favoris' : '🤍 Ajouter aux favoris'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Stocker les photos pour la navigation
            window.currentProductPhotos = photos;
            window.currentPhotoIndex = 0;

            navigateTo('productDetail');
            hideLoading();
            } 400);
        }

        function addToCartFromDetail(productId) {
            const btn = document.getElementById('detail-cart-btn') || document.querySelector('.detail-add-cart-btn');
            const fakeEvent = { stopPropagation: () => {} currentTarget: btn, target: btn };
            addToCart(fakeEvent, productId);
        }

        // ==================== CARROUSEL DE PHOTOS ====================
        function changeMainPhoto(index) {
            if (!window.currentProductPhotos || index < 0 || index >= window.currentProductPhotos.length) return;
            
            window.currentPhotoIndex = index;
            const mainImage = document.getElementById('mainProductImage');
            const indexDisplay = document.getElementById('currentPhotoIndex');
            
            if (mainImage) {
                mainImage.style.opacity = '0';
                setTimeout(() => {
                    mainImage.src = window.currentProductPhotos[index];
                    mainImage.onclick = () => openImageLightbox(window.currentProductPhotos[index]);
                    mainImage.style.opacity = '1';
                } 150);
            }
            
            if (indexDisplay) {
                indexDisplay.textContent = index + 1;
            }
            
            // Mettre à jour les bordures des miniatures
            document.querySelectorAll('.thumbnail-photo').forEach((thumb, i) => {
                thumb.style.border = i === index 
                    ? '3px solid rgba(212, 165, 116, 0.8)' 
                    : '3px solid rgba(255,255,255,0.3)';
            });
        }

        function nextPhoto() {
            if (!window.currentProductPhotos) return;
            const nextIndex = (window.currentPhotoIndex + 1) % window.currentProductPhotos.length;
            changeMainPhoto(nextIndex);
        }

        function previousPhoto() {
            if (!window.currentProductPhotos) return;
            const prevIndex = (window.currentPhotoIndex - 1 + window.currentProductPhotos.length) % window.currentProductPhotos.length;
            changeMainPhoto(prevIndex);
        }

        // ==================== NAVIGATION JUMIA (jm) ====================
        function jmGoTo(index) {
            if (!window.currentProductPhotos || index < 0 || index >= window.currentProductPhotos.length) return;
            window.currentPhotoIndex = index;
            const mainImg = document.getElementById('mainProductImage');
            if (mainImg) {
                mainImg.style.opacity = '0';
                setTimeout(() => {
                    mainImg.src = window.currentProductPhotos[index];
                    mainImg.style.opacity = '1';
                } 150);
            }
            // Mettre à jour le compteur
            const counter = document.getElementById('jmCurrent');
            if (counter) counter.textContent = index + 1;
            // Mettre à jour les miniatures
            document.querySelectorAll('.jm-thumb').forEach((t, i) => {
                t.classList.toggle('active', i === index);
            });
        }

        function jmNext() {
            if (!window.currentProductPhotos) return;
            jmGoTo((window.currentPhotoIndex + 1) % window.currentProductPhotos.length);
        }

        function jmPrev() {
            if (!window.currentProductPhotos) return;
            jmGoTo((window.currentPhotoIndex - 1 + window.currentProductPhotos.length) % window.currentProductPhotos.length);
        }

        // Navigation clavier pour le carrousel
        document.addEventListener('keydown', (e) => {
            const isProductDetailPage = document.getElementById('productDetailPage').classList.contains('active');
            if (!isProductDetailPage || !window.currentProductPhotos || window.currentProductPhotos.length <= 1) return;
            
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                previousPhoto();
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                nextPhoto();
            }
        });

        // ==================== ARTIST DETAIL ====================
        // Load artists data from storage or use defaults
        async function viewArtistDetail(event, artistName) {
            if (event) event.stopPropagation();
            if (!artistName || artistName === 'Artiste inconnu') return;

            const container = document.getElementById('artistDetailContainer');
            if (!container) return;

            // Afficher un loader pendant le chargement
            container.innerHTML = `
                <div style="text-align:center; padding:80px 20px; opacity:0.7;">
                    <div style="font-size:50px; margin-bottom:20px; animation: floatEmoji 1.5s ease-in-out infinite;">⏳</div>
                    <p style="font-size:18px;">Chargement du profil...</p>
                </div>
            `;
            navigateTo('artistDetail');

            // Récupérer les œuvres : depuis l'API et depuis getProducts()
            let artistWorks = [];
            try {
                const response = fetch(`https://arkyl-galerie.onrender.com/api_galerie_publique.php?t=${Date.now()}`);
                const result = await response.json();
                if (result.success && result.data) {
                    artistWorks = result.data.filter(a =>
                        a.artist_name && a.artist_name.trim().toLowerCase() === artistName.trim().toLowerCase()
                    );
                }
            } catch(e) {}

            // Compléter avec les produits locaux
            const localWorks = getProducts().filter(p => p.artist && p.artist.toLowerCase() === artistName.toLowerCase());
            localWorks.forEach(p => {
                if (!artistWorks.find(o => String(o.id) === String(p.id))) {
                    artistWorks.push({ id: p.id, title: p.title, artist_name: p.artist, price: p.price, image_url: p.image, badge: p.badge, category: p.category });
                }
            });

            // ⭐ Récupérer le profil artiste depuis l'API (avatar, bio, spécialité)
            let serverArtistProfile = null;
            try {
                const profileResp = fetch(`https://arkyl-galerie.onrender.com/api_modifier_profil.php?artist_name=${encodeURIComponent(artistName)}&t=${Date.now()}`);
                if (profileResp.ok) {
                    const profileResult = await profileResp.json();
                    if (profileResult.success && profileResult.artist) {
                        serverArtistProfile = profileResult.artist;
                    }
                }
            } catch(e) {}

            // Récupérer les données artiste depuis artistsData local (fallback)
            let artist = artistsData[artistName] || null;
            
            // Fusionner avec le profil serveur si disponible
            if (serverArtistProfile) {
                const serverAvatar = serverArtistProfile.avatar;
                const isImageUrl = serverAvatar && (serverAvatar.startsWith('data:') || serverAvatar.startsWith('http'));
                artist = {
                    profileImage: isImageUrl ? serverAvatar : (artist ? artist.profileImage : null),
                    avatar: isImageUrl ? null : (serverAvatar || (artist ? artist.avatar : '👨🏿‍🎨')),
                    specialty: serverArtistProfile.specialty || (artist ? artist.specialty : null) || (artistWorks[0] ? artistWorks[0].category : 'Artiste'),
                    bio: serverArtistProfile.bio || (artist ? artist.bio : null) || `Artiste spécialisé en ${artistWorks[0] ? artistWorks[0].category : 'art'}`,
                    country: serverArtistProfile.country || (artist ? artist.country : null),
                    website: serverArtistProfile.website || (artist ? artist.website : null),
                    followers: artist ? artist.followers : 0,
                    works: artistWorks.length,
                    rating: artist ? artist.rating : 0,
                };
            } else if (!artist && artistWorks.length > 0) {
                // Fallback : profil basique depuis les œuvres
                artist = {
                    avatar: '👨🏿‍🎨',
                    specialty: artistWorks[0].category || 'Artiste',
                    bio: `Artiste talentueux spécialisé en ${artistWorks[0].category || 'art'}`,
                    followers: 0,
                    works: artistWorks.length,
                    rating: 0,
                    profileImage: null
                };
            }

            // Construire l'avatar
            const isOwnProfile = currentUser && currentUser.isArtist && currentUser.artistName === artistName;
            const avatarStyle = (serverArtistProfile && serverArtistProfile.avatar_style) || 'slices';
            const avatarDisplay = (artist && artist.profileImage)
                ? buildAvatarDisplay(artist.profileImage, avatarStyle, artistName)
                : `<div class="artist-detail-avatar">${artist ? (artist.avatar || '👤') : '👤'}</div>`;

            // Construire les cartes d'œuvres
            const worksHTML = artistWorks.length > 0
                ? `<div class="products-grid">
                    ${artistWorks.map(art => `
                        <div class="product-card" onclick="viewProductDetail(${art.id})">
                            <div class="product-image">
                                <span class="product-badge">${art.badge || art.category || '🎨'}</span>
                                <button class="like-button" onclick="toggleFavorite(event, ${art.id})">
                                    ${(typeof favorites !== 'undefined' && favorites.includes(art.id)) ? '❤️' : '🤍'}
                                </button>
                                <img src="${art.image_url || art.image || ''}" alt="${art.title}"
                                     style="width:100%;height:100%;object-fit:contain;background:rgba(0,0,0,0.2);border-radius:20px;" loading="lazy"
                                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22400%22%3E%3Crect fill=%22%23ddd%22 width=%22400%22 height=%22400%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2248%22%3E🎨%3C/text%3E%3C/svg%3E'">
                            </div>
                            <div class="product-info">
                                <div class="product-title">${art.title}</div>
                                <div class="product-artist">par ${art.artist_name || artistName}</div>
                                <div class="product-footer">
                                    <div class="product-price">${formatPrice(art.price || 0)}</div>
                                    <button class="add-cart-btn" onclick="addToCart(event, ${art.id})">+ Panier</button>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                   </div>`
                : `<div style="text-align:center;padding:50px;background:rgba(255,255,255,0.08);border-radius:20px;border:2px dashed rgba(255,255,255,0.2);">
                       <div style="font-size:60px;margin-bottom:15px;">🎨</div>
                       <p style="font-size:18px;font-weight:600;margin-bottom:8px;">Aucune œuvre disponible</p>
                       <p style="opacity:0.7;">Cet artiste n'a pas encore publié d'œuvres.</p>
                   </div>`;

            container.innerHTML = `
                <div class="artist-detail">
                    <div class="artist-detail-header">
                        ${avatarDisplay}
                        <h1 class="artist-detail-name">${artistName}</h1>
                        <p class="artist-detail-specialty">${artist ? (artist.specialty || 'Artiste') : 'Artiste'}</p>
                        <div style="display:flex;gap:10px;justify-content:center;margin-top:15px;">
                            ${!isOwnProfile ? `<button class="btn follow-artist-btn" id="followBtn-${artistName.replace(/\s+/g, '-')}" onclick="toggleFollowArtist('${artistName}')" ${isFollowingArtist(artistName) ? 'data-following="true"' : ''}>
                                ${isFollowingArtist(artistName) ? '✓ Abonné' : '+ Suivre'}
                            </button>` : ''}
                            ${isOwnProfile ? `<button class="btn-edit-artist-profile" onclick="openArtistEditModal()">✏️ Modifier mon profil</button>` : ''}
                        </div>
                    </div>

                    <div class="artist-stats">
                        <div class="artist-stat">
                            <div class="artist-stat-value">${artistWorks.length}</div>
                            <div class="artist-stat-label">Œuvres</div>
                        </div>
                        <div class="artist-stat">
                            <div class="artist-stat-value">${artist ? (artist.followers || 0).toLocaleString() : '0'}</div>
                            <div class="artist-stat-label">Abonnés</div>
                        </div>
                        <div class="artist-stat">
                            <div class="artist-stat-value">${artist ? (artist.rating || '—') : '—'} ${artist && artist.rating ? '⭐' : ''}</div>
                            <div class="artist-stat-label">Note</div>
                        </div>
                    </div>

                    ${artist && artist.bio ? `
                    <div class="artist-bio">
                        <h3 style="margin-bottom:15px;">📖 Biographie</h3>
                        <p>${artist.bio}</p>
                    </div>` : ''}

                    <div class="artist-artworks-section">
                        <h2 class="section-heading">🎨 Œuvres de ${artistName} (${artistWorks.length})</h2>
                        ${worksHTML}
                    </div>
                </div>
            `;
        }

        // ==================== ARTISTS FOLLOWING SYSTEM ====================
        function getFollowedArtists() {
            return safeStorage.get('arkyl_followed_artists', []);
        }

        function saveFollowedArtists(artists) {
            safeStorage.set('arkyl_followed_artists', artists);
        }

        function isFollowingArtist(artistName) {
            const followed = getFollowedArtists();
            return followed.some(a => a.name === artistName);
        }

        // Fonction toggle pour suivre/ne plus suivre
        async function toggleFollowArtist(artistName) {
            if (isFollowingArtist(artistName)) {
                unfollowArtistWithAnimation(artistName);
            } else {
                followArtist(artistName);
            }
        }

        async function unfollowArtistWithAnimation(artistName) {
            const followed = getFollowedArtists();
            
            // Trouver le bouton et ajouter l'animation
            const btnId = 'followBtn-' + artistName.replace(/\s+/g, '-');
            const btn = document.getElementById(btnId);
            
            if (btn) {
                // Animation de chargement
                btn.disabled = true;
                btn.style.transform = 'scale(0.95)';
                btn.innerHTML = '⏳ Désabonnement...';
            }

            // Petit délai pour l'animation
            await new Promise(resolve => setTimeout(resolve, 300));

            const filtered = followed.filter(a => a.name !== artistName);
            saveFollowedArtists(filtered);

            // Animation de succès
            if (btn) {
                btn.innerHTML = '+ Suivre';
                btn.removeAttribute('data-following');
                btn.style.transform = '';
                btn.disabled = false;
            }

            showToast(`❌ Vous ne suivez plus ${artistName}`);
            addNotification('Désabonnement', `Vous ne suivez plus ${artistName}`);
            
            // Update UI
            updateFollowButton(artistName, false);
            
            // Refresh page if on My Artists page
            const currentPage = document.querySelector('.page.active');
            if (currentPage && currentPage.id === 'myArtistsPage') {
                renderMyArtistsPage();
            }
        }

        async function followArtist(artistId, artistName) {
    if (!currentUser) {
        showToast('⚠️ Veuillez vous connecter pour suivre un artiste');
        return;
    }

    try {
        // 1. On prévient le serveur
        const response = fetch('https://arkyl-galerie.onrender.com/api_toggle_follow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
            body: JSON.stringify({ 
                user_id: currentUser.id, 
                artist_id: artistId 
            })
        });

        const data = await response.json();

        if (data.success) {
            // 2. On met à jour la mémoire locale selon la réponse du serveur
            if (!currentUser.following) currentUser.following = [];

            if (data.action === 'followed') {
                const avatarImgElement = document.querySelector('.artist-header-avatar');
                const artistAvatar = avatarImgElement ? avatarImgElement.src : 'default-avatar.png';
                
                currentUser.following.push({
                    id: artistId,
                    name: artistName,
                    avatar: artistAvatar
                });
                showToast(`✅ Vous suivez maintenant ${artistName}`);
            } else if (data.action === 'unfollowed') {
                currentUser.following = currentUser.following.filter(f => f.id !== artistId);
                showToast(`ℹ️ Vous ne suivez plus ${artistName}`);
            }

            safeStorage.set('arkyl_current_user', currentUser);
            
            // (Optionnel) Ici tu peux ajouter une ligne pour changer le texte du bouton de "Suivre" à "Abonné"
            
        } else {
            showToast('❌ Erreur serveur : ' + data.message);
        }
    } catch (error) {
        console.error("Erreur d'abonnement :", error);
        showToast('❌ Erreur de connexion au serveur');
    }
}

// ==========================================
// SERVICE D'UPLOAD D'IMAGES EXTERNE (CLOUDINARY)
// ==========================================

// ==========================================
// SERVICE D'UPLOAD D'IMAGES EXTERNE (CLOUDINARY)
// ==========================================
async function uploadImageToCloudinary(file) {
    const formData = new FormData();
    formData.append('file', file);
    
    // 👇 INFOS CLOUDINARY CONFIGURÉES 👇
    formData.append('upload_preset', 'arkyl_preset'); // Preset Unsigned
    const cloudName = 'ddah64j2a'; // Ton Cloud Name réel

    try {
        const response = fetch(`https://api.cloudinary.com/v1_1/${cloudName}/image/upload`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        if (data.secure_url) {
            return data.secure_url; 
        } else {
            throw new Error('Erreur Cloudinary');
        }
    } catch (error) {
        console.error("Erreur d'upload :", error);
        throw error;
    }
}


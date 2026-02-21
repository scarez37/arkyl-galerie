
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
                    }, 500);
                }
            }
            
            setTimeout(() => {
                startAutoRefresh();
                showUpdateIndicator();
            }, 2000);
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
        // Store en mémoire — aucune persistence localStorage
        const _memStore = {};
        const safeStorage = {
            get: (key, defaultValue = null) => {
                return (key in _memStore) ? _memStore[key] : defaultValue;
            },
            set: (key, value) => {
                _memStore[key] = value;
                return true;
            },
            remove: (key) => {
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
            { id: 1, title: 'Bienvenue!', text: 'Découvrez nos nouvelles œuvres d\'art', time: 'Il y a 2h', unread: true },
            { id: 2, title: 'Promotion', text: '-20% sur toutes les sculptures cette semaine', time: 'Il y a 5h', unread: true },
            { id: 3, title: 'Nouvel artiste', text: 'Kofi Mensah a ajouté de nouvelles peintures', time: 'Hier', unread: false }
        ]);

        const sampleProducts = [];

        // Cache des données
        let appData = {
            artworks: [],
            artists: {},
            news: [],
            orders: [],
            users: [],
            interactions: { likes: [], comments: [] },
            settings: {},
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
                }, 2000);
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
                setTimeout(() => { goToAdmin(); }, 1500);
            } else {
                showToast(successMessage);
            }
        }

        // Charge le panier depuis la BDD et enrichit avec les données produits
        async function chargerPanierUtilisateur(userId) {
            if (!userId) return;
            try {
                const response = await fetch(
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
                console.log('✅ Déconnexion Google effectuée');
            }
            
            // Déconnecter le compte utilisateur
            currentUser = null;
            safeStorage.remove('arkyl_current_user');

            // Vider le panier et l'adresse en mémoire au logout (localStorage conservé par compte)
            cartItems = [];
            clientAddress = null;
            posteVille = '';
            transportCompagnie = '';
            mainPropreLieu = '';
            updateBadges();
            
            // Déconnecter le compte artiste (si existant)
            if (hasArtistAccount) {
                safeStorage.remove('arkyl_artist_account'); delete _memStore['arkyl_artist_account'];
                console.log('✅ Compte artiste déconnecté');
            }
            
            // Retour au mode client si on était en mode artiste
            const artistSpace = document.getElementById('artistSpace');
            if (artistSpace && artistSpace.style.display !== 'none') {
                switchToClientMode();
            }
            
            updateAuthUI();
            showToast('👋 Déconnecté avec succès - À bientôt!');
            
            // Hide user menu
            document.getElementById('userMenuDropdown').classList.remove('show');
            
            // Navigate to home page
            navigateTo('home');
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
                        <button class="admin-btn-delete" onclick="deleteArtwork(${product.id})">🗑️ Supprimer</button>
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
                        ${artistData.avatar ? 
                            `<img loading="lazy" src="${artistData.avatar}" alt="${name}" class="top-artist-avatar">` :
                            `<div class="top-artist-avatar" style="display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.1); font-size: 24px;">👤</div>`
                        }
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
                        <button class="admin-btn-delete" onclick="deleteArtwork(${product.id})">🗑️ Supprimer</button>
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
            document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
            document.getElementById(page + 'Page').classList.add('active');
            
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
                await saveAppData();
                
            } catch (error) {
                console.error('❌ Erreur lors du like:', error);
            }
        }

        // Charger les likes de l'utilisateur
        async function loadUserLikes() {
            if (!currentUser) return;
            
            try {
                const response = await fetch(`api_interactions.php?action=get_user_likes&user_email=${encodeURIComponent(currentUser.email)}`);
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
                { tx: '-80px', ty: '-8px'  },
                { tx: '-60px', ty: '-25px' },
                { tx: '-35px', ty: '-38px' },
                { tx: '0px',   ty: '-45px' },
                { tx: '35px',  ty: '-38px' },
                { tx: '60px',  ty: '-25px' },
                { tx: '80px',  ty: '-8px'  },
                { tx: '80px',  ty: '18px'  },
                { tx: '55px',  ty: '35px'  },
                { tx: '20px',  ty: '45px'  },
                { tx: '-20px', ty: '45px'  },
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
                }, 500);
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
                    await chargerLaVraieGalerie();
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
                const response = await fetch('https://arkyl-galerie.onrender.com/api_ajouter_favoris.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
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
            }, 400);
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
                setTimeout(() => navigateTo('login'), 800);
                return;
            }

            const btn = event.currentTarget || event.target.closest('button') || event.target;
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '⏳';
            btn.disabled = true;

            try {
                const response = await fetch('https://arkyl-galerie.onrender.com/api_ajouter_panier.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
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
                setTimeout(() => { btn.innerHTML = originalHTML; btn.disabled = false; }, 700);
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
        function renderFavorites() {
            const container = document.getElementById('favoritesContainer');
            const emptyState = document.getElementById('emptyFavorites');
            const allProducts = getProducts();
            const favoriteProducts = allProducts.filter(p => favorites.includes(p.id));
            
            if (favoriteProducts.length === 0) {
                container.style.display = 'none';
                emptyState.style.display = 'block';
                return;
            }
            
            container.style.display = 'grid';
            emptyState.style.display = 'none';
            
            container.innerHTML = favoriteProducts.map(product => `
                <div class="product-card">
                    <div class="product-image">
                        <span class="product-badge">${product.badge}</span>
                        <button class="like-button" onclick="toggleFavorite(event, ${product.id})">❤️</button>
                        <div class="product-emoji">${product.emoji}</div>
                    </div>
                    <div class="product-info">
                        <div class="product-title">${product.title}</div>
                        <div class="product-artist">par ${product.artist}</div>
                        <div class="product-footer">
                            <div class="product-price">${formatPrice(product.price)}</div>
                            <button class="add-cart-btn" onclick="addToCart(event, ${product.id})">+ Panier</button>
                        </div>
                    </div>
                </div>
            `).join('');
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
                    ? `<img loading="lazy" src="${item.image}" alt="${item.title}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`
                    : '';
                const emojiHtml = `<span style="${item.image ? 'display:none;' : ''}align-items:center;justify-content:center;width:100%;height:100%;font-size:48px;display:flex;">${item.emoji || '🎨'}</span>`;
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
                            <button id="checkout-btn" class="checkout-btn" onclick="validerPaiementStripe()">COMMANDER — ${formatPrice(total)}</button>
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
                                <div class="payment-methods">
                                    <div class="payment-method selected"><div class="payment-icon">📱</div><div class="payment-name">MTN Money</div></div>
                                    <div class="payment-method"><div class="payment-icon">🌊</div><div class="payment-name">Wave</div></div>
                                    <div class="payment-method"><div class="payment-icon">🟠</div><div class="payment-name">Orange Money</div></div>
                                    <div class="payment-method"><div class="payment-icon">🟣</div><div class="payment-name">Moov Money</div></div>
                                </div>
                            </div>
                            <div class="security-badge">🔒 Paiement sécurisé — Tiers de Confiance ARKYL</div>
                        </div>
                    </div>
                </div>
            `;

            document.querySelectorAll('.payment-method').forEach(m => {
                m.addEventListener('click', function() {
                    document.querySelectorAll('.payment-method').forEach(x => x.classList.remove('selected'));
                    this.classList.add('selected');
                });
            });
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
            { nom: 'Océan',          desc: 'Océan Transport — réseau Abidjan & intérieur' },
            { nom: 'SBTA',           desc: 'Société de Bus et Transport en Afrique — national' },
            { nom: 'CTE',            desc: 'Compagnie de Transport de l\'Est — Abengourou & Est CI' },
            { nom: 'AT',             desc: 'Abidjan Transit — liaisons urbaines & périurbaines' },
            { nom: 'UTB',            desc: 'Union des Transports de Bouaké — réseau national' },
            { nom: 'STIF',           desc: 'Société de Transport Interurbain — Daloa & national' },
            { nom: 'STGB',           desc: 'Société de Transport Grand Bassam' },
            { nom: 'Sans Frontières',desc: 'Liaisons CI, Burkina Faso, Mali' },
            { nom: 'MST',            desc: 'Mon Service Transport — Abidjan & intérieur' },
            { nom: 'KTC',            desc: 'Korhogo Transport & Compagnie — Nord CI' },
            { nom: 'Trans Ivoir',    desc: 'Liaisons interurbaines Centre-Ouest' },
            { nom: 'Bouaké Express', desc: 'Bouaké ↔ Abidjan express' },
            { nom: 'SOTRA',          desc: 'Transport urbain Abidjan & grandes villes' },
            { nom: 'Autre',          desc: 'Autre compagnie / taxi-brousse' },
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
                    headers: { 'Content-Type': 'application/json' },
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
            }, 3000);
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
                    headers: { 'Content-Type': 'application/json' },
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
                setTimeout(() => navigateTo('login'), 800);
                return;
            }

            try {
                const response = await fetch('api_stripe_checkout.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: userId })
                });

                const data = await response.json();

                if (data.success && data.url) {
                    window.location.href = data.url;
                } else {
                    alert('❌ Erreur renvoyée par le serveur : ' + data.message);
                    btn.disabled = false;
                    btn.innerHTML = '🛒 Finaliser la commande';
                }
            } catch (error) {
                alert('❌ Impossible de contacter le serveur PHP.');
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
        function renderOrders() {
            const container = document.getElementById('ordersContainer');
            
            if (orderHistory.length === 0) {
                container.innerHTML = `
                    <div class="empty-orders">
                        <div class="empty-orders-icon">📦</div>
                        <div class="empty-orders-text">Aucune commande pour le moment</div>
                        <div class="empty-orders-subtext">Vos commandes apparaîtront ici une fois validées</div>
                        <button class="btn" onclick="navigateTo('home')" style="background:linear-gradient(135deg,var(--terre-cuite),var(--terre-sombre));border:none;color:white;padding:14px 28px;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;transition:all 0.3s;">
                            🛍️ Découvrir la galerie
                        </button>
                    </div>
                `;
                return;
            }

            // Calculate statistics
            const totalOrders = orderHistory.length;
            const totalSpent = orderHistory.reduce((sum, order) => sum + order.total, 0);
            const totalItems = orderHistory.reduce((sum, order) => sum + order.items.reduce((s, item) => s + item.quantity, 0), 0);

            const statsHtml = `
                <div class="orders-stats">
                    <div class="orders-stat-card">
                        <div class="orders-stat-icon">📦</div>
                        <div class="orders-stat-value">${totalOrders}</div>
                        <div class="orders-stat-label">Commandes</div>
                    </div>
                    <div class="orders-stat-card">
                        <div class="orders-stat-icon">💰</div>
                        <div class="orders-stat-value">${formatPrice(totalSpent)}</div>
                        <div class="orders-stat-label">Total dépensé</div>
                    </div>
                    <div class="orders-stat-card">
                        <div class="orders-stat-icon">🎨</div>
                        <div class="orders-stat-value">${totalItems}</div>
                        <div class="orders-stat-label">Œuvres achetées</div>
                    </div>
                </div>
            `;

            const ordersHtml = orderHistory.map(order => {
                const es = order.escrow_status || 'payée_en_attente';
                const steps = [
                    { key: 'payée_en_attente', icon: '💳', label: 'Payée' },
                    { key: 'expédiée',         icon: '🚚', label: 'Expédiée' },
                    { key: 'livrée_confirmée', icon: '📬', label: 'Reçue' },
                    { key: 'fonds_libérés',    icon: '💰', label: 'Payé' },
                ];
                const stepIndex = steps.findIndex(s => s.key === es);

                const escrowProgressHtml = `
                    <div class="escrow-progress">
                        ${steps.map((s, i) => `
                            ${i > 0 ? `<div class="escrow-connector ${i <= stepIndex ? 'done' : ''}"></div>` : ''}
                            <div class="escrow-step ${i < stepIndex ? 'done' : i === stepIndex ? 'active' : ''}">
                                <div class="escrow-step-dot">${i < stepIndex ? '✓' : s.icon}</div>
                                <div class="escrow-step-label">${s.label}</div>
                            </div>
                        `).join('')}
                    </div>
                `;

                const escrowDescMap = {
                    'payée_en_attente': { cls: 'pending',   icon: '🔒', title: 'Fonds sécurisés — en attente d\'expédition', desc: 'Votre paiement est bloqué sur le compte ARKYL. Il sera libéré à l\'artiste uniquement après confirmation de votre réception.' },
                    'expédiée':         { cls: 'shipped',   icon: '🚚', title: 'Colis en route — en attente de réception',    desc: 'L\'artiste a expédié votre œuvre. Confirmez la réception dès qu\'elle arrive pour libérer les fonds.' },
                    'livrée_confirmée': { cls: 'confirmed', icon: '📬', title: 'Réception confirmée — libération en cours',   desc: 'Vous avez confirmé la réception. Les fonds sont en cours de transfert vers l\'artiste.' },
                    'fonds_libérés':    { cls: 'released',  icon: '✅', title: 'Transaction complète — artiste payé',         desc: 'Les fonds ont été transférés à l\'artiste. Merci pour votre confiance !' },
                };
                const ed = escrowDescMap[es] || escrowDescMap['payée_en_attente'];

                // Countdown déblocage auto (21 jours)
                let countdownHtml = '';
                if (es === 'expédiée' && order.escrow_auto_release_date) {
                    const daysLeft = Math.max(0, Math.ceil((new Date(order.escrow_auto_release_date) - Date.now()) / (1000 * 60 * 60 * 24)));
                    countdownHtml = `<div class="escrow-countdown">⏱ Libération automatique dans ${daysLeft} jour${daysLeft > 1 ? 's' : ''} si aucun litige</div>`;
                }

                // Bouton confirmation réception
                const confirmBtnHtml = es === 'expédiée' ? `
                    <button class="confirm-reception-btn" onclick="confirmReception('${order.id}')">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                        J'ai bien reçu mon œuvre — Libérer les fonds
                    </button>
                ` : '';

                // Infos tracking
                const trackingHtml = order.tracking_number ? `
                    <div style="background:rgba(33,150,243,0.1); border:1px solid rgba(33,150,243,0.3); border-radius:10px; padding:10px 14px; margin-top:10px; font-size:13px;">
                        📦 N° de suivi : <strong>${order.tracking_number}</strong>
                        ${order.shipping_proof_url ? ` · <a href="${order.shipping_proof_url}" target="_blank" style="color:#90caf9;">Voir preuve d'expédition</a>` : ''}
                    </div>
                ` : '';

                return `
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <div class="order-id">Commande ${order.order_number || '#' + order.id}</div>
                            <div class="order-date">📅 ${order.date}</div>
                        </div>
                        <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px;">
                            <span class="order-status">${order.status}</span>
                            <span class="${es === 'fonds_libérés' ? 'funds-released-badge' : 'funds-locked-badge'}">
                                ${es === 'fonds_libérés' ? '✅ Fonds libérés' : '🔒 Fonds sécurisés'}
                            </span>
                        </div>
                    </div>

                    <!-- Progression Tiers de Confiance -->
                    ${escrowProgressHtml}

                    <div class="escrow-status-bar ${ed.cls}">
                        <div class="escrow-status-title">${ed.icon} ${ed.title}</div>
                        <div class="escrow-status-desc">${ed.desc}</div>
                        ${countdownHtml}
                    </div>

                    ${trackingHtml}
                    ${confirmBtnHtml}
                    
                    <div class="order-items-list">
                        ${order.items.map(item => `
                            <div class="order-item-row">
                                ${getOrderItemImageHtml(item)}
                                <div class="order-item-name">${item.title}</div>
                                <div class="order-item-qty">x${item.quantity}</div>
                                <div class="order-item-price">${formatPrice(item.price * item.quantity)}</div>
                            </div>
                        `).join('')}
                    </div>

                    <div class="order-summary">
                        <div class="order-summary-row">
                            <span>Sous-total</span>
                            <span>${formatPrice(order.subtotal)}</span>
                        </div>
                        <div class="order-summary-row">
                            <span>TVA (18%)</span>
                            <span>${formatPrice(order.tax)}</span>
                        </div>
                        <div class="order-summary-row">
                            <span>Livraison (${order.shippingName})</span>
                            <span>${formatPrice(order.shipping)}</span>
                        </div>
                        <div class="order-summary-row">
                            <span>Paiement</span>
                            <span>${order.paymentMethod}</span>
                        </div>
                        <div class="order-summary-row total">
                            <span>Total</span>
                            <span>${formatPrice(order.total)}</span>
                        </div>
                    </div>
                </div>
            `}).join('');

            container.innerHTML = `
                <div class="orders-container">
                    ${statsHtml}
                    ${ordersHtml}
                </div>
            `;
        }

        // ==================== ADMIN ORDERS ====================
        let currentAdminFilter = 'all';

        function renderAdminOrders() {
            const container = document.getElementById('adminOrdersContainer');
            const allOrders = safeStorage.get('arkyl_orders', []);
            
            if (allOrders.length === 0) {
                container.innerHTML = `
                    <div style="text-align:center; padding:60px 20px;">
                        <div style="font-size:80px; margin-bottom:20px;">🛒</div>
                        <div style="font-size:22px; font-weight:700; margin-bottom:10px;">Aucune commande reçue</div>
                        <div style="font-size:14px; opacity:0.6;">Les commandes clients apparaîtront ici</div>
                    </div>
                `;
                updateAdminOrderCounts(allOrders);
                return;
            }

            // Filter orders
            const filteredOrders = currentAdminFilter === 'all' 
                ? allOrders 
                : allOrders.filter(order => order.status === currentAdminFilter);

            // Update counts
            updateAdminOrderCounts(allOrders);

            // Render orders
            const ordersHtml = filteredOrders.map(order => {
                const statusClass = order.status === 'En préparation' ? 'pending' : 
                                   order.status === 'Expédiée' ? 'shipped' : 'delivered';
                
                return `
                    <div class="admin-order-card">
                        <div style="display:flex; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:15px;">
                            <div>
                                <div style="font-size:18px; font-weight:700; color:var(--ocre); margin-bottom:5px;">📦 Commande #${order.id}</div>
                                <div style="font-size:14px; opacity:0.8; margin-bottom:3px;">👤 ${order.user}${order.userEmail ? ` (${order.userEmail})` : ''}</div>
                                <div style="font-size:13px; opacity:0.6;">📅 ${order.date}</div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:22px; font-weight:800; color:var(--ocre); margin-bottom:10px;">${formatPrice(order.total)}</div>
                                <span style="display:inline-block; padding:6px 16px; border-radius:20px; font-size:12px; font-weight:600; text-transform:uppercase; background:${order.status === 'Livrée' ? 'linear-gradient(135deg, #4caf50, #388e3c)' : order.status === 'Expédiée' ? 'linear-gradient(135deg, #2196f3, #1976d2)' : 'linear-gradient(135deg, #ff9800, #f57c00)'}; color:white;">${order.status}</span>
                            </div>
                        </div>

                        <!-- Timeline de progression -->
                        <div class="order-timeline">
                            <div class="timeline-step completed">
                                <div class="timeline-icon">📝</div>
                                <div class="timeline-label">Commandée</div>
                                <div class="timeline-date">${order.date.split(',')[0]}</div>
                            </div>
                            <div class="timeline-connector ${order.status === 'Expédiée' || order.status === 'Livrée' ? 'active' : ''}"></div>
                            <div class="timeline-step ${order.status === 'Expédiée' || order.status === 'Livrée' ? 'completed' : order.status === 'En préparation' ? 'current' : ''}">
                                <div class="timeline-icon">🚚</div>
                                <div class="timeline-label">Expédiée</div>
                                <div class="timeline-date">${order.shippedDate ? order.shippedDate.split(',')[0] : '-'}</div>
                            </div>
                            <div class="timeline-connector ${order.status === 'Livrée' ? 'active' : ''}"></div>
                            <div class="timeline-step ${order.status === 'Livrée' ? 'completed' : order.status === 'Expédiée' ? 'current' : ''}">
                                <div class="timeline-icon">✅</div>
                                <div class="timeline-label">Livrée</div>
                                <div class="timeline-date">${order.deliveredDate ? order.deliveredDate.split(',')[0] : '-'}</div>
                            </div>
                        </div>
                        
                        <div style="background:rgba(0,0,0,0.2); border-radius:12px; padding:15px; margin-bottom:15px;">
                            ${order.items.map(item => `
                                <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid rgba(255,255,255,0.1);">
                                    <div style="font-weight:600;">${item.title}</div>
                                    <div style="color:var(--ocre); margin:0 15px;">x${item.quantity}</div>
                                    <div style="font-weight:700; min-width:100px; text-align:right;">${formatPrice(item.price * item.quantity)}</div>
                                </div>
                            `).join('')}
                        </div>

                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:15px;">
                            <div class="admin-detail-box">
                                <div class="admin-detail-label">Livraison</div>
                                <div class="admin-detail-value">${order.shippingName}</div>
                            </div>
                            <div class="admin-detail-box">
                                <div class="admin-detail-label">Paiement</div>
                                <div class="admin-detail-value">${order.paymentMethod}</div>
                            </div>
                            <div class="admin-detail-box">
                                <div class="admin-detail-label">Sous-total</div>
                                <div class="admin-detail-value">${formatPrice(order.subtotal)}</div>
                            </div>
                            <div class="admin-detail-box">
                                <div class="admin-detail-label">TVA (18%)</div>
                                <div class="admin-detail-value">${formatPrice(order.tax)}</div>
                            </div>
                            ${order.shippedDate ? `
                                <div class="admin-detail-box" style="grid-column: span 2; background: rgba(33, 150, 243, 0.2); border: 1px solid rgba(33, 150, 243, 0.4);">
                                    <div class="admin-detail-label">🚚 Date d'expédition</div>
                                    <div class="admin-detail-value">${order.shippedDate}</div>
                                </div>
                            ` : ''}
                            ${order.deliveredDate ? `
                                <div class="admin-detail-box" style="grid-column: span 2; background: rgba(76, 175, 80, 0.2); border: 1px solid rgba(76, 175, 80, 0.4);">
                                    <div class="admin-detail-label">✅ Date de livraison</div>
                                    <div class="admin-detail-value">${order.deliveredDate}</div>
                                </div>
                            ` : ''}
                        </div>

                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            ${order.status === 'En préparation' ? `
                                <!-- Zone expédition avec preuve -->
                                <div style="width:100%; background:rgba(33,150,243,0.08); border:1px solid rgba(33,150,243,0.25); border-radius:14px; padding:16px; margin-bottom:10px;">
                                    <div style="font-weight:700; margin-bottom:12px; font-size:14px;">🚚 Marquer comme expédiée</div>
                                    <input id="tracking-${order.id}" type="text" placeholder="N° de suivi (optionnel)" style="width:100%; padding:10px 14px; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); border-radius:10px; color:white; font-size:13px; margin-bottom:10px; box-sizing:border-box;">
                                    <div class="proof-upload-zone" onclick="document.getElementById('proof-${order.id}').click()" id="proof-zone-${order.id}">
                                        <input type="file" id="proof-${order.id}" accept="image/*,.pdf" style="display:none" onchange="document.getElementById('proof-zone-${order.id}').classList.add('has-file'); document.getElementById('proof-zone-${order.id}').innerHTML='✅ Fichier sélectionné : ' + this.files[0].name">
                                        <div style="font-size:24px; margin-bottom:6px;">📎</div>
                                        <div style="font-size:13px; font-weight:600;">Cliquer pour ajouter la photo du reçu d'expédition</div>
                                        <div style="font-size:11px; opacity:0.6; margin-top:4px;">JPG, PNG ou PDF · Fortement recommandé</div>
                                    </div>
                                    <button onclick="markAsShippedWithProof('${order.id}')" style="width:100%; margin-top:10px; padding:12px; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; background:linear-gradient(135deg, #2196f3, #1976d2); color:white; transition:all 0.3s;">
                                        🚚 Confirmer l'expédition & libérer l'attente
                                    </button>
                                </div>
                            ` : ''}
                            ${order.status === 'Expédiée' ? `
                                <div style="width:100%; background:rgba(76,175,80,0.08); border:1px solid rgba(76,175,80,0.25); border-radius:12px; padding:14px; margin-bottom:10px; font-size:13px;">
                                    🔒 <strong>Fonds sécurisés</strong> — En attente de confirmation de réception par l'acheteur.<br>
                                    <span style="opacity:0.7;">Libération automatique dans 21 jours si aucun litige.</span>
                                    ${order.tracking_number ? `<br>📦 Suivi : <strong>${order.tracking_number}</strong>` : ''}
                                </div>
                                <button onclick="changeOrderStatus('${order.id}', 'Livrée')" style="flex:1; min-width:150px; padding:12px 20px; border:none; border-radius:12px; font-size:14px; font-weight:600; cursor:pointer; background:linear-gradient(135deg, #4caf50, #388e3c); color:white; transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                                    ✅ Forcer confirmation livraison (admin)
                                </button>
                            ` : ''}
                            ${order.status === 'Livrée' ? `
                                <div style="width:100%; background:rgba(156,39,176,0.1); border:1px solid rgba(156,39,176,0.3); border-radius:12px; padding:14px; font-size:13px;">
                                    💰 <strong>Fonds libérés</strong> — L'artiste a été payé le ${order.escrow_released_at ? new Date(order.escrow_released_at).toLocaleDateString('fr-FR') : 'N/A'}.
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            }).join('');

            container.innerHTML = ordersHtml || `
                <div style="text-align:center; padding:60px 20px;">
                    <div style="font-size:80px; margin-bottom:20px;">🔍</div>
                    <div style="font-size:22px; font-weight:700; margin-bottom:10px;">Aucune commande avec ce statut</div>
                    <div style="font-size:14px; opacity:0.6;">Essayez un autre filtre</div>
                </div>
            `;
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
                }, 300);
            }
        }

        // ===== TIERS DE CONFIANCE — ACHETEUR =====
        function confirmReception(orderId) {
            const btn = event.target.closest('.confirm-reception-btn');
            if (btn) { btn.disabled = true; btn.textContent = '⏳ Confirmation en cours...'; }

            setTimeout(() => {
                const orders = safeStorage.get('arkyl_orders', []);
                const order = orders.find(o => o.id == orderId);
                if (!order) return;

                order.escrow_status    = 'livrée_confirmée';
                order.escrow_confirmed_at = new Date().toISOString();
                order.status = 'Livrée';

                // Libération automatique immédiate après confirmation
                setTimeout(() => {
                    order.escrow_status   = 'fonds_libérés';
                    order.escrow_released_at = new Date().toISOString();
                    safeStorage.set('arkyl_orders', orders);
                    orderHistory = orders;
                    renderOrders();
                    showToast('💰 Fonds libérés — l\'artiste a été payé !');
                    addNotification('Transaction complète', 'Les fonds ont été transférés à l\'artiste. Merci !');
                }, 2000);

                safeStorage.set('arkyl_orders', orders);
                orderHistory = orders;
                renderOrders();
                showToast('✅ Réception confirmée ! Libération des fonds en cours...');
                addNotification('Œuvre reçue', 'Merci d\'avoir confirmé la réception. Les fonds sont en cours de transfert.');
            }, 1200);
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
                reader.readAsDataURL(proofFile);
            } else {
                safeStorage.set('arkyl_orders', orders);
                orderHistory = orders;
                renderAdminOrders();
                showToast('🚚 Commande marquée expédiée !');
            }

            // Auto-libération après 21 jours (simulé ici via flag)
            order.escrow_auto_release_date = new Date(Date.now() + 21 * 24 * 60 * 60 * 1000).toISOString();
            safeStorage.set('arkyl_orders', orders);

            // Envoyer notification à l'acheteur
            sendStatusChangeNotifications(order, 'En préparation', 'Expédiée');
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
        function updateOrderStatus(orderId, newStatus) {
            const order = orderHistory.find(o => o.id === orderId);
            if (!order) return;
            
            const oldStatus = order.status;
            order.status = newStatus;
            safeStorage.set('arkyl_orders', orderHistory);
            
            // Icônes et messages selon le statut
            const statusMessages = {
                'En préparation': { icon: '📦', client: 'Votre commande est en cours de préparation', admin: 'Commande en préparation' },
                'Expédiée': { icon: '🚚', client: 'Votre commande a été expédiée !', admin: 'Commande expédiée' },
                'En livraison': { icon: '🛵', client: 'Votre commande est en cours de livraison', admin: 'Commande en livraison' },
                'Livrée': { icon: '✅', client: 'Votre commande a été livrée avec succès !', admin: 'Commande livrée' },
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
                },
                'Expédiée': {
                    icon: '🚚',
                    clientTitle: '🚚 Commande expédiée !',
                    clientMessage: 'Bonne nouvelle ! Votre commande a été expédiée et est en route vers vous',
                    adminTitle: '🚚 Commande expédiée avec succès',
                    adminMessage: 'La commande a été expédiée au client',
                    artistTitle: '🚚 Votre œuvre a été expédiée !',
                    artistMessage: 'Votre œuvre est en route vers le client'
                },
                'En livraison': {
                    icon: '🛵',
                    clientTitle: '🛵 Commande en cours de livraison',
                    clientMessage: 'Votre commande est actuellement en cours de livraison et arrivera bientôt',
                    adminTitle: '🛵 Commande en cours de livraison',
                    adminMessage: 'La commande est en livraison chez le client',
                    artistTitle: '🛵 Livraison en cours',
                    artistMessage: 'Votre œuvre est actuellement en cours de livraison'
                },
                'Livrée': {
                    icon: '✅',
                    clientTitle: '✅ Commande livrée !',
                    clientMessage: 'Votre commande a été livrée avec succès ! Merci pour votre confiance',
                    adminTitle: '✅ Livraison confirmée',
                    adminMessage: 'La commande a été livrée au client avec succès',
                    artistTitle: '✅ Œuvre livrée avec succès !',
                    artistMessage: 'Félicitations ! Votre œuvre a été livrée au client'
                },
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
                    },
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
        function toggleChat() {
            document.getElementById('chatWindow').classList.toggle('show');
        }

        function sendChatMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            if (!message) return;

            const messagesDiv = document.getElementById('chatMessages');
            
            const userMsg = document.createElement('div');
            userMsg.className = 'chat-message user';
            userMsg.textContent = message;
            messagesDiv.appendChild(userMsg);
            
            input.value = '';
            messagesDiv.scrollTop = messagesDiv.scrollHeight;

            setTimeout(() => {
                const botMsg = document.createElement('div');
                botMsg.className = 'chat-message bot';
                botMsg.textContent = getBotResponse(message);
                messagesDiv.appendChild(botMsg);
                messagesDiv.scrollTop = messagesDiv.scrollHeight;
            }, 1000);
        }

        function getBotResponse(message) {
            message = message.toLowerCase();
            if (message.includes('prix')) return "Nos œuvres varient de 67 000 à 150 000 FCFA. Parcourez notre catalogue! 💰";
            if (message.includes('livraison')) return "Livraison via SBTA, AT Transport, TSR, Ocean Delivery. Délais: 1-5 jours. 📦";
            if (message.includes('paiement')) return "Nous acceptons MTN Money, Wave, Orange Money et Moov Money. 100% sécurisé! 💳";
            return "Merci! Contactez-nous à arkyl.app@gmail.com pour plus d'infos. 😊";
        }

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
            }, 400);
        }

        function addToCartFromDetail(productId) {
            const btn = document.getElementById('detail-cart-btn') || document.querySelector('.detail-add-cart-btn');
            const fakeEvent = { stopPropagation: () => {}, currentTarget: btn, target: btn };
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
                }, 150);
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
                const response = await fetch(`api_galerie_publique.php?t=${Date.now()}`);
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

            // Récupérer les données artiste depuis artistsData local
            let artist = artistsData[artistName] || null;
            
            // Si pas de données artiste, créer un profil basique à partir des œuvres
            if (!artist && artistWorks.length > 0) {
                artist = {
                    avatar: '👨🏿‍🎨',
                    specialty: artistWorks[0].category || 'Artiste',
                    bio: `Artiste talentueux spécialisé en ${artistWorks[0].category || 'art'}`,
                    followers: 0,
                    works: artistWorks.length,
                    rating: 0,
                    profileImage: null // Sera peut-être rempli plus tard
                };
            }

            // Construire l'avatar
            const isOwnProfile = currentUser && currentUser.isArtist && currentUser.artistName === artistName;
            const avatarDisplay = (artist && artist.profileImage)
                ? `<img loading="lazy" src="${artist.profileImage}" style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:4px solid var(--terre-cuite);margin-bottom:20px;" alt="${artistName}" onerror="this.style.display='none'">`
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
                                     style="width:100%;height:100%;object-fit:cover;border-radius:20px;" loading="lazy"
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
                await unfollowArtistWithAnimation(artistName);
            } else {
                await followArtist(artistName);
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

        async function followArtist(artistName) {
            const followed = getFollowedArtists();
            
            // Check if already following
            if (isFollowingArtist(artistName)) {
                showToast(`ℹ️ Vous suivez déjà ${artistName}`);
                return;
            }

            // Trouver le bouton et ajouter l'animation
            const btnId = 'followBtn-' + artistName.replace(/\s+/g, '-');
            const btn = document.getElementById(btnId);
            
            if (btn) {
                // Animation de chargement
                btn.disabled = true;
                btn.style.transform = 'scale(0.95)';
                btn.innerHTML = '⏳ Abonnement...';
            }

            // Charger les œuvres depuis l'API et/ou local
            let allProducts = [];
            try {
                const response = await fetch('api_galerie_publique.php');
                const contentType = response.headers.get('content-type');
                
                if (response.ok && contentType && contentType.includes('application/json')) {
                    const result = await response.json();
                    if (result.success && result.data && result.data.length > 0) {
                        allProducts = result.data.map(art => ({
                            id: art.id,
                            title: art.title,
                            artist: art.artist_name,
                            category: art.category,
                            price: art.price,
                            image_url: art.image_url,
                            artistAvatar: art.artist_avatar || '👨🏿‍🎨'
                        }));
                    }
                }
            } catch (error) {
                console.log('⚠️ Erreur API, utilisation des données locales');
            }
            
            // Données locales si API indisponible
            if (allProducts.length === 0) {
                allProducts = getProducts();
            }

            // Comparaison robuste pour trouver les œuvres de l'artiste
            const artistProducts = allProducts.filter(p => 
                p.artist && artistName && 
                p.artist.trim().toLowerCase() === artistName.trim().toLowerCase()
            );
            
            if (artistProducts.length === 0) {
                showToast(`⚠️ Impossible de trouver des œuvres de ${artistName}`);
                console.log('Artiste recherché:', artistName);
                console.log('Artistes disponibles:', [...new Set(allProducts.map(p => p.artist))]);
                
                // Réinitialiser le bouton
                if (btn) {
                    btn.disabled = false;
                    btn.style.transform = 'scale(1)';
                    btn.innerHTML = '+ Suivre';
                }
                return;
            }

            // Récupérer les données de l'artiste depuis artistsData si disponible
            const artistData = artistsData[artistName] || {};

            // Chercher une photo de profil dans les œuvres si disponible
            let profileImageUrl = artistData.profileImage;
            if (!profileImageUrl && artistProducts.length > 0) {
                // Si l'œuvre a une image d'avatar de l'artiste, l'utiliser
                const artworkWithProfile = artistProducts.find(p => p.artist_profile_image);
                if (artworkWithProfile) {
                    profileImageUrl = artworkWithProfile.artist_profile_image;
                }
            }

            // Create artist profile
            const artistProfile = {
                name: artistName,
                avatar: artistData.avatar || artistProducts[0].artistAvatar || '👨🏿‍🎨',
                specialty: artistData.specialty || artistProducts[0].category || 'Artiste',
                artworksCount: artistProducts.length,
                followedAt: new Date().toISOString(),
                bio: artistData.bio || `Artiste talentueux spécialisé en ${artistProducts[0].category}`,
                profile_image: profileImageUrl
            };

            followed.push(artistProfile);
            saveFollowedArtists(followed);

            // Animation de succès
            if (btn) {
                btn.innerHTML = '✓ Abonné';
                btn.setAttribute('data-following', 'true');
                btn.style.transform = 'scale(1.1)';
                
                // Animation de confettis
                createConfetti(btn);
                
                setTimeout(() => {
                    btn.style.transform = '';
                    btn.disabled = false;
                }, 300);
            }

            showToast(`✅ Vous suivez maintenant ${artistName}!`);
            addNotification('Nouvel abonnement', `Vous suivez maintenant ${artistName}`);
            
            // Update UI if on artist detail page
            updateFollowButton(artistName, true);
        }

        function unfollowArtist(artistName) {
            const followed = getFollowedArtists();
            const filtered = followed.filter(a => a.name !== artistName);
            saveFollowedArtists(filtered);

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

        function updateFollowButton(artistName, isFollowing) {
            // Find all follow buttons for this artist and update them
            const buttons = document.querySelectorAll(`[data-artist="${artistName}"]`);
            buttons.forEach(btn => {
                if (isFollowing) {
                    btn.textContent = '✓ Abonné';
                    btn.style.background = 'rgba(76, 175, 80, 0.2)';
                    btn.style.borderColor = '#4CAF50';
                    btn.onclick = () => unfollowArtist(artistName);
                } else {
                    btn.textContent = '+ Suivre';
                    btn.style.background = 'linear-gradient(135deg,var(--terre-cuite),var(--terre-sombre))';
                    btn.style.borderColor = 'transparent';
                    btn.onclick = () => followArtist(artistName);
                }
            });
        }

        async function renderMyArtistsPage() {
            const followed = getFollowedArtists();
            const emptyState = document.getElementById('emptyArtistsState');
            const carousel = document.getElementById('followedArtistsCarousel');
            const loopsFeed = document.getElementById('artistsLoopsFeed');

            console.log('📊 Artistes suivis:', followed);

            // Show/hide empty state
            if (followed.length === 0) {
                emptyState.style.display = 'block';
                document.getElementById('followedArtistsSection').style.display = 'none';
                document.getElementById('artistsLoopsSection').style.display = 'none';
                return;
            }

            emptyState.style.display = 'none';
            document.getElementById('followedArtistsSection').style.display = 'block';
            document.getElementById('artistsLoopsSection').style.display = 'block';

            // Charger les œuvres depuis le serveur
            let allProducts = [];
            try {
                const response = await fetch('api_galerie_publique.php');
                const contentType = response.headers.get('content-type');
                
                if (response.ok && contentType && contentType.includes('application/json')) {
                    const result = await response.json();
                    if (result.success && result.data && result.data.length > 0) {
                        // Convertir le format API vers le format attendu
                        allProducts = result.data.map(art => ({
                            id: art.id,
                            title: art.title,
                            artist: art.artist_name,
                            category: art.category,
                            price: art.price,
                            image_url: art.image_url,
                            emoji: '🎨',
                            description: art.description || ''
                        }));
                        console.log('✅ Œuvres chargées depuis API:', allProducts.length);
                        console.log('📋 Artistes dans API:', [...new Set(allProducts.map(p => p.artist))]);
                    }
                }
            } catch (error) {
                console.log('⚠️ Utilisation des données locales:', error);
            }
            
            // Données locales si API indisponible si l'API échoue
            if (allProducts.length === 0) {
                allProducts = getProducts();
                console.log('📦 Œuvres chargées depuis local:', allProducts.length);
                console.log('📋 Artistes dans local:', [...new Set(allProducts.map(p => p.artist))]);
            }

            // Render carousel d'avatars (Your Loop)
            carousel.innerHTML = followed.map(artist => `
                <div class="artist-avatar-item" onclick="scrollToArtistLoops('${artist.name}')">
                    <div class="artist-avatar-ring">
                        <div class="artist-avatar-inner" style="overflow: hidden; background: linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1)); display: flex; align-items: center; justify-content: center;">
                            ${artist.profile_image && artist.profile_image !== 'undefined'
                                ? `<img loading="lazy" src="${artist.profile_image}" alt="${artist.name}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;" onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\\'font-size: 34px;\\'>${artist.avatar || '🎨'}</div>'">` 
                                : `<div style="font-size: 34px;">${artist.avatar || '🎨'}</div>`}
                        </div>
                    </div>
                    <div style="font-size: 12px; font-weight: 600; color: #ffffff; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); max-width: 70px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        ${artist.name.split(' ')[0]}
                    </div>
                </div>
            `).join('');

            // Afficher un indicateur de chargement pendant le chargement du feed
            loopsFeed.innerHTML = `
                <div style="text-align: center; padding: 60px 20px;">
                    <div style="font-size: 48px; margin-bottom: 20px; animation: pulse 1.5s ease-in-out infinite;">⏳</div>
                    <div style="color: rgba(255, 255, 255, 0.9); font-size: 16px; font-weight: 600;">Chargement des créations...</div>
                </div>
            `;

            // Render feed de loops (posts des artistes)
            loopsFeed.innerHTML = followed.map(artist => {
                // Comparaison robuste : ignorer la casse et les espaces
                const artistWorks = allProducts.filter(p => 
                    p.artist && artist.name && 
                    p.artist.trim().toLowerCase() === artist.name.trim().toLowerCase()
                );
                
                // Si aucune œuvre trouvée, afficher un message de debug
                if (artistWorks.length === 0) {
                    console.log(`⚠️ Aucune œuvre trouvée pour ${artist.name}`);
                    console.log('Produits disponibles:', allProducts.map(p => p.artist));
                }
                
                return artistWorks.map((work, index) => {
                    // Utiliser uniquement les vraies données
                    const isSocialLiked = isSociallyLiked(work.id);
                    
                    // Compter les likes réels pour cette œuvre
                    const allSocialLikes = getSocialLikes();
                    const realLikesCount = allSocialLikes.filter(id => id === work.id).length;
                    
                    // Utiliser la date de création si disponible, sinon rien
                    const timeAgo = work.created_at || work.date || '';
                    
                    return `
                    <div class="artist-loop-card" id="loop-${artist.name}-${index}" data-artist="${artist.name}">
                        <!-- Header -->
                        <div class="loop-header">
                            <div class="loop-avatar" onclick="viewArtistDetail(event, '${artist.name}')" style="cursor: pointer; overflow: hidden; background: linear-gradient(135deg, rgba(255,255,255,0.3), rgba(255,255,255,0.1)); display: flex; align-items: center; justify-content: center;">
                                ${artist.profile_image && artist.profile_image !== 'undefined'
                                    ? `<img loading="lazy" src="${artist.profile_image}" alt="${artist.name}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;" onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\\'font-size: 20px;\\'>${artist.avatar || '🎨'}</div>'">` 
                                    : `<div style="font-size: 20px;">${artist.avatar || '🎨'}</div>`}
                            </div>
                            <div class="loop-info" onclick="viewArtistDetail(event, '${artist.name}')" style="cursor: pointer;">
                                <div class="loop-artist-name">${artist.name}</div>
                                <div class="loop-metadata">${timeAgo} • ${work.category}</div>
                            </div>
                            <button class="loop-menu-btn" onclick="toggleArtistMenu(event, '${artist.name}')" title="Plus d'options">
                                ⋯
                            </button>
                        </div>

                        <!-- Image de l'œuvre -->
                        <div class="loop-image" onclick="viewProductDetail(${work.id})" style="position: relative; background: linear-gradient(135deg, #f8f9fa 0%, #e8eaed 100%);">
                            ${work.image_url && work.image_url !== 'undefined' 
                                ? `<img loading="lazy" src="${work.image_url}" 
                                       alt="${work.title}" 
                                       style="width: 100%; height: 100%; object-fit: cover;"
                                       onerror="this.style.display='none'; this.parentElement.querySelector('.loop-image-placeholder').style.display='flex';">
                                   <div class="loop-image-placeholder" style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; flex-direction: column; font-size: 96px;">
                                       ${work.emoji || '🎨'}
                                   </div>`
                                : `<div class="loop-image-placeholder" style="display: flex; width: 100%; height: 100%; align-items: center; justify-content: center; flex-direction: column;">
                                       <div style="font-size: 96px; margin-bottom: 16px;">${work.emoji || '🎨'}</div>
                                       <div style="font-size: 14px; color: rgba(0,0,0,0.4); font-weight: 600;">Image à venir</div>
                                   </div>`
                            }
                            <div class="loop-image-overlay">👁️</div>
                        </div>

                        <!-- Actions -->
                        <div class="loop-actions">
                            <button class="loop-action-btn ${isSocialLiked ? 'liked' : ''}" onclick="toggleSocialLike(event, ${work.id}, this)" title="J'aime cette création">
                                ${isSocialLiked ? '❤️' : '🤍'}
                                ${realLikesCount > 0 ? `<span class="action-count" id="like-count-${work.id}">${realLikesCount}</span>` : ''}
                            </button>
                            <button class="loop-action-btn" onclick="focusComment(${work.id})" title="Commenter">
                                💬
                            </button>
                            <button class="loop-action-btn" onclick="shareArtwork(${work.id})" title="Partager">
                                📤
                            </button>
                            <div style="flex: 1;"></div>
                            <button class="loop-action-btn ${favorites.includes(work.id) ? 'favorited' : ''}" onclick="toggleFavorite(event, ${work.id}); this.classList.toggle('favorited'); this.childNodes[0].textContent = favorites.includes(${work.id}) ? '⭐' : '☆'" title="Ajouter aux favoris">
                                ${favorites.includes(work.id) ? '⭐' : '☆'}
                            </button>
                            <button class="loop-action-btn" onclick="addToCart(event, ${work.id})" title="Ajouter au panier">
                                🛒
                            </button>
                        </div>

                        <!-- Détails -->
                        <div class="loop-details">
                            ${realLikesCount > 0 ? `<div class="loop-likes">${realLikesCount} j'aime</div>` : ''}
                            <div class="loop-title">
                                <span class="artist-tag" onclick="viewArtistDetail(event, '${artist.name}')">${artist.name}</span> 
                                ${work.title}
                            </div>
                            <div class="loop-description">
                                ${work.description || ''}
                            </div>
                            <div class="loop-price" style="color: #D4A574; text-shadow: 0 2px 5px rgba(212, 165, 116, 0.3); margin-top: 8px;">
                                ${formatPrice(work.price)}
                            </div>
                            
                            <!-- Section commentaires -->
                            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(255, 255, 255, 0.15);">
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <input type="text" 
                                           id="comment-${work.id}"
                                           placeholder="Ajouter un commentaire..." 
                                           style="flex: 1; background: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 20px; padding: 8px 16px; color: white; font-size: 13px; outline: none;"
                                           onfocus="this.style.background='rgba(255, 255, 255, 0.2)'"
                                           onblur="this.style.background='rgba(255, 255, 255, 0.15)'"
                                    />
                                    <button onclick="postComment(${work.id})" style="background: none; border: none; color: rgba(212, 165, 116, 0.8); font-weight: 700; font-size: 13px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.color='rgb(212, 165, 116)'" onmouseout="this.style.color='rgba(212, 165, 116, 0.8)'">
                                        Publier
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `}).join('');
            }).join('');
        }

        async function switchArtistTab(tab) {
            // Update active tab
            document.querySelectorAll('.artist-tab').forEach(t => {
                t.classList.remove('active');
            });
            document.querySelector(`[data-tab="${tab}"]`).classList.add('active');

            // Show different content based on tab
            if (tab === 'all') {
                await renderMyArtistsPage();
            } else if (tab === 'following') {
                await renderFollowingArtistsGrid();
            }
        }

        async function renderFollowingArtistsGrid() {
            const followed = getFollowedArtists();
            const emptyState = document.getElementById('emptyArtistsState');
            const followedSection = document.getElementById('followedArtistsSection');
            const loopsSection = document.getElementById('artistsLoopsSection');

            // Hide the carousel and loops sections
            followedSection.style.display = 'none';
            loopsSection.style.display = 'none';

            // Show/hide empty state
            if (followed.length === 0) {
                emptyState.style.display = 'block';
                return;
            }

            emptyState.style.display = 'none';
            loopsSection.style.display = 'block';

            // Charger les œuvres depuis le serveur
            let allProducts = [];
            try {
                const response = await fetch('api_galerie_publique.php');
                const contentType = response.headers.get('content-type');
                
                if (response.ok && contentType && contentType.includes('application/json')) {
                    const result = await response.json();
                    if (result.success && result.data && result.data.length > 0) {
                        allProducts = result.data.map(art => ({
                            id: art.id,
                            title: art.title,
                            artist: art.artist_name,
                            category: art.category,
                            price: art.price,
                            image_url: art.image_url,
                            emoji: '🎨'
                        }));
                    }
                }
            } catch (error) {
                console.log('⚠️ Utilisation des données locales');
            }
            
            if (allProducts.length === 0) {
                allProducts = getProducts();
            }

            // Create grid view for followed artists
            const loopsFeed = document.getElementById('artistsLoopsFeed');
            loopsFeed.innerHTML = `
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr)); gap: 20px; margin-top: 20px;">
                    ${followed.map(artist => {
                        // Comparaison robuste : ignorer la casse et les espaces
                        const artistWorks = allProducts.filter(p => 
                            p.artist && artist.name && 
                            p.artist.trim().toLowerCase() === artist.name.trim().toLowerCase()
                        );
                        const totalLikes = 0; // Compteur de likes
                        
                        return `
                            <div class="artist-profile-card" onclick="viewArtistDetail(event, '${artist.name}')" style="cursor: pointer;">
                                <div class="artist-profile-header">
                                    <div class="artist-profile-avatar" style="overflow: hidden; background: rgba(255,255,255,0.95); display: flex; align-items: center; justify-content: center;">
                                        ${artist.profile_image && artist.profile_image !== 'undefined'
                                            ? `<img loading="lazy" src="${artist.profile_image}" alt="${artist.name}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;" onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\\'font-size: 48px;\\'>${artist.avatar || '🎨'}</div>'">` 
                                            : `<div style="font-size: 48px;">${artist.avatar || '🎨'}</div>`}
                                    </div>
                                    <div class="artist-profile-badge">✓</div>
                                </div>
                                
                                <div class="artist-profile-info">
                                    <h3 class="artist-profile-name">${artist.name}</h3>
                                    <p class="artist-profile-specialty">${artist.specialty}</p>
                                    
                                    <div class="artist-profile-stats">
                                        <div class="artist-profile-stat">
                                            <span class="stat-value">${artistWorks.length}</span>
                                            <span class="stat-label">Œuvres</span>
                                        </div>
                                        <div class="artist-profile-stat">
                                            <span class="stat-value">${totalLikes}</span>
                                            <span class="stat-label">Likes</span>
                                        </div>
                                        <div class="artist-profile-stat">
                                            <span class="stat-value">0</span>
                                            <span class="stat-label">Vues</span>
                                        </div>
                                    </div>

                                    <p class="artist-profile-bio">${artist.bio}</p>

                                    <div class="artist-profile-preview">
                                        ${artistWorks.slice(0, 3).map(work => `
                                            <div class="preview-artwork" style="overflow: hidden; background: linear-gradient(135deg, #f8f9fa, #e8eaed); display: flex; align-items: center; justify-content: center;">
                                                ${work.image_url && work.image_url !== 'undefined'
                                                    ? `<img loading="lazy" src="${work.image_url}" alt="${work.title}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;" onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\\'font-size: 32px;\\'>${work.emoji || '🎨'}</div>'">` 
                                                    : `<div style="font-size: 32px;">${work.emoji || '🎨'}</div>`}
                                            </div>
                                        `).join('')}
                                        ${artistWorks.length > 3 ? `<div class="preview-more">+${artistWorks.length - 3}</div>` : ''}
                                    </div>
                                </div>

                                <div class="artist-profile-actions">
                                    <button onclick="event.stopPropagation(); viewArtistDetail(event, '${artist.name}')" class="btn-view-profile">
                                        👁️ Voir le profil
                                    </button>
                                    <button onclick="event.stopPropagation(); unfollowArtist('${artist.name}')" class="btn-unfollow">
                                        ✓ Abonné
                                    </button>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
        }


        function scrollToArtistLoops(artistName) {
            const element = document.querySelector(`[data-artist="${artistName}"]`);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                // Highlight effect
                element.style.transform = 'scale(1.02)';
                setTimeout(() => {
                    element.style.transform = 'scale(1)';
                }, 300);
            }
        }

        function toggleArtistMenu(event, artistName) {
            event.stopPropagation();
            
            const menu = document.createElement('div');
            menu.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: flex-end; justify-content: center; z-index: 10000; backdrop-filter: blur(5px);';
            
            menu.innerHTML = `
                <div style="background: white; width: 100%; max-width: 600px; border-radius: 20px 20px 0 0; padding: 20px; animation: slideUpMenu 0.3s;">
                    <div style="width: 40px; height: 4px; background: #e0e0e0; border-radius: 2px; margin: 0 auto 20px;"></div>
                    
                    <button onclick="viewArtistDetail(event, '${artistName}'); document.body.removeChild(this.closest('div').parentElement)" style="width: 100%; background: none; border: none; padding: 16px; text-align: left; font-size: 15px; font-weight: 500; color: #1a1a1a; cursor: pointer; border-radius: 12px; margin-bottom: 8px;" onmouseover="this.style.background='#f0f0f0'" onmouseout="this.style.background='none'">
                        👤 Voir le profil de ${artistName}
                    </button>
                    
                    <button onclick="unfollowArtist('${artistName}'); document.body.removeChild(this.closest('div').parentElement)" style="width: 100%; background: none; border: none; padding: 16px; text-align: left; font-size: 15px; font-weight: 500; color: #f44336; cursor: pointer; border-radius: 12px; margin-bottom: 8px;" onmouseover="this.style.background='#ffebee'" onmouseout="this.style.background='none'">
                        ❌ Ne plus suivre ${artistName}
                    </button>
                    
                    <button onclick="document.body.removeChild(this.parentElement.parentElement)" style="width: 100%; background: #f0f0f0; border: none; padding: 16px; text-align: center; font-size: 15px; font-weight: 600; color: #666; cursor: pointer; border-radius: 12px; margin-top: 12px;">
                        Annuler
                    </button>
                </div>
                
                
            `;
            
            menu.onclick = (e) => {
                if (e.target === menu) {
                    document.body.removeChild(menu);
                }
            };
            
            document.body.appendChild(menu);
        }

        function shareArtwork(productId) {
            const allProducts = getProducts();
            const product = allProducts.find(p => p.id === productId);
            
            if (navigator.share) {
                navigator.share({
                    title: product.title,
                    text: `Découvrez "${product.title}" par ${product.artist} sur ARKYL`,
                    url: window.location.href
                }).then(() => {
                    showToast('✅ Partagé avec succès');
                }).catch(() => {
                    fallbackShare(product);
                });
            } else {
                fallbackShare(product);
            }
        }

        function fallbackShare(product) {
            const shareText = `Découvrez "${product.title}" par ${product.artist} sur ARKYL - ${formatPrice(product.price)}`;
            navigator.clipboard.writeText(shareText).then(() => {
                showToast('📋 Lien copié dans le presse-papier');
            }).catch(() => {
                showToast('📤 ' + product.title);
            });
        }

        // ==================== SYSTÈME DE LIKES POUR LE FEED SOCIAL ====================
        // Stockage séparé pour les likes du feed (différent des favoris)
        function getSocialLikes() {
            return safeStorage.get('arkyl_social_likes', []);
        }

        function saveSocialLikes(likes) {
            safeStorage.set('arkyl_social_likes', likes);
        }

        function isSociallyLiked(productId) {
            const likes = getSocialLikes();
            return likes.includes(productId);
        }

        // Nouvelle fonction pour gérer le like social avec animation
        function toggleSocialLike(event, productId, button) {
            event.stopPropagation();
            
            let likes = getSocialLikes();
            const isCurrentlyLiked = likes.includes(productId);
            
            // Toggle like
            if (isCurrentlyLiked) {
                likes = likes.filter(id => id !== productId);
            } else {
                likes.push(productId);
            }
            saveSocialLikes(likes);
            
            // Animation du bouton
            button.classList.add('liked');
            setTimeout(() => {
                button.classList.remove('liked');
            }, 400);
            
            // Calculer le nouveau nombre total de likes pour cette œuvre
            const newRealLikesCount = likes.filter(id => id === productId).length;
            
            // Mise à jour ou création du compteur
            let countElement = button.querySelector('.action-count');
            const newIsLiked = !isCurrentlyLiked;
            
            if (newRealLikesCount > 0) {
                if (!countElement) {
                    // Créer le compteur s'il n'existe pas
                    countElement = document.createElement('span');
                    countElement.className = 'action-count';
                    countElement.id = 'like-count-' + productId;
                    button.appendChild(countElement);
                }
                countElement.textContent = newRealLikesCount;
            } else if (countElement) {
                // Supprimer le compteur s'il n'y a plus de likes
                countElement.remove();
            }
            
            // Mise à jour de l'icône avec animation de cœur
            const iconNode = button.childNodes[0];
            if (iconNode && iconNode.nodeType === Node.TEXT_NODE) {
                button.childNodes[0].textContent = newIsLiked ? '❤️' : '🤍';
            }
            
            // Mise à jour du texte "X j'aime" dans les détails
            const loopCard = button.closest('.artist-loop-card');
            if (loopCard) {
                let likesDiv = loopCard.querySelector('.loop-likes');
                if (newRealLikesCount > 0) {
                    if (!likesDiv) {
                        // Créer le div s'il n'existe pas
                        const detailsDiv = loopCard.querySelector('.loop-details');
                        likesDiv = document.createElement('div');
                        likesDiv.className = 'loop-likes';
                        detailsDiv.insertBefore(likesDiv, detailsDiv.firstChild);
                    }
                    likesDiv.textContent = newRealLikesCount + ' j\'aime';
                } else if (likesDiv) {
                    // Supprimer le div s'il n'y a plus de likes
                    likesDiv.remove();
                }
            }
            
            // Feedback visuel
            if (newIsLiked) {
                showToast('❤️ Vous aimez cette création !');
            }
        }


        // Fonction pour focus sur le champ de commentaire
        function focusComment(productId) {
            const commentInput = document.getElementById(`comment-${productId}`);
            if (commentInput) {
                commentInput.focus();
            }
        }

        // Fonction pour poster un commentaire
        function postComment(productId) {
            const commentInput = document.getElementById(`comment-${productId}`);
            if (!commentInput || !commentInput.value.trim()) {
                showToast('⚠️ Veuillez écrire un commentaire');
                return;
            }
            
            const comment = commentInput.value.trim();
            
            // Animation de publication
            commentInput.value = '';
            commentInput.blur();
            
            // Feedback visuel
            showToast('💬 Commentaire publié avec succès !');
            
            // TODO: sync commentaire API
            console.log('Nouveau commentaire sur l\'œuvre', productId, ':', comment);
        }

        // ==================== UTILS ====================
        function formatPrice(price) {
            return price.toLocaleString('fr-FR') + ' FCFA';
        }

        function performSearch() {
            const query = document.getElementById('globalSearch').value.trim().toLowerCase();
            if (!query) {
                showToast('⚠️ Entrez un terme de recherche');
                return;
            }

            // Search in local products
            const allProducts = getProducts();
            const results = allProducts.filter(p => 
                p.title.toLowerCase().includes(query) || 
                p.artist.toLowerCase().includes(query) ||
                p.category.toLowerCase().includes(query)
            );

            if (results.length > 0) {
                // Show local results
                navigateTo('home');
                const container = document.getElementById('productsContainer');
                container.innerHTML = results.map(product => `
                    <div class="product-card" onclick="viewProductDetail(${product.id})">
                        <div class="product-image">
                            <span class="product-badge">${product.badge}</span>
                            <button class="like-button" onclick="toggleFavorite(event, ${product.id})">
                                ${favorites.includes(product.id) ? '❤️' : '🤍'}
                            </button>
                            <img src="${product.image}" alt="${product.title}" style="width:100%;height:100%;object-fit:cover;border-radius:20px;" loading="lazy" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22400%22%3E%3Crect fill=%22%23ddd%22 width=%22400%22 height=%22400%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2248%22%3E🎨%3C/text%3E%3C/svg%3E'">
                        </div>
                        <div class="product-info">
                            <div class="product-title">${product.title}</div>
                            <div class="product-artist" onclick="viewArtistDetail(event, '${product.artist}')">par ${product.artist}</div>
                            <div class="product-footer">
                                <div class="product-price">${formatPrice(product.price)}</div>
                                <button class="add-cart-btn" onclick="addToCart(event, ${product.id})">+ Panier</button>
                            </div>
                        </div>
                    </div>
                `).join('');
                
                showToast(`✅ ${results.length} résultat(s) trouvé(s)`);
            } else {
                // Offer to search on Google
                if (confirm(`Aucun résultat local pour "${query}". Rechercher sur Google ?`)) {
                    window.open(`https://www.google.com/search?q=${encodeURIComponent('art africain ' + query)}`, '_blank');
                } else {
                    showToast(`❌ Aucun résultat pour "${query}"`);
                    (typeof afficherOeuvresFiltrees === 'function' && window.toutesLesOeuvres?.length > 0) ? afficherOeuvresFiltrees() : (typeof chargerLaVraieGalerie === 'function' ? chargerLaVraieGalerie() : null); // Show all products again
                }
            }
        }

        // Allow Enter key in search
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('globalSearch');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        performSearch();
                    }
                });
            }
        });

        function updateBadges() {
            const notifCount = notifications.filter(n => n.unread).length;
            document.getElementById('notifBadge').textContent = notifCount;
            document.getElementById('notifBadge').style.display = notifCount > 0 ? 'block' : 'none';

            document.getElementById('favBadge').textContent = favorites.length;
            document.getElementById('favBadge').style.display = favorites.length > 0 ? 'block' : 'none';

            const cartCount = cartItems.reduce((s, i) => s + i.quantity, 0);
            const cartBadge = document.getElementById('cartBadge');
            if (cartBadge) {
                cartBadge.textContent = cartCount;
                cartBadge.style.display = cartCount > 0 ? 'block' : 'none';
            }
        }

        let toastTimeout;
        function showToast(message) {
            const toast = document.getElementById('toast');
            document.getElementById('toastText').textContent = message;
            toast.classList.add('show');
            clearTimeout(toastTimeout);
            toastTimeout = setTimeout(() => toast.classList.remove('show'), 3000);
        }

        // ==================== ARTIST REGISTRATION ====================


        function openArtistRegistration() {
            document.getElementById('artistRegModal').classList.add('show');
            resetRegistrationForm();
        }

        function closeArtistRegistration() {
            document.getElementById('artistRegModal').classList.remove('show');
        }

        function resetRegistrationForm() {
            // Reset all inputs
            ['reg-name','reg-email','reg-phone','reg-password','reg-password-confirm'].forEach(id => {
                document.getElementById(id).value = '';
            });
            document.getElementById('reg-country').value = '';
            document.getElementById('reg-bio').value = '';

            // Reset specialty chips
            document.querySelectorAll('.specialty-chip').forEach(c => c.classList.remove('selected'));

            // Reset avatar to first
            document.querySelectorAll('.avatar-option').forEach((a, i) => {
                a.classList.toggle('selected', i === 0);
            });

            // Reset stepper
            goToRegStepVisual(1);
            validateRegStep1();
        }

        // --- Step navigation ---
        function goToRegStep(step) {
            // Validate before proceeding
            if (step === 2) {
                if (!validateRegStep1(true)) return;
            }
            if (step === 3) {
                if (!validateRegStep2(true)) return;
                buildReviewCard();
            }
            goToRegStepVisual(step);
        }

        function goToRegStepVisual(step) {
            // Hide all panels
            document.querySelectorAll('.reg-step-panel').forEach(p => p.classList.remove('active'));
            document.getElementById('regStep' + step).classList.add('active');

            // Update stepper dots
            for (let i = 1; i <= 3; i++) {
                const dot = document.getElementById('stepDot' + i);
                dot.classList.remove('active', 'done');
                if (i < step) dot.classList.add('done');
                else if (i === step) dot.classList.add('active');
            }

            // Update connector lines
            for (let i = 1; i <= 2; i++) {
                const line = document.getElementById('stepLine' + i);
                line.classList.toggle('done', i < step);
            }
        }

        // --- Avatar & specialty selectors ---
        function selectAvatar(el) {
            document.querySelectorAll('.avatar-option').forEach(a => a.classList.remove('selected'));
            el.classList.add('selected');
        }

        function toggleSpecialty(el) {
            el.classList.toggle('selected');
            validateRegStep2();
        }

        // --- Validation ---
        function validateRegStep1(showErrors = false) {
            const name  = document.getElementById('reg-name').value.trim();
            const email = document.getElementById('reg-email').value.trim();
            const phone = document.getElementById('reg-phone').value.trim();
            const pw    = document.getElementById('reg-password').value;
            const pwc   = document.getElementById('reg-password-confirm').value;

            let valid = name.length >= 2
                     && email.includes('@') && email.includes('.')
                     && phone.length >= 8
                     && pw.length >= 6
                     && pw === pwc;

            document.getElementById('regNext1').disabled = !valid;

            if (showErrors && !valid) {
                if (pw.length < 6) showToast('Le mot de passe doit avoir au moins 6 caractères');
                else if (pw !== pwc) showToast('Les mots de passe ne correspondent pas');
                else showToast('Veuillez compléter tous les champs');
                return false;
            }
            return valid;
        }

        function validateRegStep2(showErrors = false) {
            const country = document.getElementById('reg-country').value;
            const specialties = document.querySelectorAll('.specialty-chip.selected');

            let valid = country !== '' && specialties.length > 0;

            document.getElementById('regNext2').disabled = !valid;

            if (showErrors && !valid) {
                if (!country) showToast('Veuillez sélectionner votre pays');
                else showToast('Choisissez au moins une spécialité');
                return false;
            }
            return valid;
        }

        // --- Build review card (step 3) ---
        function buildReviewCard() {
            const name    = document.getElementById('reg-name').value.trim();
            const email   = document.getElementById('reg-email').value.trim();
            const phone   = document.getElementById('reg-phone').value.trim();
            const country = document.getElementById('reg-country').value;
            const avatarSrc = document.getElementById('regAvatarImg').src;
            const specs   = [...document.querySelectorAll('.specialty-chip.selected')].map(c => c.textContent.trim());
            const bio     = document.getElementById('reg-bio').value.trim();

            document.getElementById('regReviewCard').innerHTML = `
                <div class="reg-review-row"><span>Avatar</span><span style="font-size:22px;"><img loading="lazy" src="${avatarSrc}" style="width:50px;height:50px;border-radius:50%;object-fit:cover;border:2px solid var(--or);" /></span></div>
                <div class="reg-review-row"><span>Nom</span><span>${name}</span></div>
                <div class="reg-review-row"><span>Email</span><span>${email}</span></div>
                <div class="reg-review-row"><span>Téléphone</span><span>${phone}</span></div>
                <div class="reg-review-row"><span>Pays</span><span>${country}</span></div>
                <div class="reg-review-row"><span>Spécialité(s)</span><span>${specs.join(', ')}</span></div>
                ${bio ? `<div class="reg-review-row"><span>Bio</span><span style="max-width:55%;text-align:right;line-height:1.4;">${bio}</span></div>` : ''}
            `;
        }

        // --- Submit ---
        function submitArtistRegistration() {
            // Gather data
            const accountData = {
                name:     document.getElementById('reg-name').value.trim(),
                email:    document.getElementById('reg-email').value.trim(),
                phone:    document.getElementById('reg-phone').value.trim(),
                country:  document.getElementById('reg-country').value,
                avatar:   document.getElementById('regAvatarImg').src,
                specialty:[...document.querySelectorAll('.specialty-chip.selected')].map(c => c.textContent.trim()),
                bio:      document.getElementById('reg-bio').value.trim(),
                createdAt: new Date().toISOString()
            };

            // Simulate a brief loading delay for realism
            const btn = document.querySelector('#regStep3 .reg-btn-next:last-child');
            btn.textContent = 'Création en cours...';
            btn.disabled = true;

            setTimeout(() => {
                // Persist the artist account flag
                safeStorage.set('arkyl_artist_account', accountData); _memStore['arkyl_artist_account'] = accountData;

                // Si l'utilisateur est connecté avec Google et que l'email correspond, lier les comptes
                if (currentUser && currentUser.email === accountData.email) {
                    currentUser.isArtist = true;
                    currentUser.artistName = accountData.name;
                    safeStorage.set('arkyl_current_user', currentUser);
                    console.log('✅ Compte Google lié au compte artiste:', accountData.name);
                }

                // Show success step
                document.querySelectorAll('.reg-step-panel').forEach(p => p.classList.remove('active'));
                document.getElementById('regStep4').classList.add('active');

                // Update stepper: all done
                for (let i = 1; i <= 3; i++) {
                    const dot = document.getElementById('stepDot' + i);
                    dot.classList.remove('active');
                    dot.classList.add('done');
                }
                document.querySelectorAll('.reg-step-line').forEach(l => l.classList.add('done'));
            }, 1200);
        }

        function goToArtistSpace() {
            closeArtistRegistration();
            switchToArtistMode();
        }


        // ==================== ARTISTE DATABASE ====================
        class ArtistDatabase {
            constructor() {
                this.artworks = this._load('artist_artworks') || [];
                this.sales    = this._load('artist_sales')    || []; // Tableau vide par défaut
                this.nextId = this._load('next_artwork_id') || 1;
            }
            
            _load(k) { 
                try { 
                    const d = _memStore['arkyl_'+k] || null; 
                    return d ? JSON.parse(d) : null; 
                } catch(e){ 
                    console.error('Erreur de chargement:', k, e); 
                    return null; 
                } 
            }
            
            _save(k,v) { 
                try { 
                    _memStore['arkyl_'+k] = v; 
                    return true;
                } catch(e){
                    console.error('Erreur de sauvegarde:', k, e);
                    // Check if quota exceeded
                    if (e.name === 'QuotaExceededError') {
                        showToast('⚠️ Espace de stockage insuffisant. Supprimez des œuvres anciennes.');
                    }
                    return false;
                } 
            }
            
            addArtwork(a) { 
                a.id = this.nextId++; 
                a.status = 'published'; 
                a.createdAt = new Date().toISOString(); 
                this.artworks.push(a); 
                
                const saved = this._save('artist_artworks', this.artworks) && 
                              this._save('next_artwork_id', this.nextId);
                
                if (!saved) {
                    // Rollback if save failed
                    this.artworks.pop();
                    this.nextId--;
                    throw new Error('Échec de la sauvegarde');
                }
            }
            
            updateArtwork(id, u) { 
                const i = this.artworks.findIndex(a => a.id === id); 
                if (i > -1) { 
                    const backup = {...this.artworks[i]};
                    this.artworks[i] = {...this.artworks[i], ...u}; 
                    
                    if (!this._save('artist_artworks', this.artworks)) {
                        // Rollback on failure
                        this.artworks[i] = backup;
                        throw new Error('Échec de la mise à jour');
                    }
                } 
            }
            
            deleteArtwork(id) { 
                const i = this.artworks.findIndex(a => a.id === id); 
                if (i > -1) { 
                    const backup = this.artworks[i];
                    this.artworks.splice(i, 1); 
                    
                    if (!this._save('artist_artworks', this.artworks)) {
                        // Rollback on failure
                        this.artworks.splice(i, 0, backup);
                        throw new Error('Échec de la suppression');
                    }
                } 
            }
            
            // Helper method to clear all data
            clearAll() {
                delete _memStore['arkyl_artist_artworks'];
                delete _memStore['arkyl_artist_sales'];
                delete _memStore['arkyl_next_artwork_id'];
                this.artworks = [];
                this.sales = [];
                this.nextId = 1;
            }
        }
        const db = new ArtistDatabase();
        
        // Nettoyer les données de ventes au démarrage
        db.sales = [];
        db._save('artist_sales', []);
        
        let editingArtworkId = null;
        let currentGalleryFilter = 'all';

        // ==================== MODE SWITCHING ====================
        function switchToArtistMode() {
            document.getElementById('clientNav').style.display  = 'none';
            document.getElementById('artistNav').style.display  = 'flex';
            // hide all client pages
            document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
            // show artist space
            document.getElementById('artistSpace').style.display = 'block';
            // hydrate + dashboard
            hydrateProfile();
            showArtistSection('dashboard');
            window.scrollTo(0,0);
        }

        function switchToClientMode() {
            document.getElementById('artistNav').style.display  = 'none';
            document.getElementById('clientNav').style.display  = 'flex';
            document.getElementById('artistSpace').style.display = 'none';
            navigateTo('home');
        }

        // ==================== HYDRATION ====================
        function hydrateProfile(skipToast = false) {
            const acc = safeStorage.get('arkyl_artist_account', null) || _memStore['arkyl_artist_account'] || null;
            if (!acc) return;

            // Update nav avatar
            const navAvatar = document.getElementById('navAvatar');
            if (navAvatar) {
                if (acc.avatar && (acc.avatar.startsWith('http') || acc.avatar.startsWith('data:'))) {
                    navAvatar.innerHTML = `<img loading="lazy" src="${acc.avatar}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" />`;
                } else {
                    navAvatar.textContent = acc.avatar || '🎨';
                }
            }

            // Update welcome banner avatar
            const welcomeAvatar = document.getElementById('welcomeAvatar');
            if (welcomeAvatar) {
                if (acc.avatar && (acc.avatar.startsWith('http') || acc.avatar.startsWith('data:'))) {
                    welcomeAvatar.innerHTML = `<img loading="lazy" src="${acc.avatar}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" />`;
                } else {
                    welcomeAvatar.textContent = acc.avatar || '🎨';
                }
            }

            const navArtistName = document.getElementById('navArtistName');
            if (navArtistName) navArtistName.textContent = acc.name || 'Artiste';
            
            const welcomeName = document.getElementById('welcomeName');
            if (welcomeName) welcomeName.textContent = 'Bienvenue, ' + (acc.name || 'Artiste');
            
            const welcomeBio = document.getElementById('welcomeBio');
            if (welcomeBio) welcomeBio.textContent = acc.bio || 'Publiez vos œuvres et développez votre carrière sur ARKYL.';

            let meta = '';
            if (acc.country)   meta += '<span>📍 ' + acc.country + '</span>';
            if (acc.specialty && acc.specialty.length) meta += '<span>🎨 ' + acc.specialty.join(' · ') + '</span>';
            if (acc.email)     meta += '<span>✉️ ' + acc.email + '</span>';
            
            const welcomeMeta = document.getElementById('welcomeMeta');
            if (welcomeMeta) welcomeMeta.innerHTML = meta;

            // Toast contextualisé - uniquement si pas de skipToast
            if (!skipToast) {
                const fk = 'arkyl_artist_first_visit_done';
                if (!_memStore[fk]) {
                    _memStore[fk] = '1';
                    showToast('🎉 Bienvenue, ' + acc.name + ' ! Votre espace artiste est prêt.');
                } else {
                    showToast('Bienvenue, ' + acc.name + ' ! 🎨');
                }
            }
        }

        // ==================== ARTISTE NAVIGATION ====================
        function showArtistSection(section) {
            document.querySelectorAll('.page-section').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('#artistNav .nav-link').forEach(l => l.classList.remove('active'));

            const map = { dashboard:'dashboardSection', artworks:'artworksSection', sales:'salesSection', gallery:'artistGallerySection' };
            const navMap = { dashboard:'artNavDashboard', artworks:'artNavArtworks', sales:'artNavSales', gallery:'artNavGallery' };

            if (map[section]) {
                document.getElementById(map[section]).classList.add('active');
                document.getElementById(navMap[section]).classList.add('active');
            }

            if (section === 'dashboard') updateDashboard();
            if (section === 'artworks') renderArtworks();
            if (section === 'sales')    renderSales();
            if (section === 'gallery')  renderArtistGallery();
            window.scrollTo(0,0);
        }

        // ==================== DASHBOARD ====================
        function updateDashboard() {
            // Ensure db.sales is an array
            if (!Array.isArray(db.sales)) {
                db.sales = [];
                db._save('artist_sales', db.sales);
            }
            
            document.getElementById('totalArtworks').textContent = db.artworks.length;
            const rev = db.sales.reduce((s,sale) => s + sale.price, 0);
            document.getElementById('totalRevenue').textContent = formatPrice(rev);
            document.getElementById('totalSales').textContent   = db.sales.length;
            
            // Display recent activity based on actual data
            const recentActivityEl = document.getElementById('recentActivity');
            if (db.artworks.length === 0 && db.sales.length === 0) {
                recentActivityEl.innerHTML = `
                    <p style="opacity:0.6;line-height:1.8;text-align:center;">
                        🎨 Aucune activité pour le moment<br>
                        Commencez par publier votre première œuvre !
                    </p>`;
            } else {
                let activities = [];
                
                // Add recent artworks
                const recentArtworks = db.artworks.slice(-3).reverse();
                recentArtworks.forEach(a => {
                    activities.push(`✅ Œuvre "${a.title}" publiée`);
                });
                
                // Add recent sales
                const recentSales = db.sales.slice(-3).reverse();
                recentSales.forEach(s => {
                    activities.push(`💰 Vente : "${s.artwork}" — ${formatPrice(s.price)}`);
                });
                
                recentActivityEl.innerHTML = `
                    <p style="opacity:0.8;line-height:1.8;">
                        ${activities.join('<br>')}
                    </p>`;
            }
        }
        
        // Function to reset all artist data (useful for testing or fresh start)
        function resetArtistData() {
            if (confirm('⚠️ Voulez-vous vraiment réinitialiser toutes vos données (œuvres, ventes) ?\n\nCette action est irréversible !')) {
                db.clearAll();
                updateDashboard();
                renderArtworks();
                renderSales();
                showToast('✅ Données réinitialisées avec succès !');
            }
        }
        
        // Expose reset function globally for console access
        window.resetArtistData = resetArtistData;

        // ==================== PHOTO UPLOAD MANAGEMENT ====================
        let currentImageMode = 'photo'; // Mode photo uniquement
        let currentPhotosData = []; // Store multiple base64 photo data (max 5)


        // Nouvelle fonction pour gérer plusieurs photos
        function handleMultiplePhotosUpload(event) {
            const files = Array.from(event.target.files);
            if (!files.length) return;

            // Vérifier qu'on ne dépasse pas 5 photos au total
            const remainingSlots = 5 - currentPhotosData.length;
            if (files.length > remainingSlots) {
                showToast(`⚠️ Vous ne pouvez ajouter que ${remainingSlots} photo(s) supplémentaire(s)`);
                return;
            }

            let filesProcessed = 0;
            files.forEach(file => {
                // Validate file type
                if (!file.type.startsWith('image/')) {
                    showToast('⚠️ Veuillez sélectionner des images uniquement');
                    return;
                }

                // Validate file size (5MB max)
                const maxSize = 5 * 1024 * 1024;
                if (file.size > maxSize) {
                    showToast(`⚠️ ${file.name} dépasse 5 MB`);
                    return;
                }

                // Read and store the image
                const reader = new FileReader();
                reader.onload = function(e) {
                    currentPhotosData.push(e.target.result);
                    filesProcessed++;
                    
                    if (filesProcessed === files.length) {
                        updatePhotosPreview();
                        showToast(`✅ ${files.length} photo(s) ajoutée(s)`);
                    }
                };
                reader.onerror = function() {
                    showToast('⚠️ Erreur lors du chargement d\'une photo');
                };
                reader.readAsDataURL(file);
            });
            
            // Reset input
            event.target.value = '';
        }

        function updatePhotosPreview() {
            const grid = document.getElementById('photosPreviewGrid');
            grid.innerHTML = currentPhotosData.map((photo, index) => `
                <div style="position: relative; aspect-ratio: 1; border-radius: 12px; overflow: hidden; background: rgba(255,255,255,0.1); border: 2px solid ${index === 0 ? 'rgba(212, 165, 116, 0.8)' : 'rgba(255,255,255,0.2)'};">
                    <img loading="lazy" src="${photo}" alt="Photo ${index + 1}" style="width: 100%; height: 100%; object-fit: cover;">
                    ${index === 0 ? '<div style="position: absolute; top: 4px; left: 4px; background: rgba(212, 165, 116, 0.95); color: white; padding: 3px 8px; border-radius: 6px; font-size: 10px; font-weight: 700;">PRINCIPALE</div>' : ''}
                    <button onclick="removePhotoAtIndex(${index})" style="position: absolute; top: 4px; right: 4px; background: rgba(0,0,0,0.7); color: white; border: none; width: 28px; height: 28px; border-radius: 50%; cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,0,0,0.8)'" onmouseout="this.style.background='rgba(0,0,0,0.7)'">×</button>
                    ${index > 0 ? `<button onclick="setMainPhoto(${index})" style="position: absolute; bottom: 4px; left: 4px; background: rgba(255,255,255,0.9); color: #333; border: none; padding: 4px 8px; border-radius: 6px; cursor: pointer; font-size: 10px; font-weight: 600; transition: all 0.2s;" onmouseover="this.style.background='rgba(212, 165, 116, 0.9)'; this.style.color='white'" onmouseout="this.style.background='rgba(255,255,255,0.9)'; this.style.color='#333'">⭐ Principale</button>` : ''}
                </div>
            `).join('');
            
            // Afficher/masquer le placeholder
            document.getElementById('photoPlaceholder').style.display = currentPhotosData.length >= 5 ? 'none' : 'block';
            if (currentPhotosData.length >= 5) {
                document.getElementById('photoUploadContainer').style.display = 'none';
            }
        }

        function removePhotoAtIndex(index) {
            if (confirm('Supprimer cette photo ?')) {
                currentPhotosData.splice(index, 1);
                updatePhotosPreview();
                
                // Réafficher le container d'upload si on a moins de 5 photos
                if (currentPhotosData.length < 5) {
                    document.getElementById('photoUploadContainer').style.display = 'block';
                }
                
                showToast('Photo supprimée');
            }
        }

        function setMainPhoto(index) {
            // Déplacer la photo sélectionnée en première position
            const photo = currentPhotosData.splice(index, 1)[0];
            currentPhotosData.unshift(photo);
            updatePhotosPreview();
            showToast('✅ Photo principale mise à jour');
        }


        function changePhoto(event) {
            event.stopPropagation();
            document.getElementById('artwork-photo-input').click();
        }

        function removePhoto(event) {
            event.stopPropagation();
            currentPhotosData = [];
            updatePhotosPreview();
            document.getElementById('photoUploadContainer').style.display = 'block';
            showToast('Toutes les photos supprimées');
        }

        function resetPhotoUpload() {
            currentPhotosData = [];
            updatePhotosPreview();
            document.getElementById('artwork-photo-input').value = '';
            document.getElementById('photoUploadContainer').style.display = 'block';
        }

        // ==================== PUBLIC PRODUCTS MANAGEMENT ====================
        function addToPublicProducts(artwork) {
            const products = getProducts();
            
            // Trouver le prochain ID disponible
            const maxId = products.length > 0 ? Math.max(...products.map(p => p.id)) : 0;
            
            const publicProduct = {
                id: maxId + 1,
                title: artwork.title,
                artist: artwork.artistName || 'Artiste',
                artistCountry: artwork.artistCountry || '',
                artistAvatar: artwork.artistAvatar || '',
                category: artwork.category,
                price: artwork.price,
                image: artwork.photo,
                photos: artwork.photos || [artwork.photo],
                description: artwork.description || '',
                dimensions: artwork.dimensions || null,
                technique: artwork.technique || null,
                techniqueCustom: artwork.techniqueCustom || null,
                createdAt: new Date().toISOString(),
                // Stocker l'ID de l'artiste pour lier les deux
                artistArtworkId: artwork.id
            };
            
            products.push(publicProduct);
            saveProducts(products);
            
            // Ajouter au storage partagé pour le monitoring en temps réel
            const artworkKey = `artwork:${publicProduct.id}`;
            window.storage.set(artworkKey, JSON.stringify(publicProduct), true).then(() => {
                console.log('✅ Œuvre ajoutée au storage partagé:', artworkKey);
            }).catch(err => {
                console.log('⚠️ Erreur ajout storage:', err);
            });
            
            console.log('✅ Œuvre ajoutée à la galerie publique:', publicProduct);
        }

        function updatePublicProduct(artistArtworkId, artwork) {
            const products = getProducts();
            const index = products.findIndex(p => p.artistArtworkId === artistArtworkId);
            
            if (index > -1) {
                products[index] = {
                    ...products[index],
                    title: artwork.title,
                    category: artwork.category,
                    price: artwork.price,
                    image: artwork.photo,
                    photos: artwork.photos || [artwork.photo],
                    description: artwork.description || '',
                    dimensions: artwork.dimensions || null,
                    technique: artwork.technique || null,
                    techniqueCustom: artwork.techniqueCustom || null
                };
                
                saveProducts(products);
            }
        }

        function removeFromPublicProducts(artistArtworkId) {
            const products = getProducts();
            const productToRemove = products.find(p => p.artistArtworkId === artistArtworkId);
            const filtered = products.filter(p => p.artistArtworkId !== artistArtworkId);
            
            if (filtered.length < products.length) {
                saveProducts(filtered);
                
                // Supprimer aussi du storage partagé
                if (productToRemove && productToRemove.id) {
                    const artworkKey = `artwork:${productToRemove.id}`;
                    window.storage.delete(artworkKey, true).then(() => {
                        console.log('✅ Œuvre supprimée du storage partagé:', artworkKey);
                    }).catch(err => {
                        console.log('⚠️ Erreur suppression storage:', err);
                    });
                }
            }
        }

        // ==================== ARTWORKS ====================
        function openArtworkModal(id) {
            editingArtworkId = id || null;
            resetPhotoUpload(); // Reset photo state
            
            if (id) {
                const a = db.artworks.find(x => x.id === id);
                if (a) {
                    document.getElementById('artwork-title').value       = a.title;
                    document.getElementById('artwork-category').value    = a.category;
                    document.getElementById('artwork-price').value       = a.price;
                    document.getElementById('artwork-description').value = a.description || '';
                    
                    // Dimensions
                    document.getElementById('artwork-width').value  = a.dimensions?.width || '';
                    document.getElementById('artwork-height').value = a.dimensions?.height || '';

                    
                    // Technique
                    document.getElementById('artwork-technique').value = a.technique || '';
                    if (a.technique === 'Autre' || (a.techniqueCustom && a.techniqueCustom !== a.technique)) {
                        document.getElementById('artwork-technique-custom').style.display = 'block';
                        document.getElementById('artwork-technique-custom').value = a.techniqueCustom || '';
                    }
                    
                    // Handle photos if exist
                    if (a.photos && a.photos.length > 0) {
                        currentPhotosData = [...a.photos];
                        updatePhotosPreview();
                    } else if (a.photo) {
                        // Compatibilité avec ancien système mono-photo
                        currentPhotosData = [a.photo];
                        updatePhotosPreview();
                    }
                }
            } else {
                document.getElementById('artwork-title').value       = '';
                document.getElementById('artwork-category').value    = '';
                document.getElementById('artwork-price').value       = '';
                document.getElementById('artwork-description').value = '';
                document.getElementById('artwork-width').value       = '';
                document.getElementById('artwork-height').value      = '';

                document.getElementById('artwork-technique').value   = '';
                document.getElementById('artwork-technique-custom').value = '';
                document.getElementById('artwork-technique-custom').style.display = 'none';
            }
            document.getElementById('artworkModal').classList.add('show');
        }

        // Gestion de l'affichage du champ technique personnalisé
        document.addEventListener('DOMContentLoaded', function() {
            const techniqueSelect = document.getElementById('artwork-technique');
            const techniqueCustomInput = document.getElementById('artwork-technique-custom');
            
            if (techniqueSelect && techniqueCustomInput) {
                techniqueSelect.addEventListener('change', function() {
                    if (this.value === 'Autre' || this.value === '') {
                        techniqueCustomInput.style.display = 'block';
                    } else {
                        techniqueCustomInput.style.display = 'none';
                    }
                });
            }
        });

        function closeArtworkModal() {
            document.getElementById('artworkModal').classList.remove('show');
        }

        async function saveArtwork() {
            const title = document.getElementById('artwork-title').value.trim();
            const cat   = document.getElementById('artwork-category').value;
            if (!title || !cat) { showToast('Titre et catégorie obligatoires'); return; }

            // Validate that we have at least one photo
            if (!currentPhotosData || currentPhotosData.length === 0) {
                showToast('⚠️ Veuillez ajouter au moins une photo de l\'œuvre');
                return;
            }

            // Get artist account info
            const artistAccount = safeStorage.get('arkyl_artist_account', null);
            if (!artistAccount) {
                showToast('❌ Compte artiste non trouvé');
                return;
            }

            // Get dimensions
            const width = parseFloat(document.getElementById('artwork-width').value) || null;
            const height = parseFloat(document.getElementById('artwork-height').value) || null;
            
            // Get technique
            const technique = document.getElementById('artwork-technique').value;
            const techniqueCustom = document.getElementById('artwork-technique-custom').value.trim();

            const artwork = {
                title: title,
                category: cat,
                price: parseFloat(document.getElementById('artwork-price').value) || 0,
                description: document.getElementById('artwork-description').value.trim(),
                imageMode: 'photo',
                photos: currentPhotosData, // Array de photos
                photo: currentPhotosData[0], // Photo principale
                dimensions: (width || height) ? { width, height } : null,
                technique: technique || null,
                techniqueCustom: (technique === 'Autre' || techniqueCustom) ? techniqueCustom : null,
                emoji: '🎨',
                artistName: artistAccount.name || 'Artiste',
                artistCountry: artistAccount.country || '',
                artistAvatar: artistAccount.avatar || ''
            };

            // Afficher un loader
            showToast('📤 Publication en cours...');

            try {
                // Sauvegarder dans la base locale de l'artiste (pour son portfolio)
                if (editingArtworkId) {
                    db.updateArtwork(editingArtworkId, artwork);
                    // Mettre à jour aussi dans la galerie publique
                    updatePublicProduct(editingArtworkId, artwork);
                } else {
                    // Ajouter d'abord à la base de données pour obtenir l'ID
                    db.addArtwork(artwork);
                    // Récupérer l'œuvre avec son ID généré
                    const addedArtwork = db.artworks[db.artworks.length - 1];
                    
                    // ⭐ ENVOI DIRECT AU SERVEUR (plus besoin de addToPublicProducts)
                    try {
                        // Préparer les données en JSON
                        const dataToSend = {
                            title: addedArtwork.title,
                            category: addedArtwork.category,
                            price: addedArtwork.price,
                            description: addedArtwork.description || '',
                            artist_id: currentUser.id,
                            artist_name: addedArtwork.artistName,
                            artist_country: addedArtwork.artistCountry || '',
                            image_url: addedArtwork.photo, // Photo principale en base64
                            photos: addedArtwork.photos || [],
                            technique: addedArtwork.technique || '',
                            dimensions: addedArtwork.dimensions || null,
                            status: 'publiée'
                        };
                        
                        // Debug : afficher les données envoyées
                        console.log('📤 Données envoyées à l\'API:', dataToSend);
                        console.log('👤 currentUser:', currentUser);
                        
                        const response = await fetch('https://arkyl-galerie.onrender.com/api_ajouter_oeuvre.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(dataToSend)
                        });
                        
                        // Debug : afficher la réponse brute
                        console.log('📥 Statut HTTP:', response.status);
                        const responseText = await response.text();
                        console.log('📥 Réponse brute du serveur:', responseText);
                        
                        // Tenter de parser le JSON
                        let result;
                        try {
                            result = JSON.parse(responseText);
                        } catch (e) {
                            console.error('❌ Erreur parsing JSON:', e);
                            alert("❌ Le serveur n'a pas renvoyé du JSON valide. Réponse: " + responseText.substring(0, 200));
                            throw new Error('Réponse serveur invalide');
                        }
                        
                        console.log('📥 Réponse parsée:', result);
                        
                        if (result.success) {
                            console.log('✅ Œuvre envoyée au serveur:', result);
                            alert("✅ Succès : " + result.message);
                        } else {
                            console.warn('⚠️ Sauvegarde serveur échouée:', result.message);
                            alert("🛑 Erreur du serveur : " + result.message + "\n\nDonnées envoyées: " + JSON.stringify(dataToSend, null, 2));
                            showToast('⚠️ Œuvre publiée localement, mais pas sur le serveur : ' + result.message);
                        }
                    } catch (serverError) {
                        console.error('❌ Erreur serveur:', serverError);
                        alert("❌ VRAIE ERREUR JAVASCRIPT : " + serverError.message);
                        showToast('❌ Erreur réseau : ' + serverError.message);
                    }
                }
                
                closeArtworkModal();
                renderArtworks();
                updateDashboard();
                
                showToast('✅ Œuvre publiée et visible par tous !');
                
                // Rafraîchir la galerie publique
                if (typeof renderProducts === 'function') {
                    (typeof afficherOeuvresFiltrees === 'function' && window.toutesLesOeuvres?.length > 0) ? afficherOeuvresFiltrees() : (typeof chargerLaVraieGalerie === 'function' ? chargerLaVraieGalerie() : null);
                }
                
            } catch (error) {
                console.error('Erreur publication:', error);
                showToast('❌ Erreur: ' + error.message);
            }
        }

        function renderArtworks() {
            const c = document.getElementById('artworksGrid');
            if (!db.artworks.length) { c.innerHTML = '<p style="text-align:center;opacity:0.7;grid-column:1/-1;">Aucune œuvre. Commencez à créer votre portfolio !</p>'; return; }
            c.innerHTML = db.artworks.map(a => {
                // Determine what to display: photo or emoji
                let imageContent = '';
                if (a.photo) {
                    imageContent = `<img loading="lazy" src="${a.photo}" alt="${a.title}" style="width:100%;height:100%;object-fit:cover;">`;
                } else {
                    imageContent = `<span style="font-size:70px;">${a.emoji || '🎨'}</span>`;
                }
                
                return `
                <div class="artwork-card">
                    <div class="artwork-image">
                        ${imageContent}
                        <div class="artwork-status ${a.status}">${a.status==='published'?'Publiée':'En attente'}</div>
                    </div>
                    <div class="artwork-info">
                        <div class="artwork-title">${a.title}</div>
                        <div class="artwork-price">${formatPrice(a.price)}</div>
                        <div class="artwork-meta"><span>🏷️ ${a.category}</span><span>👁️ 0 vues</span></div>
                        <div class="artwork-actions">
                            <button class="btn-small btn-edit" onclick="openArtworkModal(${a.id})">✏️ Modifier</button>
                            <button class="btn-small btn-delete" onclick="deleteArtwork(${a.id})">🗑️ Supprimer</button>
                        </div>
                    </div>
                </div>`;
            }).join('');
        }

        function deleteArtwork(id) {
            if (!confirm('Supprimer cette œuvre de votre portfolio et de la galerie publique ?')) return;
            
            try {
                // Remove from public products first
                removeFromPublicProducts(id);
                
                // Then remove from artist's artworks
                db.deleteArtwork(id);
                
                showToast('✅ Œuvre supprimée de votre portfolio et de la galerie');
                renderArtworks();
                updateDashboard();
            } catch (error) {
                showToast('❌ ' + error.message);
                console.error('Erreur de suppression:', error);
            }
        }

        // ==================== PUBLIC PRODUCTS MANAGEMENT ====================
        function addToPublicProducts(artwork) {
            const products = getProducts();
            
            // Trouver le prochain ID disponible
            const maxId = products.length > 0 ? Math.max(...products.map(p => p.id)) : 0;
            
            const publicProduct = {
                id: maxId + 1,
                title: artwork.title,
                artist: artwork.artistName || 'Artiste',
                artistCountry: artwork.artistCountry || '',
                artistAvatar: artwork.artistAvatar || '',
                category: artwork.category,
                price: artwork.price,
                image: artwork.photo,
                photos: artwork.photos || [artwork.photo],
                description: artwork.description || '',
                dimensions: artwork.dimensions || null,
                technique: artwork.technique || null,
                techniqueCustom: artwork.techniqueCustom || null,
                createdAt: new Date().toISOString(),
                // Stocker l'ID de l'artiste pour lier les deux
                artistArtworkId: artwork.id
            };
            
            products.push(publicProduct);
            saveProducts(products);
            
            // Ajouter au storage partagé pour le monitoring en temps réel
            const artworkKey = `artwork:${publicProduct.id}`;
            window.storage.set(artworkKey, JSON.stringify(publicProduct), true).then(() => {
                console.log('✅ Œuvre ajoutée au storage partagé:', artworkKey);
            }).catch(err => {
                console.log('⚠️ Erreur ajout storage:', err);
            });
            
            console.log('✅ Œuvre ajoutée à la galerie publique:', publicProduct);
        }

        function updatePublicProduct(artistArtworkId, artwork) {
            const products = getProducts();
            const index = products.findIndex(p => p.artistArtworkId === artistArtworkId);
            
            if (index > -1) {
                products[index] = {
                    ...products[index],
                    title: artwork.title,
                    category: artwork.category,
                    price: artwork.price,
                    image: artwork.photo,
                    photos: artwork.photos || [artwork.photo],
                    description: artwork.description || '',
                    dimensions: artwork.dimensions || null,
                    technique: artwork.technique || null,
                    techniqueCustom: artwork.techniqueCustom || null
                };
                
                saveProducts(products);
            }
        }

        function removeFromPublicProducts(artistArtworkId) {
            const products = getProducts();
            const productToRemove = products.find(p => p.artistArtworkId === artistArtworkId);
            const filtered = products.filter(p => p.artistArtworkId !== artistArtworkId);
            
            if (filtered.length < products.length) {
                saveProducts(filtered);
                
                // Supprimer aussi du storage partagé
                if (productToRemove && productToRemove.id) {
                    const artworkKey = `artwork:${productToRemove.id}`;
                    window.storage.delete(artworkKey, true).then(() => {
                        console.log('✅ Œuvre supprimée du storage partagé:', artworkKey);
                    }).catch(err => {
                        console.log('⚠️ Erreur suppression storage:', err);
                    });
                }
            }
        }

        // ==================== SALES ====================
        function renderSales() {
            const tbody = document.getElementById('salesTableBody');
            if (!db.sales.length) { tbody.innerHTML='<tr><td colspan="5" style="text-align:center;opacity:0.7;">Aucune vente</td></tr>'; return; }
            tbody.innerHTML = db.sales.map(s => `<tr>
                <td>${new Date(s.date).toLocaleDateString('fr-FR')}</td>
                <td><strong>${s.artwork}</strong></td>
                <td>${s.client}</td>
                <td><strong>${formatPrice(s.price)}</strong></td>
                <td><span style="background:${s.status==='Livrée'?'rgba(76,175,80,0.3)':'rgba(255,193,7,0.3)'};padding:5px 12px;border-radius:12px;font-size:12px;">${s.status}</span></td>
            </tr>`).join('');
        }

        // ==================== ARTIST GALLERY ====================
        function renderArtistGallery() {
            const products = getProducts();
            const filtered = currentGalleryFilter === 'all' ? products : products.filter(p => p.category === currentGalleryFilter);
            document.getElementById('artistGalleryGrid').innerHTML = filtered.map(p => `
                <div class="artwork-card" onclick="artistViewProductDetail(${p.id})">
                    <div class="artwork-image"><img src="${p.image}" alt="${p.title}" style="width:100%;height:100%;object-fit:cover;" loading="lazy" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22400%22%3E%3Crect fill=%22%23ddd%22 width=%22400%22 height=%22400%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2248%22%3E🎨%3C/text%3E%3C/svg%3E'"></div>
                    <div class="artwork-info">
                        <div class="artwork-title">${p.title}</div>
                        <div style="font-size:13px;opacity:0.8;margin-bottom:8px;cursor:pointer;" onclick="artistViewArtistDetail(event,'${p.artist}')">par ${p.artist}</div>
                        <div class="artwork-price">${formatPrice(p.price)}</div>
                        <div class="artwork-meta"><span>🏷️ ${p.category}</span><span>${p.badge}</span></div>
                    </div>
                </div>`).join('');
        }

        function filterArtistGallery(cat, event) {
            currentGalleryFilter = cat;
            document.querySelectorAll('#artistGalleryFilters .filter-btn').forEach(b => b.classList.remove('active'));
            if (event && event.target) {
                event.target.classList.add('active');
            }
            renderArtistGallery();
        }

        // ==================== ARTISTE : PRODUCT DETAIL ====================
        function artistViewProductDetail(productId) {
            const allProducts = getProducts();
            const product = allProducts.find(p => p.id === productId);
            if (!product) return;
            document.getElementById('artistProductDetailContainer').innerHTML = `
                <div class="product-detail">
                    <div class="product-detail-grid">
                        <div class="product-detail-image"><img src="${product.image}" alt="${product.title}" style="width:100%;height:100%;object-fit:cover;border-radius:20px;" loading="lazy" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22400%22%3E%3Crect fill=%22%23ddd%22 width=%22400%22 height=%22400%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2248%22%3E🎨%3C/text%3E%3C/svg%3E'"></div>
                        <div class="product-detail-info">
                            <div class="product-detail-title">${product.title}</div>
                            <div class="product-detail-artist" onclick="artistViewArtistDetail(event,'${product.artist}')" style="cursor:pointer;">👨‍🎨 par ${product.artist}</div>
                            <div class="product-detail-price">${formatPrice(product.price)}</div>
                            <div class="product-detail-meta">
                                <div class="meta-item"><div class="meta-label">Catégorie</div><div class="meta-value">${product.category}</div></div>
                                <div class="meta-item"><div class="meta-label">Statut</div><div class="meta-value">${product.badge}</div></div>
                                <div class="meta-item"><div class="meta-label">Dimensions</div><div class="meta-value">80 × 60 cm</div></div>
                                <div class="meta-item"><div class="meta-label">Technique</div><div class="meta-value">Huile sur toile</div></div>
                            </div>
                            <div class="product-detail-description">
                                <h3 style="margin-bottom:10px;">Description</h3>
                                <p>Cette magnifique œuvre capture l'essence de l'art africain contemporain. Créée avec passion, elle représente ${product.title.toLowerCase()} à travers le regard unique de ${product.artist}.</p>
                            </div>
                            <div class="product-detail-actions">
                                <button class="btn-large btn-primary" onclick="showToast('Fonctionnalité disponible pour les clients')">🛒 Voir dans la boutique</button>
                                <button class="btn-large btn-secondary" onclick="showToast('Œuvre ajoutée à vos inspirations')">⭐ Inspirations</button>
                            </div>
                        </div>
                    </div>
                </div>`;
            document.querySelectorAll('.page-section').forEach(s => s.classList.remove('active'));
            document.getElementById('artistProductDetailSection').classList.add('active');
            window.scrollTo(0,0);
        }

        // ==================== ARTISTE : ARTIST DETAIL ====================
        function artistViewArtistDetail(event, artistName) {
            event.stopPropagation();
            if (!artistName) return;
            window.location.href = `profil.html?name=${encodeURIComponent(artistName)}`;
        }
        // ==================== INIT ====================
        // ==================== PULL TO REFRESH ====================
        let touchStartY = 0;
        let touchCurrentY = 0;
        let touchStartX = 0;
        let touchCurrentX = 0;
        let isPulling = false;
        let isRefreshing = false;
        let isSwiping = false;

        document.addEventListener('touchstart', function(e) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            
            // Check for pull to refresh (at top of page)
            if (window.scrollY === 0 && !isRefreshing) {
                isPulling = true;
            }
            
            // Check for swipe back (from left edge)
            if (touchStartX < 50 && currentHistoryIndex > 0) {
                isSwiping = true;
            }
        }, { passive: true });

        document.addEventListener('touchmove', function(e) {
            touchCurrentX = e.touches[0].clientX;
            touchCurrentY = e.touches[0].clientY;
            
            // Handle pull to refresh
            if (isPulling && !isRefreshing) {
                const pullDistance = touchCurrentY - touchStartY;
                
                if (pullDistance > 0 && pullDistance < 100) {
                    const pullToRefresh = document.getElementById('pullToRefresh');
                    pullToRefresh.classList.add('visible');
                    pullToRefresh.style.transform = `translateY(${Math.min(pullDistance - 80, 0)}px)`;
                }
            }
            
            // Handle swipe back gesture
            if (isSwiping) {
                const swipeDistance = touchCurrentX - touchStartX;
                if (swipeDistance > 50) {
                    // Show visual feedback for swipe
                    document.body.style.transform = `translateX(${Math.min(swipeDistance * 0.3, 100)}px)`;
                    document.body.style.transition = 'none';
                }
            }
        }, { passive: true });

        document.addEventListener('touchend', function(e) {
            // Handle pull to refresh
            if (isPulling && !isRefreshing) {
                const pullDistance = touchCurrentY - touchStartY;
                const pullToRefresh = document.getElementById('pullToRefresh');
                
                if (pullDistance > 80) {
                    refreshPage();
                } else {
                    pullToRefresh.classList.remove('visible');
                    pullToRefresh.style.transform = '';
                }
                
                isPulling = false;
            }
            
            // Handle swipe back
            if (isSwiping) {
                const swipeDistance = touchCurrentX - touchStartX;
                
                if (swipeDistance > 100) {
                    // Trigger back navigation
                    goBack();
                }
                
                // Reset body transform
                document.body.style.transition = 'transform 0.3s ease';
                document.body.style.transform = '';
                setTimeout(() => {
                    document.body.style.transition = '';
                }, 300);
                
                isSwiping = false;
            }
            
            touchStartX = 0;
            touchStartY = 0;
            touchCurrentX = 0;
            touchCurrentY = 0;
        }, { passive: true });

        function refreshPage() {
            if (isRefreshing) return;
            
            isRefreshing = true;
            const pullToRefresh = document.getElementById('pullToRefresh');
            pullToRefresh.classList.add('refreshing');
            
            // Simulate loading
            setTimeout(() => {
                // Refresh current page content
                const currentPage = document.querySelector('.page.active');
                if (currentPage && currentPage.id === 'homePage') {
                    (typeof afficherOeuvresFiltrees === 'function' && window.toutesLesOeuvres?.length > 0) ? afficherOeuvresFiltrees() : (typeof chargerLaVraieGalerie === 'function' ? chargerLaVraieGalerie() : null);
                } else if (currentPage && currentPage.id === 'favoritesPage') {
                    renderFavorites();
                } else if (currentPage && currentPage.id === 'cartPage') {
                    renderCart();
                } else if (currentPage && currentPage.id === 'artistDetailPage') {
                    // Recharger le profil artiste actuel
                    const artistNameElement = document.querySelector('.artist-detail-name');
                    if (artistNameElement) {
                        const artistName = artistNameElement.textContent.trim();
                        viewArtistDetail(artistName);
                    }
                }
                
                // Update badges
                updateBadges();
                renderNotifications();
                
                showToast('✅ Page actualisée');
                
                // Hide refresh indicator
                pullToRefresh.classList.remove('visible', 'refreshing');
                pullToRefresh.style.transform = '';
                isRefreshing = false;
            }, 1000);
        }

        function manualRefresh() {
            if (isRefreshing) return;
            
            const btn = event.target.closest('.icon-btn');
            btn.style.animation = 'spin 0.5s linear';
            
            setTimeout(() => {
                btn.style.animation = '';
            }, 500);
            
            showLoading();
            
            setTimeout(() => {
                // Refresh current page content
                const currentPage = document.querySelector('.page.active');
                if (currentPage && currentPage.id === 'homePage') {
                    (typeof afficherOeuvresFiltrees === 'function' && window.toutesLesOeuvres?.length > 0) ? afficherOeuvresFiltrees() : (typeof chargerLaVraieGalerie === 'function' ? chargerLaVraieGalerie() : null);
                } else if (currentPage && currentPage.id === 'favoritesPage') {
                    renderFavorites();
                } else if (currentPage && currentPage.id === 'cartPage') {
                    renderCart();
                }
                
                // Update badges and notifications
                updateBadges();
                renderNotifications();
                
                hideLoading();
                showToast('✅ Page actualisée');
            }, 600);
        }

        // ==================== NAVIGATION HISTORY ====================
        const navigationHistory = [];
        let currentHistoryIndex = -1;

        function updateNavigationHistory(page) {
            // Remove any forward history when navigating to a new page
            navigationHistory.splice(currentHistoryIndex + 1);
            navigationHistory.push(page);
            currentHistoryIndex = navigationHistory.length - 1;
            updateBackButton();
        }

        function updateBackButton() {
            const backButton = document.getElementById('backButton');
            if (currentHistoryIndex > 0) {
                backButton.classList.add('show');
            } else {
                backButton.classList.remove('show');
            }
        }

        function goBack() {
            if (currentHistoryIndex > 0) {
                currentHistoryIndex--;
                const previousPage = navigationHistory[currentHistoryIndex];
                navigateToWithoutHistory(previousPage);
                updateBackButton();
            }
        }

        function navigateToWithoutHistory(page) {
            document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
            const pageElement = document.getElementById(page + 'Page');
            if (pageElement) {
                pageElement.classList.add('active');
                
                // Refresh content based on page
                if (page === 'home') (typeof afficherOeuvresFiltrees === 'function' && window.toutesLesOeuvres?.length > 0) ? afficherOeuvresFiltrees() : (typeof chargerLaVraieGalerie === 'function' ? chargerLaVraieGalerie() : null);
                else if (page === 'favorites') renderFavorites();
                else if (page === 'cart') renderCart();
                
                window.scrollTo(0, 0);
            }
        }

        // ==================== LOADING OVERLAY ====================
        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('show');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('show');
        }

        // Override original navigateTo to use history
        const originalNavigateTo = navigateTo;
        navigateTo = function(page) {
            originalNavigateTo(page);
            updateNavigationHistory(page);
        };

        // ==================== ARTIST PROFILE MANAGEMENT ====================
        
        function openArtistEditModal() {
            // Get artist account data from memory
            const acc = safeStorage.get('arkyl_artist_account', null) || _memStore['arkyl_artist_account'] || null;
            
            if (!acc) {
                showToast('⚠️ Aucun compte artiste trouvé');
                return;
            }
            
            // Update profile preview
            const previewContainer = document.getElementById('profilePreviewContainer');
            if (acc.avatar && (acc.avatar.startsWith('http') || acc.avatar.startsWith('data:'))) {
                previewContainer.innerHTML = `<img loading="lazy" src="${acc.avatar}" class="current-profile-preview" alt="Photo de profil">`;
            } else {
                previewContainer.innerHTML = `<div class="profile-emoji-preview">${acc.avatar || '👤'}</div>`;
            }
            
            // Fill all form fields with existing data
            document.getElementById('editArtistName').value = acc.name || '';
            document.getElementById('editArtistEmail').value = acc.email || '';
            document.getElementById('editArtistPhone').value = acc.phone || '';
            document.getElementById('editArtistCountry').value = acc.country || '';
            document.getElementById('editArtistSpecialty').value = acc.specialty ? acc.specialty.join(', ') : '';
            document.getElementById('editArtistBio').value = acc.bio || '';
            document.getElementById('editArtistWebsite').value = acc.website || '';
            document.getElementById('editArtistSocial').value = acc.social || '';
            
            // Update character count for bio
            updateBioCharCount();
            
            // Show modal
            document.getElementById('artistEditModal').classList.add('show');
        }

        function updateBioCharCount() {
            const bioField = document.getElementById('editArtistBio');
            const charCount = document.getElementById('bioCharCount');
            if (bioField && charCount) {
                const length = bioField.value.length;
                const color = length >= 10 ? '#4CAF50' : length >= 5 ? '#FFC107' : '#F44336';
                charCount.textContent = `${length} / 10 caractères minimum`;
                charCount.style.color = color;
            }
        }

        function closeArtistEditModal() {
            document.getElementById('artistEditModal').classList.remove('show');
        }

        function handleProfileImageUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            // Validate file type
            if (!file.type.startsWith('image/')) {
                showToast('⚠️ Veuillez sélectionner une image valide');
                return;
            }

            // Validate file size (5MB max)
            if (file.size > 5 * 1024 * 1024) {
                showToast('⚠️ L\'image ne doit pas dépasser 5MB');
                return;
            }

            // Read and convert to base64
            const reader = new FileReader();
            reader.onload = function(e) {
                const base64Image = e.target.result;
                
                // Update preview
                const previewContainer = document.getElementById('profilePreviewContainer');
                previewContainer.innerHTML = `<img loading="lazy" src="${base64Image}" class="current-profile-preview" alt="Photo de profil">`;
                
                // Store in temporary variable (will be saved on "Enregistrer")
                window.tempProfileImage = base64Image;
                
                showToast('✅ Image chargée! Cliquez sur "Enregistrer" pour valider.');
            };
            reader.onerror = function() {
                showToast('❌ Erreur lors du chargement de l\'image');
            };
            reader.readAsDataURL(file);
        }

        function saveArtistProfile() {
            
            // Get current artist account
            const acc = safeStorage.get('arkyl_artist_account', null) || _memStore['arkyl_artist_account'] || null;
            
            console.log('🔍 DEBUG: Compte artiste récupéré:', acc);
            
            if (!acc) {
                console.error('❌ DEBUG: Aucun compte artiste trouvé');
                showToast('⚠️ Aucun compte artiste trouvé');
                return;
            }

            // Get all field values
            const name = document.getElementById('editArtistName').value.trim();
            const email = document.getElementById('editArtistEmail').value.trim();
            const phone = document.getElementById('editArtistPhone').value.trim();
            const country = document.getElementById('editArtistCountry').value;
            const specialty = document.getElementById('editArtistSpecialty').value.trim();
            const bio = document.getElementById('editArtistBio').value.trim();
            const website = document.getElementById('editArtistWebsite').value.trim();
            const social = document.getElementById('editArtistSocial').value.trim();

            console.log('🔍 DEBUG: Valeurs récupérées:', { name, email, phone, country, specialty, bio, website, social });

            // Validation
            if (!name || name.length < 2) {
                console.warn('⚠️ DEBUG: Nom invalide');
                showToast('⚠️ Veuillez entrer un nom valide (min. 2 caractères)');
                return;
            }
            if (!email || !email.includes('@')) {
                console.warn('⚠️ DEBUG: Email invalide');
                showToast('⚠️ Veuillez entrer un email valide');
                return;
            }
            if (!phone || phone.length < 8) {
                console.warn('⚠️ DEBUG: Téléphone invalide');
                showToast('⚠️ Veuillez entrer un numéro de téléphone valide');
                return;
            }
            if (!country) {
                console.warn('⚠️ DEBUG: Pays non sélectionné');
                showToast('⚠️ Veuillez sélectionner votre pays');
                return;
            }
            if (!specialty) {
                console.warn('⚠️ DEBUG: Spécialité manquante');
                showToast('⚠️ Veuillez renseigner votre spécialité');
                return;
            }
            if (!bio || bio.length < 10) {
                console.warn('⚠️ DEBUG: Biographie trop courte');
                showToast('⚠️ Veuillez renseigner une biographie (min. 10 caractères)');
                return;
            }

            // Update all artist account data
            acc.name = name;
            acc.email = email;
            acc.phone = phone;
            acc.country = country;
            acc.specialty = specialty.split(',').map(s => s.trim()).filter(s => s);
            acc.bio = bio;
            acc.website = website;
            acc.social = social;
            acc.updatedAt = new Date().toISOString();
            
            // Update profile image if a new one was uploaded
            if (window.tempProfileImage) {
                acc.avatar = window.tempProfileImage;
                window.tempProfileImage = null;
            }

            console.log('🔍 DEBUG: Objet compte avant sauvegarde:', acc);

            // Save to memory
            try {
                _memStore['arkyl_artist_account'] = acc;
                
                // Vérifier immédiatement après sauvegarde
                const verification = _memStore['arkyl_artist_account'];
                console.log('🔍 DEBUG: Vérification après sauvegarde:', verification);
                
                // Refresh the profile display (skip welcome toast)
                hydrateProfile(true);
                
                // Close modal
                closeArtistEditModal();
                
                // Show success message
                showToast('✅ Profil mis à jour avec succès!');
                
                // Add notification
                if (typeof addNotification === 'function') {
                    addNotification('Profil modifié', 'Vos informations ont été mises à jour avec succès.');
                }
            } catch (error) {
                console.error('❌ DEBUG: Erreur lors de la sauvegarde:', error);
                showToast('❌ Erreur lors de la sauvegarde du profil');
            }
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('artistEditModal');
            if (event.target === modal) {
                closeArtistEditModal();
            }
        });

        // ==================== NEWS MANAGEMENT (ADMIN) ====================
        
        // ========== ACTUALITÉS : stockage côté serveur (partagé entre tous les utilisateurs) ==========
        const NEWS_API = 'https://arkyl-galerie.onrender.com/api_news.php';
        let newsItems = [];

        async function fetchNewsFromServer() {
            try {
                const res = await fetch(NEWS_API + '?action=get&t=' + Date.now());
                const data = await res.json();
                if (data.success && Array.isArray(data.news)) {
                    // Normaliser is_image → isImage (colonne SQL snake_case)
                    newsItems = data.news.map(n => ({
                        ...n,
                        isImage: n.isImage ?? (n.is_image == 1)
                    }));
                    return true;
                }
            } catch(e) {
                console.warn('⚠️ Impossible de charger les actualités:', e);
            }
            return false;
        }

        async function saveNewsToServer(action, payload) {
            // L'API PHP lit l'action depuis ?action= dans l'URL
            try {
                const res = await fetch(NEWS_API + '?action=' + action, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    // Recharger la liste complète depuis le serveur pour rester en sync
                    await fetchNewsFromServer();
                }
                return data;
            } catch(e) {
                console.error('❌ Erreur API news:', e);
                showToast('❌ Erreur de connexion au serveur');
                return { success: false };
            }
        }

        function renderNewsTicker() {
            const container = document.querySelector('.news-ticker-content');
            if (!container) return;

            // Duplicate items for seamless loop
            const duplicatedNews = [...newsItems, ...newsItems];
            
            container.innerHTML = duplicatedNews.map((news, index) => {
                const originalIndex = index % newsItems.length;
                const iconHTML = news.isImage 
                    ? `<img loading="lazy" src="${news.icon}" alt="Affiche" onerror="this.style.display='none'; this.parentElement.innerHTML='📰';">`
                    : news.icon;
                
                return `
                    <div class="news-ticker-item" onclick="openNewsLightbox(${originalIndex})">
                        <div class="news-ticker-icon ${news.gradient}">
                            ${iconHTML}
                        </div>
                        <span class="news-ticker-text">${news.text}</span>
                    </div>
                `;
            }).join('');
        }

        function renderNewsList() {
            const container = document.getElementById('newsListContainer');
            if (!container) return;

            if (newsItems.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 60px 20px; background: rgba(255,255,255,0.1); border-radius: 20px;">
                        <div style="font-size: 60px; margin-bottom: 20px;">📰</div>
                        <p style="font-size: 18px; opacity: 0.8;">Aucune actualité pour le moment</p>
                        <p style="font-size: 14px; opacity: 0.6; margin-top: 10px;">Cliquez sur "Ajouter une actualité" pour commencer</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = newsItems.map((news, index) => {
                const iconDisplay = news.isImage 
                    ? `<img loading="lazy" src="${news.icon}" style="width: 50px; height: 50px; border-radius: 10px; object-fit: cover;" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2250%22 height=%2250%22%3E%3Crect fill=%22%23ddd%22 width=%2250%22 height=%2250%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2230%22%3E📰%3C/text%3E%3C/svg%3E';">`
                    : `<div style="font-size: 40px;">${news.icon}</div>`;

                return `
                    <div style="background: rgba(255,255,255,0.12); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.2); border-radius: 16px; padding: 20px; margin-bottom: 15px; display: flex; align-items: center; gap: 20px;">
                        <div class="news-ticker-icon ${news.gradient}" style="width: 60px; height: 60px; flex-shrink: 0;">
                            ${iconDisplay}
                        </div>
                        <div style="flex: 1;">
                            <div style="font-size: 15px; font-weight: 600; margin-bottom: 5px;">${news.text}</div>
                            <div style="font-size: 12px; opacity: 0.7;">Gradient: ${news.gradient} ${news.isImage ? '· Image' : '· Emoji'}</div>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button onclick="editNews(${news.id})" style="background: rgba(66, 135, 245, 0.2); border: 1px solid rgba(66, 135, 245, 0.4); color: white; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600;">
                                ✏️ Modifier
                            </button>
                            <button onclick="deleteNews(${news.id})" style="background: rgba(245, 66, 66, 0.2); border: 1px solid rgba(245, 66, 66, 0.4); color: white; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600;">
                                🗑️ Supprimer
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function openAddNewsModal() {
            document.getElementById('newsModalTitle').textContent = '➕ Nouvelle Actualité';
            document.getElementById('newsIcon').value = '';
            document.getElementById('newsImageUpload').value = '';
            document.getElementById('newsGradient').value = 'gradient-1';
            document.getElementById('newsText').value = '';
            document.getElementById('newsEditIndex').value = '';
            document.getElementById('newsImagePreview').style.display = 'none';
            document.getElementById('newsModal').classList.add('show');
        }

        // Gestion de l'upload d'image
        document.addEventListener('DOMContentLoaded', function() {
            const imageUpload = document.getElementById('newsImageUpload');
            const urlInput = document.getElementById('newsIcon');
            const previewContainer = document.getElementById('newsImagePreview');
            const previewImg = document.getElementById('newsPreviewImg');

            // Quand on upload une image
            if (imageUpload) {
                imageUpload.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file && file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(event) {
                            const base64Image = event.target.result;
                            // Mettre l'image en base64 dans le champ URL
                            urlInput.value = base64Image;
                            // Afficher la prévisualisation
                            previewImg.src = base64Image;
                            previewContainer.style.display = 'block';
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            // Prévisualisation pour URL
            if (urlInput) {
                urlInput.addEventListener('input', function() {
                    const value = this.value.trim();
                    if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('data:image')) {
                        previewImg.src = value;
                        previewContainer.style.display = 'block';
                    } else {
                        previewContainer.style.display = 'none';
                    }
                });
            }
        });

        function editNews(id) {
            const news = newsItems.find(n => n.id === id);
            if (!news) return;
            document.getElementById('newsModalTitle').textContent = '✏️ Modifier l\'Actualité';
            document.getElementById('newsIcon').value = news.icon;
            document.getElementById('newsImageUpload').value = ''; // Reset file input
            document.getElementById('newsGradient').value = news.gradient;
            document.getElementById('newsText').value = news.text;
            document.getElementById('newsEditIndex').value = id; // ID serveur
            
            // Afficher la prévisualisation si c'est une image
            const previewContainer = document.getElementById('newsImagePreview');
            const previewImg = document.getElementById('newsPreviewImg');
            if (news.isImage) {
                previewImg.src = news.icon;
                previewContainer.style.display = 'block';
            } else {
                previewContainer.style.display = 'none';
            }
            
            document.getElementById('newsModal').classList.add('show');
        }

        function closeNewsModal() {
            document.getElementById('newsModal').classList.remove('show');
        }

        async function saveNews() {
            const icon = document.getElementById('newsIcon').value.trim();
            const gradient = document.getElementById('newsGradient').value;
            const text = document.getElementById('newsText').value.trim();
            const editId = document.getElementById('newsEditIndex').value; // contient l'ID serveur ou ''

            if (!icon || !text) {
                showToast('⚠️ Veuillez remplir tous les champs');
                return;
            }

            const isImage = icon.startsWith('http://') || icon.startsWith('https://') || icon.startsWith('data:image');
            const payload = { icon, gradient, text, isImage };

            let result;
            if (editId !== '') {
                // Mode édition : on envoie l'ID serveur
                result = await saveNewsToServer('update', { id: parseInt(editId), ...payload });
                if (result.success) showToast('✅ Actualité modifiée avec succès!');
            } else {
                // Mode ajout
                result = await saveNewsToServer('add', payload);
                if (result.success) showToast('✅ Actualité ajoutée avec succès!');
            }

            if (result.success) {
                renderNewsTicker();
                renderNewsList();
                closeNewsModal();
            }
        }

        async function deleteNews(id) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cette actualité ?')) return;

            const result = await saveNewsToServer('delete', { id });
            if (result.success) {
                renderNewsTicker();
                renderNewsList();
                showToast('✅ Actualité supprimée');
            }
        }

        // ==================== IMAGE LIGHTBOX (plein écran) ====================
        function openImageLightbox(src) {
            let lb = document.getElementById('imageLightboxOverlay');
            if (!lb) {
                lb = document.createElement('div');
                lb.id = 'imageLightboxOverlay';
                lb.style.cssText = `
                    position: fixed; inset: 0; z-index: 99999;
                    background: rgba(0,0,0,0.92);
                    display: flex; align-items: center; justify-content: center;
                    cursor: zoom-out;
                    animation: fadeInLb 0.2s ease;
                `;
                lb.innerHTML = `
                    <style>
                        @keyframes fadeInLb { from { opacity:0; } to { opacity:1; } }
                        @keyframes zoomInLb { from { transform: scale(0.85); opacity:0; } to { transform: scale(1); opacity:1; } }
                        #imageLightboxImg {
                            max-width: 92vw; max-height: 92vh;
                            object-fit: contain; border-radius: 12px;
                            box-shadow: 0 30px 80px rgba(0,0,0,0.6);
                            animation: zoomInLb 0.25s cubic-bezier(0.34,1.56,0.64,1);
                            cursor: default;
                        }
                        #imageLightboxClose {
                            position: fixed; top: 20px; right: 24px;
                            background: rgba(255,255,255,0.15); border: none;
                            color: white; font-size: 28px; width: 48px; height: 48px;
                            border-radius: 50%; cursor: pointer; display: flex;
                            align-items: center; justify-content: center;
                            transition: background 0.2s;
                            backdrop-filter: blur(4px);
                        }
                        #imageLightboxClose:hover { background: rgba(255,255,255,0.3); }
                    </style>
                    <button id="imageLightboxClose" onclick="event.stopPropagation(); closeImageLightbox();">✕</button>
                    <img loading="lazy" id="imageLightboxImg" src="" alt="Vue agrandie">
                `;
                lb.addEventListener('click', closeImageLightbox);
                lb.querySelector('#imageLightboxImg').addEventListener('click', e => e.stopPropagation());
                document.body.appendChild(lb);
                // Fermer avec Echap
                document.addEventListener('keydown', _lbKeyHandler);
            }
            document.getElementById('imageLightboxImg').src = src;
            lb.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeImageLightbox() {
            const lb = document.getElementById('imageLightboxOverlay');
            if (lb) lb.style.display = 'none';
            document.body.style.overflow = '';
        }

        function _lbKeyHandler(e) {
            if (e.key === 'Escape') closeImageLightbox();
        }

        // ==================== NEWS LIGHTBOX FUNCTIONS ====================
        function openNewsLightbox(index) {
            const news = newsItems[index];
            if (!news) return;

            const lightbox = document.getElementById('newsLightbox');
            const imageContainer = document.getElementById('newsLightboxImage');
            const title = document.getElementById('newsLightboxTitle');
            const gradientName = document.getElementById('newsLightboxGradientName');
            const gradientPreview = document.getElementById('newsLightboxGradientPreview');
            const description = document.getElementById('newsLightboxDescription');

            // Set title
            title.textContent = news.text;

            // Set image or emoji — utilise un <img loading="lazy"> pour voir l'image complète
            if (news.isImage) {
                imageContainer.classList.remove('emoji-display');
                imageContainer.style.backgroundImage = 'none';
                imageContainer.innerHTML = `<img loading="lazy" class="lightbox-img" src="${news.icon}" alt="${news.text}" onerror="this.parentElement.innerHTML='📰'">`;
            } else {
                imageContainer.classList.add('emoji-display');
                imageContainer.style.backgroundImage = 'none';
                imageContainer.innerHTML = news.icon;
            }

            // Set gradient info
            const gradientNames = {
                'gradient-1': 'Bronze-Cuivre',
                'gradient-2': 'Terre-Argile',
                'gradient-3': 'Or-Doré',
                'gradient-4': 'Cuivre-Sable',
                'gradient-5': 'Bronze-Sable'
            };
            gradientName.textContent = gradientNames[news.gradient] || news.gradient;

            // Apply gradient preview
            gradientPreview.className = `news-lightbox-gradient-preview news-ticker-icon ${news.gradient}`;

            // Set description (same as text for now, you can enhance this)
            description.textContent = news.text;

            // Show lightbox
            lightbox.classList.add('active');
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        }

        function closeNewsLightbox(event) {
            // Close only if clicking on background or close button
            if (event && event.target.closest('.news-lightbox-content') && !event.target.classList.contains('news-lightbox-close')) {
                return;
            }

            const lightbox = document.getElementById('newsLightbox');
            lightbox.classList.remove('active');
            document.body.style.overflow = ''; // Restore scrolling
        }

        // Close lightbox with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeNewsLightbox();
            }
        });

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('newsModal');
            if (event.target === modal) {
                closeNewsModal();
            }
        });

        // ==================== AVATAR UPLOAD HANDLERS ====================
        function handleAvatarUpload(event, context) {
            const file = event.target.files[0];
            if (!file) return;

            // Validate file type
            if (!file.type.startsWith('image/')) {
                showToast('❌ Veuillez sélectionner une image valide');
                return;
            }

            // Validate file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                showToast('❌ L\'image ne doit pas dépasser 5MB');
                return;
            }

            // Read and display the image
            const reader = new FileReader();
            reader.onload = function(e) {
                const imgId = context === 'reg' ? 'regAvatarImg' : 'editAvatarImg';
                const imgElement = document.getElementById(imgId);
                if (imgElement) {
                    imgElement.src = e.target.result;
                    showToast('✅ Photo importée avec succès!');
                }
            };
            reader.readAsDataURL(file);
        }

        function selectPresetAvatar(src, context) {
            const imgId = context === 'reg' ? 'regAvatarImg' : 'editAvatarImg';
            const imgElement = document.getElementById(imgId);
            if (imgElement) {
                imgElement.src = src;
            }
            
            // Visual feedback on preset avatars
            const container = event.target.closest('.preset-avatars');
            if (container) {
                container.querySelectorAll('.preset-avatar').forEach(img => {
                    img.classList.remove('selected');
                });
                event.target.classList.add('selected');
            }
        }

        function selectPresetAvatarEdit(src) {
            const previewContainer = document.getElementById('profilePreviewContainer');
            previewContainer.innerHTML = `<img loading="lazy" src="${src}" class="current-profile-preview" alt="Photo de profil">`;
            window.tempProfileImage = src;
            
            // Visual feedback
            const container = event.target.closest('.preset-avatars');
            if (container) {
                container.querySelectorAll('.preset-avatar').forEach(img => {
                    img.classList.remove('selected');
                });
                event.target.classList.add('selected');
            }
            
            showToast('✅ Avatar sélectionné! Cliquez sur "Enregistrer" pour valider.');
        }

        // ==================== INIT ====================
        function init() {
            (typeof afficherOeuvresFiltrees === 'function' && window.toutesLesOeuvres?.length > 0) ? afficherOeuvresFiltrees() : (typeof chargerLaVraieGalerie === 'function' ? chargerLaVraieGalerie() : null);
            updateBadges();
            renderNotifications();
            updateAuthUI(); // Initialize authentication UI
            
            // Initialiser Google Sign-In après le chargement complet
            if (document.readyState === 'complete') {
                initializeGoogleSignIn();
            } else {
                window.addEventListener('load', initializeGoogleSignIn);
            }
            
            // Charger les actualités depuis le serveur (partagées entre tous les utilisateurs)
            fetchNewsFromServer().then(() => {
                renderNewsTicker();
                renderNewsList();
            });
            
            // Restaurer la dernière page active si elle existe
            const lastPage = safeStorage.get('arkyl_last_page', null);
            let startPage = 'home';
            
            // Vérifier si la sauvegarde est récente (moins de 5 secondes = rechargement)
            if (lastPage && (Date.now() - lastPage.timestamp) < 5000) {
                startPage = lastPage.pageId.replace('Page', ''); // artistDetailPage → artistDetail
                
                // Restaurer le contexte de la page
                setTimeout(() => {
                    if (lastPage.artistName && startPage === 'artistDetail') {
                        viewArtistDetail(lastPage.artistName);
                    } else if (lastPage.productId && startPage === 'productDetail') {
                        viewProductDetail(lastPage.productId);
                    } else {
                        navigateTo(startPage);
                    }
                }, 100);
                
                // Effacer la sauvegarde pour ne pas la restaurer indéfiniment
                safeStorage.remove('arkyl_last_page');
            } else {
                // Initialize navigation history normalement
                updateNavigationHistory(startPage);
            }
            
            // Show welcome message based on auth status
            if (currentUser) {
                if (currentUser.isAdmin) {
                    showToast(`👋 Bienvenue Admin ${currentUser.name}!`);
                } else {
                    showToast(`👋 Bienvenue ${currentUser.name}!`);
                }
            } else {
                showToast('Bienvenue sur ARKYL! 🎨');
            }
        }

        init();
    
    

/* ============================
   BLOC JS SUIVANT
   ============================ */


    // ==================== FONCTION POUR VOIR LES DÉTAILS D'UNE ŒUVRE DEPUIS L'API ====================
    async function viewProductDetailFromAPI(artworkId) {
        showLoading();
        
        try {
            // Charger les détails depuis l'API
            const response = await fetch(`https://arkyl-galerie.onrender.com/api_galerie_publique.php?artwork_id=${artworkId}`);
            const result = await response.json();
            
            if (!result.success || !result.data) {
                hideLoading();
                showToast('❌ Impossible de charger les détails de l\'œuvre');
                return;
            }
            
            const product = result.data;
            
            // Récupérer toutes les photos
            const photos = product.photos && product.photos.length > 0 
                ? product.photos 
                : (product.image_url ? [product.image_url] : []);
            
            // Créer le carrousel d'images si plusieurs photos
            let imageSection = '';
            if (photos.length > 1) {
                imageSection = `
                    <div class="product-detail-image" style="position: relative;">
                        <img id="mainProductImage" src="${photos[0]}" alt="${product.title}" style="width:100%;height:100%;object-fit:cover;border-radius:20px;cursor:pointer;" loading="lazy" onclick="openImageLightbox('${photos[0]}')" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22400%22%3E%3Crect fill=%22%23ddd%22 width=%22400%22 height=%22400%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2248%22%3E🎨%3C/text%3E%3C/svg%3E'">
                        
                        <div style="position: absolute; top: 15px; left: 15px; background: rgba(0,0,0,0.7); color: white; padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 600;">
                            <span id="currentPhotoIndex">1</span>/${photos.length}
                        </div>
                        
                        <button class="like-button" onclick="toggleFavorite(event, ${product.id})" style="position:absolute;top:15px;right:15px;">🤍</button>
                        
                        ${photos.length > 1 ? `
                            <button onclick="previousPhoto()" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.7); color: white; border: none; width: 45px; height: 45px; border-radius: 50%; cursor: pointer; font-size: 20px; display: flex; align-items: center; justify-content: center;">‹</button>
                            <button onclick="nextPhoto()" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.7); color: white; border: none; width: 45px; height: 45px; border-radius: 50%; cursor: pointer; font-size: 20px; display: flex; align-items: center; justify-content: center;">›</button>
                        ` : ''}
                        
                        <div style="display: flex; gap: 10px; margin-top: 15px; overflow-x: auto; padding: 5px 0;">
                            ${photos.map((photo, index) => `
                                <div onclick="changeMainPhoto(${index})" style="width: 80px; height: 80px; border-radius: 12px; overflow: hidden; cursor: pointer; border: 3px solid ${index === 0 ? 'rgba(212, 165, 116, 0.8)' : 'rgba(255,255,255,0.3)'};" class="thumbnail-photo" data-index="${index}">
                                    <img loading="lazy" src="${photo}" alt="Photo ${index + 1}" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            } else {
                imageSection = `
                    <div class="product-detail-image">
                        <img src="${photos[0] || product.image_url}" alt="${product.title}" style="width:100%;height:100%;object-fit:cover;border-radius:20px;cursor:pointer;" loading="lazy" onclick="openImageLightbox('${photos[0] || product.image_url}')" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22400%22%3E%3Crect fill=%22%23ddd%22 width=%22400%22 height=%22400%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2248%22%3E🎨%3C/text%3E%3C/svg%3E'">
                        <button class="like-button" onclick="toggleFavorite(event, ${product.id})" style="position:absolute;top:20px;right:20px;">🤍</button>
                    </div>
                `;
            }
            
            // Créer les métadonnées avec TOUTES les informations
            let dimensionsText = 'Non spécifiées';
            // Parser les dimensions : depuis PostgreSQL c arrive comme string JSON
            let dims = product.dimensions;
            if (dims && typeof dims === 'string') {
                try { dims = JSON.parse(dims); } catch(e) { dims = null; }
            }
            if (dims && (dims.width || dims.height)) {
                const parts = [];
                if (dims.width) parts.push(`L ${dims.width} cm`);
                if (dims.height) parts.push(`H ${dims.height} cm`);
                dimensionsText = parts.join(' × ');
            }
            
            let techniqueText = product.technique || product.techniqueCustom || 'Non spécifiée';
            
            // Pays de l'artiste avec drapeau
            let artistCountryText = '';
            if (product.artist_country) {
                const countryFlags = {
                    'CI': '🇨🇮',
                    'SN': '🇸🇳', 
                    'ML': '🇲🇱',
                    'BJ': '🇧🇯',
                    'BF': '🇧🇫',
                    'TG': '🇹🇬',
                    'GH': '🇬🇭',
                    'NG': '🇳🇬',
                    'CM': '🇨🇲',
                    'CD': '🇨🇩',
                    'FR': '🇫🇷'
                };
                const flag = countryFlags[product.artist_country] || '🌍';
                artistCountryText = `${flag} ${product.artist_country}`;
            } else {
                artistCountryText = 'Non spécifié';
            }
            
            // Gérer les valeurs undefined
            const artistName = product.artist_name || product.artist || 'Artiste inconnu';
            const title = product.title || 'Sans titre';
            const price = product.price || 0;
            const category = product.category || 'Non spécifiée';
            const description = product.description || 'Aucune description disponible.';
            
            const container = document.getElementById('productDetailContainer');
            container.innerHTML = `
                <div class="product-detail">
                    <div class="product-detail-grid">
                        ${imageSection}
                        <div class="product-detail-info">
                            <div class="product-detail-title">${title}</div>
                            <div class="product-detail-artist" onclick="viewArtistDetail(event, '${artistName}')">
                                👨‍🎨 par ${artistName}
                            </div>
                            <div class="product-detail-price">${price} FCFA</div>
                        
                        <div class="product-detail-meta">
                            <div class="meta-item">
                                <div class="meta-label">Catégorie</div>
                                <div class="meta-value">${category}</div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Pays de l'artiste</div>
                                <div class="meta-value">${artistCountryText}</div>
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
                            <div class="description-title">Description</div>
                            <div class="description-text">${description}</div>
                        </div>

                        <div class="product-detail-actions">
                            <button class="action-btn primary-btn" onclick="addToCart(event, ${product.id})">
                                🛒 Ajouter au panier
                            </button>
                            <button class="action-btn secondary-btn" onclick="toggleFavorite(event, ${product.id})">
                                🤍 Ajouter aux favoris
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            window.currentProductPhotos = photos;
            window.currentPhotoIndex = 0;
            window.currentProductId = product.id;
            
            navigateTo('productDetail');
            hideLoading();
            
        } catch (error) {
            console.error('Erreur chargement détails:', error);
            hideLoading();
            showToast('❌ Erreur lors du chargement des détails');
        }
    }

    // ==================== CHARGEMENT DE LA GALERIE ====================
    document.addEventListener('DOMContentLoaded', chargerLaVraieGalerie);

    async function chargerLaVraieGalerie() {
        const grille = document.getElementById('productsContainer'); 
        if (!grille) return; 

        try {
            // L'adresse COMPLÈTE et ABSOLUE de ton serveur API
            const urlAPI = 'https://arkyl-galerie.onrender.com/api_galerie_publique.php?t=' + Date.now();
            
            const reponse = await fetch(urlAPI);
            const resultat = await reponse.json();

            grille.innerHTML = ''; // On vide le texte de chargement

            if (resultat.success && resultat.data.length > 0) {
                window.toutesLesOeuvres = resultat.data;
                afficherOeuvresFiltrees();
            } else {
                grille.innerHTML = '<p style="text-align:center; width:100%;">La galerie est vide pour le moment.</p>';
            }

        } catch (erreur) {
            console.error("Erreur de communication :", erreur);
            grille.innerHTML = '<p style="color:red; text-align:center;">Serveur injoignable pour le moment.</p>';
        }
    }

    // Stockage global des œuvres
    window.toutesLesOeuvres = [];

    window.afficherOeuvresFiltrees = function() {
        const grille = document.getElementById('productsContainer');
        if (!grille) return;
        const cat = window.currentCategory || 'all';
        const selected = window.selectedCategories;
        let oeuvres;
        if (cat === '__multi__' && selected && selected.size > 0) {
            oeuvres = window.toutesLesOeuvres.filter(o => {
                const c = (o.category || o.categorie || o.type || '').toLowerCase().trim();
                return Array.from(selected).some(s => s.toLowerCase().trim() === c);
            });
        } else {
            oeuvres = cat === 'all'
                ? window.toutesLesOeuvres
                : window.toutesLesOeuvres.filter(o => {
                    const c = (o.category || o.categorie || o.type || '').toLowerCase().trim();
                    return c === cat.toLowerCase().trim();
                  });
        }
        grille.innerHTML = '';
        if (oeuvres.length === 0) {
            grille.innerHTML = '<p style="text-align:center;width:100%;opacity:0.7;padding:40px;">Aucune œuvre dans cette catégorie.</p>';
            return;
        }
        oeuvres.forEach((oeuvre, index) => {
                    // Gérer les photos multiples
                    const photos = oeuvre.photos && Array.isArray(oeuvre.photos) && oeuvre.photos.length > 0
                        ? oeuvre.photos
                        : [oeuvre.image_url];
                    
                    // Si plusieurs photos, créer un carrousel
                    let imageHTML = '';
                    if (photos.length > 1) {
                        imageHTML = `
                            <div class="product-image" style="position: relative;">
                                <span class="product-badge">${oeuvre.badge || 'Disponible'}</span>
                                <button class="like-button" onclick="toggleFavorite(event, ${oeuvre.id})">🤍</button>
                                
                                <!-- Conteneur du carrousel -->
                                <div style="position: relative; width: 100%; height: 100%; overflow: hidden;">
                                    ${photos.map((photo, photoIndex) => `
                                        <img 
                                            id="card-img-${oeuvre.id}-${photoIndex}"
                                            src="${photo}" 
                                            alt="${oeuvre.title}" 
                                            style="width:100%;height:100%;object-fit:cover;border-radius:20px;position:absolute;top:0;left:0;transition:opacity 0.3s ease;opacity:${photoIndex === 0 ? '1' : '0'};" 
                                            loading="lazy" 
                                            onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22400%22%3E%3Crect fill=%22%23ddd%22 width=%22400%22 height=%22400%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2248%22%3E🎨%3C/text%3E%3C/svg%3E'">
                                    `).join('')}
                                </div>
                                
                                <!-- Indicateur de photos -->
                                <div id="card-indicator-${oeuvre.id}" style="position: absolute; bottom: 10px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.7); color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; z-index: 2;">
                                    1/${photos.length}
                                </div>
                                
                                <!-- Boutons de navigation (apparaissent au survol) -->
                                <button 
                                    onclick="event.stopPropagation(); previousCardPhoto(${oeuvre.id}, ${photos.length})"
                                    style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.6); color: white; border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-size: 18px; display: none; align-items: center; justify-content: center; z-index: 3; transition: all 0.2s;"
                                    class="card-nav-btn card-nav-prev-${oeuvre.id}"
                                    onmouseover="this.style.background='rgba(0,0,0,0.8)'; this.style.transform='translateY(-50%) scale(1.1)'"
                                    onmouseout="this.style.background='rgba(0,0,0,0.6)'; this.style.transform='translateY(-50%) scale(1)'">
                                    ‹
                                </button>
                                <button 
                                    onclick="event.stopPropagation(); nextCardPhoto(${oeuvre.id}, ${photos.length})"
                                    style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.6); color: white; border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-size: 18px; display: none; align-items: center; justify-content: center; z-index: 3; transition: all 0.2s;"
                                    class="card-nav-btn card-nav-next-${oeuvre.id}"
                                    onmouseover="this.style.background='rgba(0,0,0,0.8)'; this.style.transform='translateY(-50%) scale(1.1)'"
                                    onmouseout="this.style.background='rgba(0,0,0,0.6)'; this.style.transform='translateY(-50%) scale(1)'">
                                    ›
                                </button>
                            </div>
                        `;
                    } else {
                        // Une seule photo - affichage classique
                        imageHTML = `
                            <div class="product-image">
                                <span class="product-badge">${oeuvre.badge || 'Disponible'}</span>
                                <button class="like-button" onclick="toggleFavorite(event, ${oeuvre.id})">🤍</button>
                                <img src="${photos[0]}" alt="${oeuvre.title}" style="width:100%;height:100%;object-fit:cover;border-radius:20px;" loading="lazy" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22400%22%3E%3Crect fill=%22%23ddd%22 width=%22400%22 height=%22400%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2248%22%3E🎨%3C/text%3E%3C/svg%3E'">
                            </div>
                        `;
                    }
                    
                    // Gérer les valeurs undefined
                    const artistName = oeuvre.artist_name || oeuvre.artist || 'Artiste inconnu';
                    const title = oeuvre.title || 'Sans titre';
                    const price = oeuvre.price || 0;
                    
                    const carte = `
                        <div class="product-card" 
                             onclick="viewProductDetailFromAPI(${oeuvre.id})"
                             onmouseenter="showCardNavButtons(${oeuvre.id})"
                             onmouseleave="hideCardNavButtons(${oeuvre.id})">
                            ${imageHTML}
                            <div class="product-info">
                                <div class="product-title">${title}</div>
                                <div class="product-artist" onclick="viewArtistDetail(event, '${artistName}')">par ${artistName}</div>
                                <div class="product-footer">
                                    <div class="product-price">${price} FCFA</div>
                                    <button class="add-cart-btn" onclick="addToCart(event, ${oeuvre.id})">+ Panier</button>
                                </div>
                            </div>
                        </div>
                    `;
                    grille.innerHTML += carte;
                });
    }

    // ==================== FONCTIONS DE CARROUSEL POUR LES CARTES ====================
    
    // Stocker l'index actuel pour chaque carte
    window.cardPhotoIndexes = {};
    
    function showCardNavButtons(cardId) {
        const prevBtn = document.querySelector(`.card-nav-prev-${cardId}`);
        const nextBtn = document.querySelector(`.card-nav-next-${cardId}`);
        if (prevBtn) prevBtn.style.display = 'flex';
        if (nextBtn) nextBtn.style.display = 'flex';
    }
    
    function hideCardNavButtons(cardId) {
        const prevBtn = document.querySelector(`.card-nav-prev-${cardId}`);
        const nextBtn = document.querySelector(`.card-nav-next-${cardId}`);
        if (prevBtn) prevBtn.style.display = 'none';
        if (nextBtn) nextBtn.style.display = 'none';
    }
    
    function nextCardPhoto(cardId, totalPhotos) {
        if (!window.cardPhotoIndexes[cardId]) {
            window.cardPhotoIndexes[cardId] = 0;
        }
        
        const currentIndex = window.cardPhotoIndexes[cardId];
        const nextIndex = (currentIndex + 1) % totalPhotos;
        
        // Cacher l'image actuelle
        const currentImg = document.getElementById(`card-img-${cardId}-${currentIndex}`);
        if (currentImg) currentImg.style.opacity = '0';
        
        // Afficher la nouvelle image
        const nextImg = document.getElementById(`card-img-${cardId}-${nextIndex}`);
        if (nextImg) nextImg.style.opacity = '1';
        
        // Mettre à jour l'indicateur
        const indicator = document.getElementById(`card-indicator-${cardId}`);
        if (indicator) indicator.textContent = `${nextIndex + 1}/${totalPhotos}`;
        
        // Sauvegarder le nouvel index
        window.cardPhotoIndexes[cardId] = nextIndex;
    }
    
    function previousCardPhoto(cardId, totalPhotos) {
        if (!window.cardPhotoIndexes[cardId]) {
            window.cardPhotoIndexes[cardId] = 0;
        }
        
        const currentIndex = window.cardPhotoIndexes[cardId];
        const prevIndex = (currentIndex - 1 + totalPhotos) % totalPhotos;
        
        // Cacher l'image actuelle
        const currentImg = document.getElementById(`card-img-${cardId}-${currentIndex}`);
        if (currentImg) currentImg.style.opacity = '0';
        
        // Afficher la nouvelle image
        const prevImg = document.getElementById(`card-img-${cardId}-${prevIndex}`);
        if (prevImg) prevImg.style.opacity = '1';
        
        // Mettre à jour l'indicateur
        const indicator = document.getElementById(`card-indicator-${cardId}`);
        if (indicator) indicator.textContent = `${prevIndex + 1}/${totalPhotos}`;
        
        // Sauvegarder le nouvel index
        window.cardPhotoIndexes[cardId] = prevIndex;
    }



/* ============================
   BLOC JS SUIVANT
   ============================ */


        // ==================== TICKER NAVIGATION ====================
        (function() {
            let tickerPaused = false;
            let tickerOffset = 0;          // px décalage manuel
            const STEP = 320;              // px par clic

            function getContent() { return document.getElementById('newsTickerContent'); }

            function applyOffset(delta) {
                const c = getContent();
                if (!c) return;
                c.classList.add('paused');
                tickerPaused = true;
                tickerOffset += delta;
                const maxOffset = c.scrollWidth / 2;
                if (tickerOffset > 0) tickerOffset = 0;
                if (tickerOffset < -maxOffset) tickerOffset = -maxOffset + Math.abs(delta);
                c.style.transform = `translateX(${tickerOffset}px)`;
                clearTimeout(window._tickerResumeTimer);
                window._tickerResumeTimer = setTimeout(() => {
                    tickerPaused = false;
                    tickerOffset = 0;
                    c.style.transform = '';
                    c.classList.remove('paused');
                }, 5000);
            }

            // Pause/reprise au survol (déjà géré en CSS hover, mais on renforce)
            document.addEventListener('DOMContentLoaded', function() {
                const scroll = document.querySelector('.news-ticker-scroll');
                if (!scroll) return;

                scroll.addEventListener('mouseenter', () => {
                    const c = getContent();
                    if (c) c.classList.add('paused');
                });
                scroll.addEventListener('mouseleave', () => {
                    const c = getContent();
                    if (c && !tickerPaused) c.classList.remove('paused');
                });

                // Défilement à la molette
                scroll.addEventListener('wheel', (e) => {
                    e.preventDefault();
                    applyOffset(-e.deltaY * 0.8);
                }, { passive: false });

                // Swipe tactile
                let touchStartX = 0;
                scroll.addEventListener('touchstart', (e) => {
                    touchStartX = e.touches[0].clientX;
                }, { passive: true });
                scroll.addEventListener('touchmove', (e) => {
                    e.preventDefault();
                    const dx = e.touches[0].clientX - touchStartX;
                    touchStartX = e.touches[0].clientX;
                    applyOffset(dx);
                }, { passive: false });
            });

            window.tickerNav = function(dir) {
                applyOffset(dir * STEP);
            };
        })();



/* ============================
   BLOC JS SUIVANT
   ============================ */


(function() {
    const canvas = document.getElementById('golden-particles');
    const ctx = canvas.getContext('2d');
    let W, H, particles = [], symbols = [];

    const COLORS = ['rgba(255,255,255,', 'rgba(240,240,240,', 'rgba(220,220,220,', 'rgba(200,200,200,'];

    // Symboles Adinkra dessinés via Canvas 2D
    // Chaque symbole = fonction(ctx, x, y, size)
    const adinkraDrawers = [

        // GYE NYAME — symbole de la suprématie divine (forme en S ornée)
        function gyeNyame(ctx, x, y, s) {
            ctx.save();
            ctx.translate(x, y);
            ctx.beginPath();
            ctx.moveTo(-s*0.5, 0);
            ctx.bezierCurveTo(-s*0.5, -s*0.8, s*0.5, -s*0.8, s*0.5, 0);
            ctx.bezierCurveTo(s*0.5, s*0.8, -s*0.5, s*0.8, -s*0.5, 0);
            ctx.strokeStyle = 'inherit'; ctx.lineWidth = s*0.08;
            ctx.stroke();
            // Petits cercles décoratifs
            [-s*0.5, s*0.5].forEach(cx => {
                ctx.beginPath();
                ctx.arc(cx, 0, s*0.12, 0, Math.PI*2);
                ctx.stroke();
            });
            ctx.restore();
        },

        // SANKOFA — "revenir chercher ce qu'on a oublié" (oiseau regardant en arrière)
        function sankofa(ctx, x, y, s) {
            ctx.save();
            ctx.translate(x, y);
            // Corps coeur
            ctx.beginPath();
            ctx.moveTo(0, s*0.3);
            ctx.bezierCurveTo(-s*0.6, -s*0.1, -s*0.6, -s*0.6, 0, -s*0.2);
            ctx.bezierCurveTo(s*0.6, -s*0.6, s*0.6, -s*0.1, 0, s*0.3);
            ctx.lineWidth = s*0.07; ctx.stroke();
            // Tête retournée
            ctx.beginPath();
            ctx.arc(s*0.3, -s*0.4, s*0.15, 0, Math.PI*2);
            ctx.stroke();
            // Queue
            ctx.beginPath();
            ctx.moveTo(0, s*0.3);
            ctx.lineTo(-s*0.2, s*0.55);
            ctx.moveTo(0, s*0.3);
            ctx.lineTo(s*0.1, s*0.58);
            ctx.stroke();
            ctx.restore();
        },

        // ADINKRAHENE — "roi des Adinkra" (3 cercles concentriques)
        function adinkrahene(ctx, x, y, s) {
            ctx.save();
            ctx.translate(x, y);
            [s*0.15, s*0.3, s*0.48].forEach(r => {
                ctx.beginPath();
                ctx.arc(0, 0, r, 0, Math.PI*2);
                ctx.lineWidth = s*0.06;
                ctx.stroke();
            });
            ctx.restore();
        },

        // DWENNIMMEN — "bélier" (deux spirales en miroir = humilité + force)
        function dwennimmen(ctx, x, y, s) {
            ctx.save();
            ctx.translate(x, y);
            ctx.lineWidth = s*0.07;
            [[-1,1],[1,1],[1,-1],[-1,-1]].forEach(([sx,sy]) => {
                ctx.beginPath();
                ctx.arc(sx*s*0.25, sy*s*0.25, s*0.25, 0, Math.PI*1.5);
                ctx.stroke();
            });
            ctx.restore();
        },

        // NYAME BIRIBI WO SORO — "étoile à 8 branches" (espoir, ciel)
        function nyameSoro(ctx, x, y, s) {
            ctx.save();
            ctx.translate(x, y);
            ctx.lineWidth = s*0.07;
            for (let i = 0; i < 8; i++) {
                const angle = (i / 8) * Math.PI * 2;
                ctx.beginPath();
                ctx.moveTo(0, 0);
                ctx.lineTo(Math.cos(angle)*s*0.5, Math.sin(angle)*s*0.5);
                ctx.stroke();
            }
            ctx.beginPath();
            ctx.arc(0, 0, s*0.15, 0, Math.PI*2);
            ctx.stroke();
            ctx.restore();
        },

        // FUNTUNFUNEFU — "crocodiles siamois" (unité dans la diversité)
        function funtun(ctx, x, y, s) {
            ctx.save();
            ctx.translate(x, y);
            ctx.lineWidth = s*0.07;
            // Cercle central
            ctx.beginPath(); ctx.arc(0,0,s*0.2,0,Math.PI*2); ctx.stroke();
            // 4 têtes
            const dirs = [[0,-1],[0,1],[-1,0],[1,0]];
            dirs.forEach(([dx,dy]) => {
                ctx.beginPath();
                ctx.arc(dx*s*0.45, dy*s*0.45, s*0.12, 0, Math.PI*2);
                ctx.stroke();
                ctx.beginPath();
                ctx.moveTo(dx*s*0.2, dy*s*0.2);
                ctx.lineTo(dx*s*0.33, dy*s*0.33);
                ctx.stroke();
            });
            ctx.restore();
        },
    ];

    function resize() {
        W = canvas.width = window.innerWidth;
        H = canvas.height = window.innerHeight;
    }

    function createParticle() {
        return {
            x: Math.random() * W, y: Math.random() * H,
            r: Math.random() * 2.2 + 0.5,
            alpha: Math.random() * 0.5 + 0.08,
            vx: (Math.random() - 0.5) * 0.35,
            vy: -(Math.random() * 0.45 + 0.08),
            color: COLORS[Math.floor(Math.random() * COLORS.length)],
            twinkleSpeed: Math.random() * 0.015 + 0.004,
            twinkleDir: Math.random() > 0.5 ? 1 : -1
        };
    }

    function createSymbol() {
        return {
            x: Math.random() * W, y: Math.random() * H,
            size: Math.random() * 28 + 18,
            alpha: Math.random() * 0.06 + 0.03,
            vx: (Math.random() - 0.5) * 0.12,
            vy: -(Math.random() * 0.15 + 0.03),
            rotation: Math.random() * Math.PI * 2,
            rotSpeed: (Math.random() - 0.5) * 0.003,
            drawFn: adinkraDrawers[Math.floor(Math.random() * adinkraDrawers.length)],
            pulseSpeed: Math.random() * 0.008 + 0.002,
            pulseDir: Math.random() > 0.5 ? 1 : -1
        };
    }

    function init() {
        resize();
        particles = Array.from({length: 40}, createParticle);
        symbols = Array.from({length: 7}, createSymbol);
    }

    function draw() {
        ctx.clearRect(0, 0, W, H);

        // — Particules —
        particles.forEach(p => {
            p.alpha += p.twinkleSpeed * p.twinkleDir;
            if (p.alpha >= 0.58 || p.alpha <= 0.04) p.twinkleDir *= -1;
            p.x += p.vx; p.y += p.vy;
            if (p.y < -5) p.y = H + 5;
            if (p.x < -5) p.x = W + 5;
            if (p.x > W + 5) p.x = -5;

            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI*2);
            ctx.fillStyle = p.color + p.alpha + ')';
            ctx.fill();

            if (p.r > 1.4) {
                const grd = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, p.r*4);
                grd.addColorStop(0, p.color + (p.alpha*0.35) + ')');
                grd.addColorStop(1, p.color + '0)');
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.r*4, 0, Math.PI*2);
                ctx.fillStyle = grd;
                ctx.fill();
            }
        });

        // — Symboles Adinkra —
        symbols.forEach(s => {
            s.rotation += s.rotSpeed;
            s.x += s.vx; s.y += s.vy;
            if (s.y < -80) s.y = H + 80;
            if (s.x < -80) s.x = W + 80;
            if (s.x > W + 80) s.x = -80;

            ctx.save();
            ctx.globalAlpha = 0.07;
            ctx.strokeStyle = `rgba(212,175,55,1)`;
            ctx.translate(s.x, s.y);
            ctx.rotate(s.rotation);
            s.drawFn(ctx, 0, 0, s.size);
            ctx.restore();
        });

        requestAnimationFrame(draw);
    }

    window.addEventListener('resize', resize);
    init();
    draw();
})();

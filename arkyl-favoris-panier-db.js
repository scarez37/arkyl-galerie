// ==================== ARKYL - FAVORIS & PANIER BASE DE DONNÉES ====================
// Version: 1.0
// Ce fichier remplace les fonctions localStorage par des fonctions base de données

(function() {
    'use strict';
    
    console.log('🔄 Chargement du module Favoris & Panier DB...');
    
    // Fonction pour obtenir un user_id unique
    window.getUserId = function() {
        let userId = localStorage.getItem('arkyl_user_id');
        if (!userId) {
            userId = 'user_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            localStorage.setItem('arkyl_user_id', userId);
        }
        return userId;
    };
    
    // ==================== FAVORIS ====================
    
    window.toggleFavorite = async function(event, artworkId) {
        event.stopPropagation();
        
        try {
            const response = await fetch('https://arkyl-galerie.onrender.com/api_ajouter_favoris.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    artwork_id: artworkId,
                    user_id: getUserId()
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                if (typeof showToast === 'function') showToast(result.message);
                if (typeof updateBadges === 'function') updateBadges();
                
                const favPage = document.getElementById('favoritesPage');
                if (favPage && favPage.classList.contains('active')) {
                    renderFavorites();
                }
            } else {
                if (typeof showToast === 'function') showToast('❌ ' + result.message);
            }
        } catch (error) {
            console.error('Erreur toggle favori:', error);
            if (typeof showToast === 'function') showToast('❌ Erreur de connexion');
        }
    };
    
    window.renderFavorites = async function() {
        const container = document.getElementById('favoritesContainer');
        const emptyState = document.getElementById('emptyFavorites');
        
        if (!container) {
            console.warn('Container favoris non trouvé');
            return;
        }
        
        if (typeof showLoading === 'function') showLoading();
        
        try {
            const response = await fetch(`https://arkyl-galerie.onrender.com/api_get_favoris.php?user_id=${getUserId()}`);
            const result = await response.json();
            
            if (typeof hideLoading === 'function') hideLoading();
            
            if (!result.success) {
                throw new Error(result.message);
            }
            
            const favoriteProducts = result.data || [];
            
            if (favoriteProducts.length === 0) {
                container.style.display = 'none';
                if (emptyState) emptyState.style.display = 'block';
                return;
            }
            
            container.style.display = 'grid';
            if (emptyState) emptyState.style.display = 'none';
            
            container.innerHTML = favoriteProducts.map(product => {
                const imageUrl = product.image_url || product.image || 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22400%22%3E%3Crect fill=%22%23ddd%22 width=%22400%22 height=%22400%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2248%22%3E🎨%3C/text%3E%3C/svg%3E';
                
                return `
                    <div class="product-card" onclick="viewProductDetailFromAPI(${product.id})">
                        <div class="product-image" style="position:relative;width:100%;height:250px;overflow:hidden;border-radius:15px;">
                            <img src="${imageUrl}" alt="${product.title}" style="width:100%;height:100%;object-fit:cover;" loading="lazy" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22400%22%3E%3Crect fill=%22%23ddd%22 width=%22400%22 height=%22400%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2248%22%3E🎨%3C/text%3E%3C/svg%3E'">
                            <span class="product-badge" style="position:absolute;top:10px;left:10px;background:rgba(212,165,116,0.9);color:white;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;">${product.badge || 'Disponible'}</span>
                            <button class="like-button" onclick="toggleFavorite(event, ${product.id})" style="position:absolute;top:10px;right:10px;background:rgba(255,255,255,0.9);border:none;width:40px;height:40px;border-radius:50%;cursor:pointer;font-size:20px;">❤️</button>
                        </div>
                        <div class="product-info" style="padding:15px;">
                            <div class="product-title" style="font-size:18px;font-weight:600;margin-bottom:5px;">${product.title}</div>
                            <div class="product-artist" style="font-size:14px;opacity:0.8;margin-bottom:10px;">par ${product.artist_name || product.artist || 'Artiste inconnu'}</div>
                            <div class="product-footer" style="display:flex;justify-content:space-between;align-items:center;">
                                <div class="product-price" style="font-size:20px;font-weight:700;color:rgba(212,165,116,1);">${product.price} FCFA</div>
                                <button class="add-cart-btn" onclick="addToCart(event, ${product.id})" style="background:rgba(212,165,116,0.2);border:1px solid rgba(212,165,116,0.4);padding:8px 16px;border-radius:20px;cursor:pointer;">🛒 Panier</button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
            
        } catch (error) {
            if (typeof hideLoading === 'function') hideLoading();
            console.error('Erreur chargement favoris:', error);
            container.innerHTML = '<p style="text-align:center;padding:40px;">❌ Erreur de chargement</p>';
        }
    };
    
    // ==================== PANIER ====================
    
    window.addToCart = async function(event, artworkId, quantity = 1) {
        if (event) event.stopPropagation();
        
        try {
            const response = await fetch('https://arkyl-galerie.onrender.com/api_ajouter_panier.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    artwork_id: artworkId,
                    user_id: getUserId(),
                    quantity: quantity
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                if (typeof showToast === 'function') showToast(result.message);
                if (typeof updateBadges === 'function') updateBadges();
                
                const cartPage = document.getElementById('cartPage');
                if (cartPage && cartPage.classList.contains('active')) {
                    renderCart();
                }
            } else {
                if (typeof showToast === 'function') showToast('❌ ' + result.message);
            }
        } catch (error) {
            console.error('Erreur ajout panier:', error);
            if (typeof showToast === 'function') showToast('❌ Erreur de connexion');
        }
    };
    
    window.renderCart = async function() {
        const container = document.getElementById('cartContainer');
        
        if (!container) {
            console.warn('Container panier non trouvé');
            return;
        }
        
        if (typeof showLoading === 'function') showLoading();
        
        try {
            const response = await fetch(`https://arkyl-galerie.onrender.com/api_get_panier.php?user_id=${getUserId()}`);
            const result = await response.json();
            
            if (typeof hideLoading === 'function') hideLoading();
            
            if (!result.success) {
                throw new Error(result.message);
            }
            
            const cartItems = result.data || [];
            
            if (cartItems.length === 0) {
                container.innerHTML = `
                    <div class="empty-state" style="text-align:center;padding:60px 20px;">
                        <div style="font-size:80px;margin-bottom:20px;">🛒</div>
                        <h3 style="font-size:24px;margin-bottom:10px;">Votre panier est vide</h3>
                        <p style="opacity:0.8;margin-bottom:30px;">Explorez notre galerie et ajoutez des œuvres!</p>
                        <button class="modal-btn" onclick="navigateTo('home')" style="background:rgba(212,165,116,1);color:white;padding:12px 30px;border:none;border-radius:25px;cursor:pointer;font-size:16px;">Parcourir les œuvres</button>
                    </div>
                `;
                return;
            }
            
            const totalPrice = result.total_price || 0;
            const shipping = 5000;
            const total = totalPrice + shipping;
            
            container.innerHTML = `
                <div style="max-width:900px;margin:0 auto;">
                    ${cartItems.map(item => {
                        const imageUrl = item.image_url || item.image || 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22400%22%3E%3Crect fill=%22%23ddd%22 width=%22400%22 height=%22400%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2248%22%3E🎨%3C/text%3E%3C/svg%3E';
                        const subtotal = item.price * item.quantity;
                        
                        return `
                            <div class="cart-item" style="display:flex;gap:20px;padding:20px;background:rgba(255,255,255,0.05);border-radius:15px;margin-bottom:15px;">
                                <img src="${imageUrl}" alt="${item.title}" style="width:120px;height:120px;object-fit:cover;border-radius:10px;" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22400%22%3E%3Crect fill=%22%23ddd%22 width=%22400%22 height=%22400%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2248%22%3E🎨%3C/text%3E%3C/svg%3E'">
                                <div style="flex:1;">
                                    <h3 style="font-size:18px;font-weight:600;margin-bottom:5px;">${item.title}</h3>
                                    <p style="opacity:0.8;font-size:14px;margin-bottom:10px;">par ${item.artist_name || item.artist || 'Artiste inconnu'}</p>
                                    <p style="font-size:16px;font-weight:600;color:rgba(212,165,116,1);">${item.price} FCFA</p>
                                </div>
                                <div style="display:flex;flex-direction:column;align-items:flex-end;justify-content:space-between;">
                                    <div style="display:flex;align-items:center;gap:10px;background:rgba(255,255,255,0.1);padding:8px;border-radius:25px;">
                                        <span style="padding:0 15px;font-weight:600;">Quantité: ${item.quantity}</span>
                                    </div>
                                    <p style="font-size:18px;font-weight:700;">${subtotal} FCFA</p>
                                </div>
                            </div>
                        `;
                    }).join('')}
                    
                    <div style="background:rgba(212,165,116,0.1);padding:25px;border-radius:15px;margin-top:30px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                            <span style="font-size:16px;">Sous-total</span>
                            <span style="font-size:20px;font-weight:600;">${totalPrice} FCFA</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                            <span style="font-size:16px;">Livraison</span>
                            <span style="font-size:20px;font-weight:600;">${shipping} FCFA</span>
                        </div>
                        <hr style="border:none;border-top:1px solid rgba(255,255,255,0.2);margin:15px 0;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                            <span style="font-size:20px;font-weight:600;">Total</span>
                            <span style="font-size:28px;font-weight:700;color:rgba(212,165,116,1);">${total} FCFA</span>
                        </div>
                        <button onclick="alert('🚧 Paiement en développement')" style="width:100%;background:rgba(212,165,116,1);color:white;padding:15px;border:none;border-radius:25px;cursor:pointer;font-size:18px;font-weight:600;">✓ Passer la commande</button>
                    </div>
                </div>
            `;
            
        } catch (error) {
            if (typeof hideLoading === 'function') hideLoading();
            console.error('Erreur chargement panier:', error);
            container.innerHTML = '<p style="text-align:center;padding:40px;">❌ Erreur de chargement</p>';
        }
    };
    
    // ==================== BADGES ====================
    
    window.updateBadges = async function() {
        const userId = getUserId();
        
        try {
            const favResponse = await fetch(`https://arkyl-galerie.onrender.com/api_get_favoris.php?user_id=${userId}`);
            const favResult = await favResponse.json();
            const favCount = favResult.success ? (favResult.count || 0) : 0;
            
            const cartResponse = await fetch(`https://arkyl-galerie.onrender.com/api_get_panier.php?user_id=${userId}`);
            const cartResult = await cartResponse.json();
            const cartCount = cartResult.success ? (cartResult.total_items || 0) : 0;
            
            const favBadges = document.querySelectorAll('.fav-badge, #favBadge');
            favBadges.forEach(badge => {
                badge.textContent = favCount;
                badge.style.display = favCount > 0 ? 'flex' : 'none';
            });
            
            const cartBadges = document.querySelectorAll('.cart-badge, #cartBadge');
            cartBadges.forEach(badge => {
                badge.textContent = cartCount;
                badge.style.display = cartCount > 0 ? 'flex' : 'none';
            });
            
        } catch (error) {
            console.error('Erreur mise à jour badges:', error);
        }
    };
    
    // Initialiser
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateBadges);
    } else {
        updateBadges();
    }
    
    console.log('✅ Module Favoris & Panier DB chargé avec succès');
    
})();

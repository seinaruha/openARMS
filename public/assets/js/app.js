/**
 * openARMS Main JavaScript
 * 
 * Common functionality for Shelter Resource Management System
 */

// ============================================
// Application Configuration
// ============================================
const APP_CONFIG = {
    baseUrl: window.location.origin,  // Auto-detect from current URL (XAMPP compatible)
    apiPath: '/api',
    toastDuration: 3000,
};

// ============================================
// Toast Notifications
// ============================================
function toast(message, type = 'success') {
    const t = document.getElementById('toast');
    if (!t) return;
    
    t.textContent = message;
    t.className = `show ${type}`;
    
    setTimeout(() => {
        t.className = '';
    }, APP_CONFIG.toastDuration);
}

// ============================================
// Error Display
// ============================================
function showError(message) {
    const banner = document.getElementById('error-banner');
    const text = document.getElementById('error-text');
    
    if (banner && text) {
        text.textContent = message;
        banner.classList.add('show');
    }
}

function hideError() {
    const banner = document.getElementById('error-banner');
    if (banner) {
        banner.classList.remove('show');
    }
}

// ============================================
// Navigation Toggle (Mobile)
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');
    
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function() {
            navMenu.classList.toggle('show');
            // Update icon
            const icon = navToggle.querySelector('i');
            if (icon) {
                icon.className = navMenu.classList.contains('show') 
                    ? 'bi bi-x-lg' 
                    : 'bi bi-list';
            }
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!navToggle.contains(e.target) && !navMenu.contains(e.target)) {
                navMenu.classList.remove('show');
                const icon = navToggle.querySelector('i');
                if (icon) icon.className = 'bi bi-list';
            }
        });
    }
    
    // Initialize password toggles
    initPasswordToggles();
    
    // Initialize form validations
    initFormValidations();
    
    // Initialize confirm dialogs
    initConfirmDialogs();
});

// ============================================
// Password Visibility Toggle
// ============================================
function initPasswordToggles() {
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            if (!input) return;
            
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            this.textContent = isHidden ? 'Hide' : 'Show';
        });
    });
}

// ============================================
// Form Validation Helpers
// ============================================
function initFormValidations() {
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
}

function validateForm(form) {
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        removeFieldError(field);
        
        if (!field.value.trim()) {
            showFieldError(field, 'This field is required');
            isValid = false;
        } else if (field.type === 'email' && !isValidEmail(field.value)) {
            showFieldError(field, 'Please enter a valid email address');
            isValid = false;
        }
    });
    
    return isValid;
}

function showFieldError(field, message) {
    field.classList.add('error');
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    field.parentNode.appendChild(errorDiv);
}

function removeFieldError(field) {
    field.classList.remove('error');
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) existingError.remove();
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

// ============================================
// Confirm Dialog Enhancement
// ============================================
function initConfirmDialogs() {
    document.querySelectorAll('button[data-confirm]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const message = this.dataset.confirm || 'Are you sure?';
            if (!confirm(message)) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });
    });
}

// ============================================
// API Helper Functions
// ============================================
async function apiFetch(endpoint, options = {}) {
    const url = `${APP_CONFIG.baseUrl}${endpoint}`;
    
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
            ...options.headers
        }
    };
    
    try {
        const response = await fetch(url, { ...defaultOptions, ...options });
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Request failed');
        }
        
        return data;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

// ============================================
// Utility Functions
// ============================================
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP'
    }).format(amount);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

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

// ============================================
// Table Search/Filter Helper
// ============================================
function initTableSearch(tableId, searchInputId) {
    const table = document.getElementById(tableId);
    const searchInput = document.getElementById(searchInputId);
    
    if (!table || !searchInput) return;
    
    searchInput.addEventListener('input', debounce(function() {
        const searchTerm = searchInput.value.toLowerCase();
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    }, 300));
}

// ============================================
// Loading State Management
// ============================================
function setLoading(element, isLoading) {
    if (!element) return;
    
    element.disabled = isLoading;
    element.textContent = isLoading ? 'Loading...' : element.dataset.originalText || element.textContent;
    
    if (isLoading && !element.dataset.originalText) {
        element.dataset.originalText = element.textContent;
    }
}

// ============================================
// Session Management
// ============================================
function checkAuth() {
    const user = localStorage.getItem('asrms_user') || sessionStorage.getItem('asrms_user');
    if (!user && !window.location.pathname.includes('login')) {
        window.location.href = 'login.html';
        return false;
    }
    return true;
}

function logout() {
    localStorage.removeItem('asrms_user');
    sessionStorage.removeItem('asrms_user');
    localStorage.removeItem('asrms_token');
    sessionStorage.removeItem('asrms_token');
    window.location.href = 'login.html';
}

// Export for module usage (if needed)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { toast, showError, hideError, apiFetch, checkAuth, logout };
}

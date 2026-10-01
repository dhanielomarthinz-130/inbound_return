/**
 * Toast Premium Notification System for Inbound Return IEG
 * Enterprise-grade, sleek micro-animations, glassmorphism, countdown progress bar.
 */
(function() {
    'use strict';

    // Buat container toast jika belum ada
    function getOrCreateContainer() {
        let container = document.getElementById('iegToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'iegToastContainer';
            container.className = 'fixed top-4 right-4 z-[99999] flex flex-col gap-2.5 max-w-sm w-full pointer-events-none px-3 sm:px-0';
            document.body.appendChild(container);
        }
        return container;
    }

    const toastIcons = {
        success: 'fa-solid fa-circle-check text-emerald-500',
        error: 'fa-solid fa-circle-xmark text-rose-500',
        warning: 'fa-solid fa-triangle-exclamation text-amber-500',
        info: 'fa-solid fa-circle-info text-indigo-500'
    };

    const toastBadges = {
        success: { bg: 'bg-emerald-50', border: 'border-emerald-200', bar: 'bg-emerald-500', text: 'text-emerald-700' },
        error: { bg: 'bg-rose-50', border: 'border-rose-200', bar: 'bg-rose-500', text: 'text-rose-700' },
        warning: { bg: 'bg-amber-50', border: 'border-amber-200', bar: 'bg-amber-500', text: 'text-amber-700' },
        info: { bg: 'bg-indigo-50', border: 'border-indigo-200', bar: 'bg-indigo-500', text: 'text-indigo-700' }
    };

    const defaultTitles = {
        success: 'Berhasil',
        error: 'Terjadi Kesalahan',
        warning: 'Peringatan',
        info: 'Informasi'
    };

    function showToast(type, message, title, duration = 4000) {
        if (!type || !toastIcons[type]) {
            type = 'info';
        }

        const container = getOrCreateContainer();
        const badge = toastBadges[type];
        const iconClass = toastIcons[type];
        const displayTitle = title || defaultTitles[type];
        const displayMessage = (message === undefined || message === null) ? '' : String(message);

        // Card Element
        const toast = document.createElement('div');
        toast.className = `ieg-toast-item pointer-events-auto relative overflow-hidden bg-white/95 backdrop-blur-md border ${badge.border} shadow-xl shadow-slate-900/10 rounded-2xl p-3.5 flex items-start gap-3 transition-all duration-300 transform translate-x-10 opacity-0`;
        
        // Progress Bar
        const progressBar = document.createElement('div');
        progressBar.className = `absolute bottom-0 left-0 h-1 ${badge.bar} transition-all ease-linear`;
        progressBar.style.width = '100%';
        progressBar.style.transitionDuration = `${duration}ms`;

        toast.innerHTML = `
            <div class="w-9 h-9 rounded-xl ${badge.bg} border ${badge.border} flex items-center justify-center shrink-0 text-base shadow-xs">
                <i class="${iconClass}"></i>
            </div>
            <div class="flex-1 min-w-0 pr-2 pt-0.5">
                <h5 class="text-xs font-bold text-slate-800 leading-tight mb-0.5">${escapeHtml(displayTitle)}</h5>
                <p class="text-[11px] text-slate-600 leading-relaxed break-words whitespace-pre-line">${escapeHtml(displayMessage)}</p>
            </div>
            <button type="button" class="toast-close-btn text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100 transition shrink-0">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        `;

        toast.appendChild(progressBar);
        container.appendChild(toast);

        // Entrance animation
        requestAnimationFrame(() => {
            toast.classList.remove('translate-x-10', 'opacity-0');
            toast.classList.add('translate-x-0', 'opacity-100');
            // Trigger progress bar
            setTimeout(() => {
                progressBar.style.width = '0%';
            }, 20);
        });

        let removeTimer;
        let isDismissed = false;

        function dismiss() {
            if (isDismissed) return;
            isDismissed = true;
            clearTimeout(removeTimer);
            toast.classList.remove('translate-x-0', 'opacity-100');
            toast.classList.add('translate-x-12', 'opacity-0');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }

        // Auto dismiss timer
        removeTimer = setTimeout(dismiss, duration);

        // Close button click
        const closeBtn = toast.querySelector('.toast-close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', dismiss);
        }

        // Pause on hover
        toast.addEventListener('mouseenter', () => {
            clearTimeout(removeTimer);
            progressBar.style.transitionDuration = '0ms';
        });

        toast.addEventListener('mouseleave', () => {
            progressBar.style.transitionDuration = '1500ms';
            progressBar.style.width = '0%';
            removeTimer = setTimeout(dismiss, 1500);
        });

        return { dismiss };
    }

    function escapeHtml(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Object API
    window.toast = {
        success: (msg, title, duration) => showToast('success', msg, title, duration),
        error: (msg, title, duration) => showToast('error', msg, title, duration),
        warning: (msg, title, duration) => showToast('warning', msg, title, duration),
        info: (msg, title, duration) => showToast('info', msg, title, duration),
        show: showToast
    };

    window.showToast = showToast;
    window.escapeHtml = escapeHtml;

    // INTERCEPT NATIVE WINDOW.ALERT SECARA OTOMATIS
    const _nativeAlert = window.alert;
    window.alert = function(msg) {
        if (msg === undefined || msg === null) msg = '';
        let str = (typeof msg === 'object') ? JSON.stringify(msg) : String(msg);
        let lower = str.toLowerCase();
        
        let type = 'info';
        let title = 'Informasi';

        if (lower.includes('berhasil') || lower.includes('sukses') || lower.includes('selesai') || lower.includes('success')) {
            type = 'success';
            title = 'Berhasil';
        } else if (lower.includes('gagal') || lower.includes('error') || lower.includes('kesalahan') || lower.includes('ditolak') || lower.includes('terjadi kesalahan')) {
            type = 'error';
            title = 'Gagal';
        } else if (lower.includes('harap') || lower.includes('wajib') || lower.includes('peringatan') || lower.includes('belum') || lower.includes('tidak ada') || lower.includes('hanya ada')) {
            type = 'warning';
            title = 'Perhatian';
        }

        showToast(type, str, title, 4500);
        // Console debug log (non-blocking)
        console.log(`[Toast Alert Intercepted] (${type}): ${str}`);
    };

})();

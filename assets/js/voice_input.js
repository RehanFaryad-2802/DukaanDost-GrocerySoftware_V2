// Add this at the very top of voice_input.js
window.stopAllVoiceInput = function () {
    if (window.__activeRecognition) {
        try { window.__activeRecognition.abort(); } catch (e) { }
        window.__activeRecognition = null;
    }
    if (window.recognition) {
        try { window.recognition.abort(); } catch (e) { }
        window.recognition = null;
    }
    document.querySelectorAll('.voice-input-wrapper').forEach(wrapper => {
        const input = wrapper.querySelector('input');
        if (input) {
            input.style.paddingRight = '';
            wrapper.parentNode.insertBefore(input, wrapper);
            wrapper.remove();
        }
    });
    document.querySelectorAll('input.voice-input, input[data-voice="true"], #search_product').forEach(input => {
        input.removeAttribute('data-voice-attached');
    });
};
// Use PHP-injected value directly — no async race condition
window.__voiceInputEnabled = (typeof VOICE_INPUT_ENABLED !== 'undefined') ? VOICE_INPUT_ENABLED : true;

async function updateVoiceInputStatus() {
    try {
        // Use the PHP-set value, no API call needed
        window.__voiceInputEnabled = (typeof VOICE_INPUT_ENABLED !== 'undefined') ? VOICE_INPUT_ENABLED : true;
        console.log('Voice input:', window.__voiceInputEnabled ? 'ENABLED' : 'DISABLED');

        // If voice is disabled, stop any active listening
        if (!window.__voiceInputEnabled && window.__activeRecognition) {
            try {
                window.__activeRecognition.abort();
            } catch (e) { }
            window.__activeRecognition = null;

            // Remove listening class from active input
            if (window.__activeInput) {
                window.__activeInput.classList.remove('voice-input-listening');
                const wrapper = window.__activeInput.closest('.voice-input-wrapper');
                if (wrapper) {
                    const micBtn = wrapper.querySelector('.voice-mic-btn');
                    if (micBtn) micBtn.classList.remove('listening');
                }
                window.__activeInput = null;
            }
        }

        return window.__voiceInputEnabled;
    } catch (e) {
        window.__voiceInputEnabled = true;
        return true;
    }
}

// Call immediately and when page becomes visible
updateVoiceInputStatus();
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateVoiceInputStatus);
} else {
    updateVoiceInputStatus();
}

(function () {
    // Active recognition instance - make global
    window.__activeRecognition = null;
    window.__activeInput = null;
    let isTypingManually = false;

    // Add CSS
    function addStyles() {
        if (document.getElementById('voice-input-styles')) return;

        const style = document.createElement('style');
        style.id = 'voice-input-styles';
        style.textContent = `
            .voice-input-wrapper {
                position: relative;
                display: inline-block;
                width: 100%;
            }
            
            .voice-mic-btn {
                position: absolute !important;
                right: 8px !important;
                top: 50% !important;
                transform: translateY(-50%) !important;
                background: transparent !important;
                border: none !important;
                cursor: pointer !important;
                padding: 4px 8px !important;
                border-radius: 20px !important;
                transition: all 0.2s ease !important;
                z-index: 100 !important;
                color: #6c757d !important;
                font-size: 16px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            
            .voice-mic-btn:hover {
                background-color: #e9ecef !important;
                color: #28a745 !important;
            }
            
            .voice-mic-btn.listening,
            .voice-input-listening .voice-mic-btn {
                background-color: #dc3545 !important;
                color: white !important;
                animation: voicePulse 1.5s infinite !important;
            }
            
            .voice-input-listening {
                border: 2px solid #dc3545 !important;
                background-color: #fff5f5 !important;
            }
            
            @keyframes voicePulse {
                0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
                70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
                100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
            }
            
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            
            .voice-toast {
                position: fixed !important;
                bottom: 80px !important;
                right: 20px !important;
                background: #17a2b8 !important;
                color: white !important;
                padding: 12px 20px !important;
                border-radius: 8px !important;
                font-size: 14px !important;
                z-index: 10000 !important;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
                display: flex !important;
                align-items: center !important;
                gap: 10px !important;
                animation: slideInRight 0.3s ease !important;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            }
            
            .voice-toast.voice-toast-success { background: #28a745 !important; }
            .voice-toast.voice-toast-error { background: #dc3545 !important; }
            .voice-toast.voice-toast-info { background: #17a2b8 !important; }
            
            [dir="rtl"] .voice-mic-btn,
            .voice-input-wrapper[dir="rtl"] .voice-mic-btn {
                right: auto !important;
                left: 8px !important;
            }
            
            [dir="rtl"].voice-input,
            input[dir="rtl"].voice-input {
                padding-right: 35px !important;
                padding-left: 5px !important;
            }
        `;
        document.head.appendChild(style);
    }

    // Show toast notification
    function showToast(message, type = 'info') {
        const existingToast = document.querySelector('.voice-toast');
        if (existingToast) existingToast.remove();

        const toast = document.createElement('div');
        toast.className = `voice-toast voice-toast-${type}`;
        toast.innerHTML = `<span>${message}</span>`;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Stop current listening
    function stopListening(reason = '') {
        if (window.__activeRecognition) {
            try {
                window.__activeRecognition.abort();
            } catch (e) { }
            window.__activeRecognition = null;
        }

        if (window.__activeInput) {
            window.__activeInput.classList.remove('voice-input-listening');
            const wrapper = window.__activeInput.closest('.voice-input-wrapper');
            if (wrapper) {
                const micBtn = wrapper.querySelector('.voice-mic-btn');
                if (micBtn) micBtn.classList.remove('listening');
            }

            if (window.__activeInput.placeholder != 'تلاش کریں...') {
                window.__activeInput.placeholder = 'تلاش کریں...';
            }

            window.__activeInput = null;
        }
        isTypingManually = false;
    }

    // Start listening for an input
    function startListening(input, micBtn) {
        // CRITICAL: Check if voice input is enabled in settings - check fresh each time
        if (!window.__voiceInputEnabled) {
            console.log('Voice input blocked - disabled in settings');
            return false;
        }

        // Stop any existing listening first
        stopListening('New request');

        // Reset typing flag
        isTypingManually = false;

        // Check speech recognition support
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) {
            showToast('Voice not supported. Use Chrome/Edge.', 'error');
            return false;
        }

        // Set active
        window.__activeInput = input;
        input.classList.add('voice-input-listening');
        if (micBtn) micBtn.classList.add('listening');

        // Create recognition
        window.__activeRecognition = new SpeechRecognition();
        window.__activeRecognition.lang = 'ur-PK';
        window.__activeRecognition.continuous = false;
        window.__activeRecognition.interimResults = true;
        window.__activeRecognition.maxAlternatives = 1;

        window.__activeRecognition.onstart = function () {
            input.placeholder = 'بولیں...';
            showToast('بولیں...', 'info');
        };

        window.__activeRecognition.onresult = function (event) {
            if (isTypingManually) return;

            let interim = '';
            let final = '';

            for (let i = event.resultIndex; i < event.results.length; i++) {
                const transcript = event.results[i][0].transcript;
                if (event.results[i].isFinal) {
                    final += transcript;
                } else {
                    interim += transcript;
                }
            }

            if (interim && !isTypingManually) {
                input.value = interim;
                input.placeholder = '🎤 ' + interim + ' (speaking...)';
            }

            if (final && !isTypingManually) {
                input.value = final;
                input.placeholder = '';
                showToast(`✅ "${final}" added`, 'success');
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                stopListening('Voice input complete');
            }
        };

        window.__activeRecognition.onerror = function (event) {
            if (event.error === 'aborted') return;
            showToast('Voice error: ' + event.error, 'error');
            stopListening('Error: ' + event.error);
        };

        window.__activeRecognition.onend = function () {
            if (window.__activeInput === input && !isTypingManually) {
                stopListening('Recognition ended');
            }
        };

        window.__activeRecognition.start();
        return true;
    }

    // Add voice input to an element
    function addVoiceInput(input) {
        if (input.hasAttribute('data-voice-attached')) return;

        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) return;

        input.setAttribute('data-voice-attached', 'true');

        const wrapper = document.createElement('div');
        wrapper.className = 'voice-input-wrapper';
        wrapper.style.cssText = 'position: relative; display: inline-block; width: 100%;';

        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        const micBtn = document.createElement('button');
        micBtn.type = 'button';
        micBtn.className = 'voice-mic-btn';
        micBtn.innerHTML = '<i class="bi bi-mic"></i>';
        micBtn.title = 'Click to speak';

        input.style.paddingRight = '40px';
        wrapper.appendChild(micBtn);

        input.addEventListener('focus', function (e) {
            startListening(input, micBtn);
        });

        input.addEventListener('blur', function (e) {
            stopListening('Input lost focus');
        });

        input.addEventListener('keydown', function (e) {
            const specialKeys = [
                'Shift', 'Ctrl', 'Alt', 'Meta', 'Control', 'AltGraph',
                'CapsLock', 'NumLock', 'ScrollLock', 'Tab', 'Escape',
                'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight',
                'Home', 'End', 'PageUp', 'PageDown', 'Insert', 'Delete'
            ];

            const isFunctionKey = e.key.startsWith('F') && e.key.length === 2;

            if (!specialKeys.includes(e.key) && !isFunctionKey && e.key.length === 1) {
                if (window.__activeRecognition) {
                    isTypingManually = true;
                    stopListening('User started typing');
                }
            }
        });

        input.addEventListener('input', function (e) {
            if (window.__activeRecognition && !isTypingManually) {
                stopListening('Input changed');
            }
        });

        micBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            input.focus();
        });
    }

    // Initialize all voice inputs
    async function initVoiceInputs() {
        // Refresh preference before initializing
        await updateVoiceInputStatus();

        if (!window.__voiceInputEnabled) {
            console.log('Voice input disabled in settings - not initializing');
            return;
        }

        const inputs = document.querySelectorAll('input.voice-input, input[data-voice="true"]');
        inputs.forEach(addVoiceInput);

        const searchInput = document.getElementById('search_product');
        if (searchInput && !searchInput.hasAttribute('data-voice-attached')) {
            addVoiceInput(searchInput);
        }
    }

    // Watch for dynamically added inputs
    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(function (node) {
                if (node.nodeType === 1) {
                    if (node.matches && node.matches('input.voice-input, input[data-voice="true"]')) {
                        // Only add if voice is enabled
                        if (window.__voiceInputEnabled) addVoiceInput(node);
                    }
                    if (node.querySelectorAll) {
                        const inputs = node.querySelectorAll('input.voice-input, input[data-voice="true"]');
                        inputs.forEach(input => {
                            if (window.__voiceInputEnabled) addVoiceInput(input);
                        });
                    }
                }
            });
        });
    });

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            addStyles();
            initVoiceInputs();
            observer.observe(document.body, { childList: true, subtree: true });
        });
    } else {
        addStyles();
        initVoiceInputs();
        observer.observe(document.body, { childList: true, subtree: true });
    }

    // Re-check preference when page becomes visible (after settings change)
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            updateVoiceInputStatus().then(enabled => {
                if (!enabled && window.__activeRecognition) {
                    stopListening('Voice disabled via settings');
                }
            });
        }
    });

    // Also check every 5 seconds (in case settings changed in another tab)
    setInterval(function () {
        updateVoiceInputStatus().then(enabled => {
            if (!enabled && window.__activeRecognition) {
                stopListening('Voice disabled via settings');
            }
        });
    }, 5000);
})();
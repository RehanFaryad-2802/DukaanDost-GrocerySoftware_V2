// ===============================================
// Voice Input - Auto-start on focus, stop on typing
// ===============================================

(function () {
    // Active recognition instance
    let activeRecognition = null;
    let activeInput = null;
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
            
            /* RTL Support */
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
        if (activeRecognition) {
            try {
                activeRecognition.abort();
            } catch (e) { }
            activeRecognition = null;
        }

        if (activeInput) {
            activeInput.classList.remove('voice-input-listening');
            const wrapper = activeInput.closest('.voice-input-wrapper');
            if (wrapper) {
                const micBtn = wrapper.querySelector('.voice-mic-btn');
                if (micBtn) micBtn.classList.remove('listening');
            }

            // Reset placeholder
            if (activeInput.placeholder != 'تلاش کریں...') {
                activeInput.placeholder = 'تلاش کریں...';
            }

            if (reason) {
                console.log(`Voice stopped: ${reason}`);
            }

            activeInput = null;
        }
        isTypingManually = false;
    }

    // Start listening for an input
    function startListening(input, micBtn) {
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
        activeInput = input;
        input.classList.add('voice-input-listening');
        if (micBtn) micBtn.classList.add('listening');

        // Create recognition
        activeRecognition = new SpeechRecognition();
        activeRecognition.lang = 'ur-PK';
        activeRecognition.continuous = false;
        activeRecognition.interimResults = true;
        activeRecognition.maxAlternatives = 1;

        activeRecognition.onstart = function () {
            input.placeholder = 'بولیں...';
            showToast('بولیں...', 'info');
        };

        activeRecognition.onresult = function (event) {
            // If user started typing, ignore voice results
            if (isTypingManually) {
                console.log('Ignoring voice result - user is typing');
                return;
            }

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

                // Trigger events for search
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));

                // Auto-stop after successful input
                stopListening('Voice input complete');
            }
        };

        activeRecognition.onerror = function (event) {
            let msg = 'Voice error';
            if (event.error === 'no-speech') msg = 'No speech detected - click mic and speak again';
            else if (event.error === 'not-allowed') msg = 'Microphone permission denied';
            else if (event.error === 'aborted') return; // User stopped
            showToast(msg, 'error');
            stopListening('Error: ' + event.error);
        };

        activeRecognition.onend = function () {
            if (activeInput === input && !isTypingManually) {
                stopListening('Recognition ended');
            }
        };

        activeRecognition.start();
        return true;
    }

    // Add voice input to an element
    function addVoiceInput(input) {
        if (input.hasAttribute('data-voice-attached')) return;

        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) {
            console.log('Speech recognition not supported');
            return;
        }

        input.setAttribute('data-voice-attached', 'true');

        // Create wrapper
        const wrapper = document.createElement('div');
        wrapper.className = 'voice-input-wrapper';
        wrapper.style.cssText = 'position: relative; display: inline-block; width: 100%;';

        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        // Create mic button
        const micBtn = document.createElement('button');
        micBtn.type = 'button';
        micBtn.className = 'voice-mic-btn';
        micBtn.innerHTML = '<i class="bi bi-mic"></i>';
        micBtn.title = 'Click to speak';
        micBtn.style.cssText = `
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 20px;
            z-index: 100;
            color: #6c757d;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        `;

        input.style.paddingRight = '40px';
        wrapper.appendChild(micBtn);

        // FOCUS: Start listening when input gets focus
        input.addEventListener('focus', function (e) {
            startListening(input, micBtn);
        });

        // BLUR: Stop listening when input loses focus
        input.addEventListener('blur', function (e) {
            stopListening('Input lost focus');
        });

        // KEYDOWN: Stop listening IMMEDIATELY when user starts typing
        input.addEventListener('keydown', function (e) {
            // Check if this is a typing key (not special keys)
            const specialKeys = [
                'Shift', 'Ctrl', 'Alt', 'Meta', 'Control', 'AltGraph',
                'CapsLock', 'NumLock', 'ScrollLock', 'Tab', 'Escape',
                'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight',
                'Home', 'End', 'PageUp', 'PageDown', 'Insert', 'Delete'
            ];

            // Function keys F1-F12
            const isFunctionKey = e.key.startsWith('F') && e.key.length === 2;

            if (!specialKeys.includes(e.key) && !isFunctionKey && e.key.length === 1) {
                // User is typing a character - STOP VOICE IMMEDIATELY
                if (activeRecognition) {
                    isTypingManually = true;
                    stopListening('User started typing');
                    showToast('Voice stopped - typing detected', 'info');
                }
            }
        });

        // INPUT event - also stop listening when input value changes via typing
        input.addEventListener('input', function (e) {
            if (activeRecognition && !isTypingManually) {
                // Check if this change came from voice or typing
                // If value length increased by more than 1, likely from voice
                // But to be safe, we'll stop listening
                stopListening('Input changed');
            }
        });

        // Click on mic button also triggers focus and starts listening
        micBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            input.focus();
        });
    }

    // Initialize all voice inputs
    function initVoiceInputs() {
        const inputs = document.querySelectorAll('input.voice-input, input[data-voice="true"]');
        console.log(`Found ${inputs.length} inputs for voice attachment`);
        inputs.forEach(addVoiceInput);

        // Also add to search input specifically
        const searchInput = document.getElementById('search_product');
        if (searchInput && !searchInput.hasAttribute('data-voice-attached')) {
            addVoiceInput(searchInput);
        }
    }

    // Auto-focus on search input (optional)
    function autoFocusSearch() {
        const searchInput = document.getElementById('search_product');
        if (searchInput) {
            setTimeout(function () {
                searchInput.focus();
            }, 500);
        }
    }

    // Watch for dynamically added inputs
    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(function (node) {
                if (node.nodeType === 1) {
                    if (node.matches && node.matches('input.voice-input, input[data-voice="true"]')) {
                        addVoiceInput(node);
                    }
                    if (node.querySelectorAll) {
                        const inputs = node.querySelectorAll('input.voice-input, input[data-voice="true"]');
                        inputs.forEach(addVoiceInput);
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
            console.log('✅ Voice Input Ready - Focus to speak, typing stops listening');
        });
    } else {
        addStyles();
        initVoiceInputs();
        observer.observe(document.body, { childList: true, subtree: true });
        console.log('✅ Voice Input Ready - Focus to speak, typing stops listening');
    }
})();
// ===============================================
// VOICE BATCH INPUT SYSTEM - DukaanDost FINAL
// ===============================================

window.voiceRecognizedProducts = window.voiceRecognizedProducts || [];
let recognition = null;
let isListening = false;
let finalTranscript = '';

function isSpeechSupported() {
    return 'webkitSpeechRecognition' in window || 'SpeechRecognition' in window;
}

function toggleVoiceInput() {
    const modal = new bootstrap.Modal(document.getElementById('voiceModal'));
    modal.show();
    setupVoiceModal();
    
    // Try to initialize speech recognition, fallback to text mode
    if (!isSpeechSupported()) {
        document.getElementById('voiceStatus').innerHTML = '⚠️ Offline Mode: Type product names manually or paste text below';
        document.getElementById('startListenBtn').style.display = 'none';
        document.getElementById('stopListenBtn').style.display = 'none';
    } else {
        initSpeechRecognition();
    }
}

function setupVoiceModal() {
    document.getElementById('voiceTextarea').value = '';
    document.getElementById('processedItems').innerHTML = '';
    document.getElementById('interimResult').textContent = '';
    document.getElementById('voiceStatus').className = 'alert alert-info text-center';
    document.getElementById('voiceStatus').innerHTML = '<strong>🎤 Voice Search</strong><br>Say names with <b>"اگلا"</b> or <b>comma</b> between';
    if (window.voiceRecognizedProducts.length > 0) displayRecognizedProducts(window.voiceRecognizedProducts);
}

function initSpeechRecognition() {
    if (recognition) { try { recognition.abort(); } catch(e) {} }
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    recognition = new SR();
    recognition.lang = 'ur-PK';
    recognition.continuous = true;
    recognition.interimResults = true;
    recognition.maxAlternatives = 1;
    recognition.onstart = () => {
        isListening = true;
        document.getElementById('startListenBtn').style.display = 'none';
        document.getElementById('stopListenBtn').style.display = 'inline-block';
        document.getElementById('voiceStatus').innerHTML = ' بولیں۔۔۔';
        finalTranscript = '';
    };
    recognition.onresult = (event) => {
        let it = '';
        for (let i = event.resultIndex; i < event.results.length; i++) {
            if (event.results[i].isFinal) finalTranscript += event.results[i][0].transcript + ' ';
            else it += event.results[i][0].transcript;
        }
        document.getElementById('interimResult').textContent = it || finalTranscript;
        if (finalTranscript) document.getElementById('voiceTextarea').value = finalTranscript.trim();
    };
    recognition.onerror = (e) => { if (e.error === 'no-speech') document.getElementById('voiceStatus').innerHTML = '⚠️ No speech'; resetVoiceButtons(); };
    recognition.onend = () => { if (isListening) { try { recognition.start(); } catch(e) {} } };
}

function startListening() { finalTranscript = ''; document.getElementById('voiceTextarea').value = ''; if (recognition) { try { recognition.start(); } catch(e) {} } }
function stopListening() { isListening = false; if (recognition) recognition.stop(); document.getElementById('voiceStatus').innerHTML = '✅ Click Verify & Add'; resetVoiceButtons(); }
function resetVoiceButtons() { document.getElementById('startListenBtn').style.display = 'inline-block'; document.getElementById('stopListenBtn').style.display = 'none'; }
function stopVoiceInput() { isListening = false; if (recognition) recognition.abort(); bootstrap.Modal.getInstance(document.getElementById('voiceModal')).hide(); }
function clearVoiceText() { document.getElementById('voiceTextarea').value = ''; document.getElementById('processedItems').innerHTML = ''; window.voiceRecognizedProducts = []; }

function convertEnglishToUrdu(text) {
    const m = { a:'ا',b:'ب',c:'ک',d:'د',e:'ع',f:'ف',g:'گ',h:'ہ',i:'آئی',j:'ج',k:'ک',l:'ل',m:'م',n:'ن',o:'او',p:'پ',q:'ق',r:'ر',s:'س',t:'ٹ',u:'یو',v:'و',w:'ڈبلیو',x:'ایکس',y:'وائے',z:'زی' };
    return text.replace(/\b([a-z])\b/gi, x => m[x.toLowerCase()] || x).trim();
}

// ===============================================
// PROCESS
// ===============================================

async function processVoiceText() {
    let text = document.getElementById('voiceTextarea').value.trim();
    if (!text) { showNotification('error','No text!'); return; }
    text = convertEnglishToUrdu(text);
    
    const sep = /اگلا|اگلی|اگلے|اگے|اگا|اگہ|اگل|next|آگلا|آگے|،|,/gi;
    let segments = text.split(sep).map(s => s.trim()).filter(s => s.length > 1);
    if (segments.length === 0) { let c = text.replace(sep,' ').replace(/\s+/g,' ').trim(); if (c.length > 2) segments = [c]; }
    
    console.log('Segments:', segments);
    
    if (segments.length === 0) {
        document.getElementById('processedItems').innerHTML = '<div class="alert alert-warning">⚠️ No names found</div>';
        return;
    }
    
    document.getElementById('processedItems').innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary"></div> Searching...</div>';
    
    let notFound = [];
    
    for (const seg of segments) {
        const result = await searchProductDeep(seg);
        if (result.found) {
            if (!window.voiceRecognizedProducts) window.voiceRecognizedProducts = [];
            const ex = window.voiceRecognizedProducts.find(p => p.id === result.product.id);
            if (!ex) {
                window.voiceRecognizedProducts.push({
                    ...result.product,
                    _exact: result.exactMatch === true,
                    _searchedFor: seg,
                    _allOptions: result.allMatches || [result.product],
                    _qty: 1
                });
            }
        } else {
            notFound.push(seg);
        }
    }
    
    if (window.voiceRecognizedProducts.length > 0) {
        displayRecognizedProducts(window.voiceRecognizedProducts);
        if (notFound.length > 0) {
            const d = document.createElement('div');
            d.className = 'alert alert-warning mt-2';
            d.innerHTML = '<strong>⚠️ Not Found:</strong> ' + notFound.map(n => '"' + n + '"').join(', ');
            document.getElementById('processedItems').appendChild(d);
        }
    } else {
        document.getElementById('processedItems').innerHTML = '<div class="alert alert-danger">❌ No products found.<br><small>Tried: ' + notFound.join(', ') + '</small></div>';
    }
}

// ===============================================
// DEEP PRODUCT SEARCH (Word-by-Word)
// ===============================================

async function searchProductDeep(namePart) {
    namePart = namePart.trim();
    
    // Clean: remove units, numbers
    const rw = ['کلو','گرام','پیس','پیکٹ','ڈبہ','گتہ','بوتل','لیٹر','درجن','ٹرے','پاؤ','کے','کی','ہے','ہیں','تھا','تھی','kg','piece','packet','میں','سے','پر','کا','ml','ایم','ایل','ایم ایل','ملی','ملی لیٹر'];
    let clean = namePart;
    for (const w of rw) clean = clean.replace(new RegExp('\\b'+w+'\\b','gi'), '').trim();
    clean = clean.replace(/\b\d+\b/g,'').replace(/\s+/g,' ').trim();
    
    console.log('Searching:', clean);
    if (clean.length < 2) return { found: false };
    
    // Strategy 1: Full search
    let all = await searchAPI(clean);
    
    // Strategy 2: If no results, try word by word
    if (all.length === 0) {
        const words = clean.split(/\s+/);
        for (const w of words) {
            if (w.length >= 3) {
                const r = await searchAPI(w);
                all = [...all, ...r];
            }
        }
        all = all.filter((p,i,s) => s.findIndex(t => t.id === p.id) === i);
    }
    
    // Strategy 3: Try with 3-4 character chunks
    if (all.length === 0 && clean.length >= 3) {
        for (let i = 0; i <= clean.length - 3; i++) {
            const chunk = clean.substring(i, i + 4);
            if (chunk.length >= 3) {
                const r = await searchAPI(chunk);
                all = [...all, ...r];
            }
        }
        all = all.filter((p,i,s) => s.findIndex(t => t.id === p.id) === i);
    }
    
    if (all.length === 0) return { found: false };
    
    const exact = all.find(p => p.name === clean);
    if (exact) return { found: true, exactMatch: true, product: exact, allMatches: all, searchedFor: namePart };
    
    return { found: true, exactMatch: false, product: all[0], allMatches: all, searchedFor: namePart };
}

async function searchAPI(query) {
    try {
        const r = await fetch(`api/search_product.php?q=${encodeURIComponent(query)}`);
        return await r.json();
    } catch(e) { return []; }
}

// ===============================================
// DISPLAY (Qty Input, No Match Column)
// ===============================================

function displayRecognizedProducts(products) {
    if (!products) products = [];
    let html = '';
    
    if (products.length > 0) {
        html += '<h6 class="border-bottom pb-2">📋 Products (' + products.length + '):</h6>';
        html += '<table class="table table-sm"><thead><tr><th>Product</th><th>Qty</th><th>Action</th></tr></thead><tbody>';
        
        products.forEach((p, i) => {
            const hasOpts = p._allOptions && p._allOptions.length > 1;
            const qty = p._qty || 1;
            
            let cell = '';
            if (hasOpts) {
                cell = `<select class="form-select form-select-sm" onchange="changeVoiceProduct(${i},this.value)" style="min-width:200px;">`;
                p._allOptions.forEach(op => {
                    cell += `<option value="${op.id}" ${op.id===p.id?'selected':''}>${op.name} (${op.unit||'Piece'})</option>`;
                });
                cell += `</select>`;
                if (!p._exact) cell += `<small class="text-muted d-block">Searched: "${p._searchedFor}" - ${p._allOptions.length} matches</small>`;
            } else {
                cell = `<strong>${p.name}</strong>`;
                if (!p._exact) cell += `<br><small class="text-muted">Best match for: ${p._searchedFor}</small>`;
            }
            
            html += `<tr>
                <td>${cell}</td>
                <td><input type="number" id="voiceQty${i}" value="${qty}" class="form-control form-control-sm" style="width:70px;" min="0.001" step="0.001"></td>
                <td><button class="btn btn-sm btn-danger" onclick="removeRecognizedProduct(${i})"><i class="bi bi-trash"></i></button></td>
            </tr>`;
        });
        html += '</tbody></table>';
    } else {
        html += '<p class="text-muted">No products yet.</p>';
    }
    
    html += `<div class="mt-3 d-flex justify-content-center gap-2">
        <button class="btn btn-success btn-lg" onclick="addRecognizedToCart()">Add ${products.length} to Cart</button>
        <button class="btn btn-outline-danger" onclick="clearVoiceText()">Clear All</button>
    </div>`;
    document.getElementById('processedItems').innerHTML = html;
}

function changeVoiceProduct(index, newId) {
    if (!window.voiceRecognizedProducts || !window.voiceRecognizedProducts[index]) return;
    const opts = window.voiceRecognizedProducts[index]._allOptions;
    if (!opts) return;
    const np = opts.find(p => p.id == newId);
    if (np) window.voiceRecognizedProducts[index] = { ...np, _exact: false, _searchedFor: window.voiceRecognizedProducts[index]._searchedFor, _allOptions: opts, _qty: window.voiceRecognizedProducts[index]._qty || 1 };
}

function removeRecognizedProduct(index) {
    if (!window.voiceRecognizedProducts) return;
    window.voiceRecognizedProducts.splice(index, 1);
    displayRecognizedProducts(window.voiceRecognizedProducts);
}

// ===============================================
// ADD TO CART (With Custom Qty)
// ===============================================

async function addRecognizedToCart() {
    const products = window.voiceRecognizedProducts || [];
    if (products.length === 0) { showNotification('error','No products!'); return; }
    
    for (let i = 0; i < products.length; i++) {
        const qtyInput = document.getElementById('voiceQty' + i);
        const qty = qtyInput ? parseFloat(qtyInput.value) || 1 : 1;
        const p = products[i];
        await addToCartWithQuantity(p.id, p.name, p.unit||'Piece', p.current_stock, qty);
    }
    
    renderCart(); updateTotal(); clearVoiceText(); stopVoiceInput();
    showNotification('success', '✅ Added ' + products.length + ' to cart!');
}

console.log('Voice.js vFinal loaded');
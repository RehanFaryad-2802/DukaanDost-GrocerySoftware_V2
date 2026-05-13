// ===============================================
// VOICE BATCH INPUT SYSTEM - IMPROVED
// ===============================================

window.voiceRecognizedProducts = window.voiceRecognizedProducts || [];
let recognition = null;
let isListening = false;
let finalTranscript = "";

// Urdu number words → digits
const URDU_NUMBERS = {
  ایک: 1,
  دو: 2,
  تین: 3,
  چار: 4,
  پانچ: 5,
  چھ: 6,
  چھے: 6,
  سات: 7,
  آٹھ: 8,
  نو: 9,
  دس: 10,
  گیارہ: 11,
  بارہ: 12,
  تیرہ: 13,
  چودہ: 14,
  پندرہ: 15,
  سولہ: 16,
  سترہ: 17,
  اٹھارہ: 18,
  انیس: 19,
  بیس: 20,
  پچیس: 25,
  تیس: 30,
  پینتیس: 35,
  چالیس: 40,
  پچاس: 50,
  ساٹھ: 60,
  ستر: 70,
  اسی: 80,
  نوے: 90,
  سو: 100,
  one: 1,
  two: 2,
  three: 3,
  four: 4,
  five: 5,
  six: 6,
  seven: 7,
  eight: 8,
  nine: 9,
  ten: 10,
  half: "0.5",
  آدھا: "0.5",
  آدھی: "0.5",
  ڈیڑھ: "1.5",
  ڈھائی: "2.5",
};

const UNIT_WORDS = [
  "گرام",
  "پیس",
  "پیکٹ",
  "ڈبہ",
  "گتہ",
  "بوتل",
  "درجن",
  "ٹرے",
  "پاؤ",
  "تھیلی",
  "kg",
  "g",
  "liter",
  "ml",
  "piece",
  "packet",
  "box",
  "dozen",
  "سیر",
  "من",
  "کے",
  "کی",
  "ہے",
  "ہیں",
  "تھا",
  "تھی",
  "میں",
  "سے",
  "پر",
  "کا",
  "کو",
  "نے",
  "والا",
  "والی",
  "والے",
];

// Separators between products
const SEPARATORS =
  /اگلا|اگلی|اگلے|اگے|اگا|اگہ|اگل|next|آگلا|آگے|پھر|اور پھر|،|,|\n/gi;

function isSpeechSupported() {
  return "webkitSpeechRecognition" in window || "SpeechRecognition" in window;
}

function toggleVoiceInput() {
  const modal = new bootstrap.Modal(document.getElementById("voiceModal"));
  modal.show();
  setupVoiceModal();
  if (!isSpeechSupported()) {
    document.getElementById("voiceStatus").innerHTML =
      "⚠️ Offline Mode: Type product names manually below";
    document.getElementById("startListenBtn").style.display = "none";
    document.getElementById("stopListenBtn").style.display = "none";
  } else {
    initSpeechRecognition();
  }
}

function setupVoiceModal() {
  document.getElementById("voiceTextarea").value = "";
  document.getElementById("processedItems").innerHTML = "";
  document.getElementById("interimResult").textContent = "";
  document.getElementById("voiceStatus").className =
    "alert alert-info text-center";
  document.getElementById("voiceStatus").innerHTML =
    '<strong>🎤 Voice Input</strong><br>Say product names separated by <b>"اگلا"</b> or comma. Include quantity (e.g. چینی پانچ کلو)';
  if (
    window.voiceRecognizedProducts &&
    window.voiceRecognizedProducts.length > 0
  ) {
    displayRecognizedProducts(window.voiceRecognizedProducts);
  }
}

function initSpeechRecognition() {
  if (recognition) {
    try {
      recognition.abort();
    } catch (e) {}
  }
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  recognition = new SR();
  recognition.lang = "ur-PK";
  recognition.continuous = true;
  recognition.interimResults = true;
  recognition.maxAlternatives = 3;
  recognition.onstart = () => {
    isListening = true;
    document.getElementById("startListenBtn").style.display = "none";
    document.getElementById("stopListenBtn").style.display = "inline-block";
    document.getElementById("voiceStatus").innerHTML = "🔴 بولیں۔۔۔";
    finalTranscript = "";
  };
  recognition.onresult = (event) => {
    let interim = "";
    for (let i = event.resultIndex; i < event.results.length; i++) {
      if (event.results[i].isFinal) {
        // Pick best alternative
        finalTranscript += event.results[i][0].transcript + " ";
      } else {
        interim += event.results[i][0].transcript;
      }
    }
    document.getElementById("interimResult").textContent = interim || "";
    if (finalTranscript)
      document.getElementById("voiceTextarea").value = finalTranscript.trim();
  };
  recognition.onerror = (e) => {
    if (e.error !== "no-speech") {
      document.getElementById("voiceStatus").innerHTML = "⚠️ Error: " + e.error;
    }
    resetVoiceButtons();
  };
  recognition.onend = () => {
    if (isListening) {
      try {
        recognition.start();
      } catch (e) {}
    }
  };
}

function startListening() {
  finalTranscript = "";
  document.getElementById("voiceTextarea").value = "";
  if (recognition) {
    try {
      recognition.start();
    } catch (e) {}
  }
}

function stopListening() {
  isListening = false;
  if (recognition) recognition.stop();
  document.getElementById("voiceStatus").innerHTML =
    '✅ Review & click "Verify & Add to Cart"';
  resetVoiceButtons();
}

function resetVoiceButtons() {
  document.getElementById("startListenBtn").style.display = "inline-block";
  document.getElementById("stopListenBtn").style.display = "none";
}

function stopVoiceInput() {
  isListening = false;
  if (recognition) recognition.abort();
  const modalEl = document.getElementById("voiceModal");
  const modal = bootstrap.Modal.getInstance(modalEl);
  if (modal) modal.hide();
}

function clearVoiceText() {
  document.getElementById("voiceTextarea").value = "";
  document.getElementById("processedItems").innerHTML = "";
  window.voiceRecognizedProducts = [];
}

// ===============================================
// QUANTITY EXTRACTION
// ===============================================

function extractQuantityFromSegment(segment) {
  let qty = 1;
  let cleanedSegment = segment;

  // Step 1: Extract Urdu number words ONLY if followed by a unit word
  // e.g. "پانچ کلو" → qty=5, but "50 والا" → NOT quantity
  const unitPattern =
    "کلو|لیٹر|گرام|پیس|پیکٹ|ڈبہ|گتہ|بوتل|درجن|ٹرے|پاؤ|kg|g|ml|piece|packet";

  for (const [word, val] of Object.entries(URDU_NUMBERS)) {
    // Only extract if the number word appears standalone or before a unit
    const regex = new RegExp(
      "(?<![\\u0600-\\u06FF])" + word + "(?![\\u0600-\\u06FF])",
    );
    if (regex.test(cleanedSegment)) {
      qty = parseFloat(val);
      cleanedSegment = cleanedSegment.replace(word, "").trim();
      break;
    }
  }

  // Step 2: Extract Arabic/English digits ONLY if followed by unit word
  // "5 کلو" → qty=5  |  "50 والا" → NOT qty (it's part of product name)
  const digitWithUnit = new RegExp(
    "(\\d+\\.?\\d*)\\s*(?:" + unitPattern + ")",
    "i",
  );
  const digitMatch = cleanedSegment.match(digitWithUnit);
  if (digitMatch) {
    qty = parseFloat(digitMatch[1]);
    cleanedSegment = cleanedSegment.replace(digitMatch[1], "").trim();
  }

  // Step 3: Remove unit words (but keep والا/والی as they're part of product names)
  const stripUnits = [
    "کلو",
    "گرام",
    "پیس",
    "پیکٹ",
    "ڈبہ",
    "گتہ",
    "بوتل",
    "درجن",
    "ٹرے",
    "پاؤ",
    "تھیلی",
    "kg",
    "g",
    "liter",
    "ml",
    "piece",
    "packet",
    "box",
    "dozen",
  ];
  for (const uw of stripUnits) {
    cleanedSegment = cleanedSegment
      .replace(new RegExp("(?<![\\S])" + uw + "(?![\\S])", "gi"), "")
      .trim();
  }

  // Step 4: Remove grammar words
  const grammarWords = [
    "کے",
    "کی",
    "ہے",
    "ہیں",
    "تھا",
    "تھی",
    "میں",
    "سے",
    "پر",
    "کا",
    "کو",
    "نے",
  ];
  for (const gw of grammarWords) {
    cleanedSegment = cleanedSegment
      .replace(
        new RegExp(
          "(?<![\\u0600-\\u06FF])" + gw + "(?![\\u0600-\\u06FF])",
          "g",
        ),
        "",
      )
      .trim();
  }

  cleanedSegment = cleanedSegment.replace(/\s+/g, " ").trim();

  return { qty, productName: cleanedSegment };
}

// ===============================================
// SCORING: how well does a product name match search
// ===============================================

function scoreMatch(productName, searchQuery) {
  const p = productName.trim();
  const q = searchQuery.trim();

  if (p === q) return 100; // exact

  // Count matching words
  const pWords = p.split(/\s+/).filter((w) => w.length > 1);
  const qWords = q.split(/\s+/).filter((w) => w.length > 1);

  if (qWords.length === 0) return 0;

  let matchedWords = 0;
  let matchedChars = 0;

  for (const qw of qWords) {
    for (const pw of pWords) {
      if (pw === qw) {
        matchedWords += 2; // exact word = bonus
        matchedChars += qw.length;
        break;
      } else if (pw.includes(qw) || qw.includes(pw)) {
        matchedWords += 1;
        matchedChars += Math.min(pw.length, qw.length);
        break;
      }
    }
  }

  // Base score from word overlap
  let score = Math.round((matchedWords / (qWords.length * 2)) * 80);

  // Bonus: if matched characters cover most of query length
  const queryCoverage = matchedChars / Math.max(q.length, 1);
  score += Math.round(queryCoverage * 20);

  // Penalty: if product name is much longer than query (likely wrong match)
  const lengthRatio = q.length / Math.max(p.length, 1);
  if (lengthRatio < 0.3) score = Math.round(score * 0.5);

  // Penalty: if only 1 short word matched
  if (matchedWords <= 1 && qWords.length > 1) score = Math.round(score * 0.4);

  return Math.min(score, 99); // never 100 unless exact
}

// ===============================================
// PROCESS
// ===============================================

async function processVoiceText() {
  let text = document.getElementById("voiceTextarea").value.trim();
  if (!text) {
    showNotification("error", "No text to process!");
    return;
  }

  // Split into segments
  let segments = text
    .split(SEPARATORS)
    .map((s) => s.trim())
    .filter((s) => s.length > 1);
  if (segments.length === 0) {
    document.getElementById("processedItems").innerHTML =
      '<div class="alert alert-warning">⚠️ No products found in text</div>';
    return;
  }

  document.getElementById("processedItems").innerHTML = `
        <div class="text-center py-3">
            <div class="spinner-border text-primary"></div>
            <p class="mt-2 text-muted">Searching ${segments.length} product(s)...</p>
        </div>`;

  window.voiceRecognizedProducts = [];
  let notFound = [];

  for (const seg of segments) {
    const { qty, productName } = extractQuantityFromSegment(seg);
    console.log("Segment:", seg, "→ qty:", qty, "product:", productName);

    if (productName.length < 2) continue;

    const result = await searchProductDeep(productName);

    if (result.found) {
      const existing = window.voiceRecognizedProducts.find(
        (p) => p.id === result.product.id,
      );
      if (existing) {
        // Same product found again — add quantity
        existing._qty = (existing._qty || 1) + qty;
      } else {
        window.voiceRecognizedProducts.push({
          ...result.product,
          _exact: result.exactMatch,
          _confidence: result.confidence,
          _searchedFor: productName,
          _allOptions: result.allMatches || [result.product],
          _qty: qty,
        });
      }
    } else {
      notFound.push(seg);
    }
  }

  if (window.voiceRecognizedProducts.length > 0) {
    displayRecognizedProducts(window.voiceRecognizedProducts);
    if (notFound.length > 0) {
      const d = document.createElement("div");
      d.className = "alert alert-warning mt-2";
      d.innerHTML =
        "<strong>⚠️ Not Found:</strong> " +
        notFound.map((n) => `"${n}"`).join(", ");
      document.getElementById("processedItems").appendChild(d);
    }
  } else {
    document.getElementById("processedItems").innerHTML =
      '<div class="alert alert-danger">❌ No products matched.<br><small>Tried: ' +
      segments.join(" | ") +
      "</small></div>";
  }
}

// ===============================================
// DEEP SEARCH WITH SCORING
// ===============================================

async function searchProductDeep(namePart) {
  namePart = namePart.trim();
  if (namePart.length < 2) return { found: false };

  // Strategy 1: full phrase
  let all = await searchAPI(namePart);

  // Strategy 2: individual words (length >= 3 only — avoid noise)
  if (all.length === 0) {
    const words = namePart.split(/\s+/).filter((w) => w.length >= 3);
    for (const w of words) {
      const r = await searchAPI(w);
      all = [...all, ...r];
    }
    all = dedup(all);
  }

  // Strategy 3: substring chunks — ONLY if still nothing found
  if (all.length === 0 && namePart.length >= 4) {
    for (let i = 0; i <= namePart.length - 4; i++) {
      const chunk = namePart.substring(i, i + 4);
      const r = await searchAPI(chunk);
      all = [...all, ...r];
    }
    all = dedup(all);
  }

  if (all.length === 0) return { found: false };

  // Score all
  const scored = all
    .map((p) => ({
      ...p,
      _score: scoreMatch(p.name, namePart),
    }))
    .sort((a, b) => b._score - a._score);

  const best = scored[0];

  // MINIMUM CONFIDENCE THRESHOLD — reject bad matches
  if (best._score < 25) return { found: false };

  const isExact = best.name === namePart;

  return {
    found: true,
    exactMatch: isExact,
    confidence: best._score,
    product: best,
    allMatches: scored.filter((p) => p._score >= 20), // only show reasonable options
  };
}

function dedup(arr) {
  return arr.filter((p, i, s) => s.findIndex((t) => t.id === p.id) === i);
}

async function searchAPI(query) {
  try {
    const r = await fetch(
      `api/search_product.php?q=${encodeURIComponent(query)}`,
    );
    const data = await r.json();
    return Array.isArray(data) ? data : [];
  } catch (e) {
    return [];
  }
}

// ===============================================
// DISPLAY
// ===============================================

function displayRecognizedProducts(products) {
  if (!products || products.length === 0) {
    document.getElementById("processedItems").innerHTML =
      '<p class="text-muted">No products.</p>';
    return;
  }

  let html = `<h6 class="border-bottom pb-2">📋 Found ${products.length} product(s) — review before adding:</h6>`;
  html +=
    '<table class="table table-sm table-bordered"><thead><tr><th>Product</th><th style="width:80px">Qty</th><th style="width:40px"></th></tr></thead><tbody>';

  products.forEach((p, i) => {
    const hasOpts = p._allOptions && p._allOptions.length > 1;
    const qty = p._qty || 1;
    const isLowConf = !p._exact && (p._confidence || 0) < 50;
    const rowClass = isLowConf
      ? "table-warning"
      : p._exact
        ? ""
        : "table-light";

    let cell = "";
    if (hasOpts) {
      cell = `<select class="form-select form-select-sm" onchange="changeVoiceProduct(${i}, this.value)">`;
      p._allOptions.forEach((op) => {
        cell += `<option value="${op.id}" ${op.id === p.id ? "selected" : ""}>${op.name} (${op.unit || ""})</option>`;
      });
      cell += `</select>`;
    } else {
      cell = `<strong dir="rtl">${p.name}</strong>`;
    }

    const confidenceBadge = p._exact
      ? `<span class="badge bg-success ms-1">✓ Exact</span>`
      : isLowConf
        ? `<span class="badge bg-warning text-dark ms-1">⚠️ Low match — please verify</span>`
        : `<span class="badge bg-info ms-1">${p._confidence || 0}% match</span>`;

    cell += `<br><small class="text-muted" dir="rtl">Searched: "${p._searchedFor}"</small> ${confidenceBadge}`;

    html += `<tr class="${rowClass}">
            <td>${cell}</td>
            <td><input type="number" id="voiceQty${i}" value="${qty}" class="form-control form-control-sm" min="0.001" step="0.001"></td>
            <td><button class="btn btn-sm btn-outline-danger" onclick="removeRecognizedProduct(${i})"><i class="bi bi-x"></i></button></td>
        </tr>`;
  });

  html += "</tbody></table>";
  html += `<div class="d-flex gap-2 justify-content-end mt-2">
        <button class="btn btn-outline-danger btn-sm" onclick="clearVoiceText()"><i class="bi bi-trash"></i> Clear</button>
        <button class="btn btn-success" onclick="addRecognizedToCart()"><i class="bi bi-cart-plus"></i> Add ${products.length} to Cart</button>
    </div>`;

  document.getElementById("processedItems").innerHTML = html;
}

function changeVoiceProduct(index, newId) {
  if (!window.voiceRecognizedProducts?.[index]) return;
  const opts = window.voiceRecognizedProducts[index]._allOptions;
  const np = opts?.find((p) => p.id == newId);
  if (np) {
    window.voiceRecognizedProducts[index] = {
      ...np,
      _exact: false,
      _confidence: 100,
      _searchedFor: window.voiceRecognizedProducts[index]._searchedFor,
      _allOptions: opts,
      _qty: window.voiceRecognizedProducts[index]._qty || 1,
    };
  }
}

function removeRecognizedProduct(index) {
  window.voiceRecognizedProducts?.splice(index, 1);
  displayRecognizedProducts(window.voiceRecognizedProducts);
}

// ===============================================
// ADD TO CART
// ===============================================

async function addRecognizedToCart() {
  const products = window.voiceRecognizedProducts || [];
  if (products.length === 0) {
    showNotification("error", "No products to add!");
    return;
  }

  let added = 0;
  for (let i = 0; i < products.length; i++) {
    const qtyInput = document.getElementById("voiceQty" + i);
    const qty = qtyInput ? parseFloat(qtyInput.value) || 1 : 1;
    const p = products[i];
    if (p.id && qty > 0) {
      await addToCartWithQuantity(
        p.id,
        p.name,
        p.unit || "Piece",
        p.current_stock || 999999,
        qty,
      );
      added++;
    }
  }

  renderCart();
  updateTotal();
  clearVoiceText();
  stopVoiceInput();
  showNotification("success", `✅ Added ${added} product(s) to cart!`);
}

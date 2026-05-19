<!-- Calculator Block -->
<div class="col-md-12 mt-3">
    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">Calculator</h5>
        </div>
        <div class="card-body p-3">

            <!-- Result Display -->
            <div class="border p-3 rounded bg-light mb-3" style="min-height: 85px;">
                <div class="row align-items-center h-100">
                    <div class="col-7">
                        <div id="calc-expression" class="text-start fs-5 text-muted">
                            0
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <div id="calc-result" class="fs-2 fw-bold" style="color:#28a745;">
                            = 0
                        </div>
                    </div>
                </div>
            </div>

            <!-- History and Clear Buttons -->
            <div class="text-end mb-3">
                <button type="button" onclick="showHistoryModal()" class="btn btn-sm btn-outline-secondary"
                    title="Show entered values" aria-label="Show entered values">
                    <i class="bi bi-list-ul"></i>
                </button>
                <button type="button" onclick="clearCalculator()" class="btn btn-sm btn-outline-danger ms-1"
                    title="Clear calculator" aria-label="Clear calculator">
                    <i class="bi bi-trash"></i>
                </button>
            </div>

            <!-- Input Box -->
            <input type="text" id="calc-input" class="form-control" style="font-size: 20px; height: 52px;"
                placeholder="Enter number" onkeypress="handleKeyPress(event)" oninput="livePreview()">

        </div>
    </div>
</div>

<!-- History Modal Container -->
<div id="historyModalContainer"></div>

<script>
    // Chain Calculator - Fixed Version
    let calcAnswer = 0;
    let calcOperatorPending = null;
    let historyEntries = [];

    function formatNumericString(value) {
        if (!value) return '';
        const normalized = value.replace(/,/g, '');
        if (normalized === '-' || normalized === '.' || normalized === '-.') return normalized;

        const sign = normalized.startsWith('-') ? '-' : '';
        const absValue = sign ? normalized.slice(1) : normalized;
        const parts = absValue.split('.');
        const integerPart = parts[0].replace(/^0+(?=\d)/, '');
        const formattedInt = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return sign + (formattedInt || '0') + (parts[1] !== undefined ? '.' + parts[1] : '');
    }

    function formatNumber(value) {
        if (value === null || value === undefined || Number.isNaN(value)) return '0';
        return formatNumericString(value.toString());
    }

    function parseInputValue() {
        const input = document.getElementById('calc-input');
        const raw = input.value.replace(/,/g, '');
        return parseFloat(raw) || 0;
    }

    function updateDisplay(expression, result) {
        document.getElementById('calc-expression').textContent = expression || '0';
        const res = parseFloat(result) || 0;
        document.getElementById('calc-result').textContent = `= ${formatNumber(res)}`;
    }

    function handleKeyPress(e) {
        if (e.key.toLowerCase() === 'c') {
            e.preventDefault();
            clearCalculator();
            return;
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            finalizeCalculation();
        }
        if (['+', '-', '*', '/'].includes(e.key)) {
            e.preventDefault();
            processOperator(e.key);
        }
    }

    function processOperator(op) {
        const currentVal = parseInputValue();

        if (calcOperatorPending !== null) {
            calcAnswer = calculate(calcAnswer, currentVal, calcOperatorPending);
        } else {
            calcAnswer = currentVal;
        }

        // Only add to history if value is greater than 0
        if (currentVal > 0) {
            addToHistory(currentVal, op);
        }

        calcOperatorPending = op;
        const input = document.getElementById('calc-input');
        input.value = '';
        livePreview();
    }

    function livePreview() {
        const input = document.getElementById('calc-input');
        const oldValue = input.value;
        const cursorPosition = input.selectionStart;

        const formattedValue = formatNumericString(oldValue);
        const rawBeforeCursor = oldValue.slice(0, cursorPosition).replace(/,/g, '');
        const formattedBeforeCursor = formatNumericString(rawBeforeCursor);

        input.value = formattedValue;
        input.setSelectionRange(formattedBeforeCursor.length, formattedBeforeCursor.length);

        const currentVal = parseInputValue();

        if (calcOperatorPending === null) {
            updateDisplay(input.value || '0', currentVal);
        } else if (input.value === '') {
            updateDisplay(`${formatNumber(calcAnswer)} ${calcOperatorPending}`, calcAnswer);
        } else {
            const preview = calculate(calcAnswer, currentVal, calcOperatorPending);
            updateDisplay(`${formatNumber(calcAnswer)} ${calcOperatorPending} ${input.value} =`, preview);
        }
    }

    function calculate(a, b, op) {
        switch (op) {
            case '+': return a + b;
            case '-': return a - b;
            case '*': return a * b;
            case '/': return b !== 0 ? a / b : 0;
            default: return b;
        }
    }

    function finalizeCalculation() {
        const currentVal = parseInputValue();

        if (calcOperatorPending !== null) {
            calcAnswer = calculate(calcAnswer, currentVal, calcOperatorPending);
            if (currentVal > 0) {
                addToHistory(currentVal, calcOperatorPending);
            }
        } else {
            calcAnswer = currentVal;
        }

        updateDisplay(formatNumber(calcAnswer), calcAnswer);
        document.getElementById('calc-input').value = formatNumber(calcAnswer);
        calcOperatorPending = null;
    }

    function addToHistory(value, operator) {
        if (value > 0) {
            historyEntries.push({ value: parseFloat(value), operator: operator || '=' });
        }
    }

    // Compact History Modal
    function showHistoryModal() {
        const container = document.getElementById('historyModalContainer');

        let html = '';
        historyEntries.forEach((item, index) => {
            const operatorText = item.operator === '+' ? '' : ` ${item.operator}`;
            html += `
            <div class="col-12 mb-1">
                <div style="padding: 6px 10px; font-size: 16px;">
                    <span class="text-muted">${index + 1}) </span> 
                    <strong>${formatNumber(item.value)}${operatorText}</strong>
                </div>
            </div>
        `;
        });

        if (historyEntries.length === 0) {
            html = `<div class="text-center text-muted py-5">No values entered yet</div>`;
        }

        const totalEntered = calcAnswer;

        container.innerHTML = `
    <div class="modal fade" id="historyModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-list-ul"></i> Entered Values</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3" style="max-height: 460px; overflow-y: auto;">
                    <div class="row g-1">
                        ${html}
                    </div>
                </div>
                <div class="px-3 pb-3">
                    <div class="border rounded p-3 bg-light">
                        <div class="text-muted small">Calculated total</div>
                        <div class="fs-5 fw-bold">${formatNumber(totalEntered)}</div>
                    </div>
                </div>

                <div class="modal-footer">
                    ${historyEntries.length > 0 ? `<button type="button" class="btn btn-danger" onclick="clearCalculator()">Clear History (${historyEntries.length})</button>` : ''}                
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>`;

        new bootstrap.Modal(document.getElementById('historyModal')).show();
    }

    function clearCalculator() {
        const historyModalEl = document.getElementById('historyModal');
        if (historyModalEl) {
            const modalInstance = bootstrap.Modal.getInstance(historyModalEl) || new bootstrap.Modal(historyModalEl);
            modalInstance.hide();
        }

        document.getElementById('calc-input').value = '';
        calcAnswer = 0;
        calcOperatorPending = null;
        historyEntries = [];
        updateDisplay('0', 0);

        const container = document.getElementById('historyModalContainer');
        if (container) {
            container.innerHTML = '';
        }
    }
</script>
<!-- Voice Input Modal -->
<div class="modal fade" id="voiceModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-mic"></i> 🎤 Voice Batch Input
                </h5>
                <button type="button" class="btn-close btn-close-white" onclick="stopVoiceInput()"></button>
            </div>
            <div class="modal-body">
                <!-- Status -->
                <div id="voiceStatus" class="alert alert-info text-center">
                    <i class="bi bi-mic-fill"></i> Click <strong>Start Listening</strong> and speak all products
                </div>

                <!-- Controls -->
                <div class="text-center mb-3">
                    <button class="btn btn-success btn-lg" id="startListenBtn" onclick="startListening()">
                        <i class="bi bi-mic-fill"></i> Start Listening
                    </button>
                    <button class="btn btn-danger btn-lg" id="stopListenBtn" onclick="stopListening()"
                        style="display: none;">
                        <i class="bi bi-stop-circle"></i> Stop & Process
                    </button>
                </div>

                <!-- Interim Result -->
                <div id="interimResult" class="text-muted text-center mb-3"
                    style="min-height: 30px; font-size: 18px;"></div>

                <!-- Review Textarea -->
                <div class="mb-3">
                    <label class="fw-bold">📝 Review & Edit (one product per line):</label>
                    <textarea id="voiceTextarea" class="form-control" rows="8" dir="rtl"
                        placeholder="Spoken products will appear here..."
                        style="font-size: 16px; font-family: 'Noto Nastaliq Urdu', serif;"></textarea>
                    <small class="text-muted">
                        Format: <code>ProductName Quantity Unit</code> (e.g., چینی 5 کلو)
                    </small>
                </div>

                <!-- Processed Items Preview -->
                <div id="processedItems" style="max-height: 200px; overflow-y: auto;"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="stopVoiceInput()">Cancel</button>
                <button class="btn btn-primary" onclick="processVoiceText()">
                    <i class="bi bi-check-circle"></i> Verify & Add to Cart
                </button>
            </div>
        </div>
    </div>
</div>
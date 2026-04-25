<!-- Product Search -->
<div class="card mb-3">
    <div class="p-2">
        <div class="input-group mb-3">
            <input dir="rtl" type="text" id="search_product" data-voice="true"
                class="form-control form-control-lg" placeholder="تلاش کریں۔۔۔"
                oninput="handleSearchInput(this.value)" autofocus>
            <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                <i class="bi bi-x"></i>
            </button>
            <button class="btn btn-outline-success" type="button" id="voiceBtn" onclick="toggleVoiceInput()"
                title="🎤 Voice Add Products (Batch)">
                <i class="bi bi-mic"></i>
            </button>
        </div>
        <div id="search_results" class="list-group" style="max-height: 200px; overflow-y: auto;"></div>
    </div>
</div>
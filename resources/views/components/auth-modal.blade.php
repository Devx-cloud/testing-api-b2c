{{-- resources/views/components/auth-modal.blade.php --}}
<div class="modal fade" id="authModal" tabindex="-1" aria-labelledby="authModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title" id="authModalLabel">
                    <i class="fa-solid fa-shield-halved me-2 text-primary"></i>Pengaturan Otorisasi
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-4">Masukkan API Key Anda (header <code>Token</code>). Kunci ini akan disimpan sementara di browser dan digunakan untuk semua request selanjutnya.</p>
                <div class="mb-3">
                    <label for="modalApiKey" class="form-label fw-bold">Token</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-key text-muted"></i></span>
                        <input type="password" class="form-control border-start-0" id="modalApiKey" placeholder="Masukkan API Key di sini">
                        <button class="btn btn-outline-secondary" type="button" id="toggleApiKeyVisibility">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    <i class="fa-solid fa-times me-2"></i>Batal
                </button>
                <button type="button" class="btn btn-primary" onclick="saveApiKey()">
                    <i class="fa-solid fa-save me-2"></i>Simpan
                </button>
            </div>
        </div>
    </div>
</div>

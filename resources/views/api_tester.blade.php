<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokumentasi API Tokodaring</title>

    {{-- Bootstrap & Font --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    {{-- Ikon --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    {{-- Syntax Highlighting --}}
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/theme/darcula.min.css">

    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Inter', sans-serif;
        }

        .card {
            border-radius: 0.75rem;
        }

        .endpoint-path {
            font-family: 'Courier New', Courier, monospace;
            background-color: #e9ecef;
            color: #495057;
            padding: 0.25rem 0.6rem;
            border-radius: 0.3rem;
            font-size: 0.9em;
        }

        .method-badge {
            font-weight: 700;
            padding: 0.5em 0.9em;
        }

        .response-area,
        pre,
        code {
            border-radius: 0 !important;
        }

        pre {
            padding: 1.25rem;
        }

        .border-bottom-radius {
            border-bottom-left-radius: 0.75rem;
            border-bottom-right-radius: 0.75rem;
        }

        .tab-content pre {
            max-height: 600px;
            overflow-y: auto;
        }

        .response-container pre code.hljs {
            padding: 1.25rem;
            background-color: #282c34 !important;
        }

        .request-body-section textarea {
            background-color: #282c34 !important;
            color: #abb2bf !important;
        }

        .nav-tabs-bordered .nav-link {
            border: none;
            border-bottom: 2px solid transparent;
            color: #6c757d;
        }

        .nav-tabs-bordered .nav-link.active {
            border-color: #0d6efd;
            color: #0d6efd;
            background-color: transparent;
        }

        .tab-content {
            border-radius: 0 0 0.75rem 0.75rem;
            border: 1px solid #dee2e6;
            border-top: none;
            overflow: hidden;
        }

        .tab-pane {
            position: relative;
        }

        .tab-content pre {
            margin: 0;
            border-radius: 0 !important;
            max-height: 400px;
            overflow-y: auto;
        }

        .btn-copy {
            position: absolute;
            top: 1.6rem;
            right: 1.6rem;
            z-index: 10;
            opacity: 0.6;
            transition: all 0.2s ease-in-out;
        }

        .btn-copy:hover {
            opacity: 1;
        }
    </style>
</head>

<body>
    {{-- Memanggil komponen Modal --}}
    <x-auth-modal />

    <div class="container py-5">
        <header class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom">
            <div>
                <h1 class="fw-bold">Dokumentasi API</h1>
                <p class="lead text-muted mb-0">Tokodaring</p>
            </div>
            <div>
                <button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#authModal">
                    <i class="fa-solid fa-key me-2"></i>Authorize
                </button>
                <span id="apiKeyStatus" class="badge bg-danger ms-2">Belum Diatur</span>
            </div>
        </header>

        {{-- GET semua produk --}}
        <x-api-endpoint-card method="GET" endpoint="/api/products"
            description="Mengambil daftar semua produk dengan paginasi." id="get-all">
            @include('api_examples.get_products_all_response')
        </x-api-endpoint-card>

        {{-- GET produk berdasarkan ID --}}
        <x-api-endpoint-card method="GET" endpoint="/api/products/{id}"
            description="Mengambil satu data produk berdasarkan ID-nya." id="get-by-id">
            <x-slot name="parameters">
                <input type="number" class="form-control form-control-sm" id="param-productId-get-by-id"
                    placeholder="ID Produk" style="width: 120px;">
            </x-slot>
            @include('api_examples.get_products_single_response')
        </x-api-endpoint-card>

        {{-- GET cari produk berdasarkan nama --}}
        <x-api-endpoint-card method="GET" endpoint="/api/products/search"
            description="Mencari data produk berdasarkan namanya." id="get-by-name">
            <x-slot name="parameters">
                <input type="text" class="form-control form-control-sm" id="param-productId-get-by-name"
                    placeholder="Nama Produk" style="width: 200px;">
            </x-slot>
            @include('api_examples.get_products_search_response')
        </x-api-endpoint-card>

        {{-- GET cari produk berdasarkan nama & kategori --}}
        <x-api-endpoint-card method="GET" endpoint="/api/products/search-category"
            description="Cari produk berdasarkan Nama Produk DAN/ATAU Nama Kategori." id="get-prod-cat-search">
            <x-slot name="parameters">
                <div class="d-flex gap-2">
                    <input type="text" class="form-control form-control-sm" id="param-pname-cat-search"
                        placeholder="Nama Produk" style="width: 160px;">
                    <input type="text" class="form-control form-control-sm" id="param-cname-cat-search"
                        placeholder="Nama Kategori" style="width: 160px;">
                </div>
            </x-slot>
            @include('api_examples.get_products_category_search_response')
        </x-api-endpoint-card>

        {{-- POST sinkronisasi nomor WhatsApp dengan user Tokodaring --}}
        <x-api-endpoint-card method="POST" endpoint="/api/whatsapp/sync-user"
            description="Cocokkan nomor WhatsApp pengirim dengan tabel user Tokodaring (lookup, tidak membuat akun baru)."
            id="post-whatsapp-sync">
            @include('api_examples.post_whatsapp_sync_user_request')
        </x-api-endpoint-card>

        {{-- GET daftar alamat tersimpan user --}}
        <x-api-endpoint-card method="GET" endpoint="/api/user-addresses"
            description="Ambil daftar alamat pengiriman tersimpan milik seorang user Tokodaring." id="get-user-addresses">
            <x-slot name="parameters">
                <input type="number" class="form-control form-control-sm" id="param-user-id-get-user-addresses"
                    placeholder="user_id" style="width: 120px;">
            </x-slot>
            @include('api_examples.get_user_addresses_response')
        </x-api-endpoint-card>

        {{-- POST simpan alamat baru --}}
        <x-api-endpoint-card method="POST" endpoint="/api/user-addresses"
            description="Simpan alamat pengiriman baru untuk user (dipakai saat AI mengumpulkan alamat lewat chat)."
            id="post-user-addresses">
            @include('api_examples.post_user_addresses_request')
        </x-api-endpoint-card>

        {{-- GET cari kota (RajaOngkir) --}}
        <x-api-endpoint-card method="GET" endpoint="/api/shipping/cities"
            description="Cari kota lewat RajaOngkir untuk resolve nama kota menjadi city_id saat mengumpulkan alamat baru."
            id="get-shipping-cities">
            <x-slot name="parameters">
                <input type="text" class="form-control form-control-sm" id="param-search-get-shipping-cities"
                    placeholder="Nama kota" style="width: 160px;">
            </x-slot>
            @include('api_examples.get_shipping_cities_response')
        </x-api-endpoint-card>

        {{-- POST hitung ongkir --}}
        <x-api-endpoint-card method="POST" endpoint="/api/shipping/cost"
            description="Hitung opsi ongkir (RajaOngkir) dari sebuah toko ke sebuah alamat tujuan untuk sekumpulan item."
            id="post-shipping-cost">
            @include('api_examples.post_shipping_cost_request')
        </x-api-endpoint-card>

        {{-- POST buat order (checkout) --}}
        <x-api-endpoint-card method="POST" endpoint="/api/orders"
            description="Buat order (bisa lebih dari satu toko sekaligus, dihubungkan oleh shared_id). Harga & stok divalidasi ulang di server."
            id="post-orders-checkout">
            @include('api_examples.post_orders_checkout_request')
        </x-api-endpoint-card>

        {{-- GET detail order --}}
        <x-api-endpoint-card method="GET" endpoint="/api/orders/{invoice}"
            description="Ambil detail sebuah order berdasarkan nomor invoice." id="get-order-by-invoice">
            <x-slot name="parameters">
                <input type="text" class="form-control form-control-sm" id="param-invoice-get-order-by-invoice"
                    placeholder="Invoice, mis. INV-20260721-2888" style="width: 220px;">
            </x-slot>
            @include('api_examples.get_order_by_invoice_response')
        </x-api-endpoint-card>
    </div>

    {{-- Aset JavaScript --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/json.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/mode/javascript/javascript.min.js"></script>

    <script>
        const API_KEY_TTL_HOURS = 2; // Kunci akan berlaku selama 2 jam

        document.addEventListener('DOMContentLoaded', function() {
            const editors = {};

            // BAGIAN 1: STATE MANAGEMENT & INISIALISASI
            let currentApiKey = '';
            const storedDataString = localStorage.getItem('tokodaring_api_key_data');
            if (storedDataString) {
                try {
                    const storedData = JSON.parse(storedDataString);
                    const now = new Date().getTime();
                    if (now < storedData.expiresAt) {
                        currentApiKey = storedData.key;
                    } else {
                        localStorage.removeItem('tokodaring_api_key_data');
                    }
                } catch (e) {
                    localStorage.removeItem('tokodaring_api_key_data');
                }
            }

            // BAGIAN 2: DEFINISI ELEMEN DOM & INISIALISASI AWAL
            const authModal = new bootstrap.Modal(document.getElementById('authModal'));
            const apiKeyStatus = document.getElementById('apiKeyStatus');
            const modalApiKeyInput = document.getElementById('modalApiKey');
            const toggleVisibilityBtn = document.getElementById('toggleApiKeyVisibility');

            // Inisialisasi semua editor CodeMirror (untuk request body POST/PUT)
            document.querySelectorAll('[id^="editor-"]').forEach(placeholder => {
                const id = placeholder.id.replace('editor-', '');
                const template = document.getElementById('template-' + id);
                const initialContent = template ? template.innerHTML.trim() : '{}';

                editors[id] = CodeMirror(placeholder, {
                    value: initialContent,
                    mode: {
                        name: "javascript",
                        json: true
                    },
                    theme: "darcula",
                    lineNumbers: true,
                    lineWrapping: true,
                    indentUnit: 2,
                    tabSize: 2
                });
            });

            // BAGIAN 3: DEFINISI FUNGSI-FUNGSI UTAMA

            function updateKeyStatus() {
                if (currentApiKey) {
                    apiKeyStatus.textContent = 'Telah Diatur';
                    apiKeyStatus.classList.replace('bg-danger', 'bg-success');
                    modalApiKeyInput.value = currentApiKey;
                } else {
                    apiKeyStatus.textContent = 'Belum Diatur';
                    apiKeyStatus.classList.replace('bg-success', 'bg-danger');
                    modalApiKeyInput.value = '';
                }
            }

            window.saveApiKey = function() {
                const apiKeyInput = document.getElementById('modalApiKey').value;
                if (apiKeyInput) {
                    const now = new Date();
                    const expiresAt = now.getTime() + (API_KEY_TTL_HOURS * 60 * 60 * 1000);
                    const dataToStore = {
                        key: apiKeyInput,
                        expiresAt: expiresAt
                    };
                    localStorage.setItem('tokodaring_api_key_data', JSON.stringify(dataToStore));
                    currentApiKey = apiKeyInput;
                    Swal.fire({
                        icon: 'success',
                        title: 'API Key Disimpan!',
                        text: `Kunci akan berlaku selama ${API_KEY_TTL_HOURS} jam.`,
                        showConfirmButton: false,
                        timer: 2000
                    });
                } else {
                    localStorage.removeItem('tokodaring_api_key_data');
                    currentApiKey = '';
                }
                updateKeyStatus();
                authModal.hide();
            };

            window.handleRequest = function(id, method) {
                const card = document.getElementById(`card-${id}`);
                if (!card) return;

                let endpoint = card.querySelector('.endpoint-path').textContent;
                let body = null;
                method = method.toUpperCase();
                let paramInput = null;

                // ============================================================
                // 1. HANDLING ENDPOINT DENGAN LEBIH DARI SATU PARAMETER
                // ============================================================
                if (id === 'get-prod-cat-search') {
                    const pName = card.querySelector('#param-pname-cat-search').value;
                    const cName = card.querySelector('#param-cname-cat-search').value;

                    const params = [];
                    if (pName) params.push(`product_name=${encodeURIComponent(pName)}`);
                    if (cName) params.push(`category_name=${encodeURIComponent(cName)}`);

                    if (params.length > 0) endpoint += '?' + params.join('&');
                    paramInput = { value: 'handled_manually' }; // Bypass validasi standar
                } else {
                    // ============================================================
                    // 2. HANDLING ENDPOINT STANDAR (Satu Parameter)
                    // ============================================================
                    if (id === 'get-by-id') {
                        paramInput = card.querySelector('#param-productId-get-by-id');
                    } else if (id === 'get-by-name') {
                        paramInput = card.querySelector('input[placeholder="Nama Produk"]');
                    } else if (id === 'get-user-addresses') {
                        paramInput = card.querySelector('#param-user-id-get-user-addresses');
                    } else if (id === 'get-shipping-cities') {
                        paramInput = card.querySelector('#param-search-get-shipping-cities');
                    } else if (id === 'get-order-by-invoice') {
                        paramInput = card.querySelector('#param-invoice-get-order-by-invoice');
                    }

                    if (paramInput && paramInput.value) {
                        const encodedValue = encodeURIComponent(paramInput.value);

                        if (id === 'get-by-id' || id === 'get-order-by-invoice') {
                            endpoint = endpoint.replace(/\{[^}]+\}/, encodedValue);
                        } else if (id === 'get-by-name') {
                            endpoint = `${endpoint}?name=${encodedValue}`;
                        } else if (id === 'get-user-addresses') {
                            endpoint = `${endpoint}?user_id=${encodedValue}`;
                        } else if (id === 'get-shipping-cities') {
                            endpoint = `${endpoint}?search=${encodedValue}`;
                        }
                    }
                }

                // ============================================================
                // 3. VALIDASI DAN PENGIRIMAN
                // ============================================================

                // Cek jika input wajib kosong (kecuali yang handled manually atau POST/PUT tanpa parameter)
                if (paramInput && !paramInput.value) {
                    Swal.fire('Input Diperlukan', 'Silakan masukkan nilai untuk parameter.', 'warning');
                    return;
                }

                // Parsing Body untuk POST/PUT
                if ((method === 'POST' || method === 'PUT') && editors[id]) {
                    const editorContent = editors[id].getValue();
                    if (editorContent) {
                        try {
                            JSON.parse(editorContent);
                            body = editorContent;
                        } catch (error) {
                            Swal.fire('JSON Tidak Valid', 'Mohon periksa format JSON di Request Body.', 'error');
                            return;
                        }
                    }
                }

                sendRequest(endpoint, id, method, body);
            };

            async function sendRequest(endpointUrl, prefix, method, body = null) {
                if (!currentApiKey) {
                    Swal.fire({
                        title: 'API Key Diperlukan',
                        text: 'Anda harus mengatur API Key terlebih dahulu.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#0d6efd',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Atur API Key',
                        cancelButtonText: 'Batal'
                    }).then(result => {
                        if (result.isConfirmed) authModal.show();
                    });
                    return;
                }

                const button = document.getElementById(`btn-${prefix}`);
                const btnText = button.querySelector('.btn-text');
                const spinner = button.querySelector('.spinner-border');

                button.disabled = true;
                if (btnText) btnText.classList.add('d-none');
                if (spinner) spinner.classList.remove('d-none');

                try {
                    const options = {
                        method: method,
                        headers: {
                            'Token': currentApiKey,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        }
                    };
                    if (body) {
                        options.body = body;
                    }
                    const fullUrl = endpointUrl.startsWith('http') ? endpointUrl :
                        `${window.location.origin}${endpointUrl}`;
                    const response = await fetch(fullUrl, options);
                    let data = null;
                    if (response.status !== 204) {
                        data = await response.json();
                    }
                    updateResponseUI(response.status, response.statusText, data, prefix);
                } catch (error) {
                    updateResponseUI('Error', 'Network Error', {
                        error: error.toString()
                    }, prefix);
                } finally {
                    button.disabled = false;
                    if (btnText) btnText.classList.remove('d-none');
                    if (spinner) spinner.classList.add('d-none');
                }
            }

            function updateResponseUI(status, statusText, data, prefix) {
                const responseArea = document.getElementById(`response-area-${prefix}`);
                if (!responseArea) return;
                responseArea.classList.remove('d-none');
                const responseStatus = document.getElementById(`responseStatus-${prefix}`);
                const statusClass = status >= 400 || status === 'Error' ? 'bg-danger text-white' :
                    'bg-success text-white';
                if (responseStatus) responseStatus.innerHTML =
                    `<span class="badge ${statusClass}">${status} ${statusText}</span>`;

                const dataPanel = document.querySelector(`#data-panel-${prefix} pre code`);
                const linksPanel = document.querySelector(`#links-panel-${prefix} pre code`);
                const metaPanel = document.querySelector(`#meta-panel-${prefix} pre code`);
                const rawPanel = document.querySelector(`#raw-panel-${prefix} pre code`);

                const highlightAndFill = (element, content) => {
                    if (element) {
                        const highlightedContent = hljs.highlight(content, {
                            language: 'json'
                        }).value;
                        element.innerHTML = highlightedContent;
                        element.classList.add('hljs');
                    }
                };

                const dataContent = data?.data ? JSON.stringify(data.data, null, 2) : (data ? JSON.stringify(data,
                    null, 2) : '// Tidak ada konten "data".');
                const linksContent = data?.links ? JSON.stringify(data.links, null, 2) :
                    '// Tidak ada konten "links".';
                const metaContent = data?.meta ? JSON.stringify(data.meta, null, 2) : '// Tidak ada konten "meta".';
                const rawContent = data ? JSON.stringify(data, null, 2) : '// Respons kosong.';

                highlightAndFill(dataPanel, dataContent);
                highlightAndFill(linksPanel, linksContent);
                highlightAndFill(metaPanel, metaContent);
                highlightAndFill(rawPanel, rawContent);

                if (data?.meta?.links) {
                    renderPagination(data.meta.links, prefix);
                } else {
                    const paginationControls = document.getElementById(`pagination-controls-${prefix}`);
                    if (paginationControls) paginationControls.innerHTML = '';
                }
            }

            function renderPagination(links, prefix) {
                const paginationControls = document.getElementById(`pagination-controls-${prefix}`);
                if (!paginationControls) return;
                paginationControls.innerHTML = '';
                const ul = document.createElement('ul');
                ul.className = 'pagination pagination-sm m-0';
                links.forEach(link => {
                    const li = document.createElement('li');
                    li.className =
                        `page-item ${link.active ? 'active' : ''} ${!link.url ? 'disabled' : ''}`;
                    const a = document.createElement('a');
                    a.className = 'page-link';
                    a.href = '#';
                    a.innerHTML = link.label;
                    if (link.url) {
                        a.onclick = (e) => {
                            e.preventDefault();
                            sendRequest(link.url, prefix, 'GET');
                        };
                    }
                    li.appendChild(a);
                    ul.appendChild(li);
                });
                paginationControls.appendChild(ul);
            }

            // BAGIAN 4: EVENT LISTENERS & EKSEKUSI AWAL
            updateKeyStatus();
            document.querySelectorAll('pre code.json').forEach(block => {
                block.classList.add('hljs');
                hljs.highlightElement(block);
            });

            toggleVisibilityBtn.addEventListener('click', () => {
                const isPassword = modalApiKeyInput.type === 'password';
                modalApiKeyInput.type = isPassword ? 'text' : 'password';
                toggleVisibilityBtn.querySelector('i').classList.toggle('fa-eye', !isPassword);
                toggleVisibilityBtn.querySelector('i').classList.toggle('fa-eye-slash', isPassword);
            });
        });

        window.copyCode = function(buttonElement) {
            const codeWrapper = buttonElement.closest('.position-relative');
            const codeBlock = codeWrapper.querySelector('pre code');

            if (!codeBlock) return;

            const codeToCopy = codeBlock.textContent;
            const copyTextSpan = buttonElement.querySelector('.copy-text');
            const copyIcon = buttonElement.querySelector('i');

            navigator.clipboard.writeText(codeToCopy).then(() => {
                copyTextSpan.textContent = 'Copied!';
                copyIcon.classList.remove('fa-regular', 'fa-copy');
                copyIcon.classList.add('fa-solid', 'fa-check');
                buttonElement.classList.add('btn-success');

                setTimeout(() => {
                    copyTextSpan.textContent = 'Copy';
                    copyIcon.classList.remove('fa-solid', 'fa-check');
                    copyIcon.classList.add('fa-regular', 'fa-copy');
                    buttonElement.classList.remove('btn-success');
                }, 2000);
            }).catch(err => {
                Swal.fire('Oops...', 'Gagal menyalin teks!', 'error');
            });
        }
    </script>
</body>

</html>

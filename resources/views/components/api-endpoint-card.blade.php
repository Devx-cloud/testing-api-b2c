{{-- resources/views/components/api-endpoint-card.blade.php --}}
@props(['method', 'endpoint', 'description', 'id'])

@php
    $method = strtolower($method);

    $methodColors = [
        'get' => 'bg-success',
        'post' => 'bg-primary',
        'put' => 'bg-warning text-dark',
        'delete' => 'bg-danger',
    ];
    $methodColor = isset($methodColors[$method]) ? $methodColors[$method] : 'bg-secondary';

    $buttonClasses = [
        'get' => 'btn-success',
        'post' => 'btn-primary',
    ];
    $buttonClass = isset($buttonClasses[$method]) ? $buttonClasses[$method] : 'btn-secondary';
@endphp

<div class="card shadow-sm mb-4" id="card-{{ $id }}">
    <div class="card-header bg-white p-3 border-0">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="d-flex align-items-center mb-0 gap-3">
                <span class="badge {{ $methodColor }} method-badge fs-6">{{ strtoupper($method) }}</span>
                <span class="endpoint-path">{{ $endpoint }}</span>
            </h5>
            <div id="param-input-{{ $id }}" class="d-flex gap-2 align-items-center">
                {{-- Slot untuk input parameter seperti ID --}}
                {{ $parameters ?? '' }}
                {{-- Tombol dibuat dinamis berdasarkan method --}}
                <button class="btn {{ $buttonClass }} btn-sm" id="btn-{{ $id }}"
                    onclick="handleRequest('{{ $id }}', '{{ $method }}')">
                    <span class="btn-text"><i class="fa-solid fa-paper-plane me-2"></i>Kirim Request</span>
                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-4">
        <p class="card-text text-muted">{{ $description }}</p>

        @if ($method === 'post' || $method === 'put')
            <div class="request-body-section mt-4">
                <h6 class="fw-bold">Request Body</h6>

                {{-- Wadah untuk CodeMirror --}}
                <div id="editor-{{ $id }}" class="border rounded"></div>

                {{-- Template untuk menyimpan contoh body request dari slot --}}
                <template id="template-{{ $id }}">
                    {{ trim($slot) }}
                </template>
            </div>
        @endif

        <div class="response-container mt-3 d-none" id="response-area-{{ $id }}">
            <div class="d-flex justify-content-between align-items-center p-2 bg-light border-bottom">
                <span class="fw-bold small ms-2">Respons</span>
                <div id="responseStatus-{{ $id }}"></div>
            </div>

            <ul class="nav nav-tabs nav-tabs-bordered nav-fill" id="responseTab-{{ $id }}">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab"
                        data-bs-target="#data-panel-{{ $id }}">Data</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab"
                        data-bs-target="#links-panel-{{ $id }}">Links</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab"
                        data-bs-target="#meta-panel-{{ $id }}">Meta</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab"
                        data-bs-target="#raw-panel-{{ $id }}">Raw</button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active position-relative" id="data-panel-{{ $id }}">
                    <button class="btn btn-sm btn-outline-light btn-copy" onclick="copyCode(this)" style="top: 1.6rem; right: 2.8rem;">
                        <i class="fa-regular fa-copy me-1"></i>
                        <span class="copy-text">Copy</span>
                    </button>
                    <pre><code class="language-json"></code></pre>
                </div>

                <div class="tab-pane fade position-relative" id="links-panel-{{ $id }}">
                    <button class="btn btn-sm btn-outline-light btn-copy" onclick="copyCode(this)" style="top: 1.6rem; right: 2rem;">
                        <i class="fa-regular fa-copy me-1"></i>
                        <span class="copy-text">Copy</span>
                    </button>
                    <pre><code class="language-json"></code></pre>
                </div>

                <div class="tab-pane fade position-relative" id="meta-panel-{{ $id }}">
                    <button class="btn btn-sm btn-outline-light btn-copy" onclick="copyCode(this)" style="top: 1.6rem; right: 2.8rem;">
                        <i class="fa-regular fa-copy me-1"></i>
                        <span class="copy-text">Copy</span>
                    </button>
                    <pre><code class="language-json"></code></pre>
                </div>

                <div class="tab-pane fade position-relative" id="raw-panel-{{ $id }}">
                    <button class="btn btn-sm btn-outline-light btn-copy" onclick="copyCode(this)" style="top: 1.6rem; right: 2.8rem;">
                        <i class="fa-regular fa-copy me-1"></i>
                        <span class="copy-text">Copy</span>
                    </button>
                    <pre><code class="language-json"></code></pre>
                </div>
            </div>

            <div id="pagination-controls-{{ $id }}"
                class="d-flex justify-content-center p-2 bg-light border-top border-bottom-radius">
            </div>
        </div>
    </div>
    <div class="card-footer bg-white text-center p-2 border-0">
        <a class="text-decoration-none text-muted small" data-bs-toggle="collapse"
            href="#initialResponse-{{ $id }}" role="button" aria-expanded="false">
            Contoh Request / Respons <i class="fa-solid fa-chevron-down small"></i>
        </a>
    </div>
    <div class="collapse" id="initialResponse-{{ $id }}">
        <div class="position-relative">
            <button class="btn btn-sm btn-outline-light btn-copy" onclick="copyCode(this)">
                <i class="fa-regular fa-copy me-1"></i>
                <span class="copy-text">Copy</span>
            </button>

            <div class="response-area border-top">
                <pre><code class="json">{{ $slot }}</code></pre>
            </div>
        </div>
    </div>
</div>

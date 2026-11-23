@extends('layouts.app')

@section('title', 'Enrollment Tokens - NADI Console')

@section('breadcrumb', 'Management / Enrollment Tokens')

@section('content')

@php
    $totalBase = max((int) $totalTokens, 1);
    $activePercent = round(((int) $activeTokens / $totalBase) * 100);
    $usedPercent = round(((int) $usedTokens / $totalBase) * 100);
    $revokedPercent = round(((int) $revokedTokens / $totalBase) * 100);
    $expiredPercent = round(((int) $expiredTokens / $totalBase) * 100);
@endphp

<div class="tokens-page">

    <div class="page-header">
        <div>
            <h1 class="page-title">Enrollment Tokens</h1>
            <p class="page-description">Manage enrollment token lifecycle and installation access.</p>
        </div>
    </div>

    <div class="token-summary" id="tokenSummary">

        <div class="token-summary-card">
            <div class="token-summary-top">
                <div class="token-summary-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="10" width="18" height="11" rx="2"/>
                        <path d="M7 10V7a5 5 0 0 1 10 0v3"/>
                        <circle cx="12" cy="15" r="1"/>
                    </svg>
                </div>
                <div class="token-summary-label">Total Tokens</div>
            </div>
            <div class="token-summary-value">
                {{ number_format($totalTokens) }}
            </div>
            <div class="token-summary-description">All enrollment tokens</div>
            <div class="token-summary-meter">
                <div class="token-summary-meter-fill" style="width: 100%;"></div>
            </div>
        </div>

        <div class="token-summary-card">
            <div class="token-summary-top">
                <div class="token-summary-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M8 12.5l3 3 5-6"/>
                    </svg>
                </div>
                <div class="token-summary-label">Usable</div>
            </div>
            <div class="token-summary-value">
                {{ number_format($activeTokens) }}
                <span class="token-summary-percent">{{ $activePercent }}%</span>
            </div>
            <div class="token-summary-description">Ready for installation</div>
            <div class="token-summary-meter">
                <div class="token-summary-meter-fill" style="width: {{ $activePercent }}%;"></div>
            </div>
        </div>

        <div class="token-summary-card">
            <div class="token-summary-top">
                <div class="token-summary-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 4v11"/>
                        <path d="M7.5 11l4.5 4.5 4.5-4.5"/>
                        <path d="M5 19h14"/>
                    </svg>
                </div>
                <div class="token-summary-label">Used</div>
            </div>
            <div class="token-summary-value">
                {{ number_format($usedTokens) }}
                <span class="token-summary-percent">{{ $usedPercent }}%</span>
            </div>
            <div class="token-summary-description">Already consumed</div>
            <div class="token-summary-meter">
                <div class="token-summary-meter-fill" style="width: {{ $usedPercent }}%;"></div>
            </div>
        </div>

        <div class="token-summary-card">
            <div class="token-summary-top">
                <div class="token-summary-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M5.7 5.7l12.6 12.6"/>
                    </svg>
                </div>
                <div class="token-summary-label">Revoked</div>
            </div>
            <div class="token-summary-value">
                {{ number_format($revokedTokens) }}
                <span class="token-summary-percent">{{ $revokedPercent }}%</span>
            </div>
            <div class="token-summary-description">Access withdrawn</div>
            <div class="token-summary-meter">
                <div class="token-summary-meter-fill" style="width: {{ $revokedPercent }}%;"></div>
            </div>
        </div>

        <div class="token-summary-card">
            <div class="token-summary-top">
                <div class="token-summary-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 7.5V12l3 2"/>
                    </svg>
                </div>
                <div class="token-summary-label">Expired</div>
            </div>
            <div class="token-summary-value">
                {{ number_format($expiredTokens) }}
                <span class="token-summary-percent">{{ $expiredPercent }}%</span>
            </div>
            <div class="token-summary-description">Past expiry time</div>
            <div class="token-summary-meter">
                <div class="token-summary-meter-fill" style="width: {{ $expiredPercent }}%;"></div>
            </div>
        </div>

    </div>

    <div class="token-panel">

        <div class="token-panel-header">
            <div>
                <h2>
                    Token Inventory
                    <span class="token-panel-live">Live</span>
                </h2>
                <p>Enrollment token records. Original token values are never displayed.</p>
            </div>
        </div>

        <div class="token-create-panel">

            <div class="token-create-title">
                <div class="token-create-title-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                </div>
                Create Enrollment Token
            </div>

            <form
                id="enrollmentTokenCreateForm"
                method="POST"
                action="{{ route('management.enrollment-tokens.store') }}"
                class="token-create-form"
            >
                @csrf

                <div class="token-create-field">
                    <label for="enrollmentTokenName">Token Name</label>
                    <input
                        type="text"
                        id="enrollmentTokenName"
                        name="token_name"
                        maxlength="100"
                        placeholder="Example: BMI Production Server"
                    >
                </div>

                <div class="token-create-field">
                    <label for="enrollmentEnvironmentScope">Environment Scope</label>
                    <input
                        type="text"
                        id="enrollmentEnvironmentScope"
                        name="environment_scope"
                        maxlength="50"
                        placeholder="Example: production"
                    >
                </div>

                <div class="token-create-field">
                    <label for="enrollmentTokenLifetime">Token Lifetime</label>
                    <select
                        id="enrollmentTokenLifetime"
                        name="lifetime_hours"
                        required
                    >
                        <option value="24">24 Hours</option>
                        <option value="72">3 Days</option>
                        <option value="168">7 Days</option>
                        <option value="720">30 Days</option>
                    </select>
                </div>

                <button
                    type="submit"
                    id="enrollmentTokenSubmit"
                    class="token-filter-button"
                >
                    Generate Enrollment Token
                </button>
            </form>

            <div
                id="enrollmentTokenError"
                class="token-create-error"
                role="alert"
                hidden
            ></div>

            <div
                id="enrollmentTokenResult"
                class="token-create-result"
                role="status"
                hidden
            >
                <h3>Enrollment Token Created</h3>

                <p>
                    Copy and save this token now. It will not be displayed again.
                </p>

                <input
                    type="text"
                    id="enrollmentTokenValue"
                    readonly
                    autocomplete="off"
                >

                <button
                    type="button"
                    id="enrollmentTokenCopy"
                    class="token-filter-button"
                >
                    Copy Token
                </button>

                <button
                    type="button"
                    id="enrollmentTokenDone"
                    class="token-reset-button"
                >
                    Done
                </button>
            </div>
        </div>

        <form
            method="GET"
            action="{{ route('management.enrollment-tokens') }}"
            class="token-filter-form"
        >

            <div class="token-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="M20 20l-3.5-3.5"/>
                </svg>

                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search token name, environment, or ID..."
                >
            </div>

            <select name="status">
                <option value="">All Statuses</option>

                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>
                    Usable
                </option>

                <option value="used" {{ request('status') === 'used' ? 'selected' : '' }}>
                    Used
                </option>

                <option value="revoked" {{ request('status') === 'revoked' ? 'selected' : '' }}>
                    Revoked
                </option>

                <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>
                    Expired
                </option>
            </select>

            <button type="submit" class="token-filter-button">
                Filter
            </button>

            <a
                href="{{ route('management.enrollment-tokens') }}"
                class="token-reset-button"
                {{ request()->hasAny(['search', 'status']) ? '' : 'hidden' }}
            >
                Reset
            </a>

        </form>

        <div
            id="tokenListError"
            class="token-list-error"
            role="alert"
            hidden
        ></div>

        <div class="token-list-region" id="tokenListRegion">

            <div class="token-table-wrapper">

                <table class="token-table">

                    <thead>
                        <tr>
                            <th>Token</th>
                            <th>Environment</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th>Expires At</th>
                            <th>Used At</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse ($tokens as $token)

                            <tr>

                                <td>
                                    <div class="token-main">
                                        <div class="token-main-icon">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="10" width="18" height="11" rx="2"/>
                                                <path d="M7 10V7a5 5 0 0 1 10 0v3"/>
                                                <circle cx="12" cy="15" r="1"/>
                                            </svg>
                                        </div>

                                        <div>
                                            <div class="token-primary">
                                                {{ $token->token_name ?: 'Unnamed Token' }}
                                            </div>

                                            <div class="token-secondary token-id" title="{{ $token->id }}">
                                                {{ $token->id }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="token-environment">
                                        {{ $token->environment_scope ?: 'All Environments' }}
                                    </span>
                                </td>

                                <td>
                                    @if ($token->creator)
                                        <div class="token-primary">
                                            {{ $token->creator->name }}
                                        </div>

                                        <div class="token-secondary">
                                            {{ $token->creator->username }}
                                        </div>
                                    @else
                                        <span class="token-secondary">
                                            Unknown
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    @if ($token->created_at)
                                        <div class="token-primary token-date">
                                            {{ $token->created_at->format('d M Y') }}
                                        </div>

                                        <div class="token-secondary">
                                            {{ $token->created_at->format('H:i:s') }}
                                        </div>
                                    @else
                                        <span class="token-secondary">
                                            —
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    <div class="token-primary token-date">
                                        {{ $token->expires_at->format('d M Y') }}
                                    </div>

                                    <div class="token-secondary">
                                        {{ $token->expires_at->format('H:i:s') }}
                                        · {{ $token->expires_at->diffForHumans() }}
                                    </div>
                                </td>

                                <td>
                                    @if ($token->used_at)
                                        <div class="token-primary token-date">
                                            {{ $token->used_at->format('d M Y') }}
                                        </div>

                                        <div class="token-secondary">
                                            {{ $token->used_at->format('H:i:s') }}
                                        </div>
                                    @else
                                        <span class="token-secondary">
                                            Not used
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    <span class="token-status status-{{ $token->computed_status }}">
                                        <span class="token-status-dot"></span>
                                        {{ ucfirst($token->computed_status) }}
                                    </span>

                                    @if ($token->revoked_at)
                                        <div class="token-secondary">
                                            Revoked {{ $token->revoked_at->format('d M Y H:i') }}
                                        </div>
                                    @endif
                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="7">
                                    <div class="token-empty-state">

                                        <div class="token-empty-icon">
                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.7"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            >
                                                <rect x="3" y="10" width="18" height="11" rx="2"/>
                                                <path d="M7 10V7a5 5 0 0 1 10 0v3"/>
                                                <circle cx="12" cy="15" r="1"/>
                                            </svg>
                                        </div>

                                        <h3>No enrollment tokens found</h3>

                                        <p>
                                            Enrollment tokens will appear here when created.
                                        </p>

                                    </div>
                                </td>
                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

            @if ($tokens->hasPages())
                <div class="token-pagination">
                    {{ $tokens->links() }}
                </div>
            @endif

        </div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function ()
{
    const form = document.getElementById('enrollmentTokenCreateForm');
    const submitButton = document.getElementById('enrollmentTokenSubmit');
    const errorContainer = document.getElementById('enrollmentTokenError');
    const resultContainer = document.getElementById('enrollmentTokenResult');
    const tokenInput = document.getElementById('enrollmentTokenValue');
    const copyButton = document.getElementById('enrollmentTokenCopy');
    const doneButton = document.getElementById('enrollmentTokenDone');

    const filterForm = document.querySelector('.token-filter-form');
    const searchInput = filterForm ? filterForm.querySelector('input[name="search"]') : null;
    const statusSelect = filterForm ? filterForm.querySelector('select[name="status"]') : null;
    const resetLink = filterForm ? filterForm.querySelector('.token-reset-button') : null;
    const summaryContainer = document.getElementById('tokenSummary');
    const listRegion = document.getElementById('tokenListRegion');
    const listError = document.getElementById('tokenListError');

    let listController = null;

    /*
     * State filter disimpan di JavaScript, bukan di URL,
     * sehingga alamat halaman tidak pernah berubah.
     */
    const listState = {
        search: searchInput ? searchInput.value.trim() : '',
        status: statusSelect ? statusSelect.value : '',
        page: new URLSearchParams(window.location.search).get('page') || ''
    };

    function buildListUrl()
    {
        const url = new URL(filterForm.action, window.location.origin);

        if (listState.search)
        {
            url.searchParams.set('search', listState.search);
        }

        if (listState.status)
        {
            url.searchParams.set('status', listState.status);
        }

        if (listState.page)
        {
            url.searchParams.set('page', listState.page);
        }

        return url;
    }

    function syncResetVisibility()
    {
        if (resetLink)
        {
            resetLink.hidden = !(listState.search || listState.status);
        }
    }

    function animateMeters()
    {
        summaryContainer.querySelectorAll('.token-summary-meter-fill').forEach(function (fill)
        {
            const target = fill.style.width;

            fill.style.width = '0%';

            void fill.offsetWidth;

            fill.style.width = target;
        });
    }

    function replayFade()
    {
        listRegion.classList.remove('is-fresh');

        void listRegion.offsetWidth;

        listRegion.classList.add('is-fresh');
    }

    async function loadList()
    {
        if (listController)
        {
            listController.abort();
        }

        const controller = new AbortController();

        listController = controller;

        const url = buildListUrl();

        listRegion.classList.add('is-loading');
        listRegion.setAttribute('aria-busy', 'true');

        listError.hidden = true;
        listError.textContent = '';

        try
        {
            const response = await fetch(url.toString(), {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: controller.signal
            });

            if (response.redirected)
            {
                /* Sesi berakhir atau diarahkan ke halaman lain: lanjutkan navigasi biasa. */
                window.location.assign(url.toString());

                return;
            }

            if (!response.ok)
            {
                throw new Error('Unable to refresh the token list. Please try again.');
            }

            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');

            const nextSummary = doc.getElementById('tokenSummary');
            const nextRegion = doc.getElementById('tokenListRegion');

            if (!nextSummary || !nextRegion)
            {
                window.location.assign(url.toString());

                return;
            }

            summaryContainer.innerHTML = nextSummary.innerHTML;
            listRegion.innerHTML = nextRegion.innerHTML;

            animateMeters();
            replayFade();
            syncResetVisibility();
        }
        catch (error)
        {
            if (error.name === 'AbortError')
            {
                return;
            }

            listError.textContent = error.message || 'Unable to refresh the token list. Please try again.';
            listError.hidden = false;
        }
        finally
        {
            if (listController === controller)
            {
                listRegion.classList.remove('is-loading');
                listRegion.removeAttribute('aria-busy');
            }
        }
    }

    if (filterForm && searchInput && statusSelect && summaryContainer && listRegion && listError)
    {
        filterForm.addEventListener('submit', function (event)
        {
            event.preventDefault();

            listState.search = searchInput.value.trim();
            listState.status = statusSelect.value;
            listState.page = '';

            loadList();
        });

        if (resetLink)
        {
            resetLink.addEventListener('click', function (event)
            {
                event.preventDefault();

                searchInput.value = '';
                statusSelect.value = '';

                listState.search = '';
                listState.status = '';
                listState.page = '';

                loadList();
            });
        }

        listRegion.addEventListener('click', function (event)
        {
            const link = event.target.closest('.token-pagination a');

            if (!link || !link.href)
            {
                return;
            }

            event.preventDefault();

            const target = new URL(link.href, window.location.origin);

            listState.page = target.searchParams.get('page') || '';

            loadList().then(function ()
            {
                filterForm.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            });
        });
    }

    if (!form)
    {
        return;
    }

    form.addEventListener('submit', async function (event)
    {
        event.preventDefault();

        errorContainer.hidden = true;
        errorContainer.textContent = '';

        resultContainer.hidden = true;
        tokenInput.value = '';

        submitButton.disabled = true;
        submitButton.textContent = 'Generating...';

        try
        {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new FormData(form)
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok || !payload.success)
            {
                let message = payload.message || 'Unable to create enrollment token.';

                if (payload.errors)
                {
                    const firstError = Object.values(payload.errors)[0];

                    if (firstError && firstError.length > 0)
                    {
                        message = firstError[0];
                    }
                }

                throw new Error(message);
            }

            tokenInput.value = payload.data.token;

            resultContainer.hidden = false;

            form.reset();

            tokenInput.focus();
            tokenInput.select();

            /* Perbarui ringkasan dan daftar token tanpa memuat ulang halaman. */
            if (listRegion && filterForm)
            {
                listState.page = '';

                loadList();
            }
        }
        catch (error)
        {
            errorContainer.textContent = error.message;
            errorContainer.hidden = false;
        }
        finally
        {
            submitButton.disabled = false;
            submitButton.textContent = 'Generate Enrollment Token';
        }
    });

    copyButton.addEventListener('click', async function ()
    {
        if (!tokenInput.value)
        {
            return;
        }

        try
        {
            await navigator.clipboard.writeText(tokenInput.value);

            copyButton.textContent = 'Copied';

            setTimeout(function ()
            {
                copyButton.textContent = 'Copy Token';
            }, 2000);
        }
        catch (error)
        {
            tokenInput.focus();
            tokenInput.select();

            copyButton.textContent = 'Select and Copy';
        }
    });

    doneButton.addEventListener('click', function ()
    {
        tokenInput.value = '';

        resultContainer.hidden = true;

        copyButton.textContent = 'Copy Token';

        document.getElementById('enrollmentTokenName').focus();
    });
});
</script>

@endsection

@push('styles')

<style>
    .tokens-page
    {
        --status-ok: #16a34a;
        --status-warn: #d97706;
        --status-bad: #dc2626;
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .tokens-page .page-header
    {
        margin-bottom: 0;
    }

    /* ---------- Ringkasan ---------- */
    .token-summary
    {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 18px;
    }

    .token-summary-card
    {
        position: relative;
        padding: 22px 22px;
        border: 1px solid var(--line);
        border-radius: 16px;
        background-color: var(--surface);
        background-image: radial-gradient(circle at 100% 0%, color-mix(in srgb, var(--accent) 14%, transparent), transparent 55%), linear-gradient(color-mix(in srgb, var(--accent) 5%, transparent) 1px, transparent 1px), linear-gradient(90deg, color-mix(in srgb, var(--accent) 5%, transparent) 1px, transparent 1px);
        background-size: auto, 22px 22px, 22px 22px;
        box-shadow: var(--shadow);
        overflow: hidden;
        transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease;
    }

    .token-summary-card:hover
    {
        transform: translateY(-3px);
        border-color: color-mix(in srgb, var(--accent) 45%, var(--line));
        box-shadow: var(--shadow), 0 18px 40px -22px var(--accent);
    }

    .token-summary-card::before
    {
        content: '';
        position: absolute;
        top: -1px;
        left: 22px;
        width: 44px;
        height: 2px;
        border-radius: 0 0 3px 3px;
        background: var(--brand);
    }

    /* Siku HUD di pojok kanan bawah */
    .token-summary-card::after
    {
        content: '';
        position: absolute;
        right: 12px;
        bottom: 12px;
        width: 14px;
        height: 14px;
        border-right: 2px solid var(--accent);
        border-bottom: 2px solid var(--accent);
        border-radius: 0 0 4px 0;
        opacity: .5;
        pointer-events: none;
    }

    .token-summary-top
    {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .token-summary-icon
    {
        width: 40px;
        height: 40px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        border: 1px solid color-mix(in srgb, var(--accent) 40%, transparent);
        border-radius: 12px;
        background: var(--accent-soft);
        color: var(--accent);
        box-shadow: 0 0 22px -6px var(--accent), inset 0 0 12px -6px var(--accent);
    }

    .token-summary-icon svg
    {
        width: 19px;
        height: 19px;
    }

    .token-summary-label
    {
        font: 600 11.5px var(--mono);
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .token-summary-value
    {
        display: flex;
        align-items: baseline;
        flex-wrap: wrap;
        gap: 10px;
        font-size: 32px;
        font-weight: 800;
        letter-spacing: -.03em;
        line-height: 1.1;
        color: var(--ink);
    }

    .token-summary-percent
    {
        padding: 3px 9px;
        border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent);
        border-radius: 999px;
        background: var(--accent-soft);
        color: var(--accent);
        font: 600 11px var(--mono);
        letter-spacing: 0;
    }

    .token-summary-description
    {
        margin-top: 8px;
        font: 500 11.5px var(--mono);
        color: var(--muted);
    }

    .token-summary-meter
    {
        height: 4px;
        margin-top: 16px;
        border-radius: 999px;
        background: var(--surface-2);
        overflow: hidden;
    }

    .token-summary-meter-fill
    {
        height: 100%;
        border-radius: 999px;
        background: var(--brand);
        box-shadow: 0 0 12px var(--accent);
        transition: width .8s cubic-bezier(.2, .8, .2, 1);
    }

    /* ---------- Panel ---------- */
    .token-panel
    {
        position: relative;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: var(--surface);
        box-shadow: var(--shadow);
        overflow: hidden;
    }

    .token-panel::before
    {
        content: '';
        position: absolute;
        top: 0;
        left: 28px;
        right: 28px;
        height: 3px;
        border-radius: 0 0 4px 4px;
        background: var(--brand);
        box-shadow: 0 0 18px var(--accent);
    }

    .token-panel-header
    {
        padding: 26px 24px 20px;
        border-bottom: 1px solid var(--line);
        background-image: linear-gradient(180deg, color-mix(in srgb, var(--accent) 6%, transparent), transparent);
    }

    .token-panel-header h2
    {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
        font-size: 16px;
        font-weight: 800;
        color: var(--ink);
    }

    .token-panel-header p
    {
        margin: 5px 0 0;
        font: 500 12px var(--mono);
        color: var(--muted);
    }

    .token-panel-live
    {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 9px;
        border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent);
        border-radius: 999px;
        background: var(--accent-soft);
        color: var(--accent);
        font: 600 10.5px var(--mono);
        letter-spacing: .1em;
        text-transform: uppercase;
    }

    .token-panel-live::before
    {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
        animation: token-pulse 2s ease-out infinite;
    }

    /* ---------- Buat token ---------- */
    .token-create-panel
    {
        display: flex;
        flex-direction: column;
        gap: 16px;
        padding: 20px 24px 22px;
        border-bottom: 1px solid var(--line);
        background-image: linear-gradient(180deg, color-mix(in srgb, var(--accent) 4%, transparent), transparent);
    }

    .token-create-title
    {
        display: flex;
        align-items: center;
        gap: 10px;
        font: 600 11.5px var(--mono);
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .token-create-title-icon
    {
        width: 26px;
        height: 26px;
        display: grid;
        place-items: center;
        border: 1px solid color-mix(in srgb, var(--accent) 40%, transparent);
        border-radius: 8px;
        background: var(--accent-soft);
        color: var(--accent);
    }

    .token-create-title-icon svg
    {
        width: 14px;
        height: 14px;
    }

    .token-create-form
    {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr)) auto;
        align-items: end;
        gap: 14px;
    }

    .token-create-field
    {
        display: flex;
        flex-direction: column;
        gap: 7px;
        min-width: 0;
    }

    .token-create-field label
    {
        font: 600 11px var(--mono);
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .token-create-panel input,
    .token-create-panel select
    {
        width: 100%;
        height: 40px;
        box-sizing: border-box;
        border: 1.5px solid var(--line);
        border-radius: 10px;
        padding: 0 12px;
        background: var(--surface-2);
        color: var(--ink);
        font: 500 13px var(--font);
        outline: none;
        transition: border-color .2s, box-shadow .2s, background .2s;
    }

    .token-create-panel input::placeholder
    {
        color: var(--muted);
        opacity: .8;
    }

    .token-create-panel input:focus,
    .token-create-panel select:focus
    {
        background: var(--surface);
        border-color: var(--accent);
        box-shadow: 0 0 0 4px var(--accent-soft), 0 0 22px -8px var(--accent);
    }

    .token-create-panel .token-filter-button:disabled
    {
        opacity: .6;
        cursor: not-allowed;
        transform: none;
        filter: none;
    }

    .token-create-error,
    .token-list-error
    {
        padding: 12px 16px;
        border: 1px solid color-mix(in srgb, var(--status-bad) 35%, transparent);
        border-left: 3px solid var(--status-bad);
        border-radius: 10px;
        background: color-mix(in srgb, var(--status-bad) 10%, transparent);
        color: var(--status-bad);
        font: 500 12.5px var(--mono);
    }

    .token-list-error
    {
        margin: 16px 24px 0;
    }

    .token-create-result
    {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        padding: 18px 20px;
        border: 1px solid color-mix(in srgb, var(--status-ok) 35%, transparent);
        border-left: 3px solid var(--status-ok);
        border-radius: 12px;
        background: color-mix(in srgb, var(--status-ok) 8%, transparent);
        box-shadow: 0 0 30px -14px var(--status-ok);
    }

    .token-create-result h3
    {
        flex: 0 0 100%;
        margin: 0;
        font-size: 15px;
        font-weight: 800;
        color: var(--status-ok);
    }

    .token-create-result p
    {
        flex: 0 0 100%;
        margin: 0 0 4px;
        font: 500 12px var(--mono);
        color: var(--status-warn);
    }

    .token-create-panel .token-create-result input
    {
        flex: 1;
        min-width: 260px;
        width: auto;
        border-color: color-mix(in srgb, var(--accent) 45%, var(--line));
        background: var(--surface);
        color: var(--accent);
        font: 600 12.5px var(--mono);
        letter-spacing: .02em;
    }

    .token-create-panel [hidden],
    .token-list-error[hidden],
    .token-filter-form .token-reset-button[hidden]
    {
        display: none;
    }

    /* ---------- Filter ---------- */
    .token-filter-form
    {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        padding: 18px 24px;
        border-bottom: 1px solid var(--line);
    }

    .token-search
    {
        position: relative;
        flex: 1;
        min-width: 220px;
    }

    .token-search svg
    {
        position: absolute;
        top: 50%;
        left: 12px;
        width: 16px;
        height: 16px;
        transform: translateY(-50%);
        color: var(--muted);
        pointer-events: none;
        transition: color .2s;
    }

    .token-search:focus-within svg
    {
        color: var(--accent);
    }

    .token-filter-form input,
    .token-filter-form select
    {
        height: 40px;
        width: 100%;
        border: 1.5px solid var(--line);
        border-radius: 10px;
        padding: 0 12px;
        background: var(--surface-2);
        color: var(--ink);
        font: 500 13px var(--font);
        outline: none;
        transition: border-color .2s, box-shadow .2s, background .2s;
    }

    .token-filter-form input
    {
        padding-left: 36px;
    }

    .token-filter-form input::placeholder
    {
        color: var(--muted);
        opacity: .8;
    }

    .token-filter-form select
    {
        width: auto;
        min-width: 150px;
    }

    .token-filter-form input:focus,
    .token-filter-form select:focus
    {
        background: var(--surface);
        border-color: var(--accent);
        box-shadow: 0 0 0 4px var(--accent-soft), 0 0 22px -8px var(--accent);
    }

    .token-filter-button,
    .token-reset-button
    {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 40px;
        padding: 0 18px;
        border-radius: 10px;
        font: 700 13px var(--font);
        text-decoration: none;
        cursor: pointer;
        transition: filter .2s, transform .2s, color .2s, border-color .2s;
    }

    .token-filter-button
    {
        border: 0;
        background: var(--brand);
        color: #ffffff;
        box-shadow: 0 10px 22px -12px rgba(47, 76, 240, .9), inset 0 1px 0 rgba(255, 255, 255, .3);
    }

    .token-filter-button:hover
    {
        filter: brightness(1.08);
        transform: translateY(-1px);
    }

    .token-reset-button
    {
        border: 1.5px solid var(--line);
        background: transparent;
        color: var(--muted);
    }

    .token-reset-button:hover
    {
        border-color: var(--accent);
        color: var(--accent);
    }

    /* ---------- Daftar (diperbarui tanpa reload) ---------- */
    .token-list-region
    {
        position: relative;
        transition: opacity .2s ease;
    }

    .token-list-region.is-loading
    {
        opacity: .45;
        pointer-events: none;
    }

    .token-list-region.is-loading::before
    {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, transparent, var(--accent), transparent);
        background-size: 40% 100%;
        background-repeat: no-repeat;
        animation: token-loading 1s ease-in-out infinite;
    }

    .token-list-region.is-fresh
    {
        animation: token-fade-in .3s ease both;
    }

    /* ---------- Tabel ---------- */
    .token-table-wrapper
    {
        width: 100%;
        overflow-x: auto;
    }

    .token-table
    {
        width: 100%;
        border-collapse: collapse;
        white-space: nowrap;
    }

    .token-table th
    {
        padding: 13px 20px;
        background: var(--surface-2);
        border-bottom: 1px solid var(--line);
        text-align: left;
        font: 600 11px var(--mono);
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .token-table td
    {
        padding: 16px 20px;
        border-bottom: 1px solid var(--line);
        font-size: 13px;
        color: var(--muted);
        vertical-align: middle;
    }

    .token-table tbody tr:last-child td
    {
        border-bottom: 0;
    }

    .token-table tbody tr
    {
        transition: background .2s;
    }

    .token-table tbody tr:hover
    {
        background: var(--accent-soft);
    }

    .token-table tbody tr td:first-child
    {
        box-shadow: inset 0 0 0 var(--accent);
        transition: box-shadow .2s;
    }

    .token-table tbody tr:hover td:first-child
    {
        box-shadow: inset 3px 0 0 var(--accent);
    }

    .token-main
    {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .token-main-icon
    {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        border: 1px solid color-mix(in srgb, var(--accent) 30%, transparent);
        border-radius: 10px;
        background: var(--accent-soft);
        color: var(--accent);
    }

    .token-main-icon svg
    {
        width: 16px;
        height: 16px;
    }

    .token-primary
    {
        color: var(--ink);
        font-size: 13px;
        font-weight: 700;
    }

    .token-date
    {
        font: 600 12px var(--mono);
    }

    .token-secondary
    {
        margin-top: 5px;
        font: 500 11px var(--mono);
        color: var(--muted);
    }

    .token-id
    {
        max-width: 220px;
        overflow: hidden;
        text-overflow: ellipsis;
        color: var(--accent);
    }

    .token-environment
    {
        display: inline-block;
        padding: 4px 10px;
        border: 1px solid var(--line);
        border-radius: 8px;
        background: var(--surface-2);
        color: var(--ink);
        font: 600 11.5px var(--mono);
    }

    /* ---------- Status ---------- */
    .token-status
    {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 5px 11px;
        border: 1px solid var(--line);
        border-radius: 999px;
        background: var(--surface-2);
        color: var(--muted);
        font: 600 11.5px var(--mono);
    }

    .token-status-dot
    {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }

    .token-status.status-active
    {
        border-color: color-mix(in srgb, var(--status-ok) 40%, transparent);
        background: color-mix(in srgb, var(--status-ok) 14%, transparent);
        color: var(--status-ok);
        box-shadow: 0 0 18px -8px var(--status-ok);
    }

    .token-status.status-active .token-status-dot
    {
        animation: token-pulse 2s ease-out infinite;
    }

    .token-status.status-used
    {
        border-color: color-mix(in srgb, var(--accent) 40%, transparent);
        background: var(--accent-soft);
        color: var(--accent);
    }

    .token-status.status-expired
    {
        border-color: color-mix(in srgb, var(--status-warn) 40%, transparent);
        background: color-mix(in srgb, var(--status-warn) 14%, transparent);
        color: var(--status-warn);
    }

    .token-status.status-revoked
    {
        border-color: color-mix(in srgb, var(--status-bad) 40%, transparent);
        background: color-mix(in srgb, var(--status-bad) 14%, transparent);
        color: var(--status-bad);
    }

    /* ---------- Kosong ---------- */
    .token-empty-state
    {
        padding: 64px 24px;
        text-align: center;
    }

    .token-empty-icon
    {
        width: 56px;
        height: 56px;
        display: grid;
        place-items: center;
        margin: 0 auto 18px;
        border: 1px solid color-mix(in srgb, var(--accent) 40%, transparent);
        border-radius: 16px;
        background: var(--accent-soft);
        color: var(--accent);
        box-shadow: 0 0 30px -8px var(--accent);
    }

    .token-empty-icon svg
    {
        width: 24px;
        height: 24px;
    }

    .token-empty-state h3
    {
        margin: 0 0 8px;
        font-size: 16px;
        font-weight: 800;
        color: var(--ink);
    }

    .token-empty-state p
    {
        margin: 0;
        font-size: 13px;
        color: var(--muted);
    }

    /* ---------- Pagination ---------- */
    .token-pagination
    {
        padding: 18px 24px;
        border-top: 1px solid var(--line);
        font-size: 13px;
        color: var(--muted);
    }

    .token-pagination svg
    {
        width: 16px;
        height: 16px;
    }

    .token-pagination p
    {
        margin: 0;
        color: var(--muted);
    }

    .token-pagination a
    {
        color: var(--accent);
        text-decoration: none;
    }

    /* ---------- Animasi ---------- */
    @keyframes token-pulse
    {
        0%
        {
            box-shadow: 0 0 0 0 color-mix(in srgb, currentColor 55%, transparent);
        }

        100%
        {
            box-shadow: 0 0 0 8px transparent;
        }
    }

    @keyframes token-loading
    {
        0%
        {
            background-position: -40% 0;
        }

        100%
        {
            background-position: 140% 0;
        }
    }

    @keyframes token-fade-in
    {
        0%
        {
            opacity: 0;
            transform: translateY(4px);
        }

        100%
        {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (prefers-reduced-motion: reduce)
    {
        .token-panel-live::before,
        .token-status.status-active .token-status-dot,
        .token-list-region.is-loading::before,
        .token-list-region.is-fresh
        {
            animation: none;
        }

        .token-summary-card,
        .token-summary-meter-fill,
        .token-list-region
        {
            transition: none;
        }
    }

    @media (max-width: 1200px)
    {
        .token-summary
        {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 900px)
    {
        .token-create-form
        {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .token-create-form .token-filter-button
        {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 700px)
    {
        .token-summary
        {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .token-filter-form
        {
            flex-direction: column;
            align-items: stretch;
        }

        .token-filter-form select
        {
            width: 100%;
        }

        .token-search
        {
            min-width: 0;
        }

        .token-filter-button,
        .token-reset-button
        {
            width: 100%;
        }

        .token-panel-header
        {
            padding: 22px 16px 16px;
        }

        .token-create-panel
        {
            padding: 18px 16px;
        }

        .token-list-error
        {
            margin: 16px 16px 0;
        }

        .token-create-form
        {
            grid-template-columns: 1fr;
        }

        .token-create-panel .token-create-result input
        {
            min-width: 0;
            flex: 0 0 100%;
        }
    }

    @media (max-width: 480px)
    {
        .token-summary
        {
            grid-template-columns: 1fr;
        }
    }
</style>

@endpush
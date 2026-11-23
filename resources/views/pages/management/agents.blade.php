@extends('layouts.app')



@section('title', 'Agents - NADI Console')



@section('breadcrumb', 'Management / Agents')



@section('content')



@php

    $totalBase = max((int) $totalAgents, 1);

    $registeredPercent = round(((int) $registeredAgents / $totalBase) * 100);

    $installedPercent = round(((int) $installedAgents / $totalBase) * 100);

    $uninstalledPercent = round(((int) $uninstalledAgents / $totalBase) * 100);

@endphp



<div class="agents-page">



    <div class="page-header">

        <div>

            <h1 class="page-title">Agents</h1>

            <p class="page-description">Manage and monitor NADI agents installed across servers.</p>

        </div>

    </div>



    <div class="agent-summary">



        <div class="agent-summary-card">

            <div class="agent-summary-top">

                <div class="agent-summary-icon">

                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">

                        <rect x="5" y="5" width="14" height="14" rx="3"/>

                        <path d="M9 2v3M15 2v3M9 19v3M15 19v3M2 9h3M2 15h3M19 9h3M19 15h3"/>

                        <path d="M10 10h4v4h-4z"/>

                    </svg>

                </div>

                <div class="agent-summary-label">Total Agents</div>

            </div>

            <div class="agent-summary-value">{{ number_format($totalAgents) }}</div>

            <div class="agent-summary-description">Agents known to NADI Central</div>

            <div class="agent-summary-meter">

                <div class="agent-summary-meter-fill" style="width: 100%;"></div>

            </div>

        </div>



        <div class="agent-summary-card">

            <div class="agent-summary-top">

                <div class="agent-summary-icon">

                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">

                        <circle cx="12" cy="12" r="9"/>

                        <path d="M8 12.5l3 3 5-6"/>

                    </svg>

                </div>

                <div class="agent-summary-label">Registered</div>

            </div>

            <div class="agent-summary-value">

                {{ number_format($registeredAgents) }}

                <span class="agent-summary-percent">{{ $registeredPercent }}%</span>

            </div>

            <div class="agent-summary-description">Status: registered</div>

            <div class="agent-summary-meter">

                <div class="agent-summary-meter-fill" style="width: {{ $registeredPercent }}%;"></div>

            </div>

        </div>



        <div class="agent-summary-card">

            <div class="agent-summary-top">

                <div class="agent-summary-icon">

                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">

                        <path d="M12 4v11"/>

                        <path d="M7.5 11l4.5 4.5 4.5-4.5"/>

                        <path d="M5 19h14"/>

                    </svg>

                </div>

                <div class="agent-summary-label">Installed</div>

            </div>

            <div class="agent-summary-value">

                {{ number_format($installedAgents) }}

                <span class="agent-summary-percent">{{ $installedPercent }}%</span>

            </div>

            <div class="agent-summary-description">Installation state: installed</div>

            <div class="agent-summary-meter">

                <div class="agent-summary-meter-fill" style="width: {{ $installedPercent }}%;"></div>

            </div>

        </div>



        <div class="agent-summary-card">

            <div class="agent-summary-top">

                <div class="agent-summary-icon">

                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">

                        <circle cx="12" cy="12" r="9"/>

                        <path d="M9 9l6 6M15 9l-6 6"/>

                    </svg>

                </div>

                <div class="agent-summary-label">Uninstalled</div>

            </div>

            <div class="agent-summary-value">

                {{ number_format($uninstalledAgents) }}

                <span class="agent-summary-percent">{{ $uninstalledPercent }}%</span>

            </div>

            <div class="agent-summary-description">Installation state: uninstalled</div>

            <div class="agent-summary-meter">

                <div class="agent-summary-meter-fill" style="width: {{ $uninstalledPercent }}%;"></div>

            </div>

        </div>



    </div>



    <div class="agent-panel">



        <div class="agent-panel-header">

            <div>

                <h2>

                    Agent Inventory

                    <span class="agent-panel-live">Live</span>

                </h2>

                <p>Registered NADI agents and their server assignments.</p>

            </div>

        </div>



        <form method="GET" action="{{ route('management.agents') }}" class="agent-filter-form">



            <div class="agent-search">

                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">

                    <circle cx="11" cy="11" r="7"/>

                    <path d="M20 20l-3.5-3.5"/>

                </svg>



                <input

                    type="text"

                    name="search"

                    value="{{ request('search') }}"

                    placeholder="Search agent, server, IP, or version..."

                >

            </div>



            <select name="status">

                <option value="">All Statuses</option>



                @foreach ($statuses as $status)

                    <option

                        value="{{ $status }}"

                        {{ request('status') === $status ? 'selected' : '' }}

                    >

                        {{ ucfirst(str_replace('\_', ' ', $status)) }}

                    </option>

                @endforeach

            </select>



            <select name="installation_state">

                <option value="">All Installation States</option>



                @foreach ($installationStates as $installationState)

                    <option

                        value="{{ $installationState }}"

                        {{ request('installation_state') === $installationState ? 'selected' : '' }}

                    >

                        {{ ucfirst(str_replace('\_', ' ', $installationState)) }}

                    </option>

                @endforeach

            </select>



            <button type="submit" class="agent-filter-button">

                Filter

            </button>



            @if (request()->hasAny(['search', 'status', 'installation_state']))

                <a href="{{ route('management.agents') }}" class="agent-reset-button">

                    Reset

                </a>

            @endif



        </form>



        <div class="agent-table-wrapper">



            <table class="agent-table">



                <thead>

                    <tr>

                        <th>Agent</th>

                        <th>Server</th>

                        <th>Version</th>

                        <th>Installation</th>

                        <th>Status</th>

                        <th>Applications</th>

                        <th>Last Heartbeat</th>

                        <th>Installed At</th>

                        <th>Actions</th>

                    </tr>

                </thead>



                <tbody>



                    @forelse ($agents as $agent)



                        <tr>



                            <td>

                                <div class="agent-main">

                                    <div class="agent-main-icon">

                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">

                                            <rect x="5" y="5" width="14" height="14" rx="3"/>

                                            <path d="M9 2v3M15 2v3M9 19v3M15 19v3M2 9h3M2 15h3M19 9h3M19 15h3"/>

                                            <path d="M10 10h4v4h-4z"/>

                                        </svg>

                                    </div>



                                    <div>

                                        <div class="agent-primary agent-instance">

                                            {{ $agent->agent_instance_id ?: 'Unknown Agent' }}

                                        </div>



                                        <div class="agent-secondary">

                                            ID: {{ $agent->id }}

                                        </div>

                                    </div>

                                </div>

                            </td>



                            <td>

                                @if ($agent->server)

                                    <div class="agent-primary">

                                        {{ $agent->server->hostname }}

                                    </div>



                                    <div class="agent-secondary">

                                        <span class="agent-chip">

                                            {{ $agent->server->ip_address ?: 'No IP address' }}

                                        </span>

                                    </div>

                                @else

                                    <span class="agent-secondary">

                                        Server unavailable

                                    </span>

                                @endif

                            </td>



                            <td>

                                <div class="agent-version">

                                    {{ $agent->agent_version ?: '—' }}

                                </div>



                                <div class="agent-secondary">

                                    Protocol {{ $agent->protocol_version ?: '—' }}

                                </div>

                            </td>



                            <td>

                                <span class="agent-status installation-{{ strtolower($agent->installation_state) }}">

                                    <span class="agent-status-dot"></span>

                                    {{ ucfirst(str_replace('\_', ' ', $agent->installation_state)) }}

                                </span>

                            </td>



                            <td>

                                <span class="agent-status status-{{ strtolower($agent->status) }}">

                                    <span class="agent-status-dot"></span>

                                    {{ ucfirst(str_replace('\_', ' ', $agent->status)) }}

                                </span>

                            </td>



                            <td>

                                <div class="agent-application-count">

                                    {{ number_format($agent->applications_count) }}

                                </div>

                            </td>



                            <td>

                                @if ($agent->last_heartbeat_at)

                                    <div class="agent-primary agent-date">

                                        {{ $agent->last_heartbeat_at->format('d M Y') }}

                                    </div>



                                    <div class="agent-secondary agent-time">

                                        {{ $agent->last_heartbeat_at->format('H:i:s') }}

                                        · {{ $agent->last_heartbeat_at->diffForHumans() }}

                                    </div>

                                @else

                                    <span class="agent-secondary">

                                        Never

                                    </span>

                                @endif

                            </td>



                            <td>

                                @if ($agent->installed_at)

                                    <div class="agent-primary agent-date">

                                        {{ $agent->installed_at->format('d M Y') }}

                                    </div>



                                    <div class="agent-secondary agent-time">

                                        {{ $agent->installed_at->format('H:i:s') }}

                                    </div>

                                @else

                                    <span class="agent-secondary">

                                        —

                                    </span>

                                @endif

                            </td>

                            <td>

                                <button
                                    type="button"
                                    class="agent-manage-button"
                                    data-agent-id="{{ $agent->id }}"
                                    data-agent-name="{{ $agent->agent_instance_id ?: 'Unknown Agent' }}"
                                    data-credentials-url="{{ route('management.agent-credentials.index', ['agent' => $agent->id]) }}"
                                    data-issue-url="{{ route('management.agent-credentials.issue', ['agent' => $agent->id]) }}"
                                    data-revoke-url-template="{{ route('management.agent-credentials.revoke', ['agent' => $agent->id, 'credential' => '__CREDENTIAL__']) }}"
                                >
                                    Manage Credentials
                                </button>

                            </td>

                        </tr>



                    @empty



                        <tr>

                            <td colspan="9">

                                <div class="agent-empty-state">

                                    <div class="agent-empty-icon">

                                        <svg

                                            viewBox="0 0 24 24"

                                            fill="none"

                                            stroke="currentColor"

                                            stroke-width="1.7"

                                            stroke-linecap="round"

                                            stroke-linejoin="round"

                                        >

                                            <rect x="3" y="4" width="18" height="6" rx="2"/>

                                            <rect x="3" y="14" width="18" height="6" rx="2"/>

                                            <path d="M7 7h.01M7 17h.01"/>

                                        </svg>

                                    </div>



                                    <h3>No agents found</h3>



                                    <p>

                                        NADI agents will appear here after they are registered.

                                    </p>

                                </div>

                            </td>

                        </tr>



                    @endforelse



                </tbody>



            </table>



        </div>



        @if ($agents->hasPages())

            <div class="agent-pagination">

                {{ $agents->links() }}

            </div>

        @endif



    </div>



</div>

<div class="agent-credential-modal" id="agentCredentialModal" aria-hidden="true">
    <div class="agent-credential-modal-backdrop" data-credential-close></div>

    <section class="agent-credential-dialog" role="dialog" aria-modal="true" aria-labelledby="agentCredentialTitle">
        <div class="agent-credential-dialog-header">
            <div>
                <h2 id="agentCredentialTitle">Agent Credentials</h2>
                <p id="agentCredentialAgentName">Manage credentials for this agent.</p>
            </div>

            <button type="button" class="agent-credential-close" data-credential-close aria-label="Close">
                &times;
            </button>
        </div>

        <div class="agent-credential-dialog-body">
            <div class="agent-credential-toolbar">
                <div>
                    <strong>Credential Inventory</strong>
                    <p>Manage authentication credentials assigned to this agent.</p>
                </div>

                <form id="agentCredentialIssueForm">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <label class="agent-credential-lifetime-label" for="agentCredentialLifetime">Lifetime</label>
                    <select id="agentCredentialLifetime" name="lifetime_days">
                        <option value="30">30 days</option>
                        <option value="90" selected>90 days</option>
                        <option value="180">180 days</option>
                        <option value="365">365 days</option>
                    </select>
                    <button type="submit" class="agent-credential-primary-button" id="agentCredentialIssueButton">Issue Credential</button>
                </form>
            </div>

            <div class="agent-credential-alert" id="agentCredentialAlert" role="status" aria-live="polite" hidden></div>

            <div class="agent-credential-token-panel" id="agentCredentialTokenPanel" hidden>
                <div class="agent-credential-token-heading">
                    <strong>New credential generated</strong>
                    <span>Copy this token now. It will not be shown again.</span>
                </div>
                <div class="agent-credential-token-row">
                    <input type="text" id="agentCredentialToken" readonly autocomplete="off" spellcheck="false">
                    <button type="button" id="agentCredentialCopyButton" class="agent-credential-secondary-button">Copy token</button>
                </div>
            </div>

            <div class="agent-credential-table-wrapper">
                <table class="agent-credential-table">
                    <thead>
                        <tr>
                            <th>Credential</th>
                            <th>Status</th>
                            <th>Issued</th>
                            <th>Expires</th>
                            <th>Last Used</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="agentCredentialTableBody">
                        <tr>
                            <td colspan="6" class="agent-credential-empty">Open an agent to load credentials.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<script>
    (() =>
    {
        const modal = document.getElementById('agentCredentialModal');

        if (!modal)
        {
            return;
        }

        const title = document.getElementById('agentCredentialAgentName');
        const tableBody = document.getElementById('agentCredentialTableBody');
        const alertBox = document.getElementById('agentCredentialAlert');
        const tokenPanel = document.getElementById('agentCredentialTokenPanel');
        const tokenInput = document.getElementById('agentCredentialToken');
        const issueForm = document.getElementById('agentCredentialIssueForm');
        const issueButton = document.getElementById('agentCredentialIssueButton');
        const copyButton = document.getElementById('agentCredentialCopyButton');

        let currentAgent = null;

        const csrfToken = issueForm.querySelector('input[name="_token"]').value;

        const escapeHtml = (value) =>
        {
            return String(value ?? '').replace(/[&<>"']/g, (character) =>
            {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[character];
            });
        };

        const showAlert = (message, type = 'error') =>
        {
            alertBox.textContent = message;
            alertBox.className = `agent-credential-alert ${type}`;
            alertBox.hidden = false;
        };

        const clearAlert = () =>
        {
            alertBox.textContent = '';
            alertBox.hidden = true;
        };

        const formatDate = (value) =>
        {
            if (!value)
            {
                return '—';
            }

            const date = new Date(value);

            if (Number.isNaN(date.getTime()))
            {
                return '—';
            }

            return date.toLocaleString();
        };

        const requestJson = async (url, options = {}) =>
        {
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                    ...(options.headers || {})
                }
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok || payload.success === false)
            {
                throw new Error(payload.message || `Request failed with status ${response.status}.`);
            }

            return payload;
        };

        const renderCredentials = (credentials) =>
        {
            if (!credentials.length)
            {
                tableBody.innerHTML = '<tr><td colspan="6" class="agent-credential-empty">No credentials have been issued for this agent.</td></tr>';
                return;
            }

            tableBody.innerHTML = credentials.map((credential) =>
            {
                const status = String(credential.status || 'unknown').toLowerCase();
                const canRevoke = status === 'active' && !credential.revoked_at;
                const revokeButton = canRevoke
                    ? `<button type="button" class="agent-credential-revoke-button" data-revoke-credential="${escapeHtml(credential.id)}">Revoke</button>`
                    : '—';

                return `
                    <tr>
                        <td>
                            <strong>${escapeHtml(credential.id)}</strong>
                            <span class="agent-credential-version">Key version ${escapeHtml(credential.key_version)}</span>
                        </td>
                        <td><span class="agent-credential-status status-${escapeHtml(status)}">${escapeHtml(status)}</span></td>
                        <td>${escapeHtml(formatDate(credential.issued_at))}</td>
                        <td>${escapeHtml(formatDate(credential.expires_at))}</td>
                        <td>${escapeHtml(formatDate(credential.last_used_at))}</td>
                        <td>${revokeButton}</td>
                    </tr>
                `;
            }).join('');
        };

        const loadCredentials = async () =>
        {
            if (!currentAgent)
            {
                return;
            }

            tableBody.innerHTML = '<tr><td colspan="6" class="agent-credential-empty">Loading credentials...</td></tr>';

            try
            {
                const payload = await requestJson(currentAgent.credentialsUrl);
                renderCredentials(payload.data.credentials || []);
            }
            catch (error)
            {
                tableBody.innerHTML = '<tr><td colspan="6" class="agent-credential-empty">Unable to load credentials.</td></tr>';
                showAlert(error.message);
            }
        };

        const openModal = async (button) =>
        {
            currentAgent = {
                id: button.dataset.agentId,
                name: button.dataset.agentName,
                credentialsUrl: button.dataset.credentialsUrl,
                issueUrl: button.dataset.issueUrl,
                revokeUrlTemplate: button.dataset.revokeUrlTemplate
            };

            title.textContent = currentAgent.name;
            tokenPanel.hidden = true;
            tokenInput.value = '';
            clearAlert();
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('agent-credential-modal-open');

            await loadCredentials();
        };

        const closeModal = () =>
        {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('agent-credential-modal-open');
            currentAgent = null;
            tokenInput.value = '';
            tokenPanel.hidden = true;
            clearAlert();
        };

        document.querySelectorAll('.agent-manage-button').forEach((button) =>
        {
            button.addEventListener('click', () => openModal(button));
        });

        modal.querySelectorAll('[data-credential-close]').forEach((button) =>
        {
            button.addEventListener('click', closeModal);
        });

        issueForm.addEventListener('submit', async (event) =>
        {
            event.preventDefault();
            clearAlert();
            tokenPanel.hidden = true;
            issueButton.disabled = true;
            issueButton.textContent = 'Issuing...';

            try
            {
                const payload = await requestJson(currentAgent.issueUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        lifetime_days: Number(document.getElementById('agentCredentialLifetime').value)
                    })
                });

                tokenInput.value = payload.data.token;
                tokenPanel.hidden = false;
                showAlert('Credential issued. Copy the token now; it will not be displayed again.', 'success');
                await loadCredentials();
            }
            catch (error)
            {
                showAlert(error.message);
            }
            finally
            {
                issueButton.disabled = false;
                issueButton.textContent = 'Issue Credential';
            }
        });

        copyButton.addEventListener('click', async () =>
        {
            if (!tokenInput.value)
            {
                return;
            }

            try
            {
                await navigator.clipboard.writeText(tokenInput.value);
                showAlert('Credential copied to clipboard.', 'success');
            }
            catch (error)
            {
                tokenInput.focus();
                tokenInput.select();
                showAlert('Clipboard access is unavailable. Copy the selected token manually.', 'error');
            }
        });

        tableBody.addEventListener('click', async (event) =>
        {
            const button = event.target.closest('[data-revoke-credential]');

            if (!button || !currentAgent)
            {
                return;
            }

            const credentialId = button.dataset.revokeCredential;

            if (!window.confirm('Revoke this credential? The agent will no longer be able to authenticate with it.'))
            {
                return;
            }

            button.disabled = true;

            try
            {
                const revokeUrl = currentAgent.revokeUrlTemplate.replace('__CREDENTIAL__', encodeURIComponent(credentialId));

                await requestJson(revokeUrl, {
                    method: 'POST'
                });

                showAlert('Credential revoked successfully.', 'success');
                await loadCredentials();
            }
            catch (error)
            {
                button.disabled = false;
                showAlert(error.message);
            }
        });

        document.addEventListener('keydown', (event) =>
        {
            if (event.key === 'Escape' && modal.classList.contains('is-open'))
            {
                closeModal();
            }
        });
    })();
</script>


@endsection



@push('styles')



<style>

    .agents-page

    {

        --status-ok: #16a34a;

        --status-warn: #d97706;

        --status-bad: #dc2626;

        display: flex;

        flex-direction: column;

        gap: 24px;

    }



    .agents-page .page-header

    {

        margin-bottom: 0;

    }



    /* ---------- Ringkasan ---------- */

    .agent-summary

    {

        display: grid;

        grid-template-columns: repeat(4, minmax(0, 1fr));

        gap: 18px;

    }



    .agent-summary-card

    {

        position: relative;

        padding: 22px 24px;

        border: 1px solid var(--line);

        border-radius: 16px;

        background-color: var(--surface);

        background-image: radial-gradient(circle at 100% 0%, color-mix(in srgb, var(--accent) 14%, transparent), transparent 55%), linear-gradient(color-mix(in srgb, var(--accent) 5%, transparent) 1px, transparent 1px), linear-gradient(90deg, color-mix(in srgb, var(--accent) 5%, transparent) 1px, transparent 1px);

        background-size: auto, 22px 22px, 22px 22px;

        box-shadow: var(--shadow);

        overflow: hidden;

        transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease;

    }



    .agent-summary-card:hover

    {

        transform: translateY(-3px);

        border-color: color-mix(in srgb, var(--accent) 45%, var(--line));

        box-shadow: var(--shadow), 0 18px 40px -22px var(--accent);

    }



    .agent-summary-card::before

    {

        content: '';

        position: absolute;

        top: -1px;

        left: 24px;

        width: 44px;

        height: 2px;

        border-radius: 0 0 3px 3px;

        background: var(--brand);

    }



    /* Siku HUD di pojok kanan bawah */

    .agent-summary-card::after

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



    .agent-summary-top

    {

        display: flex;

        align-items: center;

        gap: 12px;

        margin-bottom: 16px;

    }



    .agent-summary-icon

    {

        width: 40px;

        height: 40px;

        display: grid;

        place-items: center;

        border: 1px solid color-mix(in srgb, var(--accent) 40%, transparent);

        border-radius: 12px;

        background: var(--accent-soft);

        color: var(--accent);

        box-shadow: 0 0 22px -6px var(--accent), inset 0 0 12px -6px var(--accent);

    }



    .agent-summary-icon svg

    {

        width: 19px;

        height: 19px;

    }



    .agent-summary-label

    {

        font: 600 11.5px var(--mono);

        letter-spacing: .08em;

        text-transform: uppercase;

        color: var(--muted);

    }



    .agent-summary-value

    {

        display: flex;

        align-items: baseline;

        gap: 10px;

        font-size: 34px;

        font-weight: 800;

        letter-spacing: -.03em;

        line-height: 1.1;

        color: var(--ink);

    }



    .agent-summary-percent

    {

        padding: 3px 9px;

        border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent);

        border-radius: 999px;

        background: var(--accent-soft);

        color: var(--accent);

        font: 600 11px var(--mono);

        letter-spacing: 0;

    }



    .agent-summary-description

    {

        margin-top: 8px;

        font: 500 11.5px var(--mono);

        color: var(--muted);

    }



    .agent-summary-meter

    {

        height: 4px;

        margin-top: 16px;

        border-radius: 999px;

        background: var(--surface-2);

        overflow: hidden;

    }



    .agent-summary-meter-fill

    {

        height: 100%;

        border-radius: 999px;

        background: var(--brand);

        box-shadow: 0 0 12px var(--accent);

        transition: width .8s cubic-bezier(.2, .8, .2, 1);

    }



    /* ---------- Panel inventaris ---------- */

    .agent-panel

    {

        position: relative;

        border: 1px solid var(--line);

        border-radius: 18px;

        background: var(--surface);

        box-shadow: var(--shadow);

        overflow: hidden;

    }



    .agent-panel::before

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



    .agent-panel-header

    {

        padding: 26px 24px 20px;

        border-bottom: 1px solid var(--line);

        background-image: linear-gradient(180deg, color-mix(in srgb, var(--accent) 6%, transparent), transparent);

    }



    .agent-panel-header h2

    {

        display: flex;

        align-items: center;

        gap: 10px;

        margin: 0;

        font-size: 16px;

        font-weight: 800;

        color: var(--ink);

    }



    .agent-panel-header p

    {

        margin: 5px 0 0;

        font: 500 12px var(--mono);

        color: var(--muted);

    }



    .agent-panel-live

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



    .agent-panel-live::before

    {

        content: '';

        width: 6px;

        height: 6px;

        border-radius: 50%;

        background: currentColor;

        animation: agent-pulse 2s ease-out infinite;

    }



    /* ---------- Filter ---------- */

    .agent-filter-form

    {

        display: flex;

        align-items: center;

        flex-wrap: wrap;

        gap: 10px;

        padding: 18px 24px;

        border-bottom: 1px solid var(--line);

    }



    .agent-search

    {

        position: relative;

        flex: 1;

        min-width: 220px;

    }



    .agent-search svg

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



    .agent-search:focus-within svg

    {

        color: var(--accent);

    }



    .agent-filter-form input,

    .agent-filter-form select

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



    .agent-filter-form input

    {

        padding-left: 36px;

    }



    .agent-filter-form input::placeholder

    {

        color: var(--muted);

        opacity: .8;

    }



    .agent-filter-form select

    {

        width: auto;

        min-width: 150px;

    }



    .agent-filter-form input:focus,

    .agent-filter-form select:focus

    {

        background: var(--surface);

        border-color: var(--accent);

        box-shadow: 0 0 0 4px var(--accent-soft), 0 0 22px -8px var(--accent);

    }



    .agent-filter-button,

    .agent-reset-button

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



    .agent-filter-button

    {

        border: 0;

        background: var(--brand);

        color: #ffffff;

        box-shadow: 0 10px 22px -12px rgba(47, 76, 240, .9), inset 0 1px 0 rgba(255, 255, 255, .3);

    }



    .agent-filter-button:hover

    {

        filter: brightness(1.08);

        transform: translateY(-1px);

    }



    .agent-reset-button

    {

        border: 1.5px solid var(--line);

        background: transparent;

        color: var(--muted);

    }



    .agent-reset-button:hover

    {

        border-color: var(--accent);

        color: var(--accent);

    }



    /* ---------- Tabel ---------- */

    .agent-table-wrapper

    {

        width: 100%;

        overflow-x: auto;

    }



    .agent-table

    {

        width: 100%;

        border-collapse: collapse;

        white-space: nowrap;

    }



    .agent-table th

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



    .agent-table td

    {

        padding: 16px 20px;

        border-bottom: 1px solid var(--line);

        font-size: 13px;

        color: var(--muted);

        vertical-align: middle;

    }



    .agent-table tbody tr:last-child td

    {

        border-bottom: 0;

    }



    .agent-table tbody tr

    {

        transition: background .2s;

    }



    .agent-table tbody tr:hover

    {

        background: var(--accent-soft);

    }



    .agent-table tbody tr td:first-child

    {

        box-shadow: inset 0 0 0 var(--accent);

        transition: box-shadow .2s;

    }



    .agent-table tbody tr:hover td:first-child

    {

        box-shadow: inset 3px 0 0 var(--accent);

    }



    .agent-main

    {

        display: flex;

        align-items: center;

        gap: 12px;

    }



    .agent-main-icon

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



    .agent-main-icon svg

    {

        width: 16px;

        height: 16px;

    }



    .agent-primary

    {

        color: var(--ink);

        font-size: 13px;

        font-weight: 700;

    }



    .agent-instance

    {

        font: 600 12px var(--mono);

    }



    .agent-date

    {

        font: 600 12px var(--mono);

    }



    .agent-secondary

    {

        margin-top: 5px;

        font: 500 11px var(--mono);

        color: var(--muted);

    }



    .agent-chip

    {

        display: inline-block;

        padding: 2px 8px;

        border: 1px solid var(--line);

        border-radius: 6px;

        background: var(--surface-2);

        color: var(--muted);

        font: 500 11px var(--mono);

    }



    .agent-version

    {

        display: inline-block;

        padding: 4px 10px;

        border: 1px dashed color-mix(in srgb, var(--accent) 45%, var(--line));

        border-radius: 8px;

        color: var(--accent);

        font: 600 12px var(--mono);

    }



    /* ---------- Status ---------- */

    .agent-status

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



    .agent-status-dot

    {

        width: 6px;

        height: 6px;

        border-radius: 50%;

        background: currentColor;

    }



    .agent-status.status-registered,

    .agent-status.status-active,

    .agent-status.installation-installed

    {

        border-color: color-mix(in srgb, var(--status-ok) 40%, transparent);

        background: color-mix(in srgb, var(--status-ok) 14%, transparent);

        color: var(--status-ok);

        box-shadow: 0 0 18px -8px var(--status-ok);

    }



    .agent-status.status-registered .agent-status-dot,

    .agent-status.status-active .agent-status-dot,

    .agent-status.installation-installed .agent-status-dot

    {

        animation: agent-pulse 2s ease-out infinite;

    }



    .agent-status.status-pending,

    .agent-status.installation-pending

    {

        border-color: color-mix(in srgb, var(--status-warn) 40%, transparent);

        background: color-mix(in srgb, var(--status-warn) 14%, transparent);

        color: var(--status-warn);

    }



    .agent-status.status-failed,

    .agent-status.status-revoked,

    .agent-status.installation-failed

    {

        border-color: color-mix(in srgb, var(--status-bad) 40%, transparent);

        background: color-mix(in srgb, var(--status-bad) 14%, transparent);

        color: var(--status-bad);

    }



    .agent-status.status-inactive,

    .agent-status.status-suspended,

    .agent-status.installation-uninstalled

    {

        border-color: var(--line);

        background: var(--surface-2);

        color: var(--muted);

    }



    .agent-application-count

    {

        display: inline-flex;

        align-items: center;

        justify-content: center;

        min-width: 32px;

        padding: 4px 10px;

        border: 1px solid color-mix(in srgb, var(--accent) 30%, transparent);

        border-radius: 8px;

        background: var(--accent-soft);

        color: var(--accent);

        font: 700 13px var(--mono);

    }



    /* ---------- Kosong ---------- */

    .agent-empty-state

    {

        padding: 64px 24px;

        text-align: center;

    }



    .agent-empty-icon

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



    .agent-empty-icon svg

    {

        width: 24px;

        height: 24px;

    }



    .agent-empty-state h3

    {

        margin: 0 0 8px;

        font-size: 16px;

        font-weight: 800;

        color: var(--ink);

    }



    .agent-empty-state p

    {

        margin: 0;

        font-size: 13px;

        color: var(--muted);

    }



    /* ---------- Pagination ---------- */

    .agent-pagination

    {

        padding: 18px 24px;

        border-top: 1px solid var(--line);

        font-size: 13px;

        color: var(--muted);

    }



    .agent-pagination svg

    {

        width: 16px;

        height: 16px;

    }



    .agent-pagination p

    {

        margin: 0;

        color: var(--muted);

    }



    .agent-pagination a

    {

        color: var(--accent);

        text-decoration: none;

    }



    /* ---------- Animasi ---------- */

    @keyframes agent-pulse

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



    @media (prefers-reduced-motion: reduce)

    {

        .agent-panel-live::before,

        .agent-status.status-registered .agent-status-dot,

        .agent-status.status-active .agent-status-dot,

        .agent-status.installation-installed .agent-status-dot

        {

            animation: none;

        }



        .agent-summary-card,

        .agent-summary-meter-fill

        {

            transition: none;

        }

    }



    @media (max-width: 1100px)

    {

        .agent-summary

        {

            grid-template-columns: repeat(2, minmax(0, 1fr));

        }

    }



    @media (max-width: 700px)

    {

        .agent-filter-form

        {

            flex-direction: column;

            align-items: stretch;

        }



        .agent-filter-form select

        {

            width: 100%;

        }



        .agent-search

        {

            min-width: 0;

        }



        .agent-filter-button,

        .agent-reset-button

        {

            width: 100%;

        }



        .agent-panel-header

        {

            padding: 22px 16px 16px;

        }

    }



    @media (max-width: 480px)

    {

        .agent-summary

        {

            grid-template-columns: 1fr;

        }

    }



    /* ---------- Credential Management ---------- */
    .agent-manage-button
    {
        padding: 8px 12px;
        border: 1px solid color-mix(in srgb, var(--accent) 40%, var(--line));
        border-radius: 9px;
        background: var(--accent-soft);
        color: var(--accent);
        font: 700 11px var(--font);
        cursor: pointer;
        transition: background .2s, transform .2s;
    }

    .agent-manage-button:hover
    {
        transform: translateY(-1px);
        background: color-mix(in srgb, var(--accent) 16%, var(--surface));
    }

    .agent-manage-button:focus-visible,
    .agent-credential-modal button:focus-visible,
    .agent-credential-modal select:focus-visible,
    .agent-credential-modal input:focus-visible
    {
        outline: 2px solid var(--accent);
        outline-offset: 2px;
    }

    .agent-credential-modal[aria-hidden="true"]
    {
        display: none;
    }

    .agent-credential-modal.is-open
    {
        position: fixed;
        z-index: 1500;
        inset: 0;
        display: grid;
        place-items: center;
        padding: 24px;
    }

    .agent-credential-modal-backdrop
    {
        position: absolute;
        inset: 0;
        background: rgba(4, 10, 24, .68);
        backdrop-filter: blur(4px);
    }

    .agent-credential-dialog
    {
        position: relative;
        z-index: 1;
        width: min(1050px, 100%);
        max-height: min(820px, 90vh);
        overflow: auto;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: var(--surface);
        color: var(--ink);
        box-shadow: 0 24px 80px rgba(0, 0, 0, .35);
    }

    .agent-credential-dialog-header
    {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        padding: 22px 24px;
        border-bottom: 1px solid var(--line);
        background: linear-gradient(180deg, color-mix(in srgb, var(--accent) 7%, transparent), transparent);
    }

    .agent-credential-dialog-header h2
    {
        margin: 0;
        color: var(--ink);
        font-size: 17px;
        font-weight: 800;
    }

    .agent-credential-dialog-header p,
    .agent-credential-toolbar p
    {
        margin: 5px 0 0;
        color: var(--muted);
        font: 500 11px var(--mono);
        overflow-wrap: anywhere;
    }

    .agent-credential-close
    {
        width: 34px;
        height: 34px;
        border: 1px solid var(--line);
        border-radius: 9px;
        background: var(--surface-2);
        color: var(--muted);
        font-size: 22px;
        cursor: pointer;
    }

    .agent-credential-dialog-body
    {
        padding: 22px 24px 24px;
    }

    .agent-credential-toolbar
    {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 18px;
    }

    .agent-credential-toolbar strong
    {
        color: var(--ink);
        font-size: 13px;
    }

    #agentCredentialIssueForm
    {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .agent-credential-lifetime-label
    {
        color: var(--muted);
        font-size: 11px;
        font-weight: 600;
    }

    #agentCredentialLifetime
    {
        min-height: 38px;
        padding: 0 10px;
        border: 1px solid var(--line);
        border-radius: 8px;
        background: var(--surface-2);
        color: var(--ink);
        font: 500 12px var(--font);
    }

    .agent-credential-primary-button,
    .agent-credential-secondary-button
    {
        min-height: 38px;
        padding: 0 13px;
        border: 0;
        border-radius: 8px;
        font: 700 12px var(--font);
        cursor: pointer;
    }

    .agent-credential-primary-button
    {
        background: var(--brand);
        color: #ffffff;
    }

    .agent-credential-secondary-button
    {
        border: 1px solid var(--line);
        background: var(--surface-2);
        color: var(--ink);
    }

    .agent-credential-primary-button:disabled,
    .agent-credential-revoke-button:disabled
    {
        cursor: not-allowed;
        opacity: .55;
    }

    .agent-credential-alert
    {
        margin: 0 0 16px;
        padding: 11px 13px;
        border: 1px solid color-mix(in srgb, var(--status-bad) 35%, transparent);
        border-radius: 9px;
        background: color-mix(in srgb, var(--status-bad) 9%, transparent);
        color: var(--status-bad);
        font-size: 12px;
    }

    .agent-credential-alert.success
    {
        border-color: color-mix(in srgb, var(--status-ok) 35%, transparent);
        background: color-mix(in srgb, var(--status-ok) 9%, transparent);
        color: var(--status-ok);
    }

    .agent-credential-token-panel
    {
        margin-bottom: 18px;
        padding: 15px;
        border: 1px solid color-mix(in srgb, var(--status-ok) 35%, transparent);
        border-radius: 11px;
        background: color-mix(in srgb, var(--status-ok) 7%, var(--surface));
    }

    .agent-credential-token-heading
    {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin-bottom: 10px;
    }

    .agent-credential-token-heading strong
    {
        color: var(--ink);
        font-size: 13px;
    }

    .agent-credential-token-heading span
    {
        color: var(--muted);
        font-size: 11px;
    }

    .agent-credential-token-row
    {
        display: flex;
        gap: 8px;
    }

    .agent-credential-token-row input
    {
        flex: 1;
        min-width: 0;
        min-height: 38px;
        padding: 0 10px;
        border: 1px solid var(--line);
        border-radius: 8px;
        background: var(--surface);
        color: var(--ink);
        font: 500 11px var(--mono);
    }

    .agent-credential-table-wrapper
    {
        width: 100%;
        overflow-x: auto;
        border: 1px solid var(--line);
        border-radius: 11px;
    }

    .agent-credential-table
    {
        width: 100%;
        border-collapse: collapse;
        white-space: nowrap;
    }

    .agent-credential-table th
    {
        padding: 12px 13px;
        background: var(--surface-2);
        color: var(--muted);
        text-align: left;
        font: 600 10px var(--mono);
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .agent-credential-table td
    {
        padding: 13px;
        border-top: 1px solid var(--line);
        color: var(--muted);
        font-size: 11px;
        vertical-align: middle;
    }

    .agent-credential-table td strong
    {
        display: block;
        max-width: 180px;
        overflow: hidden;
        color: var(--ink);
        font: 600 10px var(--mono);
        text-overflow: ellipsis;
    }

    .agent-credential-version
    {
        display: block;
        margin-top: 4px;
        color: var(--muted);
        font-size: 10px;
    }

    .agent-credential-status
    {
        display: inline-flex;
        padding: 4px 8px;
        border: 1px solid var(--line);
        border-radius: 999px;
        background: var(--surface-2);
        color: var(--muted);
        font: 600 10px var(--mono);
        text-transform: capitalize;
    }

    .agent-credential-status.status-active
    {
        border-color: color-mix(in srgb, var(--status-ok) 35%, transparent);
        background: color-mix(in srgb, var(--status-ok) 10%, transparent);
        color: var(--status-ok);
    }

    .agent-credential-status.status-revoked
    {
        border-color: color-mix(in srgb, var(--status-bad) 35%, transparent);
        background: color-mix(in srgb, var(--status-bad) 10%, transparent);
        color: var(--status-bad);
    }

    .agent-credential-revoke-button
    {
        padding: 6px 9px;
        border: 1px solid color-mix(in srgb, var(--status-bad) 35%, transparent);
        border-radius: 7px;
        background: color-mix(in srgb, var(--status-bad) 8%, transparent);
        color: var(--status-bad);
        font: 700 10px var(--font);
        cursor: pointer;
    }

    .agent-credential-empty
    {
        padding: 28px 16px !important;
        text-align: center;
    }

    body.agent-credential-modal-open
    {
        overflow: hidden;
    }

    @media (max-width: 700px)
    {
        .agent-credential-modal.is-open
        {
            padding: 10px;
        }

        .agent-credential-dialog-header,
        .agent-credential-dialog-body
        {
            padding-right: 15px;
            padding-left: 15px;
        }

        .agent-credential-toolbar
        {
            align-items: stretch;
            flex-direction: column;
        }

        #agentCredentialIssueForm
        {
            align-items: stretch;
        }

        .agent-credential-lifetime-label
        {
            width: 100%;
        }

        #agentCredentialLifetime,
        .agent-credential-primary-button
        {
            flex: 1;
        }

        .agent-credential-token-row
        {
            flex-direction: column;
        }
    }

</style>



@endpush
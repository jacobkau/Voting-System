<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include __DIR__ . '/conn.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}
?>

<style>
    /* ─────────────────────────────────────────────
       Page shell
       ───────────────────────────────────────────── */
    .mv-wrap {
        max-width: 1200px;
        margin: 0 auto;
    }

    /* Stats strip */
    .mv-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }

    .mv-stat {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .mv-stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: #e6f4f4;
        color: #2c7a7b;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .mv-stat-info { flex: 1; min-width: 0; }

    .mv-stat-value {
        font-size: 20px;
        font-weight: 800;
        color: #1f2937;
        line-height: 1.2;
    }

    .mv-stat-label {
        font-size: 11px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        margin-top: 2px;
    }

    /* Section title */
    .mv-section-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 14px;
        flex-wrap: wrap;
        gap: 10px;
    }

    .mv-section-title h2 {
        font-size: 18px;
        font-weight: 700;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
    }

    .mv-section-title h2 i { color: #2c7a7b; }

    .mv-live-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        background: #e6f4f4;
        color: #2c7a7b;
        padding: 4px 10px;
        border-radius: 20px;
        font-weight: 600;
    }

    .mv-live-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #10b981;
        animation: mvPulse 2s infinite;
    }

    @keyframes mvPulse {
        0%, 100% { opacity: 1; }
        50%      { opacity: 0.4; }
    }

    /* Election card */
    .mv-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        margin-bottom: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        transition: all 0.2s ease;
    }

    .mv-card:hover { box-shadow: 0 4px 15px rgba(44, 122, 123, 0.08); }

    .mv-card-header {
        padding: 16px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        flex-wrap: wrap;
        background: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
    }

    .mv-card.active .mv-card-header {
        border-bottom-color: #e5e7eb;
    }

    .mv-card-title-block {
        flex: 1;
        min-width: 220px;
    }

    .mv-card-title {
        font-size: 16px;
        font-weight: 700;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 6px;
    }

    .mv-card-meta {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        font-size: 12px;
        color: #6b7280;
    }

    .mv-card-meta span {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .mv-card-meta i { color: #2c7a7b; font-size: 11px; }

    .mv-card-right {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }

    .mv-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        white-space: nowrap;
    }

    .mv-status-active    { background: #d1fae5; color: #065f46; }
    .mv-status-upcoming  { background: #fef3c7; color: #92400e; }
    .mv-status-completed { background: #e5e7eb; color: #374151; }

    .mv-toggle-icon {
        font-size: 14px;
        color: #6b7280;
        transition: transform 0.25s ease;
    }

    .mv-card.active .mv-toggle-icon { transform: rotate(180deg); }

    /* Body */
    .mv-card-body {
        display: none;
        padding: 20px;
    }

    .mv-card.active .mv-card-body { display: block; }

    /* Turnout block */
    .mv-turnout {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 20px;
        padding: 14px;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
    }

    .mv-turnout-cell {
        text-align: center;
    }

    .mv-turnout-cell .num {
        font-size: 20px;
        font-weight: 800;
        color: #2c7a7b;
        line-height: 1.1;
    }

    .mv-turnout-cell .lbl {
        font-size: 11px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        margin-top: 3px;
    }

    /* Turnout progress bar */
    .mv-turnout-bar {
        margin-top: 12px;
        background: #e5e7eb;
        border-radius: 20px;
        height: 10px;
        overflow: hidden;
    }

    .mv-turnout-fill {
        height: 100%;
        background: linear-gradient(90deg, #2c7a7b, #4fd1c5);
        border-radius: 20px;
        transition: width 0.6s ease;
    }

    /* Positions */
    .mv-position {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 12px;
    }

    .mv-position:last-child { margin-bottom: 0; }

    .mv-position-header {
        background: #f3f4f6;
        padding: 11px 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        border-bottom: 1px solid #e5e7eb;
    }

    .mv-position-name {
        font-size: 13px;
        font-weight: 700;
        color: #374151;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .mv-position-name i { color: #2c7a7b; font-size: 12px; }

    .mv-winner-tag {
        font-size: 10px;
        font-weight: 700;
        background: #fef3c7;
        color: #92400e;
        padding: 3px 10px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .mv-winner-tag i { color: #f59e0b; }

    /* Results table */
    .mv-table {
        width: 100%;
        border-collapse: collapse;
    }

    .mv-table th,
    .mv-table td {
        padding: 10px 14px;
        text-align: left;
        font-size: 13px;
        border-bottom: 1px solid #f3f4f6;
    }

    .mv-table tr:last-child td { border-bottom: none; }

    .mv-table tr:hover { background: #f9fafb; }

    .mv-table tr.mv-winner-row {
        background: #ecfdf5;
    }

    .mv-table tr.mv-winner-row:hover {
        background: #d1fae5;
    }

    .mv-table th {
        font-size: 10px;
        font-weight: 700;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: #f9fafb;
    }

    .mv-rank {
        font-weight: 700;
        color: #6b7280;
        font-size: 12px;
    }

    .mv-rank.mv-rank-winner {
        color: #f59e0b;
    }

    .mv-candidate {
        font-weight: 600;
        color: #1f2937;
    }

    .mv-vote-count {
        font-weight: 800;
        color: #2c7a7b;
        font-size: 14px;
    }

    .mv-pct-bar {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .mv-pct-track {
        background: #e5e7eb;
        border-radius: 10px;
        height: 6px;
        flex: 1;
        overflow: hidden;
        min-width: 60px;
    }

    .mv-pct-fill {
        background: #2c7a7b;
        height: 100%;
        border-radius: 10px;
        transition: width 0.6s ease;
    }

    .mv-pct-text {
        font-size: 12px;
        font-weight: 600;
        color: #6b7280;
        min-width: 42px;
        text-align: right;
    }

    /* Actions footer */
    .mv-card-actions {
        margin-top: 18px;
        padding-top: 16px;
        border-top: 1px solid #e5e7eb;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
    }

    .mv-btn-danger {
        background: #dc2626;
        color: white;
        border: none;
        padding: 9px 16px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        font-family: inherit;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        transition: all 0.2s ease;
    }

    .mv-btn-danger:hover {
        background: #b91c1c;
        transform: translateY(-1px);
    }

    /* Empty states */
    .mv-empty {
        text-align: center;
        padding: 60px 20px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        color: #9ca3af;
    }

    .mv-empty i {
        font-size: 48px;
        display: block;
        margin-bottom: 14px;
        color: #d1d5db;
    }

    .mv-no-votes {
        text-align: center;
        padding: 20px;
        color: #9ca3af;
        font-style: italic;
        font-size: 12px;
    }

    /* Reset modal */
    .mv-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.6);
        z-index: 1000;
        padding: 20px;
        overflow-y: auto;
    }

    .mv-modal.open {
        display: flex;
        align-items: flex-start;
        justify-content: center;
    }

    .mv-modal-content {
        background: #ffffff;
        border-radius: 14px;
        width: 100%;
        max-width: 480px;
        margin: 60px 0;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        overflow: hidden;
        animation: mvSlideDown 0.25s ease;
    }

    @keyframes mvSlideDown {
        from { transform: translateY(-24px); opacity: 0; }
        to   { transform: translateY(0); opacity: 1; }
    }

    .mv-modal-header {
        background: #dc2626;
        color: white;
        padding: 16px 22px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .mv-modal-header h3 {
        margin: 0;
        font-size: 15px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .mv-modal-close {
        background: rgba(255, 255, 255, 0.15);
        border: none;
        color: white;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .mv-modal-close:hover { background: rgba(255, 255, 255, 0.3); }

    .mv-modal-body {
        padding: 22px;
    }

    .mv-modal-warning {
        background: #fef2f2;
        color: #991b1b;
        padding: 12px 14px;
        border-radius: 10px;
        font-size: 13px;
        line-height: 1.5;
        margin-bottom: 18px;
        border-left: 3px solid #dc2626;
    }

    .mv-modal-form label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 6px;
    }

    .mv-modal-form input,
    .mv-modal-form textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 13px;
        font-family: inherit;
        margin-bottom: 14px;
        box-sizing: border-box;
        background: #ffffff;
        color: #1f2937;
        -webkit-text-fill-color: #1f2937;
    }

    .mv-modal-form textarea {
        resize: vertical;
        min-height: 70px;
    }

    .mv-modal-form input:focus,
    .mv-modal-form textarea:focus {
        outline: none;
        border-color: #dc2626;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15);
    }

    .mv-modal-confirm-hint {
        font-size: 11px;
        color: #6b7280;
        margin-top: -10px;
        margin-bottom: 14px;
    }

    .mv-modal-confirm-hint code {
        background: #f3f4f6;
        padding: 1px 6px;
        border-radius: 4px;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 11px;
        color: #dc2626;
        font-weight: 600;
    }

    .mv-modal-footer {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 6px;
    }

    .mv-modal-btn {
        padding: 9px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        font-family: inherit;
        border: none;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .mv-modal-btn.cancel {
        background: #f3f4f6;
        color: #4b5563;
    }

    .mv-modal-btn.cancel:hover { background: #e5e7eb; }

    .mv-modal-btn.danger {
        background: #dc2626;
        color: white;
    }

    .mv-modal-btn.danger:hover { background: #b91c1c; }

    .mv-modal-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Toast */
    .mv-toast {
        position: fixed;
        bottom: 24px;
        right: 24px;
        background: #1f2937;
        color: white;
        padding: 12px 18px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 500;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        z-index: 2000;
        animation: mvToastIn 0.3s ease;
        max-width: 340px;
    }

    .mv-toast.success { background: #065f46; }
    .mv-toast.error   { background: #991b1b; }

    @keyframes mvToastIn {
        from { transform: translateX(20px); opacity: 0; }
        to   { transform: translateX(0); opacity: 1; }
    }

    /* Live flash when a number changes */
    @keyframes mvFlash {
        0%   { background: rgba(16, 185, 129, 0.35); }
        100% { background: transparent; }
    }

    .mv-num-updated { animation: mvFlash 1.2s ease; border-radius: 4px; }

    /* Responsive */
    @media (max-width: 900px) {
        .mv-stats { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 600px) {
        .mv-stats { grid-template-columns: 1fr; }
        .mv-turnout { grid-template-columns: 1fr; gap: 8px; }
        .mv-card-header { padding: 14px 16px; }
        .mv-card-body { padding: 16px; }
        .mv-table th,
        .mv-table td { padding: 8px 10px; font-size: 12px; }
        .mv-card-title { font-size: 14px; }
        .mv-section-title { flex-direction: column; align-items: flex-start; }
    }
</style>

<div class="mv-wrap">
    <!-- Stats strip -->
    <div class="mv-stats" id="mvStats">
        <div class="mv-stat">
            <div class="mv-stat-icon"><i class="fas fa-poll"></i></div>
            <div class="mv-stat-info">
                <div class="mv-stat-value" id="mvTotalElections">—</div>
                <div class="mv-stat-label">Total Elections</div>
            </div>
        </div>
        <div class="mv-stat">
            <div class="mv-stat-icon"><i class="fas fa-play"></i></div>
            <div class="mv-stat-info">
                <div class="mv-stat-value" id="mvActiveElections">—</div>
                <div class="mv-stat-label">Active</div>
            </div>
        </div>
        <div class="mv-stat">
            <div class="mv-stat-icon"><i class="fas fa-user-check"></i></div>
            <div class="mv-stat-info">
                <div class="mv-stat-value" id="mvTotalTurnout">—</div>
                <div class="mv-stat-label">Voters Turned Out</div>
            </div>
        </div>
        <div class="mv-stat">
            <div class="mv-stat-icon"><i class="fas fa-vote-yea"></i></div>
            <div class="mv-stat-info">
                <div class="mv-stat-value" id="mvTotalVotes">—</div>
                <div class="mv-stat-label">Total Votes Cast</div>
            </div>
        </div>
    </div>

    <!-- Section title -->
    <div class="mv-section-title">
        <h2><i class="fas fa-chart-bar"></i> Voting Results</h2>
        <span class="mv-live-badge">
            <span class="mv-live-dot"></span> Live
        </span>
    </div>

    <!-- Elections container -->
    <div id="mvElections">
        <div class="mv-empty">
            <i class="fas fa-spinner fa-pulse"></i>
            <p>Loading elections…</p>
        </div>
    </div>
</div>

<!-- Reset modal -->
<div class="mv-modal" id="mvResetModal" onclick="if(event.target === this) closeResetModal()">
    <div class="mv-modal-content" onclick="event.stopPropagation()">
        <div class="mv-modal-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Reset Election</h3>
            <button type="button" class="mv-modal-close" onclick="closeResetModal()" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mv-modal-body">
            <div class="mv-modal-warning">
                <strong>This action cannot be undone.</strong>
                <div style="margin-top: 4px;">
                    All votes for <strong id="mvResetTitle">this election</strong> will be permanently deleted,
                    and all candidate counters will be reset to zero.
                </div>
            </div>

            <form class="mv-modal-form" id="mvResetForm" onsubmit="return submitReset(event)">
                <input type="hidden" id="mvResetElectionId">

                <label for="mvResetConfirm">Type the election title to confirm:</label>
                <input type="text" id="mvResetConfirm" autocomplete="off" required>
                <div class="mv-modal-confirm-hint">
                    Must match exactly: <code id="mvResetExpected">—</code>
                </div>

                <label for="mvResetReason">Reason for reset (logged):</label>
                <textarea id="mvResetReason" required minlength="5" placeholder="e.g., Testing phase complete, starting fresh"></textarea>

                <div class="mv-modal-footer">
                    <button type="button" class="mv-modal-btn cancel" onclick="closeResetModal()">Cancel</button>
                    <button type="submit" class="mv-modal-btn danger" id="mvResetSubmitBtn">
                        <i class="fas fa-trash-alt"></i> Reset Election
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    const POLL_INTERVAL_MS = 15000;
    const RESULTS_API = 'api/election_results_admin.php';
    const RESET_API = 'api/reset_election.php';

    const openCards = new Set();

    // ─────────────────────────────────────────
    // Toast helper
    // ─────────────────────────────────────────
    function showToast(message, type) {
        const t = document.createElement('div');
        t.className = 'mv-toast ' + (type || '');
        t.textContent = message;
        document.body.appendChild(t);
        setTimeout(function() { t.remove(); }, 4000);
    }

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(s)));
        return d.innerHTML;
    }

    function fmtDate(s) {
        if (!s) return '—';
        const d = new Date(s.replace(' ', 'T'));
        if (isNaN(d)) return s;
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }

    // ─────────────────────────────────────────
    // Renderers
    // ─────────────────────────────────────────
    function renderStats(elections) {
        const totalElections = elections.length;
        const activeCount = elections.filter(function(e) { return e.status === 'active'; }).length;
        const totalTurnout = elections.reduce(function(sum, e) { return sum + (e.voted || 0); }, 0);
        const totalVotes = elections.reduce(function(sum, e) { return sum + (e.total_votes_cast || 0); }, 0);

        document.getElementById('mvTotalElections').textContent = totalElections.toLocaleString();
        document.getElementById('mvActiveElections').textContent = activeCount.toLocaleString();
        document.getElementById('mvTotalTurnout').textContent = totalTurnout.toLocaleString();
        document.getElementById('mvTotalVotes').textContent = totalVotes.toLocaleString();
    }

    function renderElection(e) {
        const isOpen = openCards.has(e.id);
        const cardClass = 'mv-card' + (isOpen ? ' active' : '');

        let positionsHtml = '';
        if (!e.positions || e.positions.length === 0) {
            positionsHtml = '<div class="mv-no-votes"><i class="fas fa-info-circle"></i> No positions defined for this election.</div>';
        } else {
            positionsHtml = e.positions.map(function(post) {
                if (!post.candidates || post.candidates.length === 0) {
                    return '<div class="mv-position">' +
                        '<div class="mv-position-header">' +
                            '<span class="mv-position-name"><i class="fas fa-user-tie"></i> ' + escapeHtml(post.postname) + '</span>' +
                        '</div>' +
                        '<div class="mv-no-votes">No approved candidates for this position.</div>' +
                    '</div>';
                }

                const winner = post.candidates[0];

                const rows = post.candidates.map(function(c, idx) {
                    const isWinner = idx === 0 && post.total_votes > 0;
                    return '<tr class="' + (isWinner ? 'mv-winner-row' : '') + '">' +
                        '<td style="width:60px;">' +
                            '<span class="mv-rank' + (isWinner ? ' mv-rank-winner' : '') + '">' +
                                (isWinner ? '<i class="fas fa-crown"></i> ' : '') + '#' + (idx + 1) +
                            '</span>' +
                        '</td>' +
                        '<td class="mv-candidate">' + escapeHtml(c.name) + '</td>' +
                        '<td style="width:80px;">' +
                            '<span class="mv-vote-count" data-candidate-id="' + c.id + '">' + c.votes.toLocaleString() + '</span>' +
                        '</td>' +
                        '<td style="width:200px;">' +
                            '<div class="mv-pct-bar">' +
                                '<div class="mv-pct-track">' +
                                    '<div class="mv-pct-fill" style="width:' + c.percentage + '%;"></div>' +
                                '</div>' +
                                '<span class="mv-pct-text">' + c.percentage + '%</span>' +
                            '</div>' +
                        '</td>' +
                    '</tr>';
                }).join('');

                const winnerTag = post.total_votes > 0 && winner
                    ? '<span class="mv-winner-tag"><i class="fas fa-crown"></i> Winner: ' + escapeHtml(winner.name) + '</span>'
                    : '';

                return '<div class="mv-position">' +
                    '<div class="mv-position-header">' +
                        '<span class="mv-position-name"><i class="fas fa-user-tie"></i> ' + escapeHtml(post.postname) + '</span>' +
                        winnerTag +
                    '</div>' +
                    '<table class="mv-table">' +
                        '<thead>' +
                            '<tr>' +
                                '<th>Rank</th>' +
                                '<th>Candidate</th>' +
                                '<th>Votes</th>' +
                                '<th>Percentage</th>' +
                            '</tr>' +
                        '</thead>' +
                        '<tbody>' + rows + '</tbody>' +
                    '</table>' +
                '</div>';
            }).join('');
        }

        const turnoutHtml =
            '<div class="mv-turnout">' +
                '<div class="mv-turnout-cell">' +
                    '<div class="num">' + (e.registered || 0).toLocaleString() + '</div>' +
                    '<div class="lbl">Registered</div>' +
                '</div>' +
                '<div class="mv-turnout-cell">' +
                    '<div class="num">' + (e.voted || 0).toLocaleString() + '</div>' +
                    '<div class="lbl">Voted</div>' +
                '</div>' +
                '<div class="mv-turnout-cell">' +
                    '<div class="num">' + e.turnout_pct + '%</div>' +
                    '<div class="lbl">Turnout</div>' +
                '</div>' +
            '</div>' +
            '<div class="mv-turnout-bar">' +
                '<div class="mv-turnout-fill" style="width:' + Math.min(e.turnout_pct, 100) + '%;"></div>' +
            '</div>';

        return '<div class="' + cardClass + '" data-election-id="' + e.id + '">' +
            '<div class="mv-card-header" onclick="toggleCard(' + e.id + ')">' +
                '<div class="mv-card-title-block">' +
                    '<div class="mv-card-title">' +
                        '<i class="fas fa-poll" style="color:#2c7a7b;"></i> ' + escapeHtml(e.title) +
                        '<span class="mv-status-pill mv-status-' + escapeHtml(e.status) + '">' + escapeHtml(e.status) + '</span>' +
                    '</div>' +
                    '<div class="mv-card-meta">' +
                        '<span><i class="far fa-calendar-alt"></i> ' + fmtDate(e.start_date) + '</span>' +
                        '<span><i class="far fa-calendar-check"></i> ' + fmtDate(e.end_date) + '</span>' +
                    '</div>' +
                '</div>' +
                '<div class="mv-card-right">' +
                    '<i class="fas fa-chevron-down mv-toggle-icon"></i>' +
                '</div>' +
            '</div>' +
            '<div class="mv-card-body">' +
                turnoutHtml +
                '<div style="margin-top:20px;">' + positionsHtml + '</div>' +
                '<div class="mv-card-actions">' +
                    '<button type="button" class="mv-btn-danger" onclick="event.stopPropagation(); openResetModal(' + e.id + ', \'' + escapeHtml(e.title.replace(/'/g, "\\'")) + '\')">' +
                        '<i class="fas fa-sync-alt"></i> Reset Election' +
                    '</button>' +
                '</div>' +
            '</div>' +
        '</div>';
    }

    function render(elections) {
        renderStats(elections);

        const container = document.getElementById('mvElections');

        if (!elections || elections.length === 0) {
            container.innerHTML =
                '<div class="mv-empty">' +
                    '<i class="fas fa-vote-yea"></i>' +
                    '<p>No elections found. Please create one first.</p>' +
                '</div>';
            return;
        }

        container.innerHTML = elections.map(renderElection).join('');
    }

    // ─────────────────────────────────────────
    // Toggle card
    // ─────────────────────────────────────────
    window.toggleCard = function(id) {
        const card = document.querySelector('.mv-card[data-election-id="' + id + '"]');
        if (!card) return;

        if (card.classList.contains('active')) {
            card.classList.remove('active');
            openCards.delete(id);
        } else {
            card.classList.add('active');
            openCards.add(id);
        }
    };

    // ─────────────────────────────────────────
    // Live polling
    // ─────────────────────────────────────────
    async function poll() {
        if (document.visibilityState === 'hidden') return;

        try {
            const res = await fetch(RESULTS_API, {
                credentials: 'same-origin',
                cache: 'no-store'
            });
            if (!res.ok) return;

            const data = await res.json();
            if (!data.ok || !Array.isArray(data.elections)) return;

            const serverIds = data.elections.map(function(e) { return String(e.id); }).sort().join(',');
            const renderedIds = Array.from(document.querySelectorAll('.mv-card'))
                .map(function(el) { return el.dataset.electionId; }).sort().join(',');

            if (serverIds !== renderedIds) {
                render(data.elections);
                return;
            }

            data.elections.forEach(function(e) {
                const card = document.querySelector('.mv-card[data-election-id="' + e.id + '"]');
                if (!card) return;

                const oldBody = card.querySelector('.mv-card-body');
                if (!oldBody) return;

                const cells = oldBody.querySelectorAll('.mv-turnout-cell .num');
                if (cells.length >= 3) {
                    const oldReg = parseInt(cells[0].textContent.replace(/,/g, ''), 10) || 0;
                    const oldVoted = parseInt(cells[1].textContent.replace(/,/g, ''), 10) || 0;

                    if (oldReg !== e.registered || oldVoted !== e.voted) {
                        const isOpen = card.classList.contains('active');
                        const fresh = document.createElement('div');
                        fresh.innerHTML = renderElection(e);
                        const freshCard = fresh.firstElementChild;

                        if (isOpen) freshCard.classList.add('active');
                        card.replaceWith(freshCard);
                    }
                }
            });

        } catch (err) {
            // Silent — retry on next tick
        }
    }

    // ─────────────────────────────────────────
    // Reset modal
    // ─────────────────────────────────────────
    window.openResetModal = function(electionId, title) {
        document.getElementById('mvResetElectionId').value = electionId;
        document.getElementById('mvResetTitle').textContent = title;
        document.getElementById('mvResetExpected').textContent = title;
        document.getElementById('mvResetConfirm').value = '';
        document.getElementById('mvResetReason').value = '';
        document.getElementById('mvResetModal').classList.add('open');
    };

    window.closeResetModal = function() {
        document.getElementById('mvResetModal').classList.remove('open');
    };

    window.submitReset = function(ev) {
        ev.preventDefault();

        const electionId = document.getElementById('mvResetElectionId').value;
        const confirmText = document.getElementById('mvResetConfirm').value.trim();
        const reason = document.getElementById('mvResetReason').value.trim();
        const expected = document.getElementById('mvResetExpected').textContent;

        if (confirmText !== expected) {
            showToast('Confirmation text does not match.', 'error');
            return false;
        }
        if (reason.length < 5) {
            showToast('Please provide a reason (min 5 characters).', 'error');
            return false;
        }

        const btn = document.getElementById('mvResetSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Resetting…';

        const formData = new FormData();
        formData.append('election_id', electionId);
        formData.append('confirm_text', confirmText);
        formData.append('reason', reason);

        fetch(RESET_API, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-trash-alt"></i> Reset Election';

            if (data.success) {
                showToast(data.message || 'Election reset successfully.', 'success');
                closeResetModal();
                poll();
            } else {
                showToast(data.message || 'Reset failed.', 'error');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-trash-alt"></i> Reset Election';
            showToast('Network error. Please try again.', 'error');
        });

        return false;
    };

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeResetModal();
    });

    // ─────────────────────────────────────────
    // Boot
    // ─────────────────────────────────────────
    poll();
    setInterval(poll, POLL_INTERVAL_MS);

    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') poll();
    });
})();
</script>

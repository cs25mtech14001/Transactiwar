<?php
# Atish Kadam - CS25MTECH14003
# Akarsh Dubey - CS25MTECH14001
# Atharva Kale - CS25MTECH11024
# Prashant Kumar Dubey - CS25MTECH14011
# Debdip Choudhuri - CS25MTECH11025
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// make sure user is logged in before anything else
require_login();

// grab the logged in user's data
$user = get_logged_in_user();
$user_id  = $user['id'];
$username = $user['username'];
$balance  = $user['balance'];

// log that this user visited the history page
log_activity('history.php', $username);

// all dates should show in Indian time
date_default_timezone_set('Asia/Kolkata');

// get db connection
$pdo = get_db();

// read url params, default to sensible values
$filter    = $_GET['filter']    ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to']   ?? '';
$page      = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 15;

// -------- TYPE VALIDATION --------

// Ensure all are strings
if (!is_string($filter)) {
    $filter = 'all';
}

if (!is_string($date_from)) {
    $date_from = '';
}

if (!is_string($date_to)) {
    $date_to = '';
}

// -------- SANITIZE --------

$filter    = trim($filter);
$date_from = trim($date_from);
$date_to   = trim($date_to);

// -------- VALIDATION --------

// Optional: restrict filter values
$allowed_filters = ['all', 'sent', 'received'];
if (!in_array($filter, $allowed_filters, true)) {
    $filter = 'all';
}

// Validate date format (YYYY-MM-DD)
$date_from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) ? $date_from : '';
$date_to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)   ? $date_to   : '';

//base query — show only transactions involving this user
$where  = "WHERE (t.sender_id = :uid1 OR t.receiver_id = :uid2)";
$params = [':uid1' => $user_id, ':uid2' => $user_id];

if ($date_from !== '') {
    $where .= " AND CONVERT_TZ(t.created_at, '+00:00', '+05:30') >= :date_from";
    $params[':date_from'] = $date_from . ' 00:00:00';
}
if ($date_to !== '') {
    $where .= " AND CONVERT_TZ(t.created_at, '+00:00', '+05:30') <= :date_to";
    $params[':date_to'] = $date_to . ' 23:59:59';
}

// need total count first to figure out how many pages
$count_sql  = "SELECT COUNT(*) FROM transactions t $where";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_rows  = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page        = min($page, $total_pages); // clamp to valid range
$offset      = ($page - 1) * $per_page;

// fetch just the rows for this page
$sql = "
    SELECT
        t.id,
        t.amount,
        t.comment,
        t.created_at,
        t.sender_id,
        t.receiver_id,
        s.username AS sender_username,
        r.username AS receiver_username
    FROM transactions t
    JOIN users s ON t.sender_id  = s.id
    JOIN users r ON t.receiver_id = r.id
    $where
    ORDER BY t.created_at DESC
    LIMIT $per_page OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// fetch all matching transactions for totals, not just current page
$stats_sql  = "SELECT sender_id, receiver_id, amount FROM transactions t $where";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute($params);
$all_txns_for_stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);

// calculate sent/received totals
$total_sent = $total_received = $count_sent = $count_received = 0;
foreach ($all_txns_for_stats as $tx) {
    if ($tx['sender_id'] == $user_id) {
        $total_sent += $tx['amount'];
        $count_sent++;
    } else {
        $total_received += $tx['amount'];
        $count_received++;
    }
}

// work out what the balance was after each transaction
// list is newest-first so we walk backwards from current balance
$running_balances = [];
$running = $balance;
foreach ($transactions as $tx) {
    $running_balances[$tx['id']] = $running;
    if ($tx['sender_id'] == $user_id) {
        $running += $tx['amount']; // undo sent: balance was higher before
    } else {
        $running -= $tx['amount']; // undo received: balance was lower before
    }
}

function esc(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transaction History – TransactiWar</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#0d1117;color:#e6edf3;min-height:100vh}

/* NAVBAR */
.navbar{background:#161b22;border-bottom:1px solid #30363d;padding:14px 32px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.hamburger{display:none}
.brand{font-size:1.3rem;font-weight:700;color:#e6edf3;text-decoration:none}
.btn{padding:7px 15px;border-radius:8px;font-size:.85rem;font-weight:500;text-decoration:none;border:1px solid #30363d;color:#e6edf3;background:transparent;cursor:pointer;transition:background .2s,border-color .2s;display:inline-block}
.btn:hover{background:#21262d;border-color:#58a6ff}
.btn-purple{background:#5865f2;border-color:#5865f2;color:#fff}
.btn-purple:hover{background:#4752c4;border-color:#4752c4}
.btn-danger{border-color:#f85149;color:#f85149}
.btn-danger:hover{background:rgba(248,81,73,.1)}

/* LAYOUT */
.wrap{max-width:880px;margin:36px auto;padding:0 20px}
.page-title{font-size:1.6rem;font-weight:700;margin-bottom:22px}
.page-title span{color:#58a6ff}

/* BALANCE CARD */
.balance-card{background:#161b22;border:1px solid #30363d;border-radius:12px;padding:20px 26px;margin-bottom:22px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.bal-label{font-size:.78rem;color:#8b949e;margin-bottom:4px}
.bal-amount{font-size:2rem;font-weight:700;color:#3fb950}

/* STATS */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:22px}
.stat{background:#161b22;border:1px solid #30363d;border-radius:10px;padding:14px 16px;text-align:center}
.stat-label{font-size:.68rem;color:#8b949e;text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px}
.stat-val{font-size:1.25rem;font-weight:700}
.c-blue{color:#58a6ff}.c-red{color:#f85149}.c-green{color:#3fb950}

/* FILTERS */
.filters{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
.ftab{padding:7px 18px;border-radius:20px;font-size:.82rem;font-weight:500;text-decoration:none;border:1px solid #30363d;color:#8b949e;background:transparent;transition:all .2s}
.ftab:hover{color:#e6edf3;background:#21262d}
.ftab.fa{border-color:#58a6ff;color:#58a6ff;background:rgba(88,166,255,.08)}
.ftab.fs{border-color:#f85149;color:#f85149;background:rgba(248,81,73,.08)}
.ftab.fr{border-color:#3fb950;color:#3fb950;background:rgba(63,185,80,.08)}

/* SEARCH */
.search-wrap{position:relative;margin-bottom:20px}
.search-wrap input{width:100%;background:#161b22;border:1px solid #30363d;border-radius:8px;color:#e6edf3;padding:10px 14px 10px 40px;font-size:.9rem;outline:none;transition:border-color .2s}
.search-wrap input:focus{border-color:#58a6ff}
.search-wrap input::placeholder{color:#8b949e}
.s-icon{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#8b949e;pointer-events:none}

/* DATE RANGE PICKER */
.date-range-wrap{
    background:#161b22;border:1px solid #30363d;border-radius:10px;
    padding:14px 18px;margin-bottom:16px;
    display:flex;align-items:flex-end;gap:14px;flex-wrap:wrap;
}
.date-range-wrap label{font-size:.73rem;color:#8b949e;text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:5px}
.date-range-wrap input[type="date"]{
    background:#0d1117;border:1px solid #30363d;border-radius:7px;
    color:#e6edf3;padding:8px 12px;font-size:.88rem;outline:none;
    transition:border-color .2s;
    color-scheme:dark;
}
.date-range-wrap input[type="date"]:focus{border-color:#58a6ff}
.drp-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.btn-apply{padding:8px 18px;border-radius:7px;font-size:.85rem;font-weight:600;background:#5865f2;border:none;color:#fff;cursor:pointer;transition:background .2s}
.btn-apply:hover{background:#4752c4}
.btn-clear{padding:8px 14px;border-radius:7px;font-size:.85rem;font-weight:500;background:transparent;border:1px solid #30363d;color:#8b949e;text-decoration:none;transition:all .2s;display:inline-block}
.btn-clear:hover{border-color:#f85149;color:#f85149}
.active-range-badge{
    display:inline-flex;align-items:center;gap:6px;
    background:rgba(88,166,255,.1);border:1px solid rgba(88,166,255,.3);
    border-radius:20px;padding:4px 12px;font-size:.78rem;color:#58a6ff;
}

/* PAGINATION */
.pagination{
    display:flex;align-items:center;justify-content:space-between;
    flex-wrap:wrap;gap:12px;
    background:#161b22;border:1px solid #30363d;border-radius:10px;
    padding:14px 20px;margin-top:20px;
}
.pag-info{font-size:.82rem;color:#8b949e}
.pag-info strong{color:#c9d1d9}
.pag-controls{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.pag-btn{
    padding:7px 13px;border-radius:7px;font-size:.82rem;font-weight:500;
    text-decoration:none;border:1px solid #30363d;color:#e6edf3;
    background:transparent;transition:all .2s;display:inline-block;
}
.pag-btn:hover:not(.disabled):not(.active){background:#21262d;border-color:#58a6ff}
.pag-btn.active{background:#5865f2;border-color:#5865f2;color:#fff;pointer-events:none}
.pag-btn.disabled{color:#484f58;border-color:#21262d;pointer-events:none;cursor:default}
.pag-jump{display:flex;align-items:center;gap:6px;font-size:.82rem;color:#8b949e}
.pag-jump input{
    width:52px;background:#0d1117;border:1px solid #30363d;border-radius:6px;
    color:#e6edf3;padding:5px 8px;font-size:.82rem;text-align:center;outline:none;
}
.pag-jump input:focus{border-color:#58a6ff}
.pag-jump button{
    padding:5px 10px;border-radius:6px;font-size:.8rem;
    background:#21262d;border:1px solid #30363d;color:#e6edf3;cursor:pointer;
}
.pag-jump button:hover{border-color:#58a6ff}

/* DATE DIVIDER */
.date-divider{text-align:center;position:relative;margin:16px 0 8px}
.date-divider::before{content:'';position:absolute;top:50%;left:0;right:0;height:1px;background:#21262d}
.date-divider span{position:relative;background:#0d1117;padding:0 14px;font-size:.72rem;color:#8b949e;font-weight:600;text-transform:uppercase;letter-spacing:.07em}

/* TX CARD */
.tx-list{display:flex;flex-direction:column;gap:10px}
.tx-card{
    background:#161b22;border:1px solid #30363d;border-radius:12px;
    padding:16px 20px;
    display:grid;grid-template-columns:52px 1fr auto;
    align-items:center;gap:16px;
    transition:background .15s;
    border-left:3px solid transparent;
}
.tx-card:hover{background:#1c2128}
.tx-card.sent    {border-left-color:#f85149}
.tx-card.received{border-left-color:#3fb950}

/* Icon */
.tx-icon{width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0}
.icon-sent    {background:rgba(248,81,73,.1);border:1.5px solid rgba(248,81,73,.25)}
.icon-received{background:rgba(63,185,80,.1);border:1.5px solid rgba(63,185,80,.25)}

/* Info */
.tx-type-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-bottom:3px}
.lbl-sent    {color:#f85149}
.lbl-received{color:#3fb950}
.tx-who{font-size:.97rem;font-weight:600;color:#e6edf3;display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:6px}
.tx-who a{color:#58a6ff;text-decoration:none}
.tx-who a:hover{text-decoration:underline}
.tx-who .sub{color:#8b949e;font-weight:400;font-size:.88rem}

/* Date pill */
.tx-datetime{display:inline-flex;align-items:center;gap:6px;background:#21262d;border:1px solid #30363d;border-radius:20px;padding:3px 10px;font-size:.75rem;color:#8b949e}
.tx-datetime .date-part{color:#c9d1d9;font-weight:500}
.tx-datetime .sep{color:#484f58}

/* Comment */
.tx-comment{display:inline-flex;align-items:center;gap:5px;margin-left:6px;background:rgba(88,166,255,.07);border:1px solid rgba(88,166,255,.15);border-radius:20px;padding:3px 10px;font-size:.73rem;color:#8b949e;font-style:italic;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:middle}

/* Amount */
.tx-amount-wrap{text-align:right;flex-shrink:0}
.tx-amount{font-size:1.35rem;font-weight:800;letter-spacing:-.01em}
.amt-sent    {color:#f85149;text-shadow:0 0 16px rgba(248,81,73,.35)}
.amt-received{color:#3fb950;text-shadow:0 0 16px rgba(63,185,80,.35)}

/* Running balance */
.tx-running-bal{
    display:inline-flex;align-items:center;gap:4px;
    margin-top:5px;
    background:#0d1117;border:1px solid #30363d;
    border-radius:6px;padding:3px 9px;
    font-size:.72rem;color:#8b949e;white-space:nowrap;
}
.tx-running-bal .rbal-label{color:#484f58;font-size:.68rem}
.tx-running-bal .rbal-val{color:#c9d1d9;font-weight:600}

/* Empty */

.empty-state{text-align:center;padding:40px 20px;display:flex;flex-direction:column;align-items:center;gap:10px}
.empty-state .es-icon{font-size:2.8rem}
.empty-state .es-title{font-size:.9rem;font-weight:600;color:#e2e8f0}
.empty-state .es-sub{font-family:'Space Mono',monospace;font-size:.68rem;color:#64748b}
.empty-state .es-btn{margin-top:6px;padding:8px 20px;background:#5865f2;border:none;border-radius:8px;color:#fff;font-size:.82rem;font-weight:600;text-decoration:none;display:inline-block}
.empty{text-align:center;padding:60px 20px;color:#8b949e}
.empty .e-icon{font-size:3rem;margin-bottom:12px}

@media(max-width:580px){
    .tx-card{grid-template-columns:46px 1fr;grid-template-rows:1fr auto}
    .tx-amount-wrap{grid-column:2;text-align:left;margin-top:4px}
    .navbar{padding:12px 16px}
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <a href="dashboard.php" style="font-size:1.1rem;font-weight:700;color:#e6edf3;text-decoration:none">💸 TransactiWar</a>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
        <a href="dashboard.php" class="btn">Dashboard</a>
        <a href="transfer.php"  class="btn">Transfer</a>
        <a href="history.php"   class="btn btn-purple">History</a>
        <a href="profile.php"   class="btn">Profile</a>
        <a href="logout.php"    class="btn btn-danger">Logout</a>
    </div>
</nav>

<div class="wrap">

    <h1 class="page-title">📋 Transaction <span>History</span></h1>

    <!-- BALANCE -->
    <div class="balance-card">
        <div>
            <div class="bal-label">Current Balance</div>
            <div class="bal-amount">₹<?php echo number_format($balance, 2); ?></div>
        </div>
        <a href="transfer.php" class="btn btn-purple" style="padding:10px 22px">💸 New Transfer</a>
    </div>

    <!-- STATS -->
    <div class="stats">
        <div class="stat">
            <div class="stat-label">Total Txns</div>
            <div class="stat-val c-blue"><?php echo count($transactions); ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Total Sent</div>
            <div class="stat-val c-red">₹<?php echo number_format($total_sent, 2); ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Total Received</div>
            <div class="stat-val c-green">₹<?php echo number_format($total_received, 2); ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Sent Count</div>
            <div class="stat-val c-red"><?php echo $count_sent; ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Received Count</div>
            <div class="stat-val c-green"><?php echo $count_received; ?></div>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="filters">
        <?php
        $dr = ($date_from ? '&date_from='.esc($date_from) : '') . ($date_to ? '&date_to='.esc($date_to) : '');
        ?>
        <a href="?filter=all<?php echo $dr; ?>"      class="ftab <?php echo $filter==='all'      ? 'fa' : ''; ?>">🔁 All</a>
        <a href="?filter=sent<?php echo $dr; ?>"     class="ftab <?php echo $filter==='sent'     ? 'fs' : ''; ?>">📤 Sent</a>
        <a href="?filter=received<?php echo $dr; ?>" class="ftab <?php echo $filter==='received' ? 'fr' : ''; ?>">📥 Received</a>
    </div>

    <!-- DATE RANGE FILTER -->
    <div class="date-range-wrap">
        <div>
            <label>From Date</label>
            <input type="date" id="dateFrom" value="<?php echo esc($date_from); ?>"
                   max="<?php echo date('Y-m-d'); ?>">
        </div>
        <div>
            <label>To Date</label>
            <input type="date" id="dateTo" value="<?php echo esc($date_to); ?>"
                   max="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="drp-actions">
            <button class="btn-apply" onclick="applyDateRange()">Apply</button>
            <?php if ($date_from || $date_to): ?>
                <a href="?filter=<?php echo esc($filter); ?>" class="btn-clear">✕ Clear</a>
                <span class="active-range-badge">
                    📅 <?php echo $date_from ?: '…'; ?> → <?php echo $date_to ?: '…'; ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- SEARCH -->
    <div class="search-wrap">
        <span class="s-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Search by username or comment…">
    </div>

    <!-- CARDS -->
    <div class="tx-list" id="txList">
    <?php
    $shown     = 0;
    $last_date = '';

    foreach ($transactions as $tx):
        $is_sent = ((int)$tx['sender_id'] === (int)$user_id);

        if ($filter === 'sent'     && !$is_sent) continue;
        if ($filter === 'received' &&  $is_sent) continue;

        $shown++;

        $counterpart    = $is_sent ? $tx['receiver_username'] : $tx['sender_username'];
        $counterpart_id = $is_sent ? (int)$tx['receiver_id']  : (int)$tx['sender_id'];

        $dt = new DateTime($tx['created_at'], new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
        $date_str = $dt->format('d M Y');
        $time_str = $dt->format('h:i A');

        $today     = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('d M Y');
        $yesterday = (new DateTime('yesterday', new DateTimeZone('Asia/Kolkata')))->format('d M Y');
        $display_date = $date_str === $today
            ? 'Today'
            : ($date_str === $yesterday ? 'Yesterday' : $date_str);

        if ($date_str !== $last_date):
            $last_date = $date_str;
    ?>
        <div class="date-divider">
            <span><?php echo esc($display_date); ?></span>
        </div>
    <?php endif; ?>

        <div class="tx-card <?php echo $is_sent ? 'sent' : 'received'; ?>"
             data-who="<?php echo strtolower(esc($counterpart)); ?>"
             data-comment="<?php echo strtolower(esc($tx['comment'] ?? '')); ?>">

            <!-- ICON -->
            <div class="tx-icon <?php echo $is_sent ? 'icon-sent' : 'icon-received'; ?>">
                <?php echo $is_sent ? '📤' : '📥'; ?>
            </div>

            <!-- INFO -->
            <div class="tx-info">
                <div class="tx-type-label <?php echo $is_sent ? 'lbl-sent' : 'lbl-received'; ?>">
                    <?php echo $is_sent ? '▲ Money Sent' : '▼ Money Received'; ?>
                </div>

                <div class="tx-who">
                    <span class="sub"><?php echo $is_sent ? 'You sent to' : 'Received from'; ?></span>
                        @<?php echo esc($counterpart); ?>
                </div>

                <div style="display:flex;align-items:center;flex-wrap:wrap;gap:6px">
                    <span class="tx-datetime">
                        🗓 <span class="date-part"><?php echo $date_str; ?></span>
                        <span class="sep">·</span>
                        🕐 <span><?php echo $time_str; ?></span>
                    </span>
                    <?php if (!empty($tx['comment'])): ?>
                        <span class="tx-comment" title="<?php echo esc($tx['comment']); ?>">
                            💬 <?php echo esc($tx['comment']); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- AMOUNT -->
            <div class="tx-amount-wrap">
                <div class="tx-amount <?php echo $is_sent ? 'amt-sent' : 'amt-received'; ?>">
                    <?php echo $is_sent ? '−' : '+'; ?> ₹<?php echo number_format($tx['amount'], 2); ?>
                </div>
                <div class="tx-running-bal">
                    <span class="rbal-label">Balance after:</span>
                    <span class="rbal-val">₹<?php echo number_format($running_balances[$tx['id']], 2); ?></span>
                </div>
            </div>

        </div>

    <?php endforeach; ?>

    <?php if ($shown === 0): ?>
        <div class="empty-state">
            <div class="es-icon">📭</div>
            <div class="es-title">No transactions found</div>
            <div class="es-sub">// try adjusting your filters or date range</div>
            <a href="history.php" class="es-btn">Clear Filters</a>
        </div>
    <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <?php
        // Build base URL preserving all current params except page
        $base_params = array_filter([
            'filter'    => $filter !== 'all' ? $filter : null,
            'date_from' => $date_from ?: null,
            'date_to'   => $date_to   ?: null,
        ]);
        function pag_url(int $p, array $base): string {
            $q = array_merge($base, ['page' => $p]);
            return 'history.php?' . http_build_query($q);
        }
        // Pages to show: always first, last, and 2 around current
        $pages_to_show = array_unique(array_filter([
            1,
            $page - 2, $page - 1, $page, $page + 1, $page + 2,
            $total_pages
        ], fn($p) => $p >= 1 && $p <= $total_pages));
        sort($pages_to_show);
    ?>
    <div class="pagination">
        <!-- Info -->
        <div class="pag-info">
            Showing <strong><?php echo number_format($offset + 1); ?>–<?php echo number_format(min($offset + $per_page, $total_rows)); ?></strong>
            of <strong><?php echo number_format($total_rows); ?></strong> transactions
            &nbsp;·&nbsp; Page <strong><?php echo $page; ?></strong> of <strong><?php echo $total_pages; ?></strong>
        </div>

        <!-- Controls -->
        <div class="pag-controls">
            <!-- Prev -->
            <?php if ($page > 1): ?>
                <a href="<?php echo esc(pag_url($page - 1, $base_params)); ?>" class="pag-btn">← Prev</a>
            <?php else: ?>
                <span class="pag-btn disabled">← Prev</span>
            <?php endif; ?>

            <!-- Page numbers -->
            <?php
            $prev_shown = null;
            foreach ($pages_to_show as $p):
                if ($prev_shown !== null && $p - $prev_shown > 1): ?>
                    <span class="pag-btn disabled">…</span>
                <?php endif; ?>
                <a href="<?php echo esc(pag_url($p, $base_params)); ?>"
                   class="pag-btn <?php echo $p === $page ? 'active' : ''; ?>">
                    <?php echo $p; ?>
                </a>
            <?php
                $prev_shown = $p;
            endforeach; ?>

            <!-- Next -->
            <?php if ($page < $total_pages): ?>
                <a href="<?php echo esc(pag_url($page + 1, $base_params)); ?>" class="pag-btn">Next →</a>
            <?php else: ?>
                <span class="pag-btn disabled">Next →</span>
            <?php endif; ?>

            <!-- Jump to page -->
            <div class="pag-jump">
                Go to
                <input type="number" id="jumpPage" min="1" max="<?php echo $total_pages; ?>"
                       placeholder="<?php echo $page; ?>">
                <button onclick="jumpToPage()">Go</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
// Live search
const searchInput = document.getElementById('searchInput');
searchInput.addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('.tx-card').forEach(card => {
        const match = !q || card.dataset.who.includes(q) || card.dataset.comment.includes(q);
        card.style.display = match ? '' : 'none';
    });
    refreshDividers();
});

// Refresh date divider visibility
function refreshDividers() {
    document.querySelectorAll('.date-divider').forEach(div => {
        let next = div.nextElementSibling;
        let visible = false;
        while (next && !next.classList.contains('date-divider')) {
            if (next.classList.contains('tx-card') && next.style.display !== 'none') { visible = true; break; }
            next = next.nextElementSibling;
        }
        div.style.display = visible ? '' : 'none';
    });
}

// Apply date range — submits as GET params preserving current filter
function applyDateRange() {
    const from   = document.getElementById('dateFrom').value;
    const to     = document.getElementById('dateTo').value;
    const filter = new URLSearchParams(window.location.search).get('filter') || 'all';

    if (from && to && from > to) {
        alert('"From" date cannot be after "To" date.');
        return;
    }

    const params = new URLSearchParams({ filter });
    if (from) params.set('date_from', from);
    if (to)   params.set('date_to',   to);
    window.location.href = 'history.php?' + params.toString();
}

// Allow pressing Enter in date inputs to apply
document.getElementById('dateFrom').addEventListener('keydown', e => { if(e.key==='Enter') applyDateRange(); });
document.getElementById('dateTo').addEventListener('keydown',   e => { if(e.key==='Enter') applyDateRange(); });

// Jump to page
function jumpToPage() {
    const input     = document.getElementById('jumpPage');
    const p         = parseInt(input.value);
    const total     = <?php echo $total_pages; ?>;
    if (!p || p < 1 || p > total) { input.style.borderColor = '#f85149'; return; }
    const params    = new URLSearchParams(window.location.search);
    params.set('page', p);
    window.location.href = 'history.php?' + params.toString();
}
document.getElementById('jumpPage')?.addEventListener('keydown', e => { if(e.key==='Enter') jumpToPage(); });


// Page fade-out on navigation
document.querySelectorAll('a[href]').forEach(a => {
    const href = a.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('javascript') || href.startsWith('mailto')) return;
    a.addEventListener('click', function(e) {
        // Don't intercept logout or external links
        if (a.classList.contains('danger')) return;
        e.preventDefault();
        document.body.classList.add('navigating');
        setTimeout(() => { window.location.href = href; }, 150);
    });
});
</script>
</body>
</html>

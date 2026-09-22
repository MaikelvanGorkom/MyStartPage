<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/auth.php';

function EscapeHtml(mixed $Value): string
{
    return htmlspecialchars((string)($Value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$Tabs = [];
$HeadersByTab = [];
$HeaderCellsByTab = [];
$ErrorMessage = null;
$SelectedTabId = filter_var($_GET['tab'] ?? null, FILTER_VALIDATE_INT);
$IsAdminLoggedIn = IsAdminLoggedIn();
$TabVisibilityCondition = $IsAdminLoggedIn ? '1 = 1' : '(hidefrompublic IS NULL OR hidefrompublic = 0)';
$HeaderVisibilityCondition = $IsAdminLoggedIn ? '1 = 1' : '(t.hidefrompublic IS NULL OR t.hidefrompublic = 0)';

try {
    $DatabaseConnection = CreateDatabaseConnection();

        $TabStatement = $DatabaseConnection->query(
            "SELECT idtab, tabname
         FROM tab
         WHERE {$TabVisibilityCondition}
         ORDER BY hidefrompublic ASC, tabname ASC"
    );
    $Tabs = $TabStatement->fetchAll();

    $HeaderStatement = $DatabaseConnection->query(
          "SELECT
            h.idheader,
            h.tabid,
            h.headername,
            h.`column`,
            h.`row`,
            l.idlinks,
            l.pagename,
            l.link,
            l.favicon
         FROM header h
         LEFT JOIN links l ON l.headerid = h.idheader
         INNER JOIN tab t ON t.idtab = h.tabid
         WHERE {$HeaderVisibilityCondition}
          ORDER BY h.tabid, h.`row`, h.`column`, h.headername, h.idheader, l.link, l.idlinks"
    );

    foreach ($HeaderStatement->fetchAll() as $Row) {
        $TabId = (int)$Row['tabid'];
        $HeaderId = (int)$Row['idheader'];
        if (!isset($HeadersByTab[$TabId][$HeaderId])) {
            $HeadersByTab[$TabId][$HeaderId] = [
                'id' => $HeaderId,
                'name' => $Row['headername'],
                'column' => min(4, max(1, (int)$Row['column'])),
                'row' => max(1, (int)$Row['row']),
                'links' => [],
            ];
        }
        if ($Row['idlinks'] !== null) {
            $HeadersByTab[$TabId][$HeaderId]['links'][] = [
                'id' => (int)$Row['idlinks'],
                'name' => $Row['pagename'],
                'url' => $Row['link'],
                'favicon' => $Row['favicon'],
            ];
        }
    }

    foreach ($HeadersByTab as $TabId => $Headers) {
        foreach ($Headers as $Header) {
            $CellKey = $Header['row'] . '-' . $Header['column'];
            $HeaderCellsByTab[$TabId][$CellKey]['column'] = $Header['column'];
            $HeaderCellsByTab[$TabId][$CellKey]['row'] = $Header['row'];
            $HeaderCellsByTab[$TabId][$CellKey]['headers'][] = $Header;
        }
    }

    $TabIds = array_map(static fn (array $Tab): int => (int)$Tab['idtab'], $Tabs);
    if ($SelectedTabId === false || !in_array($SelectedTabId, $TabIds, true)) {
        $SelectedTabId = isset($Tabs[0]) ? (int)$Tabs[0]['idtab'] : null;
    }
} catch (Throwable $Exception) {
    $ErrorMessage = 'The start page is temporarily unavailable.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MyStartPage</title>
    <link rel="stylesheet" href="config/lookandfeel.css">
    <style>
        body.focus-mode nav.tabs a:not(.active) { display: none; }
    </style>
</head>
<body class="public-page<?= $IsAdminLoggedIn ? ' admin-logged-in' : '' ?>">
<header><div class="brand"><h1>MyStartPage</h1><span>Personal dashboard</span></div>
<div class="account"><?php if ($IsAdminLoggedIn): ?><label style="display: inline-flex; align-items: center; gap: 8px; margin-right: 16px;"><input type="checkbox" id="disableLogoutTimeout"> Disable auto-logout</label><span id="logoutTimer" style="display: inline-block; min-width: 60px; text-align: right; font-family: monospace; color: #666; margin-right: 16px;"></span><?php endif; ?><a href="admin/" <?php if (!$IsAdminLoggedIn): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>>Admin</a><?php if ($IsAdminLoggedIn): ?> <a href="admin/?logout=1">Log out</a><?php endif; ?></div>
</header>
<main>
    <?php if ($ErrorMessage !== null): ?>
        <p class="error"><?= EscapeHtml($ErrorMessage) ?></p>
    <?php elseif ($Tabs === []): ?>
        <p class="empty">No public tabs have been configured yet.</p>
    <?php else: ?>
        <nav class="tabs" aria-label="Start page tabs">
            <?php foreach ($Tabs as $Tab): ?>
                <?php $TabId = (int)$Tab['idtab']; ?>
                <a class="<?= $TabId === $SelectedTabId ? 'active' : '' ?>" href="?tab=<?= $TabId ?>" <?= $TabId === $SelectedTabId ? 'aria-current="page"' : '' ?> data-tab-id="<?= $TabId ?>"><?= EscapeHtml($Tab['tabname'] ?: 'Untitled tab') ?></a>
            <?php endforeach; ?>
        </nav>
        <?php $SelectedTabIndex = array_search($SelectedTabId, array_map(static fn (array $Tab): int => (int)$Tab['idtab'], $Tabs), true); ?>
        <?php $SelectedTab = $SelectedTabIndex === false ? null : $Tabs[$SelectedTabIndex]; ?>
        <section class="tab-panel" aria-label="<?= EscapeHtml((string)($SelectedTab['tabname'] ?? 'Selected tab')) ?>">
            <?php $Headers = array_values($HeaderCellsByTab[$SelectedTabId] ?? []); ?>
            <?php if ($Headers === []): ?>
                <p class="empty">No links have been configured for this tab yet.</p>
            <?php else: ?>
                <?php $HeadersByColumn = [1 => [], 2 => [], 3 => [], 4 => []]; ?>
                <?php foreach ($Headers as $HeaderCell): ?>
                    <?php $HeadersByColumn[(int)$HeaderCell['column']][] = $HeaderCell; ?>
                <?php endforeach; ?>
                <div class="headers">
                    <?php for ($ColumnNumber = 1; $ColumnNumber <= 4; $ColumnNumber++): ?>
                        <?php usort($HeadersByColumn[$ColumnNumber], static fn (array $First, array $Second): int => [$First['row'], $First['headers'][0]['name']] <=> [$Second['row'], $Second['headers'][0]['name']]); ?>
                        <div class="header-column">
                            <?php foreach ($HeadersByColumn[$ColumnNumber] as $HeaderCell): ?>
                                <?php foreach ($HeaderCell['headers'] as $Header): ?>
                                    <section class="header-group">
                                        <h2><?= EscapeHtml($Header['name']) ?></h2>
                                        <div class="links">
                                            <?php foreach ($Header['links'] as $Link): ?>
                                                <?php if (filter_var($Link['url'], FILTER_VALIDATE_URL) === false) { continue; } ?>
                                                <a class="link" href="<?= EscapeHtml($Link['url']) ?>" target="_blank" rel="noopener noreferrer" data-link-id="<?= (int)$Link['id'] ?>">
                                                    <span class="favicon-slot"><?php if (filter_var($Link['favicon'], FILTER_VALIDATE_URL) !== false): ?><img src="<?= EscapeHtml($Link['favicon']) ?>" alt="" loading="lazy"><?php endif; ?></span>
                                                    <span class="link-label"><?= EscapeHtml($Link['name'] ?: $Link['url']) ?></span>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
<script>
    if (new URLSearchParams(window.location.search).has('focus')) {
        document.body.classList.add('focus-mode');
    }

    const body = document.body;
    const IsAdminLoggedIn = <?php echo json_encode($IsAdminLoggedIn); ?>;

    if (body.classList.contains('admin-logged-in')) {
        let contextMenu = null;

        document.addEventListener('contextmenu', (event) => {
            const link = event.target.closest('a.link');
            if (!link) return;

            event.preventDefault();

            if (contextMenu) contextMenu.remove();

            const linkId = link.dataset.linkId;
            contextMenu = document.createElement('div');
            contextMenu.style.cssText = `
                position: fixed;
                top: ${event.clientY}px;
                left: ${event.clientX}px;
                background: white;
                border: 1px solid #ccc;
                border-radius: 4px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.15);
                z-index: 10000;
                min-width: 150px;
            `;
            contextMenu.innerHTML = `
                <a href="admin/?table=links&edit=${linkId}" style="display: block; padding: 8px 16px; text-decoration: none; color: #333; border-bottom: 1px solid #eee; cursor: pointer;">Edit link</a>
                <a href="admin/?table=links&delete=${linkId}" style="display: block; padding: 8px 16px; text-decoration: none; color: #d9534f; cursor: pointer;">Delete link</a>
            `;
            Array.from(contextMenu.querySelectorAll('a')).forEach((a) => {
                a.addEventListener('click', () => {
                    contextMenu.remove();
                });
            });
            document.body.appendChild(contextMenu);

            document.addEventListener('click', () => {
                if (contextMenu) contextMenu.remove();
            }, { once: true });
        });
    }

    const tabLinks = document.querySelectorAll('nav.tabs a');
    const isFocusMode = new URLSearchParams(window.location.search).has('focus');

    tabLinks.forEach((tabLink) => {
        if (isFocusMode && tabLink.classList.contains('active')) {
            tabLink.addEventListener('click', (e) => {
                e.preventDefault();
            });
        }

        tabLink.addEventListener('contextmenu', (e) => {
            e.preventDefault();

            let existingMenu = document.getElementById('tabContextMenu');
            if (existingMenu) existingMenu.remove();

            const menu = document.createElement('div');
            menu.id = 'tabContextMenu';
            menu.style.cssText = `
                position: fixed;
                top: ${e.clientY}px;
                left: ${e.clientX}px;
                background: white;
                border: 1px solid #ccc;
                border-radius: 4px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.15);
                z-index: 10000;
                min-width: 120px;
            `;

            const tabId = tabLink.dataset.tabId;
            const isCurrent = tabLink.classList.contains('active');

            if (isFocusMode && isCurrent) {
                menu.innerHTML = `<a href="?tab=${tabId}" style="display: block; padding: 8px 16px; text-decoration: none; color: #333; cursor: pointer;">Unfocus</a>`;
            } else {
                menu.innerHTML = `<a href="?tab=${tabId}&focus=1" style="display: block; padding: 8px 16px; text-decoration: none; color: #333; cursor: pointer;">Focus</a>`;
            }

            document.body.appendChild(menu);

            document.addEventListener('click', () => {
                menu.remove();
            }, { once: true });
        });
    });

    const DisableLogoutCheckbox = document.getElementById('disableLogoutTimeout');
    const LogoutTimerDisplay = document.getElementById('logoutTimer');

    if (DisableLogoutCheckbox && LogoutTimerDisplay) {
        const LogoutTimeout = 15 * 60 * 1000;
        let LogoutTimer = null;
        let TimeoutEnd = null;
        let IsTimeoutDisabled = sessionStorage.getItem('disableLogoutTimeout') === 'true';

        DisableLogoutCheckbox.checked = IsTimeoutDisabled;

        function UpdateTimerDisplay() {
            if (IsTimeoutDisabled || !TimeoutEnd) {
                LogoutTimerDisplay.textContent = '';
                return;
            }

            const Now = Date.now();
            const TimeLeft = Math.max(0, TimeoutEnd - Now);
            const Minutes = Math.floor(TimeLeft / 60000);
            const Seconds = Math.floor((TimeLeft % 60000) / 1000);
            LogoutTimerDisplay.textContent = `${Minutes}:${Seconds.toString().padStart(2, '0')}`;
        }

        function StartLogoutTimer() {
            if (IsTimeoutDisabled) return;

            TimeoutEnd = Date.now() + LogoutTimeout;
            UpdateTimerDisplay();

            if (LogoutTimer) clearTimeout(LogoutTimer);
            LogoutTimer = setTimeout(() => {
                window.location.href = 'admin/?logout=1';
            }, LogoutTimeout);
        }

        function StopLogoutTimer() {
            if (LogoutTimer) {
                clearTimeout(LogoutTimer);
                LogoutTimer = null;
            }
            TimeoutEnd = null;
            LogoutTimerDisplay.textContent = '';
        }

        DisableLogoutCheckbox.addEventListener('change', (e) => {
            IsTimeoutDisabled = e.target.checked;
            sessionStorage.setItem('disableLogoutTimeout', IsTimeoutDisabled ? 'true' : 'false');

            if (IsTimeoutDisabled) {
                StopLogoutTimer();
            } else {
                StartLogoutTimer();
            }
        });

        document.addEventListener('click', (e) => {
            if (!IsTimeoutDisabled && e.target.id !== 'disableLogoutTimeout' && !e.target.closest('label:has(#disableLogoutTimeout)')) {
                StartLogoutTimer();
            }
        });

        setInterval(() => {
            UpdateTimerDisplay();
        }, 1000);

        StartLogoutTimer();
    }
</script>
</body>
</html>
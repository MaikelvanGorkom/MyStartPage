<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

if (empty($_SESSION['LoginCsrfToken'])) {
    $_SESSION['LoginCsrfToken'] = bin2hex(random_bytes(32));
}
$LoginCsrfToken = $_SESSION['LoginCsrfToken'];
$LoginError = null;

if (isset($_GET['logout'])) {
    LogoutAdmin();
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (!hash_equals($LoginCsrfToken, (string)($_POST['loginCsrfToken'] ?? ''))) {
        $LoginError = 'The login session expired. Please try again.';
    } elseif (LoginAdmin((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''))) {
        header('Location: index.php');
        exit;
    } else {
        $LoginError = 'The username or password is incorrect.';
    }
}

if (!IsAdminLoggedIn()):
    function EscapeLoginHtml(mixed $Value): string
    {
        return htmlspecialchars((string)($Value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Login | MyStartPage Admin</title>
        <link rel="stylesheet" href="../config/lookandfeel.css">
    </head>
    <body class="admin-login"><main>
        <h1>MyStartPage Admin</h1>
        <p>Log in to manage your tabs, headers, and links.</p>
        <?php if ($LoginError !== null): ?><p class="error"><?= EscapeLoginHtml($LoginError) ?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="loginCsrfToken" value="<?= EscapeLoginHtml($LoginCsrfToken) ?>">
            <label>Username <input name="username" autocomplete="username" required autofocus></label>
            <label>Password <input type="password" name="password" autocomplete="current-password" required></label>
            <button type="submit">Log in</button>
        </form>
    </main></body>
    </html>
    <?php
    exit;
endif;

$TableDefinitions = [
    'tab' => [
        'label' => 'Tabs',
        'primaryKey' => 'idtab',
        'columns' => [
            'tabname' => ['label' => 'Tab name', 'type' => 'text', 'required' => false],
            'hidefrompublic' => ['label' => 'Hide from public', 'type' => 'boolean', 'required' => false],
        ],
    ],
    'header' => [
        'label' => 'Headers',
        'primaryKey' => 'idheader',
        'columns' => [
            'tabid' => ['label' => 'Tab', 'type' => 'foreignKey', 'required' => true],
            'headername' => ['label' => 'Header name', 'type' => 'text', 'required' => true],
            'column' => ['label' => 'Column', 'type' => 'number', 'required' => true],
            'row' => ['label' => 'Row', 'type' => 'number', 'required' => true],
        ],
    ],
    'links' => [
        'label' => 'Links',
        'primaryKey' => 'idlinks',
        'columns' => [
            'headerid' => ['label' => 'Header', 'type' => 'foreignKey', 'required' => true],
            'pagename' => ['label' => 'Page name', 'type' => 'text', 'required' => false],
            'link' => ['label' => 'URL', 'type' => 'url', 'required' => false],
            'favicon' => ['label' => 'Favicon', 'type' => 'url', 'required' => false],
        ],
    ],
];

$Messages = [];
if (!empty($_SESSION['SuccessMessage'])) {
    $Messages[] = $_SESSION['SuccessMessage'];
    unset($_SESSION['SuccessMessage']);
}
$Errors = [];
$ActiveTable = (string)($_GET['table'] ?? $_POST['table'] ?? 'tab');
if (!array_key_exists($ActiveTable, $TableDefinitions)) {
    $ActiveTable = 'tab';
}

if (empty($_SESSION['AdminCsrfToken'])) {
    $_SESSION['AdminCsrfToken'] = bin2hex(random_bytes(32));
}
$CsrfToken = $_SESSION['AdminCsrfToken'];
$TabOptions = [];
$HeaderDisplayNames = [];

function EscapeHtml(mixed $Value): string
{
    return htmlspecialchars((string)($Value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function FormatUrlForDisplay(mixed $Value): string
{
    $Url = (string)($Value ?? '');
    $Chunks = str_split($Url, 20);

    return implode('<br>', array_map('EscapeHtml', $Chunks));
}

function GetForeignKeyOptions(PDO $DatabaseConnection, string $ColumnName): array
{
    if ($ColumnName === 'tabid') {
        $Statement = $DatabaseConnection->query('SELECT idtab AS id, tabname AS label FROM tab ORDER BY tabname, idtab');
    } else {
        $Statement = $DatabaseConnection->query('SELECT idheader AS id, tabid, headername AS label FROM header ORDER BY headername, idheader');
    }

    return $Statement->fetchAll();
}

function GetRecord(PDO $DatabaseConnection, string $TableName, array $TableDefinition, int $RecordId): ?array
{
    $PrimaryKey = $TableDefinition['primaryKey'];
    $Statement = $DatabaseConnection->prepare("SELECT * FROM `{$TableName}` WHERE `{$PrimaryKey}` = :recordId");
    $Statement->execute(['recordId' => $RecordId]);
    $Record = $Statement->fetch();

    return $Record ?: null;
}

function ValidateRecord(array $TableDefinition, array $Input): array
{
    $Record = [];
    $Errors = [];

    foreach ($TableDefinition['columns'] as $ColumnName => $ColumnDefinition) {
        $RawValue = $Input[$ColumnName] ?? null;

        if ($ColumnDefinition['type'] === 'boolean') {
            $Record[$ColumnName] = isset($Input[$ColumnName]) ? 1 : 0;
            continue;
        }

        $Value = is_string($RawValue) ? trim($RawValue) : '';
        if ($ColumnDefinition['required'] && $Value === '') {
            $Errors[] = $ColumnDefinition['label'] . ' is required.';
            continue;
        }

        if ($Value !== '' && $ColumnDefinition['type'] === 'number' && filter_var($Value, FILTER_VALIDATE_INT) === false) {
            $Errors[] = $ColumnDefinition['label'] . ' must be a whole number.';
            continue;
        }

        if ($Value !== '' && $ColumnDefinition['type'] === 'foreignKey' && filter_var($Value, FILTER_VALIDATE_INT) === false) {
            $Errors[] = $ColumnDefinition['label'] . ' is invalid.';
            continue;
        }

        if ($Value !== '' && $ColumnDefinition['type'] === 'url' && filter_var($Value, FILTER_VALIDATE_URL) === false) {
            $Errors[] = $ColumnDefinition['label'] . ' must be a valid URL.';
            continue;
        }

        $Record[$ColumnName] = $Value === '' ? null : ($ColumnDefinition['type'] === 'number' || $ColumnDefinition['type'] === 'foreignKey' ? (int)$Value : $Value);
    }

    return [$Record, $Errors];
}

function FindFaviconUrl(string $PageUrl): ?string
{
    $PageParts = parse_url($PageUrl);
    if (!is_array($PageParts) || !isset($PageParts['scheme'], $PageParts['host']) || !in_array(strtolower($PageParts['scheme']), ['http', 'https'], true)) {
        return null;
    }

    $RequestContext = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 3,
            'follow_location' => 1,
            'max_redirects' => 3,
            'user_agent' => 'MyStartPage favicon checker',
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $PageContents = @file_get_contents($PageUrl, false, $RequestContext);
    if (is_string($PageContents) && preg_match('/<link[^>]+rel=["\'][^"\']*(?:icon|shortcut icon)[^"\']*["\'][^>]+href=["\']([^"\']+)["\']/i', $PageContents, $Matches) === 1) {
        return ResolveRelativeUrl($PageUrl, html_entity_decode($Matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    $FaviconUrl = $PageParts['scheme'] . '://' . $PageParts['host'] . (isset($PageParts['port']) ? ':' . $PageParts['port'] : '') . '/favicon.ico';
    $Headers = @get_headers($FaviconUrl, true, $RequestContext);
    if (is_array($Headers) && isset($Headers[0]) && preg_match('/\s2\d\d\s/', (string)$Headers[0]) === 1) {
        return $FaviconUrl;
    }

    return null;
}

function FindPageTitle(string $PageUrl): ?string
{
    $PageParts = parse_url($PageUrl);
    if (!is_array($PageParts) || !isset($PageParts['host'])) {
        return null;
    }

    $UserAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1)',
    ];

    $PageContents = null;
    foreach ($UserAgents as $UserAgent) {
        $RequestContext = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'follow_location' => 1,
                'max_redirects' => 5,
                'user_agent' => $UserAgent,
                'header' => "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\nAccept-Language: en-US,en;q=0.5\r\n",
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $PageContents = @file_get_contents($PageUrl, false, $RequestContext);
        if (is_string($PageContents) && strlen($PageContents) > 100) {
            break;
        }
    }

    if (is_string($PageContents) && strlen($PageContents) > 100) {
        $PageTitle = ExtractPageTitle($PageContents);
        if ($PageTitle !== null) {
            return $PageTitle;
        }
    }

    if (preg_match('/^(?:www\.)?etsy\.com$/i', (string)$PageParts['host']) === 1 && isset($PageParts['path']) && preg_match('#^/(?:[a-z]{2}/)?shop/([^/]+)/?$#i', $PageParts['path'], $Matches) === 1) {
        $ShopName = rawurldecode($Matches[1]);
        return $ShopName . ' - Etsy';
    }

    if (preg_match('/^(?:www\.)?reddit\.com$/i', (string)$PageParts['host']) === 1 && isset($PageParts['path']) && preg_match('#^/r/([^/]+)/?$#i', $PageParts['path'], $Matches) === 1) {
        $SubredditName = rawurldecode($Matches[1]);
        return 'r/' . $SubredditName . ' - Reddit';
    }

    if (strcasecmp((string)$PageParts['host'], 'cults3d.com') === 0 && isset($PageParts['path']) && preg_match('#^/[^/]+/users/([^/]+)/3d-models/?$#i', $PageParts['path'], $Matches) === 1) {
        $Username = rawurldecode($Matches[1]);
        return 'All the 3D models of ' . $Username . "\u{30FB}Cults";
    }

    if (preg_match('/^(?:www\.)?thingiverse\.com$/i', (string)$PageParts['host']) === 1 && isset($PageParts['path'])) {
        if (preg_match('#^/([^/]+)/designs/?$#i', $PageParts['path'], $Matches) === 1) {
            $Username = ucfirst(rawurldecode($Matches[1]));
            return 'Designs - ' . $Username . ' - Thingiverse';
        }
    }

    $HostName = preg_replace('/^www\./i', '', (string)$PageParts['host']);
    return is_string($HostName) && $HostName !== '' ? $HostName : null;
}

function ExtractPageTitle(string $PageContents): ?string
{
    $TitlePos = strpos($PageContents, 'DetailPageTitle__thingTitleName');
    if ($TitlePos !== false) {
        $AfterClass = substr($PageContents, $TitlePos, 500);
        if (preg_match('/>([^<]{5,200})</', $AfterClass, $Matches) === 1) {
            $Title = trim(html_entity_decode($Matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($Title !== '' && strlen($Title) > 3) {
                return $Title . ' - Thingiverse';
            }
        }
    }

    $TitlePatterns = [
        '/<title[^>]*>(.*?)<\/title>/is',
        '/<meta[^>]+property=["\']og:title["\'][^>]*content=["\']([^"\']+)["\']/i',
        '/<meta[^>]+content=["\']([^"\']+)["\'][^>]*property=["\']og:title["\']/i',
        '/<meta[^>]+name=["\']twitter:title["\'][^>]*content=["\']([^"\']+)["\']/i',
        '/<meta[^>]+content=["\']([^"\']+)["\'][^>]*name=["\']twitter:title["\']/i',
        '/<meta[^>]+name=["\']title["\'][^>]*content=["\']([^"\']+)["\']/i',
        '/<meta[^>]+content=["\']([^"\']+)["\'][^>]*name=["\']title["\']/i',
    ];

    foreach ($TitlePatterns as $TitlePattern) {
        if (preg_match($TitlePattern, $PageContents, $Matches) !== 1) {
            continue;
        }

        $Title = trim(html_entity_decode(strip_tags(ConvertToUtf8($PageContents, $Matches[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($Title !== '') {
            return $Title;
        }
    }

    return null;
}

function ConvertToUtf8(string $PageContents, string $Text): string
{
    $Encoding = null;
    if (preg_match('/<meta[^>]+charset=["\']?\s*([^\s"\'>;]+)/i', $PageContents, $Matches) === 1) {
        $Encoding = $Matches[1];
    } elseif (preg_match('/<meta[^>]+content=["\'][^"\']*charset=\s*([^\s"\';]+)/i', $PageContents, $Matches) === 1) {
        $Encoding = $Matches[1];
    } elseif (function_exists('mb_detect_encoding')) {
        $Encoding = mb_detect_encoding($Text, ['UTF-8', 'Windows-1251', 'ISO-8859-1'], true);
    }

    if ($Encoding === null || strcasecmp($Encoding, 'UTF-8') === 0) {
        return $Text;
    }

    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($Text, 'UTF-8', $Encoding);
    }

    $ConvertedText = iconv($Encoding, 'UTF-8//IGNORE', $Text);
    return $ConvertedText === false ? $Text : $ConvertedText;
}

function ResolveRelativeUrl(string $PageUrl, string $FaviconPath): ?string
{
    if (filter_var($FaviconPath, FILTER_VALIDATE_URL) !== false) {
        return $FaviconPath;
    }

    $PageParts = parse_url($PageUrl);
    if (!is_array($PageParts) || !isset($PageParts['scheme'], $PageParts['host'])) {
        return null;
    }
    $BaseUrl = $PageParts['scheme'] . '://' . $PageParts['host'] . (isset($PageParts['port']) ? ':' . $PageParts['port'] : '');
    if (str_starts_with($FaviconPath, '/')) {
        return $BaseUrl . $FaviconPath;
    }

    return $BaseUrl . '/' . ltrim($FaviconPath, '/');
}

try {
    $DatabaseConnection = CreateDatabaseConnection();
        $TabOptions = $DatabaseConnection->query('SELECT idtab, tabname FROM tab ORDER BY tabname, idtab')->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($CsrfToken, (string)($_POST['csrfToken'] ?? ''))) {
            throw new RuntimeException('The form session expired. Please try again.');
        }

        $PostedAction = (string)($_POST['action'] ?? '');
        $PostedTable = (string)($_POST['table'] ?? '');
        if (!array_key_exists($PostedTable, $TableDefinitions)) {
            throw new RuntimeException('The selected table is invalid.');
        }
        $ActiveTable = $PostedTable;
        $TableDefinition = $TableDefinitions[$ActiveTable];
        $PrimaryKey = $TableDefinition['primaryKey'];

        if ($PostedAction === 'save') {
            [$Record, $ValidationErrors] = ValidateRecord($TableDefinition, $_POST['record'] ?? []);
            if ($ValidationErrors !== []) {
                $Errors = $ValidationErrors;
            } else {
                $RecordId = filter_var($_POST['recordId'] ?? null, FILTER_VALIDATE_INT);
                if ($ActiveTable === 'links' && ($RecordId === false || $RecordId === null || $RecordId < 1) && empty($Record['favicon']) && !empty($Record['link'])) {
                    $Record['favicon'] = FindFaviconUrl($Record['link']);
                }
                if ($ActiveTable === 'links' && ($RecordId === false || $RecordId === null || $RecordId < 1) && empty($Record['pagename']) && !empty($Record['link'])) {
                    $Record['pagename'] = FindPageTitle($Record['link']);
                    if ($Record['pagename'] === null) {
                        $Errors[] = 'Page name could not be read from the URL. Please enter a name manually.';
                    }
                }
                if ($Errors === []) {
                    if ($RecordId !== false && $RecordId !== null && $RecordId > 0) {
                        if ($ActiveTable === 'links' && empty($Record['favicon']) && !empty($Record['link'])) {
                            $Record['favicon'] = FindFaviconUrl($Record['link']);
                        }
                        if ($ActiveTable === 'links' && empty($Record['pagename']) && !empty($Record['link'])) {
                            $Record['pagename'] = FindPageTitle($Record['link']);
                            if ($Record['pagename'] === null) {
                                $Errors[] = 'Page name could not be read from the URL. Please enter a name manually.';
                            }
                        }
                        if ($Errors === []) {
                            $Assignments = array_map(static fn (string $Column): string => "`{$Column}` = :{$Column}", array_keys($Record));
                            $Record['recordId'] = $RecordId;
                            $Statement = $DatabaseConnection->prepare("UPDATE `{$ActiveTable}` SET " . implode(', ', $Assignments) . " WHERE `{$PrimaryKey}` = :recordId");
                            $Statement->execute($Record);
                            $Messages[] = $TableDefinition['label'] . ' record updated.';
                        }
                    } else {
                        $ColumnNames = array_keys($Record);
                        $Placeholders = array_map(static fn (string $Column): string => ':' . $Column, $ColumnNames);
                        $Statement = $DatabaseConnection->prepare("INSERT INTO `{$ActiveTable}` (`" . implode('`, `', $ColumnNames) . "`) VALUES (" . implode(', ', $Placeholders) . ")");
                        $Statement->execute($Record);
                        $Messages[] = $TableDefinition['label'] . ' record added.';
                    }
                }
            }
        } elseif ($PostedAction === 'delete') {
            $RecordId = filter_var($_POST['recordId'] ?? null, FILTER_VALIDATE_INT);
            $ConfirmationCode = (string)($_POST['confirmationCode'] ?? '');
            if ($RecordId === false || $RecordId < 1 || !preg_match('/^\d{4}$/', $ConfirmationCode) || !hash_equals((string)($_SESSION['DeleteCodes'][$ActiveTable][$RecordId] ?? ''), $ConfirmationCode)) {
                $Errors[] = 'The delete confirmation code is incorrect.';
            } else {
                $Statement = $DatabaseConnection->prepare("DELETE FROM `{$ActiveTable}` WHERE `{$PrimaryKey}` = :recordId");
                $Statement->execute(['recordId' => $RecordId]);
                unset($_SESSION['DeleteCodes'][$ActiveTable][$RecordId]);
                $_SESSION['SuccessMessage'] = $TableDefinition['label'] . ' record deleted.';
                header('Location: ?table=' . urlencode($ActiveTable));
                exit;
            }
        } elseif ($PostedAction === 'requestDelete') {
            $RecordId = filter_var($_POST['recordId'] ?? null, FILTER_VALIDATE_INT);
            if ($RecordId === false || $RecordId < 1) {
                $Errors[] = 'The selected record is invalid.';
            } else {
                $_SESSION['DeleteCodes'][$ActiveTable][$RecordId] = (string)random_int(1000, 9999);
                $Messages[] = 'Enter the four-digit confirmation code shown below to delete record #' . $RecordId . '.';
            }
        }
    }

    $TableDefinition = $TableDefinitions[$ActiveTable];
    $PrimaryKey = $TableDefinition['primaryKey'];
    $ListStatement = $DatabaseConnection->query("SELECT * FROM `{$ActiveTable}` ORDER BY `{$PrimaryKey}` DESC");
    $Records = $ListStatement->fetchAll();
    $ForeignKeyOptions = [];
    foreach ($TableDefinition['columns'] as $ColumnName => $ColumnDefinition) {
        if ($ColumnDefinition['type'] === 'foreignKey') {
            $ForeignKeyOptions[$ColumnName] = GetForeignKeyOptions($DatabaseConnection, $ColumnName);
        }
    }
    $TabNamesById = [];
    foreach ($TabOptions as $TabOption) {
        $TabNamesById[(int)$TabOption['idtab']] = (string)($TabOption['tabname'] ?: 'Untitled tab');
    }
    foreach ($ForeignKeyOptions['headerid'] ?? [] as $HeaderOption) {
        $HeaderDisplayNames[(int)$HeaderOption['id']] = ($TabNamesById[(int)$HeaderOption['tabid']] ?? 'Unknown tab') . ' / ' . $HeaderOption['label'];
    }
} catch (PDOException $Exception) {
    if (($Exception->errorInfo[1] ?? null) === 1142) {
        $Errors[] = 'The database account does not have permission to update records. Ask the database administrator to grant UPDATE on MyStartPage.* to MyStartPage.';
    } else {
        $Errors[] = $Exception->getMessage();
    }
    $Records = [];
    $TableDefinition = $TableDefinitions[$ActiveTable];
    $ForeignKeyOptions = [];
} catch (Throwable $Exception) {
    $Errors[] = $Exception->getMessage();
    $Records = [];
    $TableDefinition = $TableDefinitions[$ActiveTable];
    $ForeignKeyOptions = [];
}

$EditRecord = null;
if (array_key_exists('edit', $_GET)) {
    try {
        $EditId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
        if ($EditId === false || $EditId < 1) {
            $Errors[] = 'The selected record is invalid.';
        } elseif (isset($DatabaseConnection)) {
            $EditRecord = GetRecord($DatabaseConnection, $ActiveTable, $TableDefinitions[$ActiveTable], $EditId);
            if ($EditRecord === null) {
                $Errors[] = 'The selected record could not be found.';
            }
        }
    } catch (Throwable $Exception) {
        $Errors[] = 'Unable to load the record for editing.';
    }
}
$DeleteRecordId = filter_var($_GET['delete'] ?? null, FILTER_VALIDATE_INT);
$DeleteRequested = $DeleteRecordId !== false && $DeleteRecordId > 0;
if ($DeleteRequested && empty($_SESSION['DeleteCodes'][$ActiveTable][$DeleteRecordId])) {
    $_SESSION['DeleteCodes'][$ActiveTable][$DeleteRecordId] = (string)random_int(1000, 9999);
}
$DeleteCode = $DeleteRecordId !== false && $DeleteRecordId > 0 ? ($_SESSION['DeleteCodes'][$ActiveTable][$DeleteRecordId] ?? null) : null;
$SelectedLinkTabId = '';
if ($ActiveTable === 'links') {
    $SelectedHeaderId = (int)($EditRecord['headerid'] ?? 0);
    foreach ($ForeignKeyOptions['headerid'] ?? [] as $HeaderOption) {
        if ((int)$HeaderOption['id'] === $SelectedHeaderId) {
            $SelectedLinkTabId = (string)$HeaderOption['tabid'];
            break;
        }
    }
    if ($SelectedLinkTabId === '' && isset($TabOptions[0])) {
        $SelectedLinkTabId = (string)$TabOptions[0]['idtab'];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MyStartPage Admin</title>
    <link rel="stylesheet" href="../config/lookandfeel.css">
</head>
<body class="admin-page">
<header>
    <h1>MyStartPage Admin</h1>
    <p>Manage tabs, headers, and links from one place.</p>
    <div class="header-actions"><a href="../index.php">View MyStartPage</a><a href="?logout=1">Log out</a></div>
</header>
<main>
    <?php foreach ($Messages as $Message): ?><div class="notice"><?= EscapeHtml($Message) ?></div><?php endforeach; ?>
    <?php foreach ($Errors as $Error): ?><div class="notice error"><?= EscapeHtml($Error) ?></div><?php endforeach; ?>

    <div class="layout">
        <nav aria-label="Database tables">
            <strong>Tables</strong>
            <?php foreach ($TableDefinitions as $TableName => $Definition): ?>
                <a class="<?= $ActiveTable === $TableName ? 'active' : '' ?>" href="?table=<?= EscapeHtml($TableName) ?>"><?= EscapeHtml($Definition['label']) ?></a>
            <?php endforeach; ?>
        </nav>
        <div>
            <?php if ($DeleteCode !== null && $DeleteRecordId !== false && $DeleteRecordId > 0): ?>
                <div class="confirm">
                    <h3>Confirm deletion of record #<?= (int)$DeleteRecordId ?></h3>
                    <p>Enter this four-digit code in the confirmation field: <span class="code"><?= EscapeHtml($DeleteCode) ?></span></p>
                    <form method="post" class="grid">
                        <input type="hidden" name="csrfToken" value="<?= EscapeHtml($CsrfToken) ?>">
                        <input type="hidden" name="table" value="<?= EscapeHtml($ActiveTable) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="recordId" value="<?= (int)$DeleteRecordId ?>">
                        <label>Confirmation code <input name="confirmationCode" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required></label>
                        <div class="actions"><button type="submit" class="danger">Delete record</button><a class="button secondary" href="?table=<?= EscapeHtml($ActiveTable) ?>">Cancel</a></div>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($ActiveTable === 'links' && $EditRecord !== null && isset($EditRecord['link']) && preg_match('/thingiverse\.com/i', (string)$EditRecord['link'])): ?>
                <div class="notice">
                    <p><strong>Thingiverse Note:</strong> Thingiverse doesn't load the page title correctly. Please enter or verify the correct title for this design before saving.</p>
                </div>
            <?php endif; ?>

            <section>
                <h2><?= $EditRecord === null ? 'Add' : 'Edit' ?> <?= EscapeHtml($TableDefinition['label']) ?></h2>
                <form method="post" class="grid">
                    <input type="hidden" name="csrfToken" value="<?= EscapeHtml($CsrfToken) ?>">
                    <input type="hidden" name="table" value="<?= EscapeHtml($ActiveTable) ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="recordId" value="<?= EscapeHtml($EditRecord[$PrimaryKey] ?? '') ?>">
                    <?php foreach ($TableDefinition['columns'] as $ColumnName => $ColumnDefinition): ?>
                        <?php $FieldValue = $EditRecord[$ColumnName] ?? ''; ?>
                        <?php if ($ColumnDefinition['type'] === 'boolean'): ?>
                            <label class="checkbox-label"><input type="checkbox" name="record[<?= EscapeHtml($ColumnName) ?>]" value="1" <?= (int)$FieldValue === 1 ? 'checked' : '' ?>><?= EscapeHtml($ColumnDefinition['label']) ?></label>
                        <?php elseif ($ActiveTable === 'links' && $ColumnName === 'headerid'): ?>
                            <label>Tab
                                <select id="linkTabId" name="linkTabId">
                                    <?php foreach ($TabOptions as $TabOption): ?><option value="<?= (int)$TabOption['idtab'] ?>" <?= (string)$TabOption['idtab'] === $SelectedLinkTabId ? 'selected' : '' ?>><?= EscapeHtml($TabOption['tabname'] ?: 'Untitled tab') ?></option><?php endforeach; ?>
                                </select>
                            </label>
                            <label>Header
                                <select id="linkHeaderId" name="record[headerid]" required>
                                    <option value="">Select...</option>
                                    <?php foreach ($ForeignKeyOptions['headerid'] ?? [] as $Option): ?><option value="<?= (int)$Option['id'] ?>" data-tab-id="<?= (int)$Option['tabid'] ?>" <?= (string)$FieldValue === (string)$Option['id'] ? 'selected' : '' ?>><?= EscapeHtml($Option['label']) ?></option><?php endforeach; ?>
                                </select>
                            </label>
                        <?php elseif ($ColumnDefinition['type'] === 'foreignKey'): ?>
                            <label><?= EscapeHtml($ColumnDefinition['label']) ?>
                                <select name="record[<?= EscapeHtml($ColumnName) ?>]" <?= $ColumnDefinition['required'] ? 'required' : '' ?>>
                                    <option value="">Select...</option>
                                    <?php foreach ($ForeignKeyOptions[$ColumnName] ?? [] as $Option): ?><option value="<?= (int)$Option['id'] ?>" <?= (string)$FieldValue === (string)$Option['id'] ? 'selected' : '' ?>><?= EscapeHtml($Option['label'] . ' (#' . $Option['id'] . ')') ?></option><?php endforeach; ?>
                                </select>
                            </label>
                        <?php else: ?>
                            <label><?= EscapeHtml($ColumnDefinition['label']) ?>
                                <input type="<?= $ColumnDefinition['type'] === 'url' ? 'url' : ($ColumnDefinition['type'] === 'number' ? 'number' : 'text') ?>" name="record[<?= EscapeHtml($ColumnName) ?>]" value="<?= EscapeHtml((string)$FieldValue) ?>" <?= $ColumnDefinition['required'] ? 'required' : '' ?>>
                            </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <div class="actions"><button type="submit"><?= $EditRecord === null ? 'Add record' : 'Save changes' ?></button><?php if ($EditRecord !== null): ?><a class="button secondary" href="?table=<?= EscapeHtml($ActiveTable) ?>">Cancel edit</a><?php endif; ?></div>
                </form>
            </section>

            <section>
                <h2><?= EscapeHtml($TableDefinition['label']) ?></h2>
                <?php if ($Records === []): ?><p class="empty">No records found.</p><?php else: ?>
                    <?php $DisplayColumns = array_keys($TableDefinition['columns']); ?>
                    <?php if ($ActiveTable === 'links'): ?><?php $DisplayColumns = array_merge(['favicon'], array_diff($DisplayColumns, ['favicon'])); ?><?php endif; ?>
                    <div class="table-wrap"><table>
                        <thead><tr><th>Actions</th><?php foreach ($DisplayColumns as $ColumnName): ?><th><?= EscapeHtml($TableDefinition['columns'][$ColumnName]['label']) ?></th><?php endforeach; ?></tr></thead>
                        <tbody>
                        <?php foreach ($Records as $Record): ?>
                            <tr>
                                <td class="actions-cell <?= $ActiveTable === 'links' ? 'links-actions-cell' : '' ?>"><a class="button secondary" href="?table=<?= EscapeHtml($ActiveTable) ?>&edit=<?= (int)$Record[$PrimaryKey] ?>">Edit</a> <a class="button danger" href="?table=<?= EscapeHtml($ActiveTable) ?>&delete=<?= (int)$Record[$PrimaryKey] ?>">Remove</a></td>
                                <?php foreach ($DisplayColumns as $ColumnName): ?><td class="<?= $ActiveTable === 'links' && $ColumnName === 'link' ? 'url-cell' : ($ActiveTable === 'links' && $ColumnName === 'pagename' ? 'page-name-cell' : ($ActiveTable === 'links' && $ColumnName === 'favicon' ? 'favicon-cell' : ($ActiveTable === 'links' && $ColumnName === 'headerid' ? 'header-cell' : ''))) ?>"><?php if ($ActiveTable === 'links' && $ColumnName === 'link' && filter_var($Record[$ColumnName] ?? '', FILTER_VALIDATE_URL) !== false): ?><a class="button secondary url-button" href="<?= EscapeHtml($Record[$ColumnName]) ?>" target="_blank" rel="noopener noreferrer" title="<?= EscapeHtml($Record[$ColumnName]) ?>">Open page</a><?php elseif ($ActiveTable === 'links' && $ColumnName === 'favicon' && filter_var($Record[$ColumnName] ?? '', FILTER_VALIDATE_URL) !== false): ?><img src="<?= EscapeHtml($Record[$ColumnName]) ?>" alt="Favicon" loading="lazy"><?php elseif (!($ActiveTable === 'links' && in_array($ColumnName, ['link', 'favicon'], true))): ?><?= EscapeHtml($ActiveTable === 'links' && $ColumnName === 'headerid' ? ($HeaderDisplayNames[(int)$Record[$ColumnName]] ?? 'Unknown header') : ($ActiveTable === 'header' && $ColumnName === 'tabid' ? ($TabNamesById[(int)$Record[$ColumnName]] ?? 'Unknown tab') : (string)($Record[$ColumnName] ?? ''))) ?><?php endif; ?></td><?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>
<?php if ($ActiveTable === 'links'): ?>
<script>
    const LinkTabSelect = document.getElementById('linkTabId');
    const LinkHeaderSelect = document.getElementById('linkHeaderId');

    function UpdateLinkHeaders() {
        const SelectedTabId = LinkTabSelect.value;
        let HasSelectedHeader = false;

        Array.from(LinkHeaderSelect.options).forEach((Option) => {
            if (!Option.value) {
                Option.hidden = false;
                return;
            }
            const IsVisible = Option.dataset.tabId === SelectedTabId;
            Option.hidden = !IsVisible;
            if (IsVisible && Option.selected) {
                HasSelectedHeader = true;
            }
        });

        if (!HasSelectedHeader) {
            LinkHeaderSelect.value = '';
        }
    }

    LinkTabSelect.addEventListener('change', UpdateLinkHeaders);
    UpdateLinkHeaders();
</script>
<?php endif; ?>
<script>
if (<?php echo json_encode($IsAdminLoggedIn); ?>) {
    sessionStorage.removeItem('disableLogoutTimeout');
}
</script>
</body>
</html>

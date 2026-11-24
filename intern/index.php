<?php
/*
 * Karlsruher Freaks – interner Bereich
 * Das Passwort liegt als Hash in einer Datei AUSSERHALB von public_html
 * (nicht im GitHub-Repository). Beim ersten Aufruf wird es hier festgelegt.
 */
declare(strict_types=1);

$configFile = dirname(__DIR__, 2) . '/kf-intern-zugang.php';

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/intern/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];
$config = is_file($configFile) ? (include $configFile) : null;
$mode = $config ? 'login' : 'setup';
$error = '';

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: /intern/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        $error = 'Die Sitzung ist abgelaufen. Bitte noch einmal versuchen.';
    } elseif ($mode === 'setup') {
        $p1 = (string)($_POST['pass'] ?? '');
        $p2 = (string)($_POST['pass2'] ?? '');
        if (strlen($p1) < 10) {
            $error = 'Das Passwort muss mindestens 10 Zeichen lang sein.';
        } elseif ($p1 !== $p2) {
            $error = 'Die beiden Passwörter stimmen nicht überein.';
        } elseif (is_file($configFile)) {
            $error = 'Das Passwort wurde bereits festgelegt.';
        } else {
            $php = "<?php\nreturn " . var_export(['hash' => password_hash($p1, PASSWORD_DEFAULT)], true) . ";\n";
            if (@file_put_contents($configFile, $php, LOCK_EX) === false) {
                $error = 'Das Passwort konnte nicht gespeichert werden (Schreibrechte).';
            } else {
                @chmod($configFile, 0600);
                session_regenerate_id(true);
                $_SESSION['ok'] = true;
                header('Location: /intern/');
                exit;
            }
        }
    } else {
        $fails = $_SESSION['fails'] ?? 0;
        if ($fails >= 3) { sleep(min(10, $fails)); }
        if (password_verify((string)($_POST['pass'] ?? ''), $config['hash'])) {
            session_regenerate_id(true);
            $_SESSION['ok'] = true;
            $_SESSION['fails'] = 0;
            header('Location: /intern/');
            exit;
        }
        $_SESSION['fails'] = $fails + 1;
        $error = 'Das Passwort ist falsch.';
    }
}

$loggedIn = !empty($_SESSION['ok']) && $config;
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Interner Bereich – Karlsruher Freaks</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  color-scheme: dark;
  --night: #14131f; --night-2: #1c1b2b; --night-3: #27253a;
  --paper: #f1eee6; --muted: #a39fb5; --baden: #f6c21c; --red: #e5483a; --blurple: #5865f2;
  --display: "Bricolage Grotesque", "Arial Narrow", Arial, sans-serif;
  --body: "IBM Plex Sans", "Segoe UI", Roboto, sans-serif;
  --mono: "IBM Plex Mono", ui-monospace, Menlo, monospace;
}
* { box-sizing: border-box; }
body { margin: 0; background: var(--night); color: var(--paper); font: 17px/1.6 var(--body); -webkit-font-smoothing: antialiased; }
a { color: var(--baden); }
:focus-visible { outline: 2px solid var(--baden); outline-offset: 3px; }
.wrap { max-width: 1040px; margin: 0 auto; padding: 0 20px; }
h1, h2, h3 { font-family: var(--display); line-height: 1.05; margin: 0; letter-spacing: -0.02em; text-wrap: balance; }
h1 { font-size: clamp(2.2rem, 6vw, 3.6rem); font-weight: 800; }
h2 { font-size: clamp(1.5rem, 3.4vw, 2rem); font-weight: 800; }
h3 { font-size: 1.1rem; font-weight: 700; }
p { margin: 0; }
.eyebrow { font-family: var(--mono); font-size: .76rem; letter-spacing: .14em; text-transform: uppercase; color: var(--baden); }
.muted { color: var(--muted); }
header.top { border-bottom: 1px solid var(--night-3); background: rgba(20,19,31,.9); position: sticky; top: 0; z-index: 5; backdrop-filter: blur(10px); }
header.top .wrap { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 64px; }
.logo { display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--paper); font-family: var(--display); font-weight: 800; }
.logo svg { width: 28px; height: 28px; }
.btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 46px; padding: 10px 18px; border-radius: 4px; border: 0; font: 600 1rem var(--body); text-decoration: none; cursor: pointer; }
.btn-primary { background: var(--baden); color: #16130a; }
.btn-ghost { background: none; color: var(--paper); box-shadow: inset 0 0 0 1.5px var(--night-3); }
.btn-ghost:hover { box-shadow: inset 0 0 0 1.5px var(--paper); }

/* Login */
.gate { min-height: calc(100vh - 65px); display: grid; place-items: center; padding: 40px 16px; }
.card { width: min(420px, 100%); background: var(--night-2); border: 1px solid var(--night-3); border-radius: 10px; padding: 30px 26px; display: grid; gap: 16px; }
.field { display: grid; gap: 6px; }
.field label { font-size: .88rem; color: var(--muted); }
.field input { font: inherit; color: var(--paper); background: var(--night); border: 1.5px solid var(--night-3); border-radius: 4px; padding: 12px 14px; width: 100%; }
.field input:focus { border-color: var(--baden); outline: none; }
.error { border-left: 3px solid var(--red); background: var(--night); padding: 10px 14px; font-size: .92rem; }
.hint { border-left: 3px solid var(--baden); background: var(--night); padding: 10px 14px; font-size: .92rem; }

/* Inhalt */
.hero { padding: 48px 0 36px; border-bottom: 1px solid var(--night-3); display: grid; gap: 14px; }
.toc { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.toc a { font-family: var(--mono); font-size: .8rem; text-decoration: none; color: var(--paper); border: 1px solid var(--night-3); border-radius: 99px; padding: 6px 12px; }
.toc a:hover { border-color: var(--baden); color: var(--baden); }
section { padding: 44px 0; border-bottom: 1px solid var(--night-3); display: grid; gap: 20px; scroll-margin-top: 72px; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; }
.box { background: var(--night-2); border: 1px solid var(--night-3); border-radius: 6px; padding: 20px; display: grid; gap: 8px; align-content: start; }
.box .k { font-family: var(--mono); font-size: .72rem; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); }
.box .v { font-family: var(--display); font-size: 1.3rem; font-weight: 700; }
.todo { color: var(--baden); border-bottom: 1px dashed var(--baden); }
.table-wrap { overflow-x: auto; border: 1px solid var(--night-3); border-radius: 6px; }
table { width: 100%; border-collapse: collapse; min-width: 520px; }
th, td { text-align: left; padding: 12px 16px; border-bottom: 1px solid var(--night-3); }
th { font: 500 .74rem var(--mono); letter-spacing: .1em; text-transform: uppercase; color: var(--muted); background: var(--night-2); }
tr:last-child td { border-bottom: 0; }
ol.rules { margin: 0; padding: 0; list-style: none; counter-reset: r; display: grid; gap: 12px; }
ol.rules li { counter-increment: r; display: grid; grid-template-columns: 36px 1fr; gap: 12px; }
ol.rules li::before { content: counter(r, decimal-leading-zero); font-family: var(--mono); font-size: .85rem; color: var(--red); padding-top: 2px; }
ol.rules span { color: var(--muted); display: block; font-size: .95rem; }
.docs { display: grid; gap: 8px; }
.doc { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 14px 18px; background: var(--night-2); border: 1px solid var(--night-3); border-radius: 6px; }
.doc small { font-family: var(--mono); color: var(--muted); font-size: .75rem; }
footer { padding: 32px 0 48px; color: var(--muted); font-size: .88rem; }
@media (max-width: 600px) { body { font-size: 16px; } header.top .btn { padding: 8px 12px; } }
@media (prefers-reduced-motion: reduce) { * { scroll-behavior: auto !important; } }
</style>
</head>
<body>
<header class="top">
  <div class="wrap">
    <a class="logo" href="/">
      <svg viewBox="0 0 32 32" aria-hidden="true"><circle cx="16" cy="16" r="15" fill="none" stroke="#f6c21c" stroke-width="1.5"/><g stroke="#f6c21c" stroke-width="1.5" stroke-linecap="round"><path d="M16 16 6 27M16 16l-5 13M16 16v14M16 16l5 13M16 16l10 11"/></g><circle cx="16" cy="16" r="3" fill="#e5483a"/></svg>
      Karlsruher Freaks
    </a>
    <?php if ($loggedIn): ?>
      <a class="btn btn-ghost" href="/intern/?logout=1">Abmelden</a>
    <?php else: ?>
      <a class="btn btn-ghost" href="/">Zur Startseite</a>
    <?php endif; ?>
  </div>
</header>

<?php if (!$loggedIn): ?>
<main class="gate">
  <form class="card" method="post" action="/intern/" autocomplete="off">
    <p class="eyebrow">Interner Bereich</p>
    <?php if ($mode === 'setup'): ?>
      <h1 style="font-size:2rem">Zugang einrichten</h1>
      <p class="hint">Es ist noch kein Passwort festgelegt. Das Passwort, das du hier vergibst, gilt für alle Mitglieder. Es wird nur verschlüsselt gespeichert.</p>
      <div class="field"><label for="pass">Neues Passwort (mindestens 10 Zeichen)</label><input id="pass" name="pass" type="password" minlength="10" required autocomplete="new-password"></div>
      <div class="field"><label for="pass2">Passwort wiederholen</label><input id="pass2" name="pass2" type="password" minlength="10" required autocomplete="new-password"></div>
    <?php else: ?>
      <h1 style="font-size:2rem">Nur für Freaks</h1>
      <p class="muted">Gib das Mitglieder-Passwort ein. Du bekommst es im Discord von der Clanleitung.</p>
      <div class="field"><label for="pass">Passwort</label><input id="pass" name="pass" type="password" required autocomplete="current-password" autofocus></div>
    <?php endif; ?>
    <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <button class="btn btn-primary" type="submit"><?= $mode === 'setup' ? 'Passwort speichern' : 'Einloggen' ?></button>
  </form>
</main>
<?php else: ?>
<main class="wrap">
  <div class="hero">
    <p class="eyebrow">Interner Bereich · Vereinsinfos</p>
    <h1>Willkommen im Clanheim.</h1>
    <p class="muted" style="max-width:60ch">Hier steht alles, was Mitglieder wissen müssen: wer den Clan leitet, wie die Mitgliedschaft funktioniert, unsere Regeln, feste Termine und die Zugänge zu unseren Servern. Gelb gestrichelte Stellen sind Platzhalter, die noch ausgefüllt werden.</p>
    <nav class="toc" aria-label="Inhalt">
      <a href="#ueberblick">Überblick</a><a href="#leitung">Clanleitung</a><a href="#mitgliedschaft">Mitgliedschaft</a><a href="#regeln">Regeln</a><a href="#termine">Termine</a><a href="#server">Server</a><a href="#dokumente">Dokumente</a>
    </nav>
  </div>

  <section id="ueberblick">
    <h2>Überblick</h2>
    <div class="grid">
      <div class="box"><span class="k">Gegründet</span><span class="v">2001 in Karlsruhe</span></div>
      <div class="box"><span class="k">Rechtsform</span><span class="v"><span class="todo">eingetragener Verein oder Interessengemeinschaft?</span></span></div>
      <div class="box"><span class="k">Mitglieder</span><span class="v"><span class="todo">Anzahl eintragen</span></span></div>
      <div class="box"><span class="k">Treffpunkt</span><span class="v">Discord-Server</span></div>
    </div>
  </section>

  <section id="leitung">
    <h2>Clanleitung und Ansprechpartner</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Aufgabe</th><th>Name / Nickname</th><th>Erreichbar über</th></tr></thead>
        <tbody>
          <tr><td>Clanleitung</td><td><span class="todo">eintragen</span></td><td>Discord</td></tr>
          <tr><td>Stellvertretung</td><td><span class="todo">eintragen</span></td><td>Discord</td></tr>
          <tr><td>Discord-Moderation</td><td><span class="todo">eintragen</span></td><td>Discord</td></tr>
          <tr><td>Gameserver &amp; Technik</td><td><span class="todo">eintragen</span></td><td>Discord</td></tr>
          <tr><td>Kasse</td><td><span class="todo">eintragen oder streichen</span></td><td>Discord</td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <section id="mitgliedschaft">
    <h2>Mitgliedschaft</h2>
    <div class="grid">
      <div class="box"><h3>Aufnahme</h3><p class="muted">Wer mitspielen will, tritt dem Discord bei, stellt sich vor und spielt ein paar Runden mit. Über die Aufnahme entscheidet die Clanleitung.</p></div>
      <div class="box"><h3>Beitrag</h3><p class="muted"><span class="todo">Höhe und Zahlungsweise eintragen, oder „Die Mitgliedschaft ist kostenlos.“</span></p></div>
      <div class="box"><h3>Austritt</h3><p class="muted">Ein kurzer Hinweis an die Clanleitung genügt. Die Rolle im Discord wird danach entfernt.</p></div>
    </div>
  </section>

  <section id="regeln">
    <h2>Unsere Regeln</h2>
    <ol class="rules">
      <li><div><strong>Respekt im Umgang</strong><span>Keine Beleidigungen, kein Rassismus, keine Diskriminierung, weder im Discord noch im Spiel.</span></div></li>
      <li><div><strong>Fair Play</strong><span>Keine Cheats, Hacks oder Exploits. Wer betrügt, fliegt.</span></div></li>
      <li><div><strong>Clan-Tag mit Stolz</strong><span>Wer den Clan-Tag trägt, vertritt die Karlsruher Freaks und benimmt sich entsprechend.</span></div></li>
      <li><div><strong>Voice-Chat</strong><span>Push-to-Talk bei Hintergrundgeräuschen, niemand wird niedergeschrien.</span></div></li>
      <li><div><strong>Interna bleiben intern</strong><span>Server-Passwörter und Inhalte aus diesem Bereich werden nicht weitergegeben.</span></div></li>
      <li><div><strong>Streit klären</strong><span>Probleme direkt und sachlich ansprechen, im Zweifel die Clanleitung einschalten.</span></div></li>
    </ol>
  </section>

  <section id="termine">
    <h2>Feste Termine</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Was</th><th>Wann</th><th>Wo</th></tr></thead>
        <tbody>
          <tr><td>Fester Spieleabend</td><td><span class="todo">Wochentag und Uhrzeit</span></td><td>Discord, Voice-Channel</td></tr>
          <tr><td>Clan-Besprechung</td><td><span class="todo">z. B. erster Sonntag im Monat</span></td><td>Discord</td></tr>
          <tr><td>Treffen in Karlsruhe / LAN</td><td><span class="todo">Datum</span></td><td><span class="todo">Ort</span></td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <section id="server">
    <h2>Gameserver und Zugänge</h2>
    <p class="muted">Adressen und Passwörter nur hier oder im Discord teilen, nie öffentlich.</p>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Spiel</th><th>Server / Gruppe</th><th>Zugang</th></tr></thead>
        <tbody>
          <tr><td>Call of Duty</td><td><span class="todo">Clan / Gruppe</span></td><td><span class="todo">eintragen</span></td></tr>
          <tr><td>Battlefield 6</td><td><span class="todo">Platoon / Server</span></td><td><span class="todo">eintragen</span></td></tr>
          <tr><td>World of Warcraft</td><td><span class="todo">Realm und Gilde</span></td><td><span class="todo">eintragen</span></td></tr>
          <tr><td>GTA 6</td><td><span class="todo">Crew (sobald Online-Modus da ist)</span></td><td><span class="todo">eintragen</span></td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <section id="dokumente">
    <h2>Dokumente</h2>
    <div class="docs">
      <div class="doc"><span>Satzung bzw. Clan-Regeln</span><small class="todo">noch nicht hinterlegt</small></div>
      <div class="doc"><span>Protokolle der Besprechungen</span><small class="todo">noch nicht hinterlegt</small></div>
      <div class="doc"><span>Clan-Archiv seit 2001</span><small class="todo">Fotos und Screenshots sammeln</small></div>
    </div>
  </section>
</main>
<footer class="wrap">Interner Bereich der Karlsruher Freaks · nicht öffentlich</footer>
<?php endif; ?>
</body>
</html>

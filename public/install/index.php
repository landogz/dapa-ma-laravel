<?php

declare(strict_types=1);

require __DIR__.'/_bootstrap.php';

$installed = installer_is_locked();
$suggestedUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    .'://'.($_SERVER['HTTP_HOST'] ?? 'localhost');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>DAPE-MA Installer</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --blue: #055498;
      --nile: #123a60;
      --red: #CE2028;
      --gold: #FBD116;
      --bg: #0b1726;
      --card: #ffffff;
      --muted: #64748b;
      --line: #e2e8f0;
      --ok: #059669;
      --fail: #CE2028;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      font-family: Montserrat, system-ui, sans-serif;
      color: #0f172a;
      background:
        radial-gradient(1200px 600px at 10% -10%, rgba(5,84,152,.55), transparent 55%),
        radial-gradient(900px 500px at 100% 0%, rgba(18,58,96,.7), transparent 50%),
        linear-gradient(160deg, #07111d 0%, #123a60 45%, #055498 100%);
    }
    .shell {
      max-width: 880px;
      margin: 0 auto;
      padding: 28px 16px 48px;
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 14px;
      color: #fff;
      margin-bottom: 22px;
    }
    .brand-mark {
      width: 48px; height: 48px;
      border-radius: 14px;
      background: linear-gradient(135deg, var(--blue), var(--nile));
      border: 1px solid rgba(255,255,255,.25);
      display: grid; place-items: center;
      font-weight: 700; color: #fff;
      box-shadow: 0 10px 30px rgba(5,84,152,.35);
    }
    .brand h1 {
      margin: 0;
      font-size: clamp(1.25rem, 3vw, 1.6rem);
      font-weight: 700;
      letter-spacing: .02em;
    }
    .brand p { margin: 2px 0 0; opacity: .8; font-size: .9rem; }
    .card {
      background: var(--card);
      border-radius: 20px;
      box-shadow: 0 24px 60px rgba(0,0,0,.28);
      overflow: hidden;
    }
    .steps {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 0;
      background: #f8fafc;
      border-bottom: 1px solid var(--line);
    }
    .step-tab {
      padding: 14px 10px;
      text-align: center;
      font-size: .72rem;
      font-weight: 600;
      color: var(--muted);
      border-right: 1px solid var(--line);
      position: relative;
    }
    .step-tab:last-child { border-right: 0; }
    .step-tab.active { color: var(--blue); background: #fff; }
    .step-tab.done { color: var(--ok); }
    .step-tab span {
      display: inline-grid;
      place-items: center;
      width: 22px; height: 22px;
      border-radius: 999px;
      margin-right: 6px;
      font-size: .7rem;
      border: 1px solid currentColor;
    }
    .body { padding: 22px 22px 18px; }
    h2 { margin: 0 0 6px; font-size: 1.2rem; color: var(--nile); }
    .lead { margin: 0 0 18px; color: var(--muted); font-size: .92rem; line-height: 1.5; }
    .grid { display: grid; gap: 12px; }
    .grid.two { grid-template-columns: 1fr 1fr; }
    label { display: block; font-size: .78rem; font-weight: 600; color: #334155; margin-bottom: 6px; }
    input[type=text], input[type=password], input[type=url], input[type=number] {
      width: 100%;
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 12px 14px;
      font: inherit;
      font-size: .92rem;
      outline: none;
      transition: border-color .15s, box-shadow .15s;
    }
    input:focus {
      border-color: var(--blue);
      box-shadow: 0 0 0 3px rgba(5,84,152,.15);
    }
    .check {
      display: flex; align-items: flex-start; gap: 10px;
      padding: 12px 14px;
      border: 1px solid var(--line);
      border-radius: 12px;
      background: #f8fafc;
    }
    .check input { margin-top: 3px; }
    .check strong { display: block; font-size: .9rem; }
    .check small { color: var(--muted); }
    .list { display: grid; gap: 8px; margin: 14px 0; }
    .row {
      display: flex; justify-content: space-between; gap: 12px; align-items: center;
      padding: 10px 12px; border-radius: 10px; background: #f8fafc; border: 1px solid var(--line);
      font-size: .86rem;
    }
    .badge {
      font-size: .72rem; font-weight: 700; padding: 4px 8px; border-radius: 999px;
    }
    .badge.ok { background: #d1fae5; color: #065f46; }
    .badge.fail { background: #fee2e2; color: #991b1b; }
    .actions {
      display: flex; flex-wrap: wrap; gap: 10px; justify-content: flex-end;
      margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--line);
    }
    button {
      appearance: none; border: 0; cursor: pointer;
      border-radius: 12px; padding: 12px 18px;
      font: inherit; font-weight: 600; font-size: .9rem;
      min-height: 44px;
    }
    .btn-primary {
      color: #fff;
      background: linear-gradient(135deg, var(--blue) 0%, var(--nile) 100%);
      box-shadow: 0 10px 24px rgba(5,84,152,.28);
    }
    .btn-primary:disabled { opacity: .55; cursor: not-allowed; }
    .btn-ghost {
      background: #fff; color: var(--nile); border: 1px solid var(--line);
    }
    .alert {
      display: none;
      margin: 0 0 14px;
      padding: 12px 14px;
      border-radius: 12px;
      font-size: .88rem;
      line-height: 1.45;
    }
    .alert.show { display: block; }
    .alert.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .alert.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert.info { background: #eff6ff; color: #1e3a8a; border: 1px solid #bfdbfe; }
    .panel { display: none; }
    .panel.active { display: block; }
    .done-box {
      text-align: center; padding: 18px 8px 8px;
    }
    .done-box .icon {
      width: 64px; height: 64px; margin: 0 auto 12px; border-radius: 999px;
      display: grid; place-items: center;
      background: linear-gradient(135deg, var(--blue), var(--nile));
      color: #fff; font-size: 1.6rem; font-weight: 700;
    }
    .links { display: grid; gap: 10px; margin-top: 18px; }
    .links a {
      display: block; text-decoration: none; color: var(--nile);
      border: 1px solid var(--line); border-radius: 12px; padding: 14px;
      font-weight: 600; background: #f8fafc;
    }
    .links a:hover { border-color: var(--blue); background: #fff; }
    .spinner {
      width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.35);
      border-top-color: #fff; border-radius: 50%; display: inline-block;
      animation: spin .7s linear infinite; vertical-align: -3px; margin-right: 8px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    @media (max-width: 720px) {
      .grid.two { grid-template-columns: 1fr; }
      .steps { grid-template-columns: 1fr 1fr; }
      .step-tab { border-bottom: 1px solid var(--line); font-size: .68rem; }
      .body { padding: 18px 14px; }
    }
  </style>
</head>
<body>
  <div class="shell">
    <div class="brand">
      <div class="brand-mark">D</div>
      <div>
        <h1>DAPE-MA Installer</h1>
        <p>Web setup for Laravel + MySQL on this server</p>
      </div>
    </div>

    <div class="card" id="app" data-installed="<?= $installed ? '1' : '0' ?>">
      <div class="steps" id="stepTabs">
        <div class="step-tab active" data-step="1"><span>1</span>Requirements</div>
        <div class="step-tab" data-step="2"><span>2</span>App</div>
        <div class="step-tab" data-step="3"><span>3</span>Database</div>
        <div class="step-tab" data-step="4"><span>4</span>Finish</div>
      </div>

      <div class="body">
        <div id="alert" class="alert" role="alert"></div>

        <?php if ($installed): ?>
          <div class="done-box">
            <div class="icon">✓</div>
            <h2>Already installed</h2>
            <p class="lead">This server has a completed install lock. Open the admin panel or API.</p>
            <div class="links">
              <a href="/admin/login">Go to Admin Login</a>
              <a href="/api/v1/health">Check API health</a>
            </div>
          </div>
        <?php else: ?>

        <section class="panel active" data-panel="1">
          <h2>Server requirements</h2>
          <p class="lead">Checking PHP, extensions, Composer vendor, and writable folders.</p>
          <div class="list" id="reqList"><div class="row">Loading…</div></div>
          <div class="actions">
            <button type="button" class="btn-ghost" id="btnRecheck">Recheck</button>
            <button type="button" class="btn-primary" id="btnToApp" disabled>Continue</button>
          </div>
        </section>

        <section class="panel" data-panel="2">
          <h2>Application</h2>
          <p class="lead">Public URL used for links, Sanctum, and storage media.</p>
          <div class="grid">
            <div>
              <label for="app_name">App name</label>
              <input id="app_name" type="text" value="DAPE-MA" autocomplete="organization">
            </div>
            <div>
              <label for="app_url">App URL</label>
              <input id="app_url" type="url" value="<?= htmlspecialchars($suggestedUrl, ENT_QUOTES) ?>" placeholder="https://your-domain.com">
            </div>
            <label class="check">
              <input type="checkbox" id="seed_demo" checked>
              <span>
                <strong>Seed demo data & accounts</strong>
                <small>Includes categories, sample content, and demo users (password: password).</small>
              </span>
            </label>
          </div>
          <div class="actions">
            <button type="button" class="btn-ghost" data-back="1">Back</button>
            <button type="button" class="btn-primary" id="btnToDb">Continue</button>
          </div>
        </section>

        <section class="panel" data-panel="3">
          <h2>MySQL database</h2>
          <p class="lead">Use an existing database user, or create one with MySQL root.</p>
          <div class="grid two">
            <div>
              <label for="db_host">DB host</label>
              <input id="db_host" type="text" value="127.0.0.1">
            </div>
            <div>
              <label for="db_port">DB port</label>
              <input id="db_port" type="number" value="3306">
            </div>
            <div>
              <label for="db_database">Database name</label>
              <input id="db_database" type="text" value="DAPE_MA">
            </div>
            <div>
              <label for="db_username">DB username</label>
              <input id="db_username" type="text" value="dape_ma_user">
            </div>
            <div style="grid-column: 1 / -1;">
              <label for="db_password">DB password</label>
              <input id="db_password" type="password" value="" autocomplete="new-password">
            </div>
          </div>
          <label class="check" style="margin-top:12px;">
            <input type="checkbox" id="create_database">
            <span>
              <strong>Create database & user with MySQL root</strong>
              <small>Needed on a fresh MySQL install if the app user does not exist yet.</small>
            </span>
          </label>
          <div class="grid two" id="rootFields" style="display:none; margin-top:12px;">
            <div>
              <label for="db_root_username">MySQL root user</label>
              <input id="db_root_username" type="text" value="root">
            </div>
            <div>
              <label for="db_root_password">MySQL root password</label>
              <input id="db_root_password" type="password" value="" autocomplete="current-password">
            </div>
          </div>
          <div class="actions">
            <button type="button" class="btn-ghost" data-back="2">Back</button>
            <button type="button" class="btn-ghost" id="btnTestDb">Test connection</button>
            <button type="button" class="btn-primary" id="btnInstall">Install now</button>
          </div>
        </section>

        <section class="panel" data-panel="4">
          <div class="done-box">
            <div class="icon">✓</div>
            <h2>Installation complete</h2>
            <p class="lead" id="finishLead">DAPE-MA is ready on this server.</p>
            <div class="alert info show" id="finishNote"></div>
            <div class="links">
              <a id="adminLink" href="/admin/login">Open Admin Login</a>
              <a id="apiLink" href="/api/v1/health">Open API health</a>
            </div>
            <p class="lead" style="margin-top:16px;">For security, delete or lock down <code>public/install</code> after verifying login.</p>
          </div>
        </section>

        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if (! $installed): ?>
  <script>
    const alertEl = document.getElementById('alert');
    let current = 1;
    let requirementsOk = false;

    function showAlert(type, message) {
      alertEl.className = 'alert show ' + type;
      alertEl.textContent = message;
    }
    function clearAlert() {
      alertEl.className = 'alert';
      alertEl.textContent = '';
    }
    function goStep(n) {
      current = n;
      document.querySelectorAll('.panel').forEach(p => {
        p.classList.toggle('active', Number(p.dataset.panel) === n);
      });
      document.querySelectorAll('.step-tab').forEach(tab => {
        const s = Number(tab.dataset.step);
        tab.classList.toggle('active', s === n);
        tab.classList.toggle('done', s < n);
      });
      clearAlert();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    async function api(action, payload = {}) {
      const res = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ action, ...payload }),
      });
      const data = await res.json().catch(() => ({ status: false, message: 'Invalid server response.' }));
      if (!res.ok || data.status === false) {
        throw new Error(data.message || 'Request failed.');
      }
      return data;
    }
    function badge(ok) {
      return `<span class="badge ${ok ? 'ok' : 'fail'}">${ok ? 'OK' : 'FAIL'}</span>`;
    }
    async function loadRequirements() {
      const data = await api('requirements');
      const c = data.data;
      const rows = [];
      rows.push(`<div class="row"><span>PHP ${c.php_version} (≥ 8.2)</span>${badge(c.php_ok)}</div>`);
      rows.push(`<div class="row"><span>Composer vendor/</span>${badge(c.vendor_ok)}</div>`);
      rows.push(`<div class="row"><span>artisan</span>${badge(c.artisan_ok)}</div>`);
      Object.entries(c.extensions).forEach(([name, ok]) => {
        rows.push(`<div class="row"><span>ext-${name}</span>${badge(ok)}</div>`);
      });
      Object.entries(c.paths).forEach(([name, ok]) => {
        rows.push(`<div class="row"><span>${name}</span>${badge(ok)}</div>`);
      });
      document.getElementById('reqList').innerHTML = rows.join('');
      requirementsOk = !!c.ready;
      document.getElementById('btnToApp').disabled = !requirementsOk;
      if (!requirementsOk) showAlert('error', data.message);
      else showAlert('success', data.message);
    }
    function formPayload() {
      return {
        app_name: document.getElementById('app_name').value.trim(),
        app_url: document.getElementById('app_url').value.trim().replace(/\/$/, ''),
        seed_demo: document.getElementById('seed_demo').checked,
        db_host: document.getElementById('db_host').value.trim(),
        db_port: document.getElementById('db_port').value.trim(),
        db_database: document.getElementById('db_database').value.trim(),
        db_username: document.getElementById('db_username').value.trim(),
        db_password: document.getElementById('db_password').value,
        create_database: document.getElementById('create_database').checked,
        db_root_username: document.getElementById('db_root_username').value.trim(),
        db_root_password: document.getElementById('db_root_password').value,
      };
    }

    document.getElementById('btnRecheck').addEventListener('click', () => {
      loadRequirements().catch(e => showAlert('error', e.message));
    });
    document.getElementById('btnToApp').addEventListener('click', () => {
      if (requirementsOk) goStep(2);
    });
    document.getElementById('btnToDb').addEventListener('click', () => {
      const url = document.getElementById('app_url').value.trim();
      if (!url) return showAlert('error', 'App URL is required.');
      goStep(3);
    });
    document.querySelectorAll('[data-back]').forEach(btn => {
      btn.addEventListener('click', () => goStep(Number(btn.dataset.back)));
    });
    document.getElementById('create_database').addEventListener('change', (e) => {
      document.getElementById('rootFields').style.display = e.target.checked ? 'grid' : 'none';
    });
    document.getElementById('btnTestDb').addEventListener('click', async () => {
      clearAlert();
      const btn = document.getElementById('btnTestDb');
      btn.disabled = true;
      try {
        const data = await api('test_database', formPayload());
        showAlert('success', data.message);
      } catch (e) {
        showAlert('error', e.message);
      } finally {
        btn.disabled = false;
      }
    });
    document.getElementById('btnInstall').addEventListener('click', async () => {
      clearAlert();
      const btn = document.getElementById('btnInstall');
      const original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner"></span>Installing…';
      try {
        const data = await api('install', formPayload());
        document.getElementById('finishLead').textContent = data.message;
        document.getElementById('finishNote').textContent = data.data.demo_note || '';
        document.getElementById('adminLink').href = data.data.admin_url;
        document.getElementById('apiLink').href = data.data.api_url;
        goStep(4);
        showAlert('success', 'All set.');
      } catch (e) {
        showAlert('error', e.message);
      } finally {
        btn.disabled = false;
        btn.innerHTML = original;
      }
    });

    loadRequirements().catch(e => showAlert('error', e.message));
  </script>
  <?php endif; ?>
</body>
</html>

<?php
declare(strict_types=1);

// Keep the authenticated snapshot for this request, but do not lock other tabs
// while a read, report or external assistant call is running.
function session_identity176(array $state): array
{
    $u = $state['user'] ?? [];
    return [(string)($state['csrf'] ?? ''), (int)($u['id'] ?? 0), (string)($u['role'] ?? ''), (int)($u['tenant_id'] ?? 0)];
}

function session_release176(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) return;
    csrf_token();
    // Flash messages are consumed while the page is rendered, after this
    // session has been released. Remove them from the stored session now and
    // keep a request-local copy for render_flashes().
    $pending = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    $GLOBALS['fisitaap_read_session176'] = ['id'=>session_id(), 'identity'=>session_identity176($_SESSION)];
    $GLOBALS['fisitaap_read_session176']['flash'] = is_array($pending) ? $pending : [];
    session_write_close();
}

// Persist only the requested small change against the latest session. Never
// merge an entire stale snapshot: that could restore a logged-out user or lose
// messages written by another request. A rotated/deleted session is rejected.
function session_change176(callable $change): mixed
{
    if (session_status() === PHP_SESSION_ACTIVE) return $change();
    $read = $GLOBALS['fisitaap_read_session176'] ?? null;
    if (!$read) return null;
    // Once HTML/JSON has started, PHP cannot safely reopen the session
    // headers. Keep non-critical request-local changes in memory; critical
    // mutations happen before release or through the normal POST lifecycle.
    if (headers_sent()) return $change();
    $snapshot = $_SESSION;
    session_id($read['id']);
    if (!session_start(['use_cookies'=>false, 'use_strict_mode'=>true, 'cache_limiter'=>''])) {
        $_SESSION = $snapshot;
        return null;
    }
    if (session_id() !== $read['id'] || session_identity176($_SESSION) !== $read['identity']) {
        session_abort();
        $_SESSION = $snapshot;
        return null;
    }
    try {
        $result = $change();
        $latest = $_SESSION;
        session_write_close();
        $_SESSION = $latest;
        return $result;
    } catch (Throwable $ex) {
        session_abort();
        $_SESSION = $snapshot;
        throw $ex;
    }
}

function session_take176(string $key): mixed
{
    if (session_status() !== PHP_SESSION_ACTIVE && $key === 'flash' && isset($GLOBALS['fisitaap_read_session176'])) {
        $value = $GLOBALS['fisitaap_read_session176']['flash'] ?? [];
        $GLOBALS['fisitaap_read_session176']['flash'] = [];
        return $value;
    }
    return session_change176(function () use ($key) {
        $value = $_SESSION[$key] ?? null;
        unset($_SESSION[$key]);
        return $value;
    });
}

function session_read_route176(string $path): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;
    // Login, registration, customer pages and POST mutations retain their
    // existing session lifecycle. Release administrative reads after the fresh
    // database authentication/permission guard has run.
    if ($path === 'admin' || str_starts_with($path, 'admin/') || $path === 'master'
        || str_starts_with($path, 'master/') || $path === 'api/pos-options') session_release176();
}

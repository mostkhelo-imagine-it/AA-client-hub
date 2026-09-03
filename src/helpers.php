<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function base_url(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . base_url($path));
    exit;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

function old(string $key, string $default = ''): string
{
    return e($_SESSION['old'][$key] ?? $default);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', is_string($token) ? $token : '')) {
        http_response_code(419);
        exit('Session expired — go back and try again.');
    }
}

/** Render a view file with the given data, wrapped in the shared layout. */
function render(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $viewFile = dirname(__DIR__) . '/src/views/' . $view . '.php';
    $currentUser = Auth::user();
    ob_start();
    require $viewFile;
    $content = ob_get_clean();
    require dirname(__DIR__) . '/src/views/layout/app.php';
}

/** Render a bare view (no layout) — used for the login screen. */
function render_bare(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require dirname(__DIR__) . '/src/views/' . $view . '.php';
}

/**
 * Renders a <select> of CSV column names for the import mapping screen.
 * @param array<int,string> $header
 */
function import_select(array $header, string $name, ?string $selected, bool $required = false): void
{
    echo '<select id="' . e(str_replace(['[', ']'], ['_', ''], $name)) . '" name="' . e($name) . '"' . ($required ? ' required' : '') . '>';
    echo '<option value="">' . ($required ? '— choose a column —' : "— don't import —") . '</option>';
    foreach ($header as $col) {
        $isSelected = $selected !== null && $selected === $col;
        echo '<option value="' . e($col) . '"' . ($isSelected ? ' selected' : '') . '>' . e($col) . '</option>';
    }
    echo '</select>';
}

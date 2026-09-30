<?php
/**
 * CodeQuest test runner.
 *
 * The project deliberately ships without Composer or any test framework -- the
 * README states the app must run with "no Composer, no npm, no internet
 * required at runtime". Adding PHPUnit would break that property, so this is a
 * small self-contained runner instead: no dependencies, no network, no
 * database.
 *
 * Run with:
 *     php tests/run.php
 */

declare(strict_types=1);

$GLOBALS['__passed'] = 0;
$GLOBALS['__failed'] = 0;
$GLOBALS['__failures'] = [];
$GLOBALS['__group'] = '';

function group(string $name): void
{
    $GLOBALS['__group'] = $name;
    echo "\n{$name}\n";
}

function ok(bool $cond, string $label, string $detail = ''): void
{
    if ($cond) {
        $GLOBALS['__passed']++;
        echo "  PASS  {$label}\n";
    } else {
        $GLOBALS['__failed']++;
        $GLOBALS['__failures'][] = $GLOBALS['__group'] . ' :: ' . $label
            . ($detail !== '' ? " ({$detail})" : '');
        echo "  FAIL  {$label}" . ($detail !== '' ? "  <{$detail}>" : '') . "\n";
    }
}

function same($expected, $actual, string $label): void
{
    ok(
        $expected === $actual,
        $label,
        $expected === $actual ? '' : 'expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
    );
}

require_once dirname(__DIR__) . '/includes/helpers.php';

// ---------------------------------------------------------------- e() -------
group('e() — HTML escaping');

same('&lt;script&gt;', e('<script>'), 'escapes angle brackets');
same('&quot;', e('"'), 'escapes double quotes');
same('&#039;', e("'"), 'escapes single quotes');
same('a &amp; b', e('a & b'), 'escapes ampersands');
same('plain', e('plain'), 'leaves plain text alone');
ok(strlen(e('<b>')) > 4, 'output is longer than the raw input');

// ------------------------------------------------------ sanitize_input() -----
group('sanitize_input() — input trimming');

same('hello', sanitize_input('hello'), 'leaves a clean value alone');
same('hello', sanitize_input('  hello  '), 'trims surrounding whitespace');
same("a\nb", sanitize_input("a\nb"), 'preserves internal whitespace');
same("a\tb", sanitize_input("a\tb"), 'keeps internal tabs and spaces');

// --------------------------------------------------------- csrf_token() ------
group('CSRF tokens');

$token = csrf_token();
ok(strlen($token) === 64, 'token is 64 hex chars', 'len=' . strlen($token));
ok((bool) preg_match('/^[0-9a-f]{64}$/', $token), 'token is lowercase hex');
same($token, csrf_token(), 'token is stable within a session');
ok(str_contains(csrf_field(), 'name="csrf_token"'), 'csrf_field emits the token input');
ok(str_contains(csrf_field(), $token), 'csrf_field embeds the current token');

// ------------------------------------------------------- verify_csrf() -------
group('verify_csrf()');

$_POST = ['csrf_token' => $token];
ok(verify_csrf(), 'accepts the matching POST token');

$_POST = ['csrf_token' => str_repeat('0', 64)];
ok(!verify_csrf(), 'rejects a wrong POST token');

$_POST = [];
$_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
ok(verify_csrf(), 'accepts the token from the X-CSRF-Token header');

$_POST = [];
unset($_SERVER['HTTP_X_CSRF_TOKEN']);
ok(!verify_csrf(), 'rejects a missing token');

$_POST = [];
$_SERVER['HTTP_X_CSRF_TOKEN'] = substr($token, 0, 63);
ok(!verify_csrf(), 'rejects a truncated token');
unset($_SERVER['HTTP_X_CSRF_TOKEN']);

// ------------------------------------------------------------- flash ---------
group('flash messages');

same(null, get_flash(), 'no flash set initially');
set_flash('success', 'Saved');
$f = get_flash();
ok(is_array($f) && $f['msg'] === 'Saved', 'flash round-trips its message');
same(null, get_flash(), 'flash is consumed on read');

set_flash('error', '<b>bad</b>');
$html = flash_html();
ok(str_contains($html, 'alert-danger'), 'error flash uses the danger class');
ok(str_contains($html, '&lt;b&gt;'), 'flash message is HTML-escaped');

// ------------------------------------------------------------- auth ----------
group('session auth predicates');

$_SESSION = [];
ok(!is_admin_logged(), 'admin is logged out when the session is empty');
ok(!is_candidate_logged(), 'candidate is logged out when the session is empty');

$_SESSION['admin_id'] = 7;
ok(is_admin_logged(), 'admin is logged in once admin_id is set');
ok(!is_candidate_logged(), 'admin session does not imply candidate login');

$_SESSION = ['candidate_id' => 3];
ok(is_candidate_logged(), 'candidate is logged in once candidate_id is set');
ok(!is_admin_logged(), 'candidate session does not imply admin login');

$_SESSION = [];

// ------------------------------------------------------------ summary --------
$passed = $GLOBALS['__passed'];
$failed = $GLOBALS['__failed'];

echo "\n" . str_repeat('-', 52) . "\n";
printf("%d passed, %d failed\n", $passed, $failed);

if ($failed > 0) {
    echo "\nFailures:\n";
    foreach ($GLOBALS['__failures'] as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
}

exit(0);

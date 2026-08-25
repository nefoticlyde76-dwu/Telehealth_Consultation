<?php

/**
 * Wallet-hero sparkline and allocation ring helpers.
 *
 * Usage: php bin/test_wallet_heroes.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Helpers\DashboardHero;
use App\Helpers\Status;

Environment::load(dirname(__DIR__) . '/.env');

$failed = 0;
$passed = 0;

function expect_true(bool $condition, string $label): void
{
    global $failed, $passed;
    if ($condition) {
        $passed++;
        echo "PASS  {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL  {$label}\n";
}

$emptySpark = DashboardHero::sparklinePaths([]);
expect_true(str_starts_with($emptySpark['line'], 'M'), 'Empty series still produces a move command');
expect_true(str_contains($emptySpark['area'], 'V40 H0 Z'), 'Area path closes against the baseline');

$rising = DashboardHero::sparklinePaths([1, 2, 3, 4]);
expect_true(substr_count($rising['line'], 'L') === 3, 'Four points produce three line segments');
expect_true(!str_contains($rising['line'], 'NaN'), 'Sparkline path has no NaN coordinates');

$deltaUp = DashboardHero::seriesDelta([10, 12]);
expect_true($deltaUp['up'] === true && $deltaUp['percent'] === 20.0, 'Rising series reports +20%');

$deltaDown = DashboardHero::seriesDelta([10, 5]);
expect_true($deltaDown['up'] === false && $deltaDown['percent'] === -50.0, 'Falling series reports -50%');

$deltaFlat = DashboardHero::seriesDelta([0, 0]);
expect_true($deltaFlat['flat'] === true, 'Zero-to-zero series is flat');

$fromZero = DashboardHero::seriesDelta([0, 4]);
expect_true($fromZero['percent'] === 100.0 && $fromZero['up'] === true, 'Zero previous with new activity is +100%');

$chart = Status::consultationDistributionChart([
    'Pending' => 55,
    'Approved' => 30,
    'Completed' => 15,
]);
$slices = DashboardHero::statusSlices($chart);
expect_true($slices['total'] === 100, 'Status slices total the distribution');
expect_true(str_contains($slices['gradient'], Status::chartColor(Status::PENDING) . ' 0%'), 'Ring starts with the Pending status colour');
expect_true(str_ends_with($slices['gradient'], '100%'), 'Ring gradient closes at 100%');

$emptySlices = DashboardHero::statusSlices(Status::consultationDistributionChart([]));
expect_true($emptySlices['total'] === 0, 'Empty distribution totals zero');
expect_true($emptySlices['gradient'] === '#D9E2EC 0% 100%', 'Empty ring uses the muted canvas border stop');

$hero = DashboardHero::fromCharts(
    [
        'monthly_requests' => [
            'data' => [
                'labels' => ['Mar', 'Apr'],
                'datasets' => [['data' => [8, 10]]],
            ],
        ],
        'status_distribution' => $chart,
    ],
    'patient'
);

expect_true($hero['balance'] === '100', 'Balance card uses the caseload total');
expect_true($hero['delta_text'] === '+25%', 'Monthly sparkline delta is last vs previous month');
expect_true($hero['delta_period'] === 'past 6 months', 'Patient monthly series is labelled as 6 months');
expect_true(count($hero['assets']) === 3, 'Balance card shows Pending, Approved, Completed micro bars');
expect_true(count($hero['actions']) === 3, 'Allocation card has three action links');
expect_true($hero['actions'][0]['url'] === '/patient/available-slots', 'Patient primary action is book');

$adminHero = DashboardHero::fromCharts(['weekly_requests' => ['data' => ['datasets' => [['data' => [1, 2, 3]]]]]], 'admin');
expect_true($adminHero['delta_period'] === 'past 7 days', 'Admin weekly series is labelled as 7 days');
expect_true($adminHero['actions'][0]['url'] === Status::filteredListUrl('/admin/consultation-requests', Status::PENDING), 'Admin primary action is the pending queue');

$markup = file_get_contents(dirname(__DIR__) . '/app/Views/partials/dashboard/wallet_heroes.php');
expect_true(is_string($markup) && str_contains($markup, 'pathLength="340"'), 'Sparkline uses pathLength so dash animation matches any series');
expect_true(is_string($markup) && str_contains($markup, 'glz-05b__ring'), 'Allocation ring markup is present');
expect_true(!str_contains((string) $markup, 'chart.js') && !str_contains((string) $markup, 'data-chart'), 'Hero partial does not use a chart library');

$css = file_get_contents(dirname(__DIR__) . '/public/css/wallet-heroes.css');
expect_true(is_string($css) && str_contains($css, 'conic-gradient'), 'Allocation ring is a CSS conic-gradient donut');
expect_true(is_string($css) && str_contains($css, 'stroke-dashoffset'), 'Sparkline draws in via stroke-dashoffset');
expect_true(is_string($css) && str_contains($css, 'prefers-reduced-motion'), 'Animations honour reduced motion');
expect_true(!str_contains((string) $css, 'Chivo Mono') && !str_contains((string) $css, 'Unbounded'), 'Hero cards use dashboard fonts, not wallet demo fonts');
expect_true(!str_contains((string) $css, 'backdrop-filter'), 'Hero cards do not use glassmorphism');

$baseUrl = rtrim((string) Environment::get('APP_URL', 'http://localhost/Telehealth_Consultation_System/public'), '/');
$cookieFile = dirname(__DIR__) . '/tmp/wallet-heroes-http.cookie';
if (!is_dir(dirname($cookieFile))) {
    mkdir(dirname($cookieFile), 0775, true);
}

$httpGet = static function (string $url, string $cookies, bool $post = false, array $fields = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_COOKIEJAR => $cookies,
        CURLOPT_COOKIEFILE => $cookies,
    ]);
    if ($post) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => $body];
};

$cssHttp = $httpGet($baseUrl . '/css/wallet-heroes.css', $cookieFile);
expect_true($cssHttp['status'] === 200 && str_contains($cssHttp['body'], 'glz-05a__line'), 'Apache serves wallet-heroes.css');

$login = $httpGet($baseUrl . '/login', $cookieFile);
$token = '';
if (preg_match('/name="_token"\s+value="([^"]+)"/', $login['body'], $match)) {
    $token = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
}

$auth = $httpGet($baseUrl . '/login', $cookieFile, true, [
    '_token' => $token,
    'email' => 'week9.admin@mbpha.test',
    'password' => 'Week9!Admin',
]);
$dash = $httpGet($baseUrl . '/admin/dashboard', $cookieFile);
$onDashboard = $auth['status'] === 200 && $dash['status'] === 200 && !str_contains($dash['body'], 'name="password"');
if ($onDashboard) {
    expect_true(str_contains($dash['body'], 'wallet-heroes.css'), 'Admin dashboard loads wallet-heroes.css');
    expect_true(str_contains($dash['body'], 'glz-05a__card') && str_contains($dash['body'], 'glz-05b__ring'), 'Admin dashboard renders both hero cards');
    expect_true(str_contains($dash['body'], 'glz-05a__line') && str_contains($dash['body'], '--glz-05b-stops'), 'Sparkline path and status ring stops are in the HTML');
    expect_true(!str_contains($dash['body'], 'Bitcoin'), 'Hero cards are bound to consultation data, not crypto demo copy');
} else {
    echo "SKIP  Live dashboard login (week9.admin@mbpha.test not available)\n";
}

if (is_file($cookieFile)) {
    unlink($cookieFile);
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);

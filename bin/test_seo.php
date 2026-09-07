<?php

/**
 * SEO foundation checks for public pages and private-page exclusion.
 *
 * Usage: php bin/test_seo.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Helpers\Seo;

$failed = 0;
$passed = 0;
$projectRoot = dirname(__DIR__);

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

$home = Seo::forPath('/');
expect_true(
    $home['title'] === 'MBPHA TeleHealth | Online Healthcare Consultations in Papua New Guinea',
    'Homepage title matches the public SEO title'
);
expect_true(
    $home['description'] === 'MBPHA TeleHealth provides secure online healthcare consultations, connecting patients with healthcare professionals through a convenient digital platform in Papua New Guinea.',
    'Homepage meta description matches the approved copy'
);
expect_true($home['canonical'] === 'https://mbphatelehealth.com/', 'Homepage canonical is the production origin with a trailing slash');
expect_true($home['robots'] === null, 'Homepage remains indexable');
expect_true($home['openGraph'] === true, 'Homepage emits Open Graph tags');
expect_true(is_array($home['jsonLd']) && $home['jsonLd'] !== [], 'Homepage includes JSON-LD');

$json = Seo::encodeJsonLd($home['jsonLd'] ?? []);
$decoded = json_decode($json, true);
expect_true(is_array($decoded), 'Homepage JSON-LD is valid JSON');
expect_true(($decoded['@context'] ?? '') === 'https://schema.org', 'JSON-LD uses schema.org');
expect_true(($decoded['@graph'][0]['name'] ?? '') === 'MBPHA TeleHealth', 'JSON-LD organization name is MBPHA TeleHealth');
expect_true(($decoded['@graph'][0]['url'] ?? '') === 'https://mbphatelehealth.com/', 'JSON-LD organization URL is the production homepage');

$about = Seo::forPath('/about');
expect_true($about['description'] !== $home['description'], 'About page does not reuse the homepage description');
expect_true($about['canonical'] === 'https://mbphatelehealth.com/about', 'About canonical has no trailing slash');

$login = Seo::forPath('/login');
expect_true($login['robots'] === 'noindex, nofollow', 'Login is noindex');
expect_true($login['openGraph'] === false, 'Login does not emit Open Graph tags');
expect_true($login['canonical'] === null, 'Login has no public canonical URL');

$reset = Seo::forPath('/reset-password?token=secret-token');
expect_true($reset['robots'] === 'noindex, nofollow', 'Password-reset pages are noindex even with a token query');
expect_true($reset['canonical'] === null, 'Password-reset tokens are not written into a canonical URL');

foreach (['/admin/dashboard', '/doctor/dashboard', '/patient/dashboard', '/notifications', '/account/security', '/patient/consultation-requests/12'] as $privatePath) {
    expect_true(Seo::shouldNoIndexPath($privatePath), 'Private path is excluded from indexing: ' . $privatePath);
    expect_true(!Seo::isIndexablePath($privatePath), 'Private path is not in the public catalog: ' . $privatePath);
}

$robotsPath = $projectRoot . '/public/robots.txt';
expect_true(is_file($robotsPath), 'public/robots.txt exists');
$robots = is_file($robotsPath) ? (string) file_get_contents($robotsPath) : '';
expect_true(str_contains($robots, "User-agent: *\n"), 'robots.txt declares User-agent: *');
expect_true(preg_match('/^Allow: \/$/m', $robots) === 1, 'robots.txt allows /');
expect_true(str_contains($robots, 'Sitemap: https://mbphatelehealth.com/sitemap.xml'), 'robots.txt points to the production sitemap');
foreach (['/admin', '/doctor', '/patient', '/account', '/notifications', '/auth/'] as $disallow) {
    expect_true(preg_match('/^Disallow: ' . preg_quote($disallow, '/') . '$/m', $robots) === 1, 'robots.txt disallows ' . $disallow);
}

$sitemapPath = $projectRoot . '/public/sitemap.xml';
expect_true(is_file($sitemapPath), 'public/sitemap.xml exists');
$sitemapXml = is_file($sitemapPath) ? (string) file_get_contents($sitemapPath) : '';
$previous = libxml_use_internal_errors(true);
$sitemap = simplexml_load_string($sitemapXml);
$xmlErrors = libxml_get_errors();
libxml_clear_errors();
libxml_use_internal_errors($previous);
expect_true($sitemap !== false && $xmlErrors === [], 'sitemap.xml is well-formed XML');

$locs = [];
if ($sitemap !== false) {
    foreach ($sitemap->url as $url) {
        $locs[] = (string) $url->loc;
    }
}

expect_true($locs === Seo::sitemapUrls(), 'sitemap.xml URLs match the Seo helper catalog');
expect_true(in_array('https://mbphatelehealth.com/', $locs, true), 'sitemap.xml includes the homepage');
foreach (['/admin', '/doctor/dashboard', '/patient/dashboard', '/login', '/register', '/notifications', '/account'] as $blocked) {
    $found = false;
    foreach ($locs as $loc) {
        if (str_contains($loc, $blocked)) {
            $found = true;
            break;
        }
    }
    expect_true(!$found, 'sitemap.xml does not include ' . $blocked);
}

$appLayout = (string) file_get_contents($projectRoot . '/app/Views/layouts/app.php');
$dashboardLayout = (string) file_get_contents($projectRoot . '/app/Views/layouts/dashboard.php');
expect_true(substr_count($appLayout, '<title') === 0, 'Public layout does not hardcode a second title tag');
expect_true(str_contains($appLayout, 'partials/public/seo_head.php'), 'Public layout includes the shared SEO partial');
expect_true(str_contains($dashboardLayout, '<meta name="robots" content="noindex, nofollow">'), 'Dashboard layout always sends noindex');
expect_true(!str_contains($dashboardLayout, 'robotsNoIndex'), 'Unused robotsNoIndex flag is no longer required');

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = 'mbphatelehealth.com';
$title = $home['title'];
$metaDescription = $home['description'];
ob_start();
require $projectRoot . '/app/Views/partials/public/seo_head.php';
$headHtml = (string) ob_get_clean();

expect_true(substr_count($headHtml, '<title>') === 1, 'Rendered homepage head has exactly one title');
expect_true(str_contains($headHtml, $home['title']), 'Rendered homepage head contains the SEO title');
expect_true(str_contains($headHtml, 'name="description"'), 'Rendered homepage head contains a meta description');
expect_true(str_contains($headHtml, 'rel="canonical"'), 'Rendered homepage head contains a canonical link');
expect_true(str_contains($headHtml, 'property="og:title"'), 'Rendered homepage head contains og:title');
expect_true(str_contains($headHtml, 'property="og:description"'), 'Rendered homepage head contains og:description');
expect_true(str_contains($headHtml, 'property="og:url"'), 'Rendered homepage head contains og:url');
expect_true(str_contains($headHtml, 'property="og:type"'), 'Rendered homepage head contains og:type');
expect_true(str_contains($headHtml, 'property="og:site_name"'), 'Rendered homepage head contains og:site_name');
expect_true(str_contains($headHtml, 'property="og:image"'), 'Rendered homepage head contains og:image');
expect_true(str_contains($headHtml, 'name="twitter:card"'), 'Rendered homepage head contains twitter:card');
expect_true(str_contains($headHtml, 'name="twitter:image"'), 'Rendered homepage head contains twitter:image');
expect_true(str_contains($headHtml, 'application/ld+json'), 'Rendered homepage head contains JSON-LD');
expect_true(str_contains($headHtml, 'images/LOGOS.png'), 'Social image uses the existing public logo');
expect_true(!str_contains($headHtml, 'name="robots"'), 'Rendered homepage head does not include a robots noindex tag');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);

import { runCLI } from '@wp-playground/cli';
import { mkdir, readFile, writeFile, access } from 'node:fs/promises';
import { resolve } from 'node:path';

const testing = process.argv.includes('--test');
await mkdir('.runtime', { recursive: true });
if (!testing) await mkdir('.runtime/wordpress', { recursive: true });
const existing = !testing && await access('.runtime/wordpress/wp-load.php').then(() => true, () => false);
const server = await runCLI({
  command: 'server', php: '8.3', wp: 'https://wordpress.org/wordpress-7.1.zip', port: testing ? 9401 : 9400,
  login: false, workers: 1,
  'define-bool': { WP_HTTP_BLOCK_EXTERNAL: true, DISABLE_WP_CRON: true },
  ...(!testing ? { 'mount-before-install': [{hostPath:resolve('.runtime/wordpress'),vfsPath:'/wordpress'}], wordpressInstallMode: existing ? 'do-not-attempt-installing' : 'download-and-install' } : {}),
  mount: [
    { hostPath: resolve('content/policies'), vfsPath: '/cleanup-policies' },
    { hostPath: resolve('content/initial'), vfsPath: '/cleanup-initial' },
    ...(testing ? [{hostPath:resolve('tests'),vfsPath:'/cleanup-tests'}] : []),
    { hostPath: resolve('wp-content/plugins/cleanup-core'), vfsPath: '/wordpress/wp-content/plugins/cleanup-core' },
    { hostPath: resolve('wp-content/themes/cleanup-portal'), vfsPath: '/wordpress/wp-content/themes/cleanup-portal' },
  ],
  blueprint: { steps: [
    { step: 'activatePlugin', pluginPath: '/wordpress/wp-content/plugins/cleanup-core/cleanup-core.php' },
    { step: 'activateTheme', themeFolderName: 'cleanup-portal' },
    { step: 'runPHP', code: `<?php require '/wordpress/wp-load.php';
      update_option('blogname', '不用品回収・遺品整理 悪徳業者撲滅プロジェクト');
      update_option('blogdescription', '不用品回収の裏側まで、正直に。');
      update_option('timezone_string', 'Asia/Tokyo');
      update_option('blog_public', 0);
      update_option('permalink_structure', '/%postname%/');
      if (!get_option('cleanup_local_initialized')) {
        foreach(get_posts(['post_type'=>['post','page'],'post_status'=>'any','numberposts'=>-1]) as $p) wp_delete_post($p->ID,true);
        update_option('cleanup_local_initialized', 1);
      }
      flush_rewrite_rules();` },
  ] },
});
// Preview is local-only: close the CLI listener and bind it to loopback.
await new Promise((done, reject) => server.server.close(error => error ? reject(error) : done()));
await new Promise(done => server.server.listen(testing ? 9401 : 9400, '127.0.0.1', done));
if (testing) {
  try {
    const source = await readFile('tests/integration.php', 'utf8');
    const result = await server.playground.run({ code: "<?php define('CLEANUP_TESTING', true); ?>" + source });
    console.log(result.text);
    if (result.errors) console.error(result.errors);
    if (result.exitCode !== 0 || !result.text.includes('ALL TESTS PASSED')) throw new Error('PHP integration tests failed');
    const routes = ['/', '/scam/', '/industry/', '/staff/', '/news/', '/price/', '/municipality/', '/company/', '/scam/test-scam-renamed/', '/municipality/test-pref/', '/municipality/test-pref/test-city/', '/company/test-pref/', '/company/detail/test-company/'];
    for (const route of routes) {
      const response = await fetch(`http://127.0.0.1:9401${route}`);
      const html = await response.text();
      if (response.status !== 200 || !html.includes('id="main"') || /Fatal error|Warning:|Parse error/.test(html)) throw new Error(`Route failed: ${route} (${response.status})`);
      console.log(`PASS HTTP ${route}`);
      if (route === '/company/detail/test-company/' && (!html.includes('運営者の自社サービス') || !html.includes('提携業者（紹介料あり）'))) throw new Error('Missing disclosure');
      if (route === '/scam/' && html.includes('TEST_NEWS_ONLY')) throw new Error('Article type filter leaked another category');
      const canonicals = [...html.matchAll(/<link rel="canonical" href="([^"]+)"/g)];
      if (canonicals.length !== 1 || canonicals[0][1] !== 'http://127.0.0.1:9401'+route) throw new Error('Canonical mismatch: '+route);
      if (route === '/scam/test-scam-renamed/' && (!html.includes('"@type":"Article"') || !html.includes('"@type":"BreadcrumbList"'))) throw new Error('Structured data missing');
      if (['/company/test-pref/','/price/'].includes(route) && !/<meta name=['"]robots['"][^>]+noindex/.test(html)) throw new Error('Unreviewed archive must be noindex');
    }
    const redirect = await fetch('http://127.0.0.1:9401/scam/test-scam/',{redirect:'manual'});
    if (redirect.status !== 301 || redirect.headers.get('location') !== 'http://127.0.0.1:9401/scam/test-scam-renamed/') throw new Error('Old URL redirect failed');
    const xml = await fetch('http://127.0.0.1:9401/wp-sitemap-cleanup-1.xml').then(r=>r.text());
    if (!xml.includes('/scam/test-scam-renamed/') || xml.includes('/company/detail/test-company/') || xml.includes('/scam/test-scam/')) throw new Error('Sitemap eligibility mismatch');
    console.log('PASS SEO canonical / Schema / robots / redirect / XML sitemap');
    const paged = await fetch('http://127.0.0.1:9401/scam/page/2/').then(r=>r.text());
    if (!paged.includes('rel="canonical" href="http://127.0.0.1:9401/scam/page/2/"') || /<meta name=['"]robots['"][^>]+noindex/.test(paged)) throw new Error('Pagination canonical/index policy failed');
    const filtered = await fetch('http://127.0.0.1:9401/company/test-pref/?service=unknown').then(r=>r.text());
    if (!/<meta name=['"]robots['"][^>]+noindex/.test(filtered)) throw new Error('Filtered page must be noindex');
    const indexed = await fetch('http://127.0.0.1:9401/scam/test-scam-renamed/').then(r=>r.text());
    if (/<meta name=['"]robots['"][^>]+noindex/.test(indexed)) throw new Error('Approved article unexpectedly noindex');
    await server.playground.run({code:"<?php require '/wordpress/wp-load.php'; update_option('blog_public',0);"});
    const privateHtml = await fetch('http://127.0.0.1:9401/scam/test-scam-renamed/').then(r=>r.text());
    if (!/<meta name=['"]robots['"][^>]+noindex/.test(privateHtml)) throw new Error('Site-wide privacy not respected');
    const privateSitemap = await fetch('http://127.0.0.1:9401/wp-sitemap.xml');
    if (privateSitemap.status !== 404) throw new Error('Private site sitemap must be disabled');
    console.log('PASS pagination / filtered URLs / index opt-in / site-wide noindex');
    for (const route of ['/company/unknown-prefecture/', '/scam/missing-article/']) {
      const response = await fetch(`http://127.0.0.1:9401${route}`);
      if (response.status !== 404) throw new Error(`Expected 404: ${route}, got ${response.status}`);
      console.log(`PASS HTTP 404 ${route}`);
    }
    const {randomBytes} = await import('node:crypto');
    const password = randomBytes(24).toString('base64url');
    await server.playground.run({code: `<?php require '/wordpress/wp-load.php'; wp_set_password('${password}', 1);`});
    const login = await fetch('http://127.0.0.1:9401/wp-login.php', {method:'POST', redirect:'manual', body:new URLSearchParams({log:'admin',pwd:password,'wp-submit':'Log In',redirect_to:'http://127.0.0.1:9401/wp-admin/'})});
    const cookies = login.headers.getSetCookie().map(c => c.split(';')[0]).join('; ');
    if (!cookies.includes('wordpress_logged_in')) throw new Error('Admin login failed');
    for (const route of ['/wp-admin/admin.php?page=cleanup-dashboard', '/wp-admin/edit.php?post_type=municipality', '/wp-admin/post-new.php?post_type=company', '/wp-admin/admin.php?page=cleanup-seo', '/wp-admin/admin.php?page=cleanup-ai']) {
      const response = await fetch('http://127.0.0.1:9401'+route, {headers:{cookie:cookies}});
      const html = await response.text();
      if (response.status !== 200 || /Fatal error|Parse error/.test(html) || html.includes('id="loginform"')) throw new Error('Admin failed: '+route);
      console.log('PASS admin '+route);
    }
    await writeFile('.runtime/test-result.txt', 'WordPress 7.1 / PHP 8.3 / Playground SQLite\n' + result.text + '\n13 public routes, 2 invalid routes, 5 authenticated admin pages passed\nSEO canonical / Schema / robots / redirect / XML sitemap passed\n', 'utf8');
  } finally { await server[Symbol.asyncDispose](); }
} else {
  if (process.argv.includes('--seed-initial')) {
    const seeded = await server.playground.run({code: "<?php require '/wordpress/wp-load.php'; wp_set_current_user(1); require '/cleanup-initial/import.php'; echo wp_json_encode(cleanup_import_initial_pack(), JSON_UNESCAPED_UNICODE);"});
    if (seeded.exitCode !== 0 || seeded.errors) throw new Error(seeded.errors || seeded.text);
    await writeFile('.runtime/initial-import.json', seeded.text, 'utf8');
    console.log('Initial content imported as drafts. Report: .runtime/initial-import.json');
  }
  // Never use Playground's default password, even on a loopback preview.
  if (process.argv.includes('--seed-policies')) {
    const result = await server.playground.run({code: "<?php require '/wordpress/wp-load.php'; wp_set_current_user(1); require '/cleanup-policies/import.php'; $first=cleanup_import_policy_drafts(); $second=cleanup_import_policy_drafts(); if(array_column($first,'id')!==array_column($second,'id')) throw new RuntimeException('Duplicate import'); foreach($first as $row) { if(get_post_status($row['id'])!=='draft') throw new RuntimeException('Expected draft'); } wp_set_current_user(0); $denied=false; try { cleanup_import_policy_drafts(); } catch(RuntimeException $e) { $denied=true; } if(!$denied) throw new RuntimeException('Permission failure'); echo wp_json_encode($first);"});
    if (result.exitCode !== 0 || result.errors) throw new Error(result.errors || result.text);
    await writeFile('.runtime/policy-import.json', result.text, 'utf8');
    console.log('Policy drafts imported; duplicate and permission checks passed.');
  }
  const { randomBytes } = await import('node:crypto');
  const password = randomBytes(24).toString('base64url');
  await server.playground.run({code: `<?php require '/wordpress/wp-load.php'; wp_set_password('${password}', 1);`});
  await writeFile('.runtime/preview-access.txt', `URL: http://127.0.0.1:9400/wp-admin/\nUsername: admin\nPassword: ${password}\nLocal development only. Data: .runtime/wordpress\n`, 'utf8');
  console.log('Preview: http://127.0.0.1:9400/');
  console.log('Local admin credentials: .runtime/preview-access.txt');
}

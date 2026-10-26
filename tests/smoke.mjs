import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import net from 'node:net';
import { fileURLToPath } from 'node:url';
import { spawn, spawnSync } from 'node:child_process';

const php = process.env.PHP_BINARY || 'php';
const source = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'portal-test-'));
const site = path.join(temp, 'site');
fs.cpSync(source, site, { recursive: true, filter: name => path.basename(name) !== 'tests' });
const file = path.join(site, 'data', 'portal_data.json');
const original = fs.readFileSync(file, 'utf8');
const read = () => JSON.parse(fs.readFileSync(file, 'utf8'));
let checks = 0;
const check = (condition, label) => { assert.ok(condition, label); console.log(`PASS ${++checks}: ${label}`); };
const listener = net.createServer();
await new Promise(resolve => listener.listen(0, '127.0.0.1', resolve));
const port = listener.address().port;
await new Promise(resolve => listener.close(resolve));
const server = spawn(php, ['-d', 'display_errors=1', '-d', 'error_reporting=32767', '-d', `session.save_path=${temp}`, '-d', 'upload_max_filesize=6M', '-d', 'post_max_size=8M', '-S', `127.0.0.1:${port}`, '-t', site], { windowsHide: true });
let logs = '';
server.stderr.on('data', data => { logs += data; });
server.on('error', error => { logs += error.message; });
let cookie = '';
async function request(url, fields, multipart = false) {
    const response = await fetch(`http://127.0.0.1:${port}/${url}`, {
        redirect: 'manual', headers: cookie ? { Cookie: cookie } : {},
        method: fields ? 'POST' : 'GET',
        body: fields ? (multipart ? fields : new URLSearchParams(fields)) : undefined,
    });
    const setCookie = response.headers.get('set-cookie');
    if (setCookie) cookie = setCookie.split(';')[0];
    const text = await response.text();
    assert.doesNotMatch(text, /(?:Fatal error|Warning|Deprecated|Parse error):/);
    return { status: response.status, location: response.headers.get('location'), text };
}
async function post(fields, expected) {
    const response = await request('admin.php', fields);
    check(response.status === 303 && response.location === 'admin.php', 'POST redirects to GET');
    check((await request('admin.php')).text.includes(expected), expected);
}

try {
    let ready = false;
    for (let i = 0; i < 50; i++) {
        try { await request('login.php'); ready = true; break; } catch { await new Promise(resolve => setTimeout(resolve, 100)); }
    }
    assert.ok(ready, logs);
    for (const name of fs.readdirSync(site).filter(name => name.endsWith('.php'))) {
        const lint = spawnSync(php, ['-l', path.join(site, name)], { encoding: 'utf8', windowsHide: true });
        check(lint.status === 0, `PHP syntax ${name}`);
    }
    check((await request('admin.php')).location === 'login.php', 'Admin requires login');
    check((await request('index.php')).text.includes('Mengurus segala hal terkait perkuliahan'), 'Legacy category descriptions display');
    check((await request('click.php?cat=0&app=1')).location === 'https://siakad.poltekkes-manado.ac.id', 'Legacy url key redirects correctly');
    const placeholderViews = read().kategori_layanan[0].aplikasi[2].views;
    check((await request('click.php?cat=0&app=2')).location === 'index.php', 'Unavailable app returns to home');
    check(read().kategori_layanan[0].aplikasi[2].views === placeholderViews, 'Unavailable app does not increase views');
    check((await request('login.php', { username: 'admin', password: 'wrong' })).text.includes('salah'), 'Invalid login rejected');
    check((await request('login.php', { 'username[]': 'admin', 'password[]': 'admin123' })).status === 200, 'Malformed login handled');
    check((await request('login.php', { username: 'admin', password: 'admin123' })).location === 'admin.php', 'Valid login succeeds');
    check(!/type="url"[^>]*value="#"/.test((await request('admin.php')).text), 'Optional URL fields do not contain invalid #');
    const count = read().pengumuman_list.length;
    await post({ action: 'add_pengumuman', judul: 'Uji pengumuman', isi: 'Isi uji', tanggal: '2026-10-26', link: '' }, 'Pengumuman berhasil dipublikasikan!');
    await request('admin.php');
    check(read().pengumuman_list.length === count + 1, 'Refresh does not duplicate announcements');
    await post({ action: 'edit_pengumuman', index_pengumuman: '0', judul: 'Revisi', isi: 'Isi revisi', tanggal: '2026-10-27', link: '#' }, 'Pengumuman berhasil diperbarui!');
    check(read().pengumuman_list[0].link === '', 'Legacy # link normalized');
    await post({ action: 'delete_pengumuman', index_pengumuman: '0' }, 'Pengumuman berhasil dihapus!');
    check(read().pengumuman_list.length === count, 'Announcement deleted');
    const categories = read().kategori_layanan.length;
    await post({ action: 'add_layanan', nama_kategori: 'Uji', deskripsi: 'Deskripsi uji' }, 'Layanan baru berhasil dibuat!');
    await post({ action: 'edit_layanan', index_layanan: String(categories), nama_kategori: 'Revisi kategori', deskripsi: 'Revisi deskripsi' }, 'Data Layanan berhasil diperbarui!');
    await post({ action: 'add_aplikasi', index_layanan: String(categories), nama: 'Uji aplikasi', link: 'https://example.com/test' }, 'Aplikasi baru berhasil ditambahkan!');
    const click = await request(`click.php?cat=${categories}&app=0`);
    check(click.status === 302 && click.location === 'https://example.com/test', 'App redirects to configured URL');
    check(read().kategori_layanan[categories].aplikasi[0].views === 1, 'Click counted once');
    await post({ action: 'edit_aplikasi', index_layanan: String(categories), index_aplikasi: '0', nama: 'Revisi aplikasi', link: 'https://example.com/revised' }, 'Data aplikasi berhasil diperbarui!');
    check(read().kategori_layanan[categories].aplikasi[0].views === 1, 'Editing preserves views');
    await post({ action: 'delete_aplikasi', index_layanan: String(categories), index_aplikasi: '0' }, 'Aplikasi berhasil dihapus!');
    await post({ action: 'delete_layanan', index_layanan: String(categories) }, 'Layanan berhasil dihapus!');
    const beforeInvalid = fs.readFileSync(file, 'utf8');
    await post({ action: 'delete_layanan' }, 'Indeks data tidak tersedia.');
    await post({ action: 'delete_layanan', index_layanan: 'abc' }, 'Indeks data tidak valid.');
    await post({ action: 'add_pengumuman', judul: 'Uji', isi: 'Uji', tanggal: '2026-02-30' }, 'Tanggal pengumuman tidak valid.');
    await post({ action: 'add_aplikasi', index_layanan: '0', nama: 'Uji', link: 'javascript:alert(1)' }, 'Tautan harus berupa URL');
    await post({ action: 'add_layanan', 'nama_kategori[]': 'Uji' }, 'Isian formulir tidak valid.');
    check(fs.readFileSync(file, 'utf8') === beforeInvalid, 'Invalid requests leave data unchanged');
    for (const query of ['cat[]=0&app=0', 'cat=abc&app=0', 'cat=-1&app=0', 'cat=0', 'cat=999&app=0']) {
        check((await request(`click.php?${query}`)).location === 'index.php', `Invalid click handled: ${query}`);
    }
    const settings = { ...read().settings, action: 'save_settings', hero_height: '500' };
    await post(settings, 'Pengaturan umum, logo, &amp; background berhasil diperbarui!');
    check(read().settings.hero_height === '500', 'Settings saved');
    const form = new FormData();
    for (const [key, value] of Object.entries(settings)) form.append(key, value);
    form.set('bg_hero', new Blob(['not an image'], { type: 'image/png' }), 'invalid.png');
    const beforeUpload = fs.readFileSync(file, 'utf8');
    check((await request('admin.php', form, true)).status === 303, 'Invalid image returns redirect');
    check((await request('admin.php')).text.includes('Gunakan gambar JPG'), 'Invalid image reports error');
    check(fs.readFileSync(file, 'utf8') === beforeUpload, 'Failed upload leaves settings unchanged');
    form.set('bg_hero', new Blob([fs.readFileSync(path.join(site, 'images', 'Logo Blu.png'))], { type: 'image/png' }), 'valid.png');
    await request('admin.php', form, true);
    check((await request('admin.php')).text.includes('berhasil diperbarui!'), 'Valid image accepted');
    check(fs.existsSync(path.join(site, read().settings.bg_hero)), 'Uploaded image saved');
    const partial = read(); partial.settings = { hero_title: 'Partial settings' }; fs.writeFileSync(file, JSON.stringify(partial));
    check((await request('index.php')).status === 200, 'Partial settings receive defaults');
    fs.writeFileSync(file, '{broken');
    check((await request('index.php')).status === 503, 'Corrupt JSON produces controlled error');
    check((await request('admin.php', { action: 'add_layanan', nama_kategori: 'Test' })).status === 503, 'Admin refuses to overwrite corrupt JSON');
    check(fs.readFileSync(file, 'utf8') === '{broken', 'Corrupt file preserved for recovery');
    fs.writeFileSync(file, original);
    const worker = `require $argv[1]; for ($i=0; $i<10; $i++) { $lock=lockPortalData($argv[2]); try { $d=readPortalData($argv[2]); $d['kategori_layanan'][0]['aplikasi'][0]['views']++; saveJSON($argv[2], $d); } finally { unlockPortalData($lock); } }`;
    const baseline = read().kategori_layanan[0].aplikasi[0].views;
    await Promise.all(Array.from({ length: 4 }, () => new Promise((resolve, reject) => {
        const child = spawn(php, ['-r', worker, path.join(site, 'storage.php'), file], { windowsHide: true });
        let output = '';
        child.stderr.on('data', data => { output += data; });
        child.on('error', reject);
        child.on('exit', code => code === 0 ? resolve() : reject(new Error(output)));
    })));
    check(read().kategori_layanan[0].aplikasi[0].views === baseline + 40, 'Concurrent writes preserve all 40 updates');
    check((await request('admin.php?action=logout')).location === 'login.php', 'Logout succeeds');
    check((await request('admin.php')).location === 'login.php', 'Logged-out session cannot access admin');
    const errors = logs.split('\n').filter(line => /PHP (?:Warning|Fatal error|Deprecated|Parse error):/.test(line));
    assert.deepEqual([...new Set(errors)], [], 'PHP server must not emit warnings');
    console.log(`\n${checks} checks passed. Original application data untouched.`);
} finally {
    server.kill();
    if (server.exitCode === null) await new Promise(resolve => server.once('exit', resolve));
    const resolved = path.resolve(temp);
    assert.ok(resolved.startsWith(path.resolve(os.tmpdir()) + path.sep) && path.basename(resolved).startsWith('portal-test-'));
    fs.rmSync(resolved, { recursive: true, force: true });
}

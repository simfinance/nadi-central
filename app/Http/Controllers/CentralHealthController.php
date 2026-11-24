<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Central Health: snapshot kondisi host NADI Central.
 *
 * - GET biasa            => halaman (snapshot awal ikut tertanam di view).
 * - GET Accept: JSON     => snapshot JSON, dipakai auto-refresh di halaman.
 *
 * Semua pembacaan bersifat read-only (/proc, /sys, systemctl show, df, SELECT).
 * Nilai "rate" (CPU, jaringan, disk I/O) dihitung dari dua sampel berjarak SAMPLE_US;
 * counter kumulatif ikut dikirim agar browser bisa menghitung rate yang lebih akurat
 * di antara dua polling.
 */
class CentralHealthController extends Controller
{
    private const SAMPLE_US = 300000;

    private const CLK_TCK = 100;

    private const PAGE_SIZE = 4096;

    private const CORE_SERVICES = [
        'nginx'       => 'Nginx',
        'php8.5-fpm'  => 'PHP-FPM 8.5',
        'mysql'       => 'MySQL',
        'cron'        => 'Cron',
    ];

    /** Hanya ditampilkan jika unit-nya terpasang di host. */
    private const OPTIONAL_SERVICES = [
        'ssh'               => 'OpenSSH',
        'redis-server'      => 'Redis',
        'supervisor'        => 'Supervisor',
        'fail2ban'          => 'Fail2ban',
        'ufw'               => 'UFW Firewall',
        'systemd-timesyncd' => 'Time Sync',
        'nadi-agent'        => 'NADI Agent',
    ];

    private const REAL_FS = ['ext2', 'ext3', 'ext4', 'xfs', 'btrfs', 'zfs', 'f2fs', 'vfat', 'exfat', 'ntfs', 'fuseblk', 'nfs', 'nfs4', 'cifs'];

    private const TCP_STATES = [
        '01' => 'established', '02' => 'syn_sent', '03' => 'syn_recv', '04' => 'fin_wait1', '05' => 'fin_wait2',
        '06' => 'time_wait', '07' => 'close', '08' => 'close_wait', '09' => 'last_ack', '0A' => 'listen', '0B' => 'closing',
    ];

    private const PORT_NAMES = [
        21 => 'FTP', 22 => 'SSH', 25 => 'SMTP', 53 => 'DNS', 80 => 'HTTP', 110 => 'POP3', 143 => 'IMAP', 443 => 'HTTPS',
        465 => 'SMTPS', 587 => 'SMTP', 993 => 'IMAPS', 995 => 'POP3S', 3306 => 'MySQL', 33060 => 'MySQL X', 5432 => 'PostgreSQL',
        6379 => 'Redis', 8080 => 'HTTP alt', 8443 => 'HTTPS alt', 9000 => 'PHP-FPM', 11211 => 'Memcached', 27017 => 'MongoDB',
        9200 => 'Elasticsearch', 5672 => 'RabbitMQ', 3000 => 'Dev server',
    ];

    /** Port yang tidak seharusnya terbuka ke publik. */
    private const SENSITIVE_PORTS = [3306, 33060, 5432, 6379, 11211, 27017, 9200, 5672, 9000];

    public function index(Request $request)
    {
        $snapshot = $this->snapshot();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($snapshot)->header('Cache-Control', 'no-store');
        }

        return view('pages.monitoring.central-health', ['snapshot' => $snapshot]);
    }

    /* ------------------------------------------------------------------ */
    /*  Snapshot                                                           */
    /* ------------------------------------------------------------------ */

    public function collectSnapshot(): array
    {
        return $this->snapshot();
    }

    private function snapshot(): array
    {
        $a = $this->sampleRaw();
        usleep(self::SAMPLE_US);
        $b  = $this->sampleRaw();
        $dt = max(0.05, $b['t'] - $a['t']);

        $cpu     = $this->cpuSection($a['cpu'], $b['cpu']);
        $load    = $this->loadSection($cpu['cores_count']);
        $memory  = $this->memorySection();
        $storage = $this->storageSection($a['disk'], $b['disk'], $dt);
        $network = $this->networkSection($a['net'], $b['net'], $dt);
        $procs   = $this->processSection($a['proc'], $b['proc'], $dt, $b['cpu'], $a['cpu']);
        $services = $this->servicesSection();
        $database = $this->databaseSection();
        $php      = $this->phpSection();
        $laravel  = $this->laravelSection();
        $system   = $this->systemSection();

        $snapshot = [
            'sampled_at'  => now()->toIso8601String(),
            'sampled_ms'  => (int) round($b['t'] * 1000),
            'sample_window' => round($dt, 3),
            'host'        => $this->hostSection($cpu),
            'cpu'         => $cpu,
            'load'        => $load,
            'memory'      => $memory,
            'pressure'    => $this->pressureSection(),
            'storage'     => $storage,
            'network'     => $network,
            'processes'   => $procs,
            'services'    => $services,
            'database'    => $database,
            'php'         => $php,
            'laravel'     => $laravel,
            'system'      => $system,
        ];

        $snapshot['findings'] = $this->findings($snapshot);
        $snapshot['overall']  = $this->overall($snapshot['findings']);

        return $snapshot;
    }

    private function sampleRaw(): array
    {
        return [
            't'    => microtime(true),
            'cpu'  => $this->readCpuStat(),
            'net'  => $this->readNetDev(),
            'disk' => $this->readDiskStats(),
            'proc' => $this->readProcTicks(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Host & CPU                                                         */
    /* ------------------------------------------------------------------ */

    private function hostSection(array $cpu): array
    {
        $uptime = (float) strtok((string) $this->read('/proc/uptime'), ' ');

        return [
            'hostname' => gethostname() ?: 'Unknown',
            'os'       => $this->osName(),
            'kernel'   => php_uname('r'),
            'arch'     => php_uname('m'),
            'uptime_seconds' => $uptime > 0 ? (int) floor($uptime) : null,
            'boot_time' => $uptime > 0 ? now()->subSeconds((int) $uptime)->toIso8601String() : null,
            'virtualization' => $this->virtualization(),
        ];
    }

    private function osName(): string
    {
        $c = $this->read('/etc/os-release');

        if ($c !== null && preg_match('/^PRETTY_NAME="?([^"\n]+)"?/m', $c, $m)) {
            return trim($m[1]);
        }

        return php_uname('s');
    }

    private function virtualization(): ?string
    {
        $v = trim((string) $this->read('/sys/class/dmi/id/sys_vendor'));
        $p = trim((string) $this->read('/sys/class/dmi/id/product_name'));
        $label = trim($v . ' ' . $p);

        return $label !== '' ? $label : null;
    }

    private function readCpuStat(): array
    {
        $out = ['total' => array_fill(0, 8, 0), 'cores' => [], 'ctxt' => null, 'forks' => null, 'running' => null, 'blocked' => null];
        $c = $this->read('/proc/stat');

        if ($c === null) {
            return $out;
        }

        foreach (preg_split('/\r?\n/', $c) as $line) {
            if (preg_match('/^cpu(\d*)\s+(.+)$/', $line, $m)) {
                $v = array_slice(array_map('intval', preg_split('/\s+/', trim($m[2]))), 0, 8);
                $v = array_pad($v, 8, 0);

                if ($m[1] === '') {
                    $out['total'] = $v;
                } else {
                    $out['cores'][(int) $m[1]] = $v;
                }
            } elseif (preg_match('/^(ctxt|processes|procs_running|procs_blocked)\s+(\d+)/', $line, $m)) {
                $map = ['ctxt' => 'ctxt', 'processes' => 'forks', 'procs_running' => 'running', 'procs_blocked' => 'blocked'];
                $out[$map[$m[1]]] = (int) $m[2];
            }
        }

        return $out;
    }

    private function cpuDelta(array $x, array $y): array
    {
        $total = max(1, array_sum($y) - array_sum($x));
        $d     = fn (int $i) => max(0, $y[$i] - $x[$i]);
        $idle  = $d(3) + $d(4);
        $pct   = fn ($v) => round(min(100, max(0, $v / $total * 100)), 1);

        return [
            'usage'  => $pct($total - $idle),
            'user'   => $pct($d(0) + $d(1)),
            'system' => $pct($d(2) + $d(5) + $d(6)),
            'iowait' => $pct($d(4)),
            'steal'  => $pct($d(7)),
            'idle'   => $pct($d(3)),
        ];
    }

    private function cpuSection(array $a, array $b): array
    {
        $total = $this->cpuDelta($a['total'], $b['total']);
        $cores = [];

        foreach ($b['cores'] as $i => $vals) {
            if (isset($a['cores'][$i])) {
                $cores[] = ['id' => $i, 'usage' => $this->cpuDelta($a['cores'][$i], $vals)['usage']];
            }
        }

        $model = null;
        $mhz   = null;
        $info  = $this->read('/proc/cpuinfo');

        if ($info !== null) {
            if (preg_match('/^model name\s*:\s*(.+)$/mi', $info, $m) || preg_match('/^(?:Model|Hardware)\s*:\s*([^\d\s].*)$/m', $info, $m)) {
                $model = trim(preg_replace('/\s+/', ' ', $m[1]));
            }
            if (preg_match('/^cpu MHz\s*:\s*([\d.]+)/mi', $info, $m)) {
                $mhz = round((float) $m[1]);
            }
        }

        $t = $b['total'];

        return $total + [
            'model'       => $model,
            'mhz'         => $mhz,
            'cores_count' => count($b['cores']) ?: null,
            'cores'       => $cores,
            'context_switches' => $b['ctxt'],
            'forks'       => $b['forks'],
            'running'     => $b['running'],
            'blocked'     => $b['blocked'],
            // Counter kumulatif untuk perhitungan rate di browser.
            'jiffies'     => [
                'total'  => array_sum($t),
                'idle'   => $t[3] + $t[4],
                'user'   => $t[0] + $t[1],
                'system' => $t[2] + $t[5] + $t[6],
                'iowait' => $t[4],
                'steal'  => $t[7],
            ],
        ];
    }

    private function loadSection(?int $cores): array
    {
        $c = $this->read('/proc/loadavg');
        $parts = $c !== null ? preg_split('/\s+/', trim($c)) : [];

        $one = isset($parts[0]) ? (float) $parts[0] : null;
        $five = isset($parts[1]) ? (float) $parts[1] : null;
        $fifteen = isset($parts[2]) ? (float) $parts[2] : null;

        [$run, $tot] = isset($parts[3]) && str_contains($parts[3], '/') ? array_map('intval', explode('/', $parts[3])) : [null, null];

        return [
            'one' => $one, 'five' => $five, 'fifteen' => $fifteen,
            'per_core' => ($cores && $one !== null) ? round($one / $cores, 2) : null,
            'running' => $run, 'total_tasks' => $tot,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Memory & pressure                                                  */
    /* ------------------------------------------------------------------ */

    private function memorySection(): array
    {
        $c = $this->read('/proc/meminfo');

        if ($c === null) {
            return ['available' => false];
        }

        $v = [];
        foreach (preg_split('/\r?\n/', $c) as $line) {
            if (preg_match('/^([A-Za-z_()0-9]+):\s+(\d+)/', $line, $m)) {
                $v[$m[1]] = (int) $m[2] * 1024;
            }
        }

        $total     = $v['MemTotal'] ?? 0;
        $available = $v['MemAvailable'] ?? ($v['MemFree'] ?? 0);
        $free      = $v['MemFree'] ?? 0;
        $cache     = ($v['Cached'] ?? 0) + ($v['Buffers'] ?? 0) + ($v['SReclaimable'] ?? 0);
        $used      = max(0, $total - $available);
        $apps      = max(0, $total - $free - $cache);
        $swapTotal = $v['SwapTotal'] ?? 0;
        $swapUsed  = max(0, $swapTotal - ($v['SwapFree'] ?? 0));

        $vm = $this->read('/proc/vmstat') ?? '';
        $swapIn  = preg_match('/^pswpin\s+(\d+)/m', $vm, $m1) ? (int) $m1[1] : null;
        $swapOut = preg_match('/^pswpout\s+(\d+)/m', $vm, $m2) ? (int) $m2[1] : null;
        $oom     = preg_match('/^oom_kill\s+(\d+)/m', $vm, $m3) ? (int) $m3[1] : null;

        return [
            'available'     => $total > 0,
            'total'         => $total,
            'used'          => $used,
            'free'          => $free,
            'available_bytes' => $available,
            'apps'          => $apps,
            'cache'         => $cache,
            'usage_percent' => $total > 0 ? round($used / $total * 100, 1) : null,
            'buffers'       => $v['Buffers'] ?? 0,
            'cached'        => $v['Cached'] ?? 0,
            'shmem'         => $v['Shmem'] ?? 0,
            'slab'          => $v['Slab'] ?? 0,
            'dirty'         => $v['Dirty'] ?? 0,
            'committed'     => $v['Committed_AS'] ?? 0,
            'commit_limit'  => $v['CommitLimit'] ?? 0,
            'swap_total'    => $swapTotal,
            'swap_used'     => $swapUsed,
            'swap_percent'  => $swapTotal > 0 ? round($swapUsed / $swapTotal * 100, 1) : null,
            'swap_in_pages' => $swapIn,
            'swap_out_pages'=> $swapOut,
            'oom_kills'     => $oom,
        ];
    }

    private function pressureSection(): array
    {
        $out = [];

        foreach (['cpu', 'memory', 'io'] as $res) {
            $c = $this->read("/proc/pressure/{$res}");

            if ($c === null) {
                continue;
            }

            $row = ['some' => null, 'full' => null];

            foreach (preg_split('/\r?\n/', trim($c)) as $line) {
                if (preg_match('/^(some|full)\s+avg10=([\d.]+)\s+avg60=([\d.]+)\s+avg300=([\d.]+)/', $line, $m)) {
                    $row[$m[1]] = ['avg10' => (float) $m[2], 'avg60' => (float) $m[3], 'avg300' => (float) $m[4]];
                }
            }

            $out[$res] = $row;
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */
    /*  Storage                                                            */
    /* ------------------------------------------------------------------ */

    private function readDiskStats(): array
    {
        $c = $this->read('/proc/diskstats');
        $out = [];

        if ($c === null) {
            return $out;
        }

        foreach (preg_split('/\r?\n/', $c) as $line) {
            $p = preg_split('/\s+/', trim($line));

            if (count($p) < 14) {
                continue;
            }

            $name = $p[2];

            // Hanya disk fisik/virtual utama, bukan partisi/loop/ram.
            if (! preg_match('/^(sd[a-z]+|vd[a-z]+|xvd[a-z]+|hd[a-z]+|nvme\d+n\d+|mmcblk\d+)$/', $name)) {
                continue;
            }

            $out[$name] = [
                'reads' => (int) $p[3], 'rbytes' => (int) $p[5] * 512, 'writes' => (int) $p[7], 'wbytes' => (int) $p[9] * 512,
                'io_ms' => (int) $p[12], 'inflight' => (int) $p[11],
            ];
        }

        return $out;
    }

    private function inodeMap(): array
    {
        $out = $this->run(['df', '-P', '-i']);
        $map = [];

        if ($out === null) {
            return $map;
        }

        foreach (array_slice(preg_split('/\r?\n/', trim($out)), 1) as $line) {
            $p = preg_split('/\s+/', trim($line), 6);

            if (count($p) >= 6 && is_numeric($p[1])) {
                $map[$p[5]] = ['total' => (int) $p[1], 'used' => (int) $p[2], 'percent' => (float) rtrim($p[4], '%')];
            }
        }

        return $map;
    }

    private function storageSection(array $a, array $b, float $dt): array
    {
        $inodes = $this->inodeMap();
        $mounts = [];
        $seen   = [];

        foreach ((@file('/proc/mounts', FILE_IGNORE_NEW_LINES) ?: []) as $line) {
            $p = preg_split('/\s+/', $line);

            if (count($p) < 3 || ! in_array($p[2], self::REAL_FS, true) || isset($seen[$p[0]])) {
                continue;
            }

            $seen[$p[0]] = true;
            $mp    = str_replace('\040', ' ', $p[1]);
            $total = @disk_total_space($mp);
            $free  = @disk_free_space($mp);

            if ($total === false || $free === false || $total <= 0) {
                continue;
            }

            $used = $total - $free;

            $mounts[] = [
                'mount' => $mp, 'device' => $p[0], 'fs' => $p[2],
                'total' => (float) $total, 'used' => (float) $used, 'free' => (float) $free,
                'percent' => round($used / $total * 100, 1),
                'inodes' => $inodes[$mp] ?? null,
                'writable' => is_writable($mp),
            ];
        }

        usort($mounts, fn ($x, $y) => ($y['mount'] === '/') <=> ($x['mount'] === '/'));

        $devices = [];
        $sumR = $sumW = $opsR = $opsW = 0;

        foreach ($b as $name => $y) {
            $x = $a[$name] ?? $y;

            $devices[] = [
                'name' => $name,
                'read_bps'  => round(max(0, $y['rbytes'] - $x['rbytes']) / $dt),
                'write_bps' => round(max(0, $y['wbytes'] - $x['wbytes']) / $dt),
                'read_iops' => round(max(0, $y['reads'] - $x['reads']) / $dt, 1),
                'write_iops'=> round(max(0, $y['writes'] - $x['writes']) / $dt, 1),
                'util'      => round(min(100, max(0, ($y['io_ms'] - $x['io_ms']) / ($dt * 1000) * 100)), 1),
                'inflight'  => $y['inflight'],
            ];

            $sumR += $y['rbytes'];
            $sumW += $y['wbytes'];
            $opsR += $y['reads'];
            $opsW += $y['writes'];
        }

        return [
            'mounts'  => $mounts,
            'devices' => $devices,
            'io_counters' => ['read_bytes' => $sumR, 'write_bytes' => $sumW, 'read_ops' => $opsR, 'write_ops' => $opsW],
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Network                                                            */
    /* ------------------------------------------------------------------ */

    private function readNetDev(): array
    {
        $c = $this->read('/proc/net/dev');
        $out = [];

        if ($c === null) {
            return $out;
        }

        foreach (array_slice(preg_split('/\r?\n/', $c), 2) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $stats] = explode(':', $line, 2);
            $name = trim($name);
            $v    = preg_split('/\s+/', trim($stats));

            if (count($v) < 12) {
                continue;
            }

            $out[$name] = [
                'rx_bytes' => (int) $v[0], 'rx_packets' => (int) $v[1], 'rx_errors' => (int) $v[2], 'rx_dropped' => (int) $v[3],
                'tx_bytes' => (int) $v[8], 'tx_packets' => (int) $v[9], 'tx_errors' => (int) $v[10], 'tx_dropped' => (int) $v[11],
            ];
        }

        return $out;
    }

    private function networkSection(array $a, array $b, float $dt): array
    {
        $addr = [];
        $ip   = $this->run(['ip', '-o', '-4', 'addr', 'show']);

        if ($ip !== null) {
            foreach (preg_split('/\r?\n/', trim($ip)) as $line) {
                if (preg_match('/^\d+:\s+(\S+)\s+inet\s+(\S+)/', $line, $m)) {
                    $addr[$m[1]][] = $m[2];
                }
            }
        }

        $ifaces = [];
        $tot = ['rx_bytes' => 0, 'tx_bytes' => 0, 'rx_packets' => 0, 'tx_packets' => 0];

        foreach ($b as $name => $y) {
            if ($name === 'lo' || str_starts_with($name, 'veth') || str_starts_with($name, 'br-')) {
                continue;
            }

            $x = $a[$name] ?? $y;
            $sys = "/sys/class/net/{$name}";
            $speed = (int) trim((string) $this->read("{$sys}/speed"));

            $ifaces[] = [
                'name' => $name,
                'state' => trim((string) $this->read("{$sys}/operstate")) ?: 'unknown',
                'mac' => trim((string) $this->read("{$sys}/address")) ?: null,
                'mtu' => (int) trim((string) $this->read("{$sys}/mtu")) ?: null,
                'speed_mbps' => $speed > 0 ? $speed : null,
                'addresses' => $addr[$name] ?? [],
                'rx_bps' => round(max(0, $y['rx_bytes'] - $x['rx_bytes']) / $dt),
                'tx_bps' => round(max(0, $y['tx_bytes'] - $x['tx_bytes']) / $dt),
            ] + $y;

            foreach (array_keys($tot) as $k) {
                $tot[$k] += $y[$k];
            }
        }

        return [
            'interfaces' => $ifaces,
            'totals'     => $tot,
            'rx_bps'     => array_sum(array_column($ifaces, 'rx_bps')),
            'tx_bps'     => array_sum(array_column($ifaces, 'tx_bps')),
            'tcp'        => $this->tcpSection(),
            'sockets'    => $this->sockstat(),
        ];
    }

    private function tcpSection(): array
    {
        $states = array_fill_keys(array_values(self::TCP_STATES), 0);
        $listen = [];

        foreach (['/proc/net/tcp' => false, '/proc/net/tcp6' => true] as $file => $v6) {
            $c = $this->read($file);

            if ($c === null) {
                continue;
            }

            foreach (array_slice(preg_split('/\r?\n/', trim($c)), 1) as $line) {
                $p = preg_split('/\s+/', trim($line));

                if (count($p) < 4) {
                    continue;
                }

                $st = strtoupper($p[3]);
                $states[self::TCP_STATES[$st] ?? 'close']++;

                if ($st !== '0A') {
                    continue;
                }

                [$hex, $port] = explode(':', $p[1]) + [null, null];
                $port = hexdec((string) $port);
                $local = $v6 ? in_array($hex, ['00000000000000000000000001000000', '00000000000000000000000000000001'], true) : $hex === '0100007F';
                $any   = $v6 ? $hex === '00000000000000000000000000000000' : $hex === '00000000';
                $scope = $local ? 'local' : ($any ? 'public' : 'bound');

                $key = $port;

                // Jika port sama terbuka di beberapa alamat, prioritaskan yang paling terbuka.
                if (! isset($listen[$key]) || $scope === 'public' || ($scope === 'bound' && $listen[$key]['scope'] === 'local')) {
                    $listen[$key] = [
                        'port' => $port,
                        'scope' => $scope,
                        'service' => self::PORT_NAMES[$port] ?? null,
                        'sensitive' => in_array($port, self::SENSITIVE_PORTS, true),
                    ];
                }
            }
        }

        ksort($listen);

        return ['states' => $states, 'listening' => array_values($listen)];
    }

    private function sockstat(): array
    {
        $c = (string) $this->read('/proc/net/sockstat');
        $g = fn (string $re) => preg_match($re, $c, $m) ? (int) $m[1] : null;

        return [
            'used' => $g('/sockets: used (\d+)/'),
            'tcp_inuse' => $g('/TCP: inuse (\d+)/'),
            'tcp_orphan' => $g('/orphan (\d+)/'),
            'tcp_alloc' => $g('/alloc (\d+)/'),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Processes                                                          */
    /* ------------------------------------------------------------------ */

    private function readProcTicks(): array
    {
        $out = [];

        foreach ((glob('/proc/[0-9]*', GLOB_ONLYDIR) ?: []) as $dir) {
            $c = @file_get_contents($dir . '/stat');

            if ($c === false || ! preg_match('/^(\d+) \((.*)\) (\S) (.*)$/s', $c, $m)) {
                continue;
            }

            $f = explode(' ', $m[4]);

            $out[(int) $m[1]] = [
                'pid' => (int) $m[1], 'comm' => $m[2], 'state' => $m[3],
                'ticks' => (int) ($f[10] ?? 0) + (int) ($f[11] ?? 0),
                'threads' => (int) ($f[16] ?? 1),
                'rss' => (int) ($f[20] ?? 0) * self::PAGE_SIZE,
            ];
        }

        return $out;
    }

    private function processSection(array $a, array $b, float $dt, array $cpuB, array $cpuA): array
    {
        $states = ['running' => 0, 'sleeping' => 0, 'disk_wait' => 0, 'zombie' => 0, 'stopped' => 0, 'other' => 0];
        $groups = [];
        $threads = 0;

        foreach ($b as $pid => $p) {
            $key = match ($p['state']) {
                'R' => 'running', 'S', 'I' => 'sleeping', 'D' => 'disk_wait', 'Z' => 'zombie', 'T', 't' => 'stopped', default => 'other',
            };
            $states[$key]++;
            $threads += $p['threads'];

            $cpu = isset($a[$pid]) ? max(0, $p['ticks'] - $a[$pid]['ticks']) / self::CLK_TCK / $dt * 100 : 0;

            $g = &$groups[$p['comm']];
            $g ??= ['name' => $p['comm'], 'count' => 0, 'rss' => 0, 'cpu' => 0.0, 'user' => null, 'top_pid' => $pid, 'top_rss' => 0];
            $g['count']++;
            $g['rss'] += $p['rss'];
            $g['cpu'] += $cpu;

            if ($p['rss'] >= $g['top_rss']) {
                $g['top_rss'] = $p['rss'];
                $g['top_pid'] = $pid;
            }

            unset($g);
        }

        foreach ($groups as &$g) {
            $uid = @fileowner('/proc/' . $g['top_pid']);
            $g['user'] = $uid !== false && function_exists('posix_getpwuid') ? (posix_getpwuid($uid)['name'] ?? (string) $uid) : ($uid !== false ? (string) $uid : null);
            $g['cpu'] = round($g['cpu'], 1);
            unset($g['top_rss'], $g['top_pid']);
        }
        unset($g);

        $list  = array_values($groups);
        $byMem = $list;
        $byCpu = $list;
        usort($byMem, fn ($x, $y) => $y['rss'] <=> $x['rss']);
        usort($byCpu, fn ($x, $y) => $y['cpu'] <=> $x['cpu']);

        $fd = preg_split('/\s+/', trim((string) $this->read('/proc/sys/fs/file-nr')));

        return [
            'total' => count($b),
            'threads' => $threads,
            'states' => $states,
            'top_memory' => array_slice($byMem, 0, 8),
            'top_cpu' => array_slice(array_filter($byCpu, fn ($g) => $g['cpu'] > 0), 0, 8),
            'file_descriptors' => isset($fd[0], $fd[2]) ? ['open' => (int) $fd[0], 'max' => (int) $fd[2]] : null,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Services                                                           */
    /* ------------------------------------------------------------------ */

    private function servicesSection(): array
    {
        $items = [];

        foreach (self::CORE_SERVICES as $unit => $label) {
            $items[] = $this->serviceInfo($unit, $label, true);
        }

        foreach (self::OPTIONAL_SERVICES as $unit => $label) {
            $s = $this->serviceInfo($unit, $label, false);

            if ($s !== null) {
                $items[] = $s;
            }
        }

        $failed = [];
        $out = $this->run(['systemctl', '--failed', '--no-legend', '--plain', '--no-pager']);

        if ($out !== null) {
            foreach (preg_split('/\r?\n/', trim($out)) as $line) {
                if ($line !== '' && preg_match('/^(\S+)\s+\S+\s+(\S+)\s+(\S+)/', trim($line), $m)) {
                    $failed[] = ['unit' => $m[1], 'sub' => $m[3]];
                }
            }
        }

        return ['items' => $items, 'failed' => $failed];
    }

    private function serviceInfo(string $unit, string $label, bool $core): ?array
    {
        $out = $this->run(['systemctl', 'show', $unit, '--no-pager',
            '--property=LoadState,ActiveState,SubState,MainPID,MemoryCurrent,ActiveEnterTimestamp,NRestarts,TasksCurrent']);

        $base = ['key' => $unit, 'label' => $label, 'core' => $core, 'state' => 'unavailable', 'sub' => null, 'pid' => null,
            'memory' => null, 'tasks' => null, 'uptime' => null, 'restarts' => null, 'status' => 'unknown'];

        if ($out === null) {
            return $core ? $base : null;
        }

        $kv = [];
        foreach (preg_split('/\r?\n/', trim($out)) as $line) {
            if (str_contains($line, '=')) {
                [$k, $v] = explode('=', $line, 2);
                $kv[$k] = trim($v);
            }
        }

        // systemd tidak tersedia / unit tidak bisa dibaca: status tidak diketahui, bukan gagal.
        if (! isset($kv['ActiveState'])) {
            return $base;
        }

        if (($kv['LoadState'] ?? '') === 'not-found') {
            return $core ? array_merge($base, ['state' => 'not installed', 'status' => 'critical']) : null;
        }

        $active = $kv['ActiveState'] ?? 'unknown';
        $mem = $kv['MemoryCurrent'] ?? '';
        $since = ! empty($kv['ActiveEnterTimestamp']) ? strtotime($kv['ActiveEnterTimestamp']) : false;

        return array_merge($base, [
            'state' => $active,
            'sub' => $kv['SubState'] ?? null,
            'pid' => (int) ($kv['MainPID'] ?? 0) ?: null,
            'memory' => ctype_digit($mem) && (float) $mem < 1.8e19 ? (float) $mem : null,
            'tasks' => ctype_digit($kv['TasksCurrent'] ?? '') && (float) $kv['TasksCurrent'] < 1.8e19 ? (int) $kv['TasksCurrent'] : null,
            'uptime' => $active === 'active' && $since ? max(0, time() - $since) : null,
            'restarts' => isset($kv['NRestarts']) && ctype_digit($kv['NRestarts']) ? (int) $kv['NRestarts'] : null,
            'status' => $active === 'active' ? 'healthy' : ($active === 'activating' || $active === 'reloading' ? 'warning' : ($core ? 'critical' : 'unknown')),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Database                                                           */
    /* ------------------------------------------------------------------ */

    private function databaseSection(): array
    {
        $started = microtime(true);
        $base = ['status' => 'critical', 'state' => 'Connection failed', 'driver' => config('database.default'), 'latency_ms' => null];

        try {
            DB::select('SELECT 1');
            $base = ['status' => 'healthy', 'state' => 'Connected', 'latency_ms' => round((microtime(true) - $started) * 1000, 2)] + $base;
        } catch (Throwable $e) {
            return $base;
        }

        try {
            $driver = DB::connection()->getDriverName();
            $base['engine'] = $driver;

            if (! in_array($driver, ['mysql', 'mariadb'], true)) {
                return $base;
            }

            $base['version'] = DB::selectOne('SELECT VERSION() AS v')->v ?? null;
            $base['name'] = DB::selectOne('SELECT DATABASE() AS d')->d ?? null;

            $want = ['Threads_connected', 'Threads_running', 'Questions', 'Slow_queries', 'Aborted_connects', 'Connections', 'Uptime',
                'Max_used_connections', 'Innodb_buffer_pool_reads', 'Innodb_buffer_pool_read_requests'];
            $status = [];

            foreach (DB::select("SHOW GLOBAL STATUS WHERE Variable_name IN ('" . implode("','", $want) . "')") as $r) {
                $status[$r->Variable_name] = (float) $r->Value;
            }

            $max = DB::select("SHOW VARIABLES LIKE 'max_connections'");
            $reads = $status['Innodb_buffer_pool_reads'] ?? 0;
            $reqs  = $status['Innodb_buffer_pool_read_requests'] ?? 0;

            $base['stats'] = [
                'threads_connected' => (int) ($status['Threads_connected'] ?? 0),
                'threads_running' => (int) ($status['Threads_running'] ?? 0),
                'max_connections' => isset($max[0]) ? (int) $max[0]->Value : null,
                'max_used' => (int) ($status['Max_used_connections'] ?? 0),
                'questions' => (int) ($status['Questions'] ?? 0),
                'slow_queries' => (int) ($status['Slow_queries'] ?? 0),
                'aborted_connects' => (int) ($status['Aborted_connects'] ?? 0),
                'connections' => (int) ($status['Connections'] ?? 0),
                'uptime' => (int) ($status['Uptime'] ?? 0),
                'buffer_pool_hit' => $reqs > 0 ? round((1 - $reads / $reqs) * 100, 2) : null,
            ];

            $tables = DB::select('SELECT table_name AS name, (data_length + index_length) AS size_bytes, table_rows AS row_estimate
                FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY (data_length + index_length) DESC LIMIT 6');

            $size = DB::selectOne('SELECT COALESCE(SUM(data_length + index_length), 0) AS s FROM information_schema.tables WHERE table_schema = DATABASE()');

            $base['size_bytes'] = (float) ($size->s ?? 0);
            $base['top_tables'] = array_map(fn ($t) => ['name' => $t->name, 'size' => (float) $t->size_bytes, 'rows' => (int) $t->row_estimate], $tables);
        } catch (Throwable $e) {
            $base['stats_error'] = 'Detailed statistics unavailable (insufficient privileges)';
        }

        return $base;
    }

    /* ------------------------------------------------------------------ */
    /*  PHP / Laravel / System                                             */
    /* ------------------------------------------------------------------ */

    private function phpSection(): array
    {
        $op = null;

        if (function_exists('opcache_get_status')) {
            $s = @opcache_get_status(false);

            if (is_array($s)) {
                $mem = $s['memory_usage'] ?? [];
                $st  = $s['opcache_statistics'] ?? [];
                $usedMem = (float) ($mem['used_memory'] ?? 0);
                $freeMem = (float) ($mem['free_memory'] ?? 0);

                $op = [
                    'enabled' => (bool) ($s['opcache_enabled'] ?? false),
                    'used' => $usedMem, 'free' => $freeMem, 'wasted_percent' => round((float) ($mem['current_wasted_percentage'] ?? 0), 2),
                    'hit_rate' => round((float) ($st['opcache_hit_rate'] ?? 0), 2),
                    'scripts' => (int) ($st['num_cached_scripts'] ?? 0),
                    'max_scripts' => (int) ($st['max_cached_keys'] ?? 0),
                    'restarts' => (int) (($st['oom_restarts'] ?? 0) + ($st['hash_restarts'] ?? 0) + ($st['manual_restarts'] ?? 0)),
                ];
            }
        }

        return [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'timezone' => date_default_timezone_get(),
            'extensions' => count(get_loaded_extensions()),
            'request_memory' => memory_get_peak_usage(true),
            'opcache_loaded' => extension_loaded('Zend OPcache'),
            'opcache' => $op,
        ];
    }

    private function laravelSection(): array
    {
        $logDir = storage_path('logs');
        $files  = glob($logDir . '/*.log') ?: [];
        $size   = 0;
        $newest = null;

        foreach ($files as $f) {
            $size += (int) @filesize($f);

            if ($newest === null || @filemtime($f) > @filemtime($newest)) {
                $newest = $f;
            }
        }

        $errors = $this->recentLogErrors($newest);

        $checks = [];
        foreach (['Application storage' => storage_path(), 'Application logs' => $logDir, 'Bootstrap cache' => base_path('bootstrap/cache')] as $label => $path) {
            $exists = is_dir($path);
            $ok = $exists && is_writable($path);
            $checks[] = ['label' => $label, 'path' => $path, 'state' => ! $exists ? 'Missing' : ($ok ? 'Writable' : 'Not writable'), 'status' => $ok ? 'healthy' : 'critical'];
        }

        $failed = $pending = null;

        try {
            $failed = DB::table('failed_jobs')->count();
        } catch (Throwable $e) {
        }

        try {
            if (config('queue.default') === 'database') {
                $pending = DB::table('jobs')->count();
            }
        } catch (Throwable $e) {
        }

        return [
            'version' => app()->version(),
            'environment' => app()->environment(),
            'debug' => (bool) config('app.debug'),
            'timezone' => config('app.timezone'),
            'queue' => config('queue.default'),
            'cache' => config('cache.default'),
            'session' => config('session.driver'),
            'key_configured' => filled(config('app.key')),
            'maintenance' => app()->isDownForMaintenance(),
            'config_cached' => app()->configurationIsCached(),
            'routes_cached' => app()->routesAreCached(),
            'events_cached' => app()->eventsAreCached(),
            'storage' => $checks,
            'logs' => [
                'files' => count($files), 'size' => $size,
                'newest' => $newest ? basename($newest) : null,
                'modified' => $newest ? date('c', (int) @filemtime($newest)) : null,
                'errors_24h' => $errors['count'], 'recent' => $errors['recent'],
            ],
            'failed_jobs' => $failed,
            'pending_jobs' => $pending,
        ];
    }

    /** Baca ±256 KB terakhir log terbaru dan hitung ERROR 24 jam terakhir. */
    private function recentLogErrors(?string $file): array
    {
        $out = ['count' => null, 'recent' => []];

        if (! $file || ! is_readable($file)) {
            return $out;
        }

        $size = (int) @filesize($file);
        $fh   = @fopen($file, 'rb');

        if (! $fh) {
            return $out;
        }

        fseek($fh, max(0, $size - 262144));
        $chunk = (string) fread($fh, 262144);
        fclose($fh);

        $limit = now()->subDay()->timestamp;
        $count = 0;
        $recent = [];

        if (preg_match_all('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \S+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.{0,180})/m', $chunk, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                if (strtotime($row[1]) >= $limit) {
                    $count++;
                    $recent[] = ['time' => $row[1], 'level' => $row[2], 'message' => trim($row[3])];
                }
            }
        }

        $out['count'] = $count;
        $out['recent'] = array_slice(array_reverse($recent), 0, 4);

        return $out;
    }

    private function systemSection(): array
    {
        $ntp = null;
        $tz  = null;
        $out = $this->run(['timedatectl', 'show', '--property=NTPSynchronized', '--property=Timezone']);

        if ($out !== null) {
            $ntp = preg_match('/NTPSynchronized=(\w+)/', $out, $m) ? $m[1] === 'yes' : null;
            $tz  = preg_match('/Timezone=(\S+)/', $out, $m) ? $m[1] : null;
        }

        $updates = null;
        $u = $this->read('/var/lib/update-notifier/updates-available');

        if ($u !== null && preg_match('/(\d+) updates? can be/', $u, $m)) {
            $updates = (int) $m[1];
        }

        $security = null;
        if ($u !== null && preg_match('/(\d+) of these updates? (?:is|are) (?:a )?security/', $u, $m)) {
            $security = (int) $m[1];
        }

        return [
            'reboot_required' => file_exists('/var/run/reboot-required'),
            'ntp_synchronized' => $ntp,
            'system_timezone' => $tz,
            'pending_updates' => $updates,
            'security_updates' => $security,
            'server_time' => now()->toIso8601String(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Analisa                                                            */
    /* ------------------------------------------------------------------ */

    private function findings(array $s): array
    {
        $f = [];
        $add = function (string $level, string $title, string $detail, string $area) use (&$f) {
            $f[] = compact('level', 'title', 'detail', 'area');
        };

        $cpu = $s['cpu'];
        if ($cpu['usage'] >= 90) {
            $add('critical', 'CPU saturated', "CPU usage {$cpu['usage']}% (user {$cpu['user']}%, system {$cpu['system']}%).", 'cpu');
        } elseif ($cpu['usage'] >= 75) {
            $add('warning', 'CPU usage high', "CPU usage {$cpu['usage']}%.", 'cpu');
        }
        if ($cpu['iowait'] >= 20) {
            $add('warning', 'High I/O wait', "CPU spends {$cpu['iowait']}% waiting for disk. Storage may be the bottleneck.", 'cpu');
        }
        if ($cpu['steal'] >= 10) {
            $add('warning', 'CPU steal time', "Hypervisor is taking {$cpu['steal']}% of CPU time (noisy neighbour or undersized VM).", 'cpu');
        }

        $pc = $s['load']['per_core'];
        if ($pc !== null && $pc >= 2) {
            $add('critical', 'Load average very high', "Load {$s['load']['one']} on {$cpu['cores_count']} cores ({$pc} per core).", 'cpu');
        } elseif ($pc !== null && $pc >= 1) {
            $add('warning', 'Load average high', "Load {$s['load']['one']} on {$cpu['cores_count']} cores ({$pc} per core).", 'cpu');
        }

        $m = $s['memory'];
        if (($m['available'] ?? false) && $m['usage_percent'] !== null) {
            if ($m['usage_percent'] >= 95) {
                $add('critical', 'Memory almost exhausted', "{$m['usage_percent']}% of RAM in use; only {$this->bytes($m['available_bytes'])} available.", 'memory');
            } elseif ($m['usage_percent'] >= 85) {
                $add('warning', 'Memory usage high', "{$m['usage_percent']}% of RAM in use ({$this->bytes($m['available_bytes'])} available).", 'memory');
            }
            if (($m['swap_percent'] ?? 0) >= 50) {
                $add('warning', 'Swap heavily used', "{$m['swap_percent']}% of swap in use ({$this->bytes($m['swap_used'])}).", 'memory');
            }
            if (($m['oom_kills'] ?? 0) > 0) {
                $add('warning', 'OOM killer has fired', "{$m['oom_kills']} process(es) were killed by the kernel since boot.", 'memory');
            }
        }

        foreach ($s['pressure'] as $res => $p) {
            $v = $p['some']['avg10'] ?? 0;
            if ($v >= 20) {
                $add('critical', 'Resource pressure: ' . $res, "Tasks stalled on {$res} for {$v}% of the last 10 seconds.", $res === 'io' ? 'storage' : $res);
            } elseif ($v >= 8) {
                $add('warning', 'Resource pressure: ' . $res, "Tasks stalled on {$res} for {$v}% of the last 10 seconds.", $res === 'io' ? 'storage' : $res);
            }
        }

        foreach ($s['storage']['mounts'] as $d) {
            if ($d['percent'] >= 95) {
                $add('critical', "Disk {$d['mount']} almost full", "{$d['percent']}% used, {$this->bytes($d['free'])} free.", 'storage');
            } elseif ($d['percent'] >= 85) {
                $add('warning', "Disk {$d['mount']} filling up", "{$d['percent']}% used, {$this->bytes($d['free'])} free.", 'storage');
            }
            if (($d['inodes']['percent'] ?? 0) >= 90) {
                $add('warning', "Inodes on {$d['mount']} nearly exhausted", "{$d['inodes']['percent']}% of inodes used.", 'storage');
            }
        }

        foreach ($s['storage']['devices'] as $d) {
            if ($d['util'] >= 90) {
                $add('warning', "Disk {$d['name']} saturated", "Device busy {$d['util']}% of the time.", 'storage');
            }
        }

        foreach ($s['network']['interfaces'] as $i) {
            // Hanya interface yang pernah dipakai (punya IP atau trafik) yang dianggap bermasalah saat down.
            if ($i['state'] === 'down' && ($i['addresses'] || $i['rx_bytes'] > 0)) {
                $add('warning', "Interface {$i['name']} is down", 'Link state reports down although the interface was in use.', 'network');
            }
            $drop = $i['rx_packets'] > 0 ? $i['rx_dropped'] / $i['rx_packets'] * 100 : 0;
            if ($drop >= 1) {
                $add('warning', "Packet drops on {$i['name']}", number_format($i['rx_dropped']) . ' inbound packets dropped (' . round($drop, 2) . '% of RX).', 'network');
            }
            if ($i['rx_errors'] + $i['tx_errors'] > 0) {
                $add('warning', "Interface errors on {$i['name']}", "RX errors {$i['rx_errors']}, TX errors {$i['tx_errors']}.", 'network');
            }
        }

        $tcp = $s['network']['tcp'];
        foreach ($tcp['listening'] as $l) {
            if ($l['sensitive'] && $l['scope'] !== 'local') {
                $add('critical', "{$l['service']} port {$l['port']} is exposed", 'Service is listening on all interfaces. Bind it to 127.0.0.1 or firewall it.', 'security');
            }
        }
        if ($tcp['states']['close_wait'] >= 100) {
            $add('warning', 'Many CLOSE_WAIT sockets', "{$tcp['states']['close_wait']} connections wait for the application to close them (possible leak).", 'network');
        }
        if ($tcp['states']['time_wait'] >= 10000) {
            $add('warning', 'Many TIME_WAIT sockets', "{$tcp['states']['time_wait']} sockets in TIME_WAIT.", 'network');
        }

        $p = $s['processes'];
        if ($p['states']['zombie'] > 0) {
            $add('warning', 'Zombie processes', "{$p['states']['zombie']} zombie process(es) present.", 'processes');
        }
        if ($p['file_descriptors'] && $p['file_descriptors']['max'] > 0 && $p['file_descriptors']['open'] / $p['file_descriptors']['max'] >= 0.8) {
            $add('warning', 'File descriptors running out', "{$p['file_descriptors']['open']} of {$p['file_descriptors']['max']} in use.", 'processes');
        }

        foreach ($s['services']['items'] as $svc) {
            if ($svc['core'] && $svc['status'] === 'critical') {
                $add('critical', "{$svc['label']} is {$svc['state']}", 'Core service is not running.', 'services');
            } elseif ($svc['status'] === 'warning') {
                $add('warning', "{$svc['label']} is {$svc['state']}", 'Service is transitioning.', 'services');
            }
            if (($svc['restarts'] ?? 0) >= 3) {
                $add('warning', "{$svc['label']} restarted {$svc['restarts']} times", 'Service keeps being restarted by systemd.', 'services');
            }
        }
        if ($s['services']['failed']) {
            $add('warning', count($s['services']['failed']) . ' failed systemd unit(s)', implode(', ', array_column($s['services']['failed'], 'unit')), 'services');
        }

        $db = $s['database'];
        if ($db['status'] !== 'healthy') {
            $add('critical', 'Database unreachable', 'The application could not run a query against the central database.', 'database');
        } else {
            if (($db['latency_ms'] ?? 0) >= 200) {
                $add('warning', 'Database latency high', "SELECT 1 took {$db['latency_ms']} ms.", 'database');
            }
            $st = $db['stats'] ?? null;
            if ($st && $st['max_connections'] && $st['threads_connected'] / $st['max_connections'] >= 0.8) {
                $add('warning', 'Database connections near limit', "{$st['threads_connected']} of {$st['max_connections']} connections in use.", 'database');
            }
        }

        $l = $s['laravel'];
        foreach ($l['storage'] as $c) {
            if ($c['status'] !== 'healthy') {
                $add('critical', "{$c['label']}: {$c['state']}", $c['path'], 'application');
            }
        }
        if (! $l['key_configured']) {
            $add('critical', 'APP_KEY is missing', 'Encryption and sessions are not secure without an application key.', 'application');
        }
        if ($l['debug'] && $l['environment'] === 'production') {
            $add('critical', 'Debug mode is ON in production', 'Stack traces and environment details may leak to visitors.', 'application');
        } elseif ($l['debug']) {
            $add('info', 'Debug mode is ON', 'Remember to disable APP_DEBUG before going to production.', 'application');
        }
        if ($l['maintenance']) {
            $add('warning', 'Application in maintenance mode', 'Visitors currently receive the maintenance response.', 'application');
        }
        if (($l['failed_jobs'] ?? 0) > 0) {
            $add('warning', "{$l['failed_jobs']} failed queue job(s)", 'Review with php artisan queue:failed.', 'application');
        }
        if (($l['logs']['errors_24h'] ?? 0) > 0) {
            $add('warning', "{$l['logs']['errors_24h']} application error(s) in 24h", 'See the log excerpt below.', 'application');
        }
        if (($l['logs']['size'] ?? 0) >= 200 * 1024 * 1024) {
            $add('warning', 'Log files are large', $this->bytes($l['logs']['size']) . ' in storage/logs. Consider log rotation.', 'application');
        }
        if ($l['environment'] === 'production' && ! $l['config_cached']) {
            $add('info', 'Configuration is not cached', 'Run php artisan optimize for faster boot in production.', 'application');
        }

        $o = $s['php']['opcache'];
        if (! $s['php']['opcache_loaded'] || ($o && ! $o['enabled'])) {
            $add('info', 'OPcache is disabled', 'Enabling OPcache significantly reduces PHP response time.', 'php');
        } elseif ($o && $o['free'] + $o['used'] > 0 && $o['free'] / ($o['free'] + $o['used']) < 0.05) {
            $add('warning', 'OPcache memory almost full', 'Increase opcache.memory_consumption to avoid restarts.', 'php');
        }

        $sys = $s['system'];
        if ($sys['reboot_required']) {
            $add('warning', 'Reboot required', 'Installed updates need a restart to take effect.', 'system');
        }
        if (($sys['security_updates'] ?? 0) > 0) {
            $add('warning', "{$sys['security_updates']} security update(s) pending", 'Apply with apt upgrade.', 'system');
        } elseif (($sys['pending_updates'] ?? 0) > 0) {
            $add('info', "{$sys['pending_updates']} package update(s) available", 'No security updates flagged.', 'system');
        }
        if ($sys['ntp_synchronized'] === false) {
            $add('warning', 'Clock not synchronised', 'NTP reports the system clock is not synchronised.', 'system');
        }

        $rank = ['critical' => 0, 'warning' => 1, 'info' => 2];
        usort($f, fn ($x, $y) => $rank[$x['level']] <=> $rank[$y['level']]);

        return $f;
    }

    private function overall(array $findings): array
    {
        $crit = count(array_filter($findings, fn ($x) => $x['level'] === 'critical'));
        $warn = count(array_filter($findings, fn ($x) => $x['level'] === 'warning'));
        $info = count(array_filter($findings, fn ($x) => $x['level'] === 'info'));

        return [
            'status' => $crit ? 'critical' : ($warn ? 'warning' : 'healthy'),
            'critical' => $crit, 'warning' => $warn, 'info' => $info,
            'score' => max(0, 100 - $crit * 25 - $warn * 8 - $info),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Helper                                                             */
    /* ------------------------------------------------------------------ */

    private function read(string $path): ?string
    {
        $c = @file_get_contents($path);

        return $c === false ? null : $c;
    }

    private function run(array $cmd, float $timeout = 3.0): ?string
    {
        try {
            $bin = (new ExecutableFinder())->find($cmd[0], null, ['/usr/bin', '/bin', '/usr/sbin', '/sbin']);

            if (! $bin) {
                return null;
            }

            $cmd[0] = $bin;
            $p = new Process($cmd);
            $p->setTimeout($timeout);
            $p->run();

            return $p->getOutput();
        } catch (Throwable $e) {
            return null;
        }
    }

    private function bytes(float|int|null $b): string
    {
        if ($b === null) {
            return 'N/A';
        }

        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $b = (float) $b;

        while ($b >= 1024 && $i < count($u) - 1) {
            $b /= 1024;
            $i++;
        }

        return round($b, $i > 1 ? 1 : 0) . ' ' . $u[$i];
    }
}
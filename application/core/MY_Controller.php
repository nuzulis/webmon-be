<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @property CI_Router $router
 * @property CI_Input $input
 * @property CI_Output $output
 * @property CI_User_agent $agent
 * @property CI_DB_query_builder $db
 * @method jsonRes($status, $data)
 
 */

class MY_Controller extends CI_Controller
{
    public array $user = [];
    protected array $featurePolicy = [];
    protected $db_ums;

    public function __construct()
    {
        parent::__construct();
        $this->db_ums = $this->load->database('ums', TRUE);
        $this->load->library('user_agent');
        $this->load->helper(['jwt', 'url']);
        $this->load->config('jwt');
        $this->load->config('feature_policy');

        $this->featurePolicy = (array) $this->config->item('feature_policy');
        date_default_timezone_set('Asia/Jakarta');
        $this->handleSecurity();
    }
   
    protected function handleSecurity(): void
    {
        $controller = strtolower($this->router->fetch_class());

        $whiteList = ['auth', 'welcome', 'dashboard'];
        if (in_array($controller, $whiteList, true)) {
            return;
        }
        $this->validateJwt();
        $category = $this->determineCategory($controller);
        $this->requireFeature($category . '.' . $controller);
        $this->logActivity();
    }


    protected function determineCategory(string $controller): string
    {
        $map = [
            'operasional' => [
                'permohonan', 'monitoring', 'domasonline', 'transaksi', 'revisi',
                'perlakuan', 'serahterima', 'batalpermohonan',
                'nnc',  'detail'
            ],
            'tindakan' => [
                'periksaadmin', 'periksalapangan', 'periksafisik',
                'penahanan', 'penolakan', 'pengasingan',
                'pemusnahan', 'pelepasan', 'tangkapan', 'elab', 'elabdetail'
            ],
            'pnbp' => [
                'kwitansi', 'kwitansibatal', 'kwitansibelumbayar',
                'billingbatal', 'carikuitansi'
            ],
            'preborder' => [
                'priornotice', 'ecert',
                'priornoticedetail', 'ecertdetail'
            ],
            'profiling' => ['penggunajasa'],
            'penugasan' => ['penugasan'],
            'caricepat' => ['caridokumen'],
        ];

        foreach ($map as $category => $controllers) {
            if (in_array($controller, $controllers, true)) {
                return $category;
            }
        }

        return 'umum';
    }

    protected function json($data, int $code = 200)
    {
        return $this->output
            ->set_status_header($code)
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }

    
    protected function validateJwt(): void
    {
        $auth =
            $this->input->get_request_header('Authorization', true)
            ?: $this->input->get_request_header('HTTP_AUTHORIZATION', true)
            ?: ($_SERVER['HTTP_AUTHORIZATION'] ?? null)
            ?: $this->input->get('token', true);

        if (!$auth) {
            $this->deny('Unauthorized');
        }

        $token = (strpos($auth, 'Bearer ') === 0)
            ? substr($auth, 7)
            : $auth;

        $payload = jwt_decode(trim($token), '');

        if ($payload === false) {
            $this->deny('Invalid or Expired Token');
        }

        $this->user = (array) $payload;
    }

    protected function requireFeature(string $feature): void
    {
        if (empty($this->user['detil']) || !is_array($this->user['detil'])) {
            $this->deny('User role detail not found');
        }

        foreach ($this->user['detil'] as $detail) {
            $role  = $detail['role_name'] ?? '';
            $appId = $detail['apps_id'] ?? '';

            if (!isset($this->featurePolicy[$role])) {
                continue;
            }

            $policy =
                $this->featurePolicy[$role][$appId]
                ?? $this->featurePolicy[$role]['*']
                ?? null;

            if (!$policy || empty($policy['allow'])) {
                continue;
            }

            foreach ($policy['allow'] as $rule) {
                if (
                    $rule === '*' ||
                    $rule === $feature ||
                    (substr($rule, -1) === '*' && strpos($feature, rtrim($rule, '*')) === 0)
                ) {
                    return;
                }
            }
        }

        $this->deny('Akses fitur ditolak: ' . $feature);
    }



    protected function applyScope(array &$filter): void
    {
        $userUpt = (string) ($this->user['upt'] ?? '');

        if ($userUpt === '1000') {
            if (empty($filter['upt_id']) || strtolower((string) $filter['upt_id']) === 'all') {
                unset($filter['upt_id']);
            }
            return;
        }

        $filter['upt_id'] = $userUpt;
    }

    public function logActivity(?string $customAction = null, ?string $appsId = '008'): bool
    {
        try {
            $controller = strtoupper($this->router->fetch_class());
            $method     = $this->router->fetch_method();

            $action = $customAction
                ?: "AKSES MENU: {$controller} ({$method})";

            $this->db_ums->insert('activity_log', [
                'id'         => $this->uuidV4(),
                'users_id'   => $this->user['sub']   ?? null,
                'username'   => $this->user['uname'] ?? 'guest',
                'action'     => $action,
                'apps_id'    => $appsId,
                'ip_address' => $this->input->ip_address(),
                'user_agent' => $this->agent->agent_string(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return true;

        } catch (\Throwable $e) {
            log_message('error', 'Activity log skipped: ' . $e->getMessage());
            return false;
        }
    }


    protected function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    protected function deny(string $message): void
    {
        $this->output
            ->set_status_header(403)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success' => false,
                'message' => $message
            ], JSON_UNESCAPED_UNICODE))
            ->_display();
        exit;
    }

    protected function buildReportHeader(string $title, array $filters, array $data = []): array
    {
        $uptName = 'SEMUA UPT';
        if (!empty($filters['upt_id']) && $filters['upt_id'] !== 'all') {
            if (!empty($data) && isset($data[0]['upt'])) {
                $uptName = $data[0]['upt'];
            }
            elseif (!empty($this->user['upt'])) {
                $uptName = 'UPT ' . $this->user['upt'];
            }
            else {
                $uptName = 'UPT';
            }
        }

        return [
            'judul'     => strtoupper($title),
            'upt'       => strtoupper($uptName),
            'periode'   => "PERIODE {$filters['start_date']} S/D {$filters['end_date']}",
            'pencetak'  => "Waktu Cetak: " . date('Y-m-d H:i:s') .
                           " | Oleh: " . ($this->user['nama'] ?? 'Admin'),
            'source'    => "Generated from: Web Monitoring Karantina v2.0",
            'report_id' => "REPBTL-" . date('Ymd') . "-" . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT)
        ];
    }

    protected function jsonRes(int $status, array $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}

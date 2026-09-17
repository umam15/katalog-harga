<?php
// GET /api/v1/openapi.json
// Skema OpenAPI 3.0 untuk seluruh endpoint /api/v1/* - self-describing,
// supaya agent/LLM bisa "baca sendiri" cara pakai API ini tanpa dokumentasi
// terpisah. Sengaja TANPA wajib API key (skemanya sendiri bukan data
// sensitif, dan client/agent butuh baca ini SEBELUM punya key untuk tahu
// cara pakainya) - tapi tetap ikut aturan toggle "Aktifkan API": kalau API
// dimatikan, seluruh /api/v1/* dianggap tidak ada (404), termasuk skema ini.

define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/includes/functions.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    api_error(405, 'method_not_allowed', 'Hanya method GET yang didukung.');
}
if (!get_api_enabled()) {
    api_error(404, 'not_found', 'Endpoint tidak ditemukan.');
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$serverUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$errorResponse = static function (string $desc): array {
    return [
        'description' => $desc,
        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
    ];
};

$spec = [
    'openapi' => '3.0.3',
    'info' => [
        'title'       => 'Katalog Harga API',
        'version'     => defined('APP_VERSION') ? APP_VERSION : '0.0.0-unknown',
        'description' => 'Akses baca-saja ke katalog harga & stok. Wajib API key '
            . '(header Authorization: Bearer <key>) di semua endpoint kecuali /health. '
            . 'Tiap key punya scope kantor sendiri - lihat GET /api/v1/kantor untuk tahu '
            . 'kantor apa saja yang boleh diakses key yang kamu pakai.',
    ],
    'servers' => [['url' => $serverUrl]],
    'security' => [['bearerAuth' => []]],
    'paths' => [
        '/api/v1/items' => [
            'get' => [
                'summary'    => 'Cari item katalog beserta harga & stok di satu kantor',
                'parameters' => [
                    ['name' => 'q', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'],
                        'description' => 'Kata kunci pencarian: nama, merek, jenis, kode item, atau barcode.'],
                    ['name' => 'kantor', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'],
                        'description' => 'Kode kantor/gudang. Wajib diisi kalau API key ini punya akses ke lebih dari satu kantor.'],
                    ['name' => 'limit', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 20, 'maximum' => 100]],
                    ['name' => 'offset', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 0, 'minimum' => 0]],
                ],
                'responses' => [
                    '200' => ['description' => 'Daftar item.', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ItemsListResponse']]]],
                    '400' => $errorResponse('Parameter tidak valid (mis. kantor wajib diisi / kantor tidak ditemukan).'),
                    '401' => $errorResponse('API key tidak ada/tidak valid.'),
                    '403' => $errorResponse('API key tidak punya akses ke kantor yang diminta.'),
                    '503' => $errorResponse('Database katalog sedang tidak bisa diakses.'),
                ],
            ],
        ],
        '/api/v1/items/{kodeitem}' => [
            'get' => [
                'summary'    => 'Detail satu item: harga per satuan, barcode, dan stok di satu kantor',
                'parameters' => [
                    ['name' => 'kodeitem', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ['name' => 'kantor', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'],
                        'description' => 'Kode kantor/gudang. Wajib diisi kalau API key ini punya akses ke lebih dari satu kantor.'],
                ],
                'responses' => [
                    '200' => ['description' => 'Detail item.', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ItemDetail']]]],
                    '400' => $errorResponse('Parameter tidak valid.'),
                    '401' => $errorResponse('API key tidak ada/tidak valid.'),
                    '403' => $errorResponse('API key tidak punya akses ke kantor yang diminta.'),
                    '404' => $errorResponse('Item tidak ditemukan di kantor tersebut.'),
                    '503' => $errorResponse('Database katalog sedang tidak bisa diakses.'),
                ],
            ],
        ],
        '/api/v1/kantor' => [
            'get' => [
                'summary'   => 'Daftar kantor/gudang yang boleh diakses API key ini',
                'responses' => [
                    '200' => ['description' => 'Daftar kantor.', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['kantor' => ['type' => 'array', 'items' => ['type' => 'string']]]]]]],
                    '401' => $errorResponse('API key tidak ada/tidak valid.'),
                    '503' => $errorResponse('Database katalog sedang tidak bisa diakses.'),
                ],
            ],
        ],
        '/api/v1/health' => [
            'get' => [
                'summary'   => 'Status uptime - tanpa API key, tidak membocorkan data katalog',
                'security'  => [],
                'responses' => [
                    '200' => ['description' => 'Sehat.', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['status' => ['type' => 'string'], 'database' => ['type' => 'string'], 'time' => ['type' => 'string', 'format' => 'date-time']]]]]],
                    '503' => ['description' => 'Database tidak tersambung.', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['status' => ['type' => 'string'], 'database' => ['type' => 'string'], 'time' => ['type' => 'string', 'format' => 'date-time']]]]]],
                ],
            ],
        ],
    ],
    'components' => [
        'securitySchemes' => [
            'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'API key yang dibuat di Panel Admin -> Pengaturan API.'],
        ],
        'schemas' => [
            'Error' => [
                'type' => 'object',
                'properties' => [
                    'error' => [
                        'type' => 'object',
                        'properties' => [
                            'code'    => ['type' => 'string'],
                            'message' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            'ItemSummary' => [
                'type' => 'object',
                'properties' => [
                    'kodeitem'     => ['type' => 'string'],
                    'nama'         => ['type' => 'string'],
                    'merek'        => ['type' => 'string'],
                    'jenis'        => ['type' => 'string'],
                    'satuan_dasar' => ['type' => 'string'],
                    'stok'         => ['type' => 'number'],
                    'harga'        => ['type' => 'number'],
                ],
            ],
            'ItemsListResponse' => [
                'type' => 'object',
                'properties' => [
                    'kantor' => ['type' => 'string'],
                    'items'  => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ItemSummary']],
                    'pagination' => [
                        'type' => 'object',
                        'properties' => [
                            'limit'  => ['type' => 'integer'],
                            'offset' => ['type' => 'integer'],
                            'total'  => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            'HargaSatuan' => [
                'type' => 'object',
                'properties' => [
                    'satuan'    => ['type' => 'string'],
                    'barcode'   => ['type' => 'string', 'description' => "Gabungan barcode dipisah ', ' kalau lebih dari satu, '-' kalau tidak ada."],
                    'harga'     => ['type' => 'number'],
                    'level'     => ['type' => ['integer', 'null'], 'description' => 'Tingkatan harga bertingkat (sistem harga L), null kalau tidak relevan.'],
                    'jmlsampai' => ['type' => ['number', 'null'], 'description' => 'Batas jumlah untuk harga grosir (sistem harga J), null kalau tidak relevan.'],
                    'info'      => ['type' => 'string', 'description' => 'Teks siap tampil dari level/jmlsampai, boleh kosong.'],
                ],
            ],
            'ItemDetail' => [
                'type' => 'object',
                'properties' => [
                    'kodeitem'     => ['type' => 'string'],
                    'nama'         => ['type' => 'string'],
                    'merek'        => ['type' => 'string'],
                    'jenis'        => ['type' => 'string'],
                    'keterangan'   => ['type' => 'string'],
                    'satuan_dasar' => ['type' => 'string'],
                    'stok'         => ['type' => 'number'],
                    'kantor'       => ['type' => 'string'],
                    'harga_list'   => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/HargaSatuan']],
                ],
            ],
        ],
    ],
];

api_json($spec);

<?php
// Define o cabeçalho da resposta como JSON
header('Content-Type: application/json');

// Função para sanitizar o domínio de entrada
function sanitize_domain($input) {
    $domain = trim(strtolower($input));
    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = preg_replace('#^ftp://#', '', $domain);
    $domain = preg_replace('#/.*$#', '', $domain);
    $domain = str_replace(' ', '', $domain);
    return $domain;
}

// Função para obter o nome do tipo de registro DNS
function get_dns_type_name($type) {
    $map = [
        DNS_A => 'A', DNS_MX => 'MX', DNS_NS => 'NS', DNS_TXT => 'TXT',
        DNS_CNAME => 'CNAME', DNS_SOA => 'SOA', DNS_SRV => 'SRV'
    ];
    return $map[$type] ?? 'Desconhecido';
}

// Função para obter a informação principal de um registro
function get_record_info($type, $record) {
    switch ($type) {
        case DNS_A:
            return $record['ip'];
        case DNS_CNAME:
        case DNS_NS:
            return $record['target'];
        case DNS_MX:
            return "{$record['pri']} {$record['target']}";
        case DNS_TXT:
            return implode(", ", $record['entries']);
        case DNS_SOA:
            return "{$record['mname']} {$record['rname']} {$record['serial']}";
        case DNS_SRV:
            return "{$record['pri']} {$record['weight']} {$record['port']} {$record['target']}";
        default:
            return '';
    }
}

$rawDomain = $_POST['domain'] ?? '';
$domain = sanitize_domain($rawDomain);

if (empty($domain)) {
    echo json_encode(['error' => 'Domínio não informado ou inválido.']);
    exit;
}

$results = [];
$unique_records = []; // Array para rastrear registros únicos

$subdomains = ['pop', 'imap', 'smtp', 'mail', 'webmail', 'autodiscover', 'www', '_dmarc', 'email-locaweb'];
$types_to_check = [DNS_A, DNS_MX, DNS_NS, DNS_SOA, DNS_TXT, DNS_CNAME, DNS_SRV];

$hosts_to_check = array_merge([$domain], array_map(fn($sub) => "{$sub}.{$domain}", $subdomains));
$hosts_to_check[] = "_autodiscover._tcp.{$domain}";

$mxPermitidos = ['mx.a.locaweb.com.br', 'mx.b.locaweb.com.br', 'mx.core.locaweb.com.br', 'mx.jk.locaweb.com.br'];
$nsPermitidos = ['ns1.locaweb.com.br', 'ns2.locaweb.com.br', 'ns3.locaweb.com.br'];

foreach ($hosts_to_check as $host) {
    foreach ($types_to_check as $type) {
        $records = @dns_get_record($host, $type);
        if ($records) {
            foreach ($records as $record) {
                // Normalize host names for comparison
                $record_host_normalized = rtrim(strtolower($record['host']), '.');
                $domain_normalized = rtrim(strtolower($domain), '.');

                // Check if the record's host is the domain itself or a subdomain.
                // This prevents showing NS records for parent zones (like .com or root servers).
                if (
                    $record_host_normalized !== $domain_normalized &&
                    (substr($record_host_normalized, -strlen('.' . $domain_normalized)) !== '.' . $domain_normalized)
                ) {
                    continue;
                }

                $info = get_record_info($type, $record);
                $typeName = get_dns_type_name($type);

                // Cria uma chave de deduplicação robusta, normalizando os dados
                $host_key = rtrim(strtolower($record['host']), '.');
                $info_key = rtrim(strtolower($info), '.');
                $record_key = "{$host_key}-{$typeName}-{$info_key}";

                // Se a chave não existir, adiciona o registro e marca a chave como vista
                if (!isset($unique_records[$record_key])) {
                    $highlight = false;
                    // A verificação de highlight usa o $info original, com case
                    if ($typeName === 'MX') {
                        $parts = explode(' ', $info, 2);
                        if (isset($parts[1]) && in_array(strtolower($parts[1]), $mxPermitidos)) {
                            $highlight = true;
                        }
                    }
                    if ($typeName === 'NS' && in_array(strtolower($info), $nsPermitidos)) {
                        $highlight = true;
                    }

                    $results[] = [
                        'host' => $record['host'],
                        'type' => $typeName,
                        'info' => $info, // Exibe o $info original, com case preservado
                        'highlight' => $highlight
                    ];

                    $unique_records[$record_key] = true;
                }
            }
        }
    }
}

echo json_encode(['domain' => $domain, 'records' => $results]);
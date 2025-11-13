<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta DNS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <style>
    body {
        font-family: "Roboto Condensed", sans-serif;
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        background-color: #ffffff;
        color: #0e387a;
    }

    header {
        background-color: #0e387a;
        padding: 20px;
        text-align: center;
        color: #ffffff;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    h1 {
        margin: 0;
        font-size: 2em;
    }

    form {
        margin: 30px auto;
        max-width: 500px;
        background-color: #f8f9fb;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0,0,0,0.05);
        text-align: center;
    }

    label {
        font-weight: bold;
        display: block;
        margin-bottom: 10px;
    }

    input[type="text"] {
        padding: 10px;
        width: 90%;
        max-width: 400px;
        border: 1px solid #ccc;
        border-radius: 4px;
        margin-bottom: 15px;
        font-size: 1em;
    }

    button {
        background-color: #0e387a;
        color: #ffffff;
        border: none;
        padding: 10px 20px;
        font-size: 1em;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    button:hover {
        background-color: #092955;
    }

    h2 {
        text-align: center;
        margin-top: 40px;
        color: #0e387a;
    }

    table {
        width: 95%;
        margin: 20px auto;
        border-collapse: collapse;
        background-color: #ffffff;
        box-shadow: 0 0 10px rgba(0,0,0,0.05);
    }

    th, td {
        padding: 12px;
        border: 1px solid #ddd;
        text-align: left;
    }

    th {
        background-color: #9fafca;
        color: #0e387a;
    }

    tr:nth-child(even) {
        background-color: #f4f6f9;
    }

    .highlight-green {
        background-color: #c8f7c5 !important;
    }

    p {
        text-align: center;
        font-size: 1.1em;
        margin-top: 30px;
    }   
</style>
</head>
<body>
    <header>
        <h1>Consulta de Registros DNS</h1>
    </header>
    <main>
    <!-- Formulário para inserir o domínio -->
    <form method="POST">
       <div class="form-group">
                <input type="text" id="domain" name="domain" placeholder="Digite o domínio" value="<?php echo isset($_POST['domain']) ? htmlspecialchars($_POST['domain']) : ''; ?>" required>
                <button type="submit">Consultar</button>
            </div>
    </form>

    <!-- O conteúdo dos registros DNS será exibido abaixo após a consulta -->
    <div class="table-container">
        <?php
        // Verificar se o formulário foi enviado
        if (isset($_POST['domain'])) {
            $domain = $_POST['domain'];

            // Tipos de registros que podemos consultar
            $types = [DNS_A, DNS_AAAA, DNS_MX, DNS_NS, DNS_SOA, DNS_SRV];
            // Inicializar um array para armazenar todos os registros
            $allRecords = [];
            $rawRecords = [];

            // Loop através dos tipos de registros e armazenar resultados
            foreach ($types as $type) {
                // Consultar os registros do tipo especificado
                $records = dns_get_record($domain, $type);

                if ($records) {
                    foreach ($records as $record) {
                        $allRecords[] = [
                            'host' => $record['host'],
                            'type' => get_dns_type_name($type),
                            'info' => get_record_info($type, $record)
                        ];
                        $rawRecords[] = $record; // Armazenar o registro bruto
                    }
                }
            }

            // Subdomínios específicos para consulta CNAME e TXT
            $subdomains = ['pop', 'imap', 'smtp', 'mail', 'webmail', 'autodiscover', 'www', '_dmarc', 'email-locaweb', '_autodiscover._tcp'];

            foreach ($subdomains as $subdomain) {
                $subdomainDomain = $subdomain . '.' . $domain;

                // Consultar os registros CNAME para o subdomínio
                $records = dns_get_record($subdomainDomain, DNS_CNAME);

                if ($records) {
                    foreach ($records as $record) {
                        $allRecords[] = [
                            'host' => $record['host'],
                            'type' => 'CNAME',
                            'info' => $record['target']
                        ];
                        $rawRecords[] = $record; // Armazenar o registro bruto
                    }
                }

                // Consultar os registros TXT para os subdomínios específicos
                $recordsTXT = dns_get_record($subdomainDomain, DNS_TXT);

                if ($recordsTXT) {
                    foreach ($recordsTXT as $record) {
                        $allRecords[] = [
                            'host' => $record['host'],
                            'type' => 'TXT',
                            'info' => implode(", ", $record['entries'])
                        ];
                        $rawRecords[] = $record; // Armazenar o registro bruto
                    }
                }
            }

            // **ADICIONAR AQUI A CONSULTA SRV PARA _autodiscover._tcp.dominio**
            $srvDomain = "_autodiscover._tcp." . $domain;
            $srvRecords = dns_get_record($srvDomain, DNS_SRV);

            if ($srvRecords) {
                foreach ($srvRecords as $record) {
                    $allRecords[] = [
                        'host' => $record['host'],
                        'type' => 'SRV',
                        'info' => " " . $record['pri'] . " " . $record['weight'] . " " . $record['port'] . " " . $record['target']
                    ];
                    $rawRecords[] = $record; // Armazenar o registro bruto
                }
            }

            // Exibir todos os registros em uma única tabela
            if (!empty($allRecords)) {
                echo "<h2>Registros DNS para: $domain</h2>";
                echo "<table border='1'>"; 
                echo "<tr><th>Host</th><th>Tipo</th><th>Informações</th></tr>";

               # foreach ($allRecords as $record) {
                #    echo "<tr><td>" . $record['host'] . "</td><td>" . $record['type'] . "</td><td>" . $record['info'] . "</td></tr>";
		# }

		$mxPermitidos = ['mx.a.locaweb.com.br', 'mx.b.locaweb.com.br', 'mx.core.locaweb.com.br', 'mx.jk.locaweb.com.br'];
$nsPermitidos = ['ns1.locaweb.com.br', 'ns2.locaweb.com.br', 'ns3.locaweb.com.br'];

foreach ($allRecords as $record) {
    $classe = '';

    if ($record['type'] === 'MX' && in_array(strtolower($record['info']), $mxPermitidos)) {
        $classe = 'highlight-green';
    }

    if ($record['type'] === 'NS' && in_array(strtolower($record['info']), $nsPermitidos)) {
        $classe = 'highlight-green';
    }

    echo "<tr>";
    echo "<td>" . htmlspecialchars($record['host']) . "</td>";
    echo "<td>" . htmlspecialchars($record['type']) . "</td>";
    echo "<td class='$classe'>" . htmlspecialchars($record['info']) . "</td>";
    echo "</tr>";
}


                echo "</table>";
            } else {
                echo "<p>Nenhum registro encontrado para o domínio $domain.</p>";
            }

            // Exibir os dados brutos
           # echo "<h3>Dados Brutos da Consulta:</h3>";
           # echo "<pre>";
           # print_r($rawRecords);
	    # echo "</pre>";


	        echo "<script>";
		echo "const rawData = " . json_encode($rawRecords, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . ";";
		echo "console.log('Dados brutos da consulta DNS:', rawData);";
		echo "</script>"; 
        }

        // Função para mapear os tipos de registros
        function get_dns_type_name($type)
        {
            switch ($type) {
                case DNS_A:
                    return "A";
                case DNS_AAAA:
                    return "AAAA";
                case DNS_MX:
                    return "MX";
                case DNS_NS:
                    return "NS";
                case DNS_TXT:
                    return "TXT";
                case DNS_CNAME:
                    return "CNAME";
                case DNS_SOA:
                    return "SOA";
                case DNS_SRV:
                    return "SRV"; // SRV foi adicionado aqui
                default:
                    return "Desconhecido";
            }
        }

        // Função para extrair as informações específicas do tipo de registro
        function get_record_info($type, $record)
        {
            switch ($type) {
                case DNS_A:
                    return $record['ip'];
                case DNS_CNAME:
                    return $record['target'];
                case DNS_TXT:
                    return implode(", ", $record['entries']);
                case DNS_NS:
                    return $record['target'];
                case DNS_MX:
                    return $record['target'];
                case DNS_SOA:
                    return $record['mname'];
                case DNS_SRV:
                    // Para SRV, exibimos as informações como [pri] [weight] [port] [target]
                    return $record['pri'] . " " . $record['weight'] . " " . $record['port'] . " " . $record['target'];
                default:
                    return "";
            }
        }
?>
    </div>
</main>
</body>
</html>


<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta DNS Rápida</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts e CSS customizado -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="d-flex align-items-center justify-content-center position-relative">
                <img src="assets/miku.png" alt="Toggle Theme" id="theme-toggle-icon" style="width: 50px; height: 50px; position: absolute; left: 0;">
                <h1 class="text-center">Consulta de Registros DNS</h1>
            </div>
        </div>
    </header>

    <main class="container mt-4">
        <form id="dns-form">
            <div class="input-group input-group-lg mb-3">
                <input type="text" id="domain" name="domain" class="form-control" placeholder="Digite um ou mais domínios, separados por vírgula" required>
                <button type="submit" class="btn btn-primary">Consultar</button>
            </div>
        </form>

        <div id="results-area">
            <!-- O conteúdo da tabela será inserido aqui via JavaScript -->
        </div>
    </main>

    <!-- jQuery e Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            const themeToggleIcon = $('#theme-toggle-icon');
            const body = $('body');

            // Função para aplicar o tema
            const applyTheme = (theme) => {
                if (theme === 'dark') {
                    body.addClass('dark-mode');
                    themeToggleIcon.attr('src', 'assets/teto.png');
                } else {
                    body.removeClass('dark-mode');
                    themeToggleIcon.attr('src', 'assets/miku.png');
                }
            };

            // Verifica o tema salvo no carregamento da página
            const savedTheme = localStorage.getItem('theme') || 'light';
            applyTheme(savedTheme);

            // Evento de clique para trocar o tema
            themeToggleIcon.on('click', function() {
                const currentTheme = localStorage.getItem('theme') || 'light';
                const newTheme = currentTheme === 'light' ? 'dark' : 'light';
                localStorage.setItem('theme', newTheme);
                applyTheme(newTheme);
            });

            // Lógica do formulário DNS
            $('#dns-form').on('submit', function(e) {
                e.preventDefault();
                const domainsInput = $('#domain').val().trim();
                if (!domainsInput) return;

                const resultsArea = $('#results-area');
                resultsArea.html(`<div class="d-flex justify-content-center mt-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div></div>`);

                const domains = domainsInput.split(',').map(d => d.trim()).filter(d => d);
                resultsArea.empty();

                domains.forEach(domain => {
                    $.ajax({
                        url: 'dns-consulta.php',
                        type: 'POST',
                        dataType: 'json',
                        data: { domain: domain },
                        success: function(response) {
                            let tableHtml = `<h2 class="mt-4">Registros para: ${response.domain}</h2>`;
                            if (response.error) {
                                tableHtml += `<div class="alert alert-danger">${response.error}</div>`;
                            } else if (response.records && response.records.length > 0) {
                                tableHtml += `<div class="table-responsive"><table class="table table-bordered table-hover">` +
                                    `<thead class="table-light"><tr><th>Host</th><th>Tipo</th><th>Informações</th></tr></thead><tbody>`;
                                
                                response.records.sort((a, b) => {
                                    if (a.host < b.host) return -1;
                                    if (a.host > b.host) return 1;
                                    if (a.type < b.type) return -1;
                                    if (a.type > b.type) return 1;
                                    return 0;
                                });

                                response.records.forEach(record => {
                                    const highlightClass = record.highlight ? 'table-info' : '';
                                    tableHtml += `<tr class="align-middle ${highlightClass}">` +
                                        `<td>${record.host}</td>` +
                                        `<td><span class="badge bg-secondary">${record.type}</span></td>` +
                                        `<td style="word-break: break-all;">${record.info}</td></tr>`;
                                });
                                tableHtml += '</tbody></table></div>';
                            } else {
                                tableHtml += '<div class="alert alert-info">Nenhum registro DNS encontrado.</div>';
                            }
                            resultsArea.append(tableHtml);
                        },
                        error: function() {
                            resultsArea.append(`<h2 class="mt-4">Erro ao consultar ${domain}</h2><div class="alert alert-danger">Ocorreu um erro no servidor.</div>`);
                        },
                        complete: function() {
                            $('.spinner-border').parent().remove();
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>

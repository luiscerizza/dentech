<?php
require_once 'conexao/conexao.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT paciente, cpf, rg FROM prontuarios WHERE id = ?");
$stmt->execute([$id]);
$paciente = $stmt->fetch();

if (!$paciente) {
    die("Paciente não encontrado.");
}

$isPrint = isset($_GET['print']) && $_GET['print'] == '1';

$nome = htmlspecialchars($paciente['paciente']);
$data_hoje = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termos de Consentimento - Dentech</title>
    <style>
        :root {
            --roxo-escuro: #5d3a8c;
            --roxo-medio: #8a5ebf;
            --roxo-claro: #c9a7e8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Times New Roman', Georgia, serif;
        }

        body {
            background: white;
            color: #000;
            padding: 25mm;
            line-height: 1.6;
            font-size: 12pt;
        }

        .container {
            max-width: 210mm;
            margin: 0 auto;
        }

        h1 {
            text-align: center;
            font-size: 16pt;
            margin: 0 0 18pt;
            color: var(--roxo-escuro);
            text-transform: uppercase;
            page-break-after: avoid;
        }

        /* === DADOS DO PACIENTE === */
        .dados-identificacao {
            background: #f8f6fc;
            padding: 12pt;
            border-left: 3pt solid var(--roxo-medio);
            margin: 16pt 0;
            font-weight: bold;
            font-size: 11pt;
            line-height: 1.4;
        }

        /* === DECLARAÇÃO INICIAL === */
        .declaracao-inicial {
            text-align: justify;
            text-indent: 0;
            font-weight: bold;
            margin: 16pt 0;
            line-height: 1.5;
            background: #f9f9f9;
            padding: 12pt;
            border-radius: 4pt;
        }

        /* === CONTEÚDO DO TERMO === */
        .conteudo-termo h2 {
            font-size: 13pt;
            color: var(--roxo-medio);
            margin: 20pt 0 12pt;
            padding-bottom: 6pt;
            border-bottom: 1pt solid #eee;
            page-break-after: avoid;
        }

        .conteudo-termo p {
            text-align: justify;
            line-height: 1.6;
            margin-bottom: 10pt;
            text-indent: 0;
        }

        .conteudo-termo ul {
            margin: 12pt 0 20pt 25pt;
            padding-left: 10pt;
            list-style-type: disc;
            line-height: 1.6;
        }

        .conteudo-termo li {
            margin-bottom: 8pt;
        }

        /* === ASSINATURAS === */
        .assinatura {
            margin-top: 40pt;
            display: flex;
            justify-content: space-between;
            page-break-inside: avoid;
        }

        .linha-assinatura {
            width: 220px;
            border-top: 1px solid #000;
            padding-top: 4pt;
            text-align: center;
            font-size: 11pt;
        }

        /* === RODAPÉ === */
        .pagina-rodape {
            margin-top: 30pt;
            text-align: center;
            font-size: 10pt;
            color: #666;
            page-break-inside: avoid;
        }

        /* === BOTÕES (apenas em tela) === */
        @media screen {
            .btn-imprimir {
                padding: 8px 20px;
                background: var(--roxo-escuro);
                color: white;
                border: none;
                border-radius: 6px;
                font-size: 14px;
                cursor: pointer;
            }

            .btn-imprimir:hover {
                background: #4a2d70;
            }
        }

        /* === IMPRESSÃO === */
        @media print {
            body {
                padding: 15mm;
            }

            .btn-imprimir,
            a[href] {
                display: none !important;
            }

            h1,
            h2 {
                color: #000 !important;
                border-color: #000 !important;
            }
        }
    </style>
</head>

<body>

    <!-- TERMO DE CONSENTIMENTO LIVRE E ESCLARECIDO -->
    <div class="termo">
        <h1>TERMO DE CONSENTIMENTO LIVRE E ESCLARECIDO (TCLE)</h1>

        <!-- DADOS DO PACIENTE -->
        <div class="dados-identificacao">
            <strong>Paciente:</strong> <?= $nome ?><br>
            <strong>CPF:</strong> <?= htmlspecialchars($paciente['cpf'] ?? '__________') ?><br>
            <strong>RG:</strong> <?= htmlspecialchars($paciente['rg'] ?? '__________') ?>
        </div>

        <!-- DECLARAÇÃO INICIAL -->
        <p class="declaracao-inicial">
            Eu, acima identificado(a), declaro que fui devidamente informado(a) e estou de acordo com os termos abaixo,
            nos moldes da Lei Geral de Proteção de Dados Pessoais – LGPD (Lei nº 13.709/2018, com redação dada pela Lei nº 13.853/2019).
        </p>

        <!-- SEÇÕES NUMERADAS -->
        <div class="conteudo-termo">
            <h2>1. Finalidade do Tratamento de Dados</h2>
            <p>A Dentista Katia Gonçalves de Jesus, inscrita no CRO-SP nº 135972, realiza a coleta e o tratamento de meus dados pessoais e dados pessoais sensíveis exclusivamente para as seguintes finalidades:</p>
            <ul>
                <li>Prestação de serviços odontológicos;</li>
                <li>Cumprimento de obrigações legais, regulatórias e éticas;</li>
                <li>Gestão administrativa, financeira e contratual;</li>
                <li>Comunicação relacionada à saúde bucal, tratamentos, orientações e agendamentos.</li>
            </ul>

            <h2>2. Dados Pessoais Tratados</h2>
            <p>Serão tratados, quando necessários à prestação dos serviços odontológicos:</p>
            <ul>
                <li>dados cadastrais e de identificação;</li>
                <li>informações clínicas e históricas de saúde;</li>
                <li>anamnese, exames, imagens radiográficas e fotográficas;</li>
                <li>demais dados indispensáveis à adequada execução dos serviços, conforme o Código de Ética Odontológica.</li>
            </ul>

            <h2>3. Base Legal do Tratamento</h2>
            <p>O tratamento dos dados pessoais e sensíveis ocorre com fundamento:</p>
            <ul>
                <li>no art. 7º, inciso VI, da LGPD, para o exercício regular de direitos em processo judicial, administrativo ou arbitral;</li>
                <li>no art. 11, inciso II, alíneas “a” e “f”, da LGPD, para a prestação de serviços de saúde;</li>
                <li>e no art. 10, inciso I, da LGPD, em razão do legítimo interesse da profissional na adequada execução, comprovação e defesa da regular prestação dos serviços odontológicos.</li>
            </ul>

            <h2>4. Compartilhamento de Dados</h2>
            <p>Meus dados poderão ser compartilhados, quando estritamente necessário, com:</p>
            <ul>
                <li>laboratórios de prótese;</li>
                <li>operadoras ou planos odontológicos;</li>
                <li>profissionais de apoio à atividade clínica;</li>
                <li>autoridades sanitárias, fiscais ou judiciais, mediante obrigação legal ou determinação de autoridade competente.</li>
            </ul>
            <p>O compartilhamento sempre ocorrerá nos limites da legislação vigente.</p>

            <h2>5. Direitos do Titular dos Dados</h2>
            <p>Nos termos do art. 18 da LGPD, estou ciente de que posso, a qualquer momento:</p>
            <ul>
                <li>confirmar a existência de tratamento de meus dados;</li>
                <li>acessar os dados tratados;</li>
                <li>solicitar correção de dados incompletos, inexatos ou desatualizados;</li>
                <li>solicitar anonimização, bloqueio ou eliminação de dados excessivos ou desnecessários, quando aplicável;</li>
                <li>obter informações sobre o compartilhamento de dados.</li>
            </ul>

            <h2>6. Segurança e Sigilo</h2>
            <p>A clínica adota medidas técnicas e administrativas aptas a proteger meus dados pessoais contra acessos não autorizados e situações acidentais ou ilícitas de destruição, perda, alteração, comunicação ou qualquer forma de tratamento inadequado ou ilícito, mantendo o sigilo profissional em conformidade com a legislação e normas éticas aplicáveis.</p>

            <h2>7. Prazo de Armazenamento dos Dados</h2>
            <p>Os dados pessoais serão armazenados pelo prazo exigido pela legislação vigente, pelas normas do Conselho Federal de Odontologia e enquanto necessários para a defesa de direitos e responsabilidades da profissional.</p>

            <h2>8. Revogação do Consentimento</h2>
            <p>Declaro estar ciente de que posso revogar este consentimento a qualquer momento, mediante solicitação formal. A revogação não afetará a licitude do tratamento realizado até a data da solicitação, nos termos da LGPD.</p>

            <h2>9. Validade</h2>
            <p>Este termo possui validade por prazo indeterminado, podendo ser atualizado conforme alterações legislativas ou mudanças nas práticas da clínica.</p>
        </div>
    </div>

    <!-- ASSINATURAS -->
    <div class="assinatura">
        <div class="linha-assinatura">
            <?= $nome ?><br>
            <em>Paciente</em>
        </div>
        <div class="linha-assinatura">
            <?= $data_hoje ?><br>
            <em>Data</em>
        </div>
    </div>

    <!-- RODAPÉ -->
    <div class="pagina-rodape">
        Endereço: Siqueira Campos 1100 sala 03 – São João – Araçatuba/SP<br>
        Telefone: (18) 98190-4484
    </div>
    </div>

    <!-- BOTÕES -->
    <?php if (!$isPrint): ?>
        <div style="text-align:center; margin-top:20pt;">
            <button class="btn-imprimir" onclick="window.print()"
                style="padding:8px 20px; background:#5d3a8c; color:white; border:none; border-radius:6px;">
                🖨️ Imprimir Termo
            </button>
            <a href="visualizar_prontuario.php?id=<?= $id ?>" style="margin-left:12px; color:#5d3a8c; text-decoration:none;">
                Voltar ao Prontuário
            </a>
        </div>
    <?php endif; ?>

    <script>
        // Detectar modo de impressão
        const urlParams = new URLSearchParams(window.location.search);
        const isPrint = urlParams.get('print') === '1';

        if (isPrint) {
            window.print();
        }
    </script>
</body>

</html>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Visualização de Dados WiFi">
    <title>Dados de Acesso</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        .form-signin {
            width: 100%;
            max-width: 330px;
            padding: 15px;
            margin: auto;
        }
        /* Força o conteúdo da tabela a ficar em uma única linha */
        .table th, .table td {
            white-space: nowrap;
        }
        /* Estilo para cortar textos muito longos com "..." */
        .truncate {
            max-width: 250px; /* Defina uma largura máxima para a coluna */
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body>

<div class="container-fluid mt-4">
    <h2 class="text-center">Dados Acesso WIFI</h2>

    <?php
    if (isset($_POST['inputsenha']) && $_POST['inputsenha'] == "charlesl21") {
    ?>
    
    <div class="table-responsive mt-4">
        <table class="table table-striped table-sm table-hover table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>CPF</th>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Empresa</th>
                    <th>Telefone</th>
                    <th>Site Acessado</th>
                    <th>MAC</th>
                    <th>IP</th>
                    <th>Data Cadastro</th>
                </tr>
            </thead>
            <tbody>
                <?php
                include "db.php";
                $query = "SELECT * FROM dados ORDER BY id DESC";
                $result = $MySQLi->query($query);

                while ($fetch = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($fetch['id']) . "</td>";
                    echo "<td>" . htmlspecialchars($fetch['cpf']) . "</td>";
                    echo "<td>" . htmlspecialchars($fetch['nome']) . "</td>";
                    echo "<td>" . htmlspecialchars($fetch['email']) . "</td>";
                    echo "<td>" . htmlspecialchars($fetch['empresa']) . "</td>";
                    echo "<td>" . htmlspecialchars($fetch['telefone']) . "</td>";
                    // CÓDIGO EDITADO: Adicionada a classe 'truncate' para cortar o link
                    echo "<td class='truncate' title='" . htmlspecialchars($fetch['link_orig']) . "'>" . htmlspecialchars($fetch['link_orig']) . "</td>";
                    echo "<td>" . htmlspecialchars($fetch['mac']) . "</td>";
                    echo "<td>" . htmlspecialchars($fetch['ip']) . "</td>";
                    echo "<td>" . htmlspecialchars($fetch['data_cadastro']) . "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <?php
    } else {
        echo "
        <div class='form-signin'>
            <form id='form' method='post' action='dados.php'>
                <h1 class='h3 mb-3 font-weight-normal'>Acesso Restrito</h1>
                <input name='inputsenha' type='password' class='form-control' placeholder='Senha' required autofocus>
                <button class='btn btn-lg btn-primary btn-block mt-3' type='submit'>Acessar</button>
            </form>
        </div>
        ";
    }
    ?>

</div>

</body>
</html>
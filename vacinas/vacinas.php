<?php
// Inicia a sessão
if (session_status() == PHP_SESSION_NONE) { // Inicia apenas se não estiver ativa
    session_start();
}

// Verifica se o usuário está logado
if (!isset($_SESSION['id_usuario'])) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Sessão expirada ou usuário não logado.', 'sessao_expirada' => true]); // Adiciona flag
        exit();
    }
    header('Location: ../Tela_de_site/login.php');
    exit();
}

require_once('../conecta_db.php'); 
$conn_global = conecta_db(); 

if (isset($_REQUEST['acao'])) {
    header('Content-Type: application/json');
    $conn_acao = conecta_db(); 

    if (!$conn_acao) {
        echo json_encode(['error' => 'Falha na conexão com o banco de dados ao processar ação.']);
        exit();
    }

    $acao = $_REQUEST['acao'];

    switch ($acao) {
        case 'listar_vacinas':
            $pet_id_get = isset($_GET['pet_id']) ? intval($_GET['pet_id']) : 0;
            $vacinas_list = [];
            if ($pet_id_get > 0) {
                $query = "SELECT id, pet_id, nome, data_aplicacao, lote, observacoes
                          FROM Vacinas
                          WHERE pet_id = ?
                          ORDER BY data_aplicacao DESC, id DESC";
                $stmt = $conn_acao->prepare($query);
                if ($stmt) {
                    $stmt->bind_param("i", $pet_id_get);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        while ($row = $result->fetch_assoc()) {
                            $vacinas_list[] = $row;
                        }
                    } else {
                         $vacinas_list = ['error' => 'Erro ao executar listagem: ' . $stmt->error];
                    }
                    $stmt->close();
                } else {
                    $vacinas_list = ['error' => 'Erro ao preparar listagem: ' . $conn_acao->error];
                }
            } else {
                $vacinas_list = ['error' => 'ID do Pet não fornecido para listagem.'];
            }
            echo json_encode($vacinas_list);
            break;

        case 'obter_vacina':
            $id_vacina_get = isset($_GET['id_vacina']) ? intval($_GET['id_vacina']) : 0;
            $vacina_data = ['error' => 'ID da vacina não fornecido ou inválido'];
            if ($id_vacina_get > 0) {
                $query = "SELECT id, pet_id, nome, data_aplicacao, lote, observacoes
                          FROM Vacinas
                          WHERE id = ?";
                $stmt = $conn_acao->prepare($query);
                if ($stmt) {
                    $stmt->bind_param("i", $id_vacina_get);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        $data = $result->fetch_assoc();
                        if ($data) {
                            $vacina_data = $data;
                        } else {
                            $vacina_data = ['error' => 'Vacina não encontrada.'];
                        }
                    } else {
                        $vacina_data = ['error' => 'Erro ao executar obtenção: ' . $stmt->error];
                    }
                    $stmt->close();
                } else {
                    $vacina_data = ['error' => 'Erro ao preparar obtenção: ' . $conn_acao->error];
                }
            }
            echo json_encode($vacina_data);
            break;

        case 'excluir_vacina':
            $response = ['success' => false, 'message' => 'ID da vacina inválido ou não fornecido.'];
            $id_vacina_req = isset($_REQUEST['id_vacina']) ? intval($_REQUEST['id_vacina']) : 0;

            if ($id_vacina_req > 0) {
                $sql = "DELETE FROM Vacinas WHERE id = ?";
                $stmt = $conn_acao->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("i", $id_vacina_req);
                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                            $response = ['success' => true, 'message' => 'Vacina excluída com sucesso!'];
                        } else {
                            $response['message'] = 'Nenhuma vacina encontrada com este ID para excluir.';
                        }
                    } else {
                        $response['message'] = 'Erro ao excluir vacina: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                     $response['message'] = 'Erro ao preparar exclusão: ' . $conn_acao->error;
                }
            }
            echo json_encode($response);
            break;

        default:
            echo json_encode(['error' => 'Ação desconhecida.']);
            break;
    }
    if ($conn_acao) $conn_acao->close();
    exit();
}

// Processamento do formulário principal (Adicionar/Editar Vacina)
$mensagem = '';
$tipo_mensagem = ''; 
$pet_id_formulario_submetido = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nome_vacina'])) {
    if (!$conn_global) { // Re-conecta se a conexão global não estiver ativa
        $conn_global = conecta_db();
    }

    if (!$conn_global) {
        $mensagem = "Erro crítico: Falha na conexão com o banco de dados.";
        $tipo_mensagem = "erro";
    } else {
        $pet_id_form = $_POST['pet_id'];
        $pet_id_formulario_submetido = $pet_id_form;
        $nome_vacina_form = trim($_POST['nome_vacina']);
        $data_aplicacao_form = $_POST['data_vacina'];
        $lote_form = trim($_POST['lote']);
        $observacoes_form = !empty($_POST['reforco']) ? trim($_POST['reforco']) : NULL; // reforco é o name no form
        $id_vacina_para_editar = isset($_POST['id_vacina_edit']) && !empty($_POST['id_vacina_edit']) ? intval($_POST['id_vacina_edit']) : null;

        if (empty($pet_id_form) || empty($nome_vacina_form) || empty($data_aplicacao_form)) {
            $mensagem = "Pet, Nome da Vacina e Data da Aplicação são obrigatórios.";
            $tipo_mensagem = "erro";
        } else {
            if ($id_vacina_para_editar) { // Edição
                $sql = "UPDATE Vacinas SET pet_id = ?, nome = ?, data_aplicacao = ?, lote = ?, observacoes = ? WHERE id = ?";
                $stmt = $conn_global->prepare($sql);
                $stmt->bind_param("issssi", $pet_id_form, $nome_vacina_form, $data_aplicacao_form, $lote_form, $observacoes_form, $id_vacina_para_editar);
                if ($stmt->execute()) {
                    $mensagem = "Vacina atualizada com sucesso!";
                    $tipo_mensagem = "sucesso";
                } else {
                    $mensagem = "Erro ao atualizar vacina: " . $stmt->error;
                    $tipo_mensagem = "erro";
                }
            } else { // Inserção
                $sql = "INSERT INTO Vacinas (pet_id, nome, data_aplicacao, lote, observacoes) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn_global->prepare($sql);
                $stmt->bind_param("issss", $pet_id_form, $nome_vacina_form, $data_aplicacao_form, $lote_form, $observacoes_form);
                if ($stmt->execute()) {
                    $mensagem = "Vacina registrada com sucesso!";
                    $tipo_mensagem = "sucesso";
                } else {
                    $mensagem = "Erro ao registrar vacina: " . $stmt->error;
                    $tipo_mensagem = "erro";
                }
            }
            if (isset($stmt)) $stmt->close();
        }
    }
}

// Busca todos os pets para o dropdown
$pets_dropdown = [];
if ($conn_global) {
    $query_pets = "SELECT id_pet, nome FROM Pets ORDER BY nome";
    $result_pets = $conn_global->query($query_pets);
    if ($result_pets) {
        while ($row_pet = $result_pets->fetch_assoc()) {
            $pets_dropdown[] = $row_pet;
        }
    } else {
        // Não define mensagem para não sobrescrever a do formulário
    }
} else {
    if (empty($mensagem)) {
      $mensagem = "Não foi possível conectar ao banco para carregar a lista de pets.";
      $tipo_mensagem = "erro";
    }
}

require_once('../includes/header.php');
require_once('../includes/sidebar.php');
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Controle de Vacinas - PetPlus</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Quicksand', sans-serif; background-color: #f5f7fa; margin: 0; padding: 0; }
        .container { margin-left: 180px; padding: 20px; transition: margin-left 0.3s; }
        .card { background-color: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); max-width: 800px; margin: 0 auto 30px; }
        h1, h2 { color: #003b66; text-align: center; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 6px; font-weight: bold; color: #003b66; }
        input[type="text"], input[type="date"], select, textarea { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ccc; font-size: 14px; box-sizing: border-box; }
        .btn-submit, .btn-cancel { color: white; padding: 12px; border: none; font-size: 16px; border-radius: 6px; cursor: pointer; width: auto; min-width: 150px; font-weight: bold; margin-right: 10px; }
        .btn-submit { background-color: #003b66; }
        .btn-submit:hover { background-color: #002b4d; }
        .btn-cancel { background-color: #6c757d; }
        .btn-cancel:hover { background-color: #5a6268; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th, table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        table th { background-color: #f2f2f2; color: #003b66; }
        .btn-edit, .btn-danger { color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; margin-right: 5px; font-size: 0.9em; }
        .btn-edit { background-color: #ffc107; }
        .btn-edit:hover { background-color: #e0a800; }
        .btn-danger { background-color: #dc3545; }
        .btn-danger:hover { background-color: #c82333; }
        @media (max-width: 768px) {
            .container { margin-left: 0; padding: 15px; }
            .sidebar.active + .container { margin-left: 60px; }
            .card { padding: 20px; }
            .btn-submit, .btn-cancel { width: 100%; margin-bottom: 10px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Controle de Vacinas</h1>

            <div class="form-group">
                <label for="selectPet">Selecione o Pet:</label>
                <select id="selectPet" onchange="selecionarPet(this.value)">
                    <option value="">-- Selecione um Pet --</option>
                    <?php foreach ($pets_dropdown as $pet_item): ?>
                        <option value="<?php echo $pet_item['id_pet']; ?>" <?php echo ($pet_id_formulario_submetido == $pet_item['id_pet'] ? 'selected' : ''); ?>>
                            <?php echo htmlspecialchars($pet_item['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <form id="formVacina" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" style="display: none;">
                <input type="hidden" id="vacina_pet_id_form" name="pet_id" value="">
                <input type="hidden" id="id_vacina_edit" name="id_vacina_edit" value="">

                <div class="form-group">
                    <label for="nome_vacina">Nome da Vacina:</label>
                    <input type="text" id="nome_vacina" name="nome_vacina" required />
                </div>

                <div class="form-group">
                    <label for="data_vacina">Data da Aplicação:</label>
                    <input type="date" id="data_vacina" name="data_vacina" required />
                </div>

                <div class="form-group">
                    <label for="lote">Lote:</label>
                    <input type="text" id="lote" name="lote" />
                </div>

                <div class="form-group">
                    <label for="reforco">Observações/Data Reforço (Opcional):</label>
                    <input type="text" id="reforco" name="reforco" />
                </div>

                <button type="button" id="btnSubmitForm" class="btn-submit">Registrar Vacina</button> <button type="button" id="btnCancelEdit" class="btn-cancel" style="display: none;" onclick="resetarFormularioVacina()">Cancelar Edição</button>
            </form>

            <div id="historico-vacina" style="display: none; margin-top: 30px;">
                <h2>Histórico de Vacinas do Pet</h2>
                <table id="tabela-vacina">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Data Aplicação</th>
                            <th>Lote</th>
                            <th>Observações/Reforço</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="lista-vacinas"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const NOME_ARQUIVO_PHP = "<?php echo basename($_SERVER["PHP_SELF"]); ?>";
        let petSelecionadoGlobal = null;
        const formVacinaEl = document.getElementById('formVacina');
        const inputPetIdForm = document.getElementById('vacina_pet_id_form');
        const inputIdVacinaEdit = document.getElementById('id_vacina_edit');
        const inputNomeVacina = document.getElementById('nome_vacina');
        const inputDataVacina = document.getElementById('data_vacina');
        const inputLote = document.getElementById('lote');
        const inputReforco = document.getElementById('reforco'); // Corrigido para 'reforco' (name do input)
        const btnSubmitForm = document.getElementById('btnSubmitForm');
        const btnCancelEdit = document.getElementById('btnCancelEdit');

        function htmlspecialchars(str) {
            if (typeof str !== 'string') return String(str);
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return str.replace(/[&<>"']/g, m => map[m]);
        }

        function formatarData(dataString) {
            if (!dataString || dataString === '0000-00-00') return '-';
            const [year, month, day] = dataString.split('-');
            if (year && month && day) {
                const dateObj = new Date(Date.UTC(parseInt(year), parseInt(month) - 1, parseInt(day)));
                return dateObj.toLocaleDateString('pt-BR', { timeZone: 'UTC' });
            }
            return dataString;
        }

        function selecionarPet(petId) {
            petSelecionadoGlobal = petId;
            inputPetIdForm.value = petId; // Define o pet_id no formulário
            resetarFormularioVacina(); // Reseta o formulário e ajusta o botão

            if (petId) {
                carregarHistoricoVacinas(petId);
                document.getElementById('historico-vacina').style.display = 'block';
                formVacinaEl.style.display = 'block';
            } else {
                document.getElementById('historico-vacina').style.display = 'none';
                formVacinaEl.style.display = 'none';
            }
        }

        function carregarHistoricoVacinas(petId) {
            if (!petId) return;
            fetch(`${NOME_ARQUIVO_PHP}?acao=listar_vacinas&pet_id=${petId}&t=${new Date().getTime()}`)
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => { throw new Error(`Erro HTTP: ${response.status}. Detalhes: ${text}`); });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.error) {
                        console.error('Erro da API ao carregar histórico:', data.error);
                        if (data.sessao_expirada) {
                             Swal.fire('Sessão Expirada', 'Sua sessão expirou. Você será redirecionado para o login.', 'warning').then(() => window.location.href = '../Tela_de_site/login.php');
                        } else {
                            document.getElementById('lista-vacinas').innerHTML = `<tr><td colspan="5">Erro ao carregar vacinas: ${htmlspecialchars(data.error)}</td></tr>`;
                        }
                    } else {
                        renderizarTabelaVacinas(data);
                    }
                })
                .catch(error => {
                    console.error('Falha ao carregar histórico de vacinas:', error);
                    document.getElementById('lista-vacinas').innerHTML = `<tr><td colspan="5">Erro ao carregar vacinas. Verifique o console. Detalhe: ${htmlspecialchars(error.message)}</td></tr>`;
                });
        }

        function renderizarTabelaVacinas(vacinas) {
            const tbody = document.getElementById('lista-vacinas');
            tbody.innerHTML = '';
            if (!Array.isArray(vacinas) || vacinas.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5">Nenhum registro de vacina encontrado.</td></tr>';
                return;
            }
            vacinas.forEach(vacina => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${htmlspecialchars(vacina.nome)}</td>
                    <td>${formatarData(vacina.data_aplicacao)}</td>
                    <td>${vacina.lote ? htmlspecialchars(vacina.lote) : '-'}</td>
                    <td>${vacina.observacoes ? htmlspecialchars(vacina.observacoes) : '-'}</td>
                    <td>
                        <button class="btn-edit" onclick="prepararEdicao(${vacina.id})">Editar</button>
                        <button class="btn-danger" onclick="excluirRegistroVacina(${vacina.id})">Excluir</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function prepararEdicao(idVacinaRegistro) {
            fetch(`${NOME_ARQUIVO_PHP}?acao=obter_vacina&id_vacina=${idVacinaRegistro}&t=${new Date().getTime()}`)
                .then(response => response.json())
                .then(vacina => {
                    if (vacina && vacina.id) {
                        inputIdVacinaEdit.value = vacina.id;
                        inputPetIdForm.value = vacina.pet_id; // Mantém o pet_id
                        inputNomeVacina.value = vacina.nome;
                        inputDataVacina.value = vacina.data_aplicacao; // YYYY-MM-DD
                        inputLote.value = vacina.lote || '';
                        inputReforco.value = vacina.observacoes || '';
                        btnSubmitForm.textContent = 'Atualizar Vacina';
                        btnCancelEdit.style.display = 'inline-block';
                        formVacinaEl.scrollIntoView({ behavior: 'smooth' });
                    } else {
                         Swal.fire('Erro!', `Dados da vacina não encontrados para edição. Detalhe: ${htmlspecialchars(vacina.error || 'Resposta inválida da API.')}`, 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro ao buscar dados da vacina para edição:', error);
                    Swal.fire('Erro!', 'Ocorreu um erro ao buscar dados da vacina. Verifique o console.', 'error');
                });
        }

        function resetarFormularioVacina() {
            formVacinaEl.reset();
            inputIdVacinaEdit.value = '';
            inputPetIdForm.value = petSelecionadoGlobal || ''; // Restaura o pet_id selecionado
            btnSubmitForm.textContent = 'Registrar Vacina';
            btnCancelEdit.style.display = 'none';
        }

        function excluirRegistroVacina(idVacinaRegistro) {
            Swal.fire({
                title: 'Tem certeza?',
                text: "Você realmente deseja excluir este registro de vacina?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sim, excluir!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(`${NOME_ARQUIVO_PHP}?acao=excluir_vacina&id_vacina=${idVacinaRegistro}&t=${new Date().getTime()}`, { method: 'GET' }) // Pode ser POST
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Excluído!', data.message || 'Registro excluído com sucesso.', 'success');
                                if (petSelecionadoGlobal) {
                                    carregarHistoricoVacinas(petSelecionadoGlobal);
                                }
                                resetarFormularioVacina(); // Limpa o form se a vacina excluída estava em edição
                            } else {
                                Swal.fire('Erro!', data.message || 'Erro ao excluir o registro.', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Erro ao excluir registro de vacina:', error);
                            Swal.fire('Erro!', 'Ocorreu um erro ao tentar excluir. Verifique o console.', 'error');
                        });
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Listener para o botão de submit do formulário de vacina
            if (btnSubmitForm) {
                btnSubmitForm.addEventListener('click', function(event) {
                    event.preventDefault();

                    const petId = inputPetIdForm.value;
                    const nomeVacina = inputNomeVacina.value.trim();
                    const dataVacina = inputDataVacina.value;

                    if (!petId) {
                        Swal.fire('Atenção!', 'Por favor, selecione um pet primeiro.', 'warning');
                        return;
                    }
                    if (!nomeVacina) {
                        Swal.fire('Atenção!', 'O nome da vacina é obrigatório.', 'warning');
                        inputNomeVacina.focus();
                        return;
                    }
                    if (!dataVacina) {
                        Swal.fire('Atenção!', 'A data da aplicação é obrigatória.', 'warning');
                        inputDataVacina.focus();
                        return;
                    }

                    const isEditing = inputIdVacinaEdit.value !== '';
                    const acaoTexto = isEditing ? "atualizar os dados desta vacina" : "registrar esta nova vacina";
                    const tituloConfirmacao = isEditing ? "Confirmar Atualização?" : "Confirmar Registro?";

                    Swal.fire({
                        title: tituloConfirmacao,
                        text: `Você tem certeza que deseja ${acaoTexto}?`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#003b66',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sim, confirmar!',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            formVacinaEl.submit();
                        }
                    });
                });
            }

            // Inicialização e mensagens PHP
            const petIdInicial = document.getElementById('selectPet').value;
            if (petIdInicial) {
                selecionarPet(petIdInicial);
            }

            <?php
            if (!empty($mensagem) && isset($tipo_mensagem)) {
                $swal_icon = 'info';
                $swal_title = 'Atenção!';
                if ($tipo_mensagem === 'sucesso') {
                    $swal_icon = 'success';
                    $swal_title = 'Sucesso!';
                } elseif ($tipo_mensagem === 'erro') {
                    $swal_icon = 'error';
                    $swal_title = 'Erro!';
                }
                echo "
                Swal.fire({
                    title: '" . addslashes($swal_title) . "',
                    text: '" . addslashes($mensagem) . "',
                    icon: '" . $swal_icon . "',
                    confirmButtonText: 'OK'
                });";
            }
            ?>
        });
    </script>
</body>
</html>
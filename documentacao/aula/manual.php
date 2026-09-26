<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manual da aula — banco, cadastro e usuários</title>
    <style>
        :root {
            --ink: #15202b;
            --green: #0d2a26;
            --accent: #2f8f73;
            --paper: #f6f3eb;
            --line: #d5d0c4;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: var(--ink);
            font-family: "Iowan Old Style", Palatino, Georgia, serif;
            line-height: 1.55;
            background: #fff;
        }
        h1, h2, h3, .kicker, nav, table, code, pre, .chip, .btn {
            font-family: "Avenir Next", "Segoe UI", Helvetica, sans-serif;
        }
        .wrap { max-width: 820px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }
        .banner {
            background: var(--green);
            color: var(--paper);
            padding: 1.5rem 1.25rem;
        }
        .banner h1 { margin: 0.3rem 0 0; font-size: 1.8rem; }
        .kicker {
            text-transform: uppercase;
            letter-spacing: 0.14em;
            font-size: 0.72rem;
            color: var(--accent);
            font-weight: 700;
            margin: 0;
        }
        .banner .kicker { color: #8fd4bc; }
        h2 {
            border-bottom: 3px solid var(--accent);
            padding-bottom: 0.35rem;
            color: var(--green);
        }
        nav a { color: var(--green); }
        table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
        th, td { text-align: left; vertical-align: top; padding: 0.55rem 0.6rem; border-bottom: 1px solid var(--line); }
        th { background: var(--green); color: var(--paper); }
        tr:nth-child(even) td { background: var(--paper); }
        code {
            background: #eef3ef;
            padding: 0.1em 0.35em;
            font-size: 0.88em;
        }
        pre {
            background: #15202b;
            color: #e8f0ea;
            padding: 1rem;
            overflow-x: auto;
            font-size: 0.82rem;
            line-height: 1.45;
        }
        .note {
            border-left: 4px solid #b8893a;
            background: #fbf6ea;
            padding: 0.85rem 1rem;
            margin: 1rem 0;
        }
        .warn {
            border-left: 4px solid #9c2b1a;
            background: #f8ece8;
            padding: 0.85rem 1rem;
            margin: 1rem 0;
        }
        .flow {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            align-items: center;
            font-family: "Avenir Next", Helvetica, sans-serif;
            font-size: 0.85rem;
            margin: 1rem 0;
        }
        .chip {
            border: 1.5px solid var(--green);
            padding: 0.4rem 0.55rem;
            background: var(--paper);
        }
        .chip.dark { background: var(--green); color: var(--paper); }
        ol.files { padding-left: 1.2rem; }
        ol.files li { margin: 0.45rem 0; }
    </style>
</head>
<body>
    <header class="banner">
        <p class="kicker">Aula de hoje · Clube de Mahjong</p>
        <h1>Manual: banco, cadastro e gerência de usuários</h1>
        <p>Três tarefas, no padrão MVC. Não misturem SQL na view nem HTML no model.</p>
    </header>

    <div class="wrap">
        <nav>
            <p><strong>Tarefas</strong></p>
            <ol>
                <li><a href="#banco">Nova definição do banco</a></li>
                <li><a href="#recriar">Reconfigurar o SQLite na próxima execução</a></li>
                <li><a href="#cadastro">Arrumar o registro do usuário</a></li>
                <li><a href="#gerencia">Criar a gerência de usuários</a></li>
            </ol>
        </nav>

        <p class="flow">
            <span class="chip dark">Usuário</span> →
            <span class="chip">Front controller</span> →
            <span class="chip">Controller</span> →
            <span class="chip">Model</span> →
            <span class="chip">Controller</span> →
            <span class="chip">View</span> →
            <span class="chip dark">Usuário</span>
        </p>
        <p>Copiem o dashboard: <code>DashboardController</code> pede dados ao model, recebe o resultado e só então chama a view. Modelo completo no PDF <code>documentacao/mvc/guia-mvc.pdf</code>.</p>

        <section id="banco">
            <p class="kicker">Tarefa 1</p>
            <h2>Onde colocar a definição do banco</h2>
            <p>A definição das tabelas <strong>não vai em PHP</strong>. Vai em SQL, neste arquivo:</p>
            <p><code><?= htmlspecialchars($root) ?>/database/schema.sql</code></p>
            <p>Esse caminho está em <code>config.php</code> (<code>schema_path</code>). O programa lê o arquivo em <code>src/db.php</code> e executa o SQL na abertura da conexão.</p>
            <table>
                <thead>
                    <tr><th>Arquivo</th><th>O que vocês fazem</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>database/schema.sql</code></td>
                        <td><strong>Substituam</strong> (ou completem) pelo novo schema da aula: tabelas, colunas, chaves. É o arquivo principal desta tarefa.</td>
                    </tr>
                    <tr>
                        <td><code>database/seed.sql</code></td>
                        <td>Ajustem o INSERT do admin se as colunas de <code>users</code> (ou outras) mudarem. Hoje o seed cria o usuário <code>admin</code> / senha <code>admin</code>.</td>
                    </tr>
                    <tr>
                        <td><code>config.php</code></td>
                        <td>Só mexam se o professor pedir outro nome de arquivo. Não criem um segundo schema “solto” fora desses caminhos: o PHP não vai lê-lo.</td>
                    </tr>
                </tbody>
            </table>
            <div class="note">
                O programa guarda uma impressão digital (hash) de <code>schema.sql</code> na tabela <code>_schema_meta</code>. Se o arquivo SQL mudar, na próxima execução o SQLite é recriado sozinho. Vocês <strong>não precisam apagar</strong> <code>data/app.sqlite</code> na mão. Uma cópia antiga fica em <code>data/app.sqlite.bak</code>.
            </div>
        </section>

        <section id="recriar">
            <p class="kicker">Tarefa 2</p>
            <h2>Como o banco é reconfigurado na próxima execução</h2>
            <ol>
                <li>Salvem o novo conteúdo em <code>database/schema.sql</code> (e o seed, se as colunas do admin mudarem).</li>
                <li>Abram de novo o site em <code>http://localhost:8081</code> (se o servidor já estiver no ar, basta recarregar a página).</li>
                <li><code>src/db.php</code> compara o hash do <code>schema.sql</code> com o gravado no banco. Se for diferente, recria <code>data/app.sqlite</code>, aplica o schema e o seed.</li>
            </ol>
            <div class="warn">
                Recriar o banco <strong>zera os dados de teste</strong> (cadastros que vocês tiverem feito). O admin volta a ser o do <code>seed.sql</code>. Não apaguem <code>database/schema.sql</code> nem <code>database/seed.sql</code>.
            </div>
            <p>Não existe atualização tabela a tabela. Ou o <code>schema.sql</code> é o mesmo (hash igual) e o banco fica como está, ou o arquivo mudou e o SQLite inteiro é recriado com todas as tabelas novas.</p>
        </section>

        <section id="cadastro">
            <p class="kicker">Tarefa 3</p>
            <h2>Arrumar o registro do usuário</h2>
            <p>O cadastro público já existe (<code>/cadastro</code>), mas ainda está no schema antigo: nome, e-mail e senha (mínimo 8 caracteres; o login do admin usa o “e-mail” <code>admin</code>). Depois do novo banco, o formulário e o <code>INSERT</code> precisam bater com as colunas novas.</p>
            <p>Fluxo atual (não criem outra rota se esta continuar valendo):</p>
            <p><code>GET/POST /cadastro</code> → <code>AuthController</code> → <code>User::register()</code> → view <code>views/auth/register.php</code></p>

            <h3>Arquivos para alterar</h3>
            <table>
                <thead>
                    <tr><th>Arquivo</th><th>Por quê</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>views/auth/register.php</code></td>
                        <td>View. Incluir os campos novos do schema (usuário, telefone, etc.). Manter <code>csrfField()</code>. Sem SQL aqui.</td>
                    </tr>
                    <tr>
                        <td><code>src/controllers/AuthController.php</code></td>
                        <td>Controller. Em <code>showRegister()</code> e <code>register()</code>, ler os novos campos com <code>request()</code> e passar para o model / devolver à view em caso de erro.</td>
                    </tr>
                    <tr>
                        <td><code>src/models/User.php</code></td>
                        <td>Model. Ajustar <code>register()</code>, <code>create()</code>, <code>emailExists()</code> / busca por login. Validar e gravar as colunas novas. SQL só aqui.</td>
                    </tr>
                    <tr>
                        <td><code>src/AuthService.php</code></td>
                        <td>Se o login deixar de ser o campo <code>email</code>, alinhar <code>attempt()</code> com o que o model busca (usuário, e-mail, etc.).</td>
                    </tr>
                    <tr>
                        <td><code>views/auth/login.php</code></td>
                        <td>Se o identificador mudar (ex.: usuário em vez de e-mail), o formulário de entrar precisa do mesmo campo.</td>
                    </tr>
                    <tr>
                        <td><code>database/seed.sql</code></td>
                        <td>Admin inicial tem de inserir todas as colunas NOT NULL do novo <code>users</code>.</td>
                    </tr>
                </tbody>
            </table>
            <h3>Arquivos que provavelmente não precisam ser criados</h3>
            <p>Não criem um segundo <code>RegisterController</code> se o cadastro continuar em <code>/cadastro</code>. A rota já está em <code>public/index.php</code>:</p>
            <pre>$router->get('/cadastro', [$auth, 'showRegister']);
$router->post('/cadastro', [$auth, 'register']);</pre>
            <div class="note">
                Se o schema tiver tabela <code>members</code> ligada a <code>users</code>, o cadastro pode criar o usuário <em>e</em> o membro no model (ou chamar <code>Member::create()</code>). A view não faz o INSERT.
            </div>
        </section>

        <section id="gerencia">
            <p class="kicker">Tarefa 4</p>
            <h2>Criar a gerência de usuários</h2>
            <p>Hoje o menu <strong>Usuários</strong> (<code>/usuarios</code>) cai no <code>PageController::usuarios()</code> e abre só o placeholder <code>views/coming-soon.php</code>. Vocês devem promover isso a um módulo de verdade, no mesmo desenho do dashboard.</p>

            <h3>Arquivos para criar</h3>
            <table>
                <thead>
                    <tr><th>Arquivo novo</th><th>Camada</th><th>Papel</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>src/controllers/UserController.php</code></td>
                        <td>Controller</td>
                        <td>Listar, criar (se for no admin), editar, ativar/desativar. <code>requireAuth()</code>; o ideal é restringir a <code>role = admin</code>.</td>
                    </tr>
                    <tr>
                        <td><code>views/users/index.php</code></td>
                        <td>View</td>
                        <td>Tabela de usuários (como a captura de eventos: nome, papel, status, ações).</td>
                    </tr>
                    <tr>
                        <td><code>views/users/form.php</code> <em>(se precisarem)</em></td>
                        <td>View</td>
                        <td>Formulário de novo/editar. Campos iguais aos do schema.</td>
                    </tr>
                </tbody>
            </table>

            <h3>Arquivos para alterar</h3>
            <table>
                <thead>
                    <tr><th>Arquivo existente</th><th>O que mudar</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>public/index.php</code></td>
                        <td>Front controller. Instanciar <code>UserController</code> e trocar a rota:<br>
                            <code>$router->get('/usuarios', [$users, 'index']);</code><br>
                            Acrescentar POST de criar/editar/excluir conforme a tela (ex.: <code>/usuarios/novo</code>, <code>/usuarios/salvar</code>).</td>
                    </tr>
                    <tr>
                        <td><code>src/controllers/PageController.php</code></td>
                        <td>Remover o método <code>usuarios()</code> quando a rota nova estiver no ar. Senão ficam dois donos para a mesma URL.</td>
                    </tr>
                    <tr>
                        <td><code>src/models/User.php</code></td>
                        <td>Métodos de gerência: <code>all()</code>, <code>findById()</code>, <code>update()</code>, etc. O controller não escreve SQL cru.</td>
                    </tr>
                    <tr>
                        <td><code>views/layout.php</code></td>
                        <td>O link do menu já aponta para <code>/usuarios</code>. Só mudem se a URL da gerência for outra.</td>
                    </tr>
                    <tr>
                        <td><code>public/assets/css/app.css</code></td>
                        <td>Estilo da tabela/formulário. Nada de <code>style="…"</code> no PHP.</td>
                    </tr>
                </tbody>
            </table>

            <h3>Ordem sugerida na aula</h3>
            <ol class="files">
                <li>Novo schema + recarregar o site (o programa recria o SQLite sozinho).</li>
                <li>Ajustar cadastro público (view → controller → model).</li>
                <li>Criar <code>UserController</code> + view de lista + métodos no model + rota no <code>index.php</code>.</li>
            </ol>
            <div class="warn">
                Não implementem a gerência só na view, nem só no <code>PageController</code>. Placeholder sai; módulo novo entra com controller próprio, como o dashboard.
            </div>
        </section>

        <p style="margin-top:2.5rem;color:#5c675a;font-size:0.9rem;">
            Clube de Mahjong · manual da aula · <code>documentacao/aula/manual.php</code>
        </p>
    </div>
</body>
</html>

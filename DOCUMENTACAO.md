# Documentação dos arquivos

Descrição rápida de cada arquivo de código do sistema do clube de mahjong.

O PHP segue MVC: rotas em `public/index.php`, controllers em `src/controllers/`, models em `src/models/`, views em `views/`. CSS em `public/assets/css/` define a aparência; JavaScript em `public/assets/js/` cuida do comportamento.

## Raiz

| Arquivo | Função |
| --- | --- |
| [`config.php`](config.php) | Configuração da aplicação: nome do clube e caminhos do SQLite, do schema e do seed. |
| [`README.md`](README.md) | Como subir o servidor, acesso inicial de desenvolvimento e visão geral das pastas. |

## `public/` — entrada HTTP e assets

| Arquivo | Função |
| --- | --- |
| [`public/index.php`](public/index.php) | Front controller: mapeia URLs para métodos dos controllers. |
| [`public/.htaccess`](public/.htaccess) | No Apache, envia para `index.php` qualquer URL que não seja arquivo estático. |
| [`public/assets/css/app.css`](public/assets/css/app.css) | Estilos globais: barra lateral admin, área de conteúdo, formulários e cards. |
| [`public/assets/css/login.css`](public/assets/css/login.css) | Cores da tela de login. Edite as variáveis `--login-*` no topo do arquivo. |
| [`public/assets/js/app.js`](public/assets/js/app.js) | Menu mobile da sidebar, estado de envio dos formulários e desaparecimento dos avisos (flash). |

## `src/` — núcleo MVC

| Arquivo | Função |
| --- | --- |
| [`src/bootstrap.php`](src/bootstrap.php) | Arranque: sessão, autoload, PDO e helpers (`e()`, `view()`, CSRF, flash). |
| [`src/db.php`](src/db.php) | Abre o PDO do SQLite, aplica o schema se preciso e executa o seed quando ainda não há usuários. |
| [`src/AuthService.php`](src/AuthService.php) | Sessão: usuário atual, login, logout e proteção de rotas. |
| [`src/Router.php`](src/Router.php) | Roteador por método + caminho; se a rota não existe, chama o ErrorController. |

## `src/controllers/` — Controller

| Arquivo | Função |
| --- | --- |
| [`src/controllers/Controller.php`](src/controllers/Controller.php) | Classe base: autenticação e renderização de views. |
| [`src/controllers/AuthController.php`](src/controllers/AuthController.php) | Login, cadastro e logout. |
| [`src/controllers/DashboardController.php`](src/controllers/DashboardController.php) | Painel inicial e totais. |
| [`src/controllers/EventController.php`](src/controllers/EventController.php) | Tela de eventos. |
| [`src/controllers/MatchController.php`](src/controllers/MatchController.php) | Tela de partidas. |
| [`src/controllers/MemberController.php`](src/controllers/MemberController.php) | Tela de participantes. |
| [`src/controllers/RankingController.php`](src/controllers/RankingController.php) | Tela de rankings. |
| [`src/controllers/PageController.php`](src/controllers/PageController.php) | Páginas “em breve” e redirecionamentos antigos. |
| [`src/controllers/ErrorController.php`](src/controllers/ErrorController.php) | Página 404. |

## `src/models/` — Model

| Arquivo | Função |
| --- | --- |
| [`src/models/User.php`](src/models/User.php) | Usuários: busca, cadastro e persistência. |
| [`src/models/Member.php`](src/models/Member.php) | Membros do clube. |
| [`src/models/Event.php`](src/models/Event.php) | Eventos. |
| [`src/models/GameMatch.php`](src/models/GameMatch.php) | Partidas (o nome evita conflito com a palavra `match` do PHP). |
| [`src/models/ProgressNote.php`](src/models/ProgressNote.php) | Notas de progresso. |

## `views/` — View

| Arquivo | Função |
| --- | --- |
| [`views/layout.php`](views/layout.php) | Casca comum: login (visitante) ou sidebar admin + área principal (usuário autenticado). |
| [`views/dashboard.php`](views/dashboard.php) | Painel inicial com totais e atalhos para eventos, partidas, participantes e rankings. |
| [`views/coming-soon.php`](views/coming-soon.php) | Página genérica “em breve” usada por torneios, notícias, usuários, configurações e área do app. |
| [`views/auth/login.php`](views/auth/login.php) | Formulário de entrada. |
| [`views/auth/register.php`](views/auth/register.php) | Formulário de cadastro de novo usuário. |
| [`views/events/index.php`](views/events/index.php) | Placeholder de gerenciamento de eventos. |
| [`views/matches/index.php`](views/matches/index.php) | Placeholder de partidas. |
| [`views/members/index.php`](views/members/index.php) | Placeholder de participantes. |
| [`views/progress/index.php`](views/progress/index.php) | Placeholder de rankings. |
| [`views/errors/404.php`](views/errors/404.php) | Tela de página não encontrada. |

## `database/` — SQLite

| Arquivo | Função |
| --- | --- |
| [`database/schema.sql`](database/schema.sql) | Cria as tabelas: usuários, membros, eventos, inscrições, partidas, jogadores e notas de progresso. |
| [`database/seed.sql`](database/seed.sql) | Insere o administrador inicial de desenvolvimento (`admin` / `admin`). |

## Fora do código (referência)

- `data/app.sqlite` — banco local gerado em tempo de execução; não entra no Git.
- `data/.gitkeep` — mantém a pasta `data/` no repositório.
- `Imagens_base/` — capturas de telas planejadas, usadas como referência visual.

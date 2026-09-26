# Clube de Mahjong

Sistema para administrar um clube de mahjong: membros, amistosos, eventos e acompanhamento de progresso.

Pilha atual: PHP, JavaScript, CSS e SQLite.

## Como subir

Na raiz do projeto:

```bash
php -S localhost:8081 -t public
```

Abra [http://localhost:8081](http://localhost:8081).

O arquivo SQLite é criado automaticamente em `data/app.sqlite` na primeira execução (a pasta `data/` precisa existir e ser gravável).

## Acesso inicial (desenvolvimento)

- Usuário: `admin`
- Senha: `admin`

Troque essa senha antes de usar em qualquer ambiente compartilhado.

## Estrutura

- `public/` — document root (rotas e assets)
- `src/controllers/` — controllers (pedidos HTTP)
- `src/models/` — models (dados / SQLite)
- `views/` — views (HTML das telas)
- `database/` — schema e seed
- `data/` — banco SQLite local (não versionado)
- `documentacao/mvc/` — guia MVC em PDF para o grupo de alunos
- `Imagens_base/` — capturas de telas de referência

O arquivo `data/app.sqlite` não entra no Git. Depois de clonar, suba o servidor uma vez para o banco ser criado.

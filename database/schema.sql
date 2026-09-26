PRAGMA foreign_keys = ON;

-- Mapeamento do diagrama de classes (herança por tabela da subclasse).
-- Campos extra preenchem atributos em branco no diagrama, no contexto do clube.

-- Usuário (idUsuario, Nome) + e-mail/senha/ativo para autenticação
CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE COLLATE NOCASE,
    senha_hash TEXT NOT NULL,
    ativo INTEGER NOT NULL DEFAULT 1 CHECK (ativo IN (0, 1)),
    criado_em TEXT NOT NULL DEFAULT (datetime('now')),
    atualizado_em TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Admin (especialização de Usuário)
CREATE TABLE IF NOT EXISTS admins (
    usuario_id INTEGER PRIMARY KEY,
    cargo TEXT NOT NULL DEFAULT 'administrador',
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Participante (Foto, Bio, DataCadastro) + apelido e nível de jogo
CREATE TABLE IF NOT EXISTS participantes (
    usuario_id INTEGER PRIMARY KEY,
    foto TEXT,
    bio TEXT,
    data_cadastro TEXT NOT NULL DEFAULT (datetime('now')),
    apelido TEXT,
    nivel TEXT NOT NULL DEFAULT 'iniciante'
        CHECK (nivel IN ('iniciante', 'intermediario', 'avancado')),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Evento (idEvento, Titulo, Descrição, Imagem, DataInicio, DataFim, Local)
-- Admin 1 — 0..* Evento (Cria)
-- tipo discrimina Torneio / Oficina / Encontro
CREATE TABLE IF NOT EXISTS eventos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_id INTEGER NOT NULL,
    tipo TEXT NOT NULL CHECK (tipo IN ('torneio', 'oficina', 'encontro')),
    titulo TEXT NOT NULL,
    descricao TEXT,
    imagem TEXT,
    data_inicio TEXT,
    data_fim TEXT,
    local TEXT,
    criado_em TEXT NOT NULL DEFAULT (datetime('now')),
    atualizado_em TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (admin_id) REFERENCES admins(usuario_id)
);

-- ListaParticipantes / Participa (N:N Evento ↔ Participante)
CREATE TABLE IF NOT EXISTS evento_participantes (
    evento_id INTEGER NOT NULL,
    participante_id INTEGER NOT NULL,
    inscrito_em TEXT NOT NULL DEFAULT (datetime('now')),
    status TEXT NOT NULL DEFAULT 'inscrito'
        CHECK (status IN ('inscrito', 'confirmado', 'lista_espera', 'cancelado')),
    PRIMARY KEY (evento_id, participante_id),
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE,
    FOREIGN KEY (participante_id) REFERENCES participantes(usuario_id)
);

-- Torneio: Regras, MaximoParticipantes, TaxaParticipacao + formato e premiação
CREATE TABLE IF NOT EXISTS torneios (
    evento_id INTEGER PRIMARY KEY,
    regras TEXT,
    maximo_participantes INTEGER,
    taxa_participacao REAL NOT NULL DEFAULT 0 CHECK (taxa_participacao >= 0),
    formato TEXT NOT NULL DEFAULT 'riichi'
        CHECK (formato IN ('riichi', 'hong_kong', 'sichuan', 'outro')),
    premiacao TEXT,
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE
);

-- Oficina: gerenciaPagamentos; tema, instrutor, vagas, carga horária, material, taxa
CREATE TABLE IF NOT EXISTS oficinas (
    evento_id INTEGER PRIMARY KEY,
    tema TEXT,
    instrutor TEXT,
    vagas INTEGER,
    carga_horaria_minutos INTEGER,
    material TEXT,
    taxa_inscricao REAL NOT NULL DEFAULT 0 CHECK (taxa_inscricao >= 0),
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE
);

-- Encontro: ListaPartidas; mesas disponíveis e se é aberto ao público
CREATE TABLE IF NOT EXISTS encontros (
    evento_id INTEGER PRIMARY KEY,
    quantidade_mesas INTEGER,
    aberto_publico INTEGER NOT NULL DEFAULT 1 CHECK (aberto_publico IN (0, 1)),
    observacoes TEXT,
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE
);

-- Partida (idPartida, Modalidade) + status/mesa/observações
CREATE TABLE IF NOT EXISTS partidas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    modalidade TEXT NOT NULL DEFAULT 'riichi'
        CHECK (modalidade IN ('riichi', 'hong_kong', 'sichuan', 'outro')),
    tipo TEXT NOT NULL CHECK (tipo IN ('competitiva', 'casual')),
    status TEXT NOT NULL DEFAULT 'agendada'
        CHECK (status IN ('agendada', 'em_andamento', 'encerrada', 'cancelada')),
    mesa TEXT,
    observacoes TEXT,
    criada_em TEXT NOT NULL DEFAULT (datetime('now')),
    atualizada_em TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Partida competitiva: HoraInicio, HoraFim + vínculo ao torneio e rodada
CREATE TABLE IF NOT EXISTS partidas_competitivas (
    partida_id INTEGER PRIMARY KEY,
    torneio_id INTEGER NOT NULL,
    hora_inicio TEXT,
    hora_fim TEXT,
    rodada INTEGER,
    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE CASCADE,
    FOREIGN KEY (torneio_id) REFERENCES torneios(evento_id)
);

-- Partida casual: vínculo ao encontro (ListaPartidas do Encontro)
CREATE TABLE IF NOT EXISTS partidas_casuais (
    partida_id INTEGER PRIMARY KEY,
    encontro_id INTEGER NOT NULL,
    duracao_minutos INTEGER,
    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE CASCADE,
    FOREIGN KEY (encontro_id) REFERENCES encontros(evento_id)
);

-- Jogadores de uma partida (resultado da mesa)
CREATE TABLE IF NOT EXISTS partida_jogadores (
    partida_id INTEGER NOT NULL,
    participante_id INTEGER NOT NULL,
    colocacao INTEGER,
    pontuacao INTEGER,
    PRIMARY KEY (partida_id, participante_id),
    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE CASCADE,
    FOREIGN KEY (participante_id) REFERENCES participantes(usuario_id)
);

-- Ranking (idUsuario, Pontuação) + posição e partidas jogadas
CREATE TABLE IF NOT EXISTS rankings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    pontuacao INTEGER NOT NULL DEFAULT 0,
    tipo TEXT NOT NULL CHECK (tipo IN ('competitivo', 'casual')),
    partidas_jogadas INTEGER NOT NULL DEFAULT 0,
    posicao INTEGER,
    atualizado_em TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Ranking competitivo: idTorneio (RankingTorneio)
CREATE TABLE IF NOT EXISTS rankings_competitivos (
    ranking_id INTEGER PRIMARY KEY,
    torneio_id INTEGER NOT NULL,
    usuario_id INTEGER NOT NULL,
    UNIQUE (torneio_id, usuario_id),
    FOREIGN KEY (ranking_id) REFERENCES rankings(id) ON DELETE CASCADE,
    FOREIGN KEY (torneio_id) REFERENCES torneios(evento_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Ranking casual: idEncontro
CREATE TABLE IF NOT EXISTS rankings_casuais (
    ranking_id INTEGER PRIMARY KEY,
    encontro_id INTEGER NOT NULL,
    usuario_id INTEGER NOT NULL,
    UNIQUE (encontro_id, usuario_id),
    FOREIGN KEY (ranking_id) REFERENCES rankings(id) ON DELETE CASCADE,
    FOREIGN KEY (encontro_id) REFERENCES encontros(evento_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- gerenciaPagamentos (Torneio e Oficina)
CREATE TABLE IF NOT EXISTS pagamentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    evento_id INTEGER NOT NULL,
    participante_id INTEGER NOT NULL,
    valor REAL NOT NULL CHECK (valor >= 0),
    status TEXT NOT NULL DEFAULT 'pendente'
        CHECK (status IN ('pendente', 'pago', 'isento', 'estornado')),
    forma TEXT CHECK (forma IS NULL OR forma IN ('pix', 'dinheiro', 'cartao', 'outro')),
    pago_em TEXT,
    criado_em TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE,
    FOREIGN KEY (participante_id) REFERENCES participantes(usuario_id)
);

CREATE INDEX IF NOT EXISTS idx_eventos_admin ON eventos(admin_id);
CREATE INDEX IF NOT EXISTS idx_eventos_tipo ON eventos(tipo);
CREATE INDEX IF NOT EXISTS idx_evento_participantes_participante ON evento_participantes(participante_id);
CREATE INDEX IF NOT EXISTS idx_partidas_tipo ON partidas(tipo);
CREATE INDEX IF NOT EXISTS idx_partidas_competitivas_torneio ON partidas_competitivas(torneio_id);
CREATE INDEX IF NOT EXISTS idx_partidas_casuais_encontro ON partidas_casuais(encontro_id);
CREATE INDEX IF NOT EXISTS idx_rankings_usuario ON rankings(usuario_id);
CREATE INDEX IF NOT EXISTS idx_rankings_competitivos_torneio ON rankings_competitivos(torneio_id);
CREATE INDEX IF NOT EXISTS idx_rankings_casuais_encontro ON rankings_casuais(encontro_id);
CREATE INDEX IF NOT EXISTS idx_pagamentos_evento ON pagamentos(evento_id);

INSERT INTO usuarios (nome, email, senha_hash, ativo)
VALUES (
    'Administrador',
    'admin',
    '$2y$10$B35.L5o26El7Ar/Rt3g7i.yYe8zdatw/KRldXy7JrWrakCjLtDvRq',
    1
);

INSERT INTO admins (usuario_id, cargo)
VALUES (1, 'administrador');

INSERT INTO participantes (usuario_id, foto, bio, apelido, nivel)
VALUES (
    1,
    NULL,
    'Usuário inicial de desenvolvimento.',
    'Admin',
    'avancado'
);

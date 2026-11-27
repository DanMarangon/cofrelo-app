-- Executado só na primeira vez que o volume do MySQL é criado.
-- Cria o banco usado pelos testes automatizados (docker compose run --rm testes).
CREATE DATABASE IF NOT EXISTS cofrelo_teste CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

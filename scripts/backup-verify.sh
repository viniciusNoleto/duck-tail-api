#!/usr/bin/env bash
# Prova que um backup volta: restaura o dump (o informado ou o mais recente) num banco descartável,
# conta as linhas das tabelas principais e apaga o banco. Só o Postgres (o grafo: README › Backup). Uso: backup-verify.sh [arquivo.dump]
source "$(dirname "$0")/lib.sh"

file="${1:-$(latest_backup)}"
if [ -z "$file" ] || [ ! -f "$file" ]; then
    fail "Nenhum backup encontrado."
fi

check="${DB_DATABASE}_restore_check"
drop_check() { pg_exec psql -U "$DB_USERNAME" -d "$DB_DATABASE" -q -c "DROP DATABASE IF EXISTS $check;"; }

drop_check
trap drop_check EXIT
pg_exec psql -U "$DB_USERNAME" -d "$DB_DATABASE" -q -c "CREATE DATABASE $check;"

if pg_exec pg_restore -U "$DB_USERNAME" -d "$check" --no-owner --exit-on-error < "$file" \
    && pg_exec psql -U "$DB_USERNAME" -d "$check" -tA \
        -c "SELECT 'migrations: ' || count(*) FROM migrations" \
        -c "SELECT 'users: ' || count(*) FROM users" \
        -c "SELECT 'registration_requests: ' || count(*) FROM registration_requests"; then
    echo "Backup OK: $file"
else
    fail "Backup NÃO restaurou: $file"
fi

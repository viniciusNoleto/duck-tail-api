<?php

return [
    'success' => 'Operação realizada com sucesso.',
    'unauthorized' => 'Você precisa entrar para continuar.',
    'forbidden' => 'Você não tem permissão para acessar este recurso.',
    'not_found' => 'Registro não encontrado.',
    'server_error' => 'Erro inesperado. Tente novamente.',
    'invalid_payload' => 'Dados inválidos.',
    'too_many_requests' => 'Muitas tentativas. Aguarde um pouco e tente de novo.',
    'maintenance' => 'O sistema está sendo atualizado. Tente de novo em instantes.',
    'concurrency_conflict' => 'Outra operação alterou estes dados ao mesmo tempo. Nada foi gravado: tente de novo.',

    'auth' => [
        'invalid_credentials' => 'E-mail ou senha inválidos.',
        'logged_in' => 'Login realizado com sucesso.',
        'logged_out' => 'Sessão encerrada.',
        'pending_approval' => 'Seu cadastro ainda aguarda a aprovação de um administrador.',
        'password_changed' => 'Senha alterada. As outras sessões foram encerradas.',
        'current_password_invalid' => 'A senha atual está incorreta.',
        'password_required_fields' => 'Senha atual e nova senha são obrigatórias.',
        'password_change_error' => 'Erro ao alterar a senha.',
    ],

    'registration_requests' => [
        'not_found' => 'Pedido de cadastro não encontrado.',
        'required_fields' => 'Nome, e-mail e senha são obrigatórios.',
        'name_too_long' => 'O nome deve ter no máximo 150 caracteres.',
        'invalid_email' => 'E-mail inválido.',
        'password_too_short' => 'A senha deve ter pelo menos 8 caracteres.',
        'password_too_long' => 'A senha deve ter no máximo 255 caracteres.',
        'email_taken' => 'Já existe um usuário ou pedido de cadastro com este e-mail.',
        'created' => 'Pedido de cadastro enviado. Aguarde a aprovação de um administrador.',
        'approved' => 'Cadastro aprovado. O usuário já pode entrar.',
        'rejected' => 'Pedido de cadastro recusado e excluído.',
        'fetch_error' => 'Erro ao buscar pedidos de cadastro.',
        'create_error' => 'Erro ao enviar o pedido de cadastro.',
        'approve_error' => 'Erro ao aprovar o pedido de cadastro.',
        'reject_error' => 'Erro ao recusar o pedido de cadastro.',
    ],

    'users' => [
        'not_found' => 'Usuário não encontrado.',
        'cannot_delete_admin' => 'Administradores não podem ser excluídos.',
        'deleted' => 'Usuário excluído. O acesso dele foi encerrado.',
        'fetch_error' => 'Erro ao buscar usuários.',
        'delete_error' => 'Erro ao excluir usuário.',
        'cannot_reset_admin' => 'A senha de um administrador não pode ser redefinida por outro administrador.',
        'password_reset' => 'Senha redefinida. As sessões do usuário foram encerradas.',
        'password_reset_error' => 'Erro ao redefinir a senha.',
    ],

    'places' => [
        'not_found' => 'Lugar não encontrado.',
        'invalid_coordinates' => 'Coordenadas inválidas: a latitude vai de -90 a 90 e a longitude de -180 a 180.',
        'invalid_radius' => 'O raio deve ser maior que zero e de no máximo :max km.',
        'invalid_limit' => 'O limite deve estar entre 1 e :max.',
        'invalid_page_size' => 'A quantidade por página deve estar entre 1 e :max.',
        'invalid_cursor' => 'Cursor de paginação inválido.',
        'invalid_search' => 'O termo e a categoria da busca devem ter no máximo 100 caracteres.',
        'theme_required' => 'Informe o tema do roteiro.',
    ],

    'locations' => [
        'invalid_parent' => 'Localidade pai inválida.',
        'invalid_kind' => 'O tipo de localidade deve ser country, state, region ou city.',
    ],

    'tours' => [
        'invalid_places' => 'Escolha de 1 a :max lugares.',
    ],

    'related' => [
        'author' => 'Mesmo autor: :value',
        'style' => 'Mesmo estilo: :value',
        'theme' => 'Mesmo tema: :value',
    ],

    'visits' => [
        'invalid_rating' => 'A nota deve ser um número inteiro de 1 a 5.',
    ],

    'follows' => [
        'cannot_follow_self' => 'Você não pode seguir a si mesmo.',
    ],

    'itineraries' => [
        'title_required' => 'O título do roteiro é obrigatório (até 150 caracteres).',
        'stops_required' => 'O roteiro precisa de pelo menos uma parada (no máximo :max).',
        'invalid_stop' => 'Cada parada precisa de um lugar, um dia e uma ordem maiores que zero.',
        'duplicate_stop' => 'Duas paradas não podem ter o mesmo dia e a mesma ordem.',
        'invalid_place' => 'Um dos lugares do roteiro não existe.',
    ],
];

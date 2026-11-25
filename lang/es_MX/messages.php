<?php

return [
    'success' => 'Operación realizada con éxito.',
    'unauthorized' => 'Debes iniciar sesión para continuar.',
    'forbidden' => 'No tienes permiso para acceder a este recurso.',
    'not_found' => 'Registro no encontrado.',
    'server_error' => 'Error inesperado. Inténtalo de nuevo.',
    'invalid_payload' => 'Datos inválidos.',
    'too_many_requests' => 'Demasiados intentos. Espera un momento e inténtalo de nuevo.',
    'maintenance' => 'El sistema se está actualizando. Inténtalo de nuevo en unos instantes.',
    'concurrency_conflict' => 'Otra operación modificó estos datos al mismo tiempo. No se guardó nada: inténtalo de nuevo.',

    'auth' => [
        'invalid_credentials' => 'Correo o contraseña inválidos.',
        'logged_in' => 'Inicio de sesión exitoso.',
        'logged_out' => 'Sesión cerrada.',
        'pending_approval' => 'Tu registro aún espera la aprobación de un administrador.',
        'password_changed' => 'Contraseña cambiada. Las demás sesiones fueron cerradas.',
        'current_password_invalid' => 'La contraseña actual es incorrecta.',
        'password_required_fields' => 'La contraseña actual y la nueva son obligatorias.',
        'password_change_error' => 'Error al cambiar la contraseña.',
    ],

    'registration_requests' => [
        'not_found' => 'Solicitud de registro no encontrada.',
        'required_fields' => 'Nombre, correo y contraseña son obligatorios.',
        'name_too_long' => 'El nombre debe tener como máximo 150 caracteres.',
        'invalid_email' => 'Correo inválido.',
        'password_too_short' => 'La contraseña debe tener al menos 8 caracteres.',
        'password_too_long' => 'La contraseña debe tener como máximo 255 caracteres.',
        'email_taken' => 'Ya existe un usuario o una solicitud de registro con este correo.',
        'created' => 'Solicitud de registro enviada. Espera la aprobación de un administrador.',
        'approved' => 'Registro aprobado. El usuario ya puede iniciar sesión.',
        'rejected' => 'Solicitud de registro rechazada y eliminada.',
        'fetch_error' => 'Error al buscar solicitudes de registro.',
        'create_error' => 'Error al enviar la solicitud de registro.',
        'approve_error' => 'Error al aprobar la solicitud de registro.',
        'reject_error' => 'Error al rechazar la solicitud de registro.',
    ],

    'users' => [
        'not_found' => 'Usuario no encontrado.',
        'cannot_delete_admin' => 'Los administradores no se pueden eliminar.',
        'deleted' => 'Usuario eliminado. Su acceso fue cancelado.',
        'fetch_error' => 'Error al buscar usuarios.',
        'delete_error' => 'Error al eliminar usuario.',
        'cannot_reset_admin' => 'La contraseña de un administrador no puede ser restablecida por otro administrador.',
        'password_reset' => 'Contraseña restablecida. Las sesiones del usuario fueron cerradas.',
        'password_reset_error' => 'Error al restablecer la contraseña.',
    ],

    'places' => [
        'not_found' => 'Lugar no encontrado.',
        'invalid_coordinates' => 'Coordenadas inválidas: la latitud va de -90 a 90 y la longitud de -180 a 180.',
        'invalid_radius' => 'El radio debe ser mayor que cero y de como máximo :max km.',
        'invalid_limit' => 'El límite debe estar entre 1 y :max.',
        'invalid_page_size' => 'La cantidad por página debe estar entre 1 y :max.',
        'invalid_cursor' => 'Cursor de paginación inválido.',
        'invalid_search' => 'El término y la categoría de la búsqueda deben tener como máximo 100 caracteres.',
        'theme_required' => 'Indica el tema del itinerario.',
    ],

    'locations' => [
        'invalid_parent' => 'Localidad superior inválida.',
        'invalid_kind' => 'El tipo de localidad debe ser country, state, region o city.',
    ],

    'tours' => [
        'invalid_places' => 'Elige de 1 a :max lugares.',
    ],

    'related' => [
        'author' => 'Mismo autor: :value',
        'style' => 'Mismo estilo: :value',
        'theme' => 'Mismo tema: :value',
    ],

    'visits' => [
        'invalid_rating' => 'La calificación debe ser un número entero del 1 al 5.',
    ],

    'follows' => [
        'cannot_follow_self' => 'No puedes seguirte a ti mismo.',
    ],

    'itineraries' => [
        'title_required' => 'El título del itinerario es obligatorio (hasta 150 caracteres).',
        'stops_required' => 'El itinerario necesita al menos una parada (como máximo :max).',
        'invalid_stop' => 'Cada parada necesita un lugar, un día y un orden mayores que cero.',
        'duplicate_stop' => 'Dos paradas no pueden tener el mismo día y el mismo orden.',
        'invalid_place' => 'Uno de los lugares del itinerario no existe.',
    ],
];

<?php

/*
|--------------------------------------------------------------------------
| Parámetros propios del sistema veterinario
|--------------------------------------------------------------------------
|
| Se leen con config('veterinaria.clave').
|
*/

return [

    // id_catalogo del estado "Activo" en t_catalogo.
    // Solo los clientes con este id_estado pueden iniciar sesión.
    // Si en tu base es otro id, cámbialo en .env: ESTADO_ACTIVO_ID=...
    'estado_activo_id' => (int) env('ESTADO_ACTIVO_ID', 1),

    // id_catalogo del estado "Inactivo"; se usa en el borrado lógico de clientes.
    'estado_inactivo_id' => (int) env('ESTADO_INACTIVO_ID', 2),

    // Estados buscados por código en t_catalogo (en lugar de por id fijo).
    // El servicio busca: WHERE tipo = catalogo_tipo_estado AND codigo = estado_..._codigo.
    // Si en tu base se llaman distinto, cámbialos en .env.
    'catalogo_tipo_estado' => env('CATALOGO_TIPO_ESTADO', 'ESTADO_GENERAL'),
    'estado_activo_codigo' => env('ESTADO_ACTIVO_CODIGO', 'ACTIVO'),
    'estado_inactivo_codigo' => env('ESTADO_INACTIVO_CODIGO', 'INACTIVO'),

    // Valor de la columna `tipo` en t_catalogo para los tipos de documento (CC, TI, CE...).
    'catalogo_tipo_documento' => env('CATALOGO_TIPO_DOCUMENTO', 'TIPO_DOCUMENTO'),

    // Valor de la columna `tipo` en t_catalogo para las especies de mascota (Perro, Gato...).
    'catalogo_tipo_especie' => env('CATALOGO_TIPO_ESPECIE', 'ESPECIE'),

    // Intentos fallidos de login permitidos antes de bloquear,
    // y segundos que dura el bloqueo.
    'login_max_intentos' => 5,
    'login_bloqueo_segundos' => 60,

];

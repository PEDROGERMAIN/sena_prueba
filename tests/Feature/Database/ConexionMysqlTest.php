<?php

/*
|--------------------------------------------------------------------------
| Prueba de conexión a la base de datos MySQL real
|--------------------------------------------------------------------------
|
| Solo verifica que Laravel se puede conectar a MySQL con los datos del .env
| y muestra un mensaje indicando si la conexión fue exitosa o no.
| No consulta tablas ni modifica nada.
|
| Pertenece al grupo "mysql":
|   php artisan test --group=mysql
|   php artisan test --exclude-group=mysql
|
*/

use Dotenv\Dotenv;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\TestCase;

// Arranca Laravel en cada prueba (config(), DB, modelos...).
uses(TestCase::class);

test('se conecta a la base de datos MySQL', function () {
    // phpunit.xml cambia DB_DATABASE a ":memory:" para las pruebas, así que
    // se lee el nombre real de la base de datos directamente del archivo .env.
    $env = Dotenv::parse(file_get_contents(base_path('.env')));
    $baseDatos = $env['DB_DATABASE'];

    config(['database.connections.mysql.database' => $baseDatos]);
    DB::purge('mysql');

    // Intenta conectarse. Si falla, se guarda el motivo del error.
    $error = '';

    try {
        DB::connection('mysql')->getPdo();
        $conectado = true;
    } catch (Throwable $e) {
        $conectado = false;
        $error = $e->getMessage();
    }

    if ($conectado) {
        fwrite(STDOUT, "\n  Conexión exitosa a la base de datos '{$baseDatos}'.\n");
    } else {
        $this->fail("No se pudo conectar a la base de datos '{$baseDatos}': {$error}");
    }

    expect($conectado)->toBeTrue();
})->group('mysql');

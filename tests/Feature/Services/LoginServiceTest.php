<?php

/*
|--------------------------------------------------------------------------
| Pruebas del LoginService
|--------------------------------------------------------------------------
|
| Usan la base de datos MySQL REAL (la del .env), sin mocks:
| el cliente se guarda de verdad y el login lo busca con el repositorio Eloquent.
|
| Cada prueba corre dentro de una transacción que se deshace al terminar,
| así que no queda ningún registro de prueba guardado.
|
| Requiere MySQL encendido. Pertenecen al grupo "mysql":
|   php artisan test --filter=LoginService
|   php artisan test --exclude-group=mysql   (para saltarlas)
|
*/

use App\Models\Catalogo;
use App\Models\Cliente;
use App\Services\LoginService;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\TestCase;

// Arranca Laravel en cada prueba (config(), DB, modelos...).
uses(TestCase::class);

beforeEach(function () {
    // phpunit.xml obliga a usar SQLite en memoria; aquí se cambia a MySQL
    // leyendo el nombre real de la base de datos del archivo .env.
    $env = Dotenv::parse(file_get_contents(base_path('.env')));

    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => $env['DB_DATABASE'],
    ]);
    DB::purge('mysql');

    // Se abre a mano (no con el trait DatabaseTransactions) porque el trait
    // la abriría antes de este beforeEach, sobre SQLite y no sobre MySQL.
    DB::connection('mysql')->beginTransaction();
})->group('mysql');

afterEach(function () {
    // Deshace todo lo que la prueba guardó.
    if (DB::connection('mysql')->transactionLevel() > 0) {
        DB::connection('mysql')->rollBack();
    }
});

/**
 * Guarda (dentro de la transacción) un cliente con contraseña '1234',
 * junto con los registros de t_catalogo que necesita, y apunta la
 * configuración de estados a esos ids reales.
 */
function clienteLogin(bool $activo = true): Cliente
{
    $tipoEstado = config('veterinaria.catalogo_tipo_estado');

    $cc = Catalogo::create(['tipo' => config('veterinaria.catalogo_tipo_documento'), 'codigo' => 'PRUEBA_CC', 'nombre' => 'CC (prueba)', 'orden' => 99, 'activo' => true]);
    $estadoActivo = Catalogo::create(['tipo' => $tipoEstado, 'codigo' => 'PRUEBA_ACT', 'nombre' => 'Activo (prueba)', 'orden' => 99, 'activo' => true]);
    $estadoInactivo = Catalogo::create(['tipo' => $tipoEstado, 'codigo' => 'PRUEBA_INA', 'nombre' => 'Inactivo (prueba)', 'orden' => 99, 'activo' => true]);

    config([
        'veterinaria.estado_activo_id' => $estadoActivo->id_catalogo,
        'veterinaria.estado_inactivo_id' => $estadoInactivo->id_catalogo,
    ]);

    // El cast 'hashed' del modelo cifra la contraseña al guardar.
    return Cliente::create([
        'id_tipo_documento' => $cc->id_catalogo,
        'numero_documento' => '9999000003',
        'nombres' => 'Juan Pablo',
        'apellidos' => 'Prueba',
        'correo' => 'prueba.login@example.test',
        'id_estado' => $activo ? $estadoActivo->id_catalogo : $estadoInactivo->id_catalogo,
        'password' => '1234',
    ]);
}

test('inicia sesión con credenciales correctas y cliente activo', function () {
    $guardado = clienteLogin();

    $cliente = app(LoginService::class)->login('prueba.login@example.test', '1234', '127.0.0.1');

    expect($cliente->id_cliente)->toBe($guardado->id_cliente)
        ->and(Auth::guard('cliente')->check())->toBeTrue()
        ->and(Auth::guard('cliente')->id())->toBe($guardado->id_cliente);
});

test('rechaza credenciales incorrectas', function () {
    clienteLogin();

    app(LoginService::class)->login('prueba.login@example.test', 'mala', '127.0.0.1');
})->throws(ValidationException::class, 'El correo o la contraseña son incorrectos.');

test('rechaza a un cliente inactivo', function () {
    clienteLogin(activo: false);

    try {
        app(LoginService::class)->login('prueba.login@example.test', '1234', '127.0.0.1');
    } finally {
        expect(Auth::guard('cliente')->check())->toBeFalse();
    }
})->throws(ValidationException::class, 'Tu cuenta está inactiva.');

test('bloquea después de 5 intentos fallidos', function () {
    clienteLogin();
    $servicio = app(LoginService::class);

    foreach (range(1, 5) as $i) {
        try {
            $servicio->login('prueba.login@example.test', 'mala', '10.0.0.1');
        } catch (ValidationException) {
        }
    }

    // El sexto intento se bloquea aunque la contraseña sea la correcta.
    $servicio->login('prueba.login@example.test', '1234', '10.0.0.1');
})->throws(ValidationException::class, 'Demasiados intentos.');

test('cierra la sesión', function () {
    clienteLogin();
    $servicio = app(LoginService::class);
    $servicio->login('prueba.login@example.test', '1234', '127.0.0.1');

    $servicio->logout();

    expect($servicio->clienteActual())->toBeNull();
});

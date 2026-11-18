<?php

/*
|--------------------------------------------------------------------------
| Pruebas del ClienteService
|--------------------------------------------------------------------------
|
| Usan la base de datos MySQL REAL (la del .env), sin mocks:
| el servicio trabaja con sus repositorios Eloquent de verdad.
|
| Cada prueba corre dentro de una transacción que se deshace al terminar,
| así que no queda ningún registro de prueba guardado.
|
| Requiere MySQL encendido. Pertenecen al grupo "mysql":
|   php artisan test --filter=ClienteService
|   php artisan test --exclude-group=mysql   (para saltarlas)
|
*/

use App\Models\Catalogo;
use App\Models\Cliente;
use App\Services\ClienteService;
use Dotenv\Dotenv;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
 * Crea (dentro de la transacción) los registros de t_catalogo que necesita
 * la prueba y apunta la configuración a sus ids reales.
 *
 * @return array{cc: Catalogo, activo: Catalogo, inactivo: Catalogo}
 */
function catalogosCliente(): array
{
    $tipoDocumento = config('veterinaria.catalogo_tipo_documento');
    $tipoEstado = config('veterinaria.catalogo_tipo_estado');

    $catalogos = [
        'cc' => Catalogo::create(['tipo' => $tipoDocumento, 'codigo' => 'PRUEBA_CC', 'nombre' => 'CC (prueba)', 'orden' => 99, 'activo' => true]),
        'activo' => Catalogo::create(['tipo' => $tipoEstado, 'codigo' => 'PRUEBA_ACT', 'nombre' => 'Activo (prueba)', 'orden' => 99, 'activo' => true]),
        'inactivo' => Catalogo::create(['tipo' => $tipoEstado, 'codigo' => 'PRUEBA_INA', 'nombre' => 'Inactivo (prueba)', 'orden' => 99, 'activo' => true]),
    ];

    config([
        'veterinaria.estado_activo_id' => $catalogos['activo']->id_catalogo,
        'veterinaria.estado_inactivo_id' => $catalogos['inactivo']->id_catalogo,
    ]);

    return $catalogos;
}

/** Datos de un cliente de prueba; documento y correo poco probables en la base real. */
function datosCliente(int $idTipoDocumento, array $cambios = []): array
{
    return array_merge([
        'id_tipo_documento' => $idTipoDocumento,
        'numero_documento' => ' 9999000001 ',
        'nombres' => 'Ana',
        'apellidos' => 'Prueba',
        'telefono' => '3105551003',
        'correo' => ' Prueba.Cliente@Example.TEST ',
        'direccion' => '',
        'password' => '1234',
    ], $cambios);
}

test('registra un cliente limpio y activo por defecto', function () {
    $cat = catalogosCliente();

    $cliente = app(ClienteService::class)->registrar(datosCliente($cat['cc']->id_catalogo));

    // Se lee de nuevo desde MySQL para comprobar lo que realmente se guardó.
    $guardado = Cliente::findOrFail($cliente->id_cliente);

    expect($guardado->numero_documento)->toBe('9999000001')
        ->and($guardado->correo)->toBe('prueba.cliente@example.test')
        ->and($guardado->direccion)->toBeNull()
        ->and((int) $guardado->id_estado)->toBe($cat['activo']->id_catalogo)
        ->and(Hash::check('1234', $guardado->password))->toBeTrue();
});

test('no registra un documento repetido', function () {
    $cat = catalogosCliente();
    $servicio = app(ClienteService::class);

    $servicio->registrar(datosCliente($cat['cc']->id_catalogo));
    $servicio->registrar(datosCliente($cat['cc']->id_catalogo, ['correo' => 'otro.cliente@example.test']));
})->throws(ValidationException::class, 'Ya existe un cliente con este número de documento.');

test('no registra un correo repetido', function () {
    $cat = catalogosCliente();
    $servicio = app(ClienteService::class);

    $servicio->registrar(datosCliente($cat['cc']->id_catalogo));
    $servicio->registrar(datosCliente($cat['cc']->id_catalogo, ['numero_documento' => '9999000002']));
})->throws(ValidationException::class, 'Este correo ya está registrado por otro cliente.');

test('rechaza un id de catálogo que no es tipo de documento', function () {
    catalogosCliente();

    $especie = Catalogo::create([
        'tipo' => config('veterinaria.catalogo_tipo_especie'),
        'codigo' => 'PRUEBA_ESP',
        'nombre' => 'Especie (prueba)',
        'orden' => 99,
        'activo' => true,
    ]);

    app(ClienteService::class)->registrar(datosCliente($especie->id_catalogo));
})->throws(ValidationException::class, 'El tipo de documento seleccionado no es válido.');

test('al actualizar excluye al propio cliente y conserva la contraseña si llega vacía', function () {
    $cat = catalogosCliente();
    $servicio = app(ClienteService::class);

    $cliente = $servicio->registrar(datosCliente($cat['cc']->id_catalogo));
    $hashAntes = Cliente::findOrFail($cliente->id_cliente)->password;

    // Mismo documento y correo: no debe chocar consigo mismo.
    $servicio->actualizar($cliente->id_cliente, datosCliente($cat['cc']->id_catalogo, [
        'nombres' => 'Ana María',
        'password' => '',
    ]));

    $despues = Cliente::findOrFail($cliente->id_cliente);

    expect($despues->nombres)->toBe('Ana María')
        ->and($despues->password)->toBe($hashAntes);
});

test('inactivar y activar cambian el estado', function () {
    $cat = catalogosCliente();
    $servicio = app(ClienteService::class);

    $cliente = $servicio->registrar(datosCliente($cat['cc']->id_catalogo));

    $servicio->inactivar($cliente->id_cliente);
    expect((int) Cliente::findOrFail($cliente->id_cliente)->id_estado)->toBe($cat['inactivo']->id_catalogo);

    $servicio->activar($cliente->id_cliente);
    expect((int) Cliente::findOrFail($cliente->id_cliente)->id_estado)->toBe($cat['activo']->id_catalogo);
});

test('obtener lanza 404 si el cliente no existe', function () {
    // Un id mayor que todos los existentes nunca está en la tabla.
    $idInexistente = (int) Cliente::max('id_cliente') + 1000;

    app(ClienteService::class)->obtener($idInexistente);
})->throws(ModelNotFoundException::class);

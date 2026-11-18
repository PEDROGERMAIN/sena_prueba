<?php

/*
|--------------------------------------------------------------------------
| Prueba de consulta (SELECT) a las tablas de los modelos
|--------------------------------------------------------------------------
|
| Hace un SELECT * a la tabla de cada modelo en la base de datos MySQL real
| y muestra cuántos registros encontró.
|
| IMPORTANTE: solo lee. No inserta, actualiza ni borra nada.
|
| Pertenece al grupo "mysql":
|   php artisan test --group=mysql
|   php artisan test --exclude-group=mysql
|
*/

use App\Models\Catalogo;
use App\Models\Cliente;
use App\Models\Mascota;
use Dotenv\Dotenv;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\TestCase;

// Arranca Laravel en cada prueba (config(), DB, modelos...).
uses(TestCase::class);

beforeEach(function () {
    // phpunit.xml cambia la base de datos a SQLite en memoria para las pruebas,
    // así que se usa MySQL con el nombre real de la base de datos del archivo .env.
    $env = Dotenv::parse(file_get_contents(base_path('.env')));

    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => $env['DB_DATABASE'],
    ]);

    DB::purge('mysql');
});

test('hace SELECT a la tabla del modelo', function (string $claseModelo) {
    // SELECT * FROM <tabla>
    $registros = $claseModelo::all();

    $tabla = (new $claseModelo)->getTable();
    fwrite(STDOUT, "\n  {$tabla}: {$registros->count()} registros encontrados.\n");

    expect($registros)->toBeInstanceOf(Collection::class)
        ->each->toBeInstanceOf($claseModelo);
})->with([
    'Catalogo' => [Catalogo::class],
    'Cliente' => [Cliente::class],
    'Mascota' => [Mascota::class],
])->group('mysql');

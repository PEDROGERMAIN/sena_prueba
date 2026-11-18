<?php

/*
|--------------------------------------------------------------------------
| Pruebas del MascotaService
|--------------------------------------------------------------------------
|
| No usan MySQL: los repositorios se reemplazan por mocks.
|
| Los mocks se crean con funciones auxiliares y variables locales
| (no con $this) para que el editor conozca su tipo y no marque
| "Undefined property".
|
|   php artisan test --filter=MascotaService
|
*/

use App\Models\Catalogo;
use App\Models\Mascota;
use App\Repositories\Contracts\CatalogoRepositoryInterface;
use App\Repositories\Contracts\MascotaRepositoryInterface;
use App\Services\MascotaService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Illuminate\Foundation\Testing\TestCase;

// Arranca Laravel en cada prueba (config(), DB, modelos...).
uses(TestCase::class);

beforeEach(function () {
    config([
        'veterinaria.estado_activo_id' => 1,
        'veterinaria.estado_inactivo_id' => 2,
        'veterinaria.catalogo_tipo_especie' => 'ESPECIE',
    ]);
});

/** Repositorio de mascotas simulado. Por defecto: ningún microchip está registrado. */
function mockMascotas(): MascotaRepositoryInterface&MockInterface
{
    $mascotas = Mockery::mock(MascotaRepositoryInterface::class);
    $mascotas->shouldReceive('findByMicrochip')->andReturnNull()->byDefault();

    return $mascotas;
}

/** Repositorio de catálogos simulado. Por defecto: el id 3 es una especie activa (Perro). */
function mockCatalogosMascota(): CatalogoRepositoryInterface&MockInterface
{
    $catalogos = Mockery::mock(CatalogoRepositoryInterface::class);
    $catalogos->shouldReceive('find')->with(3)
        ->andReturn(new Catalogo(['tipo' => 'ESPECIE', 'codigo' => 'PER', 'activo' => true]))
        ->byDefault();

    return $catalogos;
}

/** Servicio real con repositorios simulados; si no se pasa alguno, se usa uno por defecto. */
function servicioMascotas(
    ?MascotaRepositoryInterface $mascotas = null,
    ?CatalogoRepositoryInterface $catalogos = null,
): MascotaService {
    return new MascotaService($mascotas ?? mockMascotas(), $catalogos ?? mockCatalogosMascota());
}

/** Mascota guardada con el id indicado (sin base de datos). */
function mascotaConId(int $idMascota): Mascota
{
    $mascota = new Mascota;
    $mascota->id_mascota = $idMascota;

    return $mascota;
}

function datosMascota(array $cambios = []): array
{
    return array_merge([
        'id_cliente' => 1,
        'nombre' => ' Toby ',
        'id_especie' => 3,
        'raza' => '',
        'sexo' => 'M',
        'color' => ' Café ',
        'microchip' => ' 985112003456789 ',
    ], $cambios);
}

test('registra una mascota limpia y activa por defecto', function () {
    $mascotas = mockMascotas();
    $mascotas->shouldReceive('create')->once()
        ->withArgs(fn (array $d) => $d['nombre'] === 'Toby'
            && $d['color'] === 'Café'
            && $d['microchip'] === '985112003456789'
            && $d['raza'] === null
            && $d['id_estado'] === 1)
        ->andReturn(new Mascota);

    expect(servicioMascotas($mascotas)->registrar(datosMascota()))->toBeInstanceOf(Mascota::class);
});

test('un microchip vacío se guarda como null y no se busca repetido', function () {
    $mascotas = mockMascotas();
    $mascotas->shouldNotReceive('findByMicrochip');
    $mascotas->shouldReceive('create')->once()
        ->withArgs(fn (array $d) => $d['microchip'] === null)
        ->andReturn(new Mascota);

    servicioMascotas($mascotas)->registrar(datosMascota(['microchip' => '  ']));
});

test('no registra un microchip repetido', function () {
    $mascotas = mockMascotas();
    $mascotas->shouldReceive('findByMicrochip')->with('985112003456789')->andReturn(mascotaConId(9));
    $mascotas->shouldNotReceive('create');

    servicioMascotas($mascotas)->registrar(datosMascota());
})->throws(ValidationException::class, 'Ya existe una mascota con este microchip.');

test('rechaza un id de catálogo que no es especie', function () {
    $catalogos = mockCatalogosMascota();
    $catalogos->shouldReceive('find')->with(20)
        ->andReturn(new Catalogo(['tipo' => 'TIPO_DOCUMENTO', 'codigo' => 'CC', 'activo' => true]));

    servicioMascotas(catalogos: $catalogos)->registrar(datosMascota(['id_especie' => 20]));
})->throws(ValidationException::class, 'La especie seleccionada no es válida.');

test('rechaza una especie inactiva', function () {
    $catalogos = mockCatalogosMascota();
    $catalogos->shouldReceive('find')->with(4)
        ->andReturn(new Catalogo(['tipo' => 'ESPECIE', 'codigo' => 'AVE', 'activo' => false]));

    servicioMascotas(catalogos: $catalogos)->registrar(datosMascota(['id_especie' => 4]));
})->throws(ValidationException::class, 'La especie seleccionada no es válida.');

test('al actualizar, su propio microchip no cuenta como repetido', function () {
    $mascotas = mockMascotas();
    $mascotas->shouldReceive('findByMicrochip')->with('985112003456789')->andReturn(mascotaConId(7));
    $mascotas->shouldReceive('update')->once()
        ->withArgs(fn (int $id, array $d) => $id === 7 && $d['nombre'] === 'Toby')
        ->andReturn(new Mascota);

    servicioMascotas($mascotas)->actualizar(7, datosMascota());
});

test('al actualizar rechaza el microchip de otra mascota', function () {
    $mascotas = mockMascotas();
    $mascotas->shouldReceive('findByMicrochip')->andReturn(mascotaConId(9));
    $mascotas->shouldNotReceive('update');

    servicioMascotas($mascotas)->actualizar(7, datosMascota());
})->throws(ValidationException::class, 'Ya existe una mascota con este microchip.');

test('inactivar y activar cambian el estado', function () {
    $mascotas = mockMascotas();
    $mascotas->shouldReceive('update')->with(7, ['id_estado' => 2])->once()->andReturn(new Mascota);
    $mascotas->shouldReceive('update')->with(7, ['id_estado' => 1])->once()->andReturn(new Mascota);

    $servicio = servicioMascotas($mascotas);
    $servicio->inactivar(7);
    $servicio->activar(7);
});

test('deCliente devuelve las mascotas del cliente', function () {
    $lista = new Collection([new Mascota, new Mascota]);
    $mascotas = mockMascotas();
    $mascotas->shouldReceive('findByCliente')->with(1)->once()->andReturn($lista);

    expect(servicioMascotas($mascotas)->deCliente(1))->toBe($lista);
});

test('obtener carga dueño, especie y estado', function () {
    $mascota = new Mascota;
    $mascotas = mockMascotas();
    $mascotas->shouldReceive('find')->with(7, ['cliente', 'especie', 'estado'])->once()->andReturn($mascota);

    expect(servicioMascotas($mascotas)->obtener(7))->toBe($mascota);
});

test('obtener lanza 404 si la mascota no existe', function () {
    $mascotas = mockMascotas();
    $mascotas->shouldReceive('find')->andReturnNull();

    servicioMascotas($mascotas)->obtener(99);
})->throws(ModelNotFoundException::class);

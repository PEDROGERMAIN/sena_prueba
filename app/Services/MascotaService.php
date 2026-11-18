<?php

namespace App\Services;

// Modelo de la tabla t_mascota.
use App\Models\Mascota;

// Repositorios que usa el servicio (se inyectan por su interfaz).
use App\Repositories\Contracts\CatalogoRepositoryInterface;
use App\Repositories\Contracts\MascotaRepositoryInterface;

// Lista de mascotas que devuelve deCliente().
use Illuminate\Database\Eloquent\Collection;

// Error 404 cuando la mascota no existe.
use Illuminate\Database\Eloquent\ModelNotFoundException;

// Excepción que Laravel convierte en errores de formulario ($errors en Blade).
use Illuminate\Validation\ValidationException;

/**
 * Reglas de negocio de las mascotas.
 *
 * Reparto de responsabilidades:
 *   - FormRequest: formato de los campos (requerido, fecha, número...).
 *   - MascotaService: reglas que dependen de la base de datos
 *     (microchip único, especie válida, estados).
 *   - MascotaRepository: solo consultas.
 *
 * Las mascotas no se borran: se inactivan (borrado lógico), porque
 * tienen historial clínico que debe conservarse.
 */
class MascotaService
{
    /**
     * Columnas que pueden quedar vacías; '' se guarda como NULL.
     *
     * @var array<int, string>
     */
    private const OPCIONALES = ['raza', 'color', 'microchip', 'fecha_nacimiento', 'peso_actual_kg'];

    public function __construct(
        private MascotaRepositoryInterface $mascotas,
        private CatalogoRepositoryInterface $catalogos,
    ) {}

    /**
     * Mascotas de un cliente, ordenadas por nombre.
     *
     * @return Collection<int, Mascota>
     */
    public function deCliente(int $idCliente): Collection
    {
        return $this->mascotas->findByCliente($idCliente);
    }

    /**
     * Detalle de una mascota con su dueño, especie y estado.
     *
     * @throws ModelNotFoundException Si no existe (Laravel responde 404).
     */
    public function obtener(int $idMascota): Mascota
    {
        return $this->mascotas->find($idMascota, ['cliente', 'especie', 'estado'])
            ?? throw (new ModelNotFoundException)->setModel(Mascota::class, [$idMascota]);
    }

    /**
     * Registra una mascota nueva. Si no se envía estado, queda activa.
     *
     * @param  array<string, mixed>  $datos  Campos ya validados por el FormRequest.
     *
     * @throws ValidationException Microchip repetido o especie inválida.
     */
    public function registrar(array $datos): Mascota
    {
        $datos = $this->normalizar($datos);

        $this->validarReglas($datos);

        $datos['id_estado'] ??= config('veterinaria.estado_activo_id');

        return $this->mascotas->create($datos);
    }

    /**
     * Actualiza los datos de una mascota.
     *
     * @param  array<string, mixed>  $datos  Campos ya validados por el FormRequest.
     *
     * @throws ModelNotFoundException Si la mascota no existe.
     * @throws ValidationException    Microchip repetido o especie inválida.
     */
    public function actualizar(int $idMascota, array $datos): Mascota
    {
        $datos = $this->normalizar($datos);

        $this->validarReglas($datos, $idMascota);

        return $this->mascotas->update($idMascota, $datos);
    }

    /**
     * Borrado lógico: la mascota deja de aparecer como activa.
     *
     * @throws ModelNotFoundException Si la mascota no existe.
     */
    public function inactivar(int $idMascota): Mascota
    {
        return $this->mascotas->update($idMascota, ['id_estado' => config('veterinaria.estado_inactivo_id')]);
    }

    /**
     * Vuelve a activar una mascota inactiva.
     *
     * @throws ModelNotFoundException Si la mascota no existe.
     */
    public function activar(int $idMascota): Mascota
    {
        return $this->mascotas->update($idMascota, ['id_estado' => config('veterinaria.estado_activo_id')]);
    }

    /**
     * Limpia los datos antes de guardar:
     *   - quita espacios al inicio y al final,
     *   - convierte '' en NULL en los campos opcionales.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function normalizar(array $datos): array
    {
        foreach ($datos as $campo => $valor) {
            if (is_string($valor)) {
                $datos[$campo] = trim($valor);
            }
        }

        foreach (self::OPCIONALES as $campo) {
            if (array_key_exists($campo, $datos) && $datos[$campo] === '') {
                $datos[$campo] = null;
            }
        }

        return $datos;
    }

    /**
     * Reglas que dependen de la base de datos. Junta todos los errores
     * para mostrarlos de una vez en el formulario.
     *
     * @param  array<string, mixed>  $datos
     * @param  int|null  $exceptoId  Mascota que se edita (se excluye de los repetidos).
     *
     * @throws ValidationException
     */
    private function validarReglas(array $datos, ?int $exceptoId = null): void
    {
        $errores = [];

        if (! empty($datos['microchip'])) {
            $otra = $this->mascotas->findByMicrochip($datos['microchip']);

            if ($otra !== null && (int) $otra->id_mascota !== $exceptoId) {
                $errores['microchip'] = 'Ya existe una mascota con este microchip.';
            }
        }

        if (isset($datos['id_especie'])
            && ! $this->esEspecieValida((int) $datos['id_especie'])) {
            $errores['id_especie'] = 'La especie seleccionada no es válida.';
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
    }

    /**
     * La llave foránea solo exige que el id exista en t_catalogo;
     * aquí se verifica además que sea una especie y que esté activa.
     */
    private function esEspecieValida(int $idCatalogo): bool
    {
        $catalogo = $this->catalogos->find($idCatalogo);

        return $catalogo !== null
            && $catalogo->tipo === config('veterinaria.catalogo_tipo_especie')
            && $catalogo->activo;
    }
}

<?php

namespace App\Services;

// Modelo de la tabla t_tipo_especialidad.
use App\Models\TipoEspecialidad;

// Repositorio que usa el servicio (se inyecta por su interfaz).
use App\Repositories\Contracts\TipoEspecialidadRepositoryInterface;

// Lista de especialidades que devuelve listar().
use Illuminate\Database\Eloquent\Collection;

// Error 404 cuando la especialidad no existe.
use Illuminate\Database\Eloquent\ModelNotFoundException;

// Excepción que Laravel convierte en errores de formulario ($errors en Blade).
use Illuminate\Validation\ValidationException;

/**
 * Reglas de negocio de los tipos de especialidad.
 *
 * Reparto de responsabilidades:
 *   - FormRequest: formato de los campos (requerido, longitud...).
 *   - TipoEspecialidadService: reglas que dependen de la base de datos
 *     (código y nombre únicos, estados).
 *   - TipoEspecialidadRepository: solo consultas.
 *
 * Las especialidades no se borran: se inactivan (borrado lógico), porque
 * t_especialista las referencia con una llave foránea.
 */
class TipoEspecialidadService
{
    /**
     * Columnas que pueden quedar vacías; '' se guarda como NULL.
     *
     * @var array<int, string>
     */
    private const OPCIONALES = ['descripcion'];

    public function __construct(
        private TipoEspecialidadRepositoryInterface $especialidades,
    ) {}

    /**
     * Todas las especialidades con su estado.
     * Sin paginar: es una tabla de pocos registros.
     *
     * @return Collection<int, TipoEspecialidad>
     */
    public function listar(): Collection
    {
        return $this->especialidades->all(['estado']);
    }

    /**
     * Detalle de una especialidad con su estado.
     *
     * @throws ModelNotFoundException Si no existe (Laravel responde 404).
     */
    public function obtener(int $idTipoEspecialidad): TipoEspecialidad
    {
        return $this->especialidades->find($idTipoEspecialidad, ['estado'])
            ?? throw (new ModelNotFoundException)->setModel(TipoEspecialidad::class, [$idTipoEspecialidad]);
    }

    /**
     * Registra una especialidad nueva.
     * Si no se envía estado, queda activa; si no se indica, no exige tarjeta profesional.
     *
     * @param  array<string, mixed>  $datos  Campos ya validados por el FormRequest.
     *
     * @throws ValidationException Código o nombre repetidos.
     */
    public function registrar(array $datos): TipoEspecialidad
    {
        $datos = $this->normalizar($datos);

        $this->validarReglas($datos);

        $datos['id_estado'] ??= config('veterinaria.estado_activo_id');
        $datos['requiere_tarjeta_profesional'] ??= false;

        return $this->especialidades->create($datos);
    }

    /**
     * Actualiza los datos de una especialidad.
     *
     * @param  array<string, mixed>  $datos  Campos ya validados por el FormRequest.
     *
     * @throws ModelNotFoundException Si la especialidad no existe.
     * @throws ValidationException    Código o nombre repetidos.
     */
    public function actualizar(int $idTipoEspecialidad, array $datos): TipoEspecialidad
    {
        $datos = $this->normalizar($datos);

        $this->validarReglas($datos, $idTipoEspecialidad);

        return $this->especialidades->update($idTipoEspecialidad, $datos);
    }

    /**
     * Borrado lógico: la especialidad deja de aparecer como activa.
     *
     * @throws ModelNotFoundException Si la especialidad no existe.
     */
    public function inactivar(int $idTipoEspecialidad): TipoEspecialidad
    {
        return $this->especialidades->update($idTipoEspecialidad, ['id_estado' => config('veterinaria.estado_inactivo_id')]);
    }

    /**
     * Vuelve a activar una especialidad inactiva.
     *
     * @throws ModelNotFoundException Si la especialidad no existe.
     */
    public function activar(int $idTipoEspecialidad): TipoEspecialidad
    {
        return $this->especialidades->update($idTipoEspecialidad, ['id_estado' => config('veterinaria.estado_activo_id')]);
    }

    /**
     * Limpia los datos antes de guardar:
     *   - quita espacios al inicio y al final,
     *   - pasa el código a MAYÚSCULAS ('cirugia_vet' -> 'CIRUGIA_VET'),
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

        if (isset($datos['codigo'])) {
            $datos['codigo'] = mb_strtoupper($datos['codigo']);
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
     * @param  int|null  $exceptoId  Especialidad que se edita (se excluye de los repetidos).
     *
     * @throws ValidationException
     */
    private function validarReglas(array $datos, ?int $exceptoId = null): void
    {
        $errores = [];

        if (! empty($datos['codigo'])
            && $this->especialidades->existeCodigo($datos['codigo'], $exceptoId)) {
            $errores['codigo'] = 'Ya existe una especialidad con este código.';
        }

        if (! empty($datos['nombre'])
            && $this->especialidades->existeNombre($datos['nombre'], $exceptoId)) {
            $errores['nombre'] = 'Ya existe una especialidad con este nombre.';
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
    }
}

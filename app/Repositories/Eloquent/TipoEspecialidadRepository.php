<?php

namespace App\Repositories\Eloquent;

// Modelo Eloquent de la tabla `t_tipo_especialidad`.
use App\Models\TipoEspecialidad;

// Contrato que define los métodos que debe tener este repositorio.
use App\Repositories\Contracts\TipoEspecialidadRepositoryInterface;

// Constructor de consultas; se usa en when() para excluir el registro que se edita.
use Illuminate\Database\Eloquent\Builder;

/**
 * Repositorio de la tabla `t_tipo_especialidad`.
 *
 * Hereda de BaseRepository el CRUD completo y agrega las consultas
 * para validar que `codigo` y `nombre` no se repitan (son UNIQUE en la tabla).
 *
 * @extends BaseRepository<TipoEspecialidad>
 */
class TipoEspecialidadRepository extends BaseRepository implements TipoEspecialidadRepositoryInterface
{
    /**
     * Obligatorio: le indica a Laravel que inyecte el modelo TipoEspecialidad
     * (sin él intentaría inyectar un Model genérico y fallaría).
     */
    public function __construct(TipoEspecialidad $model)
    {
        parent::__construct($model);
    }

    /**
     * Verifica si un código ya está registrado.
     *
     * Sirve para mostrar un mensaje claro en lugar del error de la
     * llave única `uk_tipo_especialidad_codigo`.
     *
     * SQL equivalente:
     *   SELECT EXISTS(SELECT 1 FROM t_tipo_especialidad
     *                 WHERE codigo = ? [AND id_tipo_especialidad <> ?]);
     *
     * @param  string    $codigo     Código a verificar (se compara en MAYÚSCULAS).
     * @param  int|null  $exceptoId  Id de la especialidad que se está editando (se excluye).
     * @return bool true si otra especialidad ya usa ese código.
     */
    public function existeCodigo(string $codigo, ?int $exceptoId = null): bool
    {
        return $this->query()
            ->where('codigo', mb_strtoupper(trim($codigo)))
            ->when($exceptoId, fn (Builder $q) => $q->where('id_tipo_especialidad', '<>', $exceptoId))
            ->exists();
    }

    /**
     * Verifica si un nombre ya está registrado.
     *
     * Sirve para mostrar un mensaje claro en lugar del error de la
     * llave única `uk_tipo_especialidad_nombre`.
     *
     * SQL equivalente:
     *   SELECT EXISTS(SELECT 1 FROM t_tipo_especialidad
     *                 WHERE nombre = ? [AND id_tipo_especialidad <> ?]);
     *
     * @param  string    $nombre     Nombre a verificar.
     * @param  int|null  $exceptoId  Id de la especialidad que se está editando (se excluye).
     * @return bool true si otra especialidad ya usa ese nombre.
     */
    public function existeNombre(string $nombre, ?int $exceptoId = null): bool
    {
        return $this->query()
            ->where('nombre', trim($nombre))
            ->when($exceptoId, fn (Builder $q) => $q->where('id_tipo_especialidad', '<>', $exceptoId))
            ->exists();
    }
}

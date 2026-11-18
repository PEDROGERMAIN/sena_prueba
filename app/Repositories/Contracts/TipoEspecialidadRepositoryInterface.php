<?php

namespace App\Repositories\Contracts;

use App\Models\TipoEspecialidad;

/**
 * Contrato del repositorio de la tabla `t_tipo_especialidad`.
 *
 * Además del CRUD de BaseRepositoryInterface
 * (all, paginate, find, findOrFail, create, update, delete),
 * declara las consultas para validar los UNIQUE de la tabla.
 *
 * @extends BaseRepositoryInterface<TipoEspecialidad>
 */
interface TipoEspecialidadRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * true si otra especialidad ya usa ese código (UNIQUE uk_tipo_especialidad_codigo).
     *
     * @param  int|null  $exceptoId  Especialidad que se edita (se excluye).
     */
    public function existeCodigo(string $codigo, ?int $exceptoId = null): bool;

    /**
     * true si otra especialidad ya usa ese nombre (UNIQUE uk_tipo_especialidad_nombre).
     *
     * @param  int|null  $exceptoId  Especialidad que se edita (se excluye).
     */
    public function existeNombre(string $nombre, ?int $exceptoId = null): bool;
}

<?php

namespace App\Repositories\Contracts;

use App\Models\Catalogo;

/**
 * Contrato del repositorio de la tabla `t_catalogo`.
 *
 * Sin consultas propias por ahora: solo el CRUD de BaseRepositoryInterface
 * (all, paginate, find, findOrFail, create, update, delete).
 * Cuando se necesite una consulta específica, se declara aquí
 * y se implementa en CatalogoRepository.
 *
 * @extends BaseRepositoryInterface<Catalogo>
 */
interface CatalogoRepositoryInterface extends BaseRepositoryInterface
{



}

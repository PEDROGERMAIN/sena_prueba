<?php

namespace App\Repositories\Eloquent;

// Modelo Eloquent de la tabla `t_catalogo`.
use App\Models\Catalogo;

// Interfaz (contrato) que define qué métodos debe tener este repositorio.
use App\Repositories\Contracts\CatalogoRepositoryInterface;

/**
 * Repositorio de la tabla `t_catalogo`.
 *
 * Valores parametrizables del sistema (estados, roles, especies, tipos de
 * documento...). Hereda de BaseRepository el CRUD completo; no tiene consultas
 * propias por ahora.
 *
 * @extends BaseRepository<Catalogo>
 */
class CatalogoRepository extends BaseRepository implements CatalogoRepositoryInterface
{
    /**
     * Obligatorio aunque el IDE lo marque como innecesario: le indica a
     * Laravel que inyecte el modelo Catalogo (sin él intentaría inyectar
     * un Model genérico y fallaría).
     */
    public function __construct(Catalogo $model)
    {
        parent::__construct($model);
    }
}

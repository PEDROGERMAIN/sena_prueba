<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Operaciones CRUD comunes a todos los repositorios.
 *
 * Genérico en PHPDoc: TModel es el modelo que maneja cada repositorio
 * (p. ej. BaseRepositoryInterface<Cliente>). PHP lo ignora al ejecutar,
 * pero el IDE y PHPStan/Larastan lo usan para inferir los tipos.
 *
 * @template TModel of Model
 */
interface BaseRepositoryInterface
{
    /**
     * @param  array<int, string>  $relations
     * @return Collection<int, TModel>
     */
    public function all(array $relations = []): Collection;

    /**
     * @param  array<int, string>  $relations
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(int $perPage = 15, array $relations = []): LengthAwarePaginator;

    /**
     * @param  array<int, string>  $relations
     * @return TModel|null
     */
    public function find(int $id, array $relations = []): ?Model;

    /**
     * @param  array<int, string>  $relations
     * @return TModel
     */
    public function findOrFail(int $id, array $relations = []): Model;

    /**
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function create(array $data): Model;

    /**
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function update(int $id, array $data): Model;

    public function delete(int $id): bool;
}

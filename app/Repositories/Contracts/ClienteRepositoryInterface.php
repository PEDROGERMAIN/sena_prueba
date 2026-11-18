<?php

namespace App\Repositories\Contracts;

use App\Models\Cliente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Contrato del repositorio de la tabla `t_cliente`.
 *
 * Además del CRUD común heredado de BaseRepositoryInterface
 * (all, paginate, find, findOrFail, create, update, delete),
 * define las consultas propias de los clientes.
 *
 * @extends BaseRepositoryInterface<Cliente>
 */
interface ClienteRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Busca un cliente por su número de documento.
     */
    public function findByDocumento(string $numeroDocumento): ?Cliente;

    /**
     * Busca un cliente por usuario (correo) y contraseña.
     * Devuelve null si el correo no existe o la contraseña no coincide.
     */
    public function findByCredenciales(string $correo, string $password): ?Cliente;

    /**
     * Indica si ya existe un cliente con ese número de documento.
     * Con $exceptoId se ignora a ese cliente (útil al editar).
     */
    public function existeDocumento(string $numeroDocumento, ?int $exceptoId = null): bool;

    /**
     * Indica si ya existe un cliente con ese correo (el correo es el usuario del login).
     * Con $exceptoId se ignora a ese cliente (útil al editar).
     */
    public function existeCorreo(string $correo, ?int $exceptoId = null): bool;

    /**
     * Clientes cuyo estado sea el indicado (id de `t_catalogo`), ordenados por nombre.
     *
     * @return Collection<int, Cliente>
     */
    public function porEstado(int $idEstado): Collection;

    /**
     * Búsqueda paginada por documento, nombres, apellidos, teléfono o correo.
     *
     * @return LengthAwarePaginator<int, Cliente>
     */
    public function buscar(?string $termino, int $perPage = 15): LengthAwarePaginator;

    /**
     * Cliente con sus mascotas, tipo de documento y estado ya cargados.
     */
    public function findConMascotas(int $idCliente): ?Cliente;

    /**
     * Borrado lógico: cambia el estado del cliente en vez de eliminar la fila.
     */
    public function cambiarEstado(int $idCliente, int $idEstado): Cliente;
}

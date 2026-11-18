<?php

namespace App\Repositories\Eloquent;

// Modelo Eloquent de la tabla `t_cliente`.
use App\Models\Cliente;

// Contrato que define los métodos que debe tener este repositorio.
use App\Repositories\Contracts\ClienteRepositoryInterface;

// Paginador que devuelve buscar(): resultados + total + páginas.
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

// Constructor de consultas; se usa para agrupar condiciones OR dentro de buscar().
use Illuminate\Database\Eloquent\Builder;

// Colección de modelos; es lo que devuelve porEstado().
use Illuminate\Database\Eloquent\Collection;

// Facade para cifrar y verificar contraseñas (bcrypt).
use Illuminate\Support\Facades\Hash;

/**
 * Repositorio de la tabla `t_cliente`.
 *
 * Centraliza las consultas sobre los propietarios de las mascotas.
 * Hereda de BaseRepository el CRUD común (all, paginate, find, create,
 * update, delete) y el método protegido query().
 *
 * Nota: `numero_documento` es UNIQUE en la tabla, por eso findByDocumento()
 * devuelve un solo registro.
 *
 * @extends BaseRepository<Cliente>
 */
class ClienteRepository extends BaseRepository implements ClienteRepositoryInterface
{
    /**
     * Relaciones que se cargan junto con el cliente para mostrarlo
     * (evita el problema N+1 al recorrer listas).
     *
     * @var array<int, string>
     */
    private const RELACIONES_BASICAS = ['tipoDocumento', 'estado'];

    /**
     * Recibe el modelo Cliente (inyectado por el contenedor de Laravel)
     * y lo entrega al repositorio base.
     */
    public function __construct(Cliente $model)
    {
        parent::__construct($model);
    }

    /**
     * Busca un cliente por su número de documento.
     *
     * SQL equivalente:
     *   SELECT * FROM t_cliente WHERE numero_documento = ? LIMIT 1;
     *
     * @param  string  $numeroDocumento  Número tal como se guardó, p. ej. '1023456789'.
     * @return Cliente|null El cliente encontrado, o null si no existe.
     */
    public function findByDocumento(string $numeroDocumento): ?Cliente
    {
        return $this->query()
            ->with(self::RELACIONES_BASICAS)
            ->where('numero_documento', trim($numeroDocumento))
            ->first();
    }

    /**
     * Busca un cliente por usuario (correo) y contraseña.
     *
     * La contraseña NO se busca con WHERE password = ?, porque en la tabla
     * está guardada cifrada (hash) y cada hash es distinto aunque la clave
     * sea la misma. Por eso se hace en dos pasos:
     *   1. SELECT * FROM t_cliente WHERE correo = ? LIMIT 1;
     *   2. Hash::check() compara la clave escrita con el hash guardado.
     *
     * @param  string  $correo    Usuario con el que inicia sesión, p. ej. 'juan@example.com'.
     * @param  string  $password  Contraseña en texto plano tal como la escribió.
     * @return Cliente|null El cliente si las credenciales son correctas; si no, null.
     */
    public function findByCredenciales(string $correo, string $password): ?Cliente
    {
        $cliente = $this->query()
            ->with(self::RELACIONES_BASICAS)
            ->where('correo', mb_strtolower(trim($correo)))
            ->first();

        // Sin cliente o sin contraseña registrada => credenciales inválidas.
        if (! $cliente || ! $cliente->password) {
            return null;
        }

        return Hash::check($password, $cliente->password) ? $cliente : null;
    }

    /**
     * Verifica si un número de documento ya está registrado.
     *
     * Sirve para validar antes de insertar y así mostrar un mensaje claro
     * en lugar del error de la llave única `uk_cliente_documento`.
     *
     * SQL equivalente:
     *   SELECT EXISTS(SELECT 1 FROM t_cliente
     *                 WHERE numero_documento = ? [AND id_cliente <> ?]);
     *
     * @param  string    $numeroDocumento  Documento a verificar.
     * @param  int|null  $exceptoId        Id del cliente que se está editando (se excluye).
     * @return bool true si otro cliente ya usa ese documento.
     */
    public function existeDocumento(string $numeroDocumento, ?int $exceptoId = null): bool
    {
        return $this->query()
            ->where('numero_documento', trim($numeroDocumento))
            ->when($exceptoId, fn (Builder $q) => $q->where('id_cliente', '<>', $exceptoId))
            ->exists();
    }

    /**
     * Verifica si un correo ya está registrado.
     *
     * El correo es el usuario del login, por eso no puede repetirse.
     *
     * SQL equivalente:
     *   SELECT EXISTS(SELECT 1 FROM t_cliente
     *                 WHERE correo = ? [AND id_cliente <> ?]);
     *
     * @param  string    $correo     Correo a verificar (se compara en minúsculas).
     * @param  int|null  $exceptoId  Id del cliente que se está editando (se excluye).
     * @return bool true si otro cliente ya usa ese correo.
     */
    public function existeCorreo(string $correo, ?int $exceptoId = null): bool
    {
        return $this->query()
            ->where('correo', mb_strtolower(trim($correo)))
            ->when($exceptoId, fn (Builder $q) => $q->where('id_cliente', '<>', $exceptoId))
            ->exists();
    }

    /**
     * Obtiene los clientes de un estado (p. ej. solo los activos),
     * ordenados por apellidos y nombres.
     *
     * SQL equivalente:
     *   SELECT * FROM t_cliente WHERE id_estado = ? ORDER BY apellidos, nombres;
     *
     * @param  int  $idEstado  Id del registro de `t_catalogo` del tipo de estado.
     * @return Collection<int, Cliente>
     */
    public function porEstado(int $idEstado): Collection
    {
        return $this->query()
            ->with(self::RELACIONES_BASICAS)
            ->where('id_estado', $idEstado)
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get();
    }

    /**
     * Búsqueda paginada para el listado de clientes.
     *
     * Si no hay término, devuelve todos. Las condiciones OR se agrupan
     * entre paréntesis para no romper otros filtros que se agreguen después.
     *
     * SQL equivalente:
     *   SELECT * FROM t_cliente
     *   WHERE (numero_documento LIKE ? OR nombres LIKE ? OR apellidos LIKE ?
     *          OR telefono LIKE ? OR correo LIKE ?)
     *   ORDER BY apellidos, nombres
     *   LIMIT ? OFFSET ?;
     *
     * @param  string|null  $termino  Texto a buscar, p. ej. 'gomez' o '1023'.
     * @param  int          $perPage  Registros por página.
     * @return LengthAwarePaginator<int, Cliente>
     */
    public function buscar(?string $termino, int $perPage = 15): LengthAwarePaginator
    {
        $termino = trim((string) $termino);

        return $this->query()
            ->with(self::RELACIONES_BASICAS)
            ->withCount('mascotas') // Agrega la columna mascotas_count
            ->when($termino !== '', function (Builder $q) use ($termino) {
                // Escapa % y _ para que se busquen como texto literal.
                $like = '%'.addcslashes($termino, '%_\\').'%';

                $q->where(function (Builder $sub) use ($like) {
                    $sub->where('numero_documento', 'like', $like)
                        ->orWhere('nombres', 'like', $like)
                        ->orWhere('apellidos', 'like', $like)
                        ->orWhere('telefono', 'like', $like)
                        ->orWhere('correo', 'like', $like);
                });
            })
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->paginate($perPage)
            ->withQueryString(); // Conserva ?termino=... en los enlaces de página
    }

    /**
     * Obtiene un cliente con sus mascotas (y la especie de cada una),
     * su tipo de documento y su estado, en pocas consultas.
     *
     * @param  int  $idCliente  Llave primaria del cliente.
     * @return Cliente|null El cliente con relaciones cargadas, o null si no existe.
     */
    public function findConMascotas(int $idCliente): ?Cliente
    {
        return $this->find($idCliente, [
            ...self::RELACIONES_BASICAS,
            'mascotas' => fn ($q) => $q->orderBy('nombre'),
            'mascotas.especie',
        ]);
    }

    /**
     * Borrado lógico: actualiza `id_estado` en lugar de hacer DELETE.
     *
     * Se prefiere a delete() porque el cliente tiene mascotas asociadas
     * (llave foránea) y además se conserva el historial.
     *
     * SQL equivalente:
     *   UPDATE t_cliente SET id_estado = ? WHERE id_cliente = ?;
     *
     * @param  int  $idCliente  Cliente a modificar.
     * @param  int  $idEstado   Nuevo estado (id de `t_catalogo`), p. ej. INACTIVO.
     * @return Cliente El cliente actualizado.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Si el cliente no existe.
     */
    public function cambiarEstado(int $idCliente, int $idEstado): Cliente
    {
        return $this->update($idCliente, ['id_estado' => $idEstado]);
    }
}

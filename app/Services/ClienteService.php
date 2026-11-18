<?php

namespace App\Services;

// Modelo de la tabla t_cliente.
use App\Models\Cliente;

// Repositorios que usa el servicio (se inyectan por su interfaz).
use App\Repositories\Contracts\CatalogoRepositoryInterface;
use App\Repositories\Contracts\ClienteRepositoryInterface;

// Paginador que devuelve listar().
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

// Error 404 cuando el cliente no existe.
use Illuminate\Database\Eloquent\ModelNotFoundException;

// Excepción que Laravel convierte en errores de formulario ($errors en Blade).
use Illuminate\Validation\ValidationException;

/**
 * Reglas de negocio de los clientes.
 *
 * Reparto de responsabilidades:
 *   - FormRequest: formato de los campos (requerido, email, longitud...).
 *   - ClienteService: reglas que dependen de la base de datos
 *     (documento y correo únicos, tipo de documento válido, estados).
 *   - ClienteRepository: solo consultas.
 *
 * Los clientes no se borran: se inactivan (borrado lógico), porque tienen
 * mascotas asociadas y su historial debe conservarse.
 */
class ClienteService
{
    /**
     * Columnas que pueden quedar vacías; '' se guarda como NULL.
     *
     * @var array<int, string>
     */
    private const OPCIONALES = ['apellidos', 'telefono', 'correo', 'direccion'];

    public function __construct(
        private ClienteRepositoryInterface $clientes,
        private CatalogoRepositoryInterface $catalogos,
    ) {}

    /**
     * Listado paginado con búsqueda por documento, nombre, teléfono o correo.
     *
     * @return LengthAwarePaginator<int, Cliente>
     */
    public function listar(?string $termino = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->clientes->buscar($termino, $perPage);
    }

    /**
     * Detalle de un cliente con sus mascotas, tipo de documento y estado.
     *
     * @throws ModelNotFoundException Si no existe (Laravel responde 404).
     */
    public function obtener(int $idCliente): Cliente
    {
        return $this->clientes->findConMascotas($idCliente)
            ?? throw (new ModelNotFoundException)->setModel(Cliente::class, [$idCliente]);
    }

    /**
     * Registra un cliente nuevo. Si no se envía estado, queda activo.
     *
     * @param  array<string, mixed>  $datos  Campos ya validados por el FormRequest.
     *
     * @throws ValidationException Documento o correo repetidos, o tipo de documento inválido.
     */
    public function registrar(array $datos): Cliente
    {
        $datos = $this->normalizar($datos);

        $this->validarReglas($datos);

        $datos['id_estado'] ??= config('veterinaria.estado_activo_id');

        // El cast 'hashed' del modelo cifra la contraseña al guardar.
        return $this->clientes->create($datos);
    }

    /**
     * Actualiza los datos de un cliente.
     *
     * Si la contraseña llega vacía se conserva la actual.
     *
     * @param  array<string, mixed>  $datos  Campos ya validados por el FormRequest.
     *
     * @throws ModelNotFoundException Si el cliente no existe.
     * @throws ValidationException    Documento o correo repetidos, o tipo de documento inválido.
     */
    public function actualizar(int $idCliente, array $datos): Cliente
    {
        $datos = $this->normalizar($datos);

        $this->validarReglas($datos, $idCliente);

        return $this->clientes->update($idCliente, $datos);
    }

    /**
     * Cambia la contraseña con la que el cliente inicia sesión.
     *
     * @throws ModelNotFoundException Si el cliente no existe.
     */
    public function cambiarPassword(int $idCliente, string $nueva): Cliente
    {
        return $this->clientes->update($idCliente, ['password' => $nueva]);
    }

    /**
     * Borrado lógico: el cliente deja de aparecer como activo y no puede iniciar sesión.
     *
     * @throws ModelNotFoundException Si el cliente no existe.
     */
    public function inactivar(int $idCliente): Cliente
    {
        return $this->clientes->cambiarEstado($idCliente, config('veterinaria.estado_inactivo_id'));
    }

    /**
     * Vuelve a activar un cliente inactivo.
     *
     * @throws ModelNotFoundException Si el cliente no existe.
     */
    public function activar(int $idCliente): Cliente
    {
        return $this->clientes->cambiarEstado($idCliente, config('veterinaria.estado_activo_id'));
    }

    /**
     * Limpia los datos antes de guardar:
     *   - quita espacios al inicio y al final,
     *   - pasa el correo a minúsculas (el login lo busca así),
     *   - convierte '' en NULL en los campos opcionales,
     *   - descarta la contraseña si viene vacía.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function normalizar(array $datos): array
    {
        foreach ($datos as $campo => $valor) {
            if (is_string($valor) && $campo !== 'password') {
                $datos[$campo] = trim($valor);
            }
        }

        if (isset($datos['correo'])) {
            $datos['correo'] = mb_strtolower($datos['correo']);
        }

        foreach (self::OPCIONALES as $campo) {
            if (array_key_exists($campo, $datos) && $datos[$campo] === '') {
                $datos[$campo] = null;
            }
        }

        if (array_key_exists('password', $datos) && blank($datos['password'])) {
            unset($datos['password']);
        }

        return $datos;
    }

    /**
     * Reglas que dependen de la base de datos. Junta todos los errores
     * para mostrarlos de una vez en el formulario.
     *
     * @param  array<string, mixed>  $datos
     * @param  int|null  $exceptoId  Cliente que se edita (se excluye de los repetidos).
     *
     * @throws ValidationException
     */
    private function validarReglas(array $datos, ?int $exceptoId = null): void
    {
        $errores = [];

        if (! empty($datos['numero_documento'])
            && $this->clientes->existeDocumento($datos['numero_documento'], $exceptoId)) {
            $errores['numero_documento'] = 'Ya existe un cliente con este número de documento.';
        }

        if (! empty($datos['correo'])
            && $this->clientes->existeCorreo($datos['correo'], $exceptoId)) {
            $errores['correo'] = 'Este correo ya está registrado por otro cliente.';
        }

        if (isset($datos['id_tipo_documento'])
            && ! $this->esTipoDocumentoValido((int) $datos['id_tipo_documento'])) {
            $errores['id_tipo_documento'] = 'El tipo de documento seleccionado no es válido.';
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
    }

    /**
     * La llave foránea solo exige que el id exista en t_catalogo;
     * aquí se verifica además que sea un tipo de documento y que esté activo.
     */
    private function esTipoDocumentoValido(int $idCatalogo): bool
    {
        $catalogo = $this->catalogos->find($idCatalogo);

        return $catalogo !== null
            && $catalogo->tipo === config('veterinaria.catalogo_tipo_documento')
            && $catalogo->activo;
    }
}

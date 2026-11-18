<?php

namespace App\Models;

// Importación agrupada del namespace Illuminate\Database\Eloquent
// (PHP no permite `use ...\*`, así que se listan las clases dentro de llaves).
use Illuminate\Database\Eloquent\{
    Attributes\Fillable, // Atributo: columnas que se pueden llenar de forma masiva (create(), fill(), update()).
    Attributes\Table,    // Atributo: nombre de la tabla, llave primaria y timestamps.
    Builder,             // Constructor de consultas; lo reciben y devuelven los scopes.
    Model,               // Clase base de los modelos Eloquent (find(), create(), save(), where()...).
};

/**
 * Modelo de la tabla `t_catalogo`.
 *
 * Valores parametrizables del sistema (estados, roles, especies, tipos de documento...).
 * Se agrupan por la columna `tipo`, p. ej. ESTADO_GENERAL, ESPECIE, ROL_USUARIO,
 * y dentro de cada tipo se identifican por su `codigo`.
 *
 * Columnas:
 * @property int         $id_catalogo  Llave primaria.
 * @property string      $tipo         Grupo al que pertenece el valor, p. ej. 'ESPECIE'.
 * @property string      $codigo       Código del valor dentro de su tipo, p. ej. 'PER'.
 * @property string      $nombre       Texto que se muestra al usuario, p. ej. 'Perro'.
 * @property string|null $descripcion  Explicación opcional del valor.
 * @property int         $orden        Posición en la que se muestra en las listas.
 * @property bool        $activo       Si el valor está habilitado para usarse.
 *
 * Scopes:
 * @method static Builder<static> deTipo(string $tipo)  Filtra por tipo de catálogo.
 * @method static Builder<static> activos()             Filtra solo los registros activos.
 */
// name: nombre real de la tabla (Laravel buscaría `catalogos` por defecto).
// key: llave primaria (por defecto sería `id`).
// timestamps: false porque la tabla no tiene `created_at` ni `updated_at`.
#[Table(name: 't_catalogo', key: 'id_catalogo', timestamps: false)]
// Columnas que se pueden asignar en bloque, p. ej. Catalogo::create([...]).
// Cualquier otra columna enviada (como `id_catalogo`) se ignora: protege contra asignación masiva.
#[Fillable(['tipo', 'codigo', 'nombre', 'descripcion', 'orden', 'activo'])]
class Catalogo extends Model
{
    /**
     * Conversiones automáticas de tipo al leer y guardar las columnas.
     *
     * La base de datos devuelve los valores como texto o número; con los casts
     * Laravel los entrega con el tipo correcto de PHP.
     *
     * @return array<string, string> Columna => tipo al que se convierte.
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',  // '3' -> 3
            'activo' => 'boolean', // 1 / 0 -> true / false
        ];
    }

    /**
     * Scope local: filtra los registros de un tipo de catálogo.
     *
     * Se usa sin el prefijo "scope": Catalogo::query()->deTipo('ESPECIE')
     * SQL equivalente: WHERE tipo = ?
     *
     * @param  Builder  $query  Consulta en construcción (Laravel la pasa automáticamente).
     * @param  string   $tipo   Grupo del catálogo, p. ej. 'ESPECIE'.
     * @return Builder La misma consulta con el filtro agregado, para seguir encadenando.
     */
    public function scopeDeTipo(Builder $query, string $tipo): Builder
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Scope local: filtra solo los registros activos.
     *
     * Se usa sin el prefijo "scope": Catalogo::query()->activos()
     * SQL equivalente: WHERE activo = 1
     *
     * @param  Builder  $query  Consulta en construcción (Laravel la pasa automáticamente).
     * @return Builder La misma consulta con el filtro agregado, para seguir encadenando.
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}

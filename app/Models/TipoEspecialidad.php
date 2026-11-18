<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de la tabla `t_tipo_especialidad`.
 *
 * Especialidades que puede ejercer el personal de la veterinaria
 * (p. ej. VET_GENERAL, CIRUGIA_VET, ESTETICA_ANIMAL).
 *
 * @property int         $id_tipo_especialidad         Llave primaria.
 * @property string      $codigo                       Código único, p. ej. 'CIRUGIA_VET'.
 * @property string      $nombre                       Nombre único que se muestra al usuario.
 * @property string|null $descripcion                  Explicación opcional.
 * @property bool        $requiere_tarjeta_profesional Si exige tarjeta profesional.
 * @property int         $id_estado                    FK a t_catalogo (estado).
 */
#[Table(name: 't_tipo_especialidad', key: 'id_tipo_especialidad', timestamps: false)]
#[Fillable([
    'codigo',
    'nombre',
    'descripcion',
    'requiere_tarjeta_profesional',
    'id_estado',
])]
class TipoEspecialidad extends Model
{
    protected function casts(): array
    {
        return [
            // 1 / 0 -> true / false
            'requiere_tarjeta_profesional' => 'boolean',
        ];
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Catalogo::class, 'id_estado', 'id_catalogo');
    }
}

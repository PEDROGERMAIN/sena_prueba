<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 't_mascota', key: 'id_mascota', timestamps: false)]
#[Fillable([
    'id_cliente',
    'nombre',
    'id_especie',
    'raza',
    'sexo',
    'fecha_nacimiento',
    'color',
    'peso_actual_kg',
    'microchip',
    'id_estado',
])]
class Mascota extends Model
{
    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'peso_actual_kg' => 'decimal:2',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    public function especie(): BelongsTo
    {
        return $this->belongsTo(Catalogo::class, 'id_especie', 'id_catalogo');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Catalogo::class, 'id_estado', 'id_catalogo');
    }
}

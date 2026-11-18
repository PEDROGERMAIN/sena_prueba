<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table(name: 't_cliente', key: 'id_cliente', timestamps: false)]
#[Fillable([
    'id_tipo_documento',
    'numero_documento',
    'nombres',
    'apellidos',
    'telefono',
    'correo',
    'direccion',
    'id_estado',
    'password',
])]
// Nunca se incluye el hash de la contraseña al convertir el modelo a array/JSON.
#[Hidden(['password'])]
// AuthenticatableContract + trait Authenticatable: permiten iniciar sesión
// con el guard 'cliente' (Auth::guard('cliente')->login($cliente)).
class Cliente extends Model implements AuthenticatableContract
{
    use Authenticatable;

    /**
     * t_cliente no tiene columna `remember_token`, así que se desactiva
     * la opción "recordarme". Con '' Laravel no intenta leerla ni escribirla.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }

    protected function casts(): array
    {
        return [
            // Al asignar $cliente->password = '1234' se guarda cifrado con bcrypt.
            'password' => 'hashed',
        ];
    }

    /** CLIENTE 1 : N MASCOTA */
    public function mascotas(): HasMany
    {
        return $this->hasMany(Mascota::class, 'id_cliente', 'id_cliente');
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(Catalogo::class, 'id_tipo_documento', 'id_catalogo');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Catalogo::class, 'id_estado', 'id_catalogo');
    }
}

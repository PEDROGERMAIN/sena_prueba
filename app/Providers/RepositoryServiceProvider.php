<?php

namespace App\Providers;

use App\Repositories\Contracts\CatalogoRepositoryInterface;
use App\Repositories\Contracts\ClienteRepositoryInterface;
use App\Repositories\Contracts\MascotaRepositoryInterface;
use App\Repositories\Contracts\TipoEspecialidadRepositoryInterface;
use App\Repositories\Eloquent\CatalogoRepository;
use App\Repositories\Eloquent\ClienteRepository;
use App\Repositories\Eloquent\MascotaRepository;
use App\Repositories\Eloquent\TipoEspecialidadRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Une cada interfaz de repositorio con su implementación.
 * Al pedir la interfaz (inyección en controladores/servicios),
 * Laravel entrega la implementación Eloquent.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        CatalogoRepositoryInterface::class => CatalogoRepository::class,
        ClienteRepositoryInterface::class => ClienteRepository::class,
        MascotaRepositoryInterface::class => MascotaRepository::class,
        TipoEspecialidadRepositoryInterface::class => TipoEspecialidadRepository::class,


        
    ];
}

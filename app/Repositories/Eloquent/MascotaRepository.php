<?php

namespace App\Repositories\Eloquent;

use App\Models\Mascota;
use App\Repositories\Contracts\MascotaRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Mascota>
 */
class MascotaRepository extends BaseRepository implements MascotaRepositoryInterface
{
    public function __construct(Mascota $model)
    {
        parent::__construct($model);
    }

    public function findByCliente(int $idCliente): Collection
    {
        return $this->query()
            ->where('id_cliente', $idCliente)
            ->orderBy('nombre')
            ->get();
    }

    public function findByMicrochip(string $microchip): ?Mascota
    {
        return $this->query()
            ->where('microchip', $microchip)
            ->first();
    }
}

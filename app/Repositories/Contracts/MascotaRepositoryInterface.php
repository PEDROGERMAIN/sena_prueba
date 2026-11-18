<?php

namespace App\Repositories\Contracts;

use App\Models\Mascota;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<Mascota>
 */
interface MascotaRepositoryInterface extends BaseRepositoryInterface
{
    /** @return Collection<int, Mascota> */
    public function findByCliente(int $idCliente): Collection;

    public function findByMicrochip(string $microchip): ?Mascota;
}

<?php

namespace App\Services;

// Modelo que representa al cliente que inicia sesión.
use App\Models\Cliente;

// Repositorio de clientes; aquí está findByCredenciales().
use App\Repositories\Contracts\ClienteRepositoryInterface;

// Guard de sesión (Auth::guard('cliente')).
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;

// Limita los intentos fallidos para frenar ataques de fuerza bruta.
use Illuminate\Support\Facades\RateLimiter;

// Sesión HTTP; se regenera al entrar y se invalida al salir.
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

// Excepción que Laravel convierte en errores de formulario ($errors en Blade).
use Illuminate\Validation\ValidationException;

/**
 * Lógica del inicio y cierre de sesión de los clientes.
 *
 * El controlador solo recibe el formulario y llama a este servicio.
 * El servicio:
 *   1. Revisa que no se hayan superado los intentos permitidos.
 *   2. Pide al repositorio el cliente por correo + contraseña.
 *   3. Verifica que el cliente esté activo.
 *   4. Abre la sesión con el guard 'cliente'.
 *
 * Si algo falla lanza ValidationException, que Laravel convierte
 * automáticamente en un "redirect back" con el mensaje de error.
 */
class LoginService
{
    /**
     * Laravel inyecta la implementación registrada en RepositoryServiceProvider.
     */
    public function __construct(
        private ClienteRepositoryInterface $clientes
        
    ) {}

    /**
     * Inicia sesión de un cliente.
     *
     * @param  string  $correo    Usuario (correo) escrito en el formulario.
     * @param  string  $password  Contraseña en texto plano.
     * @param  string  $ip        IP de quien intenta entrar ($request->ip()).
     * @return Cliente El cliente que quedó autenticado.
     *
     * @throws ValidationException Credenciales incorrectas, cliente inactivo o demasiados intentos.
     */
    public function login(string $correo, string $password, string $ip): Cliente
    {
        $llave = $this->llaveIntentos($correo, $ip);

        $this->verificarBloqueo($llave);

        $cliente = $this->clientes->findByCredenciales($correo, $password);

        if (! $cliente) {
            // Suma un intento fallido; el contador se borra solo al pasar el bloqueo.
            RateLimiter::hit($llave, config('veterinaria.login_bloqueo_segundos'));

            // Mismo mensaje si el correo no existe o si la clave está mal:
            // así no se revela qué correos están registrados.
            throw ValidationException::withMessages([
                'correo' => 'El correo o la contraseña son incorrectos.',
            ]);
        }

        if ((int) $cliente->id_estado !== config('veterinaria.estado_activo_id')) {
            throw ValidationException::withMessages([
                'correo' => 'Tu cuenta está inactiva. Comunícate con la veterinaria.',
            ]);
        }

        RateLimiter::clear($llave);

        $this->guard()->login($cliente);

        // Nuevo id de sesión para evitar fijación de sesión (session fixation).
        Session::regenerate();

        return $cliente;
    }

    /**
     * Cierra la sesión del cliente y descarta los datos de la sesión.
     */
    public function logout(): void
    {
        $this->guard()->logout();

        Session::invalidate();       // Borra los datos de la sesión
        Session::regenerateToken();  // Nuevo token CSRF
    }

    /**
     * Cliente con sesión abierta, o null si nadie ha iniciado sesión.
     */
    public function clienteActual(): ?Cliente
    {
        /** @var Cliente|null */
        return $this->guard()->user();
    }

    /**
     * Guard de sesión de los clientes, definido en config/auth.php.
     */
    private function guard(): StatefulGuard
    {
        return Auth::guard('cliente');
    }

    /**
     * Si ya superó los intentos permitidos, lanza el error con los segundos de espera.
     *
     * @throws ValidationException
     */
    private function verificarBloqueo(string $llave): void
    {
        if (! RateLimiter::tooManyAttempts($llave, config('veterinaria.login_max_intentos'))) {
            return;
        }

        $segundos = RateLimiter::availableIn($llave);

        throw ValidationException::withMessages([
            'correo' => "Demasiados intentos. Intenta de nuevo en {$segundos} segundos.",
        ]);
    }

    /**
     * Llave del contador de intentos: correo + IP.
     * Así un atacante no bloquea a otros usuarios desde su IP.
     */
    private function llaveIntentos(string $correo, string $ip): string
    {
        return 'login-cliente:'.Str::lower(trim($correo)).'|'.$ip;
    }
}

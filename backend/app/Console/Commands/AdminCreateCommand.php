<?php

namespace App\Console\Commands;

use App\Domain\Users\Models\User;
use Illuminate\Console\Command;

/**
 * §3.1: el único camino seguro para crear el primer admin.
 * RB-01: el rol admin jamás existe en registro público ni Google.
 * Otros admins pueden promover docentes ya aprobados vía endpoint admin.
 */
final class AdminCreateCommand extends Command
{
    protected $signature = 'edugt:admin-create {--name=} {--email=} {--password=}';

    protected $description = 'Crea (o promueve) la cuenta administradora inicial — solo CLI';

    public function handle(): int
    {
        $name = $this->option('name') ?? $this->ask('Nombre del administrador');
        $email = $this->option('email') ?? $this->ask('Correo del administrador');
        $password = $this->option('password') ?? $this->secret('Contraseña (mínimo 8 caracteres)');

        if (strlen((string) $password) < 8) {
            $this->error('La contraseña debe tener al menos 8 caracteres.');

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            if ($existing->isAdmin()) {
                $this->info('Ese correo ya es administrador.');

                return self::SUCCESS;
            }

            // Promoción manual consciente de un usuario YA existente
            $existing->forceFill(['role' => 'admin', 'status' => 'active'])->save();
            $this->info("Usuario existente promovido a admin: {$existing->email}");

            return self::SUCCESS;
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => \Illuminate\Support\Facades\Hash::make($password),
        ])->forceFill(['role' => 'admin', 'status' => 'active'])->save();

        $this->info("Administrador creado: {$email}");

        return self::SUCCESS;
    }
}

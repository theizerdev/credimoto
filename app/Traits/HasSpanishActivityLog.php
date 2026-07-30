<?php

namespace App\Traits;

trait HasSpanishActivityLog
{
    /**
     * Obtener la descripción traducida al español para el evento de actividad.
     *
     * @param string $eventName
     * @return string
     */
    public static function getSpanishDescription(string $eventName): string
    {
        return match ($eventName) {
            'created' => 'Creado',
            'updated' => 'Actualizado',
            'deleted' => 'Eliminado',
            'restored' => 'Restaurado',
            default => ucfirst($eventName),
        };
    }
}

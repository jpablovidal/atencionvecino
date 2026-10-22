<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Parte de un expediente: persona u organismo a quien se dirige o interesa el procedimiento.
 *
 * @property int $id
 * @property int $expediente_id
 * @property string $tipo ciudadano|funcionario|externo|organismo
 * @property string $rol interesado|destinatario|copia
 * @property string $nombre
 * @property string|null $run
 * @property string|null $correo
 * @property int|null $usuario_id
 * @property int|null $dd_entidad_id
 * @property string|null $canal
 */
class ExpedienteParte extends Model
{
    protected $table = 'expediente_partes';

    protected $guarded = ['id'];

    public const TIPOS = ['ciudadano' => 'Persona (vecino/a)', 'funcionario' => 'Funcionario(a) municipal', 'externo' => 'Persona o entidad externa', 'organismo' => 'Organismo público en DocDigital'];

    public const ROLES = ['interesado' => 'Interesado(a)', 'destinatario' => 'Destinatario(a)', 'copia' => 'Con copia'];

    /** Canales de notificación que admite cada tipo de parte. */
    public const CANALES = [
        'ciudadano' => ['casilla_unica' => 'Casilla Única (notificador)', 'correo' => 'Correo electrónico', 'presencial' => 'Entrega física'],
        'funcionario' => ['interno' => 'Aviso interno (queda como acción pendiente)'],
        'externo' => ['correo' => 'Correo electrónico', 'presencial' => 'Entrega física'],
        'organismo' => ['docdigital' => 'DocDigital'],
    ];

    /** @return BelongsTo<Expediente, $this> */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    /** @return list<array{id:int,nombre:string}> */
    public function personasDd(): array
    {
        return array_values((array) json_decode((string) $this->dd_usuarios, true));
    }
}

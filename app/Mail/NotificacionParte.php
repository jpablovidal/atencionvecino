<?php

namespace App\Mail;

use App\Models\Notificacion;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Notificación de un documento a una parte de un expediente (persona externa o vecino sin Casilla Única). Sin adjuntos: enlaza a la validación pública. */
class NotificacionParte extends Mailable
{
    public function __construct(public Notificacion $notificacion) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Municipalidad de Santo Domingo - Notificación de un documento');
    }

    public function content(): Content
    {
        $d = $this->notificacion->documento;
        $publico = $d && $d->esPublico();

        return new Content(html: 'emails.notificacion_parte', text: 'emails.texto.notificacion_parte', with: [
            'nombre' => $this->notificacion->destinatario_nombre, 'documento' => $d ? $d->numeroFormateado().' — '.$d->titulo : 'Documento',
            'enlace' => $d ? route('validar', $d->codigo_validacion) : url('/validar'), 'copia' => $publico && $d ? route('validar.copia', $d->codigo_validacion) : null,
        ]);
    }

    /** @return array<int,never> */
    public function attachments(): array
    {
        return []; // nunca se adjuntan documentos
    }
}

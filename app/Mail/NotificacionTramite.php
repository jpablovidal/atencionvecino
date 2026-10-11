<?php

namespace App\Mail;

use App\Models\Solicitud;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Sin adjuntos ni datos sensibles: solo folio, estado y enlace a la sección autenticada. */
class NotificacionTramite extends Mailable
{
    public function __construct(public Solicitud $solicitud) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Municipalidad de Santo Domingo - Actualización de su solicitud '.$this->solicitud->folio);
    }

    public function content(): Content
    {
        return new Content(text: 'emails.notificacion', with: [
            'folio' => $this->solicitud->folio, 'estado' => str_replace('_', ' ', $this->solicitud->estado),
            'enlace' => route('ciudadano.solicitud', $this->solicitud->folio),
        ]);
    }

    /** @return array<int,never> */
    public function attachments(): array
    {
        return []; // nunca se adjuntan documentos
    }
}

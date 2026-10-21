<?php

namespace App\Mail;

use App\Models\RespuestaFinal;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Copia por correo del despacho de la respuesta final: nunca adjunta documentos. */
class RespuestaFinalNotificada extends Mailable
{
    public function __construct(public RespuestaFinal $respuesta) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Municipalidad de Santo Domingo - Respuesta a su solicitud '.$this->respuesta->solicitud->folio);
    }

    public function content(): Content
    {
        return new Content(html: 'emails.respuesta_final', text: 'emails.texto.respuesta_final', with: [
            'folio' => $this->respuesta->solicitud->folio, 'expediente' => $this->respuesta->expediente->codigo, 'cantidad' => $this->respuesta->items()->count(),
            'enlace' => route('ciudadano.solicitud', $this->respuesta->solicitud->folio),
        ]);
    }

    /** @return array<int,never> */
    public function attachments(): array
    {
        return [];
    }
}

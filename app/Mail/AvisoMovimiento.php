<?php

namespace App\Mail;

use App\Models\Solicitud;
use App\Models\SolicitudMovimiento;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

/** Copia por correo del aviso de Casilla Única: número de solicitud, expediente, movimiento y enlace. Sin adjuntos ni datos personales. */
class AvisoMovimiento extends Mailable
{
    public function __construct(public Solicitud $solicitud, public SolicitudMovimiento $movimiento) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Municipalidad de Santo Domingo - Novedad en su solicitud '.$this->solicitud->folio);
    }

    public function content(): Content
    {
        $e = $this->solicitud->expediente;

        return new Content(html: 'emails.aviso_movimiento', text: 'emails.texto.aviso_movimiento', with: [
            'folio' => $this->solicitud->folio, 'expediente' => $e?->codigo, 'descripcion' => $this->movimiento->descripcion,
            'fecha' => Carbon::parse($this->movimiento->created_at)->setTimezone('America/Santiago')->format('d-m-Y H:i'),
            'direccion' => $this->movimiento->unidad?->nombre, 'enlace' => route('ciudadano.solicitud', $this->solicitud->folio),
        ]);
    }

    /** @return array<int,never> */
    public function attachments(): array
    {
        return [];
    }
}

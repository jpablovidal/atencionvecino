<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\DocumentoVersionEvento;
use App\Models\Usuario;
use Illuminate\Support\Collection;

/**
 * Historial de versiones de un documento: qué cambió respecto de la versión anterior y quién la editó, visó o firmó.
 * Los pasos de visación/firma se borran al editar (el flujo se reinicia); por eso cada acción se registra aquí con su versión.
 */
class DocumentoHistorialService
{
    public function registrar(Documento $doc, string $evento, Usuario $u, ?string $detalle = null, ?int $version = null): void
    {
        DocumentoVersionEvento::query()->create(['documento_id' => $doc->id, 'version' => $version ?? $doc->version_actual, 'evento' => $evento, 'usuario_id' => $u->id,
            'detalle' => $detalle !== null ? mb_substr($detalle, 0, 300) : null]);
    }

    /** @return list<array{v:DocumentoVersion,vigente:bool,cambios:array<string,mixed>,eventos:Collection}> de la más nueva a la más antigua */
    public function historial(Documento $doc): array
    {
        $vers = DocumentoVersion::query()->where('documento_id', $doc->id)->orderBy('version')->get();
        $eventos = DocumentoVersionEvento::query()->with('usuario')->where('documento_id', $doc->id)->orderBy('id')->get()->groupBy('version');
        $autores = Usuario::query()->whereIn('id', $vers->pluck('creado_por'))->get()->keyBy('id');
        $out = [];
        $prev = null;
        foreach ($vers as $v) {
            $ev = $eventos->get($v->version, collect());
            if (! $ev->contains(fn ($e) => $e->evento === 'editado') && isset($autores[$v->creado_por])) { // versiones anteriores al registro de eventos
                $o = new DocumentoVersionEvento(['evento' => 'editado', 'usuario_id' => $v->creado_por, 'detalle' => 'Versión '.$v->version, 'created_at' => $v->created_at]);
                $ev = $ev->prepend($o->setRelation('usuario', $autores[$v->creado_por]));
            }
            $out[] = ['v' => $v, 'vigente' => $v->version === (int) $doc->version_actual, 'cambios' => $this->cambios($prev?->html, $v->html), 'eventos' => $ev];
            $prev = $v;
        }

        return array_reverse($out);
    }

    /** @return array<string,mixed> */
    public function cambios(?string $antes, string $despues): array
    {
        if ($antes === null) {
            return ['inicial' => true, 'resumen' => 'Primera versión del documento.', 'agregados' => [], 'quitados' => []];
        }
        $a = $this->parrafos($antes);
        $b = $this->parrafos($despues);
        $quitados = array_values(array_diff($a, $b));
        $agregados = array_values(array_diff($b, $a));
        if (! $quitados && ! $agregados) {
            return ['inicial' => false, 'resumen' => 'Sin cambios en el texto (solo formato o estructura).', 'agregados' => [], 'quitados' => []];
        }

        return ['inicial' => false, 'resumen' => count($agregados).' párrafo(s) agregado(s) o modificado(s) y '.count($quitados).' quitado(s) o reemplazado(s).',
            'agregados' => array_map(fn ($x) => mb_substr($x, 0, 200), array_slice($agregados, 0, 5)), 'quitados' => array_map(fn ($x) => mb_substr($x, 0, 200), array_slice($quitados, 0, 5))];
    }

    /** @return list<string> */
    private function parrafos(string $html): array
    {
        $t = preg_replace('#</(p|div|h[1-6]|li|tr|br)\s*>|<br\s*/?>#i', "\n", $html) ?? $html;
        $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return array_values(array_filter(array_map(fn ($l) => trim(preg_replace('/\s+/u', ' ', $l) ?? ''), explode("\n", $t)), fn ($l) => $l !== ''));
    }
}

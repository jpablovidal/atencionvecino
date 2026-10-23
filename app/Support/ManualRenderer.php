<?php

namespace App\Support;

use App\Models\Usuario;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Convierte los manuales (Markdown en resources/manuales) a HTML accesible. El HTML crudo del Markdown se elimina
 * y los enlaces inseguros (javascript:, data:) se bloquean: los manuales son contenido del repositorio, no de usuarios,
 * pero se tratan igualmente como no confiables.
 */
class ManualRenderer
{
    public const PERFILES = [
        'ciudadano' => ['Ciudadano / vecino(a)', 'Ingresar solicitudes, hacer seguimiento, responder devoluciones y descargar documentos notificados.'],
        'funcionario' => ['Funcionario(a)', 'Gestionar solicitudes y expedientes, redactar documentos, visar, derivar, notificar y comunicar oficialmente.'],
        'secretario' => ['Secretario(a) municipal', 'Unidad receptora de DocDigital, visación y firma, expedientes de comunicaciones recibidas.'],
        'alcalde' => ['Alcalde / alcaldesa', 'Firma de decretos, delegaciones de firma y supervisión.'],
        'administrador' => ['Administrador(a) del sistema', 'Mantenedores, Codificador, PISEE, DocDigital, seguridad y diagnóstico.'],
        'auditor' => ['Auditor(a)', 'Consulta de solo lectura, verificación de la cadena de auditoría e incidentes.'],
        'autoridad' => ['Autoridad', 'Consultar el seguimiento de las solicitudes: etapa, estado, quién la tiene y si terminó.'],
        'transparencia' => ['Transparencia activa y pasiva', 'Gestionar consultas de acceso a la información, expedientes, requerimientos, plazos, transparencia activa y reportes.'],
        'oirs' => ['OIRS', 'Visualizar solicitudes, su estado, responsables y plazos vencidos o por vencer.'],
        'oficina-partes' => ['Oficina de Partes', 'Recibir las respuestas finales instruidas y notificarlas al solicitante por Casilla Única con copia al correo, o registrar la entrega física.'],
        'pagos' => ['Encargado(a) de pagos', 'Crear decretos de pago con el expediente de pago y el proceso de compra, generar o cargar el PDF y enviarlo a firma.'],
        'tesorero' => ['Tesorero(a) municipal', 'Generar el egreso de los decretos de pago (fecha, medio de pago, monto y detalle) y firmarlo con firma simple o avanzada.'],
        'publico' => ['Público general', 'Validar documentos, consultar documentos públicos y usar las opciones de accesibilidad.'],
    ];

    /** Manual principal según el rol base. */
    public const POR_ROL = ['funcionario' => 'funcionario', 'secretario' => 'secretario', 'alcalde' => 'alcalde', 'administrador_municipal' => 'funcionario', 'admin' => 'administrador', 'auditor' => 'auditor', 'autoridad' => 'autoridad', 'oirs' => 'oirs',
        'transparencia' => 'transparencia', 'oficina_partes' => 'oficina-partes', 'contabilidad' => 'pagos', 'tesorero' => 'tesorero'];

    /** Manuales adicionales según permisos (quien tiene la función, tiene el manual). */
    private const POR_PERMISO = ['transparencia.ver' => 'transparencia', 'oirs.ver' => 'oirs', 'respuestas.despachar' => 'oficina-partes', 'decretopago.gestionar' => 'pagos', 'egresos.gestionar' => 'tesorero', 'seguimiento.consultar' => 'autoridad'];

    /**
     * Manuales que corresponden al perfil de la persona (el del público siempre). Un(a) superadministrador(a) ve todos.
     *
     * @return list<string> slugs en el orden del catálogo
     */
    public static function perfilesPara(?Usuario $u, bool $esVecino): array
    {
        if (! $u) {
            return ['ciudadano']; // el público y los vecinos ven solo el manual del ciudadano: los demás manuales son para el personal municipal
        }
        $ok = ['publico', 'ciudadano'];
        if ($u) {
            if ($u->veTodo()) {
                return array_keys(self::PERFILES);
            }
            foreach ($u->basesRol() as $b) { // principal y roles adicionales
                if ($p = self::POR_ROL[$b] ?? null) {
                    $ok[] = $p;
                }
            }
            foreach (self::POR_PERMISO as $perm => $slug) {
                if ($u->tienePermiso($perm)) {
                    $ok[] = $slug;
                }
            }
        }

        return array_values(array_filter(array_keys(self::PERFILES), fn ($k) => in_array($k, $ok, true)));
    }

    /** Dirección de la ayuda: el personal ve todos los manuales de su perfil; el público y los vecinos, directamente el manual del ciudadano. */
    public static function ayudaUrl(): string
    {
        return auth()->check() ? route('ayuda') : route('ayuda.manual', 'ciudadano');
    }

    public static function ayudaEtiqueta(): string
    {
        return auth()->check() ? 'Ayuda y manuales' : 'Manual del ciudadano';
    }

    /** Manual del espacio activo (para el recorrido guiado). */
    public static function principalPara(?Usuario $u, bool $esVecino): string
    {
        if ($esVecino || ! $u) {
            return $esVecino ? 'ciudadano' : 'publico';
        }

        return self::POR_ROL[$u->rolBase()] ?? 'funcionario';
    }

    /** @return array{html:string,toc:array<int,array{id:string,texto:string}>,titulo:string}|null */
    public function render(string $perfil): ?array
    {
        $ruta = resource_path('manuales/'.$perfil.'.md');
        if (! isset(self::PERFILES[$perfil]) || ! is_file($ruta)) {
            return null;
        }

        return $this->aHtml((string) file_get_contents($ruta));
    }

    /** @return array{html:string,toc:array<int,array{id:string,texto:string}>,titulo:string} */
    public function aHtml(string $markdown): array
    {
        $env = new Environment([
            'html_input' => 'strip', 'allow_unsafe_links' => false, 'max_nesting_level' => 20,
        ]);
        $env->addExtension(new CommonMarkCoreExtension);
        $env->addExtension(new GithubFlavoredMarkdownExtension);
        $html = (string) (new MarkdownConverter($env))->convert($markdown);
        // ids de encabezados (para el índice y los enlaces internos) y encabezados de columna accesibles
        $usados = [];
        $html = (string) preg_replace_callback('#<h([1-6])>(.*?)</h\1>#s', function ($m) use (&$usados) {
            $base = Str::slug(strip_tags($m[2])) ?: 'seccion';
            $id = $base;
            for ($i = 2; isset($usados[$id]); $i++) {
                $id = $base.'-'.$i;
            }
            $usados[$id] = true;

            return '<h'.$m[1].' id="'.$id.'">'.$m[2].'</h'.$m[1].'>';
        }, $html);
        $html = (string) preg_replace('#<th(?=[\s>])(?![^>]*\bscope=)([^>]*)>#', '<th scope="col"$1>', $html);

        $toc = [];
        preg_match_all('#<h2[^>]*id="([^"]+)"[^>]*>(.*?)</h2>#s', $html, $m, PREG_SET_ORDER);
        foreach ($m as [, $id, $texto]) {
            $toc[] = ['id' => $id, 'texto' => trim(strip_tags($texto))];
        }
        preg_match('#<h1[^>]*>(.*?)</h1>#s', $html, $t);

        return ['html' => $html, 'toc' => $toc, 'titulo' => trim(strip_tags($t[1] ?? 'Manual'))];
    }
}

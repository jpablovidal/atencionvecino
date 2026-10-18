<?php

namespace App\Support;

/** Plantillas de ejemplo (estructuradas) para decretos, oficios y demás tipos de cuerpo. Los cargos y fundamentos son genéricos: revíselos antes de usarlos. */
class PlantillaEjemplos
{
    private const CADENA = [['cargo' => 'Director(a) de Obras Municipales', 'usuario_id' => null], ['cargo' => 'Asesor(a) Jurídico(a)', 'usuario_id' => null], ['cargo' => 'Secretario(a) Municipal', 'usuario_id' => null]];

    private const FIRMAS_ALC = [['cargo' => 'Alcalde(sa)', 'rol' => 'alcalde'], ['cargo' => 'Secretario(a) Municipal', 'rol' => 'secretario']];

    /** @return array<int,array{nombre:string,tipo_codigo:string,tipo_nombre:string,prefijo:string,descripcion:string,estructura:array<string,mixed>}> */
    public static function definiciones(): array
    {
        $e = fn (array $x) => $x + ['cierre' => PlantillaEstructura::CIERRES[$x['cuerpo_tipo']]];

        return [
            ['nombre' => 'Decreto alcaldicio (ejemplo)', 'tipo_codigo' => 'DEC_ALC', 'tipo_nombre' => 'Decreto alcaldicio', 'prefijo' => 'Decreto', 'descripcion' => 'Decreto con vistos, considerandos, resolutivos, doble firma y cadena de visaciones.',
                'estructura' => $e(['materia' => 'Autoriza [materia del decreto] solicitado en el expediente {{expediente}}', 'cuerpo_tipo' => 'decreto',
                    'vistos' => ['Lo dispuesto en la Ley N° 18.695, Orgánica Constitucional de Municipalidades.', 'La Ley N° 19.880, que establece las bases de los procedimientos administrativos.', 'La solicitud folio {{folio}} ingresada en el trámite «{{tramite}}», expediente {{expediente}}.'],
                    'considerando' => ['Que, [fundamento de hecho o de derecho que justifica la medida].', 'Que, la unidad {{unidad}} informó favorablemente la solicitud.'],
                    'cuerpo_html' => '<p>1.- <strong>AUTORÍZASE</strong> [describir lo que se autoriza o aprueba].</p><p>2.- <strong>IMPÚTESE</strong> el gasto que corresponda, si lo hubiere, al ítem presupuestario respectivo.</p><p>3.- <strong>NOTIFÍQUESE</strong> la presente resolución a quien corresponda.</p>',
                    'firmas' => self::FIRMAS_ALC, 'cadena' => self::CADENA])],
            ['nombre' => 'Decreto de pago (ejemplo)', 'tipo_codigo' => 'DECRETO_PAGO', 'tipo_nombre' => 'Decreto de pago', 'prefijo' => 'Decreto de pago', 'descripcion' => 'Decreto de pago a proveedores con los campos del módulo (proveedor, monto, concepto, orden de compra, factura e imputación).',
                'estructura' => $e(['materia' => 'Autoriza el pago de {{monto}} a {{proveedor_nombre}}', 'cuerpo_tipo' => 'decreto',
                    'vistos' => ['Lo dispuesto en la Ley N° 18.695, Orgánica Constitucional de Municipalidades.', 'La Ley N° 19.886, de bases sobre contratos administrativos de suministro y prestación de servicios, y su reglamento.', 'La orden de compra {{orden_compra}} y la factura {{factura}} del proceso de compra {{expediente_compra}}.', 'El expediente de pago {{expediente}}.'],
                    'considerando' => ['Que, el pago corresponde a: {{concepto}}.', 'Que, la unidad {{unidad}} certificó la recepción conforme de los bienes o servicios.', 'Que, existe disponibilidad presupuestaria en la imputación {{imputacion}}.'],
                    'cuerpo_html' => '<p>1.- <strong>AUTORÍZASE</strong> el pago de <strong>{{monto}}</strong> ({{monto_letras}}) a <strong>{{proveedor_nombre}}</strong>, RUT {{proveedor_run}}, por concepto de {{concepto}}.</p><p>2.- <strong>IMPÚTESE</strong> el gasto a {{imputacion}}.</p><p>3.- <strong>PÁGUESE</strong> por la Tesorería Municipal, previa verificación de la documentación de respaldo; Tesorería emitirá el egreso respectivo.</p>',
                    'firmas' => self::FIRMAS_ALC, 'cadena' => [['cargo' => 'Director(a) de Administración y Finanzas', 'usuario_id' => null], ['cargo' => 'Director(a) de Control', 'usuario_id' => null], ['cargo' => 'Asesor(a) Jurídico(a)', 'usuario_id' => null]]])],
            ['nombre' => 'Oficio ordinario (ejemplo)', 'tipo_codigo' => 'OFICIO', 'tipo_nombre' => 'Oficio', 'prefijo' => 'Oficio', 'descripcion' => 'Oficio de respuesta con antecedentes, fundamentos y una firma.',
                'estructura' => $e(['materia' => 'Responde la solicitud folio {{folio}}', 'cuerpo_tipo' => 'oficio',
                    'vistos' => ['Solicitud folio {{folio}} presentada por {{solicitante_nombre}} en el trámite «{{tramite}}».', 'Expediente {{expediente}}.'],
                    'considerando' => ['Que, la solicitud fue revisada por {{unidad}}.'],
                    'cuerpo_html' => '<p>De mi consideración:</p><p>Junto con saludar, y en respuesta a la solicitud individualizada en los antecedentes, informo a usted que [detalle de la respuesta].</p>',
                    'firmas' => [['cargo' => 'Director(a) de la unidad', 'rol' => 'funcionario']], 'cadena' => [['cargo' => 'Director(a) de Obras Municipales', 'usuario_id' => null]]])],
            ['nombre' => 'Ordenanza municipal (ejemplo)', 'tipo_codigo' => 'ORDENANZA', 'tipo_nombre' => 'Ordenanza', 'prefijo' => 'Ordenanza', 'descripcion' => 'Ordenanza con título, artículos y publicación.',
                'estructura' => $e(['materia' => 'Ordenanza sobre [materia regulada]', 'cuerpo_tipo' => 'ordenanza',
                    'vistos' => ['Lo dispuesto en la Ley N° 18.695, Orgánica Constitucional de Municipalidades.', 'El acuerdo del Concejo Municipal adoptado en la sesión [N°] de fecha [fecha].'],
                    'considerando' => ['Que, es necesario regular [materia] en la comuna.'],
                    'cuerpo_html' => '<p><strong>TÍTULO I — DISPOSICIONES GENERALES</strong></p><p><strong>Artículo 1°.-</strong> [Objeto de la ordenanza].</p><p><strong>Artículo 2°.-</strong> [Ámbito de aplicación].</p><p><strong>TÍTULO II — [NOMBRE]</strong></p><p><strong>Artículo 3°.-</strong> [Texto].</p>',
                    'firmas' => self::FIRMAS_ALC, 'cadena' => self::CADENA])],
            ['nombre' => 'Reglamento interno (ejemplo)', 'tipo_codigo' => 'REGLAMENTO', 'tipo_nombre' => 'Reglamento', 'prefijo' => 'Reglamento', 'descripcion' => 'Reglamento con capítulos y artículos.',
                'estructura' => $e(['materia' => 'Aprueba el reglamento de [materia]', 'cuerpo_tipo' => 'reglamento',
                    'vistos' => ['Lo dispuesto en la Ley N° 18.695, Orgánica Constitucional de Municipalidades.'],
                    'considerando' => ['Que, se requiere un marco claro de funcionamiento para [materia].'],
                    'cuerpo_html' => '<p><strong>CAPÍTULO I — NORMAS GENERALES</strong></p><p><strong>Artículo 1°.-</strong> [Objeto].</p><p><strong>CAPÍTULO II — [NOMBRE]</strong></p><p><strong>Artículo 2°.-</strong> [Texto].</p>',
                    'firmas' => self::FIRMAS_ALC, 'cadena' => self::CADENA])],
            ['nombre' => 'Instructivo (ejemplo)', 'tipo_codigo' => 'INSTRUCTIVO', 'tipo_nombre' => 'Instructivo', 'prefijo' => 'Instructivo', 'descripcion' => 'Instructivo interno con instrucciones numeradas.',
                'estructura' => $e(['materia' => 'Instruye sobre [materia]', 'cuerpo_tipo' => 'instructivo',
                    'vistos' => [], 'considerando' => ['Que, es necesario uniformar el procedimiento de [materia].'],
                    'cuerpo_html' => '<p>1.- Las unidades municipales deberán [instrucción].</p><p>2.- El plazo para dar cumplimiento será de [N] días hábiles.</p><p>3.- Las consultas se dirigirán a [unidad o cargo].</p>',
                    'firmas' => [['cargo' => 'Alcalde(sa)', 'rol' => 'alcalde']], 'cadena' => [['cargo' => 'Secretario(a) Municipal', 'usuario_id' => null]]])],
        ];
    }
}
